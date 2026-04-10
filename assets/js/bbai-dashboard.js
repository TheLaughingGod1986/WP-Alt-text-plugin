/**
 * AI Alt Text Dashboard JavaScript
 * Handles upgrade modal, auth buttons, and Stripe integration
 */

const bbaiRunWithJQuery = (function() {
    let warned = false;
    return function(callback) {
        const jq = window.jQuery || window.$;
        if (typeof jq !== 'function') {
            if (!warned) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] jQuery not found; dashboard scripts not run.');
                warned = true;
            }
            return;
        }
        callback(jq);
    };
})();

const BBAI_STATS_SYNC_KEY = 'bbai-dashboard-stats-sync';

function bbaiGetLockedTrialCtaLabelFromRoot() {
    var root = document.querySelector('[data-bbai-dashboard-root="1"]');
    var missing = root ? parseInt(root.getAttribute('data-bbai-missing-count') || '0', 10) || 0 : 0;
    var weak = root ? parseInt(root.getAttribute('data-bbai-weak-count') || '0', 10) || 0 : 0;

    return (missing + weak) > 0 ? 'Fix remaining images for free' : 'Continue fixing images';
}

function bbaiSetDashboardAuthTriggerLoading(trigger) {
    if (!trigger || trigger.getAttribute('data-bbai-auth-loading') === '1') {
        return false;
    }

    trigger.setAttribute('data-bbai-auth-loading', '1');
    trigger.setAttribute('aria-busy', 'true');
    trigger.setAttribute('data-bbai-original-label', trigger.getAttribute('data-bbai-original-label') || (trigger.textContent || '').trim());
    trigger.classList.add('is-loading', 'is-opening');

    if ('disabled' in trigger) {
        trigger.disabled = true;
    }

    trigger.innerHTML = '<span class="bbai-dashboard-auth-label">Opening...</span><span class="bbai-dashboard-auth-spinner" aria-hidden="true"></span>';

    return true;
}

function bbaiResetDashboardAuthTriggerLoading(trigger) {
    var originalLabel;

    if (!trigger || trigger.getAttribute('data-bbai-auth-loading') !== '1') {
        return;
    }

    originalLabel = trigger.getAttribute('data-bbai-original-label') || bbaiGetLockedTrialCtaLabelFromRoot();
    trigger.textContent = originalLabel;
    trigger.removeAttribute('data-bbai-auth-loading');
    trigger.removeAttribute('aria-busy');
    trigger.classList.remove('is-loading', 'is-opening');

    if ('disabled' in trigger) {
        trigger.disabled = false;
    }
}

function bbaiOpenDashboardAuthDirect(trigger) {
    var tab = trigger && trigger.getAttribute && trigger.getAttribute('data-auth-tab') === 'login'
        ? 'login'
        : 'register';

    if (window.authModal && typeof window.authModal.show === 'function') {
        window.authModal.show();
        if (tab === 'login' && typeof window.authModal.showLoginForm === 'function') {
            window.authModal.showLoginForm();
        } else if (typeof window.authModal.showRegisterForm === 'function') {
            window.authModal.showRegisterForm();
        }
        return true;
    }

    if (typeof window.showAuthModal === 'function') {
        window.showAuthModal(tab);
        return true;
    }

    if (typeof showAuthModal === 'function') {
        showAuthModal(tab);
        return true;
    }

    return false;
}

function bbaiInterceptDashboardAuthTriggers() {
    document.addEventListener('click', function(event) {
        var target = event.target && event.target.nodeType === 1
            ? event.target
            : (event.target && event.target.parentElement ? event.target.parentElement : null);
        var trigger;

        if (!target || typeof target.closest !== 'function') {
            return;
        }

        trigger = target.closest('[data-action="show-dashboard-auth"]');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            if (trigger.getAttribute('data-bbai-auth-loading') === '1') {
                return;
            }

            bbaiSetDashboardAuthTriggerLoading(trigger);
            if (!bbaiOpenDashboardAuthDirect(trigger)) {
                bbaiResetDashboardAuthTriggerLoading(trigger);
                return;
            }

            window.setTimeout(function() {
                bbaiResetDashboardAuthTriggerLoading(trigger);
            }, 800);
            return;
        }
    }, true);
}

bbaiInterceptDashboardAuthTriggers();

/**
 * Must live in this outer scope: bbaiNormalizeUsageObject() runs before the jQuery IIFE below executes.
 *
 * @see BBAI_BANNER_LOW_CREDITS_THRESHOLD in includes/admin/banner-system.php
 */
var BBAI_BANNER_LOW_CREDITS_THRESHOLD = 10;

function bbaiString(value) {
    return value === undefined || value === null ? '' : String(value);
}

function bbaiReadUsageNumber(source, keys) {
    var i;
    var value;
    var parsed;

    if (!source || typeof source !== 'object' || !Array.isArray(keys)) {
        return NaN;
    }

    for (i = 0; i < keys.length; i++) {
        value = source[keys[i]];
        if (value === undefined || value === null || value === '') {
            continue;
        }

        parsed = parseInt(value, 10);
        if (!isNaN(parsed)) {
            return parsed;
        }
    }

    return NaN;
}

function bbaiReadUsageString(source, keys) {
    var i;
    var value;

    if (!source || typeof source !== 'object' || !Array.isArray(keys)) {
        return '';
    }

    for (i = 0; i < keys.length; i++) {
        value = source[keys[i]];
        if (typeof value === 'string' && value.trim() !== '') {
            return value;
        }
    }

    return '';
}

function bbaiNormalizeUsageObject(rawUsage) {
    var usage = rawUsage && rawUsage.data && typeof rawUsage.data === 'object' ? rawUsage.data : rawUsage;
    var quota;
    var used;
    var limit;
    var remaining;
    var percentage;
    var resetDate;
    var resetDisplay;
    var resetTimestamp;
    var daysUntilReset;
    var planType;

    if (!usage || typeof usage !== 'object') {
        return null;
    }

    quota = usage.quota && typeof usage.quota === 'object' ? usage.quota : {};

    function getTrialLimitValue() {
        var explicitLimit = bbaiReadUsageNumber(usage, ['trial_limit', 'trialLimit']);
        var quotaLimit = bbaiReadUsageNumber(quota, ['trial_limit', 'trialLimit']);
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');
        var rootLimit = root ? parseInt(root.getAttribute('data-bbai-trial-limit') || '', 10) : NaN;

        if (!isNaN(explicitLimit) && explicitLimit > 0) {
            return explicitLimit;
        }
        if (!isNaN(quotaLimit) && quotaLimit > 0) {
            return quotaLimit;
        }
        if (!isNaN(rootLimit) && rootLimit > 0) {
            return rootLimit;
        }

        return 5;
    }

    function hasResetSignal() {
        var timestampUsage = bbaiReadUsageNumber(usage, ['reset_timestamp', 'resetTimestamp', 'reset_ts', 'days_until_reset', 'daysUntilReset']);
        var timestampQuota = bbaiReadUsageNumber(quota, ['reset_timestamp', 'resetTimestamp', 'reset_ts', 'days_until_reset', 'daysUntilReset']);
        var resetUsage = bbaiReadUsageString(usage, ['resetDate', 'reset_date']);
        var resetQuota = bbaiReadUsageString(quota, ['resetDate', 'reset_date']);

        return (!!resetUsage || !!resetQuota || !isNaN(timestampUsage) || !isNaN(timestampQuota));
    }

    function shouldInferAnonymousTrial(limitValue, planValue, authValue, quotaValue) {
        var normalizedPlan = bbaiString(planValue).toLowerCase();
        var normalizedAuth = bbaiString(authValue).toLowerCase();
        var normalizedQuota = bbaiString(quotaValue).toLowerCase();
        var source = bbaiString(usage.source).toLowerCase();
        var isTrialFlag = usage.is_trial !== undefined ? !!usage.is_trial : !!quota.is_trial;
        var isPaidPlan = normalizedPlan === 'growth' || normalizedPlan === 'pro' || normalizedPlan === 'agency' || normalizedPlan === 'enterprise';
        var upgradeRequired = usage.upgrade_required !== undefined ? !!usage.upgrade_required : !!quota.upgrade_required;

        if (normalizedAuth === 'anonymous' || normalizedQuota === 'trial' || normalizedPlan === 'trial' || normalizedPlan === 'anonymous_trial') {
            return true;
        }
        if (isTrialFlag || source === 'anonymous_trial') {
            return true;
        }
        if (isPaidPlan || normalizedQuota === 'paid' || upgradeRequired) {
            return false;
        }

        return limitValue > 0 && limitValue <= getTrialLimitValue() && !hasResetSignal();
    }

    used = bbaiReadUsageNumber(usage, ['credits_used', 'creditsUsed', 'used']);
    if (isNaN(used)) {
        used = bbaiReadUsageNumber(quota, ['credits_used', 'creditsUsed', 'used']);
    }
    used = isNaN(used) ? 0 : Math.max(0, used);

    limit = bbaiReadUsageNumber(usage, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit']);
    if (isNaN(limit)) {
        limit = bbaiReadUsageNumber(quota, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit']);
    }
    limit = isNaN(limit) || limit <= 0 ? 50 : limit;

    remaining = bbaiReadUsageNumber(usage, ['credits_remaining', 'creditsRemaining', 'remaining']);
    if (isNaN(remaining)) {
        remaining = bbaiReadUsageNumber(quota, ['credits_remaining', 'creditsRemaining', 'remaining']);
    }
    if (isNaN(remaining)) {
        remaining = Math.max(0, limit - used);
    }
    remaining = Math.max(0, remaining);

    percentage = parseFloat(usage.percentage);
    if (isNaN(percentage) && limit > 0) {
        percentage = (Math.min(used, limit) / limit) * 100;
    }
    percentage = isNaN(percentage) ? 0 : Math.max(0, Math.min(100, percentage));

    resetDate = bbaiReadUsageString(usage, ['resetDate']);
    resetDisplay = bbaiReadUsageString(usage, ['reset_date']) || bbaiReadUsageString(quota, ['reset_date']);
    if (!resetDate) {
        resetDate = resetDisplay;
    }

    resetTimestamp = bbaiReadUsageNumber(usage, ['reset_timestamp', 'resetTimestamp', 'reset_ts']);
    if (isNaN(resetTimestamp)) {
        resetTimestamp = bbaiReadUsageNumber(quota, ['reset_timestamp']);
    }

    daysUntilReset = bbaiReadUsageNumber(usage, ['days_until_reset', 'daysUntilReset']);
    if (isNaN(daysUntilReset)) {
        daysUntilReset = bbaiReadUsageNumber(quota, ['days_until_reset']);
    }

    planType = bbaiReadUsageString(usage, ['plan_type', 'plan']) || bbaiReadUsageString(quota, ['plan_type', 'plan']);
    var authState = bbaiReadUsageString(usage, ['auth_state']) || bbaiReadUsageString(quota, ['auth_state']) || 'authenticated';
    var quotaType = bbaiReadUsageString(usage, ['quota_type']) || bbaiReadUsageString(quota, ['quota_type']) || '';
    var inferredTrial = shouldInferAnonymousTrial(limit, planType, authState, quotaType);
    if (inferredTrial) {
        authState = 'anonymous';
        quotaType = 'trial';
        if (!planType || planType === 'free' || planType === 'anonymous_trial') {
            planType = 'trial';
        }
    }
    if (!planType && (authState === 'anonymous' || quotaType === 'trial')) {
        planType = 'trial';
    }
    if (!planType) {
        planType = 'free';
    }
    if (!quotaType) {
        quotaType = planType === 'trial'
            ? 'trial'
            : (planType === 'growth' || planType === 'pro' || planType === 'agency' || planType === 'enterprise'
                ? 'paid'
                : 'monthly_account');
    }
    var quotaState = bbaiReadUsageString(usage, ['quota_state']) || bbaiReadUsageString(quota, ['quota_state']) || '';
    var lowCreditThreshold = bbaiReadUsageNumber(usage, ['low_credit_threshold']);
    if (isNaN(lowCreditThreshold)) {
        lowCreditThreshold = bbaiReadUsageNumber(quota, ['low_credit_threshold']);
    }
    if (isNaN(lowCreditThreshold)) {
        lowCreditThreshold = quotaType === 'trial'
            ? Math.min(2, Math.max(1, limit - 1))
            : BBAI_BANNER_LOW_CREDITS_THRESHOLD;
    }
    if (!quotaState) {
        if (remaining <= 0) {
            quotaState = 'exhausted';
        } else if (remaining <= Math.max(1, parseInt(lowCreditThreshold, 10) || 0)) {
            quotaState = 'near_limit';
        } else {
            quotaState = 'active';
        }
    }
    var freePlanOffer = bbaiReadUsageNumber(usage, ['free_plan_offer']);
    if (isNaN(freePlanOffer)) {
        freePlanOffer = bbaiReadUsageNumber(quota, ['free_plan_offer']);
    }
    freePlanOffer = isNaN(freePlanOffer) ? 50 : Math.max(0, parseInt(freePlanOffer, 10));
    var signupRequired = usage.signup_required !== undefined
        ? !!usage.signup_required
        : (quota.signup_required !== undefined ? !!quota.signup_required : (quotaType === 'trial' && remaining <= 0));
    var upgradeRequired = usage.upgrade_required !== undefined
        ? !!usage.upgrade_required
        : (quota.upgrade_required !== undefined ? !!quota.upgrade_required : (quotaType === 'trial' ? false : remaining <= 0));
    var isTrial = usage.is_trial !== undefined
        ? !!usage.is_trial
        : (quota.is_trial !== undefined ? !!quota.is_trial : (quotaType === 'trial' || authState === 'anonymous' || inferredTrial));
    var trialExhausted = usage.trial_exhausted !== undefined
        ? !!usage.trial_exhausted
        : (quota.trial_exhausted !== undefined ? !!quota.trial_exhausted : (isTrial && remaining <= 0));

    return Object.assign({}, usage, {
        used: used,
        limit: limit,
        remaining: remaining,
        percentage: percentage,
        plan: planType,
        plan_type: planType,
        auth_state: authState,
        quota_type: quotaType,
        quota_state: quotaState,
        signup_required: signupRequired,
        upgrade_required: upgradeRequired,
        free_plan_offer: freePlanOffer,
        is_trial: isTrial,
        trial_exhausted: trialExhausted,
        low_credit_threshold: Math.max(0, parseInt(lowCreditThreshold, 10) || 0),
        resetDate: resetDate || '',
        reset_date: resetDisplay || resetDate || '',
        reset_timestamp: isNaN(resetTimestamp) ? 0 : resetTimestamp,
        days_until_reset: isNaN(daysUntilReset) ? null : Math.max(0, daysUntilReset),
        daysUntilReset: isNaN(daysUntilReset) ? null : Math.max(0, daysUntilReset),
        credits_used: used,
        credits_total: limit,
        credits_remaining: remaining,
        creditsUsed: used,
        creditsTotal: limit,
        creditsLimit: limit,
        creditsRemaining: remaining,
        quota: {
            used: used,
            limit: limit,
            remaining: remaining,
            reset_date: resetDisplay || resetDate || '',
            reset_timestamp: isNaN(resetTimestamp) ? 0 : resetTimestamp,
            plan_type: planType,
            auth_state: authState,
            quota_type: quotaType,
            quota_state: quotaState,
            signup_required: signupRequired,
            upgrade_required: upgradeRequired,
            free_plan_offer: freePlanOffer,
            is_trial: isTrial,
            trial_exhausted: trialExhausted,
            low_credit_threshold: Math.max(0, parseInt(lowCreditThreshold, 10) || 0)
        }
    });
}

function bbaiMirrorUsageObject(rawUsage) {
    var usage = bbaiNormalizeUsageObject(rawUsage);
    var targets = [window.BBAI_DASH, window.BBAI_DASHBOARD, window.BBAI, window.BBAI_UPGRADE];

    if (!usage) {
        return null;
    }

    targets.forEach(function(target) {
        if (!target || typeof target !== 'object') {
            return;
        }

        target.usage = usage;
        target.initialUsage = usage;
        target.quota = usage.quota;
    });

    return usage;
}

window.bbaiNormalizeAuthenticatedUsage = window.bbaiNormalizeAuthenticatedUsage || bbaiNormalizeUsageObject;
window.bbaiMirrorAuthenticatedUsage = window.bbaiMirrorAuthenticatedUsage || bbaiMirrorUsageObject;

function bbaiIsAnonymousTrialUsage(usage) {
    if (!usage || typeof usage !== 'object') {
        return false;
    }

    return String(usage.auth_state || '').toLowerCase() === 'anonymous' ||
        String(usage.quota_type || '').toLowerCase() === 'trial' ||
        !!usage.is_trial;
}

function bbaiGetUsageObject() {
    var root = document.querySelector('[data-bbai-dashboard-root="1"]');
    var rootUsage = null;
    var globalUsage = bbaiNormalizeUsageObject((window.BBAI_DASH && (window.BBAI_DASH.initialUsage || window.BBAI_DASH.usage)) ||
        (window.BBAI_DASHBOARD && (window.BBAI_DASHBOARD.initialUsage || window.BBAI_DASHBOARD.usage)) ||
        (window.BBAI && (window.BBAI.initialUsage || window.BBAI.usage)) ||
        (window.BBAI_UPGRADE && window.BBAI_UPGRADE.usage) ||
        null);

        if (root) {
            var rootUsed = parseInt(root.getAttribute('data-bbai-credits-used') || '0', 10);
            var rootLimit = parseInt(root.getAttribute('data-bbai-credits-total') || '0', 10);
            var rootRemaining = parseInt(root.getAttribute('data-bbai-credits-remaining') || '0', 10);

            if (!isNaN(rootUsed) || !isNaN(rootLimit) || !isNaN(rootRemaining)) {
                rootUsage = bbaiNormalizeUsageObject({
                    used: isNaN(rootUsed) ? 0 : rootUsed,
                    limit: isNaN(rootLimit) || rootLimit <= 0 ? 50 : rootLimit,
                    remaining: isNaN(rootRemaining) ? Math.max(0, (isNaN(rootLimit) || rootLimit <= 0 ? 50 : rootLimit) - (isNaN(rootUsed) ? 0 : rootUsed)) : rootRemaining,
                    reset_line: root.getAttribute('data-bbai-credits-reset-line') || '',
                    auth_state: root.getAttribute('data-bbai-auth-state') || '',
                    quota_type: root.getAttribute('data-bbai-quota-type') || '',
                    quota_state: root.getAttribute('data-bbai-quota-state') || '',
                    signup_required: root.getAttribute('data-bbai-signup-required') === '1',
                    free_plan_offer: root.getAttribute('data-bbai-free-plan-offer') || 50
                });
            }
        }

    var merged = rootUsage && globalUsage && typeof globalUsage === 'object'
        ? bbaiNormalizeUsageObject(Object.assign({}, rootUsage, globalUsage))
        : (rootUsage || globalUsage);

    if (root && merged && typeof merged === 'object' && root.getAttribute('data-bbai-is-guest-trial') === '1') {
        merged = bbaiNormalizeUsageObject(
            Object.assign({}, merged, {
                auth_state: 'anonymous',
                quota_type: 'trial',
                is_trial: true
            })
        );
    }

    return merged;
}

function bbaiGetUsageFromDom() {
    var selectors = [
        '.bbai-usage-count',
        '.bbai-usage-count-text',
        '.bbai-card__subtitle'
    ];

    for (var i = 0; i < selectors.length; i++) {
        var node = document.querySelector(selectors[i]);
        if (!node || !node.textContent) {
            continue;
        }

        var match = node.textContent.match(/([0-9][0-9,]*)\s*of\s*([0-9][0-9,]*)/i);
        if (!match) {
            continue;
        }

        var used = parseInt(String(match[1]).replace(/,/g, ''), 10);
        var limit = parseInt(String(match[2]).replace(/,/g, ''), 10);
        if (!isNaN(used) && !isNaN(limit) && limit > 0) {
            return { used: used, limit: limit };
        }
    }

    return null;
}

function bbaiIsUsageExhausted() {
    var usage = bbaiNormalizeUsageObject(bbaiGetUsageObject());
    if (usage && typeof usage === 'object') {
        var remaining = parseInt(usage.remaining, 10);

        if (!isNaN(remaining) && remaining <= 0) {
            return true;
        }

        var used = parseInt(usage.used, 10);
        var limit = parseInt(usage.limit, 10);
        if (!isNaN(used) && !isNaN(limit) && limit > 0 && used >= limit) {
            return true;
        }
    }

    var domUsage = bbaiGetUsageFromDom();
    return !!(domUsage && domUsage.limit > 0 && domUsage.used >= domUsage.limit);
}

function bbaiHasQuotaLockHint(value) {
    var text = bbaiString(value).toLowerCase();
    if (!text) {
        return false;
    }

    return text.indexOf('out of credits') !== -1 ||
        text.indexOf('unlock more generations') !== -1 ||
        text.indexOf('monthly quota') !== -1 ||
        text.indexOf('monthly limit') !== -1 ||
        text.indexOf('quota reached') !== -1;
}

function bbaiIsGenerationActionControl(element) {
    if (!element) {
        return false;
    }

    var action = bbaiString(element.getAttribute && element.getAttribute('data-action')).toLowerCase();
    var bbaiAction = bbaiString(element.getAttribute && element.getAttribute('data-bbai-action')).toLowerCase();
    var id = bbaiString(element.id).toLowerCase();
    var className = bbaiString(element.className).toLowerCase();

    if (action === 'generate-missing' || action === 'regenerate-all' || action === 'regenerate-single' || action === 'phase17-improve-alt') {
        return true;
    }

    if (bbaiAction === 'generate_missing' || bbaiAction === 'reoptimize_all') {
        return true;
    }

    if (id.indexOf('batch-regenerate') !== -1) {
        return true;
    }

    if (className.indexOf('bbai-optimization-cta') !== -1 ||
        className.indexOf('bbai-action-btn-primary') !== -1 ||
        className.indexOf('bbai-action-btn-secondary') !== -1 ||
        className.indexOf('bbai-dashboard-btn--primary') !== -1 ||
        className.indexOf('bbai-dashboard-btn--secondary') !== -1) {
        return true;
    }

    var labelText = (bbaiString(element.getAttribute && element.getAttribute('aria-label')) + ' ' + bbaiString(element.textContent)).toLowerCase();
    return labelText.indexOf('generate missing') !== -1 ||
        labelText.indexOf('regenerate') !== -1 ||
        labelText.indexOf('re-optim') !== -1 ||
        labelText.indexOf('reoptimiz') !== -1 ||
        labelText.indexOf('optimise all') !== -1 ||
        labelText.indexOf('optimize all') !== -1;
}

function bbaiIsLockedActionControl(element) {
    if (!element) {
        return false;
    }

    var target = element;
    if (target.closest) {
        var closestTarget = target.closest('[data-bbai-lock-control], [data-action], [data-bbai-action], .bbai-optimization-cta, .bbai-action-btn, .bbai-dashboard-btn, button, a');
        if (closestTarget) {
            target = closestTarget;
        }
    }

    var className = bbaiString(target.className).toLowerCase();

    if ((target.hasAttribute && (target.hasAttribute('disabled') || target.hasAttribute('data-bbai-lock-control'))) ||
        target.disabled ||
        (target.getAttribute && target.getAttribute('aria-disabled') === 'true')) {
        return true;
    }

    if (className.indexOf('bbai-optimization-cta--disabled') !== -1 ||
        className.indexOf('bbai-optimization-cta--locked') !== -1 ||
        className.indexOf('bbai-action-btn--disabled') !== -1 ||
        className.indexOf(' disabled') !== -1 ||
        className.indexOf('disabled ') === 0 ||
        className === 'disabled') {
        return true;
    }

    var hintText = bbaiString(target.getAttribute && target.getAttribute('title')) + ' ' + bbaiString(target.getAttribute && target.getAttribute('data-bbai-tooltip'));
    if (bbaiHasQuotaLockHint(hintText)) {
        return true;
    }

    if (target.querySelector) {
        var hintNode = target.querySelector('[title], [data-bbai-tooltip], [data-bbai-lock-control]');
        if (hintNode) {
            if ((hintNode.hasAttribute && hintNode.hasAttribute('data-bbai-lock-control')) ||
                bbaiHasQuotaLockHint(bbaiString(hintNode.getAttribute && hintNode.getAttribute('title')) + ' ' + bbaiString(hintNode.getAttribute && hintNode.getAttribute('data-bbai-tooltip')))) {
                return true;
            }
        }
    }

    if (bbaiIsGenerationActionControl(target) && bbaiIsUsageExhausted()) {
        return true;
    }

    return false;
}

bbaiRunWithJQuery(function($) {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
    const _n = i18n && typeof i18n._n === 'function' ? i18n._n : (single, plural, number) => (number === 1 ? single : plural);
    const sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : (format) => format;

    // Cache commonly used DOM elements (performance optimization)
    var $cachedElements = {};
    const FALLBACK_UPGRADE_SELECTOR = [
        '.bbai-upgrade-link',
        '.bbai-upgrade-inline',
        '.bbai-upgrade-cta-card button',
        '.bbai-upgrade-cta-card a',
        '.bbai-pro-upsell-card button',
        '.bbai-upgrade-banner button',
        '.bbai-upgrade-banner a',
        '.bbai-upgrade-callout button',
        '.bbai-upgrade-callout a',
        '[data-upgrade-trigger="true"]'
    ].join(', ');

    function getCachedElement(selector) {
        if (!$cachedElements[selector]) {
            $cachedElements[selector] = $(selector);
        }
        return $cachedElements[selector];
    }

    // ========================================
    // Monthly Reset Insight Modal
    // ========================================
    (function initResetModal() {
        var dashConfig = window.BBAI_DASH || {};
        var resetData = dashConfig.resetModal;

        if (!resetData || typeof resetData !== 'object') {
            return;
        }

        // Delay to let onboarding and other modals initialise first.
        setTimeout(function showIfIdle() {
            if (window.bbaiModal && window.bbaiModal.activeModal) {
                // Another modal is open — retry later.
                setTimeout(showIfIdle, 3000);
                return;
            }

            if (!window.bbaiModal || typeof window.bbaiModal.show !== 'function') {
                return;
            }

            var lastUsed  = parseInt(resetData.lastMonthUsed, 10) || 0;
            var lastLimit = parseInt(resetData.lastMonthLimit, 10) || 0;
            var newLimit  = parseInt(resetData.newLimit, 10) || 0;
            var plan      = resetData.plan || 'free';
            var planLabel = resetData.planLabel || 'Free';

            var message = '';
            if (lastUsed > 0 && lastLimit > 0) {
                var usagePct = Math.round((lastUsed / lastLimit) * 100);
                message += __('Last month you generated alt text for', 'beepbeep-ai-alt-text-generator')
                    + ' ' + lastUsed.toLocaleString() + ' '
                    + __('of', 'beepbeep-ai-alt-text-generator') + ' ' + lastLimit.toLocaleString()
                    + ' ' + __('images', 'beepbeep-ai-alt-text-generator') + ' (' + usagePct + '%).\n\n';
            } else {
                message += __('Your monthly quota has been refreshed.', 'beepbeep-ai-alt-text-generator') + '\n\n';
            }

            message += __('You now have', 'beepbeep-ai-alt-text-generator') + ' '
                + newLimit.toLocaleString() + ' '
                + __('credits available on your', 'beepbeep-ai-alt-text-generator') + ' '
                + planLabel + ' ' + __('plan.', 'beepbeep-ai-alt-text-generator');

            var buttons = [
                {
                    text: __('Got it', 'beepbeep-ai-alt-text-generator'),
                    primary: true,
                    action: function() {
                        dismissResetModal();
                        window.bbaiModal.close();
                    }
                }
            ];

            if (plan === 'free') {
                buttons.unshift({
                    text: __('Upgrade for more', 'beepbeep-ai-alt-text-generator'),
                    primary: false,
                    action: function() {
                        dismissResetModal();
                        window.bbaiModal.close();
                        var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            upgradeBtn.click();
                        }
                    }
                });
            }

            window.bbaiModal.show({
                type: 'info',
                title: __('Monthly Quota Reset', 'beepbeep-ai-alt-text-generator'),
                message: message,
                buttons: buttons,
                onClose: function() {
                    dismissResetModal();
                }
            });
        }, 1500);

        function dismissResetModal() {
            var ajaxUrl = (window.bbai_ajax && window.bbai_ajax.ajaxurl) || '';
            var nonce   = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
            if (!ajaxUrl || !nonce) { return; }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbai_dismiss_reset_modal',
                    nonce: nonce
                }
            });
        }
    })();

    // Initialize when DOM is ready
    // Add vanilla JS handler as fallback (works even if jQuery isn't ready)
    (function() {
        function handleCheckoutClick(e) {
            const btn = e.target.closest('[data-action="checkout-plan"]');
            if (!btn) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout button clicked (vanilla JS handler)!');

            const plan = btn.getAttribute('data-plan') || btn.dataset.plan;
            const priceId = btn.getAttribute('data-price-id') || btn.dataset.priceId;
            const $btn = window.jQuery ? window.jQuery(btn) : null;

            if ($btn && $btn.length) {
                initiateCheckout($btn, priceId, plan);
            } else if (!openCheckoutUrl(resolveCheckoutFallbackUrl(null, plan))) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No checkout URL available!');
                alert(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
            }

            return false;
        }
        
        // Attach event listener
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                document.addEventListener('click', handleCheckoutClick, true);
            });
        } else {
            document.addEventListener('click', handleCheckoutClick, true);
        }
    })();

    $(document).ready(function() {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] jQuery ready - setting up upgrade modal handlers');
        
        // Load license site usage if on agency license (delay for Admin tab)
        setTimeout(function() {
            if (typeof loadLicenseSiteUsage === 'function') {
                loadLicenseSiteUsage();
            }
        }, 500);
        
        // Check if we should open portal after login
        const shouldOpenPortal = localStorage.getItem('bbai_open_portal_after_login');
        
        if (shouldOpenPortal === 'true') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal flag found, checking authentication...');
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth state:', {
                hasAjax: !!window.bbai_ajax,
                isAuthenticated: window.bbai_ajax?.is_authenticated,
                ajaxObject: window.bbai_ajax
            });
            
            // Wait a bit longer for authentication state to be set
            // Check multiple times since the state might not be ready immediately
            let checkCount = 0;
            const maxChecks = 10;
            const checkInterval = setInterval(function() {
                checkCount++;
                const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
                
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal check attempt', checkCount, {
                    isAuthenticated: isAuthenticated,
                    authValue: window.bbai_ajax?.is_authenticated
                });
                
                if (isAuthenticated) {
                    clearInterval(checkInterval);
                    // Clear the flag
                    localStorage.removeItem('bbai_open_portal_after_login');
                    
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User authenticated, opening portal after login');
                    
                    // Set a flag to indicate we're opening after login (to prevent modal from showing)
                    sessionStorage.setItem('bbai_portal_after_login', 'true');
                    
                    // Small delay to ensure everything is ready
                    setTimeout(function() {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening portal now...');
                        openCustomerPortal();
                        
                        // Clear the session flag after a delay
                        setTimeout(function() {
                            sessionStorage.removeItem('bbai_portal_after_login');
                        }, 2000);
                    }, 300);
                } else if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    // If still not authenticated after checks, clear the flag
                    localStorage.removeItem('bbai_open_portal_after_login');
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] User not authenticated after multiple checks, clearing portal flag');
                }
            }, 200); // Check every 200ms
        }
        
        // Handle upgrade CTA: use new pricing modal
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Upgrade CTA clicked via jQuery handler', this);

            if (typeof window.bbaiOpenUpgradeModal === 'function') {
                try {
                    if (window.bbaiOpenUpgradeModal('default', { source: 'dashboard-jquery', triggerKey: 'manual' })) {
                        return false;
                    }
                } catch (err) {
                    if (typeof alttextaiDebug !== 'undefined' && alttextaiDebug && window.BBAI_LOG) {
                        window.BBAI_LOG.warn('[AltText AI] bbaiOpenUpgradeModal failed (jQuery handler)', err);
                    }
                }
            }

            if (typeof alttextaiShowModal === 'function' && alttextaiShowModal()) {
                return false;
            }

            const modal = findUpgradeModalElement();
            if (modal) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Fallback: showing modal with legacy inline styles');
                modal.removeAttribute('style');
                modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
                modal.classList.add('active');
                modal.classList.add('is-visible');
                modal.setAttribute('aria-hidden', 'false');
                bbaiSetUpgradeModalScrollLock(true);
                const modalContent = modal.querySelector('.bbai-upgrade-modal__content');
                if (modalContent) {
                    modalContent.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
                }
                return false;
            }

            if (typeof window.openPricingModal === 'function') {
                window.openPricingModal('enterprise');
            } else if (typeof bbaiApp !== 'undefined' && typeof bbaiApp.showModal === 'function') {
                // Fallback to old modal
                bbaiApp.showModal();
            } else if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal(); // Legacy fallback
            } else {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Pricing modal not available and DOM element not found!');
                if (typeof window.bbaiModal !== 'undefined') {
                    window.bbaiModal.warning(__('Upgrade modal not found. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                }
            }
            
            return false;
        });
        
        // Ensure legacy/future upgrade buttons automatically open the modal
        bindFallbackUpgradeTriggers();
        ensureUpgradeAttributes();
        observeFutureUpgradeTriggers();
        bindDirectUpgradeHandlers();
        
        // Handle auth banner button
        $(document).on('click', '#bbai-show-auth-banner-btn', function(e) {
            e.preventDefault();
            showAuthBanner();
        });
        
        // Handle auth login button
        $(document).on('click', '#bbai-show-auth-login-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });
        
        // Handle logout button (jQuery)
        $(document).on('click', '#bbai-logout-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleLogout();
        });
        
        // Handle show auth modal button
        $(document).on('click', '[data-action="show-auth-modal"]', function(e) {
            e.preventDefault();
            const authTab = $(this).attr('data-auth-tab') || 'login';
            showAuthModal(authTab);
        });
        
        // Handle billing portal
        $(document).on('click', '[data-action="open-billing-portal"]', function(e) {
            e.preventDefault();
            
            // Check if user is authenticated
            const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
                openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal
                if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    window.authModal.showLoginForm();
                } else if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                } else {
                    // Fallback: try to show auth modal manually
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        authModal.removeAttribute('aria-hidden');
                        authModal.setAttribute('data-bbai-auth-modal-visible', '1');
                        document.body.classList.add('bbai-auth-modal-open');
                        document.body.style.overflow = 'hidden';
                    } else {
                        window.bbaiModal.warning(__('Please log in first to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                    }
                }
            }
        });
        
        // Handle demo signup buttons
        $(document).on('click', '#bbai-demo-signup-btn, #bbai-settings-signup-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });

        // Load subscription info if on Settings page
        if ($('#bbai-account-management').length) {
            loadSubscriptionInfo();
        }

        // Handle account management buttons
        $(document).on('click', '#bbai-update-payment-method, #bbai-manage-subscription', function(e) {
            e.preventDefault();
            
            // Check if user is authenticated
            const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
            openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal
                if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    window.authModal.showLoginForm();
                } else if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                } else {
                    // Fallback: try to show auth modal manually
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        authModal.removeAttribute('aria-hidden');
                        authModal.setAttribute('data-bbai-auth-modal-visible', '1');
                        document.body.classList.add('bbai-auth-modal-open');
                        document.body.style.overflow = 'hidden';
                    } else {
                        window.bbaiModal.warning(__('Please log in first to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                    }
                }
            }
        });

        $(document).on('click', '[data-action="manage-subscription"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            
            // Prevent multiple clicks
            if ($btn.hasClass('bbai-processing') || $btn.prop('disabled')) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Already processing, ignoring click');
                return false;
            }
            
            $btn.addClass('bbai-processing').prop('disabled', true);
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Manage subscription clicked');
            
            // Check if user is authenticated - check multiple sources
            const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
            const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
            
            // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
            const isAdminTab = $('.bbai-admin-content').length > 0;
            const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
            
            const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Authentication check:', {
                hasAjax: !!window.bbai_ajax,
                ajaxAuth: ajaxAuth,
                userDataAuth: userDataAuth,
                isAdminTab: isAdminTab,
                isAdminAuthenticated: isAdminAuthenticated,
                isAuthenticated: isAuthenticated,
                authValue: window.bbai_ajax?.is_authenticated,
                userData: window.bbai_ajax?.user_data
            });
            
            // Restore button state
            setTimeout(function() {
                $btn.removeClass('bbai-processing').prop('disabled', false);
            }, 1000);
            
            if (!isAuthenticated) {
                // User not authenticated - show login modal first
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User not authenticated, showing login modal');
                
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal - try multiple methods
                let modalShown = false;
                
                if (typeof window.authModal !== 'undefined' && window.authModal) {
                    if (typeof window.authModal.show === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using window.authModal.show()');
                        window.authModal.show();
                        if (typeof window.authModal.showLoginForm === 'function') {
                            window.authModal.showLoginForm();
                        }
                        modalShown = true;
                    }
                }
                
                if (!modalShown && typeof showAuthModal === 'function') {
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using showAuthModal() function');
                    showAuthModal('login');
                    modalShown = true;
                }
                
                if (!modalShown) {
                    // Fallback: try to show auth modal manually
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Trying manual modal show');
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        authModal.removeAttribute('aria-hidden');
                        authModal.setAttribute('data-bbai-auth-modal-visible', '1');
                        document.body.classList.add('bbai-auth-modal-open');
                        document.body.style.overflow = 'hidden';
                        
                        // Try to show login form
                        const loginForm = document.getElementById('login-form');
                        const registerForm = document.getElementById('register-form');
                        if (loginForm) loginForm.style.display = 'block';
                        if (registerForm) registerForm.style.display = 'none';
                        
                        modalShown = true;
                    }
                }
                
                if (!modalShown) {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Could not show auth modal');
                    window.bbaiModal.warning(__('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.', 'beepbeep-ai-alt-text-generator'));
                }
                
                return false;
            }
            
            // User is authenticated, open portal directly
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User authenticated, opening portal');
            openCustomerPortal();
        });

        $(document).on('click', '[data-action="disconnect-account"]', function(e) {
            e.preventDefault();
            if (!confirm(__('Disconnect this account for all WordPress users? You can reconnect at any time.', 'beepbeep-ai-alt-text-generator'))) {
                return;
            }
            disconnectAccount($(this));
        });

        // Handle logout button (consolidated disconnect/logout)
        $(document).on('click', '[data-action="logout"]', function(e) {
            e.preventDefault();
            const $button = $(this);
            
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button clicked');
            
            if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
                window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                return;
            }

            // Show loading state
            $button.prop('disabled', true)
                   .addClass('bbai-btn-loading')
                   .attr('aria-busy', 'true');
            
            const originalText = $button.text();
            $button.html('<span class="bbai-spinner"></span> ' + __('Logging out...', 'beepbeep-ai-alt-text-generator'));

            $.ajax({
                url: window.bbai_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'beepbeepai_logout',
                    nonce: window.bbai_ajax.nonce
                },
                timeout: 15000,
                success: function(response) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout response:', response);
                    
                    if (response.success) {
                        // Show success message briefly
                        $button.removeClass('bbai-btn-loading')
                               .html('✓ ' + __('Logged out', 'beepbeep-ai-alt-text-generator'))
                               .attr('aria-busy', 'false');
                        
                        // Clear any cached data
                        if (typeof localStorage !== 'undefined') {
                            localStorage.removeItem('bbai_subscription_cache');
                            localStorage.removeItem('alttextai_token');
                            localStorage.removeItem('bbai_open_portal_after_login');
                        }
                        
                        // Redirect or reload
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        }
                    } else {
                        // Restore button
                        $button.prop('disabled', false)
                               .removeClass('bbai-btn-loading')
                               .text(originalText)
                               .attr('aria-busy', 'false');
                        
                        const errorMsg = response.data?.message || __('Failed to log out. Please try again.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout error:', status, error);
                    
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
                    let errorMessage = __('Unable to log out. Please try again.', 'beepbeep-ai-alt-text-generator');
                    
                    if (status === 'timeout') {
                        errorMessage = __('Request timed out. Try refreshing the page.', 'beepbeep-ai-alt-text-generator');
                    } else if (xhr.status === 0) {
                        errorMessage = __('Network error. Please check your connection and try again.', 'beepbeep-ai-alt-text-generator');
                    }
                    
                    window.bbaiModal.error(errorMessage);
                }
            });
        });

        // Handle checkout plan buttons in upgrade modal
        $(document).on('click', '[data-action="checkout-plan"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout button clicked!');
            
            const $btn = $(this);
            const plan = $btn.attr('data-plan') || $btn.data('plan');
            const priceId = $btn.attr('data-price-id') || $btn.data('price-id');
            const fallbackUrl = $btn.attr('data-fallback-url') || $btn.data('fallback-url');
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout plan:', plan, 'priceId:', priceId, 'fallbackUrl:', fallbackUrl);

            initiateCheckout($btn, priceId, plan);

            return false;
        });

        // Handle retry subscription fetch
        $(document).on('click', '#bbai-retry-subscription', function(e) {
            e.preventDefault();
            loadSubscriptionInfo(true); // Force refresh
        });
    });

    /**
     * Unified handler for upgrade triggers
     */
    function handleUpgradeTrigger(event, triggerElement) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        try {
            // Always show upgrade modal - it handles authentication internally
            if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal();
            } else {
                // Fallback: try to show modal directly
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.add('active');
                    modal.classList.add('is-visible');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    if (document.documentElement) {
                        document.documentElement.style.overflow = 'hidden';
                    }
                } else {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Upgrade modal not found in DOM');
                    // Last resort: check if user is authenticated and show auth modal
                    var isAuthed = false;
                    if (typeof window.bbai_ajax !== 'undefined' && typeof window.bbai_ajax.is_authenticated !== 'undefined') {
                        isAuthed = !!window.bbai_ajax.is_authenticated;
                    }
                    if (!isAuthed && typeof showAuthLogin === 'function') {
                        showAuthLogin();
                    }
                }
            }
        } catch (err) {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Error in handleUpgradeTrigger:', err);
            // Fallback: try to show modal directly
            try {
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.add('active');
                    modal.classList.add('is-visible');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    if (document.documentElement) {
                        document.documentElement.style.overflow = 'hidden';
                    }
                }
            } catch (e) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to show modal:', e);
            }
        }
    }

    function bindDirectUpgradeHandlers(targetSelector) {
        var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
        if (!selector) {
            return;
        }

        var nodes = document.querySelectorAll(selector);
        if (!nodes || !nodes.length) {
            return;
        }

        var ElementRef = typeof window !== 'undefined' && window.Element ? window.Element : null;

        nodes.forEach(function(el) {
            if (!el || (ElementRef && !(el instanceof ElementRef))) {
                return;
            }

            if (el.dataset && el.dataset.upgradeBound === '1') {
                return;
            }

            el.addEventListener('click', function(event) {
                handleUpgradeTrigger(event, el);
            }, true);

            if (el.dataset) {
                el.dataset.upgradeBound = '1';
            }
        });
    }

    /**
     * Bind fallback listeners for upgrade CTAs that might not have data-action attributes.
     * Ensures any future CTA using the shared CSS classes can still trigger the modal.
     */
    function bindFallbackUpgradeTriggers() {
        if (!FALLBACK_UPGRADE_SELECTOR) {
            return;
        }

        document.addEventListener('click', function(event) {
            var fallbackTrigger = event.target && event.target.closest(FALLBACK_UPGRADE_SELECTOR);
            if (!fallbackTrigger) {
                return;
            }

            // If the element (or ancestor) already has the data-action, let the jQuery handler fire instead.
            if (fallbackTrigger.closest('[data-action="show-upgrade-modal"]')) {
                return;
            }

            handleUpgradeTrigger(event, fallbackTrigger);
        }, true);
    }

    /**
     * Add data-action attributes to any CTA selectors missing them so that the
     * delegated jQuery handler can respond consistently.
     */
    function ensureUpgradeAttributes(targetSelector) {
        var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
        if (!selector) {
            return;
        }

        var nodes = document.querySelectorAll(selector);
        if (!nodes || !nodes.length) {
            return;
        }

        var ElementRef = typeof window !== 'undefined' && window.Element ? window.Element : null;

        nodes.forEach(function(el) {
            if (!el || (ElementRef && !(el instanceof ElementRef))) {
                return;
            }

            if (!el.hasAttribute('data-action')) {
                el.setAttribute('data-action', 'show-upgrade-modal');
            }
            el.setAttribute('data-upgrade-trigger', 'true');
        });

        bindDirectUpgradeHandlers(selector);
    }

    function observeFutureUpgradeTriggers() {
        if (typeof MutationObserver === 'undefined' || !FALLBACK_UPGRADE_SELECTOR) {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (!mutation.addedNodes || !mutation.addedNodes.length) {
                    return;
                }

                mutation.addedNodes.forEach(function(node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches(FALLBACK_UPGRADE_SELECTOR)) {
                        ensureUpgradeAttributes();
                    } else if (node.querySelector) {
                        var matches = node.querySelector(FALLBACK_UPGRADE_SELECTOR);
                        if (matches) {
                            ensureUpgradeAttributes();
                        }
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Load subscription information from backend
     */
    function loadSubscriptionInfo(forceRefresh) {
        // Use cached DOM elements for better performance
        const $loading = getCachedElement('#bbai-subscription-loading');
        const $error = getCachedElement('#bbai-subscription-error');
        const $info = getCachedElement('#bbai-subscription-info');
        const $freeMessage = getCachedElement('#bbai-free-plan-message');

        // Check cache first (unless force refresh)
        if (!forceRefresh) {
            const cached = getCachedSubscriptionInfo();
            if (cached) {
                displaySubscriptionInfo(cached.data);
                return;
            }
        }

        // Show loading state
        $loading.show();
        $error.hide();
        $info.hide();
        $freeMessage.hide();

        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            showSubscriptionError(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                return;
            }

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
                data: {
                action: 'beepbeepai_get_subscription_info',
                nonce: window.bbai_ajax.nonce
            },
            success: function(response) {
                $loading.hide();

                if (response.success && response.data) {
                    // Reset retry attempts on success
                    resetRetryAttempts();
                    
                    // Cache the subscription info (15 minutes - optimized)
                    cacheSubscriptionInfo(response.data);
                    displaySubscriptionInfo(response.data);
                    
                    // Show success notice if redirected from portal
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('portal_return') === 'success') {
                        // Notice will be shown by WordPress admin notice
                        // Clean up URL
                        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]portal_return=success/, '').replace(/^&/, '?');
                        if (cleanUrl !== window.location.pathname + window.location.search) {
                            window.history.replaceState({}, document.title, cleanUrl);
                        }
                        // Force refresh cache after portal update
                        setTimeout(function() {
                            loadSubscriptionInfo(true);
                        }, 2000);
                    }
                } else {
                    const plan = response.data?.plan || 'free';
                    if (plan === 'free') {
                        $freeMessage.show();
            } else {
                        // Provide better error messages
                        let errorMessage = response.data?.message || __('Failed to load subscription information.', 'beepbeep-ai-alt-text-generator');
                        
                        if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                            errorMessage = __('Please log in to view your subscription information.', 'beepbeep-ai-alt-text-generator');
                        } else if (errorMessage.toLowerCase().includes('not found')) {
                            errorMessage = __('Subscription not found. If you just upgraded, please wait a moment and refresh.', 'beepbeep-ai-alt-text-generator');
                        }
                        
                        showSubscriptionError(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                
                // Provide better error messages
                let errorMessage = __('Network error. Please try again.', 'beepbeep-ai-alt-text-generator');
                
                if (xhr.status === 401 || xhr.status === 403) {
                    errorMessage = __('Please log in to view your subscription information.', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    return; // Don't retry auth errors
                } else if (xhr.status === 404) {
                    errorMessage = __('Subscription information not found. If you just signed up, please wait a moment and refresh.', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    return; // Don't retry 404 errors
                } else if (xhr.status >= 500 || status === 'timeout' || status === 'error') {
                    errorMessage = __('Service temporarily unavailable. Retrying automatically...', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    
                    // Retry with exponential backoff
                    retrySubscriptionLoad();
                return;
            }

                showSubscriptionError(errorMessage);
            }
        });
    }

    /**
     * Display subscription information
     */
    function displaySubscriptionInfo(data) {
        // Use cached DOM elements for better performance
        const $info = getCachedElement('#bbai-subscription-info');
        const $error = getCachedElement('#bbai-subscription-error');
        const $freeMessage = getCachedElement('#bbai-free-plan-message');

        // Hide other states
        $error.hide();
        $freeMessage.hide();

        // Handle free plan
        if (!data.plan || data.plan === 'free' || data.status === 'free') {
            $freeMessage.show();
                    return;
                }

        // Display subscription status (using cached elements)
        const status = data.status || 'active';
        const statusBadge = getCachedElement('#bbai-status-badge');
        const statusLabel = statusBadge.find('.bbai-status-label');
        
        statusBadge.removeClass('bbai-status-active bbai-status-cancelled bbai-status-trial');
        statusBadge.addClass('bbai-status-' + status);
        statusLabel.text(status.charAt(0).toUpperCase() + status.slice(1));

        // Show cancel warning if needed
        const $cancelWarning = getCachedElement('#bbai-cancel-warning');
        if (data.cancelAtPeriodEnd) {
            $cancelWarning.show();
                } else {
            $cancelWarning.hide();
        }

        // Display plan details (using cached elements)
        const planName = data.plan ? data.plan.charAt(0).toUpperCase() + data.plan.slice(1) : '-';
        getCachedElement('#bbai-plan-name').text(planName);

        const billingCycle = data.billingCycle ? data.billingCycle.charAt(0).toUpperCase() + data.billingCycle.slice(1) : '-';
        getCachedElement('#bbai-billing-cycle').text(billingCycle);

        // Format next billing date
        if (data.nextBillingDate) {
            const date = new Date(data.nextBillingDate);
            getCachedElement('#bbai-next-billing').text(date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            }));
            } else {
            getCachedElement('#bbai-next-billing').text('-');
        }

        // Format next charge amount
        if (data.nextChargeAmount !== undefined && data.nextChargeAmount !== null) {
            const currency = data.currency || 'GBP';
            const symbol = currency === 'USD' ? '$' : currency === 'EUR' ? '€' : '£';
            getCachedElement('#bbai-next-charge').text(symbol + parseFloat(data.nextChargeAmount).toFixed(2));
            } else {
            getCachedElement('#bbai-next-charge').text('-');
        }

        // Display payment method if available (using cached elements)
        if (data.paymentMethod && data.paymentMethod.last4) {
            const $paymentMethod = getCachedElement('#bbai-payment-method');
            const brand = data.paymentMethod.brand || 'card';
            const last4 = data.paymentMethod.last4;
            const expMonth = data.paymentMethod.expMonth;
            const expYear = data.paymentMethod.expYear;

            getCachedElement('#bbai-card-brand').text(getCardBrandIcon(brand) + ' ' + brand.toUpperCase());
            getCachedElement('#bbai-card-last4').text('•••• ' + last4);
            if (expMonth && expYear) {
                getCachedElement('#bbai-card-expiry').text(expMonth + '/' + expYear.toString().slice(-2));
            }
            $paymentMethod.show();
        } else {
            getCachedElement('#bbai-payment-method').hide();
        }

        // Show subscription info
        $info.show();
    }

    /**
     * Retry subscription load with exponential backoff
     */
    let retryAttempts = 0;
    const maxRetries = 5;
    let retryTimeout = null;

    function retrySubscriptionLoad() {
        if (retryAttempts >= maxRetries) {
            showSubscriptionError(__('Service unavailable after multiple attempts. Please try again later or refresh the page.', 'beepbeep-ai-alt-text-generator'));
            retryAttempts = 0;
                return;
            }

        retryAttempts++;
        
        // Exponential backoff: 1s, 2s, 4s, 8s, 16s
        const delay = Math.min(1000 * Math.pow(2, retryAttempts - 1), 16000);
        
        const $error = $('#bbai-subscription-error');
        $error.find('.bbai-error-message').text(
            sprintf(
                __('Service temporarily unavailable. Retrying in %1$d seconds... (Attempt %2$d/%3$d)', 'beepbeep-ai-alt-text-generator'),
                Math.ceil(delay / 1000),
                retryAttempts,
                maxRetries
            )
        );

        // Clear any existing timeout
        if (retryTimeout) {
            clearTimeout(retryTimeout);
        }

        retryTimeout = setTimeout(function() {
            loadSubscriptionInfo(true); // Force refresh, bypass cache
        }, delay);
    }

    /**
     * Reset retry attempts on successful load
     */
    function resetRetryAttempts() {
        retryAttempts = 0;
        if (retryTimeout) {
            clearTimeout(retryTimeout);
            retryTimeout = null;
        }
    }

    /**
     * Show subscription error
     */
    function showSubscriptionError(message) {
        const $error = $('#bbai-subscription-error');
        const $info = $('#bbai-subscription-info');
        const $freeMessage = $('#bbai-free-plan-message');

        $error.find('.bbai-error-message').text(message);
        $error.show();
        $info.hide();
        $freeMessage.hide();
    }

    /**
     * Get card brand icon/emoji
     */
    function getCardBrandIcon(brand) {
        const icons = {
            'visa': '💳',
            'mastercard': '💳',
            'amex': '💳',
            'discover': '💳',
            'diners': '💳',
            'jcb': '💳',
            'unionpay': '💳'
        };
        return icons[brand.toLowerCase()] || '💳';
    }

    /**
     * Cache subscription info in localStorage
     */
    function cacheSubscriptionInfo(data) {
        try {
            const cacheData = {
                data: data,
                timestamp: Date.now(),
                expiry: 15 * 60 * 1000 // 15 minutes (optimized - subscription info doesn't change frequently)
            };
            localStorage.setItem('bbai_subscription_cache', JSON.stringify(cacheData));
        } catch (e) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Could not cache subscription info:', e);
        }
    }

    /**
     * Get cached subscription info if still valid
     */
    function getCachedSubscriptionInfo() {
        try {
            const cached = localStorage.getItem('bbai_subscription_cache');
            if (!cached) return null;

            const cacheData = JSON.parse(cached);
            const age = Date.now() - cacheData.timestamp;

            if (age < cacheData.expiry) {
                return cacheData;
            } else {
                // Cache expired, remove it
                localStorage.removeItem('bbai_subscription_cache');
                return null;
            }
        } catch (e) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Could not read subscription cache:', e);
            return null;
        }
    }

    /**
     * Load license site usage data
     * Fetches and displays sites using the agency license
     */
    window.loadLicenseSiteUsage = function loadLicenseSiteUsage() {
        const $sitesContent = $('#bbai-license-sites-content');
        if (!$sitesContent.length) {
            // This is expected for free/Pro users - element only exists for Agency licenses
            // Silently return without logging (no need to clutter console for expected behavior)
            return; // Not on settings page or not agency license
        }

        // Check if user is authenticated (JWT) or has active license (license key auth)
        const isAuthenticated = window.bbai_ajax && (
            window.bbai_ajax.is_authenticated === true ||
            (window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0)
        );

        // In Admin tab, check if admin is logged in (separate session)
        const isAdminTab = $('.bbai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // If we're in admin content, admin is authenticated

        // Check if there's an active license (license key authentication)
        // Note: We can't directly check this from JS, but the backend will handle it
        // So we'll always attempt the request if we're on the license page (it means license is active)

        // Allow if authenticated (JWT) OR admin authenticated OR on license settings page (has license)
        const isOnLicensePage = $sitesContent.length > 0; // If this element exists, we're on license page
        
        if (!isAuthenticated && !isAdminAuthenticated && !isOnLicensePage) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Not authenticated and not on license page, skipping license sites load');
            return; // Don't load if not authenticated and not on license page
        }

        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Loading license site usage...', {
            isAuthenticated: isAuthenticated,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated
        });

        // Show loading state
        $sitesContent.html(
            '<div class="bbai-settings-license-sites-loading">' +
            '<span class="bbai-spinner"></span> ' +
            __('Loading site usage...', 'beepbeep-ai-alt-text-generator') +
            '</div>'
        );

        // Fetch license site usage
        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_get_license_sites',
                nonce: window.bbai_ajax.nonce
            },
            success: function(response) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] License sites response:', response);
                if (response.success && response.data) {
                    // Handle both array response and object with sites property
                    const sites = Array.isArray(response.data) ? response.data : (response.data.sites || []);
                    if (sites && sites.length > 0) {
                        displayLicenseSites(sites);
                    } else {
                        $sitesContent.html(
                            '<div class="bbai-settings-license-sites-empty">' +
                            '<p>' + __('No sites are currently using this license.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                            '</div>'
                        );
                    }
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] License sites request failed:', response);
                    $sitesContent.html(
                        '<div class="bbai-settings-license-sites-error">' +
                        '<p>' + (response.data?.message || __('Failed to load site usage. Please try again.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to load license sites:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                $sitesContent.html(
                    '<div class="bbai-settings-license-sites-error">' +
                    '<p>' + __('Failed to load site usage. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                    '</div>'
                );
            }
        });
    };

    /**
     * Display license site usage data
     */
    window.displayLicenseSites = function displayLicenseSites(sites) {
        const $sitesContent = $('#bbai-license-sites-content');
        
        if (!sites || sites.length === 0) {
            $sitesContent.html(
                '<div class="bbai-settings-license-sites-empty">' +
                '<p>' + __('No sites are currently using this license.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                '</div>'
            );
            return;
        }

        let html = '<div class="bbai-settings-license-sites-list">';
        html += '<div class="bbai-settings-license-sites-summary">';
        html += '<strong>' + sites.length + '</strong> ' + _n('site using this license', 'sites using this license', sites.length, 'beepbeep-ai-alt-text-generator');
        html += '</div>';
        html += '<ul class="bbai-settings-license-sites-items">';

        sites.forEach(function(site) {
            const siteName = site.site_name || site.install_id || __('Unknown Site', 'beepbeep-ai-alt-text-generator');
            const siteId = site.siteId || site.install_id || site.installId || '';
            const generations = site.total_generations || site.generations || 0;
            const lastUsed = site.last_used ? new Date(site.last_used).toLocaleDateString() : __('Never', 'beepbeep-ai-alt-text-generator');
            const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
            const disconnectAriaLabel = sprintf(__('Disconnect %s', 'beepbeep-ai-alt-text-generator'), siteName);
            
            html += '<li class="bbai-settings-license-sites-item">';
            html += '<div class="bbai-settings-license-sites-item-main">';
            html += '<div class="bbai-settings-license-sites-item-info">';
            html += '<div class="bbai-settings-license-sites-item-name">' + escapeHtml(siteName) + '</div>';
            html += '<div class="bbai-settings-license-sites-item-stats">';
            html += '<span class="bbai-settings-license-sites-item-generations">';
            html += '<strong>' + generations.toLocaleString() + '</strong> ' + escapeHtml(__('alt text generated', 'beepbeep-ai-alt-text-generator'));
            html += '</span>';
            html += '<span class="bbai-settings-license-sites-item-last">';
            html += escapeHtml(__('Last used:', 'beepbeep-ai-alt-text-generator')) + ' ' + escapeHtml(lastUsed);
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="bbai-settings-license-sites-item-actions">';
            html += '<button type="button" class="bbai-settings-license-sites-disconnect-btn" ';
            html += 'data-site-id="' + escapeHtml(siteId) + '" ';
            html += 'data-site-name="' + escapeHtml(siteName) + '" ';
            html += 'aria-label="' + escapeHtml(disconnectAriaLabel) + '">';
            html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">';
            html += '<path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
            html += '</svg>';
            html += '<span>' + escapeHtml(disconnectLabel) + '</span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '</li>';
        });

        html += '</ul>';
        html += '</div>';

        $sitesContent.html(html);
        
        // Attach disconnect button handlers
        attachDisconnectHandlers();
    }

    /**
     * Attach event handlers for disconnect buttons
     */
    function attachDisconnectHandlers() {
        $(document).off('click', '.bbai-settings-license-sites-disconnect-btn');
        $(document).on('click', '.bbai-settings-license-sites-disconnect-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const siteId = $btn.data('site-id');
            const siteName = $btn.data('site-name') || siteId;
            
            if (!siteId) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No site ID provided for disconnect');
                return;
            }
            
            // Confirm disconnect action
            // For confirm dialog, we need plain text (not HTML-escaped)
            const siteNameText = siteName.replace(/"/g, '&quot;').replace(/\n/g, ' ');
            if (!confirm(sprintf(__('Are you sure you want to disconnect "%s"?\n\nThis will remove the site from your license. The site will need to reconnect using the license key.', 'beepbeep-ai-alt-text-generator'), siteNameText))) {
                return;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true)
                .addClass('bbai-processing')
                .html('<span class="bbai-spinner"></span> ' + __('Disconnecting...', 'beepbeep-ai-alt-text-generator'));
            
            // Make AJAX request to disconnect site
            $.ajax({
                url: window.bbai_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'beepbeepai_disconnect_license_site',
                    site_id: siteId,
                    nonce: window.bbai_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Site disconnected successfully:', siteName);
                        
                        // Reload the license sites list
                        loadLicenseSiteUsage();
                    } else {
                        // Show error message
                        const details = response.data?.message || __('Unknown error', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(sprintf(__('Failed to disconnect site: %s', 'beepbeep-ai-alt-text-generator'), details));
                        
                        // Restore button
                        const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
                        $btn.prop('disabled', false)
                            .removeClass('bbai-processing')
                            .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>' + escapeHtml(disconnectLabel) + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to disconnect site:', error);
                    window.bbaiModal.error(__('Failed to disconnect site. Please try again.', 'beepbeep-ai-alt-text-generator'));
                    
                    // Restore button
                    const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
                    $btn.prop('disabled', false)
                        .removeClass('bbai-processing')
                        .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>' + escapeHtml(disconnectLabel) + '</span>');
                }
            });
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Open Stripe Customer Portal
     * Opens Stripe billing portal in new tab for subscription management
     */
    function openCustomerPortal() {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening customer portal...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                    return;
                }
        
        // Check authentication before making the request - check multiple sources
        const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
        const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
        
        // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
        const isAdminTab = $('.bbai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
        
        const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
        
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] openCustomerPortal() - auth check:', {
            hasAjax: !!window.bbai_ajax,
            ajaxAuth: ajaxAuth,
            userDataAuth: userDataAuth,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated,
            isAuthenticated: isAuthenticated,
            authValue: window.bbai_ajax?.is_authenticated,
            userData: window.bbai_ajax?.user_data
        });
        
        if (!isAuthenticated) {
            // Check if we're trying to open portal after login (in which case, don't show modal again)
            const isAfterLogin = sessionStorage.getItem('bbai_portal_after_login') === 'true';
            
            if (isAfterLogin) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal opened after login but auth check failed - waiting for auth state...');
                // Wait a bit and retry
                setTimeout(function() {
                    const retryAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
                    if (retryAuth) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth state now ready, retrying portal...');
                        openCustomerPortal();
                    } else {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Auth state still not ready after retry');
                        sessionStorage.removeItem('bbai_portal_after_login');
                    }
                }, 1000);
                return;
            }
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Not authenticated in openCustomerPortal, showing login modal');
            // User not authenticated - show login modal instead
            localStorage.setItem('bbai_open_portal_after_login', 'true');
            
            // Show login modal - try multiple methods
            let modalShown = false;
            
            if (typeof window.authModal !== 'undefined' && window.authModal) {
                if (typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    if (typeof window.authModal.showLoginForm === 'function') {
                        window.authModal.showLoginForm();
                    }
                    modalShown = true;
                }
            }
            
            if (!modalShown && typeof showAuthModal === 'function') {
                showAuthModal('login');
                modalShown = true;
            }
            
            if (!modalShown) {
                // Fallback: try to show auth modal manually
                const authModal = document.getElementById('alttext-auth-modal');
                if (authModal) {
                    authModal.style.display = 'block';
                    authModal.removeAttribute('aria-hidden');
                    authModal.setAttribute('data-bbai-auth-modal-visible', '1');
                    document.body.classList.add('bbai-auth-modal-open');
                    document.body.style.overflow = 'hidden';
                    
                    // Try to show login form
                    const loginForm = document.getElementById('login-form');
                    const registerForm = document.getElementById('register-form');
                    if (loginForm) loginForm.style.display = 'block';
                    if (registerForm) registerForm.style.display = 'none';
                    modalShown = true;
                }
            }
            
            if (!modalShown) {
                window.bbaiModal.warning(__('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.', 'beepbeep-ai-alt-text-generator'));
            }
            
            return;
        }

        // Find all manage subscription buttons
        const $buttons = $('[data-action="manage-subscription"], #bbai-update-payment-method, #bbai-manage-subscription');
        
        // Show loading state with visual feedback
        $buttons.prop('disabled', true)
                .addClass('bbai-btn-loading')
                .attr('aria-busy', 'true');
        
        // Update button text temporarily
        const originalText = {};
        $buttons.each(function() {
            const $btn = $(this);
            originalText[$btn.attr('id') || 'btn'] = $btn.text();
            $btn.html('<span class="bbai-spinner"></span> ' + __('Opening portal...', 'beepbeep-ai-alt-text-generator'));
        });

                $.ajax({
            url: window.bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                action: 'beepbeepai_create_portal',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal response:', response);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || __('Manage Subscription', 'beepbeep-ai-alt-text-generator'));
                });

                if (response.success && response.data && response.data.url) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening portal URL:', response.data.url);
                    
                    // Open portal in new tab
                    const portalWindow = window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    
                    if (!portalWindow) {
                        window.bbaiModal.warning(__('Please allow popups for this site to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                        return;
                    }
                    
                    // Monitor for user return and refresh subscription data
                    let checkCount = 0;
                    const maxChecks = 150; // 5 minutes max
                    
                    const checkInterval = setInterval(function() {
                        checkCount++;
                        if (document.hasFocus() || checkCount >= maxChecks) {
                            clearInterval(checkInterval);
                            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User returned, refreshing data...');
                            
                            // Reload subscription info
                            if (typeof loadSubscriptionInfo === 'function') {
                                loadSubscriptionInfo(true); // Force refresh
                            }
                            
                            // Refresh usage stats on dashboard
                            if (typeof window.alttextai_refresh_usage === 'function') {
                                window.alttextai_refresh_usage();
                            }
                        }
                    }, 2000);
                } else {
                    // Provide context-aware error messages
                    let errorMessage = response.data?.message || __('Failed to open customer portal. Please try again.', 'beepbeep-ai-alt-text-generator');
                    
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal request failed:', errorMessage);
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login') || errorMessage.toLowerCase().includes('authentication required')) {
                        // This shouldn't happen since we check auth first, but handle it gracefully
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Authentication error from server, showing login modal');
                        localStorage.setItem('bbai_open_portal_after_login', 'true');
                        
                        // Try to show login modal instead of alert
                        let modalShown = false;
                        if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                            window.authModal.show();
                            window.authModal.showLoginForm();
                            // Don't show error message - the login modal itself indicates authentication is required
                            modalShown = true;
                        } else if (typeof showAuthModal === 'function') {
                            showAuthModal('login');
                            modalShown = true;
                        } else {
                            const authModal = document.getElementById('alttext-auth-modal');
                            if (authModal) {
                                authModal.style.display = 'block';
                                authModal.removeAttribute('aria-hidden');
                                authModal.setAttribute('data-bbai-auth-modal-visible', '1');
                                document.body.classList.add('bbai-auth-modal-open');
                                document.body.style.overflow = 'hidden';
                                const loginForm = document.getElementById('login-form');
                                const registerForm = document.getElementById('register-form');
                                if (loginForm) loginForm.style.display = 'block';
                                if (registerForm) registerForm.style.display = 'none';
                                modalShown = true;
                            }
                        }
                        
                        if (!modalShown) {
                            window.bbaiModal.warning(__('Please log in first to manage your billing.\n\nClick the "Login" button in the top navigation.', 'beepbeep-ai-alt-text-generator'));
                        }
                    } else if (errorMessage.toLowerCase().includes('not found') || errorMessage.toLowerCase().includes('subscription')) {
                        errorMessage = __('No active subscription found.\n\nPlease upgrade to a paid plan first, then you can manage your subscription.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMessage);
                    } else if (errorMessage.toLowerCase().includes('customer')) {
                        errorMessage = __('Unable to find your billing account.\n\nPlease contact support for assistance.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMessage);
                    } else {
                    window.bbaiModal.error(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Portal error:', status, error, xhr);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || __('Manage Subscription', 'beepbeep-ai-alt-text-generator'));
                });
                
                // Provide helpful error message based on status
                let errorMessage = __('Unable to connect to billing system. Please try again.', 'beepbeep-ai-alt-text-generator');
                
                if (status === 'timeout') {
                    errorMessage = __('Request timed out. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr.status === 0) {
                    errorMessage = __('Network connection lost. Please check your internet and try again.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr.status >= 500) {
                    errorMessage = __('Billing system is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
                }
                
                window.bbaiModal.error(errorMessage);
            }
        });
    }

    /**
     * Initiate Stripe Checkout
     * Creates checkout session and opens in new tab
     */
    function resolveCheckoutPriceId($button, priceId, planName) {
        const upgradePriceIds = (window.BBAI_UPGRADE && window.BBAI_UPGRADE.priceIds) || {};
        const dashboardPriceIds = (window.BBAI_DASH && window.BBAI_DASH.checkoutPrices) || {};

        return priceId
            || ($button && $button.attr('data-price-id'))
            || upgradePriceIds[planName]
            || dashboardPriceIds[planName]
            || '';
    }

    function resolveCheckoutFallbackUrl($button, planName) {
        const fallbackUrl = ($button && $button.attr('data-fallback-url')) || '';
        const stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
        let resolvedLink = fallbackUrl || stripeLinks[planName] || '';

        if (!resolvedLink) {
            if (planName === 'pro' || planName === 'growth') {
                resolvedLink = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
            } else if (planName === 'agency') {
                resolvedLink = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
            } else if (planName === 'credits') {
                resolvedLink = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
            }
        }

        return resolvedLink;
    }

    function openCheckoutUrl(url) {
        if (!url) {
            return false;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
        if (typeof alttextaiCloseModal === 'function') {
            alttextaiCloseModal();
        }

        return true;
    }

    function setCheckoutButtonLoading($button, isLoading) {
        if (!$button || !$button.length) {
            return;
        }

        if (isLoading) {
            if (!$button.data('bbaiCheckoutLabel')) {
                $button.data('bbaiCheckoutLabel', $button.text());
            }

            $button.prop('disabled', true)
                .addClass('bbai-btn-loading')
                .attr('aria-busy', 'true')
                .text(__('Redirecting…', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        $button.prop('disabled', false)
            .removeClass('bbai-btn-loading')
            .attr('aria-busy', 'false')
            .text($button.data('bbaiCheckoutLabel') || $button.text());
    }

    function getCheckoutAttributionPayload(planName) {
        if (window.bbaiTelemetry && typeof window.bbaiTelemetry.buildCheckoutAttribution === 'function') {
            return window.bbaiTelemetry.buildCheckoutAttribution({
                target_plan: planName || '',
                source: 'app'
            });
        }

        var context = (window.bbaiAnalytics && typeof window.bbaiAnalytics.getContext === 'function')
            ? (window.bbaiAnalytics.getContext() || {})
            : ((window.BBAI_POSTHOG && window.BBAI_POSTHOG.context) || {});

        var payload = {
            account_id: context.account_id || '',
            user_id: context.user_id || '',
            license_key: context.license_key || '',
            site_id: context.site_id || '',
            site_hash: context.site_hash || '',
            email: context.email || '',
            trigger_feature: 'unknown',
            trigger_location: 'unknown',
            source_page: context.page || 'dashboard',
            target_plan: planName || '',
            current_plan: context.plan_type || context.plan || '',
            source: 'app'
        };

        if (!payload.email) {
            delete payload.email;
        }

        return payload;
    }

    function initiateCheckout($button, priceId, planName) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Initiating checkout:', planName, priceId);

        const ajaxUrl = window.bbai_ajax && window.bbai_ajax.ajaxurl;
        const nonce = window.bbai_ajax && window.bbai_ajax.nonce;
        const resolvedPriceId = resolveCheckoutPriceId($button, priceId, planName);
        const fallbackUrl = resolveCheckoutFallbackUrl($button, planName);
        const attributionPayload = getCheckoutAttributionPayload(planName);

        if (!ajaxUrl || !nonce || !resolvedPriceId || !window.jQuery || typeof $.ajax !== 'function') {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Falling back to Stripe payment link:', fallbackUrl);
            if (openCheckoutUrl(fallbackUrl)) {
                return;
            }
        } else {
            setCheckoutButtonLoading($button, true);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: $.extend({
                    action: 'beepbeepai_create_checkout',
                    nonce: nonce,
                    price_id: resolvedPriceId,
                    plan_id: planName || ''
                }, attributionPayload)
            }).done(function(response) {
                const checkoutData = response && response.success && response.data ? response.data : {};
                const checkoutUrl = checkoutData && checkoutData.url ? checkoutData.url : '';
                const checkoutSessionId = checkoutData && (checkoutData.session_id || checkoutData.sessionId)
                    ? String(checkoutData.session_id || checkoutData.sessionId)
                    : '';
                const invalidHostedSession = checkoutUrl
                    && /checkout\.stripe\.com\/c\/pay\//i.test(checkoutUrl)
                    && checkoutSessionId === '';

                setCheckoutButtonLoading($button, false);

                if (checkoutUrl && !invalidHostedSession) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening Stripe checkout session:', checkoutUrl);
                    openCheckoutUrl(checkoutUrl);
                    return;
                }

                if (invalidHostedSession && alttextaiDebug) {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Hosted checkout response missing session ID, falling back to payment link', checkoutData);
                }

                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Checkout session missing URL, falling back to payment link');
                if (openCheckoutUrl(fallbackUrl)) {
                    return;
                }

                if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                    window.bbaiModal.error(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
                }
            }).fail(function(xhr) {
                setCheckoutButtonLoading($button, false);

                if (alttextaiDebug) {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Checkout session creation failed, falling back to payment link', {
                        status: xhr && xhr.status,
                        response: xhr && xhr.responseJSON ? xhr.responseJSON : null
                    });
                }

                if (openCheckoutUrl(fallbackUrl)) {
                    return;
                }

                const errorMessage = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                    || __('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');

                if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                    window.bbaiModal.error(errorMessage);
                }
            });

            return;
        }

        // No link available
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No Stripe payment link available for plan:', planName);
        if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
        }
    }

    /**
     * Disconnect Account
     * Clears authentication and allows another admin to connect
     */
    function disconnectAccount($button) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Disconnecting account...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('bbai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalText = $button.text();
        $button.html('<span class="bbai-spinner"></span> ' + __('Disconnecting...', 'beepbeep-ai-alt-text-generator'));

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_disconnect_account',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 15000, // 15 second timeout
            success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Disconnect response:', response);
                
	                if (response.success) {
	                    // Show success message
	                    $button.removeClass('bbai-btn-loading')
	                           .removeClass('bbai-btn--ghost')
	                           .addClass('bbai-btn--success')
	                           .html('✓ ' + __('Disconnected', 'beepbeep-ai-alt-text-generator'))
	                           .attr('aria-busy', 'false');
                    
                    // Clear any cached data
                    if (typeof localStorage !== 'undefined') {
                        localStorage.removeItem('bbai_subscription_cache');
                        localStorage.removeItem('alttextai_token');
                    }
                    
                    // Reload after brief delay to show success state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
	                    const errorMsg = response.data?.message || __('Failed to disconnect account. Please try again.', 'beepbeep-ai-alt-text-generator');
	                    window.bbaiModal.error(errorMsg);
	                }
	            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Disconnect error:', status, error);
                
                // Restore button
	                $button.prop('disabled', false)
	                       .removeClass('bbai-btn-loading')
	                       .text(originalText)
	                       .attr('aria-busy', 'false');
	                
	                // Provide helpful error message
	                let errorMessage = __('Unable to disconnect account. Please try again.', 'beepbeep-ai-alt-text-generator');
	                
	                if (status === 'timeout') {
	                    errorMessage = __('Request timed out. Your connection may still be disconnected. Try refreshing the page.', 'beepbeep-ai-alt-text-generator');
	                } else if (xhr.status === 0) {
	                    errorMessage = __('Network error. Please check your connection and try again.', 'beepbeep-ai-alt-text-generator');
	                }
	                
	                window.bbaiModal.error(errorMessage);
	            }
	        });
	    }

});

// Debug mode check (define early so it can be used in functions)
var alttextaiDebug = (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false;

function findUpgradeModalElement() {
    var modalById = document.getElementById('bbai-upgrade-modal');
    if (modalById && modalById.querySelector('.bbai-upgrade-modal__content')) {
        if (document.body && modalById.parentNode !== document.body) {
            document.body.appendChild(modalById);
        }
        return modalById;
    }

    var modalByData = document.querySelector('[data-bbai-upgrade-modal="1"]');
    if (modalByData && modalByData.querySelector('.bbai-upgrade-modal__content')) {
        if (modalByData.id !== 'bbai-upgrade-modal') {
            modalByData.id = 'bbai-upgrade-modal';
        }
        if (document.body && modalByData.parentNode !== document.body) {
            document.body.appendChild(modalByData);
        }
        return modalByData;
    }

    return null;
}

// Check if modal exists when script loads
(function() {
    'use strict';
    function checkModalExists() {
        const modal = findUpgradeModalElement();
        if (!modal) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Upgrade modal not found in DOM. Make sure upgrade-modal.php is included.');
        } else {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Upgrade modal found in DOM');
        }
    }
    
    // Check immediately and also after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkModalExists);
    } else {
        checkModalExists();
    }
})();

// Global app object to avoid collisions
var bbaiApp = bbaiApp || {};

function bbaiSetUpgradeModalScrollLock(isLocked) {
    if (document.body && document.body.classList) {
        document.body.classList.toggle('modal-open', !!isLocked);
        document.body.classList.toggle('bbai-modal-open', !!isLocked);
    }

    if (document.body) {
        document.body.style.overflow = isLocked ? 'hidden' : '';
    }

    if (document.documentElement) {
        document.documentElement.style.overflow = isLocked ? 'hidden' : '';
    }
}

// Global functions for modal - make it very robust (legacy support)
function alttextaiShowModal() {
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] alttextaiShowModal() called');
    if (typeof window.bbaiOpenUpgradeModal === 'function') {
        try {
            if (window.bbaiOpenUpgradeModal('default', { source: 'alttextaiShowModal', triggerKey: 'manual' })) {
                return true;
            }
        } catch (err) {
            if (typeof alttextaiDebug !== 'undefined' && alttextaiDebug && window.BBAI_LOG) {
                window.BBAI_LOG.warn('[AltText AI] bbaiOpenUpgradeModal failed', err);
            }
        }
    }
    const modal = findUpgradeModalElement();
    
    if (!modal) {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Upgrade modal element not found in DOM!');
        if (window.bbaiModal && typeof window.bbaiModal.warning === 'function') {
            window.bbaiModal.warning(__('Upgrade modal not found. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
        }
        return false;
    }
    
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Modal element found:', modal);
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Current display:', window.getComputedStyle(modal).display);
    
    // Remove inline display:none to allow transition
    if (modal.style.display === 'none') {
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
    }
    
    // Force reflow to ensure initial state is applied
    void modal.offsetHeight;
    
    // Now add active class to trigger smooth animation
    modal.classList.add('active');
    modal.classList.add('is-visible');
    modal.removeAttribute('aria-hidden');
    bbaiSetUpgradeModalScrollLock(true);
    
    // Focus the close button after animation starts
    setTimeout(function() {
        const closeBtn = modal.querySelector('.bbai-upgrade-modal__close, .bbai-modal-close, [data-action="close-modal"], button[aria-label*="Close"]');
        if (closeBtn && typeof closeBtn.focus === 'function') {
            closeBtn.focus();
        }
    }, 150);
    
    return true;
}

// Make it available globally for onclick handlers (legacy support)
window.alttextaiShowModal = alttextaiShowModal;

// Also create a simple global function that always tries to show the modal
window.showUpgradeModal = function() {
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] window.showUpgradeModal() called');
    
    // Try the main function first
    if (typeof alttextaiShowModal === 'function') {
        if (alttextaiShowModal()) {
            return true;
        }
    }
    
    // Direct DOM manipulation as ultimate fallback
    var modal = findUpgradeModalElement();
    
    if (modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Found modal, showing directly');
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        modal.style.zIndex = '999999';
        modal.classList.add('active');
        modal.classList.add('is-visible');
        modal.removeAttribute('aria-hidden');
        bbaiSetUpgradeModalScrollLock(true);
        return true;
    }
    
    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Modal not found in DOM');
    return false;
};

// Provide legacy/global aliases used by dashboard bridge code.
window.bbaiShowUpgradeModal = window.showUpgradeModal;
window.alttextaiShowUpgradeModal = window.showUpgradeModal;

// Also add to bbaiApp namespace
bbaiApp.showModal = alttextaiShowModal;

// Also create a simple test function
window.testUpgradeModal = function() {
    window.BBAI_LOG && window.BBAI_LOG.log('=== Testing Upgrade Modal ===');
    const modal = findUpgradeModalElement();
    window.BBAI_LOG && window.BBAI_LOG.log('Modal element:', modal);
    if (modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('Modal HTML:', modal.outerHTML.substring(0, 200));
        window.BBAI_LOG && window.BBAI_LOG.log('Modal classes:', modal.className);
        window.BBAI_LOG && window.BBAI_LOG.log('Modal inline style:', modal.getAttribute('style'));
        alttextaiShowModal();
    } else {
        window.BBAI_LOG && window.BBAI_LOG.error('Modal not found!');
    }
};

function alttextaiCloseModal() {
    const modal = findUpgradeModalElement();
    if (modal) {
        modal.classList.remove('active');
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        const modalContent = modal.querySelector('.bbai-upgrade-modal__content');
        if (modalContent) {
            modalContent.removeAttribute('style');
        }
        modal.style.display = 'none';
        bbaiSetUpgradeModalScrollLock(false);
        if (typeof window.bbaiResetUpgradeModalContext === 'function') {
            window.bbaiResetUpgradeModalContext();
        }
    }
    if (typeof window.bbaiEnsureDashboardMainVisible === 'function') {
        window.bbaiEnsureDashboardMainVisible();
    }
}

// Add to bbaiApp namespace
bbaiApp.closeModal = alttextaiCloseModal;

// Make it available globally for onclick handlers (legacy support)
window.alttextaiCloseModal = alttextaiCloseModal;

// Global fallback handler for upgrade buttons (works even if jQuery isn't ready)
// This ensures the upgrade modal works on all tabs, even if jQuery handlers fail
(function() {
    'use strict';
    
    // Function to show modal directly with aggressive approach
    function showModalDirectly() {
        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            try {
                if (window.bbaiOpenUpgradeModal('default', { source: 'showModalDirectly', triggerKey: 'manual' })) {
                    return true;
                }
            } catch (err) {
                if (typeof alttextaiDebug !== 'undefined' && alttextaiDebug && window.BBAI_LOG) {
                    window.BBAI_LOG.warn('[AltText AI] bbaiOpenUpgradeModal failed in showModalDirectly', err);
                }
            }
        }
        const modal = findUpgradeModalElement();
        if (!modal) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Upgrade modal not found in DOM');
            return false;
        }
        
        // Remove inline style completely, then set with !important
        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
        modal.classList.add('active');
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        bbaiSetUpgradeModalScrollLock(true);
        
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Modal shown via direct method');
        return true;
    }
    
    // Use event delegation on document to catch all clicks (capture phase for early handling)
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented) {
            return;
        }

        // Check if clicked element or its parent has data-action="show-upgrade-modal"
        const trigger = e.target.closest('[data-action="show-upgrade-modal"]');
        if (trigger) {
            if (bbaiIsGenerationActionControl(trigger) && bbaiIsLockedActionControl(trigger)) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                    window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
                } else {
                    showUpgradeModalFallback();
                }
                return false;
            }

            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Global vanilla JS handler: Upgrade CTA clicked', trigger);
            
            // Prevent default immediately
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            
            // Show modal directly - don't wait for anything
            if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal();
            } else {
                showModalDirectly();
            }
            
            return false; // Prevent any further event handling
        }
        
        // Handle close button clicks
        const closeBtn = e.target.closest('.bbai-modal-close, [onclick*="alttextaiCloseModal"]');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    bbaiSetUpgradeModalScrollLock(false);
                }
            }
            return false;
        }
        
        // Handle backdrop clicks (click outside modal content to close)
        const modal = findUpgradeModalElement();
        if (modal && modal.style.display === 'flex' && e.target === modal) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                bbaiSetUpgradeModalScrollLock(false);
            }
            return false;
        }
    }, true); // Use capture phase to catch events early
    
    // Handle ESC key globally to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const modal = findUpgradeModalElement();
            if (modal && modal.style.display === 'flex') {
                e.preventDefault();
                if (typeof alttextaiCloseModal === 'function') {
                    alttextaiCloseModal();
                } else {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    bbaiSetUpgradeModalScrollLock(false);
                }
            }
        }
    });
})();

function openStripeLink(url) {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

function showAuthBanner() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth banner');
    
    // Try to show the auth modal
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                        } else {
        // Try to find and show the auth modal directly
        const authModal = document.getElementById('alttext-auth-modal');
	        if (authModal) {
	            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth modal directly');
	            authModal.style.display = 'block';
	            authModal.removeAttribute('aria-hidden');
	            authModal.setAttribute('data-bbai-auth-modal-visible', '1');
	            document.body.classList.add('bbai-auth-modal-open');
	            document.body.style.overflow = 'hidden';
	        } else {
	            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal not found');
	            window.bbaiModal.error(__('Authentication system not available. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
	        }
	    }
	}

function showAuthLogin() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth login');
    
    // Try multiple methods to show auth modal (check bbaiApp namespace first)
    if (window.bbaiApp && window.bbaiApp.authModal && typeof window.bbaiApp.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using bbaiApp.authModal.show()');
        window.bbaiApp.authModal.show();
        return;
    } else if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
        // Legacy fallback
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using AltTextAuthModal.show()');
        window.AltTextAuthModal.show();
        return;
    }

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                    return;
                }

    // Fallback to showAuthBanner method
    showAuthBanner();
}

function showAuthModal(tab) {
    var analyticsSource = 'dashboard';

    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth modal, tab:', tab);
    if (window.bbaiAnalytics && typeof window.bbaiAnalytics.resolveSource === 'function') {
        analyticsSource = window.bbaiAnalytics.resolveSource(document.activeElement);
    }
    if (typeof emitDashboardAnalyticsEvent === 'function') {
        emitDashboardAnalyticsEvent(
            tab === 'register' ? 'signup_cta_clicked' : 'login_modal_opened',
            {
                source: analyticsSource
            }
        );
    }
    
    // Try to show auth modal with specific tab
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
        
        // Switch to the appropriate form
        if (tab === 'register' && typeof window.authModal.showRegisterForm === 'function') {
            window.authModal.showRegisterForm();
        } else if (typeof window.authModal.showLoginForm === 'function') {
            window.authModal.showLoginForm();
        }
        return;
    }

    // Fallback to showAuthLogin
    showAuthLogin();
}

function handleLogout() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Handling logout');
    
    // Make AJAX request to logout
    if (typeof window.bbai_ajax === 'undefined') {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] bbai_ajax object not found');
        // Still try to clear token and reload
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('alttextai_token');
        }
        window.location.reload();
                    return;
                }

    const ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || '';
    const nonce = window.bbai_ajax.nonce || '';
    
    if (!ajaxUrl) {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX URL not found');
        window.location.reload();
                    return;
                }

    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Calling logout AJAX:', ajaxUrl);
    
    // Try jQuery first, fallback to vanilla JS
    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
        jQuery.ajax({
            url: ajaxUrl,
                type: 'POST',
                    data: {
                action: 'beepbeepai_logout',
                nonce: nonce
                    },
                    success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout successful', response);
                // Clear any local storage
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
            // Redirect to plugin dashboard/sign-up page after logout
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
                    },
                    error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout failed:', error, xhr.responseText);
                // Even on error, clear local storage and reload
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
            }
        });
                        } else {
        // Vanilla JS fallback
        const formData = new FormData();
        formData.append('action', 'beepbeepai_logout');
        formData.append('nonce', nonce);
        
        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout response status:', response.status);
            return response.json().catch(() => ({}));
        })
        .then(data => {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout successful', data);
            // Clear any local storage
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        })
        .catch(error => {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout failed:', error);
            // Even on error, clear local storage and reload
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        });
    }
}

// Initialize auth modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] DOM loaded, initializing auth system');
    
    // Vanilla JS fallback for logout button (in case jQuery isn't ready)
    const logoutBtn = document.getElementById('bbai-logout-btn');
    if (logoutBtn) {
        // Remove any existing listeners to avoid duplicates
        const newLogoutBtn = logoutBtn.cloneNode(true);
        logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);
        
        // Add vanilla JS event listener
        newLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button clicked (Vanilla JS)');
            handleLogout();
        });
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button found and listener attached');
    } else {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button not found');
    }
    
    // Check if auth modal exists and initialize it (debug only)
    if (alttextaiDebug) {
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal found');
        } else {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal not found - may need to be created');
        }
        
        // Check if authModal object exists
        if (typeof window.authModal !== 'undefined') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] authModal object found');
        } else {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] authModal object not found');
        }
    }
    
    // Initialize countdown timer
    initCountdownTimer();
    
    // Also try initializing after a short delay in case DOM isn't ready
    setTimeout(function() {
        if (!document.querySelector('.bbai-countdown[data-countdown]')) {
            return; // Still not found
        }
        // Re-initialize if not already running
        if (!window.alttextaiCountdownInterval) {
            initCountdownTimer();
        }
    }, 500);
});

/**
 * Initialize and update countdown timer for limit reset
 */
function initCountdownTimer() {
    const countdownElement = document.querySelector('.bbai-countdown[data-countdown]');
    if (!countdownElement) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown element not found');
        return; // No countdown on this page
    }

    const totalSeconds = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
    
    // Check if we have valid seconds
    if (totalSeconds <= 0) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown has zero or invalid seconds:', totalSeconds);
        return;
    }

    const daysEl = countdownElement.querySelector('[data-days]');
    const hoursEl = countdownElement.querySelector('[data-hours]');
    const minutesEl = countdownElement.querySelector('[data-minutes]');

    if (!daysEl || !hoursEl || !minutesEl) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Countdown elements not found', {
            days: !!daysEl,
            hours: !!hoursEl,
            minutes: !!minutesEl
        });
        return;
    }

    // Store initial seconds and start time for accurate countdown
    countdownElement.setAttribute('data-initial-seconds', totalSeconds.toString());
    countdownElement.setAttribute('data-start-time', (Date.now() / 1000).toString());

    if (alttextaiDebug) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown initialized:', {
            totalSeconds: totalSeconds,
            days: Math.floor(totalSeconds / 86400),
            hours: Math.floor((totalSeconds % 86400) / 3600),
            minutes: Math.floor((totalSeconds % 3600) / 60)
        });
    }

    function updateCountdown() {
        // Try to use reset timestamp first (most accurate)
        const resetTimestamp = parseInt(countdownElement.getAttribute('data-reset-timestamp'), 10) || 0;
        let remaining = 0;
        
        if (resetTimestamp > 0) {
            // Calculate from actual reset timestamp
            const currentTime = Math.floor(Date.now() / 1000);
            remaining = Math.max(0, resetTimestamp - currentTime);
        } else {
            // Fallback: use elapsed seconds method
        const initialSeconds = parseInt(countdownElement.getAttribute('data-initial-seconds'), 10) || 0;
            
            if (initialSeconds <= 0) {
                daysEl.textContent = '0';
                hoursEl.textContent = '0';
                minutesEl.textContent = '0';
                if (window.alttextaiCountdownInterval) {
                    clearInterval(window.alttextaiCountdownInterval);
                    window.alttextaiCountdownInterval = null;
                }
                return;
            }
        
        // Calculate elapsed time since page load
        const startTime = parseFloat(countdownElement.getAttribute('data-start-time')) || (Date.now() / 1000);
        const currentTime = Date.now() / 1000;
            const elapsed = Math.max(0, Math.floor(currentTime - startTime));
        
        // Calculate remaining seconds
            remaining = Math.max(0, initialSeconds - elapsed);
        }

        if (remaining <= 0) {
            daysEl.textContent = '0';
            hoursEl.textContent = '0';
            minutesEl.textContent = '0';
            countdownElement.setAttribute('data-countdown', '0');
            if (window.alttextaiCountdownInterval) {
                clearInterval(window.alttextaiCountdownInterval);
                window.alttextaiCountdownInterval = null;
            }
            return;
        }

        // Calculate days, hours, minutes
        const days = Math.floor(remaining / 86400);
        const hours = Math.floor((remaining % 86400) / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);

        // Update display
        daysEl.textContent = days.toString();
        hoursEl.textContent = hours.toString();
        minutesEl.textContent = minutes.toString();
        
        // Update the data-countdown attribute for debugging
        countdownElement.setAttribute('data-countdown', remaining);
    }

    // Update immediately
    updateCountdown();

    // Clear any existing interval
    if (window.alttextaiCountdownInterval) {
        clearInterval(window.alttextaiCountdownInterval);
    }

    // Update every second continuously
    window.alttextaiCountdownInterval = setInterval(function() {
        updateCountdown();
    }, 1000); // Update every second
}

/**
 * ALT Library - Search and Filter Functionality
 */
bbaiRunWithJQuery(function($) {
    'use strict';

    $(document).ready(function() {
        // Search functionality (debounced to avoid excessive filtering while typing)
        var searchDebounceTimer;
        $('#bbai-library-search').on('input', function() {
            var $input = $(this);
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function() {
                var searchTerm = $input.val().toLowerCase();
                filterLibraryRows(searchTerm, getActiveFilter());
            }, 300);
        });

        // Filter buttons
        $('.bbai-filter-btn').on('click', function() {
            const $btn = $(this);
            const filter = $btn.attr('data-filter');
            
            // Toggle active state
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), null);
            } else {
                $('.bbai-filter-btn').removeClass('active');
                $btn.addClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), filter);
            }
        });

        /**
         * Get currently active filter
         */
        function getActiveFilter() {
            const $activeBtn = $('.bbai-filter-btn.active');
            return $activeBtn.length ? $activeBtn.attr('data-filter') : null;
        }

        /**
         * Filter library table rows based on search and filter
         */
        function filterLibraryRows(searchTerm, filter) {
            let visibleCount = 0;
            
            $('.bbai-library-row').each(function() {
                const $row = $(this);
                const status = $row.attr('data-status');
                const attachmentId = $row.attr('data-attachment-id');
                const rowText = $row.text().toLowerCase();
                
                // Check search match
                const matchesSearch = !searchTerm || rowText.includes(searchTerm);
                
                // Check filter match
                let matchesFilter = true;
                if (filter) {
                    if (filter === 'missing') {
                        matchesFilter = status === 'missing';
                    } else if (filter === 'has-alt') {
                        matchesFilter = status === 'optimized' || status === 'regenerated';
                    } else if (filter === 'regenerated') {
                        matchesFilter = status === 'regenerated';
                    } else if (filter === 'recent') {
                        // Show images from last 30 days (would need date data)
                        matchesFilter = true; // Simplified for now
                    }
                }
                
                // Show/hide row
                if (matchesSearch && matchesFilter) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });
            
            // Show/hide empty state
            if (visibleCount === 0 && $('.bbai-library-table tbody tr').length > 1) {
                // Could show a "No results" message here
                window.BBAI_LOG && window.BBAI_LOG.log('No matching rows found');
            }
        }
    });

    /**
     * SEO Character Counter
     * Displays 125-character count for Google Images optimization
     */
    window.bbaiCharCounter = {
        /**
         * Create a character counter element
         * @param {string} text - The alt text to count
         * @param {Object} options - Configuration options
         * @returns {string} HTML string for the counter
         */
        create: function(text, options) {
            options = options || {};
            var charCount = text ? text.length : 0;
            var maxChars = options.maxChars || 125;
            var isOptimal = charCount <= maxChars;
            var isEmpty = charCount === 0;

            var stateClass = isEmpty ? 'bbai-char-counter--empty' :
                           (isOptimal ? 'bbai-char-counter--optimal' : 'bbai-char-counter--warning');

            var icon = isEmpty ? '' :
                      (isOptimal ?
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M3.5 6L5.5 8L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M6 3v3.5M6 8.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');

            var message = isEmpty ? 'No alt text' :
                         (isOptimal ? 'Optimal for SEO' : 'Too long for optimal SEO');

            var tooltip = isEmpty ? 'Add alt text for SEO' :
                         (isOptimal ?
                          'Alt text length is optimal for Google Images (≤125 characters recommended)' :
                          'Consider shortening to 125 characters or less for optimal Google Images SEO');

            return '<span class="bbai-char-counter ' + stateClass + '" title="' + tooltip + '">' +
                   icon +
                   '<span class="bbai-char-counter__number">' + charCount + '</span>' +
                   '<span class="bbai-char-counter__label">/' + maxChars + '</span>' +
                   '</span>';
        },

        /**
         * Initialize character counters for all alt text elements
         */
        init: function() {
            $('.bbai-library-alt-text').each(function() {
                var $altText = $(this);
                var text = $altText.text().trim();

                // Check if counter already exists
                if ($altText.next('.bbai-char-counter').length === 0) {
                    var counterHTML = window.bbaiCharCounter.create(text);
                    $altText.after(counterHTML);
                }
            });
        },

        /**
         * Update counter for a specific element
         * @param {jQuery} $element - The element to update
         * @param {string} newText - The new alt text
         */
        update: function($element, newText) {
            var $counter = $element.next('.bbai-char-counter');
            if ($counter.length) {
                var newCounterHTML = this.create(newText);
                $counter.replaceWith(newCounterHTML);
                // Add update animation
                $element.next('.bbai-char-counter').addClass('bbai-char-counter--updating');
                setTimeout(function() {
                    $element.next('.bbai-char-counter').removeClass('bbai-char-counter--updating');
                }, 300);
            }
        }
    };

    // Initialize character counters when DOM is ready
    $(document).ready(function() {
        // Initial setup
        if (typeof window.bbaiCharCounter !== 'undefined') {
            window.bbaiCharCounter.init();
        }

        // Re-initialize after AJAX updates (e.g., after regenerating alt text)
        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiCharCounter !== 'undefined') {
                window.bbaiCharCounter.init();
            }
        });
    });

});


/**
 * SEO Quality Checker
 * Validates alt text quality for SEO best practices
 */
window.bbaiSEOChecker = {
    /**
     * Check if alt text starts with redundant phrases
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    hasRedundantPrefix: function(text) {
        if (!text) return false;
        var lowerText = text.toLowerCase().trim();
        var redundantPrefixes = [
            'image of',
            'picture of',
            'photo of',
            'photograph of',
            'graphic of',
            'illustration of',
            'image showing',
            'picture showing',
            'photo showing'
        ];
        return redundantPrefixes.some(function(prefix) {
            return lowerText.startsWith(prefix);
        });
    },

    /**
     * Check if alt text is just a filename
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    isJustFilename: function(text) {
        if (!text) return false;
        // Check for common filename patterns
        var filenamePatterns = [
            /^IMG[-_]\d+/i,           // IMG_1234, IMG-5678
            /^DSC[-_]\d+/i,           // DSC_1234, DSC-5678
            /^\d{8}[-_]\d+/i,         // 20230101_123456
            /^screenshot[-_]/i,       // screenshot_2023
            /^image[-_]\d+/i,         // image_001, image-02
            /\.(jpg|jpeg|png|gif|webp)$/i  // ends with file extension
        ];
        return filenamePatterns.some(function(pattern) {
            return pattern.test(text.trim());
        });
    },

    /**
     * Check if alt text has meaningful content
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    hasDescriptiveContent: function(text) {
        if (!text) return false;
        // Should have at least 3 words for meaningful description
        var words = text.trim().split(/\s+/);
        return words.length >= 3 && words.some(function(word) {
            return word.length > 3; // Has at least one substantial word
        });
    },

    /**
     * Calculate SEO quality score
     * @param {string} text - The alt text to check
     * @returns {Object} Score and issues
     */
    calculateQuality: function(text) {
        var issues = [];
        var score = 100;

        if (!text || text.trim().length === 0) {
            return {
                score: 0,
                grade: 'F',
                issues: ['No alt text provided'],
                badge: 'missing'
            };
        }

        // Check length (125 chars recommended)
        if (text.length > 125) {
            issues.push('Too long (>' + text.length + ' chars). Aim for ≤125 for optimal Google Images SEO');
            score -= 25;
        }

        // Check for redundant prefixes
        if (this.hasRedundantPrefix(text)) {
            issues.push('Starts with "image of" or similar. Remove redundant prefix');
            score -= 20;
        }

        // Check if it's just a filename
        if (this.isJustFilename(text)) {
            issues.push('Appears to be a filename. Use descriptive text instead');
            score -= 30;
        }

        // Check for descriptive content
        if (!this.hasDescriptiveContent(text)) {
            issues.push('Too short or lacks descriptive keywords');
            score -= 15;
        }

        // Placeholder / non-descriptive check
        var nondescriptiveWords = ['test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder', 'sample', 'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp', 'crap', 'stuff', 'thing', 'things', 'something', 'anything', 'whatever', 'blah', 'meh', 'idk', 'nada', 'random', 'garbage', 'junk', 'dummy', 'fake', 'lorem', 'ipsum'];
        var words = text.trim().toLowerCase().split(/\s+/);
        var badCount = 0;
        for (var i = 0; i < nondescriptiveWords.length; i++) {
            if (words.indexOf(nondescriptiveWords[i]) !== -1) badCount++;
        }
        if (badCount >= 1 && (badCount >= 2 || words.length <= 4)) {
            issues.push('Does not appear to describe the image — edit or regenerate');
            score -= 50;
        }

        // Determine grade
        var grade = 'F';
        var badge = 'needs-work';
        if (score >= 90) {
            grade = 'A';
            badge = 'excellent';
        } else if (score >= 75) {
            grade = 'B';
            badge = 'good';
        } else if (score >= 60) {
            grade = 'C';
            badge = 'fair';
        } else if (score >= 40) {
            grade = 'D';
            badge = 'poor';
        }

        return {
            score: Math.max(0, score),
            grade: grade,
            issues: issues,
            badge: badge
        };
    },

    /**
     * Create SEO quality badge HTML
     * @param {string} text - The alt text to check
     * @returns {string} HTML for quality badge
     */
    createBadge: function(text) {
        var quality = this.calculateQuality(text);

        if (quality.badge === 'missing') {
            return '';
        }

        var badgeClass = 'bbai-seo-badge bbai-seo-badge--' + quality.badge;
        var icon = quality.grade === 'A' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1 5l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
            quality.grade === 'B' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M5 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' :
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 2l6 6M2 8l6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

        var tooltip = quality.issues.length > 0 ?
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100)\n' + quality.issues.join('\n') :
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100) - Excellent!';

        return '<span class="' + badgeClass + '" title="' + tooltip.replace(/"/g, '&quot;') + '">' +
               icon +
               'SEO: ' + quality.grade +
               '</span>';
    },

    /**
     * Initialize SEO quality badges for all alt text elements
     */
    init: function() {
        var self = this;
        // Use jQuery instead of $ for WordPress compatibility (noConflict mode)
        var $ = window.jQuery || window.$;
        if (typeof $ !== 'function') {
            return;
        }
        $('.bbai-library-alt-text').each(function() {
            var $altText = $(this);
            var text = $altText.attr('data-full-text') || $altText.text().trim();

            // Check if badge already exists
            if ($altText.parent().find('.bbai-seo-badge').length === 0) {
                var badgeHTML = self.createBadge(text);
                if (badgeHTML) {
                    var $counter = $altText.next('.bbai-char-counter');
                    if ($counter.length) {
                        $counter.after(badgeHTML);
                    }
                }
            }
        });
    }
};

// Initialize SEO checker when ready
bbaiRunWithJQuery(function($) {
    if (typeof $ !== 'function') {
        return;
    }
    $(document).ready(function() {
        if (typeof window.bbaiSEOChecker !== 'undefined') {
            window.bbaiSEOChecker.init();
        }

        // Re-initialize after AJAX updates
        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiSEOChecker !== 'undefined') {
                window.bbaiSEOChecker.init();
            }
        });
    });
});

(function() {
    'use strict';

    function showUpgradeModalFallback() {
        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            return window.bbaiOpenUpgradeModal();
        }

        if (typeof window.openPricingModal === 'function') {
            window.openPricingModal('enterprise');
            return true;
        }

        if (typeof window.alttextaiShowModal === 'function') {
            return window.alttextaiShowModal();
        }

        var modal = findUpgradeModalElement();
        if (modal) {
            modal.removeAttribute('style');
            modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
            modal.classList.add('active');
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
            bbaiSetUpgradeModalScrollLock(true);
            var modalContent = modal.querySelector('.bbai-upgrade-modal__content');
            if (modalContent) {
                modalContent.style.cssText = 'opacity: 1 !important; visibility: visible !important; transform: translateY(0) scale(1) !important;';
            }
            return true;
        }

        return false;
    }

    document.addEventListener('click', function(e) {
        if (e.defaultPrevented) {
            return;
        }

        var trigger = e.target.closest('[data-action="show-upgrade-modal"], [data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="phase17-improve-alt"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (!trigger) {
            return;
        }

        if (bbaiIsGenerationActionControl(trigger) && bbaiIsLockedActionControl(trigger)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
            } else {
                showUpgradeModalFallback();
            }
            return;
        }

        var isBulkAction = trigger.matches('[data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="phase17-improve-alt"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (isBulkAction) {
            var isLockedTrigger = !!(
                trigger.disabled ||
                trigger.getAttribute('aria-disabled') === 'true' ||
                trigger.getAttribute('data-bbai-lock-control') === '1' ||
                trigger.getAttribute('data-bbai-locked-cta') === '1' ||
                trigger.classList.contains('disabled') ||
                trigger.classList.contains('bbai-is-locked') ||
                trigger.classList.contains('bbai-optimization-cta--disabled') ||
                trigger.classList.contains('bbai-optimization-cta--locked') ||
                trigger.classList.contains('bbai-action-btn--disabled') ||
                String(trigger.getAttribute('title') || '').toLowerCase().indexOf('out of credits') !== -1 ||
                String(trigger.getAttribute('data-bbai-tooltip') || '').toLowerCase().indexOf('unlock more generations') !== -1
            );

            if (isLockedTrigger) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                    window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
                } else {
                    showUpgradeModalFallback();
                }
                return;
            }
        }

        if (trigger.matches('[data-action="show-upgrade-modal"]')) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            showUpgradeModalFallback();
            return;
        }

        // Upgrade links (anchors with real href): let browser navigate, don't intercept
        if (trigger.tagName === 'A') {
            var href = trigger.getAttribute && trigger.getAttribute('href');
            if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                return;
            }
        }

        if (window.bbaiBulkHandlersReady) {
            return;
        }

        var handler = trigger.matches('[data-action="generate-missing"]')
            ? window.bbaiHandleGenerateMissing
            : window.bbaiHandleRegenerateAll;

        if (typeof handler !== 'function') {
            return;
        }

        e.preventDefault();
        handler.call(trigger, e);
    }, true);

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var trigger = e.target.closest('[data-action="show-upgrade-modal"], [data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="phase17-improve-alt"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (!trigger || trigger.getAttribute('role') !== 'button') {
            return;
        }
        e.preventDefault();
        trigger.click();
    }, true);
})();

// Keep the dashboard action surfaces in sync with live stats and usage updates.
bbaiRunWithJQuery(function($) {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function(text) { return text; };
    var _n = i18n && typeof i18n._n === 'function' ? i18n._n : function(single, plural, number) { return number === 1 ? single : plural; };
    var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function(format) {
        var args = Array.prototype.slice.call(arguments, 1);
        var index = 0;
        return String(format).replace(/%(\d+\$)?s/g, function() {
            var value = args[index];
            index += 1;
            return value;
        });
    };
    var feedbackTimeout = null;
    var lastScanRelativeTickerId = null;
    var lastScanRelativeTickerVisibilityHooked = false;
    var dashboardMountLoadingController = null;

    function parseCount(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) || parsed < 0 ? 0 : parsed;
    }

    function formatCount(value) {
        return parseCount(value).toLocaleString();
    }

    function formatPercentageLabel(value) {
        var numericValue = parseFloat(value);

        if (isNaN(numericValue) || numericValue <= 0) {
            return '0';
        }

        if (numericValue >= 100) {
            return '100';
        }

        if (numericValue < 0.01) {
            return '<0.01';
        }

        if (numericValue < 0.1) {
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        if (numericValue < 10) {
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });
        }

        return numericValue.toLocaleString(undefined, {
            maximumFractionDigits: 0
        });
    }

    function getGrowthPlanComparison(data) {
        var growthCapacity = data && data.isPremium ? Math.max(1000, parseCount(data.creditsTotal)) : 1000;
        var usagePercent = Math.min(100, Math.max(0, (parseCount(data && data.creditsUsed) / Math.max(1, growthCapacity)) * 100));
        var displayPercent = formatPercentageLabel(usagePercent);

        return {
            line: data && data.isPremium
                ? sprintf(
                    __('You are using %s%% of Growth capacity this month.', 'beepbeep-ai-alt-text-generator'),
                    displayPercent
                )
                : sprintf(
                    __('On Growth, this usage would be %s%% of monthly capacity.', 'beepbeep-ai-alt-text-generator'),
                    displayPercent
                ),
            display: displayPercent,
            percent: usagePercent,
            indicatorPercent: usagePercent > 0 ? Math.min(99.5, Math.max(0.5, usagePercent)) : 0
        };
    }

    function isAnonymousTrialState(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }
        if (data.root && typeof data.root.getAttribute === 'function' &&
                data.root.getAttribute('data-bbai-is-guest-trial') === '1') {
            return true;
        }
        return !!(
            data.isAnonymousTrial ||
            String(data.authState || '').toLowerCase() === 'anonymous' ||
            String(data.quotaType || '').toLowerCase() === 'trial'
        );
    }

    function getAnonymousTrialOffer(data) {
        return Math.max(0, parseCount(data && data.freePlanOffer) || 50);
    }

    function getLowCreditThresholdForState(data) {
        var explicitThreshold = parseCount(data && data.lowCreditThreshold);
        if (explicitThreshold > 0) {
            return explicitThreshold;
        }

        return isAnonymousTrialState(data)
            ? Math.min(2, Math.max(1, parseCount(data && data.creditsTotal) - 1))
            : BBAI_BANNER_LOW_CREDITS_THRESHOLD;
    }

    function getPlanLabel(data) {
        if (isAnonymousTrialState(data)) {
            return __('Free trial', 'beepbeep-ai-alt-text-generator');
        }
        if (data && data.planLabel) {
            return String(data.planLabel);
        }
        return data && data.isPremium
            ? __('Growth plan', 'beepbeep-ai-alt-text-generator')
            : __('Free plan', 'beepbeep-ai-alt-text-generator');
    }

    function getRemainingPlanLine(data) {
        var remaining = Math.max(0, parseCount(data && data.creditsRemaining));
        if (isAnonymousTrialState(data)) {
            return remaining > 0
                ? sprintf(
                    _n('%s trial generation remaining', '%s trial generations remaining', remaining, 'beepbeep-ai-alt-text-generator'),
                    formatCount(remaining)
                )
                : __('No trial generations remaining', 'beepbeep-ai-alt-text-generator');
        }
        return sprintf(
            _n('%s image left this month', '%s images left this month', remaining, 'beepbeep-ai-alt-text-generator'),
            formatCount(remaining)
        );
    }

    function isCoverageProcessingActive(data, statusCoverage) {
        var total = statusCoverage ? statusCoverage.total : 0;
        return (
            total > 0 &&
            parseCount(data && data.creditsUsed) > 0 &&
            parseCount(data && data.optimized) === 0
        );
    }

    function getCoverageMotivationAndBadge(statusCoverage, data) {
        var cov = statusCoverage ? statusCoverage.coverage : 0;
        var total = statusCoverage ? statusCoverage.total : 0;
        var motivation = '';
        var badge = '';

        if (!statusCoverage || total <= 0) {
            return { motivation: '', badge: '' };
        }

        if (isCoverageProcessingActive(data, statusCoverage)) {
            return { motivation: '', badge: '' };
        }

        if (cov >= 100) {
            motivation = __('100% coverage reached 🚀', 'beepbeep-ai-alt-text-generator');
            badge = __('Top 5% of WordPress sites for image SEO 👀', 'beepbeep-ai-alt-text-generator');
        } else if (cov >= 95) {
            motivation = __('You\'re almost there 🎯', 'beepbeep-ai-alt-text-generator');
            badge = statusCoverage.missing === 1
                ? __('1 fix to go', 'beepbeep-ai-alt-text-generator')
                : sprintf(__('%s fixes to go', 'beepbeep-ai-alt-text-generator'), formatCount(statusCoverage.missing));
        } else if (cov < 80) {
            motivation = __('Add ALT text to improve visibility', 'beepbeep-ai-alt-text-generator');
        }

        return { motivation: motivation, badge: badge };
    }

    function getPlanResetLine(data) {
        if (isAnonymousTrialState(data)) {
            return sprintf(
                __('Create a free account to unlock %d generations per month', 'beepbeep-ai-alt-text-generator'),
                getAnonymousTrialOffer(data)
            );
        }

        var daysUntilReset = parseCount(data && data.daysUntilReset);

        if (daysUntilReset >= 0) {
            return buildUsageResetCopy(daysUntilReset);
        }

        if (data && data.creditsResetLine) {
            return String(data.creditsResetLine).replace(/^Credits\s+/i, '');
        }

        return __('Resets monthly', 'beepbeep-ai-alt-text-generator');
    }

    function getCompactPlanResetLine(data) {
        if (isAnonymousTrialState(data)) {
            return __('Continue fixing images', 'beepbeep-ai-alt-text-generator');
        }

        var daysUntilReset = parseCount(data && data.daysUntilReset);

        if (daysUntilReset >= 0) {
            return sprintf(
                _n('%s day', '%s days', daysUntilReset, 'beepbeep-ai-alt-text-generator'),
                formatCount(daysUntilReset)
            );
        }

        if (data && data.creditsResetLine) {
            return String(data.creditsResetLine).replace(/^Credits\s+/i, '').replace(/^Resets\s+/i, '');
        }

        return __('Monthly', 'beepbeep-ai-alt-text-generator');
    }

    function getUpgradeValueMinutesCopy(data) {
        var minutesSaved = Math.max(0, Math.round(parseCount(data && data.creditsUsed) * 2.5));

        return sprintf(
            _n('~%s minute', '~%s minutes', minutesSaved, 'beepbeep-ai-alt-text-generator'),
            formatCount(minutesSaved)
        );
    }

    function getCreditAlertCopy(data) {
        var remaining = Math.max(0, parseCount(data && data.creditsRemaining));
        var resetLine = data && data.creditsResetLine ? String(data.creditsResetLine) : '';
        var freePlanOffer = getAnonymousTrialOffer(data);

        if (!data || data.isPremium) {
            return null;
        }

        if (isAnonymousTrialState(data)) {
            if (remaining <= 0) {
                return {
                    modifier: 'danger',
                    title: __('Free trial complete', 'beepbeep-ai-alt-text-generator'),
                    message: sprintf(
                        __('Create a free account to unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    ),
                    ctaLabel: __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator'),
                    meta: resetLine
                };
            }

            if (remaining <= getLowCreditThresholdForState(data)) {
                return {
                    modifier: 'warning',
                    title: __('Your free trial is almost used', 'beepbeep-ai-alt-text-generator'),
                    message: sprintf(
                        _n(
                            'You have %1$s trial generation left. Create a free account to unlock %2$d per month.',
                            'You have %1$s trial generations left. Create a free account to unlock %2$d per month.',
                            remaining,
                            'beepbeep-ai-alt-text-generator'
                        ),
                        formatCount(remaining),
                        freePlanOffer
                    ),
                    ctaLabel: __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator'),
                    meta: resetLine
                };
            }

            return null;
        }

        if (remaining <= 0) {
            return {
                modifier: 'danger',
                title: __('This month’s free allowance is used', 'beepbeep-ai-alt-text-generator'),
                message: __('Your existing ALT text is still available to review. Upgrade to continue generating and keep automation running.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                meta: resetLine
            };
        }

        if (remaining <= 5) {
            return {
                modifier: 'warning',
                title: __('You’re close to this month’s allowance', 'beepbeep-ai-alt-text-generator'),
                message: sprintf(
                    _n(
                        'You can generate ALT text for %s more image this month. Keep your library moving without interruption.',
                        'You can generate ALT text for %s more images this month. Keep your library moving without interruption.',
                        remaining,
                        'beepbeep-ai-alt-text-generator'
                    ),
                    formatCount(remaining)
                ),
                ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                meta: resetLine
            };
        }

        return null;
    }

    function getDashboardRoot() {
        return document.querySelector('[data-bbai-dashboard-root="1"]');
    }

    function getDashboardStateRoots() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-bbai-dashboard-state-root="1"]'));
    }

    function resolveDashboardDisplayState(baseState, runtimeState) {
        if (runtimeState === 'generation_complete' || runtimeState === 'generation_failed') {
            return runtimeState;
        }

        if (runtimeState === 'generation_running') {
            return baseState === 'logged_out_trial_available'
                ? 'logged_out_trial_running'
                : 'generation_running';
        }

        return baseState || 'logged_in_free_or_paid';
    }

    function syncDashboardStateRootAttributes(update) {
        var roots = getDashboardStateRoots();

        if (!roots.length) {
            return null;
        }

        update = update && typeof update === 'object' ? update : {};

        roots.forEach(function(root) {
            if (!root) {
                return;
            }

            var baseState = update.baseState !== undefined
                ? bbaiString(update.baseState)
                : bbaiString(root.getAttribute('data-bbai-dashboard-base-state'));
            var runtimeState = update.runtimeState !== undefined
                ? bbaiString(update.runtimeState || 'idle')
                : bbaiString(root.getAttribute('data-bbai-dashboard-runtime-state') || 'idle');

            if (update.isGuestTrial !== undefined) {
                root.setAttribute('data-bbai-is-guest-trial', update.isGuestTrial ? '1' : '0');
            }
            if (update.lockedCtaMode !== undefined) {
                root.setAttribute('data-bbai-locked-cta-mode', bbaiString(update.lockedCtaMode));
            }
            if (update.freeAccountMonthlyLimit !== undefined) {
                root.setAttribute('data-bbai-free-account-monthly-limit', String(Math.max(0, parseCount(update.freeAccountMonthlyLimit))));
            }
            if (update.trial && typeof update.trial === 'object') {
                if (update.trial.limit !== undefined) {
                    root.setAttribute('data-bbai-trial-limit', String(Math.max(0, parseCount(update.trial.limit))));
                }
                if (update.trial.used !== undefined) {
                    root.setAttribute('data-bbai-trial-used', String(Math.max(0, parseCount(update.trial.used))));
                }
                if (update.trial.remaining !== undefined) {
                    root.setAttribute('data-bbai-trial-remaining', String(Math.max(0, parseCount(update.trial.remaining))));
                }
                if (update.trial.exhausted !== undefined) {
                    root.setAttribute('data-bbai-trial-exhausted', update.trial.exhausted ? '1' : '0');
                }
            }

            root.setAttribute('data-bbai-dashboard-base-state', baseState);
            root.setAttribute('data-bbai-dashboard-runtime-state', runtimeState);
            root.setAttribute('data-bbai-dashboard-state', resolveDashboardDisplayState(baseState, runtimeState));
        });

        return roots[0];
    }

    window.bbaiSyncDashboardStateRoot = window.bbaiSyncDashboardStateRoot || function(update) {
        return syncDashboardStateRootAttributes(update || {});
    };

    function buildUsageResetCopy(daysLeft) {
        var safeDaysLeft = Math.max(0, parseCount(daysLeft));

        return sprintf(
            _n('Resets in %s day', 'Resets in %s days', safeDaysLeft, 'beepbeep-ai-alt-text-generator'),
            formatCount(safeDaysLeft)
        );
    }

    function getUsageResetCopy(usageLine) {
        var hero;
        var daysLeftAttr;

        if (!usageLine) {
            return '';
        }

        hero = usageLine.closest('[data-bbai-dashboard-hero="1"]');
        if (hero) {
            daysLeftAttr = hero.getAttribute('data-bbai-banner-days-left');
            if (daysLeftAttr !== null && daysLeftAttr !== '') {
                var inlineCopy = buildUsageResetCopy(daysLeftAttr);
                usageLine.setAttribute('data-bbai-reset-copy', inlineCopy);
                return inlineCopy;
            }
        }

        var existing = usageLine.getAttribute('data-bbai-reset-copy');
        if (existing) {
            return existing;
        }

        var text = usageLine.textContent || '';
        var parts = text.split('•');
        var resetCopy = parts.length > 1 ? parts.slice(1).join('•').trim() : '';
        usageLine.setAttribute('data-bbai-reset-copy', resetCopy);
        return resetCopy;
    }

    function getDashboardData() {
        var root = getDashboardRoot();
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        var usage = bbaiNormalizeUsageObject(bbaiGetUsageObject());
        if (!root) {
            return null;
        }

        var missing = parseCount(root.getAttribute('data-bbai-missing-count'));
        var weak = parseCount(root.getAttribute('data-bbai-weak-count'));
        var optimized = parseCount(root.getAttribute('data-bbai-optimized-count'));
        var total = parseCount(root.getAttribute('data-bbai-total-count'));
        var hasScanResultsAttr = root.getAttribute('data-bbai-has-scan-results');
        var domGuestTrial = root.getAttribute('data-bbai-is-guest-trial') === '1';

        return {
            root: root,
            missing: missing,
            weak: weak,
            optimized: optimized,
            total: total,
            generated: parseCount(root.getAttribute('data-bbai-generated-count')),
            creditsUsed: usage ? parseCount(usage.used) : parseCount(root.getAttribute('data-bbai-credits-used')),
            creditsTotal: usage ? Math.max(1, parseCount(usage.limit)) : Math.max(1, parseCount(root.getAttribute('data-bbai-credits-total'))),
            creditsRemaining: usage ? parseCount(usage.remaining) : parseCount(root.getAttribute('data-bbai-credits-remaining')),
            creditsResetLine: usage ? buildCreditsResetLine(usage, root.getAttribute('data-bbai-credits-reset-line') || '') : (root.getAttribute('data-bbai-credits-reset-line') || ''),
            authState: domGuestTrial
                ? 'anonymous'
                : ((usage && usage.auth_state) || root.getAttribute('data-bbai-auth-state') || ''),
            quotaType: domGuestTrial
                ? 'trial'
                : ((usage && usage.quota_type) || root.getAttribute('data-bbai-quota-type') || ''),
            quotaState: (usage && usage.quota_state) || root.getAttribute('data-bbai-quota-state') || '',
            signupRequired: (usage && usage.signup_required !== undefined)
                ? !!usage.signup_required
                : root.getAttribute('data-bbai-signup-required') === '1',
            upgradeRequired: root.getAttribute('data-bbai-upgrade-required') === '1',
            freePlanOffer: (usage && usage.free_plan_offer !== undefined)
                ? Math.max(0, parseCount(usage.free_plan_offer) || 50)
                : Math.max(0, parseCount(root.getAttribute('data-bbai-free-plan-offer')) || 50),
            lowCreditThreshold: (usage && usage.low_credit_threshold !== undefined)
                ? Math.max(0, parseCount(usage.low_credit_threshold))
                : Math.max(0, parseCount(root.getAttribute('data-bbai-low-credit-threshold'))),
            planLabel: (usage && usage.plan_label) || getPlanLabel({
                isPremium: root.getAttribute('data-bbai-is-premium') === '1',
                authState: root.getAttribute('data-bbai-auth-state') || '',
                quotaType: root.getAttribute('data-bbai-quota-type') || ''
            }),
            isAnonymousTrial: domGuestTrial || (usage
                ? bbaiIsAnonymousTrialUsage(usage)
                : root.getAttribute('data-bbai-auth-state') === 'anonymous' || root.getAttribute('data-bbai-quota-type') === 'trial'),
            isPremium: root.getAttribute('data-bbai-is-premium') === '1',
            hasScanResults: hasScanResultsAttr === null
                ? (total > 0 || missing > 0 || weak > 0 || optimized > 0)
                : hasScanResultsAttr === '1',
            daysUntilReset: usage && usage.days_until_reset !== undefined
                ? parseCount(usage.days_until_reset)
                : (hero ? parseCount(hero.getAttribute('data-bbai-banner-days-left')) : 0),
            lastScanTs: parseCount(root.getAttribute('data-bbai-last-scan-ts')),
            libraryUrl: root.getAttribute('data-bbai-library-url') || '',
            missingLibraryUrl: root.getAttribute('data-bbai-missing-library-url') || '',
            needsReviewLibraryUrl: root.getAttribute('data-bbai-needs-review-library-url') || '',
            settingsUrl: root.getAttribute('data-bbai-settings-url') || '',
            usageUrl: root.getAttribute('data-bbai-usage-url') || '',
            guideUrl: root.getAttribute('data-bbai-guide-url') || ''
        };
    }

    function dashboardCanAccessLibrary(data) {
        return !!(
            data &&
            data.root &&
            data.root.getAttribute('data-bbai-has-connected-account') === '1'
        );
    }

    function buildDashboardReviewUrl(baseUrl) {
        var rawUrl = String(baseUrl || '').split('#')[0] || '#';
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

    function resolveDashboardReviewUrl(trigger, data) {
        var segment = trigger
            ? String(trigger.getAttribute('data-bbai-review-segment') || trigger.getAttribute('data-bbai-status-segment') || 'all')
            : 'all';
        var baseUrl = '';

        if (!data) {
            return '#';
        }

        if (segment === 'missing') {
            baseUrl = String(data.missingLibraryUrl || data.libraryUrl || '');
        } else if (segment === 'weak' || segment === 'needs_review') {
            baseUrl = String(data.needsReviewLibraryUrl || data.libraryUrl || '');
        } else {
            baseUrl = String(data.libraryUrl || '');
        }

        return buildDashboardReviewUrl(baseUrl);
    }

	function openDashboardLockedGate(trigger) {
	    return bbaiOpenDashboardAuthDirect(trigger);
	}

    function syncStatsToRoot(stats) {
        var root = getDashboardRoot();
        if (!root || !stats || typeof stats !== 'object') {
            return;
        }

        var currentMissing = parseCount(root.getAttribute('data-bbai-missing-count'));
        var currentWeak = parseCount(root.getAttribute('data-bbai-weak-count'));
        var currentOptimized = parseCount(root.getAttribute('data-bbai-optimized-count'));
        var currentTotal = parseCount(root.getAttribute('data-bbai-total-count'));
        var missing = stats.images_missing_alt !== undefined
            ? parseCount(stats.images_missing_alt)
            : (stats.missing !== undefined ? parseCount(stats.missing) : currentMissing);
        var weak = stats.needs_review_count !== undefined
            ? parseCount(stats.needs_review_count)
            : currentWeak;
        var optimized = currentOptimized;

        if (stats.optimized_count !== undefined) {
            optimized = parseCount(stats.optimized_count);
        } else if (stats.needs_review_count !== undefined && stats.images_with_alt !== undefined) {
            optimized = Math.max(0, parseCount(stats.images_with_alt) - parseCount(stats.needs_review_count));
        } else if (stats.needs_review_count !== undefined && stats.with_alt !== undefined) {
            optimized = Math.max(0, parseCount(stats.with_alt) - parseCount(stats.needs_review_count));
        }

        var total = stats.total_images !== undefined
            ? parseCount(stats.total_images)
            : (stats.total !== undefined ? parseCount(stats.total) : currentTotal);
        var currentGenerated = parseCount(root.getAttribute('data-bbai-generated-count'));
        var generated = stats.generated !== undefined ? parseCount(stats.generated) : currentGenerated;
        var currentLastScanTs = parseCount(root.getAttribute('data-bbai-last-scan-ts'));
        var lastScanTs = currentLastScanTs;
        var hasScanResults = total > 0 || missing > 0 || weak > 0 || optimized > 0;

        if (stats.scanned_at !== undefined) {
            lastScanTs = parseCount(stats.scanned_at);
        } else if (stats.scannedAt !== undefined) {
            lastScanTs = parseCount(stats.scannedAt);
        }

        root.setAttribute('data-bbai-missing-count', String(missing));
        root.setAttribute('data-bbai-weak-count', String(weak));
        root.setAttribute('data-bbai-optimized-count', String(optimized));
        root.setAttribute('data-bbai-total-count', String(total));
        root.setAttribute('data-bbai-generated-count', String(generated));
        root.setAttribute('data-bbai-has-scan-results', hasScanResults ? '1' : '0');
        root.setAttribute('data-bbai-last-scan-ts', String(lastScanTs));
        root.setAttribute('data-bbai-actionable-state', missing > 0 ? 'missing' : (weak > 0 ? 'review' : 'complete'));
        root.setAttribute('data-bbai-actionable-count', String(Math.max(0, missing + weak)));
        syncCoverageProcessingFlagToRoot(root);
    }

    function syncCoverageProcessingFlagToRoot(root) {
        var creditsUsed;
        var optimized;
        var total;

        if (!root) {
            return;
        }

        creditsUsed = parseCount(root.getAttribute('data-bbai-credits-used'));
        optimized = parseCount(root.getAttribute('data-bbai-optimized-count'));
        total = parseCount(root.getAttribute('data-bbai-total-count'));
        root.setAttribute(
            'data-bbai-coverage-processing',
            creditsUsed > 0 && optimized === 0 && total > 0 ? '1' : '0'
        );
    }

    function syncUsageToRoot(usage) {
        var root = getDashboardRoot();
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        usage = bbaiNormalizeUsageObject(usage);
        var daysUntilReset = parseInt(usage && usage.days_until_reset, 10);
        var isAnonymousTrial;
        var lockedCtaMode;
        var usagePlan;
        var isFreeTier;
        var trialLimit;
        var trialUsed;
        var trialRemaining;
        var trialExhausted;

        if (!usage || typeof usage !== 'object') {
            return;
        }

        var used = parseCount(usage.used);
        var limit = Math.max(1, parseCount(usage.limit || 50));
        var remaining = parseCount(usage.remaining);

        var domGuestTrial = root && root.getAttribute('data-bbai-is-guest-trial') === '1';

        if (root) {
            root.setAttribute('data-bbai-credits-used', String(used));
            root.setAttribute('data-bbai-credits-total', String(limit));
            root.setAttribute('data-bbai-credits-remaining', String(remaining));
            root.setAttribute('data-bbai-quota-state', String(usage.quota_state || root.getAttribute('data-bbai-quota-state') || ''));
            root.setAttribute('data-bbai-signup-required', usage.signup_required ? '1' : '0');
            root.setAttribute('data-bbai-free-plan-offer', String(Math.max(0, parseCount(usage.free_plan_offer) || 50)));
            root.setAttribute('data-bbai-low-credit-threshold', String(Math.max(0, parseCount(usage.low_credit_threshold) || 0)));
            if (usage.plan_label) {
                root.setAttribute('data-bbai-plan-label', String(usage.plan_label));
            }
            root.setAttribute(
                'data-bbai-credits-reset-line',
                buildCreditsResetLine(usage, root.getAttribute('data-bbai-credits-reset-line') || '')
            );
        }

        if (hero && !isNaN(daysUntilReset)) {
            hero.setAttribute('data-bbai-banner-days-left', String(Math.max(0, daysUntilReset)));
        }

        isAnonymousTrial = bbaiIsAnonymousTrialUsage(usage) || domGuestTrial;

        if (root) {
            if (isAnonymousTrial) {
                root.setAttribute('data-bbai-auth-state', 'anonymous');
                root.setAttribute('data-bbai-quota-type', 'trial');
            } else {
                root.setAttribute('data-bbai-auth-state', String(usage.auth_state || root.getAttribute('data-bbai-auth-state') || ''));
                root.setAttribute('data-bbai-quota-type', String(usage.quota_type || root.getAttribute('data-bbai-quota-type') || ''));
            }
        }

        usagePlan = bbaiString(usage.plan || usage.plan_type).toLowerCase();
        isFreeTier = usagePlan === '' || usagePlan === 'free' || usagePlan === 'starter' || usagePlan === 'trial';
        trialLimit = Math.max(0, parseCount(usage.limit));
        trialUsed = Math.max(0, parseCount(usage.used));
        trialRemaining = Math.max(0, parseCount(usage.remaining));
        trialExhausted = !!usage.trial_exhausted || (isAnonymousTrial && trialRemaining <= 0);
        if (isAnonymousTrial) {
            lockedCtaMode = trialExhausted ? 'create_account' : '';
        } else {
            var exhaustedIn = !!(usage.upgrade_required || usage.quota_state === 'exhausted');
            var growthLike = usagePlan === 'growth' || usagePlan === 'pro';
            var agencyLike = usagePlan === 'agency';
            if (exhaustedIn) {
                if (agencyLike) {
                    lockedCtaMode = 'manage_plan';
                } else if (growthLike) {
                    lockedCtaMode = 'upgrade_agency';
                } else if (isFreeTier) {
                    lockedCtaMode = 'upgrade_growth';
                } else {
                    lockedCtaMode = 'upgrade_growth';
                }
            } else {
                lockedCtaMode = '';
            }
        }

        syncDashboardStateRootAttributes({
            baseState: isAnonymousTrial
                ? (trialExhausted ? 'logged_out_trial_exhausted' : 'logged_out_trial_available')
                : 'logged_in_free_or_paid',
            isGuestTrial: isAnonymousTrial,
            lockedCtaMode: lockedCtaMode,
            freeAccountMonthlyLimit: Math.max(0, parseCount(usage.free_plan_offer)),
            trial: {
                limit: trialLimit,
                used: trialUsed,
                remaining: trialRemaining,
                exhausted: trialExhausted
            }
        });

        if (root) {
            syncCoverageProcessingFlagToRoot(root);
        }
    }

    function hasCreditsAvailable(data) {
        return !!(data && (data.isPremium || data.creditsRemaining > 0));
    }

    function buildCreditsResetLine(usage, fallback) {
        var existing = String(fallback || '');
        var resetTsRaw;
        var resetTs;
        var formatted = '';

        if (!usage || typeof usage !== 'object') {
            return existing;
        }

        if (bbaiIsAnonymousTrialUsage(usage)) {
            return sprintf(
                __('Create a free account to keep your progress and unlock %d monthly generations', 'beepbeep-ai-alt-text-generator'),
                Math.max(0, parseCount(usage.free_plan_offer) || 50)
            );
        }

        resetTsRaw = usage.reset_timestamp || usage.resetTimestamp || usage.reset_ts || 0;
        resetTs = parseInt(resetTsRaw, 10);

        if (!isNaN(resetTs) && resetTs > 0 && resetTs < 1000000000000) {
            resetTs = resetTs * 1000;
        }

        if ((!resetTs || isNaN(resetTs)) && usage.resetDate) {
            resetTs = Date.parse(String(usage.resetDate));
        }
        if ((!resetTs || isNaN(resetTs)) && usage.reset_date) {
            resetTs = Date.parse(String(usage.reset_date));
        }

        if (resetTs && !isNaN(resetTs)) {
            try {
                formatted = new Date(resetTs).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (error) {
                formatted = '';
            }
        }

        if (!formatted) {
            formatted = usage.resetDate || usage.reset_date || '';
        }

        return formatted
            ? sprintf(__('Credits reset %s', 'beepbeep-ai-alt-text-generator'), formatted)
            : existing;
    }

    function formatLastScanCopy(timestamp) {
        var scanTs = parseInt(timestamp, 10);
        var diffMs;
        var minutes;
        var hours;
        var days;

        if (isNaN(scanTs) || scanTs <= 0) {
            return '';
        }

        if (scanTs < 1000000000000) {
            scanTs = scanTs * 1000;
        }

        diffMs = Math.max(0, Date.now() - scanTs);
        minutes = Math.floor(diffMs / 60000);
        hours = Math.floor(diffMs / 3600000);
        days = Math.floor(diffMs / 86400000);

        if (diffMs < 1000) {
            return __('Last scan just now', 'beepbeep-ai-alt-text-generator');
        }

        if (minutes < 1) {
            var seconds = Math.floor(diffMs / 1000);
            return sprintf(
                __('Last scan %s ago', 'beepbeep-ai-alt-text-generator'),
                sprintf(_n('%d second', '%d seconds', seconds, 'beepbeep-ai-alt-text-generator'), seconds)
            );
        }
        if (minutes < 60) {
            return sprintf(
                _n('Last scan %d minute ago', 'Last scan %d minutes ago', minutes, 'beepbeep-ai-alt-text-generator'),
                minutes
            );
        }
        if (hours < 24) {
            return sprintf(
                _n('Last scan %d hour ago', 'Last scan %d hours ago', hours, 'beepbeep-ai-alt-text-generator'),
                hours
            );
        }
        if (days < 7) {
            return sprintf(
                _n('Last scan %d day ago', 'Last scan %d days ago', days, 'beepbeep-ai-alt-text-generator'),
                days
            );
        }

        try {
            return sprintf(
                __('Last scan %s', 'beepbeep-ai-alt-text-generator'),
                new Date(scanTs).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })
            );
        } catch (error) {
            return '';
        }
    }

    function updateStatusCardLastScanDisplay() {
        var root = getDashboardRoot();
        var metaNode = document.querySelector('[data-bbai-dashboard-status-card="1"] [data-bbai-status-last-scan]');
        var ts;
        var copy;

        if (!root || !metaNode) {
            return;
        }

        ts = parseCount(root.getAttribute('data-bbai-last-scan-ts'));
        copy = formatLastScanCopy(ts);
        metaNode.textContent = copy;
        metaNode.hidden = !copy;
    }

    function stopLastScanRelativeTicker() {
        if (lastScanRelativeTickerId !== null) {
            window.clearInterval(lastScanRelativeTickerId);
            lastScanRelativeTickerId = null;
        }
    }

    function startLastScanRelativeTicker() {
        var metaNode = document.querySelector('[data-bbai-dashboard-status-card="1"] [data-bbai-status-last-scan]');

        if (!metaNode) {
            stopLastScanRelativeTicker();
            return;
        }

        stopLastScanRelativeTicker();
        updateStatusCardLastScanDisplay();
        lastScanRelativeTickerId = window.setInterval(updateStatusCardLastScanDisplay, 5000);
    }

    function ensureLastScanRelativeTicker() {
        if (!lastScanRelativeTickerVisibilityHooked && typeof document.addEventListener === 'function') {
            lastScanRelativeTickerVisibilityHooked = true;
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopLastScanRelativeTicker();
                } else {
                    startLastScanRelativeTicker();
                }
            });
        }

        if (typeof document.hidden !== 'undefined' && document.hidden) {
            return;
        }

        startLastScanRelativeTicker();
    }

    function hasScanResults(data) {
        if (!data) {
            return false;
        }

        if (typeof data.hasScanResults === 'boolean') {
            return data.hasScanResults;
        }

        return !!(data.total > 0 || data.missing > 0 || data.weak > 0 || data.optimized > 0);
    }

    function getDashboardHeroIconMarkup(tone) {
        if (typeof window !== 'undefined' && typeof window.bbaiGetSharedCommandHeroIconMarkup === 'function') {
            return window.bbaiGetSharedCommandHeroIconMarkup(tone);
        }

        if (tone === 'setup') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        if (tone === 'attention') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M12 3L21 19H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
        }

        if (tone === 'paused') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M10 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M14 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    }

    function getDashboardHeroStateModel(data) {
        var missing = Math.max(0, parseCount(data && data.missing));
        var weak = Math.max(0, parseCount(data && data.weak));
        if (weak <= 0 && data && data.needs_review_count !== undefined && data.needs_review_count !== null) {
            weak = parseCount(data.needs_review_count);
        }
        var total = Math.max(0, parseCount(data && data.total));
        var totalIssues = missing + weak;
        var creditsRemaining = Math.max(0, parseCount(data && data.creditsRemaining));
        var usagePercent = Math.min(100, Math.max(0, Math.round((parseCount(data && data.creditsUsed) / Math.max(1, parseCount(data && data.creditsTotal))) * 100)));
        var thresh = getLowCreditThresholdForState(data);
        var isLowCredits = creditsRemaining > 0 && creditsRemaining <= thresh;
        var isOutOfCredits = creditsRemaining === 0;
        var libraryUrl = data && data.libraryUrl ? String(data.libraryUrl) : '#';
        var usageUrl = data && data.usageUrl ? String(data.usageUrl) : '#';
        var guideUrl = data && data.guideUrl ? String(data.guideUrl) : '#';
        var settingsUrl = data && data.settingsUrl ? String(data.settingsUrl) : '#';

        function createAction(label, helper, options) {
            return $.extend({
                label: label || '',
                helper: helper || '',
                href: '#',
                removeAttributes: ['data-action', 'data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source', 'aria-describedby']
            }, options || {});
        }

        var model = {
            state: 'healthy-free',
            tone: 'healthy',
            headline: '',
            subtext: '',
            nextStep: '',
            workflowHint: '',
            note: '',
            loopActions: [],
            usagePercent: usagePercent,
            primaryAction: createAction('', '', {}),
            secondaryAction: null,
            tertiaryAction: null
        };

        function createActionFromShared(actionConfig) {
            if (!actionConfig || !actionConfig.label) {
                return null;
            }

            return createAction(
                actionConfig.label,
                actionConfig.helper || '',
                {
                    href: actionConfig.href || '#',
                    action: actionConfig.action || '',
                    bbaiAction: actionConfig.bbaiAction || '',
                    attributes: actionConfig.attributes || null
                }
            );
        }

        var sharedHeroState = typeof window !== 'undefined' && typeof window.bbaiBuildSharedCommandHeroState === 'function'
            ? window.bbaiBuildSharedCommandHeroState({
                totalImages: total,
                missingCount: missing,
                weakCount: weak,
                creditsRemaining: creditsRemaining,
                creditsLimit: parseCount(data && data.creditsTotal),
                isPro: !!(data && data.isPremium),
                lowCreditThreshold: thresh,
                libraryUrl: libraryUrl,
                usageUrl: usageUrl,
                guideUrl: guideUrl,
                settingsUrl: settingsUrl,
                needsReviewLibraryUrl: (data && data.needsReviewLibraryUrl) || libraryUrl || '#',
                authState: data && data.authState,
                quotaType: data && data.quotaType,
                quotaState: data && data.quotaState,
                signupRequired: data && data.signupRequired,
                freePlanOffer: data && data.freePlanOffer,
                isTrial: isAnonymousTrialState(data),
                isGuestTrial: data && data.root && data.root.getAttribute('data-bbai-is-guest-trial') === '1',
                pageContext: 'dashboard'
            })
            : null;

        if (sharedHeroState) {
            model.state = sharedHeroState.dashboardState || 'healthy-free';
            model.tone = sharedHeroState.tone || 'healthy';
            model.headline = sharedHeroState.headline || '';
            model.subtext = sharedHeroState.subtext || '';
            model.nextStep = sharedHeroState.nextStep || '';
            model.note = sharedHeroState.note || '';
            model.primaryAction = createActionFromShared(sharedHeroState.primaryAction) || createAction('', '', {});
            model.secondaryAction = createActionFromShared(sharedHeroState.secondaryAction);
            model.tertiaryAction = createActionFromShared(sharedHeroState.tertiaryAction);

                if (sharedHeroState.state === 'healthy') {
                    if (isAnonymousTrialState(data)) {
                        model.loopActions = [
                            createAction(__('Open ALT Library', 'beepbeep-ai-alt-text-generator'), '', { href: libraryUrl || '#' }),
                            createAction(__('Fix remaining images for free', 'beepbeep-ai-alt-text-generator'), '', {
                            action: 'show-dashboard-auth',
                            attributes: { 'data-auth-tab': 'register' }
                        })
                    ];
                    model.loopSupportLine = '';
                    model.upgradeTensionLine = sprintf(
                        __('Create a free account to unlock %d generations per month.', 'beepbeep-ai-alt-text-generator'),
                        getAnonymousTrialOffer(data)
                    );
                } else {
                    model.loopActions = [
                        createAction(__('Auto-optimise future uploads', 'beepbeep-ai-alt-text-generator'), '', { href: settingsUrl }),
                        createAction(__('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'), '', { action: 'show-upgrade-modal' })
                    ];
                    model.loopSupportLine = '';
                    model.upgradeTensionLine = data && data.isPremium
                        ? ''
                        : __('New uploads will stop being optimised automatically on the free plan', 'beepbeep-ai-alt-text-generator');
                }
            }

            return model;
        }

        return null;
    }

    function formatMonthlyProgressCopy(count) {
        var optimizedThisMonth = parseCount(count);
        if (optimizedThisMonth <= 0) {
            return __('No ALT descriptions generated by AI yet this month', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            _n('%s ALT description generated by AI this month', '%s ALT descriptions generated by AI this month', optimizedThisMonth, 'beepbeep-ai-alt-text-generator'),
            formatCount(optimizedThisMonth)
        );
    }

    function getStatusCoverageData(data) {
        var optimized = parseCount(data && data.optimized);
        var weak = parseCount(data && data.weak);
        if (weak <= 0 && data && data.needs_review_count !== undefined && data.needs_review_count !== null) {
            weak = parseCount(data.needs_review_count);
        }
        var missing = parseCount(data && data.missing);
        var derivedTotal = Math.max(0, optimized + weak + missing);
        var statedTotal = data && data.total !== undefined && data.total !== null ? parseCount(data.total) : 0;
        var total = statedTotal > 0 ? statedTotal : derivedTotal;
        var coverage = total > 0 ? Math.round((optimized / total) * 100) : 0;

        return {
            optimized: optimized,
            weak: weak,
            missing: missing,
            total: total,
            coverage: coverage
        };
    }

    function getOptimizedRatioCopy(statusCoverage) {
        if (!statusCoverage || statusCoverage.total <= 0) {
            return __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', statusCoverage.total, 'beepbeep-ai-alt-text-generator'),
            formatCount(statusCoverage.optimized),
            formatCount(statusCoverage.total)
        );
    }

    function getStatusRingSegments(statusCoverage, circumference) {
        var segmentGap = 0;
        var segmentOffset = 0;
        var nonZeroSegments = 0;
        var linecap = 'butt';
        var segments = {};
        var animationOrder = 0;
        var segmentConfig = [
            {
                key: 'optimized',
                value: statusCoverage.optimized,
                stroke: '#16A34A'
            },
            {
                key: 'weak',
                value: statusCoverage.weak,
                stroke: '#F97316'
            },
            {
                key: 'missing',
                value: statusCoverage.missing,
                stroke: '#EF4444'
            }
        ];

        segmentConfig.forEach(function(segment) {
            if (segment.value > 0) {
                nonZeroSegments += 1;
            }
        });

        if (nonZeroSegments <= 1) {
            linecap = 'round';
        }

        if (nonZeroSegments > 1) {
            linecap = 'butt';
        }

        segmentConfig.forEach(function(segment) {
            var segmentLength = statusCoverage.total > 0
                ? circumference * (segment.value / statusCoverage.total)
                : 0;
            var gap = nonZeroSegments > 1 ? Math.min(segmentGap, segmentLength * 0.35) : 0;
            var visibleLength = Math.max(0, segmentLength - gap);

            segments[segment.key] = {
                stroke: segment.stroke,
                dasharray: visibleLength.toFixed(3) + ' ' + Math.max(0, circumference - visibleLength).toFixed(3),
                dashoffset: (-segmentOffset).toFixed(3),
                isVisible: visibleLength > 0.01,
                linecap: linecap,
                animationOrder: visibleLength > 0.01 ? animationOrder++ : animationOrder
            };

            segmentOffset += segmentLength;
        });

        return segments;
    }

    function getPrimaryAction(data) {
        if (!data) {
            return 'scan';
        }
        if (!hasCreditsAvailable(data)) {
            return 'upgrade';
        }
        if (data.missing > 0) {
            return 'generate_missing';
        }
        if (data.weak > 0) {
            return 'review_weak';
        }
        if (hasScanResults(data)) {
            return 'review_library';
        }
        return 'scan';
    }

    function formatCreditHelper(required, remaining) {
        var requiredCount = Math.max(0, parseCount(required));
        var remainingCount = Math.max(0, parseCount(remaining));

        if (remainingCount >= requiredCount) {
            return sprintf(
                _n('Uses %s credit', 'Uses %s credits', requiredCount, 'beepbeep-ai-alt-text-generator'),
                formatCount(requiredCount)
            );
        }

        return sprintf(
            _n('%s credit available now', '%s credits available now', remainingCount, 'beepbeep-ai-alt-text-generator'),
            formatCount(remainingCount)
        );
    }

    function escapeDashboardHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setInteractiveControl(node, config) {
        if (!node || !config) {
            return;
        }

        var href = config.href || '#';
        if (!config.preserveContent) {
            node.textContent = config.label || '';
        }
        node.setAttribute('href', href);

        if (config.ariaLabel || config.label) {
            node.setAttribute('aria-label', config.ariaLabel || config.label);
        } else {
            node.removeAttribute('aria-label');
        }

        if (Array.isArray(config.removeAttributes)) {
            config.removeAttributes.forEach(function(attributeName) {
                if (attributeName) {
                    node.removeAttribute(attributeName);
                }
            });
        }

        if (config.action) {
            node.setAttribute('data-action', config.action);
        } else {
            node.removeAttribute('data-action');
        }

        if (config.bbaiAction) {
            node.setAttribute('data-bbai-action', config.bbaiAction);
        } else {
            node.removeAttribute('data-bbai-action');
        }

        ['data-bbai-regenerate-scope', 'data-bbai-generation-source'].forEach(function(attributeName) {
            if (!config.attributes || !Object.prototype.hasOwnProperty.call(config.attributes, attributeName)) {
                node.removeAttribute(attributeName);
            }
        });

        if (config.attributes) {
            Object.keys(config.attributes).forEach(function(attributeName) {
                if (!attributeName) {
                    return;
                }

                var attributeValue = config.attributes[attributeName];
                if (attributeValue === null || attributeValue === undefined || attributeValue === '') {
                    node.removeAttribute(attributeName);
                    return;
                }

                node.setAttribute(attributeName, attributeValue);
            });
        }

        if (config.disabled) {
            node.classList.add('is-disabled');
            node.setAttribute('aria-disabled', 'true');
            node.setAttribute('href', '#');
        } else {
            node.classList.remove('is-disabled');
            node.removeAttribute('aria-disabled');
        }
    }

    function syncHeroLinkRow(hero) {
        if (!hero) {
            return;
        }

        var tertiaryItem = hero.querySelector('[data-bbai-hero-tertiary-item]');
        var tertiaryLink = hero.querySelector('[data-bbai-hero-secondary-link]');
        if (tertiaryItem) {
            tertiaryItem.hidden = !(tertiaryLink && !tertiaryLink.hidden);
        }
    }

    function renderHero(data) {
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        if (!hero || !data) {
            return;
        }

        if (hero.getAttribute('data-bbai-top-banner-mode') === 'plan') {
            return;
        }

        var model = getDashboardHeroStateModel(data);
        if (!model) {
            return;
        }

        var iconNode = hero.querySelector('[data-bbai-hero-icon]');
        var headline = hero.querySelector('[data-bbai-hero-headline]');
        var subtext = hero.querySelector('[data-bbai-hero-subtext]');
        var nextStepNode = hero.querySelector('[data-bbai-hero-next-step]');
        var workflowRow = hero.querySelector('[data-bbai-hero-workflow-row]');
        var workflowNode = hero.querySelector('[data-bbai-hero-workflow]');
        var noteNode = hero.querySelector('[data-bbai-hero-note]');
        var primaryItem = hero.querySelector('[data-bbai-hero-primary-item]');
        var secondaryItem = hero.querySelector('[data-bbai-hero-secondary-item]');
        var tertiaryItem = hero.querySelector('[data-bbai-hero-tertiary-item]');
        var generatorCta = hero.querySelector('[data-bbai-hero-generator-cta]');
        var libraryCta = hero.querySelector('[data-bbai-hero-library-cta]');
        var secondaryLink = hero.querySelector('[data-bbai-hero-secondary-link]');
        var primaryHelperNode = hero.querySelector('[data-bbai-hero-primary-helper]');
        var secondaryHelperNode = hero.querySelector('[data-bbai-hero-secondary-helper]');
        var tertiaryHelperNode = hero.querySelector('[data-bbai-hero-tertiary-helper]');
        var usageLine = hero.querySelector('[data-bbai-banner-usage-line]');
        var progressFill = hero.querySelector('[data-bbai-banner-progress]');
        var loopNode = hero.querySelector('[data-bbai-hero-loop]');
        var loopScanNode = hero.querySelector('[data-bbai-hero-loop-scan]');
        var loopSettingsNode = hero.querySelector('[data-bbai-hero-loop-settings]');
        var loopUpgradeNode = hero.querySelector('[data-bbai-hero-loop-upgrade]');
        var loopTensionNode = hero.querySelector('[data-bbai-hero-loop-tension]');

        function renderAction(itemNode, controlNode, helperNode, helperId, actionConfig) {
            var config;

            if (!itemNode || !controlNode) {
                return;
            }

            if (!actionConfig || !actionConfig.label) {
                itemNode.hidden = true;
                controlNode.hidden = true;
                if (helperNode) {
                    helperNode.hidden = true;
                    helperNode.textContent = '';
                }
                return;
            }

            config = $.extend(true, {}, actionConfig);
            controlNode.hidden = false;

            if (helperNode) {
                helperNode.textContent = actionConfig.helper || '';
                helperNode.hidden = !(actionConfig.helper || '');
            }

            if (actionConfig.helper && helperId) {
                config.attributes = $.extend({}, config.attributes || {}, {
                    'aria-describedby': helperId
                });
            }

            setInteractiveControl(controlNode, config);
            itemNode.hidden = false;
        }

        hero.setAttribute('data-state', model.state || 'healthy-free');
        hero.setAttribute('data-tone', model.tone || 'healthy');
        hero.classList.remove('bbai-banner--success', 'bbai-banner--warning');
        hero.classList.add((model.tone || 'healthy') === 'healthy' ? 'bbai-banner--success' : 'bbai-banner--warning');
        hero.setAttribute('data-bbai-banner-used', String(Math.max(0, parseCount(data.creditsUsed))));
        hero.setAttribute('data-bbai-banner-limit', String(Math.max(1, parseCount(data.creditsTotal))));
        hero.setAttribute('data-bbai-banner-remaining', String(Math.max(0, parseCount(data.creditsRemaining))));
        hero.setAttribute('data-bbai-banner-missing-count', String(Math.max(0, parseCount(data.missing))));
        hero.setAttribute('data-bbai-banner-weak-count', String(Math.max(0, parseCount(data.weak))));

        if (iconNode) {
            iconNode.innerHTML = getDashboardHeroIconMarkup(model.tone || 'healthy');
        }
        if (headline) {
            headline.textContent = model.headline || '';
        }
        if (subtext) {
            subtext.textContent = model.subtext || '';
        }
        if (nextStepNode) {
            nextStepNode.textContent = model.nextStep || '';
            nextStepNode.hidden = !model.nextStep;
        }
        if (workflowRow) {
            workflowRow.hidden = !model.workflowHint;
        }
        if (workflowNode) {
            workflowNode.textContent = model.workflowHint || '';
        }
        if (noteNode) {
            noteNode.textContent = model.note || '';
            noteNode.hidden = !model.note;
        }

        renderAction(primaryItem, generatorCta, primaryHelperNode, 'bbai-dashboard-hero-primary-helper', model.primaryAction);
        renderAction(secondaryItem, libraryCta, secondaryHelperNode, 'bbai-dashboard-hero-secondary-helper', model.secondaryAction);
        renderAction(tertiaryItem, secondaryLink, tertiaryHelperNode, 'bbai-dashboard-hero-tertiary-helper', model.tertiaryAction);

        if (loopNode) {
            var loopActions = Array.isArray(model.loopActions) ? model.loopActions : [];
            var renderLoopAction = function(node, config) {
                if (!node) {
                    return;
                }
                if (!config || !config.label) {
                    node.hidden = true;
                    return;
                }
                setInteractiveControl(node, config);
                node.hidden = false;
            };

            loopNode.hidden = loopActions.length === 0;
            renderLoopAction(loopScanNode, loopActions[0] || null);
            renderLoopAction(loopSettingsNode, loopActions[1] || null);
            renderLoopAction(loopUpgradeNode, loopActions[2] || null);

            if (loopTensionNode) {
                loopTensionNode.textContent = model.upgradeTensionLine || '';
                loopTensionNode.hidden = !model.upgradeTensionLine;
            }
        }

        syncHeroLinkRow(hero);

        if (usageLine) {
            var resetCopy = getPlanResetLine(data) || getUsageResetCopy(usageLine);
            usageLine.setAttribute('data-bbai-reset-copy', resetCopy || '');
            usageLine.innerHTML =
                '<span class="bbai-dashboard-hero__usage-pill bbai-banner__pill">' + escapeDashboardHtml(getPlanLabel(data)) + '</span>' +
                '<span class="bbai-dashboard-hero__usage-primary bbai-banner__usage-primary">' + escapeDashboardHtml(getRemainingPlanLine(data)) + '</span>' +
                (resetCopy ? '<span class="bbai-dashboard-hero__usage-secondary bbai-banner__usage-secondary">' + escapeDashboardHtml(resetCopy) + '</span>' : '');
        }

        if (progressFill) {
            var usagePercent = model.usagePercent;
            progressFill.setAttribute('data-bbai-banner-progress-target', String(Math.round(usagePercent)));
            animateLinearProgress(progressFill, usagePercent, 400, 40);
            var progressTrack = progressFill.parentNode;
            if (progressTrack && typeof progressTrack.setAttribute === 'function') {
                progressTrack.setAttribute('aria-valuenow', String(Math.round(usagePercent)));
                var usedNow = Math.max(0, parseCount(data.creditsUsed));
                var limitNow = Math.max(1, parseCount(data.creditsTotal));
                progressTrack.setAttribute(
                    'aria-valuetext',
                    sprintf(
                        /* translators: 1: percent used, 2: credits used, 3: credit limit */
                        __('%1$s%% used — %2$s of %3$s credits this cycle.', 'beepbeep-ai-alt-text-generator'),
                        String(Math.round(usagePercent)),
                        formatCount(usedNow),
                        formatCount(limitNow)
                    )
                );
            }
        }

        updateSuccessLoopState(hero, data, model);
    }

    function renderQuickActions(data) {
        var row = document.querySelector('[data-bbai-quick-actions]');
        if (!row || !data) {
            return;
        }

        var generateButton = row.querySelector('[data-bbai-quick-action="generate-missing"]');
        var reviewButton = row.querySelector('[data-bbai-quick-action="review-weak"]');
        var bulkButton = row.querySelector('[data-bbai-quick-action="bulk-optimize"]');
        var generatePrimary = !hasScanResults(data) || data.missing > 0;
        var reviewPrimary = !generatePrimary && data.weak > 0;
        var bulkPrimary = !generatePrimary && !reviewPrimary;

        function setQuickButton(node, config) {
            if (!node) {
                return;
            }

            var variant = config.primary ? 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--primary' : 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--secondary';
            node.className = variant + (config.disabled ? ' is-disabled' : '') + (config.complete ? ' is-complete' : '');
            setInteractiveControl(node, config);

            var labelNode = node.querySelector('[data-bbai-quick-action-label]');
            var helperNode = node.querySelector('[data-bbai-quick-action-helper]');

            if (labelNode) {
                labelNode.textContent = config.label || '';
            }

            if (helperNode) {
                helperNode.textContent = config.helper || '';
                helperNode.hidden = !config.helper;
            }
        }

        var isAnonymousTrial = isAnonymousTrialState(data);
        var freePlanOffer = getAnonymousTrialOffer(data);

        if (!hasScanResults(data)) {
            setQuickButton(generateButton, {
                label: __('Scan media library', 'beepbeep-ai-alt-text-generator'),
                helper: __('Find images missing ALT text before generating descriptions.', 'beepbeep-ai-alt-text-generator'),
                bbaiAction: 'scan-opportunity',
                primary: generatePrimary,
                preserveContent: true
            });
        } else if (data.missing > 0 && !hasCreditsAvailable(data)) {
            setQuickButton(generateButton, {
                label: isAnonymousTrial
                    ? __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator')
                    : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                helper: isAnonymousTrial
                    ? sprintf(
                        __('Unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    )
                    : __('Continue generating ALT text without interruption.', 'beepbeep-ai-alt-text-generator'),
                action: isAnonymousTrial ? 'show-dashboard-auth' : 'show-upgrade-modal',
                primary: generatePrimary,
                preserveContent: true,
                attributes: isAnonymousTrial ? { 'data-auth-tab': 'register' } : null,
                removeAttributes: ['data-bbai-action']
            });
        } else if (data.missing > 0) {
            var generateLabel = __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator');
            var generateHelper = formatCreditHelper(
                data.missing,
                data.creditsRemaining > 0 ? data.creditsRemaining : data.missing
            );

            setQuickButton(generateButton, {
                label: generateLabel,
                helper: generateHelper,
                action: 'generate-missing',
                bbaiAction: 'generate_missing',
                primary: generatePrimary,
                preserveContent: true,
                ariaLabel: generateLabel + '. ' + generateHelper
            });
        } else {
            var generateDoneHelper = __('No images are currently missing ALT text.', 'beepbeep-ai-alt-text-generator');
            setQuickButton(generateButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: generateDoneHelper,
                href: data.libraryUrl,
                primary: generatePrimary,
                complete: true,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }

        if (!hasScanResults(data)) {
            setQuickButton(reviewButton, {
                label: __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                helper: __('Scan first to find weaker ALT descriptions that need review.', 'beepbeep-ai-alt-text-generator'),
                primary: reviewPrimary,
                disabled: true,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.weak > 0 && !hasCreditsAvailable(data)) {
            setQuickButton(reviewButton, {
                label: isAnonymousTrial
                    ? __('Open ALT Library', 'beepbeep-ai-alt-text-generator')
                    : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                helper: isAnonymousTrial
                    ? __('Your trial results are still available to review and edit.', 'beepbeep-ai-alt-text-generator')
                    : __('Your existing results are still available to review.', 'beepbeep-ai-alt-text-generator'),
                action: isAnonymousTrial ? '' : 'show-upgrade-modal',
                href: isAnonymousTrial ? (data.needsReviewLibraryUrl || data.libraryUrl || '#') : '#',
                primary: reviewPrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.weak > 0) {
            var reviewLabel = __('Review ALT text', 'beepbeep-ai-alt-text-generator');
            var reviewHelper = __('Open the ALT Library filtered to descriptions that need review.', 'beepbeep-ai-alt-text-generator');
            var reviewHref = data.needsReviewLibraryUrl || data.libraryUrl || '#';

            setQuickButton(reviewButton, {
                label: reviewLabel,
                helper: reviewHelper,
                href: reviewHref,
                primary: reviewPrimary,
                preserveContent: true,
                removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source'],
                ariaLabel: reviewLabel + '. ' + reviewHelper
            });
        } else {
            setQuickButton(reviewButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('No weak ALT descriptions are waiting for review.', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                primary: reviewPrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }

        if (!bulkButton) {
            return;
        }

        if (!data.isPremium) {
            setQuickButton(bulkButton, {
                label: isAnonymousTrial
                    ? __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator')
                    : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                helper: isAnonymousTrial
                    ? sprintf(
                        __('Save your progress and unlock %d generations per month.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    )
                    : __('Never worry about missing ALT text again.', 'beepbeep-ai-alt-text-generator'),
                action: isAnonymousTrial ? 'show-dashboard-auth' : 'show-upgrade-modal',
                primary: bulkPrimary,
                preserveContent: true,
                attributes: isAnonymousTrial ? { 'data-auth-tab': 'register' } : null,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
            return;
        }

        if (!hasScanResults(data)) {
            setQuickButton(bulkButton, {
                label: __('Scan media library', 'beepbeep-ai-alt-text-generator'),
                helper: __('Scan first to find images that can be fixed automatically.', 'beepbeep-ai-alt-text-generator'),
                bbaiAction: 'scan-opportunity',
                primary: bulkPrimary,
                preserveContent: true,
                removeAttributes: ['data-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.missing > 0) {
            setQuickButton(bulkButton, {
                label: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                helper: __('Bulk generation is available on your plan.', 'beepbeep-ai-alt-text-generator'),
                action: 'generate-missing',
                bbaiAction: 'generate_missing',
                primary: bulkPrimary,
                preserveContent: true
            });
        } else if (data.weak > 0) {
            setQuickButton(bulkButton, {
                label: __('Improve weak ALT text', 'beepbeep-ai-alt-text-generator'),
                helper: __('Regenerate descriptions that need stronger wording (uses credits).', 'beepbeep-ai-alt-text-generator'),
                action: 'regenerate-all',
                attributes: {
                    'data-bbai-regenerate-scope': 'needs-review',
                    'data-bbai-generation-source': 'regenerate-weak'
                },
                removeAttributes: ['data-bbai-action'],
                primary: bulkPrimary,
                preserveContent: true
            });
        } else {
            setQuickButton(bulkButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('No bulk fixes are needed right now.', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                primary: bulkPrimary,
                preserveContent: true,
                removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }
    }

    function renderWorkflow(data) {
        if (!data) {
            return;
        }

        var reviewDesc = document.querySelector('[data-bbai-workflow-review-desc]');
        var reviewCta = document.querySelector('[data-bbai-workflow-review-cta]');
        var reviewDescription = hasScanResults(data)
            ? __('Review, edit, or improve generated descriptions.', 'beepbeep-ai-alt-text-generator')
            : __('Review, edit, or improve generated descriptions after scanning.', 'beepbeep-ai-alt-text-generator');

        if (reviewDesc) {
            reviewDesc.textContent = reviewDescription;
        }

        if (reviewCta) {
            reviewCta.className = 'bbai-workflow-step__btn bbai-workflow-step__btn--secondary';
            setInteractiveControl(reviewCta, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }
    }

    function getStatusCardSegmentColor(segmentKey, activeSegment) {
        var palette = {
            optimized: {
                base: '#3fa66f',
                active: '#2f915e',
                muted: '#deeee4'
            },
            weak: {
                base: '#f59e0b',
                active: '#d97706',
                muted: '#fde7c4'
            },
            missing: {
                base: '#ef4444',
                active: '#dc2626',
                muted: '#fee2e2'
            }
        };
        var colors = palette[segmentKey] || {
            base: '#e2e8f0',
            active: '#cbd5e1',
            muted: '#e2e8f0'
        };

        if (!activeSegment) {
            return colors.base;
        }

        return activeSegment === segmentKey ? colors.active : colors.muted;
    }

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - Math.min(1, Math.max(0, t)), 3);
    }

    function getStatusCardDonutGradientProgress(statusCoverage, activeSegment, progress) {
        var optimizedAngle;
        var weakAngle;
        var missingAngle;
        var optimizedEnd;
        var weakEnd;
        var missingEnd;
        var neutralTail = '#e2e8f0';
        var p = Math.min(1, Math.max(0, progress));

        if (!statusCoverage || statusCoverage.total <= 0 || p <= 0) {
            return 'conic-gradient(' + neutralTail + ' 0deg 360deg)';
        }

        optimizedAngle = 360 * (statusCoverage.optimized / statusCoverage.total);
        weakAngle = 360 * (statusCoverage.weak / statusCoverage.total);
        missingAngle = 360 * (statusCoverage.missing / statusCoverage.total);

        optimizedEnd = optimizedAngle * p;
        weakEnd = optimizedEnd + weakAngle * p;
        missingEnd = weakEnd + missingAngle * p;

        return 'conic-gradient(' +
            getStatusCardSegmentColor('optimized', activeSegment) + ' 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('weak', activeSegment) + ' ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('missing', activeSegment) + ' ' + weakEnd.toFixed(3) + 'deg ' + missingEnd.toFixed(3) + 'deg, ' +
            neutralTail + ' ' + missingEnd.toFixed(3) + 'deg 360deg)';
    }

    function animateStatusCardDonutIntro(donutNode, statusCard, statusCoverage) {
        var startTs = null;
        var duration = 960;

        function tick(now) {
            var activeSegment;
            var t;
            var p;
            var rafId;

            if (!donutNode || !statusCard || !document.contains(donutNode)) {
                return;
            }

            activeSegment = getStatusCardActiveSegment(statusCard);
            if (startTs === null) {
                startTs = now;
            }
            t = Math.min(1, (now - startTs) / duration);
            p = easeOutCubic(t);
            donutNode.style.transition = 'none';
            donutNode.style.background = getStatusCardDonutGradientProgress(statusCoverage, activeSegment, p);

            if (t < 1) {
                rafId = window.requestAnimationFrame(tick);
                if (rafId) {
                    donutNode.setAttribute('data-bbai-donut-intro-raf', String(rafId));
                }
            } else {
                donutNode.removeAttribute('data-bbai-donut-intro-raf');
                donutNode.removeAttribute('data-bbai-donut-intro-animating');
                donutNode.setAttribute('data-bbai-donut-intro-played', '1');
                donutNode.style.transition = 'background 0.45s cubic-bezier(0.2, 0.8, 0.2, 1)';
                donutNode.style.background = getStatusCardDonutGradient(statusCoverage, activeSegment);
                donutNode.setAttribute('data-bbai-status-active-segment', activeSegment);
            }
        }

        donutNode.setAttribute('data-bbai-donut-intro-animating', '1');
        donutNode.style.transition = 'none';
        donutNode.style.background = getStatusCardDonutGradientProgress(
            statusCoverage,
            getStatusCardActiveSegment(statusCard),
            0
        );
        window.requestAnimationFrame(tick);
    }

    function getStatusCardDonutGradient(statusCoverage, activeSegment) {
        var optimizedAngle;
        var weakAngle;
        var optimizedEnd;
        var weakEnd;

        if (!statusCoverage || statusCoverage.total <= 0) {
            return 'conic-gradient(#e2e8f0 0deg 360deg)';
        }

        optimizedAngle = 360 * (statusCoverage.optimized / statusCoverage.total);
        weakAngle = 360 * (statusCoverage.weak / statusCoverage.total);
        optimizedEnd = Math.max(0, Math.min(360, optimizedAngle));
        weakEnd = Math.max(optimizedEnd, Math.min(360, optimizedEnd + weakAngle));

        return 'conic-gradient(' +
            getStatusCardSegmentColor('optimized', activeSegment) + ' 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('weak', activeSegment) + ' ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('missing', activeSegment) + ' ' + weakEnd.toFixed(3) + 'deg 360deg' +
            ')';
    }

    function getStatusCardActiveSegment(statusCard) {
        if (!statusCard) {
            return '';
        }

        return String(statusCard.getAttribute('data-bbai-status-active-segment') || '');
    }

    function applyStatusCardDonutState(statusCard, statusCoverage) {
        var donutNode = statusCard ? statusCard.querySelector('[data-bbai-status-donut]') : null;
        var activeSegment = getStatusCardActiveSegment(statusCard);
        var reduceMotion;
        var dashboardData;
        var coverageProcessing;
        var introRaf;

        if (!donutNode) {
            return;
        }

        dashboardData = getDashboardData();
        coverageProcessing = isCoverageProcessingActive(dashboardData, statusCoverage);

        if (coverageProcessing) {
            introRaf = donutNode.getAttribute('data-bbai-donut-intro-raf');
            if (introRaf) {
                window.cancelAnimationFrame(parseInt(introRaf, 10));
                donutNode.removeAttribute('data-bbai-donut-intro-raf');
            }
            donutNode.removeAttribute('data-bbai-donut-intro-animating');
            donutNode.classList.add('bbai-command-donut--processing');
            donutNode.style.transition = 'background 0.45s cubic-bezier(0.2, 0.8, 0.2, 1)';
            donutNode.style.background = 'conic-gradient(#cbd5e1 0deg 360deg)';
            donutNode.setAttribute('data-bbai-status-active-segment', activeSegment);
            return;
        }

        donutNode.classList.remove('bbai-command-donut--processing');

        if (donutNode.getAttribute('data-bbai-donut-intro-animating') === '1') {
            introRaf = donutNode.getAttribute('data-bbai-donut-intro-raf');
            if (!introRaf) {
                donutNode.removeAttribute('data-bbai-donut-intro-animating');
                donutNode.setAttribute('data-bbai-donut-intro-played', '1');
            } else if (activeSegment) {
                window.cancelAnimationFrame(parseInt(introRaf, 10));
                donutNode.removeAttribute('data-bbai-donut-intro-raf');
                donutNode.removeAttribute('data-bbai-donut-intro-animating');
                donutNode.setAttribute('data-bbai-donut-intro-played', '1');
            } else {
                return;
            }
        }

        reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (
            statusCoverage &&
            statusCoverage.total > 0 &&
            donutNode.getAttribute('data-bbai-donut-intro-played') !== '1' &&
            !reduceMotion
        ) {
            animateStatusCardDonutIntro(donutNode, statusCard, statusCoverage);
            return;
        }

        donutNode.style.transition = 'background 0.45s cubic-bezier(0.2, 0.8, 0.2, 1)';
        donutNode.style.background = getStatusCardDonutGradient(statusCoverage, activeSegment);
        donutNode.setAttribute('data-bbai-status-active-segment', activeSegment);

        if (reduceMotion && statusCoverage && statusCoverage.total > 0) {
            donutNode.setAttribute('data-bbai-donut-intro-played', '1');
        }
    }

    function getStatusCardSelectedSegment(statusCard) {
        if (!statusCard) {
            return '';
        }

        return String(
            statusCard.getAttribute('data-bbai-status-selected-segment') ||
            statusCard.getAttribute('data-bbai-status-active-segment') ||
            ''
        );
    }

    function setStatusCardActiveSegment(statusCard, segmentKey, persistSelection) {
        var activeSegment = String(segmentKey || '');
        var dashboardData;

        if (!statusCard) {
            return;
        }

        if (persistSelection) {
            if (activeSegment) {
                statusCard.setAttribute('data-bbai-status-selected-segment', activeSegment);
            } else {
                statusCard.removeAttribute('data-bbai-status-selected-segment');
            }
        }

        if (!activeSegment) {
            activeSegment = getStatusCardSelectedSegment(statusCard);
        }

        if (activeSegment) {
            statusCard.setAttribute('data-bbai-status-active-segment', activeSegment);
        } else {
            statusCard.removeAttribute('data-bbai-status-active-segment');
        }

        Array.prototype.forEach.call(statusCard.querySelectorAll('[data-bbai-status-row]'), function(row) {
            row.classList.toggle(
                'bbai-filter-group__item--active',
                !!activeSegment && String(row.getAttribute('data-bbai-status-segment') || '') === activeSegment
            );
            row.classList.toggle(
                'is-active',
                !!activeSegment && String(row.getAttribute('data-bbai-status-segment') || '') === activeSegment
            );
        });

        dashboardData = getDashboardData();
        applyStatusCardDonutState(statusCard, getStatusCoverageData(dashboardData));
    }

    function getStatusCardFilterValue(segmentKey, fallbackFilter) {
        if (fallbackFilter) {
            return String(fallbackFilter);
        }

        if (segmentKey === 'optimized') {
            return 'optimized';
        }
        if (segmentKey === 'weak') {
            return 'needs_review';
        }
        if (segmentKey === 'missing') {
            return 'missing';
        }

        return '';
    }

    function buildStatusCardFilterUrl(baseUrl, filterValue) {
        var cleanBaseUrl = String(baseUrl || '');
        var statusValue = String(filterValue || '');

        if (!cleanBaseUrl) {
            return '';
        }

        try {
            var url = new URL(cleanBaseUrl, window.location.href);
            if (statusValue) {
                url.searchParams.set('status', statusValue);
            } else {
                url.searchParams.delete('status');
            }
            return url.toString();
        } catch (error) {
            if (!statusValue) {
                return cleanBaseUrl;
            }
            return cleanBaseUrl + (cleanBaseUrl.indexOf('?') === -1 ? '?' : '&') + 'status=' + encodeURIComponent(statusValue);
        }
    }

    function resolveStatusCardRowUrl(row) {
        var dashboardData;
        var targetUrl;
        var segmentKey;
        var filterValue;

        if (!row) {
            return '';
        }

        targetUrl = String(row.getAttribute('href') || row.getAttribute('data-bbai-status-url') || '');
        if (!targetUrl) {
            dashboardData = getDashboardData();
            segmentKey = String(row.getAttribute('data-bbai-status-segment') || '');
            filterValue = getStatusCardFilterValue(
                segmentKey,
                row.getAttribute('data-bbai-status-filter') || ''
            );
            targetUrl = buildStatusCardFilterUrl(dashboardData && dashboardData.libraryUrl, filterValue);
        }

        return targetUrl;
    }

    function navigateToStatusCardFilter(row) {
        var dashboardData = getDashboardData();
        var targetUrl = resolveStatusCardRowUrl(row);

        if (dashboardData && !dashboardCanAccessLibrary(dashboardData)) {
            openDashboardLockedGate(row);
            return;
        }

        if (targetUrl) {
            window.location.assign(targetUrl);
        }
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function getStatusCardMissingRow(statusCard) {
        return statusCard ? statusCard.querySelector('[data-bbai-status-segment="missing"]') : null;
    }

    function getMissingStatusRowLabel(count) {
        return __('Missing', 'beepbeep-ai-alt-text-generator');
    }

    function isMissingStatusRowActionable(data, statusCoverage) {
        return !!(
            data &&
            statusCoverage &&
            statusCoverage.missing > 0 &&
            getPrimaryAction(data) === 'generate_missing'
        );
    }

    /**
     * Visual emphasis + attention animation: any real missing ALT count with a meaningful scan.
     * Stricter than nothing — avoids animating empty / pre-scan / processing placeholder states.
     * (Aria "actionable" wording still uses isMissingStatusRowActionable + credits / primary CTA.)
     */
    function shouldEmphasizeMissingStatusRow(data, statusCoverage) {
        return !!(
            data &&
            statusCoverage &&
            statusCoverage.missing > 0 &&
            statusCoverage.total > 0 &&
            hasScanResults(data) &&
            !isCoverageProcessingActive(data, statusCoverage)
        );
    }

    function clearDashboardMissingAttentionTimer(statusCard) {
        if (statusCard && statusCard._bbaiMissingAttentionTimer) {
            window.clearTimeout(statusCard._bbaiMissingAttentionTimer);
            statusCard._bbaiMissingAttentionTimer = null;
        }
    }

    function getMissingStatusRowAriaLabel(count, isActionable) {
        var safeCount = Math.max(0, parseCount(count));

        if (safeCount <= 0) {
            return __('View images missing ALT text in ALT Library', 'beepbeep-ai-alt-text-generator');
        }

        return isActionable
            ? sprintf(
                _n(
                    'Open %s image that needs ALT text in ALT Library',
                    'Open %s images that need ALT text in ALT Library',
                    safeCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                formatCount(safeCount)
            )
            : sprintf(
                _n(
                    'View %s image missing ALT text in ALT Library',
                    'View %s images missing ALT text in ALT Library',
                    safeCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                formatCount(safeCount)
            );
    }

    function hasDismissedMissingStatusCueForSession() {
        try {
            return sessionStorage.getItem('bbai_status_missing_row_clicked') === '1';
        } catch (error) {
            return false;
        }
    }

    function dismissMissingStatusCueForSession() {
        try {
            sessionStorage.setItem('bbai_status_missing_row_clicked', '1');
        } catch (error) {
            // Storage access can be blocked.
        }
    }

    function applyMissingStatusRowState(statusCard, data, statusCoverage) {
        var missingRow = getStatusCardMissingRow(statusCard);
        var labelNode;
        var missingCount;
        var emphasize;
        var actionableStrict;

        if (!missingRow) {
            return;
        }

        labelNode = missingRow.querySelector('.bbai-filter-group__label');
        missingCount = statusCoverage ? Math.max(0, parseCount(statusCoverage.missing)) : 0;
        emphasize = shouldEmphasizeMissingStatusRow(data, statusCoverage);
        actionableStrict = isMissingStatusRowActionable(data, statusCoverage);

        if (labelNode) {
            labelNode.textContent = getMissingStatusRowLabel(missingCount);
        }

        missingRow.classList.toggle('bbai-filter-group__item--actionable', emphasize);
        missingRow.setAttribute('aria-label', getMissingStatusRowAriaLabel(missingCount, actionableStrict));

        if (!emphasize) {
            clearDashboardMissingAttentionTimer(statusCard);
            missingRow.classList.remove('bbai-dashboard-missing-attention');
            statusCard.removeAttribute('data-bbai-missing-attention-complete');
            return;
        }

        if (prefersReducedMotion() || hasDismissedMissingStatusCueForSession()) {
            return;
        }

        if (statusCard._bbaiMissingAttentionTimer) {
            return;
        }

        if (statusCard.getAttribute('data-bbai-missing-attention-complete') === '1') {
            return;
        }

        missingRow.classList.remove('bbai-dashboard-missing-attention');
        missingRow.offsetWidth;
        missingRow.classList.add('bbai-dashboard-missing-attention');

        statusCard._bbaiMissingAttentionTimer = window.setTimeout(function() {
            if (missingRow && document.body && document.body.contains(missingRow)) {
                missingRow.classList.remove('bbai-dashboard-missing-attention');
            }
            statusCard._bbaiMissingAttentionTimer = null;
            statusCard.setAttribute('data-bbai-missing-attention-complete', '1');
        }, 3000);
    }

    function setStatusCardRefreshLoading(button, isLoading) {
        if (!button) {
            return;
        }

        button.classList.toggle('is-loading', !!isLoading);
        button.disabled = !!isLoading;
        if (isLoading) {
            button.setAttribute('aria-busy', 'true');
        } else {
            button.removeAttribute('aria-busy');
        }
        button.setAttribute(
            'aria-label',
            isLoading
                ? __('Refreshing library scan', 'beepbeep-ai-alt-text-generator')
                : __('Refresh library scan', 'beepbeep-ai-alt-text-generator')
        );
    }

    function dispatchDashboardFeedbackMessage(type, message) {
        if (!message) {
            return;
        }

        try {
            document.dispatchEvent(new CustomEvent('bbai:dashboard-feedback', {
                detail: {
                    type: type || 'info',
                    message: message,
                    duration: 4200
                }
            }));
        } catch (error) {
            // Ignore CustomEvent failures.
        }
    }

    var bbaiStatusCardRefreshDelegated = false;

    function ensureStatusCardRefreshClickDelegated() {
        if (bbaiStatusCardRefreshDelegated) {
            return;
        }
        bbaiStatusCardRefreshDelegated = true;

        document.addEventListener('click', function(event) {
            var btn = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-refresh]')
                : null;
            if (!btn) {
                return;
            }
            var dashMain = document.getElementById('bbai-dashboard-main');
            if (!dashMain || !dashMain.contains(btn)) {
                return;
            }
            handleStatusCardRefresh(event, btn);
        });
    }

    function handleStatusCardRefresh(event, refreshButtonOverride) {
        var button = refreshButtonOverride || null;
        if (!button && event && event.currentTarget && event.currentTarget.hasAttribute &&
                event.currentTarget.hasAttribute('data-bbai-status-refresh')) {
            button = event.currentTarget;
        }
        if (!button && event && event.target && typeof event.target.closest === 'function') {
            button = event.target.closest('[data-bbai-status-refresh]');
        }
        var request;
        var fallbackMessage = __('Unable to refresh the library scan right now.', 'beepbeep-ai-alt-text-generator');

        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }

        if (!button || button.classList.contains('is-loading')) {
            return;
        }

        if (typeof window.bbaiRefreshDashboardCoverage !== 'function') {
            dispatchDashboardFeedbackMessage('error', fallbackMessage);
            return;
        }

        setStatusCardRefreshLoading(button, true);
        request = window.bbaiRefreshDashboardCoverage();

        if (!request || typeof request.always !== 'function') {
            setStatusCardRefreshLoading(button, false);
            return;
        }

        if (typeof request.fail === 'function') {
            request.fail(function(errorMessage) {
                dispatchDashboardFeedbackMessage(
                    'error',
                    errorMessage || __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator')
                );
            });
        }

        request.always(function() {
            setStatusCardRefreshLoading(button, false);
        });
    }

    function bindStatusCardInteractions() {
        var statusCard = document.querySelector('[data-bbai-dashboard-status-card="1"]');
        var statusRows;
        var refreshButton;

        if (!statusCard || statusCard.getAttribute('data-bbai-status-interactions-bound') === '1') {
            return;
        }

        statusRows = statusCard.querySelectorAll('[data-bbai-status-row]');
        refreshButton = statusCard.querySelector('[data-bbai-status-refresh]');

        statusCard.setAttribute('data-bbai-status-interactions-bound', '1');

        if (!statusRows.length && !refreshButton) {
            return;
        }

        Array.prototype.forEach.call(statusRows, function(row) {
            var targetUrl = resolveStatusCardRowUrl(row);

            if (targetUrl) {
                row.setAttribute('href', targetUrl);
            }

            row.addEventListener('mouseenter', function() {
                setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '');
            });

            row.addEventListener('mouseleave', function(event) {
                var related = event.relatedTarget;
                var nextRow = related && related.nodeType === 1 && typeof related.closest === 'function'
                    ? related.closest('[data-bbai-status-row]')
                    : null;

                if (nextRow && statusCard.contains(nextRow)) {
                    setStatusCardActiveSegment(statusCard, nextRow.getAttribute('data-bbai-status-segment') || '');
                    return;
                }

                if (
                    document.activeElement &&
                    typeof document.activeElement.closest === 'function' &&
                    statusCard.contains(document.activeElement) &&
                    document.activeElement.closest('[data-bbai-status-row]')
                ) {
                    setStatusCardActiveSegment(
                        statusCard,
                        document.activeElement.closest('[data-bbai-status-row]').getAttribute('data-bbai-status-segment') || ''
                    );
                    return;
                }

                setStatusCardActiveSegment(statusCard, '', false);
            });
        });

        statusCard.addEventListener('focusin', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;

            if (!row || !statusCard.contains(row)) {
                return;
            }

            setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '');
        });

        statusCard.addEventListener('focusout', function() {
            window.setTimeout(function() {
                var activeRow = document.activeElement && typeof document.activeElement.closest === 'function'
                    ? document.activeElement.closest('[data-bbai-status-row]')
                    : null;

                if (activeRow && statusCard.contains(activeRow)) {
                    setStatusCardActiveSegment(statusCard, activeRow.getAttribute('data-bbai-status-segment') || '');
                    return;
                }

                if (!statusCard.matches(':hover')) {
                    setStatusCardActiveSegment(statusCard, '', false);
                }
            }, 0);
        });

        statusCard.addEventListener('click', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;
            var targetUrl;
            var isModifiedClick;

            if (event.defaultPrevented) {
                return;
            }

            if (!row || !statusCard.contains(row)) {
                return;
            }

            if (row.hasAttribute('data-bbai-dashboard-status-pill')) {
                return;
            }

            isModifiedClick = !!(
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                event.button !== 0
            );
            if (isModifiedClick) {
                return;
            }

            event.preventDefault();
            if (
                String(row.getAttribute('data-bbai-status-segment') || '') === 'missing' &&
                row.classList.contains('bbai-filter-group__item--actionable')
            ) {
                dismissMissingStatusCueForSession();
                clearDashboardMissingAttentionTimer(statusCard);
                row.classList.remove('bbai-dashboard-missing-attention');
                statusCard.setAttribute('data-bbai-missing-attention-complete', '1');
            }
            targetUrl = resolveStatusCardRowUrl(row);
            if (!targetUrl) {
                return;
            }

            setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '', true);
            row.classList.add('is-pressed');

            window.setTimeout(function() {
                navigateToStatusCardFilter(row);
            }, 110);

            window.setTimeout(function() {
                row.classList.remove('is-pressed');
            }, 220);
        });

        ensureStatusCardRefreshClickDelegated();
    }

    function renderStatusCard(data) {
        if (!data) {
            return;
        }

        var statusCoverage = getStatusCoverageData(data);
        var coverageProcessing = isCoverageProcessingActive(data, statusCoverage);
        var statusCard = document.querySelector('[data-bbai-dashboard-status-card="1"]');
        if (!statusCard) {
            stopLastScanRelativeTicker();
            return;
        }

        var summaryRatioNode = statusCard.querySelector('[data-bbai-status-summary-ratio]');
        var summaryMetaNode = statusCard.querySelector('[data-bbai-status-last-scan]');
        var summaryDetailNode = statusCard.querySelector('[data-bbai-status-summary-detail]');
        var metrics = {
            all: statusCard.querySelector('[data-bbai-status-metric="all"]'),
            optimized: statusCard.querySelector('[data-bbai-status-metric="optimized"]'),
            weak: statusCard.querySelector('[data-bbai-status-metric="weak"]'),
            missing: statusCard.querySelector('[data-bbai-status-metric="missing"]')
        };
        var coverageValue = statusCard.querySelector('[data-bbai-status-coverage-value]');
        var percentWrap = statusCard.querySelector('[data-bbai-status-coverage-percent-wrap]');
        var processingWrap = statusCard.querySelector('[data-bbai-status-coverage-processing]');
        var processingTextNode = statusCard.querySelector('[data-bbai-status-coverage-processing-text]');
        var valueBlock = statusCard.querySelector('.bbai-command-status__value');
        var motivationNode = statusCard.querySelector('[data-bbai-status-coverage-motivation]');
        var coverageBadgeNode = statusCard.querySelector('[data-bbai-status-coverage-badge]');
        var prevCoverage = parseInt(statusCard.getAttribute('data-bbai-prev-coverage'), 10);
        var motivationBadge = getCoverageMotivationAndBadge(statusCoverage, data);
        var processingMsg = __('Processing your first results — this usually takes a few seconds', 'beepbeep-ai-alt-text-generator');
        var processingDetailMsg = __('This count updates after ALT text is saved — it usually takes a few seconds.', 'beepbeep-ai-alt-text-generator');

        if (valueBlock) {
            valueBlock.classList.toggle('bbai-command-status__value--processing', coverageProcessing);
        }

        if (percentWrap) {
            percentWrap.hidden = coverageProcessing;
        }

        if (processingWrap) {
            processingWrap.hidden = !coverageProcessing;
        }

        if (processingTextNode && coverageProcessing) {
            processingTextNode.textContent = processingMsg;
        }

        statusCard.classList.toggle('bbai-command-card--coverage-processing', coverageProcessing);

        if (summaryRatioNode || summaryDetailNode) {
            var optimizedRatio = getOptimizedRatioCopy(statusCoverage);
            var summaryRatio = optimizedRatio;
            var summaryMeta = formatLastScanCopy(data.lastScanTs);
            var summaryDetail = '';

            if (!hasScanResults(data) || statusCoverage.total <= 0) {
                summaryRatio = __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
                summaryDetail = __('Run your first scan to see coverage and missing ALT text.', 'beepbeep-ai-alt-text-generator');
            } else if (coverageProcessing) {
                summaryDetail = processingDetailMsg;
            } else if (statusCoverage.missing > 0) {
                summaryDetail = statusCoverage.missing === 1
                    ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
                    : sprintf(
                        __('%s images away from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                        formatCount(statusCoverage.missing)
                    );
            } else if (statusCoverage.weak > 0) {
                summaryDetail = sprintf(
                    _n('%s description needs review.', '%s descriptions need review.', statusCoverage.weak, 'beepbeep-ai-alt-text-generator'),
                    formatCount(statusCoverage.weak)
                );
            } else if (statusCoverage.optimized > 0) {
                summaryDetail = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
            }

            if (summaryRatioNode) {
                summaryRatioNode.textContent = summaryRatio;
            }
            if (summaryMetaNode) {
                summaryMetaNode.textContent = summaryMeta;
                summaryMetaNode.hidden = !summaryMeta;
            }
            if (summaryDetailNode) {
                summaryDetailNode.textContent = summaryDetail;
                summaryDetailNode.hidden = !summaryDetail;
            }
        }

        Object.keys(metrics).forEach(function(key) {
            if (!metrics[key]) {
                return;
            }
            var value = key === 'all' ? statusCoverage.total : statusCoverage[key];
            metrics[key].textContent = formatCount(value);
        });

        applyMissingStatusRowState(statusCard, data, statusCoverage);

        if (coverageValue) {
            coverageValue.textContent = formatCount(statusCoverage.coverage);
            if (!isNaN(prevCoverage) && statusCoverage.coverage > prevCoverage) {
                coverageValue.classList.add('bbai-coverage-value--pulse');
                window.setTimeout(function() {
                    coverageValue.classList.remove('bbai-coverage-value--pulse');
                }, 900);
            }
        }

        statusCard.setAttribute('data-bbai-prev-coverage', String(statusCoverage.coverage));

        if (motivationNode) {
            motivationNode.textContent = motivationBadge.motivation || '';
            motivationNode.hidden = !motivationBadge.motivation;
        }

        if (coverageBadgeNode) {
            coverageBadgeNode.textContent = motivationBadge.badge || '';
            coverageBadgeNode.hidden = !motivationBadge.badge;
        }

        var isCoverageComplete = statusCoverage.total > 0 && statusCoverage.coverage >= 100;
        var wasAlreadyComplete = statusCard.classList.contains('bbai-command-card--coverage-complete');

        statusCard.classList.toggle('bbai-command-card--coverage-complete', isCoverageComplete);

        try {
            if (isCoverageComplete && !wasAlreadyComplete && !sessionStorage.getItem('bbai_coverage_card_glow_played')) {
                sessionStorage.setItem('bbai_coverage_card_glow_played', '1');
                statusCard.classList.add('bbai-command-card--coverage-glow');
                window.setTimeout(function() {
                    statusCard.classList.remove('bbai-command-card--coverage-glow');
                }, 320);
            }
        } catch (e) {
            // noop — storage blocked
        }

        ensureLastScanRelativeTicker();

        applyStatusCardDonutState(statusCard, statusCoverage);
    }

    function animateStatusRingSegment(segmentNode, segmentStyle, circumference) {
        if (!segmentNode || !segmentStyle) {
            return;
        }

        var hiddenDasharray = '0 ' + Math.max(0, circumference).toFixed(3);
        var targetOpacity = segmentStyle.isVisible ? '1' : '0';
        var hasAnimated = segmentNode.getAttribute('data-bbai-status-ring-initialized') === '1';
        var animationDelay = Math.max(0, parseInt(segmentStyle.animationOrder, 10) || 0) * 180;

        if (hasAnimated) {
            segmentNode.style.transition = 'stroke-dasharray 0.8s ease, stroke-dashoffset 0.8s ease, opacity 0.25s ease';
            segmentNode.style.strokeDasharray = segmentStyle.dasharray;
            segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
            segmentNode.style.opacity = targetOpacity;
            return;
        }

        segmentNode.style.transition = 'none';
        segmentNode.style.strokeDasharray = hiddenDasharray;
        segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
        segmentNode.style.opacity = targetOpacity;
        segmentNode.offsetWidth;

        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                segmentNode.style.transition = 'stroke-dasharray 0.8s ease ' + animationDelay + 'ms, stroke-dashoffset 0.8s ease ' + animationDelay + 'ms, opacity 0.25s ease ' + animationDelay + 'ms';
                segmentNode.style.strokeDasharray = segmentStyle.dasharray;
                segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
                segmentNode.style.opacity = targetOpacity;
                segmentNode.setAttribute('data-bbai-status-ring-initialized', '1');
            });
        });
    }

    function animateLinearProgress(node, targetPercent, duration, delay) {
        if (!node) {
            return;
        }

        var target = Math.min(100, Math.max(0, parseFloat(targetPercent) || 0));
        var transitionDuration = Math.max(0, parseInt(duration, 10) || 600);
        var transitionDelay = Math.max(0, parseInt(delay, 10) || 0);
        var hasAnimated = node.getAttribute('data-bbai-progress-initialized') === '1';
        var transitionValue = 'width ' + transitionDuration + 'ms cubic-bezier(0.33, 1, 0.68, 1)' + (transitionDelay > 0 ? ' ' + transitionDelay + 'ms' : '');

        if (hasAnimated) {
            node.style.transition = transitionValue;
            node.style.width = target + '%';
            return;
        }

        node.style.transition = 'none';
        node.style.width = '0%';
        node.offsetWidth;

        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                node.style.transition = transitionValue;
                node.style.width = target + '%';
                node.setAttribute('data-bbai-progress-initialized', '1');
            });
        });
    }

    function getAccessibilityImpact(data) {
        var optimized = Math.max(0, parseCount(data && data.optimized));
        var total = Math.max(0, parseCount(data && data.total));
        var coverage = total > 0 ? Math.round((optimized / total) * 100) : 0;
        var impact = {
            coverage: coverage,
            coverageLabel: formatCount(coverage),
            optimized: optimized,
            optimizedLabel: formatCount(optimized)
        };

        impact.altText = sprintf(
            __('Shareable accessibility badge showing a %1$s%% accessibility score and %2$s optimized images powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            impact.coverageLabel,
            impact.optimizedLabel
        );
        impact.embedHtml = '<a href="https://beepbeep.ai" target="_blank" rel="noreferrer noopener">\n<img src="' + getAccessibilityBadgeDataUrl(impact) + '"\nalt="' + escapeSvgText(impact.altText) + '">\n</a>';
        impact.shareText = sprintf(
            __('My WordPress site has an accessibility score of %1$s%% with %2$s optimized images powered by BeepBeep AI.', 'beepbeep-ai-alt-text-generator'),
            impact.coverageLabel,
            impact.optimizedLabel
        );

        return impact;
    }

    function escapeSvgText(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildAccessibilityBadgeSvg(impact) {
        var titleText = __('Accessibility badge powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator');
        var descText = impact && impact.altText
            ? impact.altText
            : sprintf(
                __('Shareable accessibility badge showing a %1$s%% accessibility score and %2$s optimized images powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
                impact.coverageLabel,
                impact.optimizedLabel
            );

        return [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 220" width="460" height="220" role="img" aria-labelledby="bbai-badge-title bbai-badge-desc">',
            '<title id="bbai-badge-title">', escapeSvgText(titleText), '</title>',
            '<desc id="bbai-badge-desc">', escapeSvgText(descText), '</desc>',
            '<defs>',
            '<linearGradient id="bbai-badge-bg" x1="30" y1="24" x2="430" y2="196" gradientUnits="userSpaceOnUse">',
            '<stop offset="0%" stop-color="#FFFFFF"/>',
            '<stop offset="100%" stop-color="#F3FBF5"/>',
            '</linearGradient>',
            '<linearGradient id="bbai-badge-score" x1="44" y1="58" x2="190" y2="58" gradientUnits="userSpaceOnUse">',
            '<stop offset="0%" stop-color="#16A34A"/>',
            '<stop offset="100%" stop-color="#15803D"/>',
            '</linearGradient>',
            '</defs>',
            '<rect x="1" y="1" width="458" height="218" rx="24" fill="url(#bbai-badge-bg)" stroke="#B7DFC0"/>',
            '<circle cx="396" cy="48" r="28" fill="#DCFCE7"/>',
            '<circle cx="423" cy="34" r="8" fill="#86EFAC"/>',
            '<path d="M384 49l9 9 18-20" fill="none" stroke="#15803D" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>',
            '<text x="36" y="44" fill="#15803D" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" letter-spacing="0.03em">', escapeSvgText(__('Accessibility score', 'beepbeep-ai-alt-text-generator')), '</text>',
            '<text x="36" y="112" fill="url(#bbai-badge-score)" font-family="Segoe UI, Arial, sans-serif" font-size="58" font-weight="700">', escapeSvgText(impact.coverageLabel), '%</text>',
            '<path d="M36 138h388" stroke="#CDE7D2" stroke-width="1"/>',
            '<text x="36" y="170" fill="#166534" font-family="Segoe UI, Arial, sans-serif" font-size="16" font-weight="600">', escapeSvgText(__('Images optimized', 'beepbeep-ai-alt-text-generator')), '</text>',
            '<text x="424" y="170" text-anchor="end" fill="#0F172A" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700">', escapeSvgText(impact.optimizedLabel), '</text>',
            '<text x="36" y="196" fill="#166534" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="600">', escapeSvgText(__('Powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator')), '</text>',
            '</svg>'
        ].join('');
    }

    function getAccessibilityBadgeDataUrl(impact) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(buildAccessibilityBadgeSvg(impact));
    }

    function getUpgradeCardCopy(data) {
        var usagePercent;
        var growthComparison;

        if (!data) {
            return null;
        }

        usagePercent = Math.min(100, Math.max(0, (data.creditsUsed / Math.max(1, data.creditsTotal)) * 100));
        growthComparison = getGrowthPlanComparison(data);

        if (!hasCreditsAvailable(data) && !data.isPremium) {
            return {
                context: __('You have reached your AI generation limit. Growth restarts progress immediately and keeps future uploads moving automatically.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Restart with Growth', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: true
            };
        }

        if (!data.isPremium && data.creditsRemaining <= 5) {
            return {
                context: __('You are running low on AI generations. Growth keeps automation running as new uploads arrive.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Upgrade Before You Run Out', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: true
            };
        }

        if (data.isPremium) {
            return {
                context: __('Growth is built for continuous coverage, not one-off manual cleanups.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Open Automation Settings', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth automation is ready for future uploads and bulk clean-ups.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: false
            };
        }

        return {
            context: __('Upgrade for automatic coverage, faster bulk optimization, and less manual work each month.', 'beepbeep-ai-alt-text-generator'),
            ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
            growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
            growthUsageLine: growthComparison.line,
            growthUsagePercent: growthComparison.percent,
            growthIndicatorPercent: growthComparison.indicatorPercent,
            usageLine: sprintf(
                __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                formatCount(data.creditsUsed),
                formatCount(data.creditsTotal)
            ),
            remainingLine: getRemainingPlanLine(data),
            resetLine: data.creditsResetLine || '',
            usagePercent: usagePercent,
            urgent: false
        };
    }

    function getAutomationCardCopy(data) {
        var firstRun;
        var hasExistingIssues;

        if (!data) {
            return null;
        }

        firstRun = !hasScanResults(data) || data.total <= 0;
        hasExistingIssues = data.missing > 0 || data.weak > 0;

        if (data.isPremium) {
            return {
                lead: firstRun
                    ? __('After your first scan, keep future uploads covered automatically from Settings.', 'beepbeep-ai-alt-text-generator')
                    : (
                        hasExistingIssues
                            ? __('Finish cleaning up your current library, then use automation to keep new uploads from adding more manual work.', 'beepbeep-ai-alt-text-generator')
                            : __('You’ve optimized your current images. Now keep future uploads covered automatically.', 'beepbeep-ai-alt-text-generator')
                    ),
                statusLabel: __('Growth ready', 'beepbeep-ai-alt-text-generator'),
                statusHelper: __('Manage this in Settings when you want on-upload ALT generation.', 'beepbeep-ai-alt-text-generator'),
                valueSupport: __('Manage how future uploads are handled in Settings whenever your workflow changes.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Manage Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
                secondaryLabel: __('Review ALT Library', 'beepbeep-ai-alt-text-generator'),
                modifier: 'ready'
            };
        }

        return {
            lead: firstRun
                ? __('After your first scan, this can keep new uploads optimized automatically.', 'beepbeep-ai-alt-text-generator')
                : (
                    hasExistingIssues
                        ? __('Once your existing library is covered, this keeps new uploads optimized automatically.', 'beepbeep-ai-alt-text-generator')
                        : __('You’ve optimized your current images. Now automate future uploads.', 'beepbeep-ai-alt-text-generator')
                ),
            statusLabel: __('Available on Growth', 'beepbeep-ai-alt-text-generator'),
            statusHelper: __('Turn on with the Growth plan.', 'beepbeep-ai-alt-text-generator'),
            valueSupport: __('Upgrade once and let Growth keep future uploads covered automatically.', 'beepbeep-ai-alt-text-generator'),
            ctaLabel: __('Enable Auto-Optimisation', 'beepbeep-ai-alt-text-generator'),
            secondaryLabel: __('Compare plans', 'beepbeep-ai-alt-text-generator'),
            modifier: 'locked'
        };
    }

    function renderPerformanceMetrics(data) {
        if (!data) {
            return;
        }

        var minutesNode = document.querySelector('[data-bbai-performance-minutes]');
        var optimizedNode = document.querySelector('[data-bbai-performance-optimized]');
        var coverageNode = document.querySelector('[data-bbai-performance-coverage]');
        var hoursSaved = Math.max(0, (data.creditsUsed * 2.5) / 60);
        var minutesSaved = Math.round(hoursSaved * 60);
        var coveragePercent = data.total > 0 ? Math.round((data.optimized / data.total) * 100) : 0;

        if (minutesNode) {
            minutesNode.textContent = formatCount(minutesSaved);
        }
        if (optimizedNode) {
            optimizedNode.textContent = formatCount(data.optimized);
        }
        if (coverageNode) {
            coverageNode.textContent = formatCount(coveragePercent);
        }
    }

    function renderAccessibilityImpact(data) {
        var cardNode = document.querySelector('[data-bbai-accessibility-card="1"]');
        var coverageNode;
        var optimizedNode;
        var statsLineNode;
        var previewImageNode;
        var shareNode;
        var impact;

        if (!cardNode || !data) {
            return;
        }

        coverageNode = cardNode.querySelector('[data-bbai-accessibility-coverage]');
        optimizedNode = cardNode.querySelector('[data-bbai-accessibility-optimized]');
        statsLineNode = cardNode.querySelector('[data-bbai-accessibility-stats-line]');
        previewImageNode = cardNode.querySelector('[data-bbai-accessibility-badge-preview]');
        shareNode = cardNode.querySelector('[data-bbai-accessibility-action="share"]');
        impact = getAccessibilityImpact(data);

        if (coverageNode) {
            coverageNode.textContent = impact.coverageLabel;
        }

        if (optimizedNode) {
            optimizedNode.textContent = impact.optimizedLabel;
        }

        if (statsLineNode) {
            statsLineNode.textContent = sprintf(
                __('Accessibility score: %1$s%% | Images optimized: %2$s', 'beepbeep-ai-alt-text-generator'),
                impact.coverageLabel,
                impact.optimizedLabel
            );
        }

        if (previewImageNode) {
            previewImageNode.setAttribute('src', getAccessibilityBadgeDataUrl(impact));
            previewImageNode.setAttribute('alt', impact.altText);
        }

        if (shareNode) {
            shareNode.setAttribute(
                'href',
                'https://twitter.com/intent/tweet?text=' + encodeURIComponent(impact.shareText) + '&url=' + encodeURIComponent('https://beepbeep.ai')
            );
        }
    }

    function renderUpgradeContext(data) {
        // Must scope to dashboard root: #bbai-dashboard-main also carries data-bbai-plan-label on the container
        // for hydration. document.querySelector would match that node first; setting .textContent wipes the UI.
        var scope = getDashboardRoot() || document;
        var planLabelNode = scope.querySelector('[data-bbai-plan-label]');
        var usageLineNode = scope.querySelector('[data-bbai-plan-usage-line]');
        var remainingLineNode = scope.querySelector('[data-bbai-plan-usage-remaining]');
        var resetLineNode = scope.querySelector('[data-bbai-plan-usage-reset]');
        var usageProgressNode = scope.querySelector('[data-bbai-plan-usage-progress]');
        var growthLineNode = scope.querySelector('[data-bbai-plan-growth-line]');
        var growthProgressNode = scope.querySelector('[data-bbai-plan-growth-progress]');
        var growthPercentNode = scope.querySelector('[data-bbai-plan-growth-percent-label]');
        var upgradeNoteNode = scope.querySelector('[data-bbai-plan-upgrade-note]');
        var upgradeLeadNode = upgradeNoteNode ? upgradeNoteNode.querySelector('[data-bbai-plan-upgrade-lead]') : null;
        var upgradeSubNode = upgradeNoteNode ? upgradeNoteNode.querySelector('[data-bbai-plan-upgrade-sub]') : null;
        var upgradeCtaSubNode = scope.querySelector('[data-bbai-plan-upgrade-cta-sub]');
        var lowCreditsBadgeNode = scope.querySelector('[data-bbai-plan-low-credits-badge]');
        var primaryActionNode = scope.querySelector('[data-bbai-plan-action-primary]');
        var secondaryActionNode = scope.querySelector('[data-bbai-plan-action-secondary]');
        var growthComparison = getGrowthPlanComparison(data);
        var planRemaining = Math.max(0, parseCount(data && data.creditsRemaining));
        var isAnonymousTrial = isAnonymousTrialState(data);
        var lowCreditThreshold = getLowCreditThresholdForState(data);
        var freePlanOffer = getAnonymousTrialOffer(data);
        var growthComparisonBlock = growthLineNode && growthLineNode.closest
            ? growthLineNode.closest('.bbai-command-plan__comparison-block')
            : null;

        if (!data) {
            return;
        }

        if (planLabelNode) {
            planLabelNode.textContent = getPlanLabel(data);
        }

        if (usageLineNode) {
            usageLineNode.textContent = isAnonymousTrial
                ? sprintf(
                    __('%1$s / %2$s free trial generations used', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                )
                : sprintf(
                    __('%1$s / %2$s used', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                );
        }

        if (remainingLineNode) {
            remainingLineNode.textContent = getRemainingPlanLine(data);
        }

        if (resetLineNode) {
            resetLineNode.textContent = getCompactPlanResetLine(data);
        }

        if (usageProgressNode) {
            var usagePercent = Math.min(100, Math.max(0, Math.round((data.creditsUsed / Math.max(1, data.creditsTotal)) * 100)));
            usageProgressNode.setAttribute('data-bbai-plan-usage-progress-target', String(usagePercent));
            animateLinearProgress(usageProgressNode, usagePercent, 600, 80);
        }

        if (growthLineNode) {
            growthLineNode.textContent = growthComparison.line || '';
        }

        if (growthComparisonBlock) {
            growthComparisonBlock.hidden = !!isAnonymousTrial;
        }

        if (growthProgressNode) {
            growthProgressNode.setAttribute('data-bbai-plan-growth-progress-target', String(growthComparison.percent || 0));
            animateLinearProgress(growthProgressNode, growthComparison.percent || 0, 600, 120);
        }

        if (growthPercentNode) {
            growthPercentNode.textContent = (growthComparison.display || '0') + '%';
        }

        var missingCount = Math.max(0, parseCount(data && data.missing));

        if (upgradeNoteNode) {
            upgradeNoteNode.hidden = isAnonymousTrial ? false : (data.isPremium && missingCount <= 0);
        }

        var hasEnoughCredits = missingCount > 0 && planRemaining >= missingCount;

        if (isAnonymousTrial) {
            if (upgradeLeadNode) {
                upgradeLeadNode.textContent = planRemaining <= 0
                    ? __('Free trial complete', 'beepbeep-ai-alt-text-generator')
                    : (String(data.quotaState || '').toLowerCase() === 'near_limit'
                        ? __('You’re close to the end of your free trial', 'beepbeep-ai-alt-text-generator')
                        : __('Try BeepBeep AI before creating an account', 'beepbeep-ai-alt-text-generator'));
            }
            if (upgradeSubNode) {
                upgradeSubNode.textContent = planRemaining <= 0
                    ? sprintf(
                        __('Create a free account to unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    )
                    : sprintf(
                        __('Create a free account to unlock %d generations per month whenever you’re ready.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    );
            }
        } else if (data.isPremium && missingCount > 0) {
            if (upgradeLeadNode) {
                upgradeLeadNode.textContent = missingCount === 1
                    ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
                    : sprintf(
                        __('%s images from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                        formatCount(missingCount)
                    );
            }
            if (upgradeSubNode) {
                upgradeSubNode.textContent = hasEnoughCredits
                    ? __('You have enough credits to fix this now.', 'beepbeep-ai-alt-text-generator')
                    : __('Fix the remaining to maximise your traffic potential.', 'beepbeep-ai-alt-text-generator');
            }
        } else if (!data.isPremium && hasEnoughCredits) {
            if (upgradeLeadNode) {
                upgradeLeadNode.textContent = __('You have enough credits to fix this now.', 'beepbeep-ai-alt-text-generator');
            }
            if (upgradeSubNode) {
                upgradeSubNode.textContent = __('Or upgrade for unlimited optimisation.', 'beepbeep-ai-alt-text-generator');
            }
        } else {
            if (upgradeLeadNode) {
                upgradeLeadNode.textContent = __('Unlock full site optimisation', 'beepbeep-ai-alt-text-generator');
            }
            if (upgradeSubNode) {
                upgradeSubNode.textContent = __('New uploads will stop being optimised automatically on the free plan', 'beepbeep-ai-alt-text-generator');
            }
        }

        if (upgradeCtaSubNode) {
            upgradeCtaSubNode.hidden = isAnonymousTrial || !!data.isPremium;
            if (!isAnonymousTrial && !data.isPremium) {
                upgradeCtaSubNode.textContent = __('Automatically optimise every new image', 'beepbeep-ai-alt-text-generator');
            }
        }

        if (lowCreditsBadgeNode) {
            lowCreditsBadgeNode.hidden = planRemaining > lowCreditThreshold || planRemaining <= 0;
            lowCreditsBadgeNode.textContent = isAnonymousTrial
                ? __('Near limit', 'beepbeep-ai-alt-text-generator')
                : __('Low credits', 'beepbeep-ai-alt-text-generator');
        }

        if (primaryActionNode) {
            if (isAnonymousTrial) {
                primaryActionNode.hidden = false;
                primaryActionNode.className = 'bbai-command-action bbai-command-action--primary';
                primaryActionNode.textContent = planRemaining <= lowCreditThreshold
                    ? __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator')
                    : __('Continue in ALT Library', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(primaryActionNode, planRemaining <= lowCreditThreshold
                    ? {
                        label: __('Fix remaining images for free', 'beepbeep-ai-alt-text-generator'),
                        action: 'show-dashboard-auth',
                        href: '#',
                        attributes: { 'data-auth-tab': 'register' },
                        removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    }
                    : {
                        label: __('Continue in ALT Library', 'beepbeep-ai-alt-text-generator'),
                        href: data.libraryUrl || '#',
                        removeAttributes: ['data-action', 'data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    });
            } else if (data.isPremium) {
                primaryActionNode.hidden = false;
                primaryActionNode.className = 'bbai-command-action bbai-command-action--secondary';
                primaryActionNode.textContent = __('Review ALT text', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(primaryActionNode, {
                    label: __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    href: data.needsReviewLibraryUrl || data.libraryUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            } else {
                primaryActionNode.hidden = false;
                primaryActionNode.className = 'bbai-command-action bbai-command-action--primary';
                primaryActionNode.textContent = __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(primaryActionNode, {
                    label: __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator'),
                    action: 'show-upgrade-modal',
                    href: '#',
                    removeAttributes: ['data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            }
        }

        if (secondaryActionNode) {
            if (isAnonymousTrial) {
                secondaryActionNode.hidden = false;
                secondaryActionNode.className = 'bbai-command-action bbai-command-action--secondary';
                secondaryActionNode.textContent = planRemaining <= lowCreditThreshold
                    ? __('Open ALT Library', 'beepbeep-ai-alt-text-generator')
                    : __('Continue fixing images', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(secondaryActionNode, planRemaining <= lowCreditThreshold
                    ? {
                        label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                        href: data.libraryUrl || '#',
                        removeAttributes: ['data-action', 'data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    }
                    : {
                        label: __('Continue fixing images', 'beepbeep-ai-alt-text-generator'),
                        action: 'show-dashboard-auth',
                        href: '#',
                        attributes: { 'data-auth-tab': 'register' },
                        removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    });
            } else if (data.isPremium) {
                secondaryActionNode.hidden = true;
            } else {
                secondaryActionNode.hidden = false;
                secondaryActionNode.className = 'bbai-command-action bbai-command-action--secondary';
                secondaryActionNode.textContent = __('Review ALT text', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(secondaryActionNode, {
                    label: __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    href: data.needsReviewLibraryUrl || data.libraryUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-auth-tab', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            }
        }
    }

    var LAST_SUCCESS_TIMESTAMP_KEY = 'last_success_timestamp';
    var REVIEW_PROMPT_SHOWN_KEY = 'review_prompt_shown';
    var REVIEW_SNOOZE_UNTIL_KEY = 'bbai_review_prompt_snooze_until';
    var REVIEW_COMPLETED_KEY = 'bbai_review_completed';
    var USER_ACTION_COUNT_KEY = 'bbai_dashboard_action_count';
    var SUCCESS_CELEBRATION_SESSION_KEY = 'bbai_success_celebration_played';
    var REVIEW_DELAY_MS = 3000;
    var REVIEW_SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;
    var ACTION_TRACKING_BOUND = false;

    function getStoredNumber(key) {
        var value = 0;
        try {
            value = parseInt(localStorage.getItem(key), 10);
        } catch (e) {
            return 0;
        }
        return isNaN(value) ? 0 : value;
    }

    function setStoredValue(key, value) {
        try {
            localStorage.setItem(key, String(value));
        } catch (e) {
            // Ignore storage failures in private mode / blocked storage.
        }
    }

    function getStoredBoolean(key) {
        try {
            return localStorage.getItem(key) === 'true';
        } catch (e) {
            return false;
        }
    }

    function emitDashboardAnalyticsEvent(eventName, payload) {
        var detail = $.extend({ event: eventName }, payload || {});
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', { detail: detail }));
        } catch (e) {
            // noop
        }
        if (window.dataLayer && typeof window.dataLayer.push === 'function') {
            window.dataLayer.push(detail);
        }
    }

    function isSuccessState(data) {
        var coverage = getStatusCoverageData(data);
        if (!data || coverage.total <= 0) {
            return false;
        }
        return coverage.coverage >= 100 || (coverage.missing === 0 && coverage.weak === 0);
    }

    function shouldShowDashboardReviewPrompt(data) {
        var now = Date.now();
        var successTimestamp = getStoredNumber(LAST_SUCCESS_TIMESTAMP_KEY);
        var snoozedUntil = getStoredNumber(REVIEW_SNOOZE_UNTIL_KEY);
        var actionCount = getStoredNumber(USER_ACTION_COUNT_KEY);
        var coverage = getStatusCoverageData(data);

        if (!data || !isSuccessState(data) || coverage.coverage < 100) {
            return false;
        }
        if (!successTimestamp) {
            return false;
        }
        if (getStoredBoolean(REVIEW_COMPLETED_KEY)) {
            return false;
        }
        if (snoozedUntil && now < snoozedUntil) {
            return false;
        }
        if (getStoredBoolean(REVIEW_PROMPT_SHOWN_KEY)) {
            return false;
        }
        if (actionCount < 1 && Math.max(0, parseCount(data.generated)) < 1) {
            return false;
        }

        return true;
    }

    function bindDashboardActionTracking() {
        if (ACTION_TRACKING_BOUND) {
            return;
        }
        ACTION_TRACKING_BOUND = true;

        document.addEventListener('click', function(event) {
            var actionNode = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-action], [data-action]')
                : null;
            var reviewNode = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-review-action]')
                : null;
            var bbaiAction = '';
            var actionName = '';
            var isTrackedAction = false;
            var isScanAction = false;
            var count = 0;

            if (!actionNode || reviewNode) {
                return;
            }

            bbaiAction = actionNode.getAttribute('data-bbai-action') || '';
            actionName = actionNode.getAttribute('data-action') || '';
            isTrackedAction = [
                'scan-opportunity',
                'generate_missing',
                'reoptimize_all'
            ].indexOf(bbaiAction) !== -1 || [
                'generate-missing',
                'regenerate-all',
                'regenerate-selected'
            ].indexOf(actionName) !== -1;

            if (!isTrackedAction) {
                return;
            }

            count = getStoredNumber(USER_ACTION_COUNT_KEY) + 1;
            setStoredValue(USER_ACTION_COUNT_KEY, count);
            isScanAction = bbaiAction === 'scan-opportunity';
            if (isScanAction) {
                emitDashboardAnalyticsEvent('scan_again_clicked', { action_count: count });
            }
        }, true);
    }

    function updateSuccessLoopState(hero, data, model) {
        var now = Date.now();
        var successNow = isSuccessState(data);
        var coverage = getStatusCoverageData(data);
        var previousCoverage = parseInt(hero.getAttribute('data-bbai-prev-success-coverage'), 10);
        var previousMissing = parseInt(hero.getAttribute('data-bbai-prev-missing-count'), 10);
        var justFixedFinalIssue = !isNaN(previousMissing) && previousMissing > 0 && coverage.missing === 0 && coverage.weak === 0;
        var newlyReached100 = !isNaN(previousCoverage) && previousCoverage < 100 && coverage.coverage >= 100;
        var becameSuccessful = hero.getAttribute('data-bbai-success-state') !== '1' && successNow;
        var hasExistingSuccessStamp = getStoredNumber(LAST_SUCCESS_TIMESTAMP_KEY) > 0;
        var shouldMarkNewSuccess = newlyReached100 || justFixedFinalIssue || (becameSuccessful && !hasExistingSuccessStamp);

        hero.setAttribute('data-bbai-prev-success-coverage', String(coverage.coverage));
        hero.setAttribute('data-bbai-prev-missing-count', String(coverage.missing));

        if (!successNow) {
            hero.setAttribute('data-bbai-success-state', '0');
            hero.classList.remove('bbai-dashboard-hero--celebrate', 'bbai-banner--celebrate');
            return;
        }

        hero.setAttribute('data-bbai-success-state', '1');

        if (shouldMarkNewSuccess) {
            setStoredValue(LAST_SUCCESS_TIMESTAMP_KEY, now);
            setStoredValue(REVIEW_PROMPT_SHOWN_KEY, 'false');
            emitDashboardAnalyticsEvent('success_reached_100', {
                coverage: coverage.coverage,
                missing: coverage.missing,
                weak: coverage.weak
            });
        }

        try {
            if (!sessionStorage.getItem(SUCCESS_CELEBRATION_SESSION_KEY) && shouldMarkNewSuccess) {
                sessionStorage.setItem(SUCCESS_CELEBRATION_SESSION_KEY, '1');
                hero.classList.remove('bbai-dashboard-hero--celebrate', 'bbai-banner--celebrate');
                window.setTimeout(function() {
                    hero.classList.add('bbai-dashboard-hero--celebrate', 'bbai-banner--celebrate');

                    var iconNode = hero.querySelector('[data-bbai-hero-icon]');
                    if (iconNode) {
                        iconNode.classList.add('bbai-dashboard-hero__icon--draw');
                    }

                    window.setTimeout(function() {
                        hero.classList.remove('bbai-dashboard-hero--celebrate', 'bbai-banner--celebrate');
                    }, 620);
                }, 20);
            }
        } catch (e) {
            // noop
        }

        if (model && model.loopActions && model.loopActions.length) {
            hero.setAttribute('data-bbai-loop-active', '1');
        } else {
            hero.setAttribute('data-bbai-loop-active', '0');
        }
    }

    function dismissReviewModal(promptNode, reason) {
        if (promptNode.hidden) {
            return;
        }
        promptNode.classList.add('bbai-dashboard-review-overlay--exit');
        window.setTimeout(function() {
            promptNode.hidden = true;
            promptNode.classList.remove('bbai-dashboard-review-overlay--exit', 'bbai-dashboard-review-overlay--enter');
            promptNode.removeAttribute('data-bbai-review-revealed');
            document.body.style.overflow = '';
        }, 260);
        if (reason === 'later') {
            setStoredValue(REVIEW_SNOOZE_UNTIL_KEY, Date.now() + REVIEW_SNOOZE_MS);
            setStoredValue(REVIEW_PROMPT_SHOWN_KEY, 'false');
            emitDashboardAnalyticsEvent('review_dismissed', { source: 'dashboard_success_loop' });
        }
    }

    function bindReviewPromptActions(promptNode) {
        if (promptNode.getAttribute('data-bbai-review-bound') === '1') {
            return;
        }
        promptNode.setAttribute('data-bbai-review-bound', '1');

        promptNode.addEventListener('click', function(e) {
            var actionEl = e.target.closest('[data-bbai-review-action]');
            if (actionEl) {
                var action = actionEl.getAttribute('data-bbai-review-action');
                if (action === 'leave') {
                    setStoredValue(REVIEW_COMPLETED_KEY, 'true');
                    setStoredValue(REVIEW_PROMPT_SHOWN_KEY, 'true');
                    emitDashboardAnalyticsEvent('review_clicked', { source: 'dashboard_success_loop' });
                    dismissReviewModal(promptNode, 'leave');
                    return;
                }
                if (action === 'later') {
                    dismissReviewModal(promptNode, 'later');
                    return;
                }
            }

            if (e.target.closest('[data-bbai-review-backdrop]')) {
                dismissReviewModal(promptNode, 'later');
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key !== 'Escape' || promptNode.hidden) {
                return;
            }
            dismissReviewModal(promptNode, 'later');
        });
    }

    function renderDashboardReviewPrompt(data) {
        var promptNode = document.querySelector('[data-bbai-dashboard-review-prompt]');

        if (!promptNode) {
            return;
        }

        bindReviewPromptActions(promptNode);

        if (!shouldShowDashboardReviewPrompt(data)) {
            promptNode.hidden = true;
            promptNode.removeAttribute('data-bbai-review-revealed');
            return;
        }

        if (promptNode.getAttribute('data-bbai-review-revealed') === '1') {
            return;
        }

        promptNode.setAttribute('data-bbai-review-revealed', '1');
        window.setTimeout(function() {
            if (!shouldShowDashboardReviewPrompt(data)) {
                promptNode.removeAttribute('data-bbai-review-revealed');
                return;
            }
            promptNode.hidden = false;
            document.body.style.overflow = 'hidden';
            promptNode.classList.remove('bbai-dashboard-review-overlay--exit');
            promptNode.classList.add('bbai-dashboard-review-overlay--enter');
            setStoredValue(REVIEW_PROMPT_SHOWN_KEY, 'true');
            emitDashboardAnalyticsEvent('review_prompt_shown', { source: 'dashboard_success_loop' });

            var focusTarget = promptNode.querySelector('[data-bbai-review-action="leave"]');
            if (focusTarget) {
                window.setTimeout(function() { focusTarget.focus(); }, 100);
            }
        }, REVIEW_DELAY_MS);
    }

    /**
     * State machine: single source of truth for the dashboard UI mode.
     * Every render path must branch on this value — no generic fallback.
     */
    function getDashboardState(data) {
        if (!data) {
            return 'NOT_SCANNED';
        }

        var root = data.root;
        var runtimeState = root ? (root.getAttribute('data-bbai-dashboard-runtime-state') || 'idle') : 'idle';

        if (runtimeState === 'generation_running') {
            return 'FIXING';
        }

        var missing = Math.max(0, parseCount(data.missing));
        var weak = Math.max(0, parseCount(data.weak));
        var total = Math.max(0, parseCount(data.total));
        var creditsRemaining = Math.max(0, parseCount(data.creditsRemaining));

        if (!hasScanResults(data)) {
            return 'NOT_SCANNED';
        }

        if (isCoverageProcessingActive(data, getStatusCoverageData(data))) {
            return 'SCANNING';
        }

        if (creditsRemaining === 0) {
            return 'LIMIT_REACHED';
        }

        if (missing > 0 || weak > 0) {
            return 'SCANNED_HAS_ISSUES';
        }

        if (total > 0) {
            return 'COMPLETE';
        }

        return 'NOT_SCANNED';
    }

    /**
     * Apply state-driven visibility to dashboard sections.
     * The hero + action CTA always lead; status/usage cards are secondary.
     */
    function applyDashboardStateVisibility(state, data) {
        var root = getDashboardRoot();
        if (!root) {
            return;
        }

        root.setAttribute('data-bbai-ui-state', state);

        var primaryCard = root.querySelector('[data-bbai-dashboard-primary-action-card]');
        var statusCard = root.querySelector('[data-bbai-dashboard-status-card="1"]');
        var planCard = root.querySelector('.bbai-command-card--plan');

        // Primary action card: show only when there are actionable issues
        if (primaryCard) {
            primaryCard.hidden = state !== 'SCANNED_HAS_ISSUES';
        }

        // Plan card upgrade note: only prominent in LIMIT_REACHED
        if (planCard) {
            var upgradeNote = planCard.querySelector('[data-bbai-plan-upgrade-note]');
            var ctaSub = planCard.querySelector('[data-bbai-plan-upgrade-cta-sub]');
            if (state === 'LIMIT_REACHED') {
                planCard.classList.add('bbai-command-card--limit-active');
                if (upgradeNote) { upgradeNote.hidden = false; }
            } else {
                planCard.classList.remove('bbai-command-card--limit-active');
            }
        }
    }

    function renderDashboardState() {
        var data = getDashboardData();
        if (!data) {
            return;
        }

        var state = getDashboardState(data);

        function runStep(stepName, callback) {
            try {
                callback();
            } catch (error) {
                if (window.BBAI_LOG && typeof window.BBAI_LOG.warn === 'function') {
                    window.BBAI_LOG.warn('[AltText AI] Dashboard render step failed: ' + stepName, error);
                }
            }
        }

        runStep('state-visibility', function() {
            applyDashboardStateVisibility(state, data);
        });
        runStep('action-tracking', bindDashboardActionTracking);
        runStep('hero', function() {
            renderHero(data);
        });
        runStep('status-card', function() {
            renderStatusCard(data);
        });
        runStep('status-card-bindings', bindStatusCardInteractions);
        runStep('upgrade-context', function() {
            renderUpgradeContext(data);
        });
        runStep('review-prompt', function() {
            renderDashboardReviewPrompt(data);
        });
    }

    window.bbaiGetDashboardData = getDashboardData;
    window.bbaiGetDashboardState = function() {
        return getDashboardState(getDashboardData());
    };
    window.bbaiRenderDashboardHero = function(dataOverride) {
        renderHero(dataOverride || getDashboardData());
    };
    window.bbaiSyncDashboardState = function(statsOverride, usageOverride) {
        syncAndRender(statsOverride || null, usageOverride || null);
    };

    function setTemporaryButtonLabel(button, label) {
        var resetTimer;

        if (!button) {
            return;
        }

        if (!button.getAttribute('data-bbai-default-label')) {
            button.setAttribute('data-bbai-default-label', button.textContent || '');
        }

        button.textContent = label;
        resetTimer = parseInt(button.getAttribute('data-bbai-reset-timer'), 10);
        if (!isNaN(resetTimer) && resetTimer) {
            window.clearTimeout(resetTimer);
        }

        resetTimer = window.setTimeout(function() {
            button.textContent = button.getAttribute('data-bbai-default-label') || button.textContent;
            button.removeAttribute('data-bbai-reset-timer');
        }, 1800);

        button.setAttribute('data-bbai-reset-timer', String(resetTimer));
    }

    function copyTextToClipboard(text, onSuccess, onError) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(function() {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
            }).catch(function(error) {
                if (typeof onError === 'function') {
                    onError(error);
                }
            });
            return;
        }

        try {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            if (document.execCommand('copy')) {
                document.body.removeChild(textarea);
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            document.body.removeChild(textarea);
        } catch (error) {
            if (typeof onError === 'function') {
                onError(error);
            }
            return;
        }

        if (typeof onError === 'function') {
            onError(new Error('copy_failed'));
        }
    }

    function showDashboardFeedback(detail) {
        var feedbackNode = document.querySelector('[data-bbai-dashboard-feedback]');
        var titleNode;
        var detailNode;
        if (!feedbackNode || !detail || !detail.message) {
            return;
        }

        feedbackNode.hidden = false;
        feedbackNode.className = 'bbai-dashboard-feedback bbai-dashboard-feedback--' + (detail.type || 'info');
        feedbackNode.textContent = '';

        titleNode = document.createElement('div');
        titleNode.className = 'bbai-dashboard-feedback__title';
        titleNode.textContent = detail.message;
        feedbackNode.appendChild(titleNode);

        if (detail.detail) {
            detailNode = document.createElement('div');
            detailNode.className = 'bbai-dashboard-feedback__detail';
            detailNode.textContent = detail.detail;
            feedbackNode.appendChild(detailNode);
        }

        if (feedbackTimeout) {
            window.clearTimeout(feedbackTimeout);
        }

        feedbackTimeout = window.setTimeout(function() {
            feedbackNode.hidden = true;
            feedbackNode.textContent = '';
            feedbackNode.className = 'bbai-dashboard-feedback';
        }, Math.max(2500, parseInt(detail && detail.duration, 10) || 6000));
    }

    function syncAndRender(stats, usage) {
        if (stats) {
            syncStatsToRoot(stats);
        }
        if (usage) {
            syncUsageToRoot(usage);
        }
        renderDashboardState();
    }

    function DashboardLoadingOverlay() {
        this.hideTimerId = 0;
        this.node = document.createElement('div');
        this.node.className = 'bbai-dashboard-loading-overlay';
        this.node.setAttribute('data-bbai-dashboard-loading-overlay', '1');
        this.node.setAttribute('aria-hidden', 'true');
        this.node.innerHTML =
            '<div class="bbai-dashboard-loading-overlay__panel" role="status" aria-live="polite">' +
                '<div class="bbai-dashboard-loading-overlay__dots" aria-hidden="true">' +
                    '<span></span><span></span><span></span>' +
                '</div>' +
                '<p class="bbai-dashboard-loading-overlay__title">' + escapeDashboardHtml(__('Loading your dashboard…', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                '<p class="bbai-dashboard-loading-overlay__subtext">' + escapeDashboardHtml(__('Fetching your images and usage', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '</div>';
    }

    DashboardLoadingOverlay.prototype.mount = function() {
        if (!document.body || this.node.parentNode) {
            return;
        }

        document.body.appendChild(this.node);
    };

    DashboardLoadingOverlay.prototype.show = function() {
        this.mount();
        this.node.setAttribute('aria-hidden', 'false');
        this.node.classList.remove('is-exiting');
        this.node.classList.add('is-visible');
    };

    DashboardLoadingOverlay.prototype.hide = function(callback) {
        var overlay = this;

        if (overlay.hideTimerId) {
            window.clearTimeout(overlay.hideTimerId);
            overlay.hideTimerId = 0;
        }

        if (!overlay.node.parentNode) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        if (!overlay.node.classList.contains('is-visible') && !overlay.node.classList.contains('is-exiting')) {
            overlay.node.setAttribute('aria-hidden', 'true');
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        overlay.node.classList.remove('is-visible');
        overlay.node.classList.add('is-exiting');
        overlay.hideTimerId = window.setTimeout(function() {
            overlay.node.classList.remove('is-exiting');
            overlay.node.setAttribute('aria-hidden', 'true');
            overlay.hideTimerId = 0;
            if (typeof callback === 'function') {
                callback();
            }
        }, 150);
    };

    DashboardLoadingOverlay.prototype.destroy = function() {
        if (this.hideTimerId) {
            window.clearTimeout(this.hideTimerId);
            this.hideTimerId = 0;
        }

        if (this.node.parentNode) {
            this.node.parentNode.removeChild(this.node);
        }
    };

    window.DashboardLoadingOverlay = window.DashboardLoadingOverlay || DashboardLoadingOverlay;

    function getDashboardLoadingSurfaceMarkup(type) {
        if (type === 'hero') {
            return '' +
                '<div class="bbai-dashboard-loading-skeleton__stack">' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--eyebrow"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--title"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--title-short"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--body"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--body-short"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__button"></span>' +
                '</div>';
        }

        if (type === 'status') {
            return '' +
                '<div class="bbai-dashboard-loading-skeleton__status-layout">' +
                    '<div class="bbai-dashboard-loading-skeleton__donut-wrap">' +
                        '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__donut"></span>' +
                    '</div>' +
                    '<div class="bbai-dashboard-loading-skeleton__status-copy">' +
                        '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--eyebrow"></span>' +
                        '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--metric"></span>' +
                        '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--body"></span>' +
                        '<div class="bbai-dashboard-loading-skeleton__list">' +
                            '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__list-item"></span>' +
                            '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__list-item"></span>' +
                            '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__list-item"></span>' +
                        '</div>' +
                    '</div>' +
                '</div>';
        }

        return '' +
            '<div class="bbai-dashboard-loading-skeleton__stack bbai-dashboard-loading-skeleton__stack--usage">' +
                '<div class="bbai-dashboard-loading-skeleton__usage-row">' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--label"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--value"></span>' +
                '</div>' +
                '<div class="bbai-dashboard-loading-skeleton__usage-row">' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--label"></span>' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--value-wide"></span>' +
                '</div>' +
                '<div class="bbai-dashboard-loading-skeleton__progress">' +
                    '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__progress-fill"></span>' +
                '</div>' +
                '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__line bbai-dashboard-loading-skeleton__line--body"></span>' +
                '<span class="bbai-skeleton bbai-dashboard-loading-skeleton__button bbai-dashboard-loading-skeleton__button--wide"></span>' +
            '</div>';
    }

    function ensureDashboardLoadingSurface(node, type) {
        var skeletonNode;

        if (!node) {
            return null;
        }

        node.classList.add('bbai-dashboard-loading-surface');
        node.setAttribute('data-bbai-dashboard-loading-surface', type);

        skeletonNode = node.querySelector('.bbai-dashboard-loading-skeleton');
        if (!skeletonNode) {
            skeletonNode = document.createElement('div');
            skeletonNode.className = 'bbai-dashboard-loading-skeleton bbai-dashboard-loading-skeleton--' + type;
            skeletonNode.setAttribute('aria-hidden', 'true');
            skeletonNode.innerHTML = getDashboardLoadingSurfaceMarkup(type);
            node.appendChild(skeletonNode);
        }

        return skeletonNode;
    }

    function getDashboardLoadingSurfaces(root) {
        var surfaces = [];
        var heroNode = document.querySelector('[data-bbai-dashboard-hero="1"]');
        var statusNode = root ? root.querySelector('[data-bbai-dashboard-status-card="1"]') : null;
        var usageNode = root ? root.querySelector('.bbai-command-card--plan') : null;

        if (heroNode) {
            surfaces.push({ node: heroNode, type: 'hero' });
        }
        if (statusNode) {
            surfaces.push({ node: statusNode, type: 'status' });
        }
        if (usageNode) {
            surfaces.push({ node: usageNode, type: 'usage' });
        }

        return surfaces;
    }

    function setDashboardLoadingSurfacesState(controller, phase) {
        if (!controller || !Array.isArray(controller.surfaces)) {
            return;
        }

        controller.surfaces.forEach(function(surface) {
            if (!surface || !surface.node) {
                return;
            }

            ensureDashboardLoadingSurface(surface.node, surface.type);
            surface.node.classList.remove('is-loading', 'is-loaded');

            if (phase === 'loading' || phase === 'loaded') {
                surface.node.classList.add('is-' + phase);
            }
        });
    }

    function normalizeDashboardLoadingStatsPayload(rawStats) {
        var payload = rawStats && rawStats.data && typeof rawStats.data === 'object'
            ? rawStats.data
            : (rawStats && typeof rawStats === 'object' ? rawStats : {});
        var normalized = $.extend({}, payload);
        var total = payload.total_images !== undefined ? parseCount(payload.total_images) : parseCount(payload.total);
        var withAlt = payload.images_with_alt !== undefined ? parseCount(payload.images_with_alt) : parseCount(payload.with_alt);
        var missing = payload.images_missing_alt !== undefined ? parseCount(payload.images_missing_alt) : parseCount(payload.missing);
        var weak = payload.needs_review_count !== undefined ? parseCount(payload.needs_review_count) : parseCount(payload.weak);
        var optimized = payload.optimized_count !== undefined
            ? parseCount(payload.optimized_count)
            : Math.max(0, withAlt - weak);

        normalized.total_images = total;
        normalized.total = total;
        normalized.images_with_alt = withAlt;
        normalized.with_alt = withAlt;
        normalized.images_missing_alt = missing;
        normalized.missing = missing;
        normalized.needs_review_count = weak;
        normalized.weak = weak;
        normalized.optimized_count = optimized;
        normalized.coverage_percent = payload.coverage_percent !== undefined
            ? parseCount(payload.coverage_percent)
            : (total > 0 ? Math.round((optimized / total) * 100) : 0);

        return normalized;
    }

    function getDashboardLoadingRestConfig() {
        return window.BBAI_DASH || window.BBAI || {};
    }

    function fetchDashboardUsageSnapshot() {
        var deferred = $.Deferred();
        var config = getDashboardLoadingRestConfig();
        var usageUrl = config && config.restUsage ? String(config.restUsage) : '';
        var nonce = config && config.nonce ? String(config.nonce) : '';

        if (!usageUrl || !nonce) {
            deferred.reject(new Error('usage_endpoint_unavailable'));
            return deferred.promise();
        }

        $.ajax({
            url: usageUrl,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-WP-Nonce': nonce
            },
            timeout: 10000
        })
        .done(function(response) {
            deferred.resolve(response);
        })
        .fail(function(xhr, status, error) {
            deferred.reject(error || status || xhr);
        });

        return deferred.promise();
    }

    function fetchDashboardStatsSnapshot() {
        var deferred = $.Deferred();
        var config = getDashboardLoadingRestConfig();
        var statsUrl = config && config.restStats ? String(config.restStats) : '';
        var nonce = config && config.nonce ? String(config.nonce) : '';
        var requestUrl;

        if (!statsUrl || !nonce) {
            deferred.reject(new Error('stats_endpoint_unavailable'));
            return deferred.promise();
        }

        requestUrl = statsUrl + (statsUrl.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';

        $.ajax({
            url: requestUrl,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-WP-Nonce': nonce
            },
            timeout: 12000
        })
        .done(function(response) {
            deferred.resolve(normalizeDashboardLoadingStatsPayload(response));
        })
        .fail(function(xhr, status, error) {
            deferred.reject(error || status || xhr);
        });

        return deferred.promise();
    }

    function applyDashboardMountStats(statsPayload) {
        var normalizedStats = normalizeDashboardLoadingStatsPayload(statsPayload);

        if (window.BBAI_DASH) {
            window.BBAI_DASH.stats = $.extend({}, window.BBAI_DASH.stats || {}, normalizedStats);
        }
        if (window.BBAI) {
            window.BBAI.stats = $.extend({}, window.BBAI.stats || {}, normalizedStats);
        }

        $(document).trigger('bbai:stats-updated', [{ stats: normalizedStats }]);
    }

    function applyDashboardMountUsage(usagePayload) {
        if (typeof window.alttextai_refresh_usage === 'function') {
            window.alttextai_refresh_usage(usagePayload);
            return;
        }

        bbaiMirrorUsageObject(usagePayload);
        syncAndRender(null, usagePayload);
    }

    function maybeFinishDashboardMountLoading(controller) {
        var overlayVisibleFor = 0;
        var remainingOverlayDelay = 0;

        if (!controller || controller.completed || controller.pending.stats || controller.pending.usage) {
            return;
        }

        controller.completed = true;
        controller.root.removeAttribute('data-bbai-dashboard-loading');
        controller.root.removeAttribute('aria-busy');

        if (controller.overlayTimerId) {
            window.clearTimeout(controller.overlayTimerId);
            controller.overlayTimerId = 0;
        }

        setDashboardLoadingSurfacesState(controller, 'loaded');
        window.setTimeout(function() {
            if (!controller || !Array.isArray(controller.surfaces)) {
                return;
            }

            controller.surfaces.forEach(function(surface) {
                if (surface && surface.node) {
                    surface.node.classList.remove('is-loaded');
                }
            });
        }, 180);

        if (controller.overlayVisibleAt > 0) {
            overlayVisibleFor = Date.now() - controller.overlayVisibleAt;
            remainingOverlayDelay = Math.max(0, controller.minimumOverlayVisibleMs - overlayVisibleFor);
        }

        window.setTimeout(function() {
            controller.overlay.hide(function() {
                controller.overlay.destroy();
                if (dashboardMountLoadingController === controller) {
                    dashboardMountLoadingController = null;
                }
            });
        }, remainingOverlayDelay);
    }

    function startDashboardMountLoading() {
        var root = getDashboardRoot();
        var overlayDelayMs = 300;

        if (!root || dashboardMountLoadingController) {
            return;
        }

        dashboardMountLoadingController = {
            root: root,
            overlay: new DashboardLoadingOverlay(),
            overlayTimerId: 0,
            overlayVisibleAt: 0,
            minimumOverlayVisibleMs: 180,
            completed: false,
            pending: {
                stats: true,
                usage: true
            },
            surfaces: getDashboardLoadingSurfaces(root)
        };

        root.setAttribute('data-bbai-dashboard-loading', '1');
        root.setAttribute('aria-busy', 'true');
        setDashboardLoadingSurfacesState(dashboardMountLoadingController, 'loading');

        dashboardMountLoadingController.overlayTimerId = window.setTimeout(function() {
            if (!dashboardMountLoadingController || dashboardMountLoadingController.completed) {
                return;
            }

            dashboardMountLoadingController.overlayVisibleAt = Date.now();
            dashboardMountLoadingController.overlay.show();
        }, overlayDelayMs);

        fetchDashboardStatsSnapshot()
            .done(function(statsPayload) {
                applyDashboardMountStats(statsPayload);
            })
            .fail(function(error) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Dashboard mount stats refresh failed.', error);
            })
            .always(function() {
                if (!dashboardMountLoadingController) {
                    return;
                }

                dashboardMountLoadingController.pending.stats = false;
                maybeFinishDashboardMountLoading(dashboardMountLoadingController);
            });

        fetchDashboardUsageSnapshot()
            .done(function(usagePayload) {
                applyDashboardMountUsage(usagePayload);
            })
            .fail(function(error) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Dashboard mount usage refresh failed.', error);
            })
            .always(function() {
                if (!dashboardMountLoadingController) {
                    return;
                }

                dashboardMountLoadingController.pending.usage = false;
                maybeFinishDashboardMountLoading(dashboardMountLoadingController);
            });
    }

    $(document).on('click', '[data-bbai-quick-action].is-disabled, [data-bbai-workflow-generate-cta].is-disabled', function(event) {
        if (this.getAttribute('data-action') === 'show-upgrade-modal') {
            return;
        }
        event.preventDefault();
    });

    document.addEventListener('click', function(event) {
        var eventTarget = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        var actionButton = eventTarget && typeof eventTarget.closest === 'function'
            ? eventTarget.closest('[data-bbai-accessibility-action]')
            : null;
        var data;
        var cardNode;
        var previewNode;
        var impact;
        var svg;
        var blob;
        var url;
        var downloadLink;

        if (!actionButton) {
            return;
        }

        data = getDashboardData();
        if (!data) {
            return;
        }

        cardNode = actionButton.closest('[data-bbai-accessibility-card="1"]');
        if (!cardNode) {
            return;
        }

        previewNode = cardNode.querySelector('[data-bbai-accessibility-preview]');
        impact = getAccessibilityImpact(data);

        if (actionButton.getAttribute('data-bbai-accessibility-action') === 'preview') {
            event.preventDefault();
            if (previewNode) {
                previewNode.hidden = !previewNode.hidden;
                actionButton.setAttribute('aria-expanded', previewNode.hidden ? 'false' : 'true');
            }
            return;
        }

        if (
            actionButton.getAttribute('data-bbai-accessibility-action') === 'download' ||
            actionButton.getAttribute('data-bbai-accessibility-action') === 'get-badge'
        ) {
            event.preventDefault();
            svg = buildAccessibilityBadgeSvg(impact);

            if (typeof Blob === 'function' && window.URL && typeof window.URL.createObjectURL === 'function') {
                blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
                url = window.URL.createObjectURL(blob);
                downloadLink = document.createElement('a');
                downloadLink.href = url;
                downloadLink.download = 'beepbeep-ai-accessibility-impact.svg';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                window.setTimeout(function() {
                    window.URL.revokeObjectURL(url);
                }, 1000);
            } else {
                window.open(getAccessibilityBadgeDataUrl(impact), '_blank', 'noopener,noreferrer');
            }

            setTemporaryButtonLabel(actionButton, __('Downloaded', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        if (actionButton.getAttribute('data-bbai-accessibility-action') === 'copy-embed') {
            event.preventDefault();
            copyTextToClipboard(
                impact.embedHtml,
                function() {
                    setTemporaryButtonLabel(actionButton, __('Copied', 'beepbeep-ai-alt-text-generator'));
                },
                function() {
                    setTemporaryButtonLabel(actionButton, __('Copy failed', 'beepbeep-ai-alt-text-generator'));
                }
            );
        }
    });

    document.addEventListener('click', function(event) {
        var eventTarget;
        var reviewTrigger;
        var libraryLink;
        var data;
        var reviewUrl;

        if (event.defaultPrevented) {
            return;
        }

        eventTarget = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        if (!eventTarget || typeof eventTarget.closest !== 'function') {
            return;
        }

        reviewTrigger = eventTarget.closest('[data-action="review-dashboard-results"]');
        libraryLink = reviewTrigger ? null : eventTarget.closest('a[href*="page=bbai-library"]');
        if (!reviewTrigger && !libraryLink) {
            return;
        }

        data = getDashboardData();
        if (!data || dashboardCanAccessLibrary(data)) {
            if (reviewTrigger) {
                reviewUrl = resolveDashboardReviewUrl(reviewTrigger, data);
                if (reviewUrl) {
                    event.preventDefault();
                    window.location.assign(reviewUrl);
                }
            }
            return;
        }

        event.preventDefault();
        openDashboardLockedGate(reviewTrigger || libraryLink);
    });

    document.addEventListener('bbai:dashboard-feedback', function(event) {
        if (event && event.detail) {
            showDashboardFeedback(event.detail);
        }
    });

    if (typeof window.addEventListener === 'function') {
        window.addEventListener('bbai-stats-update', function(event) {
            var usage = event && event.detail ? event.detail.usage : null;
            var stats = event && event.detail ? event.detail.stats : null;
            syncAndRender(stats, usage);
        });

        window.addEventListener('storage', function(event) {
            if (!event || event.key !== BBAI_STATS_SYNC_KEY || !event.newValue) {
                return;
            }

            try {
                var payload = JSON.parse(event.newValue);
                syncAndRender(payload && payload.stats ? payload.stats : null, payload && payload.usage ? payload.usage : null);
            } catch (storageError) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Failed to parse cross-tab dashboard stats sync payload.', storageError);
            }
        });
    }

    $(document).on('bbai:stats-updated.bbaiOperational bbai:usage-updated.bbaiOperational', function(event, payload) {
        var stats = null;
        var usage = bbaiGetUsageObject();

        if (event && event.type === 'bbai:stats-updated') {
            if (payload && payload.stats) {
                stats = payload.stats;
            } else if (event.detail && event.detail.stats) {
                stats = event.detail.stats;
            }
        }

        syncAndRender(stats, usage);
    });

    $(document).ready(function() {
        ensureStatusCardRefreshClickDelegated();
        startDashboardMountLoading();
        syncAndRender(
            (window.BBAI_DASH && window.BBAI_DASH.stats) || (window.BBAI && window.BBAI.stats) || null,
            bbaiGetUsageObject()
        );
        setTimeout(function() {
            syncAndRender(
                (window.BBAI_DASH && window.BBAI_DASH.stats) || (window.BBAI && window.BBAI.stats) || null,
                bbaiGetUsageObject()
            );
        }, 400);
    });
});
