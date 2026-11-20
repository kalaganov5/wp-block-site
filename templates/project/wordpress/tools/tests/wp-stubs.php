<?php
/**
 * Минимальные заглушки WordPress для тестов бизнес-правил.
 *
 * Здесь только то, что нужно, чтобы файл плагина загрузился и чистая функция отработала. Если тесту
 * понадобилась новая функция WordPress — добавьте простую заглушку сюда. Если заглушек нужно много, значит,
 * правило не отделено от WordPress: вынесите его в чистую функцию (скилл wp-block-site, глава 15).
 */

define( 'ABSPATH', __DIR__ . '/' );
// Та же константа, что объявляет сам плагин (acme-core.php).
define( 'ACME_CORE_DIR', dirname( __DIR__, 2 ) . '/acme-core/' );

/** Опции сайта для теста: $GLOBALS['acme_test_options']['имя'] = значение. */
$GLOBALS['acme_test_options'] = array();

function add_filter() {
	return true;
}

function add_action() {
	return true;
}

function apply_filters( $hook, $value ) {
	return $value;
}

function get_option( $name, $fallback = false ) {
	return $GLOBALS['acme_test_options'][ $name ] ?? $fallback;
}

function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- это и есть заглушка
}

function strip_shortcodes( $text ) {
	return preg_replace( '/\[[^\]]+\]/', '', (string) $text );
}

function is_admin() {
	return false;
}
