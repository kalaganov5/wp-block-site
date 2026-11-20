<?php
/**
 * SEO через Rank Math: установка настроек кодом (команда wp acme seo, её запускает каждый деплой).
 * Остальное SEO и GEO — в соседних файлах, по одной задаче на файл:
 *   onboarding.php   — Rank Math без мастера, подключения аккаунта, статистики и рекламных уведомлений;
 *   options.php      — значения настроек версии 1: модули, заголовки, карта сайта, llms.txt;
 *   indexnow.php     — модуль IndexNow только на открытом боевом сайте;
 *   verification.php — коды подтверждения вебмастеров из настроек сайта;
 *   social.php       — обложка сайта для соцсетей по языкам;
 *   titles.php, descriptions.php, canonical.php — title, description и canonical поверх шаблонов Rank Math;
 *   closed.php       — закрытые от поиска записи (robots, карта сайта);
 *   schema*.php, local-business.php, dates.php — JSON-LD; llms.php, robots.php — llms.txt и robots.txt;
 *   status.php, cli.php — самопроверка и команда wp acme seo [--force].
 *
 * Настройки Rank Math ставятся один раз на версию ACME_SEO_VERSION: дальше их меняют в админке, деплой
 * их не перезаписывает (--force — поставить заново). Изменить настройки уже работающих сайтов:
 *   1) увеличить ACME_SEO_VERSION до 2;
 *   2) написать acme_seo_options_v2() — только новые или изменённые ключи, не копию v1;
 *   3) раскомментировать строку «$installed_version < 2» в acme_seo_setup().
 * Версию 1 после первого деплоя не менять: на работающих сайтах она уже не выполнится.
 */

defined( 'ABSPATH' ) || exit;

const ACME_SEO_VERSION = 1;

/** Кто стоит за сайтом: person (эксперт) или company. Имя — название сайта. */
const ACME_SEO_ENTITY = 'company';

/** Настраивает Rank Math; возвращает список сделанного для вывода в консоль. */
function acme_seo_setup( $force = false ) {
	$log    = acme_seo_onboarding();
	$covers = acme_seo_site_covers();
	// Каждая версия ставит только свои настройки: правки в админке после неё деплой не трогает.
	$installed_version = $force ? 0 : (int) get_option( 'acme_seo_version' );
	if ( $installed_version < 1 ) {
		acme_seo_options_v1( $covers );
	}
	// if ( $installed_version < 2 ) { acme_seo_options_v2(); } — следующая версия настроек.
	if ( $installed_version < ACME_SEO_VERSION ) {
		update_option( 'acme_seo_version', ACME_SEO_VERSION );
		$log[] = 'настройки Rank Math поставлены (версия ' . ACME_SEO_VERSION . ')';
	}
	// Эти два шага — не настройки по вкусу, а соответствие сайту: выполняются при каждом деплое.
	$log[] = acme_seo_sync_indexnow();
	$log[] = acme_seo_sync_verification() ? 'коды подтверждения вебмастеров переданы в Rank Math' : '';
	acme_seo_flush_sitemap_cache();
	return array_values( array_filter( $log ) );
}

/** Сброс кеша карты сайта: и в базе, и файлы. */
function acme_seo_flush_sitemap_cache() {
	if ( ! class_exists( 'RankMath\\Sitemap\\Cache' ) ) {
		return;
	}
	\RankMath\Sitemap\Cache::invalidate_storage();
	// Rank Math удаляет файлы кеша через WP_Filesystem и из WP-CLI (другой пользователь, чем у веб-сервера)
	// может молча оставить их: карта отдаётся из старого файла. Удаляем сами.
	$cache_dir = trailingslashit( \RankMath\Sitemap\Cache::get_cache_directory() );
	foreach ( (array) glob( $cache_dir . 'rank_math_*.xml' ) as $cache_file ) {
		wp_delete_file( $cache_file );
	}
}

/** Дописывает значения в опцию-массив Rank Math, не трогая остальные её ключи. */
function acme_seo_merge_option( $option, array $values ) {
	$current = (array) get_option( $option, array() );
	$merged  = array_merge( $current, $values );
	if ( $merged !== $current ) {
		update_option( $option, $merged );
	}
	return $merged !== $current;
}
