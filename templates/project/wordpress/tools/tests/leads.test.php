<?php
/**
 * Заявка с формы: что считается заполненным именем и контактом (acme-core/inc/leads.php).
 */

require_once ACME_CORE_DIR . 'inc/leads.php';

test(
	'имя короче двух букв — ошибка имени',
	function () {
		$errors = acme_lead_validate(
			array(
				'name'    => ' А ',
				'contact' => 'anna@example.com',
			)
		);
		expect_equal( array( 'name' => 'eName' ), $errors );
	}
);

test(
	'пустой контакт — ошибка «укажите контакт», а не «неверный контакт»',
	function () {
		$errors = acme_lead_validate(
			array(
				'name'    => 'Анна',
				'contact' => '   ',
			)
		);
		expect_equal( 'eContact', $errors['contact'] );
	}
);

test(
	'e-mail, ник Telegram и телефон принимаются как контакт',
	function () {
		foreach ( array( 'anna@example.com', '@anna_dev', '+7 (900) 123-45-67' ) as $contact ) {
			$errors = acme_lead_validate(
				array(
					'name'    => 'Анна',
					'contact' => $contact,
				)
			);
			expect_equal( array(), $errors );
		}
	}
);

test(
	'текст без e-mail, ника и телефона — ошибка «неверный контакт»',
	function () {
		$errors = acme_lead_validate(
			array(
				'name'    => 'Анна',
				'contact' => 'позвоните мне, пожалуйста',
			)
		);
		expect_equal( 'eContactBad', $errors['contact'] );
	}
);
