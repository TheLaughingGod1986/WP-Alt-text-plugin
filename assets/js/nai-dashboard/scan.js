(function (window) {
	'use strict';

	var modals = window.BBAINaiModals || {};

	function renderOnboarding(state) {
		var root = state.root;
		var modal = root.querySelector('[data-nai-modal="onboarding"]');
		var labels = [
			'Step 1 of 3 · Welcome',
			'Step 2 of 3 · Scan library',
			'Step 3 of 3 · Ready'
		];
		var pct;
		var bar;
		var label;
		var out;

		if (!modal) {
			return;
		}

		modal.querySelector('[data-nai-onboarding-step]').textContent = labels[state.onboardingStep];
		modal.querySelectorAll('.nai-onboarding-panel').forEach(function (panel) {
			panel.classList.toggle('is-active', Number(panel.getAttribute('data-step')) === state.onboardingStep);
		});
		modal.querySelectorAll('.nai-steps span').forEach(function (step, idx) {
			step.classList.toggle('is-active', idx <= state.onboardingStep);
		});
		modal.querySelector('[data-nai-onboarding-back]').hidden = state.onboardingStep === 0;
		modal.querySelector('[data-nai-onboarding-next]').textContent = state.onboardingStep === 2 ? 'Open dashboard' : 'Continue';

		if (state.onboardingStep === 1) {
			pct = 0;
			bar = modal.querySelector('[data-nai-scan-bar]');
			label = modal.querySelector('[data-nai-scan-label]');
			out = modal.querySelector('[data-nai-scan-pct]');
			clearInterval(modal._scanTimer);
			modal._scanTimer = setInterval(function () {
				pct = Math.min(100, pct + 8);
				if (bar) {
					bar.style.width = pct + '%';
				}
				if (out) {
					out.textContent = pct + '%';
				}
				if (pct >= 100) {
					clearInterval(modal._scanTimer);
					if (label) {
						label.textContent = 'Scan complete';
					}
				}
			}, 80);
		}
	}

	function openOnboarding(state) {
		state.onboardingStep = 0;
		renderOnboarding(state);
		modals.open(state.root, 'onboarding');
	}

	window.BBAINaiScan = {
		renderOnboarding: renderOnboarding,
		openOnboarding: openOnboarding
	};
}(window));
