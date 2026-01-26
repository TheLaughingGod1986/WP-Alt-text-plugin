/**
 * Admin Configuration
 * Configuration and utility functions for admin operations
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    // Ensure BBAI_DASH exists (from dashboard) or use BBAI
    window.bbaiAdminConfig = window.BBAI_DASH || window.BBAI || {};

    // Check if we have the necessary configuration for bulk operations
    window.bbaiHasBulkConfig = !!(window.bbaiAdminConfig.rest && window.bbaiAdminConfig.nonce);

    if (!window.bbaiHasBulkConfig) {
        console.warn('[AI Alt Text] REST configuration missing. Bulk operations disabled, but single regenerate will still work.');
    }

    /**
     * Check if current user can manage account
     */
    window.bbaiCanManageAccount = function() {
        return !!(window.BBAI && window.BBAI.canManage);
    };

    /**
     * Handle limit reached - show upgrade modal
     */
    window.bbaiHandleLimitReached = function(errorData) {
        var message = (errorData && errorData.message) || 'Monthly limit reached. Please contact a site administrator.';
        if (!window.bbaiCanManageAccount()) {
            if (typeof showNotification === 'function') {
                showNotification(message, 'warning');
            }
            return;
        }

        var usage = errorData && errorData.usage ? errorData.usage : null;

        // Try multiple methods to show the upgrade modal
        if (typeof alttextaiShowModal === 'function') {
            alttextaiShowModal();
        } else if (typeof window.alttextaiShowModal === 'function') {
            window.alttextaiShowModal();
        } else if (typeof showUpgradeModal === 'function') {
            showUpgradeModal(usage);
        } else if (typeof window.beepbeepai_show_upgrade_modal === 'function') {
            window.beepbeepai_show_upgrade_modal(usage);
        } else {
            // Fallback: trigger event or click upgrade button
            var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
            if (upgradeBtn) {
                upgradeBtn.click();
            } else {
                $(document).trigger('alttextai:show-upgrade-modal', [usage]);
            }
        }

        // Show notification
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        }
    };

    /**
     * Escape HTML to prevent XSS
     */
    window.bbaiEscapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Show notification message
     */
    window.bbaiShowNotification = function(message, type) {
        type = type || 'info';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap').first().prepend($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
    };

    // Alias for backward compatibility
    window.showNotification = window.bbaiShowNotification;

})(jQuery);
