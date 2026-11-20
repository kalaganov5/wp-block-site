/*
 * Правила SEO-полей исходного контента: чистые функции без чтения файлов и без WordPress.
 * Сборщик (tools/build-content.js) передаёт сюда все записи на всех языках и останавливается с кодом 1,
 * если validateSeo() вернула хотя бы одну ошибку. Тесты — tools/tests/seo-rules.test.js.
 *
 * Запись: { type, key, lang, slug, seoTitle, seoDescription, content, isClosed }.
 * Те же пределы длины использует плагин на сайте (acme-core/inc/seo/titles.php, descriptions.php).
 */

const LIMITS = {
  // Google показывает около 600 px заголовка — это примерно 60 знаков; длиннее — обрежет многоточием.
  // Яндекс вмещает до 70, поэтому 60 подходит обоим.
  TITLE_MAX: 60,
  // Короче 10 знаков — это одно слово («Услуги»): по нему не понять, что на странице и чья она.
  TITLE_MIN: 10,
  // Описание короче 70 знаков поисковик обычно заменяет случайным куском текста страницы.
  DESCRIPTION_MIN: 70,
  // В сниппете Google на компьютере помещается 155–160 знаков; остальное обрезается.
  DESCRIPTION_MAX: 160,
};

// Адрес: строчная латиница и цифры, слова через один дефис. Кириллица в адресе превращается в %D0%…
const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

// Длина в знаках, а не в единицах UTF-16: эмодзи и редкие символы считаются за один.
const lengthOf = (text) => [...text].length;

// Метки {{price:ключ}} заменяются ценой на языке записи: в выдаче будет цена, а не метка.
function expandPrices(text, lang, prices) {
  return String(text || '').replace(/\{\{price:([a-z0-9_]+)\}\}/g, (placeholder, key) => {
    const price = prices[key];
    return (price && price[lang]) || placeholder;
  });
}

function lengthErrors(name, text, [min, max]) {
  if (!text) return [`нет ${name}`];
  const length = lengthOf(text);
  if (length > max) return [`${name} длиннее ${max} символов (${length}): «${text}»`];
  if (length < min) return [`${name} короче ${min} символов (${length}): «${text}»`];
  return [];
}

// Заголовок H1 выводит тема из названия записи: второй H1 в тексте путает поисковик и читателя с экрана.
const hasH1 = (content) =>
  /<h1[\s>]/i.test(content) || /wp:heading \{[^}]*"level":1[,}]/.test(content);

// Картинки текста без описания: alt нужен и поиску по картинкам, и программам чтения с экрана.
const imagesWithoutAlt = (content) =>
  (content.match(/<img\b[^>]*>/gi) || []).filter((tag) => !/\salt="[^"]+"/i.test(tag));

function contentErrors(content) {
  const errors = [];
  if (hasH1(content))
    errors.push('H1 в тексте записи: заголовок страницы выводит тема из названия');
  const badImages = imagesWithoutAlt(content);
  if (badImages.length) errors.push(`картинка без alt: ${badImages[0]}`);
  return errors;
}

// Ошибки одной записи на одном языке. Закрытая от поиска копия не проверяется: в выдачу она не попадёт.
function recordErrors(record, prices) {
  if (record.isClosed) return [];
  const title = expandPrices(record.seoTitle, record.lang, prices);
  const description = expandPrices(record.seoDescription, record.lang, prices);
  const errors = [
    ...lengthErrors('seoTitle', title, [LIMITS.TITLE_MIN, LIMITS.TITLE_MAX]),
    ...lengthErrors('seoDescription', description, [
      LIMITS.DESCRIPTION_MIN,
      LIMITS.DESCRIPTION_MAX,
    ]),
    ...contentErrors(record.content || ''),
  ];
  if (!SLUG_PATTERN.test(record.slug || '')) {
    errors.push(`адрес «${record.slug}» — нужны строчная латиница, цифры и дефисы`);
  }
  return errors;
}

const nameOf = (record) => `${record.type}/${record.key}`;

// Одинаковые заголовки или описания внутри языка: поисковик не различит такие страницы в выдаче.
function duplicateErrors(records, field) {
  const namesByValue = new Map();
  for (const record of records) {
    const value = record[field];
    if (!value || record.isClosed) continue;
    const id = `[${record.lang}] ${value}`;
    namesByValue.set(id, [...(namesByValue.get(id) || []), nameOf(record)]);
  }
  return [...namesByValue.entries()]
    .filter(([, names]) => names.length > 1)
    .map(([id, names]) => `${id.slice(0, 4)}: ${field} повторяется: ${names.join(', ')}`);
}

/**
 * Все ошибки SEO-полей. options.prices — таблица цен { ключ: { язык: значение } } для меток {{price:ключ}}.
 * Возвращает массив строк «тип/ключ [язык]: что не так»; пустой массив — всё в порядке.
 */
function validateSeo(records, options = {}) {
  const prices = options.prices || {};
  const errors = [];
  for (const record of records) {
    for (const message of recordErrors(record, prices)) {
      errors.push(`${nameOf(record)} [${record.lang}]: ${message}`);
    }
  }
  return [
    ...errors,
    ...duplicateErrors(records, 'seoTitle'),
    ...duplicateErrors(records, 'seoDescription'),
  ];
}

module.exports = { LIMITS, SLUG_PATTERN, validateSeo };
