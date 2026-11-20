#!/usr/bin/env bash
# Контракт страниц: то, что обязано быть на сайте с контентом каркаса (content.json), открытом для поисковиков.
# 404 для несуществующей страницы, редиректы со старых адресов, описание и JSON-LD, карта сайта, robots.txt
# с ботами ИИ, llms.txt. Запускает CI после установки с нуля (ci.yml); можно и на локальном сайте:
#
#   bash wordpress/deploy/contract.sh http://localhost:8080
#
# «Сайт жив» (200 и тема на каждой странице списка) проверяет smoke.sh — здесь это не повторяется.
# Адреса ниже — из контента каркаса: поменяли услугу-пример или редиректы — поправьте их здесь.
set -euo pipefail

BASE="${1:?адрес сайта: bash wordpress/deploy/contract.sh http://localhost:8080}"
BASE="${BASE%/}"
SERVICE_PAGE='/services/example/'            # страница услуги: на ней проверяется разметка Service, FAQPage
SEO_PAGES="/ $SERVICE_PAGE /about/"          # страницы, где обязаны быть описание и JSON-LD
# Старый адрес → новый одним 301 (карта — в content.json, раздел redirects).
REDIRECTS='/old-services/ /services/'

failed=0
fail() {
	failed=1
	if [ -n "${GITHUB_ACTIONS:-}" ]; then echo "::error::$*"; else echo "ОШИБКА: $*"; fi
}
# Страница — в переменную, поиск — по переменной: «curl | grep -q» с pipefail ложно падает на больших страницах
# (grep выходит на первом совпадении, curl получает SIGPIPE).
fetch() { curl -s -m 30 "$BASE$1" || true; }
status() { curl -s -m 30 -o /dev/null -w '%{http_code}' "$BASE$1" || true; }
redirect() { curl -s -m 30 -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE$1" || true; }
has() { grep -q -- "$2" <<<"$1"; }

code=$(status /this-page-does-not-exist/)
if [ "$code" = 404 ]; then echo "OK несуществующая страница → 404"; else fail "несуществующая страница → $code (нужен 404)"; fi

while read -r from to; do
	[ -n "$from" ] || continue
	got=$(redirect "$from")
	if [ "$got" = "301 $BASE$to" ]; then echo "OK $from → $to"; else fail "редирект $from → ожидали 301 $to, получили $got"; fi
done <<<"$REDIRECTS"

for path in $SEO_PAGES; do
	page=$(fetch "$path")
	has "$page" '<meta name="description" content="[^"]\{10,\}' || fail "нет description: $path"
	has "$page" 'application/ld+json' || fail "нет JSON-LD: $path"
done
echo "Проверены описание и JSON-LD: $SEO_PAGES"

page=$(fetch "$SERVICE_PAGE")
for type in '"@type":"Service"' 'FAQPage' '"@type":"BreadcrumbList"'; do
	has "$page" "$type" || fail "нет $type на странице услуги $SERVICE_PAGE"
done

code=$(status /sitemap_index.xml)
if [ "$code" = 200 ]; then echo "OK sitemap_index.xml"; else fail "sitemap_index.xml → $code"; fi
has "$(fetch /robots.txt)" 'User-agent: GPTBot' || fail "в robots.txt нет ботов ИИ"
has "$(fetch /llms.txt)" '^# ' || fail "нет llms.txt"

[ "$failed" = 0 ] || { echo "Контракт страниц нарушен ($BASE)"; exit 1; }
echo "Контракт страниц соблюдён ($BASE)"
