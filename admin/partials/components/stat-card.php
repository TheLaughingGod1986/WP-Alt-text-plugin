<?php
/**
 * Metric / stat summary card — surface shell around shared stat-item primitive.
 *
 * $bbai_ui keys:
 * - value, label: strings (formatted value)
 * - root_class: optional extra classes on root (e.g. bbai-analytics-metric-card--warn)
 * - density: 'default' | 'compact' (Library stat group uses compact)
 * - value_tag, value_attrs: passed through to stat-item
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_value = isset($bbai_ui['value']) ? (string) $bbai_ui['value'] : '';
$bbai_label = isset($bbai_ui['label']) ? (string) $bbai_ui['label'] : '';
$bbai_root_extra = isset($bbai_ui['root_class']) ? trim((string) $bbai_ui['root_class']) : '';
$bbai_density = isset($bbai_ui['density']) && 'compact' === (string) $bbai_ui['density'] ? 'compact' : 'default';
$bbai_density_class = 'compact' === $bbai_density ? ' bbai-ui-stat-card--compact bbai-card--compact' : '';
$bbai_root = trim('bbai-ui-stat-card bbai-stat-card bbai-analytics-metric-card bbai-dashboard-surface-card bbai-card' . $bbai_density_class . ' ' . $bbai_root_extra);
$bbai_vtag = isset($bbai_ui['value_tag']) ? (string) $bbai_ui['value_tag'] : 'div';
$bbai_vattrs = (string) ($bbai_ui['value_attrs'] ?? '');
$bbai_label_first = !empty($bbai_ui['label_first']);
?>
<div class="<?php echo esc_attr(trim($bbai_root)); ?>">
    <?php
    if (!function_exists('bbai_ui_render')) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
    }
    bbai_ui_render(
        'stat-item',
        [
            'value'        => $bbai_value,
            'label'        => $bbai_label,
            'value_tag'    => $bbai_vtag,
            'value_attrs'  => $bbai_vattrs,
            'label_first'  => $bbai_label_first,
            'root_class'   => 'bbai-ui-stat-item bbai-ui-stat-item--in-card',
        ]
    );
    ?>
</div>
