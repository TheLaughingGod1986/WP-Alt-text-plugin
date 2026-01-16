<?php
/**
 * Dashboard tab wrapper. Delegates to modular partials and shared helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';

use BeepBeepAI\AltTextGenerator\Auth_State;
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;

// Resolve authentication/licensing once.
$auth = Auth_State::resolve($this->api_client);
$is_authenticated    = $auth['is_authenticated']    ?? false;
$has_license         = $auth['has_license']         ?? false;
$has_registered_user = $auth['has_registered_user'] ?? false;

// Ensure stats/usage are available for downstream partials.
$stats = (isset($stats) && is_array($stats)) ? $stats : $this->get_media_stats();
$usage_stats = Usage_Helper::get_usage($this->api_client, $has_registered_user);

// Partial paths.
$dashboard_auth_partial    = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-authenticated.php';
$dashboard_hero_partial    = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-hero.php';
$dashboard_demo_partial    = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-demo.php';
$dashboard_scripts_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-scripts.php';

// Render authenticated/licensed view or fallback demo.
if ($has_registered_user || $is_authenticated || $has_license) {
    if (file_exists($dashboard_auth_partial)) {
        include $dashboard_auth_partial;
    } else {
        esc_html_e('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
} else {
    if (file_exists($dashboard_hero_partial)) {
        include $dashboard_hero_partial;
    }
    if (file_exists($dashboard_demo_partial)) {
        include $dashboard_demo_partial;
    }
}

// Load dashboard scripts/styles.
if (file_exists($dashboard_scripts_partial)) {
    include $dashboard_scripts_partial;
}
