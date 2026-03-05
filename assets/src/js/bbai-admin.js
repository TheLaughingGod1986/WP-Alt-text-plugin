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

    function getUsageForQuotaChecks() {
        return (window.BBAI_DASH && (window.BBAI_DASH.initialUsage || window.BBAI_DASH.usage)) ||
            (window.BBAI_DASHBOARD && window.BBAI_DASHBOARD.usage) ||
            (window.BBAI && window.BBAI.usage) ||
            null;
    }

    function getUsageFromDom() {
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

    function isFreePlan(planName) {
        var plan = String(planName || '').toLowerCase();
        if (!plan) {
            return true;
        }

        return plan === 'free' || plan === 'trial' || plan === 'starter';
    }

    function getDashboardState() {
        var usage = getUsageForQuotaChecks() || {};
        var isFree = isFreePlan(usage.plan);
        var remaining = NaN;
        var quotaUsed = NaN;
        var quotaLimit = NaN;

        if (usage.credits_remaining !== undefined && usage.credits_remaining !== null) {
            remaining = parseInt(usage.credits_remaining, 10);
        } else if (usage.remaining !== undefined && usage.remaining !== null) {
            remaining = parseInt(usage.remaining, 10);
        } else if (usage.generations_remaining !== undefined && usage.generations_remaining !== null) {
            remaining = parseInt(usage.generations_remaining, 10);
        }

        if (usage.quota && typeof usage.quota === 'object') {
            quotaUsed = parseInt(usage.quota.used, 10);
            quotaLimit = parseInt(usage.quota.limit, 10);
        } else {
            quotaUsed = parseInt(usage.used, 10);
            quotaLimit = parseInt(usage.limit, 10);
        }

        var remainingReached = !isNaN(remaining) && remaining <= 0;
        var quotaReached = !isNaN(quotaUsed) && !isNaN(quotaLimit) && quotaLimit > 0 && quotaUsed >= quotaLimit;
        if (isFree && (remainingReached || quotaReached)) {
            return 'limit_reached';
        }

        var domUsage = getUsageFromDom();
        if (isFree && domUsage && domUsage.limit > 0 && domUsage.used >= domUsage.limit) {
            return 'limit_reached';
        }

        return 'active';
    }

    function isOutOfCreditsFromUsage() {
        return getDashboardState() === 'limit_reached';
    }

    function isGenerationActionControl(element) {
        if (!element) {
            return false;
        }

        var action = String(element.getAttribute('data-action') || '').toLowerCase();
        var bbaiAction = String(element.getAttribute('data-bbai-action') || '').toLowerCase();
        var intendedAction = String(element.getAttribute('data-bbai-intended-action') || '').toLowerCase();
        if (action === 'generate-missing' || action === 'regenerate-all' || action === 'regenerate-single') {
            return true;
        }
        if (bbaiAction === 'generate_missing' || bbaiAction === 'reoptimize_all') {
            return true;
        }
        if (intendedAction === 'generate-missing' || intendedAction === 'regenerate-all' || intendedAction === 'regenerate-single') {
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

        if (isGenerationActionControl(element) && getDashboardState() === 'limit_reached') {
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

    function trackDashboardEvent(eventName, payload) {
        var detail = $.extend(
            {
                event: eventName,
                source: 'dashboard',
                timestamp: Date.now()
            },
            payload || {}
        );

        try {
            document.dispatchEvent(new CustomEvent('bbai:analytics', { detail: detail }));
        } catch (e) {
            // Ignore dispatch errors.
        }

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
        var localized = window.bbai_admin || {};
        var credits = localized.credits || {};
        var usage = getUsageForQuotaChecks() || {};
        var used = parseInt(credits.used, 10);
        var limit = parseInt(credits.limit, 10);
        var daysLeft = parseInt(credits.daysLeft, 10);

        if (isNaN(used)) {
            used = parseInt(usage.used, 10);
        }
        if (isNaN(limit)) {
            limit = parseInt(usage.limit, 10);
        }

        return {
            used: isNaN(used) ? 0 : Math.max(0, used),
            limit: isNaN(limit) ? 0 : Math.max(0, limit),
            daysLeft: isNaN(daysLeft) ? null : Math.max(0, daysLeft),
            resetDate: credits.resetDate || '',
            upgradeUrl: (localized.urls && localized.urls.upgrade) || getUpgradeUrl(),
            missingCount: localized.counts && typeof localized.counts.missing !== 'undefined'
                ? Math.max(0, parseInt(localized.counts.missing, 10) || 0)
                : null,
            isLocked: !!localized.isLocked
        };
    }

    function isCreditsLocked() {
        var adminState = getAdminCreditsState();
        if (adminState.isLocked) {
            return true;
        }
        if (adminState.limit > 0 && adminState.used >= adminState.limit) {
            return true;
        }
        return isOutOfCreditsFromUsage();
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
        if (normalizedReason === 'generate_missing' || normalizedReason === 'generate-missing') {
            return __('Generate Missing Alt Text (Upgrade required)', 'beepbeep-ai-alt-text-generator');
        }
        if (normalizedReason === 'reoptimize_all' || normalizedReason === 'reoptimize-all' || normalizedReason === 'regenerate-all') {
            return __('Re-optimise All Alt Text (Upgrade required)', 'beepbeep-ai-alt-text-generator');
        }
        if (normalizedReason === 'regenerate_single' || normalizedReason === 'regenerate-single') {
            return __('Regen (Upgrade required)', 'beepbeep-ai-alt-text-generator');
        }

        if (element && String(element.textContent || '').toLowerCase().indexOf('generate missing') !== -1) {
            return __('Generate Missing Alt Text (Upgrade required)', 'beepbeep-ai-alt-text-generator');
        }
        if (element && String(element.textContent || '').toLowerCase().indexOf('re-optim') !== -1) {
            return __('Re-optimise All Alt Text (Upgrade required)', 'beepbeep-ai-alt-text-generator');
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

        if (lockedLabel) {
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
        var spans = element.querySelectorAll('span');
        if (!spans.length) {
            return null;
        }
        return spans[spans.length - 1];
    }

    function convertControlToUpgradeRequired(element) {
        if (!element || !isGenerationActionControl(element)) {
            return;
        }

        var currentAction = element.getAttribute('data-action');
        var currentBbaiAction = element.getAttribute('data-bbai-action');
        if (!element.hasAttribute('data-bbai-original-action')) {
            element.setAttribute('data-bbai-original-action', currentAction ? currentAction : '__none__');
        }
        if (!element.hasAttribute('data-bbai-original-bbai-action')) {
            element.setAttribute('data-bbai-original-bbai-action', currentBbaiAction ? currentBbaiAction : '__none__');
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
        element.setAttribute('data-bbai-action', 'open-upgrade');
        element.setAttribute('data-bbai-locked-cta', '1');
        element.setAttribute('aria-disabled', 'true');
        if (!element.getAttribute('data-bbai-locked-source')) {
            element.setAttribute('data-bbai-locked-source', 'dashboard');
        }
        element.removeAttribute('data-action');
        element.setAttribute('data-bbai-tooltip', __('Upgrade required to continue this action this month.', 'beepbeep-ai-alt-text-generator'));
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
        element.setAttribute('data-bbai-lock-reason', reason);
        applyLockedActionState(element, reason);
    }

    function restoreControlFromUpgradeRequired(element) {
        if (!element || !element.classList.contains('bbai-upgrade-required-action')) {
            return;
        }

        var originalAction = element.getAttribute('data-bbai-original-action');
        var originalBbaiAction = element.getAttribute('data-bbai-original-bbai-action');
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
        } else if (element.getAttribute('data-bbai-action') === 'open-upgrade') {
            element.removeAttribute('data-bbai-action');
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

    function renderLimitReachedUI(state, forceRefresh) {
        var dashboardContainer = document.getElementById('bbai-dashboard-main') || document.querySelector('[data-bbai-dashboard-container]');
        if (!dashboardContainer) {
            return;
        }

        var root = document.getElementById('bbai-limit-state-root');
        if (!root) {
            root = document.createElement('section');
            root.id = 'bbai-limit-state-root';
            root.className = 'bbai-limit-state-root';
            dashboardContainer.insertBefore(root, dashboardContainer.firstChild);
        }

        if (state !== 'limit_reached') {
            root.hidden = true;
            root.innerHTML = '';
            bbaiLimitStateViewedTracked = false;
            return;
        }

        var usage = getUsageForQuotaChecks() || {};
        var optimized = getOptimizedCountFromStats();
        if (!optimized) {
            optimized = Math.max(0, parseInt(usage.used, 10) || 0);
        }
        var missing = getMissingCountFromStats();

        root.hidden = false;
        root.innerHTML = '' +
            '<div class="bbai-limit-state-card">' +
            '  <h3 class="bbai-limit-state-title">' +
            escapeHtml(sprintf(__('You optimized %d images', 'beepbeep-ai-alt-text-generator'), optimized)) +
            ' &#127881;</h3>' +
            '  <p class="bbai-limit-state-subtitle">' +
            escapeHtml(sprintf(__('%d images still missing alt text', 'beepbeep-ai-alt-text-generator'), missing)) +
            '</p>' +
            '  <button type="button" class="bbai-btn bbai-btn-primary bbai-limit-state-cta bbai-upgrade-required-action" data-bbai-action="open-upgrade" data-bbai-intended-action="generate-missing" data-bbai-locked-cta="1" data-bbai-locked-source="limit-state-continue">' +
            escapeHtml(__('Continue optimizing (Upgrade required)', 'beepbeep-ai-alt-text-generator')) +
            '</button>' +
            '</div>' +
            '<div class="bbai-limit-state-next">' +
            '  <h4 class="bbai-limit-state-next-title">' + escapeHtml(__('Next images to optimize', 'beepbeep-ai-alt-text-generator')) + '</h4>' +
            '  <div class="bbai-limit-state-preview" data-bbai-limit-preview>' +
            '    <p class="bbai-limit-state-loading">' + escapeHtml(__('Loading image preview…', 'beepbeep-ai-alt-text-generator')) + '</p>' +
            '  </div>' +
            '</div>';

        if (!bbaiLimitStateViewedTracked) {
            bbaiLimitStateViewedTracked = true;
            trackDashboardEvent('bbai_limit_state_viewed', { source: 'dashboard' });
        }

        var previewTarget = root.querySelector('[data-bbai-limit-preview]');
        getLimitPreviewData(!!forceRefresh).done(function(previewData) {
            if (getDashboardState() !== 'limit_reached') {
                return;
            }
            var latestMissingCount = parseInt(previewData && previewData.missing_count, 10);
            if (!isNaN(latestMissingCount)) {
                var subtitleNode = root.querySelector('.bbai-limit-state-subtitle');
                if (subtitleNode) {
                    subtitleNode.textContent = sprintf(
                        __('%d images still missing alt text', 'beepbeep-ai-alt-text-generator'),
                        latestMissingCount
                    );
                }
            }
            renderLimitPreviewItems(previewTarget, previewData);
        });
    }

    function applyDashboardState(forcePreviewRefresh) {
        var state = getDashboardState();
        var selector = [
            '[data-action="generate-missing"]',
            '[data-action="regenerate-all"]',
            '[data-bbai-action="generate_missing"]',
            '[data-bbai-action="reoptimize_all"]',
            '[data-bbai-action="open-upgrade"][data-bbai-intended-action]'
        ].join(', ');

        document.querySelectorAll(selector).forEach(function(control) {
            if (state === 'limit_reached') {
                convertControlToUpgradeRequired(control);
            } else {
                restoreControlFromUpgradeRequired(control);
            }
        });

        renderLimitReachedUI(state, !!forcePreviewRefresh);
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
            var adminState = getAdminCreditsState();
            var upgradeUrl = adminState.upgradeUrl || getUpgradeUrl();
            if (upgradeUrl) {
                window.open(upgradeUrl, '_blank', 'noopener,noreferrer');
            }
        });

        return wrapper;
    }

    function openLockedUpgradeModal(reason, context) {
        var modalNode = ensureLockedUpgradeModal();
        if (!modalNode) {
            return false;
        }

        var adminState = getAdminCreditsState();
        var usageLimit = adminState.limit > 0 ? adminState.limit : 50;
        var usageCopy = sprintf(
            __('You\'ve used %d free credits this month.', 'beepbeep-ai-alt-text-generator'),
            usageLimit
        );
        var bodyNode = modalNode.querySelector('#bbai-locked-upgrade-body');
        if (bodyNode) {
            bodyNode.textContent = usageCopy;
        }

        var upgradeUrl = adminState.upgradeUrl || getUpgradeUrl();
        var primaryLink = modalNode.querySelector('.bbai-locked-upgrade-modal__primary');
        if (primaryLink) {
            primaryLink.setAttribute('href', upgradeUrl || '#');
        }

        bbaiLockedModalState.lastTrigger = context && context.trigger ? context.trigger : document.activeElement;
        modalNode.classList.add('is-visible');
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
        return openLockedUpgradeModal(reason, context || {});
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

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            openUpgradeModal(reason, {
                source: getLockedCtaSource(trigger),
                trigger: trigger
            });
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
        if (hasReasonContext) {
            if (openLockedUpgradeModal(reasonOrUsage, context || {})) {
                return true;
            }
        }

        var usage = reasonOrUsage;
        if (hasVisibleLimitModal()) {
            return true;
        }

        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            if (window.bbaiOpenUpgradeModal() !== false) {
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
        }
        if (bbaiLockedModalState.keyHandlerBound) {
            document.removeEventListener('keydown', handleLockedUpgradeModalKeys, true);
            bbaiLockedModalState.keyHandlerBound = false;
        }

        var modal = getUpgradeModalElement();
        if (modal) {
            modal.classList.remove('active');
            modal.classList.remove('is-visible');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
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

        openUpgradeModal(null);
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

        if (code === 'limit_reached' || code === 'quota_exhausted') {
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
            message.indexOf('out of credits') !== -1;
    }

    function handleTrialExhausted(errorData) {
        // Ensure the error code is set so handleLimitReached routes to the trial branch
        if (!errorData) errorData = {};
        errorData.code = 'bbai_trial_exhausted';
        handleLimitReached(errorData);
    }

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
    }

    function showLimitFallbackDialog(message, usage, canManage) {
        var noticeMessage = String(message || __('Monthly quota reached.', 'beepbeep-ai-alt-text-generator'));
        var promptMessage = noticeMessage + '\n\n' + __('Press OK to open upgrade plans now, or Cancel to wait for your monthly reset.', 'beepbeep-ai-alt-text-generator');

        if (canManage && typeof window.confirm === 'function') {
            var shouldUpgradeNow = window.confirm(promptMessage);
            if (shouldUpgradeNow) {
                openUpgradeDestination(usage);
            }
            return;
        }

        if (typeof showNotification === 'function') {
            showNotification(noticeMessage, 'warning');
        } else if (typeof window.alert === 'function') {
            window.alert(noticeMessage);
        }
    }

    function getUsageSnapshot(errorUsage) {
        return errorUsage ||
            (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
            (window.BBAI_DASH && window.BBAI_DASH.usage) ||
            (window.BBAI && window.BBAI.usage) ||
            null;
    }

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

        var source = getLockedCtaSource(trigger);
        var usage = getUsageSnapshot(null);

        trackDashboardEvent('bbai_locked_cta_clicked', { source: source });

        var modalOpened = openUpgradeModal(usage);
        if (!modalOpened) {
            modalOpened = openUpgradeDestination(usage);
        }

        if (modalOpened) {
            trackDashboardEvent('bbai_upgrade_modal_opened', { source: source });
        }

        return false;
    }

    function handleLimitReached(errorData) {
        var normalizedError = normalizeLimitErrorData(errorData);
        var errorCode = normalizedError && normalizedError.code ? String(normalizedError.code) : '';
        var usage = getUsageSnapshot(normalizedError && normalizedError.usage ? normalizedError.usage : null);
        var isTrialExhausted = errorCode === 'bbai_trial_exhausted';
        var canManage = canManageAccount();

        clearModalAndScrollLocks();
        applyDashboardState(false);

        if (isTrialExhausted && canManageAccount() && window.bbaiModal && typeof window.bbaiModal.show === 'function') {
            try {
                window.bbaiModal.show({
                    type: 'warning',
                    title: __("You've used your 10 free generations", 'beepbeep-ai-alt-text-generator'),
                    message: __('Create a free account to unlock 50 more credits per month', 'beepbeep-ai-alt-text-generator'),
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
                            text: __('View plans', 'beepbeep-ai-alt-text-generator'),
                            primary: false,
                            action: function() {
                                window.bbaiModal.close();
                                openUpgradeModal(usage);
                            }
                        }
                    ]
                });
                ensureVisibleLimitModalOrFallback(__('You have used your free generations. Create an account or upgrade to continue.', 'beepbeep-ai-alt-text-generator'), usage, canManage);
                if (window.bbaiModal && window.bbaiModal.activeModal) {
                    return;
                }
            } catch (modalError) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] Failed to show trial exhausted modal', modalError);
            }

            showLimitFallbackDialog(__('You have used your free generations. Create an account or upgrade to continue.', 'beepbeep-ai-alt-text-generator'), usage, canManage);
            return;
        }

        var baseMessage = (normalizedError && normalizedError.message) || __('You have used all available free credits for this month.', 'beepbeep-ai-alt-text-generator');
        var quotaResetMeta = getQuotaResetMeta(usage);
        var resetMessage = __('Your next 50 free credits will be available at the next monthly reset.', 'beepbeep-ai-alt-text-generator');

        if (quotaResetMeta.daysUntilReset !== null) {
            if (quotaResetMeta.daysUntilReset <= 0) {
                resetMessage = __('Your next 50 free credits should be available today.', 'beepbeep-ai-alt-text-generator');
            } else {
                resetMessage = sprintf(
                    _n(
                        'Your next 50 free credits will be available in %d day.',
                        'Your next 50 free credits will be available in %d days.',
                        quotaResetMeta.daysUntilReset,
                        'beepbeep-ai-alt-text-generator'
                    ),
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
                        title: __('Monthly quota reached', 'beepbeep-ai-alt-text-generator'),
                        message: fullMessage + '\n\n' + __('Upgrade now to keep generating alt text immediately.', 'beepbeep-ai-alt-text-generator'),
                        buttons: [
                            {
                                text: __('Upgrade now', 'beepbeep-ai-alt-text-generator'),
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
                        title: __('Monthly quota reached', 'beepbeep-ai-alt-text-generator'),
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
        var requestedScope = scope === 'all' ? 'all' : 'missing';
        var requestedLimit = parseInt(limit, 10);
        if (isNaN(requestedLimit) || requestedLimit <= 0) {
            requestedLimit = 500;
        }

        var restUrl = '';
        if (requestedScope === 'all') {
            restUrl = config.restAll || ((config.restRoot || '') + 'bbai/v1/list?scope=all');
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

    /**
     * Generate alt text for missing images
     */
    function handleGenerateMissing(e) {
        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        clearModalAndScrollLocks();
        var $btn = $(this);
        var originalText = $btn.text();

        if ($btn.prop('disabled')) {
            return false;
        }

        // Check if we have necessary configuration
        if (!hasBulkConfig) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
            return false;
        }

        // Check trial exhaustion first
        var trialData = window.BBAI_DASH && window.BBAI_DASH.trial;
        if (trialData && trialData.is_trial && trialData.exhausted) {
            handleTrialExhausted({
                message: __('You\'ve used your free trial generations. Create a free account to continue.', 'beepbeep-ai-alt-text-generator'),
                code: 'bbai_trial_exhausted'
            });
            return false;
        }

        // Check if user is out of credits BEFORE starting
        // First, try to get fresh usage stats from API if available
        var usageStats = (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
                         (window.BBAI_DASH && window.BBAI_DASH.usage) ||
                         (window.BBAI && window.BBAI.usage) || null;

        // Check if remaining is 0 or less, or if used >= limit
        var remaining = usageStats && (usageStats.remaining !== undefined) ? parseInt(usageStats.remaining, 10) : null;
        var used = usageStats && (usageStats.used !== undefined) ? parseInt(usageStats.used, 10) : null;
        var limit = usageStats && (usageStats.limit !== undefined) ? parseInt(usageStats.limit, 10) : null;
        var plan = usageStats && usageStats.plan ? usageStats.plan.toLowerCase() : 'free';

        // Check if user has quota OR is on premium plan (pro/agency)
        // Only show modal when remaining is explicitly 0 (not null/undefined)
        var isPremium = plan === 'pro' || plan === 'agency';
        var hasQuota = remaining !== null && remaining !== undefined && remaining > 0;
        var isOutOfCredits = remaining !== null && remaining !== undefined && remaining === 0;

        // Safety check: If we have credits remaining (> 0), NEVER show modal
        if (remaining !== null && remaining !== undefined && remaining > 0) {
            // User has credits - continue without checking anything else
            continueWithGeneration();
            return false;
        }

        // If no usage stats available, don't block - let the API handle it
        if (!usageStats) {
            continueWithGeneration();
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
            $btn.prop('disabled', true);
            $btn.text(__('Loading...', 'beepbeep-ai-alt-text-generator'));

            // Get list of images missing alt text (REST with admin-ajax fallback)
            fetchBulkImageIds('missing', 500)
            .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
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
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
            var count = ids.length;

            // Show progress bar
	            showBulkProgress(__('Preparing bulk run...', 'beepbeep-ai-alt-text-generator'), count, 0);

            // Queue all images
            queueImages(ids, 'bulk', { skipSchedule: true }, function(success, queued, error, processedIds) {
                $btn.prop('disabled', false);
                $btn.text(originalText);

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

                    startInlineGeneration(processedIds || ids, 'bulk');

                    // Don't hide modal - let user close it manually or monitor progress
	                } else if (success && queued === 0) {
	                    updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
	                    logBulkProgressSuccess(__('All images are already in queue or processing', 'beepbeep-ai-alt-text-generator'));
	                    startInlineGeneration(processedIds || ids, 'bulk');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for quota errors FIRST - show upgrade modal immediately
                    if (error && (error.code === 'limit_reached' || error.code === 'bbai_trial_exhausted' || error.code === 'quota_exhausted')) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Check for insufficient credits with 0 remaining - show upgrade modal
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining === 0) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Show error in modal log
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
                        var totalRequested = count;
                        var errorMsg = error.message || sprintf(_n('You only have %d generation remaining.', 'You only have %d generations remaining.', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount);
                        var modalMessage = errorMsg + '\n\n' + sprintf(
                            _n(
                                'You can generate %1$d image now using your remaining credit, or upgrade for more.',
                                'You can generate %1$d images now using your remaining credits, or upgrade for more.',
                                remainingCount,
                                'beepbeep-ai-alt-text-generator'
                            ),
                            remainingCount
                        );

                        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                            window.bbaiModal.show({
                                type: 'warning',
                                title: __('Not enough credits', 'beepbeep-ai-alt-text-generator'),
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
                                        text: __('Upgrade now', 'beepbeep-ai-alt-text-generator'),
                                        primary: false,
                                        action: function() {
                                            window.bbaiModal.close();
                                            openUpgradeModal(getUsageSnapshot(error.usage || null));
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
                $btn.prop('disabled', false);
                $btn.text(originalText);

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
        var originalText = $btn.text();

        if ($btn.prop('disabled')) {
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

        // Show confirmation via modal instead of native confirm() to avoid page freezing
        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
            window.bbaiModal.show({
                type: 'warning',
                title: __('Re-optimise All Images', 'beepbeep-ai-alt-text-generator'),
                message: __('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?', 'beepbeep-ai-alt-text-generator'),
                buttons: [
                    {
                        text: __('Yes, re-optimise all', 'beepbeep-ai-alt-text-generator'),
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
        if (!confirm(__('This will regenerate alt text for ALL images, replacing existing alt text. Are you sure?', 'beepbeep-ai-alt-text-generator'))) {
            return false;
        }
        proceedWithRegeneration();
        return false;

        function proceedWithRegeneration() {
        $btn.prop('disabled', true);
        $btn.text(__('Loading...', 'beepbeep-ai-alt-text-generator'));

        // Get list of all images (REST with admin-ajax fallback)
        fetchBulkImageIds('all', 500)
        .done(function(response) {
            if (!response || !response.ids || response.ids.length === 0) {
                window.bbaiModal.info(__('No images found.', 'beepbeep-ai-alt-text-generator'));
                $btn.prop('disabled', false);
                $btn.text(originalText);
                return;
            }

            var ids = response.ids || [];
	            var count = ids.length;

	            // Show progress bar
	            showBulkProgress(__('Preparing bulk regeneration...', 'beepbeep-ai-alt-text-generator'), count, 0);

            // Queue all images
            queueImages(ids, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, error, processedIds) {
                $btn.prop('disabled', false);
                $btn.text(originalText);

	                if (success && queued > 0) {
	                    // Update modal to show success and keep it open
	                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
	                    logBulkProgressSuccess(sprintf(_n('Successfully queued %d image for regeneration', 'Successfully queued %d images for regeneration', queued, 'beepbeep-ai-alt-text-generator'), queued));

                    // Trigger celebration for bulk regeneration
                    if (window.bbaiCelebrations && typeof window.bbaiCelebrations.showConfetti === 'function') {
                        window.bbaiCelebrations.showConfetti();
                    }
	                    if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
	                        window.bbaiPushToast('success', sprintf(_n('Successfully queued %d image for regeneration!', 'Successfully queued %d images for regeneration!', queued, 'beepbeep-ai-alt-text-generator'), queued), { duration: 5000 });
	                    }

                    // Dispatch custom event for celebrations
                    var event = new CustomEvent('bbai:generation:success', { detail: { count: queued, type: 'bulk-regenerate' } });
                    document.dispatchEvent(event);

                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');

                    // Don't hide modal - let user close it manually or monitor progress
	                } else if (success && queued === 0) {
	                    updateBulkProgressTitle(__('Already Queued', 'beepbeep-ai-alt-text-generator'));
	                    logBulkProgressSuccess(__('All images are already in queue or processing', 'beepbeep-ai-alt-text-generator'));
	                    startInlineGeneration(processedIds || ids, 'bulk-regenerate');

                    // Don't hide modal - let user close it manually
                } else {
                    // Check for quota errors FIRST - show upgrade modal immediately
                    if (error && (error.code === 'limit_reached' || error.code === 'bbai_trial_exhausted' || error.code === 'quota_exhausted')) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Check for insufficient credits with 0 remaining - show upgrade modal
                    if (error && error.code === 'insufficient_credits' && error.remaining !== null && error.remaining === 0) {
                        hideBulkProgress();
                        handleLimitReached(error);
                        return; // Exit early - don't show bulk progress modal
                    }

                    // Show error in modal log
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
                        var totalRequested = count;
                        var errorMsg = error.message || sprintf(_n('You only have %d generation remaining.', 'You only have %d generations remaining.', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount);
                        var modalMessage = errorMsg + '\n\n' + sprintf(
                            _n(
                                'You can regenerate %1$d image now using your remaining credit, or upgrade for more.',
                                'You can regenerate %1$d images now using your remaining credits, or upgrade for more.',
                                remainingCount,
                                'beepbeep-ai-alt-text-generator'
                            ),
                            remainingCount
                        );

                        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
                            window.bbaiModal.show({
                                type: 'warning',
                                title: __('Not enough credits', 'beepbeep-ai-alt-text-generator'),
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
                                            showBulkProgress(sprintf(_n('Queueing %d image for regeneration...', 'Queueing %d images for regeneration...', remainingCount, 'beepbeep-ai-alt-text-generator'), remainingCount), remainingCount, 0);

                                            queueImages(limitedIds, 'bulk-regenerate', { skipSchedule: true }, function(success, queued, queueError, processedLimited) {
                                                $btn.prop('disabled', false);
                                                $btn.text(originalText);

                                                if (success && queued > 0) {
                                                    updateBulkProgressTitle(__('Successfully Queued!', 'beepbeep-ai-alt-text-generator'));
                                                    logBulkProgressSuccess(sprintf(_n('Queued %d image using remaining credits', 'Queued %d images using remaining credits', queued, 'beepbeep-ai-alt-text-generator'), queued));
                                                    startInlineGeneration(processedLimited || limitedIds, 'bulk-regenerate');
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
                                        text: __('Upgrade now', 'beepbeep-ai-alt-text-generator'),
                                        primary: false,
                                        action: function() {
                                            window.bbaiModal.close();
                                            openUpgradeModal(getUsageSnapshot(error.usage || null));
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
            $btn.prop('disabled', false);
            $btn.text(originalText);

            logBulkProgressError(__('Failed to load images. Please try again.', 'beepbeep-ai-alt-text-generator'));
            // Keep modal open to show error - user can close manually
        });
        } // end proceedWithRegeneration
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

        // Hard-stop single regenerate when free credits are exhausted.
        if (isOutOfCreditsFromUsage() || isLockedBulkControl($btn.get(0))) {
            return handleLockedCtaClick($btn.get(0), e);
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

        if ($btn.prop('disabled')) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] Cannot regenerate - button is disabled');
            return false;
        }

        // Disable the button immediately to prevent multiple clicks
        var originalText = $btn.text();
        $btn.prop('disabled', true).addClass('regenerating');
        $btn.text(__('Processing...', 'beepbeep-ai-alt-text-generator'));

        // Get image info - handle both table view (media library) and form view (edit media page)
        var imageTitle = __('Image', 'beepbeep-ai-alt-text-generator');
        var defaultImageTitle = imageTitle;
        var imageSrc = '';

        // Try to find in table row (media library view)
        var $row = $btn.closest('tr');
        if ($row.length) {
            imageTitle = $row.find('.bbai-table__cell--title').text().trim() || imageTitle;
            imageSrc = $row.find('img').attr('src') || imageSrc;
        }

        // If not found, try to find in form (edit media page)
        if (!imageSrc || imageTitle === defaultImageTitle) {
            // Try to get from attachment details form
            var $form = $btn.closest('form');
            if ($form.length) {
                // Try to get title from various possible locations
                var $titleInput = $form.find('input[name="post_title"]');
                if ($titleInput.length) {
                    imageTitle = $titleInput.val() || imageTitle;
                }

                // Try to get image preview from attachment details
                var $preview = $form.find('img.attachment-thumbnail, img.attachment-preview, .attachment-preview img, #postimagediv img');
                if ($preview.length) {
                    imageSrc = $preview.attr('src') || $preview.attr('data-src') || imageSrc;
                }
            }
        }

        // If still no image, try to get from attachment details area
        if (!imageSrc) {
            var $attachmentDetails = $('#attachment-details, .attachment-details');
            if ($attachmentDetails.length) {
                var $img = $attachmentDetails.find('img');
                if ($img.length) {
                    imageSrc = $img.attr('src') || $img.attr('data-src') || imageSrc;
                }
            }
        }

        // Show modal (imageSrc can be empty - modal will handle it)
        showRegenerateModal(attachmentId, imageTitle, imageSrc, $btn, originalText);
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

        // Disable accept button during loading
        $modal.find('.bbai-regenerate-modal__btn--accept').prop('disabled', true);

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        // Prevent stale AJAX responses from older regenerate requests from mutating the active modal state.
        var requestKey = 'regen-' + attachmentId + '-' + Date.now();
        $modal.data('bbai-request-key', requestKey);

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
            data: {
                action: 'beepbeepai_regenerate_single',
                attachment_id: attachmentId,
                request_key: requestKey,
                nonce: nonceValue
            },
            timeout: 20000
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

            if (response && response.success) {
                // Backend returns altText (camelCase), support both for compatibility
                var newAltText = (response.data && response.data.altText) || (response.data && response.data.alt_text) || response.altText || response.alt_text || '';
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] New alt text:', newAltText);
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Alt text length:', newAltText.length);
                window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Full response:', response);

                if (newAltText) {
                    // Show result
                    $modal.find('.bbai-regenerate-modal__alt-text').text(newAltText);
                    $modal.find('.bbai-regenerate-modal__result').addClass('active');

                    // Update usage from response if available (avoids extra API call)
                    var usageInResponse = (response.data && response.data.usage) || response.usage;
                    if (usageInResponse && typeof usageInResponse === 'object') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Updating usage from response:', usageInResponse);
                        if (window.BBAI_DASH) {
                            window.BBAI_DASH.usage = usageInResponse;
                            window.BBAI_DASH.initialUsage = usageInResponse;
                        }
                        if (window.BBAI) {
                            window.BBAI.usage = usageInResponse;
                        }

                        // Update display immediately
                        if (typeof window.alttextai_refresh_usage === 'function') {
                            // Pass usage data directly to avoid API call
                            window.alttextai_refresh_usage(usageInResponse);
                        } else if (typeof refreshUsageStats === 'function') {
                            refreshUsageStats(usageInResponse);
                        }
                    } else {
                        // Fallback: Refresh usage stats from API
                        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] No usage in response, fetching from API');
                        if (typeof refreshUsageStats === 'function') {
                            refreshUsageStats();
                        } else if (typeof window.alttextai_refresh_usage === 'function') {
                            window.alttextai_refresh_usage();
                        }
                    }

                    // Update trial usage client-side
                    if (typeof window.bbaiUpdateTrialUsage === 'function') {
                        window.bbaiUpdateTrialUsage();
                    }

                    // Enable accept button
                    $modal.find('.bbai-regenerate-modal__btn--accept')
                        .prop('disabled', false)
                        .off('click')
                        .on('click', function() {
                            acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal);
                        });
                } else {
                    showModalError($modal, __('No alt text was generated. Please try again.', 'beepbeep-ai-alt-text-generator'));
                    reenableButton($btn, originalBtnText);
                }
            } else {
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
                    var errorMessage = errorData.message || __('Failed to regenerate alt text', 'beepbeep-ai-alt-text-generator');

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
                var message = errorData.message || __('Failed to regenerate alt text. Please try again.', 'beepbeep-ai-alt-text-generator');
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
    }

    /**
     * Close regenerate modal
     */
    function closeRegenerateModal($modal) {
        $modal.removeData('bbai-request-key');
        $modal.removeClass('active');
        clearBodyScrollLocks();
    }

    /**
     * Re-enable the regenerate button
     */
    function reenableButton($btn, originalText) {
        $btn.prop('disabled', false).removeClass('regenerating');
        $btn.text(originalText);
    }

    /**
     * Calculate SEO quality score for alt text (client-side version)
     */
    function calculateSeoQuality(text) {
        if (!text || text.trim() === '') {
            return { score: 0, grade: 'F', badge: 'missing' };
        }

        var score = 100;
        var textLength = text.length;

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

        // Check for descriptive content (at least 3 words)
        var words = text.trim().split(/\s+/);
        if (words.length < 3) {
            score -= 15;
        }

        score = Math.max(0, score);

        var grade, badge;
        if (score >= 90) { grade = 'A'; badge = 'excellent'; }
        else if (score >= 75) { grade = 'B'; badge = 'good'; }
        else if (score >= 60) { grade = 'C'; badge = 'fair'; }
        else if (score >= 40) { grade = 'D'; badge = 'poor'; }
        else { grade = 'F'; badge = 'needs-work'; }

        return { score: score, grade: grade, badge: badge };
    }

    /**
     * Accept regenerated alt text and update the UI
     */
    function acceptRegeneratedAltText(attachmentId, newAltText, $btn, originalBtnText, $modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Accepting new alt text');

        // Update the alt text in the table
        var $row = $btn.closest('tr');

        if (newAltText) {
            var $altCell = $row.find('.bbai-library-cell--alt-text');

            if ($altCell.length) {
                var safeAlt = $('<div>').text(newAltText).html();
                var truncated = newAltText.length > 80 ? newAltText.substring(0, 77) + '…' : newAltText;
                var safeTruncated = $('<div>').text(truncated).html();
                var charCount = newAltText.length;
                var isOptimal = charCount <= 125;
                var counterClass = isOptimal ? 'bbai-char-counter--optimal' : 'bbai-char-counter--warning';
                var counterTooltip = isOptimal
                    ? __('Optimal length for Google Images SEO', 'beepbeep-ai-alt-text-generator')
                    : __('Consider shortening to 125 chars or less', 'beepbeep-ai-alt-text-generator');

                // Calculate SEO quality
                var seoQuality = calculateSeoQuality(newAltText);
                var seoBadgeHtml = '';
                if (seoQuality.badge !== 'missing') {
                    var seoScoreLabel = sprintf(
                        /* translators: 1: SEO grade letter, 2: SEO score out of 100 */
                        __('SEO Score: %1$s (%2$d/100)', 'beepbeep-ai-alt-text-generator'),
                        seoQuality.grade,
                        seoQuality.score
                    );
                    var seoPrefix = __('SEO:', 'beepbeep-ai-alt-text-generator');
                    seoBadgeHtml = '<span class="bbai-meta-separator">○</span>' +
                        '<span class="bbai-seo-badge bbai-seo-badge--' + seoQuality.badge + '" data-bbai-tooltip="' + escapeHtml(seoScoreLabel) + '" data-bbai-tooltip-position="top">' + escapeHtml(seoPrefix) + ' ' + seoQuality.grade + '</span>' +
                        '<span class="bbai-meta-separator">○</span>';
                }

                // Build the full alt text cell content with metrics
                var cellHtml =
                    '<div class="bbai-alt-text-content">' +
                        '<div class="bbai-alt-text-preview" title="' + safeAlt + '">' + safeTruncated + '</div>' +
                        '<div class="bbai-alt-text-meta">' +
                            '<span class="' + counterClass + '" data-bbai-tooltip="' + counterTooltip + '" data-bbai-tooltip-position="top">' + charCount + '/125</span>' +
                            seoBadgeHtml +
                        '</div>' +
                    '</div>';

                $altCell.html(cellHtml);

                // Update status badge to "Regenerated"
                var $statusCell = $row.find('.bbai-library-cell--status span');
                if ($statusCell.length) {
                    $statusCell
                        .removeClass()
                        .addClass('bbai-status-badge bbai-status-badge--regenerated')
                        .text(__('Regenerated', 'beepbeep-ai-alt-text-generator'));
                }

                // Update row data attribute
                $row.attr('data-status', 'regenerated');
            }
        }

        // Show success message in toast notification before closing modal
        if (window.bbaiPushToast && typeof window.bbaiPushToast === 'function') {
            window.bbaiPushToast('success', __('Alt text updated successfully!', 'beepbeep-ai-alt-text-generator'), { duration: 4000 });
        } else if (window.bbaiToast && typeof window.bbaiToast.success === 'function') {
            window.bbaiToast.success(__('Alt text updated successfully!', 'beepbeep-ai-alt-text-generator'), { duration: 4000 });
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
        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
        }
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
                   code === 'quota_exhausted' || code === 'insufficient_credits';
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

    /**
     * Begin inline generation after queue completes.
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

        $modal.data('startTime', Date.now());
        $modal.data('total', normalized.length);
        $modal.data('current', 0);
        $modal.data('successes', 0);
        $modal.data('failed', 0);
        $modal.find('.bbai-bulk-progress__total').text(normalized.length);
        $modal.find('.bbai-bulk-progress__current').text(0);
        $modal.find('.bbai-bulk-progress__percentage').text('0%');
        $modal.find('.bbai-bulk-progress__eta').text(__('Calculating...', 'beepbeep-ai-alt-text-generator'));
        $modal.find('.bbai-bulk-progress__bar-fill').css('width', '0%');

        var intro = sprintf(
            _n(
                'Starting inline generation for %d image...',
                'Starting inline generation for %d images...',
                normalized.length,
                'beepbeep-ai-alt-text-generator'
            ),
            normalized.length
        );
        updateBulkProgressTitle(__('Generating Alt Text…', 'beepbeep-ai-alt-text-generator'));
        logBulkProgressSuccess(intro);

        $modal.data('batchQueue', normalized.slice(0));
        var inlineBatchSize = window.BBAI && window.BBAI.inlineBatchSize
            ? Math.max(1, parseInt(window.BBAI.inlineBatchSize, 10))
            : 1;
        processInlineGenerationQueue(normalized, inlineBatchSize);
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
        var processed = 0;
        var successes = 0;
        var failures = 0;
        var active = 0;
        var blockedByQuota = false;

        function processNext() {
            if (blockedByQuota && active === 0) {
                hideBulkProgress();
                return;
            }

            if (!queue.length && active === 0) {
                finalizeInlineGeneration(successes, failures);
                return;
            }

            if (active >= batchSize || !queue.length) {
                return;
            }

            var id = queue.shift();
            active++;

            generateAltTextForId(id)
                .then(function(result) {
                    successes++;
                    processed++;
                    // Update trial usage client-side
                    if (typeof window.bbaiUpdateTrialUsage === 'function') {
                        window.bbaiUpdateTrialUsage();
                    }
                    var title = result && result.title
                        ? result.title
                        : sprintf(__('Generated alt text for image #%d', 'beepbeep-ai-alt-text-generator'), id);
                    updateBulkProgress(processed, total, title);
                })
                .catch(function(error) {
                    var errorCode = error && error.code ? String(error.code) : '';
                    var isQuotaError = errorCode === 'limit_reached' ||
                                      errorCode === 'bbai_trial_exhausted' ||
                                      errorCode === 'quota_exhausted' ||
                                      (errorCode === 'insufficient_credits' && (!error.remaining || error.remaining === 0));
                    if (isQuotaError) {
                        blockedByQuota = true;
                        queue = [];
                        handleLimitReached(error || { code: errorCode });
                        return;
                    }

                    failures++;
                    processed++;
                    var fallbackError = __('Failed to generate alt text.', 'beepbeep-ai-alt-text-generator');
                    var details = (error && error.message) ? error.message : fallbackError;
                    var message = sprintf(__('Image #%d: %s', 'beepbeep-ai-alt-text-generator'), id, details);
                    logBulkProgressError(message);
                    updateBulkProgress(processed, total);
                })
                .finally(function() {
                    active--;
                    if (blockedByQuota) {
                        if (active === 0) {
                            hideBulkProgress();
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

    function finalizeInlineGeneration(successes, failures) {
        var $modal = $('#bbai-bulk-progress-modal');
        var total = successes + failures;
        var startTime = $modal.length ? $modal.data('startTime') : Date.now();
        var elapsed = (Date.now() - startTime) / 1000; // seconds

	        // Calculate time saved (estimate: 2 minutes per image)
	        var timeSavedMinutes = successes * 2;
	        var timeSavedHours = Math.round(timeSavedMinutes / 60);
	        var timeSavedText = timeSavedHours > 0
	            ? sprintf(
	                _n('%d hour', '%d hours', timeSavedHours, 'beepbeep-ai-alt-text-generator'),
	                timeSavedHours
	            )
	            : __('< 1 hour', 'beepbeep-ai-alt-text-generator');

        // Calculate AI confidence (100% if no failures, otherwise percentage)
        var confidence = total > 0 ? Math.round((successes / total) * 100) : 100;

        // Hide progress modal
        hideBulkProgress();

        // Show success modal
        setTimeout(function() {
            showSuccessModal({
                processed: successes,
                total: total,
                failures: failures,
                timeSaved: timeSavedText,
                confidence: confidence
            });
        }, 300);

        if (typeof refreshUsageStats === 'function') {
            refreshUsageStats();
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
                data: {
                    action: 'beepbeepai_inline_generate',
                    attachment_ids: [id],
                    nonce: nonceValue
                }
            })
            .done(function(response) {
                // Handle successful HTTP response (status 200)
                try {
                    // Handle successful response
                    if (response && response.success) {
                        // Check for results array (inline generate format)
                        if (response.data && response.data.results && Array.isArray(response.data.results)) {
                            var first = response.data.results[0];
                            if (first && first.success) {
                                resolve({
                                    id: id,
                                    alt: first.alt_text || '',
                                    title: first.title || sprintf(__('Image #%d', 'beepbeep-ai-alt-text-generator'), id)
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
                                reject({ message: errorMsg, code: (first && first.code) ? first.code : 'generation_failed' });
                                return;
                            }
                        }
                        // Check for direct alt_text (regenerate single format)
                        else if (response.data && response.data.alt_text) {
                            resolve({
                                id: id,
                                alt: response.data.alt_text || '',
                                title: sprintf(__('Image #%d', 'beepbeep-ai-alt-text-generator'), id)
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
                        reject({ message: errorMsg, code: (response.data && response.data.code) || response.code || 'api_error' });
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

                reject({ message: message, code: errorCode });
            });
        });
    }

    /**
     * Show bulk progress modal with detailed tracking
     */
    function showBulkProgress(label, total, current) {
        var $modal = $('#bbai-bulk-progress-modal');

        // Create modal if it doesn't exist
        if (!$modal.length) {
            $modal = createBulkProgressModal();
        }

        // Initialize progress tracking
        $modal.data('startTime', Date.now());
        $modal.data('total', total);
        $modal.data('current', current || 0);

        // Update initial state
        $modal.find('.bbai-bulk-progress__title').text(label || __('Processing Images...', 'beepbeep-ai-alt-text-generator'));
        $modal.find('.bbai-bulk-progress__total').text(total);
        $modal.find('.bbai-bulk-progress__log').empty();

        // Show modal
        $modal.addClass('active');
        $('body').css('overflow', 'hidden');

        updateBulkProgress(current || 0, total);
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
            '            <h2 class="bbai-bulk-progress__title">' + escapeHtml(__('Processing Images...', 'beepbeep-ai-alt-text-generator')) + '</h2>' +
            '            <button type="button" class="bbai-bulk-progress__close" aria-label="' + escapeHtml(__('Close', 'beepbeep-ai-alt-text-generator')) + '">×</button>' +
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
            '            <div class="bbai-bulk-progress__log-container">' +
            '                <h3 class="bbai-bulk-progress__log-title">' + escapeHtml(__('Processing Log', 'beepbeep-ai-alt-text-generator')) + '</h3>' +
            '                <div class="bbai-bulk-progress__log"></div>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        $('body').append(modalHtml);
        var $modal = $('#bbai-bulk-progress-modal');

        // Add close button handler
        $modal.find('.bbai-bulk-progress__close').on('click', function() {
            hideBulkProgress();
        });

        return $modal;
    }

    /**
     * Update bulk progress bar with detailed stats
     */
    function updateBulkProgress(current, total, imageTitle) {
        var $modal = $('#bbai-bulk-progress-modal');
        if (!$modal.length) return;

        var percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        var startTime = $modal.data('startTime') || Date.now();
        var elapsed = (Date.now() - startTime) / 1000; // seconds

        // Calculate ETA
        var eta = __('Calculating...', 'beepbeep-ai-alt-text-generator');
        if (current > 0 && elapsed > 0) {
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

        // Update stats
        $modal.find('.bbai-bulk-progress__current').text(current);
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
     * Hide bulk progress bar
     */
    function hideBulkProgress() {
        var $modal = $('#bbai-bulk-progress-modal');
        if ($modal.length) {
            $modal.removeClass('active');
        }
        clearBodyScrollLocks();
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
            '            <div class="bbai-modal-success__summary-text">' + escapeHtml(__('All images were processed successfully.', 'beepbeep-ai-alt-text-generator')) + '</div>' +
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
            $summary.find('.bbai-modal-success__summary-text').text(__('All images were processed successfully.', 'beepbeep-ai-alt-text-generator'));
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
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').first().prepend($notice);

        // Auto-dismiss after 5 seconds
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
            openUpgradeModal(getUsageSnapshot(null));
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
                    renderWizard();
                })
                .catch(function(error) {
                    state.scan.loading = false;
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
    window.bbaiRegenerateSingle = function(e) {
        return handleRegenerateSingle.call(this || document.body, e || { preventDefault: function() {}, stopPropagation: function() {} });
    };

    function handleDelegatedAdminClick(e) {
        var trigger = e.target && e.target.closest
            ? e.target.closest('[data-bbai-action], [data-action], #bbai-batch-regenerate')
            : null;

        if (!trigger) {
            return;
        }

        var bbaiAction = String(trigger.getAttribute('data-bbai-action') || '').toLowerCase();
        if (bbaiAction === 'open-upgrade') {
            handleLockedCtaClick(trigger, e);
            return;
        }

        var action = trigger.getAttribute('data-action') || '';
        if (action === 'generate-missing') {
            if (isOutOfCreditsFromUsage() || isLockedBulkControl(trigger)) {
                handleLockedCtaClick(trigger, e);
                return;
            }
            handleGenerateMissing.call(trigger, e);
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
        if (action === 'deactivate-license') {
            handleLicenseDeactivation.call(trigger, e);
        }
    }

    function bindDelegatedAdminHandlers() {
        if (bbaiDelegatedHandlersBound) {
            return;
        }

        var eventRoot = document.getElementById('wpbody-content') || document.body || document;
        eventRoot.addEventListener('click', handleDelegatedAdminClick, false);
        bbaiDelegatedHandlersBound = true;
    }

    /**
     * JS Activation Smoke Plan:
     * 1. Fresh install: run 3-image demo and confirm instant-win rows render.
     * 2. No missing images: demo returns friendly upload suggestion.
     * 3. Trial exhausted: upgrade CTA remains clickable, no disabled primary CTA.
     * 4. Rapid repeated clicks: single in-flight request, no duplicate processing.
     */
    function init() {
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
        bindLockedAction('[data-bbai-action="generate_missing"]', 'generate_missing');
        bindLockedAction('[data-bbai-action="reoptimize_all"]', 'reoptimize_all');
        bindLockedAction('#bbai-batch-regenerate', 'reoptimize_all');
        bindLockedAction('[data-bbai-locked-cta="1"]', 'upgrade_required');

        bindDelegatedAdminHandlers();
        bindOutOfCreditsClickGuard();
        enforceOutOfCreditsBulkLocks();
        applyDashboardState(false);
        setTimeout(function() {
            enforceOutOfCreditsBulkLocks();
            applyDashboardState(false);
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

        $(document)
            .off('bbai:regenerate-all.bbaiBridge')
            .on('bbai:regenerate-all.bbaiBridge', function() {
                var target = getActionButton('regenerate-all') || document.body;
                handleRegenerateAll.call(target, { preventDefault: function() {} });
            });

        $(document)
            .off('bbai:usage-updated.bbaiDashboardState bbai:stats-updated.bbaiDashboardState')
            .on('bbai:usage-updated.bbaiDashboardState bbai:stats-updated.bbaiDashboardState', function(event) {
                if (event && event.type === 'bbai:stats-updated') {
                    bbaiLimitPreviewCache = null;
                }
                enforceOutOfCreditsBulkLocks();
                applyDashboardState(event && event.type === 'bbai:stats-updated');
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

    $(init);

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
        if (!response || typeof response !== 'object') {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Invalid usage data for display:', response);
            return;
        }

        // Update the usage data in global object
        if (window.BBAI_DASH) {
            window.BBAI_DASH.usage = response;
            window.BBAI_DASH.initialUsage = response;
        }
        if (window.BBAI) {
            window.BBAI.usage = response;
        }

        // Extract usage values - handle both direct response and nested data
        var used = response.used !== undefined ? response.used : 0;
        var limit = response.limit !== undefined ? response.limit : 50;
        var remaining = response.remaining !== undefined ? response.remaining : (limit - used);

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
                if ($el.text().trim() !== percentageDisplay) {
                    var oldPercent = $el.text().trim();
                    $el.removeData('bbai-animated');
                    $el.text(percentageDisplay);
                    $el.fadeOut(100, function() {
                        $(this).fadeIn(100);
                    });
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Updated circular progress percent from', oldPercent, 'to', percentageDisplay);
                }
            });

            // Update circular progress bar visual (stroke-dashoffset + adaptive color)
            $('.bbai-circular-progress-bar').each(function() {
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

            // Update linear progress bar color to match
            $('.bbai-linear-progress-fill').each(function() {
                var $bar = $(this);
                var barColor = '#10B981';
                if (percentage >= 100) {
                    barColor = '#EF4444';
                } else if (percentage >= 86) {
                    barColor = '#EF4444';
                } else if (percentage >= 61) {
                    barColor = '#F59E0B';
                }
                $bar.css('background', barColor);
            });
        }

        applyDashboardState(false);
        $(document).trigger('bbai:usage-updated');
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Usage display update complete');
    }

    // Also create a global refreshUsageStats function for compatibility
    if (typeof window.refreshUsageStats === 'undefined') {
        window.refreshUsageStats = window.alttextai_refresh_usage;
    }

})(jQuery);
