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
                console.warn('[AltText AI] jQuery not found; dashboard scripts not run.');
                warned = true;
            }
            return;
        }
        callback(jq);
    };
})();

bbaiRunWithJQuery(function($) {
    'use strict';

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

    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('[AltText AI] jQuery ready - setting up upgrade modal handlers');
        
        // Load license site usage if on agency license (delay for Admin tab)
        setTimeout(function() {
            if (typeof loadLicenseSiteUsage === 'function') {
                loadLicenseSiteUsage();
            }
        }, 500);
        
        // Check if we should open portal after login
        const shouldOpenPortal = localStorage.getItem('bbai_open_portal_after_login');
        
        if (shouldOpenPortal === 'true') {
            console.log('[AltText AI] Portal flag found, checking authentication...');
            console.log('[AltText AI] Auth state:', {
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
                
                console.log('[AltText AI] Portal check attempt', checkCount, {
                    isAuthenticated: isAuthenticated,
                    authValue: window.bbai_ajax?.is_authenticated
                });
                
                if (isAuthenticated) {
                    clearInterval(checkInterval);
                    // Clear the flag
                    localStorage.removeItem('bbai_open_portal_after_login');
                    
                    console.log('[AltText AI] User authenticated, opening portal after login');
                    
                    // Set a flag to indicate we're opening after login (to prevent modal from showing)
                    sessionStorage.setItem('bbai_portal_after_login', 'true');
                    
                    // Small delay to ensure everything is ready
                    setTimeout(function() {
                        console.log('[AltText AI] Opening portal now...');
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
                    console.warn('[AltText AI] User not authenticated after multiple checks, clearing portal flag');
                }
            }, 200); // Check every 200ms
        }
        
        // Handle upgrade CTA: use new pricing modal
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[AltText AI] Upgrade CTA clicked via jQuery handler', this);
            
            // Use new pricing modal if available
            if (typeof window.openPricingModal === 'function') {
                window.openPricingModal('enterprise');
            } else if (typeof bbaiApp && typeof bbaiApp.showModal === 'function') {
                // Fallback to old modal
                bbaiApp.showModal();
            } else if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal(); // Legacy fallback
            } else {
                console.error('[AltText AI] Pricing modal not available!');
                // Direct fallback
                const modal = document.getElementById('bbai-upgrade-modal');
                if (modal) {
                    modal.removeAttribute('style');
                    modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important;';
                } else {
                    window.bbaiModal.warning('Upgrade modal not found. Please refresh the page.');
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
                        window.bbaiModal.warning('Please log in first to manage your subscription.');
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
                        window.bbaiModal.warning('Please log in first to manage your subscription.');
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
                console.log('[AltText AI] Already processing, ignoring click');
                return false;
            }
            
            $btn.addClass('bbai-processing').prop('disabled', true);
            
            console.log('[AltText AI] Manage subscription clicked');
            
            // Check if user is authenticated - check multiple sources
            const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
            const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
            
            // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
            const isAdminTab = $('.bbai-admin-content').length > 0;
            const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
            
            const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
            
            console.log('[AltText AI] Authentication check:', {
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
                console.log('[AltText AI] User not authenticated, showing login modal');
                
                // Set a flag to open portal after successful login
                localStorage.setItem('bbai_open_portal_after_login', 'true');
                
                // Show login modal - try multiple methods
                let modalShown = false;
                
                if (typeof window.authModal !== 'undefined' && window.authModal) {
                    if (typeof window.authModal.show === 'function') {
                        console.log('[AltText AI] Using window.authModal.show()');
                        window.authModal.show();
                        if (typeof window.authModal.showLoginForm === 'function') {
                            window.authModal.showLoginForm();
                        }
                        modalShown = true;
                    }
                }
                
                if (!modalShown && typeof showAuthModal === 'function') {
                    console.log('[AltText AI] Using showAuthModal() function');
                    showAuthModal('login');
                    modalShown = true;
                }
                
                if (!modalShown) {
                    // Fallback: try to show auth modal manually
                    console.log('[AltText AI] Trying manual modal show');
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
                    console.error('[AltText AI] Could not show auth modal');
                    window.bbaiModal.warning('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.');
                }
                
                return false;
            }
            
            // User is authenticated, open portal directly
            console.log('[AltText AI] User authenticated, opening portal');
            openCustomerPortal();
        });

        $(document).on('click', '[data-action="disconnect-account"]', function(e) {
            e.preventDefault();
            if (!confirm('Disconnect this account for all WordPress users? You can reconnect at any time.')) {
                return;
            }
            disconnectAccount($(this));
        });

        // Handle logout button (consolidated disconnect/logout)
        $(document).on('click', '[data-action="logout"]', function(e) {
            e.preventDefault();
            const $button = $(this);
            
            if (alttextaiDebug) console.log('[AltText AI] Logout button clicked');
            
            if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
                window.bbaiModal.error('Configuration error. Please refresh the page.');
                return;
            }

            // Show loading state
            $button.prop('disabled', true)
                   .addClass('bbai-btn-loading')
                   .attr('aria-busy', 'true');
            
            const originalText = $button.text();
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
                    if (alttextaiDebug) console.log('[AltText AI] Logout response:', response);
                    
                    if (response.success) {
                        // Show success message briefly
                        $button.removeClass('bbai-btn-loading')
                               .html('✓ Logged out')
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
                        
                        const errorMsg = response.data?.message || 'Failed to log out. Please try again.';
                        window.bbaiModal.error(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    if (alttextaiDebug) console.error('[AltText AI] Logout error:', status, error);
                    
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('bbai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
                    let errorMessage = 'Unable to log out. Please try again.';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Try refreshing the page.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection and try again.';
                    }
                    
                    window.bbaiModal.error(errorMessage);
                }
            });
        });

        // Handle checkout plan buttons in upgrade modal
        $(document).on('click', '[data-action="checkout-plan"]', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const plan = $btn.attr('data-plan');
            const priceId = $btn.attr('data-price-id');
            const fallbackUrl = $btn.attr('data-fallback-url');
            
            if (alttextaiDebug) console.log('[AltText AI] Checkout plan:', plan, priceId);
            
            // Try to use backend checkout API if we have a price ID
            // This provides better tracking, custom success URLs, and account linking
            if (priceId && window.bbai_ajax && window.bbai_ajax.ajaxurl) {
                // Use backend checkout session API
                initiateCheckout($btn, priceId, plan);
            } else {
                // Fall back to direct Stripe payment link if no price ID or AJAX not available
                if (fallbackUrl) {
                    if (alttextaiDebug) console.log('[AltText AI] Using fallback Stripe payment link');
                    window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                    // Close the upgrade modal after opening the payment link
                    if (typeof alttextaiCloseModal === 'function') {
                        alttextaiCloseModal();
                    }
                } else {
                    window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
                }
            }
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
                const modal = document.getElementById('bbai-upgrade-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                } else {
                    if (alttextaiDebug) console.error('[AltText AI] Upgrade modal not found in DOM');
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
            if (alttextaiDebug) console.error('[AltText AI] Error in handleUpgradeTrigger:', err);
            // Fallback: try to show modal directly
            try {
                const modal = document.getElementById('bbai-upgrade-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }
            } catch (e) {
                if (alttextaiDebug) console.error('[AltText AI] Failed to show modal:', e);
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
            showSubscriptionError('Configuration error. Please refresh the page.');
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
                        let errorMessage = response.data?.message || 'Failed to load subscription information.';
                        
                        if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                            errorMessage = 'Please log in to view your subscription information.';
                        } else if (errorMessage.toLowerCase().includes('not found')) {
                            errorMessage = 'Subscription not found. If you just upgraded, please wait a moment and refresh.';
                        }
                        
                        showSubscriptionError(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                $loading.hide();
                
                // Provide better error messages
                let errorMessage = 'Network error. Please try again.';
                
                if (xhr.status === 401 || xhr.status === 403) {
                    errorMessage = 'Please log in to view your subscription information.';
                    showSubscriptionError(errorMessage);
                    return; // Don't retry auth errors
                } else if (xhr.status === 404) {
                    errorMessage = 'Subscription information not found. If you just signed up, please wait a moment and refresh.';
                    showSubscriptionError(errorMessage);
                    return; // Don't retry 404 errors
                } else if (xhr.status >= 500 || status === 'timeout' || status === 'error') {
                    errorMessage = 'Service temporarily unavailable. Retrying automatically...';
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
            showSubscriptionError('Service unavailable after multiple attempts. Please try again later or refresh the page.');
            retryAttempts = 0;
                return;
            }

        retryAttempts++;
        
        // Exponential backoff: 1s, 2s, 4s, 8s, 16s
        const delay = Math.min(1000 * Math.pow(2, retryAttempts - 1), 16000);
        
        const $error = $('#bbai-subscription-error');
        $error.find('.bbai-error-message').text(
            'Service temporarily unavailable. Retrying in ' + Math.ceil(delay / 1000) + ' seconds... (Attempt ' + retryAttempts + '/' + maxRetries + ')'
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
            console.warn('[AltText AI] Could not cache subscription info:', e);
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
            console.warn('[AltText AI] Could not read subscription cache:', e);
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
            console.log('[AltText AI] License sites content element not found');
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
            console.log('[AltText AI] Not authenticated and not on license page, skipping license sites load');
            return; // Don't load if not authenticated and not on license page
        }

        console.log('[AltText AI] Loading license site usage...', {
            isAuthenticated: isAuthenticated,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated
        });

        // Show loading state
        $sitesContent.html(
            '<div class="bbai-settings-license-sites-loading">' +
            '<span class="bbai-spinner"></span> ' +
            'Loading site usage...' +
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
                console.log('[AltText AI] License sites response:', response);
                if (response.success && response.data) {
                    // Handle both array response and object with sites property
                    const sites = Array.isArray(response.data) ? response.data : (response.data.sites || []);
                    if (sites && sites.length > 0) {
                        displayLicenseSites(sites);
                    } else {
                        $sitesContent.html(
                            '<div class="bbai-settings-license-sites-empty">' +
                            '<p>No sites are currently using this license.</p>' +
                            '</div>'
                        );
                    }
                } else {
                    console.error('[AltText AI] License sites request failed:', response);
                    $sitesContent.html(
                        '<div class="bbai-settings-license-sites-error">' +
                        '<p>' + (response.data?.message || 'Failed to load site usage. Please try again.') + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('[AltText AI] Failed to load license sites:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                $sitesContent.html(
                    '<div class="bbai-settings-license-sites-error">' +
                    '<p>Failed to load site usage. Please refresh the page and try again.</p>' +
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
                '<p>No sites are currently using this license.</p>' +
                '</div>'
            );
            return;
        }

        let html = '<div class="bbai-settings-license-sites-list">';
        html += '<div class="bbai-settings-license-sites-summary">';
        html += '<strong>' + sites.length + '</strong> site' + (sites.length !== 1 ? 's' : '') + ' using this license';
        html += '</div>';
        html += '<ul class="bbai-settings-license-sites-items">';

        sites.forEach(function(site) {
            const siteName = site.site_name || site.install_id || 'Unknown Site';
            const siteId = site.siteId || site.install_id || site.installId || '';
            const generations = site.total_generations || site.generations || 0;
            const lastUsed = site.last_used ? new Date(site.last_used).toLocaleDateString() : 'Never';
            
            html += '<li class="bbai-settings-license-sites-item">';
            html += '<div class="bbai-settings-license-sites-item-main">';
            html += '<div class="bbai-settings-license-sites-item-info">';
            html += '<div class="bbai-settings-license-sites-item-name">' + escapeHtml(siteName) + '</div>';
            html += '<div class="bbai-settings-license-sites-item-stats">';
            html += '<span class="bbai-settings-license-sites-item-generations">';
            html += '<strong>' + generations.toLocaleString() + '</strong> alt text generated';
            html += '</span>';
            html += '<span class="bbai-settings-license-sites-item-last">';
            html += 'Last used: ' + escapeHtml(lastUsed);
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="bbai-settings-license-sites-item-actions">';
            html += '<button type="button" class="bbai-settings-license-sites-disconnect-btn" ';
            html += 'data-site-id="' + escapeHtml(siteId) + '" ';
            html += 'data-site-name="' + escapeHtml(siteName) + '" ';
            html += 'aria-label="Disconnect ' + escapeHtml(siteName) + '">';
            html += '<svg width="16" height="16" viewBox="0 0 16 16" fill="none">';
            html += '<path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>';
            html += '</svg>';
            html += '<span>Disconnect</span>';
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
                console.error('[AltText AI] No site ID provided for disconnect');
                return;
            }
            
            // Confirm disconnect action
            // For confirm dialog, we need plain text (not HTML-escaped)
            const siteNameText = siteName.replace(/"/g, '&quot;').replace(/\n/g, ' ');
            if (!confirm('Are you sure you want to disconnect "' + siteNameText + '"?\n\nThis will remove the site from your license. The site will need to reconnect using the license key.')) {
                return;
            }
            
            // Disable button and show loading state
            $btn.prop('disabled', true)
                .addClass('bbai-processing')
                .html('<span class="bbai-spinner"></span> Disconnecting...');
            
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
                        console.log('[AltText AI] Site disconnected successfully:', siteName);
                        
                        // Reload the license sites list
                        loadLicenseSiteUsage();
                    } else {
                        // Show error message
                        window.bbaiModal.error('Failed to disconnect site: ' + (response.data?.message || 'Unknown error'));
                        
                        // Restore button
                        $btn.prop('disabled', false)
                            .removeClass('bbai-processing')
                            .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Disconnect</span>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[AltText AI] Failed to disconnect site:', error);
                    window.bbaiModal.error('Failed to disconnect site. Please try again.');
                    
                    // Restore button
                    $btn.prop('disabled', false)
                        .removeClass('bbai-processing')
                        .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Disconnect</span>');
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
        if (alttextaiDebug) console.log('[AltText AI] Opening customer portal...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
                    return;
                }
        
        // Check authentication before making the request - check multiple sources
        const ajaxAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
        const userDataAuth = window.bbai_ajax && window.bbai_ajax.user_data && Object.keys(window.bbai_ajax.user_data).length > 0;
        
        // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
        const isAdminTab = $('.bbai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
        
        const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
        
        console.log('[AltText AI] openCustomerPortal() - auth check:', {
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
                console.log('[AltText AI] Portal opened after login but auth check failed - waiting for auth state...');
                // Wait a bit and retry
                setTimeout(function() {
                    const retryAuth = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;
                    if (retryAuth) {
                        console.log('[AltText AI] Auth state now ready, retrying portal...');
                        openCustomerPortal();
                    } else {
                        console.error('[AltText AI] Auth state still not ready after retry');
                        sessionStorage.removeItem('bbai_portal_after_login');
                    }
                }, 1000);
                return;
            }
            
            console.log('[AltText AI] Not authenticated in openCustomerPortal, showing login modal');
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
                window.bbaiModal.warning('Please log in first to manage your subscription.\n\nUse the "Login" button in the header.');
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
            $btn.html('<span class="bbai-spinner"></span> Opening portal...');
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
                if (alttextaiDebug) console.log('[AltText AI] Portal response:', response);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || 'Manage Subscription');
                });

                if (response.success && response.data && response.data.url) {
                    if (alttextaiDebug) console.log('[AltText AI] Opening portal URL:', response.data.url);
                    
                    // Open portal in new tab
                    const portalWindow = window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    
                    if (!portalWindow) {
                        window.bbaiModal.warning('Please allow popups for this site to manage your subscription.');
                        return;
                    }
                    
                    // Monitor for user return and refresh subscription data
                    let checkCount = 0;
                    const maxChecks = 150; // 5 minutes max
                    
                    const checkInterval = setInterval(function() {
                        checkCount++;
                        if (document.hasFocus() || checkCount >= maxChecks) {
                            clearInterval(checkInterval);
                            if (alttextaiDebug) console.log('[AltText AI] User returned, refreshing data...');
                            
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
                    let errorMessage = response.data?.message || 'Failed to open customer portal. Please try again.';
                    
                    console.log('[AltText AI] Portal request failed:', errorMessage);
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login') || errorMessage.toLowerCase().includes('authentication required')) {
                        // This shouldn't happen since we check auth first, but handle it gracefully
                        console.log('[AltText AI] Authentication error from server, showing login modal');
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
                            window.bbaiModal.warning('Please log in first to manage your billing.\n\nClick the "Login" button in the top navigation.');
                        }
                    } else if (errorMessage.toLowerCase().includes('not found') || errorMessage.toLowerCase().includes('subscription')) {
                        errorMessage = 'No active subscription found.\n\nPlease upgrade to a paid plan first, then you can manage your subscription.';
                        window.bbaiModal.error(errorMessage);
                    } else if (errorMessage.toLowerCase().includes('customer')) {
                        errorMessage = 'Unable to find your billing account.\n\nPlease contact support for assistance.';
                        window.bbaiModal.error(errorMessage);
                    } else {
                    window.bbaiModal.error(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Portal error:', status, error, xhr);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('bbai-btn-loading')
                        .attr('aria-busy', 'false');
                
                // Restore original text
                $buttons.each(function() {
                    const $btn = $(this);
                    const key = $btn.attr('id') || 'btn';
                    $btn.text(originalText[key] || 'Manage Subscription');
                });
                
                // Provide helpful error message based on status
                let errorMessage = 'Unable to connect to billing system. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please check your internet connection and try again.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network connection lost. Please check your internet and try again.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Billing system is temporarily unavailable. Please try again in a few minutes.';
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
        if (alttextaiDebug) console.log('[AltText AI] Initiating checkout:', planName, priceId);
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('bbai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalHtml = $button.html();
        $button.html('<span class="bbai-spinner"></span> Loading checkout...');

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_create_checkout',
                nonce: window.bbai_ajax.nonce,
                price_id: priceId
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Checkout response:', response);
                
                // Restore button state
                $button.prop('disabled', false)
                       .removeClass('bbai-btn-loading')
                       .html(originalHtml)
                       .attr('aria-busy', 'false');

                if (response.success && response.data && response.data.url) {
                    if (alttextaiDebug) console.log('[AltText AI] Opening checkout URL:', response.data.url);
                    
                    // Open checkout in new tab
                    const checkoutWindow = window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    
                    if (!checkoutWindow) {
                        window.bbaiModal.warning('Please allow popups for this site to complete checkout.');
                        return;
                    }
                    
                    // Close the upgrade modal
                    alttextaiCloseModal();
                    
                    // Monitor for successful checkout (user returns to success page)
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('checkout') === 'success') {
                        // Refresh data after successful checkout
                        if (typeof window.alttextai_refresh_usage === 'function') {
                            window.alttextai_refresh_usage();
                        }
                    }
                } else {
                    // Backend API failed - fall back to direct Stripe payment link
                    const fallbackUrl = $button.attr('data-fallback-url');
                    if (fallbackUrl) {
                        if (alttextaiDebug) console.log('[AltText AI] Backend checkout failed, using fallback Stripe link');
                        window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                        alttextaiCloseModal();
                    } else {
                        // No fallback available - show error
                        let errorMessage = response.data?.message || 'Failed to create checkout session. Please try again.';
                        const errorCode = response.data?.code || '';
                        
                        if (errorMessage.toLowerCase().includes('price')) {
                            errorMessage = 'Unable to load pricing information.\n\nPlease try again or contact support.';
                        }
                        
                        window.bbaiModal.error(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Checkout error:', status, error, xhr);
                
                // Restore button state
                $button.prop('disabled', false)
                       .removeClass('bbai-btn-loading')
                       .html(originalHtml)
                       .attr('aria-busy', 'false');
                
                // Try fallback to direct Stripe payment link
                const fallbackUrl = $button.attr('data-fallback-url');
                if (fallbackUrl) {
                    if (alttextaiDebug) console.log('[AltText AI] Backend checkout error, using fallback Stripe link');
                    window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                    alttextaiCloseModal();
                } else {
                    // No fallback available - show error
                    let errorMessage = 'Unable to connect to checkout system. Please try again.';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please check your internet connection and try again.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Network connection lost. Please check your internet and try again.';
                    } else if (xhr.status >= 500) {
                        errorMessage = 'Checkout system is temporarily unavailable. Please try again in a few minutes.';
                    }
                    
                    window.bbaiModal.error(errorMessage);
                }
            }
        });
    }

    /**
     * Disconnect Account
     * Clears authentication and allows another admin to connect
     */
    function disconnectAccount($button) {
        if (alttextaiDebug) console.log('[AltText AI] Disconnecting account...');
        
        if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('bbai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalText = $button.text();
        $button.html('<span class="bbai-spinner"></span> Disconnecting...');

        $.ajax({
            url: window.bbai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'beepbeepai_disconnect_account',
                nonce: window.bbai_ajax.nonce
            },
            timeout: 15000, // 15 second timeout
            success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Disconnect response:', response);
                
                if (response.success) {
                    // Show success message
                    $button.removeClass('bbai-btn-loading')
                           .removeClass('bbai-btn--ghost')
                           .addClass('bbai-btn--success')
                           .html('✓ Disconnected')
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
                    
                    const errorMsg = response.data?.message || 'Failed to disconnect account. Please try again.';
                    window.bbaiModal.error(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Disconnect error:', status, error);
                
                // Restore button
                $button.prop('disabled', false)
                       .removeClass('bbai-btn-loading')
                       .text(originalText)
                       .attr('aria-busy', 'false');
                
                // Provide helpful error message
                let errorMessage = 'Unable to disconnect account. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Your connection may still be disconnected. Try refreshing the page.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                }
                
                window.bbaiModal.error(errorMessage);
            }
        });
    }

});

// Debug mode check (define early so it can be used in functions)
var alttextaiDebug = (typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false;

// Check if modal exists when script loads
(function() {
    'use strict';
    function checkModalExists() {
        const modal = document.getElementById('bbai-upgrade-modal');
        if (!modal) {
            console.warn('[AltText AI] Upgrade modal not found in DOM. Make sure upgrade-modal.php is included.');
        } else {
            if (alttextaiDebug) console.log('[AltText AI] Upgrade modal found in DOM');
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

// Global functions for modal - make it very robust (legacy support)
function alttextaiShowModal() {
    console.log('[AltText AI] alttextaiShowModal() called');
    const modal = document.getElementById('bbai-upgrade-modal');
    
    if (!modal) {
        console.error('[AltText AI] Upgrade modal element not found in DOM!');
        console.error('[AltText AI] Searching for modal...');
        // Try to find it by class
        const byClass = document.querySelector('.bbai-modal-backdrop');
        if (byClass) {
            console.log('[AltText AI] Found modal by class:', byClass);
            byClass.id = 'bbai-upgrade-modal';
            return alttextaiShowModal(); // Retry
        }
        window.bbaiModal.warning('Upgrade modal not found. Please refresh the page.');
        return false;
    }
    
    console.log('[AltText AI] Modal element found:', modal);
    console.log('[AltText AI] Current display:', window.getComputedStyle(modal).display);
    
    // Remove the inline display:none style completely, then set to flex
    modal.removeAttribute('style');
    
    // Now set display to flex with !important
    modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.6) !important;';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    
    // Verify it worked
    setTimeout(function() {
        const computed = window.getComputedStyle(modal);
        console.log('[AltText AI] Modal computed display:', computed.display);
        console.log('[AltText AI] Modal computed visibility:', computed.visibility);
        console.log('[AltText AI] Modal computed z-index:', computed.zIndex);
        
        if (computed.display === 'none' || computed.visibility === 'hidden') {
            console.error('[AltText AI] Modal still not visible!');
            // Nuclear option - remove all classes and inline styles, rebuild
            modal.className = 'bbai-modal-backdrop';
            modal.style.cssText = '';
        modal.style.display = 'flex';
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.right = '0';
            modal.style.bottom = '0';
            modal.style.left = '0';
            modal.style.zIndex = '999999';
            modal.style.backgroundColor = 'rgba(0, 0, 0, 0.6)';
        } else {
            console.log('[AltText AI] ✓ Modal is now visible!');
        }
    }, 50);
    
    return true;
}

// Make it available globally for onclick handlers (legacy support)
window.alttextaiShowModal = alttextaiShowModal;

// Also add to bbaiApp namespace
bbaiApp.showModal = alttextaiShowModal;

// Also create a simple test function
window.testUpgradeModal = function() {
    console.log('=== Testing Upgrade Modal ===');
    const modal = document.getElementById('bbai-upgrade-modal');
    console.log('Modal element:', modal);
    if (modal) {
        console.log('Modal HTML:', modal.outerHTML.substring(0, 200));
        console.log('Modal classes:', modal.className);
        console.log('Modal inline style:', modal.getAttribute('style'));
        alttextaiShowModal();
    } else {
        console.error('Modal not found!');
    }
};

function alttextaiCloseModal() {
    const modal = document.getElementById('bbai-upgrade-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        // Restore body scroll
        document.body.style.overflow = '';
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
        const modal = document.getElementById('bbai-upgrade-modal');
        if (!modal) {
            console.warn('[AltText AI] Upgrade modal not found in DOM');
            return false;
        }
        
        // Remove inline style completely, then set with !important
        modal.removeAttribute('style');
        modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important; background-color: rgba(0, 0, 0, 0.6) !important;';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        console.log('[AltText AI] Modal shown via direct method');
        return true;
    }
    
    // Use event delegation on document to catch all clicks (capture phase for early handling)
    document.addEventListener('click', function(e) {
        // Check if clicked element or its parent has data-action="show-upgrade-modal"
        const trigger = e.target.closest('[data-action="show-upgrade-modal"]');
        if (trigger) {
            console.log('[AltText AI] Global vanilla JS handler: Upgrade CTA clicked', trigger);
            
            // Prevent default immediately
            e.preventDefault();
            e.stopPropagation();
            
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
                const modal = document.getElementById('bbai-upgrade-modal');
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }
            }
            return false;
        }
        
        // Handle backdrop clicks (click outside modal content to close)
        const modal = document.getElementById('bbai-upgrade-modal');
        if (modal && modal.style.display === 'flex' && e.target === modal) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            return false;
        }
    }, true); // Use capture phase to catch events early
    
    // Handle ESC key globally to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            const modal = document.getElementById('bbai-upgrade-modal');
            if (modal && modal.style.display === 'flex') {
                e.preventDefault();
                if (typeof alttextaiCloseModal === 'function') {
                    alttextaiCloseModal();
                } else {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }
            }
        }
    });
})();

function openStripeLink(url) {
    if (alttextaiDebug) console.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

function showAuthBanner() {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth banner');
    
    // Try to show the auth modal
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) console.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                        } else {
        // Try to find and show the auth modal directly
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            if (alttextaiDebug) console.log('[AltText AI] Showing auth modal directly');
            authModal.style.display = 'block';
        } else {
            if (alttextaiDebug) console.log('[AltText AI] Auth modal not found');
            window.bbaiModal.error('Authentication system not available. Please refresh the page.');
        }
    }
}

function showAuthLogin() {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth login');
    
    // Try multiple methods to show auth modal (check bbaiApp namespace first)
    if (window.bbaiApp && window.bbaiApp.authModal && typeof window.bbaiApp.authModal.show === 'function') {
        if (alttextaiDebug) console.log('[AltText AI] Using bbaiApp.authModal.show()');
        window.bbaiApp.authModal.show();
        return;
    } else if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
        // Legacy fallback
        if (alttextaiDebug) console.log('[AltText AI] Using AltTextAuthModal.show()');
        window.AltTextAuthModal.show();
        return;
    }

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) console.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
                    return;
                }

    // Fallback to showAuthBanner method
    showAuthBanner();
}

function showAuthModal(tab) {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth modal, tab:', tab);
    
    // Try to show auth modal with specific tab
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        if (alttextaiDebug) console.log('[AltText AI] Using authModal.show()');
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
    if (alttextaiDebug) console.log('[AltText AI] Handling logout');
    
    // Make AJAX request to logout
    if (typeof window.bbai_ajax === 'undefined') {
        console.error('[AltText AI] bbai_ajax object not found');
        // Still try to clear token and reload
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('alttextai_token');
        }
        window.location.reload();
                    return;
                }

    const ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || ajaxurl;
    const nonce = window.bbai_ajax.nonce || '';
    
    if (!ajaxUrl) {
        console.error('[AltText AI] AJAX URL not found');
        window.location.reload();
                    return;
                }

    if (alttextaiDebug) console.log('[AltText AI] Calling logout AJAX:', ajaxUrl);
    
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
                if (alttextaiDebug) console.log('[AltText AI] Logout successful', response);
                // Clear any local storage
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
            // Redirect to plugin dashboard/sign-up page after logout
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
                    },
                    error: function(xhr, status, error) {
                console.error('[AltText AI] Logout failed:', error, xhr.responseText);
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
        formData.append('action', 'alttextai_logout');
        formData.append('nonce', nonce);
        
        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (alttextaiDebug) console.log('[AltText AI] Logout response status:', response.status);
            return response.json().catch(() => ({}));
        })
        .then(data => {
            if (alttextaiDebug) console.log('[AltText AI] Logout successful', data);
            // Clear any local storage
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            const redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        })
        .catch(error => {
            console.error('[AltText AI] Logout failed:', error);
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
    if (alttextaiDebug) console.log('[AltText AI] DOM loaded, initializing auth system');
    
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
            if (alttextaiDebug) console.log('[AltText AI] Logout button clicked (Vanilla JS)');
            handleLogout();
        });
        if (alttextaiDebug) console.log('[AltText AI] Logout button found and listener attached');
    } else {
        if (alttextaiDebug) console.log('[AltText AI] Logout button not found');
    }
    
    // Check if auth modal exists and initialize it (debug only)
    if (alttextaiDebug) {
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            console.log('[AltText AI] Auth modal found');
        } else {
            console.log('[AltText AI] Auth modal not found - may need to be created');
        }
        
        // Check if authModal object exists
        if (typeof window.authModal !== 'undefined') {
            console.log('[AltText AI] authModal object found');
        } else {
            console.log('[AltText AI] authModal object not found');
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
        if (alttextaiDebug) console.log('[AltText AI] Countdown element not found');
        return; // No countdown on this page
    }

    const totalSeconds = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
    
    // Check if we have valid seconds
    if (totalSeconds <= 0) {
        if (alttextaiDebug) console.log('[AltText AI] Countdown has zero or invalid seconds:', totalSeconds);
        return;
    }

    const daysEl = countdownElement.querySelector('[data-days]');
    const hoursEl = countdownElement.querySelector('[data-hours]');
    const minutesEl = countdownElement.querySelector('[data-minutes]');

    if (!daysEl || !hoursEl || !minutesEl) {
        if (alttextaiDebug) console.warn('[AltText AI] Countdown elements not found', {
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
        console.log('[AltText AI] Countdown initialized:', {
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
        // Search functionality
        $('#bbai-library-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterLibraryRows(searchTerm, getActiveFilter());
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
                console.log('No matching rows found');
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

        var badgeClass = 'bbai-seo-quality-badge bbai-seo-quality-badge--' + quality.badge;
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
            if ($altText.parent().find('.bbai-seo-quality-badge').length === 0) {
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
