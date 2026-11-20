# Acme Site — правила для ИИ-агента

Сайт на WordPress: блочная тема `wordpress/acme/` (вид) + плагин данных `wordpress/acme-core/` (модель, импорт,
SEO, формы, аналитика, безопасность). Деплой — GitHub Actions по SSH (`wordpress/deploy/remote.sh`) или вручную
(`wordpress/deploy/manual.sh`). Документация владельца и разработчика — `wordpress/README.md`. Дела — `TODO.md`.
Словарь имён — `docs/glossary.md`, принятые решения — `docs/decisions.md`.

## Где что

| Что | Где |
|---|---|
| Цвета, шрифты, отступы | `wordpress/acme/assets/css/tokens.css` (+ палитра и пресеты `theme.json`) |
| Общий вид | `wordpress/acme/assets/css/{base,core-blocks,components}.css` |
| Вид одного блока | `wordpress/acme/blocks/<блок>/style.css` |
| Поправки редактора | `wordpress/acme/assets/css/editor.css` (на холсте ничто не плавает и не прилипает) |
| Интерактив | `wordpress/acme/assets/js/site.js` (`init(root)` + `once()`, работает и в редакторе) |
| Разметка блока | `wordpress/acme/blocks/<блок>/render.php` + `block.json` (панель строится сама по атрибутам) |
| Шаблоны | `wordpress/acme/templates/*.html` (здесь H1 — `core/post-title`), части — `parts/` |
| Картинки | `acme_image()` в `wordpress/acme/inc/images.php` |
| Контент до запуска | `project/content/site.js` (страницы и SEO-поля) → `npm run build` → `content.json` |
| Подписи интерфейса | `project/content/strings.js` (сборщик пишет `inc/strings-data.php`) |
| Что тема может вызывать у плагина | `wordpress/acme-core/inc/api.php` — и только это |
| Типы записей и поля | `wordpress/acme-core/inc/post-types.php` → `acme_meta_schema()` |
| Настройки сайта | `wordpress/acme-core/inc/settings.php` |
| SEO, JSON-LD, robots, llms.txt | `wordpress/acme-core/inc/seo/` (по файлу на задачу; Rank Math — версиями `ACME_SEO_VERSION`) |
| Безопасность | `wordpress/acme-core/inc/security.php`, `wordpress/deploy/htaccess-*.conf` |
| Деплой | `wordpress/deploy/*.sh`, `.github/workflows/*.yml`; страницы для проверки — `wordpress/deploy/pages.txt` |
| Новый блок темы | `node wordpress/tools/new-block.js <имя> --title="…" --attrs=title,lead,items` |
| Перенос чужой вёрстки | правило «чужой паттерн → core-блок или блок темы» (скилл wp-block-site, глава 14) |

## Правила

- Код — только через git; контент — в админке. Не трогать боевой сайт напрямую (SSH, SQL).
- Не работать в `main`: ветка `claude/<тема>`, PR в `main`. Коммитить только по просьбе владельца.
- Сгенерированные файлы (`content.json`, `prices.php`, `strings-data.php`) не править руками — менять исходник и
  пересобирать.
- Новый блок — генератором; новое поле — строка в `acme_meta_schema()`; новая подпись — ключ в `strings.js` на
  всех языках и `acme_t('ключ')` в разметке. Никакого текста интерфейса прямо в PHP.
- В `default` атрибутов блока нет образцов текста: списки — `[]`, форма элемента — `itemShape`.
- Один H1 на странице — из шаблона. Текст, паттерны и блоки начинаются с H2.
- Весь вывод экранирован (`esc_html`, `esc_attr`, `esc_url`); префикс `acme_`/`acme-` у всего своего.
- Тема работает без плагина и вызывает только функции из `acme-core/inc/api.php`.
- Команды WP-CLI и импорт идемпотентны: создают только отсутствующее, перезапись — только флагом `--force`.
- Не добавлять плагины, npm- и composer-зависимости в тему и плагин без согласования; внешних запросов из темы нет
  (шрифты, скрипты, стили — локально).
- Не запускать деплой production, `force_import`, откат без явного «да» владельца.

## Читаемый код

Полные правила — скилл wp-block-site, глава 15. Главное:

- Строка ≤ 120 символов (JS — 100), функция ≤ 40 строк, файл ≤ 250, вложенность ≤ 3, сложность ≤ 10. Проверяет
  линтер; обходить пределы комментариями `phpcs:ignore`/`eslint-disable` нельзя.
- Один файл — одна задача, имя по задаче. Папок и файлов `helpers`, `utils`, `common` нет.
- Имена английские и говорящие (`$contact`, а не `$c`), по словарю `docs/glossary.md`; булевы — `is_`/`has_`/`can_`.
- Комментарий объясняет **почему**, а не что. У каждого файла — шапка: за что он отвечает.
- Бизнес-правило — чистая функция с тестом в `wordpress/tools/tests/`; название теста — фраза-требование.

## Вопросы по ходу работы

Нет ответа в брифе, README или TODO — не решать молча. Спросить владельца так:

1. **Вопрос** — одной фразой.
2. **Варианты** — два-три реальных.
3. **Влияние** каждого: что затрагивает, объём, что потом трудно изменить (адреса страниц, данные в базе,
   разметка сохранённых блоков).
4. **Рекомендация** — какой вариант и почему.

Решение записать в `docs/decisions.md`.

## Проверки перед коммитом

```bash
npm install && composer install       # один раз: инструменты проверки
npm run check                         # всё: синтаксис, оформление, пределы, безопасность, граница, тесты, бюджет
npm run format                        # починить оформление
docker compose up -d && docker compose logs -f setup                 # локальный сайт: http://localhost:8080
QA_BASE=http://localhost:8080 node wordpress/tools/qa/crawl.js 360    # обход: ошибки JS, горизонтальная прокрутка
QA_BASE=http://localhost:8080 QA_USER=admin QA_PASS=admin node wordpress/tools/qa/editor-usability.js
node wordpress/tools/qa/seo-gate.js http://localhost:8080             # SEO-гейт, как в CI
```

Хук `.githooks/pre-commit` (подключается при `npm install`) запускает поиск секретов и `npm run check` сам.

## Коммиты

Conventional Commits по-английски: `feat(blocks): add price table`. Тело — пункты «что и почему», неочевидные
причины обязательно. Сгенерированные файлы — в том же коммите, что изменение исходника. README/TODO — обновить,
если меняется работа владельца.
