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

$nai_state = isset( $bbai_dashboard_state ) && is_array( $bbai_dashboard_state ) ? $bbai_dashboard_state : array();

$nai_total       = max( 0, (int) ( $nai_state['totalImages'] ?? 0 ) );
$nai_optimized   = max( 0, (int) ( $nai_state['optimizedCount'] ?? 0 ) );
$nai_missing     = max( 0, (int) ( $nai_state['missingCount'] ?? 0 ) );
$nai_weak        = max( 0, (int) ( $nai_state['weakCount'] ?? 0 ) );
$nai_coverage    = max( 0, min( 100, (int) ( $nai_state['coveragePercent'] ?? 0 ) ) );
$nai_is_pro      = ! empty( $nai_state['isProPlan'] );
$nai_credits_use = max( 0, (int) ( $nai_state['creditsUsed'] ?? 0 ) );
$nai_credits_lim = max( 1, (int) ( $nai_state['creditsLimit'] ?? 1 ) );
$nai_credits_rem = max( 0, (int) ( $nai_state['creditsRemaining'] ?? 0 ) );
$nai_days_reset  = max( 0, (int) ( $nai_state['daysUntilReset'] ?? 0 ) );

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

// Build a small preview queue from real attention buckets.
$nai_queue_size  = min( 5, max( 0, $nai_missing + $nai_weak ) );
$nai_show_queue  = $nai_queue_size > 0;
$nai_queue_items = array();
for ( $i = 0; $i < $nai_queue_size; $i++ ) {
	/* translators: %d: queue index, used as a placeholder filename in the preview row. */
	$nai_queue_name = sprintf( __( 'image-%d', 'beepbeep-ai-alt-text-generator' ), $i + 1 );
	if ( $i < $nai_missing ) {
		$nai_queue_items[] = array(
			'name'   => $nai_queue_name,
			'signal' => __( 'Missing ALT', 'beepbeep-ai-alt-text-generator' ),
			'tone'   => 'danger',
			'hue'    => ( $i * 70 ) % 360,
		);
	} else {
		$nai_queue_items[] = array(
			'name'   => $nai_queue_name,
			'signal' => __( 'Needs review', 'beepbeep-ai-alt-text-generator' ),
			'tone'   => 'warn',
			'hue'    => ( $i * 70 ) % 360,
		);
	}
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
?>
<div class="nai-screen nai-screen--dashboard" data-nai-screen="dashboard">

	<?php // -------- HERO: Today's pass -------- ?>
	<?php
	$nai_hero_interactive = $nai_show_queue;
	$nai_hero_attrs       = array( 'class' => 'nai-hero' . ( $nai_hero_interactive ? ' nai-hero--interactive' : '' ) );
	if ( $nai_hero_interactive ) {
		$nai_hero_attrs['role']       = 'link';
		$nai_hero_attrs['tabindex']   = '0';
		$nai_hero_attrs['data-href']  = $nai_primary_cta_url;
		$nai_hero_attrs['aria-label'] = sprintf(
			/* translators: %d: number of images ready for today's pass. */
			esc_attr__( "Start today's pass — %d images ready", 'beepbeep-ai-alt-text-generator' ),
			$nai_queue_size
		);
	}
	?>
	<div 
	<?php
	foreach ( $nai_hero_attrs as $k => $v ) {
		echo esc_attr( $k ) . '="' . esc_attr( $v ) . '" '; }
	?>
	>
		<div class="nai-hero__header">
			<div style="min-width:0;flex:1;">
				<div class="nai-hero__eyebrow">
					<span style="color:var(--nai-primary);display:inline-flex;"><?php echo $nai_icon( 'zap', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<span class="nai-eyebrow nai-eyebrow--primary"><?php esc_html_e( "Today's pass", 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="nai-hero__title">
					<?php if ( $nai_show_queue ) : ?>
						<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_queue_size ); ?></span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d is replaced by a separate styled span above. */
								_n( ' image ready for today\'s pass', ' images ready for today\'s pass', $nai_queue_size, 'beepbeep-ai-alt-text-generator' ),
								$nai_queue_size
							)
						);
						?>
					<?php else : ?>
						<?php esc_html_e( "Today's pass complete", 'beepbeep-ai-alt-text-generator' ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( $nai_show_queue ) : ?>
			<div class="nai-hero__queue" style="grid-template-columns:repeat(<?php echo (int) $nai_queue_size; ?>, minmax(0, 1fr));">
				<?php foreach ( $nai_queue_items as $idx => $item ) : ?>
					<div class="nai-hero__queue-item">
						<div class="nai-thumb" style="background-color:oklch(0.93 0.02 <?php echo (int) $item['hue']; ?>);background-image:repeating-linear-gradient(45deg, oklch(0.88 0.025 <?php echo (int) $item['hue']; ?>) 0, oklch(0.88 0.025 <?php echo (int) $item['hue']; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $item['hue']; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $item['hue']; ?>) 12px);">#<?php echo (int) ( $idx + 1 ); ?></div>
						<div style="min-width:0;flex:1;">
							<div class="nai-hero__queue-item-name"><?php echo esc_html( $item['name'] ); ?></div>
							<div class="nai-hero__queue-item-signal">
								<span class="nai-chip nai-chip--<?php echo esc_attr( $item['tone'] ); ?>">
									<span class="nai-chip__dot"></span>
									<?php echo esc_html( $item['signal'] ); ?>
								</span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="nai-hero__complete">
				<span style="display:inline-flex;color:var(--nai-ok-ink);"><?php echo $nai_icon( 'check', 16, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div style="flex:1;">
					<?php if ( $nai_is_pro ) : ?>
						<strong style="font-weight:600;"><?php esc_html_e( 'Optimisation complete.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'Autopilot is monitoring new uploads in the background.', 'beepbeep-ai-alt-text-generator' ); ?>
					<?php else : ?>
						<strong style="font-weight:600;"><?php esc_html_e( 'Library fully covered.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'New uploads will appear here when they need ALT text.', 'beepbeep-ai-alt-text-generator' ); ?>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="nai-hero__footer">
			<div class="nai-hero__status nai-tnum">
				<?php if ( $nai_is_pro ) : ?>
					<span class="nai-pulse-dot" style="width:6px;height:6px;"></span>
					<span class="nai-hero__status-strong"><?php esc_html_e( 'Autopilot active', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<span>·
						<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_credits_use ); ?></span>
						<?php esc_html_e( 'images improved this period', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				<?php else : ?>
					<span class="nai-hero__status-strong">
						<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_credits_use ); ?></span>
						<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_credits_lim ); ?></span>
						<?php esc_html_e( 'monthly generations', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
					<?php if ( $nai_days_reset > 0 ) : ?>
						<span>·
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: days until quota resets. */
									_n( 'resets in %d day', 'resets in %d days', $nai_days_reset, 'beepbeep-ai-alt-text-generator' ),
									$nai_days_reset
								)
							);
							?>
						</span>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div onclick="event.stopPropagation();" style="display:inline-flex;">
				<?php if ( $nai_show_queue ) : ?>
					<a class="nai-btn nai-btn--primary nai-btn--lg" href="<?php echo esc_url( $nai_primary_cta_url ); ?>" data-bbai-nai-cta="start-pass">
						<?php echo $nai_icon( 'sparkles', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php
						echo esc_html(
							$nai_is_pro
								? __( 'Run optimisation pass', 'beepbeep-ai-alt-text-generator' )
								: __( "Start today's pass", 'beepbeep-ai-alt-text-generator' )
						);
						?>
					</a>
				<?php elseif ( ! $nai_is_pro ) : ?>
					<button class="nai-btn nai-btn--pro nai-btn--sm" type="button" data-action="show-upgrade-modal" data-bbai-nai-cta="upgrade">
						<?php echo $nai_icon( 'crown', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Lift the monthly allowance', 'beepbeep-ai-alt-text-generator' ); ?>
					</button>
				<?php else : ?>
					<button class="nai-btn nai-btn--secondary nai-btn--md" type="button" disabled><?php esc_html_e( 'All caught up', 'beepbeep-ai-alt-text-generator' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php // -------- ROW 2: Library health + Autopilot -------- ?>
	<div class="nai-row-2">

		<div class="nai-card nai-health">
			<div class="nai-health__top">
				<div class="nai-ring <?php echo esc_attr( $nai_tone_class ); ?>" style="width:<?php echo (int) $nai_ring_size; ?>px;height:<?php echo (int) $nai_ring_size; ?>px;">
					<svg width="<?php echo (int) $nai_ring_size; ?>" height="<?php echo (int) $nai_ring_size; ?>">
						<circle class="nai-ring__track" cx="<?php echo (int) ( $nai_ring_size / 2 ); ?>" cy="<?php echo (int) ( $nai_ring_size / 2 ); ?>" r="<?php echo esc_attr( (string) $nai_ring_radius ); ?>" stroke-width="<?php echo (int) $nai_ring_stroke; ?>"/>
						<circle class="nai-ring__fill" cx="<?php echo (int) ( $nai_ring_size / 2 ); ?>" cy="<?php echo (int) ( $nai_ring_size / 2 ); ?>" r="<?php echo esc_attr( (string) $nai_ring_radius ); ?>" stroke-width="<?php echo (int) $nai_ring_stroke; ?>" stroke-dasharray="<?php echo esc_attr( sprintf( '%F %F', $nai_ring_dash, $nai_ring_circ ) ); ?>"/>
					</svg>
					<div class="nai-ring__center">
						<div class="nai-mono nai-tnum" style="font-size:15px;font-weight:600;letter-spacing:-0.02em;"><?php echo esc_html( (string) $nai_coverage ); ?></div>
					</div>
				</div>
				<div style="flex:1;min-width:0;">
					<div class="nai-eyebrow" style="margin-bottom:4px;"><?php esc_html_e( 'Library health', 'beepbeep-ai-alt-text-generator' ); ?></div>
					<div class="nai-health__headline">
						<span class="nai-mono nai-tnum nai-health__num"><?php echo esc_html( (string) $nai_coverage ); ?></span>
						<span class="nai-health__max"><?php esc_html_e( '/ 100', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<?php if ( $nai_distance_to_milestone > 0 ) : ?>
						<div class="nai-health__milestone">
							<?php esc_html_e( 'Next milestone', 'beepbeep-ai-alt-text-generator' ); ?>
							<span class="nai-mono nai-tnum" style="color:var(--nai-text);font-weight:600;"><?php echo esc_html( (string) $nai_next_milestone ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="nai-health__insight">
				<div class="nai-health__insight-title">
					<span class="nai-ambient-pulse" aria-hidden="true"></span>
					<span class="nai-health__insight-title-text"><?php esc_html_e( 'Steady improvement', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
				<div class="nai-health__insight-body">
					<?php if ( $nai_coverage >= 100 ) : ?>
						<?php esc_html_e( 'Your library is fully optimised. Autopilot can keep new uploads covered automatically.', 'beepbeep-ai-alt-text-generator' ); ?>
					<?php elseif ( $nai_coverage >= $nai_projection_target ) : ?>
						<?php esc_html_e( "You're holding ahead of the curve. Keep the steady pace and you'll close the remaining gap shortly.", 'beepbeep-ai-alt-text-generator' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'At your current pace, your library could reach', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum" style="color:var(--nai-text);font-weight:600;"><?php echo esc_html( (string) $nai_projection_target ); ?>%</span>
						<?php esc_html_e( 'coverage in around', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum" style="color:var(--nai-text);font-weight:600;"><?php echo esc_html( (string) $nai_weeks_to_target ); ?></span>
						<?php
						echo esc_html(
							_n( 'week.', 'weeks.', $nai_weeks_to_target, 'beepbeep-ai-alt-text-generator' )
						);
						?>
					<?php endif; ?>
				</div>
			</div>

			<div class="nai-health__work">
				<div class="nai-eyebrow"><?php esc_html_e( 'Work saved', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-tnum">
					~<span class="nai-mono nai-health__work-strong"><?php echo esc_html( number_format_i18n( $nai_hours_saved, 1 ) ); ?></span>
					<?php esc_html_e( 'hours saved', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-health__work-sep">·</span>
					<span class="nai-mono nai-health__work-strong"><?php echo esc_html( number_format_i18n( $nai_optimized ) ); ?></span>
					<?php esc_html_e( 'images improved', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
		</div>

		<?php if ( $nai_is_pro ) : ?>
			<div class="nai-card nai-autopilot">
				<div class="nai-autopilot__header">
					<div style="min-width:0;">
						<div class="nai-eyebrow" style="margin-bottom:6px;"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="nai-autopilot__title"><?php esc_html_e( 'Running in the background.', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="nai-autopilot__sub"><?php esc_html_e( "New uploads get ALT text the moment they're added.", 'beepbeep-ai-alt-text-generator' ); ?></div>
					</div>
					<span class="nai-toggle nai-toggle--on" role="img" aria-label="<?php esc_attr_e( 'Autopilot enabled', 'beepbeep-ai-alt-text-generator' ); ?>"><span class="nai-toggle__knob"></span></span>
				</div>
				<div class="nai-autopilot__list">
					<?php
					$nai_pro_rows = array(
						__( 'New uploads covered instantly', 'beepbeep-ai-alt-text-generator' ),
						__( 'Weekly progress summary', 'beepbeep-ai-alt-text-generator' ),
						__( 'WooCommerce support', 'beepbeep-ai-alt-text-generator' ),
					);
					foreach ( $nai_pro_rows as $row ) :
						?>
						<div class="nai-autopilot__row">
							<span style="color:var(--nai-ok-ink);display:inline-flex;flex-shrink:0;"><?php echo $nai_icon( 'check', 13, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php echo esc_html( $row ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="nai-card nai-autopilot">
				<div class="nai-autopilot__header">
					<div style="min-width:0;">
						<div class="nai-eyebrow" style="margin-bottom:6px;"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
						<div class="nai-autopilot__title"><?php esc_html_e( 'Automatically optimise new uploads in the background.', 'beepbeep-ai-alt-text-generator' ); ?></div>
					</div>
				</div>
				<div class="nai-autopilot__list">
					<?php
					$nai_free_rows = array(
						__( 'New uploads covered instantly', 'beepbeep-ai-alt-text-generator' ),
						__( 'Weekly progress summary', 'beepbeep-ai-alt-text-generator' ),
						__( 'WooCommerce support', 'beepbeep-ai-alt-text-generator' ),
					);
					foreach ( $nai_free_rows as $row ) :
						?>
						<div class="nai-autopilot__row">
							<span style="color:var(--nai-ok-ink);display:inline-flex;flex-shrink:0;"><?php echo $nai_icon( 'check', 13, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span><?php echo esc_html( $row ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="nai-autopilot__cta">
					<button class="nai-btn nai-btn--pro nai-btn--md nai-btn--full" type="button" data-action="show-upgrade-modal" data-bbai-pricing-variant="growth"><?php esc_html_e( 'Enable Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php // -------- Library coverage -------- ?>
	<?php if ( $nai_total > 0 ) : ?>
		<div class="nai-card nai-coverage">
			<div class="nai-coverage__inner">
				<div class="nai-coverage__row">
					<div class="nai-coverage__head">
						<span class="nai-eyebrow"><?php esc_html_e( 'Library coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span class="nai-mono nai-tnum nai-coverage__num"><?php echo esc_html( (string) $nai_coverage_pct_real ); ?>%</span>
						<span class="nai-coverage__sub"><?php esc_html_e( 'optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<div class="nai-tnum nai-coverage__stats">
						<span class="nai-mono nai-coverage__stats-strong"><?php echo esc_html( number_format_i18n( $nai_optimized ) ); ?></span>
						<?php esc_html_e( 'improved', 'beepbeep-ai-alt-text-generator' ); ?>
						<span> · </span>
						<span class="nai-mono nai-coverage__stats-strong"><?php echo esc_html( number_format_i18n( $nai_remaining_total ) ); ?></span>
						<?php esc_html_e( 'remaining', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
				</div>
				<div class="nai-progress <?php echo esc_attr( $nai_coverage_tone_cls ); ?>">
					<div class="nai-progress__bar" style="width:<?php echo esc_attr( (string) $nai_coverage_pct_real ); ?>%;"></div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php // -------- Activity strip — two most recent improvements -------- ?>
	<?php if ( $nai_optimized > 0 || $nai_missing > 0 || $nai_weak > 0 ) : ?>
		<div class="nai-activity">
			<div class="nai-activity__head">
				<span class="nai-eyebrow"><?php esc_html_e( 'Latest improvements', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<?php if ( $nai_missing > 0 ) : ?>
				<div class="nai-activity__row">
					<span class="nai-activity__icon nai-activity__icon--warn"><?php echo $nai_icon( 'upload', 9, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="nai-activity__text">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of images missing ALT text. */
								_n( '%s image still needs ALT text', '%s images still need ALT text', $nai_missing, 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_missing )
							)
						);
						?>
					</span>
					<span class="nai-mono nai-activity__time"><?php esc_html_e( 'pending', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<a class="nai-activity__action" href="<?php echo esc_url( $nai_missing_url ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
				</div>
			<?php endif; ?>
			<?php if ( $nai_optimized > 0 ) : ?>
				<div class="nai-activity__row">
					<span class="nai-activity__icon nai-activity__icon--primary"><?php echo $nai_icon( 'sparkles', 9, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="nai-activity__text">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: optimised count, 2: coverage percent. */
								__( '%1$s images improved · coverage at %2$s%%', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_optimized ),
								(string) $nai_coverage_pct_real
							)
						);
						?>
					</span>
					<span class="nai-mono nai-activity__time"><?php esc_html_e( 'so far', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php // -------- Footer metrics -------- ?>
	<div class="nai-footer-metrics">
		<?php if ( $nai_is_pro ) : ?>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Improvements this month', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( number_format_i18n( $nai_credits_use ) ); ?></span>
					<?php esc_html_e( 'images', 'beepbeep-ai-alt-text-generator' ); ?>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Coverage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_coverage ); ?>%</span>
					<span class="nai-footer-metrics__muted">·
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: total images. */
								__( '%s images', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_total )
							)
						);
						?>
					</span>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-pulse-dot" style="width:6px;height:6px;"></span>
					<?php esc_html_e( 'Active', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">·
						<?php esc_html_e( 'monitoring new uploads', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
			</div>
		<?php else : ?>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Monthly usage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_credits_use ); ?></span>
					<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_credits_lim ); ?></span>
					<?php if ( $nai_days_reset > 0 ) : ?>
						<span class="nai-footer-metrics__muted">·
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: days until quota resets. */
									_n( 'resets in %d day', 'resets in %d days', $nai_days_reset, 'beepbeep-ai-alt-text-generator' ),
									$nai_days_reset
								)
							);
							?>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Coverage', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_coverage ); ?>%</span>
					<span class="nai-footer-metrics__muted">·
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: total images. */
								__( '%s images', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_total )
							)
						);
						?>
					</span>
				</div>
			</div>
			<div class="nai-footer-metrics__item">
				<div class="nai-footer-metrics__label"><?php esc_html_e( 'Library health', 'beepbeep-ai-alt-text-generator' ); ?></div>
				<div class="nai-footer-metrics__value">
					<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_missing ); ?></span>
					<?php esc_html_e( 'missing', 'beepbeep-ai-alt-text-generator' ); ?>
					<span class="nai-footer-metrics__muted">·
						<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_weak ); ?></span>
						<?php esc_html_e( 'to review', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
( function () {
	var hero = document.querySelector( '.nai-dashboard .nai-hero--interactive' );
	if ( ! hero ) { return; }
	var href = hero.getAttribute( 'data-href' );
	if ( ! href ) { return; }
	var go = function () { window.location.href = href; };
	hero.addEventListener( 'click', function ( event ) {
		// Buttons/links inside hero handle their own clicks.
		if ( event.target.closest( 'a, button' ) ) { return; }
		go();
	} );
	hero.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' || event.key === ' ' ) {
			event.preventDefault();
			go();
		}
	} );
}() );
</script>
