/*
 * Acme Site — поведение сайта (без зависимостей). Разметку отдаёт сервер, скрипт только добавляет интерактив.
 * Ключ localStorage: acme-theme (light | dark | нет — как в системе). Окно cookie — blocks/site-footer/view.js.
 *
 * Как подключается: inc/setup.php, acme_enqueue_site_script() — скрипт «acme-site» (defer, в подвале), перед ним
 * PHP кладёт window.ACME: подписи на языке страницы (ACME.s), язык (ACME.lang), признак холста редактора (ACME.editor).
 * Сборки и модулей нет: файл отдаётся браузеру как есть.
 *
 * Разделы, которые относятся к блокам, запускаются через init( root ) и помечают элемент once( element, 'key' ):
 * в редакторе (ACME.editor) превью блоков перерисовывается сколько угодно раз, а слушатели не дублируются.
 * Глобальное (тема, Esc, YouTube) и мобильное меню — только на сайте: в редакторе ничто не должно
 * открываться поверх текста и перехватывать клики.
 */
(function () {
	'use strict';

	const acme = window.ACME || {};
	const strings = acme.s || {};
	const isEditor = !!acme.editor;
	const html = document.documentElement;

	const THEME_KEY = 'acme-theme';
	const EDITOR_DEBOUNCE_MS = 150;

	const query = (selector, root) => (root || document).querySelector(selector);
	const queryAll = (selector, root) => Array.from((root || document).querySelectorAll(selector));

	// Как queryAll, но учитывает и сам root.
	function queryAllWithRoot(selector, root) {
		const elements = queryAll(selector, root);
		if (root && root !== document && root.matches && root.matches(selector)) {
			elements.unshift(root);
		}
		return elements;
	}

	// Элемент запускается один раз.
	function once(element, key) {
		const flag = 'acme' + key;
		const isFirstRun = !element[flag];
		element[flag] = true;
		return isFirstRun;
	}

	const storage = {
		get: function (key) {
			try {
				return localStorage.getItem(key);
			} catch {
				// Хранилище недоступно (приватный режим, запрет в настройках) — как будто значения нет.
				return null;
			}
		},
		set: function (key, value) {
			try {
				localStorage.setItem(key, value);
			} catch {
				// Хранилище недоступно — выбор не запоминается, сайт работает дальше.
			}
		},
	};

	const prefersReducedMotion = () =>
		window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- Блоки: всё, что может появиться в превью редактора ---------- */

	// Открытое меню отдаёт фокус первой ссылке, закрытое — возвращает его на кнопку.
	function setMenu(button, panel, isOpen) {
		panel.hidden = !isOpen;
		button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		button.setAttribute('aria-label', isOpen ? strings.close || 'Close' : strings.menu || 'Menu');
		html.classList.toggle('is-menu-open', isOpen);
		if (!isOpen) {
			button.focus();
			return;
		}
		const firstFocusable = query('a, button', panel);
		if (firstFocusable) {
			firstFocusable.focus();
		}
	}

	// Мобильное меню: кнопка с aria-controls; Esc и клик по ссылке закрывают.
	function initMenuToggle(button) {
		if (!once(button, 'menu')) {
			return;
		}
		const panel = document.getElementById(button.getAttribute('aria-controls'));
		if (!panel) {
			return;
		}
		button.addEventListener('click', () => setMenu(button, panel, panel.hidden));
		queryAll('a', panel).forEach((link) => {
			link.addEventListener('click', () => setMenu(button, panel, false));
		});
	}

	// Слайдер-лента: кнопки прокручивают на ширину видимой части. Плавность — здесь, не в CSS
	// (scroll-behavior: smooth вместе со scroll-snap ломает замер LCP).
	function initSlider(slider) {
		if (!once(slider, 'slider')) {
			return;
		}
		const track = query('[data-acme-track]', slider);
		if (!track) {
			return;
		}
		queryAll('[data-acme-dir]', slider).forEach((button) => {
			button.addEventListener('click', () => {
				track.scrollBy({
					left: +button.dataset.acmeDir * track.clientWidth,
					behavior: prefersReducedMotion() ? 'auto' : 'smooth',
				});
			});
		});
	}

	function init(root) {
		queryAllWithRoot('[data-acme-slider]', root).forEach((slider) => initSlider(slider));
		// Меню открывается на весь экран и запрещает прокрутку страницы — на холсте редактора это мешало бы писать.
		if (!isEditor) {
			queryAllWithRoot('[data-acme-menu-toggle]', root).forEach((button) => initMenuToggle(button));
		}
	}

	/* ---------- Тема: светлая / тёмная / как в системе ---------- */

	function applyTheme(darkQuery) {
		const preference = storage.get(THEME_KEY) || 'auto';
		const isDark =
			preference === 'dark' || (preference !== 'light' && darkQuery && darkQuery.matches);
		const scheme = isDark ? 'dark' : 'light';
		html.dataset.theme = scheme;
		html.style.colorScheme = scheme;
		queryAll('[data-acme-theme]').forEach((button) => {
			const isPressed = button.dataset.acmeTheme === preference;
			button.setAttribute('aria-pressed', isPressed ? 'true' : 'false');
		});
	}

	function initTheme() {
		const darkQuery = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
		queryAll('[data-acme-theme]').forEach((button) => {
			button.addEventListener('click', () => {
				storage.set(THEME_KEY, button.dataset.acmeTheme);
				applyTheme(darkQuery);
			});
		});
		if (darkQuery && darkQuery.addEventListener) {
			darkQuery.addEventListener('change', () => applyTheme(darkQuery));
		}
		applyTheme(darkQuery);
	}

	/* ---------- YouTube: плеер вместо превью только по клику (inc/embeds.php) ---------- */

	function onYoutubeClick(event) {
		const button = event.target.closest('[data-acme-yt]');
		if (!button) {
			return;
		}
		const frame = document.createElement('iframe');
		frame.src =
			'https://www.youtube-nocookie.com/embed/' +
			encodeURIComponent(button.dataset.acmeYt) +
			'?autoplay=1&rel=0';
		frame.title = button.dataset.title || button.getAttribute('aria-label');
		frame.allow =
			'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		frame.allowFullscreen = true;
		frame.className = 'acme-yt-frame';
		button.replaceWith(frame);
		frame.focus();
	}

	/* ---------- Esc закрывает открытое и возвращает фокус на кнопку ---------- */

	function onEscape(event) {
		if (event.key !== 'Escape') {
			return;
		}
		queryAll('[data-acme-menu-toggle][aria-expanded="true"]').forEach((button) => {
			setMenu(button, document.getElementById(button.getAttribute('aria-controls')), false);
		});
	}

	function startSite() {
		initTheme();
		document.addEventListener('click', onYoutubeClick);
		document.addEventListener('keydown', onEscape);
		init(document);
	}

	/* ---------- Редактор: холст с превью блоков ---------- */

	// Язык холста = язык записи (Polylang): от него зависят переносы слов (hyphens: auto), как на сайте.
	function syncCanvasLang() {
		let lang = acme.lang;
		try {
			lang = window.parent.wp.data.select('core/editor').getEditedPostAttribute('lang') || lang;
		} catch {
			// Нет доступа к окну редактора или хранилища записи (редактор сайта) — остаётся язык из ACME.lang.
		}
		if (lang && html.lang !== lang) {
			html.lang = lang;
		}
	}

	// Превью блоков приходят с сервера и перерисовываются: после каждой пачки изменений DOM
	// запускаем init заново (once() не даёт повесить слушатели дважды).
	function startEditorCanvas() {
		let timer;
		const refresh = function () {
			clearTimeout(timer);
			timer = setTimeout(() => {
				syncCanvasLang();
				init(document);
			}, EDITOR_DEBOUNCE_MS);
		};
		new MutationObserver(refresh).observe(html, { childList: true, subtree: true });
		refresh();
	}

	if (!isEditor) {
		startSite();
		return;
	}
	// Редактор: скрипт подключён и к странице админки, и к холсту — работаем только в холсте (iframe).
	if (!window.frameElement) {
		return;
	}
	startEditorCanvas();
})();
