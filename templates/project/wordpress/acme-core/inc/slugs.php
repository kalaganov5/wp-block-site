<?php
/**
 * Адреса без кириллицы: если у новой записи, страницы или рубрики слаг не задан вручную, WordPress
 * собирает его из заголовка — русские буквы при этом переводятся в латиницу. Лучше всё же задавать
 * английский слаг в поле «Ссылка» (как у остальных страниц сайта); транслит — запасной вариант.
 */

defined( 'ABSPATH' ) || exit;

// Приоритет 9: раньше sanitize_title_with_dashes (10), который превратил бы кириллицу в %-коды.
add_filter( 'sanitize_title', 'acme_translit_slug', 9, 3 );

function acme_translit( $text ) {
	static $letters = array(
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'е' => 'e',
		'ё' => 'e',
		'ж' => 'zh',
		'з' => 'z',
		'и' => 'i',
		'й' => 'j',
		'к' => 'k',
		'л' => 'l',
		'м' => 'm',
		'н' => 'n',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'c',
		'ч' => 'ch',
		'ш' => 'sh',
		'щ' => 'shch',
		'ъ' => '',
		'ы' => 'y',
		'ь' => '',
		'э' => 'e',
		'ю' => 'yu',
		'я' => 'ya',
	);
	return strtr( mb_strtolower( $text, 'UTF-8' ), $letters );
}

/** Транслит только при сохранении слага (контекст save): в остальных случаях строка остаётся как есть. */
function acme_translit_slug( $title, $raw_title = '', $context = 'display' ) {
	if ( 'save' === $context && preg_match( '/[\x{0400}-\x{04FF}]/u', (string) $title ) ) {
		return acme_translit( $title );
	}
	return $title;
}
