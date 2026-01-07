<?php
/**
 * API Usage Trait
 * Handles usage tracking and limits
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Api_Usage {

    /**
     * Get current usage statistics
     */
    public function get_usage($force_refresh = false) {
        $cache_key = 'bbai_usage_cache';
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        if ($this->has_active_license()) {
            $license_data = $this->get_license_data();
            if (!empty($license_data) && isset($license_data['organization'])) {
                $usage = $this->format_license_usage_from_cache($license_data);
                if (!empty($usage)) {
                    set_transient($cache_key, $usage, 5 * MINUTE_IN_SECONDS);
                    return $usage;
                }
            }
        }

        $response = $this->make_request('/usage', 'GET');

        if (is_wp_error($response)) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
            return $response;
        }

        $organization = $response['organization'] ?? [];
        $site_data = $response['site'] ?? [];
        $usage = $this->sync_license_usage_snapshot($response, $organization, $site_data);

        set_transient($cache_key, $usage, 5 * MINUTE_IN_SECONDS);

        return $usage;
    }

    /**
     * Format usage from cached license data
     */
    private function format_license_usage_from_cache($license_data) {
        $org = $license_data['organization'] ?? [];
        $site = $license_data['site'] ?? [];

        if (empty($org)) {
            return null;
        }

        $plan = strtolower($org['plan'] ?? 'free');
        $limit = intval($org['monthly_limit'] ?? 0);
        $used = intval($org['credits_used'] ?? 0);
        $remaining = max(0, $limit - $used);

        return [
            'plan' => $plan,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'reset_date' => $org['reset_date'] ?? null,
            'is_license' => true,
            'organization' => $org['name'] ?? '',
            'site_used' => intval($site['credits_used'] ?? 0),
        ];
    }

    /**
     * Sync usage snapshot from API response
     */
    private function sync_license_usage_snapshot($usage, $organization = [], $site_data = []) {
        $plan = strtolower($usage['plan'] ?? 'free');
        $limit = intval($usage['limit'] ?? $usage['monthly_limit'] ?? 0);
        $used = intval($usage['used'] ?? $usage['credits_used'] ?? 0);
        $remaining = isset($usage['remaining']) ? intval($usage['remaining']) : max(0, $limit - $used);

        if (!empty($organization)) {
            $this->set_license_data([
                'organization' => $organization,
                'site' => $site_data,
            ]);
        }

        return [
            'plan' => $plan,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'reset_date' => $usage['reset_date'] ?? null,
            'is_license' => !empty($this->get_license_key()),
            'organization' => $organization['name'] ?? '',
            'site_used' => intval($site_data['credits_used'] ?? 0),
        ];
    }

    /**
     * Check if user has reached their limit
     */
    public function has_reached_limit() {
        $usage = $this->get_usage();

        if (is_wp_error($usage)) {
            return false;
        }

        $plan = strtolower($usage['plan'] ?? 'free');
        if ($plan === 'pro' || $plan === 'agency') {
            return false;
        }

        $remaining = isset($usage['remaining']) ? intval($usage['remaining']) : null;
        if ($remaining !== null && $remaining <= 0) {
            return true;
        }

        $limit = intval($usage['limit'] ?? 0);
        $used = intval($usage['used'] ?? 0);
        if ($limit > 0 && $used >= $limit) {
            return true;
        }

        return false;
    }

    /**
     * Get usage percentage
     */
    public function get_usage_percentage() {
        $usage = $this->get_usage();

        if (is_wp_error($usage)) {
            return 0;
        }

        $limit = intval($usage['limit'] ?? 0);
        $used = intval($usage['used'] ?? 0);

        if ($limit <= 0) {
            return 0;
        }

        return min(100, round(($used / $limit) * 100, 1));
    }
}
