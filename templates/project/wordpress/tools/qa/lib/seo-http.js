/*
 * HTTP-запросы SEO-обхода: один запрос без автоматических редиректов и проход цепочки редиректов вручную.
 * Редиректы не отдаём на откуп fetch: в отчёте нужна вся цепочка с кодами ответа каждого шага.
 *
 * Отдельно не запускается — подключается из wordpress/tools/qa/seo-crawl.js и модулей lib/seo-*.js.
 */
const USER_AGENT = 'Mozilla/5.0 (compatible; site-seo-audit/1.0)';
const MAX_REDIRECTS = 10;

// Ответ всегда одной формы: при сетевой ошибке status = 0 и текст ошибки в error — обход не прерывается.
async function get(url, options = {}) {
  const startedAt = Date.now();
  try {
    const response = await fetch(url, {
      redirect: 'manual',
      headers: { 'user-agent': USER_AGENT, 'accept-encoding': 'gzip, br' },
      signal: AbortSignal.timeout(30000),
      ...options,
    });
    const body = options.method === 'HEAD' ? '' : await response.text();
    return {
      status: response.status,
      headers: Object.fromEntries(response.headers),
      body,
      ms: Date.now() - startedAt,
      location: response.headers.get('location'),
    };
  } catch (error) {
    return {
      status: 0,
      error: String(error.message || error),
      headers: {},
      body: '',
      ms: Date.now() - startedAt,
    };
  }
}

/** Цепочка редиректов до конечного ответа (не больше 10 шагов). */
async function follow(url) {
  const chain = [];
  let current = url;
  for (let i = 0; i < MAX_REDIRECTS; i++) {
    const response = await get(current);
    chain.push({ url: current, status: response.status, ms: response.ms });
    const isRedirect = response.status >= 300 && response.status < 400 && response.location;
    if (!isRedirect) return { final: current, response, chain };
    current = new URL(response.location, current).href;
  }
  const loopResponse = { status: 0, error: 'redirect loop', headers: {}, body: '' };
  return { final: current, response: loopResponse, chain };
}

// Адрес без #якоря: якорь не делает страницу другой.
function normalizeUrl(url) {
  const parsed = new URL(url);
  parsed.hash = '';
  return parsed.href;
}

// Внутренняя ли ссылка. Неразбираемый адрес считаем внешним.
function isInternalUrl(url, host) {
  try {
    return new URL(url).host === host;
  } catch {
    return false;
  }
}

module.exports = { get, follow, normalizeUrl, isInternalUrl };
