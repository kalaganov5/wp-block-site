<?php
/**
 * Настройки → Acme Site: контакты, адрес организации, коды вебмастеров, интеграции, служебные страницы.
 * Поля описаны массивом: новая настройка = строка в acme_settings_fields()
 * и значение в acme_settings_defaults().
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'acme_settings_menu' );
add_action( 'admin_init', 'acme_settings_register' );

/** Настройки по умолчанию («Настройки → Acme Site»). Тип значения задаёт санитайзер: число → (int). */
function acme_settings_defaults() {
	return array(
		'email'            => get_option( 'admin_email' ),
		'telegram_url'     => '',
		'social_urls'      => '',
		'area_served'      => '',
		'phone'            => '',
		'address_street'   => '',
		'address_locality' => '',
		'address_region'   => '',
		'address_postal'   => '',
		'address_country'  => '',
		'opening_hours'    => '',
		'geo'              => '',
		'verify_yandex'    => '',
		'verify_google'    => '',
		'verify_bing'      => '',
		'tg_bot_token'     => '',
		'tg_chat_id'       => '',
		'gtm_id'           => '',
		'gtm_test'         => 0,
	);
}

function acme_settings_fields() {
	return array(
		'Контакты' => array(
			'email'        => 'Email',
			'telegram_url' => 'Telegram: ссылка',
			'social_urls'  => 'Другие профили и каталоги: ссылки через пробел (в разметку организации)',
			'area_served'  => 'Где работаете: страна, город или «Весь мир» (в разметку услуг)',
		),
		'Адрес организации (заполнен — в разметке сайт становится местной компанией; пусто — не выводится)' => array(
			'phone'            => 'Телефон в международном формате: +7 900 000-00-00',
			'address_street'   => 'Улица, дом, офис',
			'address_locality' => 'Город',
			'address_region'   => 'Регион (необязательно)',
			'address_postal'   => 'Индекс',
			'address_country'  => 'Страна: код из двух букв (RU, KZ, DE)',
			'opening_hours'    => 'Часы работы: Mo-Fr 09:00-18:00; Sa 10:00-14:00',
			'geo'              => 'Координаты: широта, долгота (55.7963, 49.1088)',
		),
		'Подтверждение прав в вебмастерах (код или тег meta целиком; выводит Rank Math на главной)' => array(
			'verify_yandex' => 'Яндекс Вебмастер',
			'verify_google' => 'Google Search Console',
			'verify_bing'   => 'Bing Webmaster',
		),
		'Заявки с сайта (копия в Telegram; почту настраивает Contact Form 7)' => array(
			'tg_bot_token' => 'Telegram-бот: токен',
			'tg_chat_id'   => 'Telegram-бот: chat_id получателя',
		),
		'Аналитика (Google Tag Manager)' => array(
			'gtm_id'   => 'ID контейнера GTM (GTM-XXXXXXX; пусто — выключено)',
			'gtm_test' => 'Включить и на тестовом сайте: 1 — да, 0 — только на боевом',
		),
	);
}

/**
 * Служебные страницы: роль → подпись.
 * Код получает адрес по роли, а не по слагу: переименование не ломает ссылки.
 */
function acme_role_labels() {
	return array(
		'home'     => 'Главная',
		'about'    => 'О нас',
		'contacts' => 'Контакты',
		'blog'     => 'Блог',
		'privacy'  => 'Политика конфиденциальности',
	);
}

function acme_settings_menu() {
	add_options_page( 'Acme Site', 'Acme Site', 'manage_options', 'acme-settings', 'acme_settings_page' );
}

function acme_settings_register() {
	register_setting(
		'acme_settings',
		'acme_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'acme_settings_sanitize',
		)
	);
	register_setting(
		'acme_settings',
		'acme_roles',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'acme_roles_sanitize',
		)
	);
}

/** Сохраняются только известные настройки; тип берётся из значения по умолчанию. */
function acme_settings_sanitize( $input ) {
	$clean = array();
	foreach ( acme_settings_defaults() as $key => $default ) {
		$value         = isset( $input[ $key ] ) ? trim( wp_unslash( $input[ $key ] ) ) : '';
		$clean[ $key ] = is_int( $default ) ? (int) $value : sanitize_text_field( $value );
	}
	return $clean;
}

function acme_roles_sanitize( $input ) {
	return array_map( 'absint', (array) $input );
}

function acme_settings_page() {
	echo '<div class="wrap"><h1>Acme Site</h1>';
	echo '<p>Подписи интерфейса на всех языках правятся в «Языки → Перевод строк», группа <b>Acme Site</b>.</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'acme_settings' );
	acme_settings_render_fields();
	acme_settings_render_roles();
	submit_button();
	echo '</form></div>';
}

function acme_settings_render_fields() {
	$values = wp_parse_args( (array) get_option( 'acme_settings', array() ), acme_settings_defaults() );
	foreach ( acme_settings_fields() as $group => $fields ) {
		echo '<h2>' . esc_html( $group ) . '</h2><table class="form-table" role="presentation">';
		foreach ( $fields as $key => $label ) {
			printf(
				'<tr><th scope="row"><label for="acme-%1$s">%2$s</label></th>'
					. '<td><input type="%4$s" class="regular-text" id="acme-%1$s" name="acme_settings[%1$s]"'
					. ' value="%3$s" autocomplete="off"></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( (string) ( $values[ $key ] ?? '' ) ),
				// Токен бота не показывается на экране.
				esc_attr( false !== strpos( $key, 'token' ) ? 'password' : 'text' )
			);
		}
		echo '</table>';
	}
}

function acme_settings_render_roles() {
	$roles = (array) get_option( 'acme_roles', array() );
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'lang'           => acme_main_lang(),
		)
	);
	echo '<h2>Служебные страницы</h2>';
	echo '<p>Версии на основном языке; переводы подбираются через Polylang.</p>';
	echo '<table class="form-table" role="presentation">';
	foreach ( acme_role_labels() as $role => $label ) {
		$selected_id = (int) ( $roles[ $role ] ?? 0 );
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
		echo '<td><select name="acme_roles[' . esc_attr( $role ) . ']"><option value="0">—</option>';
		foreach ( $pages as $page ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $page->ID,
				selected( $selected_id, (int) $page->ID, false ),
				esc_html( $page->post_title )
			);
		}
		echo '</select></td></tr>';
	}
	echo '</table>';
}
