/*
 * Проверки сайта целиком для SEO-обхода: зеркала (http, www), robots.txt, доступ ботов ИИ-ассистентов,
 * llms.txt, ответ несуществующей страницы, карты сайта и старые адреса прежнего сайта.
 * Порядок полей объекта site — это порядок полей в site.json.
 *
 * Отдельно не запускается — подключается из wordpress/tools/qa/seo-crawl.js.
 */
const fs = require('fs');
const { get, follow } = require('./seo-http');

// GEO: боты ИИ-ассистентов и поисковиков, для которых проверяем доступ в robots.txt.
const AI_BOTS = [
  'GPTBot',
  'OAI-SearchBot',
  'ChatGPT-User',
  'ClaudeBot',
  'PerplexityBot',
  'Google-Extended',
  'YandexBot',
];

// Группы robots.txt: подряд идущие User-agent делят один набор правил; после правил начинается новая группа.
function parseRobotsGroups(body) {
  const groups = [];
  let current = null;
  for (const rawLine of body.split(/\r?\n/)) {
    const match = /^([a-z-]+)\s*:\s*(.*)$/i.exec(rawLine.replace(/#.*/, '').trim());
    if (!match) continue;
    const field = match[1].toLowerCase();
    const value = match[2].trim();
    if (field === 'user-agent') {
      if (!current || current.rules.length) {
        current = { agents: [], rules: [] };
        groups.push(current);
      }
      current.agents.push(value.toLowerCase());
    } else if (current && (field === 'allow' || field === 'disallow')) {
      current.rules.push([field, value]);
    }
  }
  return groups;
}

/** Закрыт ли сайт для бота: в его группе (или в группе *, если своей нет) есть «Disallow: /». */
function robotsBlocks(body, bot) {
  const groups = parseRobotsGroups(body);
  const ownGroups = groups.filter((group) => group.agents.includes(bot.toLowerCase()));
  const activeGroups = ownGroups.length
    ? ownGroups
    : groups.filter((group) => group.agents.includes('*'));
  return activeGroups.some((group) =>
    group.rules.some(([field, value]) => field === 'disallow' && value === '/'),
  );
}

// Главные зеркала: http, www — должны вести одним 301 на https://<host>/.
async function checkMirrors(host) {
  const bareHost = host.replace(/^www\./, '');
  const mirrorUrls = [
    'http://' + host + '/',
    'https://www.' + bareHost + '/',
    'http://www.' + bareHost + '/',
  ];
  const mirrors = [];
  for (const mirrorUrl of mirrorUrls) {
    const result = await follow(mirrorUrl);
    mirrors.push({
      from: mirrorUrl,
      chain: result.chain.map((step) => step.status + ' ' + step.url),
      final: result.final,
    });
  }
  return mirrors;
}

// Адреса из <loc>: вложенные карты (.xml) — в очередь, остальные — страницы.
function readSitemapLocs(body) {
  const sitemaps = [];
  const pages = [];
  for (const match of body.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)) {
    const url = match[1].replace(/&amp;/g, '&');
    (/\.xml(\?|$)/.test(url) ? sitemaps : pages).push(url);
  }
  return { sitemaps, pages };
}

// Карты сайта: пробуем три стандартных адреса (Rank Math, обычная, встроенная в WordPress) и вложенные карты.
async function collectSitemaps(base) {
  const seen = new Set();
  const urls = new Set();
  const maps = [];
  const queue = [base + '/sitemap_index.xml', base + '/sitemap.xml', base + '/wp-sitemap.xml'];
  while (queue.length) {
    const sitemapUrl = queue.shift();
    if (seen.has(sitemapUrl)) continue;
    seen.add(sitemapUrl);
    const { final, response, chain } = await follow(sitemapUrl);
    maps.push({
      url: sitemapUrl,
      status: response.status,
      final,
      redirects: chain.length - 1,
      type: response.headers['content-type'] || '',
    });
    if (response.status !== 200) continue;
    const locs = readSitemapLocs(response.body);
    queue.push(...locs.sitemaps);
    locs.pages.forEach((url) => urls.add(url));
  }
  return { maps, urls: [...urls] };
}

// Все проверки уровня сайта. Запросы идут строго по очереди — так же, как поля в site.json.
async function inspectSite(config) {
  const { base, host } = config;
  const site = { base, date: new Date().toISOString() };
  site.mirrors = await checkMirrors(host);
  const robots = await get(base + '/robots.txt');
  site.robots = { status: robots.status, body: robots.body.slice(0, 4000) };
  // GEO: открыт ли сайт ботам ИИ-ассистентов и есть ли llms.txt.
  site.aiBots = AI_BOTS.map((bot) => ({ bot, blocked: robotsBlocks(robots.body, bot) }));
  const llms = await get(base + '/llms.txt');
  site.llms = {
    status: llms.status,
    links: llms.body.split('\n').filter((line) => /^- \[/.test(line)).length,
  };
  const notFound = await get(base + '/this-page-does-not-exist-seoaudit/');
  site.notFound = { status: notFound.status };
  const sitemaps = await collectSitemaps(base);
  site.sitemaps = sitemaps.maps;
  site.sitemapCount = sitemaps.urls.length;
  return { site, sitemapUrls: sitemaps.urls };
}

// Старые адреса прежнего сайта → должны отвечать 301 на страницу с 200.
async function checkLegacyUrls(base, legacyFile) {
  const legacy = [];
  if (!fs.existsSync(legacyFile)) return legacy;
  const paths = fs
    .readFileSync(legacyFile, 'utf8')
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));
  for (const legacyPath of paths) {
    const result = await follow(base + legacyPath);
    legacy.push({
      from: legacyPath,
      chain: result.chain.map((step) => step.status),
      final: result.final.replace(base, ''),
      status: result.response.status,
    });
  }
  return legacy;
}

module.exports = { inspectSite, checkLegacyUrls };
