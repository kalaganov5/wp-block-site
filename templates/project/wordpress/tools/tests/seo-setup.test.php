<?php
/**
 * Настройка Rank Math и самопроверка после деплоя (acme-core/inc/seo/verification.php, indexnow.php, status.php).
 */

require_once ACME_CORE_DIR . 'inc/seo/verification.php';
require_once ACME_CORE_DIR . 'inc/seo/indexnow.php';
require_once ACME_CORE_DIR . 'inc/seo/status.php';

test(
	'код подтверждения вебмастера принимается и кодом, и целым тегом meta',
	function () {
		expect_equal( 'abc123', acme_seo_verification_code( ' abc123 ' ) );
		$tag = '<meta name="yandex-verification" content="abc123" />';
		expect_equal( 'abc123', acme_seo_verification_code( $tag ) );
		expect_equal( '', acme_seo_verification_code( '' ) );
	}
);

test(
	'в коде подтверждения остаются только буквы, цифры, дефис и подчёркивание',
	function () {
		expect_equal( 'abc-12_3script', acme_seo_verification_code( 'abc-12_3"><script>' ) );
	}
);

test(
	'IndexNow включается только на открытом боевом сайте',
	function () {
		expect_true( acme_seo_indexnow_allowed( true, 'production' ) );
		expect_true( ! acme_seo_indexnow_allowed( false, 'production' ) );
		expect_true( ! acme_seo_indexnow_allowed( true, 'staging' ) );
		expect_true( ! acme_seo_indexnow_allowed( true, 'local' ) );
	}
);

test(
	'самопроверка после деплоя — пять строк: карта сайта, robots, Rank Math, модули, коды вебмастеров',
	function () {
		$lines = acme_seo_status_lines(
			array(
				'sitemap_url' => 'https://acme.example/sitemap_index.xml',
				'is_public'   => true,
				'version'     => '1.0.282',
				'modules'     => array( 'sitemap', 'rich-snippet' ),
				'codes'       => array(
					'Яндекс' => true,
					'Google' => false,
				),
			)
		);
		expect_equal( 5, count( $lines ) );
		expect_true( false !== strpos( $lines[0], 'https://acme.example/sitemap_index.xml' ) );
		expect_true( false !== strpos( $lines[1], 'открыт' ) );
		expect_true( false !== strpos( $lines[2], '1.0.282' ) );
		expect_true( false !== strpos( $lines[3], 'sitemap, rich-snippet' ) );
		expect_true( false !== strpos( $lines[4], 'Яндекс — есть' ) && false !== strpos( $lines[4], 'Google — нет' ) );
	}
);

test(
	'самопроверка называет закрытый сайт закрытым, а отсутствие Rank Math — отсутствием',
	function () {
		$lines = acme_seo_status_lines(
			array(
				'sitemap_url' => '',
				'is_public'   => false,
				'version'     => '',
				'modules'     => array(),
				'codes'       => array(),
			)
		);
		expect_true( false !== strpos( $lines[1], 'закрыт' ) );
		expect_true( false !== strpos( $lines[2], 'не установлен' ) );
	}
);
