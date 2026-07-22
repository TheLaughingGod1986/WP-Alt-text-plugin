(function (window) {
	'use strict';

	function buildGenerateSelectedProgressLabel(count, i18n) {
		var tools = i18n || {};
		if (typeof tools.sprintf === 'function' && typeof tools.n === 'function') {
			return tools.sprintf(
				tools.n('Preparing %d selected image...', 'Preparing %d selected images...', count),
				count
			);
		}
		return count === 1 ? 'Preparing 1 selected image...' : 'Preparing ' + count + ' selected images...';
	}

	function buildRegenerateSelectedProgressLabel(count, i18n) {
		var tools = i18n || {};
		if (typeof tools.sprintf === 'function' && typeof tools.n === 'function') {
			return tools.sprintf(
				tools.n('Preparing %d image...', 'Preparing %d images...', count),
				count
			);
		}
		return count === 1 ? 'Preparing 1 image...' : 'Preparing ' + count + ' images...';
	}

	function normalizeSelectedBulkQueueResult(success, queued, error, processedIds, fallbackIds, responseData, options) {
		var settings = options || {};
		var safeQueued = Math.max(0, parseInt(queued, 10) || 0);
		var ids = processedIds || fallbackIds || [];

		if (success && safeQueued > 0) {
			return {
				status: 'start',
				ids: ids,
				flow: {
					source: settings.source || 'bulk',
					entry: settings.entry || '',
					responseData: responseData,
					progressLabel: settings.progressLabel || '',
					queued: safeQueued
				}
			};
		}

		if (success && safeQueued === 0) {
			return {
				status: 'start',
				ids: ids,
				flow: {
					source: settings.source || 'bulk',
					entry: settings.zeroEntry || '',
					responseData: responseData,
					progressLabel: settings.progressLabel || '',
					queued: 0
				}
			};
		}

		return {
			status: 'error',
			error: error || null,
			message: error && error.message ? error.message : (settings.failureMessage || '')
		};
	}

	window.BBAIAltLibraryBulkProgress = {
		buildGenerateSelectedProgressLabel: buildGenerateSelectedProgressLabel,
		buildRegenerateSelectedProgressLabel: buildRegenerateSelectedProgressLabel,
		normalizeSelectedBulkQueueResult: normalizeSelectedBulkQueueResult
	};
}(window));
