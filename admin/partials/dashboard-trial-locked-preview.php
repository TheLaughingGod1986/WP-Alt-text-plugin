<?php
/**
 * Secondary benefits strip for exhausted anonymous trial users.
 * (Unlock overlay + blurred real library preview live in dashboard-trial-library-preview.php.)
 *
 * Expected parent scope:
 * - $bbai_has_connected_account
 * - $bbai_state_credits_remaining
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! empty( $bbai_has_connected_account ) ) {
	return;
}

?>
<div
	class="bbai-dashboard-locked-preview-stack"
	data-bbai-trial-locked-preview="1"
>
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
