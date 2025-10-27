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
            const self = this;
            const markDismissed = () => {
                self.closeModal();
                self.markDismissed();
            };

            // Close button handler ONLY
            $(document).on('click.alttextaiUpgrade', '[data-action="close-modal"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                markDismissed();
            });

            // ESC key handler
            $(document).on('keydown.alttextaiUpgrade', (event) => {
                if (event.key === 'Escape' && $('#alttextai-upgrade-modal').is(':visible')) {
                    markDismissed();
                }
            });

            // DO NOT close on backdrop clicks - let buttons work!
            // DO NOT prevent propagation - let onclick handlers fire!
        },

        closeModal() {
            this.modal.fadeOut(280);
            $('body').css('overflow', '');
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
