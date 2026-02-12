FROM docker/sandbox-templates:claude-code

USER root

RUN apt-get update && apt-get install -y --no-install-recommends \
  php-cli php-mbstring php-xml php-curl php-zip php-intl php-bcmath php-sqlite3 php8.4-sqlite3 \
  unzip ca-certificates \
  && rm -rf /var/lib/apt/lists/*

# Node.js 22 (base image ships v20 which is too old for modern TypeScript)
RUN npm install -g n && n 22

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

# Agent Browser https://agent-browser.dev/installation
RUN npm install -g agent-browser
RUN agent-browser install --with-deps

# Configure gh as git credential helper so git clone works with GH_TOKEN at runtime
RUN git config --system credential.https://github.com.helper '!/usr/bin/gh auth git-credential' \
  && git config --system credential.https://gist.github.com.helper '!/usr/bin/gh auth git-credential'

# Sandbox preparation script (node_modules isolation + host access)
COPY docker/setup-sandbox.sh /usr/local/bin/setup-sandbox
RUN chmod +x /usr/local/bin/setup-sandbox

# IMPORTANT: run as the sandbox base user
USER agent
