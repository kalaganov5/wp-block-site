<?php
/**
 * Импорт, шаг «формы»: формы заявки Contact Form 7, по одной на язык. Потом их можно править в CF7.
 * ID форм — опция acme_cf7_forms [язык => ID]; обработка отправленных заявок — inc/leads.php.
 */

defined( 'ABSPATH' ) || exit;

function acme_import_cf7_forms() {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! function_exists( 'acme_t' ) ) {
		WP_CLI::warning(
			'Contact Form 7 или тема не активны: формы заявки не созданы. Активируйте и запустите импорт с --force.'
		);
		return;
	}
	$form_ids = array();
	foreach ( ACME_LANGS as $lang ) {
		$form_ids[ $lang ] = acme_import_cf7_form( $lang );
	}
	update_option( 'acme_cf7_forms', $form_ids );
	WP_CLI::log( 'Формы CF7: ' . implode( ', ', $form_ids ) );
}

/** Создаёт форму заявки на языке и возвращает её ID. */
function acme_import_cf7_form( $lang ) {
	$label = function ( $key ) use ( $lang ) {
		return acme_t( $key, $lang );
	};
	$form  = WPCF7_ContactForm::get_template( array( 'title' => 'Заявка · ' . strtoupper( $lang ) ) );
	$form->set_properties(
		array(
			'form'                => acme_cf7_form_body( $label, acme_role_url( 'privacy', $lang ) ),
			'mail'                => acme_import_cf7_mail( $form->prop( 'mail' ), $lang ),
			'additional_settings' => 'acceptance_as_validation: on',
		)
	);
	$form_id = $form->save();
	update_post_meta( $form_id, 'acme_imported', 1 );
	return (int) $form_id;
}

/** Письмо владельцу сайта: шаблон CF7 с нашими темой, отправителем и текстом. */
function acme_import_cf7_mail( $mail, $lang ) {
	$mail['subject']            = 'Заявка с сайта: [your-name]';
	$mail['sender']             = '[_site_title] <wordpress@[_site_domain]>';
	$mail['recipient']          = '[_site_admin_email]';
	$mail['additional_headers'] = '';
	$mail['body']               = "Имя: [your-name]\nКонтакт: [your-contact]\n\n[your-message]\n\n"
		. 'Язык: ' . strtoupper( $lang ) . "\nСтраница: [_url]";
	return $mail;
}

/**
 * Шаблон формы CF7 для языка.
 * $label — функция подписи по ключу, $privacy_url — адрес политики на языке формы.
 */
function acme_cf7_form_body( callable $label, $privacy_url ) {
	// Кавычки внутри подписи сломали бы тег CF7, поэтому вырезаются.
	$quote = function ( $text ) {
		return '"' . str_replace( '"', '', $text ) . '"';
	};
	$agree = str_replace( '{url}', esc_url( $privacy_url ), $label( 'fAgree' ) );
	return implode(
		"\n",
		array(
			'<label>' . $label( 'fName' ) . ' [text* your-name autocomplete:name]</label>',
			'<label>' . $label( 'fContact' ) . ' [text* your-contact placeholder ' . $quote( $label( 'fContactPh' ) )
				. ']</label>',
			'<label>' . $label( 'fMessage' ) . ' [textarea your-message x4]</label>',
			// Поле-ловушка: человек его не видит, бот заполняет (inc/leads.php, антиспам).
			'<span class="acme-hp" aria-hidden="true">[text website-hp tabindex:-1 autocomplete:off]</span>',
			'[acceptance agree] ' . $agree . ' [/acceptance]',
			'[submit ' . $quote( $label( 'fSubmit' ) ) . ']',
		)
	);
}
