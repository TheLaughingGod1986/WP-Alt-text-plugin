<?php
/**
 * nAi dashboard component.
 *
 * @package BeepBeep_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<?php // -------- Activity strip — uploads, generations, and remaining work -------- ?>
	<?php if ( $nai_pass_total > 0 || $nai_optimized > 0 || $nai_missing > 0 || $nai_weak > 0 ) : ?>
		<div class="nai-activity" data-nai-activity>
			<div class="nai-activity__head">
				<span class="nai-eyebrow"><?php esc_html_e( 'Latest activity', 'beepbeep-ai-alt-text-generator' ); ?></span>
			</div>
			<?php if ( $nai_pass_total > 0 ) : ?>
				<div class="nai-activity__row" data-nai-activity-pass-row>
					<span class="nai-activity__icon nai-activity__icon--warn"><?php echo $nai_icon( 'upload', 9, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="nai-activity__text" data-nai-activity-pass-text>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of uploads detected. */
								_n( '%s new upload detected for today\'s pass', '%s new uploads detected for today\'s pass', $nai_pass_total, 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_pass_total )
							)
						);
						?>
					</span>
					<span class="nai-mono nai-activity__time"><?php esc_html_e( 'ready', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<a class="nai-activity__action" href="<?php echo esc_url( $nai_primary_cta_url ); ?>"><?php esc_html_e( 'Start', 'beepbeep-ai-alt-text-generator' ); ?></a>
				</div>
			<?php endif; ?>
			<?php if ( $nai_optimized > 0 ) : ?>
				<div class="nai-activity__row" data-nai-activity-optimized-row>
					<span class="nai-activity__icon nai-activity__icon--primary"><?php echo $nai_icon( 'sparkles', 9, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="nai-activity__text" data-nai-activity-optimized-text>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: optimised count, 2: coverage percent. */
								__( '%1$s images generated or improved · coverage at %2$s%%', 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_optimized ),
								(string) $nai_coverage_pct_real
							)
						);
						?>
					</span>
					<span class="nai-mono nai-activity__time"><?php esc_html_e( 'so far', 'beepbeep-ai-alt-text-generator' ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $nai_missing > 0 ) : ?>
				<div class="nai-activity__row" data-nai-activity-missing-row>
					<span class="nai-activity__icon nai-activity__icon--warn"><?php echo $nai_icon( 'info', 9, 2.4 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="nai-activity__text" data-nai-activity-missing-text>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of images still without ALT text. */
								_n( '%s image left without ALT text', '%s images left without ALT text', $nai_missing, 'beepbeep-ai-alt-text-generator' ),
								number_format_i18n( $nai_missing )
							)
						);
						?>
					</span>
					<span class="nai-mono nai-activity__time"><?php esc_html_e( 'left', 'beepbeep-ai-alt-text-generator' ); ?></span>
					<a class="nai-activity__action" href="<?php echo esc_url( $nai_missing_url ); ?>"><?php esc_html_e( 'Review', 'beepbeep-ai-alt-text-generator' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
