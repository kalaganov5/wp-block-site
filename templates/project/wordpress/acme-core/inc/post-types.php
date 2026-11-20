<?php
/**
 * Типы записей и поля.
 *   acme_service — услуги (/services/…/), архив /services/ — каталог;
 *   acme_lead    — заявки с сайта (нельзя создать вручную, видны только в админке).
 * Таксономия acme_topic — разделы услуг (без своих страниц).
 *
 * Поля записей — без ACF: схема acme_meta_schema() → register_post_meta (REST) → панели в редакторе
 * (assets/meta-panel.js). Добавить поле = одна строка в схеме.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'acme_register_content_types', 5 );
add_action( 'init', 'acme_enable_page_excerpt' );
add_filter( 'manage_acme_lead_posts_columns', 'acme_lead_columns' );
add_action( 'manage_acme_lead_posts_custom_column', 'acme_lead_column_content', 10, 2 );

/**
 * Описание полей: тип записи → [ ключ => [ тип, подпись, параметры, панель ] ].
 * Типы: string, integer, boolean, date, service | post (выпадающий список записей → ID),
 * rows (повторяющиеся строки). Поля без панели — в панели «Поля сайта».
 */
function acme_meta_schema() {
	$update_panel = 'Обновление статьи';
	return array(
		'post'         => array(
			'acme_service' => array( 'service', 'Услуга по теме (пусто — подберётся сама)' ),
			'acme_updated' => array(
				'date',
				'Дата существенного обновления (пусто — не обновлялась)',
				null,
				$update_panel,
			),
			'acme_changes' => array(
				'rows',
				'Что изменилось, 1–4 строки',
				array(
					'max'    => 4,
					'fields' => array(
						array( 'text', 'Текст, до 80 символов', 'text' ),
						array( 'type', 'Тип', 'new:+ новый раздел|upd:↻ обновлено' ),
					),
				),
				$update_panel,
			),
		),
		'page'         => array(
			'acme_untranslated' => array( 'boolean', 'Текст ещё не переведён (noindex на этом языке)' ),
		),
		'acme_service' => array(
			'acme_short'        => array( 'string', 'Короткое название (меню, поиск)' ),
			'acme_price'        => array( 'string', 'Цена (можно метку {{price:ключ}})' ),
			'acme_blurb'        => array( 'string', 'Анонс в каталоге' ),
			'acme_kw'           => array( 'string', 'Слова для поиска и подбора к статьям' ),
			'acme_untranslated' => array( 'boolean', 'Текст ещё не переведён (noindex на этом языке)' ),
		),
	);
}

/** Регистрирует всё сразу: вызывается на init и при активации плагина (перед сбросом ЧПУ). */
function acme_register_content_types() {
	acme_register_service_type();
	acme_register_lead_type();
	acme_register_topic_taxonomy();
	acme_register_meta_fields();
}

function acme_register_service_type() {
	register_post_type(
		'acme_service',
		array(
			'labels'        => acme_labels( 'Услуги', 'Услуга' ),
			'public'        => true,
			'show_in_rest'  => true,
			'has_archive'   => 'services',
			'rewrite'       => array(
				'slug'       => 'services',
				'with_front' => false,
			),
			'menu_icon'     => 'dashicons-hammer',
			'menu_position' => 20,
			'supports'      => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'page-attributes',
				'custom-fields',
				'revisions',
			),
		)
	);
}

/** Заявки создаёт только форма на сайте: кнопки «Добавить» в админке нет (create_posts запрещён). */
function acme_register_lead_type() {
	register_post_type(
		'acme_lead',
		array(
			'labels'          => acme_labels( 'Заявки', 'Заявка' ),
			'public'          => false,
			'show_ui'         => true,
			'show_in_rest'    => false,
			'menu_icon'       => 'dashicons-email-alt',
			'menu_position'   => 25,
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}

function acme_register_topic_taxonomy() {
	register_taxonomy(
		'acme_topic',
		array( 'acme_service' ),
		array(
			'labels'            => array(
				'name'          => 'Разделы услуг',
				'singular_name' => 'Раздел',
				'menu_name'     => 'Разделы',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}

function acme_register_meta_fields() {
	foreach ( acme_meta_schema() as $post_type => $fields ) {
		foreach ( $fields as $meta_key => $field ) {
			register_post_meta( $post_type, $meta_key, acme_meta_field_args( $field ) );
		}
	}
}

/** Аргументы register_post_meta для поля схемы. */
function acme_meta_field_args( array $field ) {
	$field_type = $field[0];
	if ( 'rows' === $field_type ) {
		return acme_meta_rows_args( $field[2]['fields'] );
	}
	// Ссылка на запись хранится её ID, дата — строкой ГГГГ-ММ-ДД.
	$storage_types = array(
		'service' => 'integer',
		'post'    => 'integer',
		'date'    => 'string',
	);
	return array(
		'type'          => $storage_types[ $field_type ] ?? $field_type,
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => 'acme_can_edit_meta',
	);
}

/** Повторяющиеся строки — массив объектов: REST сохраняет его только со схемой элементов. */
function acme_meta_rows_args( array $row_fields ) {
	$properties = array();
	foreach ( $row_fields as $row_field ) {
		$properties[ $row_field[0] ] = array( 'type' => 'string' );
	}
	return array(
		'type'          => 'array',
		'single'        => true,
		'default'       => array(),
		'show_in_rest'  => array(
			'schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => $properties,
					'additionalProperties' => false,
				),
			),
		),
		'auth_callback' => 'acme_can_edit_meta',
	);
}

function acme_can_edit_meta() {
	return current_user_can( 'edit_posts' );
}

/** Отрывок у страниц: из него берётся описание для поисковиков. */
function acme_enable_page_excerpt() {
	add_post_type_support( 'page', 'excerpt' );
}

function acme_labels( $plural, $single ) {
	return array(
		'name'          => $plural,
		'singular_name' => $single,
		'add_new'       => 'Добавить',
		'add_new_item'  => 'Добавить: ' . mb_strtolower( $single ),
		'edit_item'     => 'Изменить: ' . mb_strtolower( $single ),
		'all_items'     => $plural,
		'search_items'  => 'Найти',
		'not_found'     => 'Ничего нет',
	);
}

/** Заявки: колонки в списке. */
function acme_lead_columns( $columns ) {
	return array(
		'cb'        => $columns['cb'],
		'title'     => 'Имя и контакт',
		'acme_page' => 'Страница',
		'date'      => 'Дата',
	);
}

/** Заявки: в колонке «Страница» — путь страницы, с которой отправлена форма. */
function acme_lead_column_content( $column, $post_id ) {
	if ( 'acme_page' !== $column ) {
		return;
	}
	$page_url = get_post_meta( $post_id, 'acme_page', true );
	if ( ! $page_url ) {
		return;
	}
	$page_path = (string) wp_parse_url( $page_url, PHP_URL_PATH );
	echo '<a href="' . esc_url( $page_url ) . '">' . esc_html( $page_path ) . '</a>';
}
