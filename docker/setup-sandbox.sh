#!/bin/bash
# Prepare the sandbox environment for development.
# Baked into the image at /usr/local/bin/setup-sandbox.
# Run via `docker sandbox exec` before each Claude session.
#
# Usage: setup-sandbox <workspace-path> [host:ip host:ip ...]
#
# Features:
# 1. Node modules isolation — installs to sandbox-local dir
# 2. Host access — adds /etc/hosts entries for dev server access

set -euo pipefail

WORKSPACE="${1:?Usage: setup-sandbox <workspace-path> [host:ip ...]}"
shift

# ------------------------------------------------------------------
# 1. Node modules isolation
# ------------------------------------------------------------------
DEPS_DIR="/home/agent/.sandbox-deps"

if [ -f "$WORKSPACE/package.json" ]; then
    # Determine lockfile
    LOCK_FILE=""
    if [ -f "$WORKSPACE/package-lock.json" ]; then
        LOCK_FILE="$WORKSPACE/package-lock.json"
    fi

    # Check if deps need updating (compare hash of package.json + lockfile)
    HASH_INPUT="$WORKSPACE/package.json"
    if [ -n "$LOCK_FILE" ]; then
        HASH_INPUT="$HASH_INPUT $LOCK_FILE"
    fi
    CURRENT_HASH=$(cat $HASH_INPUT | md5sum | cut -d' ' -f1)

    STORED_HASH=""
    if [ -f "$DEPS_DIR/.deps-hash" ]; then
        STORED_HASH=$(cat "$DEPS_DIR/.deps-hash")
    fi

    if [ "$CURRENT_HASH" != "$STORED_HASH" ]; then
        mkdir -p "$DEPS_DIR"
        cp "$WORKSPACE/package.json" "$DEPS_DIR/"
        if [ -n "$LOCK_FILE" ]; then
            cp "$LOCK_FILE" "$DEPS_DIR/"
        fi

        cd "$DEPS_DIR"
        # --ignore-scripts avoids postinstall crashes from native binaries
        # that may not run on LinuxKit (e.g., esbuild's Go binary).
        npm install --ignore-scripts --no-audit --no-fund 2>&1

        echo "$CURRENT_HASH" > "$DEPS_DIR/.deps-hash"
    fi

    # Configure NODE_PATH + PATH (idempotent)
    ENV_FILE="/etc/sandbox-persistent.sh"
    if ! grep -q "SANDBOX_NODE_DEPS" "$ENV_FILE" 2>/dev/null; then
        echo "# Sandbox node_modules isolation (added by setup-sandbox)" >> "$ENV_FILE"
        echo "export SANDBOX_NODE_DEPS=$DEPS_DIR/node_modules" >> "$ENV_FILE"
        echo "export NODE_PATH=$DEPS_DIR/node_modules" >> "$ENV_FILE"
        echo 'export PATH="'"$DEPS_DIR"'/node_modules/.bin:$PATH"' >> "$ENV_FILE"
    fi
fi

# ------------------------------------------------------------------
# 2. Host access — /etc/hosts entries
# ------------------------------------------------------------------
# Remaining args are hostname:ip pairs
for ENTRY in "$@"; do
    HOSTNAME="${ENTRY%%:*}"
    HOST_IP="${ENTRY#*:}"

    # Add to /etc/hosts if not already present
    if ! grep -q "$HOSTNAME" /etc/hosts 2>/dev/null; then
        echo "$HOST_IP $HOSTNAME" >> /etc/hosts
    fi
done
