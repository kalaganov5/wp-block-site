<?php
/**
 * Поддержка темы, меню, режим редактора, категория блоков, отключение лишнего от WordPress.
 * Стили и скрипты — inc/assets.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Типы записей, которые открываются в редакторе сразу вместе с шаблоном (шапка, подвал, крошки вокруг текста).
 * По умолчанию пусто: запись открывается обычным редактором — поле «Заголовок» и содержимое, ничего лишнего
 * вокруг. Так удобнее писать и править; шаблон включается в редакторе кнопкой «Показать шаблон» (панель записи →
 * «Шаблон» или меню «Просмотр»). Нужен шаблон сразу — впишите тип: array( 'page', 'acme_service' ).
 */
const ACME_TEMPLATE_MODE_POST_TYPES = array();

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		// Интервалы задаёт CSS темы (сетка и gap), генерация отступов «layout» от WordPress не нужна.
		add_theme_support( 'disable-layout-styles' );
		remove_theme_support( 'core-block-patterns' );
		register_nav_menus( acme_menu_locations() );
		// Редактор = сайт: те же общие стили на холсте, плюс поправки редактора.
		// Стили блоков темы редактор берёт сам из их block.json.
		add_editor_style( array_merge( acme_base_style_files(), array( 'assets/css/editor.css' ) ) );
	}
);

/** Области меню. Polylang даёт каждой области своё меню на каждом языке. */
function acme_menu_locations() {
	return array(
		'acme_header'      => 'Шапка',
		'acme_footer'      => 'Подвал',
		'acme_footer_docs' => 'Подвал: документы',
	);
}

add_action( 'registered_post_type', 'acme_set_editor_template_mode' );

/**
 * Включает показ шаблона по умолчанию для типов из ACME_TEMPLATE_MODE_POST_TYPES.
 * Хук registered_post_type — потому что Polylang регистрирует типы заново.
 */
function acme_set_editor_template_mode( $post_type ) {
	if ( ! in_array( $post_type, ACME_TEMPLATE_MODE_POST_TYPES, true ) ) {
		return;
	}
	// Поддержка editor хранится как true либо как [ массив настроек ]: свои настройки дописываем к имеющимся.
	$editor_support = get_all_post_type_supports( $post_type )['editor'] ?? true;
	$has_settings   = is_array( $editor_support ) && isset( $editor_support[0] ) && is_array( $editor_support[0] );
	$settings       = $has_settings ? $editor_support[0] : array();
	$settings       = array_merge( $settings, array( 'default-mode' => 'template-locked' ) );
	add_post_type_support( $post_type, 'editor', $settings );
}

// Категория блоков и паттернов темы в редакторе.
add_filter(
	'block_categories_all',
	function ( $categories ) {
		array_unshift(
			$categories,
			array(
				'slug'  => 'acme',
				'title' => 'Acme Site',
			)
		);
		return $categories;
	}
);
add_action(
	'init',
	function () {
		register_block_pattern_category( 'acme', array( 'label' => 'Acme Site' ) );
	}
);

// Эмодзи WordPress (скрипт и картинки с s.w.org) сайту не нужны.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
