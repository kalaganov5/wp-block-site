/*
 * Общее для проверок в редакторе WordPress (editor-validity.js, editor-usability.js): вход в админку,
 * открытие записи в редакторе, запросы REST от имени вошедшего пользователя.
 *
 * Отдельно не запускается: const editor = require('./lib/editor');
 */
/* global wp -- объект редактора WordPress: доступен в функциях, которые Playwright выполняет внутри страницы */

const BASE = process.env.QA_BASE || 'http://localhost:8080';
const USER = process.env.QA_USER || 'admin';
const PASS = process.env.QA_PASS || 'admin';
const EDITOR_TIMEOUT_MS = 60000;
// Пауза после готовности редактора: даём ему закончить разбор блоков записи.
const EDITOR_SETTLE_MS = 1500;

async function logIn(page) {
  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
}

// Выполняется внутри страницы: редактор загрузил запись и хранилище блоков готово.
const isEditorReady = () =>
  window.wp &&
  wp.data &&
  wp.data.select('core/block-editor') &&
  wp.data.select('core/editor').getCurrentPostId();

// Выполняется внутри страницы редактора: окна «Добро пожаловать» и «Выберите шаблон» закрывали бы холст.
function dismissEditorGuides() {
  const preferences = wp.data.dispatch('core/preferences');
  preferences.set('core/edit-post', 'welcomeGuide', false);
  preferences.set('core', 'enableChoosePatternModal', false);
}

/** Открывает адрес редактора (post.php?post=…&action=edit или post-new.php) и ждёт готовности. */
async function openEditor(page, adminPath) {
  await page.goto(BASE + '/wp-admin/' + adminPath);
  await page.waitForFunction(isEditorReady, null, { timeout: EDITOR_TIMEOUT_MS });
  await page.evaluate(dismissEditorGuides);
  await page.waitForTimeout(EDITOR_SETTLE_MS);
}

// Выполняется внутри страницы редактора: запрос REST через wp.apiFetch (cookie входа и nonce он добавляет сам).
// Ошибка запроса — null: вызывающая проверка сама решает, провал это или нет.
function restInPage({ method, path, data }) {
  return wp.apiFetch({ path, method: method || 'GET', data }).catch(() => null);
}

/** Запрос REST от имени вошедшего пользователя; на странице должен быть открыт редактор. */
const rest = (page, request) => page.evaluate(restInPage, request);

module.exports = { BASE, logIn, openEditor, rest };
