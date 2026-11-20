<?php
/**
 * robots.txt: открытый сайт явно разрешён ботам ИИ-ассистентов; Яндексу названы рекламные метки в адресах,
 * чтобы страницы с utm_* не считались отдельными. Закрытый сайт (blog_public = 0) не трогаем.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'robots_txt', 'acme_seo_robots_txt', 99, 2 );

/** Боты ИИ-ассистентов: обучение, поиск и запросы по просьбе пользователя. */
function acme_seo_ai_bots() {
	return array(
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'ClaudeBot',
		'Claude-SearchBot',
		'Claude-User',
		'PerplexityBot',
		'Perplexity-User',
		'Google-Extended',
		'Applebot-Extended',
	);
}

function acme_seo_robots_txt( $output, $is_public ) {
	// Закрытый сайт не открываем; повторно (правила уже дописаны вручную или другим плагином) не дописываем.
	if ( ! $is_public || false !== strpos( $output, 'GPTBot' ) ) {
		return $output;
	}
	$output = rtrim( $output ) . "\n\n# Ассистенты с ИИ могут читать и цитировать сайт\n";
	foreach ( acme_seo_ai_bots() as $bot ) {
		$output .= "User-agent: $bot\n";
	}
	$ad_params = 'utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid&ysclid';
	$output   .= "Allow: /\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\n";
	$output   .= "Clean-param: $ad_params\n\n";
	$output   .= '# Описание сайта для ИИ-ассистентов: ' . home_url( '/llms.txt' ) . "\n";
	return $output;
}
