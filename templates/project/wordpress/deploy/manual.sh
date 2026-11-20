#!/usr/bin/env bash
# Ручной деплой по SSH со своего компьютера — то же, что workflow «Деплой» (deploy.yml), без GitHub Actions.
# Нужен, когда Actions недоступен (не прошла оплата GitHub, сбой сервиса) или сайт надо срочно откатить.
#
#   bash wordpress/deploy/manual.sh test                  # тестовый сайт
#   bash wordpress/deploy/manual.sh production            # боевой — только из main, с подтверждением
#   bash wordpress/deploy/manual.sh production rollback   # вернуть предыдущую версию темы и плагина
#
# Ключи — в wordpress/deploy/manual-<test|production>.env (в git не попадает), образец — manual.env.example.
# Порядок как в Actions: проверка ключей → npm run check → deploy.sh (пакет, remote.sh на сервере, проверка
# страниц, откат при неудаче). Файлы уходят одним SSH-подключением: пароль спросят один раз за подключение.
#
# Необязательно (переменные окружения): FORCE_IMPORT=1 — как галочка force_import в Run workflow;
# SKIP_CHECK=1 — не проверять страницы; YES=1 — не спрашивать подтверждение боевого деплоя;
# ACME_ENV_FILE — другой файл ключей; ACME_EXEC — команда вместо ssh для проверки скрипта без сервера
# (например, ACME_EXEC='docker compose exec -T wordpress').
set -euo pipefail

TARGET="${1:-}"
ACTION="${2:-deploy}"
PROD_HOST=acme.example # боевой домен: тестовый деплой не должен на него смотреть
usage() { echo "Использование: bash wordpress/deploy/manual.sh test|production [rollback]" >&2; exit 2; }
[ "$TARGET" = test ] || [ "$TARGET" = production ] || usage
[ "$ACTION" = deploy ] || [ "$ACTION" = rollback ] || usage

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
say() { printf '\n=== %s\n' "$*"; }
die() { printf '\nОШИБКА: %s\n' "$*" >&2; exit 1; }

ENV_FILE="${ACME_ENV_FILE:-wordpress/deploy/manual-$TARGET.env}"
[ -f "$ENV_FILE" ] || die "нет файла $ENV_FILE. Скопируйте wordpress/deploy/manual.env.example в $ENV_FILE и заполните."
# Все переменные файла экспортируются: их читает deploy.sh.
set -a
# shellcheck disable=SC1090 # файл ключей у каждого свой
. "$ENV_FILE"
set +a

# ---------- Проверка ключей (как в deploy.yml) ----------
miss=""
for v in DEPLOY_PATH SITE_URL; do [ -n "${!v:-}" ] || miss="$miss $v"; done
[ -n "${ACME_EXEC:-}" ] || for v in SSH_HOST SSH_USER; do [ -n "${!v:-}" ] || miss="$miss $v"; done
[ -z "$miss" ] || die "в $ENV_FILE не заданы:$miss"
SITE_URL="${SITE_URL%/}"
host=$(printf '%s' "$SITE_URL" | sed -E 's#^https?://##; s#/.*##')
if [ "$TARGET" = production ]; then
	printf '%s' "$host" | grep -qiE '^dev\.' && die "production: SITE_URL = «$host» — это тестовый адрес."
	printf '%s' "$DEPLOY_PATH" | grep -qiE '(^|/)dev\.' && die "production: DEPLOY_PATH указывает на папку тестового сайта."
else
	{ [ "$host" = "$PROD_HOST" ] || [ "$host" = "www.$PROD_HOST" ]; } && die "тестовый деплой смотрит на боевой адрес $host."
fi
# Откат кода не выкладывает, поэтому ветка и состояние git для него не важны.
if [ "$TARGET" = production ] && [ "$ACTION" = deploy ]; then
	[ "$(git rev-parse --abbrev-ref HEAD)" = main ] || die "боевой сайт выкладывается только из ветки main: сначала слейте изменения в main."
	git diff --quiet HEAD -- wordpress || die "в папке wordpress есть незакоммиченные изменения — на боевой уходит только то, что в main."
	git fetch -q origin main && [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] \
		|| die "локальная main не совпадает с origin/main: сделайте git pull (или git push), чтобы на сайте был тот же код, что в GitHub."
fi

# ---------- Проверки кода: та же команда, что в CI и перед коммитом ----------
if [ "$ACTION" = deploy ]; then
	say "Проверка кода (npm run check)"
	command -v npm >/dev/null 2>&1 || die "нужен Node.js: проверки кода запускает npm run check"
	[ -d node_modules ] || die "не установлены инструменты проверки: выполните npm install и composer install"
	npm run --silent check || die "проверки кода не прошли — исправьте и повторите (так же ответил бы CI)"
fi

# ---------- Подтверждение ----------
if [ "$TARGET" = production ] && [ "${YES:-0}" != 1 ]; then
	if [ "$ACTION" = rollback ]; then
		printf '\nВернуть предыдущую версию темы и плагина на БОЕВОМ сайте %s? Напишите yes: ' "$SITE_URL"
	else
		printf '\nВыложить %s (коммит %s) на БОЕВОЙ сайт %s? Напишите yes: ' "$(git rev-parse --abbrev-ref HEAD)" "$(git rev-parse --short HEAD)" "$SITE_URL"
	fi
	read -r answer
	[ "$answer" = yes ] || die "отменено"
fi

# ---------- Выкладка или откат: общий с Actions скрипт ----------
if [ -z "${ACME_EXEC:-}" ]; then
	# accept-new: отпечаток сервера запоминается при первом подключении, подмена сервера позже — ошибка.
	SSH_CMD="ssh -p ${SSH_PORT:-22} -o ServerAliveInterval=30 -o StrictHostKeyChecking=accept-new"
	# Путь к ключу — без пробелов (команда передаётся строкой).
	[ -n "${SSH_KEY_FILE:-}" ] && SSH_CMD="$SSH_CMD -i ${SSH_KEY_FILE/#\~/$HOME} -o IdentitiesOnly=yes"
	export SSH_CMD
fi
export SITE_URL SITE_ENV="$TARGET"
[ "$ACTION" = rollback ] && export ROLLBACK=1
exec bash wordpress/deploy/deploy.sh
