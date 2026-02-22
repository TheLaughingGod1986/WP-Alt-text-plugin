/**
 * Customer Portal
 * Stripe billing portal integration
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

// Ensure global variables exist (may not be defined if this file loads before main bundle)
var alttextaiDebug = (typeof alttextaiDebug !== 'undefined') ? alttextaiDebug : ((typeof window !== 'undefined' && typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false);

/**
 * Open customer portal
 */
function openCustomerPortal() {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    if (alttextaiDebug) console.log('[AltText AI] Opening customer portal');

    if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
        if (window.bbaiModal) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
        }
        return;
    }

    // Show loading indicator
    var $overlay = $('<div class="bbai-portal-loading-overlay">Loading billing portal...</div>');
    $('body').append($overlay);

    $.ajax({
        url: window.bbai_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'beepbeepai_create_portal_session',
            nonce: window.bbai_ajax.nonce
        },
        timeout: 30000,
        success: function(response) {
            $overlay.remove();

            if (response.success && response.data && response.data.url) {
                if (alttextaiDebug) console.log('[AltText AI] Portal URL received, redirecting');
                window.location.href = response.data.url;
            } else {
                var errorMsg = response.data?.message || 'Could not open billing portal. Please try again.';
                if (window.bbaiModal) {
                    window.bbaiModal.error(errorMsg);
                }
            }
        },
        error: function(xhr, status, error) {
            $overlay.remove();

            if (alttextaiDebug) console.error('[AltText AI] Portal error:', status, error);

            var errorMessage = 'Unable to open billing portal. Please try again.';
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.status === 401 || xhr.status === 403) {
                errorMessage = 'Please log in to access the billing portal.';
            }

            if (window.bbaiModal) {
                window.bbaiModal.error(errorMessage);
            }
        }
    });
}

/**
 * Check if portal should open after login
 */
function checkPortalAfterLogin() {
    var shouldOpenPortal = localStorage.getItem('bbai_open_portal_after_login');

    if (shouldOpenPortal === 'true') {
        if (alttextaiDebug) console.log('[AltText AI] Portal flag found, checking authentication...');

        var checkCount = 0;
        var maxChecks = 10;
        var checkInterval = setInterval(function() {
            checkCount++;
            var isAuthenticated = window.bbai_ajax && window.bbai_ajax.is_authenticated === true;

            if (isAuthenticated) {
                clearInterval(checkInterval);
                localStorage.removeItem('bbai_open_portal_after_login');

                sessionStorage.setItem('bbai_portal_after_login', 'true');

                setTimeout(function() {
                    openCustomerPortal();
                    setTimeout(function() {
                        sessionStorage.removeItem('bbai_portal_after_login');
                    }, 2000);
                }, 300);
            } else if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
                localStorage.removeItem('bbai_open_portal_after_login');
                console.warn('[AltText AI] User not authenticated after multiple checks');
            }
        }, 200);
    }
}

/**
 * Open Stripe payment link
 */
function openStripeLink(url) {
    if (alttextaiDebug) console.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

// Export functions
window.openCustomerPortal = openCustomerPortal;
window.checkPortalAfterLogin = checkPortalAfterLogin;
window.openStripeLink = openStripeLink;
