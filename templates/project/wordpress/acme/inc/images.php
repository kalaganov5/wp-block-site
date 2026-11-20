<?php
/**
 * Картинки медиатеки: единый вывод (размеры, srcset, отложенная загрузка) и WebP при загрузке файлов.
 *
 * Зачем одна функция: у каждой картинки должны быть width/height (иначе страница «прыгает» при загрузке, CLS),
 * srcset и sizes (телефон не качает картинку для монитора), а у главной картинки первого экрана — приоритет
 * загрузки (LCP). Если писать <img> руками в каждом блоке, что-нибудь из этого забывается.
 *
 * Своих размеров картинок тема не регистрирует: каждый add_image_size() — ещё один файл на каждую загруженную
 * картинку. Блоки берут стандартные размеры WordPress (medium_large — 768 px, large — 1024 px), а нужные
 * пропорции задаёт CSS (aspect-ratio + object-fit). Понадобился жёсткий кадр — добавьте размер здесь и
 * используйте его в блоке.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Тег <img> для картинки из медиатеки.
 *
 * $args:
 *   size       — размер WordPress, из которого берётся src (по умолчанию large); srcset собирается из всех размеров;
 *   sizes      — атрибут sizes: какую ширину занимает картинка в вёрстке, например
 *                '(max-width: 759.98px) 100vw, 360px'. Без него браузер считает, что картинка во весь экран;
 *   priority   — true для главной картинки первого экрана (LCP): без lazy, с fetchpriority="high".
 *                На странице такая картинка одна;
 *   decorative — true, если картинка ничего не сообщает (рядом тот же текст): alt будет пустым.
 *                Иначе alt берётся из медиатеки («Альтернативный текст»), а если он не заполнен — из alt;
 *   alt        — запасной текст на случай пустого поля в медиатеке (например, заголовок записи);
 *   class      — класс тега.
 *
 * Возвращает готовую разметку (атрибуты экранирует WordPress) или '' — если картинки нет.
 */
function acme_image( $attachment_id, array $args = array() ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) {
		return '';
	}
	$size = $args['size'] ?? 'large';
	return wp_get_attachment_image( $attachment_id, $size, false, acme_image_attributes( $attachment_id, $args ) );
}

/** Атрибуты <img> по правилам темы; width, height, src и srcset добавляет wp_get_attachment_image(). */
function acme_image_attributes( $attachment_id, array $args ) {
	$is_priority = ! empty( $args['priority'] );
	$attributes  = array(
		'decoding' => 'async',
		// Явное значение: иначе WordPress сам угадывает, какая картинка «первая», и в блоках ошибается.
		'loading'  => $is_priority ? false : 'lazy',
	);
	if ( $is_priority ) {
		$attributes['fetchpriority'] = 'high';
	}
	if ( ! empty( $args['sizes'] ) ) {
		$attributes['sizes'] = $args['sizes'];
	}
	if ( ! empty( $args['class'] ) ) {
		$attributes['class'] = $args['class'];
	}
	if ( ! empty( $args['decorative'] ) ) {
		$attributes['alt'] = '';
		return $attributes;
	}
	$library_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	if ( '' === $library_alt && ! empty( $args['alt'] ) ) {
		$attributes['alt'] = $args['alt'];
	}
	return $attributes;
}

add_filter( 'image_editor_output_format', 'acme_webp_output_format' );

/**
 * Загруженные JPEG и PNG WordPress пересохраняет в WebP: все уменьшенные копии (их и получает посетитель)
 * весят на 25–35 % меньше. Исходный файл остаётся в папке загрузок как был — его можно скачать или
 * пересоздать копии. Если на сервере нет поддержки WebP (GD или Imagick), WordPress сам оставит прежний формат.
 */
function acme_webp_output_format( $formats ) {
	$formats['image/jpeg'] = 'image/webp';
	$formats['image/png']  = 'image/webp';
	return $formats;
}
