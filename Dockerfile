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

# IMPORTANT: run as the sandbox base user
USER agent
