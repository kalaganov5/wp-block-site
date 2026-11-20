#!/usr/bin/env bash
# Копия боевого сайта на тестовый: база и загруженные файлы. Запускается на сервере из GitHub Actions
# (.github/workflows/sync.yml). Только в одну сторону — боевой → тестовый; боевой сайт не меняется.
#
# Нужен SSH-доступ, из-под которого видны обе папки (на одном хостинге это основной пользователь аккаунта).
#
# Переменные окружения:
#   PROD_PATH PROD_URL   папка и адрес боевого сайта
#   TEST_PATH TEST_URL   папка и адрес тестового сайта (WordPress там уже установлен деплоем)
#   [KEEP_LEADS=0]       1 — оставить заявки посетителей и в копии (по умолчанию удаляются)
#   [PHP_BIN]
set -euo pipefail

# shellcheck source=lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

: "${PROD_PATH:?нет PROD_PATH}"
: "${PROD_URL:?нет PROD_URL}"
: "${TEST_PATH:?нет TEST_PATH}"
: "${TEST_URL:?нет TEST_URL}"
PROD_PATH="${PROD_PATH/#\~/$HOME}"
TEST_PATH="${TEST_PATH/#\~/$HOME}"
PROD_URL="${PROD_URL%/}"
TEST_URL="${TEST_URL%/}"

[ -f "$PROD_PATH/wp-config.php" ] || die "нет WordPress в $PROD_PATH (PROD_PATH)"
[ -f "$TEST_PATH/wp-config.php" ] || die "нет WordPress в $TEST_PATH (TEST_PATH). Сначала выложите тестовый сайт деплоем."
[ "$(cd "$PROD_PATH" && pwd -P)" != "$(cd "$TEST_PATH" && pwd -P)" ] || die "PROD_PATH и TEST_PATH — одна и та же папка"
[ "$PROD_URL" != "$TEST_URL" ] || die "PROD_URL и TEST_URL совпадают"

prod() { wpat "$PROD_PATH" --skip-themes --skip-plugins "$@"; }
test_wp() { wpat "$TEST_PATH" --skip-themes --skip-plugins "$@"; }
test_wpx() { wpat "$TEST_PATH" "$@"; }

# Префиксы таблиц должны совпадать: дамп переносится как есть.
[ "$(prod config get table_prefix)" = "$(test_wp config get table_prefix)" ] || die "разные префиксы таблиц в wp-config.php боевого и тестового сайтов"

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
chmod 700 "$TMP"

say "Резервная копия базы тестового сайта"
test_wp db export "$TMP/test-before.sql" >/dev/null
mkdir -p "$HOME/acme-backups"
gzip -c "$TMP/test-before.sql" > "$HOME/acme-backups/test-before-sync-$(date +%Y%m%d-%H%M%S).sql.gz"
# Оставляем пять последних копий.
find "$HOME/acme-backups" -name 'test-before-sync-*.sql.gz' -type f | sort -r | tail -n +6 | xargs -r rm -f

say "База: боевой → тестовый"
prod db export "$TMP/prod.sql" --single-transaction >/dev/null
test_wp db reset --yes >/dev/null
test_wp db import "$TMP/prod.sql" >/dev/null
test_wp search-replace "$PROD_URL" "$TEST_URL" --skip-columns=guid --all-tables-with-prefix --report-changed-only

say "Файлы: wp-content/uploads"
mkdir -p "$TEST_PATH/wp-content/uploads"
if command -v rsync >/dev/null 2>&1; then
	rsync -a --delete "$PROD_PATH/wp-content/uploads/" "$TEST_PATH/wp-content/uploads/"
else
	rm -rf "$TEST_PATH/wp-content/uploads" && cp -a "$PROD_PATH/wp-content/uploads" "$TEST_PATH/wp-content/uploads"
fi

say "Настройки тестового сайта"
test_wp option update blog_public 0 # закрыт от поисковиков
if test_wp plugin is-installed query-monitor; then test_wp plugin activate query-monitor; fi
if [ "${KEEP_LEADS:-0}" != 1 ]; then
	# Заявки посетителей — личные данные, на тестовом они не нужны.
	ids=$(test_wp post list --post_type=acme_lead --post_status=any --format=ids)
	[ -z "$ids" ] || { read -r -a arr <<<"$ids"; test_wp post delete "${arr[@]}" --force >/dev/null; echo "Удалено заявок: ${#arr[@]}"; }
fi
test_wpx rewrite flush >/dev/null
test_wp cache flush >/dev/null 2>&1 || true
say "Готово: $TEST_URL — копия $PROD_URL"
