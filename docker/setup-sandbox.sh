#!/bin/bash
# Prepare the sandbox environment for development.
# Baked into the image at /usr/local/bin/setup-sandbox.
# Run via `docker sandbox exec` before each Claude session.
#
# Usage: setup-sandbox <workspace-path> [host:ip host:ip ...]
#
# Features:
# 1. Host access — adds /etc/hosts entries for dev server access

set -euo pipefail

WORKSPACE="${1:?Usage: setup-sandbox <workspace-path> [host:ip ...]}"
shift

echo "[setup-sandbox] Preparing sandbox for $WORKSPACE"

# ------------------------------------------------------------------
# Host access — /etc/hosts entries
# ------------------------------------------------------------------
# Remaining args are hostname:ip pairs
for ENTRY in "$@"; do
    HOSTNAME="${ENTRY%%:*}"
    HOST_IP="${ENTRY#*:}"

    # Add to /etc/hosts if not already present
    if ! grep -q "$HOSTNAME" /etc/hosts 2>/dev/null; then
        echo "[setup-sandbox] Adding host entry: $HOSTNAME -> $HOST_IP"
        echo "$HOST_IP $HOSTNAME" | sudo tee -a /etc/hosts > /dev/null
    fi
done

echo "[setup-sandbox] Done"
