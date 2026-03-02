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
        window.BBAI_LOG && window.BBAI_LOG.warn('[AI Alt Text] REST configuration missing. Bulk operations disabled, but single regenerate will still work.');
    }

    /**
     * Check if current user can manage account
     */
    window.bbaiCanManageAccount = function() {
        return !!(window.BBAI && window.BBAI.canManage);
    };

    /**
     * Handle trial exhausted - show auth modal (register tab)
     */
    window.bbaiHandleTrialExhausted = function(errorData) {
        var message = (errorData && errorData.message) || 'You\'ve used your free trial generations. Create a free account to continue.';

        // Try to show auth modal with register tab
        if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
            window.authModal.show();
            if (typeof window.authModal.showRegisterForm === 'function') {
                window.authModal.showRegisterForm();
            }
        } else {
            // Fallback: click any auth trigger with register tab
            var authBtn = document.querySelector('[data-action="show-auth-modal"][data-auth-tab="register"]') ||
                          document.querySelector('[data-action="show-auth-modal"]');
            if (authBtn) {
                authBtn.click();
            } else {
                // Final fallback: dispatch event
                if (typeof CustomEvent === 'function') {
                    document.dispatchEvent(new CustomEvent('bbai:show-auth', {
                        detail: { mode: 'register' },
                        bubbles: true
                    }));
                }
            }
        }

        if (typeof showNotification === 'function') {
            showNotification(message, 'warning');
        }
    };

    /**
     * Check if an error is a trial exhausted error
     */
    window.bbaiIsTrialExhausted = function(errorData) {
        if (!errorData) return false;
        if (errorData.code === 'bbai_trial_exhausted') return true;
        var msg = (errorData.message || '').toLowerCase();
        return msg.indexOf('free trial') !== -1 && msg.indexOf('generation') !== -1;
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
     * Update trial status client-side after a successful generation.
     * Called after each generation to keep the UI in sync without page reload.
     */
    window.bbaiUpdateTrialUsage = function() {
        var trial = window.BBAI_DASH && window.BBAI_DASH.trial;
        if (!trial || !trial.is_trial) return;
        trial.used = Math.min(trial.limit, (trial.used || 0) + 1);
        trial.remaining = Math.max(0, trial.limit - trial.used);
        trial.exhausted = trial.remaining <= 0;

        // Update any visible trial banners
        var bannerText = document.querySelector('.bbai-trial-banner__text');
        if (bannerText && trial.remaining > 0) {
            bannerText.textContent = trial.remaining + ' of ' + trial.limit + ' free trial generations remaining';
        } else if (bannerText && trial.remaining <= 0) {
            var banner = document.querySelector('.bbai-trial-banner');
            if (banner) {
                banner.classList.add('bbai-trial-banner--exhausted');
                bannerText.textContent = 'Free trial exhausted — create a free account to continue';
            }
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
