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
	aria-label="<?php echo esc_attr__( 'Logged-in dashboard', 'beepbeep-ai-alt-text-generator' ); ?>"
>

	<?php
	// ── State-aware top banner — same shared component used on Library, Analytics, Usage ────
	if ( isset( $bbai_dashboard_command_hero ) && is_array( $bbai_dashboard_command_hero ) ) {
		$bbai_command_hero = $bbai_dashboard_command_hero;
		$bbai_ch_partial   = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/status-banner.php';
		if ( is_readable( $bbai_ch_partial ) ) {
			require $bbai_ch_partial;
		}
	}
	?>

	<?php
	$bbai_li_upgrade_float_enabled = ! empty( $bbai_has_connected_account )
		&& empty( $bbai_state_is_pro_plan )
		&& empty( $bbai_is_anonymous_trial );
	$bbai_li_upgrade_float_variants = [
		'MISSING_ALT' => [
			'title' => __( 'Scale up with BeepBeep Pro', 'beepbeep-ai-alt-text-generator' ),
			'text'  => __( 'Unlock API key management, faster bulk actions, and priority support as you work through larger libraries.', 'beepbeep-ai-alt-text-generator' ),
		],
		'ALL_CLEAR'   => [
			'title' => __( 'Keep new uploads moving with Pro', 'beepbeep-ai-alt-text-generator' ),
			'text'  => __( 'Stay ahead as your library grows with faster bulk actions, API key management, and priority support.', 'beepbeep-ai-alt-text-generator' ),
		],
	];
	$bbai_li_upgrade_float_state = isset( $bbai_li_upgrade_float_variants[ $bbai_li_state_raw ] ) ? $bbai_li_state_raw : '';
	$bbai_li_upgrade_float_copy  = $bbai_li_upgrade_float_variants[ $bbai_li_upgrade_float_state ] ?? reset( $bbai_li_upgrade_float_variants );
	$bbai_li_show_upgrade_float  = $bbai_li_upgrade_float_enabled && '' !== $bbai_li_upgrade_float_state;
	?>
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

	<?php if ( $bbai_li_upgrade_float_enabled ) : ?>
	<aside
		class="bbai-li-upgrade-float bbai-li-upgrade-float--standalone"
		data-bbai-li-upgrade-float="1"
		data-bbai-li-upgrade-state="<?php echo esc_attr( $bbai_li_upgrade_float_state ); ?>"
		data-bbai-li-upgrade-title-missing-alt="<?php echo esc_attr( (string) ( $bbai_li_upgrade_float_variants['MISSING_ALT']['title'] ?? '' ) ); ?>"
		data-bbai-li-upgrade-text-missing-alt="<?php echo esc_attr( (string) ( $bbai_li_upgrade_float_variants['MISSING_ALT']['text'] ?? '' ) ); ?>"
		data-bbai-li-upgrade-title-all-clear="<?php echo esc_attr( (string) ( $bbai_li_upgrade_float_variants['ALL_CLEAR']['title'] ?? '' ) ); ?>"
		data-bbai-li-upgrade-text-all-clear="<?php echo esc_attr( (string) ( $bbai_li_upgrade_float_variants['ALL_CLEAR']['text'] ?? '' ) ); ?>"
		aria-label="<?php esc_attr_e( 'Upgrade suggestion', 'beepbeep-ai-alt-text-generator' ); ?>"
		<?php echo $bbai_li_show_upgrade_float ? '' : 'hidden'; ?>
	>
		<div class="bbai-li-upgrade-float__body">
			<span class="bbai-li-upgrade-float__icon" aria-hidden="true">
				<svg width="18" height="18" viewBox="0 0 18 18" fill="none" focusable="false">
					<circle cx="9" cy="9" r="7.25" stroke="currentColor" stroke-width="1.5"/>
					<path d="M6.25 8.4L8.3 10.45L12 6.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<div class="bbai-li-upgrade-float__copy">
				<h2 class="bbai-li-upgrade-float__title"><?php echo esc_html( (string) ( $bbai_li_upgrade_float_copy['title'] ?? '' ) ); ?></h2>
				<p class="bbai-li-upgrade-float__text"><?php echo esc_html( (string) ( $bbai_li_upgrade_float_copy['text'] ?? '' ) ); ?></p>
			</div>
		</div>
		<div class="bbai-li-upgrade-float__actions">
			<button type="button" class="bbai-li-upgrade-float__button" data-action="show-upgrade-modal">
				<?php esc_html_e( 'Upgrade to Pro', 'beepbeep-ai-alt-text-generator' ); ?>
			</button>
		</div>
	</aside>
	<?php endif; ?>

	<?php
	// ── Activity strip — thin contextual row under the hero ──────────────────
	$bbai_strip_meta    = is_array( $bbai_li_state['meta']    ?? null ) ? $bbai_li_state['meta']    : [];
	$bbai_strip_summary = is_array( $bbai_li_state['hero']['summary'] ?? null ) ? $bbai_li_state['hero']['summary'] : [];
	$bbai_strip_job     = is_array( $bbai_strip_meta['job']   ?? null ) ? $bbai_strip_meta['job']   : null;
	$bbai_strip_last    = (string) ( $bbai_strip_meta['last_run_at'] ?? '' );
	$bbai_strip_checked = $bbai_strip_job ? (string) ( $bbai_strip_job['last_checked_at'] ?? '' ) : '';

	// Pull counts from hero summary items (label → value map).
	$bbai_strip_counts = [];
	foreach ( $bbai_strip_summary as $bbai_strip_item ) {
		$bbai_strip_counts[ strtolower( (string) ( $bbai_strip_item['label'] ?? '' ) ) ] = (string) ( $bbai_strip_item['value'] ?? '' );
	}
	$bbai_strip_missing      = $bbai_strip_counts[ strtolower( __( 'Missing', 'beepbeep-ai-alt-text-generator' ) ) ] ?? '';
	$bbai_strip_review       = $bbai_strip_counts[ strtolower( __( 'To review', 'beepbeep-ai-alt-text-generator' ) ) ] ?? '';
	$bbai_strip_credits_left = $bbai_strip_counts[ strtolower( __( 'Credits left', 'beepbeep-ai-alt-text-generator' ) ) ] ?? '';

	// Format relative time (simple, no dependency).
	$bbai_li_format_relative = static function ( string $raw ): string {
		if ( '' === $raw ) {
			return '';
		}

		$bbai_strip_age = time() - (int) strtotime( $raw );
		if ( $bbai_strip_age < 60 ) {
			return __( 'just now', 'beepbeep-ai-alt-text-generator' );
		}
		if ( $bbai_strip_age < 3600 ) {
			return sprintf(
				/* translators: %d: minutes ago */
				_n( '%d minute ago', '%d minutes ago', (int) floor( $bbai_strip_age / 60 ), 'beepbeep-ai-alt-text-generator' ),
				(int) floor( $bbai_strip_age / 60 )
			);
		}
		if ( $bbai_strip_age < 86400 ) {
			return sprintf(
				/* translators: %d: hours ago */
				_n( '%d hour ago', '%d hours ago', (int) floor( $bbai_strip_age / 3600 ), 'beepbeep-ai-alt-text-generator' ),
				(int) floor( $bbai_strip_age / 3600 )
			);
		}
		return sprintf(
			/* translators: %d: days ago */
			_n( '%d day ago', '%d days ago', (int) floor( $bbai_strip_age / 86400 ), 'beepbeep-ai-alt-text-generator' ),
			(int) floor( $bbai_strip_age / 86400 )
		);
	};
	$bbai_strip_last_fmt    = $bbai_li_format_relative( $bbai_strip_last );
	$bbai_strip_checked_fmt = $bbai_li_format_relative( $bbai_strip_checked );

	// Build items array based on state.
	$bbai_strip_items        = [];
	$bbai_strip_tone         = 'neutral'; // dot colour: neutral | warn | ok | alert
	$bbai_strip_signal_index = -1;

		switch ( $bbai_li_state_raw ) {

		case 'QUEUED':
			$bbai_strip_tone  = 'queued';
			$bbai_strip_total = $bbai_strip_job ? (int) $bbai_strip_job['total'] : 0;
			$bbai_strip_items[] = __( 'Queued automatically', 'beepbeep-ai-alt-text-generator' );
			if ( $bbai_strip_total > 0 ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s queued', '%s queued', $bbai_strip_total, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_strip_total )
				);
			}
			$bbai_strip_items[] = $bbai_strip_checked_fmt
				? sprintf(
					/* translators: %s: relative time */
					__( 'Last checked %s', 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_checked_fmt
				)
				: __( 'Last checked just now', 'beepbeep-ai-alt-text-generator' );
			$bbai_strip_signal_index = count( $bbai_strip_items ) - 1;
			break;

		case 'PROCESSING':
			$bbai_strip_tone = 'scanning';
			$bbai_strip_done  = $bbai_strip_job ? (int) $bbai_strip_job['done']  : 0;
			$bbai_strip_total = $bbai_strip_job ? (int) $bbai_strip_job['total'] : 0;
			$bbai_strip_left  = max( 0, $bbai_strip_total - $bbai_strip_done );
			$bbai_strip_items[] = __( 'Generation active', 'beepbeep-ai-alt-text-generator' );
			if ( $bbai_strip_done > 0 ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s processed', '%s processed', $bbai_strip_done, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_strip_done )
				);
			}
			if ( $bbai_strip_left > 0 ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s remaining', '%s remaining', $bbai_strip_left, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_strip_left )
				);
			}
			break;

		case 'MIXED_ATTENTION':
			$bbai_strip_tone = 'alert';
			if ( $bbai_strip_missing !== '' ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s image needs ALT text', '%s images need ALT text', (int) str_replace( ',', '', $bbai_strip_missing ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_missing
				);
			}
			if ( $bbai_strip_review !== '' && (int) str_replace( ',', '', $bbai_strip_review ) > 0 ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s ready for review', '%s ready for review', (int) str_replace( ',', '', $bbai_strip_review ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_review
				);
			}
			break;

		case 'MISSING_ALT':
			$bbai_strip_tone = 'alert';
			if ( $bbai_strip_missing !== '' ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s image needs ALT text', '%s images need ALT text', (int) str_replace( ',', '', $bbai_strip_missing ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_missing
				);
			}
			if ( $bbai_strip_review !== '' && (int) str_replace( ',', '', $bbai_strip_review ) > 0 ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s ready for review', '%s ready for review', (int) str_replace( ',', '', $bbai_strip_review ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_review
				);
			}
			if ( $bbai_strip_last_fmt ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: relative time */
					__( 'Last run %s', 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_last_fmt
				);
			}
			break;

		case 'NEEDS_REVIEW':
			$bbai_strip_tone = 'warn';
			if ( $bbai_strip_review !== '' ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: count */
					_n( '%s ready for review', '%s ready for review', (int) str_replace( ',', '', $bbai_strip_review ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_review
				);
			}
			if ( $bbai_strip_last_fmt ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: relative time */
					__( 'Last run %s', 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_last_fmt
				);
			}
			break;

		case 'ALL_CLEAR':
			$bbai_strip_tone    = 'ok';
			$bbai_strip_items[] = __( 'Ready for new uploads', 'beepbeep-ai-alt-text-generator' );
			if ( '' !== $bbai_strip_credits_left && (int) str_replace( ',', '', $bbai_strip_credits_left ) > 0 ) {
				$bbai_strip_credit_count = (int) str_replace( ',', '', $bbai_strip_credits_left );
				$bbai_strip_items[]      = sprintf(
					/* translators: %s: remaining credits */
					_n( '%s credit left', '%s credits left', $bbai_strip_credit_count, 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_credits_left
				);
			}
			if ( $bbai_strip_last_fmt ) {
				$bbai_strip_items[] = sprintf(
					/* translators: %s: relative time */
					__( 'Last run %s', 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_last_fmt
				);
			}
			break;

		case 'QUOTA_EXHAUSTED':
			$bbai_strip_tone    = 'alert';
			$bbai_strip_items[] = __( 'Out of credits', 'beepbeep-ai-alt-text-generator' );
			$bbai_strip_items[] = __( 'Add more to continue generating', 'beepbeep-ai-alt-text-generator' );
			break;

		case 'ERROR':
			$bbai_strip_tone    = 'alert';
			$bbai_strip_items[] = __( 'Generation paused', 'beepbeep-ai-alt-text-generator' );
			$bbai_strip_items[] = __( 'Check settings to continue', 'beepbeep-ai-alt-text-generator' );
			break;

		default:
			$bbai_strip_items[] = __( 'Library ready to optimise', 'beepbeep-ai-alt-text-generator' );
			break;
	}

	// Cap at 3 items.
	$bbai_strip_items = array_slice( $bbai_strip_items, 0, 3 );

	if ( ! empty( $bbai_strip_items ) ) :
	?>
	<div class="bbai-li-activity-strip bbai-li-activity-strip--<?php echo esc_attr( $bbai_strip_tone ); ?>" data-bbai-li-activity-strip="1">
		<span class="bbai-li-activity-strip__dot" aria-hidden="true"></span>
		<?php foreach ( $bbai_strip_items as $bbai_strip_i => $bbai_strip_text ) : ?>
			<?php if ( $bbai_strip_i > 0 ) : ?><span class="bbai-li-activity-strip__sep" aria-hidden="true"> · </span><?php endif; ?>
			<span class="bbai-li-activity-strip__item"<?php echo $bbai_strip_i === $bbai_strip_signal_index ? ' data-bbai-li-queued-signal="1"' : ''; ?>><?php echo esc_html( $bbai_strip_text ); ?></span>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php
	// ── Value / impact line — lightweight, positive, below the strip ─────────
	$bbai_impact_line = '';
	// Pull optimised count from donut segments (resolver puts it there for all states).
	$bbai_impact_complete = (int) ( $bbai_li_state['donut']['segments']['optimized'] ?? 0 );
	switch ( $bbai_li_state_raw ) {
		case 'ALL_CLEAR':
			$bbai_impact_line = $bbai_impact_complete > 0
				? sprintf(
					/* translators: %s: count of optimised images */
					_n( 'You\'ve improved accessibility on %s image.', 'You\'ve improved accessibility on %s images.', $bbai_impact_complete, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_impact_complete )
				)
				: __( 'Your library is fully optimised for accessibility and search.', 'beepbeep-ai-alt-text-generator' );
			break;
		case 'MISSING_ALT':
			if ( $bbai_impact_complete > 0 ) {
				$bbai_impact_line = sprintf(
					/* translators: %s: count of optimised images */
					_n( 'You\'ve improved accessibility on %s image so far.', 'You\'ve improved accessibility on %s images so far.', $bbai_impact_complete, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_impact_complete )
				);
			}
			break;
		case 'NEEDS_REVIEW':
			if ( '' !== $bbai_strip_review && (int) str_replace( ',', '', $bbai_strip_review ) > 0 ) {
				$bbai_impact_line = sprintf(
					/* translators: %s: count of images ready for review */
					_n( '%s ready for review', '%s ready for review', (int) str_replace( ',', '', $bbai_strip_review ), 'beepbeep-ai-alt-text-generator' ),
					$bbai_strip_review
				);
			} elseif ( $bbai_impact_complete > 0 ) {
				$bbai_impact_line = sprintf(
					/* translators: %s: count of optimised images */
					_n( 'You\'ve improved accessibility on %s image so far.', 'You\'ve improved accessibility on %s images so far.', $bbai_impact_complete, 'beepbeep-ai-alt-text-generator' ),
					number_format_i18n( $bbai_impact_complete )
				);
			}
			break;
	}
	if ( '' !== $bbai_impact_line ) :
	?>
	<p class="bbai-li-impact-line"><?php echo esc_html( $bbai_impact_line ); ?></p>
	<?php endif; ?>


</section>
