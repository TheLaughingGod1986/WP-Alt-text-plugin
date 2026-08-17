<?php
/**
 * Dashboard hero credits block.
 *
 * Expected parent scope:
 * - $bbai_hero_credits_remaining_line
 * - $bbai_hero_credits_usage_line
 * - $bbai_hero_credits_progress
 * - $bbai_hero_credits_state
 * - $bbai_hero_credits_comparison_line
 * - $bbai_hero_credits_upgrade_url
 * - $bbai_hero_credits_upgrade_label
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bbai_hero_credits_progress = isset( $bbai_hero_credits_progress ) ? max( 0, min( 100, (int) $bbai_hero_credits_progress ) ) : 0;
$bbai_hero_credits_remaining_line = isset( $bbai_hero_credits_remaining_line ) ? (string) $bbai_hero_credits_remaining_line : '';
$bbai_hero_credits_usage_line = isset( $bbai_hero_credits_usage_line ) ? (string) $bbai_hero_credits_usage_line : '';
$bbai_hero_credits_comparison_line = isset( $bbai_hero_credits_comparison_line ) ? (string) $bbai_hero_credits_comparison_line : '';
$bbai_hero_credits_upgrade_url = isset( $bbai_hero_credits_upgrade_url ) ? (string) $bbai_hero_credits_upgrade_url : '';
$bbai_hero_credits_upgrade_label = isset( $bbai_hero_credits_upgrade_label ) ? (string) $bbai_hero_credits_upgrade_label : '';
$bbai_hero_credits_state = isset( $bbai_hero_credits_state ) ? sanitize_key( (string) $bbai_hero_credits_state ) : 'default';
if ( ! in_array( $bbai_hero_credits_state, [ 'default', 'low', 'exhausted' ], true ) ) {
    $bbai_hero_credits_state = 'default';
}
?>
<section
    class="bbai-dashboard-credits bbai-dashboard-credits--<?php echo esc_attr( $bbai_hero_credits_state ); ?>"
    data-bbai-dashboard-credits
    data-bbai-hero-credits-state="<?php echo esc_attr( $bbai_hero_credits_state ); ?>"
    aria-label="<?php esc_attr_e( 'Credit usage', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <p class="bbai-dashboard-credits__remaining" data-bbai-hero-credits-remaining>
        <?php echo esc_html( $bbai_hero_credits_remaining_line ); ?>
    </p>

    <div
        class="bbai-dashboard-credits__progress"
        data-bbai-hero-credits-progress
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow="<?php echo esc_attr( $bbai_hero_credits_progress ); ?>"
    >
        <span
            class="bbai-dashboard-credits__progress-fill"
            data-bbai-hero-credits-progress-fill
            style="width: <?php echo esc_attr( $bbai_hero_credits_progress ); ?>%;"
        ></span>
    </div>

    <div class="bbai-dashboard-credits__meta">
        <span class="bbai-dashboard-credits__usage" data-bbai-hero-credits-usage>
            <?php echo esc_html( $bbai_hero_credits_usage_line ); ?>
        </span>
        <span
            class="bbai-dashboard-credits__comparison"
            data-bbai-hero-credits-comparison
            <?php echo '' !== $bbai_hero_credits_comparison_line ? '' : 'hidden'; ?>
        >
            <?php echo esc_html( $bbai_hero_credits_comparison_line ); ?>
        </span>
        <a
            href="<?php echo esc_url( $bbai_hero_credits_upgrade_url ); ?>"
            class="bbai-dashboard-credits__upgrade"
            data-bbai-hero-credits-upgrade
            <?php echo '' !== $bbai_hero_credits_upgrade_label ? '' : 'hidden'; ?>
        >
            <?php echo esc_html( $bbai_hero_credits_upgrade_label ); ?>
        </a>
    </div>

    <?php if ( ! empty( $bbai_is_logged_in_dashboard ) ) : ?>
        <p
            class="bbai-dashboard-credits__activity"
            data-bbai-hero-generation-activity
            hidden
        ></p>
    <?php endif; ?>
</section>
