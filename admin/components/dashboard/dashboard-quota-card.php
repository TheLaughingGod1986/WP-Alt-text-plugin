<?php
/**
 * nAi dashboard component.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nai-card nai-entitlement-notice" role="status" data-bbai-entitlement-exhausted aria-hidden="<?php echo esc_attr( $nai_pass_blocked ? 'false' : 'true' ); ?>" <?php echo $nai_pass_blocked ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php $nai_notice_monthly = ( $nai_credits_rem <= 0 || empty( $nai_has_daily_cap ) ); ?>
		<div class="nai-eyebrow" data-bbai-entitlement-notice-title><?php echo esc_html( $nai_notice_monthly ? __( 'Monthly allowance used', 'beepbeep-ai-alt-text-generator' ) : __( "Today's allowance used", 'beepbeep-ai-alt-text-generator' ) ); ?></div>
		<p data-bbai-entitlement-notice-copy><?php echo esc_html( $nai_notice_monthly ? __( 'You have 0 generation credits remaining. Review stays available, or upgrade to continue generating.', 'beepbeep-ai-alt-text-generator' ) : __( "You've used today's free generations. Review stays available, or upgrade to continue generating before the next refresh.", 'beepbeep-ai-alt-text-generator' ) ); ?></p>
	</div>
