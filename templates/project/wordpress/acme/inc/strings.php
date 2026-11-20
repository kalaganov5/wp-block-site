<?php
/**
 * Подписи интерфейса (шапка, подвал, формы, cookie, кнопки…).
 * Значения по умолчанию — inc/strings-data.php. Поверх них работают переводы из Polylang:
 * «Языки → Перевод строк», группа «Acme Site». Ключ строки — её имя.
 */

defined( 'ABSPATH' ) || exit;

function acme_strings_defaults() {
	static $data = null;
	if ( null === $data ) {
		$data = include __DIR__ . '/strings-data.php';
	}
	return $data;
}

/** Основной язык сайта — первый в strings-data.php. */
function acme_default_lang() {
	return (string) array_key_first( acme_strings_defaults() );
}

/** Текущий язык (из плагина acme-core или Polylang). */
function acme_cur_lang() {
	if ( function_exists( 'acme_lang' ) ) {
		return acme_lang();
	}
	if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
		return pll_current_language();
	}
	return acme_default_lang();
}

/** Подпись по ключу на языке $lang (по умолчанию — текущем). Параметры в тексте — {name}. */
function acme_t( $key, $lang = null ) {
	$lang         = $lang ? $lang : acme_cur_lang();
	$defaults     = acme_strings_defaults();
	$default_text = $defaults[ acme_default_lang() ][ $key ] ?? '';
	$text         = $defaults[ $lang ][ $key ] ?? $default_text;
	if ( '' !== $default_text && function_exists( 'pll_translate_string' ) ) {
		$translation = pll_translate_string( $default_text, $lang );
		if ( is_string( $translation ) && '' !== $translation && $translation !== $default_text ) {
			$text = $translation;
		}
	}
	return function_exists( 'acme_apply_prices' ) ? acme_apply_prices( $text, $lang ) : $text;
}

/** echo с экранированием. */
function acme_e( $key ) {
	echo esc_html( acme_t( $key ) );
}

/** Регистрация строк в Polylang, чтобы их можно было править в админке. */
add_action(
	'init',
	function () {
		if ( ! is_admin() || ! function_exists( 'pll_register_string' ) ) {
			return;
		}
		foreach ( acme_strings_defaults()[ acme_default_lang() ] as $key => $value ) {
			if ( '' !== $value ) {
				pll_register_string( $key, $value, 'Acme Site', strlen( $value ) > 90 );
			}
		}
	}
);

/**
 * Записывает значения других языков из strings-data.php в переводы строк Polylang, чтобы в «Перевод строк»
 * были видны текущие тексты. Вызывается из импорта (wp acme import); уже заданные переводы не трогает.
 */
function acme_seed_string_translations() {
	if ( ! class_exists( 'PLL_MO' ) || ! function_exists( 'PLL' ) ) {
		return;
	}
	$defaults  = acme_strings_defaults();
	$base_lang = acme_default_lang();
	foreach ( array_keys( $defaults ) as $lang ) {
		$language = $lang === $base_lang ? null : PLL()->model->get_language( $lang );
		if ( ! $language ) {
			continue;
		}
		$translations = new PLL_MO();
		$translations->import_from_db( $language );
		foreach ( $defaults[ $base_lang ] as $key => $source ) {
			if ( '' === $source || empty( $defaults[ $lang ][ $key ] ) || $defaults[ $lang ][ $key ] === $source ) {
				continue;
			}
			$current         = $translations->translate( $source );
			$is_untranslated = '' === (string) $current || $current === $source;
			if ( $is_untranslated ) {
				$translations->add_entry( $translations->make_entry( $source, $defaults[ $lang ][ $key ] ) );
			}
		}
		$translations->export_to_db( $language );
	}
}
