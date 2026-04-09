#!/usr/bin/env bash
# Turbo OAuth callback relay.
#
# Bridges 0.0.0.0:PORT to 127.0.0.1:PORT inside the sandbox so that
# Claude Code's localhost-bound MCP OAuth listener becomes reachable via
# `sbx ports --publish` (which routes host traffic to eth0).
#
# Port is set via TURBO_OAUTH_PORT (defaults to 33418).
# A PID file at /tmp/turbo-oauth-relay.pid lets callers check liveness.
set -euo pipefail

PORT="${TURBO_OAUTH_PORT:-33418}"
PIDFILE="/tmp/turbo-oauth-relay.pid"

# Record this shell's PID before exec replaces it with socat (the PID
# is preserved across exec, so the file will point at the live relay).
echo $$ > "$PIDFILE"

exec socat \
    "TCP-LISTEN:${PORT},bind=0.0.0.0,reuseaddr,fork" \
    "TCP:127.0.0.1:${PORT}" \
    >/dev/null 2>&1
