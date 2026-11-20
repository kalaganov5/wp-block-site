/*
 * Открывает записи в редакторе WordPress и ищет «недействительные» блоки (разметка в базе не совпадает с тем,
 * что сохранил бы редактор). Нужна после изменения сборщиков (tools/lib/blocks.js), паттернов и стилей блоков.
 *
 *   npm i playwright && npx playwright install chromium
 *   QA_BASE=http://localhost:8080 QA_USER=admin QA_PASS=admin node wordpress/tools/qa/editor-validity.js [ID …]
 *
 * Без ID — все страницы, записи и услуги (до 200).
 * Код выхода: 0 — недействительных блоков нет, 1 — есть (по каждой записи печатается FAIL со списком).
 */
/* global wp -- объект редактора WordPress: доступен в функциях, которые Playwright выполняет внутри страницы */
const { chromium } = require('playwright');

const { BASE, logIn, openEditor } = require('./lib/editor');

// Выполняется внутри страницы админки: ID страниц, записей и услуг через REST (не больше 200).
async function fetchAllPostIds() {
  const nonce = window.wpApiSettings ? window.wpApiSettings.nonce : '';
  const ids = [];
  for (const restBase of ['pages', 'posts', 'acme_service']) {
    const response = await fetch(
      `/wp-json/wp/v2/${restBase}?per_page=100&status=publish,draft,future&_fields=id&lang=`,
      { headers: { 'X-WP-Nonce': nonce } },
    );
    if (response.ok) (await response.json()).forEach((post) => ids.push(post.id));
  }
  return ids.slice(0, 200);
}

async function resolvePostIds(page) {
  const idsFromArgs = process.argv.slice(2).map(Number).filter(Boolean);
  if (idsFromArgs.length) return idsFromArgs;
  // REST с cookie авторизации: nonce берём со страницы админки.
  await page.goto(BASE + '/wp-admin/');
  return page.evaluate(fetchAllPostIds);
}

// Выполняется внутри страницы редактора: считает блоки (с вложенными) и собирает недействительные.
function collectInvalidBlocks() {
  const invalid = [];
  let count = 0;
  const walk = (blocks) =>
    blocks.forEach((block) => {
      count++;
      if (block.isValid === false)
        invalid.push(block.name + ': ' + (block.originalContent || '').slice(0, 120));
      walk(block.innerBlocks || []);
    });
  walk(wp.data.select('core/block-editor').getBlocks());
  return { count, invalid };
}

async function checkPost(page, id) {
  await openEditor(page, `post.php?post=${id}&action=edit`);
  return page.evaluate(collectInvalidBlocks);
}

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await logIn(page);
  const ids = await resolvePostIds(page);

  let totalBlocks = 0;
  let invalidBlocks = 0;
  for (const id of ids) {
    const result = await checkPost(page, id);
    totalBlocks += result.count;
    invalidBlocks += result.invalid.length;
    if (result.invalid.length) console.log(`FAIL #${id}\n  ` + result.invalid.join('\n  '));
  }
  console.log(`Записей: ${ids.length}, блоков: ${totalBlocks}, недействительных: ${invalidBlocks}`);
  await browser.close();
  process.exit(invalidBlocks ? 1 : 0);
}

main();
