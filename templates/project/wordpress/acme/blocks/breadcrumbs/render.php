<?php
/**
 * Хлебные крошки: acme_breadcrumb_items() (inc/markup.php). На главной не выводятся.
 * Те же данные плагин выводит разметкой BreadcrumbList.
 */

defined( 'ABSPATH' ) || exit;

// Каждая крошка — [ подпись, адрес ]; у последней (текущая страница) адреса нет.
$acme_crumbs = acme_breadcrumb_items();
if ( count( $acme_crumbs ) < 2 ) {
	return;
}
?>
<nav class="acme-wrap acme-crumbs" aria-label="<?php echo esc_attr( acme_t( 'crumbsAria' ) ); ?>"><ol>
	<?php foreach ( $acme_crumbs as $acme_crumb ) : ?>
		<li>
			<?php if ( empty( $acme_crumb[1] ) ) : ?>
				<span aria-current="page"><?php echo esc_html( $acme_crumb[0] ); ?></span>
			<?php else : ?>
				<a href="<?php echo esc_url( $acme_crumb[1] ); ?>"><?php echo esc_html( $acme_crumb[0] ); ?></a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ol></nav>
