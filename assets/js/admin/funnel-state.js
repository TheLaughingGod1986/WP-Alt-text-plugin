/**
 * Dashboard hero, credits block, and status-pill sync.
 */
(function () {
    'use strict';

    var HERO_STATES = {
        NOT_SCANNED: 'not_scanned',
        SCANNING: 'scanning',
        SCANNED_HAS_ISSUES: 'scanned_has_issues',
        SCANNED_CLEAN: 'scanned_clean'
    };
    var ACTIONABLE_STATES = {
        MISSING: 'missing',
        REVIEW: 'review',
        COMPLETE: 'complete'
    };
    var CREDIT_PROMPT_WINDOW_MS = 12000;
    var CREDIT_LOW_THRESHOLD = 2;

    function getRoot() {
        return document.querySelector('[data-bbai-dashboard-root="1"]');
    }

    function getHero() {
        return document.querySelector('[data-bbai-funnel-hero]');
    }

    function getStatusRow() {
        return document.querySelector('[data-bbai-dashboard-status-nav]');
    }

    function getTrialPreview() {
        return document.querySelector('[data-bbai-trial-preview="1"]:not([hidden])');
    }

    function getTrialPreviewNode() {
        return document.querySelector('[data-bbai-trial-preview="1"]');
    }

    function getLockedTrialPreview() {
        return document.querySelector('[data-bbai-trial-locked-preview="1"]');
    }

    function getProofSection() {
        return document.querySelector('[data-bbai-dashboard-proof-section], [data-bbai-dashboard-proof-card]');
    }

    function parseCount(value) {
        return Math.max(0, parseInt(value, 10) || 0);
    }

    function formatCount(value) {
        try {
            return Number(value || 0).toLocaleString();
        } catch (error) {
            return String(parseCount(value));
        }
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function parseDonutMetric(value) {
        var text = String(value || '').trim();
        var match = text.match(/^([0-9][0-9,]*)(.*)$/);

        if (!match) {
            return null;
        }

        return {
            number: Math.max(0, parseInt(String(match[1]).replace(/,/g, ''), 10) || 0),
            suffix: String(match[2] || '')
        };
    }

    function setAnimatedDonutValue(node, nextValue) {
        var targetText = String(nextValue || '');
        var previousText;
        var previousMetric;
        var nextMetric;
        var startValue;
        var delta;
        var startedAt = 0;
        var duration = 320;

        if (!node) {
            return;
        }

        previousText = String(node.getAttribute('data-bbai-donut-value-raw') || node.textContent || '');
        if (previousText === targetText) {
            return;
        }

        node.setAttribute('data-bbai-donut-value-raw', targetText);
        previousMetric = parseDonutMetric(previousText);
        nextMetric = parseDonutMetric(targetText);

        if (node._bbaiDonutValueRaf) {
            window.cancelAnimationFrame(node._bbaiDonutValueRaf);
            node._bbaiDonutValueRaf = 0;
        }

        if (prefersReducedMotion() || typeof window.requestAnimationFrame !== 'function' || !previousMetric || !nextMetric || previousMetric.suffix !== nextMetric.suffix) {
            node.textContent = targetText;
            return;
        }

        startValue = previousMetric.number;
        delta = nextMetric.number - startValue;

        if (!delta) {
            node.textContent = targetText;
            return;
        }

        function step(timestamp) {
            var progress;
            var eased;
            var currentValue;

            if (!startedAt) {
                startedAt = timestamp;
            }

            progress = Math.min(1, (timestamp - startedAt) / duration);
            eased = 1 - Math.pow(1 - progress, 3);
            currentValue = Math.round(startValue + (delta * eased));
            node.textContent = formatCount(currentValue) + nextMetric.suffix;

            if (progress < 1) {
                node._bbaiDonutValueRaf = window.requestAnimationFrame(step);
                return;
            }

            node.textContent = targetText;
            node._bbaiDonutValueRaf = 0;
        }

        node._bbaiDonutValueRaf = window.requestAnimationFrame(step);
    }

    function triggerCompleteDonutPulse(donut) {
        if (!donut || prefersReducedMotion()) {
            return;
        }

        donut.classList.remove('bbai-dashboard-hero-action__donut--complete-pulse');
        window.requestAnimationFrame(function () {
            donut.classList.add('bbai-dashboard-hero-action__donut--complete-pulse');
        });
    }

    function nowMs() {
        return Date.now ? Date.now() : (new Date()).getTime();
    }

    function setHidden(node, hidden) {
        if (!node) {
            return;
        }

        if (hidden) {
            node.setAttribute('hidden', '');
        } else {
            node.removeAttribute('hidden');
        }
    }

    function getCounts(root) {
        return {
            missing: parseCount(root.getAttribute('data-bbai-missing-count')),
            weak: parseCount(root.getAttribute('data-bbai-weak-count')),
            optimized: parseCount(root.getAttribute('data-bbai-optimized-count')),
            total: parseCount(root.getAttribute('data-bbai-total-count'))
        };
    }

    function getActionableState(root, counts) {
        var nextCounts = counts || getCounts(root);
        var missing = Math.max(0, parseCount(nextCounts.missing));
        var review = Math.max(0, parseCount(nextCounts.weak));
        var optimized = Math.max(0, parseCount(nextCounts.optimized));
        var total = Math.max(0, parseCount(nextCounts.total));
        var percentage = total > 0 ? Math.max(0, Math.round((optimized / total) * 100)) : 0;
        var state = ACTIONABLE_STATES.COMPLETE;
        var actionableCount = missing + review;

        if (missing > 0) {
            state = ACTIONABLE_STATES.MISSING;
        } else if (review > 0) {
            state = ACTIONABLE_STATES.REVIEW;
        }

        if (root) {
            root.setAttribute('data-bbai-actionable-state', state);
            root.setAttribute('data-bbai-actionable-count', String(actionableCount));
        }

        return {
            key: state,
            actionableCount: actionableCount,
            missingCount: missing,
            reviewCount: review,
            optimizedCount: optimized,
            totalCount: total,
            percentage: percentage
        };
    }

    function getTrialCreditsRemaining(root) {
        if (!root) {
            return 0;
        }

        return parseCount(
            root.getAttribute('data-bbai-trial-remaining') ||
            root.getAttribute('data-bbai-credits-remaining')
        );
    }

    function isPremiumUser(root) {
        return !!root && root.getAttribute('data-bbai-is-premium') === '1';
    }

    function canAccessLibrary(root) {
        return !!root && root.getAttribute('data-bbai-has-connected-account') === '1';
    }

    function isGuestTrialUser(root) {
        return !!root &&
            root.getAttribute('data-bbai-is-guest-trial') === '1' &&
            root.getAttribute('data-bbai-has-connected-account') !== '1';
    }

    function hasRecentSuccess(root) {
        var successTs;

        if (!root) {
            return false;
        }

        successTs = parseInt(root.getAttribute('data-bbai-last-success-ts') || '0', 10) || 0;
        return successTs > 0 && (nowMs() - successTs) < CREDIT_PROMPT_WINDOW_MS;
    }

    function getCreditFunnelState(root) {
        var remaining = parseCount(root && root.getAttribute('data-bbai-credits-remaining'));
        var used = parseCount(root && root.getAttribute('data-bbai-credits-used'));
        var limit = Math.max(1, parseCount(root && root.getAttribute('data-bbai-credits-total')) || 1);
        var isGuest = isGuestTrialUser(root);
        var isPremium = isPremiumUser(root);
        var freePlanOffer = parseCount(root && root.getAttribute('data-bbai-free-account-monthly-limit')) || 50;
        var exhausted = !isPremium && remaining <= 0;
        var low = !isPremium && remaining > 0 && remaining <= CREDIT_LOW_THRESHOLD;

        return {
            remaining: remaining,
            used: used,
            limit: limit,
            isGuestTrial: isGuest,
            isPremium: isPremium,
            isExhausted: exhausted,
            isLow: low,
            freePlanOffer: freePlanOffer,
            hasRecentSuccess: hasRecentSuccess(root)
        };
    }

    function hasScanData(root, counts) {
        return root.getAttribute('data-bbai-has-scan-results') === '1'
            || root.getAttribute('data-bbai-has-scan-history') === '1'
            || parseCount(root.getAttribute('data-bbai-last-scan-ts')) > 0
            || counts.total > 0
            || counts.missing > 0
            || counts.weak > 0
            || counts.optimized > 0;
    }

    function setScanInProgress(root, enabled) {
        if (root) {
            root.setAttribute('data-bbai-scan-in-progress', enabled ? '1' : '0');
        }
    }

    function resolveHeroState(root) {
        var counts;

        if (!root) {
            return HERO_STATES.NOT_SCANNED;
        }

        counts = getCounts(root);

        if (root.getAttribute('data-bbai-scan-in-progress') === '1') {
            return HERO_STATES.SCANNING;
        }

        if (!hasScanData(root, counts)) {
            return HERO_STATES.NOT_SCANNED;
        }

        if (counts.missing > 0 || counts.weak > 0) {
            return HERO_STATES.SCANNED_HAS_ISSUES;
        }

        return HERO_STATES.SCANNED_CLEAN;
    }

    function buildDonutGradient(counts) {
        if (!counts.total) {
            return 'conic-gradient(#d7dee8 0deg 360deg)';
        }

        var optimizedEnd = 360 * counts.optimized / counts.total;
        var weakEnd = optimizedEnd + (360 * counts.weak / counts.total);
        var missingEnd = weakEnd + (360 * counts.missing / counts.total);

        return 'conic-gradient(' +
            '#22c55e 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
            '#f59e0b ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
            '#ef4444 ' + weakEnd.toFixed(3) + 'deg ' + missingEnd.toFixed(3) + 'deg, ' +
            '#d7dee8 ' + missingEnd.toFixed(3) + 'deg 360deg)';
    }

    function formatMissingLine(count) {
        return count === 1
            ? '1 image missing ALT text'
            : formatCount(count) + ' images missing ALT text';
    }

    function formatMissingSummary(count) {
        return formatCount(Math.max(0, count)) + ' missing';
    }

    function formatOptimizedSummary(count) {
        return count === 1
            ? '1 image optimized'
            : formatCount(count) + ' images optimized';
    }

    function buildFixTitle(count) {
        var total = Math.max(0, parseInt(count, 10) || 0);

        if (total <= 0) {
            return 'Fix images in seconds';
        }

        return total === 1
            ? 'Fix 1 image in seconds'
            : 'Fix ' + formatCount(total) + ' images in seconds';
    }

    function buildReviewTitle(count) {
        var total = Math.max(0, parseInt(count, 10) || 0);

        if (total <= 0) {
            return 'Images ready to review';
        }

        return total === 1
            ? '1 image ready to review'
            : formatCount(total) + ' images ready to review';
    }

    function formatLastScanLine(timestamp) {
        var seconds = parseCount(timestamp);
        var now;
        var diff;
        var minutes;
        var hours;
        var days;

        if (!seconds) {
            return '';
        }

        now = Math.floor(Date.now() / 1000);
        if (seconds > now) {
            return '';
        }

        diff = now - seconds;

        if (diff < 60) {
            return 'Last scan: just now';
        }

        if (diff < 3600) {
            minutes = Math.max(1, Math.floor(diff / 60));
            return 'Last scan: ' + minutes + ' minute' + (minutes === 1 ? '' : 's') + ' ago';
        }

        if (diff < 86400) {
            hours = Math.max(1, Math.floor(diff / 3600));
            return 'Last scan: ' + hours + ' hour' + (hours === 1 ? '' : 's') + ' ago';
        }

        if (diff < 604800) {
            days = Math.max(1, Math.floor(diff / 86400));
            return 'Last scan: ' + days + ' day' + (days === 1 ? '' : 's') + ' ago';
        }

        try {
            return 'Last scan: ' + new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(seconds * 1000));
        } catch (error) {
            return 'Last scan: ' + new Date(seconds * 1000).toLocaleDateString();
        }
    }

    function buildProofModel(root) {
        var counts = getCounts(root);
        var state = resolveHeroState(root);
        var hasData = hasScanData(root, counts);
        var lastScanLine = formatLastScanLine(root.getAttribute('data-bbai-last-scan-ts'));
        var progressMessage = 'Scan your library to see what needs attention';

        if (state === HERO_STATES.SCANNING) {
            progressMessage = 'Scan in progress - results will appear here';
        } else if (counts.missing > 0) {
            progressMessage = 'Fix remaining images to improve your SEO';
        } else if (counts.weak > 0) {
            progressMessage = 'You\'re almost done — review remaining images';
        } else if (hasData) {
            progressMessage = 'Your images are fully optimized 🎉';
        }

        return {
            progressMessage: progressMessage,
            lastScanLine: lastScanLine,
            optimizedLine: formatOptimizedSummary(counts.optimized),
            showActivity: hasData || !!lastScanLine
        };
    }

    function openAuthModal(tab) {
        var mode = tab === 'register' ? 'register' : 'login';
        var nextUrl;

        if (window.authModal && typeof window.authModal.show === 'function') {
            try {
                window.authModal.show();

                if (mode === 'register' && typeof window.authModal.showRegisterForm === 'function') {
                    window.authModal.showRegisterForm();
                } else if (mode !== 'register' && typeof window.authModal.showLoginForm === 'function') {
                    window.authModal.showLoginForm();
                }

                return true;
            } catch (error) {
                // Fall through to direct auth modal handling.
            }
        }

        if (typeof window.showAuthModal === 'function') {
            try {
                window.showAuthModal(mode);
                return true;
            } catch (error) {
                // Fall through to URL-based fallback.
            }
        }

        try {
            nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('bbai_open_auth', '1');
            nextUrl.searchParams.set('bbai_auth_tab', mode);
            window.location.assign(nextUrl.toString());
            return true;
        } catch (error) {
            // Fall through to hard false if URL construction fails.
        }

        return false;
    }

    function isUpgradeModalLocked(root) {
        return !canAccessLibrary(root) && getCreditFunnelState(root).isExhausted;
    }

    function openUpgradeModal(trigger, options) {
        var root = getRoot();
        var source = options && options.source ? String(options.source) : 'dashboard';
        var context = {
            source: source,
            trigger: trigger || document.activeElement || null
        };

        if (!root || !canAccessLibrary(root)) {
            return openAuthModal(trigger && trigger.getAttribute && trigger.getAttribute('data-auth-tab') === 'login' ? 'login' : 'register');
        }

        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            try {
                if (window.bbaiOpenUpgradeModal('dashboard_locked', context) !== false) {
                    return true;
                }
            } catch (error) {
                // Fall through to centered dashboard modal fallback.
            }
        }

        return revealLockedPreviewUpgrade(trigger);
    }

    function revealLockedPreviewUpgrade(trigger) {
        var lockedPreview = getLockedTrialPreview();
        var authTrigger;

        if (lockedPreview && !lockedPreview.hasAttribute('hidden')) {
            authTrigger = lockedPreview.querySelector('[data-action="show-dashboard-auth"][data-auth-tab="register"]') ||
                lockedPreview.querySelector('[data-action="show-dashboard-auth"]');

            if (typeof lockedPreview.scrollIntoView === 'function') {
                lockedPreview.scrollIntoView({
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                    block: 'center'
                });
            }

            if (authTrigger && typeof authTrigger.focus === 'function') {
                window.setTimeout(function () {
                    authTrigger.focus();
                }, 180);
            }

            return true;
        }

        return openAuthModal(trigger && trigger.getAttribute && trigger.getAttribute('data-auth-tab') === 'login' ? 'login' : 'register');
    }

    function buildReviewImagesUrl(url) {
        var rawUrl = String(url || '').split('#')[0] || '#';
        var nextUrl;

        try {
            nextUrl = new URL(rawUrl, window.location.href);
        } catch (error) {
            return rawUrl;
        }

        nextUrl.searchParams.set('bbai_focus', 'first-review');
        nextUrl.hash = 'bbai-alt-table';

        return nextUrl.toString();
    }

    function resolveReviewImagesUrl(root, trigger) {
        var segment = trigger
            ? String(trigger.getAttribute('data-bbai-review-segment') || trigger.getAttribute('data-bbai-status-segment') || 'all')
            : 'all';
        var baseUrl = '';

        if (!root) {
            return '#';
        }

        if (segment === 'missing') {
            baseUrl = String(root.getAttribute('data-bbai-missing-library-url') || '');
        } else if (segment === 'weak' || segment === 'needs_review') {
            baseUrl = String(root.getAttribute('data-bbai-needs-review-library-url') || '');
        } else {
            baseUrl = String(root.getAttribute('data-bbai-library-url') || '');
        }

        if (!baseUrl) {
            baseUrl = String(root.getAttribute('data-bbai-library-url') || '#');
        }

        return buildReviewImagesUrl(baseUrl);
    }

    function getCreditsModel(root) {
        var creditState = getCreditFunnelState(root);
        var remaining = creditState.remaining;
        var used = creditState.used;
        var limit = creditState.limit;

        return {
            remainingLine: creditState.isGuestTrial
                ? (creditState.isExhausted
                    ? 'You\u2019ve used all ' + formatCount(limit) + ' free generations'
                    : (creditState.isLow
                        ? (remaining === 1
                            ? 'You\u2019re running low \u2014 1 free generation left'
                            : 'You\u2019re running low \u2014 ' + formatCount(remaining) + ' free generations left')
                        : (remaining === 1
                            ? 'You have 1 free generation remaining'
                            : 'You have ' + formatCount(remaining) + ' free generations remaining')))
                : (creditState.isExhausted
                    ? 'You\u2019ve used all ' + formatCount(limit) + ' generations this cycle'
                    : (creditState.isLow
                        ? (remaining === 1
                            ? 'You\u2019re running low \u2014 1 generation left'
                            : 'You\u2019re running low \u2014 ' + formatCount(remaining) + ' generations left')
                        : (remaining === 1
                            ? 'You have 1 generation remaining'
                            : 'You have ' + formatCount(remaining) + ' generations remaining'))),
            usageLine: formatCount(used) + ' / ' + formatCount(limit) + ' used',
            comparisonLine: creditState.isGuestTrial && creditState.isExhausted
                ? 'Create a free account to unlock ' + formatCount(creditState.freePlanOffer) + ' generations per month'
                : (!creditState.isGuestTrial && creditState.isExhausted && !creditState.isPremium
                    ? 'Unlock full ALT optimisation to keep improving your SEO.'
                    : ''),
            upgradeLabel: '',
            upgradeUrl: '',
            progress: Math.max(0, Math.min(100, Math.round((used / limit) * 100))),
            state: creditState.isExhausted ? 'exhausted' : (creditState.isLow ? 'low' : 'default')
        };
    }

function buildUnlockAction(root) {
	    if (isGuestTrialUser(root)) {
	        return buildAction(getLockedTrialCtaLabel(root), {
	            action: 'show-dashboard-auth',
	            authTab: 'register',
	            analytics: 'hero_continue_optimizing_signup'
            });
        }

        return buildAction('Unlock full ALT optimisation', {
            action: 'show-upgrade-modal'
        });
    }

	function buildLoginAction() {
	    return buildAction('Login', {
	        action: 'show-dashboard-auth',
	        authTab: 'login',
	        analytics: 'hero_exhausted_trial_login'
        });
    }

    function buildConversionPromptModel(root) {
        var creditState = getCreditFunnelState(root);

        if (creditState.isPremium || (!creditState.isLow && !creditState.isExhausted)) {
            return {
                visible: false,
                tone: 'soft',
                title: '',
                copy: '',
                note: '',
                action: null
            };
        }

        if (creditState.isExhausted) {
            if (creditState.isGuestTrial) {
                return {
                    visible: false,
                    tone: 'exhausted',
                    title: '',
                    copy: '',
                    note: '',
                    action: null
                };
            }

            return {
                visible: true,
                tone: 'exhausted',
                title: 'You\u2019ve used all ' + formatCount(creditState.limit) + ' generations this cycle',
                copy: 'Unlock full ALT optimisation to continue improving your SEO.',
                note: 'Higher monthly usage and bulk optimisation unlock on paid plans.',
                action: null
            };
        }

        if (creditState.hasRecentSuccess) {
            return {
                visible: true,
                tone: 'low',
                title: 'Nice \u2014 your images are improving',
                copy: creditState.isGuestTrial
                    ? 'Continue fixing your remaining images and unlock full access.'
                    : 'Unlock full ALT optimisation to keep the momentum going.',
                note: creditState.isGuestTrial
                    ? 'Unlock ' + formatCount(creditState.freePlanOffer) + ' generations per month'
                    : 'More monthly usage and bulk optimisation unlock next.',
                action: buildUnlockAction(root)
            };
        }

        return {
            visible: true,
            tone: 'low',
            title: creditState.isGuestTrial
                ? 'You\u2019re almost out of free generations'
                : 'You\u2019re running low on generations',
            copy: creditState.isGuestTrial
                ? 'Continue fixing your remaining images and unlock full access.'
                : 'Unlock full ALT optimisation to keep improving your SEO.',
            note: creditState.isGuestTrial
                ? 'Unlock ' + formatCount(creditState.freePlanOffer) + ' generations per month'
                : 'More monthly usage and bulk optimisation unlock next.',
            action: buildUnlockAction(root)
        };
    }

    function buildAction(label, config) {
        var action = config || {};

        return {
            label: label || '',
            href: action.href || '#',
            action: action.action || '',
            bbaiAction: action.bbaiAction || '',
            authTab: action.authTab || '',
            analytics: action.analytics || '',
            disabled: !!action.disabled,
            ariaBusy: !!action.ariaBusy,
            fixDashboard: !!action.fixDashboard,
            reviewDashboard: !!action.reviewDashboard,
            reviewSegment: action.reviewSegment || ''
        };
    }

    function getLockedTrialCtaLabel(root) {
        var counts = getCounts(root);
        return (counts.missing + counts.weak) > 0
            ? 'Fix remaining images for free'
            : 'Continue fixing images';
    }

    function getLockedTrialCtaContext(root, missingCount) {
        var count = typeof missingCount === 'number'
            ? Math.max(0, missingCount)
            : Math.max(0, getCounts(root).missing);

        if (count === 1) {
            return 'You\u2019re 1 image away from full optimisation';
        }

        if (count > 1) {
            return 'You\u2019re ' + formatCount(count) + ' images away from full optimisation';
        }

        return 'Continue fixing images and unlock full access';
    }

    function resolveLockedAction(root) {
        var mode = String(root.getAttribute('data-bbai-locked-cta-mode') || '');
        var isGuestTrial = root.getAttribute('data-bbai-is-guest-trial') === '1';
        var quotaState = String(root.getAttribute('data-bbai-quota-state') || '');

	    if (mode === 'create_account' || (mode === '' && isGuestTrial && quotaState === 'exhausted')) {
	        return buildAction(getLockedTrialCtaLabel(root), {
	            action: 'show-dashboard-auth',
	            authTab: 'register',
	            analytics: 'hero_create_account_exhausted'
            });
        }

        if (mode) {
            return buildAction('Unlock full ALT optimisation', {
                action: 'show-upgrade-modal'
            });
        }

        return null;
    }

    function buildFixPrimaryAction(root, actionableCount) {
        var quotaState = String(root && root.getAttribute('data-bbai-quota-state') || '');

	    if (isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0) {
	        return buildAction(getLockedTrialCtaLabel(root), {
	            action: 'show-dashboard-auth',
	            authTab: 'register',
	            analytics: 'hero_fix_all_images_signup'
            });
        }

        if (!isGuestTrialUser(root) && actionableCount > 0 && quotaState === 'exhausted') {
            return buildAction('Unlock full ALT optimisation', {
                action: 'show-upgrade-modal'
            });
        }

        return buildAction('Fix all images', {
            action: 'generate-missing',
            bbaiAction: 'generate_missing',
            fixDashboard: true
        });
    }

    function buildReviewPrimaryAction(root) {
	    if (isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0) {
	        return buildAction(getLockedTrialCtaLabel(root), {
	            action: 'show-dashboard-auth',
	            authTab: 'register',
	            analytics: 'hero_review_images_signup'
            });
        }

        if (isGuestTrialUser(root)) {
            return buildAction('Review images', {
                action: 'review-dashboard-results',
                reviewDashboard: true,
                reviewSegment: 'weak'
            });
        }

        return buildAction('Review images', {
            action: 'review-dashboard-results',
            href: buildReviewImagesUrl(
                root.getAttribute('data-bbai-needs-review-library-url') ||
                root.getAttribute('data-bbai-library-url') ||
                '#'
            ),
            reviewDashboard: true,
            reviewSegment: 'weak'
        });
    }

    function buildCompletePrimaryAction(root) {
        if (isGuestTrialUser(root)) {
            return buildAction('View results', {
                action: 'review-dashboard-results',
                reviewDashboard: true,
                reviewSegment: 'optimized'
            });
        }

        if (canAccessLibrary(root)) {
            return buildAction('View results', {
                href: String(root && root.getAttribute('data-bbai-library-url') || '#')
            });
        }

        return buildAction('View results', {
            action: 'review-dashboard-results',
            reviewDashboard: true,
            reviewSegment: 'optimized'
        });
    }

    function buildExhaustedTrialHeroModel(root, counts) {
        var credits = getCreditsModel(root);
        var missingCount = Math.max(0, counts && typeof counts.missing === 'number' ? counts.missing : 0);
        var limit = getCreditFunnelState(root).limit;
        var title = 'You\u2019ve used all ' + formatCount(limit) + ' free generations';
        var statusLabel = missingCount === 1
            ? '1 image missing ALT text'
            : formatCount(missingCount) + ' images missing ALT text';

        return {
            statusLabel: statusLabel,
            statusDetail: '',
            donutValue: formatCount(missingCount),
            donutLabel: '',
            donutTone: missingCount > 0 ? 'problem' : 'neutral',
            donutBackground: buildDonutGradient(counts),
            title: title,
            description: 'Continue fixing your remaining images and unlock full access.',
            primaryAction: buildUnlockAction(root),
            secondaryAction: buildLoginAction(),
            ctaContext: getLockedTrialCtaContext(root, missingCount),
            supportLine: '50 free generations/month • Full library access • Bulk optimise',
            showCredits: false,
            showConversionPrompt: false,
            showMicrocopy: false,
            credits: credits,
            isExhaustedTrial: true
        };
    }

    function buildHeroModel(root, state) {
        var counts = getCounts(root);
        var actionable = getActionableState(root, counts);
        var runtime = String(root.getAttribute('data-bbai-dashboard-runtime-state') || 'idle');
        var generationBusy = runtime === 'generation_running' || runtime === 'logged_out_trial_running';
        var fixAction = buildFixPrimaryAction(root, actionable.actionableCount);
        var reviewAction = buildReviewPrimaryAction(root);
        var defaultDescription = 'Automatically generate SEO-friendly ALT text';
        var reviewDescription = 'Quickly review generated ALT text before publishing.';
        var donutValue = actionable.key === ACTIONABLE_STATES.MISSING
            ? formatCount(actionable.actionableCount)
            : (actionable.key === ACTIONABLE_STATES.REVIEW ? formatCount(actionable.reviewCount) : '100%');
        var donutLabel = actionable.key === ACTIONABLE_STATES.MISSING
            ? 'TO FIX'
            : (actionable.key === ACTIONABLE_STATES.REVIEW ? 'TO REVIEW' : 'OPTIMIZED');

        if (state === HERO_STATES.NOT_SCANNED) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: '0%',
                donutLabel: 'OPTIMIZED',
                donutTone: 'neutral',
                donutBackground: 'conic-gradient(#d7dee8 0deg 360deg)',
                title: 'Scan your images in seconds',
                description: defaultDescription,
                primaryAction: buildAction('Scan images', {
                    bbaiAction: 'scan-opportunity'
                }),
                secondaryAction: null,
                credits: getCreditsModel(root)
            };
        }

        if (state === HERO_STATES.SCANNING) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: '0',
                donutLabel: 'TO FIX',
                donutTone: 'scanning',
                donutBackground: 'conic-gradient(#d7dee8 0deg 360deg)',
                title: 'Scanning your images',
                description: defaultDescription,
                primaryAction: buildAction('Scanning images...', { disabled: true, ariaBusy: true }),
                secondaryAction: null,
                credits: getCreditsModel(root)
            };
        }

        if (generationBusy) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: donutValue,
                donutLabel: donutLabel,
                donutTone: 'scanning',
                donutBackground: buildDonutGradient(counts),
                title: actionable.actionableCount === 1
                    ? 'Fixing 1 image...'
                    : 'Fixing ' + formatCount(Math.max(1, actionable.actionableCount)) + ' images...',
                description: defaultDescription,
                primaryAction: buildAction('Fixing images...', { disabled: true, ariaBusy: true }),
                secondaryAction: null,
                credits: getCreditsModel(root)
            };
        }

        if (isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0) {
            return buildExhaustedTrialHeroModel(root, counts);
        }

        if (state === HERO_STATES.SCANNED_HAS_ISSUES && actionable.key === ACTIONABLE_STATES.REVIEW) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: donutValue,
                donutLabel: donutLabel,
                donutTone: 'problem',
                donutBackground: buildDonutGradient(counts),
                title: buildReviewTitle(actionable.reviewCount),
                description: isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0
                    ? 'Continue fixing your remaining images and unlock full access.'
                    : reviewDescription,
                primaryAction: reviewAction,
                secondaryAction: null,
                supportLine: '',
                showCredits: true,
                showConversionPrompt: true,
                showMicrocopy: true,
                credits: getCreditsModel(root)
            };
        }

        if (state === HERO_STATES.SCANNED_HAS_ISSUES && isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: donutValue,
                donutLabel: donutLabel,
                donutTone: 'problem',
                donutBackground: buildDonutGradient(counts),
                title: buildFixTitle(actionable.actionableCount),
                description: 'Continue fixing your remaining images and unlock full access.',
                primaryAction: fixAction,
                secondaryAction: null,
                supportLine: '',
                showCredits: true,
                showConversionPrompt: true,
                showMicrocopy: true,
                credits: getCreditsModel(root)
            };
        }

        if (state === HERO_STATES.SCANNED_HAS_ISSUES && String(root.getAttribute('data-bbai-quota-state') || '') === 'exhausted') {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: donutValue,
                donutLabel: donutLabel,
                donutTone: 'problem',
                donutBackground: buildDonutGradient(counts),
                title: buildFixTitle(actionable.actionableCount),
                description: 'Unlock full ALT optimisation to continue from the dashboard.',
                primaryAction: fixAction,
                secondaryAction: null,
                supportLine: '',
                showCredits: true,
                showConversionPrompt: true,
                showMicrocopy: true,
                credits: getCreditsModel(root)
            };
        }

        if (state === HERO_STATES.SCANNED_HAS_ISSUES) {
            return {
                statusLabel: '',
                statusDetail: '',
                donutValue: donutValue,
                donutLabel: donutLabel,
                donutTone: 'problem',
                donutBackground: buildDonutGradient(counts),
                title: buildFixTitle(actionable.actionableCount),
                description: defaultDescription,
                primaryAction: fixAction,
                secondaryAction: null,
                supportLine: '',
                showCredits: true,
                showConversionPrompt: true,
                showMicrocopy: true,
                credits: getCreditsModel(root)
            };
        }

        return {
            statusLabel: '',
            statusDetail: '',
            donutValue: donutValue,
            donutLabel: 'OPTIMIZED',
            donutTone: 'healthy',
            donutBackground: 'conic-gradient(#22c55e 0deg 360deg)',
            title: 'Your images are fully optimised',
            description: 'Your latest ALT text is ready to view and review.',
            primaryAction: buildCompletePrimaryAction(root),
            secondaryAction: null,
            supportLine: '',
            showCredits: true,
            showConversionPrompt: true,
            showMicrocopy: true,
            credits: getCreditsModel(root)
        };
    }

    function setActionNode(node, model) {
        if (!node) {
            return;
        }

        if (!model || !model.label) {
            node.textContent = '';
            node.setAttribute('href', '#');
            node.removeAttribute('data-action');
            node.removeAttribute('data-bbai-action');
            node.removeAttribute('data-auth-tab');
            node.removeAttribute('data-bbai-analytics-upgrade');
            node.removeAttribute('data-bbai-fix-dashboard');
            node.removeAttribute('data-bbai-review-dashboard');
            node.removeAttribute('data-bbai-review-segment');
            node.removeAttribute('disabled');
            node.removeAttribute('aria-busy');
            setHidden(node, true);
            return;
        }

        node.textContent = model.label;
        node.setAttribute('href', model.href || '#');
        node.removeAttribute('data-action');
        node.removeAttribute('data-bbai-action');
        node.removeAttribute('data-auth-tab');
        node.removeAttribute('data-bbai-analytics-upgrade');
        node.removeAttribute('data-bbai-fix-dashboard');
        node.removeAttribute('data-bbai-review-dashboard');
        node.removeAttribute('data-bbai-review-segment');
        node.removeAttribute('disabled');
        node.removeAttribute('aria-busy');

        if (model.action) {
            node.setAttribute('data-action', model.action);
        }
        if (model.bbaiAction) {
            node.setAttribute('data-bbai-action', model.bbaiAction);
        }
        if (model.authTab) {
            node.setAttribute('data-auth-tab', model.authTab);
        }
        if (model.analytics) {
            node.setAttribute('data-bbai-analytics-upgrade', model.analytics);
        }
        if (model.fixDashboard) {
            node.setAttribute('data-bbai-fix-dashboard', '1');
        }
        if (model.reviewDashboard) {
            node.setAttribute('data-bbai-review-dashboard', '1');
        }
        if (model.reviewSegment) {
            node.setAttribute('data-bbai-review-segment', model.reviewSegment);
        }
        if (model.disabled) {
            node.setAttribute('disabled', '');
        }
        if (model.ariaBusy) {
            node.setAttribute('aria-busy', 'true');
        }

        setHidden(node, false);
    }

    function setCreditsBlock(hero, credits) {
        var creditsRoot = hero.querySelector('[data-bbai-dashboard-credits]');
        var remainingNode = hero.querySelector('[data-bbai-hero-credits-remaining]');
        var usageNode = hero.querySelector('[data-bbai-hero-credits-usage]');
        var comparisonNode = hero.querySelector('[data-bbai-hero-credits-comparison]');
        var upgradeNode = hero.querySelector('[data-bbai-hero-credits-upgrade]');
        var progressNode = hero.querySelector('[data-bbai-hero-credits-progress]');
        var progressFillNode = hero.querySelector('[data-bbai-hero-credits-progress-fill]');

        if (creditsRoot) {
            creditsRoot.classList.remove('bbai-dashboard-credits--default', 'bbai-dashboard-credits--low', 'bbai-dashboard-credits--exhausted');
            creditsRoot.classList.add('bbai-dashboard-credits--' + String(credits.state || 'default'));
            creditsRoot.setAttribute('data-bbai-hero-credits-state', String(credits.state || 'default'));
        }

        if (remainingNode) {
            remainingNode.textContent = credits.remainingLine || '';
        }
        if (usageNode) {
            usageNode.textContent = credits.usageLine || '';
        }
        if (comparisonNode) {
            comparisonNode.textContent = credits.comparisonLine || '';
            setHidden(comparisonNode, !credits.comparisonLine);
        }
        if (upgradeNode) {
            upgradeNode.textContent = credits.upgradeLabel || '';
            if (credits.upgradeUrl) {
                upgradeNode.setAttribute('href', credits.upgradeUrl);
            } else {
                upgradeNode.removeAttribute('href');
            }
            setHidden(upgradeNode, !credits.upgradeLabel || !credits.upgradeUrl);
        }
        if (progressNode) {
            progressNode.setAttribute('aria-valuenow', String(credits.progress || 0));
        }
        if (progressFillNode) {
            progressFillNode.style.width = String(credits.progress || 0) + '%';
        }
    }

    function setConversionPrompt(hero, prompt) {
        var promptRoot = hero.querySelector('[data-bbai-hero-conversion-prompt]');
        var titleNode;
        var copyNode;
        var noteNode;
        var actionsNode;
        var ctaNode;

        if (!promptRoot) {
            return;
        }

        titleNode = promptRoot.querySelector('[data-bbai-hero-conversion-title]');
        copyNode = promptRoot.querySelector('[data-bbai-hero-conversion-copy]');
        noteNode = promptRoot.querySelector('[data-bbai-hero-conversion-note]');
        actionsNode = promptRoot.querySelector('[data-bbai-hero-conversion-actions]');
        ctaNode = promptRoot.querySelector('[data-bbai-hero-conversion-cta]');

        promptRoot.classList.remove('bbai-dashboard-conversion-prompt--low', 'bbai-dashboard-conversion-prompt--exhausted', 'bbai-dashboard-conversion-prompt--soft');
        promptRoot.classList.add('bbai-dashboard-conversion-prompt--' + String(prompt && prompt.tone ? prompt.tone : 'soft'));
        promptRoot.setAttribute('data-bbai-hero-conversion-tone', String(prompt && prompt.tone ? prompt.tone : 'soft'));
        setHidden(promptRoot, !prompt || !prompt.visible);

        if (titleNode) {
            titleNode.textContent = prompt && prompt.title ? prompt.title : '';
        }
        if (copyNode) {
            copyNode.textContent = prompt && prompt.copy ? prompt.copy : '';
        }
        if (noteNode) {
            noteNode.textContent = prompt && prompt.note ? prompt.note : '';
            setHidden(noteNode, !prompt || !prompt.note);
        }
        if (actionsNode) {
            setHidden(actionsNode, !prompt || !prompt.action || !prompt.action.label);
        }
        if (ctaNode) {
            setActionNode(ctaNode, prompt && prompt.action ? prompt.action : null);
        }
    }

    function syncHero() {
        var root = getRoot();
        var hero = getHero();
        var model;
        var nextState;
        var previousState;
        var donut;
        var donutValue;
        var donutLabel;
        var statusLabel;
        var statusDetail;
        var title;
        var description;
        var ctaContext;
        var statusBlock;
        var supportLine;
        var creditsRoot;
        var conversionPrompt;
        var microcopy;

        if (!root || !hero) {
            return;
        }

        previousState = String(hero.getAttribute('data-bbai-funnel-hero-state') || '');
        nextState = resolveHeroState(root);
        model = buildHeroModel(root, nextState);
        donut = hero.querySelector('[data-bbai-status-donut]');
        donutValue = hero.querySelector('[data-bbai-funnel-donut-value]');
        donutLabel = hero.querySelector('[data-bbai-funnel-donut-label]');
        statusLabel = hero.querySelector('[data-bbai-hero-status-label]');
        statusDetail = hero.querySelector('[data-bbai-hero-status-detail]');
        title = hero.querySelector('[data-bbai-funnel-hero-title]');
        description = hero.querySelector('[data-bbai-funnel-hero-desc]');
        ctaContext = hero.querySelector('[data-bbai-funnel-hero-cta-context]');
        statusBlock = hero.querySelector('[data-bbai-hero-status-block]');
        supportLine = hero.querySelector('[data-bbai-funnel-hero-support]');
        creditsRoot = hero.querySelector('[data-bbai-dashboard-credits]');
        conversionPrompt = hero.querySelector('[data-bbai-hero-conversion-prompt]');
        microcopy = hero.querySelector('[data-bbai-hero-microcopy]');

        hero.setAttribute('data-bbai-funnel-hero-state', nextState);
        hero.setAttribute('data-bbai-hero-ui-state', nextState);
        root.setAttribute('data-bbai-funnel-state', nextState);
        hero.classList.toggle('bbai-funnel-hero--trial-exhausted', !!model.isExhaustedTrial);

        if (hero.querySelector('[data-bbai-dashboard-hero-action]')) {
            hero.querySelector('[data-bbai-dashboard-hero-action]').classList.toggle('bbai-dashboard-hero-action--exhausted-trial', !!model.isExhaustedTrial);
        }

        if (donut) {
            donut.style.background = model.donutBackground;
            donut.className = donut.className.replace(/\sbbai-command-donut--(?:healthy|problem|neutral|scanning|fixing)\b/g, '');
            donut.classList.add('bbai-command-donut--' + model.donutTone);
            donut.classList.toggle('bbai-dashboard-hero-action__donut--idle', nextState === HERO_STATES.NOT_SCANNED);
        }
        if (donutValue) {
            setAnimatedDonutValue(donutValue, model.donutValue);
            donutValue.className = donutValue.className.replace(/\sbbai-command-donut__center-value--(?:healthy|problem|neutral|scanning|fixing)\b/g, '');
            donutValue.classList.add('bbai-command-donut__center-value--' + model.donutTone);
        }
        if (donutLabel) {
            donutLabel.textContent = model.donutLabel;
            setHidden(donutLabel, !model.donutLabel);
        }
        if (statusLabel) {
            statusLabel.textContent = model.statusLabel;
        }
        if (statusDetail) {
            statusDetail.textContent = model.statusDetail || '';
            setHidden(statusDetail, !model.statusDetail);
        }
        if (statusBlock) {
            setHidden(statusBlock, !model.statusLabel && !model.statusDetail);
        }
        if (title) {
            title.textContent = model.title;
        }
        if (description) {
            description.textContent = model.description;
        }
        if (ctaContext) {
            ctaContext.textContent = model.ctaContext || '';
            setHidden(ctaContext, !model.ctaContext);
        }
        if (supportLine) {
            supportLine.textContent = model.supportLine || '';
            setHidden(supportLine, !model.supportLine);
        }

        setActionNode(hero.querySelector('[data-bbai-funnel-hero-primary]'), model.primaryAction);
        setActionNode(hero.querySelector('[data-bbai-funnel-hero-secondary]'), model.secondaryAction || null);
        setCreditsBlock(hero, model.credits || {});
        setConversionPrompt(hero, buildConversionPromptModel(root));

        if (creditsRoot) {
            setHidden(creditsRoot, model.showCredits === false);
        }
        if (conversionPrompt) {
            setHidden(conversionPrompt, model.showConversionPrompt === false);
        }
        if (microcopy) {
            setHidden(microcopy, model.showMicrocopy === false);
        }

        if (donut && nextState === HERO_STATES.SCANNED_CLEAN && previousState !== HERO_STATES.SCANNED_CLEAN) {
            triggerCompleteDonutPulse(donut);
        }
    }

    function getPreviewCountMap(root) {
        var counts = getCounts(root);

        return {
            all: counts.total,
            missing: counts.missing,
            weak: counts.weak,
            optimized: counts.optimized
        };
    }

    function rowMatchesPreviewSegment(row, segment) {
        var status;

        if (!row) {
            return false;
        }

        status = String(row.getAttribute('data-status') || row.getAttribute('data-review-state') || '').toLowerCase();

        if (!segment || segment === 'all') {
            return true;
        }

        if (segment === 'weak') {
            return status === 'weak' || status === 'needs-review' || status === 'needs_review';
        }

        return status === segment;
    }

    function getPreviewSegmentLabel(segment) {
        if (segment === 'missing') {
            return 'missing';
        }
        if (segment === 'weak') {
            return 'needs review';
        }
        if (segment === 'optimized') {
            return 'optimized';
        }

        return 'all';
    }

    function applyTrialPreviewFilter(segment) {
        var root = getRoot();
        var preview = getTrialPreview();
        var rows;
        var countMap;
        var moreNode;
        var emptyNode;
        var emptyStateNode;
        var normalizedSegment = segment || 'all';
        var visibleCount = 0;
        var totalForSegment;
        var extraCount;

        if (!root || !preview) {
            return false;
        }

        rows = preview.querySelectorAll('[data-bbai-trial-preview-row="1"]');
        countMap = getPreviewCountMap(root);
        totalForSegment = Object.prototype.hasOwnProperty.call(countMap, normalizedSegment)
            ? countMap[normalizedSegment]
            : countMap.all;
        moreNode = preview.querySelector('[data-bbai-trial-preview-more]');
        emptyNode = preview.querySelector('[data-bbai-trial-preview-empty]');
        emptyStateNode = preview.querySelector('[data-bbai-trial-preview-empty-state]');

        Array.prototype.forEach.call(rows, function (row) {
            var matches = rowMatchesPreviewSegment(row, normalizedSegment);

            row.classList.toggle('bbai-library-row--hidden', !matches);
            row.setAttribute('aria-hidden', matches ? 'false' : 'true');
            row.hidden = !matches;

            if (matches) {
                visibleCount += 1;
            }
        });

        extraCount = Math.max(0, totalForSegment - visibleCount);

        if (moreNode) {
            moreNode.textContent = extraCount > 0
                ? '+' + formatCount(extraCount) + ' more images'
                : '';
            setHidden(moreNode, extraCount <= 0);
        }

        if (emptyNode) {
            if (rows.length === 0) {
                emptyNode.textContent = '';
                setHidden(emptyNode, true);
            } else if (visibleCount === 0 && totalForSegment > 0) {
                emptyNode.textContent = 'This preview shows only the first 3 images. Create a free account to unlock the full ALT Library.';
                setHidden(emptyNode, false);
            } else if (visibleCount === 0) {
                emptyNode.textContent = 'No ' + getPreviewSegmentLabel(normalizedSegment) + ' images in this preview yet.';
                setHidden(emptyNode, false);
            } else {
                emptyNode.textContent = '';
                setHidden(emptyNode, true);
            }
        }

        if (emptyStateNode) {
            setHidden(emptyStateNode, rows.length > 0);
        }

        preview.setAttribute('data-bbai-trial-preview-filter', normalizedSegment);
        return true;
    }

    function getDefaultStatusSegment(root) {
        var counts = getCounts(root);
        var actionable = getActionableState(root, counts);

        if (!hasScanData(root, counts)) {
            return 'all';
        }
        if (actionable.key === ACTIONABLE_STATES.MISSING) {
            return 'missing';
        }
        if (actionable.key === ACTIONABLE_STATES.REVIEW) {
            return 'weak';
        }
        if (counts.optimized > 0) {
            return 'optimized';
        }
        return 'all';
    }

    function setStatusRowActive(row, segment) {
        var card = row && typeof row.closest === 'function'
            ? row.closest('[data-bbai-dashboard-status-card]')
            : null;

        row.setAttribute('data-bbai-status-active-segment', segment || 'all');
        row.setAttribute('data-bbai-status-selected-segment', segment || 'all');

        if (card) {
            card.setAttribute('data-bbai-status-active-segment', segment || 'all');
            card.setAttribute('data-bbai-status-selected-segment', segment || 'all');
        }

        Array.prototype.forEach.call(row.querySelectorAll('[data-bbai-dashboard-status-pill]'), function (item) {
            var isActive = String(item.getAttribute('data-bbai-status-segment') || 'all') === segment;
            item.classList.toggle('bbai-filter-group__item--active', isActive);
            item.classList.toggle('is-active', isActive);
            item.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            if (isActive) {
                item.setAttribute('aria-current', 'true');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    function scrollDashboardReviewResults(trigger) {
        var row = getStatusRow();
        var preview = getTrialPreview();
        var target = preview || document.querySelector('#bbai-dashboard-status-card') || (row ? row.closest('[data-bbai-dashboard-status-card]') : null);
        var segment = trigger
            ? String(trigger.getAttribute('data-bbai-review-segment') || trigger.getAttribute('data-bbai-status-segment') || 'all')
            : 'all';
        var activeItem;

        if (row) {
            setStatusRowActive(row, segment);
            activeItem = row.querySelector('[data-bbai-status-segment="' + segment + '"]');
        }

        if (preview) {
            applyTrialPreviewFilter(segment);
        }

        if (target && typeof target.scrollIntoView === 'function') {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        if (activeItem && typeof activeItem.focus === 'function') {
            window.setTimeout(function () {
                activeItem.focus();
            }, 160);
        }
    }

    function syncStatusRow() {
        var root = getRoot();
        var row = getStatusRow();
        var counts;
        var countMap;
        var isGenerating;
        var selectedSegment;

        if (!root || !row) {
            return;
        }

        isGenerating = window.bbaiJobState &&
            window.bbaiJobState.getState &&
            window.bbaiJobState.getState().status === 'processing';

        if (isGenerating) {
            return;
        }

        counts = getCounts(root);
        countMap = {
            all: counts.total,
            optimized: counts.optimized,
            weak: counts.weak,
            missing: counts.missing
        };

        Array.prototype.forEach.call(row.querySelectorAll('[data-bbai-dashboard-status-pill]'), function (item) {
            var segment = String(item.getAttribute('data-bbai-status-segment') || 'all');
            var countNode = item.querySelector('[data-bbai-dashboard-status-count]');

            if (countNode && Object.prototype.hasOwnProperty.call(countMap, segment)) {
                countNode.textContent = formatCount(countMap[segment]);
            }
        });

        selectedSegment = String(
            row.getAttribute('data-bbai-status-selected-segment') ||
            row.getAttribute('data-bbai-status-active-segment') ||
            getDefaultStatusSegment(root)
        );
        setStatusRowActive(row, selectedSegment || getDefaultStatusSegment(root));
    }

    function bindStatusRow() {
        var root = getRoot();
        var row = getStatusRow();

        if (!row || row.getAttribute('data-bbai-status-nav-bound') === '1') {
            return;
        }

        row.setAttribute('data-bbai-status-nav-bound', '1');
        row.addEventListener('click', function (event) {
            var item = event.target && event.target.closest
                ? event.target.closest('[data-bbai-dashboard-status-pill]')
                : null;
            var modifiedClick;
            var targetUrl;
            var segment;

            if (!item) {
                return;
            }

            segment = String(item.getAttribute('data-bbai-status-segment') || 'all');
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            if (isUpgradeModalLocked(root)) {
                event.preventDefault();
                openUpgradeModal(item, { source: 'dashboard-status-pill' });
                return;
            }

            if (isGuestTrialUser(root) && getTrialPreview()) {
                event.preventDefault();
                setStatusRowActive(row, segment);
                applyTrialPreviewFilter(segment);
                item.classList.add('is-pressed');

                window.setTimeout(function () {
                    item.classList.remove('is-pressed');
                }, 90);
                return;
            }

            if (!canAccessLibrary(root)) {
                event.preventDefault();
                openUpgradeModal(item, { source: 'dashboard-status-pill' });
                return;
            }

            modifiedClick = !!(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0);
            if (modifiedClick) {
                return;
            }

            targetUrl = String(item.getAttribute('href') || item.getAttribute('data-bbai-status-url') || '');
            if (!targetUrl) {
                return;
            }

            event.preventDefault();
            setStatusRowActive(row, segment);
            item.classList.add('is-pressed');

            window.setTimeout(function () {
                item.classList.remove('is-pressed');
                window.location.assign(targetUrl);
            }, 90);
        });
    }

    function bindHeroLibraryAccess() {
        var root = getRoot();
        var hero = getHero();

        if (!hero || hero.getAttribute('data-bbai-hero-library-gate-bound') === '1') {
            return;
        }

	    hero.setAttribute('data-bbai-hero-library-gate-bound', '1');
	    hero.addEventListener('click', function (event) {
	        var trigger = event.target && event.target.closest
	            ? event.target.closest('[data-action="show-dashboard-auth"], [data-action="review-dashboard-results"]')
	            : null;
	        var reviewUrl;

            if (!trigger) {
                return;
            }

            if (trigger.getAttribute('data-action') === 'review-dashboard-results') {
                event.preventDefault();
                if (isGuestTrialUser(root) && getTrialPreview()) {
                    scrollDashboardReviewResults(trigger);
                    return;
                }
                if (!canAccessLibrary(root)) {
                    openUpgradeModal(trigger, { source: 'dashboard-review-action' });
                    return;
                }

                reviewUrl = resolveReviewImagesUrl(root, trigger);
                if (reviewUrl) {
                    window.location.assign(reviewUrl);
                }
	            return;
	        }

	        if (trigger.getAttribute('data-action') === 'show-dashboard-auth') {
	            event.preventDefault();
	            event.stopPropagation();
	            if (typeof event.stopImmediatePropagation === 'function') {
	                event.stopImmediatePropagation();
	            }
	            openAuthModal(trigger.getAttribute('data-auth-tab') === 'login' ? 'login' : 'register');
	            return;
	        }

	        event.preventDefault();
	        event.stopPropagation();
	        openUpgradeModal(trigger, { source: 'dashboard-locked-cta' });
        });

        if (!root) {
            return;
        }

        root.addEventListener('click', function (event) {
            if (event.defaultPrevented) {
                return;
            }

            var reviewTrigger = event.target && event.target.closest
                ? event.target.closest('[data-action="review-dashboard-results"]')
                : null;
	        var lockedTrigger = event.target && event.target.closest
	            ? event.target.closest('[data-action="show-dashboard-auth"]')
	            : null;
            var trigger = event.target && event.target.closest
                ? event.target.closest('a[href*="page=bbai-library"]')
                : null;
            var reviewUrl;

	        if (lockedTrigger) {
	            event.preventDefault();
	            event.stopPropagation();
	            if (typeof event.stopImmediatePropagation === 'function') {
	                event.stopImmediatePropagation();
	            }
	            openAuthModal(lockedTrigger.getAttribute('data-auth-tab') === 'login' ? 'login' : 'register');
	            return;
	        }

            if (reviewTrigger) {
                event.preventDefault();
                if (isGuestTrialUser(root) && getTrialPreview()) {
                    scrollDashboardReviewResults(reviewTrigger);
                    return;
                }
                if (!canAccessLibrary(root)) {
                    event.stopPropagation();
                    openUpgradeModal(reviewTrigger, { source: 'dashboard-review-action' });
                    return;
                }

                reviewUrl = resolveReviewImagesUrl(root, reviewTrigger);
                if (reviewUrl) {
                    window.location.assign(reviewUrl);
                }
                return;
            }

            if (!trigger) {
                return;
            }

            if (canAccessLibrary(root) || root.getAttribute('data-bbai-has-connected-account') === '1') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            openUpgradeModal(trigger, { source: 'dashboard-library-link' });
        });
    }

    function maybeRevealLibraryGateFromQuery() {
        var root = getRoot();
        var nextUrl;

        if (!root || canAccessLibrary(root)) {
            return;
        }

        try {
            nextUrl = new URL(window.location.href);
        } catch (error) {
            return;
        }

        if (nextUrl.searchParams.get('bbai_show_library_gate') !== '1') {
            return;
        }

        nextUrl.searchParams.delete('bbai_show_library_gate');
        window.history.replaceState({}, document.title, nextUrl.toString());
        openUpgradeModal(null, { source: 'dashboard-library-redirect' });
    }

    function syncProofSection() {
        var root = getRoot();
        var proofSection = getProofSection();
        var model;
        var messageNode;
        var activityNode;
        var lastScanNode;
        var optimizedNode;

        if (!root || !proofSection) {
            return;
        }

        if (isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0) {
            setHidden(proofSection, true);
            return;
        }

        model = buildProofModel(root);
        messageNode = proofSection.querySelector('[data-bbai-dashboard-proof-message]');
        activityNode = proofSection.querySelector('[data-bbai-dashboard-proof-activity]');
        lastScanNode = proofSection.querySelector('[data-bbai-dashboard-proof-last-scan]');
        optimizedNode = proofSection.querySelector('[data-bbai-dashboard-proof-optimized]');

        setHidden(proofSection, false);

        if (messageNode) {
            messageNode.textContent = model.progressMessage;
        }

        if (lastScanNode) {
            lastScanNode.textContent = model.lastScanLine || '';
            setHidden(lastScanNode, !model.lastScanLine);
        }

        if (optimizedNode) {
            optimizedNode.textContent = model.optimizedLine;
        }

        if (activityNode) {
            setHidden(activityNode, !model.showActivity);
        }
    }

    function syncTrialPreviewSurface() {
        var root = getRoot();
        var activePreview = getTrialPreviewNode();
        var lockedPreview = getLockedTrialPreview();
        var primaryCtaNode;
        var showLocked;

        if (!root || (!activePreview && !lockedPreview)) {
            return;
        }

        showLocked = isGuestTrialUser(root) && getTrialCreditsRemaining(root) <= 0;

        if (activePreview) {
            setHidden(activePreview, showLocked);
        }

        if (lockedPreview) {
            setHidden(lockedPreview, !showLocked);

            primaryCtaNode = lockedPreview.querySelector('[data-action="show-dashboard-auth"][data-auth-tab="register"]');
            if (primaryCtaNode) {
                primaryCtaNode.textContent = getLockedTrialCtaLabel(root);
            }
        }
    }

    function syncTrialPreview() {
        var row = getStatusRow();
        var preview = getTrialPreview();
        var segment;

        if (!preview) {
            return;
        }

        segment = row
            ? String(
                row.getAttribute('data-bbai-status-selected-segment') ||
                row.getAttribute('data-bbai-status-active-segment') ||
                getDefaultStatusSegment(root)
            )
            : getDefaultStatusSegment(root);

        applyTrialPreviewFilter(segment || 'all');
    }

    function refresh() {
        syncHero();
        syncTrialPreviewSurface();
        syncStatusRow();
        syncTrialPreview();
        syncProofSection();
        bindStatusRow();
        bindHeroLibraryAccess();
    }

    var originalSync = window.bbaiSyncDashboardStateRoot;
    window.bbaiSyncDashboardStateRoot = function (update) {
        var result = originalSync ? originalSync(update) : null;
        refresh();
        return result;
    };
    window.bbaiGetDashboardActionableState = function (root) {
        var target = root || getRoot();

        if (!target) {
            return {
                key: ACTIONABLE_STATES.COMPLETE,
                actionableCount: 0,
                missingCount: 0,
                reviewCount: 0,
                optimizedCount: 0,
                totalCount: 0,
                percentage: 0
            };
        }

        return getActionableState(target, getCounts(target));
    };

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest
            ? event.target.closest('[data-action="scan-opportunity"], [data-bbai-action="scan-opportunity"]')
            : null;
        var root = getRoot();

        if (!trigger || trigger.hasAttribute('disabled') || !root) {
            return;
        }

        setScanInProgress(root, true);
        refresh();
    });

    document.addEventListener('bbai:scan:started', function () {
        setScanInProgress(getRoot(), true);
        refresh();
    });
    document.addEventListener('bbai:scan:complete', function () {
        setScanInProgress(getRoot(), false);
        refresh();
    });
    document.addEventListener('bbai:scan:failed', function () {
        setScanInProgress(getRoot(), false);
        refresh();
    });
    document.addEventListener('bbai:stats-updated', refresh);
    document.addEventListener('bbai:generation:started', refresh);
    document.addEventListener('bbai:generation:progress', refresh);
    document.addEventListener('bbai:generation:complete', refresh);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            refresh();
            maybeRevealLibraryGateFromQuery();
        });
    } else {
        refresh();
        maybeRevealLibraryGateFromQuery();
    }
})();
