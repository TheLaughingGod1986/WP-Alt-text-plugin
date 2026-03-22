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

    $bbai_generate_label = __('Scan Media Library', 'beepbeep-ai-alt-text-generator');
    $bbai_generate_helper = __('Find images missing ALT text before generating descriptions.', 'beepbeep-ai-alt-text-generator');
    $bbai_generate_attrs = 'href="#" data-bbai-action="scan-opportunity"';
    $bbai_generate_is_complete = $bbai_has_scan_results && $bbai_missing <= 0;
    if ($bbai_has_scan_results && $bbai_missing > 0 && $bbai_limit_reached) {
        $bbai_generate_label = __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator');
        $bbai_generate_helper = __('Buy additional credits', 'beepbeep-ai-alt-text-generator');
        $bbai_generate_attrs = 'href="#" data-action="show-upgrade-modal"';
    } elseif ($bbai_has_scan_results && $bbai_missing > 0) {
        $bbai_generate_label = sprintf(
            _n('Generate ALT for %s image', 'Generate ALT for %s images', $bbai_missing, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_missing)
        );
        $bbai_generate_helper = $bbai_format_credit_helper($bbai_missing, $bbai_remaining_credits > 0 ? $bbai_remaining_credits : $bbai_missing);
        $bbai_generate_attrs = 'href="#" data-action="generate-missing" data-bbai-action="generate_missing"';
    } elseif ($bbai_generate_is_complete) {
        $bbai_generate_label = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
        $bbai_generate_helper = __('No images are currently missing ALT text.', 'beepbeep-ai-alt-text-generator');
        $bbai_generate_attrs = 'href="' . esc_url($bbai_library_url) . '" aria-label="' . esc_attr__('Generate Missing ALT complete', 'beepbeep-ai-alt-text-generator') . '"';
    }

    $bbai_review_label = __('Improve ALT descriptions', 'beepbeep-ai-alt-text-generator');
    $bbai_review_helper = __('Scan first to find weaker ALT descriptions that need review.', 'beepbeep-ai-alt-text-generator');
    $bbai_review_attrs = 'href="#" aria-disabled="true"';
    $bbai_review_disabled = !$bbai_has_scan_results;
    if ($bbai_has_scan_results && $bbai_weak > 0 && !$bbai_has_available_credits) {
        $bbai_review_label = __('Upgrade to continue generating ALT text', 'beepbeep-ai-alt-text-generator');
        $bbai_review_helper = __('Buy additional credits', 'beepbeep-ai-alt-text-generator');
        $bbai_review_attrs = 'href="#" data-action="show-upgrade-modal"';
        $bbai_review_disabled = false;
    } elseif ($bbai_has_scan_results && $bbai_weak > 0) {
        $bbai_review_label = sprintf(
            _n('Improve %s ALT description', 'Improve %s ALT descriptions', $bbai_weak, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_weak)
        );
        $bbai_review_helper = $bbai_format_credit_helper($bbai_weak, $bbai_remaining_credits > 0 ? $bbai_remaining_credits : $bbai_weak);
        $bbai_review_attrs = 'href="#" data-action="regenerate-all" data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"';
        $bbai_review_disabled = false;
    } elseif ($bbai_has_scan_results) {
        $bbai_review_label = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
        $bbai_review_helper = __('No weak ALT descriptions are waiting for review.', 'beepbeep-ai-alt-text-generator');
        $bbai_review_attrs = 'href="' . esc_url($bbai_library_url) . '"';
        $bbai_review_disabled = false;
    }

    $bbai_bulk_label = __('Automatically optimize every new image you upload', 'beepbeep-ai-alt-text-generator');
    $bbai_bulk_helper = __('Never worry about missing ALT text again.', 'beepbeep-ai-alt-text-generator');
    $bbai_bulk_attrs = 'href="#" data-action="show-upgrade-modal"';
    if ($bbai_is_premium_plan) {
        if ($bbai_missing > 0) {
            $bbai_bulk_helper = __('Bulk generation is available on your plan.', 'beepbeep-ai-alt-text-generator');
            $bbai_bulk_attrs = 'href="#" data-action="generate-missing" data-bbai-action="generate_missing"';
        } elseif ($bbai_weak > 0) {
            $bbai_bulk_helper = __('Bulk improvements are available on your plan.', 'beepbeep-ai-alt-text-generator');
            $bbai_bulk_attrs = 'href="#" data-action="regenerate-all" data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"';
        } else {
            $bbai_bulk_label = __('Open ALT Library', 'beepbeep-ai-alt-text-generator');
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
                    data-action="show-upgrade-modal"
                    data-bbai-lock-control="1"
                    title="<?php esc_attr_e('Upgrade to process the rest automatically', 'beepbeep-ai-alt-text-generator'); ?>"
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
                    <h3 class="bbai-quick-action-title"><?php esc_html_e('Generate Missing ALT Text', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-quick-action-description"><?php esc_html_e('Scan your media library and generate ALT text automatically.', 'beepbeep-ai-alt-text-generator'); ?></p>
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
                    <h3 class="bbai-quick-action-title"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-quick-action-description"><?php esc_html_e('Find images missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </a>

            <a href="<?php echo esc_url($bbai_library_optimized_url); ?>" class="bbai-quick-action-card bbai-quick-action-btn bbai-quick-action-btn--link">
                <div class="bbai-quick-action-icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                    </svg>
                </div>
                <div class="bbai-quick-action-content">
                    <h3 class="bbai-quick-action-title"><?php esc_html_e('Review Optimized Images', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-quick-action-description"><?php esc_html_e('See and improve AI-generated alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
            </a>
        </div>
    </section>
<?php endif; ?>
