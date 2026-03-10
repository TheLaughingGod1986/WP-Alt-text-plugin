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

// Derive from $bbai_stats or $bbai_state depending on context
$bbai_missing = isset($bbai_stats['missing']) ? max(0, (int) $bbai_stats['missing']) : (isset($bbai_missing_count) ? max(0, (int) $bbai_missing_count) : 0);
$bbai_total = isset($bbai_stats['total']) ? max(0, (int) $bbai_stats['total']) : (isset($bbai_total_images) ? max(0, (int) $bbai_total_images) : 0);
$bbai_with_alt = isset($bbai_stats['with_alt']) ? max(0, (int) $bbai_stats['with_alt']) : (isset($bbai_with_alt_count) ? max(0, (int) $bbai_with_alt_count) : 0);

$bbai_limit_reached = isset($bbai_limit_reached_state) ? $bbai_limit_reached_state : (isset($bbai_state['is_out_of_credits']) ? $bbai_state['is_out_of_credits'] : false);
$bbai_generate_locked = $bbai_limit_reached;
$bbai_generate_disabled = $bbai_missing <= 0 && !$bbai_limit_reached;
?>
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
