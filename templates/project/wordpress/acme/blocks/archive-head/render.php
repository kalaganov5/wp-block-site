<?php
/**
 * Заголовок списка: страница блога (её заголовок и отрывок), рубрика (название и описание),
 * архив услуг (подписи из строк интерфейса), 404 (mode = 404 или страница не найдена).
 */

defined( 'ABSPATH' ) || exit;

$acme_is_404  = '404' === ( $attributes['mode'] ?? '' ) || is_404();
$acme_heading = '';
$acme_lead    = '';
if ( $acme_is_404 ) {
	$acme_heading = acme_t( 'notFoundH1' );
	$acme_lead    = acme_t( 'notFoundText' );
} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
	$acme_blog_page_id = (int) get_option( 'page_for_posts' );
	// Страница блога на языке посетителя; перевода нет — остаётся страница основного языка.
	$acme_translated_id = function_exists( 'pll_get_post' ) ? (int) pll_get_post( $acme_blog_page_id ) : 0;
	if ( $acme_translated_id ) {
		$acme_blog_page_id = $acme_translated_id;
	}
	$acme_heading = get_the_title( $acme_blog_page_id );
	$acme_lead    = get_the_excerpt( $acme_blog_page_id );
} elseif ( is_category() || is_tax() ) {
	$acme_heading = single_term_title( '', false );
	$acme_lead    = wp_strip_all_tags( term_description() );
} elseif ( is_post_type_archive( 'acme_service' ) ) {
	$acme_heading = acme_t( 'titleServices' );
	$acme_lead    = acme_t( 'servicesLead' );
} elseif ( is_archive() ) {
	$acme_heading = wp_strip_all_tags( get_the_archive_title() );
}
if ( '' === $acme_heading ) {
	return;
}
$acme_home_url = acme_home();
?>
<div class="acme-stack-16">
	<h1><?php echo esc_html( $acme_heading ); ?></h1>
	<?php if ( $acme_lead ) : ?>
		<p class="acme-lead"><?php echo esc_html( $acme_lead ); ?></p>
	<?php endif; ?>
	<?php if ( $acme_is_404 ) : ?>
		<div class="wp-block-buttons">
			<div class="wp-block-button is-style-primary">
				<a
					class="wp-block-button__link"
					href="<?php echo esc_url( $acme_home_url ); ?>"
				><?php acme_e( 'toHome' ); ?></a>
			</div>
		</div>
	<?php endif; ?>
</div>
