(function (window) {
	'use strict';

	function isServerLockedBulkControl(element) {
		if (!element) {
			return false;
		}

		var action = String(element.getAttribute('data-bbai-action') || '').toLowerCase();
		return action === 'open-upgrade' ||
			action === 'open-signup' ||
			action === 'open-usage' ||
			element.getAttribute('data-bbai-locked-cta') === '1' ||
			element.getAttribute('data-bbai-lock-control') === '1' ||
			element.classList.contains('bbai-upgrade-required-action') ||
			element.classList.contains('bbai-is-locked') ||
			element.classList.contains('bbai-optimization-cta--locked') ||
			element.classList.contains('bbai-optimization-cta--disabled');
	}

	window.BBAIAltLibraryBulkSelection = {
		isServerLockedBulkControl: isServerLockedBulkControl
	};
}(window));
