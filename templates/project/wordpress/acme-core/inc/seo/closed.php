<?php
/**
 * Закрытые от поиска записи. Одно правило acme_seo_is_closed() — для трёх мест:
 * robots (noindex), карта сайта (адреса нет), llms.txt (в списке нет, см. llms.php).
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'rank_math/frontend/robots', 'acme_seo_robots' );
add_filter( 'rank_math/sitemap/entry', 'acme_seo_sitemap_entry', 10, 3 );

/**
 * Закрыта ли запись от поисковиков: непереведённая копия (текст пока на основном языке).
 * Новая причина закрыть страницу добавляется здесь — robots, карта сайта и llms.txt подхватят её сами.
 */
function acme_seo_is_closed( $post ) {
	$post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
	return $post_id && acme_is_untranslated( $post_id );
}

function acme_seo_is_open( $post ) {
	return ! acme_seo_is_closed( $post );
}

/** Rank Math заменяет wp_robots своим выводом — noindex сайта переносится туда. */
function acme_seo_robots( $robots ) {
	if ( is_singular() && acme_seo_is_closed( get_queried_object_id() ) ) {
		$robots['index'] = 'noindex';
	}
	return $robots;
}

/** Карта сайта: false убирает адрес закрытой записи. */
function acme_seo_sitemap_entry( $url, $type, $entry_object ) {
	return ( 'post' === $type && acme_seo_is_closed( $entry_object ) ) ? false : $url;
}
