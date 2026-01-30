<?php
/**
 * Dashboard content for authenticated/licensed users.
 */
if (!defined('ABSPATH')) { exit; }
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
?>
<div class="bbai-clean-dashboard" data-stats='<?php echo esc_attr(wp_json_encode($stats)); ?>'>
                <?php
                // Check for stored credentials (token or license) to determine if user is registered
                // This is more reliable than is_authenticated() which validates via API
                $stored_token = get_option('beepbeepai_jwt_token', '');
                $has_stored_token = !empty($stored_token);
                $stored_license = '';
                try {
                    $stored_license = $this->api_client->get_license_key();
                } catch (Exception $e) {
                    $stored_license = '';
                } catch (Error $e) {
                    $stored_license = '';
                }
                $has_stored_license = !empty($stored_license);
                $has_registered_user = ($has_registered_user ?? false) || $has_stored_token || $has_stored_license;
                
                // Reuse preloaded usage when present to avoid duplicate API calls.
                if (!isset($usage_stats) || !is_array($usage_stats)) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
                    $usage_stats = Usage_Helper::get_usage($this->api_client, $has_registered_user);
                }
                if (!class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                }
                $account_summary = $this->api_client->is_authenticated() ? $this->get_account_summary($usage_stats) : null;
                
                // Get raw values directly from the stats array - same calculation method as Settings tab
                $dashboard_used = max(0, intval($usage_stats['used'] ?? 0));
                $dashboard_limit = max(1, intval($usage_stats['limit'] ?? 50));
                $dashboard_remaining = max(0, intval($usage_stats['remaining'] ?? 50));
                
                // Recalculate remaining to ensure accuracy
                $dashboard_remaining = max(0, $dashboard_limit - $dashboard_used);
                
                // Cap used at limit to prevent showing > 100%
                if ($dashboard_used > $dashboard_limit) {
                    $dashboard_used = $dashboard_limit;
                    $dashboard_remaining = 0;
                }
                
                // Calculate percentage - same way as Settings tab
                $percentage = $dashboard_limit > 0 ? (($dashboard_used / $dashboard_limit) * 100) : 0;
                $percentage = min(100, max(0, $percentage));
                
                // If at limit, ensure it shows 100%
                if ($dashboard_used >= $dashboard_limit && $dashboard_remaining <= 0) {
                    $percentage = 100;
                }
                
                // Update the stats with calculated values for display
                $usage_stats['used'] = $dashboard_used;
                $usage_stats['limit'] = $dashboard_limit;
                $usage_stats['remaining'] = $dashboard_remaining;
                $usage_stats['percentage'] = $percentage;
                $usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label($percentage);
                ?>
                
                <?php
$dashboard_auth_content = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-auth-content.php';
if (file_exists($dashboard_auth_content)) {
    include $dashboard_auth_content;
} else {
    esc_html_e('Dashboard content unavailable.', 'opptiai-alt');
}
?>
