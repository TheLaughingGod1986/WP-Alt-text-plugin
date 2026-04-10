<?php
/**
 * Dashboard locked ALT results teaser for exhausted anonymous trial users.
 *
 * Expected parent scope:
 * - $bbai_is_anonymous_trial
 * - $bbai_has_connected_account
 * - $bbai_state_credits_remaining
 * - $bbai_state_missing_count
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $bbai_is_anonymous_trial ) || ! empty( $bbai_has_connected_account ) ) {
    return;
}

$bbai_is_exhausted_trial_checkpoint = max( 0, (int) ( $bbai_state_credits_remaining ?? 0 ) ) <= 0;
$bbai_locked_preview_rows = [
    [
        'status' => __( 'Missing', 'beepbeep-ai-alt-text-generator' ),
        'tone'   => 'missing',
    ],
    [
        'status' => __( 'Needs review', 'beepbeep-ai-alt-text-generator' ),
        'tone'   => 'review',
    ],
    [
        'status' => __( 'Optimized', 'beepbeep-ai-alt-text-generator' ),
        'tone'   => 'optimized',
    ],
];
?>
<div
    class="bbai-dashboard-locked-preview-stack"
    data-bbai-trial-locked-preview="1"
    <?php echo $bbai_is_exhausted_trial_checkpoint ? '' : 'hidden'; ?>
>
    <section
        class="bbai-dashboard-locked-preview"
        aria-labelledby="bbai-dashboard-locked-preview-heading"
    >
        <div class="bbai-dashboard-locked-preview__header">
            <h2 id="bbai-dashboard-locked-preview-heading" class="bbai-dashboard-locked-preview__title">
                <?php esc_html_e( 'Unlock your full ALT library', 'beepbeep-ai-alt-text-generator' ); ?>
            </h2>
            <p class="bbai-dashboard-locked-preview__description">
                <?php esc_html_e( 'Review, edit, and optimise all your images from one focused library.', 'beepbeep-ai-alt-text-generator' ); ?>
            </p>
        </div>

        <div class="bbai-dashboard-locked-preview__shell">
            <div class="bbai-dashboard-locked-preview__mock" aria-hidden="true">
                <?php foreach ( $bbai_locked_preview_rows as $bbai_locked_preview_row ) : ?>
                    <div class="bbai-dashboard-locked-preview__row">
                        <div class="bbai-dashboard-locked-preview__thumb"></div>
                        <div class="bbai-dashboard-locked-preview__content">
                            <div class="bbai-dashboard-locked-preview__meta">
                                <span class="bbai-dashboard-locked-preview__filename"></span>
                                <span class="bbai-dashboard-locked-preview__badge bbai-dashboard-locked-preview__badge--<?php echo esc_attr( $bbai_locked_preview_row['tone'] ); ?>">
                                    <?php echo esc_html( $bbai_locked_preview_row['status'] ); ?>
                                </span>
                            </div>
                            <div class="bbai-dashboard-locked-preview__alt-lines">
                                <span class="bbai-dashboard-locked-preview__line bbai-dashboard-locked-preview__line--wide"></span>
                                <span class="bbai-dashboard-locked-preview__line bbai-dashboard-locked-preview__line--mid"></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="bbai-dashboard-locked-preview__fade" aria-hidden="true"></div>

            <div class="bbai-dashboard-locked-preview__overlay">
                <div class="bbai-dashboard-locked-preview__overlay-card">
                    <h3 class="bbai-dashboard-locked-preview__overlay-title">
                        <?php esc_html_e( 'You’re one step away from finishing your optimisation', 'beepbeep-ai-alt-text-generator' ); ?>
                    </h3>
                    <p class="bbai-dashboard-locked-preview__overlay-copy">
                        <?php esc_html_e( 'Review, edit, and optimise your library once you’re signed in.', 'beepbeep-ai-alt-text-generator' ); ?>
                    </p>

                    <div class="bbai-dashboard-locked-preview__actions">
                        <button
                            type="button"
                            class="bbai-btn bbai-btn-primary bbai-dashboard-locked-preview__cta bbai-dashboard-locked-preview__cta--primary"
                            data-action="show-dashboard-auth"
                            data-auth-tab="register"
                        >
                            <?php esc_html_e( 'Fix remaining images for free', 'beepbeep-ai-alt-text-generator' ); ?>
                        </button>
                        <button
                            type="button"
                            class="bbai-btn bbai-btn-secondary bbai-dashboard-locked-preview__cta bbai-dashboard-locked-preview__cta--secondary"
                            data-action="show-dashboard-auth"
                            data-auth-tab="login"
                        >
                            <?php esc_html_e( 'Login', 'beepbeep-ai-alt-text-generator' ); ?>
                        </button>
                    </div>

                    <ul class="bbai-dashboard-locked-preview__benefits" aria-label="<?php esc_attr_e( 'Unlocked value', 'beepbeep-ai-alt-text-generator' ); ?>">
                        <li class="bbai-dashboard-locked-preview__benefit">
                            <span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
                            <span><?php esc_html_e( 'Review and edit ALT text', 'beepbeep-ai-alt-text-generator' ); ?></span>
                        </li>
                        <li class="bbai-dashboard-locked-preview__benefit">
                            <span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
                            <span><?php esc_html_e( 'Bulk optimise your media library', 'beepbeep-ai-alt-text-generator' ); ?></span>
                        </li>
                        <li class="bbai-dashboard-locked-preview__benefit">
                            <span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
                            <span><?php esc_html_e( '50 generations per month', 'beepbeep-ai-alt-text-generator' ); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <div class="bbai-dashboard-value-strip bbai-dashboard-value-strip--locked-preview bbai-benefits-row bbai-trust-grid" aria-label="<?php esc_attr_e( 'Core value', 'beepbeep-ai-alt-text-generator' ); ?>">
        <div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
            <span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
            <span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Improve image SEO rankings', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </div>
        <div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
            <span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
            <span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Boost accessibility compliance', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </div>
        <div class="bbai-dashboard-value-strip__item bbai-benefit-item bbai-trust-grid__item">
            <span class="bbai-dashboard-value-strip__icon bbai-benefit-icon bbai-trust-grid__icon" aria-hidden="true">✓</span>
            <span class="bbai-benefit-text bbai-trust-grid__text"><?php esc_html_e( 'Save hours of manual work', 'beepbeep-ai-alt-text-generator' ); ?></span>
        </div>
    </div>
</div>
