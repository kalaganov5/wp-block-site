<?php
/**
 * Коды подтверждения прав на сайт: Яндекс Вебмастер, Google Search Console, Bing Webmaster.
 * Владелец вводит их в «Настройки → Acme Site»; отсюда они передаются в настройки Rank Math
 * (Общие настройки → Инструменты для веб-мастеров), и Rank Math сам выводит meta-теги на главной.
 * Передаются только заполненные поля: код, введённый прямо в Rank Math, пустое поле сайта не сотрёт.
 */

defined( 'ABSPATH' ) || exit;

// Сохранение настроек сайта в админке: первое (add) и последующие (update).
add_action( 'add_option_acme_settings', 'acme_seo_sync_verification', 10, 0 );
add_action( 'update_option_acme_settings', 'acme_seo_sync_verification', 10, 0 );

/** Настройка сайта → [ ключ в опции rank-math-options-general, название для самопроверки ]. */
function acme_seo_verification_fields() {
	return array(
		'verify_yandex' => array( 'yandex_verify', 'Яндекс' ),
		'verify_google' => array( 'google_verify', 'Google' ),
		'verify_bing'   => array( 'bing_verify', 'Bing' ),
	);
}

/** Код из того, что вставил владелец: сам код или целый тег <meta … content="код">. */
function acme_seo_verification_code( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/content\s*=\s*["\']([^"\']+)["\']/i', $value, $matches ) ) {
		$value = $matches[1];
	}
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
}

/** Передаёт заполненные коды в Rank Math; true — что-то изменилось. */
function acme_seo_sync_verification() {
	$settings = (array) get_option( 'acme_settings', array() );
	$codes    = array();
	foreach ( acme_seo_verification_fields() as $setting_key => $field ) {
		$code = acme_seo_verification_code( $settings[ $setting_key ] ?? '' );
		if ( '' !== $code ) {
			$codes[ $field[0] ] = $code;
		}
	}
	return $codes ? acme_seo_merge_option( 'rank-math-options-general', $codes ) : false;
}
