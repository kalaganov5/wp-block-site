#!/usr/bin/env bash
# Проверка защиты живого сайта снаружи, как её видит посторонний: заголовки безопасности, закрытые служебные
# файлы, XML-RPC, список пользователей — и что нужное при этом работает (REST API, карта сайта, robots.txt).
#
#   bash wordpress/deploy/hardening-check.sh https://acme.example
#
# Запускает deploy.yml после выкладки; находки — предупреждения, деплой они не останавливают: на хостинге
# может не быть модуля Apache (mod_headers), и это повод написать в поддержку, а не откатывать сайт.
# STRICT=1 — завершиться с кодом 1, если есть находки.
# Что защищено и почему: wordpress/deploy/htaccess-security.conf и wordpress/acme-core/inc/security.php.
set -euo pipefail

BASE="${1:?адрес сайта: bash wordpress/deploy/hardening-check.sh https://домен}"
BASE="${BASE%/}"

found=0
warn() {
	found=$((found + 1))
	if [ -n "${GITHUB_ACTIONS:-}" ]; then echo "::warning::$*"; else echo "ВНИМАНИЕ: $*"; fi
}
status() { curl -s -m 30 -o /dev/null -w '%{http_code}' "$BASE$1" || true; }

# expect_status <путь> <допустимые коды через |> <что это>
expect_status() {
	local code
	code=$(status "$1")
	case "|$2|" in
		*"|$code|"*) echo "OK $1 → $code ($3)" ;;
		*) warn "$1 → $code, ожидали $2 ($3)" ;;
	esac
}

# ---------- Заголовки ----------
headers=$(curl -s -m 30 -D - -o /dev/null "$BASE/" | tr -d '\r' || true)
for header in 'X-Content-Type-Options: nosniff' 'Referrer-Policy: ' 'Permissions-Policy: ' 'X-Frame-Options: ' "Content-Security-Policy: frame-ancestors"; do
	if grep -qi -- "^$header" <<<"$headers"; then echo "OK заголовок $header"; else warn "нет заголовка «$header» (на хостинге выключен mod_headers?)"; fi
done
grep -qi '^X-Powered-By:' <<<"$headers" && warn "заголовок X-Powered-By выдаёт версию PHP"
case "$BASE" in
	https://*) grep -qi '^Strict-Transport-Security:' <<<"$headers" && echo "OK заголовок Strict-Transport-Security" || echo "Нет Strict-Transport-Security — так и должно быть на тестовом сайте (ставится только на боевом)" ;;
esac

# ---------- Закрыто ----------
expect_status /xmlrpc.php '403' 'XML-RPC закрыт'
expect_status /wp-config.php '403' 'файл с паролем базы'
expect_status /wp-content/debug.log '403' 'журнал ошибок PHP'
expect_status /readme.html '403' 'версия WordPress'
expect_status /license.txt '403' 'версия WordPress'
expect_status /.env '403' 'скрытые файлы'
expect_status /backup.sql '403' 'дампы базы'
expect_status /wp-content/uploads/acme-probe.php '403' 'PHP в папке загрузок не выполняется'
expect_status /wp-json/wp/v2/users '401' 'список пользователей закрыт для посетителей'
expect_status '/?author=1' '404' 'логин не выдаётся по номеру автора'
expect_status /comments/feed/ '404' 'лента комментариев (если комментарии закрыты)'

page=$(curl -s -m 30 "$BASE/" || true)
if grep -qi '<meta name="generator" content="WordPress' <<<"$page"; then warn "в разметке есть <meta name=\"generator\"> с версией WordPress"; else echo "OK версии WordPress в разметке нет"; fi

# ---------- Работает ----------
expect_status /wp-json/ '200' 'REST API открыт: формы и редактор'
expect_status /robots.txt '200' 'robots.txt'
expect_status /sitemap_index.xml '200' 'карта сайта'
expect_status /llms.txt '200' 'llms.txt'
expect_status /wp-login.php '200' 'страница входа'

if [ "$found" -gt 0 ]; then
	echo "Находок: $found ($BASE)"
	[ "${STRICT:-0}" = 1 ] && exit 1
	exit 0
fi
echo "Защита в порядке ($BASE)"
