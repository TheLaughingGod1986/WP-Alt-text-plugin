<?php
/**
 * Plan Detection Helpers for BeepBeep AI
 * Centralized helper functions for detecting user plan types
 *
 * @package BeepBeep_AI
 * @since 5.5.0
 */

namespace BeepBeepAI\AltTextGenerator\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Plan_Helpers
 * Provides centralized plan detection functionality
 */
class Plan_Helpers {

    /**
     * Cached plan data to avoid multiple lookups
     *
     * @var array|null
     */
    private static $cached_plan_data = null;

    /**
     * Get the current plan slug
     *
     * @return string Plan slug (free, growth, pro, agency)
     */
    public static function get_plan_slug() {
        $data = self::get_plan_data();
        return $data['plan_slug'];
    }

    /**
     * Get all plan data including flags
     *
     * @param bool $force_refresh Force refresh of cached data
     * @return array Plan data with keys: plan_slug, is_free, is_growth, is_agency, is_pro
     */
    public static function get_plan_data($force_refresh = false) {
        if (self::$cached_plan_data !== null && !$force_refresh) {
            return self::$cached_plan_data;
        }

        $plan_slug = 'free';

        // Try to get plan from Usage_Tracker
        if (class_exists('BeepBeepAI\\AltTextGenerator\\Usage_Tracker')) {
            $usage_data = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage();
            if (is_array($usage_data) && isset($usage_data['plan']) && !empty($usage_data['plan'])) {
                $plan_slug = strtolower($usage_data['plan']);
            }
        }

        // Check for license-based plan
        if (class_exists('BeepBeepAI\\AltTextGenerator\\API_Client_V2')) {
            try {
                $api_client = new \BeepBeepAI\AltTextGenerator\API_Client_V2();
                $license_key = $api_client->get_license_key();
                $has_license = !empty($license_key);

                // If using license and plan is still free, check license data
                if ($has_license && $plan_slug === 'free') {
                    $license_data = $api_client->get_license_data();
                    if ($license_data && isset($license_data['organization'])) {
                        $plan_slug = strtolower($license_data['organization']['plan'] ?? 'free');
                    }
                }
            } catch (\Exception $e) {
                // Silently fail, use current plan_slug
            }
        }

        // Calculate plan flags
        $is_free = ($plan_slug === 'free');
        $is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
        $is_agency = ($plan_slug === 'agency');
        $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency'); // Any paid plan

        self::$cached_plan_data = [
            'plan_slug' => $plan_slug,
            'is_free' => $is_free,
            'is_growth' => $is_growth,
            'is_agency' => $is_agency,
            'is_pro' => $is_pro,
        ];

        return self::$cached_plan_data;
    }

    /**
     * Check if user is on free plan
     *
     * @return bool
     */
    public static function is_free() {
        $data = self::get_plan_data();
        return $data['is_free'];
    }

    /**
     * Check if user is on growth/pro plan (not agency)
     *
     * @return bool
     */
    public static function is_growth() {
        $data = self::get_plan_data();
        return $data['is_growth'];
    }

    /**
     * Check if user is on agency plan
     *
     * @return bool
     */
    public static function is_agency() {
        $data = self::get_plan_data();
        return $data['is_agency'];
    }

    /**
     * Check if user is on any paid plan (pro, growth, or agency)
     *
     * @return bool
     */
    public static function is_paid() {
        $data = self::get_plan_data();
        return $data['is_pro'];
    }

    /**
     * Clear the cached plan data
     * Useful when plan changes during a session
     */
    public static function clear_cache() {
        self::$cached_plan_data = null;
    }

    /**
     * Get the plan badge text
     *
     * @return string Badge text for display
     */
    public static function get_plan_badge_text() {
        $plan_slug = self::get_plan_slug();

        switch ($plan_slug) {
            case 'agency':
                return __('AGENCY', 'opptiai-alt');
            case 'pro':
            case 'growth':
                return __('PRO', 'opptiai-alt');
            default:
                return __('FREE', 'opptiai-alt');
        }
    }

    /**
     * Get the plan badge variant (CSS class suffix)
     *
     * @return string Badge variant (free, pro, agency)
     */
    public static function get_plan_badge_variant() {
        $plan_slug = self::get_plan_slug();

        switch ($plan_slug) {
            case 'agency':
                return 'agency';
            case 'pro':
            case 'growth':
                return 'pro';
            default:
                return 'free';
        }
    }

    /**
     * Export plan variables for use in PHP templates
     * Returns an array that can be extracted into local scope
     *
     * @return array Plan variables
     */
    public static function get_template_vars() {
        $data = self::get_plan_data();
        return [
            'plan_slug' => $data['plan_slug'],
            'is_free' => $data['is_free'],
            'is_growth' => $data['is_growth'],
            'is_agency' => $data['is_agency'],
            'is_pro' => $data['is_pro'],
            'plan_badge_text' => self::get_plan_badge_text(),
            'plan_badge_variant' => self::get_plan_badge_variant(),
        ];
    }
}
