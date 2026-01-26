/**
 * Authentication Handlers
 * Auth banner, login, logout functionality
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

// Ensure global variables exist (may not be defined if this file loads before main bundle)
var alttextaiDebug = (typeof alttextaiDebug !== 'undefined') ? alttextaiDebug : ((typeof window !== 'undefined' && typeof window.bbai_ajax !== 'undefined' && window.bbai_ajax.debug) || false);

/**
 * Show auth banner
 */
function showAuthBanner() {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth banner');

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        window.authModal.show();
    } else {
        var authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            authModal.style.display = 'block';
        } else {
            if (window.bbaiModal) {
                window.bbaiModal.error('Authentication system not available. Please refresh the page.');
            }
        }
    }
}

/**
 * Show auth login modal
 */
function showAuthLogin() {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth login');

    // Try multiple methods to show auth modal
    if (window.bbaiApp && window.bbaiApp.authModal && typeof window.bbaiApp.authModal.show === 'function') {
        window.bbaiApp.authModal.show();
        return;
    } else if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
        window.AltTextAuthModal.show();
        return;
    }

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        window.authModal.show();
        return;
    }

    showAuthBanner();
}

/**
 * Show auth modal with specific tab
 */
function showAuthModal(tab) {
    if (alttextaiDebug) console.log('[AltText AI] Showing auth modal, tab:', tab);

    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        window.authModal.show();

        if (tab === 'register' && typeof window.authModal.showRegisterForm === 'function') {
            window.authModal.showRegisterForm();
        } else if (typeof window.authModal.showLoginForm === 'function') {
            window.authModal.showLoginForm();
        }
        return;
    }

    showAuthLogin();
}

/**
 * Handle logout
 */
function handleLogout() {
    if (window.alttextaiDebug) console.log('[AltText AI] Handling logout');

    if (typeof window.bbai_ajax === 'undefined') {
        if (window.alttextaiDebug) console.warn('[AltText AI] bbai_ajax object not found');
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('alttextai_token');
        }
        window.location.reload();
        return;
    }

    var ajaxUrl = window.bbai_ajax.ajax_url || window.bbai_ajax.ajaxurl || '';
    var nonce = window.bbai_ajax.nonce || '';

    if (!ajaxUrl) {
        if (window.alttextaiDebug) console.warn('[AltText AI] AJAX URL not found');
        window.location.reload();
        return;
    }

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
                if (window.alttextaiDebug) console.log('[AltText AI] Logout successful', response);
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
                var redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
                window.location.href = redirect;
            },
            error: function(xhr, status, error) {
                if (window.alttextaiDebug) console.warn('[AltText AI] Logout failed:', error);
                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('alttextai_token');
                }
                var redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
                window.location.href = redirect;
            }
        });
    } else {
        // Vanilla JS fallback
        var formData = new FormData();
        formData.append('action', 'alttextai_logout');
        formData.append('nonce', nonce);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json().catch(function() { return {}; });
        })
        .then(function() {
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            var redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        })
        .catch(function() {
            if (typeof localStorage !== 'undefined') {
                localStorage.removeItem('alttextai_token');
            }
            var redirect = (window.bbai_ajax && (window.bbai_ajax.logout_redirect || window.bbai_ajax.ajax_url)) || window.location.href;
            window.location.href = redirect;
        });
    }
}

/**
 * Disconnect account
 */
function disconnectAccount($btn) {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
        if (window.bbaiModal) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
        }
        return;
    }

    $btn.prop('disabled', true).addClass('bbai-btn-loading');
    var originalText = $btn.text();
    $btn.html('<span class="bbai-spinner"></span> Disconnecting...');

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
                $btn.removeClass('bbai-btn-loading').html('✓ Disconnected');

                if (typeof localStorage !== 'undefined') {
                    localStorage.removeItem('bbai_subscription_cache');
                    localStorage.removeItem('alttextai_token');
                }

                if (response.data && response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }
            } else {
                $btn.prop('disabled', false)
                    .removeClass('bbai-btn-loading')
                    .text(originalText);

                var errorMsg = response.data?.message || 'Failed to disconnect. Please try again.';
                if (window.bbaiModal) {
                    window.bbaiModal.error(errorMsg);
                }
            }
        },
        error: function(xhr, status) {
            $btn.prop('disabled', false)
                .removeClass('bbai-btn-loading')
                .text(originalText);

            var errorMessage = 'Unable to disconnect. Please try again.';
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Try refreshing the page.';
            }
            if (window.bbaiModal) {
                window.bbaiModal.error(errorMessage);
            }
        }
    });
}

// Export functions
window.showAuthBanner = showAuthBanner;
window.showAuthLogin = showAuthLogin;
window.showAuthModal = showAuthModal;
window.handleLogout = handleLogout;
window.disconnectAccount = disconnectAccount;
