<?php
/**
 * Inline notice — single partial for Analytics guidance, Library credit alerts, etc.
 *
 * $bbai_ui keys:
 * - variant: success | warning | info | neutral
 * - message: plain text
 * - action_html: optional pre-escaped button or link
 * - role: optional ARIA role
 * - class: optional extra root classes
 * - layout: 'stack' (default) | 'inline' — inline = message + action in one row (Library usage alert)
 * - library_usage_alert: bool — adds data-bbai-library-usage-alert + legacy hooks for JS
 * - data_state: string when library_usage_alert (e.g. low | out)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_variant_in = isset($bbai_ui['variant']) ? (string) $bbai_ui['variant'] : 'neutral';
$bbai_variant = in_array($bbai_variant_in, ['success', 'warning', 'info', 'neutral'], true) ? $bbai_variant_in : 'neutral';
$bbai_message = (string) ($bbai_ui['message'] ?? '');
$bbai_action = (string) ($bbai_ui['action_html'] ?? '');
$bbai_role = trim((string) ($bbai_ui['role'] ?? ''));
$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_layout = isset($bbai_ui['layout']) && 'inline' === (string) $bbai_ui['layout'] ? 'inline' : 'stack';
$bbai_lib_alert = !empty($bbai_ui['library_usage_alert']);
$bbai_data_state = isset($bbai_ui['data_state']) ? sanitize_key((string) $bbai_ui['data_state']) : '';

$bbai_root = [ 'bbai-ui-notice-strip', 'bbai-ui-notice-strip--' . $bbai_variant ];
if ('inline' === $bbai_layout) {
    $bbai_root[] = 'bbai-ui-notice-strip--layout-inline';
}
if ($bbai_lib_alert) {
    $bbai_root[] = 'bbai-library-usage-alert';
    $bbai_root[] = 'bbai-library-usage-alert--inline';
    $bbai_root[] = 'bbai-library-usage-alert--unified';
    if ('' !== $bbai_data_state) {
        $bbai_root[] = 'bbai-library-usage-alert--' . $bbai_data_state;
    }
}
if ('' !== $bbai_extra) {
    $bbai_root[] = $bbai_extra;
}
$bbai_root_class = trim(implode(' ', array_filter(array_map('trim', $bbai_root))));

$bbai_role_attr = '' !== $bbai_role ? ' role="' . esc_attr($bbai_role) . '"' : '';
$bbai_lib_attr = $bbai_lib_alert ? ' data-bbai-library-usage-alert="1"' : '';
$bbai_state_attr = ($bbai_lib_alert && '' !== $bbai_data_state) ? ' data-state="' . esc_attr($bbai_data_state) . '"' : '';
?>
<div class="<?php echo esc_attr($bbai_root_class); ?>"<?php echo $bbai_role_attr . $bbai_lib_attr . $bbai_state_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php if ('inline' === $bbai_layout) : ?>
        <div class="bbai-ui-notice-strip__row">
            <span class="bbai-ui-notice-strip__text" data-bbai-library-usage-alert-line><?php echo esc_html($bbai_message); ?></span>
            <?php if ('' !== $bbai_action) : ?>
                <div class="bbai-ui-notice-strip__action"><?php echo $bbai_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <p class="bbai-ui-notice-strip__text"><?php echo esc_html($bbai_message); ?></p>
        <?php if ('' !== $bbai_action) : ?>
            <div class="bbai-ui-notice-strip__action"><?php echo $bbai_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
