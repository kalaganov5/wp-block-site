<?php
/**
 * Самопроверка SEO после деплоя: пять строк в логе — карта сайта, открыт ли сайт, версия Rank Math,
 * его модули, коды вебмастеров. Печатает команда wp acme seo (cli.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Строки самопроверки. $state: sitemap_url ('' — карты нет), is_public, version ('' — Rank Math не установлен),
 * modules (список включённых), codes [ название => задан ли код ].
 */
function acme_seo_status_lines( array $state ) {
	$codes = array();
	foreach ( $state['codes'] as $name => $is_set ) {
		$codes[] = $name . ' — ' . ( $is_set ? 'есть' : 'нет' );
	}
	$is_public = ! empty( $state['is_public'] );
	return array(
		'Карта сайта: ' . ( $state['sitemap_url'] ? $state['sitemap_url'] : 'нет (нужен модуль sitemap Rank Math)' ),
		'Индексация: ' . ( $is_public ? 'сайт открыт для поисковиков' : 'сайт закрыт от поисковиков (noindex)' ),
		'Rank Math: ' . ( $state['version'] ? $state['version'] : 'не установлен или выключен' ),
		'Модули Rank Math: ' . ( $state['modules'] ? implode( ', ', $state['modules'] ) : '—' ),
		'Коды вебмастеров: ' . ( $codes ? implode( ', ', $codes ) : 'не заданы' ),
	);
}

/** Состояние SEO сайта сейчас — данные для acme_seo_status_lines(). */
function acme_seo_status() {
	$has_rank_math = defined( 'RANK_MATH_VERSION' );
	$modules       = $has_rank_math ? array_values( (array) get_option( 'rank_math_modules', array() ) ) : array();
	$general       = (array) get_option( 'rank-math-options-general', array() );
	$codes         = array();
	foreach ( acme_seo_verification_fields() as $field ) {
		$codes[ $field[1] ] = ! empty( $general[ $field[0] ] );
	}
	return array(
		'sitemap_url' => in_array( 'sitemap', $modules, true ) ? home_url( '/sitemap_index.xml' ) : '',
		'is_public'   => (bool) get_option( 'blog_public' ),
		'version'     => $has_rank_math ? RANK_MATH_VERSION : '',
		'modules'     => $modules,
		'codes'       => $codes,
	);
}
