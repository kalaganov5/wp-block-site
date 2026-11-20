#!/usr/bin/env node
/*
 * Подключает хуки репозитория (.githooks) — запускается сам при `npm install` (скрипт prepare).
 * Вне git-репозитория (архив, контейнер) ничего не делает.
 */
const { execFileSync } = require('node:child_process');
const { existsSync } = require('node:fs');

if (existsSync('.git')) {
  execFileSync('git', ['config', 'core.hooksPath', '.githooks'], { stdio: 'inherit' });
}
