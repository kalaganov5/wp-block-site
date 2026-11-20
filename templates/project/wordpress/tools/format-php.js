#!/usr/bin/env node
/*
 * Чинит оформление PHP темы и плагина (phpcbf по правилам phpcs.xml.dist): npm run format.
 * Отдельный скрипт, потому что phpcbf отвечает кодом 1, когда что-то исправил, — для npm это не ошибка.
 * Нет vendor/ — подсказка выполнить composer install.
 */
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const PHPCBF = path.join(ROOT, 'vendor', 'bin', 'phpcbf');
// Коды phpcbf: 0 — нечего чинить, 1 — исправлено, 2 и больше — осталось то, что нужно править руками.
const FIXED_OR_CLEAN = [0, 1];

if (!fs.existsSync(PHPCBF)) {
  console.log('Нет vendor/: выполните composer install — PHP не отформатирован');
  process.exit(0);
}
const result = spawnSync('php', [PHPCBF, '-q'], { cwd: ROOT, stdio: 'inherit' });
if (!FIXED_OR_CLEAN.includes(result.status)) {
  console.log('phpcbf исправил не всё: остальное покажет npm run check');
}
process.exit(0);
