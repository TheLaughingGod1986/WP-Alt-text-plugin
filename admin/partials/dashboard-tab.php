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
$bbai_auth = Auth_State::resolve($this->api_client);
$bbai_is_authenticated    = $bbai_auth['is_authenticated']    ?? false;
$bbai_has_license         = $bbai_auth['has_license']         ?? false;
$bbai_has_registered_user = $bbai_auth['has_registered_user'] ?? false;

// Ensure stats/usage are available for downstream partials.
$bbai_stats = (isset($bbai_stats) && is_array($bbai_stats)) ? $bbai_stats : $this->get_media_stats();
$bbai_usage_stats = Usage_Helper::get_usage($this->api_client, $bbai_has_registered_user);

// Partial paths.
$bbai_dashboard_auth_partial       = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-authenticated.php';
$bbai_dashboard_logged_out_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-out.php';

// Render authenticated/licensed view or clean logged-out onboarding.
if ($bbai_has_registered_user || $bbai_is_authenticated || $bbai_has_license) {
    if (file_exists($bbai_dashboard_auth_partial)) {
        include $bbai_dashboard_auth_partial;
    } else {
        esc_html_e('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
} else {
    // Show clean onboarding screen for logged-out users
    // No usage counters, progress bars, or demo widgets
    if (file_exists($bbai_dashboard_logged_out_partial)) {
        include $bbai_dashboard_logged_out_partial;
    } else {
        esc_html_e('Please sign in to access the dashboard.', 'beepbeep-ai-alt-text-generator');
    }
}
