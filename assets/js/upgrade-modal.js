/**
 * AI Alt Text Upgrade Modal JavaScript
 * Handles upgrade modal open/close and event listeners
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function() {
    'use strict';

    var upgradeModalState = {
        keyHandlerBound: false,
        lastTrigger: null
    };

    function bbaiString(value) {
        return value === undefined || value === null ? '' : String(value);
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

    function applyUpgradeModalContext(modal, reason, context) {
        var copy = getUpgradeModalCopy(modal);
        if (!copy) {
            return;
        }

        var isLockedContext = typeof reason === 'string' && reason !== '' && reason !== 'default';
        var variant = isLockedContext ? copy.locked : copy.defaults;
        var decisionDesc = variant.decisionDesc;

        modal.setAttribute('data-bbai-upgrade-context', isLockedContext ? 'locked' : 'default');
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
        if (!modal) {
            return false;
        }

        var request = normalizeUpgradeOpenRequest(reasonOrContext, maybeContext);
        upgradeModalState.lastTrigger = request.context && request.context.trigger ? request.context.trigger : document.activeElement;
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
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUpgradeModalEvents);
    } else {
        initUpgradeModalEvents();
    }
})();
