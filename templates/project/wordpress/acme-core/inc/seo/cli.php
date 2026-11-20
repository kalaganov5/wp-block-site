<?php
/**
 * wp acme seo [--force] — запускается при каждом деплое (deploy/remote.sh): включает и настраивает Rank Math
 * (один раз на версию ACME_SEO_VERSION), сбрасывает кеш карты сайта и печатает самопроверку SEO — пять строк,
 * по которым в логе деплоя видно состояние сайта. Подключается только под WP-CLI.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Включает и настраивает Rank Math (один раз на версию), сбрасывает кеш карты сайта, печатает самопроверку.
 *
 * ## OPTIONS
 *
 * [--force]
 * : Поставить настройки Rank Math заново (правки в админке перезапишутся).
 *
 * @param array $args  Позиционные аргументы.
 * @param array $assoc Флаги.
 */
function acme_seo_cli_command( $args, $assoc ) {
	$log = acme_seo_setup( ! empty( $assoc['force'] ) );
	foreach ( acme_seo_status_lines( acme_seo_status() ) as $line ) {
		WP_CLI::log( 'SEO · ' . $line );
	}
	WP_CLI::success( $log ? 'SEO: ' . implode( '; ', $log ) . '.' : 'SEO: всё уже настроено.' );
}

WP_CLI::add_command( 'acme seo', 'acme_seo_cli_command' );
