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
        $usage_stats = Usage_Tracker::get_local_usage_snapshot();

        try {
            $can_fetch = $has_registered_user
                || (is_object($api_client) && method_exists($api_client, 'is_authenticated') && $api_client->is_authenticated())
                || (is_object($api_client) && method_exists($api_client, 'has_active_license') && $api_client->has_active_license());

            if ($can_fetch) {
                $live_usage = $api_client->get_usage();
                if (is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                    if (($live_usage['source'] ?? '') !== 'local_snapshot') {
                        Usage_Tracker::update_usage($live_usage);
                    }
                    $usage_stats = self::normalize_usage($usage_stats, $live_usage);
                }
            }
        } catch (Exception $e) {
            $usage_stats = Usage_Tracker::get_local_usage_snapshot();
        } catch (Error $e) {
            $usage_stats = Usage_Tracker::get_local_usage_snapshot();
        }

        if (isset($live_usage) && is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
            $usage_stats = self::normalize_usage($usage_stats, $live_usage);
        }

        return $usage_stats;
    }

    /**
     * Normalize usage stats with API data.
     *
     * Runtime quota comes from the normalized usage API payload. Do not read
     * deprecated account/license quota fields here.
     *
     * @param  array      $usage_stats Cached usage.
     * @param  array|null $live_usage  Live usage from API.
     * @return array
     */
    public static function normalize_usage(array $usage_stats, ?array $live_usage = null): array {
        if (!is_array($live_usage) || empty($live_usage)) {
            return $usage_stats;
        }

        $used = max(0, intval($live_usage['used'] ?? ($usage_stats['used'] ?? 0)));
        $limit = max(1, intval($live_usage['limit'] ?? ($usage_stats['limit'] ?? 50)));
        $remaining = max(0, intval($live_usage['remaining'] ?? ($usage_stats['remaining'] ?? 0)));

        $usage_stats['used'] = $used;
        $usage_stats['limit'] = $limit;
        $usage_stats['remaining'] = $remaining;
        $usage_stats['creditsUsed'] = $used;
        $usage_stats['creditsTotal'] = $limit;
        $usage_stats['creditsLimit'] = $limit;
        $usage_stats['creditsRemaining'] = $remaining;

        $percentage = $limit > 0 ? (($used / $limit) * 100) : 0;
        $usage_stats['percentage'] = min(100, max(0, $percentage));
        $usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label($usage_stats['percentage']);

        $plan = $live_usage['plan_type'] ?? $live_usage['plan'] ?? ($usage_stats['plan_type'] ?? ($usage_stats['plan'] ?? 'free'));
        $usage_stats['source'] = $live_usage['source'] ?? 'remote_usage';
        $usage_stats['plan'] = $plan;
        $usage_stats['plan_type'] = $plan;
        $usage_stats['plan_label'] = ucfirst($plan);
        $usage_stats['is_free'] = $plan === 'free';
        $usage_stats['is_pro'] = in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true);

        if (isset($live_usage['resetDate'])) {
            $usage_stats['resetDate'] = $live_usage['resetDate'];
        }
        if (isset($live_usage['reset_timestamp'])) {
            $usage_stats['reset_timestamp'] = $live_usage['reset_timestamp'];
            $usage_stats['reset_date'] = date_i18n('F j, Y', $live_usage['reset_timestamp']);
        } elseif (isset($live_usage['resetDate']) || isset($live_usage['reset_date'])) {
            $reset_value = $live_usage['resetDate'] ?? $live_usage['reset_date'];
            $parsed_ts = strtotime((string) $reset_value);
            if ($parsed_ts > 0) {
                $usage_stats['reset_timestamp'] = $parsed_ts;
                $usage_stats['reset_date'] = date_i18n('F j, Y', $parsed_ts);
                $usage_stats['resetDate'] = date_i18n('Y-m-d', $parsed_ts);
            }
        }

        if (!isset($usage_stats['resetDate']) && isset($usage_stats['reset_timestamp'])) {
            $usage_stats['resetDate'] = date_i18n('Y-m-d', (int) $usage_stats['reset_timestamp']);
        }

        if (isset($usage_stats['reset_timestamp']) && is_numeric($usage_stats['reset_timestamp'])) {
            $reset_timestamp = max(0, (int) $usage_stats['reset_timestamp']);
            $now = (int) current_time('timestamp');
            $seconds_until_reset = max(0, $reset_timestamp - $now);
            $usage_stats['seconds_until_reset'] = $seconds_until_reset;
            $usage_stats['days_until_reset'] = Usage_Tracker::seconds_to_days_until_reset($seconds_until_reset);
        }

        return $usage_stats;
    }
}
