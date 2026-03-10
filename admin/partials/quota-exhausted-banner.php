<?php
/**
 * Usage limit banner for dashboard - shown when monthly limit reached.
 * Soft blue background, calm SaaS style, explains why actions are locked.
 *
 * Expects:
 * - $bbai_usage_stats (array)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $bbai_usage_stats ) || ! is_array( $bbai_usage_stats ) ) {
    return;
}

$bbai_banner_used = max( 0, (int) ( $bbai_usage_stats['used'] ?? 0 ) );
$bbai_banner_limit = max( 1, (int) ( $bbai_usage_stats['limit'] ?? 50 ) );

$bbai_banner_progress_pct = min( 100, max( 0, ( $bbai_banner_used / max( 1, $bbai_banner_limit ) ) * 100 ) );

$bbai_banner_headline = __( 'Monthly limit reached', 'beepbeep-ai-alt-text-generator' );
$bbai_banner_supporting = sprintf(
    /* translators: %s: number of images optimized */
    __( 'You optimized %s images this month.', 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_banner_used )
);
$bbai_banner_progress_label = sprintf(
    /* translators: 1: used count, 2: limit count */
    __( '%1$s / %2$s images used', 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_banner_used ),
    number_format_i18n( $bbai_banner_limit )
);
?>
<section
    class="bbai-usage-banner bbai-saas-banner bbai-quota-exhausted-banner bbai-banner--limit-reached bbai-usage-limit-banner"
    data-bbai-shared-banner="1"
    data-bbai-banner-used="<?php echo esc_attr( $bbai_banner_used ); ?>"
    data-bbai-banner-limit="<?php echo esc_attr( $bbai_banner_limit ); ?>"
    aria-label="<?php esc_attr_e( 'Monthly limit reached', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <div class="bbai-usage-banner__inner">
        <div class="bbai-usage-banner__top">
            <div class="bbai-usage-banner__content">
                <h2 class="bbai-usage-banner__headline"><?php echo esc_html( $bbai_banner_headline ); ?></h2>
            </div>
            <div class="bbai-usage-banner__actions">
                <button
                    type="button"
                    class="bbai-usage-banner__cta bbai-btn-primary"
                    data-action="show-upgrade-modal"
                ><?php esc_html_e( 'Upgrade Plan', 'beepbeep-ai-alt-text-generator' ); ?></button>
            </div>
        </div>
        <p class="bbai-usage-banner__credits"><?php echo esc_html( $bbai_banner_supporting ); ?></p>
        <div class="bbai-usage-banner__progress-wrap bbai-usage-banner__progress-wrap--full">
            <p class="bbai-usage-banner__progress-label"><?php echo esc_html( $bbai_banner_progress_label ); ?></p>
            <div class="bbai-usage-banner__progress" role="progressbar" aria-valuenow="<?php echo esc_attr( round( $bbai_banner_progress_pct ) ); ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="bbai-usage-banner__progress-fill" style="width: <?php echo esc_attr( $bbai_banner_progress_pct ); ?>%;"></span>
            </div>
        </div>
    </div>
</section>
