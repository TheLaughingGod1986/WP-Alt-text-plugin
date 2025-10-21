/**
 * Upgrade Modal helper - handles auto display on limit hit.
 */

(function($) {
    'use strict';

    const STORAGE_KEY = 'alttextaiUpgradeDismissed';

    const AltTextUpgrade = {
        modal: null,

        init() {
            this.modal = $('#alttextai-upgrade-modal');
            if (!this.modal.length) {
                return;
            }
            this.registerDismissTracking();
            this.autoShow();
        },

        autoShow() {
            if (this.isDismissed()) {
                return;
            }

            const usage = (window.AI_ALT_GPT_DASH && window.AI_ALT_GPT_DASH.initialUsage) || null;
            if (!usage) {
                return;
            }

            const remaining = parseInt(usage.remaining || 0, 10);
            if (remaining > 0) {
                return;
            }

            setTimeout(() => {
                if (typeof window.AiAltEnhancements?.showUpgradeModal === 'function') {
                    window.AiAltEnhancements.showUpgradeModal();
                } else {
                    this.modal.fadeIn(280);
                    $('body').css('overflow', 'hidden');
                }
            }, 900);
        },

        registerDismissTracking() {
            const markDismissed = () => this.markDismissed();

            $(document).on('click.alttextaiUpgrade', '[data-action="close-modal"]', markDismissed);
            $(document).on('click.alttextaiUpgrade', '.alttextai-modal-backdrop', function(event) {
                if (event.target === this) {
                    markDismissed();
                }
            });
            $(document).on('keydown.alttextaiUpgrade', (event) => {
                if (event.key === 'Escape' && $('#alttextai-upgrade-modal').is(':visible')) {
                    markDismissed();
                }
            });
        },

        markDismissed() {
            try {
                sessionStorage.setItem(STORAGE_KEY, '1');
            } catch (e) {
                // Ignore storage errors (private mode, etc.)
            }
        },

        isDismissed() {
            try {
                return sessionStorage.getItem(STORAGE_KEY) === '1';
            } catch (e) {
                return false;
            }
        }
    };

    $(document).ready(() => AltTextUpgrade.init());

})(jQuery);
