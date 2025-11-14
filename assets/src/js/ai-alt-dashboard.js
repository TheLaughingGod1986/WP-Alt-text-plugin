/**
 * AI Alt Text Dashboard JavaScript
 * Handles upgrade modal, auth buttons, and Stripe integration
 */

(function($) {
    'use strict';

    // Cache commonly used DOM elements (performance optimization)
    var $cachedElements = {};
    const FALLBACK_UPGRADE_SELECTOR = [
        '.alttextai-upgrade-link',
        '.alttextai-upgrade-inline',
        '.alttextai-upgrade-cta-card button',
        '.alttextai-upgrade-cta-card a',
        '.alttextai-pro-upsell-card button',
        '.alttextai-upgrade-banner button',
        '.alttextai-upgrade-banner a',
        '.alttextai-hero-actions [data-cta="upgrade"]',
        '.alttextai-upgrade-callout button',
        '.alttextai-upgrade-callout a',
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
        const shouldOpenPortal = localStorage.getItem('alttextai_open_portal_after_login');
        
        if (shouldOpenPortal === 'true') {
            console.log('[AltText AI] Portal flag found, checking authentication...');
            console.log('[AltText AI] Auth state:', {
                hasAjax: !!window.alttextai_ajax,
                isAuthenticated: window.alttextai_ajax?.is_authenticated,
                ajaxObject: window.alttextai_ajax
            });
            
            // Wait a bit longer for authentication state to be set
            // Check multiple times since the state might not be ready immediately
            let checkCount = 0;
            const maxChecks = 10;
            const checkInterval = setInterval(function() {
                checkCount++;
                const isAuthenticated = window.alttextai_ajax && window.alttextai_ajax.is_authenticated === true;
                
                console.log('[AltText AI] Portal check attempt', checkCount, {
                    isAuthenticated: isAuthenticated,
                    authValue: window.alttextai_ajax?.is_authenticated
                });
                
                if (isAuthenticated) {
                    clearInterval(checkInterval);
                    // Clear the flag
                    localStorage.removeItem('alttextai_open_portal_after_login');
                    
                    console.log('[AltText AI] User authenticated, opening portal after login');
                    
                    // Set a flag to indicate we're opening after login (to prevent modal from showing)
                    sessionStorage.setItem('alttextai_portal_after_login', 'true');
                    
                    // Small delay to ensure everything is ready
                    setTimeout(function() {
                        console.log('[AltText AI] Opening portal now...');
                        openCustomerPortal();
                        
                        // Clear the session flag after a delay
                        setTimeout(function() {
                            sessionStorage.removeItem('alttextai_portal_after_login');
                        }, 2000);
                    }, 300);
                } else if (checkCount >= maxChecks) {
                    clearInterval(checkInterval);
                    // If still not authenticated after checks, clear the flag
                    localStorage.removeItem('alttextai_open_portal_after_login');
                    console.warn('[AltText AI] User not authenticated after multiple checks, clearing portal flag');
                }
            }, 200); // Check every 200ms
        }
        
        // Handle upgrade CTA: always show upgrade modal
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[AltText AI] Upgrade CTA clicked via jQuery handler', this);
            
            // Call the global function - it has all the logic
            if (typeof alttextaiShowModal === 'function') {
                alttextaiShowModal();
            } else {
                console.error('[AltText AI] alttextaiShowModal function not available!');
                // Direct fallback
                const modal = document.getElementById('alttextai-upgrade-modal');
                if (modal) {
                    modal.removeAttribute('style');
                    modal.style.cssText = 'display: flex !important; z-index: 999999 !important; position: fixed !important; inset: 0 !important;';
                } else {
                    alert('Upgrade modal not found. Please refresh the page.');
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
        $(document).on('click', '#alttextai-show-auth-banner-btn', function(e) {
            e.preventDefault();
            showAuthBanner();
        });
        
        // Handle auth login button
        $(document).on('click', '#alttextai-show-auth-login-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });
        
        // Handle logout button (jQuery)
        $(document).on('click', '#alttextai-logout-btn', function(e) {
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
            const isAuthenticated = window.alttextai_ajax && window.alttextai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
                openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('alttextai_open_portal_after_login', 'true');
                
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
                        alert('Please log in first to manage your subscription.');
                    }
                }
            }
        });
        
        // Handle demo signup buttons
        $(document).on('click', '#alttextai-demo-signup-btn, #alttextai-settings-signup-btn', function(e) {
            e.preventDefault();
            showAuthLogin();
        });

        // Load subscription info if on Settings page
        if ($('#alttextai-account-management').length) {
            loadSubscriptionInfo();
        }

        // Handle account management buttons
        $(document).on('click', '#alttextai-update-payment-method, #alttextai-manage-subscription', function(e) {
            e.preventDefault();
            
            // Check if user is authenticated
            const isAuthenticated = window.alttextai_ajax && window.alttextai_ajax.is_authenticated;
            
            if (isAuthenticated) {
                // User is authenticated, open portal directly
                openCustomerPortal();
            } else {
                // User not authenticated - show login modal first
                // Set a flag to open portal after successful login
                localStorage.setItem('alttextai_open_portal_after_login', 'true');
                
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
                        alert('Please log in first to manage your subscription.');
                    }
                }
            }
        });

        $(document).on('click', '[data-action="manage-subscription"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            
            // Prevent multiple clicks
            if ($btn.hasClass('alttextai-processing') || $btn.prop('disabled')) {
                console.log('[AltText AI] Already processing, ignoring click');
                return false;
            }
            
            $btn.addClass('alttextai-processing').prop('disabled', true);
            
            console.log('[AltText AI] Manage subscription clicked');
            
            // Check if user is authenticated - check multiple sources
            const ajaxAuth = window.alttextai_ajax && window.alttextai_ajax.is_authenticated === true;
            const userDataAuth = window.alttextai_ajax && window.alttextai_ajax.user_data && Object.keys(window.alttextai_ajax.user_data).length > 0;
            
            // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
            const isAdminTab = $('.alttextai-admin-content').length > 0;
            const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
            
            const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
            
            console.log('[AltText AI] Authentication check:', {
                hasAjax: !!window.alttextai_ajax,
                ajaxAuth: ajaxAuth,
                userDataAuth: userDataAuth,
                isAdminTab: isAdminTab,
                isAdminAuthenticated: isAdminAuthenticated,
                isAuthenticated: isAuthenticated,
                authValue: window.alttextai_ajax?.is_authenticated,
                userData: window.alttextai_ajax?.user_data
            });
            
            // Restore button state
            setTimeout(function() {
                $btn.removeClass('alttextai-processing').prop('disabled', false);
            }, 1000);
            
            if (!isAuthenticated) {
                // User not authenticated - show login modal first
                console.log('[AltText AI] User not authenticated, showing login modal');
                
                // Set a flag to open portal after successful login
                localStorage.setItem('alttextai_open_portal_after_login', 'true');
                
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
                    alert('Please log in first to manage your subscription. Use the "Login" button in the header.');
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

        // Handle checkout plan buttons in upgrade modal
        $(document).on('click', '[data-action="checkout-plan"]', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const plan = $btn.attr('data-plan');
            const priceId = $btn.attr('data-price-id');
            const fallbackUrl = $btn.attr('data-fallback-url');
            
            if (alttextaiDebug) console.log('[AltText AI] Checkout plan:', plan, priceId);
            
            // Check if authenticated and has price ID from API
            const isAuthenticated = window.alttextai_ajax && window.alttextai_ajax.is_authenticated;
            
            if (isAuthenticated && priceId) {
                // Use backend checkout session API
                initiateCheckout($btn, priceId, plan);
            } else {
                // Fall back to direct Stripe link
                if (fallbackUrl) {
                    window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                } else {
                    alert('Unable to initiate checkout. Please try again or contact support.');
                }
            }
        });

        // Handle retry subscription fetch
        $(document).on('click', '#alttextai-retry-subscription', function(e) {
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
                const modal = document.getElementById('alttextai-upgrade-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                } else {
                    if (alttextaiDebug) console.error('[AltText AI] Upgrade modal not found in DOM');
                    // Last resort: check if user is authenticated and show auth modal
                    var isAuthed = false;
                    if (typeof window.alttextai_ajax !== 'undefined' && typeof window.alttextai_ajax.is_authenticated !== 'undefined') {
                        isAuthed = !!window.alttextai_ajax.is_authenticated;
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
                const modal = document.getElementById('alttextai-upgrade-modal');
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
        const $loading = getCachedElement('#alttextai-subscription-loading');
        const $error = getCachedElement('#alttextai-subscription-error');
        const $info = getCachedElement('#alttextai-subscription-info');
        const $freeMessage = getCachedElement('#alttextai-free-plan-message');

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

        if (!window.alttextai_ajax || !window.alttextai_ajax.ajaxurl) {
            showSubscriptionError('Configuration error. Please refresh the page.');
                return;
            }

        $.ajax({
            url: window.alttextai_ajax.ajaxurl,
            type: 'POST',
                data: {
                action: 'alttextai_get_subscription_info',
                nonce: window.alttextai_ajax.nonce
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
        const $info = getCachedElement('#alttextai-subscription-info');
        const $error = getCachedElement('#alttextai-subscription-error');
        const $freeMessage = getCachedElement('#alttextai-free-plan-message');

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
        const statusBadge = getCachedElement('#alttextai-status-badge');
        const statusLabel = statusBadge.find('.alttextai-status-label');
        
        statusBadge.removeClass('alttextai-status-active alttextai-status-cancelled alttextai-status-trial');
        statusBadge.addClass('alttextai-status-' + status);
        statusLabel.text(status.charAt(0).toUpperCase() + status.slice(1));

        // Show cancel warning if needed
        const $cancelWarning = getCachedElement('#alttextai-cancel-warning');
        if (data.cancelAtPeriodEnd) {
            $cancelWarning.show();
                } else {
            $cancelWarning.hide();
        }

        // Display plan details (using cached elements)
        const planName = data.plan ? data.plan.charAt(0).toUpperCase() + data.plan.slice(1) : '-';
        getCachedElement('#alttextai-plan-name').text(planName);

        const billingCycle = data.billingCycle ? data.billingCycle.charAt(0).toUpperCase() + data.billingCycle.slice(1) : '-';
        getCachedElement('#alttextai-billing-cycle').text(billingCycle);

        // Format next billing date
        if (data.nextBillingDate) {
            const date = new Date(data.nextBillingDate);
            getCachedElement('#alttextai-next-billing').text(date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            }));
            } else {
            getCachedElement('#alttextai-next-billing').text('-');
        }

        // Format next charge amount
        if (data.nextChargeAmount !== undefined && data.nextChargeAmount !== null) {
            const currency = data.currency || 'GBP';
            const symbol = currency === 'USD' ? '$' : currency === 'EUR' ? '€' : '£';
            getCachedElement('#alttextai-next-charge').text(symbol + parseFloat(data.nextChargeAmount).toFixed(2));
            } else {
            getCachedElement('#alttextai-next-charge').text('-');
        }

        // Display payment method if available (using cached elements)
        if (data.paymentMethod && data.paymentMethod.last4) {
            const $paymentMethod = getCachedElement('#alttextai-payment-method');
            const brand = data.paymentMethod.brand || 'card';
            const last4 = data.paymentMethod.last4;
            const expMonth = data.paymentMethod.expMonth;
            const expYear = data.paymentMethod.expYear;

            getCachedElement('#alttextai-card-brand').text(getCardBrandIcon(brand) + ' ' + brand.toUpperCase());
            getCachedElement('#alttextai-card-last4').text('•••• ' + last4);
            if (expMonth && expYear) {
                getCachedElement('#alttextai-card-expiry').text(expMonth + '/' + expYear.toString().slice(-2));
            }
            $paymentMethod.show();
        } else {
            getCachedElement('#alttextai-payment-method').hide();
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
        
        const $error = $('#alttextai-subscription-error');
        $error.find('.alttextai-error-message').text(
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
        const $error = $('#alttextai-subscription-error');
        const $info = $('#alttextai-subscription-info');
        const $freeMessage = $('#alttextai-free-plan-message');

        $error.find('.alttextai-error-message').text(message);
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
            localStorage.setItem('alttextai_subscription_cache', JSON.stringify(cacheData));
        } catch (e) {
            console.warn('[AltText AI] Could not cache subscription info:', e);
        }
    }

    /**
     * Get cached subscription info if still valid
     */
    function getCachedSubscriptionInfo() {
        try {
            const cached = localStorage.getItem('alttextai_subscription_cache');
            if (!cached) return null;

            const cacheData = JSON.parse(cached);
            const age = Date.now() - cacheData.timestamp;

            if (age < cacheData.expiry) {
                return cacheData;
            } else {
                // Cache expired, remove it
                localStorage.removeItem('alttextai_subscription_cache');
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
        const $sitesContent = $('#alttextai-license-sites-content');
        if (!$sitesContent.length) {
            console.log('[AltText AI] License sites content element not found');
            return; // Not on settings page or not agency license
        }

        // Check if user is authenticated (JWT) or has active license (license key auth)
        const isAuthenticated = window.alttextai_ajax && (
            window.alttextai_ajax.is_authenticated === true ||
            (window.alttextai_ajax.user_data && Object.keys(window.alttextai_ajax.user_data).length > 0)
        );

        // In Admin tab, check if admin is logged in (separate session)
        const isAdminTab = $('.alttextai-admin-content').length > 0;
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
            '<div class="alttextai-settings-license-sites-loading">' +
            '<span class="alttextai-spinner"></span> ' +
            'Loading site usage...' +
            '</div>'
        );

        // Fetch license site usage
        $.ajax({
            url: window.alttextai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'alttextai_get_license_sites',
                nonce: window.alttextai_ajax.nonce
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
                            '<div class="alttextai-settings-license-sites-empty">' +
                            '<p>No sites are currently using this license.</p>' +
                            '</div>'
                        );
                    }
                } else {
                    console.error('[AltText AI] License sites request failed:', response);
                    $sitesContent.html(
                        '<div class="alttextai-settings-license-sites-error">' +
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
                    '<div class="alttextai-settings-license-sites-error">' +
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
        const $sitesContent = $('#alttextai-license-sites-content');
        
        if (!sites || sites.length === 0) {
            $sitesContent.html(
                '<div class="alttextai-settings-license-sites-empty">' +
                '<p>No sites are currently using this license.</p>' +
                '</div>'
            );
            return;
        }

        let html = '<div class="alttextai-settings-license-sites-list">';
        html += '<div class="alttextai-settings-license-sites-summary">';
        html += '<strong>' + sites.length + '</strong> site' + (sites.length !== 1 ? 's' : '') + ' using this license';
        html += '</div>';
        html += '<ul class="alttextai-settings-license-sites-items">';

        sites.forEach(function(site) {
            const siteName = site.site_name || site.install_id || 'Unknown Site';
            const siteId = site.siteId || site.install_id || site.installId || '';
            const generations = site.total_generations || site.generations || 0;
            const lastUsed = site.last_used ? new Date(site.last_used).toLocaleDateString() : 'Never';
            
            html += '<li class="alttextai-settings-license-sites-item">';
            html += '<div class="alttextai-settings-license-sites-item-main">';
            html += '<div class="alttextai-settings-license-sites-item-info">';
            html += '<div class="alttextai-settings-license-sites-item-name">' + escapeHtml(siteName) + '</div>';
            html += '<div class="alttextai-settings-license-sites-item-stats">';
            html += '<span class="alttextai-settings-license-sites-item-generations">';
            html += '<strong>' + generations.toLocaleString() + '</strong> alt text generated';
            html += '</span>';
            html += '<span class="alttextai-settings-license-sites-item-last">';
            html += 'Last used: ' + escapeHtml(lastUsed);
            html += '</span>';
            html += '</div>';
            html += '</div>';
            html += '<div class="alttextai-settings-license-sites-item-actions">';
            html += '<button type="button" class="alttextai-settings-license-sites-disconnect-btn" ';
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
        $(document).off('click', '.alttextai-settings-license-sites-disconnect-btn');
        $(document).on('click', '.alttextai-settings-license-sites-disconnect-btn', function(e) {
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
                .addClass('alttextai-processing')
                .html('<span class="alttextai-spinner"></span> Disconnecting...');
            
            // Make AJAX request to disconnect site
            $.ajax({
                url: window.alttextai_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'alttextai_disconnect_license_site',
                    site_id: siteId,
                    nonce: window.alttextai_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        console.log('[AltText AI] Site disconnected successfully:', siteName);
                        
                        // Reload the license sites list
                        loadLicenseSiteUsage();
                    } else {
                        // Show error message
                        alert('Failed to disconnect site: ' + (response.data?.message || 'Unknown error'));
                        
                        // Restore button
                        $btn.prop('disabled', false)
                            .removeClass('alttextai-processing')
                            .html('<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span>Disconnect</span>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[AltText AI] Failed to disconnect site:', error);
                    alert('Failed to disconnect site. Please try again.');
                    
                    // Restore button
                    $btn.prop('disabled', false)
                        .removeClass('alttextai-processing')
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
        
        if (!window.alttextai_ajax || !window.alttextai_ajax.ajaxurl) {
            alert('Configuration error. Please refresh the page.');
            return;
        }
        
        // Check authentication before making the request - check multiple sources
        const ajaxAuth = window.alttextai_ajax && window.alttextai_ajax.is_authenticated === true;
        const userDataAuth = window.alttextai_ajax && window.alttextai_ajax.user_data && Object.keys(window.alttextai_ajax.user_data).length > 0;
        
        // If in Admin tab, admin is already authenticated (they had to log in to see Admin content)
        const isAdminTab = $('.alttextai-admin-content').length > 0;
        const isAdminAuthenticated = isAdminTab; // Admin login is separate, if we're in admin content, admin is authenticated
        
        const isAuthenticated = ajaxAuth || userDataAuth || isAdminAuthenticated;
        
        console.log('[AltText AI] openCustomerPortal() - auth check:', {
            hasAjax: !!window.alttextai_ajax,
            ajaxAuth: ajaxAuth,
            userDataAuth: userDataAuth,
            isAdminTab: isAdminTab,
            isAdminAuthenticated: isAdminAuthenticated,
            isAuthenticated: isAuthenticated,
            authValue: window.alttextai_ajax?.is_authenticated,
            userData: window.alttextai_ajax?.user_data
        });
        
        if (!isAuthenticated) {
            // Check if we're trying to open portal after login (in which case, don't show modal again)
            const isAfterLogin = sessionStorage.getItem('alttextai_portal_after_login') === 'true';
            
            if (isAfterLogin) {
                console.log('[AltText AI] Portal opened after login but auth check failed - waiting for auth state...');
                // Wait a bit and retry
                setTimeout(function() {
                    const retryAuth = window.alttextai_ajax && window.alttextai_ajax.is_authenticated === true;
                    if (retryAuth) {
                        console.log('[AltText AI] Auth state now ready, retrying portal...');
                        openCustomerPortal();
                    } else {
                        console.error('[AltText AI] Auth state still not ready after retry');
                        sessionStorage.removeItem('alttextai_portal_after_login');
                    }
                }, 1000);
                return;
            }
            
            console.log('[AltText AI] Not authenticated in openCustomerPortal, showing login modal');
            // User not authenticated - show login modal instead
            localStorage.setItem('alttextai_open_portal_after_login', 'true');
            
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
                alert('Please log in first to manage your subscription. Use the "Login" button in the header.');
            }
            
            return;
        }

        // Find all manage subscription buttons
        const $buttons = $('[data-action="manage-subscription"], #alttextai-update-payment-method, #alttextai-manage-subscription');
        
        // Show loading state with visual feedback
        $buttons.prop('disabled', true)
                .addClass('alttextai-btn-loading')
                .attr('aria-busy', 'true');
        
        // Update button text temporarily
        const originalText = {};
        $buttons.each(function() {
            const $btn = $(this);
            originalText[$btn.attr('id') || 'btn'] = $btn.text();
            $btn.html('<span class="alttextai-spinner"></span> Opening portal...');
        });

                $.ajax({
            url: window.alttextai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                action: 'alttextai_create_portal',
                nonce: window.alttextai_ajax.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Portal response:', response);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('alttextai-btn-loading')
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
                        alert('Please allow popups for this site to manage your subscription.');
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
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                        // This shouldn't happen since we check auth first, but handle it gracefully
                        console.log('[AltText AI] Authentication error from server, showing login modal');
                        localStorage.setItem('alttextai_open_portal_after_login', 'true');
                        
                        // Try to show login modal instead of alert
                        let modalShown = false;
                        if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
                            window.authModal.show();
                            window.authModal.showLoginForm();
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
                            alert('Please log in first to manage your billing.\n\nClick the "Login" button in the top navigation.');
                        }
                    } else if (errorMessage.toLowerCase().includes('not found') || errorMessage.toLowerCase().includes('subscription')) {
                        errorMessage = 'No active subscription found.\n\nPlease upgrade to a paid plan first, then you can manage your subscription.';
                        alert(errorMessage);
                    } else if (errorMessage.toLowerCase().includes('customer')) {
                        errorMessage = 'Unable to find your billing account.\n\nPlease contact support for assistance.';
                        alert(errorMessage);
                    } else {
                        alert(errorMessage);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Portal error:', status, error, xhr);
                
                // Restore button state
                $buttons.prop('disabled', false)
                        .removeClass('alttextai-btn-loading')
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
                
                alert(errorMessage);
            }
        });
    }

    /**
     * Initiate Stripe Checkout
     * Creates checkout session and opens in new tab
     */
    function initiateCheckout($button, priceId, planName) {
        if (alttextaiDebug) console.log('[AltText AI] Initiating checkout:', planName, priceId);
        
        if (!window.alttextai_ajax || !window.alttextai_ajax.ajaxurl) {
            alert('Configuration error. Please refresh the page.');
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('alttextai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalHtml = $button.html();
        $button.html('<span class="alttextai-spinner"></span> Loading checkout...');

        $.ajax({
            url: window.alttextai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'alttextai_create_checkout',
                nonce: window.alttextai_ajax.nonce,
                price_id: priceId
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Checkout response:', response);
                
                // Restore button state
                $button.prop('disabled', false)
                       .removeClass('alttextai-btn-loading')
                       .html(originalHtml)
                       .attr('aria-busy', 'false');

                if (response.success && response.data && response.data.url) {
                    if (alttextaiDebug) console.log('[AltText AI] Opening checkout URL:', response.data.url);
                    
                    // Open checkout in new tab
                    const checkoutWindow = window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    
                    if (!checkoutWindow) {
                        alert('Please allow popups for this site to complete checkout.');
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
                    // Provide context-aware error messages
                    let errorMessage = response.data?.message || 'Failed to create checkout session. Please try again.';
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                        errorMessage = 'Please log in first to upgrade.\n\nClick the "Login" button in the top navigation.';
                    } else if (errorMessage.toLowerCase().includes('price')) {
                        errorMessage = 'Unable to load pricing information.\n\nPlease try again or contact support.';
                    }
                    
                    alert(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Checkout error:', status, error, xhr);
                
                // Restore button state
                $button.prop('disabled', false)
                       .removeClass('alttextai-btn-loading')
                       .html(originalHtml)
                       .attr('aria-busy', 'false');
                
                // Provide helpful error message
                let errorMessage = 'Unable to connect to checkout system. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please check your internet connection and try again.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network connection lost. Please check your internet and try again.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Checkout system is temporarily unavailable. Please try again in a few minutes.';
                }
                
                alert(errorMessage);
            }
        });
    }

    /**
     * Disconnect OpptiAI Account
     * Clears authentication and allows another admin to connect
     */
    function disconnectAccount($button) {
        if (alttextaiDebug) console.log('[AltText AI] Disconnecting account...');
        
        if (!window.alttextai_ajax || !window.alttextai_ajax.ajaxurl) {
            alert('Configuration error. Please refresh the page.');
            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('alttextai-btn-loading')
               .attr('aria-busy', 'true');
        
        const originalText = $button.text();
        $button.html('<span class="alttextai-spinner"></span> Disconnecting...');

        $.ajax({
            url: window.alttextai_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'alttextai_disconnect_account',
                nonce: window.alttextai_ajax.nonce
            },
            timeout: 15000, // 15 second timeout
            success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Disconnect response:', response);
                
                if (response.success) {
                    // Show success message
                    $button.removeClass('alttextai-btn-loading')
                           .removeClass('alttextai-btn--ghost')
                           .addClass('alttextai-btn--success')
                           .html('✓ Disconnected')
                           .attr('aria-busy', 'false');
                    
                    // Clear any cached data
                    if (typeof localStorage !== 'undefined') {
                        localStorage.removeItem('alttextai_subscription_cache');
                        localStorage.removeItem('alttextai_token');
                    }
                    
                    // Reload after brief delay to show success state
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Restore button
                    $button.prop('disabled', false)
                           .removeClass('alttextai-btn-loading')
                           .text(originalText)
                           .attr('aria-busy', 'false');
                    
                    const errorMsg = response.data?.message || 'Failed to disconnect account. Please try again.';
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                if (alttextaiDebug) console.error('[AltText AI] Disconnect error:', status, error);
                
                // Restore button
                $button.prop('disabled', false)
                       .removeClass('alttextai-btn-loading')
                       .text(originalText)
                       .attr('aria-busy', 'false');
                
                // Provide helpful error message
                let errorMessage = 'Unable to disconnect account. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Your connection may still be disconnected. Try refreshing the page.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                }
                
                alert(errorMessage);
            }
        });
    }

})(jQuery);

// Debug mode check (define early so it can be used in functions)
var alttextaiDebug = (typeof window.alttextai_ajax !== 'undefined' && window.alttextai_ajax.debug) || false;

// Check if modal exists when script loads
(function() {
    'use strict';
    function checkModalExists() {
        const modal = document.getElementById('alttextai-upgrade-modal');
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

// Global functions for modal - make it very robust
function alttextaiShowModal() {
    console.log('[AltText AI] alttextaiShowModal() called');
    const modal = document.getElementById('alttextai-upgrade-modal');
    
    if (!modal) {
        console.error('[AltText AI] Upgrade modal element not found in DOM!');
        console.error('[AltText AI] Searching for modal...');
        // Try to find it by class
        const byClass = document.querySelector('.alttextai-modal-backdrop');
        if (byClass) {
            console.log('[AltText AI] Found modal by class:', byClass);
            byClass.id = 'alttextai-upgrade-modal';
            return alttextaiShowModal(); // Retry
        }
        alert('Upgrade modal not found. Please refresh the page.');
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
            modal.className = 'alttextai-modal-backdrop';
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

// Make it available globally for onclick handlers
window.alttextaiShowModal = alttextaiShowModal;

// Also create a simple test function
window.testUpgradeModal = function() {
    console.log('=== Testing Upgrade Modal ===');
    const modal = document.getElementById('alttextai-upgrade-modal');
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
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        // Restore body scroll
        document.body.style.overflow = '';
    }
}

// Global fallback handler for upgrade buttons (works even if jQuery isn't ready)
// This ensures the upgrade modal works on all tabs, even if jQuery handlers fail
(function() {
    'use strict';
    
    // Function to show modal directly with aggressive approach
    function showModalDirectly() {
        const modal = document.getElementById('alttextai-upgrade-modal');
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
        const closeBtn = e.target.closest('.alttextai-modal-close, [onclick*="alttextaiCloseModal"]');
        if (closeBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof alttextaiCloseModal === 'function') {
                alttextaiCloseModal();
            } else {
                const modal = document.getElementById('alttextai-upgrade-modal');
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }
            }
            return false;
        }
        
        // Handle backdrop clicks (click outside modal content to close)
        const modal = document.getElementById('alttextai-upgrade-modal');
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
            const modal = document.getElementById('alttextai-upgrade-modal');
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
            alert('Authentication system not available. Please refresh the page.');
        }
    }
}

function showAuthLogin() {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth login');
    
    // Try multiple methods to show auth modal
    if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
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
    if (typeof window.alttextai_ajax === 'undefined') {
        console.error('[AltText AI] alttextai_ajax object not found');
        // Still try to clear token and reload
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('alttextai_token');
        }
        window.location.reload();
                    return;
                }

    const ajaxUrl = window.alttextai_ajax.ajax_url || window.alttextai_ajax.ajaxurl || ajaxurl;
    const nonce = window.alttextai_ajax.nonce || '';
    
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
                action: 'alttextai_logout',
                nonce: nonce
                    },
                    success: function(response) {
                if (alttextaiDebug) console.log('[AltText AI] Logout successful', response);
                // Clear any local storage
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
                // Reload the page to update the UI
                window.location.reload();
                    },
                    error: function(xhr, status, error) {
                console.error('[AltText AI] Logout failed:', error, xhr.responseText);
                // Even on error, clear local storage and reload
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
                alert('Logged out locally. Refreshing page...');
                window.location.reload();
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
            window.location.reload();
        })
        .catch(error => {
            console.error('[AltText AI] Logout failed:', error);
            // Even on error, clear local storage and reload
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            alert('Logged out locally. Refreshing page...');
            window.location.reload();
        });
    }
}

// Initialize auth modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (alttextaiDebug) console.log('[AltText AI] DOM loaded, initializing auth system');
    
    // Vanilla JS fallback for logout button (in case jQuery isn't ready)
    const logoutBtn = document.getElementById('alttextai-logout-btn');
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
        if (!document.querySelector('.alttextai-countdown[data-countdown]')) {
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
    const countdownElement = document.querySelector('.alttextai-countdown[data-countdown]');
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
(function($) {
    'use strict';

    $(document).ready(function() {
        // Search functionality
        $('#alttextai-library-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            filterLibraryRows(searchTerm, getActiveFilter());
        });

        // Filter buttons
        $('.alttextai-filter-btn').on('click', function() {
            const $btn = $(this);
            const filter = $btn.attr('data-filter');
            
            // Toggle active state
            if ($btn.hasClass('active')) {
                $btn.removeClass('active');
                filterLibraryRows($('#alttextai-library-search').val().toLowerCase(), null);
            } else {
                $('.alttextai-filter-btn').removeClass('active');
                $btn.addClass('active');
                filterLibraryRows($('#alttextai-library-search').val().toLowerCase(), filter);
            }
        });

        /**
         * Get currently active filter
         */
        function getActiveFilter() {
            const $activeBtn = $('.alttextai-filter-btn.active');
            return $activeBtn.length ? $activeBtn.attr('data-filter') : null;
        }

        /**
         * Filter library table rows based on search and filter
         */
        function filterLibraryRows(searchTerm, filter) {
            let visibleCount = 0;
            
            $('.alttextai-library-row').each(function() {
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
            if (visibleCount === 0 && $('.alttextai-library-table tbody tr').length > 1) {
                // Could show a "No results" message here
                console.log('No matching rows found');
            }
        }
    });

})(jQuery);

