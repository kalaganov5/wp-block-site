<?php
/**
 * Настройки Rank Math версии 1 — по функции на группу. Ставятся один раз (см. rank-math.php):
 * дальше владелец меняет их в админке. Ключи опций сверены с исходниками Rank Math 1.0.282.
 */

defined( 'ABSPATH' ) || exit;

/** Типы записей сайта, у которых есть свои страницы. */
function acme_seo_post_types() {
	return array( 'post', 'page', 'acme_service' );
}

function acme_seo_options_v1( array $covers ) {
	acme_seo_modules_v1();
	acme_seo_general_v1();
	acme_seo_titles_v1( $covers );
	acme_seo_sitemap_v1();
}

/**
 * Модули. Включены только те, что нужны сайту; выключены те, что требуют аккаунта Rank Math,
 * нагружают сохранение записей или дублируют сделанное в каркасе. Остальные (например, redirections,
 * если владелец включил) не трогаем. IndexNow включается отдельно — indexnow.php.
 */
function acme_seo_modules_v1() {
	$enabled  = array(
		'sitemap',      // карта сайта
		'rich-snippet', // JSON-LD
		'llms-txt',     // llms.txt для ИИ-ассистентов
	);
	$disabled = array(
		'analytics',     // нужен аккаунт Rank Math и доступ к Search Console
		'content-ai',    // нужен аккаунт и платные кредиты
		'ai-visibility', // нужен аккаунт
		'seo-analysis',  // разовый аудит в админке: его делает tools/qa/seo-crawl.js
		'link-counter',  // пересчёт ссылок при каждом сохранении записи и две таблицы; сирот ищет seo-crawl.js
		'404-monitor',   // пишет в базу каждый 404; редиректы каркаса работают без него (inc/redirects.php)
		'image-seo',     // подставляет alt на лету: alt обязан быть в контенте (проверяет сборщик и SEO-гейт)
		'local-seo',     // адрес и часы работы выводит local-business.php из настроек сайта
		'woocommerce',
		'buddypress',
		'bbpress',
		'acf',
		'web-stories',
	);
	$modules  = array_diff( (array) get_option( 'rank_math_modules', array() ), $disabled );
	update_option( 'rank_math_modules', array_values( array_unique( array_merge( $modules, $enabled ) ) ) );
}

/** Общие: хлебные крошки выводит тема, у вложений нет своих страниц; состав llms.txt. */
function acme_seo_general_v1() {
	acme_seo_merge_option(
		'rank-math-options-general',
		array(
			'breadcrumbs'              => 'off',
			'attachment_redirect_urls' => 'on',
			'llms_post_types'          => acme_seo_post_types(),
			'llms_taxonomies'          => array( 'category' ),
			'llms_limit'               => 200,
			'llms_summary'             => get_bloginfo( 'description' ),
		)
	);
	flush_rewrite_rules( false ); // правило /llms.txt появляется после пересборки ЧПУ
}

/** Заголовки и сущность сайта: кто стоит за сайтом, что не индексируется, обложка и логотип. */
function acme_seo_titles_v1( array $covers ) {
	$settings = (array) get_option( 'acme_settings', array() );
	$titles   = array(
		'title_separator'            => '|',
		'knowledgegraph_type'        => ACME_SEO_ENTITY,
		'knowledgegraph_name'        => get_bloginfo( 'name' ),
		'website_name'               => get_bloginfo( 'name' ),
		'social_additional_profiles' => implode( "\n", acme_seo_profile_urls( $settings ) ),
		'disable_author_archives'    => 'on',
		'disable_date_archives'      => 'on',
		'noindex_empty_taxonomies'   => 'on',
		'noindex_search'             => 'on',
		// Вторая и следующие страницы списков индексируются: у них свой title и description (titles.php).
		'noindex_archive_subpages'   => 'off',
		'twitter_card_type'          => 'summary_large_image',
		'tax_category_title'         => '%term% %sep% %sitename%',
		'tax_category_description'   => '%term_description%',
		'tax_post_tag_custom_robots' => 'on',
		'tax_post_tag_robots'        => array( 'noindex', 'follow' ),
	);
	$titles   = array_merge( $titles, acme_seo_post_type_titles(), acme_seo_site_images( $covers ) );
	acme_seo_merge_option( 'rank-math-options-titles', $titles );
}

/**
 * Шаблоны по типам записей — запасной слой: у записи со своими SEO-полями они не используются.
 * Разметка: статья — BlogPosting; страница и услуга — без Article (узлы WebPage и Service — schema*.php).
 */
function acme_seo_post_type_titles() {
	$titles = array();
	foreach ( acme_seo_post_types() as $post_type ) {
		$titles[ "pt_{$post_type}_title" ]                = '%title% %sep% %sitename%';
		$titles[ "pt_{$post_type}_description" ]          = '%excerpt%';
		$titles[ "pt_{$post_type}_default_rich_snippet" ] = 'off';
		$titles[ "pt_{$post_type}_add_meta_box" ]         = 'on';
	}
	$titles['pt_post_default_rich_snippet'] = 'article';
	$titles['pt_post_default_article_type'] = 'BlogPosting';
	return $titles;
}

/** Логотип организации (логотип темы или значок сайта) и обложка основного языка для соцсетей. */
function acme_seo_site_images( array $covers ) {
	$images   = array();
	$logo_id  = (int) get_theme_mod( 'custom_logo' );
	$logo_id  = $logo_id ? $logo_id : (int) get_option( 'site_icon' );
	$cover_id = (int) ( $covers[ acme_main_lang() ] ?? 0 );
	if ( $logo_id && wp_get_attachment_url( $logo_id ) ) {
		$images['knowledgegraph_logo']    = wp_get_attachment_url( $logo_id );
		$images['knowledgegraph_logo_id'] = $logo_id;
	}
	if ( $cover_id && wp_get_attachment_url( $cover_id ) ) {
		$images['open_graph_image']    = wp_get_attachment_url( $cover_id );
		$images['open_graph_image_id'] = $cover_id;
	}
	return $images;
}

/** Карта сайта: записи, страницы, услуги и рубрики с картинками; без авторов, вложений и меток. */
function acme_seo_sitemap_v1() {
	$sitemap = array(
		'authors_sitemap'        => 'off',
		'include_images'         => 'on',
		'include_featured_image' => 'on',
		'pt_attachment_sitemap'  => 'off',
		'tax_category_sitemap'   => 'on',
		'tax_post_tag_sitemap'   => 'off',
	);
	foreach ( acme_seo_post_types() as $post_type ) {
		$sitemap[ "pt_{$post_type}_sitemap" ] = 'on';
	}
	acme_seo_merge_option( 'rank-math-options-sitemap', $sitemap );
}
