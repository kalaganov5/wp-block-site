#!/usr/bin/env node
/*
 * Новый динамический блок темы: папка blocks/<имя>/ с тремя файлами — block.json, render.php, style.css.
 * Регистрировать блок и писать JS не нужно: inc/blocks.php находит папку сам, панель настроек в редакторе
 * строится по атрибутам block.json (assets/js/editor.js), а style.css объявлен в block.json — WordPress
 * подключает его только на страницах, где блок есть, и в редакторе.
 *
 *   node wordpress/tools/new-block.js <имя> [--title="Название в редакторе"] [--attrs=title,lead,items]
 *
 *   <имя>    латиница в нижнем регистре, цифры и дефис: price-table, team
 *   --attrs  атрибуты через запятую. Тип по имени: items/rows/list → массив объектов (карточки с полями),
 *            showX, isX, hasX → да/нет; xCount, xNum, perPage → число; остальное — строка.
 */
const fs = require('fs');
const path = require('path');

const THEME = path.resolve(__dirname, '..', 'acme');

function fail(message) {
  console.error('Ошибка: ' + message);
  process.exit(1);
}

// ---------- Аргументы командной строки ----------
// --ключ=значение → { ключ: значение }; в значении может быть свой знак «=».
function parseOptions(args) {
  const pairs = args
    .filter((arg) => arg.startsWith('--'))
    .map((arg) => {
      const [key, ...valueParts] = arg.slice(2).split('=');
      return [key, valueParts.join('=')];
    });
  return Object.fromEntries(pairs);
}

function validateName(name) {
  if (!name)
    fail(
      'укажите имя блока: node wordpress/tools/new-block.js price-table --title="Таблица цен" --attrs=title,items',
    );
  if (!/^[a-z][a-z0-9-]{1,40}$/.test(name) || name.endsWith('-'))
    fail('имя — латиница в нижнем регистре, цифры и дефис, с буквы (price-table)');
}

function parseAttrNames(attrsOption) {
  const attrNames = (attrsOption || 'title')
    .split(',')
    .map((attrName) => attrName.trim())
    .filter(Boolean);
  for (const attrName of attrNames) {
    if (!/^[a-zA-Z][a-zA-Z0-9]*$/.test(attrName))
      fail(`атрибут «${attrName}»: только латиница и цифры, camelCase`);
  }
  return attrNames;
}

// Порядок проверок — как у сообщений об ошибках: имя, занятая папка, затем атрибуты.
function readArgs() {
  const args = process.argv.slice(2);
  const options = parseOptions(args);
  const name = args.find((arg) => !arg.startsWith('--'));
  validateName(name);
  const dir = path.join(THEME, 'blocks', name);
  if (fs.existsSync(dir)) fail(`блок ${name} уже есть: ${path.relative(process.cwd(), dir)}`);
  return { name, dir, title: options.title || name, attrNames: parseAttrNames(options.attrs) };
}

// ---------- block.json ----------
// default каждого атрибута — образец формы данных: по нему editor.js выбирает поле в панели.
// Список объектов по умолчанию пуст, поля его элемента — в itemShape: образец в default попал бы на сайт.
function attrDefinition(attrName) {
  if (/^(items|rows|list)$/.test(attrName))
    return { type: 'array', default: [], itemShape: { title: '', text: '', url: '' } };
  if (/^(show|is|has)[A-Z]/.test(attrName)) return { type: 'boolean', default: false };
  if (/(Count|Num|^perPage)$/.test(attrName)) return { type: 'integer', default: 0 };
  return { type: 'string', default: '' };
}

const buildBlockJson = (spec) => ({
  $schema: 'https://schemas.wp.org/trunk/block.json',
  apiVersion: 3,
  name: `acme/${spec.name}`,
  title: spec.title,
  category: 'acme',
  description: `${spec.title}. Описание для редактора: что выводит блок и откуда берёт данные.`,
  textdomain: 'acme',
  attributes: spec.attributes,
  supports: { html: false, customClassName: false, reusable: false },
  editorScript: 'acme-editor',
  style: 'file:./style.css',
  render: 'file:./render.php',
});

// ---------- render.php ----------
// Имя переменной PHP для атрибута: showPhoto → $acme_show_photo.
const phpVar = (attrName) =>
  `$acme_${attrName.replace(/[A-Z]/g, (letter) => '_' + letter.toLowerCase())}`;

// Список: элемент без названия — незаполненный («+ Добавить» и ничего не вписано), на сайт он не попадает.
const phpReadList = (variable, attrName) => `${variable} = array_filter(
	(array) ( $attributes['${attrName}'] ?? array() ),
	function ( $item ) {
		return '' !== trim( (string) ( $item['title'] ?? '' ) );
	}
);`;

// render.php: каждый атрибут читается с значением по умолчанию, всё экранируется.
function phpRead(attrName, type) {
  const variable = phpVar(attrName);
  if (type === 'array') return phpReadList(variable, attrName);
  if (type === 'boolean') return `${variable} = ! empty( $attributes['${attrName}'] );`;
  if (type === 'integer') return `${variable} = (int) ( $attributes['${attrName}'] ?? 0 );`;
  return `${variable} = (string) ( $attributes['${attrName}'] ?? '' );`;
}

const headingMarkup = (attrName) =>
  `\t\t<?php if ( '' !== ${phpVar(attrName)} ) : ?>\n\t\t\t<h2><?php echo esc_html( ${phpVar(attrName)} ); ?></h2>\n\t\t<?php endif; ?>`;

const paragraphMarkup = (attrName, isLead) =>
  `\t\t<?php if ( '' !== ${phpVar(attrName)} ) : ?>\n\t\t\t<p${isLead ? ' class="acme-lead"' : ''}><?php echo esc_html( ${phpVar(attrName)} ); ?></p>\n\t\t<?php endif; ?>`;

const listMarkup = (attrName, className) =>
  `\t\t<div class="${className}__list">\n\t\t\t<?php foreach ( ${phpVar(attrName)} as $acme_it ) : ?>\n\t\t\t\t<div class="${className}__item">\n\t\t\t\t\t<h3><?php echo esc_html( $acme_it['title'] ?? '' ); ?></h3>\n\t\t\t\t\t<?php if ( ! empty( $acme_it['text'] ) ) : ?>\n\t\t\t\t\t\t<p><?php echo esc_html( $acme_it['text'] ); ?></p>\n\t\t\t\t\t<?php endif; ?>\n\t\t\t\t</div>\n\t\t\t<?php endforeach; ?>\n\t\t</div>`;

// Роли атрибутов в разметке.
// Первая строка — заголовок H2, следующие — абзацы (вторая — лид), первый массив — карточки.
function attrRoles(spec) {
  const typeOf = (attrName) => spec.attributes[attrName].type;
  const stringNames = spec.attrNames.filter((attrName) => typeOf(attrName) === 'string');
  return {
    headingName: stringNames[0],
    paragraphNames: stringNames.slice(1),
    listName: spec.attrNames.find((attrName) => typeOf(attrName) === 'array'),
    unusedNames: spec.attrNames.filter(
      (attrName) => !['string', 'array'].includes(typeOf(attrName)),
    ),
  };
}

function renderBody(roles, className) {
  return [
    roles.headingName ? headingMarkup(roles.headingName) : '',
    ...roles.paragraphNames.map((attrName, i) => paragraphMarkup(attrName, i === 0)),
    roles.listName ? listMarkup(roles.listName, className) : '',
  ]
    .filter(Boolean)
    .join('\n');
}

// Условие PHP «блок пуст»: нет заголовка и нет карточек. Без строк и массивов блок пустым не бывает.
function emptyCondition(roles) {
  const isListEmpty = roles.listName ? `! ${phpVar(roles.listName)}` : '';
  if (roles.headingName) {
    return `'' === ${phpVar(roles.headingName)}` + (isListEmpty ? ` && ${isListEmpty}` : '');
  }
  return isListEmpty || 'false';
}

function buildRenderPhp(spec) {
  const roles = attrRoles(spec);
  const reads = spec.attrNames
    .map((attrName) => phpRead(attrName, spec.attributes[attrName].type))
    .join('\n');
  const unusedNote = roles.unusedNames.length
    ? `\n * Пока не выводятся в разметке (используйте в условиях или запросах): ${roles.unusedNames.join(', ')}.`
    : '';
  return `<?php
/**
 * ${spec.title}.
 * Атрибуты: ${spec.attrNames.join(', ')}. Тексты интерфейса — acme_t( 'ключ' ), данные плагина — через acme_has_core().${unusedNote}
 */

defined( 'ABSPATH' ) || exit;

${reads}
if ( ${emptyCondition(roles)} ) {
	return; // пустой блок ничего не выводит; в редакторе на его месте подпись-подсказка
}
?>
<section class="acme-section ${spec.className}">
	<div class="acme-wrap">
${renderBody(roles, spec.className)}
	</div>
</section>
`;
}

// ---------- style.css ----------
// Стили блока лежат рядом с ним; общее для нескольких блоков — в assets/css/components.css.
const buildStyleCss = (spec) => `/* ${spec.title}. */
.${spec.className}__list {
	display: grid;
	gap: var(--space-4);
	grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.${spec.className}__item {
	display: grid;
	gap: var(--space-2);
	padding: var(--space-5);
	background: var(--surface-raised);
	border: 1px solid var(--line);
	border-radius: var(--radius-lg);
}
`;

// ---------- Запись файлов ----------
function printSummary(spec) {
  const relative = (file) => path.relative(process.cwd(), file).replace(/\\/g, '/');
  console.log(`Блок acme/${spec.name} создан:
  ${relative(path.join(spec.dir, 'block.json'))}
  ${relative(path.join(spec.dir, 'render.php'))}
  ${relative(path.join(spec.dir, 'style.css'))}

Дальше:
  - подписи полей панели — LABELS в wordpress/acme/assets/js/editor.js (иначе показывается имя атрибута);
  - тексты интерфейса (кнопки, aria) — ключи в project/content/strings.js, затем build-content.js;
  - обновите страницу редактора: блок в «+» → категория «Acme Site».`);
}

function main() {
  const spec = readArgs();
  spec.attributes = Object.fromEntries(
    spec.attrNames.map((attrName) => [attrName, attrDefinition(attrName)]),
  );
  spec.className = `acme-${spec.name}`;

  fs.mkdirSync(spec.dir, { recursive: true });
  fs.writeFileSync(
    path.join(spec.dir, 'block.json'),
    JSON.stringify(buildBlockJson(spec), null, '\t') + '\n',
  );
  fs.writeFileSync(path.join(spec.dir, 'render.php'), buildRenderPhp(spec));
  fs.writeFileSync(path.join(spec.dir, 'style.css'), buildStyleCss(spec));
  printSummary(spec);
}

main();
