<?php
/**
 * Шапка: логотип, меню (область acme_header, своё меню на каждом языке), язык, тема, мобильное меню.
 * Если меню в области не назначено — пункты по умолчанию из ролей служебных страниц.
 */

defined( 'ABSPATH' ) || exit;

// Меню выводится дважды (строка в шапке и мобильное) с разным классом списка, поэтому — функция.
// Замыкание, а не именованная функция: render.php подключается при каждом выводе блока.
$acme_menu = function ( $css_class ) {
	if ( has_nav_menu( 'acme_header' ) ) {
		return wp_nav_menu(
			array(
				'theme_location' => 'acme_header',
				'container'      => false,
				'menu_class'     => $css_class,
				'depth'          => 1,
				'echo'           => false,
				'fallback_cb'    => false,
			)
		);
	}
	$default_items = array(
		'about'    => 'navAbout',
		'blog'     => 'navBlog',
		'contacts' => 'navContacts',
	);
	$markup        = '<ul class="' . esc_attr( $css_class ) . '">';
	foreach ( $default_items as $role => $label_key ) {
		$markup .= sprintf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url( acme_url( $role ) ),
			esc_html( acme_t( $label_key ) )
		);
	}
	return $markup . '</ul>';
};

$acme_languages = array();
if ( function_exists( 'pll_the_languages' ) ) {
	$acme_languages = (array) pll_the_languages(
		array(
			'raw'           => 1,
			'hide_if_empty' => 0,
		)
	);
}
$acme_home_url  = acme_home();
$acme_site_name = get_bloginfo( 'name' );
$acme_nav_label = acme_t( 'navAria' );
?>
<a class="screen-reader-text" href="#main"><?php acme_e( 'skipLink' ); ?></a>
<header class="acme-header">
	<div class="acme-wrap acme-header__in">
		<a
			class="acme-logo"
			href="<?php echo esc_url( $acme_home_url ); ?>"
			rel="home"
		><?php echo esc_html( $acme_site_name ); ?></a>
		<nav class="acme-nav" aria-label="<?php echo esc_attr( $acme_nav_label ); ?>">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput -- разметка wp_nav_menu или экранированная выше
			echo $acme_menu( 'acme-nav__list' );
			?>
		</nav>
		<div class="acme-header__tools">
			<?php foreach ( $acme_languages as $acme_language ) : ?>
				<?php if ( empty( $acme_language['current_lang'] ) ) : ?>
					<a
						class="acme-icon-btn"
						href="<?php echo esc_url( $acme_language['url'] ); ?>"
						hreflang="<?php echo esc_attr( $acme_language['slug'] ); ?>"
						lang="<?php echo esc_attr( $acme_language['slug'] ); ?>"
					><?php echo esc_html( strtoupper( $acme_language['slug'] ) ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
			<button
				type="button"
				class="acme-icon-btn"
				data-acme-theme="light"
				aria-pressed="false"
				aria-label="<?php echo esc_attr( acme_t( 'themeLight' ) ); ?>"
			><?php echo acme_icon( 'sun' ); ?></button>
			<button
				type="button"
				class="acme-icon-btn"
				data-acme-theme="dark"
				aria-pressed="false"
				aria-label="<?php echo esc_attr( acme_t( 'themeDark' ) ); ?>"
			><?php echo acme_icon( 'moon' ); ?></button>
			<button
				type="button"
				class="acme-icon-btn acme-burger"
				data-acme-menu-toggle
				aria-controls="acme-mnav"
				aria-expanded="false"
				aria-label="<?php echo esc_attr( acme_t( 'menu' ) ); ?>"
			><?php echo acme_icon( 'menu' ); ?></button>
		</div>
	</div>
	<nav id="acme-mnav" class="acme-mnav" hidden aria-label="<?php echo esc_attr( $acme_nav_label ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput -- разметка wp_nav_menu или экранированная выше
		echo $acme_menu( 'acme-mnav__list' );
		?>
	</nav>
</header>
