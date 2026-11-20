#!/usr/bin/env node
/*
 * Одна команда проверки проекта: npm run check.
 *
 * Её вызывают и человек перед коммитом (.githooks/pre-commit), и CI (job «Проверка кода»), и ручной деплой —
 * поэтому «у меня проходит, а в CI нет» не бывает: набор проверок один.
 *
 *   npm run check              все проверки
 *   npm run check -- php js    только названные (имена — ключи в CHECKS ниже)
 *
 * Проверка, которой не хватает инструмента (нет vendor/ или shellcheck), локально пропускается с
 * предупреждением, а в CI (переменная CI) считается ошибкой: там обязаны пройти все.
 */
const { spawnSync } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { checkBudget } = require('./lib/budget');

const ROOT = path.resolve(__dirname, '..', '..');
const THEME = 'wordpress/acme';
const PLUGIN = 'wordpress/acme-core';
const IS_CI = Boolean(process.env.CI);
const SKIPPED = 'skipped';

/** Запускает программу в корне проекта; вывод идёт в консоль как есть. */
function run(command, args) {
  const result = spawnSync(command, args, { cwd: ROOT, stdio: 'inherit' });
  return result.status === 0;
}

/** Запускает инструмент из node_modules тем же Node — одинаково на Windows, macOS и Linux. */
function runNodeTool(binPath, args) {
  const bin = path.join(ROOT, 'node_modules', binPath);
  if (!fs.existsSync(bin)) {
    console.log('Нет node_modules: выполните npm install');
    return false;
  }
  return run(process.execPath, [bin, ...args]);
}

/** Все файлы папки с нужным расширением (без node_modules и vendor). */
function listFiles(dir, extension) {
  const found = [];
  for (const entry of fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true })) {
    const relative = `${dir}/${entry.name}`;
    if (entry.isDirectory()) {
      if (entry.name !== 'node_modules' && entry.name !== 'vendor') {
        found.push(...listFiles(relative, extension));
      }
    } else if (entry.name.endsWith(extension)) {
      found.push(relative);
    }
  }
  return found;
}

function hasCommand(command) {
  return spawnSync(command, ['--version'], { stdio: 'ignore' }).status === 0;
}

function checkPhpSyntax() {
  if (!hasCommand('php')) {
    console.log('PHP не установлен: синтаксис PHP проверит CI');
    return SKIPPED;
  }
  const broken = listFiles('wordpress', '.php').filter((file) => {
    const result = spawnSync('php', ['-l', file], { cwd: ROOT, encoding: 'utf8' });
    if (result.status !== 0) {
      console.log(result.stdout + result.stderr);
    }
    return result.status !== 0;
  });
  return broken.length === 0;
}

function checkJson() {
  const files = [...listFiles(THEME, '.json'), ...listFiles(`${PLUGIN}/content`, '.json')];
  const broken = files.filter((file) => {
    try {
      JSON.parse(fs.readFileSync(path.join(ROOT, file), 'utf8'));
      return false;
    } catch (error) {
      console.log(`${file}: ${error.message}`);
      return true;
    }
  });
  return broken.length === 0;
}

/** Отпечаток сгенерированных файлов: по нему видно, изменил ли их сборщик. */
function generatedFingerprint() {
  const files = [...listFiles(`${PLUGIN}/content`, ''), `${THEME}/inc/strings-data.php`];
  const hash = crypto.createHash('sha1');
  for (const file of files.sort()) {
    hash.update(file).update(fs.readFileSync(path.join(ROOT, file)));
  }
  return hash.digest('hex');
}

/** Сгенерированное не правят руками и не забывают пересобрать: после сборки файлы не должны измениться. */
function checkGenerated() {
  const before = generatedFingerprint();
  if (!run(process.execPath, ['wordpress/tools/build-content.js'])) {
    return false;
  }
  if (generatedFingerprint() === before) {
    return true;
  }
  console.log('Сгенерированные файлы устарели и сейчас пересобраны: добавьте их в коммит.');
  return false;
}

function checkPhpcs() {
  const phpcs = path.join(ROOT, 'vendor', 'bin', 'phpcs');
  if (!fs.existsSync(phpcs)) {
    console.log(
      'Нет vendor/: выполните composer install (PHPCS — безопасность, оформление, пределы размера)',
    );
    return SKIPPED;
  }
  return run('php', [phpcs, '-q']);
}

/** Имена функций, объявленных в PHP-файлах. */
function declaredFunctions(files) {
  const names = new Set();
  for (const file of files) {
    const source = fs.readFileSync(path.join(ROOT, file), 'utf8');
    for (const match of source.matchAll(/^\s*function\s+(acme_\w+)\s*\(/gm)) {
      names.add(match[1]);
    }
  }
  return names;
}

/**
 * Граница слоёв: тема вызывает только функции плагина из inc/api.php.
 * Так плагин можно перестраивать внутри, не разыскивая вызовы по теме.
 */
function checkBoundary() {
  const apiFile = `${PLUGIN}/inc/api.php`;
  const pluginFiles = listFiles(PLUGIN, '.php');
  const internal = declaredFunctions(pluginFiles.filter((file) => file !== apiFile));
  const api = declaredFunctions([apiFile]);
  let isClean = true;
  for (const file of listFiles(THEME, '.php')) {
    const source = fs.readFileSync(path.join(ROOT, file), 'utf8');
    const called = new Set([...source.matchAll(/\b(acme_\w+)\s*\(/g)].map((match) => match[1]));
    for (const name of called) {
      if (internal.has(name) && !api.has(name)) {
        console.log(
          `${file}: ${name}() — внутренняя функция плагина. Тема вызывает только ${apiFile}`,
        );
        isClean = false;
      }
    }
  }
  return isClean;
}

/** Размер общей статики темы и запрет чужих серверов — правила и числа в tools/lib/budget.js. */
function checkThemeBudget() {
  const themeFiles = listFiles(THEME, '').map((file) => file.slice(THEME.length + 1));
  return checkBudget(path.join(ROOT, THEME), themeFiles);
}

/** Тесты правил на JS (сборщик, SEO-гейт): встроенный в Node раннер, без зависимостей. */
function checkJsTests() {
  const files = listFiles('wordpress/tools/tests', '.test.js');
  return files.length === 0 || run(process.execPath, ['--test', ...files]);
}

/** Тесты правил на PHP (плагин): wordpress/tools/tests/run.php. */
function checkPhpTests() {
  // Правила работают с кириллицей (mb_*): без расширения mbstring PHP-тесты проверить нельзя.
  const hasMbstring =
    spawnSync('php', ['-r', 'exit(extension_loaded("mbstring") ? 0 : 1);']).status === 0;
  if (!hasMbstring) {
    console.log('Нет PHP с расширением mbstring: PHP-тесты выполнит CI');
    return SKIPPED;
  }
  return run('php', ['wordpress/tools/tests/run.php']);
}

function checkTests() {
  const jsOutcome = checkJsTests();
  const phpOutcome = checkPhpTests();
  if (jsOutcome === false || phpOutcome === false) {
    return false;
  }
  return phpOutcome;
}

function checkShell() {
  if (!hasCommand('shellcheck')) {
    console.log('shellcheck не установлен: скрипты деплоя проверит CI');
    return SKIPPED;
  }
  // Все скрипты оболочки проекта: деплой, локальный сайт, инструменты, CI и хук перед коммитом.
  const folders = ['wordpress/deploy', 'wordpress/local', 'wordpress/tools', '.github/scripts'];
  const scripts = [...folders.flatMap((dir) => listFiles(dir, '.sh')), '.githooks/pre-commit'];
  return run('shellcheck', ['-x', '-S', 'warning', ...scripts]);
}

const CHECKS = {
  php: ['PHP без синтаксических ошибок', checkPhpSyntax],
  json: ['theme.json, block.json, content.json — корректный JSON', checkJson],
  generated: ['Сгенерированные файлы актуальны', checkGenerated],
  format: [
    'Оформление JS, CSS, JSON, YAML (Prettier)',
    () => runNodeTool('prettier/bin/prettier.cjs', ['--check', '.']),
  ],
  js: ['JS: ошибки и пределы размера (ESLint)', () => runNodeTool('eslint/bin/eslint.js', ['.'])],
  css: [
    'CSS: правила и имена классов (Stylelint)',
    () => runNodeTool('stylelint/bin/stylelint.mjs', [`${THEME}/**/*.css`]),
  ],
  phpcs: ['PHP: безопасность, оформление, пределы размера (PHPCS)', checkPhpcs],
  boundary: ['Тема вызывает только публичные функции плагина', checkBoundary],
  budget: ['Тема: размер общей статики, без чужих серверов', checkThemeBudget],
  tests: ['Тесты бизнес-правил', checkTests],
  shell: ['Скрипты деплоя (shellcheck)', checkShell],
};

function main() {
  const wanted = process.argv.slice(2);
  const names = wanted.length ? wanted : Object.keys(CHECKS);
  const failed = [];
  for (const name of names) {
    if (!CHECKS[name]) {
      console.log(`Нет проверки «${name}». Есть: ${Object.keys(CHECKS).join(', ')}`);
      process.exit(2);
    }
    const [title, check] = CHECKS[name];
    console.log(`\n=== ${title}`);
    const outcome = check();
    if (outcome === false || (outcome === SKIPPED && IS_CI)) {
      failed.push(title);
    }
  }
  if (failed.length) {
    console.log(`\nНЕ ПРОШЛО:\n- ${failed.join('\n- ')}`);
    process.exit(1);
  }
  console.log('\nВсе проверки пройдены.');
}

main();
