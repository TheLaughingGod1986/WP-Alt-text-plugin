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
$nai_summary_tiles = array(
	array(
		'label'   => __( 'Images Scanned', 'beepbeep-ai-alt-text-generator' ),
		'value'   => number_format_i18n( $nai_total ),
		'tone'    => 'neutral',
		'numeric' => true,
	),
	array(
		'label'   => __( 'Missing Alt Text', 'beepbeep-ai-alt-text-generator' ),
		'value'   => number_format_i18n( $nai_missing ),
		'tone'    => $nai_missing > 0 ? 'danger' : 'ok',
		'numeric' => true,
	),
	array(
		'label'   => __( 'Weak Alt Text', 'beepbeep-ai-alt-text-generator' ),
		'value'   => number_format_i18n( $nai_weak ),
		'tone'    => $nai_weak > 0 ? 'warn' : 'ok',
		'numeric' => true,
	),
	array(
		'label'   => __( 'Duplicate Alt Text', 'beepbeep-ai-alt-text-generator' ),
		'value'   => number_format_i18n( $nai_duplicate ),
		'tone'    => $nai_duplicate > 0 ? 'warn' : 'ok',
		'numeric' => true,
	),
	array(
		'label'   => __( 'Excellent Images', 'beepbeep-ai-alt-text-generator' ),
		'value'   => number_format_i18n( $nai_optimized ),
		'tone'    => 'ok',
		'numeric' => true,
	),
	array(
		'label'   => __( 'Autopilot', 'beepbeep-ai-alt-text-generator' ),
		'value'   => $nai_is_pro ? __( 'Active', 'beepbeep-ai-alt-text-generator' ) : __( 'Inactive', 'beepbeep-ai-alt-text-generator' ),
		'tone'    => $nai_is_pro ? 'ok' : 'neutral',
		'numeric' => false,
	),
);
?>
	<?php // -------- Summary stat tiles -------- ?>
	<div class="nai-summary-tiles">
		<?php foreach ( $nai_summary_tiles as $nai_summary_tile ) : ?>
			<?php
			$nai_summary_tile_classes = array( 'nai-summary-tile__value', 'nai-summary-tile__value--' . $nai_summary_tile['tone'] );
			if ( ! empty( $nai_summary_tile['numeric'] ) ) {
				$nai_summary_tile_classes[] = 'nai-mono';
				$nai_summary_tile_classes[] = 'nai-tnum';
			} else {
				$nai_summary_tile_classes[] = 'nai-summary-tile__value--text';
			}
			?>
			<div class="nai-card">
				<div class="nai-summary-tile__inner">
					<div class="nai-summary-tile__label"><?php echo esc_html( $nai_summary_tile['label'] ); ?></div>
					<div class="<?php echo esc_attr( implode( ' ', $nai_summary_tile_classes ) ); ?>">
						<?php echo esc_html( $nai_summary_tile['value'] ); ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
