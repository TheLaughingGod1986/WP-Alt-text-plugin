(function () {
    'use strict';

    if (window.BBAI_DASHBOARD_STATE_STORE) {
        return;
    }

    window.BBAI_DASHBOARD_STATE_ENDPOINT_ACTIVE = true;

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

        // For logged-out guest trials: mirror credits into trial-specific attrs
        // so that funnel-state.js reads a consistent remaining count regardless
        // of which attribute it checks.
        var isGuestTrial = root.getAttribute('data-bbai-is-guest-trial') === '1' &&
            root.getAttribute('data-bbai-has-connected-account') !== '1';
        if (isGuestTrial) {
            var guestRemaining = clampNonNeg(credits.remaining);
            setAttrIfChanged(root, 'data-bbai-trial-remaining', guestRemaining);
            setAttrIfChanged(root, 'data-bbai-trial-used', clampNonNeg(credits.used));
            if (guestRemaining <= 0) {
                setAttrIfChanged(root, 'data-bbai-trial-exhausted', '1');
                setAttrIfChanged(root, 'data-bbai-quota-state', 'exhausted');
                setAttrIfChanged(root, 'data-bbai-signup-required', '1');
            }
        }
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

    function fetchState() {
        var url = getEndpoint();
        if (!url) {
            return Promise.reject(new Error('Missing REST root'));
        }
        return window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': getNonce()
            }
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        });
    }

    var state = null;
    var listeners = [];
    var pollTimer = null;
    var inflight = null;

    function notify(next) {
        listeners.slice().forEach(function (fn) {
            try { fn(next); } catch (e) { /* ignore */ }
        });
    }

    function applyAll(next) {
        var comparable = normalizeComparableState(next);

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

    function refresh() {
        if (inflight) {
            return inflight;
        }
        inflight = fetchState().then(function (next) {
            inflight = null;
            state = next;
            applyAll(next);
            notify(next);
            return next;
        }).catch(function (err) {
            inflight = null;
            return Promise.reject(err);
        });
        return inflight;
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = window.setInterval(function () {
            refresh().catch(function () { /* ignore */ });
        }, 5000);
    }

    window.BBAI_DASHBOARD_STATE_STORE = {
        getState: function () { return state; },
        refresh: refresh,
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

    // Initial fetch + polling.
    refresh().catch(function () { /* ignore */ });
    startPolling();

    // Refresh aggressively after generation triggers.
    document.addEventListener('click', function (e) {
        var trigger = e && e.target && e.target.closest
            ? e.target.closest('[data-action="generate-missing"], [data-bbai-li-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"]')
            : null;
        if (!trigger) {
            return;
        }
        window.setTimeout(function () { refresh().catch(function () { /* ignore */ }); }, 500);
        window.setTimeout(function () { refresh().catch(function () { /* ignore */ }); }, 2500);
    }, true);
}());

