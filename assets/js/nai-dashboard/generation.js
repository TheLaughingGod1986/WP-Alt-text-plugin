(function (window, document) {
	'use strict';

	var dom = window.BBAINaiDom || {};

	function createThumb(item, idx) {
		var thumb = item.thumb_url ? document.createElement('img') : document.createElement('div');
		var hue = Number(item.hue || 30);
		thumb.className = 'nai-thumb';
		if (item.thumb_url) {
			thumb.src = item.thumb_url;
			thumb.alt = '';
			thumb.loading = 'lazy';
			thumb.decoding = 'async';
			return thumb;
		}
		thumb.style.backgroundColor = 'oklch(0.93 0.02 ' + hue + ')';
		thumb.style.backgroundImage = 'repeating-linear-gradient(45deg, oklch(0.88 0.025 ' + hue + ') 0, oklch(0.88 0.025 ' + hue + ') 6px, oklch(0.93 0.02 ' + hue + ') 6px, oklch(0.93 0.02 ' + hue + ') 12px)';
		thumb.textContent = '#' + (idx + 1);
		return thumb;
	}

	function setDrawerOpen(drawer) {
		dom.show(drawer);
		drawer.setAttribute('aria-hidden', 'false');
	}

	function setProgress(parts, pct) {
		var value = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
		if (!parts || !parts.progress) {
			return;
		}
		parts.progress.style.setProperty('width', value + '%', 'important');
		parts.progress.setAttribute('aria-valuenow', String(value));
	}

	function appendResultRow(stream, item, idx, textValue, pending) {
		var row = document.createElement('div');
		var thumb = createThumb(item, idx);
		var body = document.createElement('div');
		var name = document.createElement('strong');
		var page = document.createElement('small');
		var text = document.createElement('p');
		var actions = document.createElement('div');
		var edit = document.createElement('button');
		var regenerate = document.createElement('button');
		var check = document.createElement('span');

		row.className = pending ? 'nai-gen-row is-pending' : 'nai-gen-row is-complete';
		row.setAttribute('data-nai-gen-row', '');
		row.setAttribute('data-nai-gen-row-index', String(idx));
		name.textContent = item.name || ('Image #' + (idx + 1));
		page.textContent = item.page || '';
		text.contentEditable = 'true';
		text.textContent = textValue || (pending ? 'Waiting for ALT text...' : 'ALT text generated and saved.');
		actions.className = 'nai-gen-row__actions';
		edit.type = 'button';
		edit.setAttribute('data-nai-gen-edit', '');
		edit.textContent = 'Edit';
		regenerate.type = 'button';
		regenerate.setAttribute('data-nai-gen-regen', '');
		regenerate.textContent = 'Regenerate';
		check.className = 'nai-gen-row__check';
		check.setAttribute('aria-hidden', 'true');
		check.textContent = '✓';

		actions.append(edit, regenerate);
		body.append(name, page, text, actions);
		row.append(thumb, body, check);
		stream.appendChild(row);
		return row;
	}

	function getGeneratedText(fallback) {
		return fallback || 'ALT text generated and saved. Open the Library to review the final wording.';
	}

	function ensureDrawerRows(parts, state, total) {
		var items = state.queueItems.length ? state.queueItems : [{ name: 'Image #1', page: '', hue: 30 }];
		var count = Math.max(1, total || items.length);
		var idx;
		var item;

		if (!parts.stream) {
			return;
		}

		for (idx = 0; idx < count; idx++) {
			if (parts.stream.querySelector('[data-nai-gen-row-index="' + idx + '"]')) {
				continue;
			}
			item = items[idx] || { name: 'Image #' + (idx + 1), page: '', hue: 145 };
			appendResultRow(parts.stream, item, idx, '', true);
		}
	}

	function markRowComplete(parts, state, idx, textValue) {
		var row = parts.stream ? parts.stream.querySelector('[data-nai-gen-row-index="' + idx + '"]') : null;
		var text;

		if (!row) {
			ensureDrawerRows(parts, state, idx + 1);
			row = parts.stream ? parts.stream.querySelector('[data-nai-gen-row-index="' + idx + '"]') : null;
		}
		if (!row) {
			return;
		}

		row.classList.remove('is-pending');
		row.classList.add('is-complete');
		text = row.querySelector('p');
		if (text) {
			text.textContent = getGeneratedText(textValue);
		}
	}

	function getResultIndex(drawer, id) {
		var items = drawer && drawer._naiState && drawer._naiState.queueItems ? drawer._naiState.queueItems : [];
		var normalizedId = String(id || '');
		var idx;

		if (normalizedId) {
			for (idx = 0; idx < items.length; idx++) {
				if (String(items[idx].id || items[idx].attachment_id || '') === normalizedId) {
					return idx;
				}
			}
		}

		return Math.max(0, parseInt(drawer && drawer._naiNextResultIndex, 10) || 0);
	}

	function recordLegacyResult(id, altText) {
		var root = document.querySelector('.nai-app');
		var parts = root ? getDrawerParts(root) : null;
		var drawer = parts && parts.drawer;
		var idx;

		if (!drawer || !drawer._naiLegacyMode) {
			return;
		}

		idx = getResultIndex(drawer, id);
		drawer._naiResultText = drawer._naiResultText || {};
		drawer._naiResultText[idx] = String(altText || '');
		drawer._naiNextResultIndex = Math.max(idx + 1, parseInt(drawer._naiNextResultIndex, 10) || 0);
	}

	function trimDrawerRows(parts, count) {
		var rows = parts.stream ? parts.stream.querySelectorAll('[data-nai-gen-row]') : [];
		Array.prototype.forEach.call(rows, function (row, idx) {
			if (idx >= count) {
				row.remove();
			}
		});
	}

	function getDrawerParts(root) {
		var drawer = root.querySelector('[data-nai-drawer]');
		if (!drawer) {
			return null;
		}
		return {
			drawer: drawer,
			stream: root.querySelector('[data-nai-drawer-stream]'),
			title: root.querySelector('[data-nai-drawer-title]'),
			eyebrow: root.querySelector('[data-nai-drawer-eyebrow]'),
			progress: root.querySelector('[data-nai-drawer-progress]'),
			status: root.querySelector('[data-nai-drawer-status]'),
			done: root.querySelector('[data-nai-complete-drawer]')
		};
	}

	function setDrawerComplete(parts, state, completed, total, hasIssues) {
		var label = completed === 1 ? '1 image improved' : completed + ' images improved';

		parts.drawer.classList.add('is-complete');
		setProgress(parts, 100);
		parts.eyebrow.textContent = 'Pass complete';
		parts.title.textContent = label;
		parts.status.textContent = hasIssues ? 'Some images may still need review.' : 'Coverage +2% · streak extended';
		parts.done.hidden = false;
		parts.drawer._naiDashboardSuccesses = Math.max(0, parseInt(completed, 10) || 0);
		if (!parts.drawer._naiLegacyMode && typeof window.CustomEvent === 'function') {
			document.dispatchEvent(new window.CustomEvent('bbai:nai-drawer:complete', {
				detail: {
					successes: parts.drawer._naiDashboardSuccesses,
					total: Math.max(0, parseInt(total, 10) || 0),
					hasIssues: !!hasIssues
				}
			}));
		}
	}

	function openDrawer(state, options) {
		var root = state.root;
		var parts = getDrawerParts(root);
		var drawer = parts && parts.drawer;
		var stream = parts && parts.stream;
		var title = parts && parts.title;
		var eyebrow = parts && parts.eyebrow;
		var progress = parts && parts.progress;
		var status = parts && parts.status;
		var done = parts && parts.done;
		var simulate = !options || options.simulate !== false;
		var items;
		var idx;

		if (!drawer || !stream || !title || !progress || !status || !done) {
			return;
		}

		items = state.queueItems.length ? state.queueItems : [{ name: 'hero-spring-collection.jpg', page: 'Homepage', hue: 30 }];
		idx = 0;

		stream.textContent = '';
		drawer.classList.remove('is-complete');
		setProgress(parts, 0);
		done.hidden = true;
		eyebrow.textContent = state.isPro ? 'Optimisation' : "Today's pass";
		title.textContent = state.isPro ? 'Optimising latest uploads...' : "Working through today's images...";
		status.textContent = state.isPro ? 'Autopilot active · improving images...' : "Working through today's images...";
		drawer._naiState = state;
		drawer._naiLastCompleted = 0;
		drawer._naiTotal = items.length;
		drawer._naiLegacyMode = !simulate;
		drawer._naiResultText = {};
		drawer._naiNextResultIndex = 0;
		drawer._naiDashboardSuccesses = 0;
		drawer.removeAttribute('data-nai-dashboard-applied');
		ensureDrawerRows(parts, state, items.length);
		setDrawerOpen(drawer);
		clearInterval(drawer._timer);
		if (!simulate) {
			return;
		}
		drawer._timer = setInterval(function () {
			markRowComplete(parts, state, idx, '');
			idx++;
			setProgress(parts, Math.round((idx / items.length) * 100));
			title.textContent = state.isPro ? idx + ' images improved' : idx + ' of ' + items.length + ' complete';
			status.textContent = idx >= items.length
				? (state.isPro ? 'Optimisation complete · Autopilot active.' : 'Your site is now more accessible.')
				: (items.length - idx) + ' images to go';

			if (idx >= items.length) {
				clearInterval(drawer._timer);
				setDrawerComplete(parts, state, idx, items.length, false);
			}
		}, 850);
	}

	function syncLegacyProgress(current, total, imageTitle) {
		var root = document.querySelector('.nai-app');
		var parts = root ? getDrawerParts(root) : null;
		var drawer = parts && parts.drawer;
		var completed = Math.max(0, parseInt(current, 10) || 0);
		var count = Math.max(0, parseInt(total, 10) || 0);
		var pct = count > 0 ? Math.min(100, Math.round((completed / count) * 100)) : 0;
		var idx;

		if (!parts || !drawer || !drawer._naiLegacyMode) {
			return;
		}

		drawer._naiTotal = Math.max(drawer._naiTotal || 0, count);
		ensureDrawerRows(parts, drawer._naiState, drawer._naiTotal);
		setProgress(parts, pct);
		parts.title.textContent = completed >= count && count > 0
			? count + ' images improved'
			: completed + ' of ' + count + ' complete';
		parts.status.textContent = completed >= count && count > 0
			? 'Your site is now more accessible.'
			: (count > 0 ? (count - completed) + ' images to go' : 'Preparing images...');

		for (idx = drawer._naiLastCompleted || 0; idx < completed; idx++) {
			markRowComplete(parts, drawer._naiState, idx, drawer._naiResultText && drawer._naiResultText[idx] ? drawer._naiResultText[idx] : '');
		}
		drawer._naiLastCompleted = Math.max(drawer._naiLastCompleted || 0, completed);
	}

	function completeLegacyProgress(detail) {
		var root = document.querySelector('.nai-app');
		var parts = root ? getDrawerParts(root) : null;
		var drawer = parts && parts.drawer;
		var successes = detail && detail.successes ? parseInt(detail.successes, 10) || 0 : 0;
		var failures = detail && detail.failures ? parseInt(detail.failures, 10) || 0 : 0;
		var skipped = detail && detail.skipped ? parseInt(detail.skipped, 10) || 0 : 0;
		var storedTotal;
		var total;
		var displaySuccesses;
		var renderedRows;

		if (!parts || !drawer || !drawer._naiLegacyMode) {
			return;
		}

		storedTotal = Math.max(0, parseInt(drawer._naiTotal, 10) || 0);
		renderedRows = parts.stream ? parts.stream.querySelectorAll('[data-nai-gen-row]').length : 0;
		total = Math.max(successes + failures + skipped, successes, storedTotal, renderedRows);
		displaySuccesses = successes > 0 ? successes : renderedRows;
		if (displaySuccesses <= 0 && failures <= 0 && skipped <= 0 && storedTotal > 0) {
			displaySuccesses = storedTotal;
		}
		if (displaySuccesses > 0 && total > 0) {
			syncLegacyProgress(displaySuccesses, total, '');
		}
		if (successes > 0 && successes < renderedRows) {
			trimDrawerRows(parts, successes);
		}
		setDrawerComplete(parts, drawer._naiState, displaySuccesses, Math.max(total, displaySuccesses), failures > displaySuccesses || skipped > 0);
	}

	window.BBAINaiGeneration = {
		openDrawer: openDrawer,
		recordLegacyResult: recordLegacyResult,
		syncLegacyProgress: syncLegacyProgress,
		completeLegacyProgress: completeLegacyProgress
	};
}(window, document));
