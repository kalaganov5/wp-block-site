<?php
/**
 * Подвал: меню (области acme_footer, acme_footer_docs), контакты из настроек, копирайт, окно cookie.
 */

defined( 'ABSPATH' ) || exit;

// Меню области подвала; область без назначенного меню даёт пустую строку, а не список всех страниц.
// Замыкание, а не именованная функция: render.php подключается при каждом выводе блока.
$acme_menu = function ( $location ) {
	if ( ! has_nav_menu( $location ) ) {
		return '';
	}
	return wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'depth'          => 1,
			'echo'           => false,
			'fallback_cb'    => false,
		)
	);
};

$acme_email        = acme_set( 'email' );
$acme_telegram_url = acme_set( 'telegram_url' );
$acme_home_url     = acme_home();
$acme_site_name    = get_bloginfo( 'name' );
$acme_copyright    = gmdate( 'Y' ) . ' ' . $acme_site_name;
?>
<footer class="acme-footer">
	<div class="acme-wrap acme-footer__in">
		<div>
			<a
				class="acme-logo"
				href="<?php echo esc_url( $acme_home_url ); ?>"
			><?php echo esc_html( $acme_site_name ); ?></a>
			<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
		</div>
		<nav aria-label="<?php echo esc_attr( acme_t( 'footerNav' ) ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput -- разметка wp_nav_menu
			echo $acme_menu( 'acme_footer' );
			?>
		</nav>
		<ul>
			<?php if ( $acme_email ) : ?>
				<li>
					<a
						href="mailto:<?php echo esc_attr( $acme_email ); ?>"
					><?php echo esc_html( $acme_email ); ?></a>
				</li>
			<?php endif; ?>
			<?php if ( $acme_telegram_url ) : ?>
				<li><a href="<?php echo esc_url( $acme_telegram_url ); ?>" rel="noopener">Telegram</a></li>
			<?php endif; ?>
		</ul>
		<nav aria-label="<?php echo esc_attr( acme_t( 'docsNav' ) ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput -- разметка wp_nav_menu
			echo $acme_menu( 'acme_footer_docs' );
			?>
			<p><a href="#" data-acme-ck="prefs"><?php acme_e( 'cookieSettings' ); ?></a></p>
		</nav>
		<p class="acme-footer__copy">© <?php echo esc_html( $acme_copyright ); ?></p>
	</div>
</footer>
<div
	class="acme-cookie"
	data-acme-cookie
	hidden
	role="dialog"
	aria-label="<?php echo esc_attr( acme_t( 'cookieTitle' ) ); ?>"
>
	<div data-acme-ck-main>
		<p><?php acme_e( 'cookieText' ); ?></p>
		<div class="acme-cookie__row wp-block-buttons">
			<div class="wp-block-button is-style-primary">
				<button
					type="button"
					class="wp-block-button__link"
					data-acme-ck="all"
				><?php acme_e( 'cookieAll' ); ?></button>
			</div>
			<div class="wp-block-button is-style-secondary">
				<button
					type="button"
					class="wp-block-button__link"
					data-acme-ck="none"
				><?php acme_e( 'cookieNone' ); ?></button>
			</div>
			<div class="wp-block-button is-style-ghost">
				<button
					type="button"
					class="wp-block-button__link"
					data-acme-ck="prefs"
				><?php acme_e( 'cookiePrefs' ); ?></button>
			</div>
		</div>
	</div>
	<div data-acme-ck-prefs hidden>
		<label><input type="checkbox" checked disabled> <?php acme_e( 'cookieNecessary' ); ?></label>
		<label><input type="checkbox" name="analytics"> <?php acme_e( 'cookieAnalytics' ); ?></label>
		<label><input type="checkbox" name="marketing"> <?php acme_e( 'cookieMarketing' ); ?></label>
		<div class="acme-cookie__row wp-block-buttons">
			<div class="wp-block-button is-style-primary">
				<button
					type="button"
					class="wp-block-button__link"
					data-acme-ck="save"
				><?php acme_e( 'cookieSave' ); ?></button>
			</div>
		</div>
	</div>
</div>
