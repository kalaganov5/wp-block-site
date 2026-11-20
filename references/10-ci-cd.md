# 10. Локальный сайт, CI, деплой, защита, синхронизация

## Один скрипт развёртывания на все случаи

`wordpress/deploy/remote.sh` запускают пять потребителей — поэтому ошибки деплоя видны до выкладки:

| Где | Как | Особенности |
|---|---|---|
| Локальный Docker | `compose.yaml` → сервис `setup` → `local/setup.sh` → `remote.sh` | `PACKAGE=none`: тема и плагин смонтированы из репозитория; `SITE_ENV=local`, кеш страниц выключен |
| CI | `ci.yml`, job «Установка с нуля» | MySQL; установка, затем повторный запуск (должен ничего не менять), на PHP 8.3 и 8.4 |
| Тестовый сервер | `deploy.yml` → `deploy.sh`, окружение `test` | закрыт от поисковиков, ставится Query Monitor, контент перезаливается при новой `version` |
| Боевой сервер | `deploy.yml` → `deploy.sh`, окружение `production` | только из `main`, открыт для поисковиков (если не `NOINDEX=1`) |
| Ручной деплой | `manual.sh test\|production` → `deploy.sh` | когда GitHub Actions недоступен |

### Версии

Минимум — **PHP 8.3** и **WordPress 7.0** (предыдущая основная ветка; тестируется на текущей). PHP 8.2 и
старее не поддерживаются: текущий Contact Form 7 (6.2.5) требует PHP 8.3, и установка с нуля на 8.2 падала.
Минимум задан в одном месте на слой, у каждого — комментарий, как понизить для старого хостинга:

| Слой | Где |
|---|---|
| Тема, плагин | заголовки `style.css` и `acme-core.php` (`Requires at least`, `Requires PHP`) |
| PHPCS | `phpcs.xml.dist`: `testVersion`, `minimum_wp_version` |
| Деплой | `lib.sh`: `MIN_PHP_ID`, `MIN_PHP` — не найден подходящий PHP → подсказка, где переключить версию в панели хостинга или задать `PHP_BIN` |
| Локально | `ACME_PHP` (по умолчанию 8.4): `ACME_PHP=8.3 docker compose up -d --build` проверяет минимум |
| CI | `ci.yml`: job `check` на 8.3, `e2e` — матрица 8.3 и 8.4 |

Понизить PHP — по строке на слой, но тогда нужно закрепить старую версию CF7.

### Что делает remote.sh (по порядку)

1. `set -euo pipefail`; `lib.sh` находит PHP нужной версии, скачивает WP-CLI в `~/.local/bin`, обёртка
   `wpat <путь> <команда>` с `memory_limit=512M`, `max_execution_time=0`, `--allow-root` только под root.
2. Проверяет обязательные переменные (`: "${DEPLOY_PATH:?…}"`) с понятным текстом, что сделать.
   `ROLLBACK=1` — вместо деплоя откат (ниже).
3. **Файлы и wp-config** (если нет `wp-config.php`): заглушка хостинга `index.html` переименовывается,
   `core download`, `config create`. **Первая установка** определяется по базе (`wp core is-installed`), а не по
   `wp-config.php`: если прошлый запуск упал после создания конфига, следующий доустановит сайт сам.
4. **Бэкап базы** перед обновлением: `~/acme-backups/<домен>-before-deploy-<дата>.sql.gz`, 10 последних.
5. **Смена адреса**: если `home` ≠ `SITE_URL` — `search-replace` (`--skip-columns=guid`), затем `home`/`siteurl`.
6. **Тема и плагин**: текущие копируются в `~/acme-backups/releases/<сайт>/` (одно поколение, вне папки сайта,
   чтобы WordPress не видел вторую тему), новые — `rsync -a --delete` из архива. Совпадают с живыми — копия не
   перезаписывается. Сразу после активации — `wp eval`: тема или плагин с фатальной ошибкой PHP останавливают
   деплой, а `trap` возвращает прошлую версию файлов без второго подключения.
7. **Плагины из каталога**: install if missing + activate (Polylang, CF7, Rank Math; Query Monitor — не на
   боевом); удалить `hello`, `akismet`; активировать тему.
8. **Языки**: переводы ядра, плагинов, темы; язык сайта (`site switch-language`).
9. **Настройки только при первой установке** (часовой пояс, формат даты, комментарии закрыты; «Привет, мир»,
   «Пример страницы» и форма CF7 по умолчанию удаляются) — дальше они принадлежат владельцу. **Каждый раз**:
   `blog_public` по окружению и `NOINDEX`.
10. **Контент**: импорт по правилам главы 05 (признак «импортировано» читается без загрузки темы), затем
    `wp acme seo` (настройки Rank Math, самопроверка SEO — пять строк в логе).
11. **Защита** (каждый деплой, ниже).
12. **Кеш страниц** (ниже).
13. Постоянные ссылки, `rewrite flush`; **`.htaccess` пишется явно**: `insert_with_markers()` — блоки
    `WordPress`, `acme cache` (`htaccess-cache.conf` темы), `acme security`, `acme hsts`. WP-CLI сам `.htaccess`
    не пишет (не видит mod_rewrite), без него работает только главная.
14. Предупреждение, если в базе есть шаблоны из редактора сайта (`wp_template`, `wp_template_part`).
15. Сброс кеша объектов и страниц.

Скрипт печатает `=== Шаг` перед каждым шагом и одну строку результата — лог деплоя читается без знаний WP.
В `remote.sh` 444 строки: предел размера файла (глава 15) к shell-скриптам линтером не применяется, разделение
на модули по шагам — задача следующей версии.

### Откат

- **Автоматически**: `deploy.sh` после выкладки запускает `smoke.sh`; не прошёл — `ROLLBACK=1 remote.sh`,
  снова `smoke.sh`, код 1 и итог в сводке запуска («Выкладка отменена, сайт работает на прошлой версии»).
- **Вручную**: Actions → Деплой → галочка `rollback`, или `bash wordpress/deploy/manual.sh production rollback`.
- Откат возвращает **только файлы** темы и плагина и чистит кеш. База не откатывается: после деплоя владелец
  мог изменить контент. Скрипт печатает точную команду восстановления базы из бэкапа — её выполняют вручную.
- Поколение одно: неудачный деплой с автооткатом перезаписывает сохранённую копию текущей версией, поэтому
  ручной откат после него вернёт ту же версию. Нужна более старая — Revert в git и обычный деплой.

### Защита сервера

Шаг «Защита» в `remote.sh` (каждый деплой, идемпотентно):

- `wp-config.php`: `DISALLOW_FILE_EDIT`, `WP_ENVIRONMENT_TYPE` (production / staging / local),
  `WP_AUTO_UPDATE_CORE=minor`, `FORCE_SSL_ADMIN` при https, `WP_DEBUG` и `WP_DEBUG_DISPLAY` — false на боевом;
  ключи-соли — `shuffle-salts`, только если их нет или стоит заглушка.
- `DISALLOW_FILE_MODS` **не задаётся**: деплой ставит плагины через WP-CLI, владелец обновляет их в админке.
- **Автообновление плагинов**: `AUTO_UPDATE_PLUGINS=1` включает, `0` выключает, пусто — не трогает. По умолчанию
  выключено: крупное обновление плагина без присмотра может сломать сайт — обновления идут тестовый → боевой.
  Ядро получает только минорные обновления (безопасность).
- **Права**: тема и плагин 755/644; `wp-config.php` — 600, если сайт его читает, иначе 640 или 644. Проверяется
  пробным файлом и крошечным PHP рядом, а не сменой прав на самом конфиге: opcache держит конфиг в памяти, и
  проверка запросом страницы давала ложный успех. Файлы с записью для всех — только предупреждение.
- **`.htaccess`** (`wordpress/deploy/`, у каждого блока своя метка):
  - `htaccess-security.conf` — `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
    `Permissions-Policy` (камера, микрофон, геолокация и др. запрещены), `X-Frame-Options: SAMEORIGIN` и
    `Content-Security-Policy: frame-ancestors 'self'`, снят `X-Powered-By`; запрещены dot-файлы (кроме
    `.well-known`), `wp-config.php`, `xmlrpc.php`, `readme.html`, `license.txt`, `*.sql`, `*.log`, `*.bak`;
  - `htaccess-hsts.conf` — `Strict-Transport-Security` на год, только боевой с https; без `includeSubDomains` и
    `preload` — их нельзя отменить быстро, если поддомен ещё без https;
  - `htaccess-uploads.conf` → `wp-content/uploads/.htaccess` — PHP в загрузках не выполняется.
- Запреты — через `mod_rewrite [F]`, а не `Require`/`Deny`: rewrite уже нужен WordPress, а `Require` при урезанном
  `AllowOverride` на общем хостинге дал бы 500 на весь сайт. Всё — в `<IfModule>`.
- **Полного CSP нет**: GTM после согласия загружает чужие скрипты, строгая политика ломает аналитику.
  Вебвизору Яндекс Метрики `frame-ancestors 'self'` мешает — готовая строка для него в комментарии файла.

В плагине — `inc/security.php`. Каждая мера отключается одной строкой
`add_filter( 'acme_security_<имя>', '__return_false' )`:

| Имя | Что делает |
|---|---|
| `xmlrpc` | XML-RPC выключен: без методов, `X-Pingback`, RSD, пингов |
| `users` | `/wp/v2/users` без входа — 401 (редактору список нужен — после входа работает); `?author=N` — 404 |
| `version` | нет generator; `?ver=<версия ядра>` у скриптов заменён отпечатком |
| `app_passwords` | пароли приложений выключены, пока нет `define( 'ACME_APP_PASSWORDS', true )` |
| `login_errors` | одно сообщение для неверного логина и неверного пароля |
| `comments` | при закрытых комментариях лента комментариев и trackback — 404 |

Проверка снаружи: `bash wordpress/deploy/hardening-check.sh <адрес>` — заголовки, закрытые файлы, xmlrpc,
пользователи, PHP в uploads, работа формы, карты сайта и llms.txt. `deploy.yml` запускает её после выкладки
(предупреждения; `STRICT=1` — ошибки).

Чего защита не закрывает: логин админа виден в `/author/<логин>/`, если знать его заранее, а «Забыли пароль»
различает существующих пользователей. Логин администратора — не `admin` и не имя с сайта.

## GitHub Actions

### ci.yml — на каждый push и PR (и `workflow_call` из деплоя)

Job **check** (PHP 8.3, Node 22): `npm ci`, `composer install`, поиск секретов во всей истории
(`scan-secrets.sh`, gitleaks по digest), `npm run check` — та же команда, что перед коммитом.

Job **e2e** (после check, матрица PHP 8.3 и 8.4, MySQL 8.4): архив темы и плагина → `remote.sh` (установка) →
`remote.sh` (обновление: лог должен содержать «Контент уже импортирован») → `php -S` с роутером →
`smoke.sh` (страницы из `pages.txt`) → `contract.sh` (контракт страниц, глава 11) → SEO-гейт
`seo-gate.js "$SITE_URL" --public` → `grep -i fatal php.log`.

Проверки в шагах пишутся «скачать в файл → grep», а не `curl | grep -q`: `grep -q` выходит на первом
совпадении, `curl` получает SIGPIPE, и с `pipefail` проверка ложно падает на больших страницах.

### deploy.yml и deploy.sh

- Триггеры: push в `main` (пути `wordpress/**`, `.github/**`) → тестовый; `workflow_dispatch`: `target:
  test|production`, галочки `force_import`, `skip_check`, `rollback` (при откате CI пропускается).
- `jobs.ci: uses: ./.github/workflows/ci.yml` → `deploy: needs: ci` — **деплой только после зелёного CI**.
  Вызванный из деплоя CI получает свою группу `concurrency` по `run_id`, иначе ручной боевой деплой и деплой по
  push отменяли бы CI друг друга.
- `environment: <target>` — секреты берутся из окружения.
- **Проверка ключей** до подключения: production — только из `refs/heads/main`, `SITE_URL` не `dev.*`,
  `DEPLOY_PATH` без `dev.`; test — `SITE_URL` не боевой домен; недостающие секреты — одной ошибкой.
- SSH — composite action `.github/actions/ssh`: `known_hosts` из секрета или `ssh-keyscan`; ключ или пароль.
- **`deploy.sh`** — общий для `deploy.yml` и `manual.sh`: пакет (архив, `remote.sh`, `lib.sh`, файл переменных
  `deploy.env` с `umask 077`, `printf 'export %s=%q'` — секреты не попадают в командную строку) → **одно**
  SSH-подключение → `remote.sh` → `smoke.sh` → при сбое откат → `hardening-check.sh`.

### Ручной деплой — manual.sh

`bash wordpress/deploy/manual.sh test|production [rollback]` — то же, что Actions, со своего компьютера (например,
когда не прошла оплата GitHub). Ключи — в `wordpress/deploy/manual-<test|production>.env` (в `.gitignore`), образец —
`manual.env.example`. Production — только из чистой и актуальной `main` и после ввода `yes`. Перед выкладкой —
`npm run check`. Переменные: `YES=1`, `SKIP_CHECK=1`, `FORCE_IMPORT=1`, `ACME_ENV_FILE`; `ACME_EXEC` — команда
вместо ssh (например, `docker compose exec -T wordpress`) для проверки скрипта без сервера.

### Один список страниц

`wordpress/deploy/pages.txt` (адреса, комментарии `#`) и `smoke.sh <адрес> [список]` — 200 и маркер темы
`acme-header`; если маркера нет — подсказка «ответ веб-сервера, не WordPress — проблема ЧПУ/.htaccess». Им
пользуются CI, деплой, ручной деплой и дежурная проверка. Новый тип страницы — строка в `pages.txt`.

### sync.yml — копия боевого на тестовый

Ручной запуск, окружение `production`, секреты `SYNC_TEST_PATH`, `SYNC_TEST_URL`. `sync.sh`: проверки (обе папки —
WordPress, разные пути и адреса, одинаковый префикс таблиц) → бэкап тестовой базы → экспорт боевой → импорт →
`search-replace` → `rsync --delete` uploads → тестовый закрыт от поисковиков, Query Monitor включён, заявки
удалены. **Обратного направления нет намеренно** — нельзя случайно затереть живой контент.

### health.yml — дежурная проверка

Каждые 6 часов и вручную: `smoke.sh` по переменной репозитория `SITE_URL`; одна повторная попытка через минуту,
затем сообщение в Telegram и красный запуск. Нет переменной — проверка пропускается с пометкой.

### seo-audit.yml — раз в месяц и вручную

Обход и Lighthouse (глава 11). `.github/scripts/seo-compare.sh` сравнивает с прошлым отчётом из ветки `seo-reports`
и пишет раздел «Изменилось с прошлого аудита». Запуск красный, только если выросло «Не 200», «Без title»,
«Без description», «Старые адреса: не 301», страниц стало меньше на 20 % и более или оценка Lighthouse упала на 10
и более. Отчёт сохраняется до пометки ошибки.

### Защита пайплайна

- `permissions: contents: read` на уровне каждого workflow; запись — только у job аудита (push отчёта).
- `timeout-minutes` у каждого job, `concurrency` у каждого workflow, `persist-credentials: false` у checkout.
- Сторонние actions закреплены полным SHA коммита с версией в комментарии; `.github/dependabot.yml` — обновления
  actions, npm, composer и образа Docker раз в неделю группами.
- Секреты в истории ищет gitleaks: перед коммитом (`.githooks/pre-commit`, добавленные файлы) и в CI (вся история).

## Окружения и секреты

**Settings → Environments → `test` и `production`**, у каждого полный набор секретов с одинаковыми именами.
Общие секреты репозитория для деплоя не используются: если секрета нет в окружении, GitHub подставит общий, и
боевой деплой мог бы взять, например, базу тестового.

| Секрет / переменная | Где | Обязателен |
|---|---|---|
| `SSH_HOST`, `SSH_USER`, `SSH_KEY` (или `SSH_PASSWORD`) | окружение, secrets | да |
| `SSH_PORT`, `SSH_KNOWN_HOSTS` | окружение, secrets | нет (22; отпечаток — желательно) |
| `DEPLOY_PATH`, `SITE_URL` | окружение, secrets | да |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST` | окружение, secrets | для первой установки |
| `ADMIN_USER`, `ADMIN_PASSWORD`, `ADMIN_EMAIL` | окружение, secrets | для первой установки |
| `PHP_BIN`, `TG_BOT_TOKEN`, `TG_CHAT_ID`, `NOINDEX`, `LEGACY_FILE`, `LEGACY_UPLOADS` | окружение, secrets | нет |
| `PAGE_CACHE` | окружение, variables | нет: 1 — кеш страниц (по умолчанию на test и production), 0 — выключить |
| `AUTO_UPDATE_PLUGINS` | окружение, variables | нет: 1 — включить, 0 — выключить, пусто — не трогать |
| `PROBE_URL` | окружение, variables | нет: адрес, по которому сервер видит сам себя, если не по `SITE_URL` |
| `SYNC_TEST_PATH`, `SYNC_TEST_URL` (только production) | окружение, secrets | для sync.yml |
| `SITE_URL` | репозиторий, variables | для health.yml и seo-audit.yml по расписанию |
| `ALERT_TG_BOT_TOKEN`, `ALERT_TG_CHAT_ID` | репозиторий, secrets | для сообщений health.yml |

Для дежурной проверки — именно `ALERT_TG_*`, а не `TG_*`: секреты репозитория GitHub подставит и в окружение
`test`, если там нет своих, и тестовые заявки ушли бы в боевой чат.

Отдельный SSH-ключ на окружение: `ssh-keygen -t ed25519 -f acme_deploy -N ""`, `.pub` → `authorized_keys`.
Для production можно включить **Required reviewers**.

## Локальный сайт (Docker)

`compose.yaml`:

- `db` — MariaDB с healthcheck;
- `wordpress` — образ `wordpress:php${ACME_PHP:-8.4}-apache` (как хостинг) + `mariadb-client`, `rsync`, WP-CLI-обёртка,
  `a2enmod rewrite expires headers deflate` и ссылкой `mysqlcheck` → `mariadb-check` (WP-CLI зовёт её, если сервер
  базы — MySQL); тема и плагин смонтированы из репозитория; порт — `${ACME_BIND:-127.0.0.1}:${ACME_PORT:-8080}`
  (по умолчанию только этот компьютер); mu-plugin `local/acme-local-host.php` — сайт открывается по любому адресу;
- `phpmyadmin` — только с профилем `tools` (`docker compose --profile tools up -d` → http://localhost:8081, вход уже
  настроен на базу `wp`); порт только на 127.0.0.1;
- `setup` — одноразовый, `depends_on` db healthy и wordpress started; запускает `local/setup.sh` →
  `remote.sh` с `PACKAGE=none`, затем `WP_DEBUG_LOG=true`, `WP_DEBUG_DISPLAY=false`, `chown www-data`;
  `local-data/` (экспорт старого сайта, вне git) смонтирована только для чтения.

Команды: `docker compose up -d` (первый раз 3–5 минут), `docker compose logs -f setup`,
`docker compose exec wordpress tail -f wp-content/debug.log`, `docker compose exec wordpress wp …`,
`docker compose down -v` (с нуля).

### Сайт на телефоне

`ACME_BIND=0.0.0.0 docker compose up -d` (PowerShell: `$env:ACME_BIND="0.0.0.0"; docker compose up -d`), затем
`node wordpress/local/phone.js` — печатает адрес в локальной сети и QR-код (или задача VS Code «открыть для
телефона»). Адрес сайта в базе не меняется: mu-plugin `acme-local-host.php` на время запроса подставляет адрес,
по которому пришёл посетитель (`option_home`/`option_siteurl`), и заменяет старый адрес в готовой странице, в том
числе в JSON. Скрипт выбирает Wi-Fi/Ethernet, а не VPN и виртуальные адаптеры. Не открывается — телефон в той же
сети, VPN на компьютере не отрезает локальную сеть, брандмауэр разрешает Docker для частных сетей.

## Запуск боевого на домене со старым сайтом (без простоя)

1. Новая база; пустая папка `<домен>/public_html_new`; экспорт старого сайта в `~/acme-legacy/export.xml`.
2. Окружение `production`: `DEPLOY_PATH=<домен>/public_html_new`, `SITE_URL=https://домен`,
   `LEGACY_UPLOADS=<домен>/public_html/wp-content/uploads`.
3. Деплой `production` с `legacy_import` и `skip_check` — сайт ставится в соседнюю папку.
4. Бэкап старого; переименование `public_html` → `public_html_old`, `public_html_new` → `public_html` (секунды).
5. `DEPLOY_PATH` → `public_html`; деплой без галочек (проверит страницы).
6. Откат — переименовать обратно. `public_html_old` удалить через 1–2 месяца.

## Кеш страниц

Деплой ставит и настраивает **Cache Enabler** (`remote.sh`, шаг «Кеш страниц») вместо ручной настройки плагина
кеша. Почему он: 252 КБ против 3,6 МБ у WP Super Cache; все настройки — одна опция `cache_enabler`, есть
`wp cache-enabler clear`; у WP Super Cache настройки лежат в PHP-файле, и из WP-CLI их без доп. пакета не задать.

- Каждый деплой подтверждает свои поля: срок 12 часов, очистка всего сайта при сохранении записи, термина и
  смене плагинов, gzip-копии.
- **Мимо кеша**: `wp-json`, карты сайта, `llms*.txt`, `robots.txt`, запросы с параметрами — кроме рекламных меток
  (`utm_*`, `yclid`, `ysclid`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `_openstat`, `_ga`, `_gl`,
  `mc_cid`, `mc_eid`): иначе рекламный трафик получал бы страницы без кеша. Вошедших, не-GET и не-200 плагин
  пропускает сам; 404 не кешируется — на 404 работают редиректы.
- `PAGE_CACHE`: по умолчанию 1 на test и production, 0 локально (разработчик сразу видит правки; включить —
  `ACME_PAGE_CACHE=1`). `0` деактивирует плагин.
- Кеш чистится в конце деплоя и после отката (иначе посетители видят старую вёрстку с новыми CSS), а плагин
  данных чистит его при сохранении «глобальных» данных (`inc/cache.php`: настройки, цены, роли, меню, «Перевод
  строк», настройки Rank Math). Поддерживаются Cache Enabler, W3 Total Cache, WP Super Cache, LiteSpeed Cache.
- **Проверить, что кеш работает**: `curl -sI -H 'Accept: text/html' https://домен/` дважды — во втором ответе
  `X-Cache-Handler: cache-enabler-engine`. Без `Accept: text/html` Cache Enabler кеш не отдаёт — поэтому
  `smoke.sh` всегда проверяет настоящую страницу, а не копию.
- **WP-Cron при кеше страниц не просыпается**: от него зависят отложенные публикации и автообновления ядра.
  Cron хостинга раз в 15 минут дёргает `wp-cron.php` (`*/15 * * * * wget -q -O - https://домен/wp-cron.php`).
