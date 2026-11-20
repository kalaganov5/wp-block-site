/*
 * Обход страниц для SEO-аудита: сначала главная и адреса из карты сайта, затем страницы, найденные по ссылкам.
 * Для каждой страницы — цепочка редиректов, заголовки ответа и разбор HTML (lib/seo-page.js).
 * Порядок полей страницы — это порядок полей в pages.json.
 *
 * Отдельно не запускается — подключается из wordpress/tools/qa/seo-crawl.js.
 */
const { follow, normalizeUrl, isInternalUrl } = require('./seo-http');
const { analysePage } = require('./seo-page');

// Файлы, а не страницы: по ссылкам на них не ходим.
const SKIPPED_EXTENSIONS =
  /\.(jpe?g|png|gif|webp|avif|svg|pdf|zip|mp4|webm|css|js|xml|txt|ico|woff2?)$/i;
// Служебные адреса WordPress и адреса с параметрами — не страницы сайта.
const SKIPPED_PATHS = /\/wp-(admin|json|login)|\?/;

function isCrawlable(url, host) {
  if (!isInternalUrl(url, host)) return false;
  return !SKIPPED_EXTENSIONS.test(new URL(url).pathname) && !SKIPPED_PATHS.test(url);
}

function buildPageRecord(url, result, isInSitemap) {
  const { final, response, chain } = result;
  return {
    url,
    final,
    status: response.status,
    chain: chain.length > 1 ? chain.map((step) => step.status + ' ' + step.url) : [],
    ms: chain[0].ms,
    inSitemap: isInSitemap,
    error: response.error || '',
    headers: {
      type: response.headers['content-type'] || '',
      cache: response.headers['cache-control'] || '',
      encoding: response.headers['content-encoding'] || '',
      hsts: response.headers['strict-transport-security'] || '',
      server: response.headers.server || '',
    },
  };
}

// Абсолютный внутренний адрес ссылки или null (почта, телефон, якорь, внешний сайт, неразбираемый адрес).
function resolveInternalLink(href, pageUrl, host) {
  if (!href || /^(mailto|tel|javascript|#)/.test(href)) return null;
  let absolute;
  try {
    absolute = normalizeUrl(new URL(href, pageUrl).href);
  } catch {
    return null;
  }
  return isInternalUrl(absolute, host) ? absolute : null;
}

// Ссылки страницы: запоминаем, кто на кого ссылается (для поиска сиротских страниц), новые адреса — в очередь.
function registerLinks(page, state, host) {
  for (const href of page._links) {
    const target = resolveInternalLink(href, page.final, host);
    if (!target) continue;
    if (!state.linkedFrom.has(target)) state.linkedFrom.set(target, new Set());
    state.linkedFrom.get(target).add(page.url);
    if (!state.done.has(target)) state.queue.push(target);
  }
}

async function crawlPage(url, state, config) {
  const result = await follow(url);
  const { response } = result;
  const page = buildPageRecord(url, result, state.inSitemap.has(url));
  if (response.status === 200 && /html/.test(page.headers.type)) {
    Object.assign(page, analysePage(response.body, response.headers, config.host));
    registerLinks(page, state, config.host);
    delete page._links;
  }
  state.done.set(url, page);
  // Строка прогресса перезаписывается на месте (\r); padEnd стирает хвост более длинного прошлого адреса.
  process.stdout.write(`\r${state.done.size} ${response.status} ${url.slice(0, 90).padEnd(90)}`);
}

// Обход: сначала карта сайта, затем ссылки со страниц. Не больше config.max страниц.
async function crawlPages(config, sitemapUrls) {
  const state = {
    queue: [config.base + '/', ...sitemapUrls],
    inSitemap: new Set(sitemapUrls.map(normalizeUrl)),
    done: new Map(),
    linkedFrom: new Map(),
  };
  while (state.queue.length && state.done.size < config.max) {
    const url = normalizeUrl(state.queue.shift());
    if (state.done.has(url) || !isCrawlable(url, config.host)) continue;
    await crawlPage(url, state, config);
  }
  console.log();
  const pages = [...state.done.values()];
  for (const page of pages) {
    page.inlinks = state.linkedFrom.has(page.url) ? state.linkedFrom.get(page.url).size : 0;
  }
  return pages;
}

module.exports = { crawlPages };
