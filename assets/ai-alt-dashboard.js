/**
 * AI Alt Text Dashboard JavaScript
 * Handles upgrade modal, auth buttons, and Stripe integration
 */

(function($) {
    'use strict';

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
    });

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

function openStripeLink(url) {
    console.log('[AltText AI] Opening Stripe link:', url);
    window.open(url, '_blank');
}

function showAuthBanner() {
    console.log('[AltText AI] Showing auth banner');
    
    // Try to show the auth modal
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        console.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
    } else {
        // Try to find and show the auth modal directly
        const authModal = document.getElementById('alttext-auth-modal');
        if (authModal) {
            console.log('[AltText AI] Showing auth modal directly');
            authModal.style.display = 'block';
        } else {
            console.log('[AltText AI] Auth modal not found');
            alert('Authentication system not available. Please refresh the page.');
        }
    }
}

function showAuthLogin() {
    console.log('[AltText AI] Showing auth login');
    
    // Try multiple methods to show auth modal
    if (typeof window.AltTextAuthModal !== 'undefined' && window.AltTextAuthModal && typeof window.AltTextAuthModal.show === 'function') {
        console.log('[AltText AI] Using AltTextAuthModal.show()');
        window.AltTextAuthModal.show();
        return;
    }
    
    if (typeof window.authModal !== 'undefined' && window.authModal && typeof window.authModal.show === 'function') {
        console.log('[AltText AI] Using authModal.show()');
        window.authModal.show();
        return;
    }
    
    // Fallback to showAuthBanner method
    showAuthBanner();
}

function handleLogout() {
    console.log('[AltText AI] Handling logout');
    
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
    
    console.log('[AltText AI] Calling logout AJAX:', ajaxUrl);
    
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
                console.log('[AltText AI] Logout successful', response);
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
            console.log('[AltText AI] Logout response status:', response.status);
            return response.json().catch(() => ({}));
        })
        .then(data => {
            console.log('[AltText AI] Logout successful', data);
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
    console.log('[AltText AI] DOM loaded, initializing auth system');
    
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
            console.log('[AltText AI] Logout button clicked (Vanilla JS)');
            handleLogout();
        });
        console.log('[AltText AI] Logout button found and listener attached');
    } else {
        console.log('[AltText AI] Logout button not found');
    }
    
    // Check if auth modal exists and initialize it
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
});
