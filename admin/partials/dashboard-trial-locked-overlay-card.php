<?php
/**
 * Conversion overlay (guest library preview) — static signup CTAs, no "Fix 0" copy.
 *
 * Expects parent scope:
 * - $bbai_locked_preview_overlay_copy
 * - $bbai_locked_preview_trust_line
 * - $bbai_locked_preview_context_line (optional string)
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_locked_ctx = isset( $bbai_locked_preview_context_line ) ? (string) $bbai_locked_preview_context_line : '';
?>
<div
	class="bbai-dashboard-locked-preview__overlay"
	role="region"
	aria-labelledby="bbai-locked-preview-conversion-title"
>
	<div class="bbai-dashboard-locked-preview__overlay-card">
		<h3 id="bbai-locked-preview-conversion-title" class="bbai-dashboard-locked-preview__overlay-title">
			<?php esc_html_e( 'Unlock your full ALT library', 'beepbeep-ai-alt-text-generator' ); ?>
		</h3>
		<p class="bbai-dashboard-locked-preview__overlay-copy">
			<?php echo esc_html( $bbai_locked_preview_overlay_copy ); ?>
		</p>
		<?php if ( $bbai_locked_ctx !== '' ) : ?>
			<p class="bbai-dashboard-locked-preview__overlay-context" data-bbai-guest-preview-context>
				<?php echo esc_html( $bbai_locked_ctx ); ?>
			</p>
		<?php endif; ?>

		<div class="bbai-dashboard-locked-preview__actions">
			<button
				type="button"
				class="bbai-btn bbai-btn-primary bbai-dashboard-locked-preview__cta bbai-dashboard-locked-preview__cta--primary"
				data-action="show-dashboard-auth"
				data-auth-tab="register"
				data-bbai-modal-context="library"
				data-bbai-analytics-upgrade="library_overlay_create_account"
			>
				<?php esc_html_e( 'Create free account', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
			<p class="bbai-dashboard-locked-preview__signin">
				<?php esc_html_e( 'Already have an account?', 'beepbeep-ai-alt-text-generator' ); ?>
				<a
					href="#"
					class="bbai-dashboard-locked-preview__signin-link"
					data-action="show-dashboard-auth"
					data-auth-tab="login"
					data-bbai-modal-context="login"
				>
					<?php esc_html_e( 'Sign in', 'beepbeep-ai-alt-text-generator' ); ?>
				</a>
			</p>
			<p class="bbai-dashboard-locked-preview__trust">
				<?php echo esc_html( $bbai_locked_preview_trust_line ); ?>
			</p>
		</div>

		<ul class="bbai-dashboard-locked-preview__benefits bbai-dashboard-locked-preview__benefits--conversion" aria-label="<?php esc_attr_e( 'Unlocked value', 'beepbeep-ai-alt-text-generator' ); ?>">
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
