/*
 * Генераторы разметки блоков Gutenberg в каноническом виде (так, как их сохраняет редактор WordPress 6.8).
 * Если разметка отличается от канонической хотя бы пробелом, редактор покажет блок «недействительным».
 * Формат проверяется скриптом tools/qa/editor-validity.js (в редакторе).
 *
 * Отдельно не запускается — подключается сборщиком контента:
 *   const blocks = require('./lib/blocks');   // wordpress/tools/build-content.js
 */

const esc = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
const escAttr = (value) => esc(value).replace(/"/g, '&quot;');

const isFilled = (value) => value !== undefined && value !== null && value !== '';

// Как serialize_block_attributes() в WordPress.
function attrsJson(attrs) {
  const clean = Object.fromEntries(
    Object.entries(attrs || {}).filter(([, value]) => isFilled(value)),
  );
  if (!Object.keys(clean).length) return '';
  const json = JSON.stringify(clean)
    .replace(/--/g, '\\u002d\\u002d')
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026')
    .replace(/\\"/g, '\\u0022');
  return ' ' + json;
}

const cls = (...classNames) => classNames.flat().filter(Boolean).join(' ');
const clsAttr = (className) => (className ? ` class="${className}"` : '');

function block(name, attrs, inner) {
  const json = attrsJson(attrs);
  if (inner === undefined) return `<!-- wp:${name}${json} /-->`;
  return `<!-- wp:${name}${json} -->\n${inner}\n<!-- /wp:${name} -->`;
}

// Динамический блок темы (рендер на сервере): acme('cards', {...}) → <!-- wp:acme/cards {...} /-->.
const acme = (name, attrs) => block('acme/' + name, attrs);

// Встроенный HTML (уже экранированный) для абзаца.
function paragraph(html, options = {}) {
  const { className } = options;
  return block('paragraph', { className }, `<p${clsAttr(className)}>${html}</p>`);
}
// Абзац из обычного текста: экранирует сам.
const textParagraph = (text, options) => paragraph(esc(text), options);

function heading(text, options = {}) {
  const level = options.level || 2;
  const html = options.html ? text : esc(text);
  // Уровень 2 — значение по умолчанию: редактор его в атрибуты не записывает.
  const attrs = { level: level === 2 ? undefined : level, className: options.className };
  return block(
    'heading',
    attrs,
    `<h${level} class="${cls('wp-block-heading', options.className)}">${html}</h${level}>`,
  );
}

// Пункт списка: строка (текст) или { html, className }.
function listItem(source) {
  const item = typeof source === 'string' ? { html: esc(source) } : source;
  return block(
    'list-item',
    { className: item.className },
    `<li${clsAttr(item.className)}>${item.html}</li>`,
  );
}

// items: строки (текст) или { html, className }
function list(items, options = {}) {
  const tag = options.ordered ? 'ol' : 'ul';
  const itemsHtml = items.map(listItem).join('');
  return block(
    'list',
    { ordered: options.ordered || undefined, className: options.className },
    `<${tag} class="${cls('wp-block-list', options.className)}">${itemsHtml}</${tag}>`,
  );
}

function group(inner, options = {}) {
  const tag = options.tagName || 'div';
  // div — тег по умолчанию: редактор его в атрибуты не записывает.
  const attrs = {
    tagName: tag === 'div' ? undefined : tag,
    anchor: options.anchor,
    className: options.className,
  };
  const idAttr = options.anchor ? ` id="${options.anchor}"` : '';
  const body = Array.isArray(inner) ? inner.join('\n\n') : inner;
  return block(
    'group',
    attrs,
    `<${tag}${idAttr} class="${cls('wp-block-group', options.className)}">${body}</${tag}>`,
  );
}

// Кнопка: { text, url, className, target, rel }.
function button(item) {
  const target = item.target ? ` target="${item.target}"` : '';
  const rel = item.rel ? ` rel="${item.rel}"` : '';
  return block(
    'button',
    { className: item.className, linkTarget: item.target, rel: item.rel },
    `<div class="${cls('wp-block-button', item.className)}"><a class="wp-block-button__link wp-element-button" href="${escAttr(item.url)}"${target}${rel}>${esc(item.text)}</a></div>`,
  );
}

function buttons(items, options = {}) {
  const inner = items.map(button).join('\n\n');
  return block(
    'buttons',
    { className: options.className },
    `<div class="${cls('wp-block-buttons', options.className)}">${inner}</div>`,
  );
}

// options: { id, url, alt, className, sizeSlug }
function image(options) {
  const size = options.sizeSlug || 'full';
  const idClass = options.id ? ` class="wp-image-${options.id}"` : '';
  return block(
    'image',
    { id: options.id, sizeSlug: size, linkDestination: 'none', className: options.className },
    `<figure class="${cls('wp-block-image', 'size-' + size, options.className)}"><img src="${escAttr(options.url)}" alt="${escAttr(options.alt || '')}"${idClass}/></figure>`,
  );
}

// Ячейка таблицы: строка (текст) либо { html, tag:'th', scope:'row' }.
function tableCell(source, defaultTag) {
  const cell = typeof source === 'string' ? { html: esc(source) } : source;
  const tag = cell.tag || defaultTag;
  const scope = cell.scope ? ` scope="${cell.scope}"` : '';
  return `<${tag}${scope}>${cell.html}</${tag}>`;
}

// Ячейка шапки: строка превращается в <th scope="col">.
const tableHeadCell = (source) =>
  tableCell(typeof source === 'string' ? { html: esc(source), scope: 'col' } : source, 'th');

const tableRow = (cells) => `<tr>${cells.map((cell) => tableCell(cell, 'td')).join('')}</tr>`;

// rows: массив строк; ячейка — строка (текст), либо { html, tag:'th', scope:'row' }
function table(head, rows, options = {}) {
  const thead = head ? `<thead><tr>${head.map(tableHeadCell).join('')}</tr></thead>` : '';
  const tbody = `<tbody>${rows.map(tableRow).join('')}</tbody>`;
  return block(
    'table',
    { hasFixedLayout: false, className: options.className },
    `<figure class="${cls('wp-block-table', options.className)}"><table>${thead}${tbody}</table></figure>`,
  );
}

function details(summary, answerHtml, options = {}) {
  const openAttr = options.open ? ' open' : '';
  return block(
    'details',
    { showContent: options.open || undefined },
    `<details class="wp-block-details"${openAttr}><summary>${esc(summary)}</summary>${paragraph(answerHtml)}</details>`,
  );
}

module.exports = {
  esc,
  escAttr,
  attrsJson,
  block,
  acme,
  paragraph,
  textParagraph,
  heading,
  list,
  group,
  buttons,
  image,
  table,
  details,
  cls,
};
