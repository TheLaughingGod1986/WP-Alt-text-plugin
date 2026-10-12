<?php
/**
 * Headline audit counts.
 *
 * $bbai_ui keys:
 * - stats: list of [ 'label', 'value', 'tone', 'help' ]
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bbai_ui    = isset( $bbai_ui ) && is_array( $bbai_ui ) ? $bbai_ui : array();
$bbai_stats = isset( $bbai_ui['stats'] ) && is_array( $bbai_ui['stats'] ) ? $bbai_ui['stats'] : array();

if ( empty( $bbai_stats ) ) {
	return;
}

$bbai_allowed_tones = array( 'neutral', 'success', 'warning', 'danger', 'info' );
?>
<ul class="bbai-audit-stats" role="list">
	<?php foreach ( $bbai_stats as $bbai_stat ) : ?>
		<?php
		$bbai_stat_tone = isset( $bbai_stat['tone'] ) ? (string) $bbai_stat['tone'] : 'neutral';
		$bbai_stat_tone = in_array( $bbai_stat_tone, $bbai_allowed_tones, true ) ? $bbai_stat_tone : 'neutral';
		$bbai_stat_help = isset( $bbai_stat['help'] ) ? trim( (string) $bbai_stat['help'] ) : '';
		?>
		<li class="bbai-audit-stats__item bbai-audit-stats__item--<?php echo esc_attr( $bbai_stat_tone ); ?>">
			<span class="bbai-audit-stats__value">
				<?php echo esc_html( number_format_i18n( (int) ( $bbai_stat['value'] ?? 0 ) ) ); ?>
			</span>
			<span class="bbai-audit-stats__label">
				<?php echo esc_html( (string) ( $bbai_stat['label'] ?? '' ) ); ?>
			</span>
			<?php if ( '' !== $bbai_stat_help ) : ?>
				<span class="bbai-audit-stats__help"><?php echo esc_html( $bbai_stat_help ); ?></span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
