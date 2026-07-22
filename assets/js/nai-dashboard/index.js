(function (window, document) {
	'use strict';

	var stateFactory = window.BBAINaiDashboardState || {};
	var events = window.BBAINaiDashboardEvents || {};
	var root = document.querySelector('.nai-app');

	if (!root || !stateFactory.create || !events.bind) {
		return;
	}

	if (root.getAttribute('data-nai-dashboard-bound') === '1') {
		return;
	}
	root.setAttribute('data-nai-dashboard-bound', '1');

	events.bind(stateFactory.create(root));
}(window, document));
