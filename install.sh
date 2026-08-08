#!/usr/bin/env bash
# Install the `turbo` shell shorthand.
#
#   curl -fsSL https://raw.githubusercontent.com/springloadedco/turbo/main/install.sh | bash
#
# Clones this repo to ~/.turbo and sources shell/turbo.sh from your shell rc, so
# `turbo` becomes shorthand for `sbx run --kit ~/.turbo/kit <agent>`.
#
# Referencing the kit as a local directory sidesteps the digest-pinning rule that
# applies to remote kit references, so updating is just `turbo-update`.

set -euo pipefail

TURBO_HOME="${TURBO_HOME:-$HOME/.turbo}"
TURBO_REPO="${TURBO_REPO:-https://github.com/springloadedco/turbo.git}"
TURBO_BRANCH="${TURBO_BRANCH:-main}"

START_MARKER="# >>> turbo >>>"
END_MARKER="# <<< turbo <<<"

info() { printf '\033[0;36m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[0;33mwarning:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[0;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

command -v git >/dev/null || die "git is required"

if ! command -v sbx >/dev/null; then
    warn "sbx not found on PATH. Install it with: brew install docker/tap/sbx"
fi

# ---------------------------------------------------------------------------
# Clone or update the checkout
# ---------------------------------------------------------------------------
if [ -d "$TURBO_HOME/.git" ]; then
    info "Updating $TURBO_HOME"
    git -C "$TURBO_HOME" fetch --quiet origin "$TURBO_BRANCH"
    git -C "$TURBO_HOME" checkout --quiet "$TURBO_BRANCH"
    git -C "$TURBO_HOME" merge --quiet --ff-only "origin/$TURBO_BRANCH"
elif [ -e "$TURBO_HOME" ]; then
    die "$TURBO_HOME exists but is not a git checkout. Move it aside and re-run."
else
    info "Cloning into $TURBO_HOME"
    git clone --quiet --depth 1 --branch "$TURBO_BRANCH" "$TURBO_REPO" "$TURBO_HOME"
fi

[ -f "$TURBO_HOME/shell/turbo.sh" ] || die "checkout is missing shell/turbo.sh"

# ---------------------------------------------------------------------------
# Wire it into the shell rc
# ---------------------------------------------------------------------------
# Prefer the login shell over whatever is executing this script — piping through
# `bash` would otherwise always pick bash, even for zsh users.
shell_name="$(basename "${SHELL:-bash}")"
case "$shell_name" in
    zsh)  rc="$HOME/.zshrc" ;;
          # macOS terminals start bash as a *login* shell, which reads
          # .bash_profile and never .bashrc — so target it whether or not it
          # already exists, otherwise the block lands in a file nothing sources.
    bash) if [ "$(uname -s)" = "Darwin" ]; then
              rc="$HOME/.bash_profile"
          else
              rc="$HOME/.bashrc"
          fi ;;
    *)    rc="" ;;
esac

snippet="$START_MARKER
export TURBO_HOME=\"$TURBO_HOME\"
[ -f \"\$TURBO_HOME/shell/turbo.sh\" ] && . \"\$TURBO_HOME/shell/turbo.sh\"
$END_MARKER"

if [ -z "$rc" ]; then
    warn "Unrecognised shell '$shell_name'. Add this to your shell rc by hand:"
    printf '\n%s\n\n' "$snippet"
elif [ -f "$rc" ] && grep -qF "$START_MARKER" "$rc"; then
    # The block sources a tracked file, so it stays current across updates on its
    # own — but it also pins TURBO_HOME, so a re-run that relocates the checkout
    # has to rewrite it or the old path keeps winning.
    if grep -qF "export TURBO_HOME=\"$TURBO_HOME\"" "$rc"; then
        info "Already wired into $rc"
    elif ! grep -qF "$END_MARKER" "$rc"; then
        warn "The turbo block in $rc has no closing marker — leaving it alone."
        warn "Replace it by hand with:"
        printf '\n%s\n\n' "$snippet"
    else
        info "Repointing the turbo block in $rc at $TURBO_HOME"
        tmp="$(mktemp)"
        awk -v start="$START_MARKER" -v end="$END_MARKER" -v block="$snippet" '
            $0 == start { skip = 1; print block; next }
            $0 == end   { skip = 0; next }
            !skip
        ' "$rc" > "$tmp"
        # Write through the original file so its mode and inode survive.
        cat "$tmp" > "$rc"
        rm -f "$tmp"
    fi
else
    info "Adding the turbo function to $rc"
    printf '\n%s\n' "$snippet" >> "$rc"
fi

cat <<EOF

$(info "Done.")

  Restart your shell, or:  source $rc

  turbo                 # claude, on the prebuilt Turbo image
  turbo codex           # any other agent, toolchain installed by the kit
  turbo claude -- --continue
  turbo-update          # pull the latest kit

EOF
