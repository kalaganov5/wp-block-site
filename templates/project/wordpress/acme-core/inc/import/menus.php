<?php
/**
 * Импорт, шаг «меню»: меню по областям темы и языкам.
 * Пункт: { role } (служебная страница), { key } (запись), { archive } или { url };
 * label — ключ строки интерфейса или текст.
 * Polylang хранит назначение меню по языкам в своей опции nav_menus.
 */

defined( 'ABSPATH' ) || exit;

function acme_import_menus( array $menus, array $post_ids ) {
	$theme          = get_stylesheet();
	$locations      = (array) get_theme_mod( 'nav_menu_locations', array() );
	$polylang_menus = array(); // [тема][область][язык] => ID меню
	foreach ( $menus as $location => $items ) {
		foreach ( ACME_LANGS as $lang ) {
			$menu_id = acme_import_menu( $location, $lang, $items, $post_ids );
			if ( ! $menu_id ) {
				continue;
			}
			// Область темы получает меню основного языка; остальные языки подставляет Polylang.
			if ( acme_main_lang() === $lang ) {
				$locations[ $location ] = $menu_id;
			}
			$polylang_menus[ $theme ][ $location ][ $lang ] = $menu_id;
		}
	}
	set_theme_mod( 'nav_menu_locations', $locations );
	if ( function_exists( 'PLL' ) && $polylang_menus ) {
		acme_import_save_polylang_menus( $polylang_menus );
	}
}

/** Создаёт меню области на языке со всеми пунктами; возвращает ID меню или 0, если создать не удалось. */
function acme_import_menu( $location, $lang, array $items, array $post_ids ) {
	$menu_id = wp_create_nav_menu( $location . ' · ' . strtoupper( $lang ) );
	if ( is_wp_error( $menu_id ) ) {
		return 0;
	}
	update_term_meta( $menu_id, 'acme_imported', 1 );
	foreach ( $items as $i => $item ) {
		$item_args = array(
			'menu-item-title'    => acme_import_menu_label( $item['label'], $lang ),
			'menu-item-status'   => 'publish',
			'menu-item-position' => $i + 1,
		);
		$item_id   = wp_update_nav_menu_item(
			$menu_id,
			0,
			$item_args + acme_import_menu_target( $item, $lang, $post_ids )
		);
		if ( ! is_wp_error( $item_id ) ) {
			update_post_meta( $item_id, 'acme_imported', 1 );
		}
	}
	return $menu_id;
}

/** Подпись пункта: перевод строки интерфейса, а если такой строки нет — сам текст. */
function acme_import_menu_label( $label, $lang ) {
	if ( ! function_exists( 'acme_t' ) ) {
		return $label;
	}
	$translated = acme_t( $label, $lang );
	return '' !== $translated ? $translated : $label;
}

/** Куда ведёт пункт: запись (по роли или ключу), архив типа записей или произвольный адрес. */
function acme_import_menu_target( array $item, $lang, array $post_ids ) {
	$post_id = 0;
	if ( ! empty( $item['role'] ) ) {
		$post_id = acme_role_id( $item['role'], $lang );
	} elseif ( ! empty( $item['key'] ) ) {
		$post_id = $post_ids[ $item['key'] ][ $lang ] ?? 0;
	}
	if ( $post_id ) {
		return array(
			'menu-item-object-id' => $post_id,
			'menu-item-object'    => get_post_type( $post_id ),
			'menu-item-type'      => 'post_type',
		);
	}
	if ( ! empty( $item['archive'] ) ) {
		return array(
			'menu-item-object' => $item['archive'],
			'menu-item-type'   => 'post_type_archive',
		);
	}
	return array(
		'menu-item-url'  => $item['url'] ?? '#',
		'menu-item-type' => 'custom',
	);
}

/** Назначение меню по языкам: Polylang 3.7+ — объект настроек, старые версии — опция polylang. */
function acme_import_save_polylang_menus( array $polylang_menus ) {
	$options = PLL()->options;
	if ( is_object( $options ) && $options instanceof ArrayAccess ) {
		$saved_menus          = (array) ( $options['nav_menus'] ?? array() );
		$options['nav_menus'] = array_replace_recursive( $saved_menus, $polylang_menus );
		if ( method_exists( $options, 'save' ) ) {
			$options->save();
		}
		return;
	}
	$legacy_options              = (array) get_option( 'polylang', array() );
	$legacy_options['nav_menus'] = array_replace_recursive(
		(array) ( $legacy_options['nav_menus'] ?? array() ),
		$polylang_menus
	);
	update_option( 'polylang', $legacy_options );
}
