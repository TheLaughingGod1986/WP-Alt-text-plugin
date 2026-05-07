<?php
/**
 * Dashboard page header — premium SaaS-style greeting row.
 *
 * Renders above the hero cards: a left-side heading + subtitle and a right-side
 * "All systems operational" status pill.  Purely presentational; no state logic.
 *
 * @package BeepBeep_AI
 * @since   5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_current_user    = wp_get_current_user();
$bbai_display_name    = ( $bbai_current_user instanceof WP_User && '' !== $bbai_current_user->display_name )
	? $bbai_current_user->display_name
	: __( 'there', 'beepbeep-ai-alt-text-generator' );
?>

<header class="bbai-dashboard-header" aria-label="<?php echo esc_attr__( 'Dashboard overview', 'beepbeep-ai-alt-text-generator' ); ?>" style="display:flex;flex-direction:row;align-items:center;justify-content:space-between;gap:20px;padding-bottom:20px;width:100%;">

	<div class="bbai-dashboard-header__left" style="display:flex;flex-direction:column;gap:3px;min-width:0;">
		<h1 class="bbai-dashboard-header__heading" style="margin:0;padding:0;font-size:26px;font-weight:700;color:#0f172a;border:none;background:none;box-shadow:none;white-space:nowrap;line-height:1.2;">
			<?php
			printf(
				/* translators: %s: WordPress display name of the current user. */
				esc_html__( 'Welcome back, %s 👋', 'beepbeep-ai-alt-text-generator' ),
				esc_html( $bbai_display_name )
			);
			?>
		</h1>
		<p class="bbai-dashboard-header__subtitle" style="margin:0;padding:0;font-size:14px;color:#64748b;">
			<?php esc_html_e( "Here's how your image library is performing.", 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	</div>

	<div class="bbai-dashboard-header__right" style="flex-shrink:0;">
		<div class="bbai-dashboard-header__status-pill" role="status" aria-live="polite" style="display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 16px;border-radius:20px;border:1.5px solid #16a34a;background:#f0fdf4;white-space:nowrap;">
			<span class="bbai-dashboard-header__status-dot" aria-hidden="true" style="display:block;width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>
			<span class="bbai-dashboard-header__status-text" style="font-size:13px;font-weight:600;color:#15803d;line-height:1;">
				<?php esc_html_e( 'All systems operational', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>
		</div>
	</div>

</header>
