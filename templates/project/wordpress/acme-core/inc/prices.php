<?php
/**
 * Цены как переменные: в текстах стоит метка {{price:ключ}}, на сайте вместо неё — цена на языке страницы.
 * Значения задаются в «Настройки → Цены» (inc/prices-admin.php) отдельно для каждого языка сайта.
 * Значения по умолчанию — content/prices.php (собирает tools/build-content.js).
 *
 * Метку можно вставить в любой текст: абзац, таблицу, заголовок записи, поля услуги, SEO-заголовок Rank Math,
 * подписи в «Перевод строк». Пример: «Час работы — {{price:hour}}».
 *
 * Здесь — таблица цен и подключение замены к выводу; сама замена — acme_apply_prices() в inc/api.php.
 */

defined( 'ABSPATH' ) || exit;

function acme_prices_defaults() {
	static $defaults = null;
	if ( null === $defaults ) {
		$defaults = include ACME_CORE_DIR . '/content/prices.php';
	}
	return $defaults;
}

/** Все цены: значения по умолчанию, поверх — сохранённые в админке. Свои ключи из админки тоже работают. */
function acme_prices() {
	static $all = null;
	if ( null !== $all ) {
		return $all;
	}
	$all   = acme_prices_defaults();
	$saved = (array) get_option( 'acme_prices', array() );
	foreach ( $saved as $key => $values ) {
		// Пустое поле в админке не затирает значение по умолчанию.
		$filled      = array_filter( (array) $values, 'strlen' );
		$all[ $key ] = array_merge( $all[ $key ] ?? array( 'label' => $key ), $filled );
	}
	return $all;
}

/** Замена меток в каждой строке списка (части заголовка страницы). */
function acme_apply_prices_to_list( $parts ) {
	return array_map( 'acme_apply_prices', $parts );
}

/** Замена меток во всех строках вложенного массива: микроразметка Rank Math (описания услуг в JSON-LD). */
function acme_apply_prices_deep( $data ) {
	array_walk_recursive(
		$data,
		function ( &$value ) {
			$value = acme_apply_prices( $value );
		}
	);
	return $data;
}

/** Вывод на сайте: блоки (тексты страниц, шапка, подвал, каталог), заголовки, меню, SEO Rank Math. */
function acme_prices_add_frontend_filters() {
	add_filter( 'render_block', 'acme_apply_prices', 20 );
	foreach ( array( 'the_title', 'single_post_title', 'get_the_excerpt' ) as $hook ) {
		add_filter( $hook, 'acme_apply_prices', 20 );
	}
	add_filter( 'pre_get_document_title', 'acme_apply_prices', 99 );
	add_filter( 'document_title_parts', 'acme_apply_prices_to_list' );
	$rank_math_hooks = array(
		'rank_math/frontend/title',
		'rank_math/frontend/description',
		'rank_math/opengraph/facebook/og_title',
		'rank_math/opengraph/facebook/og_description',
		'rank_math/opengraph/twitter/twitter_title',
		'rank_math/opengraph/twitter/twitter_description',
	);
	foreach ( $rank_math_hooks as $hook ) {
		add_filter( $hook, 'acme_apply_prices', 20 );
	}
	add_filter( 'rank_math/json_ld', 'acme_apply_prices_deep', 99 );
	add_filter( 'nav_menu_item_title', 'acme_apply_prices' );
}

// В админке метки остаются метками: редактор должен видеть и править {{price:ключ}}, а не подставленную цену.
if ( ! is_admin() || wp_doing_ajax() ) {
	acme_prices_add_frontend_filters();
}

// Отправленные заявки и письма CF7 (форма может содержать цены в подсказках).
add_filter( 'wpcf7_form_elements', 'acme_apply_prices' );
