<?php
/**
 * Стили и скрипты темы на сайте и в редакторе, тёмная тема без мигания, preload шрифтов.
 *
 * Стили делятся на общие (assets/css, нужны каждой странице) и стили блоков (blocks/<имя>/style.css):
 * файл блока объявлен в его block.json, и WordPress подключает его только там, где блок выведен.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Общие стили в порядке каскада: каждый следующий может опираться на предыдущий.
 * fonts — @font-face; tokens — переменные дизайна; base — основа и типографика;
 * core-blocks — вид core-блоков; components — то, что делят несколько блоков.
 */
const ACME_BASE_STYLES = array( 'fonts', 'tokens', 'base', 'core-blocks', 'components' );

/**
 * Файлы шрифтов для preload — только основное начертание основных алфавитов
 * (лишний preload отнимает полосу у LCP).
 * Например: array( 'onest-cyrillic-wght-normal.woff2', 'onest-latin-wght-normal.woff2' ).
 */
const ACME_PRELOAD_FONTS = array();

/** Пути общих стилей от корня темы — для сайта и для add_editor_style(). */
function acme_base_style_files() {
	$files = array();
	foreach ( ACME_BASE_STYLES as $name ) {
		$files[] = 'assets/css/' . $name . '.css';
	}
	return $files;
}

// Стили core-блоков грузим только для блоков на странице.
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Версия файла темы: меняется с каждым деплоем, поэтому браузер может кешировать статику на год
 * (.htaccess, блок «acme cache»).
 */
function acme_asset_ver( $file ) {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors -- файла может не быть: версия без времени правки лучше предупреждения.
	$modified_at = (int) @filemtime( get_theme_file_path( $file ) );
	return ACME_THEME_VERSION . '.' . $modified_at;
}

add_action( 'wp_enqueue_scripts', 'acme_enqueue_site_assets' );

function acme_enqueue_site_assets() {
	foreach ( acme_base_style_files() as $file ) {
		$handle = 'acme-' . basename( $file, '.css' );
		wp_enqueue_style( $handle, get_theme_file_uri( $file ), array(), acme_asset_ver( $file ) );
	}
	acme_move_block_styles_after_base();
	acme_enqueue_site_script();
}

/**
 * Стили блоков темы должны идти после общих: при равной специфичности побеждает правило блока
 * (например, цвет ссылок подвала против общего a:hover).
 * WordPress ставит стиль блока в очередь в момент вывода блока, а блочная тема выводит весь шаблон до wp_head, —
 * то есть раньше, чем сюда попадают общие стили. Переставляем уже поставленные стили блоков темы в конец очереди.
 * Сами стили при этом остаются в <head>: шапка получает оформление до первой отрисовки.
 */
function acme_move_block_styles_after_base() {
	foreach ( wp_styles()->queue as $handle ) {
		if ( 0 !== strpos( $handle, 'acme-' ) || '-style' !== substr( $handle, -6 ) ) {
			continue;
		}
		wp_dequeue_style( $handle );
		wp_enqueue_style( $handle );
	}
}

add_filter( 'block_type_metadata', 'acme_set_block_asset_version' );

/**
 * Версия стилей и скриптов блока темы = версия темы + время последней правки файлов блока.
 * Без неё WordPress подставил бы свою версию, и после деплоя браузер год показывал бы старый CSS блока из кеша.
 */
function acme_set_block_asset_version( $metadata ) {
	$block_file = wp_normalize_path( $metadata['file'] ?? '' );
	$blocks_dir = wp_normalize_path( get_theme_file_path( 'blocks/' ) );
	if ( '' === $block_file || 0 !== strpos( $block_file, $blocks_dir ) ) {
		return $metadata;
	}
	$modified_at = 0;
	foreach ( (array) glob( dirname( $block_file ) . '/*' ) as $file ) {
		$modified_at = max( $modified_at, (int) filemtime( $file ) );
	}
	$metadata['version'] = ACME_THEME_VERSION . '.' . $modified_at;
	return $metadata;
}

/** Скрипт сайта и его данные (подписи на языке страницы). $is_editor — внутри холста редактора. */
function acme_enqueue_site_script( $is_editor = false ) {
	wp_enqueue_script(
		'acme-site',
		get_theme_file_uri( 'assets/js/site.js' ),
		array(),
		acme_asset_ver( 'assets/js/site.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
	$lang   = acme_cur_lang();
	$labels = array();
	// Только ключи, которые нужны скрипту.
	foreach ( array( 'menu', 'close', 'sending', 'sendError' ) as $key ) {
		$labels[ $key ] = acme_t( $key, $lang );
	}
	$data = array(
		'lang' => $lang,
		's'    => $labels,
	);
	if ( $is_editor ) {
		$data['editor'] = true;
	}
	wp_add_inline_script( 'acme-site', 'window.ACME = ' . wp_json_encode( $data ) . ';', 'before' );
}

// Редактор: тот же скрипт внутри холста — превью блоков получают то же поведение, что на сайте (слайдеры).
// Меню и тему скрипт в редакторе не трогает (assets/js/site.js); окно cookie — только на сайте (view.js подвала).
add_action(
	'enqueue_block_assets',
	function () {
		if ( is_admin() ) {
			acme_enqueue_site_script( true );
		}
	}
);

add_action( 'wp_head', 'acme_print_theme_script', 1 );
add_action( 'wp_head', 'acme_print_font_preloads', 1 );

/**
 * Тема (светлая / тёмная / как в системе) ставится до отрисовки, чтобы не было мигания.
 * Поэтому скрипт встроен в head и не вынесен в файл; он крошечный и собран из частей только ради длины строки.
 */
function acme_print_theme_script() {
	echo '<script>(function(){try{',
		"var t=localStorage.getItem('acme-theme');",
		"var d=t==='dark'||(t!=='light'&&matchMedia('(prefers-color-scheme: dark)').matches);",
		'var r=document.documentElement;',
		"r.dataset.theme=d?'dark':'light';",
		"r.style.colorScheme=d?'dark':'light';",
		'}catch(e){}})();</script>',
		"\n";
}

function acme_print_font_preloads() {
	foreach ( ACME_PRELOAD_FONTS as $font_file ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_theme_file_uri( 'assets/fonts/' . $font_file ) )
		);
	}
}
