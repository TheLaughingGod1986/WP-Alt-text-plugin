<?php
/**
 * Quiet OpptiAI Titles cross-sell for the home Dashboard tab.
 *
 * Shown only to signed-in Free, Starter, or Growth (billing id `pro`) users.
 * Guests and Agency (and any other plan) never see this line.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

$bbai_titles_xsell_connected = ! empty( $bbai_has_connected_account )
	&& empty( $bbai_is_anonymous_trial )
	&& empty( $bbai_is_guest_trial );

if ( ! $bbai_titles_xsell_connected ) {
	return;
}

if ( ! class_exists( Plan_Helpers::class ) ) {
	$bbai_plan_helpers_path = BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';
	if ( is_readable( $bbai_plan_helpers_path ) ) {
		require_once $bbai_plan_helpers_path;
	}
}

$bbai_titles_xsell_plan = class_exists( Plan_Helpers::class )
	? strtolower( (string) Plan_Helpers::get_plan_slug() )
	: 'free';

// Billing id `pro` is Growth. Agency / enterprise / unknown plans are excluded.
$bbai_titles_xsell_eligible = in_array(
	$bbai_titles_xsell_plan,
	[ 'free', 'starter', 'growth', 'pro' ],
	true
);

if ( ! $bbai_titles_xsell_eligible ) {
	return;
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// WP.org folder is opptiai-titles; bootstrap file verified as beepbeep-titles.php.
$bbai_titles_plugin_file = 'opptiai-titles/beepbeep-titles.php';
$bbai_titles_is_active   = function_exists( 'is_plugin_active' ) && is_plugin_active( $bbai_titles_plugin_file );

// "Done" state: every image has ALT text and at least one was fixed. Point to the
// next free audit (Titles) as a card, but only while Titles is not active.
$bbai_titles_xsell_state = isset( $bbai_dashboard_state ) && is_array( $bbai_dashboard_state ) ? $bbai_dashboard_state : array();
$bbai_titles_xsell_done  = ! empty( $bbai_titles_xsell_state )
	&& 0 === max( 0, (int) ( $bbai_titles_xsell_state['missingCount'] ?? 1 ) )
	&& (
		max( 0, (int) ( $bbai_titles_xsell_state['optimizedCount'] ?? 0 ) ) > 0
		|| ! empty( $bbai_titles_xsell_state['hasEverGeneratedAlt'] )
	);

if ( $bbai_titles_xsell_done && ! $bbai_titles_is_active ) {
	?>
	<section class="bbai-next-audit-card bbai-daily-card" data-bbai-next-audit="opptiai-titles" aria-labelledby="bbai-next-audit-title">
		<p class="bbai-daily-eyebrow"><?php esc_html_e( 'Next free audit', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<h3 id="bbai-next-audit-title"><?php esc_html_e( 'Your images are done. Check your titles and meta next.', 'beepbeep-ai-alt-text-generator' ); ?></h3>
		<p><?php esc_html_e( 'OpptiAI Titles scans every page title and meta description for free and shows what to fix first.', 'beepbeep-ai-alt-text-generator' ); ?></p>
		<a
			class="bbai-daily-btn bbai-daily-btn--secondary"
			href="<?php echo esc_url( 'https://wordpress.org/plugins/opptiai-titles/' ); ?>"
			target="_blank"
			rel="noopener noreferrer"
		><?php esc_html_e( 'Get the free Titles audit', 'beepbeep-ai-alt-text-generator' ); ?></a>
	</section>
	<?php
	return;
}

if ( $bbai_titles_is_active ) {
	$bbai_titles_xsell_copy = __( 'Your OpptiAI credits also write titles and meta.', 'beepbeep-ai-alt-text-generator' );
	$bbai_titles_xsell_cta  = __( 'Open Titles', 'beepbeep-ai-alt-text-generator' );
	// Titles registers its top-level admin page with BEEPTI_SLUG (`beepbeep-titles`).
	$bbai_titles_xsell_href = admin_url( 'admin.php?page=beepbeep-titles' );
	$bbai_titles_xsell_external = false;
} else {
	$bbai_titles_xsell_copy = __( 'Your OpptiAI credits also write titles and meta. Scan for free, then generate into Yoast, Rank Math, or AIOSEO.', 'beepbeep-ai-alt-text-generator' );
	$bbai_titles_xsell_cta  = __( 'Get OpptiAI Titles', 'beepbeep-ai-alt-text-generator' );
	$bbai_titles_xsell_href = 'https://wordpress.org/plugins/opptiai-titles/';
	$bbai_titles_xsell_external = true;
}
?>
<p class="bbai-titles-cross-sell" data-bbai-titles-cross-sell="1" role="note">
	<span class="bbai-titles-cross-sell__text"><?php echo esc_html( $bbai_titles_xsell_copy ); ?></span>
	<a
		class="bbai-titles-cross-sell__cta"
		href="<?php echo esc_url( $bbai_titles_xsell_href ); ?>"
		<?php if ( $bbai_titles_xsell_external ) : ?>
			target="_blank"
			rel="noopener noreferrer"
		<?php endif; ?>
	><?php echo esc_html( $bbai_titles_xsell_cta ); ?></a>
</p>
