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
$bbai_has_connected_account = $bbai_auth['has_connected_account'] ?? false;
$bbai_is_anonymous_trial = $bbai_auth['is_anonymous_trial'] ?? false;

// Ensure stats/usage are available for downstream partials.
$bbai_stats = (isset($bbai_stats) && is_array($bbai_stats))
    ? $bbai_stats
    : (method_exists($this, 'get_dashboard_stats_payload')
        ? $this->get_dashboard_stats_payload(false)
        : $this->get_media_stats());
$bbai_usage_stats = Usage_Helper::get_usage($this->api_client, (bool) $bbai_has_connected_account);

// Partial paths.
$bbai_dashboard_auth_partial       = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-authenticated.php';
$bbai_dashboard_logged_out_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-out.php';
$bbai_is_guest_trial_user = (bool) $bbai_is_anonymous_trial;

// Route: connected/licensed users get the full dashboard; anonymous trial users get the FTUE panel.
if ( $bbai_has_connected_account || $bbai_is_authenticated || $bbai_has_license ) {
    if ( file_exists( $bbai_dashboard_auth_partial ) ) {
        include $bbai_dashboard_auth_partial;
    } else {
        esc_html_e( 'Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator' );
    }
} else {
    if ( file_exists( $bbai_dashboard_logged_out_partial ) ) {
        include $bbai_dashboard_logged_out_partial;
    } else {
        esc_html_e( 'Please connect your BeepBeep AI account to access the dashboard.', 'beepbeep-ai-alt-text-generator' );
    }
}
