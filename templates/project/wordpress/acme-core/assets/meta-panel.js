/*
 * Панели полей записи. Схема полей приходит из PHP (acme_meta_schema) в window.ACME_META:
 * { ключ: [ тип, подпись, параметры, панель ] }. Поля без панели — в «Поля сайта».
 * Типы: boolean, integer, string, date, service, post (выпадающие списки), rows (повторяющиеся строки).
 *
 * Как подключается: inc/editor.php — скрипт «acme-meta-panel» в редакторе записи, у типа которой есть схема.
 * Перед ним PHP кладёт window.ACME_META и списки для выпадающих полей: window.ACME_SERVICES, window.ACME_POSTS
 * (пары [ ID, название ] на языке записи). Сборки нет: используются глобальные wp.*.
 */
(function (wp) {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { TextControl, TextareaControl, ToggleControl, SelectControl, Button, BaseControl } =
		wp.components;
	const { useSelect, useDispatch } = wp.data;
	const el = wp.element.createElement;
	const schema = window.ACME_META || {};
	const common = { __nextHasNoMarginBottom: true, __next40pxDefaultSize: true };

	const DEFAULT_PANEL = 'Поля сайта';
	const DEFAULT_MAX_ROWS = 20;
	// Строковые поля с такими ключами — длинный текст: им нужно многострочное поле.
	const LONG_TEXT_KEYS = /desc|problem|done|blurb|kw|lead|cta_p/;
	const ROW_STYLE = {
		display: 'grid',
		gap: '8px',
		padding: '10px',
		border: '1px solid #ddd',
		borderRadius: '4px',
	};

	/* Повторяющиеся строки: params.fields = [ [ ключ, подпись, 'text' | 'date' | 'a:Подпись|b:Подпись' ] ], params.max. */

	// Вид ячейки с двоеточием — выпадающий список «значение:Подпись|значение:Подпись».
	const isChoice = (kind) => kind.includes(':');

	function choiceOptions(kind) {
		return kind.split('|').map((pair) => {
			const [value, label] = pair.split(':');
			return { value, label };
		});
	}

	// Новая строка: текст и дата пустые, в списке — первый вариант.
	function blankRow(fields) {
		return Object.fromEntries(
			fields.map(([key, , kind]) => [key, isChoice(kind) ? choiceOptions(kind)[0].value : '']),
		);
	}

	function rowCell([key, label, kind], row, onChange) {
		if (isChoice(kind)) {
			const options = choiceOptions(kind);
			return el(SelectControl, {
				key,
				label,
				value: row[key] || options[0].value,
				options,
				onChange,
				...common,
			});
		}
		return el(TextControl, {
			key,
			label,
			type: kind === 'date' ? 'date' : 'text',
			value: row[key] || '',
			onChange,
			...common,
		});
	}

	function rowCard({ row, index, fields, table }) {
		return el(
			'div',
			{ key: index, style: ROW_STYLE },
			fields.map((field) => rowCell(field, row, (value) => table.setCell(index, field[0], value))),
			el(
				Button,
				{ variant: 'link', isDestructive: true, onClick: () => table.remove(index) },
				'Удалить строку',
			),
		);
	}

	function Rows({ label, value, params, onChange }) {
		const rows = Array.isArray(value) ? value : [];
		const max = params.max || DEFAULT_MAX_ROWS;
		const table = {
			setCell: (index, key, cellValue) =>
				onChange(rows.map((row, j) => (j === index ? { ...row, [key]: cellValue } : row))),
			remove: (index) => onChange(rows.filter((_, j) => j !== index)),
		};
		return el(
			BaseControl,
			{ label, __nextHasNoMarginBottom: true },
			el(
				'div',
				{ style: { display: 'grid', gap: '12px' } },
				rows.map((row, index) => rowCard({ row, index, fields: params.fields, table })),
				rows.length < max &&
					el(
						Button,
						{
							variant: 'secondary',
							onClick: () => onChange(rows.concat([blankRow(params.fields)])),
						},
						'Добавить строку',
					),
			),
		);
	}

	/* Поля записи: по одной функции на тип из схемы. */

	// Список записей или услуг; значение 0 — пункт «Автоматически».
	function postSelect({ type, label, value, onChange }) {
		const list = (type === 'post' ? window.ACME_POSTS : window.ACME_SERVICES) || [];
		const options = [{ value: '0', label: '— Автоматически —' }].concat(
			list.map(([id, name]) => ({ value: String(id), label: name })),
		);
		return el(SelectControl, {
			label,
			value: String(value || 0),
			options,
			onChange: (selected) => onChange(parseInt(selected, 10)),
			...common,
		});
	}

	function inputField({ type, label, value, onChange }) {
		if (type === 'integer') {
			return el(TextControl, {
				label,
				type: 'number',
				value: value ?? 0,
				onChange: (text) => onChange(parseInt(text || '0', 10)),
				...common,
			});
		}
		return el(TextControl, { label, type: 'date', value: value ?? '', onChange, ...common });
	}

	function textField({ name, label, value, onChange }) {
		return el(LONG_TEXT_KEYS.test(name) ? TextareaControl : TextControl, {
			label,
			value: value ?? '',
			onChange,
			...common,
		});
	}

	function Field({ name, meta, setMeta }) {
		const [type, label, params] = schema[name];
		const value = meta[name];
		const onChange = (newValue) => setMeta(name, newValue);
		if (type === 'boolean') {
			return el(ToggleControl, {
				label,
				checked: !!value,
				onChange,
				__nextHasNoMarginBottom: true,
			});
		}
		if (type === 'service' || type === 'post') {
			return postSelect({ type, label, value, onChange });
		}
		if (type === 'integer' || type === 'date') {
			return inputField({ type, label, value, onChange });
		}
		if (type === 'rows') {
			return el(Rows, { label, value, params, onChange });
		}
		return textField({ name, label, value, onChange });
	}

	// Поля по панелям в порядке схемы: { название панели: [ ключи ] }.
	function groupByPanel() {
		const groups = {};
		Object.keys(schema).forEach((name) => {
			const panel = schema[name][3] || DEFAULT_PANEL;
			(groups[panel] = groups[panel] || []).push(name);
		});
		return groups;
	}

	function Panels() {
		const meta = useSelect(
			(select) => select('core/editor').getEditedPostAttribute('meta') || {},
			[],
		);
		const { editPost } = useDispatch('core/editor');
		const setMeta = (name, value) => editPost({ meta: { [name]: value } });
		const groups = groupByPanel();
		return Object.keys(groups).map((panel, index) =>
			el(
				PluginDocumentSettingPanel,
				{ key: panel, name: 'acme-meta-' + index, title: panel, initialOpen: index === 0 },
				el(
					'div',
					{ style: { display: 'grid', gap: '16px' } },
					groups[panel].map((name) => el(Field, { key: name, name, meta, setMeta })),
				),
			),
		);
	}

	registerPlugin('acme-meta-panel', { render: Panels });
})(window.wp);
