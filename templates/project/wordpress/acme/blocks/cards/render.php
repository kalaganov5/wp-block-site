<?php
/**
 * Карточки-ссылки.
 * items: [{ title, text, post (ID записи — ссылка и заголовок по умолчанию), url (если нет записи), urgent }].
 * Ссылка хранится как ID записи, а не адрес: смена слага не ломает карточку.
 * moreLabel — текст ссылки «Подробнее»; пусто — подпись readMore на языке страницы.
 */

defined( 'ABSPATH' ) || exit;

$acme_cards = array();
foreach ( (array) ( $attributes['items'] ?? array() ) as $acme_item ) {
	$acme_post  = ! empty( $acme_item['post'] ) ? get_post( (int) $acme_item['post'] ) : null;
	$acme_title = trim( (string) ( $acme_item['title'] ?? '' ) );
	if ( '' === $acme_title && $acme_post ) {
		$acme_title = get_the_title( $acme_post );
	}
	// Карточка без заголовка — незаполненная («+ Добавить» и ничего не вписано): на сайт не попадает.
	if ( '' === $acme_title ) {
		continue;
	}
	$acme_is_published = $acme_post && 'publish' === $acme_post->post_status;
	$acme_cards[]      = array(
		'title' => $acme_title,
		'text'  => (string) ( $acme_item['text'] ?? '' ),
		// Неопубликованная запись ссылкой не становится: используется запасной адрес или карточка без ссылки.
		'href'  => $acme_is_published ? get_permalink( $acme_post ) : (string) ( $acme_item['url'] ?? '' ),
		'class' => empty( $acme_item['urgent'] ) ? 'acme-card' : 'acme-card is-urgent',
	);
}
// Пустой блок ничего не выводит; в редакторе на его месте подпись-подсказка (assets/js/editor.js).
if ( ! $acme_cards ) {
	return;
}
$acme_more_label = (string) ( $attributes['moreLabel'] ?? '' );
if ( '' === $acme_more_label ) {
	$acme_more_label = acme_t( 'readMore' );
}
?>
<div class="acme-cards">
	<?php foreach ( $acme_cards as $acme_card ) : ?>
		<?php if ( $acme_card['href'] ) : ?>
			<a
				class="<?php echo esc_attr( $acme_card['class'] ); ?>"
				href="<?php echo esc_url( $acme_card['href'] ); ?>"
			>
		<?php else : ?>
			<div class="<?php echo esc_attr( $acme_card['class'] ); ?>">
		<?php endif; ?>
			<h3><?php echo esc_html( $acme_card['title'] ); ?></h3>
			<?php if ( '' !== $acme_card['text'] ) : ?>
				<p><?php echo esc_html( $acme_card['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( $acme_card['href'] ) : ?>
				<span class="acme-card__more"><?php echo esc_html( $acme_more_label ); ?></span>
			<?php endif; ?>
		<?php echo $acme_card['href'] ? '</a>' : '</div>'; ?>
	<?php endforeach; ?>
</div>
