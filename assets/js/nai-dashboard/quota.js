(function (window) {
	'use strict';

	var modals = window.BBAINaiModals || {};

	function paywallCopy(trigger) {
		var map = {
			'daily-limit': {
				title: "You've used today's free generations",
				sub: 'Pro includes 1,000 AI generations per month with no daily cap, so you can keep your library moving when you need to.',
				urgency: 'Your next 5 unlock in 8h.'
			},
			'monthly-limit': {
				title: "You've reached this month's free generations",
				sub: 'Pro gives you 1,000 AI generations per month with no daily cap inside that allowance.',
				urgency: 'Free credits reset soon.'
			},
			'default': {
				title: 'Never worry about missing ALT again',
				sub: '1,000 AI generations per month, Autopilot for new uploads, and no daily cap within your monthly allowance.',
				urgency: ''
			}
		};

		return map[trigger] || map.default;
	}

	function setText(node, value) {
		if (node) {
			node.textContent = String(value);
		}
	}

	function parseCount(text) {
		return Math.max(0, parseInt(String(text || '').replace(/[^0-9]/g, ''), 10) || 0);
	}

	function setQuotaUsed(root, usedSelector, limitSelector) {
		var limit = parseCount(root.querySelector(limitSelector) && root.querySelector(limitSelector).textContent);

		if (limit <= 0) {
			return;
		}

		Array.prototype.forEach.call(root.querySelectorAll(usedSelector), function (node) {
			setText(node, limit);
		});
	}

	function setStartPassLocked(root, locked) {
		Array.prototype.forEach.call(root.querySelectorAll('[data-bbai-nai-cta="start-pass"]'), function (button) {
			button.disabled = !!locked;
			button.setAttribute('aria-disabled', locked ? 'true' : 'false');
			button.classList.toggle('is-disabled', !!locked);
		});
	}

	function applyPrototypeLimitState(root, trigger) {
		if (trigger === 'daily-limit') {
			setQuotaUsed(root, '[data-bbai-entitlement-daily-used]', '[data-bbai-entitlement-daily-limit]');
			setStartPassLocked(root, true);
		}

		if (trigger === 'monthly-limit') {
			setQuotaUsed(root, '[data-bbai-entitlement-used]', '[data-bbai-entitlement-limit]');
			setStartPassLocked(root, true);
		}
	}

	function openPaywall(state, trigger) {
		var root = state.root;
		var modal = root.querySelector('[data-nai-modal="paywall"]');
		var copy = paywallCopy(trigger || 'default');
		var urgency;

		applyPrototypeLimitState(root, trigger || 'default');

		if (modal) {
			modal.querySelector('[data-nai-paywall-title]').textContent = copy.title;
			modal.querySelector('[data-nai-paywall-subtitle]').textContent = copy.sub;
			urgency = modal.querySelector('[data-nai-paywall-urgency]');
			if (copy.urgency) {
				urgency.hidden = false;
				urgency.textContent = copy.urgency;
			} else {
				urgency.hidden = true;
			}
		}
		modals.open(root, 'paywall');
	}

	window.BBAINaiQuota = {
		openPaywall: openPaywall
	};
}(window));
