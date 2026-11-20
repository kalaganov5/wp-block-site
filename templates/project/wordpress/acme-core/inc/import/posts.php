<?php
/**
 * Импорт, шаг «записи»: страницы, услуги и статьи на всех языках со связями переводов, полями и разделами.
 */

defined( 'ABSPATH' ) || exit;

/** Возвращает карту: [ключ записи][язык] => ID записи. */
function acme_import_posts( $posts, array $term_ids ) {
	acme_import_delete_privacy_draft();
	$post_ids = array();
	foreach ( $posts as $post_data ) {
		$translations = array();
		foreach ( $post_data['langs'] as $lang => $version ) {
			$post_id = acme_import_post( $post_data, $lang, $version );
			if ( ! empty( $post_data['topic'] ) && ! empty( $term_ids[ $post_data['topic'] ][ $lang ] ) ) {
				wp_set_object_terms( $post_id, array( $term_ids[ $post_data['topic'] ][ $lang ] ), 'acme_topic' );
			}
			$translations[ $lang ]                  = $post_id;
			$post_ids[ $post_data['key'] ][ $lang ] = $post_id;
		}
		if ( count( $translations ) > 1 && function_exists( 'pll_save_post_translations' ) ) {
			pll_save_post_translations( $translations );
		}
	}
	return $post_ids;
}

/** Черновик «Privacy Policy» нового WordPress занимает адрес /privacy-policy/. */
function acme_import_delete_privacy_draft() {
	$draft_ids = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'draft',
			'name'           => 'privacy-policy',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'lang'           => '',
		)
	);
	foreach ( $draft_ids as $draft_id ) {
		if ( ! get_post_meta( $draft_id, 'acme_imported', true ) ) {
			wp_delete_post( $draft_id, true );
		}
	}
}

/** Создаёт запись на одном языке ($version — её заголовок, слаг, текст и поля) и возвращает ID. */
function acme_import_post( array $post_data, $lang, array $version ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => $post_data['type'],
			'post_status'  => 'publish',
			'post_title'   => wp_slash( $version['title'] ),
			'post_name'    => $version['slug'],
			'post_content' => wp_slash( $version['content'] ?? '' ),
			'post_excerpt' => wp_slash( $version['excerpt'] ?? '' ),
			'menu_order'   => (int) ( $post_data['order'] ?? 0 ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::error( "{$post_data['type']}/{$post_data['key']}/$lang: " . $post_id->get_error_message() );
	}
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $post_id, $lang );
	}
	update_post_meta( $post_id, 'acme_imported', 1 );
	update_post_meta( $post_id, 'acme_key', $post_data['key'] );
	acme_import_post_meta( $post_id, (array) ( $version['meta'] ?? array() ) );
	acme_import_post_meta( $post_id, acme_import_seo_meta( $version ) );
	return $post_id;
}

/**
 * SEO-поля записи → поля Rank Math. Если Rank Math не установлен, те же поля читает тема (inc/seo.php темы).
 * Сборщик контента уже проверил их длину и уникальность (tools/lib/seo-rules.js).
 */
function acme_import_seo_meta( array $version ) {
	return array(
		'rank_math_title'         => (string) ( $version['seoTitle'] ?? '' ),
		'rank_math_description'   => (string) ( $version['seoDescription'] ?? '' ),
		'rank_math_focus_keyword' => (string) ( $version['focusKeyword'] ?? '' ),
	);
}

/** Поля записи; пустые не сохраняются, чтобы работали значения по умолчанию. */
function acme_import_post_meta( $post_id, array $meta ) {
	foreach ( $meta as $meta_key => $value ) {
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $post_id, $meta_key, is_string( $value ) ? wp_slash( $value ) : $value );
		}
	}
}
