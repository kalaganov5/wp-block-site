<?php
/**
 * Импорт, шаг «разделы услуг»: термины acme_topic на всех языках со связями переводов.
 */

defined( 'ABSPATH' ) || exit;

/** Возвращает карту: [ключ раздела][язык] => ID термина. */
function acme_import_topics( array $topics ) {
	$term_ids = array();
	foreach ( $topics as $topic ) {
		$translations = array();
		foreach ( $topic['names'] as $lang => $name ) {
			$term_id                            = acme_import_topic( $topic, $lang, $name );
			$translations[ $lang ]              = $term_id;
			$term_ids[ $topic['key'] ][ $lang ] = $term_id;
		}
		if ( count( $translations ) > 1 && function_exists( 'pll_save_term_translations' ) ) {
			pll_save_term_translations( $translations );
		}
	}
	return $term_ids;
}

/** Создаёт раздел на одном языке и возвращает ID термина. */
function acme_import_topic( array $topic, $lang, $name ) {
	// Слаг термина уникален на весь сайт: у переводов — суффикс языка.
	$slug   = acme_main_lang() === $lang ? $topic['key'] : $topic['key'] . '-' . $lang;
	$result = wp_insert_term( $name, 'acme_topic', array( 'slug' => $slug ) );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( 'Раздел ' . $slug . ': ' . $result->get_error_message() );
	}
	$term_id = (int) $result['term_id'];
	update_term_meta( $term_id, 'acme_key', $topic['key'] );
	update_term_meta( $term_id, 'acme_order', (int) ( $topic['order'] ?? 0 ) );
	if ( function_exists( 'pll_set_term_language' ) ) {
		pll_set_term_language( $term_id, $lang );
	}
	return $term_id;
}
