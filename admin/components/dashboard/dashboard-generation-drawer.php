<?php
/**
 * nAi dashboard generation drawer.
 *
 * @package BeepBeepAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nai-drawer" hidden data-nai-drawer aria-hidden="true">
	<div class="nai-drawer__backdrop" data-nai-close-drawer></div>
	<aside class="nai-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="nai-drawer-title">
		<header class="nai-drawer__head">
			<div>
				<div class="nai-drawer__eyebrow" data-nai-drawer-eyebrow><?php echo esc_html( $nai_is_pro ? __( 'Optimisation', 'beepbeep-ai-alt-text-generator' ) : __( "Today's pass", 'beepbeep-ai-alt-text-generator' ) ); ?></div>
				<h2 id="nai-drawer-title" data-nai-drawer-title><?php esc_html_e( 'Preparing images...', 'beepbeep-ai-alt-text-generator' ); ?></h2>
			</div>
			<button class="nai-icon-btn" type="button" data-nai-close-drawer aria-label="<?php esc_attr_e( 'Close', 'beepbeep-ai-alt-text-generator' ); ?>"><?php echo $nai_icon( 'x', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</header>
		<div class="nai-drawer__progress"><div class="nai-progress"><div class="nai-progress__bar" style="width:0%;" data-nai-drawer-progress></div></div></div>
		<div class="nai-drawer__stream" aria-live="polite" data-nai-drawer-stream></div>
		<footer class="nai-drawer__foot">
			<span data-nai-drawer-status><?php esc_html_e( 'Optimising your latest uploads...', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<button class="nai-btn nai-btn--primary nai-btn--sm" type="button" data-nai-complete-drawer hidden><?php esc_html_e( 'Done', 'beepbeep-ai-alt-text-generator' ); ?> <?php echo $nai_icon( 'arrow-right', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
		</footer>
	</aside>
</div>
