<?php
/**
 * Shared admin UI component renderer (PHP partials under admin/partials/components/).
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a UI component partial. Pass options as the second argument; inside the partial they are available as `$bbai_ui`.
 *
 * @param string $component File name without .php (e.g. 'stat-card', 'sidebar-card').
 * @param array  $args      Component arguments.
 */
function bbai_ui_render(string $component, array $args = []): void
{
    $file = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/' . $component . '.php';
    if (!is_readable($file)) {
        return;
    }
    // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Local scope for partial templates only.
    extract(['bbai_ui' => $args], EXTR_SKIP);
    include $file;
}
