/*
 * Бюджет статики темы: проверка «budget» из tools/check.js.
 *
 * Зачем: скорость сайта теряется незаметно — по килобайту в общий CSS, по функции в общий скрипт, по одному
 * «удобному» шрифту с чужого сервера. Проверка останавливает это на коммите, а не на замере PageSpeed.
 *
 *   1. CSS, который грузится на каждой странице (общие файлы + шапка и подвал), — не больше CSS_BUDGET.
 *   2. JS на каждой странице (assets/js/site.js + view.js шапки и подвала) — не больше JS_BUDGET.
 *   3. Тема не обращается к чужим серверам: шрифты, скрипты и стили лежат в теме.
 *
 * Отдельно не запускается: const { checkBudget } = require('./lib/budget');
 */
const fs = require('node:fs');
const path = require('node:path');

// Размеры — без сжатия, как в файловой системе (по сети gzip отдаёт примерно вчетверо меньше).
// Числа — размер каркаса плюс около 30 % на оформление проекта: общий CSS каркаса — 17,2 КБ, скрипт — 11,6 КБ.
// Упёрлись в предел — сначала вынесите стили блока в его style.css, а поведение — в view.js блока (они грузятся
// только вместе с блоком), и только потом поднимайте бюджет, записав причину в docs/decisions.md.
const KILOBYTE = 1024;
const CSS_BUDGET = 22 * KILOBYTE;
const JS_BUDGET = 15 * KILOBYTE;

// Блоки, которые есть на каждой странице: их стили — часть общего CSS.
const EVERY_PAGE_BLOCKS = ['site-header', 'site-footer'];

// Чужие адреса, которые теме разрешены, и почему.
const ALLOWED_HOSTS = [
  'www.youtube-nocookie.com', // плеер YouTube: грузится только по клику на превью (inc/embeds.php)
  'i.ytimg.com', // картинка-превью ролика YouTube
  'schema.org', // словарь разметки — это имя, а не запрос
  'www.w3.org', // пространства имён SVG и XML — тоже имена
  'schemas.wp.org', // "$schema" в block.json и theme.json: подсказки редактора кода
];
const SCANNED_EXTENSIONS = ['.php', '.css', '.js', '.html', '.json'];
// Адрес с протоколом или «//host» в кавычках и скобках (так подключают ресурсы без протокола).
const EXTERNAL_URL = /(?:https?:|["'(])\/\/([a-z0-9.-]+\.[a-z]{2,})/gi;

const kilobytes = (bytes) => (bytes / KILOBYTE).toFixed(1) + ' КБ';

/** Общие стили в порядке подключения: имена из ACME_BASE_STYLES (inc/assets.php) — список там один. */
function baseStyleFiles(themeDir) {
  const source = fs.readFileSync(path.join(themeDir, 'inc', 'assets.php'), 'utf8');
  const declaration = source.match(/const \w+_BASE_STYLES = array\(([^)]*)\)/);
  if (!declaration) {
    return null;
  }
  return [...declaration[1].matchAll(/'([\w-]+)'/g)].map((match) => `assets/css/${match[1]}.css`);
}

function checkSize({ title, files, budget, themeDir }) {
  const total = files.reduce((sum, file) => sum + fs.statSync(path.join(themeDir, file)).size, 0);
  const isWithin = total <= budget;
  console.log(
    `${title}: ${kilobytes(total)} из ${kilobytes(budget)}${isWithin ? '' : ' — ПРЕВЫШЕН'}`,
  );
  if (!isWithin) {
    files.forEach((file) =>
      console.log(`  ${kilobytes(fs.statSync(path.join(themeDir, file)).size)}  ${file}`),
    );
  }
  return isWithin;
}

/** Чужие адреса в файле: [ 'файл:строка — host', … ]. */
function externalHostsIn(file, themeDir) {
  const lines = fs.readFileSync(path.join(themeDir, file), 'utf8').split('\n');
  const found = [];
  lines.forEach((line, index) => {
    for (const match of line.matchAll(EXTERNAL_URL)) {
      const host = match[1].toLowerCase();
      if (!ALLOWED_HOSTS.includes(host)) {
        found.push(`${file}:${index + 1} — ${host}`);
      }
    }
  });
  return found;
}

function checkExternalHosts(themeFiles, themeDir) {
  // style.css в корне темы — только шапка с адресами автора и лицензии: сайт этот файл не подключает.
  const scanned = themeFiles.filter(
    (file) => file !== 'style.css' && SCANNED_EXTENSIONS.includes(path.extname(file)),
  );
  const found = scanned.flatMap((file) => externalHostsIn(file, themeDir));
  if (found.length) {
    console.log('Тема обращается к чужим серверам (шрифты, скрипты и стили должны лежать в теме):');
    found.forEach((line) => console.log('  ' + line));
    console.log('Нужный адрес (видео по клику и т. п.) — добавьте в ALLOWED_HOSTS с причиной.');
  }
  return found.length === 0;
}

/**
 * themeDir — папка темы; themeFiles — её файлы (пути от папки темы).
 * Все три части проверяются всегда: сообщение покажет сразу всё, что не так.
 */
function checkBudget(themeDir, themeFiles) {
  const baseStyles = baseStyleFiles(themeDir);
  if (!baseStyles) {
    console.log('Не найден список общих стилей *_BASE_STYLES в inc/assets.php');
    return false;
  }
  const blockStyles = EVERY_PAGE_BLOCKS.map((name) => `blocks/${name}/style.css`);
  const everyPageCss = [...baseStyles, ...blockStyles];
  const blockScripts = EVERY_PAGE_BLOCKS.map((name) => `blocks/${name}/view.js`).filter((file) =>
    fs.existsSync(path.join(themeDir, file)),
  );
  const results = [
    checkSize({
      title: 'CSS на каждой странице',
      files: everyPageCss,
      budget: CSS_BUDGET,
      themeDir,
    }),
    checkSize({
      title: 'JS на каждой странице',
      files: ['assets/js/site.js', ...blockScripts],
      budget: JS_BUDGET,
      themeDir,
    }),
    checkExternalHosts(themeFiles, themeDir),
  ];
  return results.every(Boolean);
}

module.exports = { checkBudget };
