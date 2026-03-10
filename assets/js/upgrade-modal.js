/**
 * AI Alt Text Upgrade Modal JavaScript
 * Handles upgrade modal open/close and event listeners
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function() {
    'use strict';

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
            return modalById;
        }

        var modalByData = document.querySelector('[data-bbai-upgrade-modal="1"]');
        if (modalByData && modalByData.querySelector('.bbai-upgrade-modal__content')) {
            if (modalByData.id !== 'bbai-upgrade-modal') {
                modalByData.id = 'bbai-upgrade-modal';
            }
            return modalByData;
        }

        return null;
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
    window.bbaiOpenUpgradeModal = function() {
        var modal = resolveUpgradeModalElement();
        if (!modal) {
            return false;
        }

        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important;';
        modal.classList.add('active');
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (document.documentElement) {
            document.documentElement.style.overflow = 'hidden';
        }

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
        var modal = resolveUpgradeModalElement();
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            modal.classList.remove('is-visible');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (document.documentElement) {
                document.documentElement.style.overflow = '';
            }
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
                if (isGenerationActionControl(target) && isLockedActionControl(target)) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof e.stopImmediatePropagation === 'function') {
                        e.stopImmediatePropagation();
                    }
                    return;
                }
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

        initUpgradeUsageCalculator();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUpgradeModalEvents);
    } else {
        initUpgradeModalEvents();
    }
})();
