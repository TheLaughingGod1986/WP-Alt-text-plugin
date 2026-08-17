<?php
/**
 * Dashboard tab entry. Loads dashboard-authenticated.php → dashboard-body.php.
 *
 * Routing (see dashboard-body.php):
 * - No connected SaaS account: guest dashboard-hero (funnel) + exhausted-only library overlay partials.
 * - Connected account: logged-in dashboard (dashboard-logged-in-page), not the guest hero partial.
 * - BBAI_FORCE_CLEAN_LOGGED_OUT: legacy FTUE in dashboard-logged-out.php (bypasses body).
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

// Ensure stats/usage are available for downstream partials without blocking first paint.
// The live dashboard/state-truth refresh can reconcile after load; PHP render should use
// cached media/coverage snapshots only.
if (!isset($bbai_stats) || !is_array($bbai_stats)) {
    $bbai_cached_stats = get_transient('bbai_stats_v3');
    $bbai_cached_coverage = get_transient('bbai_alt_coverage_scan_v4');
    $bbai_stats = is_array($bbai_cached_stats) ? $bbai_cached_stats : [];
    if (is_array($bbai_cached_coverage)) {
        $bbai_stats['total'] = max(0, (int) ($bbai_cached_coverage['total_images'] ?? ($bbai_stats['total'] ?? 0)));
        $bbai_stats['with_alt'] = max(0, (int) ($bbai_cached_coverage['images_with_alt'] ?? ($bbai_stats['with_alt'] ?? 0)));
        $bbai_stats['missing'] = max(0, (int) ($bbai_cached_coverage['images_missing_alt'] ?? ($bbai_stats['missing'] ?? 0)));
        $bbai_stats['total_images'] = max(0, (int) ($bbai_cached_coverage['total_images'] ?? ($bbai_stats['total_images'] ?? 0)));
        $bbai_stats['images_with_alt'] = max(0, (int) ($bbai_cached_coverage['images_with_alt'] ?? ($bbai_stats['images_with_alt'] ?? 0)));
        $bbai_stats['images_missing_alt'] = max(0, (int) ($bbai_cached_coverage['images_missing_alt'] ?? ($bbai_stats['images_missing_alt'] ?? 0)));
        $bbai_stats['coverage_percent'] = max(0, min(100, (int) ($bbai_cached_coverage['coverage_percent'] ?? 0)));
        $bbai_stats['needs_review_count'] = max(0, (int) ($bbai_cached_coverage['needs_review_count'] ?? 0));
        $bbai_stats['optimized_count'] = max(0, (int) ($bbai_cached_coverage['optimized_count'] ?? 0));
    }
}
$bbai_usage_stats = (bool) $bbai_has_connected_account
    ? Usage_Tracker::get_local_usage_snapshot()
    : Usage_Helper::get_usage($this->api_client, false);

// Partial paths.
$bbai_dashboard_auth_partial       = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-authenticated.php';
$bbai_dashboard_logged_out_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-logged-out.php';
$bbai_is_guest_trial_user = (bool) $bbai_is_anonymous_trial;

// Dashboard-first flow: all visitors land in the shared dashboard shell.
// dashboard-body.php resolves connected / anonymous-trial / guest UI internally.
if ( file_exists( $bbai_dashboard_auth_partial ) ) {
    include $bbai_dashboard_auth_partial;
} else {
    esc_html_e( 'Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator' );
}
