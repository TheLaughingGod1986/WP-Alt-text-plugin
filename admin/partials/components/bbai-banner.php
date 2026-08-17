<?php
/**
 * Canonical admin banner shell (bbai-banner). Alias entrypoint for bbai_ui_render('bbai-banner', …).
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

require __DIR__ . '/status-banner.php';
