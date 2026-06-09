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
	<?php // -------- Library coverage -------- ?>
	<?php if ( $nai_total > 0 ) : ?>
		<div class="nai-card nai-coverage">
			<div class="nai-coverage__inner">
				<div class="nai-coverage__row">
					<div class="nai-coverage__head">
						<span class="nai-eyebrow"><?php esc_html_e( 'Library coverage', 'beepbeep-ai-alt-text-generator' ); ?></span>
						<span class="nai-mono nai-tnum nai-coverage__num" data-nai-coverage-number><?php echo esc_html( (string) $nai_coverage_pct_real ); ?>%</span>
						<span class="nai-coverage__sub"><?php esc_html_e( 'optimised', 'beepbeep-ai-alt-text-generator' ); ?></span>
					</div>
					<div class="nai-tnum nai-coverage__stats">
						<span class="nai-mono nai-coverage__stats-strong" data-nai-coverage-optimized><?php echo esc_html( number_format_i18n( $nai_optimized ) ); ?></span>
						<?php esc_html_e( 'improved', 'beepbeep-ai-alt-text-generator' ); ?>
						<span> · </span>
						<span class="nai-mono nai-coverage__stats-strong" data-nai-coverage-remaining><?php echo esc_html( number_format_i18n( $nai_remaining_total ) ); ?></span>
						<?php esc_html_e( 'remaining', 'beepbeep-ai-alt-text-generator' ); ?>
					</div>
				</div>
				<div class="nai-progress <?php echo esc_attr( $nai_coverage_tone_cls ); ?>">
					<div class="nai-progress__bar" data-nai-coverage-progress style="width:<?php echo esc_attr( (string) $nai_coverage_pct_real ); ?>%;"></div>
				</div>
			</div>
		</div>
	<?php endif; ?>
