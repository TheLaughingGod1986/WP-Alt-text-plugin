<?php
/**
 * Large section container (surface card wrapper) — canonical section shell.
 *
 * $bbai_ui keys:
 * - phase: 'open' | 'close'
 * - tag: 'div' | 'section' (default div). Match close_tag on close.
 * - class: extra classes merged with surface tokens (see surface)
 * - aria_label: optional when tag is section
 * - close_tag: optional on phase close
 * - surface: 'dashboard' (default) adds bbai-ui-section-card bbai-dashboard-surface-card;
 *            'custom' uses only classes from `class` + bbai-ui-section-shell
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_phase = isset($bbai_ui['phase']) ? (string) $bbai_ui['phase'] : 'open';

if ('close' === $bbai_phase) {
    $bbai_close_tag = isset($bbai_ui['close_tag']) ? strtolower((string) $bbai_ui['close_tag']) : 'div';
    echo '</' . ('section' === $bbai_close_tag ? 'section' : 'div') . '>';
    return;
}

$bbai_tag = isset($bbai_ui['tag']) ? strtolower((string) $bbai_ui['tag']) : 'div';
$bbai_tag = 'section' === $bbai_tag ? 'section' : 'div';
$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_surface = isset($bbai_ui['surface']) ? strtolower((string) $bbai_ui['surface']) : 'dashboard';
if ('custom' === $bbai_surface) {
    $bbai_classes = trim('bbai-ui-section-shell ' . $bbai_extra);
} else {
    $bbai_classes = trim('bbai-ui-section-card bbai-dashboard-surface-card ' . $bbai_extra);
}
$bbai_aria = isset($bbai_ui['aria_label']) ? trim((string) $bbai_ui['aria_label']) : '';
$bbai_aria_out = '' !== $bbai_aria ? ' aria-label="' . esc_attr($bbai_aria) . '"' : '';
printf('<%s class="%s"%s>', esc_attr($bbai_tag), esc_attr($bbai_classes), $bbai_aria_out); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
