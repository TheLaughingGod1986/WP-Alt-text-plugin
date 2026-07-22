<?php
/**
 * Reciprocal OpptiAI Titles promotion for the Alt Text dashboard.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

$nai_titles_basename = 'beepbeep-titles/beepbeep-titles.php';
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( is_plugin_active( $nai_titles_basename ) ) {
	$nai_titles_label = __( 'Open Titles', 'beepbeep-ai-alt-text-generator' );
	$nai_titles_url   = admin_url( 'admin.php?page=beepbeep-titles' );
} elseif ( file_exists( WP_PLUGIN_DIR . '/' . $nai_titles_basename ) ) {
	$nai_titles_label = __( 'Activate Titles', 'beepbeep-ai-alt-text-generator' );
	$nai_titles_url   = add_query_arg(
		array( 'plugin_status' => 'inactive', 's' => 'OpptiAI Titles' ),
		admin_url( 'plugins.php' )
	);
} else {
	$nai_titles_label = __( 'Add Titles', 'beepbeep-ai-alt-text-generator' );
	$nai_titles_url   = add_query_arg(
		array( 'tab' => 'search', 's' => 'OpptiAI Titles' ),
		admin_url( 'plugin-install.php' )
	);
}
?>
<div class="nai-companion-card">
	<div class="nai-companion-card__icon">
		<?php echo $nai_icon( 'edit', 19, 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG helper. ?>
	</div>
	<div class="nai-companion-card__content">
		<div class="nai-companion-card__heading">
			<span><?php esc_html_e( 'Your credits also work on Titles & Meta Descriptions', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<span class="nai-chip nai-companion-card__badge"><?php esc_html_e( 'From OpptiAI', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<div class="nai-companion-card__copy">
			<?php esc_html_e( 'The same shared credit pool powers OpptiAI Titles — generate SEO-friendly titles and meta descriptions for pages, posts, and products. No extra subscription.', 'beepbeep-ai-alt-text-generator' ); ?>
		</div>
	</div>
	<a class="nai-btn nai-btn--secondary nai-btn--md nai-companion-card__action" href="<?php echo esc_url( $nai_titles_url ); ?>">
		<?php echo $nai_icon( 'external', 15, 1.9 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG helper. ?>
		<?php echo esc_html( $nai_titles_label ); ?>
	</a>
</div>
