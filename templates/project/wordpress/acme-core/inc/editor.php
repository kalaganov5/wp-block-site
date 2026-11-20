<?php
/**
 * Панели полей записи в редакторе (assets/meta-panel.js) по схеме acme_meta_schema().
 */

defined( 'ABSPATH' ) || exit;

add_action( 'enqueue_block_editor_assets', 'acme_enqueue_meta_panel' );

/** Записи типа для выпадающего списка: [ [ id, название ] ] на языке $lang. */
function acme_post_options( $type, $lang, $exclude = 0 ) {
	$posts   = get_posts(
		array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'lang'           => $lang,
			'exclude'        => $exclude ? array( $exclude ) : array(),
		)
	);
	$options = array();
	foreach ( $posts as $post ) {
		$options[] = array( $post->ID, wp_strip_all_tags( get_the_title( $post ) ) );
	}
	return $options;
}

function acme_enqueue_meta_panel() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->post_type ) ) {
		return;
	}
	$schema = acme_meta_schema();
	if ( empty( $schema[ $screen->post_type ] ) ) {
		return;
	}
	wp_enqueue_script(
		'acme-meta-panel',
		ACME_CORE_URL . 'assets/meta-panel.js',
		array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data' ),
		// Версия по изменению файла: редактор не держит старый скрипт в кеше.
		(string) filemtime( ACME_CORE_DIR . '/assets/meta-panel.js' ),
		true
	);
	acme_meta_panel_set_global( 'ACME_META', $schema[ $screen->post_type ] );

	// Списки для полей service / post — на языке записи.
	$post = get_post();
	$lang = acme_editor_post_lang( $post );
	acme_meta_panel_set_global( 'ACME_SERVICES', acme_post_options( 'acme_service', $lang ) );
	acme_meta_panel_set_global( 'ACME_POSTS', acme_post_options( 'post', $lang, $post ? $post->ID : 0 ) );
}

/** Данные для панели: глобальная переменная JS, объявленная до скрипта панели. */
function acme_meta_panel_set_global( $name, $value ) {
	wp_add_inline_script( 'acme-meta-panel', 'window.' . $name . ' = ' . wp_json_encode( $value ) . ';', 'before' );
}

/** Язык редактируемой записи; у новой записи языка ещё нет — Polylang передаёт его в ?new_lang=. */
function acme_editor_post_lang( $post ) {
	$lang = $post && function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID ) : '';
	if ( $lang ) {
		return $lang;
	}
	// phpcs:ignore WordPress.Security.NonceVerification -- только выбор языка списков, данные не меняются
	return isset( $_GET['new_lang'] ) ? sanitize_key( wp_unslash( $_GET['new_lang'] ) ) : acme_main_lang();
}
