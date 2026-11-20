<?php
/**
 * Местная компания: адрес, телефон, часы работы и координаты в узле Organization (JSON-LD).
 * Работает, когда за сайтом стоит компания (ACME_SEO_ENTITY = company) и в «Настройки → Acme Site»
 * заполнены улица и город. Без адреса ничего не выводится: компания без офиса — обычная Organization.
 * Поля читает schema.php (acme_seo_schema_entity) и дописывает к узлу организации.
 */

defined( 'ABSPATH' ) || exit;

/** Свойства узла организации из настроек сайта; нет улицы или города — пустой массив. */
function acme_seo_local_business( array $settings ) {
	$setting = function ( $key ) use ( $settings ) {
		return trim( (string) ( $settings[ $key ] ?? '' ) );
	};
	if ( '' === $setting( 'address_street' ) || '' === $setting( 'address_locality' ) ) {
		return array();
	}
	$address = array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => $setting( 'address_street' ),
		'addressLocality' => $setting( 'address_locality' ),
		'addressRegion'   => $setting( 'address_region' ),
		'postalCode'      => $setting( 'address_postal' ),
		'addressCountry'  => strtoupper( $setting( 'address_country' ) ),
	);
	// Часы работы — в записи schema.org («Mo-Fr 09:00-18:00»), несколько интервалов через точку с запятой.
	$hours = array_values( array_filter( array_map( 'trim', explode( ';', $setting( 'opening_hours' ) ) ) ) );
	return array_filter(
		array(
			'@type'        => array( 'Organization', 'LocalBusiness' ),
			'address'      => array_filter( $address ),
			'telephone'    => $setting( 'phone' ),
			'openingHours' => $hours,
			'geo'          => acme_seo_geo( $setting( 'geo' ) ),
		)
	);
}

/** Координаты «широта, долгота» → GeoCoordinates или null, если это не два числа. */
function acme_seo_geo( $text ) {
	$pattern = '/^(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)$/';
	if ( ! preg_match( $pattern, trim( (string) $text ), $matches ) ) {
		return null;
	}
	return array(
		'@type'     => 'GeoCoordinates',
		'latitude'  => (float) $matches[1],
		'longitude' => (float) $matches[2],
	);
}
