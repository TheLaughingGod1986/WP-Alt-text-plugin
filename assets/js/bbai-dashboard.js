/**
 * AI Alt Text Dashboard JavaScript
 * Handles upgrade modal, auth buttons, and Stripe integration
 */

const bbaiRunWithJQuery = (function() {
    let warned = false;
    return function(callback) {
        const jq = window.jQuery || window.$;
        if (typeof jq !== 'function') {
            if (!warned) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] jQuery not found; dashboard scripts not run.');
                warned = true;
            }
            return;
        }
        callback(jq);
    };
})();

const BBAI_STATS_SYNC_KEY = 'bbai-dashboard-stats-sync';

function bbaiString(value) {
    return value === undefined || value === null ? '' : String(value);
}

function bbaiGetUsageObject() {
    var root = document.querySelector('[data-bbai-dashboard-root="1"]');
    var rootUsage = null;
    var globalUsage = (window.BBAI_DASH && (window.BBAI_DASH.initialUsage || window.BBAI_DASH.usage)) ||
        (window.BBAI_DASHBOARD && window.BBAI_DASHBOARD.usage) ||
        (window.BBAI && window.BBAI.usage) ||
        (window.BBAI_UPGRADE && window.BBAI_UPGRADE.usage) ||
        null;

    if (root) {
        var rootUsed = parseInt(root.getAttribute('data-bbai-credits-used') || '0', 10);
        var rootLimit = parseInt(root.getAttribute('data-bbai-credits-total') || '0', 10);
        var rootRemaining = parseInt(root.getAttribute('data-bbai-credits-remaining') || '0', 10);

        if (!isNaN(rootUsed) || !isNaN(rootLimit) || !isNaN(rootRemaining)) {
            rootUsage = {
                used: isNaN(rootUsed) ? 0 : rootUsed,
                limit: isNaN(rootLimit) || rootLimit <= 0 ? 50 : rootLimit,
                remaining: isNaN(rootRemaining) ? Math.max(0, (isNaN(rootLimit) || rootLimit <= 0 ? 50 : rootLimit) - (isNaN(rootUsed) ? 0 : rootUsed)) : rootRemaining,
                reset_line: root.getAttribute('data-bbai-credits-reset-line') || ''
            };
        }
    }

    if (rootUsage && globalUsage && typeof globalUsage === 'object') {
        var rootLimitValue = parseInt(rootUsage.limit, 10) || 0;
        var globalLimitValue = parseInt(globalUsage.limit, 10) || 0;

        if (rootLimitValue > 0 && globalLimitValue > 0 && rootLimitValue !== globalLimitValue) {
            return rootLimitValue > globalLimitValue ? rootUsage : globalUsage;
        }

        return Object.assign({}, rootUsage, globalUsage);
    }

    return rootUsage || globalUsage;
}

function bbaiGetUsageFromDom() {
    var selectors = [
        '.bbai-usage-count',
        '.bbai-usage-count-text',
        '.bbai-card__subtitle'
    ];

    for (var i = 0; i < selectors.length; i++) {
        var node = document.querySelector(selectors[i]);
        if (!node || !node.textContent) {
            continue;
        }

        var match = node.textContent.match(/([0-9][0-9,]*)\s*of\s*([0-9][0-9,]*)/i);
        if (!match) {
            continue;
        }

        var used = parseInt(String(match[1]).replace(/,/g, ''), 10);
        var limit = parseInt(String(match[2]).replace(/,/g, ''), 10);
        if (!isNaN(used) && !isNaN(limit) && limit > 0) {
            return { used: used, limit: limit };
        }
    }

    return null;
}

function bbaiIsUsageExhausted() {
    var usage = bbaiGetUsageObject();
    if (usage && typeof usage === 'object') {
        var remaining = NaN;
        if (usage.remaining !== undefined && usage.remaining !== null) {
            remaining = parseInt(usage.remaining, 10);
        } else if (usage.generations_remaining !== undefined && usage.generations_remaining !== null) {
            remaining = parseInt(usage.generations_remaining, 10);
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

    var domUsage = bbaiGetUsageFromDom();
    return !!(domUsage && domUsage.limit > 0 && domUsage.used >= domUsage.limit);
}

function bbaiHasQuotaLockHint(value) {
    var text = bbaiString(value).toLowerCase();
    if (!text) {
        return false;
    }

    return text.indexOf('out of credits') !== -1 ||
        text.indexOf('unlock more generations') !== -1 ||
        text.indexOf('monthly quota') !== -1 ||
        text.indexOf('monthly limit') !== -1 ||
        text.indexOf('quota reached') !== -1;
}

function bbaiIsGenerationActionControl(element) {
    if (!element) {
        return false;
    }

    var action = bbaiString(element.getAttribute && element.getAttribute('data-action')).toLowerCase();
    var bbaiAction = bbaiString(element.getAttribute && element.getAttribute('data-bbai-action')).toLowerCase();
    var id = bbaiString(element.id).toLowerCase();
    var className = bbaiString(element.className).toLowerCase();

    if (action === 'generate-missing' || action === 'regenerate-all' || action === 'regenerate-single') {
        return true;
    }

    if (bbaiAction === 'generate_missing' || bbaiAction === 'reoptimize_all') {
        return true;
    }

    if (id.indexOf('batch-regenerate') !== -1) {
        return true;
    }

    if (className.indexOf('bbai-optimization-cta') !== -1 ||
        className.indexOf('bbai-action-btn-primary') !== -1 ||
        className.indexOf('bbai-action-btn-secondary') !== -1 ||
        className.indexOf('bbai-dashboard-btn--primary') !== -1 ||
        className.indexOf('bbai-dashboard-btn--secondary') !== -1) {
        return true;
    }

    var labelText = (bbaiString(element.getAttribute && element.getAttribute('aria-label')) + ' ' + bbaiString(element.textContent)).toLowerCase();
    return labelText.indexOf('generate missing') !== -1 ||
        labelText.indexOf('regenerate') !== -1 ||
        labelText.indexOf('re-optim') !== -1 ||
        labelText.indexOf('reoptimiz') !== -1 ||
        labelText.indexOf('optimise all') !== -1 ||
        labelText.indexOf('optimize all') !== -1;
}

function bbaiIsLockedActionControl(element) {
    if (!element) {
        return false;
    }

    var target = element;
    if (target.closest) {
        var closestTarget = target.closest('[data-bbai-lock-control], [data-action], [data-bbai-action], .bbai-optimization-cta, .bbai-action-btn, .bbai-dashboard-btn, button, a');
        if (closestTarget) {
            target = closestTarget;
        }
    }

    var className = bbaiString(target.className).toLowerCase();

    if ((target.hasAttribute && (target.hasAttribute('disabled') || target.hasAttribute('data-bbai-lock-control'))) ||
        target.disabled ||
        (target.getAttribute && target.getAttribute('aria-disabled') === 'true')) {
        return true;
    }

    if (className.indexOf('bbai-optimization-cta--disabled') !== -1 ||
        className.indexOf('bbai-optimization-cta--locked') !== -1 ||
        className.indexOf('bbai-action-btn--disabled') !== -1 ||
        className.indexOf(' disabled') !== -1 ||
        className.indexOf('disabled ') === 0 ||
        className === 'disabled') {
        return true;
    }

    var hintText = bbaiString(target.getAttribute && target.getAttribute('title')) + ' ' + bbaiString(target.getAttribute && target.getAttribute('data-bbai-tooltip'));
    if (bbaiHasQuotaLockHint(hintText)) {
        return true;
    }

    if (target.querySelector) {
        var hintNode = target.querySelector('[title], [data-bbai-tooltip], [data-bbai-lock-control]');
        if (hintNode) {
            if ((hintNode.hasAttribute && hintNode.hasAttribute('data-bbai-lock-control')) ||
                bbaiHasQuotaLockHint(bbaiString(hintNode.getAttribute && hintNode.getAttribute('title')) + ' ' + bbaiString(hintNode.getAttribute && hintNode.getAttribute('data-bbai-tooltip')))) {
                return true;
            }
        }
    }

    if (bbaiIsGenerationActionControl(target) && bbaiIsUsageExhausted()) {
        return true;
    }

    return false;
}

bbaiRunWithJQuery(function($) {
    'use strict';

    const i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    const __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : (text) => text;
    const _n = i18n && typeof i18n._n === 'function' ? i18n._n : (single, plural, number) => (number === 1 ? single : plural);
    const sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : (format) => format;

    // Cache commonly used DOM elements (performance optimization)
    var $cachedElements = {};
    const FALLBACK_UPGRADE_SELECTOR = [
        '.bbai-upgrade-link',
        '.bbai-upgrade-inline',
        '.bbai-upgrade-cta-card button',
        '.bbai-upgrade-cta-card a',
        '.bbai-pro-upsell-card button',
        '.bbai-upgrade-banner button',
        '.bbai-upgrade-banner a',
        '.bbai-hero-actions [data-cta="upgrade"]',
        '.bbai-upgrade-callout button',
        '.bbai-upgrade-callout a',
        '[data-upgrade-trigger="true"]'
    ].join(', ');

    function getCachedElement(selector) {
        if (!$cachedElements[selector]) {
            $cachedElements[selector] = $(selector);
        }
        return $cachedElements[selector];
    }

    // ========================================
    // Monthly Reset Insight Modal
    // ========================================
    (function initResetModal() {
        var dashConfig = window.BBAI_DASH || {};
        var resetData = dashConfig.resetModal;

        if (!resetData || typeof resetData !== 'object') {
            return;
        }

        // Delay to let onboarding and other modals initialise first.
        setTimeout(function showIfIdle() {
            if (window.bbaiModal && window.bbaiModal.activeModal) {
                // Another modal is open — retry later.
                setTimeout(showIfIdle, 3000);
                return;
            }

            if (!window.bbaiModal || typeof window.bbaiModal.show !== 'function') {
                return;
            }

            var lastUsed  = parseInt(resetData.lastMonthUsed, 10) || 0;
            var lastLimit = parseInt(resetData.lastMonthLimit, 10) || 0;
            var newLimit  = parseInt(resetData.newLimit, 10) || 0;
            var plan      = resetData.plan || 'free';
            var planLabel = resetData.planLabel || 'Free';

            var message = '';
            if (lastUsed > 0 && lastLimit > 0) {
                var usagePct = Math.round((lastUsed / lastLimit) * 100);
                message += __('Last month you generated alt text for', 'beepbeep-ai-alt-text-generator')
                    + ' ' + lastUsed.toLocaleString() + ' '
                    + __('of', 'beepbeep-ai-alt-text-generator') + ' ' + lastLimit.toLocaleString()
                    + ' ' + __('images', 'beepbeep-ai-alt-text-generator') + ' (' + usagePct + '%).\n\n';
            } else {
                message += __('Your monthly quota has been refreshed.', 'beepbeep-ai-alt-text-generator') + '\n\n';
            }

            message += __('You now have', 'beepbeep-ai-alt-text-generator') + ' '
                + newLimit.toLocaleString() + ' '
                + __('credits available on your', 'beepbeep-ai-alt-text-generator') + ' '
                + planLabel + ' ' + __('plan.', 'beepbeep-ai-alt-text-generator');

            var buttons = [
                {
                    text: __('Got it', 'beepbeep-ai-alt-text-generator'),
                    primary: true,
                    action: function() {
                        dismissResetModal();
                        window.bbaiModal.close();
                    }
                }
            ];

            if (plan === 'free') {
                buttons.unshift({
                    text: __('Upgrade for more', 'beepbeep-ai-alt-text-generator'),
                    primary: false,
                    action: function() {
                        dismissResetModal();
                        window.bbaiModal.close();
                        var upgradeBtn = document.querySelector('[data-action="show-upgrade-modal"]');
                        if (upgradeBtn) {
                            upgradeBtn.click();
                        }
                    }
                });
            }

            window.bbaiModal.show({
                type: 'info',
                title: __('Monthly Quota Reset', 'beepbeep-ai-alt-text-generator'),
                message: message,
                buttons: buttons,
                onClose: function() {
                    dismissResetModal();
                }
            });
        }, 1500);

        function dismissResetModal() {
            var ajaxUrl = (window.bbai_ajax && window.bbai_ajax.ajaxurl) || '';
            var nonce   = (window.bbai_ajax && window.bbai_ajax.nonce) || '';
            if (!ajaxUrl || !nonce) { return; }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bbai_dismiss_reset_modal',
                    nonce: nonce
                }
            });
        }
    })();

    // Initialize when DOM is ready
    // Add vanilla JS handler as fallback (works even if jQuery isn't ready)
    (function() {
        function handleCheckoutClick(e) {
            const btn = e.target.closest('[data-action="checkout-plan"]');
            if (!btn) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout button clicked (vanilla JS handler)!');

            const plan = btn.getAttribute('data-plan') || btn.dataset.plan;
            const fallbackUrl = btn.getAttribute('data-fallback-url') || btn.dataset.fallbackUrl;

            // Resolve Stripe Payment Link: prefer data attribute, then localized links
            const stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
            const resolvedLink = fallbackUrl || stripeLinks[plan] || '';

            if (resolvedLink) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using Stripe payment link:', resolvedLink);
                window.open(resolvedLink, '_blank', 'noopener,noreferrer');
                if (typeof alttextaiCloseModal === 'function') {
                    alttextaiCloseModal();
                }
            } else {
                // Last resort: hardcoded Stripe Payment Links
                let stripeUrl = '';
                if (plan === 'pro' || plan === 'growth') {
                    stripeUrl = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
                } else if (plan === 'agency') {
                    stripeUrl = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
                } else if (plan === 'credits') {
                    stripeUrl = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
                }

                if (stripeUrl) {
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using default Stripe payment link:', stripeUrl);
                    window.open(stripeUrl, '_blank', 'noopener,noreferrer');
                    if (typeof alttextaiCloseModal === 'function') {
                        alttextaiCloseModal();
                    }
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No checkout URL available!');
                    alert(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
                }
            }

            return false;
        }
        
        // Attach event listener
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                document.addEventListener('click', handleCheckoutClick, true);
            });
        } else {
            document.addEventListener('click', handleCheckoutClick, true);
        }
    })();

    $(document).ready(function() {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] jQuery ready - setting up upgrade modal handlers');
        
        // Load license site usage if on agency license (delay for Admin tab)
        setTimeout(function() {
            if (typeof loadLicenseSiteUsage === 'function') {
                loadLicenseSiteUsage();
            }
        }, 500);
        
        // Check if we should open portal after login
        const shouldOpenPortal = localStorage.getItem('bbai_open_portal_after_login');
        
        if (shouldOpenPortal === 'true') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal flag found, checking authentication...');
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth state:', {
                hasAjax: !!window.bbai_ajax,
                isAuthenticated: window.bbai_ajax?.is_authenticated,
                ajaxObject: window.bbai_ajax
            });
            
            // Wait a bit longer for authentication state to be set
            // Check multiple times since the state might not be ready immediately
            let checkCount = 0;
            const maxChecks = 10;
            const checkInterval = setInterval(function() {
                checkCount++;
                const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
                
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal check attempt', checkCount, {
                    isAuthenticated: isAuthenticated,
                    authValue: window.bbai_ajax?.is_authenticated
                });
                
                if (isAuthenticated) {
                    clearInterval(checkInterval);
                    // Clear the flag
                    localStorage.removeItem('bbai_open_portal_after_login');
                    
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User authenticated, opening portal after login');
                    
                    // Set a flag to indicate we're opening after login (to prevent modal from showing)
                    sessionStorage.setItem('bbai_portal_after_login', 'true');
                    
                    // Small delay to ensure everything is ready
                    setTimeout(function() {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening portal now...');
                        openCustomerPortal();
                        
                        // Clear the session flag after a delay
                        setTimeout(function() {
                            sessionStorage.removeItem('bbai_portal_after_login');
                        }, 2000);
                    }, 300);
                } else if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    // If still not authenticated after checks, clear the flag
                    localStorage.removeItem('bbai_open_portal_after_login');
                    window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] User not authenticated after multiple checks, clearing portal flag');
                }
            }, 200); // Check every 200ms
        }
        
        // Handle upgrade CTA: use new pricing modal
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Upgrade CTA clicked via jQuery handler', this);
            
            // Direct fallback - show the modal directly (most reliable method)
            const modal = document.getElementById('bbai-upgrade-modal');
            if (modal) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Found modal, showing directly');
                modal.removeAttribute('style');
                modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
                modal.classList.add('active'); // Required for content to become visible (opacity: 1)
                modal.classList.add('is-visible');
                modal.setAttribute('aria-hidden', 'false');
                bbaiSetUpgradeModalScrollLock(true);

                // Also ensure modal content is visible
                const modalContent = modal.querySelector('.bbai-upgrade-modal__content');
                if (modalContent) {
                    modalContent.style.cssText = 'opacity: 1 !important; transform: translateY(0) scale(1) !important;';
                }
                return false;
            }

            // Prefer the server-rendered modal before optional bridge wrappers.
            if (typeof window.bbaiOpenUpgradeModal === 'function') {
                window.bbaiOpenUpgradeModal();
            } else if (typeof window.openPricingModal === 'function') {
                window.openPricingModal('enterprise');
            } else if (typeof bbaiApp !== 'undefined' && typeof bbaiApp.showModal === 'function') {
                // Fallback to old modal
                bbaiApp.showModal();
            } else if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal(); // Legacy fallback
            } else {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Pricing modal not available and DOM element not found!');
                if (typeof window.bbaiModal !== 'undefined') {
                    window.bbaiModal.warning(__('Upgrade modal not found. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                }
            }
            
            return false;
        });
        
        // Ensure legacy/future upgrade buttons automatically open the modal
        bindFallbackUpgradeTriggers();
        ensureUpgradeAttributes();
        observeFutureUpgradeTriggers();
        bindDirectUpgradeHandlers();
        
        // Handle auth banner button
        $(document).on('click', '#bbai-show-auth-banner-btn', function(e) {
            e.preventDefault();
            showAuthBanner();
        });
        
        // Handle auth login button
        $(document).on('click', '#bbai-show-auth-login-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });
        
        // Handle logout button (jQuery)
        $(document).on('click', '#bbai-logout-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleLogout();
        });
        
        // Handle show auth modal button
        $(document).on('click', '[data-action="show-auth-modal"]', function(e) {
            e.preventDefault();
            const authTab = $(this).attr('data-auth-tab') || 'login';
            showAuthModal(authTab);
        });
        
        // Handle billing portal
        $(document).on('click', '[data-action="open-billing-portal"]', function(e) {
            e.preventDefault();
            
            // Check if user is authenticated
            const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
                openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal
                if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    window.authModal.showLoginForm();
                } else if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                } else {
                    // Fallback: try to show auth modal manually
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        window.bbaiModal.warning(__('Please log in first to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                    }
                }
            }
        });
        
        // Handle demo signup buttons
        $(document).on('click', '#bbai-demo-signup-btn, #bbai-settings-signup-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });

        // Load subscription info if on Settings page
        if ($('#bbai-account-management').length) {
            loadSubscriptionInfo();
        }

        // Handle account management buttons
        $(document).on('click', '#bbai-update-payment-method, #bbai-manage-subscription', function(e) {
            e.preventDefault();
            
            // Check if user is authenticated
            const isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
            openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal
                if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    window.authModal.showLoginForm();
                } else if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                } else {
                    // Fallback: try to show auth modal manually
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    } else {
                        window.bbaiModal.warning(__('Please log in first to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                    }
                }
            }
        });

        $(document).on('click', '[data-action="manage-subscription"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            
            // Prevent multiple clicks
            if ($btn.hasClass('bbai-processing') || $btn.prop('disabled')) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Already processing, ignoring click');
                return false;
            }
            
            $btn.addClass('bbai-processing').prop('disabled', true);
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Manage subscription clicked');
            
            // Check if user is authenticated - check multiple sources
            const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
            const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
            
            // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
            const isAdminTab = $('.bbai-admin-content').length > 0;
            const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
            
            const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Authentication check:', {
                hasAjax: !!window.bbai_ajax,
                ajaxAuth: ajaxAuth,
                userDataAuth: userDataAuth,
                isAdminTab: isAdminTab,
                isAdminAuthenticated: isAdminAuthenticated,
                isAuthenticated: isAuthenticated,
                authValue: window.bbai_ajax?.is_authenticated,
                userData: window.bbai_ajax?.user_data
            });
            
            // Restore button state
            setTimeout(function() {
                $btn.removeClass('bbai-processing').prop('disabled', false);
            }, 1000);
            
            if (!isAuthenticated) {
                // User not authenticated - show login modal first
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User not authenticated, showing login modal');
                
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal - try multiple methods
                let modalShown = false;
                
                if (typeof window.authModal !== 'undefined' && window.authModal) {
                    if (typeof window.authModal.show === 'function') {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using window.authModal.show()');
                        window.authModal.show();
                        if (typeof window.authModal.showLoginForm === 'function') {
                            window.authModal.showLoginForm();
                        }
                        modalShown = true;
                    }
                }
                
                if (!modalShown && typeof showAuthModal === 'function') {
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using showAuthModal() function');
                    showAuthModal('login');
                    modalShown = true;
                }
                
                if (!modalShown) {
                    // Fallback: try to show auth modal manually
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Trying manual modal show');
                    const authModal = document.getElementById('alttext-auth-modal');
                    if (authModal) {
                        authModal.style.display = 'block';
                        authModal.setAttribute('aria-hidden', 'false');
                        document.body.style.overflow = 'hidden';
                        
                        // Try to show login form
                        const loginForm = document.getElementById('login-form');
                        const registerForm = document.getElementById('register-form');
                        if (loginForm) loginForm.style.display = 'block';
                        if (registerForm) registerForm.style.display = 'none';
                        
                        modalShown = true;
                    }
                }
                
                if (!modalShown) {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Could not show auth modal');
                    window.bbaiModal.warning(__('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.', 'beepbeep-ai-alt-text-generator'));
                }
                
                return false;
            }
            
            // User is authenticated, open portal directly
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User authenticated, opening portal');
            openCustomerPortal();
        });

        $(document).on('click', '[data-action="disconnect-account"]', function(e) {
            e.preventDefault();
            if (!confirm(__('Disconnect this account for all WordPress users? You can reconnect at any time.', 'beepbeep-ai-alt-text-generator'))) {
                return;
            }
            disconnectAccount($(this));
        });

        // Handle logout button (consolidated disconnect/logout)
        $(document).on('click', '[data-action="logout"]', function(e) {
            e.preventDefault();
            const $button = $(this);
            
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button clicked');
            
            if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
                window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                return;
            }

            // Show loading state
            $button.prop('disabled', true)
                   .addClass('bbai-btn-loading')
                   .attr('aria-busy', 'true');
            
            const originalText = $button.text();
            $button.html('<span class="bbai-spinner"></span> ' + __('Logging out...', 'beepbeep-ai-alt-text-generator'));

            $.ajax({
                url: window.bbai_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'beepbeepai_logout',
                    nonce: window.bbai_ajax.nonce
                },
                timeout: 15000,
                success: function(response) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout response:', response);
                    
                    if (response.success) {
                        // Show success message briefly
                        $button.removeClass('bbai-btn-loading')
                               .html('✓ ' + __('Logged out', 'beepbeep-ai-alt-text-generator'))
                               .attr('aria-busy', 'false');
                        
                        // Clear any cached data
                        if (typeof localStorage !== 'undefined') {
                            localStorage.removeItem('bbai_subscription_cache');
                            localStorage.removeItem('alttextai_token');
                            localStorage.removeItem('bbai_open_portal_after_login');
                        }
                        
                        // Redirect or reload
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            setTimeout(function() {
                                window.location.reload();
                            }, 500);
                        }
                    } else {
                        // Restore button
                        $button.prop('disabled', false)
                               .removeClass('bbai-btn-loading')
                               .text(originalText)
                               .attr('aria-busy', 'false');
                        
                        const errorMsg = response.data?.message || __('Failed to log out. Please try again.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout error:', status, error);
                    
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
                    let errorMessage = __('Unable to log out. Please try again.', 'beepbeep-ai-alt-text-generator');
                    
                    if (status === 'timeout') {
                        errorMessage = __('Request timed out. Try refreshing the page.', 'beepbeep-ai-alt-text-generator');
                    } else if (xhr.status === 0) {
                        errorMessage = __('Network error. Please check your connection and try again.', 'beepbeep-ai-alt-text-generator');
                    }
                    
                    window.bbaiModal.error(errorMessage);
                }
            });
        });

        // Handle checkout plan buttons in upgrade modal
        $(document).on('click', '[data-action="checkout-plan"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout button clicked!');
            
            const $btn = $(this);
            const plan = $btn.attr('data-plan') || $btn.data('plan');
            const priceId = $btn.attr('data-price-id') || $btn.data('price-id');
            const fallbackUrl = $btn.attr('data-fallback-url') || $btn.data('fallback-url');
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Checkout plan:', plan, 'priceId:', priceId, 'fallbackUrl:', fallbackUrl);

            // Resolve Stripe Payment Link: prefer data attribute, then localized links
            const stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
            const resolvedLink = fallbackUrl || stripeLinks[plan] || '';

            // Use direct Stripe Payment Links for reliable checkout
            if (resolvedLink) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using Stripe payment link:', resolvedLink);
                window.open(resolvedLink, '_blank', 'noopener,noreferrer');
                if (typeof alttextaiCloseModal === 'function') {
                    alttextaiCloseModal();
                }
            } else {
                // If no fallback URL, try to construct one based on plan
                let stripeUrl = '';
                if (plan === 'pro' || plan === 'growth') {
                    stripeUrl = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
                } else if (plan === 'agency') {
                    stripeUrl = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
                } else if (plan === 'credits') {
                    stripeUrl = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
                }

                if (stripeUrl) {
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using default Stripe payment link:', stripeUrl);
                    window.location.href = stripeUrl;
                    if (typeof alttextaiCloseModal === 'function') {
                        alttextaiCloseModal();
                    }
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No checkout URL available!');
                    if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                        window.bbaiModal.error(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
                    } else {
                        alert(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
                    }
                }
            }

            return false;
        });

        // Handle retry subscription fetch
        $(document).on('click', '#bbai-retry-subscription', function(e) {
            e.preventDefault();
            loadSubscriptionInfo(true); // Force refresh
        });
    });

    /**
     * Unified handler for upgrade triggers
     */
    function handleUpgradeTrigger(event, triggerElement) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        try {
            // Always show upgrade modal - it handles authentication internally
            if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal();
            } else {
                // Fallback: try to show modal directly
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.add('active');
                    modal.classList.add('is-visible');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    if (document.documentElement) {
                        document.documentElement.style.overflow = 'hidden';
                    }
                } else {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Upgrade modal not found in DOM');
                    // Last resort: check if user is authenticated and show auth modal
                    var isAuthed = false;
                    if (typeof window.bbai_ajax !== 'undefined' && typeof window.bbai_ajax.is_authenticated !== 'undefined') {
                        isAuthed = !!window.bbai_ajax.is_authenticated;
                    }
                    if (!isAuthed && typeof showAuthLogin === 'function') {
                        showAuthLogin();
                    }
                }
            }
        } catch (err) {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Error in handleUpgradeTrigger:', err);
            // Fallback: try to show modal directly
            try {
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.add('active');
                    modal.classList.add('is-visible');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    if (document.documentElement) {
                        document.documentElement.style.overflow = 'hidden';
                    }
                }
            } catch (e) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to show modal:', e);
            }
        }
    }

    function bindDirectUpgradeHandlers(targetSelector) {
        var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
        if (!selector) {
            return;
        }

        var nodes = document.querySelectorAll(selector);
        if (!nodes || !nodes.length) {
            return;
        }

        var ElementRef = typeof window !== 'undefined' && window.Element ? window.Element : null;

        nodes.forEach(function(el) {
            if (!el || (ElementRef && !(el instanceof ElementRef))) {
                return;
            }

            if (el.dataset && el.dataset.upgradeBound === '1') {
                return;
            }

            el.addEventListener('click', function(event) {
                handleUpgradeTrigger(event, el);
            }, true);

            if (el.dataset) {
                el.dataset.upgradeBound = '1';
            }
        });
    }

    /**
     * Bind fallback listeners for upgrade CTAs that might not have data-action attributes.
     * Ensures any future CTA using the shared CSS classes can still trigger the modal.
     */
    function bindFallbackUpgradeTriggers() {
        if (!FALLBACK_UPGRADE_SELECTOR) {
            return;
        }

        document.addEventListener('click', function(event) {
            var fallbackTrigger = event.target && event.target.closest(FALLBACK_UPGRADE_SELECTOR);
            if (!fallbackTrigger) {
                return;
            }

            // If the element (or ancestor) already has the data-action, let the jQuery handler fire instead.
            if (fallbackTrigger.closest('[data-action="show-upgrade-modal"]')) {
                return;
            }

            handleUpgradeTrigger(event, fallbackTrigger);
        }, true);
    }

    /**
     * Add data-action attributes to any CTA selectors missing them so that the
     * delegated jQuery handler can respond consistently.
     */
    function ensureUpgradeAttributes(targetSelector) {
        var selector = targetSelector || FALLBACK_UPGRADE_SELECTOR;
        if (!selector) {
            return;
        }

        var nodes = document.querySelectorAll(selector);
        if (!nodes || !nodes.length) {
            return;
        }

        var ElementRef = typeof window !== 'undefined' && window.Element ? window.Element : null;

        nodes.forEach(function(el) {
            if (!el || (ElementRef && !(el instanceof ElementRef))) {
                return;
            }

            if (!el.hasAttribute('data-action')) {
                el.setAttribute('data-action', 'show-upgrade-modal');
            }
            el.setAttribute('data-upgrade-trigger', 'true');
        });

        bindDirectUpgradeHandlers(selector);
    }

    function observeFutureUpgradeTriggers() {
        if (typeof MutationObserver === 'undefined' || !FALLBACK_UPGRADE_SELECTOR) {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (!mutation.addedNodes || !mutation.addedNodes.length) {
                    return;
                }

                mutation.addedNodes.forEach(function(node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches(FALLBACK_UPGRADE_SELECTOR)) {
                        ensureUpgradeAttributes();
                    } else if (node.querySelector) {
                        var matches = node.querySelector(FALLBACK_UPGRADE_SELECTOR);
                        if (matches) {
                            ensureUpgradeAttributes();
                        }
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Load subscription information from backend
     */
    function loadSubscriptionInfo(forceRefresh) {
        // Use cached DOM elements for better performance
        const $loading = getCachedElement('#bbai-subscription-loading');
        const $error = getCachedElement('#bbai-subscription-error');
        const $info = getCachedElement('#bbai-subscription-info');
        const $freeMessage = getCachedElement('#bbai-free-plan-message');

        // Check cache first (unless force refresh)
        if (!forceRefresh) {
            const cached = getCachedSubscriptionInfo();
            if (cached) {
                displaySubscriptionInfo(cached.data);
                return;
            }
        }

        // Show loading state
        $loading.show();
        $error.hide();
        $info.hide();
        $freeMessage.hide();

        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            showSubscriptionError(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                return;
            }

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
                data: {
                action: 'beepbeepai_get_subscription_info',
                nonce: window.bbai_ajax.nonce
            },
            success: function(response) {
                $loading.hide();

                if (response.success && response.data) {
                    // Reset retry attempts on success
                    resetRetryAttempts();
                    
                    // Cache the subscription info (15 minutes - optimized)
                    cacheSubscriptionInfo(response.data);
                    displaySubscriptionInfo(response.data);
                    
                    // Show success notice if redirected from portal
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('portal_return') === 'success') {
                        // Notice will be shown by WordPress admin notice
                        // Clean up URL
                        const cleanUrl = window.location.pathname + window.location.search.replace(/[?&]portal_return=success/, '').replace(/^&/, '?');
                        if (cleanUrl !== window.location.pathname + window.location.search) {
                            window.history.replaceState({}, document.title, cleanUrl);
                        }
                        // Force refresh cache after portal update
                        setTimeout(function() {
                            loadSubscriptionInfo(true);
                        }, 2000);
                    }
                } else {
                    const plan = response.data?.plan || 'free';
                    if (plan === 'free') {
                        $freeMessage.show();
            } else {
                        // Provide better error messages
                        let errorMessage = response.data?.message || __('Failed to load subscription information.', 'beepbeep-ai-alt-text-generator');
                        
                        if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                            errorMessage = __('Please log in to view your subscription information.', 'beepbeep-ai-alt-text-generator');
                        } else if (errorMessage.toLowerCase().includes('not found')) {
                            errorMessage = __('Subscription not found. If you just upgraded, please wait a moment and refresh.', 'beepbeep-ai-alt-text-generator');
                        }
                        
                        showSubscriptionError(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                
                // Provide better error messages
                let errorMessage = __('Network error. Please try again.', 'beepbeep-ai-alt-text-generator');
                
                if (xhr.status === 401 || xhr.status === 403) {
                    errorMessage = __('Please log in to view your subscription information.', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    return; // Don't retry auth errors
                } else if (xhr.status === 404) {
                    errorMessage = __('Subscription information not found. If you just signed up, please wait a moment and refresh.', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    return; // Don't retry 404 errors
                } else if (xhr.status >= 500 || status === 'timeout' || status === 'error') {
                    errorMessage = __('Service temporarily unavailable. Retrying automatically...', 'beepbeep-ai-alt-text-generator');
                    showSubscriptionError(errorMessage);
                    
                    // Retry with exponential backoff
                    retrySubscriptionLoad();
                return;
            }

                showSubscriptionError(errorMessage);
            }
        });
    }

    /**
     * Display subscription information
     */
    function displaySubscriptionInfo(data) {
        // Use cached DOM elements for better performance
        const $info = getCachedElement('#bbai-subscription-info');
        const $error = getCachedElement('#bbai-subscription-error');
        const $freeMessage = getCachedElement('#bbai-free-plan-message');

        // Hide other states
        $error.hide();
        $freeMessage.hide();

        // Handle free plan
        if (!data.plan || data.plan === 'free' || data.status === 'free') {
            $freeMessage.show();
                    return;
                }

        // Display subscription status (using cached elements)
        const status = data.status || 'active';
        const statusBadge = getCachedElement('#bbai-status-badge');
        const statusLabel = statusBadge.find('.bbai-status-label');
        
        statusBadge.removeClass('bbai-status-active bbai-status-cancelled bbai-status-trial');
        statusBadge.addClass('bbai-status-' + status);
        statusLabel.text(status.charAt(0).toUpperCase() + status.slice(1));

        // Show cancel warning if needed
        const $cancelWarning = getCachedElement('#bbai-cancel-warning');
        if (data.cancelAtPeriodEnd) {
            $cancelWarning.show();
                } else {
            $cancelWarning.hide();
        }

        // Display plan details (using cached elements)
        const planName = data.plan ? data.plan.charAt(0).toUpperCase() + data.plan.slice(1) : '-';
        getCachedElement('#bbai-plan-name').text(planName);

        const billingCycle = data.billingCycle ? data.billingCycle.charAt(0).toUpperCase() + data.billingCycle.slice(1) : '-';
        getCachedElement('#bbai-billing-cycle').text(billingCycle);

        // Format next billing date
        if (data.nextBillingDate) {
            const date = new Date(data.nextBillingDate);
            getCachedElement('#bbai-next-billing').text(date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            }));
            } else {
            getCachedElement('#bbai-next-billing').text('-');
        }

        // Format next charge amount
        if (data.nextChargeAmount !== undefined && data.nextChargeAmount !== null) {
            const currency = data.currency || 'GBP';
            const symbol = currency === 'USD' ? '$' : currency === 'EUR' ? '€' : '£';
            getCachedElement('#bbai-next-charge').text(symbol + parseFloat(data.nextChargeAmount).toFixed(2));
            } else {
            getCachedElement('#bbai-next-charge').text('-');
        }

        // Display payment method if available (using cached elements)
        if (data.paymentMethod && data.paymentMethod.last4) {
            const $paymentMethod = getCachedElement('#bbai-payment-method');
            const brand = data.paymentMethod.brand || 'card';
            const last4 = data.paymentMethod.last4;
            const expMonth = data.paymentMethod.expMonth;
            const expYear = data.paymentMethod.expYear;

            getCachedElement('#bbai-card-brand').text(getCardBrandIcon(brand) + ' ' + brand.toUpperCase());
            getCachedElement('#bbai-card-last4').text('•••• ' + last4);
            if (expMonth && expYear) {
                getCachedElement('#bbai-card-expiry').text(expMonth + '/' + expYear.toString().slice(-2));
            }
            $paymentMethod.show();
        } else {
            getCachedElement('#bbai-payment-method').hide();
        }

        // Show subscription info
        $info.show();
    }

    /**
     * Retry subscription load with exponential backoff
     */
    let retryAttempts = 0;
    const maxRetries = 5;
    let retryTimeout = null;

    function retrySubscriptionLoad() {
        if (retryAttempts >= maxRetries) {
            showSubscriptionError(__('Service unavailable after multiple attempts. Please try again later or refresh the page.', 'beepbeep-ai-alt-text-generator'));
            retryAttempts = 0;
                return;
            }

        retryAttempts++;
        
        // Exponential backoff: 1s, 2s, 4s, 8s, 16s
        const delay = Math.min(1000 * Math.pow(2, retryAttempts - 1), 16000);
        
        const $error = $('#bbai-subscription-error');
        $error.find('.bbai-error-message').text(
            sprintf(
                __('Service temporarily unavailable. Retrying in %1$d seconds... (Attempt %2$d/%3$d)', 'beepbeep-ai-alt-text-generator'),
                Math.ceil(delay / 1000),
                retryAttempts,
                maxRetries
            )
        );

        // Clear any existing timeout
        if (retryTimeout) {
            clearTimeout(retryTimeout);
        }

        retryTimeout = setTimeout(function() {
            loadSubscriptionInfo(true); // Force refresh, bypass cache
        }, delay);
    }

    /**
     * Reset retry attempts on successful load
     */
    function resetRetryAttempts() {
        retryAttempts = 0;
        if (retryTimeout) {
            clearTimeout(retryTimeout);
            retryTimeout = null;
        }
    }

    /**
     * Show subscription error
     */
    function showSubscriptionError(message) {
        const $error = $('#bbai-subscription-error');
        const $info = $('#bbai-subscription-info');
        const $freeMessage = $('#bbai-free-plan-message');

        $error.find('.bbai-error-message').text(message);
        $error.show();
        $info.hide();
        $freeMessage.hide();
    }

    /**
     * Get card brand icon/emoji
     */
    function getCardBrandIcon(brand) {
        const icons = {
            'visa': '💳',
            'mastercard': '💳',
            'amex': '💳',
            'discover': '💳',
            'diners': '💳',
            'jcb': '💳',
            'unionpay': '💳'
        };
        return icons[brand.toLowerCase()] || '💳';
    }

    /**
     * Cache subscription info in localStorage
     */
    function cacheSubscriptionInfo(data) {
        try {
            const cacheData = {
                data: data,
                timestamp: Date.now(),
                expiry: 15 * 60 * 1000 // 15 minutes (optimized - subscription info doesn't change frequently)
            };
            localStorage.setItem('bbai_subscription_cache', JSON.stringify(cacheData));
        } catch (e) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Could not cache subscription info:', e);
        }
    }

    /**
     * Get cached subscription info if still valid
     */
    function getCachedSubscriptionInfo() {
        try {
            const cached = localStorage.getItem('bbai_subscription_cache');
            if (!cached) return null;

            const cacheData = JSON.parse(cached);
            const age = Date.now() - cacheData.timestamp;

            if (age < cacheData.expiry) {
                return cacheData;
            } else {
                // Cache expired, remove it
                localStorage.removeItem('bbai_subscription_cache');
                return null;
            }
        } catch (e) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Could not read subscription cache:', e);
            return null;
        }
    }

    /**
     * Load license site usage data
     * Fetches and displays sites using the agency license
     */
    window.loadLicenseSiteUsage = function loadLicenseSiteUsage() {
        const $sitesContent = $('#bbai-license-sites-content');
        if (!$sitesContent.length) {
            // This is expected for free/Pro users - element only exists for Agency licenses
            // Silently return without logging (no need to clutter console for expected behavior)
            return; // Not on settings page or not agency license
        }

        // Check if user is authenticated (JWT) or has active license (license key auth)
        const isAuthenticated = window.bbai_ajax && (
            window.bbai_ajax.is_authenticated === true ||
            (window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0)
        );

        // In Admin tab, check if admin is logged in (separate session)
        const isAdminTab = $('.bbai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // If we're in admin content, admin is authenticated

        // Check if there's an active license (license key authentication)
        // Note: We can't directly check this from JS, but the backend will handle it
        // So we'll always attempt the request if we're on the license page (it means license is active)

        // Allow if authenticated (JWT) OR admin authenticated OR on license settings page (has license)
        const isOnLicensePage = $sitesContent.length > 0; // If this element exists, we're on license page
        
        if (!isAuthenticated && !isAdminAuthenticated && !isOnLicensePage) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Not authenticated and not on license page, skipping license sites load');
            return; // Don't load if not authenticated and not on license page
        }

        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Loading license site usage...', {
            isAuthenticated: isAuthenticated,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated
        });

        // Show loading state
        $sitesContent.html(
            '<div class="bbai-settings-license-sites-loading">' +
            '<span class="bbai-spinner"></span> ' +
            __('Loading site usage...', 'beepbeep-ai-alt-text-generator') +
            '</div>'
        );

        // Fetch license site usage
        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_get_license_sites',
                nonce: window.bbai_ajax.nonce
            },
            success: function(response) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] License sites response:', response);
                if (response.success && response.data) {
                    // Handle both array response and object with sites property
                    const sites = Array.isArray(response.data) ? response.data : (response.data.sites || []);
                    if (sites && sites.length > 0) {
                        displayLicenseSites(sites);
                    } else {
                        $sitesContent.html(
                            '<div class="bbai-settings-license-sites-empty">' +
                            '<p>' + __('No sites are currently using this license.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                            '</div>'
                        );
                    }
                } else {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] License sites request failed:', response);
                    $sitesContent.html(
                        '<div class="bbai-settings-license-sites-error">' +
                        '<p>' + (response.data?.message || __('Failed to load site usage. Please try again.', 'beepbeep-ai-alt-text-generator')) + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to load license sites:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                $sitesContent.html(
                    '<div class="bbai-settings-license-sites-error">' +
                    '<p>' + __('Failed to load site usage. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                    '</div>'
                );
            }
        });
    };

    /**
     * Display license site usage data
     */
    window.displayLicenseSites = function displayLicenseSites(sites) {
        const $sitesContent = $('#bbai-license-sites-content');
        
        if (!sites || sites.length === 0) {
            $sitesContent.html(
                '<div class="bbai-settings-license-sites-empty">' +
                '<p>' + __('No sites are currently using this license.', 'beepbeep-ai-alt-text-generator') + '</p>' +
                '</div>'
            );
            return;
        }

        let html = '<div class="bbai-settings-license-sites-list">';
        html += '<div class="bbai-settings-license-sites-summary">';
        html += '<strong>' + sites.length + '</strong> ' + _n('site using this license', 'sites using this license', sites.length, 'beepbeep-ai-alt-text-generator');
        html += '</div>';
        html += '<ul class="bbai-settings-license-sites-items">';

        sites.forEach(function(site) {
            const siteName = site.site_name || site.install_id || __('Unknown Site', 'beepbeep-ai-alt-text-generator');
            const siteId = site.siteId || site.install_id || site.installId || '';
            const generations = site.total_generations || site.generations || 0;
            const lastUsed = site.last_used ? new Date(site.last_used).toLocaleDateString() : __('Never', 'beepbeep-ai-alt-text-generator');
            const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
            const disconnectAriaLabel = sprintf(__('Disconnect %s', 'beepbeep-ai-alt-text-generator'), siteName);
            
            html += '<li class="bbai-settings-license-sites-item">';
            html += '<div class="bbai-settings-license-sites-item-main">';
            html += '<div class="bbai-settings-license-sites-item-info">';
            html += '<div class="bbai-settings-license-sites-item-name">' + escapeHtml(siteName) + '</div>';
            html += '<div class="bbai-settings-license-sites-item-stats">';
            html += '<span class="bbai-settings-license-sites-item-generations">';
            html += '<strong>' + generations.toLocaleString() + '</strong> ' + escapeHtml(__('alt text generated', 'beepbeep-ai-alt-text-generator'));
            html += '</span>';
            html += '<span class="bbai-settings-license-sites-item-last">';
            html += escapeHtml(__('Last used:', 'beepbeep-ai-alt-text-generator')) + ' ' + escapeHtml(lastUsed);
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="bbai-settings-license-sites-item-actions">';
            html += '<button type="button" class="bbai-settings-license-sites-disconnect-btn" ';
            html += 'data-site-id="' + escapeHtml(siteId) + '" ';
            html += 'data-site-name="' + escapeHtml(siteName) + '" ';
            html += 'aria-label="' + escapeHtml(disconnectAriaLabel) + '">';
            html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">';
            html += '<path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
            html += '</svg>';
            html += '<span>' + escapeHtml(disconnectLabel) + '</span>';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            html += '</li>';
        });

        html += '</ul>';
        html += '</div>';

        $sitesContent.html(html);
        
        // Attach disconnect button handlers
        attachDisconnectHandlers();
    }

    /**
     * Attach event handlers for disconnect buttons
     */
    function attachDisconnectHandlers() {
        $(document).off('click', '.bbai-settings-license-sites-disconnect-btn');
        $(document).on('click', '.bbai-settings-license-sites-disconnect-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const siteId = $btn.data('site-id');
            const siteName = $btn.data('site-name') || siteId;
            
            if (!siteId) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No site ID provided for disconnect');
                return;
            }
            
            // Confirm disconnect action
            // For confirm dialog, we need plain text (not HTML-escaped)
            const siteNameText = siteName.replace(/"/g, '&quot;').replace(/\n/g, ' ');
            if (!confirm(sprintf(__('Are you sure you want to disconnect "%s"?\n\nThis will remove the site from your license. The site will need to reconnect using the license key.', 'beepbeep-ai-alt-text-generator'), siteNameText))) {
                return;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true)
                .addClass('bbai-processing')
                .html('<span class="bbai-spinner"></span> ' + __('Disconnecting...', 'beepbeep-ai-alt-text-generator'));
            
            // Make AJAX request to disconnect site
            $.ajax({
                url: window.bbai_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'beepbeepai_disconnect_license_site',
                    site_id: siteId,
                    nonce: window.bbai_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Site disconnected successfully:', siteName);
                        
                        // Reload the license sites list
                        loadLicenseSiteUsage();
                    } else {
                        // Show error message
                        const details = response.data?.message || __('Unknown error', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(sprintf(__('Failed to disconnect site: %s', 'beepbeep-ai-alt-text-generator'), details));
                        
                        // Restore button
                        const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
                        $btn.prop('disabled', false)
                            .removeClass('bbai-processing')
                            .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>' + escapeHtml(disconnectLabel) + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Failed to disconnect site:', error);
                    window.bbaiModal.error(__('Failed to disconnect site. Please try again.', 'beepbeep-ai-alt-text-generator'));
                    
                    // Restore button
                    const disconnectLabel = __('Disconnect', 'beepbeep-ai-alt-text-generator');
                    $btn.prop('disabled', false)
                        .removeClass('bbai-processing')
                        .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>' + escapeHtml(disconnectLabel) + '</span>');
                }
            });
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Open Stripe Customer Portal
     * Opens Stripe billing portal in new tab for subscription management
     */
    function openCustomerPortal() {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening customer portal...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
                    return;
                }
        
        // Check authentication before making the request - check multiple sources
        const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
        const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
        
        // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
        const isAdminTab = $('.bbai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
        
        const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
        
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] openCustomerPortal() - auth check:', {
            hasAjax: !!window.bbai_ajax,
            ajaxAuth: ajaxAuth,
            userDataAuth: userDataAuth,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated,
            isAuthenticated: isAuthenticated,
            authValue: window.bbai_ajax?.is_authenticated,
            userData: window.bbai_ajax?.user_data
        });
        
        if (!isAuthenticated) {
            // Check if we're trying to open portal after login (in which case, don't show modal again)
            const isAfterLogin = sessionStorage.getItem('bbai_portal_after_login') === 'true';
            
            if (isAfterLogin) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal opened after login but auth check failed - waiting for auth state...');
                // Wait a bit and retry
                setTimeout(function() {
                    const retryAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
                    if (retryAuth) {
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth state now ready, retrying portal...');
                        openCustomerPortal();
                    } else {
                        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Auth state still not ready after retry');
                        sessionStorage.removeItem('bbai_portal_after_login');
                    }
                }, 1000);
                return;
            }
            
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Not authenticated in openCustomerPortal, showing login modal');
            // User not authenticated - show login modal instead
            localStorage.setItem('bbai_open_portal_after_login', 'true');
            
            // Show login modal - try multiple methods
            let modalShown = false;
            
            if (typeof window.authModal !== 'undefined' && window.authModal) {
                if (typeof window.authModal.show === 'function') {
                    window.authModal.show();
                    if (typeof window.authModal.showLoginForm === 'function') {
                        window.authModal.showLoginForm();
                    }
                    modalShown = true;
                }
            }
            
            if (!modalShown && typeof showAuthModal === 'function') {
                showAuthModal('login');
                modalShown = true;
            }
            
            if (!modalShown) {
                // Fallback: try to show auth modal manually
                const authModal = document.getElementById('alttext-auth-modal');
                if (authModal) {
                    authModal.style.display = 'block';
                    authModal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    
                    // Try to show login form
                    const loginForm = document.getElementById('login-form');
                    const registerForm = document.getElementById('register-form');
                    if (loginForm) loginForm.style.display = 'block';
                    if (registerForm) registerForm.style.display = 'none';
                    modalShown = true;
                }
            }
            
            if (!modalShown) {
                window.bbaiModal.warning(__('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.', 'beepbeep-ai-alt-text-generator'));
            }
            
            return;
        }

        // Find all manage subscription buttons
        const $buttons = $('[data-action="manage-subscription"], #bbai-update-payment-method, #bbai-manage-subscription');
        
        // Show loading state with visual feedback
        $buttons.prop('disabled', true)
                .addClass('bbai-btn-loading')
                .attr('aria-busy', 'true');
        
        // Update button text temporarily
        const originalText = {};
        $buttons.each(function() {
            const $btn = $(this);
            originalText[$btn.attr('id') || 'btn'] = $btn.text();
            $btn.html('<span class="bbai-spinner"></span> ' + __('Opening portal...', 'beepbeep-ai-alt-text-generator'));
        });

                $.ajax({
            url: window.bbai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                action: 'beepbeepai_create_portal',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal response:', response);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || __('Manage Subscription', 'beepbeep-ai-alt-text-generator'));
                });

                if (response.success && response.data && response.data.url) {
                    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening portal URL:', response.data.url);
                    
                    // Open portal in new tab
                    const portalWindow = window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    
                    if (!portalWindow) {
                        window.bbaiModal.warning(__('Please allow popups for this site to manage your subscription.', 'beepbeep-ai-alt-text-generator'));
                        return;
                    }
                    
                    // Monitor for user return and refresh subscription data
                    let checkCount = 0;
                    const maxChecks = 150; // 5 minutes max
                    
                    const checkInterval = setInterval(function() {
                        checkCount++;
                        if (document.hasFocus() || checkCount >= maxChecks) {
                            clearInterval(checkInterval);
                            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] User returned, refreshing data...');
                            
                            // Reload subscription info
                            if (typeof loadSubscriptionInfo === 'function') {
                                loadSubscriptionInfo(true); // Force refresh
                            }
                            
                            // Refresh usage stats on dashboard
                            if (typeof window.alttextai_refresh_usage === 'function') {
                                window.alttextai_refresh_usage();
                            }
                        }
                    }, 2000);
                } else {
                    // Provide context-aware error messages
                    let errorMessage = response.data?.message || __('Failed to open customer portal. Please try again.', 'beepbeep-ai-alt-text-generator');
                    
                    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Portal request failed:', errorMessage);
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login') || errorMessage.toLowerCase().includes('authentication required')) {
                        // This shouldn't happen since we check auth first, but handle it gracefully
                        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Authentication error from server, showing login modal');
                        localStorage.setItem('bbai_open_portal_after_login', 'true');
                        
                        // Try to show login modal instead of alert
                        let modalShown = false;
                        if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                            window.authModal.show();
                            window.authModal.showLoginForm();
                            // Don't show error message - the login modal itself indicates authentication is required
                            modalShown = true;
                        } else if (typeof showAuthModal === 'function') {
                            showAuthModal('login');
                            modalShown = true;
                        } else {
                            const authModal = document.getElementById('alttext-auth-modal');
                            if (authModal) {
                                authModal.style.display = 'block';
                                authModal.setAttribute('aria-hidden', 'false');
                                document.body.style.overflow = 'hidden';
                                const loginForm = document.getElementById('login-form');
                                const registerForm = document.getElementById('register-form');
                                if (loginForm) loginForm.style.display = 'block';
                                if (registerForm) registerForm.style.display = 'none';
                                modalShown = true;
                            }
                        }
                        
                        if (!modalShown) {
                            window.bbaiModal.warning(__('Please log in first to manage your billing.\n\nClick the "Login" button in the top navigation.', 'beepbeep-ai-alt-text-generator'));
                        }
                    } else if (errorMessage.toLowerCase().includes('not found') || errorMessage.toLowerCase().includes('subscription')) {
                        errorMessage = __('No active subscription found.\n\nPlease upgrade to a paid plan first, then you can manage your subscription.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMessage);
                    } else if (errorMessage.toLowerCase().includes('customer')) {
                        errorMessage = __('Unable to find your billing account.\n\nPlease contact support for assistance.', 'beepbeep-ai-alt-text-generator');
                        window.bbaiModal.error(errorMessage);
                    } else {
                    window.bbaiModal.error(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Portal error:', status, error, xhr);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || __('Manage Subscription', 'beepbeep-ai-alt-text-generator'));
                });
                
                // Provide helpful error message based on status
                let errorMessage = __('Unable to connect to billing system. Please try again.', 'beepbeep-ai-alt-text-generator');
                
                if (status === 'timeout') {
                    errorMessage = __('Request timed out. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr.status === 0) {
                    errorMessage = __('Network connection lost. Please check your internet and try again.', 'beepbeep-ai-alt-text-generator');
                } else if (xhr.status >= 500) {
                    errorMessage = __('Billing system is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
                }
                
                window.bbaiModal.error(errorMessage);
            }
        });
    }

    /**
     * Initiate Stripe Checkout
     * Creates checkout session and opens in new tab
     */
    function initiateCheckout($button, priceId, planName) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Initiating checkout:', planName, priceId);

        // Resolve Stripe Payment Link from button data or localized config
        const fallbackUrl = $button.attr('data-fallback-url') || '';
        const stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
        let resolvedLink = fallbackUrl || stripeLinks[planName] || '';

        if (!resolvedLink) {
            // Hardcoded fallback Payment Links
            if (planName === 'pro' || planName === 'growth') {
                resolvedLink = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
            } else if (planName === 'agency') {
                resolvedLink = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
            } else if (planName === 'credits') {
                resolvedLink = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
            }
        }

        if (resolvedLink) {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening Stripe payment link:', resolvedLink);
            window.open(resolvedLink, '_blank', 'noopener,noreferrer');
            return;
        }

        // No link available
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No Stripe payment link available for plan:', planName);
        if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error(__('Unable to initiate checkout. Please try again or contact support.', 'beepbeep-ai-alt-text-generator'));
        }
    }

    /**
     * Disconnect Account
     * Clears authentication and allows another admin to connect
     */
    function disconnectAccount($button) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Disconnecting account...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error(__('Configuration error. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('bbai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalText = $button.text();
        $button.html('<span class="bbai-spinner"></span> ' + __('Disconnecting...', 'beepbeep-ai-alt-text-generator'));

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_disconnect_account',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 15000, // 15 second timeout
            success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Disconnect response:', response);
                
	                if (response.success) {
	                    // Show success message
	                    $button.removeClass('bbai-btn-loading')
	                           .removeClass('bbai-btn--ghost')
	                           .addClass('bbai-btn--success')
	                           .html('✓ ' + __('Disconnected', 'beepbeep-ai-alt-text-generator'))
	                           .attr('aria-busy', 'false');
                    
                    // Clear any cached data
                    if (typeof localStorage !== 'undefined') {
                        localStorage.removeItem('bbai_subscription_cache');
                        localStorage.removeItem('alttextai_token');
                    }
                    
                    // Reload after brief delay to show success state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
	                    const errorMsg = response.data?.message || __('Failed to disconnect account. Please try again.', 'beepbeep-ai-alt-text-generator');
	                    window.bbaiModal.error(errorMsg);
	                }
	            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Disconnect error:', status, error);
                
                // Restore button
	                $button.prop('disabled', false)
	                       .removeClass('bbai-btn-loading')
	                       .text(originalText)
	                       .attr('aria-busy', 'false');
	                
	                // Provide helpful error message
	                let errorMessage = __('Unable to disconnect account. Please try again.', 'beepbeep-ai-alt-text-generator');
	                
	                if (status === 'timeout') {
	                    errorMessage = __('Request timed out. Your connection may still be disconnected. Try refreshing the page.', 'beepbeep-ai-alt-text-generator');
	                } else if (xhr.status === 0) {
	                    errorMessage = __('Network error. Please check your connection and try again.', 'beepbeep-ai-alt-text-generator');
	                }
	                
	                window.bbaiModal.error(errorMessage);
	            }
	        });
	    }

});

// Debug mode check (define early so it can be used in functions)
var alttextaiDebug = (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false;

function findUpgradeModalElement() {
    var modalById = document.getElementById('bbai-upgrade-modal');
    if (modalById && modalById.querySelector('.bbai-upgrade-modal__content')) {
        if (document.body && modalById.parentNode !== document.body) {
            document.body.appendChild(modalById);
        }
        return modalById;
    }

    var modalByData = document.querySelector('[data-bbai-upgrade-modal="1"]');
    if (modalByData && modalByData.querySelector('.bbai-upgrade-modal__content')) {
        if (modalByData.id !== 'bbai-upgrade-modal') {
            modalByData.id = 'bbai-upgrade-modal';
        }
        if (document.body && modalByData.parentNode !== document.body) {
            document.body.appendChild(modalByData);
        }
        return modalByData;
    }

    return null;
}

// Check if modal exists when script loads
(function() {
    'use strict';
    function checkModalExists() {
        const modal = findUpgradeModalElement();
        if (!modal) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Upgrade modal not found in DOM. Make sure upgrade-modal.php is included.');
        } else {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Upgrade modal found in DOM');
        }
    }
    
    // Check immediately and also after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkModalExists);
    } else {
        checkModalExists();
    }
})();

// Global app object to avoid collisions
var bbaiApp = bbaiApp || {};

function bbaiSetUpgradeModalScrollLock(isLocked) {
    if (document.body && document.body.classList) {
        document.body.classList.toggle('modal-open', !!isLocked);
        document.body.classList.toggle('bbai-modal-open', !!isLocked);
    }

    if (document.body) {
        document.body.style.overflow = isLocked ? 'hidden' : '';
    }

    if (document.documentElement) {
        document.documentElement.style.overflow = isLocked ? 'hidden' : '';
    }
}

// Global functions for modal - make it very robust (legacy support)
function alttextaiShowModal() {
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] alttextaiShowModal() called');
    const modal = findUpgradeModalElement();
    
    if (!modal) {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Upgrade modal element not found in DOM!');
        if (window.bbaiModal && typeof window.bbaiModal.warning === 'function') {
            window.bbaiModal.warning(__('Upgrade modal not found. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
        }
        return false;
    }
    
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Modal element found:', modal);
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Current display:', window.getComputedStyle(modal).display);
    
    // Remove inline display:none to allow transition
    if (modal.style.display === 'none') {
        modal.style.display = 'flex';
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
    }
    
    // Force reflow to ensure initial state is applied
    void modal.offsetHeight;
    
    // Now add active class to trigger smooth animation
    modal.classList.add('active');
    modal.classList.add('is-visible');
    modal.removeAttribute('aria-hidden');
    bbaiSetUpgradeModalScrollLock(true);
    
    // Focus the close button after animation starts
    setTimeout(function() {
        const closeBtn = modal.querySelector('.bbai-upgrade-modal__close, .bbai-modal-close, [data-action="close-modal"], button[aria-label*="Close"]');
        if (closeBtn && typeof closeBtn.focus === 'function') {
            closeBtn.focus();
        }
    }, 150);
    
    return true;
}

// Make it available globally for onclick handlers (legacy support)
window.alttextaiShowModal = alttextaiShowModal;

// Also create a simple global function that always tries to show the modal
window.showUpgradeModal = function() {
    window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] window.showUpgradeModal() called');
    
    // Try the main function first
    if (typeof alttextaiShowModal === 'function') {
        if (alttextaiShowModal()) {
            return true;
        }
    }
    
    // Direct DOM manipulation as ultimate fallback
    var modal = findUpgradeModalElement();
    
    if (modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Found modal, showing directly');
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        modal.style.zIndex = '999999';
        modal.classList.add('active');
        modal.classList.add('is-visible');
        modal.removeAttribute('aria-hidden');
        bbaiSetUpgradeModalScrollLock(true);
        return true;
    }
    
    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Modal not found in DOM');
    return false;
};

// Provide legacy/global aliases used by dashboard bridge code.
window.bbaiShowUpgradeModal = window.showUpgradeModal;
window.alttextaiShowUpgradeModal = window.showUpgradeModal;

// Also add to bbaiApp namespace
bbaiApp.showModal = alttextaiShowModal;

// Also create a simple test function
window.testUpgradeModal = function() {
    window.BBAI_LOG && window.BBAI_LOG.log('=== Testing Upgrade Modal ===');
    const modal = findUpgradeModalElement();
    window.BBAI_LOG && window.BBAI_LOG.log('Modal element:', modal);
    if (modal) {
        window.BBAI_LOG && window.BBAI_LOG.log('Modal HTML:', modal.outerHTML.substring(0, 200));
        window.BBAI_LOG && window.BBAI_LOG.log('Modal classes:', modal.className);
        window.BBAI_LOG && window.BBAI_LOG.log('Modal inline style:', modal.getAttribute('style'));
        alttextaiShowModal();
    } else {
        window.BBAI_LOG && window.BBAI_LOG.error('Modal not found!');
    }
};

function alttextaiCloseModal() {
    const modal = findUpgradeModalElement();
    if (modal) {
        // Remove active class to trigger CSS transition
        modal.classList.remove('active');
        modal.classList.remove('is-visible');
        
        // Wait for animation to complete before hiding
        setTimeout(function() {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }, 300); // Match animation duration
        
        // Restore body scroll
        bbaiSetUpgradeModalScrollLock(false);
        if (typeof window.bbaiResetUpgradeModalContext === 'function') {
            window.bbaiResetUpgradeModalContext();
        }
    }
}

// Add to bbaiApp namespace
bbaiApp.closeModal = alttextaiCloseModal;

// Make it available globally for onclick handlers (legacy support)
window.alttextaiCloseModal = alttextaiCloseModal;

// Global fallback handler for upgrade buttons (works even if jQuery isn't ready)
// This ensures the upgrade modal works on all tabs, even if jQuery handlers fail
(function() {
    'use strict';
    
    // Function to show modal directly with aggressive approach
    function showModalDirectly() {
        const modal = findUpgradeModalElement();
        if (!modal) {
            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Upgrade modal not found in DOM');
            return false;
        }
        
        // Remove inline style completely, then set with !important
        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
        modal.classList.add('active');
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        bbaiSetUpgradeModalScrollLock(true);
        
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Modal shown via direct method');
        return true;
    }
    
    // Use event delegation on document to catch all clicks (capture phase for early handling)
    document.addEventListener('click', function(e) {
        if (e.defaultPrevented) {
            return;
        }

        // Check if clicked element or its parent has data-action="show-upgrade-modal"
        const trigger = e.target.closest('[data-action="show-upgrade-modal"]');
        if (trigger) {
            if (bbaiIsGenerationActionControl(trigger) && bbaiIsLockedActionControl(trigger)) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                    window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
                } else {
                    showUpgradeModalFallback();
                }
                return false;
            }

            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Global vanilla JS handler: Upgrade CTA clicked', trigger);
            
            // Prevent default immediately
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            
            // Show modal directly - don't wait for anything
            if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal();
            } else {
                showModalDirectly();
            }
            
            return false; // Prevent any further event handling
        }
        
        // Handle close button clicks
        const closeBtn = e.target.closest('.bbai-modal-close, [onclick*="alttextaiCloseModal"]');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                const modal = findUpgradeModalElement();
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    bbaiSetUpgradeModalScrollLock(false);
                }
            }
            return false;
        }
        
        // Handle backdrop clicks (click outside modal content to close)
        const modal = findUpgradeModalElement();
        if (modal && modal.style.display === 'flex' && e.target === modal) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                bbaiSetUpgradeModalScrollLock(false);
            }
            return false;
        }
    }, true); // Use capture phase to catch events early
    
    // Handle ESC key globally to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const modal = findUpgradeModalElement();
            if (modal && modal.style.display === 'flex') {
                e.preventDefault();
                if (typeof alttextaiCloseModal === 'function') {
                    alttextaiCloseModal();
                } else {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    bbaiSetUpgradeModalScrollLock(false);
                }
            }
        }
    });
})();

function openStripeLink(url) {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

function showAuthBanner() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth banner');
    
    // Try to show the auth modal
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                        } else {
        // Try to find and show the auth modal directly
        const authModal = document.getElementById('alttext-auth-modal');
	        if (authModal) {
	            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth modal directly');
	            authModal.style.display = 'block';
	        } else {
	            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal not found');
	            window.bbaiModal.error(__('Authentication system not available. Please refresh the page.', 'beepbeep-ai-alt-text-generator'));
	        }
	    }
	}

function showAuthLogin() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth login');
    
    // Try multiple methods to show auth modal (check bbaiApp namespace first)
    if (window.bbaiApp && window.bbaiApp.authModal && typeof window.bbaiApp.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using bbaiApp.authModal.show()');
        window.bbaiApp.authModal.show();
        return;
    } else if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
        // Legacy fallback
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using AltTextAuthModal.show()');
        window.AltTextAuthModal.show();
        return;
    }

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                    return;
                }

    // Fallback to showAuthBanner method
    showAuthBanner();
}

function showAuthModal(tab) {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Showing auth modal, tab:', tab);
    
    // Try to show auth modal with specific tab
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
        
        // Switch to the appropriate form
        if (tab === 'register' && typeof window.authModal.showRegisterForm === 'function') {
            window.authModal.showRegisterForm();
        } else if (typeof window.authModal.showLoginForm === 'function') {
            window.authModal.showLoginForm();
        }
        return;
    }

    // Fallback to showAuthLogin
    showAuthLogin();
}

function handleLogout() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Handling logout');
    
    // Make AJAX request to logout
    if (typeof window.bbai_ajax === 'undefined') {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] bbai_ajax object not found');
        // Still try to clear token and reload
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('alttextai_token');
        }
        window.location.reload();
                    return;
                }

    const ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || '';
    const nonce = window.bbai_ajax.nonce || '';
    
    if (!ajaxUrl) {
        window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] AJAX URL not found');
        window.location.reload();
                    return;
                }

    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Calling logout AJAX:', ajaxUrl);
    
    // Try jQuery first, fallback to vanilla JS
    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
        jQuery.ajax({
            url: ajaxUrl,
                type: 'POST',
                    data: {
                action: 'beepbeepai_logout',
                nonce: nonce
                    },
                    success: function(response) {
                if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout successful', response);
                // Clear any local storage
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
            // Redirect to plugin dashboard/sign-up page after logout
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
                    },
                    error: function(xhr, status, error) {
                window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout failed:', error, xhr.responseText);
                // Even on error, clear local storage and reload
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
            }
        });
                        } else {
        // Vanilla JS fallback
        const formData = new FormData();
        formData.append('action', 'beepbeepai_logout');
        formData.append('nonce', nonce);
        
        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout response status:', response.status);
            return response.json().catch(() => ({}));
        })
        .then(data => {
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout successful', data);
            // Clear any local storage
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        })
        .catch(error => {
            window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] Logout failed:', error);
            // Even on error, clear local storage and reload
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        });
    }
}

// Initialize auth modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] DOM loaded, initializing auth system');
    
    // Vanilla JS fallback for logout button (in case jQuery isn't ready)
    const logoutBtn = document.getElementById('bbai-logout-btn');
    if (logoutBtn) {
        // Remove any existing listeners to avoid duplicates
        const newLogoutBtn = logoutBtn.cloneNode(true);
        logoutBtn.parentNode.replaceChild(newLogoutBtn, logoutBtn);
        
        // Add vanilla JS event listener
        newLogoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button clicked (Vanilla JS)');
            handleLogout();
        });
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button found and listener attached');
    } else {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Logout button not found');
    }
    
    // Check if auth modal exists and initialize it (debug only)
    if (alttextaiDebug) {
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal found');
        } else {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Auth modal not found - may need to be created');
        }
        
        // Check if authModal object exists
        if (typeof window.authModal !== 'undefined') {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] authModal object found');
        } else {
            window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] authModal object not found');
        }
    }
    
    // Initialize countdown timer
    initCountdownTimer();
    
    // Also try initializing after a short delay in case DOM isn't ready
    setTimeout(function() {
        if (!document.querySelector('.bbai-countdown[data-countdown]')) {
            return; // Still not found
        }
        // Re-initialize if not already running
        if (!window.alttextaiCountdownInterval) {
            initCountdownTimer();
        }
    }, 500);
});

/**
 * Initialize and update countdown timer for limit reset
 */
function initCountdownTimer() {
    const countdownElement = document.querySelector('.bbai-countdown[data-countdown]');
    if (!countdownElement) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown element not found');
        return; // No countdown on this page
    }

    const totalSeconds = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
    
    // Check if we have valid seconds
    if (totalSeconds <= 0) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown has zero or invalid seconds:', totalSeconds);
        return;
    }

    const daysEl = countdownElement.querySelector('[data-days]');
    const hoursEl = countdownElement.querySelector('[data-hours]');
    const minutesEl = countdownElement.querySelector('[data-minutes]');

    if (!daysEl || !hoursEl || !minutesEl) {
        if (alttextaiDebug) window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Countdown elements not found', {
            days: !!daysEl,
            hours: !!hoursEl,
            minutes: !!minutesEl
        });
        return;
    }

    // Store initial seconds and start time for accurate countdown
    countdownElement.setAttribute('data-initial-seconds', totalSeconds.toString());
    countdownElement.setAttribute('data-start-time', (Date.now() / 1000).toString());

    if (alttextaiDebug) {
        window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Countdown initialized:', {
            totalSeconds: totalSeconds,
            days: Math.floor(totalSeconds / 86400),
            hours: Math.floor((totalSeconds % 86400) / 3600),
            minutes: Math.floor((totalSeconds % 3600) / 60)
        });
    }

    function updateCountdown() {
        // Try to use reset timestamp first (most accurate)
        const resetTimestamp = parseInt(countdownElement.getAttribute('data-reset-timestamp'), 10) || 0;
        let remaining = 0;
        
        if (resetTimestamp > 0) {
            // Calculate from actual reset timestamp
            const currentTime = Math.floor(Date.now() / 1000);
            remaining = Math.max(0, resetTimestamp - currentTime);
        } else {
            // Fallback: use elapsed seconds method
        const initialSeconds = parseInt(countdownElement.getAttribute('data-initial-seconds'), 10) || 0;
            
            if (initialSeconds <= 0) {
                daysEl.textContent = '0';
                hoursEl.textContent = '0';
                minutesEl.textContent = '0';
                if (window.alttextaiCountdownInterval) {
                    clearInterval(window.alttextaiCountdownInterval);
                    window.alttextaiCountdownInterval = null;
                }
                return;
            }
        
        // Calculate elapsed time since page load
        const startTime = parseFloat(countdownElement.getAttribute('data-start-time')) || (Date.now() / 1000);
        const currentTime = Date.now() / 1000;
            const elapsed = Math.max(0, Math.floor(currentTime - startTime));
        
        // Calculate remaining seconds
            remaining = Math.max(0, initialSeconds - elapsed);
        }

        if (remaining <= 0) {
            daysEl.textContent = '0';
            hoursEl.textContent = '0';
            minutesEl.textContent = '0';
            countdownElement.setAttribute('data-countdown', '0');
            if (window.alttextaiCountdownInterval) {
                clearInterval(window.alttextaiCountdownInterval);
                window.alttextaiCountdownInterval = null;
            }
            return;
        }

        // Calculate days, hours, minutes
        const days = Math.floor(remaining / 86400);
        const hours = Math.floor((remaining % 86400) / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);

        // Update display
        daysEl.textContent = days.toString();
        hoursEl.textContent = hours.toString();
        minutesEl.textContent = minutes.toString();
        
        // Update the data-countdown attribute for debugging
        countdownElement.setAttribute('data-countdown', remaining);
    }

    // Update immediately
    updateCountdown();

    // Clear any existing interval
    if (window.alttextaiCountdownInterval) {
        clearInterval(window.alttextaiCountdownInterval);
    }

    // Update every second continuously
    window.alttextaiCountdownInterval = setInterval(function() {
        updateCountdown();
    }, 1000); // Update every second
}

/**
 * ALT Library - Search and Filter Functionality
 */
bbaiRunWithJQuery(function($) {
    'use strict';

    $(document).ready(function() {
        // Search functionality (debounced to avoid excessive filtering while typing)
        var searchDebounceTimer;
        $('#bbai-library-search').on('input', function() {
            var $input = $(this);
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(function() {
                var searchTerm = $input.val().toLowerCase();
                filterLibraryRows(searchTerm, getActiveFilter());
            }, 300);
        });

        // Filter buttons
        $('.bbai-filter-btn').on('click', function() {
            const $btn = $(this);
            const filter = $btn.attr('data-filter');
            
            // Toggle active state
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), null);
            } else {
                $('.bbai-filter-btn').removeClass('active');
                $btn.addClass('active');
                filterLibraryRows($('#bbai-library-search').val().toLowerCase(), filter);
            }
        });

        /**
         * Get currently active filter
         */
        function getActiveFilter() {
            const $activeBtn = $('.bbai-filter-btn.active');
            return $activeBtn.length ? $activeBtn.attr('data-filter') : null;
        }

        /**
         * Filter library table rows based on search and filter
         */
        function filterLibraryRows(searchTerm, filter) {
            let visibleCount = 0;
            
            $('.bbai-library-row').each(function() {
                const $row = $(this);
                const status = $row.attr('data-status');
                const attachmentId = $row.attr('data-attachment-id');
                const rowText = $row.text().toLowerCase();
                
                // Check search match
                const matchesSearch = !searchTerm || rowText.includes(searchTerm);
                
                // Check filter match
                let matchesFilter = true;
                if (filter) {
                    if (filter === 'missing') {
                        matchesFilter = status === 'missing';
                    } else if (filter === 'has-alt') {
                        matchesFilter = status === 'optimized' || status === 'regenerated';
                    } else if (filter === 'regenerated') {
                        matchesFilter = status === 'regenerated';
                    } else if (filter === 'recent') {
                        // Show images from last 30 days (would need date data)
                        matchesFilter = true; // Simplified for now
                    }
                }
                
                // Show/hide row
                if (matchesSearch && matchesFilter) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });
            
            // Show/hide empty state
            if (visibleCount === 0 && $('.bbai-library-table tbody tr').length > 1) {
                // Could show a "No results" message here
                window.BBAI_LOG && window.BBAI_LOG.log('No matching rows found');
            }
        }
    });

    /**
     * SEO Character Counter
     * Displays 125-character count for Google Images optimization
     */
    window.bbaiCharCounter = {
        /**
         * Create a character counter element
         * @param {string} text - The alt text to count
         * @param {Object} options - Configuration options
         * @returns {string} HTML string for the counter
         */
        create: function(text, options) {
            options = options || {};
            var charCount = text ? text.length : 0;
            var maxChars = options.maxChars || 125;
            var isOptimal = charCount <= maxChars;
            var isEmpty = charCount === 0;

            var stateClass = isEmpty ? 'bbai-char-counter--empty' :
                           (isOptimal ? 'bbai-char-counter--optimal' : 'bbai-char-counter--warning');

            var icon = isEmpty ? '' :
                      (isOptimal ?
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M3.5 6L5.5 8L8.5 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
                       '<svg class="bbai-char-counter__icon" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M6 3v3.5M6 8.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>');

            var message = isEmpty ? 'No alt text' :
                         (isOptimal ? 'Optimal for SEO' : 'Too long for optimal SEO');

            var tooltip = isEmpty ? 'Add alt text for SEO' :
                         (isOptimal ?
                          'Alt text length is optimal for Google Images (≤125 characters recommended)' :
                          'Consider shortening to 125 characters or less for optimal Google Images SEO');

            return '<span class="bbai-char-counter ' + stateClass + '" title="' + tooltip + '">' +
                   icon +
                   '<span class="bbai-char-counter__number">' + charCount + '</span>' +
                   '<span class="bbai-char-counter__label">/' + maxChars + '</span>' +
                   '</span>';
        },

        /**
         * Initialize character counters for all alt text elements
         */
        init: function() {
            $('.bbai-library-alt-text').each(function() {
                var $altText = $(this);
                var text = $altText.text().trim();

                // Check if counter already exists
                if ($altText.next('.bbai-char-counter').length === 0) {
                    var counterHTML = window.bbaiCharCounter.create(text);
                    $altText.after(counterHTML);
                }
            });
        },

        /**
         * Update counter for a specific element
         * @param {jQuery} $element - The element to update
         * @param {string} newText - The new alt text
         */
        update: function($element, newText) {
            var $counter = $element.next('.bbai-char-counter');
            if ($counter.length) {
                var newCounterHTML = this.create(newText);
                $counter.replaceWith(newCounterHTML);
                // Add update animation
                $element.next('.bbai-char-counter').addClass('bbai-char-counter--updating');
                setTimeout(function() {
                    $element.next('.bbai-char-counter').removeClass('bbai-char-counter--updating');
                }, 300);
            }
        }
    };

    // Initialize character counters when DOM is ready
    $(document).ready(function() {
        // Initial setup
        if (typeof window.bbaiCharCounter !== 'undefined') {
            window.bbaiCharCounter.init();
        }

        // Re-initialize after AJAX updates (e.g., after regenerating alt text)
        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiCharCounter !== 'undefined') {
                window.bbaiCharCounter.init();
            }
        });
    });

});


/**
 * SEO Quality Checker
 * Validates alt text quality for SEO best practices
 */
window.bbaiSEOChecker = {
    /**
     * Check if alt text starts with redundant phrases
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    hasRedundantPrefix: function(text) {
        if (!text) return false;
        var lowerText = text.toLowerCase().trim();
        var redundantPrefixes = [
            'image of',
            'picture of',
            'photo of',
            'photograph of',
            'graphic of',
            'illustration of',
            'image showing',
            'picture showing',
            'photo showing'
        ];
        return redundantPrefixes.some(function(prefix) {
            return lowerText.startsWith(prefix);
        });
    },

    /**
     * Check if alt text is just a filename
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    isJustFilename: function(text) {
        if (!text) return false;
        // Check for common filename patterns
        var filenamePatterns = [
            /^IMG[-_]\d+/i,           // IMG_1234, IMG-5678
            /^DSC[-_]\d+/i,           // DSC_1234, DSC-5678
            /^\d{8}[-_]\d+/i,         // 20230101_123456
            /^screenshot[-_]/i,       // screenshot_2023
            /^image[-_]\d+/i,         // image_001, image-02
            /\.(jpg|jpeg|png|gif|webp)$/i  // ends with file extension
        ];
        return filenamePatterns.some(function(pattern) {
            return pattern.test(text.trim());
        });
    },

    /**
     * Check if alt text has meaningful content
     * @param {string} text - The alt text to check
     * @returns {boolean}
     */
    hasDescriptiveContent: function(text) {
        if (!text) return false;
        // Should have at least 3 words for meaningful description
        var words = text.trim().split(/\s+/);
        return words.length >= 3 && words.some(function(word) {
            return word.length > 3; // Has at least one substantial word
        });
    },

    /**
     * Calculate SEO quality score
     * @param {string} text - The alt text to check
     * @returns {Object} Score and issues
     */
    calculateQuality: function(text) {
        var issues = [];
        var score = 100;

        if (!text || text.trim().length === 0) {
            return {
                score: 0,
                grade: 'F',
                issues: ['No alt text provided'],
                badge: 'missing'
            };
        }

        // Check length (125 chars recommended)
        if (text.length > 125) {
            issues.push('Too long (>' + text.length + ' chars). Aim for ≤125 for optimal Google Images SEO');
            score -= 25;
        }

        // Check for redundant prefixes
        if (this.hasRedundantPrefix(text)) {
            issues.push('Starts with "image of" or similar. Remove redundant prefix');
            score -= 20;
        }

        // Check if it's just a filename
        if (this.isJustFilename(text)) {
            issues.push('Appears to be a filename. Use descriptive text instead');
            score -= 30;
        }

        // Check for descriptive content
        if (!this.hasDescriptiveContent(text)) {
            issues.push('Too short or lacks descriptive keywords');
            score -= 15;
        }

        // Placeholder / non-descriptive check
        var nondescriptiveWords = ['test', 'testing', 'tested', 'tests', 'asdf', 'qwerty', 'placeholder', 'sample', 'example', 'demo', 'foo', 'bar', 'abc', 'xyz', 'temp', 'tmp', 'crap', 'stuff', 'thing', 'things', 'something', 'anything', 'whatever', 'blah', 'meh', 'idk', 'nada', 'random', 'garbage', 'junk', 'dummy', 'fake', 'lorem', 'ipsum'];
        var words = text.trim().toLowerCase().split(/\s+/);
        var badCount = 0;
        for (var i = 0; i < nondescriptiveWords.length; i++) {
            if (words.indexOf(nondescriptiveWords[i]) !== -1) badCount++;
        }
        if (badCount >= 1 && (badCount >= 2 || words.length <= 4)) {
            issues.push('Does not appear to describe the image — edit or regenerate');
            score -= 50;
        }

        // Determine grade
        var grade = 'F';
        var badge = 'needs-work';
        if (score >= 90) {
            grade = 'A';
            badge = 'excellent';
        } else if (score >= 75) {
            grade = 'B';
            badge = 'good';
        } else if (score >= 60) {
            grade = 'C';
            badge = 'fair';
        } else if (score >= 40) {
            grade = 'D';
            badge = 'poor';
        }

        return {
            score: Math.max(0, score),
            grade: grade,
            issues: issues,
            badge: badge
        };
    },

    /**
     * Create SEO quality badge HTML
     * @param {string} text - The alt text to check
     * @returns {string} HTML for quality badge
     */
    createBadge: function(text) {
        var quality = this.calculateQuality(text);

        if (quality.badge === 'missing') {
            return '';
        }

        var badgeClass = 'bbai-seo-badge bbai-seo-badge--' + quality.badge;
        var icon = quality.grade === 'A' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M1 5l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>' :
            quality.grade === 'B' ?
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M5 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>' :
            '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 2l6 6M2 8l6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

        var tooltip = quality.issues.length > 0 ?
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100)\n' + quality.issues.join('\n') :
            'SEO Quality: ' + quality.grade + ' (' + quality.score + '/100) - Excellent!';

        return '<span class="' + badgeClass + '" title="' + tooltip.replace(/"/g, '&quot;') + '">' +
               icon +
               'SEO: ' + quality.grade +
               '</span>';
    },

    /**
     * Initialize SEO quality badges for all alt text elements
     */
    init: function() {
        var self = this;
        // Use jQuery instead of $ for WordPress compatibility (noConflict mode)
        var $ = window.jQuery || window.$;
        if (typeof $ !== 'function') {
            return;
        }
        $('.bbai-library-alt-text').each(function() {
            var $altText = $(this);
            var text = $altText.attr('data-full-text') || $altText.text().trim();

            // Check if badge already exists
            if ($altText.parent().find('.bbai-seo-badge').length === 0) {
                var badgeHTML = self.createBadge(text);
                if (badgeHTML) {
                    var $counter = $altText.next('.bbai-char-counter');
                    if ($counter.length) {
                        $counter.after(badgeHTML);
                    }
                }
            }
        });
    }
};

// Initialize SEO checker when ready
bbaiRunWithJQuery(function($) {
    if (typeof $ !== 'function') {
        return;
    }
    $(document).ready(function() {
        if (typeof window.bbaiSEOChecker !== 'undefined') {
            window.bbaiSEOChecker.init();
        }

        // Re-initialize after AJAX updates
        $(document).on('bbai:alttext:updated', function() {
            if (typeof window.bbaiSEOChecker !== 'undefined') {
                window.bbaiSEOChecker.init();
            }
        });
    });
});

(function() {
    'use strict';

    function showUpgradeModalFallback() {
        if (typeof window.bbaiOpenUpgradeModal === 'function') {
            return window.bbaiOpenUpgradeModal();
        }

        if (typeof window.openPricingModal === 'function') {
            window.openPricingModal('enterprise');
            return true;
        }

        if (typeof window.alttextaiShowModal === 'function') {
            return window.alttextaiShowModal();
        }

        var modal = findUpgradeModalElement();
        if (modal) {
            modal.removeAttribute('style');
            modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; overflow-y: auto !important;';
            modal.classList.add('active');
            modal.classList.add('is-visible');
            modal.setAttribute('aria-hidden', 'false');
            bbaiSetUpgradeModalScrollLock(true);
            var modalContent = modal.querySelector('.bbai-upgrade-modal__content');
            if (modalContent) {
                modalContent.style.cssText = 'opacity: 1 !important; visibility: visible !important; transform: translateY(0) scale(1) !important;';
            }
            return true;
        }

        return false;
    }

    document.addEventListener('click', function(e) {
        if (e.defaultPrevented) {
            return;
        }

        var trigger = e.target.closest('[data-action="show-upgrade-modal"], [data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (!trigger) {
            return;
        }

        if (bbaiIsGenerationActionControl(trigger) && bbaiIsLockedActionControl(trigger)) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
            } else {
                showUpgradeModalFallback();
            }
            return;
        }

        var isBulkAction = trigger.matches('[data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (isBulkAction) {
            var isLockedTrigger = !!(
                trigger.disabled ||
                trigger.getAttribute('aria-disabled') === 'true' ||
                trigger.getAttribute('data-bbai-lock-control') === '1' ||
                trigger.getAttribute('data-bbai-locked-cta') === '1' ||
                trigger.classList.contains('disabled') ||
                trigger.classList.contains('bbai-is-locked') ||
                trigger.classList.contains('bbai-optimization-cta--disabled') ||
                trigger.classList.contains('bbai-optimization-cta--locked') ||
                trigger.classList.contains('bbai-action-btn--disabled') ||
                String(trigger.getAttribute('title') || '').toLowerCase().indexOf('out of credits') !== -1 ||
                String(trigger.getAttribute('data-bbai-tooltip') || '').toLowerCase().indexOf('unlock more generations') !== -1
            );

            if (isLockedTrigger) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                if (typeof window.bbaiOpenLockedUpgradeModal === 'function') {
                    window.bbaiOpenLockedUpgradeModal('upgrade_required', { source: 'dashboard', trigger: trigger });
                } else {
                    showUpgradeModalFallback();
                }
                return;
            }
        }

        if (trigger.matches('[data-action="show-upgrade-modal"]')) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            showUpgradeModalFallback();
            return;
        }

        // Upgrade links (anchors with real href): let browser navigate, don't intercept
        if (trigger.tagName === 'A') {
            var href = trigger.getAttribute && trigger.getAttribute('href');
            if (href && href !== '#' && href.indexOf('javascript:') !== 0) {
                return;
            }
        }

        if (window.bbaiBulkHandlersReady) {
            return;
        }

        var handler = trigger.matches('[data-action="generate-missing"]')
            ? window.bbaiHandleGenerateMissing
            : window.bbaiHandleRegenerateAll;

        if (typeof handler !== 'function') {
            return;
        }

        e.preventDefault();
        handler.call(trigger, e);
    }, true);

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var trigger = e.target.closest('[data-action="show-upgrade-modal"], [data-action="generate-missing"], [data-action="regenerate-all"], [data-action="regenerate-single"], [data-action="regenerate-selected"], [data-bbai-action="generate_missing"], [data-bbai-action="reoptimize_all"], [data-bbai-action="open-upgrade"]');
        if (!trigger || trigger.getAttribute('role') !== 'button') {
            return;
        }
        e.preventDefault();
        trigger.click();
    }, true);
})();

// Keep the dashboard action surfaces in sync with live stats and usage updates.
bbaiRunWithJQuery(function($) {
    'use strict';

    var i18n = window.wp && window.wp.i18n ? window.wp.i18n : null;
    var __ = i18n && typeof i18n.__ === 'function' ? i18n.__ : function(text) { return text; };
    var _n = i18n && typeof i18n._n === 'function' ? i18n._n : function(single, plural, number) { return number === 1 ? single : plural; };
    var sprintf = i18n && typeof i18n.sprintf === 'function' ? i18n.sprintf : function(format) {
        var args = Array.prototype.slice.call(arguments, 1);
        var index = 0;
        return String(format).replace(/%(\d+\$)?s/g, function() {
            var value = args[index];
            index += 1;
            return value;
        });
    };
    var feedbackTimeout = null;

    function parseCount(value) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) || parsed < 0 ? 0 : parsed;
    }

    function formatCount(value) {
        return parseCount(value).toLocaleString();
    }

    function formatPercentageLabel(value) {
        var numericValue = parseFloat(value);

        if (isNaN(numericValue) || numericValue <= 0) {
            return '0';
        }

        if (numericValue >= 100) {
            return '100';
        }

        if (numericValue < 0.01) {
            return '<0.01';
        }

        if (numericValue < 0.1) {
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        if (numericValue < 10) {
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1
            });
        }

        return numericValue.toLocaleString(undefined, {
            maximumFractionDigits: 0
        });
    }

    function getGrowthPlanComparison(data) {
        var growthCapacity = data && data.isPremium ? Math.max(1000, parseCount(data.creditsTotal)) : 1000;
        var usagePercent = Math.min(100, Math.max(0, (parseCount(data && data.creditsUsed) / Math.max(1, growthCapacity)) * 100));
        var displayPercent = formatPercentageLabel(usagePercent);

        return {
            line: data && data.isPremium
                ? sprintf(
                    __('You are using %s%% of Growth capacity this month.', 'beepbeep-ai-alt-text-generator'),
                    displayPercent
                )
                : sprintf(
                    __('On Growth, this usage would be %s%% of monthly capacity.', 'beepbeep-ai-alt-text-generator'),
                    displayPercent
                ),
            display: displayPercent,
            percent: usagePercent,
            indicatorPercent: usagePercent > 0 ? Math.min(99.5, Math.max(0.5, usagePercent)) : 0
        };
    }

    function getPlanLabel(data) {
        return data && data.isPremium
            ? __('Growth plan', 'beepbeep-ai-alt-text-generator')
            : __('Free plan', 'beepbeep-ai-alt-text-generator');
    }

    function getRemainingPlanLine(data) {
        var remaining = Math.max(0, parseCount(data && data.creditsRemaining));
        return sprintf(
            _n('%s remaining', '%s remaining', remaining, 'beepbeep-ai-alt-text-generator'),
            formatCount(remaining)
        );
    }

    function getPlanResetLine(data) {
        var daysUntilReset = parseCount(data && data.daysUntilReset);

        if (daysUntilReset >= 0) {
            return buildUsageResetCopy(daysUntilReset);
        }

        if (data && data.creditsResetLine) {
            return String(data.creditsResetLine).replace(/^Credits\s+/i, '');
        }

        return __('Resets monthly', 'beepbeep-ai-alt-text-generator');
    }

    function getCompactPlanResetLine(data) {
        var daysUntilReset = parseCount(data && data.daysUntilReset);

        if (daysUntilReset >= 0) {
            return sprintf(
                _n('%s day', '%s days', daysUntilReset, 'beepbeep-ai-alt-text-generator'),
                formatCount(daysUntilReset)
            );
        }

        if (data && data.creditsResetLine) {
            return String(data.creditsResetLine).replace(/^Credits\s+/i, '').replace(/^Resets\s+/i, '');
        }

        return __('Monthly', 'beepbeep-ai-alt-text-generator');
    }

    function getUpgradeValueMinutesCopy(data) {
        var minutesSaved = Math.max(0, Math.round(parseCount(data && data.creditsUsed) * 2.5));

        return sprintf(
            _n('~%s minute', '~%s minutes', minutesSaved, 'beepbeep-ai-alt-text-generator'),
            formatCount(minutesSaved)
        );
    }

    function getCreditAlertCopy(data) {
        var remaining = Math.max(0, parseCount(data && data.creditsRemaining));
        var resetLine = data && data.creditsResetLine ? String(data.creditsResetLine) : '';

        if (!data || data.isPremium) {
            return null;
        }

        if (remaining <= 0) {
            return {
                modifier: 'danger',
                title: __('No credits left', 'beepbeep-ai-alt-text-generator'),
                message: __('AI generation is paused until your credits reset. Upgrade to keep optimizing images and automate future uploads.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                meta: resetLine
            };
        }

        if (remaining <= 5) {
            return {
                modifier: 'warning',
                title: __('You are running low on credits', 'beepbeep-ai-alt-text-generator'),
                message: sprintf(
                    _n(
                        'You can optimize %s more image this month. Upgrade before new uploads start building up.',
                        'You can optimize %s more images this month. Upgrade before new uploads start building up.',
                        remaining,
                        'beepbeep-ai-alt-text-generator'
                    ),
                    formatCount(remaining)
                ),
                ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
                meta: resetLine
            };
        }

        return null;
    }

    function getDashboardRoot() {
        return document.querySelector('[data-bbai-dashboard-root="1"]');
    }

    function buildUsageResetCopy(daysLeft) {
        var safeDaysLeft = Math.max(0, parseCount(daysLeft));

        return sprintf(
            _n('Resets in %s day', 'Resets in %s days', safeDaysLeft, 'beepbeep-ai-alt-text-generator'),
            formatCount(safeDaysLeft)
        );
    }

    function getUsageResetCopy(usageLine) {
        var hero;
        var daysLeftAttr;

        if (!usageLine) {
            return '';
        }

        hero = usageLine.closest('[data-bbai-dashboard-hero="1"]');
        if (hero) {
            daysLeftAttr = hero.getAttribute('data-bbai-banner-days-left');
            if (daysLeftAttr !== null && daysLeftAttr !== '') {
                var inlineCopy = buildUsageResetCopy(daysLeftAttr);
                usageLine.setAttribute('data-bbai-reset-copy', inlineCopy);
                return inlineCopy;
            }
        }

        var existing = usageLine.getAttribute('data-bbai-reset-copy');
        if (existing) {
            return existing;
        }

        var text = usageLine.textContent || '';
        var parts = text.split('•');
        var resetCopy = parts.length > 1 ? parts.slice(1).join('•').trim() : '';
        usageLine.setAttribute('data-bbai-reset-copy', resetCopy);
        return resetCopy;
    }

    function getDashboardData() {
        var root = getDashboardRoot();
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        if (!root) {
            return null;
        }

        var missing = parseCount(root.getAttribute('data-bbai-missing-count'));
        var weak = parseCount(root.getAttribute('data-bbai-weak-count'));
        var optimized = parseCount(root.getAttribute('data-bbai-optimized-count'));
        var total = parseCount(root.getAttribute('data-bbai-total-count'));
        var hasScanResultsAttr = root.getAttribute('data-bbai-has-scan-results');

        return {
            root: root,
            missing: missing,
            weak: weak,
            optimized: optimized,
            total: total,
            generated: parseCount(root.getAttribute('data-bbai-generated-count')),
            creditsUsed: parseCount(root.getAttribute('data-bbai-credits-used')),
            creditsTotal: Math.max(1, parseCount(root.getAttribute('data-bbai-credits-total'))),
            creditsRemaining: parseCount(root.getAttribute('data-bbai-credits-remaining')),
            creditsResetLine: root.getAttribute('data-bbai-credits-reset-line') || '',
            isPremium: root.getAttribute('data-bbai-is-premium') === '1',
            hasScanResults: hasScanResultsAttr === null
                ? (total > 0 || missing > 0 || weak > 0 || optimized > 0)
                : hasScanResultsAttr === '1',
            daysUntilReset: hero ? parseCount(hero.getAttribute('data-bbai-banner-days-left')) : 0,
            lastScanTs: parseCount(root.getAttribute('data-bbai-last-scan-ts')),
            libraryUrl: root.getAttribute('data-bbai-library-url') || '',
            missingLibraryUrl: root.getAttribute('data-bbai-missing-library-url') || '',
            needsReviewLibraryUrl: root.getAttribute('data-bbai-needs-review-library-url') || '',
            settingsUrl: root.getAttribute('data-bbai-settings-url') || '',
            usageUrl: root.getAttribute('data-bbai-usage-url') || '',
            guideUrl: root.getAttribute('data-bbai-guide-url') || ''
        };
    }

    function syncStatsToRoot(stats) {
        var root = getDashboardRoot();
        if (!root || !stats || typeof stats !== 'object') {
            return;
        }

        var currentMissing = parseCount(root.getAttribute('data-bbai-missing-count'));
        var currentWeak = parseCount(root.getAttribute('data-bbai-weak-count'));
        var currentOptimized = parseCount(root.getAttribute('data-bbai-optimized-count'));
        var currentTotal = parseCount(root.getAttribute('data-bbai-total-count'));
        var missing = stats.images_missing_alt !== undefined
            ? parseCount(stats.images_missing_alt)
            : (stats.missing !== undefined ? parseCount(stats.missing) : currentMissing);
        var weak = stats.needs_review_count !== undefined
            ? parseCount(stats.needs_review_count)
            : currentWeak;
        var optimized = currentOptimized;

        if (stats.optimized_count !== undefined) {
            optimized = parseCount(stats.optimized_count);
        } else if (stats.needs_review_count !== undefined && stats.images_with_alt !== undefined) {
            optimized = Math.max(0, parseCount(stats.images_with_alt) - parseCount(stats.needs_review_count));
        } else if (stats.needs_review_count !== undefined && stats.with_alt !== undefined) {
            optimized = Math.max(0, parseCount(stats.with_alt) - parseCount(stats.needs_review_count));
        }

        var total = stats.total_images !== undefined
            ? parseCount(stats.total_images)
            : (stats.total !== undefined ? parseCount(stats.total) : currentTotal);
        var currentGenerated = parseCount(root.getAttribute('data-bbai-generated-count'));
        var generated = stats.generated !== undefined ? parseCount(stats.generated) : currentGenerated;
        var currentLastScanTs = parseCount(root.getAttribute('data-bbai-last-scan-ts'));
        var lastScanTs = currentLastScanTs;
        var hasScanResults = total > 0 || missing > 0 || weak > 0 || optimized > 0;

        if (stats.scanned_at !== undefined) {
            lastScanTs = parseCount(stats.scanned_at);
        } else if (stats.scannedAt !== undefined) {
            lastScanTs = parseCount(stats.scannedAt);
        }

        root.setAttribute('data-bbai-missing-count', String(missing));
        root.setAttribute('data-bbai-weak-count', String(weak));
        root.setAttribute('data-bbai-optimized-count', String(optimized));
        root.setAttribute('data-bbai-total-count', String(total));
        root.setAttribute('data-bbai-generated-count', String(generated));
        root.setAttribute('data-bbai-has-scan-results', hasScanResults ? '1' : '0');
        root.setAttribute('data-bbai-last-scan-ts', String(lastScanTs));
    }

    function syncUsageToRoot(usage) {
        var root = getDashboardRoot();
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        var daysUntilReset = parseInt(usage && usage.days_until_reset, 10);

        if (!root || !usage || typeof usage !== 'object') {
            return;
        }

        var used = parseCount(usage.used);
        var limit = Math.max(1, parseCount(usage.limit || 50));
        var remaining = usage.remaining !== undefined && usage.remaining !== null
            ? parseCount(usage.remaining)
            : Math.max(0, limit - used);

        root.setAttribute('data-bbai-credits-used', String(used));
        root.setAttribute('data-bbai-credits-total', String(limit));
        root.setAttribute('data-bbai-credits-remaining', String(remaining));
        root.setAttribute(
            'data-bbai-credits-reset-line',
            buildCreditsResetLine(usage, root.getAttribute('data-bbai-credits-reset-line') || '')
        );

        if (hero && !isNaN(daysUntilReset)) {
            hero.setAttribute('data-bbai-banner-days-left', String(Math.max(0, daysUntilReset)));
        }
    }

    function hasCreditsAvailable(data) {
        return !!(data && (data.isPremium || data.creditsRemaining > 0));
    }

    function buildCreditsResetLine(usage, fallback) {
        var existing = String(fallback || '');
        var resetTsRaw;
        var resetTs;
        var formatted = '';

        if (!usage || typeof usage !== 'object') {
            return existing;
        }

        resetTsRaw = usage.reset_timestamp || usage.resetTimestamp || usage.reset_ts || 0;
        resetTs = parseInt(resetTsRaw, 10);

        if (!isNaN(resetTs) && resetTs > 0 && resetTs < 1000000000000) {
            resetTs = resetTs * 1000;
        }

        if ((!resetTs || isNaN(resetTs)) && usage.resetDate) {
            resetTs = Date.parse(String(usage.resetDate));
        }
        if ((!resetTs || isNaN(resetTs)) && usage.reset_date) {
            resetTs = Date.parse(String(usage.reset_date));
        }

        if (resetTs && !isNaN(resetTs)) {
            try {
                formatted = new Date(resetTs).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } catch (error) {
                formatted = '';
            }
        }

        if (!formatted) {
            formatted = usage.resetDate || usage.reset_date || '';
        }

        return formatted
            ? sprintf(__('Credits reset %s', 'beepbeep-ai-alt-text-generator'), formatted)
            : existing;
    }

    function formatLastScanCopy(timestamp) {
        var scanTs = parseInt(timestamp, 10);
        var diffMs;
        var minutes;
        var hours;
        var days;

        if (isNaN(scanTs) || scanTs <= 0) {
            return '';
        }

        if (scanTs < 1000000000000) {
            scanTs = scanTs * 1000;
        }

        diffMs = Math.max(0, Date.now() - scanTs);
        minutes = Math.floor(diffMs / 60000);
        hours = Math.floor(diffMs / 3600000);
        days = Math.floor(diffMs / 86400000);

        if (minutes < 1) {
            return __('Last scan just now', 'beepbeep-ai-alt-text-generator');
        }
        if (minutes < 60) {
            return sprintf(
                _n('Last scan %d minute ago', 'Last scan %d minutes ago', minutes, 'beepbeep-ai-alt-text-generator'),
                minutes
            );
        }
        if (hours < 24) {
            return sprintf(
                _n('Last scan %d hour ago', 'Last scan %d hours ago', hours, 'beepbeep-ai-alt-text-generator'),
                hours
            );
        }
        if (days < 7) {
            return sprintf(
                _n('Last scan %d day ago', 'Last scan %d days ago', days, 'beepbeep-ai-alt-text-generator'),
                days
            );
        }

        try {
            return sprintf(
                __('Last scan %s', 'beepbeep-ai-alt-text-generator'),
                new Date(scanTs).toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                })
            );
        } catch (error) {
            return '';
        }
    }

    function hasScanResults(data) {
        if (!data) {
            return false;
        }

        if (typeof data.hasScanResults === 'boolean') {
            return data.hasScanResults;
        }

        return !!(data.total > 0 || data.missing > 0 || data.weak > 0 || data.optimized > 0);
    }

    function getDashboardHeroIconMarkup(tone) {
        if (tone === 'setup') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"></circle><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        if (tone === 'attention') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M12 3L21 19H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><circle cx="12" cy="17" r="1" fill="currentColor"></circle></svg>';
        }

        if (tone === 'paused') {
            return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"></circle><path d="M10 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path><path d="M14 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    }

    function getDashboardHeroStateModel(data) {
        var missing = Math.max(0, parseCount(data && data.missing));
        var weak = Math.max(0, parseCount(data && data.weak));
        var total = Math.max(0, parseCount(data && data.total));
        var creditsRemaining = Math.max(0, parseCount(data && data.creditsRemaining));
        var usagePercent = Math.min(100, Math.max(0, Math.round((parseCount(data && data.creditsUsed) / Math.max(1, parseCount(data && data.creditsTotal))) * 100)));
        var isHealthy = missing === 0 && weak === 0 && total > 0;
        var isLowCredits = creditsRemaining > 0 && creditsRemaining < 10;
        var isOutOfCredits = creditsRemaining === 0;
        var isFirstRun = !hasScanResults(data) || total === 0;
        var libraryUrl = data && data.libraryUrl ? String(data.libraryUrl) : '#';
        var usageUrl = data && data.usageUrl ? String(data.usageUrl) : '#';
        var guideUrl = data && data.guideUrl ? String(data.guideUrl) : '#';
        var settingsUrl = data && data.settingsUrl ? String(data.settingsUrl) : '#';

        function createAction(label, helper, options) {
            return $.extend({
                label: label || '',
                helper: helper || '',
                href: '#',
                removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source', 'aria-describedby']
            }, options || {});
        }

        var model = {
            state: 'healthy-free',
            tone: 'healthy',
            headline: '',
            subtext: '',
            nextStep: '',
            workflowHint: '',
            note: '',
            usagePercent: usagePercent,
            primaryAction: createAction('', '', {}),
            secondaryAction: null,
            tertiaryAction: null
        };

        if (isFirstRun) {
            model.state = 'first-run';
            model.tone = 'setup';
            model.headline = __('Get started with your media library', 'beepbeep-ai-alt-text-generator');
            model.subtext = __('Scan your library to find images missing ALT text and generate descriptions faster.', 'beepbeep-ai-alt-text-generator');
            model.nextStep = __('Start by scanning your media library.', 'beepbeep-ai-alt-text-generator');
            model.primaryAction = createAction(
                __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    bbaiAction: 'scan-opportunity'
                }
            );
            model.secondaryAction = createAction(
                __('Learn how it works', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: guideUrl
                }
            );
            return model;
        }

        if (isOutOfCredits) {
            model.state = 'out-of-credits';
            model.tone = 'paused';
            model.headline = __("You've used all credits this cycle", 'beepbeep-ai-alt-text-generator');
            model.subtext = __('ALT generation is paused until your allowance resets or you upgrade.', 'beepbeep-ai-alt-text-generator');
            model.nextStep = __('Upgrade to continue optimizing new images.', 'beepbeep-ai-alt-text-generator');
            model.primaryAction = createAction(
                __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    action: 'show-upgrade-modal'
                }
            );
            model.secondaryAction = createAction(
                __('Review usage', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: usageUrl
                }
            );
            model.tertiaryAction = createAction(
                __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: libraryUrl
                }
            );
            return model;
        }

        if (isLowCredits) {
            model.state = 'low-credits';
            model.tone = 'attention';
            model.headline = __('You’re running low on credits', 'beepbeep-ai-alt-text-generator');
            model.subtext = sprintf(
                _n(
                    'You have %s optimization left this cycle.',
                    'You have %s optimizations left this cycle.',
                    creditsRemaining,
                    'beepbeep-ai-alt-text-generator'
                ),
                formatCount(creditsRemaining)
            );

            if (data && data.isPremium) {
                model.nextStep = __('Review your usage and plan ahead.', 'beepbeep-ai-alt-text-generator');
                model.primaryAction = createAction(
                    __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        href: usageUrl
                    }
                );
                model.secondaryAction = createAction(
                    __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        href: libraryUrl
                    }
                );
            } else {
                model.nextStep = __('Upgrade to keep optimizing without interruption.', 'beepbeep-ai-alt-text-generator');
                model.primaryAction = createAction(
                    __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        action: 'show-upgrade-modal'
                    }
                );
                model.secondaryAction = createAction(
                    __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        href: usageUrl
                    }
                );
            }
            return model;
        }

        if (!isHealthy) {
            model.state = 'incomplete';
            model.tone = 'attention';
            model.headline = __('Your library needs attention', 'beepbeep-ai-alt-text-generator');
            model.subtext = missing > 0
                ? __('Some images are still missing ALT text.', 'beepbeep-ai-alt-text-generator')
                : __('Some descriptions still need review.', 'beepbeep-ai-alt-text-generator');
            model.nextStep = missing > 0
                ? __('Fix the remaining images to reach full coverage.', 'beepbeep-ai-alt-text-generator')
                : __('Review the remaining descriptions to reach full coverage.', 'beepbeep-ai-alt-text-generator');
            model.secondaryAction = createAction(
                __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: missing > 0
                        ? (data && data.missingLibraryUrl ? data.missingLibraryUrl : libraryUrl)
                        : (data && data.needsReviewLibraryUrl ? data.needsReviewLibraryUrl : libraryUrl)
                }
            );
            if (missing > 0) {
                model.primaryAction = createAction(
                    __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        action: 'generate-missing',
                        bbaiAction: 'generate_missing'
                    }
                );
            } else {
                model.primaryAction = createAction(
                    __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    '',
                    {
                        action: 'show-generate-alt-modal'
                    }
                );
            }
            return model;
        }

        if (data && data.isPremium) {
            model.state = 'healthy-pro';
            model.tone = 'healthy';
            model.headline = __('Your library is optimized', 'beepbeep-ai-alt-text-generator');
            model.subtext = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
            model.nextStep = __('Auto-optimization is ready to cover future uploads.', 'beepbeep-ai-alt-text-generator');
            model.note = __('Review new uploads or adjust automation in Settings whenever you need to.', 'beepbeep-ai-alt-text-generator');
            model.primaryAction = createAction(
                __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: libraryUrl
                }
            );
            model.secondaryAction = createAction(
                __('Review usage', 'beepbeep-ai-alt-text-generator'),
                '',
                {
                    href: usageUrl
                }
            );
            return model;
        }

        model.state = 'healthy-free';
        model.tone = 'healthy';
        model.headline = __('Your library is optimized', 'beepbeep-ai-alt-text-generator');
        model.subtext = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
        model.nextStep = __('Enable auto-optimization to cover future uploads automatically.', 'beepbeep-ai-alt-text-generator');
        model.note = __('Auto-optimization is available on Growth.', 'beepbeep-ai-alt-text-generator');
        model.primaryAction = createAction(
            __('Enable auto-optimization', 'beepbeep-ai-alt-text-generator'),
            '',
            {
                action: 'show-upgrade-modal'
            }
        );
        model.secondaryAction = createAction(
            __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
            '',
            {
                href: libraryUrl
            }
        );

        return model;
    }

    function formatMonthlyProgressCopy(count) {
        var optimizedThisMonth = parseCount(count);
        if (optimizedThisMonth <= 0) {
            return __('No ALT descriptions generated by AI yet this month', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            _n('%s ALT description generated by AI this month', '%s ALT descriptions generated by AI this month', optimizedThisMonth, 'beepbeep-ai-alt-text-generator'),
            formatCount(optimizedThisMonth)
        );
    }

    function getStatusCoverageData(data) {
        var optimized = parseCount(data && data.optimized);
        var weak = parseCount(data && data.weak);
        var missing = parseCount(data && data.missing);
        var total = Math.max(0, optimized + weak + missing);
        var coverage = total > 0 ? Math.round((optimized / total) * 100) : 0;

        return {
            optimized: optimized,
            weak: weak,
            missing: missing,
            total: total,
            coverage: coverage
        };
    }

    function getOptimizedRatioCopy(statusCoverage) {
        if (!statusCoverage || statusCoverage.total <= 0) {
            return __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
        }

        return sprintf(
            _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', statusCoverage.total, 'beepbeep-ai-alt-text-generator'),
            formatCount(statusCoverage.optimized),
            formatCount(statusCoverage.total)
        );
    }

    function getStatusRingSegments(statusCoverage, circumference) {
        var segmentGap = 0;
        var segmentOffset = 0;
        var nonZeroSegments = 0;
        var linecap = 'butt';
        var segments = {};
        var animationOrder = 0;
        var segmentConfig = [
            {
                key: 'optimized',
                value: statusCoverage.optimized,
                stroke: '#16A34A'
            },
            {
                key: 'weak',
                value: statusCoverage.weak,
                stroke: '#F97316'
            },
            {
                key: 'missing',
                value: statusCoverage.missing,
                stroke: '#EF4444'
            }
        ];

        segmentConfig.forEach(function(segment) {
            if (segment.value > 0) {
                nonZeroSegments += 1;
            }
        });

        if (nonZeroSegments <= 1) {
            linecap = 'round';
        }

        if (nonZeroSegments > 1) {
            linecap = 'butt';
        }

        segmentConfig.forEach(function(segment) {
            var segmentLength = statusCoverage.total > 0
                ? circumference * (segment.value / statusCoverage.total)
                : 0;
            var gap = nonZeroSegments > 1 ? Math.min(segmentGap, segmentLength * 0.35) : 0;
            var visibleLength = Math.max(0, segmentLength - gap);

            segments[segment.key] = {
                stroke: segment.stroke,
                dasharray: visibleLength.toFixed(3) + ' ' + Math.max(0, circumference - visibleLength).toFixed(3),
                dashoffset: (-segmentOffset).toFixed(3),
                isVisible: visibleLength > 0.01,
                linecap: linecap,
                animationOrder: visibleLength > 0.01 ? animationOrder++ : animationOrder
            };

            segmentOffset += segmentLength;
        });

        return segments;
    }

    function getPrimaryAction(data) {
        if (!data) {
            return 'scan';
        }
        if (!hasCreditsAvailable(data)) {
            return 'upgrade';
        }
        if (data.missing > 0) {
            return 'generate_missing';
        }
        if (data.weak > 0) {
            return 'review_weak';
        }
        if (hasScanResults(data)) {
            return 'review_library';
        }
        return 'scan';
    }

    function formatCreditHelper(required, remaining) {
        var requiredCount = Math.max(0, parseCount(required));
        var remainingCount = Math.max(0, parseCount(remaining));

        if (remainingCount >= requiredCount) {
            return sprintf(
                _n('Uses %s credit', 'Uses %s credits', requiredCount, 'beepbeep-ai-alt-text-generator'),
                formatCount(requiredCount)
            );
        }

        return sprintf(
            _n('%s credit available now', '%s credits available now', remainingCount, 'beepbeep-ai-alt-text-generator'),
            formatCount(remainingCount)
        );
    }

    function escapeDashboardHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setInteractiveControl(node, config) {
        if (!node || !config) {
            return;
        }

        var href = config.href || '#';
        if (!config.preserveContent) {
            node.textContent = config.label || '';
        }
        node.setAttribute('href', href);

        if (config.ariaLabel || config.label) {
            node.setAttribute('aria-label', config.ariaLabel || config.label);
        } else {
            node.removeAttribute('aria-label');
        }

        if (Array.isArray(config.removeAttributes)) {
            config.removeAttributes.forEach(function(attributeName) {
                if (attributeName) {
                    node.removeAttribute(attributeName);
                }
            });
        }

        if (config.action) {
            node.setAttribute('data-action', config.action);
        } else {
            node.removeAttribute('data-action');
        }

        if (config.bbaiAction) {
            node.setAttribute('data-bbai-action', config.bbaiAction);
        } else {
            node.removeAttribute('data-bbai-action');
        }

        ['data-bbai-regenerate-scope', 'data-bbai-generation-source'].forEach(function(attributeName) {
            if (!config.attributes || !Object.prototype.hasOwnProperty.call(config.attributes, attributeName)) {
                node.removeAttribute(attributeName);
            }
        });

        if (config.attributes) {
            Object.keys(config.attributes).forEach(function(attributeName) {
                if (!attributeName) {
                    return;
                }

                var attributeValue = config.attributes[attributeName];
                if (attributeValue === null || attributeValue === undefined || attributeValue === '') {
                    node.removeAttribute(attributeName);
                    return;
                }

                node.setAttribute(attributeName, attributeValue);
            });
        }

        if (config.disabled) {
            node.classList.add('is-disabled');
            node.setAttribute('aria-disabled', 'true');
            node.setAttribute('href', '#');
        } else {
            node.classList.remove('is-disabled');
            node.removeAttribute('aria-disabled');
        }
    }

    function syncHeroLinkRow(hero) {
        if (!hero) {
            return;
        }

        var tertiaryItem = hero.querySelector('[data-bbai-hero-tertiary-item]');
        var tertiaryLink = hero.querySelector('[data-bbai-hero-secondary-link]');
        if (tertiaryItem) {
            tertiaryItem.hidden = !(tertiaryLink && !tertiaryLink.hidden);
        }
    }

    function renderHero(data) {
        var hero = document.querySelector('[data-bbai-dashboard-hero="1"]');
        if (!hero || !data) {
            return;
        }

        var model = getDashboardHeroStateModel(data);
        if (!model) {
            return;
        }

        var iconNode = hero.querySelector('[data-bbai-hero-icon]');
        var headline = hero.querySelector('[data-bbai-hero-headline]');
        var subtext = hero.querySelector('[data-bbai-hero-subtext]');
        var nextStepNode = hero.querySelector('[data-bbai-hero-next-step]');
        var workflowRow = hero.querySelector('[data-bbai-hero-workflow-row]');
        var workflowNode = hero.querySelector('[data-bbai-hero-workflow]');
        var noteNode = hero.querySelector('[data-bbai-hero-note]');
        var primaryItem = hero.querySelector('[data-bbai-hero-primary-item]');
        var secondaryItem = hero.querySelector('[data-bbai-hero-secondary-item]');
        var tertiaryItem = hero.querySelector('[data-bbai-hero-tertiary-item]');
        var generatorCta = hero.querySelector('[data-bbai-hero-generator-cta]');
        var libraryCta = hero.querySelector('[data-bbai-hero-library-cta]');
        var secondaryLink = hero.querySelector('[data-bbai-hero-secondary-link]');
        var primaryHelperNode = hero.querySelector('[data-bbai-hero-primary-helper]');
        var secondaryHelperNode = hero.querySelector('[data-bbai-hero-secondary-helper]');
        var tertiaryHelperNode = hero.querySelector('[data-bbai-hero-tertiary-helper]');
        var usageLine = hero.querySelector('[data-bbai-banner-usage-line]');
        var progressFill = hero.querySelector('[data-bbai-banner-progress]');

        function renderAction(itemNode, controlNode, helperNode, helperId, actionConfig) {
            var config;

            if (!itemNode || !controlNode) {
                return;
            }

            if (!actionConfig || !actionConfig.label) {
                itemNode.hidden = true;
                controlNode.hidden = true;
                if (helperNode) {
                    helperNode.hidden = true;
                    helperNode.textContent = '';
                }
                return;
            }

            config = $.extend(true, {}, actionConfig);
            controlNode.hidden = false;

            if (helperNode) {
                helperNode.textContent = actionConfig.helper || '';
                helperNode.hidden = !(actionConfig.helper || '');
            }

            if (actionConfig.helper && helperId) {
                config.attributes = $.extend({}, config.attributes || {}, {
                    'aria-describedby': helperId
                });
            }

            setInteractiveControl(controlNode, config);
            itemNode.hidden = false;
        }

        hero.setAttribute('data-state', model.state || 'healthy-free');
        hero.setAttribute('data-tone', model.tone || 'healthy');
        hero.setAttribute('data-bbai-banner-used', String(Math.max(0, parseCount(data.creditsUsed))));
        hero.setAttribute('data-bbai-banner-limit', String(Math.max(1, parseCount(data.creditsTotal))));
        hero.setAttribute('data-bbai-banner-remaining', String(Math.max(0, parseCount(data.creditsRemaining))));
        hero.setAttribute('data-bbai-banner-missing-count', String(Math.max(0, parseCount(data.missing))));
        hero.setAttribute('data-bbai-banner-weak-count', String(Math.max(0, parseCount(data.weak))));

        if (iconNode) {
            iconNode.innerHTML = getDashboardHeroIconMarkup(model.tone || 'healthy');
        }
        if (headline) {
            headline.textContent = model.headline || '';
        }
        if (subtext) {
            subtext.textContent = model.subtext || '';
        }
        if (nextStepNode) {
            nextStepNode.textContent = model.nextStep || '';
            nextStepNode.hidden = !model.nextStep;
        }
        if (workflowRow) {
            workflowRow.hidden = !model.workflowHint;
        }
        if (workflowNode) {
            workflowNode.textContent = model.workflowHint || '';
        }
        if (noteNode) {
            noteNode.textContent = model.note || '';
            noteNode.hidden = !model.note;
        }

        renderAction(primaryItem, generatorCta, primaryHelperNode, 'bbai-dashboard-hero-primary-helper', model.primaryAction);
        renderAction(secondaryItem, libraryCta, secondaryHelperNode, 'bbai-dashboard-hero-secondary-helper', model.secondaryAction);
        renderAction(tertiaryItem, secondaryLink, tertiaryHelperNode, 'bbai-dashboard-hero-tertiary-helper', model.tertiaryAction);

        syncHeroLinkRow(hero);

        if (usageLine) {
            var resetCopy = getPlanResetLine(data) || getUsageResetCopy(usageLine);
            usageLine.setAttribute('data-bbai-reset-copy', resetCopy || '');
            usageLine.innerHTML =
                '<span class="bbai-dashboard-hero__usage-pill">' + escapeDashboardHtml(getPlanLabel(data)) + '</span>' +
                '<span class="bbai-dashboard-hero__usage-primary">' + escapeDashboardHtml(getRemainingPlanLine(data)) + '</span>' +
                (resetCopy ? '<span class="bbai-dashboard-hero__usage-secondary">' + escapeDashboardHtml(resetCopy) + '</span>' : '');
        }

        if (progressFill) {
            var usagePercent = model.usagePercent;
            progressFill.setAttribute('data-bbai-banner-progress-target', String(Math.round(usagePercent)));
            animateLinearProgress(progressFill, usagePercent, 400, 40);
            if (progressFill.parentNode && typeof progressFill.parentNode.setAttribute === 'function') {
                progressFill.parentNode.setAttribute('aria-valuenow', String(Math.round(usagePercent)));
            }
        }
    }

    function renderQuickActions(data) {
        var row = document.querySelector('[data-bbai-quick-actions]');
        if (!row || !data) {
            return;
        }

        var generateButton = row.querySelector('[data-bbai-quick-action="generate-missing"]');
        var reviewButton = row.querySelector('[data-bbai-quick-action="review-weak"]');
        var bulkButton = row.querySelector('[data-bbai-quick-action="bulk-optimize"]');
        var generatePrimary = !hasScanResults(data) || data.missing > 0;
        var reviewPrimary = !generatePrimary && data.weak > 0;
        var bulkPrimary = !generatePrimary && !reviewPrimary;

        function setQuickButton(node, config) {
            if (!node) {
                return;
            }

            var variant = config.primary ? 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--primary' : 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--secondary';
            node.className = variant + (config.disabled ? ' is-disabled' : '') + (config.complete ? ' is-complete' : '');
            setInteractiveControl(node, config);

            var labelNode = node.querySelector('[data-bbai-quick-action-label]');
            var helperNode = node.querySelector('[data-bbai-quick-action-helper]');

            if (labelNode) {
                labelNode.textContent = config.label || '';
            }

            if (helperNode) {
                helperNode.textContent = config.helper || '';
                helperNode.hidden = !config.helper;
            }
        }

        if (!hasScanResults(data)) {
            setQuickButton(generateButton, {
                label: __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('Find images missing ALT text before generating descriptions.', 'beepbeep-ai-alt-text-generator'),
                bbaiAction: 'scan-opportunity',
                primary: generatePrimary,
                preserveContent: true
            });
        } else if (data.missing > 0 && !hasCreditsAvailable(data)) {
            setQuickButton(generateButton, {
                label: __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator'),
                helper: __('Buy additional credits', 'beepbeep-ai-alt-text-generator'),
                action: 'show-upgrade-modal',
                primary: generatePrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action']
            });
        } else if (data.missing > 0) {
            var generateLabel = sprintf(
                _n('Generate ALT for %s image', 'Generate ALT for %s images', data.missing, 'beepbeep-ai-alt-text-generator'),
                formatCount(data.missing)
            );
            var generateHelper = formatCreditHelper(
                data.missing,
                data.creditsRemaining > 0 ? data.creditsRemaining : data.missing
            );

            setQuickButton(generateButton, {
                label: generateLabel,
                helper: generateHelper,
                action: 'generate-missing',
                bbaiAction: 'generate_missing',
                primary: generatePrimary,
                preserveContent: true,
                ariaLabel: generateLabel + '. ' + generateHelper
            });
        } else {
            var generateDoneHelper = __('No images are currently missing ALT text.', 'beepbeep-ai-alt-text-generator');
            setQuickButton(generateButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: generateDoneHelper,
                href: data.libraryUrl,
                primary: generatePrimary,
                complete: true,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }

        if (!hasScanResults(data)) {
            setQuickButton(reviewButton, {
                label: __('Improve ALT descriptions', 'beepbeep-ai-alt-text-generator'),
                helper: __('Scan first to find weaker ALT descriptions that need review.', 'beepbeep-ai-alt-text-generator'),
                primary: reviewPrimary,
                disabled: true,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.weak > 0 && !hasCreditsAvailable(data)) {
            setQuickButton(reviewButton, {
                label: __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator'),
                helper: __('Buy additional credits', 'beepbeep-ai-alt-text-generator'),
                action: 'show-upgrade-modal',
                primary: reviewPrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.weak > 0) {
            var reviewLabel = sprintf(
                _n('Improve %s ALT description', 'Improve %s ALT descriptions', data.weak, 'beepbeep-ai-alt-text-generator'),
                formatCount(data.weak)
            );
            var reviewHelper = formatCreditHelper(
                data.weak,
                data.creditsRemaining > 0 ? data.creditsRemaining : data.weak
            );

            setQuickButton(reviewButton, {
                label: reviewLabel,
                helper: reviewHelper,
                action: 'regenerate-all',
                attributes: {
                    'data-bbai-regenerate-scope': 'needs-review',
                    'data-bbai-generation-source': 'regenerate-weak'
                },
                removeAttributes: ['data-bbai-action'],
                primary: reviewPrimary,
                preserveContent: true,
                ariaLabel: reviewLabel + '. ' + reviewHelper
            });
        } else {
            setQuickButton(reviewButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('No weak ALT descriptions are waiting for review.', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                primary: reviewPrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }

        if (!bulkButton) {
            return;
        }

        if (!data.isPremium) {
            setQuickButton(bulkButton, {
                label: __('Automatically optimize every new image you upload', 'beepbeep-ai-alt-text-generator'),
                helper: __('Never worry about missing ALT text again.', 'beepbeep-ai-alt-text-generator'),
                action: 'show-upgrade-modal',
                primary: bulkPrimary,
                preserveContent: true,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
            return;
        }

        if (!hasScanResults(data)) {
            setQuickButton(bulkButton, {
                label: __('Scan Media Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('Scan first to find images that can be fixed automatically.', 'beepbeep-ai-alt-text-generator'),
                bbaiAction: 'scan-opportunity',
                primary: bulkPrimary,
                preserveContent: true,
                removeAttributes: ['data-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        } else if (data.missing > 0) {
            setQuickButton(bulkButton, {
                label: __('Fix all images automatically', 'beepbeep-ai-alt-text-generator'),
                helper: __('Bulk generation is available on your plan.', 'beepbeep-ai-alt-text-generator'),
                action: 'generate-missing',
                bbaiAction: 'generate_missing',
                primary: bulkPrimary,
                preserveContent: true
            });
        } else if (data.weak > 0) {
            setQuickButton(bulkButton, {
                label: __('Fix all images automatically', 'beepbeep-ai-alt-text-generator'),
                helper: __('Bulk improvements are available on your plan.', 'beepbeep-ai-alt-text-generator'),
                action: 'regenerate-all',
                attributes: {
                    'data-bbai-regenerate-scope': 'needs-review',
                    'data-bbai-generation-source': 'regenerate-weak'
                },
                removeAttributes: ['data-bbai-action'],
                primary: bulkPrimary,
                preserveContent: true
            });
        } else {
            setQuickButton(bulkButton, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                helper: __('No bulk fixes are needed right now.', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                primary: bulkPrimary,
                preserveContent: true,
                removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }
    }

    function renderWorkflow(data) {
        if (!data) {
            return;
        }

        var reviewDesc = document.querySelector('[data-bbai-workflow-review-desc]');
        var reviewCta = document.querySelector('[data-bbai-workflow-review-cta]');
        var reviewDescription = hasScanResults(data)
            ? __('Review, edit, or improve generated descriptions.', 'beepbeep-ai-alt-text-generator')
            : __('Review, edit, or improve generated descriptions after scanning.', 'beepbeep-ai-alt-text-generator');

        if (reviewDesc) {
            reviewDesc.textContent = reviewDescription;
        }

        if (reviewCta) {
            reviewCta.className = 'bbai-workflow-step__btn bbai-workflow-step__btn--secondary';
            setInteractiveControl(reviewCta, {
                label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                href: data.libraryUrl,
                removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
            });
        }
    }

    function getStatusCardSegmentColor(segmentKey, activeSegment) {
        var palette = {
            optimized: {
                base: '#22c55e',
                active: '#16a34a',
                muted: '#d9f4e2'
            },
            weak: {
                base: '#f59e0b',
                active: '#d97706',
                muted: '#fde7c4'
            },
            missing: {
                base: '#ef4444',
                active: '#dc2626',
                muted: '#fee2e2'
            }
        };
        var colors = palette[segmentKey] || {
            base: '#e2e8f0',
            active: '#cbd5e1',
            muted: '#e2e8f0'
        };

        if (!activeSegment) {
            return colors.base;
        }

        return activeSegment === segmentKey ? colors.active : colors.muted;
    }

    function getStatusCardDonutGradient(statusCoverage, activeSegment) {
        var optimizedAngle;
        var weakAngle;
        var optimizedEnd;
        var weakEnd;

        if (!statusCoverage || statusCoverage.total <= 0) {
            return 'conic-gradient(#e2e8f0 0deg 360deg)';
        }

        optimizedAngle = 360 * (statusCoverage.optimized / statusCoverage.total);
        weakAngle = 360 * (statusCoverage.weak / statusCoverage.total);
        optimizedEnd = Math.max(0, Math.min(360, optimizedAngle));
        weakEnd = Math.max(optimizedEnd, Math.min(360, optimizedEnd + weakAngle));

        return 'conic-gradient(' +
            getStatusCardSegmentColor('optimized', activeSegment) + ' 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('weak', activeSegment) + ' ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
            getStatusCardSegmentColor('missing', activeSegment) + ' ' + weakEnd.toFixed(3) + 'deg 360deg' +
            ')';
    }

    function getStatusCardActiveSegment(statusCard) {
        if (!statusCard) {
            return '';
        }

        return String(statusCard.getAttribute('data-bbai-status-active-segment') || '');
    }

    function applyStatusCardDonutState(statusCard, statusCoverage) {
        var donutNode = statusCard ? statusCard.querySelector('[data-bbai-status-donut]') : null;
        var activeSegment = getStatusCardActiveSegment(statusCard);

        if (!donutNode) {
            return;
        }

        donutNode.style.background = getStatusCardDonutGradient(statusCoverage, activeSegment);
        donutNode.setAttribute('data-bbai-status-active-segment', activeSegment);
    }

    function setStatusCardActiveSegment(statusCard, segmentKey) {
        var activeSegment = String(segmentKey || '');
        var dashboardData;

        if (!statusCard) {
            return;
        }

        if (activeSegment) {
            statusCard.setAttribute('data-bbai-status-active-segment', activeSegment);
        } else {
            statusCard.removeAttribute('data-bbai-status-active-segment');
        }

        Array.prototype.forEach.call(statusCard.querySelectorAll('[data-bbai-status-row]'), function(row) {
            row.classList.toggle(
                'is-active',
                !!activeSegment && String(row.getAttribute('data-bbai-status-segment') || '') === activeSegment
            );
        });

        dashboardData = getDashboardData();
        applyStatusCardDonutState(statusCard, getStatusCoverageData(dashboardData));
    }

    function getStatusCardFilterValue(segmentKey, fallbackFilter) {
        if (fallbackFilter) {
            return String(fallbackFilter);
        }

        if (segmentKey === 'optimized') {
            return 'optimized';
        }
        if (segmentKey === 'weak') {
            return 'needs_review';
        }
        if (segmentKey === 'missing') {
            return 'missing';
        }

        return '';
    }

    function buildStatusCardFilterUrl(baseUrl, filterValue) {
        var cleanBaseUrl = String(baseUrl || '');
        var statusValue = String(filterValue || '');

        if (!cleanBaseUrl) {
            return '';
        }

        try {
            var url = new URL(cleanBaseUrl, window.location.href);
            if (statusValue) {
                url.searchParams.set('status', statusValue);
            } else {
                url.searchParams.delete('status');
            }
            return url.toString();
        } catch (error) {
            if (!statusValue) {
                return cleanBaseUrl;
            }
            return cleanBaseUrl + (cleanBaseUrl.indexOf('?') === -1 ? '?' : '&') + 'status=' + encodeURIComponent(statusValue);
        }
    }

    function resolveStatusCardRowUrl(row) {
        var dashboardData;
        var targetUrl;
        var segmentKey;
        var filterValue;

        if (!row) {
            return '';
        }

        targetUrl = String(row.getAttribute('href') || row.getAttribute('data-bbai-status-url') || '');
        if (!targetUrl) {
            dashboardData = getDashboardData();
            segmentKey = String(row.getAttribute('data-bbai-status-segment') || '');
            filterValue = getStatusCardFilterValue(
                segmentKey,
                row.getAttribute('data-bbai-status-filter') || ''
            );
            targetUrl = buildStatusCardFilterUrl(dashboardData && dashboardData.libraryUrl, filterValue);
        }

        return targetUrl;
    }

    function navigateToStatusCardFilter(row) {
        var targetUrl = resolveStatusCardRowUrl(row);

        if (targetUrl) {
            window.location.assign(targetUrl);
        }
    }

    function setStatusCardRefreshLoading(button, isLoading) {
        if (!button) {
            return;
        }

        button.classList.toggle('is-loading', !!isLoading);
        button.disabled = !!isLoading;
        if (isLoading) {
            button.setAttribute('aria-busy', 'true');
        } else {
            button.removeAttribute('aria-busy');
        }
        button.setAttribute(
            'aria-label',
            isLoading
                ? __('Refreshing library scan', 'beepbeep-ai-alt-text-generator')
                : __('Refresh library scan', 'beepbeep-ai-alt-text-generator')
        );
    }

    function dispatchDashboardFeedbackMessage(type, message) {
        if (!message) {
            return;
        }

        try {
            document.dispatchEvent(new CustomEvent('bbai:dashboard-feedback', {
                detail: {
                    type: type || 'info',
                    message: message,
                    duration: 4200
                }
            }));
        } catch (error) {
            // Ignore CustomEvent failures.
        }
    }

    function handleStatusCardRefresh(event) {
        var button = event && event.currentTarget ? event.currentTarget : null;
        var request;
        var fallbackMessage = __('Unable to refresh the library scan right now.', 'beepbeep-ai-alt-text-generator');

        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (!button || button.classList.contains('is-loading')) {
            return;
        }

        if (typeof window.bbaiRefreshDashboardCoverage !== 'function') {
            dispatchDashboardFeedbackMessage('error', fallbackMessage);
            return;
        }

        setStatusCardRefreshLoading(button, true);
        request = window.bbaiRefreshDashboardCoverage();

        if (!request || typeof request.always !== 'function') {
            setStatusCardRefreshLoading(button, false);
            return;
        }

        if (typeof request.fail === 'function') {
            request.fail(function(errorMessage) {
                dispatchDashboardFeedbackMessage(
                    'error',
                    errorMessage || __('Scan failed. Please try again.', 'beepbeep-ai-alt-text-generator')
                );
            });
        }

        request.always(function() {
            setStatusCardRefreshLoading(button, false);
        });
    }

    function bindStatusCardInteractions() {
        var statusCard = document.querySelector('[data-bbai-dashboard-status-card="1"]');
        var statusRows;
        var refreshButton;

        if (!statusCard || statusCard.getAttribute('data-bbai-status-interactions-bound') === '1') {
            return;
        }

        statusRows = statusCard.querySelectorAll('[data-bbai-status-row]');
        refreshButton = statusCard.querySelector('[data-bbai-status-refresh]');

        statusCard.setAttribute('data-bbai-status-interactions-bound', '1');

        if (!statusRows.length && !refreshButton) {
            return;
        }

        Array.prototype.forEach.call(statusRows, function(row) {
            var targetUrl = resolveStatusCardRowUrl(row);

            if (targetUrl) {
                row.setAttribute('href', targetUrl);
            }
        });

        statusCard.addEventListener('mouseover', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;

            if (!row || !statusCard.contains(row)) {
                return;
            }

            setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '');
        });

        statusCard.addEventListener('mouseout', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;
            var relatedRow = event.relatedTarget && typeof event.relatedTarget.closest === 'function'
                ? event.relatedTarget.closest('[data-bbai-status-row]')
                : null;

            if (!row || !statusCard.contains(row)) {
                return;
            }

            if (relatedRow && statusCard.contains(relatedRow)) {
                setStatusCardActiveSegment(statusCard, relatedRow.getAttribute('data-bbai-status-segment') || '');
                return;
            }

            if (
                document.activeElement &&
                typeof document.activeElement.closest === 'function' &&
                statusCard.contains(document.activeElement) &&
                document.activeElement.closest('[data-bbai-status-row]')
            ) {
                setStatusCardActiveSegment(
                    statusCard,
                    document.activeElement.closest('[data-bbai-status-row]').getAttribute('data-bbai-status-segment') || ''
                );
                return;
            }

            setStatusCardActiveSegment(statusCard, '');
        });

        statusCard.addEventListener('focusin', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;

            if (!row || !statusCard.contains(row)) {
                return;
            }

            setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '');
        });

        statusCard.addEventListener('focusout', function() {
            window.setTimeout(function() {
                var activeRow = document.activeElement && typeof document.activeElement.closest === 'function'
                    ? document.activeElement.closest('[data-bbai-status-row]')
                    : null;

                if (activeRow && statusCard.contains(activeRow)) {
                    setStatusCardActiveSegment(statusCard, activeRow.getAttribute('data-bbai-status-segment') || '');
                    return;
                }

                if (!statusCard.matches(':hover')) {
                    setStatusCardActiveSegment(statusCard, '');
                }
            }, 0);
        });

        statusCard.addEventListener('click', function(event) {
            var row = event.target && typeof event.target.closest === 'function'
                ? event.target.closest('[data-bbai-status-row]')
                : null;
            var targetUrl;
            var isModifiedClick;

            if (!row || !statusCard.contains(row)) {
                return;
            }

            isModifiedClick = !!(
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                event.button !== 0
            );
            if (isModifiedClick) {
                return;
            }

            event.preventDefault();
            targetUrl = resolveStatusCardRowUrl(row);
            if (!targetUrl) {
                return;
            }

            setStatusCardActiveSegment(statusCard, row.getAttribute('data-bbai-status-segment') || '');
            row.classList.add('is-pressed');

            window.setTimeout(function() {
                navigateToStatusCardFilter(row);
            }, 110);
        });

        if (refreshButton) {
            refreshButton.addEventListener('click', handleStatusCardRefresh);
        }
    }

    function renderStatusCard(data) {
        if (!data) {
            return;
        }

        var statusCoverage = getStatusCoverageData(data);
        var statusCard = document.querySelector('[data-bbai-dashboard-status-card="1"]');
        if (!statusCard) {
            return;
        }

        var summaryRatioNode = statusCard.querySelector('[data-bbai-status-summary-ratio]');
        var summaryMetaNode = statusCard.querySelector('[data-bbai-status-last-scan]');
        var summaryDetailNode = statusCard.querySelector('[data-bbai-status-summary-detail]');
        var metrics = {
            optimized: statusCard.querySelector('[data-bbai-status-metric="optimized"]'),
            weak: statusCard.querySelector('[data-bbai-status-metric="weak"]'),
            missing: statusCard.querySelector('[data-bbai-status-metric="missing"]')
        };
        var coverageValue = statusCard.querySelector('[data-bbai-status-coverage-value]');

        if (summaryRatioNode || summaryDetailNode) {
            var optimizedRatio = getOptimizedRatioCopy(statusCoverage);
            var summaryRatio = optimizedRatio;
            var summaryMeta = formatLastScanCopy(data.lastScanTs);
            var summaryDetail = '';

            if (!hasScanResults(data) || statusCoverage.total <= 0) {
                summaryRatio = __('No images found in your media library', 'beepbeep-ai-alt-text-generator');
                summaryDetail = __('Run your first scan to see coverage and missing ALT text.', 'beepbeep-ai-alt-text-generator');
            } else if (statusCoverage.missing > 0) {
                summaryDetail = sprintf(
                    _n('%s image is missing ALT text.', '%s images are missing ALT text.', statusCoverage.missing, 'beepbeep-ai-alt-text-generator'),
                    formatCount(statusCoverage.missing)
                );
            } else if (statusCoverage.weak > 0) {
                summaryDetail = sprintf(
                    _n('%s description needs review.', '%s descriptions need review.', statusCoverage.weak, 'beepbeep-ai-alt-text-generator'),
                    formatCount(statusCoverage.weak)
                );
            } else if (statusCoverage.optimized > 0) {
                summaryDetail = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
            }

            if (summaryRatioNode) {
                summaryRatioNode.textContent = summaryRatio;
            }
            if (summaryMetaNode) {
                summaryMetaNode.textContent = summaryMeta;
                summaryMetaNode.hidden = !summaryMeta;
            }
            if (summaryDetailNode) {
                summaryDetailNode.textContent = summaryDetail;
                summaryDetailNode.hidden = !summaryDetail;
            }
        }

        Object.keys(metrics).forEach(function(key) {
            if (metrics[key]) {
                metrics[key].textContent = formatCount(statusCoverage[key]);
            }
        });

        if (coverageValue) {
            coverageValue.textContent = formatCount(statusCoverage.coverage);
        }

        applyStatusCardDonutState(statusCard, statusCoverage);
    }

    function animateStatusRingSegment(segmentNode, segmentStyle, circumference) {
        if (!segmentNode || !segmentStyle) {
            return;
        }

        var hiddenDasharray = '0 ' + Math.max(0, circumference).toFixed(3);
        var targetOpacity = segmentStyle.isVisible ? '1' : '0';
        var hasAnimated = segmentNode.getAttribute('data-bbai-status-ring-initialized') === '1';
        var animationDelay = Math.max(0, parseInt(segmentStyle.animationOrder, 10) || 0) * 180;

        if (hasAnimated) {
            segmentNode.style.transition = 'stroke-dasharray 0.8s ease, stroke-dashoffset 0.8s ease, opacity 0.25s ease';
            segmentNode.style.strokeDasharray = segmentStyle.dasharray;
            segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
            segmentNode.style.opacity = targetOpacity;
            return;
        }

        segmentNode.style.transition = 'none';
        segmentNode.style.strokeDasharray = hiddenDasharray;
        segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
        segmentNode.style.opacity = targetOpacity;
        segmentNode.offsetWidth;

        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                segmentNode.style.transition = 'stroke-dasharray 0.8s ease ' + animationDelay + 'ms, stroke-dashoffset 0.8s ease ' + animationDelay + 'ms, opacity 0.25s ease ' + animationDelay + 'ms';
                segmentNode.style.strokeDasharray = segmentStyle.dasharray;
                segmentNode.style.strokeDashoffset = segmentStyle.dashoffset;
                segmentNode.style.opacity = targetOpacity;
                segmentNode.setAttribute('data-bbai-status-ring-initialized', '1');
            });
        });
    }

    function animateLinearProgress(node, targetPercent, duration, delay) {
        if (!node) {
            return;
        }

        var target = Math.min(100, Math.max(0, parseFloat(targetPercent) || 0));
        var transitionDuration = Math.max(0, parseInt(duration, 10) || 600);
        var transitionDelay = Math.max(0, parseInt(delay, 10) || 0);
        var hasAnimated = node.getAttribute('data-bbai-progress-initialized') === '1';
        var transitionValue = 'width ' + transitionDuration + 'ms ease' + (transitionDelay > 0 ? ' ' + transitionDelay + 'ms' : '');

        if (hasAnimated) {
            node.style.transition = transitionValue;
            node.style.width = target + '%';
            return;
        }

        node.style.transition = 'none';
        node.style.width = '0%';
        node.offsetWidth;

        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                node.style.transition = transitionValue;
                node.style.width = target + '%';
                node.setAttribute('data-bbai-progress-initialized', '1');
            });
        });
    }

    function getAccessibilityImpact(data) {
        var optimized = Math.max(0, parseCount(data && data.optimized));
        var total = Math.max(0, parseCount(data && data.total));
        var coverage = total > 0 ? Math.round((optimized / total) * 100) : 0;
        var impact = {
            coverage: coverage,
            coverageLabel: formatCount(coverage),
            optimized: optimized,
            optimizedLabel: formatCount(optimized)
        };

        impact.altText = sprintf(
            __('Shareable accessibility badge showing a %1$s%% accessibility score and %2$s optimized images powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            impact.coverageLabel,
            impact.optimizedLabel
        );
        impact.embedHtml = '<a href="https://beepbeep.ai" target="_blank" rel="noreferrer noopener">\n<img src="' + getAccessibilityBadgeDataUrl(impact) + '"\nalt="' + escapeSvgText(impact.altText) + '">\n</a>';
        impact.shareText = sprintf(
            __('My WordPress site has an accessibility score of %1$s%% with %2$s optimized images powered by BeepBeep AI.', 'beepbeep-ai-alt-text-generator'),
            impact.coverageLabel,
            impact.optimizedLabel
        );

        return impact;
    }

    function escapeSvgText(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildAccessibilityBadgeSvg(impact) {
        var titleText = __('Accessibility badge powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator');
        var descText = impact && impact.altText
            ? impact.altText
            : sprintf(
                __('Shareable accessibility badge showing a %1$s%% accessibility score and %2$s optimized images powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
                impact.coverageLabel,
                impact.optimizedLabel
            );

        return [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 460 220" width="460" height="220" role="img" aria-labelledby="bbai-badge-title bbai-badge-desc">',
            '<title id="bbai-badge-title">', escapeSvgText(titleText), '</title>',
            '<desc id="bbai-badge-desc">', escapeSvgText(descText), '</desc>',
            '<defs>',
            '<linearGradient id="bbai-badge-bg" x1="30" y1="24" x2="430" y2="196" gradientUnits="userSpaceOnUse">',
            '<stop offset="0%" stop-color="#FFFFFF"/>',
            '<stop offset="100%" stop-color="#F3FBF5"/>',
            '</linearGradient>',
            '<linearGradient id="bbai-badge-score" x1="44" y1="58" x2="190" y2="58" gradientUnits="userSpaceOnUse">',
            '<stop offset="0%" stop-color="#16A34A"/>',
            '<stop offset="100%" stop-color="#15803D"/>',
            '</linearGradient>',
            '</defs>',
            '<rect x="1" y="1" width="458" height="218" rx="24" fill="url(#bbai-badge-bg)" stroke="#B7DFC0"/>',
            '<circle cx="396" cy="48" r="28" fill="#DCFCE7"/>',
            '<circle cx="423" cy="34" r="8" fill="#86EFAC"/>',
            '<path d="M384 49l9 9 18-20" fill="none" stroke="#15803D" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>',
            '<text x="36" y="44" fill="#15803D" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" letter-spacing="0.03em">', escapeSvgText(__('Accessibility score', 'beepbeep-ai-alt-text-generator')), '</text>',
            '<text x="36" y="112" fill="url(#bbai-badge-score)" font-family="Segoe UI, Arial, sans-serif" font-size="58" font-weight="700">', escapeSvgText(impact.coverageLabel), '%</text>',
            '<path d="M36 138h388" stroke="#CDE7D2" stroke-width="1"/>',
            '<text x="36" y="170" fill="#166534" font-family="Segoe UI, Arial, sans-serif" font-size="16" font-weight="600">', escapeSvgText(__('Images optimized', 'beepbeep-ai-alt-text-generator')), '</text>',
            '<text x="424" y="170" text-anchor="end" fill="#0F172A" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700">', escapeSvgText(impact.optimizedLabel), '</text>',
            '<text x="36" y="196" fill="#166534" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="600">', escapeSvgText(__('Powered by BeepBeep AI', 'beepbeep-ai-alt-text-generator')), '</text>',
            '</svg>'
        ].join('');
    }

    function getAccessibilityBadgeDataUrl(impact) {
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(buildAccessibilityBadgeSvg(impact));
    }

    function getUpgradeCardCopy(data) {
        var usagePercent;
        var growthComparison;

        if (!data) {
            return null;
        }

        usagePercent = Math.min(100, Math.max(0, (data.creditsUsed / Math.max(1, data.creditsTotal)) * 100));
        growthComparison = getGrowthPlanComparison(data);

        if (!hasCreditsAvailable(data) && !data.isPremium) {
            return {
                context: __('You have reached your AI generation limit. Growth restarts progress immediately and keeps future uploads moving automatically.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Restart with Growth', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: true
            };
        }

        if (!data.isPremium && data.creditsRemaining <= 5) {
            return {
                context: __('You are running low on AI generations. Growth keeps automation running as new uploads arrive.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Upgrade Before You Run Out', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: true
            };
        }

        if (data.isPremium) {
            return {
                context: __('Growth is built for continuous coverage, not one-off manual cleanups.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Open Automation Settings', 'beepbeep-ai-alt-text-generator'),
                growthLine: __('Growth automation is ready for future uploads and bulk clean-ups.', 'beepbeep-ai-alt-text-generator'),
                growthUsageLine: growthComparison.line,
                growthUsagePercent: growthComparison.percent,
                growthIndicatorPercent: growthComparison.indicatorPercent,
                usageLine: sprintf(
                    __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                    formatCount(data.creditsUsed),
                    formatCount(data.creditsTotal)
                ),
                remainingLine: getRemainingPlanLine(data),
                resetLine: data.creditsResetLine || '',
                usagePercent: usagePercent,
                urgent: false
            };
        }

        return {
            context: __('Upgrade for automatic coverage, faster bulk optimization, and less manual work each month.', 'beepbeep-ai-alt-text-generator'),
            ctaLabel: __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'),
            growthLine: __('Growth unlocks always-on media library automation.', 'beepbeep-ai-alt-text-generator'),
            growthUsageLine: growthComparison.line,
            growthUsagePercent: growthComparison.percent,
            growthIndicatorPercent: growthComparison.indicatorPercent,
            usageLine: sprintf(
                __('%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator'),
                formatCount(data.creditsUsed),
                formatCount(data.creditsTotal)
            ),
            remainingLine: getRemainingPlanLine(data),
            resetLine: data.creditsResetLine || '',
            usagePercent: usagePercent,
            urgent: false
        };
    }

    function getAutomationCardCopy(data) {
        var firstRun;
        var hasExistingIssues;

        if (!data) {
            return null;
        }

        firstRun = !hasScanResults(data) || data.total <= 0;
        hasExistingIssues = data.missing > 0 || data.weak > 0;

        if (data.isPremium) {
            return {
                lead: firstRun
                    ? __('After your first scan, keep future uploads covered automatically from Settings.', 'beepbeep-ai-alt-text-generator')
                    : (
                        hasExistingIssues
                            ? __('Finish cleaning up your current library, then use automation to keep new uploads from adding more manual work.', 'beepbeep-ai-alt-text-generator')
                            : __('You’ve optimized your current images. Now keep future uploads covered automatically.', 'beepbeep-ai-alt-text-generator')
                    ),
                statusLabel: __('Growth ready', 'beepbeep-ai-alt-text-generator'),
                statusHelper: __('Manage this in Settings when you want on-upload ALT generation.', 'beepbeep-ai-alt-text-generator'),
                valueSupport: __('Manage how future uploads are handled in Settings whenever your workflow changes.', 'beepbeep-ai-alt-text-generator'),
                ctaLabel: __('Manage Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
                secondaryLabel: __('Review ALT Library', 'beepbeep-ai-alt-text-generator'),
                modifier: 'ready'
            };
        }

        return {
            lead: firstRun
                ? __('After your first scan, this can keep new uploads optimized automatically.', 'beepbeep-ai-alt-text-generator')
                : (
                    hasExistingIssues
                        ? __('Once your existing library is covered, this keeps new uploads optimized automatically.', 'beepbeep-ai-alt-text-generator')
                        : __('You’ve optimized your current images. Now automate future uploads.', 'beepbeep-ai-alt-text-generator')
                ),
            statusLabel: __('Available on Growth', 'beepbeep-ai-alt-text-generator'),
            statusHelper: __('Turn on with the Growth plan.', 'beepbeep-ai-alt-text-generator'),
            valueSupport: __('Upgrade once and let Growth keep future uploads covered automatically.', 'beepbeep-ai-alt-text-generator'),
            ctaLabel: __('Enable Auto-Optimization', 'beepbeep-ai-alt-text-generator'),
            secondaryLabel: __('Compare plans', 'beepbeep-ai-alt-text-generator'),
            modifier: 'locked'
        };
    }

    function renderPerformanceMetrics(data) {
        if (!data) {
            return;
        }

        var minutesNode = document.querySelector('[data-bbai-performance-minutes]');
        var optimizedNode = document.querySelector('[data-bbai-performance-optimized]');
        var coverageNode = document.querySelector('[data-bbai-performance-coverage]');
        var hoursSaved = Math.max(0, (data.creditsUsed * 2.5) / 60);
        var minutesSaved = Math.round(hoursSaved * 60);
        var coveragePercent = data.total > 0 ? Math.round((data.optimized / data.total) * 100) : 0;

        if (minutesNode) {
            minutesNode.textContent = formatCount(minutesSaved);
        }
        if (optimizedNode) {
            optimizedNode.textContent = formatCount(data.optimized);
        }
        if (coverageNode) {
            coverageNode.textContent = formatCount(coveragePercent);
        }
    }

    function renderAccessibilityImpact(data) {
        var cardNode = document.querySelector('[data-bbai-accessibility-card="1"]');
        var coverageNode;
        var optimizedNode;
        var statsLineNode;
        var previewImageNode;
        var shareNode;
        var impact;

        if (!cardNode || !data) {
            return;
        }

        coverageNode = cardNode.querySelector('[data-bbai-accessibility-coverage]');
        optimizedNode = cardNode.querySelector('[data-bbai-accessibility-optimized]');
        statsLineNode = cardNode.querySelector('[data-bbai-accessibility-stats-line]');
        previewImageNode = cardNode.querySelector('[data-bbai-accessibility-badge-preview]');
        shareNode = cardNode.querySelector('[data-bbai-accessibility-action="share"]');
        impact = getAccessibilityImpact(data);

        if (coverageNode) {
            coverageNode.textContent = impact.coverageLabel;
        }

        if (optimizedNode) {
            optimizedNode.textContent = impact.optimizedLabel;
        }

        if (statsLineNode) {
            statsLineNode.textContent = sprintf(
                __('Accessibility score: %1$s%% | Images optimized: %2$s', 'beepbeep-ai-alt-text-generator'),
                impact.coverageLabel,
                impact.optimizedLabel
            );
        }

        if (previewImageNode) {
            previewImageNode.setAttribute('src', getAccessibilityBadgeDataUrl(impact));
            previewImageNode.setAttribute('alt', impact.altText);
        }

        if (shareNode) {
            shareNode.setAttribute(
                'href',
                'https://twitter.com/intent/tweet?text=' + encodeURIComponent(impact.shareText) + '&url=' + encodeURIComponent('https://beepbeep.ai')
            );
        }
    }

    function renderUpgradeContext(data) {
        var planLabelNode = document.querySelector('[data-bbai-plan-label]');
        var usageLineNode = document.querySelector('[data-bbai-plan-usage-line]');
        var remainingLineNode = document.querySelector('[data-bbai-plan-usage-remaining]');
        var resetLineNode = document.querySelector('[data-bbai-plan-usage-reset]');
        var usageProgressNode = document.querySelector('[data-bbai-plan-usage-progress]');
        var growthLineNode = document.querySelector('[data-bbai-plan-growth-line]');
        var growthProgressNode = document.querySelector('[data-bbai-plan-growth-progress]');
        var growthPercentNode = document.querySelector('[data-bbai-plan-growth-percent-label]');
        var upgradeNoteNode = document.querySelector('[data-bbai-plan-upgrade-note]');
        var primaryActionNode = document.querySelector('[data-bbai-plan-action-primary]');
        var secondaryActionNode = document.querySelector('[data-bbai-plan-action-secondary]');
        var growthComparison = getGrowthPlanComparison(data);

        if (!data) {
            return;
        }

        if (planLabelNode) {
            planLabelNode.textContent = getPlanLabel(data);
        }

        if (usageLineNode) {
            usageLineNode.textContent = sprintf(
                __('%1$s / %2$s used', 'beepbeep-ai-alt-text-generator'),
                formatCount(data.creditsUsed),
                formatCount(data.creditsTotal)
            );
        }

        if (remainingLineNode) {
            remainingLineNode.textContent = getRemainingPlanLine(data);
        }

        if (resetLineNode) {
            resetLineNode.textContent = getCompactPlanResetLine(data);
        }

        if (usageProgressNode) {
            var usagePercent = Math.min(100, Math.max(0, Math.round((data.creditsUsed / Math.max(1, data.creditsTotal)) * 100)));
            usageProgressNode.setAttribute('data-bbai-plan-usage-progress-target', String(usagePercent));
            animateLinearProgress(usageProgressNode, usagePercent, 600, 80);
        }

        if (growthLineNode) {
            growthLineNode.textContent = growthComparison.line || '';
        }

        if (growthProgressNode) {
            growthProgressNode.setAttribute('data-bbai-plan-growth-progress-target', String(growthComparison.percent || 0));
            animateLinearProgress(growthProgressNode, growthComparison.percent || 0, 600, 120);
        }

        if (growthPercentNode) {
            growthPercentNode.textContent = (growthComparison.display || '0') + '%';
        }

        if (upgradeNoteNode) {
            upgradeNoteNode.hidden = !!data.isPremium;
        }

        if (primaryActionNode) {
            if (data.isPremium) {
                primaryActionNode.hidden = false;
                primaryActionNode.className = 'bbai-command-action bbai-command-action--secondary';
                primaryActionNode.textContent = __('Review usage', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(primaryActionNode, {
                    label: __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    href: data.usageUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            } else {
                primaryActionNode.hidden = false;
                primaryActionNode.className = 'bbai-command-action bbai-command-action--primary';
                primaryActionNode.textContent = __('Upgrade', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(primaryActionNode, {
                    label: __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                    action: 'show-upgrade-modal',
                    href: '#',
                    removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            }
        }

        if (secondaryActionNode) {
            if (data.isPremium) {
                secondaryActionNode.hidden = true;
            } else {
                secondaryActionNode.hidden = false;
                secondaryActionNode.className = 'bbai-command-action bbai-command-action--secondary';
                secondaryActionNode.textContent = __('Review usage', 'beepbeep-ai-alt-text-generator');
                setInteractiveControl(secondaryActionNode, {
                    label: __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    href: data.usageUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                });
            }
        }
    }

    function getDashboardAlertModel(data) {
        var remaining;

        if (!data) {
            return null;
        }

        remaining = Math.max(0, parseCount(data.creditsRemaining));

        if (remaining === 0) {
            return {
                tone: 'danger',
                title: __('You have no optimizations left this cycle', 'beepbeep-ai-alt-text-generator'),
                message: __('Upgrade or wait for your allowance to reset to continue ALT generation.', 'beepbeep-ai-alt-text-generator'),
                primaryAction: {
                    label: __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                    action: 'show-upgrade-modal',
                    href: '#',
                    removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                },
                secondaryAction: {
                    label: __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    href: data.usageUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                }
            };
        }

        if (parseCount(data.missing) > 0) {
            return {
                tone: 'warning',
                title: __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                message: _n(
                    'Fix the remaining image to reach full coverage.',
                    'Fix the remaining images to reach full coverage.',
                    parseCount(data.missing),
                    'beepbeep-ai-alt-text-generator'
                ),
                primaryAction: {
                    label: __('Fix missing ALT text', 'beepbeep-ai-alt-text-generator'),
                    action: 'generate-missing',
                    bbaiAction: 'generate_missing',
                    href: '#',
                    removeAttributes: ['data-bbai-regenerate-scope', 'data-bbai-generation-source']
                },
                secondaryAction: {
                    label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    href: data.missingLibraryUrl || data.libraryUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                }
            };
        }

        if (parseCount(data.weak) > 0) {
            return {
                tone: 'warning',
                title: __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                message: _n(
                    'Review the remaining description to reach full coverage.',
                    'Review the remaining descriptions to reach full coverage.',
                    parseCount(data.weak),
                    'beepbeep-ai-alt-text-generator'
                ),
                primaryAction: {
                    label: __('Review ALT text', 'beepbeep-ai-alt-text-generator'),
                    action: 'show-generate-alt-modal',
                    href: '#',
                    removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                },
                secondaryAction: {
                    label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                    href: data.needsReviewLibraryUrl || data.libraryUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                }
            };
        }

        if (remaining > 0 && remaining < 10) {
            if (data.isPremium) {
                return {
                    tone: 'warning',
                    title: sprintf(
                        _n('%s optimization left this cycle', '%s optimizations left this cycle', remaining, 'beepbeep-ai-alt-text-generator'),
                        formatCount(remaining)
                    ),
                    message: __('Review your usage before the cycle resets.', 'beepbeep-ai-alt-text-generator'),
                    primaryAction: {
                        label: __('Review usage', 'beepbeep-ai-alt-text-generator'),
                        href: data.usageUrl || '#',
                        removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    },
                    secondaryAction: {
                        label: __('Open ALT Library', 'beepbeep-ai-alt-text-generator'),
                        href: data.libraryUrl || '#',
                        removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                    }
                };
            }

            return {
                tone: 'warning',
                title: sprintf(
                    _n('%s optimization left this cycle', '%s optimizations left this cycle', remaining, 'beepbeep-ai-alt-text-generator'),
                    formatCount(remaining)
                ),
                message: __('Upgrade to keep optimizing without interruption.', 'beepbeep-ai-alt-text-generator'),
                primaryAction: {
                    label: __('Upgrade', 'beepbeep-ai-alt-text-generator'),
                    action: 'show-upgrade-modal',
                    href: '#',
                    removeAttributes: ['data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                },
                secondaryAction: {
                    label: __('Review usage', 'beepbeep-ai-alt-text-generator'),
                    href: data.usageUrl || '#',
                    removeAttributes: ['data-action', 'data-bbai-action', 'data-bbai-regenerate-scope', 'data-bbai-generation-source']
                }
            };
        }

        return null;
    }

    function renderDashboardAlert(data) {
        var alertSection = document.querySelector('[data-bbai-dashboard-alert="1"]');
        var titleNode;
        var messageNode;
        var primaryNode;
        var secondaryNode;
        var alertModel;

        if (!alertSection) {
            return;
        }

        alertModel = getDashboardAlertModel(data);
        if (!alertModel) {
            alertSection.hidden = true;
            return;
        }

        titleNode = alertSection.querySelector('[data-bbai-dashboard-alert-title]');
        messageNode = alertSection.querySelector('[data-bbai-dashboard-alert-message]');
        primaryNode = alertSection.querySelector('[data-bbai-dashboard-alert-primary]');
        secondaryNode = alertSection.querySelector('[data-bbai-dashboard-alert-secondary]');

        alertSection.className = 'bbai-dashboard-alert-card bbai-dashboard-alert-card--' + (alertModel.tone || 'warning');
        alertSection.hidden = false;

        if (titleNode) {
            titleNode.textContent = alertModel.title || '';
        }

        if (messageNode) {
            messageNode.textContent = alertModel.message || '';
        }

        if (primaryNode && alertModel.primaryAction) {
            primaryNode.hidden = !(alertModel.primaryAction.label);
            primaryNode.textContent = alertModel.primaryAction.label || '';
            setInteractiveControl(primaryNode, alertModel.primaryAction);
        }

        if (secondaryNode && alertModel.secondaryAction) {
            secondaryNode.hidden = !(alertModel.secondaryAction.label);
            secondaryNode.textContent = alertModel.secondaryAction.label || '';
            setInteractiveControl(secondaryNode, alertModel.secondaryAction);
        }
    }

    function shouldShowDashboardReviewPrompt(data) {
        var coverage = getStatusCoverageData(data);
        var remaining = Math.max(0, parseCount(data && data.creditsRemaining));

        if (!data || !coverage.total) {
            return false;
        }

        return coverage.coverage >= 95 &&
            coverage.optimized >= 20 &&
            coverage.missing === 0 &&
            coverage.weak === 0 &&
            remaining >= 10;
    }

    function renderDashboardReviewPrompt(data) {
        var promptNode = document.querySelector('[data-bbai-dashboard-review-prompt]');
        if (!promptNode) {
            return;
        }

        promptNode.hidden = !shouldShowDashboardReviewPrompt(data);
    }

    function renderDashboardState() {
        var data = getDashboardData();
        if (!data) {
            return;
        }

        bindStatusCardInteractions();
        renderHero(data);
        renderStatusCard(data);
        renderUpgradeContext(data);
        renderDashboardAlert(data);
        renderDashboardReviewPrompt(data);
    }

    window.bbaiGetDashboardData = getDashboardData;
    window.bbaiRenderDashboardHero = function(dataOverride) {
        renderHero(dataOverride || getDashboardData());
    };
    window.bbaiSyncDashboardState = function(statsOverride, usageOverride) {
        syncAndRender(statsOverride || null, usageOverride || null);
    };

    function setTemporaryButtonLabel(button, label) {
        var resetTimer;

        if (!button) {
            return;
        }

        if (!button.getAttribute('data-bbai-default-label')) {
            button.setAttribute('data-bbai-default-label', button.textContent || '');
        }

        button.textContent = label;
        resetTimer = parseInt(button.getAttribute('data-bbai-reset-timer'), 10);
        if (!isNaN(resetTimer) && resetTimer) {
            window.clearTimeout(resetTimer);
        }

        resetTimer = window.setTimeout(function() {
            button.textContent = button.getAttribute('data-bbai-default-label') || button.textContent;
            button.removeAttribute('data-bbai-reset-timer');
        }, 1800);

        button.setAttribute('data-bbai-reset-timer', String(resetTimer));
    }

    function copyTextToClipboard(text, onSuccess, onError) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(function() {
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
            }).catch(function(error) {
                if (typeof onError === 'function') {
                    onError(error);
                }
            });
            return;
        }

        try {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            if (document.execCommand('copy')) {
                document.body.removeChild(textarea);
                if (typeof onSuccess === 'function') {
                    onSuccess();
                }
                return;
            }

            document.body.removeChild(textarea);
        } catch (error) {
            if (typeof onError === 'function') {
                onError(error);
            }
            return;
        }

        if (typeof onError === 'function') {
            onError(new Error('copy_failed'));
        }
    }

    function showDashboardFeedback(detail) {
        var feedbackNode = document.querySelector('[data-bbai-dashboard-feedback]');
        if (!feedbackNode || !detail || !detail.message) {
            return;
        }

        feedbackNode.hidden = false;
        feedbackNode.textContent = detail.message;
        feedbackNode.className = 'bbai-dashboard-feedback bbai-dashboard-feedback--' + (detail.type || 'info');

        if (feedbackTimeout) {
            window.clearTimeout(feedbackTimeout);
        }

        feedbackTimeout = window.setTimeout(function() {
            feedbackNode.hidden = true;
            feedbackNode.textContent = '';
            feedbackNode.className = 'bbai-dashboard-feedback';
        }, Math.max(2500, parseInt(detail && detail.duration, 10) || 6000));
    }

    function syncAndRender(stats, usage) {
        if (stats) {
            syncStatsToRoot(stats);
        }
        if (usage) {
            syncUsageToRoot(usage);
        }
        renderDashboardState();
    }

    $(document).on('click', '[data-bbai-quick-action].is-disabled, [data-bbai-workflow-generate-cta].is-disabled', function(event) {
        if (this.getAttribute('data-action') === 'show-upgrade-modal') {
            return;
        }
        event.preventDefault();
    });

    document.addEventListener('click', function(event) {
        var eventTarget = event.target && event.target.nodeType === 1 ? event.target : event.target.parentElement;
        var actionButton = eventTarget && typeof eventTarget.closest === 'function'
            ? eventTarget.closest('[data-bbai-accessibility-action]')
            : null;
        var data;
        var cardNode;
        var previewNode;
        var impact;
        var svg;
        var blob;
        var url;
        var downloadLink;

        if (!actionButton) {
            return;
        }

        data = getDashboardData();
        if (!data) {
            return;
        }

        cardNode = actionButton.closest('[data-bbai-accessibility-card="1"]');
        if (!cardNode) {
            return;
        }

        previewNode = cardNode.querySelector('[data-bbai-accessibility-preview]');
        impact = getAccessibilityImpact(data);

        if (actionButton.getAttribute('data-bbai-accessibility-action') === 'preview') {
            event.preventDefault();
            if (previewNode) {
                previewNode.hidden = !previewNode.hidden;
                actionButton.setAttribute('aria-expanded', previewNode.hidden ? 'false' : 'true');
            }
            return;
        }

        if (
            actionButton.getAttribute('data-bbai-accessibility-action') === 'download' ||
            actionButton.getAttribute('data-bbai-accessibility-action') === 'get-badge'
        ) {
            event.preventDefault();
            svg = buildAccessibilityBadgeSvg(impact);

            if (typeof Blob === 'function' && window.URL && typeof window.URL.createObjectURL === 'function') {
                blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
                url = window.URL.createObjectURL(blob);
                downloadLink = document.createElement('a');
                downloadLink.href = url;
                downloadLink.download = 'beepbeep-ai-accessibility-impact.svg';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                window.setTimeout(function() {
                    window.URL.revokeObjectURL(url);
                }, 1000);
            } else {
                window.open(getAccessibilityBadgeDataUrl(impact), '_blank', 'noopener,noreferrer');
            }

            setTemporaryButtonLabel(actionButton, __('Downloaded', 'beepbeep-ai-alt-text-generator'));
            return;
        }

        if (actionButton.getAttribute('data-bbai-accessibility-action') === 'copy-embed') {
            event.preventDefault();
            copyTextToClipboard(
                impact.embedHtml,
                function() {
                    setTemporaryButtonLabel(actionButton, __('Copied', 'beepbeep-ai-alt-text-generator'));
                },
                function() {
                    setTemporaryButtonLabel(actionButton, __('Copy failed', 'beepbeep-ai-alt-text-generator'));
                }
            );
        }
    });

    document.addEventListener('bbai:dashboard-feedback', function(event) {
        if (event && event.detail) {
            showDashboardFeedback(event.detail);
        }
    });

    if (typeof window.addEventListener === 'function') {
        window.addEventListener('bbai-stats-update', function(event) {
            var usage = event && event.detail ? event.detail.usage : null;
            var stats = event && event.detail ? event.detail.stats : null;
            syncAndRender(stats, usage);
        });

        window.addEventListener('storage', function(event) {
            if (!event || event.key !== BBAI_STATS_SYNC_KEY || !event.newValue) {
                return;
            }

            try {
                var payload = JSON.parse(event.newValue);
                syncAndRender(payload && payload.stats ? payload.stats : null, payload && payload.usage ? payload.usage : null);
            } catch (storageError) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Failed to parse cross-tab dashboard stats sync payload.', storageError);
            }
        });
    }

    $(document).on('bbai:stats-updated.bbaiOperational bbai:usage-updated.bbaiOperational', function(event, payload) {
        var stats = null;
        var usage = bbaiGetUsageObject();

        if (event && event.type === 'bbai:stats-updated') {
            if (payload && payload.stats) {
                stats = payload.stats;
            } else if (event.detail && event.detail.stats) {
                stats = event.detail.stats;
            }
        }

        syncAndRender(stats, usage);
    });

    $(document).ready(function() {
        syncAndRender(
            (window.BBAI_DASH && window.BBAI_DASH.stats) || (window.BBAI && window.BBAI.stats) || null,
            bbaiGetUsageObject()
        );
        setTimeout(function() {
            syncAndRender(
                (window.BBAI_DASH && window.BBAI_DASH.stats) || (window.BBAI && window.BBAI.stats) || null,
                bbaiGetUsageObject()
            );
        }, 400);
    });
});
