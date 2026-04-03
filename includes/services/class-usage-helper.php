<?php
/**
 * Usage helper utilities.
 */

namespace BeepBeepAI\AltTextGenerator\Services;

use BeepBeepAI\AltTextGenerator\Trial_Quota;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use Exception;
use Error;

if (!defined('ABSPATH')) {
    exit;
}

class Usage_Helper {
	/**
	 * Resolve the current anonymous-trial credit limit.
	 *
	 * @return int
	 */
	private static function get_trial_limit(): int {
		if ( ! class_exists( Trial_Quota::class ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
		}

		return max( 1, (int) Trial_Quota::get_limit() );
	}

	/**
	 * Infer the anonymous-trial contract when a payload reports trial-sized quota
	 * but omits or mislabels the auth/quota fields.
	 *
	 * @param array<string, mixed> $live_usage Live usage payload.
	 * @param array<string, mixed> $quota      Nested quota payload.
	 * @param int                  $limit      Normalized credit limit.
	 * @param string               $plan       Normalized plan slug.
	 * @param string               $auth_state Reported auth state.
	 * @param string               $quota_type Reported quota type.
	 * @return bool
	 */
	private static function should_infer_anonymous_trial( array $live_usage, array $quota, int $limit, string $plan, string $auth_state, string $quota_type ): bool {
		$plan = strtolower( trim( $plan ) );
		$auth_state = strtolower( trim( $auth_state ) );
		$quota_type = strtolower( trim( $quota_type ) );

		if ( 'anonymous' === $auth_state || 'trial' === $quota_type || in_array( $plan, [ 'trial', 'anonymous_trial' ], true ) ) {
			return true;
		}

		$is_trial = isset( $live_usage['is_trial'] )
			? (bool) $live_usage['is_trial']
			: ! empty( $quota['is_trial'] );
		if ( $is_trial ) {
			return true;
		}

		if ( in_array( $plan, [ 'pro', 'growth', 'agency', 'enterprise' ], true ) || 'paid' === $quota_type ) {
			return false;
		}

		$source = strtolower( trim( (string) ( $live_usage['source'] ?? '' ) ) );
		if ( 'anonymous_trial' === $source ) {
			return true;
		}

		$has_reset_signal = false;
		foreach ( [ 'resetDate', 'reset_date' ] as $key ) {
			$usage_value = isset( $live_usage[ $key ] ) ? trim( (string) $live_usage[ $key ] ) : '';
			$quota_value = isset( $quota[ $key ] ) ? trim( (string) $quota[ $key ] ) : '';
			if ( '' !== $usage_value || '' !== $quota_value ) {
				$has_reset_signal = true;
				break;
			}
		}

		if ( ! $has_reset_signal ) {
			foreach ( [ 'reset_timestamp', 'resetTimestamp', 'reset_ts', 'days_until_reset', 'daysUntilReset' ] as $key ) {
				if (
					( array_key_exists( $key, $live_usage ) && is_numeric( $live_usage[ $key ] ) )
					|| ( array_key_exists( $key, $quota ) && is_numeric( $quota[ $key ] ) )
				) {
					$has_reset_signal = true;
					break;
				}
			}
		}

		$upgrade_required = isset( $live_usage['upgrade_required'] )
			? (bool) $live_usage['upgrade_required']
			: ! empty( $quota['upgrade_required'] );
		if ( $upgrade_required ) {
			return false;
		}

		return $limit > 0 && $limit <= self::get_trial_limit() && ! $has_reset_signal;
	}

	/**
	 * Resolve quota state for a usage payload.
	 *
	 * @param int  $remaining Remaining credits.
	 * @param int  $limit     Credit limit.
	 * @param bool $is_trial  Whether this is the anonymous trial.
	 * @return string
	 */
	private static function determine_quota_state( int $remaining, int $limit, bool $is_trial = false ): string {
		if ( $remaining <= 0 ) {
			return 'exhausted';
		}

		$threshold = $is_trial
			? ( class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' )
				? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_low_credit_threshold()
				: ( function_exists( '\BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold' )
					? \BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold( $limit )
					: min( 2, max( 1, $limit - 1 ) ) ) )
			: 10;

		return $remaining <= max( 1, (int) $threshold ) ? 'near_limit' : 'active';
	}

    /**
     * Guest / anonymous trial usage snapshot.
     *
     * @return array
     */
    public static function get_guest_trial_usage(): array {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';

        $trial_status = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status();
        $used = max(0, (int) ($trial_status['credits_used'] ?? $trial_status['used'] ?? 0));
        $limit = max(1, (int) ($trial_status['credits_total'] ?? $trial_status['limit'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit()));
        $remaining = max(0, (int) ($trial_status['credits_remaining'] ?? $trial_status['remaining'] ?? max(0, $limit - $used)));
        $percentage = $limit > 0 ? min(100, max(0, ($used / $limit) * 100)) : 0;
        $quota_state = (string) ($trial_status['quota_state'] ?? self::determine_quota_state($remaining, $limit, true));
        $free_plan_offer = max(0, (int) ($trial_status['free_plan_offer'] ?? 50));
        $low_credit_threshold = max(0, (int) ($trial_status['low_credit_threshold'] ?? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_low_credit_threshold()));

        return array_merge($trial_status, [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'percentage_display' => Usage_Tracker::format_percentage_label($percentage),
            'plan' => 'trial',
            'plan_type' => 'trial',
            'plan_label' => __('Free trial', 'beepbeep-ai-alt-text-generator'),
            'source' => 'anonymous_trial',
            'is_trial' => true,
            'trial_exhausted' => !empty($trial_status['exhausted']),
            'trial_near_limit' => 'near_limit' === $quota_state,
            'remaining_free_images' => $remaining,
            'auth_state' => 'anonymous',
            'quota_type' => 'trial',
            'quota_state' => $quota_state,
            'low_credit_threshold' => $low_credit_threshold,
            'signup_required' => !empty($trial_status['signup_required']) || $remaining <= 0,
            'upgrade_required' => false,
            'free_plan_offer' => $free_plan_offer,
            'credits_used' => $used,
            'credits_total' => $limit,
            'credits_remaining' => $remaining,
            'quota' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'plan_type' => 'trial',
                'auth_state' => 'anonymous',
                'quota_type' => 'trial',
                'quota_state' => $quota_state,
                'low_credit_threshold' => $low_credit_threshold,
                'signup_required' => !empty($trial_status['signup_required']) || $remaining <= 0,
                'upgrade_required' => false,
                'free_plan_offer' => $free_plan_offer,
                'credits_used' => $used,
                'credits_total' => $limit,
                'credits_remaining' => $remaining,
                'trial_near_limit' => 'near_limit' === $quota_state,
            ],
            'creditsUsed' => $used,
            'creditsTotal' => $limit,
            'creditsLimit' => $limit,
            'creditsRemaining' => $remaining,
            'anon_id' => $trial_status['anon_id'] ?? '',
            'identity_key' => $trial_status['identity_key'] ?? '',
        ]);
    }

    /**
     * Fetch usage stats, preferring live API when a connected account exists.
     *
     * @param  object $api_client           API client instance.
     * @param  bool   $has_connected_account Whether the site is connected to an account/license.
     * @return array
     */
    public static function get_usage($api_client, bool $has_connected_account = false): array {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';

        $live_usage = null;
        $usage_stats = Usage_Tracker::get_local_usage_snapshot();
        $can_fetch = false;

        try {
            $can_fetch = $has_connected_account
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

        // Trial gating applies even when can_fetch is true (e.g. stray JWT or wrong connected flag).
        if ( class_exists( Trial_Quota::class ) && Trial_Quota::is_trial_user() ) {
            return self::get_guest_trial_usage();
        }

        if (!$can_fetch) {
            return self::get_guest_trial_usage();
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

        $quota = is_array($live_usage['quota'] ?? null) ? $live_usage['quota'] : [];
        $read_number = static function (array $source, array $keys, $default = null) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $value = $source[$key];
                if (is_numeric($value)) {
                    return (float) $value;
                }
            }

            return $default;
        };
        $read_string = static function (array $source, array $keys, string $default = ''): string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $value = $source[$key];
                if (is_string($value) && '' !== trim($value)) {
                    return trim($value);
                }
            }

            return $default;
        };

        $used = $read_number($live_usage, ['credits_used', 'creditsUsed', 'used'], null);
        if (null === $used) {
            $used = $read_number($quota, ['credits_used', 'creditsUsed', 'used'], $usage_stats['used'] ?? 0);
        }
        $used = max(0, intval($used));

        $limit = $read_number($live_usage, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit'], null);
        if (null === $limit) {
            $limit = $read_number($quota, ['credits_total', 'creditsTotal', 'creditsLimit', 'limit'], $usage_stats['limit'] ?? 50);
        }
        $limit = max(1, intval($limit));

        $remaining = $read_number($live_usage, ['credits_remaining', 'creditsRemaining', 'remaining'], null);
        if (null === $remaining) {
            $remaining = $read_number($quota, ['credits_remaining', 'creditsRemaining', 'remaining'], null);
        }
        if (null === $remaining) {
            $remaining = max(0, $limit - $used);
        }
        $remaining = max(0, intval($remaining));

        $usage_stats['used'] = $used;
        $usage_stats['limit'] = $limit;
        $usage_stats['remaining'] = $remaining;
        $usage_stats['credits_used'] = $used;
        $usage_stats['credits_total'] = $limit;
        $usage_stats['credits_remaining'] = $remaining;
        $usage_stats['creditsUsed'] = $used;
        $usage_stats['creditsTotal'] = $limit;
        $usage_stats['creditsLimit'] = $limit;
        $usage_stats['creditsRemaining'] = $remaining;

        $percentage = $limit > 0 ? (($used / $limit) * 100) : 0;
        $usage_stats['percentage'] = min(100, max(0, $percentage));
        $usage_stats['percentage_display'] = Usage_Tracker::format_percentage_label($usage_stats['percentage']);

        $auth_state = $read_string($live_usage, ['auth_state'], $read_string($quota, ['auth_state'], 'authenticated'));
        $quota_type = $read_string($live_usage, ['quota_type'], $read_string($quota, ['quota_type'], ''));
        $plan = $read_string($live_usage, ['plan_type', 'plan'], $read_string($quota, ['plan_type', 'plan'], ''));
        if ('' === $plan && ('anonymous' === $auth_state || 'trial' === $quota_type)) {
            $plan = 'trial';
        }
        if ('' === $plan) {
            $plan = (string) ($usage_stats['plan_type'] ?? ($usage_stats['plan'] ?? 'free'));
        }

        $inferred_trial = self::should_infer_anonymous_trial($live_usage, $quota, $limit, $plan, $auth_state, $quota_type);
        if ($inferred_trial) {
            $auth_state = 'anonymous';
            $quota_type = 'trial';
            if ('' === $plan || in_array($plan, ['free', 'trial', 'anonymous_trial'], true)) {
                $plan = 'trial';
            }
        }

        $usage_stats['source'] = $live_usage['source'] ?? 'remote_usage';
        $usage_stats['plan'] = $plan;
        $usage_stats['plan_type'] = $plan;
        $usage_stats['plan_label'] = isset($live_usage['plan_label']) && is_string($live_usage['plan_label']) && '' !== trim($live_usage['plan_label'])
            ? $live_usage['plan_label']
            : ('trial' === $plan ? __('Free trial', 'beepbeep-ai-alt-text-generator') : ucfirst($plan));
        $usage_stats['is_free'] = in_array($plan, ['free', 'trial'], true);
        $usage_stats['is_pro'] = in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true);
        if ('' === $quota_type) {
            $quota_type = 'trial' === $plan
                ? 'trial'
                : (in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true) ? 'paid' : 'monthly_account');
        }
        $usage_stats['auth_state'] = $auth_state;
        $usage_stats['quota_type'] = $quota_type;
        $usage_stats['quota_state'] = isset($live_usage['quota_state']) && is_string($live_usage['quota_state']) && '' !== trim($live_usage['quota_state'])
            ? $live_usage['quota_state']
            : self::determine_quota_state($remaining, $limit, 'trial' === $quota_type);
        $usage_stats['low_credit_threshold'] = isset($live_usage['low_credit_threshold'])
            ? max(0, (int) $live_usage['low_credit_threshold'])
            : ('trial' === $quota_type
                ? ( function_exists('\BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold')
                    ? max(0, (int) \BeepBeepAI\AltTextGenerator\bbai_get_trial_near_limit_threshold($limit))
                    : min(2, max(1, $limit - 1)) )
                : 10);
        $usage_stats['signup_required'] = isset($live_usage['signup_required'])
            ? (bool) $live_usage['signup_required']
            : ('trial' === $quota_type && $remaining <= 0);
        $usage_stats['upgrade_required'] = isset($live_usage['upgrade_required'])
            ? (bool) $live_usage['upgrade_required']
            : ('trial' === $quota_type ? false : (!$usage_stats['is_pro'] && $remaining <= 0));
        $usage_stats['free_plan_offer'] = max(0, (int) ($live_usage['free_plan_offer'] ?? $usage_stats['free_plan_offer'] ?? 50));
        $usage_stats['is_trial'] = isset($live_usage['is_trial'])
            ? (bool) $live_usage['is_trial']
            : ('trial' === $plan || 'trial' === $quota_type || 'anonymous' === $auth_state || $inferred_trial);
        $usage_stats['trial_exhausted'] = isset($live_usage['trial_exhausted'])
            ? (bool) $live_usage['trial_exhausted']
            : ($usage_stats['is_trial'] && $remaining <= 0);

        if ('trial' === $quota_type && !isset($live_usage['resetDate']) && !isset($live_usage['reset_date']) && !isset($live_usage['reset_timestamp'])) {
            unset($usage_stats['resetDate'], $usage_stats['reset_date'], $usage_stats['reset_timestamp'], $usage_stats['seconds_until_reset']);
            $usage_stats['days_until_reset'] = null;
            $usage_stats['daysUntilReset'] = null;
        } elseif (isset($live_usage['resetDate'])) {
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

        $usage_stats['quota'] = array_merge(
            is_array($usage_stats['quota'] ?? null) ? $usage_stats['quota'] : [],
            [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'plan_type' => $plan,
                'auth_state' => $usage_stats['auth_state'],
                'quota_type' => $usage_stats['quota_type'],
                'quota_state' => $usage_stats['quota_state'],
                'low_credit_threshold' => $usage_stats['low_credit_threshold'],
                'signup_required' => $usage_stats['signup_required'],
                'upgrade_required' => $usage_stats['upgrade_required'],
                'free_plan_offer' => $usage_stats['free_plan_offer'],
                'is_trial' => $usage_stats['is_trial'],
                'trial_exhausted' => $usage_stats['trial_exhausted'],
            ]
        );

        return $usage_stats;
    }
}
