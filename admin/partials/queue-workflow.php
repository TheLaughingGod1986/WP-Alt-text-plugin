<?php
/**
 * Queue Workflow UI - ALT Optimization Pipeline.
 *
 * Replaces scattered action cards / banners with a clear 4-step workflow.
 *
 * Expects $bbai_state (array) with:
 *   total_images, optimized_count, missing_alts, needs_review_count,
 *   has_scan_results, last_scan_timestamp
 *
 * @package BeepBeep_AI_Alt_Text_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_wf_total     = max( 0, (int) ( $bbai_state['total_images'] ?? 0 ) );
$bbai_wf_optimized = max( 0, (int) ( $bbai_state['optimized_count'] ?? 0 ) );
$bbai_wf_missing   = max( 0, (int) ( $bbai_state['missing_alts'] ?? 0 ) );
$bbai_wf_weak      = max( 0, (int) ( $bbai_state['needs_review_count'] ?? 0 ) );
$bbai_wf_pct       = $bbai_wf_total > 0 ? (int) round( ( $bbai_wf_optimized / $bbai_wf_total ) * 100 ) : 0;
$bbai_wf_library   = add_query_arg( [ 'page' => 'bbai-library' ], admin_url( 'admin.php' ) );
$bbai_wf_settings  = admin_url( 'admin.php?page=bbai-settings#bbai-enable-on-upload' );
$bbai_wf_is_pro    = ! empty( $bbai_is_pro );
$bbai_wf_credits_remaining = isset( $bbai_credits_remaining ) ? max( 0, (int) $bbai_credits_remaining ) : 0;
$bbai_wf_is_healthy = $bbai_wf_total > 0 && $bbai_wf_missing <= 0 && $bbai_wf_weak <= 0;

// Determine active step: 1-based index.
$bbai_wf_active = 1;
if ( ! empty( $bbai_state['has_scan_results'] ) && $bbai_wf_missing > 0 ) {
    $bbai_wf_active = 2;
} elseif ( ! empty( $bbai_state['has_scan_results'] ) && ( $bbai_wf_weak > 0 || $bbai_wf_optimized > 0 ) && $bbai_wf_missing <= 0 ) {
    $bbai_wf_active = 3;
}
if ( $bbai_wf_pct >= 90 && $bbai_wf_missing <= 0 && $bbai_wf_weak <= 0 ) {
    $bbai_wf_active = 4;
}

$bbai_wf_steps = [
    [
        'key'    => 'scan',
        'icon'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>',
        'title'  => bbai_copy_cta_scan_media_library(),
        'metric' => sprintf(
            _n( '%s image scanned', '%s images scanned', max( 1, $bbai_wf_total ), 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_wf_total )
        ),
        'btn_label' => bbai_copy_cta_scan_media_library(),
        'btn_attrs' => 'data-bbai-action="scan-opportunity"',
        'btn_tag'   => 'button',
    ],
    [
        'key'    => 'generate',
        'icon'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
        'title'  => bbai_copy_cta_generate_missing_images(),
        'metric' => sprintf(
            _n( '%s image missing ALT', '%s images missing ALT', max( 1, $bbai_wf_missing ), 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_wf_missing )
        ),
        'btn_label' => bbai_copy_cta_generate_missing_images(),
        'btn_attrs' => ! empty( $bbai_limit_reached_state )
            ? 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="queue-workflow-generate" aria-disabled="true"'
            : 'data-action="show-generate-alt-modal"',
        'btn_tag'   => 'button',
    ],
    [
        'key'    => 'review',
        'icon'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
        'title'  => bbai_copy_cta_improve_alt(),
        'metric' => sprintf(
            _n( '%s image needs review', '%s images need review', max( 1, $bbai_wf_weak ), 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_wf_weak )
        ),
        'btn_label' => bbai_copy_cta_improve_alt(),
        'btn_attrs' => 'href="' . esc_url( $bbai_wf_library ) . '#bbai-alt-table" data-bbai-review-scroll="1"',
        'btn_tag'   => 'a',
    ],
    [
        'key'    => 'complete',
        'icon'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>',
        'title'  => __( 'Complete', 'beepbeep-ai-alt-text-generator' ),
        'metric' => sprintf(
            __( '%s%% optimized', 'beepbeep-ai-alt-text-generator' ),
            number_format_i18n( $bbai_wf_pct )
        ),
        'btn_label' => $bbai_wf_pct >= 90 && $bbai_wf_missing <= 0 && $bbai_wf_weak <= 0
            ? __( 'Completed', 'beepbeep-ai-alt-text-generator' )
            : __( 'In progress', 'beepbeep-ai-alt-text-generator' ),
        'btn_attrs' => $bbai_wf_pct >= 90 && $bbai_wf_missing <= 0 && $bbai_wf_weak <= 0
            ? 'data-bbai-wf-action-state="complete"'
            : 'data-bbai-wf-action-state="progress"',
        'btn_tag'   => 'span',
    ],
];
?>

<section
    class="bbai-queue-workflow bbai-dashboard-section"
    data-bbai-queue-workflow
    data-bbai-wf-active="<?php echo esc_attr( $bbai_wf_active ); ?>"
    data-bbai-wf-mode="<?php echo esc_attr( $bbai_wf_is_healthy ? 'compact' : 'steps' ); ?>"
    aria-labelledby="bbai-queue-workflow-title"
>
    <header class="bbai-queue-workflow__header">
        <h2 id="bbai-queue-workflow-title" class="bbai-queue-workflow__title"><?php esc_html_e( 'ALT Optimization Workflow', 'beepbeep-ai-alt-text-generator' ); ?></h2>
        <p class="bbai-queue-workflow__desc"><?php esc_html_e( 'Fix accessibility issues across your media library.', 'beepbeep-ai-alt-text-generator' ); ?></p>
    </header>

    <div class="bbai-queue-workflow__healthy" data-bbai-wf-healthy-panel>
        <div class="bbai-queue-workflow__healthy-main">
            <div class="bbai-queue-workflow__healthy-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="M22 4L12 14.01l-3-3"></path></svg>
            </div>
            <div>
                <p class="bbai-queue-workflow__healthy-eyebrow"><?php esc_html_e( 'Library Health', 'beepbeep-ai-alt-text-generator' ); ?></p>
                <h3 class="bbai-queue-workflow__healthy-title" data-bbai-wf-healthy-title><?php esc_html_e( 'All images are optimized', 'beepbeep-ai-alt-text-generator' ); ?></h3>
                <p class="bbai-queue-workflow__healthy-copy" data-bbai-wf-healthy-copy>
                    <?php
                    echo esc_html(
                        $bbai_wf_is_pro
                            ? __( 'Your media library is fully covered. Scan future uploads or review your automation settings to keep it that way.', 'beepbeep-ai-alt-text-generator' )
                            : __( 'Your media library is fully covered. Scan future uploads or enable automation to keep it that way.', 'beepbeep-ai-alt-text-generator' )
                    );
                    ?>
                </p>
            </div>
        </div>
        <div class="bbai-queue-workflow__healthy-metrics">
            <div class="bbai-queue-workflow__healthy-metric">
                <span class="bbai-queue-workflow__healthy-metric-label"><?php esc_html_e( 'Optimized images', 'beepbeep-ai-alt-text-generator' ); ?></span>
                <strong class="bbai-queue-workflow__healthy-metric-value" data-bbai-wf-healthy-optimized><?php echo esc_html( number_format_i18n( $bbai_wf_optimized ) ); ?></strong>
            </div>
            <div class="bbai-queue-workflow__healthy-metric">
                <span class="bbai-queue-workflow__healthy-metric-label"><?php esc_html_e( 'Credits remaining', 'beepbeep-ai-alt-text-generator' ); ?></span>
                <strong class="bbai-queue-workflow__healthy-metric-value" data-bbai-wf-healthy-credits><?php echo esc_html( number_format_i18n( $bbai_wf_credits_remaining ) ); ?></strong>
            </div>
        </div>
        <div class="bbai-queue-workflow__healthy-actions">
            <button type="button" class="bbai-queue-workflow__healthy-btn bbai-queue-workflow__healthy-btn--primary" data-action="rescan-media-library">
                <?php esc_html_e( 'Scan for new uploads', 'beepbeep-ai-alt-text-generator' ); ?>
            </button>
            <?php if ( $bbai_wf_is_pro ) : ?>
                <a href="<?php echo esc_url( $bbai_wf_settings ); ?>" class="bbai-queue-workflow__healthy-btn">
                    <?php esc_html_e( 'Manage auto-optimization', 'beepbeep-ai-alt-text-generator' ); ?>
                </a>
            <?php else : ?>
                <button
                    type="button"
                    class="bbai-queue-workflow__healthy-btn bbai-queue-workflow__healthy-btn--upgrade"
                    data-bbai-action="open-upgrade"
                    data-bbai-locked-cta="1"
                    data-bbai-lock-reason="automation"
                    data-bbai-locked-source="queue-workflow-healthy"
                >
                    <?php esc_html_e( 'Enable automatic ALT with Pro', 'beepbeep-ai-alt-text-generator' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bbai-queue-workflow__steps" role="list">
        <?php foreach ( $bbai_wf_steps as $bbai_wf_i => $bbai_wf_step ) :
            $bbai_wf_step_num   = $bbai_wf_i + 1;
            $bbai_wf_is_active  = $bbai_wf_step_num === $bbai_wf_active;
            $bbai_wf_is_done    = $bbai_wf_step_num < $bbai_wf_active;
            $bbai_wf_step_class = 'bbai-queue-workflow__step';
            if ( $bbai_wf_is_active ) {
                $bbai_wf_step_class .= ' bbai-queue-workflow__step--active';
            }
            if ( $bbai_wf_is_done ) {
                $bbai_wf_step_class .= ' bbai-queue-workflow__step--done';
            }
            if ( 'complete' === $bbai_wf_step['key'] && $bbai_wf_pct >= 90 && $bbai_wf_missing <= 0 && $bbai_wf_weak <= 0 ) {
                $bbai_wf_step_class .= ' bbai-queue-workflow__step--success';
            }
        ?>
        <div
            class="<?php echo esc_attr( $bbai_wf_step_class ); ?>"
            role="listitem"
            data-bbai-wf-step="<?php echo esc_attr( $bbai_wf_step['key'] ); ?>"
            data-bbai-wf-step-num="<?php echo esc_attr( $bbai_wf_step_num ); ?>"
        >
            <div class="bbai-queue-workflow__step-badge">
                <span class="bbai-queue-workflow__step-num"><?php echo esc_html( $bbai_wf_step_num ); ?></span>
                <span class="bbai-queue-workflow__step-check" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
            </div>
            <div class="bbai-queue-workflow__step-icon" aria-hidden="true"><?php echo $bbai_wf_step['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup defined above. ?></div>
            <h3 class="bbai-queue-workflow__step-title"><?php echo esc_html( $bbai_wf_step['title'] ); ?></h3>
            <p class="bbai-queue-workflow__step-metric" data-bbai-wf-metric="<?php echo esc_attr( $bbai_wf_step['key'] ); ?>"><?php echo esc_html( $bbai_wf_step['metric'] ); ?></p>
            <?php if ( '' !== $bbai_wf_step['btn_label'] ) : ?>
            <<?php echo esc_attr( $bbai_wf_step['btn_tag'] ); ?>
                class="bbai-queue-workflow__step-btn<?php echo 'span' === $bbai_wf_step['btn_tag'] ? ' bbai-queue-workflow__step-btn--static' : ''; ?>"
                data-bbai-wf-action="<?php echo esc_attr( $bbai_wf_step['key'] ); ?>"
                <?php echo 'button' === $bbai_wf_step['btn_tag'] ? 'type="button"' : ''; ?>
                <?php echo $bbai_wf_step['btn_attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes built from escaped values. ?>
            ><?php echo esc_html( $bbai_wf_step['btn_label'] ); ?></<?php echo esc_attr( $bbai_wf_step['btn_tag'] ); ?>>
            <?php endif; ?>
        </div>
        <?php if ( $bbai_wf_step_num < count( $bbai_wf_steps ) ) : ?>
        <div class="bbai-queue-workflow__arrow<?php echo $bbai_wf_is_done ? ' bbai-queue-workflow__arrow--done' : ''; ?>" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
