<?php
/**
 * Two-column workspace: outer grid + optional main column wrapper.
 *
 * $bbai_ui keys:
 * - phase: 'open' | 'main_open' | 'main_close' | 'close'
 * - class: extra classes on outer grid (e.g. bbai-analytics-workspace)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_phase = isset($bbai_ui['phase']) ? (string) $bbai_ui['phase'] : 'open';

if ('main_close' === $bbai_phase) {
    echo '</div>';
    return;
}

if ('close' === $bbai_phase) {
    echo '</div>';
    return;
}

if ('main_open' === $bbai_phase) {
    echo '<div class="bbai-main-content">';
    return;
}

$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_grid_class = trim('bbai-workspace-grid bbai-card-grid bbai-ui-workspace-grid ' . $bbai_extra);
printf('<div class="%s">', esc_attr($bbai_grid_class));
