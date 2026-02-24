<?php
/**
 * Dashboard content for authenticated/licensed users.
 */
if (!defined('ABSPATH')) { exit; }
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
?>
<div class="bbai-clean-dashboard" data-stats='<?php echo esc_attr(wp_json_encode($bbai_stats)); ?>'>
                <?php
                // Check for stored credentials (token or license) to determine if user is registered
                // This is more reliable than is_authenticated() which validates via API
                $bbai_stored_token = get_option('beepbeepai_jwt_token', '');
                $bbai_has_stored_token = !empty($bbai_stored_token);
                $bbai_stored_license = '';
                try {
                    $bbai_stored_license = $this->api_client->get_license_key();
                } catch (Exception $e) {
                    $bbai_stored_license = '';
                } catch (Error $e) {
                    $bbai_stored_license = '';
                }
                $bbai_has_stored_license = !empty($bbai_stored_license);
                $bbai_has_registered_user = ($bbai_has_registered_user ?? false) || $bbai_has_stored_token || $bbai_has_stored_license;
                
                // Reuse preloaded usage when present to avoid duplicate API calls.
                if (!isset($bbai_usage_stats) || !is_array($bbai_usage_stats)) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
                    $bbai_usage_stats = Usage_Helper::get_usage($this->api_client, $bbai_has_registered_user);
                }
                if (!class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                }
                $bbai_account_summary = $this->api_client->is_authenticated() ? $this->get_account_summary($bbai_usage_stats) : null;
                
                // Get raw values directly from the stats array - same calculation method as Settings tab
                $bbai_dashboard_used = max(0, intval($bbai_usage_stats['used'] ?? 0));
                $bbai_dashboard_limit = max(1, intval($bbai_usage_stats['limit'] ?? 50));
                $bbai_dashboard_remaining = max(0, intval($bbai_usage_stats['remaining'] ?? 50));
                
                // Recalculate remaining to ensure accuracy
                $bbai_dashboard_remaining = max(0, $bbai_dashboard_limit - $bbai_dashboard_used);
                
                // Cap used at limit to prevent showing > 100%
                if ($bbai_dashboard_used > $bbai_dashboard_limit) {
                    $bbai_dashboard_used = $bbai_dashboard_limit;
                    $bbai_dashboard_remaining = 0;
                }
                
                // Calculate percentage - same way as Settings tab
                $bbai_percentage = $bbai_dashboard_limit > 0 ? (($bbai_dashboard_used / $bbai_dashboard_limit) * 100) : 0;
                $bbai_percentage = min(100, max(0, $bbai_percentage));
                
                // If at limit, ensure it shows 100%
                if ($bbai_dashboard_used >= $bbai_dashboard_limit && $bbai_dashboard_remaining <= 0) {
                    $bbai_percentage = 100;
                }
                
                // Update the stats with calculated values for display
                $bbai_usage_stats['used'] = $bbai_dashboard_used;
                $bbai_usage_stats['limit'] = $bbai_dashboard_limit;
                $bbai_usage_stats['remaining'] = $bbai_dashboard_remaining;
                $bbai_usage_stats['percentage'] = $bbai_percentage;
                $bbai_usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label($bbai_percentage);
                ?>
                
                <?php
$bbai_dashboard_auth_content = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-auth-content.php';
if (file_exists($bbai_dashboard_auth_content)) {
    include $bbai_dashboard_auth_content;
} else {
    esc_html_e('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator');
}
?>
