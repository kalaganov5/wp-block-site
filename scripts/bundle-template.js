#!/usr/bin/env node
/*
 * Собирает каркас templates/project в один файл templates/project.bundle.json — для архива скилла в claude.ai,
 * где в скилле не больше 200 файлов. scaffold.js разворачивает проект и из папки, и из этого файла.
 *
 *   node scripts/bundle-template.js            → templates/project.bundle.json
 *   node scripts/bundle-template.js --zip      → ещё и dist/wp-block-site.zip без папки каркаса (нужен git)
 *
 * В репозитории файл не хранится: источник — папка, файл собирается перед загрузкой в claude.ai.
 */
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, '..');
const SRC = path.join(ROOT, 'templates', 'project');
const BUNDLE = path.join(ROOT, 'templates', 'project.bundle.json');
const SKIP_DIRS = ['node_modules', 'vendor'];

function collect(dir, prefix, files) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory() && SKIP_DIRS.includes(entry.name)) continue;
    const relative = prefix ? `${prefix}/${entry.name}` : entry.name;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) collect(full, relative, files);
    else files[relative] = fs.readFileSync(full).toString('base64');
  }
  return files;
}

const files = collect(SRC, '', {});
fs.writeFileSync(BUNDLE, JSON.stringify({ format: 1, files }) + '\n');
console.log(`Каркас: ${Object.keys(files).length} файлов → ${path.relative(ROOT, BUNDLE)}`);

if (process.argv.includes('--zip')) {
  // Архив для claude.ai: файлы скилла из git, без папки каркаса и файлов только для GitHub, плюс собранный файл.
  const SKIP = /^(templates\/project\/|README\.md$|CONTRIBUTING\.md$|LICENSE$|\.github\/|\.gitattributes$|\.gitignore$)/;
  const tracked = execFileSync('git', ['ls-files'], { cwd: ROOT, encoding: 'utf8' }).split('\n').filter(Boolean);
  const list = [...tracked.filter((file) => !SKIP.test(file)), 'templates/project.bundle.json'];
  const dist = path.join(ROOT, 'dist');
  const stage = path.join(dist, 'wp-block-site');
  fs.rmSync(dist, { recursive: true, force: true });
  for (const file of list) {
    fs.mkdirSync(path.dirname(path.join(stage, file)), { recursive: true });
    fs.copyFileSync(path.join(ROOT, file), path.join(stage, file));
  }
  console.log(`Файлов в архиве: ${list.length}; упакуйте папку ${path.relative(ROOT, stage)} в zip`);
}
