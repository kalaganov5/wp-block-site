/*
 * Окно cookie в подвале: выбор посетителя хранится год, после выбора — событие acme-consent.
 * Его слушает GTM (плагин acme-core, inc/analytics.php): до выбора аналитика и реклама запрещены.
 *
 * Как подключается: "viewScript" в block.json подвала — только на сайте и только там, где есть подвал;
 * в редакторе не загружается (окно cookie поверх холста мешало бы писать).
 * Ключ localStorage: acme-consent — { v, necessary, analytics, marketing, ts }.
 */
(function () {
	'use strict';

	const CONSENT_KEY = 'acme-consent';
	const CONSENT_VERSION = 1;
	// Имя поля версии — «v»: в таком виде выбор уже лежит в localStorage посетителей и читается в GTM (плагин).
	const CONSENT_VERSION_FIELD = 'v';
	const CONSENT_TTL = 365 * 24 * 3600 * 1000;

	const query = (selector, root) => (root || document).querySelector(selector);
	const queryAll = (selector, root) => Array.from((root || document).querySelectorAll(selector));

	function readStorage() {
		try {
			return localStorage.getItem(CONSENT_KEY);
		} catch {
			// Хранилище недоступно (приватный режим, запрет в настройках) — как будто выбора нет.
			return null;
		}
	}

	function writeStorage(value) {
		try {
			localStorage.setItem(CONSENT_KEY, value);
		} catch {
			// Хранилище недоступно — выбор не запоминается, сайт работает дальше.
		}
	}

	function loadConsent() {
		try {
			const consent = JSON.parse(readStorage() || 'null');
			const isCurrent = consent && consent[CONSENT_VERSION_FIELD] === CONSENT_VERSION;
			if (isCurrent && Date.now() - consent.ts < CONSENT_TTL) {
				window.acmeConsent = consent;
				return consent;
			}
		} catch {
			// В хранилище не JSON (правили руками, старый формат) — считаем, что выбора не было.
		}
		return null;
	}

	function showCookie(banner, isPrefs) {
		banner.hidden = false;
		query('[data-acme-ck-main]', banner).hidden = !!isPrefs;
		query('[data-acme-ck-prefs]', banner).hidden = !isPrefs;
		const consent = loadConsent() || {};
		queryAll('input[name="analytics"]', banner).forEach((input) => {
			input.checked = !!consent.analytics;
		});
		queryAll('input[name="marketing"]', banner).forEach((input) => {
			input.checked = !!consent.marketing;
		});
	}

	function saveConsent(banner, hasAnalytics, hasMarketing) {
		const consent = {
			[CONSENT_VERSION_FIELD]: CONSENT_VERSION,
			necessary: true,
			analytics: !!hasAnalytics,
			marketing: !!hasMarketing,
			ts: Date.now(),
		};
		writeStorage(JSON.stringify(consent));
		window.acmeConsent = consent;
		window.dispatchEvent(new CustomEvent('acme-consent', { detail: consent }));
		banner.hidden = true;
	}

	function onCookieClick(banner, event) {
		const button = event.target.closest('[data-acme-ck]');
		if (!button) {
			return;
		}
		const action = button.dataset.acmeCk;
		if (action === 'all') {
			saveConsent(banner, true, true);
		} else if (action === 'none') {
			saveConsent(banner, false, false);
		} else if (action === 'prefs') {
			showCookie(banner, true);
		} else if (action === 'save') {
			saveConsent(
				banner,
				query('input[name="analytics"]', banner).checked,
				query('input[name="marketing"]', banner).checked,
			);
		}
	}

	function initCookie() {
		const banner = query('[data-acme-cookie]');
		if (!banner) {
			return;
		}
		if (!loadConsent()) {
			showCookie(banner, false);
		}
		document.addEventListener('click', (event) => onCookieClick(banner, event));
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCookie);
	} else {
		initCookie();
	}
})();
