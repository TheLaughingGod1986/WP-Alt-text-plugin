<?php
/**
 * Upgrade Modal Template - Premium SaaS Design
 * Matches new Dashboard, Analytics, Library, Settings design system
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Get current plan from API client
$bbai_current_plan = 'free';
$bbai_usage_data = [];
try {
    if (isset($this->api_client) && is_object($this->api_client) && method_exists($this->api_client, 'get_usage')) {
        $bbai_usage_data = $this->api_client->get_usage();
        if (!is_wp_error($bbai_usage_data) && is_array($bbai_usage_data) && isset($bbai_usage_data['plan'])) {
            $bbai_current_plan = strtolower($bbai_usage_data['plan']);
        }
    }
} catch (Exception $e) {
    // Silently fail, use default free plan
}
// Map 'pro' to 'growth' for consistency
if ($bbai_current_plan === 'pro') {
    $bbai_current_plan = 'growth';
}

// Get price IDs from backend API
$bbai_pro_price_id = $checkout_prices['pro'] ?? '';
$bbai_agency_price_id = $checkout_prices['agency'] ?? '';
$bbai_credits_price_id = $checkout_prices['credits'] ?? '';

// Fallback to hardcoded Stripe links if API price IDs not available
$bbai_stripe_links = [
    'pro' => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
    'agency' => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
    'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00'
];

// Currency - Default to GBP, but support detection
$bbai_currency = $bbai_currency ?? ['symbol' => '£', 'code' => 'GBP', 'free' => 0, 'growth' => 12.99, 'pro' => 12.99, 'agency' => 49.99, 'credits' => 19.99];

// Calculate annual prices (2 months free = 10 months of monthly price)
$bbai_growth_monthly = $bbai_currency['growth'] ?? 12.99;
$bbai_growth_annual = round($bbai_growth_monthly * 10, 2);
$bbai_agency_monthly = $bbai_currency['agency'] ?? 49.99;
$bbai_agency_annual = round($bbai_agency_monthly * 10, 2);

// Calculate annual savings (20% = 2 months free)
$bbai_growth_annual_savings = round(($bbai_growth_monthly * 12) - $bbai_growth_annual, 2);
$bbai_agency_annual_savings = round(($bbai_agency_monthly * 12) - $bbai_agency_annual, 2);

// Billing portal URL for current plan management
$bbai_billing_url = admin_url('admin.php?page=bbai-billing');

$bbai_usage_limit = isset($bbai_usage_data['limit']) && is_numeric($bbai_usage_data['limit']) ? max(1, (int) $bbai_usage_data['limit']) : 50;
$bbai_usage_used = isset($bbai_usage_data['used']) && is_numeric($bbai_usage_data['used']) ? max(0, (int) $bbai_usage_data['used']) : 0;
$bbai_usage_remaining = isset($bbai_usage_data['remaining']) && is_numeric($bbai_usage_data['remaining']) ? max(0, (int) $bbai_usage_data['remaining']) : max(0, $bbai_usage_limit - $bbai_usage_used);

if (isset($bbai_usage_stats) && is_array($bbai_usage_stats)) {
    if (isset($bbai_usage_stats['limit']) && is_numeric($bbai_usage_stats['limit'])) {
        $bbai_usage_limit = max(1, (int) $bbai_usage_stats['limit']);
    }
    if (isset($bbai_usage_stats['used']) && is_numeric($bbai_usage_stats['used'])) {
        $bbai_usage_used = max(0, (int) $bbai_usage_stats['used']);
    }
    if (isset($bbai_usage_stats['remaining']) && is_numeric($bbai_usage_stats['remaining'])) {
        $bbai_usage_remaining = max(0, (int) $bbai_usage_stats['remaining']);
    }
}

$bbai_usage_used = min($bbai_usage_used, $bbai_usage_limit);
$bbai_is_usage_triggered = ('free' === $bbai_current_plan) && $bbai_usage_remaining <= 0;
$bbai_problem_images = 0;
$bbai_modal_optimized_count = 0;
$bbai_modal_library_size = 0;

if (isset($bbai_state) && is_array($bbai_state)) {
    $bbai_problem_images = max(
        0,
        (int) ($bbai_state['missing_alts'] ?? 0) + (int) ($bbai_state['needs_review_count'] ?? 0)
    );
    $bbai_modal_optimized_count = max(0, (int) ($bbai_state['optimized_count'] ?? 0));
    $bbai_modal_library_size = max(0, (int) ($bbai_state['total_images'] ?? 0));
} elseif (isset($bbai_stats) && is_array($bbai_stats)) {
    $bbai_problem_images = max(
        0,
        (int) ($bbai_stats['missing_alts'] ?? $bbai_stats['images_without_alt'] ?? 0) + (int) ($bbai_stats['needs_review_count'] ?? 0)
    );
    $bbai_modal_optimized_count = max(0, (int) ($bbai_stats['optimized_count'] ?? $bbai_stats['with_alt'] ?? 0));
    $bbai_modal_library_size = max(0, (int) ($bbai_stats['total_images'] ?? $bbai_stats['total'] ?? 0));
}

$bbai_modal_title = ('free' === $bbai_current_plan)
    ? __('You\'re almost out of free ALT credits', 'beepbeep-ai-alt-text-generator')
    : __('Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator');

$bbai_modal_subtitle = ('free' === $bbai_current_plan)
    ? __('Upgrade to continue generating ALT text automatically.', 'beepbeep-ai-alt-text-generator')
    : __('Manage billing or add one-time credits for smaller batches.', 'beepbeep-ai-alt-text-generator');

if ($bbai_is_usage_triggered && 'free' === $bbai_current_plan) {
    $bbai_modal_title = __('You\'re out of free ALT credits', 'beepbeep-ai-alt-text-generator');
}

$bbai_usage_status = sprintf(
    /* translators: 1: used credits, 2: credit limit */
    ('free' === $bbai_current_plan)
        ? __('%1$s of %2$s free credits used', 'beepbeep-ai-alt-text-generator')
        : __('%1$s of %2$s credits used', 'beepbeep-ai-alt-text-generator'),
    number_format_i18n($bbai_usage_used),
    number_format_i18n($bbai_usage_limit)
);

$bbai_problem_status = '';
if ($bbai_problem_images > 0) {
    $bbai_problem_status = $bbai_is_usage_triggered
        ? sprintf(
            /* translators: %s: number of blocked images */
            _n('%s image blocked', '%s images blocked', $bbai_problem_images, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_problem_images)
        )
        : sprintf(
            /* translators: %s: number of images that still need ALT text */
            _n('%s image needs ALT text', '%s images need ALT text', $bbai_problem_images, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_problem_images)
        );
}

$bbai_credit_pack_price = number_format((float) ($bbai_currency['credits'] ?? 9.99), 2);
$bbai_credit_pack_cta = sprintf(
    /* translators: 1: currency symbol, 2: credit pack price */
    __('Buy 100 credits for %1$s%2$s', 'beepbeep-ai-alt-text-generator'),
    $bbai_currency['symbol'],
    $bbai_credit_pack_price
);
$bbai_credit_pack_title = __('Just need a few more images?', 'beepbeep-ai-alt-text-generator');
$bbai_credit_pack_summary = sprintf(
    /* translators: 1: currency symbol, 2: credit pack price */
    __('100 ALT texts – %1$s%2$s', 'beepbeep-ai-alt-text-generator'),
    $bbai_currency['symbol'],
    $bbai_credit_pack_price
);

$bbai_decision_eyebrow = ('free' === $bbai_current_plan)
    ? __('Recommended', 'beepbeep-ai-alt-text-generator')
    : __('Account', 'beepbeep-ai-alt-text-generator');

$bbai_free_decision_title = __('You\'ve already started optimizing your images.', 'beepbeep-ai-alt-text-generator');
if ($bbai_modal_optimized_count > 0) {
    $bbai_free_decision_title = sprintf(
        _n(
            'You\'ve already optimized %s image.',
            'You\'ve already optimized %s images.',
            $bbai_modal_optimized_count,
            'beepbeep-ai-alt-text-generator'
        ),
        number_format_i18n($bbai_modal_optimized_count)
    );
} elseif ($bbai_usage_used > 0) {
    $bbai_free_decision_title = sprintf(
        _n(
            'You\'ve already used %s free ALT credit.',
            'You\'ve already used %s free ALT credits.',
            $bbai_usage_used,
            'beepbeep-ai-alt-text-generator'
        ),
        number_format_i18n($bbai_usage_used)
    );
}

$bbai_free_decision_copy = __('Upgrade to Growth to remove the 50 image limit and optimize your entire media library automatically.', 'beepbeep-ai-alt-text-generator');
$bbai_free_decision_meta = __('Most WordPress sites save hours writing ALT text with Growth.', 'beepbeep-ai-alt-text-generator');

$bbai_decision_title = ('free' === $bbai_current_plan)
    ? $bbai_free_decision_title
    : __('Manage your plan or add one-time credits', 'beepbeep-ai-alt-text-generator');

$bbai_decision_copy = ('free' === $bbai_current_plan)
    ? $bbai_free_decision_copy
    : __('Open billing for plan changes, or buy credits for smaller batches that do not expire.', 'beepbeep-ai-alt-text-generator');

$bbai_default_decision_note = __('One-time credits for smaller batches. They do not expire.', 'beepbeep-ai-alt-text-generator');
$bbai_locked_modal_title = ('free' === $bbai_current_plan)
    ? __('You\'re out of free ALT credits', 'beepbeep-ai-alt-text-generator')
    : __('Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator');
$bbai_locked_modal_subtitle = ('free' === $bbai_current_plan)
    ? __('Upgrade to continue generating ALT text automatically.', 'beepbeep-ai-alt-text-generator')
    : __('Manage billing or add one-time credits for smaller batches.', 'beepbeep-ai-alt-text-generator');
$bbai_locked_decision_eyebrow = __('Monthly limit reached', 'beepbeep-ai-alt-text-generator');
$bbai_locked_decision_title = ('free' === $bbai_current_plan)
    ? $bbai_free_decision_title
    : __('Upgrade to keep generating ALT text today', 'beepbeep-ai-alt-text-generator');
$bbai_locked_decision_copy = ('free' === $bbai_current_plan)
    ? $bbai_free_decision_copy
    : __('Unlock 1,000 ALT texts per month, bulk optimization, and priority processing.', 'beepbeep-ai-alt-text-generator');
$bbai_locked_decision_note = __('One-time credits for smaller batches. They do not expire.', 'beepbeep-ai-alt-text-generator');
?>

<div
    id="bbai-upgrade-modal"
    class="bbai-modal-backdrop"
    data-bbai-upgrade-modal="1"
    data-bbai-upgrade-context="default"
    data-bbai-upgrade-default-title="<?php echo esc_attr($bbai_modal_title); ?>"
    data-bbai-upgrade-default-subtitle="<?php echo esc_attr($bbai_modal_subtitle); ?>"
    data-bbai-upgrade-default-eyebrow="<?php echo esc_attr($bbai_decision_eyebrow); ?>"
    data-bbai-upgrade-default-decision-title="<?php echo esc_attr($bbai_decision_title); ?>"
    data-bbai-upgrade-default-decision-desc="<?php echo esc_attr($bbai_decision_copy); ?>"
    data-bbai-upgrade-default-note="<?php echo esc_attr($bbai_default_decision_note); ?>"
    data-bbai-upgrade-locked-title="<?php echo esc_attr($bbai_locked_modal_title); ?>"
    data-bbai-upgrade-locked-subtitle="<?php echo esc_attr($bbai_locked_modal_subtitle); ?>"
    data-bbai-upgrade-locked-eyebrow="<?php echo esc_attr($bbai_locked_decision_eyebrow); ?>"
    data-bbai-upgrade-locked-decision-title="<?php echo esc_attr($bbai_locked_decision_title); ?>"
    data-bbai-upgrade-locked-decision-desc="<?php echo esc_attr($bbai_locked_decision_copy); ?>"
    data-bbai-upgrade-locked-note="<?php echo esc_attr($bbai_locked_decision_note); ?>"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-hidden="true"
    aria-labelledby="bbai-upgrade-modal-title"
>
    <div class="bbai-upgrade-modal__content">
        <button type="button" class="bbai-btn bbai-btn-icon-only bbai-upgrade-modal__close" onclick="if(typeof bbaiCloseUpgradeModal==='function'){bbaiCloseUpgradeModal();}else if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <div class="bbai-upgrade-modal__body">
            <div class="bbai-upgrade-modal__header">
                <h2 id="bbai-upgrade-modal-title" data-bbai-upgrade-title><?php echo esc_html($bbai_modal_title); ?></h2>
                <p class="bbai-upgrade-modal__subtitle" data-bbai-upgrade-subtitle><?php echo esc_html($bbai_modal_subtitle); ?></p>
                <div class="bbai-upgrade-modal__status" aria-label="<?php esc_attr_e('Usage summary', 'beepbeep-ai-alt-text-generator'); ?>">
                    <span class="bbai-upgrade-modal__status-item"><?php echo esc_html($bbai_usage_status); ?></span>
                    <?php if ('' !== $bbai_problem_status) : ?>
                        <span class="bbai-upgrade-modal__status-item"><?php echo esc_html($bbai_problem_status); ?></span>
                    <?php endif; ?>
                    <span class="bbai-upgrade-modal__status-item"><?php esc_html_e('Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
            </div>

            <div class="bbai-upgrade-modal__decision">
                <div class="bbai-upgrade-modal__decision-copy">
                    <p class="bbai-upgrade-modal__decision-eyebrow" data-bbai-upgrade-eyebrow><?php echo esc_html($bbai_decision_eyebrow); ?></p>
                    <p class="bbai-upgrade-modal__decision-title" data-bbai-upgrade-decision-title><?php echo esc_html($bbai_decision_title); ?></p>
                    <p class="bbai-upgrade-modal__decision-desc" data-bbai-upgrade-decision-desc><?php echo esc_html($bbai_decision_copy); ?></p>
                    <?php if ('' !== $bbai_free_decision_meta && 'free' === $bbai_current_plan) : ?>
                        <p class="bbai-upgrade-modal__decision-meta"><?php echo esc_html($bbai_free_decision_meta); ?></p>
                    <?php endif; ?>
                </div>
                <div class="bbai-upgrade-modal__decision-actions">
                    <div class="bbai-upgrade-modal__credit-copy">
                        <p class="bbai-upgrade-modal__credit-title"><?php echo esc_html($bbai_credit_pack_title); ?></p>
                        <p class="bbai-upgrade-modal__credit-price"><?php echo esc_html($bbai_credit_pack_summary); ?></p>
                        <p class="bbai-upgrade-modal__decision-note" data-bbai-upgrade-note><?php echo esc_html($bbai_default_decision_note); ?></p>
                    </div>
                    <div class="bbai-upgrade-modal__decision-buttons">
                        <?php if ('free' === $bbai_current_plan) : ?>
                            <div class="bbai-upgrade-modal__primary-stack">
                                <button type="button"
                                        class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-upgrade-modal__primary-action"
                                        data-action="checkout-plan"
                                        data-plan="pro"
                                        data-price-id="<?php echo esc_attr($bbai_pro_price_id); ?>"
                                        data-fallback-url="<?php echo esc_url($bbai_stripe_links['pro']); ?>">
                                    <?php esc_html_e('Start Growth', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <p class="bbai-upgrade-modal__primary-trust"><?php esc_html_e('Secure checkout • Cancel anytime • No lock-in', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        <?php else : ?>
                            <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-upgrade-modal__primary-action">
                                <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php endif; ?>
                        <button type="button"
                                class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-upgrade-modal__secondary-action"
                                data-action="checkout-plan"
                                data-plan="credits"
                                data-price-id="<?php echo esc_attr($bbai_credits_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['credits']); ?>">
                            <?php echo esc_html($bbai_credit_pack_cta); ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="bbai-pricing-grid">
                <div class="bbai-pricing-card bbai-pricing-card--free">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--free"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Best for trying BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount">0</span>
                    </div>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('50 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Basic bulk generation', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Great for small sites or testing', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'free') : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free" disabled>
                            <?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free">
                            <?php esc_html_e('Continue with Free', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="bbai-pricing-card bbai-pricing-card--growth">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--growth"><?php esc_html_e('Most popular', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php if ($bbai_current_plan === 'growth') : ?>
                            <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Perfect for most WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_growth_monthly, 2)); ?></span>
                        <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <p class="bbai-pricing-card__price-context"><?php esc_html_e('≈ 1.3p per ALT text', 'beepbeep-ai-alt-text-generator'); ?></p>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('1,000 AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <p class="bbai-pricing-card__billing">
                        <?php
                        /* translators: 1: annual price */
                        echo esc_html(sprintf(__('or £%s billed annually (2 months free)', 'beepbeep-ai-alt-text-generator'), number_format($bbai_growth_annual, 2)));
                        ?>
                    </p>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Bulk media library optimization', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Fix accessibility issues faster', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Priority queue processing', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'growth') : ?>
                        <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth">
                            <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php else : ?>
                        <button type="button"
                                class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth"
                                data-action="checkout-plan"
                                data-plan="pro"
                                data-price-id="<?php echo esc_attr($bbai_pro_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['pro']); ?>">
                            <?php esc_html_e('Start Growth', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                        <p class="bbai-pricing-card__trust"><?php esc_html_e('Secure checkout • Cancel anytime • No lock-in', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="bbai-pricing-card bbai-pricing-card--agency">
                    <div class="bbai-pricing-card__badges">
                        <span class="bbai-pricing-card__badge bbai-pricing-card__badge--agency"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php if ($bbai_current_plan === 'agency') : ?>
                            <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bbai-pricing-card__intro">
                        <h3 class="bbai-pricing-card__title"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Built for agencies managing multiple WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-pricing-card__price">
                        <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                        <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_agency_monthly, 2)); ?></span>
                        <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    
                    <p class="bbai-pricing-card__billing">
                        <?php
                        /* translators: 1: annual price */
                        echo esc_html(sprintf(__('or £%s billed annually (2 months free)', 'beepbeep-ai-alt-text-generator'), number_format($bbai_agency_annual, 2)));
                        ?>
                    </p>

                    <div class="bbai-pricing-card__limit"><?php esc_html_e('10,000+ AI alt texts per month', 'beepbeep-ai-alt-text-generator'); ?></div>

                    <ul class="bbai-pricing-card__features">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Multi-site bulk optimization', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Client usage reporting', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Cancel or downgrade anytime', 'beepbeep-ai-alt-text-generator'); ?>
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="bbai-feature-coming-soon"><?php esc_html_e('White-label support (coming soon)', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </li>
                    </ul>

                    <?php if ($bbai_current_plan === 'agency') : ?>
                        <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency">
                            <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php else : ?>
                        <button type="button"
                                class="bbai-btn bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency"
                                data-action="checkout-plan"
                                data-plan="agency"
                                data-price-id="<?php echo esc_attr($bbai_agency_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['agency']); ?>">
                            <?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
