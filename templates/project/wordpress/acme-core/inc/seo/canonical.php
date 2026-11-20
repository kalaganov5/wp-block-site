<?php
/**
 * Canonical страницы блога на неосновных языках.
 * Rank Math вместе с Polylang дописывал язык к адресу страницы записей второй раз (/en/articles/en/),
 * поэтому canonical списка статей строится из перевода страницы блога и номера страницы списка.
 */

defined( 'ABSPATH' ) || exit;

// Приоритет 99: после исправлений самого Rank Math для Polylang.
add_filter( 'rank_math/frontend/canonical', 'acme_seo_blog_canonical', 99 );

function acme_seo_blog_canonical( $canonical ) {
	$blog_page_id = is_home() ? acme_role_id( 'blog' ) : 0;
	if ( ! $blog_page_id ) {
		return $canonical;
	}
	$blog_url    = get_permalink( $blog_page_id );
	$page_number = (int) get_query_var( 'paged' );
	if ( $page_number < 2 ) {
		return $blog_url;
	}
	return trailingslashit( $blog_url ) . user_trailingslashit( 'page/' . $page_number, 'paged' );
}
