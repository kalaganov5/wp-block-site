<?php
/**
 * Google Tag Manager с режимом согласия (Consent Mode v2).
 *
 * До выбора в окне cookie всё, кроме необходимого, запрещено (denied). Выбор посетителя (window.acmeConsent,
 * событие acme-consent из темы) передаётся в GTM: gtag('consent', 'update', …) и событие acme_consent_update
 * в dataLayer. Теги, которые сами не понимают Consent Mode (например, Яндекс Метрика), в GTM запускайте
 * с условием «Дополнительные проверки согласия: analytics_storage» или по событию acme_consent_update.
 *
 * Работает только на боевом сайте (WP_ENVIRONMENT_TYPE = production), открытом для поисковиков, если
 * в настройках не включено «и на тестовом». Пока боевой сайт закрыт (NOINDEX до переключения домена),
 * счётчиков нет. Для вошедших редакторов и администраторов не выводится, чтобы не засорять статистику.
 * Сам контейнер грузится с задержкой (первое действие посетителя или 3 с после загрузки), чтобы
 * не тормозить страницу.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'acme_gtm_print_head', 1 );
add_action( 'wp_body_open', 'acme_gtm_print_noscript' );

/** ID контейнера GTM, если счётчики на этой странице нужны, иначе ''. */
function acme_gtm_id() {
	$id = strtoupper( trim( (string) acme_setting( 'gtm_id' ) ) );
	if ( ! preg_match( '/^GTM-[A-Z0-9]{4,12}$/', $id ) ) {
		return '';
	}
	$is_live_site = 'production' === wp_get_environment_type() && get_option( 'blog_public' );
	if ( ! $is_live_site && ! (int) acme_setting( 'gtm_test' ) ) {
		return '';
	}
	if ( is_admin() || is_preview() || is_customize_preview() || current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return $id;
}

function acme_gtm_print_head() {
	$id = acme_gtm_id();
	if ( ! $id ) {
		return;
	}
	acme_gtm_print_consent_script();
	acme_gtm_print_loader_script( $id );
}

/** Согласие по умолчанию и его обновление: должно стоять до загрузки контейнера. */
function acme_gtm_print_consent_script() {
	?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
(function () {
	var state = function (isAllowed) { return isAllowed ? 'granted' : 'denied'; };
	var toConsent = function (choice) {
		var marketing = state(choice.marketing);
		return {
			analytics_storage: state(choice.analytics),
			ad_storage: marketing, ad_user_data: marketing, ad_personalization: marketing
		};
	};
	gtag('consent', 'default', {
		analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
		functionality_storage: 'granted', security_storage: 'granted', wait_for_update: 500
	});
	try {
		var saved = JSON.parse(localStorage.getItem('acme-consent') || 'null');
		var isFresh = saved && saved.v === 1 && Date.now() - saved.ts < 365 * 864e5;
		if (isFresh) { gtag('consent', 'update', toConsent(saved)); }
	} catch (error) {}
	window.addEventListener('acme-consent', function (event) {
		var choice = event.detail || {};
		gtag('consent', 'update', toConsent(choice));
		dataLayer.push({
			event: 'acme_consent_update',
			acme_analytics: !!choice.analytics,
			acme_marketing: !!choice.marketing
		});
	});
})();
</script>
	<?php
}

function acme_gtm_print_loader_script( $id ) {
	?>
<script>
(function (w, d, id) {
	// GTM грузится после первого действия посетителя или через 3 с после загрузки страницы:
	// счётчики не тормозят первую отрисовку (Lighthouse: Total Blocking Time).
	var isLoaded = false;
	var events = ['pointerdown', 'keydown', 'scroll', 'touchstart', 'mousemove'];
	function load() {
		if (isLoaded) { return; }
		isLoaded = true;
		events.forEach(function (name) { w.removeEventListener(name, load, { passive: true }); });
		w.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
		var script = d.createElement('script');
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtm.js?id=' + id;
		d.head.appendChild(script);
	}
	function loadLater() { setTimeout(load, 3000); }
	events.forEach(function (name) { w.addEventListener(name, load, { passive: true }); });
	if (d.readyState === 'complete') { loadLater(); } else { w.addEventListener('load', loadLater); }
})(window, document, '<?php echo esc_js( $id ); ?>');
</script>
	<?php
}

/** Запасной вариант GTM для посетителей без JavaScript. */
function acme_gtm_print_noscript() {
	$id = acme_gtm_id();
	if ( ! $id ) {
		return;
	}
	printf(
		'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0"'
			. ' style="display:none;visibility:hidden"></iframe></noscript>',
		esc_attr( $id )
	);
}
