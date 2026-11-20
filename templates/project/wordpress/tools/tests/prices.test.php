<?php
/**
 * Цены-переменные в тексте: {{price:ключ}} (acme-core/inc/api.php, inc/prices.php).
 */

require_once ACME_CORE_DIR . 'inc/api.php';
require_once ACME_CORE_DIR . 'inc/prices.php';

test(
	'неизвестный ключ остаётся в тексте как есть: владелец видит опечатку, а не пустое место',
	function () {
		expect_equal( 'от {{price:no_such_key}}', acme_apply_prices( 'от {{price:no_such_key}}', 'ru' ) );
	}
);

test(
	'текст без переменных возвращается без изменений',
	function () {
		expect_equal( 'Цена по запросу', acme_apply_prices( 'Цена по запросу', 'ru' ) );
	}
);

test(
	'известный ключ заменяется ценой на языке страницы',
	function () {
		$prices = acme_prices();
		$key    = array_key_first( $prices );
		$price  = acme_price( $key, 'ru' );
		expect_true( '' !== $price );
		expect_equal( "от $price", acme_apply_prices( "от {{price:$key}}", 'ru' ) );
	}
);
