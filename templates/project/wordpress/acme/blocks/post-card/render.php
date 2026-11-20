<?php
/**
 * Карточка записи внутри «Цикла запросов» (core/post-template): картинка записи (если задана), рубрика,
 * заголовок, отрывок, ссылка. Запись — из контекста блока (usesContext: postId).
 */

defined( 'ABSPATH' ) || exit;

$acme_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $acme_post_id ) {
	return;
}
// Рубрики есть только у записей блога; у услуг и страниц метки над заголовком нет.
$acme_categories = 'post' === get_post_type( $acme_post_id ) ? get_the_category( $acme_post_id ) : array();
// Картинка декоративная: рядом заголовок записи, повторять его в alt — лишний шум для экранного диктора.
// Грузится отложенно: карточки списка обычно ниже первого экрана. sizes — ширина колонки сетки .acme-cards.
$acme_image = acme_image(
	get_post_thumbnail_id( $acme_post_id ),
	array(
		'size'       => 'medium_large',
		'sizes'      => '(max-width: 599.98px) 100vw, 400px',
		'decorative' => true,
		'class'      => 'acme-card__image',
	)
);
?>
<a class="acme-card" href="<?php echo esc_url( get_permalink( $acme_post_id ) ); ?>">
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput -- разметка wp_get_attachment_image
	echo $acme_image;
	?>
	<?php if ( $acme_categories ) : ?>
		<span class="acme-card__meta"><?php echo esc_html( $acme_categories[0]->name ); ?></span>
	<?php endif; ?>
	<h3><?php echo esc_html( get_the_title( $acme_post_id ) ); ?></h3>
	<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $acme_post_id ), 24 ) ); ?></p>
	<span class="acme-card__more"><?php acme_e( 'readMore' ); ?></span>
</a>
