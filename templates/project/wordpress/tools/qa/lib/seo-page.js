/*
 * Разбор HTML одной страницы для SEO-обхода: title, description, robots, canonical, hreflang, Open Graph,
 * заголовки, картинки, микроразметка JSON-LD, ссылки. Порядок полей результата — это порядок полей в pages.json.
 *
 * Отдельно не запускается — подключается из lib/seo-crawler.js.
 * Нужен пакет node-html-parser (npm i node-html-parser@6).
 */
const { parse } = require('node-html-parser');
const { isInternalUrl } = require('./seo-http');

const collapseSpaces = (text) => text.replace(/\s+/g, ' ').trim();

// Типы одного блока JSON-LD; попутно дописывает авторов в общий список.
// rawText: внутри <script> сущности (&quot;) не раскодируются — так JSON видят браузер и поисковики.
function readJsonLdScript(script, authors) {
  try {
    const data = JSON.parse(script.rawText);
    const nodes = data['@graph'] || [data];
    nodes.forEach((node) => {
      if (!node.author) return;
      [].concat(node.author).forEach((author) => authors.push(author.name || author['@id'] || '?'));
    });
    return nodes.map((node) => node['@type']).flat();
  } catch {
    return ['INVALID_JSON'];
  }
}

function readJsonLd(root) {
  const authors = [];
  const types = root
    .querySelectorAll('script[type="application/ld+json"]')
    .map((script) => readJsonLdScript(script, authors))
    .flat();
  return { types, authors };
}

function readLinks(root) {
  return root.querySelectorAll('a[href]').map((anchor) => ({
    href: anchor.getAttribute('href'),
    text: collapseSpaces(anchor.text).slice(0, 80),
    rel: anchor.getAttribute('rel') || '',
  }));
}

const metaContent = (root, selector) =>
  root.querySelector(selector)?.getAttribute('content')?.trim() || '';

function headFields(root, headers) {
  const meta = (selector) => metaContent(root, selector);
  return {
    lang: root.querySelector('html')?.getAttribute('lang') || '',
    title: root.querySelector('title')?.text.trim() || '',
    description: meta('meta[name="description"]'),
    robots: meta('meta[name="robots"]'),
    xRobots: headers['x-robots-tag'] || '',
    canonical: root.querySelector('link[rel="canonical"]')?.getAttribute('href') || '',
    hreflang: root.querySelectorAll('link[rel="alternate"][hreflang]').map((link) => ({
      lang: link.getAttribute('hreflang'),
      href: link.getAttribute('href'),
    })),
    og: {
      title: meta('meta[property="og:title"]'),
      description: meta('meta[property="og:description"]'),
      image: meta('meta[property="og:image"]'),
      url: meta('meta[property="og:url"]'),
      type: meta('meta[property="og:type"]'),
    },
    twitter: meta('meta[name="twitter:card"]'),
  };
}

function headingFields(root) {
  const headingTexts = (tag) =>
    root.querySelectorAll(tag).map((heading) => collapseSpaces(heading.text));
  return {
    h1: headingTexts('h1'),
    h2: headingTexts('h2').slice(0, 30),
    h3count: root.querySelectorAll('h3').length,
  };
}

function imageFields(root) {
  const images = root.querySelectorAll('img');
  return {
    images: images.length,
    // alt="" — допустимо (декоративная картинка); ошибка — когда атрибута нет совсем.
    imagesNoAlt: images
      .filter((image) => image.getAttribute('alt') === undefined)
      .map((image) => image.getAttribute('src'))
      .slice(0, 20),
    imagesNoSize: images.filter(
      (image) => !image.getAttribute('width') || !image.getAttribute('height'),
    ).length,
  };
}

function linkFields(links, host) {
  const isInternal = (href) => isInternalUrl(href, host);
  return {
    linksInternal: links.filter(
      (link) => link.href && (link.href.startsWith('/') || isInternal(link.href)),
    ).length,
    linksExternal: links.filter(
      (link) => /^https?:/.test(link.href || '') && !isInternal(link.href),
    ).length,
    emptyAnchors: links.filter((link) => !link.text && !/aria-label/.test(link.rel)).length,
    // Служебное поле: по нему обход находит новые страницы и удаляет его перед записью отчёта.
    _links: links.map((link) => link.href),
  };
}

function analysePage(html, headers, host) {
  const root = parse(html, { comment: false });
  const mainText = root.querySelector('main') || root.querySelector('body');
  const text = (mainText ? collapseSpaces(mainText.text) : '') || '';
  const jsonLd = readJsonLd(root);
  return {
    ...headFields(root, headers),
    authors: jsonLd.authors,
    faqBlocks: root.querySelectorAll('.acme-faq details').length,
    viewport: metaContent(root, 'meta[name="viewport"]'),
    ...headingFields(root),
    ...imageFields(root),
    words: text ? text.split(' ').length : 0,
    jsonld: jsonLd.types,
    ...linkFields(readLinks(root), host),
    htmlBytes: Buffer.byteLength(html),
  };
}

module.exports = { analysePage };
