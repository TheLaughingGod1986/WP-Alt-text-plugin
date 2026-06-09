(function (window) {
	'use strict';

	function isMissingRow(row) {
		return !!(row && String(row.getAttribute('data-alt-missing') || 'false') === 'true');
	}

	function parseOptionalCount(value) {
		var parsed = parseInt(value, 10);
		return isNaN(parsed) ? null : parsed;
	}

	function applyRegenerateSuccessToRow(row, attachmentId, altText, payload, renderOptions, deps) {
		var settings = deps || {};
		var clean = String(altText || '').trim();
		var nextOptions;

		if (!row || !clean) {
			return;
		}

		nextOptions = renderOptions && typeof renderOptions === 'object'
			? renderOptions
			: (
				typeof settings.buildRenderOptions === 'function'
					? settings.buildRenderOptions(payload && payload.meta ? payload.meta : null, { approved: false })
					: {}
			);

		if (typeof settings.renderAltCell === 'function') {
			settings.renderAltCell(row, clean, nextOptions);
		}
		if (typeof settings.updateLastUpdated === 'function') {
			settings.updateLastUpdated(row, payload && payload.meta && payload.meta.generated ? payload.meta.generated : '');
		}
		if (typeof settings.updateSelectionState === 'function') {
			settings.updateSelectionState();
		}
		if (row.getAttribute('data-bbai-filter-exit-in-flight') !== '1' && typeof settings.flashSuccess === 'function') {
			settings.flashSuccess(row);
		}
		if (typeof settings.syncPreview === 'function') {
			settings.syncPreview(row, attachmentId);
		}
	}

	function applyOptimisticMissingResolved(deps) {
		var settings = deps || {};
		if (typeof settings.applyOptimisticMissingResolved === 'function') {
			settings.applyOptimisticMissingResolved();
		}
	}

	function refreshStats(deps) {
		var settings = deps || {};
		if (typeof settings.refreshStats === 'function') {
			settings.refreshStats();
			window.setTimeout(function () {
				settings.refreshStats();
			}, 450);
		}
	}

	function applyGenerationSuccess(context, deps) {
		var settings = deps || {};
		var data = context || {};
		var row = data.row || null;
		var payload = data.payload || {};
		var trimmedAlt = String(data.altText || '').trim();
		var wasMissingRow = typeof data.wasMissing === 'boolean' ? data.wasMissing : isMissingRow(row);
		var workspaceRoot = typeof settings.getWorkspaceRoot === 'function' ? settings.getWorkspaceRoot() : null;
		var missingBefore = workspaceRoot ? parseOptionalCount(workspaceRoot.getAttribute('data-bbai-missing-count')) : null;
		var statsPayload = typeof settings.coerceStats === 'function' ? settings.coerceStats(payload ? payload.stats : null) : (payload ? payload.stats : null);
		var renderOptions = typeof settings.buildRenderOptions === 'function'
			? settings.buildRenderOptions(payload && payload.meta ? payload.meta : null, { approved: false })
			: {};
		var payloadMissing;
		var serverMissingStale;

		if (row && trimmedAlt) {
			applyRegenerateSuccessToRow(row, data.attachmentId, trimmedAlt, payload, renderOptions, settings);
		}

		if (statsPayload) {
			if (typeof settings.updateCoverageCard === 'function') {
				settings.updateCoverageCard(statsPayload);
			}
			if (typeof settings.updateFilterCounts === 'function') {
				settings.updateFilterCounts(statsPayload);
			}
			if (typeof settings.dispatchStatsUpdated === 'function') {
				settings.dispatchStatsUpdated(statsPayload);
			}
		}

		payloadMissing = statsPayload ? parseInt(statsPayload.images_missing_alt, 10) : NaN;
		serverMissingStale =
			wasMissingRow &&
			trimmedAlt &&
			(!statsPayload ||
				isNaN(payloadMissing) ||
				(missingBefore != null && !isNaN(payloadMissing) && payloadMissing >= missingBefore));

		if (serverMissingStale) {
			applyOptimisticMissingResolved(settings);
		}

		refreshStats(settings);

		if (typeof settings.refreshUsage === 'function') {
			settings.refreshUsage(payload ? payload.usage : null);
		}
		if (typeof settings.updateTrialUsage === 'function') {
			settings.updateTrialUsage();
		}
		if (typeof settings.setRuntimeState === 'function') {
			settings.setRuntimeState('generation_complete');
		}

		return {
			isMissing: wasMissingRow,
			statsPayload: statsPayload,
			trimmedAlt: trimmedAlt
		};
	}

	function syncLibraryRowAfterGeneration(id, altText, payload, deps) {
		var settings = deps || {};
		var doc = settings.document || window.document;
		var row = doc.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
		var renderOptions;

		if (!row) {
			return;
		}

		renderOptions = typeof settings.buildRenderOptions === 'function'
			? settings.buildRenderOptions(payload && payload.meta ? payload.meta : null, { approved: false })
			: {};

		applyRegenerateSuccessToRow(row, id, altText, payload || null, renderOptions, settings);

		if (typeof settings.syncPreview === 'function') {
			settings.syncPreview(row, id);
		}
	}

	window.BBAIAltLibraryState = {
		applyGenerationSuccess: applyGenerationSuccess,
		applyOptimisticMissingResolved: applyOptimisticMissingResolved,
		applyRegenerateSuccessToRow: applyRegenerateSuccessToRow,
		isMissingRow: isMissingRow,
		syncLibraryRowAfterGeneration: syncLibraryRowAfterGeneration
	};
}(window));
