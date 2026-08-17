<?php
/**
 * Shared product state surface: loading, empty, error, partial success, retry.
 *
 * $bbai_ui keys:
 * - variant: loading|empty|error|partial|retry (required)
 * - title: string
 * - body: string (supporting line)
 * - meta: string (optional fine print)
 * - icon_html: pre-escaped SVG or HTML for icon slot
 * - show_spinner: bool — use built-in .bbai-ix-spinner (loading)
 * - actions_html: pre-escaped buttons/links markup
 * - compact: bool — tighter padding, table/inline
 * - section: bool — full-width section style
 * - role: default status
 * - aria_live: polite|assertive|off
 * - root_class: extra classes on root
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_variant = isset($bbai_ui['variant']) ? preg_replace('/[^a-z-]/', '', (string) $bbai_ui['variant']) : 'empty';
if ('' === $bbai_variant) {
    $bbai_variant = 'empty';
}

$bbai_title = isset($bbai_ui['title']) ? (string) $bbai_ui['title'] : '';
$bbai_body = isset($bbai_ui['body']) ? (string) $bbai_ui['body'] : '';
$bbai_meta = isset($bbai_ui['meta']) ? (string) $bbai_ui['meta'] : '';
$bbai_icon_html = isset($bbai_ui['icon_html']) ? (string) $bbai_ui['icon_html'] : '';
$bbai_actions = isset($bbai_ui['actions_html']) ? (string) $bbai_ui['actions_html'] : '';
$bbai_compact = !empty($bbai_ui['compact']);
$bbai_section = !empty($bbai_ui['section']);
$bbai_show_spinner = !empty($bbai_ui['show_spinner']);
$bbai_role = isset($bbai_ui['role']) ? sanitize_key((string) $bbai_ui['role']) : 'status';
$bbai_aria_live = isset($bbai_ui['aria_live']) ? sanitize_key((string) $bbai_ui['aria_live']) : 'polite';
$bbai_root_extra = isset($bbai_ui['root_class']) ? trim((string) $bbai_ui['root_class']) : '';

$bbai_root = ['bbai-state', 'bbai-state--' . $bbai_variant];
if ($bbai_compact) {
    $bbai_root[] = 'bbai-state--compact';
}
if ($bbai_section) {
    $bbai_root[] = 'bbai-state--section';
}
if ('' !== $bbai_root_extra) {
    $bbai_root[] = $bbai_root_extra;
}
$bbai_root_class = implode(' ', array_filter($bbai_root));
$bbai_aria_live_attr = in_array($bbai_aria_live, ['polite', 'assertive', 'off'], true) ? $bbai_aria_live : 'polite';
?>
<div
    class="<?php echo esc_attr($bbai_root_class); ?>"
    role="<?php echo esc_attr($bbai_role); ?>"
    <?php if ('off' !== $bbai_aria_live_attr) : ?>
        aria-live="<?php echo esc_attr($bbai_aria_live_attr); ?>"
    <?php endif; ?>
>
    <?php if ($bbai_show_spinner) : ?>
        <div class="bbai-state__icon bbai-state__icon--loading" aria-hidden="true">
            <span class="bbai-ix-spinner bbai-ix-spinner--sm"></span>
        </div>
    <?php elseif ('' !== $bbai_icon_html) : ?>
        <div class="bbai-state__icon" aria-hidden="true"><?php echo $bbai_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>

    <?php if ('' !== $bbai_title) : ?>
        <h3 class="bbai-state__title"><?php echo esc_html($bbai_title); ?></h3>
    <?php endif; ?>

    <?php if ('' !== $bbai_body) : ?>
        <p class="bbai-state__body"><?php echo esc_html($bbai_body); ?></p>
    <?php endif; ?>

    <?php if ('' !== $bbai_meta) : ?>
        <p class="bbai-state__meta"><?php echo esc_html($bbai_meta); ?></p>
    <?php endif; ?>

    <?php if ('' !== $bbai_actions) : ?>
        <div class="bbai-state__actions"><?php echo $bbai_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>
</div>
