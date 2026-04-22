<?php
/**
 * Dashboard mini ALT Library preview for anonymous trial users.
 *
 * Expected parent scope:
 * - $bbai_is_anonymous_trial
 * - $bbai_has_connected_account
 * - $bbai_state_total_images
 * - $bbai_state_credits_remaining
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $bbai_is_anonymous_trial ) || ! empty( $bbai_has_connected_account ) || ! isset( $this ) ) {
    return;
}

$bbai_trial_preview_rows = method_exists( $this, 'get_trial_dashboard_preview_rows' )
    ? $this->get_trial_dashboard_preview_rows( 3 )
    : [];
$bbai_trial_preview_rows = is_array( $bbai_trial_preview_rows ) ? $bbai_trial_preview_rows : [];
$bbai_is_exhausted_trial_checkpoint = max( 0, (int) ( $bbai_state_credits_remaining ?? 0 ) ) <= 0;
$bbai_trial_preview_total = max( 0, (int) ( $bbai_state_total_images ?? count( $bbai_trial_preview_rows ) ) );
$bbai_trial_preview_displayed = count( $bbai_trial_preview_rows );
$bbai_trial_preview_extra = max( 0, $bbai_trial_preview_total - $bbai_trial_preview_displayed );
$bbai_trial_preview_generation_locked = max( 0, (int) ( $bbai_state_credits_remaining ?? 0 ) ) <= 0;
?>
<section
    class="bbai-dashboard-trial-preview"
    data-bbai-trial-preview="1"
    data-bbai-trial-preview-limit="3"
    aria-labelledby="bbai-dashboard-trial-preview-heading"
    <?php echo $bbai_is_exhausted_trial_checkpoint ? 'hidden' : ''; ?>
>
    <div class="bbai-dashboard-trial-preview__header">
        <div class="bbai-dashboard-trial-preview__copy">
            <h2 id="bbai-dashboard-trial-preview-heading" class="bbai-dashboard-trial-preview__title">
                <?php esc_html_e( 'Review your first results', 'beepbeep-ai-alt-text-generator' ); ?>
            </h2>
            <p class="bbai-dashboard-trial-preview__description">
                <?php esc_html_e( 'See the first images you fixed and review them before you unlock the full library.', 'beepbeep-ai-alt-text-generator' ); ?>
            </p>
        </div>
        <span
            class="bbai-dashboard-trial-preview__more"
            data-bbai-trial-preview-more
            <?php echo $bbai_trial_preview_extra > 0 ? '' : 'hidden'; ?>
        >
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: remaining image count. */
                    __( '+%s more images', 'beepbeep-ai-alt-text-generator' ),
                    number_format_i18n( $bbai_trial_preview_extra )
                )
            );
            ?>
        </span>
    </div>

    <?php
    $bbai_lib_card_row_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/library-dashboard-card-row.php';
    ?>
    <?php if ( ! empty( $bbai_trial_preview_rows ) && is_readable( $bbai_lib_card_row_path ) ) : ?>
        <div class="bbai-dashboard-trial-preview__list" role="list" data-bbai-trial-preview-list>
            <?php foreach ( $bbai_trial_preview_rows as $bbai_preview_row ) : ?>
                <?php
                if ( ! is_array( $bbai_preview_row ) ) {
                    continue;
                }
                $bbai_dash_card_mode = 'trial';
                require $bbai_lib_card_row_path;
                ?>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="bbai-dashboard-trial-preview__empty" data-bbai-trial-preview-empty-state>
            <p class="bbai-dashboard-trial-preview__empty-title"><?php esc_html_e( 'Your first ALT results will appear here', 'beepbeep-ai-alt-text-generator' ); ?></p>
            <p class="bbai-dashboard-trial-preview__empty-copy"><?php esc_html_e( 'Run a scan or fix images from the hero to preview your next results without leaving the dashboard.', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
    <?php endif; ?>

    <p class="bbai-dashboard-trial-preview__empty" data-bbai-trial-preview-empty hidden></p>
</section>
