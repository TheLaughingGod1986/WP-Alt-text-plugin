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
                $bbai_has_connected_account = (bool) ($bbai_has_connected_account ?? $bbai_has_registered_user ?? false);
                $bbai_is_authenticated = (bool) ($bbai_is_authenticated ?? false);
                
                // Reuse preloaded usage when present to avoid duplicate API calls.
                if (!isset($bbai_usage_stats) || !is_array($bbai_usage_stats)) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
                    $bbai_usage_stats = Usage_Helper::get_usage($this->api_client, $bbai_has_connected_account);
                }
                if (!class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                }
                $bbai_account_summary = $bbai_is_authenticated ? $this->get_account_summary($bbai_usage_stats) : null;
                
                // Get raw values directly from the stats array - same calculation method as Settings tab
                $bbai_dashboard_used = max(0, intval($bbai_usage_stats['used'] ?? 0));
                $bbai_dashboard_limit = max(1, intval($bbai_usage_stats['limit'] ?? 50));
                $bbai_dashboard_remaining = max(0, intval($bbai_usage_stats['remaining'] ?? 0));
                
                // Calculate percentage - same way as Settings tab
                $bbai_percentage_used = min($bbai_dashboard_used, $bbai_dashboard_limit);
                $bbai_percentage = $bbai_dashboard_limit > 0 ? (($bbai_percentage_used / $bbai_dashboard_limit) * 100) : 0;
                $bbai_percentage = min(100, max(0, $bbai_percentage));
                
                // If at limit, ensure it shows 100%
                if ($bbai_percentage_used >= $bbai_dashboard_limit && $bbai_dashboard_remaining <= 0) {
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
