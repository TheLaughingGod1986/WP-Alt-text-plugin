<?php
/**
 * Shared exhausted-trial upgrade wall shown inside the real dashboard/library.
 *
 * Expects:
 * - $bbai_product_state_model (array)
 * - $bbai_trial_upgrade_context (string, optional)
 * - $bbai_trial_upgrade_missing_count (int, optional)
 * - $bbai_trial_upgrade_weak_count (int, optional)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_product_state_model = isset( $bbai_product_state_model ) && is_array( $bbai_product_state_model )
	? $bbai_product_state_model
	: [];

if ( empty( $bbai_product_state_model['flags']['show_exhausted_upgrade_wall'] ) ) {
	return;
}

$bbai_trial_upgrade_context = isset( $bbai_trial_upgrade_context ) ? sanitize_key( (string) $bbai_trial_upgrade_context ) : 'dashboard';
$bbai_trial_copy            = isset( $bbai_product_state_model['copy'] ) && is_array( $bbai_product_state_model['copy'] )
	? $bbai_product_state_model['copy']
	: [];
$bbai_trial_snapshot        = isset( $bbai_product_state_model['trial'] ) && is_array( $bbai_product_state_model['trial'] )
	? $bbai_product_state_model['trial']
	: [];
$bbai_trial_missing_count   = isset( $bbai_trial_upgrade_missing_count ) ? max( 0, (int) $bbai_trial_upgrade_missing_count ) : 0;
$bbai_trial_weak_count      = isset( $bbai_trial_upgrade_weak_count ) ? max( 0, (int) $bbai_trial_upgrade_weak_count ) : 0;
$bbai_trial_remaining_work  = $bbai_trial_missing_count + $bbai_trial_weak_count;

$bbai_trial_metrics = [];
if ( $bbai_trial_remaining_work > 0 ) {
	$bbai_trial_metrics[] = sprintf(
		_n( '%s image still needs attention', '%s images still need attention', $bbai_trial_remaining_work, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_trial_remaining_work )
	);
}
if ( $bbai_trial_missing_count > 0 ) {
	$bbai_trial_metrics[] = sprintf(
		_n( '%s image still missing ALT text', '%s images still missing ALT text', $bbai_trial_missing_count, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_trial_missing_count )
	);
}
if ( $bbai_trial_weak_count > 0 ) {
	$bbai_trial_metrics[] = sprintf(
		_n( '%s image ready for review', '%s images ready for review', $bbai_trial_weak_count, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_trial_weak_count )
	);
}
?>
<section
	class="bbai-dashboard-card bbai-command-card bbai-trial-upgrade-wall"
	data-bbai-trial-upgrade-wall="1"
	data-bbai-trial-upgrade-context="<?php echo esc_attr( $bbai_trial_upgrade_context ); ?>"
>
	<div class="bbai-trial-upgrade-wall__main">
		<div class="bbai-trial-upgrade-wall__copy">
			<p class="bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php echo esc_html( $bbai_trial_copy['exhausted_eyebrow'] ?? __( 'Free trial complete', 'beepbeep-ai-alt-text-generator' ) ); ?></p>
			<h2 class="bbai-command-card__title bbai-section-title bbai-card-title"><?php echo esc_html( $bbai_trial_copy['exhausted_title'] ?? '' ); ?></h2>
			<p class="bbai-trial-upgrade-wall__body"><?php echo esc_html( $bbai_trial_copy['exhausted_body'] ?? '' ); ?></p>
			<p class="bbai-trial-upgrade-wall__value"><?php echo esc_html( $bbai_trial_copy['value_prop'] ?? '' ); ?></p>
			<div class="bbai-trial-upgrade-wall__pills" role="list" aria-label="<?php esc_attr_e( 'Upgrade reasons', 'beepbeep-ai-alt-text-generator' ); ?>">
				<span class="bbai-trial-upgrade-wall__pill" role="listitem"><?php echo esc_html( $bbai_trial_copy['helper_copy'] ?? '' ); ?></span>
				<span class="bbai-trial-upgrade-wall__pill" role="listitem"><?php esc_html_e( 'No credit card required', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="bbai-trial-upgrade-wall__pill" role="listitem"><?php esc_html_e( 'Keep current results visible', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<p class="bbai-trial-upgrade-wall__trust"><?php echo esc_html( $bbai_trial_copy['trust_copy'] ?? '' ); ?></p>
		</div>

		<div class="bbai-trial-upgrade-wall__actions">
			<a
				href="#"
				class="bbai-command-action bbai-command-action--primary bbai-btn bbai-btn-primary"
				data-action="show-auth-modal"
				data-auth-tab="register"
				data-bbai-trial-wall-primary="1"
			><?php echo esc_html( $bbai_trial_copy['primary_cta'] ?? __( 'Create free account', 'beepbeep-ai-alt-text-generator' ) ); ?></a>
			<a
				href="#"
				class="bbai-command-action bbai-command-action--secondary bbai-btn bbai-btn-secondary"
				data-action="show-upgrade-modal"
				data-bbai-trial-wall-secondary="1"
			><?php echo esc_html( $bbai_trial_copy['secondary_cta'] ?? __( 'View plans', 'beepbeep-ai-alt-text-generator' ) ); ?></a>
		</div>
	</div>

	<div class="bbai-trial-upgrade-wall__meta">
		<strong class="bbai-trial-upgrade-wall__usage"><?php echo esc_html( $bbai_trial_copy['usage_line'] ?? '' ); ?></strong>
		<?php if ( ! empty( $bbai_trial_metrics ) ) : ?>
			<p class="bbai-trial-upgrade-wall__summary"><?php echo esc_html( implode( ' • ', $bbai_trial_metrics ) ); ?></p>
		<?php endif; ?>
	</div>
</section>
