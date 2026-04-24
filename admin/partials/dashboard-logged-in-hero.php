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

// ── Donut ────────────────────────────────────────────────────────────────────
$bbai_li_donut_color    = (string) ( $bbai_li_donut['color'] ?? 'gray' );
$bbai_li_donut_animated = ! empty( $bbai_li_donut['animated'] );
$bbai_li_donut_center   = esc_html( (string) ( $bbai_li_donut['center_label'] ?? '' ) );
$bbai_li_donut_sub      = esc_html( (string) ( $bbai_li_donut['center_sub_label'] ?? '' ) );
$bbai_li_donut_aria     = esc_attr( (string) ( $bbai_li_donut['aria_label'] ?? '' ) );
$bbai_li_donut_pct      = max( 0, min( 100, (int) ( $bbai_li_donut['pct'] ?? 0 ) ) );

// Build multi-segment conic-gradient: green → amber → red → gray.
$bbai_li_seg      = is_array( $bbai_li_donut['segments'] ?? null ) ? $bbai_li_donut['segments'] : [];
$bbai_li_seg_opt  = max( 0, (int) ( $bbai_li_seg['optimized'] ?? 0 ) );
$bbai_li_seg_weak = max( 0, (int) ( $bbai_li_seg['weak']      ?? 0 ) );
$bbai_li_seg_miss = max( 0, (int) ( $bbai_li_seg['missing']   ?? 0 ) );
$bbai_li_seg_tot  = max( 1, (int) ( $bbai_li_seg['total']     ?? 1 ) );

$bbai_li_opt_end  = round( ( 360 * $bbai_li_seg_opt ) / $bbai_li_seg_tot, 3 );
$bbai_li_weak_end = round( min( 360, $bbai_li_opt_end + ( 360 * $bbai_li_seg_weak ) / $bbai_li_seg_tot ), 3 );
$bbai_li_miss_end = round( min( 360, $bbai_li_weak_end + ( 360 * $bbai_li_seg_miss ) / $bbai_li_seg_tot ), 3 );

if ( $bbai_li_seg_opt + $bbai_li_seg_weak + $bbai_li_seg_miss === 0 ) {
	$bbai_li_donut_bg = 'conic-gradient(#e2e8f0 0deg 360deg)';
} elseif ( $bbai_li_seg_opt >= $bbai_li_seg_tot ) {
	$bbai_li_donut_bg = 'conic-gradient(#22c55e 0deg 360deg)';
} else {
	$bbai_li_donut_bg = sprintf(
		'conic-gradient(#22c55e 0deg %.3Fdeg, #f59e0b %.3Fdeg %.3Fdeg, #ef4444 %.3Fdeg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)',
		$bbai_li_opt_end,
		$bbai_li_opt_end,
		$bbai_li_weak_end,
		$bbai_li_weak_end,
		$bbai_li_miss_end,
		$bbai_li_miss_end
	);
}

// Map resolver color → tone class.
$bbai_li_tone_map = [
	'blue'  => 'scanning',
	'green' => 'healthy',
	'amber' => 'problem',
	'gray'  => 'neutral',
];
$bbai_li_donut_tone = sanitize_html_class( $bbai_li_tone_map[ $bbai_li_donut_color ] ?? 'neutral' );

// ── Per-segment hover gradients for the chip → donut interaction ─────────────
// Each chip highlights its own segment: full ring in that segment's colour,
// the rest fades to the empty-track grey. Center label shows the segment count.
$bbai_li_seg_hover = [];
if ( $bbai_li_seg_tot > 0 ) {
	$bbai_li_seg_all_pct  = 360; // "all" = full ring, use the real multi-segment bg
	$bbai_li_seg_opt_deg  = round( ( 360 * $bbai_li_seg_opt  ) / $bbai_li_seg_tot, 3 );
	$bbai_li_seg_weak_deg = round( ( 360 * $bbai_li_seg_weak ) / $bbai_li_seg_tot, 3 );
	$bbai_li_seg_miss_deg = round( ( 360 * $bbai_li_seg_miss ) / $bbai_li_seg_tot, 3 );

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
			? sprintf( 'conic-gradient(#ef4444 0deg %.3Fdeg, #e2e8f0 %.3Fdeg 360deg)', $bbai_li_seg_miss_deg, $bbai_li_seg_miss_deg )
			: 'conic-gradient(#e2e8f0 0deg 360deg)',
		'label' => (string) $bbai_li_seg_miss,
		'sub'   => esc_js( __( 'missing ALT', 'beepbeep-ai-alt-text-generator' ) ),
		'tone'  => 'problem',
	];
}
$bbai_li_seg_hover_json = wp_json_encode( $bbai_li_seg_hover );

// ── Copy ─────────────────────────────────────────────────────────────────────
$bbai_li_title       = esc_html( (string) ( $bbai_li_hero['headline'] ?? '' ) );
$bbai_li_description = esc_html( (string) ( $bbai_li_hero['support'] ?? '' ) );

// ── CTAs ─────────────────────────────────────────────────────────────────────
$bbai_li_primary_cta   = is_array( $bbai_li_hero['primary_cta'] ?? null ) ? $bbai_li_hero['primary_cta'] : [];
$bbai_li_secondary_cta = is_array( $bbai_li_hero['secondary_cta'] ?? null ) ? $bbai_li_hero['secondary_cta'] : null;

// ── Badge ─────────────────────────────────────────────────────────────────────
$bbai_li_badge = is_array( $bbai_li_hero['badge'] ?? null ) ? $bbai_li_hero['badge'] : null;
?>

<div
	class="bbai-li-hero-grid"
	data-bbai-li-hero="1"
	data-bbai-li-state="<?php echo esc_attr( $bbai_li_state_id ); ?>"
	data-bbai-li-variant="<?php echo esc_attr( (string) ( $bbai_li_hero['variant'] ?? 'default' ) ); ?>"
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
		if ( 'NEEDS_REVIEW' === $bbai_li_state_id && ! empty( $bbai_li_secondary_cta['label'] ) ) {
			$bbai_li_donut_action_label = $bbai_li_secondary_cta['label'];
		}
		?>
	<?php /* ── LEFT CARD: donut + "X images left" + footer pills ─────────── */ ?>
	<div class="bbai-li-card bbai-li-card--donut<?php echo $bbai_li_donut_clickable ? ' bbai-li-card--donut-clickable' : ''; ?>"
		<?php if ( $bbai_li_donut_clickable ) : ?>
			role="button"
			tabindex="0"
			data-bbai-li-donut-card-trigger="1"
			aria-label="<?php echo esc_attr( $bbai_li_donut_action_label ); ?>"
		<?php endif; ?>
	>

		<div class="bbai-li-donut-area">
			<div
				class="bbai-li-donut bbai-command-donut--<?php echo esc_attr( $bbai_li_donut_tone ); ?><?php echo $bbai_li_donut_animated ? ' bbai-li-donut--animated' : ''; ?>"
				data-bbai-li-donut="1"
				data-bbai-li-donut-pct="<?php echo esc_attr( (string) $bbai_li_donut_pct ); ?>"
				data-bbai-li-donut-color="<?php echo esc_attr( $bbai_li_donut_color ); ?>"
				data-bbai-li-seg-hover="<?php echo esc_attr( $bbai_li_seg_hover_json ); ?>"
				aria-label="<?php echo $bbai_li_donut_aria; ?>"
				style="background: <?php echo esc_attr( $bbai_li_donut_bg ); ?>;"
			>
				<span class="bbai-li-donut__inner"></span>
				<span class="bbai-li-donut__center">
					<span class="bbai-li-donut__value bbai-li-donut__value--<?php echo esc_attr( $bbai_li_donut_tone ); ?>" data-bbai-li-donut-label="1">
						<?php echo $bbai_li_donut_center; ?>
					</span>
				</span>
			</div>

			<?php
		// Build a clearer sub-label based on state and counts.
		$bbai_li_donut_sub_display = '';
		if ( 'QUEUED' === $bbai_li_state_id ) {
			$bbai_li_donut_sub_display = $bbai_li_donut_sub;
		} elseif ( 'PROCESSING' === $bbai_li_state_id ) {
			$bbai_li_donut_sub_display = esc_html__( 'generating now', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'ALL_CLEAR' === $bbai_li_state_id ) {
			$bbai_li_donut_sub_display = esc_html__( 'all optimised', 'beepbeep-ai-alt-text-generator' );
		} elseif ( $bbai_li_seg_miss > 0 ) {
			$bbai_li_donut_sub_display = sprintf(
				/* translators: %s: count of images missing ALT text */
				esc_html( _n( '%s image needs ALT', '%s images need ALT', $bbai_li_seg_miss, 'beepbeep-ai-alt-text-generator' ) ),
				number_format_i18n( $bbai_li_seg_miss )
			);
		} elseif ( $bbai_li_seg_weak > 0 ) {
			$bbai_li_donut_sub_display = sprintf(
				/* translators: %s: count of images needing review */
				esc_html( _n( '%s ready for review', '%s ready for review', $bbai_li_seg_weak, 'beepbeep-ai-alt-text-generator' ) ),
				number_format_i18n( $bbai_li_seg_weak )
			);
		} elseif ( $bbai_li_donut_sub ) {
			$bbai_li_donut_sub_display = $bbai_li_donut_sub;
		}
		if ( $bbai_li_donut_sub_display ) :
		?>
		<p class="bbai-li-donut__sub-label" data-bbai-li-donut-sub="1"><?php echo $bbai_li_donut_sub_display; ?></p>
		<?php endif; ?>

		<?php
		// Donut meta line — brief contextual nudge beneath sub-label.
		$bbai_li_donut_meta = '';
		if ( 'QUEUED' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'Queued automatically', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'PROCESSING' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'This may take a few minutes', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'ALL_CLEAR' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'Library fully optimised', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'NEEDS_REVIEW' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'Open review queue', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'MISSING_ALT' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'Click to generate', 'beepbeep-ai-alt-text-generator' );
		} elseif ( 'QUOTA_EXHAUSTED' === $bbai_li_state_id ) {
			$bbai_li_donut_meta = esc_html__( 'Add credits to continue', 'beepbeep-ai-alt-text-generator' );
		}
		if ( $bbai_li_donut_meta ) :
		?>
		<p class="bbai-li-donut__meta"><?php echo $bbai_li_donut_meta; ?></p>
		<?php endif; ?>
		</div>

	</div>

	<?php /* ── RIGHT CARD: badge + headline + support + CTAs + summary ────── */ ?>
	<div class="bbai-li-card bbai-li-card--content">

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
		><?php echo $bbai_li_title; ?></h1>

		<p
			class="bbai-li-support"
			data-bbai-li-hero-support="1"
			<?php if ( $bbai_li_donut_animated ) : ?>aria-live="polite" aria-atomic="true"<?php endif; ?>
		><?php echo $bbai_li_description; ?></p>

		<div class="bbai-li-cta-row">
			<?php if ( ! empty( $bbai_li_primary_cta['label'] ) ) :
					$bbai_li_busy_label = (string) ( $bbai_li_primary_cta['busy_label'] ?? '' );
					if ( '' === $bbai_li_busy_label ) {
						$bbai_li_busy_label = __( 'Working…', 'beepbeep-ai-alt-text-generator' );
					}
				?>
					<a
						class="bbai-li-btn-primary bbai-btn bbai-btn-primary"
						href="<?php echo esc_url( $bbai_li_primary_cta['href'] ?? '#' ); ?>"
						data-bbai-li-action="<?php echo esc_attr( $bbai_li_primary_cta['action'] ?? '' ); ?>"
						<?php if ( 'generate-missing' === (string) ( $bbai_li_primary_cta['action'] ?? '' ) ) : ?>
							data-bbai-action="generate_missing"
						<?php endif; ?>
						data-bbai-li-primary-cta="1"
						data-busy-label="<?php echo esc_attr( $bbai_li_busy_label ); ?>"
					><?php echo esc_html( $bbai_li_primary_cta['label'] ); ?></a>
			<?php endif; ?>

			<?php if ( ! empty( $bbai_li_secondary_cta['label'] ) ) : ?>
				<a
					class="bbai-li-btn-secondary bbai-btn bbai-btn-secondary"
					href="<?php echo esc_url( $bbai_li_secondary_cta['href'] ?? '#' ); ?>"
					data-action="<?php echo esc_attr( $bbai_li_secondary_cta['action'] ?? '' ); ?>"
					data-bbai-li-secondary-cta="1"
				><?php echo esc_html( $bbai_li_secondary_cta['label'] ); ?></a>
			<?php endif; ?>
		</div>

		<?php
		// ── Summary metrics row ───────────────────────────────────────────────
		$bbai_li_summary = is_array( $bbai_li_hero['summary'] ?? null ) ? $bbai_li_hero['summary'] : [];
		if ( ! empty( $bbai_li_summary ) ) :
		?>
		<dl class="bbai-li-summary" aria-label="<?php esc_attr_e( 'Status summary', 'beepbeep-ai-alt-text-generator' ); ?>">
			<?php foreach ( $bbai_li_summary as $bbai_li_stat ) :
				$bbai_li_stat_label = (string) ( $bbai_li_stat['label'] ?? '' );
				$bbai_li_stat_value = (string) ( $bbai_li_stat['value'] ?? '' );
				$bbai_li_stat_mod   = sanitize_html_class( (string) ( $bbai_li_stat['mod'] ?? 'muted' ) );
				if ( '' === $bbai_li_stat_label || '' === $bbai_li_stat_value ) { continue; }
			?>
			<div class="bbai-li-summary__item bbai-li-summary__item--<?php echo $bbai_li_stat_mod; ?>">
				<dt class="bbai-li-summary__label"><?php echo esc_html( $bbai_li_stat_label ); ?></dt>
				<dd class="bbai-li-summary__value"><?php echo esc_html( $bbai_li_stat_value ); ?></dd>
			</div>
			<?php endforeach; ?>
		</dl>
		<?php endif; ?>

		<?php
		// ── Progress narrative row ────────────────────────────────────────────
		// Step 1: Generate → Step 2: Review → Step 3: Done
		// Active step is determined by current state.
		$bbai_li_progress_step = 1; // default: generate
		if ( 'NEEDS_REVIEW' === $bbai_li_state_id ) {
			$bbai_li_progress_step = 2;
		} elseif ( 'ALL_CLEAR' === $bbai_li_state_id ) {
			$bbai_li_progress_step = 3;
		} elseif ( in_array( $bbai_li_state_id, [ 'QUEUED', 'QUOTA_EXHAUSTED', 'ERROR', 'NO_IMAGES' ], true ) ) {
			$bbai_li_progress_step = 0; // hide
		}
		if ( $bbai_li_progress_step > 0 ) :
			$bbai_li_step_classes = [
				1 => [ 'bbai-li-progress-steps__step--idle' ],
				2 => [ 'bbai-li-progress-steps__step--idle' ],
				3 => [ 'bbai-li-progress-steps__step--idle' ],
			];
			if ( 'MIXED_ATTENTION' === $bbai_li_state_id ) {
				$bbai_li_step_classes[1] = [ 'bbai-li-progress-steps__step--active' ];
				$bbai_li_step_classes[2] = [ 'bbai-li-progress-steps__step--active', 'bbai-li-progress-steps__step--available' ];
			} else {
				$bbai_li_step_classes[1] = 1 === $bbai_li_progress_step ? [ 'bbai-li-progress-steps__step--active' ] : ( $bbai_li_progress_step > 1 ? [ 'bbai-li-progress-steps__step--done' ] : [ 'bbai-li-progress-steps__step--idle' ] );
				$bbai_li_step_classes[2] = 2 === $bbai_li_progress_step ? [ 'bbai-li-progress-steps__step--active' ] : ( $bbai_li_progress_step > 2 ? [ 'bbai-li-progress-steps__step--done' ] : [ 'bbai-li-progress-steps__step--idle' ] );
				$bbai_li_step_classes[3] = 3 === $bbai_li_progress_step ? [ 'bbai-li-progress-steps__step--active', 'bbai-li-progress-steps__step--done' ] : [ 'bbai-li-progress-steps__step--idle' ];
			}
		?>
		<div class="bbai-li-progress-steps" role="list" aria-label="<?php esc_attr_e( 'Workflow steps', 'beepbeep-ai-alt-text-generator' ); ?>">

			<span class="bbai-li-progress-steps__step <?php echo esc_attr( implode( ' ', $bbai_li_step_classes[1] ) ); ?>" role="listitem">
				<span class="bbai-li-progress-steps__dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Generate', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>

			<span class="bbai-li-progress-steps__sep" aria-hidden="true"></span>

			<span class="bbai-li-progress-steps__step <?php echo esc_attr( implode( ' ', $bbai_li_step_classes[2] ) ); ?>" role="listitem">
				<span class="bbai-li-progress-steps__dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>

			<span class="bbai-li-progress-steps__sep" aria-hidden="true"></span>

			<span class="bbai-li-progress-steps__step <?php echo esc_attr( implode( ' ', $bbai_li_step_classes[3] ) ); ?>" role="listitem">
				<span class="bbai-li-progress-steps__dot" aria-hidden="true"></span>
				<?php esc_html_e( 'Done', 'beepbeep-ai-alt-text-generator' ); ?>
			</span>

		</div>
		<?php endif; ?>

	</div>

</div>

<?php /* ── Perceived-performance: CTA busy state + optimistic status line ── */ ?>
<script>
(function () {
	'use strict';

	var hero = document.querySelector( '[data-bbai-li-hero="1"]' );
	if ( ! hero ) { return; }

	var heroVariant = hero.getAttribute( 'data-bbai-li-variant' ) || '';
	var safetyTimer = null;
	var liveSignalTimer = null;
	var transientStatusTimer = null;
	var transitionPulseTimer = null;
	var successHighlightTimer = null;
	var processingAnimationFrame = null;
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
	};

	// Inline config from PHP — avoids dependency on missing compiled JS bundles.
	var BBAI_HERO_CFG = {
		ajaxUrl:    '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
		ajaxNonce:  '<?php echo esc_js( wp_create_nonce( 'beepbeepai_nonce' ) ); ?>',
		stateTruthUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard/state-truth' ) ); ?>',
		bootstrapSyncUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard/bootstrap-sync' ) ); ?>',
		dashboardUrl: '<?php echo esc_js( rest_url( 'bbai/v1/dashboard' ) ); ?>',
		restNonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
		missingCount: <?php
			// Pull missing count from hero summary items (same approach as activity strip).
			$bbai_hero_missing_count = 0;
			$bbai_hero_summary_items = is_array( $bbai_li_state['hero']['summary'] ?? null ) ? $bbai_li_state['hero']['summary'] : [];
			foreach ( $bbai_hero_summary_items as $bbai_hero_stat ) {
				$lbl = strtolower( (string) ( $bbai_hero_stat['label'] ?? '' ) );
				if ( strpos( $lbl, 'missing' ) !== false ) {
					$bbai_hero_missing_count = (int) str_replace( ',', '', (string) ( $bbai_hero_stat['value'] ?? '0' ) );
					break;
				}
			}
			echo $bbai_hero_missing_count;
			?>,
	};

	var BOOTSTRAP_SYNC_LOCK_TTL_MS = 5 * 60 * 1000;
	var BOOTSTRAP_SYNC_FAILURE_COOLDOWN_MS = 15 * 60 * 1000;
	var BOOTSTRAP_SYNC_SUCCESS_TTL_MS = 2 * 60 * 60 * 1000;

	var ACTION_STATUS = {
		'generate-missing': '<?php echo esc_js( __( 'Starting generation…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
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
		generationInProgress: '<?php echo esc_js( __( 'Generation in progress…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		checkingQueue: '<?php echo esc_js( __( 'Checking queue…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedJustNow: '<?php echo esc_js( __( 'Last checked just now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedSecond: '<?php echo esc_js( __( 'Last checked 1 second ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedSeconds: '<?php echo esc_js( __( 'Last checked %s seconds ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedMinute: '<?php echo esc_js( __( 'Last checked 1 minute ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedMinutes: '<?php echo esc_js( __( 'Last checked %s minutes ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedHour: '<?php echo esc_js( __( 'Last checked 1 hour ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastCheckedHours: '<?php echo esc_js( __( 'Last checked %s hours ago', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		lastRun: '<?php echo esc_js( __( 'Last run %s', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedBadge: '<?php echo esc_js( __( 'Queued', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingBadge: '<?php echo esc_js( __( 'Processing', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		actionNeededBadge: '<?php echo esc_js( __( 'Action needed', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewReadyBadge: '<?php echo esc_js( __( 'Review ready', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsNeededBadge: '<?php echo esc_js( __( 'Credits needed', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allOptimisedBadge: '<?php echo esc_js( __( 'All optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedSupport: '<?php echo esc_js( __( 'Your next batch is lined up. Start it now or leave it queued for automatic processing.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedHeadlineSingular: '<?php echo esc_js( _n( '%s image is queued', '%s images are queued', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedHeadlinePlural: '<?php echo esc_js( _n( '%s image is queued', '%s images are queued', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedPrimarySingular: '<?php echo esc_js( __( 'Start generating now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedPrimaryPlural: '<?php echo esc_js( __( 'Start generating now', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedSecondary: '<?php echo esc_js( __( 'View queued images', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedAutomatically: '<?php echo esc_js( __( 'Queued automatically', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedCountSingular: '<?php echo esc_js( _n( '%s queued', '%s queued', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		queuedCountPlural: '<?php echo esc_js( _n( '%s queued', '%s queued', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingHeadline: '<?php echo esc_js( __( 'Generating — %1$s of %2$s images done', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingSupportDone: '<?php echo esc_js( __( '%1$s optimised so far · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingSupportEta: '<?php echo esc_js( __( '%1$s remaining · ~%2$s to finish', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingRemainingSingular: '<?php echo esc_js( _n( '%s image remaining.', '%s images remaining.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingRemainingPlural: '<?php echo esc_js( _n( '%s image remaining.', '%s images remaining.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingAria: '<?php echo esc_js( __( 'Processing: %d%% complete', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		pause: '<?php echo esc_js( __( 'Pause', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		pausing: '<?php echo esc_js( __( 'Pausing…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		openAltLibrary: '<?php echo esc_js( __( 'Open ALT Library', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		approveAll: '<?php echo esc_js( __( 'Approve all', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		openReviewQueue: '<?php echo esc_js( __( 'Open review queue', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		generateMissingAlt: '<?php echo esc_js( __( 'Generate missing ALT text', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		addCredits: '<?php echo esc_js( __( 'Add credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		rescanLibrary: '<?php echo esc_js( __( 'Re-scan library', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingLabel: '<?php echo esc_js( __( 'Missing', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewLabel: '<?php echo esc_js( __( 'To review', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsLabel: '<?php echo esc_js( __( 'Credits', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		optimizedLabel: '<?php echo esc_js( __( 'Optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		creditsLeftLabel: '<?php echo esc_js( __( 'Credits left', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processingStrip: '<?php echo esc_js( __( 'Generation active', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processedCountSingular: '<?php echo esc_js( _n( '%s processed', '%s processed', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		processedCountPlural: '<?php echo esc_js( _n( '%s processed', '%s processed', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		remainingCountSingular: '<?php echo esc_js( _n( '%s remaining', '%s remaining', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		remainingCountPlural: '<?php echo esc_js( _n( '%s remaining', '%s remaining', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingAltSingular: '<?php echo esc_js( _n( '%s image needs ALT text', '%s images need ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingAltPlural: '<?php echo esc_js( _n( '%s image needs ALT text', '%s images need ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingHeadlineSingular: '<?php echo esc_js( _n( '%s image is missing ALT text', '%s images are missing ALT text', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingHeadlinePlural: '<?php echo esc_js( _n( '%s image is missing ALT text', '%s images are missing ALT text', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		mixedSupport: '<?php echo esc_js( __( 'Generate missing ALT text first, then review the suggested descriptions before they go live.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingSupport: '<?php echo esc_js( __( 'Generate the missing ALT text now to keep your library accessible, searchable, and up to date.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		missingSupportProgress: '<?php echo esc_js( __( '%1$s images already optimised — generate the remaining %2$s to complete your library.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyReviewSingular: '<?php echo esc_js( _n( '%s ready for review', '%s ready for review', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyReviewPlural: '<?php echo esc_js( _n( '%s ready for review', '%s ready for review', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewHeadlineSingular: '<?php echo esc_js( _n( '%s image is ready for review', '%s images are ready for review', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewHeadlinePlural: '<?php echo esc_js( _n( '%s image is ready for review', '%s images are ready for review', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupport: '<?php echo esc_js( __( 'ALT text is ready for a quick review before it goes live.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupportSingular: '<?php echo esc_js( _n( 'AI has prepared a suggestion for %s image — approve everything now or open the queue for a closer pass.', 'AI has prepared suggestions for %s images — approve everything now or open the queue for a closer pass.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewSupportPlural: '<?php echo esc_js( _n( 'AI has prepared a suggestion for %s image — approve everything now or open the queue for a closer pass.', 'AI has prepared suggestions for %s images — approve everything now or open the queue for a closer pass.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allImagesOptimised: '<?php echo esc_js( __( 'All images optimised', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		libraryUpToDate: '<?php echo esc_js( __( 'Library is up to date', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearHeadlineSingular: '<?php echo esc_js( _n( 'All %s image is optimised', 'All %s images are optimised', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearHeadlinePlural: '<?php echo esc_js( _n( 'All %s image is optimised', 'All %s images are optimised', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearSupport: '<?php echo esc_js( __( 'Your library is fully optimised. New uploads will be processed automatically.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearSupportProSingular: '<?php echo esc_js( _n( 'All %s image is optimised. New uploads are processed automatically.', 'All %s images are optimised. New uploads are processed automatically.', 1, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		allClearSupportProPlural: '<?php echo esc_js( _n( 'All %s image is optimised. New uploads are processed automatically.', 'All %s images are optimised. New uploads are processed automatically.', 2, 'beepbeep-ai-alt-text-generator' ) ); ?>',
		readyForNewUploads: '<?php echo esc_js( __( 'Ready for new uploads', 'beepbeep-ai-alt-text-generator' ) ); ?>',
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
		impactQueued: '<?php echo esc_js( __( 'We\'ll start generating these automatically.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
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
		confirmFailed: '<?php echo esc_js( __( 'Could not confirm the latest dashboard state yet. Refresh to check progress.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		startFailed: '<?php echo esc_js( __( 'Could not start generation. Please try again.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		noImagesNeedAlt: '<?php echo esc_js( __( 'No images need ALT text.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		etaSeconds: '<?php echo esc_js( __( '%ds', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		etaMinutes: '<?php echo esc_js( __( '%dm', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		batchReadyForReview: '<?php echo esc_js( __( 'Batch complete. Ready for review.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		reviewComplete: '<?php echo esc_js( __( 'Review complete. Library is all clear.', 'beepbeep-ai-alt-text-generator' ) ); ?>',
		approveUpdating: '<?php echo esc_js( __( 'Updating review queue…', 'beepbeep-ai-alt-text-generator' ) ); ?>',
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

	function getLoggedInDashboardRoot() {
		return document.querySelector( '[data-bbai-logged-in-dashboard]' );
	}

	function getPrimaryCta() {
		return hero.querySelector( '[data-bbai-li-primary-cta]' );
	}

	function getSecondaryCta() {
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

	function firstDefined() {
		var i;
		for ( i = 0; i < arguments.length; i += 1 ) {
			if ( undefined !== arguments[ i ] && null !== arguments[ i ] ) {
				return arguments[ i ];
			}
		}
		return undefined;
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

		if ( state ) {
			return state;
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
		var counts = payload && payload.counts && typeof payload.counts === 'object' ? payload.counts : {};
		var missing = parseInt( firstDefined( counts.missing, counts.missing_alt, counts.missingAlt, 0 ), 10 );
		var review = parseInt( firstDefined( counts.review, counts.to_review, counts.toReview, counts.needs_review, counts.needsReview, counts.weak, 0 ), 10 );
		var complete = parseInt( firstDefined( counts.complete, counts.optimized, counts.optimised, 0 ), 10 );
		var failed = parseInt( firstDefined( counts.failed, 0 ), 10 );
		var total = parseInt( firstDefined( counts.total, counts.total_images, counts.totalImages, missing + review + complete + failed ), 10 );

		return {
			missing: Math.max( 0, isNaN( missing ) ? 0 : missing ),
			review: Math.max( 0, isNaN( review ) ? 0 : review ),
			complete: Math.max( 0, isNaN( complete ) ? 0 : complete ),
			failed: Math.max( 0, isNaN( failed ) ? 0 : failed ),
			total: Math.max( 0, isNaN( total ) ? 0 : total ),
		};
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
		var credits = payload && payload.credits && typeof payload.credits === 'object' ? payload.credits : {};
		var used = parseInt( firstDefined( credits.used, credits.credits_used, credits.creditsUsed, 0 ), 10 );
		var total = parseInt( firstDefined( credits.total, credits.limit, credits.credits_total, credits.creditsTotal, 1 ), 10 );
		var remaining = parseInt( firstDefined( credits.remaining, credits.credits_remaining, credits.creditsRemaining, Math.max( 0, total - used ) ), 10 );
		var plan = String( firstDefined( credits.plan, credits.plan_slug, credits.planSlug, credits.plan_type, credits.planType, '' ) ).toLowerCase();
		var isPro = !! ( credits.is_pro || credits.isPro );

		if ( ! isPro && [ 'pro', 'growth', 'agency', 'enterprise' ].indexOf( plan ) !== -1 ) {
			isPro = true;
		}

		return {
			used: Math.max( 0, isNaN( used ) ? 0 : used ),
			total: Math.max( 1, isNaN( total ) ? 1 : total ),
			remaining: Math.max( 0, isNaN( remaining ) ? Math.max( 0, total - used ) : remaining ),
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

	function fetchStateTruth( context, opts ) {
		var options = opts || {};
		var stateTruthUrl = getStateTruthUrl();
		if ( ! stateTruthUrl || ! BBAI_HERO_CFG.restNonce ) {
			var missingConfigError = new Error( 'state_truth_config_missing' );
			if ( options.logFailure !== false ) {
				logDashboardUiFailure( context, missingConfigError );
			}
			return Promise.reject( missingConfigError );
		}

		return fetch( stateTruthUrl, {
			method:      'GET',
			credentials: 'same-origin',
			headers:     {
				'X-WP-Nonce': BBAI_HERO_CFG.restNonce,
			},
		} )
		.then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'state_truth ' + res.status );
			}
			return res.json();
		} )
		.then( function ( payload ) {
			var truth = normalizeStateTruthPayload( payload );
			var truthState = getStateTruthState( truth );
			if ( ! truth || ! truthState ) {
				throw new Error( 'state_truth_invalid' );
			}
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
			lastRunAt: getLastRunAt( truth ),
		} );
	}

	function getPollIntervalForState( state ) {
		switch ( String( state || '' ).toUpperCase() ) {
			case 'QUEUED':
			case 'PROCESSING':
				return 2500;
			case 'NEEDS_REVIEW':
			case 'MIXED_ATTENTION':
				return 12000;
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

	function clearOptimisticAction() {
		var primaryCta = getPrimaryCta();
		if ( dashboardPolling.optimisticAction && primaryCta && primaryCta.getAttribute( 'aria-busy' ) === 'true' ) {
			setBusy( primaryCta, false );
		}
		dashboardPolling.optimisticAction = '';
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
		var lastCheckedAt = job ? job.lastCheckedAt : null;
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
		if ( busy ) {
			if ( ! el.getAttribute( 'data-original-label' ) ) {
				el.setAttribute( 'data-original-label', el.textContent.trim() );
			}
			el.setAttribute( 'aria-busy', 'true' );
			el.setAttribute( 'disabled', '' );
			el.textContent = el.getAttribute( 'data-busy-label' ) || TEXT.working;
		} else {
			el.removeAttribute( 'aria-busy' );
			el.removeAttribute( 'disabled' );
			var orig = el.getAttribute( 'data-original-label' );
			if ( orig ) { el.textContent = orig; el.removeAttribute( 'data-original-label' ); }
			removeStatusLine();
		}
	}

	function flashSummaryUpdating() {
		var values = hero.querySelectorAll( '.bbai-li-summary__value' );
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
			label: TEXT.creditsLeftLabel,
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
			queuedTotal = job && job.total > 0 ? job.total : counts.missing;
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

	function buildQueuedStateData( truth ) {
		var counts = getTruthCounts( truth );
		var job = getTruthJob( truth );
		var queuedTotal = job && job.total > 0 ? job.total : counts.missing;

		return {
			state: 'QUEUED',
			hero: {
				badge: { text: TEXT.queuedBadge, mod: 'gray' },
				headline: formatSingularPlural( queuedTotal, TEXT.queuedHeadlineSingular, TEXT.queuedHeadlinePlural ),
				support: TEXT.queuedSupport,
				variant: 'queued',
				primary_cta: {
					label: 1 === queuedTotal ? TEXT.queuedPrimarySingular : TEXT.queuedPrimaryPlural,
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
				busy_label: isPrimary ? ( node.getAttribute( 'data-busy-label' ) || getActionStatusText( node ) || TEXT.working ) : undefined,
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
					center_sub_label: 'all clear',
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
		var totalOptimised = Math.max( counts.total, counts.complete );

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

				support = 'Generate missing ALT text first, then review the suggested descriptions before they go live.';

				badge = badge || { text: TEXT.actionNeededBadge, mod: 'amber' };

				primaryCta = {
					label: TEXT.generateMissingAlt,
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
				support = reuseCurrentState && current.support
					? current.support
					: ( credits.isPro && counts.complete > 0
						? replaceTokens( TEXT.missingSupportProgress, {
							'%1$s': formatCount( counts.complete ),
							'%2$s': formatCount( counts.missing ),
						} )
						: TEXT.missingSupport );
				badge = badge || { text: TEXT.actionNeededBadge, mod: 'amber' };
				primaryCta = primaryCta || {
					label: TEXT.generateMissingAlt,
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
					label: TEXT.approveAll,
					busy_label: ACTION_STATUS[ 'approve-all' ] || TEXT.working,
					action: 'approve-all',
					href: '#',
				};
				secondaryCta = secondaryCta || {
					label: TEXT.openReviewQueue,
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
			case 'ALL_CLEAR':
				headline = formatSingularPlural( totalOptimised, TEXT.allClearHeadlineSingular, TEXT.allClearHeadlinePlural );
				support = reuseCurrentState && current.support
					? current.support
					: ( credits.isPro
						? formatSingularPlural( totalOptimised, TEXT.allClearSupportProSingular, TEXT.allClearSupportProPlural )
						: TEXT.allClearSupport );
				badge = badge || { text: TEXT.allOptimisedBadge, mod: 'green' };
				primaryCta = primaryCta || {
					label: TEXT.rescanLibrary,
					busy_label: ACTION_STATUS[ 'rescan-media-library' ] || TEXT.working,
					action: 'rescan-media-library',
					href: '#',
				};
				secondaryCta = secondaryCta || {
					label: TEXT.openAltLibrary,
					action: 'navigate',
					href: getLibraryHref(),
				};
				break;
			default:
				return null;
		}

		return {
			state: state,
			id: state,
			hero: {
				badge: badge,
				headline: headline,
				support: support,
				variant: variant,
				primary_cta: primaryCta,
				secondary_cta: secondaryCta,
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

	function syncDashboardRootFromTruth( truth ) {
		var root = getDashboardRoot();
		var loggedInRoot = getLoggedInDashboardRoot();
		var counts = getTruthCounts( truth );
		var credits = getTruthCredits( truth );
		var state = getStateTruthState( truth );

		if ( root ) {
			root.setAttribute( 'data-bbai-missing-count', String( counts.missing ) );
			root.setAttribute( 'data-bbai-weak-count', String( counts.review ) );
			root.setAttribute( 'data-bbai-optimized-count', String( counts.complete ) );
			root.setAttribute( 'data-bbai-total-count', String( counts.total ) );
			root.setAttribute( 'data-bbai-generated-count', String( counts.complete ) );
			root.setAttribute( 'data-bbai-credits-used', String( credits.used ) );
			root.setAttribute( 'data-bbai-credits-total', String( credits.total ) );
			root.setAttribute( 'data-bbai-credits-remaining', String( credits.remaining ) );
			root.setAttribute( 'data-bbai-is-premium', credits.isPro ? '1' : '0' );
		}

		if ( loggedInRoot ) {
			loggedInRoot.setAttribute( 'data-state', state );
		}

		BBAI_HERO_CFG.missingCount = counts.missing;
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
				items.push( TEXT.queuedAutomatically );
				if ( job && job.total > 0 ) {
					items.push( formatSingularPlural( job.total, TEXT.queuedCountSingular, TEXT.queuedCountPlural ) );
				}
				checkedAge = job && job.lastCheckedAt ? getQueuedSignalText( Math.max( 0, Math.floor( ( Date.now() - job.lastCheckedAt ) / 1000 ) ) ) : TEXT.lastCheckedJustNow;
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
				tone = 'alert';

				if ( counts.missing > 0 ) {
					items.push( formatSingularPlural( counts.missing, TEXT.missingAltSingular, TEXT.missingAltPlural ) );
				}

				if ( counts.review > 0 ) {
					items.push( formatSingularPlural( counts.review, TEXT.readyReviewSingular, TEXT.readyReviewPlural ) );
				}

				break;
			case 'MISSING_ALT':
				tone = 'alert';
				if ( counts.missing > 0 ) {
					items.push( formatSingularPlural( counts.missing, TEXT.missingAltSingular, TEXT.missingAltPlural ) );
				}
				if ( counts.review > 0 ) {
					items.push( formatSingularPlural( counts.review, TEXT.readyReviewSingular, TEXT.readyReviewPlural ) );
				}
				if ( lastRunAt ) {
					items.push( replaceTokens( TEXT.lastRun, { '%s': formatRelativeAge( lastRunAt ) } ) );
				}
				break;
			case 'NEEDS_REVIEW':
				tone = 'warn';
				if ( counts.review > 0 ) {
					items.push( formatSingularPlural( counts.review, TEXT.readyReviewSingular, TEXT.readyReviewPlural ) );
				}
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
		Array.prototype.forEach.call( strips, function ( strip ) {
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
				if ( counts.missing > 0 && counts.review > 0 ) {
					nextText =
						formatSingularPlural( counts.missing, TEXT.missingAltSingular, TEXT.missingAltPlural ) +
						' · ' +
						formatSingularPlural( counts.review, TEXT.readyReviewSingular, TEXT.readyReviewPlural );
				}
				break;
			case 'MISSING_ALT':
				if ( complete > 0 ) {
					nextText = formatSingularPlural( complete, TEXT.impactSoFarSingle, TEXT.impactSoFarPlural );
				}
				break;
			case 'NEEDS_REVIEW':
				if ( counts.review > 0 ) {
					nextText = formatSingularPlural( counts.review, TEXT.readyReviewSingular, TEXT.readyReviewPlural );
				} else if ( complete > 0 ) {
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
			line = document.createElement( 'p' );
			line.className = 'bbai-li-impact-line';
			host.appendChild( line );
		}

		if ( line ) {
			line.textContent = nextText;
			logStatusStrip( 'impact_line_write', buildStatusStripLogContext( truth, stripMeta, nextText, 'impact_line', 'write' ) );
		}
	}

	function getSummaryItemByLabel( labelText ) {
		var items = hero.querySelectorAll( '.bbai-li-summary__item' );
		var match = null;
		Array.prototype.forEach.call( items, function ( item ) {
			var labelNode = item.querySelector( '.bbai-li-summary__label' );
			if ( match || ! labelNode ) {
				return;
			}
			if ( labelNode.textContent.trim().toLowerCase() === String( labelText || '' ).trim().toLowerCase() ) {
				match = item;
			}
		} );
		return match;
	}

	function getDisplayedCreditsSummary() {
		var item = getSummaryItemByLabel( TEXT.creditsLeftLabel ) || getSummaryItemByLabel( TEXT.creditsLabel );
		var labelNode;
		var valueNode;

		if ( ! item ) {
			return null;
		}

		labelNode = item.querySelector( '.bbai-li-summary__label' );
		valueNode = item.querySelector( '.bbai-li-summary__value' );

		return {
			displayed_label: labelNode ? String( labelNode.textContent || '' ).trim() : '',
			displayed_value: valueNode ? String( valueNode.textContent || '' ).trim() : '',
		};
	}

	function updateSummaryValueByLabel( labelText, value, mod ) {
		var item = getSummaryItemByLabel( labelText );
		var valueNode;

		if ( ! item ) {
			return;
		}

		valueNode = item.querySelector( '.bbai-li-summary__value' );
		if ( valueNode ) {
			valueNode.textContent = String( value );
			valueNode.classList.add( 'bbai-li-summary__value--updating' );
			window.setTimeout( function () {
				valueNode.classList.remove( 'bbai-li-summary__value--updating' );
			}, 420 );
		}

		if ( mod ) {
			removeClassesWithPrefix( item, 'bbai-li-summary__item--' );
			item.classList.add( 'bbai-li-summary__item--' + String( mod ).replace( /[^a-z0-9_-]/gi, '' ) );
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

	function animateProcessingTruthUpdate( previousTruth, nextTruth ) {
		var dashboard = getLoggedInDashboardRoot();
		var startedAt = 0;
		var duration = 950;
		var token;

		if ( ! canAnimateProcessingUpdate( previousTruth, nextTruth ) || 'function' !== typeof window.bbaiRenderLoggedInDashboardHeroState ) {
			cancelProcessingAnimation();
			return false;
		}

		cancelProcessingAnimation();
		token = processingAnimationToken;
		hero.setAttribute( 'data-bbai-li-live-progress', '1' );
		if ( dashboard ) {
			dashboard.setAttribute( 'data-bbai-li-live-progress', '1' );
		}

		function step( now ) {
			var frameProgress;
			var eased;
			var frameTruth;

			if ( token !== processingAnimationToken ) {
				return;
			}

			if ( ! startedAt ) {
				startedAt = now;
			}

			frameProgress = Math.min( 1, ( now - startedAt ) / duration );
			eased = 1 - Math.pow( 1 - frameProgress, 3 );
			frameTruth = buildInterpolatedProcessingTruth( previousTruth, nextTruth, eased );

			window.bbaiRenderLoggedInDashboardHeroState( buildProcessingStateData( frameTruth ), {
				skipCtas: true,
				skipUpgradeFloat: true,
				skipReviewSurface: true,
				skipDashboardAttrs: true,
			} );
			renderActivityStripFromTruth( frameTruth, buildStatusStripMeta( 'processing_animation_frame', 'animateProcessingTruthUpdate' ) );

			if ( frameProgress >= 1 ) {
				cancelProcessingAnimation();
				window.bbaiRenderLoggedInDashboardHeroState( buildProcessingStateData( nextTruth ), {
					skipCtas: true,
					skipUpgradeFloat: true,
					skipReviewSurface: true,
					skipDashboardAttrs: true,
				} );
				renderActivityStripFromTruth( nextTruth, buildStatusStripMeta( 'processing_animation_complete', 'animateProcessingTruthUpdate' ) );
				return;
			}

			processingAnimationFrame = window.requestAnimationFrame( step );
		}

		processingAnimationToken += 1;
		token = processingAnimationToken;
		processingAnimationFrame = window.requestAnimationFrame( step );
		return true;
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
		if ( ! previousTruth ) {
			if ( 'QUEUED' === nextState || 'PROCESSING' === nextState ) {
				return false;
			}
			return 'NEEDS_REVIEW' === nextState;
		}
		if ( nextState !== getStateTruthState( previousTruth ) ) {
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
		dashboardPolling.latestState = getStateTruthState( truth );
		dashboardPolling.failureCount = 0;
		dashboardPolling.requiresResolvedSync = false;
		dashboardPolling.bootstrapSyncApplied = false;
		syncDashboardRootFromTruth( truth );
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

	function applyLocalActiveTruthState( truth, context ) {
		var stateData = buildLocalActiveStateData( truth );
		if ( ! stateData || 'function' !== typeof window.bbaiApplyLoggedInDashboardStatePayload ) {
			return false;
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

	function commitTruthPayload( truth, context, forceResolved ) {
		var previousTruth = dashboardPolling.currentTruth;
		if ( forceResolved ) {
			cancelProcessingAnimation();
			return applyResolvedDashboardTruth( truth, context );
		}
		if ( previousTruth && animateProcessingTruthUpdate( previousTruth, truth ) ) {
			commitTruthSnapshot( truth, context, true, {
				skipActivityStrip: true,
			} );
			return Promise.resolve( truth );
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
				fetchStateTruth( context, {
					logLoaded: true,
					logFailure: true,
				} )
				.then( resolve )
				.catch( reject );
			}, Math.max( 0, parseInt( delayMs, 10 ) || 0 ) );
		} );
	}

	function runPollingTick( context ) {
		var pollContext = context || 'polling';
		var currentState = dashboardPolling.latestState || hero.getAttribute( 'data-bbai-li-state' ) || '';
		if ( dashboardPolling.inFlight ) {
			return;
		}
		if ( document.visibilityState === 'hidden' ) {
			stopPolling( 'hidden' );
			return;
		}
		if ( ! shouldPollState( currentState ) && ! dashboardPolling.requiresResolvedSync && 'startup' !== pollContext ) {
			stopPolling( 'stable' );
			return;
		}

		dashboardPolling.inFlight = true;
		logDashboardUi( 'polling_tick', {
			context: pollContext,
			state: currentState,
		} );

		fetchStateTruth( pollContext, {
			logLoaded: 'startup' === pollContext,
			logFailure: false,
		} )
			.then( function ( truth ) {
				return maybeBootstrapTruth( truth, pollContext );
			} )
			.then( function ( truth ) {
				var previousTruth = dashboardPolling.currentTruth;
				var previousState = previousTruth ? getStateTruthState( previousTruth ) : ( hero.getAttribute( 'data-bbai-li-state' ) || '' );
				var nextState = getStateTruthState( truth );
				var nextSignature = buildTruthSignature( truth );
				var forceResolved = dashboardPolling.requiresResolvedSync || shouldUseResolvedDashboard( truth, previousTruth );

				if ( ! previousTruth && ! forceResolved && ! dashboardPolling.bootstrapSyncApplied && ! domTruthMismatch( truth ) ) {
					var unchangedMeta = buildStatusStripMeta( pollContext, 'runPollingTick_state_unchanged' );
					dashboardPolling.currentTruth = truth;
					dashboardPolling.currentSignature = nextSignature;
					dashboardPolling.latestState = nextState;
					dashboardPolling.failureCount = 0;
					dashboardPolling.bootstrapSyncApplied = false;
					syncDashboardRootFromTruth( truth );
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
					dashboardPolling.failureCount = 0;
					dashboardPolling.bootstrapSyncApplied = false;
					syncDashboardRootFromTruth( truth );
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
			if ( ! res.ok ) { throw new Error( 'list ' + res.status ); }
			return res.json();
		} )
		.then( function ( json ) {
			if ( ! json || ! json.success || ! json.data || ! Array.isArray( json.data.ids ) ) {
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

		if ( ! BBAI_HERO_CFG.ajaxUrl || ! BBAI_HERO_CFG.ajaxNonce ) {
			if ( runEstablishedGenerateFlow( e ) ) {
				return;
			}
			showStatusLine( '<?php echo esc_js( __( 'Could not start generation. Please try again.', 'beepbeep-ai-alt-text-generator' ) ); ?>' );
			return;
		}

		logDashboardUi( 'optimistic_starting', { action: 'generate-missing' } );
		markOptimisticAction( 'generate-missing' );
		setBusy( trigger, true );
		showStatusLine( ACTION_STATUS[ 'generate-missing' ] );
		flashSummaryUpdating();

		var limit = Math.max( 1, Math.min( 500, BBAI_HERO_CFG.missingCount || 500 ) );

		// Step 1: fetch missing attachment IDs through the registered WP Ajax action.
		fetchMissingAttachmentIds( limit )
		.then( function ( ids ) {
			if ( ids.length === 0 ) {
				clearOptimisticAction();
				showStatusLine( TEXT.noImagesNeedAlt );
				return null;
			}

			return postBulkQueue( ids );
		} )
		.then( function ( queueJson ) {
			if ( ! queueJson ) { return; } // handled above (no images)
			if ( queueJson && queueJson.success ) {
				return confirmLatestStateTruth( 'generate_missing', 900 )
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
						clearOptimisticAction();
						logDashboardUiFailure( 'generate_missing', error );
						showStatusLine( TEXT.confirmFailed );
					} );
			} else {
				var msg = ( queueJson && queueJson.data && queueJson.data.message )
					? queueJson.data.message
					: TEXT.startFailed;
				clearOptimisticAction();
				showStatusLine( msg );
			}
		} )
		.catch( function () {
			clearOptimisticAction();
			showStatusLine( TEXT.startFailed );
		} );
	}

	// Donut card click → take the state-specific next step.
	var donutCard = hero.querySelector( '.bbai-li-card--donut' );
	if ( donutCard ) {
		function donutCardActivate( e ) {
			var primaryCta = getPrimaryCta();
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

		if ( primaryCta.getAttribute( 'aria-busy' ) === 'true' ) {
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

	document.addEventListener( 'bbai:logged-in-dashboard-state-applied', function ( event ) {
		var detail = event && event.detail ? event.detail : {};
		triggerDashboardTransitionFeedback( detail.previousState || '', detail.nextState || '' );
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
		var counts;
		var nextReview;
		var root;

		if ( approvedCount <= 0 || ! currentTruth || 'NEEDS_REVIEW' !== getStateTruthState( currentTruth ) ) {
			return;
		}

		counts = getTruthCounts( currentTruth );
		nextReview = Math.max( 0, counts.review - approvedCount );
		root = getDashboardRoot();

		if ( root ) {
			root.setAttribute( 'data-bbai-weak-count', String( nextReview ) );
		}

		updateSummaryValueByLabel( TEXT.reviewLabel, formatCount( nextReview ), nextReview > 0 ? 'primary' : 'ok' );
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
		return fetchStateTruth( refreshContext, {
			logLoaded: true,
			logFailure: true,
		} )
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

		if ( shouldPollState( dashboardPolling.latestState ) || dashboardPolling.requiresResolvedSync ) {
			runPollingTick( 'visibility_resume' );
		}

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

	if ( heroVariant === 'running' ) {
		showStatusLine( TEXT.generationInProgress, 'scanning', {
			meta: buildStatusStripMeta( 'legacy_init_running', 'initial_hero_variant' ),
		} );
	} else if ( heroVariant === 'queued' ) {
		renderQueuedSignal( buildStatusStripMeta( 'legacy_init_queued', 'initial_hero_variant' ) );
	}
	runPollingTick( 'startup' );

}());
</script>
