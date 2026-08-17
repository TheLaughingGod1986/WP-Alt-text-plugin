<?php
/**
 * Horizontal or stacked action group (banner / card foot).
 *
 * $bbai_ui keys:
 * - layout: horizontal | vertical | stack (default horizontal)
 * - html: pre-escaped actions markup
 * - class: optional extra root classes
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_layout_in = isset($bbai_ui['layout']) ? (string) $bbai_ui['layout'] : 'horizontal';
$bbai_layout = in_array($bbai_layout_in, ['horizontal', 'vertical', 'stack'], true) ? $bbai_layout_in : 'horizontal';
$bbai_html = (string) ($bbai_ui['html'] ?? '');
$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_root = trim('bbai-ui-button-group bbai-ui-button-group--' . $bbai_layout . ' ' . $bbai_extra);
?>
<div class="<?php echo esc_attr(trim($bbai_root)); ?>">
    <?php echo $bbai_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
