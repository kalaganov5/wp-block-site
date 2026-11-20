<?php
/**
 * simple.
 * Атрибуты: title. Тексты интерфейса — acme_t( 'ключ' ), данные плагина — через acme_has_core().
 */

defined( 'ABSPATH' ) || exit;

$acme_title = (string) ( $attributes['title'] ?? '' );
if ( '' === $acme_title ) {
	return; // пустой блок ничего не выводит; в редакторе у выбранного блока — подсказка
}
?>
<section class="acme-section acme-simple">
	<div class="acme-wrap">
		<?php if ( '' !== $acme_title ) : ?>
			<h2><?php echo esc_html( $acme_title ); ?></h2>
		<?php endif; ?>
	</div>
</section>
