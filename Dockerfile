FROM docker/sandbox-templates:claude-code

USER root

RUN apt-get update && apt-get install -y --no-install-recommends \
  php-cli php-mbstring php-xml php-curl php-zip php-intl php-bcmath php-sqlite3 php8.4-sqlite3 \
  unzip ca-certificates \
  && rm -rf /var/lib/apt/lists/*

# Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

# Agent Browser https://agent-browser.dev/installation
RUN npm install -g agent-browser \
  && agent-browser install --with-deps

# IMPORTANT: run as the sandbox base user
USER agent
