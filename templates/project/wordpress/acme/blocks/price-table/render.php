<?php
/**
 * Таблица цен: заголовок, описание и карточки тарифов.
 * Атрибуты: title, lead, items [{ title, text, url }], note и showNote (примечание под карточками).
 * Тексты интерфейса — acme_t( 'ключ' ), данные плагина — через acme_has_core().
 */

defined( 'ABSPATH' ) || exit;

$acme_title = (string) ( $attributes['title'] ?? '' );
$acme_lead  = (string) ( $attributes['lead'] ?? '' );
$acme_note  = ! empty( $attributes['showNote'] ) ? (string) ( $attributes['note'] ?? '' ) : '';
// Карточка без названия — незаполненная («+ Добавить» и ничего не вписано): на сайт не попадает.
$acme_items = array_filter(
	(array) ( $attributes['items'] ?? array() ),
	function ( $item ) {
		return '' !== trim( (string) ( $item['title'] ?? '' ) );
	}
);
// Пустой блок ничего не выводит; в редакторе на его месте подпись-подсказка (assets/js/editor.js).
if ( '' === $acme_title && ! $acme_items ) {
	return;
}
?>
<section class="acme-section acme-price-table">
	<div class="acme-wrap">
		<?php if ( '' !== $acme_title ) : ?>
			<h2><?php echo esc_html( $acme_title ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $acme_lead ) : ?>
			<p class="acme-lead"><?php echo esc_html( $acme_lead ); ?></p>
		<?php endif; ?>
		<?php if ( $acme_items ) : ?>
			<div class="acme-price-table__list">
				<?php foreach ( $acme_items as $acme_item ) : ?>
					<div class="acme-price-table__item">
						<h3><?php echo esc_html( $acme_item['title'] ); ?></h3>
						<?php if ( ! empty( $acme_item['text'] ) ) : ?>
							<p><?php echo esc_html( $acme_item['text'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $acme_note ) : ?>
			<p><?php echo esc_html( $acme_note ); ?></p>
		<?php endif; ?>
	</div>
</section>
