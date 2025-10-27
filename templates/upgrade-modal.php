<?php
/**
 * Upgrade Modal Template - Simple Redirect Version
 * @package AI_Alt_Text_Generator_GPT
 */

if (!defined('ABSPATH')) { exit; }

// Currency display
$currency = [
    'symbol' => '$',
    'pro' => 12.99,
    'agency' => 49.99,
    'credits' => 9.99
];

$price_ids = isset($checkout_prices) && is_array($checkout_prices) ? $checkout_prices : [];
$pro_price_id = $price_ids['pro'] ?? '';
$agency_price_id = $price_ids['agency'] ?? '';
$credits_price_id = $price_ids['credits'] ?? '';

$direct_checkout_nonce = wp_create_nonce('alttextai_direct_checkout');
$checkout_base = admin_url('admin.php');

$pro_url = add_query_arg([
    'page'               => 'ai-alt-gpt-checkout',
    'plan'               => 'pro',
    'price_id'           => $pro_price_id,
    '_alttextai_nonce'   => $direct_checkout_nonce,
], $checkout_base);
$agency_url = add_query_arg([
    'page'               => 'ai-alt-gpt-checkout',
    'plan'               => 'agency',
    'price_id'           => $agency_price_id,
    '_alttextai_nonce'   => $direct_checkout_nonce,
], $checkout_base);
$credits_url = add_query_arg([
    'page'               => 'ai-alt-gpt-checkout',
    'type'               => 'credits',
    'price_id'           => $credits_price_id,
    '_alttextai_nonce'   => $direct_checkout_nonce,
], $checkout_base);
?>

<script>
// Close modal function
window.alttextaiCloseModal = function() {
    console.log('[AltText AI] Closing modal');
    jQuery('#alttextai-upgrade-modal').fadeOut(280);
    jQuery('body').css('overflow', '');
    return false;
};

// Prevent clicks inside modal content from closing modal, BUT allow links to work
jQuery(document).ready(function($) {
    $('.alttextai-upgrade-modal__content').on('click', function(e) {
        // Don't stop propagation for links - let them navigate
        if (!$(e.target).closest('a').length) {
            e.stopPropagation();
        }
    });

    $(document).on('click', '.alttextai-upgrade-test-link', function() {
        const $link = $(this);
        console.group('[AltText AI] Checkout Link Clicked');
        console.log('Plan:', $link.data('plan'));
        console.log('Price ID:', $link.data('price-id'));
        console.log('Destination href:', $link.attr('href'));
        console.log('Timestamp:', new Date().toISOString());
        console.log('Target window:', $link.attr('target') || 'same window');
        console.groupEnd();

        setTimeout(function() {
            console.warn('[AltText AI] If no new tab opened or you saw a 404/blank page, copy the URL above and share it so we can trace the backend call.');
        }, 2000);
    });

    (function checkForErrors() {
        try {
            const params = new URLSearchParams(window.location.search);
            const errorMessage = params.get('checkout_error');
            if (errorMessage) {
                console.error('[AltText AI] Checkout error:', decodeURIComponent(errorMessage));
            }
        } catch (err) {
            console.error('[AltText AI] Unable to parse checkout error:', err);
        }
    })();
});
</script>

<div id="alttextai-upgrade-modal" class="alttextai-modal" style="display:none;">
    <div class="alttextai-modal-backdrop" onclick="window.alttextaiCloseModal();"></div>
    <div class="alttextai-upgrade-modal__content">
        <button class="alttextai-modal__close" onclick="window.alttextaiCloseModal(); return false;" aria-label="<?php esc_attr_e('Close upgrade modal', 'ai-alt-gpt'); ?>">×</button>
        <div class="alttextai-upgrade-modal__header">
            <div class="alttextai-upgrade-modal__icon-wrapper">
                <svg class="alttextai-upgrade-modal__rocket" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#EF4444" d="M32 4 c8 0 16 8 20 16 s4 16 0 24 c-4 8-12 16-20 16 s-16-8-20-16 s-4-16 0-24 C16 12 24 4 32 4z"/>
                    <circle cx="32" cy="28" r="6" fill="#FEE2E2"/>
                    <path fill="#DC2626" d="M20 48 c0-4 4-8 12-8 s12 4 12 8 l-24 0z"/>
                </svg>
            </div>
            <h2><?php esc_html_e('Unlock More AI-Powered Alt Text', 'ai-alt-gpt'); ?></h2>
            <p><?php esc_html_e('Choose a plan that fits your needs and start generating professional alt text at scale.', 'ai-alt-gpt'); ?></p>
        </div>
        <?php
            $missing_plans = [];
            if (empty($pro_price_id)) {
                $missing_plans[] = __('Pro', 'ai-alt-gpt');
            }
            if (empty($agency_price_id)) {
                $missing_plans[] = __('Agency', 'ai-alt-gpt');
            }
            if (empty($credits_price_id)) {
                $missing_plans[] = __('Credits', 'ai-alt-gpt');
            }
            if (!empty($missing_plans)) :
        ?>
            <div class="notice notice-warning" style="margin:0 0 16px 0;padding:12px 16px;">
                <p style="margin:0;font-size:13px;">
                    <?php
                        printf(
                            esc_html__('Stripe price IDs are missing for: %s. Set the matching values in your backend environment configuration to enable checkout.', 'ai-alt-gpt'),
                            esc_html(implode(', ', $missing_plans))
                        );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="alttextai-plan-grid">
            <!-- Free Plan -->
            <div class="alttextai-plan-card">
                <div class="alttextai-plan-card__label"><?php esc_html_e('Free', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">
                    <?php echo esc_html($currency['symbol']); ?>0<span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span>
                </div>
                <ul>
                    <li>🎯 <?php esc_html_e('10 images / month', 'ai-alt-gpt'); ?></li>
                    <li>✨ <?php esc_html_e('AI-powered alt text', 'ai-alt-gpt'); ?></li>
                    <li>📊 <?php esc_html_e('Basic analytics', 'ai-alt-gpt'); ?></li>
                </ul>
                <button type="button" class="alttextai-plan-button alttextai-plan-button--current" disabled>
                    <?php esc_html_e('Current Plan', 'ai-alt-gpt'); ?>
                </button>
            </div>

            <!-- Pro Plan -->
            <div class="alttextai-plan-card alttextai-plan-card--featured">
                <div class="alttextai-plan-pill"><?php esc_html_e('Most Popular', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__label"><?php esc_html_e('Pro', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">
                    <?php echo esc_html($currency['symbol'] . number_format($currency['pro'], 2)); ?><span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span>
                </div>
                <div class="alttextai-plan-card__daily"><?php esc_html_e('~33 images per day', 'ai-alt-gpt'); ?></div>
                <ul>
                    <li>⭐ <?php esc_html_e('1000 images / mth', 'ai-alt-gpt'); ?></li>
                    <li>⚡ <?php esc_html_e('Priority AI processing', 'ai-alt-gpt'); ?></li>
                    <li>🔧 <?php esc_html_e('Keyword optimization booster', 'ai-alt-gpt'); ?></li>
                </ul>
                <a href="<?php echo esc_url($pro_url); ?>"
                   class="alttextai-plan-button alttextai-plan-button--primary alttextai-upgrade-test-link"
                   data-plan="pro"
                   data-price-id="<?php echo esc_attr($pro_price_id); ?>"
                   target="_blank"
                   rel="noopener"
                   style="display: inline-block; text-decoration: none; text-align: center;">
                    <?php esc_html_e('Unlock 1000 AI Tags Now', 'ai-alt-gpt'); ?> →
                </a>
                <p class="alttextai-plan-card__badge"><?php esc_html_e('🔥 Most users choose this plan', 'ai-alt-gpt'); ?></p>
            </div>

            <!-- Agency Plan -->
            <div class="alttextai-plan-card">
                <div class="alttextai-plan-card__label"><?php esc_html_e('Agency', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">
                    <?php echo esc_html($currency['symbol'] . number_format($currency['agency'], 2)); ?><span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span>
                </div>
                <ul>
                    <li>🏢 <?php esc_html_e('10 000 images / month', 'ai-alt-gpt'); ?></li>
                    <li>🌐 <?php esc_html_e('Multi-site license', 'ai-alt-gpt'); ?></li>
                    <li>💬 <?php esc_html_e('Priority support', 'ai-alt-gpt'); ?></li>
                </ul>
                <a href="<?php echo esc_url($agency_url); ?>"
                   class="alttextai-plan-button alttextai-plan-button--secondary alttextai-upgrade-test-link"
                   data-plan="agency"
                   data-price-id="<?php echo esc_attr($agency_price_id); ?>"
                   target="_blank"
                   rel="noopener"
                   style="display: inline-block; text-decoration: none; text-align: center;">
                    <?php esc_html_e('Upgrade to Agency', 'ai-alt-gpt'); ?>
                </a>
            </div>
        </div>

        <div class="alttextai-upgrade-modal__credits">
            <p><?php printf(esc_html__('Need a quick top-up? Buy 100 extra credits for %s (one-time, no subscription).', 'ai-alt-gpt'), esc_html($currency['symbol'] . number_format($currency['credits'], 2))); ?></p>
            <a href="<?php echo esc_url($credits_url); ?>"
               class="alttextai-plan-button alttextai-plan-button--credits alttextai-upgrade-test-link"
               data-plan="credits"
               data-price-id="<?php echo esc_attr($credits_price_id); ?>"
               target="_blank"
               rel="noopener"
               style="display: inline-block; text-decoration: none; text-align: center;">
                <?php esc_html_e('Buy 100 Credits – $11.99', 'ai-alt-gpt'); ?> →
            </a>
        </div>

        <div class="alttextai-upgrade-modal__trust">
            <p>🔒 <?php esc_html_e('Secure payment via Stripe', 'ai-alt-gpt'); ?></p>
            <p>💳 <?php esc_html_e('Cancel anytime', 'ai-alt-gpt'); ?></p>
            <p>✨ <?php esc_html_e('Instant activation', 'ai-alt-gpt'); ?></p>
        </div>
    </div>
</div>
