<?php
/**
 * Polylang: переводимые типы записей и разделы, язык текущей страницы;
 * непереведённые копии закрыты от индексации и не указаны в hreflang.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pll_get_post_types', 'acme_polylang_post_types' );
add_filter( 'pll_get_taxonomies', 'acme_polylang_taxonomies' );
add_filter( 'wp_robots', 'acme_untranslated_robots' );
add_filter( 'pll_rel_hreflang_attributes', 'acme_untranslated_hreflang' );

function acme_polylang_post_types( $types ) {
	$types['acme_service'] = 'acme_service';
	return $types;
}

function acme_polylang_taxonomies( $taxonomies ) {
	$taxonomies['acme_topic'] = 'acme_topic';
	return $taxonomies;
}

/**
 * Язык страницы по Polylang или '' (Polylang не активен либо язык не из ACME_LANGS).
 * Вне основного запроса (REST, превью блока) текущего языка нет — тогда берётся язык записи.
 */
function acme_polylang_lang() {
	if ( ! function_exists( 'pll_current_language' ) ) {
		return '';
	}
	$lang = pll_current_language();
	if ( $lang && in_array( $lang, ACME_LANGS, true ) ) {
		return $lang;
	}
	$post = get_post();
	if ( ! $post || ! function_exists( 'pll_get_post_language' ) ) {
		return '';
	}
	$lang = pll_get_post_language( $post->ID );
	return ( $lang && in_array( $lang, ACME_LANGS, true ) ) ? $lang : '';
}

/** Копия записи с флажком «Текст ещё не переведён». */
function acme_is_untranslated( $post_id ) {
	return (bool) get_post_meta( (int) $post_id, 'acme_untranslated', true );
}

/** Непереведённая копия: noindex, follow (навигация работает, в поиск не попадает). */
function acme_untranslated_robots( $robots ) {
	if ( is_singular() && acme_is_untranslated( get_queried_object_id() ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}

/** hreflang не указывает на непереведённые копии. */
function acme_untranslated_hreflang( $hreflangs ) {
	if ( ! is_singular() || ! function_exists( 'pll_get_post' ) ) {
		return $hreflangs;
	}
	$post_id = get_queried_object_id();
	foreach ( array_keys( $hreflangs ) as $code ) {
		$translation_id = pll_get_post( $post_id, substr( $code, 0, 2 ) );
		if ( $translation_id && acme_is_untranslated( $translation_id ) ) {
			unset( $hreflangs[ $code ] );
		}
	}
	return $hreflangs;
}
