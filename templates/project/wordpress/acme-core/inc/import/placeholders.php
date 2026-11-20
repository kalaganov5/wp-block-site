<?php
/**
 * Импорт, шаг «ссылки в текстах»: метки {{url:post:ключ}}, {{id:post:ключ}}, {{url:media:ключ}},
 * {{url:archive:тип}} и временные ID картинок → настоящие значения.
 * Выполняется после создания всех записей: адрес и ID становятся известны только тогда.
 */

defined( 'ABSPATH' ) || exit;

function acme_import_resolve_content( array $post_ids, array $media ) {
	foreach ( $post_ids as $ids_by_lang ) {
		foreach ( $ids_by_lang as $lang => $post_id ) {
			acme_import_resolve_post( $post_id, $lang, $post_ids, $media );
		}
	}
}

/** Заменяет метки в тексте одной записи; запись обновляется, только если текст изменился. */
function acme_import_resolve_post( $post_id, $lang, array $post_ids, array $media ) {
	$content  = (string) get_post_field( 'post_content', $post_id, 'raw' );
	$resolved = preg_replace_callback(
		'/\{\{(url|id):(post|media|archive):([a-z0-9_-]+)\}\}/',
		function ( $matches ) use ( $lang, $post_ids, $media ) {
			return acme_import_placeholder_value( $matches, $lang, $post_ids, $media );
		},
		$content
	);
	foreach ( $media as $file ) {
		// Сборщик контента ставит в разметку картинок временный ID (sentinel): настоящего до загрузки нет.
		$resolved = str_replace(
			array( '"id":' . $file['sentinel'], 'wp-image-' . $file['sentinel'] ),
			array( '"id":' . $file['id'], 'wp-image-' . $file['id'] ),
			$resolved
		);
	}
	if ( $resolved === $content ) {
		return;
	}
	wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $resolved ),
		)
	);
}

/** ID или адрес загруженного файла; файла с таким ключом нет — '0' или пустой адрес. */
function acme_import_media_value( array $file, $is_id ) {
	return $is_id ? (string) ( $file['id'] ?? 0 ) : (string) ( $file['url'] ?? '' );
}

/** Значение метки: $matches — [ метка, url|id, post|media|archive, ключ ]. */
function acme_import_placeholder_value( array $matches, $lang, array $post_ids, array $media ) {
	list( $placeholder, $what, $kind, $key ) = $matches;
	$is_id                                   = 'id' === $what;
	if ( 'media' === $kind ) {
		return acme_import_media_value( $media[ $key ] ?? array(), $is_id );
	}
	if ( 'archive' === $kind ) {
		$archive_url = get_post_type_archive_link( $key );
		return $archive_url ? $archive_url : '#';
	}
	// Записи на этом языке нет — ссылка ведёт на версию основного языка.
	$post_id = $post_ids[ $key ][ $lang ] ?? ( $post_ids[ $key ][ acme_main_lang() ] ?? 0 );
	if ( ! $post_id ) {
		WP_CLI::warning( "Не найдено: {$placeholder} ($lang)" );
		return $is_id ? '0' : '#';
	}
	return $is_id ? (string) $post_id : get_permalink( $post_id );
}
