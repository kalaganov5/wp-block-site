// ESLint: ошибки и пределы читаемости для JS темы, плагина и инструментов. Оформление — Prettier.
// Пределы те же, что для PHP (phpcs.xml.dist). Зачем они — скилл wp-block-site, глава 15.
const js = require('@eslint/js');
const globals = require('globals');

const readability = {
  'max-lines': ['error', { max: 250, skipBlankLines: true, skipComments: true }],
  'max-lines-per-function': ['error', { max: 40, skipBlankLines: true, skipComments: true }],
  'max-depth': ['error', 3],
  'max-params': ['error', 3],
  'max-nested-callbacks': ['error', 3],
  complexity: ['error', 10],
  'no-var': 'error',
  'prefer-const': 'error',
  eqeqeq: ['error', 'always'],
  'id-length': ['error', { min: 2, exceptions: ['i', 'j', 'x', 'y', '_'] }],
  'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
};

module.exports = [
  {
    ignores: [
      'node_modules/',
      'vendor/',
      'seo-report/',
      'local-data/',
      'wordpress/acme-core/content/',
    ],
  },
  js.configs.recommended,
  {
    // Сайт и редактор: скрипты без сборки, выполняются в браузере.
    files: ['wordpress/acme/**/*.js', 'wordpress/acme-core/**/*.js'],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: 'script',
      globals: { ...globals.browser, wp: 'readonly', ACME: 'readonly' },
    },
    rules: readability,
  },
  {
    // Сборщики, QA и настройки: Node.
    files: ['wordpress/tools/**/*.js', 'wordpress/local/**/*.js', 'project/**/*.js', '*.js'],
    languageOptions: { ecmaVersion: 2023, sourceType: 'commonjs', globals: globals.node },
    rules: readability,
  },
  {
    // Скрипты QA выполняют функции внутри страницы (Playwright): там доступны document и window.
    files: ['wordpress/tools/qa/**/*.js'],
    languageOptions: { globals: { ...globals.node, ...globals.browser } },
  },
];
