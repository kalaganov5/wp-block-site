<?php
/**
 * wp acme import [--force]
 *
 * Ставит контент сайта из content/content.json (его собирает tools/build-content.js): языки Polylang,
 * медиа, разделы, страницы и записи на всех языках со связями переводов, роли служебных страниц,
 * главную и страницу блога, формы Contact Form 7, меню, редиректы, переводы строк интерфейса.
 *
 * Каждая созданная запись получает мету acme_imported и acme_key (стабильный ключ). --force удаляет только
 * их и создаёт заново: записи, созданные владельцем, не трогаются. Без --force повторный запуск отказывается.
 *
 * Здесь — только порядок шагов; каждый шаг живёт в своём файле этой папки и получает данные аргументами.
 * Карты «ключ → ID», которые шаги передают друг другу:
 *   $post_ids [ключ][язык] => ID записи;  $term_ids [ключ][язык] => ID термина;
 *   $media    ключ => [ id, url, sentinel ] (sentinel — временный ID картинки в разметке блоков).
 */

defined( 'ABSPATH' ) || exit;

class Acme_Import_Command {

	/**
	 * Импортирует контент сайта.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Удалить ранее импортированное и импортировать заново.
	 *
	 * @param array $args  Позиционные аргументы.
	 * @param array $assoc Флаги.
	 */
	public function import( $args, $assoc ) {
		$is_forced = ! empty( $assoc['force'] );
		$data      = $this->read_content();
		$this->check_ready( $is_forced );
		$this->prepare();

		if ( $is_forced ) {
			acme_import_cleanup();
		}
		acme_import_languages( $data['languages'] );
		acme_import_permalinks();
		$media    = acme_import_media( (array) ( $data['media'] ?? array() ) );
		$term_ids = acme_import_topics( (array) ( $data['topics'] ?? array() ) );
		$post_ids = acme_import_posts( $data['posts'], $term_ids );
		acme_import_resolve_content( $post_ids, $media );
		acme_import_options( $data, $post_ids );
		acme_import_cf7_forms();
		acme_import_menus( (array) ( $data['menus'] ?? array() ), $post_ids );
		if ( function_exists( 'acme_seed_string_translations' ) ) {
			acme_seed_string_translations();
		}

		$this->finish( (int) ( $data['version'] ?? 1 ) );
		$post_count = array_sum( array_map( 'count', $post_ids ) );
		WP_CLI::success( 'Готово: записей ' . $post_count . ', файлов ' . count( $media ) . '.' );
	}

	private function read_content() {
		$file = ACME_CORE_DIR . '/content/content.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- локальный файл плагина, WP_Filesystem не нужен
		$data = json_decode( (string) file_get_contents( $file ), true );
		if ( ! $data ) {
			WP_CLI::error( 'Не удалось прочитать ' . $file );
		}
		return $data;
	}

	/** Отказ до любых изменений: без Polylang переводы не связать, повторный импорт задвоил бы записи. */
	private function check_ready( $is_forced ) {
		if ( count( ACME_LANGS ) > 1 && ! function_exists( 'pll_set_post_language' ) ) {
			WP_CLI::error( 'Языков больше одного: нужен активный плагин Polylang.' );
		}
		if ( get_option( 'acme_imported' ) && ! $is_forced ) {
			WP_CLI::error( 'Контент уже импортирован. Чтобы перезаписать, запустите с --force.' );
		}
	}

	private function prepare() {
		// Автор записей и право на «сырой» HTML: в WP-CLI текущего пользователя нет.
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);
		if ( $admins ) {
			wp_set_current_user( $admins[0]->ID );
		}
		kses_remove_filters(); // иначе из разметки блоков вырежутся атрибуты
		wp_defer_term_counting( true );
	}

	private function finish( $content_version ) {
		wp_defer_term_counting( false );
		delete_option( 'rewrite_rules' ); // пересоберутся при следующем запросе, уже с правилами языков Polylang
		update_option( 'acme_imported', gmdate( 'c' ) );
		update_option( 'acme_content_version', $content_version );
	}
}

WP_CLI::add_command( 'acme', 'Acme_Import_Command' );
