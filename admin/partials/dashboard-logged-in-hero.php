<?php
/**
 * Logged-in dashboard hero card.
 *
 * Two-card side-by-side layout matching the reference design:
 *   Left card  — donut (progress indicator) only
 *   Right card — badge + headline + support + CTAs + status line + summary metrics
 *
 * Required in parent scope:
 *   $bbai_li_state  array  The full DashboardState object from the resolver.
 *
 * @package BeepBeep_AI
 * @since   5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $bbai_li_state ) || ! is_array( $bbai_li_state ) ) {
	?>
	<div class="bbai-li-hero-grid">
		<div class="bbai-li-hero-grid__content bbai-li-card">
			<h1 id="bbai-li-hero-title" class="bbai-dashboard-hero-action__title">
				<?php esc_html_e( 'Dashboard loading', 'beepbeep-ai-alt-text-generator' ); ?>
			</h1>
		</div>
	</div>
	<?php
	return;
}

$bbai_li_hero     = $bbai_li_state['hero']  ?? [];
$bbai_li_donut    = $bbai_li_state['donut'] ?? [];
$bbai_li_state_id = $bbai_li_state['state'] ?? '';

$bbai_hero_is_free_plan = true;
if ( isset( $bbai_state_is_pro_plan ) ) {
	$bbai_hero_is_free_plan = ! (bool) $bbai_state_is_pro_plan;
} elseif ( isset( $bbai_is_premium ) ) {
	$bbai_hero_is_free_plan = ! (bool) $bbai_is_premium;
}
$bbai_li_all_clear_free_upsell = ( 'ALL_CLEAR' === $bbai_li_state_id && $bbai_hero_is_free_plan );
$bbai_li_all_clear_upgrade_panel = $bbai_li_all_clear_free_upsell;

// ── Donut ────────────────────────────────────────────────────────────────────
$bbai_li_donut_color    = (string) ( $bbai_li_donut['color'] ?? 'gray' );
$bbai_li_donut_animated = ! empty( $bbai_li_donut['animated'] );
$bbai_li_donut_sub      = (string) ( $bbai_li_donut['center_sub_label'] ?? '' );
$bbai_li_donut_aria     = (string) ( $bbai_li_donut['aria_label'] ?? '' );
$bbai_li_donut_pct      = max( 0, min( 100, (int) ( $bbai_li_donut['pct'] ?? 0 ) ) );

// Map resolver color → tone class (needed before computing center value class).
$bbai_li_tone_map = [
	'blue'  => 'scanning',
	'green' => 'healthy',
	'amber' => 'problem',
	'gray'  => 'neutral',
];
$bbai_li_donut_tone = sanitize_html_class( $bbai_li_tone_map[ $bbai_li_donut_color ] ?? 'neutral' );

// Build multi-segment conic-gradient: **missing (red) → review (amber) → optimised (green) → empty (gray)**.
$bbai_li_seg      = is_array( $bbai_li_donut['segments'] ?? null ) ? $bbai_li_donut['segments'] : [];
$bbai_li_seg_opt  = max( 0, (int) ( $bbai_li_seg['optimized'] ?? 0 ) );
$bbai_li_seg_weak = max( 0, (int) ( $bbai_li_seg['weak']      ?? 0 ) );
$bbai_li_seg_miss = max( 0, (int) ( $bbai_li_seg['missing']   ?? 0 ) );
$bbai_li_seg_tot  = max( 1, (int) ( $bbai_li_seg['total']     ?? 1 ) );

// Donut centre text: use resolver `center_label` when present (queue/processing/etc.), else segment order.
$bbai_li_donut_center = isset( $bbai_li_donut['center_label'] ) && (string) $bbai_li_donut['center_label'] !== ''
	? (string) $bbai_li_donut['center_label']
	: ( $bbai_li_seg_miss > 0
		? (string) number_format_i18n( $bbai_li_seg_miss )
		: ( $bbai_li_seg_weak > 0
			? (string) number_format_i18n( $bbai_li_seg_weak )
			: '✓' ) );

// Value colour: jobs/system states use tone; data states use segment-based emphasis.
$bbai_li_donut_value_uses_tone = in_array( $bbai_li_state_id, [ 'QUEUED', 'PROCESSING', 'ERROR', 'NO_IMAGES' ], true );
if ( $bbai_li_donut_value_uses_tone ) {
	$bbai_li_donut_value_class = 'bbai-li-donut__value--' . $bbai_li_donut_tone;
} elseif ( $bbai_li_seg_miss > 0 ) {
	$bbai_li_donut_value_class = 'bbai-li-donut__value--center-missing';
} elseif ( $bbai_li_seg_weak > 0 ) {
	$bbai_li_donut_value_class = 'bbai-li-donut__value--center-review';
} else {
	$bbai_li_donut_value_class = 'bbai-li-donut__value--center-clear';
}

// While a batch is queued or generating, trust copy lives in donut meta + activity strip;
// avoid hiding meta behind the generic missing-image helper (still true for static MISSING_ALT).
$bbai_li_donut_helper = ( $bbai_li_seg_miss > 0 && ! in_array( $bbai_li_state_id, [ 'QUEUED', 'PROCESSING' ], true ) )
	? __( 'Fix these first to improve SEO and accessibility.', 'beepbeep-ai-alt-text-generator' )
	: '';

if ( ! function_exists( 'bbai_dashboard_donut_ring_degrees' ) ) {
	/**
	 * Donut ring boundaries: real proportions, with a minimum ~20% arc for the
	 * current primary action (missing → red, else review → amber) so the ring never reads “all done” when it is not.
	 *
	 * @return array{0:float,1:float,2:float} End angles (deg) for red, amber, green segments.
	 */
	function bbai_dashboard_donut_ring_degrees( int $miss, int $weak, int $opt, int $tot ): array {
		$tot        = max( 1, $tot );
		$min_action = 72.0;
		$dm         = 360.0 * $miss / $tot;
		$dw         = 360.0 * $weak / $tot;
		$do         = 360.0 * $opt / $tot;
		if ( $miss > 0 ) {
			$dm = max( $dm, $min_action );
		} elseif ( $weak > 0 ) {
			$dw = max( $dw, $min_action );
		}
		$sum = $dm + $dw + $do;
		if ( $sum > 360.0001 ) {
			if ( $miss > 0 ) {
				$room = 360.0 - $dm;
				$rest = $dw + $do;
				if ( $rest > 0.0001 ) {
					$f  = $room / $rest;
					$dw = $dw * $f;
					$do = $do * $f;
				} else {
					$dw = 0.0;
					$do = 0.0;
				}
			} elseif ( $weak > 0 ) {
				$dm = 0.0;
				$do = 360.0 - $dw;
			} else {
				$f  = 360.0 / $sum;
				$dm = $dm * $f;
				$dw = $dw * $f;
				$do = $do * $f;
			}
		}
		$a1 = round( min( 360, $dm ), 3 );
		$a2 = round( min( 360, $dm + $dw ), 3 );
		$a3 = round( min( 360, $dm + $dw + $do ), 3 );

		return [ $a1, $a2, $a3 ];
	}
}

if ( $bbai_li_seg_opt + $bbai_li_seg_weak + $bbai_li_seg_miss === 0 ) {
	$bbai_li_donut_bg = 'conic-gradient(#e2e8f0 0deg 360deg)';
} elseif ( $bbai_li_seg_opt >= $bbai_li_seg_tot ) {
	$bbai_li_donut_bg = 'conic-gradient(#22c55e 0deg 360deg)';
} else {
	list( $bbai_li_donut_a1, $bbai_li_donut_a2, $bbai_li_donut_a3 ) = bbai_dashboard_donut_ring_degrees(
		$bbai_li_seg_miss,
		$bbai_li_seg_weak,
		$bbai_li_seg_opt,
		$bbai_li_seg_tot
	);
	$bbai_li_missing_seg_color = '#c98218';
	$bbai_li_donut_bg = sprintf(
		'conic-gradient(%1$s 0deg %.3Fdeg, #f59e0b %.3Fdeg %.3Fdeg, #22c55e %.3Fdeg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)',
		$bbai_li_missing_seg_color,
		$bbai_li_donut_a1,
		$bbai_li_donut_a1,
		$bbai_li_donut_a2,
		$bbai_li_donut_a2,
		$bbai_li_donut_a3,
		$bbai_li_donut_a3
	);
}

if ( ! isset( $bbai_li_donut_a1, $bbai_li_donut_a2, $bbai_li_donut_a3 ) ) {
	$bbai_li_donut_a1 = $bbai_li_donut_a2 = $bbai_li_donut_a3 = 0.0;
}

// ── Per-segment hover gradients for the chip → donut interaction ─────────────
// Each chip highlights its own segment: full ring in that segment's colour,
// the rest fades to the empty-track grey. Center label shows the segment count.
$bbai_li_seg_hover = [];
if ( $bbai_li_seg_tot > 0 ) {
	$bbai_li_seg_all_pct = 360; // "all" = full ring, use the real multi-segment bg
	if ( ( $bbai_li_seg_miss + $bbai_li_seg_weak + $bbai_li_seg_opt ) > 0 && $bbai_li_seg_opt < $bbai_li_seg_tot ) {
		$bbai_li_seg_opt_deg  = round( $bbai_li_donut_a3 - $bbai_li_donut_a2, 3 );
		$bbai_li_seg_weak_deg = round( $bbai_li_donut_a2 - $bbai_li_donut_a1, 3 );
		$bbai_li_seg_miss_deg = round( $bbai_li_donut_a1, 3 );
	} else {
		$bbai_li_seg_opt_deg  = round( ( 360 * $bbai_li_seg_opt ) / $bbai_li_seg_tot, 3 );
		$bbai_li_seg_weak_deg = round( ( 360 * $bbai_li_seg_weak ) / $bbai_li_seg_tot, 3 );
		$bbai_li_seg_miss_deg = round( ( 360 * $bbai_li_seg_miss ) / $bbai_li_seg_tot, 3 );
	}

	// "all" → restore the real gradient (stored as empty string = use default)
	$bbai_li_seg_hover['all'] = [
		'bg'    => $bbai_li_donut_bg,
		'label' => (string) $bbai_li_seg_tot,
		'sub'   => esc_js( __( 'total images', 'beepbeep-ai-alt-text-generator' ) ),
		'tone'  => $bbai_li_donut_tone,
	];
	// "optimized" → full green arc proportional to optimized count
	$bbai_li_seg_hover['optimized'] = [
		'bg'    => $bbai_li_seg_opt_deg > 0
			? sprintf( 'conic-gradient(#22c55e 0deg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)', $bbai_li_seg_opt_deg, $bbai_li_seg_opt_deg )
			: 'conic-gradient(#e2e8f0 0deg 360deg)',
		'label' => (string) $bbai_li_seg_opt,
		'sub'   => esc_js( __( 'optimized', 'beepbeep-ai-alt-text-generator' ) ),
		'tone'  => 'healthy',
	];
	// "weak" → full amber arc
	$bbai_li_seg_hover['weak'] = [
		'bg'    => $bbai_li_seg_weak_deg > 0
			? sprintf( 'conic-gradient(#f59e0b 0deg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)', $bbai_li_seg_weak_deg, $bbai_li_seg_weak_deg )
			: 'conic-gradient(#e2e8f0 0deg 360deg)',
		'label' => (string) $bbai_li_seg_weak,
		'sub'   => esc_js( __( 'needs review', 'beepbeep-ai-alt-text-generator' ) ),
		'tone'  => 'problem',
	];
	// "missing" → full red arc
	$bbai_li_seg_hover['missing'] = [
		'bg'    => $bbai_li_seg_miss_deg > 0
			? sprintf(
				'conic-gradient(%1$s 0deg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)',
				'#c98218',
				$bbai_li_seg_miss_deg,
				$bbai_li_seg_miss_deg
			)
			: 'conic-gradient(#e2e8f0 0deg 360deg)',
		'label' => (string) $bbai_li_seg_miss,
		'sub'   => esc_js( __( 'missing ALT', 'beepbeep-ai-alt-text-generator' ) ),
		'tone'  => 'problem',
	];
}
$bbai_li_seg_hover_json = wp_json_encode( $bbai_li_seg_hover );

// ── Copy ─────────────────────────────────────────────────────────────────────
$bbai_li_title       = (string) ( $bbai_li_hero['headline'] ?? '' );
$bbai_li_description = (string) ( $bbai_li_hero['support'] ?? '' );

// Right-card action strip (same numbers as donut segments).
$bbai_li_missing_count = $bbai_li_seg_miss;
$bbai_li_review_count  = $bbai_li_seg_weak;
$bbai_li_flow_hidden   = in_array( $bbai_li_state_id, [ 'QUEUED', 'QUOTA_EXHAUSTED', 'ERROR', 'NO_IMAGES' ], true );
$bbai_li_flow_gen_on   = ( $bbai_li_missing_count > 0 );
$bbai_li_flow_rev_on   = ( $bbai_li_review_count > 0 );
$bbai_li_flow_done_on  = ( 0 === $bbai_li_missing_count && 0 === $bbai_li_review_count );

$bbai_hero_cta_hint = '';
if ( $bbai_li_missing_count > 0 ) {
	if ( $bbai_hero_is_free_plan && 'generate-missing' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ) {
		$bbai_hero_cta_hint = __( 'Manual generation on the free plan', 'beepbeep-ai-alt-text-generator' );
	} else {
		$bbai_hero_cta_hint = __( 'Generate ALT text to move to review.', 'beepbeep-ai-alt-text-generator' );
	}
} elseif ( $bbai_li_review_count > 0 ) {
	$bbai_hero_cta_hint = __( 'Complete your optimisation', 'beepbeep-ai-alt-text-generator' );
}

// ── CTAs ─────────────────────────────────────────────────────────────────────
$bbai_li_primary_cta   = is_array( $bbai_li_hero['primary_cta'] ?? null ) ? $bbai_li_hero['primary_cta'] : [];
$bbai_li_secondary_cta = is_array( $bbai_li_hero['secondary_cta'] ?? null ) ? $bbai_li_hero['secondary_cta'] : null;

if ( 0 === $bbai_li_missing_count && 0 === $bbai_li_review_count && in_array( $bbai_li_state_id, [ 'ALL_CLEAR', 'DONE' ], true ) ) {
	$bbai_li_state_id = 'ALL_CLEAR';
	$bbai_li_title    = __( 'Your media library is fully optimised', 'beepbeep-ai-alt-text-generator' );
	$bbai_li_description = __( 'Everything is accessible, SEO-ready, and performing at its best.', 'beepbeep-ai-alt-text-generator' );
	$bbai_li_description .= ' ' . __( 'nAi will keep watching new uploads.', 'beepbeep-ai-alt-text-generator' );
	$bbai_li_primary_cta = [
		'label'  => __( 'Upload new images', 'beepbeep-ai-alt-text-generator' ),
		'action' => 'navigate',
		'href'   => admin_url( 'upload.php' ),
	];
	$bbai_li_secondary_cta = [
		'label'      => __( 'Re-scan', 'beepbeep-ai-alt-text-generator' ),
		'busy_label' => __( 'Scanning library…', 'beepbeep-ai-alt-text-generator' ),
		'action'     => 'rescan-media-library',
		'href'       => '#',
	];
	if ( is_array( $bbai_li_hero ) ) {
		$bbai_li_hero['primary_cta']   = $bbai_li_primary_cta;
		$bbai_li_hero['secondary_cta'] = $bbai_li_secondary_cta;
		$bbai_li_hero['library_cta']   = null;
		$bbai_li_hero['badge'] = [
			'text' => __( 'All optimised', 'beepbeep-ai-alt-text-generator' ),
			'mod'  => 'green',
		];
	}
}

// ── Badge ─────────────────────────────────────────────────────────────────────
$bbai_li_badge = is_array( $bbai_li_hero['badge'] ?? null ) ? $bbai_li_hero['badge'] : null;

// Badge thresholds for missing images:
// - <= 5 missing: no badge
// - 6–20 missing: "Recommended"
// - > 20 missing: "Action needed"
if ( in_array( $bbai_li_state_id, [ 'MISSING_ALT', 'MIXED_ATTENTION' ], true ) && $bbai_li_missing_count > 0 ) {
	if ( $bbai_li_missing_count <= 5 ) {
		$bbai_li_badge = null;
	} elseif ( $bbai_li_missing_count > 20 ) {
		$bbai_li_badge = [ 'text' => __( 'Action needed', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'amber' ];
	} else {
		$bbai_li_badge = [ 'text' => __( 'Recommended', 'beepbeep-ai-alt-text-generator' ), 'mod' => 'blue' ];
	}
}

// ── Presentation overrides: QUEUED reads as “Ready to generate” ───────────────
// Keep internal state + action wiring unchanged; only improve user-facing copy.
if ( 'QUEUED' === $bbai_li_state_id ) {
	$bbai_li_queued_total = max( 0, (int) ( $bbai_li_donut['job_total'] ?? 0 ) );
	if ( $bbai_li_queued_total <= 0 ) {
		$bbai_li_queued_total = $bbai_li_missing_count;
	}
	// Avoid over-reporting queued work beyond what's currently missing locally.
	// Prefer the dashboard root counts (local coverage scan; matches ALT Library chips),
	// because resolver "truth" counts can lag behind the Media Library.
	$bbai_li_dashboard_missing_cap = isset( $bbai_dashboard_root_missing_count ) ? max( 0, (int) $bbai_dashboard_root_missing_count ) : 0;
	$bbai_li_missing_cap = $bbai_li_dashboard_missing_cap > 0 ? $bbai_li_dashboard_missing_cap : max( 0, (int) $bbai_li_missing_count );
	if ( $bbai_li_missing_cap > 0 ) {
		$bbai_li_queued_total = min( $bbai_li_queued_total, $bbai_li_missing_cap );
	}
	if ( $bbai_li_queued_total <= 0 ) {
		$bbai_li_queued_total = 1;
	}

	$bbai_li_badge = [
		'text' => __( 'READY TO GENERATE', 'beepbeep-ai-alt-text-generator' ),
		'mod'  => 'blue',
	];
	$bbai_li_title = sprintf(
		/* translators: %s: number of images ready to generate */
		_n( '%s image is ready for ALT text', '%s images are ready for ALT text', $bbai_li_queued_total, 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_li_queued_total )
	);
	$bbai_li_description = __( 'Generate ALT text now to make these images accessible and SEO-ready.', 'beepbeep-ai-alt-text-generator' );

	if ( is_array( $bbai_li_primary_cta ) && 'generate-missing' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ) {
		$bbai_li_primary_cta['label'] = sprintf(
			/* translators: %s: number of images ready to generate */
			_n( 'Generate ALT text for %s image', 'Generate ALT text for %s images', $bbai_li_queued_total, 'beepbeep-ai-alt-text-generator' ),
			number_format_i18n( $bbai_li_queued_total )
		);
		$bbai_li_primary_cta['busy_label'] = __( 'Generating ALT text…', 'beepbeep-ai-alt-text-generator' );
	}

	if ( is_array( $bbai_li_secondary_cta ) ) {
		$bbai_li_secondary_cta['label'] = __( 'Preview images', 'beepbeep-ai-alt-text-generator' );
	}

	$bbai_hero_cta_hint = __( 'Moves images into review before they go live.', 'beepbeep-ai-alt-text-generator' );
}

// Credit usage: prefer dashboard root totals (truth-reconciled in dashboard-body) over raw usage/trial defaults.
if ( isset( $bbai_dashboard_root_credits_total, $bbai_dashboard_root_credits_used, $bbai_dashboard_root_credits_left ) ) {
	$bbai_hero_c_lim  = max( 1, (int) $bbai_dashboard_root_credits_total );
	$bbai_hero_c_used = max( 0, (int) $bbai_dashboard_root_credits_used );
	$bbai_hero_c_rem  = max( 0, (int) $bbai_dashboard_root_credits_left );

	// Keep the triple consistent; prefer "remaining" when present (truth can lag on "used" right after generation).
	$bbai_hero_c_rem  = min( $bbai_hero_c_rem, $bbai_hero_c_lim );
	$bbai_hero_c_used = max( 0, min( $bbai_hero_c_lim, $bbai_hero_c_lim - $bbai_hero_c_rem ) );
} else {
	$bbai_hero_c_rem = isset( $bbai_credits_remaining ) ? max( 0, (int) $bbai_credits_remaining ) : 0;
	$bbai_hero_c_lim = 1;
	if ( class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' ) ) {
		$bbai_hero_c_lim = max( 1, (int) \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit() );
	}
	if ( isset( $bbai_credits_total ) ) {
		$bbai_hero_c_lim = max( 1, (int) $bbai_credits_total );
	} elseif ( isset( $bbai_usage_stats ) && is_array( $bbai_usage_stats ) ) {
		$bbai_hero_c_lim = max( 1, (int) ( $bbai_usage_stats['credits_total'] ?? $bbai_usage_stats['creditsTotal'] ?? $bbai_usage_stats['limit'] ?? 1 ) );
		if ( ! isset( $bbai_credits_remaining ) ) {
			$bbai_hero_c_rem = max( 0, (int) ( $bbai_usage_stats['credits_remaining'] ?? $bbai_usage_stats['creditsRemaining'] ?? $bbai_usage_stats['remaining'] ?? 0 ) );
		}
	}
	$bbai_hero_c_used = max( 0, (int) ( $bbai_credits_used ?? ( $bbai_hero_c_lim - $bbai_hero_c_rem ) ) );
}
$bbai_hero_c_used = min( $bbai_hero_c_used, $bbai_hero_c_lim );
$bbai_hero_c_rem  = min( max( 0, $bbai_hero_c_rem ), $bbai_hero_c_lim );
$bbai_hero_c_rem  = min( $bbai_hero_c_rem, max( 0, $bbai_hero_c_lim - $bbai_hero_c_used ) );
$bbai_hero_c_pct = (int) min( 100, max( 0, round( ( $bbai_hero_c_used / $bbai_hero_c_lim ) * 100 ) ) );
$bbai_hero_c_remaining_pct = (int) min( 100, max( 0, round( ( $bbai_hero_c_rem / $bbai_hero_c_lim ) * 100 ) ) );

$bbai_hero_credit_state = 'healthy';
if ( $bbai_hero_c_rem <= 0 || $bbai_hero_c_pct >= 90 ) {
	$bbai_hero_credit_state = 'empty';
} elseif ( $bbai_hero_c_pct >= 70 || $bbai_hero_c_remaining_pct <= 30 ) {
	$bbai_hero_credit_state = 'low';
}

$bbai_hero_credit_usage_line = ( 'ALL_CLEAR' === $bbai_li_state_id && $bbai_hero_is_free_plan )
	? __( 'Resets monthly', 'beepbeep-ai-alt-text-generator' )
	: __( 'Used when generating or improving ALT text', 'beepbeep-ai-alt-text-generator' );

$bbai_hero_credit_state_hint = '';
$bbai_hero_credit_low_suffix  = '';
if ( $bbai_hero_c_rem > 0 ) {
	if ( $bbai_hero_c_rem <= 10 ) {
		$bbai_hero_credit_low_suffix = __( 'Running low — upgrade or top up before you run out.', 'beepbeep-ai-alt-text-generator' );
	}
	switch ( $bbai_li_state_id ) {
		case 'MISSING_ALT':
		case 'MIXED_ATTENTION':
			$bbai_hero_credit_state_hint = __( 'Manual generation uses credits.', 'beepbeep-ai-alt-text-generator' );
			break;
		case 'NEEDS_REVIEW':
			$bbai_hero_credit_state_hint = __( 'Reviewing does not use credits.', 'beepbeep-ai-alt-text-generator' );
			break;
		case 'ALL_CLEAR':
			$bbai_hero_credit_state_hint = __( 'Credits are ready for your next uploads.', 'beepbeep-ai-alt-text-generator' );
			break;
		case 'PROCESSING':
		case 'QUEUED':
			$bbai_hero_credit_state_hint = __( 'Manual generation uses credits.', 'beepbeep-ai-alt-text-generator' );
			break;
		default:
			$bbai_hero_credit_state_hint = '';
	}
}

$bbai_hero_credit_context_line = trim( trim( (string) $bbai_hero_credit_state_hint ) . ( $bbai_hero_credit_state_hint && $bbai_hero_credit_low_suffix ? ' ' : '' ) . (string) $bbai_hero_credit_low_suffix );

$bbai_hero_credit_helper          = '';
$bbai_hero_credit_helper_hidden = true;

if ( 0 === $bbai_hero_c_rem ) {
	$bbai_hero_credit_helper        = __( 'Add credits to continue generating ALT text.', 'beepbeep-ai-alt-text-generator' );
	$bbai_hero_credit_helper_hidden = false;
}

$bbai_hero_credit_label = sprintf(
	/* translators: 1: credits used this period, 2: monthly or plan credit limit */
	__( '%1$s / %2$s used this month', 'beepbeep-ai-alt-text-generator' ),
	number_format_i18n( $bbai_hero_c_used ),
	number_format_i18n( $bbai_hero_c_lim )
);

$bbai_hero_growth_credit_limit = 1000;

if ( $bbai_hero_c_rem <= 0 ) {
	$bbai_hero_credit_context_line = __( 'No credits remaining this month', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_hero_c_rem < 20 ) {
	$bbai_hero_credit_context_line = sprintf(
		/* translators: %s: remaining credits */
		__( 'Only %s credits left this month', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_hero_c_rem )
	);
} else {
	$bbai_hero_credit_context_line = sprintf(
		/* translators: %s: remaining credits */
		__( '%s remaining this month', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_hero_c_rem )
	);
}

$bbai_hero_credit_batch_line = '';
if ( $bbai_li_missing_count > 0 && $bbai_hero_c_rem >= $bbai_li_missing_count ) {
	$bbai_hero_credit_batch_line = __( '✔ Enough credits to finish this batch', 'beepbeep-ai-alt-text-generator' );
} elseif ( $bbai_li_missing_count > 0 && $bbai_hero_c_rem < $bbai_li_missing_count ) {
	$bbai_hero_credit_batch_line = sprintf(
		/* translators: %s: number of images that can be generated with remaining credits */
		__( 'You can generate %s more images', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_hero_c_rem )
	);
}

	$bbai_hero_credit_helper = sprintf(
		/* translators: %s: Growth plan monthly image allowance */
		__( 'Enable Autopilot (up to %s images/month)', 'beepbeep-ai-alt-text-generator' ),
		number_format_i18n( $bbai_hero_growth_credit_limit )
	);
$bbai_hero_credit_helper_hidden = false;

$bbai_hero_credit_bar_aria = sprintf(
	/* translators: 1: credits used, 2: credit limit */
	__( '%1$s of %2$s credits used this month', 'beepbeep-ai-alt-text-generator' ),
	number_format_i18n( $bbai_hero_c_used ),
	number_format_i18n( $bbai_hero_c_lim )
);
?>

<div
	class="bbai-li-hero-grid<?php echo 'ALL_CLEAR' === $bbai_li_state_id ? ' bbai-all-clear-state' : ''; ?><?php echo 'NEEDS_REVIEW' === $bbai_li_state_id ? ' bbai-li-hero--needs-review' : ''; ?><?php echo in_array( $bbai_li_state_id, [ 'MISSING_ALT', 'MIXED_ATTENTION' ], true ) && $bbai_li_missing_count > 0 ? ' bbai-li-hero--missing' : ''; ?>"
	data-bbai-li-hero="1"
	data-bbai-li-state="<?php echo esc_attr( $bbai_li_state_id ); ?>"
	data-bbai-li-variant="<?php echo esc_attr( (string) ( $bbai_li_hero['variant'] ?? 'default' ) ); ?>"
	data-bbai-li-missing-count="<?php echo esc_attr( (string) $bbai_li_seg_miss ); ?>"
	data-bbai-li-review-count="<?php echo esc_attr( (string) $bbai_li_seg_weak ); ?>"
	data-bbai-li-optimised-count="<?php echo esc_attr( (string) $bbai_li_seg_opt ); ?>"
	data-bbai-li-total-count="<?php echo esc_attr( (string) $bbai_li_seg_tot ); ?>"
	aria-labelledby="bbai-li-hero-title"
>

		<?php
		// Donut card is clickable for actionable states and follows that state's next step.
		$bbai_li_has_primary_cta = ! empty( $bbai_li_primary_cta['label'] );
		$bbai_li_donut_clickable = (
			in_array( $bbai_li_state_id, [ 'MISSING_ALT', 'MIXED_ATTENTION' ], true )
			&& $bbai_li_has_primary_cta
		) || 'NEEDS_REVIEW' === $bbai_li_state_id
			|| ( 'PROCESSING' === $bbai_li_state_id && $bbai_li_has_primary_cta );
		$bbai_li_donut_action_label = $bbai_li_primary_cta['label'] ?? __( 'Take action', 'beepbeep-ai-alt-text-generator' );
		if ( 'NEEDS_REVIEW' === $bbai_li_state_id ) {
			$bbai_li_donut_action_label = __( 'Review images', 'beepbeep-ai-alt-text-generator' );
		}
		?>
	<?php /* ── LEFT CARD: donut + "X images left" + footer pills ─────────── */ ?>
	<div class="bbai-li-card bbai-li-card--donut<?php echo esc_attr( $bbai_li_donut_clickable ? ' bbai-li-card--donut-clickable' : '' ); ?>"
		<?php if ( $bbai_li_donut_clickable ) : ?>
			role="button"
			tabindex="0"
			data-bbai-li-donut-card-trigger="1"
			aria-label="<?php echo esc_attr( $bbai_li_donut_action_label ); ?>"
		<?php endif; ?>
	>

		<div class="bbai-li-donut-col">
		<div class="bbai-li-donut-area">
			<div
				class="bbai-li-donut bbai-command-donut--<?php echo esc_attr( $bbai_li_donut_tone ); ?><?php echo esc_attr( $bbai_li_donut_animated ? ' bbai-li-donut--animated' : '' ); ?>"
				data-bbai-li-donut="1"
				data-bbai-li-donut-pct="<?php echo esc_attr( (string) $bbai_li_donut_pct ); ?>"
				data-bbai-li-donut-color="<?php echo esc_attr( $bbai_li_donut_color ); ?>"
				data-bbai-li-seg-hover="<?php echo esc_attr( $bbai_li_seg_hover_json ); ?>"
				aria-label="<?php echo esc_attr( $bbai_li_donut_aria ); ?>"
				style="background: <?php echo esc_attr( $bbai_li_donut_bg ); ?>;"
			>
				<span class="bbai-li-donut__inner"></span>
				<span class="bbai-li-donut__center">
					<span class="bbai-li-donut__value <?php echo esc_attr( $bbai_li_donut_value_class ); ?>" data-bbai-li-donut-label="1">
						<?php echo esc_html( $bbai_li_donut_center ); ?>
					</span>
				</span>
			</div>

			<?php
		// Build a clearer sub-label based on state and counts.
		$bbai_li_donut_sub_display = '';
		if ( in_array( $bbai_li_state_id, [ 'QUEUED', 'ERROR', 'NO_IMAGES' ], true ) && $bbai_li_donut_sub ) {
			$bbai_li_donut_sub_display = $bbai_li_donut_sub;
		} elseif ( 'PROCESSING' === $bbai_li_state_id ) {
			$bbai_li_donut_sub_display = __( 'generating now', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'NEEDS_REVIEW' === $bbai_li_state_id ) {
			$bbai_li_donut_sub_display = __( 'IMAGES READY FOR REVIEW', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $bbai_li_seg_miss > 0 ) {
			// Completes the sentence with the large centre numeral: "4" + "images need ALT text".
			$bbai_li_donut_sub_display = _n(
				'image needs ALT text',
				'images need ALT text',
				$bbai_li_seg_miss,
				'beepbeep-ai-alt-text-generator'
			);
		} elseif ( $bbai_li_seg_weak > 0 ) {
			// Centre shows the count; sub is the tail of the sentence.
			$bbai_li_donut_sub_display = _n(
				'image ready for review',
				'images ready for review',
				$bbai_li_seg_weak,
				'beepbeep-ai-alt-text-generator'
			);
		} elseif ( $bbai_li_donut_sub ) {
			$bbai_li_donut_sub_display = $bbai_li_donut_sub;
		}
		if ( $bbai_li_donut_sub_display ) :
		?>
		<p class="bbai-donut-label bbai-li-donut__sub-label<?php echo 'NEEDS_REVIEW' === $bbai_li_state_id ? ' bbai-li-donut__sub-label--review-ready' : ''; ?>" data-bbai-li-donut-sub="1"><?php echo esc_html( $bbai_li_donut_sub_display ); ?></p>
		<?php endif; ?>

		<?php
		$bbai_li_donut_cm_href   = '';
		$bbai_li_donut_cm_label = '';
		if ( in_array( $bbai_li_state_id, [ 'MISSING_ALT', 'MIXED_ATTENTION' ], true ) && $bbai_li_seg_miss > 0 ) {
			$bbai_li_donut_cm_href   = $bbai_li_primary_cta['href'] ?? '#';
			$bbai_li_donut_cm_label = __( 'Generate ALT text →', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'MIXED_ATTENTION' === $bbai_li_state_id && $bbai_li_seg_weak > 0 && ! empty( $bbai_needs_review_library_url ) ) {
			$bbai_li_donut_cm_href   = $bbai_needs_review_library_url;
			$bbai_li_donut_cm_label = __( 'Review images →', 'beepbeep-ai-alt-text-generator' );
		}
		?>
		<?php if ( $bbai_li_donut_cm_href && $bbai_li_donut_cm_label ) : ?>
		<p class="bbai-li-donut__action-wrap"><a href="<?php echo esc_url( $bbai_li_donut_cm_href ); ?>" class="bbai-li-donut__action-cm"><?php echo esc_html( $bbai_li_donut_cm_label ); ?></a></p>
		<?php endif; ?>

		<div
			class="bbai-li-donut__review-block"
			data-bbai-li-donut-review-block="1"
			<?php echo ( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['href'] ) ) ? '' : 'hidden'; ?>
		>
			<p class="bbai-li-donut__review-prompt" data-bbai-li-donut-review-prompt="1"><?php esc_html_e( 'Review now to complete your optimisation', 'beepbeep-ai-alt-text-generator' ); ?></p>
			<a
				class="bbai-li-donut__text-cta"
				data-bbai-li-donut-review-link="1"
				href="<?php echo esc_url( ( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['href'] ) ) ? $bbai_li_secondary_cta['href'] : '#' ); ?>"
				data-action="<?php echo esc_attr( 'NEEDS_REVIEW' === $bbai_li_state_id ? ( $bbai_li_secondary_cta['action'] ?? 'navigate' ) : 'navigate' ); ?>"
			><?php esc_html_e( 'Review images →', 'beepbeep-ai-alt-text-generator' ); ?></a>
		</div>

		<p class="bbai-li-donut__helper" data-bbai-li-donut-helper="1" <?php echo $bbai_li_donut_helper ? '' : 'hidden'; ?>><?php echo $bbai_li_donut_helper ? esc_html( $bbai_li_donut_helper ) : ''; ?></p>

		<?php
		// Donut meta line — only when not using the SEO helper (avoid duplicate nudges).
		$bbai_li_donut_meta = '';
		if ( ! $bbai_li_donut_helper ) {
			if ( 'QUEUED' === $bbai_li_state_id ) {
				$bbai_li_donut_meta = __( 'Start generation when you’re ready.', 'beepbeep-ai-alt-text-generator' );
			} elseif ( 'PROCESSING' === $bbai_li_state_id ) {
				$bbai_li_donut_meta = __( 'This may take a few minutes', 'beepbeep-ai-alt-text-generator' );
			} elseif ( 'ALL_CLEAR' === $bbai_li_state_id ) {
				$bbai_li_donut_meta = __( 'Everything is optimised and SEO-ready', 'beepbeep-ai-alt-text-generator' );
			} elseif ( 'MISSING_ALT' === $bbai_li_state_id ) {
				$bbai_li_donut_meta = __( 'Click to generate', 'beepbeep-ai-alt-text-generator' );
			} elseif ( 'QUOTA_EXHAUSTED' === $bbai_li_state_id ) {
				$bbai_li_donut_meta = __( 'Add credits to continue', 'beepbeep-ai-alt-text-generator' );
			}
		}
		if ( $bbai_li_donut_meta ) :
		?>
		<p class="bbai-li-donut__meta" data-bbai-li-donut-meta="1"><?php echo esc_html( $bbai_li_donut_meta ); ?></p>
		<?php endif; ?>
		</div>
		</div><!-- /.bbai-li-donut-col -->

		<div class="bbai-li-hero-content-col">

		<div class="bbai-li-card-section bbai-li-card-section--intro">
		<?php if ( $bbai_li_badge ) : ?>
		<span class="bbai-li-state-badge bbai-li-state-badge--<?php echo sanitize_html_class( $bbai_li_badge['mod'] ); ?>" aria-hidden="true">
			<?php echo esc_html( $bbai_li_badge['text'] ); ?>
		</span>
		<?php endif; ?>

		<h1
			id="bbai-li-hero-title"
			class="bbai-li-headline"
			data-bbai-li-hero-headline="1"
			<?php if ( $bbai_li_donut_animated ) : ?>aria-live="polite" aria-atomic="true"<?php endif; ?>
		><?php echo esc_html( $bbai_li_title ); ?></h1>

		<p
			class="bbai-li-support"
			data-bbai-li-hero-support="1"
			<?php if ( $bbai_li_donut_animated ) : ?>aria-live="polite" aria-atomic="true"<?php endif; ?>
		><?php echo esc_html( $bbai_li_description ); ?></p>

		</div>

		<div class="bbai-li-card-section bbai-li-card-section--actions">
		<div class="bbai-action-block">
			<div class="bbai-li-cta-row bbai-li-cta-group bbai-cta-group">
			<?php if ( ! empty( $bbai_li_primary_cta['label'] ) ) :
					$bbai_li_busy_label = (string) ( $bbai_li_primary_cta['busy_label'] ?? '' );
					if ( '' === $bbai_li_busy_label ) {
						$bbai_li_busy_label = __( 'Working…', 'beepbeep-ai-alt-text-generator' );
					}
				?>
				<div class="bbai-li-cta-primary-col">
					<a
						class="bbai-li-btn-primary bbai-btn bbai-btn-primary<?php echo 'generate-missing' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ? ' bbai-generate-button' : ''; ?>"
						href="<?php echo esc_url( $bbai_li_primary_cta['href'] ?? '#' ); ?>"
						data-bbai-li-action="<?php echo esc_attr( $bbai_li_primary_cta['action'] ?? '' ); ?>"
						<?php if ( 'generate-missing' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ) : ?>
							data-action="generate-missing"
							data-bbai-action="generate_missing"
							data-bbai-generation-action="1"
						<?php endif; ?>
						data-bbai-li-primary-cta="1"
						data-busy-label="<?php echo esc_attr( $bbai_li_busy_label ); ?>"
					><?php if ( 'approve-all' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ) : ?><span class="bbai-btn-content"><?php echo esc_html( $bbai_li_primary_cta['label'] ); ?></span><span class="bbai-btn-loading-label" aria-hidden="true"><?php echo esc_html( $bbai_li_busy_label ); ?></span><span class="bbai-btn-spinner" aria-hidden="true"></span><?php else : echo esc_html( $bbai_li_primary_cta['label'] ); endif; ?></a>
					<?php if ( $bbai_hero_cta_hint ) : ?>
					<p class="bbai-hero-cta-hint" data-bbai-hero-cta-hint="1"><?php echo esc_html( $bbai_hero_cta_hint ); ?></p>
					<?php endif; ?>
					<p class="bbai-hero-cta-hint bbai-hero-cta-hint--passive" data-bbai-gen-running-note="1" hidden>
						<?php esc_html_e( 'Generation is running in the background.', 'beepbeep-ai-alt-text-generator' ); ?>
					</p>
					<a
						class="bbai-hero-review-inline-link"
						href="<?php echo esc_url( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['href'] ) ? $bbai_li_secondary_cta['href'] : '#' ); ?>"
						data-action="<?php echo esc_attr( 'NEEDS_REVIEW' === $bbai_li_state_id ? ( $bbai_li_secondary_cta['action'] ?? 'navigate' ) : 'navigate' ); ?>"
						data-bbai-li-secondary-inline-cta="1"
						<?php echo ( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['href'] ) ) ? '' : 'hidden'; ?>
					><?php echo esc_html( ( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['label'] ) ) ? $bbai_li_secondary_cta['label'] : __( 'Review individually →', 'beepbeep-ai-alt-text-generator' ) ); ?></a>
					<?php
					$bbai_li_all_clear_rescan_busy = is_array( $bbai_li_secondary_cta ) ? (string) ( $bbai_li_secondary_cta['busy_label'] ?? '' ) : '';
					?>
					<a
						class="bbai-all-clear-rescan-link"
						href="<?php echo esc_url( 'ALL_CLEAR' === $bbai_li_state_id && is_array( $bbai_li_secondary_cta ) ? ( $bbai_li_secondary_cta['href'] ?? '#' ) : '#' ); ?>"
						data-action="<?php echo esc_attr( 'ALL_CLEAR' === $bbai_li_state_id && is_array( $bbai_li_secondary_cta ) ? ( $bbai_li_secondary_cta['action'] ?? 'rescan-media-library' ) : 'rescan-media-library' ); ?>"
						data-bbai-li-all-clear-rescan="1"
						<?php if ( 'ALL_CLEAR' === $bbai_li_state_id && '' !== $bbai_li_all_clear_rescan_busy ) : ?>
						data-busy-label="<?php echo esc_attr( $bbai_li_all_clear_rescan_busy ); ?>"
						<?php endif; ?>
						<?php echo ( 'ALL_CLEAR' === $bbai_li_state_id && is_array( $bbai_li_secondary_cta ) && ! empty( $bbai_li_secondary_cta['label'] ) ) ? '' : 'hidden'; ?>
					><?php echo esc_html( ( 'ALL_CLEAR' === $bbai_li_state_id && is_array( $bbai_li_secondary_cta ) && ! empty( $bbai_li_secondary_cta['label'] ) ) ? $bbai_li_secondary_cta['label'] : __( 'Re-scan', 'beepbeep-ai-alt-text-generator' ) ); ?></a>
					<span
						class="bbai-dashboard-scan-inline-feedback"
						data-bbai-dashboard-scan-inline-feedback="1"
						data-bbai-dashboard-scan-inline-feedback-slot="1"
						role="status"
						aria-live="polite"
						hidden
					></span>
				</div>
			<?php endif; ?>

			<?php if ( $bbai_li_review_count > 0 && is_array( $bbai_li_secondary_cta ) && ! empty( $bbai_li_secondary_cta['label'] ) && 'NEEDS_REVIEW' !== $bbai_li_state_id && 'ALL_CLEAR' !== $bbai_li_state_id ) :
				$bbai_li_secondary_busy = (string) ( $bbai_li_secondary_cta['busy_label'] ?? '' );
				?>
				<a
					class="bbai-li-btn-secondary bbai-btn bbai-btn-secondary bbai-button-secondary"
					href="<?php echo esc_url( $bbai_li_secondary_cta['href'] ?? '#' ); ?>"
					data-action="<?php echo esc_attr( $bbai_li_secondary_cta['action'] ?? '' ); ?>"
					data-bbai-li-secondary-cta="1"
					<?php if ( '' !== $bbai_li_secondary_busy ) : ?>
						data-busy-label="<?php echo esc_attr( $bbai_li_secondary_busy ); ?>"
					<?php endif; ?>
				><?php echo esc_html( $bbai_li_secondary_cta['label'] ); ?></a>
			<?php endif; ?>
			</div>
		</div>
		</div>

		<div class="bbai-li-donut-context" data-bbai-li-donut-context="1">
			<div class="bbai-status-summary" role="group" aria-label="<?php esc_attr_e( 'Status summary', 'beepbeep-ai-alt-text-generator' ); ?>">
			<button type="button" class="bbai-status-item bbai-status-item--readout is-missing" data-bbai-dashboard-status-filter="missing" data-filter="missing" aria-label="<?php echo esc_attr( 'QUEUED' === $bbai_li_state_id ? __( 'Preview images ready to generate', 'beepbeep-ai-alt-text-generator' ) : __( 'Show images that need alt text', 'beepbeep-ai-alt-text-generator' ) ); ?>">
				<span class="bbai-dot <?php echo esc_attr( 'QUEUED' === $bbai_li_state_id ? 'blue' : 'red' ); ?>" aria-hidden="true"></span>
				<span class="bbai-status-text">
					<strong class="bbai-count" data-bbai-status-metric="missing"><?php echo esc_html( (string) number_format_i18n( 'QUEUED' === $bbai_li_state_id && isset( $bbai_li_queued_total ) ? $bbai_li_queued_total : $bbai_li_missing_count ) ); ?></strong>
					<?php
					$bbai_li_ready_label_count = ( 'QUEUED' === $bbai_li_state_id && isset( $bbai_li_queued_total ) ) ? $bbai_li_queued_total : $bbai_li_missing_count;
					echo esc_html( 'QUEUED' === $bbai_li_state_id
						? ' ' . _n( 'ready to generate', 'ready to generate', $bbai_li_ready_label_count, 'beepbeep-ai-alt-text-generator' )
						: ' ' . _n( 'image needs ALT text', 'images need ALT text', $bbai_li_missing_count, 'beepbeep-ai-alt-text-generator' )
					);
					?>
				</span>
			</button>
			<button type="button" class="bbai-status-item bbai-status-item--readout is-review" data-bbai-dashboard-status-filter="weak" data-filter="review" title="<?php esc_attr_e( 'Review improves accuracy before publishing', 'beepbeep-ai-alt-text-generator' ); ?>" aria-label="<?php esc_attr_e( 'Show images ready for review', 'beepbeep-ai-alt-text-generator' ); ?>">
				<span class="bbai-dot orange" aria-hidden="true"></span>
				<span class="bbai-status-text">
					<strong class="bbai-count" data-bbai-status-metric="review"><?php echo esc_html( (string) number_format_i18n( $bbai_li_review_count ) ); ?></strong>
					<?php
					echo esc_html(
						' ' . _n( 'ready for review', 'ready for review', $bbai_li_review_count, 'beepbeep-ai-alt-text-generator' )
					);
					?>
				</span>
			</button>
			</div>

			<?php
			// Step indicator: one clear "current" step, prior steps marked done.
			$bbai_li_active_step = ( $bbai_li_missing_count > 0 )
				? 'generate'
				: ( $bbai_li_review_count > 0 ? 'review' : 'done' );
			$bbai_li_gen_class  = ( 'generate' === $bbai_li_active_step ) ? 'is-active' : 'is-done';
			$bbai_li_rev_class  = ( 'review' === $bbai_li_active_step ) ? 'is-active' : ( 'done' === $bbai_li_active_step ? 'is-done' : 'is-inactive' );
			$bbai_li_done_class = ( 'done' === $bbai_li_active_step ) ? 'is-active' : 'is-inactive';
			?>
			<div
				class="bbai-progress-flow"
				data-bbai-li-flow="1"
				<?php echo $bbai_li_flow_hidden ? 'hidden' : ''; ?>
				role="group"
				aria-label="<?php esc_attr_e( 'Workflow: Generate, Review, Done', 'beepbeep-ai-alt-text-generator' ); ?>"
			>
				<span class="bbai-progress-step <?php echo esc_attr( $bbai_li_gen_class ); ?>" data-bbai-flow-step="generate"><?php esc_html_e( 'Generate', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="bbai-progress-flow__arrow" aria-hidden="true"><?php echo esc_html( is_rtl() ? '←' : '→' ); ?></span>
				<span class="bbai-progress-step <?php echo esc_attr( $bbai_li_rev_class ); ?>" data-bbai-flow-step="review"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="bbai-progress-flow__arrow" aria-hidden="true"><?php echo esc_html( is_rtl() ? '←' : '→' ); ?></span>
				<span class="bbai-progress-step <?php echo esc_attr( $bbai_li_done_class ); ?>" data-bbai-flow-step="done"><?php esc_html_e( 'Done', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
		</div>

		<div class="bbai-card-meta-row">
			<div
				class="bbai-li-activity-strip"
				data-bbai-li-activity-strip="1"
				hidden="hidden"
				aria-hidden="true"
			></div>
		</div>

		</div><!-- /.bbai-li-hero-content-col -->

	</div>

	<?php /* ── RIGHT CARD: usage + automation ─────────────────────────────── */ ?>
	<div class="bbai-li-card bbai-li-card--content">

		<div class="bbai-li-card-section bbai-li-card-section--activation" data-bbai-activation-host="1" aria-live="polite"></div>

		<div class="bbai-li-card-section bbai-li-card-section--monetisation bbai-li-card-section--spaced">

			<?php if ( $bbai_li_all_clear_upgrade_panel ) : ?>
			<hr class="bbai-all-clear-section-divider" aria-hidden="true" />
			<div class="bbai-all-clear-upgrade" data-bbai-all-clear-upgrade="1">
					<p class="bbai-li-free-plan-upsell__note" data-bbai-li-free-upsell="1"><?php esc_html_e( 'New uploads need Autopilot to stay covered.', 'beepbeep-ai-alt-text-generator' ); ?></p>
				<div class="bbai-all-clear-upgrade__panel">
					<span class="bbai-all-clear-upgrade__icon" aria-hidden="true">⚡</span>
					<div class="bbai-all-clear-upgrade__copy">
						<p class="bbai-all-clear-upgrade__title"><?php esc_html_e( 'Enable Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></p>
						<p class="bbai-all-clear-upgrade__desc"><?php esc_html_e( 'Automatically generate ALT text for new media uploads.', 'beepbeep-ai-alt-text-generator' ); ?></p>
					</div>
					<button
						type="button"
						class="bbai-all-clear-upgrade__cta bbai-automation-toggle"
						data-action="show-upgrade-modal"
						data-bbai-analytics-upgrade="all_clear_automate_uploads"
						aria-label="<?php esc_attr_e( 'Upgrade to enable automatic ALT text for future uploads', 'beepbeep-ai-alt-text-generator' ); ?>"
						aria-pressed="false"
						aria-disabled="true"
					><span aria-hidden="true"></span></button>
				</div>
				<p class="bbai-all-clear-upgrade__nudge"><?php esc_html_e( 'Keep your library optimised as you upload new images.', 'beepbeep-ai-alt-text-generator' ); ?></p>
			</div>
			<?php endif; ?>

			<div
				class="bbai-credit-usage<?php echo 'ALL_CLEAR' === $bbai_li_state_id ? ' bbai-credit-usage--all-clear-muted' : ''; ?>"
				data-bbai-hero-credit-usage="1"
				data-credit-state="<?php echo esc_attr( $bbai_hero_credit_state ); ?>"
				data-bbai-hero-credits-used="<?php echo esc_attr( (string) $bbai_hero_c_used ); ?>"
				data-bbai-hero-credits-remaining="<?php echo esc_attr( (string) $bbai_hero_c_rem ); ?>"
				data-bbai-hero-credits-limit="<?php echo esc_attr( (string) $bbai_hero_c_lim ); ?>"
				data-bbai-hero-growth-credit-limit="<?php echo esc_attr( (string) $bbai_hero_growth_credit_limit ); ?>"
				data-bbai-hero-missing-count="<?php echo esc_attr( (string) $bbai_li_missing_count ); ?>"
				data-bbai-hero-review-count="<?php echo esc_attr( (string) $bbai_li_review_count ); ?>"
			>
				<div class="bbai-credit-head">
					<div class="bbai-credit-label" data-bbai-hero-credit-label="1"><?php echo esc_html( $bbai_hero_credit_label ); ?></div>
					<p class="bbai-credit-clarity" data-bbai-hero-credit-clarity="1"><?php echo esc_html( $bbai_hero_credit_usage_line ); ?></p>
				</div>
				<div
					class="bbai-credit-bar"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="<?php echo esc_attr( (string) $bbai_hero_c_pct ); ?>"
					aria-label="<?php echo esc_attr( $bbai_hero_credit_bar_aria ); ?>"
					data-bbai-hero-credit-bar="1"
				>
					<div class="bbai-credit-fill" data-bbai-hero-credit-fill="1" style="--bbai-credit-percent: <?php echo esc_attr( (string) $bbai_hero_c_pct ); ?>%;"></div>
				</div>
				<p
					class="bbai-credit-context<?php echo 'healthy' !== $bbai_hero_credit_state ? ' bbai-credit-context--warning' : ''; ?>"
					data-bbai-hero-credit-context="1"
				><?php echo esc_html( $bbai_hero_credit_context_line ); ?></p>
				<p
					class="bbai-credit-insight"
					data-bbai-hero-credit-insight="1"
					<?php echo '' !== $bbai_hero_credit_batch_line ? '' : 'hidden'; ?>
				><?php echo esc_html( $bbai_hero_credit_batch_line ); ?></p>
				<p
					class="bbai-credit-helper bbai-credit-helper--growth"
					data-bbai-hero-credit-helper="1"
					<?php echo $bbai_hero_credit_helper_hidden ? 'hidden' : ''; ?>
				><a
					href="#"
					class="bbai-credit-growth-link"
					data-action="show-upgrade-modal"
					data-bbai-analytics-upgrade="hero_credits_growth_remaining"
					data-bbai-hero-credit-growth-link="1"
				><?php echo esc_html( $bbai_hero_credit_helper ); ?></a></p>
				<?php if ( $bbai_hero_is_free_plan && 'ALL_CLEAR' !== $bbai_li_state_id ) : ?>
				<div class="bbai-credit-upgrade-panel" data-bbai-credit-upgrade-panel="1">
					<div class="bbai-credit-upgrade-panel__copy">
						<p class="bbai-credit-upgrade-panel__title"><?php esc_html_e( 'Enable Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></p>
							<p class="bbai-credit-upgrade-panel__sub"><?php esc_html_e( 'Use Autopilot when new images are added.', 'beepbeep-ai-alt-text-generator' ); ?></p>
					</div>
					<span
						class="bbai-credit-upgrade-panel__cta"
						role="button"
						tabindex="0"
						data-action="show-upgrade-modal"
						data-bbai-analytics-upgrade="hero_credits_upgrade_compact"
					><?php esc_html_e( 'Upgrade', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<?php endif; ?>
			</div>
		</div>

	</div>

</div>

<?php /* ── Perceived-performance: CTA busy state + optimistic status line ── */ ?>
<?php
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment -- Inline hero script: large TEXT map; placeholders follow standard sprintf patterns.
?>
<script>
(function () {
	'use strict';

	var hero = document.querySelector( '[data-bbai-li-hero="1"]' );
	if ( ! hero ) { return; }

	function isUserAuthenticated() {
		return !! window.bbaiUser && !! window.bbaiUser.email;
	}

	window.isUserAuthenticated = window.isUserAuthenticated || isUserAuthenticated;

	function resolveHeroCreditState( used, total, remaining ) {
		var safeTotal = Math.max( 1, parseInt( total, 10 ) || 1 );
		var safeUsed = Math.max( 0, parseInt( used, 10 ) || 0 );
		var safeRemaining = Math.max( 0, parseInt( remaining, 10 ) || 0 );
		var usedPct = Math.min( 100, Math.max( 0, Math.round( ( safeUsed / safeTotal ) * 100 ) ) );
		var remainingPct = Math.min( 100, Math.max( 0, Math.round( ( safeRemaining / safeTotal ) * 100 ) ) );

		if ( safeRemaining <= 0 || usedPct >= 90 ) {
			return 'empty';
		}

		if ( usedPct >= 70 || remainingPct <= 30 ) {
			return 'low';
		}

		return 'healthy';
	}

	function applyHeroCreditStateFromAttributes() {
		var wrap = hero.querySelector( '[data-bbai-hero-credit-usage="1"]' ) || document.querySelector( '[data-bbai-hero-credit-usage="1"]' );
		if ( ! wrap ) {
			return;
		}

		var used = parseInt( wrap.getAttribute( 'data-bbai-hero-credits-used' ) || '0', 10 ) || 0;
		var total = parseInt( wrap.getAttribute( 'data-bbai-hero-credits-limit' ) || '1', 10 ) || 1;
		var remaining = parseInt( wrap.getAttribute( 'data-bbai-hero-credits-remaining' ) || String( Math.max( 0, total - used ) ), 10 ) || 0;
		var creditState = resolveHeroCreditState( used, total, remaining );
		var ctx = wrap.querySelector( '[data-bbai-hero-credit-context="1"]' );

		if ( wrap.getAttribute( 'data-credit-state' ) !== creditState ) {
			wrap.setAttribute( 'data-credit-state', creditState );
		}
		if ( ctx ) {
			ctx.classList.toggle( 'bbai-credit-context--warning', creditState !== 'healthy' );
		}
	}

	function renderGuestDashboardFallback() {
		var root = document.querySelector( '[data-bbai-dashboard-root="1"]' );
		var loggedIn = document.querySelector( '[data-bbai-logged-in-dashboard]' );
		if ( typeof window.bbaiResetDashboardStateController === 'function' ) {
			window.bbaiResetDashboardStateController();
		}
		if ( typeof window.bbaiRenderGuestDashboard === 'function' ) {
			window.bbaiRenderGuestDashboard();
			return;
		}
		if ( loggedIn ) {
			loggedIn.hidden = true;
			loggedIn.setAttribute( 'aria-hidden', 'true' );
		}
		if ( root ) {
			root.setAttribute( 'data-bbai-has-connected-account', '0' );
			root.setAttribute( 'data-bbai-is-guest-trial', '1' );
			root.setAttribute( 'data-bbai-auth-state', 'anonymous' );
			root.setAttribute( 'data-bbai-dashboard-funnel', 'guest_dashboard' );
			// Do not inject a minimal guest UI fallback.
			// Guests must use the SSR guest funnel/dashboard markup from PHP.
			root.hidden = false;
			root.removeAttribute( 'aria-busy' );
		}
	}

	if ( window.bbaiAuthResolved === true && ! isUserAuthenticated() ) {
		renderGuestDashboardFallback();
		return;
	}

	var heroVariant = hero.getAttribute( 'data-bbai-li-variant' ) || '';
	var safetyTimer = null;
	var heroGenerationWatchdog = null;
	var liveSignalTimer = null;
	var transientStatusTimer = null;
	var transitionPulseTimer = null;
	var successHighlightTimer = null;
	var processingAnimationFrame = null;
	var processingAnimationTimer = null;
	var processingAnimationToken = 0;
	var dashboardPolling = {
		timer: null,
		inFlight: false,
		failureCount: 0,
		currentSignature: '',
		currentTruth: null,
		latestState: hero.getAttribute( 'data-bbai-li-state' ) || '',
		active: false,
		activeInterval: 0,
		requiresResolvedSync: false,
		optimisticAction: '',
		bootstrapSyncApplied: false,
		hydrated: false,
		ssrRendered: true,
	};

	// ── Dashboard state stabilizer (prevents flicker/regression) ─────────────
	// Guarded so other scripts can reuse the same controller.
	window.BBAI_DASHBOARD_STATE_STABILIZER = window.BBAI_DASHBOARD_STATE_STABILIZER || ( function () {
		var STATE_PRIORITY = {
			needs_alt: 1,
			ready_to_generate: 2,
			queued: 3,
			review_ready: 4,
			complete: 5,
		};

		function prio( key ) {
			return STATE_PRIORITY[ String( key || '' ) ] || 0;
		}

		var ctrl = {
			currentState: '',
			pendingState: null,
			lastStateHash: '',
			stableCount: 0,
			isGenerating: false,
			debounceTimer: null,
		};

		function logDev( msg, detail ) {
			if ( ! window.BBAI_LOG || typeof window.BBAI_LOG.info !== 'function' || ! window.console || typeof window.console.debug !== 'function' ) {
				return;
			}
			window.console.debug( '[bbai-dashboard-stabilizer]', msg, detail || {} );
		}

		ctrl.receive = function ( next ) {
			var n = next && typeof next === 'object' ? next : {};
			var key = String( n.key || '' );
			var hash = String( n.hash || '' );
			var apply = typeof n.apply === 'function' ? n.apply : null;
			var generating = !! n.generating;
			var reason = String( n.reason || '' );

			if ( ! key || ! hash || ! apply ) {
				return false;
			}

			logDev( 'State received', { key: key, reason: reason } );

			// Freeze during generation: ignore mid-run transitions/updates.
			if ( ctrl.isGenerating ) {
				if ( generating ) {
					logDev( 'State ignored (generating freeze)', { key: key, reason: reason } );
					return false;
				}
				// Generation ended: allow higher-priority completion states.
				ctrl.isGenerating = false;
				window.bbaiIsGenerating = false;
			}

			// Reject regressions.
			if ( ctrl.currentState && prio( key ) < prio( ctrl.currentState ) ) {
				logDev( 'State rejected (regression)', { from: ctrl.currentState, to: key, reason: reason } );
				return false;
			}

			// Stability: must match twice.
			if ( ctrl.lastStateHash === hash ) {
				ctrl.stableCount += 1;
			} else {
				ctrl.lastStateHash = hash;
				ctrl.stableCount = 1;
			}

			if ( ctrl.stableCount < 2 ) {
				logDev( 'State ignored (unstable)', { key: key, reason: reason, stableCount: ctrl.stableCount } );
				return false;
			}

			// Debounce apply: only latest state renders.
			ctrl.pendingState = { key: key, hash: hash, apply: apply, generating: generating, reason: reason };
			if ( ctrl.debounceTimer ) {
				window.clearTimeout( ctrl.debounceTimer );
				ctrl.debounceTimer = null;
			}
			ctrl.debounceTimer = window.setTimeout( function () {
				var pending = ctrl.pendingState;
				ctrl.debounceTimer = null;
				if ( ! pending || pending.hash !== hash ) {
					return;
				}
				ctrl.pendingState = null;
				ctrl.currentState = pending.key;
				if ( pending.generating ) {
					ctrl.isGenerating = true;
					window.bbaiIsGenerating = true;
				}
				logDev( 'State applied', { key: pending.key, reason: pending.reason } );
				try { pending.apply(); } catch ( e ) {}
			}, 650 );

			return true;
		};

		return ctrl;
	}() );

	// Global request coordinator for dashboard polling (state-truth owner).
	window.bbaiDashboardStateRequest = window.bbaiDashboardStateRequest || {
		inFlight: false,
		promise: null,
		controller: null,
		sequence: 0,
		latestAppliedSequence: 0,
		lastStartedAt: 0,
		lastCompletedAt: 0,
		lastReason: null,
		lastSignature: null,
	};

	function bbaiShouldDedupeDashboardRefresh( reason ) {
		var r = String( reason || '' );
		if ( ! window.bbaiDashboardStateRequest.lastCompletedAt ) { return false; }
		if ( Date.now() - window.bbaiDashboardStateRequest.lastCompletedAt >= 3000 ) { return false; }
		return ( 'poll' === r || 'focus' === r || 'bootstrap' === r || 'visibility' === r || 'visibility_resume' === r );
	}

	function bbaiShouldForceDashboardTruthRefresh( reason ) {
		var r = String( reason || '' );
		return (
			'generation_completed' === r ||
			'generate_missing' === r ||
			'inline_generation' === r ||
			'manual_rescan' === r ||
			'user_action' === r ||
			'approve_all' === r ||
			'approve_all_success' === r
		);
	}

	function bbaiDebugTiming( eventName, detail ) {
		if ( ! window.BBAI_LOG || typeof window.BBAI_LOG.info !== 'function' || ! window.console || typeof window.console.debug !== 'function' ) {
			return;
		}
		window.console.debug( '[bbai-dashboard-state]', Object.assign( { event: eventName }, detail || {} ) );
	}

	function bbaiBuildTruthSignature( truth ) {
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );
		var state = getStateTruthState( truth );
		var total = counts ? ( counts.total || ( counts.missing + counts.review + counts.complete + counts.failed ) ) : 0;
		var generationInProgress = ( 'QUEUED' === state || 'PROCESSING' === state );
		return [
			counts ? counts.missing : 0,
			counts ? counts.review : 0,
			counts ? counts.complete : 0,
			total,
			credits ? credits.used : 0,
			credits ? credits.total : 1,
			credits ? credits.remaining : 0,
			getDashboardRenderMode( state ),
			generationInProgress ? 1 : 0,
		].join( '|' );
	}

	window.bbaiRequestDashboardStateRefresh = window.bbaiRequestDashboardStateRefresh || function ( reason ) {
		var req = window.bbaiDashboardStateRequest;
		var r = String( reason || 'poll' );

		if ( req.inFlight && req.promise ) {
			bbaiDebugTiming( 'dashboard_state_fetch_skipped', { reason: r, endpoint: 'state-truth', because: 'in_flight' } );
			return req.promise;
		}

		if ( bbaiShouldDedupeDashboardRefresh( r ) ) {
			bbaiDebugTiming( 'dashboard_state_fetch_skipped', { reason: r, endpoint: 'state-truth', because: 'dedupe_recent' } );
			return Promise.resolve( dashboardPolling.currentTruth );
		}

		if ( bbaiShouldForceDashboardTruthRefresh( r ) ) {
			req.lastCompletedAt = 0;
		}

		req.sequence += 1;
		var sequence = req.sequence;
		req.inFlight = true;
		req.lastStartedAt = Date.now();
		req.lastReason = r;

		var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		req.controller = controller;

		var stateSnapshot = String( dashboardPolling.latestState || hero.getAttribute( 'data-bbai-li-state' ) || '' ).toUpperCase();
		var timeoutMs = ( 'QUEUED' === stateSnapshot || 'PROCESSING' === stateSnapshot ) ? 25000 : 15000;
		var timeoutId = controller
			? window.setTimeout( function () {
				bbaiDebugTiming( 'dashboard_state_fetch_slow', { reason: r, sequence: sequence, durationMs: timeoutMs, endpoint: 'state-truth' } );
			}, timeoutMs )
			: null;

		bbaiDebugTiming( 'dashboard_state_fetch_start', { reason: r, sequence: sequence, durationMs: 0, endpoint: 'state-truth' } );

		req.promise = fetchStateTruth( r, { logLoaded: 'bootstrap' === r, logFailure: false }, {
			controller: controller,
			timeoutMs: timeoutMs,
			timeoutId: timeoutId,
		} ).then( function ( truth ) {
			if ( timeoutId ) { window.clearTimeout( timeoutId ); }
			req.inFlight = false;
			req.controller = null;
			req.lastCompletedAt = Date.now();
			// Only update "last checked" for explicit actions, never background polling.
			if ( r !== 'poll' && r !== 'scheduled' && r !== 'visibility' && r !== 'focus' ) {
				window.bbaiLastSuccessfulDashboardRefreshAt = req.lastCompletedAt;
				// Ensure any visible "Last checked" signal never reflects an ancient scan timestamp.
				syncLastCheckedJustNow();
			}

			bbaiDebugTiming( 'dashboard_state_fetch_complete', {
				reason: r,
				sequence: sequence,
				durationMs: Math.max( 0, req.lastCompletedAt - req.lastStartedAt ),
				endpoint: 'state-truth',
			} );

			if ( sequence <= req.latestAppliedSequence ) {
				bbaiDebugTiming( 'ignore_stale_dashboard_state_response', { reason: r, sequence: sequence, latestAppliedSequence: req.latestAppliedSequence, endpoint: 'state-truth' } );
				return dashboardPolling.currentTruth;
			}

			req.latestAppliedSequence = sequence;

			var sig = bbaiBuildTruthSignature( truth );
			if ( req.lastSignature && req.lastSignature === sig ) {
				bbaiDebugTiming( 'skip_unchanged_dashboard_render', { reason: r, sequence: sequence, endpoint: 'state-truth' } );
				return truth;
			}
			req.lastSignature = sig;

			return truth;
		} ).catch( function ( err ) {
			if ( timeoutId ) { window.clearTimeout( timeoutId ); }
			req.inFlight = false;
			req.controller = null;
			req.lastCompletedAt = Date.now();
			if ( controller && controller.signal && controller.signal.aborted ) {
				bbaiDebugTiming( 'dashboard_state_fetch_timeout', { reason: r, sequence: sequence, durationMs: Math.max( 0, req.lastCompletedAt - req.lastStartedAt ), endpoint: 'state-truth' } );
			}
			throw err;
		} ).finally( function () {
			req.promise = null;
		} );

		return req.promise;
	};

	// Inline config from PHP — avoids dependency on missing compiled JS bundles.
	var BBAI_HERO_CFG = {
		ajaxUrl:    '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		ajaxNonce:  '<?php echo esc_js( wp_create_nonce( 'beepbeepai_nonce' ) ); ?>',
		stateTruthUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard/state-truth' ) ); ?>',
		bootstrapSyncUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard/bootstrap-sync' ) ); ?>',
		dashboardUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard' ) ); ?>',
		restNonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>' || ( window.wpApiSettings && window.wpApiSettings.nonce ) || '',
		missingCount: <?php
			// Pull missing count from hero summary items (same approach as activity strip).
			$bbai_hero_missing_count = 0;
			$bbai_hero_summary_items = is_array( $bbai_li_state['hero']['summary'] ?? null ) ? $bbai_li_state['hero']['summary'] : [];
			foreach ( $bbai_hero_summary_items as $bbai_hero_stat ) {
				$bbai_hero_lbl = strtolower( (string) ( $bbai_hero_stat['label'] ?? '' ) );
				if ( strpos( $bbai_hero_lbl, 'missing' ) !== false ) {
					$bbai_hero_missing_count = (int) str_replace( ',', '', (string) ( $bbai_hero_stat['value'] ?? '0' ) );
					break;
				}
			}
			echo absint( $bbai_hero_missing_count );
			?>,
		wpDebug: '<?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '1' : '0'; ?>',
	};

	// Expose for debugging and console checks (read-only; no secrets).
	window.BBAI_HERO_CFG = BBAI_HERO_CFG;
	applyHeroCreditStateFromAttributes();
	window.setTimeout( applyHeroCreditStateFromAttributes, 0 );
	window.setTimeout( applyHeroCreditStateFromAttributes, 750 );

	var BOOTSTRAP_SYNC_LOCK_TTL_MS = 5 * 60 * 1000;
	var BOOTSTRAP_SYNC_FAILURE_COOLDOWN_MS = 15 * 60 * 1000;
	var BOOTSTRAP_SYNC_SUCCESS_TTL_MS = 2 * 60 * 60 * 1000;

	var ACTION_STATUS = {
		'generate-missing': '<?php echo esc_js( __( 'Generating ALT text…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'resume-job':       '<?php echo esc_js( __( 'Resuming…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'pause-job':        '<?php echo esc_js( __( 'Pausing…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'retry-failed':     '<?php echo esc_js( __( 'Retrying failed images…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'approve-all':      '<?php echo esc_js( __( 'Approving…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'rescan-media-library': '<?php echo esc_js( __( 'Scanning library…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		'add-credits':      null,
	};

	var TEXT = {
		working: '<?php echo esc_js( __( 'Working…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		aMoment: '<?php echo esc_js( __( 'a moment', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generationInProgress: '<?php echo esc_js( __( ' · Generation in progress…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		checkingQueue: '<?php echo esc_js( __( 'Checking queue…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedJustNow: '<?php echo esc_js( __( 'Last checked just now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedSecond: '<?php echo esc_js( __( 'Last checked 1 second ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedSeconds: '<?php echo esc_js( __( 'Last checked %s seconds ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedMinute: '<?php echo esc_js( __( 'Last checked 1 minute ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedMinutes: '<?php echo esc_js( __( 'Last checked %s minutes ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedHour: '<?php echo esc_js( __( 'Last checked 1 hour ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedHours: '<?php echo esc_js( __( 'Last checked %s hours ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastRun: '<?php echo esc_js( __( 'Last batch completed %s', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedBadge: '<?php echo esc_js( __( 'Ready to generate', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingBadge: '<?php echo esc_js( __( 'Processing', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		actionNeededBadge: '<?php echo esc_js( __( 'Action needed', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		recommendedBadge: '<?php echo esc_js( __( 'Recommended', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewReadyBadge: '<?php echo esc_js( __( 'Review ready', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsNeededBadge: '<?php echo esc_js( __( 'Credits needed', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allOptimisedBadge: '<?php echo esc_js( __( 'All optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedSupport: '<?php echo esc_js( __( 'Generate ALT text now to make these images accessible and SEO-ready.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedHeadlineSingular: '<?php echo esc_js( _n( '%s image is ready for ALT text', '%s images are ready for ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedHeadlinePlural: '<?php echo esc_js( _n( '%s image is ready for ALT text', '%s images are ready for ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedPrimarySingular: '<?php echo esc_js( _n( 'Generate ALT text for %s image', 'Generate ALT text for %s images', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedPrimaryPlural: '<?php echo esc_js( _n( 'Generate ALT text for %s image', 'Generate ALT text for %s images', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedSecondary: '<?php echo esc_js( __( 'Preview images', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedReadySingular: '<?php echo esc_js( _n( '%s ready to generate', '%s ready to generate', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedReadyPlural: '<?php echo esc_js( _n( '%s ready to generate', '%s ready to generate', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingHeadline: '<?php echo esc_js( __( 'Generating — %1$s of %2$s images done', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingSupportDone: '<?php echo esc_js( __( '%1$s optimised so far · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingSupportEta: '<?php echo esc_js( __( '%1$s remaining · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingRemainingSingular: '<?php echo esc_js( _n( '%s image remaining.', '%s images remaining.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingRemainingPlural: '<?php echo esc_js( _n( '%s image remaining.', '%s images remaining.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingAria: '<?php echo esc_js( __( 'Processing: %d%% complete', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		pause: '<?php echo esc_js( __( 'Pause', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		pausing: '<?php echo esc_js( __( 'Pausing…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		openAltLibrary: '<?php echo esc_js( __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		uploadMoreImages: '<?php echo esc_js( __( 'Add new images →', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		approveAll: '<?php echo esc_js( __( 'Approve all', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		openReviewQueue: '<?php echo esc_js( __( 'Open review queue', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewIndividually: '<?php echo esc_js( __( 'Review individually →', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generateMissingAlt: '<?php echo esc_js( __( 'Generate ALT Text Now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generateAltForSingular: '<?php echo esc_js( __( 'Generate ALT Text Now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generateAltForPlural: '<?php echo esc_js( __( 'Generate ALT Text Now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		postSignupFirstGeneration: '<?php echo esc_js( __( 'You have 50 free credits. Generate your first ALT text now.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		addCredits: '<?php echo esc_js( __( 'Add credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		rescanLibrary: '<?php echo esc_js( __( 'Re-scan', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingLabel: '<?php echo esc_js( __( 'Missing', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewLabel: '<?php echo esc_js( __( 'To review', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsLabel: '<?php echo esc_js( __( 'Credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		optimizedLabel: '<?php echo esc_js( __( 'Optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsLeftLabel: '<?php echo esc_js( __( 'Credits left', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsRemainingLabel: '<?php echo esc_js( __( 'Credits remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditUsedThisMonth: '<?php echo esc_js( __( '%1$s / %2$s used this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditFreeSingular: '<?php echo esc_js( __( '%s monthly credit remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditFreePlural: '<?php echo esc_js( __( '%s monthly credits remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditPaidNoneLeft: '<?php echo esc_js( __( 'No credits remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditPaidOneMonth: '<?php echo esc_js( __( '1 credit remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditPaidPluralMonth: '<?php echo esc_js( __( '%s credits remaining', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditUsageLine: '<?php echo esc_js( __( 'Used when generating or improving ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditHelperExhausted: '<?php echo esc_js( __( 'Add credits to continue generating ALT text.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditNoneRemainingThisMonth: '<?php echo esc_js( __( 'No credits remaining this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditRemainingThisMonth: '<?php echo esc_js( __( '%s remaining this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditOnlyLeftThisMonth: '<?php echo esc_js( __( 'Only %s credits left this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditEnoughForBatch: '<?php echo esc_js( __( '✔ Enough credits to finish this batch', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		heroCreditCanGenerateMore: '<?php echo esc_js( __( 'You can generate %s more images', 'beepbeep-ai-alt-text-generator' ) ); ?>',
			heroCreditUpgradeGrowth: '<?php echo esc_js( __( 'Enable Autopilot (up to %s images/month)', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditLowRunningSuffix: '<?php echo esc_js( __( 'Running low — upgrade or top up before you run out.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditCtxManual: '<?php echo esc_js( __( 'Manual generation uses credits.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditCtxReview: '<?php echo esc_js( __( 'Reviewing does not use credits.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditCtxAllClear: '<?php echo esc_js( __( 'Credits are ready for your next uploads.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditAriaMonthly: '<?php echo esc_js( __( '%1$s of %2$s credits used this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditAriaPaid: '<?php echo esc_js( __( '%1$s of %2$s plan credits used this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsResetMonthly: '<?php echo esc_js( __( 'Resets monthly', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingStrip: '<?php echo esc_js( __( 'Generation active', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processedCountSingular: '<?php echo esc_js( _n( '%s processed', '%s processed', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processedCountPlural: '<?php echo esc_js( _n( '%s processed', '%s processed', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		remainingCountSingular: '<?php echo esc_js( _n( '%s remaining', '%s remaining', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		remainingCountPlural: '<?php echo esc_js( _n( '%s remaining', '%s remaining', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingAltSingular: '<?php echo esc_js( _n( '%s image needs ALT text', '%s images need ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingAltPlural: '<?php echo esc_js( _n( '%s image needs ALT text', '%s images need ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingHeadlineSingular: '<?php echo esc_js( _n( '%s image is missing ALT text', '%s images are missing ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingHeadlinePlural: '<?php echo esc_js( _n( '%s image is missing ALT text', '%s images are missing ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		mixedSupport: '<?php echo esc_js( __( 'Generate ALT text first, then review the suggested descriptions before they go live.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingSupport: '<?php echo esc_js( __( 'Generate the missing ALT text now to keep your library accessible, searchable, and up to date.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingSupportProgress: '<?php echo esc_js( __( '%1$s images already optimised — generate the remaining %2$s to complete your library.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyReviewSingular: '<?php echo esc_js( _n( '%s ready for review', '%s ready for review', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyReviewPlural: '<?php echo esc_js( _n( '%s ready for review', '%s ready for review', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewHeadlineSingular: '<?php echo esc_js( _n( '%s image is ready for review', '%s images are ready for review', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewHeadlinePlural: '<?php echo esc_js( _n( '%s image is ready for review', '%s images are ready for review', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupport: '<?php echo esc_js( __( 'ALT text is ready for a quick review before it goes live.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupportSingular: '<?php echo esc_js( _n( 'You have %s image waiting for approval — approve all or open the queue to check each one.', 'You have %s images waiting for approval — approve all or open the queue to check each one.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupportPlural: '<?php echo esc_js( _n( 'You have %s image waiting for approval — approve all or open the queue to check each one.', 'You have %s images waiting for approval — approve all or open the queue to check each one.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allImagesOptimised: '<?php echo esc_js( __( 'All images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		libraryUpToDate: '<?php echo esc_js( __( 'Library is up to date', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearHeadline: '<?php echo esc_js( __( 'Your media library is fully optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearSupport: '<?php echo esc_js( __( 'Everything is accessible, SEO-ready, and performing at its best.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyForNewUploads: '<?php echo esc_js( __( 'Everything is optimised and SEO-ready', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditLeftSingular: '<?php echo esc_js( _n( '%s credit left', '%s credits left', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditLeftPlural: '<?php echo esc_js( _n( '%s credit left', '%s credits left', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		outOfCredits: '<?php echo esc_js( __( 'Out of credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		addMoreToContinue: '<?php echo esc_js( __( 'Add more to continue generating', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		quotaHeadlineSingular: '<?php echo esc_js( _n( '%s image still needs ALT text', '%s images still need ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		quotaHeadlinePlural: '<?php echo esc_js( _n( '%s image still needs ALT text', '%s images still need ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		quotaSupportSingular: '<?php echo esc_js( _n( 'Add credits to keep going — %s image still needs ALT text.', 'Add credits to keep going — %s images still need ALT text.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		quotaSupportPlural: '<?php echo esc_js( _n( 'Add credits to keep going — %s image still needs ALT text.', 'Add credits to keep going — %s images still need ALT text.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		quotaSupportProgress: '<?php echo esc_js( __( 'You\'ve already optimised %1$s images — add credits to finish the remaining %2$s.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generationPaused: '<?php echo esc_js( __( 'Generation paused', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		checkSettingsToContinue: '<?php echo esc_js( __( 'Check settings to continue', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		libraryReadyToOptimise: '<?php echo esc_js( __( 'Library ready to optimise', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactQueued: '<?php echo esc_js( __( 'Ready when you are — start generating to move images into review.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactProcessing: '<?php echo esc_js( __( 'BeepBeep is working through your library now.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactAllClearSingle: '<?php echo esc_js( _n( 'You\'ve improved accessibility on %s image.', 'You\'ve improved accessibility on %s images.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactAllClearPlural: '<?php echo esc_js( _n( 'You\'ve improved accessibility on %s image.', 'You\'ve improved accessibility on %s images.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactAllClearFallback: '<?php echo esc_js( __( 'Your library is fully optimised for accessibility and search.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactSoFarSingle: '<?php echo esc_js( _n( 'You\'ve improved accessibility on %s image so far.', 'You\'ve improved accessibility on %s images so far.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		impactSoFarPlural: '<?php echo esc_js( _n( 'You\'ve improved accessibility on %s image so far.', 'You\'ve improved accessibility on %s images so far.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedImageSingular: '<?php echo esc_js( _n( 'queued image', 'queued images', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedImagePlural: '<?php echo esc_js( _n( 'queued image', 'queued images', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		imageLeftSingular: '<?php echo esc_js( _n( 'image left', 'images left', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		imageLeftPlural: '<?php echo esc_js( _n( 'image left', 'images left', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		startFailed: '<?php echo esc_js( __( 'Generation could not start. Please refresh and try again.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		startDidNotStart: '<?php echo esc_js( __( 'Generation did not start. Please try again.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		sessionExpired: '<?php echo esc_js( __( 'Your session expired. Please refresh and try again.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		noMissingFoundRescan: '<?php echo esc_js( __( 'No missing images were found. Please re-scan your library.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		preparingBulkRun: '<?php echo esc_js( __( 'Preparing bulk run...', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		etaSeconds: '<?php echo esc_js( __( '%ds', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		etaMinutes: '<?php echo esc_js( __( '%dm', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		batchReadyForReview: '<?php echo esc_js( __( 'Batch complete. Ready for review.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewComplete: '<?php echo esc_js( __( 'Review complete. Library is all clear.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		approveUpdating: '<?php echo esc_js( __( 'Updating review queue…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightMinsEst: '<?php echo esc_js( __( '%s min (est.)', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightImagesOptimisedSingular: '<?php echo esc_js( __( '%s image optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightImagesOptimisedPlural: '<?php echo esc_js( __( '%s images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightAccessibleSuffix: '<?php echo esc_js( __( 'of images accessible', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightMinsSaved: '<?php echo esc_js( __( '~%s mins saved', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightMinsSavedZero: '<?php echo esc_js( __( '~0 mins saved', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightCoverageMeta: '<?php echo esc_js( __( '%1$s of %2$s images covered', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightCoverageMetaZero: '<?php echo esc_js( __( 'Run a scan to measure coverage.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightSeoMetaSingular: '<?php echo esc_js( __( '%s search-ready image', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		insightSeoMetaPlural: '<?php echo esc_js( __( '%s search-ready images', 'beepbeep-ai-alt-text-generator' ) ); ?>',
	};

	var PROMPT_TEXT = {
		firstSuccessTitle: '<?php echo esc_js( __( 'Nice — your images now have ALT text 🎉', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		firstSuccessCopy: '<?php echo esc_js( __( 'Search engines and screen readers can now understand them.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewAltText: '<?php echo esc_js( __( 'Review ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generateMore: '<?php echo esc_js( __( 'Generate more', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		approveAll: '<?php echo esc_js( __( 'Approve all ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		editFew: '<?php echo esc_js( __( 'Edit a few manually', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		done: '<?php echo esc_js( __( 'Done', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		upgradeTitle: '<?php echo esc_js( __( 'You’ve optimised %s images', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		upgradeCopy: '<?php echo esc_js( __( 'Upgrade to automate this for future uploads.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		enableAutomation: '<?php echo esc_js( __( 'Enable automatic ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditUsed: '<?php echo esc_js( __( 'You’ve used %1$s of %2$s credits this month', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditLowTitle: '<?php echo esc_js( __( 'You’re running low on credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditLowCopy: '<?php echo esc_js( __( 'Upgrade to continue generating ALT text.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		upgradePlan: '<?php echo esc_js( __( 'Upgrade plan', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		emptyTitle: '<?php echo esc_js( __( 'Add images to your library to start generating ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		openMedia: '<?php echo esc_js( __( 'Open media library', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		learnSeo: '<?php echo esc_js( __( 'Learn how ALT text improves SEO', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewValueTitle: '<?php echo esc_js( __( 'Reviewing helps improve your SEO accuracy', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		scoreImproved: '<?php echo esc_js( __( 'Score improved from 45 → 82', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		nextStepTitle: '<?php echo esc_js( __( 'ALT text generated. Choose your next step.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
	};

	var ACTIVATION_PROMPT_KEYS = {
		firstSuccessSeen: 'bbai_activation_first_success_seen',
		creditWarning60Shown: 'bbai_activation_credit_warning_60_shown',
		creditWarning80Shown: 'bbai_activation_credit_warning_80_shown',
		upgradeDismissed: 'bbai_activation_upgrade_dismissed',
		upgradeShown: 'bbai_activation_upgrade_shown',
	};

	var lastStateTruthFailureLogAt = 0;

	function logDashboardUi( eventName, context ) {
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[dashboard-ui] ' + eventName, context || {} );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[dashboard-ui] ' + eventName, context || {} );
		}
	}

	function logRender( eventName, context ) {
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[bbai-render] ' + eventName, context || {} );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-render] ' + eventName, context || {} );
		}
	}

	function logDashboardUiFailure( context, error ) {
		var now = Date.now();
		if ( now - lastStateTruthFailureLogAt < 60000 ) {
			return;
		}
		lastStateTruthFailureLogAt = now;
		logDashboardUi( 'state_truth_failed', {
			context: context || '',
			message: error && error.message ? error.message : String( error || '' ),
		} );
	}

	function getGenerationQuotaDebugContext( trigger ) {
		var root = getDashboardRoot();
		var truth = dashboardPolling.currentTruth || null;
		var credits = truth ? getTruthCredits( truth ) : null;
		var button = trigger || ( hero ? hero.querySelector( '[data-bbai-li-primary-cta]' ) : null );
		var authState = root && root.getAttribute ? String( root.getAttribute( 'data-bbai-auth-state' ) || '' ) : '';
		var quotaType = root && root.getAttribute ? String( root.getAttribute( 'data-bbai-quota-type' ) || '' ) : '';
		var quotaSource = root && root.getAttribute ? String( root.getAttribute( 'data-bbai-quota-source' ) || '' ) : '';

		if ( truth && truth.credits && typeof truth.credits === 'object' ) {
			authState = authState || String( truth.credits.auth_state || truth.credits.authState || '' );
			quotaType = quotaType || String( truth.credits.quota_type || truth.credits.quotaType || '' );
			quotaSource = quotaSource || String( truth.credits.quota_source_displayed_to_user || truth.credits.quotaSourceDisplayedToUser || '' );
		}

		return {
			auth_state: authState || 'unknown',
			quota_type: quotaType || 'unknown',
			quota_source_displayed_to_user: quotaSource || ( authState === 'anonymous' || quotaType === 'trial' ? 'anonymous_trial' : 'authenticated_account' ),
			generation_button_enabled: !! ( button && ! button.disabled && button.getAttribute( 'aria-disabled' ) !== 'true' ),
			credits_used: credits ? credits.used : parseDashboardIntAttr( root, 'data-bbai-credits-used', 0 ),
			credits_total: credits ? credits.total : parseDashboardIntAttr( root, 'data-bbai-credits-total', 0 ),
			credits_remaining: credits ? credits.remaining : parseDashboardIntAttr( root, 'data-bbai-credits-remaining', 0 ),
		};
	}

	function normalizeGenerationAnalyticsSource( source ) {
		source = String( source || 'dashboard' );
		if ( source === 'library' || source === 'alt-library' || source === 'alt_library' ) {
			return 'alt_library';
		}
		if ( source === 'footer' || source === 'footer-banner' ) {
			return 'footer_banner';
		}
		if ( source === 'dashboard' || source === 'modal' || source === 'footer_banner' ) {
			return source;
		}
		return 'dashboard';
	}

	function parseDashboardIntAttr( node, attr, fallback ) {
		var raw = node && node.getAttribute ? node.getAttribute( attr ) : '';
		var parsed = parseInt( raw, 10 );
		return isNaN( parsed ) ? ( fallback || 0 ) : Math.max( 0, parsed );
	}

	function buildGenerationAnalyticsPayload( source, extra ) {
		var root = getDashboardRoot();
		var truth = dashboardPolling.currentTruth || null;
		var counts = truth ? getTruthCounts( truth ) : null;
		var credits = truth ? getTruthCredits( truth ) : null;
		var site = truth && truth.site && typeof truth.site === 'object' ? truth.site : {};
		var payload = Object.assign( {}, getGenerationQuotaDebugContext(), {
			source: normalizeGenerationAnalyticsSource( source || 'dashboard' ),
			requested_count: counts ? Math.max( 0, counts.missing || 0 ) : parseDashboardIntAttr( root, 'data-bbai-missing-count', BBAI_HERO_CFG.missingCount || 0 ),
			missing_count: counts ? Math.max( 0, counts.missing || 0 ) : parseDashboardIntAttr( root, 'data-bbai-missing-count', BBAI_HERO_CFG.missingCount || 0 ),
			review_count: counts ? Math.max( 0, counts.review || 0 ) : parseDashboardIntAttr( root, 'data-bbai-weak-count', 0 ),
			credits_remaining: credits ? Math.max( 0, credits.remaining || 0 ) : parseDashboardIntAttr( root, 'data-bbai-credits-remaining', 0 ),
			plan: credits && credits.plan ? String( credits.plan ) : ( root ? String( root.getAttribute( 'data-bbai-plan-label' ) || '' ) : '' ),
			is_logged_in: !! ( root && root.getAttribute( 'data-bbai-has-connected-account' ) === '1' ),
			site_hash_present: !! ( site.site_hash || site.siteHash || site.hash ),
			ajax_action: '',
			error_code: '',
			error_message: '',
		} );
		if ( extra && typeof extra === 'object' ) {
			Object.keys( extra ).forEach( function ( key ) {
				if ( extra[ key ] !== undefined ) {
					payload[ key ] = extra[ key ];
				}
			} );
		}
		return payload;
	}

	function emitGenerationAnalytics( eventName, source, extra ) {
		var payload = buildGenerationAnalyticsPayload( source || 'dashboard', extra || {} );
		try {
			document.dispatchEvent( new CustomEvent( 'bbai:analytics', {
				detail: Object.assign( {
					event: eventName,
					timestamp: Date.now(),
				}, payload ),
			} ) );
		} catch ( error ) {
			// Ignore analytics dispatch failures; generation flow must continue.
		}
	}

	function getGenerationJobIdFromPayload( data ) {
		if ( ! data || typeof data !== 'object' ) {
			return '';
		}
		if ( data.job_id || data.jobId ) {
			return String( data.job_id || data.jobId );
		}
		if ( data.job && typeof data.job === 'object' && ( data.job.id || data.job.job_id || data.job.jobId ) ) {
			return String( data.job.id || data.job.job_id || data.job.jobId );
		}
		if ( data.job_state && typeof data.job_state === 'object' && ( data.job_state.id || data.job_state.job_id || data.job_state.jobId ) ) {
			return String( data.job_state.id || data.job_state.job_id || data.job_state.jobId );
		}
		return '';
	}

	function logGenerationStartFailureForDev( detail ) {
		if ( BBAI_HERO_CFG.wpDebug !== '1' ) {
			return;
		}
		if ( window.console && typeof window.console.error === 'function' ) {
			window.console.error( '[dashboard-generation] generation_start_failed', detail || {} );
		}
	}

	function recoverGenerationStartFailure( trigger, message, detail ) {
		var failureDetail = detail || {};
		clearHeroGenerationWatchdog();
		releaseInlineGenerationLock();
		clearOptimisticAction();
		dashboardPolling.optimisticAction = '';
		if ( trigger ) {
			setBusy( trigger, false );
		}
		showStatusLine( message || TEXT.startFailed, 'error' );
		logGenerationStartFailureForDev( failureDetail );
		emitGenerationAnalytics( 'generation_start_failed', failureDetail.source || 'dashboard', {
			requested_count: failureDetail.requested_count,
			ajax_action: failureDetail.ajax_action || '',
			error_code: failureDetail.error_code || 'generation_start_failed',
			error_message: failureDetail.error_message || message || TEXT.startFailed,
			job_id: failureDetail.job_id || '',
		} );
	}

	function logBootstrapSync( eventName, context ) {
		var serialized = '';
		try {
			serialized = JSON.stringify( context || {} );
		} catch ( error ) {
			serialized = '{"serialization_error":true}';
		}
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[bbai-bootstrap] ' + eventName + ' ' + serialized );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-bootstrap] ' + eventName + ' ' + serialized );
		}
	}

	function logCredits( eventName, context ) {
		var serialized = '';
		try {
			serialized = JSON.stringify( context || {} );
		} catch ( error ) {
			serialized = '{"serialization_error":true}';
		}
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[bbai-credits] ' + eventName + ' ' + serialized );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-credits] ' + eventName + ' ' + serialized );
		}
	}

	function logStatusStrip( eventName, context ) {
		var serialized = '';
		try {
			serialized = JSON.stringify( context || {} );
		} catch ( error ) {
			serialized = '{"serialization_error":true}';
		}
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[bbai-status-strip] ' + eventName + ' ' + serialized );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-status-strip] ' + eventName + ' ' + serialized );
		}
	}

	function logCounts( eventName, context ) {
		var serialized = '';
		try {
			serialized = JSON.stringify( context || {} );
		} catch ( error ) {
			serialized = '{"serialization_error":true}';
		}
		if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
			window.BBAI_LOG.log( '[bbai-counts] ' + eventName + ' ' + serialized );
		}
		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-counts] ' + eventName + ' ' + serialized );
		}
	}

	function getStatusStripSource( reason ) {
		var normalized = String( reason || '' ).toLowerCase();

		if ( ! normalized ) {
			return 'legacy_init';
		}
		if ( -1 !== normalized.indexOf( 'bootstrap' ) ) {
			return 'bootstrap_refresh';
		}
		if (
			-1 !== normalized.indexOf( 'startup' ) ||
			-1 !== normalized.indexOf( 'scheduled' ) ||
			-1 !== normalized.indexOf( 'visibility_resume' ) ||
			-1 !== normalized.indexOf( 'state_' ) ||
			-1 !== normalized.indexOf( 'poll' )
		) {
			return 'polling_tick';
		}
		if ( -1 !== normalized.indexOf( 'fallback' ) ) {
			return 'fallback';
		}
		if ( -1 !== normalized.indexOf( 'legacy' ) || -1 !== normalized.indexOf( 'init' ) ) {
			return 'legacy_init';
		}

		return 'truth';
	}

	function buildStatusStripMeta( reason, caller ) {
		var normalizedReason = String( reason || '' );
		return {
			reason: normalizedReason,
			caller: String( caller || '' ),
			source: getStatusStripSource( normalizedReason ),
		};
	}

	function buildStatusStripLogContext( truth, meta, text, surface, action ) {
		var payload = normalizeStateTruthPayload( truth || dashboardPolling.currentTruth || {} );
		var counts = getTruthCounts( payload );
		var resolvedMeta = meta && typeof meta === 'object' ? meta : {};

		return {
			surface: surface,
			action: action || 'write',
			state: getStateTruthState( payload ) || ( hero.getAttribute( 'data-bbai-li-state' ) || '' ),
			counts_to_review: counts.review,
			counts_missing: counts.missing,
			text_written: String( text || '' ),
			reason: String( resolvedMeta.reason || '' ),
			caller: String( resolvedMeta.caller || '' ),
			source: String( resolvedMeta.source || getStatusStripSource( resolvedMeta.reason || '' ) ),
		};
	}

	function getDashboardRoot() {
		return document.querySelector( '[data-bbai-dashboard-root="1"]' );
	}

	function getDashboardRootMissingCount() {
		var root = getDashboardRoot();
		if ( ! root ) {
			return 0;
		}
		return Math.max( 0, parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) || 0 );
	}

	function getLoggedInDashboardRoot() {
		return document.querySelector( '[data-bbai-logged-in-dashboard]' );
	}

	function getPrimaryCta() {
		return hero.querySelector( '[data-bbai-li-primary-cta]' );
	}

	function getSecondaryCta() {
		var state = hero.getAttribute( 'data-bbai-li-state' ) || '';
		var inline = hero.querySelector( '[data-bbai-li-secondary-inline-cta="1"]' );
		var rescan;
		if ( 'NEEDS_REVIEW' === state && inline && ! inline.hidden && inline.getAttribute( 'aria-hidden' ) !== 'true' ) {
			return inline;
		}
		rescan = hero.querySelector( '[data-bbai-li-all-clear-rescan="1"]' );
		if ( 'ALL_CLEAR' === state && rescan && ! rescan.hidden && rescan.getAttribute( 'aria-hidden' ) !== 'true' ) {
			return rescan;
		}
		return hero.querySelector( '[data-bbai-li-secondary-cta]' );
	}

	function replaceTokens( template, replacements ) {
		var text = String( template || '' );
		Object.keys( replacements || {} ).forEach( function ( key ) {
			text = text.split( key ).join( String( replacements[ key ] ) );
		} );
		return text;
	}

	function formatCount( value ) {
		var safe = parseInt( value, 10 );
		if ( isNaN( safe ) ) {
			safe = 0;
		}
		return safe.toLocaleString();
	}

	function formatHeroCreditDashboardLabel( rem ) {
		var dr = getDashboardRoot();
		var lim = dr ? parseInt( dr.getAttribute( 'data-bbai-credits-total' ), 10 ) : NaN;
		var used = dr ? parseInt( dr.getAttribute( 'data-bbai-credits-used' ), 10 ) : NaN;
		var r = Math.max( 0, parseInt( rem, 10 ) || 0 );
		lim = Math.max( 1, isNaN( lim ) ? 1 : lim );
		if ( isNaN( used ) || used < 0 ) {
			used = Math.max( 0, lim - r );
		}
		return replaceTokens( TEXT.heroCreditUsedThisMonth || '', {
			'%1$s': formatCount( used ),
			'%2$s': formatCount( lim ),
		} );
	}

	function buildHeroCreditContextLine( rem, liState ) {
		var r = Math.max( 0, parseInt( rem, 10 ) || 0 );
		if ( r <= 0 ) {
			return TEXT.heroCreditNoneRemainingThisMonth || '';
		}
		if ( r < 20 ) {
			return replaceTokens( TEXT.heroCreditOnlyLeftThisMonth || '', {
				'%s': formatCount( r ),
			} );
		}
		return replaceTokens( TEXT.heroCreditRemainingThisMonth || '', {
			'%s': formatCount( r ),
		} );
	}

	function buildHeroCreditBatchLine( rem, missingCount ) {
		var r = Math.max( 0, parseInt( rem, 10 ) || 0 );
		var missing = Math.max( 0, parseInt( missingCount, 10 ) || 0 );
		if ( missing <= 0 ) {
			return '';
		}
		if ( r >= missing ) {
			return TEXT.heroCreditEnoughForBatch || '';
		}
		return replaceTokens( TEXT.heroCreditCanGenerateMore || '', {
			'%s': formatCount( r ),
		} );
	}

	function getHeroGrowthCreditLimit( wrap, fallbackTotal ) {
		var raw = wrap ? parseInt( wrap.getAttribute( 'data-bbai-hero-growth-credit-limit' ) || '', 10 ) : NaN;
		if ( ! isNaN( raw ) && raw > 0 ) {
			return raw;
		}
		return Math.max( 1, parseInt( fallbackTotal, 10 ) || 1 );
	}

	function buildHeroCreditGrowthLine( wrap, used, total, remaining ) {
		var growthLimit = getHeroGrowthCreditLimit( wrap, total );
		return replaceTokens( TEXT.heroCreditUpgradeGrowth || '', {
			'%s': formatCount( growthLimit ),
		} );
	}

	function formatHeroCreditBarAria( rem, lim ) {
		var l = Math.max( 1, parseInt( lim, 10 ) || 1 );
		var r = Math.max( 0, parseInt( rem, 10 ) || 0 );
		var used = Math.max( 0, l - r );
		var dr = getDashboardRoot();
		var du = dr ? parseInt( dr.getAttribute( 'data-bbai-credits-used' ), 10 ) : NaN;
		if ( ! isNaN( du ) && du >= 0 ) {
			used = du;
		}
		var isFree = dr && dr.getAttribute( 'data-bbai-is-premium' ) !== '1';
		var t = isFree ? TEXT.creditAriaMonthly : TEXT.creditAriaPaid;
		return replaceTokens( t, {
			'%1$s': formatCount( used ),
			'%2$s': formatCount( l ),
		} );
	}

	function firstDefined() {
		var i;
		for ( i = 0; i < arguments.length; i += 1 ) {
			if ( undefined !== arguments[ i ] && null !== arguments[ i ] ) {
				return arguments[ i ];
			}
		}
		return undefined;
	}

	function normalizeCounts( counts ) {
		var source = counts && typeof counts === 'object' ? counts : {};
		var missing = parseInt( firstDefined( source.missing, source.missing_alt, source.missingAlt, source.images_missing_alt, source.imagesMissingAlt, source.missing_count, source.missingCount, 0 ), 10 );
		var review = parseInt( firstDefined( source.review, source.to_review, source.toReview, source.needs_review, source.needsReview, source.needs_review_count, source.needsReviewCount, source.ready_for_review, source.readyForReview, source.ready_for_review_count, source.readyForReviewCount, source.weak, source.weak_count, source.weakCount, 0 ), 10 );
		var complete = parseInt( firstDefined( source.optimized, source.complete, source.optimised, source.optimized_count, source.optimizedCount, source.optimised_count, source.optimisedCount, 0 ), 10 );
		var failed = parseInt( firstDefined( source.failed, 0 ), 10 );
		var total = parseInt( firstDefined( source.total, source.total_images, source.totalImages, source.total_count, source.totalCount, missing + review + complete + failed ), 10 );

		missing = Math.max( 0, isNaN( missing ) ? 0 : missing );
		review = Math.max( 0, isNaN( review ) ? 0 : review );
		complete = Math.max( 0, isNaN( complete ) ? 0 : complete );
		failed = Math.max( 0, isNaN( failed ) ? 0 : failed );
		total = Math.max( 0, isNaN( total ) ? missing + review + complete + failed : total );

		return {
			missing: missing,
			review: review,
			complete: complete,
			failed: failed,
			total: total,
		};
	}

	function getDomCounts() {
		var root = getDashboardRoot();
		if ( ! root ) {
			return normalizeCounts( {} );
		}
		return normalizeCounts( {
			missing: root.getAttribute( 'data-bbai-missing-count' ),
			review: root.getAttribute( 'data-bbai-weak-count' ),
			optimized: root.getAttribute( 'data-bbai-optimized-count' ),
			total: root.getAttribute( 'data-bbai-total-count' ),
		} );
	}

	function countsEqual( a, b ) {
		var left = normalizeCounts( a );
		var right = normalizeCounts( b );
		return left.missing === right.missing &&
			left.review === right.review &&
			left.complete === right.complete &&
			left.failed === right.failed &&
			left.total === right.total;
	}

	function hasCountValues( counts ) {
		var normalized = normalizeCounts( counts );
		return normalized.missing > 0 ||
			normalized.review > 0 ||
			normalized.complete > 0 ||
			normalized.failed > 0 ||
			normalized.total > 0;
	}

	function getCurrentCountsHash() {
		var root = getDashboardRoot();
		var loggedInRoot = getLoggedInDashboardRoot();
		return root && root.getAttribute( 'data-bbai-counts-hash' )
			? String( root.getAttribute( 'data-bbai-counts-hash' ) || '' )
			: ( loggedInRoot ? String( loggedInRoot.getAttribute( 'data-bbai-counts-hash' ) || '' ) : '' );
	}

	function formatSingularPlural( count, singular, plural ) {
		return replaceTokens( 1 === count ? singular : plural, { '%s': formatCount( count ) } );
	}

	function getStateTruthUrl() {
		var root = document.querySelector( '[data-bbai-li-state-truth-url]' );
		if ( root ) {
			return root.getAttribute( 'data-bbai-li-state-truth-url' ) || BBAI_HERO_CFG.stateTruthUrl || BBAI_HERO_CFG.dashboardUrl;
		}
		return BBAI_HERO_CFG.stateTruthUrl || BBAI_HERO_CFG.dashboardUrl;
	}

	function normalizeStateTruthPayload( payload ) {
		if ( payload && payload.data && typeof payload.data === 'object' ) {
			return payload.data;
		}
		return payload && typeof payload === 'object' ? payload : null;
	}

	function normalizeResolvedDashboardPayload( payload ) {
		if ( payload && payload.data && typeof payload.data === 'object' ) {
			return payload.data;
		}
		return payload && typeof payload === 'object' ? payload : null;
	}

	function normalizeBootstrapSyncPayload( payload ) {
		if ( payload && payload.data && typeof payload.data === 'object' ) {
			return payload.data;
		}
		return payload && typeof payload === 'object' ? payload : null;
	}

	function applyStatePriority( rawState, payload ) {
		var state = String( rawState || '' ).toUpperCase();
		var counts = getTruthCounts( payload );
		var credits = getTruthCredits( payload );
		var job = getTruthJob( payload );

		if ( credits.remaining <= 0 && counts.missing > 0 ) {
			return 'QUOTA_EXHAUSTED';
		}

		if ( job && job.active && 'queued' === job.status ) {
			return 'QUEUED';
		}

		if ( job && job.active && 'processing' === job.status ) {
			return 'PROCESSING';
		}

		if ( counts.missing > 0 && counts.review > 0 ) {
			return 'MIXED_ATTENTION';
		}

		if ( counts.missing > 0 ) {
			return 'MISSING_ALT';
		}

		if ( counts.review > 0 ) {
			return 'NEEDS_REVIEW';
		}

		if ( counts.failed > 0 ) {
			return 'ERROR';
		}

		// Stale SaaS state labels must not override reconciled counts (see Logged_In_Dashboard_Resolver::compute_state_id).
		if (
			( 'NEEDS_REVIEW' === state && counts.review <= 0 ) ||
			( 'MISSING_ALT' === state && counts.missing <= 0 ) ||
			( 'MIXED_ATTENTION' === state && ( counts.missing <= 0 || counts.review <= 0 ) )
		) {
			state = '';
		}

		if ( state ) {
			return state;
		}

		if (
			counts.total <= 0 &&
			counts.missing <= 0 &&
			counts.review <= 0 &&
			counts.complete <= 0
		) {
			return 'NO_IMAGES';
		}

		return 'ALL_CLEAR';
	}

	function getStateTruthState( payload ) {
		var raw = payload && payload.state ? String( payload.state ).toUpperCase() : '';
		var selected = applyStatePriority( raw, payload );

		if ( window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-state-priority]', {
				raw_state: raw,
				selected_state: selected,
				counts: getTruthCounts( payload ),
				credits: getTruthCredits( payload )
			} );
		}

		return selected;
	}

	function getTruthSite( payload ) {
		return payload && payload.site && typeof payload.site === 'object' ? payload.site : {};
	}

	function getTruthResolutionSources( payload ) {
		if ( payload && payload.resolution_sources && typeof payload.resolution_sources === 'object' ) {
			return payload.resolution_sources;
		}
		if ( payload && payload.resolutionSources && typeof payload.resolutionSources === 'object' ) {
			return payload.resolutionSources;
		}
		if ( payload && payload.sources && typeof payload.sources === 'object' ) {
			return payload.sources;
		}
		return {};
	}

	function getTruthRawResolution( payload ) {
		if ( payload && payload.resolution && typeof payload.resolution === 'object' ) {
			return payload.resolution;
		}

		return {};
	}

	function getTruthSiteHash( payload ) {
		var site = getTruthSite( payload );
		return String( firstDefined( site.site_hash, site.siteHash, site.site_id, site.siteId, '' ) || '' );
	}

	function getTruthRawCounts( payload ) {
		return payload && payload.counts && typeof payload.counts === 'object' ? payload.counts : {};
	}

	function getTruthCountsHash( payload ) {
		var direct = payload ? firstDefined( payload.counts_hash, payload.countsHash, '' ) : '';
		var embedded = '';
		var stc;
		if ( payload && payload.meta && payload.meta.state_truth ) {
			stc = normalizeStateTruthPayload( payload.meta.state_truth );
			embedded = stc ? firstDefined( stc.counts_hash, stc.countsHash, '' ) : '';
		}
		return String( embedded || direct || '' );
	}

	function getTruthRawCredits( payload ) {
		return payload && payload.credits && typeof payload.credits === 'object' ? payload.credits : {};
	}

	function normalizeJobStatus( rawStatus ) {
		var status = String( rawStatus || '' ).toLowerCase();
		if ( 'running' === status ) {
			return 'processing';
		}
		if ( 'queued' === status || 'processing' === status || 'paused' === status ) {
			return status;
		}
		return status || 'idle';
	}

	function getTruthCounts( payload ) {
		var direct = payload && payload.counts && typeof payload.counts === 'object' ? payload.counts : null;
		var embedded = null;
		if ( payload && payload.meta && payload.meta.state_truth ) {
			var stc = normalizeStateTruthPayload( payload.meta.state_truth );
			embedded = stc && stc.counts && typeof stc.counts === 'object' ? stc.counts : null;
		}
		if ( embedded && Object.keys( embedded ).length > 0 ) {
			return normalizeCounts( embedded );
		}
		if ( direct && Object.keys( direct ).length > 0 ) {
			if ( hasCountValues( direct ) || ! hasCountValues( getDomCounts() ) ) {
				return normalizeCounts( direct );
			}
		}
		return getDomCounts();
	}

	function updateInsightCardsFromTruth( truth ) {
		var counts = getTruthCounts( truth );
		var opt = counts.complete;
		var total = counts.total;
		var miss = counts.missing;
		var withAlt = total > 0 ? Math.max( 0, total - miss ) : 0;
		var cov = total > 0 ? Math.min( 100, Math.round( 100 * withAlt / total ) ) : 0;
		var mins = opt * 2;
		var elOpt = document.querySelector( '[data-bbai-li-insight-optimized]' );
		var elCov = document.querySelector( '[data-bbai-li-insight-coverage]' );
		var elMins = document.querySelector( '[data-bbai-li-insight-mins]' );
		var elCovMeta = document.querySelector( '[data-bbai-li-insight-coverage-meta]' );
		var elSeoMeta = document.querySelector( '[data-bbai-li-insight-seo-meta]' );

		if ( elOpt ) {
			elOpt.textContent = formatSingularPlural( opt, TEXT.insightImagesOptimisedSingular, TEXT.insightImagesOptimisedPlural );
		}
		if ( elCov ) {
			elCov.textContent = String( cov ) + '% ' + TEXT.insightAccessibleSuffix;
		}
		if ( elCovMeta ) {
			elCovMeta.textContent = total > 0
				? replaceTokens( TEXT.insightCoverageMeta, { '%1$s': formatCount( withAlt ), '%2$s': formatCount( total ) } )
				: TEXT.insightCoverageMetaZero;
		}
		if ( elMins ) {
			if ( mins <= 0 ) {
				elMins.textContent = TEXT.insightMinsSavedZero;
			} else {
				elMins.textContent = replaceTokens( TEXT.insightMinsSaved, { '%s': formatCount( mins ) } );
			}
		}
		if ( elSeoMeta ) {
			elSeoMeta.textContent = formatSingularPlural( opt, TEXT.insightSeoMetaSingular, TEXT.insightSeoMetaPlural );
		}
	}

	function truthSupportsBootstrapSync( truth ) {
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var sources = getTruthResolutionSources( truth );
		var countsSource = String( firstDefined( sources.counts, sources.count_source, sources.countSource, '' ) || '' ).toLowerCase();
		var zeroCounts = counts.missing === 0 && counts.review === 0 && counts.complete === 0 && counts.failed === 0 && counts.total === 0;

		if ( ! truth || truth.fallback ) {
			return false;
		}

		if ( ! getTruthSiteHash( truth ) ) {
			return false;
		}

		if ( /assumed|empty|unseeded|seed|bootstrap|ledger/.test( countsSource ) ) {
			return true;
		}

		return state === 'MISSING_ALT' && zeroCounts;
	}

	function getBootstrapSyncStorageKey( truth ) {
		var siteHash = getTruthSiteHash( truth );
		return siteHash ? 'bbaiDashboardBootstrapSync:' + siteHash : '';
	}

	function readBootstrapSyncState( truth ) {
		var key = getBootstrapSyncStorageKey( truth );
		var raw;
		var parsed;

		if ( ! key || ! window.localStorage ) {
			return {};
		}

		try {
			raw = window.localStorage.getItem( key );
			parsed = raw ? JSON.parse( raw ) : {};
			if ( parsed && parsed.expiresAt && parseInt( parsed.expiresAt, 10 ) <= Date.now() ) {
				window.localStorage.removeItem( key );
				return {};
			}
			return parsed && typeof parsed === 'object' ? parsed : {};
		} catch ( error ) {
			return {};
		}
	}

	function writeBootstrapSyncState( truth, patch ) {
		var key = getBootstrapSyncStorageKey( truth );
		var current;
		var next;

		if ( ! key || ! window.localStorage ) {
			return;
		}

		try {
			current = readBootstrapSyncState( truth );
			next = Object.assign( {}, current, patch || {} );
			window.localStorage.setItem( key, JSON.stringify( next ) );
		} catch ( error ) {
			return;
		}
	}

	function clearBootstrapSyncState( truth ) {
		var key = getBootstrapSyncStorageKey( truth );

		if ( ! key || ! window.localStorage ) {
			return;
		}

		try {
			window.localStorage.removeItem( key );
		} catch ( error ) {
			return;
		}
	}

	function describeBootstrapSyncGuard( truth ) {
		var syncState = readBootstrapSyncState( truth );
		var expiresAt = parseInt( syncState && syncState.expiresAt ? syncState.expiresAt : 0, 10 ) || 0;
		var expiresInMs = expiresAt > Date.now() ? Math.max( 0, expiresAt - Date.now() ) : 0;

		return {
			status: String( syncState && syncState.status ? syncState.status : '' ),
			expires_at: expiresAt || 0,
			expires_in_ms: expiresInMs,
			active: !! ( syncState && syncState.status && expiresInMs > 0 ),
		};
	}

	function describeBootstrapSyncEligibility( truth ) {
		var payload = normalizeStateTruthPayload( truth );
		var state = getStateTruthState( payload );
		var counts = getTruthCounts( payload );
		var rawCounts = getTruthRawCounts( payload );
		var rawCredits = getTruthRawCredits( payload );
		var sources = getTruthResolutionSources( payload );
		var rawResolution = getTruthRawResolution( payload );
		var countsSource = String( firstDefined( sources.counts, sources.count_source, sources.countSource, '' ) || '' );
		var rawCountsSource = String( firstDefined( rawResolution.count_source, rawResolution.countSource, countsSource ) || '' );
		var zeroCounts = counts.missing === 0 && counts.review === 0 && counts.complete === 0 && counts.failed === 0 && counts.total === 0;
		var sourceIndicatesUnseeded = /assumed|empty|unseeded|seed|bootstrap|ledger/.test( countsSource.toLowerCase() );
		var rawSourceIndicatesUnseeded = /assumed|empty|unseeded|seed|bootstrap|ledger/.test( rawCountsSource.toLowerCase() );
		var guard = describeBootstrapSyncGuard( payload );
		var fallback = !! ( payload && payload.fallback );
		var linkedSite = !! getTruthSiteHash( payload );
		var stateIsMissingAlt = state === 'MISSING_ALT';
		var countsConsideredEmpty = zeroCounts;
		var shouldBootstrap = truthSupportsBootstrapSync( payload );
		var skipReason = '';

		if ( ! linkedSite ) {
			skipReason = 'not_linked';
		} else if ( guard.active ) {
			skipReason = 'already_ran';
		} else if ( fallback ) {
			skipReason = 'fallback_truth';
		} else if ( sourceIndicatesUnseeded || rawSourceIndicatesUnseeded ) {
			skipReason = '';
		} else if ( ! stateIsMissingAlt ) {
			skipReason = 'state_mismatch';
		} else if ( ! countsConsideredEmpty ) {
			skipReason = 'counts_not_considered_empty';
		} else {
			skipReason = 'missing_backend_flag';
		}

		return {
			state: state,
			linked_site: linkedSite,
			site_hash: getTruthSiteHash( payload ),
			fallback_truth: fallback,
			logic_counts_source: countsSource,
			raw_counts_source: rawCountsSource,
			logic_ledger_empty_or_unseeded: sourceIndicatesUnseeded || ( state === 'MISSING_ALT' && zeroCounts ),
			raw_ledger_empty_or_unseeded: rawSourceIndicatesUnseeded || ( state === 'MISSING_ALT' && zeroCounts ),
			zero_counts: zeroCounts,
			state_is_missing_alt: stateIsMissingAlt,
			counts_considered_empty: countsConsideredEmpty,
			raw_counts: rawCounts,
			raw_credits: rawCredits,
			counts: {
				missing: counts.missing,
				review: counts.review,
				complete: counts.complete,
				failed: counts.failed,
				total: counts.total,
				total_attention: parseInt( firstDefined( rawCounts.total_attention, rawCounts.totalAttention, counts.missing + counts.review ), 10 ) || 0,
				to_review: parseInt( firstDefined( rawCounts.to_review, rawCounts.toReview, rawCounts.review, rawCounts.needs_review, rawCounts.needsReview, rawCounts.weak, counts.review ), 10 ) || 0,
			},
			guard_already_tripped: guard.active,
			guard_status: guard.status,
			guard_expires_in_ms: guard.expires_in_ms,
			bootstrap_already_ran: guard.active,
			bootstrap_should_run: shouldBootstrap,
			final_should_bootstrap: shouldBootstrap && ! guard.active,
			skip_reason: skipReason,
			resolution_fields: {
				resolution_sources: sources,
				resolution: rawResolution,
			},
		};
	}

	function canAttemptBootstrapSync( truth ) {
		var syncState;
		var status;

		if ( ! truthSupportsBootstrapSync( truth ) ) {
			return false;
		}

		syncState = readBootstrapSyncState( truth );
		status = String( syncState.status || '' );

		if ( status && syncState.expiresAt && parseInt( syncState.expiresAt, 10 ) > Date.now() ) {
			return false;
		}

		return true;
	}

	function getTruthCredits( payload ) {
		var direct = payload && payload.credits && typeof payload.credits === 'object' ? payload.credits : null;
		var embedded = null;
		var hasCredits;
		var fallbackRoot;
		var fallbackHero;
		var fallbackSource;
		var fallbackUsed;
		var fallbackTotal;
		var fallbackRemaining;
		if ( payload && payload.meta && payload.meta.state_truth ) {
			var stx = normalizeStateTruthPayload( payload.meta.state_truth );
			embedded = stx && stx.credits && typeof stx.credits === 'object' ? stx.credits : null;
		}
		var credits = ( direct && Object.keys( direct ).length > 0 ) ? direct : ( embedded || {} );
		hasCredits = credits && Object.keys( credits ).length > 0;
		if ( ! hasCredits ) {
			fallbackRoot = getDashboardRoot();
			fallbackHero = hero ? hero.querySelector( '[data-bbai-hero-credit-usage]' ) : null;
			fallbackSource = fallbackRoot || fallbackHero;
			fallbackUsed = fallbackSource ? parseInt( fallbackSource.getAttribute( fallbackRoot ? 'data-bbai-credits-used' : 'data-bbai-hero-credits-used' ) || '0', 10 ) : 0;
			fallbackTotal = fallbackSource ? parseInt( fallbackSource.getAttribute( fallbackRoot ? 'data-bbai-credits-total' : 'data-bbai-hero-credits-limit' ) || '1', 10 ) : 1;
			fallbackRemaining = fallbackSource ? parseInt( fallbackSource.getAttribute( fallbackRoot ? 'data-bbai-credits-remaining' : 'data-bbai-hero-credits-remaining' ) || String( fallbackTotal - fallbackUsed ), 10 ) : Math.max( 0, fallbackTotal - fallbackUsed );
			return {
				used: Math.max( 0, isNaN( fallbackUsed ) ? 0 : fallbackUsed ),
				total: Math.max( 1, isNaN( fallbackTotal ) ? 1 : fallbackTotal ),
				remaining: Math.max( 0, isNaN( fallbackRemaining ) ? 0 : fallbackRemaining ),
				isPro: fallbackRoot ? fallbackRoot.getAttribute( 'data-bbai-is-premium' ) === '1' : false,
				plan: '',
			};
		}
		var usedExplicit = credits.used != null || credits.credits_used != null || credits.creditsUsed != null;
		var usedRaw = parseInt( firstDefined( credits.used, credits.credits_used, credits.creditsUsed, '' ), 10 );
		var totalRaw = parseInt( firstDefined( credits.total, credits.limit, credits.credits_total, credits.creditsTotal, 1 ), 10 );
		var remRawCandidate = firstDefined( credits.remaining, credits.credits_remaining, credits.creditsRemaining, null );
		var remRaw = null === remRawCandidate ? NaN : parseInt( remRawCandidate, 10 );

		var total = Math.max( 1, isNaN( totalRaw ) ? 1 : totalRaw );

		// If remaining is provided (common for quota surfaces), respect it for UI.
		// Fall back to used when remaining is missing; if used is missing but remaining exists, derive used = total - remaining.
		var remaining = ! isNaN( remRaw ) ? Math.max( 0, Math.min( total, remRaw ) ) : NaN;
		var used = ! isNaN( usedRaw ) ? Math.max( 0, usedRaw ) : NaN;

		if ( isNaN( used ) && ! isNaN( remaining ) ) {
			used = Math.max( 0, total - remaining );
		}
		if ( isNaN( remaining ) && ! isNaN( used ) ) {
			remaining = Math.max( 0, total - used );
		}

		used = Math.max( 0, isNaN( used ) ? 0 : used );
		used = Math.min( used, total );
		remaining = Math.max( 0, isNaN( remaining ) ? ( total - used ) : remaining );

		var plan = String( firstDefined( credits.plan, credits.plan_slug, credits.planSlug, credits.plan_type, credits.planType, '' ) ).toLowerCase();
		var isPro = !! ( credits.is_pro || credits.isPro );

		if ( ! isPro && [ 'pro', 'growth', 'agency', 'enterprise' ].indexOf( plan ) !== -1 ) {
			isPro = true;
		}

		fallbackRoot = getDashboardRoot();
		if ( fallbackRoot ) {
			fallbackUsed = parseInt( fallbackRoot.getAttribute( 'data-bbai-credits-used' ) || '', 10 );
			fallbackTotal = parseInt( fallbackRoot.getAttribute( 'data-bbai-credits-total' ) || '', 10 );
			fallbackRemaining = parseInt( fallbackRoot.getAttribute( 'data-bbai-credits-remaining' ) || '', 10 );
			if (
				! isNaN( fallbackUsed ) &&
				! isNaN( fallbackTotal ) &&
				fallbackTotal === total &&
				fallbackUsed > used
			) {
				used = Math.min( total, Math.max( 0, fallbackUsed ) );
				remaining = ! isNaN( fallbackRemaining )
					? Math.max( 0, Math.min( total, fallbackRemaining ) )
					: Math.max( 0, total - used );
			}
		}

		return {
			used: used,
			total: total,
			remaining: remaining,
			isPro: isPro,
			plan: plan,
		};
	}

	function getTruthJob( payload ) {
		var rawJob = payload && payload.job && typeof payload.job === 'object' ? payload.job : null;
		var done;
		var total;
		var etaSeconds;

		if ( ! rawJob ) {
			return null;
		}

		done = parseInt( firstDefined( rawJob.done, 0 ), 10 );
		total = parseInt( firstDefined( rawJob.total, 0 ), 10 );
		etaSeconds = firstDefined( rawJob.eta_seconds, rawJob.etaSeconds, null );
		etaSeconds = null === etaSeconds ? null : parseInt( etaSeconds, 10 );

		return {
			active: !! rawJob.active,
			pausable: !! rawJob.pausable,
			status: normalizeJobStatus( rawJob.status || rawJob.state || '' ),
			done: Math.max( 0, isNaN( done ) ? 0 : done ),
			total: Math.max( 0, isNaN( total ) ? 0 : total ),
			etaSeconds: isNaN( etaSeconds ) ? null : etaSeconds,
			lastCheckedAt: getStateTruthLastCheckedAt( payload ),
		};
	}

	function getStateTruthLastCheckedAt( payload ) {
		var job = payload && payload.job && typeof payload.job === 'object' ? payload.job : null;
		var raw = job
			? ( job.last_checked_at || job.lastCheckedAt || job.checked_at || job.checkedAt || '' )
			: '';
		var parsed = raw ? Date.parse( String( raw ) ) : NaN;
		return ! isNaN( parsed ) ? parsed : null;
	}

	function getLastRunAt( payload ) {
		var raw = payload ? ( payload.last_run_at || payload.lastRunAt || '' ) : '';
		var parsed = raw ? Date.parse( String( raw ) ) : NaN;
		return ! isNaN( parsed ) ? parsed : null;
	}

	function fetchStateTruth( context, opts, requestOptions ) {
		var options = opts || {};
		var reqOpts = requestOptions && typeof requestOptions === 'object' ? requestOptions : {};
		var stateTruthUrl = getStateTruthUrl();
		if ( ! stateTruthUrl || ! BBAI_HERO_CFG.restNonce ) {
			var missingConfigError = new Error( 'state_truth_config_missing' );
			if ( options.logFailure !== false ) {
				logDashboardUiFailure( context, missingConfigError );
			}
			return Promise.reject( missingConfigError );
		}

		if ( bbaiShouldForceDashboardTruthRefresh( context ) ) {
			try {
				var parsedUrl = new URL( stateTruthUrl, window.location.href );
				parsedUrl.searchParams.set( 'force', '1' );
				parsedUrl.searchParams.set( 'context', String( context || '' ) );
				stateTruthUrl = parsedUrl.toString();
			} catch ( e ) {
				stateTruthUrl += ( -1 === stateTruthUrl.indexOf( '?' ) ? '?' : '&' ) + 'force=1&context=' + encodeURIComponent( String( context || '' ) );
			}
		}

		var controller = reqOpts.controller || ( typeof AbortController !== 'undefined' ? new AbortController() : null );
		var startedAt = Date.now();
		var timeoutMs = reqOpts.timeoutMs || ( function () {
			var s = String( hero.getAttribute( 'data-bbai-li-state' ) || '' ).toUpperCase();
			return ( 'QUEUED' === s || 'PROCESSING' === s ) ? 25000 : 15000;
		}() );
		var timeoutId = ( reqOpts.timeoutId === 0 || reqOpts.timeoutId )
			? reqOpts.timeoutId
			: ( controller
			? window.setTimeout( function () {
				if ( window.BBAI_LOG && typeof window.BBAI_LOG.info === 'function' && window.console && typeof window.console.debug === 'function' ) {
					window.console.debug( '[bbai-dashboard-state]', {
						event: 'dashboard_state_fetch_slow',
						reason: context || '',
						sequence: ( window.bbaiDashboardStateRequest && window.bbaiDashboardStateRequest.sequence ) || 0,
						durationMs: timeoutMs,
						endpoint: 'state-truth',
					} );
				}
			}, timeoutMs )
			: null );

		if ( window.BBAI_LOG && typeof window.BBAI_LOG.info === 'function' && window.console && typeof window.console.debug === 'function' ) {
			window.console.debug( '[bbai-dashboard-state]', {
				event: 'dashboard_state_fetch_start',
				reason: context || '',
				sequence: ( window.bbaiDashboardStateRequest && window.bbaiDashboardStateRequest.sequence ) || 0,
				durationMs: 0,
				endpoint: 'state-truth',
			} );
		}

		return fetch( stateTruthUrl, {
			method:      'GET',
			credentials: 'same-origin',
			signal:      controller ? controller.signal : undefined,
			headers:     {
				'X-WP-Nonce': BBAI_HERO_CFG.restNonce,
			},
		} )
		.then( function ( res ) {
			if ( timeoutId ) { window.clearTimeout( timeoutId ); }
			if ( ! res.ok ) {
				throw new Error( 'state_truth ' + res.status );
			}
			return res.json();
		} )
		.then( function ( payload ) {
			if ( timeoutId ) { window.clearTimeout( timeoutId ); }
			var truth = normalizeStateTruthPayload( payload );
			var truthState = getStateTruthState( truth );
			if ( ! truth || ! truthState ) {
				throw new Error( 'state_truth_invalid' );
			}
			try {
				syncCreditsOnlyFromTruth( truth );
			} catch ( e ) {}
			logBootstrapSync( 'state_truth_received', Object.assign(
				{
					context: context || '',
					state: truthState,
					linked_site: !! getTruthSiteHash( truth ),
				},
				describeBootstrapSyncEligibility( truth )
			) );
			logCredits( 'raw_truth', {
				context: context || '',
				state: truthState,
				raw_truth_credits: getTruthRawCredits( truth ),
				resolved_credits: getTruthCredits( truth ),
				linked_site: !! getTruthSiteHash( truth ),
			} );
			logCounts( 'raw_truth', {
				context: context || '',
				state: truthState,
				raw_truth_counts: getTruthRawCounts( truth ),
				resolved_counts: getTruthCounts( truth ),
				linked_site: !! getTruthSiteHash( truth ),
			} );
			if ( options.logLoaded !== false ) {
				logDashboardUi( 'state_truth_loaded', {
					context: context || '',
					state: truthState,
					job_status: truth && truth.job && truth.job.status ? String( truth.job.status ) : '',
				} );
			}
			return truth;
		} )
		.catch( function ( error ) {
			if ( timeoutId ) { window.clearTimeout( timeoutId ); }
			if ( controller && controller.signal && controller.signal.aborted ) {
				if ( window.BBAI_LOG && typeof window.BBAI_LOG.info === 'function' && window.console && typeof window.console.debug === 'function' ) {
					window.console.debug( '[bbai-dashboard-state]', {
						event: 'dashboard_state_fetch_timeout',
						reason: context || '',
						sequence: ( window.bbaiDashboardStateRequest && window.bbaiDashboardStateRequest.sequence ) || 0,
						durationMs: Math.max( 0, Date.now() - startedAt ),
						endpoint: 'state-truth',
					} );
				}
			}
			if ( options.logFailure !== false ) {
				logDashboardUiFailure( context, error );
			}
			throw error;
		} );
	}

	function fetchDashboardState( context ) {
		if ( ! BBAI_HERO_CFG.dashboardUrl || ! BBAI_HERO_CFG.restNonce ) {
			return Promise.reject( new Error( 'dashboard_state_config_missing' ) );
		}

		return fetch( BBAI_HERO_CFG.dashboardUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': BBAI_HERO_CFG.restNonce,
			},
		} )
		.then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'dashboard_state ' + res.status );
			}
			return res.json();
		} )
		.then( function ( payload ) {
			var stateData = normalizeResolvedDashboardPayload( payload );
			if ( ! stateData || ! stateData.state ) {
				throw new Error( 'dashboard_state_invalid' );
			}
			return stateData;
		} );
	}

	function postBootstrapSync() {
		if ( ! BBAI_HERO_CFG.bootstrapSyncUrl || ! BBAI_HERO_CFG.restNonce ) {
			return Promise.reject( new Error( 'bootstrap_sync_config_missing' ) );
		}

		return fetch( BBAI_HERO_CFG.bootstrapSyncUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': BBAI_HERO_CFG.restNonce,
			},
		} )
		.then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'bootstrap_sync ' + res.status );
			}
			return res.json()
				.then( function ( payload ) {
					if ( payload && typeof payload === 'object' ) {
						payload.__bbai_http_status = res.status;
					}
					return payload;
				} );
		} )
		.then( function ( payload ) {
			var data = normalizeBootstrapSyncPayload( payload );
			if ( ! data ) {
				throw new Error( 'bootstrap_sync_invalid' );
			}
			return data;
		} );
	}

	function resolveTruthAfterBootstrap( bootstrapPayload ) {
		var bootstrapTruth = normalizeStateTruthPayload( bootstrapPayload && bootstrapPayload.truth ? bootstrapPayload.truth : null );

		if ( bootstrapTruth && getStateTruthState( bootstrapTruth ) && ! truthSupportsBootstrapSync( bootstrapTruth ) ) {
			logBootstrapSync( 'truth_refresh', Object.assign(
				{
					context: 'bootstrap_response_truth',
					still_bootstrap_eligible: truthSupportsBootstrapSync( bootstrapTruth ),
				},
				describeBootstrapSyncEligibility( bootstrapTruth )
			) );
			return Promise.resolve( bootstrapTruth );
		}

		return confirmLatestStateTruth( 'bootstrap_sync_refresh', 900 );
	}

	function maybeBootstrapTruth( truth, context ) {
		var bootstrapContext = context || 'polling';
		var eligibility = describeBootstrapSyncEligibility( truth );
		var canAttempt = canAttemptBootstrapSync( truth );

		logBootstrapSync( 'eligibility', Object.assign(
			{
				context: bootstrapContext,
				can_attempt: canAttempt,
			},
			eligibility
		) );
		logBootstrapSync( 'decision', {
			context: bootstrapContext,
			state: eligibility.state,
			counts_total: eligibility.counts.total,
			counts_total_attention: eligibility.counts.total_attention,
			counts_missing: eligibility.counts.missing,
			counts_to_review: eligibility.counts.to_review,
			bootstrapAlreadyRan: eligibility.bootstrap_already_ran,
			isLinkedSite: eligibility.linked_site,
			fallbackTruth: eligibility.fallback_truth,
			ledgerEmpty: eligibility.logic_ledger_empty_or_unseeded,
			rawLedgerEmpty: eligibility.raw_ledger_empty_or_unseeded,
			countsSource: eligibility.logic_counts_source,
			rawCountsSource: eligibility.raw_counts_source,
			finalShouldBootstrap: eligibility.final_should_bootstrap,
		} );

		if ( ! canAttempt ) {
			dashboardPolling.bootstrapSyncApplied = false;
			logBootstrapSync( 'skipped', {
				context: bootstrapContext,
				reason: eligibility.skip_reason || ( eligibility.guard_already_tripped ? 'already_ran' : 'not_eligible' ),
				guard_status: eligibility.guard_status,
				guard_expires_in_ms: eligibility.guard_expires_in_ms,
				raw_counts_source: eligibility.raw_counts_source,
				logic_counts_source: eligibility.logic_counts_source,
				failed_condition: eligibility.skip_reason || 'not_eligible',
				state: eligibility.state,
				isLinkedSite: eligibility.linked_site,
				bootstrapAlreadyRan: eligibility.bootstrap_already_ran,
				counts_total: eligibility.counts.total,
				counts_total_attention: eligibility.counts.total_attention,
				counts_missing: eligibility.counts.missing,
				counts_to_review: eligibility.counts.to_review,
				fallbackTruth: eligibility.fallback_truth,
				ledgerEmpty: eligibility.logic_ledger_empty_or_unseeded,
			} );
			return Promise.resolve( truth );
		}

		dashboardPolling.bootstrapSyncApplied = true;
		writeBootstrapSyncState( truth, {
			status: 'in_progress',
			expiresAt: Date.now() + BOOTSTRAP_SYNC_LOCK_TTL_MS,
		} );
		logBootstrapSync( 'STARTING SYNC', {
			context: bootstrapContext,
			site_hash: getTruthSiteHash( truth ),
		} );
		logDashboardUi( 'bootstrap_sync_started', {
			context: bootstrapContext,
			state: getStateTruthState( truth ),
			site_hash: getTruthSiteHash( truth ),
		} );
		logBootstrapSync( 'request_started', {
			context: bootstrapContext,
			endpoint: BBAI_HERO_CFG.bootstrapSyncUrl || '',
			request_started_at: new Date().toISOString(),
			site_hash: getTruthSiteHash( truth ),
		} );

		return postBootstrapSync()
			.then( function ( payload ) {
				var skippedReason = String( payload && payload.reason ? payload.reason : '' );
				var immediateTruth = normalizeStateTruthPayload( payload && payload.truth ? payload.truth : null );
				var cachedStatus = 'skipped';
				var ttl = BOOTSTRAP_SYNC_FAILURE_COOLDOWN_MS;
				var payloadDebug = payload && payload.debug && typeof payload.debug === 'object' ? payload.debug : {};
				var canWriteSuccessGuard = false;
				var returnedTruth = immediateTruth && getStateTruthState( immediateTruth ) ? immediateTruth : truth;
				var returnedTruthEligible = truthSupportsBootstrapSync( returnedTruth );

				logBootstrapSync( 'response_received', {
					context: bootstrapContext,
					triggered: !! ( payload && payload.triggered ),
					skipped: !! ( payload && payload.skipped ),
					reason: skippedReason,
					local_total: parseInt( payload && payload.local_total ? payload.local_total : 0, 10 ) || 0,
					sent_count: parseInt( payload && payload.sent_count ? payload.sent_count : 0, 10 ) || 0,
					inserted: parseInt( payload && payload.inserted ? payload.inserted : 0, 10 ) || 0,
					updated: parseInt( payload && payload.updated ? payload.updated : 0, 10 ) || 0,
					changed: parseInt( payload && payload.changed ? payload.changed : 0, 10 ) || 0,
					unchanged: parseInt( payload && payload.unchanged ? payload.unchanged : 0, 10 ) || 0,
					media_inventory: payloadDebug.media_inventory || null,
					payload: payloadDebug.payload || null,
					network: payloadDebug.network || null,
					truth_refresh: payloadDebug.truth_refresh || null,
					response_status: parseInt( payload && payload.__bbai_http_status ? payload.__bbai_http_status : 0, 10 ) || 0,
					response_body_summary: {
						triggered: !! ( payload && payload.triggered ),
						skipped: !! ( payload && payload.skipped ),
						reason: skippedReason,
					},
				} );

				if ( payload && payload.skipped ) {
					if ( skippedReason === 'success' ) {
						canWriteSuccessGuard = ! returnedTruthEligible;
						if ( canWriteSuccessGuard ) {
							cachedStatus = 'success';
							ttl = BOOTSTRAP_SYNC_SUCCESS_TTL_MS;
						}
					} else if ( skippedReason === 'in_progress' ) {
						cachedStatus = 'in_progress';
						ttl = BOOTSTRAP_SYNC_LOCK_TTL_MS;
					} else if ( skippedReason === 'failed' ) {
						cachedStatus = 'failed';
					}

					if ( skippedReason === 'success' && ! canWriteSuccessGuard ) {
						clearBootstrapSyncState( truth );
					} else {
						writeBootstrapSyncState( truth, {
							status: cachedStatus,
							expiresAt: Date.now() + ttl,
						} );
					}

					logBootstrapSync( 'success_guard_decision', {
						context: bootstrapContext,
						skipped_response: true,
						refreshed_truth_bootstrap_eligible: returnedTruthEligible,
						success_guard_written: skippedReason === 'success' ? canWriteSuccessGuard : cachedStatus === 'success',
						success_guard_skipped: skippedReason === 'success' && ! canWriteSuccessGuard,
						status: skippedReason === 'success' && ! canWriteSuccessGuard ? 'retryable' : cachedStatus,
						site_hash: getTruthSiteHash( truth ),
					} );

					logBootstrapSync( 'guard_write', {
						context: bootstrapContext,
						status: skippedReason === 'success' && ! canWriteSuccessGuard ? 'retryable' : cachedStatus,
						expires_in_ms: skippedReason === 'success' && ! canWriteSuccessGuard ? 0 : ttl,
						site_hash: getTruthSiteHash( truth ),
						still_bootstrap_eligible: returnedTruthEligible,
						guard_written: !( skippedReason === 'success' && ! canWriteSuccessGuard ),
					} );

					return returnedTruth;
				}

				return resolveTruthAfterBootstrap( payload )
					.then( function ( updatedTruth ) {
						var refreshedTruthEligible = truthSupportsBootstrapSync( updatedTruth );

						logBootstrapSync( 'truth_refresh', Object.assign(
							{
								context: bootstrapContext,
								still_bootstrap_eligible: refreshedTruthEligible,
							},
							describeBootstrapSyncEligibility( updatedTruth )
						) );

						logBootstrapSync( 'success_guard_decision', {
							context: bootstrapContext,
							skipped_response: false,
							refreshed_truth_bootstrap_eligible: refreshedTruthEligible,
							success_guard_written: ! refreshedTruthEligible,
							success_guard_skipped: refreshedTruthEligible,
							status: refreshedTruthEligible ? 'retryable' : 'success',
							site_hash: getTruthSiteHash( truth ),
						} );

						if ( refreshedTruthEligible ) {
							clearBootstrapSyncState( truth );
						} else {
							writeBootstrapSyncState( truth, {
								status: 'success',
								expiresAt: Date.now() + BOOTSTRAP_SYNC_SUCCESS_TTL_MS,
							} );
						}

						logBootstrapSync( 'guard_write', {
							context: bootstrapContext,
							status: refreshedTruthEligible ? 'retryable' : 'success',
							expires_in_ms: refreshedTruthEligible ? 0 : BOOTSTRAP_SYNC_SUCCESS_TTL_MS,
							still_bootstrap_eligible: refreshedTruthEligible,
							site_hash: getTruthSiteHash( truth ),
							guard_written: ! refreshedTruthEligible,
						} );
						logDashboardUi( 'bootstrap_sync_completed', {
							context: bootstrapContext,
							state: getStateTruthState( updatedTruth ),
							sent_count: payload && payload.sent_count ? payload.sent_count : 0,
						} );
						return updatedTruth;
					} );
			} )
			.catch( function ( error ) {
				writeBootstrapSyncState( truth, {
					status: 'failed',
					expiresAt: Date.now() + BOOTSTRAP_SYNC_FAILURE_COOLDOWN_MS,
				} );
				logBootstrapSync( 'guard_write', {
					context: bootstrapContext,
					status: 'failed',
					expires_in_ms: BOOTSTRAP_SYNC_FAILURE_COOLDOWN_MS,
					site_hash: getTruthSiteHash( truth ),
				} );
				logBootstrapSync( 'network_error', {
					context: bootstrapContext,
					message: error && error.message ? error.message : String( error || '' ),
					site_hash: getTruthSiteHash( truth ),
				} );
				logDashboardUi( 'bootstrap_sync_failed', {
					context: bootstrapContext,
					state: getStateTruthState( truth ),
					message: error && error.message ? error.message : String( error || '' ),
				} );
				return truth;
			} );
	}

	function buildTruthSignature( truth ) {
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );
		var job = getTruthJob( truth );
		return JSON.stringify( {
			state: state,
			counts: counts,
			credits: credits,
			job: job ? {
				active: job.active,
				pausable: job.pausable,
				status: job.status,
				done: job.done,
				total: job.total,
				etaSeconds: job.etaSeconds,
			} : null,
		} );
	}

	function getDashboardRenderMode( state ) {
		state = String( state || '' ).toUpperCase();
		return 'ALL_CLEAR' === state ? 'DONE' : state;
	}

	function isGenerationInProgressForRender( truth ) {
		var state = truth ? getStateTruthState( truth ) : ( hero.getAttribute( 'data-bbai-li-state' ) || '' );
		var job = truth ? getTruthJob( truth ) : null;
		return !! (
			( window.bbaiGenerationLock && window.bbaiGenerationLock.active ) ||
			window.bbaiGenerationInProgress ||
			'QUEUED' === state ||
			'PROCESSING' === state ||
			( job && job.active )
		);
	}

	function buildDashboardRenderSignatureFromParts( counts, credits, state, generationInProgress ) {
		return JSON.stringify( {
			missing: Math.max( 0, parseInt( counts.missing, 10 ) || 0 ),
			needsReview: Math.max( 0, parseInt( counts.review, 10 ) || 0 ),
			optimized: Math.max( 0, parseInt( counts.complete, 10 ) || 0 ),
			total: Math.max( 0, parseInt( counts.total, 10 ) || 0 ),
			creditsUsed: Math.max( 0, parseInt( credits.used, 10 ) || 0 ),
			creditsLimit: Math.max( 1, parseInt( credits.total, 10 ) || 1 ),
			creditsRemaining: Math.max( 0, parseInt( credits.remaining, 10 ) || 0 ),
			mode: getDashboardRenderMode( state ),
			generationInProgress: !! generationInProgress,
		} );
	}

	function buildDashboardRenderSignature( truth ) {
		return buildDashboardRenderSignatureFromParts(
			getTruthCounts( truth ),
			getTruthCredits( truth ),
			getStateTruthState( truth ),
			isGenerationInProgressForRender( truth )
		);
	}

	function buildCurrentDashboardRenderSignature() {
		var root = getDashboardRoot();
		var heroCredit = hero.querySelector( '[data-bbai-hero-credit-usage="1"]' );
		var creditsTotal = root ? parseInt( root.getAttribute( 'data-bbai-credits-total' ) || '', 10 ) : NaN;
		var creditsUsed = root ? parseInt( root.getAttribute( 'data-bbai-credits-used' ) || '', 10 ) : NaN;
		var creditsRemaining = root ? parseInt( root.getAttribute( 'data-bbai-credits-remaining' ) || '', 10 ) : NaN;

		if ( isNaN( creditsTotal ) && heroCredit ) {
			creditsTotal = parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-limit' ) || '', 10 );
		}
		if ( isNaN( creditsUsed ) && heroCredit ) {
			creditsUsed = parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-used' ) || '', 10 );
		}
		if ( isNaN( creditsRemaining ) && heroCredit ) {
			creditsRemaining = parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-remaining' ) || '', 10 );
		}
		creditsTotal = Math.max( 1, isNaN( creditsTotal ) ? 1 : creditsTotal );
		creditsUsed = Math.max( 0, isNaN( creditsUsed ) ? 0 : creditsUsed );
		creditsRemaining = Math.max( 0, isNaN( creditsRemaining ) ? Math.max( 0, creditsTotal - creditsUsed ) : creditsRemaining );

		return buildDashboardRenderSignatureFromParts(
			getDomCounts(),
			{
				used: creditsUsed,
				total: creditsTotal,
				remaining: creditsRemaining,
			},
			hero.getAttribute( 'data-bbai-li-state' ) || '',
			isGenerationInProgressForRender()
		);
	}

	function getLastDashboardRenderSignature() {
		return window.bbaiLastDashboardRenderSignature || dashboardPolling.renderSignature || buildCurrentDashboardRenderSignature();
	}

	function rememberDashboardRenderSignature( truth ) {
		var signature = buildDashboardRenderSignature( truth );
		dashboardPolling.renderSignature = signature;
		window.bbaiLastDashboardRenderSignature = signature;
		window.bbaiPreviousDashboardMode = getDashboardRenderMode( getStateTruthState( truth ) );
		return signature;
	}

	function parseDashboardRenderSignature( signature ) {
		try {
			return signature ? JSON.parse( signature ) : null;
		} catch ( err ) {
			return null;
		}
	}

	function renderSignaturesShareStableModeAndCounts( previous, next ) {
		return !! (
			previous &&
			next &&
			previous.mode === next.mode &&
			previous.generationInProgress === next.generationInProgress &&
			previous.missing === next.missing &&
			previous.needsReview === next.needsReview &&
			previous.optimized === next.optimized &&
			previous.total === next.total
		);
	}

	function renderSignatureCreditsChanged( previous, next ) {
		return !! (
			previous &&
			next &&
			(
				previous.creditsUsed !== next.creditsUsed ||
				previous.creditsLimit !== next.creditsLimit ||
				previous.creditsRemaining !== next.creditsRemaining
			)
		);
	}

	function getPollIntervalForState( state ) {
		// Poll only while generation is active; stable dashboard states refresh on explicit actions.
		switch ( String( state || '' ).toUpperCase() ) {
			case 'QUEUED':
			case 'PROCESSING':
				return 3000;
			default:
				return 0;
		}
	}

	function getPollingBackoffDelay( failureCount ) {
		var attempt = Math.max( 1, parseInt( failureCount, 10 ) || 1 );
		return Math.min( 12000, 3000 * Math.pow( 2, attempt - 1 ) );
	}

	function shouldPollState( state ) {
		return getPollIntervalForState( state ) > 0;
	}

	function stopLiveSignalTimer() {
		if ( liveSignalTimer ) {
			window.clearInterval( liveSignalTimer );
			liveSignalTimer = null;
		}
	}

	function markOptimisticAction( action ) {
		dashboardPolling.optimisticAction = String( action || '' );
	}

	function clearHeroGenerationWatchdog() {
		if ( heroGenerationWatchdog ) {
			window.clearTimeout( heroGenerationWatchdog );
			heroGenerationWatchdog = null;
		}
	}

	function clearOptimisticAction() {
		var primaryCta = getPrimaryCta();
		if (
			dashboardPolling.optimisticAction &&
			primaryCta &&
			primaryCta.getAttribute( 'aria-busy' ) === 'true' &&
			! ( window.bbaiGenerationLock && window.bbaiGenerationLock.active )
		) {
			setBusy( primaryCta, false );
		}
		dashboardPolling.optimisticAction = '';
		clearHeroGenerationWatchdog();
		if ( safetyTimer ) {
			window.clearTimeout( safetyTimer );
			safetyTimer = null;
		}
	}

	function stopPolling( reason ) {
		if ( dashboardPolling.timer ) {
			window.clearTimeout( dashboardPolling.timer );
			dashboardPolling.timer = null;
		}
		stopLiveSignalTimer();
		if ( dashboardPolling.active ) {
			dashboardPolling.active = false;
			dashboardPolling.activeInterval = 0;
			logDashboardUi( 'polling_stopped', {
				reason: reason || '',
				state: dashboardPolling.latestState || hero.getAttribute( 'data-bbai-li-state' ) || '',
			} );
		}
	}

	function schedulePolling( reason, overrideDelay ) {
		var state = dashboardPolling.latestState || hero.getAttribute( 'data-bbai-li-state' ) || '';
		var nextDelay = null === overrideDelay || undefined === overrideDelay
			? getPollIntervalForState( state )
			: Math.max( 0, parseInt( overrideDelay, 10 ) || 0 );
		var shouldActivate = nextDelay > 0 || dashboardPolling.requiresResolvedSync;

		if ( document.visibilityState === 'hidden' ) {
			stopPolling( 'hidden' );
			return;
		}

		if ( dashboardPolling.timer ) {
			window.clearTimeout( dashboardPolling.timer );
			dashboardPolling.timer = null;
		}

		if ( ! shouldActivate ) {
			stopPolling( reason || 'stable' );
			return;
		}

		if ( ! dashboardPolling.active || dashboardPolling.activeInterval !== nextDelay ) {
			dashboardPolling.active = true;
			dashboardPolling.activeInterval = nextDelay;
			logDashboardUi( 'polling_started', {
				reason: reason || '',
				state: state,
				interval_ms: nextDelay,
			} );
		}

		dashboardPolling.timer = window.setTimeout( function () {
			runPollingTick( 'scheduled' );
		}, nextDelay );
	}

	function getActionStatusText( el ) {
		var action = el.getAttribute( 'data-bbai-li-action' ) || el.getAttribute( 'data-action' ) || '';
		if ( action in ACTION_STATUS ) { return ACTION_STATUS[ action ]; }
		if ( /generate|run|scan|optimis|process|retr/i.test( action ) ) {
			return TEXT.working;
		}
		return null;
	}

	function clearTransientStatusTimer() {
		if ( transientStatusTimer ) {
			window.clearTimeout( transientStatusTimer );
			transientStatusTimer = null;
		}
	}

	function showStatusLine( text, tone, options ) {
		var opts = options || {};
		var existing = hero.querySelector( '.bbai-li-hero-status-line' );
		var meta = opts.meta || buildStatusStripMeta( 'status_line_direct', 'showStatusLine' );
		var truth = opts.truth || dashboardPolling.currentTruth || null;
		if ( ! opts.preserveTimer ) {
			clearTransientStatusTimer();
		}
		if ( existing ) {
			existing.className = 'bbai-li-hero-status-line' + ( tone ? ' bbai-li-hero-status-line--' + tone : '' );
			if ( existing.textContent !== text ) { existing.textContent = text; }
			logStatusStrip( 'status_line_write', buildStatusStripLogContext( truth, meta, text, 'status_line', 'write' ) );
			return;
		}
		var support = hero.querySelector( '[data-bbai-li-hero-support]' );
		if ( ! support ) { return; }
		var line = document.createElement( 'p' );
		line.className = 'bbai-li-hero-status-line' + ( tone ? ' bbai-li-hero-status-line--' + tone : '' );
		line.setAttribute( 'role', 'status' );
		line.setAttribute( 'aria-live', 'polite' );
		line.textContent = text;
		support.insertAdjacentElement( 'afterend', line );
		logStatusStrip( 'status_line_write', buildStatusStripLogContext( truth, meta, text, 'status_line', 'write' ) );
	}

	function showTransientStatusLine( text, tone, duration, meta ) {
		var stripMeta = meta || buildStatusStripMeta( 'transient_status', 'showTransientStatusLine' );
		clearTransientStatusTimer();
		showStatusLine( text, tone, {
			preserveTimer: true,
			meta: stripMeta,
		} );
		transientStatusTimer = window.setTimeout( function () {
			transientStatusTimer = null;
			syncStatusLineForTruth( dashboardPolling.currentTruth, stripMeta );
		}, Math.max( 400, parseInt( duration, 10 ) || 1600 ) );
	}

	function removeStatusLine( meta ) {
		clearTransientStatusTimer();
		var line = hero.querySelector( '.bbai-li-hero-status-line' );
		if ( line ) {
			logStatusStrip( 'status_line_write', buildStatusStripLogContext( dashboardPolling.currentTruth, meta || buildStatusStripMeta( 'status_line_remove', 'removeStatusLine' ), line.textContent || '', 'status_line', 'remove' ) );
			line.remove();
		}
	}

	function getQueuedSignalText( seconds ) {
		if ( seconds < 1 ) {
			return TEXT.lastCheckedJustNow;
		}
		if ( seconds < 60 ) {
			if ( seconds === 1 ) {
				return TEXT.lastCheckedSecond;
			}
			return replaceTokens( TEXT.lastCheckedSeconds, { '%s': String( seconds ) } );
		}
		if ( seconds < 3600 ) {
			var minutes = Math.max( 1, Math.floor( seconds / 60 ) );
			if ( 1 === minutes ) {
				return TEXT.lastCheckedMinute;
			}
			return replaceTokens( TEXT.lastCheckedMinutes, { '%s': String( minutes ) } );
		}
		var hours = Math.max( 1, Math.floor( seconds / 3600 ) );
		if ( 1 === hours ) {
			return TEXT.lastCheckedHour;
		}
		return replaceTokens( TEXT.lastCheckedHours, { '%s': String( hours ) } );
	}

	function getQueuedStatusText( seconds ) {
		return TEXT.checkingQueue + ' ' + getQueuedSignalText( seconds );
	}

	function getLastSuccessfulDashboardRefreshAt() {
		var raw = window.bbaiLastSuccessfulDashboardRefreshAt;
		var t = raw ? parseInt( raw, 10 ) : 0;
		return isNaN( t ) ? 0 : Math.max( 0, t );
	}

	function pickLastCheckedAt( jobCheckedAt ) {
		var refreshAt = getLastSuccessfulDashboardRefreshAt();
		var jobAt = jobCheckedAt ? parseInt( jobCheckedAt, 10 ) : 0;
		jobAt = isNaN( jobAt ) ? 0 : Math.max( 0, jobAt );
		// Prefer the most recent successful dashboard refresh to avoid stale server timestamps.
		return Math.max( refreshAt, jobAt );
	}

	function syncLastCheckedJustNow() {
		var nodes = document.querySelectorAll( '[data-bbai-li-queued-signal="1"]' );
		if ( ! nodes || ! nodes.length ) { return; }
		Array.prototype.forEach.call( nodes, function ( n ) {
			if ( n && n.textContent !== TEXT.lastCheckedJustNow ) {
				n.textContent = TEXT.lastCheckedJustNow;
			}
		} );
	}

	function updateQueuedStripSignal( text ) {
		var signal = document.querySelector( '[data-bbai-li-queued-signal="1"]' );
		if ( signal ) {
			signal.textContent = text;
		}
	}

	function formatRelativeAge( timestamp ) {
		if ( ! timestamp ) {
			return '';
		}
		var seconds = Math.max( 0, Math.floor( ( Date.now() - timestamp ) / 1000 ) );
		if ( seconds < 60 ) {
			return '<?php echo esc_js( __( 'just now', 'beepbeep-ai-alt-text-generator' ) ); ?>';
		}
		if ( seconds < 3600 ) {
			var minutes = Math.max( 1, Math.floor( seconds / 60 ) );
			return replaceTokens(
				1 === minutes
					? '<?php echo esc_js( _n( '%d minute ago', '%d minutes ago', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>'
					: '<?php echo esc_js( _n( '%d minute ago', '%d minutes ago', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
				{ '%d': String( minutes ) }
			);
		}
		if ( seconds < 86400 ) {
			var hours = Math.max( 1, Math.floor( seconds / 3600 ) );
			return replaceTokens(
				1 === hours
					? '<?php echo esc_js( _n( '%d hour ago', '%d hours ago', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>'
					: '<?php echo esc_js( _n( '%d hour ago', '%d hours ago', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
				{ '%d': String( hours ) }
			);
		}
		var days = Math.max( 1, Math.floor( seconds / 86400 ) );
		return replaceTokens(
			1 === days
				? '<?php echo esc_js( _n( '%d day ago', '%d days ago', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>'
				: '<?php echo esc_js( _n( '%d day ago', '%d days ago', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
			{ '%d': String( days ) }
		);
	}

	function renderQueuedSignal( meta ) {
		var truth = dashboardPolling.currentTruth;
		var job = getTruthJob( truth );
		var lastCheckedAt = job ? pickLastCheckedAt( job.lastCheckedAt ) : pickLastCheckedAt( 0 );
		var stripMeta = meta || buildStatusStripMeta( 'queued_signal', 'renderQueuedSignal' );
		if ( ! lastCheckedAt ) {
			lastCheckedAt = Date.now();
		}
		var seconds = Math.max( 0, Math.floor( ( Date.now() - lastCheckedAt ) / 1000 ) );
		var signalText = getQueuedSignalText( seconds );
		showStatusLine( getQueuedStatusText( seconds ), 'queued', {
			meta: stripMeta,
			truth: truth,
		} );
		updateQueuedStripSignal( signalText );
		logStatusStrip( 'activity_strip_write', buildStatusStripLogContext( truth, stripMeta, signalText, 'activity_strip_signal', 'write' ) );
	}

	function syncStatusLineForTruth( truth, meta ) {
		var state = getStateTruthState( truth );
		var stripMeta = meta || buildStatusStripMeta( 'truth_status_sync', 'syncStatusLineForTruth' );
		stopLiveSignalTimer();

		if ( transientStatusTimer ) {
			return;
		}

		if ( 'QUEUED' === state ) {
			renderQueuedSignal( stripMeta );
			liveSignalTimer = window.setInterval( function () {
				renderQueuedSignal( buildStatusStripMeta( stripMeta.reason || 'queued_signal_interval', 'renderQueuedSignalInterval' ) );
			}, 5000 );
			return;
		}

		if ( 'PROCESSING' === state ) {
			showStatusLine( TEXT.generationInProgress, 'scanning', {
				meta: stripMeta,
				truth: truth,
			} );
			return;
		}

		if ( ! dashboardPolling.optimisticAction ) {
			removeStatusLine( stripMeta );
		}
	}

	function setBusy( el, busy ) {
		var busyLabel;
		var spinner;
		var label;
		var originalHtml;

		if ( busy ) {
			if ( ! el.getAttribute( 'data-bbai-busy-original-html' ) ) {
				el.setAttribute( 'data-bbai-busy-original-html', el.innerHTML );
			}
			if ( ! el.getAttribute( 'data-original-label' ) ) {
				el.setAttribute( 'data-original-label', el.textContent.trim() );
			}
			busyLabel = 'generate-missing' === ( el.getAttribute( 'data-bbai-li-action' ) || el.getAttribute( 'data-action' ) || '' )
				? ACTION_STATUS[ 'generate-missing' ]
				: ( el.getAttribute( 'data-busy-label' ) || TEXT.working );
			el.setAttribute( 'aria-busy', 'true' );
			el.setAttribute( 'aria-disabled', 'true' );
			el.setAttribute( 'disabled', '' );
			el.setAttribute( 'data-bbai-generation-action', '1' );
			el.classList.add( 'is-busy', 'bbai-generate-button' );
			el.textContent = '';
			spinner = document.createElement( 'span' );
			spinner.className = 'bbai-button-spinner';
			spinner.setAttribute( 'aria-hidden', 'true' );
			label = document.createElement( 'span' );
			label.className = 'bbai-button-label';
			label.textContent = busyLabel;
			el.appendChild( spinner );
			el.appendChild( label );
		} else {
			el.removeAttribute( 'aria-busy' );
			el.removeAttribute( 'aria-disabled' );
			el.removeAttribute( 'disabled' );
			el.classList.remove( 'is-busy' );
			originalHtml = el.getAttribute( 'data-bbai-busy-original-html' );
			if ( originalHtml ) {
				el.innerHTML = originalHtml;
				el.removeAttribute( 'data-bbai-busy-original-html' );
			} else {
				var orig = el.getAttribute( 'data-original-label' );
				if ( orig ) { el.textContent = orig; }
			}
			el.removeAttribute( 'data-original-label' );
			removeStatusLine();
		}
	}

	function acquireInlineGenerationLock( e, source ) {
		if ( window.bbaiGenerationLock && window.bbaiGenerationLock.active ) {
			if ( e && typeof e.preventDefault === 'function' ) { e.preventDefault(); }
			if ( e && typeof e.stopPropagation === 'function' ) { e.stopPropagation(); }
			if ( e && typeof e.stopImmediatePropagation === 'function' ) { e.stopImmediatePropagation(); }
			return false;
		}

		window.bbaiGenerationLock = {
			active: true,
			source: source || 'dashboard_generate',
			startedAt: Date.now(),
		};
		window.bbaiGenerationInProgress = true;
		return true;
	}

	function releaseInlineGenerationLock() {
		if ( window.bbaiGenerationLock ) {
			window.bbaiGenerationLock.active = false;
			window.bbaiGenerationLock.source = null;
			window.bbaiGenerationLock.jobId = null;
			window.bbaiGenerationLock.startedAt = null;
		}
		window.bbaiGenerationInProgress = false;
	}

	function flashSummaryUpdating() {
		var values = hero.querySelectorAll( '.bbai-status-summary .bbai-count' );
		values.forEach( function ( v ) { v.classList.add( 'bbai-li-summary__value--updating' ); } );
		setTimeout( function () {
			values.forEach( function ( v ) { v.classList.remove( 'bbai-li-summary__value--updating' ); } );
		}, 600 );
	}

	function getLibraryHref() {
		var dashboardRoot = getDashboardRoot();
		return dashboardRoot ? ( dashboardRoot.getAttribute( 'data-bbai-library-url' ) || '' ) : '';
	}

	function getQueuedLibraryHref() {
		var secondaryCta = getSecondaryCta();
		var secondaryHref = secondaryCta ? secondaryCta.getAttribute( 'href' ) : '';
		if ( secondaryHref && secondaryHref !== '#' ) {
			return secondaryHref;
		}
		var dashboardRoot = getDashboardRoot();
		var base = dashboardRoot
			? ( dashboardRoot.getAttribute( 'data-bbai-missing-library-url' ) || dashboardRoot.getAttribute( 'data-bbai-library-url' ) || '' )
			: '';
		return base ? String( base ).split( '#' )[ 0 ] + '#bbai-review-filter-tabs' : '#';
	}

	function getCreditUsageHref() {
		var root = getDashboardRoot();
		var currentPrimary = getPrimaryCta();
		var currentHref = currentPrimary ? currentPrimary.getAttribute( 'href' ) : '';
		var ajaxUrl = BBAI_HERO_CFG.ajaxUrl || ( window.ajaxurl || '' );

		if ( root && root.getAttribute( 'data-state' ) === 'QUOTA_EXHAUSTED' && currentHref && currentHref !== '#' ) {
			return currentHref;
		}

		if ( ajaxUrl && ajaxUrl.indexOf( 'admin-ajax.php' ) !== -1 ) {
			return ajaxUrl.replace( /admin-ajax\.php(?:\?.*)?$/, 'admin.php?page=bbai-credit-usage' );
		}

		return '';
	}

	function formatEta( etaSeconds ) {
		var safe = null === etaSeconds ? null : parseInt( etaSeconds, 10 );
		if ( null === safe || isNaN( safe ) || safe <= 0 ) {
			return TEXT.aMoment;
		}
		if ( safe < 60 ) {
			return replaceTokens( TEXT.etaSeconds, { '%d': String( safe ) } );
		}
		return replaceTokens( TEXT.etaMinutes, { '%d': String( Math.ceil( safe / 60 ) ) } );
	}

	function buildCreditsSummaryItem( state, truth ) {
		var credits = getTruthCredits( truth );
		var remainingPct = credits.total > 0 ? Math.round( ( credits.remaining / credits.total ) * 100 ) : 0;
		var item = {
			label: 'ALL_CLEAR' === state ? TEXT.creditsRemainingLabel : TEXT.creditsLeftLabel,
			value: formatCount( credits.remaining ) + ' / ' + formatCount( credits.total ),
			mod: 'NEEDS_REVIEW' === state || 'ALL_CLEAR' === state
				? 'muted'
				: ( credits.remaining <= 0 ? 'alert' : ( remainingPct <= 20 ? 'warn' : 'muted' ) ),
		};

		logCredits( 'renderer_value', {
			state: state,
			resolved_credits: credits,
			display_label: item.label,
			display_value: item.value,
			display_mod: item.mod,
		} );

		return item;
	}

	function buildSummaryFromTruth( state, truth ) {
		var counts = getTruthCounts( truth );

		return [
			{
				label: TEXT.missingLabel,
				value: formatCount( counts.missing ),
				mod: counts.missing > 0 ? 'alert' : 'ok',
			},
			{
				label: TEXT.reviewLabel,
				value: formatCount( counts.review ),
				mod: 'NEEDS_REVIEW' === state ? 'primary' : ( counts.review > 0 ? 'warn' : 'ok' ),
			},
			buildCreditsSummaryItem( state, truth ),
		];
	}

	function buildDonutFromTruth( state, truth ) {
		var counts = getTruthCounts( truth );
		var job = getTruthJob( truth );
		var total = Math.max( 1, counts.total || counts.missing + counts.review + counts.complete + counts.failed );
		var segments = {
			optimized: counts.complete,
			weak: counts.review,
			missing: counts.missing,
			total: total,
		};
		var queuedTotal;
		var done;
		var jobTotal;
		var jobPct;

		if ( 'QUEUED' === state ) {
			var dashboardMissingCap = getDashboardRootMissingCount();
			queuedTotal = job && ( job.queue_count > 0 || job.total > 0 )
				? Math.max( job.queue_count || 0, Math.max( 0, ( job.total || 0 ) - ( job.done || 0 ) ), job.total || 0 )
				: counts.missing;
			// Cap to the dashboard's local coverage scan (source of ALT Library chips).
			// Remote "truth" can lag; the UI should not claim more are ready than are actually missing.
			queuedTotal = Math.min( queuedTotal, dashboardMissingCap > 0 ? dashboardMissingCap : counts.missing );
			return {
				pct: 0,
				color: 'gray',
				animated: false,
				center_label: formatCount( queuedTotal ),
				center_sub_label: 1 === queuedTotal ? TEXT.queuedImageSingular : TEXT.queuedImagePlural,
				aria_label: formatSingularPlural(
					queuedTotal,
					'<?php echo esc_js( _n( '%s image queued; waiting to start', '%s images queued; waiting to start', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
					'<?php echo esc_js( _n( '%s image queued; waiting to start', '%s images queued; waiting to start', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>'
				),
				segments: segments,
				job_done: 0,
				job_total: queuedTotal,
			};
		}

		done = job ? job.done : 0;
		jobTotal = Math.max( 1, job && job.total ? job.total : 1 );
		jobPct = Math.max( 0, Math.min( 100, Math.round( ( done / jobTotal ) * 100 ) ) );

		return {
			pct: jobPct,
			color: 'blue',
			animated: true,
			center_label: formatCount( counts.missing ),
			center_sub_label: 1 === counts.missing ? TEXT.imageLeftSingular : TEXT.imageLeftPlural,
			aria_label: replaceTokens( TEXT.processingAria, { '%d': String( jobPct ) } ),
			segments: segments,
			job_pct: jobPct,
			job_done: done,
			job_total: jobTotal,
		};
	}

	function isPauseAllowed( truth ) {
		var job = getTruthJob( truth );
		return 'PROCESSING' === getStateTruthState( truth ) && !! ( job && job.active && job.pausable && 'processing' === job.status );
	}

	function hasVisiblePauseCta() {
		var pauseCta = hero.querySelector( '[data-bbai-li-primary-cta][data-bbai-li-action="pause-job"]' );
		return !! ( pauseCta && ! pauseCta.hidden && pauseCta.getAttribute( 'aria-hidden' ) !== 'true' );
	}

	function truthUsageForStatePayload( truth ) {
		var c = getTruthCredits( truth );
		return {
			used: c.used,
			total: c.total,
			remaining: c.remaining,
		};
	}

	function buildQueuedStateData( truth ) {
		var counts = getTruthCounts( truth );
		var job = getTruthJob( truth );
		var dashboardMissingCap = getDashboardRootMissingCount();
		var isFirstFreeGeneration = credits && ! credits.isPro && credits.used === 0 && credits.total >= 50 && counts.missing > 0;
		var queuedTotal = job && ( job.queue_count > 0 || job.total > 0 )
			? Math.max( job.queue_count || 0, Math.max( 0, ( job.total || 0 ) - ( job.done || 0 ) ), job.total || 0 )
			: counts.missing;
		queuedTotal = Math.min( queuedTotal, dashboardMissingCap > 0 ? dashboardMissingCap : counts.missing );

		return {
			state: 'QUEUED',
			usage: truthUsageForStatePayload( truth ),
			hero: {
				badge: { text: TEXT.queuedBadge, mod: 'gray' },
				headline: formatSingularPlural( queuedTotal, TEXT.queuedHeadlineSingular, TEXT.queuedHeadlinePlural ),
				support: isFirstFreeGeneration ? TEXT.postSignupFirstGeneration : TEXT.queuedSupport,
				variant: 'queued',
				primary_cta: {
					label: replaceTokens(
						1 === queuedTotal ? TEXT.queuedPrimarySingular : TEXT.queuedPrimaryPlural,
						{ '%s': formatCount( queuedTotal ) }
					),
					busy_label: ACTION_STATUS[ 'generate-missing' ],
					action: 'generate-missing',
					href: '#',
				},
				secondary_cta: {
					label: TEXT.queuedSecondary,
					action: 'navigate',
					href: getQueuedLibraryHref(),
				},
				summary: buildSummaryFromTruth( 'QUEUED', truth ),
			},
			donut: buildDonutFromTruth( 'QUEUED', truth ),
		};
	}

	function getCurrentHeroBadgeData() {
		var badge = hero.querySelector( '.bbai-li-state-badge' );
		var mod = 'gray';

		if ( ! badge || badge.hidden ) {
			return null;
		}

		Array.prototype.forEach.call( badge.classList, function ( className ) {
			if ( 0 === className.indexOf( 'bbai-li-state-badge--' ) ) {
				mod = className.replace( 'bbai-li-state-badge--', '' ) || 'gray';
			}
		} );

		return {
			text: ( badge.textContent || '' ).trim(),
			mod: mod,
		};
	}

	function getCurrentHeroCopyData() {
		var headline = hero.querySelector( '[data-bbai-li-hero-headline="1"]' );
		var support = hero.querySelector( '[data-bbai-li-hero-support="1"]' );
		var primary = getPrimaryCta();
		var secondary = getSecondaryCta();
		var state = hero.getAttribute( 'data-bbai-li-state' ) || '';

		function mapCta( node, isPrimary ) {
			var label;

			if ( ! node || node.hidden || node.getAttribute( 'aria-hidden' ) === 'true' ) {
				return null;
			}

			label = ( node.textContent || '' ).trim();
			if ( ! label ) {
				return null;
			}

			return {
				label: label,
				action: isPrimary ? ( node.getAttribute( 'data-bbai-li-action' ) || '' ) : ( node.getAttribute( 'data-action' ) || '' ),
				href: node.getAttribute( 'href' ) || '#',
				busy_label: ( function () {
					var b = node.getAttribute( 'data-busy-label' ) || '';
					if ( isPrimary ) {
						return b || getActionStatusText( node ) || TEXT.working;
					}
					return b || undefined;
				}() ),
			};
		}

		return {
			state: state,
			variant: hero.getAttribute( 'data-bbai-li-variant' ) || '',
			badge: getCurrentHeroBadgeData(),
			headline: headline ? ( headline.textContent || '' ).trim() : '',
			support: support ? ( support.textContent || '' ).trim() : '',
			primaryCta: mapCta( primary, true ),
			secondaryCta: mapCta( secondary, false ),
		};
	}

	function buildStableSummaryFromTruth( state, truth ) {
		var counts = getTruthCounts( truth );

		if ( 'ALL_CLEAR' === state ) {
			return [
				{
					label: TEXT.optimizedLabel,
					value: formatCount( counts.complete ),
					mod: 'ok',
				},
				buildCreditsSummaryItem( state, truth ),
			];
		}

		return buildSummaryFromTruth( state, truth );
	}

	function buildStableDonutFromTruth( state, truth ) {
		var counts = getTruthCounts( truth );
		var total = Math.max( 1, counts.total || counts.missing + counts.review + counts.complete + counts.failed );
		var pct = Math.max( 0, Math.min( 100, Math.round( ( counts.complete / total ) * 100 ) ) );
		var segments = {
			optimized: counts.complete,
			weak: counts.review,
			missing: counts.missing,
			total: total,
		};

		switch ( state ) {
			case 'MIXED_ATTENTION':
			case 'MISSING_ALT':
				return {
					pct: pct,
					color: 'amber',
					animated: false,
					center_label: formatCount( counts.missing ),
					center_sub_label: 'missing ALT',
					aria_label: replaceTokens( '<?php echo esc_js( __( '%1$s of %2$s images have ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>', {
						'%1$s': formatCount( counts.complete ),
						'%2$s': formatCount( total ),
					} ),
					segments: segments,
				};
			case 'NEEDS_REVIEW':
				return {
					pct: pct,
					color: 'blue',
					animated: false,
					center_label: formatCount( counts.review ),
					center_sub_label: 'to review',
					aria_label: formatSingularPlural(
						counts.review,
						'<?php echo esc_js( _n( '%s image needs review', '%s images need review', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
						'<?php echo esc_js( _n( '%s image needs review', '%s images need review', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>'
					),
					segments: segments,
				};
			case 'QUOTA_EXHAUSTED':
				return {
					pct: pct,
					color: 'amber',
					animated: false,
					center_label: formatCount( counts.missing ),
					center_sub_label: 'credits needed',
					aria_label: replaceTokens( '<?php echo esc_js( __( '%d%% of images have ALT text — credits exhausted', 'beepbeep-ai-alt-text-generator' ) ); ?>', {
						'%d': String( pct ),
					} ),
					segments: segments,
				};
			case 'ALL_CLEAR':
				return {
					pct: 100,
					color: 'green',
					animated: false,
					center_label: '✓',
					center_sub_label: '',
					aria_label: '<?php echo esc_js( __( 'All images have ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
					segments: segments,
				};
			default:
				return buildDonutFromTruth( state, truth );
		}
	}

	function buildStableStateData( truth ) {
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );
		var current = getCurrentHeroCopyData();
		var reuseCurrentState = current.state === state;
		var summary = buildStableSummaryFromTruth( state, truth );
		var headline = '';
		var support = '';
		var badge = reuseCurrentState ? current.badge : null;
		var variant = reuseCurrentState && current.variant ? current.variant : ( 'ALL_CLEAR' === state ? 'success' : 'default' );
		var primaryCta = reuseCurrentState ? current.primaryCta : null;
		var secondaryCta = reuseCurrentState ? current.secondaryCta : null;
		var isFirstFreeGeneration = credits && ! credits.isPro && credits.used === 0 && credits.total >= 50 && counts.missing > 0;

		switch ( state ) {
			case 'MIXED_ATTENTION':
				headline =
					formatSingularPlural(
						counts.missing,
						TEXT.missingAltSingular,
						TEXT.missingAltPlural
					) +
					', ' +
					formatSingularPlural(
						counts.review,
						TEXT.readyReviewSingular,
						TEXT.readyReviewPlural
					);

				support = TEXT.mixedSupport;

				if ( counts.missing > 20 ) {
					badge = { text: TEXT.actionNeededBadge, mod: 'amber' };
				} else if ( counts.missing > 5 ) {
					badge = { text: TEXT.recommendedBadge, mod: 'blue' };
				} else {
					badge = null;
				}

				primaryCta = {
					label: 'Generate ALT text for ' + formatCount( counts.missing ) + ( counts.missing === 1 ? ' image' : ' images' ),
					busy_label: ACTION_STATUS[ 'generate-missing' ] || TEXT.working,
					action: 'generate-missing',
					href: '#',
				};

				secondaryCta = {
					label: 'Review ' + formatCount( counts.review ) + ( counts.review === 1 ? ' image' : ' images' ),
					action: 'navigate',
					href: getReviewLibraryHref(),
				};

				console.log( '[bbai-render] fallback_state=MIXED_ATTENTION', {
					missing: counts.missing,
					review: counts.review,
				} );

				break;
			case 'MISSING_ALT':
				headline = formatSingularPlural( counts.missing, TEXT.missingHeadlineSingular, TEXT.missingHeadlinePlural );
				support = isFirstFreeGeneration
					? TEXT.postSignupFirstGeneration
					: ( reuseCurrentState && current.support
					? current.support
					: ( credits.isPro && counts.complete > 0
						? replaceTokens( TEXT.missingSupportProgress, {
							'%1$s': formatCount( counts.complete ),
							'%2$s': formatCount( counts.missing ),
						} )
						: TEXT.missingSupport ) );
				if ( counts.missing > 20 ) {
					badge = badge || { text: TEXT.actionNeededBadge, mod: 'amber' };
				} else if ( counts.missing > 5 ) {
					badge = badge || { text: TEXT.recommendedBadge, mod: 'blue' };
				} else {
					badge = null;
				}
				primaryCta = primaryCta || {
					label: 'Generate ALT text for ' + formatCount( counts.missing ) + ( counts.missing === 1 ? ' image' : ' images' ),
					busy_label: ACTION_STATUS[ 'generate-missing' ] || TEXT.working,
					action: 'generate-missing',
					href: '#',
				};
				secondaryCta = secondaryCta || {
					label: TEXT.openAltLibrary,
					action: 'navigate',
					href: getLibraryHref(),
				};
				break;
			case 'NEEDS_REVIEW':
				headline = formatSingularPlural( counts.review, TEXT.reviewHeadlineSingular, TEXT.reviewHeadlinePlural );
				support = reuseCurrentState && current.support
					? current.support
					: ( credits.isPro
						? formatSingularPlural( counts.review, TEXT.reviewSupportSingular, TEXT.reviewSupportPlural )
						: TEXT.reviewSupport );
				badge = badge || { text: TEXT.reviewReadyBadge, mod: 'blue' };
				primaryCta = primaryCta || {
					label: 'Review ' + formatCount( counts.review ) + ( counts.review === 1 ? ' image' : ' images' ),
					busy_label: ACTION_STATUS[ 'approve-all' ] || TEXT.working,
					action: 'approve-all',
					href: '#',
				};
				secondaryCta = secondaryCta || {
					label: TEXT.reviewIndividually,
					action: 'navigate',
					href: getReviewLibraryHref(),
				};
				break;
			case 'QUOTA_EXHAUSTED':
				headline = formatSingularPlural( counts.missing, TEXT.quotaHeadlineSingular, TEXT.quotaHeadlinePlural );
				support = reuseCurrentState && current.support
					? current.support
					: ( credits.isPro && counts.complete > 0
						? replaceTokens( TEXT.quotaSupportProgress, {
							'%1$s': formatCount( counts.complete ),
							'%2$s': formatCount( counts.missing ),
						} )
						: formatSingularPlural( counts.missing, TEXT.quotaSupportSingular, TEXT.quotaSupportPlural ) );
				badge = badge || { text: TEXT.creditsNeededBadge, mod: 'amber' };
				primaryCta = primaryCta || {
					label: TEXT.addCredits,
					busy_label: ACTION_STATUS[ 'add-credits' ] || TEXT.working,
					action: 'add-credits',
					href: getCreditUsageHref(),
				};
				secondaryCta = secondaryCta || {
					label: TEXT.openAltLibrary,
					action: 'navigate',
					href: getLibraryHref(),
				};
				break;
			case 'ALL_CLEAR': {
				headline = TEXT.allClearHeadline;
				if ( reuseCurrentState && current.support ) {
					support = current.support;
				} else {
					support = TEXT.allClearSupport;
				}
				badge = badge || { text: TEXT.allOptimisedBadge, mod: 'green' };
				primaryCta = primaryCta || {
					label: TEXT.uploadMoreImages,
					action: 'navigate',
					href: '<?php echo esc_js( admin_url( 'upload.php' ) ); ?>',
				};
				secondaryCta = secondaryCta || {
					label: TEXT.rescanLibrary,
					busy_label: ACTION_STATUS[ 'rescan-media-library' ] || TEXT.working,
					action: 'rescan-media-library',
					href: '#',
				};
				break;
			}
			default:
				return null;
		}

		return {
			state: state,
			id: state,
			usage: truthUsageForStatePayload( truth ),
			hero: {
				badge: badge,
				headline: headline,
				support: support,
				variant: variant,
				primary_cta: primaryCta,
				secondary_cta: secondaryCta,
				library_cta: null,
				summary: summary,
			},
			donut: buildStableDonutFromTruth( state, truth ),
		};
	}

	function buildProcessingSupport( truth ) {
		var job = getTruthJob( truth );
		var credits = getTruthCredits( truth );
		var done = job ? job.done : 0;
		var total = job ? job.total : 0;
		var remaining = Math.max( 0, total - done );
		var eta = formatEta( job ? job.etaSeconds : null );

		if ( credits.isPro && done > 0 ) {
			return replaceTokens( TEXT.processingSupportDone, {
				'%1$s': formatCount( done ),
				'%2$s': eta,
			} );
		}

		if ( eta ) {
			return replaceTokens( TEXT.processingSupportEta, {
				'%1$s': formatCount( remaining ),
				'%2$s': eta,
			} );
		}

		return formatSingularPlural( remaining, TEXT.processingRemainingSingular, TEXT.processingRemainingPlural );
	}

	function buildProcessingStateData( truth ) {
		var job = getTruthJob( truth );
		var done = job ? job.done : 0;
		var total = job ? job.total : 0;

		return {
			state: 'PROCESSING',
			usage: truthUsageForStatePayload( truth ),
			hero: {
				badge: { text: TEXT.processingBadge, mod: 'blue' },
				headline: replaceTokens( TEXT.processingHeadline, {
					'%1$s': formatCount( done ),
					'%2$s': formatCount( total ),
				} ),
				support: buildProcessingSupport( truth ),
				variant: 'running',
				primary_cta: isPauseAllowed( truth ) ? {
					label: TEXT.pause,
					busy_label: TEXT.pausing,
					action: 'pause-job',
					href: '#',
				} : null,
				secondary_cta: {
					label: TEXT.openAltLibrary,
					action: 'navigate',
					href: getLibraryHref(),
				},
				summary: buildSummaryFromTruth( 'PROCESSING', truth ),
			},
			donut: buildDonutFromTruth( 'PROCESSING', truth ),
		};
	}

	function buildLocalActiveStateData( truth ) {
		var state = getStateTruthState( truth );
		if ( 'QUEUED' === state ) {
			return buildQueuedStateData( truth );
		}
		if ( 'PROCESSING' === state ) {
			return buildProcessingStateData( truth );
		}
		return buildStableStateData( truth );
	}

	function navigateToHref( href ) {
		href = href ? String( href ) : '';
		if ( ! href || href === '#' ) {
			return false;
		}
		window.location.assign( href );
		return true;
	}

	function getReviewLibraryHref() {
		var dashboardRoot = document.querySelector( '[data-bbai-needs-review-library-url]' );
		if ( dashboardRoot ) {
			return dashboardRoot.getAttribute( 'data-bbai-needs-review-library-url' ) ||
				dashboardRoot.getAttribute( 'data-bbai-library-url' ) ||
				'';
		}

		var secondaryCta = getSecondaryCta();
		var secondaryHref = secondaryCta ? secondaryCta.getAttribute( 'href' ) : '';
		if ( secondaryHref && secondaryHref !== '#' ) {
			return secondaryHref;
		}

		return '';
	}

	/**
	 * Keep the right-rail hero credit meter in sync with state-truth (same numbers as #bbai-dashboard-main).
	 * Without this, SSR/Poller update the root data attributes but the visible label/bar stayed stale until reload.
	 */
	function syncHeroCreditBlockFromTruth( truth ) {
		var wrap = hero.querySelector( '[data-bbai-hero-credit-usage="1"]' ) || document.querySelector( '[data-bbai-hero-credit-usage="1"]' );
		if ( ! wrap ) {
			return;
		}
		var credits = getTruthCredits( truth );
		var used = Math.max( 0, credits.used );
		var total = Math.max( 1, credits.total );
		var remaining = Math.max( 0, credits.remaining );
		var pct = Math.min( 100, Math.max( 0, Math.round( ( used / total ) * 100 ) ) );
		var creditState = resolveHeroCreditState( used, total, remaining );

		// Idempotent rendering: skip all DOM writes when credits are unchanged.
		// Prevents flicker from re-applying text/classes/animations on every poll.
		window.bbaiLastRenderedCredits = window.bbaiLastRenderedCredits || { used: null, limit: null, remaining: null, pct: null, creditState: null };
		var prev = window.bbaiLastRenderedCredits;
		var unchanged = prev
			&& prev.used === used
			&& prev.limit === total
			&& prev.remaining === remaining
			&& prev.creditState === creditState;
		var creditsDebug = !!( window.BBAI_DEBUG || ( window.BBAI && window.BBAI.debug ) );
		if ( unchanged ) {
			if ( creditsDebug ) {
				logCredits( 'skipped unchanged render', { previous: prev, next: { used: used, limit: total, remaining: remaining } } );
			}
			return;
		}

		window.bbaiLastRenderedCredits = { used: used, limit: total, remaining: remaining, pct: pct, creditState: creditState };
		if ( creditsDebug ) {
			logCredits( 'updated', { previous: prev, next: window.bbaiLastRenderedCredits } );
		}

		var state = getStateTruthState( truth );
		var isFreePlan = ! credits.isPro;
		var counts = normalizeCounts( truth && truth.counts ? truth.counts : {} );

		if ( wrap.getAttribute( 'data-bbai-hero-credits-used' ) !== String( used ) ) {
			wrap.setAttribute( 'data-bbai-hero-credits-used', String( used ) );
		}
		if ( wrap.getAttribute( 'data-bbai-hero-credits-remaining' ) !== String( remaining ) ) {
			wrap.setAttribute( 'data-bbai-hero-credits-remaining', String( remaining ) );
		}
		if ( wrap.getAttribute( 'data-bbai-hero-credits-limit' ) !== String( total ) ) {
			wrap.setAttribute( 'data-bbai-hero-credits-limit', String( total ) );
		}
		// These are inside the credit block, but do not change the “10 / 50 used this month” line.
		// Keep them in sync without re-writing when unchanged.
		if ( wrap.getAttribute( 'data-bbai-hero-missing-count' ) !== String( counts.missing ) ) {
			wrap.setAttribute( 'data-bbai-hero-missing-count', String( counts.missing ) );
		}
		if ( wrap.getAttribute( 'data-bbai-hero-review-count' ) !== String( counts.review ) ) {
			wrap.setAttribute( 'data-bbai-hero-review-count', String( counts.review ) );
		}
		if ( wrap.getAttribute( 'data-credit-state' ) !== creditState ) {
			wrap.setAttribute( 'data-credit-state', creditState );
		}
		var shouldMute = ( 'ALL_CLEAR' === state && isFreePlan );
		if ( wrap.classList.contains( 'bbai-credit-usage--all-clear-muted' ) !== shouldMute ) {
			wrap.classList.toggle( 'bbai-credit-usage--all-clear-muted', shouldMute );
		}

		var labelEl = wrap.querySelector( '[data-bbai-hero-credit-label="1"]' );
		if ( labelEl ) {
			var nextLabel = replaceTokens( TEXT.heroCreditUsedThisMonth || '', {
				'%1$s': formatCount( used ),
				'%2$s': formatCount( total ),
			} );
			if ( labelEl.textContent !== nextLabel ) {
				labelEl.textContent = nextLabel;
				labelEl.classList.add( 'bbai-li-summary__value--updating' );
				window.setTimeout( function () {
					labelEl.classList.remove( 'bbai-li-summary__value--updating' );
				}, 420 );
			}
		}

		var clarity = wrap.querySelector( '[data-bbai-hero-credit-clarity="1"]' );
		if ( clarity ) {
			clarity.textContent = ( 'ALL_CLEAR' === state && isFreePlan )
				? ( TEXT.creditsResetMonthly || '' )
				: ( TEXT.creditUsageLine || '' );
		}

		var fill = wrap.querySelector( '[data-bbai-hero-credit-fill="1"]' );
		if ( fill ) {
			var nextPct = String( pct ) + '%';
			// Only update when different to avoid restarting CSS transitions.
			if ( fill.style.getPropertyValue( '--bbai-credit-percent' ) !== nextPct ) {
				fill.style.setProperty( '--bbai-credit-percent', nextPct );
			}
		}

		var bar = wrap.querySelector( '[data-bbai-hero-credit-bar="1"]' );
		if ( bar ) {
			bar.setAttribute( 'aria-valuenow', String( pct ) );
			bar.setAttribute( 'aria-label', formatHeroCreditBarAria( remaining, total ) );
		}

		var ctx = wrap.querySelector( '[data-bbai-hero-credit-context="1"]' );
		var ctxLine = buildHeroCreditContextLine( remaining, state );
		if ( ctx ) {
			ctx.classList.toggle( 'bbai-credit-context--warning', creditState !== 'healthy' );
			if ( ctxLine ) {
				ctx.removeAttribute( 'hidden' );
				ctx.textContent = ctxLine;
			} else {
				ctx.setAttribute( 'hidden', 'hidden' );
				ctx.textContent = '';
			}
		}

		var insight = wrap.querySelector( '[data-bbai-hero-credit-insight="1"]' );
		var insightLine = buildHeroCreditBatchLine( remaining, counts.missing );
		if ( insight ) {
			if ( insightLine ) {
				insight.removeAttribute( 'hidden' );
				insight.textContent = insightLine;
			} else {
				insight.setAttribute( 'hidden', 'hidden' );
				insight.textContent = '';
			}
		}

		var helper = wrap.querySelector( '[data-bbai-hero-credit-helper="1"]' );
		if ( helper ) {
			var helperLine = buildHeroCreditGrowthLine( wrap, used, total, remaining );
			var helperLink = helper.querySelector( '[data-bbai-hero-credit-growth-link="1"]' );
			if ( helperLine ) {
				helper.removeAttribute( 'hidden' );
				if ( helperLink ) {
					helperLink.textContent = helperLine;
				} else {
					helper.textContent = helperLine;
				}
			} else {
				helper.setAttribute( 'hidden', 'hidden' );
				if ( helperLink ) {
					helperLink.textContent = '';
				} else {
					helper.textContent = '';
				}
			}
		}
	}

	/**
	 * Credits can change independently of counts/state (e.g. user generates ALT text
	 * but coverage counts are preserved from local scan). Keep credit UI accurate
	 * even when we short-circuit a full UI commit.
	 */
	function syncCreditsOnlyFromTruth( truth ) {
		var root = getDashboardRoot();
		var credits = getTruthCredits( truth );

		if ( root ) {
			root.setAttribute( 'data-bbai-credits-used', String( credits.used ) );
			root.setAttribute( 'data-bbai-credits-total', String( credits.total ) );
			root.setAttribute( 'data-bbai-credits-remaining', String( credits.remaining ) );
			root.setAttribute( 'data-bbai-is-premium', credits.isPro ? '1' : '0' );
		}

		syncHeroCreditBlockFromTruth( truth );
	}

	function syncCountsOnlyFromTruth( truth, context ) {
		var counts = getTruthCounts( truth );
		var meta = buildStatusStripMeta( context || '', 'syncCountsOnlyFromTruth' );
		var stateData = buildStableStateData( truth );
		var headline = hero.querySelector( '[data-bbai-li-hero-headline="1"]' );
		var support = hero.querySelector( '[data-bbai-li-hero-support="1"]' );
		syncDashboardRootFromTruth( truth );
		if ( stateData && stateData.hero ) {
			if ( headline && stateData.hero.headline && headline.textContent !== String( stateData.hero.headline ) ) {
				headline.textContent = String( stateData.hero.headline );
			}
			if ( support && stateData.hero.support && support.textContent !== String( stateData.hero.support ) ) {
				support.textContent = String( stateData.hero.support );
			}
			hero.setAttribute( 'data-bbai-li-missing-count', String( Math.max( 0, counts.missing || 0 ) ) );
			hero.setAttribute( 'data-bbai-li-review-count', String( Math.max( 0, counts.review || 0 ) ) );
			hero.setAttribute( 'data-bbai-li-optimised-count', String( Math.max( 0, counts.complete || 0 ) ) );
			hero.setAttribute( 'data-bbai-li-total-count', String( Math.max( 1, counts.total || counts.missing + counts.review + counts.complete + counts.failed || 1 ) ) );
		}
		updateInsightCardsFromTruth( truth );
		updateSummaryValueByLabel( TEXT.missingLabel, formatCount( counts.missing ), counts.missing > 0 ? 'alert' : 'ok' );
		updateSummaryValueByLabel( TEXT.reviewLabel, formatCount( counts.review ), counts.review > 0 ? 'primary' : 'ok' );
		syncRetentionStripFromTruth( truth );
		renderActivityStripFromTruth( truth, meta );
		renderImpactLineFromTruth( truth, meta );
		syncStatusLineForTruth( truth, meta );
	}

	function syncDashboardRootFromTruth( truth ) {
		if ( window.BBAI_DASHBOARD_STATE_ENDPOINT_ACTIVE ) {
			return;
		}
		var root = getDashboardRoot();
		var loggedInRoot = getLoggedInDashboardRoot();
		var counts = getTruthCounts( truth );
		var countsHash = getTruthCountsHash( truth );
		var credits = getTruthCredits( truth );
		var state = getStateTruthState( truth );
		var rawCounts = getTruthRawCounts( truth );
		var hasAuthoritativeCounts = hasCountValues( rawCounts ) || !! countsHash || 'ALL_CLEAR' === state || 'NEEDS_REVIEW' === state;
		var preserveLocalCounts = false;

		if ( root ) {
			// Dashboard and ALT Library must agree on coverage counts.
			// The backend "truth" payload (jobs/ledger) can lag behind the local Media Library scan,
			// so only preserve local scan counts when the truth payload does not include
			// authoritative count fields. Otherwise stale scan counts leak into the strip.
			preserveLocalCounts = root.getAttribute( 'data-bbai-has-scan-results' ) === '1' && ! hasAuthoritativeCounts;
			if ( ! preserveLocalCounts ) {
				root.setAttribute( 'data-bbai-missing-count', String( counts.missing ) );
				root.setAttribute( 'data-bbai-weak-count', String( counts.review ) );
				root.setAttribute( 'data-bbai-optimized-count', String( counts.complete ) );
				root.setAttribute( 'data-bbai-total-count', String( counts.total ) );
				root.setAttribute( 'data-bbai-generated-count', String( counts.complete ) );
				if ( countsHash ) {
					root.setAttribute( 'data-bbai-counts-hash', countsHash );
				}
			}

			// Credits / plan state are safe to sync from truth.
			root.setAttribute( 'data-bbai-credits-used', String( credits.used ) );
			root.setAttribute( 'data-bbai-credits-total', String( credits.total ) );
			root.setAttribute( 'data-bbai-credits-remaining', String( credits.remaining ) );
			root.setAttribute( 'data-bbai-is-premium', credits.isPro ? '1' : '0' );
		}

		if ( loggedInRoot ) {
			loggedInRoot.setAttribute( 'data-state', state );
			// Only sync the counts hash when we are not preserving local scan counts.
			if ( ! preserveLocalCounts && countsHash ) {
				loggedInRoot.setAttribute( 'data-bbai-counts-hash', countsHash );
			}
		}

		// Keep the hero config consistent with what's on the dashboard root (local scan).
		BBAI_HERO_CFG.missingCount = getDashboardRootMissingCount();

		syncHeroCreditBlockFromTruth( truth );

		if ( root && typeof window.jQuery !== 'undefined' ) {
			window.jQuery( document ).trigger( 'bbai:usage-updated' );
		}
	}

	function syncRetentionStripAction( link, label, href, action, bbaiAction ) {
		if ( ! link ) {
			return;
		}
		if ( link.textContent !== label ) {
			link.textContent = label;
		}
		link.setAttribute( 'href', href || '#' );
		if ( action ) {
			link.setAttribute( 'data-action', action );
		} else {
			link.removeAttribute( 'data-action' );
		}
		if ( bbaiAction ) {
			link.setAttribute( 'data-bbai-action', bbaiAction );
		} else {
			link.removeAttribute( 'data-bbai-action' );
		}
	}

	function syncRetentionStripFromTruth( truth ) {
		var strip = document.querySelector( '[data-bbai-retention-strip="1"].bbai-retention-strip' );
		var counts;
		var missing;
		var review;
		var complete;
		var total;
		var attention;
		var pct;
		var headline;
		var supporting;
		var processed;
		var primaryLabel;
		var primaryHref;
		var primaryAction;
		var primaryBbaiAction;
		var secondaryLabel;
		var secondaryHref;
		var secondaryAction;
		var secondaryBbaiAction;
		var headlineNode;
		var supportingNode;
		var deltaNode;
		var bar;
		var fill;
		var processedNode;
		var actions;
		var primary;
		var secondary;
		var hint;

		if ( ! strip ) {
			return;
		}

		counts = getTruthCounts( truth );
		missing = Math.max( 0, counts.missing || 0 );
		review = Math.max( 0, counts.review || 0 );
		complete = Math.max( 0, counts.complete || 0 );
		total = Math.max( missing + review + complete, counts.total || 0 );
		attention = missing + review;
		pct = total > 0 ? Math.max( 0, Math.min( 100, Math.round( 100 * complete / total ) ) ) : 0;

		if ( attention <= 0 ) {
			pct = 100;
			headline = '<?php echo esc_js( __( 'You’re 100% optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			supporting = '<?php echo esc_js( __( 'All images are done. nAi will keep watching new uploads.', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			processed = formatCount( total ) + ' / ' + formatCount( total ) + ' <?php echo esc_js( __( 'images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryLabel = TEXT.uploadMoreImages || '<?php echo esc_js( __( 'Upload new images', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryHref = '<?php echo esc_js( admin_url( 'upload.php' ) ); ?>';
			primaryAction = 'navigate';
			primaryBbaiAction = '';
			secondaryLabel = '<?php echo esc_js( __( 'View library status', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			secondaryHref = getLibraryHref();
			secondaryAction = 'navigate';
			secondaryBbaiAction = '';
			strip.setAttribute( 'data-bbai-retention-trigger', 'all_clear' );
		} else if ( missing > 0 ) {
			headline = '<?php echo esc_js( __( 'You’re %1$s% optimised — finish the last %2$s%', 'beepbeep-ai-alt-text-generator' ) ); ?>'
				.replace( '%1$s', String( pct ) )
				.replace( '%2$s', String( Math.max( 1, 100 - pct ) ) );
			supporting = formatSingularPlural(
				attention,
				'<?php echo esc_js( __( '%s image still needs attention.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
				'<?php echo esc_js( __( '%s images still need attention.', 'beepbeep-ai-alt-text-generator' ) ); ?>'
			);
			processed = formatCount( complete ) + ' / ' + formatCount( total ) + ' <?php echo esc_js( __( 'images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryLabel = TEXT.generateMissingAlt || '<?php echo esc_js( __( 'Generate ALT Text Now', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryHref = '#';
			primaryAction = 'generate-missing';
			primaryBbaiAction = 'generate_missing';
			secondaryLabel = '<?php echo esc_js( __( 'Review queue', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			secondaryHref = getLibraryHref();
			secondaryAction = 'navigate';
			secondaryBbaiAction = '';
			strip.setAttribute( 'data-bbai-retention-trigger', 'missing_alt' );
		} else {
			headline = formatSingularPlural(
				review,
				'<?php echo esc_js( __( '%s image needs review', 'beepbeep-ai-alt-text-generator' ) ); ?>',
				'<?php echo esc_js( __( '%s images need review', 'beepbeep-ai-alt-text-generator' ) ); ?>'
			);
			supporting = formatSingularPlural(
				review,
				'<?php echo esc_js( __( '%s image still needs attention.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
				'<?php echo esc_js( __( '%s images still need attention.', 'beepbeep-ai-alt-text-generator' ) ); ?>'
			);
			processed = formatCount( complete ) + ' / ' + formatCount( total ) + ' <?php echo esc_js( __( 'images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryLabel = '<?php echo esc_js( __( 'Review now', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			primaryHref = getReviewLibraryHref();
			primaryAction = 'navigate';
			primaryBbaiAction = '';
			secondaryLabel = '<?php echo esc_js( __( 'Review ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>';
			secondaryHref = getReviewLibraryHref();
			secondaryAction = 'navigate';
			secondaryBbaiAction = '';
			strip.setAttribute( 'data-bbai-retention-trigger', 'needs_review' );
		}

		headlineNode = strip.querySelector( '.bbai-retention-strip__headline' );
		supportingNode = strip.querySelector( '.bbai-retention-strip__supporting' );
		deltaNode = strip.querySelector( '.bbai-retention-strip__delta' );
		bar = strip.querySelector( '.bbai-retention-strip__bar' );
		fill = strip.querySelector( '.bbai-retention-strip__bar-fill' );
		processedNode = strip.querySelector( '.bbai-retention-strip__processed' );
		actions = strip.querySelector( '.bbai-retention-strip__actions' );
		primary = actions ? actions.querySelector( '.bbai-btn-primary' ) : null;
		secondary = actions ? actions.querySelector( '.bbai-btn-secondary' ) : null;
		hint = strip.querySelector( '.bbai-retention-strip__upgrade-hint' );

		if ( headlineNode && headlineNode.textContent !== headline ) {
			headlineNode.textContent = headline;
		}
		if ( supportingNode && supportingNode.textContent !== supporting ) {
			supportingNode.textContent = supporting;
		}
		if ( deltaNode ) {
			deltaNode.textContent = '';
			deltaNode.hidden = true;
		}
		if ( bar ) {
			bar.setAttribute( 'aria-valuenow', String( pct ) );
		}
		if ( fill ) {
			fill.style.width = String( pct ) + '%';
		}
		if ( processedNode && processedNode.textContent !== processed ) {
			processedNode.textContent = processed;
		}
		syncRetentionStripAction( primary, primaryLabel, primaryHref, primaryAction, primaryBbaiAction );
		syncRetentionStripAction( secondary, secondaryLabel, secondaryHref, secondaryAction, secondaryBbaiAction );
		if ( hint && attention <= 0 ) {
			hint.textContent = '<?php echo esc_js( __( 'Keep every new upload optimised with Growth.', 'beepbeep-ai-alt-text-generator' ) ); ?>';
		}
	}

	function renderActivityStripItems( strip, tone, items, signalIndex ) {
		if ( ! strip ) {
			return;
		}

		Array.prototype.slice.call( strip.classList ).forEach( function ( className ) {
			if ( 0 === className.indexOf( 'bbai-li-activity-strip--' ) ) {
				strip.classList.remove( className );
			}
		} );
		strip.classList.add( 'bbai-li-activity-strip--' + tone );
		strip.innerHTML = '';

		var dot = document.createElement( 'span' );
		dot.className = 'bbai-li-activity-strip__dot';
		dot.setAttribute( 'aria-hidden', 'true' );
		strip.appendChild( dot );

		items.forEach( function ( text, index ) {
			var item;
			if ( index > 0 ) {
				var sep = document.createElement( 'span' );
				sep.className = 'bbai-li-activity-strip__sep';
				sep.setAttribute( 'aria-hidden', 'true' );
				sep.textContent = ' · ';
				strip.appendChild( sep );
			}
			item = document.createElement( 'span' );
			item.className = 'bbai-li-activity-strip__item';
			if ( index === signalIndex ) {
				item.setAttribute( 'data-bbai-li-queued-signal', '1' );
			}
			item.textContent = text;
			strip.appendChild( item );
		} );
	}

	function renderActivityStripFromTruth( truth, meta ) {
		var strips = document.querySelectorAll( '[data-bbai-li-activity-strip="1"]' );
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );
		var job = getTruthJob( truth );
		var lastRunAt = getLastRunAt( truth );
		var items = [];
		var tone = 'neutral';
		var signalIndex = -1;
		var checkedAge;

		if ( ! strips.length ) {
			return;
		}

		switch ( state ) {
			case 'QUEUED':
				tone = 'queued';
				if ( job && job.total > 0 ) {
					items.push( formatSingularPlural( job.total, TEXT.queuedReadySingular, TEXT.queuedReadyPlural ) );
				}
				var checkedAt = job ? pickLastCheckedAt( job.lastCheckedAt ) : pickLastCheckedAt( 0 );
				// If we refreshed within the last minute, always show "just now".
				if ( checkedAt && ( Date.now() - checkedAt ) < 60000 ) {
					checkedAge = TEXT.lastCheckedJustNow;
				} else if ( checkedAt ) {
					checkedAge = getQueuedSignalText( Math.max( 0, Math.floor( ( Date.now() - checkedAt ) / 1000 ) ) );
				} else {
					checkedAge = TEXT.lastCheckedJustNow;
				}
				items.push( checkedAge );
				signalIndex = items.length - 1;
				break;
			case 'PROCESSING':
				tone = 'scanning';
				items.push( TEXT.processingStrip );
				if ( job && job.done > 0 ) {
					items.push( formatSingularPlural( job.done, TEXT.processedCountSingular, TEXT.processedCountPlural ) );
				}
				if ( job && job.total > job.done ) {
					items.push( formatSingularPlural( Math.max( 0, job.total - job.done ), TEXT.remainingCountSingular, TEXT.remainingCountPlural ) );
				}
				break;
			case 'MIXED_ATTENTION':
				/* Counts are in the hero status row only. */
				tone = 'alert';
				break;
			case 'MISSING_ALT':
				tone = 'alert';
				if ( lastRunAt ) {
					items.push( replaceTokens( TEXT.lastRun, { '%s': formatRelativeAge( lastRunAt ) } ) );
				}
				break;
			case 'NEEDS_REVIEW':
				tone = 'warn';
				if ( lastRunAt ) {
					items.push( replaceTokens( TEXT.lastRun, { '%s': formatRelativeAge( lastRunAt ) } ) );
				}
				break;
			case 'ALL_CLEAR':
				tone = 'ok';
				items.push( TEXT.readyForNewUploads );
				if ( credits.remaining > 0 ) {
					items.push( formatSingularPlural( credits.remaining, TEXT.creditLeftSingular, TEXT.creditLeftPlural ) );
				}
				if ( lastRunAt ) {
					items.push( replaceTokens( TEXT.lastRun, { '%s': formatRelativeAge( lastRunAt ) } ) );
				}
				break;
			case 'QUOTA_EXHAUSTED':
				tone = 'alert';
				items.push( TEXT.outOfCredits );
				items.push( TEXT.addMoreToContinue );
				break;
			case 'ERROR':
				tone = 'alert';
				items.push( TEXT.generationPaused );
				items.push( TEXT.checkSettingsToContinue );
				break;
			default:
				items.push( TEXT.libraryReadyToOptimise );
				break;
		}

		items = items.slice( 0, 3 );

		if ( ! items.length ) {
			Array.prototype.forEach.call( strips, function ( strip ) {
				if ( strip.hidden && strip.getAttribute( 'data-bbai-li-activity-signature' ) === 'hidden' ) {
					return;
				}
				strip.setAttribute( 'data-bbai-li-activity-signature', 'hidden' );
				strip.innerHTML = '';
				strip.hidden = true;
				strip.setAttribute( 'aria-hidden', 'true' );
			} );
			logStatusStrip( 'activity_strip_write', buildStatusStripLogContext( truth, meta || buildStatusStripMeta( 'truth_activity_strip', 'renderActivityStripFromTruth' ), '', 'activity_strip', 'clear' ) );
			return;
		}

		Array.prototype.forEach.call( strips, function ( strip ) {
			var nextSignature = JSON.stringify( {
				tone: tone,
				items: items,
				signalIndex: signalIndex,
			} );
			if ( strip.getAttribute( 'data-bbai-li-activity-signature' ) === nextSignature ) {
				return;
			}
			strip.setAttribute( 'data-bbai-li-activity-signature', nextSignature );
			strip.hidden = false;
			strip.removeAttribute( 'aria-hidden' );
			renderActivityStripItems( strip, tone, items, signalIndex );
		} );
		logStatusStrip( 'activity_strip_write', buildStatusStripLogContext( truth, meta || buildStatusStripMeta( 'truth_activity_strip', 'renderActivityStripFromTruth' ), items.join( ' | ' ), 'activity_strip', 'write' ) );
	}

	function renderImpactLineFromTruth( truth, meta ) {
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var complete = counts.complete;
		var line = document.querySelector( '.bbai-li-impact-line' );
		var host = getLoggedInDashboardRoot();
		var nextText = '';
		var stripMeta = meta || buildStatusStripMeta( 'truth_impact_line', 'renderImpactLineFromTruth' );

		switch ( state ) {
			case 'ALL_CLEAR':
				nextText = complete > 0
					? formatSingularPlural( complete, TEXT.impactAllClearSingle, TEXT.impactAllClearPlural )
					: TEXT.impactAllClearFallback;
				break;
			case 'MIXED_ATTENTION':
				nextText = '';
				break;
			case 'MISSING_ALT':
				if ( complete > 0 ) {
					nextText = formatSingularPlural( complete, TEXT.impactSoFarSingle, TEXT.impactSoFarPlural );
				}
				break;
			case 'NEEDS_REVIEW':
				if ( complete > 0 ) {
					nextText = formatSingularPlural( complete, TEXT.impactSoFarSingle, TEXT.impactSoFarPlural );
				}
				break;
		}

		if ( ! nextText ) {
			if ( line ) {
				logStatusStrip( 'impact_line_write', buildStatusStripLogContext( truth, stripMeta, line.textContent || '', 'impact_line', 'remove' ) );
				line.remove();
			}
			return;
		}

		if ( ! line && host ) {
			var belowBand = host.querySelector( '.bbai-li-hero-below' );
			var appendHost  = belowBand || host;
			line = document.createElement( 'p' );
			line.className = 'bbai-li-impact-line';
			appendHost.appendChild( line );
		}

		if ( line ) {
			if ( line.textContent !== nextText ) {
				line.textContent = nextText;
				logStatusStrip( 'impact_line_write', buildStatusStripLogContext( truth, stripMeta, nextText, 'impact_line', 'write' ) );
			}
		}
	}

	function getSummaryItemByLabel( labelText ) {
		var key = String( labelText || '' ).trim().toLowerCase();
		var countEl;
		if ( key === String( TEXT.missingLabel || '' ).trim().toLowerCase() ) {
			countEl = hero.querySelector( '.bbai-status-summary [data-bbai-status-metric="missing"]' );
			return countEl ? countEl.closest( '.bbai-status-item' ) : null;
		}
		if ( key === String( TEXT.reviewLabel || '' ).trim().toLowerCase() ) {
			countEl = hero.querySelector( '.bbai-status-summary [data-bbai-status-metric="review"]' );
			return countEl ? countEl.closest( '.bbai-status-item' ) : null;
		}
		if ( key === String( TEXT.creditsLeftLabel || '' ).trim().toLowerCase() || key === String( TEXT.creditsRemainingLabel || '' ).trim().toLowerCase() || key === String( TEXT.creditsLabel || '' ).trim().toLowerCase() ) {
			/* Credits are no longer in the hero status row; read from dashboard root. */
			return getDashboardRoot() || null;
		}
		return null;
	}

	function getDisplayedCreditsSummary() {
		var root = getDashboardRoot();
		var rem;
		var tot;
		if ( ! root ) {
			return null;
		}
		rem = root.getAttribute( 'data-bbai-credits-remaining' );
		tot = root.getAttribute( 'data-bbai-credits-total' );
		if ( null == rem && null == tot ) {
			return null;
		}
		return {
			displayed_label: ( 'ALL_CLEAR' === ( hero.getAttribute( 'data-bbai-li-state' ) || '' ) && parseInt( rem, 10 ) > 0 )
				? String( TEXT.creditsRemainingLabel || '' ).trim()
				: String( TEXT.creditsLeftLabel || '' ).trim(),
			displayed_value: formatHeroCreditDashboardLabel( rem ),
		};
	}

	function updateSummaryValueByLabel( labelText, value, mod ) {
		var item = getSummaryItemByLabel( labelText );
		var valueNode;
		var key = String( labelText || '' ).trim().toLowerCase();

		if ( ! item ) {
			return;
		}

		if ( item === getDashboardRoot() && ( key === String( TEXT.creditsLeftLabel || '' ).trim().toLowerCase() || key === String( TEXT.creditsRemainingLabel || '' ).trim().toLowerCase() || key === String( TEXT.creditsLabel || '' ).trim().toLowerCase() ) ) {
			return;
		}

		valueNode = item.querySelector( '.bbai-count' );
		if ( valueNode ) {
			valueNode.textContent = String( value );
			valueNode.classList.add( 'bbai-li-summary__value--updating' );
			window.setTimeout( function () {
				valueNode.classList.remove( 'bbai-li-summary__value--updating' );
			}, 420 );
		}
	}

	function clearDashboardTransitionFeedback() {
		var dashboard = getLoggedInDashboardRoot();
		hero.classList.remove( 'bbai-li-hero--state-transition', 'bbai-li-hero--transition-success' );
		if ( dashboard ) {
			dashboard.classList.remove( 'bbai-li-dashboard--state-transition', 'bbai-li-dashboard--transition-success' );
		}
	}

	function triggerDashboardTransitionFeedback( fromState, toState ) {
		var dashboard = getLoggedInDashboardRoot();
		var isSuccessTransition = ( 'PROCESSING' === fromState && 'NEEDS_REVIEW' === toState ) || ( 'NEEDS_REVIEW' === fromState && 'ALL_CLEAR' === toState );

		if ( ! fromState || ! toState || fromState === toState ) {
			return;
		}

		if ( transitionPulseTimer ) {
			window.clearTimeout( transitionPulseTimer );
			transitionPulseTimer = null;
		}
		if ( successHighlightTimer ) {
			window.clearTimeout( successHighlightTimer );
			successHighlightTimer = null;
		}

		clearDashboardTransitionFeedback();
		void hero.offsetWidth;
		hero.classList.add( 'bbai-li-hero--state-transition' );
		if ( dashboard ) {
			dashboard.classList.add( 'bbai-li-dashboard--state-transition' );
		}

		transitionPulseTimer = window.setTimeout( function () {
			hero.classList.remove( 'bbai-li-hero--state-transition' );
			if ( dashboard ) {
				dashboard.classList.remove( 'bbai-li-dashboard--state-transition' );
			}
			transitionPulseTimer = null;
		}, 220 );

		if ( ! isSuccessTransition ) {
			return;
		}

		hero.classList.add( 'bbai-li-hero--transition-success' );
		if ( dashboard ) {
			dashboard.classList.add( 'bbai-li-dashboard--transition-success' );
		}
		showTransientStatusLine(
			'NEEDS_REVIEW' === toState ? TEXT.batchReadyForReview : TEXT.reviewComplete,
			'success',
			1700,
			buildStatusStripMeta( 'transition_success', 'triggerDashboardTransitionFeedback' )
		);
		successHighlightTimer = window.setTimeout( function () {
			hero.classList.remove( 'bbai-li-hero--transition-success' );
			if ( dashboard ) {
				dashboard.classList.remove( 'bbai-li-dashboard--transition-success' );
			}
			successHighlightTimer = null;
		}, 1700 );
	}

	function cancelProcessingAnimation() {
		processingAnimationToken += 1;
		if ( processingAnimationFrame ) {
			window.cancelAnimationFrame( processingAnimationFrame );
			processingAnimationFrame = null;
		}
		if ( processingAnimationTimer ) {
			window.clearTimeout( processingAnimationTimer );
			processingAnimationTimer = null;
		}
		hero.removeAttribute( 'data-bbai-li-live-progress' );
		var dashboard = getLoggedInDashboardRoot();
		if ( dashboard ) {
			dashboard.removeAttribute( 'data-bbai-li-live-progress' );
		}
	}

	function interpolateNumber( start, end, progress ) {
		return start + ( end - start ) * progress;
	}

	function interpolateInteger( start, end, progress ) {
		return Math.round( interpolateNumber( start, end, progress ) );
	}

	function buildInterpolatedProcessingTruth( previousTruth, nextTruth, progress ) {
		var previousCounts = getTruthCounts( previousTruth );
		var nextCounts = getTruthCounts( nextTruth );
		var previousJob = getTruthJob( previousTruth );
		var nextJob = getTruthJob( nextTruth );
		var nextJobRaw = nextTruth && nextTruth.job && typeof nextTruth.job === 'object' ? nextTruth.job : {};
		var previousEta = previousJob && null !== previousJob.etaSeconds ? previousJob.etaSeconds : null;
		var nextEta = nextJob && null !== nextJob.etaSeconds ? nextJob.etaSeconds : null;
		var frameEta = null;

		if ( null !== previousEta || null !== nextEta ) {
			frameEta = interpolateInteger(
				null === previousEta ? ( nextEta || 0 ) : previousEta,
				null === nextEta ? 0 : nextEta,
				progress
			);
			frameEta = Math.max( 0, frameEta );
		}

		return {
			state: 'PROCESSING',
			counts: {
				missing: interpolateInteger( previousCounts.missing, nextCounts.missing, progress ),
				review: interpolateInteger( previousCounts.review, nextCounts.review, progress ),
				complete: interpolateInteger( previousCounts.complete, nextCounts.complete, progress ),
				failed: nextCounts.failed,
				total: nextCounts.total,
			},
			credits: nextTruth && nextTruth.credits && typeof nextTruth.credits === 'object' ? nextTruth.credits : {},
			job: {
				active: !! ( nextJob && nextJob.active ),
				pausable: !! ( nextJob && nextJob.pausable ),
				status: nextJob ? nextJob.status : 'processing',
				done: interpolateInteger( previousJob ? previousJob.done : 0, nextJob ? nextJob.done : 0, progress ),
				total: nextJob ? nextJob.total : ( previousJob ? previousJob.total : 0 ),
				eta_seconds: frameEta,
				last_checked_at: nextJobRaw.last_checked_at || nextJobRaw.lastCheckedAt || nextJobRaw.checked_at || nextJobRaw.checkedAt || '',
			},
			last_run_at: nextTruth && ( nextTruth.last_run_at || nextTruth.lastRunAt ) ? ( nextTruth.last_run_at || nextTruth.lastRunAt ) : '',
		};
	}

	function canAnimateProcessingUpdate( previousTruth, nextTruth ) {
		var previousJob = getTruthJob( previousTruth );
		var nextJob = getTruthJob( nextTruth );
		var previousCounts = getTruthCounts( previousTruth );
		var nextCounts = getTruthCounts( nextTruth );

		if ( 'PROCESSING' !== getStateTruthState( previousTruth ) || 'PROCESSING' !== getStateTruthState( nextTruth ) ) {
			return false;
		}
		if ( ! previousJob || ! nextJob || nextJob.total <= 0 ) {
			return false;
		}

		return previousJob.done !== nextJob.done ||
			previousCounts.missing !== nextCounts.missing ||
			previousCounts.complete !== nextCounts.complete ||
			previousCounts.review !== nextCounts.review ||
			previousJob.etaSeconds !== nextJob.etaSeconds;
	}

	function maybeRunProcessingTruthAnimation( previousTruth, nextTruth ) {
		var dashboard = getLoggedInDashboardRoot();
		var duration = 950;

		if ( ! previousTruth || 'function' !== typeof window.bbaiRenderLoggedInDashboardHeroState ) {
			return null;
		}
		if ( ! canAnimateProcessingUpdate( previousTruth, nextTruth ) ) {
			cancelProcessingAnimation();
			return null;
		}

		cancelProcessingAnimation();
		hero.setAttribute( 'data-bbai-li-live-progress', '1' );
		if ( dashboard ) {
			dashboard.setAttribute( 'data-bbai-li-live-progress', '1' );
		}

		return new Promise( function ( resolve ) {
			var startedAt = Date.now();
			processingAnimationToken += 1;
			var myToken = processingAnimationToken;

			function tick() {
				if ( myToken !== processingAnimationToken ) {
					resolve();
					return;
				}

				var elapsed = Date.now() - startedAt;
				var frameProgress = Math.min( 1, elapsed / duration );
				var eased = 1 - Math.pow( 1 - frameProgress, 3 );
				var frameTruth = buildInterpolatedProcessingTruth( previousTruth, nextTruth, eased );

				window.bbaiRenderLoggedInDashboardHeroState( buildProcessingStateData( frameTruth ), {
					skipCtas: true,
					skipUpgradeFloat: true,
					skipReviewSurface: true,
					skipDashboardAttrs: true,
				} );
				renderActivityStripFromTruth( frameTruth, buildStatusStripMeta( 'processing_animation_frame', 'animateProcessingTruthUpdate' ) );

				if ( frameProgress >= 1 ) {
					if ( processingAnimationTimer ) {
						window.clearTimeout( processingAnimationTimer );
						processingAnimationTimer = null;
					}
					cancelProcessingAnimation();
					window.bbaiRenderLoggedInDashboardHeroState( buildProcessingStateData( nextTruth ), {
						skipCtas: true,
						skipUpgradeFloat: true,
						skipReviewSurface: true,
						skipDashboardAttrs: true,
					} );
					renderActivityStripFromTruth( nextTruth, buildStatusStripMeta( 'processing_animation_complete', 'animateProcessingTruthUpdate' ) );
					resolve();
					return;
				}

				processingAnimationTimer = window.setTimeout( tick, 16 );
			}

			tick();
		} );
	}

	function domTruthMismatch( truth ) {
		var root = getDashboardRoot();
		var state = getStateTruthState( truth );
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );

		if ( ! root ) {
			return true;
		}

		if ( ( hero.getAttribute( 'data-bbai-li-state' ) || '' ) !== state ) {
			return true;
		}

		if ( parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) !== counts.missing ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-weak-count' ) || '0', 10 ) !== counts.review ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-optimized-count' ) || '0', 10 ) !== counts.complete ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-total-count' ) || '0', 10 ) !== counts.total ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-credits-used' ) || '0', 10 ) !== credits.used ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-credits-total' ) || '1', 10 ) !== credits.total ) {
			return true;
		}
		if ( parseInt( root.getAttribute( 'data-bbai-credits-remaining' ) || '0', 10 ) !== credits.remaining ) {
			return true;
		}
		if ( 'PROCESSING' === state && hasVisiblePauseCta() !== isPauseAllowed( truth ) ) {
			return true;
		}

		return false;
	}

	function shouldUseResolvedDashboard( truth, previousTruth ) {
		var nextState = getStateTruthState( truth );
		var prevState;
		if ( ! previousTruth ) {
			if ( 'QUEUED' === nextState || 'PROCESSING' === nextState ) {
				return false;
			}
			return 'NEEDS_REVIEW' === nextState;
		}
		prevState = getStateTruthState( previousTruth );
		// Queued ↔ processing: same truth source as state-truth; skip extra /dashboard fetch so
		// hero hydrates from buildQueuedStateData / buildProcessingStateData without a second network dependency.
		if ( nextState !== prevState ) {
			if (
				( 'QUEUED' === prevState && 'PROCESSING' === nextState ) ||
				( 'PROCESSING' === prevState && 'QUEUED' === nextState )
			) {
				return false;
			}
			return true;
		}
		if ( 'NEEDS_REVIEW' === nextState ) {
			return true;
		}
		if ( 'PROCESSING' === nextState && isPauseAllowed( truth ) !== isPauseAllowed( previousTruth ) ) {
			return true;
		}
		return false;
	}

	function commitTruthSnapshot( truth, context, logApplied, options ) {
		var opts = options || {};
		var job = getTruthJob( truth );
		var displayedCredits = null;
		var resolvedCounts = getTruthCounts( truth );
		var root = null;
		var statusStripMeta = buildStatusStripMeta( context || '', 'commitTruthSnapshot' );
		clearOptimisticAction();
		dashboardPolling.currentTruth = truth;
		dashboardPolling.currentSignature = buildTruthSignature( truth );
		rememberDashboardRenderSignature( truth );
		dashboardPolling.latestState = getStateTruthState( truth );
		dashboardPolling.failureCount = 0;
		dashboardPolling.requiresResolvedSync = false;
		dashboardPolling.bootstrapSyncApplied = false;
		syncDashboardRootFromTruth( truth );
		updateInsightCardsFromTruth( truth );
		syncRetentionStripFromTruth( truth );
		if ( ! opts.skipActivityStrip ) {
			renderActivityStripFromTruth( truth, statusStripMeta );
		}
		if ( ! opts.skipImpactLine ) {
			renderImpactLineFromTruth( truth, statusStripMeta );
		}
		if ( ! opts.skipStatusLine ) {
			syncStatusLineForTruth( truth, statusStripMeta );
		}
		displayedCredits = getDisplayedCreditsSummary();
		if ( displayedCredits ) {
			logCredits( 'displayed_value', Object.assign(
				{
					context: context || '',
					state: dashboardPolling.latestState,
					resolved_credits: getTruthCredits( truth ),
				},
				displayedCredits
			) );
		}
		root = getDashboardRoot();
		logCounts( 'applied_truth', {
			context: context || '',
			state: dashboardPolling.latestState,
			resolved_counts: resolvedCounts,
			rendered_counts: {
				missing: root ? parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) || 0 : 0,
				review: root ? parseInt( root.getAttribute( 'data-bbai-weak-count' ) || '0', 10 ) || 0 : 0,
				total: root ? parseInt( root.getAttribute( 'data-bbai-total-count' ) || '0', 10 ) || 0 : 0,
			},
		} );
		if ( logApplied ) {
			logDashboardUi( 'state_truth_applied', {
				context: context || '',
				state: dashboardPolling.latestState,
				job_status: job ? job.status : '',
			} );
		}
		schedulePolling( 'state_applied' );
	}

	function isSameHeroPayload( current, nextHero ) {
		if ( ! current || ! nextHero ) {
			return false;
		}
		var nextPrimary = nextHero.primary_cta || nextHero.primaryCta || null;
		var nextSecondary = nextHero.secondary_cta || nextHero.secondaryCta || null;
		return (
			String( current.headline || '' ) === String( nextHero.headline || '' )
			&& String( current.support || '' ) === String( nextHero.support || '' )
			&& String( ( current.primaryCta && current.primaryCta.label ) || '' ) === String( ( nextPrimary && nextPrimary.label ) || '' )
			&& String( ( current.secondaryCta && current.secondaryCta.label ) || '' ) === String( ( nextSecondary && nextSecondary.label ) || '' )
		);
	}

	function shouldSkipDashboardStateApply( stateData ) {
		if ( ! stateData || ! stateData.hero ) {
			return false;
		}
		var current = getCurrentHeroCopyData();
		return isSameHeroPayload( current, stateData.hero );
	}

	function applyLocalActiveTruthState( truth, context ) {
		var stateData = buildLocalActiveStateData( truth );
		if ( ! stateData || 'function' !== typeof window.bbaiApplyLoggedInDashboardStatePayload ) {
			return false;
		}
		// Avoid double-render/hydration flicker: if SSR already matches, skip applying.
		if ( shouldSkipDashboardStateApply( stateData ) ) {
			commitTruthSnapshot( truth, context, true );
			return true;
		}
		logRender( 'fallback_state', {
			context: context || '',
			state: stateData.state || getStateTruthState( truth ),
			counts: getTruthCounts( truth ),
		} );
		cancelProcessingAnimation();
		clearOptimisticAction();
		window.bbaiApplyLoggedInDashboardStatePayload( stateData );
		commitTruthSnapshot( truth, context, true );
		return true;
	}

	function applyResolvedDashboardTruth( truth, context ) {
		return fetchDashboardState( context )
			.then( function ( stateData ) {
				var truthState = getStateTruthState( truth );
				if ( 'function' !== typeof window.bbaiApplyLoggedInDashboardStatePayload ) {
					throw new Error( 'dashboard_state_applier_missing' );
				}
				if ( String( stateData.state || '' ).toUpperCase() !== truthState ) {
					throw new Error( 'dashboard_state_mismatch' );
				}
				if ( shouldSkipDashboardStateApply( stateData ) ) {
					commitTruthSnapshot( truth, context, true );
					return truth;
				}
				logRender( 'resolved_dashboard_success', {
					context: context || '',
					state: stateData.state || '',
					truth_state: truthState,
				} );
				cancelProcessingAnimation();
				clearOptimisticAction();
				window.bbaiApplyLoggedInDashboardStatePayload( stateData );
				commitTruthSnapshot( truth, context, true );
				return truth;
			} )
			.catch( function ( error ) {
				logRender( 'resolved_dashboard_failed', {
					context: context || '',
					state: getStateTruthState( truth ),
					message: error && error.message ? error.message : String( error || '' ),
				} );
				if ( applyLocalActiveTruthState( truth, context ) ) {
					return truth;
				}
				dashboardPolling.requiresResolvedSync = true;
				throw error;
			} );
	}

	function markTruthSeenWithoutUiUpdate( truth ) {
		dashboardPolling.currentTruth = truth;
		dashboardPolling.currentSignature = buildTruthSignature( truth );
		rememberDashboardRenderSignature( truth );
		dashboardPolling.latestState = getStateTruthState( truth );
		dashboardPolling.failureCount = 0;
		dashboardPolling.bootstrapSyncApplied = false;
	}

	function shouldSkipTruthUiUpdate( truth, context, forceResolved ) {
		var nextRenderSignature = buildDashboardRenderSignature( truth );
		var lastRenderSignature = getLastDashboardRenderSignature();
		var incomingHash = getTruthCountsHash( truth );
		var currentHash = getCurrentCountsHash();
		var incomingCounts = getTruthCounts( truth );
		var currentCounts = getDomCounts();
		var currentState = hero.getAttribute( 'data-bbai-li-state' ) || '';
		var incomingState = getStateTruthState( truth );
		var root = getDashboardRoot();
		var incomingCredits = getTruthCredits( truth );

		// Never skip when the resolver state changes (QUEUED→PROCESSING, etc.).
		if ( incomingState !== currentState ) {
			return false;
		}
		if ( 'QUEUED' === incomingState || 'PROCESSING' === incomingState ) {
			return false;
		}

		if ( nextRenderSignature === lastRenderSignature ) {
			logRender( 'skip_unchanged_poll_render', {
				context: context || '',
				signature: nextRenderSignature,
			} );
			return true;
		}

		var domUsed = root ? parseInt( root.getAttribute( 'data-bbai-credits-used' ) || '', 10 ) : NaN;
		var domTotal = root ? parseInt( root.getAttribute( 'data-bbai-credits-total' ) || '', 10 ) : NaN;
		var domRemaining = root ? parseInt( root.getAttribute( 'data-bbai-credits-remaining' ) || '', 10 ) : NaN;
		if ( root ) {
			if ( ! isNaN( domUsed ) && domUsed !== incomingCredits.used ) {
				return false;
			}
			if ( ! isNaN( domTotal ) && domTotal !== incomingCredits.total ) {
				return false;
			}
			if ( ! isNaN( domRemaining ) && domRemaining !== incomingCredits.remaining ) {
				return false;
			}
		}
		var heroCredit = hero.querySelector( '[data-bbai-hero-credit-usage="1"]' );
		var heroUsed = heroCredit ? parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-used' ) || '', 10 ) : NaN;
		if ( ! isNaN( heroUsed ) && heroUsed !== incomingCredits.used ) {
			return false;
		}
		var heroLim = heroCredit ? parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-limit' ) || '', 10 ) : NaN;
		if ( ! isNaN( heroLim ) && heroLim !== incomingCredits.total ) {
			return false;
		}
		var heroRemaining = heroCredit ? parseInt( heroCredit.getAttribute( 'data-bbai-hero-credits-remaining' ) || '', 10 ) : NaN;
		if ( ! isNaN( heroRemaining ) && heroRemaining !== incomingCredits.remaining ) {
			return false;
		}

		if ( incomingHash && currentHash && incomingHash === currentHash ) {
			logDashboardUi( 'state_counts_hash_unchanged', {
				context: context || '',
				state: incomingState,
			} );
			return true;
		}

		if ( ! forceResolved && countsEqual( incomingCounts, currentCounts ) && incomingState === currentState ) {
			logDashboardUi( 'state_counts_unchanged', {
				context: context || '',
				state: incomingState,
			} );
			return true;
		}

		return false;
	}

	function mapTruthToStabilizedStateKey( truth ) {
		var s = String( getStateTruthState( truth ) || '' ).toUpperCase();
		if ( 'QUEUED' === s || 'PROCESSING' === s ) {
			return 'queued';
		}
		if ( 'NEEDS_REVIEW' === s ) {
			return 'review_ready';
		}
		if ( 'ALL_CLEAR' === s ) {
			return 'complete';
		}
		// Covers MISSING_ALT, MIXED_ATTENTION, and any attention-led state.
		return 'needs_alt';
	}

	function buildStabilizedTruthHash( truth ) {
		var counts = getTruthCounts( truth ) || { missing: 0, review: 0, complete: 0, failed: 0, total: 0 };
		var credits = getTruthCredits( truth ) || { used: 0, total: 1, remaining: 0 };
		var s = mapTruthToStabilizedStateKey( truth );
		var total = counts.total || ( counts.missing + counts.review + counts.complete + counts.failed );
		return [
			s,
			Math.max( 0, counts.missing || 0 ),
			Math.max( 0, counts.review || 0 ),
			Math.max( 0, counts.complete || 0 ),
			Math.max( 0, total || 0 ),
			Math.max( 0, credits.used || 0 ),
			Math.max( 1, credits.total || 1 ),
			Math.max( 0, credits.remaining || 0 ),
		].join( '|' );
	}

	function commitTruthPayload( truth, context, forceResolved ) {
		var previousTruth = dashboardPolling.currentTruth;
		var previousRenderSignature;
		var nextRenderSignature;
		var previousRenderParts;
		var nextRenderParts;
		if ( dashboardPolling.ssrRendered && ! dashboardPolling.hydrated && ! domTruthMismatch( truth ) ) {
			syncDashboardRootFromTruth( truth );
			syncRetentionStripFromTruth( truth );
			markTruthSeenWithoutUiUpdate( truth );
			schedulePolling( 'hydration_guard' );
			return Promise.resolve( truth );
		}
		if ( shouldSkipTruthUiUpdate( truth, context, forceResolved ) ) {
			syncCreditsOnlyFromTruth( truth );
			syncDashboardRootFromTruth( truth );
			syncRetentionStripFromTruth( truth );
			markTruthSeenWithoutUiUpdate( truth );
			schedulePolling( 'counts_unchanged' );
			return Promise.resolve( truth );
		}
		previousRenderSignature = getLastDashboardRenderSignature();
		nextRenderSignature = buildDashboardRenderSignature( truth );
		previousRenderParts = parseDashboardRenderSignature( previousRenderSignature );
		nextRenderParts = parseDashboardRenderSignature( nextRenderSignature );
		if (
			renderSignaturesShareStableModeAndCounts( previousRenderParts, nextRenderParts ) &&
			renderSignatureCreditsChanged( previousRenderParts, nextRenderParts )
		) {
			syncCreditsOnlyFromTruth( truth );
			markTruthSeenWithoutUiUpdate( truth );
			logRender( 'patch_credits_only_poll_render', {
				context: context || '',
				signature: nextRenderSignature,
			} );
			schedulePolling( 'credits_changed' );
			return Promise.resolve( truth );
		}
		if (
			previousRenderParts &&
			nextRenderParts &&
			previousRenderParts.mode === nextRenderParts.mode &&
			! nextRenderParts.generationInProgress &&
			'QUEUED' !== getStateTruthState( truth ) &&
			'PROCESSING' !== getStateTruthState( truth )
		) {
			syncCountsOnlyFromTruth( truth, context );
			markTruthSeenWithoutUiUpdate( truth );
			logRender( 'patch_counts_poll_render', {
				context: context || '',
				signature: nextRenderSignature,
			} );
			schedulePolling( 'counts_changed' );
			return Promise.resolve( truth );
		}
		if ( forceResolved ) {
			cancelProcessingAnimation();
			return applyResolvedDashboardTruth( truth, context );
		}
		var anim = previousTruth ? maybeRunProcessingTruthAnimation( previousTruth, truth ) : null;
		if ( anim ) {
			return anim.then( function () {
				commitTruthSnapshot( truth, context, true, {
					skipActivityStrip: true,
				} );
				if ( 'function' === typeof window.bbaiApplyLoggedInDashboardStatePayload && 'PROCESSING' === getStateTruthState( truth ) ) {
					window.bbaiApplyLoggedInDashboardStatePayload( buildProcessingStateData( truth ) );
				}
				return truth;
			} );
		}
		if ( applyLocalActiveTruthState( truth, context ) ) {
			return Promise.resolve( truth );
		}
		cancelProcessingAnimation();
		commitTruthSnapshot( truth, context, false );
		return Promise.resolve( truth );
	}

	function confirmLatestStateTruth( context, delayMs ) {
		return new Promise( function ( resolve, reject ) {
			window.setTimeout( function () {
				window.bbaiRequestDashboardStateRefresh( context )
				.then( resolve )
				.catch( reject );
			}, Math.max( 0, parseInt( delayMs, 10 ) || 0 ) );
		} );
	}

	function hasStartupDashboardDomMismatch() {
		var root = getDashboardRoot();
		var state = String( hero.getAttribute( 'data-bbai-li-state' ) || '' ).toUpperCase();
		var missing;
		var review;
		if ( ! root ) {
			return false;
		}
		missing = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) || 0 );
		review = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-weak-count' ) || '0', 10 ) || 0 );
		if ( ( 'QUEUED' === state || 'PROCESSING' === state || 'MISSING_ALT' === state ) && 0 === missing && review > 0 ) {
			return true;
		}
		if ( 'NEEDS_REVIEW' === state && 0 === review && missing > 0 ) {
			return true;
		}
		if ( 'NEEDS_REVIEW' === state && 0 === review && 0 === missing ) {
			return true;
		}
		if ( ( 'DONE' === state || 'ALL_CLEAR' === state ) && ( missing > 0 || review > 0 ) ) {
			return true;
		}
		return false;
	}

	function buildStartupTruthFromDashboardRoot() {
		var root = getDashboardRoot();
		var missing;
		var review;
		var complete;
		var failed;
		var total;
		var used;
		var limit;
		var remaining;
		var state;
		if ( ! root ) {
			return null;
		}
		missing = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) || 0 );
		review = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-weak-count' ) || '0', 10 ) || 0 );
		complete = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-optimized-count' ) || '0', 10 ) || 0 );
		failed = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-failed-count' ) || '0', 10 ) || 0 );
		total = Math.max( missing + review + complete + failed, parseInt( root.getAttribute( 'data-bbai-total-count' ) || '0', 10 ) || 0 );
		used = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-credits-used' ) || '0', 10 ) || 0 );
		limit = Math.max( 1, parseInt( root.getAttribute( 'data-bbai-credits-total' ) || '1', 10 ) || 1 );
		remaining = Math.max( 0, parseInt( root.getAttribute( 'data-bbai-credits-remaining' ) || String( limit - used ), 10 ) || 0 );
		if ( missing > 0 && review > 0 ) {
			state = 'MIXED_ATTENTION';
		} else if ( missing > 0 ) {
			state = 'MISSING_ALT';
		} else if ( review > 0 ) {
			state = 'NEEDS_REVIEW';
		} else {
			state = 'ALL_CLEAR';
		}
		return {
			state: state,
			counts: {
				missing: missing,
				review: review,
				complete: complete,
				failed: failed,
				total: total,
			},
			credits: {
				used: used,
				total: limit,
				limit: limit,
				remaining: Math.min( limit, remaining ),
				is_pro: root.getAttribute( 'data-bbai-is-premium' ) === '1',
			},
			job: {
				status: 'idle',
				active: false,
				pausable: false,
				done: 0,
				total: 0,
			},
			site: {
				site_hash: root.getAttribute( 'data-bbai-site-hash' ) || '',
			},
		};
	}

	function applyStartupDashboardRootTruth() {
		var truth;
		if ( ! hasStartupDashboardDomMismatch() || 'function' !== typeof window.bbaiApplyLoggedInDashboardStatePayload ) {
			return false;
		}
		truth = buildStartupTruthFromDashboardRoot();
		if ( ! truth ) {
			return false;
		}
		if ( applyLocalActiveTruthState( truth, 'startup_dom_snapshot' ) ) {
			markDashboardStartupTruthResolved();
			return true;
		}
		return false;
	}

	function markDashboardStartupTruthResolved() {
		var root;
		if ( hasStartupDashboardDomMismatch() ) {
			logRender( 'startup_hydration_waiting_for_consistent_dom', {
				state: hero.getAttribute( 'data-bbai-li-state' ) || '',
			} );
			return false;
		}
		root = document.getElementById( 'bbai-dashboard-main' );
		if ( root ) {
			root.setAttribute( 'data-bbai-startup-truth-resolved', '1' );
		}
		dashboardPolling.hydrated = true;
		if ( typeof window.bbaiMarkDashboardMainHydrated === 'function' ) {
			window.bbaiMarkDashboardMainHydrated();
		}
		return true;
	}

	function runPollingTick( context ) {
		var pollContext = context || 'polling';
		var currentState = dashboardPolling.latestState || hero.getAttribute( 'data-bbai-li-state' ) || '';

		// Stop background polling and UI churn during generation.
		if ( window.bbaiIsGenerating === true && pollContext !== 'generation_completed' && pollContext !== 'manual_rescan' && pollContext !== 'user_action' ) {
			stopPolling( 'generating_freeze' );
			return;
		}
		if ( dashboardPolling.inFlight ) {
			return;
		}
		if ( document.visibilityState === 'hidden' ) {
			stopPolling( 'hidden' );
			return;
		}
		if ( ! shouldPollState( currentState ) && ! dashboardPolling.requiresResolvedSync && ( 'startup' !== pollContext || ! hasStartupDashboardDomMismatch() ) ) {
			stopPolling( 'stable' );
			if ( 'startup' === pollContext ) {
				markDashboardStartupTruthResolved();
			}
			return;
		}

		dashboardPolling.inFlight = true;
		logDashboardUi( 'polling_tick', {
			context: pollContext,
			state: currentState,
		} );

		window.bbaiRequestDashboardStateRefresh( pollContext )
			.then( function ( truth ) {
				return maybeBootstrapTruth( truth, pollContext );
			} )
			.then( function ( truth ) {
				var previousTruth = dashboardPolling.currentTruth;
				var previousState = previousTruth ? getStateTruthState( previousTruth ) : ( hero.getAttribute( 'data-bbai-li-state' ) || '' );
				var nextState = getStateTruthState( truth );
				var nextSignature = buildTruthSignature( truth );
				var nextRenderSignature = buildDashboardRenderSignature( truth );
				var forceResolved = dashboardPolling.requiresResolvedSync || shouldUseResolvedDashboard( truth, previousTruth );

				if ( dashboardPolling.ssrRendered && ! dashboardPolling.hydrated && ! domTruthMismatch( truth ) ) {
					markTruthSeenWithoutUiUpdate( truth );
					schedulePolling( 'hydration_guard' );
					return;
				}

				if ( shouldSkipTruthUiUpdate( truth, pollContext, forceResolved ) ) {
					markTruthSeenWithoutUiUpdate( truth );
					schedulePolling( 'counts_unchanged' );
					return;
				}

				if ( ! previousTruth && ! forceResolved && ! dashboardPolling.bootstrapSyncApplied && ! domTruthMismatch( truth ) ) {
					var unchangedMeta = buildStatusStripMeta( pollContext, 'runPollingTick_state_unchanged' );
					dashboardPolling.currentTruth = truth;
					dashboardPolling.currentSignature = nextSignature;
					window.bbaiLastDashboardRenderSignature = nextRenderSignature;
					dashboardPolling.renderSignature = nextRenderSignature;
					window.bbaiPreviousDashboardMode = getDashboardRenderMode( nextState );
					dashboardPolling.latestState = nextState;
					dashboardPolling.failureCount = 0;
					dashboardPolling.bootstrapSyncApplied = false;
					syncDashboardRootFromTruth( truth );
					updateInsightCardsFromTruth( truth );
					renderActivityStripFromTruth( truth, unchangedMeta );
					renderImpactLineFromTruth( truth, unchangedMeta );
					syncStatusLineForTruth( truth, unchangedMeta );
					logDashboardUi( 'state_unchanged', {
						context: pollContext,
						state: nextState,
					} );
					schedulePolling( 'state_unchanged' );
					return;
				}

				if ( dashboardPolling.currentSignature && dashboardPolling.currentSignature === nextSignature && ! dashboardPolling.requiresResolvedSync && ! domTruthMismatch( truth ) ) {
					var signatureMeta = buildStatusStripMeta( pollContext, 'runPollingTick_signature_match' );
					dashboardPolling.currentTruth = truth;
					dashboardPolling.latestState = nextState;
					window.bbaiLastDashboardRenderSignature = nextRenderSignature;
					dashboardPolling.renderSignature = nextRenderSignature;
					window.bbaiPreviousDashboardMode = getDashboardRenderMode( nextState );
					dashboardPolling.failureCount = 0;
					dashboardPolling.bootstrapSyncApplied = false;
					syncDashboardRootFromTruth( truth );
					updateInsightCardsFromTruth( truth );
					renderActivityStripFromTruth( truth, signatureMeta );
					renderImpactLineFromTruth( truth, signatureMeta );
					syncStatusLineForTruth( truth, signatureMeta );
					logDashboardUi( 'state_unchanged', {
						context: pollContext,
						state: nextState,
					} );
					schedulePolling( 'state_unchanged' );
					return;
				}

				// Stabilize state transitions to prevent flicker/regressions.
				var stabilizer = window.BBAI_DASHBOARD_STATE_STABILIZER;
				var stabilizedKey = mapTruthToStabilizedStateKey( truth );
				var stabilizedHash = buildStabilizedTruthHash( truth );
				var isGeneratingNow = stabilizedKey === 'queued';

				var accepted = stabilizer && typeof stabilizer.receive === 'function'
					? stabilizer.receive( {
						key: stabilizedKey,
						hash: stabilizedHash,
						reason: pollContext,
						generating: isGeneratingNow,
						apply: function () {
							return commitTruthPayload( truth, pollContext, forceResolved )
								.then( function () {
									dashboardPolling.bootstrapSyncApplied = false;
									logDashboardUi( 'state_updated', {
										context: pollContext,
										from_state: previousState,
										to_state: nextState,
										mode: forceResolved ? 'resolved' : 'local',
									} );
								} );
						}
					} )
					: false;

				if ( accepted ) {
					// Render will happen via debounced apply().
					schedulePolling( 'stabilized_pending' );
					return;
				}

				// Keep internal truth up to date without re-rendering the dashboard UI.
				markTruthSeenWithoutUiUpdate( truth );
				schedulePolling( 'stabilized_ignored' );
				return;
			} )
			.catch( function ( error ) {
				dashboardPolling.failureCount += 1;
				dashboardPolling.bootstrapSyncApplied = false;
				var retryInMs = getPollingBackoffDelay( dashboardPolling.failureCount );
				logDashboardUi( 'polling_failed', {
					context: pollContext,
					state: currentState,
					message: error && error.message ? error.message : String( error || '' ),
					retry_in_ms: retryInMs,
				} );
				if ( 'startup' === pollContext ) {
					logDashboardUiFailure( pollContext, error );
				}
				schedulePolling( 'polling_failed', retryInMs );
			} )
			.finally( function () {
				dashboardPolling.inFlight = false;
				if ( 'startup' === pollContext ) {
					if ( typeof window.requestAnimationFrame === 'function' ) {
						window.requestAnimationFrame( function () {
							window.requestAnimationFrame( function () {
								markDashboardStartupTruthResolved();
							} );
						} );
					} else {
						window.setTimeout( function () {
							markDashboardStartupTruthResolved();
						}, 0 );
					}
				}
			} );
	}

	function runEstablishedGenerateFlow( e ) {
		var primaryCta = getPrimaryCta();
		if ( typeof window.bbaiHandleGenerateMissing === 'function' ) {
			window.bbaiHandleGenerateMissing.call( primaryCta, e );
			return true;
		}

		if ( typeof window.handleGenerateMissing === 'function' ) {
			window.handleGenerateMissing.call( primaryCta, e );
			return true;
		}

		if ( typeof window.bulk_generate_alt === 'function' ) {
			window.bulk_generate_alt( 'missing' );
			return true;
		}

		if ( window.jQuery && typeof window.jQuery.fn === 'object' ) {
			window.jQuery( document ).trigger( 'bbai:generate-missing' );
			return true;
		}

		return false;
	}

	function bbaiHeroLooksLikeSessionOrNonceMessage( raw ) {
		var s = String( raw || '' ).toLowerCase();
		return s.indexOf( 'nonce' ) !== -1 || s.indexOf( 'unauthorized' ) !== -1 || s.indexOf( 'session' ) !== -1 || s.indexOf( 'expired' ) !== -1 || s.indexOf( 'forbidden' ) !== -1;
	}

	function fetchMissingAttachmentIds( limit ) {
		var body = new URLSearchParams();
		body.append( 'action', 'beepbeepai_get_attachment_ids' );
		body.append( 'nonce', BBAI_HERO_CFG.ajaxNonce );
		body.append( 'scope', 'missing' );
		body.append( 'limit', String( limit ) );
		body.append( 'offset', '0' );

		return fetch( BBAI_HERO_CFG.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} )
		.then( function ( res ) {
			return res.json().then( function ( json ) {
				return { res: res, json: json };
			} );
		} )
		.then( function ( payload ) {
			var json = payload && payload.json;
			var res  = payload && payload.res;
			if ( ! json ) {
				throw new Error( 'list invalid' );
			}
			if ( json.success === false ) {
				var ajaxErr = new Error( 'list_ajax' );
				ajaxErr.bbaiAjaxMessage = ( json.data && json.data.message ) ? String( json.data.message ) : '';
				ajaxErr.bbaiHttpStatus  = res ? res.status : 0;
				throw ajaxErr;
			}
			if ( ! json.success || ! json.data || ! Array.isArray( json.data.ids ) ) {
				throw new Error( 'list invalid' );
			}
			return json.data.ids;
		} );
	}

	/**
	 * POST to the WP-queue bulk endpoint — works for both trial and licensed users.
	 * attachment_ids must be sent as a PHP array (attachment_ids[]) not JSON.
	 */
	function postBulkQueue( ids ) {
		var body = new URLSearchParams();
		body.append( 'action',  'beepbeepai_bulk_queue' );
		body.append( 'nonce',   BBAI_HERO_CFG.ajaxNonce );
		body.append( 'source',  'dashboard' );
		body.append( 'skip_schedule', '1' );
		ids.forEach( function ( id ) { body.append( 'attachment_ids[]', id ); } );

		return fetch( BBAI_HERO_CFG.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} ).then( function ( res ) { return res.json(); } );
	}

	function dispatchGenerateMissing( e, trigger ) {
		e.preventDefault();
		e.stopPropagation();
		if ( typeof e.stopImmediatePropagation === 'function' ) {
			e.stopImmediatePropagation();
		}

		logDashboardUi( 'generation_button_clicked', Object.assign( {
			source: 'dashboard',
			ajax_action: 'beepbeepai_get_attachment_ids',
		}, getGenerationQuotaDebugContext( trigger ) ) );

		emitGenerationAnalytics( 'generation_cta_clicked', 'dashboard', {
			ajax_action: 'beepbeepai_get_attachment_ids',
		} );

		if ( ! acquireInlineGenerationLock( e, 'dashboard_generate' ) ) {
			return;
		}

		if ( ! BBAI_HERO_CFG.ajaxUrl || ! BBAI_HERO_CFG.ajaxNonce ) {
			releaseInlineGenerationLock();
			if ( runEstablishedGenerateFlow( e ) ) {
				return;
			}
			recoverGenerationStartFailure( trigger, TEXT.startFailed, {
				source: 'dashboard',
				ajax_action: 'beepbeepai_get_attachment_ids',
				error_code: 'missing_ajax_config',
				error_message: TEXT.startFailed,
			} );
			return;
		}

		var limit = Math.max( 1, Math.min( 500, BBAI_HERO_CFG.missingCount || 500 ) );
		var idsQueued = null;

		// Step 1: fetch missing attachment IDs through the registered WP Ajax action.
		fetchMissingAttachmentIds( limit )
		.then( function ( ids ) {
			if ( ids.length === 0 ) {
				recoverGenerationStartFailure( trigger, TEXT.noMissingFoundRescan, {
					source: 'dashboard',
					requested_count: 0,
					ajax_action: 'beepbeepai_get_attachment_ids',
					error_code: 'no_missing_attachment_ids',
					error_message: TEXT.noMissingFoundRescan,
				} );
				showStatusLine( TEXT.noMissingFoundRescan );
				var showMismatch = ( BBAI_HERO_CFG.missingCount || 0 ) > 0;
				if ( showMismatch && BBAI_HERO_CFG.wpDebug === '1' ) {
					logDashboardUi( 'get_attachment_ids_empty_with_positive_missing_hero', {
						scope: 'missing',
						heroMissingCount: BBAI_HERO_CFG.missingCount,
						idsReturned: 0,
						source: 'beepbeepai_get_attachment_ids',
					} );
				}
				return null;
			}

			idsQueued = ids;
			logDashboardUi( 'generation_request_started', Object.assign( {
				source: 'dashboard',
				requested_count: idsQueued.length,
				ajax_action: 'beepbeepai_bulk_queue',
			}, getGenerationQuotaDebugContext( trigger ) ) );
			emitGenerationAnalytics( 'generation_request_started', 'dashboard', {
				requested_count: idsQueued.length,
				ajax_action: 'beepbeepai_bulk_queue',
			} );
			return postBulkQueue( ids );
		} )
		.then( function ( queueJson ) {
			if ( ! queueJson ) { return; } // handled above (no images)
			if ( ! queueJson.success ) {
				var failRaw = queueJson.data && queueJson.data.message ? String( queueJson.data.message ) : '';
				if ( bbaiHeroLooksLikeSessionOrNonceMessage( failRaw ) ) {
					recoverGenerationStartFailure( trigger, TEXT.sessionExpired, {
						source: 'dashboard',
						requested_count: idsQueued ? idsQueued.length : limit,
						ajax_action: 'beepbeepai_bulk_queue',
						error_code: queueJson.data && queueJson.data.code ? String( queueJson.data.code ) : 'queue_failed',
						error_message: failRaw || TEXT.sessionExpired,
					} );
				} else {
					recoverGenerationStartFailure( trigger, failRaw || TEXT.startFailed, {
						source: 'dashboard',
						requested_count: idsQueued ? idsQueued.length : limit,
						ajax_action: 'beepbeepai_bulk_queue',
						error_code: queueJson.data && queueJson.data.code ? String( queueJson.data.code ) : 'queue_failed',
						error_message: failRaw || TEXT.startFailed,
					} );
				}
				return;
			}

			var responseData = queueJson.data || {};
			var jobId = getGenerationJobIdFromPayload( responseData );

			logDashboardUi( 'bulk_queue_success', {
				context: 'generate_missing',
				ids_count: idsQueued.length,
				queued: responseData.queued,
			} );

			clearHeroGenerationWatchdog();
			logDashboardUi( 'accepted_starting', { action: 'generate-missing', ids_count: idsQueued.length, job_id: jobId } );
			markOptimisticAction( 'generate-missing' );
			setBusy( trigger, true );
			showStatusLine( ACTION_STATUS[ 'generate-missing' ] );
			flashSummaryUpdating();
			emitGenerationAnalytics( 'generation_job_created', 'dashboard', {
				requested_count: idsQueued.length,
				job_id: jobId,
				ajax_action: 'beepbeepai_bulk_queue',
			} );

			var flowOk = false;
			if ( typeof window.startGenerationFlow === 'function' ) {
				flowOk = window.startGenerationFlow( idsQueued, {
					source: 'generate-missing',
					entry: 'dashboard_hero',
					responseData: responseData,
					progressLabel: TEXT.preparingBulkRun,
					queued: responseData.queued != null ? responseData.queued : idsQueued.length,
				} );
			}

			if ( ! flowOk ) {
				recoverGenerationStartFailure( trigger, TEXT.startFailed, {
					source: 'dashboard',
					requested_count: idsQueued.length,
					job_id: jobId,
					ajax_action: 'beepbeepai_bulk_queue',
					error_code: 'start_generation_flow_unavailable',
					error_message: TEXT.startFailed,
				} );
				return;
			}

			dashboardPolling.optimisticAction = '';

			heroGenerationWatchdog = window.setTimeout( function () {
				heroGenerationWatchdog = null;
				var modal = document.getElementById( 'bbai-bulk-progress-modal' );
				var modalActive = modal && modal.classList.contains( 'active' );
				var jobState = window.bbaiJobState && typeof window.bbaiJobState.getState === 'function' ? window.bbaiJobState.getState() : null;
				var jobRunning = jobState && jobState.running;
				var hasJobId = !! ( jobId || ( jobState && ( jobState.jobId || jobState.job_id ) ) );
				var hasProgress = !! ( jobState && parseInt( jobState.progress, 10 ) > 0 );
				if ( ! modalActive && ! jobRunning && ! hasJobId && ! hasProgress ) {
					logDashboardUi( 'generation_start_watchdog_failed', {
						entry: 'dashboard_hero',
						ids_count: idsQueued.length,
					} );
					recoverGenerationStartFailure( getPrimaryCta(), TEXT.startDidNotStart, {
						source: 'dashboard',
						requested_count: idsQueued.length,
						job_id: jobId,
						ajax_action: 'beepbeepai_bulk_queue',
						error_code: 'start_watchdog_timeout',
						error_message: TEXT.startDidNotStart,
					} );
					emitGenerationAnalytics( 'generation_stuck_recovered', 'dashboard', {
						requested_count: idsQueued.length,
						job_id: jobId,
						ajax_action: 'beepbeepai_bulk_queue',
						error_code: 'start_watchdog_timeout',
						error_message: TEXT.startDidNotStart,
					} );
				}
			}, 20000 );

			confirmLatestStateTruth( 'generate_missing', 900 )
				.then( function ( truth ) {
					var beforeTruth = dashboardPolling.currentTruth;
					var beforeState = beforeTruth ? getStateTruthState( beforeTruth ) : ( hero.getAttribute( 'data-bbai-li-state' ) || '' );
					var forceResolved = shouldUseResolvedDashboard( truth, beforeTruth );
					return commitTruthPayload( truth, 'generate_missing', forceResolved )
						.then( function () {
							logDashboardUi( 'state_updated', {
								context: 'generate_missing',
								from_state: beforeState,
								to_state: getStateTruthState( truth ),
								mode: forceResolved ? 'resolved' : 'local',
							} );
						} );
				} )
				.catch( function ( error ) {
					logDashboardUiFailure( 'generate_missing_truth_async', error );
				} );
		} )
		.catch( function ( err ) {
			var ajaxMsg = err && err.bbaiAjaxMessage ? String( err.bbaiAjaxMessage ) : '';
			if ( ajaxMsg && bbaiHeroLooksLikeSessionOrNonceMessage( ajaxMsg ) ) {
				recoverGenerationStartFailure( trigger, TEXT.sessionExpired, {
					source: 'dashboard',
					requested_count: idsQueued ? idsQueued.length : limit,
					ajax_action: idsQueued ? 'beepbeepai_bulk_queue' : 'beepbeepai_get_attachment_ids',
					error_code: err && err.message ? String( err.message ) : 'ajax_failed',
					error_message: ajaxMsg || TEXT.sessionExpired,
				} );
				return;
			}
			recoverGenerationStartFailure( trigger, TEXT.startFailed, {
				source: 'dashboard',
				requested_count: idsQueued ? idsQueued.length : limit,
				ajax_action: idsQueued ? 'beepbeepai_bulk_queue' : 'beepbeepai_get_attachment_ids',
				error_code: err && err.message ? String( err.message ) : 'ajax_failed',
				error_message: ajaxMsg || ( err && err.message ? String( err.message ) : TEXT.startFailed ),
			} );
		} );
	}

	// Donut card click → take the state-specific next step.
	var donutCard = hero.querySelector( '.bbai-li-card--donut' );
	if ( donutCard ) {
		function donutCardActivate( e ) {
			var primaryCta = getPrimaryCta();
			var t = e && e.target ? e.target : null;
			if ( t && typeof t.closest === 'function' ) {
				if ( t.closest( '.bbai-status-summary' ) || t.closest( '.bbai-progress-flow' ) || t.closest( '.bbai-li-donut__text-cta' ) ) {
					return;
				}
			}
			if ( ! donutCard.hasAttribute( 'data-bbai-li-donut-card-trigger' ) ) { return; }
			if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) { return; }
			if ( e.type === 'keydown' ) { e.preventDefault(); }
			if ( primaryCta && primaryCta.getAttribute( 'aria-busy' ) === 'true' ) { return; }

			if ( hero.getAttribute( 'data-bbai-li-state' ) === 'NEEDS_REVIEW' ) {
				if ( e && typeof e.preventDefault === 'function' ) { e.preventDefault(); }
				if ( navigateToHref( getReviewLibraryHref() ) ) { return; }
			}

			if ( primaryCta && typeof primaryCta.click === 'function' ) {
				primaryCta.click();
			}
		}
		donutCard.addEventListener( 'click', donutCardActivate );
		donutCard.addEventListener( 'keydown', donutCardActivate );
	}

	hero.addEventListener( 'click', function ( e ) {
		var primaryCta = e.target && e.target.closest ? e.target.closest( '[data-bbai-li-primary-cta]' ) : null;
		var action;
		var statusText;

		if ( ! primaryCta || ! hero.contains( primaryCta ) ) {
			return;
		}

		if ( primaryCta.getAttribute( 'aria-busy' ) === 'true' || primaryCta.classList.contains( 'is-loading' ) ) {
			e.preventDefault();
			return;
		}

		action = primaryCta.getAttribute( 'data-bbai-li-action' ) || primaryCta.getAttribute( 'data-action' ) || '';
		if ( action === 'generate-missing' ) {
			dispatchGenerateMissing( e, primaryCta );
			return;
		}
		if ( action === 'approve-all' ) {
			e.preventDefault();
			e.stopPropagation();
			if ( typeof e.stopImmediatePropagation === 'function' ) {
				e.stopImmediatePropagation();
			}
			showStatusLine( ACTION_STATUS[ 'approve-all' ], 'success', {
				meta: buildStatusStripMeta( 'approve_all_click', 'primary_cta_handler' ),
			} );
			flashSummaryUpdating();
			if ( typeof window.bbaiApproveAllNeedsReview === 'function' ) {
				window.bbaiApproveAllNeedsReview( primaryCta, e );
				return;
			}
			showStatusLine( '<?php echo esc_js( __( 'Unable to approve these images right now.', 'beepbeep-ai-alt-text-generator' ) ); ?>', '', {
				meta: buildStatusStripMeta( 'approve_all_missing_handler', 'primary_cta_handler' ),
			} );
			return;
		}

		statusText = getActionStatusText( primaryCta );
		if ( null === statusText ) { return; }
		setBusy( primaryCta, true );
		showStatusLine( statusText, '', {
			meta: buildStatusStripMeta( 'primary_cta_busy', 'primary_cta_handler' ),
		} );
		flashSummaryUpdating();
		if ( safetyTimer ) { clearTimeout( safetyTimer ); }
		safetyTimer = setTimeout( function () {
			setBusy( primaryCta, false );
			safetyTimer = null;
		}, 25000 );
	} );

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target && e.target.closest
			? e.target.closest( '[data-action="generate-missing"], [data-bbai-action="generate_missing"]' )
			: null;
		var root;

		if ( ! trigger || hero.contains( trigger ) ) {
			return;
		}
		if ( trigger.getAttribute( 'aria-disabled' ) === 'true' || trigger.hasAttribute( 'disabled' ) ) {
			return;
		}
		if ( trigger.getAttribute( 'data-bbai-locked-cta' ) === '1' ) {
			return;
		}
		root = getDashboardRoot();
		if ( ! root || ! root.contains( trigger ) ) {
			return;
		}
		if ( trigger.closest( '[data-bbai-library-workspace-root="1"], .bbai-library-container' ) ) {
			return;
		}
		dispatchGenerateMissing( e, trigger );
	}, true );

	document.addEventListener( 'bbai:logged-in-dashboard-state-applied', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		triggerDashboardTransitionFeedback( detail.previousState || '', detail.nextState || '' );
	} );

	document.addEventListener( 'bbai:generation:finished', function () {
		var primaryCta = getPrimaryCta();
		releaseInlineGenerationLock();
		dashboardPolling.optimisticAction = '';
		if ( primaryCta && primaryCta.getAttribute( 'aria-busy' ) === 'true' ) {
			setBusy( primaryCta, false );
		}
		if ( safetyTimer ) { clearTimeout( safetyTimer ); safetyTimer = null; }
	} );

	document.addEventListener( 'bbai:dashboard-approve-all-pending', function () {
		if ( 'NEEDS_REVIEW' !== ( hero.getAttribute( 'data-bbai-li-state' ) || '' ) ) {
			return;
		}
		showStatusLine( ACTION_STATUS[ 'approve-all' ], 'success', {
			meta: buildStatusStripMeta( 'approve_all_pending', 'dashboard_approve_all' ),
		} );
		flashSummaryUpdating();
	} );

	document.addEventListener( 'bbai:dashboard-approve-all-success', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		var approvedCount = Math.max( 0, parseInt( detail.approvedCount, 10 ) || 0 );

		if ( 'NEEDS_REVIEW' !== ( hero.getAttribute( 'data-bbai-li-state' ) || '' ) ) {
			return;
		}

		if ( approvedCount > 0 ) {
			showTransientStatusLine( TEXT.approveUpdating, 'success', 1400, buildStatusStripMeta( 'approve_all_success', 'dashboard_approve_all' ) );
			return;
		}

		syncStatusLineForTruth( dashboardPolling.currentTruth, buildStatusStripMeta( 'approve_all_success', 'dashboard_approve_all' ) );
	} );

	document.addEventListener( 'bbai:dashboard-review-count-optimistic', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		var approvedCount = Math.max( 0, parseInt( detail.approvedCount, 10 ) || 0 );
		var currentTruth = dashboardPolling.currentTruth;
		var heroState = hero.getAttribute( 'data-bbai-li-state' ) || '';
		var truthState = currentTruth ? getStateTruthState( currentTruth ) : '';
		var counts;
		var currentReview;
		var nextReview;
		var root;
		var reviewMetric;

		if ( approvedCount <= 0 || 'NEEDS_REVIEW' !== heroState ) {
			return;
		}
		if ( currentTruth && 'NEEDS_REVIEW' !== truthState ) {
			return;
		}

		counts = currentTruth ? getTruthCounts( currentTruth ) : null;
		currentReview = counts ? counts.review : NaN;
		reviewMetric = hero.querySelector( '.bbai-status-summary [data-bbai-status-metric="review"]' );
		if ( isNaN( currentReview ) || currentReview <= 0 ) {
			currentReview = reviewMetric
				? parseInt( String( reviewMetric.textContent || '' ).replace( /,/g, '' ), 10 ) || 0
				: 0;
		}

		nextReview = Math.max( 0, currentReview - approvedCount );
		root = getDashboardRoot();

		if ( root ) {
			root.setAttribute( 'data-bbai-weak-count', String( nextReview ) );
		}

		if ( reviewMetric ) {
			reviewMetric.textContent = formatCount( nextReview );
			reviewMetric.classList.add( 'bbai-li-summary__value--updating' );
			window.setTimeout( function () {
				reviewMetric.classList.remove( 'bbai-li-summary__value--updating' );
			}, 420 );
		}

		updateSummaryValueByLabel( TEXT.reviewLabel, formatCount( nextReview ), nextReview > 0 ? 'primary' : 'ok' );

		if ( 0 === nextReview && root && ( parseInt( root.getAttribute( 'data-bbai-missing-count' ) || '0', 10 ) || 0 ) <= 0 ) {
			applyStartupDashboardRootTruth();
		}
	} );

	document.addEventListener( 'bbai:dashboard-approve-all-failed', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		var message = detail.message || '<?php echo esc_js( __( 'Unable to approve these images right now.', 'beepbeep-ai-alt-text-generator' ) ); ?>';
		showStatusLine( message, '', {
			meta: buildStatusStripMeta( 'approve_all_failed', 'dashboard_approve_all' ),
		} );
	} );

	window.bbaiRefreshLoggedInDashboardTruth = function ( context ) {
		var refreshContext = context || 'approve_all';
		return window.bbaiRequestDashboardStateRefresh( refreshContext )
		.then( function ( truth ) {
			var forceResolved = shouldUseResolvedDashboard( truth, dashboardPolling.currentTruth );
			return commitTruthPayload( truth, refreshContext, forceResolved )
				.then( function () {
					return truth;
				} );
		} );
	};

	document.addEventListener( 'visibilitychange', function () {
		var primaryCta = getPrimaryCta();

		if ( document.visibilityState === 'hidden' ) {
			cancelProcessingAnimation();
			stopPolling( 'hidden' );
			return;
		}

		// Resume with one refresh when visible again.
		runPollingTick( 'visibility' );

		if ( primaryCta && primaryCta.getAttribute( 'aria-busy' ) === 'true' ) {
			setTimeout( function () {
				var visiblePrimaryCta = getPrimaryCta();
				if ( visiblePrimaryCta && visiblePrimaryCta.getAttribute( 'aria-busy' ) === 'true' ) {
					setBusy( visiblePrimaryCta, false );
					if ( safetyTimer ) { clearTimeout( safetyTimer ); safetyTimer = null; }
				}
			}, 300 );
		}
	} );

	window.addEventListener( 'pagehide', function () {
		cancelProcessingAnimation();
		stopPolling( 'pagehide' );
	} );
	window.addEventListener( 'beforeunload', function () {
		cancelProcessingAnimation();
		stopPolling( 'beforeunload' );
	} );

	function mapStatusSummaryToFilterSlug( raw ) {
		var s = String( raw || '' ).toLowerCase();
		if ( 'review' === s ) {
			return 'weak';
		}
		return s;
	}

	function getLibraryFilterButtonBySlug( slug ) {
		if ( ! slug ) {
			return null;
		}
		return document.querySelector( '#bbai-review-filter-tabs button[data-filter="' + slug + '"]' );
	}

	function syncBbaiStatusSummaryActive() {
		var activeBtn = document.querySelector( '#bbai-review-filter-tabs button.bbai-filter-group__item--active' );
		var wrap = document.querySelector( '.bbai-status-summary' );
		var slug;
		var el;
		if ( ! wrap ) {
			return;
		}
		wrap.querySelectorAll( '.bbai-status-item.is-active' ).forEach( function ( n ) {
			n.classList.remove( 'is-active' );
		} );
		if ( ! activeBtn ) {
			return;
		}
		slug = activeBtn.getAttribute( 'data-filter' ) || '';
		if ( 'weak' === slug ) {
			el = wrap.querySelector( '.bbai-status-item[data-bbai-dashboard-status-filter="weak"]' );
		} else if ( 'missing' === slug ) {
			el = wrap.querySelector( '.bbai-status-item[data-bbai-dashboard-status-filter="missing"]' );
		}
		if ( el ) {
			el.classList.add( 'is-active' );
		}
	}

	function initBbaiStatusSummary() {
		var wrap = document.querySelector( '.bbai-status-summary' );
		if ( ! wrap || wrap.getAttribute( 'data-bbai-status-bound' ) === '1' ) {
			return;
		}
		wrap.setAttribute( 'data-bbai-status-bound', '1' );
		wrap.querySelectorAll( '.bbai-status-item' ).forEach( function ( item ) {
			item.addEventListener( 'click', function () {
				var raw = item.getAttribute( 'data-bbai-dashboard-status-filter' ) || item.getAttribute( 'data-filter' ) || '';
				var slug = mapStatusSummaryToFilterSlug( raw );
				var target = getLibraryFilterButtonBySlug( slug );
				var root;
				if ( target ) {
					target.click();
					window.setTimeout( syncBbaiStatusSummaryActive, 0 );
					return;
				}
				root = getDashboardRoot();
				if ( ! root ) {
					return;
				}
				if ( 'missing' === slug ) {
					window.location.assign( root.getAttribute( 'data-bbai-missing-library-url' ) || root.getAttribute( 'data-bbai-library-url' ) || '' );
					return;
				}
				if ( 'weak' === slug ) {
					window.location.assign( root.getAttribute( 'data-bbai-needs-review-library-url' ) || root.getAttribute( 'data-bbai-library-url' ) || '' );
				}
			} );
		} );
		syncBbaiStatusSummaryActive();
	}

	function bbaiStorageGet( store, key ) {
		try {
			return store && store.getItem ? store.getItem( key ) : null;
		} catch ( err ) {
			return null;
		}
	}

	function bbaiStorageSet( store, key, value ) {
		try {
			if ( store && store.setItem ) {
				store.setItem( key, value );
			}
		} catch ( err ) {}
	}

	function bbaiPromptTrack( eventName, detail ) {
		detail = detail && typeof detail === 'object' ? detail : {};
		if ( typeof window.bbaiTrack !== 'function' ) {
			window.bbaiTrack = function ( name, payload ) {
				document.dispatchEvent( new CustomEvent( 'bbai:track', {
					detail: {
						event: name,
						payload: payload || {},
					},
				} ) );
				if ( window.BBAI_LOG && typeof window.BBAI_LOG.log === 'function' ) {
					window.BBAI_LOG.log( '[bbai-track] ' + name, payload || {} );
				}
			};
		}
		window.bbaiTrack( eventName, detail );
	}

	function getActivationCounts( detail ) {
		var truth = detail && detail.stateData ? normalizeStateTruthPayload( detail.stateData ) : dashboardPolling.currentTruth;
		var counts = truth ? getTruthCounts( truth ) : null;
		if ( counts ) {
			return counts;
		}
		return {
			missing: parseInt( hero.getAttribute( 'data-bbai-li-missing-count' ) || '0', 10 ) || 0,
			review: parseInt( hero.getAttribute( 'data-bbai-li-review-count' ) || '0', 10 ) || 0,
			complete: parseInt( hero.getAttribute( 'data-bbai-li-optimised-count' ) || '0', 10 ) || 0,
			failed: 0,
			total: parseInt( hero.getAttribute( 'data-bbai-li-total-count' ) || '0', 10 ) || 0,
		};
	}

	function getActivationCredits( detail ) {
		var truth = detail && detail.stateData ? normalizeStateTruthPayload( detail.stateData ) : dashboardPolling.currentTruth;
		var creditNode = hero.querySelector( '[data-bbai-hero-credit-usage]' );
		var credits = truth ? getTruthCredits( truth ) : null;
		if ( credits ) {
			return credits;
		}
		if ( creditNode ) {
			var total = Math.max( 1, parseInt( creditNode.getAttribute( 'data-bbai-hero-credits-limit' ) || '1', 10 ) || 1 );
			var used = Math.max( 0, parseInt( creditNode.getAttribute( 'data-bbai-hero-credits-used' ) || '0', 10 ) || 0 );
			var remaining = Math.max( 0, parseInt( creditNode.getAttribute( 'data-bbai-hero-credits-remaining' ) || String( total - used ), 10 ) || 0 );
			return {
				used: used,
				total: total,
				remaining: remaining,
				isPro: false,
				plan: '',
			};
		}
		return { used: 0, total: 1, remaining: 1, isPro: false, plan: '' };
	}

	function isActivationQuietMoment() {
		var primaryCta = getPrimaryCta();
		return ! (
			( window.bbaiGenerationLock && window.bbaiGenerationLock.active ) ||
			( primaryCta && primaryCta.getAttribute( 'aria-busy' ) === 'true' ) ||
			'PROCESSING' === ( hero.getAttribute( 'data-bbai-li-state' ) || '' ) ||
			'QUEUED' === ( hero.getAttribute( 'data-bbai-li-state' ) || '' )
		);
	}

	function buildPromptButton( label, action, isPrimary ) {
		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = isPrimary ? 'bbai-activation-panel__btn bbai-activation-panel__btn--primary' : 'bbai-activation-panel__btn';
		button.setAttribute( 'data-bbai-activation-action', action );
		button.textContent = label;
		return button;
	}

	function buildPromptLink( label, href, action, isPrimary ) {
		var link = document.createElement( 'a' );
		link.className = isPrimary ? 'bbai-activation-panel__btn bbai-activation-panel__btn--primary' : 'bbai-activation-panel__btn';
		link.href = href || '#';
		link.setAttribute( 'data-bbai-activation-action', action );
		link.textContent = label;
		return link;
	}

	function createActivationPanel( options ) {
		var panel = document.createElement( 'section' );
		var copy;
		var title;
		var actions;
		panel.className = 'bbai-activation-panel bbai-activation-panel--' + ( options.tone || 'info' );
		panel.setAttribute( 'data-bbai-activation-panel', options.id || 'panel' );
		title = document.createElement( 'p' );
		title.className = 'bbai-activation-panel__title';
		title.textContent = options.title || '';
		panel.appendChild( title );
		if ( options.copy ) {
			copy = document.createElement( 'p' );
			copy.className = 'bbai-activation-panel__copy';
			copy.textContent = options.copy;
			panel.appendChild( copy );
		}
		if ( options.actions && options.actions.length ) {
			actions = document.createElement( 'div' );
			actions.className = 'bbai-activation-panel__actions';
			options.actions.forEach( function ( action ) {
				actions.appendChild( action.href
					? buildPromptLink( action.label, action.href, action.action, !! action.primary )
					: buildPromptButton( action.label, action.action, !! action.primary )
				);
			} );
			panel.appendChild( actions );
		}
		if ( options.dismissAction ) {
			var dismiss = document.createElement( 'button' );
			dismiss.type = 'button';
			dismiss.className = 'bbai-activation-panel__dismiss';
			dismiss.setAttribute( 'aria-label', '<?php echo esc_js( __( 'Dismiss', 'beepbeep-ai-alt-text-generator' ) ); ?>' );
			dismiss.setAttribute( 'data-bbai-activation-action', options.dismissAction );
			dismiss.textContent = '×';
			panel.appendChild( dismiss );
		}
		return panel;
	}

	function renderActivationPrompts( context, detail ) {
		var host = hero.querySelector( '[data-bbai-activation-host]' );
		var counts = getActivationCounts( detail );
		var credits = getActivationCredits( detail );
		var state = ( detail && detail.nextState ) || ( hero.getAttribute( 'data-bbai-li-state' ) || '' );
		var usedPct = credits.total > 0 ? Math.round( ( credits.used / credits.total ) * 100 ) : 0;
		var panels = [];
		var reviewHref = getReviewLibraryHref();
		var libraryHref = getLibraryHref();

		if ( ! host || ! isActivationQuietMoment() ) {
			return;
		}

		host.textContent = '';

		if ( counts.missing === 0 && counts.review === 0 && counts.complete === 0 ) {
			panels.push( createActivationPanel( {
				id: 'empty-library',
				tone: 'info',
				title: PROMPT_TEXT.emptyTitle,
				actions: [
					{ label: PROMPT_TEXT.openMedia, href: '<?php echo esc_js( admin_url( 'upload.php' ) ); ?>', action: 'open-media-library', primary: true },
					{ label: PROMPT_TEXT.learnSeo, href: '<?php echo esc_js( admin_url( 'admin.php?page=bbai-guide' ) ); ?>', action: 'learn-seo' },
				],
			} ) );
		}

		if (
			counts.complete > 0 &&
			! bbaiStorageGet( window.localStorage, ACTIVATION_PROMPT_KEYS.firstSuccessSeen )
		) {
			panels.push( createActivationPanel( {
				id: 'first-success',
				tone: 'success',
				title: PROMPT_TEXT.firstSuccessTitle,
				copy: PROMPT_TEXT.firstSuccessCopy,
				actions: [
					{ label: PROMPT_TEXT.reviewAltText, href: reviewHref, action: 'review-alt-text', primary: true },
					{ label: PROMPT_TEXT.generateMore, action: 'generate-more' },
				],
			} ) );
			bbaiStorageSet( window.localStorage, ACTIVATION_PROMPT_KEYS.firstSuccessSeen, '1' );
		}

		if ( context === 'generation_finished' && counts.review > 0 ) {
			panels.push( createActivationPanel( {
				id: 'quick-actions',
				tone: 'success',
				title: PROMPT_TEXT.nextStepTitle,
				copy: PROMPT_TEXT.firstSuccessCopy,
				actions: [
					{ label: PROMPT_TEXT.approveAll, action: 'approve-all-alt', primary: true },
					{ label: PROMPT_TEXT.editFew, href: reviewHref, action: 'edit-few' },
					{ label: PROMPT_TEXT.done, action: 'done' },
				],
			} ) );
		}

		if ( 'NEEDS_REVIEW' === state && counts.review > 0 ) {
			panels.push( createActivationPanel( {
				id: 'review-value',
				tone: 'info',
				title: PROMPT_TEXT.reviewValueTitle,
				copy: PROMPT_TEXT.scoreImproved,
				actions: [
					{ label: PROMPT_TEXT.reviewAltText, href: reviewHref, action: 'review-alt-text', primary: true },
				],
			} ) );
		}

		if ( usedPct >= 80 ) {
			if ( ! bbaiStorageGet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.creditWarning80Shown ) ) {
				panels.push( createActivationPanel( {
					id: 'credits-low',
					tone: 'warning',
					title: PROMPT_TEXT.creditLowTitle,
					copy: PROMPT_TEXT.creditLowCopy,
					actions: [
						{ label: PROMPT_TEXT.upgradePlan, action: 'upgrade-plan', primary: true },
					],
				} ) );
				bbaiStorageSet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.creditWarning80Shown, '1' );
				bbaiPromptTrack( 'credits_low_warning_shown', { threshold: 80, used: credits.used, total: credits.total } );
			}
		} else if ( usedPct >= 60 && ! bbaiStorageGet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.creditWarning60Shown ) ) {
			panels.push( createActivationPanel( {
				id: 'credits-aware',
				tone: 'info',
				title: replaceTokens( PROMPT_TEXT.creditUsed, {
					'%1$s': formatCount( credits.used ),
					'%2$s': formatCount( credits.total ),
				} ),
			} ) );
			bbaiStorageSet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.creditWarning60Shown, '1' );
			bbaiPromptTrack( 'credits_low_warning_shown', { threshold: 60, used: credits.used, total: credits.total } );
		}

		if (
			! credits.isPro &&
			( counts.complete > 10 || context === 'generation_finished' ) &&
			! bbaiStorageGet( window.localStorage, ACTIVATION_PROMPT_KEYS.upgradeDismissed ) &&
			! bbaiStorageGet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.upgradeShown )
		) {
			panels.push( createActivationPanel( {
				id: 'upgrade-automation',
				tone: 'upgrade',
				title: replaceTokens( PROMPT_TEXT.upgradeTitle, { '%s': formatCount( counts.complete ) } ),
				copy: PROMPT_TEXT.upgradeCopy,
				actions: [
					{ label: PROMPT_TEXT.enableAutomation, action: 'upgrade-automation', primary: true },
				],
				dismissAction: 'dismiss-upgrade',
			} ) );
			bbaiStorageSet( window.sessionStorage, ACTIVATION_PROMPT_KEYS.upgradeShown, '1' );
		}

		panels.forEach( function ( panel ) {
			host.appendChild( panel );
		} );
	}

	function handleActivationPromptAction( e ) {
		var target = e.target && e.target.closest ? e.target.closest( '[data-bbai-activation-action]' ) : null;
		var action;
		var primaryCta;
		var approveAllCta;
		if ( ! target || ! hero.contains( target ) ) {
			return;
		}
		action = target.getAttribute( 'data-bbai-activation-action' ) || '';
		if ( action !== 'review-alt-text' && action !== 'edit-few' && action !== 'open-media-library' && action !== 'learn-seo' ) {
			e.preventDefault();
		}
		if ( action === 'review-alt-text' || action === 'edit-few' ) {
			bbaiPromptTrack( 'review_started', { source: action } );
			return;
		}
		if ( action === 'generate-more' ) {
			primaryCta = getPrimaryCta();
			if ( primaryCta && primaryCta.getAttribute( 'aria-busy' ) !== 'true' ) {
				primaryCta.click();
			}
			return;
		}
		if ( action === 'approve-all-alt' ) {
			approveAllCta = hero.querySelector( '[data-bbai-li-action="approve-all"], [data-action="approve-all"]' );
			if ( approveAllCta ) {
				approveAllCta.click();
			} else {
				bbaiPromptTrack( 'review_started', { source: action });
				window.location.assign( getReviewLibraryHref() );
			}
			return;
		}
		if ( action === 'done' ) {
			target.closest( '[data-bbai-activation-panel]' ).remove();
			return;
		}
		if ( action === 'dismiss-upgrade' ) {
			bbaiStorageSet( window.localStorage, ACTIVATION_PROMPT_KEYS.upgradeDismissed, '1' );
			target.closest( '[data-bbai-activation-panel]' ).remove();
			return;
		}
		if ( action === 'upgrade-plan' || action === 'upgrade-automation' ) {
			var upgrade = hero.querySelector( '[data-action="show-upgrade-modal"]' );
			if ( upgrade && typeof upgrade.click === 'function' ) {
				upgrade.click();
			} else {
				bbaiPromptTrack( 'upgrade_clicked', { source: action } );
			}
		}
	}

	function afterDashboardHydration( callback ) {
		var run = function () {
			markDashboardStartupTruthResolved();
			callback();
		};

		if ( typeof window.requestAnimationFrame === 'function' ) {
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( run );
			} );
			return;
		}

		window.setTimeout( run, 0 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initBbaiStatusSummary );
	} else {
		initBbaiStatusSummary();
	}
	hero.addEventListener( 'click', handleActivationPromptAction );
	document.addEventListener( 'bbai:logged-in-dashboard-state-applied', function () {
		if ( ! dashboardPolling.hydrated ) {
			window.setTimeout( markDashboardStartupTruthResolved, 0 );
		}
		window.setTimeout( syncBbaiStatusSummaryActive, 0 );
		window.setTimeout( function () {
			renderActivationPrompts( 'state_applied' );
		}, 0 );
	} );
	document.addEventListener( 'bbai:generation:finished', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		if ( detail.successes > 0 ) {
			logDashboardUi( 'generation_request_completed', Object.assign( {
				source: detail.source || 'dashboard',
				success_count: detail.successes || 0,
				failure_count: detail.failures || 0,
				processed_count: detail.processed || detail.successes || 0,
			}, getGenerationQuotaDebugContext() ) );
			bbaiPromptTrack( 'generation_completed', detail );
			window.setTimeout( function () {
				renderActivationPrompts( 'generation_finished', {
					stateData: dashboardPolling.currentTruth,
					nextState: hero.getAttribute( 'data-bbai-li-state' ) || '',
				} );
			}, 0 );
		}
	} );
	document.addEventListener( 'bbai:dashboard-approve-all-success', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		if ( Math.max( 0, parseInt( detail.approvedCount, 10 ) || 0 ) > 0 ) {
			bbaiPromptTrack( 'review_completed', detail );
		}
	} );
	document.addEventListener( 'click', function ( event ) {
		var upgradeTrigger = event.target && event.target.closest ? event.target.closest( '[data-action="show-upgrade-modal"], [data-bbai-analytics-upgrade]' ) : null;
		var reviewTrigger = event.target && event.target.closest ? event.target.closest( '[data-bbai-li-secondary-inline-cta], [data-bbai-li-secondary-cta], [data-bbai-li-donut-review-cta]' ) : null;
		if ( upgradeTrigger ) {
			bbaiPromptTrack( 'upgrade_clicked', {
				source: upgradeTrigger.getAttribute( 'data-bbai-analytics-upgrade' ) || upgradeTrigger.getAttribute( 'data-action' ) || 'upgrade',
			} );
		}
		if ( reviewTrigger && ( reviewTrigger.textContent || '' ).toLowerCase().indexOf( 'review' ) !== -1 ) {
			bbaiPromptTrack( 'review_started', {
				source: reviewTrigger.getAttribute( 'data-action' ) || 'review_link',
			} );
		}
	} );

	(function initAllClearHeroFromSsr() {
		if ( hero.getAttribute( 'data-bbai-li-state' ) !== 'ALL_CLEAR' ) {
			return;
		}
		hero.classList.add( 'bbai-all-clear-state' );
		if ( hero.getAttribute( 'data-bbai-all-clear-hydration-applied' ) === '1' ) {
			return;
		}
		hero.setAttribute( 'data-bbai-all-clear-hydration-applied', '1' );
		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				if ( hero.getAttribute( 'data-bbai-li-state' ) === 'ALL_CLEAR' ) {
					hero.classList.add( 'bbai-all-clear-state--entered' );
				}
			} );
		} );
	}());

	if ( heroVariant === 'running' ) {
		showStatusLine( TEXT.generationInProgress, 'scanning', {
			meta: buildStatusStripMeta( 'legacy_init_running', 'initial_hero_variant' ),
		} );
	} else if ( heroVariant === 'queued' ) {
		renderQueuedSignal( buildStatusStripMeta( 'legacy_init_queued', 'initial_hero_variant' ) );
	}
	applyStartupDashboardRootTruth();
	renderActivationPrompts( 'dashboard_load' );
	runPollingTick( 'startup' );

}());
</script>
<?php
// phpcs:enable WordPress.WP.I18n.MissingTranslatorsComment
?>
