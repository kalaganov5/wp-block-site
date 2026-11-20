#!/usr/bin/env node
// Собирает контент сайта из исходных данных (project/content/site.js) в файлы для WordPress:
//   acme-core/content/content.json — страницы, услуги и статьи на всех языках (разметка блоков Gutenberg), меню, редиректы
//   acme-core/content/prices.php   — цены по умолчанию (метки {{price:ключ}})
//   acme/inc/strings-data.php      — подписи интерфейса темы на всех языках
// Запуск: node wordpress/tools/build-content.js [путь к project]
// Сборка останавливается с кодом 1, если SEO-поля записей нарушают правила (tools/lib/seo-rules.js):
// нет заголовка или описания, не та длина, повторы внутри языка, адрес не латиницей, H1 или картинка без alt в тексте.
// Результат детерминирован: CI пересобирает файлы и проверяет, что они закоммичены (git diff --exit-code).
// Если исходник — прототип из дизайн-инструмента (файлы window.X = {…}), загрузите их через vm.runInContext.
const fs = require('fs');
const path = require('path');
const blocks = require('./lib/blocks');
const { validateSeo } = require('./lib/seo-rules');
const { phpArrayFile } = require('./lib/php-files');
const { esc, acme, textParagraph, heading, list, group, buttons, details } = blocks;

const ROOT = path.resolve(__dirname, '..', '..');
const PROJECT = path.resolve(process.argv[2] || path.join(ROOT, 'project'));
const OUT_CORE = path.join(ROOT, 'wordpress', 'acme-core', 'content');
const OUT_THEME = path.join(ROOT, 'wordpress', 'acme', 'inc');

const SITE = require(path.join(PROJECT, 'content', 'site.js'));
const LANGS = SITE.languages.map((language) => language.slug);
// Первый язык — основной: его текст подставляется там, где перевода нет.
const MAIN = LANGS[0];

// Значение на языке lang, а если перевода нет — на основном языке.
const pick = (translations, lang) =>
  (translations && (translations[lang] ?? translations[MAIN])) || '';
// Блоки записи разделяются пустой строкой — так их сохраняет редактор.
const joinBlocks = (parts) => parts.filter(Boolean).join('\n\n');

// ---------- Секции страниц: одинаковая оболочка section.acme-section > div.acme-wrap ----------
const section = (inner, options = {}) =>
  group(group(inner, { className: blocks.cls('acme-wrap', options.wrap) }), {
    tagName: 'section',
    anchor: options.id,
    className: blocks.cls('acme-section', options.sunken && 'is-sunken'),
  });
// Заголовка H1 в тексте записи нет: его выводит тема из названия записи.
const hero = (data) =>
  section([
    textParagraph(data.lead, { className: 'acme-lead' }),
    buttons([{ text: data.cta, url: '#form', className: 'is-style-primary' }]),
  ]);
const textPage = (paragraphs) =>
  section(
    paragraphs.map((text) => textParagraph(text)),
    { wrap: 'acme-prose' },
  );
const faq = (title, items) =>
  section([heading(title), ...items.map(([question, answer]) => details(question, esc(answer)))], {
    sunken: true,
    wrap: 'acme-faq',
  });

// ---------- Страницы ----------
// Карточки услуг на главной. Метка {{id:post:…}} внутри атрибута блока — строка; импортёр подставит ID.
const serviceCards = (lang) =>
  SITE.services.map((service) => ({
    title: pick(service.title, lang),
    text: pick(service.lead, lang),
    post: `{{id:post:${service.key}}}`,
    url: '',
    urgent: false,
  }));

// Страница с первым экраном (главная): hero, карточки услуг, форма заявки.
const heroPageContent = (page, lang) =>
  [
    hero(page.hero[lang]),
    section(
      [
        heading(pick(page.cards, lang)),
        acme('cards', { items: serviceCards(lang), moreLabel: pick(SITE.strings, lang).readMore }),
      ],
      { sunken: true },
    ),
    acme('lead-form'),
  ].join('\n\n');

// Текстовая страница: заголовок, абзацы и, если задано, форма.
const textPageContent = (page, lang) =>
  joinBlocks([textPage(pick(page.text, lang)), page.form ? acme('lead-form') : '']);

function pageContent(page, lang) {
  if (page.hero) return heroPageContent(page, lang);
  if (page.text) return textPageContent(page, lang);
  return '';
}

// SEO-поля записи на языке: без подстановки основного языка — пропуск должен быть виден проверке.
// Исключение — непереведённая копия: она закрыта от поиска и повторяет поля основного языка.
function seoFields(source, lang) {
  const isCopy = Boolean(source.untranslated) && lang !== MAIN;
  const value = (field) => (isCopy ? pick(source[field], lang) : source[field]?.[lang]) || '';
  return {
    seoTitle: value('seoTitle'),
    seoDescription: value('seoDescription'),
    focusKeyword: value('focusKeyword'),
  };
}

function buildPage(page) {
  const langs = {};
  for (const lang of LANGS) {
    langs[lang] = {
      title: pick(page.title, lang),
      slug: page.slug[lang],
      content: pageContent(page, lang),
      excerpt: pick(page.excerpt, lang),
      ...seoFields(page, lang),
    };
  }
  return { type: 'page', key: page.key, role: page.role, langs };
}

// ---------- Услуги ----------
function serviceContent(service, lang) {
  const strings = SITE.strings[lang] || SITE.strings[MAIN];
  const isMainLang = lang === MAIN;
  return joinBlocks([
    hero({ lead: pick(service.lead, lang), cta: strings.formTitle }),
    section([
      heading(isMainLang ? 'Что входит' : 'What is included'),
      list(pick(service.facts, lang), { className: 'is-style-facts' }),
    ]),
    service.faq ? faq(isMainLang ? 'Вопросы и ответы' : 'FAQ', pick(service.faq, lang)) : '',
  ]);
}

function serviceMeta(service, lang) {
  const meta = {};
  for (const [key, translations] of Object.entries(service.meta || {})) {
    meta[key] = pick(translations, lang);
  }
  // Услуга без перевода: по этой отметке плагин правит robots и hreflang страницы на неосновном языке
  // (acme-core/inc/polylang.php).
  if (service.untranslated && lang !== MAIN) meta.acme_untranslated = true;
  return meta;
}

function buildService(service) {
  const langs = {};
  for (const lang of LANGS) {
    langs[lang] = {
      title: pick(service.title, lang),
      slug: service.slug[lang],
      content: serviceContent(service, lang),
      excerpt: pick(service.lead, lang),
      meta: serviceMeta(service, lang),
      ...seoFields(service, lang),
    };
  }
  return { type: 'acme_service', key: service.key, topic: service.topic, langs };
}

// ---------- Статьи ----------
// Обёртки-секции нет: шаблон статьи сам ставит текст в колонку (templates/single.html).
function buildPost(post) {
  const langs = {};
  for (const lang of LANGS) {
    langs[lang] = {
      title: pick(post.title, lang),
      slug: post.slug[lang],
      content: joinBlocks(pick(post.text, lang).map((text) => textParagraph(text))),
      excerpt: pick(post.excerpt, lang),
      ...seoFields(post, lang),
    };
  }
  return { type: 'post', key: post.key, langs };
}

// ---------- Проверка SEO-полей ----------
// Запись на каждом языке — отдельная строка для проверки; непереведённая копия закрыта от поиска.
function seoRecords(posts) {
  return posts.flatMap((post) =>
    Object.entries(post.langs).map(([lang, version]) => ({
      type: post.type,
      key: post.key,
      lang,
      ...version,
      isClosed: Boolean(version.meta?.acme_untranslated),
    })),
  );
}

function failOnSeoErrors(posts) {
  const errors = validateSeo(seoRecords(posts), { prices: SITE.prices });
  if (!errors.length) return;
  console.error('SEO-поля в project/content/site.js — ошибок: ' + errors.length);
  for (const error of errors) console.error('  ' + error);
  console.error('Правила и пределы длины — wordpress/tools/lib/seo-rules.js. Файлы не записаны.');
  process.exit(1);
}

// ---------- content.json ----------
// Метки {{id:post:…}} внутри атрибутов блока — строки; импортёр подставит ID (в JSON атрибута это станет "123").
function writeContentJson(posts) {
  const content = {
    version: SITE.version,
    site: SITE.site,
    languages: SITE.languages,
    media: [],
    topics: SITE.topics,
    posts,
    menus: SITE.menus,
    redirects: SITE.redirects,
  };
  fs.mkdirSync(OUT_CORE, { recursive: true });
  fs.writeFileSync(path.join(OUT_CORE, 'content.json'), JSON.stringify(content, null, 1) + '\n');
}

// ---------- PHP-файлы ----------
function writePricesPhp() {
  const header = 'Значения по умолчанию; правятся в «Настройки → Цены».';
  fs.writeFileSync(path.join(OUT_CORE, 'prices.php'), phpArrayFile([header], SITE.prices));
}

function writeStringsPhp() {
  const header = [
    'Правки подписей делайте в админке: Языки → Перевод строк.',
    '// Этот файл задаёт значения по умолчанию. Первый язык — основной.',
  ];
  const stringsByLang = Object.fromEntries(LANGS.map((lang) => [lang, SITE.strings[lang] || {}]));
  fs.mkdirSync(OUT_THEME, { recursive: true });
  fs.writeFileSync(path.join(OUT_THEME, 'strings-data.php'), phpArrayFile(header, stringsByLang));
}

function main() {
  // Порядок записей — часть результата: сначала страницы, затем услуги и статьи.
  const posts = [
    ...SITE.pages.map(buildPage),
    ...SITE.services.map(buildService),
    ...(SITE.posts || []).map(buildPost),
  ];
  failOnSeoErrors(posts);
  writeContentJson(posts);
  writePricesPhp();
  writeStringsPhp();
  console.log(
    `posts ${posts.length} × ${LANGS.length} langs, strings ${Object.keys(SITE.strings[MAIN]).length}, prices ${Object.keys(SITE.prices).length}`,
  );
}

main();
