<?php
/**
 * Доступ к данным плагина acme-core: настройки сайта и адреса служебных страниц.
 * Тема обязана работать без плагина, поэтому блоки обращаются к нему только через эти функции:
 * без плагина они возвращают пустую строку или адрес главной, а не ошибку.
 */

defined( 'ABSPATH' ) || exit;

function acme_has_core() {
	return function_exists( 'acme_setting' );
}

/** Значение настройки сайта (email, telegram_url…) или '' без плагина. */
function acme_set( $key ) {
	return acme_has_core() ? acme_setting( $key ) : '';
}

/** Адрес служебной страницы по роли (contacts, about, blog…) на текущем языке. */
function acme_url( $role ) {
	return acme_has_core() ? acme_role_url( $role ) : home_url( '/' );
}

/** Главная на текущем языке. */
function acme_home() {
	return acme_has_core() ? acme_home_url() : home_url( '/' );
}
