<?php
/**
 * Credit Usage tab content partial.
 *
 * Expects auth checks already performed.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-credit-usage-page.php';
\BeepBeepAI\AltTextGenerator\Credit_Usage_Page::render_page_content();
