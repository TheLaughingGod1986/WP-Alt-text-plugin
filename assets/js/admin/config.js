/**
 * Admin Configuration
 * Configuration and utility functions for admin operations
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function(text) { return text; };
    var _n = i18n && typeof i18n._n === 'function' ? i18n._n : function(single, plural, number) {
        return number === 1 ? single : plural;
    };
    var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function(format) { return format; };

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
     * Open upgrade modal with graceful fallbacks.
     *
     * @param {Object|null} usage Optional usage payload.
     * @returns {boolean} Whether a modal open path was triggered.
     */
    window.bbaiOpenUpgradeModal = function(usage) {
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

        var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
        if (upgradeBtn) {
            upgradeBtn.click();
            return true;
        }

        $(document).trigger('alttextai:show-upgrade-modal', [usage]);
        return false;
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
     * Get the current authenticated usage snapshot from plugin globals.
     * Runtime quota should come from the usage API payload mirrored here.
     *
     * @param {Object|null} errorUsage Usage from error payload.
     * @returns {Object|null}
     */
    window.bbaiGetUsageSnapshot = function(errorUsage) {
        var usage = errorUsage ||
            (window.BBAI_DASH && window.BBAI_DASH.initialUsage) ||
            (window.BBAI_DASH && window.BBAI_DASH.usage) ||
            (window.BBAI && window.BBAI.usage) ||
            null;

        if (typeof window.bbaiNormalizeAuthenticatedUsage === 'function') {
            return window.bbaiNormalizeAuthenticatedUsage(usage);
        }

        return usage;
    };

    /**
     * Derive reset metadata from usage payload.
     *
     * @param {Object|null} usage Usage payload.
     * @returns {{daysUntilReset:number|null, formattedResetDate:string}}
     */
    window.bbaiGetQuotaResetMeta = function(usage) {
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
    };

    /**
     * Handle limit reached - show upgrade modal
     */
    window.bbaiHandleLimitReached = function(errorData) {
        var usage = window.bbaiGetUsageSnapshot(errorData && errorData.usage ? errorData.usage : null);
        var baseMessage = (errorData && errorData.message) || __('You have used all available free credits for this month.', 'beepbeep-ai-alt-text-generator');
        var resetMeta = window.bbaiGetQuotaResetMeta(usage);
        var canManage = window.bbaiCanManageAccount();
        var resetMessage = __('Your next 50 free credits will be available at the next monthly reset.', 'beepbeep-ai-alt-text-generator');

        if (resetMeta.daysUntilReset !== null) {
            if (resetMeta.daysUntilReset <= 0) {
                resetMessage = __('Your next 50 free credits should be available today.', 'beepbeep-ai-alt-text-generator');
            } else {
                resetMessage = sprintf(
                    _n(
                        'Your next 50 free credits will be available in %d day.',
                        'Your next 50 free credits will be available in %d days.',
                        resetMeta.daysUntilReset,
                        'beepbeep-ai-alt-text-generator'
                    ),
                    resetMeta.daysUntilReset
                );
            }
        }

        if (resetMeta.formattedResetDate) {
            resetMessage += ' ' + sprintf(
                __('Reset date: %s.', 'beepbeep-ai-alt-text-generator'),
                resetMeta.formattedResetDate
            );
        }

        var fullMessage = baseMessage + '\n\n' + resetMessage;

        if (window.bbaiModal && typeof window.bbaiModal.show === 'function') {
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
                                var opened = window.bbaiOpenUpgradeModal(usage);
                                if (!opened && typeof showNotification === 'function') {
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
            return;
        }

        if (!canManage) {
            if (typeof showNotification === 'function') {
                showNotification(fullMessage + ' ' + __('Please contact the account owner to upgrade.', 'beepbeep-ai-alt-text-generator'), 'warning');
            }
            return;
        }

        var modalOpened = typeof window.bbaiTriggerUpgradePrompt === 'function'
            ? window.bbaiTriggerUpgradePrompt('limit_reached', {
                usage: usage,
                source: 'limit-reached',
                force: true,
                limitMessage: baseMessage,
                resetMessage: resetMessage
            })
            : window.bbaiOpenUpgradeModal('limit_reached', {
                usage: usage,
                source: 'limit-reached',
                force: true,
                limitMessage: baseMessage,
                resetMessage: resetMessage
            });
        if (typeof showNotification === 'function') {
            var fallbackMessage = fullMessage;
            if (!modalOpened) {
                fallbackMessage += ' ' + __('Unable to open the upgrade modal automatically.', 'beepbeep-ai-alt-text-generator');
            }
            showNotification(fallbackMessage, 'warning');
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
        var bannerText = document.getElementById('bbai-trial-banner-text') || document.querySelector('[data-bbai-trial-banner-text="1"]');
        if (bannerText && trial.remaining > 0) {
            bannerText.textContent = trial.remaining + ' of ' + trial.limit + ' free trial generations remaining';
        } else if (bannerText && trial.remaining <= 0) {
            var banner = document.querySelector('.bbai-trial-banner');
            if (banner) {
                banner.classList.add('bbai-trial-banner--exhausted', 'bbai-banner--warning');
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
