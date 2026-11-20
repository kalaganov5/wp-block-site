#!/usr/bin/env bash
# Развёртывание сайта на хостинге. Запускается на сервере: из GitHub Actions (.github/workflows/deploy.yml) и
# вручную (wordpress/deploy/manual.sh) — оба через deploy.sh; в CI (установка с нуля) и в локальном Docker
# (wordpress/local/setup.sh).
# Скрипт повторяемый: первый запуск ставит WordPress, плагины и контент; следующие — обновляют тему и плагин
# и создают только то, чего ещё нет. Контент и настройки, изменённые в админке, не трогаются.
# Настройки защиты (wp-config.php, .htaccess, права на файлы) и кеша страниц проверяются при каждом запуске.
#
# Переменные окружения (приходят из секретов окружения GitHub или из wordpress/deploy/manual-<окружение>.env):
#   DEPLOY_PATH   папка сайта на хостинге, например ~/dev.acme.example/public_html
#   SITE_URL      адрес сайта, например https://dev.acme.example
#   DB_NAME DB_USER DB_PASSWORD [DB_HOST=localhost]   база MySQL (создаётся в панели хостинга)
#   ADMIN_USER ADMIN_PASSWORD ADMIN_EMAIL              администратор WordPress (только для первой установки)
#   [SITE_ENV=test]       test — сайт закрыт от поисковиков; production — открыт; local — локальный Docker
#   [NOINDEX=0]           1 — закрыть и боевой сайт (пока он проверяется до переключения домена)
#   [FORCE_IMPORT=0]      1 — перезаписать импортированный контент (wp acme import --force)
#   [PHP_BIN]             путь к PHP (минимальная версия — MIN_PHP_ID в lib.sh); если пусто — ищется автоматически
#   [TG_BOT_TOKEN TG_CHAT_ID]  копии заявок в Telegram
#   [PAGE_CACHE]          1 — кеш страниц включён (по умолчанию на test и production), 0 — выключен (local)
#   [AUTO_UPDATE_PLUGINS] 1 — включить автообновление плагинов из каталога, 0 — выключить, пусто — не трогать
#   [PROBE_URL=SITE_URL]  адрес, по которому сервер видит сам себя (проверка после смены прав wp-config.php)
#   [ROLLBACK=0]          1 — вместо деплоя вернуть предыдущую версию темы и плагина (файлы; базу — см. ниже)
#   PACKAGE              путь к архиву с темой и плагином; none — тема и плагин уже на месте (локальный Docker)
set -euo pipefail

CONF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib.sh
. "$CONF_DIR/lib.sh"

# ---------- Настройки проекта ----------
THEME=acme
PLUGIN=acme-core
SITE_TITLE='Acme Site'
LOCALE=ru_RU
EXTRA_LOCALES='en_US'          # переводы админки и плагинов для других языков сайта
TIMEZONE='Europe/Moscow'
PLUGINS='polylang contact-form-7 seo-by-rank-math'
# Кеш страниц — один плагин, который ставит и настраивает деплой (не W3 Total Cache с ручной настройкой).
# Cache Enabler: 250 КБ, все настройки — одна опция в базе, очистка из WP-CLI, не требует доступа к серверу.
CACHE_PLUGIN=cache-enabler
CACHE_HOURS=12                 # через столько часов страница в кеше считается устаревшей
# Не кешируются (регулярные выражения PHP): REST API, карты сайта, llms.txt, robots.txt.
CACHE_SKIP_PATHS='/^\/(wp-json\/|.*sitemap.*\.(xml|xsl)$|llms.*\.txt$|robots\.txt$)/'
# Запрос с параметрами идёт мимо кеша — кроме рекламных меток: страница с ?utm_source=… та же, что без неё.
CACHE_SKIP_QUERY='/^(?!(utm_[a-z_]+|yclid|ysclid|gclid|gbraid|wbraid|fbclid|msclkid|_openstat|_ga|_gl|mc_(cid|eid))=).+$/'

: "${DEPLOY_PATH:?нет DEPLOY_PATH}"
: "${SITE_URL:?нет SITE_URL}"
SITE_ENV="${SITE_ENV:-test}"
DB_HOST="${DB_HOST:-localhost}"
FORCE_IMPORT="${FORCE_IMPORT:-0}"
ROLLBACK="${ROLLBACK:-0}"
DEPLOY_PATH="${DEPLOY_PATH/#\~/$HOME}"
SITE_URL="${SITE_URL%/}"
if [ -z "${PAGE_CACHE:-}" ]; then PAGE_CACHE=$([ "$SITE_ENV" = local ] && echo 0 || echo 1); fi

wp() { wpat "$DEPLOY_PATH" --skip-themes --skip-plugins "$@"; } # ядро, без плагинов
wpx() { wpat "$DEPLOY_PATH" "$@"; } # с темой и плагинами

# Копии — вне папки сайта: по адресу они не открываются, а WordPress не видит вторую тему и второй плагин.
BK="$HOME/acme-backups"
NAME="$(site_key "$SITE_URL")"
RELEASE="$BK/releases/$NAME" # предыдущая версия темы и плагина, ровно одна

mkdir -p "$DEPLOY_PATH"
cd "$DEPLOY_PATH"

# ---------- Функции ----------
# Кеш объектов и кеш страниц: новые стили, скрипты и тексты должны быть видны сразу.
flush_caches() {
	wp cache flush >/dev/null 2>&1 || true
	if wp plugin is-active "$CACHE_PLUGIN" >/dev/null 2>&1; then
		wpx "$CACHE_PLUGIN" clear >/dev/null 2>&1 && echo "Кеш страниц очищен" || echo "ВНИМАНИЕ: не удалось очистить кеш страниц — очистите в админке (Настройки → Cache Enabler)"
	fi
	if wp plugin is-active w3-total-cache >/dev/null 2>&1; then
		wpx w3-total-cache flush all >/dev/null 2>&1 && echo "Кеш страниц W3 Total Cache очищен" || echo "ВНИМАНИЕ: не удалось очистить кеш W3 Total Cache — очистите вручную"
	fi
}

# Возвращает файлы темы и плагина из сохранённой предыдущей версии.
restore_release() {
	[ -f "$RELEASE/theme/style.css" ] && [ -d "$RELEASE/plugin" ] || return 1
	mirror_dir "$RELEASE/theme" "wp-content/themes/$THEME"
	mirror_dir "$RELEASE/plugin" "wp-content/plugins/$PLUGIN"
}

# Базу откат не трогает: после деплоя на сайте могли появиться заявки и правки владельца. Команда — для человека.
print_db_restore_hint() {
	local dump
	dump=$(sed -n 's/^db_backup=//p' "$RELEASE/info.txt" 2>/dev/null || true)
	[ -n "$dump" ] && [ -f "$dump" ] || { echo "Копии базы, сделанной перед тем деплоем, нет (первая установка или копия не удалась)."; return 0; }
	echo "База НЕ откатывалась. Копия, сделанная перед тем деплоем: $dump"
	echo "Вернуть и её (всё, что изменилось на сайте после деплоя, пропадёт) — на сервере:"
	echo "  gunzip -c '$dump' | '$PHP' '$WPCLI' --path='$DEPLOY_PATH' db import -"
}

# Деплой упал уже после замены темы и плагина (например, фатальная ошибка PHP в новой теме) — сайт не должен
# остаться сломанным: возвращаем файлы сразу, не дожидаясь проверки страниц.
REPLACED=0
on_exit() {
	local status=$?
	trap - EXIT
	if [ "$status" != 0 ] && [ "$REPLACED" = 1 ]; then
		say "Деплой не завершён (код $status) — возвращаю предыдущую версию темы и плагина"
		if restore_release; then flush_caches; print_db_restore_hint; else echo "ВНИМАНИЕ: предыдущей версии нет — сайт остался с новыми файлами"; fi
	fi
	exit "$status"
}
trap on_exit EXIT

# config_set <константа> <значение> [raw] — wp-config.php; повторный запуск ничего не меняет.
config_set() {
	local name=$1 value=$2 raw=()
	[ "${3:-}" = raw ] && raw=(--raw)
	wp config set "$name" "$value" --type=constant ${raw[@]+"${raw[@]}"} --quiet
}

# htaccess_block <файл .htaccess> <метка> <файл со строками или ''> [top] — блок «# BEGIN метка … # END метка».
# Повторный запуск заменяет содержимое блока, остальной файл не трогается. top — новый блок ставится в начало.
htaccess_block() {
	HT_FILE=$1 HT_MARKER=$2 HT_SOURCE=$3 HT_TOP=${4:-} wp eval '
		require_once ABSPATH . "wp-admin/includes/misc.php";
		$file   = getenv( "HT_FILE" );
		$marker = getenv( "HT_MARKER" );
		$lines  = getenv( "HT_SOURCE" ) ? file( getenv( "HT_SOURCE" ), FILE_IGNORE_NEW_LINES ) : array();
		$text   = file_exists( $file ) ? (string) file_get_contents( $file ) : "";
		if ( getenv( "HT_TOP" ) && false === strpos( $text, "# BEGIN " . $marker ) ) {
			file_put_contents( $file, "# BEGIN " . $marker . "\n# END " . $marker . "\n\n" . $text );
		}
		echo insert_with_markers( $file, $marker, $lines ) ? "" : "ВНИМАНИЕ: не удалось записать блок «" . $marker . "» в " . $file . "\n";
	'
}

# wp-config.php с паролем базы: 600 — читает только владелец. На части хостингов PHP сайта работает под другим
# пользователем и с 600 сайт падает. Поэтому сначала спрашиваем сам веб-сервер, какие права он осилит: кладём
# рядом пробный файл с такими правами и крошечный PHP-файл, который отвечает, читается ли тот. Сам wp-config.php
# для пробы не трогаем: PHP держит его разобранным в памяти (opcache) и не заметил бы, что файл стал недоступен, —
# сайт упал бы позже, уже без деплоя. Имена пробных файлов случайные, после проверки они удаляются.
harden_config_mode() {
	local token url mode answer chosen=''
	[ -O wp-config.php ] || { echo "wp-config.php: владелец — другой пользователь, права не менялись"; return 0; }
	token="acme-probe-$(date +%s)-$$-$RANDOM"
	url="${PROBE_URL:-$SITE_URL}/$token.php"
	printf '<?php echo is_readable( __DIR__ . "/%s.dat" ) ? "readable" : "denied";' "$token" > "$token.php"
	: > "$token.dat"
	for mode in 600 640 644; do
		chmod "$mode" "$token.dat"
		answer=$(curl -s -m 20 -k "$url" 2>/dev/null || true)
		if [ "$answer" = readable ]; then chosen=$mode; break; fi
		[ "$answer" = denied ] || break # ответ не от пробного файла: сервер не открывает сам себя
	done
	rm -f "$token.php" "$token.dat"
	if [ -z "$chosen" ]; then
		echo "wp-config.php: права не менялись — сервер не открывает сам себя по адресу ${PROBE_URL:-$SITE_URL} (нужен PROBE_URL?)"
		return 0
	fi
	chmod "$chosen" wp-config.php
	echo "wp-config.php: права $chosen"
}

# ---------- Откат (ROLLBACK=1) ----------
if [ "$ROLLBACK" = 1 ]; then
	say "Откат: возвращаю предыдущую версию темы $THEME и плагина $PLUGIN"
	[ -f wp-config.php ] || die "в $DEPLOY_PATH нет WordPress — откатывать нечего"
	restore_release || die "нет сохранённой предыдущей версии ($RELEASE): она появляется после первого обновления сайта деплоем"
	echo "Файлы возвращены (версия, сохранённая $(sed -n 's/^saved=//p' "$RELEASE/info.txt" 2>/dev/null || echo '?'))"
	flush_caches
	print_db_restore_hint
	say "Откат готов: $SITE_URL"
	exit 0
fi
: "${PACKAGE:?нет PACKAGE}"

# ---------- WordPress ----------
if [ ! -f wp-config.php ]; then
	: "${DB_NAME:?нет DB_NAME — создайте базу MySQL в панели хостинга и добавьте секреты DB_NAME, DB_USER, DB_PASSWORD}"
	: "${DB_USER:?нет DB_USER}"
	: "${DB_PASSWORD:?нет DB_PASSWORD}"
	say "Файлы WordPress и wp-config.php в $DEPLOY_PATH"
	# Заглушка хостинга мешает index.php.
	for f in index.html index.htm; do [ -f "$f" ] && mv "$f" "$f.hosting-stub.bak"; done
	[ -f wp-includes/version.php ] || wp core download --locale="$LOCALE"
	# Секретные ключи (salts) создаёт сам config create.
	wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST" --dbcharset=utf8mb4 --locale="$LOCALE" --skip-check \
		--extra-php <<'PHP'
define( 'WP_POST_REVISIONS', 20 );
PHP
fi
# Первая установка определяется по базе, а не по wp-config.php: если прошлый запуск упал после создания
# wp-config.php (неверный e-mail администратора, нет доступа к базе), следующий деплой доустановит сайт сам.
FIRST=0
if ! wp core is-installed 2>/dev/null; then
	FIRST=1
	wp db check >/dev/null 2>&1 || die "нет подключения к базе: проверьте DB_NAME, DB_USER, DB_PASSWORD, DB_HOST (они записаны в wp-config.php при первом запуске)"
	: "${ADMIN_USER:?нет ADMIN_USER}"
	: "${ADMIN_PASSWORD:?нет ADMIN_PASSWORD}"
	: "${ADMIN_EMAIL:?нет ADMIN_EMAIL}"
	say "Первая установка WordPress"
	wp core install --url="$SITE_URL" --title="$SITE_TITLE" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" --admin_email="$ADMIN_EMAIL" --skip-email --locale="$LOCALE" \
		|| die "установка WordPress не прошла: проверьте ADMIN_USER, ADMIN_PASSWORD, ADMIN_EMAIL (сообщение WP-CLI выше)"
fi
say "WordPress $(wp core version)"

# Резервная копия базы перед обновлением: ~/acme-backups, десять последних на каждый сайт.
DB_BACKUP=''
if [ "$FIRST" != 1 ]; then
	mkdir -p "$BK" && chmod 700 "$BK"
	DB_BACKUP="$BK/$NAME-before-deploy-$(date +%Y%m%d-%H%M%S).sql.gz"
	if wp db export - --single-transaction 2>/dev/null | gzip > "$DB_BACKUP"; then
		echo "Копия базы: $DB_BACKUP"
	else
		echo "ВНИМАНИЕ: не удалось сделать копию базы"
		rm -f "$DB_BACKUP"
		DB_BACKUP=''
	fi
	find "$BK" -maxdepth 1 -name "$NAME-before-deploy-*.sql.gz" -type f | sort -r | tail -n +11 | xargs -r rm -f
fi

# Адрес сайта (если сменился домен или http → https).
CUR=$(wp option get home)
if [ "$CUR" != "$SITE_URL" ]; then
	say "Меняю адрес сайта: $CUR → $SITE_URL"
	# Ссылки и картинки в текстах тоже (сериализованные данные WP-CLI правит корректно). GUID не трогаем.
	wp search-replace "$CUR" "$SITE_URL" --skip-columns=guid --all-tables-with-prefix --report-changed-only
	wp option update home "$SITE_URL"
	wp option update siteurl "$SITE_URL"
fi

# ---------- Тема и плагин из репозитория ----------
if [ "$PACKAGE" = none ]; then
	say "Тема и плагин подключены из папки репозитория (локальный сайт)"
else
	say "Обновляю тему $THEME и плагин $PLUGIN"
	TMP=$(mktemp -d)
	tar -xzf "$PACKAGE" -C "$TMP"
	[ -f "$TMP/$THEME/style.css" ] && [ -d "$TMP/$PLUGIN" ] || die "в архиве нет папок $THEME и $PLUGIN"
	# Права как на хостинге принято: папки 755, файлы 644 (архив с Windows приходит с какими попало).
	find "$TMP" -type d -exec chmod 755 {} +
	find "$TMP" -type f -exec chmod 644 {} +
	if diff -rq "$TMP/$THEME" "wp-content/themes/$THEME" >/dev/null 2>&1 && diff -rq "$TMP/$PLUGIN" "wp-content/plugins/$PLUGIN" >/dev/null 2>&1; then
		# Те же файлы (перезапуск деплоя, правка только workflow): сохранённую предыдущую версию не затираем —
		# иначе откат вернул бы то же самое.
		echo "Файлы темы и плагина не изменились"
	else
		if [ -f "wp-content/themes/$THEME/style.css" ] && [ -d "wp-content/plugins/$PLUGIN" ]; then
			# Предыдущая версия — для отката (ROLLBACK=1): одна, заменяется при каждом обновлении.
			mkdir -p "$RELEASE" && chmod 700 "$BK" "$BK/releases" "$RELEASE"
			mirror_dir "wp-content/themes/$THEME" "$RELEASE/theme"
			mirror_dir "wp-content/plugins/$PLUGIN" "$RELEASE/plugin"
			printf 'saved=%s\ndb_backup=%s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$DB_BACKUP" > "$RELEASE/info.txt"
			echo "Предыдущая версия сохранена для отката: $RELEASE"
		fi
		REPLACED=1
		mirror_dir "$TMP/$THEME" "wp-content/themes/$THEME"
		mirror_dir "$TMP/$PLUGIN" "wp-content/plugins/$PLUGIN"
	fi
	rm -rf "$TMP" "$PACKAGE"
fi

# ---------- Плагины ----------
say "Плагины"
for p in $PLUGINS; do
	wp plugin is-installed "$p" || wp plugin install "$p"
done
if [ "$SITE_ENV" != production ]; then
	wp plugin is-installed query-monitor || wp plugin install query-monitor
	wp plugin activate query-monitor
fi
# shellcheck disable=SC2086 # список плагинов через пробел
wp plugin activate $PLUGINS "$PLUGIN"
for p in hello akismet; do wp plugin is-installed "$p" && wp plugin delete "$p" || true; done
wp theme activate "$THEME"
# Тема и плагин загружаются без фатальных ошибок PHP? Иначе дальше упала бы первая же команда с непонятной причиной.
wpx eval 'echo "";' || die "тема $THEME или плагин $PLUGIN падают с ошибкой PHP (текст — выше): исправьте код и повторите деплой"
# Переводы админки и плагинов. Язык сайта — основной (--activate при нескольких языках включил бы последний).
# shellcheck disable=SC2086
wp language core install "$LOCALE" $EXTRA_LOCALES >/dev/null 2>&1 || true
wp site switch-language "$LOCALE" || wp option update WPLANG "$LOCALE"
# shellcheck disable=SC2086
wp language plugin install --all "$LOCALE" $EXTRA_LOCALES >/dev/null 2>&1 || true
wp language theme install --all "$LOCALE" >/dev/null 2>&1 || true

# ---------- Настройки ----------
say "Настройки"
if [ "$FIRST" = 1 ]; then
	# Только при установке: дальше эти настройки принадлежат владельцу, деплой их не трогает.
	wp option update timezone_string "$TIMEZONE"
	wp option update date_format 'j F Y'
	wp option update time_format 'H:i'
	wp option update start_of_week 1
	wp option update default_comment_status closed
	wp option update default_ping_status closed
	wp post delete 1 2 --force >/dev/null 2>&1 || true # «Привет, мир» и «Пример страницы»
fi
if [ "$SITE_ENV" = production ] && [ "${NOINDEX:-0}" != 1 ]; then
	wp option update blog_public 1
else
	wp option update blog_public 0 # тестовый сайт закрыт от поисковиков
fi

# ---------- Контент ----------
CONTENT="wp-content/plugins/$PLUGIN/content/content.json"
if ! wp option get acme_imported >/dev/null 2>&1; then
	say "Импорт контента (первый раз)"
	wpx acme import
	# Форма «Контактная форма 1», которую CF7 создаёт при включении, не нужна: у сайта свои формы.
	wpx eval 'foreach ( get_posts( array( "post_type" => "wpcf7_contact_form", "numberposts" => -1, "meta_key" => "acme_imported", "meta_compare" => "NOT EXISTS" ) ) as $f ) { wp_delete_post( $f->ID, true ); }'
elif [ "$FORCE_IMPORT" = 1 ]; then
	say "Импорт контента заново (FORCE_IMPORT=1)"
	wpx acme import --force
elif [ "$SITE_ENV" != production ] && [ "$(wp option get acme_content_version 2>/dev/null || echo 1)" != "$("$PHP" -r 'echo (int) json_decode(file_get_contents($argv[1]), true)["version"];' "$CONTENT")" ]; then
	# Только тестовый: content.json сменил версию (например, адреса страниц) — перезаливаем сам.
	say "Импорт контента заново: новая версия контента"
	wpx acme import --force
else
	say "Контент уже импортирован — не трогаю (для перезаписи запустите деплой с force_import)"
fi

# Команды «создай, чего нет»: правки в админке не трогаются.
wpx acme seo

# Иконка сайта — знак логотипа.
if [ "$(wp option get site_icon 2>/dev/null || echo 0)" = 0 ] && [ -f "wp-content/themes/$THEME/assets/brand/mark-512.png" ]; then
	ICON=$(wp media import "wp-content/themes/$THEME/assets/brand/mark-512.png" --title="Иконка сайта" --porcelain 2>/dev/null || true)
	[ -n "$ICON" ] && wp option update site_icon "$ICON"
fi

# Telegram для копий заявок — из секретов (тогда менять их в секретах, а не в админке).
if [ -n "${TG_BOT_TOKEN:-}" ] && [ -n "${TG_CHAT_ID:-}" ]; then
	wpx eval '$s = (array) get_option( "acme_settings", array() ); $s["tg_bot_token"] = getenv( "TG_BOT_TOKEN" ); $s["tg_chat_id"] = getenv( "TG_CHAT_ID" ); update_option( "acme_settings", $s ); echo "Telegram для заявок настроен\n";'
fi

# Адреса записей (константа плагина), правила ЧПУ.
wpx eval 'if ( defined( "ACME_PERMALINKS" ) && ACME_PERMALINKS !== get_option( "permalink_structure" ) ) { $GLOBALS["wp_rewrite"]->set_permalink_structure( ACME_PERMALINKS ); echo "Адреса записей: " . ACME_PERMALINKS . "\n"; }'
wpx rewrite flush >/dev/null
# Правила ЧПУ для Apache. WP-CLI сам .htaccess не пишет (не видит mod_rewrite), без него работает только главная.
wpx eval 'require_once ABSPATH . "wp-admin/includes/misc.php"; $r = $GLOBALS["wp_rewrite"]->mod_rewrite_rules(); if ( $r && insert_with_markers( ABSPATH . ".htaccess", "WordPress", explode( "\n", trim( $r ) ) ) ) { echo ".htaccess: правила WordPress на месте\n"; } else { echo "ВНИМАНИЕ: не удалось записать .htaccess\n"; }'
# Сжатие и кеширование статики в браузере (CSS и JS темы меняют адрес с новой версией).
htaccess_block "$DEPLOY_PATH/.htaccess" 'acme cache' "$DEPLOY_PATH/wp-content/themes/$THEME/htaccess-cache.conf"
echo ".htaccess: кеширование статики на месте"
# Шаблоны, изменённые в редакторе сайта, хранятся в базе и перекрывают файлы темы из git.
CUSTOM=$(wp post list --post_type=wp_template,wp_template_part --post_status=publish --field=post_name 2>/dev/null | tr '\n' ' ')
if [ -n "${CUSTOM// /}" ]; then
	echo "ВНИМАНИЕ: в редакторе сайта изменены шаблоны: $CUSTOM— изменения этих шаблонов из git на сайте не видны."
	echo "  Сбросить: Внешний вид → Редактор → Шаблоны → шаблон → ⋮ → «Сбросить»."
fi

# ---------- Защита ----------
# Проверяется при каждом деплое: если константу или блок .htaccess убрали руками, следующий деплой вернёт.
say "Защита"
IS_HTTPS=0
case "$SITE_URL" in https://*) IS_HTTPS=1 ;; esac
# Редактор файлов темы и плагинов в админке: взломанная учётная запись не должна давать выполнение кода.
# DISALLOW_FILE_MODS не задаём: он запретил бы установку плагинов деплоем и обновления в админке.
config_set DISALLOW_FILE_EDIT true raw
case "$SITE_ENV" in production) ENV_TYPE=production ;; local) ENV_TYPE=local ;; *) ENV_TYPE=staging ;; esac
config_set WP_ENVIRONMENT_TYPE "$ENV_TYPE"
# Исправления безопасности ядра (7.1.1 → 7.1.2) ставятся сами; переход на новую ветку (7.1 → 7.2) — вручную.
config_set WP_AUTO_UPDATE_CORE minor
# Вход в админку только по HTTPS — если у сайта есть сертификат.
[ "$IS_HTTPS" = 1 ] && config_set FORCE_SSL_ADMIN true raw
if [ "$SITE_ENV" = production ]; then
	# Тексты ошибок PHP раскрывают пути и запросы к базе: на боевом сайте посетителю их не показываем.
	config_set WP_DEBUG false raw
	config_set WP_DEBUG_DISPLAY false raw
fi
# Секретные ключи должны быть свои. Создаются заново только если их нет (перевыпуск разлогинивает всех).
if [ "$(wp config get AUTH_KEY --type=constant 2>/dev/null || true)" = '' ] || [ "$(wp config get AUTH_KEY --type=constant)" = 'put your unique phrase here' ]; then
	wp config shuffle-salts >/dev/null && echo "wp-config.php: созданы секретные ключи"
fi
echo "wp-config.php: константы защиты на месте (окружение — $ENV_TYPE)"

# Заголовки безопасности и запрет служебных файлов — в начало .htaccess, раньше правил WordPress.
htaccess_block "$DEPLOY_PATH/.htaccess" 'acme security' "$CONF_DIR/htaccess-security.conf" top
# HSTS — только боевой сайт на HTTPS; в остальных случаях блок очищается.
if [ "$SITE_ENV" = production ] && [ "$IS_HTTPS" = 1 ]; then HSTS_CONF="$CONF_DIR/htaccess-hsts.conf"; else HSTS_CONF=''; fi
htaccess_block "$DEPLOY_PATH/.htaccess" 'acme hsts' "$HSTS_CONF" top
# В папке загрузок PHP не выполняется.
mkdir -p wp-content/uploads
htaccess_block "$DEPLOY_PATH/wp-content/uploads/.htaccess" 'acme uploads' "$CONF_DIR/htaccess-uploads.conf"
echo ".htaccess: заголовки безопасности, запрет служебных файлов и PHP в uploads на месте"

if [ "$PACKAGE" != none ]; then
	# Локальный сайт пропускается: там файлы принадлежат разным пользователям (репозиторий, root, www-data).
	harden_config_mode
	# Файлы, доступные на запись всем пользователям сервера, — частая причина взлома на общем хостинге.
	# Только сообщаем: на части хостингов загрузки без этого не работают, решение — за владельцем.
	# Папка кеша страниц не в счёт: такой её создаёт сам плагин кеша.
	LOOSE=$({ find . -xdev -path ./wp-content/cache -prune -o \( -type f -o -type d \) -perm -0002 -print 2>/dev/null || true; } | wc -l)
	[ "$LOOSE" -eq 0 ] || echo "ВНИМАНИЕ: файлов и папок с записью для всех (права 777/666): $LOOSE. Обычно достаточно 755 для папок и 644 для файлов."
fi

# ---------- Кеш страниц ----------
say "Кеш страниц"
if [ "$PAGE_CACHE" = 1 ]; then
	wp plugin is-installed "$CACHE_PLUGIN" || wp plugin install "$CACHE_PLUGIN"
	# Включение создаёт wp-content/advanced-cache.php и ставит WP_CACHE в wp-config.php.
	wp plugin is-active "$CACHE_PLUGIN" || wpx plugin activate "$CACHE_PLUGIN"
	# Настройки каркаса принадлежат деплою (остальные поля владелец меняет в админке). Вошедшие пользователи,
	# запросы не GET и ответы не 200 (404, редиректы) плагин не кеширует сам.
	CACHE_HOURS=$CACHE_HOURS CACHE_SKIP_PATHS=$CACHE_SKIP_PATHS CACHE_SKIP_QUERY=$CACHE_SKIP_QUERY wpx eval '
		$want = array(
			"cache_expires"                      => 1,
			"cache_expiry_time"                  => (int) getenv( "CACHE_HOURS" ),
			"clear_site_cache_on_saved_post"     => 1, // запись видна в списках и меню — чистим весь сайт
			"clear_site_cache_on_saved_term"     => 1,
			"clear_site_cache_on_changed_plugin" => 1,
			"compress_cache"                     => 1,
			"excluded_page_paths"                => getenv( "CACHE_SKIP_PATHS" ),
			"excluded_query_strings"             => getenv( "CACHE_SKIP_QUERY" ),
		);
		$now = (array) get_option( "cache_enabler", array() );
		$new = Cache_Enabler::validate_settings( array_merge( $now, $want ) );
		if ( $new != $now ) {
			update_option( "cache_enabler", $new );
			echo "Настройки кеша обновлены\n";
		}
	'
	if [ "$(wp config get WP_CACHE --type=constant 2>/dev/null || true)" = 1 ]; then
		echo "Кеш страниц включён ($CACHE_PLUGIN, страница хранится до $CACHE_HOURS ч)"
	else
		echo "ВНИМАНИЕ: в wp-config.php нет WP_CACHE — кеш страниц не работает (файл недоступен на запись?)"
	fi
elif wp plugin is-active "$CACHE_PLUGIN" >/dev/null 2>&1; then
	wpx plugin deactivate "$CACHE_PLUGIN" >/dev/null && echo "Кеш страниц выключен (PAGE_CACHE=0)"
else
	echo "Кеш страниц выключен (PAGE_CACHE=0)"
fi

# Автообновление плагинов по умолчанию выключено: крупное обновление без присмотра может сломать сайт.
# Порядок — обновить на тестовом, проверить, затем на боевом. AUTO_UPDATE_PLUGINS=1 — включить осознанно.
AUTO_UPDATE_ACTION=''
case "${AUTO_UPDATE_PLUGINS:-}" in 1) AUTO_UPDATE_ACTION=enable ;; 0) AUTO_UPDATE_ACTION=disable ;; esac
if [ -n "$AUTO_UPDATE_ACTION" ]; then
	# «|| true»: WP-CLI возвращает ошибку, если плагин уже в нужном состоянии или ещё не установлен.
	# shellcheck disable=SC2086
	wp plugin auto-updates "$AUTO_UPDATE_ACTION" $PLUGINS "$CACHE_PLUGIN" >/dev/null 2>&1 || true
	echo "Автообновление плагинов: $AUTO_UPDATE_ACTION"
fi

flush_caches

REPLACED=0 # деплой дошёл до конца — автооткат больше не нужен
say "Готово: $SITE_URL"
