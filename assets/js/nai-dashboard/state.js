(function (window, document) {
	'use strict';

		function parseDrawerItems(root) {
			try {
				var itemsNode = root.querySelector('[data-nai-drawer-items]');
				return itemsNode ? JSON.parse(itemsNode.getAttribute('data-nai-drawer-items-json') || itemsNode.textContent || '[]') : [];
			} catch (err) {
				return [];
			}
	}

	function create(root) {
		return {
			root: root,
			prototypeMode: root.getAttribute('data-nai-prototype') === '1',
			isPro: root.getAttribute('data-nai-is-pro') === '1',
			demoTrigger: root.getAttribute('data-nai-demo-trigger') || '',
			queueItems: parseDrawerItems(root),
			onboardingStep: 0
		};
	}

	window.BBAINaiDashboardState = {
		create: create
	};
}(window, document));
