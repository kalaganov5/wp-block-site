<?php
/**
 * Лёгкие встраивания вместо тяжёлых iframe.
 *
 * - YouTube: превью и кнопка «Смотреть видео», плеер (youtube-nocookie.com) грузится только по клику
 *   (assets/js/site.js, [data-acme-yt]). Плеер YouTube — около 600 КБ скриптов и cookie ещё до просмотра:
 *   на страницах с видео PageSpeed падал до 52.
 * - Встраивание чужой записи WordPress (тип wp-embed) — обычная ссылка: iframe чужого сайта
 *   и скрипт wp-embed не нужны.
 */

defined( 'ABSPATH' ) || exit;

/** ID ролика YouTube из адреса (watch?v=, youtu.be/, embed/, shorts/) или ''. */
function acme_youtube_id( $url ) {
	$pattern = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)'
		. '([A-Za-z0-9_-]{11})~';
	return preg_match( $pattern, (string) $url, $matches ) ? $matches[1] : '';
}

/**
 * Приоритет 5 — раньше wp_maybe_enqueue_oembed_host_js (10): без iframe чужого сайта WordPress
 * не подключает wp-embed. В редакторе встраивания остаются как есть.
 */
add_filter( 'embed_oembed_html', 'acme_light_embed', 5, 2 );

function acme_light_embed( $html, $url ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $html;
	}
	$video_id = acme_youtube_id( $url );
	if ( $video_id ) {
		return acme_youtube_preview( $video_id, $html );
	}
	if ( false !== strpos( $html, 'wp-embedded-content' ) ) {
		return acme_embed_link( $html, $url );
	}
	return $html;
}

/** Кнопка с превью ролика вместо плеера. Название ролика берём из title у iframe, который вернул YouTube. */
function acme_youtube_preview( $video_id, $iframe_html ) {
	$title = '';
	if ( preg_match( '/<iframe[^>]+title="([^"]*)"/', $iframe_html, $matches ) ) {
		$title = html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
	}
	$label = acme_t( 'playVideo' ) . ( $title ? ': ' . $title : '' );
	return sprintf(
		'<button type="button" class="acme-yt" data-acme-yt="%1$s" data-title="%2$s" aria-label="%3$s">'
			. '<img src="%4$s" alt="" width="480" height="360" loading="lazy" decoding="async">'
			. '<span class="acme-yt__play" aria-hidden="true"></span></button>',
		esc_attr( $video_id ),
		esc_attr( $title ),
		esc_attr( $label ),
		esc_url( 'https://i.ytimg.com/vi/' . $video_id . '/hqdefault.jpg' )
	);
}

/** Ссылка на чужую запись WordPress вместо её iframe. Текст ссылки — заголовок записи, без него — адрес. */
function acme_embed_link( $embed_html, $url ) {
	$title = $url;
	if ( preg_match( '~<blockquote[^>]*>.*?<a[^>]*>(.*?)</a>~s', $embed_html, $matches ) ) {
		$title = wp_strip_all_tags( $matches[1] );
	}
	return sprintf(
		'<p class="acme-embed-link"><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>',
		esc_url( $url ),
		esc_html( $title ? $title : $url )
	);
}
