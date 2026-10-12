<?php
/**
 * nAi dashboard component.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included component.
if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
}
if ( empty( $nai_is_pro ) && isset( $nai_credits_lim ) && (int) $nai_credits_lim >= 50 && class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) ) {
	$nai_component_local_generation_count = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_successful_generations_this_month();
	$nai_component_handled_pass_ids       = get_option( 'bbai_todays_pass_handled_v1', array() );
	if ( is_array( $nai_component_handled_pass_ids ) ) {
		$nai_component_local_generation_count = max( $nai_component_local_generation_count, count( array_filter( array_map( 'absint', $nai_component_handled_pass_ids ) ) ) );
	}
	if ( $nai_component_local_generation_count > (int) $nai_credits_use ) {
		$nai_credits_use = min( (int) $nai_credits_lim, $nai_component_local_generation_count );
		$nai_credits_rem = max( 0, (int) $nai_credits_lim - (int) $nai_credits_use );
		if ( ! empty( $nai_has_daily_cap ) ) {
			$nai_daily_limit = max( 1, (int) ( $nai_daily_limit ?? 5 ) );
			$nai_daily_use   = min( $nai_daily_limit, (int) $nai_credits_use );
			$nai_daily_rem   = max( 0, $nai_daily_limit - $nai_daily_use );
		}
	}
}
?>
	<?php // -------- Footer metrics (shared OpptiAI chrome — matches Titles) -------- ?>
	<div class="nai-footer-metrics">
		<div class="nai-footer-metrics__item">
			<div class="nai-footer-metrics__label"><?php esc_html_e( 'Editing streak', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-footer-metrics__value">
				<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_streak ); ?></span><?php esc_html_e( '-day streak', 'beepbeep-ai-alt-text-generator' ); ?>
			</div>
		</div>
		<?php if ( ! empty( $nai_has_daily_cap ) ) : ?>
		<div class="nai-footer-metrics__item">
			<div class="nai-footer-metrics__label"><?php esc_html_e( 'Daily service allowance', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-footer-metrics__value">
				<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-used><?php echo esc_html( (string) $nai_daily_use ); ?></span>
				<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
				<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-limit><?php echo esc_html( (string) $nai_daily_limit ); ?></span>
				<?php esc_html_e( 'used today', 'beepbeep-ai-alt-text-generator' ); ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="nai-footer-metrics__item">
			<div class="nai-footer-metrics__label"><?php esc_html_e( 'Monthly service usage', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-footer-metrics__value">
				<span class="nai-mono nai-tnum" data-bbai-entitlement-used><?php echo esc_html( (string) $nai_credits_use ); ?></span>
				<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
				<span class="nai-mono nai-tnum" data-bbai-entitlement-limit><?php echo esc_html( (string) $nai_credits_lim ); ?></span>
				<?php esc_html_e( 'used', 'beepbeep-ai-alt-text-generator' ); ?>
			</div>
		</div>
		<div class="nai-footer-metrics__item">
			<div class="nai-footer-metrics__label"><?php esc_html_e( 'Continuous Optimisation', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-footer-metrics__value">
				<?php
				$nai_footer_options = is_array( get_option( 'bbai_options', array() ) ) ? get_option( 'bbai_options', array() ) : array();
				$nai_footer_auto_on = ! empty( $nai_is_pro ) && ! empty( $nai_footer_options['auto_generate'] );
				echo esc_html( $nai_footer_auto_on ? __( 'Enabled', 'beepbeep-ai-alt-text-generator' ) : __( 'Paused', 'beepbeep-ai-alt-text-generator' ) );
				?>
			</div>
		</div>
		<div class="nai-footer-metrics__item">
			<div class="nai-footer-metrics__label"><?php esc_html_e( 'Remaining', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-footer-metrics__value">
				<span class="nai-mono nai-tnum" data-bbai-entitlement-remaining><?php echo esc_html( (string) $nai_credits_rem ); ?></span>
				<?php esc_html_e( 'service credits', 'beepbeep-ai-alt-text-generator' ); ?>
			</div>
		</div>
	</div>
