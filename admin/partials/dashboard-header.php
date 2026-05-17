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

$bbai_current_user = wp_get_current_user();
$bbai_display_name = ( $bbai_current_user instanceof WP_User && '' !== $bbai_current_user->display_name )
	? $bbai_current_user->display_name
	: __( 'there', 'beepbeep-ai-alt-text-generator' );
?>
<?php /* Scoped fallback styles: ensures flex layout and pill render even when external CSS is delayed or blocked. */ ?>
<style id="bbai-dashboard-header-critical">
.bbai-dashboard-header{display:flex!important;flex-direction:row!important;align-items:center!important;justify-content:space-between!important;gap:20px!important;padding-bottom:20px!important;width:100%}
.bbai-dashboard-header__left{display:flex!important;flex-direction:column!important;gap:3px!important;min-width:0!important}
.bbai-dashboard-header__right{flex-shrink:0!important}
.bbai-dashboard-header__heading{font-size:26px!important;font-weight:700!important;color:#0f172a!important;margin:0!important;padding:0!important;border:none!important;background:none!important;box-shadow:none!important;white-space:nowrap!important;line-height:1.2!important}
.bbai-dashboard-header__subtitle{font-size:14px!important;font-weight:400!important;color:#64748b!important;margin:0!important;padding:0!important}
.bbai-dashboard-header__status-pill{display:inline-flex!important;align-items:center!important;gap:8px!important;height:38px!important;padding:0 16px!important;border-radius:20px!important;border:1.5px solid #16a34a!important;background:#f0fdf4!important;white-space:nowrap!important}
.bbai-dashboard-header__status-dot{display:block!important;width:8px!important;height:8px!important;border-radius:50%!important;background:#22c55e!important;flex-shrink:0!important}
.bbai-dashboard-header__status-text{font-size:13px!important;font-weight:600!important;color:#15803d!important;line-height:1!important}
@media(max-width:782px){.bbai-dashboard-header{flex-direction:column!important;align-items:flex-start!important;gap:12px!important}.bbai-dashboard-header__heading{font-size:22px!important;white-space:normal!important}}
</style>

<header class="bbai-dashboard-header" aria-label="<?php echo esc_attr__( 'Dashboard overview', 'beepbeep-ai-alt-text-generator' ); ?>">

	<div class="bbai-dashboard-header__left">
		<h1 class="bbai-dashboard-header__heading">
			<?php
			printf(
				/* translators: %s: WordPress display name of the current user. */
				esc_html__( 'Welcome back, %s 👋', 'beepbeep-ai-alt-text-generator' ),
				esc_html( $bbai_display_name )
			);
			?>
		</h1>
		<p class="bbai-dashboard-header__subtitle">
			<?php esc_html_e( "Here's how your image library is performing.", 'beepbeep-ai-alt-text-generator' ); ?>
		</p>
	</div>

	<div class="bbai-dashboard-header__right">
		<div class="bbai-dashboard-header__status-pill" role="status" aria-live="polite">
			<span class="bbai-dashboard-header__status-dot" aria-hidden="true"></span>
			<span class="bbai-dashboard-header__status-text">
				<?php esc_html_e( 'All systems operational', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>
		</div>
	</div>

</header>
