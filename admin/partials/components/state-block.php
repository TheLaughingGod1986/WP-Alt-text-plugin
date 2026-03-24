<?php
/**
 * Empty / success / warning state panel.
 *
 * $bbai_ui keys:
 * - variant: success | warning | info | neutral
 * - icon_html: optional decorative markup (caller marks aria-hidden on icon root)
 * - title, description: plain text
 * - actions_html: pre-escaped buttons/links
 * - class: optional root classes
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_variant_in = isset($bbai_ui['variant']) ? (string) $bbai_ui['variant'] : 'neutral';
$bbai_variant = in_array($bbai_variant_in, ['success', 'warning', 'info', 'neutral'], true) ? $bbai_variant_in : 'neutral';
$bbai_icon = (string) ($bbai_ui['icon_html'] ?? '');
$bbai_title = (string) ($bbai_ui['title'] ?? '');
$bbai_desc = (string) ($bbai_ui['description'] ?? '');
$bbai_actions = (string) ($bbai_ui['actions_html'] ?? '');
$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_root = trim('bbai-ui-state-block bbai-ui-state-block--' . $bbai_variant . ' ' . $bbai_extra);
?>
<div class="<?php echo esc_attr(trim($bbai_root)); ?>">
    <?php if ('' !== $bbai_icon) : ?>
        <div class="bbai-ui-state-block__icon"><?php echo $bbai_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>
    <div class="bbai-ui-state-block__body">
        <?php if ('' !== $bbai_title) : ?>
            <p class="bbai-ui-state-block__title"><?php echo esc_html($bbai_title); ?></p>
        <?php endif; ?>
        <?php if ('' !== $bbai_desc) : ?>
            <p class="bbai-ui-state-block__description"><?php echo esc_html($bbai_desc); ?></p>
        <?php endif; ?>
    </div>
    <?php if ('' !== $bbai_actions) : ?>
        <div class="bbai-ui-state-block__actions"><?php echo $bbai_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>
</div>
