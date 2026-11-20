<?php
/**
 * Заголовок страницы (title) поверх шаблонов Rank Math.
 *
 * У записи со своим SEO-заголовком Rank Math выводит его как есть. Здесь — то, чего шаблоны не умеют:
 *   - архив услуг и страница 404 — подписи интерфейса на языке страницы (у Rank Math одна строка на все языки);
 *   - вторая и следующие страницы списков — номер страницы, иначе у них одинаковый заголовок;
 *   - слишком длинный заголовок теряет хвост с именем сайта.
 * Правила — чистые функции (тесты: tools/tests/seo-titles.test.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Предел длины заголовка. Google показывает около 600 px — это примерно 60 знаков, длиннее обрежет
 * многоточием. То же число проверяет сборщик контента (tools/lib/seo-rules.js, TITLE_MAX).
 */
const ACME_SEO_TITLE_MAX = 60;

/** Короче 10 знаков заголовок без имени сайта — одно слово («Блог»): хвост лучше оставить. */
const ACME_SEO_TITLE_MIN = 10;

// Приоритет 15: раньше подстановки цен (20, inc/prices.php).
add_filter( 'rank_math/frontend/title', 'acme_seo_frontend_title', 15 );

/** Подпись интерфейса темы на языке страницы; без темы acme — пустая строка. */
function acme_seo_string( $key, $lang = null ) {
	return function_exists( 'acme_t' ) ? (string) acme_t( $key, $lang ) : '';
}

/** Убирает хвост « | Имя сайта» (или через тире) у заголовка длиннее предела. */
function acme_seo_trim_title( $title, array $site_names, $max_length = ACME_SEO_TITLE_MAX ) {
	$title = (string) $title;
	if ( mb_strlen( $title ) <= $max_length ) {
		return $title;
	}
	foreach ( array_filter( $site_names ) as $site_name ) {
		$headline = preg_replace( '/\s+[|—–-]\s+' . preg_quote( $site_name, '/' ) . '$/u', '', $title );
		if ( $headline !== $title && mb_strlen( $headline ) >= ACME_SEO_TITLE_MIN ) {
			return $headline;
		}
	}
	return $title;
}

/** «страница 2» по шаблону «страница {n}»; первая страница списка и пустой шаблон — пустая строка. */
function acme_seo_page_label( $page_number, $template ) {
	return (int) $page_number > 1 ? str_replace( '{n}', (string) (int) $page_number, (string) $template ) : '';
}

/** Заголовок страницы списка: «Блог — страница 2 | Имя сайта». */
function acme_seo_paged_title( $title, $page_label ) {
	$title = (string) $title;
	if ( '' === (string) $page_label || false !== mb_strpos( $title, $page_label ) ) {
		return $title;
	}
	if ( false !== strpos( $title, ' | ' ) ) {
		return preg_replace( '/ \| /', ' — ' . $page_label . ' | ', $title, 1 );
	}
	return $title . ' — ' . $page_label;
}

/** Номер текущей страницы списка подписью на языке страницы или ''. */
function acme_seo_current_page_label() {
	// У несуществующей страницы списка (/services/page/9/) номера нет: это 404, а не часть списка.
	if ( is_404() ) {
		return '';
	}
	$page_number = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	return acme_seo_page_label( $page_number, acme_seo_string( 'pageN' ) );
}

/** Заголовок страниц, у которых он берётся из подписей интерфейса; '' — оставить заголовок Rank Math. */
function acme_seo_string_title() {
	$key = '';
	if ( is_post_type_archive( 'acme_service' ) ) {
		$key = 'titleServices';
	} elseif ( is_404() ) {
		$key = 'notFoundH1';
	}
	$headline = $key ? acme_seo_string( $key ) : '';
	return '' !== $headline ? $headline . ' | ' . get_bloginfo( 'name' ) : '';
}

function acme_seo_frontend_title( $title ) {
	$string_title = acme_seo_string_title();
	$title        = '' !== $string_title ? $string_title : $title;
	$title        = acme_seo_paged_title( $title, acme_seo_current_page_label() );
	return acme_seo_trim_title( $title, array( get_bloginfo( 'name' ) ) );
}
