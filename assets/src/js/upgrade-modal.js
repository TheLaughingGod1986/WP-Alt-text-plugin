/**
 * AI Alt Text Upgrade Modal JavaScript
 * Handles upgrade modal open/close and event listeners
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function() {
    'use strict';

    /**
     * Open the upgrade modal
     * @returns {boolean} True if modal was opened successfully
     */
    window.bbaiOpenUpgradeModal = function() {
        var modal = document.getElementById('bbai-upgrade-modal');
        if (!modal) {
            return false;
        }

        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var content = modal.querySelector('.bbai-upgrade-modal__content');
        if (content) {
            content.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
        }

        return true;
    };

    /**
     * Close the upgrade modal
     */
    window.bbaiCloseUpgradeModal = function() {
        var modal = document.getElementById('bbai-upgrade-modal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    };

    /**
     * Initialize event listeners on DOM ready
     */
    function initUpgradeModalEvents() {
        // Event delegation for upgrade buttons
        document.addEventListener('click', function(e) {
            var target = e.target.closest('[data-action="show-upgrade-modal"]');
            if (target) {
                e.preventDefault();
                e.stopPropagation();
                window.bbaiOpenUpgradeModal();
            }
        }, true);

        // Close on backdrop click
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'bbai-upgrade-modal') {
                window.bbaiCloseUpgradeModal();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.bbaiCloseUpgradeModal();
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

