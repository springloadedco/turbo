#!/bin/bash
# Fix native binaries corrupted by Docker sandbox workspace file sync.
#
# The sandbox uses file synchronization (not volume mounts) for the workspace.
# npm install extracts packages correctly, but the sync layer corrupts native
# binary files during the copy. ELF headers stay intact but content is scrambled.
#
# This script performs a "shadow install" outside the workspace where file sync
# doesn't interfere, then copies the intact native binaries back.
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
HASH_INPUT="$WORKSPACE/package.json"
if [ -f "$WORKSPACE/package-lock.json" ]; then
    HASH_INPUT="$HASH_INPUT $WORKSPACE/package-lock.json"
fi
CURRENT_HASH=$(cat $HASH_INPUT | md5sum | cut -d' ' -f1)

STORED_HASH=""
if [ -f "$SHADOW_DIR/.deps-hash" ]; then
    STORED_HASH=$(cat "$SHADOW_DIR/.deps-hash")
fi

if [ "$CURRENT_HASH" = "$STORED_HASH" ] && [ -d "$SHADOW_DIR/node_modules" ]; then
    echo "[fix-native-binaries] Shadow dir up to date (hash match), copying binaries only"
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
# 3. Copy native binaries from shadow to workspace
# ------------------------------------------------------------------
COPIED=0

# Find ELF binaries in scoped packages (e.g., @esbuild/linux-arm64/bin/esbuild)
while IFS= read -r -d '' shadow_bin; do
    rel_path="${shadow_bin#$SHADOW_DIR/}"
    workspace_bin="$WORKSPACE/$rel_path"

    if [ -f "$workspace_bin" ]; then
        shadow_md5=$(md5sum "$shadow_bin" | cut -d' ' -f1)
        workspace_md5=$(md5sum "$workspace_bin" | cut -d' ' -f1)

        if [ "$shadow_md5" != "$workspace_md5" ]; then
            echo "[fix-native-binaries] Fixing: $rel_path"
            cp "$shadow_bin" "$workspace_bin"
            COPIED=$((COPIED + 1))
        fi
    fi
done < <(find "$SHADOW_DIR/node_modules/@"*/*/bin -type f -executable 2>/dev/null -print0)

# Find native addon .node files in scoped packages
while IFS= read -r -d '' shadow_bin; do
    rel_path="${shadow_bin#$SHADOW_DIR/}"
    workspace_bin="$WORKSPACE/$rel_path"

    if [ -f "$workspace_bin" ]; then
        shadow_md5=$(md5sum "$shadow_bin" | cut -d' ' -f1)
        workspace_md5=$(md5sum "$workspace_bin" | cut -d' ' -f1)

        if [ "$shadow_md5" != "$workspace_md5" ]; then
            echo "[fix-native-binaries] Fixing: $rel_path"
            cp "$shadow_bin" "$workspace_bin"
            COPIED=$((COPIED + 1))
        fi
    fi
done < <(find "$SHADOW_DIR/node_modules/@"*/*/ -maxdepth 1 -name "*.node" -type f 2>/dev/null -print0)

# Find native addon .node files in unscoped packages
while IFS= read -r -d '' shadow_bin; do
    rel_path="${shadow_bin#$SHADOW_DIR/}"
    workspace_bin="$WORKSPACE/$rel_path"

    if [ -f "$workspace_bin" ]; then
        shadow_md5=$(md5sum "$shadow_bin" | cut -d' ' -f1)
        workspace_md5=$(md5sum "$workspace_bin" | cut -d' ' -f1)

        if [ "$shadow_md5" != "$workspace_md5" ]; then
            echo "[fix-native-binaries] Fixing: $rel_path"
            cp "$shadow_bin" "$workspace_bin"
            COPIED=$((COPIED + 1))
        fi
    fi
done < <(find "$SHADOW_DIR/node_modules" -maxdepth 2 -name "*.node" -type f -not -path "*/node_modules/@*" 2>/dev/null -print0)

# Find executable binaries in unscoped packages' bin directories
while IFS= read -r -d '' shadow_bin; do
    rel_path="${shadow_bin#$SHADOW_DIR/}"
    workspace_bin="$WORKSPACE/$rel_path"

    if [ -f "$workspace_bin" ]; then
        shadow_md5=$(md5sum "$shadow_bin" | cut -d' ' -f1)
        workspace_md5=$(md5sum "$workspace_bin" | cut -d' ' -f1)

        if [ "$shadow_md5" != "$workspace_md5" ]; then
            echo "[fix-native-binaries] Fixing: $rel_path"
            cp "$shadow_bin" "$workspace_bin"
            COPIED=$((COPIED + 1))
        fi
    fi
done < <(find "$SHADOW_DIR/node_modules" -maxdepth 3 -path "*/bin/*" -type f -executable -not -path "*/node_modules/@*" -not -path "*/.bin/*" 2>/dev/null -print0)

if [ "$COPIED" -gt 0 ]; then
    echo "[fix-native-binaries] Fixed $COPIED corrupted binary/binaries"
else
    echo "[fix-native-binaries] No corrupted binaries found"
fi
