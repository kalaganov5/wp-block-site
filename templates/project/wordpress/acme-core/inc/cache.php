<?php
/**
 * Кеш страниц: очистка, когда меняется то, что видно на всех страницах.
 *
 * Плагин кеша — Cache Enabler: его ставит и настраивает деплой (wordpress/deploy/remote.sh, переменная
 * PAGE_CACHE), вручную настраивать не нужно. Он сам очищает кеш при правке записи, но не знает про цены,
 * настройки сайта, меню и строки интерфейса — они выводятся в шапке, подвале и блоках каждой страницы.
 * После их сохранения кеш чистится целиком. После деплоя кеш чистит remote.sh.
 *
 * Если проект заменил плагин кеша, очистка продолжит работать: поддерживаются также W3 Total Cache,
 * WP Super Cache и LiteSpeed Cache.
 */

defined( 'ABSPATH' ) || exit;

/** Очистить весь кеш страниц, какой плагин кеша ни стоит. Нет плагина — действия ничего не делают. */
function acme_flush_page_cache() {
	do_action( 'cache_enabler_clear_complete_cache' ); // Cache Enabler — плагин кеша каркаса
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache(); // WP Super Cache
	}
	do_action( 'litespeed_purge_all' ); // LiteSpeed Cache (хостинги на LiteSpeed ставят его сами)
	wp_cache_flush();
}

$acme_global_options = array(
	'acme_prices',
	'acme_settings',
	'acme_roles',
	'acme_media',
	'acme_legacy_map',
);
foreach ( $acme_global_options as $acme_opt ) {
	add_action( "update_option_{$acme_opt}", 'acme_flush_page_cache' );
}
unset( $acme_opt );

// Меню шапки и подвала, «Перевод строк» Polylang, настройки Rank Math.
add_action( 'wp_update_nav_menu', 'acme_flush_page_cache' );
add_action( 'update_option_polylang', 'acme_flush_page_cache' );
add_action( 'pll_save_strings_translations', 'acme_flush_page_cache' );
add_action( 'update_option_rank-math-options-titles', 'acme_flush_page_cache' );
add_action( 'update_option_rank-math-options-general', 'acme_flush_page_cache' );
