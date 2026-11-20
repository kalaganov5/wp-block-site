#!/usr/bin/env node
/*
 * SEO-обход сайта: robots.txt, карты сайта, все страницы из карт и найденные по ссылкам.
 * Для каждой страницы — код ответа, цепочка редиректов, заголовки, время ответа, title, description,
 * robots, canonical, hreflang, Open Graph, H1–H3, картинки без alt, микроразметка, ссылки.
 * GEO: llms.txt, открыт ли сайт ботам ИИ-ассистентов, автор в разметке статей, BreadcrumbList, FAQPage у блоков «Вопросы и ответы».
 * Плюс проверка старых адресов (tools/qa/legacy-urls.txt): каждый должен вести 301 на живую страницу.
 *
 *   npm i node-html-parser@6
 *   node wordpress/tools/qa/seo-crawl.js https://acme.example 500 seo-report
 *
 * Результат: seo-report/pages.json, legacy.json, site.json и summary.md (сводка проблем).
 * Запускается в GitHub Actions (workflow «SEO-аудит»), отчёт сохраняется в ветку seo-reports.
 *
 * Части скрипта — в lib/: seo-http.js (запросы и редиректы), seo-site.js (проверки сайта целиком),
 * seo-crawler.js (обход страниц), seo-page.js (разбор HTML), seo-summary.js (сводка).
 */
const fs = require('fs');
const path = require('path');
const { inspectSite, checkLegacyUrls } = require('./lib/seo-site');
const { crawlPages } = require('./lib/seo-crawler');
const { buildSummaryLines } = require('./lib/seo-summary');

const BASE = (process.argv[2] || 'https://acme.example').replace(/\/$/, '');
const MAX = +(process.argv[3] || 500);
const OUT = process.argv[4] || 'seo-report';
// Разбираем адрес сразу: неверный адрес сайта останавливает скрипт до первых запросов и записи файлов.
const HOST = new URL(BASE).host;
const LEGACY_FILE = path.join(__dirname, 'legacy-urls.txt');

const writeJson = (fileName, data) =>
  fs.writeFileSync(path.join(OUT, fileName), JSON.stringify(data, null, 1));

async function main() {
  fs.mkdirSync(OUT, { recursive: true });
  const config = { base: BASE, host: HOST, max: MAX };

  const { site, sitemapUrls } = await inspectSite(config);
  const pages = await crawlPages(config, sitemapUrls);
  const legacy = await checkLegacyUrls(BASE, LEGACY_FILE);

  writeJson('site.json', site);
  writeJson('pages.json', pages);
  writeJson('legacy.json', legacy);

  const lines = buildSummaryLines({ base: BASE, site, pages, legacy });
  fs.writeFileSync(path.join(OUT, 'summary.md'), lines.join('\n') + '\n');
  // В консоль — только шапка сводки и заголовок первого раздела; полная сводка — в summary.md.
  console.log(lines.slice(0, 6).join('\n'));
}

main();
