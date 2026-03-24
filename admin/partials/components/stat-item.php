<?php
/**
 * Single stat metric (value + label) — shared primitive for stat-card and compact groups.
 *
 * $bbai_ui keys:
 * - value, label: strings
 * - value_tag: 'div' | 'strong' (default div)
 * - value_attrs: raw HTML attributes for value element (e.g. data-* for Library JS)
 * - root_class: optional wrapper class (default bbai-ui-stat-item)
 * - label_first: bool — label above value (Library compact); default false (Analytics order)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_val = (string) ($bbai_ui['value'] ?? '');
$bbai_lab = (string) ($bbai_ui['label'] ?? '');
$bbai_vtag = isset($bbai_ui['value_tag']) && 'strong' === (string) $bbai_ui['value_tag'] ? 'strong' : 'div';
$bbai_vattrs = trim((string) ($bbai_ui['value_attrs'] ?? ''));
$bbai_root = isset($bbai_ui['root_class']) ? trim((string) $bbai_ui['root_class']) : 'bbai-ui-stat-item';
$bbai_label_first = !empty($bbai_ui['label_first']);
?>
<div class="<?php echo esc_attr($bbai_root); ?>">
    <?php if ($bbai_label_first) : ?>
        <div class="bbai-stat-label bbai-ui-stat-card__label"><?php echo esc_html($bbai_lab); ?></div>
        <?php if ('strong' === $bbai_vtag) : ?>
            <strong class="bbai-stat-value bbai-ui-stat-card__value" <?php echo $bbai_vattrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($bbai_val); ?></strong>
        <?php else : ?>
            <div class="bbai-stat-value bbai-ui-stat-card__value"><?php echo esc_html($bbai_val); ?></div>
        <?php endif; ?>
    <?php else : ?>
        <?php if ('strong' === $bbai_vtag) : ?>
            <strong class="bbai-stat-value bbai-ui-stat-card__value" <?php echo $bbai_vattrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($bbai_val); ?></strong>
        <?php else : ?>
            <div class="bbai-stat-value bbai-ui-stat-card__value"><?php echo esc_html($bbai_val); ?></div>
        <?php endif; ?>
        <div class="bbai-stat-label bbai-ui-stat-card__label"><?php echo esc_html($bbai_lab); ?></div>
    <?php endif; ?>
</div>
