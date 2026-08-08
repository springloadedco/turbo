# Turbo shell helpers. Sourced from your shell rc by install.sh.
# Works in bash and zsh — keep this POSIX-ish, no arrays.

# Where the Turbo checkout lives. Override before sourcing to relocate it.
: "${TURBO_HOME:=$HOME/.turbo}"

# Template image used for the claude agent. It is built on the claude-code base,
# so it is deliberately NOT used for other agents — they install the toolchain
# through the kit's guarded install hooks instead. Set TURBO_TEMPLATE= (empty)
# to always start from the stock base image.
#
# `=` rather than `:=` on purpose: an empty TURBO_TEMPLATE is the documented
# opt-out, and `:=` would overwrite it with the default on every shell start.
: "${TURBO_TEMPLATE=docker.io/springloadedco/turbo:latest}"

# turbo [AGENT] [SBX_ARGS...]
#
# Run an agent in a sandbox with the Turbo kit applied. AGENT defaults to
# claude. Anything after the agent is passed straight through to `sbx run`, so
# `turbo claude -- --continue` and `turbo codex --name scratch` both work.
turbo() {
    # Only take $1 as the agent when it actually looks like one. A leading `-`
    # means the caller went straight to sbx flags (`turbo --name scratch`) or to
    # agent args (`turbo -- --continue`), so the default agent stands and the
    # word has to survive into "$@".
    local agent=claude
    case "${1-}" in
        ""|-*) ;;
        *) agent="$1"; shift ;;
    esac

    if [ ! -d "$TURBO_HOME/kit" ]; then
        echo "turbo: no kit at $TURBO_HOME/kit — run the installer again" >&2
        return 1
    fi

    if [ "$agent" = "claude" ] && [ -n "$TURBO_TEMPLATE" ]; then
        sbx run --kit "$TURBO_HOME/kit" --template "$TURBO_TEMPLATE" "$agent" "$@"
    else
        sbx run --kit "$TURBO_HOME/kit" "$agent" "$@"
    fi
}

# turbo-update — pull the latest kit.
turbo-update() {
    git -C "$TURBO_HOME" pull --ff-only && echo "turbo: updated to $(git -C "$TURBO_HOME" rev-parse --short HEAD)"
}
