<?php
/**
 * Общие куски разметки для блоков темы: иконки и хлебные крошки.
 * Данные берутся из плагина acme-core через inc/core.php; без него блоки выводят пустоту
 * или значения по умолчанию, а не ошибку.
 */

defined( 'ABSPATH' ) || exit;

/** Иконка из набора темы (SVG, currentColor, декоративная). */
function acme_icon( $name, $size = 20 ) {
	static $paths = array(
		'menu'  => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
		'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'check' => '<path d="M5 12l5 5L20 7"/>',
		'sun'   => '<circle cx="12" cy="12" r="4"/>'
			. '<path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4'
			. 'M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'  => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="acme-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
			. ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. '%2$s</svg>',
		(int) $size,
		$paths[ $name ]
	);
}

/**
 * Хлебные крошки текущей страницы: [ [ подпись, адрес ], … ], у последней адреса нет.
 * Те же данные использует разметка BreadcrumbList (плагин, inc/seo.php).
 */
function acme_breadcrumb_items() {
	if ( is_front_page() ) {
		return array();
	}
	$home = array( acme_t( 'crumbHome' ), acme_home() );
	return array_merge( array( $home ), acme_breadcrumb_tail() );
}

/** Крошки после «Главной» — по виду текущей страницы. Неизвестный вид страницы — только «Главная». */
function acme_breadcrumb_tail() {
	if ( is_singular() ) {
		return acme_singular_breadcrumbs();
	}
	if ( is_post_type_archive() ) {
		return array( array( post_type_archive_title( '', false ), '' ) );
	}
	if ( is_category() ) {
		return array(
			array( acme_t( 'navBlog' ), acme_url( 'blog' ) ),
			array( single_cat_title( '', false ), '' ),
		);
	}
	if ( is_home() ) {
		return array( array( acme_t( 'navBlog' ), '' ) );
	}
	if ( is_404() ) {
		return array( array( acme_t( 'notFoundH1' ), '' ) );
	}
	return array();
}

/** Крошки записи: раздел (блог с рубрикой или архив своего типа), затем сама запись. */
function acme_singular_breadcrumbs() {
	$items     = array();
	$post_type = get_post_type();
	if ( is_singular( 'post' ) ) {
		$items[]    = array( acme_t( 'navBlog' ), acme_url( 'blog' ) );
		$categories = get_the_category();
		if ( $categories ) {
			$items[] = array( $categories[0]->name, get_category_link( $categories[0] ) );
		}
	} elseif ( 'page' !== $post_type && get_post_type_archive_link( $post_type ) ) {
		$type_object = get_post_type_object( $post_type );
		$items[]     = array( $type_object->labels->name, get_post_type_archive_link( $post_type ) );
	}
	$items[] = array( get_the_title(), '' );
	return $items;
}
