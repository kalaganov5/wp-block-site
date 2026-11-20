<?php
/**
 * Описание страницы для поисковиков (acme-core/inc/seo/descriptions.php).
 */

require_once ACME_CORE_DIR . 'inc/seo/descriptions.php';

test(
	'короткий текст становится описанием целиком, без тегов и лишних пробелов',
	function () {
		expect_equal( 'Чиним сайты за день.', acme_seo_excerpt( "<p>Чиним   сайты\n за день.</p>" ) );
	}
);

test(
	'длинный текст обрезается по слову и не длиннее 155 символов с многоточием',
	function () {
		$description = acme_seo_excerpt( str_repeat( 'Разрабатываем сайты на WordPress. ', 12 ) );
		expect_true( mb_strlen( $description ) <= 156 );
		expect_equal( '…', mb_substr( $description, -1 ) );
		// Последнее слово целое: перед многоточием нет обрывка.
		expect_true( preg_match( '/(сайты|на|WordPress|Разрабатываем)…$/u', $description ) );
	}
);

test(
	'комментарии блоков Gutenberg в описание не попадают',
	function () {
		$html = "<!-- wp:paragraph {\"className\":\"a\"} -->\n<p>Текст.</p>\n<!-- /wp:paragraph -->";
		expect_equal( 'Текст.', acme_seo_excerpt( $html ) );
	}
);

test(
	'подзаголовок страницы — первый абзац с классом acme-lead',
	function () {
		$html = '<p>Редакция от 5 мая.</p><p class="acme-lead">Чиним сайты <b>за день</b>.</p><p>Дальше.</p>';
		expect_equal( 'Чиним сайты за день.', acme_seo_lead_text( $html ) );
		expect_equal( '', acme_seo_lead_text( '<p>Без подзаголовка.</p>' ) );
	}
);

test(
	'описание из текста начинается с первого абзаца длиннее 60 символов: «Редакция от…» пропускается',
	function () {
		$long = 'Этот документ описывает, какие данные собирает сайт и как их можно удалить по запросу.';
		$html = '<p>Редакция от 5 мая.</p><p>' . $long . '</p>';
		expect_equal( $long, acme_seo_main_paragraph( $html ) );
	}
);

test(
	'текст без длинных абзацев идёт в описание целиком',
	function () {
		expect_equal( 'Коротко. Ещё.', acme_seo_main_paragraph( '<p>Коротко.</p> <p>Ещё.</p>' ) );
	}
);

test(
	'описание по правилу: своё SEO-описание, затем подзаголовок, отрывок, начало текста',
	function () {
		$source = array(
			'own'     => 'Своё описание.',
			'lead'    => 'Подзаголовок.',
			'excerpt' => 'Отрывок.',
			'content' => '<p>Текст записи.</p>',
		);
		expect_equal( 'Своё описание.', acme_seo_description_by_rule( $source ) );
		$source['own'] = '';
		expect_equal( 'Подзаголовок.', acme_seo_description_by_rule( $source ) );
		$source['lead'] = '';
		expect_equal( 'Отрывок.', acme_seo_description_by_rule( $source ) );
		$source['excerpt'] = '';
		expect_equal( 'Текст записи.', acme_seo_description_by_rule( $source ) );
	}
);

test(
	'своё SEO-описание не обрезается, остальные источники — до 155 символов',
	function () {
		$long = str_repeat( 'слово ', 40 );
		expect_equal( trim( $long ), acme_seo_description_by_rule( array( 'own' => $long ) ) );
		expect_true( mb_strlen( acme_seo_description_by_rule( array( 'excerpt' => $long ) ) ) <= 156 );
	}
);

test(
	'на второй странице списка к описанию добавляется номер страницы с заглавной буквы',
	function () {
		expect_equal( 'Статьи о работе. Страница 2.', acme_seo_paged_description( 'Статьи о работе.', 'страница 2' ) );
		expect_equal( 'Статьи о работе.', acme_seo_paged_description( 'Статьи о работе.', '' ) );
		expect_equal( '', acme_seo_paged_description( '', 'страница 2' ) );
	}
);
