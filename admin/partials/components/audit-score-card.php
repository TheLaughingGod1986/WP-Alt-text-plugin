<?php
/**
 * Image accessibility health score card.
 *
 * $bbai_ui keys:
 * - score: 0–100
 * - scanned: images scanned
 * - total: images in scope
 * - passing: images with no detected issues
 * - attention: images needing attention
 * - has_run: whether an audit has ever completed
 * - last_completed_at: unix timestamp, 0 when never
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_ui        = isset( $bbai_ui ) && is_array( $bbai_ui ) ? $bbai_ui : array();
$bbai_score     = max( 0, min( 100, (int) ( $bbai_ui['score'] ?? 0 ) ) );
$bbai_scanned   = max( 0, (int) ( $bbai_ui['scanned'] ?? 0 ) );
$bbai_total     = max( 0, (int) ( $bbai_ui['total'] ?? 0 ) );
$bbai_passing   = max( 0, (int) ( $bbai_ui['passing'] ?? 0 ) );
$bbai_attention = max( 0, (int) ( $bbai_ui['attention'] ?? 0 ) );
$bbai_has_run   = ! empty( $bbai_ui['has_run'] );
$bbai_last_at   = max( 0, (int) ( $bbai_ui['last_completed_at'] ?? 0 ) );

// Tone tracks the score band, matching the documented thresholds.
if ( ! $bbai_has_run ) {
	$bbai_tone = 'neutral';
} elseif ( $bbai_score >= 90 ) {
	$bbai_tone = 'success';
} elseif ( $bbai_score >= 50 ) {
	$bbai_tone = 'warning';
} else {
	$bbai_tone = 'danger';
}

$bbai_ring_degrees = (int) round( ( $bbai_score / 100 ) * 360 );
?>
<section
	class="bbai-audit-score bbai-audit-score--<?php echo esc_attr( $bbai_tone ); ?> bbai-dashboard-surface-card bbai-card"
	aria-labelledby="bbai-audit-score-heading"
>
	<div class="bbai-audit-score__main">
		<div
			class="bbai-audit-score__ring"
			style="--bbai-audit-score-degrees: <?php echo esc_attr( (string) $bbai_ring_degrees ); ?>deg;"
			role="img"
			aria-label="
			<?php
			echo esc_attr(
				$bbai_has_run
					? sprintf(
						/* translators: %d: health score out of 100. */
						__( 'Image accessibility health score: %d out of 100.', 'beepbeep-ai-alt-text-generator' ),
						$bbai_score
					)
					: __( 'No image audit has run yet.', 'beepbeep-ai-alt-text-generator' )
			);
			?>
			"
		>
			<span class="bbai-audit-score__value" aria-hidden="true">
				<?php echo $bbai_has_run ? esc_html( (string) $bbai_score ) : esc_html( '—' ); ?>
			</span>
			<span class="bbai-audit-score__denominator" aria-hidden="true">
				<?php echo $bbai_has_run ? esc_html__( '/ 100', 'beepbeep-ai-alt-text-generator' ) : ''; ?>
			</span>
		</div>

		<div class="bbai-audit-score__body">
			<h2 id="bbai-audit-score-heading" class="bbai-audit-score__heading">
				<?php esc_html_e( 'Image Accessibility Health', 'beepbeep-ai-alt-text-generator' ); ?>
			</h2>

			<?php if ( $bbai_has_run ) : ?>
				<p class="bbai-audit-score__summary">
					<?php
					printf(
						/* translators: 1: images with no detected issues, 2: images scanned. */
						esc_html__( '%1$s of %2$s scanned images have no issues detected by the current audit rules.', 'beepbeep-ai-alt-text-generator' ),
						'<strong>' . esc_html( number_format_i18n( $bbai_passing ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $bbai_scanned ) ) . '</strong>'
					);
					?>
				</p>

				<?php if ( $bbai_attention > 0 ) : ?>
					<p class="bbai-audit-score__attention">
						<?php
						printf(
							/* translators: %s: number of images needing attention. */
							esc_html( _n( '%s image requires attention.', '%s images require attention.', $bbai_attention, 'beepbeep-ai-alt-text-generator' ) ),
							esc_html( number_format_i18n( $bbai_attention ) )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $bbai_last_at > 0 ) : ?>
					<p class="bbai-audit-score__meta">
						<?php
						printf(
							/* translators: %s: human-readable time difference, e.g. "5 minutes". */
							esc_html__( 'Last audit completed %s ago.', 'beepbeep-ai-alt-text-generator' ),
							esc_html( human_time_diff( $bbai_last_at, time() ) )
						);
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="bbai-audit-score__summary">
					<?php esc_html_e( 'Run an image audit to see how many images are missing alt text, and which ones to fix first.', 'beepbeep-ai-alt-text-generator' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<p class="bbai-audit-score__disclaimer">
		<?php echo esc_html( \BeepBeepAI\AltTextGenerator\Audit\Image_Accessibility_Score_Service::disclaimer() ); ?>
	</p>

	<?php if ( $bbai_has_run && $bbai_total > $bbai_scanned ) : ?>
		<p class="bbai-audit-score__partial">
			<?php
			printf(
				/* translators: 1: images scanned, 2: images in the library. */
				esc_html__( 'This score covers %1$s of %2$s images. Run the audit again to cover the rest.', 'beepbeep-ai-alt-text-generator' ),
				esc_html( number_format_i18n( $bbai_scanned ) ),
				esc_html( number_format_i18n( $bbai_total ) )
			);
			?>
		</p>
	<?php endif; ?>
</section>
