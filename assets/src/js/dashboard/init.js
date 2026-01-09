/**
 * Dashboard Initialization
 * Main entry point for dashboard functionality
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

bbaiRunWithJQuery(function($) {
    'use strict';

    // Upgrade trigger selectors for fallback binding
    var FALLBACK_UPGRADE_SELECTOR = [
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

    /**
     * Show upgrade modal directly
     */
    function showUpgradeModal() {
        var modal = document.getElementById('bbai-upgrade-modal');
        if (modal) {
            // Add active class to trigger CSS visibility
            modal.classList.add('active');
            modal.classList.add('is-visible');
            
            modal.removeAttribute('style');
            modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0,0,0,0.6) !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important;';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            return true;
        }
        return false;
    }

    /**
     * Bind fallback upgrade triggers
     */
    function bindFallbackUpgradeTriggers() {
        $(document).on('click', FALLBACK_UPGRADE_SELECTOR, function(e) {
            if ($(this).is('[data-action="show-upgrade-modal"]')) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            if (alttextaiDebug) console.log('[AltText AI] Fallback upgrade trigger clicked');

            if (!showUpgradeModal()) {
                if (typeof window.openPricingModal === 'function') {
                    window.openPricingModal('enterprise');
                }
            }

            return false;
        });
    }

    /**
     * Ensure upgrade elements have proper attributes
     */
    function ensureUpgradeAttributes() {
        $(FALLBACK_UPGRADE_SELECTOR).each(function() {
            var $el = $(this);
            if (!$el.attr('data-action')) {
                $el.attr('data-action', 'show-upgrade-modal');
            }
        });
    }

    /**
     * Observe for dynamically added upgrade triggers
     */
    function observeFutureUpgradeTriggers() {
        if (typeof MutationObserver === 'undefined') return;

        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            var $node = $(node);
                            if ($node.is(FALLBACK_UPGRADE_SELECTOR)) {
                                if (!$node.attr('data-action')) {
                                    $node.attr('data-action', 'show-upgrade-modal');
                                }
                            }
                            $node.find(FALLBACK_UPGRADE_SELECTOR).each(function() {
                                if (!$(this).attr('data-action')) {
                                    $(this).attr('data-action', 'show-upgrade-modal');
                                }
                            });
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Bind direct upgrade handlers on specific elements
     */
    function bindDirectUpgradeHandlers() {
        var directSelectors = [
            '.bbai-upgrade-cta-card button',
            '.bbai-upgrade-cta-card a',
            '.bbai-pro-upsell-card button'
        ];

        directSelectors.forEach(function(selector) {
            $(document).on('click', selector, function(e) {
                if ($(this).is('[data-action="show-upgrade-modal"]')) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                if (alttextaiDebug) console.log('[AltText AI] Direct upgrade handler fired for:', selector);

                if (!showUpgradeModal()) {
                    if (typeof window.openPricingModal === 'function') {
                        window.openPricingModal('enterprise');
                    }
                }

                return false;
            });
        });
    }

    /**
     * Handle manage subscription click
     */
    function handleManageSubscription($btn) {
        if ($btn.hasClass('bbai-processing') || $btn.prop('disabled')) {
            return false;
        }

        $btn.addClass('bbai-processing').prop('disabled', true);

        var ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
        var userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
        var isAdminTab = $('.bbai-admin-content').length > 0;
        var isAuthenticated = ajaxAuth || userDataAuth || isAdminTab;

        setTimeout(function() {
            $btn.removeClass('bbai-processing').prop('disabled', false);
        }, 1000);

        if (!isAuthenticated) {
            localStorage.setItem('bbai_open_portal_after_login', 'true');

            if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                window.authModal.show();
                if (typeof window.authModal.showLoginForm === 'function') {
                    window.authModal.showLoginForm();
                }
            } else if (typeof showAuthModal === 'function') {
                showAuthModal('login');
            } else {
                var authModal = document.getElementById('alttext-auth-modal');
                if (authModal) {
                    authModal.style.display = 'block';
                    authModal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                } else {
                    window.bbaiModal.warning('Please log in first to manage your subscription.');
                }
            }
            return false;
        }

        openCustomerPortal();
    }

    /**
     * Handle logout
     */
    function handleLogoutAction($button) {
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
            return;
        }

        $button.prop('disabled', true)
               .addClass('bbai-btn-loading')
               .attr('aria-busy', 'true');

        var originalText = $button.text();
        $button.html('<span class="bbai-spinner"></span> Logging out...');

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_logout',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 15000,
            success: function(response) {
                if (response.success) {
                    $button.removeClass('bbai-btn-loading')
                           .html('<span class="dashicons dashicons-yes"></span> Logged out');
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .attr('aria-busy', 'false')
                           .text(originalText);
                    window.bbaiModal.error(response.data?.message || 'Logout failed. Please try again.');
                }
            },
            error: function() {
                $button.prop('disabled', false)
                       .removeClass('bbai-btn-loading')
                       .attr('aria-busy', 'false')
                       .text(originalText);
                window.bbaiModal.error('Unable to log out. Please try again.');
            }
        });
    }

    /**
     * Disconnect account
     */
    function disconnectAccount($button) {
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
            return;
        }

        $button.prop('disabled', true).addClass('bbai-btn-loading');
        var originalText = $button.text();
        $button.html('<span class="bbai-spinner"></span> Disconnecting...');

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_disconnect_account',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 15000,
            success: function(response) {
                if (response.success) {
                    window.bbaiModal.success('Account disconnected. Refreshing...');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText);
                    window.bbaiModal.error(response.data?.message || 'Failed to disconnect. Please try again.');
                }
            },
            error: function() {
                $button.prop('disabled', false)
                       .removeClass('bbai-btn-loading')
                       .text(originalText);
                window.bbaiModal.error('Unable to disconnect. Please try again.');
            }
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        if (alttextaiDebug) console.log('[AltText AI] Dashboard initializing');

        // Load license site usage if on agency license
        setTimeout(function() {
            if (typeof loadLicenseSiteUsage === 'function') {
                loadLicenseSiteUsage();
            }
        }, 500);

        // Check if we should open portal after login
        checkPortalAfterLogin();

        // Initialize countdown timer
        if (typeof initCountdownTimer === 'function') {
            initCountdownTimer();
        }

        // Bind upgrade triggers
        bindFallbackUpgradeTriggers();
        ensureUpgradeAttributes();
        observeFutureUpgradeTriggers();
        bindDirectUpgradeHandlers();

        // Handle upgrade CTA
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!showUpgradeModal()) {
                if (typeof window.openPricingModal === 'function') {
                    window.openPricingModal('enterprise');
                } else if (typeof bbaiApp !== 'undefined' && typeof bbaiApp.showModal === 'function') {
                    bbaiApp.showModal();
                } else if (typeof alttextaiShowModal === 'function') {
                    alttextaiShowModal();
                } else {
                    if (typeof window.bbaiModal !== 'undefined') {
                        window.bbaiModal.warning('Upgrade modal not found. Please refresh the page.');
                    }
                }
            }

            return false;
        });

        // Handle auth banner button
        $(document).on('click', '#bbai-show-auth-banner-btn', function(e) {
            e.preventDefault();
            if (typeof showAuthBanner === 'function') {
                showAuthBanner();
            }
        });

        // Handle auth login button
        $(document).on('click', '#bbai-show-auth-login-btn', function(e) {
            e.preventDefault();
            if (typeof showAuthLogin === 'function') {
                showAuthLogin();
            }
        });

        // Handle logout button
        $(document).on('click', '#bbai-logout-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof handleLogout === 'function') {
                handleLogout();
            }
        });

        // Handle show auth modal button
        $(document).on('click', '[data-action="show-auth-modal"]', function(e) {
            e.preventDefault();
            var authTab = $(this).attr('data-auth-tab') || 'login';
            if (typeof showAuthModal === 'function') {
                showAuthModal(authTab);
            }
        });

        // Handle billing portal
        $(document).on('click', '[data-action="open-billing-portal"]', function(e) {
            e.preventDefault();
            var isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated;

            if (isAuthenticated) {
                openCustomerPortal();
            } else {
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                }
            }
        });

        // Handle demo signup buttons
        $(document).on('click', '#bbai-demo-signup-btn, #bbai-settings-signup-btn', function(e) {
            e.preventDefault();
            if (typeof showAuthLogin === 'function') {
                showAuthLogin();
            }
        });

        // Load subscription info if on Settings page
        if ($('#bbai-account-management').length) {
            if (typeof loadSubscriptionInfo === 'function') {
                loadSubscriptionInfo();
            }
        }

        // Handle account management buttons
        $(document).on('click', '#bbai-update-payment-method, #bbai-manage-subscription', function(e) {
            e.preventDefault();
            handleManageSubscription($(this));
        });

        // Handle manage subscription action
        $(document).on('click', '[data-action="manage-subscription"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleManageSubscription($(this));
        });

        // Handle disconnect account
        $(document).on('click', '[data-action="disconnect-account"]', function(e) {
            e.preventDefault();
            if (!confirm('Disconnect this account for all WordPress users? You can reconnect at any time.')) {
                return;
            }
            disconnectAccount($(this));
        });

        // Handle logout button
        $(document).on('click', '[data-action="logout"]', function(e) {
            e.preventDefault();
            handleLogoutAction($(this));
        });

        if (alttextaiDebug) console.log('[AltText AI] Dashboard initialized');
    });
});
