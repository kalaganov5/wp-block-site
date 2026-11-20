<?php
/**
 * 301-редиректы со старых адресов. Таблица — опция acme_redirects { '/старый/путь/' => цель },
 * её заполняет импорт (раздел redirects в content.json) или перенос старого сайта.
 * Срабатывают только там, где WordPress отвечает 404: существующие страницы не перехватываются,
 * обычные страницы не тратят время на проверку.
 *
 * Цель хранится не адресом, а ссылкой: { post: ID } | { key: ключ записи acme_key } | { role: роль страницы } |
 * { archive: тип записи } | { url: путь }. Поэтому редирект не ломается, если поменять слаг или перезалить контент.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', 'acme_redirect_from_old_url', 1 );

/** Адрес по цели редиректа или ''. */
function acme_redirect_target_url( array $target ) {
	if ( ! empty( $target['post'] ) ) {
		return acme_redirect_published_url( (int) $target['post'] );
	}
	if ( ! empty( $target['key'] ) ) {
		return acme_redirect_url_by_key( $target['key'] );
	}
	if ( ! empty( $target['role'] ) ) {
		return acme_role_url( $target['role'], acme_main_lang() );
	}
	if ( ! empty( $target['archive'] ) ) {
		$archive_url = get_post_type_archive_link( $target['archive'] );
		return $archive_url ? $archive_url : '';
	}
	if ( ! empty( $target['url'] ) ) {
		return home_url( $target['url'] );
	}
	return '';
}

/** Адрес записи по ID; черновик или удалённая запись — '' (редирект в никуда хуже честной 404). */
function acme_redirect_published_url( $post_id ) {
	$post = get_post( $post_id );
	return ( $post && 'publish' === $post->post_status ) ? get_permalink( $post ) : '';
}

/** Адрес записи по стабильному ключу acme_key (версия на основном языке). */
function acme_redirect_url_by_key( $key ) {
	$post_ids = get_posts(
		array(
			'post_type'      => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- поиск по ключу нужен только на 404
			'meta_key'       => 'acme_key',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- то же: одна запись, только на 404
			'meta_value'     => $key,
			'lang'           => acme_main_lang(),
		)
	);
	return $post_ids ? get_permalink( (int) $post_ids[0] ) : '';
}

/** Новый адрес для старого пути или ''. */
function acme_redirect_resolve( $path ) {
	$redirects = get_option( 'acme_redirects' );
	if ( ! $redirects || ! is_array( $redirects ) ) {
		return '';
	}
	$path = strtolower( rawurldecode( (string) $path ) );
	$path = '/' . trim( preg_replace( '#/+#', '/', $path ), '/' ) . '/';
	if ( '//' === $path ) {
		return '';
	}
	return isset( $redirects[ $path ] ) ? acme_redirect_target_url( (array) $redirects[ $path ] ) : '';
}

function acme_redirect_from_old_url() {
	if ( ! is_404() ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- путь только сравнивается с ключами таблицы
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$new_url     = acme_redirect_resolve( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
	if ( $new_url ) {
		wp_safe_redirect( $new_url, 301, 'Acme Site' );
		exit;
	}
}
