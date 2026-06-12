<?php
/**
 * nAi Dashboard — Linear/Vercel-inspired Home view.
 *
 * Consumes the $bbai_dashboard_state array prepared by dashboard-body.php.
 * Renders the connected/authenticated dashboard. Guest funnel is unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included partial.

$nai_state = isset( $bbai_dashboard_state ) && is_array( $bbai_dashboard_state ) ? $bbai_dashboard_state : array();

$nai_total       = max( 0, (int) ( $nai_state['totalImages'] ?? 0 ) );
$nai_optimized   = max( 0, (int) ( $nai_state['optimizedCount'] ?? 0 ) );
$nai_missing     = max( 0, (int) ( $nai_state['missingCount'] ?? 0 ) );
$nai_weak        = max( 0, (int) ( $nai_state['weakCount'] ?? 0 ) );
$nai_coverage    = max( 0, min( 100, (int) ( $nai_state['coveragePercent'] ?? 0 ) ) );
$nai_plan_slug   = sanitize_key( (string) ( $nai_state['planSlug'] ?? $nai_state['plan_slug'] ?? $nai_state['plan'] ?? $nai_state['plan_type'] ?? 'free' ) );
if ( 'pro' === $nai_plan_slug ) {
	$nai_plan_slug = 'growth';
}
$nai_is_trial = ! empty( $nai_state['isTrial'] ) || ! empty( $nai_state['is_trial'] ) || ! empty( $nai_state['trial'] );
$nai_credits_use = max( 0, (int) ( $nai_state['creditsUsed'] ?? 0 ) );
$nai_credits_lim = max( 1, (int) ( $nai_state['creditsLimit'] ?? 1 ) );
$nai_credits_rem = max( 0, (int) ( $nai_state['creditsRemaining'] ?? 0 ) );
if ( isset( $bbai_dashboard_root_credits_used, $bbai_dashboard_root_credits_total, $bbai_dashboard_root_credits_left ) ) {
	$nai_credits_use = max( 0, (int) $bbai_dashboard_root_credits_used );
	$nai_credits_lim = max( 1, (int) $bbai_dashboard_root_credits_total );
	$nai_credits_rem = max( 0, (int) $bbai_dashboard_root_credits_left );
}
if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
	require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
}
if ( 'starter' === $nai_plan_slug && $nai_credits_lim > 0 && $nai_credits_lim < 100 ) {
	$nai_plan_slug = 'free';
}
if ( in_array( $nai_plan_slug, array( 'growth', 'agency', 'enterprise' ), true ) && $nai_credits_lim > 0 && $nai_credits_lim < 1000 ) {
	$nai_plan_slug = 'free';
}
$nai_can_autopilot = isset( $nai_state['canAutopilot'] ) || isset( $nai_state['can_autopilot'] )
	? ( ! empty( $nai_state['canAutopilot'] ) || ! empty( $nai_state['can_autopilot'] ) )
	: in_array( $nai_plan_slug, array( 'growth', 'agency', 'enterprise' ), true );
$nai_is_pro = $nai_can_autopilot;
if ( ! $nai_is_trial && ! $nai_is_pro && $nai_credits_lim >= 50 && class_exists( '\BeepBeepAI\AltTextGenerator\Usage_Tracker' ) ) {
	$nai_local_generation_count = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_local_successful_generations_this_month();
	if ( $nai_local_generation_count > $nai_credits_use ) {
		$nai_credits_use = min( $nai_credits_lim, $nai_local_generation_count );
		$nai_credits_rem = max( 0, $nai_credits_lim - $nai_credits_use );
	}
}
$nai_days_reset  = max( 0, (int) ( $nai_state['daysUntilReset'] ?? 0 ) );
$nai_daily_limit_seed = (int) ( $nai_state['dailyCreditsLimit'] ?? 0 );
$nai_daily_limit      = $nai_daily_limit_seed > 0 ? $nai_daily_limit_seed : ( $nai_is_trial ? 5 : max( 1, $nai_credits_lim ) );
$nai_daily_use        = max( 0, min( $nai_daily_limit, (int) ( $nai_state['dailyCreditsUsed'] ?? 0 ) ) );
$nai_daily_rem        = $nai_daily_limit_seed > 0 && isset( $nai_state['dailyCreditsRemaining'] ) && (int) $nai_state['dailyCreditsRemaining'] >= 0
	? min( $nai_daily_limit, max( 0, (int) $nai_state['dailyCreditsRemaining'] ) )
	: max( 0, $nai_daily_limit - $nai_daily_use );
if ( $nai_is_trial && $nai_credits_lim >= 5 ) {
	$nai_daily_limit = max( 1, $nai_daily_limit );
	$nai_daily_use   = min( $nai_daily_limit, $nai_credits_use );
	$nai_daily_rem   = max( 0, $nai_daily_limit - $nai_daily_use );
}
$nai_daily_reset      = (string) ( $nai_state['dailyResetDate'] ?? '' );
$nai_daily_reset_ts       = '' !== $nai_daily_reset ? strtotime( $nai_daily_reset ) : false;
$nai_daily_hours_left     = false !== $nai_daily_reset_ts ? max( 1, (int) ceil( max( 0, $nai_daily_reset_ts - time() ) / HOUR_IN_SECONDS ) ) : 0;
$nai_streak               = max( 0, (int) ( $nai_state['editingStreak'] ?? 4 ) );
$nai_unslash_request_val  = static function ( $value ) {
	if ( function_exists( 'wp_unslash' ) ) {
		return wp_unslash( $value );
	}

	if ( is_array( $value ) ) {
		return array_map(
			static function ( $item ) {
				return is_string( $item ) ? stripslashes( $item ) : $item;
			},
			$value
		);
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
};
$nai_sanitize_url_raw = static function ( $value ) {
	$value = (string) $value;
	if ( function_exists( 'esc_url_raw' ) ) {
		return esc_url_raw( $value );
	}
	if ( function_exists( 'sanitize_text_field' ) ) {
		return sanitize_text_field( $value );
	}

	return filter_var( $value, FILTER_SANITIZE_URL );
};
$nai_demo_trigger = isset( $_GET['nai_trigger'] ) ? sanitize_key( (string) $nai_unslash_request_val( $_GET['nai_trigger'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$nai_show_tweaks  = (
	( isset( $_GET['bbai_preview'] ) && 'nai' === sanitize_key( (string) $nai_unslash_request_val( $_GET['bbai_preview'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	|| ( isset( $_GET['nai_tweaks'] ) && '1' === sanitize_key( (string) $nai_unslash_request_val( $_GET['nai_tweaks'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
);

$nai_library_url      = (string) ( $nai_state['libraryUrl'] ?? '' );
$nai_missing_url      = (string) ( $nai_state['missingLibraryUrl'] ?? $nai_library_url );
$nai_needs_review_url = (string) ( $nai_state['needsReviewLibraryUrl'] ?? $nai_library_url );
$nai_settings_url     = (string) ( $nai_state['settingsUrl'] ?? '' );
$nai_usage_url        = (string) ( $nai_state['usageUrl'] ?? '' );

// Ring/stage tone — early stages stay primary (calm momentum) instead of red.
$nai_tone_class = $nai_coverage >= 90 ? 'nai-ring--ok'
	: ( $nai_coverage >= 50 ? 'nai-ring--warn'
	: 'nai-ring--primary' );

$nai_next_milestone        = min( 100, (int) ( ceil( ( $nai_coverage + 1 ) / 15 ) * 15 ) );
$nai_distance_to_milestone = max( 0, $nai_next_milestone - $nai_coverage );

// Time saved estimate — ~55s per generated image.
$nai_minutes_saved = (int) round( ( $nai_optimized * 55 ) / 60 );
$nai_hours_saved   = $nai_minutes_saved / 60;

// Forward projection (calm pace assumption: ~1.2 pts/day → 8.4/week).
$nai_projection_target = $nai_coverage >= 75 ? 95 : 75;
$nai_weeks_to_target   = max( 1, (int) round( max( 0, $nai_projection_target - $nai_coverage ) / 8.4 ) );

// Today's Pass contains only actionable rows captured during the latest explicit scan.
$nai_pass_items       = isset( $nai_state['todaysPassItems'] ) && is_array( $nai_state['todaysPassItems'] ) ? $nai_state['todaysPassItems'] : array();
$nai_pass_total       = count( $nai_pass_items );
$nai_queue_items      = array_slice( $nai_pass_items, 0, 5 );
$nai_queue_size       = count( $nai_queue_items );
$nai_show_queue       = $nai_queue_size > 0;
$nai_pass_slots       = $nai_is_trial ? min( $nai_pass_total, $nai_daily_rem, $nai_credits_rem ) : min( $nai_pass_total, $nai_credits_rem );
$nai_deferred         = max( 0, $nai_pass_total - $nai_pass_slots );
$nai_pass_blocked     = $nai_pass_total > 0 && $nai_pass_slots <= 0;
$nai_existing_count   = $nai_missing > 0 ? $nai_missing : $nai_weak;
$nai_existing_work    = ! $nai_show_queue && $nai_existing_count > 0;
$nai_new_since_visit  = $nai_pass_total;
$nai_drawer_items     = array();
foreach ( array_slice( $nai_pass_items, 0, min( 5, $nai_pass_slots ) ) as $idx => $item ) {
	$item = is_array( $item ) ? $item : array();
	$nai_drawer_items[] = array(
		'id'   => (int) ( $item['id'] ?? $item['attachment_id'] ?? 0 ),
		'name' => (string) ( $item['name'] ?? sprintf(
			/* translators: %d: queue index. */
			__( 'image-%d', 'beepbeep-ai-alt-text-generator' ),
			$idx + 1
		) ),
		'page'      => (string) ( $item['signal'] ?? __( 'Library', 'beepbeep-ai-alt-text-generator' ) ),
		'thumb_url' => isset( $item['thumb_url'] ) ? $nai_sanitize_url_raw( $item['thumb_url'] ) : '',
		'hue'       => (int) ( $item['hue'] ?? ( $idx * 70 ) ),
	);
}

$nai_coverage_pct_real = $nai_total > 0 ? (int) round( ( $nai_optimized / $nai_total ) * 100 ) : 0;
$nai_remaining_total   = max( 0, $nai_total - $nai_optimized );
$nai_coverage_tone_cls = $nai_coverage_pct_real >= 90 ? '' : 'nai-progress--primary';

$nai_primary_cta_url = $nai_missing > 0 ? $nai_missing_url : ( $nai_weak > 0 ? $nai_needs_review_url : $nai_library_url );

/**
 * Inline icon helper. Mirrors the design's Icon component.
 */
$nai_icon = static function ( string $name, int $size = 16, float $stroke = 1.75 ): string {
	$paths = array(
		'zap'         => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
		'sparkles'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
		'check'       => '<path d="m5 12 5 5 9-11"/>',
		'crown'       => '<path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/>',
		'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
		'arrow-right' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
		'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
		'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
		'shield'      => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/>',
		'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'trend'       => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
		'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
		'refresh'     => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
		'info'        => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/>',
	);
	$body  = $paths[ $name ] ?? '';
	return sprintf(
		'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%3$s</svg>',
		$size,
		esc_attr( (string) $stroke ),
		$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG path data only.
	);
};

// Ring geometry.
$nai_ring_size   = 56;
$nai_ring_stroke = 5;
$nai_ring_radius = ( $nai_ring_size - $nai_ring_stroke ) / 2;
$nai_ring_circ   = 2 * M_PI * $nai_ring_radius;
$nai_ring_dash   = ( $nai_coverage / 100 ) * $nai_ring_circ;

$nai_shell_active       = 'dashboard';
$nai_shell_is_pro       = $nai_is_pro;
$nai_shell_drawer_items = $nai_drawer_items;
$nai_shell_prototype    = $nai_show_tweaks;
$nai_shell_demo_trigger = $nai_demo_trigger;
require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/nai-shell-open.php';
?>
<div
	class="nai-screen nai-screen--dashboard"
	data-nai-screen="dashboard"
	data-bbai-entitlement-remaining-value="<?php echo esc_attr( (string) $nai_credits_rem ); ?>"
	data-nai-total-images="<?php echo esc_attr( (string) $nai_total ); ?>"
	data-nai-optimized-images="<?php echo esc_attr( (string) $nai_optimized ); ?>"
	data-nai-coverage="<?php echo esc_attr( (string) $nai_coverage ); ?>"
>

	<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-quota-card.php'; ?>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-hero.php'; ?>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-stats.php'; ?>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-progress.php'; ?>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-recent-activity.php'; ?>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-actions.php'; ?>
</div>

<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-generation-drawer.php'; ?>

<div class="nai-toast" hidden data-nai-toast></div>

	<?php require BEEPBEEP_AI_PLUGIN_DIR . 'admin/components/dashboard/dashboard-prototype-overlays.php'; ?>
	</div>
