<?php
/**
 * Обложка сайта для соцсетей и мессенджеров (og:image) — по одной на язык.
 * Файлы content/covers/site-<язык>.jpg (1200×630) загружаются в медиатеку один раз командой wp acme seo.
 * Обложка основного языка записывается в настройки Rank Math как изображение по умолчанию (options.php),
 * а на страницах других языков без своей картинки подставляется обложка этого языка.
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'facebook', 'twitter' ) as $acme_seo_network ) {
	add_action( "rank_math/opengraph/{$acme_seo_network}/add_additional_images", 'acme_seo_add_site_cover' );
}
unset( $acme_seo_network );

/** Rank Math зовёт это, когда собирает картинки страницы: своя картинка записи важнее обложки сайта. */
function acme_seo_add_site_cover( $image ) {
	if ( ! is_object( $image ) || ! method_exists( $image, 'has_images' ) || $image->has_images() ) {
		return;
	}
	$covers   = (array) get_option( 'acme_og_covers', array() );
	$cover_id = (int) ( $covers[ acme_lang() ] ?? ( $covers[ acme_main_lang() ] ?? 0 ) );
	if ( $cover_id ) {
		$image->add_image_by_id( $cover_id );
	}
}

/** Загружает обложки, которых ещё нет в медиатеке; возвращает [ язык => ID вложения ]. */
function acme_seo_site_covers() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$covers = array();
	foreach ( ACME_LANGS as $lang ) {
		$file_name = 'site-' . $lang . '.jpg';
		$file_path = ACME_CORE_DIR . '/content/covers/' . $file_name;
		if ( is_file( $file_path ) ) {
			$covers[ $lang ] = acme_seo_cover_attachment( $file_name, $file_path );
		}
	}
	$covers = array_filter( $covers );
	update_option( 'acme_og_covers', $covers, false );
	return $covers;
}

/** ID вложения обложки: уже загруженная находится по мете acme_cover, новая загружается. 0 — не удалось. */
function acme_seo_cover_attachment( $file_name, $file_path ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery -- разовая команда WP-CLI, не запрос страницы
			'meta_key'       => 'acme_cover',
			// phpcs:ignore WordPress.DB.SlowDBQuery -- разовая команда WP-CLI, не запрос страницы
			'meta_value'     => $file_name,
			'lang'           => '',
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions -- локальный файл плагина, WP_Filesystem не нужен
	$upload = wp_upload_bits( $file_name, null, (string) file_get_contents( $file_path ) );
	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}
	$attachment    = array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => get_bloginfo( 'name' ),
		'post_status'    => 'inherit',
	);
	$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
	if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( get_bloginfo( 'name' ) ) );
	update_post_meta( $attachment_id, 'acme_cover', $file_name );
	return (int) $attachment_id;
}
