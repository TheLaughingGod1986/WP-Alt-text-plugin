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
$bbai_missing_library_url = admin_url( 'admin.php?page=bbai-library&status=missing' );

$bbai_days_reset = isset( $bbai_usage_stats['days_until_reset'] ) && is_numeric( $bbai_usage_stats['days_until_reset'] )
    ? max( 0, (int) $bbai_usage_stats['days_until_reset'] )
    : 30;
$bbai_reset_inline_copy = sprintf(
    /* translators: %s: number of days until reset */
    _n( 'resets in %s day', 'resets in %s days', $bbai_days_reset, 'beepbeep-ai-alt-text-generator' ),
    number_format_i18n( $bbai_days_reset )
);

$bbai_state = $bbai_state ?? [];
$bbai_banner_missing = isset( $bbai_state['missing_alts'] ) ? max( 0, (int) $bbai_state['missing_alts'] ) : 0;
$bbai_banner_needs_review = isset( $bbai_state['needs_review_count'] ) ? max( 0, (int) $bbai_state['needs_review_count'] ) : 0;
$bbai_has_scan_results = ! empty( $bbai_state['has_scan_results'] );
$bbai_is_first_scan_experience = ! $bbai_has_scan_results;
$bbai_hero_headline = '';
$bbai_hero_subtext = '';
$bbai_generator_label = __( 'Generate ALT Text', 'beepbeep-ai-alt-text-generator' );
$bbai_generator_attrs = 'href="#" data-action="show-generate-alt-modal"';
$bbai_action_helper = '';
$bbai_library_button_label = __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' );
$bbai_library_button_attrs = 'href="' . esc_url( $bbai_library_url ) . '"';
$bbai_show_library_button = false;
$bbai_secondary_link_label = '';
$bbai_secondary_link_attrs = 'href="' . esc_url( $bbai_library_url ) . '"';
$bbai_rescan_label = __( 'Rescan library', 'beepbeep-ai-alt-text-generator' );
$bbai_rescan_attrs = 'href="#" data-bbai-action="scan-opportunity"';
$bbai_show_rescan_link = false;

if ( $bbai_is_first_scan_experience ) {
    $bbai_hero_headline = __( 'Welcome to BeepBeep AI', 'beepbeep-ai-alt-text-generator' );
    $bbai_hero_subtext = __( 'Start by scanning your media library to find images missing ALT text.', 'beepbeep-ai-alt-text-generator' );
    $bbai_generator_label = __( 'Scan Library', 'beepbeep-ai-alt-text-generator' );
    $bbai_generator_attrs = 'href="#" data-bbai-action="scan-opportunity"';
} elseif ( $bbai_banner_missing > 0 ) {
    $bbai_hero_headline = sprintf(
        _n( '%s image needs ALT text', '%s images need ALT text', $bbai_banner_missing, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_banner_missing )
    );
    $bbai_hero_subtext = _n(
        'BeepBeep AI can automatically generate ALT text for the missing image in your media library.',
        'BeepBeep AI can automatically generate ALT text for missing images in your media library.',
        $bbai_banner_missing,
        'beepbeep-ai-alt-text-generator'
    );
    $bbai_action_helper = sprintf(
        _n( 'Uses %s credit', 'Uses %s credits', $bbai_banner_missing, 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_banner_missing )
    );
    $bbai_secondary_link_label = __( 'Review manually in ALT Library', 'beepbeep-ai-alt-text-generator' );
    $bbai_secondary_link_attrs = 'href="' . esc_url( $bbai_missing_library_url ) . '"';
    $bbai_show_rescan_link = true;
} elseif ( $bbai_banner_needs_review > 0 ) {
    $bbai_hero_headline = __( 'Some ALT descriptions could be improved.', 'beepbeep-ai-alt-text-generator' );
    $bbai_hero_subtext = sprintf(
        _n(
            '%s image may benefit from improved descriptions.',
            '%s images may benefit from improved descriptions.',
            $bbai_banner_needs_review,
            'beepbeep-ai-alt-text-generator'
        ),
        number_format_i18n( $bbai_banner_needs_review )
    );
    $bbai_generator_label = __( 'Improve ALT Text', 'beepbeep-ai-alt-text-generator' );
    $bbai_secondary_link_label = __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' );
    $bbai_secondary_link_attrs = 'href="' . esc_url( $bbai_library_url ) . '"';
    $bbai_show_rescan_link = true;
} else {
    $bbai_hero_headline = __( 'Your media library looks healthy', 'beepbeep-ai-alt-text-generator' );
    $bbai_hero_subtext = __( 'All images currently have ALT text.', 'beepbeep-ai-alt-text-generator' );
    $bbai_generator_label = __( 'Scan Media Library', 'beepbeep-ai-alt-text-generator' );
    $bbai_generator_attrs = 'href="#" data-bbai-action="scan-opportunity"';
    $bbai_show_library_button = true;
}

$bbai_show_library_link = $bbai_show_library_button;
$bbai_show_secondary_link = '' !== $bbai_secondary_link_label;
$bbai_show_library_separator = $bbai_show_library_link && ( $bbai_show_secondary_link || $bbai_show_rescan_link );
$bbai_show_secondary_separator = $bbai_show_secondary_link && $bbai_show_rescan_link;
?>
<section
    class="bbai-dashboard-hero bbai-dashboard-section"
    data-bbai-dashboard-hero="1"
    data-bbai-shared-banner="1"
    data-bbai-banner-used="<?php echo esc_attr( $bbai_banner_used ); ?>"
    data-bbai-banner-limit="<?php echo esc_attr( $bbai_banner_limit ); ?>"
    data-bbai-banner-library-url="<?php echo esc_url( $bbai_library_url ); ?>"
    data-bbai-banner-missing-count="<?php echo esc_attr( $bbai_banner_missing ); ?>"
    data-bbai-banner-weak-count="<?php echo esc_attr( $bbai_banner_needs_review ); ?>"
    data-bbai-banner-days-left="<?php echo esc_attr( $bbai_days_reset ); ?>"
    aria-label="<?php esc_attr_e( 'Dashboard summary', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <div class="bbai-dashboard-hero-inner">
        <div class="bbai-dashboard-hero-copy">
            <div class="bbai-dashboard-hero__heading-row">
                <div class="bbai-dashboard-hero__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" focusable="false">
                        <path d="M13 2L6 13h4l-1 9 9-12h-5l0-8z" fill="currentColor"></path>
                    </svg>
                </div>
                <div class="bbai-dashboard-hero__heading-copy">
                    <h2 class="bbai-dashboard-hero__headline" data-bbai-hero-headline><?php echo wp_kses_post( $bbai_hero_headline ); ?></h2>
                </div>
            </div>
            <p class="bbai-dashboard-hero__subtext" data-bbai-hero-subtext><?php echo esc_html( $bbai_hero_subtext ); ?></p>
            <div class="bbai-dashboard-hero__usage-row">
                <div class="bbai-dashboard-hero__usage-block">
                    <p class="bbai-dashboard-hero__usage" data-bbai-banner-usage-line data-bbai-reset-copy="<?php echo esc_attr( $bbai_reset_inline_copy ); ?>">
                        <span class="bbai-dashboard-hero__usage-primary">
                            <?php
                            printf(
                                /* translators: 1: used count, 2: limit count */
                                esc_html__( '%1$s / %2$s AI generations used this month', 'beepbeep-ai-alt-text-generator' ),
                                '<span class="bbai-number-animate bbai-banner-usage-used">' . esc_html( number_format_i18n( $bbai_banner_used ) ) . '</span>',
                                '<span class="bbai-number-animate bbai-banner-usage-limit">' . esc_html( number_format_i18n( $bbai_banner_limit ) ) . '</span>'
                            );
                            ?>
                        </span>
                        <span class="bbai-dashboard-hero__usage-progress-copy" data-bbai-banner-progress-copy hidden><?php echo esc_html( $bbai_reset_inline_copy ); ?></span>
                    </p>
                    <div class="bbai-dashboard-hero-progress">
                        <div class="bbai-dashboard-hero-progress-track" role="progressbar" aria-valuenow="<?php echo esc_attr( round( $bbai_banner_progress_pct ) ); ?>" aria-valuemin="0" aria-valuemax="100">
                            <span class="bbai-dashboard-hero-progress-fill" data-bbai-banner-progress data-bbai-banner-progress-target="<?php echo esc_attr( round( $bbai_banner_progress_pct ) ); ?>"></span>
                        </div>
                    </div>
                </div>
                <div class="bbai-dashboard-hero-actions">
                    <div class="bbai-dashboard-hero-actions__primary-row">
                        <a <?php echo $bbai_generator_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-btn-primary" data-bbai-hero-generator-cta><?php echo esc_html( $bbai_generator_label ); ?></a>
                    </div>
                    <p class="bbai-dashboard-hero__action-helper" data-bbai-hero-action-helper <?php echo '' !== $bbai_action_helper ? '' : 'hidden'; ?>><?php echo esc_html( $bbai_action_helper ); ?></p>
                </div>
            </div>
            <div class="bbai-dashboard-hero-actions__links" <?php echo ( $bbai_show_library_link || $bbai_show_secondary_link || $bbai_show_rescan_link ) ? '' : 'hidden'; ?>>
                <a <?php echo $bbai_library_button_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--secondary" data-bbai-hero-library-cta <?php echo $bbai_show_library_link ? '' : 'hidden'; ?>><?php echo esc_html( $bbai_library_button_label ); ?></a>
                <span class="bbai-dashboard-hero__link-separator" data-bbai-hero-library-separator <?php echo $bbai_show_library_separator ? '' : 'hidden'; ?>>&middot;</span>
                <a <?php echo $bbai_secondary_link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--secondary" data-bbai-hero-secondary-link <?php echo $bbai_show_secondary_link ? '' : 'hidden'; ?>><?php echo esc_html( $bbai_secondary_link_label ); ?></a>
                <span class="bbai-dashboard-hero__link-separator" data-bbai-hero-secondary-separator <?php echo $bbai_show_secondary_separator ? '' : 'hidden'; ?>>&middot;</span>
                <a <?php echo $bbai_rescan_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values above. ?> class="bbai-dashboard-hero__cta bbai-dashboard-hero__cta--secondary bbai-dashboard-hero__cta--secondary-muted" data-bbai-hero-rescan-cta <?php echo $bbai_show_rescan_link ? '' : 'hidden'; ?>><?php echo esc_html( $bbai_rescan_label ); ?></a>
            </div>
        </div>
    </div>
</section>
