<?php
/**
 * Dashboard hero banner - achievement summary + primary action area.
 * Shown when user has credits remaining (not at limit).
 *
 * Expects:
 * - $bbai_usage_stats (array)
 * - $bbai_state (array, optional) - credits_used, missing_alts, etc.
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! isset( $bbai_usage_stats ) || ! is_array( $bbai_usage_stats ) ) {
    return;
}

/* Use normalized credits from dashboard-body when available (same source as status card) */
$bbai_banner_used = isset( $bbai_credits_used ) ? max( 0, (int) $bbai_credits_used ) : max( 0, (int) ( $bbai_usage_stats['used'] ?? 0 ) );
$bbai_banner_limit = isset( $bbai_credits_total ) ? max( 1, (int) $bbai_credits_total ) : max( 1, (int) ( $bbai_usage_stats['limit'] ?? 50 ) );
$bbai_banner_progress_pct = min( 100, max( 0, ( $bbai_banner_used / max( 1, $bbai_banner_limit ) ) * 100 ) );
$bbai_library_url = admin_url( 'admin.php?page=bbai-library' );

$bbai_days_reset = isset( $bbai_usage_stats['days_until_reset'] ) && is_numeric( $bbai_usage_stats['days_until_reset'] )
    ? max( 0, (int) $bbai_usage_stats['days_until_reset'] )
    : 30;
$bbai_reset_line = sprintf(
    /* translators: %s: number of days until reset */
    _n( 'Your credits reset in %s day', 'Your credits reset in %s days', $bbai_days_reset, 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_days_reset )
);

$bbai_state = $bbai_state ?? [];
$bbai_banner_missing = isset( $bbai_state['missing_alts'] ) ? max( 0, (int) $bbai_state['missing_alts'] ) : 0;
$bbai_banner_needs_review = isset( $bbai_state['needs_review_count'] ) ? max( 0, (int) $bbai_state['needs_review_count'] ) : 0;
$bbai_show_upgrade_primary = $bbai_banner_used > 0;
$bbai_banner_is_fully_optimized = 0 === $bbai_banner_missing && 0 === $bbai_banner_needs_review;
?>
<section
    class="bbai-dashboard-hero bbai-dashboard-section"
    data-bbai-shared-banner="1"
    data-bbai-banner-used="<?php echo esc_attr( $bbai_banner_used ); ?>"
    data-bbai-banner-limit="<?php echo esc_attr( $bbai_banner_limit ); ?>"
    aria-label="<?php esc_attr_e( 'Dashboard summary', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <div class="bbai-dashboard-hero-inner">
        <div class="bbai-dashboard-hero-copy">
            <?php if ( $bbai_banner_used > 0 ) : ?>
            <h2 class="bbai-dashboard-hero__headline">
                <?php
                printf(
                    /* translators: %s: number of images optimized this month */
                    esc_html__( '%s images optimized this month', 'beepbeep-ai-alt-text-generator' ),
                    '<span class="bbai-number-animate bbai-banner-usage-used">' . esc_html( number_format_i18n( $bbai_banner_used ) ) . '</span>'
                );
                ?> &#x1f389;
            </h2>
            <p class="bbai-dashboard-hero__subtext">
                <?php
                if ( $bbai_banner_is_fully_optimized ) {
                    esc_html_e( 'Your site now has full ALT coverage.', 'beepbeep-ai-alt-text-generator' );
                } elseif ( 0 === $bbai_banner_missing ) {
                    printf(
                        /* translators: %s: count of weak ALT descriptions */
                        esc_html__( 'Your site now has full ALT coverage. %s descriptions could still be improved.', 'beepbeep-ai-alt-text-generator' ),
                        esc_html( number_format_i18n( $bbai_banner_needs_review ) )
                    );
                } else {
                    printf(
                        /* translators: %s: count of images missing ALT text */
                        esc_html__( 'Your site is making strong progress. %s images still need ALT text.', 'beepbeep-ai-alt-text-generator' ),
                        esc_html( number_format_i18n( $bbai_banner_missing ) )
                    );
                }
                ?>
            </p>
            <?php else : ?>
            <h2 class="bbai-dashboard-hero__headline"><?php esc_html_e( 'Your media library is ready for optimization', 'beepbeep-ai-alt-text-generator' ); ?></h2>
            <p class="bbai-dashboard-hero__subtext"><?php esc_html_e( 'Scan your images to generate AI ALT text automatically and improve accessibility and SEO.', 'beepbeep-ai-alt-text-generator' ); ?></p>
            <?php endif; ?>
            <p class="bbai-dashboard-hero__usage" data-bbai-banner-usage-line>
                <?php
                printf(
                    /* translators: 1: used count, 2: limit count, 3: reset line */
                    esc_html__( '%1$s / %2$s AI generations used • %3$s', 'beepbeep-ai-alt-text-generator' ),
                    '<span class="bbai-number-animate bbai-banner-usage-used">' . esc_html( number_format_i18n( $bbai_banner_used ) ) . '</span>',
                    '<span class="bbai-number-animate bbai-banner-usage-limit">' . esc_html( number_format_i18n( $bbai_banner_limit ) ) . '</span>',
                    esc_html( $bbai_reset_line )
                );
                ?>
            </p>
        </div>
        <div class="bbai-dashboard-hero-actions">
            <?php if ( $bbai_show_upgrade_primary ) : ?>
            <button type="button" class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-btn-primary" data-action="show-upgrade-modal">
                <?php esc_html_e( 'Upgrade to Growth', 'beepbeep-ai-alt-text-generator' ); ?>
            </button>
            <?php else : ?>
            <button type="button" class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-btn-primary" data-bbai-action="scan-opportunity">
                <?php esc_html_e( 'Scan Media Library', 'beepbeep-ai-alt-text-generator' ); ?>
            </button>
            <?php endif; ?>
            <a href="<?php echo esc_url( $bbai_library_url ); ?>" class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--secondary">
                <?php esc_html_e( 'Review in library', 'beepbeep-ai-alt-text-generator' ); ?>
            </a>
        </div>
        <div class="bbai-dashboard-hero-progress" style="--progress-percent: <?php echo esc_attr( $bbai_banner_progress_pct ); ?>%;">
            <div class="bbai-dashboard-hero-progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr( round( $bbai_banner_progress_pct ) ); ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="bbai-dashboard-hero-progress-fill" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr( round( $bbai_banner_progress_pct ) ); ?>"></span>
            </div>
        </div>
    </div>
</section>
