<?php
/**
 * Plugin Name: Acme Site — данные сайта
 * Description: Типы записей и поля, настройки сайта, заявки, SEO, аналитика, редиректы, импорт. Нужна тема acme.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Acme
 * Text Domain: acme-core
 * License: GPL-2.0-or-later
 */

// Минимальные версии (шапка выше). PHP 8.3 — её требует Contact Form 7 (6.2.5), без которого сайт не ставится.
// WordPress — предыдущая основная ветка относительно текущей (сейчас 7.1 → 7.0); сам деплой ставит свежую.
// Менять вместе: style.css темы, phpcs.xml.dist (testVersion, minimum_wp_version), MIN_PHP_ID в
// wordpress/deploy/lib.sh и версии PHP в .github/workflows/ci.yml.

defined( 'ABSPATH' ) || exit;

define( 'ACME_CORE_VERSION', '1.0.0' );
define( 'ACME_CORE_DIR', __DIR__ );
define( 'ACME_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'ACME_PERMALINKS', '/blog/%postname%/' ); // статьи — /blog/<slug>/, страницы и услуги — без префикса
const ACME_LANGS = array( 'ru', 'en' ); // первый — основной язык (без префикса в адресе)

// Публичный API для темы — первым: остальные файлы плагина тоже им пользуются.
require __DIR__ . '/inc/api.php';
require __DIR__ . '/inc/post-types.php';
require __DIR__ . '/inc/settings.php';
require __DIR__ . '/inc/editor.php';
require __DIR__ . '/inc/polylang.php';
require __DIR__ . '/inc/prices.php';
require __DIR__ . '/inc/prices-admin.php';
require __DIR__ . '/inc/leads.php';
require __DIR__ . '/inc/analytics.php';
require __DIR__ . '/inc/redirects.php';
require __DIR__ . '/inc/slugs.php';
// SEO: один файл — одна задача; обзор и порядок чтения — в шапке inc/seo/rank-math.php.
$acme_seo_modules = array(
	'rank-math',
	'onboarding',
	'options',
	'indexnow',
	'verification',
	'social',
	'titles',
	'descriptions',
	'canonical',
	'closed',
	'schema',
	'schema-pages',
	'schema-service',
	'schema-faq',
	'local-business',
	'dates',
	'llms',
	'robots',
	'status',
);
foreach ( $acme_seo_modules as $acme_seo_module ) {
	require __DIR__ . '/inc/seo/' . $acme_seo_module . '.php';
}
unset( $acme_seo_modules, $acme_seo_module );
require __DIR__ . '/inc/cache.php';
require __DIR__ . '/inc/security.php';

// Команды WP-CLI (wp acme seo, wp acme import) обычным запросам страниц не нужны.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require __DIR__ . '/inc/seo/cli.php';
	$acme_import_steps = array(
		'cleanup',
		'languages',
		'media',
		'terms',
		'posts',
		'placeholders',
		'options',
		'forms',
		'menus',
		'cli',
	);
	foreach ( $acme_import_steps as $acme_import_step ) {
		require __DIR__ . '/inc/import/' . $acme_import_step . '.php';
	}
	unset( $acme_import_steps, $acme_import_step );
}

register_activation_hook( __FILE__, 'acme_activate' );

/** При активации типы записей регистрируются сразу, чтобы сброс ЧПУ учёл их адреса. */
function acme_activate() {
	acme_register_content_types();
	flush_rewrite_rules();
}
