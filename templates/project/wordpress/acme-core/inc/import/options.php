<?php
/**
 * Импорт, шаг «настройки сайта»: роли служебных страниц, главная и страница блога, название сайта,
 * настройки плагина по умолчанию, таблица редиректов.
 */

defined( 'ABSPATH' ) || exit;

function acme_import_options( array $data, array $post_ids ) {
	$roles = acme_import_roles( $data['posts'], $post_ids );
	update_option( 'acme_roles', $roles );
	acme_import_reading_options( $roles );
	if ( function_exists( 'PLL' ) && method_exists( PLL()->model, 'clean_languages_cache' ) ) {
		PLL()->model->clean_languages_cache(); // Polylang хранит главную и страницу записей в кеше списка языков
	}
	update_option( 'blogname', $data['site']['name'] );
	update_option( 'blogdescription', $data['site']['description'] );
	// Настройки, уже сохранённые владельцем, импорт не перезаписывает. Начальные значения — site.settings
	// исходных данных (регион работы, адрес, профили): только известные ключи, остальное по умолчанию.
	if ( ! get_option( 'acme_settings' ) ) {
		$defaults = acme_settings_defaults();
		$initial  = array_intersect_key( (array) ( $data['site']['settings'] ?? array() ), $defaults );
		update_option( 'acme_settings', array_merge( $defaults, $initial ) );
	}
	update_option( 'acme_redirects', (array) ( $data['redirects'] ?? array() ) );
}

/** Роли служебных страниц: роль => ID записи на основном языке (переводы подбирает acme_role_id()). */
function acme_import_roles( $posts, array $post_ids ) {
	$main_lang = acme_main_lang();
	$roles     = array();
	foreach ( $posts as $post_data ) {
		if ( ! empty( $post_data['role'] ) ) {
			$roles[ $post_data['role'] ] = $post_ids[ $post_data['key'] ][ $main_lang ] ?? 0;
		}
	}
	return $roles;
}

/** Настройки WordPress, которые ссылаются на служебные страницы. */
function acme_import_reading_options( array $roles ) {
	if ( ! empty( $roles['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $roles['home'] );
	}
	if ( ! empty( $roles['blog'] ) ) {
		update_option( 'page_for_posts', $roles['blog'] );
	}
	if ( ! empty( $roles['privacy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', $roles['privacy'] );
	}
}
