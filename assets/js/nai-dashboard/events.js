(function (window, document) {
	'use strict';

	var dom = window.BBAINaiDom || {};
	var modals = window.BBAINaiModals || {};
	var notices = window.BBAINaiNotices || {};
	var quota = window.BBAINaiQuota || {};
	var scan = window.BBAINaiScan || {};
	var generation = window.BBAINaiGeneration || {};

	function getGeneration() {
		return window.BBAINaiGeneration || generation || {};
	}

	function parseCount(text) {
		return Math.max(0, parseInt(String(text || '').replace(/[^0-9]/g, ''), 10) || 0);
	}

	function setTextNumber(node, value) {
		if (node) {
			node.textContent = String(Math.max(0, value));
		}
	}

	function setText(node, value) {
		if (node) {
			node.textContent = String(value);
		}
	}

	function formatImageText(count, singular, plural) {
		var safeCount = Math.max(0, parseInt(count, 10) || 0);
		return safeCount + ' ' + (safeCount === 1 ? singular : plural);
	}

	function incrementCounterNodes(screen, usedSelector, limitSelector, amount) {
		var limitNode = screen.querySelector(limitSelector);
		var limit = parseCount(limitNode && limitNode.textContent);

		Array.prototype.forEach.call(screen.querySelectorAll(usedSelector), function (node) {
			var next = parseCount(node.textContent) + amount;
			setTextNumber(node, limit > 0 ? Math.min(limit, next) : next);
		});
	}

	function setBarWidth(node, value) {
		if (node) {
			node.style.width = Math.max(0, Math.min(100, value)) + '%';
		}
	}

	function getMissingResolved(root, successes) {
		var drawer = root ? root.querySelector('[data-nai-drawer]') : null;
		var items = drawer && drawer._naiState && drawer._naiState.queueItems ? drawer._naiState.queueItems : [];
		var count = Math.max(0, parseInt(successes, 10) || 0);
		var resolved = 0;

		Array.prototype.slice.call(items, 0, count).forEach(function (item) {
			if (String(item.page || '').toLowerCase().indexOf('missing') !== -1) {
				resolved++;
			}
		});

		if (resolved > 0 || !drawer) {
			return resolved;
		}

		Array.prototype.slice.call(drawer.querySelectorAll('[data-nai-gen-row].is-complete'), 0, count).forEach(function (row) {
			var signal = row.querySelector('small');
			if (signal && signal.textContent.toLowerCase().indexOf('missing') !== -1) {
				resolved++;
			}
		});

		return resolved;
	}

	function updateLatestActivity(screen, remainingPass, optimized, coverage, missingResolved) {
		var passText = screen.querySelector('[data-nai-activity-pass-text]');
		var optimizedText = screen.querySelector('[data-nai-activity-optimized-text]');
		var missingText = screen.querySelector('[data-nai-activity-missing-text]');
		var missingRow = screen.querySelector('[data-nai-activity-missing-row]');
		var missingNext;

		setText(passText, formatImageText(remainingPass, 'new upload detected', 'new uploads detected') + " for today's pass");
		setText(optimizedText, optimized + ' images generated or improved · coverage at ' + coverage + '%');

		if (missingText && missingResolved > 0) {
			missingNext = Math.max(0, parseCount(missingText.textContent) - missingResolved);
			setText(missingText, formatImageText(missingNext, 'image left', 'images left') + ' without ALT text');
			if (missingRow) {
				missingRow.hidden = missingNext <= 0;
			}
		}
	}

	function updateCoverageCard(screen, optimized, remaining, coverage) {
		var stats = screen.querySelectorAll('.nai-coverage__stats-strong');

		setText(screen.querySelector('[data-nai-coverage-number]') || screen.querySelector('.nai-coverage__num'), coverage + '%');
		setTextNumber(screen.querySelector('[data-nai-coverage-optimized]') || stats[0], optimized);
		setTextNumber(screen.querySelector('[data-nai-coverage-remaining]') || stats[1], remaining);
		setBarWidth(screen.querySelector('[data-nai-coverage-progress]') || screen.querySelector('.nai-coverage .nai-progress__bar'), coverage);
	}

	function updateDashboardAfterGeneration(root, detail) {
		var screen = root ? root.querySelector('[data-nai-screen="dashboard"]') : null;
		var successes = Math.max(0, parseInt(detail && detail.successes, 10) || 0);
		var queue;
		var titleNumber;
		var total;
		var optimized;
		var coverage;
		var remainingPass;
		var remainingTotal;
		var missingResolved;
		var ringFill;
		var ring;
		var workSaved;

		if (!screen || successes <= 0) {
			return;
		}

		titleNumber = screen.querySelector('.nai-hero__title .nai-tnum');
		if (titleNumber) {
			remainingPass = Math.max(0, parseCount(titleNumber.textContent) - successes);
			setTextNumber(titleNumber, remainingPass);
		}

		queue = screen.querySelector('.nai-hero__queue');
		if (queue) {
			Array.prototype.slice.call(queue.querySelectorAll('.nai-hero__queue-item'), 0, successes).forEach(function (item) {
				item.remove();
			});
			queue.style.gridTemplateColumns = 'repeat(' + Math.max(1, queue.querySelectorAll('.nai-hero__queue-item').length) + ', minmax(0, 1fr))';
		}

		incrementCounterNodes(screen, '[data-bbai-entitlement-daily-used]', '[data-bbai-entitlement-daily-limit]', successes);
		incrementCounterNodes(screen, '[data-bbai-entitlement-used]', '[data-bbai-entitlement-limit]', successes);

		total = Math.max(0, parseInt(screen.getAttribute('data-nai-total-images') || '', 10) || 0);
		optimized = Math.max(0, parseInt(screen.getAttribute('data-nai-optimized-images') || '', 10) || 0) + successes;
		screen.setAttribute('data-nai-optimized-images', String(optimized));
		if (total > 0) {
			coverage = Math.max(0, Math.min(100, Math.round((optimized / total) * 100)));
			remainingTotal = Math.max(0, total - optimized);
			screen.setAttribute('data-nai-coverage', String(coverage));
			Array.prototype.forEach.call(screen.querySelectorAll('.nai-ring__center .nai-tnum, .nai-health__num'), function (node) {
				setTextNumber(node, coverage);
			});
			updateCoverageCard(screen, optimized, remainingTotal, coverage);
			missingResolved = detail && detail.missingResolved !== undefined ? Math.max(0, parseInt(detail.missingResolved, 10) || 0) : getMissingResolved(root, successes);
			updateLatestActivity(screen, remainingPass !== undefined ? remainingPass : Math.max(0, parseCount(titleNumber && titleNumber.textContent)), optimized, coverage, missingResolved);
			ringFill = screen.querySelector('.nai-ring__fill');
			ring = ringFill && ringFill.getAttribute('r') ? parseFloat(ringFill.getAttribute('r')) : 0;
			if (ringFill && ring > 0) {
				ringFill.setAttribute('stroke-dasharray', ((coverage / 100) * 2 * Math.PI * ring) + ' ' + (2 * Math.PI * ring));
			}
		}

		workSaved = screen.querySelector('.nai-health__work .nai-health__work-strong:last-of-type');
		if (workSaved) {
			setTextNumber(workSaved, parseCount(workSaved.textContent) + successes);
		}
	}

	function getDrawerCompletionDetail(root) {
		var drawer = root ? root.querySelector('[data-nai-drawer]') : null;
		var successes;

		if (!drawer) {
			return { successes: 0 };
		}

		successes = Math.max(0, parseInt(drawer._naiDashboardSuccesses, 10) || 0);
		if (!successes) {
			successes = drawer.querySelectorAll('[data-nai-gen-row].is-complete').length;
		}
		if (!successes && drawer.classList.contains('is-complete')) {
			successes = Math.max(0, parseInt(drawer._naiTotal, 10) || 0);
		}

		return { successes: successes, missingResolved: getMissingResolved(root, successes) };
	}

	function applyDrawerDashboardUpdate(root, detail) {
		var drawer = root ? root.querySelector('[data-nai-drawer]') : null;

		if (!drawer || drawer.getAttribute('data-nai-dashboard-applied') === '1') {
			return;
		}

		updateDashboardAfterGeneration(root, detail || getDrawerCompletionDetail(root));
		drawer.setAttribute('data-nai-dashboard-applied', '1');
	}

	function bindRootEvents(state) {
		var root = state.root;
		var showCompletionToast = function () {
			var title = root.querySelector('[data-nai-drawer-title]');
			notices.toast(root, title && title.textContent ? title.textContent : 'Images improved', 'Your site is now more accessible.');
		};

		root.addEventListener('click', function (event) {
			var target = event.target;
			var userToggle = target.closest('[data-nai-user-toggle]');
			var paywallBtn;

			if (userToggle) {
				event.preventDefault();
				var panel = root.querySelector('[data-nai-user-panel]');
				panel.hidden = !panel.hidden;
				userToggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
				return;
			}

			if (target.closest('[data-nai-open-drawer]')) {
				var drawerTrigger = target.closest('[data-nai-open-drawer]');
				var isRealGenerationTrigger = !!(
					drawerTrigger &&
					(
						drawerTrigger.getAttribute('data-action') === 'generate-missing' ||
						drawerTrigger.getAttribute('data-bbai-action') === 'generate_missing'
					)
				);
				window.BBAINaiUseDrawerProgress = true;
				if (typeof getGeneration().openDrawer === 'function') {
					getGeneration().openDrawer(state, { simulate: state.prototypeMode && !isRealGenerationTrigger });
				}
				if (state.prototypeMode && !isRealGenerationTrigger) {
					event.preventDefault();
				}
				return;
			}

			if (target.closest('[data-nai-close-drawer]')) {
				event.preventDefault();
				dom.hide(root.querySelector('[data-nai-drawer]'));
				return;
			}

			if (target.closest('[data-nai-complete-drawer]')) {
				event.preventDefault();
				applyDrawerDashboardUpdate(root, getDrawerCompletionDetail(root));
				dom.hide(root.querySelector('[data-nai-drawer]'));
				showCompletionToast();
				return;
			}

			paywallBtn = target.closest('[data-nai-open-paywall]');
			if (paywallBtn) {
				event.preventDefault();
				event.stopPropagation();
				quota.openPaywall(state, paywallBtn.getAttribute('data-nai-open-paywall'));
				return;
			}

			if (!state.prototypeMode) {
				return;
			}

			if (target.closest('[data-nai-open-onboarding]')) {
				event.preventDefault();
				scan.openOnboarding(state);
				return;
			}

			if (target.closest('[data-nai-open-signout]')) {
				event.preventDefault();
				modals.open(root, 'signout');
				return;
			}

			if (target.closest('[data-nai-confirm-signout]')) {
				event.preventDefault();
				modals.closeAll(root);
				root.querySelector('[data-nai-signedout]').hidden = false;
				if (root.querySelector('[data-nai-screen="dashboard"]')) {
					root.querySelector('[data-nai-screen="dashboard"]').hidden = true;
				}
				notices.toast(root, 'Signed out', 'Autopilot is paused until you sign back in.');
				return;
			}

			if (target.closest('[data-nai-paywall-cta]')) {
				var paywallCta = target.closest('[data-nai-paywall-cta]');
				var checkoutHref = paywallCta.getAttribute('href');
				if (checkoutHref) {
					// Real Stripe checkout via the server-side direct-checkout handler.
					event.preventDefault();
					notices.toast(root, 'Opening secure checkout…', 'Redirecting you to Stripe.');
					window.location.assign(checkoutHref);
				}
				// No href configured — let the link/default action proceed.
				return;
			}

			if (target.closest('[data-nai-signin]')) {
				event.preventDefault();
				root.querySelector('[data-nai-signedout]').hidden = true;
				if (root.querySelector('[data-nai-screen="dashboard"]')) {
					root.querySelector('[data-nai-screen="dashboard"]').hidden = false;
				}
				notices.toast(root, 'Welcome back', 'Autopilot is resuming...');
				return;
			}

			if (target.closest('[data-nai-close-modal]')) {
				event.preventDefault();
				modals.closeAll(root);
				return;
			}

			if (target.closest('[data-nai-onboarding-back]')) {
				event.preventDefault();
				state.onboardingStep = Math.max(0, state.onboardingStep - 1);
				scan.renderOnboarding(state);
				return;
			}

			if (target.closest('[data-nai-onboarding-next]')) {
				event.preventDefault();
				if (state.onboardingStep >= 2) {
					modals.closeAll(root);
					notices.toast(root, 'Welcome to BeepBeep AI', 'Your first daily optimisations are ready.');
				} else {
					state.onboardingStep++;
					scan.renderOnboarding(state);
				}
				return;
			}

			if (target.closest('[data-nai-gen-regen]')) {
				event.preventDefault();
				target.closest('.nai-gen-row').querySelector('p').textContent = 'Rewritten ALT text with clearer object detail and better screen-reader context.';
				notices.toast(root, 'ALT regenerated', '');
				return;
			}

			if (target.closest('[data-nai-gen-edit]')) {
				event.preventDefault();
				target.closest('.nai-gen-row').querySelector('p').focus();
			}
		});
	}

	function bindDocumentEvents(state) {
		if (window.BBAINaiDashboardDocumentEventsBound) {
			return;
		}
		window.BBAINaiDashboardDocumentEventsBound = true;
		document.addEventListener('keydown', function (event) {
			var root = document.querySelector('.nai-app');
			if (event.key === 'Escape') {
				if (!root) {
					return;
				}
				dom.hide(root.querySelector('[data-nai-drawer]'));
				modals.closeAll(root);
			}
		});
		document.addEventListener('bbai:generation:finished', function (event) {
			var root = document.querySelector('.nai-app');
			var currentGeneration = getGeneration();
			var title;
			if (!window.BBAINaiUseDrawerProgress || !currentGeneration.completeLegacyProgress) {
				return;
			}
			currentGeneration.completeLegacyProgress(event.detail || {});
			if (root) {
				applyDrawerDashboardUpdate(root, event.detail || {});
			}
			if (root) {
				title = root.querySelector('[data-nai-drawer-title]');
				notices.toast(root, title && title.textContent ? title.textContent : 'Images improved', 'Your site is now more accessible.');
			}
		});
		document.addEventListener('bbai:nai-drawer:complete', function (event) {
			var root = document.querySelector('.nai-app');
			if (root) {
				applyDrawerDashboardUpdate(root, event.detail || {});
			}
		});
	}

	function bindHero(state) {
		var root = state.root;
		var hero = root.querySelector('.nai-hero--interactive');
		var heroHref;
		var activateHero;

		if (!hero) {
			return;
		}

		heroHref = hero.getAttribute('data-href') || '';
		activateHero = function () {
			var primaryCta = hero.querySelector('[data-bbai-nai-cta="start-pass"]');
			if (state.prototypeMode) {
				if (typeof getGeneration().openDrawer === 'function') {
					getGeneration().openDrawer(state);
				}
				return;
			}
			if (primaryCta && typeof primaryCta.click === 'function') {
				primaryCta.click();
				return;
			}
			if (heroHref) {
				window.location.href = heroHref;
			}
		};
		hero.addEventListener('click', function (event) {
			if (event.target.closest('a, button')) {
				return;
			}
			activateHero();
		});
		hero.addEventListener('keydown', function (event) {
			if (event.target.closest('a, button')) {
				return;
			}
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				activateHero();
			}
		});
	}

	function runDemoTrigger(state) {
		if (!state.prototypeMode) {
			return;
		}
		if (state.demoTrigger === 'daily-limit' || state.demoTrigger === 'monthly-limit' || state.demoTrigger === 'default-paywall') {
			quota.openPaywall(state, state.demoTrigger === 'default-paywall' ? 'default' : state.demoTrigger);
		} else if (state.demoTrigger === 'onboarding') {
			scan.openOnboarding(state);
		} else if (state.demoTrigger === 'drawer') {
			if (typeof getGeneration().openDrawer === 'function') {
				getGeneration().openDrawer(state);
			}
		} else if (state.demoTrigger === 'signout') {
			modals.open(state.root, 'signout');
		}
	}

	function bind(state) {
		bindRootEvents(state);
		bindDocumentEvents(state);
		bindHero(state);
		runDemoTrigger(state);
	}

	window.BBAINaiDashboardEvents = {
		bind: bind
	};
}(window, document));
