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

if ( ! isset( $nai_sanitize_html_class ) || ! is_callable( $nai_sanitize_html_class ) ) {
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
}

// Reuses the same generation-id/bulk-limit wiring as the "Start today's pass" CTA
// in dashboard-hero.php — no new JS behaviour is introduced here.
$nai_pac_generation_ids = array();
if ( isset( $nai_drawer_items ) && is_array( $nai_drawer_items ) ) {
	foreach ( $nai_drawer_items as $nai_pac_drawer_item ) {
		$nai_pac_id = is_array( $nai_pac_drawer_item ) ? (int) ( $nai_pac_drawer_item['id'] ?? 0 ) : 0;
		if ( $nai_pac_id > 0 ) {
			$nai_pac_generation_ids[] = $nai_pac_id;
		}
	}
}
$nai_pac_generation_ids_attr = implode( ',', array_map( 'absint', $nai_pac_generation_ids ) );
$nai_pac_show_optimise_all   = $nai_show_queue && ! $nai_pass_blocked;
?>
	<?php // -------- Priority Action Centre -------- ?>
	<div class="nai-priority-centre">
		<div class="nai-priority-centre__header">
			<div class="nai-priority-centre__title"><?php esc_html_e( 'Priority Action Centre', 'beepbeep-ai-alt-text-generator' ); ?></div>
			<?php if ( '' !== $nai_library_url ) : ?>
				<a class="nai-priority-centre__viewall" href="<?php echo esc_url( $nai_library_url ); ?>">
					<?php esc_html_e( 'ALT Library', 'beepbeep-ai-alt-text-generator' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $nai_show_queue ) : ?>
			<div class="nai-card">
				<div class="nai-priority-centre__list">
					<?php foreach ( $nai_queue_items as $nai_pac_idx => $nai_pac_item ) : ?>
						<?php
						$nai_pac_item        = is_array( $nai_pac_item ) ? $nai_pac_item : array();
						$nai_pac_item_name   = (string) ( $nai_pac_item['name'] ?? sprintf(
							/* translators: %d: queue index. */
							__( 'image-%d', 'beepbeep-ai-alt-text-generator' ),
							$nai_pac_idx + 1
						) );
						$nai_pac_item_signal = (string) ( $nai_pac_item['signal'] ?? __( 'Needs attention', 'beepbeep-ai-alt-text-generator' ) );
						$nai_pac_item_tone   = $nai_sanitize_html_class( (string) ( $nai_pac_item['tone'] ?? 'warn' ), 'warn' );
						$nai_pac_item_hue    = (int) ( $nai_pac_item['hue'] ?? ( $nai_pac_idx * 70 ) );
						$nai_pac_item_thumb  = isset( $nai_pac_item['thumb_url'] ) ? (string) $nai_pac_item['thumb_url'] : '';
						?>
						<div class="nai-priority-centre__row">
							<div class="nai-priority-centre__row-main">
								<?php if ( '' !== $nai_pac_item_thumb ) : ?>
									<img class="nai-thumb" src="<?php echo esc_url( $nai_pac_item_thumb ); ?>" alt="" loading="lazy" decoding="async" />
								<?php else : ?>
									<div class="nai-thumb" style="background-color:oklch(0.93 0.02 <?php echo (int) $nai_pac_item_hue; ?>);background-image:repeating-linear-gradient(45deg, oklch(0.88 0.025 <?php echo (int) $nai_pac_item_hue; ?>) 0, oklch(0.88 0.025 <?php echo (int) $nai_pac_item_hue; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $nai_pac_item_hue; ?>) 6px, oklch(0.93 0.02 <?php echo (int) $nai_pac_item_hue; ?>) 12px);">#<?php echo (int) ( $nai_pac_idx + 1 ); ?></div>
								<?php endif; ?>
								<div style="min-width:0;flex:1;">
									<div class="nai-priority-centre__row-name"><?php echo esc_html( $nai_pac_item_name ); ?></div>
									<span class="nai-chip nai-chip--<?php echo esc_attr( $nai_pac_item_tone ); ?>">
										<span class="nai-chip__dot"></span>
										<?php echo esc_html( $nai_pac_item_signal ); ?>
									</span>
								</div>
							</div>
							<div class="nai-priority-centre__row-actions">
								<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( $nai_primary_cta_url ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $nai_pac_show_optimise_all ) : ?>
					<div class="nai-priority-centre__footer">
						<button
							class="nai-btn nai-btn--primary nai-btn--md"
							type="button"
							data-nai-open-drawer
							data-action="generate-missing"
							data-bbai-action="generate_missing"
							data-bbai-generation-ids="<?php echo esc_attr( $nai_pac_generation_ids_attr ); ?>"
							data-bbai-bulk-limit="<?php echo esc_attr( (string) max( 1, $nai_pass_slots ) ); ?>"
						>
							<?php echo $nai_icon( 'sparkles', 15, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?>
							<?php esc_html_e( 'Optimise All', 'beepbeep-ai-alt-text-generator' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		<?php elseif ( $nai_existing_work ) : ?>
			<div class="nai-card">
				<div class="nai-priority-centre__list">
					<?php if ( $nai_missing > 0 ) : ?>
						<div class="nai-priority-centre__row">
							<div class="nai-priority-centre__row-main">
								<span class="nai-chip nai-chip--danger" style="flex-shrink:0;"><span class="nai-chip__dot"></span><?php esc_html_e( 'Critical', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<div style="min-width:0;flex:1;">
									<div class="nai-priority-centre__row-title">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of images missing ALT text. */
												_n( 'Fix %d missing alt tag', 'Fix %d missing alt tags', $nai_missing, 'beepbeep-ai-alt-text-generator' ),
												$nai_missing
											)
										);
										?>
									</div>
									<div class="nai-priority-centre__row-sub"><?php esc_html_e( 'Images with no ALT text are invisible to screen readers and search engines.', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="nai-priority-centre__row-actions">
								<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( $nai_missing_url ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
								<a class="nai-btn nai-btn--primary nai-btn--sm" href="<?php echo esc_url( $nai_missing_url ); ?>"><?php esc_html_e( 'Optimise All', 'beepbeep-ai-alt-text-generator' ); ?></a>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( $nai_weak > 0 ) : ?>
						<div class="nai-priority-centre__row">
							<div class="nai-priority-centre__row-main">
								<span class="nai-chip nai-chip--warn" style="flex-shrink:0;"><span class="nai-chip__dot"></span><?php esc_html_e( 'High', 'beepbeep-ai-alt-text-generator' ); ?></span>
								<div style="min-width:0;flex:1;">
									<div class="nai-priority-centre__row-title">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of images with weak ALT text. */
												_n( 'Fix %d weak alt tag', 'Fix %d weak alt tags', $nai_weak, 'beepbeep-ai-alt-text-generator' ),
												$nai_weak
											)
										);
										?>
									</div>
									<div class="nai-priority-centre__row-sub"><?php esc_html_e( 'Weak ALT text may be too short, generic, or unhelpful for accessibility.', 'beepbeep-ai-alt-text-generator' ); ?></div>
								</div>
							</div>
							<div class="nai-priority-centre__row-actions">
								<a class="nai-btn nai-btn--secondary nai-btn--sm" href="<?php echo esc_url( $nai_needs_review_url ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
								<a class="nai-btn nai-btn--primary nai-btn--sm" href="<?php echo esc_url( $nai_needs_review_url ); ?>"><?php esc_html_e( 'Optimise All', 'beepbeep-ai-alt-text-generator' ); ?></a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="nai-card">
				<div class="nai-priority-centre__empty">
					<span style="display:inline-flex;color:var(--nai-ok-ink);"><?php echo $nai_icon( 'check', 18, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<div>
						<strong style="font-weight:600;color:var(--nai-text);"><?php esc_html_e( 'Nothing needs attention.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'Every scanned image looks healthy.', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
