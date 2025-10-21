<?php
/**
 * Usage Tracker for AltText AI
 * Caches usage data locally and handles upgrade prompts
 */

if (!defined('ABSPATH')) { exit; }

class AltText_AI_Usage_Tracker {
    
    const CACHE_KEY = 'alttextai_usage_cache';
    const CACHE_EXPIRY = 300; // 5 minutes
    
    /**
     * Update cached usage data
     */
    public static function update_usage($usage_data) {
        if (!is_array($usage_data)) { return; }
        $used  = isset($usage_data['used']) ? max(0, intval($usage_data['used'])) : 0;
        $limit = isset($usage_data['limit']) ? intval($usage_data['limit']) : 10;
        if ($limit <= 0) { $limit = 10; }
        $remaining = isset($usage_data['remaining']) ? intval($usage_data['remaining']) : ($limit - $used);
        if ($remaining < 0) { $remaining = 0; }

        $current_ts = current_time('timestamp');
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
            'resetDate'  => $reset_raw ?: date('Y-m-01', strtotime('+1 month', $current_ts)),
            'reset_timestamp' => $reset_ts,
            'seconds_until_reset' => $seconds_until_reset,
        ];
        set_transient(self::CACHE_KEY, $normalized, self::CACHE_EXPIRY);
    }
    
    /**
     * Get cached usage data
     */
    public static function get_cached_usage() {
        $cached = get_transient(self::CACHE_KEY);
        
        if ($cached === false) {
            // Default values if no cache exists
            $reset_ts = strtotime('first day of next month');
            return [
                'used' => 0,
                'limit' => 10,
                'remaining' => 10,
                'plan' => 'free',
                'resetDate' => date('Y-m-01', $reset_ts),
                'reset_timestamp' => $reset_ts,
                'seconds_until_reset' => max(0, $reset_ts - current_time('timestamp')),
            ];
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
    public static function get_stats_display() {
        $usage = self::get_cached_usage();
        $limit = max(1, intval($usage['limit']));
        $used = max(0, intval($usage['used']));
        if ($used > $limit) { $used = $limit; }
        $remaining = max(0, $limit - $used);
        
        // Calculate days until reset
        $reset_timestamp = isset($usage['reset_timestamp']) ? intval($usage['reset_timestamp']) : 0;
        if ($reset_timestamp <= 0 && !empty($usage['resetDate'])) {
            $reset_timestamp = strtotime($usage['resetDate']);
        }
        $current_timestamp = current_time('timestamp');
        $seconds_until_reset = $reset_timestamp > 0 ? max(0, $reset_timestamp - $current_timestamp) : 0;
        $days_until_reset = (int) floor($seconds_until_reset / DAY_IN_SECONDS);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => min(100, round(($used / max($limit, 1)) * 100)),
            'plan' => $usage['plan'],
            'plan_label' => ucfirst($usage['plan']),
            'reset_date' => $reset_timestamp ? date('F j, Y', $reset_timestamp) : date('F j, Y', strtotime($usage['resetDate'])),
            'days_until_reset' => $days_until_reset,
            'seconds_until_reset' => $seconds_until_reset,
            'is_free' => $usage['plan'] === 'free',
            'is_pro' => $usage['plan'] === 'pro',
        ];
    }
    
    /**
     * Get upgrade URL
     */
    public static function get_upgrade_url() {
        // TODO: Replace with actual upgrade URL (Gumroad, Lemonsqueak, etc.)
        return 'https://alttextai.com/pricing';
    }
    
    /**
     * Dismiss upgrade notice for current session
     */
    public static function dismiss_upgrade_notice() {
        set_transient('alttextai_upgrade_dismissed', true, HOUR_IN_SECONDS);
    }
    
    /**
     * Check if upgrade notice is dismissed
     */
    public static function is_upgrade_dismissed() {
        return get_transient('alttextai_upgrade_dismissed') === true;
    }
}
