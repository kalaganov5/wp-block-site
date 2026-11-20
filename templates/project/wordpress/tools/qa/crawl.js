/*
 * Обходит все страницы сайта (по ссылкам из меню каталога и подвала) на заданной ширине экрана:
 * статус ответа, ошибки JS, горизонтальная прокрутка.
 *
 *   npm i playwright && npx playwright install chromium
 *   QA_BASE=http://localhost:8080 node wordpress/tools/qa/crawl.js [ширина=360]
 *
 * QA_START — стартовые адреса через запятую (по умолчанию «/,/en/»).
 * Печатает число страниц и проблем, затем до 30 проблемных страниц — по строке JSON на каждую.
 */
const { chromium } = require('playwright');

const BASE = process.env.QA_BASE || 'http://localhost:8080';

// Выполняется до скриптов страницы: согласие на cookie уже сохранено, поэтому окно согласия
// не открывается на каждой странице обхода.
function saveCookieConsent() {
  // Запись в том же виде, в каком её сохраняет сайт; «v» — версия формата согласия.
  const consent = Object.fromEntries([
    ['v', 1],
    ['necessary', true],
    ['analytics', false],
    ['marketing', false],
    ['ts', Date.now()],
  ]);
  try {
    localStorage.setItem('acme-consent', JSON.stringify(consent));
  } catch {
    // localStorage недоступен — обход продолжается без сохранённого согласия.
  }
}

// Выполняется внутри страницы, поэтому все помощники объявлены внутри функции.
// Возвращает ширину документа, внутренние ссылки и до трёх элементов, которые вылезают за экран.
function collectPageInfo(viewportWidth) {
  const rightLimit = viewportWidth + 1;
  const links = [...document.querySelectorAll('a[href^="' + location.origin + '"]')]
    .map((anchor) => new URL(anchor.href).pathname)
    .filter((pathname) => !/wp-content|wp-admin|\.(pdf|png|jpg)$/.test(pathname));
  // Элемент шире экрана — не ошибка, если предок обрезает или прокручивает его сам (таблицы, карусели).
  const isClippedByAncestor = (element) => {
    let ancestor = element.parentElement;
    while (ancestor && ancestor !== document.body) {
      const hasOwnScroll = /auto|scroll|hidden|clip/.test(getComputedStyle(ancestor).overflowX);
      if (hasOwnScroll && ancestor.getBoundingClientRect().right <= rightLimit) return true;
      ancestor = ancestor.parentElement;
    }
    return false;
  };
  const overflowing = [];
  document.querySelectorAll('body *').forEach((element) => {
    const rect = element.getBoundingClientRect();
    // Невидимые и закреплённые (fixed) элементы ширину документа не увеличивают.
    if (!rect.width || getComputedStyle(element).position === 'fixed') return;
    if (rect.right <= rightLimit || isClippedByAncestor(element)) return;
    const firstClass = (element.className || '').toString().split(' ')[0];
    overflowing.push(element.tagName + '.' + firstClass + ' r=' + Math.round(rect.right));
  });
  return { sw: document.documentElement.scrollWidth, links, over: overflowing.slice(0, 3) };
}

// Строка отчёта. Ключи короткие (u, sw, errs, over) — это сложившийся формат вывода, его не меняем.
const problemEntry = (url, check) =>
  Object.fromEntries([
    ['u', url],
    ['status', check.status],
    ['sw', check.info.sw],
    ['errs', check.jsErrors.slice(0, 2)],
    ['over', check.info.over],
  ]);

async function checkPage(page, url, viewportWidth) {
  const response = await page.goto(BASE + url, { waitUntil: 'load' });
  const info = await page.evaluate(collectPageInfo, viewportWidth);
  return { status: response.status(), info };
}

async function crawl(page, viewportWidth) {
  const jsErrors = [];
  page.on('pageerror', (error) => jsErrors.push(error.message));
  const seen = new Set();
  const queue = (process.env.QA_START || '/,/en/').split(',');
  const problems = [];
  while (queue.length) {
    const url = queue.shift();
    if (seen.has(url)) continue;
    seen.add(url);
    // Ошибки JS копятся в одном массиве: очищаем его перед каждой страницей.
    jsErrors.length = 0;
    const check = await checkPage(page, url, viewportWidth);
    const isBroken = check.status !== 200 || check.info.sw > viewportWidth || jsErrors.length;
    if (isBroken) problems.push(problemEntry(url, { ...check, jsErrors }));
    check.info.links.filter((link) => !seen.has(link)).forEach((link) => queue.push(link));
  }
  return { seen, problems };
}

async function main() {
  const viewportWidth = +(process.argv[2] || 360);
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: viewportWidth, height: 800 } });
  await context.addInitScript(saveCookieConsent);
  const page = await context.newPage();
  const { seen, problems } = await crawl(page, viewportWidth);
  console.log('pages:', seen.size, 'problems:', problems.length);
  problems.slice(0, 30).forEach((problem) => console.log(JSON.stringify(problem)));
  await browser.close();
}

main();
