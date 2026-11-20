<?php
/**
 * Публичный API плагина для темы acme.
 *
 * Это единственный файл плагина, функции которого тема вправе вызывать: остальные файлы inc/ — внутренняя
 * кухня, её можно перестраивать, не разыскивая вызовы по теме (границу проверяет tools/check.js, boundary).
 * Тема вызывает эти функции через function_exists() / acme_has_core(), поэтому без плагина она не падает.
 *
 * Группы:
 *   - язык:              acme_main_lang(), acme_lang();
 *   - настройки сайта:   acme_setting();
 *   - служебные страницы и переводы: acme_tr(), acme_role_id(), acme_page_role(), acme_role_url(),
 *     acme_home_url();
 *   - записи на языке:   acme_posts();
 *   - цены-переменные:   acme_price(), acme_apply_prices().
 */

defined( 'ABSPATH' ) || exit;

// ---------- Язык ----------

/** Основной язык сайта. */
function acme_main_lang() {
	return ACME_LANGS[0];
}

/** Текущий язык страницы. */
function acme_lang() {
	// Превью блоков в редакторе передаёт язык записи (assets/js/editor.js темы).
	// phpcs:ignore WordPress.Security.NonceVerification -- только выбор языка превью
	if ( isset( $_GET['acmelang'] ) && in_array( $_GET['acmelang'], ACME_LANGS, true ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- только выбор языка превью
		return sanitize_key( $_GET['acmelang'] );
	}
	$polylang_lang = acme_polylang_lang();
	return $polylang_lang ? $polylang_lang : acme_main_lang();
}

// ---------- Настройки сайта ----------

/** Значение настройки из «Настройки → Acme Site»; несохранённая — значение по умолчанию. */
function acme_setting( $key ) {
	$all = wp_parse_args( (array) get_option( 'acme_settings', array() ), acme_settings_defaults() );
	return $all[ $key ] ?? '';
}

// ---------- Служебные страницы и переводы ----------

/** Перевод записи на язык $lang (Polylang) или сама запись. */
function acme_tr( $post_id, $lang = null ) {
	$lang = $lang ? $lang : acme_lang();
	if ( $post_id && function_exists( 'pll_get_post' ) ) {
		$translation_id = pll_get_post( (int) $post_id, $lang );
		if ( $translation_id ) {
			return (int) $translation_id;
		}
	}
	return (int) $post_id;
}

/** ID служебной страницы по роли (home, about, contacts, blog, privacy…) на языке. Роли — опция acme_roles. */
function acme_role_id( $role, $lang = null ) {
	$roles = (array) get_option( 'acme_roles', array() );
	return empty( $roles[ $role ] ) ? 0 : acme_tr( (int) $roles[ $role ], $lang );
}

/** Роль служебной страницы по ID записи на любом языке (home, about, contacts…) или ''. */
function acme_page_role( $post_id ) {
	$main_id = acme_tr( (int) $post_id, acme_main_lang() );
	$role    = array_search( $main_id, array_map( 'intval', (array) get_option( 'acme_roles', array() ) ), true );
	return false === $role ? '' : (string) $role;
}

/** Адрес служебной страницы по роли; роль не назначена — главная на этом языке. */
function acme_role_url( $role, $lang = null ) {
	$id = acme_role_id( $role, $lang );
	return $id ? get_permalink( $id ) : acme_home_url( $lang );
}

/** Главная страница на языке. */
function acme_home_url( $lang = null ) {
	$lang = $lang ? $lang : acme_lang();
	if ( function_exists( 'pll_home_url' ) ) {
		return pll_home_url( $lang );
	}
	return home_url( '/' );
}

// ---------- Записи ----------

/** Записи типа на языке (Polylang понимает аргумент lang). */
function acme_posts( $post_type, $args = array(), $lang = null ) {
	return get_posts(
		array_merge(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'lang'           => $lang ? $lang : acme_lang(),
			),
			$args
		)
	);
}

// ---------- Цены-переменные ----------

/** Цена по ключу на языке; на этом языке не задана — цена основного языка. */
function acme_price( $key, $lang = null ) {
	$lang  = $lang ? $lang : acme_lang();
	$price = acme_prices()[ $key ] ?? null;
	if ( ! $price ) {
		return '';
	}
	$localized = (string) ( $price[ $lang ] ?? '' );
	return '' !== $localized ? $localized : (string) ( $price[ acme_main_lang() ] ?? '' );
}

/** Заменяет метки {{price:ключ}} в строке. Неизвестная метка остаётся как есть: её видно и можно исправить. */
function acme_apply_prices( $text, $lang = null ) {
	if ( ! is_string( $text ) || false === strpos( $text, '{{price:' ) ) {
		return $text;
	}
	return preg_replace_callback(
		'/\{\{price:([a-z0-9_]+)\}\}/',
		function ( $matches ) use ( $lang ) {
			$price = acme_price( $matches[1], $lang );
			return '' !== $price ? $price : $matches[0];
		},
		$text
	);
}
