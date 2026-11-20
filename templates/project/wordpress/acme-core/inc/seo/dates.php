<?php
/**
 * Дата изменения статьи для поисковиков: дата существенного обновления (поле acme_updated), иначе публикация.
 * Дата изменения WordPress (post_modified) не используется: она меняется от любой исправленной опечатки,
 * и поисковик перестаёт ей верить. Одно значение — в трёх местах: dateModified в JSON-LD,
 * og:updated_time и lastmod в карте сайта.
 */

defined( 'ABSPATH' ) || exit;

// Приоритет 99: после сборки графа (schema.php, 98).
add_filter( 'rank_math/json_ld', 'acme_seo_schema_dates', 99 );
add_filter( 'rank_math/opengraph/facebook/og_updated_time', 'acme_seo_og_modified' );
add_filter( 'rank_math/sitemap/entry', 'acme_seo_sitemap_lastmod', 20, 3 );

/** Дата существенного обновления статьи (ГГГГ-ММ-ДД) или '' — не обновлялась либо поле заполнено неверно. */
function acme_seo_updated_date( $post_id ) {
	$date = (string) get_post_meta( (int) $post_id, 'acme_updated', true );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
}

/** Дата изменения статьи в формате ISO 8601 по времени сайта. Полдень: дата без времени не «съезжает» в UTC. */
function acme_seo_modified_iso( $post ) {
	$updated = acme_seo_updated_date( $post->ID );
	if ( ! $updated ) {
		return get_the_date( 'c', $post );
	}
	return ( new DateTime( $updated . ' 12:00:00', wp_timezone() ) )->format( 'c' );
}

function acme_seo_schema_dates( $data ) {
	if ( ! is_array( $data ) || ! is_singular( 'post' ) ) {
		return $data;
	}
	$post        = get_queried_object();
	$dated_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage' );
	foreach ( $data as $key => $node ) {
		if ( is_array( $node ) && array_intersect( (array) ( $node['@type'] ?? array() ), $dated_types ) ) {
			$data[ $key ]['datePublished'] = get_the_date( 'c', $post );
			$data[ $key ]['dateModified']  = acme_seo_modified_iso( $post );
		}
	}
	return $data;
}

function acme_seo_og_modified( $content ) {
	return is_singular( 'post' ) ? acme_seo_modified_iso( get_queried_object() ) : $content;
}

/** Карта сайта: lastmod статьи в GMT, как у Rank Math. */
function acme_seo_sitemap_lastmod( $url, $type, $entry_object ) {
	// Rank Math передаёт не WP_Post, а строку своего запроса (stdClass) — проверяем поля.
	$is_post = 'post' === $type && is_object( $entry_object ) && 'post' === ( $entry_object->post_type ?? '' );
	if ( ! is_array( $url ) || ! $is_post ) {
		return $url;
	}
	$updated    = acme_seo_updated_date( $entry_object->ID );
	$url['mod'] = $updated ? get_gmt_from_date( $updated . ' 12:00:00' ) : $entry_object->post_date_gmt;
	return $url;
}
