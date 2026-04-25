<?php
/**
 * Logged-out / anonymous-trial FTUE dashboard.
 *
 * Shown when the user has no connected BeepBeep AI account.
 * Renders a trial_available, trial_complete, or trial_exhausted hero panel
 * depending on usage.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' ) ) {
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
}

$bbai_lo_trial_status    = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
$bbai_lo_trial_used      = max( 0, (int) ( $bbai_lo_trial_status['used']      ?? 0 ) );
$bbai_lo_trial_remaining = max( 0, (int) ( $bbai_lo_trial_status['remaining'] ?? 0 ) );
$bbai_lo_trial_limit     = max( 1, (int) ( $bbai_lo_trial_status['limit']     ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit() ) );
$bbai_lo_trial_exhausted = ! empty( $bbai_lo_trial_status['exhausted'] )
    || $bbai_lo_trial_remaining <= 0
    || $bbai_lo_trial_used >= $bbai_lo_trial_limit;

// State: trial_complete once any usage is recorded or trial is exhausted.
$bbai_lo_hero_state   = ( $bbai_lo_trial_exhausted || $bbai_lo_trial_used > 0 ) ? 'trial_complete' : 'trial_available';
$bbai_lo_is_available = 'trial_available' === $bbai_lo_hero_state;
$bbai_lo_is_complete  = 'trial_complete'  === $bbai_lo_hero_state;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing only.
$bbai_lo_page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bbai';
$bbai_lo_fallback_url = admin_url( 'admin.php?page=' . $bbai_lo_page );

// Image counts — needed only for the exhausted hero and locked preview.
$bbai_lo_missing_count      = 0;
$bbai_lo_needs_review_count = 0;
$bbai_lo_optimized_count    = 0;
$bbai_lo_total_images       = 0;

if ( $bbai_lo_trial_exhausted ) {
    if ( ! function_exists( 'bbai_get_attention_counts' ) ) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
    }
    $bbai_lo_attn               = bbai_get_attention_counts();
    $bbai_lo_missing_count      = max( 0, (int) ( $bbai_lo_attn['missing']         ?? 0 ) );
    $bbai_lo_needs_review_count = max( 0, (int) ( $bbai_lo_attn['needs_review']    ?? 0 ) );
    $bbai_lo_optimized_count    = max( 0, (int) ( $bbai_lo_attn['optimized_count'] ?? 0 ) );
    $bbai_lo_total_images       = max( 0, (int) ( $bbai_lo_attn['total_images']    ?? 0 ) );
    if ( $bbai_lo_total_images <= 0 ) {
        $bbai_lo_total_images = $bbai_lo_missing_count + $bbai_lo_needs_review_count + $bbai_lo_optimized_count;
    }
}

// remaining_for_cta = missing images (ungenerated); mirrors dashboard-hero.php convention.
// Falls back to missing + needs_review when missing is 0.
$bbai_lo_remaining_for_cta = $bbai_lo_missing_count > 0
    ? $bbai_lo_missing_count
    : $bbai_lo_missing_count + $bbai_lo_needs_review_count;

// Exhausted hero copy.
$bbai_lo_exhausted_headline = sprintf(
    /* translators: %d: number of free trial generations (e.g. 5). */
    __( "You've used all %d free generations", 'beepbeep-ai-alt-text-generator' ),
    $bbai_lo_trial_limit
);
$bbai_lo_exhausted_support  = __( 'Continue fixing your remaining images and unlock full access.', 'beepbeep-ai-alt-text-generator' );
$bbai_lo_exhausted_subline  = $bbai_lo_remaining_for_cta > 0
    ? sprintf(
        /* translators: %s: number of remaining images. */
        _n(
            "You're %s image away from full optimisation",
            "You're %s images away from full optimisation",
            $bbai_lo_remaining_for_cta,
            'beepbeep-ai-alt-text-generator'
        ),
        number_format_i18n( $bbai_lo_remaining_for_cta )
    )
    : '';
$bbai_lo_exhausted_cta      = $bbai_lo_remaining_for_cta > 0
    ? sprintf(
        /* translators: %s: number of remaining images needing alt text. */
        __( 'Fix your %s remaining images', 'beepbeep-ai-alt-text-generator' ),
        number_format_i18n( $bbai_lo_remaining_for_cta )
    )
    : __( 'Create your free account', 'beepbeep-ai-alt-text-generator' );

// Donut background — shows image coverage for the exhausted hero card.
$bbai_lo_donut_bg = 'conic-gradient(#e2e8f0 0deg 360deg)';
if ( $bbai_lo_total_images > 0 ) {
    $bbai_lo_opt_angle  = ( 360.0 * $bbai_lo_optimized_count )    / $bbai_lo_total_images;
    $bbai_lo_weak_angle = ( 360.0 * $bbai_lo_needs_review_count ) / $bbai_lo_total_images;
    $bbai_lo_opt_end    = min( 360.0, $bbai_lo_opt_angle );
    $bbai_lo_weak_end   = min( 360.0, $bbai_lo_opt_end + $bbai_lo_weak_angle );
    $bbai_lo_donut_bg   = sprintf(
        'conic-gradient(#22c55e 0deg %.3fdeg, #f59e0b %.3fdeg %.3fdeg, #ef4444 %.3fdeg 360deg)',
        $bbai_lo_opt_end,
        $bbai_lo_opt_end,
        $bbai_lo_weak_end,
        $bbai_lo_weak_end
    );
}
?>
<div
    class="bbai-logged-out"
    role="main"
    data-hero-state="<?php echo esc_attr( $bbai_lo_hero_state ); ?>"
    aria-label="<?php esc_attr_e( 'Get started with BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?>"
>
    <div class="bbai-logged-out__container">

        <?php /* --- Trial-available panel --------------------------------- */ ?>
        <section
            id="bbai-ftue-panel-trial"
            class="bbai-ftue-panel bbai-ftue-panel--trial"
            aria-labelledby="bbai-ftue-trial-title"
            <?php echo $bbai_lo_is_complete ? 'hidden' : ''; ?>
        >
            <header class="bbai-logged-out__header">
                <h1 id="bbai-ftue-trial-title" class="bbai-logged-out__title">
                    <?php esc_html_e( 'Start fixing missing alt text for free', 'beepbeep-ai-alt-text-generator' ); ?>
                </h1>
                <p class="bbai-logged-out__subtitle">
                    <?php esc_html_e( 'No account needed. Starts instantly.', 'beepbeep-ai-alt-text-generator' ); ?>
                </p>
            </header>

            <?php if ( $bbai_lo_is_available ) : ?>
            <div class="bbai-logged-out__actions">
                <button
                    type="button"
                    id="bbai-trial-generate-btn"
                    class="button button-primary button-large bbai-btn bbai-btn-primary"
                    data-action="start-trial"
                >
                    <?php
                    $bbai_lo_trial_count = max( 1, $bbai_lo_trial_limit );
                    echo esc_html(
                        sprintf(
                            /* translators: %s: number of free images. */
                            _n(
                                'Generate alt text for %s image (free)',
                                'Generate alt text for %s images (free)',
                                $bbai_lo_trial_count,
                                'beepbeep-ai-alt-text-generator'
                            ),
                            number_format_i18n( $bbai_lo_trial_count )
                        )
                    );
                    ?>
                </button>
            </div>
            <p class="bbai-ftue-proof">
                <?php esc_html_e( 'No credit card required · Takes ~10 seconds', 'beepbeep-ai-alt-text-generator' ); ?>
            </p>
            <?php endif; ?>
        </section>

        <?php /* --- Trial-complete (not exhausted): conversion panel ------ */ ?>
        <section
            id="bbai-ftue-panel-conversion"
            class="bbai-ftue-panel bbai-ftue-panel--conversion"
            aria-labelledby="bbai-logged-out-title-conversion"
            <?php echo ( $bbai_lo_is_available || $bbai_lo_trial_exhausted ) ? 'hidden' : ''; ?>
        >
            <header class="bbai-logged-out__header">
                <h1 id="bbai-logged-out-title-conversion" class="bbai-logged-out__title">
                    <?php esc_html_e( 'You generated your first alt text — keep going with a free account', 'beepbeep-ai-alt-text-generator' ); ?>
                </h1>
                <p class="bbai-logged-out__subtitle">
                    <?php esc_html_e( 'Create a free account to keep fixing your remaining images, review every ALT text, and unlock 50 generations per month.', 'beepbeep-ai-alt-text-generator' ); ?>
                </p>
            </header>
            <div class="bbai-logged-out__actions">
                <a
                    id="bbai-conversion-register-btn"
                    class="button button-primary button-large bbai-btn bbai-btn-primary"
                    href="<?php echo esc_url( $bbai_lo_fallback_url ); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="register"
                >
                    <?php esc_html_e( 'Create free account', 'beepbeep-ai-alt-text-generator' ); ?>
                </a>
            </div>
            <p class="bbai-logged-out__login-link">
                <a
                    id="bbai-conversion-login-btn"
                    href="<?php echo esc_url( $bbai_lo_fallback_url ); ?>"
                    data-action="show-auth-modal"
                    data-auth-tab="login"
                    class="bbai-link"
                >
                    <?php esc_html_e( 'Already have an account? Log in', 'beepbeep-ai-alt-text-generator' ); ?>
                </a>
            </p>
        </section>

        <?php /* --- Trial-exhausted: approved dashboard hero design ------- */ ?>
        <?php $bbai_lo_donut_tone = $bbai_lo_remaining_for_cta > 0 ? 'problem' : 'neutral'; ?>
        <div
            id="bbai-ftue-panel-exhausted"
            <?php echo ! $bbai_lo_trial_exhausted ? 'hidden' : ''; ?>
        >
            <?php /*
             * Inner wrapper uses the same ID/classes as dashboard-body.php so that
             * funnel-hero.css CSS variables (donut size, column widths, etc.) apply.
             * dashboard-logged-out.php and dashboard-body.php are mutually exclusive
             * render paths so the #bbai-dashboard-main ID is safe here.
             */ ?>
            <div id="bbai-dashboard-main" class="bbai-dashboard bbai-container" data-bbai-dashboard-container>
                <section
                    class="bbai-funnel-hero bbai-funnel-hero--hero-card bbai-funnel-hero--trial-exhausted"
                    aria-labelledby="bbai-lo-exhausted-title"
                >
                    <div class="bbai-dashboard-hero-action">
                        <div class="bbai-dashboard-hero-action__main">

                            <div class="bbai-dashboard-hero-action__status">
                                <div class="bbai-dashboard-hero-action__donut-wrap">
                                    <div
                                        class="bbai-command-donut bbai-command-donut--funnel bbai-dashboard-hero-action__donut bbai-command-donut--<?php echo esc_attr( $bbai_lo_donut_tone ); ?>"
                                        style="background: <?php echo esc_attr( $bbai_lo_donut_bg ); ?>;"
                                        aria-hidden="true"
                                    >
                                        <span class="bbai-command-donut__inner"></span>
                                        <span class="bbai-command-donut__center">
                                            <span class="bbai-command-donut__center-value bbai-command-donut__center-value--<?php echo esc_attr( $bbai_lo_donut_tone ); ?>">
                                                <?php echo esc_html( number_format_i18n( $bbai_lo_remaining_for_cta ) ); ?>
                                            </span>
                                            <span class="bbai-command-donut__center-label" hidden></span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="bbai-dashboard-hero-action__content">
                                <h1 id="bbai-lo-exhausted-title" class="bbai-dashboard-hero-action__title">
                                    <?php echo esc_html( $bbai_lo_exhausted_headline ); ?>
                                </h1>

                                <div class="bbai-dashboard-hero-action__content-flow">
                                    <p class="bbai-dashboard-hero-action__description">
                                        <?php echo esc_html( $bbai_lo_exhausted_support ); ?>
                                    </p>

                                    <?php if ( $bbai_lo_exhausted_subline ) : ?>
                                    <p class="bbai-dashboard-hero-action__progress-line bbai-dashboard-hero-action__cta-context">
                                        <?php echo esc_html( $bbai_lo_exhausted_subline ); ?>
                                    </p>
                                    <?php endif; ?>

                                    <div class="bbai-dashboard-hero-action__cta-group">
                                        <div class="bbai-dashboard-hero-action__actions">
                                            <button
                                                type="button"
                                                class="bbai-command-action bbai-command-action--primary bbai-btn bbai-btn-primary bbai-dashboard-hero-action__cta bbai-dashboard-hero-action__cta--primary"
                                                data-action="show-dashboard-auth"
                                                data-auth-tab="register"
                                                data-bbai-modal-context="fix"
                                                data-bbai-analytics-upgrade="trial_create_account_clicked"
                                            >
                                                <?php echo esc_html( $bbai_lo_exhausted_cta ); ?>
                                            </button>
                                        </div>
                                    </div>

                                    <p class="bbai-dashboard-hero-action__signin">
                                        <?php esc_html_e( 'Already have an account?', 'beepbeep-ai-alt-text-generator' ); ?>
                                        <a
                                            href="#"
                                            class="bbai-link"
                                            data-action="show-dashboard-auth"
                                            data-auth-tab="login"
                                            data-bbai-modal-context="login"
                                        ><?php esc_html_e( 'Sign in', 'beepbeep-ai-alt-text-generator' ); ?></a>
                                    </p>

                                    <p class="bbai-dashboard-hero-action__support">
                                        <?php esc_html_e( 'No credit card required', 'beepbeep-ai-alt-text-generator' ); ?>
                                    </p>
                                </div>
                            </div>
                        </div><!-- /.bbai-dashboard-hero-action__main -->

                        <?php /* Status chips — full-width below the two-column hero, inside the card */ ?>
                        <fieldset
                            class="bbai-filter-group bbai-filter-group--status bbai-filter-group--readonly bbai-filter-group--compact"
                            aria-label="<?php esc_attr_e( 'Image status', 'beepbeep-ai-alt-text-generator' ); ?>"
                        >
                            <span class="bbai-filter-group__item bbai-filter-group__item--readonly bbai-filter-group__item--status-all">
                                <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
                                <span class="bbai-filter-group__label"><?php esc_html_e( 'All', 'beepbeep-ai-alt-text-generator' ); ?></span>
                                <span class="bbai-filter-group__count"><?php echo esc_html( number_format_i18n( $bbai_lo_total_images ) ); ?></span>
                            </span>
                            <span class="bbai-filter-group__item bbai-filter-group__item--readonly bbai-filter-group__item--status-missing">
                                <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
                                <span class="bbai-filter-group__label"><?php esc_html_e( 'Missing', 'beepbeep-ai-alt-text-generator' ); ?></span>
                                <span class="bbai-filter-group__count"><?php echo esc_html( number_format_i18n( $bbai_lo_missing_count ) ); ?></span>
                            </span>
                            <span class="bbai-filter-group__item bbai-filter-group__item--readonly bbai-filter-group__item--status-review">
                                <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
                                <span class="bbai-filter-group__label"><?php esc_html_e( 'Needs review', 'beepbeep-ai-alt-text-generator' ); ?></span>
                                <span class="bbai-filter-group__count"><?php echo esc_html( number_format_i18n( $bbai_lo_needs_review_count ) ); ?></span>
                            </span>
                            <span class="bbai-filter-group__item bbai-filter-group__item--readonly bbai-filter-group__item--status-optimized">
                                <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
                                <span class="bbai-filter-group__label"><?php esc_html_e( 'Optimized', 'beepbeep-ai-alt-text-generator' ); ?></span>
                                <span class="bbai-filter-group__count"><?php echo esc_html( number_format_i18n( $bbai_lo_optimized_count ) ); ?></span>
                            </span>
                        </fieldset>
                    </div><!-- /.bbai-dashboard-hero-action -->
                </section><!-- /.bbai-funnel-hero -->
            </div><!-- /#bbai-dashboard-main -->
        </div><!-- /#bbai-ftue-panel-exhausted -->

        <?php /* --- Locked ALT library upsell (exhausted state only) ------ */ ?>
        <?php if ( $bbai_lo_trial_exhausted ) : ?>
        <?php
        // Set the scope variables expected by dashboard-trial-locked-preview.php.
        // $bbai_state_missing_count drives the "Fix your N remaining images" CTA label;
        // use the same value as the hero so both show a consistent number.
        $bbai_is_anonymous_trial      = true;
        $bbai_has_connected_account   = false;
        $bbai_state_credits_remaining = 0;
        $bbai_state_missing_count     = $bbai_lo_remaining_for_cta;
        $bbai_lo_locked_partial       = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-trial-locked-preview.php';
        if ( is_readable( $bbai_lo_locked_partial ) ) {
            include $bbai_lo_locked_partial;
        }
        ?>
        <?php endif; ?>

        <?php /* --- Marketing showcase: gated off by default -------------- */ ?>
        <?php if ( apply_filters( 'bbai_show_logged_out_marketing_showcase', false ) ) : ?>
        <section class="bbai-ftue-preview" aria-labelledby="bbai-preview-title">
            <h2 id="bbai-preview-title" class="bbai-ftue-preview__title">
                <?php esc_html_e( 'See the difference', 'beepbeep-ai-alt-text-generator' ); ?>
            </h2>
            <div class="bbai-ftue-preview__cols">
                <p class="bbai-ftue-preview__line bbai-ftue-preview__line--before">
                    <?php esc_html_e( 'Before: image without alt text (invisible to screen readers and search engines)', 'beepbeep-ai-alt-text-generator' ); ?>
                </p>
                <p class="bbai-ftue-preview__line bbai-ftue-preview__line--after">
                    <?php esc_html_e( 'After: descriptive alt text generated automatically', 'beepbeep-ai-alt-text-generator' ); ?>
                </p>
            </div>
        </section>
        <?php endif; ?>

    </div><!-- .bbai-logged-out__container -->
</div><!-- .bbai-logged-out -->
