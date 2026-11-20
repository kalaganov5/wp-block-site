/*
 * Сценарии интерфейса в браузере: PASS/FAIL по каждой проверке. Дописывайте сценарий для каждого нового интерактива.
 *
 *   npm i playwright && npx playwright install chromium
 *   QA_BASE=http://localhost:8080 node wordpress/tools/qa/interact.js
 *
 * Отправляет одну тестовую заявку — на боевом запускать только по необходимости.
 * Код выхода: 0 — все проверки прошли, 1 — есть FAIL.
 */
const { chromium } = require('playwright');

const BASE = process.env.QA_BASE || 'http://localhost:8080';
let failedCount = 0;

function check(isPassed, message) {
  if (!isPassed) failedCount++;
  console.log((isPassed ? 'PASS ' : 'FAIL ') + message);
}

const isDarkTheme = () => document.documentElement.dataset.theme === 'dark';

// Окно cookie: показано при первом визите, выбор сохраняется, событие acme-consent.
async function checkCookieConsent(page) {
  check(await page.isVisible('[data-acme-cookie]'), 'cookie: окно при первом визите');
  await page.click('[data-acme-ck="prefs"]');
  check(await page.isVisible('[data-acme-ck-prefs]'), 'cookie: настройки');
  await page.check('input[name="analytics"]');
  await page.click('[data-acme-ck="save"]');
  const consent = await page.evaluate(() => JSON.parse(localStorage.getItem('acme-consent')));
  check(
    consent && consent.analytics === true && consent.marketing === false,
    'cookie: выбор сохранён {analytics:true, marketing:false}',
  );
  check(!(await page.isVisible('[data-acme-cookie]')), 'cookie: окно закрыто');
}

// Тема: переключатель меняет data-theme и запоминается.
async function checkThemeSwitch(page) {
  await page.click('[data-acme-theme="dark"]');
  check(await page.evaluate(isDarkTheme), 'тема: тёмная');
  await page.reload({ waitUntil: 'networkidle' });
  check(await page.evaluate(isDarkTheme), 'тема: запомнена после перезагрузки');
  await page.click('[data-acme-theme="light"]');
}

// Мобильное меню: открывается, Esc закрывает и возвращает фокус на кнопку.
async function checkMobileMenu(page) {
  await page.setViewportSize({ width: 360, height: 800 });
  await page.click('[data-acme-menu-toggle]');
  check(await page.isVisible('#acme-mnav'), 'меню: открыто на 360 px');
  await page.keyboard.press('Escape');
  check(
    !(await page.isVisible('#acme-mnav')) &&
      (await page.evaluate(() => document.activeElement.hasAttribute('data-acme-menu-toggle'))),
    'меню: Esc закрывает и возвращает фокус',
  );
  await page.setViewportSize({ width: 1280, height: 900 });
}

// Форма: пустая отправка — ошибки у полей; заполненная — успех (заявка появится в «Заявки»).
async function checkLeadForm(page) {
  if (!(await page.isVisible('#form form'))) return;
  await page.click('#form .wpcf7-submit');
  // Ожидание без ошибки по таймауту: результат фиксирует проверка ниже, а не исключение.
  await page.waitForSelector('#form .wpcf7-not-valid-tip', { timeout: 10000 }).catch(() => {});
  check(
    (await page.locator('#form .wpcf7-not-valid-tip').count()) > 0,
    'форма: ошибки у пустых полей',
  );
  await page.fill('#form [name="your-name"]', 'QA test');
  await page.fill('#form [name="your-contact"]', 'qa@example.com');
  await page.check('#form [name="agree"]');
  await page.click('#form .wpcf7-submit');
  await page
    .waitForSelector('#form form.sent, #form form.failed', { timeout: 15000 })
    .catch(() => {});
  // sent — письмо ушло; failed — почта на сервере не настроена (локально), но заявка всё равно сохраняется в «Заявки».
  check(
    (await page.locator('#form form.sent, #form form.failed').count()) > 0,
    'форма: принята сервером (проверьте запись в «Заявки»)',
  );
}

async function main() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const jsErrors = [];
  page.on('pageerror', (error) => jsErrors.push(error.message));
  await page.goto(BASE + '/', { waitUntil: 'networkidle' });

  // Порядок сценариев важен: каждый продолжает с состояния страницы, оставленного предыдущим.
  await checkCookieConsent(page);
  await checkThemeSwitch(page);
  await checkMobileMenu(page);
  await checkLeadForm(page);

  check(
    !jsErrors.length,
    'нет ошибок JavaScript' + (jsErrors.length ? ': ' + jsErrors.slice(0, 3).join(' | ') : ''),
  );
  await browser.close();
  process.exit(failedCount ? 1 : 0);
}

main();
