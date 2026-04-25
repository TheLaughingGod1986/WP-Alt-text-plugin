<?php
/**
 * Logged-out / anonymous-trial FTUE dashboard.
 *
 * Shown when the user has no connected BeepBeep AI account.
 * Renders a trial_available or trial_complete hero panel depending on usage.
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
$bbai_lo_hero_state      = ( $bbai_lo_trial_exhausted || $bbai_lo_trial_used > 0 ) ? 'trial_complete' : 'trial_available';
$bbai_lo_is_available    = 'trial_available' === $bbai_lo_hero_state;
$bbai_lo_is_complete     = 'trial_complete'  === $bbai_lo_hero_state;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing only.
$bbai_lo_page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bbai';
$bbai_lo_fallback_url = admin_url( 'admin.php?page=' . $bbai_lo_page );
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

        <?php /* --- Trial-complete / conversion panel --------------------- */ ?>
        <section
            id="bbai-ftue-panel-conversion"
            class="bbai-ftue-panel bbai-ftue-panel--conversion"
            aria-labelledby="bbai-logged-out-title-conversion"
            <?php echo $bbai_lo_is_available ? 'hidden' : ''; ?>
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

    </div><!-- .bbai-logged-out__container -->
</div><!-- .bbai-logged-out -->
