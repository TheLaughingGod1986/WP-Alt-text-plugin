/**
 * Checkout Integration
 * Stripe checkout session handling
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

function resolveCheckoutPriceId($btn, priceId, plan) {
    var upgradePriceIds = (window.BBAI_UPGRADE && window.BBAI_UPGRADE.priceIds) || {};
    var dashboardPriceIds = (window.BBAI_DASH && window.BBAI_DASH.checkoutPrices) || {};

    if (priceId) {
        return priceId;
    }

    if ($btn && typeof $btn.attr === 'function') {
        return $btn.attr('data-price-id') || upgradePriceIds[plan] || dashboardPriceIds[plan] || '';
    }

    return upgradePriceIds[plan] || dashboardPriceIds[plan] || '';
}

function resolveCheckoutFallbackUrl($btn, plan) {
    var fallbackUrl = $btn && typeof $btn.attr === 'function' ? ($btn.attr('data-fallback-url') || '') : '';
    var stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
    var resolvedLink = fallbackUrl || stripeLinks[plan] || '';

    if (!resolvedLink) {
        if (plan === 'pro' || plan === 'growth') {
            resolvedLink = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
        } else if (plan === 'agency') {
            resolvedLink = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
        } else if (plan === 'credits') {
            resolvedLink = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
        }
    }

    return resolvedLink;
}

function openCheckoutUrl(url) {
    if (!url) {
        return false;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
    if (typeof window.alttextaiCloseModal === 'function') {
        window.alttextaiCloseModal();
    }

    return true;
}

function setCheckoutButtonLoading($btn, isLoading) {
    if (!$btn || typeof $btn.length === 'undefined' || !$btn.length) {
        return;
    }

    if (isLoading) {
        if (!$btn.data('bbaiCheckoutLabel')) {
            $btn.data('bbaiCheckoutLabel', $btn.text());
        }

        $btn.prop('disabled', true)
            .addClass('bbai-btn-loading')
            .attr('aria-busy', 'true')
            .text('Redirecting…');
        return;
    }

    $btn.prop('disabled', false)
        .removeClass('bbai-btn-loading')
        .attr('aria-busy', 'false')
        .text($btn.data('bbaiCheckoutLabel') || $btn.text());
}

/**
 * Initiate checkout — prefer backend-created Checkout Sessions so internal identity
 * metadata is attached, then fall back to Payment Links only if session creation
 * is unavailable or fails.
 */
function initiateCheckout($btn, priceId, plan) {
    var $ = window.jQuery || window.$;
    var ajaxUrl = window.bbai_ajax && window.bbai_ajax.ajaxurl;
    var nonce = window.bbai_ajax && window.bbai_ajax.nonce;
    var resolvedPriceId = resolveCheckoutPriceId($btn, priceId, plan);
    var fallbackUrl = resolveCheckoutFallbackUrl($btn, plan);

    if (!ajaxUrl || !nonce || !resolvedPriceId || !$ || typeof $.ajax !== 'function') {
        if (openCheckoutUrl(fallbackUrl)) {
            return;
        }
    } else {
        setCheckoutButtonLoading($btn, true);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'beepbeepai_create_checkout',
                nonce: nonce,
                price_id: resolvedPriceId,
                plan_id: plan || ''
            }
        }).done(function(response) {
            var checkoutData = response && response.success && response.data ? response.data : {};
            var checkoutUrl = checkoutData && checkoutData.url ? checkoutData.url : '';
            var checkoutSessionId = checkoutData && (checkoutData.session_id || checkoutData.sessionId)
                ? String(checkoutData.session_id || checkoutData.sessionId)
                : '';
            var invalidHostedSession = checkoutUrl
                && /checkout\.stripe\.com\/c\/pay\//i.test(checkoutUrl)
                && checkoutSessionId === '';

            setCheckoutButtonLoading($btn, false);

            if (checkoutUrl && !invalidHostedSession) {
                window.BBAI_LOG && window.BBAI_LOG.log('[AltText AI] Opening Stripe checkout session:', checkoutUrl);
                openCheckoutUrl(checkoutUrl);
                return;
            }

            if (invalidHostedSession) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Hosted checkout response missing session ID, falling back to payment link', checkoutData);
            }

            if (openCheckoutUrl(fallbackUrl)) {
                return;
            }

            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
            }
        }).fail(function(xhr) {
            var errorMessage = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                || 'Unable to initiate checkout. Please try again or contact support.';

            setCheckoutButtonLoading($btn, false);

            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Checkout session creation failed, falling back to payment link', {
                status: xhr && xhr.status,
                response: xhr && xhr.responseJSON ? xhr.responseJSON : null
            });

            if (openCheckoutUrl(fallbackUrl)) {
                return;
            }

            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error(errorMessage);
            }
        });

        return;
    }

    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No Stripe checkout URL available for plan:', plan);
    if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
        window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
    }
}

// Export function
window.initiateCheckout = initiateCheckout;
