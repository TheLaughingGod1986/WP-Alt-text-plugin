<?php
/**
 * Legacy entrypoint — delegates to the canonical bbai-banner shell (status-banner.php).
 *
 * Expects `$bbai_inline_banner` (array). Prefer:
 * `bbai_ui_render( 'bbai-banner', [ 'command_hero' => bbai_banner_inline_payload_to_command_hero( $cfg ) ] );`
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ib = isset($bbai_inline_banner) && is_array($bbai_inline_banner) ? $bbai_inline_banner : [];
if ('' === trim((string) ($bbai_ib['title'] ?? ''))) {
    return;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

$bbai_ui = [
    'command_hero' => bbai_banner_inline_payload_to_command_hero($bbai_ib),
];

require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/status-banner.php';
