<?php
/**
 * Заголовок страницы для поисковиков (acme-core/inc/seo/titles.php).
 */

require_once ACME_CORE_DIR . 'inc/seo/titles.php';

test(
	'заголовок длиннее 60 символов теряет хвост с именем сайта',
	function () {
		$headline = 'Как выбрать подрядчика для сайта и не потерять деньги и время';
		expect_equal( $headline, acme_seo_trim_title( $headline . ' | Acme Site', array( 'Acme Site' ) ) );
		expect_equal( $headline, acme_seo_trim_title( $headline . ' — Acme Site', array( 'Acme Site' ) ) );
	}
);

test(
	'заголовок в пределах 60 символов остаётся с именем сайта',
	function () {
		expect_equal( 'Контакты | Acme Site', acme_seo_trim_title( 'Контакты | Acme Site', array( 'Acme Site' ) ) );
	}
);

test(
	'длинный заголовок без имени сайта в конце не меняется',
	function () {
		$title = str_repeat( 'Очень длинный заголовок ', 4 ) . '| Другое имя';
		expect_equal( $title, acme_seo_trim_title( $title, array( 'Acme Site' ) ) );
	}
);

test(
	'хвост не убирается, если без него от заголовка останется меньше 10 символов',
	function () {
		$site_name = str_repeat( 'Имя', 20 );
		$title     = 'Блог | ' . $site_name;
		expect_equal( $title, acme_seo_trim_title( $title, array( $site_name ) ) );
	}
);

test(
	'номер страницы списка: первая страница — пусто, вторая — «страница 2»',
	function () {
		expect_equal( '', acme_seo_page_label( 1, 'страница {n}' ) );
		expect_equal( '', acme_seo_page_label( 0, 'страница {n}' ) );
		expect_equal( 'страница 2', acme_seo_page_label( 2, 'страница {n}' ) );
		expect_equal( '', acme_seo_page_label( 2, '' ) );
	}
);

test(
	'номер страницы встаёт перед именем сайта, а без имени — в конец заголовка',
	function () {
		expect_equal( 'Блог — страница 2 | Acme Site', acme_seo_paged_title( 'Блог | Acme Site', 'страница 2' ) );
		expect_equal( 'Блог — страница 2', acme_seo_paged_title( 'Блог', 'страница 2' ) );
	}
);

test(
	'номер страницы не добавляется дважды и не добавляется на первой странице',
	function () {
		expect_equal( 'Блог — страница 2', acme_seo_paged_title( 'Блог — страница 2', 'страница 2' ) );
		expect_equal( 'Блог | Acme Site', acme_seo_paged_title( 'Блог | Acme Site', '' ) );
	}
);
