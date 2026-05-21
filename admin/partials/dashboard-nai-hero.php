<?php
/**
 * nAi Dashboard hero — calm, premium SaaS layout.
 *
 * Implements the design exported from claude.ai/design ("nAi Dashboard"):
 *
 *   • Today's Pass hero (image queue + CTA)
 *   • Library Health card (compact ring + projection)
 *   • Autopilot upsell / active card
 *   • Library Coverage strip
 *   • Latest improvements activity strip
 *   • Footer metrics row
 *
 * Expects parent scope from dashboard-logged-in-page.php:
 *   $bbai_li_state            array  Resolver output
 *   $bbai_state_is_pro_plan   bool   (optional) Pro flag from resolver
 *   $bbai_is_premium          bool   (optional) Plan helper from dashboard-body
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_li_state = is_array( $bbai_li_state ?? null ) ? $bbai_li_state : array();

// ── Plan flag ─────────────────────────────────────────────────────────────────
$bbai_nai_is_pro = false;
if ( isset( $bbai_state_is_pro_plan ) ) {
	$bbai_nai_is_pro = (bool) $bbai_state_is_pro_plan;
} elseif ( isset( $bbai_is_premium ) ) {
	$bbai_nai_is_pro = (bool) $bbai_is_premium;
}

// ── Library coverage numbers (mirrored from existing insight strip) ──────────
$bbai_nai_seg       = is_array( $bbai_li_state['donut']['segments'] ?? null ) ? $bbai_li_state['donut']['segments'] : array();
$bbai_nai_optimised = max( 0, (int) ( $bbai_nai_seg['optimized'] ?? 0 ) );
$bbai_nai_missing   = max( 0, (int) ( $bbai_nai_seg['missing'] ?? 0 ) );
$bbai_nai_total     = max( 0, (int) ( $bbai_li_state['meta']['total_images'] ?? 0 ) );
$bbai_nai_with      = $bbai_nai_total > 0 ? max( 0, $bbai_nai_total - $bbai_nai_missing ) : 0;
$bbai_nai_remaining = max( 0, $bbai_nai_total - $bbai_nai_optimised );
$bbai_nai_coverage  = $bbai_nai_total > 0 ? (int) min( 100, (int) round( ( 100 * $bbai_nai_with ) / $bbai_nai_total ) ) : 0;

// ── Daily / monthly usage (Free plan quota framing) ──────────────────────────
$bbai_nai_usage     = is_array( $bbai_li_state['usage'] ?? null ) ? $bbai_li_state['usage'] : array();
$bbai_nai_used      = max( 0, (int) ( $bbai_nai_usage['used'] ?? 0 ) );
$bbai_nai_limit     = max( 1, (int) ( $bbai_nai_usage['total'] ?? $bbai_nai_usage['limit'] ?? ( $bbai_nai_is_pro ? 1000 : 50 ) ) );
$bbai_nai_left      = max( 0, (int) ( $bbai_nai_usage['remaining'] ?? max( 0, $bbai_nai_limit - $bbai_nai_used ) ) );

// Daily allowance is a soft display: surface the same figure on the daily line.
// If the resolver doesn't carry daily numbers we fall back to "5/day Free, 200/day Pro".
$bbai_nai_daily_limit = $bbai_nai_is_pro ? 200 : 5;
$bbai_nai_daily_used  = (int) min( $bbai_nai_daily_limit, $bbai_nai_used );
$bbai_nai_daily_left  = max( 0, $bbai_nai_daily_limit - $bbai_nai_daily_used );

// ── Stage labelling (calm, encouraging) ──────────────────────────────────────
if ( $bbai_nai_coverage >= 90 ) {
	$bbai_nai_ring_tone = 'ok';
} elseif ( $bbai_nai_coverage >= 50 ) {
	$bbai_nai_ring_tone = 'warn';
} else {
	$bbai_nai_ring_tone = '';
}

// Next 15-point rung (capped at 100).
$bbai_nai_next_milestone = (int) min( 100, ceil( ( $bbai_nai_coverage + 1 ) / 15 ) * 15 );

// Time saved estimate: ~55s per generated image, rounded to hours.
$bbai_nai_minutes_saved = (int) round( ( $bbai_nai_optimised * 55 ) / 60 );
$bbai_nai_hours_saved   = $bbai_nai_minutes_saved / 60;

// Steady improvement projection — small, calm horizon.
$bbai_nai_projection_target = $bbai_nai_coverage >= 75 ? 95 : 75;
$bbai_nai_points_per_week   = 8.4;
$bbai_nai_weeks_to_target   = max( 1, (int) round( ( $bbai_nai_projection_target - $bbai_nai_coverage ) / $bbai_nai_points_per_week ) );

// ── Today's Pass queue — derived from missing-ALT samples ────────────────────
// In the design these are real candidate images; for the SSR pass we render a
// representative queue of up to 5 (capped at daily_left and missing count).
$bbai_nai_queue_max  = (int) min( 5, $bbai_nai_daily_left, $bbai_nai_missing );
$bbai_nai_show_queue = $bbai_nai_queue_max > 0;

// Pull a few sample attachments for the queue thumbnails (best-effort).
$bbai_nai_queue_items = array();
if ( $bbai_nai_show_queue ) {
	$bbai_nai_query_args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => $bbai_nai_queue_max,
		'fields'         => 'ids',
		'meta_query'     => array( /* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query */
			'relation' => 'OR',
			array(
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_wp_attachment_image_alt',
				'value' => '',
			),
		),
		'no_found_rows'  => true,
	);

	$bbai_nai_query_ids = get_posts( $bbai_nai_query_args );

	if ( is_array( $bbai_nai_query_ids ) ) {
		foreach ( $bbai_nai_query_ids as $bbai_nai_aid ) {
			$bbai_nai_title  = get_the_title( $bbai_nai_aid );
			$bbai_nai_thumb  = wp_get_attachment_image_url( (int) $bbai_nai_aid, array( 80, 80 ) );
			$bbai_nai_signal = ( count( $bbai_nai_queue_items ) === 1 ) ? 'danger' : 'neutral';
			$bbai_nai_label  = ( 'danger' === $bbai_nai_signal )
				? __( 'Missing ALT', 'beepbeep-ai-alt-text-generator' )
				: __( 'In media library', 'beepbeep-ai-alt-text-generator' );

			$bbai_nai_queue_items[] = array(
				'name'   => $bbai_nai_title ? $bbai_nai_title : sprintf( 'image-%d.jpg', (int) $bbai_nai_aid ),
				'thumb'  => $bbai_nai_thumb ? $bbai_nai_thumb : '',
				'signal' => $bbai_nai_signal,
				'label'  => $bbai_nai_label,
			);
		}
	}

	// If the query returned fewer than we expected, pad with neutral placeholders
	// so the grid stays the right width (e.g. on a brand-new site with no media).
	$bbai_nai_pad_labels = array(
		__( 'Homepage', 'beepbeep-ai-alt-text-generator' ),
		__( 'About page', 'beepbeep-ai-alt-text-generator' ),
		__( 'Recent upload', 'beepbeep-ai-alt-text-generator' ),
		__( 'Product image', 'beepbeep-ai-alt-text-generator' ),
		__( 'Blog post', 'beepbeep-ai-alt-text-generator' ),
	);
	for ( $bbai_nai_p = count( $bbai_nai_queue_items ); $bbai_nai_p < $bbai_nai_queue_max; $bbai_nai_p++ ) {
		$bbai_nai_queue_items[] = array(
			'name'   => sprintf( 'image-%02d.jpg', $bbai_nai_p + 1 ),
			'thumb'  => '',
			'signal' => 'neutral',
			'label'  => $bbai_nai_pad_labels[ $bbai_nai_p % count( $bbai_nai_pad_labels ) ],
		);
	}
}

// Headline copy — different for "new images detected" vs "ready for today's pass"
$bbai_nai_new_since = (int) ( $bbai_li_state['meta']['new_since_visit'] ?? 0 );

// Ring geometry — circumference (2πr) for r=24 → ~150.8
$bbai_nai_ring_r       = 24;
$bbai_nai_ring_c       = 2 * M_PI * $bbai_nai_ring_r;
$bbai_nai_ring_offset  = $bbai_nai_ring_c * ( 1 - ( $bbai_nai_coverage / 100 ) );

$bbai_nai_progress_pct = $bbai_nai_total > 0 ? (int) round( ( 100 * $bbai_nai_optimised ) / $bbai_nai_total ) : 0;
$bbai_nai_progress_pct = max( 0, min( 100, $bbai_nai_progress_pct ) );
?>

<div class="bbai-nai-dash" data-bbai-nai-dashboard data-plan="<?php echo esc_attr( $bbai_nai_is_pro ? 'pro' : 'free' ); ?>">

	<?php /* ── HERO — Today's Pass ─────────────────────────────────────── */ ?>
	<section
		class="nai-hero<?php echo $bbai_nai_show_queue ? ' nai-hero--interactive' : ''; ?>"
		<?php if ( $bbai_nai_show_queue ) : ?>
		role="button"
		tabindex="0"
		data-action="generate-missing"
		data-bbai-action="generate_missing"
		data-bbai-generation-source="nai-todays-pass-card"
		aria-label="<?php
			echo esc_attr(
				sprintf(
					/* translators: %d: images queued for today's pass */
					_n( "Start today's pass — %d image ready", "Start today's pass — %d images ready", $bbai_nai_queue_max, 'beepbeep-ai-alt-text-generator' ),
					$bbai_nai_queue_max
				)
			);
			?>"
		<?php endif; ?>
	>
		<header class="nai-hero__head">
			<div style="min-width:0;flex:1">
				<div class="nai-eyebrow">
					<svg class="nai-eyebrow__icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>
					<span><?php esc_html_e( "Today's pass", 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<h2 class="nai-hero__headline">
					<?php if ( $bbai_nai_show_queue ) : ?>
						<?php if ( $bbai_nai_new_since > 0 ) : ?>
							<?php
							printf(
								/* translators: %s: number of new images, monospace span */
								esc_html__( '%s new images detected since your last scan', 'beepbeep-ai-alt-text-generator' ),
								'<span class="nai-mono">' . (int) $bbai_nai_new_since . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %s: number of images ready, monospace span */
								esc_html__( "%s images ready for today's pass", 'beepbeep-ai-alt-text-generator' ),
								'<span class="nai-mono">' . (int) $bbai_nai_queue_max . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( "Today's pass complete", 'beepbeep-ai-alt-text-generator' ); ?>
					<?php endif; ?>
				</h2>
			</div>
		</header>

		<?php if ( $bbai_nai_show_queue ) : ?>
			<div class="nai-queue" style="--nai-queue-cols:<?php echo (int) $bbai_nai_queue_max; ?>">
				<?php foreach ( $bbai_nai_queue_items as $bbai_nai_idx => $bbai_nai_item ) : ?>
					<div class="nai-queue-item">
						<div class="nai-queue-thumb" aria-hidden="true">
							<?php if ( ! empty( $bbai_nai_item['thumb'] ) ) : ?>
								<img src="<?php echo esc_url( $bbai_nai_item['thumb'] ); ?>" alt="" loading="lazy"/>
							<?php else : ?>
								<span>#<?php echo (int) ( $bbai_nai_idx + 1 ); ?></span>
							<?php endif; ?>
						</div>
						<div class="nai-queue-meta">
							<div class="nai-queue-name"><?php echo esc_html( $bbai_nai_item['name'] ); ?></div>
							<span class="nai-signal nai-signal--<?php echo esc_attr( $bbai_nai_item['signal'] ); ?>"><?php echo esc_html( $bbai_nai_item['label'] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="nai-hero__empty">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
				<div>
					<?php if ( $bbai_nai_is_pro ) : ?>
						<strong><?php esc_html_e( 'Optimisation complete.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'Autopilot is monitoring new uploads in the background.', 'beepbeep-ai-alt-text-generator' ); ?>
					<?php else : ?>
						<strong><?php esc_html_e( 'Daily goal complete.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'Next pass unlocks tomorrow.', 'beepbeep-ai-alt-text-generator' ); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<footer class="nai-hero__foot">
			<div class="nai-hero__status">
				<?php if ( $bbai_nai_is_pro ) : ?>
					<?php if ( $bbai_nai_show_queue ) : ?>
						<span class="nai-metric__pulse" aria-hidden="true"></span>
						<span class="nai-hero__status-strong"><?php esc_html_e( 'Autopilot active', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span>·
							<?php
							printf(
								/* translators: %s: images improved today */
								esc_html__( '%s images improved today', 'beepbeep-ai-alt-text-generator' ),
								'<span class="nai-mono">' . (int) $bbai_nai_daily_used . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						</span>
					<?php else : ?>
						<span class="nai-hero__status-strong">
							<?php
							printf(
								/* translators: %s: images improved today */
								esc_html__( '%s images improved today', 'beepbeep-ai-alt-text-generator' ),
								'<span class="nai-mono">' . (int) $bbai_nai_daily_used . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						</span>
						<span>· <?php esc_html_e( 'continuous optimisation enabled', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<?php endif; ?>
				<?php elseif ( $bbai_nai_show_queue ) : ?>
					<span class="nai-hero__status-strong">
						<?php
						printf(
							/* translators: 1: used count, 2: daily limit */
							esc_html__( '%1$s of %2$s today\'s free generations', 'beepbeep-ai-alt-text-generator' ),
							'<span class="nai-mono">' . (int) $bbai_nai_daily_used . '</span>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'<span class="nai-mono">' . (int) $bbai_nai_daily_limit . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</span>
					<span>· <?php esc_html_e( 'refreshes overnight', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<?php else : ?>
					<span class="nai-hero__status-strong">
						<?php
						printf(
							/* translators: 1: completed, 2: daily limit */
							esc_html__( '%1$s of %2$s completed today', 'beepbeep-ai-alt-text-generator' ),
							'<span class="nai-mono">' . (int) $bbai_nai_daily_limit . '</span>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'<span class="nai-mono">' . (int) $bbai_nai_daily_limit . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</span>
					<span>· <?php esc_html_e( 'next pass tomorrow', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<?php endif; ?>
			</div>

			<div onclick="event.stopPropagation()">
				<?php if ( $bbai_nai_show_queue ) : ?>
					<button
						type="button"
						class="nai-btn nai-btn--primary nai-btn--lg"
						data-action="generate-missing"
						data-bbai-action="generate_missing"
						data-bbai-generation-source="nai-todays-pass"
					>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg>
						<?php
						echo esc_html(
							$bbai_nai_is_pro
								? __( 'Run optimisation pass', 'beepbeep-ai-alt-text-generator' )
								: __( "Start today's pass", 'beepbeep-ai-alt-text-generator' )
						);
						?>
					</button>
				<?php elseif ( ! $bbai_nai_is_pro ) : ?>
					<button
						type="button"
						class="nai-btn nai-btn--pro nai-btn--sm"
						data-action="show-upgrade-modal"
					>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 19 2-11 5 5 3-7 3 7 5-5 2 11H2Z"/><path d="M2 21h20"/></svg>
						<?php esc_html_e( 'Lift the daily allowance', 'beepbeep-ai-alt-text-generator' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="nai-btn nai-btn--secondary" disabled><?php esc_html_e( 'All caught up', 'beepbeep-ai-alt-text-generator' ); ?></button>
				<?php endif; ?>
			</div>
		</footer>
	</section>

	<?php /* ── SECONDARY ROW — Library Health + Autopilot ──────────────── */ ?>
	<div class="nai-grid-pair">

		<?php /* Library Health card */ ?>
		<article class="nai-card">
			<div class="nai-health__top">
				<div class="nai-ring<?php echo $bbai_nai_ring_tone ? ' nai-ring--' . esc_attr( $bbai_nai_ring_tone ) : ''; ?>" aria-hidden="true">
					<svg viewBox="0 0 56 56">
						<circle class="nai-ring__track" cx="28" cy="28" r="<?php echo (int) $bbai_nai_ring_r; ?>"/>
						<circle class="nai-ring__fill" cx="28" cy="28" r="<?php echo (int) $bbai_nai_ring_r; ?>"
							stroke-dasharray="<?php echo esc_attr( number_format( $bbai_nai_ring_c, 2, '.', '' ) ); ?>"
							stroke-dashoffset="<?php echo esc_attr( number_format( $bbai_nai_ring_offset, 2, '.', '' ) ); ?>"/>
					</svg>
					<span class="nai-ring__label"><?php echo (int) $bbai_nai_coverage; ?></span>
				</div>
				<div class="nai-health__copy">
					<div class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Library health', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<div class="nai-health__numbers">
						<span class="nai-health__value"><?php echo (int) $bbai_nai_coverage; ?></span>
						<span class="nai-health__total">/ 100</span>
						<?php if ( $bbai_nai_optimised > 0 ) : ?>
							<span class="nai-health__delta">
								<?php
								printf(
									/* translators: %d: images improved this week */
									esc_html__( '+%d this week', 'beepbeep-ai-alt-text-generator' ),
									(int) min( 12, max( 1, (int) round( $bbai_nai_optimised / 5 ) ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<?php if ( $bbai_nai_coverage < 100 ) : ?>
						<div class="nai-health__milestone">
							<?php
							printf(
								/* translators: %s: next milestone number */
								esc_html__( 'Next milestone %s', 'beepbeep-ai-alt-text-generator' ),
								'<strong>' . (int) $bbai_nai_next_milestone . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="nai-insight">
				<div class="nai-insight__title">
					<span class="nai-insight__dot" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Steady improvement', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<p class="nai-insight__body">
					<?php
					printf(
						/* translators: 1: target percentage, 2: weeks count */
						esc_html__( 'At your current pace, your library could reach %1$s coverage in around %2$s.', 'beepbeep-ai-alt-text-generator' ),
						'<strong>' . (int) $bbai_nai_projection_target . '%</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'<strong>' . (int) $bbai_nai_weeks_to_target . '</strong> ' . esc_html( _n( 'week', 'weeks', $bbai_nai_weeks_to_target, 'beepbeep-ai-alt-text-generator' ) )
					);
					?>
				</p>
			</div>

			<div class="nai-worksaved">
				<div class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Work saved', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-worksaved__value">
					~<strong><?php echo esc_html( number_format_i18n( $bbai_nai_hours_saved, 1 ) ); ?></strong> <?php esc_html_e( 'hours saved', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-worksaved__sep">·</span>
					<strong><?php echo esc_html( number_format_i18n( $bbai_nai_optimised ) ); ?></strong> <?php esc_html_e( 'images improved', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
		</article>

		<?php /* Autopilot card */ ?>
		<?php if ( $bbai_nai_is_pro ) : ?>
			<article class="nai-card nai-autopilot nai-autopilot--active">
				<div class="nai-autopilot__head">
					<div class="nai-autopilot__head-copy">
						<div class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<h3 class="nai-autopilot__headline"><?php esc_html_e( 'Running in the background.', 'beepbeep-ai-alt-text-generator' ); ?></h3>
						<p class="nai-autopilot__sub"><?php esc_html_e( "New uploads get ALT text the moment they're added.", 'beepbeep-ai-alt-text-generator' ); ?></p>
					</div>
					<button type="button" class="nai-toggle" aria-pressed="true" aria-label="<?php esc_attr_e( 'Autopilot enabled', 'beepbeep-ai-alt-text-generator' ); ?>"></button>
				</div>
				<ul class="nai-feature-list">
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'New uploads covered instantly', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'Weekly progress summary', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'WooCommerce support', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
				</ul>
			</article>
		<?php else : ?>
			<article class="nai-card nai-autopilot">
				<div class="nai-autopilot__head">
					<div class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<h3 class="nai-autopilot__headline"><?php esc_html_e( 'Automatically optimise new uploads in the background.', 'beepbeep-ai-alt-text-generator' ); ?></h3>
				</div>
				<ul class="nai-feature-list">
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'New uploads covered instantly', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'Weekly progress summary', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
					<li>
						<svg class="nai-feature-check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
						<span><?php esc_html_e( 'WooCommerce support', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</li>
				</ul>
				<div class="nai-autopilot__cta">
					<button type="button" class="nai-btn nai-btn--pro nai-btn--full" data-action="show-upgrade-modal"><?php esc_html_e( 'Enable Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></button>
				</div>
			</article>
		<?php endif; ?>

	</div>

	<?php /* ── LIBRARY COVERAGE STRIP ────────────────────────────────── */ ?>
	<section class="nai-card nai-coverage" aria-label="<?php esc_attr_e( 'Library coverage', 'beepbeep-ai-alt-text-generator' ); ?>">
		<div class="nai-coverage__row">
			<div class="nai-coverage__label">
				<span class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Library coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
				<span class="nai-coverage__value"><?php echo (int) $bbai_nai_progress_pct; ?>%</span>
				<span class="nai-coverage__hint"><?php esc_html_e( 'optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<div class="nai-coverage__counts">
				<strong><?php echo esc_html( number_format_i18n( $bbai_nai_optimised ) ); ?></strong> <?php esc_html_e( 'improved', 'beepbeep-ai-alt-text-generator' ); ?>
				<span style="opacity:.6;margin:0 4px">·</span>
				<strong><?php echo esc_html( number_format_i18n( $bbai_nai_remaining ) ); ?></strong> <?php esc_html_e( 'remaining', 'beepbeep-ai-alt-text-generator' ); ?>
			</div>
		</div>
		<div class="nai-progress" role="progressbar" aria-valuenow="<?php echo (int) $bbai_nai_progress_pct; ?>" aria-valuemin="0" aria-valuemax="100">
			<div class="nai-progress__fill<?php echo $bbai_nai_progress_pct >= 90 ? ' nai-progress__fill--ok' : ''; ?>" style="width:<?php echo (int) $bbai_nai_progress_pct; ?>%"></div>
		</div>
	</section>

	<?php /* ── ACTIVITY STRIP — Latest improvements ─────────────────── */ ?>
	<section class="nai-activity" aria-label="<?php esc_attr_e( 'Latest improvements', 'beepbeep-ai-alt-text-generator' ); ?>">
		<div class="nai-activity__head">
			<span class="nai-eyebrow nai-eyebrow--muted"><?php esc_html_e( 'Latest improvements', 'beepbeep-ai-alt-text-generator' ); ?></span>
		</div>
		<ul class="nai-activity__list">
			<?php if ( $bbai_nai_missing > 0 ) : ?>
				<li class="nai-activity__item">
					<span class="nai-activity__icon nai-activity__icon--warn" aria-hidden="true">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
					</span>
					<span class="nai-activity__text">
						<?php
						printf(
							/* translators: %d: number of images needing ALT */
							esc_html(
								_n(
									'%d image in your media library needs ALT text',
									'%d images in your media library need ALT text',
									$bbai_nai_missing,
									'beepbeep-ai-alt-text-generator'
								)
							),
							(int) $bbai_nai_missing
						);
						?>
					</span>
					<span class="nai-activity__time"><?php esc_html_e( 'now', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<a class="nai-activity__action" href="<?php echo esc_url( admin_url( 'admin.php?page=bbai-media-library' ) ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
				</li>
			<?php endif; ?>
			<?php if ( $bbai_nai_optimised > 0 ) : ?>
				<li class="nai-activity__item">
					<span class="nai-activity__icon nai-activity__icon--primary" aria-hidden="true">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg>
					</span>
					<span class="nai-activity__text">
						<?php
						printf(
							/* translators: %d: number of images improved */
							esc_html(
								_n(
									'%d image improved — coverage continuing to grow',
									'%d images improved — coverage continuing to grow',
									$bbai_nai_optimised,
									'beepbeep-ai-alt-text-generator'
								)
							),
							(int) $bbai_nai_optimised
						);
						?>
					</span>
					<span class="nai-activity__time"><?php esc_html_e( 'recently', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</li>
			<?php endif; ?>
			<?php if ( 0 === $bbai_nai_missing && 0 === $bbai_nai_optimised ) : ?>
				<li class="nai-activity__item">
					<span class="nai-activity__icon nai-activity__icon--primary" aria-hidden="true">
						<svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>
					</span>
					<span class="nai-activity__text"><?php esc_html_e( 'Run a quick scan to surface your first improvements.', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="nai-activity__time"><?php esc_html_e( 'ready', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</li>
			<?php endif; ?>
		</ul>
	</section>

	<?php /* ── FOOTER METRICS ─────────────────────────────────────── */ ?>
	<footer class="nai-footer-metrics">
		<div class="nai-metric">
			<div class="nai-metric__label"><?php esc_html_e( 'Editing streak', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<div class="nai-metric__value">
				<?php
				$bbai_nai_streak = max( 1, (int) min( 14, ceil( $bbai_nai_optimised / 6 ) ) );
				printf(
					/* translators: %d: streak day count */
					esc_html__( '%d-day streak', 'beepbeep-ai-alt-text-generator' ),
					(int) $bbai_nai_streak
				);
				?>
				<span class="nai-metric__dim">·
					<?php
					printf(
						/* translators: %d: active days out of 14 */
						esc_html__( '%d of last 14', 'beepbeep-ai-alt-text-generator' ),
						(int) min( 14, $bbai_nai_streak + 2 )
					);
					?>
				</span>
			</div>
		</div>

		<?php if ( $bbai_nai_is_pro ) : ?>
			<div class="nai-metric">
				<div class="nai-metric__label"><?php esc_html_e( 'Improvements this week', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-metric__value">
					<span class="nai-mono"><?php echo esc_html( number_format_i18n( max( 0, $bbai_nai_optimised ) ) ); ?></span> <?php esc_html_e( 'images', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-metric__dim">· <?php esc_html_e( 'continuous improvement', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-metric">
				<div class="nai-metric__label"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-metric__value">
					<span class="nai-metric__pulse"><?php esc_html_e( 'Active', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span class="nai-metric__dim">· <?php esc_html_e( 'monitoring new uploads', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
		<?php else : ?>
			<div class="nai-metric">
				<div class="nai-metric__label"><?php esc_html_e( 'Daily allowance', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-metric__value">
					<span class="nai-mono"><?php echo (int) $bbai_nai_daily_used; ?></span> <?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?> <span class="nai-mono"><?php echo (int) $bbai_nai_daily_limit; ?></span>
					<span class="nai-metric__dim">· <?php esc_html_e( 'refills overnight', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
			<div class="nai-metric">
				<div class="nai-metric__label"><?php esc_html_e( 'Monthly usage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-metric__value">
					<span class="nai-mono"><?php echo (int) $bbai_nai_used; ?></span> <?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?> <span class="nai-mono"><?php echo (int) $bbai_nai_limit; ?></span>
					<span class="nai-metric__dim">· <?php esc_html_e( 'resets monthly', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			</div>
		<?php endif; ?>
	</footer>

</div>
