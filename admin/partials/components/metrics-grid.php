<?php
/**
 * Wrapper grid for stat cards.
 *
 * $bbai_ui keys:
 * - phase: 'open' | 'close'
 * - class: grid classes (default: bbai-analytics-metrics-grid bbai-card-grid)
 * - aria_label: optional accessible name for the group
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_phase = isset($bbai_ui['phase']) ? (string) $bbai_ui['phase'] : 'open';

if ('close' === $bbai_phase) {
    echo '</div>';
    return;
}

$bbai_class = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : 'bbai-analytics-metrics-grid bbai-card-grid';
$bbai_class = trim('bbai-ui-metrics-grid ' . $bbai_class);
$bbai_aria = isset($bbai_ui['aria_label']) ? trim((string) $bbai_ui['aria_label']) : '';
$bbai_aria_attr = '' !== $bbai_aria ? ' aria-label="' . esc_attr($bbai_aria) . '"' : '';
printf('<div class="%s"%s>', esc_attr($bbai_class), $bbai_aria_attr); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- aria_attr built with esc_attr
