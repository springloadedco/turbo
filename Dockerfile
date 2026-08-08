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

# Laravel Forge CLI. COMPOSER_HOME is set for this layer only — as a persistent
# ENV it would also redirect the agent's own `composer global require` into this
# root-owned directory.
RUN COMPOSER_HOME=/opt/composer-global COMPOSER_ALLOW_SUPERUSER=1 \
  composer global require laravel/forge-cli --no-interaction --no-progress \
  && chmod -R o+rX /opt/composer-global \
  && ln -s /opt/composer-global/vendor/bin/forge /usr/local/bin/forge

# Chromium via Playwright (works on both amd64 and arm64).
# The apt chromium-browser package is a non-functional snap stub on ARM64.
# Install to /opt/chromium so the agent user can access it (default /root is 700).
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/chromium
# Playwright prunes revisions its `.links` entries no longer reference. This
# install runs as root via `npx --yes`, so its link points into /root/.npm,
# which uid 1000 cannot read — the agent's own `playwright install` would
# classify the link broken and delete the revision out from under the symlink
# below. Opting out of the GC keeps /usr/local/bin/chromium resolvable.
ENV PLAYWRIGHT_SKIP_BROWSER_GC=1
# The extracted directory name is not stable across architectures: amd64 gets
# Chrome for Testing (chrome-linux64/), arm64 gets Playwright's own build
# (chrome-linux/) — a per-platform table in playwright-core, not something to
# rely on here. Match on the registry directory instead, which is stable on
# both. `-name chrome` is what excludes the headless shell (its binary is
# `headless_shell`); `-path` is what makes the match independent of the
# extracted subdirectory name and keeps other browsers' trees out.
#
# Sort by revision and take the newest: `playwright install` only ever adds
# revision directories, and unsorted `find | head -1` returns readdir order,
# which is the *oldest* one in practice. Capturing find's output on its own
# line also lets a genuine find failure abort here instead of arriving as an
# empty match and being misreported as a layout change.
RUN npx --yes playwright install --with-deps chromium \
  && rm -rf /var/lib/apt/lists/* \
  && chown -R agent:agent /opt/chromium \
  && chmod -R o+rX /opt/chromium \
  && CHROMIUM_MATCHES=$(find /opt/chromium -type f -name chrome -perm -u+x -path '*/chromium-*') \
  && CHROMIUM_PATH=$(printf '%s\n' "$CHROMIUM_MATCHES" | sort -V | tail -1) \
  && { [ -n "$CHROMIUM_PATH" ] || { echo "chromium: no chrome binary under /opt/chromium — Playwright's layout changed" >&2; exit 1; }; } \
  && ln -sf "$CHROMIUM_PATH" /usr/local/bin/chromium

# Agent Browser https://agent-browser.dev/installation
RUN npm install -g agent-browser
ENV AGENT_BROWSER_EXECUTABLE_PATH=/usr/local/bin/chromium

# IMPORTANT: run as the sandbox base user
USER agent
