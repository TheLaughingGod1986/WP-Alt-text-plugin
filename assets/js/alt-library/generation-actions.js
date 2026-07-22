(function (window) {
	'use strict';

	function isEntitlementExhausted() {
		return !!(
			window.BBAIEntitlements &&
			typeof window.BBAIEntitlements.get === 'function' &&
			typeof window.BBAIEntitlements.isExhausted === 'function' &&
			window.BBAIEntitlements.get() &&
			window.BBAIEntitlements.isExhausted()
		);
	}

	function primeCanonicalUsage(usage) {
		if (!usage || typeof usage !== 'object') {
			return;
		}
		window.BBAI_STATE = window.BBAI_STATE || {};
		window.BBAI_STATE.usage = usage;
		window.BBAI_STATE.plan = window.BBAI_STATE.plan || {
			slug: usage.plan || usage.plan_type || 'free'
		};
	}

	function canStart(trigger, context) {
		var settings = context || {};
		var lockedEvent = settings.event || { preventDefault: function () {} };
		var exhausted = isEntitlementExhausted();

		if (settings.requireBulkConfig && !settings.hasBulkConfig) {
			if (typeof settings.setGenerationInProgress === 'function') {
				settings.setGenerationInProgress(false);
			}
			if (settings.modal && typeof settings.modal.error === 'function') {
				settings.modal.error(settings.configErrorMessage || 'Configuration error. Please refresh the page and try again.');
			} else if (typeof settings.showNotice === 'function') {
				settings.showNotice('error', settings.configErrorMessage || 'Configuration error. Please refresh the page and try again.');
			}
			return false;
		}

		if (
			(typeof settings.isOutOfCredits === 'function' && settings.isOutOfCredits()) ||
			(typeof settings.isLockedControl === 'function' && settings.isLockedControl(trigger))
		) {
			var usage = typeof settings.getUsageForQuotaChecks === 'function'
				? settings.getUsageForQuotaChecks()
				: null;

			if (typeof settings.setGenerationInProgress === 'function') {
				settings.setGenerationInProgress(false);
			}

			if (exhausted && typeof settings.openUpgradeModal === 'function') {
				primeCanonicalUsage(usage);
				settings.openUpgradeModal('upgrade_required', {
					trigger: trigger,
					source: typeof settings.getLockedCtaSource === 'function' ? settings.getLockedCtaSource(trigger) : 'library',
					usage: usage,
					message: settings.exhaustedMessage || 'You have no generation credits remaining. Upgrade to continue generating ALT text.'
				});
				return false;
			}

			if (typeof settings.handleLockedCtaClick === 'function') {
				settings.handleLockedCtaClick(trigger, lockedEvent);
			}
			return false;
		}

		return true;
	}

	window.BBAIAltLibraryGenerationActions = {
		canStart: canStart,
		isEntitlementExhausted: isEntitlementExhausted,
		primeCanonicalUsage: primeCanonicalUsage
	};
}(window));
