<?php
/**
 * Group of stat cards (compact row) — Library coverage metrics; composes stat-card partials.
 *
 * $bbai_ui keys:
 * - items: list of arrays passed to stat-card (value, label, root_class, value_tag, value_attrs, …)
 * - aria_label: for role="group"
 * - class: extra classes on wrapper (e.g. bbai-lib-core-card__stats)
 * - density: 'compact' | 'default' (default compact)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_items = isset($bbai_ui['items']) && is_array($bbai_ui['items']) ? $bbai_ui['items'] : [];
$bbai_aria = isset($bbai_ui['aria_label']) ? trim((string) $bbai_ui['aria_label']) : '';
$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_density = isset($bbai_ui['density']) && 'default' === (string) $bbai_ui['density'] ? 'default' : 'compact';
$bbai_group_class = 'compact' === $bbai_density ? 'bbai-ui-stat-group--compact' : 'bbai-ui-stat-group--default';
$bbai_root = trim('bbai-ui-stat-group ' . $bbai_group_class . ' ' . $bbai_extra);
$bbai_group_attr = '' !== $bbai_aria ? ' role="group" aria-label="' . esc_attr($bbai_aria) . '"' : '';
?>
<div class="<?php echo esc_attr(trim($bbai_root)); ?>"<?php echo $bbai_group_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php
    if (!function_exists('bbai_ui_render')) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
    }
    foreach ($bbai_items as $bbai_it) {
        if (!is_array($bbai_it)) {
            continue;
        }
        $bbai_card_args = array_merge(
            $bbai_it,
            [ 'density' => $bbai_density ]
        );
        bbai_ui_render('stat-card', $bbai_card_args);
    }
    ?>
</div>
