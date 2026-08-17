(function (window) {
	'use strict';

	var dom = window.BBAINaiDom || {};

	function open(root, name) {
		if (!root || !dom.show) {
			return;
		}
		dom.show(root.querySelector('[data-nai-modal="' + name + '"]'));
	}

	function closeAll(root) {
		if (!root || !dom.hide) {
			return;
		}
		root.querySelectorAll('[data-nai-modal]').forEach(dom.hide);
	}

	window.BBAINaiModals = {
		open: open,
		closeAll: closeAll
	};
}(window));
