<?php
/**
 * Usage Tracker for AltText AI
 * Caches usage data locally and handles upgrade prompts
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

class Usage_Tracker {
    
    const CACHE_KEY = 'bbai_usage_cache';
    const CACHE_EXPIRY = 300; // 5 minutes
    
    /**
     * Allocate free credits on first generation request.
     * This ensures free credits are only granted once per site.
     *
     * @return bool True if credits were allocated, false if already allocated.
     */
    public static function allocate_free_credits_if_needed() {
        $free_credits_allocated = get_option('beepbeepai_free_credits_allocated', false);
        
        if ($free_credits_allocated) {
            // Already allocated
            return false;
        }
        
        // Mark as allocated (one-time per site)
        update_option('beepbeepai_free_credits_allocated', true, false);
        
        // Update usage cache with free credits
        $reset_ts = strtotime('first day of next month');
        $usage_data = [
            'used' => 0,
            'limit' => 50,
            'remaining' => 50,
            'plan' => 'free',
            'resetDate' => wp_date('Y-m-01', $reset_ts),
            'resetTimestamp' => $reset_ts,
        ];
        self::update_usage($usage_data);
        
        return true;
    }

    /**
     * Update cached usage data
     */
    public static function update_usage($usage_data) {
        if (!is_array($usage_data)) { return; }
        $used  = isset($usage_data['used']) ? max(0, intval($usage_data['used'])) : 0;
        $limit = isset($usage_data['limit']) ? intval($usage_data['limit']) : 50;
        if ($limit <= 0) { $limit = 50; }
        $remaining = isset($usage_data['remaining']) ? intval($usage_data['remaining']) : ($limit - $used);
        if ($remaining < 0) { $remaining = 0; }

        $current_ts = time();
        $reset_raw = $usage_data['resetDate'] ?? '';
        $reset_ts = isset($usage_data['resetTimestamp']) ? intval($usage_data['resetTimestamp']) : 0;
        if ($reset_ts <= 0 && $reset_raw) {
            $reset_ts = strtotime($reset_raw);
        }
        if ($reset_ts <= 0) {
            $reset_ts = strtotime('first day of next month', $current_ts);
        }
        $seconds_until_reset = max(0, $reset_ts - $current_ts);

        $normalized = [
            'used'       => $used,
            'limit'      => $limit,
            'remaining'  => $remaining,
            'plan'       => $usage_data['plan'] ?? 'free',
            'resetDate'  => wp_date('Y-m-d', $reset_ts),
            'reset_timestamp' => $reset_ts,
            'seconds_until_reset' => $seconds_until_reset,
        ];
        set_transient(self::CACHE_KEY, $normalized, self::CACHE_EXPIRY);
    }
    
    /**
     * Get cached usage data
     */
    public static function get_cached_usage($force_refresh = false) {
        // PRIORITY 1: Check for active license first - license overrides personal account
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
        $api_client = new API_Client_V2();

        if ($api_client->has_active_license()) {
            $license_data = $api_client->get_license_data();
            if ($license_data && isset($license_data['organization'])) {
                $org = $license_data['organization'];

                // Parse reset date
                $reset_ts = strtotime('first day of next month');
                if (!empty($org['resetDate'])) {
                    $parsed = strtotime($org['resetDate']);
                    if ($parsed > 0) {
                        $reset_ts = $parsed;
                    }
                }
                $current_ts = time();

                // Get plan from organization data (correct location)
                $plan = isset($org['plan']) ? strtolower($org['plan']) : 'free';

                // Get limit from organization data or calculate based on plan
                $limit = isset($org['tokenLimit']) ? intval($org['tokenLimit']) :
                         ($plan === 'free' ? 50 : ($plan === 'pro' ? 1000 : 10000));

                $tokens_remaining = isset($org['tokensRemaining']) ? max(0, intval($org['tokensRemaining'])) : $limit;
                $used = max(0, $limit - $tokens_remaining);

                // Return organization quota instead of personal account
                return [
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => $tokens_remaining,
                    'plan' => $plan,
                    'resetDate' => wp_date('Y-m-d', $reset_ts),
                    'reset_timestamp' => $reset_ts,
                    'seconds_until_reset' => max(0, $reset_ts - $current_ts),
                ];
            }
        }

        // PRIORITY 2: If no license, fall back to personal account data
        // If force refresh, clear cache first
        if ($force_refresh) {
            delete_transient(self::CACHE_KEY);
        }
        
        $cached = get_transient(self::CACHE_KEY);
        
        if ($cached === false) {
            // Check if free credits have been allocated for this site
            $free_credits_allocated = get_option('beepbeepai_free_credits_allocated', false);
            
            // Default values if no cache exists
            $reset_ts = strtotime('first day of next month');
            
            // Only show free credits if they've been allocated (first generation request)
            // This prevents showing 50 credits before first use
            if ($free_credits_allocated) {
                return [
                    'used' => 0,
                    'limit' => 50,
                    'remaining' => 50,
                    'plan' => 'free',
                    'resetDate' => wp_date('Y-m-01', $reset_ts),
                    'reset_timestamp' => $reset_ts,
                    'seconds_until_reset' => max(0, $reset_ts - time()),
                ];
            } else {
                // Free credits not yet allocated - show as unavailable
                return [
                    'used' => 0,
                    'limit' => 0,
                    'remaining' => 0,
                    'plan' => 'free',
                    'resetDate' => wp_date('Y-m-01', $reset_ts),
                    'reset_timestamp' => $reset_ts,
                    'seconds_until_reset' => max(0, $reset_ts - time()),
                ];
            }
        }
        
        return $cached;
    }
    
    /**
     * Clear cached usage
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }
    
    /**
     * Check if user should see upgrade prompt
     */
    public static function should_show_upgrade_prompt() {
        $usage = self::get_cached_usage();
        $percentage = ($usage['used'] / max($usage['limit'], 1)) * 100;
        
        // Show at 80% usage
        return $percentage >= 80;
    }
    
    /**
     * Check if user is at limit
     */
    public static function is_at_limit() {
        $usage = self::get_cached_usage();
        return $usage['remaining'] <= 0;
    }
    
    /**
     * Get usage stats for display
     */
    public static function get_stats_display($force_refresh = false) {
        $usage = self::get_cached_usage($force_refresh);
        $limit = max(1, intval($usage['limit']));
        $used = max(0, intval($usage['used']));
        if ($used > $limit) { $used = $limit; }
        $remaining = max(0, $limit - $used);
        $percentage_exact = $limit > 0 ? ($used / $limit) * 100 : 0;
        $percentage_exact = min(100, max(0, $percentage_exact));
        
        // Calculate days until reset
        $reset_timestamp = isset($usage['reset_timestamp']) ? intval($usage['reset_timestamp']) : 0;
        $current_timestamp = time();

        if ($reset_timestamp <= 0 && !empty($usage['resetDate'])) {
            // Try parsing the reset date - handle both Y-m-d and other formats
            $reset_date_str = $usage['resetDate'];
            $parsed_timestamp = strtotime($reset_date_str);

            // Validate the parsed timestamp - it should be in the future and not more than 2 months away
            $max_future = strtotime('+2 months', $current_timestamp);
            if ($parsed_timestamp > 0 && $parsed_timestamp > $current_timestamp && $parsed_timestamp <= $max_future) {
                $reset_timestamp = $parsed_timestamp;
            } else {
                // Invalid date, use first of next month
                $reset_timestamp = strtotime('first day of next month', $current_timestamp);
            }
        }

        // Fallback to next month if no reset date is set or invalid
        if ($reset_timestamp <= 0 || $reset_timestamp <= $current_timestamp) {
            $reset_timestamp = strtotime('first day of next month', $current_timestamp);
        }

        // Ensure reset timestamp is at midnight for consistency
        $reset_timestamp = strtotime(wp_date('Y-m-d 00:00:00', $reset_timestamp));

        $seconds_until_reset = max(0, $reset_timestamp - $current_timestamp);
        $days_until_reset = (int) floor($seconds_until_reset / DAY_IN_SECONDS);

        // Get plan with fallback
        $plan = isset($usage['plan']) && !empty($usage['plan']) ? $usage['plan'] : 'free';

        // Get reset date with fallback - format: "February 1, 2026"
        $reset_date_display = $reset_timestamp ? date_i18n('F j, Y', $reset_timestamp) : '';
        if (empty($reset_date_display)) {
            $reset_date_display = date_i18n('F j, Y', strtotime('first day of next month'));
        }
        
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $percentage_exact,
            'percentage_exact' => $percentage_exact,
            'percentage_display' => self::format_percentage_label($percentage_exact),
            'plan' => $plan,
            'plan_label' => ucfirst($plan),
            'reset_date' => $reset_date_display,
            'reset_timestamp' => $reset_timestamp,
            'days_until_reset' => $days_until_reset,
            'seconds_until_reset' => $seconds_until_reset,
            'is_free' => $plan === 'free',
            'is_pro' => $plan === 'pro',
        ];
    }
    
    /**
     * Get upgrade URL
     */
    public static function get_upgrade_url() {
        $default = 'https://github.com/beepbeepv2/beepbeep-ai-alt-text-generator';
        $stored  = get_option('bbai_upgrade_url', $default);
        return apply_filters('bbai_upgrade_url', $stored ?: $default);
    }

    /**
     * Get billing portal URL (Stripe customer portal, etc.)
     */
    public static function get_billing_portal_url() {
        $stored = get_option('bbai_billing_portal_url', '');
        return apply_filters('bbai_billing_portal_url', $stored);
    }
    
    /**
     * Dismiss upgrade notice for current session
     */
    public static function dismiss_upgrade_notice() {
        set_transient('bbai_upgrade_dismissed', true, HOUR_IN_SECONDS);
    }
    
    /**
     * Check if upgrade notice is dismissed
     */
    public static function is_upgrade_dismissed() {
        return get_transient('bbai_upgrade_dismissed') === true;
    }
    
    /**
     * Refresh usage data from API and update cache
     */
    public static function refresh_from_api($api_client = null) {
        if (!$api_client) {
            // Try to get API client from global instance
            global $beepbeepai_plugin;
            if (isset($beepbeepai_plugin) && isset($beepbeepai_plugin->api_client)) {
                $api_client = $beepbeepai_plugin->api_client;
            }
        }
        
        if (!$api_client) {
            return false;
        }
        
        $live_usage = $api_client->get_usage();
        if (is_array($live_usage) && !empty($live_usage)) {
            self::update_usage($live_usage);
            return true;
        }
        
        return false;
    }

    /**
     * Format percentage label with dynamic precision for small numbers.
     */
    public static function format_percentage_label($percentage_value) {
        $value = floatval($percentage_value);

        if ($value <= 0) {
            return '0';
        }

        if ($value >= 100) {
            return '100';
        }

        if ($value < 0.01) {
            return '<0.01';
        }

        if ($value < 0.1) {
            return number_format_i18n($value, 2);
        }

        if ($value < 1) {
            return number_format_i18n($value, 1);
        }

        if ($value < 10) {
            return number_format_i18n($value, 1);
        }

        return number_format_i18n($value, 0);
    }
}
