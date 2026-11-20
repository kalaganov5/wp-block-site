// Тесты правил SEO-гейта (wordpress/tools/qa/lib/seo-gate-rules.js): что останавливает CI.
// Запуск: node --test wordpress/tools/tests/*.test.js. Названия тестов — требования на языке команды.
const test = require('node:test');
const assert = require('node:assert/strict');
const { findBlockingProblems, isPublicSite } = require('../qa/lib/seo-gate-rules');

const BASE = 'http://localhost:8080';

// Страница, как её записывает обход (lib/seo-crawler.js); overrides меняют только проверяемое поле.
const page = (pathname, overrides = {}) => ({
  url: BASE + pathname,
  final: BASE + pathname,
  status: 200,
  chain: [],
  inSitemap: true,
  inlinks: 1,
  title: 'Заголовок ' + pathname,
  description: 'Описание страницы',
  robots: 'follow, index',
  xRobots: '',
  canonical: BASE + pathname,
  h1: ['Заголовок'],
  imagesNoAlt: [],
  jsonld: ['WebPage'],
  ...overrides,
});

const site = (overrides = {}) => ({
  robots: { status: 200 },
  sitemaps: [{ url: BASE + '/sitemap_index.xml', status: 200 }],
  ...overrides,
});

const problems = (pages, options = {}) =>
  findBlockingProblems({
    base: BASE,
    site: site(options.site),
    pages,
    isPublic: options.isPublic ?? true,
  });

test('сайт без нарушений проходит гейт', () => {
  assert.deepEqual(problems([page('/'), page('/about/')]), []);
});

test('страница из карты сайта с ответом не 200 блокирует', () => {
  const found = problems([page('/'), page('/gone/', { status: 404 })]);
  assert.match(found[0], /из карты сайта отвечает 404/);
});

test('пустой title и отсутствие description блокируют', () => {
  assert.match(problems([page('/', { title: '' })])[0], /нет title/);
  assert.match(problems([page('/', { description: '' })])[0], /нет meta description/);
});

test('ни одного H1 или больше одного H1 блокирует', () => {
  assert.match(problems([page('/', { h1: [] })])[0], /H1: 0/);
  assert.match(problems([page('/', { h1: ['Один', 'Два'] })])[0], /H1: 2/);
});

test('внутренняя ссылка на страницу с ошибкой 4xx или 5xx блокирует', () => {
  const broken = page('/broken/', { status: 500, inSitemap: false, inlinks: 2 });
  assert.match(problems([page('/'), broken])[0], /битая внутренняя ссылка/);
});

test('одинаковый title у двух индексируемых страниц блокирует', () => {
  const found = problems([page('/a/', { title: 'Один' }), page('/b/', { title: 'Один' })]);
  assert.match(found[0], /одинаковый title «Один»/);
});

test('картинка без атрибута alt блокирует', () => {
  assert.match(problems([page('/', { imagesNoAlt: ['/a.jpg'] })])[0], /картинка без alt/);
});

test('JSON-LD, который не разбирается как JSON, блокирует', () => {
  assert.match(problems([page('/', { jsonld: ['INVALID_JSON'] })])[0], /JSON-LD/);
});

test('robots.txt или карта сайта с ответом не 200 блокируют', () => {
  assert.match(problems([page('/')], { site: { robots: { status: 404 } } })[0], /robots\.txt/);
  const noSitemap = { sitemaps: [{ url: BASE + '/sitemap_index.xml', status: 404 }] };
  assert.match(problems([page('/')], { site: noSitemap })[0], /карта сайта/);
});

test('на открытом сайте noindex у страницы из карты сайта блокирует', () => {
  assert.match(problems([page('/', { robots: 'noindex, follow' })])[0], /noindex/);
});

test('на открытом сайте canonical обязателен и ведёт на тот же хост', () => {
  assert.match(problems([page('/', { canonical: '' })])[0], /нет canonical/);
  const foreign = page('/', { canonical: 'https://other.example/' });
  assert.match(problems([foreign])[0], /canonical на другом хосте/);
});

test('на закрытом сайте noindex и canonical не проверяются, остальное — проверяется', () => {
  const closed = page('/', { robots: 'noindex, nofollow', canonical: '' });
  assert.deepEqual(problems([closed], { isPublic: false }), []);
  const noTitle = { ...closed, title: '' };
  assert.match(problems([noTitle], { isPublic: false })[0], /нет title/);
});

test('закрытая от поиска страница вне карты сайта (noindex) на открытом сайте гейт не останавливает', () => {
  const hidden = page('/draft-copy/', {
    robots: 'noindex',
    inSitemap: false,
    title: 'Заголовок /',
  });
  assert.deepEqual(problems([page('/'), hidden]), []);
});

test('адрес с редиректом не считается отдельной страницей', () => {
  const redirected = page('/old/', {
    chain: ['301 /old/', '200 /new/'],
    title: '',
    inSitemap: false,
  });
  assert.deepEqual(problems([page('/'), redirected]), []);
});

test('сайт считается открытым, если главная без noindex', () => {
  assert.equal(isPublicSite([page('/')], BASE), true);
  assert.equal(isPublicSite([page('/', { robots: 'noindex, nofollow' })], BASE), false);
  assert.equal(isPublicSite([page('/', { xRobots: 'noindex' })], BASE), false);
});
