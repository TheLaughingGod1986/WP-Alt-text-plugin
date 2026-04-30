<?php
/**
 * Unified logged-in dashboard (PHP-first SSR).
 *
 * Expects parent scope from dashboard-body.php:
 *   $bbai_li_state  array  Full output from Logged_In_Dashboard_Resolver::resolve()
 *   $this           Core   Plugin core (for attachment ID helpers)
 *
 * Guest / logged-out flows never load this partial.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_li_state     = is_array( $bbai_li_state ?? null ) ? $bbai_li_state : [];
$bbai_li_state_raw = (string) ( $bbai_li_state['state'] ?? '' );
$bbai_li_initial_json = (string) wp_json_encode( $bbai_li_state );

// ── State-aware banner config from the resolver ──────────────────────────────
// The resolver's build_banner() returns either:
//   • a non-empty array  → merge its copy/tone over the plan banner
//   • an empty array []  → resolver explicitly suppressed the banner (e.g.
//                          PROCESSING running on dashboard — hero owns it)
// In the suppressed case we unset the plan banner so nothing renders.
$bbai_li_banner_cfg     = is_array( $bbai_li_state['banner'] ?? null ) ? $bbai_li_state['banner'] : null;
$bbai_li_banner_is_null = ( $bbai_li_banner_cfg === null );  // resolver didn't set key at all

if ( ! $bbai_li_banner_is_null && empty( $bbai_li_banner_cfg ) ) {
	// Empty array = explicit suppress signal from resolver.
	unset( $bbai_dashboard_command_hero );
} elseif ( ! empty( $bbai_li_banner_cfg ) && isset( $bbai_dashboard_command_hero ) && is_array( $bbai_dashboard_command_hero ) ) {
	// Non-empty = resolver wants to override copy/tone only.
	// Keep the plan banner's CTAs so the dashboard banner looks identical to other pages.
	$bbai_li_copy_keys = [ 'title', 'body', 'tone', 'banner_variant', 'semantic_state', 'heading_tag', 'suppress_host' ];
	foreach ( $bbai_li_copy_keys as $bbai_li_key ) {
		if ( array_key_exists( $bbai_li_key, $bbai_li_banner_cfg ) ) {
			$bbai_dashboard_command_hero[ $bbai_li_key ] = $bbai_li_banner_cfg[ $bbai_li_key ];
		}
	}
}
?>

<section
	class="bbai-logged-in-dashboard bbai-logged-in-dashboard--ssr"
	data-bbai-logged-in-dashboard
	data-bbai-li-ssr="1"
	data-state="<?php echo esc_attr( $bbai_li_state_raw ); ?>"
	data-bbai-li-initial-state="<?php echo esc_attr( $bbai_li_initial_json ); ?>"
	data-bbai-li-state-truth-url="<?php echo esc_url( rest_url( 'bbai/v1/dashboard/state-truth' ) ); ?>"
	data-bbai-counts-hash="<?php echo esc_attr( (string) ( $bbai_dashboard_root_counts_hash ?? '' ) ); ?>"
	aria-label="<?php echo esc_attr__( 'Logged-in dashboard', 'beepbeep-ai-alt-text-generator' ); ?>"
>

	<?php /* Intentionally no top marketing banner on dashboard — copy lives in the hero. */ ?>
	<?php /* Hero grid — primary action surface */ ?>
	<div class="bbai-li-hero-shell">

		<?php
		// ── Hero — mission control: status + single strong action ────────────────
		$bbai_logged_in_hero_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-in-hero.php';
		if ( is_readable( $bbai_logged_in_hero_partial ) ) {
			require $bbai_logged_in_hero_partial;
		}
		?>

	</div><?php /* /.bbai-li-hero-shell */ ?>

	<?php
	// Phase 14 retention/return strip (compact banner below the hero).
	$bbai_retention_strip_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-retention-strip.php';
	if ( isset( $bbai_retention_strip ) && is_readable( $bbai_retention_strip_partial ) ) {
		require $bbai_retention_strip_partial;
	}
	?>

	<?php
	// ── Insight stat cards (display-only; values mirror donut + library totals) ──
	$bbai_li_seg   = is_array( $bbai_li_state['donut']['segments'] ?? null ) ? $bbai_li_state['donut']['segments'] : [];
	$bbai_li_opt   = max( 0, (int) ( $bbai_li_seg['optimized'] ?? 0 ) );
	$bbai_li_miss  = max( 0, (int) ( $bbai_li_seg['missing'] ?? 0 ) );
	$bbai_li_lib_n = max( 0, (int) ( $bbai_li_state['meta']['total_images'] ?? 0 ) );
	$bbai_li_with  = $bbai_li_lib_n > 0 ? max( 0, $bbai_li_lib_n - $bbai_li_miss ) : 0;
	$bbai_li_cov   = $bbai_li_lib_n > 0 ? (int) min( 100, (int) round( ( 100 * $bbai_li_with ) / $bbai_li_lib_n ) ) : 0;
	// Rough UX estimate: ~2 minutes manual ALT work per optimised image.
	$bbai_li_mins  = $bbai_li_opt * 2;
	?>
	<section
		class="bbai-li-insights"
		aria-label="<?php echo esc_attr__( 'Library insights', 'beepbeep-ai-alt-text-generator' ); ?>"
	>
		<article class="bbai-li-insight-card bbai-stat-card primary">
			<div class="bbai-stat-card-top">
			<h3 class="bbai-li-insight-card__title"><?php esc_html_e( 'Accessibility', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			<?php if ( 100 === $bbai_li_cov && $bbai_li_lib_n > 0 ) : ?>
				<span class="bbai-li-insight-pill bbai-li-insight-pill--success"><?php esc_html_e( 'All images optimised 🎉', 'beepbeep-ai-alt-text-generator' ); ?></span>
			<?php endif; ?>
			<p
				class="bbai-li-insight-card__value"
				data-bbai-li-insight-coverage
			><?php
			echo esc_html( sprintf(
				/* translators: 1: percentage (0-100) */
				__( '%1$s%% of images accessible', 'beepbeep-ai-alt-text-generator' ),
				(string) $bbai_li_cov
			) );
			?></p>
			</div>
			<div class="bbai-stat-card-bottom">
			<p class="bbai-li-insight-card__desc"><?php
			echo ( 100 === $bbai_li_cov && $bbai_li_lib_n > 0 )
				? esc_html__( 'All images have ALT text.', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Accessibility coverage improving.', 'beepbeep-ai-alt-text-generator' );
			?></p>
			<p class="bbai-li-insight-card__support"><?php esc_html_e( 'Keep this at 100% as you upload', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a
				href="#"
				class="bbai-li-insight-card__cta"
				data-action="show-upgrade-modal"
			><?php esc_html_e( 'Enable auto ALT text', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
		</article>
		<article class="bbai-li-insight-card bbai-stat-card secondary">
			<div class="bbai-stat-card-top">
			<h3 class="bbai-li-insight-card__title"><?php esc_html_e( 'Time saved', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			<p
				class="bbai-li-insight-card__value"
				data-bbai-li-insight-mins
			><?php
			if ( $bbai_li_mins > 0 ) {
				printf(
					/* translators: %s: estimated minutes (integer, formatted) */
					esc_html( _n( '%s min saved', '%s mins saved', $bbai_li_mins, 'beepbeep-ai-alt-text-generator' ) ),
					esc_html( number_format_i18n( $bbai_li_mins ) )
				);
			} else {
				esc_html_e( '~0 mins saved', 'beepbeep-ai-alt-text-generator' );
			}
			?></p>
			</div>
			<div class="bbai-stat-card-bottom">
			<p class="bbai-li-insight-card__desc"><?php
			echo $bbai_li_mins > 0
				? esc_html__( 'No manual writing needed.', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Generate ALT in bulk to see time saved here.', 'beepbeep-ai-alt-text-generator' );
			?></p>
			<p class="bbai-li-insight-card__support"><?php esc_html_e( 'Keep saving time automatically', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a
				href="#"
				class="bbai-li-insight-card__cta"
				data-action="show-upgrade-modal"
			><?php esc_html_e( 'Enable auto optimisation', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
		</article>
		<article class="bbai-li-insight-card bbai-stat-card tertiary">
			<div class="bbai-stat-card-top">
			<h3 class="bbai-li-insight-card__title"><?php esc_html_e( 'SEO', 'beepbeep-ai-alt-text-generator' ); ?></h3>
			<p
				class="bbai-li-insight-card__value"
				data-bbai-li-insight-optimized
			><?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of images (formatted) */
					_n( '%s image optimised', '%s images optimised', $bbai_li_opt, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_li_opt )
				)
			);
			?></p>
			</div>
			<div class="bbai-stat-card-bottom">
			<p class="bbai-li-insight-card__desc"><?php
			echo $bbai_li_opt > 0
				? esc_html__( 'Helping search engines understand your content.', 'beepbeep-ai-alt-text-generator' )
				: esc_html__( 'Generate ALT text to start improving rankings.', 'beepbeep-ai-alt-text-generator' );
			?></p>
			<p class="bbai-li-insight-card__support"><?php esc_html_e( 'New uploads won’t be optimised automatically', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a
				href="#"
				class="bbai-li-insight-card__cta"
				data-action="show-upgrade-modal"
			><?php esc_html_e( 'Automate future images', 'beepbeep-ai-alt-text-generator' ); ?></a>
			</div>
		</article>
	</section>

</section>
