/**
 * Pricing Modal Bridge
 * Provides vanilla JS bridge for WordPress integration
 * Works with or without React
 */

(function(window) {
    'use strict';

    function bbaiString(value) {
        return value === undefined || value === null ? '' : String(value);
    }

    function isGenerationActionControl(element) {
        if (!element) {
            return false;
        }

        const action = bbaiString(element.getAttribute && element.getAttribute('data-action')).toLowerCase();
        const bbaiAction = bbaiString(element.getAttribute && element.getAttribute('data-bbai-action')).toLowerCase();
        const className = bbaiString(element.className).toLowerCase();
        const label = (bbaiString(element.getAttribute && element.getAttribute('aria-label')) + ' ' + bbaiString(element.textContent)).toLowerCase();

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

        const className = bbaiString(element.className).toLowerCase();
        const hint = (bbaiString(element.getAttribute && element.getAttribute('title')) + ' ' + bbaiString(element.getAttribute && element.getAttribute('data-bbai-tooltip'))).toLowerCase();

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

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;

    // Global pricing modal state
    const pricingModalState = {
        isOpen: false,
        currentPlan: null,
        isLoading: false,
        onPlanSelect: null,
        lastFocusedElement: null
    };

    /**
     * Fetch user plan from API
     */
    async function fetchUserPlan() {
        try {
            pricingModalState.isLoading = true;
            
            const restUsageUrl = window.BBAI_DASH?.restUsage || window.BBAI?.restUsage || '';
            const restNonce = window.BBAI_DASH?.nonce || window.BBAI?.nonce || '';
            const initialUsage = window.BBAI_DASH?.initialUsage || window.BBAI?.initialUsage || null;

            const extractPlan = function(payload) {
                if (!payload) {
                    return '';
                }
                return payload.plan || payload.plan_type || payload.data?.plan || payload.data?.plan_type || '';
            };

            const cachedPlan = extractPlan(initialUsage);
            if (cachedPlan) {
                pricingModalState.currentPlan = cachedPlan;
                return;
            }

            if (!restUsageUrl) {
                pricingModalState.currentPlan = 'free';
                return;
            }

            const response = await fetch(restUsageUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce,
                },
                credentials: 'same-origin',
            });

            if (response && response.ok) {
                const data = await response.json();
                pricingModalState.currentPlan = extractPlan(data) || 'free';
            } else {
                pricingModalState.currentPlan = 'free';
            }
        } catch (error) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Could not fetch user plan:', error);
            pricingModalState.currentPlan = 'free';
        } finally {
            pricingModalState.isLoading = false;
        }
    }

    /**
     * Handle plan selection
     */
    function handlePlanSelect(planId) {
        if (pricingModalState.onPlanSelect && typeof pricingModalState.onPlanSelect === 'function') {
            pricingModalState.onPlanSelect(planId);
        } else {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Plan selected:', planId);
            // Default behavior: Stripe checkout integration via callback system
        }
        closePricingModal();
    }

    /**
     * Open pricing modal
     * @param {string} variant - Modal variant (currently only 'enterprise' supported)
     */
    const existingOpenPricingModal = typeof window.openPricingModal === 'function' ? window.openPricingModal : null;
    const existingClosePricingModal = typeof window.closePricingModal === 'function' ? window.closePricingModal : null;

    function isReactModalVisible() {
        const reactRoot = document.getElementById('bbai-pricing-modal-root');
        if (!reactRoot) {
            return false;
        }

        if (reactRoot.querySelector('[role="dialog"][aria-modal="true"]')) {
            return true;
        }

        return reactRoot.childElementCount > 0;
    }

    function getUpgradeModalElement() {
        const modalById = document.getElementById('bbai-upgrade-modal');
        if (modalById && modalById.querySelector('.bbai-upgrade-modal__content')) {
            return modalById;
        }

        const modalByData = document.querySelector('[data-bbai-upgrade-modal="1"]');
        if (modalByData && modalByData.querySelector('.bbai-upgrade-modal__content')) {
            if (modalByData.id !== 'bbai-upgrade-modal') {
                modalByData.id = 'bbai-upgrade-modal';
            }
            return modalByData;
        }

        return null;
    }

    function isUpgradeModalVisible() {
        if (isReactModalVisible()) {
            return true;
        }

        const modal = getUpgradeModalElement();
        if (!modal) {
            return false;
        }

        if (modal.classList.contains('active') || modal.classList.contains('is-visible')) {
            return true;
        }

        const style = window.getComputedStyle(modal);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    }

    function getExternalOpenPricingModal() {
        const currentOpenPricingModal = window.openPricingModal;
        if (typeof currentOpenPricingModal === 'function' && currentOpenPricingModal !== openPricingModal) {
            return currentOpenPricingModal;
        }

        if (existingOpenPricingModal && existingOpenPricingModal !== openPricingModal) {
            return existingOpenPricingModal;
        }

        return null;
    }

    function getExternalClosePricingModal() {
        const currentClosePricingModal = window.closePricingModal;
        if (typeof currentClosePricingModal === 'function' && currentClosePricingModal !== closePricingModal) {
            return currentClosePricingModal;
        }

        if (existingClosePricingModal && existingClosePricingModal !== closePricingModal) {
            return existingClosePricingModal;
        }

        return null;
    }

    function rememberFocusedElement() {
        if (document.activeElement && typeof document.activeElement.focus === 'function') {
            pricingModalState.lastFocusedElement = document.activeElement;
        }
    }

    function restoreFocus() {
        const focusTarget = pricingModalState.lastFocusedElement;
        pricingModalState.lastFocusedElement = null;

        if (focusTarget && typeof focusTarget.focus === 'function') {
            try {
                focusTarget.focus();
            } catch (error) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Unable to restore focus after closing pricing modal.', error);
            }
        }
    }

    function queueFocusRestore(delay) {
        window.setTimeout(function() {
            if (!isUpgradeModalVisible()) {
                restoreFocus();
            }
        }, delay || 0);
    }

    function getFocusableElements(modal) {
        if (!modal) {
            return [];
        }

        return Array.prototype.slice.call(
            modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
        ).filter(function(element) {
            const style = window.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });
    }

    function trapFocusInModal(event) {
        if (event.key !== 'Tab') {
            return;
        }

        const modal = getUpgradeModalElement();
        if (!modal || !isUpgradeModalVisible()) {
            return;
        }

        const focusableElements = getFocusableElements(modal);
        if (!focusableElements.length) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (!modal.contains(document.activeElement)) {
            event.preventDefault();
            firstElement.focus();
            return;
        }

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    }

    function closeFallbackModal() {
        const phpModal = getUpgradeModalElement();
        if (!phpModal) {
            queueFocusRestore(0);
            return false;
        }

        if (typeof window.bbaiCloseUpgradeModal === 'function') {
            window.bbaiCloseUpgradeModal();
        } else if (typeof window.alttextaiCloseModal === 'function') {
            window.alttextaiCloseModal();
        } else if (typeof alttextaiCloseModal === 'function') {
            alttextaiCloseModal();
        } else {
            phpModal.style.display = 'none';
            phpModal.style.visibility = 'hidden';
            phpModal.style.opacity = '0';
            phpModal.classList.remove('active');
            phpModal.classList.remove('is-visible');
            phpModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (document.documentElement) {
                document.documentElement.style.overflow = '';
            }
        }

        queueFocusRestore(320);
        return true;
    }

    function openFallbackModal() {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Trying fallback modal systems...');

        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using window.bbaiOpenUpgradeModal');
            window.bbaiOpenUpgradeModal('default', {
                source: 'pricing-modal-bridge',
                trigger: pricingModalState.lastFocusedElement || document.activeElement
            });
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof window.showUpgradeModal === 'function') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using window.showUpgradeModal');
            window.showUpgradeModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof window.alttextaiShowModal === 'function') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using window.alttextaiShowModal');
            window.alttextaiShowModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof alttextaiShowModal === 'function') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using alttextaiShowModal');
            alttextaiShowModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof bbaiApp !== 'undefined' && typeof bbaiApp.showModal === 'function') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using bbaiApp.showModal');
            bbaiApp.showModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Trying direct PHP modal manipulation...');
        let phpModal = getUpgradeModalElement();
        if (phpModal) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Found PHP modal, showing it');
            phpModal.style.display = 'flex';
            phpModal.style.visibility = 'visible';
            phpModal.style.opacity = '1';
            phpModal.style.zIndex = '999999';
            phpModal.style.position = 'fixed';
            phpModal.style.inset = '0';
            phpModal.style.backgroundColor = 'rgba(0,0,0,0.6)';
            phpModal.style.alignItems = 'center';
            phpModal.style.justifyContent = 'center';
            phpModal.classList.add('active');
            phpModal.classList.add('is-visible');
            phpModal.setAttribute('aria-hidden', 'false');
            phpModal.setAttribute('aria-modal', 'true');
            document.body.style.overflow = 'hidden';
            if (document.documentElement) {
                document.documentElement.style.overflow = 'hidden';
            }

            setTimeout(function() {
                const closeBtn = phpModal.querySelector('.bbai-upgrade-modal__close, [aria-label*="Close"]');
                if (closeBtn && typeof closeBtn.focus === 'function') {
                    closeBtn.focus();
                }
            }, 100);
            return true;
        }

        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No pricing modal system available. Modal element not found in DOM.');
        if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error(__('Unable to open upgrade modal. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
        } else {
            alert(__('Unable to open upgrade modal. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator'));
        }
        return false;
    }

    function ensureFallbackAfterDelay() {
        setTimeout(function() {
            if (!isUpgradeModalVisible()) {
                openFallbackModal();
            }
        }, 80);
    }

    function openPricingModal(variant = 'enterprise') {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] openPricingModal called with variant:', variant);
        pricingModalState.isOpen = true;
        rememberFocusedElement();
        
        // Fetch user plan when opening (don't wait for it)
        fetchUserPlan();
        
        // Prefer any existing React-driven modal opener if present
        const externalOpenPricingModal = getExternalOpenPricingModal();
        if (externalOpenPricingModal) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using existing React modal opener');
            externalOpenPricingModal(variant);
            ensureFallbackAfterDelay();
            return;
        }

        // Check if React component is available
        if (typeof window.initPricingModal === 'function' || 
            (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined')) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] React available, trying React modal');
            // Use React component if available
            if (!document.getElementById('bbai-pricing-modal-root')) {
                // Load React component dynamically if needed
                if (typeof window.initPricingModal === 'function') {
                    window.initPricingModal('bbai-pricing-modal-root', handlePlanSelect);
                }
            }
            // Trigger React component to open if it registered its own handler
            const externalAfterInit = getExternalOpenPricingModal();
            if (externalAfterInit) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using React component handler');
                externalAfterInit(variant);
                ensureFallbackAfterDelay();
                return;
            }
        }

        if (openFallbackModal()) {
            return;
        }
    }

    /**
     * Close pricing modal
     */
    function closePricingModal() {
        pricingModalState.isOpen = false;
        const externalClosePricingModal = getExternalClosePricingModal();

        if (externalClosePricingModal) {
            externalClosePricingModal();
            queueFocusRestore(320);
            return;
        }

        closeFallbackModal();
    }

    /**
     * Set plan select callback
     */
    function setPlanSelectCallback(callback) {
        pricingModalState.onPlanSelect = callback;
    }

    /**
     * Get current user plan
     */
    function getCurrentPlan() {
        return pricingModalState.currentPlan;
    }

    // Expose global functions
    // Only register bridge if another implementation is not already present
    if (typeof window.openPricingModal !== 'function') {
        window.openPricingModal = openPricingModal;
    }
    window.closePricingModal = closePricingModal;
    window.setPricingModalCallback = setPlanSelectCallback;
    window.getCurrentPlan = getCurrentPlan;

    // Replace existing upgrade modal triggers
    if (typeof document !== 'undefined') {
        const ready = function() {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Pricing modal bridge ready, attaching event handlers...');
            if (document.__bbaiPricingModalBound) {
                return;
            }
            document.__bbaiPricingModalBound = true;

            document.addEventListener('click', function(e) {
                const trigger = e.target.closest('[data-action="show-upgrade-modal"]');
                if (!trigger) {
                    return;
                }

                if (isGenerationActionControl(trigger) && isLockedActionControl(trigger)) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    return;
                }

                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Upgrade button clicked (capture handler)');
                e.preventDefault();
                e.stopPropagation();
                var variantAttr = trigger.getAttribute('data-bbai-pricing-variant');
                var variant = variantAttr && String(variantAttr).trim()
                    ? String(variantAttr).trim().toLowerCase()
                    : (window.BBAI_DASH && window.BBAI_DASH.upgradePath && window.BBAI_DASH.upgradePath.pricing_variant
                        ? String(window.BBAI_DASH.upgradePath.pricing_variant).toLowerCase()
                        : 'growth');
                openPricingModal(variant);
            }, true);

            document.addEventListener('click', function(e) {
                const modal = getUpgradeModalElement();
                if (!modal || !isUpgradeModalVisible()) {
                    return;
                }

                const closeTrigger = e.target.closest('.bbai-upgrade-modal__close, [data-bbai-upgrade-close="1"], [onclick*="alttextaiCloseModal"], [data-action="close-modal"]');
                if (closeTrigger || e.target === modal) {
                    queueFocusRestore(320);
                }
            }, true);

            document.addEventListener('keydown', function(e) {
                if (!isUpgradeModalVisible()) {
                    return;
                }

                if (e.key === 'Tab') {
                    trapFocusInModal(e);
                    return;
                }

                if (e.key === 'Escape' || e.keyCode === 27) {
                    queueFocusRestore(320);
                }
            }, true);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ready);
        } else {
            // If already loaded, run immediately
            setTimeout(ready, 0);
        }
    }

    // Export for module systems
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            openPricingModal,
            closePricingModal,
            setPlanSelectCallback,
            getCurrentPlan
        };
    }

})(window);
