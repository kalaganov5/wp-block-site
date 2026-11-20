<?php
/**
 * JSON-LD поверх разметки Rank Math: кто стоит за сайтом, автор статей и хлебные крошки.
 * Остальные части графа — в соседних файлах, здесь только порядок их применения:
 *   schema-pages.php   — служебные страницы без Article, «О нас» — AboutPage / ProfilePage;
 *   schema-service.php — Service на страницах услуг;
 *   schema-faq.php     — FAQPage из блока «Вопросы и ответы»;
 *   local-business.php — адрес, телефон и часы работы организации;
 *   dates.php          — dateModified статьи (подключается своим фильтром).
 *
 * Разметку HowTo сайт не выводит: Google убрал её из выдачи в 2023 году.
 */

defined( 'ABSPATH' ) || exit;

// Приоритет 98: после того как Rank Math и его модули собрали свой граф, но до подстановки цен (99, prices.php).
add_filter( 'rank_math/json_ld', 'acme_seo_json_ld', 98 );
// У страниц и услуг тип разметки по умолчанию выключен (options.php), а без него Rank Math не выводит и общие
// узлы: организацию, WebSite и WebPage. Фильтр возвращает их на все страницы.
add_filter( 'rank_math/schema/add_global_entities', '__return_true' );

function acme_seo_json_ld( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$context = acme_seo_schema_context( $data );
	$data    = acme_seo_schema_entity( $data, $context );
	$data    = acme_seo_schema_author( $data, $context );
	$data    = acme_seo_schema_page( $data, $context );
	$data    = acme_seo_schema_service( $data, $context );
	$data    = acme_seo_schema_faq( $data, $context );
	return acme_seo_schema_breadcrumbs( $data, $context );
}

/**
 * Что известно о текущей странице, нужное всем частям графа:
 * page_url, post_id (0 — не запись), entity_key и entity_id — узел Person/Organization сайта в графе Rank Math.
 */
function acme_seo_schema_context( array $data ) {
	$post_id = is_singular() ? (int) get_queried_object_id() : 0;
	$context = array(
		'post_id'    => $post_id,
		'page_url'   => $post_id ? get_permalink( $post_id ) : home_url( add_query_arg( array() ) ),
		'entity_key' => '',
		'entity_id'  => '',
	);
	foreach ( $data as $key => $node ) {
		$node_id = is_array( $node ) && isset( $node['@id'] ) ? (string) $node['@id'] : '';
		if ( preg_match( '/#(person|organization)$/', $node_id ) ) {
			$context['entity_key'] = $key;
			$context['entity_id']  = $node_id;
		}
	}
	return $context;
}

/** Ссылки на профили организации из настроек сайта: без повторов, только адреса http(s). */
function acme_seo_profile_urls( array $settings ) {
	$text = ( $settings['telegram_url'] ?? '' ) . ' ' . ( $settings['social_urls'] ?? '' );
	$urls = array();
	foreach ( preg_split( '/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY ) as $url ) {
		if ( preg_match( '#^https?://#i', $url ) ) {
			$urls[] = $url;
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Узел Person/Organization: страница «О нас», описание, почта, способ связи, профили.
 * Данные читаются из настроек при каждом выводе: владелец меняет их в админке без деплоя.
 */
function acme_seo_schema_entity( array $data, array $context ) {
	$key = $context['entity_key'];
	if ( '' === $key ) {
		return $data;
	}
	$settings = (array) get_option( 'acme_settings', array() );
	$profiles = array_merge( (array) ( $data[ $key ]['sameAs'] ?? array() ), acme_seo_profile_urls( $settings ) );
	$node     = array(
		'url'          => acme_role_url( 'about' ),
		'description'  => get_bloginfo( 'description' ),
		'email'        => acme_setting( 'email' ),
		'sameAs'       => array_values( array_unique( $profiles ) ),
		'contactPoint' => array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'customer support',
			'url'               => acme_role_url( 'contacts' ),
			'availableLanguage' => ACME_LANGS,
		),
	);
	if ( 'company' === ACME_SEO_ENTITY ) {
		$node = array_merge( $node, acme_seo_local_business( $settings ) );
	}
	$data[ $key ] = array_merge( $data[ $key ], array_filter( $node ) );
	return $data;
}

/**
 * Автор статей — тот же Person/Organization сайта, а не учётная запись WordPress («admin»);
 * headline — название записи без хвоста « | Имя сайта».
 */
function acme_seo_schema_author( array $data, array $context ) {
	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		$is_author_node = isset( $node['@id'] ) && '#author' === substr( (string) $node['@id'], -7 );
		if ( $context['entity_id'] && $is_author_node ) {
			unset( $data[ $key ] );
			continue;
		}
		if ( $context['entity_id'] && isset( $node['author'] ) ) {
			$data[ $key ]['author'] = array(
				'@id'  => $context['entity_id'],
				'name' => get_bloginfo( 'name' ),
			);
		}
		if ( $context['post_id'] && isset( $node['headline'] ) ) {
			$data[ $key ]['headline'] = wp_strip_all_tags( get_the_title( $context['post_id'] ) );
		}
	}
	return $data;
}

/** Хлебные крошки — те же, что на странице (тема, acme_breadcrumb_items()). */
function acme_seo_schema_breadcrumbs( array $data, array $context ) {
	$crumbs = function_exists( 'acme_breadcrumb_items' ) ? acme_breadcrumb_items() : array();
	if ( count( $crumbs ) <= 1 ) {
		return $data;
	}
	$list_items = array();
	foreach ( $crumbs as $i => $crumb ) {
		$list_items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => wp_strip_all_tags( $crumb[0] ),
			// У последней крошки (текущая страница) ссылки нет — подставляем адрес страницы.
			'item'     => empty( $crumb[1] ) ? $context['page_url'] : $crumb[1],
		);
	}
	$breadcrumb_id           = $context['page_url'] . '#breadcrumb';
	$data['acmeBreadcrumbs'] = array(
		'@type'           => 'BreadcrumbList',
		'@id'             => $breadcrumb_id,
		'itemListElement' => $list_items,
	);
	if ( isset( $data['WebPage'] ) && is_array( $data['WebPage'] ) ) {
		$data['WebPage']['breadcrumb'] = array( '@id' => $breadcrumb_id );
	}
	return $data;
}
