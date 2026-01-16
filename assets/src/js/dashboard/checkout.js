/**
 * Checkout Integration
 * Stripe checkout session handling
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

/**
 * Initiate checkout session
 */
function initiateCheckout($btn, priceId, plan) {
    var $ = window.jQuery || window.$;
    if (typeof $ !== 'function') return;

    if (alttextaiDebug) console.log('[AltText AI] Initiating checkout:', plan, priceId);

    if (!window.bbai_ajax || !window.bbai_ajax.ajaxurl) {
        if (window.bbaiModal) {
            window.bbaiModal.error('Configuration error. Please refresh the page.');
        }
        return;
    }

    // Show loading state on button
    var originalText = $btn.text();
    $btn.prop('disabled', true)
        .addClass('bbai-btn-loading')
        .html('<span class="bbai-spinner"></span> Processing...');

    $.ajax({
        url: window.bbai_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'beepbeepai_create_checkout',
            nonce: window.bbai_ajax.nonce,
            price_id: priceId,
            plan: plan
        },
        timeout: 30000,
        success: function(response) {
            if (response.success && response.data && response.data.url) {
                if (alttextaiDebug) console.log('[AltText AI] Checkout URL received, opening in new window:', response.data.url);
                // Open Stripe checkout in new window
                window.open(response.data.url, '_blank', 'noopener,noreferrer');
            } else {
                // Restore button state
                $btn.prop('disabled', false)
                    .removeClass('bbai-btn-loading')
                    .text(originalText);

                var errorMsg = response.data?.message || 'Could not initiate checkout. Please try again.';
                var fallbackUrl = $btn.attr('data-fallback-url');

                // If we have a fallback URL, use it instead of showing error
                if (fallbackUrl) {
                    if (alttextaiDebug) console.log('[AltText AI] Checkout API failed, using fallback URL:', fallbackUrl);
                    window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                    return;
                }

                // Check for authentication error
                if (response.data?.code === 'not_authenticated' || errorMsg.toLowerCase().includes('log in')) {
                    // Show auth modal
                    if (typeof showAuthModal === 'function') {
                        showAuthModal('login');
                    }
                } else if (window.bbaiModal) {
                    window.bbaiModal.error(errorMsg);
                }
            }
        },
        error: function(xhr, status, error) {
            // Restore button state
            $btn.prop('disabled', false)
                .removeClass('bbai-btn-loading')
                .text(originalText);

            if (alttextaiDebug) console.error('[AltText AI] Checkout error:', status, error);

            // Try fallback URL first
            var fallbackUrl = $btn.attr('data-fallback-url');
            if (fallbackUrl) {
                if (alttextaiDebug) console.log('[AltText AI] Checkout AJAX failed, using fallback URL:', fallbackUrl);
                window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                return;
            }

            var errorMessage = 'Unable to initiate checkout. Please try again.';
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.status === 401 || xhr.status === 403) {
                errorMessage = 'Please log in to continue with checkout.';
                if (typeof showAuthModal === 'function') {
                    showAuthModal('login');
                }
            }

            if (window.bbaiModal) {
                window.bbaiModal.error(errorMessage);
            }
        }
    });
}

// Export function
window.initiateCheckout = initiateCheckout;
