FROM docker/sandbox-templates:claude-code

USER root

RUN apt-get update && apt-get install -y --no-install-recommends \
  php-cli php-mbstring php-xml php-curl php-zip php-intl php-bcmath php-sqlite3 php-mysql php-gd \
  php-redis php-pgsql php-imagick php-memcached \
  unzip ca-certificates \
  && rm -rf /var/lib/apt/lists/*

# Node.js 22 (base image ships v20 which is too old for modern TypeScript)
RUN npm install -g n && n 22

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

# Chromium via Playwright (works on both amd64 and arm64).
# The apt chromium-browser package is a non-functional snap stub on ARM64.
# Install to /opt/chromium so the agent user can access it (default /root is 700).
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/chromium
RUN npx --yes playwright install --with-deps chromium \
  && chmod -R o+rx /opt/chromium \
  && CHROMIUM_PATH=$(find /opt/chromium -name chrome -path '*/chrome-linux/*' | head -1) \
  && ln -s "$CHROMIUM_PATH" /usr/local/bin/chromium

# Agent Browser https://agent-browser.dev/installation
RUN npm install -g agent-browser
ENV AGENT_BROWSER_EXECUTABLE_PATH=/usr/local/bin/chromium

# Configure gh as git credential helper so git clone works with GH_TOKEN at runtime.
# Rewrite SSH URLs to HTTPS so tools that default to SSH (e.g. Claude Code plugin
# installer) use the credential helper instead of missing SSH keys.
RUN git config --system credential.https://github.com.helper '!/usr/bin/gh auth git-credential' \
  && git config --system credential.https://gist.github.com.helper '!/usr/bin/gh auth git-credential' \
  && git config --system url."https://github.com/".insteadOf "git@github.com:"

# Sandbox preparation script (host access)
COPY docker/setup-sandbox.sh /usr/local/bin/setup-sandbox
RUN chmod +x /usr/local/bin/setup-sandbox

# Fix native binary corruption from workspace file sync
COPY docker/fix-native-binaries.sh /usr/local/bin/fix-native-binaries
RUN chmod +x /usr/local/bin/fix-native-binaries

# npm wrapper — auto-fixes native binaries corrupted by workspace file sync.
# Intercepts npm install/i/ci, runs with --ignore-scripts to avoid postinstall
# crashes, then uses fix-native-binaries to shadow-install and symlink intact binaries.
RUN printf '%s\n' \
  'npm() {' \
  '  # Only intercept install commands' \
  '  case "${1:-}" in' \
  '    install|i|ci)' \
  '      ;;' \
  '    *)' \
  '      command npm "$@"' \
  '      return $?' \
  '      ;;' \
  '  esac' \
  '  ' \
  '  # Pass through if not in a workspace (synced) directory' \
  '  case "$PWD" in' \
  '    /Users/*|/home/*/workspace/*)' \
  '      ;;' \
  '    *)' \
  '      command npm "$@"' \
  '      return $?' \
  '      ;;' \
  '  esac' \
  '  ' \
  '  # Check if --ignore-scripts is already passed' \
  '  local has_ignore_scripts=false' \
  '  for arg in "$@"; do' \
  '    if [ "$arg" = "--ignore-scripts" ]; then' \
  '      has_ignore_scripts=true' \
  '      break' \
  '    fi' \
  '  done' \
  '  ' \
  '  # Run npm install with --ignore-scripts to prevent postinstall crashes' \
  '  if [ "$has_ignore_scripts" = "true" ]; then' \
  '    command npm "$@"' \
  '  else' \
  '    command npm "$@" --ignore-scripts' \
  '  fi' \
  '  local npm_exit=$?' \
  '  ' \
  '  # Fix corrupted native binaries via shadow install' \
  '  if [ $npm_exit -eq 0 ] && [ -f "$PWD/package.json" ]; then' \
  '    fix-native-binaries "$PWD"' \
  '  fi' \
  '  ' \
  '  return $npm_exit' \
  '}' \
  >> /etc/sandbox-persistent.sh

# Sandbox-only skill — teaches Claude about the npm binary workaround
COPY --chown=agent:agent docker/skills/fix-native-binaries/SKILL.md /home/agent/.claude/skills/fix-native-binaries/SKILL.md

# IMPORTANT: run as the sandbox base user
USER agent
