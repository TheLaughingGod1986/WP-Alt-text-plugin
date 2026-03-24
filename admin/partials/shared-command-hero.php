<?php
/**
 * Back-compat shim — canonical banner markup lives in admin/partials/components/status-banner.php.
 *
 * Expects `$bbai_command_hero` in scope (array). Prefer:
 * `bbai_ui_render( 'status-banner', [ 'command_hero' => $bbai_command_hero ] );`
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = [
    'command_hero' => isset($bbai_command_hero) && is_array($bbai_command_hero) ? $bbai_command_hero : [],
];

require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/status-banner.php';
