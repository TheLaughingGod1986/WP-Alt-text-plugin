<?php
/**
 * Dashboard value reinforcement section.
 *
 * Expected parent scope:
 * - $bbai_show_before_after
 * - $bbai_state_total_images
 * - $bbai_state_optimized_count
 * - $bbai_state_weak_count
 * - $bbai_state_missing_count
 * - $bbai_has_scan_results
 * - $bbai_has_scan_history
 * - $bbai_last_scan_timestamp
 * - $bbai_format_last_scan
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_show_before_after   = ! empty( $bbai_show_before_after );
$bbai_total_images        = max( 0, (int) ( $bbai_state_total_images ?? 0 ) );
$bbai_optimized_count     = max( 0, (int) ( $bbai_state_optimized_count ?? 0 ) );
$bbai_review_count        = max( 0, (int) ( $bbai_state_weak_count ?? 0 ) );
$bbai_missing_count       = max( 0, (int) ( $bbai_state_missing_count ?? 0 ) );
$bbai_last_scan_timestamp = max( 0, (int) ( $bbai_last_scan_timestamp ?? 0 ) );
$bbai_has_scan_data       = ! empty( $bbai_has_scan_results )
    || ! empty( $bbai_has_scan_history )
    || $bbai_last_scan_timestamp > 0
    || $bbai_total_images > 0
    || $bbai_review_count > 0
    || $bbai_missing_count > 0
    || $bbai_optimized_count > 0;
$bbai_progress_message    = __( 'Scan your library to see what needs attention', 'beepbeep-ai-alt-text-generator' );

if ( $bbai_review_count > 0 ) {
    $bbai_progress_message = __( "You're almost done — review remaining images", 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_missing_count > 0 ) {
    $bbai_progress_message = __( 'Fix remaining images to improve your SEO', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_has_scan_data ) {
    $bbai_progress_message = __( 'Your images are fully optimized 🎉', 'beepbeep-ai-alt-text-generator' );
}

$bbai_last_scan_line = '';
if ( isset( $bbai_format_last_scan ) && is_callable( $bbai_format_last_scan ) ) {
    $bbai_last_scan_line = (string) call_user_func( $bbai_format_last_scan, $bbai_last_scan_timestamp );
}

$bbai_optimized_line = sprintf(
    /* translators: %s: number of optimized images. */
    _n( '%s image optimized', '%s images optimized', $bbai_optimized_count, 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_optimized_count )
);
$bbai_show_activity = $bbai_has_scan_data || '' !== $bbai_last_scan_line;
?>
<section
    class="bbai-before-after-showcase"
    data-bbai-dashboard-proof-section
    data-bbai-dashboard-proof-card
    aria-labelledby="bbai-before-after-heading"
    <?php echo $bbai_show_before_after ? '' : 'hidden'; ?>
>
    <div class="bbai-before-after-showcase__header">
        <h2 id="bbai-before-after-heading" class="bbai-before-after-showcase__title"><?php esc_html_e( 'See the difference', 'beepbeep-ai-alt-text-generator' ); ?></h2>
    </div>

    <div class="bbai-before-after-showcase__grid">
        <article class="bbai-before-after-card bbai-before-after-card--before">
            <span class="bbai-before-after-card__label"><?php esc_html_e( 'Before', 'beepbeep-ai-alt-text-generator' ); ?></span>
            <p class="bbai-before-after-card__headline"><?php esc_html_e( 'No ALT text — invisible to search engines and screen readers', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </article>

        <article class="bbai-before-after-card bbai-before-after-card--after">
            <span class="bbai-before-after-card__label"><?php esc_html_e( 'After', 'beepbeep-ai-alt-text-generator' ); ?></span>
            <p class="bbai-before-after-card__text bbai-before-after-card__text--sample"><?php esc_html_e( 'Golden retriever running through green field under blue sky', 'beepbeep-ai-alt-text-generator' ); ?></p>
            <p class="bbai-before-after-card__supporting"><?php esc_html_e( 'Readable • descriptive • SEO-ready', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </article>
    </div>

    <div class="bbai-before-after-showcase__benefits" role="list" aria-label="<?php esc_attr_e( 'Benefits', 'beepbeep-ai-alt-text-generator' ); ?>">
        <span class="bbai-before-after-showcase__benefit" role="listitem">
            <span class="bbai-before-after-showcase__benefit-icon" aria-hidden="true">✓</span>
            <span><?php esc_html_e( 'Saves hours of manual work', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </span>
        <span class="bbai-before-after-showcase__benefit" role="listitem">
            <span class="bbai-before-after-showcase__benefit-icon" aria-hidden="true">✓</span>
            <span><?php esc_html_e( 'Improves accessibility', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </span>
        <span class="bbai-before-after-showcase__benefit" role="listitem">
            <span class="bbai-before-after-showcase__benefit-icon" aria-hidden="true">✓</span>
            <span><?php esc_html_e( 'Boosts image SEO', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </span>
    </div>

    <p class="bbai-before-after-showcase__progress" data-bbai-dashboard-proof-message><?php echo esc_html( $bbai_progress_message ); ?></p>

    <div class="bbai-before-after-showcase__activity" data-bbai-dashboard-proof-activity <?php echo $bbai_show_activity ? '' : 'hidden'; ?>>
        <span class="bbai-before-after-showcase__activity-item" data-bbai-dashboard-proof-last-scan <?php echo '' !== $bbai_last_scan_line ? '' : 'hidden'; ?>>
            <?php echo esc_html( $bbai_last_scan_line ); ?>
        </span>
        <span class="bbai-before-after-showcase__activity-item" data-bbai-dashboard-proof-optimized><?php echo esc_html( $bbai_optimized_line ); ?></span>
    </div>
</section>
