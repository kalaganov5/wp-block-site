/*
 * Текст PHP-файла с данными: «return [ ключ => [ ключ => значение ] ];». Так сборщик контента пишет цены
 * (acme-core/content/prices.php) и подписи интерфейса (acme/inc/strings-data.php).
 *
 * Отдельно не запускается — подключается сборщиком: wordpress/tools/build-content.js.
 */
const phpString = (value) => "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
const phpArrayRows = (map, indent) =>
  Object.entries(map)
    .map(([key, value]) => `${indent}${phpString(key)} => ${phpString(value)},\n`)
    .join('');
const phpNestedArray = (key, map) => `\t${phpString(key)} => [\n${phpArrayRows(map, '\t\t')}\t],\n`;

// headerLines — продолжение первой строки-комментария и следующие строки шапки (уже с «//»).
function phpArrayFile(headerLines, mapsByKey) {
  let php =
    '<?php\n// Сгенерировано tools/build-content.js. ' + headerLines.join('\n') + '\nreturn [\n';
  for (const [key, map] of Object.entries(mapsByKey)) php += phpNestedArray(key, map);
  return php + '];\n';
}

module.exports = { phpArrayFile };
