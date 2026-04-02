<?php
/**
 * Clean dashboard command center for authenticated users.
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;
use BeepBeepAI\AltTextGenerator\Services\Dashboard_State;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/retention-lifecycle.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/content/bbai-admin-copy.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-dashboard-state.php';

$bbai_build_action = static function (string $label = '', array $overrides = []): array {
    return array_merge(
        [
            'label' => $label,
            'href' => '#',
            'action' => '',
            'bbai_action' => '',
            'aria_label' => '',
            'extra_attrs' => [],
        ],
        $overrides
    );
};

$bbai_build_action_attrs = static function (array $action): string {
    $href = isset($action['href']) && '' !== (string) $action['href'] ? (string) $action['href'] : '#';
    $attributes = [
        'href="' . esc_url($href) . '"',
    ];

    if (!empty($action['action'])) {
        $attributes[] = 'data-action="' . esc_attr((string) $action['action']) . '"';
    }

    if (!empty($action['bbai_action'])) {
        $attributes[] = 'data-bbai-action="' . esc_attr((string) $action['bbai_action']) . '"';
    }

    if (!empty($action['aria_label'])) {
        $attributes[] = 'aria-label="' . esc_attr((string) $action['aria_label']) . '"';
    }

    if (!empty($action['extra_attrs']) && is_array($action['extra_attrs'])) {
        foreach ($action['extra_attrs'] as $attr_name => $attr_value) {
            if (null === $attr_value || '' === $attr_value) {
                continue;
            }
            $attributes[] = sprintf('%s="%s"', esc_attr((string) $attr_name), esc_attr((string) $attr_value));
        }
    }

    return implode(' ', $attributes);
};

$bbai_render_action_link = static function (array $action, string $class_name, string $data_attribute) use ($bbai_build_action_attrs): void {
    $label = isset($action['label']) ? (string) $action['label'] : '';
    $attrs = '' !== $label ? $bbai_build_action_attrs($action) : 'href="#"';
    ?>
    <a
        <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are assembled from escaped values. ?>
        class="<?php echo esc_attr($class_name); ?>"
        <?php echo esc_attr($data_attribute); ?>
        <?php echo '' !== $label ? '' : 'hidden'; ?>
    ><?php echo esc_html($label); ?></a>
    <?php
};

$bbai_format_reset_timing = static function (?int $days_until_reset, string $fallback = ''): string {
    if (null !== $days_until_reset) {
        return sprintf(
            _n('Resets in %s day', 'Resets in %s days', $days_until_reset, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($days_until_reset)
        );
    }

    return '' !== $fallback ? $fallback : __('Resets monthly', 'beepbeep-ai-alt-text-generator');
};

$bbai_format_reset_value = static function (?int $days_until_reset, string $fallback = ''): string {
    if (null !== $days_until_reset) {
        return sprintf(
            _n('%s day', '%s days', $days_until_reset, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($days_until_reset)
        );
    }

    if ('' !== $fallback) {
        return preg_replace('/^Resets\s+/i', '', $fallback) ?: $fallback;
    }

    return __('Monthly', 'beepbeep-ai-alt-text-generator');
};

$bbai_format_percentage_label = static function (float $value): string {
    $numeric_value = is_finite($value) ? max(0.0, $value) : 0.0;

    if ($numeric_value >= 100) {
        return '100';
    }

    if ($numeric_value < 0.01 && $numeric_value > 0) {
        return '<0.01';
    }

    if ($numeric_value < 0.1) {
        return number_format_i18n($numeric_value, 2);
    }

    if ($numeric_value < 10) {
        return number_format_i18n($numeric_value, 1);
    }

    return number_format_i18n($numeric_value, 0);
};

$bbai_build_donut_background = static function (int $optimized, int $weak, int $missing, int $total): string {
    if ($total <= 0) {
        return 'conic-gradient(#e2e8f0 0deg 360deg)';
    }

    $optimized_angle = (360 * $optimized) / $total;
    $weak_angle = (360 * $weak) / $total;
    $optimized_end = max(0, min(360, $optimized_angle));
    $weak_end = max($optimized_end, min(360, $optimized_end + $weak_angle));

    return sprintf(
        'conic-gradient(#22c55e 0deg %.3Fdeg, #f59e0b %.3Fdeg %.3Fdeg, #ef4444 %.3Fdeg 360deg)',
        $optimized_end,
        $optimized_end,
        $weak_end,
        $weak_end
    );
};

$bbai_format_last_scan = static function (int $timestamp): string {
    $now = (int) current_time('timestamp');
    if ($timestamp <= 0 || $timestamp > $now) {
        return '';
    }

    $diff = $now - $timestamp;
    if ($diff < DAY_IN_SECONDS * 7) {
        return sprintf(
            __('Last scan %s ago', 'beepbeep-ai-alt-text-generator'),
            human_time_diff($timestamp, $now)
        );
    }

    return sprintf(
        __('Last scan %s', 'beepbeep-ai-alt-text-generator'),
        date_i18n(get_option('date_format'), $timestamp)
    );
};

$bbai_is_authenticated = (bool) ($bbai_is_authenticated ?? false);
$bbai_has_license = (bool) ($bbai_has_license ?? false);
$bbai_has_registered_user = (bool) ($bbai_has_registered_user ?? false);
$bbai_has_connected_account = (bool) ($bbai_has_connected_account ?? $bbai_has_registered_user);
$bbai_is_guest_trial = isset($bbai_is_anonymous_trial)
    ? (bool) $bbai_is_anonymous_trial
    : !$bbai_has_connected_account;
$bbai_guest_trial_status = $bbai_is_guest_trial ? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status() : [];

if ($bbai_has_connected_account || $bbai_is_guest_trial) :
    $bbai_plan_data = $bbai_is_guest_trial ? [] : Plan_Helpers::get_plan_data();
    $bbai_is_agency = !$bbai_is_guest_trial && !empty($bbai_plan_data['is_agency']);
    $bbai_is_premium = !$bbai_is_guest_trial && (!empty($bbai_plan_data['is_pro']) || $bbai_is_agency);
    $bbai_auth_state = isset($bbai_usage_stats['auth_state']) && is_string($bbai_usage_stats['auth_state']) && '' !== trim($bbai_usage_stats['auth_state'])
        ? sanitize_key($bbai_usage_stats['auth_state'])
        : ($bbai_is_guest_trial ? 'anonymous' : 'authenticated');
    $bbai_quota_type = isset($bbai_usage_stats['quota_type']) && is_string($bbai_usage_stats['quota_type']) && '' !== trim($bbai_usage_stats['quota_type'])
        ? sanitize_key($bbai_usage_stats['quota_type'])
        : ($bbai_is_guest_trial ? 'trial' : ($bbai_is_premium ? 'paid' : 'monthly_account'));
    $bbai_is_anonymous_trial = ('anonymous' === $bbai_auth_state) || ('trial' === $bbai_quota_type) || $bbai_is_guest_trial;
    $bbai_free_plan_offer = max(0, (int) ($bbai_usage_stats['free_plan_offer'] ?? $bbai_guest_trial_status['free_plan_offer'] ?? 50));

    $bbai_credits_used = max(0, (int) ($bbai_usage_stats['credits_used'] ?? $bbai_usage_stats['creditsUsed'] ?? $bbai_usage_stats['used'] ?? 0));
    $bbai_credits_total = max(1, (int) ($bbai_usage_stats['credits_total'] ?? $bbai_usage_stats['creditsTotal'] ?? $bbai_usage_stats['limit'] ?? 50));
    $bbai_credits_remaining = max(0, (int) ($bbai_usage_stats['credits_remaining'] ?? $bbai_usage_stats['creditsRemaining'] ?? $bbai_usage_stats['remaining'] ?? 0));

    if ($bbai_credits_used > $bbai_credits_total) {
        $bbai_credits_used = $bbai_credits_total;
        $bbai_credits_remaining = 0;
    }

    $bbai_low_credit_threshold = max(
        0,
        (int) ($bbai_usage_stats['low_credit_threshold'] ?? ($bbai_is_anonymous_trial
            ? min(2, max(1, $bbai_credits_total - 1))
            : BBAI_BANNER_LOW_CREDITS_THRESHOLD))
    );
    $bbai_quota_state = isset($bbai_usage_stats['quota_state']) && is_string($bbai_usage_stats['quota_state']) && '' !== trim($bbai_usage_stats['quota_state'])
        ? sanitize_key($bbai_usage_stats['quota_state'])
        : ($bbai_credits_remaining <= 0
            ? 'exhausted'
            : ($bbai_credits_remaining <= $bbai_low_credit_threshold
                ? 'near_limit'
                : 'active'));
    $bbai_signup_required = !empty($bbai_usage_stats['signup_required']) || ($bbai_is_anonymous_trial && 'exhausted' === $bbai_quota_state);
    $bbai_upgrade_required = !empty($bbai_usage_stats['upgrade_required']);

    $bbai_coverage = (isset($this) && method_exists($this, 'get_alt_text_coverage_scan')) ? $this->get_alt_text_coverage_scan(false) : [];
    $bbai_attn = bbai_get_attention_counts(
        (!empty($bbai_coverage) && isset($bbai_coverage['total_images'])) ? $bbai_coverage : null
    );
    $bbai_missing_count   = $bbai_attn['missing'];
    $bbai_weak_count      = $bbai_attn['needs_review'];
    $bbai_optimized_count = $bbai_attn['optimized_count'];
    $bbai_with_alt_count  = max(0, $bbai_optimized_count + $bbai_weak_count);
    $bbai_total_images    = max(
        0,
        max(
            (int) ($bbai_stats['total'] ?? 0),
            $bbai_attn['total_images'],
            $bbai_optimized_count + $bbai_missing_count + $bbai_weak_count
        )
    );

    $bbai_reset_raw = (string) ($bbai_usage_stats['reset_date'] ?? '');
    $bbai_reset_timestamp_raw = isset($bbai_usage_stats['reset_timestamp']) ? (int) $bbai_usage_stats['reset_timestamp'] : 0;
    $bbai_reset_ts = $bbai_reset_timestamp_raw > 0 ? $bbai_reset_timestamp_raw : ($bbai_reset_raw !== '' ? strtotime($bbai_reset_raw) : false);
    $bbai_has_reset_timestamp = is_numeric($bbai_reset_ts) && (int) $bbai_reset_ts > 0;

    $bbai_days_until_reset = null;
    if ($bbai_has_reset_timestamp) {
        $bbai_days_until_reset = Usage_Tracker::calculate_days_until_reset((int) $bbai_reset_ts, (int) current_time('timestamp'));
    } elseif (isset($bbai_usage_stats['days_until_reset']) && is_numeric($bbai_usage_stats['days_until_reset'])) {
        $bbai_days_until_reset = max(0, (int) $bbai_usage_stats['days_until_reset']);
    }

    $bbai_reset_timing = $bbai_is_anonymous_trial
        ? sprintf(
            /* translators: %d: free account generations per month. */
            __('Create a free account for %d generations per month', 'beepbeep-ai-alt-text-generator'),
            $bbai_free_plan_offer
        )
        : $bbai_format_reset_timing(
            $bbai_days_until_reset,
            ($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
                ? sprintf(__('Resets %s', 'beepbeep-ai-alt-text-generator'), date_i18n(get_option('date_format'), $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
                : __('Resets monthly', 'beepbeep-ai-alt-text-generator')
        );

    $bbai_credits_reset_line = $bbai_is_anonymous_trial
        ? sprintf(
            /* translators: %d: free account generations per month. */
            __('Create a free account to keep your progress and unlock %d monthly generations', 'beepbeep-ai-alt-text-generator'),
            $bbai_free_plan_offer
        )
        : (($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
            ? sprintf(__('Credits reset %s', 'beepbeep-ai-alt-text-generator'), date_i18n('F j, Y', $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp')))
            : __('Credits reset monthly', 'beepbeep-ai-alt-text-generator'));
    $bbai_plan_label = $bbai_is_anonymous_trial
        ? __('Free trial', 'beepbeep-ai-alt-text-generator')
        : (isset($bbai_usage_stats['plan_label']) && is_string($bbai_usage_stats['plan_label']) && '' !== trim($bbai_usage_stats['plan_label'])
            ? sanitize_text_field($bbai_usage_stats['plan_label'])
            : ($bbai_is_premium ? __('Growth plan', 'beepbeep-ai-alt-text-generator') : __('Free plan', 'beepbeep-ai-alt-text-generator')));

    $bbai_last_scan_timestamp = isset($bbai_coverage['scanned_at']) ? max(0, (int) $bbai_coverage['scanned_at']) : 0;
    $bbai_has_scan_history = $bbai_last_scan_timestamp > 0;
    $bbai_has_scan_results = $bbai_total_images > 0 || $bbai_missing_count > 0 || $bbai_weak_count > 0 || $bbai_optimized_count > 0;

    // Phase 13 — activation / FTUE (pre–first successful generation).
    $bbai_current_user_id       = get_current_user_id();
    $bbai_first_alt_ts          = $bbai_current_user_id ? (int) get_user_meta($bbai_current_user_id, 'bbai_telemetry_first_alt_at', true) : 0;
    $bbai_stats_generated       = (int) ($bbai_stats['generated'] ?? 0);
    $bbai_has_ever_generated_alt = $bbai_credits_used > 0 || $bbai_first_alt_ts > 0 || $bbai_stats_generated > 0;
    $bbai_ftue_session_images    = $bbai_current_user_id
        ? (int) get_user_meta($bbai_current_user_id, '_bbai_telemetry_session_images_' . gmdate('Ymd'), true)
        : 0;
    $bbai_attn_total_need        = $bbai_missing_count + $bbai_weak_count;
    $bbai_coverage_percent = $bbai_total_images > 0 ? (int) round(($bbai_optimized_count / $bbai_total_images) * 100) : 0;
    // Match FTUE: credits spent but no visible library rows yet (optimized or needs-review).
    $bbai_coverage_processing = ($bbai_credits_used > 0 && $bbai_optimized_count === 0 && $bbai_weak_count === 0);
    $bbai_coverage_processing_ui = $bbai_coverage_processing && $bbai_total_images > 0;
    $bbai_coverage_processing_msg = __('Processing your first results — this usually takes a few seconds', 'beepbeep-ai-alt-text-generator');
    $bbai_coverage_motivation = '';
    $bbai_coverage_badge = '';
    if ($bbai_total_images > 0) {
        if ($bbai_coverage_percent >= 100) {
            $bbai_coverage_motivation = __('Fully optimised 🚀', 'beepbeep-ai-alt-text-generator');
            $bbai_coverage_badge = __('All images optimised 🎉', 'beepbeep-ai-alt-text-generator');
        } elseif ($bbai_coverage_percent >= 95) {
            $bbai_coverage_motivation = __('Almost complete 🎯', 'beepbeep-ai-alt-text-generator');
            $bbai_coverage_badge = __('1 image away from 100%', 'beepbeep-ai-alt-text-generator');
        } elseif ($bbai_coverage_percent < 80 && ! $bbai_coverage_processing) {
            $bbai_coverage_motivation = __('You’re leaving rankings on the table', 'beepbeep-ai-alt-text-generator');
        }
    }
    $bbai_usage_percent = $bbai_credits_total > 0 ? (int) round(($bbai_credits_used / $bbai_credits_total) * 100) : 0;
    $bbai_growth_capacity = $bbai_is_premium ? max(1000, $bbai_credits_total) : 1000;
    $bbai_growth_usage_percent = max(0, min(100, ($bbai_credits_used / max(1, $bbai_growth_capacity)) * 100));
    $bbai_growth_usage_display = $bbai_format_percentage_label($bbai_growth_usage_percent);
    $bbai_growth_usage_line = $bbai_is_anonymous_trial
        ? sprintf(
            /* translators: %d: free account generations per month. */
            __('A free account unlocks %d generations per month.', 'beepbeep-ai-alt-text-generator'),
            $bbai_free_plan_offer
        )
        : ($bbai_is_premium
        ? sprintf(
            __('You are using %s%% of Growth capacity this month.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        )
        : sprintf(
            __('On Growth, this usage would be %s%% of monthly capacity.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        ));
    $bbai_donut_background = $bbai_build_donut_background($bbai_optimized_count, $bbai_weak_count, $bbai_missing_count, $bbai_total_images);
    $bbai_donut_initial_background = $bbai_total_images > 0
        ? 'conic-gradient(#e2e8f0 0deg 360deg)'
        : $bbai_donut_background;

    $isHealthy = $bbai_missing_count === 0 && $bbai_weak_count === 0 && $bbai_total_images > 0;
    $isProPlan = $bbai_is_premium;
    $missingCount = $bbai_missing_count;
    $weakCount = $bbai_weak_count;
    $optimizedCount = $bbai_optimized_count;
    $totalImages = $bbai_total_images;
    $coveragePercent = $bbai_coverage_percent;
    $creditsUsed = $bbai_credits_used;
    $creditsLimit = $bbai_credits_total;
    $creditsRemaining = $bbai_credits_remaining;
    $daysUntilReset = null !== $bbai_days_until_reset ? (int) $bbai_days_until_reset : 0;
    $usagePercent = $bbai_usage_percent;
    $isLowCredits = $creditsRemaining > 0 && $creditsRemaining <= $bbai_low_credit_threshold;
    $isOutOfCredits = $creditsRemaining === 0;
    $hasScanHistory = $bbai_has_scan_history;
    $isFirstRun = !$hasScanHistory || $totalImages === 0;
    $bbai_product_state_model = Dashboard_State::resolve(
        [
            'is_guest_trial' => $bbai_is_anonymous_trial,
            'is_premium'     => $isProPlan,
            'usage_stats'    => $bbai_usage_stats,
            'trial_status'   => $bbai_guest_trial_status,
            'missing_count'  => $missingCount,
            'weak_count'     => $weakCount,
        ]
    );

    $bbai_ftue_pre_scan  = !$bbai_is_guest_trial && !$isOutOfCredits && !$hasScanHistory && !$bbai_has_ever_generated_alt;
    $bbai_ftue_post_scan = !$bbai_is_guest_trial && !$isOutOfCredits && $hasScanHistory && !$bbai_has_ever_generated_alt;
    $bbai_ftue_show_hero = $bbai_ftue_pre_scan || $bbai_ftue_post_scan;

    /**
     * Activation-first onboarding dashboard: suppress operational command grid until first ALT generation.
     * Default matches FTUE hero eligibility; narrow with the filter if needed.
     */
    $bbai_onboarding_first_open = (bool) apply_filters(
        'bbai_dashboard_onboarding_first_open',
        $bbai_ftue_show_hero,
        [
            'user_id'             => $bbai_current_user_id,
            'has_scan_history'    => $bbai_has_scan_history,
            'has_generated_alt'   => $bbai_has_ever_generated_alt,
            'is_out_of_credits'   => $isOutOfCredits,
            'credits_used'        => $bbai_credits_used,
            'stats_generated'     => $bbai_stats_generated,
            'telemetry_first_alt' => $bbai_first_alt_ts,
        ]
    );

    $bbai_library_url = add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'));
    $bbai_optimized_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'optimized'], admin_url('admin.php'));
    $bbai_needs_review_library_url = bbai_alt_library_needs_review_url();
    $bbai_missing_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
    $bbai_usage_url = $bbai_is_anonymous_trial
        ? admin_url('admin.php?page=bbai')
        : admin_url('admin.php?page=bbai-credit-usage');
    $bbai_settings_url = $bbai_is_anonymous_trial
        ? admin_url('admin.php?page=bbai')
        : admin_url('admin.php?page=bbai-settings');
    $bbai_guide_url = admin_url('admin.php?page=bbai-guide');

    $bbai_status_detail = '';
    if ($isFirstRun) {
        $bbai_status_detail = __('Run your first scan to see coverage and missing ALT text.', 'beepbeep-ai-alt-text-generator');
    } elseif ($bbai_coverage_processing_ui) {
        $bbai_status_detail = __('This count updates after ALT text is saved — it usually takes a few seconds.', 'beepbeep-ai-alt-text-generator');
    } elseif ($missingCount > 0) {
        $bbai_status_detail = 1 === $missingCount
            ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: %s: number of images */
                __('%s images away from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($missingCount)
            );
    } elseif ($weakCount > 0) {
        $bbai_status_detail = sprintf(
            _n('%s description needs review.', '%s descriptions need review.', $weakCount, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($weakCount)
        );
    } elseif ($totalImages > 0) {
        $bbai_status_detail = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
    }

    $bbai_dashboard_primary_missing = $missingCount > 0 && !$isOutOfCredits;
    $bbai_dashboard_primary_review = 0 === $missingCount && $weakCount > 0 && !$isOutOfCredits && $bbai_has_scan_results;
    $bbai_dashboard_show_primary_action = $bbai_dashboard_primary_missing || $bbai_dashboard_primary_review;

    if ($bbai_dashboard_primary_missing && ! $bbai_coverage_processing_ui) {
        $bbai_status_detail = '';
    }
    if ($bbai_dashboard_primary_review) {
        $bbai_status_detail = '';
    }

    if ($bbai_is_anonymous_trial) {
        $bbai_trial_signup_action = $bbai_build_action(
            ('exhausted' === $bbai_quota_state)
                ? __('Create free account to continue', 'beepbeep-ai-alt-text-generator')
                : __('Create free account', 'beepbeep-ai-alt-text-generator'),
            [
                'action' => 'show-auth-modal',
                'aria_label' => __('Create free account', 'beepbeep-ai-alt-text-generator'),
                'extra_attrs' => [
                    'data-auth-tab' => 'register',
                ],
            ]
        );
        $bbai_trial_continue_action = $bbai_build_action();
        $bbai_guest_primary_mode = (string) ($bbai_product_state_model['cta']['primary_mode'] ?? 'review');

        if ('generate' === $bbai_guest_primary_mode) {
            $bbai_trial_continue_action = $bbai_build_action(
                bbai_copy_cta_generate_missing_images(),
                [
                    'action' => 'generate-missing',
                    'bbai_action' => 'generate_missing',
                    'aria_label' => bbai_copy_cta_generate_missing_images(),
                ]
            );
        } elseif ('reoptimize' === $bbai_guest_primary_mode) {
            $bbai_trial_continue_action = $bbai_build_action(
                bbai_copy_cta_improve_alt(),
                [
                    'action' => 'regenerate-all',
                    'aria_label' => bbai_copy_cta_improve_alt(),
                    'extra_attrs' => [
                        'data-bbai-regenerate-scope' => 'needs-review',
                        'data-bbai-generation-source' => 'regenerate-weak',
                    ],
                ]
            );
        } else {
            $bbai_trial_continue_action = $bbai_build_action(
                __('Continue in ALT Library', 'beepbeep-ai-alt-text-generator'),
                [
                    'href' => $bbai_library_url,
                    'aria_label' => __('Continue in ALT Library', 'beepbeep-ai-alt-text-generator'),
                ]
            );
        }

        if ('create_account' === $bbai_guest_primary_mode) {
            $bbai_plan_primary_action = $bbai_trial_signup_action;
            $bbai_plan_secondary_action = $bbai_trial_continue_action;
        } else {
            $bbai_plan_primary_action = $bbai_trial_continue_action;
            $bbai_plan_secondary_action = $bbai_trial_signup_action;
        }
    } else {
        $bbai_plan_primary_action = $bbai_build_action(
            $isProPlan ? __('Review usage', 'beepbeep-ai-alt-text-generator') : __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator'),
            $isProPlan
                ? [
                    'href' => $bbai_usage_url,
                    'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                ]
                : [
                    'action' => 'show-upgrade-modal',
                    'aria_label' => __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator'),
                ]
        );

        $bbai_plan_secondary_action = !$isProPlan
            ? $bbai_build_action(
                __('Review usage', 'beepbeep-ai-alt-text-generator'),
                [
                    'href' => $bbai_usage_url,
                    'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                ]
            )
            : $bbai_build_action();
    }

    $bbai_dashboard_state = [
        'isHealthy' => $isHealthy,
        'isProPlan' => $isProPlan,
        'missingCount' => $missingCount,
        'weakCount' => $weakCount,
        'optimizedCount' => $optimizedCount,
        'totalImages' => $totalImages,
        'coveragePercent' => $coveragePercent,
        'creditsUsed' => $creditsUsed,
        'creditsLimit' => $creditsLimit,
        'creditsRemaining' => $creditsRemaining,
        'authState' => $bbai_auth_state,
        'quotaType' => $bbai_quota_type,
        'quotaState' => $bbai_quota_state,
        'signupRequired' => $bbai_signup_required,
        'upgradeRequired' => $bbai_upgrade_required,
        'freePlanOffer' => $bbai_free_plan_offer,
        'lowCreditThreshold' => $bbai_low_credit_threshold,
        'daysUntilReset' => $daysUntilReset,
        'usagePercent' => $usagePercent,
        'isLowCredits' => $isLowCredits,
        'isOutOfCredits' => $isOutOfCredits,
        'hasScanHistory' => $hasScanHistory,
        'hasScanResults' => $bbai_has_scan_results,
        'isFirstRun' => $isFirstRun,
        'lastScanTimestamp' => $bbai_last_scan_timestamp,
        'lastScanLine' => $bbai_format_last_scan($bbai_last_scan_timestamp),
        'planLabel' => $bbai_plan_label,
        'resetTiming' => $bbai_reset_timing,
        'compactResetTiming' => $bbai_format_reset_value($bbai_days_until_reset, $bbai_reset_timing),
        'creditsResetLine' => $bbai_credits_reset_line,
        'usageLine' => $bbai_is_anonymous_trial
            ? sprintf(
                __('%1$s / %2$s free trial generations used', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($creditsUsed),
                number_format_i18n($creditsLimit)
            )
            : sprintf(
                __('%1$s / %2$s used', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($creditsUsed),
                number_format_i18n($creditsLimit)
            ),
        'remainingLine' => $bbai_is_anonymous_trial
            ? sprintf(
                _n('%s trial generation remaining', '%s trial generations remaining', $creditsRemaining, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($creditsRemaining)
            )
            : sprintf(
                _n('%s image left this month', '%s images left this month', $creditsRemaining, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($creditsRemaining)
            ),
        'statusSummary' => $totalImages > 0
            ? sprintf(
                _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', $totalImages, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($optimizedCount),
                number_format_i18n($totalImages)
            )
            : __('No images found in your media library', 'beepbeep-ai-alt-text-generator'),
        'statusDetail' => $bbai_status_detail,
        'libraryUrl' => $bbai_library_url,
        'missingLibraryUrl' => $bbai_missing_library_url,
        'needsReviewLibraryUrl' => $bbai_needs_review_library_url,
        'usageUrl' => $bbai_usage_url,
        'settingsUrl' => $bbai_settings_url,
        'guideUrl' => $bbai_guide_url,
        'planPrimaryAction' => $bbai_plan_primary_action,
        'planSecondaryAction' => $bbai_plan_secondary_action,
        'hasEverGeneratedAlt' => $bbai_has_ever_generated_alt,
        'onboardingFirstOpen' => $bbai_onboarding_first_open,
        'coverageProcessing' => $bbai_coverage_processing,
        'isGuestTrial' => $bbai_is_anonymous_trial,
        'productStateModel' => $bbai_product_state_model,
    ];

    $bbai_banner_snapshot       = bbai_banner_snapshot_from_dashboard_state($bbai_dashboard_state);
    $bbai_primary_banner_state  = bbai_resolve_top_banner($bbai_banner_snapshot, BBAI_BANNER_CTX_DASHBOARD);
    $bbai_dashboard_banner_slot = bbai_get_active_banner_slot_from_state($bbai_primary_banner_state);

    $bbai_dashboard_command_hero = bbai_banner_build_command_hero(
        BBAI_BANNER_CTX_DASHBOARD,
        $bbai_banner_snapshot,
        [
            'aria_label'         => __('Dashboard summary', 'beepbeep-ai-alt-text-generator'),
            'show_hero_loop'     => true,
            'icon_wrapper_attrs' => ['data-bbai-hero-icon' => '1'],
            'headline_attrs'     => ['data-bbai-hero-headline' => '1'],
            'section_data_attrs' => [
                'data-bbai-active-banner-slot'   => (string) ( $bbai_dashboard_banner_slot ?? '' ),
                'data-bbai-dashboard-hero'       => '1',
                'data-bbai-banner-used'          => (string) $creditsUsed,
                'data-bbai-banner-limit'         => (string) $creditsLimit,
                'data-bbai-banner-remaining'     => (string) $creditsRemaining,
                'data-bbai-auth-state'           => (string) $bbai_auth_state,
                'data-bbai-quota-type'           => (string) $bbai_quota_type,
                'data-bbai-quota-state'          => (string) $bbai_quota_state,
                'data-bbai-signup-required'      => $bbai_signup_required ? '1' : '0',
                'data-bbai-free-plan-offer'      => (string) $bbai_free_plan_offer,
                'data-bbai-low-credit-threshold' => (string) $bbai_low_credit_threshold,
                'data-bbai-banner-library-url'   => $bbai_library_url,
                'data-bbai-banner-missing-count' => (string) $missingCount,
                'data-bbai-banner-weak-count'    => (string) $weakCount,
                'data-bbai-banner-days-left'     => (string) (int) ($bbai_dashboard_state['daysUntilReset'] ?? 0),
                'data-bbai-banner-settings-url'  => $bbai_settings_url,
            ],
        ]
    );
    $bbai_dashboard_command_hero['banner_logical_state'] = $bbai_primary_banner_state;

    $bbai_retention_strip = null;
    if ($bbai_current_user_id) {
        bbai_retention_schedule_snapshot_update($bbai_current_user_id, $bbai_total_images);
        $bbai_retention_strip = bbai_retention_build_strip_model(
            [
                'user_id'                  => $bbai_current_user_id,
                'ftue_show_hero'           => $bbai_ftue_show_hero,
                'ftue_pre_scan'            => $bbai_ftue_pre_scan,
                'has_scan_history'         => $bbai_has_scan_history,
                'is_out_of_credits'        => $isOutOfCredits,
                'is_pro'                   => $isProPlan,
                'missing'                  => $missingCount,
                'weak'                     => $weakCount,
                'optimized'                => $optimizedCount,
                'total'                    => $totalImages,
                'coverage_pct'             => $coveragePercent,
                'credits_used'             => $creditsUsed,
                'missing_library_url'      => $bbai_missing_library_url,
                'needs_review_library_url' => $bbai_needs_review_library_url,
                'library_url'              => $bbai_library_url,
            ]
        );
    }

    $bbai_primary_action = 'scan';
    if ($isOutOfCredits) {
        $bbai_primary_action = $bbai_is_anonymous_trial ? 'signup' : 'upgrade';
    } elseif ($missingCount > 0) {
        $bbai_primary_action = 'generate_missing';
    } elseif ($weakCount > 0) {
        $bbai_primary_action = 'review_weak';
    } elseif ($bbai_has_scan_results) {
        $bbai_primary_action = 'review_library';
    }

    $bbai_review_prompt_url = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/?rate=5#new-post';
    $bbai_plan_primary_class = $isProPlan
        ? 'bbai-command-action bbai-command-action--secondary'
        : 'bbai-command-action bbai-command-action--primary';
    $bbai_plan_secondary_class = $isProPlan
        ? 'bbai-command-action bbai-command-action--tertiary'
        : 'bbai-command-action bbai-command-action--secondary';
    ?>

    <div
        id="bbai-dashboard-main"
        class="bbai-dashboard bbai-container<?php echo $bbai_onboarding_first_open ? ' bbai-dashboard--onboarding-first-open' : ''; ?>"
        data-bbai-dashboard-container
        data-bbai-dashboard-root="1"
        data-bbai-dashboard-state-root="1"
        data-bbai-dashboard-state="<?php echo esc_attr((string) ($bbai_product_state_model['state'] ?? '')); ?>"
        data-bbai-dashboard-base-state="<?php echo esc_attr((string) ($bbai_product_state_model['base_state'] ?? '')); ?>"
        data-bbai-dashboard-runtime-state="<?php echo esc_attr((string) ($bbai_product_state_model['runtime_state'] ?? 'idle')); ?>"
        data-bbai-onboarding-first-open="<?php echo esc_attr($bbai_onboarding_first_open ? '1' : '0'); ?>"
        data-bbai-missing-count="<?php echo esc_attr($missingCount); ?>"
        data-bbai-weak-count="<?php echo esc_attr($weakCount); ?>"
        data-bbai-optimized-count="<?php echo esc_attr($optimizedCount); ?>"
        data-bbai-total-count="<?php echo esc_attr($totalImages); ?>"
        data-bbai-generated-count="<?php echo esc_attr(max(0, (int) ($bbai_stats['generated'] ?? $optimizedCount))); ?>"
        data-bbai-credits-used="<?php echo esc_attr($creditsUsed); ?>"
        data-bbai-credits-total="<?php echo esc_attr($creditsLimit); ?>"
        data-bbai-credits-remaining="<?php echo esc_attr($creditsRemaining); ?>"
        data-bbai-credits-reset-line="<?php echo esc_attr($bbai_credits_reset_line); ?>"
        data-bbai-auth-state="<?php echo esc_attr($bbai_auth_state); ?>"
        data-bbai-quota-type="<?php echo esc_attr($bbai_quota_type); ?>"
        data-bbai-quota-state="<?php echo esc_attr($bbai_quota_state); ?>"
        data-bbai-signup-required="<?php echo esc_attr($bbai_signup_required ? '1' : '0'); ?>"
        data-bbai-upgrade-required="<?php echo esc_attr($bbai_upgrade_required ? '1' : '0'); ?>"
        data-bbai-free-plan-offer="<?php echo esc_attr($bbai_free_plan_offer); ?>"
        data-bbai-low-credit-threshold="<?php echo esc_attr($bbai_low_credit_threshold); ?>"
        data-bbai-plan-label="<?php echo esc_attr($bbai_plan_label); ?>"
        data-bbai-is-premium="<?php echo esc_attr($isProPlan ? '1' : '0'); ?>"
        data-bbai-has-scan-results="<?php echo esc_attr($bbai_has_scan_results ? '1' : '0'); ?>"
        data-bbai-last-scan-ts="<?php echo esc_attr($bbai_last_scan_timestamp); ?>"
        data-bbai-primary-action="<?php echo esc_attr($bbai_primary_action); ?>"
        data-bbai-library-url="<?php echo esc_url($bbai_library_url); ?>"
        data-bbai-missing-library-url="<?php echo esc_url($bbai_missing_library_url); ?>"
        data-bbai-needs-review-library-url="<?php echo esc_url($bbai_needs_review_library_url); ?>"
        data-bbai-settings-url="<?php echo esc_url($bbai_settings_url); ?>"
        data-bbai-usage-url="<?php echo esc_url($bbai_usage_url); ?>"
        data-bbai-guide-url="<?php echo esc_url($bbai_guide_url); ?>"
        data-bbai-primary-banner-state="<?php echo esc_attr($bbai_primary_banner_state); ?>"
        data-bbai-active-banner-slot="<?php echo esc_attr((string) (bbai_get_active_banner_slot_from_state($bbai_primary_banner_state) ?? '')); ?>"
        data-bbai-coverage-processing="<?php echo esc_attr($bbai_coverage_processing ? '1' : '0'); ?>"
        data-bbai-is-guest-trial="<?php echo esc_attr($bbai_is_anonymous_trial ? '1' : '0'); ?>"
        data-bbai-trial-limit="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['limit'] ?? 0)); ?>"
        data-bbai-trial-used="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['used'] ?? 0)); ?>"
        data-bbai-trial-remaining="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['remaining'] ?? 0)); ?>"
        data-bbai-trial-exhausted="<?php echo esc_attr(!empty($bbai_product_state_model['trial']['exhausted']) ? '1' : '0'); ?>"
        data-bbai-locked-cta-mode="<?php echo esc_attr((string) ($bbai_product_state_model['cta']['locked_mode'] ?? '')); ?>"
        data-bbai-free-account-monthly-limit="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['monthly_free_limit'] ?? $bbai_free_plan_offer)); ?>"
    >
        <div id="bbai-limit-state-root" class="bbai-limit-state-root" hidden></div>

        <?php
        bbai_ui_render( 'bbai-banner', [ 'command_hero' => $bbai_dashboard_command_hero ] );

        if (!empty($bbai_product_state_model['flags']['show_exhausted_upgrade_wall'])) {
            $bbai_trial_upgrade_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/trial-exhausted-upgrade-wall.php';
            if (is_readable($bbai_trial_upgrade_partial)) {
                $bbai_trial_upgrade_context = 'dashboard';
                $bbai_trial_upgrade_missing_count = $missingCount;
                $bbai_trial_upgrade_weak_count = $weakCount;
                include $bbai_trial_upgrade_partial;
            }
        }

        if ($bbai_ftue_show_hero) {
            $bbai_ftue_phase = $bbai_ftue_pre_scan ? 'pre_scan' : 'post_scan';
            $bbai_onboarding_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-onboarding-first-open.php';
            if (file_exists($bbai_onboarding_partial)) {
                include $bbai_onboarding_partial;
            }
        }

        $bbai_retention_partial      = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-retention-strip.php';
        ?>

        <?php if (!$bbai_onboarding_first_open) : ?>
        <div class="bbai-dashboard-command bbai-top-section-stack">
                <?php if ($bbai_dashboard_show_primary_action && !bbai_banner_is_priority_slot_active($bbai_dashboard_banner_slot)) : ?>
                <article
                    class="bbai-dashboard-card bbai-command-card bbai-command-card--dashboard-primary"
                    data-bbai-dashboard-primary-action-card="1"
                    aria-labelledby="bbai-dashboard-primary-title"
                >
                    <header class="bbai-command-card__header">
                        <div>
                            <p class="bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php esc_html_e('Next step', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <h2 id="bbai-dashboard-primary-title" class="bbai-command-card__title bbai-section-title bbai-card-title">
                                <?php
                                if ($bbai_dashboard_primary_missing) {
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: number of images */
                                            _n(
                                                '%s image missing ALT text',
                                                '%s images missing ALT text',
                                                $missingCount,
                                                'beepbeep-ai-alt-text-generator'
                                            ),
                                            number_format_i18n($missingCount)
                                        )
                                    );
                                } else {
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: number of descriptions */
                                            _n(
                                                '%s description needs review.',
                                                '%s descriptions need review.',
                                                $weakCount,
                                                'beepbeep-ai-alt-text-generator'
                                            ),
                                            number_format_i18n($weakCount)
                                        )
                                    );
                                }
                                ?>
                            </h2>
                        </div>
                    </header>
                    <div class="bbai-command-plan__actions">
                        <?php if ($bbai_dashboard_primary_missing) : ?>
                            <a
                                href="#"
                                class="bbai-command-action bbai-command-action--primary bbai-btn bbai-btn-primary"
                                data-bbai-dashboard-primary-cta="1"
                                data-action="generate-missing"
                                data-bbai-action="generate_missing"
                                data-bbai-starter-cap="10"
                                aria-label="<?php echo esc_attr(bbai_copy_cta_generate_missing_images()); ?>"
                            ><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></a>
                        <?php else : ?>
                            <a
                                href="#"
                                class="bbai-command-action bbai-command-action--primary bbai-btn bbai-btn-primary"
                                data-bbai-dashboard-primary-cta="1"
                                data-action="regenerate-all"
                                data-bbai-regenerate-scope="needs-review"
                                data-bbai-generation-source="regenerate-weak"
                                aria-label="<?php echo esc_attr(bbai_copy_cta_improve_alt()); ?>"
                            ><?php echo esc_html(bbai_copy_cta_improve_alt()); ?></a>
                            <a
                                href="<?php echo esc_url($bbai_needs_review_library_url); ?>"
                                class="bbai-command-action bbai-command-action--secondary bbai-btn bbai-btn-secondary"
                                data-bbai-dashboard-primary-library="1"
                                data-bbai-navigation="review-results"
                            ><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endif; ?>

            <div class="bbai-dashboard-command__grid">
                <article class="bbai-dashboard-card bbai-command-card bbai-command-card--status<?php echo ($bbai_coverage_percent >= 100 && $bbai_total_images > 0) ? ' bbai-command-card--coverage-complete' : ''; ?><?php echo $bbai_coverage_processing_ui ? ' bbai-command-card--coverage-processing' : ''; ?>" data-bbai-dashboard-status-card="1" aria-labelledby="bbai-status-title">
                    <header class="bbai-command-card__header">
                        <div>
                            <p class="bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php esc_html_e('Library status', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <h2 id="bbai-status-title" class="bbai-command-card__title bbai-section-title bbai-card-title"><?php esc_html_e('Coverage', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        </div>
                        <div class="bbai-command-card__meta-group">
                            <p class="bbai-command-card__meta" data-bbai-status-last-scan<?php echo '' !== ($bbai_dashboard_state['lastScanLine'] ?? '') ? '' : ' hidden'; ?>>
                                <?php echo esc_html($bbai_dashboard_state['lastScanLine'] ?? ''); ?>
                            </p>
                            <button
                                type="button"
                                class="bbai-command-card__refresh"
                                data-bbai-status-refresh
                                aria-label="<?php esc_attr_e('Refresh library scan', 'beepbeep-ai-alt-text-generator'); ?>"
                            >
                                <span class="bbai-command-card__refresh-icon" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" focusable="false">
                                        <path d="M16.667 10a6.667 6.667 0 1 1-1.953-4.714" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M13.333 3.333h3.334v3.334" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="bbai-command-card__refresh-spinner" aria-hidden="true"></span>
                            </button>
                        </div>
                    </header>

                    <div class="bbai-command-status">
                        <div class="bbai-command-status__overview">
                            <div
                                class="bbai-command-donut<?php echo $bbai_coverage_processing_ui ? ' bbai-command-donut--processing' : ''; ?>"
                                data-bbai-status-donut
                                aria-hidden="true"
                                style="background: <?php echo esc_attr($bbai_donut_initial_background); ?>;"
                            >
                                <span class="bbai-command-donut__inner"></span>
                            </div>
                            <?php if ($bbai_total_images > 0) : ?>
                                <noscript>
                                    <style>
                                        #bbai-dashboard-main [data-bbai-status-donut] {
                                            background: <?php echo esc_attr($bbai_donut_background); ?> !important;
                                        }
                                    </style>
                                </noscript>
                            <?php endif; ?>

                            <div class="bbai-command-status__content">
                                <p class="bbai-command-status__label"><?php esc_html_e('Coverage', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <div class="bbai-command-status__value-group">
                                    <p class="bbai-command-status__value<?php echo $bbai_coverage_processing_ui ? ' bbai-command-status__value--processing' : ''; ?>">
                                        <span class="bbai-command-status__coverage-percent-wrap" data-bbai-status-coverage-percent-wrap<?php echo $bbai_coverage_processing_ui ? ' hidden' : ''; ?>>
                                            <span data-bbai-status-coverage-value><?php echo esc_html(number_format_i18n($coveragePercent)); ?></span><span>%</span>
                                        </span>
                                        <span class="bbai-command-status__coverage-processing" data-bbai-status-coverage-processing<?php echo $bbai_coverage_processing_ui ? '' : ' hidden'; ?>>
                                            <span class="bbai-command-status__processing-spinner" aria-hidden="true"></span>
                                            <span data-bbai-status-coverage-processing-text><?php echo esc_html($bbai_coverage_processing_msg); ?></span>
                                        </span>
                                    </p>
                                    <p class="bbai-command-status__motivation" data-bbai-status-coverage-motivation<?php echo '' !== $bbai_coverage_motivation ? '' : ' hidden'; ?>><?php echo esc_html($bbai_coverage_motivation); ?></p>
                                    <span class="bbai-command-status__coverage-badge" data-bbai-status-coverage-badge<?php echo '' !== $bbai_coverage_badge ? '' : ' hidden'; ?>><?php echo esc_html($bbai_coverage_badge); ?></span>
                                    <p class="bbai-command-status__summary-line" data-bbai-status-summary-ratio><?php echo esc_html($bbai_dashboard_state['statusSummary']); ?></p>
                                </div>
                            </div>
                        </div>

                        <?php
                        bbai_ui_render(
                            'filter-group',
                            [
                                'variant' => 'vertical',
                                'interaction_mode' => 'navigate',
                                'root_class' => 'bbai-command-status__summary',
                                'items' => [
                                    [
                                        'key' => 'all',
                                        'label' => __('All', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $totalImages,
                                        'href' => $bbai_library_url,
                                        'status_url' => $bbai_library_url,
                                        'item_aria_label' => __('View all images in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'all',
                                    ],
                                    [
                                        'key' => 'optimized',
                                        'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $optimizedCount,
                                        'href' => $bbai_optimized_library_url,
                                        'status_url' => $bbai_optimized_library_url,
                                        'item_aria_label' => __('View optimized images in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'optimized',
                                    ],
                                    [
                                        'key' => 'weak',
                                        'label' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $weakCount,
                                        'href' => $bbai_needs_review_library_url,
                                        'status_url' => $bbai_needs_review_library_url,
                                        'item_aria_label' => __('View images needing review in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'weak',
                                    ],
                                    [
                                        'key' => 'missing',
                                        'label' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $missingCount,
                                        'href' => $bbai_missing_library_url,
                                        'status_url' => $bbai_missing_library_url,
                                        'item_aria_label' => __('View images missing ALT text in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'missing',
                                    ],
                                ],
                            ]
                        );
                        ?>
                        <p class="bbai-command-status__detail" data-bbai-status-summary-detail<?php echo '' !== $bbai_status_detail ? '' : ' hidden'; ?>><?php echo esc_html($bbai_status_detail); ?></p>
                    </div>
                </article>

                <?php
                $bbai_plan_section_label = $bbai_is_anonymous_trial
                    ? __('Free trial', 'beepbeep-ai-alt-text-generator')
                    : __('Plan & usage', 'beepbeep-ai-alt-text-generator');
                $bbai_plan_title_text = $bbai_is_anonymous_trial
                    ? __('Trial usage', 'beepbeep-ai-alt-text-generator')
                    : __('Current allowance', 'beepbeep-ai-alt-text-generator');
                $bbai_plan_current_label = $bbai_is_anonymous_trial
                    ? __('Mode', 'beepbeep-ai-alt-text-generator')
                    : __('Current plan', 'beepbeep-ai-alt-text-generator');
                $bbai_plan_usage_label = $bbai_is_anonymous_trial
                    ? __('Usage', 'beepbeep-ai-alt-text-generator')
                    : __('This month', 'beepbeep-ai-alt-text-generator');
                $bbai_plan_reset_label = $bbai_is_anonymous_trial
                    ? __('Next', 'beepbeep-ai-alt-text-generator')
                    : __('Reset', 'beepbeep-ai-alt-text-generator');
                $bbai_plan_low_badge_text = $bbai_is_anonymous_trial
                    ? __('Near limit', 'beepbeep-ai-alt-text-generator')
                    : __('Low credits', 'beepbeep-ai-alt-text-generator');
                $bbai_show_growth_comparison = !$bbai_is_anonymous_trial;
                if ($bbai_is_anonymous_trial) {
                    $bbai_show_plan_note = true;
                    if ('exhausted' === $bbai_quota_state) {
                        $bbai_plan_note_lead = __('Free trial complete', 'beepbeep-ai-alt-text-generator');
                        $bbai_plan_note_sub = sprintf(
                            /* translators: %d: free account generations per month. */
                            __('Create a free account to unlock %d generations per month and continue where you left off.', 'beepbeep-ai-alt-text-generator'),
                            $bbai_free_plan_offer
                        );
                    } elseif ('near_limit' === $bbai_quota_state) {
                        $bbai_plan_note_lead = __('You’re close to the end of your free trial', 'beepbeep-ai-alt-text-generator');
                        $bbai_plan_note_sub = sprintf(
                            /* translators: %d: free account generations per month. */
                            __('Create a free account now to unlock %d generations per month before your trial runs out.', 'beepbeep-ai-alt-text-generator'),
                            $bbai_free_plan_offer
                        );
                    } else {
                        $bbai_plan_note_lead = __('Try BeepBeep AI before creating an account', 'beepbeep-ai-alt-text-generator');
                        $bbai_plan_note_sub = sprintf(
                            /* translators: %d: free account generations per month. */
                            __('Create a free account whenever you’re ready to unlock %d generations per month.', 'beepbeep-ai-alt-text-generator'),
                            $bbai_free_plan_offer
                        );
                    }
                } else {
                    $bbai_has_enough_credits = $missingCount > 0 && $creditsRemaining >= $missingCount;
                    $bbai_show_plan_note = (!$isProPlan || $missingCount > 0)
                        && !bbai_banner_suppress_dashboard_plan_attention_note(
                            $bbai_primary_banner_state,
                            $missingCount,
                            $weakCount
                        )
                        && ! $bbai_dashboard_primary_missing;
                    if ($isProPlan && $missingCount > 0) {
                        $bbai_plan_note_lead = 1 === $missingCount
                            ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
                            : sprintf(
                                /* translators: %s: number of images */
                                __('%s images from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                                number_format_i18n($missingCount)
                            );
                        $bbai_plan_note_sub = $bbai_has_enough_credits
                            ? __('You have enough credits to fix this now.', 'beepbeep-ai-alt-text-generator')
                            : __('Fix the remaining to maximise your traffic potential.', 'beepbeep-ai-alt-text-generator');
                    } elseif (!$isProPlan && $bbai_has_enough_credits) {
                        $bbai_plan_note_lead = __('You have enough credits to fix this now.', 'beepbeep-ai-alt-text-generator');
                        $bbai_plan_note_sub = __('Or upgrade for unlimited optimisation.', 'beepbeep-ai-alt-text-generator');
                    } else {
                        $bbai_plan_note_lead = __('Unlock full site optimisation', 'beepbeep-ai-alt-text-generator');
                        $bbai_plan_note_sub = __('New uploads will stop being optimised automatically on the free plan', 'beepbeep-ai-alt-text-generator');
                    }
                }
                $bbai_plan_cta_sub_text = $bbai_is_anonymous_trial
                    ? sprintf(
                        /* translators: %d: free account generations per month. */
                        __('Create a free account to unlock %d generations per month.', 'beepbeep-ai-alt-text-generator'),
                        $bbai_free_plan_offer
                    )
                    : __('Automatically optimise every new image', 'beepbeep-ai-alt-text-generator');
                ?>
                <article class="bbai-dashboard-card bbai-command-card bbai-command-card--plan bbai-product-rail-surface" aria-labelledby="bbai-plan-title">
                    <header class="bbai-command-card__header bbai-command-card__header--with-badge">
                        <div>
                            <p class="bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php echo esc_html($bbai_plan_section_label); ?></p>
                            <h2 id="bbai-plan-title" class="bbai-command-card__title bbai-section-title bbai-card-title"><?php echo esc_html($bbai_plan_title_text); ?></h2>
                        </div>
                        <span class="bbai-command-plan__low-credits-badge" data-bbai-plan-low-credits-badge<?php echo $isLowCredits ? '' : ' hidden'; ?>><?php echo esc_html($bbai_plan_low_badge_text); ?></span>
                    </header>

                    <div class="bbai-command-plan">
                        <div class="bbai-command-plan__rows">
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php echo esc_html($bbai_plan_current_label); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-label><?php echo esc_html($bbai_dashboard_state['planLabel']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php echo esc_html($bbai_plan_usage_label); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-line><?php echo esc_html($bbai_dashboard_state['usageLine']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-remaining><?php echo esc_html($bbai_dashboard_state['remainingLine']); ?></strong>
                            </div>
                            <div class="bbai-command-plan__row">
                                <span class="bbai-command-plan__label"><?php echo esc_html($bbai_plan_reset_label); ?></span>
                                <strong class="bbai-command-plan__value" data-bbai-plan-usage-reset><?php echo esc_html($bbai_dashboard_state['compactResetTiming']); ?></strong>
                            </div>
                        </div>
                        <div class="bbai-command-plan__usage">
                            <div class="bbai-command-meter bbai-command-meter--plan" aria-hidden="true">
                                <span class="bbai-command-meter__fill bbai-command-meter__fill--plan" data-bbai-plan-usage-progress style="width: <?php echo esc_attr($usagePercent); ?>%;" data-bbai-plan-usage-progress-target="<?php echo esc_attr($usagePercent); ?>"></span>
                            </div>
                            <div class="bbai-command-plan__comparison-block"<?php echo $bbai_show_growth_comparison ? '' : ' hidden'; ?>>
                                <p class="bbai-command-plan__comparison" data-bbai-plan-growth-line><?php echo esc_html($bbai_growth_usage_line); ?></p>
                                <div class="bbai-command-plan__comparison-visual" aria-hidden="true">
                                    <div class="bbai-command-plan__comparison-header">
                                        <span class="bbai-command-plan__comparison-label"><?php esc_html_e('With Growth', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <span class="bbai-command-plan__comparison-value" data-bbai-plan-growth-percent-label><?php echo esc_html($bbai_growth_usage_display); ?>%</span>
                                    </div>
                                    <div class="bbai-command-meter bbai-command-meter--growth" aria-hidden="true">
                                        <span class="bbai-command-meter__fill bbai-command-meter__fill--growth" data-bbai-plan-growth-progress style="width: <?php echo esc_attr($bbai_growth_usage_percent); ?>%;" data-bbai-plan-growth-progress-target="<?php echo esc_attr($bbai_growth_usage_percent); ?>"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="bbai-command-plan__upgrade-note" data-bbai-plan-upgrade-note<?php echo $bbai_show_plan_note ? '' : ' hidden'; ?>>
                            <span class="bbai-command-plan__upgrade-lead" data-bbai-plan-upgrade-lead><?php echo esc_html($bbai_plan_note_lead); ?></span>
                            <span class="bbai-command-plan__upgrade-sub" data-bbai-plan-upgrade-sub><?php echo esc_html($bbai_plan_note_sub); ?></span>
                        </p>
                        <div class="bbai-command-plan__actions">
                            <?php $bbai_render_action_link($bbai_plan_primary_action, $bbai_plan_primary_class, 'data-bbai-plan-action-primary'); ?>
                            <?php $bbai_render_action_link($bbai_plan_secondary_action, $bbai_plan_secondary_class, 'data-bbai-plan-action-secondary'); ?>
                        </div>
                        <p class="bbai-command-plan__cta-sub" data-bbai-plan-upgrade-cta-sub<?php echo $isProPlan ? ' hidden' : ''; ?>>
                            <?php echo esc_html($bbai_plan_cta_sub_text); ?>
                        </p>
                    </div>
                </article>
            </div>

            <?php
            if (
                !$bbai_ftue_show_hero
                && !bbai_banner_is_priority_slot_active($bbai_dashboard_banner_slot)
                && $bbai_retention_strip
                && is_readable($bbai_retention_partial)
            ) {
                include $bbai_retention_partial;
            }
            ?>

            <div
                class="bbai-dashboard-review-overlay"
                data-bbai-dashboard-review-prompt
                data-bbai-review-url="<?php echo esc_url($bbai_review_prompt_url); ?>"
                role="dialog"
                aria-modal="true"
                aria-label="<?php esc_attr_e('Review request', 'beepbeep-ai-alt-text-generator'); ?>"
                hidden
            >
                <div class="bbai-dashboard-review-overlay__backdrop" data-bbai-review-backdrop></div>
                <div class="bbai-dashboard-review-overlay__dialog" data-bbai-review-dialog>
                    <p class="bbai-dashboard-review-overlay__headline" data-bbai-review-headline><?php esc_html_e('⭐ Enjoying BeepBeep AI?', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-dashboard-review-overlay__copy" data-bbai-review-copy><?php esc_html_e('You’ve fully optimised your images. If BeepBeep AI helped, a quick review would mean a lot.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <p class="bbai-dashboard-review-overlay__aside"><?php esc_html_e('It helps more WordPress users discover the plugin.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <div class="bbai-dashboard-review-overlay__actions">
                        <a
                            href="<?php echo esc_url($bbai_review_prompt_url); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="bbai-dashboard-review-overlay__cta"
                            data-bbai-review-action="leave"
                        ><?php esc_html_e('Leave a quick review', 'beepbeep-ai-alt-text-generator'); ?></a>
                        <button
                            type="button"
                            class="bbai-dashboard-review-overlay__later"
                            data-bbai-review-action="later"
                        ><?php esc_html_e('Maybe later', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                    <a
                        href="https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="bbai-dashboard-review-overlay__feedback"
                    ><?php esc_html_e('Send feedback instead', 'beepbeep-ai-alt-text-generator'); ?></a>
                </div>
            </div>

            <div class="bbai-dashboard-feedback" data-bbai-dashboard-feedback hidden aria-live="polite" role="status"></div>
        </div>
        <?php else : ?>
        <div class="bbai-dashboard-feedback bbai-dashboard-feedback--onboarding" data-bbai-dashboard-feedback hidden aria-live="polite" role="status"></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
