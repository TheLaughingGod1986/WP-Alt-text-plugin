<?php
/**
 * Dashboard usage guide card.
 *
 * Expects: $bbai_coverage (array from get_alt_text_coverage_scan)
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_coverage = isset( $bbai_coverage ) && is_array( $bbai_coverage ) ? $bbai_coverage : [];

$bbai_total_images = max( 0, (int) ( $bbai_coverage['total_images'] ?? 0 ) );
$bbai_missing_images = max( 0, (int) ( $bbai_coverage['images_missing_alt'] ?? 0 ) );
$bbai_weak_images = max( 0, (int) ( $bbai_coverage['needs_review_count'] ?? 0 ) );
$bbai_filename_only_count = max( 0, (int) ( $bbai_coverage['filename_only_count'] ?? 0 ) );
$bbai_duplicate_alt_count = max( 0, (int) ( $bbai_coverage['duplicate_alt_count'] ?? 0 ) );
$bbai_library_url = add_query_arg( [ 'page' => 'bbai-library' ], admin_url( 'admin.php' ) );

$bbai_has_scanned = $bbai_total_images > 0;
$bbai_show_optimized_guide = $bbai_has_scanned && 0 === $bbai_missing_images && 0 === $bbai_weak_images && 0 === $bbai_filename_only_count && 0 === $bbai_duplicate_alt_count;
$bbai_default_summary = __( 'Follow this simple workflow to use BeepBeep AI:', 'beepbeep-ai-alt-text-generator' );
$bbai_generate_disabled = 0 === $bbai_missing_images;
$bbai_workflow_steps = [
    [
        'icon_class'   => 'dashicons-search',
        'icon_text'    => '',
        'title'        => bbai_copy_cta_scan_media_library(),
        'description'  => __( 'Find images missing ALT text.', 'beepbeep-ai-alt-text-generator' ),
        'button_label' => bbai_copy_cta_scan_media_library(),
        'button_kind'  => 'button',
        'button_style' => 'primary',
        'button_action'=> 'scan-opportunity',
    ],
    [
        'icon_class'   => '',
        'icon_text'    => '⚡',
        'title'        => bbai_copy_cta_generate_missing_images(),
        'description'  => __( 'Create optimized ALT descriptions automatically.', 'beepbeep-ai-alt-text-generator' ),
        'button_label' => bbai_copy_cta_generate_missing_images(),
        'button_kind'  => 'button',
        'button_style' => 'secondary',
        'button_action'=> 'show-generate-alt-modal',
        'disabled'     => $bbai_generate_disabled,
        'helper'       => $bbai_generate_disabled ? __( 'No missing ALT text found.', 'beepbeep-ai-alt-text-generator' ) : '',
    ],
    [
        'icon_class'   => 'dashicons-edit',
        'icon_text'  => '',
        'title'        => bbai_copy_cta_open_alt_library(),
        'description'  => __( 'Edit or approve generated ALT text anytime.', 'beepbeep-ai-alt-text-generator' ),
        'button_label' => bbai_copy_cta_open_alt_library(),
        'button_kind'  => 'link',
        'button_style' => 'secondary',
        'button_href'  => $bbai_library_url,
    ],
];
$bbai_results_summary = $bbai_show_optimized_guide
    ? __( 'Your media library is fully optimized.', 'beepbeep-ai-alt-text-generator' )
    : $bbai_default_summary;
$bbai_results_label = $bbai_show_optimized_guide
    ? __( 'When new images are uploaded:', 'beepbeep-ai-alt-text-generator' )
    : '';
$bbai_results_steps = $bbai_workflow_steps;

$bbai_render_usage_steps = static function ( array $steps ): void {
    foreach ( $steps as $step ) :
        $icon_class = isset( $step['icon_class'] ) ? (string) $step['icon_class'] : '';
        $icon_text  = isset( $step['icon_text'] ) ? (string) $step['icon_text'] : '';
        $button_style = isset( $step['button_style'] ) && 'primary' === $step['button_style']
            ? 'bbai-workflow-step__btn--primary'
            : 'bbai-workflow-step__btn--secondary';
        $button_classes = 'bbai-workflow-step__btn bbai-opportunity-scanner__step-button ' . $button_style;
        $button_disabled = ! empty( $step['disabled'] );
        if ( $button_disabled ) {
            $button_classes .= ' is-disabled';
        }
        ?>
        <li class="bbai-opportunity-scanner__step">
            <span class="bbai-opportunity-scanner__step-icon<?php echo $icon_class ? ' dashicons ' . esc_attr( $icon_class ) : ''; ?>" aria-hidden="true"><?php echo $icon_text ? esc_html( $icon_text ) : ''; ?></span>
            <div class="bbai-opportunity-scanner__step-content">
                <p class="bbai-opportunity-scanner__step-title"><?php echo esc_html( $step['title'] ?? '' ); ?></p>
                <p class="bbai-opportunity-scanner__step-desc"><?php echo esc_html( $step['description'] ?? '' ); ?></p>
                <?php if ( 'link' === ( $step['button_kind'] ?? '' ) ) : ?>
                    <a href="<?php echo esc_url( $step['button_href'] ?? '#' ); ?>" class="<?php echo esc_attr( $button_classes ); ?>"><?php echo esc_html( $step['button_label'] ?? '' ); ?></a>
                <?php else : ?>
                    <button
                        type="button"
                        class="<?php echo esc_attr( $button_classes ); ?>"
                        data-<?php echo 'scan-opportunity' === ( $step['button_action'] ?? '' ) ? 'bbai-action' : 'action'; ?>="<?php echo esc_attr( $step['button_action'] ?? '' ); ?>"
                        <?php echo $button_disabled ? 'disabled aria-disabled="true"' : ''; ?>
                    ><?php echo esc_html( $step['button_label'] ?? '' ); ?></button>
                <?php endif; ?>
                <?php if ( ! empty( $step['helper'] ) ) : ?>
                    <p class="bbai-opportunity-scanner__step-helper"><?php echo esc_html( $step['helper'] ); ?></p>
                <?php endif; ?>
            </div>
        </li>
        <?php
    endforeach;
};
?>
<section class="bbai-dashboard-card bbai-opportunity-scanner" aria-labelledby="bbai-opportunity-title">
    <article class="bbai-opportunity-scanner__card">
        <header class="bbai-card__header">
            <h3 id="bbai-opportunity-title" class="bbai-opportunity-scanner__title"><?php esc_html_e( 'How to use BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?></h3>
            <p class="bbai-card__copy bbai-opportunity-scanner__desc"><?php esc_html_e( 'Quick workflow for scanning and generating ALT text.', 'beepbeep-ai-alt-text-generator' ); ?></p>
        </header>
        <div class="bbai-card__body">
            <div class="bbai-opportunity-scanner__workflow-container">
                <div class="bbai-opportunity-scanner__prompt-block" data-bbai-scan-prompt <?php echo $bbai_has_scanned ? ' hidden' : ''; ?>>
                    <p class="bbai-opportunity-scanner__summary"><?php echo esc_html( $bbai_default_summary ); ?></p>
                    <ol class="bbai-opportunity-scanner__steps">
                        <?php $bbai_render_usage_steps( $bbai_workflow_steps ); ?>
                    </ol>
                </div>
                <div class="bbai-opportunity-scanner__loading" data-bbai-scan-loading hidden>
                    <span class="bbai-opportunity-scanner__spinner" aria-hidden="true"></span>
                    <div class="bbai-opportunity-scanner__loading-copy">
                        <span class="bbai-opportunity-scanner__loading-title"><?php esc_html_e( 'Scanning media library...', 'beepbeep-ai-alt-text-generator' ); ?></span>
                        <span class="bbai-opportunity-scanner__loading-note"><?php esc_html_e( 'Checking images for missing ALT text.', 'beepbeep-ai-alt-text-generator' ); ?></span>
                    </div>
                </div>
                <div class="bbai-opportunity-scanner__results" data-bbai-scan-results <?php echo ! $bbai_has_scanned ? ' hidden' : ''; ?>>
                    <p class="bbai-opportunity-scanner__summary" data-bbai-usage-guide-summary><?php echo esc_html( $bbai_results_summary ); ?></p>
                    <p class="bbai-opportunity-scanner__label" data-bbai-usage-guide-label <?php echo '' !== $bbai_results_label ? '' : 'hidden'; ?>><?php echo esc_html( $bbai_results_label ); ?></p>
                    <ol class="bbai-opportunity-scanner__steps" data-bbai-usage-guide-steps>
                        <?php $bbai_render_usage_steps( $bbai_results_steps ); ?>
                    </ol>
                </div>
            </div>
        </div>
    </article>
</section>
