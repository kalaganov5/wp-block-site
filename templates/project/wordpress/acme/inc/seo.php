<?php
/**
 * Заголовок вкладки и описание. SEO-поля хранятся в полях Rank Math (rank_math_title, rank_math_description):
 * если Rank Math установлен, он выводит их сам; если нет — тема берёт те же поля.
 */

defined( 'ABSPATH' ) || exit;

function acme_has_rank_math() {
	return class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
}

add_filter( 'pre_get_document_title', 'acme_document_title' );
add_action( 'wp_head', 'acme_print_meta_description', 2 );

function acme_document_title( $title ) {
	if ( acme_has_rank_math() ) {
		return $title;
	}
	if ( is_singular() ) {
		$seo_title = get_post_meta( get_queried_object_id(), 'rank_math_title', true );
		// Знак % — переменные Rank Math (%title% и т. п.): без него их некому подставить.
		if ( $seo_title && false === strpos( $seo_title, '%' ) ) {
			return $seo_title;
		}
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		$blog_page_id = (int) get_option( 'page_for_posts' );
		if ( function_exists( 'pll_get_post' ) ) {
			$blog_page_id = pll_get_post( $blog_page_id );
		}
		$seo_title = $blog_page_id ? get_post_meta( $blog_page_id, 'rank_math_title', true ) : '';
		if ( $seo_title ) {
			return $seo_title;
		}
	}
	if ( is_post_type_archive( 'acme_service' ) ) {
		return acme_t( 'titleServices' );
	}
	return $title;
}

function acme_print_meta_description() {
	if ( acme_has_rank_math() || ! is_singular() ) {
		return;
	}
	$description = get_post_meta( get_queried_object_id(), 'rank_math_description', true );
	if ( function_exists( 'acme_apply_prices' ) ) {
		$description = acme_apply_prices( $description );
	}
	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">' . "\n";
	}
}
