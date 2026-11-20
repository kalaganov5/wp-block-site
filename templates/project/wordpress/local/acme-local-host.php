<?php
/**
 * Только локальный Docker (compose.yaml подключает файл в mu-plugins): сайт открывается по любому адресу —
 * http://localhost:8080 на компьютере и http://<IP компьютера>:8080 с телефона в той же сети.
 *
 * WordPress хранит свой адрес в базе и перенаправляет на него все запросы, а ссылки и картинки в текстах записаны
 * полными адресами. Здесь адрес сайта на время запроса подменяется на тот, по которому пришёл посетитель,
 * и в готовой странице старый адрес заменяется новым. База не меняется. На хостинг файл не попадает.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $_SERVER['HTTP_HOST'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

$acme_local_from = untrailingslashit( (string) get_option( 'home' ) );
$acme_local_to   = ( is_ssl() ? 'https://' : 'http://' ) . preg_replace( '/[^A-Za-z0-9.:\-\[\]]/', '', wp_unslash( $_SERVER['HTTP_HOST'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- очищено регуляркой выше

if ( '' === $acme_local_from || $acme_local_from === $acme_local_to ) {
	return;
}

$acme_local_url = function () use ( $acme_local_to ) {
	return $acme_local_to;
};
add_filter( 'option_home', $acme_local_url );
add_filter( 'option_siteurl', $acme_local_url );

// Полные адреса в текстах записей, настройках и разметке — на адрес запроса. JSON экранирует «/» как «\/».
ob_start(
	function ( $html ) use ( $acme_local_from, $acme_local_to ) {
		return str_replace(
			array( $acme_local_from, str_replace( '/', '\/', $acme_local_from ) ),
			array( $acme_local_to, str_replace( '/', '\/', $acme_local_to ) ),
			$html
		);
	}
);
