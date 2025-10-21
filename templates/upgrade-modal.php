<?php
/**
 * Upgrade Modal Template
 * New SaaS-style upgrade flow
 */

if (!defined('ABSPATH')) { exit; }

$stats = AltText_AI_Usage_Tracker::get_stats_display();
$remaining = isset($stats['remaining']) ? intval($stats['remaining']) : 0;
$limit = isset($stats['limit']) ? intval($stats['limit']) : 0;
$reset_date = isset($stats['reset_date']) ? $stats['reset_date'] : '';

$billing_checkout_base = home_url('/billing/checkout');
$pro_checkout_url = esc_url(add_query_arg('plan', 'pro', $billing_checkout_base));
$credits_checkout_url = esc_url(add_query_arg('type', 'credits', $billing_checkout_base));

if ($remaining <= 0) {
    $modal_message = sprintf(
        /* translators: %s: Reset date */
        esc_html__('🎯 You’ve reached your free limit. Upgrade to keep generating AI-perfect alt text (resets %s).', 'ai-alt-gpt'),
        esc_html($reset_date)
    );
} else {
    $modal_message = sprintf(
        /* translators: 1: Remaining credits, 2: Reset date */
        esc_html__('Only %1$d credits remain. Lock in unlimited AI power before your quota resets on %2$s.', 'ai-alt-gpt'),
        $remaining,
        esc_html($reset_date)
    );
}
?>

<div id="alttextai-upgrade-modal" class="alttextai-modal" style="display:none;">
    <div class="alttextai-modal-backdrop" data-action="close-modal"></div>
    <div class="alttextai-upgrade-modal__content">
        <button class="alttextai-modal__close" data-action="close-modal" aria-label="<?php esc_attr_e('Close upgrade modal', 'ai-alt-gpt'); ?>">×</button>
        <div class="alttextai-upgrade-modal__header">
            <span class="alttextai-upgrade-modal__icon">🚀</span>
            <h2><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></h2>
            <p><?php echo $modal_message; ?></p>
        </div>
        <div class="alttextai-plan-grid">
            <div class="alttextai-plan-card">
                <div class="alttextai-plan-card__label"><?php esc_html_e('Free', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">£0<span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span></div>
                <ul>
                    <li>✅ <?php esc_html_e('10 images every month', 'ai-alt-gpt'); ?></li>
                    <li>🎯 <?php esc_html_e('Smart, descriptive alt text', 'ai-alt-gpt'); ?></li>
                    <li>📸 <?php esc_html_e('Media Library integration', 'ai-alt-gpt'); ?></li>
                </ul>
            </div>
            <div class="alttextai-plan-card alttextai-plan-card--featured">
                <div class="alttextai-plan-pill"><?php esc_html_e('Best Value', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__label"><?php esc_html_e('Pro', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">£12.99<span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span></div>
                <ul>
                    <li>♾️ <?php esc_html_e('Unlimited AI generations', 'ai-alt-gpt'); ?></li>
                    <li>⚡ <?php esc_html_e('Priority processing & reviews', 'ai-alt-gpt'); ?></li>
                    <li>🔑 <?php esc_html_e('Keyword optimisation booster', 'ai-alt-gpt'); ?></li>
                </ul>
            </div>
            <div class="alttextai-plan-card">
                <div class="alttextai-plan-card__label"><?php esc_html_e('Agency', 'ai-alt-gpt'); ?></div>
                <div class="alttextai-plan-card__price">£29<span><?php esc_html_e('/month', 'ai-alt-gpt'); ?></span></div>
                <ul>
                    <li>🏢 <?php esc_html_e('Up to 5 connected sites', 'ai-alt-gpt'); ?></li>
                    <li>👥 <?php esc_html_e('Team seats & approvals', 'ai-alt-gpt'); ?></li>
                    <li>📈 <?php esc_html_e('Client-ready analytics', 'ai-alt-gpt'); ?></li>
                </ul>
            </div>
        </div>
        <div class="alttextai-upgrade-modal__actions">
            <button type="button" class="alttextai-upgrade-primary" data-action="upgrade-plan" data-checkout-url="<?php echo $pro_checkout_url; ?>">
                <?php esc_html_e('Upgrade for £12.99/month', 'ai-alt-gpt'); ?>
            </button>
            <button type="button" class="alttextai-upgrade-secondary" data-action="buy-credits" data-checkout-url="<?php echo $credits_checkout_url; ?>">
                <?php esc_html_e('Buy 100 extra credits (£5)', 'ai-alt-gpt'); ?>
            </button>
            <p class="alttextai-upgrade-modal__footnote"><?php esc_html_e('No contracts. Cancel anytime.', 'ai-alt-gpt'); ?></p>
        </div>
    </div>
</div>
