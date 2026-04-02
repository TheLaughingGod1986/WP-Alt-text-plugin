<?php
/**
 * Quick Actions Panel - First-action guidance for Dashboard and ALT Library
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
$bbai_library_optimized_url = add_query_arg(['page' => 'bbai-library', 'status' => 'optimized'], admin_url('admin.php'));
$bbai_library_missing_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
$bbai_library_needs_review_url = function_exists('bbai_alt_library_needs_review_url') ? bbai_alt_library_needs_review_url() : add_query_arg(['page' => 'bbai-library', 'status' => 'needs_review'], admin_url('admin.php'));
$bbai_quick_actions_context = isset($bbai_quick_actions_context) ? (string) $bbai_quick_actions_context : 'library';

// Derive from $bbai_stats or $bbai_state depending on context
$bbai_missing = isset($bbai_stats['missing']) ? max(0, (int) $bbai_stats['missing']) : (isset($bbai_missing_count) ? max(0, (int) $bbai_missing_count) : 0);
$bbai_total = isset($bbai_stats['total']) ? max(0, (int) $bbai_stats['total']) : (isset($bbai_total_images) ? max(0, (int) $bbai_total_images) : 0);
$bbai_with_alt = isset($bbai_stats['with_alt']) ? max(0, (int) $bbai_stats['with_alt']) : (isset($bbai_with_alt_count) ? max(0, (int) $bbai_with_alt_count) : 0);
$bbai_weak = isset($bbai_state['needs_review_count']) ? max(0, (int) $bbai_state['needs_review_count']) : 0;

$bbai_limit_reached = isset($bbai_limit_reached_state) ? $bbai_limit_reached_state : (isset($bbai_state['is_out_of_credits']) ? $bbai_state['is_out_of_credits'] : false);
$bbai_generate_locked = $bbai_limit_reached;
$bbai_generate_disabled = $bbai_missing <= 0 && !$bbai_limit_reached;
$bbai_has_available_credits = isset($bbai_state['has_available_credits']) ? !empty($bbai_state['has_available_credits']) : !$bbai_limit_reached;
$bbai_has_scan_results = isset($bbai_state['has_scan_results']) ? !empty($bbai_state['has_scan_results']) : ($bbai_total > 0 || $bbai_missing > 0 || $bbai_weak > 0 || $bbai_with_alt > 0);
$bbai_primary_action = isset($bbai_state['primary_action']) ? (string) $bbai_state['primary_action'] : 'scan';
$bbai_remaining_credits = isset($bbai_state['credits_remaining']) ? max(0, (int) $bbai_state['credits_remaining']) : 0;
$bbai_is_premium_plan = isset($bbai_state['is_premium']) ? !empty($bbai_state['is_premium']) : (isset($bbai_is_premium) ? !empty($bbai_is_premium) : false);
$bbai_product_state_model = isset($bbai_product_state_model) && is_array($bbai_product_state_model)
    ? $bbai_product_state_model
    : [];
$bbai_state_cta = isset($bbai_product_state_model['cta']) && is_array($bbai_product_state_model['cta'])
    ? $bbai_product_state_model['cta']
    : [];
$bbai_state_copy = isset($bbai_product_state_model['copy']) && is_array($bbai_product_state_model['copy'])
    ? $bbai_product_state_model['copy']
    : [];
$bbai_locked_cta_mode = isset($bbai_state_cta['locked_mode']) ? (string) $bbai_state_cta['locked_mode'] : '';
$bbai_locked_uses_signup = 'create_account' === $bbai_locked_cta_mode;
$bbai_locked_cta_action = $bbai_locked_uses_signup ? 'open-signup' : 'open-upgrade';
$bbai_locked_auth_attr = $bbai_locked_uses_signup ? ' data-auth-tab="register"' : '';
$bbai_locked_cta_label = $bbai_locked_uses_signup
    ? (isset($bbai_state_copy['primary_cta']) ? (string) $bbai_state_copy['primary_cta'] : __('Create free account', 'beepbeep-ai-alt-text-generator'))
    : bbai_copy_cta_upgrade_growth();
$bbai_locked_cta_helper = $bbai_locked_uses_signup
    ? (isset($bbai_state_copy['helper_copy']) ? (string) $bbai_state_copy['helper_copy'] : __('Create a free account to continue.', 'beepbeep-ai-alt-text-generator'))
    : __('Buy more credits', 'beepbeep-ai-alt-text-generator');
$bbai_locked_cta_description = $bbai_locked_uses_signup
    ? (isset($bbai_state_copy['exhausted_body']) ? (string) $bbai_state_copy['exhausted_body'] : __('Create a free account to keep generating ALT text in your dashboard.', 'beepbeep-ai-alt-text-generator'))
    : __('Upgrade to continue generating ALT text in your dashboard.', 'beepbeep-ai-alt-text-generator');
$bbai_build_locked_attrs = static function (string $reason, string $source) use ($bbai_locked_cta_action, $bbai_locked_auth_attr): string {
    return sprintf(
        'data-bbai-action="%1$s" data-bbai-locked-cta="1" data-bbai-lock-control="1" data-bbai-lock-reason="%2$s" data-bbai-locked-source="%3$s" aria-disabled="true"%4$s',
        esc_attr($bbai_locked_cta_action),
        esc_attr($reason),
        esc_attr($source),
        $bbai_locked_auth_attr
    );
};

if ($bbai_quick_actions_context === 'dashboard') :
    $bbai_format_credit_helper = static function (int $required, int $remaining): string {
        if ($remaining >= $required) {
            return sprintf(
                _n('Uses %s credit', 'Uses %s credits', $required, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($required)
            );
        }

        return sprintf(
            _n('%s credit available now', '%s credits available now', $remaining, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($remaining)
        );
    };

    $bbai_generate_is_primary = !$bbai_has_scan_results || $bbai_missing > 0;
    $bbai_review_is_primary = !$bbai_generate_is_primary && $bbai_weak > 0;
    $bbai_bulk_is_primary = !$bbai_generate_is_primary && !$bbai_review_is_primary;

    $bbai_generate_classes = 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--' . ($bbai_generate_is_primary ? 'primary' : 'secondary');
    $bbai_review_classes = 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--' . ($bbai_review_is_primary ? 'primary' : 'secondary');
    $bbai_bulk_classes = 'bbai-dashboard-quick-actions__button bbai-dashboard-quick-actions__button--' . ($bbai_bulk_is_primary ? 'primary' : 'secondary');

    $bbai_generate_label = bbai_copy_cta_scan_media_library();
    $bbai_generate_helper = __('Find images missing ALT text before generating descriptions.', 'beepbeep-ai-alt-text-generator');
    $bbai_generate_attrs = 'href="#" data-bbai-action="scan-opportunity"';
    $bbai_generate_is_complete = $bbai_has_scan_results && $bbai_missing <= 0;
    if ($bbai_has_scan_results && $bbai_missing > 0 && $bbai_limit_reached) {
        $bbai_generate_label = $bbai_locked_cta_label;
        $bbai_generate_helper = $bbai_locked_cta_helper;
        $bbai_generate_attrs = 'href="#" ' . $bbai_build_locked_attrs('generate_missing', 'dashboard-quick-actions-generate');
    } elseif ($bbai_has_scan_results && $bbai_missing > 0) {
        $bbai_generate_label = bbai_copy_cta_generate_missing_images();
        $bbai_generate_helper = $bbai_format_credit_helper($bbai_missing, $bbai_remaining_credits > 0 ? $bbai_remaining_credits : $bbai_missing);
        $bbai_generate_attrs = 'href="#" data-action="generate-missing" data-bbai-action="generate_missing"';
    } elseif ($bbai_generate_is_complete) {
        $bbai_generate_label = bbai_copy_cta_open_alt_library();
        $bbai_generate_helper = __('No images are currently missing ALT text.', 'beepbeep-ai-alt-text-generator');
        $bbai_generate_attrs = 'href="' . esc_url($bbai_library_url) . '" aria-label="' . esc_attr__('Generate Missing ALT complete', 'beepbeep-ai-alt-text-generator') . '"';
    }

    $bbai_review_label = bbai_copy_cta_improve_alt();
    $bbai_review_helper = __('Scan first to find weaker ALT descriptions that need review.', 'beepbeep-ai-alt-text-generator');
    $bbai_review_attrs = 'href="#" aria-disabled="true"';
    $bbai_review_disabled = !$bbai_has_scan_results;
    if ($bbai_has_scan_results && $bbai_weak > 0 && !$bbai_has_available_credits) {
        $bbai_review_label = $bbai_locked_cta_label;
        $bbai_review_helper = $bbai_locked_cta_helper;
        $bbai_review_attrs = 'href="#" ' . $bbai_build_locked_attrs('reoptimize_all', 'dashboard-quick-actions-review');
        $bbai_review_disabled = false;
    } elseif ($bbai_has_scan_results && $bbai_weak > 0) {
        $bbai_review_label = bbai_copy_cta_review_needs_review_filter();
        $bbai_review_helper = __('Open the ALT Library filtered to descriptions that need review.', 'beepbeep-ai-alt-text-generator');
        $bbai_review_attrs = 'href="' . esc_url($bbai_library_needs_review_url) . '" data-bbai-navigation="review-results"';
        $bbai_review_disabled = false;
    } elseif ($bbai_has_scan_results) {
        $bbai_review_label = bbai_copy_cta_open_alt_library();
        $bbai_review_helper = __('No weak ALT descriptions are waiting for review.', 'beepbeep-ai-alt-text-generator');
        $bbai_review_attrs = 'href="' . esc_url($bbai_library_url) . '"';
        $bbai_review_disabled = false;
    }

    $bbai_bulk_label = $bbai_locked_cta_label;
    $bbai_bulk_helper = $bbai_locked_uses_signup
        ? $bbai_locked_cta_description
        : __('Never worry about missing ALT text again.', 'beepbeep-ai-alt-text-generator');
    $bbai_bulk_attrs = 'href="#" ' . $bbai_build_locked_attrs('automation', 'dashboard-quick-actions-bulk');
    if ($bbai_is_premium_plan) {
        if ($bbai_missing > 0) {
            $bbai_bulk_label = bbai_copy_cta_generate_missing_images();
            $bbai_bulk_helper = __('Bulk generation is available on your plan.', 'beepbeep-ai-alt-text-generator');
            $bbai_bulk_attrs = 'href="#" data-action="generate-missing" data-bbai-action="generate_missing"';
        } elseif ($bbai_weak > 0) {
            $bbai_bulk_label = bbai_copy_cta_improve_alt();
            $bbai_bulk_helper = __('Bulk improvements are available on your plan.', 'beepbeep-ai-alt-text-generator');
            $bbai_bulk_attrs = 'href="#" data-action="regenerate-all" data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"';
        } else {
            $bbai_bulk_label = bbai_copy_cta_open_alt_library();
            $bbai_bulk_helper = __('No bulk fixes are needed right now.', 'beepbeep-ai-alt-text-generator');
            $bbai_bulk_attrs = 'href="' . esc_url($bbai_library_url) . '"';
        }
    }
    ?>
    <section class="bbai-dashboard-quick-actions bbai-dashboard-section" aria-label="<?php esc_attr_e('Quick Actions', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-quick-actions>
        <div class="bbai-dashboard-quick-actions__header">
            <div class="bbai-dashboard-quick-actions__copy">
                <p class="bbai-dashboard-quick-actions__eyebrow"><?php esc_html_e('Quick Actions', 'beepbeep-ai-alt-text-generator'); ?></p>
                <p class="bbai-dashboard-quick-actions__title"><?php esc_html_e('Next best actions', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-dashboard-quick-actions__buttons">
                <a
                    <?php echo $bbai_generate_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?>
                    class="<?php echo esc_attr($bbai_generate_classes . ( ( $bbai_has_scan_results && $bbai_missing <= 0 && ! $bbai_limit_reached && ! $bbai_generate_is_complete ) ? ' is-disabled' : '' ) . ( $bbai_generate_is_complete ? ' is-complete' : '' ) ); ?>"
                    data-bbai-quick-action="generate-missing"
                >
                    <span class="bbai-dashboard-quick-actions__button-label" data-bbai-quick-action-label><?php echo esc_html($bbai_generate_label); ?></span>
                    <span class="bbai-dashboard-quick-actions__button-helper" data-bbai-quick-action-helper><?php echo esc_html($bbai_generate_helper); ?></span>
                </a>
                <a
                    <?php echo $bbai_review_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?>
                    class="<?php echo esc_attr($bbai_review_classes . ( $bbai_review_disabled ? ' is-disabled' : '' )); ?>"
                    data-bbai-quick-action="review-weak"
                >
                    <span class="bbai-dashboard-quick-actions__button-label" data-bbai-quick-action-label><?php echo esc_html($bbai_review_label); ?></span>
                    <span class="bbai-dashboard-quick-actions__button-helper" data-bbai-quick-action-helper><?php echo esc_html($bbai_review_helper); ?></span>
                </a>
                <a
                    <?php echo $bbai_bulk_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?>
                    class="<?php echo esc_attr($bbai_bulk_classes); ?>"
                    data-bbai-quick-action="bulk-optimize"
                >
                    <span class="bbai-dashboard-quick-actions__button-label" data-bbai-quick-action-label><?php echo esc_html($bbai_bulk_label); ?></span>
                    <span class="bbai-dashboard-quick-actions__button-helper" data-bbai-quick-action-helper><?php echo esc_html($bbai_bulk_helper); ?></span>
                </a>
            </div>
        </div>
        <div class="bbai-dashboard-feedback" data-bbai-dashboard-feedback hidden aria-live="polite" role="status"></div>
    </section>
<?php else : ?>
    <section class="bbai-quick-actions bbai-section" aria-label="<?php esc_attr_e( 'Quick Actions', 'beepbeep-ai-alt-text-generator' ); ?>">
        <?php if ( ! empty( $bbai_quick_actions_stats_slot ) ) : ?>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe: pre-escaped in library-tab.php
            echo $bbai_quick_actions_stats_slot;
            ?>
        <?php endif; ?>
        <div class="bbai-quick-actions__grid bbai-quick-actions-grid">
            <button type="button"
                class="bbai-quick-action-card bbai-quick-action-btn <?php echo $bbai_generate_locked ? 'bbai-quick-action-card--locked bbai-quick-action-btn--locked' : ($bbai_generate_disabled ? 'bbai-quick-action-btn--disabled' : ''); ?>"
                <?php if ($bbai_generate_locked) : ?>
                    <?php echo $bbai_build_locked_attrs('generate_missing', 'library-quick-actions-generate'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?>
                    title="<?php echo esc_attr($bbai_locked_cta_description); ?>"
                <?php elseif ($bbai_missing > 0) : ?>
                    data-action="generate-missing"
                <?php else : ?>
                    disabled
                    aria-disabled="true"
                    title="<?php esc_attr_e('All images already have alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                <?php endif; ?>
            >
                <div class="bbai-quick-action-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 2v4M10 14v4M2 10h4M14 10h4M4.93 4.93l2.83 2.83M12.24 12.24l2.83 2.83M4.93 15.07l2.83-2.83M12.24 7.76l2.83-2.83"/>
                    </svg>
                </div>
                <div class="bbai-quick-action-content">
                    <h3 class="bbai-quick-action-title"><?php echo esc_html($bbai_generate_locked ? $bbai_locked_cta_label : bbai_copy_cta_generate_missing_images()); ?></h3>
                    <p class="bbai-quick-action-description"><?php echo esc_html($bbai_generate_locked ? $bbai_locked_cta_description : __('Scan your media library and generate ALT text automatically.', 'beepbeep-ai-alt-text-generator')); ?></p>
                </div>
            </button>

            <a href="<?php echo esc_url($bbai_library_missing_url); ?>" class="bbai-quick-action-card bbai-quick-action-btn bbai-quick-action-btn--link">
                <div class="bbai-quick-action-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="10" cy="10" r="7"/>
                        <path d="M10 6v4l3 2"/>
                    </svg>
                </div>
                <div class="bbai-quick-action-content">
                    <h3 class="bbai-quick-action-title"><?php echo esc_html(bbai_copy_cta_open_alt_library()); ?></h3>
                    <p class="bbai-quick-action-description"><?php esc_html_e('Find images missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </a>

            <a href="<?php echo esc_url($bbai_library_needs_review_url); ?>" class="bbai-quick-action-card bbai-quick-action-btn bbai-quick-action-btn--link" data-bbai-navigation="review-results">
                <div class="bbai-quick-action-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                    </svg>
                </div>
                <div class="bbai-quick-action-content">
                    <h3 class="bbai-quick-action-title"><?php echo esc_html(bbai_copy_cta_review_optimized_images()); ?></h3>
                    <p class="bbai-quick-action-description"><?php esc_html_e('See and improve AI-generated alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </a>
        </div>
    </section>
<?php endif; ?>
