#!/usr/bin/env node
/*
 * Разворачивает каркас проекта (templates/project) в новую папку и заменяет имя-заглушку acme.
 *
 *   node scaffold.js <папка> --slug=studio --name="Studio Pro" --domain=studio.pro [--force]
 *
 *   --slug    латиница и цифры, 2–8 символов, с буквы (короткий префикс — короткие строки кода): папка темы, плагин <slug>-core, префикс функций <slug>_,
 *             классов <slug>-, блоков <slug>/, команд wp <slug> …, констант <SLUG>_
 *   --name    название сайта (заголовок темы и плагина, подписи в админке)
 *   --domain  боевой домен без https:// (защита деплоя, SEO-аудит, примеры)
 *
 * Только Node, без зависимостей. Папка должна быть пустой или отсутствовать (--force — писать поверх).
 */
const fs = require('fs');
const path = require('path');

const args = process.argv.slice(2);
const opt = Object.fromEntries(args.filter((a) => a.startsWith('--')).map((a) => { const [k, ...v] = a.slice(2).split('='); return [k, v.length ? v.join('=') : true]; }));
const dest = args.find((a) => !a.startsWith('--'));

const fail = (m) => { console.error('Ошибка: ' + m); process.exit(1); };
if (!dest || !opt.slug || !opt.name || !opt.domain) {
  fail('нужны <папка> --slug=… --name="…" --domain=…\nПример: node scaffold.js ../studio-site --slug=studio --name="Studio Pro" --domain=studio.pro');
}
const slug = String(opt.slug);
// Не длиннее 8: префикс стоит в каждой функции, классе и блоке; каркас рассчитан на этот запас длины строки (120).
if (!/^[a-z][a-z0-9]{1,7}$/.test(slug)) fail('--slug: латиница в нижнем регистре и цифры, 2–8 символов, с буквы (это ещё и префикс функций PHP)');
if (['wp', 'wordpress', 'core', 'block', 'theme'].includes(slug)) fail('--slug: слишком общее имя, возьмите имя проекта');
if (/['"\\]/.test(String(opt.name))) fail('--name: без кавычек и обратной косой черты (имя попадает в строки PHP и shell)');
const domain = String(opt.domain).replace(/^https?:\/\//, '').replace(/\/.*$/, '');

const SRC = path.join(__dirname, '..', 'templates', 'project');
const DEST = path.resolve(dest);
if (fs.existsSync(DEST) && fs.readdirSync(DEST).length && !opt.force) fail(`папка ${DEST} не пуста (--force — писать поверх)`);

const cap = slug[0].toUpperCase() + slug.slice(1);
// Порядок важен: сначала длинные и составные заглушки, потом само имя.
const swap = (s) => s
  .split('Acme Site').join(String(opt.name))
  .split('acme.example').join(domain)
  .split('ACME').join(slug.toUpperCase())
  .split('Acme').join(cap)
  .split('acme').join(slug);

const SKIP_DIRS = ['node_modules', 'vendor'];
// Каркас лежит папкой templates/project (репозиторий на GitHub) или одним файлом templates/project.bundle.json
// (архив для claude.ai: там не больше 200 файлов в скилле). Файл собирает scripts/bundle-template.js.
const BUNDLE = path.join(__dirname, '..', 'templates', 'project.bundle.json');

/** Файлы каркаса: [путь от корня проекта, Buffer]. */
function templateFiles() {
  if (!fs.existsSync(SRC)) {
    if (!fs.existsSync(BUNDLE)) fail('нет ни templates/project, ни templates/project.bundle.json');
    const bundle = JSON.parse(fs.readFileSync(BUNDLE, 'utf8'));
    return Object.entries(bundle.files).map(([file, base64]) => [file, Buffer.from(base64, 'base64')]);
  }
  const found = [];
  (function walk(dir, prefix) {
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
      // Установленные инструменты (npm install, composer install) в новый проект не копируются.
      if (e.isDirectory() && SKIP_DIRS.includes(e.name)) continue;
      const relative = prefix ? prefix + '/' + e.name : e.name;
      if (e.isDirectory()) walk(path.join(dir, e.name), relative);
      else found.push([relative, fs.readFileSync(path.join(dir, e.name))]);
    }
  })(SRC, '');
  return found;
}

let files = 0;
for (const [relative, buf] of templateFiles()) {
  const dst = path.join(DEST, ...swap(relative).split('/'));
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  // Текстовые файлы — с заменой имён; бинарные (картинки, шрифты) — как есть.
  const text = !buf.includes(0);
  fs.writeFileSync(dst, text ? swap(buf.toString('utf8')) : buf);
  if (/.sh$/.test(relative)) fs.chmodSync(dst, 0o755);
  files++;
}

console.log(`Готово: ${files} файлов в ${DEST}

Дальше:
  cd ${path.relative(process.cwd(), DEST) || '.'}
  git init && npm install && composer install   # инструменты проверки и хук перед коммитом
  npm run format                                  # выровнять код под имя проекта (переносы и отступы)
  npm run check && git add -A && git commit -m "chore: project scaffold"
  docker compose up -d && docker compose logs -f setup      # http://localhost:8080, admin / admin

Настроить под проект (поиск по «${slug}»):
  - языки: project/content/site.js (languages, strings), ${slug.toUpperCase()}_LANGS в wordpress/${slug}-core/${slug}-core.php,
    EXTRA_LOCALES и LOCALE в wordpress/deploy/remote.sh; один язык — уберите polylang из PLUGINS и адреса /en/ из wordpress/deploy/pages.txt
  - дизайн: wordpress/${slug}/assets/css/tokens.css + палитра theme.json, шрифты в assets/fonts
  - контент: project/content/site.js → node wordpress/tools/build-content.js
  - кто за сайтом (person | company): ${slug.toUpperCase()}_SEO_ENTITY в wordpress/${slug}-core/inc/seo/rank-math.php
  - часовой пояс: TIMEZONE в wordpress/deploy/remote.sh
  - GitHub: Settings → Environments → test, production + секреты (wordpress/README.md)`);
