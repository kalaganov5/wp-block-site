<?php
/**
 * JSON-LD FAQPage из блока «Вопросы и ответы» (группа .acme-faq с блоками core/details).
 *
 * Расширенного сниппета в Google эта разметка НЕ даёт: Google отключил показ FAQ для всех сайтов 7 мая 2026.
 * Разметка оставлена для остальных читателей — Яндекса, Bing и ИИ-ассистентов, которым пары «вопрос — ответ»
 * помогают цитировать страницу.
 * Выключить: константа ACME_SEO_FAQ_SCHEMA = false или фильтр acme_seo_faq_schema.
 */

defined( 'ABSPATH' ) || exit;

/** Выводить ли FAQPage. Значение по умолчанию для фильтра acme_seo_faq_schema. */
const ACME_SEO_FAQ_SCHEMA = true;

function acme_seo_schema_faq( array $data, array $context ) {
	if ( ! $context['post_id'] || ! apply_filters( 'acme_seo_faq_schema', ACME_SEO_FAQ_SCHEMA ) ) {
		return $data;
	}
	foreach ( $data as $node ) {
		// Владелец добавил блок FAQ Rank Math — второй FAQPage на странице не нужен.
		if ( is_array( $node ) && in_array( 'FAQPage', (array) ( $node['@type'] ?? array() ), true ) ) {
			return $data;
		}
	}
	$faq_items = acme_seo_faq_items( $context['post_id'] );
	if ( $faq_items ) {
		$data['acmeFaq'] = array(
			'@type'      => 'FAQPage',
			'@id'        => $context['page_url'] . '#faq',
			'inLanguage' => acme_lang(),
			'mainEntity' => array_map( 'acme_seo_schema_question', $faq_items ),
		);
	}
	return $data;
}

/** Пара [ вопрос, ответ ] → узел Question. */
function acme_seo_schema_question( array $faq_item ) {
	return array(
		'@type'          => 'Question',
		'name'           => $faq_item[0],
		'acceptedAnswer' => array(
			'@type' => 'Answer',
			'text'  => $faq_item[1],
		),
	);
}

/** Вопросы и ответы страницы: core/details внутри группы с классом acme-faq → [ [ вопрос, ответ ] ]. */
function acme_seo_faq_items( $post_id ) {
	$blocks = parse_blocks( (string) get_post_field( 'post_content', $post_id ) );
	return acme_seo_collect_faq( $blocks, false );
}

/** Обходит дерево блоков: внутри группы acme-faq все вложенные core/details считаются вопросами. */
function acme_seo_collect_faq( array $blocks, $is_in_faq ) {
	$faq_items = array();
	foreach ( $blocks as $block ) {
		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		$is_faq     = $is_in_faq || false !== strpos( $class_name, 'acme-faq' );
		$faq_item   = $is_faq ? acme_seo_faq_item( $block ) : null;
		if ( $faq_item ) {
			$faq_items[] = $faq_item;
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			$faq_items = array_merge( $faq_items, acme_seo_collect_faq( $block['innerBlocks'], $is_faq ) );
		}
	}
	return $faq_items;
}

/** Блок core/details → [ вопрос, ответ ] или null (не details либо пустой ответ). */
function acme_seo_faq_item( array $block ) {
	if ( 'core/details' !== $block['blockName'] ) {
		return null;
	}
	if ( ! preg_match( '#<summary>(.*?)</summary>#s', $block['innerHTML'], $matches ) ) {
		return null;
	}
	$answer_html = '';
	foreach ( $block['innerBlocks'] as $inner_block ) {
		$answer_html .= ' ' . render_block( $inner_block );
	}
	$answer = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $answer_html ) ) );
	return $answer ? array( wp_strip_all_tags( $matches[1] ), $answer ) : null;
}
