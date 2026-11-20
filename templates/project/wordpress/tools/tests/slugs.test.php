<?php
/**
 * Адреса страниц: кириллица в слаге переводится в латиницу только при сохранении (acme-core/inc/slugs.php).
 */

require_once ACME_CORE_DIR . 'inc/slugs.php';

test(
	'при сохранении русский заголовок становится латиницей',
	function () {
		expect_equal( 'shchetka dlya sajta', acme_translit_slug( 'Щётка для сайта', '', 'save' ) );
	}
);

test(
	'вне сохранения строка не меняется: поиск и вывод работают с исходным текстом',
	function () {
		expect_equal( 'Услуги', acme_translit_slug( 'Услуги', '', 'display' ) );
	}
);

test(
	'латинский слаг остаётся как есть',
	function () {
		expect_equal( 'site-audit', acme_translit_slug( 'site-audit', '', 'save' ) );
	}
);
