/**
 * AI Alt Text Admin JavaScript
 * Handles bulk generate, regenerate all, and individual regenerate buttons
 */

(function($) {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
    const _n = i18n && typeof i18n._n === 'function' ? i18n._n : (single, plural, number) => (number === 1 ? single : plural);
    const sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : (format) => format;

    // Ensure BBAI_DASH exists (from dashboard) or use BBAI
    var config = window.BBAI_DASH || window.BBAI || {};

    // Check if we have the necessary configuration for bulk operations
    var hasBulkConfig = config.rest && config.nonce;
    var bbaiAdminInitialized = false;
    var bbaiDelegatedHandlersBound = false;
    var bbaiOutOfCreditsGuardBound = false;
    var bbaiLockObserver = null;
    var bbaiModalSafetyIntervalId = null;
    var bbaiLimitStateViewedTracked = false;
    var bbaiLimitPreviewCache = null;
    var bbaiLimitPreviewRequest = null;
    var bbaiLibraryPreviewModal = null;
    var bbaiLibraryPreviewModalState = {
        lastTrigger: null
    };
    var bbaiLibraryEditModal = null;
    var bbaiLibraryEditModalState = {
        lastTrigger: null,
        row: null,
        attachmentId: 0,
        originalSuggestion: '',
        currentSuggestion: '',
        isBusy: false
    };
    var bbaiLockedActionBindings = [];
    var bbaiLockedModalState = {
        node: null,
        panel: null,
        lastTrigger: null,
        keyHandlerBound: false
    };

    if (!hasBulkConfig) {
        window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] REST configuration missing. Bulk operations disabled, but single regenerate will still work.');
    }

    function getActionButton(action) {
        var selector = '[data-action="' + action + '"]';
        var matches = Array.prototype.slice.call(document.querySelectorAll(selector));
        if (!matches.length) {
            return null;
        }

        // Prefer visible and enabled controls when duplicate actions exist.
        for (var i = 0; i < matches.length; i++) {
            if (!matches[i].disabled && matches[i].offsetParent !== null) {
                return matches[i];
            }
        }

        for (var j = 0; j < matches.length; j++) {
            if (!matches[j].disabled) {
                return matches[j];
            }
        }

        return matches[0];
    }

    function readUsageNumber(source, keys) {
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

    function readUsageString(source, keys) {
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

    function unwrapUsagePayload(rawUsage) {
        if (
            rawUsage &&
            typeof rawUsage === 'object' &&
            rawUsage.data &&
            typeof rawUsage.data === 'object' &&
            (
                rawUsage.data.used !== undefined ||
                rawUsage.data.limit !== undefined ||
                rawUsage.data.remaining !== undefined ||
                rawUsage.data.credits_used !== undefined ||
                rawUsage.data.credits_total !== undefined ||
                rawUsage.data.credits_remaining !== undefined ||
                rawUsage.data.quota
            )
        ) {
            return rawUsage.data;
        }

        return rawUsage;
    }

    function normalizeUsagePayload(rawUsage) {
        var usage = unwrapUsagePayload(rawUsage);
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

        used = readUsageNumber(usage, ['credits_used', 'creditsUsed', 'used']);
        if (isNaN(used)) {
            used = readUsageNumber(quota, ['credits_used', 'creditsUsed', 'used']);
        }
        used = isNaN(used) ? 0 : Math.max(0, used);

        limit = readUsageNumber(usage, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit']);
        if (isNaN(limit)) {
            limit = readUsageNumber(quota, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit']);
        }
        limit = isNaN(limit) || limit <= 0 ? 50 : limit;

        remaining = readUsageNumber(usage, ['credits_remaining', 'creditsRemaining', 'remaining']);
        if (isNaN(remaining)) {
            remaining = readUsageNumber(quota, ['credits_remaining', 'creditsRemaining', 'remaining']);
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

        resetDate = readUsageString(usage, ['resetDate']);
        resetDisplay = readUsageString(usage, ['reset_date']);
        if (!resetDisplay) {
            resetDisplay = readUsageString(quota, ['reset_date']);
        }
        if (!resetDate) {
            resetDate = resetDisplay;
        }

        resetTimestamp = readUsageNumber(usage, ['reset_timestamp', 'resetTimestamp', 'reset_ts']);
        if (isNaN(resetTimestamp)) {
            resetTimestamp = readUsageNumber(quota, ['reset_timestamp']);
        }

        daysUntilReset = readUsageNumber(usage, ['days_until_reset', 'daysUntilReset']);
        if (isNaN(daysUntilReset)) {
            daysUntilReset = readUsageNumber(quota, ['days_until_reset']);
        }

        planType = readUsageString(usage, ['plan_type', 'plan']);
        if (!planType) {
            planType = readUsageString(quota, ['plan_type', 'plan']);
        }

        var authState = readUsageString(usage, ['auth_state']) || readUsageString(quota, ['auth_state']) || 'authenticated';
        var quotaType = readUsageString(usage, ['quota_type']) || readUsageString(quota, ['quota_type']) || '';
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

        var quotaState = readUsageString(usage, ['quota_state']) || readUsageString(quota, ['quota_state']) || '';
        var lowCreditThreshold = readUsageNumber(usage, ['low_credit_threshold']);
        if (isNaN(lowCreditThreshold)) {
            lowCreditThreshold = readUsageNumber(quota, ['low_credit_threshold']);
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

        var freePlanOffer = readUsageNumber(usage, ['free_plan_offer']);
        if (isNaN(freePlanOffer)) {
            freePlanOffer = readUsageNumber(quota, ['free_plan_offer']);
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
            : (quota.is_trial !== undefined ? !!quota.is_trial : (quotaType === 'trial' || authState === 'anonymous'));
        var trialExhausted = usage.trial_exhausted !== undefined
            ? !!usage.trial_exhausted
            : (quota.trial_exhausted !== undefined ? !!quota.trial_exhausted : (isTrial && remaining <= 0));

        return $.extend({}, usage, {
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

    function mirrorUsagePayload(rawUsage) {
        var usage = normalizeUsagePayload(rawUsage);
        var targets;

        if (!usage) {
            return null;
        }

        targets = [window.BBAI_DASH, window.BBAI_DASHBOARD, window.BBAI, window.BBAI_UPGRADE];
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

    window.bbaiNormalizeAuthenticatedUsage = window.bbaiNormalizeAuthenticatedUsage || normalizeUsagePayload;
    window.bbaiMirrorAuthenticatedUsage = window.bbaiMirrorAuthenticatedUsage || mirrorUsagePayload;

    function isAnonymousTrialUsage(usage) {
        if (!usage || typeof usage !== 'object') {
            return false;
        }

        return String(usage.auth_state || '').toLowerCase() === 'anonymous' ||
            String(usage.quota_type || '').toLowerCase() === 'trial' ||
            !!usage.is_trial;
    }

    function getUsageForQuotaChecks() {
        return normalizeUsagePayload(
            (window.BBAI_DASH && (window.BBAI_DASH.initialUsage || window.BBAI_DASH.usage)) ||
            (window.BBAI_DASHBOARD && (window.BBAI_DASHBOARD.initialUsage || window.BBAI_DASHBOARD.usage)) ||
            (window.BBAI && (window.BBAI.initialUsage || window.BBAI.usage)) ||
            null
        );
    }

    function getUsageFromDom() {
        var selectors = [
            '[data-bbai-banner-usage-line]',
            '.bbai-usage-count',
            '.bbai-usage-count-text',
            '.bbai-card__subtitle'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (!node || !node.textContent) {
                continue;
            }

            var match = node.textContent.match(/([0-9][0-9,]*)\s*(?:of|\/)\s*([0-9][0-9,]*)/i);
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

    function getDashboardStateRoots() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-bbai-dashboard-state-root="1"]'));
    }

    function getDashboardStateRoot() {
        var roots = getDashboardStateRoots();
        if (!roots.length) {
            return null;
        }

        for (var i = 0; i < roots.length; i++) {
            if (roots[i] && roots[i].offsetParent !== null) {
                return roots[i];
            }
        }

        return roots[0];
    }

    function readGuestTrialRemainingFromDom() {
        var maxRem = 0;
        var roots = getDashboardStateRoots().concat(
            Array.prototype.slice.call(document.querySelectorAll('[data-bbai-library-workspace-root="1"]'))
        );
        var i;
        var r;
        var tr;
        var cr;
        for (i = 0; i < roots.length; i++) {
            r = roots[i];
            if (!r) {
                continue;
            }
            tr = parseInt(r.getAttribute('data-bbai-trial-remaining') || '', 10);
            cr = parseInt(r.getAttribute('data-bbai-credits-remaining') || '', 10);
            if (!isNaN(tr)) {
                maxRem = Math.max(maxRem, tr);
            }
            if (!isNaN(cr)) {
                maxRem = Math.max(maxRem, cr);
            }
        }
        return maxRem;
    }

    function guestTrialClientShowsRemainingCredits() {
        if (readGuestTrialRemainingFromDom() > 0) {
            return true;
        }
        var trial = window.BBAI_DASH && window.BBAI_DASH.trial;
        if (trial && trial.remaining !== undefined && trial.remaining !== null) {
            if (Math.max(0, parseInt(trial.remaining, 10) || 0) > 0) {
                return true;
            }
        }
        var merged = typeof window.bbaiGetUsageSnapshot === 'function' ? window.bbaiGetUsageSnapshot(null) : null;
        if (merged && isAnonymousTrialUsage(merged)) {
            var rem = parseInt(merged.remaining, 10);
            if (!isNaN(rem) && rem > 0) {
                return true;
            }
        }
        return false;
    }

    window.bbaiGuestTrialShowsRemainingCredits = guestTrialClientShowsRemainingCredits;

    function readDashboardStateBoolean(root, attributeName) {
        var value;

        if (!root || !attributeName) {
            return false;
        }

        value = String(root.getAttribute(attributeName) || '').toLowerCase();
        return value === '1' || value === 'true' || value === 'yes';
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

    function getDashboardStateContract() {
        var root = getDashboardStateRoot();
        var baseState;
        var runtimeState;
        var displayState;

        if (!root) {
            return null;
        }

        baseState = String(root.getAttribute('data-bbai-dashboard-base-state') || '');
        runtimeState = String(root.getAttribute('data-bbai-dashboard-runtime-state') || 'idle');
        displayState = String(root.getAttribute('data-bbai-dashboard-state') || '') || resolveDashboardDisplayState(baseState, runtimeState);

        return {
            root: root,
            baseState: baseState,
            runtimeState: runtimeState,
            displayState: displayState,
            isGuestTrial: readDashboardStateBoolean(root, 'data-bbai-is-guest-trial'),
            trialLimit: Math.max(0, parseInt(root.getAttribute('data-bbai-trial-limit'), 10) || 0),
            trialUsed: Math.max(0, parseInt(root.getAttribute('data-bbai-trial-used'), 10) || 0),
            trialRemaining: Math.max(0, parseInt(root.getAttribute('data-bbai-trial-remaining'), 10) || 0),
            trialExhausted: readDashboardStateBoolean(root, 'data-bbai-trial-exhausted'),
            lockedCtaMode: String(root.getAttribute('data-bbai-locked-cta-mode') || ''),
            freeAccountMonthlyLimit: Math.max(0, parseInt(root.getAttribute('data-bbai-free-account-monthly-limit'), 10) || 0)
        };
    }

    function syncDashboardStateRoots(update) {
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
                ? String(update.baseState || '')
                : String(root.getAttribute('data-bbai-dashboard-base-state') || '');
            var runtimeState = update.runtimeState !== undefined
                ? String(update.runtimeState || 'idle')
                : String(root.getAttribute('data-bbai-dashboard-runtime-state') || 'idle');

            if (update.isGuestTrial !== undefined) {
                root.setAttribute('data-bbai-is-guest-trial', update.isGuestTrial ? '1' : '0');
            }
            if (update.lockedCtaMode !== undefined) {
                root.setAttribute('data-bbai-locked-cta-mode', String(update.lockedCtaMode || ''));
            }
            if (update.freeAccountMonthlyLimit !== undefined) {
                root.setAttribute('data-bbai-free-account-monthly-limit', String(Math.max(0, parseInt(update.freeAccountMonthlyLimit, 10) || 0)));
            }
            if (update.trial && typeof update.trial === 'object') {
                if (update.trial.limit !== undefined) {
                    root.setAttribute('data-bbai-trial-limit', String(Math.max(0, parseInt(update.trial.limit, 10) || 0)));
                }
                if (update.trial.used !== undefined) {
                    root.setAttribute('data-bbai-trial-used', String(Math.max(0, parseInt(update.trial.used, 10) || 0)));
                }
                if (update.trial.remaining !== undefined) {
                    root.setAttribute('data-bbai-trial-remaining', String(Math.max(0, parseInt(update.trial.remaining, 10) || 0)));
                }
                if (update.trial.exhausted !== undefined) {
                    root.setAttribute('data-bbai-trial-exhausted', update.trial.exhausted ? '1' : '0');
                }
            }

            root.setAttribute('data-bbai-dashboard-base-state', baseState);
            root.setAttribute('data-bbai-dashboard-runtime-state', runtimeState);
            root.setAttribute('data-bbai-dashboard-state', resolveDashboardDisplayState(baseState, runtimeState));
        });

        if (typeof window.bbaiSyncDashboardStateRoot === 'function') {
            window.bbaiSyncDashboardStateRoot(update);
        }

        return getDashboardStateContract();
    }

    function syncDashboardStateFromUsage(rawUsage) {
        var usage = normalizeUsagePayload(rawUsage);
        var isAnonymousTrial;
        var limit;
        var used;
        var remaining;
        var exhausted;
        var freeAccountMonthlyLimit;
        var lockedCtaMode;
        var baseState;
        var quotaState;

        if (!usage) {
            return null;
        }

        isAnonymousTrial = isAnonymousTrialUsage(usage);
        limit = Math.max(0, parseInt(usage.limit, 10) || 0);
        used = Math.max(0, parseInt(usage.used, 10) || 0);
        remaining = parseInt(usage.remaining, 10);
        if (isNaN(remaining)) {
            remaining = Math.max(0, limit - used);
        }
        remaining = Math.max(0, remaining);
        exhausted = !!usage.trial_exhausted || (isAnonymousTrial && remaining <= 0);
        freeAccountMonthlyLimit = Math.max(0, parseInt(usage.free_plan_offer, 10) || 0);
        quotaState = String(usage.quota_state || '').toLowerCase();

        if (isAnonymousTrial) {
            baseState = exhausted ? 'logged_out_trial_exhausted' : 'logged_out_trial_available';
            lockedCtaMode = exhausted ? 'create_account' : '';
        } else {
            baseState = 'logged_in_free_or_paid';
            var planTier = normalizePlanTier(usage.plan_type || usage.plan);
            var exhaustedLoggedIn = !!(usage.upgrade_required || quotaState === 'exhausted');
            if (exhaustedLoggedIn) {
                if (planTier === 'agency') {
                    lockedCtaMode = 'manage_plan';
                } else if (planTier === 'growth') {
                    lockedCtaMode = 'upgrade_agency';
                } else {
                    lockedCtaMode = 'upgrade_growth';
                }
            } else {
                lockedCtaMode = '';
            }
        }

        return syncDashboardStateRoots({
            baseState: baseState,
            isGuestTrial: isAnonymousTrial,
            lockedCtaMode: lockedCtaMode,
            freeAccountMonthlyLimit: freeAccountMonthlyLimit,
            trial: {
                limit: limit,
                used: used,
                remaining: remaining,
                exhausted: exhausted
            }
        });
    }

    function setDashboardRuntimeState(runtimeState) {
        syncDashboardStateRoots({
            runtimeState: runtimeState || 'idle'
        });
    }

    function getUpgradeCtaUi() {
        return (window.BBAI_DASH && window.BBAI_DASH.upgradeCtaUi && typeof window.BBAI_DASH.upgradeCtaUi === 'object')
            ? window.BBAI_DASH.upgradeCtaUi
            : null;
    }

    function getLockedCtaMode() {
        var stateContract = getDashboardStateContract();
        var quotaState;
        var ctaUi = getUpgradeCtaUi();
        var lmDom;

        if (stateContract && stateContract.lockedCtaMode) {
            lmDom = String(stateContract.lockedCtaMode);
            // Logged-out users must never be put on the paid ladder by stale DOM/usage.
            if (ctaUi && !ctaUi.hasConnectedAccount && (lmDom === 'upgrade_growth' || lmDom === 'upgrade' || lmDom === 'upgrade_agency')) {
                return 'create_account';
            }
            if (lmDom) {
                return lmDom;
            }
        }

        quotaState = getQuotaState();
        if (quotaState.isAnonymousTrial && quotaState.signupRequired) {
            return 'create_account';
        }
        if (quotaState.isLocked) {
            if (ctaUi && !ctaUi.hasConnectedAccount) {
                return 'create_account';
            }
            var up = window.BBAI_DASH && window.BBAI_DASH.upgradePath ? window.BBAI_DASH.upgradePath : {};
            var st = String(up.step || '');
            if (st === 'agency') {
                return 'manage_plan';
            }
            if (st === 'growth') {
                return 'upgrade_agency';
            }
            return 'upgrade_growth';
        }

        return '';
    }

    function getLockedCtaAction(mode) {
        if (mode === 'create_account') {
            return 'open-signup';
        }
        if (mode === 'manage_plan') {
            return 'open-usage';
        }
        return 'open-upgrade';
    }

    function getLockedCtaTooltip() {
        var ctaUi = getUpgradeCtaUi();
        if (ctaUi && ctaUi.tooltipLocked) {
            return ctaUi.tooltipLocked;
        }
        var mode = getLockedCtaMode();
        if (mode === 'create_account') {
            return __('Create a free account to unlock AI generations', 'beepbeep-ai-alt-text-generator');
        }
        if (mode === 'manage_plan') {
            return __('Credit limit reached. Open usage and billing to manage your plan or buy more credits.', 'beepbeep-ai-alt-text-generator');
        }
        if (mode === 'upgrade_agency') {
            return __('You have used your Growth allowance. Upgrade to Agency for more capacity.', 'beepbeep-ai-alt-text-generator');
        }
        return __('Upgrade to Growth to continue generating', 'beepbeep-ai-alt-text-generator');
    }

    function getTrialLimit() {
        var stateContract = getDashboardStateContract();
        var usage = getUsageForQuotaChecks();

        if (stateContract && stateContract.trialLimit > 0) {
            return stateContract.trialLimit;
        }
        if (usage && isAnonymousTrialUsage(usage)) {
            return Math.max(0, parseInt(usage.limit, 10) || 0);
        }
        if (window.BBAI_DASH && window.BBAI_DASH.trial) {
            return Math.max(0, parseInt(window.BBAI_DASH.trial.limit || window.BBAI_DASH.trial.credits_total, 10) || 0);
        }

        return 0;
    }

    function getFreeAccountMonthlyLimit() {
        var stateContract = getDashboardStateContract();
        var usage = getUsageForQuotaChecks();

        if (stateContract && stateContract.freeAccountMonthlyLimit > 0) {
            return stateContract.freeAccountMonthlyLimit;
        }
        if (usage && usage.free_plan_offer !== undefined) {
            return Math.max(0, parseInt(usage.free_plan_offer, 10) || 0);
        }
        if (window.BBAI_DASH && window.BBAI_DASH.trial) {
            return Math.max(0, parseInt(window.BBAI_DASH.trial.free_plan_offer, 10) || 0);
        }

        return 50;
    }

    function buildTrialExhaustedMessage() {
        return sprintf(
            __('Free trial complete. Create a free account to unlock %d images per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
            Math.max(1, getFreeAccountMonthlyLimit() || 50)
        );
    }

    function buildLockedCtaAttributes(reason, source, options) {
        var mode = (options && options.mode) || getLockedCtaMode();
        var attributes = [
            'data-bbai-action="' + escapeLibraryAttr(getLockedCtaAction(mode)) + '"',
            'data-bbai-locked-cta="1"',
            'data-bbai-lock-reason="' + escapeLibraryAttr(reason || 'upgrade_required') + '"',
            'data-bbai-locked-source="' + escapeLibraryAttr(source || 'dashboard') + '"'
        ];

        if (!options || options.lockControl !== false) {
            attributes.push('data-bbai-lock-control="1"');
        }
        if (!options || options.ariaDisabled !== false) {
            attributes.push('aria-disabled="true"');
        }
        if (mode === 'create_account') {
            attributes.push('data-auth-tab="register"');
        }

        return ' ' + attributes.join(' ');
    }

    function applyLockedCtaAttributesToElement(element, reason, source) {
        var mode;

        if (!element) {
            return;
        }

        mode = getLockedCtaMode();
        element.setAttribute('data-bbai-action', getLockedCtaAction(mode));
        element.setAttribute('data-bbai-locked-cta', '1');
        element.setAttribute('data-bbai-lock-control', '1');
        element.setAttribute('data-bbai-lock-reason', reason || 'upgrade_required');
        element.setAttribute('data-bbai-locked-source', source || 'dashboard');
        element.setAttribute('aria-disabled', 'true');

        if (mode === 'create_account') {
            element.setAttribute('data-auth-tab', 'register');
        } else {
            element.removeAttribute('data-auth-tab');
        }

        if (mode === 'upgrade_agency') {
            element.setAttribute('data-bbai-pricing-variant', 'agency');
        } else if (mode === 'upgrade_growth' || mode === 'upgrade') {
            element.setAttribute('data-bbai-pricing-variant', 'growth');
        } else {
            element.removeAttribute('data-bbai-pricing-variant');
        }
    }

    function isFreePlan(planName) {
        var plan = String(planName || '').toLowerCase();
        if (!plan) {
            return true;
        }

        return plan === 'free' || plan === 'trial' || plan === 'starter';
    }

    function normalizePlanTier(planName) {
        var plan = String(planName || '').toLowerCase();
        if (plan === 'agency') {
            return 'agency';
        }
        if (plan === 'growth' || plan === 'pro') {
            return 'growth';
        }
        return 'free';
    }

    function getQuotaState() {
        var localized = window.bbai_admin || {};
        var usage = getUsageSnapshot(null) || {};
        var stateContract = getDashboardStateContract();
        var authState = String(usage.auth_state || '').toLowerCase();
        var quotaType = String(usage.quota_type || '').toLowerCase();
        var quotaState = String(usage.quota_state || '').toLowerCase();
        var freePlanOffer = usage.free_plan_offer !== undefined
            ? parseInt(usage.free_plan_offer, 10)
            : (stateContract ? parseInt(stateContract.freeAccountMonthlyLimit, 10) : NaN);
        var lowCreditThreshold = parseInt(usage.low_credit_threshold, 10);
        var isAnonymousTrial = (stateContract && stateContract.isGuestTrial) || isAnonymousTrialUsage(usage);

        var creditsAllocated = usage.limit !== undefined
            ? parseInt(usage.limit, 10)
            : (stateContract ? parseInt(stateContract.trialLimit, 10) : NaN);
        creditsAllocated = isNaN(creditsAllocated) ? 0 : Math.max(0, creditsAllocated);

        var creditsUsed = usage.used !== undefined
            ? parseInt(usage.used, 10)
            : (stateContract ? parseInt(stateContract.trialUsed, 10) : NaN);
        creditsUsed = isNaN(creditsUsed) ? 0 : Math.max(0, creditsUsed);

        var creditsRemaining = usage.remaining !== undefined
            ? parseInt(usage.remaining, 10)
            : (stateContract ? parseInt(stateContract.trialRemaining, 10) : NaN);
        if (isNaN(creditsRemaining) && creditsAllocated > 0) {
            creditsRemaining = creditsAllocated - creditsUsed;
        }
        creditsRemaining = isNaN(creditsRemaining) ? 0 : Math.max(0, creditsRemaining);

        var percentageRaw = parseFloat(usage.percentage);
        if (isNaN(percentageRaw) && creditsAllocated > 0) {
            percentageRaw = (creditsUsed / creditsAllocated) * 100;
        }
        percentageRaw = isNaN(percentageRaw) ? 0 : Math.max(0, Math.min(100, percentageRaw));

        var planTier = normalizePlanTier(usage.plan_type || usage.plan);
        var resetDate = usage.resetDate || usage.reset_date || '';
        var daysLeft = parseInt(usage.days_until_reset, 10);
        daysLeft = isNaN(daysLeft) ? null : Math.max(0, daysLeft);
        lowCreditThreshold = isNaN(lowCreditThreshold)
            ? (isAnonymousTrial ? Math.min(2, Math.max(1, creditsAllocated - 1)) : BBAI_BANNER_LOW_CREDITS_THRESHOLD)
            : Math.max(0, lowCreditThreshold);

        var quotaReached = creditsAllocated > 0 && creditsUsed >= creditsAllocated;
        var localizedLocked = !!localized.isLocked;
        var lockMode = stateContract ? String(stateContract.lockedCtaMode || '') : '';
        var ladderLocks = lockMode === 'upgrade' || lockMode === 'upgrade_growth' || lockMode === 'upgrade_agency' || lockMode === 'manage_plan' || lockMode === 'create_account';
        var stateLocked = !!(stateContract && (stateContract.trialExhausted || ladderLocks));
        var domRem = readGuestTrialRemainingFromDom();
        if (domRem > 0) {
            creditsRemaining = Math.max(creditsRemaining, domRem);
            quotaReached = false;
            stateLocked = false;
            localizedLocked = false;
        }
        var isLocked = localizedLocked || stateLocked || quotaReached || (creditsAllocated > 0 && creditsRemaining <= 0);

        return {
            creditsAllocated: creditsAllocated,
            creditsUsed: creditsUsed,
            creditsRemaining: creditsRemaining,
            creditsUsedPct: percentageRaw / 100,
            resetDate: resetDate,
            planTier: planTier,
            authState: authState || (isAnonymousTrial ? 'anonymous' : 'authenticated'),
            quotaType: quotaType || (isAnonymousTrial ? 'trial' : ''),
            quotaState: quotaState || ((stateContract && stateContract.trialExhausted) ? 'exhausted' : (creditsRemaining <= 0 ? 'exhausted' : (creditsRemaining <= lowCreditThreshold ? 'near_limit' : 'active'))),
            signupRequired: usage.signup_required !== undefined ? !!usage.signup_required : ((stateContract && stateContract.lockedCtaMode === 'create_account') || (isAnonymousTrial && creditsRemaining <= 0)),
            freePlanOffer: isNaN(freePlanOffer) ? 50 : Math.max(0, freePlanOffer),
            lowCreditThreshold: lowCreditThreshold,
            isAnonymousTrial: isAnonymousTrial,
            isLocked: isLocked,
            daysLeft: daysLeft,
            upgradeUrl: (localized.urls && localized.urls.upgrade) || getUpgradeUrl(),
            missingCount: localized.counts && typeof localized.counts.missing !== 'undefined'
                ? Math.max(0, parseInt(localized.counts.missing, 10) || 0)
                : null
        };
    }

    function getDashboardState() {
        var stateContract = getDashboardStateContract();
        if (stateContract && stateContract.displayState) {
            return stateContract.displayState;
        }

        var quotaState = getQuotaState();
        if (quotaState.isLocked) {
            return 'limit_reached';
        }

        var domUsage = getUsageFromDom();
        if (domUsage && domUsage.limit > 0 && domUsage.used >= domUsage.limit) {
            return 'limit_reached';
        }

        return 'active';
    }

    function isOutOfCreditsFromUsage() {
        if (guestTrialClientShowsRemainingCredits()) {
            return false;
        }
        var snap = getUsageSnapshot(null);
        if (snap && Math.max(0, parseInt(snap.remaining, 10) || 0) > 0) {
            return false;
        }

        var stateContract = getDashboardStateContract();
        if (stateContract) {
            var lm = String(stateContract.lockedCtaMode || '');
            return !!(stateContract.trialExhausted || lm === 'create_account' || lm === 'upgrade' || lm === 'upgrade_growth' || lm === 'upgrade_agency' || lm === 'manage_plan');
        }

        return getDashboardState() === 'limit_reached';
    }

    function isGenerationActionControl(element) {
        if (!element) {
            return false;
        }

        var action = String(element.getAttribute('data-action') || '').toLowerCase();
        var bbaiAction = String(element.getAttribute('data-bbai-action') || '').toLowerCase();
        var intendedAction = String(element.getAttribute('data-bbai-intended-action') || '').toLowerCase();
        if (action === 'generate-missing' || action === 'generate-selected' || action === 'regenerate-all' || action === 'regenerate-selected' || action === 'regenerate-single') {
            return true;
        }
        if (bbaiAction === 'generate_missing' || bbaiAction === 'reoptimize_all') {
            return true;
        }
        if (intendedAction === 'generate-missing' || intendedAction === 'generate-selected' || intendedAction === 'regenerate-all' || intendedAction === 'regenerate-selected' || intendedAction === 'regenerate-single') {
            return true;
        }
        return false;
    }

    function isLockedBulkControl(element) {
        if (!element) {
            return false;
        }

        var action = String(element.getAttribute('data-bbai-action') || '').toLowerCase();
        if (action === 'open-upgrade' ||
            action === 'open-signup' ||
            action === 'open-usage' ||
            element.getAttribute('data-bbai-locked-cta') === '1' ||
            element.classList.contains('bbai-upgrade-required-action') ||
            element.classList.contains('bbai-is-locked')) {
            return true;
        }

        if (element.disabled ||
            element.getAttribute('data-bbai-lock-control') === '1' ||
            element.getAttribute('aria-disabled') === 'true' ||
            element.classList.contains('disabled') ||
            element.classList.contains('bbai-optimization-cta--disabled') ||
            element.classList.contains('bbai-optimization-cta--locked')) {
            return true;
        }

        if (isGenerationActionControl(element) && isOutOfCreditsFromUsage()) {
            return true;
        }

        return false;
    }

    function getLockedCtaSource(trigger) {
        if (!trigger || !trigger.getAttribute) {
            return 'dashboard';
        }
        return trigger.getAttribute('data-bbai-locked-source') || 'dashboard';
    }

    function dispatchAnalyticsEvent(eventName, payload) {
        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', {
                detail: $.extend(
                    {
                        event: eventName,
                        timestamp: Date.now()
                    },
                    payload || {}
                )
            }));
        } catch (e) {
            // Ignore dispatch errors.
        }
    }

    function getLibraryAnalyticsStatus(row) {
        if (!row || !row.getAttribute) {
            return 'unknown';
        }

        var status = String(row.getAttribute('data-status') || '').toLowerCase();
        if (status === 'weak') {
            return 'needs_review';
        }
        if (status === 'optimized' || status === 'missing' || status === 'pending' || status === 'error') {
            return status;
        }

        return getLibraryAltTextFromRow(row) ? 'optimized' : 'missing';
    }

    function getAnalyticsPageSource() {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.getContext === 'function') {
            var context = window.bbaiAnalytics.getContext() || {};
            if (context.page === 'alt_library') {
                return 'library';
            }
            if (context.page === 'guest_dashboard') {
                return 'dashboard';
            }
            return context.page || 'dashboard';
        }

        return 'dashboard';
    }

    function resolveAnalyticsSource(node, fallback) {
        if (window.bbaiAnalytics && typeof window.bbaiAnalytics.resolveSource === 'function') {
            return window.bbaiAnalytics.resolveSource(node);
        }

        return fallback || getAnalyticsPageSource();
    }

    function trackDashboardEvent(eventName, payload) {
        var detail = $.extend(
            {
                event: eventName,
                source: 'dashboard',
                timestamp: Date.now()
            },
            payload || {}
        );

        dispatchAnalyticsEvent(eventName, detail);

        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var nonce = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
        if (!ajaxUrl || !nonce) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'beepbeepai_track_upgrade',
                nonce: nonce,
                source: eventName
            }
        });
    }

    function getAdminCreditsState() {
        var quotaState = getQuotaState();
        return {
            used: quotaState.creditsUsed,
            limit: quotaState.creditsAllocated,
            daysLeft: quotaState.daysLeft,
            resetDate: quotaState.resetDate,
            upgradeUrl: quotaState.upgradeUrl,
            missingCount: quotaState.missingCount,
            isLocked: quotaState.isLocked
        };
    }

    function isCreditsLocked() {
        return getQuotaState().isLocked;
    }

    function bindLockedAction(selector, reason) {
        if (!selector) {
            return;
        }

        for (var i = 0; i < bbaiLockedActionBindings.length; i++) {
            if (bbaiLockedActionBindings[i].selector === selector) {
                bbaiLockedActionBindings[i].reason = reason;
                return;
            }
        }

        bbaiLockedActionBindings.push({
            selector: selector,
            reason: reason || 'upgrade_required'
        });
    }

    function getLockedActionBinding(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        for (var i = 0; i < bbaiLockedActionBindings.length; i++) {
            var binding = bbaiLockedActionBindings[i];
            var matched = target.closest(binding.selector);
            if (matched) {
                return {
                    node: matched,
                    reason: binding.reason
                };
            }
        }

        return null;
    }

    function getLockedActionLabel(reason, element) {
        var normalizedReason = String(reason || '').toLowerCase();
        var lockedMode = getLockedCtaMode();
        var ctaUi = getUpgradeCtaUi();
        var map = ctaUi && ctaUi.lockedLabels ? ctaUi.lockedLabels : null;

        if (map && typeof map === 'object') {
            if (normalizedReason === 'regenerate_single' || normalizedReason === 'regenerate-single') {
                if (map.regenerate_single) {
                    return map.regenerate_single;
                }
            }
            if (normalizedReason === 'generate_missing' || normalizedReason === 'generate-missing') {
                if (map.generate_missing) {
                    return map.generate_missing;
                }
            }
            if (normalizedReason === 'reoptimize_all' || normalizedReason === 'reoptimize-all' || normalizedReason === 'regenerate-all') {
                if (map.reoptimize_all) {
                    return map.reoptimize_all;
                }
            }
            if (map.default) {
                return map.default;
            }
        }

        if (lockedMode === 'create_account') {
            if (normalizedReason === 'regenerate_single' || normalizedReason === 'regenerate-single') {
                return __('Create a free account to unlock AI regenerations', 'beepbeep-ai-alt-text-generator');
            }

            return __('Create free account to continue', 'beepbeep-ai-alt-text-generator');
        }

        if (normalizedReason === 'generate_missing' || normalizedReason === 'generate-missing') {
            return __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator');
        }
        if (normalizedReason === 'reoptimize_all' || normalizedReason === 'reoptimize-all' || normalizedReason === 'regenerate-all') {
            return __('Upgrade to continue improving ALT text', 'beepbeep-ai-alt-text-generator');
        }
        if (normalizedReason === 'regenerate_single' || normalizedReason === 'regenerate-single') {
            return __('Buy more credits to regenerate ALT text', 'beepbeep-ai-alt-text-generator');
        }

        if (element && String(element.textContent || '').toLowerCase().indexOf('generate missing') !== -1) {
            return __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator');
        }
        if (element && String(element.textContent || '').toLowerCase().indexOf('re-optim') !== -1) {
            return __('Upgrade to continue improving ALT text', 'beepbeep-ai-alt-text-generator');
        }

        return '';
    }

    function getLockedActionLabelTarget(element) {
        if (!element || !element.querySelectorAll) {
            return null;
        }

        var candidates = element.querySelectorAll('span');
        for (var i = 0; i < candidates.length; i++) {
            var span = candidates[i];
            if (span.classList && span.classList.contains('bbai-btn-icon')) {
                continue;
            }
            if (String(span.textContent || '').trim() !== '') {
                return span;
            }
        }

        return null;
    }

    function applyLockedActionState(element, reason) {
        if (!element) {
            return;
        }

        var lockedReason = reason || element.getAttribute('data-bbai-lock-reason') || 'upgrade_required';
        var labelTarget = getLockedActionLabelTarget(element);
        var lockedLabel = getLockedActionLabel(lockedReason, element);
        var preserveLabel = element.getAttribute('data-bbai-lock-preserve-label') === '1';

        element.classList.add('bbai-is-locked');
        element.classList.add('bbai-optimization-cta--locked');
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('data-bbai-lock-control', '1');
        element.setAttribute('data-bbai-locked-cta', '1');
        element.setAttribute('data-bbai-lock-reason', lockedReason);
        element.style.pointerEvents = '';
        element.style.cursor = '';

        if (typeof element.disabled !== 'undefined') {
            element.disabled = false;
        }
        if (element.hasAttribute('disabled')) {
            element.removeAttribute('disabled');
        }
        if (element.getAttribute('tabindex') === '-1') {
            element.setAttribute('tabindex', '0');
        }

        if (lockedLabel && !preserveLabel) {
            if (labelTarget) {
                if (!labelTarget.getAttribute('data-bbai-original-label')) {
                    labelTarget.setAttribute('data-bbai-original-label', String(labelTarget.textContent || '').trim());
                }
                labelTarget.textContent = lockedLabel;
            } else if (!element.querySelector('svg')) {
                if (!element.getAttribute('data-bbai-original-label')) {
                    element.setAttribute('data-bbai-original-label', String(element.textContent || '').trim());
                }
                element.textContent = lockedLabel;
            }
        }
    }

    function clearAutoLockedActionState(element) {
        if (!element || element.getAttribute('data-bbai-auto-locked') !== '1') {
            return;
        }

        element.classList.remove('bbai-is-locked');
        element.classList.remove('bbai-optimization-cta--locked');
        element.removeAttribute('aria-disabled');
        element.removeAttribute('data-bbai-lock-control');
        element.removeAttribute('data-bbai-locked-cta');
        element.removeAttribute('data-bbai-lock-reason');
        element.removeAttribute('data-bbai-auto-locked');

        var labelTarget = getLockedActionLabelTarget(element);
        if (labelTarget && labelTarget.getAttribute('data-bbai-original-label')) {
            labelTarget.textContent = labelTarget.getAttribute('data-bbai-original-label');
            labelTarget.removeAttribute('data-bbai-original-label');
        } else if (!labelTarget && element.getAttribute('data-bbai-original-label')) {
            element.textContent = element.getAttribute('data-bbai-original-label');
            element.removeAttribute('data-bbai-original-label');
        }
    }

    function getStatsSnapshot() {
        return (window.BBAI_DASH && window.BBAI_DASH.stats) ||
            (window.BBAI && window.BBAI.stats) ||
            {};
    }

    function getMissingCountFromStats() {
        var stats = getStatsSnapshot();
        if (stats.missing !== undefined) {
            return Math.max(0, parseInt(stats.missing, 10) || 0);
        }
        if (stats.missing_alt !== undefined) {
            return Math.max(0, parseInt(stats.missing_alt, 10) || 0);
        }
        return 0;
    }

    function getOptimizedCountFromStats() {
        var stats = getStatsSnapshot();
        if (stats.with_alt !== undefined) {
            return Math.max(0, parseInt(stats.with_alt, 10) || 0);
        }
        return 0;
    }

    function appendQueryParams(url, params) {
        var baseUrl = String(url || '');
        if (!baseUrl) {
            return '';
        }

        try {
            var parsed = new URL(baseUrl, window.location.origin);
            Object.keys(params || {}).forEach(function(key) {
                parsed.searchParams.set(key, params[key]);
            });
            return parsed.toString();
        } catch (e) {
            var query = Object.keys(params || {}).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }).join('&');
            if (!query) {
                return baseUrl;
            }
            return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + query;
        }
    }

    function getLimitPreviewData(forceRefresh) {
        if (!forceRefresh && bbaiLimitPreviewCache) {
            return $.Deferred().resolve(bbaiLimitPreviewCache).promise();
        }
        if (!forceRefresh && bbaiLimitPreviewRequest) {
            return bbaiLimitPreviewRequest;
        }

        var requestUrl = appendQueryParams(config.restMissing, {
            include_preview: '1',
            preview_limit: '5',
            per_page: '5'
        });
        var nonce = config.nonce || '';
        var deferred = $.Deferred();

        if (!requestUrl || !nonce) {
            bbaiLimitPreviewCache = {
                missing_count: getMissingCountFromStats(),
                missing_images: []
            };
            deferred.resolve(bbaiLimitPreviewCache);
            return deferred.promise();
        }

        $.ajax({
            url: requestUrl,
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            }
        })
            .done(function(response) {
                var payload = response && response.data ? response.data : response;
                var missingCount = parseInt(payload && payload.missing_count, 10);
                if (isNaN(missingCount)) {
                    missingCount = getMissingCountFromStats();
                }
                var missingImages = Array.isArray(payload && payload.missing_images) ? payload.missing_images : [];

                bbaiLimitPreviewCache = {
                    missing_count: Math.max(0, missingCount),
                    missing_images: missingImages.slice(0, 5).map(function(item) {
                        return {
                            id: parseInt(item && item.id, 10) || 0,
                            filename: String(item && item.filename ? item.filename : __('Untitled image', 'beepbeep-ai-alt-text-generator')),
                            thumb_url: item && item.thumb_url ? String(item.thumb_url) : ''
                        };
                    })
                };
                deferred.resolve(bbaiLimitPreviewCache);
            })
            .fail(function() {
                bbaiLimitPreviewCache = {
                    missing_count: getMissingCountFromStats(),
                    missing_images: []
                };
                deferred.resolve(bbaiLimitPreviewCache);
            });

        bbaiLimitPreviewRequest = deferred.promise();
        bbaiLimitPreviewRequest.always(function() {
            bbaiLimitPreviewRequest = null;
        });
        return bbaiLimitPreviewRequest;
    }

    function getControlLabelElement(element) {
        if (!element || !element.querySelector) {
            return null;
        }
        var quickActionLabel = element.querySelector('[data-bbai-quick-action-label]');
        if (quickActionLabel) {
            return quickActionLabel;
        }
        var spans = element.querySelectorAll('span');
        if (!spans.length) {
            return null;
        }
        for (var i = 0; i < spans.length; i++) {
            if (spans[i].classList && spans[i].classList.contains('bbai-btn-icon')) {
                continue;
            }
            return spans[i];
        }
        return spans[spans.length - 1];
    }

    function normalizeUnlockedControlLabel(element) {
        if (!element || element.classList.contains('bbai-upgrade-required-action') || element.classList.contains('bbai-is-locked')) {
            return;
        }

        var ariaLabel = String(element.getAttribute('aria-label') || '').trim();
        if (!ariaLabel) {
            return;
        }

        var labelElement = getControlLabelElement(element);
        if (labelElement && labelElement.hasAttribute('data-bbai-quick-action-label')) {
            return;
        }

        if (labelElement) {
            if (String(labelElement.textContent || '').trim() !== ariaLabel) {
                labelElement.textContent = ariaLabel;
            }
            return;
        }

        if (String(element.textContent || '').trim() !== ariaLabel) {
            element.textContent = ariaLabel;
        }
    }

    function convertControlToUpgradeRequired(element) {
        if (!element || !isGenerationActionControl(element)) {
            return;
        }

        var currentAction = element.getAttribute('data-action');
        var currentBbaiAction = element.getAttribute('data-bbai-action');
        var currentAuthTab = element.getAttribute('data-auth-tab');
        if (!element.hasAttribute('data-bbai-original-action')) {
            element.setAttribute('data-bbai-original-action', currentAction ? currentAction : '__none__');
        }
        if (!element.hasAttribute('data-bbai-original-bbai-action')) {
            element.setAttribute('data-bbai-original-bbai-action', currentBbaiAction ? currentBbaiAction : '__none__');
        }
        if (!element.hasAttribute('data-bbai-original-auth-tab')) {
            element.setAttribute('data-bbai-original-auth-tab', currentAuthTab ? currentAuthTab : '__none__');
        }
        if (!element.hasAttribute('data-bbai-intended-action')) {
            if (currentAction) {
                element.setAttribute('data-bbai-intended-action', currentAction);
            } else if (currentBbaiAction === 'generate_missing') {
                element.setAttribute('data-bbai-intended-action', 'generate-missing');
            } else if (currentBbaiAction === 'reoptimize_all') {
                element.setAttribute('data-bbai-intended-action', 'regenerate-all');
            }
        }

        element.removeAttribute('disabled');
        element.removeAttribute('aria-disabled');
        element.removeAttribute('tabindex');
        element.removeAttribute('data-bbai-lock-control');
        element.classList.remove('disabled', 'bbai-optimization-cta--disabled', 'bbai-optimization-cta--locked');
        element.classList.add('bbai-upgrade-required-action');
        if (!element.getAttribute('data-bbai-locked-source')) {
            element.setAttribute('data-bbai-locked-source', 'dashboard');
        }
        element.removeAttribute('data-action');
        element.setAttribute('data-bbai-tooltip', getLockedCtaTooltip());
        element.setAttribute('data-bbai-tooltip-position', 'bottom');
        var intendedAction = element.getAttribute('data-bbai-intended-action') || '';
        var reason = 'upgrade_required';
        if (intendedAction === 'generate-missing') {
            reason = 'generate_missing';
        } else if (intendedAction === 'regenerate-all') {
            reason = 'reoptimize_all';
        } else if (intendedAction === 'regenerate-single') {
            reason = 'regenerate_single';
        }
        applyLockedCtaAttributesToElement(
            element,
            reason,
            element.getAttribute('data-bbai-locked-source') || 'dashboard'
        );
        applyLockedActionState(element, reason);
    }

    function restoreControlFromUpgradeRequired(element) {
        if (!element || !element.classList.contains('bbai-upgrade-required-action')) {
            return;
        }

        var originalAction = element.getAttribute('data-bbai-original-action');
        var originalBbaiAction = element.getAttribute('data-bbai-original-bbai-action');
        var originalAuthTab = element.getAttribute('data-bbai-original-auth-tab');
        var intendedAction = element.getAttribute('data-bbai-intended-action');
        var originalLabel = element.getAttribute('data-bbai-original-label');

        element.classList.remove('bbai-upgrade-required-action');
        element.classList.remove('bbai-is-locked');
        element.removeAttribute('data-bbai-locked-cta');
        element.removeAttribute('data-bbai-lock-reason');
        element.removeAttribute('data-bbai-lock-control');
        element.removeAttribute('aria-disabled');
        element.removeAttribute('data-bbai-tooltip');
        element.removeAttribute('data-bbai-tooltip-position');

        if (originalAction && originalAction !== '__none__') {
            element.setAttribute('data-action', originalAction);
        } else if (intendedAction) {
            element.setAttribute('data-action', intendedAction);
        } else {
            element.removeAttribute('data-action');
        }

        if (originalBbaiAction && originalBbaiAction !== '__none__') {
            element.setAttribute('data-bbai-action', originalBbaiAction);
        } else if (
            element.getAttribute('data-bbai-action') === 'open-upgrade' ||
            element.getAttribute('data-bbai-action') === 'open-signup'
        ) {
            element.removeAttribute('data-bbai-action');
        }

        if (originalAuthTab && originalAuthTab !== '__none__') {
            element.setAttribute('data-auth-tab', originalAuthTab);
        } else {
            element.removeAttribute('data-auth-tab');
        }

        var labelElement = getControlLabelElement(element);
        if (labelElement && originalLabel) {
            labelElement.textContent = originalLabel;
        } else if (labelElement && labelElement.getAttribute('data-bbai-original-label')) {
            labelElement.textContent = labelElement.getAttribute('data-bbai-original-label');
            labelElement.removeAttribute('data-bbai-original-label');
        }

        element.removeAttribute('data-bbai-original-action');
        element.removeAttribute('data-bbai-original-bbai-action');
        element.removeAttribute('data-bbai-original-auth-tab');
        element.removeAttribute('data-bbai-original-label');
    }

    function renderLimitPreviewItems(targetNode, previewData) {
        if (!targetNode) {
            return;
        }

        var items = Array.isArray(previewData && previewData.missing_images) ? previewData.missing_images : [];
        if (!items.length) {
            targetNode.innerHTML = '<p class="bbai-limit-state-empty">' + escapeHtml(__('No preview images available yet.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            return;
        }

        var html = '<ul class="bbai-limit-state-preview-list">';
        items.slice(0, 5).forEach(function(item) {
            html += '<li class="bbai-limit-state-preview-item">';
            if (item.thumb_url) {
                html += '<img class="bbai-limit-state-preview-thumb" src="' + escapeHtml(item.thumb_url) + '" alt="">';
            }
            html += '<span class="bbai-limit-state-preview-name">' + escapeHtml(item.filename || __('Untitled image', 'beepbeep-ai-alt-text-generator')) + '</span>';
            html += '</li>';
        });
        html += '</ul>';
        targetNode.innerHTML = html;
    }

    function purgeLegacyDashboardProgressBars() {
        var dashboardRoot = document.querySelector('.bbai-dashboard-shell');
        if (!dashboardRoot) {
            return;
        }

        var removed = 0;
        dashboardRoot.querySelectorAll('.bbai-linear-progress, .bbai-usage-linear-progress, .bbai-linear-progress-fill, .bbai-usage-linear-progress-fill').forEach(function(node) {
            // Keep removal scoped to dashboard status/usage card surfaces only.
            if (node.closest('.bbai-status-card, .bbai-usage-card-redesign, .bbai-dashboard-shell')) {
                node.remove();
                removed += 1;
            }
        });

        // Guard: top action card intentionally has no progress bar in final dashboard UI.
        dashboardRoot.querySelectorAll('.bbai-dashboard-action-bar [role="progressbar"]').forEach(function(node) {
            node.remove();
            removed += 1;
        });

        if (removed > 0) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Removed legacy dashboard progress bars:', removed);
        }
    }

    function renderLimitReachedUI() {
        // Dashboard now owns lock messaging in the status/primary blocks.
        var root = document.getElementById('bbai-limit-state-root');
        if (root) {
            root.hidden = true;
            root.innerHTML = '';
        }
        bbaiLimitStateViewedTracked = false;
    }

    function applyDashboardState(forcePreviewRefresh) {
        var state = getDashboardState();
        var generationLocked = isOutOfCreditsFromUsage();
        var selector = [
            '[data-action="generate-missing"]',
            '[data-action="regenerate-all"]',
            '[data-bbai-action="generate_missing"]',
            '[data-bbai-action="reoptimize_all"]',
            '[data-bbai-action="open-upgrade"][data-bbai-intended-action]',
            '[data-bbai-action="open-signup"][data-bbai-intended-action]'
        ].join(', ');

        document.querySelectorAll(selector).forEach(function(control) {
            if (generationLocked) {
                convertControlToUpgradeRequired(control);
            } else {
                restoreControlFromUpgradeRequired(control);
                normalizeUnlockedControlLabel(control);
            }
        });

        renderLimitReachedUI(state, !!forcePreviewRefresh);
        purgeLegacyDashboardProgressBars();
    }

    function getLockedModalFocusableElements(modalNode) {
        if (!modalNode || !modalNode.querySelectorAll) {
            return [];
        }

        var nodes = modalNode.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        var focusable = [];
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (!node || node.disabled) {
                continue;
            }
            if (node.offsetParent === null && node !== document.activeElement) {
                continue;
            }
            focusable.push(node);
        }
        return focusable;
    }

    function handleLockedUpgradeModalKeys(event) {
        var modalNode = bbaiLockedModalState.node;
        if (!modalNode || modalNode.getAttribute('aria-hidden') === 'true') {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeUpgradeModal();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        var focusable = getLockedModalFocusableElements(modalNode);
        if (!focusable.length) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function ensureLockedUpgradeModal() {
        if (bbaiLockedModalState.node && document.body.contains(bbaiLockedModalState.node)) {
            return bbaiLockedModalState.node;
        }

        var wrapper = document.createElement('div');
        wrapper.id = 'bbai-locked-upgrade-modal';
        wrapper.className = 'bbai-locked-upgrade-modal';
        wrapper.setAttribute('role', 'dialog');
        wrapper.setAttribute('aria-modal', 'true');
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.setAttribute('aria-labelledby', 'bbai-locked-upgrade-title');
        wrapper.setAttribute('aria-describedby', 'bbai-locked-upgrade-body');
        wrapper.innerHTML = '' +
            '<div class="bbai-locked-upgrade-modal__backdrop" data-bbai-locked-modal-close="1"></div>' +
            '<div class="bbai-locked-upgrade-modal__panel" role="document">' +
            '  <button type="button" class="bbai-locked-upgrade-modal__close" data-bbai-locked-modal-close="1" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">×</button>' +
            '  <h2 id="bbai-locked-upgrade-title" class="bbai-locked-upgrade-modal__title">' + escapeHtml(__('Monthly credits used up', 'beepbeep-ai-alt-text-generator')) + '</h2>' +
            '  <p id="bbai-locked-upgrade-body" class="bbai-locked-upgrade-modal__body"></p>' +
            '  <p class="bbai-locked-upgrade-modal__body">' + escapeHtml(__('Upgrade to Growth for 1,000/month, bulk processing, priority queue, multilingual support.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '  <div class="bbai-locked-upgrade-modal__actions">' +
            '    <a href="#" target="_blank" rel="noopener noreferrer" class="bbai-btn bbai-btn-primary bbai-locked-upgrade-modal__primary" data-bbai-locked-modal-upgrade="1">' + escapeHtml(__('Upgrade to Growth', 'beepbeep-ai-alt-text-generator')) + '</a>' +
            '    <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-locked-modal-close="1">' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '  </div>' +
            '  <button type="button" class="bbai-locked-upgrade-modal__link" data-bbai-locked-modal-upgrade="1">' + escapeHtml(__('See plans', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '</div>';

        document.body.appendChild(wrapper);
        bbaiLockedModalState.node = wrapper;
        bbaiLockedModalState.panel = wrapper.querySelector('.bbai-locked-upgrade-modal__panel');

        // Fallback inline modal positioning if stylesheet rules are unavailable/cached stale.
        wrapper.style.position = 'fixed';
        wrapper.style.inset = '0';
        wrapper.style.zIndex = '100000';
        wrapper.style.display = 'none';
        wrapper.style.alignItems = 'center';
        wrapper.style.justifyContent = 'center';
        wrapper.style.padding = '16px';

        var backdrop = wrapper.querySelector('.bbai-locked-upgrade-modal__backdrop');
        if (backdrop) {
            backdrop.style.position = 'absolute';
            backdrop.style.inset = '0';
            backdrop.style.background = 'rgba(15, 23, 42, 0.56)';
        }

        var panel = wrapper.querySelector('.bbai-locked-upgrade-modal__panel');
        if (panel) {
            panel.style.position = 'relative';
            panel.style.zIndex = '1';
            panel.style.width = 'min(560px, 92vw)';
            panel.style.maxHeight = '90vh';
            panel.style.overflowY = 'auto';
        }

        wrapper.addEventListener('click', function(event) {
            var closeTrigger = event.target.closest('[data-bbai-locked-modal-close="1"]');
            if (closeTrigger || event.target === wrapper) {
                event.preventDefault();
                closeUpgradeModal();
                return;
            }

            var upgradeTrigger = event.target.closest('[data-bbai-locked-modal-upgrade="1"]');
            if (!upgradeTrigger) {
                return;
            }

            event.preventDefault();
            var uiModal = getUpgradeCtaUi();
            closeUpgradeModal();
            if (uiModal && !uiModal.hasConnectedAccount) {
                if (typeof openAuthSignupModal === 'function') {
                    openAuthSignupModal();
                }
                return;
            }

            var usage = getUsageSnapshot(null);

            window.setTimeout(function() {
                var opened = openUpgradeModal(usage);
                if (!opened) {
                    openUpgradeDestination(usage);
                }
            }, 0);
        });

        return wrapper;
    }

    function openLockedUpgradeModal(reason, context) {
        var ctaUiEarly = getUpgradeCtaUi();
        if (ctaUiEarly && !ctaUiEarly.hasConnectedAccount) {
            trackDashboardEvent('locked_action_signup_clicked', { source: getAnalyticsPageSource(), reason: reason || 'upgrade_required' });
            if (typeof openAuthSignupModal === 'function') {
                openAuthSignupModal();
            }
            return true;
        }

        var modalNode = ensureLockedUpgradeModal();
        if (!modalNode) {
            return false;
        }

        var usageCopy = (context && context.message)
            ? String(context.message)
            : __('You\'ve reached your monthly limit. Upgrade to generate more ALT text.', 'beepbeep-ai-alt-text-generator');
        var bodyNode = modalNode.querySelector('#bbai-locked-upgrade-body');
        if (bodyNode) {
            bodyNode.textContent = usageCopy;
        }

        var adminState = getAdminCreditsState();
        var upgradeUrl = adminState.upgradeUrl || getUpgradeUrl();
        var primaryLink = modalNode.querySelector('.bbai-locked-upgrade-modal__primary');
        if (primaryLink) {
            primaryLink.setAttribute('href', upgradeUrl || '#');
        }

        bbaiLockedModalState.lastTrigger = context && context.trigger ? context.trigger : document.activeElement;
        modalNode.classList.add('is-visible');
        modalNode.style.display = 'flex';
        modalNode.setAttribute('aria-hidden', 'false');
        if (document.body) {
            document.body.classList.add('bbai-modal-open');
            document.body.style.overflow = 'hidden';
        }
        if (document.documentElement) {
            document.documentElement.style.overflow = 'hidden';
        }

        if (!bbaiLockedModalState.keyHandlerBound) {
            document.addEventListener('keydown', handleLockedUpgradeModalKeys, true);
            bbaiLockedModalState.keyHandlerBound = true;
        }

        window.setTimeout(function() {
            var focusable = getLockedModalFocusableElements(modalNode);
            if (focusable.length) {
                focusable[0].focus();
            } else if (bbaiLockedModalState.panel) {
                bbaiLockedModalState.panel.focus();
            }
        }, 10);

        trackDashboardEvent('upgrade_required_modal_open', {
            reason: reason || 'upgrade_required',
            source: context && context.source ? context.source : 'dashboard'
        });
        return true;
    }

    window.bbaiOpenLockedUpgradeModal = function(reason, context) {
        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            return window.bbaiOpenUpgradeModal(reason || 'upgrade_required', context || {});
        }

        return openUpgradeModal(reason || 'upgrade_required', context || {});
    };

    function enforceOutOfCreditsBulkLocks() {
        var creditsLocked = isCreditsLocked();

        for (var i = 0; i < bbaiLockedActionBindings.length; i++) {
            var binding = bbaiLockedActionBindings[i];
            var nodes = document.querySelectorAll(binding.selector);

            for (var j = 0; j < nodes.length; j++) {
                var node = nodes[j];
                if (!node) {
                    continue;
                }

                var isServerLocked = node.getAttribute('data-bbai-locked-cta') === '1' ||
                    node.classList.contains('bbai-upgrade-required-action');

                if (isServerLocked || (creditsLocked && isGenerationActionControl(node))) {
                    node.setAttribute('data-bbai-auto-locked', '1');
                    applyLockedActionState(node, node.getAttribute('data-bbai-lock-reason') || binding.reason);
                    continue;
                }

                clearAutoLockedActionState(node);
            }
        }
    }

    function bindOutOfCreditsClickGuard() {
        if (bbaiOutOfCreditsGuardBound) {
            return;
        }
        bbaiOutOfCreditsGuardBound = true;

        document.addEventListener('click', function(event) {
            var binding = getLockedActionBinding(event.target);
            if (!binding || !binding.node) {
                return;
            }

            var trigger = binding.node;
            var reason = trigger.getAttribute('data-bbai-lock-reason') || binding.reason || 'upgrade_required';
            var shouldOpenLockModal = trigger.getAttribute('data-bbai-locked-cta') === '1' ||
                trigger.classList.contains('bbai-is-locked') ||
                trigger.classList.contains('bbai-upgrade-required-action') ||
                (isCreditsLocked() && isGenerationActionControl(trigger));

            if (!shouldOpenLockModal) {
                return;
            }

            applyLockedActionState(trigger, reason);
            handleLockedCtaClick(trigger, event);
        }, true);
    }

    function canManageAccount() {
        return !!(window.BBAI && window.BBAI.canManage);
    }

    function getUpgradeModalElement() {
        var modalById = document.getElementById('bbai-upgrade-modal');
        if (modalById && modalById.querySelector('.bbai-upgrade-modal__content')) {
            return modalById;
        }

        var modalByData = document.querySelector('[data-bbai-upgrade-modal="1"]');
        if (modalByData && modalByData.querySelector('.bbai-upgrade-modal__content')) {
            if (modalByData.id !== 'bbai-upgrade-modal') {
                modalByData.id = 'bbai-upgrade-modal';
            }
            return modalByData;
        }

        return null;
    }

    function getUpgradeUrl() {
        return (window.BBAI_DASH && window.BBAI_DASH.upgradeUrl) ||
            (window.BBAI && window.BBAI.upgradeUrl) ||
            (config && config.upgradeUrl) ||
            '';
    }

    function openUpgradeDestination(usage) {
        var upgradeUrl = getUpgradeUrl();
        if (upgradeUrl) {
            try {
                window.open(upgradeUrl, '_blank', 'noopener,noreferrer');
                return true;
            } catch (e) {
                // Fall through to modal fallback.
            }
        }

        return openUpgradeModal(usage);
    }

    function isElementVisiblyRendered(element) {
        if (!element) {
            return false;
        }

        var computed = window.getComputedStyle ? window.getComputedStyle(element) : null;
        if (!computed) {
            return true;
        }

        if (computed.display === 'none' || computed.visibility === 'hidden') {
            return false;
        }

        var opacity = parseFloat(computed.opacity || '1');
        if (!isNaN(opacity) && opacity <= 0.01) {
            return false;
        }

        var rect = element.getBoundingClientRect ? element.getBoundingClientRect() : null;
        if (rect && rect.width <= 1 && rect.height <= 1) {
            return false;
        }

        return true;
    }

    function hasVisibleLimitModal() {
        var customModalOverlay = document.getElementById('bbai-modal-overlay');
        if (customModalOverlay && customModalOverlay.classList.contains('is-visible') && isElementVisiblyRendered(customModalOverlay)) {
            return true;
        }

        var upgradeModal = getUpgradeModalElement();
        if (!upgradeModal) {
            return false;
        }

        var hasVisibleClass = upgradeModal.classList.contains('active') || upgradeModal.classList.contains('is-visible');
        var displayLooksOpen = String(upgradeModal.style.display || '').toLowerCase() === 'flex';
        return (hasVisibleClass || displayLooksOpen) && isElementVisiblyRendered(upgradeModal);
    }

    function ensureVisibleLimitModalOrFallback(message, usage, canManage) {
        window.setTimeout(function() {
            if (hasVisibleLimitModal()) {
                return;
            }

            clearModalAndScrollLocks();
            showLimitFallbackDialog(message, usage, canManage);
        }, 220);
    }

    function openUpgradeModal(reasonOrUsage, context) {
        var hasReasonContext = typeof reasonOrUsage === 'string' ||
            (!!context && typeof context === 'object');
        var resolvedReason = hasReasonContext && typeof reasonOrUsage === 'string' ? reasonOrUsage : '';
        var usage = reasonOrUsage;
        if (hasReasonContext) {
            if (context && typeof context === 'object' && context.usage) {
                usage = context.usage;
            }
        } else if (usage && typeof usage === 'object' && parseInt(usage.remaining, 10) <= 0) {
            hasReasonContext = true;
            resolvedReason = 'limit_reached';
            context = {
                usage: usage,
                source: 'limit-reached',
                force: true
            };
        }
        if (hasVisibleLimitModal()) {
            return true;
        }

        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            var openResult = hasReasonContext
                ? window.bbaiOpenUpgradeModal(resolvedReason || reasonOrUsage, context)
                : window.bbaiOpenUpgradeModal();
            if (openResult !== false) {
                return true;
            }
        }
        if (typeof alttextaiShowModal === 'function') {
            if (alttextaiShowModal() !== false) {
                return true;
            }
        }
        if (typeof window.alttextaiShowModal === 'function') {
            if (window.alttextaiShowModal() !== false) {
                return true;
            }
        }
        if (typeof showUpgradeModal === 'function') {
            showUpgradeModal(usage);
            return true;
        }
        if (typeof window.beepbeepai_show_upgrade_modal === 'function') {
            window.beepbeepai_show_upgrade_modal(usage);
            return true;
        }

        var directModal = getUpgradeModalElement();
        if (directModal) {
            directModal.style.display = 'flex';
            directModal.classList.add('active');
            directModal.classList.add('is-visible');
            directModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            if (document.documentElement) {
                document.documentElement.style.overflow = 'hidden';
            }
            return true;
        }

        var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
        if (upgradeBtn) {
            upgradeBtn.click();
            return true;
        }

        $(document).trigger('alttextai:show-upgrade-modal', [usage]);
        return false;
    }

    function closeUpgradeModal() {
        var lockedModal = bbaiLockedModalState.node;
        if (lockedModal) {
            lockedModal.classList.remove('is-visible');
            lockedModal.setAttribute('aria-hidden', 'true');
            lockedModal.style.display = 'none';
        }
        if (bbaiLockedModalState.keyHandlerBound) {
            document.removeEventListener('keydown', handleLockedUpgradeModalKeys, true);
            bbaiLockedModalState.keyHandlerBound = false;
        }

        var upgradeModal = getUpgradeModalElement();
        if (upgradeModal) {
            upgradeModal.classList.remove('active');
            upgradeModal.classList.remove('is-visible');
            upgradeModal.setAttribute('aria-hidden', 'true');
            var upgradeContent = upgradeModal.querySelector('.bbai-upgrade-modal__content');
            if (upgradeContent) {
                upgradeContent.removeAttribute('style');
            }
            upgradeModal.style.display = 'none';
        }
        if (document.body && document.body.classList) {
            document.body.classList.remove('modal-open');
        }

        var modalOverlay = document.getElementById('bbai-modal-overlay');
        if (modalOverlay) {
            modalOverlay.classList.remove('is-visible');
            modalOverlay.style.display = 'none';
            modalOverlay.setAttribute('aria-hidden', 'true');
        }

        if (document.body && document.body.classList) {
            document.body.classList.remove('bbai-modal-open');
        }

        if (document.body) {
            document.body.style.overflow = '';
        }
        if (document.documentElement) {
            document.documentElement.style.overflow = '';
        }

        if (bbaiLockedModalState.lastTrigger && typeof bbaiLockedModalState.lastTrigger.focus === 'function') {
            bbaiLockedModalState.lastTrigger.focus();
        }
        bbaiLockedModalState.lastTrigger = null;
    }

    function openAuthSignupModal() {
        if (typeof showAuthModal === 'function') {
            showAuthModal('register');
            return;
        }
        if (typeof window.showAuthModal === 'function') {
            window.showAuthModal('register');
            return;
        }
        if (window.authModal && typeof window.authModal.show === 'function') {
            window.authModal.show();
            if (typeof window.authModal.showRegisterForm === 'function') {
                window.authModal.showRegisterForm();
            }
            return;
        }

        var authTrigger = document.querySelector('[data-action="show-auth-modal"][data-auth-tab="register"]');
        if (authTrigger) {
            authTrigger.click();
            return;
        }

        try {
            var currentUrl = new URL(window.location.href);
            var currentPage = String(currentUrl.searchParams.get('page') || '').toLowerCase();

            if (!currentPage || currentPage.indexOf('bbai') !== 0) {
                currentUrl.searchParams.set('page', 'bbai');
                currentUrl.searchParams.delete('tab');
            }

            currentUrl.searchParams.set('bbai_open_auth', '1');
            currentUrl.searchParams.set('bbai_auth_tab', 'register');
            currentUrl.searchParams.delete('checkout');
            currentUrl.searchParams.delete('checkout_error');
            window.location.href = currentUrl.toString();
            return;
        } catch (error) {
            // Fall through to the static admin URL below.
        }

        var adminUrl = (window.bbai_ajax && window.bbai_ajax.admin_url) || 'admin.php';
        window.location.href = adminUrl + '?page=bbai&bbai_open_auth=1&bbai_auth_tab=register';
    }

    function normalizeLimitErrorData(errorData) {
        if (!errorData || typeof errorData !== 'object') {
            return {};
        }

        var normalized = $.extend({}, errorData);
        var nested = normalized.data && typeof normalized.data === 'object' ? normalized.data : null;

        if ((!normalized.code || normalized.code === '') && nested && nested.code) {
            normalized.code = nested.code;
        }

        if ((!normalized.usage || typeof normalized.usage !== 'object') && nested && nested.usage) {
            normalized.usage = nested.usage;
        }

        if ((normalized.remaining === undefined || normalized.remaining === null) && nested && nested.remaining !== undefined) {
            normalized.remaining = nested.remaining;
        }

        return normalized;
    }

    function isLimitReachedError(rawErrorData) {
        var errorData = normalizeLimitErrorData(rawErrorData);
        var code = errorData.code ? String(errorData.code) : '';

        // quota_check_mismatch is retryable / per-image — do not treat as full batch exhaustion
        if (code === 'limit_reached' || code === 'quota_exhausted' || code === 'bbai_trial_exhausted') {
            return true;
        }

        if (code === 'insufficient_credits') {
            var remaining = parseInt(errorData.remaining, 10);
            if (isNaN(remaining) || remaining <= 0) {
                return true;
            }
        }

        var message = (errorData.message || errorData.error || '').toLowerCase();
        return message.indexOf('quota exhausted') !== -1 ||
            message.indexOf('quota exceeded') !== -1 ||
            message.indexOf('monthly quota') !== -1 ||
            message.indexOf('monthly limit') !== -1 ||
            message.indexOf('limit reached') !== -1 ||
            message.indexOf('out of credits') !== -1 ||
            message.indexOf('not enough credits') !== -1 ||
            message.indexOf('credit limit') !== -1;
    }

    function buildQuotaExhaustedUsageSnapshot(rawErrorData) {
        var errorData = normalizeLimitErrorData(rawErrorData);
        var usage = getUsageSnapshot(errorData && errorData.usage ? errorData.usage : null);
        var forcedUsage;
        var isTrial;

        if (!usage) {
            return null;
        }

        forcedUsage = $.extend(true, {}, usage);
        isTrial = (errorData && errorData.code === 'bbai_trial_exhausted') || isAnonymousTrialUsage(forcedUsage);

        forcedUsage.remaining = 0;
        forcedUsage.credits_remaining = 0;
        forcedUsage.creditsRemaining = 0;
        forcedUsage.quota_state = 'exhausted';
        forcedUsage.signup_required = isTrial;
        forcedUsage.upgrade_required = !isTrial;
        forcedUsage.trial_exhausted = isTrial;
        forcedUsage.is_trial = isTrial || !!forcedUsage.is_trial;

        if (forcedUsage.quota && typeof forcedUsage.quota === 'object') {
            forcedUsage.quota.remaining = 0;
            forcedUsage.quota.credits_remaining = 0;
            forcedUsage.quota.creditsRemaining = 0;
            forcedUsage.quota.quota_state = 'exhausted';
            forcedUsage.quota.signup_required = isTrial;
            forcedUsage.quota.upgrade_required = !isTrial;
            forcedUsage.quota.trial_exhausted = isTrial;
            forcedUsage.quota.is_trial = isTrial || !!forcedUsage.quota.is_trial;
        }

        return mirrorUsagePayload(forcedUsage) || forcedUsage;
    }

    function mapGenerationErrorToUi(rawErrorData) {
        var errorData = normalizeLimitErrorData(rawErrorData);
        var code = errorData && errorData.code ? String(errorData.code).toLowerCase() : '';
        var isQuota = isLimitReachedError(errorData);
        var usage = isQuota
            ? (buildQuotaExhaustedUsageSnapshot(errorData) || getUsageSnapshot(errorData && errorData.usage ? errorData.usage : null))
            : getUsageSnapshot(errorData && errorData.usage ? errorData.usage : null);
        var quotaCause = code === 'insufficient_credits'
            ? __('not enough credits remaining', 'beepbeep-ai-alt-text-generator')
            : __('credit limit reached', 'beepbeep-ai-alt-text-generator');
        var mapped = {
            code: code || 'generation_failed_generic',
            rawMessage: (errorData && (errorData.message || errorData.error)) ? String(errorData.message || errorData.error) : '',
            usage: usage,
            isQuota: isQuota,
            isTrial: code === 'bbai_trial_exhausted' || isAnonymousTrialUsage(usage),
            uiCode: 'GENERATION_FAILED_GENERIC',
            quotaCause: '',
            rowMessage: __('This image could not be processed.', 'beepbeep-ai-alt-text-generator'),
            batchMessage: '',
            dialogMessage: ''
        };

        if (isQuota) {
            mapped.uiCode = 'QUOTA_EXCEEDED';
            mapped.quotaCause = quotaCause;
            mapped.rowMessage = sprintf(
                __('Skipped — %s', 'beepbeep-ai-alt-text-generator'),
                quotaCause
            );
            mapped.batchMessage = __('You ran out of credits during this batch.', 'beepbeep-ai-alt-text-generator');
            mapped.dialogMessage = mapped.isTrial
                ? buildTrialExhaustedMessage()
                : __('You have reached your current credit limit for ALT text generation.', 'beepbeep-ai-alt-text-generator');
            return mapped;
        }

        if (mapped.rawMessage) {
            mapped.rowMessage = mapped.rawMessage;
        }

        return mapped;
    }

    function handleTrialExhausted(errorData) {
        if (!errorData) {
            errorData = {};
        }
        if (guestTrialClientShowsRemainingCredits()) {
            if (typeof window.alttextai_refresh_usage === 'function') {
                window.alttextai_refresh_usage();
            } else if (typeof refreshUsageStats === 'function') {
                refreshUsageStats();
            }
            if (typeof showNotification === 'function') {
                showNotification(
                    __('Free generations are still available. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'info'
                );
            }
            return;
        }
        errorData.code = 'bbai_trial_exhausted';
        handleLimitReached(errorData);
    }

    function ensureBbaiDashboardMainVisible() {
        var dash = document.getElementById('bbai-dashboard-main');
        if (dash && dash.style && dash.style.display === 'none') {
            dash.style.display = '';
        }
    }

    window.bbaiEnsureDashboardMainVisible = ensureBbaiDashboardMainVisible;

    function clearModalAndScrollLocks() {
        // Clear known modal states that can leave the UI unresponsive.
        $('.bbai-regenerate-modal.active, .bbai-bulk-progress-modal.active, #bbai-modal-success.active').removeClass('active');
        closeUpgradeModal();
        $('body').css('overflow', '');
        if (document.body) {
            document.body.style.overflow = '';
        }
        if (document.documentElement) {
            document.documentElement.style.overflow = '';
        }
        ensureBbaiDashboardMainVisible();
        dismissGuestTrialBulkJobChrome();
    }

    function showLimitFallbackDialog(message, usage, canManage) {
        var isAnonymousTrial = isAnonymousTrialUsage(usage);
        var freePlanOffer = usage && usage.free_plan_offer !== undefined ? parseInt(usage.free_plan_offer, 10) : 50;
        var noticeMessage = String(
            message || (
                isAnonymousTrial
                    ? buildTrialExhaustedMessage()
                    : __('This month’s free allowance is used.', 'beepbeep-ai-alt-text-generator')
            )
        );
        var promptMessage = isAnonymousTrial
            ? noticeMessage + '\n\n' + sprintf(
                __('Press OK to create a free account and unlock %d images per month.', 'beepbeep-ai-alt-text-generator'),
                isNaN(freePlanOffer) ? 50 : Math.max(0, freePlanOffer)
            )
            : noticeMessage + '\n\n' + __('Press OK to open upgrade plans now, or Cancel to wait for your monthly reset.', 'beepbeep-ai-alt-text-generator');

        if (canManage && typeof window.confirm === 'function') {
            var shouldContinue = window.confirm(promptMessage);
            if (shouldContinue) {
                if (isAnonymousTrial) {
                    openAuthSignupModal();
                } else {
                    openUpgradeDestination(usage);
                }
            }
            return;
        }

        if (typeof showNotification === 'function') {
            showNotification(noticeMessage, 'warning');
        } else if (typeof window.alert === 'function') {
            window.alert(noticeMessage);
        }
    }

    function applyDashboardGuestTrialUsageOverlay(usage) {
        if (!usage || typeof usage !== 'object') {
            return usage;
        }
        var dashRoot = getDashboardRootNode();
        if (!dashRoot || dashRoot.getAttribute('data-bbai-is-guest-trial') !== '1') {
            return usage;
        }
        return normalizeUsagePayload(
            $.extend({}, usage, {
                auth_state: 'anonymous',
                quota_type: 'trial',
                is_trial: true
            })
        );
    }

    function getUsageSnapshot(errorUsage) {
        var fromError = normalizeUsagePayload(errorUsage);
        var snap = fromError ||
            getUsageForQuotaChecks() ||
            normalizeUsagePayload(getUsageFromBanner(getSharedBannerNode())) ||
            normalizeUsagePayload(getUsageFromDom()) ||
            null;

        if (fromError) {
            return applyDashboardGuestTrialUsageOverlay(snap);
        }

        // Library workspace data-* quota attrs are server-rendered for this page; merge over
        // BBAI_DASH even when offsetParent is null (layout edge cases) so remaining credits
        // never disagree with the hero.
        var rootEl = getLibraryWorkspaceRoot();
        if (rootEl) {
            var rootSnap = normalizeUsagePayload({
                credits_used: rootEl.getAttribute('data-bbai-credits-used'),
                credits_total: rootEl.getAttribute('data-bbai-credits-total'),
                credits_remaining: rootEl.getAttribute('data-bbai-credits-remaining'),
                auth_state: rootEl.getAttribute('data-bbai-auth-state'),
                quota_type: rootEl.getAttribute('data-bbai-quota-type'),
                quota_state: rootEl.getAttribute('data-bbai-quota-state'),
                signup_required: rootEl.getAttribute('data-bbai-signup-required') === '1',
                free_plan_offer: rootEl.getAttribute('data-bbai-free-plan-offer'),
                low_credit_threshold: rootEl.getAttribute('data-bbai-low-credit-threshold')
            });
            if (rootSnap && snap) {
                // Root data-* can lag behind REST/usage refresh (hero chips update from API first).
                // Never let stale DOM zero out credits when the client already has a higher remaining count.
                var apiRem = Math.max(0, parseInt(snap.remaining, 10) || 0);
                var domCredRem = Math.max(0, parseInt(rootSnap.remaining, 10) || 0);
                var domTrialRem = Math.max(0, parseInt(rootEl.getAttribute('data-bbai-trial-remaining') || '', 10) || 0);
                var bestRem = Math.max(apiRem, domCredRem, domTrialRem);
                return applyDashboardGuestTrialUsageOverlay(
                    normalizeUsagePayload(
                        $.extend({}, snap, rootSnap, {
                            credits_remaining: bestRem,
                            remaining: bestRem
                        })
                    )
                );
            }
            if (rootSnap) {
                return applyDashboardGuestTrialUsageOverlay(rootSnap);
            }
        }

        return applyDashboardGuestTrialUsageOverlay(snap);
    }

    window.bbaiGetUsageSnapshot = window.bbaiGetUsageSnapshot || getUsageSnapshot;

    function getQuotaResetMeta(usage) {
        var meta = {
            daysUntilReset: null,
            formattedResetDate: ''
        };

        if (!usage || typeof usage !== 'object') {
            return meta;
        }

        var resetTsRaw = usage.reset_timestamp || usage.resetTimestamp || usage.reset_ts || 0;
        var resetTs = parseInt(resetTsRaw, 10);
        if (!isNaN(resetTs) && resetTs > 0 && resetTs < 1000000000000) {
            // Backend timestamps are usually in seconds; normalize to milliseconds.
            resetTs = resetTs * 1000;
        }

        if ((!resetTs || isNaN(resetTs)) && usage.resetDate) {
            resetTs = Date.parse(String(usage.resetDate));
        }
        if ((!resetTs || isNaN(resetTs)) && usage.reset_date) {
            resetTs = Date.parse(String(usage.reset_date));
        }

        var providedDays = parseInt(usage.days_until_reset, 10);
        if (!isNaN(providedDays)) {
            meta.daysUntilReset = Math.max(0, providedDays);
        } else if (resetTs && !isNaN(resetTs)) {
            var msUntilReset = resetTs - Date.now();
            meta.daysUntilReset = Math.max(0, Math.ceil(msUntilReset / (24 * 60 * 60 * 1000)));
        }

        if (resetTs && !isNaN(resetTs)) {
            try {
                meta.formattedResetDate = new Date(resetTs).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (e) {
                meta.formattedResetDate = '';
            }
        }

        if (!meta.formattedResetDate && usage.resetDate) {
            meta.formattedResetDate = String(usage.resetDate);
        } else if (!meta.formattedResetDate && usage.reset_date) {
            meta.formattedResetDate = String(usage.reset_date);
        }

        return meta;
    }

    function buildBannerResetCopy(daysLeft) {
        var safeDaysLeft = Math.max(0, parseInt(daysLeft, 10) || 0);

        return sprintf(
            _n('resets in %s day', 'resets in %s days', safeDaysLeft, 'beepbeep-ai-alt-text-generator'),
            safeDaysLeft.toLocaleString()
        );
    }

    function handleLockedCtaClick(trigger, event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (event && typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }
        if (event && typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        var bbaiActEarly = trigger && trigger.getAttribute
            ? String(trigger.getAttribute('data-bbai-action') || '').toLowerCase()
            : '';
        if (bbaiActEarly === 'open-usage') {
            var usageUrlEarly = (window.BBAI_DASH && window.BBAI_DASH.usageAdminUrl) ||
                (window.bbai_ajax && window.bbai_ajax.admin_url ? String(window.bbai_ajax.admin_url).replace(/\/?$/, '/') + '?page=bbai-credit-usage' : '');
            if (usageUrlEarly) {
                window.location.href = usageUrlEarly;
            }
            return false;
        }

        var dashboardLockContractEarly = getDashboardStateContract();
        var dashboardLockedModeEarly = dashboardLockContractEarly ? String(dashboardLockContractEarly.lockedCtaMode || '') : '';
        if (bbaiActEarly === 'open-signup' || dashboardLockedModeEarly === 'create_account') {
            var sourceSignupEarly = getLockedCtaSource(trigger);
            var reasonSignupEarly = trigger && trigger.getAttribute
                ? String(trigger.getAttribute('data-bbai-lock-reason') || '').toLowerCase()
                : '';
            trackDashboardEvent('bbai_locked_cta_clicked', { source: sourceSignupEarly });
            dispatchAnalyticsEvent('signup_clicked', {
                source: sourceSignupEarly,
                location: sourceSignupEarly,
                trigger: bbaiActEarly === 'open-signup' ? 'locked_cta_open_signup' : 'dashboard_state_create_account',
                reason: reasonSignupEarly || 'trial_exhausted'
            });
            openAuthSignupModal();
            return false;
        }

        var source = getLockedCtaSource(trigger);
        var usage = getUsageSnapshot(null);
        var isAnonymousTrial = isAnonymousTrialUsage(usage) ||
            (trigger && trigger.getAttribute && String(trigger.getAttribute('data-bbai-action') || '').toLowerCase() === 'open-signup');
        var reason = trigger && trigger.getAttribute
            ? String(trigger.getAttribute('data-bbai-lock-reason') || '').toLowerCase()
            : '';

        if (!reason) {
            var action = trigger && trigger.getAttribute
                ? String(trigger.getAttribute('data-action') || trigger.getAttribute('data-bbai-intended-action') || '').toLowerCase()
                : '';
            if (action === 'generate-missing') {
                reason = 'generate_missing';
            } else if (action === 'regenerate-all') {
                reason = 'reoptimize_all';
            } else {
                reason = 'upgrade_required';
            }
        }

        var directUpgradeModal = trigger && trigger.getAttribute &&
            trigger.getAttribute('data-bbai-direct-upgrade-modal') === '1';

        trackDashboardEvent('bbai_locked_cta_clicked', { source: source });

        if (isAnonymousTrial) {
            dispatchAnalyticsEvent('signup_clicked', {
                source: source,
                location: source,
                trigger: 'anonymous_trial_gate',
                reason: reason || 'trial_exhausted'
            });
            if (Math.max(0, parseInt(usage && usage.remaining, 10) || 0) <= 0 ||
                String(usage && usage.quota_state || '').toLowerCase() === 'exhausted') {
                handleLimitReached({
                    code: 'bbai_trial_exhausted',
                    usage: usage
                });
                return false;
            }
            openAuthSignupModal();
            return false;
        }

        dispatchAnalyticsEvent('upgrade_clicked', {
            source: source,
            location: source,
            trigger: directUpgradeModal ? 'open_upgrade_modal' : 'locked_upgrade_cta',
            reason: reason || 'upgrade_required'
        });

        var pricingVariant = trigger && trigger.getAttribute
            ? String(trigger.getAttribute('data-bbai-pricing-variant') || '').trim().toLowerCase()
            : '';
        if (!pricingVariant && window.BBAI_DASH && window.BBAI_DASH.upgradePath && window.BBAI_DASH.upgradePath.pricing_variant) {
            pricingVariant = String(window.BBAI_DASH.upgradePath.pricing_variant).toLowerCase();
        }

        var modalOpened = false;
        if (directUpgradeModal) {
            modalOpened = openUpgradeModal(usage);
        } else {
            modalOpened = openUpgradeModal(reason, {
                trigger: trigger,
                source: source,
                usage: usage,
                message: __('You\'ve reached your monthly limit. Upgrade to generate more ALT text.', 'beepbeep-ai-alt-text-generator'),
                showAgency: pricingVariant === 'agency',
                comparePlans: pricingVariant === 'browse'
            });
        }
        if (!modalOpened) {
            modalOpened = openUpgradeDestination(usage);
        }

        return false;
    }

    function handleLimitReached(errorData) {
        var normalizedError = normalizeLimitErrorData(errorData);
        var errorCode = normalizedError && normalizedError.code ? String(normalizedError.code) : '';
        if (
            guestTrialClientShowsRemainingCredits() &&
            (errorCode === 'limit_reached' || errorCode === 'quota_exhausted' || errorCode === 'bbai_trial_exhausted')
        ) {
            if (typeof window.alttextai_refresh_usage === 'function') {
                window.alttextai_refresh_usage();
            } else if (typeof refreshUsageStats === 'function') {
                refreshUsageStats();
            }
            if (typeof showNotification === 'function') {
                showNotification(
                    __('Trial credits are still available. Please try again, or refresh the page if the message persists.', 'beepbeep-ai-alt-text-generator'),
                    'info'
                );
            }
            return;
        }
        var mappedError = mapGenerationErrorToUi(normalizedError);
        var usage = mappedError && mappedError.usage
            ? mappedError.usage
            : getUsageSnapshot(normalizedError && normalizedError.usage ? normalizedError.usage : null);
        var clientShowsTrialCredits = guestTrialClientShowsRemainingCredits();
        var isTrialExhausted =
            errorCode === 'bbai_trial_exhausted' ||
            (!clientShowsTrialCredits &&
                isAnonymousTrialUsage(usage) &&
                Math.max(0, parseInt(usage && usage.remaining, 10) || 0) <= 0);
        var canManage = canManageAccount();
        var analyticsSource = getAnalyticsPageSource();
        var freePlanOffer = usage && usage.free_plan_offer !== undefined ? parseInt(usage.free_plan_offer, 10) : 50;
        var monthlyAllowance = usage && usage.limit !== undefined ? Math.max(1, parseInt(usage.limit, 10) || 50) : 50;
        var libraryUrl = (function() {
            var libraryRoot = document.querySelector('[data-bbai-library-workspace-root="1"]');
            var dashboardRoot = document.querySelector('[data-bbai-dashboard-root="1"]');
            return (libraryRoot && libraryRoot.getAttribute('data-bbai-library-url')) ||
                (dashboardRoot && dashboardRoot.getAttribute('data-bbai-library-url')) ||
                '';
        })();

        clearModalAndScrollLocks();
        if (usage) {
            syncDashboardStateFromUsage(usage);
        }
        if (isTrialExhausted) {
            syncDashboardStateRoots({
                baseState: 'logged_out_trial_exhausted',
                runtimeState: 'idle',
                lockedCtaMode: 'create_account',
                trial: {
                    limit: getTrialLimit() || (usage ? Math.max(0, parseInt(usage.limit, 10) || 0) : 0),
                    used: getTrialLimit() || (usage ? Math.max(0, parseInt(usage.used, 10) || 0) : 0),
                    remaining: 0,
                    exhausted: true
                }
            });
        } else {
            setDashboardRuntimeState('generation_failed');
        }
        applyDashboardState(false);

        if (isTrialExhausted) {
            dispatchAnalyticsEvent('trial_exhausted', {
                source: analyticsSource
            });
        }

        if (isTrialExhausted && canManageAccount() && window.bbaiModal && typeof window.bbaiModal.show === 'function') {
            try {
                window.bbaiModal.show({
                    type: 'warning',
                    title: __('Free trial complete', 'beepbeep-ai-alt-text-generator'),
                    message: sprintf(
                        __('Create a free account to unlock %d images per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                        isNaN(freePlanOffer) ? 50 : Math.max(0, freePlanOffer)
                    ),
                    buttons: [
                        {
                            text: __('Create free account', 'beepbeep-ai-alt-text-generator'),
                            primary: true,
                            action: function() {
                                window.bbaiModal.close();
                                openAuthSignupModal();
                            }
                        },
                        {
                            text: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                            primary: false,
                            action: function() {
                                window.bbaiModal.close();
                                if (libraryUrl) {
                                    window.location.href = libraryUrl;
                                }
                            }
                        }
                    ]
                });
                ensureVisibleLimitModalOrFallback(
                    buildTrialExhaustedMessage(),
                    usage,
                    canManage
                );
                if (window.bbaiModal && window.bbaiModal.activeModal) {
                    return;
                }
            } catch (modalError) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to show trial exhausted modal', modalError);
            }

            showLimitFallbackDialog(
                buildTrialExhaustedMessage(),
                usage,
                canManage
            );
            return;
        }

        var baseMessage = (mappedError && mappedError.dialogMessage) ||
            __('You have reached your current credit limit for ALT text generation.', 'beepbeep-ai-alt-text-generator');
        var quotaResetMeta = getQuotaResetMeta(usage);
        var resetMessage = sprintf(
            __('Your next %d free credits will be available at the next monthly reset.', 'beepbeep-ai-alt-text-generator'),
            monthlyAllowance
        );

        if (quotaResetMeta.daysUntilReset !== null) {
            if (quotaResetMeta.daysUntilReset <= 0) {
                resetMessage = sprintf(
                    __('Your next %d free credits should be available today.', 'beepbeep-ai-alt-text-generator'),
                    monthlyAllowance
                );
            } else {
                resetMessage = sprintf(
                    _n(
                        'Your next %1$d free credits will be available in %2$d day.',
                        'Your next %1$d free credits will be available in %2$d days.',
                        quotaResetMeta.daysUntilReset,
                        'beepbeep-ai-alt-text-generator'
                    ),
                    monthlyAllowance,
                    quotaResetMeta.daysUntilReset
                );
            }
        }

        if (quotaResetMeta.formattedResetDate) {
            resetMessage += ' ' + sprintf(
                __('Reset date: %s.', 'beepbeep-ai-alt-text-generator'),
                quotaResetMeta.formattedResetDate
            );
        }

        var fullMessage = baseMessage + '\n\n' + resetMessage;

        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
            try {
                if (canManage) {
                    window.bbaiModal.show({
                        type: 'warning',
                        title: __('This month’s free allowance is used', 'beepbeep-ai-alt-text-generator'),
                        message: fullMessage + '\n\n' + __('Your existing ALT text is still available to review. Upgrade to continue generating ALT text now.', 'beepbeep-ai-alt-text-generator'),
                        buttons: [
                            {
                                text: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                                primary: true,
                                action: function() {
                                    window.bbaiModal.close();
                                    var modalOpened = openUpgradeModal(usage);
                                    if (!modalOpened && typeof showNotification === 'function') {
                                        showNotification(__('Unable to open the upgrade modal. Please refresh and try again.', 'beepbeep-ai-alt-text-generator'), 'warning');
                                    }
                                }
                            },
                            {
                                text: __('Maybe later', 'beepbeep-ai-alt-text-generator'),
                                primary: false,
                                action: function() {
                                    window.bbaiModal.close();
                                }
                            }
                        ]
                    });
                } else {
                    window.bbaiModal.show({
                        type: 'warning',
                        title: __('This month’s free allowance is used', 'beepbeep-ai-alt-text-generator'),
                        message: fullMessage + '\n\n' + __('You do not have permission to upgrade this account. Please contact the account owner.', 'beepbeep-ai-alt-text-generator'),
                        buttons: [
                            {
                                text: __('OK', 'beepbeep-ai-alt-text-generator'),
                                primary: true,
                                action: function() {
                                    window.bbaiModal.close();
                                }
                            }
                        ]
                    });
                }
                ensureVisibleLimitModalOrFallback(fullMessage, usage, canManage);
                if (window.bbaiModal && window.bbaiModal.activeModal) {
                    return;
                }
            } catch (modalError) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to show quota modal', modalError);
            }

            showLimitFallbackDialog(fullMessage, usage, canManage);
            return;
        }

        if (!canManage) {
            if (typeof showNotification === 'function') {
                showNotification(fullMessage + ' ' + __('Please contact the account owner to upgrade.', 'beepbeep-ai-alt-text-generator'), 'warning');
            }
            return;
        }

        var modalOpened = openUpgradeModal(usage);
        if (typeof showNotification === 'function') {
            var fallbackMessage = fullMessage;
            if (!modalOpened) {
                fallbackMessage += ' ' + __('Unable to open the upgrade modal automatically.', 'beepbeep-ai-alt-text-generator');
            }
            showNotification(fallbackMessage, 'warning');
        }
    }

    function extractIdsFromResponse(response) {
        var payload = response;
        if (response && response.success === true && response.data) {
            payload = response.data;
        }

        var ids = payload && Array.isArray(payload.ids) ? payload.ids : [];
        return ids.map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) {
            return !isNaN(id) && id > 0;
        });
    }

    /**
     * Fetch attachment IDs for bulk actions.
     * Uses REST first, then falls back to admin-ajax when REST routes are unavailable.
     */
    function fetchBulkImageIds(scope, limit) {
        var deferred = $.Deferred();
        var requestedScope = scope === 'all' || scope === 'needs-review' ? scope : 'missing';
        var requestedLimit = parseInt(limit, 10);
        if (isNaN(requestedLimit) || requestedLimit <= 0) {
            requestedLimit = 500;
        }

        var restUrl = '';
        if (requestedScope === 'all') {
            restUrl = config.restAll || ((config.restRoot || '') + 'bbai/v1/list?scope=all');
        } else if (requestedScope === 'needs-review') {
            restUrl = config.restNeedsReview || ((config.restRoot || '') + 'bbai/v1/list?scope=needs-review');
        } else {
            restUrl = config.restMissing || ((config.restRoot || '') + 'bbai/v1/list?scope=missing');
        }

        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var ajaxNonce = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce || '';

        function requestViaRest() {
            if (!restUrl || !config.nonce) {
                deferred.reject({ message: __('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator') });
                return;
            }

            $.ajax({
                url: restUrl,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': config.nonce
                },
                data: {
                    limit: requestedLimit
                }
            })
            .done(function(response) {
                deferred.resolve({
                    ids: extractIdsFromResponse(response)
                });
            })
            .fail(function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to fetch image IDs via REST fallback:', error, xhr);
                deferred.reject(xhr || { message: __('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator') });
            });
        }

        function requestViaAjax() {
            if (!ajaxUrl || !ajaxNonce) {
                requestViaRest();
                return;
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'beepbeepai_get_attachment_ids',
                    scope: requestedScope,
                    limit: requestedLimit,
                    offset: 0,
                    nonce: ajaxNonce
                }
            })
            .done(function(response) {
                deferred.resolve({
                    ids: extractIdsFromResponse(response)
                });
            })
            .fail(function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] AJAX ID lookup failed, trying REST fallback:', error, xhr && xhr.status);
                requestViaRest();
            });
        }

        requestViaAjax();

        return deferred.promise();
    }

    function setBusyStateForControls(selector, busyLabel) {
        var nodes = Array.prototype.slice.call(document.querySelectorAll(selector)).filter(function(node) {
            return !!node && node.getAttribute('aria-disabled') !== 'true';
        });

        nodes.forEach(function(node) {
            if (!node.getAttribute('data-bbai-busy-original-html')) {
                node.setAttribute('data-bbai-busy-original-html', node.innerHTML);
            }
            if (!node.getAttribute('data-bbai-busy-original-disabled')) {
                node.setAttribute('data-bbai-busy-original-disabled', node.disabled ? 'true' : 'false');
            }

            node.classList.add('is-loading');
            node.setAttribute('aria-busy', 'true');
            node.setAttribute('aria-disabled', 'true');
            if ('disabled' in node) {
                node.disabled = true;
            }
            node.innerHTML = '<span class="bbai-spinner bbai-spinner-sm" aria-hidden="true"></span><span>' + escapeHtml(busyLabel) + '</span>';
        });

        return function restoreBusyState() {
            nodes.forEach(function(node) {
                var originalHtml = node.getAttribute('data-bbai-busy-original-html');
                var originalDisabled = node.getAttribute('data-bbai-busy-original-disabled') === 'true';
                if (originalHtml !== null) {
                    node.innerHTML = originalHtml;
                    node.removeAttribute('data-bbai-busy-original-html');
                }
                node.classList.remove('is-loading');
                node.removeAttribute('aria-busy');
                if (!originalDisabled) {
                    node.removeAttribute('aria-disabled');
                }
                if ('disabled' in node) {
                    node.disabled = originalDisabled;
                }
                node.removeAttribute('data-bbai-busy-original-disabled');
            });
        };
    }

    function getCurrentAdminPage() {
        var explicitPage = document.querySelector('[data-bbai-current-page]');
        if (explicitPage) {
            return String(explicitPage.getAttribute('data-bbai-current-page') || '').toLowerCase();
        }

        if (getDashboardRootNode()) {
            return 'dashboard';
        }

        try {
            var params = new URLSearchParams(window.location.search);
            var page = String(params.get('page') || '').toLowerCase();
            var tab = String(params.get('tab') || '').toLowerCase();

            if (page === 'bbai-library' || (page === 'bbai' && tab === 'library')) {
                return 'alt-library';
            }

            if (page === 'bbai') {
                return 'dashboard';
            }

            return page;
        } catch (error) {
            return '';
        }
    }

    function getScanResultsSection() {
        return document.querySelector('[data-bbai-scan-results-section]') ||
            document.getElementById('bbai-alt-table') ||
            document.querySelector('.bbai-library-table-shell');
    }

    function getScanResultsHeading(section) {
        var targetSection = section || getScanResultsSection();
        if (!targetSection || !targetSection.querySelector) {
            return null;
        }

        return targetSection.querySelector('[data-bbai-results-heading]') ||
            targetSection.querySelector('.bbai-library-table-title');
    }

    function getScanResultSummary(payload) {
        var missing = Math.max(0, parseInt(payload && payload.images_missing_alt, 10) || parseInt(payload && payload.missing, 10) || 0);
        var weak = Math.max(0, parseInt(payload && payload.needs_review_count, 10) || 0);
        var issueCount = missing + weak;

        return {
            missing: missing,
            weak: weak,
            issueCount: issueCount,
            hasIssues: issueCount > 0
        };
    }

    function buildScanIssueMessage(issueCount) {
        var count = Math.max(0, parseInt(issueCount, 10) || 0);
        if (count > 0) {
            return sprintf(
                _n(
                    'We found %s image that needs attention.',
                    'We found %s images that need attention.',
                    count,
                    'beepbeep-ai-alt-text-generator'
                ),
                formatDashboardNumber(count)
            );
        }

        return __('We found images that need attention.', 'beepbeep-ai-alt-text-generator');
    }

    function buildScanReviewUrl(summary, context) {
        var scanSummary = summary || getScanResultSummary({});
        var scanContext = context || {};
        var libraryUrl = scanContext.libraryUrl || getAltLibraryAdminUrl();

        if (scanSummary.missing > 0 && scanSummary.weak <= 0) {
            return scanContext.missingLibraryUrl || appendQueryParams(libraryUrl, { status: 'missing' });
        }

        if (scanSummary.weak > 0 && scanSummary.missing <= 0) {
            return appendQueryParams(libraryUrl, { status: 'weak' });
        }

        return libraryUrl;
    }

    function getScanFeedbackContext(trigger) {
        var currentPage = getCurrentAdminPage();
        var isAltLibraryPage = currentPage === 'alt-library' || isOnLibraryPage();
        var dashboardRoot = getDashboardRootNode();
        var workspaceRoot = document.querySelector('[data-bbai-library-workspace-root="1"]');
        var resultsSection = getScanResultsSection();
        var libraryUrl = '';
        var missingLibraryUrl = '';

        if (workspaceRoot) {
            libraryUrl = String(workspaceRoot.getAttribute('data-bbai-library-url') || '');
            missingLibraryUrl = String(workspaceRoot.getAttribute('data-bbai-missing-library-url') || '');
        }

        if (!libraryUrl && dashboardRoot) {
            libraryUrl = String(dashboardRoot.getAttribute('data-bbai-library-url') || '');
            missingLibraryUrl = String(dashboardRoot.getAttribute('data-bbai-missing-library-url') || '');
        }

        libraryUrl = libraryUrl || getAltLibraryAdminUrl();
        missingLibraryUrl = missingLibraryUrl || appendQueryParams(libraryUrl, { status: 'missing' });

        return {
            currentPage: currentPage,
            isAltLibraryPage: isAltLibraryPage,
            libraryUrl: libraryUrl,
            missingLibraryUrl: missingLibraryUrl,
            hasResultsSection: !!resultsSection,
            resultsSection: resultsSection,
            resultsHeading: getScanResultsHeading(resultsSection),
            trigger: trigger || null
        };
    }

    function buildScanFeedbackPresentation(payload, context) {
        var scanContext = context || getScanFeedbackContext(null);
        var summary = getScanResultSummary(payload);
        var presentation = {
            title: __('Scan complete', 'beepbeep-ai-alt-text-generator'),
            body: '',
            detail: '',
            announcement: '',
            hasIssues: summary.hasIssues,
            state: summary.hasIssues ? 'attention' : 'clear',
            summary: summary,
            primaryAction: '',
            primaryLabel: '',
            primaryUrl: ''
        };

        if (summary.hasIssues) {
            presentation.body = buildScanIssueMessage(summary.issueCount);
            presentation.detail = scanContext.isAltLibraryPage
                ? __('Review them below in the ALT Library.', 'beepbeep-ai-alt-text-generator')
                : '';
            presentation.announcement = presentation.title + '. ' + presentation.body;

            if (scanContext.isAltLibraryPage) {
                if (scanContext.hasResultsSection) {
                    presentation.primaryAction = 'jump-results';
                    presentation.primaryLabel = __('View results', 'beepbeep-ai-alt-text-generator');
                }
            } else {
                presentation.primaryAction = 'open-library';
                presentation.primaryLabel = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
                presentation.primaryUrl = buildScanReviewUrl(summary, scanContext);
            }

            return presentation;
        }

        presentation.body = __('All scanned images already include ALT text.', 'beepbeep-ai-alt-text-generator');
        presentation.announcement = presentation.title + '. ' + presentation.body;

        return presentation;
    }

    function buildDashboardScanMessage(payload, context) {
        return buildScanFeedbackPresentation(payload, context).announcement;
    }

    function buildDashboardGenerationMessage(source, successes, failures) {
        var successCount = Math.max(0, parseInt(successes, 10) || 0);
        var failureCount = Math.max(0, parseInt(failures, 10) || 0);
        var baseMessage = '';
        if (source === 'fix-all-issues') {
            baseMessage = sprintf(
                _n(
                    '%s image optimized automatically.',
                    '%s images optimized automatically.',
                    successCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                successCount.toLocaleString()
            );
        } else if (source === 'regenerate-weak') {
            baseMessage = sprintf(
                _n(
                    '%s weak ALT description improved.',
                    '%s weak ALT descriptions improved.',
                    successCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                successCount.toLocaleString()
            );
        } else if (source === 'regenerate-all') {
            baseMessage = sprintf(
                _n(
                    '%s ALT description improved.',
                    '%s ALT descriptions improved.',
                    successCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                successCount.toLocaleString()
            );
        } else if (source === 'selected') {
            baseMessage = sprintf(
                _n(
                    '%s selected image processed successfully.',
                    '%s selected images processed successfully.',
                    successCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                successCount.toLocaleString()
            );
        } else {
            baseMessage = sprintf(
                _n(
                    '%s image processed successfully.',
                    '%s images processed successfully.',
                    successCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                successCount.toLocaleString()
            );
        }

        if (successCount <= 0 && failureCount > 0) {
            return __('Generation failed. Please try again.', 'beepbeep-ai-alt-text-generator');
        }

        if (failureCount > 0) {
            return baseMessage + ' ' + sprintf(
                _n('%s failed.', '%s failed.', failureCount, 'beepbeep-ai-alt-text-generator'),
                failureCount.toLocaleString()
            );
        }

        return baseMessage;
    }

    function dispatchDashboardFeedback(type, message, options) {
        var detail = {
            type: type || 'info',
            message: message || '',
            duration: options && options.duration ? parseInt(options.duration, 10) || 0 : 0
        };
        var shouldToast = !(options && options.skipToast);

        try {
            document.dispatchEvent(new CustomEvent('bbai:dashboard-feedback', { detail: detail }));
        } catch (feedbackError) {
            // Ignore feedback dispatch failures.
        }

        if (!shouldToast || !detail.message) {
            return;
        }

        if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
            window.bbaiPushToast(detail.type, detail.message, { duration: 4500 });
        } else if (detail.type === 'error' && window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error(detail.message);
        }
    }

    function getAltLibraryAdminUrl() {
        var adminBase = (window.bbai_ajax && window.bbai_ajax.admin_url) ||
            (window.bbai_env && window.bbai_env.admin_url) ||
            '';

        if (adminBase) {
            adminBase = String(adminBase).replace(/admin\.php.*$/i, '');
            if (adminBase.charAt(adminBase.length - 1) !== '/') {
                adminBase += '/';
            }

            return adminBase + 'admin.php?page=bbai-library';
        }

        return 'admin.php?page=bbai-library';
    }

    function showAltGenerationToast(summary) {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        var successCount = Math.max(0, parseInt(summary.successes, 10) || parseInt(summary.processed, 10) || 0);
        var failureCount = Math.max(0, parseInt(summary.failures, 10) || 0);
        var skippedCount = Math.max(0, parseInt(summary.skipped, 10) || 0);
        var title = __('ALT text generated', 'beepbeep-ai-alt-text-generator');
        var type = (failureCount > 0 || skippedCount > 0) ? 'warning' : 'success';
        var message = '';

        if (successCount <= 0 && failureCount > 0) {
            title = __('ALT generation failed', 'beepbeep-ai-alt-text-generator');
            type = 'error';
            message = __('No images were processed successfully. Review the ALT Library for details.', 'beepbeep-ai-alt-text-generator');
        } else if (skippedCount > 0) {
            title = __('ALT text generated with skipped images', 'beepbeep-ai-alt-text-generator');
            message = sprintf(
                _n('%1$s image processed, %2$s skipped.', '%1$s images processed, %2$s skipped.', successCount, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(successCount),
                formatDashboardNumber(skippedCount)
            );
            if (failureCount > 0) {
                message += ' ' + sprintf(
                    _n('%s failed.', '%s failed.', failureCount, 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(failureCount)
                );
            }
        } else if (failureCount > 0) {
            title = __('ALT text generated with issues', 'beepbeep-ai-alt-text-generator');
            message = sprintf(
                _n('%1$s image processed, %2$s failed.', '%1$s images processed, %2$s failed.', successCount, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(successCount),
                formatDashboardNumber(failureCount)
            );
        } else {
            message = sprintf(
                _n('%s image processed successfully', '%s images processed successfully', successCount, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(successCount)
            );
        }

        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast({
                type: type,
                title: title,
                message: message,
                duration: 4000,
                action: {
                    label: __('View in ALT Library', 'beepbeep-ai-alt-text-generator'),
                    url: getAltLibraryAdminUrl()
                }
            });
            return;
        }

        if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
            window.bbaiPushToast(type, message, {
                title: title,
                duration: 4000,
                actionLabel: __('View in ALT Library', 'beepbeep-ai-alt-text-generator'),
                url: getAltLibraryAdminUrl()
            });
        }
    }

    var bbaiDashboardScanModalState = {
        node: null,
        keyHandlerBound: false,
        lastTrigger: null,
        isOpen: false,
        isLoading: false,
        loadingIntervalId: 0,
        loadingStartedAt: 0,
        loadingExpectedDuration: 0,
        loadingTotalImages: 0,
        loadingStageText: ''
    };

    function clearDashboardScanLoadingInterval() {
        if (bbaiDashboardScanModalState.loadingIntervalId) {
            window.clearInterval(bbaiDashboardScanModalState.loadingIntervalId);
            bbaiDashboardScanModalState.loadingIntervalId = 0;
        }

        bbaiDashboardScanModalState.loadingStartedAt = 0;
        bbaiDashboardScanModalState.loadingExpectedDuration = 0;
        bbaiDashboardScanModalState.loadingTotalImages = 0;
        bbaiDashboardScanModalState.loadingStageText = '';
    }

    function getDashboardScanTotalImages() {
        var root = getDashboardRootNode();
        if (!root) {
            return 0;
        }

        return Math.max(0, parseInt(root.getAttribute('data-bbai-total-count'), 10) || 0);
    }

    function getDashboardScanExpectedDuration(totalImages) {
        var imageCount = Math.max(0, parseInt(totalImages, 10) || 0);
        return Math.min(45000, Math.max(6500, 5000 + (imageCount * 14)));
    }

    function getDashboardScanEstimatedProgress(elapsedMs, expectedDurationMs) {
        var elapsed = Math.max(0, parseInt(elapsedMs, 10) || 0);
        var expected = Math.max(1, parseInt(expectedDurationMs, 10) || 1);
        var progress = Math.round((elapsed / expected) * 100);

        if (elapsed <= 0) {
            return 8;
        }

        return Math.min(96, Math.max(8, progress));
    }

    function getDashboardScanStageText(progressPercent, elapsedMs, expectedDurationMs) {
        var progress = Math.max(0, parseInt(progressPercent, 10) || 0);
        var elapsed = Math.max(0, parseInt(elapsedMs, 10) || 0);
        var expected = Math.max(1, parseInt(expectedDurationMs, 10) || 1);

        if (elapsed > expected * 1.2) {
            return __('Still scanning your media library. Larger libraries can take longer.', 'beepbeep-ai-alt-text-generator');
        }

        if (progress < 30) {
            return __('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator');
        }

        if (progress < 60) {
            return __('Reviewing existing ALT descriptions.', 'beepbeep-ai-alt-text-generator');
        }

        if (progress < 85) {
            return __('Checking description quality and review signals.', 'beepbeep-ai-alt-text-generator');
        }

        return __('Preparing your coverage summary.', 'beepbeep-ai-alt-text-generator');
    }

    function getDashboardScanLoadingNote(totalImages, elapsedMs, expectedDurationMs) {
        var imageCount = Math.max(0, parseInt(totalImages, 10) || 0);
        var elapsed = Math.max(0, parseInt(elapsedMs, 10) || 0);
        var expected = Math.max(1, parseInt(expectedDurationMs, 10) || 1);

        if (imageCount > 0) {
            if (elapsed > expected * 1.2) {
                return sprintf(
                    __('Still working through %s images. Larger libraries can take longer.', 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(imageCount)
                );
            }

            return sprintf(
                __('Scanning %s images in your media library.', 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(imageCount)
            );
        }

        return elapsed > expected * 1.2
            ? __('Still scanning your media library. Larger libraries can take longer.', 'beepbeep-ai-alt-text-generator')
            : __('Scanning your media library now.', 'beepbeep-ai-alt-text-generator');
    }

    function formatDashboardScanElapsed(elapsedMs) {
        var totalSeconds = Math.max(0, Math.round((parseInt(elapsedMs, 10) || 0) / 1000));
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;

        if (minutes > 0) {
            return sprintf(
                __('Elapsed: %1$sm %2$ss', 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(minutes),
                formatDashboardNumber(seconds)
            );
        }

        return sprintf(
            __('Elapsed: %ss', 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(totalSeconds)
        );
    }

    function updateDashboardScanLoadingFeedback(modal, progressPayload) {
        var targetModal = modal || bbaiDashboardScanModalState.node;
        var description;
        var loadingCopy;
        var progressFill;
        var progressLabel;
        var elapsedLabel;
        var loadingNote;
        var elapsedMs;
        var progressPercent;
        var stageText;
        var noteText;

        if (!targetModal || !bbaiDashboardScanModalState.loadingStartedAt) {
            return;
        }

        description = targetModal.querySelector('[data-bbai-dashboard-scan-description]');
        loadingCopy = targetModal.querySelector('[data-bbai-dashboard-scan-loading-copy]');
        progressFill = targetModal.querySelector('[data-bbai-dashboard-scan-progress]');
        progressLabel = targetModal.querySelector('[data-bbai-dashboard-scan-progress-label]');
        elapsedLabel = targetModal.querySelector('[data-bbai-dashboard-scan-elapsed]');
        loadingNote = targetModal.querySelector('[data-bbai-dashboard-scan-loading-note]');

        elapsedMs = Date.now() - bbaiDashboardScanModalState.loadingStartedAt;
        if (progressPayload && typeof progressPayload === 'object') {
            var processedImages = Math.max(0, parseInt(progressPayload.processed_images, 10) || 0);
            var totalImages = Math.max(0, parseInt(progressPayload.total_images, 10) || 0);

            if (totalImages > 0) {
                bbaiDashboardScanModalState.loadingTotalImages = totalImages;
            }

            progressPercent = Math.max(0, Math.min(100, parseInt(progressPayload.progress_percent, 10) || 0));
            stageText = progressPayload.stage_message
                ? String(progressPayload.stage_message)
                : getDashboardScanStageText(progressPercent, elapsedMs, bbaiDashboardScanModalState.loadingExpectedDuration);
            noteText = totalImages > 0
                ? sprintf(
                    __('Scanned %1$s of %2$s images.', 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(processedImages),
                    formatDashboardNumber(totalImages)
                )
                : getDashboardScanLoadingNote(bbaiDashboardScanModalState.loadingTotalImages, elapsedMs, bbaiDashboardScanModalState.loadingExpectedDuration);
        } else {
            progressPercent = getDashboardScanEstimatedProgress(elapsedMs, bbaiDashboardScanModalState.loadingExpectedDuration);
            stageText = getDashboardScanStageText(progressPercent, elapsedMs, bbaiDashboardScanModalState.loadingExpectedDuration);
            noteText = getDashboardScanLoadingNote(bbaiDashboardScanModalState.loadingTotalImages, elapsedMs, bbaiDashboardScanModalState.loadingExpectedDuration);
        }

        if (description) {
            description.textContent = noteText;
        }

        if (loadingCopy && bbaiDashboardScanModalState.loadingStageText !== stageText) {
            loadingCopy.textContent = stageText;
            bbaiDashboardScanModalState.loadingStageText = stageText;
        }

        if (progressFill) {
            progressFill.style.width = progressPercent + '%';
        }

        if (progressLabel) {
            if (progressPayload && typeof progressPayload === 'object' && Math.max(0, parseInt(progressPayload.total_images, 10) || 0) > 0) {
                progressLabel.textContent = sprintf(
                    __('%1$s%% complete', 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(progressPercent)
                );
            } else {
                progressLabel.textContent = sprintf(
                    __('Estimated progress: %s%%', 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(progressPercent)
                );
            }
        }

        if (elapsedLabel) {
            elapsedLabel.textContent = formatDashboardScanElapsed(elapsedMs);
        }

        if (loadingNote) {
            if (progressPayload && typeof progressPayload === 'object' && String(progressPayload.status || '') === 'finalizing') {
                loadingNote.textContent = __('Finalizing scan results and refreshing your dashboard.', 'beepbeep-ai-alt-text-generator');
            } else {
                loadingNote.textContent = bbaiDashboardScanModalState.loadingTotalImages > 250
                    ? __('Large libraries can take longer to scan.', 'beepbeep-ai-alt-text-generator')
                    : __('The scan will update this window as soon as results are ready.', 'beepbeep-ai-alt-text-generator');
            }
        }
    }

    function startDashboardScanLoadingFeedback(modal) {
        clearDashboardScanLoadingInterval();

        bbaiDashboardScanModalState.loadingStartedAt = Date.now();
        bbaiDashboardScanModalState.loadingTotalImages = getDashboardScanTotalImages();
        bbaiDashboardScanModalState.loadingExpectedDuration = getDashboardScanExpectedDuration(bbaiDashboardScanModalState.loadingTotalImages);
        bbaiDashboardScanModalState.loadingStageText = '';

        updateDashboardScanLoadingFeedback(modal);

        bbaiDashboardScanModalState.loadingIntervalId = window.setInterval(function() {
            updateDashboardScanLoadingFeedback(modal);
        }, 1000);
    }

    function getFocusableElements(container) {
        if (!container || !container.querySelectorAll) {
            return [];
        }

        return Array.prototype.slice.call(
            container.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter(function(element) {
            if (!element) {
                return false;
            }

            if (element.hidden || element.getAttribute('aria-hidden') === 'true') {
                return false;
            }

            if (element.offsetParent === null && element !== document.activeElement) {
                return false;
            }

            return true;
        });
    }

    function trapFocusWithin(container, event) {
        var focusableElements = getFocusableElements(container);
        if (!focusableElements.length) {
            event.preventDefault();
            if (container && typeof container.focus === 'function') {
                container.focus();
            }
            return;
        }

        var firstElement = focusableElements[0];
        var lastElement = focusableElements[focusableElements.length - 1];
        var activeElement = document.activeElement;

        if (event.shiftKey && (activeElement === firstElement || activeElement === container)) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (!event.shiftKey && activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    }

    function focusDashboardScanModalTarget(modal) {
        if (!modal) {
            return;
        }

        var target = modal.querySelector('[data-bbai-dashboard-scan-primary]:not([hidden]):not([disabled])') ||
            modal.querySelector('.bbai-dashboard-scan-modal__close') ||
            modal.querySelector('[data-bbai-dashboard-scan-dismiss]') ||
            modal.querySelector('.bbai-dashboard-scan-modal__dialog');

        if (target && typeof target.focus === 'function') {
            target.focus();
        }
    }

    function ensureDashboardScanModal() {
        if (bbaiDashboardScanModalState.node && document.body && document.body.contains(bbaiDashboardScanModalState.node)) {
            return bbaiDashboardScanModalState.node;
        }

        if (!document.body) {
            return null;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'bbai-dashboard-scan-modal';
        wrapper.id = 'bbai-dashboard-scan-modal';
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.innerHTML =
            '<div class="bbai-dashboard-scan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbai-dashboard-scan-modal-title" aria-describedby="bbai-dashboard-scan-modal-description" tabindex="-1">' +
                '<button type="button" class="bbai-dashboard-scan-modal__close" aria-label="' + escapeHtml(__('Close scan dialog', 'beepbeep-ai-alt-text-generator')) + '">' +
                    '<span aria-hidden="true">&times;</span>' +
                '</button>' +
                '<div class="bbai-dashboard-scan-modal__state">' +
                    '<div class="bbai-dashboard-scan-modal__icon" data-bbai-dashboard-scan-icon aria-hidden="true"></div>' +
                    '<h3 id="bbai-dashboard-scan-modal-title" class="bbai-dashboard-scan-modal__title" data-bbai-dashboard-scan-title>' + escapeHtml(__('Scanning media library...', 'beepbeep-ai-alt-text-generator')) + '</h3>' +
                    '<p id="bbai-dashboard-scan-modal-description" class="bbai-dashboard-scan-modal__description" data-bbai-dashboard-scan-description>' + escapeHtml(__('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                    '<div class="bbai-dashboard-scan-modal__loading" data-bbai-dashboard-scan-loading>' +
                        '<span class="bbai-spinner bbai-spinner-lg bbai-dashboard-scan-modal__spinner" aria-hidden="true"></span>' +
                        '<p class="bbai-dashboard-scan-modal__loading-copy" data-bbai-dashboard-scan-loading-copy>' + escapeHtml(__('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                        '<div class="bbai-dashboard-scan-modal__loading-meta">' +
                            '<div class="bbai-dashboard-scan-modal__loading-progress" aria-hidden="true">' +
                                '<span class="bbai-dashboard-scan-modal__loading-progress-fill" data-bbai-dashboard-scan-progress></span>' +
                            '</div>' +
                            '<div class="bbai-dashboard-scan-modal__loading-stats">' +
                                '<span class="bbai-dashboard-scan-modal__loading-stat" data-bbai-dashboard-scan-progress-label>' + escapeHtml(__('Estimated progress: 8%', 'beepbeep-ai-alt-text-generator')) + '</span>' +
                                '<span class="bbai-dashboard-scan-modal__loading-stat" data-bbai-dashboard-scan-elapsed>' + escapeHtml(__('Elapsed: 0s', 'beepbeep-ai-alt-text-generator')) + '</span>' +
                            '</div>' +
                            '<p class="bbai-dashboard-scan-modal__loading-note" data-bbai-dashboard-scan-loading-note>' + escapeHtml(__('The scan will update this window as soon as results are ready.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bbai-dashboard-scan-modal__result" data-bbai-dashboard-scan-result aria-live="polite" hidden>' +
                        '<div class="bbai-dashboard-scan-modal__metrics" data-bbai-dashboard-scan-stats hidden>' +
                            '<span class="bbai-dashboard-scan-modal__metric">' +
                                '<strong class="bbai-dashboard-scan-modal__metric-value" data-bbai-dashboard-scan-missing>0</strong>' +
                                '<span class="bbai-dashboard-scan-modal__metric-label">' + escapeHtml(__('Missing ALT', 'beepbeep-ai-alt-text-generator')) + '</span>' +
                            '</span>' +
                            '<span class="bbai-dashboard-scan-modal__metric">' +
                                '<strong class="bbai-dashboard-scan-modal__metric-value" data-bbai-dashboard-scan-weak>0</strong>' +
                                '<span class="bbai-dashboard-scan-modal__metric-label">' + escapeHtml(__('Weak ALT', 'beepbeep-ai-alt-text-generator')) + '</span>' +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="bbai-dashboard-scan-modal__footer">' +
                    '<button type="button" class="bbai-dashboard-scan-modal__button bbai-dashboard-scan-modal__button--primary" data-bbai-dashboard-scan-primary hidden></button>' +
                    '<button type="button" class="bbai-dashboard-scan-modal__button bbai-dashboard-scan-modal__button--secondary" data-bbai-dashboard-scan-dismiss>' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                '</div>' +
            '</div>';

        wrapper.addEventListener('click', function(event) {
            var target = event.target;
            if (!target) {
                return;
            }

            var closeButton = target.closest('.bbai-dashboard-scan-modal__close, [data-bbai-dashboard-scan-dismiss]');
            if (closeButton) {
                event.preventDefault();
                closeDashboardScanModal();
                return;
            }

            var primaryButton = target.closest('[data-bbai-dashboard-scan-primary]');
            if (primaryButton) {
                event.preventDefault();
                if (bbaiDashboardScanModalState.isLoading) {
                    return;
                }

                var action = primaryButton.getAttribute('data-bbai-dashboard-scan-action') || '';
                if (action === 'retry') {
                    handleOpportunityScan.call(bbaiDashboardScanModalState.lastTrigger || primaryButton, { preventDefault: function() {} });
                    return;
                }

                if (action === 'open-library') {
                    var targetUrl = primaryButton.getAttribute('data-bbai-dashboard-scan-url') || getAltLibraryAdminUrl();
                    closeDashboardScanModal({ restoreFocus: false });
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    }
                    return;
                }

                if (action === 'jump-results') {
                    closeDashboardScanModal({ restoreFocus: false });
                    scrollToAltTable({
                        summary: getScanResultSummary({
                            images_missing_alt: primaryButton.getAttribute('data-bbai-dashboard-scan-missing') || '0',
                            needs_review_count: primaryButton.getAttribute('data-bbai-dashboard-scan-weak') || '0'
                        })
                    });
                    return;
                }

                return;
            }

            if (target === wrapper) {
                closeDashboardScanModal();
            }
        });

        document.body.appendChild(wrapper);
        bbaiDashboardScanModalState.node = wrapper;
        return wrapper;
    }

    function handleDashboardScanModalKeys(event) {
        if (!bbaiDashboardScanModalState.isOpen || !event) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDashboardScanModal();
            return;
        }

        if (event.key === 'Tab') {
            var modal = bbaiDashboardScanModalState.node;
            var dialog = modal ? modal.querySelector('.bbai-dashboard-scan-modal__dialog') : null;
            if (dialog) {
                trapFocusWithin(dialog, event);
            }
        }
    }

    function openDashboardScanModal(trigger) {
        var modal = ensureDashboardScanModal();
        if (!modal) {
            return null;
        }

        bbaiDashboardScanModalState.lastTrigger = trigger || document.activeElement;
        bbaiDashboardScanModalState.isOpen = true;
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        if (document.body) {
            document.body.classList.add('bbai-modal-open');
            document.body.style.overflow = 'hidden';
        }
        if (document.documentElement) {
            document.documentElement.style.overflow = 'hidden';
        }

        if (!bbaiDashboardScanModalState.keyHandlerBound) {
            document.addEventListener('keydown', handleDashboardScanModalKeys, true);
            bbaiDashboardScanModalState.keyHandlerBound = true;
        }

        window.setTimeout(function() {
            focusDashboardScanModalTarget(modal);
        }, 10);

        return modal;
    }

    function closeDashboardScanModal(options) {
        var modal = bbaiDashboardScanModalState.node;
        var shouldRestoreFocus = !(options && options.restoreFocus === false);
        if (!modal) {
            return;
        }

        clearDashboardScanLoadingInterval();
        bbaiDashboardScanModalState.isOpen = false;
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        clearBodyScrollLocks();

        if (bbaiDashboardScanModalState.keyHandlerBound) {
            document.removeEventListener('keydown', handleDashboardScanModalKeys, true);
            bbaiDashboardScanModalState.keyHandlerBound = false;
        }

        if (shouldRestoreFocus && bbaiDashboardScanModalState.lastTrigger && typeof bbaiDashboardScanModalState.lastTrigger.focus === 'function') {
            bbaiDashboardScanModalState.lastTrigger.focus();
        }
    }

    function setDashboardScanModalMode(mode, payload, context) {
        var modal = ensureDashboardScanModal();
        if (!modal) {
            return;
        }

        var icon = modal.querySelector('[data-bbai-dashboard-scan-icon]');
        var title = modal.querySelector('[data-bbai-dashboard-scan-title]');
        var description = modal.querySelector('[data-bbai-dashboard-scan-description]');
        var loadingBlock = modal.querySelector('[data-bbai-dashboard-scan-loading]');
        var loadingCopy = modal.querySelector('[data-bbai-dashboard-scan-loading-copy]');
        var progressFill = modal.querySelector('[data-bbai-dashboard-scan-progress]');
        var progressLabel = modal.querySelector('[data-bbai-dashboard-scan-progress-label]');
        var elapsedLabel = modal.querySelector('[data-bbai-dashboard-scan-elapsed]');
        var loadingNote = modal.querySelector('[data-bbai-dashboard-scan-loading-note]');
        var resultBlock = modal.querySelector('[data-bbai-dashboard-scan-result]');
        var statsBlock = modal.querySelector('[data-bbai-dashboard-scan-stats]');
        var missingNode = modal.querySelector('[data-bbai-dashboard-scan-missing]');
        var weakNode = modal.querySelector('[data-bbai-dashboard-scan-weak]');
        var primaryButton = modal.querySelector('[data-bbai-dashboard-scan-primary]');

        modal.setAttribute('data-bbai-dashboard-scan-mode', mode);
        bbaiDashboardScanModalState.isLoading = mode === 'loading';

        if (loadingBlock) {
            loadingBlock.hidden = mode !== 'loading';
        }
        if (resultBlock) {
            resultBlock.hidden = mode === 'loading';
        }

        if (mode === 'loading') {
            startDashboardScanLoadingFeedback(modal);
            if (icon) {
                icon.className = 'bbai-dashboard-scan-modal__icon bbai-dashboard-scan-modal__icon--loading';
                icon.textContent = '';
            }
            if (title) {
                title.textContent = __('Scanning media library...', 'beepbeep-ai-alt-text-generator');
            }
            if (description) {
                description.textContent = getDashboardScanLoadingNote(
                    bbaiDashboardScanModalState.loadingTotalImages,
                    0,
                    bbaiDashboardScanModalState.loadingExpectedDuration
                );
            }
            if (loadingCopy) {
                loadingCopy.textContent = __('Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator');
            }
            if (progressFill) {
                progressFill.style.width = '8%';
            }
            if (progressLabel) {
                progressLabel.textContent = __('Estimated progress: 8%', 'beepbeep-ai-alt-text-generator');
            }
            if (elapsedLabel) {
                elapsedLabel.textContent = __('Elapsed: 0s', 'beepbeep-ai-alt-text-generator');
            }
            if (loadingNote) {
                loadingNote.textContent = bbaiDashboardScanModalState.loadingTotalImages > 250
                    ? __('Large libraries can take longer to scan.', 'beepbeep-ai-alt-text-generator')
                    : __('The scan will update this window as soon as results are ready.', 'beepbeep-ai-alt-text-generator');
            }
            if (statsBlock) {
                statsBlock.hidden = true;
            }
            if (primaryButton) {
                primaryButton.hidden = true;
                primaryButton.textContent = '';
                primaryButton.removeAttribute('data-bbai-dashboard-scan-action');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-url');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-missing');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-weak');
            }
            return;
        }

        if (mode === 'error') {
            clearDashboardScanLoadingInterval();
            if (icon) {
                icon.className = 'bbai-dashboard-scan-modal__icon bbai-dashboard-scan-modal__icon--error';
                icon.textContent = '!';
            }
            if (title) {
                title.textContent = __('Scan failed', 'beepbeep-ai-alt-text-generator');
            }
            if (description) {
                description.textContent = payload && payload.message
                    ? String(payload.message)
                    : __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator');
            }
            if (statsBlock) {
                statsBlock.hidden = true;
            }
            if (primaryButton) {
                primaryButton.hidden = false;
                primaryButton.textContent = __('Try again', 'beepbeep-ai-alt-text-generator');
                primaryButton.setAttribute('data-bbai-dashboard-scan-action', 'retry');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-url');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-missing');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-weak');
            }
            window.setTimeout(function() {
                focusDashboardScanModalTarget(modal);
            }, 10);
            return;
        }

        var resultPayload = payload && typeof payload === 'object' ? payload : {};
        var presentation = buildScanFeedbackPresentation(resultPayload, context || getScanFeedbackContext(bbaiDashboardScanModalState.lastTrigger));
        var missing = presentation.summary.missing;
        var weak = presentation.summary.weak;
        var hasIssues = presentation.hasIssues;
        clearDashboardScanLoadingInterval();

        if (icon) {
            icon.className = 'bbai-dashboard-scan-modal__icon ' + (hasIssues ? 'bbai-dashboard-scan-modal__icon--warning' : 'bbai-dashboard-scan-modal__icon--success');
            icon.textContent = hasIssues ? '!' : '✓';
        }
        if (title) {
            title.textContent = presentation.title;
        }
        if (description) {
            description.textContent = presentation.detail
                ? presentation.body + ' ' + presentation.detail
                : presentation.body;
        }
        if (statsBlock) {
            statsBlock.hidden = !hasIssues;
        }
        if (missingNode) {
            missingNode.textContent = missing.toLocaleString();
        }
        if (weakNode) {
            weakNode.textContent = weak.toLocaleString();
        }
        if (primaryButton) {
            if (presentation.primaryAction && presentation.primaryLabel) {
                primaryButton.hidden = false;
                primaryButton.textContent = presentation.primaryLabel;
                primaryButton.setAttribute('data-bbai-dashboard-scan-action', presentation.primaryAction);
                primaryButton.setAttribute('data-bbai-dashboard-scan-missing', String(missing));
                primaryButton.setAttribute('data-bbai-dashboard-scan-weak', String(weak));
                if (presentation.primaryUrl) {
                    primaryButton.setAttribute('data-bbai-dashboard-scan-url', presentation.primaryUrl);
                } else {
                    primaryButton.removeAttribute('data-bbai-dashboard-scan-url');
                }
            } else {
                primaryButton.hidden = true;
                primaryButton.textContent = '';
                primaryButton.removeAttribute('data-bbai-dashboard-scan-action');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-url');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-missing');
                primaryButton.removeAttribute('data-bbai-dashboard-scan-weak');
            }
        }

        window.setTimeout(function() {
            focusDashboardScanModalTarget(modal);
        }, 10);
    }

    function getOpportunityScannerGuide(payload) {
        var root = getDashboardRootNode();
        var libraryUrl = root ? String(root.getAttribute('data-bbai-library-url') || '') : '';
        var missing = Math.max(0, parseInt(payload && payload.images_missing_alt, 10) || 0);
        var weak = Math.max(0, parseInt(payload && payload.needs_review_count, 10) || 0);
        var filenameOnly = Math.max(0, parseInt(payload && payload.filename_only_count, 10) || 0);
        var duplicate = Math.max(0, parseInt(payload && payload.duplicate_alt_count, 10) || 0);
        var totalImages = Math.max(0, parseInt(payload && payload.total_images, 10) || parseInt(payload && payload.total, 10) || 0);
        var optimizedOnly = totalImages > 0 && missing === 0 && weak === 0 && filenameOnly === 0 && duplicate === 0;
        var generateDisabled = missing === 0;
        var workflowSteps = [
            {
                iconClass: 'dashicons-search',
                iconText: '',
                title: __('Scan media library', 'beepbeep-ai-alt-text-generator'),
                description: __('Find images missing ALT text.', 'beepbeep-ai-alt-text-generator'),
                buttonLabel: __('Scan media library', 'beepbeep-ai-alt-text-generator'),
                buttonStyle: 'primary',
                buttonType: 'button',
                buttonAction: 'scan-opportunity'
            },
            {
                iconClass: '',
                iconText: '⚡',
                title: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                description: __('Create optimized ALT descriptions automatically.', 'beepbeep-ai-alt-text-generator'),
                buttonLabel: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                buttonStyle: 'secondary',
                buttonType: 'button',
                buttonAction: 'show-generate-alt-modal',
                disabled: generateDisabled,
                helper: generateDisabled ? __('No missing ALT text found.', 'beepbeep-ai-alt-text-generator') : ''
            },
            {
                iconClass: 'dashicons-edit',
                iconText: '',
                title: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                description: __('Edit or approve generated ALT text anytime.', 'beepbeep-ai-alt-text-generator'),
                buttonLabel: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                buttonStyle: 'secondary',
                buttonType: 'link',
                buttonHref: libraryUrl
            }
        ];

        if (!optimizedOnly) {
            return {
                summary: __('Follow this simple workflow to use BeepBeep AI:', 'beepbeep-ai-alt-text-generator'),
                label: '',
                steps: workflowSteps
            };
        }

        return {
            summary: __('Your media library is fully optimized.', 'beepbeep-ai-alt-text-generator'),
            label: __('When new images are uploaded:', 'beepbeep-ai-alt-text-generator'),
            steps: workflowSteps
        };
    }

    function renderOpportunityScannerGuideSteps(steps) {
        return (steps || []).map(function(step) {
            var iconClass = step && step.iconClass ? String(step.iconClass) : '';
            var iconText = step && step.iconText ? String(step.iconText) : '';
            var iconClasses = 'bbai-opportunity-scanner__step-icon';
            var buttonType = step && step.buttonType ? String(step.buttonType) : 'button';
            var buttonStyle = step && step.buttonStyle === 'primary' ? 'bbai-workflow-step__btn--primary' : 'bbai-workflow-step__btn--secondary';
            var buttonClasses = 'bbai-workflow-step__btn bbai-opportunity-scanner__step-button ' + buttonStyle;
            var buttonHtml = '';
            var helperHtml = '';
            var buttonLabel = step && step.buttonLabel ? String(step.buttonLabel) : '';
            var helperText = step && step.helper ? String(step.helper) : '';
            var buttonAction = step && step.buttonAction ? String(step.buttonAction) : '';
            var buttonHref = step && step.buttonHref ? String(step.buttonHref) : '';
            var isDisabled = !!(step && step.disabled);

            if (iconClass) {
                iconClasses += ' dashicons ' + iconClass;
            }
            if (isDisabled) {
                buttonClasses += ' is-disabled';
            }
            if (buttonType === 'link') {
                buttonHtml = '<a href="' + escapeHtml(buttonHref || '#') + '" class="' + buttonClasses + '">' + escapeHtml(buttonLabel) + '</a>';
            } else {
                buttonHtml = '<button type="button" class="' + buttonClasses + '"' +
                    (buttonAction === 'scan-opportunity'
                        ? ' data-bbai-action="scan-opportunity"'
                        : ' data-action="' + escapeHtml(buttonAction) + '"') +
                    (isDisabled ? ' disabled aria-disabled="true"' : '') +
                    '>' + escapeHtml(buttonLabel) + '</button>';
            }
            if (helperText) {
                helperHtml = '<p class="bbai-opportunity-scanner__step-helper">' + escapeHtml(helperText) + '</p>';
            }

            return '' +
                '<li class="bbai-opportunity-scanner__step">' +
                    '<span class="' + iconClasses + '" aria-hidden="true">' + escapeHtml(iconText) + '</span>' +
                    '<div class="bbai-opportunity-scanner__step-content">' +
                        '<p class="bbai-opportunity-scanner__step-title">' + escapeHtml(step && step.title ? step.title : '') + '</p>' +
                        '<p class="bbai-opportunity-scanner__step-desc">' + escapeHtml(step && step.description ? step.description : '') + '</p>' +
                        buttonHtml +
                        helperHtml +
                    '</div>' +
                '</li>';
        }).join('');
    }

    function updateOpportunityScannerStats(payload) {
        var scanner = document.querySelector('.bbai-opportunity-scanner');
        if (!scanner || !payload || typeof payload !== 'object') {
            return;
        }

        var promptBlock = scanner.querySelector('[data-bbai-scan-prompt]');
        var resultsBlock = scanner.querySelector('[data-bbai-scan-results]');
        var summaryNode = scanner.querySelector('[data-bbai-usage-guide-summary]');
        var labelNode = scanner.querySelector('[data-bbai-usage-guide-label]');
        var stepsNode = scanner.querySelector('[data-bbai-usage-guide-steps]');
        var missing = Math.max(0, parseInt(payload.images_missing_alt, 10) || 0);
        var weak = Math.max(0, parseInt(payload.needs_review_count, 10) || 0);
        var filenameOnly = Math.max(0, parseInt(payload.filename_only_count, 10) || 0);
        var duplicate = Math.max(0, parseInt(payload.duplicate_alt_count, 10) || 0);
        var guide = getOpportunityScannerGuide({
            images_missing_alt: missing,
            needs_review_count: weak,
            filename_only_count: filenameOnly,
            duplicate_alt_count: duplicate,
            total_images: parseInt(payload.total_images, 10) || parseInt(payload.total, 10) || 0
        });

        if (summaryNode) {
            summaryNode.textContent = guide.summary || '';
        }

        if (labelNode) {
            labelNode.textContent = guide.label || '';
            labelNode.hidden = !guide.label;
        }

        if (stepsNode) {
            stepsNode.innerHTML = renderOpportunityScannerGuideSteps(guide.steps);
        }

        if (promptBlock) {
            promptBlock.setAttribute('hidden', '');
        }
        if (resultsBlock) {
            resultsBlock.removeAttribute('hidden');
        }
    }

    function dispatchDashboardStatsUpdated(payload) {
        var normalizedStats = normalizeDashboardStatsPayload(payload);
        var root = getDashboardRootNode();

        if (root) {
            root.setAttribute('data-bbai-total-count', String(normalizedStats.total_images));
            root.setAttribute('data-bbai-optimized-count', String(normalizedStats.optimized_count));
            root.setAttribute('data-bbai-missing-count', String(normalizedStats.images_missing_alt));
            root.setAttribute('data-bbai-weak-count', String(normalizedStats.needs_review_count));
        }

        var libraryWorkspaceRoot = getLibraryWorkspaceRoot();
        if (libraryWorkspaceRoot) {
            libraryWorkspaceRoot.setAttribute('data-bbai-total-count', String(normalizedStats.total_images));
            libraryWorkspaceRoot.setAttribute('data-bbai-optimized-count', String(normalizedStats.optimized_count));
            libraryWorkspaceRoot.setAttribute('data-bbai-missing-count', String(normalizedStats.images_missing_alt));
            libraryWorkspaceRoot.setAttribute('data-bbai-weak-count', String(normalizedStats.needs_review_count));
        }

        if (window.BBAI_DASH) {
            window.BBAI_DASH.stats = $.extend({}, window.BBAI_DASH.stats || {}, normalizedStats);
        }
        if (window.BBAI) {
            window.BBAI.stats = $.extend({}, window.BBAI.stats || {}, normalizedStats);
        }

        updateDashboardHero(normalizedStats);
        updateDashboardStatusCard(normalizedStats);
        updateDashboardPerformanceMetrics(normalizedStats, null);
        updateOpportunityScannerStats(normalizedStats);

        try {
            document.dispatchEvent(new CustomEvent('bbai:stats-updated', { detail: { stats: normalizedStats } }));
        } catch (statsError) {
            // Ignore DOM dispatch failures.
        }

        $(document).trigger('bbai:stats-updated', [{ stats: normalizedStats }]);

        try {
            window.dispatchEvent(new CustomEvent('bbai-stats-update', {
                detail: {
                    stats: normalizedStats,
                    usage: getUsageSnapshot(null)
                }
            }));
        } catch (windowStatsError) {
            // Ignore window dispatch failures.
        }

        try {
            window.localStorage.setItem('bbai-dashboard-stats-sync', JSON.stringify({
                stats: normalizedStats,
                usage: getUsageSnapshot(null),
                ts: Date.now()
            }));
        } catch (storageWriteError) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Failed to broadcast dashboard stats to other tabs.', storageWriteError);
        }
    }

    /**
     * Legacy fallback: counts rows in the DOM. The ALT Library workspace uses paginated rows,
     * so this must not drive filter chips there — use coverage stats on the workspace root instead.
     */
    function syncLibraryFilterCountsFromTable() {
        var tbody = document.getElementById('bbai-library-table-body');
        if (!tbody || !tbody.classList.contains('bbai-library-review-queue')) {
            return null;
        }

        var rows = tbody.querySelectorAll('.bbai-library-row[data-status]');
        var missingCount = 0;
        var weakCount = 0;
        var optimizedCount = 0;
        var i;
        for (i = 0; i < rows.length; i++) {
            var s = String(rows[i].getAttribute('data-status') || '');
            if (s === 'missing') {
                missingCount++;
            } else if (s === 'weak') {
                weakCount++;
            } else if (s === 'optimized') {
                optimizedCount++;
            }
        }

        var allSum = missingCount + weakCount + optimizedCount;
        return {
            all: allSum,
            weak: weakCount,
            missing: missingCount,
            optimized: optimizedCount,
            'needs-review': weakCount,
            needs_review: weakCount
        };
    }

    var LIBRARY_FILTER_ATTENTION_WINDOW_MS = 6000;
    var libraryFilterAttentionStartedAt = 0;
    var libraryFilterAttentionExpired = false;

    function clearLibraryFilterAttention() {
        document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]').forEach(function(button) {
            button.classList.remove('bbai-pill--attention');
        });
    }

    function startLibraryFilterAttentionWindow() {
        if (libraryFilterAttentionStartedAt > 0 || libraryFilterAttentionExpired) {
            return;
        }

        libraryFilterAttentionStartedAt = Date.now();
        window.setTimeout(function() {
            libraryFilterAttentionExpired = true;
            clearLibraryFilterAttention();
        }, LIBRARY_FILTER_ATTENTION_WINDOW_MS);
    }

    function isLibraryFilterAttentionWindowOpen() {
        if (libraryFilterAttentionExpired || libraryFilterAttentionStartedAt === 0) {
            return false;
        }

        return (Date.now() - libraryFilterAttentionStartedAt) < LIBRARY_FILTER_ATTENTION_WINDOW_MS;
    }

    function buildLibraryFilterAttentionCountsFromWorkspace() {
        var snapshot = readLibraryWorkspaceStatsFromRoot();
        if (!snapshot) {
            return null;
        }

        var missing = Math.max(0, parseOptionalNonNegativeInt(snapshot.images_missing_alt) || 0);
        var weak = Math.max(0, parseOptionalNonNegativeInt(snapshot.needs_review_count) || 0);
        var optimized = Math.max(0, parseOptionalNonNegativeInt(snapshot.optimized_count) || 0);
        var total = parseOptionalNonNegativeInt(snapshot.total_images);

        return {
            all: total !== null ? Math.max(0, total) : Math.max(0, missing + weak + optimized),
            missing: missing,
            weak: weak,
            optimized: optimized,
            'needs-review': weak,
            needs_review: weak
        };
    }

    function getLibraryFilterAttentionCount(button, counts) {
        var filter = normalizeLibraryStatusFilter(button.getAttribute('data-filter') || 'all');
        var countKeys = [filter];

        if (filter === 'weak') {
            countKeys.push('needs-review', 'needs_review');
        }

        if (counts && typeof counts === 'object') {
            for (var i = 0; i < countKeys.length; i++) {
                if (Object.prototype.hasOwnProperty.call(counts, countKeys[i])) {
                    var explicitCount = parseOptionalNonNegativeInt(counts[countKeys[i]]);
                    if (explicitCount !== null) {
                        return explicitCount;
                    }
                }
            }
        }

        var countNode = button.querySelector('.bbai-alt-review-filters__count, .bbai-filter-group__count');
        var rawCount = countNode ? countNode.textContent : button.textContent;
        var digitText = String(rawCount || '').replace(/[^0-9]+/g, '');
        if (digitText === '') {
            return 0;
        }

        var parsedCount = parseInt(digitText, 10);
        return Number.isFinite(parsedCount) && parsedCount > 0 ? parsedCount : 0;
    }

    function syncLibraryFilterAttentionState(counts) {
        var filterButtons = document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]');
        if (!filterButtons.length) {
            return;
        }

        startLibraryFilterAttentionWindow();

        if (!isLibraryFilterAttentionWindowOpen()) {
            clearLibraryFilterAttention();
            return;
        }

        filterButtons.forEach(function(button) {
            var filter = normalizeLibraryStatusFilter(button.getAttribute('data-filter') || 'all');
            var isActionable = filter === 'missing' || filter === 'weak';
            var shouldAnimate = isActionable && getLibraryFilterAttentionCount(button, counts) > 0;
            button.classList.toggle('bbai-pill--attention', shouldAnimate);
        });
    }

    function initLibraryFilterAttention() {
        if (!document.getElementById('bbai-review-filter-tabs')) {
            return;
        }

        syncLibraryFilterAttentionState(buildLibraryFilterAttentionCountsFromWorkspace());
    }

    function applyLibraryReviewFilterCountsObject(counts) {
        var filterButtons = document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]');
        if (!filterButtons.length || !counts || typeof counts !== 'object') {
            return;
        }

        filterButtons.forEach(function(button) {
            var filter = button.getAttribute('data-filter') || '';
            if (!Object.prototype.hasOwnProperty.call(counts, filter)) {
                return;
            }

            var baseLabel = button.getAttribute('data-bbai-filter-label');
            if (!baseLabel) {
                var labelNode = button.querySelector('.bbai-alt-review-filters__label, .bbai-filter-group__label');
                baseLabel = labelNode ? String(labelNode.textContent || '').trim() : button.textContent.replace(/\s*\([0-9,]+\)\s*$/, '').trim();
                button.setAttribute('data-bbai-filter-label', baseLabel);
            }

            var countNode = button.querySelector('.bbai-alt-review-filters__count, .bbai-filter-group__count');
            if (!countNode) {
                countNode = document.createElement('span');
                countNode.className = 'bbai-alt-review-filters__count';
                button.appendChild(countNode);
            }
            var labelNodeExisting = button.querySelector('.bbai-alt-review-filters__label, .bbai-filter-group__label');
            if (labelNodeExisting) {
                labelNodeExisting.textContent = baseLabel;
            }
            countNode.textContent = formatDashboardNumber(counts[filter]);
            var attention = (filter === 'missing' || filter === 'weak') && counts[filter] > 0;
            button.classList.toggle('bbai-filter-group__item--attention', attention);
            button.classList.toggle('bbai-alt-review-filters__btn--problem', attention);
        });

        syncLibraryFilterAttentionState(counts);
    }

    function updateLibraryReviewFilterCounts(payload) {
        var filterButtons = document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]');
        if (!filterButtons.length) {
            return;
        }

        var workspaceSnapshot = readLibraryWorkspaceStatsFromRoot();
        var mergedRaw = $.extend({}, workspaceSnapshot || {}, payload || {});
        var normalized = normalizeDashboardStatsPayload(mergedRaw);

        if (getLibraryWorkspaceRoot()) {
            var weakCount = Math.max(0, nonNegativeIntFromRawOrFallback(normalized, 'needs_review_count', {}, 'needs_review_count'));
            var missingCount = Math.max(0, nonNegativeFieldWithAlias(normalized, {}, 'images_missing_alt', 'missing'));
            var optimizedExplicit = Object.prototype.hasOwnProperty.call(normalized, 'optimized_count')
                ? parseOptionalNonNegativeInt(normalized.optimized_count)
                : null;
            var withAltForDerived = Math.max(0, nonNegativeFieldWithAlias(normalized, {}, 'images_with_alt', 'with_alt'));
            var optimizedCount = Math.max(
                0,
                optimizedExplicit !== null ? optimizedExplicit : Math.max(0, withAltForDerived - weakCount)
            );
            var totalImages = Math.max(0, nonNegativeFieldWithAlias(normalized, {}, 'total_images', 'total'));
            var allSum = totalImages > 0 ? totalImages : Math.max(0, optimizedCount + weakCount + missingCount);
            var workspaceRoot = getLibraryWorkspaceRoot();
            if (workspaceRoot) {
                workspaceRoot.setAttribute('data-bbai-total-count', String(allSum));
                workspaceRoot.setAttribute('data-bbai-missing-count', String(missingCount));
                workspaceRoot.setAttribute('data-bbai-weak-count', String(weakCount));
                workspaceRoot.setAttribute('data-bbai-optimized-count', String(optimizedCount));
            }
            applyLibraryReviewFilterCountsObject({
                all: allSum,
                weak: weakCount,
                missing: missingCount,
                optimized: optimizedCount,
                'needs-review': weakCount,
                needs_review: weakCount
            });
            renderLibraryWorkflowDecisionSurfaces();
            return;
        }

        var tableCounts = syncLibraryFilterCountsFromTable();
        if (tableCounts) {
            applyLibraryReviewFilterCountsObject(tableCounts);
            renderLibraryWorkflowDecisionSurfaces();
            return;
        }

        if (!payload || typeof payload !== 'object') {
            return;
        }

        var weakCountLegacy = Math.max(0, nonNegativeIntFromRawOrFallback(payload, 'needs_review_count', {}, 'needs_review_count'));
        var missingCountLegacy = Math.max(0, nonNegativeFieldWithAlias(payload, {}, 'images_missing_alt', 'missing'));
        var optimizedExplicitLegacy = Object.prototype.hasOwnProperty.call(payload, 'optimized_count')
            ? parseOptionalNonNegativeInt(payload.optimized_count)
            : null;
        var withAltForDerivedLegacy = Math.max(0, nonNegativeFieldWithAlias(payload, {}, 'images_with_alt', 'with_alt'));
        var optimizedCountLegacy = Math.max(
            0,
            optimizedExplicitLegacy !== null ? optimizedExplicitLegacy : Math.max(0, withAltForDerivedLegacy - weakCountLegacy)
        );
        var allSumLegacy = optimizedCountLegacy + weakCountLegacy + missingCountLegacy;
        applyLibraryReviewFilterCountsObject({
            all: allSumLegacy,
            weak: weakCountLegacy,
            missing: missingCountLegacy,
            optimized: optimizedCountLegacy,
            'needs-review': weakCountLegacy,
            needs_review: weakCountLegacy
        });
        renderLibraryWorkflowDecisionSurfaces();
    }

    function getLibraryWorkflowState(payload) {
        var totalImages = Math.max(0, parseInt(payload && payload.total_images, 10) || parseInt(payload && payload.total, 10) || 0);
        var missingCount = Math.max(0, parseInt(payload && payload.images_missing_alt, 10) || parseInt(payload && payload.missing, 10) || 0);
        var weakCount = Math.max(0, parseInt(payload && payload.needs_review_count, 10) || 0);
        var optimizedCount = Math.max(
            0,
            parseInt(payload && payload.optimized_count, 10) || Math.max(0, (parseInt(payload && payload.images_with_alt, 10) || parseInt(payload && payload.with_alt, 10) || 0) - weakCount)
        );
        var optimizedPercent = totalImages > 0 ? Math.round((optimizedCount / totalImages) * 100) : 0;
        var activeStep = 1;

        if (totalImages > 0 && missingCount > 0) {
            activeStep = 2;
        } else if (totalImages > 0 && (weakCount > 0 || optimizedCount > 0)) {
            activeStep = 3;
        }

        if (totalImages > 0 && missingCount <= 0 && weakCount <= 0) {
            activeStep = 4;
        }

        return {
            totalImages: totalImages,
            missingCount: missingCount,
            weakCount: weakCount,
            optimizedCount: optimizedCount,
            optimizedPercent: optimizedPercent,
            activeStep: activeStep
        };
    }

    function buildLibraryWorkflowMetric(stepKey, value) {
        var count = Math.max(0, parseInt(value, 10) || 0);

        if (stepKey === 'scan') {
            return sprintf(
                _n('%s image scanned', '%s images scanned', Math.max(1, count), 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(count)
            );
        }

        if (stepKey === 'generate') {
            return sprintf(
                _n('%s image missing ALT', '%s images missing ALT', Math.max(1, count), 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(count)
            );
        }

        if (stepKey === 'review') {
            return sprintf(
                _n('%s image needs review', '%s images need review', Math.max(1, count), 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(count)
            );
        }

        return sprintf(
            __('%s%% optimized', 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(count)
        );
    }

    /** @see BBAI_BANNER_LOW_CREDITS_THRESHOLD in includes/admin/banner-system.php */
    var BBAI_BANNER_LOW_CREDITS_THRESHOLD = 10;

    function getLibraryWorkspaceNavUrls() {
        var root = document.querySelector('[data-bbai-library-workspace-root]');
        if (!root) {
            return { usageUrl: '', guideUrl: '', libraryUrl: '', needsReviewLibraryUrl: '' };
        }
        return {
            usageUrl: String(root.getAttribute('data-bbai-usage-url') || ''),
            guideUrl: String(root.getAttribute('data-bbai-guide-url') || ''),
            libraryUrl: String(root.getAttribute('data-bbai-library-url') || ''),
            needsReviewLibraryUrl: String(root.getAttribute('data-bbai-needs-review-library-url') || '')
        };
    }

    function getLibrarySurfaceIconMarkup(state) {
        var sharedTone = 'healthy';

        if (state === 'empty') {
            sharedTone = 'setup';
        } else if (state === 'low_credits') {
            sharedTone = 'attention';
        } else if (state === 'out_of_credits') {
            sharedTone = 'paused';
        } else if (state === 'missing' || state === 'weak') {
            sharedTone = 'attention';
        }

        if (typeof window !== 'undefined' && typeof window.bbaiGetSharedCommandHeroIconMarkup === 'function') {
            return window.bbaiGetSharedCommandHeroIconMarkup(sharedTone);
        }

        if (state === 'empty') {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        if (state === 'missing') {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"></circle><path d="M12 7V12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="16" r="1" fill="currentColor"></circle></svg>';
        }

        if (state === 'weak') {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3L21 20H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
        }

        if (state === 'low_credits') {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3L21 20H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
        }

        if (state === 'out_of_credits') {
            return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"></circle><path d="M15 9L9 15M9 9L15 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    }

    function getLibraryAutomationSettingsUrl() {
        var root = document.querySelector('[data-bbai-library-workspace-root]');
        return root ? String(root.getAttribute('data-bbai-automation-settings-url') || '') : '';
    }

    function escapeLibraryAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function buildLibraryActionAttributes(attributes) {
        var markup = '';

        if (!attributes || typeof attributes !== 'object') {
            return markup;
        }

        Object.keys(attributes).forEach(function(attributeName) {
            if (!attributeName) {
                return;
            }

            var attributeValue = attributes[attributeName];
            if (attributeValue === null || attributeValue === undefined || attributeValue === false) {
                return;
            }

            markup += ' ' + escapeLibraryAttr(attributeName) + '="' + escapeLibraryAttr(attributeValue) + '"';
        });

        return markup;
    }

    function buildLibrarySurfacePrimaryMarkup(action) {
        if (!action || !action.label) {
            return '';
        }
        var quotaUi = getLibraryQuotaUiState();
        var isAnonymousTrial = !!quotaUi.isAnonymousTrial;
        var lockClass = action.locked ? ' bbai-is-locked' : '';
        var label = escapeHtml(action.label);
        if (action.href) {
            return '<a class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-banner__cta bbai-banner__cta--primary' + lockClass + '" data-bbai-library-surface-action="1" href="' + escapeLibraryAttr(action.href) + '"' + buildLibraryActionAttributes(action.attributes) + '>' + label + '</a>';
        }
        var parts = ['<button type="button" class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-banner__cta bbai-banner__cta--primary' + lockClass + '" data-bbai-library-surface-action="1"'];
        if (action.bbaiAction) {
            parts.push(' data-bbai-action="' + escapeLibraryAttr(action.bbaiAction) + '"');
        } else if (action.filterTarget) {
            parts.push(' data-bbai-filter-target="' + escapeLibraryAttr(action.filterTarget) + '"');
        } else if (action.action) {
            parts.push(' data-action="' + escapeLibraryAttr(action.action) + '"');
        }
        if (action.bulkLimit != null && parseInt(action.bulkLimit, 10) > 0) {
            parts.push(' data-bbai-bulk-limit="' + escapeLibraryAttr(String(parseInt(action.bulkLimit, 10))) + '"');
            parts.push(' data-bbai-lock-preserve-label="1"');
        }
        if (action.locked) {
            parts.push(
                isAnonymousTrial
                    ? ' data-bbai-action="open-signup" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-progress-primary" data-auth-tab="register" aria-disabled="true"'
                    : ' data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-progress-primary" aria-disabled="true"'
            );
        } else if (action.openUpgrade) {
            parts.push(
                isAnonymousTrial
                    ? ' data-bbai-action="open-signup" data-bbai-locked-cta="1" data-bbai-lock-reason="' +
                        escapeLibraryAttr(action.lockReason || 'automation') +
                        '" data-bbai-locked-source="' +
                        escapeLibraryAttr(action.lockSource || 'library-summary-automation') +
                        '" data-auth-tab="register"'
                    : ' data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="' +
                        escapeLibraryAttr(action.lockReason || 'automation') +
                        '" data-bbai-locked-source="' +
                        escapeLibraryAttr(action.lockSource || 'library-summary-automation') +
                        '"'
            );
        }
        parts.push(buildLibraryActionAttributes(action.attributes));
        parts.push('>' + label + '</button>');
        return parts.join('');
    }

    function buildLibrarySurfaceSecondaryMarkup(action) {
        if (!action || !action.label) {
            return '';
        }
        var quotaUi = getLibraryQuotaUiState();
        var isAnonymousTrial = !!quotaUi.isAnonymousTrial;
        var label = escapeHtml(action.label);
        if (action.href) {
            return (
                '<a class="bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary bbai-banner__link bbai-banner__link--secondary" data-bbai-library-secondary-action="1" href="' +
                escapeLibraryAttr(action.href) +
                '"' +
                buildLibraryActionAttributes(action.attributes) +
                '">' +
                label +
                '</a>'
            );
        }
        var sb =
            '<button type="button" class="bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary bbai-banner__link bbai-banner__link--secondary" data-bbai-library-secondary-action="1"';
        if (action.action) {
            sb += ' data-action="' + escapeLibraryAttr(action.action) + '"';
        }
        if (action.openUpgrade) {
            sb += isAnonymousTrial
                ? ' data-bbai-action="open-signup" data-bbai-locked-cta="1" data-bbai-lock-reason="' +
                    escapeLibraryAttr(action.lockReason || 'automation') +
                    '" data-bbai-locked-source="' +
                    escapeLibraryAttr(action.lockSource || 'library-summary-automation') +
                    '" data-auth-tab="register"'
                : ' data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="' +
                    escapeLibraryAttr(action.lockReason || 'automation') +
                    '" data-bbai-locked-source="' +
                    escapeLibraryAttr(action.lockSource || 'library-summary-automation') +
                    '"';
        }
        sb += buildLibraryActionAttributes(action.attributes);
        sb += '>' + label + '</button>';
        return sb;
    }

    function getLibraryQuotaUiState() {
        var quotaState = getQuotaState();
        var remaining = Math.max(0, parseInt(quotaState.creditsRemaining, 10) || 0);
        var used = Math.max(0, parseInt(quotaState.creditsUsed, 10) || 0);
        var limit = Math.max(0, parseInt(quotaState.creditsAllocated, 10) || 0);
        var planTier = String(quotaState.planTier || '').toLowerCase();
        var isPro = planTier === 'growth' || planTier === 'agency';
        var thresh = Math.max(0, parseInt(quotaState.lowCreditThreshold, 10) || BBAI_BANNER_LOW_CREDITS_THRESHOLD);

        return {
            remaining: remaining,
            used: used,
            limit: limit,
            isPro: isPro,
            authState: quotaState.authState || '',
            quotaType: quotaState.quotaType || '',
            quotaState: quotaState.quotaState || '',
            signupRequired: !!quotaState.signupRequired,
            freePlanOffer: Math.max(0, parseInt(quotaState.freePlanOffer, 10) || 50),
            lowCreditThreshold: thresh,
            isTrial: !!quotaState.isAnonymousTrial || String(quotaState.quotaType || '').toLowerCase() === 'trial',
            isAnonymousTrial: !!quotaState.isAnonymousTrial,
            isLowCredits: remaining > 0 && remaining <= thresh,
            isOutOfCredits: remaining === 0
        };
    }

    function getLibrarySurfaceState(payload) {
        var workflowState = getLibraryWorkflowState(payload || {});
        var quotaUi = getLibraryQuotaUiState();
        var nav = getLibraryWorkspaceNavUrls();
        var rem = quotaUi.remaining;
        var missing = workflowState.missingCount;
        var weak = workflowState.weakCount;
        var total = workflowState.totalImages;
        var totalIssues = missing + weak;
        var thresh = Math.max(0, parseInt(quotaUi.lowCreditThreshold, 10) || 0);

        var pageHeroVariant = 'success';
        var bannerVariant = 'success';
        var heroTone = 'healthy';
        var surfaceShell = 'healthy';
        var logicalState = 'healthy';
        var note = '';
        var title = __('Your library is in great shape', 'beepbeep-ai-alt-text-generator');
        var copy = __('All images are optimized and up to date.', 'beepbeep-ai-alt-text-generator');
        var nextTitle = '';
        var nextCopy = '';
        var automationCopy = '';
        var progressFoot = '';
        var primaryAction = null;
        var secondaryAction = null;
        var settingsAutoUrl = getLibraryAutomationSettingsUrl();
        var libRoot = getLibraryWorkspaceRoot();
        var libGuestTrial = libRoot && libRoot.getAttribute('data-bbai-is-guest-trial') === '1';
        var sharedHeroState = typeof window !== 'undefined' && typeof window.bbaiBuildSharedCommandHeroState === 'function'
            ? window.bbaiBuildSharedCommandHeroState({
                totalImages: total,
                missingCount: missing,
                weakCount: weak,
                creditsRemaining: rem,
                creditsLimit: quotaUi.limit,
                isPro: quotaUi.isPro,
                lowCreditThreshold: thresh,
                authState: quotaUi.authState || '',
                quotaType: quotaUi.quotaType || '',
                quotaState: quotaUi.quotaState || '',
                signupRequired: !!quotaUi.signupRequired,
                freePlanOffer: quotaUi.freePlanOffer || 50,
                isTrial: !!quotaUi.isTrial || libGuestTrial,
                isGuestTrial: libGuestTrial,
                pageContext: 'library',
                libraryUrl: nav.libraryUrl,
                usageUrl: nav.usageUrl,
                guideUrl: nav.guideUrl,
                settingsUrl: settingsAutoUrl,
                needsReviewLibraryUrl: nav.needsReviewLibraryUrl || nav.libraryUrl || '#'
            })
            : null;

        if (sharedHeroState) {
            logicalState = sharedHeroState.state || 'healthy';
            surfaceShell = sharedHeroState.surfaceState || 'healthy';
            heroTone = sharedHeroState.tone || 'healthy';
            pageHeroVariant = sharedHeroState.pageHeroVariant || 'success';
            bannerVariant = sharedHeroState.bannerVariant || 'success';
            title = sharedHeroState.headline || '';
            copy = sharedHeroState.subtext || '';
            nextCopy = sharedHeroState.nextStep || '';
            note = sharedHeroState.note || '';
            primaryAction = sharedHeroState.primaryAction;
            secondaryAction = sharedHeroState.secondaryAction;

            if (
                getLibraryWorkspaceRoot()
                && primaryAction
                && primaryAction.action === 'generate-missing'
            ) {
                var wfModel = getLibraryWorkflowDecisionModel();
                if (wfModel.visible && wfModel.primaryAction && wfModel.primaryAction.kind === 'generate') {
                    primaryAction = $.extend({}, primaryAction, {
                        label: wfModel.primaryAction.label,
                        bulkLimit: wfModel.primaryAction.limit
                    });
                }
            }

            return {
                state: surfaceShell,
                logicalState: logicalState,
                heroTone: heroTone,
                pageHeroVariant: pageHeroVariant,
                bannerVariant: bannerVariant,
                title: title,
                copy: copy,
                nextTitle: nextTitle,
                nextCopy: nextCopy,
                automationCopy: automationCopy,
                primaryAction: primaryAction,
                secondaryAction: secondaryAction,
                note: note,
                progressFoot: progressFoot,
                iconMarkup: getLibrarySurfaceIconMarkup(surfaceShell)
            };
        }

        return null;
    }

    function updateLibraryWorkflow(payload) {
        var workflow = document.querySelector('[data-bbai-queue-workflow]');
        if (!workflow || !payload || typeof payload !== 'object') {
            return;
        }

        var state = getLibraryWorkflowState(payload);
        workflow.setAttribute('data-bbai-wf-active', String(state.activeStep));
        workflow.setAttribute('data-bbai-wf-mode', state.activeStep === 4 ? 'compact' : 'steps');

        workflow.querySelectorAll('[data-bbai-wf-step]').forEach(function(stepNode) {
            var stepNum = parseInt(stepNode.getAttribute('data-bbai-wf-step-num') || '0', 10);
            var stepKey = String(stepNode.getAttribute('data-bbai-wf-step') || '');
            var isActive = stepNum === state.activeStep;
            var isDone = stepNum < state.activeStep;
            var isSuccess = stepKey === 'complete' && state.activeStep === 4;
            var metricValue = stepKey === 'scan'
                ? state.totalImages
                : (stepKey === 'generate'
                    ? state.missingCount
                    : (stepKey === 'review' ? state.weakCount : state.optimizedPercent));

            stepNode.classList.toggle('bbai-queue-workflow__step--active', isActive);
            stepNode.classList.toggle('bbai-queue-workflow__step--done', isDone);
            stepNode.classList.toggle('bbai-queue-workflow__step--success', isSuccess);

            var metricNode = stepNode.querySelector('[data-bbai-wf-metric]');
            if (metricNode) {
                metricNode.textContent = buildLibraryWorkflowMetric(stepKey, metricValue);
            }
        });

        workflow.querySelectorAll('.bbai-queue-workflow__arrow').forEach(function(arrowNode, index) {
            arrowNode.classList.toggle('bbai-queue-workflow__arrow--done', index + 1 < state.activeStep);
        });

        var completeAction = workflow.querySelector('[data-bbai-wf-action="complete"]');
        if (completeAction) {
            var completed = state.activeStep === 4;
            completeAction.textContent = completed
                ? __('Completed', 'beepbeep-ai-alt-text-generator')
                : __('In progress', 'beepbeep-ai-alt-text-generator');
            completeAction.setAttribute('data-bbai-wf-action-state', completed ? 'complete' : 'progress');
        }

        var healthyTitle = workflow.querySelector('[data-bbai-wf-healthy-title]');
        if (healthyTitle) {
            healthyTitle.textContent = __('All images are optimized', 'beepbeep-ai-alt-text-generator');
        }

        var healthyCopy = workflow.querySelector('[data-bbai-wf-healthy-copy]');
        if (healthyCopy) {
            var workflowQuota = getLibraryQuotaUiState();
            healthyCopy.textContent = workflowQuota.isAnonymousTrial
                ? __('Your media library is fully covered. Review results now, and create a free account when you want more monthly generations.', 'beepbeep-ai-alt-text-generator')
                : (workflowQuota.isPro
                    ? __('Your media library is fully covered. Scan future uploads or review your automation settings to keep it that way.', 'beepbeep-ai-alt-text-generator')
                    : __('Your media library is fully covered. Scan future uploads or enable automation to keep it that way.', 'beepbeep-ai-alt-text-generator'));
        }

        var optimizedNode = workflow.querySelector('[data-bbai-wf-healthy-optimized]');
        if (optimizedNode) {
            optimizedNode.textContent = formatDashboardNumber(state.optimizedCount);
        }

        var creditsNode = workflow.querySelector('[data-bbai-wf-healthy-credits]');
        if (creditsNode) {
            creditsNode.textContent = formatDashboardNumber(getLibraryQuotaUiState().remaining);
        }
    }

    /* ── Review-scroll: contextual navigation for "Review ALT results" button ── */

    function isOnLibraryPage() {
        return getCurrentAdminPage() === 'alt-library';
    }

    function focusScanResultsHeading(node) {
        if (!node || typeof node.focus !== 'function') {
            return;
        }

        try {
            node.focus({ preventScroll: true });
        } catch (error) {
            node.focus();
        }
    }

    function getPreferredResultsFilter(summary) {
        var scanSummary = summary || {};
        var missing = Math.max(0, parseInt(scanSummary.missing, 10) || 0);
        var weak = Math.max(0, parseInt(scanSummary.weak, 10) || 0);

        if (missing > 0) {
            return 'missing';
        }

        if (weak > 0) {
            return 'weak';
        }

        return 'all';
    }

    function highlightReviewRows() {
        var resultsSection = getScanResultsSection();
        if (!resultsSection) {
            return;
        }

        var rows = resultsSection.querySelectorAll(
            '.bbai-library-row[data-alt-missing="true"], .bbai-library-row[data-status="weak"]'
        );
        rows.forEach(function(row) {
            row.classList.add('bbai-library-row--review-highlight');
        });
        setTimeout(function() {
            rows.forEach(function(row) {
                row.classList.remove('bbai-library-row--review-highlight');
            });
        }, 2000);
    }

    function scrollToAltTable(options) {
        var settings = options || {};
        var resultsSection = getScanResultsSection();
        var resultsHeading = getScanResultsHeading(resultsSection);
        var summary = settings.summary || null;

        if (!resultsSection) {
            return false;
        }

        if (summary && summary.hasIssues && typeof setLibraryReviewFilter === 'function') {
            setLibraryReviewFilter(getPreferredResultsFilter(summary));
        }

        resultsSection.classList.remove('bbai-library-results--highlight');
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

        window.setTimeout(function() {
            resultsSection.classList.add('bbai-library-results--highlight');
            focusScanResultsHeading(resultsHeading || resultsSection);
            if (summary && summary.hasIssues) {
                highlightReviewRows();
            }
        }, 220);

        window.setTimeout(function() {
            resultsSection.classList.remove('bbai-library-results--highlight');
        }, 1900);

        return true;
    }

    function getLibraryScanFeedbackSummary() {
        var banner = document.querySelector('[data-bbai-scan-feedback-banner]');
        if (!banner) {
            return getScanResultSummary({});
        }

        return getScanResultSummary({
            images_missing_alt: banner.getAttribute('data-bbai-scan-missing') || '0',
            needs_review_count: banner.getAttribute('data-bbai-scan-weak') || '0'
        });
    }

    document.addEventListener('click', function(event) {
        var link = event.target.closest('[data-bbai-review-scroll]');
        if (!link) {
            return;
        }
        if (isOnLibraryPage()) {
            event.preventDefault();
            scrollToAltTable();
        }
        // Otherwise, follow the href normally; highlight on arrival via hash.
    });

    // On page load, auto-scroll if arriving from the review button with a hash hint.
    if (isOnLibraryPage() && window.location.hash === '#bbai-alt-table') {
        // Small delay to let the table render.
        setTimeout(scrollToAltTable, 300);
    }

    function updateLibrarySummarySurface(payload) {
        var card = document.querySelector('[data-bbai-library-surface]');
        if (!card) {
            return;
        }

        var state = getLibrarySurfaceState(payload || {});
        if (!state) {
            return;
        }
        card.setAttribute('data-state', state.state);

        var heroSection = card.querySelector('[data-bbai-shared-command-hero="1"]');
        if (heroSection) {
            heroSection.setAttribute('data-tone', state.heroTone || 'healthy');
            heroSection.setAttribute('data-page-hero-variant', state.pageHeroVariant || 'success');
            heroSection.classList.remove(
                'bbai-banner--success',
                'bbai-banner--warning',
                'bbai-page-hero--success',
                'bbai-page-hero--warning',
                'bbai-page-hero--neutral'
            );
            var shellBanner = state.bannerVariant === 'warning' ? 'warning' : 'success';
            heroSection.classList.add('bbai-banner--' + shellBanner);
            heroSection.classList.add('bbai-page-hero--' + (state.pageHeroVariant || 'success'));
            if (state.logicalState) {
                heroSection.setAttribute('data-bbai-banner-logical-state', state.logicalState);
            }
        }

        var iconNode = card.querySelector('[data-bbai-library-surface-icon]');
        if (iconNode) {
            iconNode.innerHTML = state.iconMarkup;
        }

        var titleNode = card.querySelector('[data-bbai-library-surface-title]');
        if (titleNode) {
            titleNode.textContent = state.title;
        }

        var copyNode = card.querySelector('[data-bbai-library-surface-copy]');
        if (copyNode) {
            copyNode.textContent = state.copy;
        }

        var statusNode = card.querySelector('[data-bbai-library-surface-status]');
        if (statusNode) {
            statusNode.textContent = state.nextCopy;
        }

        var nextTitleNode = card.querySelector('[data-bbai-library-surface-next-title]');
        if (nextTitleNode) {
            nextTitleNode.textContent = state.nextTitle;
        }

        var automationCopyNode = card.querySelector('[data-bbai-library-automation-copy]');
        if (automationCopyNode) {
            automationCopyNode.textContent = state.automationCopy;
        }

        var footNode = card.querySelector('[data-bbai-library-progress-foot]');
        if (footNode) {
            footNode.textContent = state.progressFoot;
        }

        var noteNode = card.querySelector('[data-bbai-hero-note]');
        if (noteNode) {
            if (state.note) {
                noteNode.removeAttribute('hidden');
                noteNode.textContent = state.note;
            } else {
                noteNode.setAttribute('hidden', '');
                noteNode.textContent = '';
            }
        }

        var primaryWrap = card.querySelector('[data-bbai-hero-primary-item]');
        if (primaryWrap) {
            var primaryHtml = buildLibrarySurfacePrimaryMarkup(state.primaryAction);
            if (primaryHtml) {
                primaryWrap.removeAttribute('hidden');
                primaryWrap.innerHTML = primaryHtml;
            } else {
                primaryWrap.setAttribute('hidden', '');
                primaryWrap.innerHTML = '';
            }
        }

        var secWrap = card.querySelector('[data-bbai-hero-secondary-item]');
        if (secWrap) {
            var secHtml = buildLibrarySurfaceSecondaryMarkup(state.secondaryAction);
            if (!secHtml) {
                secWrap.setAttribute('hidden', '');
                secWrap.innerHTML = '';
            } else {
                secWrap.removeAttribute('hidden');
                secWrap.innerHTML = secHtml;
            }
        }

        syncLibraryTopHeroGenerateMissingCta();
        updateLibraryBannerInlineChips();
    }

    function updateLibraryUsageSurface(usagePayload) {
        usagePayload = normalizeUsagePayload(usagePayload);
        var root = document.querySelector('[data-bbai-library-core-usage]');
        if (!root || !usagePayload || typeof usagePayload !== 'object') {
            return;
        }

        var used = Math.max(0, parseInt(usagePayload.used, 10) || 0);
        var limit = Math.max(1, parseInt(usagePayload.limit, 10) || 50);
        var remaining = parseInt(usagePayload.remaining, 10);
        if (isNaN(remaining)) {
            remaining = 0;
        }
        remaining = Math.max(0, remaining);
        var percentage = Math.min(100, Math.round((used / limit) * 100));
        var isAnonymousTrial = isAnonymousTrialUsage(usagePayload);
        var quotaUi = getLibraryQuotaUiState();
        var resolvedLowCreditThreshold = parseInt(usagePayload.low_credit_threshold, 10);
        if (isNaN(resolvedLowCreditThreshold)) {
            resolvedLowCreditThreshold = parseInt(quotaUi.lowCreditThreshold, 10);
        }
        resolvedLowCreditThreshold = isNaN(resolvedLowCreditThreshold)
            ? (isAnonymousTrial ? Math.min(2, Math.max(1, limit - 1)) : BBAI_BANNER_LOW_CREDITS_THRESHOLD)
            : Math.max(0, resolvedLowCreditThreshold);
        quotaUi.remaining = remaining;
        quotaUi.lowCreditThreshold = resolvedLowCreditThreshold;
        quotaUi.isLowCredits = remaining > 0 && remaining <= resolvedLowCreditThreshold;
        quotaUi.isOutOfCredits = remaining === 0;

        var usageLineNode = root.querySelector('[data-bbai-library-usage-line]');
        if (usageLineNode) {
            usageLineNode.innerHTML = '<strong>' + escapeHtml(
                isAnonymousTrial
                    ? sprintf(
                        __('%1$s of %2$s free trial generations used', 'beepbeep-ai-alt-text-generator'),
                        formatDashboardNumber(used),
                        formatDashboardNumber(limit)
                    )
                    : sprintf(
                        __('%1$s of %2$s AI generations used', 'beepbeep-ai-alt-text-generator'),
                        formatDashboardNumber(used),
                        formatDashboardNumber(limit)
                    )
            ) + '</strong>';
        }

        var usageCopyNode = root.querySelector('[data-bbai-library-usage-copy]');
        if (usageCopyNode) {
            usageCopyNode.textContent = isAnonymousTrial
                ? (remaining > 0
                    ? sprintf(
                        _n('%s trial generation remaining', '%s trial generations remaining', remaining, 'beepbeep-ai-alt-text-generator'),
                        formatDashboardNumber(remaining)
                    )
                    : sprintf(
                        __('Free trial complete. Create a free account to unlock %d images per month.', 'beepbeep-ai-alt-text-generator'),
                        freePlanOffer
                    ))
                : (remaining > 0
                    ? sprintf(
                        _n('%s credit remaining this cycle', '%s credits remaining this cycle', remaining, 'beepbeep-ai-alt-text-generator'),
                        formatDashboardNumber(remaining)
                    )
                    : __('No credits remaining this cycle', 'beepbeep-ai-alt-text-generator'));
        }

        var usageProgressNode = root.querySelector('[data-bbai-library-usage-progress]');
        if (usageProgressNode) {
            usageProgressNode.style.width = percentage + '%';
        }

        var usageProgressbar = root.querySelector('[data-bbai-library-usage-progressbar]');
        if (usageProgressbar) {
            usageProgressbar.setAttribute('aria-valuenow', String(percentage));
        }

        var creditsRemainingNode = document.querySelector('[data-bbai-library-credits-remaining]');
        if (creditsRemainingNode) {
            creditsRemainingNode.textContent = formatDashboardNumber(remaining);
        }
        syncLibraryTopHeroGenerateMissingCta();
        updateLibraryBannerInlineChips();
    }

    function normalizeLibraryStatusFilter(filter) {
        var f = String(filter || 'all').toLowerCase();
        if (f === 'needs_review' || f === 'needs-review') {
            return 'weak';
        }
        return f;
    }

    function syncLibraryFilterToUrl(filter) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        if (!document.getElementById('bbai-review-filter-tabs')) {
            return;
        }
        try {
            var url = new URL(window.location.href);
            if (String(url.searchParams.get('page') || '') !== 'bbai-library') {
                return;
            }
            // Canonicalize on the modern `status` param so legacy `filter` deep links
            // cannot force the workspace back into needs-review after switching tabs.
            url.searchParams.delete('filter');
            var f = normalizeLibraryStatusFilter(filter);
            if (f === 'all') {
                url.searchParams.delete('status');
            } else if (f === 'weak') {
                url.searchParams.set('status', 'needs_review');
            } else {
                url.searchParams.set('status', f);
            }
            window.history.replaceState({}, '', url.toString());
        } catch (err) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Library filter URL sync failed', err);
        }
    }

    function getLibraryActiveFilter() {
        var activeButton = document.querySelector(
            '#bbai-review-filter-tabs button.bbai-alt-review-filters__btn--active, #bbai-review-filter-tabs button.bbai-filter-group__item--active'
        );
        return activeButton
            ? normalizeLibraryStatusFilter(activeButton.getAttribute('data-filter') || 'all')
            : 'all';
    }

    function getLibrarySearchTerm() {
        var input = document.getElementById('bbai-library-search');
        return input ? String(input.value || '').trim().toLowerCase() : '';
    }

    function getLibrarySortValue() {
        var select = document.getElementById('bbai-library-sort');
        return select ? String(select.value || 'recently-updated').toLowerCase() : 'recently-updated';
    }

    function getLibraryReviewState(row) {
        if (!row) {
            return 'all';
        }

        if (String(row.getAttribute('data-alt-missing') || 'false') === 'true') {
            return 'missing';
        }

        // User approval should move a row out of the review queue immediately, even if the
        // quality score badge still reflects a weaker ALT description.
        if (String(row.getAttribute('data-approved') || 'false') === 'true') {
            return 'optimized';
        }

        var explicit = String(row.getAttribute('data-review-state') || row.getAttribute('data-status') || '').toLowerCase();
        if (explicit === 'missing' || explicit === 'weak' || explicit === 'optimized') {
            return explicit;
        }

        var qualityClass = String(row.getAttribute('data-quality-class') || row.getAttribute('data-quality') || '').toLowerCase();
        return (qualityClass === 'excellent' || qualityClass === 'good') ? 'optimized' : 'weak';
    }

    function isLibraryWorkspaceServerFiltered() {
        var root = getLibraryWorkspaceRoot();

        return !!(root && root.getAttribute('data-bbai-library-server-filter') === '1');
    }

    function rowMatchesLibraryFilter(row, filter, searchTerm) {
        if (!row) {
            return false;
        }

        var normalizedFilter = normalizeLibraryStatusFilter(filter);
        var normalizedSearch = String(searchTerm || '').toLowerCase();
        var haystack = [
            row.getAttribute('data-file-name') || '',
            row.getAttribute('data-image-title') || '',
            row.getAttribute('data-alt-full') || '',
            row.getAttribute('data-review-summary') || ''
        ].join(' ').toLowerCase();

        var matchesSearch = !normalizedSearch || haystack.indexOf(normalizedSearch) !== -1;
        if (!matchesSearch) {
            return false;
        }

        var rowState = getLibraryReviewState(row);
        if (normalizedFilter === 'all') {
            return true;
        }

        return rowState === normalizedFilter;
    }

    function getLibraryEmptyRow() {
        return document.getElementById('bbai-library-filter-empty');
    }

    function ensureLibraryEmptyRow() {
        var tbody = document.getElementById('bbai-library-table-body');
        if (!tbody) {
            return null;
        }

        var emptyRow = getLibraryEmptyRow();
        if (emptyRow) {
            return emptyRow;
        }

        emptyRow = document.createElement('div');
        emptyRow.id = 'bbai-library-filter-empty';
        emptyRow.className = 'bbai-library-filter-empty bbai-table-empty bbai-table-empty--inline';
        var emptyCell = document.createElement('div');
        emptyCell.className = 'bbai-library-filter-empty__cell';
        emptyCell.innerHTML =
            '<div class="bbai-state bbai-state--empty bbai-state--compact bbai-library-empty-card bbai-table-empty__panel">' +
            '<h3 class="bbai-state__title bbai-library-empty-card__title bbai-table-empty__panel-title" data-bbai-library-empty-title></h3>' +
            '<p class="bbai-state__body bbai-library-empty-card__copy bbai-table-empty__panel-copy" data-bbai-library-empty-copy></p>' +
            '<div class="bbai-state__actions bbai-library-empty-card__actions bbai-table-empty__panel-actions">' +
            '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-bbai-filter-target="all">' + escapeHtml(__('Show all images', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="clear-library-search">' + escapeHtml(__('Clear search', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '</div>' +
            '</div>';
        emptyRow.appendChild(emptyCell);
        tbody.appendChild(emptyRow);
        return emptyRow;
    }

    function updateLibraryEmptyState(filter, searchTerm) {
        var emptyRow = ensureLibraryEmptyRow();
        if (!emptyRow) {
            return;
        }

        var titleNode = emptyRow.querySelector('[data-bbai-library-empty-title]');
        var copyNode = emptyRow.querySelector('[data-bbai-library-empty-copy]');
        var hasSearch = !!String(searchTerm || '').trim();
        var normalizedFilter = normalizeLibraryStatusFilter(filter);
        var isFiltered = normalizedFilter !== 'all';
        var title = __('No images match this filter.', 'beepbeep-ai-alt-text-generator');
        var copy = __('Try another filter or clear the search to see more images.', 'beepbeep-ai-alt-text-generator');

        if (hasSearch) {
            title = __('No images match this search', 'beepbeep-ai-alt-text-generator');
            copy = __('Try a different filename or ALT text search, or clear the current query.', 'beepbeep-ai-alt-text-generator');
        } else if (normalizedFilter === 'weak') {
            title = __('No images need review on this page', 'beepbeep-ai-alt-text-generator');
            copy = __('Everything here meets the quality bar, or switch filters to see other states.', 'beepbeep-ai-alt-text-generator');
        } else if (normalizedFilter === 'missing') {
            var workspaceCounts = getLibraryWorkspaceCountsSnapshot();
            if (workspaceCounts.missing === 0) {
                title = __('No missing ALT left. Nice work.', 'beepbeep-ai-alt-text-generator');
                copy = __('Everything missing on this page has been resolved. Switch filters to review the rest of your library.', 'beepbeep-ai-alt-text-generator');
            } else {
                title = __('No images are missing ALT text', 'beepbeep-ai-alt-text-generator');
                copy = __('Everything on this page already has ALT text. Switch back to the review queue or show all images.', 'beepbeep-ai-alt-text-generator');
            }
        } else if (normalizedFilter === 'optimized') {
            title = __('No optimized images match this view', 'beepbeep-ai-alt-text-generator');
            copy = __('Adjust the current search or switch filters to keep reviewing the library.', 'beepbeep-ai-alt-text-generator');
        } else if (isFiltered) {
            title = __('No images match this filter', 'beepbeep-ai-alt-text-generator');
            copy = __('Switch filters or return to the full table to keep reviewing the library.', 'beepbeep-ai-alt-text-generator');
        }

        if (titleNode) {
            titleNode.textContent = title;
        }
        if (copyNode) {
            copyNode.textContent = copy;
        }
    }

    function compareLibraryRows(a, b, sortValue) {
        var sort = String(sortValue || 'recently-updated').toLowerCase();
        var aCreated = parseInt(a.getAttribute('data-created-ts') || '0', 10) || 0;
        var bCreated = parseInt(b.getAttribute('data-created-ts') || '0', 10) || 0;
        var aStateRank = parseInt(a.getAttribute('data-state-rank') || '99', 10) || 99;
        var bStateRank = parseInt(b.getAttribute('data-state-rank') || '99', 10) || 99;
        var aUpdated = parseInt(a.getAttribute('data-updated-ts') || '0', 10) || 0;
        var bUpdated = parseInt(b.getAttribute('data-updated-ts') || '0', 10) || 0;
        var aName = String(a.getAttribute('data-file-name-sort') || a.getAttribute('data-file-name') || '').toLowerCase();
        var bName = String(b.getAttribute('data-file-name-sort') || b.getAttribute('data-file-name') || '').toLowerCase();
        var aSize = parseInt(a.getAttribute('data-file-size-bytes') || '0', 10) || 0;
        var bSize = parseInt(b.getAttribute('data-file-size-bytes') || '0', 10) || 0;

        if (sort === 'needs-attention') {
            if (aStateRank !== bStateRank) {
                return aStateRank - bStateRank;
            }
            return bUpdated - aUpdated;
        }

        if (sort === 'recently-updated') {
            return bUpdated - aUpdated;
        }

        if (sort === 'score-asc' || sort === 'score-desc') {
            var aScore = parseInt(a.getAttribute('data-quality-score') || '0', 10) || 0;
            var bScore = parseInt(b.getAttribute('data-quality-score') || '0', 10) || 0;
            var aMiss = String(a.getAttribute('data-alt-missing') || 'false') === 'true';
            var bMiss = String(b.getAttribute('data-alt-missing') || 'false') === 'true';
            if (aMiss !== bMiss) {
                return sort === 'score-asc' ? (aMiss ? -1 : 1) : (aMiss ? 1 : -1);
            }
            if (aScore !== bScore) {
                return sort === 'score-asc' ? aScore - bScore : bScore - aScore;
            }
            return bUpdated - aUpdated;
        }

        if (sort === 'filename') {
            return aName.localeCompare(bName);
        }

        if (sort === 'file-size') {
            if (aSize !== bSize) {
                return bSize - aSize;
            }
            return aName.localeCompare(bName);
        }

        return bCreated - aCreated;
    }

    function sortLibraryRows() {
        var tbody = document.getElementById('bbai-library-table-body');
        if (!tbody) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('.bbai-library-row'));
        if (!rows.length) {
            return;
        }

        var sortValue = getLibrarySortValue();
        rows.sort(function(a, b) {
            return compareLibraryRows(a, b, sortValue);
        });

        rows.forEach(function(row) {
            tbody.appendChild(row);
        });

        var emptyRow = getLibraryEmptyRow();
        if (emptyRow) {
            tbody.appendChild(emptyRow);
        }
    }

    function applyLibraryReviewFilters() {
        var rows = document.querySelectorAll('.bbai-library-row');
        sortLibraryRows();

        var filter = getLibraryActiveFilter();
        var searchTerm = getLibrarySearchTerm();
        var visibleCount = 0;
        var container = document.querySelector('.bbai-library-container');

        Array.prototype.forEach.call(rows, function(row) {
            var matches = rowMatchesLibraryFilter(row, filter, searchTerm);
            row.classList.toggle('bbai-library-row--hidden', !matches);
            row.setAttribute('aria-hidden', matches ? 'false' : 'true');
            if (matches) {
                visibleCount++;
            }
        });

        var emptyRow = ensureLibraryEmptyRow();
        if (emptyRow) {
            emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        }

        if (container) {
            container.setAttribute('data-bbai-has-search-query', searchTerm ? 'true' : 'false');
            container.setAttribute('data-bbai-has-filters', (filter !== 'all' || searchTerm) ? 'true' : 'false');
        }

        updateLibraryEmptyState(filter, searchTerm);
        renderLibraryWorkflowDecisionSurfaces();
        syncLibraryMissingBulkBar();
    }

    function isAnonymousTrialExhaustedClient() {
        var trialData = window.BBAI_DASH && window.BBAI_DASH.trial;
        if (!trialData || !trialData.is_trial) {
            return false;
        }
        var exhausted = !!(trialData.exhausted || trialData.trial_exhausted);
        if (!exhausted) {
            return false;
        }
        if (guestTrialClientShowsRemainingCredits()) {
            return false;
        }
        return true;
    }

    function syncLibraryMissingBulkBar() {
        var bar = document.getElementById('bbai-library-missing-bulk-bar');
        if (!bar) {
            return;
        }

        var hero = document.querySelector('.bbai-library-top-hero');

        var root = getLibraryWorkspaceRoot();
        if (!root) {
            bar.hidden = true;
            if (hero) {
                hero.classList.remove('bbai-library-top-hero--suppress-duplicate-missing-cta');
            }
            return;
        }

        var filter = getLibraryActiveFilter();
        var counts = getLibraryWorkspaceCountsSnapshot();
        var missing = Math.max(0, parseInt(counts.missing, 10) || 0);
        var showBar = filter === 'missing' && missing > 0;

        bar.hidden = !showBar;
        if (hero) {
            // In-context bulk row is primary for Missing; hide duplicate hero primary (“Optimize all missing”).
            hero.classList.toggle('bbai-library-top-hero--suppress-duplicate-missing-cta', showBar);
        }
        if (!showBar) {
            return;
        }

        var ready = bar.querySelector('[data-bbai-missing-bulk-ready]');
        var noCred = bar.querySelector('[data-bbai-missing-bulk-no-credits]');
        var helper = bar.querySelector('[data-bbai-missing-bulk-helper]');

        var usage = getLibraryWorkspaceUsageSnapshot();
        var remaining = usage && usage.remaining !== undefined && usage.remaining !== null
            ? Math.max(0, parseInt(usage.remaining, 10) || 0)
            : null;
        var domRem = readGuestTrialRemainingFromDom();
        if (domRem > 0) {
            remaining = remaining === null ? domRem : Math.max(remaining, domRem);
        }
        var isPro = isLibraryProPlan();
        var trialExhausted = isAnonymousTrialExhaustedClient();
        if (remaining !== null && remaining > 0) {
            trialExhausted = false;
        }
        var cannotGenerate = trialExhausted || (!isPro && remaining !== null && remaining <= 0);

        if (cannotGenerate) {
            if (ready) {
                ready.hidden = true;
            }
            if (noCred) {
                noCred.hidden = false;
            }
            if (helper) {
                helper.textContent = '';
            }
            return;
        }

        if (ready) {
            ready.hidden = false;
        }
        if (noCred) {
            noCred.hidden = true;
        }

        if (helper) {
            var parts = [];
            parts.push(
                sprintf(
                    _n('%s image missing ALT.', '%s images missing ALT.', missing, 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(missing)
                )
            );
            if (!isPro && remaining !== null) {
                parts.push(
                    remaining === 1
                        ? sprintf(
                            __('Trial usage: %s credit remaining (shown from your account).', 'beepbeep-ai-alt-text-generator'),
                            formatDashboardNumber(remaining)
                        )
                        : sprintf(
                            __('Trial usage: %s credits remaining (shown from your account).', 'beepbeep-ai-alt-text-generator'),
                            formatDashboardNumber(remaining)
                        )
                );
            }
            parts.push(
                __('All missing images are sent in one request; limits are applied on the server.', 'beepbeep-ai-alt-text-generator')
            );
            helper.textContent = parts.join(' ');
        }
    }

    function buildLibraryGenerateAllMissingQuotaDoneMessage(successCount, stillMissingCount, noCredits) {
        var s = Math.max(0, parseInt(successCount, 10) || 0);
        var m = Math.max(0, parseInt(stillMissingCount, 10) || 0);
        var parts = [];

        parts.push(
            sprintf(
                _n('%s image updated.', '%s images updated.', s, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(s)
            )
        );

        if (m > 0) {
            parts.push(
                sprintf(
                    _n('%s still missing.', '%s still missing.', m, 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(m)
                )
            );
        }

        if (noCredits) {
            parts.push(__('No credits remaining.', 'beepbeep-ai-alt-text-generator'));
        }

        return parts.join(' ');
    }

    function getLibraryWorkspaceFilterFromUrl() {
        try {
            var url = new URL(window.location.href);
            var page = String(url.searchParams.get('page') || '');
            var tab = String(url.searchParams.get('tab') || '');
            if (page !== 'bbai-library' && !(page === 'bbai' && tab === 'library')) {
                return null;
            }
            var s = String(url.searchParams.get('status') || '').toLowerCase();
            if (!s) {
                return 'all';
            }
            if (s === 'needs_review' || s === 'needs-review') {
                return 'weak';
            }
            if (s === 'missing' || s === 'optimized' || s === 'weak') {
                return normalizeLibraryStatusFilter(s);
            }

            return 'all';
        } catch (urlReadErr) {
            return null;
        }
    }

    function navigateLibraryWorkspaceFilter(filter) {
        var root = getLibraryWorkspaceRoot();
        if (!root) {
            return false;
        }

        var url;
        try {
            url = new URL(window.location.href);
        } catch (navErr) {
            return false;
        }

        var page = String(url.searchParams.get('page') || '');
        var tab = String(url.searchParams.get('tab') || '');
        if (page !== 'bbai-library' && !(page === 'bbai' && tab === 'library')) {
            var fallbackLib = root.getAttribute('data-bbai-library-url');
            if (!fallbackLib) {
                return false;
            }
            try {
                url = new URL(fallbackLib, window.location.href);
            } catch (fallbackErr) {
                return false;
            }
        }

        var f = normalizeLibraryStatusFilter(filter);
        var urlFilter = getLibraryWorkspaceFilterFromUrl();
        if (urlFilter !== null && f === urlFilter) {
            return false;
        }

        // Canonicalize on the modern `status` param so the PHP fallback for the
        // old `filter` query arg does not keep the workspace stuck on a subset.
        url.searchParams.delete('filter');
        if (f === 'all') {
            url.searchParams.delete('status');
        } else if (f === 'weak') {
            url.searchParams.set('status', 'needs_review');
        } else {
            url.searchParams.set('status', f);
        }
        url.searchParams.set('alt_page', '1');
        window.location.href = url.toString();
        return true;
    }

    function setLibraryReviewFilter(filter, opts) {
        var targetFilter = normalizeLibraryStatusFilter(filter);
        var preferClientSide = !!(opts && opts.preferClientSide);
        var shouldTrack = !!(opts && opts.track);
        if (!preferClientSide && getLibraryWorkspaceRoot() && navigateLibraryWorkspaceFilter(targetFilter)) {
            if (shouldTrack) {
                dispatchAnalyticsEvent('review_filter_applied', {
                    source: getAnalyticsPageSource(),
                    filter_state: targetFilter === 'weak' ? 'needs_review' : targetFilter
                });
            }
            return;
        }

        document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]').forEach(function(button) {
            var btnFilter = normalizeLibraryStatusFilter(button.getAttribute('data-filter') || 'all');
            var isActive = btnFilter === targetFilter;
            button.classList.toggle('bbai-filter-group__item--active', isActive);
            button.classList.toggle('bbai-alt-review-filters__btn--active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            if (isActive) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }
        });
        applyLibraryReviewFilters();
        syncLibraryFilterToUrl(targetFilter);
        renderLibraryWorkflowDecisionSurfaces();
        if (shouldTrack) {
            dispatchAnalyticsEvent('review_filter_applied', {
                source: getAnalyticsPageSource(),
                filter_state: targetFilter === 'weak' ? 'needs_review' : targetFilter
            });
        }
    }

    function bindLibraryReviewFilterButtons() {
        document.querySelectorAll('#bbai-review-filter-tabs button[data-filter]').forEach(function(button) {
            if (button.getAttribute('data-bbai-filter-bound') === '1') {
                return;
            }

            button.setAttribute('data-bbai-filter-bound', '1');
            button.addEventListener('click', function(event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                if (event && typeof event.stopPropagation === 'function') {
                    event.stopPropagation();
                }
                if (event && typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }

                setLibraryReviewFilter(button.getAttribute('data-filter') || 'all', { track: true });
            }, true);
        });
    }

    function scrollLibraryReviewListIntoView() {
        var reviewTabs = document.getElementById('bbai-review-filter-tabs');
        var listRoot = document.getElementById('bbai-library-table-body');
        var target = reviewTabs || listRoot;
        if (!target || typeof target.scrollIntoView !== 'function') {
            return;
        }
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function applyDashboardCoveragePayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        updateAltCoverageCard(payload);
        updateLibraryReviewFilterCounts(payload);
        applyLibraryReviewFilters();
        dispatchDashboardStatsUpdated(payload);
    }

    function refreshDashboardCoverageDataLegacy(deferred, ajaxUrl, nonceValue) {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'bbai_rescan_alt_coverage',
                nonce: nonceValue
            }
        })
        .done(function(response) {
            if (!response || response.success !== true) {
                deferred.reject(
                    response && response.data && response.data.message
                        ? response.data.message
                        : __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator')
                );
                return;
            }

            var payload = response.data || {};
            applyDashboardCoveragePayload(payload);
            deferred.resolve(payload);
        })
        .fail(function(xhr) {
            var errorMessage = __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator');
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                errorMessage = xhr.responseJSON.data.message;
            }
            deferred.reject(errorMessage);
        });
    }

    function pollDashboardCoverageScanJob(jobId, deferred, ajaxUrl, nonceValue) {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'bbai_poll_alt_coverage_scan',
                nonce: nonceValue,
                job_id: jobId
            }
        })
        .done(function(response) {
            if (!response || response.success !== true) {
                deferred.reject(
                    response && response.data && response.data.message
                        ? response.data.message
                        : __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator')
                );
                return;
            }

            var progress = response.data || {};
            if (bbaiDashboardScanModalState.isLoading) {
                updateDashboardScanLoadingFeedback(null, progress);
            }

            if (progress.done && progress.payload) {
                applyDashboardCoveragePayload(progress.payload);
                deferred.resolve(progress.payload);
                return;
            }

            window.setTimeout(function() {
                pollDashboardCoverageScanJob(jobId, deferred, ajaxUrl, nonceValue);
            }, 140);
        })
        .fail(function() {
            refreshDashboardCoverageDataLegacy(deferred, ajaxUrl, nonceValue);
        });
    }

    function refreshDashboardCoverageData() {
        var deferred = $.Deferred();
        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce || '';

        if (!ajaxUrl || !nonceValue) {
            deferred.reject(__('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator'));
            return deferred.promise();
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'bbai_start_alt_coverage_scan',
                nonce: nonceValue
            }
        })
        .done(function(response) {
            if (!response || response.success !== true) {
                refreshDashboardCoverageDataLegacy(deferred, ajaxUrl, nonceValue);
                return;
            }

            var progress = response.data || {};
            if (bbaiDashboardScanModalState.isLoading) {
                updateDashboardScanLoadingFeedback(null, progress);
            }

            if (progress.done && progress.payload) {
                applyDashboardCoveragePayload(progress.payload);
                deferred.resolve(progress.payload);
                return;
            }

            if (!progress.job_id) {
                refreshDashboardCoverageDataLegacy(deferred, ajaxUrl, nonceValue);
                return;
            }

            pollDashboardCoverageScanJob(progress.job_id, deferred, ajaxUrl, nonceValue);
        })
        .fail(function() {
            refreshDashboardCoverageDataLegacy(deferred, ajaxUrl, nonceValue);
        });

        return deferred.promise();
    }

    window.bbaiRefreshDashboardCoverage = refreshDashboardCoverageData;

    /**
     * Generate alt text for missing images
     */
    function handleGenerateMissing(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        clearModalAndScrollLocks();
        var $btn = $(this);
        var originalText = $.trim($btn.text()) || __('Generate ALT', 'beepbeep-ai-alt-text-generator');

        if ($btn.prop('disabled')) {
            return false;
        }

        // Prevent duplicate jobs
        if (window.bbaiJobState && window.bbaiJobState.getState().running) {
            window.bbaiModal.show({
                type: 'info',
                title: __('Job in progress', 'beepbeep-ai-alt-text-generator'),
                message: __('ALT text generation is already running. Check progress in the floating widget.', 'beepbeep-ai-alt-text-generator'),
                buttons: [{ label: __('OK', 'beepbeep-ai-alt-text-generator'), type: 'primary' }]
            });
            return false;
        }

        // Check if we have necessary configuration
        if (!hasBulkConfig) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            return false;
        }

        var usageStats = typeof window.bbaiGetUsageSnapshot === 'function'
            ? window.bbaiGetUsageSnapshot(null)
            : ((window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                (window.BBAI && window.BBAI.usage) ||
                null);

        var remaining = usageStats && usageStats.remaining !== undefined ? parseInt(usageStats.remaining, 10) : null;
        var used = usageStats && usageStats.used !== undefined ? parseInt(usageStats.used, 10) : null;
        var limit = usageStats && usageStats.limit !== undefined ? parseInt(usageStats.limit, 10) : null;
        var plan = usageStats && usageStats.plan ? String(usageStats.plan).toLowerCase() : 'free';

        var isPremium = plan === 'pro' || plan === 'agency';
        var isOutOfCredits = remaining !== null && remaining !== undefined && !isNaN(remaining) && remaining === 0;

        if (guestTrialClientShowsRemainingCredits() || (remaining !== null && remaining !== undefined && !isNaN(remaining) && remaining > 0)) {
            continueWithGeneration();
            return false;
        }

        if (!usageStats) {
            continueWithGeneration();
            return false;
        }

        var trialData = window.BBAI_DASH && window.BBAI_DASH.trial;
        if (trialData && trialData.is_trial && trialData.exhausted) {
            handleTrialExhausted({
                message: buildTrialExhaustedMessage(),
                code: 'bbai_trial_exhausted'
            });
            return false;
        }

        // If user is out of credits, show upgrade modal immediately
        if (!isPremium && isOutOfCredits) {
            handleLimitReached({
                message: __('Monthly limit reached. Upgrade to continue generating alt text.', 'beepbeep-ai-alt-text-generator'),
                code: 'limit_reached',
                usage: usageStats
            });
            return false;
        }

        continueWithGeneration();
        return false;

        function continueWithGeneration() {
            setDashboardRuntimeState('generation_running');
            var restoreGenerateBusyState = setBusyStateForControls('[data-action="generate-missing"], [data-bbai-action="generate_missing"]', __('Generating...', 'beepbeep-ai-alt-text-generator'));

            // Get list of images missing alt text (REST with admin-ajax fallback)
            fetchBulkImageIds('missing', 500)
            .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
                setDashboardRuntimeState('idle');
                // Show custom modal with options to go to library or regenerate all
                window.bbaiModal.show({
                    type: 'info',
                    title: __('No Missing Alt Text', 'beepbeep-ai-alt-text-generator'),
                    message: __('All images in your library already have alt text. You can generate alt text for individual images in the ALT Library, or regenerate all alt text to update existing ones.', 'beepbeep-ai-alt-text-generator'),
                    buttons: [
                        {
                            text: __('Go to ALT Library', 'beepbeep-ai-alt-text-generator'),
                            primary: true,
                            action: function() {
                                window.bbaiModal.close();
                                // Navigate to library tab
                                var libraryUrl = window.location.href.split('?')[0] + '?page=bbai-library';
                                window.location.href = libraryUrl;
                            }
                        },
                        {
                            text: __('Regenerate All Alt Text', 'beepbeep-ai-alt-text-generator'),
                            primary: false,
                            action: function() {
                                window.bbaiModal.close();
                                // Trigger regenerate all button
                                var regenerateBtn = getActionButton('regenerate-all');
                                if (regenerateBtn && !regenerateBtn.disabled) {
                                    regenerateBtn.click();
                                } else {
                                    // Fallback: call the handler directly if button not found
                                    var regenerateHandler = window.handleRegenerateAll || window.bbaiHandleRegenerateAll || window.bbaiRegenerateAll;
                                    if (typeof regenerateHandler !== 'function') {
                                        return;
                                    }
                                    var fakeEvent = { preventDefault: function() {} };
                                    regenerateHandler.call(regenerateBtn || document.body, fakeEvent);
                                }
                            }
                        },
                        {
                            text: __('Close', 'beepbeep-ai-alt-text-generator'),
                            primary: false,
                            action: function() {
                                window.bbaiModal.close();
                            }
                        }
                    ]
                });
                restoreGenerateBusyState();
                return;
            }

            var ids = response.ids || [];
            var starterCap = parseInt($btn.attr('data-bbai-starter-cap') || '0', 10) || 0;
            var explicitLimit = parseInt($btn.attr('data-bbai-bulk-limit') || '0', 10) || 0;
            var allowedCount = ids.length;

            if (starterCap > 0 && allowedCount > starterCap) {
                allowedCount = starterCap;
            }
            if (explicitLimit > 0 && allowedCount > explicitLimit) {
                allowedCount = explicitLimit;
            }

            ids = ids.slice(0, allowedCount);
            var count = ids.length;

            if (count <= 0) {
                restoreGenerateBusyState();
                hideBulkProgress();
                window.bbaiModal.error(__('No images to process.', 'beepbeep-ai-alt-text-generator'));
                return;
            }

            // Show progress bar (totals finalized from server response after batch).
            showBulkProgress(__('Preparing bulk run...', 'beepbeep-ai-alt-text-generator'), count, 0);

            // Queue all images
            queueImages(ids, 'bulk', { skipSchedule: true }, function(success, queued, error, processedIds) {
                restoreGenerateBusyState();

	                if (success && queued > 0) {
	                    // Update modal to show success and keep it open
	                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
	                    logBulkProgressSuccess(sprintf(_n('Successfully queued %d image for processing', 'Successfully queued %d images for processing', queued, 'beepbeep-ai-alt-text-generator'), queued));

                    // Trigger celebration for bulk operation
                    if (window.bbaiCelebrations && typeof window.bbaiCelebrations.showConfetti === 'function') {
                        window.bbaiCelebrations.showConfetti();
                    }
	                    if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
	                        window.bbaiPushToast('success', sprintf(_n('Successfully queued %d image for processing!', 'Successfully queued %d images for processing!', queued, 'beepbeep-ai-alt-text-generator'), queued), { duration: 5000 });
	                    }

                    // Dispatch custom event for celebrations
                    var event = new CustomEvent('bbai:generation:success', { detail: { count: queued, type: 'bulk' } });
                    document.dispatchEvent(event);

                    startInlineGeneration(processedIds || ids, 'generate-missing');

                    // Don't hide modal - let user close it manually or monitor progress
	                } else if (success && queued === 0) {
	                    updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
	                    logBulkProgressSuccess(__('All images are already in queue or processing', 'beepbeep-ai-alt-text-generator'));
	                    startInlineGeneration(processedIds || ids, 'generate-missing');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for quota errors FIRST - show upgrade modal immediately
                    if (isLimitReachedError(error)) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Show error in modal log
                    setDashboardRuntimeState('generation_failed');
                    if (error && error.message) {
                        logBulkProgressError(error.message);
                    } else {
                        logBulkProgressError(__('Failed to queue images. Please try again.', 'beepbeep-ai-alt-text-generator'));
                    }

                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }

                    // Check for insufficient credits with remaining > 0 - offer partial generation
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
                        hideBulkProgress();
                        var remainingCount = error.remaining;
                        var usageSnapshot = getUsageSnapshot(error.usage || null);
                        var isAnonymousTrialLimit = isAnonymousTrialUsage(usageSnapshot);
                        var freePlanOffer = Math.max(0, parseInt(usageSnapshot && usageSnapshot.free_plan_offer, 10) || 50);
                        var errorMsg = error.message || sprintf(_n('You only have %d generation remaining.', 'You only have %d generations remaining.', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount);
                        var modalMessage = errorMsg + '\n\n' + sprintf(
                            isAnonymousTrialLimit
                                ? _n(
                                    'You can generate %1$d image now using your remaining trial credit, or create a free account for %2$d images per month.',
                                    'You can generate %1$d images now using your remaining trial credits, or create a free account for %2$d images per month.',
                                    remainingCount,
                                    'beepbeep-ai-alt-text-generator'
                                )
                                : _n(
                                    'You can generate %1$d image now using your remaining credit, or upgrade for more.',
                                    'You can generate %1$d images now using your remaining credits, or upgrade for more.',
                                    remainingCount,
                                    'beepbeep-ai-alt-text-generator'
                                ),
                            remainingCount,
                            freePlanOffer
                        );

                        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                            window.bbaiModal.show({
                                type: 'warning',
                                title: isAnonymousTrialLimit
                                    ? __('Trial nearly complete', 'beepbeep-ai-alt-text-generator')
                                    : __('Not enough credits', 'beepbeep-ai-alt-text-generator'),
                                message: modalMessage,
                                buttons: [
                                    {
                                        text: sprintf(_n('Use %d remaining credit', 'Use %d remaining credits', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount),
                                        primary: true,
                                        action: function() {
                                            window.bbaiModal.close();
                                            var limitedIds = ids.slice(0, remainingCount);
                                            $btn.prop('disabled', true);
                                            $btn.text(__('Loading...', 'beepbeep-ai-alt-text-generator'));
                                            showBulkProgress(sprintf(_n('Queueing %d image...', 'Queueing %d images...', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount), remainingCount, 0);

                                            queueImages(limitedIds, 'bulk', { skipSchedule: true }, function(success, queued, queueError, processedLimited) {
                                                $btn.prop('disabled', false);
                                                $btn.text(originalText);

                                                if (success && queued > 0) {
                                                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                                                    logBulkProgressSuccess(sprintf(_n('Queued %d image using remaining credits', 'Queued %d images using remaining credits', queued, 'beepbeep-ai-alt-text-generator'), queued));
                                                    startInlineGeneration(processedLimited || limitedIds, 'bulk');
                                                } else {
                                                    var queueErrMsg = __('Failed to queue images.', 'beepbeep-ai-alt-text-generator');
                                                    if (queueError && queueError.message) {
                                                        queueErrMsg = queueError.message;
                                                    }
                                                    logBulkProgressError(queueErrMsg);
                                                }
                                            });
                                        }
                                    },
                                    {
                                        text: isAnonymousTrialLimit
                                            ? __('Create free account', 'beepbeep-ai-alt-text-generator')
                                            : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                                        primary: false,
                                        action: function() {
                                            window.bbaiModal.close();
                                            if (isAnonymousTrialLimit) {
                                                openAuthSignupModal();
                                            } else {
                                                openUpgradeModal(usageSnapshot);
                                            }
                                        }
                                    },
	                    {
	                        text: __('Cancel', 'beepbeep-ai-alt-text-generator'),
	                        primary: false,
	                        action: function() {
	                            window.bbaiModal.close();
	                        }
	                    }
                                ]
                            });
                        } else {
                            // Fallback for when modal is not available
                            handleLimitReached(error);
                        }
                        return; // Exit early
                    }

                    // Handle other errors
                    var errorMsg = __('Failed to queue images.', 'beepbeep-ai-alt-text-generator');
                    if (error && error.message) {
                        errorMsg = error.message;
                    } else {
                        if (count > 0) {
                            errorMsg += ' Please check your browser console for details and try again.';
                        } else {
                            errorMsg += ' No images were found to queue.';
                        }
                    }

                    if (error && error.message) {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error details:', error);
                    } else {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Queue failed for generate missing - no error details');
                    }

                    // Keep modal open to show error - user can close manually
                }
            });
        })
        .fail(function(xhr, status, error) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to get missing images:', error, xhr);
            restoreGenerateBusyState();
            setDashboardRuntimeState('generation_failed');

            logBulkProgressError(__('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator'));
            // Keep modal open to show error - user can close manually
        });
        }
    }

    /**
     * Regenerate alt text for all images
     */
    function handleRegenerateAll(e) {
        // If this is an upgrade link (anchor with real href), let the browser navigate - don't run handler
        var el = this && this.nodeType === 1 ? this : (e && e.currentTarget);
        if (el && el.tagName === 'A') {
            var href = el.getAttribute && el.getAttribute('href');
            if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                return; // Allow default navigation
            }
        }

        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        clearModalAndScrollLocks();

        var $btn = $(this);

        if ($btn.prop('disabled')) {
            return false;
        }

        // Prevent duplicate jobs
        if (window.bbaiJobState && window.bbaiJobState.getState().running) {
            window.bbaiModal.show({
                type: 'info',
                title: __('Job in progress', 'beepbeep-ai-alt-text-generator'),
                message: __('ALT text generation is already running. Check progress in the floating widget.', 'beepbeep-ai-alt-text-generator'),
                buttons: [{ label: __('OK', 'beepbeep-ai-alt-text-generator'), type: 'primary' }]
            });
            return false;
        }

        // Check if we have necessary configuration
        if (!hasBulkConfig) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            return false;
        }

        // Check if user is out of credits BEFORE starting
        var usageStats = (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                         (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                         (window.BBAI && window.BBAI.usage) || null;

        // Check if remaining is 0 (also derive from used/limit if missing)
        var remaining = null;
        if (usageStats) {
            if (usageStats.remaining !== undefined && usageStats.remaining !== null) {
                remaining = parseInt(usageStats.remaining, 10);
            } else if (usageStats.limit !== undefined && usageStats.used !== undefined) {
                remaining = Math.max(0, parseInt(usageStats.limit, 10) - parseInt(usageStats.used, 10));
            }
            if (isNaN(remaining)) { remaining = null; }
        }
        var plan = usageStats && usageStats.plan ? usageStats.plan.toLowerCase() : 'free';

        // Check if user has quota OR is on premium plan (pro/agency)
        // Also trust server-rendered locked state: button has bbai-optimization-cta--locked or .disabled when no credits
        var isPremium = plan === 'pro' || plan === 'agency';
        var isOutOfCredits = usageStats && remaining !== null && remaining === 0;
        var isButtonLocked = $btn.hasClass('bbai-optimization-cta--locked') || $btn.hasClass('disabled');

        if (!isPremium && (isOutOfCredits || isButtonLocked)) {
            if (e && typeof e.stopPropagation === 'function') {
                e.stopPropagation();
            }
            if (e && typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            try {
                handleLimitReached({
                    message: __('Monthly limit reached. Upgrade to continue regenerating alt text.', 'beepbeep-ai-alt-text-generator'),
                    code: 'limit_reached',
                    usage: usageStats
                });
            } catch (err) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] handleLimitReached error:', err);
                openUpgradeModal(usageStats);
            }
            return false;
        }

        var $trigger = $(this);
        var regenerateScope = String($trigger.attr('data-bbai-regenerate-scope') || 'all');
        var isWeakOnlyRun = regenerateScope === 'needs-review';
        var restoreRegenerateBusyState = setBusyStateForControls(
            isWeakOnlyRun ? '[data-action="regenerate-all"][data-bbai-regenerate-scope="needs-review"]' : '[data-action="regenerate-all"], [data-bbai-action="reoptimize_all"]',
            __('Improving...', 'beepbeep-ai-alt-text-generator')
        );
        var confirmationTitle = isWeakOnlyRun
            ? __('Improve Weak ALT', 'beepbeep-ai-alt-text-generator')
            : __('Re-optimise All Images', 'beepbeep-ai-alt-text-generator');
        var confirmationMessage = isWeakOnlyRun
            ? __('This will regenerate ALT text for images that currently need review. Are you sure?', 'beepbeep-ai-alt-text-generator')
            : __('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?', 'beepbeep-ai-alt-text-generator');
        var preparingMessage = isWeakOnlyRun
            ? __('Preparing ALT improvements...', 'beepbeep-ai-alt-text-generator')
            : __('Preparing bulk regeneration...', 'beepbeep-ai-alt-text-generator');
        var noImagesMessage = isWeakOnlyRun
            ? __('No weak ALT descriptions found.', 'beepbeep-ai-alt-text-generator')
            : __('No images found.', 'beepbeep-ai-alt-text-generator');

        // Show confirmation via modal instead of native confirm() to avoid page freezing
        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
            window.bbaiModal.show({
                type: 'warning',
                title: confirmationTitle,
                message: confirmationMessage,
                buttons: [
                    {
                        text: isWeakOnlyRun
                            ? __('Yes, improve weak ALT', 'beepbeep-ai-alt-text-generator')
                            : __('Yes, re-optimise all', 'beepbeep-ai-alt-text-generator'),
                        primary: true,
                        action: function() {
                            window.bbaiModal.close();
                            proceedWithRegeneration();
                        }
                    },
                    {
                        text: __('Cancel', 'beepbeep-ai-alt-text-generator'),
                        primary: false,
                        action: function() {
                            window.bbaiModal.close();
                        }
                    }
                ]
            });
            return false;
        }

        // Fallback: native confirm if modal unavailable
        if (!confirm(confirmationMessage)) {
            restoreRegenerateBusyState();
            return false;
        }
        proceedWithRegeneration();
        return false;

        function proceedWithRegeneration() {
        setDashboardRuntimeState('generation_running');
        // Get list of target images (REST with admin-ajax fallback)
        fetchBulkImageIds(regenerateScope, 500)
        .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
                setDashboardRuntimeState('idle');
                if (window.bbaiModal && typeof window.bbaiModal.info === 'function') {
                    window.bbaiModal.info(noImagesMessage);
                }
                dispatchDashboardFeedback('info', noImagesMessage);
                restoreRegenerateBusyState();
                return;
            }

            var ids = response.ids || [];
	            var count = ids.length;

	            // Show progress bar
                    showBulkProgress(preparingMessage, count, 0);

            // Queue the requested image set
            queueImages(ids, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, error, processedIds) {
                restoreRegenerateBusyState();

		                if (success && queued > 0) {
		                    // Update modal to show success and keep it open
		                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
		                    logBulkProgressSuccess(
                                isWeakOnlyRun
                                    ? sprintf(_n('Successfully queued %d weak ALT improvement', 'Successfully queued %d weak ALT improvements', queued, 'beepbeep-ai-alt-text-generator'), queued)
                                    : sprintf(_n('Successfully queued %d image for regeneration', 'Successfully queued %d images for regeneration', queued, 'beepbeep-ai-alt-text-generator'), queued)
                            );

	                    // Trigger celebration for bulk regeneration
	                    if (window.bbaiCelebrations && typeof window.bbaiCelebrations.showConfetti === 'function') {
                        window.bbaiCelebrations.showConfetti();
                    }
		                    if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
		                        window.bbaiPushToast(
                                    'success',
                                    isWeakOnlyRun
                                        ? sprintf(_n('Successfully queued %d weak ALT improvement!', 'Successfully queued %d weak ALT improvements!', queued, 'beepbeep-ai-alt-text-generator'), queued)
                                        : sprintf(_n('Successfully queued %d image for regeneration!', 'Successfully queued %d images for regeneration!', queued, 'beepbeep-ai-alt-text-generator'), queued),
                                    { duration: 5000 }
                                );
		                    }

                    // Dispatch custom event for celebrations
                    var event = new CustomEvent('bbai:generation:success', { detail: { count: queued, type: 'bulk-regenerate' } });
                    document.dispatchEvent(event);

	                    startInlineGeneration(processedIds || ids, isWeakOnlyRun ? 'regenerate-weak' : 'regenerate-all');

	                    // Don't hide modal - let user close it manually or monitor progress
		                } else if (success && queued === 0) {
		                    updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
		                    logBulkProgressSuccess(
                                isWeakOnlyRun
                                    ? __('All weak ALT improvements are already in queue or processing', 'beepbeep-ai-alt-text-generator')
                                    : __('All images are already in queue or processing', 'beepbeep-ai-alt-text-generator')
                            );
		                    startInlineGeneration(processedIds || ids, isWeakOnlyRun ? 'regenerate-weak' : 'regenerate-all');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for quota errors FIRST - show upgrade modal immediately
                    if (isLimitReachedError(error)) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

	                    // Show error in modal log
	                    setDashboardRuntimeState('generation_failed');
	                    if (error && error.message) {
                        logBulkProgressError(error.message);
                    } else {
                        logBulkProgressError(__('Failed to queue images. Please try again.', 'beepbeep-ai-alt-text-generator'));
                    }

                    // Error logging (keep minimal for production)
                    if (error && error.code) {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error:', error.code, error.message || 'Unknown error');
                    }

                    // Check for insufficient credits with remaining > 0 - offer partial regeneration
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining > 0) {
                        hideBulkProgress();
                        var remainingCount = error.remaining;
                        var usageSnapshot = getUsageSnapshot(error.usage || null);
                        var isAnonymousTrialLimit = isAnonymousTrialUsage(usageSnapshot);
                        var freePlanOffer = Math.max(0, parseInt(usageSnapshot && usageSnapshot.free_plan_offer, 10) || 50);
                        var errorMsg = error.message || sprintf(_n('You only have %d generation remaining.', 'You only have %d generations remaining.', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount);
                        var modalMessage = errorMsg + '\n\n' + sprintf(
                            isAnonymousTrialLimit
                                ? _n(
                                    'You can regenerate %1$d image now using your remaining trial credit, or create a free account for %2$d images per month.',
                                    'You can regenerate %1$d images now using your remaining trial credits, or create a free account for %2$d images per month.',
                                    remainingCount,
                                    'beepbeep-ai-alt-text-generator'
                                )
                                : _n(
                                    'You can regenerate %1$d image now using your remaining credit, or upgrade for more.',
                                    'You can regenerate %1$d images now using your remaining credits, or upgrade for more.',
                                    remainingCount,
                                    'beepbeep-ai-alt-text-generator'
                                ),
                            remainingCount,
                            freePlanOffer
                        );

                        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                            window.bbaiModal.show({
                                type: 'warning',
                                title: isAnonymousTrialLimit
                                    ? __('Trial nearly complete', 'beepbeep-ai-alt-text-generator')
                                    : __('Not enough credits', 'beepbeep-ai-alt-text-generator'),
                                message: modalMessage,
                                buttons: [
                                    {
                                        text: sprintf(_n('Use %d remaining credit', 'Use %d remaining credits', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount),
                                        primary: true,
                                        action: function() {
                                            window.bbaiModal.close();
                                            var limitedIds = ids.slice(0, remainingCount);
                                            restoreRegenerateBusyState = setBusyStateForControls('[data-action="regenerate-all"], [data-bbai-action="reoptimize_all"]', __('Improving...', 'beepbeep-ai-alt-text-generator'));
                                            showBulkProgress(sprintf(_n('Queueing %d image for regeneration...', 'Queueing %d images for regeneration...', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount), remainingCount, 0);

                                            queueImages(limitedIds, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, queueError, processedLimited) {
                                                restoreRegenerateBusyState();

                                                if (success && queued > 0) {
                                                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                                                    logBulkProgressSuccess(sprintf(_n('Queued %d image using remaining credits', 'Queued %d images using remaining credits', queued, 'beepbeep-ai-alt-text-generator'), queued));
                                                    startInlineGeneration(processedLimited || limitedIds, 'regenerate-all');
                                                } else {
                                                    var queueErrMsg = __('Failed to queue images.', 'beepbeep-ai-alt-text-generator');
                                                    if (queueError && queueError.message) {
                                                        queueErrMsg = queueError.message;
                                                    }
                                                    logBulkProgressError(queueErrMsg);
                                                }
                                            });
                                        }
                                    },
                                    {
                                        text: isAnonymousTrialLimit
                                            ? __('Create free account', 'beepbeep-ai-alt-text-generator')
                                            : __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                                        primary: false,
                                        action: function() {
                                            window.bbaiModal.close();
                                            if (isAnonymousTrialLimit) {
                                                openAuthSignupModal();
                                            } else {
                                                openUpgradeModal(usageSnapshot);
                                            }
                                        }
                                    },
                                    {
                                        text: __('Cancel', 'beepbeep-ai-alt-text-generator'),
                                        primary: false,
                                        action: function() {
                                            window.bbaiModal.close();
                                        }
                                    }
                                ]
                            });
                        } else {
                            // Fallback for when modal is not available
                            handleLimitReached(error);
                        }
                        return; // Exit early
                    }

                    // Handle other errors
                    var errorMsg = __('Failed to queue images.', 'beepbeep-ai-alt-text-generator');
	                    if (error && error.message) {
	                        errorMsg = error.message;
	                    } else {
	                        if (count > 0) {
	                            errorMsg += ' ' + __('Please check your browser console for details and try again.', 'beepbeep-ai-alt-text-generator');
	                        } else {
	                            errorMsg += ' ' + __('No images were found to queue.', 'beepbeep-ai-alt-text-generator');
	                        }
	                    }

                    if (error && error.message) {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error details:', error);
                    } else {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Queue failed for regenerate all - no error details');
                    }

                    // Keep modal open to show error - user can close manually
                }
            });
        })
        .fail(function(xhr, status, error) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to get all images:', error, xhr);
            restoreRegenerateBusyState();
            setDashboardRuntimeState('generation_failed');

            logBulkProgressError(__('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator'));
            // Keep modal open to show error - user can close manually
        });
        } // end proceedWithRegeneration
    }

    function runBulkFixAllIssues(trigger) {
        var button = trigger || document.querySelector('[data-action="fix-all-images-automatically"]');
        if (!button) {
            return false;
        }

        if (!hasBulkConfig) {
            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            }
            return false;
        }

        if (isLockedBulkControl(button)) {
            handleLockedCtaClick(button, { preventDefault: function() {} });
            return false;
        }

        var usageStats = getUsageSnapshot(null);
        var plan = usageStats && usageStats.plan ? String(usageStats.plan).toLowerCase() : 'free';
        var hasGrowthAccess = plan === 'growth' || plan === 'agency' || plan === 'pro';
        if (!hasGrowthAccess) {
            handleLockedCtaClick(button, { preventDefault: function() {} });
            return false;
        }

        var restoreBusyState = setBusyStateForControls(
            '[data-action="fix-all-images-automatically"]',
            __('Optimizing...', 'beepbeep-ai-alt-text-generator')
        );

        $.when(fetchBulkImageIds('missing', 500), fetchBulkImageIds('needs-review', 500))
            .done(function(missingResponse, weakResponse) {
                var ids = [];
                [missingResponse, weakResponse].forEach(function(response) {
                    var responseIds = response && Array.isArray(response.ids) ? response.ids : [];
                    responseIds.forEach(function(id) {
                        var parsedId = parseInt(id, 10);
                        if (!isNaN(parsedId) && parsedId > 0 && ids.indexOf(parsedId) === -1) {
                            ids.push(parsedId);
                        }
                    });
                });

                if (!ids.length) {
                    restoreBusyState();
                    if (typeof window.bbaiRefreshDashboardCoverage === 'function') {
                        window.bbaiRefreshDashboardCoverage();
                    }
                    dispatchDashboardFeedback('info', __('No ALT issues found. Your library is already up to date.', 'beepbeep-ai-alt-text-generator'));
                    return;
                }

                var count = ids.length;
                showBulkProgress(__('Optimizing ALT text...', 'beepbeep-ai-alt-text-generator'), count, 0);
                setBulkProgressHelperText(getBulkOptimizationProcessingLabel(count));

                queueImages(ids, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, error, processedIds) {
                    restoreBusyState();

                    if (success && queued > 0) {
                        updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                        logBulkProgressSuccess(sprintf(
                            _n(
                                'Successfully queued %d image for automatic optimization',
                                'Successfully queued %d images for automatic optimization',
                                queued,
                                'beepbeep-ai-alt-text-generator'
                            ),
                            queued
                        ));
                        startInlineGeneration(processedIds || ids, 'fix-all-issues');
                        return;
                    }

                    if (success && queued === 0) {
                        updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
                        logBulkProgressSuccess(__('All matching images are already in queue or processing', 'beepbeep-ai-alt-text-generator'));
                        startInlineGeneration(processedIds || ids, 'fix-all-issues');
                        return;
                    }

                    hideBulkProgress();
                    if (isLimitReachedError(error)) {
                        handleLimitReached(error);
                        return;
                    }

                    if (error && error.message) {
                        dispatchDashboardFeedback('error', error.message);
                        logBulkProgressError(error.message);
                    } else {
                        var fallbackMessage = __('Failed to queue images for optimization. Please try again.', 'beepbeep-ai-alt-text-generator');
                        dispatchDashboardFeedback('error', fallbackMessage);
                        logBulkProgressError(fallbackMessage);
                    }
                });
            })
            .fail(function(xhr) {
                restoreBusyState();
                hideBulkProgress();
                var errorMessage = __('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator');
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                dispatchDashboardFeedback('error', errorMessage);
            });

        return false;
    }

    function formatDashboardNumber(value) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed < 0) {
            parsed = 0;
        }
        return parsed.toLocaleString();
    }

    function formatDashboardDecimal(value) {
        var parsed = parseFloat(value);
        if (!isFinite(parsed) || parsed < 0) {
            parsed = 0;
        }
        return parsed.toLocaleString(undefined, {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        });
    }

    function getDashboardRootNode() {
        return document.querySelector('[data-bbai-dashboard-root="1"]');
    }

    function getExistingDashboardStats() {
        if (window.BBAI_DASH && window.BBAI_DASH.stats && typeof window.BBAI_DASH.stats === 'object') {
            return window.BBAI_DASH.stats;
        }
        if (window.BBAI && window.BBAI.stats && typeof window.BBAI.stats === 'object') {
            return window.BBAI.stats;
        }
        return {};
    }

    function readDashboardStatsFromRoot() {
        var root = getDashboardRootNode();
        if (!root) {
            return null;
        }

        return normalizeDashboardStatsPayload({
            total_images: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-total-count')),
            images_with_alt: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-optimized-count')),
            images_missing_alt: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-missing-count')),
            needs_review_count: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-weak-count')),
            optimized_count: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-optimized-count'))
        });
    }

    /**
     * Full-library coverage snapshot for the ALT Library workspace (not paginated table rows).
     */
    function readLibraryWorkspaceStatsFromRoot() {
        var root = getLibraryWorkspaceRoot();
        if (!root) {
            return null;
        }

        return {
            total_images: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-total-count')),
            images_missing_alt: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-missing-count')),
            needs_review_count: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-weak-count')),
            optimized_count: attrToOptionalNonNegativeInt(root.getAttribute('data-bbai-optimized-count'))
        };
    }

    function normalizeLibraryCountState(state) {
        var normalized = normalizeLibraryStatusFilter(state);
        return normalized === 'missing' || normalized === 'weak' || normalized === 'optimized'
            ? normalized
            : '';
    }

    function getLibraryWorkspaceCountsSnapshot() {
        var snapshot = readLibraryWorkspaceStatsFromRoot() || {};
        var total = attrToOptionalNonNegativeInt(snapshot.total_images);
        var missing = attrToOptionalNonNegativeInt(snapshot.images_missing_alt);
        var weak = attrToOptionalNonNegativeInt(snapshot.needs_review_count);
        var optimized = attrToOptionalNonNegativeInt(snapshot.optimized_count);

        missing = missing === null ? 0 : missing;
        weak = weak === null ? 0 : weak;
        optimized = optimized === null ? 0 : optimized;
        total = total === null ? Math.max(0, missing + weak + optimized) : total;

        return {
            total: Math.max(0, total),
            missing: Math.max(0, missing),
            weak: Math.max(0, weak),
            optimized: Math.max(0, optimized)
        };
    }

    /**
     * When a missing-row generate succeeds but AJAX/REST stats are missing or delayed,
     * nudge filter pills: one fewer missing, one more needs-review (typical post-AI state).
     * Fresh stats from refreshLibraryWorkspaceStatsFromRest() will reconcile shortly after.
     */
    function applyOptimisticLibraryCountsAfterMissingResolved() {
        var root = getLibraryWorkspaceRoot();
        if (!root) {
            return;
        }
        var total = parseInt(root.getAttribute('data-bbai-total-count'), 10);
        var missing = parseInt(root.getAttribute('data-bbai-missing-count'), 10);
        var weak = parseInt(root.getAttribute('data-bbai-weak-count'), 10);
        var optimized = parseInt(root.getAttribute('data-bbai-optimized-count'), 10);
        if (isNaN(missing) || missing <= 0) {
            return;
        }
        if (isNaN(weak) || weak < 0) {
            weak = 0;
        }
        if (isNaN(optimized) || optimized < 0) {
            optimized = 0;
        }
        if (isNaN(total) || total < 0) {
            total = 0;
        }
        var newMissing = missing - 1;
        var newWeak = weak + 1;
        var newTotal = total > 0 ? total : Math.max(0, newMissing + newWeak + optimized);
        applyLibraryWorkspaceCountsSnapshot({
            total: newTotal,
            missing: newMissing,
            weak: newWeak,
            optimized: optimized
        });
    }

    function applyLibraryWorkspaceCountsSnapshot(counts) {
        var root = getLibraryWorkspaceRoot();
        var snapshot = counts && typeof counts === 'object' ? counts : getLibraryWorkspaceCountsSnapshot();
        var total = Math.max(0, parseInt(snapshot.total, 10) || 0);
        var missing = Math.max(0, parseInt(snapshot.missing, 10) || 0);
        var weak = Math.max(0, parseInt(snapshot.weak, 10) || 0);
        var optimized = Math.max(0, parseInt(snapshot.optimized, 10) || 0);

        if (root) {
            root.setAttribute('data-bbai-total-count', String(total));
            root.setAttribute('data-bbai-missing-count', String(missing));
            root.setAttribute('data-bbai-weak-count', String(weak));
            root.setAttribute('data-bbai-optimized-count', String(optimized));
            root.setAttribute('data-bbai-is-healthy', (missing === 0 && weak === 0 && total > 0) ? 'true' : 'false');
        }

        applyLibraryReviewFilterCountsObject({
            all: total,
            missing: missing,
            weak: weak,
            optimized: optimized,
            'needs-review': weak,
            needs_review: weak
        });

        if (window.BBAI_DASH) {
            window.BBAI_DASH.stats = $.extend({}, window.BBAI_DASH.stats || {}, {
                total: total,
                total_images: total,
                missing: missing,
                images_missing_alt: missing,
                needs_review_count: weak,
                optimized_count: optimized
            });
        }

        if (window.BBAI) {
            window.BBAI.stats = $.extend({}, window.BBAI.stats || {}, {
                total: total,
                total_images: total,
                missing: missing,
                images_missing_alt: missing,
                needs_review_count: weak,
                optimized_count: optimized
            });
        }

        renderLibraryWorkflowDecisionSurfaces();
        syncLibraryMissingBulkBar();
    }

    function syncLibraryWorkspaceCountsForTransition(previousState, nextState) {
        var prev = normalizeLibraryCountState(previousState);
        var next = normalizeLibraryCountState(nextState);
        var counts;

        if (!prev && !next) {
            return;
        }

        if (prev === next) {
            return;
        }

        counts = getLibraryWorkspaceCountsSnapshot();

        if (prev && Object.prototype.hasOwnProperty.call(counts, prev)) {
            counts[prev] = Math.max(0, counts[prev] - 1);
        }

        if (next && Object.prototype.hasOwnProperty.call(counts, next)) {
            counts[next] += 1;
        }

        applyLibraryWorkspaceCountsSnapshot(counts);
    }

    function getLibraryWorkspaceUsageSnapshot() {
        var usage = getUsageSnapshot(null);
        var root;

        if (usage) {
            return usage;
        }

        root = getLibraryWorkspaceRoot();
        if (!root) {
            return null;
        }

        return normalizeUsagePayload({
            credits_used: root.getAttribute('data-bbai-credits-used'),
            credits_total: root.getAttribute('data-bbai-credits-total'),
            credits_remaining: root.getAttribute('data-bbai-credits-remaining'),
            auth_state: root.getAttribute('data-bbai-auth-state'),
            quota_type: root.getAttribute('data-bbai-quota-type'),
            quota_state: root.getAttribute('data-bbai-quota-state'),
            signup_required: root.getAttribute('data-bbai-signup-required') === '1',
            free_plan_offer: root.getAttribute('data-bbai-free-plan-offer'),
            low_credit_threshold: root.getAttribute('data-bbai-low-credit-threshold')
        });
    }

    function applyLibraryWorkspaceUsageAttributes(usage) {
        var root = getLibraryWorkspaceRoot();
        var normalized = normalizeUsagePayload(usage);

        if (!root || !normalized) {
            return;
        }

        root.setAttribute('data-bbai-credits-used', String(Math.max(0, parseInt(normalized.used, 10) || 0)));
        root.setAttribute('data-bbai-credits-total', String(Math.max(1, parseInt(normalized.limit, 10) || 50)));
        root.setAttribute('data-bbai-credits-remaining', String(Math.max(0, parseInt(normalized.remaining, 10) || 0)));
        var keepGuestTrial = root.getAttribute('data-bbai-is-guest-trial') === '1' || isAnonymousTrialUsage(normalized);
        if (keepGuestTrial) {
            root.setAttribute('data-bbai-auth-state', 'anonymous');
            root.setAttribute('data-bbai-quota-type', 'trial');
        } else {
            root.setAttribute('data-bbai-auth-state', String(normalized.auth_state || ''));
            root.setAttribute('data-bbai-quota-type', String(normalized.quota_type || ''));
        }
        root.setAttribute('data-bbai-quota-state', String(normalized.quota_state || ''));
        root.setAttribute('data-bbai-signup-required', normalized.signup_required ? '1' : '0');
        root.setAttribute('data-bbai-free-plan-offer', String(Math.max(0, parseInt(normalized.free_plan_offer, 10) || 50)));
        root.setAttribute('data-bbai-low-credit-threshold', String(Math.max(0, parseInt(normalized.low_credit_threshold, 10) || 0)));
        root.setAttribute('data-bbai-is-guest-trial', keepGuestTrial ? '1' : '0');
        root.setAttribute('data-bbai-is-low-credits', normalized.remaining > 0 && normalized.remaining <= Math.max(0, parseInt(normalized.low_credit_threshold, 10) || 0) ? 'true' : 'false');
        root.setAttribute('data-bbai-is-out-of-credits', normalized.remaining <= 0 ? 'true' : 'false');
    }

    function applyLibraryUsageAfterGeneration(usageInput, spentCount) {
        if (!usageInput) {
            if (typeof window.alttextai_refresh_usage === 'function') {
                window.alttextai_refresh_usage();
            }
            return null;
        }

        var normalized = mirrorUsagePayload(usageInput);

        if (!normalized) {
            return null;
        }

        applyLibraryWorkspaceUsageAttributes(normalized);
        updateLibraryUsageSurface(normalized);
        syncSharedUsageBanner(normalized);
        enforceOutOfCreditsBulkLocks();
        applyDashboardState(false);
        $(document).trigger('bbai:usage-updated');

        return normalized;
    }

    function buildLibraryWorkflowBulkAction(limit, label, source) {
        return {
            kind: 'generate',
            limit: Math.max(0, parseInt(limit, 10) || 0),
            label: label || '',
            source: source || 'library-workflow'
        };
    }

    function buildLibraryWorkflowAccountAction(kind, label, source) {
        return {
            kind: kind,
            label: label || '',
            source: source || 'library-workflow'
        };
    }

    function getLibraryWorkflowDecisionModel() {
        var counts = getLibraryWorkspaceCountsSnapshot();
        var usage = getLibraryWorkspaceUsageSnapshot();
        var missing = Math.max(0, parseInt(counts.missing, 10) || 0);
        var remaining = usage ? Math.max(0, parseInt(usage.remaining, 10) || 0) : 0;
        var isTrial = usage ? isAnonymousTrialUsage(usage) : false;
        var isPro = isLibraryProPlan();
        var blocked = missing > 0 && !isPro && remaining <= 0;
        var allowedNow = missing > 0 && !blocked ? missing : 0;
        var limited = false;
        var ready = missing > 0 && !blocked;
        var remainingLabel = formatDashboardNumber(remaining);
        var missingLabel = formatDashboardNumber(missing);
        var allowedLabel = formatDashboardNumber(allowedNow);
        var remainingAfterRun = 0;
        var creditsLine = isTrial
            ? sprintf(
                _n('%s trial credit left', '%s trial credits left', remaining, 'beepbeep-ai-alt-text-generator'),
                remainingLabel
            )
            : sprintf(
                _n('%s credit left this month', '%s credits left this month', remaining, 'beepbeep-ai-alt-text-generator'),
                remainingLabel
            );
        var missingLine = sprintf(
            _n('%s image still needs ALT', '%s images still need ALT', missing, 'beepbeep-ai-alt-text-generator'),
            missingLabel
        );
        var model = {
            visible: missing > 0,
            missing: missing,
            remaining: remaining,
            allowedNow: allowedNow,
            limited: limited,
            blocked: blocked,
            ready: ready,
            variant: blocked ? 'blocked' : 'ready',
            eyebrow: blocked
                ? __('Credits exhausted', 'beepbeep-ai-alt-text-generator')
                : __('Ready to optimize', 'beepbeep-ai-alt-text-generator'),
            title: missingLine,
            copy: blocked
                ? (isTrial
                    ? sprintf(
                        __('Free trial complete. Create a free account to finish the remaining %s images.', 'beepbeep-ai-alt-text-generator'),
                        missingLabel
                    )
                    : sprintf(
                        __('0 credits left this month. Upgrade to finish the remaining %s images.', 'beepbeep-ai-alt-text-generator'),
                        missingLabel
                    ))
                : sprintf(
                    __('%1$s. One run sends every missing image; the server applies your credit limit and returns exact counts.', 'beepbeep-ai-alt-text-generator'),
                    creditsLine
                ),
            stats: [
                { value: remainingLabel, label: isTrial ? __('Trial usage — credits remaining', 'beepbeep-ai-alt-text-generator') : __('credits left this month', 'beepbeep-ai-alt-text-generator') },
                { value: missingLabel, label: __('images still need ALT', 'beepbeep-ai-alt-text-generator') },
                { value: blocked ? '0' : missingLabel, label: __('queued on “Generate all missing”', 'beepbeep-ai-alt-text-generator') }
            ],
            note: blocked
                ? __('Manual editing is still available in the rows below.', 'beepbeep-ai-alt-text-generator')
                : __('After the run, trial usage and this list update from the server response.', 'beepbeep-ai-alt-text-generator'),
            workspaceSummary: blocked
                ? __('No credits left right now. Upgrade to finish the queue.', 'beepbeep-ai-alt-text-generator')
                : sprintf(
                    __('%1$s. Missing images: %2$s.', 'beepbeep-ai-alt-text-generator'),
                    creditsLine,
                    missingLabel
                ),
            primaryAction: null,
            secondaryAction: null
        };

        if (!model.visible) {
            return model;
        }

        if (blocked) {
            model.primaryAction = buildLibraryWorkflowAccountAction(
                isTrial ? 'signup' : 'upgrade',
                isTrial
                    ? __('Create free account', 'beepbeep-ai-alt-text-generator')
                    : sprintf(
                        __('Upgrade to finish all %s', 'beepbeep-ai-alt-text-generator'),
                        missingLabel
                    ),
                'library-workflow-primary-blocked'
            );
            return model;
        }

        model.primaryAction = buildLibraryWorkflowBulkAction(
            allowedNow,
            __('Optimize all missing', 'beepbeep-ai-alt-text-generator'),
            'library-workflow-primary-ready'
        );

        return model;
    }

    /**
     * Sync top library hero primary CTA with credit-aware bulk limit (replaces duplicate bulk banners).
     */
    function syncLibraryTopHeroGenerateMissingCta() {
        var card = document.querySelector('[data-bbai-library-workspace-root="1"] [data-bbai-library-surface="1"]');
        if (!card) {
            return;
        }
        var hero = card.querySelector('[data-bbai-shared-command-hero="1"]');
        if (!hero) {
            return;
        }
        var btn = hero.querySelector('[data-bbai-hero-primary-item] button[data-action="generate-missing"]');
        if (!btn) {
            btn = hero.querySelector('button[data-action="generate-missing"]');
        }
        if (!btn) {
            return;
        }

        var model = getLibraryWorkflowDecisionModel();
        if (!model.visible) {
            btn.removeAttribute('data-bbai-bulk-limit');
            return;
        }

        if (model.blocked || !model.primaryAction) {
            btn.removeAttribute('data-bbai-bulk-limit');
            return;
        }

        if (model.primaryAction.kind === 'generate') {
            var lim = Math.max(0, parseInt(model.primaryAction.limit, 10) || 0);
            if (lim > 0) {
                btn.setAttribute('data-bbai-bulk-limit', String(lim));
            } else {
                btn.removeAttribute('data-bbai-bulk-limit');
            }
            btn.setAttribute('data-bbai-lock-preserve-label', '1');
            if (model.primaryAction.label) {
                btn.textContent = model.primaryAction.label;
            }
        }
    }

    /**
     * Keep visible banner stat chips aligned with workspace counts + usage.
     */
    function updateLibraryBannerInlineChips() {
        var card = document.querySelector('[data-bbai-library-workspace-root="1"] [data-bbai-library-surface="1"]');
        if (!card) {
            return;
        }
        var statsRoot = card.querySelector('.bbai-dashboard-hero__inline-stats');
        if (!statsRoot) {
            return;
        }
        var counts = getLibraryWorkspaceCountsSnapshot();
        var usage = getLibraryWorkspaceUsageSnapshot() || {};
        var missing = Math.max(0, parseInt(counts.missing, 10) || 0);
        var remaining = Math.max(0, parseInt(usage.remaining, 10) || 0);
        var vals = statsRoot.querySelectorAll('.bbai-dashboard-hero__inline-stat-value');
        if (vals.length >= 2) {
            vals[0].textContent = formatDashboardNumber(remaining);
            vals[1].textContent = formatDashboardNumber(missing);
        }
    }

    function renderLibraryWorkflowDecisionSurfaces() {
        var topHost = document.querySelector('[data-bbai-library-credits-banner-host]');
        var workspaceHost = document.querySelector('[data-bbai-library-workspace-cta-host]');
        [topHost, workspaceHost].forEach(function(host) {
            if (!host) {
                return;
            }
            host.innerHTML = '';
            host.hidden = true;
            host.setAttribute('aria-hidden', 'true');
        });
        syncLibraryTopHeroGenerateMissingCta();
        updateLibraryBannerInlineChips();
    }

    /**
     * Valid non-negative int from attribute string, or null if absent (so normalize can fall back).
     */
    function attrToOptionalNonNegativeInt(attrVal) {
        if (attrVal === null || attrVal === '') {
            return null;
        }
        var n = parseInt(String(attrVal), 10);
        if (!Number.isFinite(n) || n < 0) {
            return null;
        }
        return n;
    }

    /**
     * Parse non-negative int; null means absent/invalid (0 is valid and returns 0).
     */
    function parseOptionalNonNegativeInt(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        var n = parseInt(value, 10);
        if (!Number.isFinite(n) || n < 0) {
            return null;
        }
        return n;
    }

    function nonNegativeIntFromRawOrFallback(raw, key, fallbackObj, fallbackKey) {
        var fk = fallbackKey != null ? fallbackKey : key;
        if (Object.prototype.hasOwnProperty.call(raw, key)) {
            var a = parseOptionalNonNegativeInt(raw[key]);
            if (a !== null) {
                return a;
            }
        }
        if (Object.prototype.hasOwnProperty.call(fallbackObj, fk)) {
            var b = parseOptionalNonNegativeInt(fallbackObj[fk]);
            if (b !== null) {
                return b;
            }
        }
        return 0;
    }

    function nonNegativeFieldWithAlias(raw, fallback, primaryKey, aliasKey) {
        if (Object.prototype.hasOwnProperty.call(raw, primaryKey)) {
            var p = parseOptionalNonNegativeInt(raw[primaryKey]);
            if (p !== null) {
                return p;
            }
        }
        if (Object.prototype.hasOwnProperty.call(raw, aliasKey)) {
            var ra = parseOptionalNonNegativeInt(raw[aliasKey]);
            if (ra !== null) {
                return ra;
            }
        }
        if (Object.prototype.hasOwnProperty.call(fallback, primaryKey)) {
            var fp = parseOptionalNonNegativeInt(fallback[primaryKey]);
            if (fp !== null) {
                return fp;
            }
        }
        if (Object.prototype.hasOwnProperty.call(fallback, aliasKey)) {
            var fa = parseOptionalNonNegativeInt(fallback[aliasKey]);
            if (fa !== null) {
                return fa;
            }
        }
        return 0;
    }

    function normalizeDashboardStatsPayload(payload) {
        var raw = payload && typeof payload === 'object' ? payload : {};
        var fallback = getExistingDashboardStats();
        var totalImages = Math.max(0, nonNegativeFieldWithAlias(raw, fallback, 'total_images', 'total'));
        var imagesWithAlt = Math.max(0, nonNegativeFieldWithAlias(raw, fallback, 'images_with_alt', 'with_alt'));
        var missingCount = Math.max(0, nonNegativeFieldWithAlias(raw, fallback, 'images_missing_alt', 'missing'));
        var weakCount = Math.max(0, nonNegativeIntFromRawOrFallback(raw, 'needs_review_count', fallback, 'needs_review_count'));
        var optimizedFromRaw = Object.prototype.hasOwnProperty.call(raw, 'optimized_count')
            ? parseOptionalNonNegativeInt(raw.optimized_count)
            : null;
        var optimizedFromFallback = Object.prototype.hasOwnProperty.call(fallback, 'optimized_count')
            ? parseOptionalNonNegativeInt(fallback.optimized_count)
            : null;
        var optimizedCount = Math.max(
            0,
            optimizedFromRaw !== null
                ? optimizedFromRaw
                : (optimizedFromFallback !== null ? optimizedFromFallback : Math.max(0, imagesWithAlt - weakCount))
        );
        var withAltCoverage = parseInt(raw.coverage_percent, 10);
        if (isNaN(withAltCoverage)) {
            withAltCoverage = parseInt(raw.coverage, 10);
        }
        if (isNaN(withAltCoverage)) {
            withAltCoverage = parseInt(fallback.coverage_percent, 10);
        }
        if (isNaN(withAltCoverage)) {
            withAltCoverage = totalImages > 0 ? Math.round((imagesWithAlt / totalImages) * 100) : 0;
        }
        var optimizedPercent = totalImages > 0 ? Math.round((optimizedCount / totalImages) * 100) : 0;
        var generatedCount = parseInt(raw.generated, 10);
        if (isNaN(generatedCount) || generatedCount < 0) {
            generatedCount = parseInt(fallback.generated, 10);
        }
        if (isNaN(generatedCount) || generatedCount < 0) {
            var generatedNode = document.querySelector('[data-bbai-performance-generated]');
            generatedCount = generatedNode ? parseInt(String(generatedNode.textContent || '').replace(/,/g, ''), 10) : 0;
        }
        generatedCount = isNaN(generatedCount) || generatedCount < 0 ? 0 : generatedCount;

        return $.extend({}, fallback, raw, {
            total: totalImages,
            total_images: totalImages,
            with_alt: imagesWithAlt,
            images_with_alt: imagesWithAlt,
            missing: missingCount,
            images_missing_alt: missingCount,
            needs_review_count: weakCount,
            optimized_count: optimizedCount,
            coverage_percent: Math.max(0, Math.min(100, withAltCoverage)),
            optimized_percent: Math.max(0, Math.min(100, optimizedPercent)),
            generated: generatedCount
        });
    }

    function buildDashboardOptimizedRatio(stats) {
        var safeTotal = Math.max(0, parseInt(stats && stats.total_images, 10) || 0);
        if (safeTotal <= 0) {
            return __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', safeTotal, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(stats && stats.optimized_count),
            formatDashboardNumber(safeTotal)
        );
    }

    function buildDashboardStatusSummary(statsInput) {
        var stats = normalizeDashboardStatsPayload(statsInput);
        if (stats.total_images <= 0) {
            return '';
        }

        if (stats.images_missing_alt > 0) {
            return sprintf(
                _n('%s image missing ALT text', '%s images missing ALT text', stats.images_missing_alt, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(stats.images_missing_alt)
            );
        }

        if (stats.needs_review_count > 0) {
            return sprintf(
                _n('%s description could be improved', '%s descriptions could be improved', stats.needs_review_count, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(stats.needs_review_count)
            );
        }

        return __('All descriptions look good', 'beepbeep-ai-alt-text-generator');
    }

    function updateDashboardHero(statsInput) {
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        if (!hero) {
            return;
        }

        if (hero.getAttribute('data-bbai-top-banner-mode') === 'plan') {
            return;
        }

        var stats = normalizeDashboardStatsPayload(statsInput);
        var root = getDashboardRootNode();
        var hasScanResults = stats.total_images > 0 || stats.images_missing_alt > 0 || stats.needs_review_count > 0 || stats.optimized_count > 0;

        if (root) {
            root.setAttribute('data-bbai-total-count', String(stats.total_images));
            root.setAttribute('data-bbai-optimized-count', String(stats.optimized_count));
            root.setAttribute('data-bbai-missing-count', String(stats.images_missing_alt));
            root.setAttribute('data-bbai-weak-count', String(stats.needs_review_count));
            root.setAttribute('data-bbai-has-scan-results', hasScanResults ? '1' : '0');
        }

        hero.setAttribute('data-bbai-banner-missing-count', String(stats.images_missing_alt));
        hero.setAttribute('data-bbai-banner-weak-count', String(stats.needs_review_count));

        if (typeof window.bbaiGetDashboardData === 'function' && typeof window.bbaiRenderDashboardHero === 'function') {
            window.bbaiRenderDashboardHero(window.bbaiGetDashboardData());
            return;
        }

        // Use the shared banner state builder so headline/subtext match the ALT Library banner.
        if (typeof window.bbaiBuildSharedCommandHeroState === 'function') {
            var usageSnap = typeof getUsageSnapshot === 'function' ? getUsageSnapshot(null) : null;
            var guestTrialHero = root && root.getAttribute('data-bbai-is-guest-trial') === '1';
            var remHero = usageSnap && usageSnap.remaining !== undefined
                ? Math.max(0, parseInt(usageSnap.remaining, 10) || 0)
                : (root ? Math.max(0, parseInt(root.getAttribute('data-bbai-credits-remaining'), 10) || 0) : 0);
            var limHero = usageSnap && usageSnap.limit !== undefined
                ? Math.max(1, parseInt(usageSnap.limit, 10) || 50)
                : (root ? Math.max(1, parseInt(root.getAttribute('data-bbai-credits-total'), 10) || 50) : 50);
            var authHero = guestTrialHero
                ? 'anonymous'
                : String((usageSnap && usageSnap.auth_state) || (root && root.getAttribute('data-bbai-auth-state')) || '');
            var quotaHero = guestTrialHero
                ? 'trial'
                : String((usageSnap && usageSnap.quota_type) || (root && root.getAttribute('data-bbai-quota-type')) || '');
            var sharedState = window.bbaiBuildSharedCommandHeroState({
                missing_count: stats.images_missing_alt,
                weak_count: stats.needs_review_count,
                credits_used: stats.credits_used,
                credits_limit: stats.credits_limit,
                credits_remaining: stats.credits_remaining,
                creditsRemaining: remHero,
                creditsLimit: limHero,
                total_images: stats.total_images,
                authState: authHero,
                quotaType: quotaHero,
                isTrial: guestTrialHero || isAnonymousTrialUsage(usageSnap || {}),
                isGuestTrial: guestTrialHero,
                libraryUrl: root ? root.getAttribute('data-bbai-library-url') || '#' : '#',
                needsReviewLibraryUrl: root
                    ? root.getAttribute('data-bbai-needs-review-library-url') || root.getAttribute('data-bbai-library-url') || '#'
                    : '#'
            });
            if (sharedState) {
                var headlineEl = hero.querySelector('[data-bbai-hero-headline]');
                if (headlineEl && sharedState.headline) {
                    headlineEl.textContent = sharedState.headline;
                }
                var subtextEl = hero.querySelector('[data-bbai-hero-subtext]');
                if (subtextEl && sharedState.subtext) {
                    subtextEl.textContent = sharedState.subtext;
                }
                return;
            }
        }

        // Final fallback — matches shared banner copy as closely as possible.
        var headline;
        var subtext;

        if (stats.total_images <= 0) {
            headline = __('Get started with your media library', 'beepbeep-ai-alt-text-generator');
            subtext = __('Scan your library to find images missing ALT text and generate descriptions faster.', 'beepbeep-ai-alt-text-generator');
        } else if (stats.images_missing_alt > 0 || stats.needs_review_count > 0) {
            headline = __('Your library needs attention', 'beepbeep-ai-alt-text-generator');
            subtext = __('Some images are missing ALT text or need a stronger description.', 'beepbeep-ai-alt-text-generator');
        } else {
            headline = __('Your library is optimized', 'beepbeep-ai-alt-text-generator');
            subtext = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
        }

        var headlineNode = hero.querySelector('[data-bbai-hero-headline]');
        if (headlineNode) {
            headlineNode.textContent = headline;
        }

        var subtextNode = hero.querySelector('[data-bbai-hero-subtext]');
        if (subtextNode) {
            subtextNode.textContent = subtext;
        }
    }

    function getDashboardGeneratorModalOptions() {
        var stats = normalizeDashboardStatsPayload(readDashboardStatsFromRoot() || {});
        var selectedCount = getSelectedLibraryIds().length;
        var options = [
            {
                id: 'missing',
                count: Math.max(0, stats.images_missing_alt),
                label: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                copy: __('Generate ALT text only for images that do not yet have descriptions.', 'beepbeep-ai-alt-text-generator'),
                recommended: true
            },
            {
                id: 'weak',
                count: Math.max(0, stats.needs_review_count),
                label: __('Improve weak ALT text', 'beepbeep-ai-alt-text-generator'),
                copy: __('Regenerate ALT text for images with low-quality descriptions.', 'beepbeep-ai-alt-text-generator')
            },
            {
                id: 'all',
                count: Math.max(0, stats.total_images),
                label: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                copy: __('Run ALT generation across your entire media library.', 'beepbeep-ai-alt-text-generator')
            },
            {
                id: 'selected',
                count: Math.max(0, selectedCount),
                label: __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator'),
                copy: __('Select images in the ALT Library and return here to generate ALT text for that selection.', 'beepbeep-ai-alt-text-generator')
            }
        ];

        options.forEach(function(option) {
            option.disabled = option.count <= 0;
        });

        return {
            options: options,
            defaultScope: stats.images_missing_alt > 0 ? 'missing' : (stats.needs_review_count > 0 ? 'weak' : 'all')
        };
    }

    function getDashboardGeneratorUsageState() {
        var usage = getUsageSnapshot(null) ||
            normalizeUsagePayload(getUsageFromDom()) ||
            normalizeUsagePayload(getUsageFromBanner(getSharedBannerNode())) ||
            {};
        var used = parseInt(usage.used, 10);
        var limit = parseInt(usage.limit, 10);
        var remaining = parseInt(usage.remaining, 10);
        var hasKnownRemaining = !isNaN(remaining);
        if (isNaN(used) || used < 0) {
            used = 0;
        }
        if (isNaN(limit) || limit < 0) {
            limit = 0;
        }
        if (isNaN(remaining)) {
            remaining = limit > 0 ? Math.max(0, limit - used) : 0;
            hasKnownRemaining = limit > 0;
        }

        return {
            usage: usage,
            used: used,
            limit: limit,
            remaining: Math.max(0, remaining),
            hasKnownRemaining: hasKnownRemaining,
            planTier: normalizePlanTier(usage.plan_type || usage.plan)
        };
    }

    function getGeneratorEstimateCopy(option, usageState) {
        var count = option ? Math.max(0, parseInt(option.count, 10) || 0) : 0;
        var imageCountCopy = sprintf(
            _n('%s image will be processed', '%s images will be processed', count, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(count)
        );
        var creditsCopy = sprintf(
            _n('%s credit will be used', '%s credits will be used', count, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(count)
        );

        if (!option) {
            return {
                imageSummary: imageCountCopy,
                creditSummary: creditsCopy,
                note: __('Choose an option to continue.', 'beepbeep-ai-alt-text-generator'),
                warning: false,
                overLimit: false,
                unavailable: true
            };
        }

        if (option.id === 'selected' && count <= 0) {
            return {
                imageSummary: imageCountCopy,
                creditSummary: creditsCopy,
                note: __('Select images in the ALT Library first.', 'beepbeep-ai-alt-text-generator'),
                warning: false,
                overLimit: false,
                unavailable: true
            };
        }

        if (count <= 0) {
            return {
                imageSummary: imageCountCopy,
                creditSummary: creditsCopy,
                note: __('No matching images are available for this action right now.', 'beepbeep-ai-alt-text-generator'),
                warning: false,
                overLimit: false,
                unavailable: true
            };
        }

        var remaining = usageState && typeof usageState.remaining === 'number' ? usageState.remaining : 0;
        if (usageState && usageState.hasKnownRemaining && count > remaining) {
            return {
                imageSummary: imageCountCopy,
                creditSummary: creditsCopy,
                note: __('Upgrade to continue generating ALT text.', 'beepbeep-ai-alt-text-generator'),
                warning: true,
                overLimit: true,
                unavailable: false
            };
        }

        return {
            imageSummary: imageCountCopy,
            creditSummary: creditsCopy,
            note: '',
            warning: false,
            overLimit: false,
            unavailable: false
        };
    }

    function normalizeGeneratorIds(ids) {
        if (!Array.isArray(ids)) {
            return [];
        }

        return Array.from(new Set(ids.map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) {
            return !isNaN(id) && id > 0;
        })));
    }

    function getGeneratorScopeIds(scope) {
        var deferred = $.Deferred();
        var normalizedScope = String(scope || 'missing').toLowerCase();

        if (normalizedScope === 'selected') {
            deferred.resolve(normalizeGeneratorIds(getSelectedLibraryIds()));
            return deferred.promise();
        }

        var requestScope = normalizedScope === 'weak'
            ? 'needs-review'
            : (normalizedScope === 'all' ? 'all' : 'missing');

        fetchBulkImageIds(requestScope, 500)
            .done(function(response) {
                deferred.resolve(normalizeGeneratorIds(response && response.ids));
            })
            .fail(function(error) {
                deferred.reject(error);
            });

        return deferred.promise();
    }

    function getGeneratorQueueSource(scope, ids) {
        var normalizedScope = String(scope || 'missing').toLowerCase();
        if (normalizedScope === 'missing') {
            return 'bulk';
        }

        if (normalizedScope === 'selected') {
            var hasExistingAlt = normalizeGeneratorIds(ids).some(function(id) {
                var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
                return row && String(row.getAttribute('data-alt-missing') || 'false') !== 'true';
            });
            return hasExistingAlt ? 'bulk-regenerate' : 'bulk';
        }

        return 'bulk-regenerate';
    }

    function getGeneratorCompletionSource(scope) {
        var normalizedScope = String(scope || 'missing').toLowerCase();
        if (normalizedScope === 'weak') {
            return 'regenerate-weak';
        }
        if (normalizedScope === 'all') {
            return 'regenerate-all';
        }
        if (normalizedScope === 'selected') {
            return 'selected';
        }
        return 'generate-missing';
    }

    function runSilentInlineGeneration(idList, source, options) {
        var normalized = normalizeGeneratorIds(idList);
        var settings = options && typeof options === 'object' ? options : {};
        var inlineBatchSize = window.BBAI && window.BBAI.inlineBatchSize
            ? Math.max(1, parseInt(window.BBAI.inlineBatchSize, 10))
            : 1;
        var queue = normalized.slice(0);
        var total = queue.length;
        var processed = 0;
        var successes = 0;
        var failures = 0;
        var active = 0;
        var blockedByQuota = false;

        if (!total) {
            if (typeof settings.onBeforeComplete === 'function') {
                settings.onBeforeComplete({
                    source: source,
                    processed: 0,
                    successes: 0,
                    failures: 0,
                    total: 0
                });
            }
            dispatchDashboardFeedback('info', __('No matching images are available for this action right now.', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        function finalize() {
            var summary = {
                source: source,
                processed: total,
                successes: successes,
                failures: failures,
                total: total
            };

            if (typeof settings.onBeforeComplete === 'function') {
                settings.onBeforeComplete(summary);
            }

            if (typeof refreshUsageStats === 'function') {
                refreshUsageStats();
            }

            var finishFeedback = function() {
                dispatchDashboardFeedback(
                    failures > 0 ? 'warning' : 'success',
                    buildDashboardGenerationMessage(source, successes, failures),
                    { duration: 12000, skipToast: true }
                );

                showAltGenerationToast(summary);

                if (typeof settings.onAfterComplete === 'function') {
                    settings.onAfterComplete(summary);
                }
            };

            if (typeof window.bbaiRefreshDashboardCoverage === 'function') {
                window.bbaiRefreshDashboardCoverage().always(finishFeedback);
            } else {
                applyLibraryReviewFilters();
                finishFeedback();
            }
        }

        function processNext() {
            if (blockedByQuota && active === 0) {
                return;
            }

            if (!queue.length && active === 0) {
                finalize();
                return;
            }

            if (active >= inlineBatchSize || !queue.length) {
                return;
            }

            var id = queue.shift();
            active++;

            generateAltTextForId(id)
                .then(function() {
                    processed++;
                    successes++;
                })
                .catch(function(error) {
                    if (isLimitReachedError(error)) {
                        blockedByQuota = true;
                        queue = [];
                        if (typeof settings.onQuotaError === 'function') {
                            settings.onQuotaError(error || { code: 'quota_exhausted' });
                        } else {
                            handleLimitReached(error || { code: 'quota_exhausted' });
                        }
                        return;
                    }

                    processed++;
                    failures++;
                })
                .finally(function() {
                    active--;
                    if (!blockedByQuota) {
                        window.setTimeout(processNext, 250);
                    }
                });

            if (active < inlineBatchSize && queue.length) {
                processNext();
            }
        }

        processNext();
    }

    function openDashboardGeneratorModal() {
        var modalState = getDashboardGeneratorModalOptions();
        if (!window.bbaiModal || typeof window.bbaiModal.show !== 'function') {
            if (typeof window.bulk_generate_alt === 'function') {
                window.bulk_generate_alt('missing');
            }
            return;
        }

        var options = modalState.options || [];
        if (!options.length) {
            window.bbaiModal.info(__('No images are available for ALT generation yet.', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        var activeScope = options[0] ? options[0].id : 'missing';
        var isGenerating = false;

        window.bbaiModal.show({
            type: 'info',
            title: __('Generate ALT text for images', 'beepbeep-ai-alt-text-generator'),
            message: ' ',
            buttons: [],
            skipAutoFocus: true,
            closeOnBackdrop: true,
            closeOnEscape: true,
            onClose: function() {
                var overlayNode = document.getElementById('bbai-modal-overlay');
                if (overlayNode) {
                    overlayNode.classList.remove('bbai-dashboard-generator-modal');
                    overlayNode.classList.remove('bbai-dashboard-generator-modal--loading');
                    overlayNode.removeAttribute('aria-describedby');
                }
            }
        });

        var overlay = document.getElementById('bbai-modal-overlay');
        if (!overlay) {
            return;
        }

        overlay.classList.add('bbai-dashboard-generator-modal');

        var messageNode = overlay.querySelector('.bbai-modal-message');
        var buttonsNode = overlay.querySelector('.bbai-modal-buttons');
        if (!messageNode || !buttonsNode) {
            return;
        }

        messageNode.innerHTML = '';
        buttonsNode.innerHTML = '';

        var descriptionId = 'bbai-dashboard-generator-description';
        var advancedId = 'bbai-dashboard-generator-advanced';
        overlay.setAttribute('aria-describedby', descriptionId);

        var intro = document.createElement('p');
        intro.className = 'bbai-dashboard-generator-modal__intro';
        intro.id = descriptionId;
        intro.textContent = __('BeepBeep AI will automatically generate ALT text for images missing descriptions.', 'beepbeep-ai-alt-text-generator');
        messageNode.appendChild(intro);

        var summaryCard = document.createElement('div');
        summaryCard.className = 'bbai-dashboard-generator-modal__summary';
        summaryCard.setAttribute('aria-live', 'polite');
        summaryCard.setAttribute('aria-atomic', 'true');

        var summaryImages = document.createElement('p');
        summaryImages.className = 'bbai-dashboard-generator-modal__summary-line';
        var summaryCredits = document.createElement('p');
        summaryCredits.className = 'bbai-dashboard-generator-modal__summary-line';
        var summaryNote = document.createElement('p');
        summaryNote.className = 'bbai-dashboard-generator-modal__summary-note';
        summaryNote.hidden = true;

        summaryCard.appendChild(summaryImages);
        summaryCard.appendChild(summaryCredits);
        summaryCard.appendChild(summaryNote);
        messageNode.appendChild(summaryCard);

        var advancedToggle = document.createElement('button');
        advancedToggle.type = 'button';
        advancedToggle.className = 'bbai-dashboard-generator-modal__toggle';
        advancedToggle.setAttribute('aria-expanded', 'false');
        advancedToggle.setAttribute('aria-controls', advancedId);
        advancedToggle.setAttribute('aria-label', __('Toggle advanced ALT generation options', 'beepbeep-ai-alt-text-generator'));
        advancedToggle.innerHTML =
            '<span>' + escapeHtml(__('Advanced options', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '<span class="bbai-dashboard-generator-modal__toggle-icon" aria-hidden="true">▾</span>';
        messageNode.appendChild(advancedToggle);

        var optionsWrap = document.createElement('div');
        optionsWrap.className = 'bbai-dashboard-generator-modal__options';
        optionsWrap.id = advancedId;
        optionsWrap.hidden = true;
        optionsWrap.setAttribute('role', 'radiogroup');
        optionsWrap.setAttribute('aria-label', __('ALT generation options', 'beepbeep-ai-alt-text-generator'));
        messageNode.appendChild(optionsWrap);

        var cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'bbai-modal-button bbai-modal-button--secondary';
        cancelButton.textContent = __('Cancel', 'beepbeep-ai-alt-text-generator');
        cancelButton.addEventListener('click', function() {
            if (!isGenerating) {
                window.bbaiModal.close();
            }
        });

        var generateButton = document.createElement('button');
        generateButton.type = 'button';
        generateButton.className = 'bbai-modal-button bbai-modal-button--primary';
        generateButton.textContent = __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator');

        buttonsNode.appendChild(cancelButton);
        buttonsNode.appendChild(generateButton);

        function setDismissState(canDismiss) {
            if (!window.bbaiModal || !window.bbaiModal.activeModal) {
                return;
            }
            window.bbaiModal.activeModal.closeOnBackdrop = canDismiss;
            window.bbaiModal.activeModal.closeOnEscape = canDismiss;
        }

        function setSummaryMessage(message, warning) {
            var hasMessage = !!String(message || '').trim();
            summaryNote.hidden = !hasMessage;
            summaryNote.textContent = hasMessage ? message : '';
            summaryCard.classList.toggle('bbai-dashboard-generator-modal__summary--warning', !!warning);
        }

        function syncAdvancedState(expanded) {
            advancedToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            optionsWrap.hidden = !expanded;
            var toggleIcon = advancedToggle.querySelector('.bbai-dashboard-generator-modal__toggle-icon');
            if (toggleIcon) {
                toggleIcon.textContent = expanded ? '▴' : '▾';
            }
        }

        function syncSelection(nextScope) {
            activeScope = nextScope;

            var optionNodes = optionsWrap.querySelectorAll('.bbai-dashboard-generator-modal__option');
            for (var i = 0; i < optionNodes.length; i++) {
                var optionNode = optionNodes[i];
                var optionId = optionNode.getAttribute('data-bbai-generator-scope');
                var isSelected = optionId === activeScope;
                optionNode.classList.toggle('is-selected', isSelected);
                var inputNode = optionNode.querySelector('input');
                if (inputNode) {
                    inputNode.checked = isSelected;
                }
            }

            var selectedOption = options.find(function(option) {
                return option.id === activeScope;
            }) || null;
            var estimateState = getGeneratorEstimateCopy(selectedOption, getDashboardGeneratorUsageState());

            summaryImages.textContent = estimateState.imageSummary;
            summaryCredits.textContent = estimateState.creditSummary;
            setSummaryMessage(estimateState.note, estimateState.warning);

            if (!isGenerating) {
                generateButton.disabled = !selectedOption || estimateState.unavailable;
            }
        }

        function setGeneratorBusyState(busy) {
            isGenerating = !!busy;
            overlay.classList.toggle('bbai-dashboard-generator-modal--loading', isGenerating);
            setDismissState(!isGenerating);

            cancelButton.disabled = isGenerating;
            advancedToggle.disabled = isGenerating;
            optionsWrap.querySelectorAll('input').forEach(function(input) {
                input.disabled = isGenerating;
            });

            if (isGenerating) {
                generateButton.disabled = true;
                generateButton.setAttribute('aria-busy', 'true');
                generateButton.innerHTML =
                    '<span class="bbai-dashboard-generator-modal__button-spinner" aria-hidden="true"></span>' +
                    '<span>' + escapeHtml(__('Generating ALT…', 'beepbeep-ai-alt-text-generator')) + '</span>';
                return;
            }

            generateButton.removeAttribute('aria-busy');
            generateButton.textContent = __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator');
            syncSelection(activeScope);
        }

        function showGeneratorError(message) {
            setSummaryMessage(
                message || __('Failed to queue images. Please try again.', 'beepbeep-ai-alt-text-generator'),
                true
            );
        }

        advancedToggle.addEventListener('click', function() {
            if (isGenerating) {
                return;
            }
            syncAdvancedState(optionsWrap.hidden);
        });

        generateButton.addEventListener('click', function() {
            if (isGenerating) {
                return;
            }

            var selectedOption = options.find(function(option) {
                return option.id === activeScope;
            }) || null;
            var usageState = getDashboardGeneratorUsageState();
            var estimateState = getGeneratorEstimateCopy(selectedOption, usageState);

            if (!selectedOption || estimateState.unavailable) {
                return;
            }

            if (estimateState.overLimit) {
                window.bbaiModal.close();
                window.setTimeout(function() {
                    openUpgradeModal(usageState.usage || null);
                }, 80);
                return;
            }

            setGeneratorBusyState(true);

            getGeneratorScopeIds(activeScope)
                .done(function(ids) {
                    var normalizedIds = normalizeGeneratorIds(ids);
                    if (!normalizedIds.length) {
                        setGeneratorBusyState(false);
                        showGeneratorError(__('No matching images are available for this action right now.', 'beepbeep-ai-alt-text-generator'));
                        return;
                    }

                    queueImages(normalizedIds, getGeneratorQueueSource(activeScope, normalizedIds), { skipSchedule: true }, function(success, queued, error, processedIds) {
                        if (success || (error && error.code === 'already_queued')) {
                            runSilentInlineGeneration(processedIds || normalizedIds, getGeneratorCompletionSource(activeScope), {
                                onBeforeComplete: function() {
                                    setGeneratorBusyState(false);
                                    setDismissState(true);
                                    if (window.bbaiModal && window.bbaiModal.activeModal) {
                                        window.bbaiModal.close();
                                    }
                                },
                                onQuotaError: function(quotaError) {
                                    setGeneratorBusyState(false);
                                    setDismissState(true);
                                    if (window.bbaiModal && window.bbaiModal.activeModal) {
                                        window.bbaiModal.close();
                                    }
                                    handleLimitReached(quotaError);
                                }
                            });
                            return;
                        }

                        setGeneratorBusyState(false);

                        if (isLimitReachedError(error)) {
                            window.bbaiModal.close();
                            handleLimitReached(error);
                            return;
                        }

                        showGeneratorError(error && error.message ? error.message : '');
                    });
                })
                .fail(function(xhr) {
                    setGeneratorBusyState(false);
                    var errorMessage = __('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator');
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (xhr && xhr.message) {
                        errorMessage = xhr.message;
                    }
                    showGeneratorError(errorMessage);
                });
        });

        options.forEach(function(option) {
            var optionLabel = document.createElement('label');
            optionLabel.className = 'bbai-dashboard-generator-modal__option';
            optionLabel.setAttribute('data-bbai-generator-scope', option.id);

            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'bbai-dashboard-generator-scope';
            input.value = option.id;
            input.checked = option.id === activeScope;
            input.setAttribute('aria-label', option.label);
            input.addEventListener('change', function() {
                syncSelection(option.id);
            });

            var title = document.createElement('span');
            title.className = 'bbai-dashboard-generator-modal__option-label';
            title.textContent = option.label;

            optionLabel.appendChild(input);
            optionLabel.appendChild(title);
            optionLabel.addEventListener('click', function(event) {
                if (isGenerating) {
                    event.preventDefault();
                    return;
                }
                syncSelection(option.id);
            });

            optionsWrap.appendChild(optionLabel);
        });

        syncAdvancedState(false);
        syncSelection(activeScope);

        window.setTimeout(function() {
            generateButton.focus();
        }, 50);
    }

    function updateDashboardStatusCard(statsInput) {
        var card = document.querySelector('[data-bbai-dashboard-status-card="1"]');
        if (!card) {
            return;
        }

        if (typeof window.bbaiSyncDashboardState === 'function') {
            window.bbaiSyncDashboardState(statsInput || null);
            return;
        }

        var stats = normalizeDashboardStatsPayload(statsInput);
        var root = getDashboardRootNode();
        var libraryUrl = root ? String(root.getAttribute('data-bbai-library-url') || '') : '';
        var missingLibraryUrl = root ? String(root.getAttribute('data-bbai-missing-library-url') || '') : libraryUrl;
        var lastScanTs = root ? parseInt(root.getAttribute('data-bbai-last-scan-ts') || '0', 10) : 0;

        if (stats.scanned_at !== undefined) {
            lastScanTs = Math.max(0, parseInt(stats.scanned_at, 10) || 0);
            if (root) {
                root.setAttribute('data-bbai-last-scan-ts', String(lastScanTs));
            }
        } else if (stats.scannedAt !== undefined) {
            lastScanTs = Math.max(0, parseInt(stats.scannedAt, 10) || 0);
            if (root) {
                root.setAttribute('data-bbai-last-scan-ts', String(lastScanTs));
            }
        }

        var formatLastScanCopy = function(scanTimestamp) {
            var scanTs = parseInt(scanTimestamp, 10);
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

            if (minutes < 1) {
                return __('Last scan: just now', 'beepbeep-ai-alt-text-generator');
            }
            if (minutes < 60) {
                return sprintf(
                    _n('Last scan: %d minute ago', 'Last scan: %d minutes ago', minutes, 'beepbeep-ai-alt-text-generator'),
                    minutes
                );
            }
            if (hours < 24) {
                return sprintf(
                    _n('Last scan: %d hour ago', 'Last scan: %d hours ago', hours, 'beepbeep-ai-alt-text-generator'),
                    hours
                );
            }
            if (days < 7) {
                return sprintf(
                    _n('Last scan: %d day ago', 'Last scan: %d days ago', days, 'beepbeep-ai-alt-text-generator'),
                    days
                );
            }

            try {
                return sprintf(
                    __('Last scan: %s', 'beepbeep-ai-alt-text-generator'),
                    new Date(scanTs).toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    })
                );
            } catch (error) {
                return '';
            }
        };

        var summaryRatioNode = card.querySelector('[data-bbai-status-summary-ratio]');
        var summaryDetailNode = card.querySelector('[data-bbai-status-summary-detail]');
        if (summaryRatioNode) {
            summaryRatioNode.textContent = stats.total_images > 0
                ? buildDashboardOptimizedRatio(stats)
                : __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
        }
        if (summaryDetailNode) {
            var detailCopy = buildDashboardStatusSummary(stats);
            summaryDetailNode.textContent = detailCopy;
            summaryDetailNode.hidden = !detailCopy;
        }

        ['optimized', 'weak', 'missing'].forEach(function(metricKey) {
            var metricNode = card.querySelector('[data-bbai-status-metric="' + metricKey + '"]');
            if (!metricNode) {
                return;
            }

            var value = metricKey === 'optimized'
                ? stats.optimized_count
                : (metricKey === 'weak' ? stats.needs_review_count : stats.images_missing_alt);
            metricNode.textContent = formatDashboardNumber(value);
        });

        var coverageNode = card.querySelector('[data-bbai-status-coverage-value]');
        if (coverageNode) {
            coverageNode.textContent = formatDashboardNumber(stats.optimized_percent);
        }

        var circumference = 2 * Math.PI * 48;
        var gap = 4.0;
        var ringOffset = 0.0;
        var ringSegments = [
            { key: 'optimized', count: stats.optimized_count, stroke: '#16A34A' },
            { key: 'weak', count: stats.needs_review_count, stroke: '#F97316' },
            { key: 'missing', count: stats.images_missing_alt, stroke: '#EF4444' }
        ];
        var nonZeroSegments = ringSegments.filter(function(segment) {
            return segment.count > 0;
        }).length;
        var linecap = nonZeroSegments > 1 ? 'butt' : 'round';

        ringSegments.forEach(function(segment) {
            var segmentNode = card.querySelector('[data-bbai-status-ring-segment="' + segment.key + '"]');
            if (!segmentNode) {
                return;
            }

            var segmentLength = stats.total_images > 0 ? (circumference * (segment.count / stats.total_images)) : 0;
            var segmentGap = nonZeroSegments > 1 ? Math.min(gap, segmentLength * 0.35) : 0;
            var visibleLength = Math.max(0, segmentLength - segmentGap);

            segmentNode.setAttribute('stroke', segment.stroke);
            segmentNode.setAttribute('stroke-linecap', linecap);
            segmentNode.setAttribute('stroke-dasharray', visibleLength.toFixed(3) + ' ' + Math.max(0, circumference - visibleLength).toFixed(3));
            segmentNode.setAttribute('stroke-dashoffset', (-ringOffset).toFixed(3));
            segmentNode.style.opacity = visibleLength <= 0.01 ? '0' : '';

            ringOffset += segmentLength;
        });

        var insightNode = card.querySelector('[data-bbai-status-insight]');
        if (!insightNode) {
            return;
        }

        var insightClass = 'bbai-status-insight bbai-status-insight--success';
        var insightTitle = '';
        var insightMessage = '';
        var insightGuidance = '';
        var insightMeta = '';
        var insightIcon = '';
        var primaryAction = 'scan-library';
        var primaryBbaiAction = 'scan-opportunity';
        var primaryLabel = __('Scan media library', 'beepbeep-ai-alt-text-generator');
        var secondaryHref = libraryUrl || '#';
        var secondaryLabel = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');

        if (stats.images_missing_alt > 0) {
            insightClass = 'bbai-status-insight bbai-status-insight--danger';
            insightTitle = __('ALT text needed', 'beepbeep-ai-alt-text-generator');
            insightMessage = sprintf(
                _n('%s image is missing ALT text.', '%s images are missing ALT text.', stats.images_missing_alt, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(stats.images_missing_alt)
            );
            insightIcon = '<span class="bbai-status-insight__icon" aria-hidden="true">⚠</span>';
            primaryAction = 'generate-missing';
            primaryBbaiAction = 'generate_missing';
            primaryLabel = __('Generate missing ALT text', 'beepbeep-ai-alt-text-generator');
            secondaryHref = missingLibraryUrl || libraryUrl || '#';
            secondaryLabel = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
        } else {
            insightTitle = __('🎉 Library fully optimized', 'beepbeep-ai-alt-text-generator');
            insightMessage = __('All images currently include accessible ALT text.', 'beepbeep-ai-alt-text-generator');
            insightGuidance = __('Future uploads can be scanned and optimized automatically.', 'beepbeep-ai-alt-text-generator');
            insightMeta = formatLastScanCopy(lastScanTs);
        }

        insightNode.className = insightClass;
        insightNode.hidden = false;
        insightNode.innerHTML =
            '<div class="bbai-status-insight__header">' +
                insightIcon +
                '<p class="bbai-status-insight__title" data-bbai-status-insight-title>' + escapeHtml(insightTitle) + '</p>' +
            '</div>' +
            '<p class="bbai-status-insight__message">' +
                '<span class="bbai-status-insight__text" data-bbai-status-insight-text>' + escapeHtml(insightMessage) + '</span>' +
            '</p>' +
            '<p class="bbai-status-insight__guidance" data-bbai-status-insight-guidance' + (insightGuidance ? '' : ' hidden') + '>' + escapeHtml(insightGuidance) + '</p>' +
            '<p class="bbai-status-insight__meta" data-bbai-status-insight-last-scan' + (insightMeta ? '' : ' hidden') + '>' + escapeHtml(insightMeta) + '</p>' +
            '<div class="bbai-status-insight__actions" data-bbai-status-insight-actions>' +
                '<button type="button" class="bbai-status-insight__action bbai-status-insight__action--button bbai-btn-primary" data-bbai-status-insight-primary data-action="' + escapeHtml(primaryAction) + '" data-bbai-action="' + escapeHtml(primaryBbaiAction) + '">' +
                    escapeHtml(primaryLabel) +
                '</button>' +
                '<a href="' + escapeHtml(secondaryHref) + '#bbai-alt-table" class="bbai-status-insight__action bbai-status-insight__action--link" data-bbai-status-insight-review data-bbai-review-scroll="1">' + escapeHtml(secondaryLabel) + '</a>' +
            '</div>';
    }

    function updateDashboardPerformanceMetrics(statsInput, usageInput) {
        var stats = normalizeDashboardStatsPayload(statsInput || readDashboardStatsFromRoot() || {});
        var usage = getUsageSnapshot(usageInput) || getUsageFromDom() || getUsageFromBanner(getSharedBannerNode()) || null;
        var used = usage && typeof usage === 'object' ? parseInt(usage.used, 10) : NaN;
        if (isNaN(used) || used < 0) {
            used = parseInt(getUsageForQuotaChecks() && getUsageForQuotaChecks().used, 10);
        }
        if (isNaN(used) || used < 0) {
            used = 0;
        }

        var hoursSaved = Math.max(0, (used * 2.5) / 60);
        var minutesSaved = Math.round(hoursSaved * 60);
        var generatedCount = Math.max(0, parseInt(stats.generated, 10) || 0);

        var minutesNode = document.querySelector('[data-bbai-performance-minutes]');
        if (minutesNode) {
            minutesNode.textContent = formatDashboardNumber(minutesSaved);
        }

        var optimizedNode = document.querySelector('[data-bbai-performance-optimized]');
        if (optimizedNode) {
            optimizedNode.textContent = formatDashboardNumber(stats.optimized_count);
        }

        var coverageNode = document.querySelector('[data-bbai-performance-coverage]');
        if (coverageNode) {
            coverageNode.textContent = formatDashboardNumber(stats.optimized_percent);
        }

        var generatedNode = document.querySelector('[data-bbai-performance-generated]');
        if (generatedNode) {
            generatedNode.textContent = formatDashboardNumber(generatedCount);
        }
    }

    function buildBulkOptimizationIssueMessage(issueCount) {
        var count = Math.max(0, parseInt(issueCount, 10) || 0);
        return sprintf(
            _n(
                '%s image could be optimized automatically',
                '%s images could be optimized automatically',
                count,
                'beepbeep-ai-alt-text-generator'
            ),
            formatDashboardNumber(count)
        );
    }

    function buildBulkOptimizationTimeLabel(issueCount) {
        var count = Math.max(0, parseInt(issueCount, 10) || 0);
        var seconds = count * 20;
        if (seconds >= 60) {
            var minutes = Math.ceil(seconds / 60);
            return sprintf(
                __('Estimated time saved: ~%s', 'beepbeep-ai-alt-text-generator'),
                sprintf(
                    _n('%d minute', '%d minutes', minutes, 'beepbeep-ai-alt-text-generator'),
                    minutes
                )
            );
        }

        return sprintf(
            __('Estimated time saved: ~%s', 'beepbeep-ai-alt-text-generator'),
            sprintf(
                _n('%d second', '%d seconds', seconds, 'beepbeep-ai-alt-text-generator'),
                seconds
            )
        );
    }

    function getBulkOptimizationProcessingLabel(issueCount) {
        var count = Math.max(0, parseInt(issueCount, 10) || 0);
        return sprintf(
            _n(
                'Processing %d image',
                'Processing %d images',
                count,
                'beepbeep-ai-alt-text-generator'
            ),
            count
        );
    }

    function setBulkProgressHelperText(text) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) {
            return;
        }

        var helperText = String(text || '').trim();
        if (!helperText) {
            return;
        }

        $modal.find('.bbai-bulk-progress__helper').text(helperText).prop('hidden', false);
    }

    function updateLibraryBulkOptimizationBanner(scanData) {
        var banner = document.querySelector('[data-bbai-bulk-optimization-banner]');
        if (!banner || !scanData || typeof scanData !== 'object') {
            return;
        }

        var missingCount = Math.max(0, parseInt(scanData.images_missing_alt, 10) || parseInt(scanData.missing, 10) || 0);
        var weakCount = Math.max(0, parseInt(scanData.needs_review_count, 10) || 0);
        var issueCount = missingCount + weakCount;

        banner.setAttribute('data-bbai-issue-count', String(issueCount));
        banner.setAttribute('data-bbai-missing-count', String(missingCount));
        banner.setAttribute('data-bbai-weak-count', String(weakCount));

        var countNode = banner.querySelector('[data-bbai-bulk-optimization-count]');
        if (countNode) {
            countNode.textContent = buildBulkOptimizationIssueMessage(issueCount);
        }

        var timeNode = banner.querySelector('[data-bbai-bulk-optimization-time]');
        if (timeNode) {
            timeNode.textContent = buildBulkOptimizationTimeLabel(issueCount);
        }

        var actionButton = banner.querySelector('[data-action="fix-all-images-automatically"]');
        if (actionButton) {
            actionButton.setAttribute('data-bbai-issue-count', String(issueCount));
        }

        banner.hidden = issueCount <= 0;
    }

    function updateAltCoverageCard(scanData) {
        var card = document.querySelector('[data-bbai-coverage-card]');
        if (!card || !scanData || typeof scanData !== 'object') {
            return;
        }

        var totalImages = Math.max(0, parseInt(scanData.total_images, 10) || parseInt(scanData.total, 10) || 0);
        var imagesWithAlt = Math.max(0, parseInt(scanData.images_with_alt, 10) || parseInt(scanData.with_alt, 10) || 0);
        var imagesMissingAlt = Math.max(0, parseInt(scanData.images_missing_alt, 10) || parseInt(scanData.missing, 10) || 0);
        var weakCount = Math.max(0, parseInt(scanData.needs_review_count, 10) || 0);
        var optimizedCount = Math.max(0, parseInt(scanData.optimized_count, 10) || Math.max(0, imagesWithAlt - weakCount));
        var optimizedPercent = totalImages > 0 ? Math.round((optimizedCount / totalImages) * 100) : 0;
        var weakPercent = totalImages > 0 ? Math.round((weakCount / totalImages) * 100) : 0;
        var missingPercent = totalImages > 0 ? Math.round((imagesMissingAlt / totalImages) * 100) : 0;
        var coverageScore = parseInt(scanData.coverage_percent, 10);
        if (isNaN(coverageScore)) {
            coverageScore = totalImages > 0 ? Math.round((imagesWithAlt / totalImages) * 100) : 0;
        }
        coverageScore = Math.max(0, Math.min(100, coverageScore));

        var freePlanLimit = parseInt(scanData.free_plan_limit, 10);
        if (isNaN(freePlanLimit) || freePlanLimit <= 0) {
            freePlanLimit = parseInt(card.getAttribute('data-bbai-free-plan-limit'), 10);
        }
        if (isNaN(freePlanLimit) || freePlanLimit <= 0) {
            freePlanLimit = 50;
        }
        card.setAttribute('data-bbai-free-plan-limit', String(freePlanLimit));

        var optimizedNode = card.querySelector('[data-bbai-library-optimized]');
        if (optimizedNode) {
            optimizedNode.textContent = formatDashboardNumber(optimizedCount);
        }

        var weakNode = card.querySelector('[data-bbai-library-weak]');
        if (weakNode) {
            weakNode.textContent = formatDashboardNumber(weakCount);
        }

        var missingSummaryNode = card.querySelector('[data-bbai-library-missing]');
        if (missingSummaryNode) {
            missingSummaryNode.textContent = formatDashboardNumber(imagesMissingAlt);
        }

        var badge = card.querySelector('[data-bbai-coverage-score-badge]');
        if (badge) {
            badge.textContent = coverageScore + '%';
        }

        var scoreText = card.querySelector('[data-bbai-coverage-score-text]');
        if (scoreText) {
            scoreText.textContent = coverageScore + '%';
        }

        var scoreInline = card.querySelector('[data-bbai-coverage-score-inline]');
        if (scoreInline) {
            scoreInline.textContent = sprintf(__('%s%%', 'beepbeep-ai-alt-text-generator'), formatDashboardNumber(optimizedPercent));
        }

        var summaryLabel = card.querySelector('[data-bbai-library-progress-label]');
        if (summaryLabel) {
            summaryLabel.textContent =
                optimizedPercent >= 100
                    ? __('Fully optimized', 'beepbeep-ai-alt-text-generator')
                    : sprintf(__('%s%% optimized', 'beepbeep-ai-alt-text-generator'), formatDashboardNumber(optimizedPercent));
        }

        var totalNode = card.querySelector('[data-bbai-coverage-total]');
        if (totalNode) {
            totalNode.textContent = formatDashboardNumber(totalImages);
        }

        var missingNode = card.querySelector('[data-bbai-coverage-missing]');
        if (missingNode) {
            missingNode.textContent = formatDashboardNumber(imagesMissingAlt);
        }

        var withAltNode = card.querySelector('[data-bbai-coverage-with-alt]');
        if (withAltNode) {
            withAltNode.textContent = formatDashboardNumber(imagesWithAlt);
        }

        var progressFill = card.querySelector('[data-bbai-coverage-progress]');
        if (progressFill) {
            progressFill.style.width = coverageScore + '%';
        }

        var optimizedSegment = card.querySelector('[data-bbai-library-progress-optimized]');
        if (optimizedSegment) {
            optimizedSegment.style.flexBasis = optimizedPercent + '%';
        }

        var weakSegment = card.querySelector('[data-bbai-library-progress-weak]');
        if (weakSegment) {
            weakSegment.style.flexBasis = weakPercent + '%';
        }

        var missingSegment = card.querySelector('[data-bbai-library-progress-missing]');
        if (missingSegment) {
            missingSegment.style.flexBasis = missingPercent + '%';
        }

        var progressBar = card.querySelector('[data-bbai-coverage-progressbar]');
        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', String(coverageScore));
        }

        var queueProgressBar = card.querySelector('[data-bbai-library-progressbar]');
        if (queueProgressBar) {
            queueProgressBar.setAttribute('aria-valuenow', String(optimizedPercent));
        }

        var limitNote = card.querySelector('[data-bbai-coverage-limit-note]');
        if (limitNote) {
            if (imagesMissingAlt > freePlanLimit) {
                limitNote.hidden = false;
                limitNote.textContent = sprintf(
                    __('You have %1$s images missing alt text. Free plan covers %2$s.', 'beepbeep-ai-alt-text-generator'),
                    formatDashboardNumber(imagesMissingAlt),
                    formatDashboardNumber(freePlanLimit)
                );
            } else {
                limitNote.hidden = true;
                limitNote.textContent = '';
            }
        }

        updateLibrarySummarySurface(scanData);
        updateLibraryWorkflow(scanData);
        updateLibraryGuidance(scanData);
        updateLibraryBulkOptimizationBanner(scanData);
    }

    window.updateAltCoverageCard = updateAltCoverageCard;

    function updateLibraryGuidance(scanData) {
        var guidance = document.querySelector('[data-bbai-library-guidance]');
        if (!guidance || !scanData || typeof scanData !== 'object') {
            return;
        }

        var root = guidance.closest('.bbai-library-container') || document.body;
        var isPro = root.getAttribute('data-bbai-library-is-pro') === '1';
        var settingsUrl = root.getAttribute('data-bbai-settings-url') || '';
        var limitReached = guidance.getAttribute('data-bbai-library-guidance-limit-reached') === '1';
        var isAnonymousTrial = !!getQuotaState().isAnonymousTrial;

        var totalImages = Math.max(0, parseInt(scanData.total_images, 10) || 0);
        var missingCount = Math.max(0, parseInt(scanData.images_missing_alt, 10) || 0);
        var weakCount = Math.max(0, parseInt(scanData.needs_review_count, 10) || 0);
        var state = 'healthy';
        var bannerVariant = 'success';
        var tone = 'healthy';
        var title = __('Your media library is fully optimized', 'beepbeep-ai-alt-text-generator');
        var copy = __('All images have ALT text. New uploads will be scanned automatically.', 'beepbeep-ai-alt-text-generator');
        var eyebrow = __('Healthy Library', 'beepbeep-ai-alt-text-generator');
        var primaryHtml = '';
        var secondaryHtml = '';
        var showSecondary = false;

        var clsP = 'bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-banner__cta bbai-banner__cta--primary bbai-ui-btn bbai-ui-btn--primary bbai-btn bbai-btn-primary';
        var clsS = 'bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary bbai-banner__link bbai-banner__link--secondary bbai-ui-btn bbai-ui-btn--secondary bbai-btn bbai-btn-secondary';
        var iconHealthy = '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var iconInfo = '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 10V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7" r="1" fill="currentColor"/></svg>';

        primaryHtml = '<button type="button" class="' + clsP + '" data-action="rescan-media-library">' +
            escapeHtml(__('Scan for new uploads', 'beepbeep-ai-alt-text-generator')) + '</button>';
        if (isPro && settingsUrl) {
            secondaryHtml = '<a href="' + escapeHtml(settingsUrl) + '" class="' + clsS + '">' +
                escapeHtml(__('Enable auto-optimization', 'beepbeep-ai-alt-text-generator')) + '</a>';
        } else if (isAnonymousTrial) {
            secondaryHtml = '<button type="button" class="' + clsS + '" data-action="show-auth-modal" data-auth-tab="register">' +
                escapeHtml(__('Create free account', 'beepbeep-ai-alt-text-generator')) + '</button>';
        } else {
            secondaryHtml = '<button type="button" class="' + clsS + '" data-action="show-upgrade-modal">' +
                escapeHtml(__('Enable auto-optimization', 'beepbeep-ai-alt-text-generator')) + '</button>';
        }
        showSecondary = true;

        if (totalImages > 0 && missingCount > 0) {
            state = 'issues';
            bannerVariant = 'info';
            tone = 'setup';
            eyebrow = __('Next Task', 'beepbeep-ai-alt-text-generator');
            title = sprintf(_n('%d image is missing ALT text', '%d images are missing ALT text', missingCount, 'beepbeep-ai-alt-text-generator'), missingCount);
            copy = __('Generate ALT text to improve accessibility and SEO before reviewing weaker descriptions.', 'beepbeep-ai-alt-text-generator');
            var lockCls = limitReached ? ' bbai-is-locked' : '';
            var lockAttrs = limitReached
                ? buildLockedCtaAttributes('generate_missing', 'library-guidance')
                : '';
            primaryHtml = '<button type="button" class="' + clsP + lockCls + '" data-action="generate-missing" data-bbai-lock-preserve-label="1"' + lockAttrs + '>' +
                escapeHtml(__('Generate missing ALT text', 'beepbeep-ai-alt-text-generator')) + '</button>';
            secondaryHtml = '';
            showSecondary = false;
        } else if (totalImages > 0 && weakCount > 0) {
            state = 'issues';
            bannerVariant = 'info';
            tone = 'setup';
            eyebrow = __('Next Task', 'beepbeep-ai-alt-text-generator');
            title = sprintf(_n('%d image needs a stronger ALT description', '%d images need a stronger ALT description', weakCount, 'beepbeep-ai-alt-text-generator'), weakCount);
            copy = __('Approve copy you are happy with, or regenerate weaker ALT text to move through the review queue quickly.', 'beepbeep-ai-alt-text-generator');
            primaryHtml = '<button type="button" class="' + clsP + '" data-bbai-filter-target="weak">' +
                escapeHtml(__('Review ALT text', 'beepbeep-ai-alt-text-generator')) + '</button>';
            secondaryHtml = '';
            showSecondary = false;
        }

        guidance.setAttribute('data-state', state);
        guidance.setAttribute('data-page-hero-variant', bannerVariant === 'success' ? 'success' : 'info');
        guidance.setAttribute('data-tone', tone);
        guidance.classList.remove('bbai-banner--success', 'bbai-banner--info', 'bbai-page-hero--success', 'bbai-page-hero--info');
        guidance.classList.add('bbai-banner--' + bannerVariant);
        guidance.classList.add('bbai-page-hero--' + bannerVariant);

        var iconWrap = guidance.querySelector('.bbai-banner__icon');
        if (iconWrap) {
            iconWrap.innerHTML = bannerVariant === 'info' ? iconInfo : iconHealthy;
        }

        var eyebrowEl = guidance.querySelector('[data-bbai-page-hero-eyebrow]');
        if (eyebrowEl) {
            eyebrowEl.textContent = eyebrow;
            eyebrowEl.hidden = !eyebrow;
        }

        var headline = guidance.querySelector('.bbai-banner__headline');
        if (headline) {
            headline.textContent = title;
        }

        var sub = guidance.querySelector('.bbai-banner__content .bbai-banner__subtext');
        if (sub) {
            sub.textContent = copy;
        }

        var primaryItem = guidance.querySelector('[data-bbai-hero-primary-item]');
        if (primaryItem) {
            primaryItem.innerHTML = primaryHtml;
            primaryItem.hidden = false;
        }

        var secondaryItem = guidance.querySelector('[data-bbai-hero-secondary-item]');
        if (secondaryItem) {
            secondaryItem.innerHTML = showSecondary ? secondaryHtml : '';
            secondaryItem.hidden = !showSecondary || !secondaryHtml;
        }
    }

    function hideLibraryScanFeedback() {
        var banner = document.querySelector('[data-bbai-scan-feedback-banner]');
        if (!banner) {
            return;
        }

        banner.hidden = true;
        banner.removeAttribute('data-bbai-scan-missing');
        banner.removeAttribute('data-bbai-scan-weak');
        banner.removeAttribute('data-bbai-scan-issues');

        var summaryNode = banner.querySelector('[data-bbai-scan-feedback-summary]');
        var detailNode = banner.querySelector('[data-bbai-scan-feedback-detail]');
        var jumpButton = banner.querySelector('[data-action="jump-scan-results"]');

        if (summaryNode) {
            summaryNode.textContent = '';
        }
        if (detailNode) {
            detailNode.textContent = '';
            detailNode.hidden = true;
        }
        if (jumpButton) {
            jumpButton.hidden = true;
        }
    }

    function showLibraryScanFeedback(presentation, context) {
        var banner = document.querySelector('[data-bbai-scan-feedback-banner]');
        if (!banner || !presentation || !context || !context.isAltLibraryPage) {
            return false;
        }

        var summaryNode = banner.querySelector('[data-bbai-scan-feedback-summary]');
        var detailNode = banner.querySelector('[data-bbai-scan-feedback-detail]');
        var jumpButton = banner.querySelector('[data-action="jump-scan-results"]');

        banner.hidden = false;
        banner.setAttribute('data-state', presentation.state || 'attention');
        banner.setAttribute('data-bbai-scan-missing', String(presentation.summary.missing));
        banner.setAttribute('data-bbai-scan-weak', String(presentation.summary.weak));
        banner.setAttribute('data-bbai-scan-issues', String(presentation.summary.issueCount));

        if (summaryNode) {
            summaryNode.textContent = presentation.body || '';
        }

        if (detailNode) {
            detailNode.textContent = presentation.detail || '';
            detailNode.hidden = !presentation.detail;
        }

        if (jumpButton) {
            jumpButton.hidden = !(presentation.primaryAction === 'jump-results' && presentation.primaryLabel);
            jumpButton.textContent = presentation.primaryLabel || __('View results', 'beepbeep-ai-alt-text-generator');
        }

        return true;
    }

    function runCoverageScanFlow(trigger, options) {
        var settings = options || {};
        var context = getScanFeedbackContext(trigger);
        var scanner = document.querySelector('.bbai-opportunity-scanner');
        var promptBlock = settings.manageScannerUi === false || !scanner ? null : scanner.querySelector('[data-bbai-scan-prompt]');
        var resultsBlock = settings.manageScannerUi === false || !scanner ? null : scanner.querySelector('[data-bbai-scan-results]');
        var loadingEl = settings.manageScannerUi === false || !scanner ? null : scanner.querySelector('[data-bbai-scan-loading]');
        var busySelector = settings.controlSelector || '[data-action="rescan-media-library"]';
        var restoreBusyState = setBusyStateForControls(busySelector, __('Scanning…', 'beepbeep-ai-alt-text-generator'));
        var startedAt = Date.now();
        var useModal = !!settings.useModal && !context.isAltLibraryPage;
        var modal = useModal ? openDashboardScanModal(trigger) : null;
        var fallbackError = settings.errorMessage || __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator');
        var analyticsSource = resolveAnalyticsSource(trigger, context.isAltLibraryPage ? 'library' : 'dashboard');

        if (context.isAltLibraryPage) {
            hideLibraryScanFeedback();
        }

        if (typeof window.bbaiRefreshDashboardCoverage !== 'function') {
            if (modal) {
                setDashboardScanModalMode('error', {
                    message: fallbackError
                });
            }
            dispatchDashboardFeedback('error', fallbackError, { skipToast: !!modal });
            restoreBusyState();
            return false;
        }

        dispatchAnalyticsEvent('scan_started', {
            source: analyticsSource
        });

        if (modal) {
            setDashboardScanModalMode('loading');
        }
        if (promptBlock) {
            promptBlock.setAttribute('hidden', '');
        }
        if (resultsBlock) {
            resultsBlock.setAttribute('hidden', '');
        }
        if (loadingEl) {
            loadingEl.hidden = false;
            loadingEl.removeAttribute('hidden');
        }

        window.bbaiRefreshDashboardCoverage()
            .done(function(payload) {
                var finalize = function() {
                    var presentation = buildScanFeedbackPresentation(payload, context);
                    dispatchAnalyticsEvent('scan_completed', {
                        source: analyticsSource,
                        total_images: parseInt(payload && payload.total_images, 10) || 0,
                        missing_alt_count: parseInt(payload && payload.images_missing_alt, 10) || 0,
                        needs_review_count: parseInt(payload && payload.needs_review_count, 10) || 0,
                        optimized_count: parseInt(payload && payload.optimized_count, 10) || 0
                    });

                    if (promptBlock) {
                        promptBlock.setAttribute('hidden', '');
                    }
                    if (resultsBlock) {
                        resultsBlock.removeAttribute('hidden');
                    }
                    if (modal) {
                        setDashboardScanModalMode('result', payload, context);
                    }
                    if (context.isAltLibraryPage) {
                        showLibraryScanFeedback(presentation, context);
                    }

                    dispatchDashboardFeedback(
                        presentation.hasIssues ? 'success' : 'info',
                        buildDashboardScanMessage(payload, context),
                        { skipToast: true, duration: 4500 }
                    );
                };
                var remaining = Math.max(0, 450 - (Date.now() - startedAt));
                if (remaining > 0) {
                    window.setTimeout(finalize, remaining);
                } else {
                    finalize();
                }
            })
            .fail(function(errorMessage) {
                var message = errorMessage || fallbackError;
                var finalize = function() {
                    if (promptBlock) {
                        promptBlock.removeAttribute('hidden');
                    }
                    dispatchAnalyticsEvent('scan_failed', {
                        source: analyticsSource
                    });
                    if (modal) {
                        setDashboardScanModalMode('error', {
                            message: message
                        });
                    }
                    dispatchDashboardFeedback('error', message, { skipToast: !!modal });
                };
                var remaining = Math.max(0, 450 - (Date.now() - startedAt));
                if (remaining > 0) {
                    window.setTimeout(finalize, remaining);
                } else {
                    finalize();
                }
            })
            .always(function() {
                if (loadingEl) {
                    loadingEl.hidden = true;
                    loadingEl.setAttribute('hidden', '');
                }
                restoreBusyState();
            });

        return false;
    }

    function handleRescanMediaLibrary(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }

        return runCoverageScanFlow(this, {
            controlSelector: '[data-action="rescan-media-library"]',
            manageScannerUi: false,
            useModal: false,
            errorMessage: __('Unable to rescan media library right now.', 'beepbeep-ai-alt-text-generator')
        });
    }

    function handleOpportunityScan(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }

        return runCoverageScanFlow(this, {
            controlSelector: '[data-bbai-action="scan-opportunity"]',
            manageScannerUi: true,
            useModal: true,
            errorMessage: __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator')
        });
    }

    /**
     * Regenerate alt text for a single image - shows modal with preview
     */
    function handleRegenerateSingle(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        if (e && typeof e.stopPropagation === 'function') {
            e.stopPropagation();
        }
        clearModalAndScrollLocks();

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Regenerate button clicked');
        var $btn = $(this);
        var trigger = $btn.get(0);
        var row = getLibraryRowFromTrigger(trigger);

        // Hard-stop single regenerate when free credits are exhausted.
        if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
            return handleLockedCtaClick(trigger, e);
        }

        // Try multiple ways to get attachment ID (jQuery data() converts kebab-case)
        var attachmentIdRaw = $btn.data('attachment-id') ||
                              $btn.data('attachmentId') ||
                              $btn.attr('data-attachment-id') ||
                              '';
        var attachmentId = parseInt(attachmentIdRaw, 10);

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Attachment ID:', attachmentId);
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Button element:', this);
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Button disabled?', $btn.prop('disabled'));
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] All data attributes:', $btn.data());

        if (!attachmentId || attachmentId <= 0) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Cannot regenerate - missing attachment ID');
            alert(__('Error: Unable to find attachment ID. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            return false;
        }

        if (!row) {
            if ($btn.prop('disabled')) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Cannot regenerate - button is disabled');
                return false;
            }

            var originalText = $btn.text();
            var originalHtml = $btn.html();
            $btn.data('bbai-original-html', originalHtml);
            $btn.prop('disabled', true).addClass('regenerating');
            $btn.text(__('Processing...', 'beepbeep-ai-alt-text-generator'));

            var imageTitle = __('Image', 'beepbeep-ai-alt-text-generator');
            var defaultImageTitle = imageTitle;
            var imageSrc = '';
            var $form = $btn.closest('form');
            if ($form.length) {
                var $titleInput = $form.find('input[name="post_title"]');
                if ($titleInput.length) {
                    imageTitle = $titleInput.val() || imageTitle;
                }

                var $preview = $form.find('img.attachment-thumbnail, img.attachment-preview, .attachment-preview img, #postimagediv img');
                if ($preview.length) {
                    imageSrc = $preview.attr('src') || $preview.attr('data-src') || imageSrc;
                }
            }

            if (!imageSrc || imageTitle === defaultImageTitle) {
                var $attachmentDetails = $('#attachment-details, .attachment-details');
                if ($attachmentDetails.length) {
                    var $img = $attachmentDetails.find('img');
                    if ($img.length) {
                        imageSrc = $img.attr('src') || $img.attr('data-src') || imageSrc;
                    }
                }
            }

            showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalText);
            return false;
        }

        var altCell = row ? row.querySelector('.bbai-library-cell--alt-text') : null;
        if (altCell && altCell.classList.contains('bbai-library-cell--editing')) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Save or cancel the current ALT edit before regenerating this image.', 'beepbeep-ai-alt-text-generator'));
            }
            return false;
        }

        if ($btn.prop('disabled')) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Cannot regenerate - button is disabled');
            return false;
        }

        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) || '';
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) ||
            (window.BBAI && window.BBAI.nonce) ||
            '';
        if (!ajaxUrl) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', __('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'));
            }
            return false;
        }

        var isMissing = !!(row && String(row.getAttribute('data-alt-missing') || 'false') === 'true');
        var busyLabel = isMissing ? __('Generating...', 'beepbeep-ai-alt-text-generator') : __('Regenerating...', 'beepbeep-ai-alt-text-generator');
        var busyCopy = isMissing ? __('Generating ALT text...', 'beepbeep-ai-alt-text-generator') : __('Regenerating ALT text...', 'beepbeep-ai-alt-text-generator');
        var altSlot = row ? row.querySelector('[data-bbai-alt-slot]') : null;
        var originalAltHtml =
            altSlot && altCell && !altCell.classList.contains('bbai-library-cell--editing') ? altSlot.innerHTML : '';
        if (!altSlot && altCell && !altCell.classList.contains('bbai-library-cell--editing')) {
            originalAltHtml = altCell.innerHTML;
        }

        setLibraryRowActionLoading(trigger, busyLabel);
        setDashboardRuntimeState('generation_running');
        if (originalAltHtml) {
            var busyMarkup =
                '<div class="bbai-library-reviewing">' +
                '<span class="bbai-row-action-spinner" aria-hidden="true"></span>' +
                '<span>' +
                escapeHtml(busyCopy) +
                '</span>' +
                '</div>';
            if (altSlot) {
                altSlot.innerHTML = busyMarkup;
            } else if (altCell) {
                altCell.innerHTML = busyMarkup;
            }
        }

        if (row && typeof window.bbaiSetRowProcessing === 'function') {
            window.bbaiSetRowProcessing(row);
        }
        if (typeof window.bbaiStatsPoller !== 'undefined' && window.bbaiStatsPoller && typeof window.bbaiStatsPoller.start === 'function') {
            window.bbaiStatsPoller.start();
        }

        function finalizeLibraryRegenerateRowUi() {
            restoreLibraryRowActionLoading(trigger);
            if (row && typeof window.bbaiSetRowDone === 'function') {
                window.bbaiSetRowDone(row);
            }
            if (
                typeof window.bbaiStatsPoller !== 'undefined' &&
                window.bbaiStatsPoller &&
                typeof window.bbaiStatsPoller.stop === 'function'
            ) {
                window.bbaiStatsPoller.stop();
            }
        }

        function restoreLibraryRowAltPlaceholder() {
            if (originalAltHtml) {
                if (altSlot) {
                    altSlot.innerHTML = originalAltHtml;
                } else if (altCell) {
                    altCell.innerHTML = originalAltHtml;
                }
            }
        }

        function applyLibraryRegenerateSuccess(altText, payload) {
            var wasMissingRow = !!(row && String(row.getAttribute('data-alt-missing') || 'false') === 'true');
            var workspaceRootForMissing = getLibraryWorkspaceRoot();
            var missingBefore = workspaceRootForMissing
                ? parseInt(workspaceRootForMissing.getAttribute('data-bbai-missing-count'), 10)
                : NaN;
            if (isNaN(missingBefore)) {
                missingBefore = null;
            }

            var statsPayload = coerceDbaiStatsObject(payload ? payload.stats : null);
            var renderOptions = buildLibraryRenderOptionsFromMeta(payload && payload.meta ? payload.meta : null, {
                approved: false
            });
            var trimmedAlt = String(altText || '').trim();

            if (row && trimmedAlt) {
                applyRegenerateSuccessToRow(row, attachmentId, trimmedAlt, payload, renderOptions);
            }

            if (statsPayload) {
                updateAltCoverageCard(statsPayload);
                updateLibraryReviewFilterCounts(statsPayload);
                dispatchDashboardStatsUpdated(statsPayload);
            }

            var payloadMissing = statsPayload ? parseInt(statsPayload.images_missing_alt, 10) : NaN;
            var serverMissingStale =
                wasMissingRow &&
                trimmedAlt &&
                (!statsPayload ||
                    isNaN(payloadMissing) ||
                    (missingBefore != null && !isNaN(payloadMissing) && payloadMissing >= missingBefore));

            if (serverMissingStale) {
                applyOptimisticLibraryCountsAfterMissingResolved();
            }

            refreshLibraryWorkspaceStatsFromRest();
            window.setTimeout(function() {
                refreshLibraryWorkspaceStatsFromRest();
            }, 450);

            if (payload.usage && typeof window.alttextai_refresh_usage === 'function') {
                window.alttextai_refresh_usage(payload.usage);
            } else if (typeof refreshUsageStats === 'function') {
                refreshUsageStats();
            }

            if (typeof window.bbaiUpdateTrialUsage === 'function') {
                window.bbaiUpdateTrialUsage();
            }

            setDashboardRuntimeState('generation_complete');

            if (trimmedAlt) {
                var successMsg = isMissing
                    ? __('ALT text generated', 'beepbeep-ai-alt-text-generator')
                    : __('ALT text regenerated', 'beepbeep-ai-alt-text-generator');
                if (isMissing && typeof getLibraryActiveFilter === 'function' && getLibraryActiveFilter() === 'missing') {
                    successMsg =
                        __('ALT text generated.', 'beepbeep-ai-alt-text-generator') +
                        ' ' +
                        __(
                            'This image will leave the Missing list — use All images or Needs review to find it.',
                            'beepbeep-ai-alt-text-generator'
                        );
                }
                notifyLibraryFeedback('success', successMsg);
            }
        }

        function handleLibraryRegenerateFailure(payload, xhr) {
            restoreLibraryRowAltPlaceholder();

            var errorData = payload || {};
            if (xhr && xhr.responseJSON) {
                errorData =
                    xhr.responseJSON.data && typeof xhr.responseJSON.data === 'object'
                        ? xhr.responseJSON.data
                        : xhr.responseJSON;
            }

            if (errorData.code === 'bbai_trial_exhausted') {
                handleTrialExhausted(errorData);
                return;
            }
            if (isLimitReachedError(errorData)) {
                handleLimitReached(normalizeLimitErrorData(errorData));
                return;
            }

            var errorMessage =
                errorData.message || __('Failed to regenerate ALT text. Please try again.', 'beepbeep-ai-alt-text-generator');
            setDashboardRuntimeState('generation_failed');
            notifyLibraryFeedback('error', errorMessage);
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                request_key: 'library-' + attachmentId + '-' + Date.now(),
                nonce: nonceValue
            },
            timeout: 120000
        })
            .done(function(response) {
                var payload = getNormalizedResponsePayload(response);
                var ajaxOk =
                    response &&
                    (response.success === true ||
                        response.success === 1 ||
                        response.success === 'true' ||
                        response.success === '1');

                if (ajaxOk) {
                    var extractedAlt = extractAltStringFromRegeneratePayload(payload);

                    function finishWithFinalAlt(finalAlt) {
                        var useAlt = String(finalAlt || '').trim();
                        if (!useAlt) {
                            setDashboardRuntimeState('generation_failed');
                            restoreLibraryRowAltPlaceholder();
                            notifyLibraryFeedback(
                                'error',
                                __('No ALT text was generated. Please try again.', 'beepbeep-ai-alt-text-generator')
                            );
                            finalizeLibraryRegenerateRowUi();
                            return;
                        }
                        payload.alt_text = useAlt;
                        payload.altText = useAlt;
                        applyLibraryRegenerateSuccess(useAlt, payload);
                        finalizeLibraryRegenerateRowUi();
                    }

                    fetchLibraryPersistedAlt(
                        attachmentId,
                        function(dbAlt) {
                            var fromDb = String(dbAlt || '').trim();
                            finishWithFinalAlt(fromDb || extractedAlt);
                        },
                        function() {
                            finishWithFinalAlt(extractedAlt);
                        }
                    );
                    return;
                }

                handleLibraryRegenerateFailure(payload, null);
                finalizeLibraryRegenerateRowUi();
            })
            .fail(function(xhr) {
                var errorPayload =
                    xhr && xhr.responseJSON && xhr.responseJSON.data && typeof xhr.responseJSON.data === 'object'
                        ? xhr.responseJSON.data
                        : {};
                handleLibraryRegenerateFailure(errorPayload, xhr);
                finalizeLibraryRegenerateRowUi();
            });

        return false;
    }

    /**
     * Show regenerate modal and start generation
     */
    function showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalBtnText) {
        // Check if modal exists, if not create it
        var $modal = $('#bbai-regenerate-modal');
        if (!$modal.length) {
            $modal = createRegenerateModal();
        }

        // Populate modal with image info
        $modal.find('.bbai-regenerate-modal__image-title').text(imageTitle);
        $modal.find('.bbai-regenerate-modal__thumbnail').attr('src', imageSrc);

        // Show loading state
        $modal.find('.bbai-regenerate-modal__loading').addClass('active');
        $modal.find('.bbai-regenerate-modal__result').removeClass('active');
        $modal.find('.bbai-regenerate-modal__error').removeClass('active');
        setDashboardRuntimeState('generation_running');

        // Disable accept button during loading
        $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        // Prevent stale AJAX responses from older regenerate requests from mutating the active modal state.
        var requestKey = 'regen-' + attachmentId + '-' + Date.now();
        $modal.data('bbai-request-key', requestKey);
        $modal.removeData('bbai-regenerate-payload');

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Starting AJAX request...');

        // Use AJAX endpoint for single regeneration
        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) || '';
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) ||
                       (window.BBAI && window.BBAI.nonce) ||
                       '';
        if (!ajaxUrl) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] AJAX endpoint unavailable.');
            showModalError($modal, __('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'));
            reenableButton($btn, originalBtnText);
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                request_key: requestKey,
                nonce: nonceValue
            },
            timeout: 120000
        })
        .done(function(response) {
            if ($modal.data('bbai-request-key') !== requestKey) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Ignoring stale regenerate response', {
                    expectedRequestKey: $modal.data('bbai-request-key'),
                    responseRequestKey: requestKey
                });
                return;
            }

            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Regenerate response:', response);
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Response type:', typeof response);
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Response.data:', response.data);

            // Hide loading state
            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');

            var modalAjaxOk =
                response &&
                (response.success === true ||
                    response.success === 1 ||
                    response.success === 'true' ||
                    response.success === '1');

            if (modalAjaxOk) {
                var payload = getNormalizedResponsePayload(response);
                var newAltText = extractAltStringFromRegeneratePayload(payload);
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] New alt text:', newAltText);
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Alt text length:', newAltText ? newAltText.length : 0);
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Full response:', response);

                function finishModalRegenerateSuccess(altStr) {
                    if (!altStr) {
                        $modal.removeData('bbai-regenerate-payload');
                        setDashboardRuntimeState('generation_failed');
                        showModalError(
                            $modal,
                            __('No alt text was generated. Please try again.', 'beepbeep-ai-alt-text-generator')
                        );
                        reenableButton($btn, originalBtnText);
                        return;
                    }
                    payload.alt_text = altStr;
                    payload.altText = altStr;
                    $modal.find('.bbai-regenerate-modal__alt-text').text(altStr);
                    $modal.find('.bbai-regenerate-modal__result').addClass('active');
                    $modal.data('bbai-regenerate-payload', payload);
                    setDashboardRuntimeState('generation_complete');

                    var usageInResponse = payload.usage || null;
                    if (usageInResponse && typeof usageInResponse === 'object') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Updating usage from response:', usageInResponse);
                        usageInResponse = mirrorUsagePayload(usageInResponse) || usageInResponse;

                        if (typeof window.alttextai_refresh_usage === 'function') {
                            window.alttextai_refresh_usage(usageInResponse);
                        } else if (typeof refreshUsageStats === 'function') {
                            refreshUsageStats(usageInResponse);
                        }
                    } else {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] No usage in response, fetching from API');
                        if (typeof refreshUsageStats === 'function') {
                            refreshUsageStats();
                        } else if (typeof window.alttextai_refresh_usage === 'function') {
                            window.alttextai_refresh_usage();
                        }
                    }

                    if (typeof window.bbaiUpdateTrialUsage === 'function') {
                        window.bbaiUpdateTrialUsage();
                    }

                    $modal.find('.bbai-regenerate-modal__btn--accept')
                        .prop('disabled', false)
                        .off('click')
                        .on('click', function() {
                            acceptRegeneratedAltText(attachmentId, altStr, $btn, originalBtnText, $modal);
                        });
                }

                if (newAltText) {
                    finishModalRegenerateSuccess(newAltText);
                } else {
                    fetchLibraryPersistedAlt(
                        attachmentId,
                        function(fetched) {
                            finishModalRegenerateSuccess(fetched);
                        },
                        function() {
                            finishModalRegenerateSuccess('');
                        }
                    );
                }
            } else {
                $modal.removeData('bbai-regenerate-payload');
                // Check for trial exhausted or limit_reached error
                var errorData = response && response.data ? response.data : {};
                if (errorData.code === 'bbai_trial_exhausted') {
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    handleTrialExhausted(errorData);
                } else if (isLimitReachedError(errorData)) {
                    var normalizedLimitError = normalizeLimitErrorData(errorData);
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);
                    handleLimitReached(normalizedLimitError);
                } else if (errorData.code === 'auth_required' || (errorData.message && errorData.message.toLowerCase().includes('authentication required'))) {
                    // Authentication required - show login modal
                    closeRegenerateModal($modal);
                    reenableButton($btn, originalBtnText);

                    // Show login modal
                    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                        window.authModal.show();
                        window.authModal.showLoginForm();
                    } else if (typeof showAuthModal === 'function') {
                        showAuthModal('login');
                    } else if (typeof showAuthLogin === 'function') {
                        showAuthLogin();
                    } else {
                        showModalError($modal, __('Please log in to regenerate alt text.', 'beepbeep-ai-alt-text-generator'));
                    }
                } else {
                    // Check for image validation errors
                    var errorCode = errorData.code || '';
                    var errorMessage = errorData.message || __('Failed to regenerate ALT text. Please try again.', 'beepbeep-ai-alt-text-generator');

                    // Provide user-friendly messages for common image errors
                    if (errorCode === 'image_too_small') {
                        errorMessage = __('This image is too small or invalid. Please use a valid image file (at least 10x10 pixels and 100 bytes).', 'beepbeep-ai-alt-text-generator');
                    } else if (errorCode === 'image_too_large') {
                        errorMessage = __('This image file is too large. Please try a smaller image or contact support.', 'beepbeep-ai-alt-text-generator');
                    } else if (errorCode === 'missing_image_data') {
                        errorMessage = __('Image data could not be loaded. Please ensure the image file exists and is accessible.', 'beepbeep-ai-alt-text-generator');
                    } else if (errorMessage.toLowerCase().includes('validation failed')) {
                        errorMessage = __('Image validation failed. This image may be corrupted, too small, or in an unsupported format. Please try a different image.', 'beepbeep-ai-alt-text-generator');
                    }

                    setDashboardRuntimeState('generation_failed');
                    showModalError($modal, errorMessage);
                    reenableButton($btn, originalBtnText);
                }
            }
        })
        .fail(function(xhr, status, error) {
            if ($modal.data('bbai-request-key') !== requestKey) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Ignoring stale regenerate failure response', {
                    expectedRequestKey: $modal.data('bbai-request-key'),
                    responseRequestKey: requestKey
                });
                return;
            }

            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to regenerate:', error, xhr);

            // Hide loading state
            $modal.find('.bbai-regenerate-modal__loading').removeClass('active');
            $modal.removeData('bbai-regenerate-payload');

            // Check for trial exhausted or limit_reached error in response
            var errorData = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
            if (errorData.code === 'bbai_trial_exhausted') {
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                handleTrialExhausted(errorData);
            } else if (isLimitReachedError(errorData)) {
                var normalizedFailLimitError = normalizeLimitErrorData(errorData);
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
                handleLimitReached(normalizedFailLimitError);
            } else if (errorData.code === 'auth_required' || (errorData.message && errorData.message.toLowerCase().includes('authentication required'))) {
                // Authentication required - show login modal
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);

                // Show login modal
                if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    window.authModal.showLoginForm();
                } else if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                } else if (typeof showAuthLogin === 'function') {
                    showAuthLogin();
                } else {
                    showModalError($modal, __('Please log in to regenerate alt text.', 'beepbeep-ai-alt-text-generator'));
                }
            } else {
                var message = errorData.message || __('Failed to regenerate ALT text. Please try again.', 'beepbeep-ai-alt-text-generator');
                setDashboardRuntimeState('generation_failed');
                showModalError($modal, message);
                reenableButton($btn, originalBtnText);
            }
        });

        // Handle cancel button
        $modal.find('.bbai-regenerate-modal__btn--cancel')
            .off('click')
            .on('click', function() {
                $modal.removeData('bbai-request-key');
                closeRegenerateModal($modal);
                reenableButton($btn, originalBtnText);
            });
    }

    /**
     * Create the regenerate modal HTML
     */
    function createRegenerateModal() {
        var modalHtml =
            '<div id="bbai-regenerate-modal" class="bbai-regenerate-modal">' +
            '    <div class="bbai-regenerate-modal__content">' +
            '        <div class="bbai-regenerate-modal__header">' +
            '            <h2 class="bbai-regenerate-modal__title">' + escapeHtml(__('Regenerate Alt Text', 'beepbeep-ai-alt-text-generator')) + '</h2>' +
            '            <p class="bbai-regenerate-modal__subtitle">' + escapeHtml(__('Review the new alt text before applying', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__body">' +
            '            <div class="bbai-regenerate-modal__image-preview">' +
            '                <img src="" alt="" class="bbai-regenerate-modal__thumbnail">' +
            '                <div class="bbai-regenerate-modal__image-info">' +
            '                    <p class="bbai-regenerate-modal__image-title"></p>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__error"></div>' +
            '            <div class="bbai-regenerate-modal__loading">' +
            '                <div class="bbai-regenerate-modal__spinner"></div>' +
            '                <p class="bbai-regenerate-modal__loading-text">' + escapeHtml(__('Generating new alt text...', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '            </div>' +
            '            <div class="bbai-regenerate-modal__result">' +
            '                <p class="bbai-regenerate-modal__alt-text-label">' + escapeHtml(__('New Alt Text:', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '                <p class="bbai-regenerate-modal__alt-text"></p>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-regenerate-modal__footer">' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--cancel">' + escapeHtml(__('Cancel', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '            <button type="button" class="bbai-regenerate-modal__btn bbai-regenerate-modal__btn--accept" disabled>' + escapeHtml(__('Accept & Apply', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        return $('#bbai-regenerate-modal');
    }

    /**
     * Show error in modal
     */
    function showModalError($modal, message) {
        $modal.find('.bbai-regenerate-modal__error').text(message).addClass('active');
    }

    function clearBodyScrollLocks() {
        $('body').css('overflow', '').removeClass('bbai-modal-open');
        if (document.body) {
            document.body.style.overflow = '';
        }
        if (document.documentElement) {
            document.documentElement.style.overflow = '';
        }
        ensureBbaiDashboardMainVisible();
    }

    /**
     * Close regenerate modal
     */
    function closeRegenerateModal($modal) {
        $modal.removeData('bbai-request-key');
        $modal.removeData('bbai-regenerate-payload');
        $modal.removeClass('active');
        clearBodyScrollLocks();
    }

    /**
     * Re-enable the regenerate button
     */
    function reenableButton($btn, originalText) {
        $btn.prop('disabled', false).removeClass('regenerating');
        var originalHtml = $btn.data('bbai-original-html');
        if (originalHtml) {
            $btn.html(originalHtml);
            $btn.removeData('bbai-original-html');
            return;
        }
        $btn.text(originalText);
    }

    /**
     * Calculate SEO quality score for alt text (client-side version).
     * Stricter scoring to catch nonsensical or non-descriptive text.
     */
    function calculateSeoQuality(text) {
        if (!text || text.trim() === '') {
            return { score: 0, grade: 'F', badge: 'missing' };
        }

        var score = 100;
        var textLength = text.length;
        var words = text.trim().split(/\s+/).filter(function(w) { return w.length > 0; });
        var wordCount = words.length;

        // Check length (125 chars recommended)
        if (textLength > 125) {
            score -= 25;
        }

        // Check for redundant prefixes
        var lowerText = text.toLowerCase().trim();
        var redundantPrefixes = ['image of', 'picture of', 'photo of', 'photograph of', 'graphic of', 'illustration of'];
        for (var i = 0; i < redundantPrefixes.length; i++) {
            if (lowerText.indexOf(redundantPrefixes[i]) === 0) {
                score -= 20;
                break;
            }
        }

        // Check for filename patterns
        if (/^IMG[-_]\d+/i.test(text) || /^DSC[-_]\d+/i.test(text) || /\.(jpg|jpeg|png|gif|webp)$/i.test(text)) {
            score -= 30;
        }

        // Strong penalty for too few words — 2 words or less rarely describes an image
        if (wordCount < 3) {
            score -= 50;
            if (textLength < 20) {
                score -= 20;
            }
        } else if (!hasDescriptiveContent(text, words)) {
            score -= 15;
        }

        // Gibberish check: longest word has no vowels (a,e,i,o,u) — likely nonsensical
        var longestWord = '';
        for (var j = 0; j < words.length; j++) {
            var lettersOnly = words[j].replace(/[^a-zA-Z]/g, '');
            if (lettersOnly.length > 2 && lettersOnly.length > longestWord.length) {
                longestWord = lettersOnly;
            }
        }
        if (longestWord.length >= 3 && !/[aeiou]/i.test(longestWord)) {
            score -= 35;
        }

        // Placeholder / non-descriptive check
        var nondescriptiveWords = ['test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder', 'sample', 'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp', 'crap', 'stuff', 'thing', 'things', 'something', 'anything', 'whatever', 'blah', 'meh', 'idk', 'nada', 'random', 'garbage', 'junk', 'dummy', 'fake', 'lorem', 'ipsum'];
        var lowerWords = words.map(function(w) { return w.toLowerCase(); });
        var badCount = 0;
        for (var k = 0; k < nondescriptiveWords.length; k++) {
            if (lowerWords.indexOf(nondescriptiveWords[k]) !== -1) badCount++;
        }
        if (badCount >= 1 && (badCount >= 2 || words.length <= 4)) {
            score -= 50;
        }

        score = Math.max(0, score);

        var grade, badge;
        if (score >= 90) { grade = 'A'; badge = 'excellent'; }
        else if (score >= 75) { grade = 'B'; badge = 'good'; }
        else if (score >= 60) { grade = 'C'; badge = 'fair'; }
        else if (score >= 40) { grade = 'D'; badge = 'poor'; }
        else { grade = 'F'; badge = 'needs-work'; }

        var hardFail = wordCount < 3 || score < 50;

        return { score: score, grade: grade, badge: badge, hardFail: hardFail };
    }

    function hasDescriptiveContent(text, words) {
        if (!words || words.length < 3) return false;
        for (var i = 0; i < words.length; i++) {
            if (words[i].replace(/[^a-zA-Z]/g, '').length > 3) return true;
        }
        return false;
    }

    function getLibraryRowFromTrigger(trigger) {
        if (!trigger || !trigger.closest) {
            return null;
        }
        return trigger.closest('.bbai-library-row');
    }

    function getLibraryAltEndpoint(attachmentId) {
        var id = parseInt(attachmentId, 10);
        if (!id || id <= 0) {
            return '';
        }

        var base = config.restAlt || ((config.restRoot || '') + 'bbai/v1/alt/');
        if (!base) {
            return '';
        }

        if (base.charAt(base.length - 1) !== '/') {
            base += '/';
        }

        return base + id;
    }

    function getLibraryReviewEndpoint(attachmentId) {
        var id = parseInt(attachmentId, 10);
        if (!id || id <= 0) {
            return '';
        }

        var base = (config.restRoot || '') + 'bbai/v1/review/';
        if (!base) {
            return '';
        }

        if (base.charAt(base.length - 1) !== '/') {
            base += '/';
        }

        return base + id;
    }

    function getLibraryReviewBatchEndpoint() {
        return (config.restRoot || '') + 'bbai/v1/review';
    }

    function getLibraryAltClearBatchEndpoint() {
        return (config.restRoot || '') + 'bbai/v1/alt/clear';
    }

    function getLibraryRestNonce() {
        if (typeof wpApiSettings !== 'undefined' && wpApiSettings && wpApiSettings.nonce) {
            return wpApiSettings.nonce;
        }
        return (
            (window.BBAI && window.BBAI.nonce) ||
            (window.BBAI_DASH && window.BBAI_DASH.nonce) ||
            config.nonce ||
            ''
        );
    }

    function getNormalizedResponsePayload(response) {
        if (response && response.data != null) {
            if (typeof response.data === 'object' && !Array.isArray(response.data)) {
                return response.data;
            }
            if (typeof response.data === 'string') {
                try {
                    var parsed = JSON.parse(response.data);
                    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                        return parsed;
                    }
                } catch (parseErr) {
                    /* ignore */
                }
            }
        }

        return response && typeof response === 'object' ? response : {};
    }

    function extractAltStringFromRegeneratePayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return '';
        }

        function pickAlt(obj, depth) {
            depth = depth || 0;
            if (depth > 8 || !obj || typeof obj !== 'object') {
                return '';
            }
            var v =
                obj.altText ||
                obj.alt_text ||
                obj.alt ||
                obj.description ||
                obj.text ||
                obj.new_alt ||
                obj.newAlt ||
                obj.generated_alt ||
                obj.generatedAlt;
            if (v == null || v === '') {
                return '';
            }
            if (typeof v === 'number' && isFinite(v)) {
                return String(v).trim();
            }
            if (typeof v === 'string') {
                return v.trim();
            }
            if (Array.isArray(v) && v.length) {
                return pickAlt({ alt_text: v[0] }, depth + 1);
            }
            if (typeof v === 'object') {
                return pickAlt(v, depth + 1);
            }
            return '';
        }

        var direct = pickAlt(payload);
        if (direct) {
            return direct;
        }

        if (payload.data && typeof payload.data === 'object') {
            direct = pickAlt(payload.data);
            if (direct) {
                return direct;
            }
        }

        var nestedKeys = ['result', 'output', 'response'];
        var nk;
        for (nk = 0; nk < nestedKeys.length; nk++) {
            var nest = payload[nestedKeys[nk]];
            if (nest && typeof nest === 'object') {
                direct = pickAlt(nest);
                if (direct) {
                    return direct;
                }
            }
        }

        return '';
    }

    function getBbaiResolvedRestRoot() {
        var live = window.BBAI_DASH || window.BBAI || config || {};
        var root = String((live && live.restRoot) || '').replace(/\/?$/, '/');
        if (!root && typeof wpApiSettings !== 'undefined' && wpApiSettings && wpApiSettings.root) {
            root = String(wpApiSettings.root).replace(/\/?$/, '/');
        }
        return root;
    }

    function getLibraryReadAltEndpoint(attachmentId) {
        var id = parseInt(attachmentId, 10);
        if (!id || id <= 0) {
            return '';
        }

        var root = getBbaiResolvedRestRoot();
        if (!root) {
            return '';
        }

        return root + 'bbai/v1/attachment-alt/' + id;
    }

    function fetchLibraryPersistedAlt(attachmentId, onFound, onMissing) {
        var readUrl = getLibraryReadAltEndpoint(attachmentId);
        var restNonce = getLibraryRestNonce();
        if (!readUrl || !restNonce) {
            if (typeof onMissing === 'function') {
                onMissing();
            }
            return;
        }
        $.ajax({
            url: readUrl,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-WP-Nonce': restNonce
            },
            timeout: 15000
        })
            .done(function(body) {
                var fetched = extractAltStringFromRegeneratePayload(body || {});
                if (fetched && typeof onFound === 'function') {
                    onFound(fetched);
                } else if (typeof onMissing === 'function') {
                    onMissing();
                }
            })
            .fail(function() {
                if (typeof onMissing === 'function') {
                    onMissing();
                }
            });
    }

    function coerceDbaiStatsObject(maybe) {
        if (maybe == null) {
            return null;
        }
        if (typeof maybe === 'string') {
            try {
                var parsed = JSON.parse(maybe);
                return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
            } catch (coerceErr) {
                return null;
            }
        }
        if (typeof maybe === 'object' && !Array.isArray(maybe)) {
            return maybe;
        }
        return null;
    }

    function refreshLibraryWorkspaceStatsFromRest(onDone) {
        var liveCfg = window.BBAI_DASH || window.BBAI || config || {};
        var base = liveCfg.restStats;
        if (!base) {
            var rr = getBbaiResolvedRestRoot();
            base = rr ? rr + 'bbai/v1/stats' : '';
        }
        if (!base) {
            if (typeof onDone === 'function') {
                onDone();
            }
            return;
        }
        var url = String(base).indexOf('?') === -1 ? base + '?fresh=1' : base + '&fresh=1';
        var nonce = getLibraryRestNonce();
        if (!nonce) {
            if (typeof onDone === 'function') {
                onDone();
            }
            return;
        }
        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-WP-Nonce': nonce
            },
            timeout: 12000
        })
            .done(function(body) {
                if (!body || typeof body !== 'object') {
                    return;
                }
                updateAltCoverageCard(body);
                updateLibraryReviewFilterCounts(body);
                dispatchDashboardStatsUpdated(body);
            })
            .always(function() {
                if (typeof onDone === 'function') {
                    onDone();
                }
            });
    }

    function getLibraryAltTextFromRow(row) {
        if (!row) {
            return '';
        }
        return String(row.getAttribute('data-alt-full') || '').trim();
    }

    function getLibraryWorkspaceRoot() {
        var roots = document.querySelectorAll('[data-bbai-library-workspace-root="1"]');
        if (!roots.length) {
            return null;
        }
        var i;
        for (i = 0; i < roots.length; i++) {
            if (roots[i] && roots[i].offsetParent !== null) {
                return roots[i];
            }
        }
        return roots[0];
    }

    function getLibraryAutomationSettingsUrl() {
        var root = getLibraryWorkspaceRoot();
        return root ? String(root.getAttribute('data-bbai-automation-settings-url') || '').trim() : '';
    }

    function isLibraryProPlan() {
        var root = getLibraryWorkspaceRoot();
        return !!(root && root.getAttribute('data-bbai-is-pro-plan') === 'true');
    }

    function getLibraryImageContextLabel(row) {
        if (!row) {
            return '';
        }

        var raw = String(row.getAttribute('data-image-title') || row.getAttribute('data-file-name') || '').trim();
        if (!raw) {
            return '';
        }

        raw = raw.replace(/\.[a-z0-9]{2,5}$/i, '');
        raw = raw.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
        if (!raw || /^\d+$/.test(raw)) {
            return '';
        }

        return raw;
    }

    function normalizeAltDraftText(text) {
        return String(text || '')
            .replace(/\s+/g, ' ')
            .replace(/^\s+|\s+$/g, '')
            .replace(/^(image|picture|photo|photograph|graphic|illustration)\s+of\s+/i, '');
    }

    function makeAltTextShorter(text) {
        var clean = normalizeAltDraftText(text);
        if (!clean) {
            return '';
        }

        var shortened = clean
            .replace(/\s*,\s*close[- ]?up\b/gi, '')
            .replace(/\s*,\s*with.*$/i, '')
            .replace(/\s{2,}/g, ' ')
            .trim();

        var words = shortened.split(/\s+/);
        if (shortened.length > 125 || words.length > 16) {
            shortened = words.slice(0, 14).join(' ').replace(/[,:;.-]+$/, '').trim();
        }

        return shortened;
    }

    function makeAltTextMoreDescriptive(text, row) {
        var clean = normalizeAltDraftText(text);
        if (!clean) {
            return '';
        }

        var words = clean.split(/\s+/).filter(function(word) {
            return word.length > 0;
        });
        var context = getLibraryImageContextLabel(row);
        var lowerClean = clean.toLowerCase();

        if (context && lowerClean.indexOf(context.toLowerCase()) === -1 && words.length <= 9) {
            return (clean.replace(/[.]+$/, '') + ', ' + context).trim();
        }

        if (words.length <= 5 && lowerClean.indexOf('showing ') !== 0) {
            return ('Image showing ' + clean).trim();
        }

        return clean;
    }

    function getLibraryModalQualityState(text) {
        var quality = getLibraryQualityMeta(text);
        var key = 'needs-work';
        var label = __('Needs improvement', 'beepbeep-ai-alt-text-generator');

        if (quality.score >= 80) {
            key = 'high';
            label = __('High', 'beepbeep-ai-alt-text-generator');
        } else if (quality.score >= 60) {
            key = 'good';
            label = __('Good', 'beepbeep-ai-alt-text-generator');
        }

        return {
            key: key,
            label: label,
            tooltip: getLibraryQualityTooltip(quality.key, String(text || '').trim() !== ''),
            score: quality.score
        };
    }

    function getLibraryNowDateLabel() {
        try {
            return new Intl.DateTimeFormat(undefined, {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            }).format(new Date());
        } catch (e) {
            return new Date().toLocaleDateString();
        }
    }

    function updateLibraryLastUpdated(row, explicitValue) {
        if (!row) {
            return;
        }

        var nextValue = String(explicitValue || '').trim();
        if (!nextValue) {
            nextValue = getLibraryNowDateLabel();
        }

        row.setAttribute('data-last-updated', nextValue);
        var labelNode = row.querySelector('.bbai-library-info-updated');
        if (labelNode) {
            labelNode.textContent = sprintf(__('Updated %s', 'beepbeep-ai-alt-text-generator'), nextValue);
        }
        var metaNode = row.querySelector('.bbai-library-card__meta');
        if (metaNode) {
            var fileMeta = String(row.getAttribute('data-file-meta') || '').trim();
            var parts = [];
            if (fileMeta) {
                parts.push(fileMeta);
            }
            parts.push(sprintf(__('Updated %s', 'beepbeep-ai-alt-text-generator'), nextValue));
            metaNode.textContent = parts.join(' • ');
        }
    }

    function getSharedBannerNode() {
        return document.querySelector('[data-bbai-shared-banner="1"]');
    }

    function removeSharedUsageBanners() {
        var selectors = [
            '.bbai-quota-exhausted-banner'
        ];
        for (var i = 0; i < selectors.length; i++) {
            var nodes = document.querySelectorAll(selectors[i]);
            for (var n = 0; n < nodes.length; n++) {
                if (nodes[n] && nodes[n].parentNode) {
                    nodes[n].parentNode.removeChild(nodes[n]);
                }
            }
        }
    }

    function resolveSharedBannerMissingCount(banner) {
        if (!banner) {
            return 0;
        }

        var attr = parseInt(banner.getAttribute('data-bbai-banner-missing-count') || '', 10);
        return isNaN(attr) || attr < 0 ? 0 : attr;
    }

    function updateSharedBannerMissingFromRowStatus(previousStatus, nextStatus) {
        if (previousStatus === nextStatus) {
            return;
        }

        var banner = getSharedBannerNode();
        if (!banner) {
            return;
        }

        var currentMissing = parseInt(banner.getAttribute('data-bbai-banner-missing-count') || '', 10);
        if (isNaN(currentMissing) || currentMissing < 0) {
            currentMissing = 0;
        }

        if (previousStatus === 'missing' && nextStatus !== 'missing') {
            currentMissing = Math.max(0, currentMissing - 1);
        } else if (previousStatus !== 'missing' && nextStatus === 'missing') {
            currentMissing += 1;
        } else {
            return;
        }

        banner.setAttribute('data-bbai-banner-missing-count', String(currentMissing));
        if (window.bbai_admin && window.bbai_admin.counts) {
            window.bbai_admin.counts.missing = currentMissing;
        }
    }

    function setSharedBannerCtaState(banner, outOfCredits, missingCount) {
        if (!banner) {
            return;
        }

        var upgradeCta = banner.querySelector('[data-bbai-banner-cta="upgrade"]');
        var generateCta = banner.querySelector('[data-bbai-banner-cta="generate"]');
        var libraryCta = banner.querySelector('[data-bbai-banner-cta="library"]');

        var showUpgrade = !!outOfCredits;
        var showGenerate = !outOfCredits && missingCount > 0;
        var showLibrary = !outOfCredits && missingCount <= 0;

        if (upgradeCta) {
            if (showUpgrade) {
                upgradeCta.removeAttribute('hidden');
            } else {
                upgradeCta.setAttribute('hidden', 'hidden');
            }
        }

        if (generateCta) {
            if (showGenerate) {
                generateCta.removeAttribute('hidden');
            } else {
                generateCta.setAttribute('hidden', 'hidden');
            }
        }

        if (libraryCta) {
            if (!libraryCta.getAttribute('href')) {
                var fallbackLibraryUrl = banner.getAttribute('data-bbai-banner-library-url');
                if (fallbackLibraryUrl) {
                    libraryCta.setAttribute('href', fallbackLibraryUrl);
                }
            }

            if (showLibrary) {
                libraryCta.removeAttribute('hidden');
            } else {
                libraryCta.setAttribute('hidden', 'hidden');
            }
        }
    }

    function getUsageFromBanner(banner) {
        if (!banner || !banner.getAttribute) {
            return null;
        }
        var used = parseInt(banner.getAttribute('data-bbai-banner-used') || '', 10);
        var limit = parseInt(banner.getAttribute('data-bbai-banner-limit') || '', 10);
        if (isNaN(used) || isNaN(limit) || limit <= 0) {
            return null;
        }
        var remaining = parseInt(
            banner.getAttribute('data-bbai-banner-remaining') ||
            banner.getAttribute('data-bbai-credits-remaining') ||
            '',
            10
        );
        if (isNaN(remaining)) {
            remaining = Math.max(0, limit - used);
        }
        var daysLeft = parseInt(banner.getAttribute('data-bbai-banner-days-left') || '', 10);
        if (isNaN(daysLeft) || daysLeft < 0) {
            daysLeft = 0;
        }
        var authState = String(
            banner.getAttribute('data-bbai-banner-auth-state') ||
            banner.getAttribute('data-bbai-auth-state') ||
            ''
        );
        var quotaType = String(
            banner.getAttribute('data-bbai-banner-quota-type') ||
            banner.getAttribute('data-bbai-quota-type') ||
            ''
        );
        var quotaState = String(
            banner.getAttribute('data-bbai-banner-quota-state') ||
            banner.getAttribute('data-bbai-quota-state') ||
            ''
        );
        var freePlanOffer = parseInt(
            banner.getAttribute('data-bbai-banner-free-plan-offer') ||
            banner.getAttribute('data-bbai-free-plan-offer') ||
            '50',
            10
        );
        var lowCreditThreshold = parseInt(
            banner.getAttribute('data-bbai-banner-low-credit-threshold') ||
            banner.getAttribute('data-bbai-low-credit-threshold') ||
            '',
            10
        );
        return {
            used: used,
            limit: limit,
            remaining: remaining,
            auth_state: authState,
            quota_type: quotaType,
            quota_state: quotaState,
            signup_required: (
                banner.getAttribute('data-bbai-banner-signup-required') ||
                banner.getAttribute('data-bbai-signup-required')
            ) === '1',
            free_plan_offer: isNaN(freePlanOffer) ? 50 : Math.max(0, freePlanOffer),
            is_trial: quotaType === 'trial' || authState === 'anonymous',
            low_credit_threshold: isNaN(lowCreditThreshold) ? null : Math.max(0, lowCreditThreshold),
            days_until_reset: daysLeft,
            reset_timestamp: daysLeft >= 0 ? (Date.now() / 1000) + (daysLeft * 86400) : 0
        };
    }

    function syncSharedUsageBanner(usageInput) {
        var banner = getSharedBannerNode();
        if (!banner) {
            return;
        }

        var usage = getUsageSnapshot(usageInput) || getUsageFromDom() || getUsageFromBanner(banner);
        var bannerUsage = getUsageFromBanner(banner);
        if (bannerUsage && usage && typeof usage === 'object') {
            var snapshotUsed = parseInt(usage.used, 10);
            var snapshotLimit = parseInt(usage.limit, 10);
            if ((!isFinite(snapshotUsed) || snapshotUsed < 0 || !isFinite(snapshotLimit) || snapshotLimit <= 0) ||
                (snapshotUsed === 0 && bannerUsage.used > 0) ||
                (snapshotLimit > 0 && bannerUsage.limit > 0 && snapshotLimit !== bannerUsage.limit)) {
                usage = bannerUsage;
            }
        } else if (!usage && bannerUsage) {
            usage = bannerUsage;
        }
        if (!usage || typeof usage !== 'object') {
            return;
        }
        var used = parseInt(usage.used, 10);
        var limit = parseInt(usage.limit, 10);

        if (isNaN(used) || used < 0) {
            used = 0;
        }
        if (isNaN(limit) || limit <= 0) {
            limit = 50;
        }

        banner.setAttribute('data-bbai-banner-used', String(used));
        banner.setAttribute('data-bbai-banner-limit', String(limit));

        var remaining = usage.remaining !== undefined && usage.remaining !== null
            ? parseInt(usage.remaining, 10)
            : (limit - used);
        if (isNaN(remaining)) {
            remaining = limit - used;
        }
        remaining = Math.max(0, remaining);

        var outOfCredits = remaining <= 0 || used >= limit;
        var percentage = Math.min(100, Math.max(0, Math.round((used / Math.max(limit, 1)) * 100)));
        var resetMeta = getQuotaResetMeta(usage);
        var daysLeft = resetMeta && typeof resetMeta.daysUntilReset === 'number'
            ? Math.max(0, resetMeta.daysUntilReset)
            : parseInt(banner.getAttribute('data-bbai-banner-days-left') || '', 10);
        if (isNaN(daysLeft) || daysLeft < 0) {
            daysLeft = 0;
        }

        var missingCount = resolveSharedBannerMissingCount(banner);
        banner.setAttribute('data-bbai-banner-days-left', String(daysLeft));
        banner.setAttribute('data-bbai-banner-missing-count', String(missingCount));
        banner.setAttribute('data-bbai-banner-remaining', String(remaining));

        // The dashboard hero has its own renderer in bbai-dashboard.js.
        // Let that renderer own the DOM so this legacy sync path does not
        // flatten the chip-style usage row into older inline copy after load.
        if (
            typeof banner.matches === 'function' &&
            banner.matches('[data-bbai-dashboard-hero="1"]') &&
            typeof window.bbaiSyncDashboardState === 'function'
        ) {
            window.bbaiSyncDashboardState(null, {
                used: used,
                limit: limit,
                remaining: remaining,
                auth_state: usage.auth_state || '',
                quota_type: usage.quota_type || '',
                quota_state: usage.quota_state || '',
                signup_required: usage.signup_required !== undefined ? !!usage.signup_required : false,
                free_plan_offer: usage.free_plan_offer !== undefined ? usage.free_plan_offer : 50,
                low_credit_threshold: usage.low_credit_threshold !== undefined ? usage.low_credit_threshold : null,
                days_until_reset: daysLeft,
                reset_timestamp: usage.reset_timestamp || usage.resetTimestamp || usage.reset_ts || 0,
                reset_date: usage.reset_date || usage.resetDate || ''
            });
            return;
        }

        var headlineNode = banner.querySelector('[data-bbai-banner-headline]');
        var headlineNum = headlineNode ? headlineNode.querySelector('.bbai-banner-headline-number') : null;
        if (headlineNum) {
            headlineNum.textContent = used.toLocaleString();
        } else if (headlineNode) {
            headlineNode.textContent = outOfCredits
                ? sprintf(__('You optimized %s images this month 🎉', 'beepbeep-ai-alt-text-generator'), used.toLocaleString())
                : sprintf(__('You optimized %s images this month', 'beepbeep-ai-alt-text-generator'), used.toLocaleString());
        }

        var sublineNode = banner.querySelector('[data-bbai-banner-subline]');
        if (sublineNode) {
            sublineNode.textContent = sprintf(__('Credits reset in %s days.', 'beepbeep-ai-alt-text-generator'), daysLeft.toLocaleString());
        }

        var usageLineNode = banner.querySelector('[data-bbai-banner-usage-line]');
        var usageUsed = usageLineNode ? usageLineNode.querySelector('.bbai-banner-usage-used') : null;
        var usageLimit = usageLineNode ? usageLineNode.querySelector('.bbai-banner-usage-limit') : null;
        var resetCopy = buildBannerResetCopy(daysLeft);
        if (usageUsed) {
            usageUsed.textContent = used.toLocaleString();
        }
        if (usageLimit) {
            usageLimit.textContent = limit.toLocaleString();
        }
        if (usageLineNode && !usageUsed && !usageLimit) {
            usageLineNode.textContent = sprintf(
                __('%1$s / %2$s AI generations used • %3$s', 'beepbeep-ai-alt-text-generator'),
                used.toLocaleString(),
                limit.toLocaleString(),
                resetCopy
            );
        }
        if (usageLineNode) {
            usageLineNode.setAttribute('data-bbai-reset-copy', resetCopy);
        }

        var progressCopyNode = banner.querySelector('[data-bbai-banner-progress-copy]');
        if (progressCopyNode) {
            progressCopyNode.textContent = resetCopy;
        }

        var progressFill = banner.querySelector('[data-bbai-banner-progress]');
        if (progressFill) {
            progressFill.setAttribute('data-bbai-banner-progress-target', String(percentage));
            if (progressFill.getAttribute('data-bbai-banner-progress-initialized') === '1') {
                progressFill.style.transition = 'width 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
                requestAnimationFrame(function() {
                    progressFill.style.width = percentage + '%';
                });
            } else {
                progressFill.style.width = '0%';
                progressFill.style.transition = 'none';
                progressFill.offsetWidth;

                setTimeout(function() {
                    progressFill.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                    progressFill.style.width = percentage + '%';
                    progressFill.setAttribute('data-bbai-banner-progress-initialized', '1');
                }, 60);
            }
        }

        var progressRoot = banner.querySelector('.bbai-usage-banner__progress');
        if (progressRoot) {
            progressRoot.setAttribute('aria-valuenow', String(percentage));
        }

        var footerNode = banner.querySelector('[data-bbai-banner-footer]');
        if (footerNode) {
            footerNode.textContent = missingCount <= 0
                ? __('All images now have alt text', 'beepbeep-ai-alt-text-generator')
                : sprintf(
                    _n('%s image still needs alt text', '%s images still need alt text', missingCount, 'beepbeep-ai-alt-text-generator'),
                    missingCount.toLocaleString()
                );
        }

        updateDashboardPerformanceMetrics(null, usage);
        setSharedBannerCtaState(banner, outOfCredits, missingCount);
    }

    function getLibraryQualityMeta(altText) {
        var clean = String(altText || '').trim();
        if (!clean) {
            return {
                key: 'poor',
                label: __('Weak', 'beepbeep-ai-alt-text-generator'),
                score: 0,
                wordCount: 0,
                tier: 'missing',
                hardFail: true
            };
        }

        var quality = calculateSeoQuality(clean);
        var key = 'excellent';
        var label = __('Good', 'beepbeep-ai-alt-text-generator');
        var tier = 'good';

        if (quality.score >= 85) {
            key = 'excellent';
            label = __('Good', 'beepbeep-ai-alt-text-generator');
            tier = 'good';
        } else if (quality.score >= 70) {
            key = 'needs-review';
            label = __('Review', 'beepbeep-ai-alt-text-generator');
            tier = 'review';
        } else {
            key = 'poor';
            label = __('Weak', 'beepbeep-ai-alt-text-generator');
            tier = 'weak';
        }

        var words = clean.split(/\s+/).filter(function(word) {
            return word.length > 0;
        }).length;

        return {
            key: key,
            label: label,
            score: quality.score,
            wordCount: words,
            tier: tier,
            hardFail: !!quality.hardFail
        };
    }

    function getLibraryPreviewText(altText) {
        var clean = String(altText || '').trim();
        if (!clean) {
            return '';
        }
        return clean;
    }

    function getLibraryQualityTooltip(qualityClass, hasAlt) {
        if (!hasAlt || qualityClass === 'missing') {
            return __('No ALT text detected', 'beepbeep-ai-alt-text-generator');
        }

        if (qualityClass === 'excellent') {
            return __('ALT text is descriptive and SEO-friendly', 'beepbeep-ai-alt-text-generator');
        }

        if (qualityClass === 'good') {
            return __('ALT text is clear and descriptive', 'beepbeep-ai-alt-text-generator');
        }

        if (qualityClass === 'needs-review') {
            return __('ALT text could use more descriptive detail', 'beepbeep-ai-alt-text-generator');
        }

        return __('ALT text is too short or lacks descriptive detail', 'beepbeep-ai-alt-text-generator');
    }

    function applyLibraryStatusBadgeTooltip(statusCell, tooltipText) {
        if (!statusCell) {
            return;
        }

        statusCell.setAttribute('data-bbai-tooltip', tooltipText || '');
        statusCell.setAttribute('data-bbai-tooltip-position', 'top');
        statusCell.setAttribute('tabindex', '0');
    }

    function getLibraryStatusLabelFromState(state) {
        if (state === 'missing') {
            return __('Missing', 'beepbeep-ai-alt-text-generator');
        }
        if (state === 'weak') {
            return __('Needs review', 'beepbeep-ai-alt-text-generator');
        }
        return __('Optimized', 'beepbeep-ai-alt-text-generator');
    }

    function buildLibraryRenderOptionsFromMeta(meta, fallback) {
        var options = $.extend({}, fallback || {});
        if (!meta || typeof meta !== 'object') {
            return options;
        }

        if (typeof meta.score === 'number' && meta.score >= 0 && meta.score <= 100) {
            options.serverQualityScore = meta.score;
        }
        if (typeof meta.row_status === 'string' && meta.row_status) {
            options.libraryRowStatus = meta.row_status;
        }

        var analysis = meta.analysis && typeof meta.analysis === 'object' ? meta.analysis : {};
        if (typeof meta.user_approved === 'boolean') {
            options.approved = meta.user_approved;
        } else if (typeof analysis.user_approved === 'boolean') {
            options.approved = analysis.user_approved;
        }
        if (typeof meta.score_grade === 'string' && meta.score_grade) {
            options.qualityLabel = meta.score_grade;
        }
        if (typeof meta.score_status === 'string' && meta.score_status) {
            options.qualityStatus = meta.score_status;
        }
        if (Array.isArray(meta.score_issues) && meta.score_issues.length) {
            options.reviewSummary = String(meta.score_issues[0] || '');
        } else if (typeof meta.score_summary === 'string' && meta.score_summary) {
            options.reviewSummary = meta.score_summary;
        }
        if (typeof analysis.status === 'string' && analysis.status) {
            options.qualityStatus = analysis.status;
        }
        if (typeof analysis.grade === 'string' && analysis.grade) {
            options.qualityLabel = analysis.grade;
        }
        if (Array.isArray(analysis.issues) && analysis.issues.length) {
            options.reviewSummary = String(analysis.issues[0] || options.reviewSummary || '');
        }

        return options;
    }

    function getLibraryRegenerateButtonHtml(row) {
        if (!row) {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        var isMissing = String(row.getAttribute('data-alt-missing') || 'false') === 'true';
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        var creditsLocked = isOutOfCreditsFromUsage();
        var lockMode = getLockedCtaMode();
        var isSignupLock = lockMode === 'create_account';
        var regenTitle = creditsLocked
            ? (isSignupLock
                ? __('Free trial complete. Create a free account to continue generating ALT text.', 'beepbeep-ai-alt-text-generator')
                : __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator'))
            : isMissing
                ? __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator')
                : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator');
        var classNames = 'bbai-row-action-btn bbai-row-action-btn--primary';
        if (creditsLocked) {
            classNames += ' bbai-is-locked';
        }
        var attributes = [
            'type="button"',
            'class="' + classNames + '"',
            'data-action="regenerate-single"',
            'data-attachment-id="' + escapeHtml(String(attachmentId)) + '"',
            'data-bbai-lock-preserve-label="1"',
            'title="' + escapeHtml(regenTitle) + '"'
        ];

        if (creditsLocked) {
            attributes.push(buildLockedCtaAttributes('regenerate_single', 'library-row-regenerate').trim());
        }

        return '<button ' + attributes.join(' ') + '>' +
            escapeHtml(isMissing ? __('Generate', 'beepbeep-ai-alt-text-generator') : __('Regenerate', 'beepbeep-ai-alt-text-generator')) +
            '</button>';
    }

    function getLibraryEditButtonHtml(row) {
        var attachmentId = row ? parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10) : 0;
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        return (
            '<button type="button" class="bbai-row-action-btn" data-action="edit-alt-inline" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '" title="' +
            escapeHtml(__('Edit ALT text manually', 'beepbeep-ai-alt-text-generator')) +
            '">' +
            escapeHtml(__('Edit manually', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryApproveButtonHtml(row) {
        if (!row || getLibraryReviewState(row) !== 'weak') {
            return '';
        }
        if (String(row.getAttribute('data-alt-missing') || 'false') === 'true') {
            return '';
        }
        if (String(row.getAttribute('data-approved') || 'false') === 'true') {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        return (
            '<button type="button" class="bbai-row-action-btn bbai-row-action-btn--ghost" data-action="approve-alt-inline" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '">' +
            escapeHtml(__('Mark reviewed', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryImproveButtonHtml(row) {
        if (!row || getLibraryReviewState(row) !== 'weak') {
            return '';
        }
        if (String(row.getAttribute('data-alt-missing') || 'false') === 'true') {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        var creditsLocked = isOutOfCreditsFromUsage();
        var lockMode = getLockedCtaMode();
        var isSignupLock = lockMode === 'create_account';
        var improveTitle = creditsLocked
            ? (isSignupLock
                ? __('Free trial complete. Create a free account to continue improving ALT text.', 'beepbeep-ai-alt-text-generator')
                : __('Upgrade to unlock AI improvements', 'beepbeep-ai-alt-text-generator'))
            : __('Improve ALT with AI (uses one credit)', 'beepbeep-ai-alt-text-generator');
        var classNames = 'bbai-row-action-btn bbai-row-action-btn--ghost';
        if (creditsLocked) {
            classNames += ' bbai-is-locked';
        }
        var attributes = [
            'type="button"',
            'class="' + classNames + '"',
            'data-action="phase17-improve-alt"',
            'data-attachment-id="' + escapeHtml(String(attachmentId)) + '"',
            'title="' + escapeHtml(improveTitle) + '"'
        ];
        if (creditsLocked) {
            attributes.push(buildLockedCtaAttributes('regenerate_single', 'library-table-phase17-improve').trim());
        }

        return '<button ' + attributes.join(' ') + '>' + escapeHtml(__('Improve ALT', 'beepbeep-ai-alt-text-generator')) + '</button>';
    }

    function getLibraryCopyButtonHtml(row) {
        var attachmentId = row ? parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10) : 0;
        var altText = row ? String(row.getAttribute('data-alt-full') || '') : '';
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        var disabled = !altText.trim();

        return (
            '<button type="button" class="bbai-row-action-btn bbai-row-action-btn--ghost" data-action="copy-alt-text" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '" data-alt-text="' +
            escapeHtml(altText) +
            '"' +
            (disabled ? ' disabled aria-disabled="true"' : '') +
            '>' +
            escapeHtml(__('Copy ALT', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryWorkspaceRegenerateButtonHtml(row) {
        if (!row) {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        var isMissing = String(row.getAttribute('data-alt-missing') || 'false') === 'true';
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        var creditsLocked = isOutOfCreditsFromUsage();
        var lockMode = getLockedCtaMode();
        var isSignupLock = lockMode === 'create_account';
        var title = creditsLocked
            ? (isSignupLock
                ? __('Free trial complete. Create a free account to continue generating ALT text.', 'beepbeep-ai-alt-text-generator')
                : __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator'))
            : isMissing
                ? __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator')
                : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator');
        var buttonVariant = isMissing ? 'bbai-library-card__quick-action--soft-primary' : 'bbai-library-card__quick-action--primary';
        var classNames = 'bbai-library-card__quick-action ' + buttonVariant + ' bbai-row-actions__primary';
        if (creditsLocked) {
            classNames += ' bbai-is-locked';
        }

        var attributes = [
            'type="button"',
            'class="' + classNames + '"',
            'data-action="regenerate-single"',
            'data-attachment-id="' + escapeHtml(String(attachmentId)) + '"',
            'data-bbai-lock-preserve-label="1"',
            'title="' + escapeHtml(title) + '"'
        ];

        if (creditsLocked) {
            attributes.push(buildLockedCtaAttributes('regenerate_single', 'library-row-regenerate-quick').trim());
        }

        return '<button ' + attributes.join(' ') + '>' +
            escapeHtml(isMissing ? __('Generate', 'beepbeep-ai-alt-text-generator') : __('Regenerate', 'beepbeep-ai-alt-text-generator')) +
            '</button>';
    }

    function getLibraryWorkspaceEditButtonHtml(row) {
        var attachmentId = row ? parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10) : 0;
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        return (
            '<button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--secondary bbai-row-actions__secondary" data-action="edit-alt-inline" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '" title="' +
            escapeHtml(__('Edit ALT text manually', 'beepbeep-ai-alt-text-generator')) +
            '">' +
            escapeHtml(__('Edit manually', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryWorkspaceCopyButtonHtml(row) {
        var attachmentId = row ? parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10) : 0;
        var altText = row ? String(row.getAttribute('data-alt-full') || '') : '';
        if (!attachmentId || attachmentId <= 0 || !altText.trim()) {
            return '';
        }

        return (
            '<button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="copy-alt-text" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '" data-alt-text="' +
            escapeHtml(altText) +
            '">' +
            escapeHtml(__('Copy ALT', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryWorkspaceImproveButtonHtml(row) {
        if (!row || getLibraryReviewState(row) !== 'weak') {
            return '';
        }
        if (String(row.getAttribute('data-alt-missing') || 'false') === 'true') {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        var creditsLocked = isOutOfCreditsFromUsage();
        var lockMode = getLockedCtaMode();
        var isSignupLock = lockMode === 'create_account';
        var classNames = 'bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra';
        if (creditsLocked) {
            classNames += ' bbai-is-locked';
        }

        var attributes = [
            'type="button"',
            'class="' + classNames + '"',
            'data-action="phase17-improve-alt"',
            'data-attachment-id="' + escapeHtml(String(attachmentId)) + '"',
            'title="' +
                escapeHtml(
                    creditsLocked
                        ? (isSignupLock
                            ? __('Free trial complete. Create a free account to continue improving ALT text.', 'beepbeep-ai-alt-text-generator')
                            : __('Upgrade to unlock AI improvements', 'beepbeep-ai-alt-text-generator'))
                        : __('Improve ALT with AI (uses one credit)', 'beepbeep-ai-alt-text-generator')
                ) +
                '"'
        ];

        if (creditsLocked) {
            attributes.push(buildLockedCtaAttributes('regenerate_single', 'library-row-phase17-improve').trim());
        }

        return '<button ' + attributes.join(' ') + '>' + escapeHtml(__('Improve ALT', 'beepbeep-ai-alt-text-generator')) + '</button>';
    }

    function getLibraryWorkspaceApproveButtonHtml(row) {
        if (!row || getLibraryReviewState(row) !== 'weak') {
            return '';
        }
        if (String(row.getAttribute('data-alt-missing') || 'false') === 'true') {
            return '';
        }
        if (String(row.getAttribute('data-approved') || 'false') === 'true') {
            return '';
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            return '';
        }

        return (
            '<button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="approve-alt-inline" data-attachment-id="' +
            escapeHtml(String(attachmentId)) +
            '">' +
            escapeHtml(__('Mark reviewed', 'beepbeep-ai-alt-text-generator')) +
            '</button>'
        );
    }

    function getLibraryCardMetaHtml(row) {
        if (!row) {
            return '';
        }

        var fileName = String(row.getAttribute('data-file-name') || '').trim();
        var fileMeta = String(row.getAttribute('data-file-meta') || '').trim();
        var updated = String(row.getAttribute('data-last-updated') || '').trim();
        var metaParts = [];

        if (fileMeta) {
            metaParts.push(fileMeta);
        }
        if (updated) {
            metaParts.push(sprintf(__('Updated %s', 'beepbeep-ai-alt-text-generator'), updated));
        }

        return '<div class="bbai-library-card__meta-wrap">' +
            '<p class="bbai-library-card__filename" title="' + escapeHtml(fileName) + '">' + escapeHtml(fileName) + '</p>' +
            '<p class="bbai-library-card__meta">' + escapeHtml(metaParts.join(' • ')) + '</p>' +
            '</div>';
    }

    function setLibraryRowActionLoading(button, label) {
        if (!button) {
            return;
        }

        if (!button.getAttribute('data-bbai-original-html')) {
            button.setAttribute('data-bbai-original-html', button.innerHTML);
        }

        button.disabled = true;
        button.classList.add('is-loading');
        button.innerHTML =
            '<span class="bbai-row-action-spinner" aria-hidden="true"></span>' +
            '<span>' + escapeHtml(label || __('Working...', 'beepbeep-ai-alt-text-generator')) + '</span>';
    }

    function restoreLibraryRowActionLoading(button) {
        if (!button) {
            return;
        }

        var originalHtml = button.getAttribute('data-bbai-original-html');
        button.disabled = false;
        button.classList.remove('is-loading');

        if (originalHtml) {
            button.innerHTML = originalHtml;
            button.removeAttribute('data-bbai-original-html');
        }
    }

    function notifyLibraryFeedback(type, message) {
        if (!message) {
            return;
        }

        if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
            window.bbaiPushToast(type || 'info', message, { duration: 4000 });
            return;
        }

        if (window.bbaiToast && typeof window.bbaiToast[type] === 'function') {
            window.bbaiToast[type](message, { duration: 4000 });
            return;
        }

        if (typeof showNotification === 'function') {
            showNotification(message, type || 'info');
        }
    }

    function flashLibraryRowSuccess(row) {
        if (!row || !row.classList) {
            return;
        }

        row.classList.remove('bbai-library-row--regen-flash');
        void row.offsetWidth;
        row.classList.add('bbai-library-row--regen-flash');

        window.setTimeout(function() {
            row.classList.remove('bbai-library-row--regen-flash');
        }, 1600);
    }

    function getNextVisibleLibraryRow(row) {
        var candidate;

        if (!row) {
            return null;
        }

        candidate = row.nextElementSibling;
        while (candidate) {
            if (
                candidate.classList &&
                candidate.classList.contains('bbai-library-row') &&
                !candidate.classList.contains('bbai-library-row--hidden') &&
                candidate.getAttribute('data-bbai-filter-exit-in-flight') !== '1'
            ) {
                return candidate;
            }
            candidate = candidate.nextElementSibling;
        }

        candidate = row.previousElementSibling;
        while (candidate) {
            if (
                candidate.classList &&
                candidate.classList.contains('bbai-library-row') &&
                !candidate.classList.contains('bbai-library-row--hidden') &&
                candidate.getAttribute('data-bbai-filter-exit-in-flight') !== '1'
            ) {
                return candidate;
            }
            candidate = candidate.previousElementSibling;
        }

        return null;
    }

    function highlightLibraryRowMomentum(row) {
        if (!row || !row.classList) {
            return;
        }

        row.classList.remove('bbai-library-row--next-up');
        void row.offsetWidth;
        row.classList.add('bbai-library-row--next-up');

        window.setTimeout(function() {
            if (row) {
                row.classList.remove('bbai-library-row--next-up');
            }
        }, 1300);
    }

    function animateLibraryRowFilterExit(row, previousState) {
        var exitFilter = normalizeLibraryStatusFilter(previousState);
        var activeFilter = getLibraryActiveFilter();
        var nextRow = getNextVisibleLibraryRow(row);
        var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        var holdDuration = reduceMotion ? 80 : 260;
        var exitDuration = reduceMotion ? 0 : 320;

        if (!row || !row.parentNode || row.getAttribute('data-bbai-filter-exit-in-flight') === '1') {
            return;
        }

        row.setAttribute('data-bbai-filter-exit-in-flight', '1');
        row.classList.add('bbai-library-row--resolved');
        flashLibraryRowSuccess(row);

        window.setTimeout(function() {
            var height;

            if (!row || !row.parentNode) {
                return;
            }

            height = row.offsetHeight;
            row.style.maxHeight = height + 'px';
            row.classList.add('bbai-library-row--filter-exit');

            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    row.classList.add('bbai-library-row--filter-exit-active');
                });
            });

            window.setTimeout(function() {
                if (!row || !row.parentNode) {
                    return;
                }

                var parent = row.parentNode;
                parent.removeChild(row);

                updateLibrarySelectionState();
                applyLibraryReviewFilters();

                if (getLibraryActiveFilter() === activeFilter && activeFilter === exitFilter) {
                    highlightLibraryRowMomentum(nextRow);
                }
            }, exitDuration);
        }, holdDuration);
    }

    window.bbaiAnimateLibraryRowFilterExit = animateLibraryRowFilterExit;

    function applyRegenerateSuccessToRow(row, attachmentId, altText, payload, renderOptions) {
        if (!row || !altText) {
            return;
        }

        var nextOptions =
            renderOptions && typeof renderOptions === 'object'
                ? renderOptions
                : buildLibraryRenderOptionsFromMeta(payload && payload.meta ? payload.meta : null, {
                      approved: false
                  });

        renderLibraryAltCell(row, altText, nextOptions);
        updateLibraryLastUpdated(row, payload && payload.meta && payload.meta.generated ? payload.meta.generated : '');
        updateLibrarySelectionState();
        if (row.getAttribute('data-bbai-filter-exit-in-flight') !== '1') {
            flashLibraryRowSuccess(row);
        }

        if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
            var modalAttachmentId = parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10);
            if (modalAttachmentId === attachmentId) {
                updateLibraryPreviewModalContent(row);
            }
        }
    }

    function updateLibraryRowActions(row) {
        if (!row) {
            return;
        }

        var actionsRoot = row.querySelector('.bbai-library-actions');
        if (actionsRoot) {
            var mainHtml = getLibraryRegenerateButtonHtml(row) + getLibraryEditButtonHtml(row);
            var extras = [];
            if (String(row.getAttribute('data-alt-missing') || 'false') !== 'true') {
                extras.push(getLibraryCopyButtonHtml(row));
            }
            if (getLibraryReviewState(row) === 'weak' && String(row.getAttribute('data-alt-missing') || 'false') !== 'true') {
                extras.push(getLibraryImproveButtonHtml(row));
            }
            extras.push(getLibraryApproveButtonHtml(row));

            var extrasFiltered = extras.filter(Boolean);
            var extrasHtml =
                extrasFiltered.length > 0
                    ? '<div class="bbai-library-actions__extras" role="group" aria-label="' +
                      escapeHtml(__('Additional actions', 'beepbeep-ai-alt-text-generator')) +
                      '">' +
                      extrasFiltered.join('') +
                      '</div>'
                    : '';

            actionsRoot.innerHTML =
                '<div class="bbai-library-actions__main">' + mainHtml + '</div>' + extrasHtml;
            return;
        }

        var quickActionsRoot = row.querySelector('.bbai-library-card__quick-actions');
        if (!quickActionsRoot) {
            return;
        }

        quickActionsRoot.innerHTML =
            getLibraryWorkspaceRegenerateButtonHtml(row) +
            getLibraryWorkspaceEditButtonHtml(row);

        var extrasHost = row.querySelector('.bbai-library-card__extra-actions');
        var extraActionHtml = [
            getLibraryWorkspaceCopyButtonHtml(row),
            getLibraryWorkspaceImproveButtonHtml(row),
            getLibraryWorkspaceApproveButtonHtml(row)
        ].filter(Boolean);

        if (!extraActionHtml.length) {
            if (extrasHost) {
                extrasHost.remove();
            }
            return;
        }

        if (!extrasHost) {
            extrasHost = document.createElement('div');
            extrasHost.className = 'bbai-library-card__extra-actions';
            extrasHost.setAttribute('role', 'group');
            extrasHost.setAttribute('aria-label', __('Additional actions', 'beepbeep-ai-alt-text-generator'));
            quickActionsRoot.parentNode.appendChild(extrasHost);
        }

        extrasHost.innerHTML = extraActionHtml.join('');
    }

    function buildLibraryScoreBadgeHtml(row) {
        if (!row) {
            return '';
        }

        var missing = String(row.getAttribute('data-alt-missing') || 'false') === 'true';
        var tier = String(row.getAttribute('data-score-tier') || (missing ? 'missing' : 'weak'));
        var score = parseInt(row.getAttribute('data-quality-score') || '0', 10) || 0;
        var tip = escapeHtml(row.getAttribute('data-quality-tooltip') || '');

        if (missing || tier === 'missing') {
            return (
                '<span class="bbai-library-score-badge bbai-library-score-badge--missing" title="' +
                tip +
                '">' +
                '<span class="bbai-library-score-badge__value" aria-hidden="true">—</span>' +
                '<span class="bbai-library-score-badge__label">' +
                escapeHtml(__('No ALT', 'beepbeep-ai-alt-text-generator')) +
                '</span></span>'
            );
        }

        var label =
            __('Score', 'beepbeep-ai-alt-text-generator');

        return (
            '<span class="bbai-library-score-badge bbai-library-score-badge--' +
            escapeHtml(tier) +
            '" title="' +
            tip +
            '">' +
            '<span class="bbai-library-score-badge__value">' +
            escapeHtml(String(score)) +
            '</span>' +
            '<span class="bbai-library-score-badge__label">' +
            escapeHtml(label) +
            '</span></span>'
        );
    }

    function updateLibraryRowScoreHint(row) {
        if (!row) {
            return;
        }

        var main = row.querySelector('.bbai-library-card__main');
        if (!main) {
            return;
        }

        var metaCol = main.querySelector('.bbai-library-card__col--meta');
        var reviewBody = main.querySelector('.bbai-library-card__review-body');
        var existing = main.querySelector('.bbai-library-card__score-hint');
        var missing = String(row.getAttribute('data-alt-missing') || 'false') === 'true';
        var tier = String(row.getAttribute('data-score-tier') || '');
        var hint = '';

        if (missing) {
            hint = __('Add or generate ALT to score this image.', 'beepbeep-ai-alt-text-generator');
        } else if (tier === 'weak') {
            hint = __('Low score — regenerating often helps.', 'beepbeep-ai-alt-text-generator');
        } else if (tier === 'review') {
            hint = __('Consider a quick manual edit for stronger context.', 'beepbeep-ai-alt-text-generator');
        }

        if (!hint) {
            if (existing) {
                existing.remove();
            }
            return;
        }

        if (!existing) {
            existing = document.createElement('p');
            existing.className = 'bbai-library-card__score-hint';
            if (metaCol) {
                metaCol.appendChild(existing);
            } else if (reviewBody && reviewBody.parentNode === main) {
                main.insertBefore(existing, reviewBody);
            } else {
                main.appendChild(existing);
            }
        }

        existing.textContent = hint;
    }

    function updateLibraryStatusBadge(row, previousStatusOverride) {
        if (!row) {
            return;
        }

        var previousStatus = typeof previousStatusOverride === 'string'
            ? normalizeLibraryStatusFilter(previousStatusOverride)
            : String(row.getAttribute('data-status') || '');
        var state = getLibraryReviewState(row);
        var tagsRoot = row.querySelector('.bbai-library-status-tags');

        if (tagsRoot) {
            tagsRoot.innerHTML =
                '<span class="bbai-library-status-badge bbai-library-status-badge--' +
                escapeHtml(state) +
                '">' +
                escapeHtml(getLibraryStatusLabelFromState(state)) +
                '</span>' +
                buildLibraryScoreBadgeHtml(row);

            var statusCell = tagsRoot.querySelector('.bbai-library-status-badge');
            if (statusCell) {
                applyLibraryStatusBadgeTooltip(
                    statusCell,
                    row.getAttribute('data-quality-tooltip') || getLibraryQualityTooltip(state, state !== 'missing')
                );
            }
        } else {
            var statusCellLegacy = row.querySelector('.bbai-library-status-badge');
            if (!statusCellLegacy) {
                return;
            }

            statusCellLegacy.className = 'bbai-library-status-badge bbai-library-status-badge--' + state;
            statusCellLegacy.textContent = getLibraryStatusLabelFromState(state);
            applyLibraryStatusBadgeTooltip(
                statusCellLegacy,
                row.getAttribute('data-quality-tooltip') || getLibraryQualityTooltip(state, state !== 'missing')
            );
        }

        row.setAttribute('data-status', state);
        row.setAttribute('data-review-state', state);
        row.setAttribute('data-status-label', getLibraryStatusLabelFromState(state));
        row.setAttribute('data-state-rank', state === 'missing' ? '0' : state === 'weak' ? '1' : '2');
        updateSharedBannerMissingFromRowStatus(previousStatus, state);
    }

    function renderLibraryAltCell(row, altText, options) {
        if (!row) {
            return;
        }

        options = options && typeof options === 'object' ? options : {};
        var altCell = row.querySelector('.bbai-library-cell--alt-text');
        if (!altCell) {
            return;
        }

        var previousReviewState = getLibraryReviewState(row);
        var activeFilter = getLibraryActiveFilter();
        var clean = String(altText || '').trim();
        var approved = options.approved === true;
        var qualityMeta = getLibraryQualityMeta(clean);
        if (typeof options.serverQualityScore === 'number') {
            qualityMeta.score = options.serverQualityScore;
            qualityMeta.hardFail = options.serverQualityScore < 50;
            if (qualityMeta.score >= 85) {
                qualityMeta.key = 'excellent';
                qualityMeta.label = __('Good', 'beepbeep-ai-alt-text-generator');
                qualityMeta.tier = 'good';
            } else if (qualityMeta.score >= 70) {
                qualityMeta.key = 'needs-review';
                qualityMeta.label = __('Review', 'beepbeep-ai-alt-text-generator');
                qualityMeta.tier = 'review';
            } else {
                qualityMeta.key = 'poor';
                qualityMeta.label = __('Weak', 'beepbeep-ai-alt-text-generator');
                qualityMeta.tier = 'weak';
            }
        }
        if (typeof options.qualityLabel === 'string' && options.qualityLabel) {
            qualityMeta.label = options.qualityLabel;
        }
        var tooltipText = options.qualityTooltip || getLibraryQualityTooltip(qualityMeta.key, clean !== '');
        var reviewState = !clean
            ? 'missing'
            : typeof options.libraryRowStatus === 'string' && options.libraryRowStatus
              ? options.libraryRowStatus
              : approved
                ? 'optimized'
              : qualityMeta.score >= 85 && !qualityMeta.hardFail
                ? 'optimized'
                : 'weak';
        var shouldAnimateFilterExit =
            activeFilter !== 'all' &&
            activeFilter === previousReviewState &&
            reviewState !== previousReviewState;
        var reviewSummary = options.reviewSummary || '';

        if (!reviewSummary) {
            if (!clean) {
                reviewSummary = __('No ALT text yet. Add one inline or generate it with AI.', 'beepbeep-ai-alt-text-generator');
            } else if (approved) {
                reviewSummary = __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator');
            } else if (reviewState === 'weak') {
                reviewSummary = getLibraryQualityTooltip(qualityMeta.key, true);
            } else {
                reviewSummary = __('Ready to publish.', 'beepbeep-ai-alt-text-generator');
            }
        }

        row.setAttribute('data-alt-full', clean);
        row.setAttribute('data-approved', approved ? 'true' : 'false');
        row.setAttribute('data-quality-class', qualityMeta.key);
        row.setAttribute('data-quality', qualityMeta.key);
        row.setAttribute('data-quality-label', qualityMeta.label);
        row.setAttribute('data-quality-tooltip', tooltipText);
        row.setAttribute('data-quality-score', clean ? String(qualityMeta.score) : '0');
        row.setAttribute('data-score-tier', clean ? qualityMeta.tier : 'missing');
        row.setAttribute('data-alt-missing', clean ? 'false' : 'true');
        row.setAttribute('data-status', reviewState);
        row.setAttribute('data-review-state', reviewState);
        row.setAttribute('data-status-label', getLibraryStatusLabelFromState(reviewState));
        row.setAttribute('data-review-summary', reviewSummary);
        row.setAttribute('data-state-rank', reviewState === 'missing' ? '0' : (reviewState === 'weak' ? '1' : '2'));

        var attachmentId = String(row.getAttribute('data-attachment-id') || '');
        var previewId = 'bbai-alt-preview-' + attachmentId;
        var shouldCollapse = clean.length > 160;

        var previewInner =
            '<div class="bbai-library-alt-preview-card bbai-library-alt-preview-card--v2 bbai-library-alt-preview-card--queue' +
            (shouldCollapse ? ' bbai-library-alt-preview-card--collapsible' : '') +
            '" data-bbai-alt-preview-card data-action="edit-alt-inline" data-attachment-id="' +
            escapeHtml(attachmentId) +
            '">' +
            (clean
                ? '<p id="' +
                  escapeHtml(previewId) +
                  '" class="bbai-alt-text-preview" title="' +
                  escapeHtml(getLibraryPreviewText(clean)) +
                  '">' +
                  escapeHtml(getLibraryPreviewText(clean)) +
                  '</p>' +
                  (shouldCollapse
                      ? '<button type="button" class="bbai-library-alt-expand" data-action="toggle-alt-preview" aria-expanded="false" aria-controls="' +
                        escapeHtml(previewId) +
                        '">' +
                        escapeHtml(__('Show more', 'beepbeep-ai-alt-text-generator')) +
                        '</button>'
                      : '')
                : '<span class="bbai-alt-text-missing">' +
                  escapeHtml(
                      __('No ALT yet — click to add or generate.', 'beepbeep-ai-alt-text-generator')
                  ) +
                  '</span>') +
            '</div>';

        var slot = row.querySelector('[data-bbai-alt-slot]');
        if (slot) {
            slot.innerHTML = previewInner;
        } else {
            altCell.innerHTML = previewInner + getLibraryCardMetaHtml(row);
        }

        syncLibraryRowCopyButtons(row, clean);

        updateLibraryStatusBadge(row, previousReviewState);
        syncLibraryWorkspaceCountsForTransition(previousReviewState, reviewState);
        updateLibraryRowScoreHint(row);
        updateLibraryRowActions(row);
        syncSharedUsageBanner(null);
        renderLibraryWorkflowDecisionSurfaces();

        if (shouldAnimateFilterExit) {
            animateLibraryRowFilterExit(row, previousReviewState);
            return;
        }

        applyLibraryReviewFilters();
    }

    function toggleLibraryAltPreview(trigger) {
        if (!trigger) {
            return;
        }

        var previewCard = trigger.closest('[data-bbai-alt-preview-card]');
        if (!previewCard) {
            return;
        }

        var expanded = trigger.getAttribute('aria-expanded') === 'true';
        previewCard.classList.toggle('is-expanded', !expanded);
        trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        trigger.textContent = expanded
            ? __('Show more', 'beepbeep-ai-alt-text-generator')
            : __('Show less', 'beepbeep-ai-alt-text-generator');
    }

    function copyLibraryAltText(trigger) {
        if (!trigger) {
            return;
        }

        var text = String(trigger.getAttribute('data-alt-text') || '').trim();
        if (!text) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('No ALT text to copy yet.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        function onSuccess() {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('success', __('ALT text copied to clipboard.', 'beepbeep-ai-alt-text-generator'));
            }
        }

        function onFailure() {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', __('Unable to copy ALT text right now.', 'beepbeep-ai-alt-text-generator'));
            }
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(onSuccess).catch(onFailure);
            return;
        }

        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            if (document.execCommand('copy')) {
                onSuccess();
            } else {
                onFailure();
            }
        } catch (error) {
            onFailure();
        }

        document.body.removeChild(textarea);
    }

    function updateLibraryPreviewModalContent(row) {
        if (!bbaiLibraryPreviewModal || !row) {
            return;
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            return;
        }

        var imageTitle = row.getAttribute('data-image-title') || __('Image', 'beepbeep-ai-alt-text-generator');
        var imageUrl = row.getAttribute('data-image-url') || '';
        var fileName = row.getAttribute('data-file-name') || imageTitle;
        var fileMeta = row.getAttribute('data-file-meta') || '';
        var lastUpdated = row.getAttribute('data-last-updated') || __('Unknown', 'beepbeep-ai-alt-text-generator');
        var thumbnail = row.querySelector('.bbai-library-thumbnail');
        if (!imageUrl && thumbnail) {
            imageUrl = thumbnail.getAttribute('src') || '';
        }

        var altText = getLibraryAltTextFromRow(row);
        var qualityLabel = row.getAttribute('data-quality-label') || __('Poor', 'beepbeep-ai-alt-text-generator');
        var qualityClass = String(row.getAttribute('data-quality-class') || 'poor').toLowerCase();
        if (!/^(excellent|good|needs-review|poor)$/.test(qualityClass)) {
            qualityClass = 'poor';
        }
        if (!altText) {
            qualityLabel = __('Poor', 'beepbeep-ai-alt-text-generator');
            qualityClass = 'poor';
        }

        var statusKey = String(row.getAttribute('data-status') || getLibraryReviewState(row) || '').toLowerCase();
        if (statusKey === 'needs-review') {
            statusKey = 'weak';
        }
        if (!/^(optimized|weak|missing|pending|error)$/.test(statusKey)) {
            statusKey = altText ? 'optimized' : 'missing';
        }
        var statusLabel = __('Optimized', 'beepbeep-ai-alt-text-generator');
        if (statusKey === 'weak') {
            statusLabel = __('Needs review', 'beepbeep-ai-alt-text-generator');
        } else if (statusKey === 'missing') {
            statusLabel = __('Missing', 'beepbeep-ai-alt-text-generator');
        } else if (statusKey === 'pending') {
            statusLabel = __('Pending', 'beepbeep-ai-alt-text-generator');
        } else if (statusKey === 'error') {
            statusLabel = __('Error', 'beepbeep-ai-alt-text-generator');
        }

        var filenameLine = fileName;
        if (fileMeta) {
            filenameLine = fileName + ' • ' + fileMeta;
        }

        bbaiLibraryPreviewModal.setAttribute('data-attachment-id', String(attachmentId));
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__title').textContent = __('Image Preview', 'beepbeep-ai-alt-text-generator');
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__filename').textContent = filenameLine;
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__image').setAttribute('src', imageUrl);
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__image').setAttribute('alt', imageTitle);
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__alt').textContent = altText || __('No alt text yet.', 'beepbeep-ai-alt-text-generator');
        var qualityValueNode = bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__quality-value');
        if (qualityValueNode) {
            qualityValueNode.textContent = qualityLabel;
            qualityValueNode.className = 'bbai-library-preview-modal__quality-value bbai-library-preview-modal__quality-value--' + qualityClass;
        }
        var statusValueNode = bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__status-value');
        if (statusValueNode) {
            statusValueNode.textContent = statusLabel;
            statusValueNode.className = 'bbai-library-preview-modal__status-value bbai-library-status-badge bbai-library-status-badge--' + statusKey;
        }
        bbaiLibraryPreviewModal.querySelector('.bbai-library-preview-modal__updated-value').textContent = lastUpdated;

        var rowRegenButton = row.querySelector('[data-action="regenerate-single"]');
        var previewRegenButton = bbaiLibraryPreviewModal.querySelector('[data-action="regenerate-from-preview"]');
        if (previewRegenButton) {
            var rowRegenLocked = !!(rowRegenButton && (
                rowRegenButton.getAttribute('data-bbai-action') === 'open-upgrade' ||
                rowRegenButton.getAttribute('data-bbai-action') === 'open-signup' ||
                rowRegenButton.getAttribute('aria-disabled') === 'true' ||
                rowRegenButton.classList.contains('bbai-is-locked') ||
                rowRegenButton.classList.contains('bbai-link-sm--disabled')
            ));
            // Lock when credits exhausted (no row regenerate button in library table)
            if (isOutOfCreditsFromUsage()) {
                rowRegenLocked = true;
            }

            if (rowRegenLocked) {
                var previewLockMode = getLockedCtaMode();
                var previewSignupLock = previewLockMode === 'create_account';
                previewRegenButton.classList.add('bbai-library-preview-modal__action--locked');
                previewRegenButton.classList.add('bbai-is-locked');
                previewRegenButton.removeAttribute('disabled');
                previewRegenButton.setAttribute('data-bbai-action', getLockedCtaAction(previewLockMode));
                previewRegenButton.setAttribute('data-bbai-locked-cta', '1');
                previewRegenButton.setAttribute('data-bbai-lock-reason', rowRegenButton ? (rowRegenButton.getAttribute('data-bbai-lock-reason') || 'regenerate_single') : 'regenerate_single');
                previewRegenButton.setAttribute('data-bbai-locked-source', 'library-preview-regenerate');
                previewRegenButton.setAttribute('data-bbai-intended-action', 'regenerate-single');
                previewRegenButton.setAttribute('aria-disabled', 'true');
                if (previewSignupLock) {
                    previewRegenButton.setAttribute('data-auth-tab', 'register');
                    previewRegenButton.setAttribute('title', __('Free trial complete. Create a free account to continue regenerating ALT text.', 'beepbeep-ai-alt-text-generator'));
                    previewRegenButton.textContent = __('Create free account to continue', 'beepbeep-ai-alt-text-generator');
                } else {
                    previewRegenButton.removeAttribute('data-auth-tab');
                    previewRegenButton.setAttribute('title', __('Upgrade required to regenerate ALT text for this image.', 'beepbeep-ai-alt-text-generator'));
                    previewRegenButton.textContent = __('Regenerate ALT (Upgrade Required)', 'beepbeep-ai-alt-text-generator');
                }
            } else {
                previewRegenButton.classList.remove('bbai-library-preview-modal__action--locked');
                previewRegenButton.classList.remove('bbai-is-locked');
                previewRegenButton.removeAttribute('data-bbai-action');
                previewRegenButton.removeAttribute('data-bbai-locked-cta');
                previewRegenButton.removeAttribute('data-bbai-lock-reason');
                previewRegenButton.removeAttribute('data-bbai-locked-source');
                previewRegenButton.removeAttribute('data-bbai-intended-action');
                previewRegenButton.removeAttribute('aria-disabled');
                previewRegenButton.removeAttribute('disabled');
                previewRegenButton.removeAttribute('data-auth-tab');
                previewRegenButton.removeAttribute('title');
                previewRegenButton.textContent = __('Regenerate ALT', 'beepbeep-ai-alt-text-generator');
            }
        }
    }

    function ensureLibraryPreviewModal() {
        if (bbaiLibraryPreviewModal && document.body.contains(bbaiLibraryPreviewModal)) {
            return bbaiLibraryPreviewModal;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'bbai-library-preview-modal';
        wrapper.setAttribute('id', 'bbai-library-preview-modal');
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.innerHTML =
            '<div class="bbai-library-preview-modal__backdrop" data-action="close-library-preview"></div>' +
            '<div class="bbai-library-preview-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbai-library-preview-title">' +
            '<button type="button" class="bbai-library-preview-modal__close" data-action="close-library-preview" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">×</button>' +
            '<h3 id="bbai-library-preview-title" class="bbai-library-preview-modal__title">' + escapeHtml(__('Image Preview', 'beepbeep-ai-alt-text-generator')) + '</h3>' +
            '<p class="bbai-library-preview-modal__filename"></p>' +
            '<img class="bbai-library-preview-modal__image" src="" alt="">' +
            '<div class="bbai-library-preview-modal__meta">' +
            '<p class="bbai-library-preview-modal__meta-label">' + escapeHtml(__('ALT text:', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '<p class="bbai-library-preview-modal__alt"></p>' +
            '<p class="bbai-library-preview-modal__quality">' +
            '<span>' + escapeHtml(__('SEO Quality:', 'beepbeep-ai-alt-text-generator')) + '</span> ' +
            '<strong class="bbai-library-preview-modal__quality-value"></strong>' +
            '</p>' +
            '<p class="bbai-library-preview-modal__quality">' +
            '<span>' + escapeHtml(__('Status:', 'beepbeep-ai-alt-text-generator')) + '</span> ' +
            '<strong class="bbai-library-preview-modal__status-value"></strong>' +
            '</p>' +
            '<p class="bbai-library-preview-modal__quality">' +
            '<span>' + escapeHtml(__('Last updated:', 'beepbeep-ai-alt-text-generator')) + '</span> ' +
            '<strong class="bbai-library-preview-modal__updated-value"></strong>' +
            '</p>' +
            '</div>' +
            '<div class="bbai-library-preview-modal__actions">' +
            '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="edit-alt-from-preview">' + escapeHtml(__('Edit ALT', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="regenerate-from-preview">' + escapeHtml(__('Regenerate ALT', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '<button type="button" class="bbai-btn bbai-btn-ghost bbai-btn-sm" data-action="close-library-preview">' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(wrapper);
        bbaiLibraryPreviewModal = wrapper;
        return bbaiLibraryPreviewModal;
    }

    function openLibraryPreviewModal(row, trigger) {
        if (!row) {
            return;
        }

        var modal = ensureLibraryPreviewModal();
        bbaiLibraryPreviewModalState.lastTrigger = trigger || document.activeElement || null;
        updateLibraryPreviewModalContent(row);
        dispatchAnalyticsEvent('alt_library_item_opened', {
            source: 'library_preview',
            item_status: getLibraryAnalyticsStatus(row)
        });
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        if (document.body) {
            document.body.style.overflow = 'hidden';
            document.body.classList.add('bbai-modal-open');
        }

        window.setTimeout(function() {
            var closeButton = modal.querySelector('[data-action="close-library-preview"]');
            if (closeButton && typeof closeButton.focus === 'function') {
                closeButton.focus();
            }
        }, 10);
    }

    function closeLibraryPreviewModal(options) {
        if (!bbaiLibraryPreviewModal) {
            return;
        }

        var shouldRestoreFocus = !(options && options.restoreFocus === false);
        var restoreTarget = shouldRestoreFocus ? bbaiLibraryPreviewModalState.lastTrigger : null;
        if ((!restoreTarget || !document.contains(restoreTarget)) && document.activeElement && bbaiLibraryPreviewModal.contains(document.activeElement)) {
            restoreTarget = document.querySelector('.bbai-library-row [data-action="preview-image"]');
        }

        if (restoreTarget && typeof restoreTarget.focus === 'function' && document.contains(restoreTarget)) {
            restoreTarget.focus();
        } else if (document.activeElement && bbaiLibraryPreviewModal.contains(document.activeElement) && typeof document.activeElement.blur === 'function') {
            document.activeElement.blur();
        }

        bbaiLibraryPreviewModal.classList.remove('is-visible');
        bbaiLibraryPreviewModal.setAttribute('aria-hidden', 'true');
        clearBodyScrollLocks();
    }

    function ensureLibraryEditModal() {
        if (bbaiLibraryEditModal && document.body.contains(bbaiLibraryEditModal)) {
            return bbaiLibraryEditModal;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'bbai-library-edit-modal';
        wrapper.id = 'bbai-library-edit-modal';
        wrapper.setAttribute('aria-hidden', 'true');
        wrapper.innerHTML =
            '<div class="bbai-library-edit-modal__backdrop" data-action="close-alt-editor-modal"></div>' +
            '<div class="bbai-library-edit-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bbai-library-edit-modal-title" aria-describedby="bbai-library-edit-modal-subtitle">' +
                '<button type="button" class="bbai-library-edit-modal__close" data-action="close-alt-editor-modal" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">×</button>' +
                '<div class="bbai-library-edit-modal__layout">' +
                    '<aside class="bbai-library-edit-modal__media">' +
                        '<div class="bbai-library-edit-modal__preview-wrap">' +
                            '<img class="bbai-library-edit-modal__preview" src="" alt="" hidden>' +
                            '<div class="bbai-library-edit-modal__preview-fallback" data-bbai-edit-preview-fallback>' +
                                '<svg width="28" height="28" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>' +
                            '</div>' +
                        '</div>' +
                        '<div class="bbai-library-edit-modal__meta">' +
                            '<div class="bbai-library-edit-modal__meta-row"><span class="bbai-library-edit-modal__meta-label">' + escapeHtml(__('Filename', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-library-edit-modal__meta-value" data-bbai-edit-file-name></span></div>' +
                            '<div class="bbai-library-edit-modal__meta-row"><span class="bbai-library-edit-modal__meta-label">' + escapeHtml(__('File details', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-library-edit-modal__meta-value" data-bbai-edit-file-meta></span></div>' +
                            '<div class="bbai-library-edit-modal__meta-row"><span class="bbai-library-edit-modal__meta-label">' + escapeHtml(__('Updated', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-library-edit-modal__meta-value" data-bbai-edit-updated></span></div>' +
                        '</div>' +
                    '</aside>' +
                    '<section class="bbai-library-edit-modal__panel">' +
                        '<header class="bbai-library-edit-modal__header">' +
                            '<h3 id="bbai-library-edit-modal-title" class="bbai-library-edit-modal__title">' + escapeHtml(__('Edit ALT text', 'beepbeep-ai-alt-text-generator')) + '</h3>' +
                            '<p id="bbai-library-edit-modal-subtitle" class="bbai-library-edit-modal__subtitle">' + escapeHtml(__('Review and improve the AI-generated description', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                        '</header>' +
                        '<div class="bbai-library-edit-modal__stack">' +
                            '<div class="bbai-library-edit-modal__label"><span>' + escapeHtml(__('Suggested ALT text', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-library-edit-modal__label-badge">' + escapeHtml(__('AI', 'beepbeep-ai-alt-text-generator')) + '</span></div>' +
                            '<div class="bbai-library-edit-modal__suggestion" data-bbai-edit-suggestion></div>' +
                        '</div>' +
                        '<div class="bbai-library-edit-modal__stack">' +
                            '<label class="bbai-library-edit-modal__label" for="bbai-library-edit-modal-textarea">' + escapeHtml(__('Final ALT text', 'beepbeep-ai-alt-text-generator')) + '</label>' +
                            '<textarea id="bbai-library-edit-modal-textarea" class="bbai-library-edit-modal__textarea bbai-textarea" rows="6"></textarea>' +
                        '</div>' +
                        '<div class="bbai-library-edit-modal__toolbar">' +
                            '<div class="bbai-library-edit-modal__ai-actions">' +
                                '<button type="button" class="bbai-library-edit-modal__ai-btn" data-action="regenerate-alt-editor-text">' + escapeHtml(__('Regenerate', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                                '<button type="button" class="bbai-library-edit-modal__ai-btn" data-action="shorten-alt-editor-text">' + escapeHtml(__('Make shorter', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                                '<button type="button" class="bbai-library-edit-modal__ai-btn" data-action="describe-alt-editor-text">' + escapeHtml(__('Make more descriptive', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                            '</div>' +
                            '<div class="bbai-library-edit-modal__quality">' +
                                '<span class="bbai-library-edit-modal__quality-label">' + escapeHtml(__('ALT quality:', 'beepbeep-ai-alt-text-generator')) + '</span>' +
                                '<span class="bbai-library-edit-modal__quality-value" data-bbai-edit-quality-value></span>' +
                                '<span class="bbai-library-edit-modal__quality-copy" data-bbai-edit-quality-copy></span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="bbai-library-edit-modal__automation">' +
                            '<div class="bbai-library-edit-modal__automation-copy">' +
                                '<p class="bbai-library-edit-modal__automation-title">' + escapeHtml(__('Apply this style to future images', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                                '<p class="bbai-library-edit-modal__automation-text">' + escapeHtml(__('Reduce repeat cleanup by turning on automatic optimisation for new uploads.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                            '</div>' +
                            '<div data-bbai-edit-automation-cta></div>' +
                        '</div>' +
                        '<div class="bbai-library-edit-modal__footer">' +
                            '<div>' +
                                '<p class="bbai-library-edit-modal__hint">' + escapeHtml(__('Tip: press Cmd/Ctrl + Enter to save.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                                '<p class="bbai-library-edit-modal__error" data-bbai-edit-error aria-live="polite"></p>' +
                            '</div>' +
                            '<div class="bbai-library-edit-modal__actions">' +
                                '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="close-alt-editor-modal">' + escapeHtml(__('Cancel', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                                '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="save-alt-editor-modal">' + escapeHtml(__('Save ALT text', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                            '</div>' +
                        '</div>' +
                    '</section>' +
                '</div>' +
            '</div>';

        document.body.appendChild(wrapper);
        bbaiLibraryEditModal = wrapper;
        return bbaiLibraryEditModal;
    }

    function updateLibraryEditModalQuality(text) {
        if (!bbaiLibraryEditModal) {
            return;
        }

        var quality = getLibraryModalQualityState(text);
        var valueNode = bbaiLibraryEditModal.querySelector('[data-bbai-edit-quality-value]');
        var copyNode = bbaiLibraryEditModal.querySelector('[data-bbai-edit-quality-copy]');
        if (valueNode) {
            valueNode.textContent = quality.label;
            valueNode.className = 'bbai-library-edit-modal__quality-value bbai-library-edit-modal__quality-value--' + quality.key;
        }
        if (copyNode) {
            copyNode.textContent = quality.tooltip;
        }
    }

    function setLibraryEditModalError(message) {
        if (!bbaiLibraryEditModal) {
            return;
        }
        var errorNode = bbaiLibraryEditModal.querySelector('[data-bbai-edit-error]');
        if (errorNode) {
            errorNode.textContent = message || '';
        }
    }

    function setLibraryEditModalBusy(button, isBusy, loadingLabel) {
        if (!button) {
            return;
        }

        if (isBusy) {
            if (!button.getAttribute('data-bbai-original-html')) {
                button.setAttribute('data-bbai-original-html', button.innerHTML);
            }
            button.disabled = true;
            button.classList.add('is-loading');
            button.innerHTML = '<span class="bbai-row-action-spinner" aria-hidden="true"></span><span>' + escapeHtml(loadingLabel || __('Working...', 'beepbeep-ai-alt-text-generator')) + '</span>';
            return;
        }

        button.disabled = false;
        button.classList.remove('is-loading');
        var originalHtml = button.getAttribute('data-bbai-original-html');
        if (originalHtml) {
            button.innerHTML = originalHtml;
            button.removeAttribute('data-bbai-original-html');
        }
    }

    function syncLibraryEditModalDraft(text, options) {
        if (!bbaiLibraryEditModal) {
            return;
        }

        options = options && typeof options === 'object' ? options : {};
        var textarea = bbaiLibraryEditModal.querySelector('.bbai-library-edit-modal__textarea');
        var suggestionNode = bbaiLibraryEditModal.querySelector('[data-bbai-edit-suggestion]');
        var nextText = String(text || '');

        if (typeof options.suggestion === 'string') {
            bbaiLibraryEditModalState.currentSuggestion = options.suggestion;
        }

        if (suggestionNode) {
            var suggestionText = bbaiLibraryEditModalState.currentSuggestion || '';
            suggestionNode.textContent = suggestionText || __('No AI suggestion yet. Regenerate to create one instantly.', 'beepbeep-ai-alt-text-generator');
            suggestionNode.classList.toggle('bbai-library-edit-modal__suggestion--empty', !suggestionText);
        }

        if (textarea && (options.replaceDraft || document.activeElement !== textarea)) {
            textarea.value = nextText;
        }

        updateLibraryEditModalQuality(textarea ? textarea.value : nextText);
    }

    function updateLibraryEditModalContent(row, options) {
        if (!row) {
            return;
        }

        var modal = ensureLibraryEditModal();
        var imageTitle = row.getAttribute('data-image-title') || __('Image', 'beepbeep-ai-alt-text-generator');
        var imageUrl = row.getAttribute('data-image-url') || '';
        var fileName = row.getAttribute('data-file-name') || imageTitle;
        var fileMeta = row.getAttribute('data-file-meta') || __('Unknown file details', 'beepbeep-ai-alt-text-generator');
        var updated = row.getAttribute('data-last-updated') || __('Unknown', 'beepbeep-ai-alt-text-generator');
        var altText = getLibraryAltTextFromRow(row);
        var previewImage = modal.querySelector('.bbai-library-edit-modal__preview');
        var previewFallback = modal.querySelector('[data-bbai-edit-preview-fallback]');
        var automationCtaHost = modal.querySelector('[data-bbai-edit-automation-cta]');
        var effectiveSuggestion = options && typeof options.suggestion === 'string'
            ? options.suggestion
            : (bbaiLibraryEditModalState.currentSuggestion || altText);

        bbaiLibraryEditModalState.row = row;
        bbaiLibraryEditModalState.attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10) || 0;
        bbaiLibraryEditModalState.originalSuggestion = altText;
        bbaiLibraryEditModalState.currentSuggestion = effectiveSuggestion;

        modal.setAttribute('data-attachment-id', String(bbaiLibraryEditModalState.attachmentId));
        modal.querySelector('[data-bbai-edit-file-name]').textContent = fileName;
        modal.querySelector('[data-bbai-edit-file-meta]').textContent = fileMeta;
        modal.querySelector('[data-bbai-edit-updated]').textContent = updated;

        if (previewImage) {
            if (imageUrl) {
                previewImage.hidden = false;
                previewImage.setAttribute('src', imageUrl);
                previewImage.setAttribute('alt', imageTitle);
                if (previewFallback) {
                    previewFallback.hidden = true;
                }
            } else {
                previewImage.hidden = true;
                previewImage.setAttribute('src', '');
                previewImage.setAttribute('alt', '');
                if (previewFallback) {
                    previewFallback.hidden = false;
                }
            }
        }

        if (automationCtaHost) {
            if (isLibraryProPlan()) {
                automationCtaHost.innerHTML = '<a href="' + escapeHtml(getLibraryAutomationSettingsUrl()) + '" class="bbai-btn bbai-btn-secondary bbai-btn-sm">' + escapeHtml(__('Open automation settings', 'beepbeep-ai-alt-text-generator')) + '</a>';
            } else if (getQuotaState().isAnonymousTrial) {
                automationCtaHost.innerHTML = '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="show-auth-modal" data-auth-tab="register">' + escapeHtml(__('Create free account', 'beepbeep-ai-alt-text-generator')) + '</button>';
            } else {
                automationCtaHost.innerHTML = '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-edit-modal-automation">' + escapeHtml(__('Enable automatic optimisation', 'beepbeep-ai-alt-text-generator')) + '</button>';
            }
        }

        syncLibraryEditModalDraft(altText || effectiveSuggestion, {
            suggestion: effectiveSuggestion,
            replaceDraft: !(options && options.preserveDraft)
        });
        setLibraryEditModalError('');
    }

    function openLibraryEditModal(row, trigger, source) {
        if (!row) {
            return;
        }

        if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
            closeLibraryPreviewModal({ restoreFocus: false });
        }

        var modal = ensureLibraryEditModal();
        bbaiLibraryEditModalState.lastTrigger = trigger || document.activeElement || null;
        updateLibraryEditModalContent(row, { replaceDraft: true });
        dispatchAnalyticsEvent('alt_library_edit_started', {
            source: source || 'library_modal',
            item_status: getLibraryAnalyticsStatus(row)
        });
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        if (document.body) {
            document.body.style.overflow = 'hidden';
            document.body.classList.add('bbai-modal-open');
        }

        window.setTimeout(function() {
            var textarea = modal.querySelector('.bbai-library-edit-modal__textarea');
            if (textarea && typeof textarea.focus === 'function') {
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        }, 10);
    }

    function closeLibraryEditModal(options) {
        if (!bbaiLibraryEditModal) {
            return;
        }

        var shouldRestoreFocus = !(options && options.restoreFocus === false);
        var restoreTarget = shouldRestoreFocus ? bbaiLibraryEditModalState.lastTrigger : null;
        if (restoreTarget && typeof restoreTarget.focus === 'function' && document.contains(restoreTarget)) {
            restoreTarget.focus();
        }

        bbaiLibraryEditModal.classList.remove('is-visible');
        bbaiLibraryEditModal.setAttribute('aria-hidden', 'true');
        bbaiLibraryEditModalState.row = null;
        bbaiLibraryEditModalState.attachmentId = 0;
        bbaiLibraryEditModalState.isBusy = false;
        clearBodyScrollLocks();
    }

    function requestLibraryPreviewAltSuggestion(attachmentId) {
        return new Promise(function(resolve, reject) {
            var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) ||
                (window.bbai_env && window.bbai_env.ajax_url) || '';
            var nonce = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce || '';

            if (!ajaxUrl || !nonce) {
                reject(new Error(__('Unable to generate a new suggestion right now.', 'beepbeep-ai-alt-text-generator')));
                return;
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'bbai_generate_preview_alt',
                    nonce: nonce,
                    attachment_ids: [attachmentId]
                }
            })
                .done(function(response) {
                    var payload = getNormalizedResponsePayload(response);
                    var previews = payload && Array.isArray(payload.previews) ? payload.previews : [];
                    if (!previews.length || !previews[0] || !previews[0].alt_text) {
                        reject(new Error(__('No new ALT suggestion was returned.', 'beepbeep-ai-alt-text-generator')));
                        return;
                    }
                    resolve(String(previews[0].alt_text || '').trim());
                })
                .fail(function(xhr) {
                    var message = __('Unable to generate a new suggestion right now.', 'beepbeep-ai-alt-text-generator');
                    if (xhr && xhr.responseJSON) {
                        if (xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            message = xhr.responseJSON.data.message;
                        }
                    }
                    reject(new Error(message));
                });
        });
    }

    function runLibraryEditAIPrompt(action, trigger) {
        if (!bbaiLibraryEditModal || bbaiLibraryEditModalState.isBusy) {
            return;
        }

        var row = bbaiLibraryEditModalState.row;
        var textarea = bbaiLibraryEditModal.querySelector('.bbai-library-edit-modal__textarea');
        if (!row || !textarea) {
            return;
        }

        var current = String(textarea.value || '').trim();
        var attachmentId = bbaiLibraryEditModalState.attachmentId;
        setLibraryEditModalError('');
        bbaiLibraryEditModalState.isBusy = true;
        setLibraryEditModalBusy(trigger, true, __('Working...', 'beepbeep-ai-alt-text-generator'));

        var finalize = function(nextText, nextSuggestion) {
            var resolvedText = String(nextText || '').trim();
            syncLibraryEditModalDraft(resolvedText, {
                suggestion: typeof nextSuggestion === 'string' ? nextSuggestion : bbaiLibraryEditModalState.currentSuggestion,
                replaceDraft: true
            });
            bbaiLibraryEditModalState.isBusy = false;
            setLibraryEditModalBusy(trigger, false);
        };

        if (action === 'regenerate') {
            requestLibraryPreviewAltSuggestion(attachmentId)
                .then(function(nextSuggestion) {
                    finalize(nextSuggestion, nextSuggestion);
                })
                .catch(function(error) {
                    bbaiLibraryEditModalState.isBusy = false;
                    setLibraryEditModalBusy(trigger, false);
                    setLibraryEditModalError(error && error.message ? error.message : __('Unable to generate a new suggestion right now.', 'beepbeep-ai-alt-text-generator'));
                });
            return;
        }

        window.setTimeout(function() {
            var nextText = current;
            if (action === 'shorter') {
                nextText = makeAltTextShorter(current);
            } else if (action === 'descriptive') {
                nextText = makeAltTextMoreDescriptive(current, row);
            }

            if (!nextText || nextText === current) {
                bbaiLibraryEditModalState.isBusy = false;
                setLibraryEditModalBusy(trigger, false);
                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast('info', __('That ALT text is already close to the requested style.', 'beepbeep-ai-alt-text-generator'));
                }
                return;
            }

            finalize(nextText);
        }, 260);
    }

    function startLibraryInlineEdit(row, source) {
        if (!row) {
            return;
        }

        if (bbaiLibraryEditModal && bbaiLibraryEditModal.classList.contains('is-visible')) {
            closeLibraryEditModal({ restoreFocus: false });
        }

        var slot = row.querySelector('[data-bbai-alt-slot]');
        if (!slot) {
            openLibraryEditModal(row, null, source || 'library_modal');
            return;
        }

        if (row.classList.contains('bbai-library-row--editing')) {
            return;
        }

        finishOtherLibraryInlineEdits(row);

        var altCell = row.querySelector('.bbai-library-cell--alt-text');
        if (!altCell) {
            return;
        }

        row._bbaiAltSlotBackup = slot.innerHTML;
        var current = getLibraryAltTextFromRow(row);
        dispatchAnalyticsEvent('alt_library_edit_started', {
            source: source || 'library_inline',
            item_status: getLibraryAnalyticsStatus(row)
        });

        row.classList.add('bbai-library-row--editing');
        altCell.classList.add('bbai-library-cell--editing');

        slot.innerHTML =
            '<div class="bbai-library-inline-alt" data-bbai-inline-alt-root="1">' +
            '<textarea class="bbai-library-inline-alt__textarea bbai-textarea" rows="3" maxlength="5000" aria-label="' +
            escapeLibraryAttr(__('ALT text', 'beepbeep-ai-alt-text-generator')) +
            '"></textarea>' +
            '<p class="bbai-library-inline-alt__error" role="alert"></p>' +
            '<div class="bbai-library-inline-alt__toolbar">' +
            '<button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="save-alt-inline">' +
            escapeHtml(__('Save', 'beepbeep-ai-alt-text-generator')) +
            '</button>' +
            '<button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="cancel-alt-inline">' +
            escapeHtml(__('Cancel', 'beepbeep-ai-alt-text-generator')) +
            '</button>' +
            '<span class="bbai-library-inline-alt__saved" data-bbai-inline-saved aria-live="polite">✓ ' +
            escapeHtml(__('Saved', 'beepbeep-ai-alt-text-generator')) +
            '</span>' +
            '</div></div>';

        var ta = slot.querySelector('.bbai-library-inline-alt__textarea');
        if (ta) {
            ta.value = current;
            ta.addEventListener('blur', onLibraryInlineAltBlur);
            ta.addEventListener('keydown', onLibraryInlineAltKeydown);
            window.setTimeout(function() {
                try {
                    ta.focus();
                    ta.setSelectionRange(ta.value.length, ta.value.length);
                } catch (focusErr) {
                    /* ignore */
                }
            }, 0);
        }
    }

    function cancelLibraryInlineEdit(row) {
        if (!row) {
            return;
        }

        var ta = row.querySelector('.bbai-library-inline-alt__textarea');
        if (ta) {
            ta.removeEventListener('blur', onLibraryInlineAltBlur);
            ta.removeEventListener('keydown', onLibraryInlineAltKeydown);
        }

        var slot = row.querySelector('[data-bbai-alt-slot]');
        if (slot && row._bbaiAltSlotBackup) {
            slot.innerHTML = row._bbaiAltSlotBackup;
            row._bbaiAltSlotBackup = null;
        } else {
            var altCellLegacy = row.querySelector('.bbai-library-cell--alt-text');
            if (altCellLegacy && altCellLegacy.classList.contains('bbai-library-cell--editing')) {
                var originalHtml = altCellLegacy.getAttribute('data-original-html');
                if (originalHtml) {
                    altCellLegacy.innerHTML = originalHtml;
                }
                altCellLegacy.classList.remove('bbai-library-cell--editing');
                altCellLegacy.removeAttribute('data-original-html');
            }
        }

        row.classList.remove('bbai-library-row--editing');
        var altCell = row.querySelector('.bbai-library-cell--alt-text');
        if (altCell) {
            altCell.classList.remove('bbai-library-cell--editing');
        }
        row.removeAttribute('data-original-alt');
    }

    function setInlineEditError(row, message) {
        if (!row) {
            return;
        }
        var errorNode =
            row.querySelector('.bbai-library-inline-alt__error') || row.querySelector('.bbai-library-inline-edit__error');
        if (errorNode) {
            errorNode.textContent = message || '';
        }
    }

    function syncLibraryRowCopyButtons(row, altText) {
        if (!row) {
            return;
        }
        var clean = String(altText || '').trim();
        var nodes = row.querySelectorAll('[data-action="copy-alt-text"]');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].setAttribute('data-alt-text', clean);
            nodes[i].disabled = !clean;
            if (!clean) {
                nodes[i].setAttribute('aria-disabled', 'true');
            } else {
                nodes[i].removeAttribute('aria-disabled');
            }
        }
    }

    function onLibraryInlineAltBlur(ev) {
        var ta = ev.target;
        if (!ta || !ta.classList || !ta.classList.contains('bbai-library-inline-alt__textarea')) {
            return;
        }
        var root = ta.closest('.bbai-library-inline-alt');
        var row = ta.closest('.bbai-library-row');
        if (!row || !row.classList.contains('bbai-library-row--editing')) {
            return;
        }
        window.setTimeout(function() {
            if (!row.classList.contains('bbai-library-row--editing')) {
                return;
            }
            var ae = document.activeElement;
            if (ae && root && root.contains(ae)) {
                return;
            }
            if (ae && ae.getAttribute && ae.getAttribute('data-action') === 'save-alt-inline') {
                return;
            }
            if (ae && ae.getAttribute && ae.getAttribute('data-action') === 'cancel-alt-inline') {
                return;
            }
            attemptLibraryInlineBlurSave(row);
        }, 180);
    }

    function onLibraryInlineAltKeydown(ev) {
        if (!ev.target || !ev.target.classList || !ev.target.classList.contains('bbai-library-inline-alt__textarea')) {
            return;
        }
        if (ev.key === 'Escape') {
            ev.preventDefault();
            var rowEsc = ev.target.closest('.bbai-library-row');
            cancelLibraryInlineEdit(rowEsc);
            return;
        }
        if ((ev.metaKey || ev.ctrlKey) && ev.key === 'Enter') {
            ev.preventDefault();
            var rowEnter = ev.target.closest('.bbai-library-row');
            var saveBtn = rowEnter ? rowEnter.querySelector('[data-action="save-alt-inline"]') : null;
            if (saveBtn) {
                saveLibraryInlineEdit(saveBtn);
            }
        }
    }

    function attemptLibraryInlineBlurSave(row) {
        if (!row) {
            return;
        }
        var ta = row.querySelector('.bbai-library-inline-alt__textarea');
        if (!ta || ta.disabled) {
            return;
        }
        var orig = String(row.getAttribute('data-alt-full') || '').trim();
        var val = String(ta.value || '').trim();
        if (val === orig) {
            cancelLibraryInlineEdit(row);
            return;
        }
        if (!val) {
            setInlineEditError(row, __('ALT text cannot be empty.', 'beepbeep-ai-alt-text-generator'));
            try {
                ta.focus();
            } catch (focusErr) {
                /* ignore */
            }
            return;
        }
        var fakeBtn = row.querySelector('[data-action="save-alt-inline"]');
        if (fakeBtn) {
            saveLibraryInlineEdit(fakeBtn);
        }
    }

    function finishOtherLibraryInlineEdits(exceptRow) {
        var editing = document.querySelectorAll('.bbai-library-row--editing');
        for (var i = 0; i < editing.length; i++) {
            if (exceptRow && editing[i] === exceptRow) {
                continue;
            }
            cancelLibraryInlineEdit(editing[i]);
        }
    }

    function saveLibraryInlineEdit(trigger) {
        var row =
            (trigger && trigger.closest && trigger.closest('.bbai-library-row')) ||
            bbaiLibraryEditModalState.row ||
            getLibraryRowFromTrigger(trigger);
        if (!row) {
            return;
        }

        var inlineTa = row.querySelector('.bbai-library-inline-alt__textarea');
        var modalTa = bbaiLibraryEditModal ? bbaiLibraryEditModal.querySelector('.bbai-library-edit-modal__textarea') : null;
        var textarea = inlineTa || modalTa;
        if (!textarea) {
            return;
        }

        var nextAlt = String(textarea.value || '').trim();
        if (!nextAlt) {
            if (inlineTa) {
                setInlineEditError(row, __('ALT text cannot be empty.', 'beepbeep-ai-alt-text-generator'));
            } else {
                setLibraryEditModalError(__('ALT text cannot be empty.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        if (!attachmentId || attachmentId <= 0) {
            if (inlineTa) {
                setInlineEditError(row, __('Unable to find this media item.', 'beepbeep-ai-alt-text-generator'));
            } else {
                setLibraryEditModalError(__('Unable to find this media item.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var endpoint = getLibraryAltEndpoint(attachmentId);
        var nonce = getLibraryRestNonce();
        if (!endpoint || !nonce) {
            if (inlineTa) {
                setInlineEditError(row, __('Unable to save ALT text right now.', 'beepbeep-ai-alt-text-generator'));
            } else {
                setLibraryEditModalError(__('Unable to save ALT text right now.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var isInline = !!inlineTa;
        var saveButton = isInline
            ? row.querySelector('[data-action="save-alt-inline"]')
            : bbaiLibraryEditModal.querySelector('[data-action="save-alt-editor-modal"]');
        var cancelButton = isInline ? row.querySelector('[data-action="cancel-alt-inline"]') : bbaiLibraryEditModal.querySelector('[data-action="close-alt-editor-modal"]');

        if (!isInline) {
            setLibraryEditModalBusy(saveButton, true, __('Saving...', 'beepbeep-ai-alt-text-generator'));
            if (cancelButton) {
                cancelButton.disabled = true;
            }
            setLibraryEditModalError('');
        } else {
            setInlineEditError(row, '');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.setAttribute('aria-busy', 'true');
            }
            if (cancelButton) {
                cancelButton.disabled = true;
            }
            inlineTa.disabled = true;
        }

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: JSON.stringify({ alt: nextAlt }),
            processData: false
        })
            .done(function(response) {
                var savedAlt = response && typeof response.alt === 'string' ? response.alt : nextAlt;
                var statsPayload = response && response.stats && typeof response.stats === 'object' ? response.stats : null;
                var renderOptions = buildLibraryRenderOptionsFromMeta(response && response.meta ? response.meta : null, {
                    approved: !!(response && response.approved),
                    reviewSummary:
                        response && typeof response.approved_at === 'string' && response.approved
                            ? __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                            : ''
                });
                try {
                    row._bbaiAltSlotBackup = null;
                    row.classList.remove('bbai-library-row--editing');
                    var altCellDone = row.querySelector('.bbai-library-cell--alt-text');
                    if (altCellDone) {
                        altCellDone.classList.remove('bbai-library-cell--editing');
                    }

                    renderLibraryAltCell(row, savedAlt, renderOptions);
                    updateLibraryLastUpdated(row, response && response.meta && response.meta.generated ? response.meta.generated : '');

                    if (statsPayload) {
                        updateAltCoverageCard(statsPayload);
                        updateLibraryReviewFilterCounts(statsPayload);
                        dispatchDashboardStatsUpdated(statsPayload);
                    }
                    updateLibrarySelectionState();
                    dispatchAnalyticsEvent('alt_library_edit_saved', {
                        source: isInline ? 'library_inline' : 'library_modal',
                        accepted_count: 1,
                        item_status: getLibraryAnalyticsStatus(row)
                    });
                    closeLibraryEditModal();

                    if (isInline && row.getAttribute('data-bbai-filter-exit-in-flight') !== '1') {
                        row.classList.add('bbai-library-row--saved-flash');
                        window.setTimeout(function() {
                            row.classList.remove('bbai-library-row--saved-flash');
                        }, 900);
                    } else if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                        window.bbaiPushToast('success', __('ALT text updated successfully.', 'beepbeep-ai-alt-text-generator'));
                    }

                    if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
                        updateLibraryPreviewModalContent(row);
                    }
                } catch (err) {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Save success handler error:', err);
                    renderLibraryAltCell(row, savedAlt, renderOptions);
                }
            })
            .fail(function(xhr) {
                var message = __('Unable to save ALT text right now.', 'beepbeep-ai-alt-text-generator');
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                }
                if (isInline) {
                    setInlineEditError(row, message);
                    if (inlineTa) {
                        inlineTa.disabled = false;
                    }
                } else {
                    setLibraryEditModalError(message);
                    setLibraryEditModalBusy(saveButton, false);
                    if (cancelButton) {
                        cancelButton.disabled = false;
                    }
                }
            })
            .always(function() {
                if (!isInline) {
                    setLibraryEditModalBusy(saveButton, false);
                    if (cancelButton) {
                        cancelButton.disabled = false;
                    }
                } else {
                    if (saveButton) {
                        saveButton.disabled = false;
                        saveButton.removeAttribute('aria-busy');
                    }
                    if (cancelButton) {
                        cancelButton.disabled = false;
                    }
                }
            });
    }

    function approveLibraryRow(trigger) {
        var row = getLibraryRowFromTrigger(trigger);
        if (!row) {
            return;
        }

        var altCell = row.querySelector('.bbai-library-cell--alt-text');
        if (altCell && altCell.classList.contains('bbai-library-cell--editing')) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Save or cancel the current ALT edit before approving this image.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var attachmentId = parseInt(row.getAttribute('data-attachment-id') || row.getAttribute('data-id') || '', 10);
        var endpoint = getLibraryReviewEndpoint(attachmentId);
        var nonce = getLibraryRestNonce();

        if (!attachmentId || attachmentId <= 0 || !endpoint || !nonce) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', __('Unable to approve this ALT text right now.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        setLibraryRowActionLoading(trigger, __('Approving...', 'beepbeep-ai-alt-text-generator'));

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: JSON.stringify({}),
            processData: false
        })
            .done(function(response) {
                var payload = getNormalizedResponsePayload(response);
                var statsPayload = payload && payload.stats && typeof payload.stats === 'object' ? payload.stats : null;
                var renderOptions = buildLibraryRenderOptionsFromMeta(payload && payload.meta ? payload.meta : null, {
                    approved: true,
                    qualityTooltip: __('Reviewed and approved in the ALT Library.', 'beepbeep-ai-alt-text-generator'),
                    reviewSummary: __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                });

                renderLibraryAltCell(
                    row,
                    payload && typeof payload.alt === 'string' ? payload.alt : getLibraryAltTextFromRow(row),
                    renderOptions
                );

                if (statsPayload) {
                    updateAltCoverageCard(statsPayload);
                    updateLibraryReviewFilterCounts(statsPayload);
                    dispatchDashboardStatsUpdated(statsPayload);
                }

                updateLibrarySelectionState();

                if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
                    var modalAttachmentId = parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10);
                    if (modalAttachmentId === attachmentId) {
                        updateLibraryPreviewModalContent(row);
                    }
                }

                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast('success', __('ALT text marked as reviewed.', 'beepbeep-ai-alt-text-generator'));
                }
            })
            .fail(function(xhr) {
                var message = __('Unable to approve this ALT text right now.', 'beepbeep-ai-alt-text-generator');
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                }

                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast('error', message);
                }
            })
            .always(function() {
                restoreLibraryRowActionLoading(trigger);
            });
    }

    function getSelectedLibraryRows() {
        var rows = [];
        var nodes = document.querySelectorAll('.bbai-library-row-check:checked');
        for (var i = 0; i < nodes.length; i++) {
            var row = nodes[i].closest ? nodes[i].closest('.bbai-library-row') : null;
            if (row) {
                rows.push(row);
            }
        }
        return rows;
    }

    function getSelectedLibraryIds(filterFn) {
        var ids = [];
        var rows = getSelectedLibraryRows();
        for (var i = 0; i < rows.length; i++) {
            if (typeof filterFn === 'function' && !filterFn(rows[i])) {
                continue;
            }

            var id = parseInt(rows[i].getAttribute('data-attachment-id') || rows[i].getAttribute('data-id') || '', 10);
            if (!isNaN(id) && id > 0) {
                ids.push(id);
            }
        }
        return ids;
    }

    function getLibraryMain() {
        return document.querySelector('.bbai-library-main');
    }

    function getLibraryBulkToggle() {
        return document.getElementById('bbai-library-bulk-toggle');
    }

    function isLibraryBulkModeEnabled() {
        var main = getLibraryMain();
        return !!(main && main.getAttribute('data-bbai-bulk-mode') === 'true');
    }

    function setLibraryBulkMode(enabled) {
        var isEnabled = !!enabled;
        var main = getLibraryMain();
        var toggle = getLibraryBulkToggle();
        var bulkBar = document.getElementById('bbai-library-selection-bar');
        var selectAll = document.getElementById('bbai-select-all');
        var selectAllTablesBulk = document.querySelectorAll('.bbai-select-all-table');
        var checkboxes = document.querySelectorAll('.bbai-library-row-check');

        if (main) {
            main.setAttribute('data-bbai-bulk-mode', isEnabled ? 'true' : 'false');
        }

        if (toggle) {
            toggle.setAttribute('aria-pressed', isEnabled ? 'true' : 'false');
            toggle.textContent = isEnabled
                ? __('Done selecting', 'beepbeep-ai-alt-text-generator')
                : __('Select multiple', 'beepbeep-ai-alt-text-generator');
        }

        if (!isEnabled) {
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = false;
            }
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            for (var sb = 0; sb < selectAllTablesBulk.length; sb++) {
                selectAllTablesBulk[sb].checked = false;
                selectAllTablesBulk[sb].indeterminate = false;
            }
        }

        if (bulkBar) {
            if (isEnabled) {
                bulkBar.removeAttribute('hidden');
            } else {
                bulkBar.setAttribute('hidden', 'hidden');
            }
        }

        updateLibrarySelectionState();
    }

    function clearLibrarySelection() {
        var checkboxes = document.querySelectorAll('.bbai-library-row-check');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }

        var selectAll = document.getElementById('bbai-select-all');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }

        var selectAllTables = document.querySelectorAll('.bbai-select-all-table');
        for (var st = 0; st < selectAllTables.length; st++) {
            selectAllTables[st].checked = false;
            selectAllTables[st].indeterminate = false;
        }

        updateLibrarySelectionState();
    }

    function updateLibrarySelectionState() {
        var bulkMode = isLibraryBulkModeEnabled();
        var main = getLibraryMain();
        var bulkBar = document.getElementById('bbai-library-selection-bar');
        var selectedCountNode = document.querySelector('[data-bbai-selected-count]');
        var selectedCreditsNode = document.querySelector('[data-bbai-selected-credits]');
        var generateSelectedButton = document.getElementById('bbai-batch-generate');
        var regenerateSelectedButton = document.getElementById('bbai-batch-regenerate');
        var reviewSelectedButton = document.getElementById('bbai-batch-reviewed');
        var exportButton = document.getElementById('bbai-batch-export');
        var clearAltButton = document.getElementById('bbai-batch-clear-alt');
        var clearButton = document.getElementById('bbai-batch-clear');
        var bulkActionSelect = document.getElementById('bbai-library-bulk-action');
        var applyBulkButton = document.getElementById('bbai-library-apply-bulk');
        var checkboxes = document.querySelectorAll('.bbai-library-row-check');
        var selectedRows = getSelectedLibraryRows();
        var selectedCount = selectedRows.length;
        var totalCount = checkboxes.length;
        var missingSelectedCount = 0;
        var improvableSelectedCount = 0;
        var weakSelectedCount = 0;
        var withAltSelectedCount = 0;
        var estimatedCredits = 0;

        for (var i = 0; i < selectedRows.length; i++) {
            if (String(selectedRows[i].getAttribute('data-alt-missing') || 'false') === 'true') {
                missingSelectedCount++;
            } else {
                improvableSelectedCount++;
                withAltSelectedCount++;
            }
            if (getLibraryReviewState(selectedRows[i]) === 'weak') {
                weakSelectedCount++;
            }
        }
        estimatedCredits = missingSelectedCount + improvableSelectedCount;

        checkboxes.forEach(function(checkbox) {
            var row = checkbox.closest ? checkbox.closest('.bbai-library-row') : null;
            if (row) {
                row.classList.toggle('is-selected', checkbox.checked);
            }
        });

        if (selectedCountNode) {
            var selectedText = sprintf(_n('%d selected', '%d selected', selectedCount, 'beepbeep-ai-alt-text-generator'), selectedCount);
            selectedCountNode.textContent = selectedText;
        }

        if (selectedCreditsNode) {
            selectedCreditsNode.textContent = sprintf(
                _n('Up to %d credit for AI actions', 'Up to %d credits for AI actions', estimatedCredits, 'beepbeep-ai-alt-text-generator'),
                estimatedCredits
            );
        }

        if (bulkBar) {
            bulkBar.setAttribute('aria-hidden', bulkMode ? 'false' : 'true');
            if (bulkMode) {
                bulkBar.removeAttribute('hidden');
            } else {
                bulkBar.setAttribute('hidden', 'hidden');
            }
        }

        if (main) {
            main.setAttribute('data-bbai-has-selection', selectedCount > 0 ? 'true' : 'false');
        }

        if (generateSelectedButton && !isLockedBulkControl(generateSelectedButton)) {
            generateSelectedButton.disabled = selectedCount === 0 || missingSelectedCount === 0;
            generateSelectedButton.setAttribute('aria-disabled', generateSelectedButton.disabled ? 'true' : 'false');
        }
        if (regenerateSelectedButton && !isLockedBulkControl(regenerateSelectedButton)) {
            regenerateSelectedButton.disabled = selectedCount === 0 || improvableSelectedCount === 0;
            regenerateSelectedButton.setAttribute('aria-disabled', regenerateSelectedButton.disabled ? 'true' : 'false');
        }
        if (reviewSelectedButton) {
            reviewSelectedButton.disabled = selectedCount === 0 || weakSelectedCount === 0;
            reviewSelectedButton.setAttribute('aria-disabled', reviewSelectedButton.disabled ? 'true' : 'false');
        }
        if (exportButton) {
            exportButton.disabled = selectedCount === 0;
            exportButton.setAttribute('aria-disabled', exportButton.disabled ? 'true' : 'false');
        }
        if (clearAltButton) {
            clearAltButton.disabled = selectedCount === 0 || withAltSelectedCount === 0;
            clearAltButton.setAttribute('aria-disabled', clearAltButton.disabled ? 'true' : 'false');
        }
        if (clearButton) {
            clearButton.disabled = selectedCount === 0;
            clearButton.setAttribute('aria-disabled', clearButton.disabled ? 'true' : 'false');
        }
        if (applyBulkButton) {
            var selectedBulkAction = bulkActionSelect ? String(bulkActionSelect.value || '').trim() : '';
            var canApply = false;

            if (selectedCount > 0 && selectedBulkAction) {
                if (selectedBulkAction === 'generate-selected') {
                    canApply = missingSelectedCount > 0;
                } else if (selectedBulkAction === 'regenerate-selected') {
                    canApply = improvableSelectedCount > 0;
                } else if (selectedBulkAction === 'mark-reviewed') {
                    canApply = weakSelectedCount > 0;
                } else if (selectedBulkAction === 'export-alt-text') {
                    canApply = selectedCount > 0;
                }
            }

            applyBulkButton.disabled = !canApply;
            applyBulkButton.setAttribute('aria-disabled', canApply ? 'false' : 'true');
        }

        var selectAll = document.getElementById('bbai-select-all');
        if (selectAll) {
            selectAll.checked = totalCount > 0 && selectedCount === totalCount;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < totalCount;
        }
        var selectAllTablesSync = document.querySelectorAll('.bbai-select-all-table');
        for (var si = 0; si < selectAllTablesSync.length; si++) {
            selectAllTablesSync[si].checked = totalCount > 0 && selectedCount === totalCount;
            selectAllTablesSync[si].indeterminate = selectedCount > 0 && selectedCount < totalCount;
        }
    }

    function runApplyBulkSelection(trigger) {
        var bulkActionSelect = document.getElementById('bbai-library-bulk-action');
        var action = bulkActionSelect ? String(bulkActionSelect.value || '').trim() : '';
        if (!action) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Choose a bulk action first.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (!getSelectedLibraryRows().length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Select at least one image first.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (action === 'generate-selected') {
            runBulkGenerateSelected(trigger);
            return;
        }
        if (action === 'regenerate-selected') {
            runBulkRegenerateSelected(trigger);
            return;
        }
        if (action === 'mark-reviewed') {
            runBulkMarkReviewed(trigger);
            return;
        }
        if (action === 'export-alt-text') {
            runExportAltText(trigger);
        }
    }

    function selectVisibleLibraryRows() {
        var visibleRows = document.querySelectorAll('.bbai-library-row:not(.bbai-library-row--hidden)');
        if (!visibleRows.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('No visible images to select.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        visibleRows.forEach(function(row) {
            var checkbox = row.querySelector('.bbai-library-row-check');
            if (checkbox) {
                checkbox.checked = true;
            }
        });

        updateLibrarySelectionState();
    }

    function runBulkGenerateSelected(trigger) {
        var ids = getSelectedLibraryIds(function(row) {
            return String(row.getAttribute('data-alt-missing') || 'false') === 'true';
        });

        if (!ids.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Select at least one image without ALT text to generate.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (!hasBulkConfig) {
            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
            handleLockedCtaClick(trigger, { preventDefault: function() {} });
            return;
        }

        var $btn = $(trigger);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(__('Loading...', 'beepbeep-ai-alt-text-generator'));
        setDashboardRuntimeState('generation_running');

        showBulkProgress(
            sprintf(
                _n('Preparing %d selected image...', 'Preparing %d selected images...', ids.length, 'beepbeep-ai-alt-text-generator'),
                ids.length
            ),
            ids.length,
            0
        );

        queueImages(ids, 'bulk', { skipSchedule: true }, function(success, queued, error, processedIds) {
            $btn.prop('disabled', false).text(originalText);

            if (success && queued > 0) {
                updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                logBulkProgressSuccess(
                    sprintf(
                        _n('Successfully queued %d selected image for generation.', 'Successfully queued %d selected images for generation.', queued, 'beepbeep-ai-alt-text-generator'),
                        queued
                    )
                );
                startInlineGeneration(processedIds || ids, 'bulk');
                return;
            }

            if (success && queued === 0) {
                updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
                logBulkProgressSuccess(__('Selected images are already queued or processing.', 'beepbeep-ai-alt-text-generator'));
                startInlineGeneration(processedIds || ids, 'bulk');
                return;
            }

            if (isLimitReachedError(error)) {
                hideBulkProgress();
                handleLimitReached(error);
                return;
            }

            var message = (error && error.message) ? error.message : __('Failed to queue selected images.', 'beepbeep-ai-alt-text-generator');
            setDashboardRuntimeState('generation_failed');
            logBulkProgressError(message);
        });
    }

    function runBulkRegenerateSelected(trigger) {
        var ids = getSelectedLibraryIds(function(row) {
            return String(row.getAttribute('data-alt-missing') || 'false') !== 'true';
        });
        if (!ids.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Select at least one image with ALT text to improve.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (!hasBulkConfig) {
            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
            handleLockedCtaClick(trigger, { preventDefault: function() {} });
            return;
        }

        var $btn = $(trigger);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(__('Loading...', 'beepbeep-ai-alt-text-generator'));
        setDashboardRuntimeState('generation_running');

        showBulkProgress(
            sprintf(
                _n('Preparing %d image...', 'Preparing %d images...', ids.length, 'beepbeep-ai-alt-text-generator'),
                ids.length
            ),
            ids.length,
            0
        );

        queueImages(ids, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, error, processedIds) {
            $btn.prop('disabled', false).text(originalText);

            if (success && queued > 0) {
                updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                logBulkProgressSuccess(
                    sprintf(
                        _n('Successfully queued %d image for regeneration.', 'Successfully queued %d images for regeneration.', queued, 'beepbeep-ai-alt-text-generator'),
                        queued
                    )
                );
                startInlineGeneration(processedIds || ids, 'bulk-regenerate');
                return;
            }

            if (success && queued === 0) {
                updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
                logBulkProgressSuccess(__('Selected images are already queued or processing.', 'beepbeep-ai-alt-text-generator'));
                startInlineGeneration(processedIds || ids, 'bulk-regenerate');
                return;
            }

            if (isLimitReachedError(error)) {
                hideBulkProgress();
                handleLimitReached(error);
                return;
            }

            var message = (error && error.message) ? error.message : __('Failed to queue selected images.', 'beepbeep-ai-alt-text-generator');
            setDashboardRuntimeState('generation_failed');
            logBulkProgressError(message);
        });
    }

    function runBulkMarkReviewed(trigger) {
        var ids = getSelectedLibraryIds(function(row) {
            return getLibraryReviewState(row) === 'weak';
        });

        if (!ids.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Select at least one weak ALT description to mark as reviewed.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var endpoint = getLibraryReviewBatchEndpoint();
        var nonce = getLibraryRestNonce();
        if (!endpoint || !nonce) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', __('Unable to mark these images as reviewed right now.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        setLibraryRowActionLoading(trigger, __('Marking...', 'beepbeep-ai-alt-text-generator'));

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: JSON.stringify({ ids: ids }),
            processData: false
        })
            .done(function(response) {
                var payload = getNormalizedResponsePayload(response);
                var approvedIds = Array.isArray(payload && payload.approved_ids) ? payload.approved_ids : [];
                var statsPayload = payload && payload.stats && typeof payload.stats === 'object' ? payload.stats : null;

                approvedIds.forEach(function(id) {
                    var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
                    if (!row) {
                        return;
                    }

                    renderLibraryAltCell(row, getLibraryAltTextFromRow(row), {
                        approved: true,
                        qualityTooltip: __('Reviewed and approved in the ALT Library.', 'beepbeep-ai-alt-text-generator'),
                        reviewSummary: __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                    });

                    if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
                        var modalAttachmentId = parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10);
                        if (modalAttachmentId === id) {
                            updateLibraryPreviewModalContent(row);
                        }
                    }
                });

                if (statsPayload) {
                    updateAltCoverageCard(statsPayload);
                    updateLibraryReviewFilterCounts(statsPayload);
                    dispatchDashboardStatsUpdated(statsPayload);
                }

                clearLibrarySelection();

                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast(
                        'success',
                        sprintf(
                            _n('%d image marked as reviewed.', '%d images marked as reviewed.', approvedIds.length || ids.length, 'beepbeep-ai-alt-text-generator'),
                            approvedIds.length || ids.length
                        )
                    );
                }
            })
            .fail(function(xhr) {
                var message = __('Unable to mark these images as reviewed right now.', 'beepbeep-ai-alt-text-generator');
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                }

                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast('error', message);
                }
            })
            .always(function() {
                restoreLibraryRowActionLoading(trigger);
            });
    }

    function runBulkClearAltSelected(trigger) {
        var ids = getSelectedLibraryIds(function(row) {
            return String(row.getAttribute('data-alt-missing') || 'false') !== 'true';
        });

        if (!ids.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast(
                    'info',
                    __('Select at least one image that already has ALT text.', 'beepbeep-ai-alt-text-generator')
                );
            }
            return;
        }

        var confirmMsg = sprintf(
            /* translators: %d: number of images */
            __('Remove ALT text from %d selected images? You can add or generate ALT again later.', 'beepbeep-ai-alt-text-generator'),
            ids.length
        );
        if (typeof window.confirm === 'function' && !window.confirm(confirmMsg)) {
            return;
        }

        var endpoint = getLibraryAltClearBatchEndpoint();
        var nonce = getLibraryRestNonce();
        if (!endpoint || !nonce) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('error', __('Unable to clear ALT text right now.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        setLibraryRowActionLoading(trigger, __('Removing…', 'beepbeep-ai-alt-text-generator'));

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-WP-Nonce': nonce
            },
            data: JSON.stringify({ ids: ids }),
            processData: false
        })
            .done(function(response) {
                var payload = getNormalizedResponsePayload(response);
                var clearedIds = Array.isArray(payload && payload.cleared_ids) ? payload.cleared_ids : ids;
                var statsPayload = payload && payload.stats && typeof payload.stats === 'object' ? payload.stats : null;

                clearedIds.forEach(function(id) {
                    var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
                    if (!row) {
                        return;
                    }
                    renderLibraryAltCell(row, '', {
                        approved: false,
                        reviewSummary: ''
                    });
                });

                if (statsPayload) {
                    updateAltCoverageCard(statsPayload);
                    updateLibraryReviewFilterCounts(statsPayload);
                    dispatchDashboardStatsUpdated(statsPayload);
                }

                clearLibrarySelection();

                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast(
                        'success',
                        sprintf(
                            _n('ALT text removed from %d image.', 'ALT text removed from %d images.', clearedIds.length, 'beepbeep-ai-alt-text-generator'),
                            clearedIds.length
                        )
                    );
                }
            })
            .fail(function(xhr) {
                var message = __('Unable to clear ALT text right now.', 'beepbeep-ai-alt-text-generator');
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                }
                if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                    window.bbaiPushToast('error', message);
                }
            })
            .always(function() {
                restoreLibraryRowActionLoading(trigger);
            });
    }

    function runExportAltText() {
        var ids = getSelectedLibraryIds();
        if (!ids.length) {
            if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
                window.bbaiPushToast('info', __('Select at least one image to export.', 'beepbeep-ai-alt-text-generator'));
            }
            return;
        }

        var rows = [];
        ids.forEach(function(id) {
            var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
            if (!row) { return; }
            var fileName = row.getAttribute('data-file-name') || row.getAttribute('data-image-title') || '';
            var altText = row.getAttribute('data-alt-full') || '';
            fileName = String(fileName).replace(/"/g, '""');
            altText = String(altText).replace(/"/g, '""');
            rows.push('"' + fileName + '","' + altText + '"');
        });

        var header = '"' + __('File Name', 'beepbeep-ai-alt-text-generator').replace(/"/g, '""') + '","' + __('ALT Text', 'beepbeep-ai-alt-text-generator').replace(/"/g, '""') + '"';
        var csv = '\uFEFF' + header + '\n' + rows.join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'bbai-alt-text-export-' + (new Date().toISOString().slice(0, 10)) + '.csv';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
            window.bbaiPushToast('success', sprintf(__('%d items exported.', 'beepbeep-ai-alt-text-generator'), ids.length));
        }
    }

    /**
     * Accept regenerated alt text and update the UI
     */
    function acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Accepting new alt text');

        var trigger = $btn && $btn.length ? $btn.get(0) : null;
        var row = getLibraryRowFromTrigger(trigger);
        if (!row && trigger && trigger.closest) {
            row = trigger.closest('tr');
        }
        var wasMissing = row ? String(row.getAttribute('data-alt-missing') || 'false') === 'true' : false;
        var payload =
            $modal && $modal.length && typeof $modal.data === 'function'
                ? ($modal.data('bbai-regenerate-payload') || {})
                : {};
        var statsPayload = payload && payload.stats && typeof payload.stats === 'object' ? payload.stats : null;

        if (newAltText && row) {
            applyRegenerateSuccessToRow(row, attachmentId, newAltText, payload);
        }

        if (statsPayload) {
            updateAltCoverageCard(statsPayload);
            updateLibraryReviewFilterCounts(statsPayload);
            dispatchDashboardStatsUpdated(statsPayload);
        }

        // Close modal
        closeRegenerateModal($modal);

        // Re-enable button
        reenableButton($btn, originalBtnText);

        // Ensure scrolling is restored after modal closes
        setTimeout(function() {
            if (document.body && document.body.style.overflow === 'hidden') {
                document.body.style.overflow = '';
            }
            if (document.documentElement && document.documentElement.style.overflow === 'hidden') {
                document.documentElement.style.overflow = '';
            }
        }, 100);

        // Refresh usage stats if available
        if (payload.usage && typeof window.alttextai_refresh_usage === 'function') {
            window.alttextai_refresh_usage(payload.usage);
        } else if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }

        notifyLibraryFeedback(
            'success',
            wasMissing ? __('ALT text generated', 'beepbeep-ai-alt-text-generator') : __('ALT text regenerated', 'beepbeep-ai-alt-text-generator')
        );
    }

    /**
     * Queue multiple images for processing
     * Uses AJAX endpoint to queue images without generating immediately
     */
    function queueImages(ids, source, options, callback) {
        if (typeof options === 'function') {
            callback = options;
            options = {};
        }

        options = options || {};

        if (!ids || ids.length === 0) {
            callback(false, 0);
            return;
        }

        var total = ids.length;
        var queued = 0;

        // Use AJAX to queue images
        // We'll create a single AJAX call that queues all images
        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
        var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce;
        if (!ajaxUrl) {
            callback(false, 0);
            return;
        }

        // Queueing images (debug info removed for production)

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'beepbeepai_bulk_queue',
                attachment_ids: ids,
                source: source || 'bulk',
                skip_schedule: options.skipSchedule ? '1' : '0',
                nonce: nonceValue
            },
            dataType: 'json'
        })
        .done(function(response) {

            if (response && response.success) {
                // WordPress wp_send_json_success returns {success: true, data: {...}}
                var responseData = response.data || {};
                queued = responseData.queued || 0;

                if (queued > 0) {
                    callback(true, queued, null, ids.slice(0));
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] No images were queued. Response:', response);
                    // Still might be success if 0 queued but they were already in queue
                    callback(true, queued, null, ids.slice(0));
                }
            } else {
                // Error response from server
                var errorMessage = __('Failed to queue images', 'beepbeep-ai-alt-text-generator');
                var errorCode = null;
                var errorRemaining = null;

                // WordPress wp_send_json_error wraps data in response.data
                if (response && response.data) {
                    // Check if data is an object with properties
                    if (typeof response.data === 'object' && response.data !== null) {
                        if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                        if (response.data.code) {
                            errorCode = response.data.code;
                        }
                        if (response.data.remaining !== undefined && response.data.remaining !== null) {
                            errorRemaining = parseInt(response.data.remaining, 10);
                        }
                    }
                }

                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Queue failed:', errorMessage, errorCode ? '(Code: ' + errorCode + ')' : '');

                // Pass error message to callback
                callback(false, 0, {
                    message: errorMessage,
                    code: errorCode,
                    remaining: errorRemaining
                }, ids.slice(0));
            }
        })
        .fail(function(xhr, status, error) {
            window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] AJAX request failed:', {
                status: status,
                error: error,
                xhr: xhr,
                responseText: xhr.responseText,
                statusCode: xhr.status
            });

            // Try to parse error response
            var errorData = null;
            try {
                var parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.data) {
                    errorData = {
                        message: parsed.data.message || __('Failed to queue images', 'beepbeep-ai-alt-text-generator'),
                        code: parsed.data.code || null,
                        remaining: parsed.data.remaining || null
                    };
                }
            } catch (e) {
                // Not JSON, use default error
            }

            // Check if it's a nonce error
            if (xhr.status === 403) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Authentication error - check nonce');
                callback(false, 0, errorData || { message: 'Authentication error. Please refresh the page and try again.' });
                return;
            }

            // If we have a specific error message, use it instead of falling back
            if (errorData && errorData.message) {
                callback(false, 0, errorData);
                return;
            }

            // Fallback: queue images individually via REST API
            // This is slower but more reliable
            // Falling back to REST API method
            queueImagesFallback(ids, source, function(success, queued) {
                callback(success, queued, null, ids.slice(0));
            });
        });
    }

    /**
     * Fallback: Queue images one by one via REST API
     */
    function queueImagesFallback(ids, source, callback) {
        var total = ids.length;
        var queued = 0;
        var failed = 0;
        var batchSize = 5; // Smaller batches for fallback
        var processed = 0;
        var quotaError = null; // Track quota errors to surface to caller

        function isQuotaErrorCode(code) {
            return code === 'limit_reached' || code === 'bbai_trial_exhausted' ||
                   code === 'quota_exhausted' || code === 'quota_check_mismatch' || code === 'insufficient_credits';
        }

        function extractErrorFromXhr(xhr) {
            if (xhr && xhr.responseJSON) {
                var data = xhr.responseJSON.data || xhr.responseJSON;
                var code = data.code || xhr.responseJSON.code || '';
                return { code: String(code), message: data.message || '', usage: data.usage || null, remaining: data.remaining };
            }
            return null;
        }

        function processBatch(startIndex) {
            // If we hit a quota error, stop immediately
            if (quotaError) {
                callback(false, queued, quotaError);
                return;
            }

            var endIndex = Math.min(startIndex + batchSize, total);
            var batch = ids.slice(startIndex, endIndex);

            // Queue this batch using REST generate endpoint (which will queue if busy)
            var promises = batch.map(function(id) {
                return $.ajax({
                    url: (config.restRoot || config.rest || '') + 'bbai/v1/generate/' + id,
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': config.nonce
                    }
                })
                .done(function() {
                    queued++;
                    processed++;
                    updateBulkProgress(processed, total);
                })
                .fail(function(xhr) {
                    failed++;
                    processed++;
                    updateBulkProgress(processed, total);
                    // Detect quota errors from response
                    var err = extractErrorFromXhr(xhr);
                    if (err && isQuotaErrorCode(err.code)) {
                        quotaError = err;
                    }
                });
            });

            // Wait for batch to complete, then process next batch
            $.when.apply($, promises)
            .then(function() {
                if (quotaError) {
                    callback(false, queued, quotaError);
                    return;
                }
                if (endIndex < total) {
                    setTimeout(function() {
                        processBatch(endIndex);
                    }, 500);
                } else {
                    var success = queued > 0;
                    callback(success, queued, null);
                }
            })
            .fail(function() {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Fallback batch failed');
                if (quotaError) {
                    callback(false, queued, quotaError);
                    return;
                }
                // Continue processing even if batch fails
                if (endIndex < total) {
                    setTimeout(function() {
                        processBatch(endIndex);
                    }, 500);
                } else {
                    var success = queued > 0;
                    callback(success, queued, null);
                }
            });
        }

        // Start processing
        processBatch(0);
    }

    function getBulkProgressDefaultTitle(source) {
        return source === 'fix-all-issues'
            ? __('Optimizing ALT text...', 'beepbeep-ai-alt-text-generator')
            : __('Generating ALT text...', 'beepbeep-ai-alt-text-generator');
    }

    function getBulkProgressState($modal) {
        if (!$modal || !$modal.length) {
            return {
                total: 0,
                current: 0,
                processed: 0,
                failed: 0,
                skipped: 0,
                pending: 0,
                source: 'generate-missing',
                activeTitle: getBulkProgressDefaultTitle('generate-missing'),
                quotaBlocked: false,
                quotaError: null,
                complete: false,
                ctaShownTracked: false,
                trialExhaustedAfter: false
            };
        }

        return $.extend({
            total: 0,
            current: 0,
            processed: 0,
            failed: 0,
            skipped: 0,
            pending: 0,
            source: 'generate-missing',
            activeTitle: getBulkProgressDefaultTitle('generate-missing'),
            quotaBlocked: false,
            quotaError: null,
            complete: false,
            ctaShownTracked: false,
            trialExhaustedAfter: false
        }, $modal.data('bbaiBulkState') || {});
    }

    function saveBulkProgressState($modal, state) {
        if (!$modal || !$modal.length) {
            return state;
        }

        $modal.data('bbaiBulkState', state);
        $modal.data('total', state.total);
        $modal.data('current', state.current);
        $modal.data('successes', state.processed);
        $modal.data('failed', state.failed);
        $modal.data('skipped', state.skipped);
        return state;
    }

    function buildBulkProgressAnalyticsPayload(state, extra) {
        var usage = state && state.quotaError && state.quotaError.usage
            ? state.quotaError.usage
            : getUsageSnapshot(null);

        return $.extend({
            source: getAnalyticsPageSource(),
            generation_mode: String(state && state.source ? state.source : 'generate-missing'),
            requested_count: Math.max(0, parseInt(state && state.total, 10) || 0),
            processed_count: Math.max(0, parseInt(state && state.processed, 10) || 0),
            failure_count: Math.max(0, parseInt(state && state.failed, 10) || 0),
            skipped_count: Math.max(0, parseInt(state && state.skipped, 10) || 0),
            pending_count: Math.max(0, parseInt(state && state.pending, 10) || 0),
            auth_state: usage && usage.auth_state ? String(usage.auth_state) : ''
        }, extra || {});
    }

    function syncBulkProgressState($modal, patch) {
        var state = $.extend({}, getBulkProgressState($modal), patch || {});
        var processed = Math.max(0, parseInt(state.processed, 10) || 0);
        var failed = Math.max(0, parseInt(state.failed, 10) || 0);
        var skipped = Math.max(0, parseInt(state.skipped, 10) || 0);
        var total = Math.max(0, parseInt(state.total, 10) || 0);

        state.processed = processed;
        state.failed = failed;
        state.skipped = skipped;
        state.total = total;
        // Completed slots = successes + hard failures + limit skips (matches server processable_count).
        state.current = Math.min(total, processed + failed + skipped);
        state.pending = Math.max(0, total - processed - failed - skipped);

        saveBulkProgressState($modal, state);
        renderBulkProgressState($modal, state);

        return state;
    }

    function getBulkProgressQuotaCtaConfig(state) {
        var quotaError = state && state.quotaError ? state.quotaError : null;
        var usage = quotaError && quotaError.usage ? quotaError.usage : getUsageSnapshot(null);
        var freePlanOffer = usage && usage.free_plan_offer !== undefined
            ? Math.max(0, parseInt(usage.free_plan_offer, 10) || 50)
            : Math.max(1, getFreeAccountMonthlyLimit() || 50);
        var libraryUrl = getAltLibraryAdminUrl();

        if (quotaError && quotaError.isTrial) {
            return {
                label: __('Create free account', 'beepbeep-ai-alt-text-generator'),
                supportingText: sprintf(
                    __('Unlock %d images/month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                    Math.max(1, freePlanOffer || 50)
                ),
                action: 'signup',
                usage: usage,
                libraryUrl: libraryUrl
            };
        }

        if (canManageAccount()) {
            return {
                label: __('Upgrade plan', 'beepbeep-ai-alt-text-generator'),
                supportingText: __('Upgrade your plan to continue the skipped images.', 'beepbeep-ai-alt-text-generator'),
                action: 'upgrade',
                usage: usage,
                libraryUrl: libraryUrl
            };
        }

        return {
            label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
            supportingText: __('Ask the account owner to upgrade, then continue the skipped images.', 'beepbeep-ai-alt-text-generator'),
            action: 'library',
            usage: usage,
            libraryUrl: libraryUrl
        };
    }

    function buildBulkProgressHeaderTitle(state) {
        if (state.quotaBlocked) {
            return __('Processing stopped — credits exhausted', 'beepbeep-ai-alt-text-generator');
        }

        if (state.complete) {
            if (state.trialExhaustedAfter) {
                return __('Processing complete — credit limit reached', 'beepbeep-ai-alt-text-generator');
            }
            return __('Processing complete', 'beepbeep-ai-alt-text-generator');
        }

        return state.activeTitle || getBulkProgressDefaultTitle(state.source);
    }

    function buildBulkProgressHeaderSubtitle(state) {
        var processedLabel = formatDashboardNumber(state.processed);
        var skippedLabel = formatDashboardNumber(state.skipped);
        var pendingLabel = formatDashboardNumber(state.pending);
        var failedLabel = formatDashboardNumber(state.failed);

        if (state.quotaBlocked) {
            if (state.pending > 0) {
                return sprintf(
                    __('%1$s processed, %2$s skipped, %3$s pending', 'beepbeep-ai-alt-text-generator'),
                    processedLabel,
                    skippedLabel,
                    pendingLabel
                );
            }

            if (state.failed > 0) {
                return sprintf(
                    __('%1$s processed, %2$s skipped, %3$s failed', 'beepbeep-ai-alt-text-generator'),
                    processedLabel,
                    skippedLabel,
                    failedLabel
                );
            }

            return sprintf(
                __('%1$s processed, %2$s skipped', 'beepbeep-ai-alt-text-generator'),
                processedLabel,
                skippedLabel
            );
        }

        if (state.complete) {
            var parts = [];
            parts.push(
                sprintf(
                    __('Processed %1$s of %2$s images in this run.', 'beepbeep-ai-alt-text-generator'),
                    processedLabel,
                    formatDashboardNumber(state.total)
                )
            );
            if (state.skipped > 0) {
                parts.push(
                    sprintf(
                        _n(
                            '%s image skipped — credit limit reached',
                            '%s images skipped — credit limit reached',
                            state.skipped,
                            'beepbeep-ai-alt-text-generator'
                        ),
                        skippedLabel
                    )
                );
            }
            if (state.failed > 0) {
                parts.push(
                    sprintf(
                        _n('%s failed.', '%s failed.', state.failed, 'beepbeep-ai-alt-text-generator'),
                        failedLabel
                    )
                );
            }
            return parts.join(' ');
        }

        var finished = state.processed + state.failed;
        if (finished === 0 && state.skipped === 0) {
            return __('Preparing images…', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            __('%1$s of %2$s finished — %3$s left', 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(finished),
            formatDashboardNumber(state.total),
            formatDashboardNumber(state.pending)
        );
    }

    function buildBulkProgressQuotaSummary(state) {
        var processedSummary = sprintf(
            _n('%s image was processed', '%s images were processed', state.processed, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(state.processed)
        );
        var skippedSummary = sprintf(
            _n('%s image was skipped', '%s images were skipped', state.skipped, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(state.skipped)
        );
        var summary = processedSummary + ', ' + skippedSummary + '.';

        if (state.failed > 0) {
            summary += ' ' + sprintf(
                _n('%s image could not be processed.', '%s images could not be processed.', state.failed, 'beepbeep-ai-alt-text-generator'),
                formatDashboardNumber(state.failed)
            );
        }

        return summary;
    }

    function trackBulkProgressQuotaCtaShown($modal, state) {
        if (state.ctaShownTracked) {
            return state;
        }

        dispatchAnalyticsEvent(
            'batch_generation_cta_shown',
            buildBulkProgressAnalyticsPayload(state, {
                cta_label: getBulkProgressQuotaCtaConfig(state).label
            })
        );

        state.ctaShownTracked = true;
        saveBulkProgressState($modal, state);

        return state;
    }

    function renderBulkProgressState($modal, state) {
        var $subtitle;
        var $helper;
        var $failedStat;
        var $callout;
        var $primaryAction;
        var $libraryAction;
        var ctaConfig;

        if (!$modal || !$modal.length) {
            return;
        }

        $modal.find('.bbai-bulk-progress__title').text(buildBulkProgressHeaderTitle(state));

        $subtitle = $modal.find('.bbai-bulk-progress__subtitle');
        if ($subtitle.length) {
            $subtitle.text(buildBulkProgressHeaderSubtitle(state)).prop('hidden', false);
        }

        $helper = $modal.find('.bbai-bulk-progress__helper');
        if ($helper.length && (state.complete || state.quotaBlocked || !$helper.text())) {
            $helper.text('').prop('hidden', true);
        }

        $modal.find('[data-bbai-bulk-progress-processed]').text(formatDashboardNumber(state.processed));
        $modal.find('[data-bbai-bulk-progress-skipped]').text(formatDashboardNumber(state.skipped));
        $modal.find('[data-bbai-bulk-progress-pending]').text(formatDashboardNumber(state.pending));

        $failedStat = $modal.find('[data-bbai-bulk-progress-failed-stat]');
        if ($failedStat.length) {
            if (state.failed > 0) {
                $failedStat.prop('hidden', false);
                $failedStat.find('[data-bbai-bulk-progress-failed]').text(formatDashboardNumber(state.failed));
            } else {
                $failedStat.prop('hidden', true);
            }
        }

        $callout = $modal.find('[data-bbai-bulk-progress-callout]');
        if (!$callout.length) {
            return;
        }

        if (!state.quotaBlocked || !state.quotaError) {
            $callout.prop('hidden', true);
            return;
        }

        ctaConfig = getBulkProgressQuotaCtaConfig(state);
        $callout.prop('hidden', false);
        $callout.find('[data-bbai-bulk-progress-callout-title]').text(state.quotaError.batchMessage);
        $callout.find('[data-bbai-bulk-progress-callout-text]').text(buildBulkProgressQuotaSummary(state));
        $callout.find('[data-bbai-bulk-progress-callout-support]').text(ctaConfig.supportingText || '');

        $primaryAction = $callout.find('[data-bbai-bulk-progress-cta]');
        $primaryAction.text(ctaConfig.label || '').prop('hidden', !ctaConfig.label);

        $libraryAction = $callout.find('[data-bbai-bulk-progress-library]');
        if (ctaConfig.action === 'library' || !ctaConfig.libraryUrl) {
            $libraryAction.prop('hidden', true);
        } else {
            $libraryAction.text(__('Open ALT Library', 'beepbeep-ai-alt-text-generator')).prop('hidden', false);
        }

        trackBulkProgressQuotaCtaShown($modal, state);
    }

    function logBulkProgressQuotaSkip(skipCount, quotaCause) {
        var $modal = $('#bbai-bulk-progress-modal');
        var $log;
        var $entry;
        var message;

        if (!$modal.length) {
            return;
        }

        $log = $modal.find('.bbai-bulk-progress__log');
        $entry = $log.find('[data-bbai-bulk-progress-quota-log]');
        message = sprintf(
            _n('%1$s image skipped — %2$s', '%1$s images skipped — %2$s', skipCount, 'beepbeep-ai-alt-text-generator'),
            formatDashboardNumber(skipCount),
            quotaCause || __('credit limit reached', 'beepbeep-ai-alt-text-generator')
        );

        if ($entry.length) {
            $entry.find('.bbai-bulk-progress__log-time').text(new Date().toLocaleTimeString());
            $entry.find('.bbai-bulk-progress__log-text').text(message);
            $log.scrollTop($log[0].scrollHeight);
            return;
        }

        $log.append(
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--warning" data-bbai-bulk-progress-quota-log="1">' +
                '<span class="bbai-bulk-progress__log-time">' + escapeHtml(new Date().toLocaleTimeString()) + '</span>' +
                '<span class="bbai-bulk-progress__log-icon">!</span>' +
                '<span class="bbai-bulk-progress__log-text">' + escapeHtml(message) + '</span>' +
            '</div>'
        );
        $log.scrollTop($log[0].scrollHeight);
    }

    function markBulkLibraryRowsQueued(ids) {
        if (!ids || !ids.length) {
            return;
        }
        ids.forEach(function(rawId) {
            var id = parseInt(rawId, 10);
            if (!id) {
                return;
            }
            var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
            if (row) {
                row.classList.add('bbai-library-row--bulk-queued');
            }
        });
    }

    function clearBulkLibraryRowGenerationUi(ids) {
        if (!ids || !ids.length) {
            return;
        }
        ids.forEach(function(rawId) {
            var id = parseInt(rawId, 10);
            if (!id) {
                return;
            }
            var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
            if (row) {
                row.classList.remove(
                    'bbai-library-row--bulk-queued',
                    'bbai-library-row--bulk-failed',
                    'bbai-library-row--processing'
                );
            }
        });
        var progressBar = document.querySelector('[data-bbai-library-progressbar]');
        if (progressBar) {
            progressBar.classList.remove('bbai-library-progressbar--busy');
        }
    }

    function useLicensedBulkJobsApi() {
        return !!(window.bbaiLicensedBulkJobClient && window.bbaiLicensedBulkJobClient.isEligible());
    }

    function stopLicensedBulkJobPolling($modal) {
        if (window.bbaiLicensedBulkJobClient && window.bbaiLicensedBulkJobClient.stopPolling) {
            window.bbaiLicensedBulkJobClient.stopPolling($modal);
        }
    }

    /**
     * Licensed bulk: delegates to bbai-licensed-bulk-job-client.js (POST /api/jobs + poll).
     */
    function startLicensedBulkJobFlow(normalizedIds, source) {
        var $modal = $('#bbai-bulk-progress-modal');
        var C = window.bbaiLicensedBulkJobClient;
        if (!$modal.length || !normalizedIds || !normalizedIds.length) {
            return;
        }
        if (!C || typeof C.run !== 'function') {
            processInlineGenerationQueue(normalizedIds, 1);
            return;
        }

        $modal.removeData('bbaiBulkJobMinimizedForLongRun');

        var total = normalizedIds.length;
        var src = String(source || 'generate-missing');

        C.run({
            $modal: $modal,
            attachmentIds: normalizedIds,
            source: src,
            strings: {
                startingGeneration: __('Starting generation…', 'beepbeep-ai-alt-text-generator'),
                sendingBatch: __('Sending batch to the server…', 'beepbeep-ai-alt-text-generator'),
                couldNotStart: __('Could not start bulk generation.', 'beepbeep-ai-alt-text-generator'),
                startTimeout: __(
                    'Starting the batch timed out. Please try again with fewer images or check your connection.',
                    'beepbeep-ai-alt-text-generator'
                ),
                noJobId: __('No job id returned.', 'beepbeep-ai-alt-text-generator'),
                jobStartedProcessing: __('Generation job started — processing images…', 'beepbeep-ai-alt-text-generator'),
                batchAccepted: __('Batch accepted. Processing on the server…', 'beepbeep-ai-alt-text-generator')
            },
            formatImageError: function(pid, msg) {
                return sprintf(__('Image #%d: %s', 'beepbeep-ai-alt-text-generator'), pid, msg);
            },
            armStallHint: function() {
                var stallT = window.setTimeout(function() {
                    var st = getBulkProgressState($modal);
                    if (st.complete) {
                        return;
                    }
                    logBulkProgressSuccess(
                        __(
                            'Still starting… Generation will continue. You can minimize this window and keep using the ALT Library.',
                            'beepbeep-ai-alt-text-generator'
                        )
                    );
                }, 35000);
                $modal.data('bbaiBulkStallTimer', stallT);
            },
            clearStallHint: function() {
                var stallClear = $modal.data('bbaiBulkStallTimer');
                if (stallClear) {
                    clearTimeout(stallClear);
                }
                $modal.removeData('bbaiBulkStallTimer');
            },
            onLogSuccess: logBulkProgressSuccess,
            onLogError: logBulkProgressError,
            onTitle: updateBulkProgressTitle,
            onNotEligibleFallback: function(ids) {
                processInlineGenerationQueue(ids, 1);
            },
            onFatalNoAjax: function() {
                logBulkProgressError(__('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'));
                finalizeInlineGeneration(0, 0, 0, null);
            },
            onPollStarted: function(payload) {
                dispatchAnalyticsEvent('bulk_generation_poll_started', {
                    source: getAnalyticsPageSource(),
                    generation_mode: src,
                    job_id: payload.jobId,
                    requested_count: payload.requested_count
                });
            },
            onFirstItemCompleted: function(payload) {
                dispatchAnalyticsEvent('bulk_generation_first_item_completed', {
                    source: getAnalyticsPageSource(),
                    generation_mode: src,
                    job_id: payload.jobId
                });
            },
            onProgressSeen: function() {
                dispatchAnalyticsEvent(
                    'bulk_generation_progress_seen',
                    buildBulkProgressAnalyticsPayload(getBulkProgressState($modal))
                );
            },
            applyUpdatedImages: applyUpdatedImagesFromEnvelope,
            syncRowFromItemAlt: function(id, alt) {
                syncLibraryRowAfterGeneration(id, alt, { meta: null, usage: null });
            },
            maybeMinimizeLongRun: function() {
                if (typeof minimizeBulkProgress === 'function') {
                    minimizeBulkProgress('long_running_job');
                }
            },
            syncModalProgress: function(ctx) {
                var job = ctx.job;
                var jobState = ctx.jobState;
                var totalN = ctx.total;
                var finishedSlots = ctx.finishedSlots;
                var terminal = ctx.terminal;
                var backendPct = ctx.backendPercent;

                var failedN = jobState.failures - (ctx.preFailed || 0);
                if (failedN < 0) {
                    failedN = 0;
                }

                var titleMsg;
                if (terminal) {
                    titleMsg = __('Finishing up…', 'beepbeep-ai-alt-text-generator');
                } else if (jobState.successes + failedN === 0) {
                    titleMsg = __('Preparing images…', 'beepbeep-ai-alt-text-generator');
                } else {
                    var activeIdx = Math.min(totalN, jobState.successes + failedN + 1);
                    titleMsg = sprintf(
                        __('Generating image %1$d of %2$d…', 'beepbeep-ai-alt-text-generator'),
                        activeIdx,
                        totalN
                    );
                }

                var st = syncBulkProgressState($modal, {
                    total: totalN,
                    processed: jobState.successes,
                    failed: jobState.failures,
                    skipped: 0,
                    source: src,
                    activeTitle: titleMsg,
                    quotaBlocked: false,
                    quotaError: null,
                    complete: false
                });
                updateBulkProgressTitle(titleMsg);
                updateBulkProgress(finishedSlots, totalN, null, {
                    backendPercent: backendPct
                });

                if (window.bbaiJobState) {
                    window.bbaiJobState.update({
                        progress: st.current,
                        label: titleMsg
                    });
                }
            },
            finalize: function(successes, failures, skipped) {
                finalizeInlineGeneration(successes, failures, skipped, null);
            }
        });
    }

    /**
     * Begin inline generation after queue completes.
     * Connected accounts: licensed bulk job client (POST /api/jobs + poll). Guest trial: sequential inline_generate per image.
     */
    function startInlineGeneration(idList, source) {
        if (!idList || !idList.length || !hasBulkConfig) {
            return;
        }

        var normalized = Array.from(new Set(idList.map(function(id) {
            return parseInt(id, 10);
        }).filter(function(id) {
            return !isNaN(id) && id > 0;
        })));

        if (!normalized.length) {
            return;
        }

        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) {
            return;
        }

        dispatchAnalyticsEvent('generation_started', {
            source: getAnalyticsPageSource(),
            generation_mode: source || 'generate-missing',
            requested_count: normalized.length
        });

        dispatchAnalyticsEvent('bulk_generation_started', {
            source: getAnalyticsPageSource(),
            generation_mode: source || 'generate-missing',
            requested_count: normalized.length,
            strategy: useLicensedBulkJobsApi() ? 'api_jobs' : 'sequential_per_image'
        });

        markBulkLibraryRowsQueued(normalized);

        var progressTitle = __('Preparing images…', 'beepbeep-ai-alt-text-generator');
        var modalState;
        var intro = source === 'fix-all-issues'
            ? sprintf(
                _n(
                    'Preparing automatic optimization for %d image…',
                    'Preparing automatic optimization for %d images…',
                    normalized.length,
                    'beepbeep-ai-alt-text-generator'
                ),
                normalized.length
            )
            : sprintf(
                _n(
                    'Preparing %d image for generation…',
                    'Preparing %d images for generation…',
                    normalized.length,
                    'beepbeep-ai-alt-text-generator'
                ),
                normalized.length
            );

        $modal.data('startTime', Date.now());
        $modal.data('source', source || 'generate-missing');
        modalState = syncBulkProgressState($modal, {
            total: normalized.length,
            processed: 0,
            failed: 0,
            skipped: 0,
            source: source || 'generate-missing',
            activeTitle: progressTitle,
            quotaBlocked: false,
            quotaError: null,
            complete: false,
            ctaShownTracked: false
        });
        updateBulkProgress(modalState.current, modalState.total);

        startBulkProgressHelperRotation($modal);
        if (source === 'fix-all-issues') {
            setBulkProgressHelperText(getBulkOptimizationProcessingLabel(normalized.length));
        }
        logBulkProgressSuccess(intro);

        // Sync global job state
        if (window.bbaiJobState) {
            window.bbaiJobState.start(progressTitle, normalized.length);
        }

        $modal.data('batchQueue', normalized.slice(0));

        if (useLicensedBulkJobsApi()) {
            startLicensedBulkJobFlow(normalized, source || 'generate-missing');
            return;
        }

        processInlineGenerationQueue(normalized, 1);
    }

    /**
     * Process images sequentially in batches and update modal progress.
     */
    function processInlineGenerationQueue(queue, batchSize) {
        batchSize = batchSize || 1;
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) {
            return;
        }
        var total = queue.length;
        var successes = 0;
        var failures = 0;
        var skipped = 0;
        var active = 0;
        var blockedByQuota = false;
        var quotaError = null;
        var quotaLimitTracked = false;
        var partialQuotaTracked = false;
        var progressSeenTracked = false;

        function clearStalledWatch() {
            var t = $modal.data('bbaiBulkStallTimer');
            if (t) {
                clearTimeout(t);
            }
            $modal.removeData('bbaiBulkStallTimer');
        }

        function armStalledWatch() {
            clearStalledWatch();
            var timerId = window.setTimeout(function() {
                if (blockedByQuota) {
                    return;
                }
                if (successes + failures > 0) {
                    return;
                }
                var st = getBulkProgressState($modal);
                if (st.complete) {
                    return;
                }
                logBulkProgressSuccess(
                    __('Still starting… Generation will continue. You can minimize this window and keep using the ALT Library.', 'beepbeep-ai-alt-text-generator')
                );
                dispatchAnalyticsEvent(
                    'bulk_generation_stalled_fallback_shown',
                    buildBulkProgressAnalyticsPayload(st)
                );
            }, 40000);
            $modal.data('bbaiBulkStallTimer', timerId);
        }

        armStalledWatch();

        function syncState(activeTitleOverride) {
            var prev = getBulkProgressState($modal);
            var fallbackTitle = getBulkProgressDefaultTitle(String($modal.data('source') || 'generate-missing'));
            var nextTitle = prev.activeTitle || fallbackTitle;
            if (activeTitleOverride !== undefined && activeTitleOverride !== null && activeTitleOverride !== '') {
                nextTitle = activeTitleOverride;
            }
            return syncBulkProgressState($modal, {
                total: total,
                processed: successes,
                failed: failures,
                skipped: skipped,
                source: String($modal.data('source') || 'generate-missing'),
                activeTitle: nextTitle,
                quotaBlocked: blockedByQuota,
                quotaError: quotaError,
                complete: false
            });
        }

        function handleQuotaStop(error) {
            var unstartedCount = queue.length;
            var state;

            clearStalledWatch();
            blockedByQuota = true;
            quotaError = mapGenerationErrorToUi(error || { code: 'quota_exhausted' });
            skipped += 1 + unstartedCount;
            queue = [];

            state = syncState();
            updateBulkProgress(state.current, state.total);
            logBulkProgressQuotaSkip(skipped, quotaError.quotaCause);

            setDashboardRuntimeState('generation_failed');

            if (window.bbaiJobState) {
                window.bbaiJobState.update({
                    progress: state.current,
                    skipped: skipped,
                    label: buildBulkProgressHeaderTitle(state)
                });
            }

            if (!quotaLimitTracked) {
                dispatchAnalyticsEvent('generation_failed', {
                    source: getAnalyticsPageSource(),
                    generation_mode: String($modal.data('source') || 'generate-missing'),
                    requested_count: total,
                    processed_count: successes + failures,
                    accepted_count: successes,
                    error_code: quotaError.code || 'quota_error'
                });
                dispatchAnalyticsEvent('batch_generation_quota_limit_hit', buildBulkProgressAnalyticsPayload(state, {
                    error_code: quotaError.code || 'quota_error'
                }));
                quotaLimitTracked = true;
            }

            if (!partialQuotaTracked && (successes > 0 || failures > 0)) {
                dispatchAnalyticsEvent('batch_generation_partial_quota_stop', buildBulkProgressAnalyticsPayload(state, {
                    error_code: quotaError.code || 'quota_error'
                }));
                partialQuotaTracked = true;
            }
        }

        function processNext() {
            if (!queue.length && active === 0) {
                finalizeInlineGeneration(successes, failures, skipped, quotaError);
                return;
            }

            if (active >= batchSize || !queue.length) {
                return;
            }

            var id = queue.shift();
            active++;

            var rowEl = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
            if (rowEl) {
                rowEl.classList.remove('bbai-library-row--bulk-queued');
                if (typeof window.bbaiSetRowProcessing === 'function') {
                    window.bbaiSetRowProcessing(rowEl);
                } else {
                    rowEl.classList.add('bbai-library-row--processing');
                }
            }

            var ordinal = successes + failures + skipped + 1;
            var generatingTitle = sprintf(
                __('Generating ALT text for image %1$d of %2$d…', 'beepbeep-ai-alt-text-generator'),
                ordinal,
                total
            );
            syncState(generatingTitle);
            updateBulkProgressTitle(generatingTitle);
            if (window.bbaiJobState) {
                window.bbaiJobState.update({ label: generatingTitle });
            }

            generateAltTextForId(id)
                .then(function(result) {
                    successes++;
                    applyLibraryUsageAfterGeneration(result && result.usage ? result.usage : null, 1);
                    var title = result && result.title
                        ? result.title
                        : sprintf(__('Generated alt text for image #%d', 'beepbeep-ai-alt-text-generator'), id);
                    if (!progressSeenTracked) {
                        progressSeenTracked = true;
                        clearStalledWatch();
                        dispatchAnalyticsEvent(
                            'bulk_generation_progress_seen',
                            buildBulkProgressAnalyticsPayload(getBulkProgressState($modal))
                        );
                    }
                    var state = syncState();
                    updateBulkProgress(state.current, state.total, title);
                    if (window.bbaiJobState) {
                        window.bbaiJobState.tick({ success: true, title: title });
                    }
                    if (rowEl && typeof window.bbaiSetRowDone === 'function') {
                        window.bbaiSetRowDone(rowEl);
                    }
                })
                .catch(function(error) {
                    if (isLimitReachedError(error)) {
                        handleQuotaStop(error);
                        if (window.bbaiJobState) {
                            window.bbaiJobState.update({ skipped: skipped });
                        }
                        return;
                    }

                    failures++;
                    if (!progressSeenTracked) {
                        progressSeenTracked = true;
                        clearStalledWatch();
                        dispatchAnalyticsEvent(
                            'bulk_generation_progress_seen',
                            buildBulkProgressAnalyticsPayload(getBulkProgressState($modal))
                        );
                    }
                    var mappedError = mapGenerationErrorToUi(error);
                    var state = syncState();
                    var message = sprintf(__('Image #%d: %s', 'beepbeep-ai-alt-text-generator'), id, mappedError.rowMessage);
                    logBulkProgressError(message);
                    updateBulkProgress(state.current, state.total);
                    if (window.bbaiJobState) {
                        window.bbaiJobState.tick({ success: false, title: message });
                    }
                    if (rowEl) {
                        rowEl.classList.remove('bbai-library-row--processing', 'bbai-library-row--bulk-queued');
                        rowEl.classList.add('bbai-library-row--bulk-failed');
                        window.setTimeout(function() {
                            if (rowEl) {
                                rowEl.classList.remove('bbai-library-row--bulk-failed');
                            }
                        }, 8000);
                    }
                })
                .finally(function() {
                    active--;
                    if (blockedByQuota) {
                        if (active === 0) {
                            finalizeInlineGeneration(successes, failures, skipped, quotaError);
                        }
                        return;
                    }
                    setTimeout(processNext, 250);
                });

            if (active < batchSize && queue.length) {
                processNext();
            }
        }

        processNext();
    }

    function finalizeInlineGeneration(successes, failures, skipped, quotaError) {
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            stopLicensedBulkJobPolling($modal);
            var stallT = $modal.data('bbaiBulkStallTimer');
            if (stallT) {
                clearTimeout(stallT);
            }
            $modal.removeData('bbaiBulkStallTimer');
            var batchIds = $modal.data('batchQueue');
            if (batchIds && batchIds.length) {
                clearBulkLibraryRowGenerationUi(batchIds);
            }
        }
        var prior = getBulkProgressState($modal);
        var state = syncBulkProgressState($modal, {
            processed: successes,
            failed: failures,
            skipped: skipped,
            quotaBlocked: !!quotaError,
            quotaError: quotaError || null,
            complete: true,
            trialExhaustedAfter: !!prior.trialExhaustedAfter
        });
        var total = state.total;
        var source = $modal.length ? String($modal.data('source') || 'generate-missing') : 'generate-missing';
        var summary = {
            source: source,
            successes: successes,
            failures: failures,
            skipped: skipped,
            processed: successes,
            total: total
        };
        var modalVisible = $modal.length && $modal.hasClass('active');

        if ($modal.length) {
            stopBulkProgressHelperRotation($modal);
            updateBulkProgress(state.current, state.total);
        }

        if (window.bbaiJobState) {
            window.bbaiJobState.complete({
                status: quotaError ? 'quota' : (failures > 0 ? 'error' : 'complete'),
                successes: successes,
                failures: failures,
                skipped: skipped
            });
        }

        if (!quotaError) {
            var completedPayload = buildBulkProgressAnalyticsPayload(state);
            dispatchAnalyticsEvent('batch_generation_completed', completedPayload);
            dispatchAnalyticsEvent('bulk_generation_completed', completedPayload);
        }

        if (!quotaError && successes > 0 && failures > 0) {
            dispatchAnalyticsEvent('bulk_generation_partial_failure', buildBulkProgressAnalyticsPayload(state));
        }

        if (!quotaError && successes > 0) {
            dispatchAnalyticsEvent('generation_completed', {
                source: getAnalyticsPageSource(),
                generation_mode: source,
                requested_count: total,
                processed_count: total,
                accepted_count: successes,
                success_count: successes,
                failure_count: failures,
                skipped_count: skipped
            });
        }
        if (!quotaError && failures > 0) {
            dispatchAnalyticsEvent('generation_failed', {
                source: getAnalyticsPageSource(),
                generation_mode: source,
                requested_count: total,
                processed_count: total,
                accepted_count: successes,
                success_count: successes,
                failure_count: failures,
                skipped_count: skipped
            });
        }

        if (quotaError || failures > 0) {
            setDashboardRuntimeState('generation_failed');
        } else if (successes > 0) {
            setDashboardRuntimeState('generation_complete');
        } else {
            setDashboardRuntimeState('idle');
        }

        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }

        var finishFeedback = function() {
            if (!modalVisible) {
                showAltGenerationToast(summary);
            }

            if (quotaError) {
                dispatchDashboardFeedback(
                    'warning',
                    buildBulkProgressQuotaSummary(state),
                    { duration: 12000, skipToast: true }
                );
                if (getLibraryWorkspaceRoot() && source === 'generate-missing') {
                    var postStats = readLibraryWorkspaceStatsFromRoot();
                    var stillMissingAfter = postStats && postStats.images_missing_alt !== null && postStats.images_missing_alt !== undefined
                        ? Math.max(0, parseInt(postStats.images_missing_alt, 10) || 0)
                        : 0;
                    var usageAfter = getLibraryWorkspaceUsageSnapshot();
                    var remAfter = usageAfter && usageAfter.remaining !== undefined && usageAfter.remaining !== null
                        ? Math.max(0, parseInt(usageAfter.remaining, 10) || 0)
                        : null;
                    var noCreditsAfter = isAnonymousTrialExhaustedClient() ||
                        (!isLibraryProPlan() && remAfter !== null && remAfter <= 0);
                    if (successes > 0 && (stillMissingAfter > 0 || noCreditsAfter)) {
                        notifyLibraryFeedback(
                            'warning',
                            buildLibraryGenerateAllMissingQuotaDoneMessage(successes, stillMissingAfter, noCreditsAfter)
                        );
                    }
                }
                return;
            }

            dispatchDashboardFeedback(
                failures > 0 ? 'warning' : 'success',
                buildDashboardGenerationMessage(source, successes, failures),
                { duration: 12000, skipToast: true }
            );

            // Preflight-capped runs finish without quotaError; still surface “N updated, M still missing, no credits” in library.
            if (getLibraryWorkspaceRoot() && source === 'generate-missing' && successes > 0) {
                var postStatsNc = readLibraryWorkspaceStatsFromRoot();
                var stillMissingNc = postStatsNc && postStatsNc.images_missing_alt !== null && postStatsNc.images_missing_alt !== undefined
                    ? Math.max(0, parseInt(postStatsNc.images_missing_alt, 10) || 0)
                    : 0;
                var usageAfterNc = getLibraryWorkspaceUsageSnapshot();
                var remAfterNc = usageAfterNc && usageAfterNc.remaining !== undefined && usageAfterNc.remaining !== null
                    ? Math.max(0, parseInt(usageAfterNc.remaining, 10) || 0)
                    : null;
                var noCreditsAfterNc = isAnonymousTrialExhaustedClient() ||
                    (!isLibraryProPlan() && remAfterNc !== null && remAfterNc <= 0);
                if (stillMissingNc > 0 && noCreditsAfterNc) {
                    notifyLibraryFeedback(
                        'warning',
                        buildLibraryGenerateAllMissingQuotaDoneMessage(successes, stillMissingNc, true)
                    );
                }
            }
        };

        if (typeof window.bbaiRefreshDashboardCoverage === 'function') {
            window.bbaiRefreshDashboardCoverage()
                .always(finishFeedback);
        } else {
            applyLibraryReviewFilters();
            finishFeedback();
        }
    }

    function syncLibraryRowAfterGeneration(id, altText, payload) {
        var row = document.querySelector('.bbai-library-row[data-attachment-id="' + id + '"]');
        if (!row) {
            return;
        }

        applyRegenerateSuccessToRow(
            row,
            id,
            altText,
            payload || null,
            buildLibraryRenderOptionsFromMeta(payload && payload.meta ? payload.meta : null, {
                approved: false
            })
        );

        if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
            var modalAttachmentId = parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10);
            if (modalAttachmentId === id) {
                updateLibraryPreviewModalContent(row);
            }
        }
    }

    function generateAltTextForId(id) {
        return new Promise(function(resolve, reject) {
            var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) || '';
            var nonceValue = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
            if (!ajaxUrl) {
                reject({ message: __('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'), code: 'ajax_unavailable' });
                return;
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                timeout: 25000,
                data: {
                    action: 'beepbeepai_inline_generate',
                    attachment_ids: [id],
                    nonce: nonceValue
                }
            })
            .done(function(response) {
                // Handle successful HTTP response (status 200)
                try {
                    if (response && response.data) {
                        console.log('[BBAI] Generation result', response.data);
                    }
                    // Handle successful response
                    if (response && response.success) {
                        // Check for results array (inline generate format)
                        if (response.data && response.data.results && Array.isArray(response.data.results)) {
                            var first = response.data.results[0];
                            if (first && first.success) {
                                var inlinePayload = {
                                    meta: first.meta || response.data.meta || null,
                                    usage: first.usage || response.data.usage || null
                                };
                                applyUpdatedImagesFromEnvelope(response.data.updated_images);
                                applyInlineGenerationTrialMetaToUi(response.data);
                                if (typeof window.alttextai_refresh_usage === 'function') {
                                    window.alttextai_refresh_usage();
                                }
                                syncLibraryRowAfterGeneration(id, first.alt_text || '', inlinePayload);
                                resolve({
                                    id: id,
                                    alt: first.alt_text || '',
                                    title: first.title || sprintf(__('Image #%d', 'beepbeep-ai-alt-text-generator'), id),
                                    usage: inlinePayload.usage || null
                                });
                                return;
                            } else {
                                // Generation failed for this image - extract error message
                                var errorMsg = __('Failed to generate alt text.', 'beepbeep-ai-alt-text-generator');
                                if (first && first.message) {
                                    errorMsg = first.message;
                                } else if (first && first.code) {
                                    errorMsg = sprintf(__('Generation failed: %s', 'beepbeep-ai-alt-text-generator'), first.code);
                                }
                                reject({
                                    message: errorMsg,
                                    code: (first && first.code) ? first.code : 'generation_failed',
                                    remaining: first && first.remaining !== undefined ? first.remaining : null,
                                    retry_after: first && first.retry_after !== undefined ? first.retry_after : null,
                                    usage: first && first.usage ? first.usage : null
                                });
                                return;
                            }
                        }
                        // Check for direct alt_text (regenerate single format)
                        else if (response.data && response.data.alt_text) {
                            var directPayload = {
                                meta: response.data.meta || null,
                                usage: response.data.usage || null
                            };
                            syncLibraryRowAfterGeneration(id, response.data.alt_text || '', directPayload);
                            resolve({
                                id: id,
                                alt: response.data.alt_text || '',
                                title: sprintf(__('Image #%d', 'beepbeep-ai-alt-text-generator'), id),
                                usage: directPayload.usage || null
                            });
                            return;
                        }
                        // Check for error message in data
                        else if (response.data && response.data.message) {
                            reject({ message: response.data.message });
                            return;
                        }
                    }

                    // Handle error response (success: false)
                    if (response && response.success === false) {
                        var errorMsg = __('Failed to generate alt text.', 'beepbeep-ai-alt-text-generator');
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.message) {
                            errorMsg = response.message;
                        }
                        reject({
                            message: errorMsg,
                            code: (response.data && response.data.code) || response.code || 'api_error',
                            remaining: response.data && response.data.remaining !== undefined ? response.data.remaining : null,
                            retry_after: response.data && response.data.retry_after !== undefined ? response.data.retry_after : null,
                            usage: response.data && response.data.usage ? response.data.usage : null
                        });
                        return;
                    }

                    // Unexpected response structure
                    window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Unexpected response structure:', response);
                    reject({ message: __('Unexpected response from server. Response structure does not match expected format.', 'beepbeep-ai-alt-text-generator') });
                } catch (e) {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Error parsing response:', e, response);
                    reject({ message: sprintf(__('Error parsing server response: %s', 'beepbeep-ai-alt-text-generator'), (e && e.message) ? e.message : __('Unknown error', 'beepbeep-ai-alt-text-generator')) });
                }
            })
            .fail(function(xhr) {
                var message = __('Request failed', 'beepbeep-ai-alt-text-generator');
                var errorCode = null;
                var errorUsage = null;
                var errorRemaining = null;
                var retryAfter = null;

                // Try to extract detailed error information
                if (xhr && xhr.responseJSON) {
                    if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    } else if (xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    if (xhr.responseJSON.data && xhr.responseJSON.data.code) {
                        errorCode = xhr.responseJSON.data.code;
                    } else if (xhr.responseJSON.code) {
                        errorCode = xhr.responseJSON.code;
                    }
                    if (xhr.responseJSON.data && xhr.responseJSON.data.usage) {
                        errorUsage = xhr.responseJSON.data.usage;
                    }
                    if (xhr.responseJSON.data && xhr.responseJSON.data.remaining !== undefined) {
                        errorRemaining = xhr.responseJSON.data.remaining;
                    }
                    if (xhr.responseJSON.data && xhr.responseJSON.data.retry_after !== undefined) {
                        retryAfter = xhr.responseJSON.data.retry_after;
                    }
                } else if (xhr && xhr.statusText === 'timeout') {
                    message = __('The request took too long and was stopped. Please try again.', 'beepbeep-ai-alt-text-generator');
                    errorCode = 'request_timeout';
                } else if (xhr && xhr.status === 0) {
                    message = __('Network error: Unable to connect to server. Please check your internet connection.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr && xhr.status === 404) {
                    message = __('AJAX endpoint not found. The plugin may need to be reactivated.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr && xhr.status === 500) {
                    message = __('Server error occurred. Please check your WordPress error logs.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr && xhr.status === 200) {
                    // Status 200 but response structure is invalid or parsing failed
                    message = __('Server returned an invalid response. Please check the browser console for details.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr && xhr.status) {
                    message = sprintf(__('Request failed with status %d', 'beepbeep-ai-alt-text-generator'), xhr.status);
                }

                // Log detailed error for debugging
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Inline generate request failed:', {
                    status: xhr ? xhr.status : 'unknown',
                    statusText: xhr ? xhr.statusText : 'unknown',
                    response: xhr ? xhr.responseJSON : 'no response',
                    errorCode: errorCode,
                    message: message
                });

                reject({
                    message: message,
                    code: errorCode,
                    remaining: errorRemaining,
                    retry_after: retryAfter,
                    usage: errorUsage
                });
            });
        });
    }

    function readInlineGenerationInt(data, key) {
        if (!data || data[key] === undefined || data[key] === null) {
            return null;
        }
        var n = parseInt(data[key], 10);
        return isNaN(n) ? null : n;
    }

    /**
     * Apply authoritative trial counters from inline_generate JSON (no client-side credit math).
     */
    function applyInlineGenerationTrialMetaToUi(data) {
        var lim = readInlineGenerationInt(data, 'trial_limit');
        var used = readInlineGenerationInt(data, 'trial_used_after');
        var rem = readInlineGenerationInt(data, 'trial_remaining_after');
        if (lim === null || used === null || rem === null) {
            return false;
        }

        var exhausted = !!data.trial_exhausted_after;
        var payload = normalizeUsagePayload({
            used: used,
            limit: lim,
            remaining: rem,
            credits_used: used,
            credits_total: lim,
            credits_remaining: rem,
            auth_state: 'anonymous',
            quota_type: 'trial',
            is_trial: true,
            trial_exhausted: exhausted,
            plan: 'trial',
            plan_type: 'trial',
            quota_state: exhausted ? 'exhausted' : (rem <= 0 ? 'exhausted' : 'active')
        });

        if (!payload) {
            return false;
        }

        mirrorUsagePayload(payload);
        applyLibraryWorkspaceUsageAttributes(payload);
        updateLibraryUsageSurface(payload);
        syncSharedUsageBanner(payload);
        enforceOutOfCreditsBulkLocks();
        applyDashboardState(false);
        $(document).trigger('bbai:usage-updated');

        if (window.BBAI_DASH && window.BBAI_DASH.trial && typeof window.BBAI_DASH.trial === 'object') {
            window.BBAI_DASH.trial.used = used;
            window.BBAI_DASH.trial.limit = lim;
            window.BBAI_DASH.trial.remaining = rem;
            window.BBAI_DASH.trial.remaining_free_images = rem;
            window.BBAI_DASH.trial.exhausted = exhausted;
        }

        if (typeof readDashboardStatsFromRoot === 'function') {
            var statsHero = readDashboardStatsFromRoot();
            if (statsHero && typeof updateDashboardHero === 'function') {
                updateDashboardHero(statsHero);
            }
        }

        return true;
    }

    function applyUpdatedImagesFromEnvelope(updatedImages) {
        if (!updatedImages || !updatedImages.length) {
            return;
        }

        updatedImages.forEach(function(entry) {
            var id = entry && entry.id !== undefined ? parseInt(entry.id, 10) : 0;
            if (!id) {
                return;
            }
            var alt = entry.alt_text != null ? String(entry.alt_text) : '';
            syncLibraryRowAfterGeneration(id, alt, { meta: null, usage: null });
        });
    }

    /**
     * Show bulk progress modal with detailed tracking
     */
    function showBulkProgress(label, total, current) {
        var $modal = $('#bbai-bulk-progress-modal');
        var state;

        // Create modal if it doesn't exist
        if (!$modal.length) {
            $modal = createBulkProgressModal();
        }

        // Initialize progress tracking
        $modal.data('startTime', Date.now());

        // Update initial state
        $modal.find('.bbai-bulk-progress__log').empty();
        state = syncBulkProgressState($modal, {
            total: total,
            processed: Math.max(0, parseInt(current, 10) || 0),
            failed: 0,
            skipped: 0,
            source: String($modal.data('source') || 'generate-missing'),
            activeTitle: label || __('Processing Images...', 'beepbeep-ai-alt-text-generator'),
            quotaBlocked: false,
            quotaError: null,
            complete: false,
            ctaShownTracked: false
        });

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        startBulkProgressHelperRotation($modal);
        updateBulkProgress(state.current, state.total);
    }

    /**
     * Rotate helper messages during bulk generation
     */
    function startBulkProgressHelperRotation($modal) {
        stopBulkProgressHelperRotation($modal);
        var helpers = [
            __('Preparing images…', 'beepbeep-ai-alt-text-generator'),
            __('Starting generation…', 'beepbeep-ai-alt-text-generator'),
            __('Analyzing image content', 'beepbeep-ai-alt-text-generator'),
            __('Writing accessible descriptions', 'beepbeep-ai-alt-text-generator'),
            __('Optimizing for SEO', 'beepbeep-ai-alt-text-generator')
        ];
        var idx = 0;
        var $helper = $modal.find('.bbai-bulk-progress__helper');
        if (!$helper.length) return;
        $helper.text(helpers[0]).prop('hidden', false);
        var interval = setInterval(function() {
            idx = (idx + 1) % helpers.length;
            $helper.text(helpers[idx]);
        }, 2500);
        $modal.data('bbaiHelperInterval', interval);
    }

    function stopBulkProgressHelperRotation($modal) {
        if (!$modal || !$modal.length) return;
        var interval = $modal.data('bbaiHelperInterval');
        if (interval) {
            clearInterval(interval);
            $modal.removeData('bbaiHelperInterval');
        }
        $modal.find('.bbai-bulk-progress__helper').text('').prop('hidden', true);
    }

    /**
     * Create bulk progress modal HTML
     */
    function createBulkProgressModal() {
        var modalHtml =
            '<div id="bbai-bulk-progress-modal" class="bbai-bulk-progress-modal">' +
            '    <div class="bbai-bulk-progress-modal__overlay"></div>' +
            '    <div class="bbai-bulk-progress-modal__content">' +
            '        <div class="bbai-bulk-progress__header">' +
            '            <div class="bbai-bulk-progress__header-text">' +
            '                <h2 class="bbai-bulk-progress__title">' + escapeHtml(__('Generating ALT text...', 'beepbeep-ai-alt-text-generator')) + '</h2>' +
            '                <p class="bbai-bulk-progress__subtitle" aria-live="polite"></p>' +
            '                <p class="bbai-bulk-progress__helper" aria-live="polite" hidden></p>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__header-actions">' +
            '                <button type="button" class="button bbai-bulk-progress__minimize" data-bbai-bulk-progress-minimize="1">' +
            escapeHtml(__('Continue in background', 'beepbeep-ai-alt-text-generator')) +
            '</button>' +
            '                <button type="button" class="bbai-bulk-progress__close" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">&times;</button>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-bulk-progress__body">' +
            '            <div class="bbai-bulk-progress__stats">' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">' + escapeHtml(__('Progress', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                    <span class="bbai-bulk-progress__stat-value">' +
            '                        <span class="bbai-bulk-progress__current">0</span> / ' +
            '                        <span class="bbai-bulk-progress__total">0</span>' +
            '                    </span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">' + escapeHtml(__('Percentage', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__percentage">0%</span>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__stat">' +
            '                    <span class="bbai-bulk-progress__stat-label">' + escapeHtml(__('Estimated Time', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                    <span class="bbai-bulk-progress__stat-value bbai-bulk-progress__eta">' + escapeHtml(__('Calculating...', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__bar-container">' +
            '                <div class="bbai-bulk-progress__bar">' +
            '                    <div class="bbai-bulk-progress__bar-fill" style="width: 0%"></div>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__summary" aria-live="polite">' +
            '                <div class="bbai-bulk-progress__summary-stats">' +
            '                    <div class="bbai-bulk-progress__summary-item">' +
            '                        <span class="bbai-bulk-progress__summary-label">' + escapeHtml(__('Processed', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                        <strong class="bbai-bulk-progress__summary-value" data-bbai-bulk-progress-processed>0</strong>' +
            '                    </div>' +
            '                    <div class="bbai-bulk-progress__summary-item">' +
            '                        <span class="bbai-bulk-progress__summary-label">' + escapeHtml(__('Skipped', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                        <strong class="bbai-bulk-progress__summary-value" data-bbai-bulk-progress-skipped>0</strong>' +
            '                    </div>' +
            '                    <div class="bbai-bulk-progress__summary-item">' +
            '                        <span class="bbai-bulk-progress__summary-label">' + escapeHtml(__('Pending', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                        <strong class="bbai-bulk-progress__summary-value" data-bbai-bulk-progress-pending>0</strong>' +
            '                    </div>' +
            '                    <div class="bbai-bulk-progress__summary-item bbai-bulk-progress__summary-item--warning" data-bbai-bulk-progress-failed-stat hidden>' +
            '                        <span class="bbai-bulk-progress__summary-label">' + escapeHtml(__('Failed', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '                        <strong class="bbai-bulk-progress__summary-value" data-bbai-bulk-progress-failed>0</strong>' +
            '                    </div>' +
            '                </div>' +
            '                <div class="bbai-bulk-progress__summary-callout" data-bbai-bulk-progress-callout hidden>' +
            '                    <div class="bbai-bulk-progress__summary-callout-copy">' +
            '                        <p class="bbai-bulk-progress__summary-callout-title" data-bbai-bulk-progress-callout-title></p>' +
            '                        <p class="bbai-bulk-progress__summary-callout-text" data-bbai-bulk-progress-callout-text></p>' +
            '                        <p class="bbai-bulk-progress__summary-callout-support" data-bbai-bulk-progress-callout-support></p>' +
            '                    </div>' +
            '                    <div class="bbai-bulk-progress__summary-callout-actions">' +
            '                        <button type="button" class="button button-primary" data-bbai-bulk-progress-cta></button>' +
            '                        <button type="button" class="button" data-bbai-bulk-progress-library hidden></button>' +
            '                    </div>' +
            '                </div>' +
            '            </div>' +
            '            <div class="bbai-bulk-progress__log-container">' +
            '                <h3 class="bbai-bulk-progress__log-title">' + escapeHtml(__('Processing Log', 'beepbeep-ai-alt-text-generator')) + '</h3>' +
            '                <div class="bbai-bulk-progress__log"></div>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        var $modal = $('#bbai-bulk-progress-modal');

        // Close — hides modal, job continues in background
        $modal.find('.bbai-bulk-progress__close').on('click', function() {
            minimizeBulkProgress('close');
        });

        $modal.on('click', '[data-bbai-bulk-progress-minimize]', function() {
            minimizeBulkProgress('explicit_button');
        });

        // Overlay click also minimizes
        $modal.find('.bbai-bulk-progress-modal__overlay').on('click', function() {
            minimizeBulkProgress('overlay');
        });

        $modal.on('click', '[data-bbai-bulk-progress-cta]', function() {
            var state = getBulkProgressState($modal);
            var ctaConfig = getBulkProgressQuotaCtaConfig(state);

            dispatchAnalyticsEvent(
                'batch_generation_cta_clicked',
                buildBulkProgressAnalyticsPayload(state, {
                    cta_label: ctaConfig.label
                })
            );

            if (ctaConfig.action === 'signup') {
                minimizeBulkProgress('quota_cta');
                openAuthSignupModal();
                return;
            }

            if (ctaConfig.action === 'upgrade') {
                minimizeBulkProgress('quota_cta');
                openUpgradeModal(ctaConfig.usage || getUsageSnapshot(null));
                return;
            }

            if (ctaConfig.libraryUrl) {
                minimizeBulkProgress('quota_cta');
                window.location.href = ctaConfig.libraryUrl;
            }
        });

        $modal.on('click', '[data-bbai-bulk-progress-library]', function() {
            var ctaConfig = getBulkProgressQuotaCtaConfig(getBulkProgressState($modal));
            if (ctaConfig.libraryUrl) {
                minimizeBulkProgress('quota_cta');
                window.location.href = ctaConfig.libraryUrl;
            }
        });

        return $modal;
    }

    /**
     * Update bulk progress bar with detailed stats
     */
    function updateBulkProgress(current, total, imageTitle, progressOptions) {
        progressOptions = progressOptions || {};
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var backendPercent = progressOptions.backendPercent;
        var percentage;
        if (typeof backendPercent === 'number' && !isNaN(backendPercent) && backendPercent >= 0) {
            percentage = Math.min(100, Math.round(backendPercent));
        } else {
            percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        }
        var startTime = $modal.data('startTime') || Date.now();
        var elapsed = (Date.now() - startTime) / 1000; // seconds

        // Calculate ETA (avoid vague "Calculating…" while nothing has finished yet)
        var bulkState = getBulkProgressState($modal);
        var eta = __('Waiting for first image…', 'beepbeep-ai-alt-text-generator');
        if (bulkState.complete && bulkState.quotaBlocked) {
            eta = __('Stopped', 'beepbeep-ai-alt-text-generator');
        } else if (total > 0 && current >= total) {
            eta = __('Done', 'beepbeep-ai-alt-text-generator');
        } else if (current > 0 && elapsed > 0) {
            var avgTimePerImage = elapsed / current;
            var remaining = total - current;
            var etaSeconds = remaining * avgTimePerImage;

            if (etaSeconds < 60) {
                eta = sprintf(__('%ds', 'beepbeep-ai-alt-text-generator'), Math.ceil(etaSeconds));
            } else if (etaSeconds < 3600) {
                eta = sprintf(__('%dm', 'beepbeep-ai-alt-text-generator'), Math.ceil(etaSeconds / 60));
            } else {
                var hours = Math.floor(etaSeconds / 3600);
                var mins = Math.ceil((etaSeconds % 3600) / 60);
                eta = sprintf(__('%dh %dm', 'beepbeep-ai-alt-text-generator'), hours, mins);
            }
        }

        // Update stats (total must stay in sync; this element is not set elsewhere on dashboard flow)
        $modal.find('.bbai-bulk-progress__current').text(current);
        $modal.find('.bbai-bulk-progress__total').text(total);
        $modal.find('.bbai-bulk-progress__percentage').text(percentage + '%');
        $modal.find('.bbai-bulk-progress__eta').text(eta);
        $modal.find('.bbai-bulk-progress__bar-fill').css('width', percentage + '%');

        // Add log entry if image title provided
        if (imageTitle) {
            var timestamp = new Date().toLocaleTimeString();
            var logEntry =
                '<div class="bbai-bulk-progress__log-entry">' +
                '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
                '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
                '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(imageTitle) + '</span>' +
                '</div>';

            var $log = $modal.find('.bbai-bulk-progress__log');
            $log.append(logEntry);

            // Auto-scroll to bottom
            $log.scrollTop($log[0].scrollHeight);
        }

        renderBulkProgressState($modal, getBulkProgressState($modal));
    }

    /**
     * Add error log entry
     */
    function logBulkProgressError(errorMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--error">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✗</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(errorMessage || __('An error occurred', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Add success log entry
     */
    function logBulkProgressSuccess(successMessage) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var timestamp = new Date().toLocaleTimeString();
        var logEntry =
            '<div class="bbai-bulk-progress__log-entry bbai-bulk-progress__log-entry--success">' +
            '    <span class="bbai-bulk-progress__log-time">' + timestamp + '</span>' +
            '    <span class="bbai-bulk-progress__log-icon">✓</span>' +
            '    <span class="bbai-bulk-progress__log-text">' + escapeHtml(successMessage || __('Success', 'beepbeep-ai-alt-text-generator')) + '</span>' +
            '</div>';

        var $log = $modal.find('.bbai-bulk-progress__log');
        $log.append(logEntry);
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Update bulk progress modal title
     */
    function updateBulkProgressTitle(title) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var state = getBulkProgressState($modal);
        if (!state.complete && !state.quotaBlocked) {
            state.activeTitle = title;
            saveBulkProgressState($modal, state);
        }
        $modal.find('.bbai-bulk-progress__title').text(title);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Anonymous / guest-trial users: closing the bulk modal should not leave the floating job widget
     * or terminal job state on screen (logged-in users keep the widget while a background job runs).
     */
    function dismissGuestTrialBulkJobChrome() {
        var dashRoot = document.querySelector('[data-bbai-dashboard-root="1"]');
        var appCfg = window.BBAI_DASH || window.BBAI || {};
        var isGuestTrial = (dashRoot && dashRoot.getAttribute('data-bbai-is-guest-trial') === '1') ||
            (dashRoot && dashRoot.getAttribute('data-bbai-auth-state') === 'anonymous') ||
            (appCfg.auth_state === 'anonymous') ||
            !!(appCfg.anonymous && appCfg.anonymous.is_guest_trial) ||
            !!(window.bbai_ajax && window.bbai_ajax.is_guest_trial);

        if (!isGuestTrial) {
            return;
        }

        if (window.bbaiJobState) {
            var st = window.bbaiJobState.getState();
            if (!st.running) {
                window.bbaiJobState.reset();
            }
        }

        $('#bbai-job-widget').prop('hidden', true);
    }

    /**
     * Hide bulk progress bar
     */
    /**
     * Minimize — hides modal but job continues. Widget becomes visible.
     */
    function minimizeBulkProgress(minimizeVia) {
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            $modal.removeClass('active');
        }
        clearBodyScrollLocks();
        ensureBbaiDashboardMainVisible();
        if (window.bbaiJobState) {
            window.bbaiJobState.update({ modalVisible: false });
        }
        if ($modal.length && window.bbaiJobState && window.bbaiJobState.getState().running) {
            dispatchAnalyticsEvent('bulk_generation_minimized', {
                source: getAnalyticsPageSource(),
                via: minimizeVia || 'unknown'
            });
        }
        dismissGuestTrialBulkJobChrome();
    }

    function hideBulkProgress() {
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            stopBulkProgressHelperRotation($modal);
            $modal.removeClass('active');
        }
        clearBodyScrollLocks();
        ensureBbaiDashboardMainVisible();
        if (window.bbaiJobState) {
            window.bbaiJobState.update({ modalVisible: false });
        }
        dismissGuestTrialBulkJobChrome();
    }

    /**
     * Global safety function to restore page scrolling
     * Can be called if scrolling gets stuck after modal operations
     */
    window.restorePageScroll = function() {
        clearBodyScrollLocks();
        // Force-close known overlays that can trap pointer events even when invisible.
        $('.bbai-regenerate-modal.active, .bbai-bulk-progress-modal.active, #bbai-modal-success.active').removeClass('active');
        $('#bbai-modal-overlay').removeClass('is-visible').css('display', 'none').attr('aria-hidden', 'true');
        $('#bbai-upgrade-modal').removeClass('active is-visible').css('display', 'none').attr('aria-hidden', 'true');
    };

    window.bbaiEmergencyUnlock = function() {
        window.restorePageScroll();
    };

    /**
     * Create and show success modal
     */
    function createSuccessModal() {
        var adminBaseUrl = (window.bbai_ajax && window.bbai_ajax.admin_url) || '';
        var libraryUrl = adminBaseUrl ? (adminBaseUrl + '?page=bbai&tab=library') : '#';
        var modalHtml =
            '<div id="bbai-modal-success" class="bbai-modal-success" role="dialog" aria-modal="true" aria-labelledby="bbai-modal-success-title">' +
            '    <div class="bbai-modal-success__overlay"></div>' +
            '    <div class="bbai-modal-success__content">' +
            '        <button type="button" class="bbai-modal-success__close" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">×</button>' +
            '        <div class="bbai-modal-success__header">' +
            '            <div class="bbai-modal-success__badge">' +
            '                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
            '                    <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
            '                </svg>' +
            '            </div>' +
            '            <h2 id="bbai-modal-success-title" class="bbai-modal-success__title">' + escapeHtml(__('Alt Text Generated Successfully', 'beepbeep-ai-alt-text-generator')) + '</h2>' +
            '            <p class="bbai-modal-success__subtitle">' + escapeHtml(__('Your images have been processed and are ready to review.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '        </div>' +
            '        <div class="bbai-modal-success__stats">' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="processed">0</div>' +
            '                <div class="bbai-modal-success__stat-label">' + escapeHtml(__('Images Processed', 'beepbeep-ai-alt-text-generator')) + '</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="time">0</div>' +
            '                <div class="bbai-modal-success__stat-label">' + escapeHtml(__('Time Saved', 'beepbeep-ai-alt-text-generator')) + '</div>' +
            '            </div>' +
            '            <div class="bbai-modal-success__stat-card">' +
            '                <div class="bbai-modal-success__stat-number" data-stat="confidence">0%</div>' +
            '                <div class="bbai-modal-success__stat-label">' + escapeHtml(__('AI Confidence', 'beepbeep-ai-alt-text-generator')) + '</div>' +
            '            </div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__summary" data-summary-type="success">' +
            '            <div class="bbai-modal-success__summary-icon">✓</div>' +
            '            <div class="bbai-modal-success__summary-text">' + escapeHtml(__('Nice work — your media library is looking great.', 'beepbeep-ai-alt-text-generator')) + '</div>' +
            '        </div>' +
            '        <div class="bbai-modal-success__actions">' +
            '            <a href="' + escapeHtml(libraryUrl) + '" class="bbai-modal-success__btn bbai-modal-success__btn--primary">' + escapeHtml(__('View ALT Library →', 'beepbeep-ai-alt-text-generator')) + '</a>' +
            '            <button type="button" class="bbai-modal-success__btn bbai-modal-success__btn--secondary" data-action="view-warnings" style="display: none;">' + escapeHtml(__('View Warnings', 'beepbeep-ai-alt-text-generator')) + '</button>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        var $existing = $('#bbai-modal-success');
        if ($existing.length) {
            $existing.remove();
        }

        $('body').append(modalHtml);
        var $modal = $('#bbai-modal-success');

        // Close handlers
        $modal.find('.bbai-modal-success__close, .bbai-modal-success__overlay').on('click', function() {
            hideSuccessModal();
        });

        // View Warnings button handler
        $modal.find('[data-action="view-warnings"]').on('click', function() {
            hideSuccessModal();
            // Show the ALT Library tab with filters applied
            if (libraryUrl && libraryUrl !== '#') {
                window.location.href = libraryUrl;
            }
        });

        // ESC key handler
        $(document).on('keydown.opptiai-success-modal', function(e) {
            if (e.keyCode === 27 && $modal.hasClass('active')) {
                hideSuccessModal();
            }
        });

        return $modal;
    }

    /**
     * Show success modal with stats
     */
    function showSuccessModal(data) {
        var $modal = $('#bbai-modal-success');
        if (!$modal.length) {
            $modal = createSuccessModal();
        }

        // Update stats
        $modal.find('[data-stat="processed"]').text(data.processed || 0);
        var defaultTime = sprintf(_n('%d hour', '%d hours', 0, 'beepbeep-ai-alt-text-generator'), 0);
        $modal.find('[data-stat="time"]').text(data.timeSaved || defaultTime);
        $modal.find('[data-stat="confidence"]').text((data.confidence || 0) + '%');

        // Update summary based on failures
        var $summary = $modal.find('.bbai-modal-success__summary');
        var $warningsBtn = $modal.find('[data-action="view-warnings"]');

        if (data.failures > 0) {
            $summary.attr('data-summary-type', 'warning');
            $summary.find('.bbai-modal-success__summary-icon').text('⚠');
            $summary.find('.bbai-modal-success__summary-text').text(__('Some images generated with warnings — review details below.', 'beepbeep-ai-alt-text-generator'));
            $warningsBtn.show();
        } else {
            $summary.attr('data-summary-type', 'success');
            $summary.find('.bbai-modal-success__summary-icon').text('✓');
            $summary.find('.bbai-modal-success__summary-text').text(__('Nice work — your media library is looking great.', 'beepbeep-ai-alt-text-generator'));
            $warningsBtn.hide();
        }

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        // Focus management
        setTimeout(function() {
            $modal.find('.bbai-modal-success__close').focus();
        }, 100);
    }

    /**
     * Hide success modal
     */
    function hideSuccessModal() {
        var $modal = $('#bbai-modal-success');
        if ($modal.length) {
            $modal.removeClass('active');
            clearBodyScrollLocks();
            $(document).off('keydown.opptiai-success-modal');
        }
    }

    /**
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info';
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast({
                type: type,
                message: message,
                duration: 4500
            });
            return;
        }

        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').first().prepend($notice);

        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
    }


    /**
     * License Management Functions
     */

    // Handle license activation form submission
    function handleLicenseActivation(e) {
        e.preventDefault();

        var $form = $('#license-activation-form');
        var $input = $('#license-key-input');
        var $button = $('#activate-license-btn');
        var $status = $('#license-activation-status');
        var nonce = $('#license-nonce').val();

        var licenseKey = $input.val().trim();

        if (!licenseKey) {
            showLicenseStatus('error', __('Please enter a license key', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        // Disable form
        $button.prop('disabled', true).text(__('Activating...', 'beepbeep-ai-alt-text-generator'));
        $input.prop('disabled', true);

        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) || '';
        if (!ajaxUrl) {
            showLicenseStatus('error', __('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'));
            $button.prop('disabled', false).text(__('Activate License', 'beepbeep-ai-alt-text-generator'));
            $input.prop('disabled', false);
            return;
        }

        // Make AJAX request
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'beepbeepai_activate_license',
                nonce: nonce,
                license_key: licenseKey
            },
            success: function(response) {
                if (response.success) {
                    showLicenseStatus('success', response.data.message || __('License activated successfully!', 'beepbeep-ai-alt-text-generator'));

                    // Reload page after 1 second to show activated state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    showLicenseStatus('error', response.data.message || __('Failed to activate license', 'beepbeep-ai-alt-text-generator'));
                    $button.prop('disabled', false).text(__('Activate License', 'beepbeep-ai-alt-text-generator'));
                    $input.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                showLicenseStatus('error', sprintf(__('Network error: %s', 'beepbeep-ai-alt-text-generator'), error));
                $button.prop('disabled', false).text(__('Activate License', 'beepbeep-ai-alt-text-generator'));
                $input.prop('disabled', false);
            }
        });
    }

    // Handle license deactivation
    function handleLicenseDeactivation(e) {
        e.preventDefault();

        if (!confirm(__('Are you sure you want to deactivate this license? You will need to reactivate it to continue using the shared quota.', 'beepbeep-ai-alt-text-generator'))) {
            return;
        }

        var $button = $(this);
        var nonce = $('#license-nonce').val();

        // Disable button
        $button.prop('disabled', true).text(__('Deactivating...', 'beepbeep-ai-alt-text-generator'));

        var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) || '';
        if (!ajaxUrl) {
            window.bbaiModal.error(__('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator'));
            $button.prop('disabled', false).text(__('Deactivate License', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        // Make AJAX request
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'beepbeepai_deactivate_license',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    window.bbaiModal.show({
                        type: 'success',
                        title: __('Success', 'beepbeep-ai-alt-text-generator'),
                        message: response.data.message || __('License deactivated successfully', 'beepbeep-ai-alt-text-generator'),
                        onClose: function() {
                            // Reload page to show deactivated state
                            window.location.reload();
                        }
                    });
                } else {
                    window.bbaiModal.error(sprintf(__('Error: %s', 'beepbeep-ai-alt-text-generator'), (response.data.message || __('Failed to deactivate license', 'beepbeep-ai-alt-text-generator'))));
                    $button.prop('disabled', false).text(__('Deactivate License', 'beepbeep-ai-alt-text-generator'));
                }
            },
            error: function(xhr, status, error) {
                window.bbaiModal.error(sprintf(__('Network error: %s', 'beepbeep-ai-alt-text-generator'), error));
                $button.prop('disabled', false).text(__('Deactivate License', 'beepbeep-ai-alt-text-generator'));
            }
        });
    }

    // Show status message in license activation form
    function showLicenseStatus(type, message) {
        var $status = $('#license-activation-status');
        var iconHtml = '';
        var bgColor = '';
        var textColor = '';

        if (type === 'success') {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M6 10L9 13L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            bgColor = '#d1fae5';
            textColor = '#065f46';
        } else {
            iconHtml = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="vertical-align: middle; margin-right: 8px;"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
            bgColor = '#fee2e2';
            textColor = '#991b1b';
        }

        $status.html(
            '<div style="padding: 12px; background: ' + bgColor + '; color: ' + textColor + '; border-radius: 4px; font-size: 14px;">' +
            iconHtml + message +
            '</div>'
        ).show();
    }

    /**
     * Setup Wizard (first-run dashboard flow)
     */
    var bbaiSetupWizard = (function() {
        var root = null;
        var dashboardContainer = null;
        var listenersBound = false;
        var initialized = false;
        var modalOverlay = null;
        var modalOverflowBackup = '';

        var state = {
            visible: false,
            currentStep: 1,
            completed: true,
            previewLimit: 3,
            applyLimit: 10,
            candidateLimit: 25,
            wooActive: false,
            wooContextEnabled: false,
            scan: {
                hasScanned: false,
                loading: false,
                totalScanned: 0,
                missingCount: 0,
                samples: [],
                candidateIds: []
            },
            previews: {
                loading: false,
                generated: false,
                items: [],
                errors: []
            },
            previewApproved: false,
            apply: {
                running: false,
                done: false,
                targetIds: [],
                processed: 0,
                updated: 0,
                failed: []
            }
        };

        function debugState(eventName, payload) {
            if (window.BBAI_DEBUG === true && window.console && typeof window.console.log === 'function') {
                window.console.log('[BBAI Wizard]', eventName, payload || {});
            }
        }

        function toInt(value, fallback) {
            var parsed = parseInt(value, 10);
            return isNaN(parsed) ? fallback : parsed;
        }

        function getWizardConfig() {
            return (config && config.wizard && typeof config.wizard === 'object') ? config.wizard : {};
        }

        function getWizardTargetCount() {
            var missingCount = toInt(state.scan.missingCount, 0);
            return Math.max(0, Math.min(state.applyLimit, missingCount || state.scan.candidateIds.length || 0));
        }

        function getApplyPercent() {
            var total = state.apply.targetIds.length || getWizardTargetCount();
            if (!total) {
                return 0;
            }
            return Math.max(0, Math.min(100, Math.round((state.apply.processed / total) * 100)));
        }

        function isActionDisabled(actionNode) {
            return !!(actionNode && actionNode.getAttribute('aria-disabled') === 'true');
        }

        function closeCustomModal() {
            if (modalOverlay && modalOverlay.parentNode) {
                modalOverlay.parentNode.removeChild(modalOverlay);
            }
            modalOverlay = null;

            if (document.body) {
                document.body.style.overflow = modalOverflowBackup || '';
            }
            if (document.documentElement) {
                document.documentElement.style.overflow = '';
            }
            modalOverflowBackup = '';
        }

        function showModal(options) {
            var settings = options || {};
            var title = settings.title || __('Notice', 'beepbeep-ai-alt-text-generator');
            var body = settings.body || '';
            var primaryText = settings.primaryText || __('OK', 'beepbeep-ai-alt-text-generator');
            var onPrimary = typeof settings.onPrimary === 'function' ? settings.onPrimary : function() {};

            closeCustomModal();

            if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                window.bbaiModal.show({
                    type: 'info',
                    title: title,
                    message: body,
                    buttons: [
                        {
                            text: primaryText,
                            primary: true,
                            action: function() {
                                window.bbaiModal.close();
                                onPrimary();
                            }
                        },
                        {
                            text: __('Close', 'beepbeep-ai-alt-text-generator'),
                            primary: false,
                            action: function() {
                                window.bbaiModal.close();
                            }
                        }
                    ]
                });
                return;
            }

            modalOverflowBackup = document.body ? document.body.style.overflow : '';
            if (document.body) {
                document.body.style.overflow = 'hidden';
            }

            modalOverlay = document.createElement('div');
            modalOverlay.className = 'bbai-setup-modal-overlay';
            modalOverlay.innerHTML =
                '<div class="bbai-setup-modal" role="dialog" aria-modal="true" aria-label="' + escapeHtml(title) + '">' +
                '    <h3 class="bbai-setup-modal__title">' + escapeHtml(title) + '</h3>' +
                '    <p class="bbai-setup-modal__body">' + escapeHtml(body) + '</p>' +
                '    <div class="bbai-setup-modal__actions">' +
                '        <button type="button" class="button button-secondary" data-modal-action="close">' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '</button>' +
                '        <button type="button" class="button button-primary" data-modal-action="primary">' + escapeHtml(primaryText) + '</button>' +
                '    </div>' +
                '</div>';

            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    closeCustomModal();
                }

                var action = e.target.closest('[data-modal-action]');
                if (!action) {
                    return;
                }

                e.preventDefault();
                var actionType = action.getAttribute('data-modal-action');
                if (actionType === 'primary') {
                    closeCustomModal();
                    onPrimary();
                    return;
                }
                closeCustomModal();
            });

            document.body.appendChild(modalOverlay);
        }

        function apiRequest(action, payload) {
            return new Promise(function(resolve, reject) {
                var ajaxUrl = (window.bbai_ajax && (window.bbai_ajax.ajaxurl || window.bbai_ajax.ajax_url)) ||
                    (window.bbai_env && window.bbai_env.ajax_url) ||
                    '';
                if (!ajaxUrl) {
                    reject(new Error(__('AJAX endpoint unavailable.', 'beepbeep-ai-alt-text-generator')));
                    return;
                }

                var nonce = (window.bbai_ajax && window.bbai_ajax.nonce) || config.nonce || '';
                var params = new URLSearchParams();
                params.append('action', action);
                params.append('nonce', nonce);

                var data = payload || {};
                Object.keys(data).forEach(function(key) {
                    var value = data[key];
                    if (value === undefined || value === null) {
                        return;
                    }
                    if (Array.isArray(value)) {
                        value.forEach(function(item) {
                            params.append(key + '[]', item);
                        });
                        return;
                    }
                    params.append(key, value);
                });

                var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var timeoutId = window.setTimeout(function() {
                    if (controller) {
                        controller.abort();
                    }
                }, 20000);

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: params.toString(),
                    signal: controller ? controller.signal : undefined
                })
                    .then(function(response) {
                        return response.text().then(function(rawText) {
                            var parsed = null;
                            try {
                                parsed = JSON.parse(rawText);
                            } catch (jsonError) {
                                throw new Error(__('Unexpected response from server.', 'beepbeep-ai-alt-text-generator'));
                            }

                            if (!parsed || typeof parsed.success === 'undefined') {
                                throw new Error(__('Invalid server response.', 'beepbeep-ai-alt-text-generator'));
                            }

                            if (!parsed.success) {
                                var errorPayload = parsed.data || {};
                                var error = new Error(errorPayload.message || __('Request failed.', 'beepbeep-ai-alt-text-generator'));
                                Object.keys(errorPayload).forEach(function(key) {
                                    error[key] = errorPayload[key];
                                });
                                throw error;
                            }

                            return parsed.data || {};
                        });
                    })
                    .then(function(data) {
                        window.clearTimeout(timeoutId);
                        resolve(data);
                    })
                    .catch(function(error) {
                        window.clearTimeout(timeoutId);
                        if (error && error.name === 'AbortError') {
                            reject(new Error(__('Request timed out. Please try again.', 'beepbeep-ai-alt-text-generator')));
                            return;
                        }
                        reject(error);
                    });
            });
        }

        function resetRunState() {
            state.scan = {
                hasScanned: false,
                loading: false,
                totalScanned: 0,
                missingCount: 0,
                samples: [],
                candidateIds: []
            };
            state.previews = {
                loading: false,
                generated: false,
                items: [],
                errors: []
            };
            state.previewApproved = false;
            state.apply = {
                running: false,
                done: false,
                targetIds: [],
                processed: 0,
                updated: 0,
                failed: []
            };
            state.currentStep = 1;
        }

        function updateLayoutVisibility() {
            var launchLinks = document.querySelectorAll('[data-action="bbai-run-setup-wizard"]');
            launchLinks.forEach(function(link) {
                link.style.display = state.visible ? 'none' : '';
            });

            if (root) {
                root.hidden = !state.visible;
            }
            if (dashboardContainer) {
                dashboardContainer.style.display = state.visible ? 'none' : '';
            }
        }

        function renderStep1() {
            var html = '';
            html += '<div class="bbai-setup-wizard__section">';
            html += '<h3>' + escapeHtml(__('Step 1: Scan your media library', 'beepbeep-ai-alt-text-generator')) + '</h3>';
            html += '<p>' + escapeHtml(__('Find image attachments that are missing alt text.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            html += '<div class="bbai-setup-wizard__actions">';
            html += '  <button type="button" class="button button-primary" data-wizard-action="scan">' + escapeHtml(state.scan.loading ? __('Scanning…', 'beepbeep-ai-alt-text-generator') : __('Scan my Media Library', 'beepbeep-ai-alt-text-generator')) + '</button>';
            html += '</div>';
            html += '</div>';

            if (!state.scan.hasScanned) {
                return html;
            }

            html += '<div class="bbai-setup-wizard__section">';
            html += '<div class="bbai-setup-stats">';
            html += '  <div class="bbai-setup-stats__card"><span class="bbai-setup-stats__label">' + escapeHtml(__('Total images scanned', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-setup-stats__value">' + escapeHtml(String(state.scan.totalScanned)) + '</span></div>';
            html += '  <div class="bbai-setup-stats__card"><span class="bbai-setup-stats__label">' + escapeHtml(__('Missing alt text', 'beepbeep-ai-alt-text-generator')) + '</span><span class="bbai-setup-stats__value">' + escapeHtml(String(state.scan.missingCount)) + '</span></div>';
            html += '</div>';

            if (state.scan.missingCount > 0 && state.scan.samples.length) {
                html += '<div class="bbai-setup-thumbs">';
                state.scan.samples.slice(0, 3).forEach(function(sample) {
                    html += '<div class="bbai-setup-thumb">';
                    html += '  <img src="' + escapeHtml(sample.thumb || '') + '" alt="">';
                    html += '  <p class="bbai-setup-thumb__title">' + escapeHtml(sample.title || '') + '</p>';
                    html += '</div>';
                });
                html += '</div>';
            } else {
                html += '<div class="notice notice-success"><p>' + escapeHtml(__('Everything looks good. No missing alt text found.', 'beepbeep-ai-alt-text-generator')) + '</p></div>';
            }
            html += '</div>';

            return html;
        }

        function renderStep2() {
            var html = '';
            var noMissing = state.scan.hasScanned && state.scan.missingCount <= 0;
            html += '<div class="bbai-setup-wizard__section">';
            html += '<h3>' + escapeHtml(__('Step 2: Preview generated alt text', 'beepbeep-ai-alt-text-generator')) + '</h3>';
            html += '<p>' + escapeHtml(__('Generate sample alt text for a few images before writing anything to your library.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            html += '<div class="bbai-setup-wizard__actions">';
            if (noMissing) {
                html += '<button type="button" class="button button-secondary" data-wizard-action="go-apply">' + escapeHtml(__('Continue', 'beepbeep-ai-alt-text-generator')) + '</button>';
            } else {
                html += '<button type="button" class="button button-primary" data-wizard-action="preview">' + escapeHtml(state.previews.loading ? __('Generating…', 'beepbeep-ai-alt-text-generator') : __('Generate previews', 'beepbeep-ai-alt-text-generator')) + '</button>';
            }
            html += '</div>';
            html += '</div>';

            if (noMissing) {
                html += '<div class="notice notice-info"><p>' + escapeHtml(__('No missing images found, so there are no previews to generate.', 'beepbeep-ai-alt-text-generator')) + '</p></div>';
                return html;
            }

            if (!state.previews.generated) {
                return html;
            }

            html += '<div class="bbai-setup-wizard__section">';
            if (state.previews.items.length) {
                html += '<div class="bbai-setup-preview-grid">';
                state.previews.items.forEach(function(item) {
                    html += '<div class="bbai-setup-preview-card">';
                    html += '  <img src="' + escapeHtml(item.thumb || '') + '" alt="">';
                    html += '  <p class="bbai-setup-preview-card__title">' + escapeHtml(item.title || '') + '</p>';
                    html += '  <p class="bbai-setup-preview-card__alt">' + escapeHtml(item.alt_text || '') + '</p>';
                    html += '</div>';
                });
                html += '</div>';
            } else {
                html += '<div class="notice notice-warning"><p>' + escapeHtml(__('No previews were returned. You can continue and apply directly.', 'beepbeep-ai-alt-text-generator')) + '</p></div>';
            }

            if (state.previews.errors.length) {
                html += '<div class="notice notice-warning"><p>' + escapeHtml(__('Some preview requests failed. You can still continue.', 'beepbeep-ai-alt-text-generator')) + '</p></div>';
            }

            html += '<p><label><input type="checkbox" data-wizard-field="approve_preview" ' + (state.previewApproved ? 'checked' : '') + '> ' +
                escapeHtml(__('These look good, apply to my library', 'beepbeep-ai-alt-text-generator')) +
                '</label></p>';
            html += '<p class="description">' + escapeHtml(__('You can edit any result later in Media Library.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            html += '<div class="bbai-setup-wizard__actions">';
            html += '<button type="button" class="button button-primary" data-wizard-action="go-apply" ' + (state.previewApproved ? '' : 'aria-disabled="true"') + '>' + escapeHtml(__('Continue to apply', 'beepbeep-ai-alt-text-generator')) + '</button>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        function renderStep3() {
            var html = '';
            var targetCount = state.apply.targetIds.length || getWizardTargetCount();
            var progressLabel = sprintf(
                _n('%1$d/%2$d updated', '%1$d/%2$d updated', targetCount || 0, 'beepbeep-ai-alt-text-generator'),
                state.apply.updated,
                targetCount || 0
            );

            html += '<div class="bbai-setup-wizard__section">';
            html += '<h3>' + escapeHtml(__('Step 3: Apply alt text', 'beepbeep-ai-alt-text-generator')) + '</h3>';
            html += '<p>' + escapeHtml(__('Write generated alt text to your first 10 missing images.', 'beepbeep-ai-alt-text-generator')) + '</p>';

            if (!state.apply.done) {
                html += '<div class="bbai-setup-wizard__actions">';
                html += '<button type="button" class="button button-primary" data-wizard-action="apply" ' + (state.apply.running ? 'aria-disabled="true"' : '') + '>' +
                    escapeHtml(state.apply.running ? __('Applying…', 'beepbeep-ai-alt-text-generator') : sprintf(__('Apply to %d images free', 'beepbeep-ai-alt-text-generator'), targetCount || state.applyLimit)) +
                    '</button>';
                html += '</div>';
            }

            if (state.apply.running || state.apply.processed > 0 || state.apply.done) {
                html += '<div class="bbai-setup-progress">';
                html += '  <p class="bbai-setup-progress__meta">' + escapeHtml(progressLabel) + '</p>';
                html += '  <div class="bbai-setup-progress__track"><div class="bbai-setup-progress__bar" style="width:' + escapeHtml(String(getApplyPercent())) + '%"></div></div>';
                html += '</div>';
            }

            if (state.apply.failed.length) {
                html += '<div class="notice notice-warning"><p>' + escapeHtml(sprintf(_n('%d image failed and was skipped.', '%d images failed and were skipped.', state.apply.failed.length, 'beepbeep-ai-alt-text-generator'), state.apply.failed.length)) + '</p></div>';
            }
            html += '</div>';

            if (!state.apply.done) {
                return html;
            }

            html += '<div class="notice notice-success"><p>' + escapeHtml(__('Alt text was applied successfully.', 'beepbeep-ai-alt-text-generator')) + '</p></div>';
            html += '<ul class="bbai-setup-next-actions">';
            html += '<li><button type="button" class="button-link" data-wizard-action="run-again">' + escapeHtml(__('Run again', 'beepbeep-ai-alt-text-generator')) + '</button></li>';
            html += '<li><button type="button" class="button-link" data-wizard-action="connect-account">' + escapeHtml(__('Connect account for bulk generation', 'beepbeep-ai-alt-text-generator')) + '</button></li>';
            html += '<li><button type="button" class="button-link" data-wizard-action="enable-woo-step">' + escapeHtml(__('Enable WooCommerce mode', 'beepbeep-ai-alt-text-generator')) + '</button></li>';
            html += '</ul>';

            html += '<div class="bbai-setup-wizard__actions">';
            if (state.wooActive) {
                html += '<button type="button" class="button button-primary" data-wizard-action="continue-woo">' + escapeHtml(__('Continue', 'beepbeep-ai-alt-text-generator')) + '</button>';
            } else {
                html += '<button type="button" class="button button-primary" data-wizard-action="finish">' + escapeHtml(__('Finish setup', 'beepbeep-ai-alt-text-generator')) + '</button>';
            }
            html += '</div>';

            return html;
        }

        function renderStep4() {
            var html = '';
            html += '<div class="bbai-setup-wizard__section">';
            html += '<h3>' + escapeHtml(__('Step 4: WooCommerce mode', 'beepbeep-ai-alt-text-generator')) + '</h3>';

            if (state.wooActive) {
                html += '<p>' + escapeHtml(__('Use product title, SKU, and attributes to improve product-image alt text.', 'beepbeep-ai-alt-text-generator')) + '</p>';
                html += '<p><label><input type="checkbox" data-wizard-field="woo_context" ' + (state.wooContextEnabled ? 'checked' : '') + '> ' + escapeHtml(__('Enable WooCommerce product context', 'beepbeep-ai-alt-text-generator')) + '</label></p>';
            } else {
                html += '<p class="description">' + escapeHtml(__('If you run WooCommerce, we can also optimize product images.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            }

            html += '<div class="bbai-setup-wizard__actions">';
            html += '<button type="button" class="button button-primary" data-wizard-action="finish">' + escapeHtml(__('Finish setup', 'beepbeep-ai-alt-text-generator')) + '</button>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        function renderBody() {
            switch (state.currentStep) {
                case 1:
                    return renderStep1();
                case 2:
                    return renderStep2();
                case 3:
                    return renderStep3();
                default:
                    return renderStep4();
            }
        }

        function renderStepper() {
            var labels = [
                __('Scan', 'beepbeep-ai-alt-text-generator'),
                __('Preview', 'beepbeep-ai-alt-text-generator'),
                __('Apply', 'beepbeep-ai-alt-text-generator'),
                __('WooCommerce', 'beepbeep-ai-alt-text-generator')
            ];
            var html = '<ol class="bbai-setup-stepper">';

            labels.forEach(function(label, index) {
                var stepIndex = index + 1;
                var cls = 'bbai-setup-stepper__item';
                if (state.currentStep === stepIndex) {
                    cls += ' is-active';
                } else if (state.currentStep > stepIndex || (stepIndex === 3 && state.apply.done)) {
                    cls += ' is-complete';
                }

                html += '<li class="' + cls + '">';
                html += '<strong>' + escapeHtml(label) + '</strong>';
                html += '</li>';
            });

            html += '</ol>';
            return html;
        }

        function renderWizard() {
            if (!root || !state.visible) {
                return;
            }

            var html = '';
            html += '<div class="bbai-setup-wizard__panel">';
            html += '  <div class="bbai-setup-wizard__header">';
            html += '    <h2 class="bbai-setup-wizard__title">' + escapeHtml(__('Setup Wizard', 'beepbeep-ai-alt-text-generator')) + '</h2>';
            html += '    <p class="bbai-setup-wizard__subtitle">' + escapeHtml(__('Complete your first successful alt-text run in under a minute.', 'beepbeep-ai-alt-text-generator')) + '</p>';
            html += '  </div>';
            html += renderStepper();
            html += renderBody();
            html += '</div>';

            root.innerHTML = html;
        }

        function openConnectAccount() {
            openAuthSignupModal();
        }

        function finishWizard() {
            apiRequest('bbai_complete_setup_wizard', {})
                .then(function() {
                    state.completed = true;
                    state.visible = false;
                    closeCustomModal();
                    updateLayoutVisibility();
                    if (root) {
                        root.innerHTML = '';
                    }
                    showNotification(__('Setup wizard completed.', 'beepbeep-ai-alt-text-generator'), 'success');
                })
                .catch(function(error) {
                    showModal({
                        title: __('Could not finish setup', 'beepbeep-ai-alt-text-generator'),
                        body: error && error.message ? error.message : __('Please try again.', 'beepbeep-ai-alt-text-generator'),
                        primaryText: __('Retry', 'beepbeep-ai-alt-text-generator'),
                        onPrimary: finishWizard
                    });
                });
        }

        function runScan() {
            if (state.scan.loading) {
                return;
            }

            state.scan.loading = true;
            renderWizard();
            debugState('scan_start');
            dispatchAnalyticsEvent('scan_started', {
                source: 'modal'
            });

            apiRequest('bbai_scan_missing_alt', {
                sample_limit: 3,
                candidate_limit: state.candidateLimit
            })
                .then(function(data) {
                    state.scan.loading = false;
                    state.scan.hasScanned = true;
                    state.scan.totalScanned = toInt(data.total_scanned, 0);
                    state.scan.missingCount = toInt(data.missing_alt_count, 0);
                    state.scan.samples = Array.isArray(data.samples) ? data.samples : [];
                    state.scan.candidateIds = Array.isArray(data.candidate_ids) ? data.candidate_ids.map(function(id) {
                        return toInt(id, 0);
                    }).filter(function(id) {
                        return id > 0;
                    }) : [];
                    state.previews.generated = false;
                    state.previews.items = [];
                    state.previews.errors = [];
                    state.previewApproved = false;
                    state.apply.done = false;
                    state.currentStep = 2;
                    debugState('scan_done', {
                        total_scanned: state.scan.totalScanned,
                        missing_alt_count: state.scan.missingCount
                    });
                    dispatchAnalyticsEvent('scan_completed', {
                        source: 'modal',
                        total_images: state.scan.totalScanned,
                        missing_alt_count: state.scan.missingCount
                    });
                    renderWizard();
                })
                .catch(function(error) {
                    state.scan.loading = false;
                    dispatchAnalyticsEvent('scan_failed', {
                        source: 'modal'
                    });
                    renderWizard();
                    showModal({
                        title: __('Scan failed', 'beepbeep-ai-alt-text-generator'),
                        body: error && error.message ? error.message : __('Unable to scan your library right now.', 'beepbeep-ai-alt-text-generator'),
                        primaryText: __('Try again', 'beepbeep-ai-alt-text-generator'),
                        onPrimary: runScan
                    });
                });
        }

        function runPreview() {
            if (state.previews.loading) {
                return;
            }

            var previewIds = state.scan.candidateIds.slice(0, state.previewLimit);
            if (!previewIds.length) {
                showModal({
                    title: __('Nothing to preview', 'beepbeep-ai-alt-text-generator'),
                    body: __('Scan first to find images missing alt text.', 'beepbeep-ai-alt-text-generator')
                });
                return;
            }

            state.previews.loading = true;
            renderWizard();
            debugState('preview_start', { ids: previewIds });

            apiRequest('bbai_generate_preview_alt', {
                attachment_ids: previewIds
            })
                .then(function(data) {
                    state.previews.loading = false;
                    state.previews.generated = true;
                    state.previews.items = Array.isArray(data.previews) ? data.previews : [];
                    state.previews.errors = Array.isArray(data.errors) ? data.errors : [];
                    state.previewApproved = state.scan.missingCount <= 0;
                    debugState('preview_done', { count: state.previews.items.length });
                    renderWizard();
                })
                .catch(function(error) {
                    state.previews.loading = false;
                    renderWizard();
                    showModal({
                        title: __('Preview failed', 'beepbeep-ai-alt-text-generator'),
                        body: error && error.message ? error.message : __('Unable to generate previews right now.', 'beepbeep-ai-alt-text-generator'),
                        primaryText: __('Try again', 'beepbeep-ai-alt-text-generator'),
                        onPrimary: runPreview
                    });
                });
        }

        function finalizeApply() {
            state.apply.running = false;
            state.apply.done = true;
            debugState('apply_done', {
                updated: state.apply.updated,
                total: state.apply.targetIds.length
            });
            renderWizard();
        }

        function runApplyBatchLoop() {
            var total = state.apply.targetIds.length;
            if (state.apply.processed >= total) {
                finalizeApply();
                return;
            }

            apiRequest('bbai_apply_alt_batch', {
                attachment_ids: state.apply.targetIds,
                limit: total,
                offset: state.apply.processed,
                batch_size: 1
            })
                .then(function(data) {
                    state.apply.processed = toInt(data.next_offset, state.apply.processed);
                    state.apply.updated += toInt(data.updated_in_batch, 0);

                    if (Array.isArray(data.failed) && data.failed.length) {
                        state.apply.failed = state.apply.failed.concat(data.failed);
                    }

                    debugState('apply_progress', {
                        processed: state.apply.processed,
                        total: total,
                        updated: state.apply.updated
                    });

                    renderWizard();

                    if (data.done || state.apply.processed >= total) {
                        finalizeApply();
                        return;
                    }

                    window.setTimeout(runApplyBatchLoop, 20);
                })
                .catch(function(error) {
                    state.apply.running = false;
                    renderWizard();
                    showModal({
                        title: __('Apply failed', 'beepbeep-ai-alt-text-generator'),
                        body: error && error.message ? error.message : __('Unable to apply alt text right now.', 'beepbeep-ai-alt-text-generator'),
                        primaryText: __('Try again', 'beepbeep-ai-alt-text-generator'),
                        onPrimary: function() {
                            state.apply.running = true;
                            renderWizard();
                            runApplyBatchLoop();
                        }
                    });
                });
        }

        function startApply() {
            if (state.apply.running) {
                return;
            }

            var targetCount = getWizardTargetCount();
            state.apply.targetIds = state.scan.candidateIds.slice(0, targetCount);

            if (!state.apply.targetIds.length) {
                state.apply.done = true;
                renderWizard();
                return;
            }

            state.apply.running = true;
            state.apply.done = false;
            state.apply.processed = 0;
            state.apply.updated = 0;
            state.apply.failed = [];
            renderWizard();

            runApplyBatchLoop();
        }

        function saveWooContext(enabled) {
            state.wooContextEnabled = !!enabled;
            renderWizard();

            apiRequest('bbai_set_woocommerce_context', {
                enabled: enabled ? 1 : 0
            })
                .then(function(data) {
                    state.wooContextEnabled = !!(data && data.enabled);
                    renderWizard();
                })
                .catch(function(error) {
                    state.wooContextEnabled = !enabled;
                    renderWizard();
                    showModal({
                        title: __('Could not update WooCommerce mode', 'beepbeep-ai-alt-text-generator'),
                        body: error && error.message ? error.message : __('Please try again.', 'beepbeep-ai-alt-text-generator')
                    });
                });
        }

        function openWizard(force) {
            if (!root) {
                return;
            }

            if (force) {
                resetRunState();
            }

            state.visible = true;
            updateLayoutVisibility();
            renderWizard();
        }

        function handleClick(event) {
            var launchTrigger = event.target.closest('[data-action="bbai-run-setup-wizard"]');
            if (launchTrigger && root) {
                event.preventDefault();
                openWizard(true);
                return;
            }

            if (!state.visible || !root) {
                return;
            }

            var actionNode = event.target.closest('[data-wizard-action]');
            if (!actionNode || !root.contains(actionNode)) {
                return;
            }

            event.preventDefault();

            var action = actionNode.getAttribute('data-wizard-action');
            if (action === 'go-apply' && isActionDisabled(actionNode)) {
                showModal({
                    title: __('Approval required', 'beepbeep-ai-alt-text-generator'),
                    body: __('Please confirm that the previews look good before applying to your library.', 'beepbeep-ai-alt-text-generator')
                });
                return;
            }

            if (action === 'apply' && isActionDisabled(actionNode)) {
                return;
            }

            switch (action) {
                case 'scan':
                    runScan();
                    break;
                case 'preview':
                    runPreview();
                    break;
                case 'go-apply':
                    state.currentStep = 3;
                    renderWizard();
                    break;
                case 'apply':
                    startApply();
                    break;
                case 'continue-woo':
                case 'enable-woo-step':
                    state.currentStep = 4;
                    renderWizard();
                    break;
                case 'run-again':
                    resetRunState();
                    renderWizard();
                    break;
                case 'connect-account':
                    openConnectAccount();
                    break;
                case 'finish':
                    finishWizard();
                    break;
                default:
                    break;
            }
        }

        function handleChange(event) {
            if (!state.visible || !root) {
                return;
            }

            var fieldNode = event.target.closest('[data-wizard-field]');
            if (!fieldNode || !root.contains(fieldNode)) {
                return;
            }

            var field = fieldNode.getAttribute('data-wizard-field');
            if (field === 'approve_preview') {
                state.previewApproved = !!fieldNode.checked;
                renderWizard();
                return;
            }

            if (field === 'woo_context') {
                saveWooContext(!!fieldNode.checked);
            }
        }

        function bindDelegatedHandlers() {
            if (listenersBound) {
                return;
            }

            listenersBound = true;
            document.addEventListener('click', handleClick);
            document.addEventListener('change', handleChange);
        }

        function init() {
            if (initialized) {
                return;
            }
            initialized = true;

            root = document.getElementById('bbai-setup-wizard');
            dashboardContainer = document.getElementById('bbai-dashboard-main') || document.querySelector('[data-bbai-dashboard-container]');
            bindDelegatedHandlers();

            if (!root) {
                return;
            }

            var wizardConfig = getWizardConfig();
            state.completed = !!wizardConfig.completed;
            state.previewLimit = Math.max(1, Math.min(5, toInt(wizardConfig.previewLimit, 3)));
            state.applyLimit = Math.max(1, Math.min(10, toInt(wizardConfig.applyLimit, 10)));
            state.candidateLimit = Math.max(3, Math.min(100, toInt(wizardConfig.candidateLimit, 25)));
            state.wooActive = !!wizardConfig.wooActive;
            state.wooContextEnabled = !!wizardConfig.wooContextEnabled;

            if (!state.completed) {
                openWizard(false);
                return;
            }

            state.visible = false;
            updateLayoutVisibility();
        }

        return {
            init: init,
            open: function() {
                openWizard(true);
            }
        };
    })();

    window.bbaiSetupWizard = bbaiSetupWizard;

    // Expose bulk handlers for non-jQuery fallback bindings.
    window.bbaiHandleGenerateMissing = handleGenerateMissing;
    window.bbaiHandleRegenerateAll = handleRegenerateAll;
    window.bbaiHandleRegenerateSingle = handleRegenerateSingle;
    window.handleGenerateMissing = handleGenerateMissing;
    window.handleRegenerateAll = handleRegenerateAll;
    window.handleRegenerateSingle = handleRegenerateSingle;

    // Compatibility aliases used by bridge/fallback scripts.
    window.bbaiGenerateMissing = function(e) {
        return handleGenerateMissing.call(this || document.body, e || { preventDefault: function() {} });
    };
    window.bbaiRegenerateAll = function(e) {
        return handleRegenerateAll.call(this || document.body, e || { preventDefault: function() {} });
    };
    window.bbaiGenerateSelected = function() {
        return runBulkGenerateSelected(this || document.body);
    };
    window.bbaiRegenerateSelected = function() {
        return runBulkRegenerateSelected(this || document.body);
    };
    window.bbaiRegenerateSingle = function(e) {
        return handleRegenerateSingle.call(this || document.body, e || { preventDefault: function() {}, stopPropagation: function() {} });
    };

    function handleDismissQuotaBanner(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }

        var targetSelector = this.getAttribute('data-target');
        var targetNode = targetSelector ? document.querySelector(targetSelector) : null;
        if (!targetNode) {
            targetNode = this.closest('#bbai-quota-banner') || this.closest('.bbai-quota-banner');
        }
        if (!targetNode) {
            return;
        }

        targetNode.setAttribute('hidden', 'hidden');
        targetNode.classList.add('is-dismissed');

        try {
            window.sessionStorage.setItem('bbaiQuotaBannerDismissed', '1');
        } catch (storageError) {
            // Ignore storage errors.
        }
    }

    function applyQuotaBannerDismissState() {
        var bannerHost = document.getElementById('bbai-quota-banner');
        if (!bannerHost) {
            return;
        }

        try {
            if (window.sessionStorage.getItem('bbaiQuotaBannerDismissed') === '1') {
                bannerHost.setAttribute('hidden', 'hidden');
                bannerHost.classList.add('is-dismissed');
            }
        } catch (storageError) {
            // Ignore storage errors.
        }
    }

    function handleDelegatedAdminChange(e) {
        var target = e.target;
        if (!target) {
            return;
        }

        if (target.id === 'bbai-select-all' || (target.classList && target.classList.contains('bbai-select-all-table'))) {
            var checked = !!target.checked;
            var checkboxes = document.querySelectorAll('.bbai-library-row-check');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = checked;
            }
            var mainSelectAll = document.getElementById('bbai-select-all');
            if (mainSelectAll && mainSelectAll !== target) {
                mainSelectAll.checked = checked;
                mainSelectAll.indeterminate = false;
            }
            var tableSelects = document.querySelectorAll('.bbai-select-all-table');
            for (var t = 0; t < tableSelects.length; t++) {
                if (tableSelects[t] !== target) {
                    tableSelects[t].checked = checked;
                    tableSelects[t].indeterminate = false;
                }
            }
            updateLibrarySelectionState();
            return;
        }

        if (target.classList && target.classList.contains('bbai-library-row-check')) {
            updateLibrarySelectionState();
            return;
        }

        if (target.id === 'bbai-library-sort') {
            applyLibraryReviewFilters();
            return;
        }

        if (target.id === 'bbai-library-bulk-action') {
            updateLibrarySelectionState();
        }
    }

    function handleDelegatedAdminInput(e) {
        var target = e.target;
        if (!target) {
            return;
        }

        if (target.id === 'bbai-library-search') {
            applyLibraryReviewFilters();
            return;
        }

        if (target.id === 'bbai-library-edit-modal-textarea') {
            updateLibraryEditModalQuality(target.value || '');
        }
    }

    function handleDelegatedAdminClick(e) {
        var featureLockTrigger = e.target && e.target.closest ? e.target.closest('.bbai-feature-lock-trigger') : null;
        if (featureLockTrigger) {
            e.preventDefault();
            var modal = document.getElementById('bbai-feature-unlock-modal');
            if (modal) {
                var title = modal.querySelector('.bbai-feature-unlock-modal__title');
                var desc = modal.querySelector('.bbai-feature-unlock-modal__desc');
                var titleText = featureLockTrigger.getAttribute('data-bbai-feature-title') || '';
                var descText = featureLockTrigger.getAttribute('data-bbai-feature-desc') || '';
                if (title) title.textContent = titleText;
                if (desc) desc.textContent = descText;
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
            }
            return;
        }

        var featureModalClose = e.target && e.target.closest ? e.target.closest('.bbai-feature-unlock-modal__close') : null;
        if (featureModalClose) {
            e.preventDefault();
            var fm = document.getElementById('bbai-feature-unlock-modal');
            if (fm) {
                fm.style.display = 'none';
                fm.setAttribute('aria-hidden', 'true');
            }
            return;
        }

        var featureModalBackdrop = e.target && e.target.closest ? e.target.closest('#bbai-feature-unlock-modal') : null;
        if (featureModalBackdrop && !e.target.closest('.bbai-feature-unlock-modal__content')) {
            e.preventDefault();
            featureModalBackdrop.style.display = 'none';
            featureModalBackdrop.setAttribute('aria-hidden', 'true');
            return;
        }

        var trigger = e.target && e.target.closest
            ? e.target.closest('[data-bbai-navigation], [data-bbai-action], [data-action], [data-bbai-filter-target], #bbai-review-filter-tabs button[data-filter], #bbai-batch-regenerate, #bbai-batch-export, #bbai-batch-reviewed, #bbai-library-bulk-toggle')
            : null;

        if (!trigger) {
            return;
        }

        if (trigger.closest && trigger.closest('#bbai-review-filter-tabs') && trigger.hasAttribute('data-filter')) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            setLibraryReviewFilter(trigger.getAttribute('data-filter') || 'all', { track: true });
            return;
        }

        if (trigger.hasAttribute && trigger.hasAttribute('data-bbai-filter-target')) {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            setLibraryReviewFilter(trigger.getAttribute('data-bbai-filter-target') || 'all', { track: true });
            return;
        }

        if (trigger.hasAttribute && trigger.getAttribute('data-bbai-navigation') === 'review-results') {
            var reviewTabs = document.getElementById('bbai-review-filter-tabs');
            if (reviewTabs) {
                if (e && typeof e.preventDefault === 'function') {
                    e.preventDefault();
                }
                setLibraryReviewFilter('weak', { preferClientSide: true, track: true });
                scrollLibraryReviewListIntoView();
            }
            return;
        }

        var bbaiAction = String(trigger.getAttribute('data-bbai-action') || '').toLowerCase();
        if (bbaiAction === 'open-upgrade' || bbaiAction === 'open-signup' || bbaiAction === 'open-usage') {
            handleLockedCtaClick(trigger, e);
            return;
        }

        var action = trigger.getAttribute('data-action') || '';
        if (action === 'show-upgrade-modal' && trigger.closest && trigger.closest('#bbai-feature-unlock-modal')) {
            var featModal = document.getElementById('bbai-feature-unlock-modal');
            if (featModal) {
                featModal.style.display = 'none';
                featModal.setAttribute('aria-hidden', 'true');
            }
        }
        if (action === 'dismiss-scan-feedback') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            hideLibraryScanFeedback();
            return;
        }
        if (action === 'close-alt-editor-modal') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            closeLibraryEditModal();
            return;
        }
        if (action === 'toggle-library-bulk-mode') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            setLibraryBulkMode(!isLibraryBulkModeEnabled());
            return;
        }
        if (action === 'save-alt-editor-modal') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            saveLibraryInlineEdit(trigger);
            return;
        }
        if (action === 'regenerate-alt-editor-text') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runLibraryEditAIPrompt('regenerate', trigger);
            return;
        }
        if (action === 'shorten-alt-editor-text') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runLibraryEditAIPrompt('shorter', trigger);
            return;
        }
        if (action === 'describe-alt-editor-text') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runLibraryEditAIPrompt('descriptive', trigger);
            return;
        }
        if (action === 'jump-scan-results') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            scrollToAltTable({
                summary: getLibraryScanFeedbackSummary()
            });
            return;
        }
        if (action === 'rescan-media-library') {
            handleRescanMediaLibrary.call(trigger, e);
            return;
        }

        if (bbaiAction === 'scan-opportunity') {
            handleOpportunityScan.call(trigger, e);
            return;
        }

        if (action === 'dismiss-quota-banner') {
            handleDismissQuotaBanner.call(trigger, e);
            return;
        }

        if (action === 'preview-image') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            openLibraryPreviewModal(getLibraryRowFromTrigger(trigger), trigger);
            return;
        }

        if (action === 'toggle-alt-preview') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            toggleLibraryAltPreview(trigger);
            return;
        }

        if (action === 'copy-alt-text') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            copyLibraryAltText(trigger);
            return;
        }

        if (action === 'close-library-preview') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            closeLibraryPreviewModal();
            return;
        }

        if (action === 'edit-alt-inline') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            startLibraryInlineEdit(getLibraryRowFromTrigger(trigger), 'library_inline');
            return;
        }

        if (action === 'save-alt-inline') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            saveLibraryInlineEdit(trigger);
            return;
        }

        if (action === 'cancel-alt-inline') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            cancelLibraryInlineEdit(getLibraryRowFromTrigger(trigger));
            return;
        }

        if (action === 'edit-alt-from-preview') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            var modalEditId = bbaiLibraryPreviewModal ? parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10) : 0;
            if (modalEditId > 0) {
                var rowForEdit = document.querySelector('.bbai-library-row[data-attachment-id="' + modalEditId + '"]');
                closeLibraryPreviewModal();
                if (rowForEdit) {
                    startLibraryInlineEdit(rowForEdit, 'preview_modal');
                }
            }
            return;
        }

        if (action === 'regenerate-from-preview') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            var modalRegenId = bbaiLibraryPreviewModal ? parseInt(bbaiLibraryPreviewModal.getAttribute('data-attachment-id') || '', 10) : 0;
            if (modalRegenId > 0) {
                var rowForRegen = document.querySelector('.bbai-library-row[data-attachment-id="' + modalRegenId + '"]');
                closeLibraryPreviewModal();
                if (rowForRegen) {
                    var regenButton = rowForRegen.querySelector('[data-action="regenerate-single"]');
                    if (regenButton) {
                        regenButton.click();
                    }
                }
            }
            return;
        }

        if (action === 'clear-selection') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            clearLibrarySelection();
            return;
        }

        if (action === 'clear-library-search') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            var searchInput = document.getElementById('bbai-library-search');
            if (searchInput) {
                searchInput.value = '';
            }
            applyLibraryReviewFilters();
            return;
        }

        if (action === 'select-all-visible') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            selectVisibleLibraryRows();
            return;
        }

        if (action === 'apply-bulk-selection') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runApplyBulkSelection(trigger);
            return;
        }

        if (action === 'generate-selected') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runBulkGenerateSelected(trigger);
            return;
        }

        if (action === 'show-generate-alt-modal') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            openDashboardGeneratorModal();
            return;
        }

        if (action === 'regenerate-selected') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runBulkRegenerateSelected(trigger);
            return;
        }

        if (action === 'mark-reviewed') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runBulkMarkReviewed(trigger);
            return;
        }

        if (action === 'export-alt-text') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runExportAltText(trigger);
            return;
        }

        if (action === 'clear-alt-selected') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runBulkClearAltSelected(trigger);
            return;
        }

        if (action === 'generate-missing') {
            if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
                handleLockedCtaClick(trigger, e);
                return;
            }
            handleGenerateMissing.call(trigger, e);
            return;
        }
        if (action === 'fix-all-images-automatically') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            runBulkFixAllIssues(trigger);
            return;
        }
        if (action === 'regenerate-all') {
            if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
                handleLockedCtaClick(trigger, e);
                return;
            }
            handleRegenerateAll.call(trigger, e);
            return;
        }
        if (action === 'regenerate-single') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Regenerate button click event fired!');
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Button element:', trigger);
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] jQuery object:', $(trigger));
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Attachment ID from data:', $(trigger).data('attachment-id'));
            handleRegenerateSingle.call(trigger, e);
            return;
        }
        if (action === 'approve-alt-inline') {
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }
            approveLibraryRow(trigger);
            return;
        }
        if (action === 'deactivate-license') {
            handleLicenseDeactivation.call(trigger, e);
        }
    }

    function bindDelegatedAdminHandlers() {
        if (bbaiDelegatedHandlersBound) {
            return;
        }

        // Use document so clicks in portaled modals (e.g. library preview) bubble correctly.
        // wpbody-content would miss clicks in modals appended to body.
        var eventRoot = document;
        eventRoot.addEventListener('click', handleDelegatedAdminClick, false);
        eventRoot.addEventListener('change', handleDelegatedAdminChange, false);
        eventRoot.addEventListener('input', handleDelegatedAdminInput, false);
        document.addEventListener('keydown', function(event) {
            if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
                if (bbaiLibraryEditModal && bbaiLibraryEditModal.classList.contains('is-visible')) {
                    var saveTrigger = bbaiLibraryEditModal.querySelector('[data-action="save-alt-editor-modal"]');
                    if (saveTrigger) {
                        event.preventDefault();
                        saveTrigger.click();
                        return;
                    }
                }
            }
            if (event.key === 'Escape') {
                if (bbaiLibraryEditModal && bbaiLibraryEditModal.classList.contains('is-visible')) {
                    closeLibraryEditModal();
                } else if (bbaiLibraryPreviewModal && bbaiLibraryPreviewModal.classList.contains('is-visible')) {
                    closeLibraryPreviewModal();
                } else {
                    var activeEl = document.activeElement;
                    if (
                        activeEl &&
                        activeEl.classList &&
                        activeEl.classList.contains('bbai-library-inline-alt__textarea') &&
                        activeEl.closest
                    ) {
                        var escRow = activeEl.closest('.bbai-library-row');
                        if (escRow) {
                            event.preventDefault();
                            cancelLibraryInlineEdit(escRow);
                            return;
                        }
                    }
                    var featModal = document.getElementById('bbai-feature-unlock-modal');
                    if (featModal && featModal.style.display === 'block') {
                        featModal.style.display = 'none';
                        featModal.setAttribute('aria-hidden', 'true');
                    }
                }
            }
        }, false);
        bbaiDelegatedHandlersBound = true;
    }

    /**
     * JS Activation Smoke Plan:
     * 1. Fresh install: run 3-image demo and confirm instant-win rows render.
     * 2. No missing images: demo returns friendly upload suggestion.
     * 3. Trial exhausted: upgrade CTA remains clickable, no disabled primary CTA.
     * 4. Rapid repeated clicks: single in-flight request, no duplicate processing.
     */
    function initDashboardOnce() {
        if (bbaiAdminInitialized) {
            return;
        }
        bbaiAdminInitialized = true;

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Admin JavaScript loaded');
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Config:', config);
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Has bulk config:', hasBulkConfig);
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] bbai_ajax:', window.bbai_ajax);

        bindLockedAction('[data-action="generate-missing"]', 'generate_missing');
        bindLockedAction('[data-action="regenerate-all"]', 'reoptimize_all');
        bindLockedAction('[data-action="regenerate-single"]', 'regenerate_single');
        bindLockedAction('[data-action="phase17-improve-alt"]', 'regenerate_single');
        bindLockedAction('#bbai-batch-generate', 'generate_missing');
        bindLockedAction('[data-bbai-action="generate_missing"]', 'generate_missing');
        bindLockedAction('[data-bbai-action="reoptimize_all"]', 'reoptimize_all');
        bindLockedAction('#bbai-batch-regenerate', 'reoptimize_all');
        bindLockedAction('[data-bbai-locked-cta="1"]', 'upgrade_required');

        bindDelegatedAdminHandlers();
        bindOutOfCreditsClickGuard();
        removeSharedUsageBanners();
        applyQuotaBannerDismissState();
        enforceOutOfCreditsBulkLocks();
        applyDashboardState(false);
        var initialDashboardStats = readDashboardStatsFromRoot();
        if (initialDashboardStats) {
            updateDashboardHero(initialDashboardStats);
            updateDashboardStatusCard(initialDashboardStats);
            updateDashboardPerformanceMetrics(initialDashboardStats, getUsageSnapshot(null));
        }
        var libraryFilterRoot = document.getElementById('bbai-review-filter-tabs');
        bindLibraryReviewFilterButtons();
        setLibraryReviewFilter(
            libraryFilterRoot
                ? (libraryFilterRoot.getAttribute('data-bbai-default-filter') || getLibraryActiveFilter() || 'all')
                : 'all'
        );
        initLibraryFilterAttention();
        updateLibrarySelectionState();
        syncSharedUsageBanner(null);
        purgeLegacyDashboardProgressBars();
        setTimeout(function() {
            enforceOutOfCreditsBulkLocks();
            applyDashboardState(false);
            updateLibrarySelectionState();
            syncSharedUsageBanner(null);
            syncLibraryMissingBulkBar();
        }, 300);
        bbaiSetupWizard.init();

        var regenButtons = $('[data-action="regenerate-single"]');
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Found ' + regenButtons.length + ' regenerate buttons');

        // Bridge compatibility: support custom events fired by non-jQuery dashboard components.
        $(document)
            .off('bbai:generate-missing.bbaiBridge')
            .on('bbai:generate-missing.bbaiBridge', function() {
                var target = getActionButton('generate-missing') || document.body;
                handleGenerateMissing.call(target, { preventDefault: function() {} });
            });

        window.bulk_generate_missing_alt = function() {
            $(document).trigger('bbai:generate-missing');
        };

        $(document)
            .off('bbai:generate-selected.bbaiBridge')
            .on('bbai:generate-selected.bbaiBridge', function() {
                var target = getActionButton('generate-selected') || document.body;
                runBulkGenerateSelected(target);
            });

        $(document)
            .off('bbai:regenerate-all.bbaiBridge')
            .on('bbai:regenerate-all.bbaiBridge', function() {
                var target = getActionButton('regenerate-all') || document.body;
                handleRegenerateAll.call(target, { preventDefault: function() {} });
            });

        window.bulk_generate_alt = function(scope) {
            var normalizedScope = String(scope || 'missing').toLowerCase();

            if (normalizedScope === 'weak') {
                var weakTrigger = document.createElement('button');
                weakTrigger.type = 'button';
                weakTrigger.setAttribute('data-action', 'regenerate-all');
                weakTrigger.setAttribute('data-bbai-regenerate-scope', 'needs-review');
                weakTrigger.setAttribute('data-bbai-generation-source', 'regenerate-weak');
                return window.bbaiRegenerateAll.call(weakTrigger, { preventDefault: function() {} });
            }

            if (normalizedScope === 'selected') {
                var selectedCount = getSelectedLibraryIds().length;
                if (!selectedCount) {
                    if (window.bbaiModal && typeof window.bbaiModal.info === 'function') {
                        window.bbaiModal.info(__('Select images in the ALT Library first, then try again.', 'beepbeep-ai-alt-text-generator'));
                    }
                    return false;
                }

                return $(document).trigger('bbai:generate-selected');
            }

            if (normalizedScope === 'all') {
                var allTrigger = document.createElement('button');
                allTrigger.type = 'button';
                allTrigger.setAttribute('data-action', 'regenerate-all');
                return window.bbaiRegenerateAll.call(allTrigger, { preventDefault: function() {} });
            }

            window.bulk_generate_missing_alt();
            return true;
        };

        $(document)
            .off('bbai:usage-updated.bbaiDashboardState bbai:stats-updated.bbaiDashboardState')
            .on('bbai:usage-updated.bbaiDashboardState bbai:stats-updated.bbaiDashboardState', function(event) {
                if (event && event.type === 'bbai:stats-updated') {
                    bbaiLimitPreviewCache = null;
                }
                enforceOutOfCreditsBulkLocks();
                applyDashboardState(event && event.type === 'bbai:stats-updated');
                syncLibraryMissingBulkBar();
            });

        if (!bbaiLockObserver && window.MutationObserver && document.body) {
            var stateSyncQueued = false;
            bbaiLockObserver = new MutationObserver(function() {
                if (stateSyncQueued) {
                    return;
                }
                stateSyncQueued = true;
                var flush = function() {
                    stateSyncQueued = false;
                    enforceOutOfCreditsBulkLocks();
                    applyDashboardState(false);
                    syncLibraryMissingBulkBar();
                };
                if (typeof window.requestAnimationFrame === 'function') {
                    window.requestAnimationFrame(flush);
                } else {
                    setTimeout(flush, 50);
                }
            });
            bbaiLockObserver.observe(document.body, { childList: true, subtree: true });
        }

        window.bbaiBulkHandlersReady = true;

        // License activation form still uses delegated submit handling.
        $(document)
            .off('submit.bbaiLicenseActivation', '#license-activation-form')
            .on('submit.bbaiLicenseActivation', '#license-activation-form', handleLicenseActivation);

        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] License management handlers registered');

        var MODAL_ACTIVE_SELECTOR = [
            '.bbai-regenerate-modal.active',
            '.bbai-bulk-progress-modal.active',
            '#bbai-upgrade-modal.active',
            '#bbai-upgrade-modal[style*="display: flex"]',
            '#bbai-modal-overlay[style*="display: block"]',
            '#bbai-modal-overlay[style*="display: flex"]',
            '#alttext-auth-modal[style*="display: block"]',
            '.bbai-locked-upgrade-modal.is-visible',
            '.bbai-shortcuts-modal.show'
        ].join(', ');

        setTimeout(function() {
            var hasActiveModals = $(MODAL_ACTIVE_SELECTOR).length;
            if (!hasActiveModals && (document.body.style.overflow === 'hidden' || document.documentElement.style.overflow === 'hidden')) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Restoring page scroll - no active modals but overflow is hidden');
                if (window.restorePageScroll) {
                    window.restorePageScroll();
                }
            }
        }, 500);

        if (bbaiModalSafetyIntervalId) {
            clearInterval(bbaiModalSafetyIntervalId);
        }
        bbaiModalSafetyIntervalId = setInterval(function() {
            var hasActiveModals = $(MODAL_ACTIVE_SELECTOR).length;
            var hasVisibleModals = $('#bbai-modal-overlay.is-visible:visible, #bbai-upgrade-modal.active:visible, #bbai-upgrade-modal.is-visible:visible, .bbai-regenerate-modal.active:visible, .bbai-bulk-progress-modal.active:visible, #bbai-modal-success.active:visible, #alttext-auth-modal:visible, .bbai-locked-upgrade-modal.is-visible:visible').length > 0;
            var hasGhostModal = hasActiveModals && !hasVisibleModals;

            if (hasGhostModal) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Ghost modal detected - forcing unlock');
                if (window.restorePageScroll) {
                    window.restorePageScroll();
                }
                return;
            }

            if (!hasActiveModals && (document.body.style.overflow === 'hidden' || document.documentElement.style.overflow === 'hidden')) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Auto-restoring page scroll - detected stuck scrolling');
                if (window.restorePageScroll) {
                    window.restorePageScroll();
                }
            }
        }, 5000);
    }

    $(initDashboardOnce);
    window.initDashboardOnce = initDashboardOnce;

    /**
     * Refresh usage stats from API and update display
     * Called after alt text generation to update the usage counter
     * Available globally on all admin pages
     * @param {Object} usageData - Optional usage data to use directly (avoids API call)
     */
    window.alttextai_refresh_usage = function(usageData) {
        // If usage data is provided directly, use it without API call
        if (usageData && typeof usageData === 'object') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using provided usage data:', usageData);
            updateUsageDisplayGlobally(usageData);
            return;
        }

        var config = window.BBAI_DASH || window.BBAI || {};
        var usageUrl = config.restUsage;
        var nonce = config.nonce || '';

        if (!usageUrl) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Cannot refresh usage: REST endpoint not available', config);
            return;
        }

        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Refreshing usage from:', usageUrl);

        $.ajax({
            url: usageUrl,
            method: 'GET',
            headers: {
                'X-WP-Nonce': nonce
            },
            success: function(response) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Usage API response:', response);

                if (response && typeof response === 'object') {
                    updateUsageDisplayGlobally(response);
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Invalid usage response format:', response);
                }
            },
            error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to refresh usage:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    responseText: xhr.responseText
                });
            }
        });
    };

    /**
     * Update usage display with provided data
     * @param {Object} response - Usage data object
     */
    function updateUsageDisplayGlobally(response) {
        var usagePayload = mirrorUsagePayload(response);

        if (!usagePayload || typeof usagePayload !== 'object') {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Invalid usage data for display:', response);
            return;
        }

        // Extract usage values - handle both direct response and nested data
        var used = usagePayload.used !== undefined ? usagePayload.used : 0;
        var limit = usagePayload.limit !== undefined ? usagePayload.limit : 50;
        var remaining = usagePayload.remaining !== undefined ? usagePayload.remaining : 0;

        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updating usage display:', { used: used, limit: limit, remaining: remaining });

        // Find all usage stat value elements and update them
        $('.bbai-usage-stat-item').each(function() {
            var $item = $(this);
            var label = $item.find('.bbai-usage-stat-label').text().trim().toLowerCase();
            var $value = $item.find('.bbai-usage-stat-value');

            if ($value.length) {
                var newValue = null;
                if (label.includes('generated') || label.includes('used')) {
                    newValue = parseInt(used).toLocaleString();
                } else if (label.includes('limit') || label.includes('monthly')) {
                    newValue = parseInt(limit).toLocaleString();
                } else if (label.includes('remaining')) {
                    newValue = parseInt(remaining).toLocaleString();
                }

                if (newValue !== null) {
                    var oldValue = $value.text();
                    $value.removeData('bbai-animated');
                    $value.text(newValue);
                    // Simple fade animation if value changed
                    if (oldValue !== newValue) {
                        $value.fadeOut(100, function() {
                            $(this).fadeIn(100);
                        });
                    }
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated', label, 'from', oldValue, 'to', newValue);
                }
            }
        });

        // Also update any generic number-counting elements that might be usage related
        $('.bbai-number-counting').each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            // Only update if it looks like a number (potential usage value)
            if (/^\d[\d,]*$/.test(text.replace(/,/g, ''))) {
                var parentText = $el.closest('.bbai-usage-card-stats, .bbai-usage-stat-item, .bbai-usage-card').text().toLowerCase();
                var newValue = null;
                if (parentText.includes('generated') || parentText.includes('used')) {
                    newValue = parseInt(used).toLocaleString();
                } else if (parentText.includes('limit') || parentText.includes('monthly')) {
                    newValue = parseInt(limit).toLocaleString();
                } else if (parentText.includes('remaining')) {
                    newValue = parseInt(remaining).toLocaleString();
                }

                if (newValue !== null && $el.text() !== newValue) {
                    var oldValue = $el.text();
                    $el.removeData('bbai-animated');
                    $el.text(newValue);
                    $el.fadeOut(100, function() {
                        $(this).fadeIn(100);
                    });
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated number element from', oldValue, 'to', newValue);
                }
            }
        });

        // Update the "7 / 50" format in .bbai-usage-text (regular layout)
        $('.bbai-usage-text').each(function() {
            var $container = $(this);
            var $strongs = $container.find('strong.bbai-number-counting');
            if ($strongs.length === 2) {
                // First strong is used, second is limit (format: "7 / 50")
                var $usedEl = $strongs.eq(0);
                var $limitEl = $strongs.eq(1);
                var usedValue = parseInt(used).toString();
                var limitValue = parseInt(limit).toString();

                if ($usedEl.text().trim() !== usedValue) {
                    var oldUsed = $usedEl.text().trim();
                    $usedEl.removeData('bbai-animated');
                    $usedEl.text(usedValue);
                    $usedEl.fadeOut(100, function() {
                        $(this).fadeIn(100);
                    });
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated usage-text used from', oldUsed, 'to', usedValue);
                }

                if ($limitEl.text().trim() !== limitValue) {
                    var oldLimit = $limitEl.text().trim();
                    $limitEl.removeData('bbai-animated');
                    $limitEl.text(limitValue);
                    $limitEl.fadeOut(100, function() {
                        $(this).fadeIn(100);
                    });
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated usage-text limit from', oldLimit, 'to', limitValue);
                }
            }
        });

        // Update text-based usage displays (e.g., "7 / 50" format on dashboard) - fallback
        $('.bbai-usage-card strong.bbai-number-counting').each(function() {
            var $el = $(this);
            var text = $el.text().trim();
            var parentText = $el.closest('.bbai-usage-card').text().toLowerCase();

            // Check if it's a usage number
            if (/^\d+$/.test(text)) {
                var numValue = parseInt(text);
                // Try to match context
                if (parentText.includes('generated') || parentText.includes('used')) {
                    if (numValue !== used) {
                        $el.removeData('bbai-animated');
                        $el.text(used);
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated card used from', numValue, 'to', used);
                    }
                } else if (parentText.includes('limit') || parentText.includes('monthly')) {
                    if (numValue !== limit) {
                        $el.removeData('bbai-animated');
                        $el.text(limit);
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated card limit from', numValue, 'to', limit);
                    }
                } else if (parentText.includes('remaining')) {
                    if (numValue !== remaining) {
                        $el.removeData('bbai-animated');
                        $el.text(remaining);
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated card remaining from', numValue, 'to', remaining);
                    }
                }
            }
        });

        // Update circular progress percentage and visual
        if (limit && limit > 0) {
            var percentage = Math.min(100, Math.round((used / limit) * 100));
            var percentageDisplay = percentage + '%';

            // Update percentage text
            $('.bbai-circular-progress-percent').each(function() {
                var $el = $(this);
                var $val = $el.find('.bbai-donut-value');
                var currentVal = $val.length ? $val.text().trim() : $el.text().trim().replace(/%$/, '');
                if (currentVal !== String(percentage)) {
                    $el.removeData('bbai-animated');
                    if ($val.length) {
                        $val.text(percentage);
                    } else {
                        $el.text(percentageDisplay);
                    }
                    $el.fadeOut(100, function() {
                        $(this).fadeIn(100);
                    });
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated circular progress percent from', currentVal, 'to', percentage);
                }
            });

            // Update circular progress bar visual (stroke-dashoffset + adaptive color)
            $('.bbai-circular-progress-bar').not('[data-bbai-status-coverage-ring]').each(function() {
                var $ring = $(this);
                var circumference = parseFloat($ring.data('circumference')) || parseFloat($ring.attr('stroke-dasharray')) || (2 * Math.PI * 48);
                var offset = circumference * (1 - (percentage / 100));

                // Get current offset
                var currentOffset = parseFloat($ring.css('stroke-dashoffset')) || parseFloat($ring.attr('stroke-dashoffset')) || circumference;

                // Update offset if changed
                if (Math.abs(currentOffset - offset) > 0.1) {
                    $ring.attr('data-offset', offset);
                    $ring.attr('data-percentage', percentage);
                    if ($ring[0].style) {
                        $ring[0].style.strokeDashoffset = offset;
                    }
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated circular progress bar offset from', currentOffset, 'to', offset);
                }

                // Adaptive stroke color based on usage percentage
                var colorClass = 'bbai-usage-ring--healthy';
                var strokeColor = '#10B981';
                if (percentage >= 100) {
                    colorClass = 'bbai-usage-ring--critical';
                    strokeColor = '#EF4444';
                } else if (percentage >= 86) {
                    colorClass = 'bbai-usage-ring--danger';
                    strokeColor = '#EF4444';
                } else if (percentage >= 61) {
                    colorClass = 'bbai-usage-ring--warning';
                    strokeColor = '#F59E0B';
                }

                $ring.removeClass('bbai-usage-ring--healthy bbai-usage-ring--warning bbai-usage-ring--danger bbai-usage-ring--critical');
                $ring.addClass(colorClass);
                $ring.attr('stroke', strokeColor);
            });

        }

        updateLibraryUsageSurface(usagePayload);
        syncSharedUsageBanner(usagePayload);
        applyDashboardState(false);
        purgeLegacyDashboardProgressBars();
        $(document).trigger('bbai:usage-updated');
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Usage display update complete');
    }

    window.bbaiUpdateTrialUsage = function(usageData) {
        var usagePayload = normalizeUsagePayload(usageData) || getUsageForQuotaChecks();

        if (!usagePayload || !isAnonymousTrialUsage(usagePayload)) {
            return;
        }

        updateUsageDisplayGlobally(usagePayload);
    };

    // Also create a global refreshUsageStats function for compatibility
    if (typeof window.refreshUsageStats === 'undefined') {
        window.refreshUsageStats = window.alttextai_refresh_usage;
    }

})(jQuery);

/* ==========================================================================
 * BBAI Library UX Enhancements
 * Real-time row states · Stats polling · Approve fade-out
 * Extends the existing library system without modifying core handlers.
 * ========================================================================== */
(function($) {
    'use strict';

    /* ── Row state helpers ──────────────────────────────────────────────── */

    /**
     * Mark a library row as in-flight (processing/generating).
     * Applies the --processing class so the row gets a blue tint.
     * Also marks the progress bar as busy so segment transitions animate.
     */
    function bbaiSetRowProcessing(row) {
        if (!row) { return; }
        row.classList.add('bbai-library-row--processing');
        row.classList.remove('bbai-library-row--done');
        // Animate the global summary-card progress bar while work is in-flight
        var progressBar = document.querySelector('[data-bbai-library-progressbar]');
        if (progressBar) {
            progressBar.classList.add('bbai-library-progressbar--busy');
        }
    }

    /**
     * Mark a library row as done.
     * Removes the --processing class and applies the --done flash animation.
     */
    function bbaiSetRowDone(row) {
        if (!row) { return; }
        row.classList.remove('bbai-library-row--processing');
        row.classList.add('bbai-library-row--done');
        // Let the animation finish then clean up
        setTimeout(function() {
            if (row) { row.classList.remove('bbai-library-row--done'); }
        }, 950);
        // Stop the busy animation on the progress bar if no other rows are processing
        var stillProcessing = document.querySelectorAll('.bbai-library-row--processing').length;
        if (!stillProcessing) {
            var progressBar = document.querySelector('[data-bbai-library-progressbar]');
            if (progressBar) {
                progressBar.classList.remove('bbai-library-progressbar--busy');
            }
        }
    }

    // Expose so other scripts can call them (e.g. bulk flows)
    window.bbaiSetRowProcessing = bbaiSetRowProcessing;
    window.bbaiSetRowDone       = bbaiSetRowDone;

    /* ── Approve row fade-out ───────────────────────────────────────────── */

    /**
     * Smoothly removes an approved row from the "Needs review" filtered view.
     * Called after a successful approve response, in addition to (not instead of)
     * the existing renderLibraryAltCell + toast flow.
     *
     * Only removes the row if the current active filter is "weak" (Needs review).
     * Under "all" or other filters the row stays but updates its badge in-place.
     */
    function bbaiApproveRowTransition(row) {
        if (!row) { return; }
        if (row.getAttribute('data-bbai-filter-exit-in-flight') === '1') { return; }

        // Determine if we are in the "Needs review" filtered view
        var activeFilterBtn = document.querySelector(
            '#bbai-review-filter-tabs button[data-filter].bbai-alt-review-filters__btn--active,' +
            '#bbai-review-filter-tabs .bbai-alt-review-filters__btn--active[data-filter]'
        );
        var activeFilter = activeFilterBtn ? (activeFilterBtn.getAttribute('data-filter') || 'all') : 'all';

        if (activeFilter !== 'weak') {
            // Not filtered to "Needs review" — just do the done flash, keep row
            bbaiSetRowDone(row);
            return;
        }

        if (typeof window.bbaiAnimateLibraryRowFilterExit === 'function') {
            window.bbaiAnimateLibraryRowFilterExit(row, 'weak');
            return;
        }

        // Capture current height for smooth collapse
        var currentHeight = row.offsetHeight;
        row.style.maxHeight = currentHeight + 'px';
        row.classList.add('bbai-library-row--approve-pending');

        // On next frame, trigger the collapse
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                row.classList.add('bbai-library-row--approve-out');
                // Remove from DOM after transition completes
                setTimeout(function() {
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }
                    // Show empty-state if no rows remain
                    bbaiCheckLibraryEmptyAfterRemoval();
                }, 750);
            });
        });
    }

    /**
     * After removing an approved row, if the filtered list is now empty,
     * toggle the empty-state element (the existing .bbai-library-filter-empty).
     */
    function bbaiCheckLibraryEmptyAfterRemoval() {
        var tableBody = document.getElementById('bbai-library-table-body');
        if (!tableBody) { return; }
        var remaining = tableBody.querySelectorAll(
            '.bbai-library-row:not(.bbai-library-row--approve-out):not(.bbai-library-row--hidden)'
        );
        if (remaining.length === 0) {
            // Try to show the existing empty-state element
            var emptyState = document.querySelector('.bbai-library-filter-empty, .bbai-table-empty');
            if (emptyState) {
                emptyState.removeAttribute('hidden');
                emptyState.style.display = '';
            }
            // Also update the filter count badge to 0
            var weakFilterBtn = document.querySelector(
                '#bbai-review-filter-tabs button[data-filter="weak"]'
            );
            if (weakFilterBtn) {
                weakFilterBtn.classList.remove('bbai-alt-review-filters__btn--problem');
            }
        }
    }

    window.bbaiApproveRowTransition = bbaiApproveRowTransition;

    /* ── Stats polling ──────────────────────────────────────────────────── */

    var bbaiStatsPoller = (function() {
        var intervalId    = null;
        var activeRegens  = 0;          // count of in-flight single regenerations
        var POLL_INTERVAL = 5000;       // ms between polls
        var lastPollTs    = 0;

        function getRestConfig() {
            return window.BBAI_DASH || window.BBAI || {};
        }

        function isLibraryPageVisible() {
            // Only poll when the library workspace is in the DOM and visible
            var root = document.getElementById('bbai-library-table-body') ||
                       document.querySelector('.bbai-library-review-queue') ||
                       document.querySelector('[data-bbai-library-workspace]');
            return !!root;
        }

        function poll() {
            if (!isLibraryPageVisible()) { return; }
            var now = Date.now();
            if (now - lastPollTs < POLL_INTERVAL - 200) { return; }
            lastPollTs = now;

            var cfg   = getRestConfig();
            var stats = cfg.restStats;
            if (!stats) {
                var pollRoot = typeof getBbaiResolvedRestRoot === 'function' ? getBbaiResolvedRestRoot() : '';
                stats = pollRoot ? pollRoot + 'bbai/v1/stats' : '';
            }
            var nonce =
                (typeof getLibraryRestNonce === 'function' && getLibraryRestNonce()) ||
                cfg.nonce ||
                '';
            if (!stats || !nonce) { return; }

            var pollUrl = String(stats).indexOf('?') === -1 ? stats + '?fresh=1' : stats + '&fresh=1';

            $.ajax({
                url: pollUrl,
                method: 'GET',
                dataType: 'json',
                headers: { 'X-WP-Nonce': nonce },
                timeout: 8000
            }).done(function(response) {
                if (!response || typeof response !== 'object') { return; }
                if (typeof window.updateAltCoverageCard === 'function') {
                    window.updateAltCoverageCard(response);
                }
                if (typeof updateLibraryReviewFilterCounts === 'function') {
                    updateLibraryReviewFilterCounts(response);
                }
                try {
                    document.dispatchEvent(new CustomEvent('bbai:stats-updated', {
                        detail: { stats: response }
                    }));
                } catch (ignored) { /* noop */ }
            }).fail(function() {
                // Silently swallow — polling is best-effort
            });
        }

        function startPolling() {
            activeRegens++;
            if (intervalId) { return; }          // already running
            if (!isLibraryPageVisible()) { return; }
            intervalId = window.setInterval(poll, POLL_INTERVAL);
        }

        function stopPolling() {
            activeRegens = Math.max(0, activeRegens - 1);
            if (activeRegens > 0) { return; }    // other regens still in-flight
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
        }

        /** Hard stop — clears regardless of active count. Used on page unload. */
        function destroyPolling() {
            activeRegens = 0;
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
        }

        // Stop on page hide to avoid stale requests
        window.addEventListener('pagehide', destroyPolling);
        window.addEventListener('beforeunload', destroyPolling);

        return {
            start:   startPolling,
            stop:    stopPolling,
            destroy: destroyPolling
        };
    })();

    window.bbaiStatsPoller = bbaiStatsPoller;

    /* ── Single-regenerate row dimming + stats polling ──────────────────── */
    /* Processing class and poller are started in handleRegenerateSingle only when
     * an AJAX request is actually sent, and cleared in the request's .always() so
     * early returns (locked CTA, inline edit guard, etc.) never leave the row
     * stuck at reduced opacity over the ALT slot. */

    /* ── Hook into approve action to trigger row fade-out ───────────────── */

    /**
     * Listen for successful approve completions.
     * The existing approveLibraryRow handler dispatches 'bbai:analytics' with
     * event='alt_library_approve_*'. We also watch for the toast being pushed,
     * but the most reliable hook is to intercept the approve button click and
     * watch for the row's data-approved attribute being set to 'true'.
     */
    document.addEventListener('click', function(e) {
        var trigger = e.target && e.target.closest
            ? e.target.closest('[data-action="approve-alt-inline"]')
            : null;
        if (!trigger) { return; }

        var row = trigger.closest('.bbai-library-row');
        if (!row) { return; }

        // Watch for data-approved="true" being set on the row by approveLibraryRow
        var approveObserver = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var m = mutations[i];
                if (
                    m.type === 'attributes' &&
                    m.attributeName === 'data-approved' &&
                    row.getAttribute('data-approved') === 'true'
                ) {
                    approveObserver.disconnect();
                    // Small delay so the row badge update is visible before fading
                    setTimeout(function() {
                        bbaiApproveRowTransition(row);
                    }, 600);
                    return;
                }
                // Also watch for status change to 'optimized'
                if (
                    m.type === 'attributes' &&
                    m.attributeName === 'data-status' &&
                    row.getAttribute('data-status') === 'optimized'
                ) {
                    approveObserver.disconnect();
                    setTimeout(function() {
                        bbaiApproveRowTransition(row);
                    }, 600);
                    return;
                }
            }
        });

        approveObserver.observe(row, {
            attributes: true,
            attributeFilter: ['data-approved', 'data-status', 'data-review-state']
        });

        // Safety: disconnect after 10s
        setTimeout(function() {
            if (approveObserver) { approveObserver.disconnect(); }
        }, 10000);

    }, true /* capture */);

})(jQuery);
