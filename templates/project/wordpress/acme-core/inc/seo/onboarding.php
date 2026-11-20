<?php
/**
 * Rank Math без первого запуска: сайт настраивает код (options.php), поэтому владелец не должен видеть
 * мастер настройки, просьбу подключить аккаунт, сбор статистики и рекламные уведомления.
 *
 * Имена опций сверены с исходниками Rank Math 1.0.282 (файл и строка — у каждой опции ниже). При обновлении
 * Rank Math незнакомая ему опция просто перестанет действовать: сайт от этого не ломается.
 */

defined( 'ABSPATH' ) || exit;

/** Выполняется при каждом wp acme seo: шаги идемпотентны. Возвращает список сделанного. */
function acme_seo_onboarding() {
	$log = array();
	// includes/helpers/class-conditional.php: is_invalid_registration(). Без этого Rank Math требует
	// подключить аккаунт и не выводит ни описаний, ни карты сайта, ни разметки.
	if ( ! get_option( 'rank_math_registration_skip' ) ) {
		update_option( 'rank_math_registration_skip', 1 );
		$log[] = 'Rank Math включён без подключения аккаунта';
	}
	// includes/admin/class-registration.php: redirect_to_welcome() ведёт в мастер, пока опции нет;
	// includes/helpers/class-conditional.php: is_configured() — предупреждение «плагин не настроен».
	if ( ! get_option( 'rank_math_wizard_completed' ) || ! get_option( 'rank_math_is_configured' ) ) {
		update_option( 'rank_math_wizard_completed', true );
		update_option( 'rank_math_is_configured', true, false );
		$log[] = 'мастер настройки Rank Math пропущен';
	}
	// includes/class-installer.php ставит этот транзиент при активации: первый вход в админку уводит в мастер.
	delete_transient( '_rank_math_activation_redirect' );
	// vendor/wp-media/wp-mixpanel/src/Optin.php: статистика использования отправляется, только пока опция есть.
	delete_option( 'rank_math_mixpanel_optin' );
	acme_seo_mute_notices();
	return $log;
}

/** Уведомления, которые не относятся к работе сайта: просьба об отзыве, реклама PRO, «новый тип записей». */
function acme_seo_mute_notices() {
	// includes/admin/class-ask-review.php и class-pro-notice.php: уведомление добавляется, пока опции нет.
	foreach ( array( 'rank_math_review_notice_added', 'rank_math_pro_notice_added' ) as $option ) {
		if ( false === get_option( $option ) ) {
			update_option( $option, '1', false );
		}
	}
	// includes/admin/class-notices.php: new_post_type() сравнивает типы записей с этим списком.
	// Услуги (acme_service) настроены кодом — напоминание проверить их настройки не нужно.
	$known_types = (array) get_option( 'rank_math_known_post_types', array() );
	$all_types   = array_merge( $known_types, get_post_types( array( 'public' => true ) ) );
	if ( $all_types !== $known_types ) {
		update_option( 'rank_math_known_post_types', $all_types );
	}
}
