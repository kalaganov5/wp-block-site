#!/usr/bin/env bash
# Установка и обновление локального сайта. Запускается сервисом setup из compose.yaml при каждом `docker compose up`.
# Делает то же, что деплой на тестовый сайт (deploy/remote.sh), только тема и плагин берутся прямо из папки
# репозитория, а кеш страниц выключен.
set -euo pipefail
cd /var/www/html

echo "Жду файлы WordPress…"
for _ in $(seq 1 120); do
	[ -f wp-includes/version.php ] && [ -f index.php ] && break
	sleep 1
done
sleep 2

# SITE_ENV=local: окружение WordPress — local, кеш страниц по умолчанию выключен (правки видны сразу).
# Проверить кеш локально: ACME_PAGE_CACHE=1 docker compose up -d
export DEPLOY_PATH=/var/www/html PACKAGE=none SITE_ENV=local
export PAGE_CACHE="${PAGE_CACHE:-0}"
export SITE_URL="${SITE_URL:-http://localhost:8080}"
export DB_NAME=wp DB_USER=wp DB_PASSWORD=wp DB_HOST=db
export ADMIN_USER="${ADMIN_USER:-admin}" ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin}" ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

# local-data/ (вне git, подключена только для чтения в /acme-data) — место для экспорта старого сайта,
# если проект переносит контент командой wp acme legacy (references/05-content-pipeline.md).

bash /acme/deploy/remote.sh

# Только локально: ошибки PHP пишутся в wp-content/debug.log.
wp config set WP_DEBUG true --raw --skip-plugins --skip-themes >/dev/null
wp config set WP_DEBUG_LOG true --raw --skip-plugins --skip-themes >/dev/null
wp config set WP_DEBUG_DISPLAY false --raw --skip-plugins --skip-themes >/dev/null

# Файлы, которые создал скрипт, должны быть доступны Apache (папки темы и плагина из репозитория не трогаем).
find /var/www/html -path /var/www/html/wp-content/themes/acme -prune -o -path /var/www/html/wp-content/plugins/acme-core -prune \
	-o ! -user www-data -exec chown www-data:www-data {} +

cat <<MSG

==============================================
  Локальный сайт готов: $SITE_URL
  Админка: $SITE_URL/wp-admin  ($ADMIN_USER / $ADMIN_PASSWORD)
==============================================
MSG
