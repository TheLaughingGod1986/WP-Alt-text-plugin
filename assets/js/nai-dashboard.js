(function () {
	'use strict';

	var root = document.querySelector('.nai-app');
	if (!root) {
		return;
	}

	var prototypeMode = root.getAttribute('data-nai-prototype') === '1';
	var isPro = root.getAttribute('data-nai-is-pro') === '1';
	var demoTrigger = root.getAttribute('data-nai-demo-trigger') || '';
	var queueItems = [];

	try {
		var itemsNode = root.querySelector('[data-nai-drawer-items]');
		queueItems = itemsNode ? JSON.parse(itemsNode.getAttribute('data-nai-drawer-items-json') || itemsNode.textContent || '[]') : [];
	} catch (err) {
		queueItems = [];
	}

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

	function openModal(name) {
		show(root.querySelector('[data-nai-modal="' + name + '"]'));
	}

	function closeModals() {
		root.querySelectorAll('[data-nai-modal]').forEach(hide);
	}

	function toast(message, sub, tone) {
		var el = root.querySelector('[data-nai-toast]');
		if (!el) {
			return;
		}

		el.className = 'nai-toast' + (tone ? ' nai-toast--' + tone : '');
		el.textContent = '';

		var title = document.createElement('strong');
		title.textContent = message;
		el.appendChild(title);

		if (sub) {
			var body = document.createElement('span');
			body.textContent = sub;
			el.appendChild(body);
		}

		el.hidden = false;
		clearTimeout(el._timer);
		el._timer = setTimeout(function () {
			el.hidden = true;
		}, 4200);
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

	function getMissingResolved(successes) {
		var drawer = root.querySelector('[data-nai-drawer]');
		var count = Math.max(0, parseInt(successes, 10) || 0);
		var resolved = 0;

		Array.prototype.slice.call(queueItems, 0, count).forEach(function (item) {
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

	function updateDashboardAfterGeneration(detail) {
		var screen = root.querySelector('[data-nai-screen="dashboard"]');
		var successes = Math.max(0, parseInt(detail && detail.successes, 10) || 0);
		var titleNumber;
		var queue;
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
			missingResolved = detail && detail.missingResolved !== undefined ? Math.max(0, parseInt(detail.missingResolved, 10) || 0) : getMissingResolved(successes);
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

	function getDrawerCompletionDetail() {
		var drawer = root.querySelector('[data-nai-drawer]');
		var successes;

		if (!drawer) {
			return { successes: 0 };
		}

		successes = Math.max(0, parseInt(drawer._naiDashboardSuccesses, 10) || 0);
		if (!successes) {
			successes = drawer.querySelectorAll('[data-nai-gen-row]').length;
		}

		return { successes: successes, missingResolved: getMissingResolved(successes) };
	}

	function applyDrawerDashboardUpdate(detail) {
		var drawer = root.querySelector('[data-nai-drawer]');

		if (!drawer || drawer.getAttribute('data-nai-dashboard-applied') === '1') {
			return;
		}

		updateDashboardAfterGeneration(detail || getDrawerCompletionDetail());
		drawer.setAttribute('data-nai-dashboard-applied', '1');
	}

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

	function setQuotaUsed(usedSelector, limitSelector) {
		var limit = parseCount(root.querySelector(limitSelector) && root.querySelector(limitSelector).textContent);

		if (limit <= 0) {
			return;
		}

		Array.prototype.forEach.call(root.querySelectorAll(usedSelector), function (node) {
			setTextNumber(node, limit);
		});
	}

	function setStartPassLocked(locked) {
		Array.prototype.forEach.call(root.querySelectorAll('[data-bbai-nai-cta="start-pass"]'), function (button) {
			button.disabled = !!locked;
			button.setAttribute('aria-disabled', locked ? 'true' : 'false');
			button.classList.toggle('is-disabled', !!locked);
		});
	}

	function applyPrototypeLimitState(trigger) {
		if (trigger === 'daily-limit') {
			setQuotaUsed('[data-bbai-entitlement-daily-used]', '[data-bbai-entitlement-daily-limit]');
			setStartPassLocked(true);
		}

		if (trigger === 'monthly-limit') {
			setQuotaUsed('[data-bbai-entitlement-used]', '[data-bbai-entitlement-limit]');
			setStartPassLocked(true);
		}
	}

	function openPaywall(trigger) {
		var modal = root.querySelector('[data-nai-modal="paywall"]');
		var copy = paywallCopy(trigger || 'default');
		applyPrototypeLimitState(trigger || 'default');
		if (modal) {
			modal.querySelector('[data-nai-paywall-title]').textContent = copy.title;
			modal.querySelector('[data-nai-paywall-subtitle]').textContent = copy.sub;
			var urgency = modal.querySelector('[data-nai-paywall-urgency]');
			if (copy.urgency) {
				urgency.hidden = false;
				urgency.textContent = copy.urgency;
			} else {
				urgency.hidden = true;
			}
		}
		openModal('paywall');
	}

	var onboardingStep = 0;

	function renderOnboarding() {
		var modal = root.querySelector('[data-nai-modal="onboarding"]');
		if (!modal) {
			return;
		}

		var labels = [
			'Step 1 of 3 · Welcome',
			'Step 2 of 3 · Scan library',
			'Step 3 of 3 · Ready'
		];

		modal.querySelector('[data-nai-onboarding-step]').textContent = labels[onboardingStep];
		modal.querySelectorAll('.nai-onboarding-panel').forEach(function (panel) {
			panel.classList.toggle('is-active', Number(panel.getAttribute('data-step')) === onboardingStep);
		});
		modal.querySelectorAll('.nai-steps span').forEach(function (step, idx) {
			step.classList.toggle('is-active', idx <= onboardingStep);
		});
		modal.querySelector('[data-nai-onboarding-back]').hidden = onboardingStep === 0;
		modal.querySelector('[data-nai-onboarding-next]').textContent = onboardingStep === 2 ? 'Open dashboard' : 'Continue';

		if (onboardingStep === 1) {
			var pct = 0;
			var bar = modal.querySelector('[data-nai-scan-bar]');
			var label = modal.querySelector('[data-nai-scan-label]');
			var out = modal.querySelector('[data-nai-scan-pct]');
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

	function openOnboarding() {
		onboardingStep = 0;
		renderOnboarding();
		openModal('onboarding');
	}

	function createThumb(item, idx) {
		var thumb = item.thumb_url ? document.createElement('img') : document.createElement('div');
		var hue = Number(item.hue || 30);
		thumb.className = 'nai-thumb';
		if (item.thumb_url) {
			thumb.src = item.thumb_url;
			thumb.alt = '';
			thumb.loading = 'lazy';
			thumb.decoding = 'async';
			return thumb;
		}
		thumb.style.backgroundColor = 'oklch(0.93 0.02 ' + hue + ')';
		thumb.style.backgroundImage = 'repeating-linear-gradient(45deg, oklch(0.88 0.025 ' + hue + ') 0, oklch(0.88 0.025 ' + hue + ') 6px, oklch(0.93 0.02 ' + hue + ') 6px, oklch(0.93 0.02 ' + hue + ') 12px)';
		thumb.textContent = '#' + (idx + 1);
		return thumb;
	}

	function openDrawer() {
		var drawer = root.querySelector('[data-nai-drawer]');
		var stream = root.querySelector('[data-nai-drawer-stream]');
		var title = root.querySelector('[data-nai-drawer-title]');
		var eyebrow = root.querySelector('[data-nai-drawer-eyebrow]');
		var progress = root.querySelector('[data-nai-drawer-progress]');
		var status = root.querySelector('[data-nai-drawer-status]');
		var done = root.querySelector('[data-nai-complete-drawer]');
		if (!drawer || !stream || !title || !progress || !status || !done) {
			return;
		}

		var items = queueItems.length ? queueItems : [{ name: 'hero-spring-collection.jpg', page: 'Homepage', hue: 30 }];
		var idx = 0;

		stream.textContent = '';
		progress.style.width = '0%';
		done.hidden = true;
		eyebrow.textContent = isPro ? 'Optimisation' : "Today's pass";
		title.textContent = isPro ? 'Optimising latest uploads...' : "Working through today's images...";
		status.textContent = isPro ? 'Autopilot active · improving images...' : "Working through today's images...";
		drawer.classList.remove('is-complete');
		drawer._naiDashboardSuccesses = 0;
		drawer.removeAttribute('data-nai-dashboard-applied');
		show(drawer);
		clearInterval(drawer._timer);
		drawer._timer = setInterval(function () {
			var item = items[idx];
			var row = document.createElement('div');
			var body = document.createElement('div');
			var name = document.createElement('strong');
			var page = document.createElement('small');
			var text = document.createElement('p');
			var actions = document.createElement('div');
			var edit = document.createElement('button');
			var regenerate = document.createElement('button');

			row.className = 'nai-gen-row is-complete';
			row.setAttribute('data-nai-gen-row', '');
			name.textContent = item.name;
			page.textContent = item.page;
			text.contentEditable = 'true';
			text.textContent = 'ALT text generated and saved. Open the Library to review the final wording.';
			actions.className = 'nai-gen-row__actions';
			edit.type = 'button';
			edit.setAttribute('data-nai-gen-edit', '');
			edit.textContent = 'Edit';
			regenerate.type = 'button';
			regenerate.setAttribute('data-nai-gen-regen', '');
			regenerate.textContent = 'Regenerate';

			actions.append(edit, regenerate);
			body.append(name, page, text, actions);
			row.append(createThumb(item, idx), body);
			stream.appendChild(row);

			idx++;
			progress.style.width = Math.round((idx / items.length) * 100) + '%';
			title.textContent = isPro ? idx + ' images improved' : idx + ' of ' + items.length + ' complete';
			status.textContent = idx >= items.length
				? (isPro ? 'Optimisation complete · Autopilot active.' : 'Your site is now more accessible.')
				: (items.length - idx) + ' images to go';

			if (idx >= items.length) {
				clearInterval(drawer._timer);
				drawer.classList.add('is-complete');
				drawer._naiDashboardSuccesses = idx;
				done.hidden = false;
				applyDrawerDashboardUpdate({ successes: idx });
			}
		}, 850);
	}

	root.addEventListener('click', function (event) {
		var target = event.target;
		var userToggle = target.closest('[data-nai-user-toggle]');

		if (userToggle) {
			event.preventDefault();
			var panel = root.querySelector('[data-nai-user-panel]');
			panel.hidden = !panel.hidden;
			userToggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
			return;
		}

		var paywallBtn = target.closest('[data-nai-open-paywall]');
		if (paywallBtn) {
			event.preventDefault();
			event.stopPropagation();
			openPaywall(paywallBtn.getAttribute('data-nai-open-paywall'));
			return;
		}

		if (!prototypeMode) {
			return;
		}

		if (target.closest('[data-nai-open-onboarding]')) {
			event.preventDefault();
			openOnboarding();
			return;
		}

		if (target.closest('[data-nai-open-signout]')) {
			event.preventDefault();
			openModal('signout');
			return;
		}

		if (target.closest('[data-nai-confirm-signout]')) {
			event.preventDefault();
			closeModals();
			root.querySelector('[data-nai-signedout]').hidden = false;
			if (root.querySelector('[data-nai-screen="dashboard"]')) {
				root.querySelector('[data-nai-screen="dashboard"]').hidden = true;
			}
			toast('Signed out', 'Autopilot is paused until you sign back in.');
			return;
		}

		if (target.closest('[data-nai-signin]')) {
			event.preventDefault();
			root.querySelector('[data-nai-signedout]').hidden = true;
			if (root.querySelector('[data-nai-screen="dashboard"]')) {
				root.querySelector('[data-nai-screen="dashboard"]').hidden = false;
			}
			toast('Welcome back', 'Autopilot is resuming...');
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
			if (!isRealGenerationTrigger) {
				event.preventDefault();
			}
			openDrawer();
			return;
		}

		if (target.closest('[data-nai-close-drawer]')) {
			event.preventDefault();
			hide(root.querySelector('[data-nai-drawer]'));
			return;
		}

		if (target.closest('[data-nai-complete-drawer]')) {
			event.preventDefault();
			applyDrawerDashboardUpdate();
			hide(root.querySelector('[data-nai-drawer]'));
			toast('Images improved', 'Your site is now more accessible.');
			return;
		}

		if (target.closest('[data-nai-close-modal]')) {
			event.preventDefault();
			closeModals();
			return;
		}

		if (target.closest('[data-nai-onboarding-back]')) {
			event.preventDefault();
			onboardingStep = Math.max(0, onboardingStep - 1);
			renderOnboarding();
			return;
		}

		if (target.closest('[data-nai-onboarding-next]')) {
			event.preventDefault();
			if (onboardingStep >= 2) {
				closeModals();
				toast('Welcome to BeepBeep AI', 'Your first daily optimisations are ready.');
			} else {
				onboardingStep++;
				renderOnboarding();
			}
			return;
		}

		if (target.closest('[data-nai-gen-regen]')) {
			event.preventDefault();
			target.closest('.nai-gen-row').querySelector('p').textContent = 'Rewritten ALT text with clearer object detail and better screen-reader context.';
			toast('ALT regenerated', '');
			return;
		}

		if (target.closest('[data-nai-gen-edit]')) {
			event.preventDefault();
			target.closest('.nai-gen-row').querySelector('p').focus();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			hide(root.querySelector('[data-nai-drawer]'));
			closeModals();
		}
	});

	var hero = root.querySelector('.nai-hero--interactive');
	if (hero) {
		var heroHref = hero.getAttribute('data-href') || '';
		var activateHero = function () {
			if (prototypeMode) {
				openDrawer();
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
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				activateHero();
			}
		});
	}

	if (prototypeMode) {
		if (demoTrigger === 'daily-limit' || demoTrigger === 'monthly-limit' || demoTrigger === 'default-paywall') {
			openPaywall(demoTrigger === 'default-paywall' ? 'default' : demoTrigger);
		} else if (demoTrigger === 'onboarding') {
			openOnboarding();
		} else if (demoTrigger === 'drawer') {
			openDrawer();
		} else if (demoTrigger === 'signout') {
			openModal('signout');
		}
	}
}());
