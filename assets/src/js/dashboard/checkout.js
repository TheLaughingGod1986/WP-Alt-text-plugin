/**
 * Checkout Integration
 * Stripe checkout session handling
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

/**
 * Initiate checkout — opens the Stripe Payment Link directly.
 */
function initiateCheckout($btn, priceId, plan) {
    var $ = window.jQuery || window.$;

    // Resolve Stripe Payment Link from button data or localized config
    var fallbackUrl = $btn && typeof $btn.attr === 'function' ? $btn.attr('data-fallback-url') : '';
    var stripeLinks = (window.bbai_ajax && window.bbai_ajax.stripe_links) || {};
    var resolvedLink = fallbackUrl || stripeLinks[plan] || '';

    if (!resolvedLink) {
        // Hardcoded fallback Payment Links
        if (plan === 'pro' || plan === 'growth') {
            resolvedLink = 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02';
        } else if (plan === 'agency') {
            resolvedLink = 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01';
        } else if (plan === 'credits') {
            resolvedLink = 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00';
        }
    }

    if (resolvedLink) {
        console.log('[AltText AI] Opening Stripe payment link:', resolvedLink);
        window.open(resolvedLink, '_blank', 'noopener,noreferrer');
        return;
    }

    // No link available
    console.error('[AltText AI] No Stripe payment link available for plan:', plan);
    if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
        window.bbaiModal.error('Unable to initiate checkout. Please try again or contact support.');
    }
}

// Export function
window.initiateCheckout = initiateCheckout;
