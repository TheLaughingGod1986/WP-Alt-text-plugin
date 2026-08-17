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
        if (plan === 'starter') {
            resolvedLink = 'https://buy.stripe.com/eVqbJ25vg0wQ05Mfaj7ss03';
        } else if (plan === 'pro' || plan === 'growth') {
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

function isStripePaymentLink(url) {
    return typeof url === 'string' && /^https:\/\/buy\.stripe\.com\//i.test(url);
}

function resolveDirectCheckoutUrl(plan, priceId) {
    var baseUrl = window.bbai_ajax && window.bbai_ajax.direct_checkout_url;
    var nonce = window.bbai_ajax && window.bbai_ajax.direct_checkout_nonce;
    if (!baseUrl || !nonce || (!plan && !priceId)) {
        return '';
    }

    try {
        var url = new URL(baseUrl, window.location.href);
        if (plan) {
            url.searchParams.set('plan', plan);
        }
        if (priceId) {
            url.searchParams.set('price_id', priceId);
        }
        url.searchParams.set('_bbai_nonce', nonce);
        return url.toString();
    } catch (e) {
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
        var params = [];
        if (plan) {
            params.push('plan=' + encodeURIComponent(plan));
        }
        if (priceId) {
            params.push('price_id=' + encodeURIComponent(priceId));
        }
        params.push('_bbai_nonce=' + encodeURIComponent(nonce));
        return baseUrl + separator + params.join('&');
    }
}

function dispatchCheckoutAnalytics(eventName, payload) {
    try {
        document.dispatchEvent(new CustomEvent('bbai:analytics', {
            detail: Object.assign({
                event: eventName,
                source: 'checkout',
                endpoint: 'beepbeepai_create_checkout',
                timestamp: Date.now()
            }, payload || {})
        }));
    } catch (error) {
        // Ignore analytics failures.
    }
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
    var directCheckoutUrl = resolveDirectCheckoutUrl(plan, resolvedPriceId);
    var checkoutWindow = null;
    var closeCheckoutWindow = function() {
        if (checkoutWindow && !checkoutWindow.closed) {
            checkoutWindow.close();
        }
    };
    var sendCheckoutWindow = function(url) {
        if (!url) {
            return false;
        }
        if (checkoutWindow && !checkoutWindow.closed) {
            checkoutWindow.location.href = url;
            return true;
        }
        return openCheckoutUrl(url);
    };

    if (isStripePaymentLink(fallbackUrl)) {
        openCheckoutUrl(fallbackUrl);
        return;
    }

    if (plan === 'credits' && directCheckoutUrl) {
        openCheckoutUrl(directCheckoutUrl);
        return;
    }

    if (plan === 'starter' && directCheckoutUrl) {
        openCheckoutUrl(directCheckoutUrl);
        return;
    }

    if (!ajaxUrl || !nonce || (!resolvedPriceId && !plan) || !$ || typeof $.ajax !== 'function') {
        if (openCheckoutUrl(fallbackUrl || directCheckoutUrl)) {
            return;
        }
        dispatchCheckoutAnalytics('checkout_failed', {
            plan: plan || '',
            error_code: !ajaxUrl ? 'ajax_unavailable' : (!nonce ? 'missing_nonce' : ((!resolvedPriceId && !plan) ? 'missing_payload' : 'jquery_unavailable')),
            error_message: 'Checkout session request could not be started.'
        });
    } else {
        checkoutWindow = window.open('about:blank', '_blank');
        if (checkoutWindow) {
            checkoutWindow.opener = null;
        }

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
                sendCheckoutWindow(checkoutUrl);
                return;
            }

            if (invalidHostedSession) {
                window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Hosted checkout response missing session ID, falling back to payment link', checkoutData);
            }

            if (sendCheckoutWindow(fallbackUrl || directCheckoutUrl)) {
                return;
            }

            closeCheckoutWindow();
            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
            }
            dispatchCheckoutAnalytics('checkout_failed', {
                plan: plan || '',
                response_status: response && response.success === false ? 'error' : 'invalid_response',
                error_code: invalidHostedSession ? 'missing_checkout_session_id' : 'missing_checkout_url',
                error_message: 'Checkout response did not include a usable URL.'
            });
        }).fail(function(xhr) {
            var errorMessage = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                || 'Unable to initiate checkout. Please try again or contact support.';

            setCheckoutButtonLoading($btn, false);

            window.BBAI_LOG && window.BBAI_LOG.warn('[AltText AI] Checkout session creation failed, falling back to payment link', {
                status: xhr && xhr.status,
                response: xhr && xhr.responseJSON ? xhr.responseJSON : null
            });

            if (sendCheckoutWindow(fallbackUrl || directCheckoutUrl)) {
                return;
            }

            closeCheckoutWindow();
            if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
                window.bbaiModal.error(errorMessage);
            }
            dispatchCheckoutAnalytics('checkout_failed', {
                plan: plan || '',
                response_status: xhr && xhr.status ? xhr.status : 'error',
                error_code: 'checkout_session_failed',
                error_message: errorMessage
            });
        });

        return;
    }

    window.BBAI_LOG && window.BBAI_LOG.error('[AltText AI] No Stripe checkout URL available for plan:', plan);
    dispatchCheckoutAnalytics('checkout_failed', {
        plan: plan || '',
        error_code: 'missing_checkout_url',
        error_message: 'No Stripe checkout URL available.'
    });
    if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
        window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
    }
}

// Export function
window.initiateCheckout = initiateCheckout;
