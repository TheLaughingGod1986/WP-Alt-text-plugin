<?php
/**
 * Usage + upgrade comparison strip below the funnel hero.
 *
 * Expected variables from dashboard-body.php parent scope:
 *   $bbai_state_credits_used, $bbai_state_credits_remaining, $bbai_state_credits_limit,
 *   $bbai_plan_label, $bbai_state_is_pro_plan, $bbai_is_anonymous_trial
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Only show for authenticated non-trial users with a credit system.
if ( ! empty( $bbai_is_anonymous_trial ) ) {
    return;
}

$bbai_strip_used      = isset( $bbai_state_credits_used ) ? max( 0, (int) $bbai_state_credits_used ) : 0;
$bbai_strip_remaining = isset( $bbai_state_credits_remaining ) ? max( 0, (int) $bbai_state_credits_remaining ) : 0;
$bbai_strip_limit     = isset( $bbai_state_credits_limit ) ? max( 1, (int) $bbai_state_credits_limit ) : 50;
$bbai_strip_pct       = min( 100, round( ( $bbai_strip_used / $bbai_strip_limit ) * 100 ) );
$bbai_strip_plan      = isset( $bbai_plan_label ) ? (string) $bbai_plan_label : 'Free';
$bbai_strip_is_pro    = ! empty( $bbai_state_is_pro_plan );
$bbai_strip_growth_limit = 500; // Growth plan baseline for comparison.
?>
<div class="bbai-usage-strip" data-bbai-usage-strip>
    <!-- Current plan usage -->
    <div class="bbai-usage-strip__row">
        <div class="bbai-usage-strip__label">
            <span class="bbai-usage-strip__plan-badge"><?php echo esc_html( $bbai_strip_plan ); ?></span>
            <span class="bbai-usage-strip__remaining">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: number of generations remaining */
                        __( '%s generations remaining this month', 'beepbeep-ai-alt-text-generator' ),
                        number_format_i18n( $bbai_strip_remaining )
                    )
                );
                ?>
            </span>
        </div>
        <div class="bbai-usage-strip__bar">
            <div class="bbai-usage-strip__bar-fill" style="width: <?php echo esc_attr( $bbai_strip_pct ); ?>%;" data-bbai-usage-bar-fill></div>
        </div>
        <span class="bbai-usage-strip__ratio"><?php echo esc_html( number_format_i18n( $bbai_strip_used ) . ' / ' . number_format_i18n( $bbai_strip_limit ) ); ?></span>
    </div>

    <?php if ( ! $bbai_strip_is_pro ) : ?>
    <!-- Upgrade comparison -->
    <div class="bbai-usage-strip__row bbai-usage-strip__row--comparison">
        <div class="bbai-usage-strip__label">
            <span class="bbai-usage-strip__plan-badge bbai-usage-strip__plan-badge--upgrade"><?php esc_html_e( 'Growth', 'beepbeep-ai-alt-text-generator' ); ?></span>
            <span class="bbai-usage-strip__remaining bbai-usage-strip__remaining--muted">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: number of generations on Growth plan */
                        __( '%s generations / month', 'beepbeep-ai-alt-text-generator' ),
                        number_format_i18n( $bbai_strip_growth_limit )
                    )
                );
                ?>
            </span>
        </div>
        <div class="bbai-usage-strip__bar bbai-usage-strip__bar--comparison">
            <div
                class="bbai-usage-strip__bar-fill bbai-usage-strip__bar-fill--comparison"
                style="width: <?php echo esc_attr( min( 100, round( ( $bbai_strip_used / $bbai_strip_growth_limit ) * 100 ) ) ); ?>%;"
            ></div>
        </div>
        <span class="bbai-usage-strip__ratio"><?php echo esc_html( number_format_i18n( $bbai_strip_used ) . ' / ' . number_format_i18n( $bbai_strip_growth_limit ) ); ?></span>
    </div>
    <div class="bbai-usage-strip__upgrade">
        <p class="bbai-usage-strip__upgrade-copy"><?php esc_html_e( 'Upgrade to process your full library in one go', 'beepbeep-ai-alt-text-generator' ); ?></p>
        <a
            href="#"
            class="bbai-btn bbai-btn-secondary bbai-usage-strip__upgrade-cta"
            data-action="show-upgrade-modal"
            data-upgrade-trigger="true"
        ><?php esc_html_e( 'View plans', 'beepbeep-ai-alt-text-generator' ); ?></a>
    </div>
    <?php endif; ?>
</div>
