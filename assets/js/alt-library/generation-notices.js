(function (window) {
	'use strict';

	function show(type, message, options) {
		var settings = options || {};
		if (!message) {
			return;
		}

		if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
			window.bbaiPushToast(type || 'info', message, { duration: settings.duration || 4500 });
			return;
		}

		if (typeof settings.fallback === 'function') {
			settings.fallback(type || 'info', message);
		}
	}

	window.BBAIAltLibraryGenerationNotices = {
		show: show
	};
}(window));
