<?php
/**
 * Usage helper utilities.
 */

namespace BeepBeepAI\AltTextGenerator\Services;

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use Exception;
use Error;

if (!defined('ABSPATH')) {
    exit;
}

class Usage_Helper {
    /**
     * Fetch usage stats, preferring live API when authenticated/licensed/registered.
     *
     * @param  object $api_client          API client instance.
     * @param  bool   $has_registered_user Whether we have stored token/license.
     * @return array
     */
    public static function get_usage($api_client, bool $has_registered_user = false): array {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';

        $live_usage = null;
        $usage_stats = Usage_Tracker::get_stats_display(false);

        try {
            $can_fetch = $has_registered_user
                || (is_object($api_client) && method_exists($api_client, 'is_authenticated') && $api_client->is_authenticated())
                || (is_object($api_client) && method_exists($api_client, 'has_active_license') && $api_client->has_active_license());

            if ($can_fetch) {
                $live_usage = $api_client->get_usage();
                if (is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                    Usage_Tracker::update_usage($live_usage);
                    $usage_stats = Usage_Tracker::get_stats_display(true);
                } else {
                    $usage_stats = Usage_Tracker::get_stats_display(true);
                }
            }
        } catch (Exception $e) {
            // Fallback to cached usage
            $usage_stats = Usage_Tracker::get_stats_display(false);
        } catch (Error $e) {
            $usage_stats = Usage_Tracker::get_stats_display(false);
        }

        if (isset($live_usage) && is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
            $usage_stats = self::normalize_usage($usage_stats, $live_usage);
        }

        return $usage_stats;
    }

    /**
     * Normalize usage stats with API data.
     *
     * @param  array      $usage_stats Cached usage.
     * @param  array|null $live_usage  Live usage from API.
     * @return array
     */
    public static function normalize_usage(array $usage_stats, ?array $live_usage = null): array {
        if (!is_array($live_usage) || empty($live_usage)) {
            return $usage_stats;
        }

        $usage_stats['used'] = max(0, intval($live_usage['used'] ?? ($usage_stats['used'] ?? 0)));
        $usage_stats['limit'] = max(1, intval($live_usage['limit'] ?? ($usage_stats['limit'] ?? 50)));
        $usage_stats['remaining'] = max(0, intval($live_usage['remaining'] ?? ($usage_stats['limit'] - $usage_stats['used'])));

        $percentage = $usage_stats['limit'] > 0 ? (($usage_stats['used'] / $usage_stats['limit']) * 100) : 0;
        $usage_stats['percentage'] = min(100, max(0, $percentage));
        $usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label($usage_stats['percentage']);

        if (isset($live_usage['plan'])) {
            $usage_stats['plan'] = $live_usage['plan'];
        }
        if (isset($live_usage['resetDate'])) {
            $usage_stats['resetDate'] = $live_usage['resetDate'];
        }
        if (isset($live_usage['reset_timestamp'])) {
            $usage_stats['reset_timestamp'] = $live_usage['reset_timestamp'];
        }

        return $usage_stats;
    }
}
