# 1. Архитектура

## Два слоя: тема (вид) и плагин данных (модель)

| Слой | Папка | Отвечает за | Не делает |
|---|---|---|---|
| Блочная тема | `wordpress/<slug>/` | Разметку и вид: шаблоны, части, блоки темы, CSS, JS, паттерны, подписи интерфейса, правила `.htaccess` для статики | Не регистрирует типы записей, не хранит данные, не ходит во внешние API |
| Плагин данных | `wordpress/<slug>-core/` | Типы записей и поля, таксономии, страница настроек, роли служебных страниц, импорт, SEO, формы и заявки, аналитика, редиректы, кеш, команды WP-CLI | Не выводит разметку страниц (кроме служебных вставок в `<head>`) |

Почему так:

- Смена дизайна не теряет данные: тип записи живёт в плагине, тема только выводит.
- Тема не падает без плагина: все функции плагина вызываются через `function_exists()` или обёртки
  (`acme_has_core()`), блок без данных возвращает пустоту. Это защищает от белого экрана, если плагин
  выключили или обновление легло наполовину.
- Плагин проверяется отдельно и ставится на любом сервере тем же архивом.

Связь между слоями — только через функции плагина из `inc/api.php` (`acme_setting()`, `acme_role_url()`,
`acme_lang()`). Тема никогда не читает опции плагина напрямую и не вызывает его внутренние функции — это
проверяет `npm run check` (проверка `boundary`). Поэтому плагин можно перестраивать внутри, не разыскивая
вызовы по теме.

## Карта файлов каркаса

```
.
├── CLAUDE.md                     правила для ИИ-агента в этом проекте
├── TODO.md                       список дел с владельцем каждого пункта
├── docs/glossary.md              словарь: одно понятие — одно английское имя в коде
├── docs/decisions.md             принятые решения: почему так и что отвергнуто
├── package.json, composer.json   инструменты разработки (линтеры, Playwright) — на сервер не попадают
├── phpcs.xml.dist                PHPCS: безопасность, оформление WordPress, пределы размера, совместимость PHP
├── eslint.config.js, .prettierrc.json, .stylelintrc.json   правила JS, оформления и CSS
├── .githooks/pre-commit          поиск секретов + npm run check перед каждым коммитом
├── compose.yaml                  локальный сайт: MariaDB + WordPress (PHP как на хостинге) + одноразовый setup;
│                                 phpMyAdmin по профилю tools; ACME_BIND=0.0.0.0 — доступ из сети (телефон)
├── .editorconfig .gitattributes .gitignore
├── .vscode/                      расширения, задачи (сайт, телефон, phpMyAdmin, новый блок, проверки), сниппеты acme-*
├── .github/
│   ├── actions/ssh/action.yml    подготовка SSH (ключ или пароль, known_hosts) → $SSH_CMD
│   ├── dependabot.yml            обновления actions, npm, composer раз в неделю
│   └── workflows/
│       ├── ci.yml                npm run check + установка с нуля + проверки страниц + SEO-гейт
│       ├── deploy.yml            CI → выкладка по SSH в окружение test | production, откат при сбое
│       ├── sync.yml              копия боевого на тестовый (база + uploads)
│       ├── health.yml            дежурная проверка боевого сайта каждые 6 часов → Telegram
│       └── seo-audit.yml         обход живого сайта + Lighthouse → ветка seo-reports, раз в месяц
├── project/content/              исходник контента: site.js (страницы, SEO-поля), strings.js (подписи)
└── wordpress/
    ├── README.md                 документация владельца и разработчика
    ├── acme/                     ТЕМА
    │   ├── style.css             заголовок темы (версия → версия статики)
    │   ├── theme.json            палитра, шрифты, пресеты отступов и скруглений = токены; лишнее выключено
    │   ├── functions.php         только require модулей inc/
    │   ├── inc/setup.php         поддержка темы, меню, режим редактора (ACME_TEMPLATE_MODE_POST_TYPES)
    │   ├── inc/assets.php        стили и скрипты (ACME_BASE_STYLES), тёмная тема без мигания, preload шрифтов
    │   ├── inc/blocks.php        регистрация blocks/*, стили core-блоков, render_block-доработки, превью в редакторе
    │   ├── inc/images.php        acme_image(): размеры, srcset, lazy, fetchpriority; WebP при загрузке
    │   ├── inc/core.php          доступ к плагину данных: acme_has_core() и обёртки над inc/api.php плагина
    │   ├── inc/markup.php        общие куски разметки: иконки, хлебные крошки
    │   ├── inc/strings.php       подписи интерфейса: acme_t(), регистрация в Polylang
    │   ├── inc/strings-data.php  значения подписей по языкам (генерируется сборщиком)
    │   ├── inc/seo.php           title/description, если Rank Math не установлен
    │   ├── inc/embeds.php        лёгкие встраивания (YouTube по клику)
    │   ├── assets/css/           fonts, tokens, base, core-blocks, components — общие; editor — поправки холста
    │   ├── assets/js/site.js     весь интерактив сайта, без зависимостей
    │   ├── assets/js/editor.js   общий редактор для всех блоков темы (без сборки)
    │   ├── assets/fonts/         woff2 (variable), лицензии OFL
    │   ├── blocks/<имя>/         block.json + render.php + style.css
    │   ├── templates/*.html      index, page, single, 404, архивы и single для своих типов; H1 — здесь
    │   ├── parts/header.html footer.html  — по одному блоку: acme/site-header, acme/site-footer
    │   ├── patterns/*.php        готовые секции для редактора (без H1)
    │   └── htaccess-cache.conf   сжатие и кеш статики (деплой вставляет в .htaccess)
    ├── acme-core/                ПЛАГИН ДАННЫХ
    │   ├── acme-core.php         заголовок, константы, require модулей; WP-CLI-модули — только под WP_CLI
    │   ├── inc/api.php           ПУБЛИЧНЫЙ ИНТЕРФЕЙС: язык, настройки, роли и адреса страниц, цены — тема
    │   │                         вызывает только эти функции (проверка boundary)
    │   ├── inc/post-types.php    типы записей, таксономии, acme_meta_schema() → register_post_meta
    │   ├── inc/editor.php        подключение панели полей в редакторе
    │   ├── inc/settings.php      «Настройки → <Сайт>»: контакты, адрес, профили, коды вебмастеров, интеграции
    │   ├── inc/prices.php, prices-admin.php   переменные {{price:key}} и страница «Настройки → Цены»
    │   ├── inc/polylang.php      переводимые типы; noindex и hreflang для непереведённых копий
    │   ├── inc/seo/              SEO и GEO по файлу на задачу: rank-math.php (настройки версиями), options,
    │   │                         onboarding, titles, descriptions, canonical, closed, schema*, local-business,
    │   │                         dates, social, verification, indexnow, llms, robots, status, cli (wp acme seo)
    │   ├── inc/security.php      XML-RPC, перечисление пользователей, версия WordPress, пароли приложений
    │   ├── inc/analytics.php     GTM + Consent Mode v2, отложенная загрузка
    │   ├── inc/leads.php         Contact Form 7 → заявки (тип записи) + копия в Telegram, антиспам
    │   ├── inc/redirects.php     301 со старых адресов (только на 404)
    │   ├── inc/slugs.php         транслит кириллицы в адресах
    │   ├── inc/cache.php         сброс кеша страниц при изменении «глобальных» данных
    │   ├── inc/import/           wp acme import [--force]: cli.php (порядок шагов), posts, terms, media, menus,
    │   │                         forms, languages, options, placeholders, cleanup
    │   ├── assets/meta-panel.js  панели полей записи по схеме
    │   └── content/              content.json, prices.php, обложки — данные для импорта (генерируются)
    ├── deploy/
    │   ├── lib.sh                поиск PHP нужной версии, установка WP-CLI, обёртка wpat
    │   ├── remote.sh             установка/обновление сайта (идемпотентно), откат ROLLBACK=1
    │   ├── manual.sh             ручной деплой с компьютера, когда GitHub Actions недоступен
    │   ├── pages.txt, smoke.sh   один список страниц и одна проверка после деплоя (CI, деплой, ручной, дежурная)
    │   ├── contract.sh           контракт страниц в CI: мета, JSON-LD, llms.txt, редиректы
    │   ├── htaccess-*.conf       заголовки безопасности, HSTS, запрет PHP в uploads
    │   └── sync.sh               боевой → тестовый
    ├── local/Dockerfile setup.sh локальный сайт запускает тот же remote.sh
    ├── local/acme-local-host.php mu-plugin только для Docker: сайт по любому адресу (localhost, IP в сети)
    ├── local/phone.js            адрес сайта в локальной сети + QR-код для телефона
    └── tools/
        ├── check.js              npm run check — все проверки одной командой
        ├── build-content.js      прототип → content.json (+ строки интерфейса); проверяет SEO-поля
        ├── new-block.js          генератор блока темы: block.json + render.php + style.css
        ├── lib/                  blocks.js (разметка Gutenberg), seo-rules.js, budget.js, php-files.js
        ├── tests/                тесты правил: *.test.php (run.php + заглушки WordPress), *.test.js (node --test)
        ├── scan-secrets.sh, git-hooks.js   поиск секретов (gitleaks), подключение хуков
        └── qa/                   crawl.js, interact.js, editor-validity.js, editor-usability.js, seo-crawl.js,
                                  seo-gate.js, legacy-urls.txt; общие модули — qa/lib/
```

## Имена и префиксы

Один короткий префикс на проект (в каркасе `acme`) используется везде — это и пространство имён, и способ
найти «своё» поиском:

| Где | Формат | Пример |
|---|---|---|
| Функции PHP | `prefix_*` | `acme_setting()`, `acme_t()` |
| Константы | `PREFIX_*` | `ACME_CORE_DIR`, `ACME_SEO_VERSION` |
| Опции, мета, транзиенты | `prefix_*` | `acme_settings`, `acme_imported`, `acme_key` |
| Типы записей, таксономии | `prefix_*` (≤ 20 символов) | `acme_service`, `acme_topic` |
| Блоки | `prefix/имя` | `acme/site-header` |
| Классы CSS сайта | `prefix-*`, БЭМ-подобно | `.acme-header__nav`, `.acme-card.is-active` |
| Классы дизайн-системы | короткий свой префикс (в kalaganov5 — `vm-`) | `.vm-btn-primary` |
| data-атрибуты для JS | `data-prefix-*` | `[data-acme-slider]` |
| Команды WP-CLI | `wp prefix <команда>` | `wp acme import`, `wp acme seo` |
| localStorage | короткий ключ проекта | `acme-theme`, `acme-consent` |

Состояния — модификаторы `is-*` (`is-open`, `is-urgent`), а не новые классы.

## Где хранится что

| Данные | Где | Кто меняет |
|---|---|---|
| Тексты страниц, услуги, статьи | записи WordPress (блоки) | владелец в админке |
| Поля записей (цена, анонс, «было → стало») | post meta по схеме | владелец, панель в редакторе |
| Контакты, профили, токены интеграций | опция `acme_settings` | владелец, «Настройки → Сайт» |
| Какая страница — «Цены», «Контакты»… | опция `acme_roles` (ID) | импорт; владелец может переназначить |
| Подписи интерфейса (кнопки, меню, форма) | `strings-data.php` по умолчанию, поверх — «Перевод строк» Polylang | владелец |
| Меню | «Внешний вид → Меню», по области на язык | импорт создаёт один раз, дальше владелец |
| Цены как переменные | `content/prices.php` по умолчанию, поверх — опция `acme_prices` | владелец, «Настройки → Цены» |
| Настройки Rank Math | опции Rank Math | `wp acme seo` один раз на версию, дальше владелец |
| Вид, разметка, логика | файлы темы и плагина | только git |

Правило: всё, что видно посетителю и может поменяться без программиста, — в базе и редактируется в админке.
Всё, что определяет поведение, — в git.

## Зависимости и версии

- WordPress 7.0+ (тестировать на последней), PHP 8.3+; локально и в CI — 8.3 и 8.4. Почему именно так и где
  понизить — глава 10, «Версии».
- Никакого npm/composer в теме и плагине: нет шага сборки, нет `node_modules` и `vendor` на сервере.
  `package.json` и `composer.json` в корне — только инструменты разработки (линтеры, Playwright, сборщики),
  они не попадают в архив деплоя.
- Плагины ставит `remote.sh` из каталога wordpress.org, не хранит в git.
