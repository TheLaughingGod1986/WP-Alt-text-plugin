<?php
/**
 * Usage Tracker for AltText AI
 * Caches normalized usage API data locally and handles upgrade prompts.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

class Usage_Tracker {
    
    const CACHE_KEY = 'bbai_usage_cache';
    const CACHE_EXPIRY = 300; // 5 minutes

    /**
     * Convert remaining seconds to a user-facing day countdown.
     * Uses ceil so partial days are surfaced as a full day.
     *
     * @param int $seconds_until_reset Remaining seconds.
     * @return int
     */
    public static function seconds_to_days_until_reset(int $seconds_until_reset): int {
        if ($seconds_until_reset <= 0) {
            return 0;
        }

        return (int) ceil($seconds_until_reset / DAY_IN_SECONDS);
    }

    /**
     * Calculate days until reset from timestamps.
     *
     * @param int      $reset_timestamp   Reset timestamp (seconds).
     * @param int|null $current_timestamp Current timestamp (seconds).
     * @return int
     */
    public static function calculate_days_until_reset(int $reset_timestamp, ?int $current_timestamp = null): int {
        $now = $current_timestamp !== null ? (int) $current_timestamp : (int) current_time('timestamp');
        $seconds_until_reset = max(0, $reset_timestamp - $now);
        return self::seconds_to_days_until_reset($seconds_until_reset);
    }

	/**
	 * Normalize plan slugs from remote/local payloads.
	 *
	 * @param mixed $plan Plan value.
	 * @return string
	 */
	private static function normalize_plan_slug($plan) {
		$plan_key = is_scalar($plan) ? sanitize_key((string) $plan) : '';
		$allowed  = ['free', 'pro', 'growth', 'agency', 'enterprise'];
		return in_array($plan_key, $allowed, true) ? $plan_key : 'free';
	}

    /**
     * Normalize a usage payload so callers see a consistent shape.
     *
     * Runtime quota source of truth is the backend usage API or a successful
     * generation response that includes refreshed usage. Legacy quota fields
     * should not drive plugin UI decisions.
     *
     * @param array $usage_data Usage payload.
     * @return array
     */
    private static function normalize_usage_payload(array $usage_data): array {
        $current_ts = (int) current_time('timestamp');

        $used = 0;
        foreach (['used'] as $used_key) {
            if (isset($usage_data[$used_key]) && is_numeric($usage_data[$used_key])) {
                $used = max(0, intval($usage_data[$used_key]));
                break;
            }
        }

        $limit = 50;
        foreach (['limit'] as $limit_key) {
            if (isset($usage_data[$limit_key]) && is_numeric($usage_data[$limit_key])) {
                $limit = intval($usage_data[$limit_key]);
                break;
            }
        }
        if ($limit <= 0) {
            $limit = 50;
        }

        $remaining = null;
        foreach (['remaining'] as $remaining_key) {
            if (isset($usage_data[$remaining_key]) && is_numeric($usage_data[$remaining_key])) {
                $remaining = intval($usage_data[$remaining_key]);
                break;
            }
        }
        if (null === $remaining) {
            $remaining = $limit - $used;
        }
        if ($remaining < 0) {
            $remaining = 0;
        }

        $plan = self::normalize_plan_slug($usage_data['plan_type'] ?? $usage_data['plan'] ?? 'free');

        $reset_input = $usage_data['resetDate'] ?? $usage_data['reset_date'] ?? '';
        $reset_ts = isset($usage_data['reset_timestamp']) ? intval($usage_data['reset_timestamp']) : 0;
        if ($reset_ts <= 0 && $reset_input) {
            $parsed_reset = strtotime((string) $reset_input);
            if ($parsed_reset > 0) {
                $reset_ts = $parsed_reset;
            }
        }
        if ($reset_ts <= 0) {
            $reset_ts = strtotime('first day of next month', $current_ts);
        }

        $usage_data['source'] = $usage_data['source'] ?? 'remote_usage';
        $usage_data['used'] = $used;
        $usage_data['limit'] = $limit;
        $usage_data['remaining'] = $remaining;
        $usage_data['creditsUsed'] = $used;
        $usage_data['creditsTotal'] = $limit;
        $usage_data['creditsLimit'] = $limit;
        $usage_data['creditsRemaining'] = $remaining;
        $usage_data['plan'] = $plan;
        $usage_data['plan_type'] = $plan;
        $usage_data['resetDate'] = wp_date('Y-m-d', $reset_ts);
        $usage_data['reset_date'] = date_i18n('F j, Y', $reset_ts);
        $usage_data['reset_timestamp'] = $reset_ts;
        $usage_data['seconds_until_reset'] = max(0, $reset_ts - $current_ts);
        $usage_data['quota'] = [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset_date' => $usage_data['reset_date'],
            'reset_timestamp' => $reset_ts,
            'plan_type' => $plan,
        ];

        return $usage_data;
    }

    /**
     * Build a usage snapshot from the current local cache only.
     *
     * This never makes a remote request and is safe to use as a fallback when
     * the backend usage endpoint is unavailable.
     *
     * @return array
     */
    public static function get_local_usage_snapshot() {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return self::normalize_usage_payload($cached);
        }

        $free_credits_allocated = get_option('beepbeepai_free_credits_allocated', false);
        $current_ts = (int) current_time('timestamp');
        $reset_ts = strtotime('first day of next month', $current_ts);
        $seconds_until_reset = max(0, $reset_ts - $current_ts);

        if ($free_credits_allocated) {
            return self::normalize_usage_payload([
                'used'       => 0,
                'limit'      => 50,
                'remaining'  => 50,
                'plan'       => 'free',
                'resetDate'  => wp_date('Y-m-01', $reset_ts),
                'reset_timestamp' => $reset_ts,
                'seconds_until_reset' => $seconds_until_reset,
                'source'     => 'local_snapshot',
            ]);
        }

        return self::normalize_usage_payload([
            'used'       => 0,
            'limit'      => 0,
            'remaining'  => 0,
            'plan'       => 'free',
            'resetDate'  => wp_date('Y-m-01', $reset_ts),
            'reset_timestamp' => $reset_ts,
            'seconds_until_reset' => $seconds_until_reset,
            'source'     => 'local_snapshot',
        ]);
    }
    
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
        $usage_data['source'] = 'remote_usage';
        set_transient(self::CACHE_KEY, self::normalize_usage_payload($usage_data), self::CACHE_EXPIRY);
        delete_transient('bbai_quota_cache');
    }
    
    /**
     * Get cached usage data
     */
    public static function get_cached_usage($force_refresh = false) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
        $api_client = API_Client_V2::get_instance();

        if ($force_refresh) {
            delete_transient(self::CACHE_KEY);
        }

        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached) && !$force_refresh) {
            return self::normalize_usage_payload($cached);
        }

        $token = '';
        $license_key = '';
        try {
            $token = $api_client->get_token();
        } catch (\Exception $e) {
            $token = '';
        } catch (\Error $e) {
            $token = '';
        }
        try {
            $license_key = $api_client->get_license_key();
        } catch (\Exception $e) {
            $license_key = '';
        } catch (\Error $e) {
            $license_key = '';
        }

        $has_credentialed_account = !empty($token) || !empty($license_key) || $api_client->has_active_license();

        if ($has_credentialed_account) {
            $live_usage = $api_client->get_usage();
            if (!is_wp_error($live_usage) && is_array($live_usage) && !empty($live_usage)) {
                if (($live_usage['source'] ?? '') !== 'local_snapshot') {
                    self::update_usage($live_usage);
                }

                return self::normalize_usage_payload($live_usage);
            }

            return self::get_local_usage_snapshot();
        }

        // No stored auth credentials - fall back to local trial/free usage.
        return self::get_local_usage_snapshot();
    }
    
    /**
     * Clear cached usage
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
        delete_transient('bbai_quota_cache');
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
        $remaining = isset($usage['remaining'])
            ? max(0, intval($usage['remaining']))
            : max(0, $limit - $used);
        $percentage_used = min($used, $limit);
        $percentage_exact = $limit > 0 ? ($percentage_used / $limit) * 100 : 0;
        $percentage_exact = min(100, max(0, $percentage_exact));
        
        // Calculate days until reset
        $reset_timestamp = isset($usage['reset_timestamp']) ? intval($usage['reset_timestamp']) : 0;
        $current_timestamp = (int) current_time('timestamp');

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
        $days_until_reset = self::seconds_to_days_until_reset($seconds_until_reset);

        // Get plan with fallback
        $plan = isset($usage['plan']) && !empty($usage['plan']) ? $usage['plan'] : 'free';

        // Get reset date with fallback - format: "February 1, 2026"
        $reset_date_display = $reset_timestamp ? date_i18n('F j, Y', $reset_timestamp) : '';
        if (empty($reset_date_display)) {
            $reset_date_display = date_i18n('F j, Y', strtotime('first day of next month'));
        }

        $plan_label = isset($usage['plan_label']) && is_string($usage['plan_label']) && '' !== trim($usage['plan_label'])
            ? sanitize_text_field($usage['plan_label'])
            : ucfirst($plan);
        
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'creditsUsed' => $used,
            'creditsTotal' => $limit,
            'creditsLimit' => $limit,
            'creditsRemaining' => $remaining,
            'percentage' => $percentage_exact,
            'percentage_exact' => $percentage_exact,
            'percentage_display' => self::format_percentage_label($percentage_exact),
            'plan' => $plan,
            'plan_type' => $plan,
            'plan_label' => $plan_label,
            'resetDate' => wp_date('Y-m-d', $reset_timestamp),
            'reset_date' => $reset_date_display,
            'reset_timestamp' => $reset_timestamp,
            'days_until_reset' => $days_until_reset,
            'seconds_until_reset' => $seconds_until_reset,
            'quota' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'reset_date' => $reset_date_display,
                'reset_timestamp' => $reset_timestamp,
                'plan_type' => $plan,
            ],
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
     * User meta keys for monthly reset tracking.
     */
    const META_LAST_RESET_PERIOD = 'bbai_last_reset_period';
    const META_LAST_MONTH_USAGE  = 'bbai_last_month_usage';
    const META_LAST_MONTH_LIMIT  = 'bbai_last_month_limit';

    /**
     * Detect if a monthly quota reset has occurred since the user last saw the modal.
     *
     * @param int|null $user_id User ID, defaults to current user.
     * @return bool True if a new billing period started since the modal was last shown.
     */
    public static function detect_reset( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return false;
        }

        $current_period = wp_date( 'Y-m' );
        $last_shown     = get_user_meta( $user_id, self::META_LAST_RESET_PERIOD, true );

        // Already shown for this period.
        if ( $last_shown === $current_period ) {
            return false;
        }

        // First visit ever — seed the period so next month triggers correctly.
        if ( empty( $last_shown ) ) {
            update_user_meta( $user_id, self::META_LAST_RESET_PERIOD, sanitize_key( $current_period ) );
            return false;
        }

        // A new period has started since last shown.
        return true;
    }

    /**
     * Store previous month's usage data for the reset insight modal.
     *
     * @param int      $used    Images generated last month.
     * @param int      $limit   Monthly limit last month.
     * @param int|null $user_id User ID, defaults to current user.
     */
    public static function store_previous_month_data( $used, $limit, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return;
        }

        update_user_meta( $user_id, self::META_LAST_MONTH_USAGE, absint( $used ) );
        update_user_meta( $user_id, self::META_LAST_MONTH_LIMIT, absint( $limit ) );
    }

    /**
     * Get data for the monthly reset insight modal.
     *
     * @param int|null $user_id User ID, defaults to current user.
     * @return array|null Modal data array or null if unavailable.
     */
    public static function get_reset_modal_data( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return null;
        }

        $last_usage = absint( get_user_meta( $user_id, self::META_LAST_MONTH_USAGE, true ) );
        $last_limit = absint( get_user_meta( $user_id, self::META_LAST_MONTH_LIMIT, true ) );

        // Only show modal if user actually generated images last month.
        if ( $last_usage <= 0 ) {
            return null;
        }

        $current_stats = self::get_stats_display();

        return [
            'lastMonthUsed'  => $last_usage,
            'lastMonthLimit' => $last_limit,
            'newLimit'       => absint( $current_stats['limit'] ),
            'plan'           => sanitize_key( $current_stats['plan'] ),
            'planLabel'      => esc_html( $current_stats['plan_label'] ),
        ];
    }

    /**
     * Mark the reset insight modal as shown for the current billing period.
     *
     * @param int|null $user_id User ID, defaults to current user.
     * @return bool
     */
    public static function mark_reset_shown( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) {
            return false;
        }

        return (bool) update_user_meta( $user_id, self::META_LAST_RESET_PERIOD, sanitize_key( wp_date( 'Y-m' ) ) );
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
