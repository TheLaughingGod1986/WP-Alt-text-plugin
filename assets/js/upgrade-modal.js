/**
 * AI Alt Text Upgrade Modal JavaScript
 * Handles upgrade modal open/close and event listeners
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function() {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function(text) { return text; };
    var _n = i18n && typeof i18n._n === 'function' ? i18n._n : function(single, plural, number) {
        return number === 1 ? single : plural;
    };
    var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function(format) { return format; };
    var TRIGGER_COOLDOWN_PREFIX = 'bbai_upgrade_trigger_cooldown_';
    var TRIGGER_DISMISS_PREFIX = 'bbai_upgrade_trigger_dismiss_';
    var CHECKOUT_PENDING_KEY = 'bbai_upgrade_pending_checkout';
    var CHECKOUT_SUCCESS_KEY = 'bbai_upgrade_completed_';
    var DEFAULT_COOLDOWN_MS = 30 * 60 * 1000;

    var upgradeModalState = {
        keyHandlerBound: false,
        lastTrigger: null,
        activeRequest: null
    };

    var observedUpgradeState = {
        scanTimestamp: 0,
        candidateCount: 0
    };

    var triggerConfigs = {
        scan_completion: {
            reason: 'scan_completion',
            cooldownMs: DEFAULT_COOLDOWN_MS
        },
        new_image_upload: {
            reason: 'new_image_upload',
            cooldownMs: DEFAULT_COOLDOWN_MS
        },
        usage_80: {
            reason: 'usage_80',
            cooldownMs: DEFAULT_COOLDOWN_MS
        },
        limit_reached: {
            reason: 'limit_reached',
            cooldownMs: 5 * 60 * 1000
        }
    };

    function bbaiString(value) {
        return value === undefined || value === null ? '' : String(value);
    }

    function parseCount(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? 0 : parsed;
    }

    function getLocalStorageSafe() {
        try {
            return window.localStorage;
        } catch (e) {
            return null;
        }
    }

    function getSessionStorageSafe() {
        try {
            return window.sessionStorage;
        } catch (e) {
            return null;
        }
    }

    function mergeObjects(target) {
        var merged = target || {};
        var index;
        var source;
        var key;

        for (index = 1; index < arguments.length; index++) {
            source = arguments[index];
            if (!source || typeof source !== 'object') {
                continue;
            }

            for (key in source) {
                if (Object.prototype.hasOwnProperty.call(source, key)) {
                    merged[key] = source[key];
                }
            }
        }

        return merged;
    }

    function getUsageSnapshot(context) {
        if (context && context.usage && typeof context.usage === 'object') {
            return context.usage;
        }

        if (window.BBAI_DASH) {
            if (window.BBAI_DASH.initialUsage && typeof window.BBAI_DASH.initialUsage === 'object') {
                return window.BBAI_DASH.initialUsage;
            }
            if (window.BBAI_DASH.usage && typeof window.BBAI_DASH.usage === 'object') {
                return window.BBAI_DASH.usage;
            }
        }

        if (window.BBAI_UPGRADE && window.BBAI_UPGRADE.usage && typeof window.BBAI_UPGRADE.usage === 'object') {
            return window.BBAI_UPGRADE.usage;
        }

        return null;
    }

    function isPremiumUsage(usage) {
        var plan = bbaiString(usage && usage.plan).toLowerCase();
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');

        if (usage && (usage.isPremium || usage.is_premium)) {
            return true;
        }

        if (plan && plan !== 'free') {
            return true;
        }

        return !!(root && root.getAttribute('data-bbai-is-premium') === '1');
    }

    function canManageUpgradeFlow() {
        if (typeof window.bbaiCanManageAccount === 'function') {
            return !!window.bbaiCanManageAccount();
        }

        if (window.BBAI && typeof window.BBAI.canManage !== 'undefined') {
            return !!window.BBAI.canManage;
        }

        if (window.BBAI_UPGRADE && typeof window.BBAI_UPGRADE.canManage !== 'undefined') {
            return !!window.BBAI_UPGRADE.canManage;
        }

        return true;
    }

    function dispatchUpgradeEvent(name, payload) {
        try {
            document.dispatchEvent(new CustomEvent(name, { detail: payload || {} }));
        } catch (e) {
            // Ignore dispatch failures.
        }
    }

    function trackUpgradeEvent(eventName, payload) {
        var detail = mergeObjects(
            {
                event: eventName,
                timestamp: Date.now(),
                source: 'upgrade-modal',
                trigger: 'unknown',
                reason: 'default'
            },
            payload || {}
        );
        var ajaxUrl = (window.BBAI_UPGRADE && window.BBAI_UPGRADE.ajaxurl) ||
            (window.bbai_ajax && (window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl)) ||
            '';
        var nonce = (window.BBAI_UPGRADE && window.BBAI_UPGRADE.nonce) ||
            (window.bbai_ajax && window.bbai_ajax.nonce) ||
            '';

        dispatchUpgradeEvent('bbai:analytics', detail);

        if (window.dataLayer && typeof window.dataLayer.push === 'function') {
            window.dataLayer.push(detail);
        }

        if (!ajaxUrl || !nonce) {
            return;
        }

        if (window.jQuery && typeof window.jQuery.ajax === 'function') {
            window.jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'beepbeepai_track_upgrade',
                    nonce: nonce,
                    event_name: eventName,
                    source: detail.source || 'upgrade-modal',
                    trigger: detail.trigger || 'unknown',
                    plan: detail.plan || ''
                }
            });
            return;
        }

        if (window.fetch) {
            window.fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: [
                    'action=beepbeepai_track_upgrade',
                    'nonce=' + encodeURIComponent(nonce),
                    'event_name=' + encodeURIComponent(eventName),
                    'source=' + encodeURIComponent(detail.source || 'upgrade-modal'),
                    'trigger=' + encodeURIComponent(detail.trigger || 'unknown'),
                    'plan=' + encodeURIComponent(detail.plan || '')
                ].join('&')
            }).catch(function() {
                // Ignore tracking failures.
            });
        }
    }

    function isGenerationActionControl(element) {
        if (!element) {
            return false;
        }

        var action = bbaiString(element.getAttribute && element.getAttribute('data-action')).toLowerCase();
        var bbaiAction = bbaiString(element.getAttribute && element.getAttribute('data-bbai-action')).toLowerCase();
        var className = bbaiString(element.className).toLowerCase();
        var label = (bbaiString(element.getAttribute && element.getAttribute('aria-label')) + ' ' + bbaiString(element.textContent)).toLowerCase();

        if (action === 'generate-missing' || action === 'regenerate-all' || action === 'regenerate-single') {
            return true;
        }
        if (bbaiAction === 'generate_missing' || bbaiAction === 'reoptimize_all') {
            return true;
        }
        if (className.indexOf('bbai-optimization-cta') !== -1 ||
            className.indexOf('bbai-action-btn-primary') !== -1 ||
            className.indexOf('bbai-action-btn-secondary') !== -1 ||
            className.indexOf('bbai-dashboard-btn--primary') !== -1 ||
            className.indexOf('bbai-dashboard-btn--secondary') !== -1) {
            return true;
        }
        return label.indexOf('generate missing') !== -1 ||
            label.indexOf('regenerate') !== -1 ||
            label.indexOf('re-optim') !== -1 ||
            label.indexOf('reoptimiz') !== -1;
    }

    function isLockedActionControl(element) {
        if (!element) {
            return false;
        }

        var className = bbaiString(element.className).toLowerCase();
        var hint = (bbaiString(element.getAttribute && element.getAttribute('title')) + ' ' + bbaiString(element.getAttribute && element.getAttribute('data-bbai-tooltip'))).toLowerCase();

        return !!(
            element.disabled ||
            (element.getAttribute && (element.getAttribute('aria-disabled') === 'true' || element.getAttribute('data-bbai-lock-control') === '1')) ||
            className.indexOf('disabled') !== -1 ||
            className.indexOf('bbai-optimization-cta--disabled') !== -1 ||
            className.indexOf('bbai-optimization-cta--locked') !== -1 ||
            hint.indexOf('out of credits') !== -1 ||
            hint.indexOf('unlock more generations') !== -1 ||
            hint.indexOf('monthly quota') !== -1
        );
    }

    function resolveUpgradeModalElement() {
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

    function setUpgradeModalScrollLock(isLocked) {
        if (!document.body || !document.body.classList) {
            return;
        }

        document.body.classList.toggle('modal-open', !!isLocked);
        document.body.classList.toggle('bbai-modal-open', !!isLocked);
    }

    function isUpgradeModalVisible(modal) {
        if (!modal) {
            return false;
        }

        if (modal.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        return modal.classList.contains('active') ||
            modal.classList.contains('is-visible') ||
            bbaiString(modal.style.display).toLowerCase() === 'flex';
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

    function getDefaultAgencyExpandedState(modal) {
        if (!modal) {
            return false;
        }

        var toggle = modal.querySelector('[data-bbai-upgrade-toggle-agency="1"]');
        if (toggle) {
            return toggle.getAttribute('data-bbai-upgrade-initial-expanded') === 'true';
        }

        var panel = modal.querySelector('[data-bbai-upgrade-agency-panel]');
        return !!(panel && !panel.hidden);
    }

    function updateAgencyComparisonState(modal, expanded) {
        if (!modal) {
            return;
        }

        var panel = modal.querySelector('[data-bbai-upgrade-agency-panel]');
        var toggle = modal.querySelector('[data-bbai-upgrade-toggle-agency="1"]');
        if (!panel) {
            return;
        }

        var isExpanded = !!expanded;
        panel.hidden = !isExpanded;
        modal.setAttribute('data-bbai-upgrade-agency-visible', isExpanded ? 'true' : 'false');

        if (!toggle) {
            return;
        }

        toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

        var showLabel = toggle.getAttribute('data-bbai-upgrade-show-label') || toggle.textContent;
        var hideLabel = toggle.getAttribute('data-bbai-upgrade-hide-label') || showLabel;
        toggle.textContent = isExpanded ? hideLabel : showLabel;
    }

    function resetAgencyComparisonState(modal) {
        updateAgencyComparisonState(modal, getDefaultAgencyExpandedState(modal));
    }

    function setUpgradeModalView(modal, view) {
        if (!modal) {
            return;
        }

        var defaultPanel = modal.querySelector('[data-bbai-upgrade-view-panel="default"]');
        var comparison = modal.querySelector('[data-bbai-upgrade-view-panel="compare"]');
        var toggle = modal.querySelector('[data-bbai-upgrade-toggle-plans="1"]');
        if (!defaultPanel || !comparison) {
            return;
        }

        var activeView = view === 'compare' ? 'compare' : 'default';
        var isCompareView = activeView === 'compare';

        defaultPanel.hidden = isCompareView;
        comparison.hidden = !isCompareView;
        modal.setAttribute('data-bbai-upgrade-view', activeView);

        if (toggle) {
            toggle.setAttribute('aria-expanded', isCompareView ? 'true' : 'false');
        }
    }

    function updatePlanComparisonState(modal, expanded) {
        setUpgradeModalView(modal, expanded ? 'compare' : 'default');
    }

    function focusUpgradeModalTarget(modal) {
        if (!modal) {
            return;
        }

        var isCompareView = modal.getAttribute('data-bbai-upgrade-view') === 'compare';
        var target = isCompareView
            ? modal.querySelector('[data-bbai-upgrade-growth-cta="1"]') ||
                modal.querySelector('[data-bbai-upgrade-back="1"]') ||
                modal.querySelector('[data-bbai-upgrade-toggle-agency="1"]')
            : modal.querySelector('[data-bbai-upgrade-primary-action="1"]') ||
                modal.querySelector('[data-bbai-upgrade-secondary-action="1"]') ||
                modal.querySelector('[data-bbai-upgrade-toggle-plans="1"]');

        target = target ||
            modal.querySelector('.bbai-upgrade-modal__close') ||
            modal.querySelector('.bbai-upgrade-modal__content');

        if (target && typeof target.focus === 'function') {
            target.focus();
        }
    }

    function handleUpgradeModalKeydown(event) {
        var modal = resolveUpgradeModalElement();
        if (!event || !isUpgradeModalVisible(modal)) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            window.bbaiCloseUpgradeModal();
            return;
        }

        if (event.key === 'Tab') {
            var content = modal.querySelector('.bbai-upgrade-modal__content') || modal;
            trapFocusWithin(content, event);
        }
    }

    function setModalNodeText(node, value) {
        if (!node || value === undefined || value === null) {
            return;
        }

        node.textContent = String(value);
    }

    function getUpgradeModalCopy(modal) {
        if (!modal) {
            return null;
        }

        if (!modal.__bbaiUpgradeCopy) {
            var titleNode = modal.querySelector('[data-bbai-upgrade-title]');
            var subtitleNode = modal.querySelector('[data-bbai-upgrade-subtitle]');
            var eyebrowNode = modal.querySelector('[data-bbai-upgrade-eyebrow]');
            var decisionTitleNode = modal.querySelector('[data-bbai-upgrade-decision-title]');
            var decisionDescNode = modal.querySelector('[data-bbai-upgrade-decision-desc]');
            var noteNode = modal.querySelector('[data-bbai-upgrade-note]');

            modal.__bbaiUpgradeCopy = {
                nodes: {
                    title: titleNode,
                    subtitle: subtitleNode,
                    eyebrow: eyebrowNode,
                    decisionTitle: decisionTitleNode,
                    decisionDesc: decisionDescNode,
                    note: noteNode
                },
                defaults: {
                    title: modal.getAttribute('data-bbai-upgrade-default-title') || (titleNode ? titleNode.textContent : ''),
                    subtitle: modal.getAttribute('data-bbai-upgrade-default-subtitle') || (subtitleNode ? subtitleNode.textContent : ''),
                    eyebrow: modal.getAttribute('data-bbai-upgrade-default-eyebrow') || (eyebrowNode ? eyebrowNode.textContent : ''),
                    decisionTitle: modal.getAttribute('data-bbai-upgrade-default-decision-title') || (decisionTitleNode ? decisionTitleNode.textContent : ''),
                    decisionDesc: modal.getAttribute('data-bbai-upgrade-default-decision-desc') || (decisionDescNode ? decisionDescNode.textContent : ''),
                    note: modal.getAttribute('data-bbai-upgrade-default-note') || (noteNode ? noteNode.textContent : '')
                },
                locked: {
                    title: modal.getAttribute('data-bbai-upgrade-locked-title') || '',
                    subtitle: modal.getAttribute('data-bbai-upgrade-locked-subtitle') || '',
                    eyebrow: modal.getAttribute('data-bbai-upgrade-locked-eyebrow') || '',
                    decisionTitle: modal.getAttribute('data-bbai-upgrade-locked-decision-title') || '',
                    decisionDesc: modal.getAttribute('data-bbai-upgrade-locked-decision-desc') || '',
                    note: modal.getAttribute('data-bbai-upgrade-locked-note') || ''
                }
            };
        }

        return modal.__bbaiUpgradeCopy;
    }

    function getTriggerLabel(triggerKey) {
        return triggerConfigs[triggerKey] ? triggerKey : 'unknown';
    }

    function getAutomaticPromptVariant(reason, context, baseVariant) {
        var usage = getUsageSnapshot(context);
        var variant = mergeObjects({}, baseVariant);
        var remaining = Math.max(0, parseCount(usage && (usage.remaining !== undefined ? usage.remaining : (usage.limit || 0) - (usage.used || 0))));
        var used = Math.max(0, parseCount(usage && usage.used));
        var limit = Math.max(1, parseCount(usage && usage.limit));
        var percent = Math.min(100, Math.max(0, parseCount(context && context.usagePercent) || Math.round((used / limit) * 100)));
        var pendingCount = Math.max(
            0,
            parseCount(context && context.pendingCount) ||
            parseCount(context && context.uploadCount) ||
            parseCount(context && context.missingCount) + parseCount(context && context.weakCount)
        );
        var pendingCountLabel = pendingCount > 0
            ? sprintf(
                _n('%s image', '%s images', pendingCount, 'beepbeep-ai-alt-text-generator'),
                pendingCount
            )
            : __('your latest images', 'beepbeep-ai-alt-text-generator');

        if (reason === 'scan_completion') {
            variant.eyebrow = __('New images found', 'beepbeep-ai-alt-text-generator');
            variant.title = sprintf(
                _n(
                    'Your latest scan found %s that still needs ALT text.',
                    'Your latest scan found %s that still need ALT text.',
                    pendingCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                pendingCountLabel
            );
            variant.subtitle = __('Enable automatic optimisation so future uploads are handled the moment they arrive.', 'beepbeep-ai-alt-text-generator');
            variant.note = __('Buy credits if you only need a one-off top-up for this scan.', 'beepbeep-ai-alt-text-generator');
            return variant;
        }

        if (reason === 'new_image_upload') {
            variant.eyebrow = __('New upload detected', 'beepbeep-ai-alt-text-generator');
            variant.title = sprintf(
                _n(
                    '%s is ready for automatic ALT text.',
                    '%s are ready for automatic ALT text.',
                    pendingCount,
                    'beepbeep-ai-alt-text-generator'
                ),
                pendingCountLabel
            );
            variant.subtitle = __('Enable automatic optimisation so every new image is optimised as soon as it is uploaded.', 'beepbeep-ai-alt-text-generator');
            variant.note = __('Buy credits if you prefer to keep uploads manual for now.', 'beepbeep-ai-alt-text-generator');
            return variant;
        }

        if (reason === 'usage_80') {
            variant.eyebrow = __('Approaching your limit', 'beepbeep-ai-alt-text-generator');
            variant.title = sprintf(
                __('You have used %s%% of your monthly credits.', 'beepbeep-ai-alt-text-generator'),
                percent
            );
            variant.subtitle = sprintf(
                _n(
                    'Only %s credit is left before new uploads stop being optimised automatically.',
                    'Only %s credits are left before new uploads stop being optimised automatically.',
                    remaining,
                    'beepbeep-ai-alt-text-generator'
                ),
                remaining
            );
            variant.note = __('Buy credits for a quick top-up, or upgrade to keep everything running automatically.', 'beepbeep-ai-alt-text-generator');
            return variant;
        }

        if (reason === 'limit_reached') {
            variant.eyebrow = __('Usage limit reached', 'beepbeep-ai-alt-text-generator');
            variant.title = __('You have used all of this month\'s credits.', 'beepbeep-ai-alt-text-generator');
            variant.subtitle = bbaiString(context && context.resetMessage) ||
                __('Enable automatic optimisation or buy credits to keep new uploads moving.', 'beepbeep-ai-alt-text-generator');
            variant.note = bbaiString(context && context.limitMessage) ||
                __('Your existing modal actions are still available below.', 'beepbeep-ai-alt-text-generator');
            return variant;
        }

        return variant;
    }

    function applyUpgradeModalContext(modal, reason, context) {
        var copy = getUpgradeModalCopy(modal);
        if (!copy) {
            return;
        }

        var isLockedContext = typeof reason === 'string' && reason !== '' && reason !== 'default';
        var baseVariant = isLockedContext ? copy.locked : copy.defaults;
        var variant = getAutomaticPromptVariant(reason, context, baseVariant);
        var decisionDesc = variant.decisionDesc;

        modal.setAttribute('data-bbai-upgrade-context', reason || (isLockedContext ? 'locked' : 'default'));
        setModalNodeText(copy.nodes.title, variant.title);
        setModalNodeText(copy.nodes.subtitle, variant.subtitle);
        setModalNodeText(copy.nodes.eyebrow, variant.eyebrow);
        setModalNodeText(copy.nodes.decisionTitle, variant.decisionTitle);
        setModalNodeText(copy.nodes.decisionDesc, decisionDesc);
        setModalNodeText(copy.nodes.note, variant.note);
    }

    function resetUpgradeModalContext(modal) {
        applyUpgradeModalContext(modal, 'default', null);
    }

    function normalizeUpgradeOpenRequest(reasonOrContext, maybeContext) {
        if (typeof reasonOrContext === 'string') {
            return {
                reason: reasonOrContext || 'default',
                context: maybeContext && typeof maybeContext === 'object' ? maybeContext : {}
            };
        }

        if (reasonOrContext && typeof reasonOrContext === 'object') {
            return {
                reason: reasonOrContext.reason || (reasonOrContext.locked ? 'upgrade_required' : 'default'),
                context: reasonOrContext
            };
        }

        return {
            reason: 'default',
            context: {}
        };
    }

    function hasSessionDismissal(triggerKey) {
        var storage = getSessionStorageSafe();
        return !!(storage && storage.getItem(TRIGGER_DISMISS_PREFIX + triggerKey) === '1');
    }

    function setSessionDismissal(triggerKey) {
        var storage = getSessionStorageSafe();
        if (!storage) {
            return;
        }

        storage.setItem(TRIGGER_DISMISS_PREFIX + triggerKey, '1');
    }

    function isTriggerCoolingDown(triggerKey) {
        var storage = getLocalStorageSafe();
        var cooldownUntil;

        if (!storage) {
            return false;
        }

        cooldownUntil = parseCount(storage.getItem(TRIGGER_COOLDOWN_PREFIX + triggerKey));
        return cooldownUntil > Date.now();
    }

    function setTriggerCooldown(triggerKey, durationMs) {
        var storage = getLocalStorageSafe();
        if (!storage) {
            return;
        }

        storage.setItem(TRIGGER_COOLDOWN_PREFIX + triggerKey, String(Date.now() + Math.max(0, parseCount(durationMs))));
    }

    function shouldSuppressAutomaticPrompt(triggerKey, context) {
        var modal = resolveUpgradeModalElement();

        if (!triggerConfigs[triggerKey]) {
            return true;
        }

        if (!context || !context.force) {
            if (!canManageUpgradeFlow() || isPremiumUsage(getUsageSnapshot(context))) {
                return true;
            }

            if (hasSessionDismissal(triggerKey) || isTriggerCoolingDown(triggerKey)) {
                return true;
            }
        }

        if (isUpgradeModalVisible(modal)) {
            return true;
        }

        return false;
    }

    function normalizeStatsPayload(rawStats) {
        var stats = rawStats && typeof rawStats === 'object' ? rawStats : {};

        return {
            missingCount: Math.max(0, parseCount(stats.images_missing_alt !== undefined ? stats.images_missing_alt : stats.missing)),
            weakCount: Math.max(0, parseCount(stats.needs_review_count !== undefined ? stats.needs_review_count : stats.weak)),
            scannedAt: Math.max(0, parseCount(
                stats.scanned_at !== undefined ? stats.scanned_at :
                    (stats.scannedAt !== undefined ? stats.scannedAt :
                        (stats.last_scan_ts !== undefined ? stats.last_scan_ts : stats.lastScanTs))
            ))
        };
    }

    function syncUsageSnapshot(usage) {
        if (window.BBAI_DASH && usage && typeof usage === 'object') {
            window.BBAI_DASH.initialUsage = usage;
        }
        if (window.BBAI_UPGRADE && usage && typeof usage === 'object') {
            window.BBAI_UPGRADE.usage = usage;
        }
    }

    function maybeTriggerUsagePrompt(usage) {
        var safeUsage = usage && typeof usage === 'object' ? usage : getUsageSnapshot();
        var used = Math.max(0, parseCount(safeUsage && safeUsage.used));
        var limit = Math.max(1, parseCount(safeUsage && safeUsage.limit));
        var percent = Math.min(100, Math.max(0, Math.round((used / limit) * 100)));
        var remaining = safeUsage && safeUsage.remaining !== undefined
            ? Math.max(0, parseCount(safeUsage.remaining))
            : Math.max(0, limit - used);

        if (percent < 80 || remaining <= 0) {
            return false;
        }

        return window.bbaiTriggerUpgradePrompt('usage_80', {
            usage: safeUsage,
            usagePercent: percent,
            source: 'upgrade-trigger'
        });
    }

    function initializeObservedScanState() {
        var initialStats = normalizeStatsPayload(window.BBAI_DASH && window.BBAI_DASH.stats ? window.BBAI_DASH.stats : null);
        var root = document.querySelector('[data-bbai-dashboard-root="1"]');

        observedUpgradeState.scanTimestamp = initialStats.scannedAt || Math.max(0, parseCount(root && root.getAttribute('data-bbai-last-scan-ts')));
        observedUpgradeState.candidateCount = initialStats.missingCount + initialStats.weakCount;
    }

    function handleStatsUpdate(detail) {
        var normalizedStats = normalizeStatsPayload(detail && detail.stats ? detail.stats : detail);
        var usage = detail && detail.usage && typeof detail.usage === 'object' ? detail.usage : null;
        var nextCandidateCount = normalizedStats.missingCount + normalizedStats.weakCount;

        if (usage) {
            syncUsageSnapshot(usage);
            maybeTriggerUsagePrompt(usage);
        }

        if (normalizedStats.scannedAt > observedUpgradeState.scanTimestamp && nextCandidateCount > 0) {
            window.bbaiTriggerUpgradePrompt('scan_completion', {
                pendingCount: nextCandidateCount,
                missingCount: normalizedStats.missingCount,
                weakCount: normalizedStats.weakCount,
                usage: usage || getUsageSnapshot(),
                source: 'upgrade-trigger'
            });
        }

        if (normalizedStats.scannedAt > 0) {
            observedUpgradeState.scanTimestamp = normalizedStats.scannedAt;
        }
        observedUpgradeState.candidateCount = nextCandidateCount;
    }

    function bindWordPressUploadTrigger() {
        if (!(window.wp && wp.Uploader && wp.Uploader.queue && typeof wp.Uploader.queue.on === 'function')) {
            return;
        }

        wp.Uploader.queue.on('add', function(attachment) {
            var type = bbaiString(attachment && attachment.get ? attachment.get('type') : '').toLowerCase();
            var attachmentId = attachment && attachment.get ? parseCount(attachment.get('id')) : 0;

            if (type && type !== 'image') {
                return;
            }

            window.setTimeout(function() {
                window.bbaiTriggerUpgradePrompt('new_image_upload', {
                    attachmentId: attachmentId,
                    uploadCount: 1,
                    source: 'upgrade-trigger'
                });
            }, 800);
        });
    }

    function consumePendingUploadTrigger() {
        var pending = window.BBAI_DASH &&
            window.BBAI_DASH.pendingUpgradeTriggers &&
            window.BBAI_DASH.pendingUpgradeTriggers.newUpload;

        if (!pending || parseCount(pending.uploadCount) <= 0) {
            return;
        }

        window.BBAI_DASH.pendingUpgradeTriggers.newUpload = {
            uploadCount: 0,
            attachmentId: 0,
            createdAt: 0
        };

        window.bbaiTriggerUpgradePrompt('new_image_upload', {
            uploadCount: parseCount(pending.uploadCount),
            attachmentId: parseCount(pending.attachmentId),
            source: 'upgrade-trigger'
        });
    }

    function maybeTrackCompletedUpgrade() {
        var storage = getSessionStorageSafe();
        var params;
        var completionKey;
        var pendingCheckout;

        if (!window.location || typeof window.URLSearchParams !== 'function') {
            return;
        }

        params = new URLSearchParams(window.location.search);
        if (params.get('checkout') !== 'success') {
            return;
        }

        completionKey = CHECKOUT_SUCCESS_KEY + window.location.search;
        if (storage && storage.getItem(completionKey) === '1') {
            return;
        }

        pendingCheckout = null;
        if (storage) {
            try {
                pendingCheckout = JSON.parse(storage.getItem(CHECKOUT_PENDING_KEY) || 'null');
            } catch (e) {
                pendingCheckout = null;
            }
        }

        trackUpgradeEvent('upgrade_completed', {
            source: 'checkout',
            trigger: pendingCheckout && pendingCheckout.trigger ? pendingCheckout.trigger : 'unknown',
            reason: pendingCheckout && pendingCheckout.reason ? pendingCheckout.reason : 'default',
            plan: pendingCheckout && pendingCheckout.plan ? pendingCheckout.plan : 'unknown'
        });

        if (storage) {
            storage.setItem(completionKey, '1');
            storage.removeItem(CHECKOUT_PENDING_KEY);
        }
    }

    window.bbaiTriggerUpgradePrompt = function(triggerKey, context) {
        var config = triggerConfigs[triggerKey];
        var requestContext;
        var opened;

        if (!config) {
            return false;
        }

        requestContext = mergeObjects(
            {
                autoTrigger: true,
                triggerKey: getTriggerLabel(triggerKey),
                source: 'upgrade-trigger'
            },
            context || {}
        );

        if (shouldSuppressAutomaticPrompt(triggerKey, requestContext)) {
            return false;
        }

        opened = window.bbaiOpenUpgradeModal(config.reason, requestContext);
        if (opened !== false) {
            setTriggerCooldown(triggerKey, config.cooldownMs);
            return true;
        }

        return false;
    };

    function initUpgradeUsageCalculator() {
        var calculators = document.querySelectorAll('[data-bbai-upgrade-calculator]');
        if (!calculators || !calculators.length) {
            return;
        }

        calculators.forEach(function(calculator) {
            if (!calculator || calculator.getAttribute('data-calculator-bound') === '1') {
                return;
            }

            var input = calculator.querySelector('[data-bbai-upgrade-input]');
            var recommendation = calculator.querySelector('[data-bbai-upgrade-recommendation]');
            if (!input || !recommendation) {
                return;
            }

            calculator.setAttribute('data-calculator-bound', '1');

            var updateRecommendation = function() {
                var value = parseInt(input.value, 10);
                if (isNaN(value) || value < 0) {
                    recommendation.textContent = 'Enter an estimate to get a recommendation.';
                    recommendation.style.color = '';
                    recommendation.style.fontWeight = '';
                    return;
                }

                if (value > 50) {
                    recommendation.textContent = 'More than 50 images without alt text usually means upgrading to Growth is the better fit.';
                    recommendation.style.color = '#047857';
                    recommendation.style.fontWeight = '600';
                    return;
                }

                recommendation.textContent = 'Your estimate fits within the Free plan limit.';
                recommendation.style.color = '';
                recommendation.style.fontWeight = '';
            };

            input.addEventListener('input', updateRecommendation);
            updateRecommendation();
        });
    }

    /**
     * Open the upgrade modal
     * @returns {boolean} True if modal was opened successfully
     */
    window.bbaiOpenUpgradeModal = function(reasonOrContext, maybeContext) {
        var modal = resolveUpgradeModalElement();
        var modalAlreadyVisible;
        var triggerKey;

        if (!modal) {
            return false;
        }

        var request = normalizeUpgradeOpenRequest(reasonOrContext, maybeContext);
        modalAlreadyVisible = isUpgradeModalVisible(modal);
        triggerKey = request.context && request.context.triggerKey ? request.context.triggerKey : 'manual';

        if (modalAlreadyVisible &&
            upgradeModalState.activeRequest &&
            upgradeModalState.activeRequest.reason === request.reason &&
            bbaiString(upgradeModalState.activeRequest.context && upgradeModalState.activeRequest.context.triggerKey) === bbaiString(triggerKey)) {
            return true;
        }

        upgradeModalState.lastTrigger = request.context && request.context.trigger ? request.context.trigger : document.activeElement;
        upgradeModalState.activeRequest = request;
        applyUpgradeModalContext(modal, request.reason, request.context);
        resetAgencyComparisonState(modal);
        updatePlanComparisonState(modal, !!(request.context && request.context.comparePlans));
        if (request.context && request.context.showAgency) {
            updateAgencyComparisonState(modal, true);
        }

        modal.classList.remove('active');
        modal.classList.remove('is-visible');
        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
        modal.setAttribute('aria-hidden', 'false');
        setUpgradeModalScrollLock(true);

        if (!upgradeModalState.keyHandlerBound) {
            document.addEventListener('keydown', handleUpgradeModalKeydown, true);
            upgradeModalState.keyHandlerBound = true;
        }

        var content = modal.querySelector('.bbai-upgrade-modal__content');
        if (content) {
            content.removeAttribute('style');
        }

        window.requestAnimationFrame(function() {
            modal.classList.add('active');
            modal.classList.add('is-visible');
        });

        window.setTimeout(function() {
            focusUpgradeModalTarget(modal);
        }, 140);

        trackUpgradeEvent('upgrade_modal_viewed', {
            source: request.context && request.context.source ? request.context.source : 'upgrade-modal',
            trigger: triggerKey,
            reason: request.reason || 'default'
        });
        dispatchUpgradeEvent('bbai:upgrade-modal-opened', { request: request });

        return true;
    };

    window.bbaiOpenLockedUpgradeModal = function(reason, context) {
        return window.bbaiOpenUpgradeModal(reason || 'upgrade_required', context || {});
    };

    window.bbaiResetUpgradeModalContext = function() {
        var modal = resolveUpgradeModalElement();
        if (!modal) {
            return;
        }

        resetUpgradeModalContext(modal);
        resetAgencyComparisonState(modal);
        updatePlanComparisonState(modal, false);
    };

    /**
     * Close the upgrade modal
     */
    window.bbaiCloseUpgradeModal = function() {
        var triggerToRestore = upgradeModalState.lastTrigger;
        var modal = resolveUpgradeModalElement();
        var content = modal ? modal.querySelector('.bbai-upgrade-modal__content') : null;
        var closedRequest = upgradeModalState.activeRequest;

        if (modal) {
            resetUpgradeModalContext(modal);
            resetAgencyComparisonState(modal);
            updatePlanComparisonState(modal, false);
            modal.classList.remove('active');
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            setUpgradeModalScrollLock(false);
            modal.style.display = 'none';
            modal.removeAttribute('style');
            if (content) {
                content.removeAttribute('style');
            }
        }

        if (upgradeModalState.keyHandlerBound) {
            document.removeEventListener('keydown', handleUpgradeModalKeydown, true);
            upgradeModalState.keyHandlerBound = false;
        }

        if (triggerToRestore && typeof triggerToRestore.focus === 'function') {
            window.setTimeout(function() {
                triggerToRestore.focus();
            }, 0);
        }
        upgradeModalState.lastTrigger = null;
        upgradeModalState.activeRequest = null;

        dispatchUpgradeEvent('bbai:upgrade-modal-closed', { request: closedRequest });
    };

    /**
     * Initialize event listeners on DOM ready
     */
    function initUpgradeModalEvents() {
        document.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action="show-upgrade-modal"]');
            if (target) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (isGenerationActionControl(target) && isLockedActionControl(target)) {
                    window.bbaiOpenUpgradeModal('upgrade_required', { source: 'upgrade-modal', trigger: target });
                } else {
                    window.bbaiOpenUpgradeModal('default', { source: 'upgrade-modal', trigger: target });
                }
            }
        }, true);

        document.addEventListener('click', function(e) {
            var checkoutTrigger = e.target && e.target.closest('[data-action="checkout-plan"]');
            if (checkoutTrigger) {
                var activeRequest = upgradeModalState.activeRequest || { reason: 'default', context: {} };
                var storage = getSessionStorageSafe();
                var checkoutPayload = {
                    trigger: bbaiString(activeRequest.context && activeRequest.context.triggerKey) || 'manual',
                    reason: activeRequest.reason || 'default',
                    plan: checkoutTrigger.getAttribute('data-plan') || 'unknown'
                };

                trackUpgradeEvent('upgrade_cta_clicked', {
                    source: 'checkout',
                    trigger: checkoutPayload.trigger,
                    reason: checkoutPayload.reason,
                    plan: checkoutPayload.plan
                });

                if (storage) {
                    storage.setItem(CHECKOUT_PENDING_KEY, JSON.stringify(checkoutPayload));
                }
            }

            if (e.target && e.target.id === 'bbai-upgrade-modal') {
                window.bbaiCloseUpgradeModal();
                return;
            }

            var closeTrigger = e.target && e.target.closest('[data-bbai-upgrade-close="1"]');
            if (closeTrigger) {
                e.preventDefault();
                window.bbaiCloseUpgradeModal();
                return;
            }

            var compareTrigger = e.target && e.target.closest('[data-bbai-upgrade-toggle-plans="1"]');
            if (compareTrigger) {
                e.preventDefault();
                var modal = resolveUpgradeModalElement();
                updatePlanComparisonState(modal, true);
                window.setTimeout(function() {
                    focusUpgradeModalTarget(modal);
                }, 0);
                return;
            }

            var backTrigger = e.target && e.target.closest('[data-bbai-upgrade-back="1"]');
            if (backTrigger) {
                e.preventDefault();
                var modalFromBack = resolveUpgradeModalElement();
                updatePlanComparisonState(modalFromBack, false);
                window.setTimeout(function() {
                    focusUpgradeModalTarget(modalFromBack);
                }, 0);
                return;
            }

            var agencyTrigger = e.target && e.target.closest('[data-bbai-upgrade-toggle-agency="1"]');
            if (agencyTrigger) {
                e.preventDefault();
                var modalFromAgency = resolveUpgradeModalElement();
                var shouldExpandAgency = agencyTrigger.getAttribute('aria-expanded') !== 'true';
                updateAgencyComparisonState(modalFromAgency, shouldExpandAgency);
                if (shouldExpandAgency) {
                    window.setTimeout(function() {
                        var agencyCta = modalFromAgency && modalFromAgency.querySelector('[data-bbai-upgrade-agency-panel] .bbai-pricing-card__btn');
                        if (agencyCta && typeof agencyCta.focus === 'function') {
                            agencyCta.focus();
                        }
                    }, 0);
                }
            }
        });

        initUpgradeUsageCalculator();
        initializeObservedScanState();
        consumePendingUploadTrigger();
        maybeTriggerUsagePrompt(getUsageSnapshot());
        bindWordPressUploadTrigger();
        maybeTrackCompletedUpgrade();

        window.addEventListener('bbai-stats-update', function(event) {
            handleStatsUpdate(event && event.detail ? event.detail : {});
        });

        document.addEventListener('bbai:stats-updated', function(event) {
            handleStatsUpdate(event && event.detail ? event.detail : {});
        });

        document.addEventListener('bbai:upgrade-modal-closed', function(event) {
            var request = event && event.detail ? event.detail.request : null;
            var triggerKey = request && request.context ? request.context.triggerKey : '';

            if (request && request.context && request.context.autoTrigger && triggerKey) {
                setSessionDismissal(triggerKey);
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUpgradeModalEvents);
    } else {
        initUpgradeModalEvents();
    }
})();
