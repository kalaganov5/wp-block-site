<?php
/**
 * Описание страницы (meta description) там, где владелец не задал своё в Rank Math.
 *
 * Порядок источников: своё SEO-описание → подзаголовок страницы (абзац .acme-lead) → отрывок → начало текста.
 * Архивы берут подписи интерфейса на языке страницы; на второй и следующих страницах списка добавляется номер —
 * иначе у всех страниц списка одно описание. Правила — чистые функции (тесты: tools/tests/seo.test.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Длина описания, которое сайт составляет сам. В сниппете Google помещается 155–160 знаков; берём нижнюю
 * границу, потому что к обрезанному тексту добавляется многоточие, а на страницах списка — ещё и номер.
 */
const ACME_SEO_DESCRIPTION_LENGTH = 155;

/** Абзац короче этого — служебная строка («Редакция от 5 мая»), а не начало текста. */
const ACME_SEO_PARAGRAPH_MIN = 60;

// Приоритет 15: раньше подстановки цен (20, inc/prices.php), чтобы метки {{price:…}} в описании заменились.
add_filter( 'rank_math/frontend/description', 'acme_seo_frontend_description', 15 );

/** Текст без разметки, комментариев блоков и лишних пробелов, не длиннее $max_length, обрезан по слову. */
function acme_seo_excerpt( $text, $max_length = ACME_SEO_DESCRIPTION_LENGTH ) {
	$text = preg_replace( '/<!--.*?-->/s', ' ', (string) $text );
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
	if ( mb_strlen( $text ) <= $max_length ) {
		return $text;
	}
	$cut         = mb_substr( $text, 0, $max_length );
	$whole_words = mb_substr( $cut, 0, (int) mb_strrpos( $cut, ' ' ) );
	return rtrim( $whole_words, ' ,.;:—-' ) . '…';
}

/** Подзаголовок страницы: текст первого абзаца с классом acme-lead или ''. */
function acme_seo_lead_text( $html ) {
	if ( ! preg_match( '#<p[^>]*class="[^"]*\bacme-lead\b[^"]*"[^>]*>(.*?)</p>#su', (string) $html, $matches ) ) {
		return '';
	}
	return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $matches[1] ) ) );
}

/**
 * Начало текста для описания: с первого абзаца не короче ACME_SEO_PARAGRAPH_MIN. В документах первым идёт
 * «Редакция от 5 октября» — без этого у политики и согласия было бы одинаковое описание.
 */
function acme_seo_main_paragraph( $html ) {
	$html = preg_replace( '/<!--.*?-->/s', ' ', (string) $html );
	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#su', $html, $matches ) ) {
		foreach ( $matches[1] as $paragraph_html ) {
			$paragraph = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $paragraph_html ) ) );
			if ( mb_strlen( $paragraph ) >= ACME_SEO_PARAGRAPH_MIN ) {
				return $paragraph;
			}
		}
	}
	return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
}

/**
 * Описание по правилу. $source: own (своё SEO-описание), lead (подзаголовок), excerpt (отрывок),
 * content (разметка текста). Своё описание не обрезается — его длину выбрал владелец.
 */
function acme_seo_description_by_rule( array $source ) {
	$own = trim( (string) ( $source['own'] ?? '' ) );
	if ( '' !== $own ) {
		return $own;
	}
	foreach ( array( 'lead', 'excerpt' ) as $key ) {
		$text = acme_seo_excerpt( $source[ $key ] ?? '' );
		if ( '' !== $text ) {
			return $text;
		}
	}
	return acme_seo_excerpt( acme_seo_main_paragraph( $source['content'] ?? '' ) );
}

/** Описание страницы списка с номером: «Статьи о работе. Страница 2.» */
function acme_seo_paged_description( $description, $page_label ) {
	$description = (string) $description;
	if ( '' === $description || '' === (string) $page_label ) {
		return $description;
	}
	$label = mb_strtoupper( mb_substr( $page_label, 0, 1 ) ) . mb_substr( $page_label, 1 );
	return rtrim( $description, '. …' ) . '. ' . $label . '.';
}

/** Описание записи: то же правило для meta description, JSON-LD и llms.txt. Цены-метки подставлены. */
function acme_seo_post_description( $post, $lang = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$description = acme_seo_description_by_rule(
		array(
			'own'     => get_post_meta( $post->ID, 'rank_math_description', true ),
			'lead'    => acme_seo_lead_text( $post->post_content ),
			'excerpt' => $post->post_excerpt,
			'content' => $post->post_content,
		)
	);
	return acme_apply_prices( $description, $lang );
}

/** Описание текущей страницы без номера страницы списка; '' — оставить то, что составил Rank Math. */
function acme_seo_page_description() {
	if ( is_post_type_archive( 'acme_service' ) ) {
		return acme_seo_excerpt( acme_seo_string( 'servicesLead' ) );
	}
	if ( is_home() ) {
		$blog_page_id = acme_role_id( 'blog' );
		return $blog_page_id ? acme_seo_post_description( $blog_page_id ) : '';
	}
	if ( is_category() ) {
		$term_text = wp_strip_all_tags( term_description() );
		return acme_seo_excerpt( $term_text ? $term_text : single_cat_title( '', false ) );
	}
	if ( ! is_singular() ) {
		return '';
	}
	// Своё SEO-описание Rank Math уже вывел (с его переменными вроде %title%) — оно остаётся как есть.
	$post_id = get_queried_object_id();
	return get_post_meta( $post_id, 'rank_math_description', true ) ? '' : acme_seo_post_description( $post_id );
}

function acme_seo_frontend_description( $description ) {
	$own = acme_seo_page_description();
	return acme_seo_paged_description( '' !== $own ? $own : $description, acme_seo_current_page_label() );
}
