<?php
/**
 * Правила микроразметки JSON-LD (acme-core/inc/seo/schema*.php, local-business.php).
 */

require_once ACME_CORE_DIR . 'inc/seo/schema.php';
require_once ACME_CORE_DIR . 'inc/seo/schema-service.php';
require_once ACME_CORE_DIR . 'inc/seo/schema-pages.php';
require_once ACME_CORE_DIR . 'inc/seo/local-business.php';

test(
	'цена услуги «от 2 000 ₽» становится предложением: 2000 RUB',
	function () {
		$offer = acme_seo_offer_from_price( 'от 2 000 ₽' );
		expect_equal( '2000', $offer['price'] );
		expect_equal( 'RUB', $offer['priceCurrency'] );
	}
);

test(
	'валюта предложения определяется по знаку: $ — USD, € — EUR, без знака — рубли',
	function () {
		expect_equal( 'USD', acme_seo_offer_from_price( 'from $25' )['priceCurrency'] );
		expect_equal( 'EUR', acme_seo_offer_from_price( '1 500,50 €' )['priceCurrency'] );
		expect_equal( '1500.50', acme_seo_offer_from_price( '1 500,50 €' )['price'] );
		expect_equal( 'RUB', acme_seo_offer_from_price( '3000' )['priceCurrency'] );
	}
);

test(
	'цена без числа («по договорённости», пусто, неподставленная метка) предложения не даёт',
	function () {
		expect_equal( null, acme_seo_offer_from_price( 'по договорённости' ) );
		expect_equal( null, acme_seo_offer_from_price( '' ) );
		expect_equal( null, acme_seo_offer_from_price( 'от {{price:hour2}}' ) );
	}
);

test(
	'страница «О нас» — AboutPage у компании и ProfilePage у эксперта; остальные служебные — просто WebPage',
	function () {
		expect_equal( 'AboutPage', acme_seo_webpage_type( 'about', 'company' ) );
		expect_equal( 'ProfilePage', acme_seo_webpage_type( 'about', 'person' ) );
		expect_equal( '', acme_seo_webpage_type( 'contacts', 'company' ) );
		expect_equal( '', acme_seo_webpage_type( '', 'company' ) );
	}
);

test(
	'без адреса в настройках свойства местной компании не выводятся',
	function () {
		expect_equal( array(), acme_seo_local_business( array( 'phone' => '+7 900 000-00-00' ) ) );
		expect_equal( array(), acme_seo_local_business( array() ) );
	}
);

test(
	'адрес в настройках делает организацию местной компанией с адресом, телефоном, часами и координатами',
	function () {
		$node = acme_seo_local_business(
			array(
				'address_street'   => 'ул. Ленина, 1',
				'address_locality' => 'Казань',
				'address_postal'   => '420000',
				'address_country'  => 'ru',
				'phone'            => '+7 900 000-00-00',
				'opening_hours'    => 'Mo-Fr 09:00-18:00; Sa 10:00-14:00',
				'geo'              => '55.7963, 49.1088',
			)
		);
		expect_equal( array( 'Organization', 'LocalBusiness' ), $node['@type'] );
		expect_equal( 'Казань', $node['address']['addressLocality'] );
		expect_equal( 'RU', $node['address']['addressCountry'] );
		expect_equal( '+7 900 000-00-00', $node['telephone'] );
		expect_equal( array( 'Mo-Fr 09:00-18:00', 'Sa 10:00-14:00' ), $node['openingHours'] );
		expect_equal( 55.7963, $node['geo']['latitude'] );
		expect_equal( 49.1088, $node['geo']['longitude'] );
	}
);

test(
	'пустые поля адреса и неверные координаты в разметку не попадают',
	function () {
		$node = acme_seo_local_business(
			array(
				'address_street'   => 'ул. Ленина, 1',
				'address_locality' => 'Казань',
				'geo'              => 'рядом с метро',
			)
		);
		expect_true( ! isset( $node['geo'] ) && ! isset( $node['telephone'] ) && ! isset( $node['openingHours'] ) );
		expect_true( ! isset( $node['address']['postalCode'] ) );
	}
);

test(
	'профили организации — ссылки из настроек без повторов и без строк, которые не являются адресами',
	function () {
		$settings = array(
			'telegram_url' => 'https://t.me/acme',
			'social_urls'  => 'https://vk.com/acme  https://t.me/acme, acme@example.com',
		);
		expect_equal( array( 'https://t.me/acme', 'https://vk.com/acme' ), acme_seo_profile_urls( $settings ) );
	}
);
