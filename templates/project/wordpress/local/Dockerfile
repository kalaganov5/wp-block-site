# Локальный сайт: тот же PHP, что на хостинге, Apache с .htaccess, WP-CLI и клиент MySQL для скрипта деплоя.
# Версия PHP — единственное место для Docker: по умолчанию актуальная (8.4). Проверить сайт на минимальной
# поддерживаемой (8.3, см. style.css темы): ACME_PHP=8.3 docker compose up -d --build
# mysqlcheck: в новом mariadb-client его нет, а WP-CLI (wp db check) зовёт его, если сервер базы — MySQL.
ARG PHP_VERSION=8.4
FROM wordpress:php${PHP_VERSION}-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends mariadb-client rsync less curl \
	&& rm -rf /var/lib/apt/lists/* \
	&& { command -v mysqlcheck >/dev/null || ln -s "$(command -v mariadb-check)" /usr/local/bin/mysqlcheck; } \
	&& a2enmod rewrite expires headers deflate setenvif \
	&& mkdir -p /etc/mysql/conf.d \
	&& printf '[client]\nskip-ssl\n' > /etc/mysql/conf.d/acme-local.cnf \
	&& mkdir -p /root/.local/bin \
	&& curl -fsSL -o /root/.local/bin/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
	&& printf '#!/bin/sh\nexec php -d memory_limit=512M /root/.local/bin/wp-cli.phar --allow-root --path=/var/www/html "$@"\n' > /usr/local/bin/wp \
	&& chmod +x /usr/local/bin/wp \
	&& printf 'memory_limit = 512M\nupload_max_filesize = 64M\npost_max_size = 64M\nmax_execution_time = 300\n' > /usr/local/etc/php/conf.d/acme-local.ini
