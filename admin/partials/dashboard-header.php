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
