#!/bin/bash
# Fix native binaries corrupted by Docker sandbox workspace file sync.
#
# The sandbox uses file synchronization (not volume mounts) for the workspace.
# npm install extracts packages correctly, but the sync layer corrupts native
# binary files during the copy — and re-corrupts them if you copy good ones in.
#
# This script performs a "shadow install" outside the workspace where file sync
# doesn't interfere, then SYMLINKS the intact native binaries into the workspace.
# Symlinks keep the actual binary content outside the synced directory.
#
# Usage: fix-native-binaries <workspace-path>

set -euo pipefail

WORKSPACE="${1:?Usage: fix-native-binaries <workspace-path>}"
SHADOW_DIR="/home/agent/.npm-shadow"

if [ ! -f "$WORKSPACE/package.json" ]; then
    echo "[fix-native-binaries] No package.json in $WORKSPACE, nothing to do"
    exit 0
fi

if [ ! -d "$WORKSPACE/node_modules" ]; then
    echo "[fix-native-binaries] No node_modules in $WORKSPACE, nothing to do"
    exit 0
fi

# ------------------------------------------------------------------
# 1. Hash check — skip shadow install if deps unchanged
# ------------------------------------------------------------------
HASH_FILES=("$WORKSPACE/package.json")
if [ -f "$WORKSPACE/package-lock.json" ]; then
    HASH_FILES+=("$WORKSPACE/package-lock.json")
fi
CURRENT_HASH=$(cat -- "${HASH_FILES[@]}" | md5sum | cut -d' ' -f1)

STORED_HASH=""
if [ -f "$SHADOW_DIR/.deps-hash" ]; then
    STORED_HASH=$(cat "$SHADOW_DIR/.deps-hash")
fi

if [ "$CURRENT_HASH" = "$STORED_HASH" ] && [ -d "$SHADOW_DIR/node_modules" ]; then
    echo "[fix-native-binaries] Shadow dir up to date (hash match), symlinking binaries only"
else
    # ------------------------------------------------------------------
    # 2. Shadow install — get intact binaries outside workspace
    # ------------------------------------------------------------------
    echo "[fix-native-binaries] Running shadow install in $SHADOW_DIR"
    mkdir -p "$SHADOW_DIR"
    cp "$WORKSPACE/package.json" "$SHADOW_DIR/"
    [ -f "$WORKSPACE/package-lock.json" ] && cp "$WORKSPACE/package-lock.json" "$SHADOW_DIR/"

    cd "$SHADOW_DIR"
    command npm install --ignore-scripts --no-audit --no-fund 2>&1

    echo "$CURRENT_HASH" > "$SHADOW_DIR/.deps-hash"
fi

# ------------------------------------------------------------------
# 3. Symlink native binaries from shadow into workspace
# ------------------------------------------------------------------
# Replace corrupted workspace binaries with symlinks to the shadow copies.
# Symlinks keep the actual binary content outside the synced workspace
# directory, preventing the file sync from corrupting them.
FIXED=0

symlink_binary() {
    local shadow_bin="$1"
    local rel_path="${shadow_bin#$SHADOW_DIR/}"
    local workspace_bin="$WORKSPACE/$rel_path"

    # Skip if workspace file doesn't exist
    [ -f "$workspace_bin" ] || [ -L "$workspace_bin" ] || return 0

    # Skip if already symlinked to the right target
    if [ -L "$workspace_bin" ]; then
        local current_target
        current_target=$(readlink "$workspace_bin")
        if [ "$current_target" = "$shadow_bin" ]; then
            return 0
        fi
    fi

    # Replace the corrupted binary with a symlink to the intact shadow copy
    rm -f "$workspace_bin"
    ln -s "$shadow_bin" "$workspace_bin"
    echo "[fix-native-binaries] Symlinked: $rel_path -> $shadow_bin"
    FIXED=$((FIXED + 1))
}

# Find ELF binaries in scoped packages (e.g., @esbuild/linux-arm64/bin/esbuild)
while IFS= read -r -d '' shadow_bin; do
    symlink_binary "$shadow_bin"
done < <(find "$SHADOW_DIR/node_modules/@"*/*/bin -type f -executable 2>/dev/null -print0)

# Find native addon .node files in scoped packages
while IFS= read -r -d '' shadow_bin; do
    symlink_binary "$shadow_bin"
done < <(find "$SHADOW_DIR/node_modules/@"*/*/ -maxdepth 1 -name "*.node" -type f 2>/dev/null -print0)

# Find native addon .node files in unscoped packages
while IFS= read -r -d '' shadow_bin; do
    symlink_binary "$shadow_bin"
done < <(find "$SHADOW_DIR/node_modules" -maxdepth 2 -name "*.node" -type f -not -path "*/node_modules/@*" 2>/dev/null -print0)

# Find executable binaries in unscoped packages' bin directories
while IFS= read -r -d '' shadow_bin; do
    symlink_binary "$shadow_bin"
done < <(find "$SHADOW_DIR/node_modules" -maxdepth 3 -path "*/bin/*" -type f -executable -not -path "*/node_modules/@*" -not -path "*/.bin/*" 2>/dev/null -print0)

if [ "$FIXED" -gt 0 ]; then
    echo "[fix-native-binaries] Symlinked $FIXED native binary/binaries from shadow dir"
else
    echo "[fix-native-binaries] All binaries already symlinked"
fi
