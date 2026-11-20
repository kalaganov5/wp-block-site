<?php
/**
 * IndexNow (модуль Rank Math «instant-indexing»): при публикации и правке записи её адрес сразу отправляется
 * Яндексу и Bing. Аккаунт Rank Math не нужен: ключ модуль создаёт сам и сам отдаёт его по адресу /<ключ>.txt.
 *
 * Модуль не смотрит, открыт ли сайт: на тестовом он отправлял бы поисковикам адреса тестового домена.
 * Поэтому он включается только на открытом боевом сайте и выключается, если сайт закрыли. Проверка идёт
 * при каждом деплое (wp acme seo): сайт, открытый после первого деплоя, получит модуль на следующем.
 * Включается один раз (опция acme_seo_indexnow): если владелец потом выключит модуль, деплой его не вернёт.
 */

defined( 'ABSPATH' ) || exit;

/** Можно ли отправлять адреса поисковикам: сайт открыт и это боевая среда (WP_ENVIRONMENT_TYPE). */
function acme_seo_indexnow_allowed( $is_public, $environment ) {
	return (bool) $is_public && 'production' === $environment;
}

/** Приводит модуль в соответствие сайту; возвращает строку для лога или ''. */
function acme_seo_sync_indexnow() {
	$modules    = (array) get_option( 'rank_math_modules', array() );
	$is_enabled = in_array( 'instant-indexing', $modules, true );
	$is_allowed = acme_seo_indexnow_allowed( get_option( 'blog_public' ), wp_get_environment_type() );
	if ( ! $is_allowed && $is_enabled ) {
		update_option( 'rank_math_modules', array_values( array_diff( $modules, array( 'instant-indexing' ) ) ) );
		// Отметка снимается: когда сайт снова откроют, модуль включится сам.
		delete_option( 'acme_seo_indexnow' );
		return 'IndexNow выключен: сайт закрыт от поисковиков или не боевой';
	}
	if ( $is_allowed && ! $is_enabled && ! get_option( 'acme_seo_indexnow' ) ) {
		$modules[] = 'instant-indexing';
		update_option( 'rank_math_modules', $modules );
		update_option( 'acme_seo_indexnow', 1, false );
		// Какие типы записей отправлять; ключ API модуль создаст сам при первой загрузке.
		$indexnow = array( 'bing_post_types' => acme_seo_post_types() );
		acme_seo_merge_option( 'rank-math-options-instant-indexing', $indexnow );
		return 'IndexNow включён: новые и изменённые страницы отправляются Яндексу и Bing';
	}
	return '';
}
