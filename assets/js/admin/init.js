/**
 * Admin Initialization
 * Main entry point for admin functionality
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

(function($) {
    'use strict';

    function getUsage() {
        var usage = (window.BBAI_DASH && (window.BBAI_DASH.initialUsage || window.BBAI_DASH.usage)) ||
            (window.BBAI && window.BBAI.usage) ||
            (window.BBAI_UPGRADE && window.BBAI_UPGRADE.usage) ||
            null;

        if (typeof window.bbaiNormalizeAuthenticatedUsage === 'function') {
            return window.bbaiNormalizeAuthenticatedUsage(usage);
        }

        return usage;
    }

    function isOutOfCredits() {
        var usage = getUsage();
        if (usage && typeof usage === 'object') {
            var remaining = NaN;
            if (usage.remaining !== undefined && usage.remaining !== null) {
                remaining = parseInt(usage.remaining, 10);
            } else if (usage.limit !== undefined && usage.used !== undefined) {
                remaining = parseInt(usage.limit, 10) - parseInt(usage.used, 10);
            }

            if (!isNaN(remaining) && remaining <= 0) {
                return true;
            }

            var used = parseInt(usage.used, 10);
            var limit = parseInt(usage.limit, 10);
            if (!isNaN(used) && !isNaN(limit) && limit > 0 && used >= limit) {
                return true;
            }
        }

        var usageSelectors = ['.bbai-usage-count', '.bbai-usage-count-text', '.bbai-card__subtitle'];
        for (var i = 0; i < usageSelectors.length; i++) {
            var node = document.querySelector(usageSelectors[i]);
            if (!node || !node.textContent) {
                continue;
            }

            var match = node.textContent.match(/([0-9][0-9,]*)\s*of\s*([0-9][0-9,]*)/i);
            if (!match) {
                continue;
            }

            var domUsed = parseInt(String(match[1]).replace(/,/g, ''), 10);
            var domLimit = parseInt(String(match[2]).replace(/,/g, ''), 10);
            if (!isNaN(domUsed) && !isNaN(domLimit) && domLimit > 0 && domUsed >= domLimit) {
                return true;
            }
        }

        return false;
    }

    function lockControl(el) {
        if (!el) {
            return;
        }
        if (el.tagName === 'A') {
            el.removeAttribute('href');
            el.setAttribute('role', 'button');
            el.setAttribute('tabindex', '-1');
            el.setAttribute('aria-disabled', 'true');
        }
        if (typeof el.disabled !== 'undefined') {
            el.disabled = true;
        }
        el.classList.add('disabled');
        el.style.pointerEvents = 'none';
        el.style.cursor = 'not-allowed';
        el.setAttribute('title', 'You are out of credits for this month. Upgrade to continue now, or wait until your monthly reset date.');
    }

    function blockIfOutOfCredits(e, el) {
        if (!isOutOfCredits()) {
            return false;
        }

        if (e && typeof e.preventDefault === 'function') {
            e.preventDefault();
        }
        if (e && typeof e.stopPropagation === 'function') {
            e.stopPropagation();
        }
        if (e && typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        lockControl(el);
        return true;
    }

    // Initialize on document ready
    $(document).ready(function() {
        if (window.BBAI_DEBUG) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Admin JavaScript loaded');
            window.BBAI_LOG && window.BBAI_LOG.log('[AI Alt Text] Config:', window.bbaiAdminConfig);
        }

        // Handle generate missing button
        $(document).on('click', '[data-action="generate-missing"]', function(e) {
            if (blockIfOutOfCredits(e, this)) {
                return false;
            }
            if (typeof window.handleGenerateMissing === 'function') {
                window.handleGenerateMissing.call(this, e);
            } else if (typeof handleGenerateMissing === 'function') {
                handleGenerateMissing.call(this, e);
            } else {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] handleGenerateMissing function not found');
            }
        });

        // Handle regenerate all button (skip when target is upgrade link - let browser navigate)
        $(document).on('click', '[data-action="regenerate-all"]', function(e) {
            if (blockIfOutOfCredits(e, this)) {
                return false;
            }
            var el = this;
            if (el && el.tagName === 'A') {
                var href = el.getAttribute && el.getAttribute('href');
                if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                    return; // Allow default navigation
                }
            }
            if (typeof window.handleRegenerateAll === 'function') {
                window.handleRegenerateAll.call(this, e);
            } else if (typeof handleRegenerateAll === 'function') {
                handleRegenerateAll.call(this, e);
            } else {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] handleRegenerateAll function not found');
            }
        });

        // Handle individual regenerate buttons
        $(document).on('click', '[data-action="regenerate-single"]', function(e) {
            if (blockIfOutOfCredits(e, this)) {
                return false;
            }
            if (typeof window.handleRegenerateSingle === 'function') {
                window.handleRegenerateSingle.call(this, e);
            } else if (typeof handleRegenerateSingle === 'function') {
                handleRegenerateSingle.call(this, e);
            } else {
                window.BBAI_LOG && window.BBAI_LOG.error('[AI Alt Text] handleRegenerateSingle function not found');
            }
        });

        $(document).on('click', '[data-action="phase17-improve-alt"]', function(e) {
            if (blockIfOutOfCredits(e, this)) {
                return false;
            }
            if (typeof window.bbaiHandlePhase17ImproveAlt === 'function') {
                return window.bbaiHandlePhase17ImproveAlt.call(this, e);
            }
            return false;
        });

        // License management handlers
        $('#license-activation-form').on('submit', handleLicenseActivation);
        $(document).on('click', '[data-action="deactivate-license"]', handleLicenseDeactivation);
    });

})(jQuery);
