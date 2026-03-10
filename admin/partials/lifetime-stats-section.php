<?php
/**
 * Lifetime Optimisation stats - reinforces value already delivered.
 *
 * Expects: $bbai_lifetime_stats (array) with keys:
 *   - total_generated (int)
 *   - images_improved (int)
 *   - hours_saved (float)
 *   - seo_improvements (int, optional)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $bbai_lifetime_stats ) || ! is_array( $bbai_lifetime_stats ) ) {
    return;
}

$total = max( 0, (int) ( $bbai_lifetime_stats['total_generated'] ?? 0 ) );
$improved = max( 0, (int) ( $bbai_lifetime_stats['images_improved'] ?? $total ) );
$hours = max( 0, (float) ( $bbai_lifetime_stats['hours_saved'] ?? 0 ) );
$seo = max( 0, (int) ( $bbai_lifetime_stats['seo_improvements'] ?? $improved ) );

/* Don't show section if no activity yet */
if ( $total <= 0 && $improved <= 0 ) {
    return;
}
?>
<section class="bbai-lifetime-stats bbai-section bbai-lifetime-value" aria-label="<?php esc_attr_e( 'Lifetime value', 'beepbeep-ai-alt-text-generator' ); ?>">
    <div class="bbai-lifetime-stats__grid">
        <div class="bbai-lifetime-stat bbai-lifetime-stat--primary">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $total ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Lifetime alt text generated', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $improved ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Images improved for accessibility', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $hours, 1 ) ); ?></span> <span><?php esc_html_e( 'hours', 'beepbeep-ai-alt-text-generator' ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Estimated time saved', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <?php if ( $seo > 0 ) : ?>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $seo ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'SEO improvements detected', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <?php endif; ?>
    </div>
</section>
