#!/usr/bin/env bash
# Выкладка на сервер: пакет темы и плагина → сервер → remote.sh → проверка страниц → откат, если сайт сломан.
# Запускается там, откуда идёт деплой: на раннере GitHub (deploy.yml) или на компьютере разработчика (manual.sh).
# Оба пути — этот один скрипт, поэтому ручной деплой делает ровно то же, что Actions.
#
# Что происходит:
#   1. тема, плагин, remote.sh, lib.sh, настройки .htaccess и файл переменных уходят одним подключением;
#   2. на сервере запускается remote.sh (копия базы, обновление файлов, настройки, кеш);
#   3. smoke.sh проверяет страницы из pages.txt;
#   4. страницы не открываются → второе подключение: remote.sh с ROLLBACK=1 возвращает предыдущую версию темы
#      и плагина, страницы проверяются ещё раз, скрипт завершается ошибкой с итогом, что произошло.
#
# Переменные окружения:
#   SITE_URL DEPLOY_PATH SITE_ENV и остальные переменные remote.sh (см. его шапку) — уходят на сервер файлом
#   Подключение — одно из двух:
#     SSH_CMD + SSH_USER + SSH_HOST   команда ssh с параметрами (её готовит .github/actions/ssh или manual.sh)
#     ACME_EXEC                       команда вместо ssh — для проверки без сервера, например
#                                     «docker compose exec -T wordpress» (см. README, «Проверка деплоя в Docker»)
#   [ROLLBACK=1]    не выкладывать, а вернуть предыдущую версию темы и плагина
#   [SKIP_CHECK=1]  не проверять страницы (сайт готовится в папке, на которую домен ещё не смотрит)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
say() { printf '\n=== %s\n' "$*"; }
die() { printf '\nОШИБКА: %s\n' "$*" >&2; exit 1; }

THEME=acme
PLUGIN=acme-core
# Переменные, которые получает remote.sh. Файлом с правами 600, а не в командной строке: её видно в списке процессов.
REMOTE_VARS='DEPLOY_PATH SITE_URL DB_NAME DB_USER DB_PASSWORD DB_HOST ADMIN_USER ADMIN_PASSWORD ADMIN_EMAIL PHP_BIN TG_BOT_TOKEN TG_CHAT_ID SITE_ENV NOINDEX FORCE_IMPORT PAGE_CACHE AUTO_UPDATE_PLUGINS PROBE_URL ROLLBACK'

: "${SITE_URL:?нет SITE_URL}"
: "${DEPLOY_PATH:?нет DEPLOY_PATH}"
SITE_URL="${SITE_URL%/}"
SITE_ENV="${SITE_ENV:-test}"
ROLLBACK="${ROLLBACK:-0}"
if [ -z "${ACME_EXEC:-}" ]; then
	: "${SSH_CMD:?нет SSH_CMD (команда ssh) — или задайте ACME_EXEC}"
	: "${SSH_USER:?нет SSH_USER}"
	: "${SSH_HOST:?нет SSH_HOST}"
fi

BUNDLE=$(mktemp -d)
trap 'rm -rf "$BUNDLE"' EXIT

# Итог запуска: в терминал и в сводку GitHub Actions (Summary), если скрипт запущен там.
summary() {
	printf '%s\n' "$*"
	[ -z "${GITHUB_STEP_SUMMARY:-}" ] || printf '%s\n\n' "$*" >> "$GITHUB_STEP_SUMMARY"
}

# run_remote <0|1> — отправляет набор файлов и запускает remote.sh; аргумент — значение ROLLBACK.
run_remote() {
	# shellcheck disable=SC2034 # читается через ${!name} ниже
	local ROLLBACK=$1 name dir remote
	(
		umask 077
		: > "$BUNDLE/deploy.env"
		for name in $REMOTE_VARS; do
			printf 'export %s=%q\n' "$name" "${!name:-}" >> "$BUNDLE/deploy.env"
		done
	)
	dir=".acme-deploy-$(date +%Y%m%d%H%M%S)-$$"
	# Временная папка на сервере закрыта от других пользователей (700) и удаляется при любом исходе.
	# PACKAGE дописывается на сервере: путь зависит от домашней папки. bash -l — чтобы PATH был как при входе по SSH.
	# shellcheck disable=SC2016 # $HOME, $D и $s раскрываются на сервере
	remote='D="$HOME/'"$dir"'"; mkdir -p "$D" && chmod 700 "$D" && tar -xzf - -C "$D" && echo "export PACKAGE=\"$D/package.tgz\"" >> "$D/deploy.env" && bash -lc "set -a; . \"$D/deploy.env\"; set +a; bash \"$D/remote.sh\" </dev/null"; s=$?; rm -rf "$D"; exit $s'
	if [ -n "${ACME_EXEC:-}" ]; then
		# shellcheck disable=SC2086 # команда из нескольких слов
		tar -czf - -C "$BUNDLE" . | $ACME_EXEC bash -c "$remote"
	else
		# shellcheck disable=SC2086
		tar -czf - -C "$BUNDLE" . | $SSH_CMD "$SSH_USER@$SSH_HOST" "$remote"
	fi
}

check_pages() {
	[ "${SKIP_CHECK:-0}" = 1 ] && { echo "Страницы не проверялись (SKIP_CHECK=1)"; return 0; }
	bash wordpress/deploy/smoke.sh "$SITE_URL"
}

# ---------- Набор файлов для сервера ----------
cp wordpress/deploy/remote.sh wordpress/deploy/lib.sh wordpress/deploy/htaccess-*.conf "$BUNDLE/"

# ---------- Откат по запросу ----------
if [ "$ROLLBACK" = 1 ]; then
	say "Откат на $SITE_URL ($SITE_ENV)"
	run_remote 1 || die "откат не выполнен — смотрите сообщение сервера выше"
	say "Проверка страниц"
	check_pages || { summary "Откат выполнен, но страницы не открываются: $SITE_URL. Причина не в последней выкладке темы и плагина — смотрите журнал ошибок PHP на сервере."; exit 1; }
	summary "Откат выполнен: на $SITE_URL возвращена предыдущая версия темы и плагина. База не менялась."
	exit 0
fi

# ---------- Выкладка ----------
say "Пакет"
tar -czf "$BUNDLE/package.tgz" -C wordpress "$THEME" "$PLUGIN"
echo "Тема и плагин: $(du -h "$BUNDLE/package.tgz" | cut -f1)"

say "Выкладка на $SITE_URL ($SITE_ENV)"
# Если remote.sh упал после замены файлов, он сам возвращает предыдущую версию (см. on_exit в remote.sh).
run_remote 0 || { summary "Выкладка на $SITE_URL не выполнена: скрипт на сервере завершился ошибкой (сообщение — в журнале выше). Если тема и плагин уже были заменены, сервер вернул предыдущую версию сам."; exit 1; }

say "Проверка страниц"
if check_pages; then
	summary "Выложено: $SITE_URL ($SITE_ENV)."
	exit 0
fi

say "Страницы не открываются — откат на предыдущую версию темы и плагина"
if ! run_remote 1; then
	summary "Выкладка сломала сайт $SITE_URL, и автоматический откат не выполнен (предыдущей версии на сервере нет?). Сайт требует внимания сейчас."
	exit 1
fi
say "Проверка страниц после отката"
if check_pages; then
	summary "Выкладка на $SITE_URL отменена: после неё не открывались страницы (список — выше). Предыдущая версия темы и плагина возвращена, сайт работает. База не откатывалась — команда для неё напечатана сервером выше."
else
	summary "Выкладка сломала сайт $SITE_URL; предыдущая версия темы и плагина возвращена, но страницы всё ещё не открываются. Сайт требует внимания сейчас: смотрите журнал ошибок PHP на сервере."
fi
exit 1
