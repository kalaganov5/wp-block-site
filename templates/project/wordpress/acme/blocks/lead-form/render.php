<?php
/**
 * Форма заявки: Contact Form 7 на языке страницы.
 * formId = 0 — форма языка из опции acme_cf7_forms, её создаёт импорт.
 * Сохранение заявки, копия в Telegram и антиспам — плагин acme-core (inc/leads.php).
 */

defined( 'ABSPATH' ) || exit;

$acme_form_id = (int) ( $attributes['formId'] ?? 0 );
if ( ! $acme_form_id ) {
	$acme_forms   = (array) get_option( 'acme_cf7_forms', array() );
	$acme_default = $acme_forms[ acme_default_lang() ] ?? 0;
	$acme_form_id = (int) ( $acme_forms[ acme_cur_lang() ] ?? $acme_default );
}
if ( ! $acme_form_id || ! shortcode_exists( 'contact-form-7' ) ) {
	return;
}
$acme_title    = (string) ( $attributes['title'] ?? '' );
$acme_subtitle = (string) ( $attributes['sub'] ?? '' );
if ( '' === $acme_title ) {
	$acme_title = acme_t( 'formTitle' );
}
if ( '' === $acme_subtitle ) {
	$acme_subtitle = acme_t( 'formSub' );
}
?>
<section id="form" class="acme-section is-sunken acme-form">
	<div class="acme-wrap">
		<h2><?php echo esc_html( $acme_title ); ?></h2>
		<?php if ( $acme_subtitle ) : ?>
			<p class="acme-lead"><?php echo esc_html( $acme_subtitle ); ?></p>
		<?php endif; ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput -- разметка CF7
		echo do_shortcode( '[contact-form-7 id="' . $acme_form_id . '"]' );
		?>
	</div>
</section>
