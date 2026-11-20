<?php
/**
 * JSON-LD служебных страниц (главная, контакты, блог, «О нас»…): это не статьи.
 * У них остаётся узел WebPage; «О нас» — AboutPage (компания) или ProfilePage (эксперт) со ссылкой
 * mainEntity на Person/Organization сайта. Для обычных страниц тип разметки по умолчанию выключен
 * настройкой Rank Math (options.php), здесь — страховка на случай, если владелец включил его обратно.
 */

defined( 'ABSPATH' ) || exit;

/** Уточнённый тип узла WebPage для служебной страницы; '' — остаётся просто WebPage. */
function acme_seo_webpage_type( $role, $entity ) {
	if ( 'about' !== $role ) {
		return '';
	}
	return 'person' === $entity ? 'ProfilePage' : 'AboutPage';
}

/** Роль текущей страницы: у списка статей это страница блога. */
function acme_seo_current_role() {
	if ( is_home() ) {
		return 'blog';
	}
	return is_singular( 'page' ) ? acme_page_role( get_queried_object_id() ) : '';
}

function acme_seo_schema_page( array $data, array $context ) {
	$role = acme_seo_current_role();
	if ( '' === $role ) {
		return $data;
	}
	$snippet_types = (array) ( $data['richSnippet']['@type'] ?? array() );
	if ( array_intersect( $snippet_types, array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
		unset( $data['richSnippet'] );
	}
	$page_type = acme_seo_webpage_type( $role, ACME_SEO_ENTITY );
	if ( $page_type && $context['entity_id'] && isset( $data['WebPage'] ) ) {
		$data['WebPage']['@type']      = array( 'WebPage', $page_type );
		$data['WebPage']['mainEntity'] = array( '@id' => $context['entity_id'] );
	}
	return $data;
}
