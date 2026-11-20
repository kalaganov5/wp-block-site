/*
 * Блоки темы в редакторе: превью с сервера (ServerSideRender) и панель настроек,
 * которая строится по атрибутам из block.json:
 *   строка → поле (длинная — многострочное), да/нет → переключатель, число → числовое поле,
 *   список строк → «по одной на строку», список объектов → карточки с полями, «+ Добавить», «Удалить».
 * Список объектов по умолчанию пуст ("default": []), а поля его элемента описаны рядом, в "itemShape":
 * образец в default попадал бы на сайт как настоящая карточка с текстом-заглушкой.
 * Сборка не нужна: используются глобальные wp.*.
 *
 * Как подключается: inc/blocks.php регистрирует скрипт «acme-editor» (редактор блоков) и перед ним кладёт
 * window.ACME_BLOCKS — имена блоков из blocks/<имя>/block.json. Для каждого имени здесь регистрируется edit.
 */
(function (wp) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, TextareaControl, ToggleControl, Button, Disabled } =
		wp.components;
	const { useSelect } = wp.data;
	const el = wp.element.createElement;
	const ServerSideRender = wp.serverSideRender;

	// Подписи полей панели «Настройки блока» по ключам атрибутов. Ключа нет — показывается сам ключ.
	const LABELS = {
		title: 'Заголовок',
		lead: 'Описание',
		items: 'Элементы',
		moreLabel: 'Текст ссылки',
		sub: 'Подзаголовок',
		submit: 'Текст кнопки',
		formId: 'ID формы CF7 (0 — по языку страницы)',
		url: 'Ссылка',
		post: 'ID записи',
		urgent: 'Выделить',
	};
	// Служебные атрибуты WordPress: в панели блока им не место.
	const HIDDEN_ATTRIBUTES = ['lock', 'metadata', 'className', 'style'];
	const LONG_TEXT_LENGTH = 60;
	const ITEM_TITLE_LENGTH = 48;

	const labelFor = (name) => LABELS[name] || name;
	const isLongText = (value) => typeof value === 'string' && value.length > LONG_TEXT_LENGTH;
	const isObjectList = (list) => list.length > 0 && typeof list[0] === 'object' && list[0] !== null;

	function StringList({ value, onChange, title }) {
		return el(TextareaControl, {
			label: title + ' — по одному на строку',
			value: (value || []).join('\n'),
			rows: Math.min(10, (value || []).length + 2),
			onChange: (text) => onChange(text.split('\n')),
			__nextHasNoMarginBottom: true,
		});
	}

	function toggleField({ name, value, onChange }) {
		return el(ToggleControl, {
			label: labelFor(name),
			checked: !!value,
			onChange,
			__nextHasNoMarginBottom: true,
		});
	}

	// Пустое числовое поле хранится как null, а не 0: «не задано» и «ноль» — разные значения.
	function numberField({ name, value, onChange }) {
		return el(TextControl, {
			label: labelFor(name),
			type: 'number',
			value: value ?? '',
			onChange: (text) => onChange(text === '' ? null : Number(text)),
			__nextHasNoMarginBottom: true,
		});
	}

	// Список объектов узнаётся по образцу элемента (itemShape в block.json) или по уже сохранённым элементам.
	function listField({ name, value, onChange, itemShape }, shape) {
		const List = itemShape || isObjectList(shape) ? ObjectList : StringList;
		return el(List, { title: labelFor(name), value: value || [], onChange, itemShape });
	}

	// Вложенный объект: по полю на каждый ключ образца.
	function objectField({ name, value, onChange }, shape) {
		const current = value || {};
		return el(
			'div',
			{ className: 'acme-ed-obj' },
			el('strong', null, labelFor(name)),
			Object.keys(shape).map((subName) =>
				el(Field, {
					key: subName,
					name: subName,
					value: current[subName],
					sample: shape[subName],
					onChange: (subValue) => onChange({ ...current, [subName]: subValue }),
				}),
			),
		);
	}

	function textField({ name, value, onChange }) {
		return el(isLongText(value) ? TextareaControl : TextControl, {
			label: labelFor(name),
			value: value ?? '',
			onChange,
			__nextHasNoMarginBottom: true,
		});
	}

	// Вид поля выбирается по форме данных: по значению, а пока его нет — по образцу (default из block.json).
	function Field(props) {
		const shape = props.value === undefined ? props.sample : props.value;
		if (typeof shape === 'boolean') {
			return toggleField(props);
		}
		if (typeof shape === 'number' || shape === null) {
			return numberField(props);
		}
		if (Array.isArray(shape)) {
			return listField(props, shape);
		}
		if (shape && typeof shape === 'object') {
			return objectField(props, shape);
		}
		return textField(props);
	}

	function itemTitle(item, index) {
		const title = item.name || item.title || item.s || item.label || '#' + (index + 1);
		return String(title).slice(0, ITEM_TITLE_LENGTH);
	}

	// Карточка одного элемента списка: поля по образцу и «Удалить».
	function objectListItem({ item, index, list }) {
		return el(
			PanelBody,
			{ key: index, title: itemTitle(item, index), initialOpen: false },
			Object.keys(list.sample).map((name) =>
				el(Field, {
					key: name,
					name,
					value: item[name],
					sample: list.sample[name],
					onChange: (fieldValue) => list.replace(index, { ...item, [name]: fieldValue }),
				}),
			),
			el(
				Button,
				{ isDestructive: true, variant: 'link', onClick: () => list.remove(index) },
				'Удалить',
			),
		);
	}

	// Новый элемент — копия образца (itemShape); у списков без образца — копия первого элемента.
	function ObjectList({ title, value, onChange, itemShape }) {
		const sample = itemShape || value[0] || {};
		const list = {
			sample,
			replace: (index, item) => onChange(value.map((current, j) => (j === index ? item : current))),
			remove: (index) => onChange(value.filter((_, j) => j !== index)),
		};
		return el(
			'div',
			{ className: 'acme-ed-list' },
			el('strong', null, title),
			value.map((item, index) => objectListItem({ item, index, list })),
			el(
				Button,
				{
					variant: 'secondary',
					onClick: () => onChange(value.concat([JSON.parse(JSON.stringify(sample))])),
				},
				'+ Добавить',
			),
		);
	}

	// Язык записи (Polylang): превью запрашивается на нём, а не на языке админки.
	function useEditedLang() {
		return useSelect((select) => {
			const editor = select('core/editor');
			return (
				(editor && editor.getEditedPostAttribute && editor.getEditedPostAttribute('lang')) || ''
			);
		}, []);
	}

	function settingsPanel({ definitions, attributes, setAttributes }) {
		const names = Object.keys(definitions).filter((name) => !HIDDEN_ATTRIBUTES.includes(name));
		if (!names.length) {
			return null;
		}
		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: 'Настройки блока', initialOpen: true },
				names.map((name) =>
					el(Field, {
						key: name,
						name,
						value: attributes[name],
						sample: definitions[name].default,
						itemShape: definitions[name].itemShape,
						onChange: (value) => setAttributes({ [name]: value }),
					}),
				),
			),
		);
	}

	// Пустой блок на сайте не выводит ничего, а в редакторе — короткую подпись: иначе его не найти и не выбрать.
	// С настройками — подсказка, где заполнить; без настроек (хлебные крошки на главной) — что так и должно быть.
	function emptyNotice(blockName, hasSettings) {
		const title = (wp.blocks.getBlockType(blockName) || {}).title || blockName;
		const hint = hasSettings
			? 'пусто — заполните в настройках блока (панель справа)'
			: 'на этой странице ничего не выводит';
		return el('div', { className: 'acme-ed-empty' }, title + ': ' + hint);
	}

	// POST, а не GET: в адресе запроса false во вложенных настройках стал бы строкой «false» (= true в PHP).
	function serverPreview({ blockName, attributes, lang, hasSettings }) {
		return el(
			Disabled,
			null,
			el(ServerSideRender, {
				block: blockName,
				attributes,
				httpMethod: 'POST',
				urlQueryArgs: lang ? { acmelang: lang } : {},
				EmptyResponsePlaceholder: () => emptyNotice(blockName, hasSettings),
			}),
		);
	}

	function makeEdit(blockName) {
		return function Edit({ attributes, setAttributes }) {
			const definitions = (wp.blocks.getBlockType(blockName) || {}).attributes || {};
			const lang = useEditedLang();
			const panel = settingsPanel({ definitions, attributes, setAttributes });
			return el(
				'div',
				useBlockProps(),
				panel,
				serverPreview({ blockName, attributes, lang, hasSettings: !!panel }),
			);
		};
	}

	(window.ACME_BLOCKS || []).forEach((blockName) => {
		if (!wp.blocks.getBlockType(blockName)) {
			registerBlockType(blockName, { edit: makeEdit(blockName), save: () => null });
		}
	});
})(window.wp);
