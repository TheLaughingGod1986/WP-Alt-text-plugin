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
            
            // Try WordPress REST API endpoint first
            const wpApiUrl = window.bbai_ajax?.ajaxurl || '/wp-json/bbai/v1/user/plan';
            const apiUrl = window.bbai_ajax?.api_url || '/api/user/plan';
            
            // Try backend API first
            let response;
            try {
                const token = window.bbai_ajax?.jwt_token || localStorage.getItem('bbai_jwt_token');
                response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(token && { 'Authorization': `Bearer ${token}` }),
                    },
                    credentials: 'include',
                });
            } catch (e) {
                // Fallback to WordPress REST API
                const nonce = window.bbai_ajax?.nonce || '';
                response = await fetch(wpApiUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    credentials: 'include',
                });
            }

            if (response && response.ok) {
                const data = await response.json();
                // Handle different response formats
                pricingModalState.currentPlan = data.plan || data.data?.plan || data.data?.data?.plan || 'free';
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
    function openPricingModal(variant = 'enterprise') {
        pricingModalState.isOpen = true;
        
        // Fetch user plan when opening
        fetchUserPlan();
        
        // Check if React component is available
        if (typeof window.initPricingModal === 'function' || 
            (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined')) {
            // Use React component if available
            if (!document.getElementById('bbai-pricing-modal-root')) {
                // Load React component dynamically if needed
                if (typeof window.initPricingModal === 'function') {
                    window.initPricingModal('bbai-pricing-modal-root', handlePlanSelect);
                }
            }
            // Trigger React component to open
            if (typeof window.openPricingModal === 'function') {
                window.openPricingModal(variant);
                return;
            }
        }
        
        // Fallback: Show alert if React not available
        console.warn('[AltText AI] React not available. Please include React and ReactDOM for the pricing modal.');
        alert('Pricing modal requires React. Please include React and ReactDOM libraries.');
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
    window.openPricingModal = openPricingModal;
    window.closePricingModal = closePricingModal;
    window.setPricingModalCallback = setPlanSelectCallback;
    window.getCurrentPlan = getCurrentPlan;

    // Replace existing upgrade modal triggers
    if (typeof document !== 'undefined') {
        const ready = function() {
            // Replace all upgrade modal triggers
            const upgradeTriggers = document.querySelectorAll('[data-action="show-upgrade-modal"]');
            upgradeTriggers.forEach(function(trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openPricingModal('enterprise');
                    return false;
                });
            });

            // Also handle jQuery triggers if jQuery is available
            if (typeof jQuery !== 'undefined') {
                jQuery(document).off('click', '[data-action="show-upgrade-modal"]');
                jQuery(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openPricingModal('enterprise');
                    return false;
                });
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ready);
        } else {
            ready();
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

