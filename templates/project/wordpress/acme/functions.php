<?php
/**
 * Тема Acme Site: подключает части темы, сама ничего не выводит.
 *
 * inc/strings.php — подписи интерфейса на всех языках (acme_t), правка в «Языки → Перевод строк»;
 * inc/setup.php   — поддержка темы, меню, режим редактора (с шаблоном или без);
 * inc/assets.php  — стили и скрипты на сайте и в редакторе, тёмная тема без мигания;
 * inc/images.php  — вывод картинок медиатеки (размеры, srcset, lazy) и WebP при загрузке;
 * inc/core.php    — доступ к данным плагина acme-core (настройки, адреса) с запасным значением без него;
 * inc/markup.php  — общие куски разметки (иконки, хлебные крошки) для блоков;
 * inc/embeds.php  — лёгкие встраивания (YouTube по клику);
 * inc/blocks.php  — регистрация блоков из папки blocks/ и доработка разметки core-блоков;
 * inc/seo.php     — заголовок вкладки и описание, если не установлен Rank Math.
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_THEME_VERSION', wp_get_theme( get_template() )->get( 'Version' ) );

require __DIR__ . '/inc/strings.php';
require __DIR__ . '/inc/assets.php';
require __DIR__ . '/inc/setup.php';
require __DIR__ . '/inc/images.php';
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/markup.php';
require __DIR__ . '/inc/embeds.php';
require __DIR__ . '/inc/blocks.php';
require __DIR__ . '/inc/seo.php';
