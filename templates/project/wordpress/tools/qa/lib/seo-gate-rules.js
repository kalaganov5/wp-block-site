/*
 * Правила SEO-гейта: какие находки обхода останавливают CI. Чистые функции над результатом обхода
 * (site и pages из lib/seo-site.js и lib/seo-crawler.js) — без запросов и файлов.
 * Тесты — wordpress/tools/tests/seo-gate.test.js.
 *
 * Блокирует только то, что ломает индексацию или страницу в выдаче. Остальное (длина описания, мало текста,
 * сиротские страницы…) остаётся предупреждениями сводки: решение по ним принимает человек.
 *
 * Отдельно не запускается — подключается из wordpress/tools/qa/seo-gate.js.
 */

const isNoindex = (page) => /noindex/i.test(`${page.robots || ''} ${page.xRobots || ''}`);
// Страница HTML с ответом 200 без редиректа: у адреса с редиректом title и canonical принадлежат конечной.
const isHtmlPage = (page) => page.status === 200 && page.title !== undefined && !page.chain.length;

// Открыт ли сайт поисковикам: закрытый WordPress (blog_public = 0) ставит noindex всем страницам, начиная с главной.
function isPublicSite(pages, base) {
  const home = pages.find((page) => page.url === base + '/');
  return Boolean(home) && !isNoindex(home);
}

function hostOf(url, fallbackBase) {
  try {
    return new URL(url, fallbackBase).host;
  } catch {
    return '';
  }
}

function siteProblems(site) {
  const problems = [];
  if (site.robots.status !== 200) problems.push(`robots.txt отвечает ${site.robots.status}`);
  const sitemap = site.sitemaps.find((entry) => entry.status === 200);
  if (!sitemap) problems.push('карта сайта не отвечает 200: ' + site.sitemaps[0].url);
  return problems;
}

// Ответы сервера: адрес из карты сайта обязан отвечать 200; ссылка внутри сайта не должна вести на ошибку.
function statusProblems(pages) {
  const problems = [];
  for (const page of pages) {
    if (page.inSitemap && page.status !== 200) {
      problems.push(`${page.url}: страница из карты сайта отвечает ${page.status}`);
    } else if (page.status >= 400 && page.inlinks > 0) {
      problems.push(`${page.url}: битая внутренняя ссылка (ответ ${page.status})`);
    }
  }
  return problems;
}

function contentProblems(page) {
  const problems = [];
  if (!page.title) problems.push('нет title');
  if (!page.description) problems.push('нет meta description');
  if (page.h1.length !== 1) problems.push(`H1: ${page.h1.length}, нужен ровно один`);
  if (page.imagesNoAlt.length) problems.push(`картинка без alt: ${page.imagesNoAlt[0]}`);
  if (page.jsonld.includes('INVALID_JSON')) problems.push('JSON-LD не разбирается как JSON');
  return problems;
}

// Только для открытого сайта: на закрытом noindex стоит везде, а canonical поисковику не показывается.
function indexingProblems(page, base) {
  const problems = [];
  if (page.inSitemap && isNoindex(page)) problems.push('noindex у страницы из карты сайта');
  if (!page.canonical) problems.push('нет canonical');
  else if (hostOf(page.canonical, page.final) !== hostOf(base)) {
    problems.push(`canonical на другом хосте: ${page.canonical}`);
  }
  return problems;
}

function duplicateTitleProblems(pages) {
  const urlsByTitle = new Map();
  for (const page of pages) {
    if (page.title) urlsByTitle.set(page.title, [...(urlsByTitle.get(page.title) || []), page.url]);
  }
  return [...urlsByTitle.entries()]
    .filter(([, urls]) => urls.length > 1)
    .map(([title, urls]) => `одинаковый title «${title}»: ${urls.join(', ')}`);
}

/**
 * Блокирующие проблемы. data: { base, site, pages, isPublic }.
 * Возвращает массив строк; пустой массив — гейт пройден.
 */
function findBlockingProblems(data) {
  const { base, site, pages, isPublic } = data;
  // На открытом сайте страницы с noindex вне карты — намеренно закрытые (непереведённые копии): их поля
  // поисковик не увидит. Страница с noindex из карты сайта остаётся в проверке — это и есть ошибка.
  const checked = pages
    .filter(isHtmlPage)
    .filter((page) => !isPublic || page.inSitemap || !isNoindex(page));
  const problems = [...siteProblems(site), ...statusProblems(pages)];
  for (const page of checked) {
    const found = [...contentProblems(page), ...(isPublic ? indexingProblems(page, base) : [])];
    problems.push(...found.map((message) => `${page.url}: ${message}`));
  }
  return [...problems, ...duplicateTitleProblems(checked)];
}

module.exports = { findBlockingProblems, isPublicSite };
