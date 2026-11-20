#!/usr/bin/env bash
# Сравнение SEO-аудита с предыдущим: что изменилось с прошлого запуска. Вызывает seo-audit.yml.
#
#   bash .github/scripts/seo-compare.sh <папка нового отчёта> <папка предыдущего отчёта>
#
# Печатает раздел сводки в Markdown (workflow дописывает его в Summary запуска и в отчёт).
# Числа берутся из готовых файлов отчёта: заголовки разделов summary.md («## Название — число», их пишет
# wordpress/tools/qa/lib/seo-summary.js) и оценки lighthouse.json. Нужен jq (на раннерах GitHub он есть).
# Код возврата 1 — только при блокирующем ухудшении: стало больше страниц с ошибкой, без title или без
# description, упало число страниц в обходе, либо оценка Lighthouse упала на DROP пунктов и больше.
# Остальные изменения показываются, но запуск не роняют: это задачи, а не поломки.
set -euo pipefail

NEW="${1:?папка нового отчёта}"
OLD="${2:?папка предыдущего отчёта}"
DROP=10 # падение оценки Lighthouse, с которого запуск считается проваленным (меньше — шум между замерами)
# Разделы сводки, рост числа в которых — поломка, а не пожелание. По одному в строке, заголовок целиком.
BLOCKING='Не 200 (ошибки и редиректы внутри сайта)
Без title
Без description
Старые адреса: не 301 на рабочую страницу'

[ -f "$OLD/summary.md" ] || { echo "Предыдущего отчёта нет — сравнивать не с чем."; exit 0; }

# «## Название — 12» → «Название<TAB>12»
sections() { sed -n -E 's/^## (.+) — ([0-9]+)$/\1\t\2/p' "$1/summary.md"; }
crawled() { sed -n -E 's/^Страниц обойдено: ([0-9]+).*/\1/p' "$1/summary.md" | head -n 1; }

blocking=0
echo "## Изменилось с прошлого аудита ($(basename "$OLD"))"
echo

new_pages=$(crawled "$NEW")
old_pages=$(crawled "$OLD")
echo "Страниц обойдено: ${old_pages:-?} → ${new_pages:-?}"
# Страниц стало меньше на пятую часть и больше — пропал раздел сайта или карта сайта.
if [ -n "$new_pages" ] && [ -n "$old_pages" ] && [ "$((new_pages * 5))" -lt "$((old_pages * 4))" ]; then
	echo "- **БЛОКИРУЕТ:** страниц в обходе стало заметно меньше"
	blocking=1
fi
echo

changed=0
while IFS=$'\t' read -r title count; do
	[ -n "$title" ] || continue
	before=$(sections "$OLD" | awk -F '\t' -v title="$title" '$1 == title { print $2 }')
	[ -n "$before" ] || before=0
	[ "$count" = "$before" ] && continue
	changed=1
	mark=''
	if [ "$count" -gt "$before" ] && grep -qxF -- "$title" <<<"$BLOCKING"; then
		mark=' — **БЛОКИРУЕТ**'
		blocking=1
	fi
	echo "- $title: $before → $count$mark"
done < <(sections "$NEW")
[ "$changed" = 1 ] || echo "Числа по разделам обхода не изменились."
echo

if [ -f "$NEW/lighthouse.json" ] && [ -f "$OLD/lighthouse.json" ]; then
	echo "### Lighthouse (мобильный)"
	echo
	# Строки «адрес<TAB>категория<TAB>было<TAB>стало» для оценок, которые изменились.
	# Оценка 0 при живой странице — сбой замера (см. поле error в lighthouse.json), её не сравниваем.
	diff_rows=$(jq -r --slurpfile old "$OLD/lighthouse.json" '
		to_entries[] | .key as $url | (.value.scores // {}) | to_entries[]
		| . as $score | ($old[0][$url].scores[$score.key] // null) as $before
		| select($before != null and $before != $score.value and $before > 0 and $score.value > 0)
		| [$url, $score.key, $before, $score.value] | @tsv
	' "$NEW/lighthouse.json")
	if [ -z "$diff_rows" ]; then
		echo "Оценки не изменились."
	else
		while IFS=$'\t' read -r url category before after; do
			mark=''
			if [ "$((before - after))" -ge "$DROP" ]; then
				mark=' — **БЛОКИРУЕТ**'
				blocking=1
			fi
			echo "- $url — $category: $before → $after$mark"
		done <<<"$diff_rows"
	fi
	echo
fi

if [ "$blocking" = 1 ]; then
	echo "Есть блокирующие ухудшения — запуск помечен как неуспешный."
	exit 1
fi
echo "Блокирующих ухудшений нет."
