/**
 * Pricing Modal Bridge
 * Provides vanilla JS bridge for WordPress integration
 * Works with or without React
 */

(function(window) {
    'use strict';

    // Global pricing modal state
    const pricingModalState = {
        isOpen: false,
        currentPlan: null,
        isLoading: false,
        onPlanSelect: null
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
            console.warn('[AltText AI] Could not fetch user plan:', error);
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
            console.log('[AltText AI] Plan selected:', planId);
            // Default behavior: Stripe checkout integration via callback system
        }
        closePricingModal();
    }

    /**
     * Open pricing modal
     * @param {string} variant - Modal variant (currently only 'enterprise' supported)
     */
    const existingOpenPricingModal = typeof window.openPricingModal === 'function' ? window.openPricingModal : null;

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

    function isUpgradeModalVisible() {
        if (isReactModalVisible()) {
            return true;
        }

        const modal = document.getElementById('bbai-upgrade-modal');
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

    function openFallbackModal() {
        console.log('[AltText AI] Trying fallback modal systems...');

        if (typeof window.showUpgradeModal === 'function') {
            console.log('[AltText AI] Using window.showUpgradeModal');
            window.showUpgradeModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof window.alttextaiShowModal === 'function') {
            console.log('[AltText AI] Using window.alttextaiShowModal');
            window.alttextaiShowModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof alttextaiShowModal === 'function') {
            console.log('[AltText AI] Using alttextaiShowModal');
            alttextaiShowModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        if (typeof bbaiApp !== 'undefined' && typeof bbaiApp.showModal === 'function') {
            console.log('[AltText AI] Using bbaiApp.showModal');
            bbaiApp.showModal();
            if (isUpgradeModalVisible()) {
                return true;
            }
        }

        console.log('[AltText AI] Trying direct PHP modal manipulation...');
        let phpModal = document.getElementById('bbai-upgrade-modal');
        if (!phpModal) {
            phpModal = document.querySelector('.bbai-modal-backdrop');
        }
        if (phpModal) {
            console.log('[AltText AI] Found PHP modal, showing it');
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
            phpModal.removeAttribute('aria-hidden');
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

        console.error('[AltText AI] No pricing modal system available. Modal element not found in DOM.');
        if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error('Unable to open upgrade modal. Please refresh the page and try again.');
        } else {
            alert('Unable to open upgrade modal. Please refresh the page and try again.');
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
        console.log('[AltText AI] openPricingModal called with variant:', variant);
        pricingModalState.isOpen = true;
        
        // Fetch user plan when opening (don't wait for it)
        fetchUserPlan();
        
        // Prefer any existing React-driven modal opener if present
        const externalOpenPricingModal = getExternalOpenPricingModal();
        if (externalOpenPricingModal) {
            console.log('[AltText AI] Using existing React modal opener');
            externalOpenPricingModal(variant);
            ensureFallbackAfterDelay();
            return;
        }

        // Check if React component is available
        if (typeof window.initPricingModal === 'function' || 
            (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined')) {
            console.log('[AltText AI] React available, trying React modal');
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
                console.log('[AltText AI] Using React component handler');
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
        if (typeof window.closePricingModal === 'function') {
            window.closePricingModal();
        }
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
            console.log('[AltText AI] Pricing modal bridge ready, attaching event handlers...');
            if (document.__bbaiPricingModalBound) {
                return;
            }
            document.__bbaiPricingModalBound = true;

            document.addEventListener('click', function(e) {
                const trigger = e.target.closest('[data-action="show-upgrade-modal"]');
                if (!trigger) {
                    return;
                }

                console.log('[AltText AI] Upgrade button clicked (capture handler)');
                e.preventDefault();
                e.stopPropagation();
                openPricingModal('enterprise');
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
