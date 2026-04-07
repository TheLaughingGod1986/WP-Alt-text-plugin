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

$bbai_is_free_plan = ('free' === $bbai_current_plan);
$bbai_is_growth_plan = ('growth' === $bbai_current_plan);
$bbai_is_agency_plan = ('agency' === $bbai_current_plan);

if (!class_exists(\BeepBeepAI\AltTextGenerator\Auth_State::class, false)) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
}
if (!class_exists(\BeepBeepAI\AltTextGenerator\Services\Upgrade_Cta_Resolver::class, false)) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-cta-resolver.php';
}
$bbai_modal_auth_ctx = (isset($this) && is_object($this) && isset($this->api_client))
    ? \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client)
    : ['has_connected_account' => false];
$bbai_modal_has_saas = !empty($bbai_modal_auth_ctx['has_connected_account']);
$bbai_upgrade_cta_resolved = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Cta_Resolver::resolve(
    [
        'has_connected_account' => $bbai_modal_has_saas,
        'plan_slug'             => $bbai_current_plan,
        'credits_remaining'     => $bbai_usage_remaining,
    ]
);
$bbai_modal_signup_first = ( \BeepBeepAI\AltTextGenerator\Services\Upgrade_Cta_Resolver::STATE_LOGGED_OUT === $bbai_upgrade_cta_resolved['state'] );
// Logged-out sites still report plan "free" from defaults; never treat that as an authenticated free plan.
$bbai_on_authenticated_free = $bbai_modal_has_saas && $bbai_is_free_plan;

$bbai_modal_title = $bbai_on_authenticated_free
    ? __('Continue improving your library', 'beepbeep-ai-alt-text-generator')
    : __('Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator');

$bbai_modal_subtitle = $bbai_on_authenticated_free
    ? sprintf(
        /* translators: 1: used credits, 2: credit limit */
        __('You\'ve already improved %1$s of %2$s images this month. Keep your images optimised automatically as you continue.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_used),
        number_format_i18n($bbai_usage_limit)
    )
    : __('Compare plans, manage billing, or add one-time credits for smaller batches.', 'beepbeep-ai-alt-text-generator');

$bbai_locked_modal_title = $bbai_on_authenticated_free
    ? __('You’ve used this month’s free allowance', 'beepbeep-ai-alt-text-generator')
    : __('Manage your BeepBeep AI plan', 'beepbeep-ai-alt-text-generator');

$bbai_locked_modal_subtitle = $bbai_on_authenticated_free
    ? sprintf(
        /* translators: 1: used credits, 2: credit limit */
        __('You\'ve already improved %1$s of %2$s images. Your existing results are still available to review.', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_used),
        number_format_i18n($bbai_usage_limit)
    )
    : __('Compare plans, manage billing, or add one-time credits for smaller batches.', 'beepbeep-ai-alt-text-generator');

$bbai_credit_pack_price = number_format((float) ($bbai_currency['credits'] ?? 9.99), 2);
$bbai_credit_pack_button_label = __('Buy more credits', 'beepbeep-ai-alt-text-generator');
$bbai_credit_pack_title = __('Buy 100 extra credits', 'beepbeep-ai-alt-text-generator');
$bbai_credit_pack_description = __('A one-time top-up for occasional usage.', 'beepbeep-ai-alt-text-generator');
$bbai_credit_pack_summary = sprintf(
    /* translators: 1: currency symbol, 2: credit pack price */
    __('%1$s%2$s one-time', 'beepbeep-ai-alt-text-generator'),
    $bbai_currency['symbol'],
    $bbai_credit_pack_price
);

$bbai_primary_button_label = $bbai_on_authenticated_free
    ? __('Upgrade to Growth', 'beepbeep-ai-alt-text-generator')
    : __('Open billing portal', 'beepbeep-ai-alt-text-generator');

$bbai_primary_price = $bbai_on_authenticated_free
    ? sprintf(
        /* translators: 1: currency symbol, 2: monthly price */
        __('%1$s%2$s/month', 'beepbeep-ai-alt-text-generator'),
        $bbai_currency['symbol'],
        number_format($bbai_growth_monthly, 2)
    )
    : __('Subscription settings', 'beepbeep-ai-alt-text-generator');

$bbai_decision_eyebrow = __('Recommended', 'beepbeep-ai-alt-text-generator');
$bbai_decision_title = $bbai_on_authenticated_free
    ? __('Keep your images optimised automatically', 'beepbeep-ai-alt-text-generator')
    : __('Manage billing', 'beepbeep-ai-alt-text-generator');
$bbai_decision_copy = $bbai_on_authenticated_free
    ? sprintf(
        /* translators: 1: currency symbol, 2: monthly Growth price */
        __('Unlock more monthly ALT text generation and keep automation running — %1$s%2$s/month', 'beepbeep-ai-alt-text-generator'),
        $bbai_currency['symbol'],
        number_format($bbai_growth_monthly, 2)
    )
    : __('Open your billing portal to manage your plan, payment method, or subscription.', 'beepbeep-ai-alt-text-generator');

$bbai_limit_label = $bbai_on_authenticated_free
    ? __('Continue generating ALT text', 'beepbeep-ai-alt-text-generator')
    : __('Usage limit reached', 'beepbeep-ai-alt-text-generator');

$bbai_default_decision_note = $bbai_credit_pack_description;
$bbai_locked_decision_eyebrow = __('Recommended', 'beepbeep-ai-alt-text-generator');
$bbai_locked_decision_title = $bbai_decision_title;
$bbai_locked_decision_copy = $bbai_decision_copy;
$bbai_locked_decision_note = $bbai_credit_pack_description;

// Logged-out / no SaaS link: pricing modal must prioritise account creation, never Growth checkout copy.
if ( $bbai_modal_signup_first ) {
	$bbai_modal_copy              = $bbai_upgrade_cta_resolved;
	$bbai_modal_title             = $bbai_modal_copy['modal_title_default'];
	$bbai_modal_subtitle          = $bbai_modal_copy['modal_subtitle_default'];
	$bbai_locked_modal_title      = $bbai_modal_copy['modal_title_default'];
	$bbai_locked_modal_subtitle   = $bbai_modal_copy['modal_subtitle_default'];
	$bbai_primary_button_label    = $bbai_modal_copy['modal_primary_label'];
	$bbai_primary_price           = '';
	$bbai_decision_eyebrow        = __( 'Next step', 'beepbeep-ai-alt-text-generator' );
	$bbai_decision_title          = $bbai_modal_copy['primary_label'];
	$bbai_decision_copy           = $bbai_modal_copy['tooltip_locked'];
	$bbai_limit_label             = __( 'Get started', 'beepbeep-ai-alt-text-generator' );
	$bbai_locked_decision_eyebrow = $bbai_decision_eyebrow;
	$bbai_locked_decision_title   = $bbai_decision_title;
	$bbai_locked_decision_copy    = $bbai_decision_copy;
	$bbai_compare_subtitle        = __( 'Compare plans below. Create your free account first to unlock AI generations.', 'beepbeep-ai-alt-text-generator' );
}

$bbai_compare_plans_label = __('Compare plans', 'beepbeep-ai-alt-text-generator');
$bbai_compare_back_label = __('Back to options', 'beepbeep-ai-alt-text-generator');
$bbai_compare_title = __('Compare plans', 'beepbeep-ai-alt-text-generator');
if ( ! $bbai_modal_signup_first ) {
	$bbai_compare_subtitle = __( 'Free and Growth cover most sites. Agency is available if you need higher volume.', 'beepbeep-ai-alt-text-generator' );
}
$bbai_agency_toggle_label = __('Need higher volume? See Agency', 'beepbeep-ai-alt-text-generator');
$bbai_agency_toggle_hide_label = __('Hide Agency', 'beepbeep-ai-alt-text-generator');
$bbai_show_agency_by_default = $bbai_is_agency_plan;
?>

<div
    id="bbai-upgrade-modal"
    class="bbai-modal-backdrop"
    data-bbai-upgrade-modal="1"
    data-bbai-upgrade-cta-state="<?php echo esc_attr( $bbai_upgrade_cta_resolved['state'] ); ?>"
    data-bbai-upgrade-modal-mode="<?php echo esc_attr( $bbai_upgrade_cta_resolved['modal_mode'] ); ?>"
    data-bbai-has-connected-account="<?php echo $bbai_modal_has_saas ? '1' : '0'; ?>"
    data-bbai-upgrade-context="default"
    data-bbai-upgrade-view="default"
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
    aria-describedby="bbai-upgrade-modal-description"
>
    <div class="bbai-upgrade-modal__content" role="document" tabindex="-1">
        <button type="button" class="bbai-btn bbai-btn-icon-only bbai-upgrade-modal__close" data-bbai-upgrade-close="1" onclick="if(typeof bbaiCloseUpgradeModal==='function'){bbaiCloseUpgradeModal();}else if(typeof alttextaiCloseModal==='function'){alttextaiCloseModal();}else if(typeof bbaiCloseModal==='function'){bbaiCloseModal();}" aria-label="<?php esc_attr_e('Close', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </button>

        <div class="bbai-upgrade-modal__body">
            <section class="bbai-upgrade-modal__view bbai-upgrade-modal__view--default" data-bbai-upgrade-view-panel="default">
                <div class="bbai-upgrade-modal__header">
                    <p class="bbai-upgrade-modal__section-label bbai-upgrade-modal__section-label--muted"><?php echo esc_html($bbai_limit_label); ?></p>
                    <h2 id="bbai-upgrade-modal-title" data-bbai-upgrade-title><?php echo esc_html($bbai_modal_title); ?></h2>
                    <p id="bbai-upgrade-modal-description" class="bbai-upgrade-modal__subtitle" data-bbai-upgrade-subtitle><?php echo esc_html($bbai_modal_subtitle); ?></p>
                </div>

                <div class="bbai-upgrade-modal__action-card bbai-upgrade-modal__action-card--primary">
                    <div class="bbai-upgrade-modal__action-copy">
                        <p class="bbai-upgrade-modal__section-label bbai-upgrade-modal__decision-eyebrow" data-bbai-upgrade-eyebrow><?php echo esc_html($bbai_decision_eyebrow); ?></p>
                        <p class="bbai-upgrade-modal__decision-title" data-bbai-upgrade-decision-title><?php echo esc_html($bbai_decision_title); ?></p>
                        <p class="bbai-upgrade-modal__decision-desc" data-bbai-upgrade-decision-desc><?php echo esc_html($bbai_decision_copy); ?></p>
                    </div>
                    <div class="bbai-upgrade-modal__action-meta">
                        <p class="bbai-upgrade-modal__action-price"><?php echo esc_html($bbai_primary_price); ?></p>
                        <?php if ( $bbai_modal_signup_first ) : ?>
                            <button type="button"
                                    class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-upgrade-modal__primary-action"
                                    data-bbai-upgrade-primary-action="1"
                                    data-action="show-auth-modal"
                                    data-auth-tab="register">
                                <?php echo esc_html($bbai_primary_button_label); ?>
                            </button>
                            <p class="bbai-upgrade-modal__risk-note"><?php esc_html_e('Free to start. No card required for the free plan.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <?php elseif ( $bbai_on_authenticated_free ) : ?>
                            <button type="button"
                                    class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-upgrade-modal__primary-action"
                                    data-bbai-upgrade-primary-action="1"
                                    data-action="checkout-plan"
                                    data-plan="pro"
                                    data-price-id="<?php echo esc_attr($bbai_pro_price_id); ?>"
                                    data-fallback-url="<?php echo esc_url($bbai_stripe_links['pro']); ?>">
                                <?php echo esc_html($bbai_primary_button_label); ?>
                            </button>
                            <p class="bbai-upgrade-modal__risk-note"><?php esc_html_e('Cancel anytime. No lock-in.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <?php else : ?>
                            <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-upgrade-modal__primary-action" data-bbai-upgrade-primary-action="1">
                                <?php echo esc_html($bbai_primary_button_label); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ( ! empty( $bbai_upgrade_cta_resolved['show_credit_pack'] ) ) : ?>
                <div class="bbai-upgrade-modal__action-card bbai-upgrade-modal__action-card--secondary">
                    <div class="bbai-upgrade-modal__action-copy">
                        <p class="bbai-upgrade-modal__section-label"><?php esc_html_e('Alternative', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-upgrade-modal__credit-title"><?php echo esc_html($bbai_credit_pack_title); ?></p>
                        <p class="bbai-upgrade-modal__decision-desc" data-bbai-upgrade-note><?php echo esc_html($bbai_default_decision_note); ?></p>
                    </div>
                    <div class="bbai-upgrade-modal__action-meta bbai-upgrade-modal__action-meta--secondary">
                        <p class="bbai-upgrade-modal__action-price bbai-upgrade-modal__action-price--secondary"><?php echo esc_html($bbai_credit_pack_summary); ?></p>
                        <button type="button"
                                class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-upgrade-modal__secondary-action"
                                data-bbai-upgrade-secondary-action="1"
                                data-action="checkout-plan"
                                data-plan="credits"
                                data-price-id="<?php echo esc_attr($bbai_credits_price_id); ?>"
                                data-fallback-url="<?php echo esc_url($bbai_stripe_links['credits']); ?>">
                            <?php echo esc_html($bbai_credit_pack_button_label); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bbai-upgrade-modal__footer-links">
                    <button type="button"
                            class="bbai-upgrade-modal__footer-link"
                            data-bbai-upgrade-toggle-plans="1"
                            aria-expanded="false"
                            aria-controls="bbai-upgrade-plan-comparison">
                        <?php echo esc_html($bbai_compare_plans_label); ?>
                    </button>
                    <button type="button" class="bbai-upgrade-modal__footer-link bbai-upgrade-modal__footer-link--muted" data-bbai-upgrade-close="1">
                        <?php esc_html_e('Not now', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </section>

            <section id="bbai-upgrade-plan-comparison" class="bbai-upgrade-modal__view bbai-upgrade-modal__view--compare" data-bbai-upgrade-view-panel="compare" data-bbai-upgrade-plan-comparison hidden>
                <div class="bbai-upgrade-modal__compare-header">
                    <div class="bbai-upgrade-modal__compare-copy">
                        <p class="bbai-upgrade-modal__section-label bbai-upgrade-modal__section-label--muted"><?php esc_html_e('Compare', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <h3 class="bbai-upgrade-modal__compare-title"><?php echo esc_html($bbai_compare_title); ?></h3>
                        <p class="bbai-upgrade-modal__compare-subtitle"><?php echo esc_html($bbai_compare_subtitle); ?></p>
                    </div>
                    <button type="button" class="bbai-upgrade-modal__footer-link bbai-upgrade-modal__back-link" data-bbai-upgrade-back="1">
                        <?php echo esc_html($bbai_compare_back_label); ?>
                    </button>
                </div>

                <div class="bbai-upgrade-modal__compare-grid">
                    <div class="bbai-pricing-card bbai-pricing-card--free">
                        <div class="bbai-pricing-card__badges">
                            <span class="bbai-pricing-card__badge bbai-pricing-card__badge--free"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php if ( $bbai_on_authenticated_free ) : ?>
                                <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bbai-pricing-card__intro">
                            <h3 class="bbai-pricing-card__title"><?php esc_html_e('Free', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-pricing-card__descriptor"><?php esc_html_e('For smaller sites', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <ul class="bbai-pricing-card__features">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Basic ALT generation', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Good for smaller libraries', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li class="bbai-pricing-card__feature--muted">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('No automatic optimisation for new uploads', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                        </ul>
                        <div class="bbai-pricing-card__footer">
                            <div class="bbai-pricing-card__price-stack">
                                <div class="bbai-pricing-card__price">
                                    <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                                    <span class="bbai-pricing-card__amount">0</span>
                                </div>
                                <div class="bbai-pricing-card__limit"><?php esc_html_e('50 ALT texts/month', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                            <?php if ( $bbai_on_authenticated_free ) : ?>
                                <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free" disabled>
                                    <?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                            <?php elseif ( $bbai_modal_signup_first ) : ?>
                                <button type="button"
                                        class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free"
                                        data-action="show-auth-modal"
                                        data-auth-tab="register">
                                    <?php echo esc_html( $bbai_upgrade_cta_resolved['primary_label'] ); ?>
                                </button>
                            <?php else : ?>
                                <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--free">
                                    <?php esc_html_e('Manage in billing', 'beepbeep-ai-alt-text-generator'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bbai-pricing-card bbai-pricing-card--growth">
                        <div class="bbai-pricing-card__badges">
                            <span class="bbai-pricing-card__badge bbai-pricing-card__badge--growth"><?php esc_html_e('Recommended', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php if ($bbai_is_growth_plan) : ?>
                                <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bbai-pricing-card__intro">
                            <h3 class="bbai-pricing-card__title"><?php esc_html_e('Growth — Most Popular', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-pricing-card__descriptor"><?php esc_html_e('Best for active WordPress sites', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <ul class="bbai-pricing-card__features">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Fix your entire image library in one click', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Improve accessibility automatically', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Optimise images faster with priority processing', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Rank in multiple languages', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                        </ul>
                        <div class="bbai-pricing-card__footer">
                            <div class="bbai-pricing-card__price-stack">
                                <div class="bbai-pricing-card__price">
                                    <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                                    <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_growth_monthly, 2)); ?></span>
                                    <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                                <div class="bbai-pricing-card__limit"><?php esc_html_e('1,000 images optimised/month', 'beepbeep-ai-alt-text-generator'); ?></div>
                                <div class="bbai-pricing-card__limit-sub"><?php esc_html_e('~£0.012 per image', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                            <?php if ( $bbai_modal_signup_first ) : ?>
                                <p class="bbai-pricing-card__upgrade-trigger"><?php esc_html_e('After you create your free account, you can upgrade to Growth anytime from billing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <button type="button"
                                        class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth"
                                        data-bbai-upgrade-growth-cta="1"
                                        data-action="show-auth-modal"
                                        data-auth-tab="register">
                                    <?php echo esc_html( $bbai_upgrade_cta_resolved['primary_label'] ); ?>
                                </button>
                                <p class="bbai-pricing-card__risk-reversal"><?php esc_html_e('Free to start. Compare plans above anytime.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <?php elseif ( $bbai_on_authenticated_free ) : ?>
                                <p class="bbai-pricing-card__upgrade-trigger"><?php esc_html_e('New images won’t be optimised automatically on the free plan', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <button type="button"
                                        class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth"
                                        data-bbai-upgrade-growth-cta="1"
                                        data-action="checkout-plan"
                                        data-plan="pro"
                                        data-price-id="<?php echo esc_attr($bbai_pro_price_id); ?>"
                                        data-fallback-url="<?php echo esc_url($bbai_stripe_links['pro']); ?>">
                                    <?php esc_html_e('Unlock Full Site Optimisation', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <p class="bbai-pricing-card__risk-reversal"><?php esc_html_e('Cancel anytime. No lock-in.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <?php else : ?>
                                <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--growth" data-bbai-upgrade-growth-cta="1">
                                    <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!$bbai_show_agency_by_default) : ?>
                    <div class="bbai-upgrade-modal__agency-toggle">
                        <button type="button"
                                class="bbai-upgrade-modal__footer-link bbai-upgrade-modal__footer-link--muted"
                                data-bbai-upgrade-toggle-agency="1"
                                data-bbai-upgrade-show-label="<?php echo esc_attr($bbai_agency_toggle_label); ?>"
                                data-bbai-upgrade-hide-label="<?php echo esc_attr($bbai_agency_toggle_hide_label); ?>"
                                data-bbai-upgrade-initial-expanded="false"
                                aria-expanded="false"
                                aria-controls="bbai-upgrade-agency-panel">
                            <?php echo esc_html($bbai_agency_toggle_label); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div id="bbai-upgrade-agency-panel" class="bbai-upgrade-modal__agency-panel" data-bbai-upgrade-agency-panel<?php echo $bbai_show_agency_by_default ? '' : ' hidden'; ?>>
                    <div class="bbai-pricing-card bbai-pricing-card--agency">
                        <div class="bbai-pricing-card__badges">
                            <span class="bbai-pricing-card__badge bbai-pricing-card__badge--agency"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php if ($bbai_is_agency_plan) : ?>
                                <span class="bbai-pricing-card__status"><?php esc_html_e('Current plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bbai-pricing-card__intro">
                            <h3 class="bbai-pricing-card__title"><?php esc_html_e('Agency', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-pricing-card__descriptor"><?php esc_html_e('For agencies and multi-site teams', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <ul class="bbai-pricing-card__features">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Multi-site bulk optimisation', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Client usage reporting', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Flexible billing control', 'beepbeep-ai-alt-text-generator'); ?>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="bbai-feature-coming-soon"><?php esc_html_e('White-label support coming soon', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </li>
                        </ul>
                        <div class="bbai-pricing-card__footer">
                            <div class="bbai-pricing-card__price-stack">
                                <div class="bbai-pricing-card__price">
                                    <span class="bbai-pricing-card__currency"><?php echo esc_html($bbai_currency['symbol']); ?></span>
                                    <span class="bbai-pricing-card__amount"><?php echo esc_html(number_format($bbai_agency_monthly, 2)); ?></span>
                                    <span class="bbai-pricing-card__period"><?php esc_html_e('/month', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                                <div class="bbai-pricing-card__limit"><?php esc_html_e('10,000+ ALT texts/month', 'beepbeep-ai-alt-text-generator'); ?></div>
                            </div>
                            <?php if ($bbai_is_agency_plan) : ?>
                                <a href="<?php echo esc_url($bbai_billing_url); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency">
                                    <?php esc_html_e('Manage billing', 'beepbeep-ai-alt-text-generator'); ?>
                                </a>
                            <?php elseif ( $bbai_modal_signup_first ) : ?>
                                <button type="button"
                                        class="bbai-btn bbai-btn-primary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency"
                                        data-action="show-auth-modal"
                                        data-auth-tab="register">
                                    <?php echo esc_html( $bbai_upgrade_cta_resolved['primary_label'] ); ?>
                                </button>
                            <?php else : ?>
                                <button type="button"
                                        class="bbai-btn bbai-btn-secondary bbai-btn-lg bbai-btn-block bbai-pricing-card__btn bbai-pricing-card__btn--agency"
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

                <div class="bbai-upgrade-modal__footer-links bbai-upgrade-modal__footer-links--compare">
                    <button type="button" class="bbai-upgrade-modal__footer-link" data-bbai-upgrade-back="1">
                        <?php echo esc_html($bbai_compare_back_label); ?>
                    </button>
                    <button type="button" class="bbai-upgrade-modal__footer-link bbai-upgrade-modal__footer-link--muted" data-bbai-upgrade-close="1">
                        <?php esc_html_e('Not now', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </section>
        </div>
    </div>
</div>
