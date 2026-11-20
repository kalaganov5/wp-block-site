<?php
/**
 * Защита сайта без отдельного плагина: закрывает то, чем WordPress по умолчанию помогает взломщику.
 *
 * Каждая мера — отдельная функция и отключается одной строкой в проекте (например, в mu-plugin):
 *   add_filter( 'acme_security_xmlrpc', '__return_false' );
 * Имена мер: xmlrpc, users, version, app_passwords, login_errors, comments.
 *
 * Вторая половина защиты — на уровне сервера, её ставит деплой (wordpress/deploy/remote.sh):
 * заголовки и запрет служебных файлов в .htaccess (deploy/htaccess-security.conf), запрет PHP в uploads,
 * константы wp-config.php (DISALLOW_FILE_EDIT, FORCE_SSL_ADMIN, WP_AUTO_UPDATE_CORE) и права на файлы.
 *
 * DISALLOW_FILE_MODS намеренно не задаётся: он запретил бы и установку плагинов деплоем через WP-CLI,
 * и обновление плагинов владельцем в админке. Редактор файлов в админке закрыт (DISALLOW_FILE_EDIT).
 */

defined( 'ABSPATH' ) || exit;

/** Включена ли мера защиты. Спрашивается в момент срабатывания, поэтому фильтр можно добавить где угодно. */
function acme_security_is_on( $feature ) {
	return (bool) apply_filters( "acme_security_{$feature}", true );
}

// ---------- xmlrpc: XML-RPC и pingback ----------
// Через xmlrpc.php подбирают пароли (сотни попыток одним запросом) и используют сайт для DDoS через pingback.
// Редактор и REST API им не пользуются. Приложение WordPress для телефона — пользуется: нужно — отключите меру.
// Сам файл xmlrpc.php дополнительно закрыт в .htaccess: запрос не доходит до PHP.
add_filter( 'xmlrpc_enabled', 'acme_security_xmlrpc_enabled' );
add_filter( 'xmlrpc_methods', 'acme_security_xmlrpc_methods' );
add_filter( 'wp_headers', 'acme_security_remove_pingback_header' );
add_filter( 'pings_open', 'acme_security_pings_open' );
add_action( 'init', 'acme_security_remove_discovery_links' );

function acme_security_xmlrpc_enabled( $is_enabled ) {
	return acme_security_is_on( 'xmlrpc' ) ? false : $is_enabled;
}

/** xmlrpc_enabled закрывает только методы с паролем; pingback и system.multicall убираются списком методов. */
function acme_security_xmlrpc_methods( $methods ) {
	return acme_security_is_on( 'xmlrpc' ) ? array() : $methods;
}

function acme_security_remove_pingback_header( $headers ) {
	if ( acme_security_is_on( 'xmlrpc' ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}

function acme_security_pings_open( $is_open ) {
	return acme_security_is_on( 'xmlrpc' ) ? false : $is_open;
}

/** Ссылки в <head>, которые сообщают адрес xmlrpc.php (RSD — для старых настольных редакторов). */
function acme_security_remove_discovery_links() {
	if ( acme_security_is_on( 'xmlrpc' ) ) {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}
}

// ---------- users: логины пользователей не видны посетителю ----------
// Логин — половина пары для подбора пароля. WordPress отдаёт его всем: /wp-json/wp/v2/users и /?author=1.
// Вошедшим пользователям список нужен (редактор блоков выбирает автора записи) — для них всё работает.
// Архив автора закрыт настройками Rank Math, карта сайта авторов выключена (inc/seo/rank-math.php).
add_filter( 'rest_pre_dispatch', 'acme_security_rest_users', 10, 3 );
add_filter( 'request', 'acme_security_author_query' );

/** Запрос списка пользователей без входа — 401, как для любого закрытого адреса REST API. */
function acme_security_rest_users( $result, $server, $request ) {
	$is_users_route = 0 === strpos( $request->get_route(), '/wp/v2/users' );
	if ( ! $is_users_route || is_user_logged_in() || ! acme_security_is_on( 'users' ) ) {
		return $result;
	}
	return new WP_Error(
		'rest_login_required',
		'Список пользователей доступен только после входа.',
		array( 'status' => 401 )
	);
}

/** /?author=1 без входа — 404. Иначе WordPress перенаправит на /author/<логин>/ и выдаст логин в адресе. */
function acme_security_author_query( $query_vars ) {
	$is_author_number = isset( $query_vars['author'] ) && ! isset( $query_vars['author_name'] );
	if ( ! $is_author_number || is_admin() || is_user_logged_in() || ! acme_security_is_on( 'users' ) ) {
		return $query_vars;
	}
	unset( $query_vars['author'] );
	$query_vars['error'] = '404';
	return $query_vars;
}

// ---------- version: версия WordPress не видна в разметке ----------
// По версии подбирают известные уязвимости. Она выводится в <meta name="generator">, в лентах и в адресах
// стилей и скриптов ядра (?ver=7.1). readme.html и license.txt закрыты в .htaccess.
add_action( 'init', 'acme_security_remove_generator' );
add_filter( 'style_loader_src', 'acme_security_hide_core_version' );
add_filter( 'script_loader_src', 'acme_security_hide_core_version' );

function acme_security_remove_generator() {
	if ( acme_security_is_on( 'version' ) ) {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
	}
}

/**
 * ?ver=<версия WordPress> → ?ver=<отпечаток>. Отпечаток меняется вместе с версией, поэтому браузер
 * по-прежнему перезагружает файлы после обновления ядра, а посторонний версию по нему не узнает
 * (в отпечаток подмешан секретный ключ сайта). Версии темы и плагинов не трогаются.
 */
function acme_security_hide_core_version( $src ) {
	$core_version = get_bloginfo( 'version' );
	if ( ! is_string( $src ) || false === strpos( $src, 'ver=' . $core_version ) || is_admin() ) {
		return $src;
	}
	if ( ! acme_security_is_on( 'version' ) ) {
		return $src;
	}
	return add_query_arg( 'ver', substr( wp_hash( $core_version ), 0, 10 ), $src );
}

// ---------- app_passwords: пароли приложений ----------
// Пароль приложения — второй способ войти по REST API в обход формы входа (и двухфакторной защиты).
// Сайту они не нужны; понадобилась внешняя интеграция — define( 'ACME_APP_PASSWORDS', true ) в wp-config.php.
add_filter( 'wp_is_application_passwords_available', 'acme_security_app_passwords' );

function acme_security_app_passwords( $is_available ) {
	if ( defined( 'ACME_APP_PASSWORDS' ) && ACME_APP_PASSWORDS ) {
		return $is_available;
	}
	return acme_security_is_on( 'app_passwords' ) ? false : $is_available;
}

// ---------- login_errors: форма входа не подсказывает, существует ли пользователь ----------
// «Неизвестное имя пользователя» и «неверный пароль для пользователя admin» — два разных ответа, по которым
// логин проверяется перебором. Вместо них — один общий. Приоритет 99: после всех проверок WordPress и плагинов.
add_filter( 'authenticate', 'acme_security_login_error', 99 );
add_filter( 'shake_error_codes', 'acme_security_shake_codes' );

function acme_security_login_error( $user ) {
	$revealing_codes = array( 'invalid_username', 'invalid_email', 'incorrect_password' );
	if ( ! is_wp_error( $user ) || ! in_array( $user->get_error_code(), $revealing_codes, true ) ) {
		return $user;
	}
	if ( ! acme_security_is_on( 'login_errors' ) ) {
		return $user;
	}
	return new WP_Error( 'acme_login_failed', 'Неверное имя пользователя или пароль.' );
}

/** Форма входа «трясётся» при ошибке только для известных кодов — добавляем свой. */
function acme_security_shake_codes( $codes ) {
	$codes[] = 'acme_login_failed';
	return $codes;
}

// ---------- comments: если комментарии закрыты, их лента и trackback не нужны ----------
// Деплой закрывает комментарии при установке (default_comment_status = closed). Пока они закрыты, лента
// комментариев и адреса trackback — только мишень для спама. Владелец открыл комментарии — мера не действует.
add_filter( 'feed_links_show_comments_feed', 'acme_security_show_comments_feed' );
add_action( 'template_redirect', 'acme_security_block_comment_endpoints', 1 );

function acme_security_comments_are_closed() {
	return 'closed' === get_option( 'default_comment_status' ) && acme_security_is_on( 'comments' );
}

function acme_security_show_comments_feed( $is_shown ) {
	return acme_security_comments_are_closed() ? false : $is_shown;
}

/** Лента комментариев и trackback → 404 (приоритет 1: раньше, чем WordPress начнёт выводить ленту). */
function acme_security_block_comment_endpoints() {
	if ( ! ( is_comment_feed() || is_trackback() ) || ! acme_security_comments_are_closed() ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
