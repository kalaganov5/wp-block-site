<?php
/**
 * Импорт, шаг --force: удаляет только то, что создал прошлый импорт (мета acme_imported).
 * Записи, меню и файлы, созданные владельцем, остаются.
 */

defined( 'ABSPATH' ) || exit;

function acme_import_cleanup() {
	// «any» не возвращает типы, скрытые из поиска (формы CF7, пункты меню): служебные типы запрашиваются явно.
	$post_ids = array_merge(
		acme_import_imported_post_ids( 'any' ),
		acme_import_imported_post_ids( array( 'attachment', 'wpcf7_contact_form', 'nav_menu_item' ) )
	);
	foreach ( array_unique( $post_ids ) as $post_id ) {
		if ( 'attachment' === get_post_type( $post_id ) ) {
			wp_delete_attachment( $post_id, true );
		} else {
			wp_delete_post( $post_id, true );
		}
	}
	acme_import_delete_topics();
	acme_import_delete_menus();
	WP_CLI::log( 'Удалено импортированного: ' . count( $post_ids ) );
}

/** ID записей с метой acme_imported на всех языках (lang = '' отключает фильтр Polylang). */
function acme_import_imported_post_ids( $post_types ) {
	return get_posts(
		array(
			'post_type'      => $post_types,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- разовая команда WP-CLI, не запрос страницы
			'meta_key'       => 'acme_imported',
			'lang'           => '',
		)
	);
}

/** Разделы услуг удаляются все: импорт создаёт их заново из content.json. */
function acme_import_delete_topics() {
	$topics = get_terms(
		array(
			'taxonomy'   => 'acme_topic',
			'hide_empty' => false,
			'lang'       => '',
		)
	);
	foreach ( (array) $topics as $topic ) {
		wp_delete_term( $topic->term_id, 'acme_topic' );
	}
}

function acme_import_delete_menus() {
	foreach ( wp_get_nav_menus() as $menu ) {
		if ( get_term_meta( $menu->term_id, 'acme_imported', true ) ) {
			wp_delete_nav_menu( $menu->term_id );
		}
	}
}
