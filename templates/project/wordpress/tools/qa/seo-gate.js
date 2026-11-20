#!/usr/bin/env node
/*
 * SEO-гейт для CI: обходит сайт так же, как seo-crawl.js, но завершается с кодом 1, если найдено то, что
 * ломает индексацию или страницу в выдаче. Остальные находки печатаются как предупреждения и сборку не валят.
 *
 *   node wordpress/tools/qa/seo-gate.js http://127.0.0.1:8080 --public [--max=200] [--out=seo-gate-report]
 *
 * Блокирует: страница из карты сайта не 200; нет title или meta description; H1 не один; битая внутренняя
 * ссылка; одинаковые title; картинка без alt; robots.txt или карта сайта не 200; JSON-LD не JSON;
 * на открытом сайте — noindex у страницы из карты, canonical отсутствует или ведёт на другой хост.
 *
 * --public — сайт обязан быть открыт поисковикам (в CI сайт ставится с blog_public = 1): noindex на главной
 * станет ошибкой. --closed — проверки индексации пропускаются. Без флага режим определяется по главной.
 * Правила — lib/seo-gate-rules.js; нужен пакет node-html-parser (он есть в devDependencies).
 */
const fs = require('fs');
const path = require('path');
const { inspectSite } = require('./lib/seo-site');
const { crawlPages } = require('./lib/seo-crawler');
const { buildSummaryLines } = require('./lib/seo-summary');
const { findBlockingProblems, isPublicSite } = require('./lib/seo-gate-rules');

const args = process.argv.slice(2);
const flag = (name) => args.includes('--' + name);
const option = (name, fallback) =>
  (args.find((arg) => arg.startsWith(`--${name}=`)) || '').split('=')[1] || fallback;

const BASE = (args.find((arg) => !arg.startsWith('--')) || '').replace(/\/$/, '');
if (!BASE) {
  console.error(
    'Адрес сайта не задан: node wordpress/tools/qa/seo-gate.js http://127.0.0.1:8080 --public',
  );
  process.exit(2);
}
const HOST = new URL(BASE).host;
const MAX = +option('max', 200);
const OUT = option('out', 'seo-gate-report');

// Открыт ли сайт: флаг важнее того, что видно на главной. --public на закрытом сайте даст ошибку noindex.
function resolveMode(pages) {
  if (flag('public')) return true;
  if (flag('closed')) return false;
  return isPublicSite(pages, BASE);
}

// Предупреждения — разделы сводки аудита с находками (заголовок раздела: «## Название — N»).
function warningLines(summaryLines) {
  return summaryLines
    .filter((line) => /^\n## .* — [1-9]\d*\n$/.test(line))
    .map((line) => line.trim().replace(/^## /, ''));
}

async function main() {
  const config = { base: BASE, host: HOST, max: MAX };
  const { site, sitemapUrls } = await inspectSite(config);
  const pages = await crawlPages(config, sitemapUrls);
  const isPublic = resolveMode(pages);
  const summary = buildSummaryLines({ base: BASE, site, pages, legacy: [] });

  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'pages.json'), JSON.stringify(pages, null, 1));
  fs.writeFileSync(path.join(OUT, 'summary.md'), summary.join('\n') + '\n');

  const problems = findBlockingProblems({ base: BASE, site, pages, isPublic });
  const mode = isPublic
    ? 'открыт поисковикам'
    : 'закрыт от поисковиков (индексация не проверяется)';
  console.log(
    `SEO-гейт ${BASE}: страниц ${pages.length}, в карте сайта ${sitemapUrls.length}, сайт ${mode}.`,
  );
  for (const warning of warningLines(summary)) console.log('предупреждение: ' + warning);
  for (const problem of problems) console.error('ОШИБКА: ' + problem);
  console.log(
    problems.length
      ? `SEO-гейт не пройден: ошибок ${problems.length}. Подробности — ${OUT}/summary.md`
      : 'SEO-гейт пройден.',
  );
  process.exit(problems.length ? 1 : 0);
}

main();
