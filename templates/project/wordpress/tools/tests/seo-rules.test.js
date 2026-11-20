// Тесты правил SEO-полей исходного контента (wordpress/tools/lib/seo-rules.js).
// Запуск: node --test wordpress/tools/tests/*.test.js. Названия тестов — требования на языке команды.
const test = require('node:test');
const assert = require('node:assert/strict');
const { validateSeo, LIMITS } = require('../lib/seo-rules');

const DESCRIPTION =
  'Чиним и обслуживаем сайты на WordPress: скорость, защита, формы. Отвечаем за рабочий день.';

// Одна запись на одном языке; overrides меняют только проверяемое поле.
const record = (overrides = {}) => ({
  type: 'page',
  key: 'about',
  lang: 'ru',
  slug: 'about',
  seoTitle: 'О компании Acme Site',
  seoDescription: DESCRIPTION,
  content: '<!-- wp:paragraph -->\n<p>Текст.</p>\n<!-- /wp:paragraph -->',
  ...overrides,
});

const errorsOf = (overrides) => validateSeo([record(overrides)]);

test('правильная запись проходит без ошибок', () => {
  assert.deepEqual(validateSeo([record()]), []);
});

test('ошибка называет страницу и язык', () => {
  const [error] = errorsOf({ seoTitle: '' });
  assert.match(error, /^page\/about \[ru\]: /);
});

test('запись без SEO-заголовка или SEO-описания — ошибка', () => {
  assert.match(errorsOf({ seoTitle: '' })[0], /нет seoTitle/);
  assert.match(errorsOf({ seoDescription: undefined })[0], /нет seoDescription/);
});

test('SEO-заголовок длиннее 60 или короче 10 символов — ошибка', () => {
  assert.match(errorsOf({ seoTitle: 'я'.repeat(LIMITS.TITLE_MAX + 1) })[0], /длиннее 60/);
  assert.match(errorsOf({ seoTitle: 'Коротко' })[0], /короче 10/);
  assert.deepEqual(errorsOf({ seoTitle: 'я'.repeat(LIMITS.TITLE_MAX) }), []);
});

test('SEO-описание вне 70–160 символов — ошибка', () => {
  assert.match(errorsOf({ seoDescription: 'я'.repeat(69) })[0], /короче 70/);
  assert.match(errorsOf({ seoDescription: 'я'.repeat(161) })[0], /длиннее 160/);
  assert.deepEqual(errorsOf({ seoDescription: 'я'.repeat(160) }), []);
});

test('длина считается после подстановки цены вместо метки {{price:ключ}}', () => {
  const seoDescription = 'я'.repeat(150) + ' {{price:hour}}';
  const prices = { hour: { ru: '2 000 ₽' } };
  assert.deepEqual(validateSeo([record({ seoDescription })], { prices }), []);
  const longPrice = { hour: { ru: 'очень длинная цена на час работы' } };
  assert.match(validateSeo([record({ seoDescription })], { prices: longPrice })[0], /длиннее 160/);
});

test('одинаковый заголовок или описание у двух записей одного языка — ошибка', () => {
  const errors = validateSeo([record(), record({ key: 'contacts', slug: 'contacts' })]);
  assert.equal(errors.length, 2);
  assert.match(errors[0], /seoTitle повторяется: page\/about, page\/contacts/);
  assert.match(errors[1], /seoDescription повторяется/);
});

test('одинаковый заголовок на разных языках — не ошибка', () => {
  assert.deepEqual(validateSeo([record(), record({ lang: 'en', slug: 'about-us' })]), []);
});

test('адрес не из строчной латиницы, цифр и дефисов — ошибка', () => {
  for (const slug of ['О-нас', 'About', 'about_us', 'about--us', '-about', '']) {
    assert.match(errorsOf({ slug })[0], /адрес/, slug);
  }
  assert.deepEqual(errorsOf({ slug: 'about-us-2' }), []);
});

test('заголовок H1 в тексте записи — ошибка: H1 выводит тема из названия записи', () => {
  const content = '<!-- wp:heading {"level":1} -->\n<h1 class="wp-block-heading">О нас</h1>';
  assert.match(errorsOf({ content })[0], /H1 в тексте/);
});

test('картинка без alt или с пустым alt — ошибка', () => {
  assert.match(errorsOf({ content: '<figure><img src="/a.jpg"/></figure>' })[0], /без alt/);
  assert.match(errorsOf({ content: '<figure><img src="/a.jpg" alt=""/></figure>' })[0], /без alt/);
  assert.deepEqual(errorsOf({ content: '<figure><img src="/a.jpg" alt="Офис"/></figure>' }), []);
});

test('закрытая от поиска копия (текст не переведён) не проверяется', () => {
  assert.deepEqual(errorsOf({ isClosed: true, seoTitle: '', seoDescription: '' }), []);
});
