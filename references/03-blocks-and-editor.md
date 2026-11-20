# 3. Блоки, шаблоны и удобный редактор

## Два вида содержимого

| Что | Чем делается | Почему |
|---|---|---|
| Тексты: абзацы, заголовки, списки, таблицы, кнопки, картинки, вопросы-ответы, секции | **core-блоки** (`core/paragraph`, `core/heading`, `core/list`, `core/buttons`, `core/group`, `core/details`…) с классами и стилями дизайн-системы | Владелец правит текст прямо на холсте, как в Word; разметка стандартная и переживёт любую тему |
| Составные интерфейсы с данными и логикой: шапка, подвал, крошки, каталог, слайдеры, тарифы, форма, карточки из записей, калькулятор | **динамические блоки темы** `acme/*`: `block.json` + `render.php` | Разметку отдаёт сервер при каждом показе: её можно менять в git без «недействительных блоков»; данные (записи, настройки, цены) подставляются свежие |

Статические блоки с `save()` в теме не используются: при изменении разметки все старые экземпляры в базе
становятся «недействительными» и требуют миграций (deprecations).

## Динамический блок темы

Новый блок — генератором, а не копированием:

```bash
node wordpress/tools/new-block.js price-table --title="Таблица цен" --attrs=title,lead,items,showNote
```

Он создаёт `block.json` (тип атрибута — по имени: `items` → массив объектов, `showX`/`isX` → да/нет,
`perPage`/`xCount` → число, остальное — строка), `render.php` с экранированием и пустым состоянием и
`style.css`. Дальше — подписи полей в `LABELS` (`editor.js`) и строки интерфейса в `project/content/strings.js`.
Регистрировать ничего не нужно. В VS Code: Tasks → «новый блок темы» (спросит имя, название, атрибуты).

**Блок целиком живёт в своей папке** — разметка, данные и вид:

```
blocks/problem-cards/
├── block.json     атрибуты, "style": "file:./style.css"
├── render.php     разметка
└── style.css      вид блока
```

WordPress подключает `style.css` блока только на страницах, где блок есть, и в редакторе. Общие стили —
в `assets/css/` (глава 02).

`block.json`:

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "acme/problem-cards",
  "title": "Карточки «С чем приходят»",
  "category": "acme",
  "description": "Карточки проблем со ссылками на услуги.",
  "textdomain": "acme",
  "attributes": {
    "items": {
      "type": "array",
      "default": [],
      "itemShape": { "title": "", "post": 0, "urgent": false }
    },
    "moreLabel": { "type": "string", "default": "" }
  },
  "supports": { "html": false, "customClassName": false, "reusable": false },
  "editorScript": "acme-editor",
  "style": "file:./style.css",
  "render": "file:./render.php"
}
```

Правила для `block.json`:

- **В `default` нет образцов текста.** Образец из `default` сохраняется в каждую новую запись и выглядит
  как настоящий контент: «Заголовок карточки» попадал на сайт, а в редакторе заглушку было не отличить от
  текста. Поэтому списки — `"default": []`, подписи — `""` (запасная подпись — `acme_t()` в `render.php`).
- **Форма элемента списка — `itemShape`.** Общий `editor.js` строит панель по типу значения: строка → поле
  (длинная → многострочное), `boolean` → переключатель, число → числовое поле, массив строк → «по одной на
  строку», массив объектов → карточки с полями из `itemShape` и кнопками «+ Добавить»/«Удалить», объект →
  вложенные поля.
- Элемент без заголовка не выводится: нажатое «+ Добавить» без текста не даёт пустой карточки на сайте.
- `supports.html: false` (нельзя «Редактировать как HTML»), `customClassName: false`, `reusable: false`.
- Шапка/подвал: `"inserter": false` — их вставляют только части шаблона.
- `editorScript` — общий хэндл `acme-editor`; у блока нет своего JS для редактора.
- Подписи интерфейса — пустой `default` и `acme_t('key')` в `render.php` (строки интерфейса, см. 06-i18n).
- **Заголовки в блоках начинаются с H2.** H1 страницы выводит шаблон из заголовка записи.

`render.php`:

```php
<?php
/**
 * Что это за блок, откуда данные, какие атрибуты (кратко).
 */
defined( 'ABSPATH' ) || exit;

$acme_items = array_filter(
	(array) ( $attributes['items'] ?? array() ),
	function ( $acme_item ) {
		return '' !== trim( (string) ( $acme_item['title'] ?? '' ) );
	}
);
if ( ! $acme_items ) {
	return; // на сайте пустой блок ничего не выводит, в редакторе показывает подсказку
}
?>
<div class="acme-cards">
	<?php foreach ( $acme_items as $acme_item ) : ?>
		<a class="acme-card" href="<?php echo esc_url( get_permalink( (int) ( $acme_item['post'] ?? 0 ) ) ); ?>">
			<h3><?php echo esc_html( $acme_item['title'] ); ?></h3>
		</a>
	<?php endforeach; ?>
</div>
```

Правила для `render.php`: каждый вывод экранирован (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` для
доверенного HTML); данные плагина — через `function_exists`/`acme_has_core()`; подписи — `acme_t()`; нет
запросов в цикле (собрать ID и сделать один `get_posts`); переменные с префиксом и понятным именем
(`$acme_service`, а не `$s`); значения вычисляются вверху, разметка ниже — по тегу на строку; картинки —
через `acme_image()` (глава 08).

Регистрация всех блоков одной функцией (`inc/blocks.php`):

```php
foreach ( glob( get_theme_file_path( 'blocks/*/block.json' ) ) as $json ) {
	$type = register_block_type( dirname( $json ) );
	if ( $type ) { $names[] = $type->name; }
}
wp_add_inline_script( 'acme-editor', 'window.ACME_BLOCKS = ' . wp_json_encode( $names ) . ';', 'before' );
```

## Общий editor.js без сборки

Один файл на глобальных `wp.*` (зависимости хэндла: `wp-blocks, wp-block-editor, wp-components, wp-element,
wp-server-side-render, wp-data, wp-core-data`). Для каждого имени из `window.ACME_BLOCKS` регистрирует
`edit` + `save: () => null`:

- `InspectorControls` → «Настройки блока»: поля по атрибутам (`Field` выбирает контрол по типу `default`).
  Подписи полей — словарь `LABELS` { ключ → понятная подпись }.
- Превью — `ServerSideRender` внутри `Disabled` (клики по ссылкам превью не уводят из редактора):
  - `httpMethod: 'POST'` — в GET-запросе `false` во вложенных атрибутах стал бы строкой `"false"`;
  - `urlQueryArgs: { acmelang: <язык записи> }` — превью на языке редактируемой записи;
  - `EmptyResponsePlaceholder` — у пустого блока всегда видна короткая подсказка: «Карточки: пусто —
    заполните в настройках блока (панель справа)» или «…: на этой странице ничего не выводит». На сайте
    пустой блок не выводит ничего.

Готовый файл — `templates/project/wordpress/acme/assets/js/editor.js`.

## Превью знает «текущую страницу»

REST-запрос `/wp/v2/block-renderer/` идёт без контекста страницы: `is_singular()`, `get_queried_object_id()`,
`is_front_page()` пусты, и крошки, шапка кейса и т. п. выглядели бы иначе. Фильтр делает редактируемую
запись текущей:

```php
add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {
	$id = (int) $request->get_param( 'post_id' );
	if ( ! $id || 0 !== strpos( $request->get_route(), '/wp/v2/block-renderer/' ) || ! current_user_can( 'edit_post', $id ) ) {
		return $response;
	}
	$type = get_post_type( $id );
	$args = array( 'post_type' => $type, 'post_status' => 'any', 'lang' => '' );
	$args[ 'page' === $type ? 'page_id' : 'p' ] = $id;
	$GLOBALS['wp_query'] = new WP_Query( $args );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	return $response;
}, 10, 3 );
```

## Стили core-блоков = классы дизайн-системы

`register_block_style()` даёт владельцу выбор в панели «Стили», а `render_block` добавляет «настоящий» класс
дизайн-системы, чтобы CSS не зависел от `is-style-*`:

```php
$styles = array(
	'core/button' => array( 'primary' => 'Основная', 'secondary' => 'Вторичная', 'ghost' => 'Текстовая' ),
	'core/list'   => array( 'facts' => 'Факты', 'steps' => 'Шаги с номером' ),
	'core/group'  => array( 'card' => 'Карточка' ),
	'core/heading'=> array( 'new' => 'Новый раздел' ),
);
```

Другие полезные доработки в `render_block` (через `WP_HTML_Tag_Processor`, не регулярками по всему HTML):

- таблица со стилем «карточками на мобильном» получает `data-label` из `<thead>` в каждую ячейку;
- фрагмент с классом `acme-lang-ru` на других языках получает `lang="ru"` (непереведённый текст для экранных
  дикторов и переносов);
- заголовкам H2 статьи — `id` (якорь автора или транслит текста) для оглавления.

## Шаблоны и части

Каждый шаблон — одна и та же оболочка:

```html
<!-- wp:template-part {"slug":"header","tagName":"div","className":"acme-header-part"} /-->
<!-- wp:group {"tagName":"main","anchor":"main","className":"acme-main"} -->
<main id="main" class="wp-block-group acme-main"><!-- wp:acme/breadcrumbs /--><!-- wp:post-content {"className":"acme-content"} /--></main>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","tagName":"div","className":"acme-footer-part"} /-->
```

- `parts/header.html` и `parts/footer.html` содержат по одному блоку (`acme/site-header`, `acme/site-footer`):
  меню, переключатель языка, статус, мегаменю — серверная разметка, легко меняется в git.
- **H1 — только в шаблоне**: `page.html`, `single.html`, `single-acme_service.html` выводят `core/post-title`
  уровня 1. Текст записи, паттерны и блоки начинаются с H2. Один H1 на странице проверяют сборщик контента
  и SEO-гейт в CI.
- Страницы (`page.html`) — заголовок и `post-content`: страница собирается в редакторе из секций
  (группа `section.acme-section` → `div.acme-wrap` → содержимое).
- Свои типы — `single-acme_service.html`, `archive-acme_service.html`; список записей — `core/query` с
  `post-template`, внутри которого карточка — блок темы (`acme/post-card`).
- Шаблоны в git — единственный источник. Шаблон, сохранённый в «Внешний вид → Редактор», живёт в базе и
  перекрывает файл; `remote.sh` после деплоя выводит предупреждение со списком таких шаблонов.

## Паттерны

`patterns/*.php` с заголовком (`Title`, `Slug`, `Categories`, `Post Types`, `Description`). Для каждого типа
записи — паттерн «полная страница» (все секции, подсказки в квадратных скобках): новая услуга
создаётся «Добавить → паттерн», а не копированием. В паттерне нет H1 — заголовок вводится в поле заголовка
записи. `remove_theme_support('core-block-patterns')` — чужие паттерны WordPress не мешают.

Разметку блоков в паттерне не переформатируют: перенос строки внутри сохранённого блока делает его
«недействительным». Поэтому `patterns/` исключены из правила о длине строки и из Prettier.

## Удобный редактор: правила

Цель — владелец открывает старую статью, ставит курсор в любой абзац и правит, а заголовок меняет в поле
заголовка. Точное превью страницы вторично и включается по желанию.

На живом сайте, сделанном по прежней версии каркаса, было наоборот: запись открывалась сразу внутри шаблона
(шапка, крошки, форма, подвал — серверные превью вокруг текста), липкие элементы закрывали текст и
перехватывали клики, H1 нельзя было поправить, а образцы из `default` выглядели как настоящий контент.

1. **Записи открываются в обычном редакторе**: поле заголовка и текст, без шаблона. Шаблон показывает
   кнопка самого редактора «Показать шаблон». Включить показ шаблона по умолчанию для типа записи — одна
   строка в `inc/setup.php`:
   ```php
   const ACME_TEMPLATE_MODE_POST_TYPES = array(); // например: array( 'page', 'acme_service' )
   ```
   (хук `registered_post_type`, потому что Polylang перерегистрирует типы при смене языка).
2. **В обычном режиме заголовок и текст стоят там же, где на сайте**: `editor.css` даёт полю заголовка и
   содержимому те же поля и ширину, что `.acme-wrap` и колонка статьи.
3. **На холсте ничто не плавает и не прилипает.** `editor.css` ставит шапку и всё с классом `.acme-is-sticky`
   в обычный поток и прячет окно cookie и мобильное меню. Липкий элемент на сайте делается только классом
   `.acme-is-sticky`; новый элемент с `position: fixed` — в список в `editor.css`. Проверяет
   `tools/qa/editor-usability.js`: на холсте не должно быть ни одного такого элемента.
4. **Те же стили на холсте**: `add_editor_style()` с общими файлами (`ACME_BASE_STYLES` в `inc/assets.php`) и
   `editor.css`; стили блоков редактор берёт из `block.json`. `editor.css` — только поправки холста, в том
   числе `.block-editor-rich-text__editable:not(:focus) { white-space: normal }` (редактор показывает пробелы
   как `pre-wrap`, и заголовки переносились иначе, чем на сайте).
5. **`site.js` работает и на холсте** (`enqueue_block_assets` + `is_admin()`, флаг `window.ACME.editor`), но
   только то, что не мешает правке: слайдеры и плашки — да, мобильное меню — нет (оно открывалось на весь
   холст и запирало прокрутку). Устройство:
   - каждая функция запускается через `init(root)` и помечает элемент `once(el, 'key')` — превью
     перерисовывается сколько угодно раз без дублирования слушателей;
   - в редакторе работает только внутри iframe (`if (!window.frameElement) return;`) и перезапускает
     `init(document)` через `MutationObserver` с задержкой 150 мс;
   - `<html lang>` холста = язык записи (от него зависят `hyphens: auto` и кавычки).
6. **Никаких заглушек**: пустой блок на холсте — короткая нейтральная подсказка, на сайте — ничего.
7. Места, которые на сайте заполняет сервер (цены `{{price:hour}}`), в редакторе видны меткой — это описано
   в README.

### Проверка

`QA_BASE=http://localhost:8080 QA_USER=admin QA_PASS=admin node wordpress/tools/qa/editor-usability.js`:

- создать запись, ввести заголовок и абзац, сохранить черновик — проверить через REST, что сохранилось;
- открыть существующую страницу и услугу, кликнуть в первый абзац и напечатать — текст попал в блок (клик
  никто не перехватил);
- включить «Показать шаблон» и повторить; на холсте нет элементов с `position: fixed` или `sticky`.

Скрипт выключает приветственные окна редактора для пользователя проверки и удаляет созданный черновик.
Рядом — `editor-validity.js`: ни одного «недействительного» блока во всех записях.
