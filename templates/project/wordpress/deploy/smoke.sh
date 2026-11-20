#!/usr/bin/env bash
# Проверка, что сайт жив: каждая страница из pages.txt отвечает 200 и это страница WordPress с темой сайта.
#
#   bash wordpress/deploy/smoke.sh https://acme.example            # список — wordpress/deploy/pages.txt
#   bash wordpress/deploy/smoke.sh http://localhost:8080 мой.txt   # свой список
#
# Один скрипт и один список на всех: CI после установки с нуля (ci.yml), деплой (deploy.sh — из deploy.yml и
# manual.sh), проверка по расписанию (health.yml). Нужен только curl: работает и в Git Bash на Windows.
# Код возврата: 0 — все страницы в порядке, 1 — хотя бы одна нет.
set -euo pipefail

BASE="${1:?адрес сайта: bash wordpress/deploy/smoke.sh https://домен}"
BASE="${BASE%/}"
LIST="${2:-$(dirname "${BASH_SOURCE[0]}")/pages.txt}"
# Класс шапки из блока темы: есть в HTML — страницу отдал WordPress с темой, а не заглушка хостинга.
MARKER='acme-header'

[ -f "$LIST" ] || { echo "ОШИБКА: нет списка страниц $LIST" >&2; exit 1; }

# В GitHub Actions ошибка попадает в сводку запуска, в терминале — обычной строкой.
report() { if [ -n "${GITHUB_ACTIONS:-}" ]; then echo "::error::$*"; else echo "ОШИБКА: $*"; fi; }

checked=0
failed=0
while IFS= read -r line || [ -n "$line" ]; do
	path="${line%%#*}"
	path="${path//[[:space:]]/}" # пробелы и \r (файл могли сохранить в Windows)
	[ -n "$path" ] || continue
	checked=$((checked + 1))
	# Без временного файла: curl из Git для Windows и bash по-разному понимают пути вида /tmp.
	page=$(curl -s -L -m 30 --retry 3 --retry-delay 3 -A 'acme-smoke' -w '\n%{http_code}' "$BASE$path" || true)
	code=${page##*$'\n'}
	if [ "$code" = 200 ] && [[ "$page" == *"$MARKER"* ]]; then
		echo "OK $path"
		continue
	fi
	failed=$((failed + 1))
	if [[ "$page" == *"$MARKER"* ]]; then
		report "$BASE$path → $code (ответ WordPress)"
	elif [ "$code" = 000 ]; then
		report "$BASE$path → нет ответа (сайт недоступен, ошибка DNS или сертификата)"
	else
		report "$BASE$path → $code (ответ не от темы сайта: запрос не дошёл до WordPress — ЧПУ/.htaccess, либо фатальная ошибка PHP)"
	fi
done < "$LIST"

[ "$checked" -gt 0 ] || { report "в $LIST нет ни одной страницы"; exit 1; }
if [ "$failed" -gt 0 ]; then
	echo "Не открываются страницы: $failed из $checked ($BASE)"
	exit 1
fi
echo "Все страницы открываются: $checked ($BASE)"
