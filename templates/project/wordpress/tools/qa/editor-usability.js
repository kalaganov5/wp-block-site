/*
 * Проверяет, что в редакторе WordPress можно писать: заголовок и текст правятся, ничто не закрывает содержимое
 * и не забирает клики — ни в обычном режиме, ни с показанным шаблоном (шапка, крошки, подвал вокруг текста).
 * Нужна после изменения шаблонов, блоков темы, editor.css и site.js.
 *
 *   npm i playwright && npx playwright install chromium
 *   QA_BASE=http://localhost:8080 QA_USER=admin QA_PASS=admin node wordpress/tools/qa/editor-usability.js
 *
 * Что делает: создаёт черновик записи (в конце удаляет его), печатает текст в существующую страницу и услугу
 * (не сохраняя), включает показ шаблона и повторяет. Пользователю QA_USER выключает приветственные окна редактора.
 * Код выхода: 0 — все проверки прошли, 1 — есть FAIL.
 */
/* global wp -- объект редактора WordPress: доступен в функциях, которые Playwright выполняет внутри страницы */
const { chromium } = require('playwright');

const { logIn, openEditor, rest } = require('./lib/editor');

const MARK = 'QA-usability-' + Date.now();
const CANVAS = 'iframe[name="editor-canvas"]';
const TITLE = '.editor-post-title__input, .wp-block-post-title[contenteditable="true"]';
const PARAGRAPH = 'p[data-type="core/paragraph"]';
// Клик Playwright сам проверяет, что элемент не закрыт другим: закрыт — ждёт и падает по этому таймауту.
const CLICK_TIMEOUT_MS = 8000;
const TEMPLATE_TIMEOUT_MS = 30000;
let failedCount = 0;

function check(isPassed, message) {
  if (!isPassed) failedCount++;
  console.log((isPassed ? 'PASS ' : 'FAIL ') + message);
}

// Выполняется внутри страницы редактора.
const getRenderingMode = () => wp.data.select('core/editor').getRenderingMode();
const setRenderingMode = (mode) => wp.data.dispatch('core/editor').setRenderingMode(mode);
const savePost = () => wp.data.dispatch('core/editor').savePost();
const getPostId = () => wp.data.select('core/editor').getCurrentPostId();

// Выполняется внутри страницы редактора: текст выбранного блока, если это абзац.
function selectedParagraphText() {
  const block = wp.data.select('core/block-editor').getSelectedBlock();
  return block && block.name === 'core/paragraph' ? String(block.attributes.content) : null;
}

// Выполняется внутри холста: видимые элементы, которые прилипают или закреплены (до пяти, «тег.класс»).
// Скрытые (display: none) не считаются: места на холсте они не занимают.
function stickyElements() {
  return [...document.querySelectorAll('body *')]
    .filter((element) => /^(fixed|sticky)$/.test(getComputedStyle(element).position))
    .filter((element) => element.getClientRects().length > 0)
    .slice(0, 5)
    .map(
      (element) => element.tagName.toLowerCase() + '.' + String(element.className).split(' ')[0],
    );
}

/** Кликает в первый абзац содержимого и печатает метку; true — текст попал в этот абзац. */
async function typeIntoFirstParagraph(page) {
  const paragraph = page.frameLocator(CANVAS).locator(PARAGRAPH).first();
  try {
    await paragraph.click({ timeout: CLICK_TIMEOUT_MS });
  } catch {
    // Абзаца нет или он закрыт другим элементом — проверка ниже зафиксирует провал.
    return false;
  }
  await page.keyboard.type(MARK);
  const text = await page.evaluate(selectedParagraphText);
  return Boolean(text && text.includes(MARK));
}

// Новая запись: обычный редактор, заголовок и абзац набираются и сохраняются.
async function checkNewPost(page) {
  await openEditor(page, 'post-new.php');
  check(
    (await page.evaluate(getRenderingMode)) === 'post-only',
    'новая запись: открыта без шаблона',
  );
  const canvas = page.frameLocator(CANVAS);
  await canvas.locator(TITLE).first().click({ timeout: CLICK_TIMEOUT_MS });
  await page.keyboard.type(MARK + ' title');
  await page.keyboard.press('Enter');
  await page.keyboard.type(MARK + ' text');
  await page.evaluate(savePost);
  const id = await page.evaluate(getPostId);
  const saved = await rest(page, { path: `/wp/v2/posts/${id}?context=edit` });
  check(
    Boolean(saved && saved.title.raw === MARK + ' title'),
    'новая запись: заголовок сохранён из поля «Заголовок»',
  );
  check(
    Boolean(saved && saved.content.raw.includes(MARK + ' text')),
    'новая запись: абзац сохранён',
  );
  return id;
}

// Существующая запись: клик в первый абзац попадает в него, текст печатается. Не сохраняем.
async function checkExistingPost(page, post) {
  await openEditor(page, `post.php?post=${post.id}&action=edit`);
  check(await typeIntoFirstParagraph(page), `${post.label} #${post.id}: текст печатается в абзац`);
}

// С показанным шаблоном: вокруг текста шапка, крошки, форма и подвал — писать должно быть так же можно.
async function checkWithTemplate(page, post) {
  await openEditor(page, `post.php?post=${post.id}&action=edit`);
  await page.evaluate(setRenderingMode, 'template-locked');
  const canvas = page.frameLocator(CANVAS);
  // Шапка и подвал — превью с сервера: ждём, пока они появятся на холсте.
  const hasTemplate = await canvas
    .locator('[data-type="acme/site-footer"] footer')
    .waitFor({ timeout: TEMPLATE_TIMEOUT_MS })
    .then(() => true)
    .catch(() => false);
  check(hasTemplate, `${post.label} #${post.id}: шаблон показан (шапка и подвал на холсте)`);
  check(
    await typeIntoFirstParagraph(page),
    `${post.label} #${post.id}: с шаблоном текст печатается`,
  );
  const sticky = await page.frame({ name: 'editor-canvas' }).evaluate(stickyElements);
  check(
    !sticky.length,
    'с шаблоном на холсте нет прилипающих и закреплённых элементов' +
      (sticky.length ? ': ' + sticky.join(', ') : ''),
  );
  // Режим запоминается в настройках пользователя: возвращаем обычный.
  await page.evaluate(setRenderingMode, 'post-only');
}

/** Первая запись типа, в тексте которой есть абзац: { id, label } или null. */
async function findPostWithParagraph(page, restBase, label) {
  const posts = await rest(page, {
    path: `/wp/v2/${restBase}?per_page=20&context=edit&_fields=id,content&lang=`,
  });
  const post = (posts || []).find((item) => item.content.raw.includes('<!-- wp:paragraph'));
  return post ? { id: post.id, label } : null;
}

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  // Уход со страницы с несохранённым текстом: соглашаемся, сохранять его не нужно.
  page.on('dialog', (dialog) => dialog.accept());
  await logIn(page);

  const draftId = await checkNewPost(page);
  const posts = [
    await findPostWithParagraph(page, 'pages', 'страница'),
    await findPostWithParagraph(page, 'acme_service', 'услуга'),
  ].filter(Boolean);
  check(posts.length === 2, 'есть страница и услуга с абзацем для проверки');
  for (const post of posts) {
    await checkExistingPost(page, post);
  }
  for (const post of [...posts, { id: draftId, label: 'запись' }]) {
    await checkWithTemplate(page, post);
  }

  await rest(page, { method: 'DELETE', path: `/wp/v2/posts/${draftId}?force=true` });
  await browser.close();
  process.exit(failedCount ? 1 : 0);
}

main();
