<?php
/**
 * JSON-LD страницы услуги: узел Service — что это, кто оказывает, где и почём.
 * Цена берётся из поля услуги acme_price — той же метки {{price:ключ}}, что в тексте страницы: при смене
 * цены в «Настройки → Цены» страница и разметка не расходятся.
 */

defined( 'ABSPATH' ) || exit;

/** Валюта цены без знака валюты (код ISO 4217). */
const ACME_SEO_CURRENCY = 'RUB';

/**
 * Цена услуги текстом («от 2 000 ₽», «$25») → [ price, priceCurrency ] или null, если числа в ней нет
 * («по договорённости») либо метка цены не подставилась.
 */
function acme_seo_offer_from_price( $price_text, $default_currency = ACME_SEO_CURRENCY ) {
	$text = preg_replace( '/\{\{[^}]*\}\}/', '', (string) $price_text );
	// Разряды в ценах разделяют пробелом, в том числе неразрывным и узким: «2 000».
	$digits = preg_replace( '/[\s\x{00A0}\x{202F}\x{2009}]+/u', '', $text );
	if ( ! preg_match( '/(\d+)(?:[.,](\d{1,2}))?(?!\d)/', $digits, $matches ) ) {
		return null;
	}
	$currencies = array(
		'$' => 'USD',
		'€' => 'EUR',
		'£' => 'GBP',
		'₽' => 'RUB',
	);
	$currency   = $default_currency;
	foreach ( $currencies as $sign => $code ) {
		if ( false !== strpos( $text, $sign ) ) {
			$currency = $code;
		}
	}
	return array(
		'price'         => isset( $matches[2] ) ? $matches[1] . '.' . str_pad( $matches[2], 2, '0' ) : $matches[1],
		'priceCurrency' => $currency,
	);
}

/** Раздел услуги (таксономия acme_topic) — её serviceType; без раздела — название услуги. */
function acme_seo_service_type( $post_id ) {
	$topics = get_the_terms( $post_id, 'acme_topic' );
	return $topics && ! is_wp_error( $topics ) ? $topics[0]->name : wp_strip_all_tags( get_the_title( $post_id ) );
}

/** Предложение с ценой услуги или null: услуга без цены выводится без offers. */
function acme_seo_service_offer( $post_id, $page_url ) {
	$price_text = acme_apply_prices( (string) get_post_meta( $post_id, 'acme_price', true ) );
	$offer      = acme_seo_offer_from_price( $price_text );
	if ( ! $offer ) {
		return null;
	}
	return array_merge(
		array(
			'@type' => 'Offer',
			'url'   => $page_url,
		),
		$offer
	);
}

function acme_seo_schema_service( array $data, array $context ) {
	if ( ! is_singular( 'acme_service' ) ) {
		return $data;
	}
	$post_id = $context['post_id'];
	// Узел заменяет разметку Rank Math по умолчанию (Article): услуга — не статья.
	$data['richSnippet'] = array_filter(
		array(
			'@type'            => 'Service',
			'@id'              => $context['page_url'] . '#service',
			'name'             => wp_strip_all_tags( get_the_title( $post_id ) ),
			'description'      => acme_seo_post_description( $post_id ),
			'url'              => $context['page_url'],
			'serviceType'      => acme_seo_service_type( $post_id ),
			'areaServed'       => acme_setting( 'area_served' ),
			'provider'         => $context['entity_id'] ? array( '@id' => $context['entity_id'] ) : null,
			'offers'           => acme_seo_service_offer( $post_id, $context['page_url'] ),
			'inLanguage'       => acme_lang(),
			'mainEntityOfPage' => isset( $data['WebPage']['@id'] ) ? array( '@id' => $data['WebPage']['@id'] ) : null,
		)
	);
	return $data;
}
