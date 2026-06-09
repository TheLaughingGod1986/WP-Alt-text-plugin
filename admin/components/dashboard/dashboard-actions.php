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
		$nai_daily_limit = max( 1, (int) ( $nai_daily_limit ?? 5 ) );
		$nai_daily_use   = min( $nai_daily_limit, (int) $nai_credits_use );
		$nai_daily_rem   = max( 0, $nai_daily_limit - $nai_daily_use );
	}
}
?>
	<?php // -------- Footer metrics -------- ?>
	<div class="nai-footer-metrics">
		<?php if ( $nai_is_pro ) : ?>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Editing streak', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_streak ); ?></span><?php esc_html_e( '-day streak', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">· <?php esc_html_e( '12 of last 14', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Improvements this week', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( number_format_i18n( $nai_daily_use > 0 ? $nai_daily_use : min( $nai_credits_use, 18 ) ) ); ?></span>
					<?php esc_html_e( 'images', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">· <?php esc_html_e( '+18% vs last week', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-pulse-dot" style="width:6px;height:6px;"></span>
					<?php esc_html_e( 'Active', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">·
						<?php esc_html_e( 'monitoring new uploads', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
			</div>
		<?php else : ?>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Editing streak', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_streak ); ?></span><?php esc_html_e( '-day streak', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">· <?php esc_html_e( '12 of last 14', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Daily allowance', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<div class="nai-footer-metrics__value">
						<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-used><?php echo esc_html( (string) $nai_daily_use ); ?></span>
						<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-limit><?php echo esc_html( (string) $nai_daily_limit ); ?></span>
						<?php if ( $nai_daily_hours_left > 0 ) : ?>
							<?php
							$nai_daily_reset_label = $nai_daily_rem <= 0
								/* translators: %d: hours until the next daily pass. */
								? sprintf( __( 'next pass in %dh', 'beepbeep-ai-alt-text-generator' ), $nai_daily_hours_left )
								/* translators: %d: hours until the daily allowance refreshes. */
								: sprintf( __( 'refreshes in %dh', 'beepbeep-ai-alt-text-generator' ), $nai_daily_hours_left );
							?>
							<span class="nai-footer-metrics__muted">· <span data-bbai-entitlement-daily-reset><?php echo esc_html( $nai_daily_reset_label ); ?></span></span>
						<?php endif; ?>
					</div>
				</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Monthly usage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum" data-bbai-entitlement-used><?php echo esc_html( (string) $nai_credits_use ); ?></span>
					<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-mono nai-tnum" data-bbai-entitlement-limit><?php echo esc_html( (string) $nai_credits_lim ); ?></span>
					<?php if ( $nai_days_reset > 0 ) : ?>
						<span class="nai-footer-metrics__muted">·
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: days until quota resets. */
									_n( 'resets in %d day', 'resets in %d days', $nai_days_reset, 'beepbeep-ai-alt-text-generator' ),
									$nai_days_reset
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
