<?php
/**
 * Импорт, шаг «языки и адреса»: языки Polylang и его настройки, структура постоянных ссылок.
 * Основной язык — без префикса в адресе, остальные — папками; язык браузера не определяется (кеш страниц).
 */

defined( 'ABSPATH' ) || exit;

function acme_import_languages( $languages ) {
	if ( ! function_exists( 'PLL' ) ) {
		return;
	}
	$model          = PLL()->model;
	$existing_slugs = wp_list_pluck( $model->get_languages_list(), 'slug' );
	foreach ( $languages as $order => $language ) {
		if ( ! in_array( $language['slug'], $existing_slugs, true ) ) {
			acme_import_add_language( $model, $language, $order );
		}
	}
	if ( method_exists( $model, 'clean_languages_cache' ) ) {
		$model->clean_languages_cache();
	}
	acme_import_polylang_settings();
}

function acme_import_add_language( $model, array $language, $order ) {
	$args = array(
		'name'       => $language['name'],
		'slug'       => $language['slug'],
		'locale'     => $language['locale'],
		'rtl'        => false,
		'term_group' => $order,
		'flag'       => $language['flag'],
	);
	// Polylang 3.7+ добавляет язык через объект languages, старые версии — методом модели.
	$has_languages_api = isset( $model->languages ) && is_object( $model->languages )
		&& method_exists( $model->languages, 'add' );
	$result            = $has_languages_api ? $model->languages->add( $args ) : $model->add_language( $args );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Язык ' . $language['slug'] . ': ' . $result->get_error_message() );
	}
	WP_CLI::log( 'Язык добавлен: ' . $language['slug'] );
}

function acme_import_polylang_settings() {
	$settings = array(
		'default_lang'  => acme_main_lang(),
		'hide_default'  => true,
		'force_lang'    => 1,
		'rewrite'       => true,
		'browser'       => false,
		'redirect_lang' => true,
		'media_support' => false,
	);
	$options  = PLL()->options;
	if ( is_object( $options ) && $options instanceof ArrayAccess ) { // Polylang 3.7+
		foreach ( $settings as $name => $value ) {
			$options[ $name ] = $value;
		}
		if ( method_exists( $options, 'save' ) ) {
			$options->save();
		}
		return;
	}
	// Старый Polylang хранит настройки массивом в опции, флажки — числами 0/1.
	$legacy_options = array_merge(
		(array) get_option( 'polylang', array() ),
		array_map( 'acme_import_bool_to_int', $settings )
	);
	update_option( 'polylang', $legacy_options );
	PLL()->options = $legacy_options;
}

function acme_import_bool_to_int( $value ) {
	return is_bool( $value ) ? (int) $value : $value;
}

function acme_import_permalinks() {
	if ( ACME_PERMALINKS !== get_option( 'permalink_structure' ) ) {
		$GLOBALS['wp_rewrite']->set_permalink_structure( ACME_PERMALINKS );
	}
}
