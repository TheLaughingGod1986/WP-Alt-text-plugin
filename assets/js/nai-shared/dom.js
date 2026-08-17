(function (window) {
	'use strict';

	function show(el) {
		if (!el) {
			return;
		}
		el.hidden = false;
		el.setAttribute('aria-hidden', 'false');
	}

	function hide(el) {
		if (!el) {
			return;
		}
		el.hidden = true;
		el.setAttribute('aria-hidden', 'true');
	}

	window.BBAINaiDom = {
		show: show,
		hide: hide
	};
}(window));
