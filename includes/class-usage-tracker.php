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
     * Lightweight debug log for quota/auth state transitions.
     *
     * @param string              $event   Event name.
     * @param array<string,mixed> $context Debug context.
     * @return void
     */
    private static function debug_quota_state(string $event, array $context = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $encoded = wp_json_encode($context);
        if (false === $encoded) {
            $encoded = '{}';
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[BBAI DEBUG] Usage_Tracker::' . sanitize_key($event) . ' ' . $encoded);
    }

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
		if ('anonymous_trial' === $plan_key) {
			return 'trial';
		}
		$allowed  = ['free', 'trial', 'pro', 'growth', 'agency', 'enterprise'];
		return in_array($plan_key, $allowed, true) ? $plan_key : 'free';
	}

	/**
	 * Option key for the durable local successful-generation floor.
	 *
	 * The backend remains the source of truth, but successful local generations
	 * can arrive before backend usage catches up. This monthly floor prevents a
	 * stale remote usage response from rolling the dashboard back.
	 *
	 * @return string
	 */
	private static function generation_success_floor_option_key(): string {
		return 'bbai_generation_success_floor_' . wp_date('Ym');
	}

	/**
	 * Object-cache key for the current monthly local generation count.
	 *
	 * @return string
	 */
	private static function local_generated_count_cache_key(): string {
		return 'bbai_local_generated_count_' . wp_date('Ym');
	}

	/**
	 * Get the durable local successful-generation floor.
	 *
	 * @return int
	 */
	private static function get_generation_success_floor(): int {
		return max(0, (int) get_option(self::generation_success_floor_option_key(), 0));
	}

	/**
	 * Raise the durable local successful-generation floor without lowering it.
	 *
	 * @param int $used Successful generations/credits used this month.
	 * @return int Updated floor.
	 */
	private static function raise_generation_success_floor(int $used): int {
		$used = max(0, (int) $used);
		if ($used <= 0) {
			return self::get_generation_success_floor();
		}

		$current = self::get_generation_success_floor();
		if ($used > $current) {
			update_option(self::generation_success_floor_option_key(), $used, false);
			wp_cache_delete(self::local_generated_count_cache_key(), 'bbai_usage');
			return $used;
		}

		return $current;
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
        foreach (['used', 'credits_used', 'creditsUsed'] as $used_key) {
            if (isset($usage_data[$used_key]) && is_numeric($usage_data[$used_key])) {
                $used = max(0, intval($usage_data[$used_key]));
                break;
            }
        }

        $limit = 50;
        foreach (['limit', 'credits_total', 'creditsTotal', 'creditsLimit', 'total_limit', 'monthly_limit'] as $limit_key) {
            if (isset($usage_data[$limit_key]) && is_numeric($usage_data[$limit_key])) {
                $limit = intval($usage_data[$limit_key]);
                break;
            }
        }
        if ($limit <= 0) {
            $limit = 50;
        }

        $remaining = null;
        foreach (['remaining', 'credits_remaining', 'creditsRemaining'] as $remaining_key) {
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

        $source = sanitize_key((string) ($usage_data['source'] ?? 'remote_usage'));
        $plan = self::normalize_plan_slug($usage_data['plan_type'] ?? $usage_data['plan'] ?? 'free');

        $auth_state = isset($usage_data['auth_state']) && is_scalar($usage_data['auth_state'])
            ? sanitize_key((string) $usage_data['auth_state'])
            : '';
        $quota_type = isset($usage_data['quota_type']) && is_scalar($usage_data['quota_type'])
            ? sanitize_key((string) $usage_data['quota_type'])
            : '';
        $is_trial = ('trial' === $plan)
            || ('anonymous' === $auth_state)
            || ('trial' === $quota_type)
            || in_array($source, ['anonymous_trial', 'local_trial_snapshot'], true)
            || !empty($usage_data['is_trial']);

        if ('' === $auth_state) {
            $auth_state = $is_trial ? 'anonymous' : 'authenticated';
        }
        if ('' === $quota_type) {
            $quota_type = $is_trial ? 'trial' : (in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true) ? 'paid' : 'monthly_account');
        }

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

        $usage_data['source'] = $source;
        $usage_data['auth_state'] = $auth_state;
        $usage_data['quota_type'] = $quota_type;
        $usage_data['is_trial'] = $is_trial;
        $usage_data['signup_required'] = $is_trial ? !empty($usage_data['signup_required']) : false;
        $usage_data['quota_source_displayed_to_user'] = $is_trial ? 'anonymous_trial' : ($source ?: 'authenticated_account');
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
            'auth_state' => $auth_state,
            'quota_type' => $quota_type,
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
        // Anonymous trial must win over the usage transient. A stale `remote_usage` payload
        // (e.g. legacy fake 50 credits) otherwise masks Trial_Quota and breaks UI vs server.
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if ( class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' ) && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() ) {
            $limit     = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_limit();
            $used      = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_used();
            $remaining = \BeepBeepAI\AltTextGenerator\Trial_Quota::get_remaining();

            return self::normalize_usage_payload(
                [
                    'used'      => $used,
                    'limit'     => $limit,
                    'remaining' => $remaining,
                    'plan'      => 'trial',
                    'source'    => 'local_trial_snapshot',
                ]
            );
        }

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
            'auth_state' => 'authenticated',
            'quota_type' => 'monthly_account',
            'is_trial' => false,
        ];
        self::update_usage($usage_data);
        
        return true;
    }

    /**
     * Update cached usage data
     */
    public static function update_usage($usage_data) {
        if (!is_array($usage_data)) { return; }
        $normalized = self::normalize_usage_payload($usage_data);
        $newer_generation_usage = self::get_newer_generation_success_snapshot($normalized);
        if (is_array($newer_generation_usage)) {
            set_transient(self::CACHE_KEY, self::normalize_usage_payload($newer_generation_usage), self::CACHE_EXPIRY);
            delete_transient('bbai_quota_cache');
            return;
        }

        $normalized['source'] = 'remote_usage';
        set_transient(self::CACHE_KEY, $normalized, self::CACHE_EXPIRY);
        delete_transient('bbai_quota_cache');
    }

    /**
     * Consume local monthly credits (free plan fallback).
     *
     * This is only used when the backend usage source-of-truth is unavailable and the
     * site is operating on the local monthly quota snapshot. It must never run for
     * anonymous trial users (Trial_Quota owns that path).
     *
     * @param int $count Credits to consume (>= 1).
     * @return array<string,mixed>|null Updated usage payload or null when not applicable.
     */
    public static function consume_local_monthly_credits(int $count = 1): ?array {
        $count = max(1, (int) $count);

        // Never touch local monthly credits for anonymous trial users.
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
        $api_client = API_Client_V2::get_instance();
        $has_account_credentials = false;
        try {
            $has_account_credentials = ! empty($api_client->get_token())
                || ! empty($api_client->get_license_key())
                || $api_client->has_active_license();
        } catch (\Throwable $e) {
            $has_account_credentials = false;
        }
        if (
            ! $has_account_credentials
            && class_exists('\BeepBeepAI\AltTextGenerator\Trial_Quota')
            && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user()
        ) {
            return null;
        }

        $usage = self::get_local_usage_snapshot();
        $source = strtolower((string) ($usage['source'] ?? ''));

        // Only mutate the local snapshot path.
        if ( ! in_array($source, ['local_snapshot'], true) ) {
            return null;
        }

        $used = max(0, (int) ($usage['used'] ?? 0));
        $limit = max(1, (int) ($usage['limit'] ?? 0));
        $remaining = max(0, (int) ($usage['remaining'] ?? max(0, $limit - $used)));

        $new_used = min($limit, $used + $count);
        $new_remaining = max(0, $limit - $new_used);

        $next = array_merge($usage, [
            'used' => $new_used,
            'remaining' => $new_remaining,
            'limit' => $limit,
            'credits_used' => $new_used,
            'credits_remaining' => $new_remaining,
            'credits_total' => $limit,
            'creditsUsed' => $new_used,
            'creditsRemaining' => $new_remaining,
            'creditsTotal' => $limit,
            'creditsLimit' => $limit,
            'source' => 'local_snapshot',
        ]);

        // Persist to transient (keep local source).
        set_transient(self::CACHE_KEY, self::normalize_usage_payload($next), self::CACHE_EXPIRY);
        delete_transient('bbai_quota_cache');

        return self::get_local_usage_snapshot();
    }

    /**
     * Record a successful generation when the backend response does not include
     * a refreshed usage payload yet.
     *
     * This keeps the dashboard usage card responsive after successful work while
     * preserving backend quota as the eventual source of truth. A later backend
     * usage payload with the same or greater used count will replace this cache.
     *
     * @param int $count Successful generation count.
     * @return array<string,mixed>|null Updated usage payload or null when not applicable.
     */
    public static function record_generation_success(int $count = 1): ?array {
        $count = max(1, (int) $count);

        $usage = self::get_local_usage_snapshot();
        if ( ! is_array($usage) || empty($usage) ) {
            return null;
        }

        $plan = self::normalize_plan_slug($usage['plan_type'] ?? $usage['plan'] ?? 'free');
        $auth_state = sanitize_key((string) ($usage['auth_state'] ?? ''));
        $quota_type = sanitize_key((string) ($usage['quota_type'] ?? ''));
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if (
            class_exists('\BeepBeepAI\AltTextGenerator\Trial_Quota')
            && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user()
            && ('anonymous' === $auth_state || 'trial' === $quota_type || 'trial' === $plan || ! empty($usage['is_trial']))
        ) {
            return null;
        }
        if ( in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true) && empty($usage['limit']) ) {
            return null;
        }

        $used = max(0, (int) ($usage['used'] ?? 0));
        $history_used = self::get_local_successful_generations_this_month();
        $limit = max(1, (int) ($usage['limit'] ?? 50));

		$new_used = min($limit, max($used + $count, $history_used));
		$new_remaining = max(0, $limit - $new_used);
		self::raise_generation_success_floor($new_used);

		$next = array_merge($usage, [
            'used' => $new_used,
            'remaining' => $new_remaining,
            'limit' => $limit,
            'credits_used' => $new_used,
            'credits_remaining' => $new_remaining,
            'credits_total' => $limit,
            'creditsUsed' => $new_used,
            'creditsRemaining' => $new_remaining,
            'creditsTotal' => $limit,
            'creditsLimit' => $limit,
            'source' => 'generation_success',
            'generation_success_recorded_at' => current_time('mysql'),
        ]);

        set_transient(self::CACHE_KEY, self::normalize_usage_payload($next), self::CACHE_EXPIRY);
        delete_transient('bbai_quota_cache');

        return self::get_local_usage_snapshot();
    }

    /**
     * Return a locally recorded generation-success snapshot when a live usage
     * response is older than the successful generation already shown locally.
     *
     * @param array<string,mixed> $live_usage Live/backend usage payload.
     * @return array<string,mixed>|null Newer local generation-success usage, or null.
     */
	public static function get_newer_generation_success_snapshot(array $live_usage): ?array {
		$cached = get_transient(self::CACHE_KEY);
		$cached = is_array($cached) ? self::normalize_usage_payload($cached) : [];
		$source = strtolower((string) ($cached['source'] ?? ''));
		$local_floor = self::get_generation_success_floor();
		$cached_used = in_array($source, ['generation_success', 'local_generation_history'], true)
			? max(0, (int) ($cached['used'] ?? $cached['credits_used'] ?? $cached['creditsUsed'] ?? 0))
			: 0;
		$live_used = max(0, (int) ($live_usage['used'] ?? $live_usage['credits_used'] ?? $live_usage['creditsUsed'] ?? 0));
		$live_limit = max(1, (int) ($live_usage['limit'] ?? $live_usage['credits_total'] ?? $live_usage['creditsTotal'] ?? $live_usage['creditsLimit'] ?? 50));
		$cached_limit = max(1, (int) ($cached['limit'] ?? $cached['credits_total'] ?? $cached['creditsTotal'] ?? $live_limit));

		if ($cached_used > $live_used && $cached_limit === $live_limit) {
			return $cached;
		}

		if ($local_floor > $live_used && $local_floor <= $live_limit) {
			$remaining = max(0, $live_limit - $local_floor);
			return self::normalize_usage_payload(array_merge($live_usage, [
				'used' => $local_floor,
				'remaining' => $remaining,
				'limit' => $live_limit,
				'credits_used' => $local_floor,
				'credits_remaining' => $remaining,
				'credits_total' => $live_limit,
				'creditsUsed' => $local_floor,
				'creditsRemaining' => $remaining,
				'creditsTotal' => $live_limit,
				'creditsLimit' => $live_limit,
				'source' => 'local_generation_history',
				'local_generation_history_recorded_at' => current_time('mysql'),
			]));
		}

		return null;
	}

    /**
     * Count successful local generation records for the current calendar month.
     *
     * @return int Local successful generation count.
     */
    public static function get_local_successful_generations_this_month(): int {
        $month_start = wp_date('Y-m-01 00:00:00');
        $month_end = wp_date('Y-m-t 23:59:59');

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        if (class_exists('\BeepBeepAI\AltTextGenerator\Credit_Usage_Logger')) {
            $site_usage = \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::get_site_usage([
                'date_from' => $month_start,
                'date_to' => $month_end,
            ]);
            $logged_credits = is_array($site_usage) ? max(0, (int) ($site_usage['total_credits'] ?? 0)) : 0;
            if ($logged_credits > 0) {
                return max($logged_credits, self::get_generation_success_floor());
            }
        }

        $cache_key = self::local_generated_count_cache_key();
        $cached_count = wp_cache_get($cache_key, 'bbai_usage');
        if (false !== $cached_count) {
            return max(max(0, (int) $cached_count), self::get_generation_success_floor());
        }

        $generated_query = new \WP_Query([
            'post_type'              => 'attachment',
            'post_status'            => 'inherit',
            'fields'                 => 'ids',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ]);

        $generated_count = 0;
        if (is_array($generated_query->posts)) {
            foreach ($generated_query->posts as $attachment_id) {
                $generated_at = (string) get_post_meta((int) $attachment_id, '_bbai_generated_at', true);
                if ($generated_at >= $month_start && $generated_at <= $month_end) {
                    ++$generated_count;
                }
            }
        }
        $generated_count = max((int) $generated_count, self::get_generation_success_floor());
        wp_cache_set($cache_key, $generated_count, 'bbai_usage', MINUTE_IN_SECONDS);

        return max(0, (int) $generated_count);
    }

    /**
     * Persist a usage snapshot from successful local generation history.
     *
     * @param int                  $used Successful local generations this month.
     * @param array<string,mixed>  $base Existing usage payload to preserve plan/limit data.
     * @return array<string,mixed>|null Updated usage payload or null when not applicable.
     */
    public static function record_local_generation_history_usage(int $used, array $base = []): ?array {
        $used = max(0, (int) $used);
        if ($used <= 0) {
            return null;
        }

        $current = self::get_local_usage_snapshot();
        $usage = array_merge(is_array($current) ? $current : [], $base);
        $plan = self::normalize_plan_slug($usage['plan_type'] ?? $usage['plan'] ?? 'free');
        $auth_state = sanitize_key((string) ($usage['auth_state'] ?? ''));
        $quota_type = sanitize_key((string) ($usage['quota_type'] ?? ''));
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        if (
            class_exists('\BeepBeepAI\AltTextGenerator\Trial_Quota')
            && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user()
            && ('anonymous' === $auth_state || 'trial' === $quota_type || 'trial' === $plan || ! empty($usage['is_trial']))
        ) {
            return null;
        }
		$limit = max(1, (int) ($usage['limit'] ?? $usage['credits_total'] ?? $usage['creditsTotal'] ?? $usage['creditsLimit'] ?? 50));
		$used = min($limit, $used);
		$remaining = max(0, $limit - $used);
		self::raise_generation_success_floor($used);

		$next = array_merge($usage, [
            'used' => $used,
            'remaining' => $remaining,
            'limit' => $limit,
            'credits_used' => $used,
            'credits_remaining' => $remaining,
            'credits_total' => $limit,
            'creditsUsed' => $used,
            'creditsRemaining' => $remaining,
            'creditsTotal' => $limit,
            'creditsLimit' => $limit,
            'source' => 'local_generation_history',
            'local_generation_history_recorded_at' => current_time('mysql'),
        ]);

        set_transient(self::CACHE_KEY, self::normalize_usage_payload($next), self::CACHE_EXPIRY);
        delete_transient('bbai_quota_cache');

        return self::get_local_usage_snapshot();
    }
    
    /**
     * Get cached usage data
     */
    public static function get_cached_usage($force_refresh = false) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
        $api_client = API_Client_V2::get_instance();

        if ($force_refresh) {
            delete_transient(self::CACHE_KEY);
        }

        // Anonymous trial: never trust bbai_usage_cache; it may hold legacy remote/free rows.
        if ( class_exists( '\BeepBeepAI\AltTextGenerator\Trial_Quota' ) && \BeepBeepAI\AltTextGenerator\Trial_Quota::is_trial_user() ) {
            return self::get_local_usage_snapshot();
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

                return self::get_local_usage_snapshot();
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[BBAI DEBUG] Usage_Tracker::clear_cache executed');
        }
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
        $source = isset($usage['source']) ? sanitize_key((string) $usage['source']) : 'remote_usage';
        $auth_state = isset($usage['auth_state']) ? sanitize_key((string) $usage['auth_state']) : '';
        $quota_type = isset($usage['quota_type']) ? sanitize_key((string) $usage['quota_type']) : '';
        $is_trial = !empty($usage['is_trial']) || 'anonymous' === $auth_state || 'trial' === $quota_type || 'trial' === self::normalize_plan_slug($plan);
        if ('' === $auth_state) {
            $auth_state = $is_trial ? 'anonymous' : 'authenticated';
        }
        if ('' === $quota_type) {
            $quota_type = $is_trial ? 'trial' : (in_array(self::normalize_plan_slug($plan), ['pro', 'growth', 'agency', 'enterprise'], true) ? 'paid' : 'monthly_account');
        }

        // Get reset date with fallback - format: "February 1, 2026"
        $reset_date_display = $reset_timestamp ? date_i18n('F j, Y', $reset_timestamp) : '';
        if (empty($reset_date_display)) {
            $reset_date_display = date_i18n('F j, Y', strtotime('first day of next month'));
        }

        $plan_label = isset($usage['plan_label']) && is_string($usage['plan_label']) && '' !== trim($usage['plan_label'])
            ? sanitize_text_field($usage['plan_label'])
            : ucfirst($plan);
        
        $display = [
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
                'auth_state' => $auth_state,
                'quota_type' => $quota_type,
            ],
            'is_free' => in_array($plan, ['free', 'trial'], true),
            'is_pro' => in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true),
            'auth_state' => $auth_state,
            'quota_type' => $quota_type,
            'source' => $source,
            'is_trial' => $is_trial,
            'signup_required' => $is_trial ? !empty($usage['signup_required']) : false,
            'quota_source_displayed_to_user' => $is_trial ? 'anonymous_trial' : ($source ?: 'authenticated_account'),
        ];

        self::debug_quota_state(
            'quota_source_displayed_to_user',
            [
                'auth_state' => $display['auth_state'],
                'quota_type' => $display['quota_type'],
                'quota_source_displayed_to_user' => $display['quota_source_displayed_to_user'],
                'source' => $display['source'],
                'used' => $display['used'],
                'limit' => $display['limit'],
                'remaining' => $display['remaining'],
            ]
        );

        return $display;
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
