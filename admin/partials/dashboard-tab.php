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
$dashboard_auth_partial       = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-authenticated.php';
$dashboard_logged_out_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-out.php';

// Render authenticated/licensed view or clean logged-out onboarding.
if ($has_registered_user || $is_authenticated || $has_license) {
    if (file_exists($dashboard_auth_partial)) {
        include $dashboard_auth_partial;
    } else {
        esc_html_e('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
} else {
    // Show clean onboarding screen for logged-out users
    // No usage counters, progress bars, or demo widgets
    if (file_exists($dashboard_logged_out_partial)) {
        include $dashboard_logged_out_partial;
    } else {
        esc_html_e('Please sign in to access the dashboard.', 'beepbeep-ai-alt-text-generator');
    }
}
