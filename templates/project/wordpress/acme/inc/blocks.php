<?php
/**
 * Блоки темы (папка blocks/, рендер на сервере) и доработка разметки core-блоков.
 * Новый блок = папка blocks/<имя>/ с block.json и render.php; регистрировать и писать JS не нужно.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'acme_register_blocks' );
add_action( 'init', 'acme_register_block_styles' );
add_filter( 'rest_request_before_callbacks', 'acme_set_block_preview_query', 10, 3 );
add_filter( 'render_block', 'acme_render_block_markup', 10, 2 );

/**
 * Регистрирует все блоки из blocks/ и общий для них редактор (assets/js/editor.js):
 * панель настроек строится по атрибутам block.json, превью приходит с сервера.
 */
function acme_register_blocks() {
	$editor_dependencies = array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-server-side-render',
		'wp-data',
		'wp-core-data',
	);
	wp_register_script(
		'acme-editor',
		get_theme_file_uri( 'assets/js/editor.js' ),
		$editor_dependencies,
		acme_asset_ver( 'assets/js/editor.js' ),
		true
	);
	$block_names = array();
	foreach ( glob( get_theme_file_path( 'blocks/*/block.json' ) ) as $block_json ) {
		$block_type = register_block_type( dirname( $block_json ) );
		if ( $block_type ) {
			$block_names[] = $block_type->name;
		}
	}
	// Редактору нужен список блоков темы: по нему он подключает к ним общую панель и превью.
	wp_add_inline_script(
		'acme-editor',
		'window.ACME_BLOCKS = ' . wp_json_encode( $block_names ) . ';',
		'before'
	);
}

/** Стили core-блоков из дизайн-системы: выбор в панели «Стили» → класс is-style-<имя>. */
function acme_register_block_styles() {
	$styles_by_block = array(
		'core/button' => array(
			'primary'   => 'Основная',
			'secondary' => 'Вторичная',
			'ghost'     => 'Текстовая',
		),
		'core/list'   => array(
			'facts' => 'Факты (галочки)',
			'steps' => 'Шаги с номером',
		),
		'core/group'  => array( 'card' => 'Карточка' ),
		'core/table'  => array( 'stack' => 'Карточками на телефоне' ),
	);
	foreach ( $styles_by_block as $block_name => $styles ) {
		foreach ( $styles as $style_name => $style_label ) {
			register_block_style(
				$block_name,
				array(
					'name'  => $style_name,
					'label' => $style_label,
				)
			);
		}
	}
}

/**
 * Превью блоков в редакторе (REST /block-renderer) идёт без «текущей страницы»: is_singular(),
 * get_queried_object_id() и is_front_page() там пустые, и хлебные крошки и т. п. выглядели бы иначе, чем на сайте.
 * Делаем редактируемую запись (post_id, его передаёт редактор) текущей — как при открытии её на сайте.
 */
function acme_set_block_preview_query( $response, $handler, $request ) {
	$post_id          = (int) $request->get_param( 'post_id' );
	$is_block_preview = 0 === strpos( $request->get_route(), '/wp/v2/block-renderer/' );
	if ( ! $post_id || ! $is_block_preview || ! current_user_can( 'edit_post', $post_id ) ) {
		return $response;
	}
	$post_type     = get_post_type( $post_id );
	$id_query_var  = 'page' === $post_type ? 'page_id' : 'p';
	$preview_query = new WP_Query(
		array(
			'post_type'   => $post_type,
			'post_status' => 'any',
			'lang'        => '',
			$id_query_var => $post_id,
		)
	);
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride -- подмена основного запроса и есть цель функции.
	$GLOBALS['wp_query'] = $preview_query;
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride -- то же: is_singular() и т. п. смотрят в основной запрос.
	$GLOBALS['wp_the_query'] = $preview_query;
	return $response;
}

/** Доработка готовой разметки core-блоков по их классу (className). */
function acme_render_block_markup( $html, $block ) {
	$block_name = $block['blockName'] ?? '';
	$css_class  = $block['attrs']['className'] ?? '';

	if ( 'core/table' === $block_name && false !== strpos( $css_class, 'is-style-stack' ) ) {
		$html = acme_table_with_cell_labels( $html );
	}

	// Фрагмент на основном языке на страницах других языков (текст ещё не переведён):
	// lang нужен для переносов и экранных дикторов.
	if ( false !== strpos( $css_class, 'acme-lang-ru' ) && 'ru' !== acme_cur_lang() ) {
		$html = acme_markup_with_lang( $html, 'ru' );
	}
	return $html;
}

/** Подписи колонок таблицы — тексты ячеек её шапки (thead). Без шапки — пустой массив. */
function acme_table_column_labels( $html ) {
	if ( ! preg_match( '#<thead>(.*?)</thead>#s', $html, $head ) ) {
		return array();
	}
	if ( ! preg_match_all( '#<th[^>]*>(.*?)</th>#s', $head[1], $head_cells ) ) {
		return array();
	}
	return array_map( 'wp_strip_all_tags', $head_cells[1] );
}

/**
 * Таблица «карточками на телефоне»: подпись колонки в data-label каждой ячейки
 * (CSS выводит её перед значением, когда строки таблицы становятся карточками).
 */
function acme_table_with_cell_labels( $html ) {
	$labels = acme_table_column_labels( $html );
	$tags   = new WP_HTML_Tag_Processor( $html );
	$column = 0;
	while ( $tags->next_tag() ) {
		$tag = $tags->get_tag();
		if ( 'TR' === $tag ) {
			$column = 0;
			continue;
		}
		if ( ( 'TD' !== $tag && 'TH' !== $tag ) || $tags->is_tag_closer() ) {
			continue;
		}
		// Ячейки-заголовки (th) подписывать не нужно, но колонку они занимают.
		if ( 'TD' === $tag && isset( $labels[ $column ] ) ) {
			$tags->set_attribute( 'data-label', $labels[ $column ] );
		}
		++$column;
	}
	return $tags->get_updated_html();
}

/** Ставит атрибут lang первому тегу разметки блока. */
function acme_markup_with_lang( $html, $lang ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	if ( ! $tags->next_tag() ) {
		return $html;
	}
	$tags->set_attribute( 'lang', $lang );
	return $tags->get_updated_html();
}
