(function (window) {
	'use strict';

	function noop() {}

	function runSelectedBulk(config, trigger, dependencies) {
		var settings = config || {};
		var deps = dependencies || {};
		var ids = typeof settings.getIds === 'function' ? settings.getIds() : [];

		if (!ids.length) {
			if (typeof deps.setGenerationInProgress === 'function') {
				deps.setGenerationInProgress(false);
			}
			if (typeof deps.showNotice === 'function') {
				deps.showNotice('info', settings.emptyMessage || '');
			}
			return;
		}

		if (typeof deps.canStart === 'function' && !deps.canStart(trigger, { requireBulkConfig: true })) {
			return;
		}

		if (typeof deps.acquireLock === 'function' && !deps.acquireLock(settings.lockKey || 'bulk', null)) {
			return;
		}

		if (typeof deps.setRuntimeState === 'function') {
			deps.setRuntimeState('generation_starting');
		}

		var progressLabel = typeof settings.buildProgressLabel === 'function'
			? settings.buildProgressLabel(ids.length)
			: '';

		if (typeof deps.queueImages !== 'function') {
			if (typeof deps.setGenerationInProgress === 'function') {
				deps.setGenerationInProgress(false);
			}
			if (typeof deps.logError === 'function') {
				deps.logError(settings.failureMessage || '');
			}
			if (typeof deps.showNotice === 'function') {
				deps.showNotice('error', settings.failureMessage || '');
			}
			return;
		}

		deps.queueImages(ids, settings.source || 'bulk', { skipSchedule: true }, function(success, queued, error, processedIds, responseData) {
			var result = typeof deps.normalizeQueueResult === 'function'
				? deps.normalizeQueueResult(success, queued, error, processedIds, ids, responseData, {
					source: settings.source || 'bulk',
					entry: settings.entry || '',
					zeroEntry: settings.zeroEntry || '',
					progressLabel: progressLabel,
					failureMessage: settings.failureMessage || ''
				})
				: null;

			if (!result) {
				if (success && queued > 0) {
					result = {
						status: 'start',
						ids: processedIds || ids,
						flow: {
							source: settings.source || 'bulk',
							entry: settings.entry || '',
							responseData: responseData,
							progressLabel: progressLabel,
							queued: queued
						}
					};
				} else if (success && queued === 0) {
					result = {
						status: 'start',
						ids: processedIds || ids,
						flow: {
							source: settings.source || 'bulk',
							entry: settings.zeroEntry || '',
							responseData: responseData,
							progressLabel: progressLabel,
							queued: 0
						}
					};
				} else {
					result = {
						status: 'error',
						error: error || null,
						message: error && error.message ? error.message : (settings.failureMessage || '')
					};
				}
			}

			if (result.status === 'start') {
				deps.startGenerationFlow(result.ids || ids, result.flow || {});
				return;
			}

			if (typeof deps.isLimitReachedError === 'function' && deps.isLimitReachedError(result.error)) {
				if (typeof deps.setGenerationInProgress === 'function') {
					deps.setGenerationInProgress(false);
				}
				if (typeof deps.hideBulkProgress === 'function') {
					deps.hideBulkProgress();
				}
				if (typeof deps.handleLimitReached === 'function') {
					deps.handleLimitReached(result.error);
				}
				return;
			}

			var message = result.message || settings.failureMessage || '';
			if (typeof deps.setGenerationInProgress === 'function') {
				deps.setGenerationInProgress(false);
			}
			if (typeof deps.setRuntimeState === 'function') {
				deps.setRuntimeState('generation_failed');
			}
			if (typeof deps.logError === 'function') {
				deps.logError(message);
			}
			if (typeof deps.showNotice === 'function') {
				deps.showNotice('error', message);
			}
		});
	}

	function runGenerateSelected(trigger, dependencies) {
		var deps = dependencies || {};
		return runSelectedBulk({
			getIds: deps.getGenerateSelectedIds || noop,
			source: 'bulk',
			lockKey: 'bulk',
			entry: 'runBulkGenerateSelected',
			zeroEntry: 'runBulkGenerateSelected_zero',
			emptyMessage: deps.generateEmptyMessage || '',
			failureMessage: deps.failureMessage || '',
			buildProgressLabel: deps.buildGenerateProgressLabel || noop
		}, trigger, deps);
	}

	function runRegenerateSelected(trigger, dependencies) {
		var deps = dependencies || {};
		return runSelectedBulk({
			getIds: deps.getRegenerateSelectedIds || noop,
			source: 'bulk-regenerate',
			lockKey: 'bulk-regenerate',
			entry: 'runBulkRegenerateSelected',
			zeroEntry: 'runBulkRegenerateSelected_zero',
			emptyMessage: deps.regenerateEmptyMessage || '',
			failureMessage: deps.failureMessage || '',
			buildProgressLabel: deps.buildRegenerateProgressLabel || noop
		}, trigger, deps);
	}

	window.BBAIAltLibraryBulkOrchestration = {
		runGenerateSelected: runGenerateSelected,
		runRegenerateSelected: runRegenerateSelected,
		runSelectedBulk: runSelectedBulk
	};
}(window));
