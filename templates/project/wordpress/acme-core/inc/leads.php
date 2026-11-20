<?php
/**
 * Заявки с формы Contact Form 7: свои тексты ошибок, антиспам без капчи, сохранение в «Заявки», копия в Telegram.
 * Формы (по одной на язык) создаёт импорт (inc/import/forms.php): опция acme_cf7_forms [язык => ID].
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wpcf7_autop_or_not', 'acme_lead_form_autop' );
add_filter( 'wpcf7_validate', 'acme_lead_validate_submission', 20, 2 );
add_filter( 'wpcf7_spam', 'acme_lead_is_spam', 10, 2 );
add_action( 'wpcf7_submit', 'acme_lead_handle_submit', 10, 2 );

function acme_cf7_forms() {
	return (array) get_option( 'acme_cf7_forms', array() );
}

function acme_is_lead_form( $form ) {
	return $form && in_array( (int) $form->id(), array_map( 'intval', acme_cf7_forms() ), true );
}

function acme_cf7_form_lang( $form ) {
	$lang = array_search( (int) $form->id(), array_map( 'intval', acme_cf7_forms() ), true );
	return $lang ? $lang : acme_main_lang();
}

/** Подпись на языке формы (строки темы, если тема активна). */
function acme_lead_str( $key, $lang ) {
	return function_exists( 'acme_t' ) ? acme_t( $key, $lang ) : $key;
}

/** Проверка полей: [ поле => ключ строки ошибки ]. */
function acme_lead_validate( array $fields ) {
	$errors = array();
	if ( mb_strlen( trim( $fields['name'] ) ) < 2 ) {
		$errors['name'] = 'eName';
	}
	$contact = trim( $fields['contact'] );
	if ( '' === $contact ) {
		$errors['contact'] = 'eContact';
	} elseif ( ! acme_lead_is_contact( $contact ) ) {
		$errors['contact'] = 'eContactBad';
	}
	return $errors;
}

/** Контакт — это e-mail, @username или телефон (не меньше 7 цифр); остальное — опечатка или мусор. */
function acme_lead_is_contact( $contact ) {
	$is_username = (bool) preg_match( '/^@?[a-z0-9_]{4,32}$/i', $contact );
	$is_phone    = strlen( preg_replace( '/\D+/', '', $contact ) ) >= 7;
	return is_email( $contact ) || $is_username || $is_phone;
}

/** Без автоматических <p> и <br>: вёрстка формы задана целиком. */
function acme_lead_form_autop( $autop ) {
	$form = class_exists( 'WPCF7_ContactForm' ) ? WPCF7_ContactForm::get_current() : null;
	return acme_is_lead_form( $form ) ? false : $autop;
}

/** Свои ошибки полей вместо стандартных CF7 — на языке формы. */
function acme_lead_validate_submission( $result, $tags ) {
	$submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
	if ( ! $submission || ! acme_is_lead_form( $submission->get_contact_form() ) ) {
		return $result;
	}
	$lang        = acme_cf7_form_lang( $submission->get_contact_form() );
	$posted      = $submission->get_posted_data();
	$errors      = acme_lead_validate(
		array(
			'name'    => (string) ( $posted['your-name'] ?? '' ),
			'contact' => (string) ( $posted['your-contact'] ?? '' ),
		)
	);
	$field_names = array(
		'your-name'    => 'name',
		'your-contact' => 'contact',
	);
	foreach ( $tags as $tag ) {
		$field = $field_names[ $tag->name ] ?? '';
		if ( isset( $errors[ $field ] ) ) {
			$result->invalidate( $tag, acme_lead_str( $errors[ $field ], $lang ) );
		}
	}
	return $result;
}

/** Антиспам: заполнено скрытое поле-ловушка или больше 5 отправок с одного IP за 10 минут. */
function acme_lead_is_spam( $is_spam, $submission ) {
	if ( $is_spam || ! acme_is_lead_form( $submission->get_contact_form() ) ) {
		return $is_spam;
	}
	$posted = $submission->get_posted_data();
	if ( ! empty( $posted['website-hp'] ) ) {
		return true;
	}
	$counter_key = 'acme_lead_' . md5( (string) $submission->get_meta( 'remote_ip' ) );
	$sent_count  = (int) get_transient( $counter_key );
	if ( $sent_count >= 5 ) {
		return true;
	}
	set_transient( $counter_key, $sent_count + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * Заявка сохраняется на сайте и копируется в Telegram (не задерживая посетителя) — и когда письмо ушло,
 * и когда почта не сработала (mail_failed): иначе заявка терялась бы именно тогда, когда страховка нужна.
 */
function acme_lead_handle_submit( $form, $result ) {
	$is_accepted = in_array( $result['status'] ?? '', array( 'mail_sent', 'mail_failed' ), true );
	if ( ! acme_is_lead_form( $form ) || ! $is_accepted ) {
		return;
	}
	$lead = acme_lead_from_submission( $form );
	acme_lead_save( $lead );
	acme_lead_notify_telegram( $lead );
}

/** Поля заявки из отправленной формы: [ name, contact, msg, page, lang ]. */
function acme_lead_from_submission( $form ) {
	$submission = WPCF7_Submission::get_instance();
	$posted     = $submission ? $submission->get_posted_data() : array();
	return array(
		'name'    => sanitize_text_field( $posted['your-name'] ?? '' ),
		'contact' => sanitize_text_field( $posted['your-contact'] ?? '' ),
		'msg'     => sanitize_textarea_field( $posted['your-message'] ?? '' ),
		'page'    => $submission ? esc_url_raw( $submission->get_meta( 'url' ) ) : '',
		'lang'    => acme_cf7_form_lang( $form ),
	);
}

function acme_lead_save( array $lead ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'acme_lead',
			'post_status'  => 'private',
			'post_title'   => wp_slash( $lead['name'] . ' — ' . $lead['contact'] ),
			'post_content' => wp_slash( $lead['msg'] ),
		)
	);
	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return;
	}
	foreach ( array( 'contact', 'page', 'lang' ) as $field ) {
		update_post_meta( $post_id, 'acme_' . $field, $lead[ $field ] );
	}
}

/** Копия заявки в Telegram, если в настройках заданы бот и получатель. */
function acme_lead_notify_telegram( array $lead ) {
	$token   = acme_setting( 'tg_bot_token' );
	$chat_id = acme_setting( 'tg_chat_id' );
	if ( ! $token || ! $chat_id ) {
		return;
	}
	$lines = array(
		'Новая заявка с сайта',
		'Имя: ' . $lead['name'],
		'Контакт: ' . $lead['contact'],
		$lead['msg'] ? 'Сообщение: ' . $lead['msg'] : '',
		'Страница: ' . $lead['page'] . ' (' . strtoupper( $lead['lang'] ) . ')',
	);
	wp_remote_post(
		'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
		array(
			'timeout'  => 5,
			// Ответ Telegram не ждём: посетитель не должен ждать чужой сервер.
			'blocking' => false,
			'body'     => array(
				'chat_id' => $chat_id,
				'text'    => implode( "\n", array_filter( $lines ) ),
				'disable_web_page_preview' => 'true',
			),
		)
	);
}
