#!/usr/bin/env bash
# Turbo OAuth callback relay.
#
# Bridges 0.0.0.0:PORT to 127.0.0.1:PORT inside the sandbox so that
# Claude Code's localhost-bound MCP OAuth listener becomes reachable via
# `sbx ports --publish` (which routes host traffic to eth0).
#
# Port is set via TURBO_OAUTH_PORT (defaults to 33418).
set -euo pipefail

PORT="${TURBO_OAUTH_PORT:-33418}"

exec socat \
    "TCP-LISTEN:${PORT},bind=0.0.0.0,reuseaddr,fork" \
    "TCP:127.0.0.1:${PORT}"
