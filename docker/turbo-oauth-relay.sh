#!/usr/bin/env bash
# Turbo OAuth callback relay.
#
# Bridges 0.0.0.0:LISTEN_PORT (reachable via sbx port publish) to
# 127.0.0.1:TARGET_PORT (where Claude Code's MCP OAuth listener lives).
#
# The two ports MUST differ — Linux treats 0.0.0.0:PORT and 127.0.0.1:PORT
# as overlapping bindings, so a relay sharing the port with Claude Code
# would prevent Claude Code from binding its loopback listener.
#
# Env vars:
#   TURBO_OAUTH_LISTEN_PORT  — port the relay binds on 0.0.0.0 (default 33419)
#   TURBO_OAUTH_TARGET_PORT  — port the relay forwards to on 127.0.0.1 (default 33418)
#
# A PID file at /tmp/turbo-oauth-relay.pid lets callers check liveness.
set -euo pipefail

LISTEN_PORT="${TURBO_OAUTH_LISTEN_PORT:-33419}"
TARGET_PORT="${TURBO_OAUTH_TARGET_PORT:-33418}"
PIDFILE="/tmp/turbo-oauth-relay.pid"

# Record this shell's PID before exec replaces it with socat (the PID
# is preserved across exec, so the file will point at the live relay).
echo $$ > "$PIDFILE"

exec socat \
    "TCP-LISTEN:${LISTEN_PORT},bind=0.0.0.0,reuseaddr,fork" \
    "TCP:127.0.0.1:${TARGET_PORT}" \
    >/dev/null 2>&1
