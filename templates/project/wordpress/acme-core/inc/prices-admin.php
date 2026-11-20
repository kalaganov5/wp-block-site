<?php
/**
 * «Настройки → Цены»: таблица цен-переменных (метки {{price:ключ}}) по языкам сайта.
 * Хранится в опции acme_prices { ключ => { label, язык => цена } }; чтение — acme_prices() в inc/prices.php.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'acme_prices_menu' );
add_action( 'admin_init', 'acme_prices_register' );

function acme_prices_menu() {
	add_options_page( 'Цены на сайте', 'Цены', 'manage_options', 'acme-prices', 'acme_prices_page' );
}

function acme_prices_register() {
	register_setting(
		'acme_prices',
		'acme_prices',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'acme_prices_sanitize',
		)
	);
}

/** Колонки таблицы с полями ввода: «Что это» и по одной на язык. */
function acme_prices_columns() {
	return array_merge( array( 'label' ), ACME_LANGS );
}

function acme_prices_sanitize( $input ) {
	$clean = array();
	foreach ( (array) $input as $key => $values ) {
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			continue;
		}
		foreach ( acme_prices_columns() as $column ) {
			$clean[ $key ][ $column ] = sanitize_text_field( wp_unslash( $values[ $column ] ?? '' ) );
		}
	}
	return acme_prices_accept_new_row( $clean );
}

/** Новая цена из пустой строки внизу таблицы: принимается, если задан ключ и цена хотя бы на одном языке. */
function acme_prices_accept_new_row( array $clean ) {
	if ( empty( $clean['__new'] ) ) {
		return $clean;
	}
	$new_row = $clean['__new'];
	unset( $clean['__new'] );
	// phpcs:ignore WordPress.Security.NonceVerification -- nonce проверяет options.php
	$new_key    = sanitize_key( $_POST['acme_price_new_key'] ?? '' );
	$has_prices = (bool) array_filter( array_intersect_key( $new_row, array_flip( ACME_LANGS ) ), 'strlen' );
	if ( $new_key && $has_prices ) {
		$clean[ $new_key ] = $new_row;
	}
	return $clean;
}

function acme_prices_page() {
	echo '<div class="wrap"><h1>Цены на сайте</h1>';
	echo '<p>В текстах страниц, услуг и подписей вместо суммы стоит метка, например <code>{{price:hour}}</code>. '
		. 'На сайте она заменяется ценой из этой таблицы на языке страницы. Пишите цену так, как она должна '
		. 'выглядеть: «2 000 ₽», «$25», «25 €». Если поле языка пустое, показывается цена основного языка.</p>';
	echo '<p>Чтобы вставить цену в текст, скопируйте метку из первой колонки в нужное место в редакторе. '
		. 'Добавить свою цену можно в последней строке таблицы.</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'acme_prices' );
	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Метка</th><th>Что это</th>';
	foreach ( ACME_LANGS as $lang ) {
		echo '<th>' . esc_html( strtoupper( $lang ) ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( acme_prices() as $key => $price ) {
		acme_prices_render_row( $key, $price );
	}
	acme_prices_render_row( '', array(), true );
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}

/** Строка таблицы: существующая цена (метка для копирования) или пустая строка для новой (поле ключа). */
function acme_prices_render_row( $key, array $price, $is_new = false ) {
	$input_name = $is_new ? '__new' : $key;
	echo '<tr><td>';
	if ( $is_new ) {
		echo '<input type="text" name="acme_price_new_key" placeholder="ключ: latin_only" class="regular-text"'
			. ' style="width:160px">';
	} else {
		echo '<code style="user-select:all">{{price:' . esc_html( $key ) . '}}</code>';
	}
	echo '</td>';
	foreach ( acme_prices_columns() as $column ) {
		printf(
			'<td><input type="text" name="acme_prices[%1$s][%2$s]" value="%3$s" class="regular-text"'
				. ' style="width:%4$s"></td>',
			esc_attr( $input_name ),
			esc_attr( $column ),
			esc_attr( $price[ $column ] ?? '' ),
			esc_attr( 'label' === $column ? '280px' : '130px' )
		);
	}
	echo '</tr>';
}
