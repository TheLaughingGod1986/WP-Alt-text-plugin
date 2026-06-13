<?php
/**
 * nAi dashboard component.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped to this included component.
?>
	<?php // -------- HERO: Today's pass -------- ?>
	<?php
	$nai_sanitize_html_class = static function ( $class, $fallback = '' ) {
		if ( function_exists( 'sanitize_html_class' ) ) {
			return sanitize_html_class( (string) $class, (string) $fallback );
		}

		$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
		if ( '' === $sanitized && '' !== $fallback ) {
			$sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $fallback );
		}

		return (string) $sanitized;
	};

	$nai_hero_interactive = ( $nai_show_queue || $nai_existing_work ) && ! $nai_pass_blocked;
	$nai_hero_attrs       = array( 'class' => 'nai-hero' . ( $nai_hero_interactive ? ' nai-hero--interactive' : '' ) );
	if ( $nai_hero_interactive ) {
		$nai_hero_attrs['role']       = 'link';
		$nai_hero_attrs['tabindex']   = '0';
		$nai_hero_attrs['data-href']  = $nai_primary_cta_url;
		$nai_hero_attrs['aria-label'] = $nai_existing_work
				? sprintf(
				/* translators: %d: number of images needing review. */
				esc_attr__( 'Review %d images that need attention', 'beepbeep-ai-alt-text-generator' ),
				$nai_existing_count
			)
			: sprintf(
				/* translators: %d: number of images ready for today's pass. */
				esc_attr__( "Start today's pass — %d images ready", 'beepbeep-ai-alt-text-generator' ),
				min( 5, $nai_pass_slots )
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
						<?php
						if ( $nai_new_since_visit > 0 ) :
							?>
							<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_new_since_visit ); ?></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d is replaced by a separate styled span above. */
									_n( ' new image detected since your last scan', ' new images detected since your last scan', $nai_new_since_visit, 'beepbeep-ai-alt-text-generator' ),
									$nai_new_since_visit
								)
							);
						else :
							?>
							<span class="nai-mono nai-tnum"><?php echo esc_html( (string) $nai_queue_size ); ?></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d is replaced by a separate styled span above. */
									_n( ' image ready for today\'s pass', ' images ready for today\'s pass', $nai_queue_size, 'beepbeep-ai-alt-text-generator' ),
									$nai_queue_size
								)
							);
						endif;
						?>
					<?php elseif ( $nai_existing_work ) : ?>
						<span class="nai-mono nai-tnum"><?php echo esc_html( number_format_i18n( $nai_existing_count ) ); ?></span>
						<?php
						echo esc_html(
							$nai_missing > 0
								? _n( ' image needs ALT text', ' images need ALT text', $nai_existing_count, 'beepbeep-ai-alt-text-generator' )
								: _n( ' image needs review', ' images need review', $nai_existing_count, 'beepbeep-ai-alt-text-generator' )
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
					<?php
					$item          = is_array( $item ) ? $item : array();
					$nai_item_name = (string) ( $item['name'] ?? sprintf(
						/* translators: %d: queue index. */
						__( 'image-%d', 'beepbeep-ai-alt-text-generator' ),
						$idx + 1
					) );
					$nai_item_tone = $nai_sanitize_html_class( (string) ( $item['tone'] ?? 'warn' ), 'warn' );
					$nai_item_signal = (string) ( $item['signal'] ?? __( 'Needs attention', 'beepbeep-ai-alt-text-generator' ) );
					$nai_item_hue  = (int) ( $item['hue'] ?? ( $idx * 70 ) );
					?>
					<div class="nai-hero__queue-item">
						<?php $nai_item_thumb = isset( $item['thumb_url'] ) ? (string) $item['thumb_url'] : ''; ?>
						<?php if ( '' !== $nai_item_thumb ) : ?>
							<img class="nai-thumb" src="<?php echo esc_url( $nai_item_thumb ); ?>" alt="" loading="lazy" decoding="async" />
						<?php else : ?>
							<div class="nai-thumb" style="background-color:oklch(0.93 0.02 <?php echo (int) $nai_item_hue; ?>);background-image:repeating-linear-gradient(45deg, oklch(0.88 0.025 <?php echo (int) $nai_item_hue; ?>) 0, oklch(0.88 0.025 <?php echo (int) $nai_item_hue; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $nai_item_hue; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $nai_item_hue; ?>) 12px);">#<?php echo (int) ( $idx + 1 ); ?></div>
						<?php endif; ?>
						<div style="min-width:0;flex:1;">
							<div class="nai-hero__queue-item-name"><?php echo esc_html( $nai_item_name ); ?></div>
							<div class="nai-hero__queue-item-signal">
								<span class="nai-chip nai-chip--<?php echo esc_attr( $nai_item_tone ); ?>">
									<span class="nai-chip__dot"></span>
									<?php echo esc_html( $nai_item_signal ); ?>
								</span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $nai_deferred > 0 ) : ?>
				<p class="nai-hero__deferred">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: images deferred until generation allowance refreshes. */
							_n( '%d image is deferred until your allowance refreshes.', '%d images are deferred until your allowance refreshes.', $nai_deferred, 'beepbeep-ai-alt-text-generator' ),
							$nai_deferred
						)
					);
					?>
				</p>
			<?php endif; ?>
		<?php elseif ( $nai_existing_work ) : ?>
			<div class="nai-hero__complete">
				<span style="display:inline-flex;color:var(--nai-warn-ink);"><?php echo $nai_icon( 'upload', 16, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<div style="flex:1;">
					<strong style="font-weight:600;"><?php echo esc_html( $nai_missing > 0 ? __( 'Missing ALT text still needs review.', 'beepbeep-ai-alt-text-generator' ) : __( 'Images still need review.', 'beepbeep-ai-alt-text-generator' ) ); ?></strong>
					<?php echo esc_html( $nai_missing > 0 ? __( 'Open the ALT Library to generate or edit the images already waiting there.', 'beepbeep-ai-alt-text-generator' ) : __( 'Open the ALT Library to review and approve the images already waiting there.', 'beepbeep-ai-alt-text-generator' ) ); ?>
				</div>
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
				<?php elseif ( empty( $nai_has_daily_cap ) ) : ?>
					<span class="nai-hero__status-strong">
						<span class="nai-mono nai-tnum" data-bbai-entitlement-used><?php echo esc_html( (string) $nai_credits_use ); ?></span>
						<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum" data-bbai-entitlement-limit><?php echo esc_html( (string) $nai_credits_lim ); ?></span>
						<?php esc_html_e( 'monthly generations used', 'beepbeep-ai-alt-text-generator' ); ?>
					</span>
					<?php if ( $nai_days_reset > 0 ) : ?>
						<span>&middot;
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
				<?php else : ?>
					<span class="nai-hero__status-strong">
						<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-used><?php echo esc_html( (string) $nai_daily_use ); ?></span>
						<?php esc_html_e( 'of', 'beepbeep-ai-alt-text-generator' ); ?>
						<span class="nai-mono nai-tnum" data-bbai-entitlement-daily-limit><?php echo esc_html( (string) $nai_daily_limit ); ?></span>
						<?php esc_html_e( "today's free generations", 'beepbeep-ai-alt-text-generator' ); ?>
						</span>
						<?php if ( $nai_daily_hours_left > 0 ) : ?>
							<?php
							$nai_daily_reset_label = $nai_daily_rem <= 0
								/* translators: %d: hours until the next daily pass. */
								? sprintf( __( 'next pass in %dh', 'beepbeep-ai-alt-text-generator' ), $nai_daily_hours_left )
								/* translators: %d: hours until the daily allowance refreshes. */
								: sprintf( __( 'refreshes in %dh', 'beepbeep-ai-alt-text-generator' ), $nai_daily_hours_left );
							?>
							<span>· <span data-bbai-entitlement-daily-reset><?php echo esc_html( $nai_daily_reset_label ); ?></span></span>
						<?php elseif ( $nai_days_reset > 0 ) : ?>
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
			<div class="nai-hero__action">
				<?php if ( $nai_show_queue && ! $nai_pass_blocked ) : ?>
					<?php
					$nai_generation_ids = array();
					if ( isset( $nai_drawer_items ) && is_array( $nai_drawer_items ) ) {
						foreach ( $nai_drawer_items as $nai_generation_item ) {
							$nai_generation_id = is_array( $nai_generation_item ) ? (int) ( $nai_generation_item['id'] ?? 0 ) : 0;
							if ( $nai_generation_id > 0 ) {
								$nai_generation_ids[] = $nai_generation_id;
							}
						}
					}
					$nai_generation_ids_attr = implode( ',', array_map( 'absint', $nai_generation_ids ) );
					?>
					<?php if ( $nai_show_tweaks ) : ?>
						<a
							class="nai-btn nai-btn--primary nai-btn--lg"
							href="<?php echo esc_url( $nai_primary_cta_url ); ?>"
							data-bbai-nai-cta="start-pass"
							data-nai-open-drawer
							data-action="generate-missing"
							data-bbai-action="generate_missing"
							data-bbai-generation-ids="<?php echo esc_attr( $nai_generation_ids_attr ); ?>"
							data-bbai-bulk-limit="<?php echo esc_attr( (string) max( 1, $nai_pass_slots ) ); ?>"
						>
							<?php echo $nai_icon( 'sparkles', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php
							echo esc_html(
								$nai_is_pro
									? __( 'Run optimisation pass', 'beepbeep-ai-alt-text-generator' )
									: __( "Start today's pass", 'beepbeep-ai-alt-text-generator' )
							);
							?>
						</a>
					<?php else : ?>
						<button
							class="nai-btn nai-btn--primary nai-btn--lg"
							type="button"
							data-bbai-nai-cta="start-pass"
							data-nai-open-drawer
							data-action="generate-missing"
							data-bbai-action="generate_missing"
							data-bbai-generation-ids="<?php echo esc_attr( $nai_generation_ids_attr ); ?>"
							data-bbai-bulk-limit="<?php echo esc_attr( (string) max( 1, $nai_pass_slots ) ); ?>"
						>
						<?php echo $nai_icon( 'sparkles', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php
						echo esc_html(
							$nai_is_pro
								? __( 'Run optimisation pass', 'beepbeep-ai-alt-text-generator' )
								: __( "Start today's pass", 'beepbeep-ai-alt-text-generator' )
						);
						?>
						</button>
					<?php endif; ?>
				<?php elseif ( $nai_existing_work ) : ?>
					<a class="nai-btn nai-btn--primary nai-btn--lg" href="<?php echo esc_url( $nai_missing_url ); ?>" data-bbai-nai-cta="review-missing">
						<?php echo $nai_icon( 'arrow-right', 16, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( $nai_missing > 0 ? __( 'Review missing ALT', 'beepbeep-ai-alt-text-generator' ) : __( 'Review images', 'beepbeep-ai-alt-text-generator' ) ); ?>
					</a>
				<?php elseif ( ! $nai_is_pro ) : ?>
					<button class="nai-btn nai-btn--pro nai-btn--sm" type="button" data-nai-open-paywall="<?php echo esc_attr( $nai_credits_rem <= 0 ? 'monthly-limit' : 'daily-limit' ); ?>" data-bbai-nai-cta="upgrade">
						<?php echo $nai_icon( 'crown', 14, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( $nai_credits_rem <= 0 ? __( 'Lift the monthly allowance', 'beepbeep-ai-alt-text-generator' ) : __( 'Upgrade to keep generating', 'beepbeep-ai-alt-text-generator' ) ); ?>
					</button>
				<?php else : ?>
					<button class="nai-btn nai-btn--secondary nai-btn--md" type="button" disabled><?php esc_html_e( 'All caught up', 'beepbeep-ai-alt-text-generator' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
	</div>
