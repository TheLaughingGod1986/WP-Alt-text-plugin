<?php
/**
 * Severity / issue badge.
 *
 * $bbai_ui keys:
 * - code: issue code
 *
 * @package BeepBeep_AI
 * @since   4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeepAI\AltTextGenerator\Audit\Audit_Issue;

$bbai_ui   = isset( $bbai_ui ) && is_array( $bbai_ui ) ? $bbai_ui : array();
$bbai_code = isset( $bbai_ui['code'] ) ? (string) $bbai_ui['code'] : '';

$bbai_definition = Audit_Issue::definition( $bbai_code );

if ( null === $bbai_definition ) {
	return;
}
?>
<span
	class="bbai-audit-badge bbai-audit-badge--<?php echo esc_attr( $bbai_definition['severity'] ); ?>"
	title="<?php echo esc_attr( $bbai_definition['explanation'] ); ?>"
	data-code="<?php echo esc_attr( $bbai_code ); ?>"
>
	<?php echo esc_html( $bbai_definition['label'] ); ?>
</span>
