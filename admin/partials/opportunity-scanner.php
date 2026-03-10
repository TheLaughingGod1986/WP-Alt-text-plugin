<?php
/**
 * Opportunity Scanner - Image SEO opportunities card.
 * Analyzes media library and surfaces optimization opportunities.
 *
 * Expects: $bbai_coverage (array from get_alt_text_coverage_scan)
 *          $bbai_is_free (bool)
 *          $bbai_credits_total (int)
 *          $bbai_credits_remaining (int)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_coverage = isset( $bbai_coverage ) && is_array( $bbai_coverage ) ? $bbai_coverage : [];
$bbai_is_free = ! empty( $bbai_is_free );
$bbai_credits_total = max( 1, (int) ( $bbai_credits_total ?? 50 ) );
$bbai_credits_remaining = max( 0, (int) ( $bbai_credits_remaining ?? 0 ) );

$total_images = max( 0, (int) ( $bbai_coverage['total_images'] ?? 0 ) );
$missing = max( 0, (int) ( $bbai_coverage['images_missing_alt'] ?? 0 ) );
$weak = max( 0, (int) ( $bbai_coverage['needs_review_count'] ?? 0 ) );
$filename_only = max( 0, (int) ( $bbai_coverage['filename_only_count'] ?? 0 ) );
$optimized = max( 0, (int) ( $bbai_coverage['optimized_count'] ?? $bbai_coverage['images_with_alt'] ?? 0 ) );
$duplicate = max( 0, (int) ( $bbai_coverage['duplicate_alt_count'] ?? 0 ) );

$bbai_has_scanned = $total_images > 0;
$bbai_opportunity_total = $missing + $weak + $filename_only + $duplicate;
$bbai_scan_library_url = add_query_arg( [ 'page' => 'bbai-library', 'status' => 'missing' ], admin_url( 'admin.php' ) );
?>
<section class="bbai-dashboard-card bbai-opportunity-scanner" aria-labelledby="bbai-opportunity-title">
    <article class="bbai-opportunity-scanner__card">
        <header class="bbai-card__header">
            <h3 id="bbai-opportunity-title" class="bbai-opportunity-scanner__title"><?php esc_html_e( 'SEO Opportunities', 'beepbeep-ai-alt-text-generator' ); ?></h3>
            <p class="bbai-card__copy bbai-opportunity-scanner__desc"><?php esc_html_e( 'BeepBeep AI analyzed your media library and found opportunities to improve accessibility and search visibility.', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </header>
        <div class="bbai-card__body">
        <div class="bbai-opportunity-scanner__prompt-block" data-bbai-scan-prompt <?php echo $bbai_has_scanned ? ' hidden' : ''; ?>>
            <p class="bbai-opportunity-scanner__prompt"><?php esc_html_e( 'Scan your media library to discover SEO opportunities.', 'beepbeep-ai-alt-text-generator' ); ?></p>
            <button type="button" class="bbai-opportunity-scanner__scan-btn bbai-btn bbai-btn-primary bbai-opportunity-scanner__cta" data-bbai-action="scan-opportunity">
                <?php esc_html_e( 'Scan Images Now', 'beepbeep-ai-alt-text-generator' ); ?>
            </button>
        </div>
        <div class="bbai-opportunity-scanner__loading" data-bbai-scan-loading hidden>
            <span class="bbai-opportunity-scanner__spinner" aria-hidden="true"></span>
            <span><?php esc_html_e( 'Scanning media library...', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </div>
        <div class="bbai-opportunity-scanner__results" data-bbai-scan-results <?php echo ! $bbai_has_scanned ? ' hidden' : ''; ?>>
            <div class="bbai-opportunity-scanner__grid bbai-opportunity-scanner__grid--2x2">
                <div class="bbai-opportunity-stat bbai-opportunity-stat--missing">
                    <span class="bbai-opportunity-stat__value bbai-number-animate" data-bbai-stat-missing><?php echo esc_html( number_format_i18n( $missing ) ); ?></span>
                    <span class="bbai-opportunity-stat__label"><?php esc_html_e( 'Missing ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
                </div>
                <div class="bbai-opportunity-stat bbai-opportunity-stat--weak">
                    <span class="bbai-opportunity-stat__value bbai-number-animate" data-bbai-stat-weak><?php echo esc_html( number_format_i18n( $weak ) ); ?></span>
                    <span class="bbai-opportunity-stat__label"><?php esc_html_e( 'Weak ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
                </div>
                <div class="bbai-opportunity-stat bbai-opportunity-stat--filename">
                    <span class="bbai-opportunity-stat__value bbai-number-animate" data-bbai-stat-filename><?php echo esc_html( number_format_i18n( $filename_only ) ); ?></span>
                    <span class="bbai-opportunity-stat__label"><?php esc_html_e( 'Filename ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
                </div>
                <div class="bbai-opportunity-stat bbai-opportunity-stat--duplicate">
                    <span class="bbai-opportunity-stat__value bbai-number-animate" data-bbai-stat-duplicate><?php echo esc_html( number_format_i18n( $duplicate ) ); ?></span>
                    <span class="bbai-opportunity-stat__label"><?php esc_html_e( 'Duplicate ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
                </div>
            </div>

            <p class="bbai-opportunity-scanner__helper">
                <?php
                if ( $weak > 0 ) {
                    printf(
                        /* translators: %s: number of images with weak ALT text */
                        esc_html__( '%s images still have weak ALT text. Fixing them could improve accessibility and search visibility.', 'beepbeep-ai-alt-text-generator' ),
                        esc_html( number_format_i18n( $weak ) )
                    );
                } elseif ( $missing > 0 ) {
                    printf(
                        /* translators: %s: number of images missing ALT text */
                        esc_html__( '%s images are missing ALT text. Adding descriptions could improve accessibility and search visibility.', 'beepbeep-ai-alt-text-generator' ),
                        esc_html( number_format_i18n( $missing ) )
                    );
                } else {
                    esc_html_e( 'All images have ALT text. Review weak descriptions to improve search visibility.', 'beepbeep-ai-alt-text-generator' );
                }
                ?>
            </p>
            <div class="bbai-opportunity-scanner__actions">
                <button type="button" class="bbai-opportunity-scanner__cta bbai-btn bbai-btn-primary" data-bbai-action="scan-opportunity">
                    <?php esc_html_e( 'Scan Images Now', 'beepbeep-ai-alt-text-generator' ); ?>
                </button>
            </div>
        </div>
        </div>
    </article>
</section>
