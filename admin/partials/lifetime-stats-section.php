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

$bbai_total_generated = max( 0, (int) ( $bbai_lifetime_stats['total_generated'] ?? 0 ) );
$bbai_improved_images = max( 0, (int) ( $bbai_lifetime_stats['images_improved'] ?? $bbai_total_generated ) );
$bbai_hours_saved = max( 0, (float) ( $bbai_lifetime_stats['hours_saved'] ?? 0 ) );
$bbai_seo_improvements = max( 0, (int) ( $bbai_lifetime_stats['seo_improvements'] ?? $bbai_improved_images ) );

/* Don't show section if no activity yet */
if ( $bbai_total_generated <= 0 && $bbai_improved_images <= 0 ) {
    return;
}
?>
<section class="bbai-lifetime-stats bbai-section bbai-lifetime-value" aria-label="<?php esc_attr_e( 'Lifetime value', 'beepbeep-ai-alt-text-generator' ); ?>">
    <div class="bbai-lifetime-stats__grid">
        <div class="bbai-lifetime-stat bbai-lifetime-stat--primary">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $bbai_total_generated ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Lifetime alt text generated', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $bbai_improved_images ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Images improved for accessibility', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $bbai_hours_saved, 1 ) ); ?></span> <span><?php esc_html_e( 'hours', 'beepbeep-ai-alt-text-generator' ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'Estimated time saved', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <?php if ( $bbai_seo_improvements > 0 ) : ?>
        <div class="bbai-lifetime-stat">
            <p class="bbai-lifetime-stat__value"><span class="bbai-number-animate"><?php echo esc_html( number_format_i18n( $bbai_seo_improvements ) ); ?></span></p>
            <p class="bbai-lifetime-stat__label"><?php esc_html_e( 'SEO improvements detected', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </div>
        <?php endif; ?>
    </div>
</section>
