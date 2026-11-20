# Как предложить правку

Спасибо, что помогаете. Скилл описывает подход, проверенный на живых сайтах, поэтому у каждого правила есть
причина. Правка принимается легче, если она приходит с причиной: что сломалось или мешало, на каком сайте или
версии WordPress, как проверено.

## Перед началом

1. Прочитайте [`SKILL.md`](SKILL.md) и главу, к которой относится правка.
2. Ошибка или идея без кода — [Issue](https://github.com/kalaganov5/wp-block-site/issues): что ожидали, что
   получилось, версии WordPress, PHP, Rank Math.
3. Одна правка — одна тема. Правило и код каркаса, который ему следует, меняются в одном pull request.

## Правка каркаса (`templates/project/`)

```bash
node scripts/scaffold.js ../wpbs-check --slug=check --name="Check" --domain=check.example
cd ../wpbs-check && npm install && composer install
npm run check                                   # должен быть зелёным
docker compose up -d && docker compose logs -f setup
QA_BASE=http://localhost:8080 node wordpress/tools/qa/crawl.js 360
QA_BASE=http://localhost:8080 QA_USER=admin QA_PASS=admin node wordpress/tools/qa/editor-usability.js
node wordpress/tools/qa/seo-gate.js http://localhost:8080
```

- Правила кода — [глава 15](references/15-code-style.md): строка ≤ 120, функция ≤ 40 строк, файл ≤ 250,
  вложенность ≤ 3; имена английские и говорящие; комментарии объясняют «почему».
- Имя-заглушка в каркасе — `acme` (`acme_`, `acme-`, `ACME_`, `Acme Site`, `acme.example`): `scaffold.js` заменяет
  его на имя проекта. Другие имена проекта в каркасе не используются.
- Имена опций, фильтров и мета-полей сторонних плагинов (Rank Math, Polylang, Contact Form 7) сверяются с их
  исходным кодом; в описании правки — версия плагина, на которой проверено.

## Правка методики (`SKILL.md`, `references/`)

- Пишите для человека без опыта WordPress: путь по меню, что будет, если нажать, почему нельзя.
- Новое правило — с причиной рядом. «Так лучше» — не причина; «страницы с видео падали до 52 в PageSpeed» — причина.
- Если правило меняет поведение агента, добавьте или поправьте задачу в [`evals/evals.json`](evals/evals.json).

## Коммиты

[Conventional Commits](https://www.conventionalcommits.org/) по-английски: `feat(seo): add LocalBusiness from site
settings`, `fix(editor): keep sticky header in flow on the canvas`, `docs(ci): explain rollback`. Тело — что и почему.

Каждое заметное изменение — строка в разделе `[Unreleased]` файла [`CHANGELOG.md`](CHANGELOG.md).

## Что нельзя присылать

Пароли, ключи, токены, экспорт сайтов, личные данные. Перед каждым коммитом поиск секретов запускается сам
(хук каркаса), в CI — тоже.
