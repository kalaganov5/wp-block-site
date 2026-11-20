<?php
/**
 * Импорт, шаг «медиа»: файлы из content/ загружаются в медиатеку.
 */

defined( 'ABSPATH' ) || exit;

/** Возвращает карту: ключ файла => [ id, url, sentinel ]. */
function acme_import_media( array $files ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$media = array();
	foreach ( $files as $file ) {
		$attachment_id         = acme_import_media_file( $file );
		$media[ $file['key'] ] = array(
			'id'       => $attachment_id,
			'url'      => wp_get_attachment_url( $attachment_id ),
			'sentinel' => (int) $file['sentinel'],
		);
	}
	return $media;
}

/** Загружает один файл и возвращает ID вложения. */
function acme_import_media_file( array $file ) {
	$source = ACME_CORE_DIR . '/content/' . $file['file'];
	// phpcs:ignore WordPress.WP.AlternativeFunctions -- локальный файл плагина, WP_Filesystem не нужен
	$upload = wp_upload_bits( basename( $source ), null, (string) file_get_contents( $source ) );
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::error( $file['file'] . ': ' . $upload['error'] );
	}
	$file_type     = wp_check_filetype( $upload['file'] );
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => $file['title'],
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	if ( 0 === strpos( (string) $file_type['type'], 'image/' ) ) {
		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $file['title'] ) );
	}
	update_post_meta( $attachment_id, 'acme_imported', 1 );
	update_post_meta( $attachment_id, 'acme_key', $file['key'] );
	return $attachment_id;
}
