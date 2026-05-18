(function () {
    'use strict';

    if (window.BBAI_DASHBOARD_STATE_STORE) {
        return;
    }

    window.BBAI_DASHBOARD_STATE_ENDPOINT_ACTIVE = true;

    // If the logged-in dashboard truth system is present, do not poll /dashboard-state on the dashboard page.
    // The hero/state-truth renderer owns dashboard polling in that case.
    function hasStateTruthDashboard() {
        return !!document.querySelector('[data-bbai-li-state-truth-url]');
    }

    function normalizeUserFromGlobals() {
        var ajaxUser = window.bbai_ajax && window.bbai_ajax.user_data && typeof window.bbai_ajax.user_data === 'object'
            ? window.bbai_ajax.user_data
            : null;

        window.bbaiUser = window.bbaiUser && typeof window.bbaiUser === 'object' ? window.bbaiUser : {};
        if (!window.bbaiUser.email && window.bbai_ajax && window.bbai_ajax.is_authenticated === true && ajaxUser && ajaxUser.email) {
            window.bbaiUser.email = String(ajaxUser.email || '');
            window.bbaiUser.id = ajaxUser.id || ajaxUser._id || ajaxUser.user_id || window.bbaiUser.id || '';
        }
        if (window.bbai_ajax && window.bbai_ajax.is_authenticated === false) {
            window.bbaiUser = {};
        }
        return window.bbaiUser;
    }

    function isUserAuthenticated() {
        normalizeUserFromGlobals();
        var root = getDashboardRoot();
        var mode = (root && root.getAttribute) ? String(root.getAttribute('data-bbai-auth-mode') || '') : '';
        mode = mode || String(window.BBAI_AUTH_MODE || '');
        if (mode === 'guest_logged_out') {
            return false;
        }
        return !!window.bbaiUser && !!window.bbaiUser.email;
    }

    window.isUserAuthenticated = isUserAuthenticated;

    function isAuthResolved() {
        var root = getDashboardRoot();
        normalizeUserFromGlobals();
        return !!(
            window.bbaiAuthResolved === true ||
            (window.bbai_ajax && typeof window.bbai_ajax.is_authenticated !== 'undefined') ||
            (window.bbaiUser && typeof window.bbaiUser.email !== 'undefined') ||
            (root && root.hasAttribute('data-bbai-has-connected-account'))
        );
    }

    // Global stabilizer (also used by the logged-in hero script).
    window.BBAI_DASHBOARD_STATE_STABILIZER = window.BBAI_DASHBOARD_STATE_STABILIZER || (function () {
        var STATE_PRIORITY = {
            needs_alt: 1,
            ready_to_generate: 2,
            queued: 3,
            review_ready: 4,
            complete: 5
        };

        function prio(key) { return STATE_PRIORITY[String(key || '')] || 0; }

        var ctrl = {
            currentState: '',
            pendingState: null,
            lastStateHash: '',
            stableCount: 0,
            isGenerating: false,
            debounceTimer: null
        };

        function devLog(msg, detail) {
            if (!window.BBAI_LOG || typeof window.BBAI_LOG.info !== 'function' || !window.console || typeof window.console.debug !== 'function') return;
            window.console.debug('[bbai-dashboard-stabilizer]', msg, detail || {});
        }

        ctrl.receive = function (next) {
            var n = next && typeof next === 'object' ? next : {};
            var key = String(n.key || '');
            var hash = String(n.hash || '');
            var apply = typeof n.apply === 'function' ? n.apply : null;
            var generating = !!n.generating;
            var reason = String(n.reason || '');

            if (!key || !hash || !apply) return false;

            devLog('State received', { key: key, reason: reason });

            if (ctrl.isGenerating) {
                if (generating) {
                    devLog('State ignored (generating freeze)', { key: key, reason: reason });
                    return false;
                }
                ctrl.isGenerating = false;
                window.bbaiIsGenerating = false;
            }

            if (ctrl.currentState && prio(key) < prio(ctrl.currentState)) {
                devLog('State rejected (regression)', { from: ctrl.currentState, to: key, reason: reason });
                return false;
            }

            if (ctrl.lastStateHash === hash) ctrl.stableCount += 1;
            else { ctrl.lastStateHash = hash; ctrl.stableCount = 1; }

            if (ctrl.stableCount < 2) {
                devLog('State ignored (unstable)', { key: key, reason: reason, stableCount: ctrl.stableCount });
                return false;
            }

            ctrl.pendingState = { key: key, hash: hash, apply: apply, generating: generating, reason: reason };
            if (ctrl.debounceTimer) { window.clearTimeout(ctrl.debounceTimer); ctrl.debounceTimer = null; }
            ctrl.debounceTimer = window.setTimeout(function () {
                var pending = ctrl.pendingState;
                ctrl.debounceTimer = null;
                if (!pending || pending.hash !== hash) return;
                ctrl.pendingState = null;
                ctrl.currentState = pending.key;
                if (pending.generating) { ctrl.isGenerating = true; window.bbaiIsGenerating = true; }
                devLog('State applied', { key: pending.key, reason: pending.reason });
                try { pending.apply(); } catch (e) {}
            }, 650);

            return true;
        };

        return ctrl;
    })();

    function getRestRoot() {
        var root = (
            (window.BBAI && window.BBAI.restRoot) ||
            (window.wpApiSettings && window.wpApiSettings.root) ||
            ''
        );
        root = String(root || '');
        return root ? root.replace(/\/?$/, '/') : '';
    }

    function getNonce() {
        return (
            (window.wpApiSettings && window.wpApiSettings.nonce) ||
            (window.BBAI && window.BBAI.nonce) ||
            ''
        );
    }

    function getEndpoint() {
        var root = getRestRoot();
        return root ? root + 'bbai/v1/dashboard-state' : '';
    }

    function safeInt(v) {
        var n = parseInt(v, 10);
        return isNaN(n) ? 0 : n;
    }

    function clampNonNeg(v) {
        v = safeInt(v);
        return v < 0 ? 0 : v;
    }

    function getDashboardRoot() {
        return document.querySelector('[data-bbai-dashboard-root="1"]');
    }

    function readStateFromDom() {
        var root = getDashboardRoot();
        if (!root) {
            return null;
        }

        var used = safeInt(root.getAttribute('data-bbai-credits-used'));
        var limit = Math.max(1, safeInt(root.getAttribute('data-bbai-credits-total')));
        var remaining = safeInt(root.getAttribute('data-bbai-credits-remaining'));

        return {
            missing: safeInt(root.getAttribute('data-bbai-missing-count')),
            review: safeInt(root.getAttribute('data-bbai-weak-count')),
            optimized: safeInt(root.getAttribute('data-bbai-optimized-count')),
            credits: {
                used: used,
                limit: limit,
                remaining: remaining
            },
            generation: {
                in_progress: root.getAttribute('data-bbai-generation-in-progress') === '1',
                queue_total: safeInt(root.getAttribute('data-bbai-generation-queue-total')),
                queue_remaining: safeInt(root.getAttribute('data-bbai-generation-queue-remaining'))
            }
        };
    }

    function normalizeComparableState(next) {
        next = next && typeof next === 'object' ? next : {};
        var counts = next.counts || {};
        var credits = next.credits || {};
        var gen = next.generation || {};

        return {
            missing: clampNonNeg(counts.missing),
            review: clampNonNeg(counts.needs_review),
            optimized: clampNonNeg(counts.optimized),
            credits: {
                used: clampNonNeg(credits.used),
                limit: Math.max(1, clampNonNeg(credits.limit)),
                remaining: clampNonNeg(credits.remaining)
            },
            generation: {
                in_progress: !!gen.in_progress,
                queue_total: clampNonNeg(gen.queue_total),
                queue_remaining: clampNonNeg(gen.queue_remaining)
            }
        };
    }

    function isSameState(a, b) {
        if (!a || !b) return false;
        return (
            a.missing === b.missing &&
            a.review === b.review &&
            a.optimized === b.optimized &&
            a.credits.used === b.credits.used &&
            a.credits.limit === b.credits.limit &&
            a.credits.remaining === b.credits.remaining &&
            a.generation.in_progress === b.generation.in_progress &&
            a.generation.queue_total === b.generation.queue_total &&
            a.generation.queue_remaining === b.generation.queue_remaining
        );
    }

    var hasHydrated = false;
    var prevComparable = readStateFromDom();

    function setAttrIfChanged(el, name, value) {
        value = String(value);
        if (!el) return;
        if (el.getAttribute(name) !== value) {
            el.setAttribute(name, value);
        }
    }

    function applyStateToRoot(state) {
        var root = getDashboardRoot();
        if (!root || !state || typeof state !== 'object') {
            return;
        }

        var counts = state.counts || {};
        var credits = state.credits || {};
        var gen = state.generation || {};

        var missing = clampNonNeg(counts.missing);
        var review = clampNonNeg(counts.needs_review);
        var optimized = clampNonNeg(counts.optimized);
        var total = missing + review + optimized;

        setAttrIfChanged(root, 'data-bbai-missing-count', missing);
        setAttrIfChanged(root, 'data-bbai-weak-count', review);
        setAttrIfChanged(root, 'data-bbai-optimized-count', optimized);
        setAttrIfChanged(root, 'data-bbai-total-count', total);
        setAttrIfChanged(root, 'data-bbai-generated-count', optimized);
        setAttrIfChanged(root, 'data-bbai-has-scan-results', total > 0 ? '1' : '0');

        setAttrIfChanged(root, 'data-bbai-credits-used', clampNonNeg(credits.used));
        setAttrIfChanged(root, 'data-bbai-credits-total', Math.max(1, clampNonNeg(credits.limit)));
        setAttrIfChanged(root, 'data-bbai-credits-remaining', clampNonNeg(credits.remaining));
        setAttrIfChanged(root, 'data-bbai-has-credit', credits.has_credit ? '1' : '0');

        setAttrIfChanged(root, 'data-bbai-generation-in-progress', gen.in_progress ? '1' : '0');
        setAttrIfChanged(root, 'data-bbai-generation-queue-total', clampNonNeg(gen.queue_total));
        setAttrIfChanged(root, 'data-bbai-generation-queue-remaining', clampNonNeg(gen.queue_remaining));
    }

    function applyStateToLibraryFilters(state) {
        var ws = document.querySelector('[data-bbai-library-workspace-root="1"]');
        if (!ws) {
            return;
        }
        var counts = state && state.counts ? state.counts : {};
        var missing = clampNonNeg(counts.missing);
        var review = clampNonNeg(counts.needs_review);
        var optimized = clampNonNeg(counts.optimized);
        var all = missing + review + optimized;

        var group = document.getElementById('bbai-review-filter-tabs');
        if (!group) {
            return;
        }

        function setCount(filterKey, value) {
            var btn = group.querySelector('button[data-filter="' + filterKey + '"]');
            if (!btn) return;
            var countEl = btn.querySelector('.bbai-filter-group__count');
            if (!countEl) return;
            var nextText = String(value.toLocaleString());
            if (countEl.textContent !== nextText) {
                countEl.textContent = nextText;
            }
        }

        setCount('all', all);
        setCount('missing', missing);
        setCount('weak', review);
        setCount('optimized', optimized);
    }

    function applyGenerationDisabled(state) {
        var gen = state && state.generation ? state.generation : {};
        var inProgress = !!gen.in_progress;
        var buttons = document.querySelectorAll('[data-action="generate-missing"], [data-bbai-li-action="generate-missing"]');
        Array.prototype.forEach.call(buttons, function (btn) {
            if (!btn) return;
            if (inProgress) {
                if (!btn.disabled) {
                    btn.setAttribute('disabled', 'disabled');
                }
                if (btn.getAttribute('aria-disabled') !== 'true') {
                    btn.setAttribute('aria-disabled', 'true');
                }
            } else {
                if (btn.disabled) {
                    btn.removeAttribute('disabled');
                }
                if (btn.getAttribute('aria-disabled') === 'true' && !btn.hasAttribute('data-bbai-locked-cta')) {
                    btn.setAttribute('aria-disabled', 'false');
                }
            }
        });
    }

    function dispatch(state) {
        try {
            window.dispatchEvent(new CustomEvent('bbai:dashboard-state', { detail: state }));
        } catch (e) {
            /* ignore */
        }
    }

    // Note: requests are coordinated through coordinatedFetch() below.

    var state = null;
    var listeners = [];

    // Global request coordinator (deduped, non-overlapping).
    var req = window.bbaiDashboardStateRequest = window.bbaiDashboardStateRequest || {
        inFlight: false,
        promise: null,
        controller: null,
        sequence: 0,
        latestAppliedSequence: 0,
        lastStartedAt: 0,
        lastCompletedAt: 0,
        lastReason: null,
        lastSignature: null
    };

    var inFlightPromise = null; // legacy local alias (kept for minimal diff)
    var pollTimer = null;
    var pollIntervalMs = 15000;
    var authResolved = false;

    function logDebug(eventName, payload) {
        if (!window.BBAI_LOG || typeof window.BBAI_LOG.info !== 'function' || !window.console || typeof window.console.debug !== 'function') {
            return;
        }
        var out = { event: eventName };
        if (payload && typeof payload === 'object') {
            Object.keys(payload).forEach(function (k) { out[k] = payload[k]; });
        }
        window.console.debug('[bbai-dashboard-state]', out);
    }

    function isDashboardVisible() {
        // Only poll/apply when a dashboard root exists and page is visible.
        return !!getDashboardRoot() && !document.hidden;
    }

    function resetDashboardStateController() {
        state = null;
        window.BBAI_STATE = null;
        window.bbaiGenerationResult = null;
        window.bbaiLastDashboardRenderSignature = null;
        window.bbaiPreviousDashboardMode = null;
        prevComparable = readStateFromDom();
        hasHydrated = false;
        req.lastSignature = null;
        req.latestAppliedSequence = 0;
        req.lastCompletedAt = 0;
        req.lastReason = null;
        if (req.controller) {
            try { req.controller.abort(); } catch (e) { /* ignore */ }
        }
        req.inFlight = false;
        req.promise = null;
        req.controller = null;
        inFlightPromise = null;

        if (window.BBAI_DASHBOARD_STATE_STABILIZER) {
            window.BBAI_DASHBOARD_STATE_STABILIZER.currentState = '';
            window.BBAI_DASHBOARD_STATE_STABILIZER.pendingState = null;
            window.BBAI_DASHBOARD_STATE_STABILIZER.lastStateHash = null;
            window.BBAI_DASHBOARD_STATE_STABILIZER.stableCount = 0;
            window.BBAI_DASHBOARD_STATE_STABILIZER.isGenerating = false;
            if (window.BBAI_DASHBOARD_STATE_STABILIZER.debounceTimer) {
                window.clearTimeout(window.BBAI_DASHBOARD_STATE_STABILIZER.debounceTimer);
                window.BBAI_DASHBOARD_STATE_STABILIZER.debounceTimer = null;
            }
        }
    }

    function renderGuestDashboard() {
        var root = getDashboardRoot();
        stopPolling();
        resetDashboardStateController();
        window.bbaiIsGenerating = false;

        if (window.bbai_ajax) {
            window.bbai_ajax.is_authenticated = false;
            window.bbai_ajax.user_data = {};
        }
        window.bbaiUser = {};
        window.bbaiAuthResolved = true;

        if (!root) {
            return;
        }

        root.setAttribute('data-bbai-has-connected-account', '0');
        root.setAttribute('data-bbai-is-guest-trial', '1');
        root.setAttribute('data-bbai-auth-state', 'anonymous');
        root.setAttribute('data-bbai-quota-type', 'trial');
        root.setAttribute('data-bbai-dashboard-funnel', 'guest_dashboard');

        // Use the existing SSR guest funnel UI. Never inject a minimal fallback UI.
        if (
            root.getAttribute('data-bbai-dashboard-funnel') === 'guest_dashboard' ||
            (root.matches && root.matches('[data-bbai-dashboard-funnel="guest_dashboard"]')) ||
            root.querySelector('.bbai-guest-hero, [data-bbai-guest-dashboard-fallback="1"]')
        ) {
            root.hidden = false;
            root.removeAttribute('aria-busy');
            return;
        }

        // If we cannot find a guest funnel inside the dashboard root, do not replace UI.
        // Prefer the SSR output from PHP; this function should only reveal it and clear state.
        root.hidden = false;
        root.removeAttribute('aria-busy');
    }

    window.bbaiRenderGuestDashboard = renderGuestDashboard;
    window.bbaiResetDashboardStateController = resetDashboardStateController;

    function showLoadingSkeleton() {
        // Never allow a blank screen: unhide any dashboard shell we can find.
        var root = getDashboardRoot();
        var loggedIn = document.querySelector('[data-bbai-logged-in-dashboard]');
        if (loggedIn) {
            loggedIn.hidden = false;
            loggedIn.removeAttribute('aria-hidden');
            if (loggedIn.style && loggedIn.style.display === 'none') {
                loggedIn.style.display = '';
            }
        }
        if (root) {
            root.hidden = false;
            root.removeAttribute('aria-hidden');
            root.setAttribute('aria-busy', 'true');
            if (root.style && root.style.display === 'none') {
                root.style.display = '';
            }
        }
    }

    function resolveAuth() {
        return new Promise(function (resolve) {
            try {
                var root = getDashboardRoot();
                var mode = (root && root.getAttribute) ? String(root.getAttribute('data-bbai-auth-mode') || '') : '';
                mode = mode || String(window.BBAI_AUTH_MODE || '');
                if (mode === 'guest_logged_out') {
                    resolve(null);
                    return;
                }
                if (window.bbaiUser && window.bbaiUser.email) {
                    resolve(window.bbaiUser);
                    return;
                }
            } catch (e) { /* ignore */ }
            resolve(null);
        });
    }

    function renderAuthenticatedDashboard(user) {
        // Show SSR logged-in dashboard markup and start refresh if available.
        var loggedIn = document.querySelector('[data-bbai-logged-in-dashboard]');
        var root = getDashboardRoot();
        window.bbaiIsGenerating = false;
        if (root) {
            root.removeAttribute('aria-busy');
            root.hidden = false;
            if (root.style && root.style.display === 'none') root.style.display = '';
        }
        if (loggedIn) {
            loggedIn.hidden = false;
            loggedIn.removeAttribute('aria-hidden');
            if (loggedIn.style && loggedIn.style.display === 'none') loggedIn.style.display = '';
        }
        // Trigger one refresh path (truth system owns polling on dashboard).
        if (typeof window.bbaiRefreshLoggedInDashboardTruth === 'function') {
            window.bbaiRefreshLoggedInDashboardTruth('startup').catch(function () {});
        }
        if (root) {
            root.setAttribute('data-bbai-has-connected-account', '1');
        }
        window.bbaiAuthResolved = true;
        window.bbaiUser = user || window.bbaiUser || {};
    }

    function initDashboard() {
        showLoadingSkeleton();
        resolveAuth().then(function (user) {
            try {
                if (user) {
                    renderAuthenticatedDashboard(user);
                    return;
                }
                renderGuestDashboard();
            } catch (e) {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('Dashboard failed:', e);
                }
                try { renderGuestDashboard(); } catch (ignored) {}
            }
        }).catch(function (e) {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('Dashboard failed:', e);
            }
            try { renderGuestDashboard(); } catch (ignored2) {}
        });
    }

    window.bbaiInitDashboard = initDashboard;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
    } else {
        initDashboard();
    }

    function getGenerationRunningSnapshot() {
        // Prefer last known state; fall back to SSR DOM.
        try {
            var gen = state && state.generation ? state.generation : null;
            if (gen && typeof gen.in_progress !== 'undefined') {
                return !!gen.in_progress;
            }
        } catch (e) { /* ignore */ }
        var dom = readStateFromDom();
        return !!(dom && dom.generation && dom.generation.in_progress);
    }

    function shouldDedupe(reason) {
        var now = Date.now();
        var recent = req.lastCompletedAt && (now - req.lastCompletedAt) < 3000;
        if (!recent) return false;

        // Only dedupe low-signal reasons.
        return (
            reason === 'poll' ||
            reason === 'focus' ||
            reason === 'visibility' ||
            reason === 'bootstrap'
        );
    }

    function buildSignature(next) {
        var c = normalizeComparableState(next);
        // Include total and mode-ish bits to avoid replaying UI work.
        var total = c.missing + c.review + c.optimized;
        return [
            c.missing, c.review, c.optimized, total,
            c.credits.used, c.credits.limit, c.credits.remaining,
            c.generation.in_progress ? 1 : 0,
            c.generation.queue_total, c.generation.queue_remaining
        ].join('|');
    }

    function mapPayloadToStabilizedKey(next) {
        var gen = next && next.generation ? next.generation : {};
        if (gen && gen.in_progress) return 'queued';
        var counts = next && next.counts ? next.counts : {};
        var missing = clampNonNeg(counts.missing);
        var review = clampNonNeg(counts.needs_review);
        if (review > 0) return 'review_ready';
        if (missing > 0) return 'needs_alt';
        return 'complete';
    }

    function notify(next) {
        listeners.slice().forEach(function (fn) {
            try { fn(next); } catch (e) { /* ignore */ }
        });
    }

    function applyAll(next) {
        var comparable = normalizeComparableState(next);

        if (!isUserAuthenticated() && next && next.plan !== 'guest') {
            logDebug('blocked_authenticated_state_for_guest', { endpoint: 'dashboard-state' });
            renderGuestDashboard();
            return;
        }

        if (!hasHydrated) {
            hasHydrated = true;
            // First client hydration: only patch if different from SSR DOM.
            if (prevComparable && isSameState(prevComparable, comparable)) {
                prevComparable = comparable;
                return;
            }
        } else {
            if (prevComparable && isSameState(prevComparable, comparable)) {
                return;
            }
        }

        prevComparable = comparable;
        applyStateToRoot(next);
        applyStateToLibraryFilters(next);
        applyGenerationDisabled(next);
        dispatch(next);
    }

    function coordinatedFetch(reason) {
        reason = String(reason || 'poll');

        if (!getDashboardRoot()) {
            return Promise.resolve(state);
        }

        if (!isAuthResolved()) {
            logDebug('dashboard_state_fetch_skipped', { reason: reason, endpoint: 'dashboard-state', because: 'auth_unresolved' });
            return Promise.resolve(state);
        }

        if (!isUserAuthenticated()) {
            logDebug('dashboard_state_fetch_skipped', { reason: reason, endpoint: 'dashboard-state', because: 'guest' });
            renderGuestDashboard();
            return Promise.resolve(state);
        }

        if (req.inFlight && (req.promise || inFlightPromise)) {
            logDebug('skip_dashboard_state_fetch_in_flight', { reason: reason, lastReason: req.lastReason || '' });
            return req.promise || inFlightPromise;
        }

        if (shouldDedupe(reason)) {
            logDebug('skip_dashboard_state_fetch_dedupe', { reason: reason });
            return Promise.resolve(state);
        }

        var url = getEndpoint();
        if (!url) {
            return Promise.reject(new Error('Missing REST root'));
        }
        url = appendIdentityToUrl(url);

        req.sequence += 1;
        var sequence = req.sequence;
        req.inFlight = true;
        req.lastStartedAt = Date.now();
        req.lastReason = reason;

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        req.controller = controller;

        var timeoutMs = getGenerationRunningSnapshot() ? 25000 : 15000;
        var timeoutId = null;
        if (controller) {
            timeoutId = window.setTimeout(function () {
                logDebug('dashboard_state_fetch_slow', {
                    reason: reason,
                    sequence: sequence,
                    durationMs: timeoutMs,
                    endpoint: 'dashboard-state'
                });
            }, timeoutMs);
        }

        logDebug('dashboard_state_fetch_start', { reason: reason, sequence: sequence, durationMs: 0, endpoint: 'dashboard-state' });

        inFlightPromise = req.promise = window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: { 'X-WP-Nonce': getNonce() }
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(function (next) {
            if (timeoutId) window.clearTimeout(timeoutId);
            req.inFlight = false;
            req.controller = null;
            req.lastCompletedAt = Date.now();
            if (reason !== 'poll' && reason !== 'focus' && reason !== 'visibility') {
                window.bbaiLastSuccessfulDashboardRefreshAt = req.lastCompletedAt;
            }
            logDebug('dashboard_state_fetch_complete', {
                reason: reason,
                sequence: sequence,
                durationMs: Math.max(0, req.lastCompletedAt - req.lastStartedAt),
                endpoint: 'dashboard-state'
            });

            if (!responseMatchesCurrentIdentity(next)) {
                logDebug('ignore_mismatched_dashboard_state_response', { sequence: sequence, endpoint: 'dashboard-state' });
                return state;
            }

            // Ignore stale responses.
            if (sequence <= req.latestAppliedSequence) {
                logDebug('ignore_stale_dashboard_state_response', { sequence: sequence, latestAppliedSequence: req.latestAppliedSequence });
                return state;
            }

            req.latestAppliedSequence = sequence;
            state = next;

            // Idempotent apply (signature-based).
            var sig = buildSignature(next);
            if (req.lastSignature && req.lastSignature === sig) {
                logDebug('skip_unchanged_dashboard_render', { reason: reason, sequence: sequence, endpoint: 'dashboard-state' });
                return next;
            }
            req.lastSignature = sig;

            // Stabilize updates (no flicker/regressions).
            var stabilizer = window.BBAI_DASHBOARD_STATE_STABILIZER;
            var key = mapPayloadToStabilizedKey(next);
            var generating = key === 'queued';
            var accepted = stabilizer && typeof stabilizer.receive === 'function'
                ? stabilizer.receive({
                    key: key,
                    hash: sig,
                    reason: reason,
                    generating: generating,
                    apply: function () {
                        applyAll(next);
                        notify(next);
                    }
                })
                : false;

            if (!accepted) {
                // Still notify subscribers with raw state, but do not repaint DOM.
                notify(next);
            }
            return next;
        }).catch(function (err) {
            if (timeoutId) window.clearTimeout(timeoutId);
            // Abort/timeout should not blank UI; just release inFlight and move on.
            req.inFlight = false;
            req.controller = null;
            req.lastCompletedAt = Date.now();
            if (controller && controller.signal && controller.signal.aborted) {
                logDebug('dashboard_state_fetch_timeout', {
                    reason: reason,
                    sequence: sequence,
                    durationMs: Math.max(0, req.lastCompletedAt - req.lastStartedAt),
                    endpoint: 'dashboard-state'
                });
            }
            logDebug('dashboard_state_fetch_failed', { reason: reason, message: String(err && err.message ? err.message : err) });
            return Promise.reject(err);
        }).finally(function () {
            inFlightPromise = null;
            req.promise = null;
        });

        return inFlightPromise;
    }

    function getCurrentUserIdForRequest() {
        normalizeUserFromGlobals();
        return String(
            (window.bbaiUser && (window.bbaiUser.id || window.bbaiUser.user_id || window.bbaiUser._id)) ||
            (window.bbai_ajax && window.bbai_ajax.user_data && (window.bbai_ajax.user_data.id || window.bbai_ajax.user_data.user_id || window.bbai_ajax.user_data._id)) ||
            ''
        );
    }

    function getCurrentSiteHashForRequest() {
        return String(
            (window.BBAI_POSTHOG && window.BBAI_POSTHOG.context && window.BBAI_POSTHOG.context.site_hash) ||
            (window.BBAI_TELEMETRY && window.BBAI_TELEMETRY.context && window.BBAI_TELEMETRY.context.site_hash) ||
            ''
        );
    }

    function appendIdentityToUrl(url) {
        var userId = getCurrentUserIdForRequest();
        var siteHash = getCurrentSiteHashForRequest();
        var glue = url.indexOf('?') === -1 ? '?' : '&';
        var params = [];
        if (userId) {
            params.push('user_id=' + encodeURIComponent(userId));
        }
        if (siteHash) {
            params.push('site_hash=' + encodeURIComponent(siteHash));
        }
        return params.length ? url + glue + params.join('&') : url;
    }

    function responseMatchesCurrentIdentity(next) {
        var meta = next && typeof next === 'object' ? (next.meta || next.site || next.auth || {}) : {};
        var responseUser = String(meta.user_id || meta.userId || '');
        var responseSite = String(meta.site_hash || meta.siteHash || '');
        var currentUser = getCurrentUserIdForRequest();
        var currentSite = getCurrentSiteHashForRequest();

        if (responseUser && currentUser && responseUser !== currentUser) {
            return false;
        }
        if (responseSite && currentSite && responseSite !== currentSite) {
            return false;
        }
        return true;
    }

    function schedulePolling() {
        if (pollTimer) return;
        pollTimer = window.setInterval(function () {
            if (!isDashboardVisible()) {
                return;
            }
            if (window.bbaiIsGenerating === true) {
                return;
            }
            coordinatedFetch('poll').catch(function () { /* ignore */ });
        }, pollIntervalMs);
    }

    function stopPolling() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function adjustPollingInterval() {
        var genRunning = getGenerationRunningSnapshot();
        var next = genRunning ? 3000 : 15000;
        if (next === pollIntervalMs) return;
        pollIntervalMs = next;
        stopPolling();
        if (!document.hidden) {
            schedulePolling();
        }
    }

    window.BBAI_DASHBOARD_STATE_STORE = {
        getState: function () { return state; },
        refresh: function (reason) { return coordinatedFetch(reason || 'manual'); },
        subscribe: function (fn) {
            if (typeof fn !== 'function') return function () {};
            listeners.push(fn);
            if (state) {
                try { fn(state); } catch (e) { /* ignore */ }
            }
            return function () {
                listeners = listeners.filter(function (x) { return x !== fn; });
            };
        }
    };

    window.bbaiRequestDashboardStateRefresh = function (reason) {
        reason = String(reason || 'manual');
        // Only bypass dedupe for high-signal reasons.
        if (reason === 'generation_completed' || reason === 'manual_rescan' || reason === 'user_action') {
            req.lastCompletedAt = 0;
        }
        if (hasStateTruthDashboard()) {
            // On the dashboard page, state-truth owns polling; avoid extra /dashboard-state fetches.
            logDebug('dashboard_state_fetch_skipped', { reason: reason, endpoint: 'dashboard-state', because: 'state-truth-dashboard' });
            return Promise.resolve(state);
        }
        return coordinatedFetch(reason);
    };

    function startWhenAuthResolved() {
        if (!getDashboardRoot() || hasStateTruthDashboard()) {
            return;
        }
        resetDashboardStateController();
        authResolved = isAuthResolved();
        if (!authResolved) {
            window.setTimeout(startWhenAuthResolved, 50);
            return;
        }
        if (!isUserAuthenticated()) {
            renderGuestDashboard();
            return;
        }
        coordinatedFetch('bootstrap').catch(function () { /* ignore */ });
        if (!document.hidden) {
            schedulePolling();
        }
    }

    // Initial fetch + polling (dashboard-visible only), unless state-truth dashboard owns polling.
    if (getDashboardRoot() && !hasStateTruthDashboard()) {
        startWhenAuthResolved();
    }

    document.addEventListener('visibilitychange', function () {
        if (hasStateTruthDashboard()) { return; }
        if (document.hidden) {
            stopPolling();
            return;
        }
        // One refresh on resume, then restart polling.
        coordinatedFetch('visibility').catch(function () { /* ignore */ });
        adjustPollingInterval();
        schedulePolling();
    });

    window.addEventListener('focus', function () {
        if (hasStateTruthDashboard()) { return; }
        if (!isDashboardVisible()) return;
        coordinatedFetch('focus').catch(function () { /* ignore */ });
    });

    window.addEventListener('pagehide', function () {
        stopPolling();
        if (req.controller) {
            try { req.controller.abort(); } catch (e) { /* ignore */ }
        }
        req.inFlight = false;
        req.controller = null;
    });

    // Refresh aggressively after generation triggers.
    document.addEventListener('click', function (e) {
        if (hasStateTruthDashboard()) { return; }
        var trigger = e && e.target && e.target.closest
            ? e.target.closest('[data-action="generate-missing"], [data-bbai-li-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"]')
            : null;
        if (!trigger) {
            return;
        }
        window.setTimeout(function () { coordinatedFetch('user_action').catch(function () { /* ignore */ }); }, 500);
        window.setTimeout(function () { coordinatedFetch('user_action').catch(function () { /* ignore */ }); }, 2500);
    }, true);

    // Speed up polling while generation is running.
    window.addEventListener('bbai:dashboard-state', function () {
        if (hasStateTruthDashboard()) { return; }
        adjustPollingInterval();
    });

    window.addEventListener('bbai_logout', function () {
        renderGuestDashboard();
    });
}());
