# wp-block-site

Скилл для [Claude Code](https://claude.com/claude-code) и готовый каркас проекта: сайт на WordPress с блочной
темой без сборки и плагином данных, где редактор удобен владельцу, код читается за пару минут, SEO настроено
с первого дня, а проверки и деплой делает GitHub Actions.

Подход проверен на живом сайте [kalaganov5.com](https://kalaganov5.com): каждое правило записано вместе с
причиной — обычно это ошибка, на которую уже наступили.

> A Claude Code skill and a project scaffold for WordPress sites built on a block theme (no build step) and a
> data plugin: readable code enforced by linters, Rank Math configured by code, an editor that does not get
> in the way, security headers, and CI/CD over SSH to shared hosting. Docs are in Russian.

## Что получается

| Часть | Что внутри |
|---|---|
| **Тема** | `theme.json` на токенах дизайна, шаблоны, динамические блоки `block.json` + `render.php` + `style.css`, один `site.js` без зависимостей |
| **Плагин данных** | типы записей и поля без ACF, настройки сайта, импорт контента, SEO, формы и заявки, аналитика с согласием на cookie, безопасность, редиректы, команды `wp <prefix> …` |
| **Читаемый код** | пределы размера (строка 120, функция 40 строк, файл 250, вложенность 3), PHPCS + ESLint + Stylelint + Prettier, словарь имён, тесты бизнес-правил, одна команда `npm run check` |
| **Удобный редактор** | записи открываются без шаблона, H1 правится в поле заголовка, на холсте ничего не плавает, в блоках нет образцов-заглушек; проверка `editor-usability.js` |
| **SEO и GEO** | SEO-поля в исходнике контента с проверкой длины и дублей, Rank Math настроен кодом (версиями), JSON-LD по типам страниц, llms.txt, robots.txt для ботов ИИ, SEO-гейт в CI |
| **Скорость** | шрифты локально, CSS блоков только на страницах с блоком, `acme_image()` с srcset и fetchpriority, WebP при загрузке, бюджет веса CSS/JS в проверке |
| **Безопасность** | заголовки безопасности, закрытые XML-RPC и перечисление пользователей, запрет PHP в uploads, константы wp-config, поиск секретов перед коммитом и в CI |
| **CI/CD** | `npm run check`, установка с нуля в CI на двух версиях PHP, деплой по SSH с бэкапом базы и откатом, ручной деплой без Actions, копия боевого на тестовый, дежурная проверка, ежемесячный SEO-аудит |

## Установка

Скилл — это папка. Склонируйте репозиторий в папку скиллов Claude Code:

```bash
# для всех проектов
git clone https://github.com/kalaganov5/wp-block-site ~/.claude/skills/wp-block-site

# или только для одного проекта
git clone https://github.com/kalaganov5/wp-block-site .claude/skills/wp-block-site
```

Claude подхватит скилл по описанию («сделай сайт на WordPress по макету», «блочная тема», «перенеси сайт»)
или по команде `/wp-block-site`. В claude.ai скилл загружается архивом папки (Settings → Capabilities → Skills).

Без Claude: начните с [`SKILL.md`](SKILL.md) — это методика, а каркас разворачивается одной командой.

## Быстрый старт: новый сайт

Нужны Node 22+, Docker, Git.

```bash
node ~/.claude/skills/wp-block-site/scripts/scaffold.js ../studio-site \
  --slug=studio --name="Studio Pro" --domain=studio.pro
cd ../studio-site
git init && npm install && composer install     # инструменты проверки; хук перед коммитом подключится сам
docker compose up -d && docker compose logs -f setup   # http://localhost:8080, admin / admin
npm run check
```

`--slug` становится папкой темы, плагином `<slug>-core`, префиксом функций, классов, блоков и команд WP-CLI.
После `scaffold.js` — шаги из таблицы «Порядок работы» в [`SKILL.md`](SKILL.md).

## Как устроено

```
SKILL.md              методика: главные правила, порядок работы, определение «готово»
references/           главы с причинами каждого правила (архитектура, дизайн, блоки, контент, i18n, SEO,
                      скорость, формы, CI/CD, проверки, чек-листы, процесс, перенос вёрстки, читаемый код)
templates/project/    рабочий проект с именем-заглушкой acme — его копирует scaffold.js
scripts/scaffold.js   разворачивает каркас и заменяет имена
evals/evals.json      типовые задачи с критериями: проверка, что агент со скиллом следует правилам
```

Главы:

| Глава | О чём |
|---|---|
| [01](references/01-architecture.md) | Два слоя (тема и плагин данных), карта файлов, имена и префиксы |
| [02](references/02-design-system.md) | Токены, тёмная тема, `theme.json`, шрифты, CSS по блокам |
| [03](references/03-blocks-and-editor.md) | Динамические блоки, общий редактор без сборки, удобный редактор |
| [04](references/04-content-model.md) | Типы записей, схема полей, настройки, роли страниц, цены-переменные |
| [05](references/05-content-pipeline.md) | Контент из прототипа, импорт, версии контента |
| [06](references/06-i18n.md) | Polylang, строки интерфейса, адреса на разных языках |
| [07](references/07-seo-geo.md) | SEO-поля, Rank Math кодом, JSON-LD, llms.txt, SEO-гейт, перенос и 301 |
| [08](references/08-performance-a11y.md) | PageSpeed 90+ и доступность AA |
| [09](references/09-forms-analytics.md) | Contact Form 7, заявки, Telegram, GTM + Consent Mode v2 |
| [10](references/10-ci-cd.md) | Docker, CI, деплой, откат, безопасность сервера, синхронизация |
| [11](references/11-qa-audits.md) | Все автоматические проверки и как их читать |
| [12](references/12-checklists.md) | Бриф, чек-листы запуска, приёмки и аудита |
| [13](references/13-workflow.md) | Git, коммиты, документация, работа ИИ-агента |
| [14](references/14-legacy-markup.md) | Перенос чужой вёрстки в блоки |
| [15](references/15-code-style.md) | Читаемый код: пределы, структура, имена, тесты |

## Проверки самого скилла

`.github/workflows/scaffold.yml` на каждый push разворачивает каркас, ставит инструменты и запускает
`npm run check` в получившемся проекте. Полная установка с нуля, деплой и SEO-гейт проверяются CI
развёрнутого проекта.

## Развитие

Скилл дорабатывается по опыту реальных сайтов. Предложения и ошибки — в
[Issues](https://github.com/kalaganov5/wp-block-site/issues); как предложить правку — [CONTRIBUTING.md](CONTRIBUTING.md).
История версий — [CHANGELOG.md](CHANGELOG.md).

## Лицензия

[GPL-2.0-or-later](LICENSE) — как у WordPress: код темы и плагина из каркаса попадает в сайты на WordPress и
должен быть совместим с его лицензией.
