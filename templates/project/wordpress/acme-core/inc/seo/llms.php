<?php
/**
 * llms.txt — описание сайта для ИИ-ассистентов (сам файл выводит модуль Rank Math «llms-txt»).
 *   - в списке нет закрытых от поиска записей (то же правило acme_seo_is_closed()) и юридических страниц;
 *   - описание записи — то же, что SEO-описание страницы, а не начало текста;
 *   - в конце — контакты и ссылки на версии сайта на других языках.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'rank_math/llms_txt/posts', 'acme_seo_llms_posts' );
add_filter( 'rank_math/llms_txt/post_description', 'acme_seo_llms_description', 10, 2 );
add_filter( 'rank_math/llms_txt/extra_content', 'acme_seo_llms_extra' );

/** Роли юридических страниц: ассистентам для ответа о компании они не нужны. */
function acme_seo_legal_roles() {
	return array( 'privacy' );
}

function acme_seo_llms_posts( $posts ) {
	return array_values( array_filter( (array) $posts, 'acme_seo_is_llms_post' ) );
}

function acme_seo_is_llms_post( $post ) {
	$post_id  = is_object( $post ) ? (int) $post->ID : (int) $post;
	$is_legal = in_array( acme_page_role( $post_id ), acme_seo_legal_roles(), true );
	return ! $is_legal && ! acme_seo_is_closed( $post_id );
}

function acme_seo_llms_description( $description, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return $description;
	}
	$lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID ) : null;
	$own  = acme_seo_post_description( $post, $lang ? $lang : null );
	return '' !== $own ? $own : acme_apply_prices( $description );
}

/** Контакты и другие языки; своё содержимое из настроек Rank Math не заменяется. */
function acme_seo_llms_extra( $extra ) {
	if ( '' !== trim( (string) $extra ) ) {
		return $extra;
	}
	$main_lang = acme_main_lang();
	$lines     = array( '## ' . acme_seo_string( 'llmsContacts', $main_lang ) );
	$contacts  = array(
		'Telegram' => acme_setting( 'telegram_url' ),
		'E-mail'   => acme_setting( 'email' ),
		acme_seo_string( 'navContacts', $main_lang ) => acme_role_url( 'contacts', $main_lang ),
	);
	foreach ( array_filter( $contacts ) as $label => $value ) {
		$lines[] = '- ' . $label . ': ' . $value;
	}
	$other_langs = array_slice( ACME_LANGS, 1 );
	if ( $other_langs ) {
		$lines[] = '';
		$lines[] = '## ' . acme_seo_string( 'llmsLanguages', $main_lang );
	}
	foreach ( $other_langs as $lang ) {
		$lines[] = '- ' . strtoupper( $lang ) . ': ' . acme_home_url( $lang );
	}
	return implode( "\n", $lines );
}
