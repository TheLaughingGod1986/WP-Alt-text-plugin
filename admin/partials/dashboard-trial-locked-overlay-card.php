<?php
/**
 * Conversion overlay (guest library preview) — static signup CTAs, no "Fix 0" copy.
 *
 * Expects parent scope:
 * - $bbai_locked_preview_overlay_copy
 * - $bbai_locked_preview_context_line (optional string)
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_locked_ctx = isset( $bbai_locked_preview_context_line ) ? (string) $bbai_locked_preview_context_line : '';
$bbai_trial_exhausted = isset( $bbai_trial_exhausted ) ? (bool) $bbai_trial_exhausted : false;
?>
<div
	class="bbai-dashboard-locked-preview__overlay bbai-modal-overlay"
	role="region"
	aria-labelledby="bbai-locked-preview-conversion-title"
>
	<div class="bbai-dashboard-locked-preview__overlay-card bbai-modal-card">
		<h3 id="bbai-locked-preview-conversion-title" class="bbai-dashboard-locked-preview__overlay-title">
			<?php echo esc_html( $bbai_trial_exhausted ? __( 'Finish the last image', 'beepbeep-ai-alt-text-generator' ) : __( 'Unlock your ALT Library', 'beepbeep-ai-alt-text-generator' ) ); ?>
		</h3>
		<?php if ( $bbai_trial_exhausted ) : ?>
			<p class="bbai-dashboard-locked-preview__overlay-subtext"><?php esc_html_e( 'You\'re 1 step away from completing your library', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<?php endif; ?>
		<p class="bbai-dashboard-locked-preview__overlay-copy">
			<?php
			echo esc_html(
				$bbai_trial_exhausted
					? __( 'Complete your library to continue optimising new images.', 'beepbeep-ai-alt-text-generator' )
					: $bbai_locked_preview_overlay_copy
			);
			?>
		</p>
		<?php if ( $bbai_locked_ctx !== '' ) : ?>
			<p class="bbai-dashboard-locked-preview__overlay-context" data-bbai-guest-preview-context>
				<?php echo esc_html( $bbai_locked_ctx ); ?>
			</p>
		<?php endif; ?>

		<div class="bbai-dashboard-locked-preview__actions">
			<button
				type="button"
				class="bbai-btn bbai-btn-primary bbai-dashboard-locked-preview__cta bbai-dashboard-locked-preview__cta--primary bbai-modal-primary"
				data-action="show-auth-modal"
				data-auth-tab="signup"
				data-bbai-modal-context="library"
				data-bbai-analytics-upgrade="library_overlay_create_account"
				data-bbai-trial-complete-cta="<?php echo esc_attr( $bbai_trial_exhausted ? 'unlock_alt_library' : 'create_account' ); ?>"
			>
				<?php echo esc_html( $bbai_trial_exhausted ? __( 'Finish the last image', 'beepbeep-ai-alt-text-generator' ) : __( 'Create free account', 'beepbeep-ai-alt-text-generator' ) ); ?>
			</button>
			<?php if ( $bbai_trial_exhausted ) : ?>
				<p class="bbai-dashboard-locked-preview__subtext bbai-modal-secondary"><?php esc_html_e( 'Create a free account to continue', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<p class="bbai-dashboard-locked-preview__microcopy bbai-modal-meta"><?php esc_html_e( 'Takes less than 10 seconds', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<?php endif; ?>
			<p class="bbai-dashboard-locked-preview__signin bbai-modal-login">
				<a
					href="#"
					class="bbai-dashboard-locked-preview__signin-link"
					data-action="show-auth-modal"
					data-auth-tab="login"
					data-bbai-modal-context="login"
					data-bbai-trial-complete-cta="login"
				>
					<?php esc_html_e( 'Log in', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</p>
		</div>

		<?php if ( ! $bbai_trial_exhausted ) : ?>
			<ul class="bbai-dashboard-locked-preview__benefits bbai-dashboard-locked-preview__benefits--conversion" aria-label="<?php esc_attr_e( 'Unlocked value', 'beepbeep-ai-alt-text-generator' ); ?>">
				<li class="bbai-dashboard-locked-preview__benefit">
					<span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
					<span><?php esc_html_e( 'Unlock your full ALT library', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</li>
				<li class="bbai-dashboard-locked-preview__benefit">
					<span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
					<span><?php esc_html_e( 'Fix all remaining images', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</li>
				<li class="bbai-dashboard-locked-preview__benefit">
					<span class="bbai-dashboard-locked-preview__benefit-icon" aria-hidden="true">✓</span>
					<span>
						<?php
						$bbai_monthly = isset( $bbai_locked_preview_monthly_free ) ? (int) $bbai_locked_preview_monthly_free : 50;
						echo esc_html(
							sprintf(
								/* translators: %d: free monthly generations. */
								__( 'Get %d generations/month', 'beepbeep-ai-alt-text-generator' ),
								$bbai_monthly
							)
						);
						?>
					</span>
				</li>
			</ul>
		<?php endif; ?>
	</div>
</div>
