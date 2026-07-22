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
$nai_autopilot_upgrade_variant = ( isset( $nai_plan_slug ) && 'starter' === sanitize_key( (string) $nai_plan_slug ) ) ? 'growth' : 'starter';
?>
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
					<button class="nai-btn nai-btn--pro nai-btn--md nai-btn--full" type="button" data-nai-open-paywall="default" data-bbai-pricing-variant="<?php echo esc_attr( $nai_autopilot_upgrade_variant ); ?>"><?php esc_html_e( 'Enable Autopilot', 'beepbeep-ai-alt-text-generator' ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</div>
