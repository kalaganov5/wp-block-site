#!/usr/bin/env bash
# Общее для remote.sh (деплой) и sync.sh (копия боевого сайта на тестовый): PHP, WP-CLI, вывод, копирование папок.
# Подключается через source; переменная PHP_BIN — необязательный путь к PHP.

say() { printf '\n=== %s\n' "$*"; }
die() { printf '\nОШИБКА: %s\n' "$*" >&2; exit 1; }

# ---------- PHP ----------
# Минимальная версия PHP на сервере — единственное место для скриптов деплоя (формат PHP_VERSION_ID: 8.3 → 80300).
# 8.3 — потому что этого требует Contact Form 7 (проверено на 6.2.5): на 8.2 деплой не смог бы его установить.
# PHP 7.4–8.2 к тому же сняты с активной поддержки или близки к этому. Если хостинг даёт только старую версию
# и сменить его нельзя — понизьте число здесь и те же версии в style.css темы, шапке плагина, phpcs.xml.dist
# (testVersion) и матрице ci.yml: проверки начнут ловить синтаксис, которого в старой версии нет.
MIN_PHP_ID=80300
MIN_PHP='8.3'

pick_php() {
	local candidate version_id
	for candidate in "${PHP_BIN:-}" php8.5 php8.4 php8.3 php8.2 \
		/usr/local/bin/php8.5 /usr/local/bin/php8.4 /usr/local/bin/php8.3 /usr/local/bin/php8.2 \
		/opt/php85/bin/php /opt/php84/bin/php /opt/php83/bin/php /opt/php82/bin/php \
		/usr/local/php/cgi/8.4/bin/php /usr/local/php/cgi/8.3/bin/php /usr/local/php/cgi/8.2/bin/php php; do
		[ -n "$candidate" ] || continue
		command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ] || continue
		version_id=$("$candidate" -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)
		if [ "${version_id:-0}" -ge "$MIN_PHP_ID" ]; then echo "$candidate"; return 0; fi
	done
	return 1
}
PHP=$(pick_php) || die "в командной строке сервера не найден PHP $MIN_PHP или новее (сейчас: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'php не найден')).
  1. Включите PHP $MIN_PHP+ для сайта в панели хостинга (обычно «Сайты» → настройки сайта → «Версия PHP»).
  2. Версия для командной строки (SSH) задаётся отдельно: узнайте путь командой «ls /usr/local/bin/php* /opt/php*/bin/php»
     и запишите его в секрет PHP_BIN (например, /usr/local/bin/php8.3).
  Хостинг не даёт PHP $MIN_PHP — см. комментарий у MIN_PHP_ID в wordpress/deploy/lib.sh."
say "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

# ---------- WP-CLI ----------
WPCLI="$HOME/.local/bin/wp-cli.phar"
if [ ! -s "$WPCLI" ]; then
	say "Устанавливаю WP-CLI"
	mkdir -p "$(dirname "$WPCLI")"
	curl -fsSL --retry 3 --retry-delay 3 --retry-all-errors -o "$WPCLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar || die "не удалось скачать WP-CLI"
fi
ROOT_FLAG=()
[ "$(id -u)" = 0 ] && ROOT_FLAG=(--allow-root) # хостинг — не root; флаг нужен только для проверки в контейнере
# wpat <папка WordPress> <команда wp-cli…>
wpat() { local p=$1; shift; "$PHP" -d memory_limit=512M -d max_execution_time=0 "$WPCLI" --path="$p" ${ROOT_FLAG[@]+"${ROOT_FLAG[@]}"} "$@"; }

# ---------- Файлы ----------
# mirror_dir <откуда> <куда>: содержимое «куда» становится точной копией «откуда» (лишние файлы удаляются).
# Без rsync (бывает на дешёвых тарифах) — через временную папку рядом: замена одним переименованием.
mirror_dir() {
	local from=$1 to=$2
	mkdir -p "$to"
	if command -v rsync >/dev/null 2>&1; then
		rsync -a --delete "$from/" "$to/"
	else
		rm -rf "$to.new" "$to.old"
		mkdir -p "$to.new" && cp -a "$from/." "$to.new/" && mv "$to" "$to.old" && mv "$to.new" "$to" && rm -rf "$to.old"
	fi
}

# site_key <адрес сайта> — имя для файлов копий: https://dev.acme.example → dev.acme.example
site_key() { printf '%s' "$1" | sed -E 's#^https?://##; s#[^a-zA-Z0-9.-]+#_#g'; }
