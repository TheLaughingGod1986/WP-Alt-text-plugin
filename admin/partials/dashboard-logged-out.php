<?php
/**
 * Legacy full-page FTUE (optional). Loaded only when BBAI_FORCE_CLEAN_LOGGED_OUT is true in dashboard-body.php.
 *
 * The default product path for users without a connected account is dashboard-body.php + dashboard-hero.php
 * (funnel + exhausted library block), not this template.
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

$bbai_lo_visible_panel = $bbai_lo_trial_exhausted
	? 'exhausted'
	: ( $bbai_lo_is_available ? 'available' : 'conversion' );

$bbai_lo_show_conversion_modal  = ( 'conversion' === $bbai_lo_visible_panel );
$bbai_lo_show_exhausted_section = ( 'exhausted' === $bbai_lo_visible_panel );

$bbai_lo_trial_block_visible = ! $bbai_lo_trial_exhausted;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing only.
$bbai_lo_page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'bbai';
$bbai_lo_fallback_url = admin_url( 'admin.php?page=' . $bbai_lo_page );
?>
<div
	class="bbai-logged-out"
	role="main"
	data-hero-state="<?php echo esc_attr( $bbai_lo_hero_state ); ?>"
	data-bbai-ftue-panel="<?php echo esc_attr( $bbai_lo_visible_panel ); ?>"
	data-bbai-trial-used="<?php echo esc_attr( (string) $bbai_lo_trial_used ); ?>"
	data-bbai-trial-remaining="<?php echo esc_attr( (string) $bbai_lo_trial_remaining ); ?>"
	data-bbai-trial-limit="<?php echo esc_attr( (string) $bbai_lo_trial_limit ); ?>"
	<?php echo $bbai_lo_show_conversion_modal ? ' data-bbai-conversion-modal="1"' : ''; ?>
	aria-label="<?php esc_attr_e( 'Get started with BeepBeep AI', 'beepbeep-ai-alt-text-generator' ); ?>"
>
	<div class="bbai-logged-out__container">

		<?php /* --- Underlay: trial panel (free generations left) + generate CTA ---------------- */ ?>
		<section
			id="bbai-ftue-panel-trial"
			class="bbai-ftue-panel bbai-ftue-panel--trial"
			aria-labelledby="bbai-ftue-trial-title"
			<?php echo $bbai_lo_trial_exhausted ? 'hidden' : ''; ?>
		>
			<header class="bbai-logged-out__header">
				<?php if ( $bbai_lo_show_conversion_modal ) : ?>
				<h2 id="bbai-ftue-trial-title" class="bbai-logged-out__title">
					<?php esc_html_e( 'Start fixing missing alt text for free', 'beepbeep-ai-alt-text-generator' ); ?>
				</h2>
				<?php else : ?>
				<h1 id="bbai-ftue-trial-title" class="bbai-logged-out__title">
					<?php esc_html_e( 'Start fixing missing alt text for free', 'beepbeep-ai-alt-text-generator' ); ?>
				</h1>
				<?php endif; ?>
				<p class="bbai-logged-out__subtitle">
					<?php esc_html_e( 'No account needed. Starts instantly.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
			</header>

			<?php if ( $bbai_lo_trial_block_visible ) : ?>
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

		<?php /* --- Full-page conversion (exhausted trial; no underlay) ----------------------- */ ?>
		<?php if ( $bbai_lo_show_exhausted_section ) : ?>
		<section
			id="bbai-ftue-panel-conversion"
			class="bbai-ftue-panel bbai-ftue-panel--conversion"
			aria-labelledby="bbai-logged-out-title-conversion"
		>
			<header class="bbai-logged-out__header">
				<h1 id="bbai-logged-out-title-conversion" class="bbai-logged-out__title">
					<?php esc_html_e( 'You’ve used all your free generations', 'beepbeep-ai-alt-text-generator' ); ?>
				</h1>
				<p class="bbai-logged-out__subtitle">
					<?php esc_html_e( 'Create a free account to review your results and unlock your full ALT library.', 'beepbeep-ai-alt-text-generator' ); ?>
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
		<?php endif; ?>

	</div><!-- .bbai-logged-out__container -->

	<?php if ( $bbai_lo_show_conversion_modal ) : ?>
	<?php
	$bbai_lo_dismiss_label = _x( 'Close', 'dismiss conversion dialog', 'beepbeep-ai-alt-text-generator' );
	?>
	<div
		id="bbai-ftue-conversion-modal"
		class="bbai-ftue-conversion-modal is-open"
		role="dialog"
		aria-modal="true"
		aria-labelledby="bbai-conversion-dialog-title"
		aria-hidden="false"
	>
		<div class="bbai-ftue-conversion-modal__backdrop" data-bbai-ftue-conversion-dismiss="1" tabindex="-1"></div>
		<div class="bbai-ftue-conversion-modal__panel">
			<button
				type="button"
				class="bbai-ftue-conversion-modal__close"
				data-bbai-ftue-conversion-dismiss="1"
				aria-label="<?php echo esc_attr( $bbai_lo_dismiss_label ); ?>"
			>
				&times;
			</button>
			<header class="bbai-logged-out__header bbai-ftue-conversion-modal__header">
				<h1 id="bbai-conversion-dialog-title" class="bbai-logged-out__title">
					<?php esc_html_e( 'You’ve used all your free generations', 'beepbeep-ai-alt-text-generator' ); ?>
				</h1>
				<p class="bbai-logged-out__subtitle">
					<?php esc_html_e( 'Create a free account to review your results and unlock your full ALT library.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
			</header>
			<div class="bbai-logged-out__actions">
				<a
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
					href="<?php echo esc_url( $bbai_lo_fallback_url ); ?>"
					data-action="show-auth-modal"
					data-auth-tab="login"
					class="bbai-link"
				>
					<?php esc_html_e( 'Already have an account? Log in', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</p>
		</div>
	</div>
	<?php endif; ?>
</div><!-- .bbai-logged-out -->
