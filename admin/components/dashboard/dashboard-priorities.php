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
$nai_priorities_has_missing = $nai_missing > 0;
$nai_priorities_has_weak    = $nai_weak > 0;
$nai_priorities_any         = $nai_priorities_has_missing || $nai_priorities_has_weak;
?>
	<?php // -------- Today's Priorities — the very first thing a returning user sees -------- ?>
	<div class="nai-card nai-priorities">
		<div class="nai-priorities__body">
			<div class="nai-eyebrow" style="margin-bottom:8px;"><?php esc_html_e( "Today's Priorities", 'beepbeep-ai-alt-text-generator' ); ?></div>

			<?php if ( ! $nai_priorities_any ) : ?>
				<div class="nai-priorities__empty">
					<span style="display:inline-flex;color:var(--nai-ok-ink);"><?php echo $nai_icon( 'check', 16, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></span>
					<div>
						<strong style="font-weight:600;color:var(--nai-text);"><?php esc_html_e( 'No priorities today.', 'beepbeep-ai-alt-text-generator' ); ?></strong>
						<?php esc_html_e( 'Everything OptiAI has scanned looks healthy.', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="nai-priorities__list">
					<?php if ( $nai_priorities_has_missing ) : ?>
						<div class="nai-priorities__item">
							<span class="nai-chip nai-chip--danger"><span class="nai-chip__dot"></span></span>
							<span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of images missing ALT text. */
										_n( 'Fix %d missing alt tag', 'Fix %d missing alt tags', $nai_missing, 'beepbeep-ai-alt-text-generator' ),
										$nai_missing
									)
								);
								?>
							</span>
						</div>
					<?php endif; ?>
					<?php if ( $nai_priorities_has_weak ) : ?>
						<div class="nai-priorities__item">
							<span class="nai-chip nai-chip--warn"><span class="nai-chip__dot"></span></span>
							<span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of images with weak ALT text. */
										_n( 'Fix %d weak alt tag', 'Fix %d weak alt tags', $nai_weak, 'beepbeep-ai-alt-text-generator' ),
										$nai_weak
									)
								);
								?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $nai_priorities_any ) : ?>
			<div class="nai-priorities__footer">
				<a class="nai-priorities__link" href="<?php echo esc_url( $nai_primary_cta_url ); ?>">
					<?php esc_html_e( 'See what OptiAI found today', 'beepbeep-ai-alt-text-generator' ); ?> &rarr;
				</a>
			</div>
		<?php endif; ?>
	</div>
