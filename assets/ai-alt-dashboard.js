/**
 * AI Alt Text Dashboard JavaScript
 * Handles upgrade modal, auth buttons, and Stripe integration
 */

(function($) {
    'use strict';

    // Cache commonly used DOM elements (performance optimization)
    var $cachedElements = {};

    function getCachedElement(selector) {
        if (!$cachedElements[selector]) {
            $cachedElements[selector] = $(selector);
        }
        return $cachedElements[selector];
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Handle upgrade CTA: if not authenticated, open auth modal instead
        $(document).on('click', '[data-action="show-upgrade-modal"]', function(e) {
            e.preventDefault();
            try {
                var isAuthed = false;
                if (typeof window.alttextai_ajax !== 'undefined' && typeof window.alttextai_ajax.is_authenticated !== 'undefined') {
                    isAuthed = !!window.alttextai_ajax.is_authenticated;
                }

                if (!isAuthed) {
                    showAuthLogin();
                    return;
                }

                alttextaiShowModal();
            } catch (err) {
                // Fallback to upgrade modal
                alttextaiShowModal();
            }
        });
        
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
        
        // Handle billing portal
        $(document).on('click', '[data-action="open-billing-portal"]', function(e) {
            e.preventDefault();
            // Add billing portal logic here if needed
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
            openCustomerPortal();
        });

        // Handle retry subscription fetch
        $(document).on('click', '#alttextai-retry-subscription', function(e) {
            e.preventDefault();
            loadSubscriptionInfo(true); // Force refresh
        });
    });

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
     * Open Stripe Customer Portal
     */
    function openCustomerPortal() {
        if (!window.alttextai_ajax || !window.alttextai_ajax.ajaxurl) {
            alert('Configuration error. Please refresh the page.');
                    return;
                }

        // Show loading state
        const $buttons = $('#alttextai-update-payment-method, #alttextai-manage-subscription');
        $buttons.prop('disabled', true);

                $.ajax({
            url: window.alttextai_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                action: 'alttextai_create_portal',
                nonce: window.alttextai_ajax.nonce
            },
            success: function(response) {
                $buttons.prop('disabled', false);

                if (response.success && response.data && response.data.url) {
                    window.open(response.data.url, '_blank');
                    // Reload subscription info after user returns (check every 2 seconds for page focus)
                    let checkCount = 0;
                    const maxChecks = 150; // 5 minutes max
                    
                    const checkInterval = setInterval(function() {
                        checkCount++;
                        if (document.hasFocus() || checkCount >= maxChecks) {
                            clearInterval(checkInterval);
                            // Reload subscription info when user returns
                            loadSubscriptionInfo();
                            // Update URL to show success notice
                            const url = new URL(window.location);
                            url.searchParams.set('portal_return', 'success');
                            window.history.replaceState({}, document.title, url.toString());
                        }
                    }, 2000);
                } else {
                    // Provide better error messages
                    let errorMessage = response.data?.message || 'Failed to open customer portal. Please try again.';
                    
                    if (errorMessage.toLowerCase().includes('not authenticated') || errorMessage.toLowerCase().includes('login')) {
                        errorMessage = 'Please log in to manage your billing.';
                    } else if (errorMessage.toLowerCase().includes('not found') || errorMessage.toLowerCase().includes('subscription')) {
                        errorMessage = 'No active subscription found. Please upgrade first to manage billing.';
                    }
                    
                    alert(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                $buttons.prop('disabled', false);
                alert('Network error. Please try again.');
            }
        });
    }

})(jQuery);

// Global functions for modal
function alttextaiShowModal() {
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function alttextaiCloseModal() {
    const modal = document.getElementById('alttextai-upgrade-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Debug mode check
var alttextaiDebug = (typeof window.alttextai_ajax !== 'undefined' && window.alttextai_ajax.debug) || false;

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
});

/**
 * Initialize and update countdown timer for limit reset
 */
function initCountdownTimer() {
    const countdownElement = document.querySelector('.alttextai-countdown[data-countdown]');
    if (!countdownElement) {
        return; // No countdown on this page
    }

    const totalSeconds = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
    const daysEl = countdownElement.querySelector('[data-days]');
    const hoursEl = countdownElement.querySelector('[data-hours]');
    const minutesEl = countdownElement.querySelector('[data-minutes]');

    if (!daysEl || !hoursEl || !minutesEl) {
        if (alttextaiDebug) console.warn('[AltText AI] Countdown elements not found');
        return;
    }

    // Store initial seconds and start time for accurate recalculation
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
        // Get the initial seconds from when page loaded
        const initialSeconds = parseInt(countdownElement.getAttribute('data-initial-seconds'), 10) || 0;
        
        // Calculate elapsed time since page load
        const startTime = parseFloat(countdownElement.getAttribute('data-start-time')) || (Date.now() / 1000);
        const currentTime = Date.now() / 1000;
        const elapsed = Math.floor(currentTime - startTime);
        
        // Calculate remaining seconds
        let remaining = Math.max(0, initialSeconds - elapsed);

        if (remaining <= 0) {
            daysEl.textContent = '0';
            hoursEl.textContent = '0';
            minutesEl.textContent = '0';
            countdownElement.setAttribute('data-countdown', '0');
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

    // Update every minute (60 seconds)
    const intervalId = setInterval(function() {
        updateCountdown();
        // Stop if countdown reaches zero
        const remaining = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
        if (remaining <= 0) {
            clearInterval(intervalId);
        }
    }, 60000); // Update every 60 seconds

    // Also update every second for the first minute to show real-time updates
    const secondIntervalId = setInterval(function() {
            updateCountdown();
        const remaining = parseInt(countdownElement.getAttribute('data-countdown'), 10) || 0;
        if (remaining <= 60) { // Stop second-by-second after 1 minute remaining
            clearInterval(secondIntervalId);
        }
    }, 1000); // Update every second
}
