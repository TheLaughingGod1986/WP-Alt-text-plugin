/**
 * Subscription Management
 * Load, display, and cache subscription info
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

// Retry state
var retryAttempts = 0;
var maxRetries = 5;
var retryTimeout = null;

/**
 * Load subscription information from backend
 */
function loadSubscriptionInfo(forceRefresh) {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    var $loading = getCachedElement('#bbai-subscription-loading');
    var $error = getCachedElement('#bbai-subscription-error');
    var $info = getCachedElement('#bbai-subscription-info');
    var $freeMessage = getCachedElement('#bbai-free-plan-message');

    // Check cache first (unless force refresh)
    if (!forceRefresh) {
        var cached = getCachedSubscriptionInfo();
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
                resetRetryAttempts();
                cacheSubscriptionInfo(response.data);
                displaySubscriptionInfo(response.data);

                // Handle portal return
                var urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('portal_return') === 'success') {
                    var cleanUrl = window.location.pathname + window.location.search.replace(/[?&]portal_return=success/, '').replace(/^&/, '?');
                    if (cleanUrl !== window.location.pathname + window.location.search) {
                        window.history.replaceState({}, document.title, cleanUrl);
                    }
                    setTimeout(function() {
                        loadSubscriptionInfo(true);
                    }, 2000);
                }
            } else {
                var plan = response.data?.plan || 'free';
                if (plan === 'free') {
                    $freeMessage.show();
                } else {
                    var errorMessage = response.data?.message || 'Failed to load subscription information.';
                    showSubscriptionError(errorMessage);
                }
            }
        },
        error: function(xhr, status) {
            $loading.hide();

            var errorMessage = 'Network error. Please try again.';

            if (xhr.status === 401 || xhr.status === 403) {
                showSubscriptionError('Please log in to view your subscription information.');
                return;
            } else if (xhr.status === 404) {
                showSubscriptionError('Subscription information not found.');
                return;
            } else if (xhr.status >= 500 || status === 'timeout' || status === 'error') {
                showSubscriptionError('Service temporarily unavailable. Retrying automatically...');
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
    var $info = getCachedElement('#bbai-subscription-info');
    var $error = getCachedElement('#bbai-subscription-error');
    var $freeMessage = getCachedElement('#bbai-free-plan-message');

    $error.hide();
    $freeMessage.hide();

    // Handle free plan
    if (!data.plan || data.plan === 'free' || data.status === 'free') {
        $freeMessage.show();
        return;
    }

    // Display subscription status
    var status = data.status || 'active';
    var statusBadge = getCachedElement('#bbai-status-badge');
    var statusLabel = statusBadge.find('.bbai-status-label');

    statusBadge.removeClass('bbai-status-active bbai-status-cancelled bbai-status-trial');
    statusBadge.addClass('bbai-status-' + status);
    statusLabel.text(status.charAt(0).toUpperCase() + status.slice(1));

    // Show cancel warning if needed
    var $cancelWarning = getCachedElement('#bbai-cancel-warning');
    if (data.cancelAtPeriodEnd) {
        $cancelWarning.show();
    } else {
        $cancelWarning.hide();
    }

    // Display plan details
    var planName = data.plan ? data.plan.charAt(0).toUpperCase() + data.plan.slice(1) : '-';
    getCachedElement('#bbai-plan-name').text(planName);

    var billingCycle = data.billingCycle ? data.billingCycle.charAt(0).toUpperCase() + data.billingCycle.slice(1) : '-';
    getCachedElement('#bbai-billing-cycle').text(billingCycle);

    // Format next billing date
    if (data.nextBillingDate) {
        var date = new Date(data.nextBillingDate);
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
        var currency = data.currency || 'GBP';
        var symbol = currency === 'USD' ? '$' : currency === 'EUR' ? '€' : '£';
        getCachedElement('#bbai-next-charge').text(symbol + parseFloat(data.nextChargeAmount).toFixed(2));
    } else {
        getCachedElement('#bbai-next-charge').text('-');
    }

    // Display payment method
    if (data.paymentMethod && data.paymentMethod.last4) {
        var $paymentMethod = getCachedElement('#bbai-payment-method');
        var brand = data.paymentMethod.brand || 'card';
        var last4 = data.paymentMethod.last4;
        var expMonth = data.paymentMethod.expMonth;
        var expYear = data.paymentMethod.expYear;

        getCachedElement('#bbai-card-brand').text(getCardBrandIcon(brand) + ' ' + brand.toUpperCase());
        getCachedElement('#bbai-card-last4').text('•••• ' + last4);
        if (expMonth && expYear) {
            getCachedElement('#bbai-card-expiry').text(expMonth + '/' + expYear.toString().slice(-2));
        }
        $paymentMethod.show();
    } else {
        getCachedElement('#bbai-payment-method').hide();
    }

    $info.show();
}

/**
 * Retry subscription load with exponential backoff
 */
function retrySubscriptionLoad() {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    if (retryAttempts >= maxRetries) {
        showSubscriptionError('Service unavailable after multiple attempts. Please try again later or refresh the page.');
        retryAttempts = 0;
        return;
    }

    retryAttempts++;
    var delay = Math.min(1000 * Math.pow(2, retryAttempts - 1), 16000);

    var $error = $('#bbai-subscription-error');
    $error.find('.bbai-error-message').text(
        'Service temporarily unavailable. Retrying in ' + Math.ceil(delay / 1000) + ' seconds... (Attempt ' + retryAttempts + '/' + maxRetries + ')'
    );

    if (retryTimeout) {
        clearTimeout(retryTimeout);
    }

    retryTimeout = setTimeout(function() {
        loadSubscriptionInfo(true);
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
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    var $error = $('#bbai-subscription-error');
    var $info = $('#bbai-subscription-info');
    var $freeMessage = $('#bbai-free-plan-message');

    $error.find('.bbai-error-message').text(message);
    $error.show();
    $info.hide();
    $freeMessage.hide();
}

/**
 * Get card brand icon/emoji
 */
function getCardBrandIcon(brand) {
    var icons = {
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
        var cacheData = {
            data: data,
            timestamp: Date.now(),
            expiry: 15 * 60 * 1000 // 15 minutes
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
        var cached = localStorage.getItem('bbai_subscription_cache');
        if (!cached) return null;

        var cacheData = JSON.parse(cached);
        var age = Date.now() - cacheData.timestamp;

        if (age < cacheData.expiry) {
            return cacheData;
        } else {
            localStorage.removeItem('bbai_subscription_cache');
            return null;
        }
    } catch (e) {
        console.warn('[AltText AI] Could not read subscription cache:', e);
        return null;
    }
}

// Export functions
window.loadSubscriptionInfo = loadSubscriptionInfo;
window.displaySubscriptionInfo = displaySubscriptionInfo;
window.cacheSubscriptionInfo = cacheSubscriptionInfo;
window.getCachedSubscriptionInfo = getCachedSubscriptionInfo;
