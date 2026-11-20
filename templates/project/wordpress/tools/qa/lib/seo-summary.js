/*
 * Сводка проблем SEO-обхода в Markdown (summary.md): шапка с итогами и разделы по группам проверок.
 * Раздел печатается всегда, даже пустой: по числу в заголовке видно, что проверка выполнена.
 * Тексты заголовков и порядок разделов менять нельзя — сводку читают люди и сравнивают между запусками.
 *
 * Отдельно не запускается — подключается из wordpress/tools/qa/seo-crawl.js.
 */
const { normalizeUrl } = require('./seo-http');

const MAX_ITEMS = 60;
const formatUrl = (page) => page.url;

// Раздел сводки: заголовок с числом находок и не больше 60 строк, остаток — одной строкой «… ещё N».
function section(title, items, format) {
  const lines = [`\n## ${title} — ${items.length}\n`];
  for (const item of items.slice(0, MAX_ITEMS)) lines.push('- ' + format(item));
  if (items.length > MAX_ITEMS) lines.push(`- … ещё ${items.length - MAX_ITEMS}`);
  return lines;
}

// Страницы с одинаковым значением поля (title, description): пары [значение, список адресов].
function findDuplicates(pages, field) {
  // Обычный объект, а не Map: порядок перебора его ключей определяет порядок строк в сводке.
  const urlsByValue = {};
  for (const page of pages) {
    const value = page[field];
    if (value) (urlsByValue[value] = urlsByValue[value] || []).push(page.url);
  }
  return Object.entries(urlsByValue).filter(([, urls]) => urls.length > 1);
}

const jsonLdTypes = (page) => page.jsonld.join(' ');
const isNoindex = (robots) => /noindex/i.test(robots);

// Глубина страницы без языкового префикса (/en/…): 0 — главная.
function pageDepth(page) {
  const parts = new URL(page.final).pathname.split('/').filter(Boolean);
  return parts.length - (/^[a-z]{2}$/.test(parts[0] || '') ? 1 : 0);
}

const hasForeignCanonical = (page) =>
  !page.canonical ||
  normalizeUrl(new URL(page.canonical, page.final).href) !== normalizeUrl(page.final);

// Автор-человек: не «admin», не заглушка «?» и не общий узел #author без имени.
const hasHumanAuthor = (page) =>
  page.authors.some((author) => !/^(admin|\?)$/i.test(author) && !/#author$/.test(author));

function headerLines(report) {
  const { base, site, pages, responding, indexable } = report;
  const mirrors = site.mirrors
    .map((mirror) => mirror.from + ' → ' + mirror.chain.join(' → '))
    .join('; ');
  const sitemaps = site.sitemaps.map((sitemap) => sitemap.status + ' ' + sitemap.url).join(', ');
  const llmsLinks = site.llms.status === 200 ? ', ссылок: ' + site.llms.links : '';
  const blockedBots =
    site.aiBots
      .filter((entry) => entry.blocked)
      .map((entry) => entry.bot)
      .join(', ') || 'нет';
  return [
    `# SEO-обход ${base} — ${site.date}\n`,
    `Страниц обойдено: ${pages.length}, в карте сайта: ${site.sitemapCount}, отвечают 200: ${responding.length}, индексируемых: ${indexable.length}.`,
    `Зеркала: ${mirrors}`,
    `robots.txt: ${site.robots.status}; несуществующая страница: ${site.notFound.status}; карты: ${sitemaps}`,
    `GEO: llms.txt — ${site.llms.status}${llmsLinks}; боты ИИ, закрытые в robots.txt: ${blockedBots}.`,
  ];
}

function statusSections({ pages, indexable }) {
  const formatNot200 = (page) =>
    `${page.status} ${page.url}${page.chain.length ? ' (' + page.chain.join(' → ') + ')' : ''}${page.error ? ' ' + page.error : ''}`;
  return [
    section(
      'Не 200 (ошибки и редиректы внутри сайта)',
      pages.filter((page) => page.status !== 200),
      formatNot200,
    ),
    section(
      'В карте сайта, но не 200 или noindex',
      pages.filter(
        (page) => page.inSitemap && (page.status !== 200 || isNoindex(page.robots || '')),
      ),
      (page) => `${page.status} ${page.url} ${page.robots || ''}`,
    ),
    section(
      'Ссылки внутри сайта ведут на редирект',
      pages.filter((page) => page.status === 200 && page.chain.length),
      (page) => `${page.url} → ${page.final}`,
    ),
    section(
      'Индексируемые, но нет в карте сайта',
      indexable.filter((page) => !page.inSitemap),
      formatUrl,
    ),
  ];
}

function titleSections({ indexable }) {
  const isBadDescriptionLength = (page) =>
    page.description && (page.description.length < 70 || page.description.length > 170);
  return [
    section(
      'Без title',
      indexable.filter((page) => !page.title),
      formatUrl,
    ),
    section(
      'Title длиннее 60 символов',
      indexable.filter((page) => page.title.length > 60),
      (page) => `${page.title.length}: ${page.title} — ${page.url}`,
    ),
    section(
      'Без description',
      indexable.filter((page) => !page.description),
      formatUrl,
    ),
    section(
      'Description короче 70 или длиннее 170',
      indexable.filter(isBadDescriptionLength),
      (page) => `${page.description.length}: ${page.url}`,
    ),
    section(
      'Одинаковые title',
      findDuplicates(indexable, 'title'),
      ([value, urls]) => `«${value}»: ${urls.join(', ')}`,
    ),
    section(
      'Одинаковые description',
      findDuplicates(indexable, 'description'),
      ([value, urls]) => `«${value.slice(0, 80)}…»: ${urls.join(', ')}`,
    ),
  ];
}

function markupSections({ responding, indexable }) {
  return [
    section(
      'H1 не один',
      indexable.filter((page) => page.h1.length !== 1),
      (page) => `${page.h1.length}: ${page.url} ${page.h1.join(' | ').slice(0, 120)}`,
    ),
    section(
      'Canonical отсутствует или указывает на другую страницу',
      indexable.filter(hasForeignCanonical),
      (page) => `${page.url} → ${page.canonical || '—'}`,
    ),
    section(
      'Нет hreflang',
      indexable.filter((page) => !page.hreflang.length),
      formatUrl,
    ),
    section(
      'Нет og:image',
      indexable.filter((page) => !page.og.image),
      formatUrl,
    ),
    section(
      'Картинки без alt',
      responding.filter((page) => page.imagesNoAlt.length),
      (page) => `${page.imagesNoAlt.length}: ${page.url}`,
    ),
    section(
      'Мало текста (< 250 слов) на индексируемых',
      indexable.filter((page) => page.words < 250),
      (page) => `${page.words}: ${page.url}`,
    ),
    section(
      'Без микроразметки JSON-LD',
      indexable.filter((page) => !page.jsonld.length),
      formatUrl,
    ),
  ];
}

function geoSections({ indexable }) {
  return [
    section(
      'GEO: статьи и кейсы без автора-человека в разметке',
      indexable.filter(
        (page) => /Article|BlogPosting/.test(jsonLdTypes(page)) && !hasHumanAuthor(page),
      ),
      (page) => `${page.url} (${page.authors.join(', ') || 'нет автора'})`,
    ),
    section(
      'GEO: внутренние страницы без BreadcrumbList',
      indexable.filter((page) => pageDepth(page) > 0 && !/BreadcrumbList/.test(jsonLdTypes(page))),
      formatUrl,
    ),
    section(
      'GEO: блок «Вопросы и ответы» без FAQPage',
      indexable.filter((page) => page.faqBlocks && !/FAQPage/.test(jsonLdTypes(page))),
      formatUrl,
    ),
  ];
}

const isLegacyOk = (entry) => entry.chain[0] === 301 && entry.status === 200;

function technicalSections({ base, responding, indexable, legacy }) {
  return [
    section(
      'Сиротские страницы (нет входящих ссылок, только из карты)',
      indexable.filter((page) => page.inlinks === 0 && page.url !== base + '/'),
      formatUrl,
    ),
    section(
      'Медленный ответ сервера (> 800 мс)',
      responding.filter((page) => page.ms > 800),
      (page) => `${page.ms} мс: ${page.url}`,
    ),
    section(
      'Без сжатия gzip/br',
      responding.filter((page) => !page.headers.encoding),
      formatUrl,
    ),
    section(
      'Старые адреса: не 301 на рабочую страницу',
      legacy.filter((entry) => !isLegacyOk(entry)),
      (entry) => `${entry.from}: ${entry.chain.join(' → ')} → ${entry.final} (${entry.status})`,
    ),
  ];
}

/**
 * Строки сводки. data: { base, site, pages, legacy }.
 * Возвращает массив строк: первые пять — шапка, дальше разделы (заголовок раздела — один элемент).
 */
function buildSummaryLines(data) {
  const responding = data.pages.filter((page) => page.status === 200 && page.title !== undefined);
  // Адрес с редиректом (/pricing/ → /en/pricing/) — не отдельная страница: её title и canonical принадлежат конечной.
  const indexable = responding.filter(
    (page) => !page.chain.length && !isNoindex(page.robots + ' ' + page.xRobots),
  );
  const report = { ...data, responding, indexable };
  const sections = [
    ...statusSections(report),
    ...titleSections(report),
    ...markupSections(report),
    ...geoSections(report),
    ...technicalSections(report),
  ];
  const legacyOkCount = data.legacy.filter(isLegacyOk).length;
  return [
    ...headerLines(report),
    ...sections.flat(),
    `\nСтарые адреса проверено: ${data.legacy.length}, правильно: ${legacyOkCount}.`,
  ];
}

module.exports = { buildSummaryLines };
