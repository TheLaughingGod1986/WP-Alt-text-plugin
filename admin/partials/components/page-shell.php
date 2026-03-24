<?php
/**
 * Page shell: open/close wrapper for consistent outer spacing and max-width behavior.
 *
 * $bbai_ui keys:
 * - phase: 'open' | 'close' (default open)
 * - class: extra classes on the root when opening
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

$bbai_extra = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : '';
$bbai_root = trim('bbai-ui-page-shell bbai-container ' . $bbai_extra);
printf('<div class="%s">', esc_attr($bbai_root));
