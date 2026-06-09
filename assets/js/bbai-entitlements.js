/**
 * Canonical frontend entitlement state bridge.
 *
 * Keeps quota-sensitive controls aligned with additive backend
 * `entitlement_state` payloads while preserving existing action contracts.
 */
(function (window, document) {
    'use strict';

    var state = null;
    var subscribers = [];
    var analyticsSeen = {};
    var conflictReported = false;
    var refreshRequest = null;
    var generationSelector = [
        '[data-action="generate-missing"]',
        '[data-action="generate-selected"]',
        '[data-action="regenerate-all"]',
        '[data-action="regenerate-selected"]',
        '[data-action="regenerate-single"]',
        '[data-action="phase17-improve-alt"]',
        '[data-bbai-nai-cta="start-pass"]',
        '[data-bbai-action="generate_missing"]',
        '[data-bbai-action="reoptimize_all"]'
    ].join(', ');

    function object(value) {
        return value && typeof value === 'object' && !Array.isArray(value) ? value : null;
    }

    function number(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? null : parsed;
    }

    function bool(value, fallback) {
        return typeof value === 'boolean' ? value : fallback;
    }

    function isPaidPlan(plan) {
        plan = String(plan || '').toLowerCase();
        return plan === 'pro' || plan === 'growth' || plan === 'agency' || plan === 'enterprise';
    }

    function normalizePlanForLimit(plan, limit) {
        plan = String(plan || 'free').toLowerCase();
        if (isPaidPlan(plan) && limit < 1000) {
            return 'free';
        }
        return plan || 'free';
    }

    function applyDashboardRootCreditFloor(next) {
        var root = document.querySelector('[data-bbai-dashboard-root][data-bbai-credits-used]');
        var rootUsed;
        var rootLimit;
        var rootRemaining;
        var dailyLimit;

        if (!root || root.getAttribute('data-bbai-is-premium') === '1') {
            return next;
        }

        rootUsed = number(root.getAttribute('data-bbai-credits-used'));
        rootLimit = number(root.getAttribute('data-bbai-credits-total'));
        rootRemaining = number(root.getAttribute('data-bbai-credits-remaining'));
        if (rootUsed === null || rootLimit === null || rootLimit < 50 || rootUsed <= next.tokens_used_this_month) {
            return next;
        }

        next = Object.assign({}, next);
        next.tokens_used_this_month = rootUsed;
        next.token_limit = rootLimit;
        next.tokens_remaining = rootRemaining === null ? Math.max(0, rootLimit - rootUsed) : rootRemaining;
        dailyLimit = next.daily_generation_limit === null ? 5 : next.daily_generation_limit;
        next.daily_generation_limit = dailyLimit;
        next.daily_generations_used = Math.min(dailyLimit, rootUsed);
        next.daily_generations_remaining = Math.max(0, dailyLimit - next.daily_generations_used);
        return next;
    }

    function copyPublicFields(input) {
        var next = {};
        var allowed = [
            'plan', 'plan_type', 'token_limit', 'tokens_used_this_month',
            'total_tokens_used', 'tokens_remaining', 'can_generate',
            'daily_generation_limit', 'daily_generations_used',
            'daily_generations_remaining', 'daily_reset_date',
            'can_autopilot', 'is_logged_in', 'is_trial', 'is_unlimited',
            'reset_date', 'last_generation_at', 'upgrade_required',
            'quota_state', 'message'
        ];

        allowed.forEach(function (key) {
            if (input[key] !== undefined && input[key] !== null) {
                next[key] = input[key];
            }
        });
        return next;
    }

    function publicFieldsOrNull(input) {
        var source = object(input);
        var fields;
        if (!source) {
            return null;
        }
        fields = copyPublicFields(source);
        return Object.keys(fields).length ? fields : null;
    }

    function fromLegacy(raw) {
        var input = object(raw);
        var quota;
        var limit;
        var used;
        var remaining;
        var plan;

        if (!input) {
            return null;
        }
        quota = object(input.quota) || {};
        limit = number(input.token_limit);
        if (limit === null) limit = number(input.limit);
        if (limit === null) limit = number(input.credits_total);
        if (limit === null) limit = number(quota.limit);
        used = number(input.tokens_used_this_month);
        if (used === null) used = number(input.used);
        if (used === null) used = number(input.credits_used);
        if (used === null) used = number(quota.used);
        remaining = number(input.tokens_remaining);
        if (remaining === null) remaining = number(input.remaining);
        if (remaining === null) remaining = number(input.credits_remaining);
        if (remaining === null) remaining = number(quota.remaining);

        if (limit === null && used === null && remaining === null && input.can_generate === undefined) {
            return null;
        }

        limit = Math.max(0, limit === null ? 0 : limit);
        used = Math.max(0, used === null ? 0 : used);
        remaining = Math.max(0, remaining === null ? Math.max(0, limit - used) : remaining);
        plan = normalizePlanForLimit(input.plan_type || input.plan || quota.plan_type || quota.plan || 'free', limit);

        return {
            plan: plan,
            plan_type: plan,
            token_limit: limit,
            tokens_used_this_month: used,
            total_tokens_used: Math.max(0, number(input.total_tokens_used) || used),
            tokens_remaining: remaining,
            daily_generation_limit: number(input.daily_generation_limit),
            daily_generations_used: number(input.daily_generations_used),
            daily_generations_remaining: number(input.daily_generations_remaining),
            daily_reset_date: String(input.daily_reset_date || ''),
            can_generate: bool(input.can_generate, remaining > 0 || isPaidPlan(plan)),
            can_autopilot: isPaidPlan(plan) ? bool(input.can_autopilot, true) : false,
            is_logged_in: bool(input.is_logged_in, String(input.auth_state || quota.auth_state || '') !== 'anonymous'),
            is_trial: bool(input.is_trial, plan === 'trial' || String(input.quota_type || quota.quota_type || '') === 'trial'),
            is_unlimited: isPaidPlan(plan) ? bool(input.is_unlimited, true) : false,
            reset_date: String(input.reset_date || quota.reset_date || ''),
            last_generation_at: String(input.last_generation_at || ''),
            upgrade_required: bool(input.upgrade_required, remaining <= 0 && !isPaidPlan(plan)),
            quota_state: String(input.quota_state || quota.quota_state || (remaining <= 0 ? 'exhausted' : 'active')),
            message: String(input.message || '')
        };
    }

    function normalize(raw) {
        var input = object(raw);
        var next;
        var remaining;
        var limit;
        var used;
        var plan;
        var unlimited;
        var dailyLimit;
        var dailyUsed;
        var dailyRemaining;

        if (!input) {
            return null;
        }
        next = copyPublicFields(input);
        remaining = number(next.tokens_remaining);
        limit = number(next.token_limit);
        used = number(next.tokens_used_this_month);
        plan = normalizePlanForLimit(next.plan_type || next.plan || 'free', limit === null ? 0 : limit);
        unlimited = bool(next.is_unlimited, isPaidPlan(plan));
        if (!isPaidPlan(plan)) {
            unlimited = false;
        }
        dailyLimit = number(next.daily_generation_limit);
        dailyUsed = number(next.daily_generations_used);
        dailyRemaining = number(next.daily_generations_remaining);

        next.plan = String(next.plan || plan);
        next.plan_type = plan;
        next.token_limit = Math.max(0, limit === null ? 0 : limit);
        next.tokens_used_this_month = Math.max(0, used === null ? 0 : used);
        next.total_tokens_used = Math.max(0, number(next.total_tokens_used) || next.tokens_used_this_month);
        next.tokens_remaining = Math.max(0, remaining === null ? Math.max(0, next.token_limit - next.tokens_used_this_month) : remaining);
        next.daily_generation_limit = dailyLimit === null ? null : Math.max(0, dailyLimit);
        next.daily_generations_used = dailyUsed === null ? null : Math.max(0, dailyUsed);
        next.daily_generations_remaining = dailyRemaining === null ? null : Math.max(0, dailyRemaining);
        next.daily_reset_date = String(next.daily_reset_date || '');
        next.is_unlimited = unlimited;
        next.can_generate = bool(next.can_generate, unlimited || next.tokens_remaining > 0);
        if (!unlimited && next.tokens_remaining <= 0) {
            next.can_generate = false;
        }
        if (!unlimited && next.daily_generations_remaining !== null && next.daily_generations_remaining <= 0) {
            next.can_generate = false;
        }
        next.can_autopilot = isPaidPlan(plan) ? bool(next.can_autopilot, unlimited) : false;
        next.is_logged_in = bool(next.is_logged_in, true);
        next.is_trial = bool(next.is_trial, plan === 'trial');
        next.upgrade_required = bool(next.upgrade_required, !unlimited && !next.can_generate);
        next.quota_state = String(next.quota_state || (!next.can_generate ? 'exhausted' : 'active'));
        next.reset_date = String(next.reset_date || '');
        next.last_generation_at = String(next.last_generation_at || '');
        next.message = String(next.message || '');
        return next;
    }

    function findEntitlement(payload, depth) {
        var value = object(payload);
        var keys;
        var i;
        var found;
        if (!value || depth > 5) {
            return null;
        }
        if (object(value.entitlement_state)) {
            return value.entitlement_state;
        }
        keys = ['data', 'result', 'job', 'payload', 'response', 'usage'];
        for (i = 0; i < keys.length; i++) {
            found = findEntitlement(value[keys[i]], depth + 1);
            if (found) {
                return found;
            }
        }
        return null;
    }

    function track(name, props, key) {
        var dedupeKey = key || name;
        if (analyticsSeen[dedupeKey]) {
            return;
        }
        analyticsSeen[dedupeKey] = true;
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: Object.assign({ event: name, source: 'entitlement_state' }, props || {})
            }));
        } catch (ignore) {}
    }

    function lockGenerationControl(node, locked) {
        if (!node || !node.setAttribute) {
            return;
        }
        if (locked) {
            if (node.getAttribute('data-bbai-entitlement-locked') !== '1') {
                node.setAttribute('data-bbai-entitlement-original-bbai-action', node.getAttribute('data-bbai-action') || '');
                node.setAttribute('data-bbai-entitlement-original-aria-disabled', node.getAttribute('aria-disabled') || '');
            }
            node.setAttribute('data-bbai-entitlement-locked', '1');
            node.setAttribute('data-bbai-action', 'open-upgrade');
            node.setAttribute('data-bbai-locked-cta', '1');
            node.setAttribute('data-bbai-lock-reason', 'quota_exhausted');
            node.setAttribute('aria-disabled', 'true');
            node.classList.add('bbai-is-locked');
            return;
        }
        if (node.getAttribute('data-bbai-entitlement-locked') !== '1') {
            return;
        }
        var originalAction = node.getAttribute('data-bbai-entitlement-original-bbai-action') || '';
        var originalDisabled = node.getAttribute('data-bbai-entitlement-original-aria-disabled') || '';
        if (originalAction) {
            node.setAttribute('data-bbai-action', originalAction);
        } else {
            node.removeAttribute('data-bbai-action');
        }
        if (originalDisabled) {
            node.setAttribute('aria-disabled', originalDisabled);
        } else {
            node.removeAttribute('aria-disabled');
        }
        node.removeAttribute('data-bbai-locked-cta');
        node.removeAttribute('data-bbai-lock-reason');
        node.removeAttribute('data-bbai-entitlement-locked');
        node.removeAttribute('data-bbai-entitlement-original-bbai-action');
        node.removeAttribute('data-bbai-entitlement-original-aria-disabled');
        node.classList.remove('bbai-is-locked');
    }

    function visible(node) {
        return !!node && !node.hidden && node.getAttribute('aria-hidden') !== 'true' && window.getComputedStyle(node).display !== 'none';
    }

    function setPrecedenceHidden(node, hidden) {
        if (!node) {
            return;
        }
        if (hidden && node.getAttribute('data-bbai-precedence-hidden') !== '1') {
            node.setAttribute('data-bbai-precedence-hidden', '1');
            node.classList.add('nai-is-precedence-hidden');
        } else if (!hidden && node.getAttribute('data-bbai-precedence-hidden') === '1') {
            node.removeAttribute('data-bbai-precedence-hidden');
            node.classList.remove('nai-is-precedence-hidden');
        }
    }

    function applyLibraryPrecedence() {
        var root = document.querySelector('[data-nai-screen="library"], [data-bbai-library-workspace-root="1"]');
        var loading;
        var empty;
        var pagination;
        var emptyVisible;
        var paginationVisible;
        if (!root) {
            return;
        }
        loading = root.querySelector('[data-bbai-library-loading]');
        empty = root.querySelector('[data-bbai-library-empty-state], .bbai-library-filter-empty, .bbai-table-empty');
        pagination = root.querySelector('[data-bbai-library-pagination]');
        if (visible(loading)) {
            setPrecedenceHidden(empty, true);
            setPrecedenceHidden(pagination, true);
            return;
        }
		emptyVisible = visible(empty) && !empty.classList.contains('bbai-library-filter-empty--hidden');
		paginationVisible = pagination && visible(pagination);
		if (emptyVisible && paginationVisible && !conflictReported) {
			conflictReported = true;
			track('library_state_conflict_detected', { surface: 'library' });
		}
        if (pagination) {
            setPrecedenceHidden(pagination, emptyVisible);
        }
    }

    function render(next) {
        next = applyDashboardRootCreditFloor(next);
        var exhausted = !next.can_generate;
        var monthlyExhausted = !next.is_unlimited && next.tokens_remaining <= 0;
        var dailyExhausted = !monthlyExhausted && next.daily_generations_remaining !== null && next.daily_generations_remaining <= 0;
        document.querySelectorAll('[data-bbai-entitlement-remaining]').forEach(function (node) {
            node.textContent = String(next.tokens_remaining);
        });
        document.querySelectorAll('[data-bbai-entitlement-used]').forEach(function (node) {
            node.textContent = String(next.tokens_used_this_month);
        });
        document.querySelectorAll('[data-bbai-entitlement-limit]').forEach(function (node) {
            node.textContent = String(next.token_limit);
        });
        document.querySelectorAll('[data-bbai-entitlement-daily-used]').forEach(function (node) {
            node.textContent = String(next.daily_generations_used === null ? 0 : next.daily_generations_used);
        });
        document.querySelectorAll('[data-bbai-entitlement-daily-limit]').forEach(function (node) {
            node.textContent = String(next.daily_generation_limit === null ? 5 : next.daily_generation_limit);
        });
        document.querySelectorAll('[data-bbai-entitlement-daily-reset]').forEach(function (node) {
            if (!next.daily_reset_date) return;
            var diff = Math.max(0, new Date(next.daily_reset_date).getTime() - Date.now());
            var hours = Math.max(1, Math.ceil(diff / (60 * 60 * 1000)));
            node.textContent = (dailyExhausted ? 'next pass in ' : 'refreshes in ') + hours + 'h';
        });
        document.querySelectorAll('[data-bbai-entitlement-exhausted]').forEach(function (node) {
            node.hidden = !exhausted;
            node.setAttribute('aria-hidden', exhausted ? 'false' : 'true');
        });
        document.querySelectorAll('[data-bbai-entitlement-notice-title]').forEach(function (node) {
            node.textContent = monthlyExhausted ? 'Monthly allowance used' : "Today's allowance used";
        });
        document.querySelectorAll('[data-bbai-entitlement-notice-copy]').forEach(function (node) {
            node.textContent = monthlyExhausted
                ? 'You have 0 generation credits remaining. Review stays available, or upgrade to continue generating.'
                : "You've used today's 5 free generations. Review stays available, or upgrade to continue generating before the next refresh.";
        });
        document.querySelectorAll(generationSelector).forEach(function (node) {
            lockGenerationControl(node, exhausted);
        });
        document.querySelectorAll('[data-bbai-entitlement-autopilot-control]').forEach(function (node) {
            var blocked = !next.can_autopilot;
            node.setAttribute('aria-disabled', blocked ? 'true' : 'false');
            node.classList.toggle('bbai-is-locked', blocked);
            if (blocked) {
                node.setAttribute('data-bbai-entitlement-autopilot-blocked', '1');
            } else {
                node.removeAttribute('data-bbai-entitlement-autopilot-blocked');
            }
        });
        document.querySelectorAll('[data-bbai-entitlement-autopilot-blocked-copy]').forEach(function (node) {
            node.hidden = !!next.can_autopilot;
        });
        var app = document.querySelector('.nai-app');
        if (app) {
            app.setAttribute('data-bbai-entitlement-quota-state', next.quota_state);
            app.setAttribute('data-bbai-entitlement-remaining-value', String(next.tokens_remaining));
        }
        var paywallTitle = document.querySelector('[data-nai-paywall-title]');
        var paywallSub = document.querySelector('[data-nai-paywall-subtitle]');
        if (exhausted && paywallTitle && paywallSub) {
            paywallTitle.textContent = monthlyExhausted ? "You've reached this month's free generations" : "You've used today's free generations";
            paywallSub.textContent = next.message || (monthlyExhausted
                ? 'Upgrade to continue generating ALT text now, or wait for your monthly credits to reset.'
                : "Upgrade to continue now, or wait for today's allowance to refresh.");
        }
        applyLibraryPrecedence();
    }

    function publish(next, source) {
        state = next;
        render(next);
        track('entitlement_state_loaded', {
            plan_type: next.plan_type,
            quota_state: next.quota_state,
            can_generate: next.can_generate,
            can_autopilot: next.can_autopilot
        }, 'entitlement_state_loaded:' + next.plan_type + ':' + next.quota_state);
        subscribers.slice().forEach(function (callback) {
            try {
                callback(Object.assign({}, next), source || 'set');
            } catch (ignore) {}
        });
        try {
            document.dispatchEvent(new CustomEvent('bbai:entitlement-updated', {
                detail: { entitlement_state: Object.assign({}, next), source: source || 'set' }
            }));
        } catch (ignore) {}
        return Object.assign({}, next);
    }

    function set(nextState, source) {
        var normalized = normalize(publicFieldsOrNull(nextState)) || fromLegacy(nextState);
        return normalized ? publish(normalized, source || 'set') : (state ? Object.assign({}, state) : null);
    }

    function merge(nextState, source) {
        var incoming = publicFieldsOrNull(nextState) || fromLegacy(nextState);
        var merged;
        if (!incoming) {
            return state ? Object.assign({}, state) : null;
        }
        merged = normalize(Object.assign({}, state || {}, incoming));
        return publish(merged, source || 'merge');
    }

    function consume(payload, source) {
        var canonical = findEntitlement(payload, 0);
        if (canonical) {
            return merge(canonical, source || 'response');
        }
        return null;
    }

    function config() {
        return window.BBAI_DASH || window.BBAI || {};
    }

    function refresh() {
        var url = String(config().restUsage || '');
        var nonce = String(config().nonce || '');
        if (!url || refreshRequest) {
            return refreshRequest || Promise.resolve(state ? Object.assign({}, state) : null);
        }
        refreshRequest = window.fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: nonce ? { 'X-WP-Nonce': nonce } : {}
        }).then(function (response) {
            return response.json();
        }).then(function (body) {
            return consume(body, 'refresh') || state;
        }).catch(function () {
            return state;
        }).then(function (result) {
            refreshRequest = null;
            return result;
        });
        return refreshRequest;
    }

    window.BBAIEntitlements = {
        get: function () { return state ? Object.assign({}, state) : null; },
        set: set,
        merge: merge,
        consume: consume,
        refresh: refresh,
        canGenerate: function () { return !state || !!state.can_generate; },
        canAutopilot: function () { return !!(state && state.can_autopilot); },
        remaining: function () { return state ? state.tokens_remaining : null; },
        isExhausted: function () { return !!(state && !state.can_generate); },
        subscribe: function (callback) {
            if (typeof callback !== 'function') return function () {};
            subscribers.push(callback);
            if (state) callback(Object.assign({}, state), 'subscribe');
            return function () {
                subscribers = subscribers.filter(function (item) { return item !== callback; });
            };
        }
    };

    var originalFetch = window.fetch;
    if (typeof originalFetch === 'function') {
        window.fetch = function () {
            return originalFetch.apply(this, arguments).then(function (response) {
                try {
                    response.clone().json().then(function (body) {
                        consume(body, 'fetch');
                    }).catch(function () {});
                } catch (ignore) {}
                return response;
            });
        };
    }

    function initialize() {
        var initial = window.bbaiInitialEntitlementState ||
            (window.BBAI_DASH && (window.BBAI_DASH.entitlementState || window.BBAI_DASH.initialUsage)) ||
            (window.BBAI && (window.BBAI.entitlementState || window.BBAI.initialUsage));
        if (initial) {
            set(initial, 'bootstrap');
        }
        if (window.jQuery && typeof window.jQuery === 'function') {
            window.jQuery(document).on('ajaxComplete.bbaiEntitlements', function (event, xhr) {
                if (xhr && xhr.responseJSON) {
                    consume(xhr.responseJSON, 'ajax');
                }
            });
        }
        if (document.querySelector('[data-nai-screen], [data-bbai-library-workspace-root="1"]')) {
            refresh();
        }
        var libraryBody = document.getElementById('bbai-library-table-body');
        if (libraryBody && typeof window.MutationObserver === 'function') {
            new MutationObserver(applyLibraryPrecedence).observe(libraryBody, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'hidden', 'style']
            });
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target.closest(generationSelector + ', [data-action="show-upgrade-modal"], [data-bbai-action="open-upgrade"], [data-bbai-entitlement-autopilot-control]') : null;
        if (!target) return;
        if (target.hasAttribute('data-bbai-entitlement-autopilot-control') && state && !state.can_autopilot) {
            event.preventDefault();
            track('generation_blocked_no_credits', { surface: 'autopilot' }, 'generation_blocked:autopilot');
            return;
        }
        if (target.matches(generationSelector) && state && !state.can_generate) {
            track('generation_blocked_no_credits', { surface: target.closest('.nai-screen--library') ? 'library' : 'dashboard' }, 'generation_blocked:' + (target.getAttribute('data-action') || target.getAttribute('data-bbai-action') || 'unknown'));
        }
        if (target.getAttribute('data-action') === 'show-upgrade-modal' || target.getAttribute('data-bbai-action') === 'open-upgrade') {
            if (state && !state.can_generate) {
                track('paywall_shown', { reason: 'quota_exhausted' });
            }
        }
    }, true);

    document.addEventListener('bbai:analytics', function (event) {
        var name = event && event.detail ? String(event.detail.event || '') : '';
        if (name === 'alt_library_edit_saved' || name === 'alt_library_approve_completed') {
            track('review_completed', { surface: 'library' }, 'review_completed:' + Date.now());
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
}(window, document));
