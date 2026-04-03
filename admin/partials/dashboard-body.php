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
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/plan-top-banner.php';
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

$bbai_map_resolver_to_plan_action = static function ( ?array $act ) use ( $bbai_build_action ): array {
    if ( empty( $act ) || empty( $act['label'] ) ) {
        return $bbai_build_action();
    }
    $attrs       = isset( $act['attributes'] ) && is_array( $act['attributes'] ) ? $act['attributes'] : [];
    $href        = isset( $act['href'] ) ? (string) $act['href'] : '';
    $data_action = isset( $attrs['data-action'] ) ? (string) $attrs['data-action'] : '';
    unset( $attrs['data-action'] );

    return $bbai_build_action(
        (string) $act['label'],
        [
            'href'        => '' !== $href ? $href : '#',
            'action'      => $data_action,
            'extra_attrs' => $attrs,
        ]
    );
};

$bbai_plan_card_credit_resolver = false;

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
            /* translators: %s: number of days until credits reset. */
            _n('Resets in %s day', 'Resets in %s days', $days_until_reset, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($days_until_reset)
        );
    }

    return '' !== $fallback ? $fallback : __('Resets monthly', 'beepbeep-ai-alt-text-generator');
};

$bbai_format_reset_value = static function (?int $days_until_reset, string $fallback = ''): string {
    if (null !== $days_until_reset) {
        return sprintf(
            /* translators: %s: number of days until credits reset. */
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
            /* translators: %s: relative time since the last scan. */
            __('Last scan %s ago', 'beepbeep-ai-alt-text-generator'),
            human_time_diff($timestamp, $now)
        );
    }

    return sprintf(
        /* translators: %s: formatted last scan date. */
        __('Last scan %s', 'beepbeep-ai-alt-text-generator'),
        date_i18n(get_option('date_format'), $timestamp)
    );
};

$bbai_is_authenticated = (bool) ($bbai_is_authenticated ?? false);
$bbai_has_license = (bool) ($bbai_has_license ?? false);
$bbai_has_registered_user = (bool) ($bbai_has_registered_user ?? false);
$bbai_has_connected_account = (bool) ($bbai_has_connected_account ?? $bbai_has_registered_user);
$bbai_has_no_saas_account     = ! $bbai_has_connected_account;
// Guest shell: no connected SaaS account. Do not trust is_anonymous_trial alone — if it is false while
// has_connected_account is false (stale flags), skipping the block below renders an empty dashboard.
$bbai_is_guest_trial = ! empty( $bbai_is_anonymous_trial ) || $bbai_has_no_saas_account;
$bbai_guest_primary_mode  = '';
$bbai_guest_trial_status = $bbai_is_guest_trial ? \BeepBeepAI\AltTextGenerator\Trial_Quota::get_status() : [];

if ($bbai_has_connected_account || $bbai_is_guest_trial) :
    $bbai_plan_data = $bbai_is_guest_trial ? [] : Plan_Helpers::get_plan_data();
    $bbai_plan_slug_ladder = $bbai_has_connected_account
        ? strtolower( (string) ( $bbai_usage_stats['plan'] ?? ( $bbai_plan_data['plan_slug'] ?? 'free' ) ) )
        : 'free';
    $bbai_is_agency = !$bbai_is_guest_trial && !empty($bbai_plan_data['is_agency']);
    $bbai_is_premium = !$bbai_is_guest_trial && (!empty($bbai_plan_data['is_pro']) || $bbai_is_agency);
    $bbai_auth_state = isset($bbai_usage_stats['auth_state']) && is_string($bbai_usage_stats['auth_state']) && '' !== trim($bbai_usage_stats['auth_state'])
        ? sanitize_key($bbai_usage_stats['auth_state'])
        : ($bbai_is_guest_trial ? 'anonymous' : 'authenticated');
    $bbai_quota_type = isset($bbai_usage_stats['quota_type']) && is_string($bbai_usage_stats['quota_type']) && '' !== trim($bbai_usage_stats['quota_type'])
        ? sanitize_key($bbai_usage_stats['quota_type'])
        : ($bbai_is_guest_trial ? 'trial' : ($bbai_is_premium ? 'paid' : 'monthly_account'));
    // Usage payloads can lag Auth_State (e.g. cached "authenticated" while JWT is gone). Product UX follows SaaS link.
    if ( $bbai_has_no_saas_account ) {
        $bbai_auth_state = 'anonymous';
        $bbai_quota_type = 'trial';
    }
    $bbai_is_anonymous_trial = ('anonymous' === $bbai_auth_state) || ('trial' === $bbai_quota_type) || !empty($bbai_usage_stats['is_trial']) || $bbai_is_guest_trial;
    if ($bbai_is_anonymous_trial) {
        $bbai_auth_state = 'anonymous';
        $bbai_quota_type = 'trial';
    }
    // Keep no-SaaS guests in the guest shell even if usage/auth flags disagree (avoids signed-in FTUE on stale payloads).
    $bbai_is_guest_trial = $bbai_is_anonymous_trial || $bbai_has_no_saas_account;
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
                ? sprintf(
                    /* translators: %s: formatted reset date. */
                    __('Resets %s', 'beepbeep-ai-alt-text-generator'),
                    date_i18n(get_option('date_format'), $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp'))
                )
                : __('Resets monthly', 'beepbeep-ai-alt-text-generator')
        );

    $bbai_credits_reset_line = $bbai_is_anonymous_trial
        ? sprintf(
            /* translators: %d: free account generations per month. */
            __('Create a free account to keep your progress and unlock %d monthly generations', 'beepbeep-ai-alt-text-generator'),
            $bbai_free_plan_offer
        )
        : (($bbai_reset_raw !== '' || $bbai_has_reset_timestamp)
            ? sprintf(
                /* translators: %s: formatted credit reset date. */
                __('Credits reset %s', 'beepbeep-ai-alt-text-generator'),
                date_i18n('F j, Y', $bbai_has_reset_timestamp ? (int) $bbai_reset_ts : current_time('timestamp'))
            )
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
            /* translators: %s: percentage of Growth plan capacity used. */
            __('You are using %s%% of Growth capacity this month.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        )
        : sprintf(
            /* translators: %s: percentage of Growth plan capacity current usage would consume. */
            __('On Growth, this usage would be %s%% of monthly capacity.', 'beepbeep-ai-alt-text-generator'),
            $bbai_growth_usage_display
        ));
    $bbai_donut_background = $bbai_build_donut_background($bbai_optimized_count, $bbai_weak_count, $bbai_missing_count, $bbai_total_images);
    $bbai_donut_initial_background = $bbai_total_images > 0
        ? 'conic-gradient(#e2e8f0 0deg 360deg)'
        : $bbai_donut_background;

    $bbai_state_is_healthy = $bbai_missing_count === 0 && $bbai_weak_count === 0 && $bbai_total_images > 0;
    $bbai_state_is_pro_plan = $bbai_is_premium;
    $bbai_state_missing_count = $bbai_missing_count;
    $bbai_state_weak_count = $bbai_weak_count;
    $bbai_state_optimized_count = $bbai_optimized_count;
    $bbai_state_total_images = $bbai_total_images;
    $bbai_state_coverage_percent = $bbai_coverage_percent;
    $bbai_state_credits_used = $bbai_credits_used;
    $bbai_state_credits_limit = $bbai_credits_total;
    $bbai_state_credits_remaining = $bbai_credits_remaining;
    $bbai_state_days_until_reset = null !== $bbai_days_until_reset ? (int) $bbai_days_until_reset : 0;
    $bbai_state_usage_percent = $bbai_usage_percent;
    $bbai_state_is_low_credits = $bbai_state_credits_remaining > 0 && $bbai_state_credits_remaining <= $bbai_low_credit_threshold;
    $bbai_state_is_out_of_credits = $bbai_state_credits_remaining === 0;
    $bbai_state_has_scan_history = $bbai_has_scan_history;
    $bbai_state_is_first_run = !$bbai_state_has_scan_history || $bbai_state_total_images === 0;
    $bbai_product_state_model = Dashboard_State::resolve(
        [
            'is_guest_trial' => $bbai_is_guest_trial,
            'is_premium'     => $bbai_state_is_pro_plan,
            'plan_slug'      => $bbai_plan_slug_ladder,
            'usage_stats'    => $bbai_usage_stats,
            'trial_status'   => $bbai_guest_trial_status,
            'missing_count'  => $bbai_state_missing_count,
            'weak_count'     => $bbai_state_weak_count,
        ]
    );

    $bbai_ftue_pre_scan  = !$bbai_is_guest_trial && !$bbai_state_is_out_of_credits && !$bbai_state_has_scan_history && !$bbai_has_ever_generated_alt;
    $bbai_ftue_post_scan = !$bbai_is_guest_trial && !$bbai_state_is_out_of_credits && $bbai_state_has_scan_history && !$bbai_has_ever_generated_alt;
    // Only signed-in SaaS users get activation FTUE that hides the command grid; guests always see the dashboard shell.
    $bbai_ftue_show_hero = $bbai_has_connected_account && ( $bbai_ftue_pre_scan || $bbai_ftue_post_scan );

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
            'is_out_of_credits'   => $bbai_state_is_out_of_credits,
            'credits_used'        => $bbai_credits_used,
            'stats_generated'     => $bbai_stats_generated,
            'telemetry_first_alt' => $bbai_first_alt_ts,
        ]
    );

    // FTUE partial only renders when $bbai_ftue_show_hero is true (see below). The command grid must not be
    // CSS-hidden unless that surface is actually output — otherwise a filter can leave an empty dashboard.
    $bbai_onboarding_shell_active = $bbai_ftue_show_hero && $bbai_onboarding_first_open;

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
    if ($bbai_state_is_first_run) {
        $bbai_status_detail = __('Run your first scan to see coverage and missing ALT text.', 'beepbeep-ai-alt-text-generator');
    } elseif ($bbai_coverage_processing_ui) {
        $bbai_status_detail = __('This count updates after ALT text is saved — it usually takes a few seconds.', 'beepbeep-ai-alt-text-generator');
    } elseif ($bbai_state_missing_count > 0) {
        $bbai_coverage_gap_total = $bbai_state_missing_count + $bbai_state_weak_count;
        $bbai_status_detail = 1 === $bbai_coverage_gap_total
            ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: %s: number of images (missing ALT plus needs-review) still to address. */
                __('%s images away from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_coverage_gap_total)
            );
    } elseif ($bbai_state_weak_count > 0) {
        $bbai_status_detail = sprintf(
            /* translators: %s: number of descriptions that need review. */
            _n('%s description needs review.', '%s descriptions need review.', $bbai_state_weak_count, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_state_weak_count)
        );
    } elseif ($bbai_state_total_images > 0) {
        $bbai_status_detail = __('All current images include ALT text.', 'beepbeep-ai-alt-text-generator');
    }

    $bbai_dashboard_primary_missing = $bbai_state_missing_count > 0 && !$bbai_state_is_out_of_credits;
    $bbai_dashboard_primary_review = 0 === $bbai_state_missing_count && $bbai_state_weak_count > 0 && !$bbai_state_is_out_of_credits && $bbai_has_scan_results;
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
                    'data-auth-tab'               => 'register',
                    'data-bbai-analytics-upgrade' => 'trial_create_account_clicked',
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
            $bbai_state_is_pro_plan ? __('Review usage', 'beepbeep-ai-alt-text-generator') : __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator'),
            $bbai_state_is_pro_plan
                ? [
                    'href' => $bbai_usage_url,
                    'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                ]
                : [
                    'action' => 'show-upgrade-modal',
                    'aria_label' => __('Turn on automatic optimisation', 'beepbeep-ai-alt-text-generator'),
                    'extra_attrs' => [
                        'data-bbai-pricing-variant' => 'growth',
                    ],
                ]
        );

        $bbai_plan_secondary_action = !$bbai_state_is_pro_plan
            ? $bbai_build_action(
                __('Review usage', 'beepbeep-ai-alt-text-generator'),
                [
                    'href' => $bbai_usage_url,
                    'aria_label' => __('Review usage', 'beepbeep-ai-alt-text-generator'),
                ]
            )
            : $bbai_build_action();

        if ( $bbai_state_is_low_credits || $bbai_state_is_out_of_credits ) {
            if ( ! class_exists( \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::class, false ) ) {
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-path-resolver.php';
            }
            $bbai_cred_snap = [
                'has_connected_account'    => (bool) $bbai_has_connected_account,
                'plan_slug'                => (string) $bbai_plan_slug_ladder,
                'library_url'              => $bbai_library_url,
                'needs_review_library_url' => $bbai_needs_review_library_url,
                'usage_url'                => $bbai_usage_url,
                'settings_url'             => $bbai_settings_url,
            ];
            list( $bbai_uc_p, $bbai_uc_s ) = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::credit_banner_actions(
                $bbai_cred_snap,
                BBAI_BANNER_CTX_DASHBOARD
            );
            $bbai_plan_primary_action   = $bbai_map_resolver_to_plan_action( $bbai_uc_p );
            $bbai_plan_secondary_action = $bbai_map_resolver_to_plan_action( $bbai_uc_s );
            $bbai_plan_card_credit_resolver = true;
        }
    }

    // Align plan-card CTAs with the same ladder as the credit banner when the guest trial is exhausted.
    if ( $bbai_is_anonymous_trial && 'exhausted' === $bbai_quota_state ) {
        if ( ! class_exists( \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::class, false ) ) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-path-resolver.php';
        }
        $bbai_guest_cred_snap = [
            'has_connected_account'    => false,
            'plan_slug'                => 'free',
            'library_url'              => $bbai_library_url,
            'needs_review_library_url' => $bbai_needs_review_library_url,
            'usage_url'                => $bbai_usage_url,
            'settings_url'             => $bbai_settings_url,
        ];
        list( $bbai_g_p, $bbai_g_s ) = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::credit_banner_actions(
            $bbai_guest_cred_snap,
            BBAI_BANNER_CTX_DASHBOARD
        );
        $bbai_plan_primary_action      = $bbai_map_resolver_to_plan_action( $bbai_g_p );
        $bbai_plan_secondary_action    = $bbai_map_resolver_to_plan_action( $bbai_g_s );
        $bbai_plan_card_credit_resolver = true;
    }

    $bbai_dashboard_state = [
        'isHealthy' => $bbai_state_is_healthy,
        'isProPlan' => $bbai_state_is_pro_plan,
        'missingCount' => $bbai_state_missing_count,
        'weakCount' => $bbai_state_weak_count,
        'optimizedCount' => $bbai_state_optimized_count,
        'totalImages' => $bbai_state_total_images,
        'coveragePercent' => $bbai_state_coverage_percent,
        'creditsUsed' => $bbai_state_credits_used,
        'creditsLimit' => $bbai_state_credits_limit,
        'creditsRemaining' => $bbai_state_credits_remaining,
        'authState' => $bbai_auth_state,
        'quotaType' => $bbai_quota_type,
        'quotaState' => $bbai_quota_state,
        'signupRequired' => $bbai_signup_required,
        'upgradeRequired' => $bbai_upgrade_required,
        'isTrial' => $bbai_is_anonymous_trial || !empty($bbai_usage_stats['is_trial']),
        'freePlanOffer' => $bbai_free_plan_offer,
        'lowCreditThreshold' => $bbai_low_credit_threshold,
        'daysUntilReset' => $bbai_state_days_until_reset,
        'usagePercent' => $bbai_state_usage_percent,
        'isLowCredits' => $bbai_state_is_low_credits,
        'isOutOfCredits' => $bbai_state_is_out_of_credits,
        'hasScanHistory' => $bbai_state_has_scan_history,
        'hasScanResults' => $bbai_has_scan_results,
        'isFirstRun' => $bbai_state_is_first_run,
        'lastScanTimestamp' => $bbai_last_scan_timestamp,
        'lastScanLine' => $bbai_format_last_scan($bbai_last_scan_timestamp),
        'planLabel' => $bbai_plan_label,
        'resetTiming' => $bbai_reset_timing,
        'compactResetTiming' => $bbai_format_reset_value($bbai_days_until_reset, $bbai_reset_timing),
        'creditsResetLine' => $bbai_credits_reset_line,
        'usageLine' => $bbai_is_anonymous_trial
            ? sprintf(
                /* translators: 1: used free trial generations, 2: total free trial generations. */
                __('%1$s / %2$s free trial generations used', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_state_credits_used),
                number_format_i18n($bbai_state_credits_limit)
            )
            : sprintf(
                /* translators: 1: used credits, 2: total credits. */
                __('%1$s / %2$s used', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_state_credits_used),
                number_format_i18n($bbai_state_credits_limit)
            ),
        'remainingLine' => $bbai_is_anonymous_trial
            ? sprintf(
                /* translators: %s: remaining free trial generations. */
                _n('%s trial generation remaining', '%s trial generations remaining', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_state_credits_remaining)
            )
            : sprintf(
                /* translators: %s: remaining images that can be processed this month. */
                _n('%s image left this month', '%s images left this month', $bbai_state_credits_remaining, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_state_credits_remaining)
            ),
        'statusSummary' => $bbai_state_total_images > 0
            ? sprintf(
                /* translators: 1: optimized image count, 2: total image count. */
                _n('%1$s / %2$s image optimized', '%1$s / %2$s images optimized', $bbai_state_total_images, 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_state_optimized_count),
                number_format_i18n($bbai_state_total_images)
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
        'onboardingFirstOpen' => $bbai_onboarding_shell_active,
        'coverageProcessing' => $bbai_coverage_processing,
        'isGuestTrial' => $bbai_is_anonymous_trial,
        'productStateModel' => $bbai_product_state_model,
        'planSlug' => $bbai_plan_slug_ladder,
        'hasConnectedAccount' => $bbai_has_connected_account,
    ];

    $bbai_plan_top_input = [
        'has_connected_account'  => $bbai_has_connected_account,
        'is_guest_trial'         => $bbai_is_anonymous_trial,
        'is_anonymous_trial'       => $bbai_is_anonymous_trial,
        'plan_slug'                => strtolower((string) $bbai_plan_slug_ladder),
        'is_agency'                => $bbai_is_agency,
        'free_plan_offer'          => $bbai_free_plan_offer,
        'usage_url'                => $bbai_usage_url,
        'billing_portal_url'       => (string) Usage_Tracker::get_billing_portal_url(),
        'settings_url'             => $bbai_settings_url,
    ];

    $bbai_hero_section_data = [
        'data-bbai-active-banner-slot'   => '',
        'data-bbai-dashboard-hero'         => '1',
        'data-bbai-banner-used'            => (string) $bbai_state_credits_used,
        'data-bbai-banner-limit'           => (string) $bbai_state_credits_limit,
        'data-bbai-banner-remaining'       => (string) $bbai_state_credits_remaining,
        'data-bbai-auth-state'             => (string) $bbai_auth_state,
        'data-bbai-quota-type'             => (string) $bbai_quota_type,
        'data-bbai-quota-state'            => (string) $bbai_quota_state,
        'data-bbai-signup-required'        => $bbai_signup_required ? '1' : '0',
        'data-bbai-free-plan-offer'        => (string) $bbai_free_plan_offer,
        'data-bbai-low-credit-threshold'   => (string) $bbai_low_credit_threshold,
        'data-bbai-banner-library-url'     => $bbai_library_url,
        'data-bbai-banner-missing-count'   => (string) $bbai_state_missing_count,
        'data-bbai-banner-weak-count'      => (string) $bbai_state_weak_count,
        'data-bbai-banner-days-left'       => (string) (int) ($bbai_dashboard_state['daysUntilReset'] ?? 0),
        'data-bbai-banner-settings-url'    => $bbai_settings_url,
    ];

    $bbai_dash_banner_snap = bbai_banner_snapshot_merge(
        [
            'missing_count'          => $bbai_state_missing_count,
            'weak_count'             => $bbai_state_weak_count,
            'total_images'           => $bbai_state_total_images,
            'credits_used'           => $bbai_state_credits_used,
            'credits_limit'          => $bbai_state_credits_limit,
            'credits_remaining'      => $bbai_state_credits_remaining,
            'usage_percent'          => $bbai_state_usage_percent,
            'is_pro_plan'            => $bbai_state_is_pro_plan,
            'is_low_credits'         => $bbai_state_is_low_credits,
            'is_out_of_credits'      => $bbai_state_is_out_of_credits,
            'has_ever_generated_alt' => $bbai_has_ever_generated_alt,
            'optimized_count'        => $bbai_state_optimized_count,
            'auth_state'             => (string) $bbai_auth_state,
            'quota_type'             => (string) $bbai_quota_type,
            'quota_state'            => (string) $bbai_quota_state,
            'signup_required'        => $bbai_signup_required,
            'upgrade_required'       => $bbai_upgrade_required,
            'is_trial'               => $bbai_is_anonymous_trial,
            'free_plan_offer'        => $bbai_free_plan_offer,
            'low_credit_threshold'   => $bbai_low_credit_threshold,
            'plan_slug'              => strtolower((string) $bbai_plan_slug_ladder),
            'has_connected_account'  => $bbai_has_connected_account,
            'library_url'            => $bbai_library_url,
            'missing_library_url'    => $bbai_missing_library_url,
            'needs_review_library_url' => $bbai_needs_review_library_url,
            'usage_url'              => $bbai_usage_url,
            'settings_url'           => $bbai_settings_url,
            'guide_url'              => $bbai_guide_url,
            'billing_portal_url'     => (string) Usage_Tracker::get_billing_portal_url(),
        ]
    );

    $bbai_dash_priority_state = bbai_get_primary_banner_state($bbai_dash_banner_snap, BBAI_BANNER_CTX_DASHBOARD);

    if (BBAI_BANNER_STATE_NONE !== $bbai_dash_priority_state) {
        $bbai_dash_page_hero_variant = 'neutral';
        if (BBAI_BANNER_STATE_HEALTHY === $bbai_dash_priority_state || BBAI_BANNER_STATE_FIRST_SUCCESS === $bbai_dash_priority_state) {
            $bbai_dash_page_hero_variant = 'success';
        } elseif (in_array(
            $bbai_dash_priority_state,
            [
                BBAI_BANNER_STATE_NEEDS_ATTENTION,
                BBAI_BANNER_STATE_LOW_CREDITS,
                BBAI_BANNER_STATE_OUT_OF_CREDITS,
            ],
            true
        )) {
            $bbai_dash_page_hero_variant = 'warning';
        }

        $bbai_dashboard_command_hero = bbai_banner_build_command_hero(
            BBAI_BANNER_CTX_DASHBOARD,
            $bbai_dash_banner_snap,
            [
                'page_hero_variant'  => $bbai_dash_page_hero_variant,
                'aria_label'         => __('Dashboard summary', 'beepbeep-ai-alt-text-generator'),
                'icon_wrapper_attrs' => ['data-bbai-hero-icon' => '1'],
                'headline_attrs'     => ['data-bbai-hero-headline' => '1'],
                'section_data_attrs' => $bbai_hero_section_data,
                'wrapper_extra_class' => 'bbai-plan-top-banner-host',
            ]
        );
    } else {
        $bbai_dashboard_command_hero = bbai_plan_top_banner_build_command_hero(
            BBAI_BANNER_CTX_DASHBOARD,
            $bbai_plan_top_input,
            [
                'aria_label'         => __('Dashboard summary', 'beepbeep-ai-alt-text-generator'),
                'icon_wrapper_attrs' => ['data-bbai-hero-icon' => '1'],
                'headline_attrs'     => ['data-bbai-hero-headline' => '1'],
                'section_data_attrs' => $bbai_hero_section_data,
            ]
        );
    }

    $bbai_primary_banner_state = (string) ($bbai_dashboard_command_hero['banner_logical_state'] ?? 'plan_free');
    $bbai_dashboard_banner_slot = bbai_get_active_banner_slot_from_state($bbai_primary_banner_state);
    if (isset($bbai_dashboard_command_hero['section_data_attrs']) && is_array($bbai_dashboard_command_hero['section_data_attrs'])) {
        $bbai_dashboard_command_hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($bbai_dashboard_banner_slot ?? '');
    }
    $bbai_suppress_dashboard_retention_for_plan_banner = true;
    $bbai_plan_card_hide_monetisation = true;

    $bbai_retention_strip = null;
    if ($bbai_current_user_id) {
        bbai_retention_schedule_snapshot_update($bbai_current_user_id, $bbai_total_images);
        $bbai_retention_strip = bbai_retention_build_strip_model(
            [
                'user_id'                  => $bbai_current_user_id,
                'ftue_show_hero'           => $bbai_ftue_show_hero,
                'ftue_pre_scan'            => $bbai_ftue_pre_scan,
                'has_scan_history'         => $bbai_has_scan_history,
                'is_out_of_credits'        => $bbai_state_is_out_of_credits,
                'is_pro'                   => $bbai_state_is_pro_plan,
                'missing'                  => $bbai_state_missing_count,
                'weak'                     => $bbai_state_weak_count,
                'optimized'                => $bbai_state_optimized_count,
                'total'                    => $bbai_state_total_images,
                'coverage_pct'             => $bbai_state_coverage_percent,
                'credits_used'             => $bbai_state_credits_used,
                'missing_library_url'      => $bbai_missing_library_url,
                'needs_review_library_url' => $bbai_needs_review_library_url,
                'library_url'              => $bbai_library_url,
            ]
        );
    }

    $bbai_primary_action = 'scan';
    if ($bbai_state_is_out_of_credits) {
        $bbai_primary_action = $bbai_is_anonymous_trial ? 'signup' : 'upgrade';
    } elseif ($bbai_state_missing_count > 0) {
        $bbai_primary_action = 'generate_missing';
    } elseif ($bbai_state_weak_count > 0) {
        $bbai_primary_action = 'review_weak';
    } elseif ($bbai_has_scan_results) {
        $bbai_primary_action = 'review_library';
    }

    // Guest trial + generate mode: banner already shows primary "Generate missing" — avoid a second identical green CTA in the plan card.
    if (
        $bbai_is_anonymous_trial
        && 'generate' === ( $bbai_guest_primary_mode ?? '' )
        && 'generate_missing' === $bbai_primary_action
        && isset( $bbai_trial_signup_action, $bbai_trial_continue_action )
    ) {
        $bbai_plan_primary_action   = $bbai_trial_signup_action;
        $bbai_plan_secondary_action = $bbai_trial_continue_action;
        $bbai_dashboard_state['planPrimaryAction']   = $bbai_plan_primary_action;
        $bbai_dashboard_state['planSecondaryAction'] = $bbai_plan_secondary_action;
    }

    $bbai_review_prompt_url = 'https://wordpress.org/support/plugin/beepbeep-ai-alt-text-generator/reviews/?rate=5#new-post';
    if ( ! empty( $bbai_plan_card_credit_resolver ) ) {
        $bbai_plan_primary_class   = 'bbai-command-action bbai-command-action--primary';
        $bbai_plan_secondary_class = 'bbai-command-action bbai-command-action--secondary';
    } else {
        $bbai_plan_primary_class = $bbai_state_is_pro_plan
            ? 'bbai-command-action bbai-command-action--secondary'
            : 'bbai-command-action bbai-command-action--primary';
        $bbai_plan_secondary_class = $bbai_state_is_pro_plan
            ? 'bbai-command-action bbai-command-action--tertiary'
            : 'bbai-command-action bbai-command-action--secondary';
    }
    ?>

    <div
        id="bbai-dashboard-main"
        class="bbai-dashboard bbai-container<?php echo $bbai_onboarding_shell_active ? ' bbai-dashboard--onboarding-first-open' : ''; ?>"
        data-bbai-dashboard-container
        data-bbai-dashboard-root="1"
        data-bbai-dashboard-state-root="1"
        data-bbai-dashboard-state="<?php echo esc_attr((string) ($bbai_product_state_model['state'] ?? '')); ?>"
        data-bbai-dashboard-base-state="<?php echo esc_attr((string) ($bbai_product_state_model['base_state'] ?? '')); ?>"
        data-bbai-dashboard-runtime-state="<?php echo esc_attr((string) ($bbai_product_state_model['runtime_state'] ?? 'idle')); ?>"
        data-bbai-onboarding-first-open="<?php echo esc_attr($bbai_onboarding_shell_active ? '1' : '0'); ?>"
        data-bbai-missing-count="<?php echo esc_attr($bbai_state_missing_count); ?>"
        data-bbai-weak-count="<?php echo esc_attr($bbai_state_weak_count); ?>"
        data-bbai-optimized-count="<?php echo esc_attr($bbai_state_optimized_count); ?>"
        data-bbai-total-count="<?php echo esc_attr($bbai_state_total_images); ?>"
        data-bbai-generated-count="<?php echo esc_attr(max(0, (int) ($bbai_stats['generated'] ?? $bbai_state_optimized_count))); ?>"
        data-bbai-credits-used="<?php echo esc_attr($bbai_state_credits_used); ?>"
        data-bbai-credits-total="<?php echo esc_attr($bbai_state_credits_limit); ?>"
        data-bbai-credits-remaining="<?php echo esc_attr($bbai_state_credits_remaining); ?>"
        data-bbai-credits-reset-line="<?php echo esc_attr($bbai_credits_reset_line); ?>"
        data-bbai-auth-state="<?php echo esc_attr($bbai_auth_state); ?>"
        data-bbai-quota-type="<?php echo esc_attr($bbai_quota_type); ?>"
        data-bbai-quota-state="<?php echo esc_attr($bbai_quota_state); ?>"
        data-bbai-signup-required="<?php echo esc_attr($bbai_signup_required ? '1' : '0'); ?>"
        data-bbai-upgrade-required="<?php echo esc_attr($bbai_upgrade_required ? '1' : '0'); ?>"
        data-bbai-free-plan-offer="<?php echo esc_attr($bbai_free_plan_offer); ?>"
        data-bbai-low-credit-threshold="<?php echo esc_attr($bbai_low_credit_threshold); ?>"
        data-bbai-plan-label="<?php echo esc_attr($bbai_plan_label); ?>"
        data-bbai-is-premium="<?php echo esc_attr($bbai_state_is_pro_plan ? '1' : '0'); ?>"
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
        bbai_ui_render('bbai-banner', ['command_hero' => $bbai_dashboard_command_hero]);

        if ($bbai_ftue_show_hero) {
            $bbai_ftue_phase = $bbai_ftue_pre_scan ? 'pre_scan' : 'post_scan';
            $bbai_onboarding_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-onboarding-first-open.php';
            if (file_exists($bbai_onboarding_partial)) {
                include $bbai_onboarding_partial;
            }
        }

        $bbai_retention_partial      = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-retention-strip.php';
        ?>

        <?php if (!$bbai_onboarding_shell_active) : ?>
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
                                                $bbai_state_missing_count,
                                                'beepbeep-ai-alt-text-generator'
                                            ),
                                            number_format_i18n($bbai_state_missing_count)
                                        )
                                    );
                                } else {
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: number of descriptions */
                                            _n(
                                                '%s description needs review.',
                                                '%s descriptions need review.',
                                                $bbai_state_weak_count,
                                                'beepbeep-ai-alt-text-generator'
                                            ),
                                            number_format_i18n($bbai_state_weak_count)
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
                                data-bbai-donut-optimized="<?php echo esc_attr($bbai_state_optimized_count); ?>"
                                data-bbai-donut-weak="<?php echo esc_attr($bbai_state_weak_count); ?>"
                                data-bbai-donut-missing="<?php echo esc_attr($bbai_state_missing_count); ?>"
                                data-bbai-donut-total="<?php echo esc_attr($bbai_state_total_images); ?>"
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
                            <?php if ($bbai_total_images > 0 && ! $bbai_coverage_processing_ui) : ?>
                                <script>
                                    (function() {
                                        var donut = document.querySelector('#bbai-dashboard-main [data-bbai-status-donut]');
                                        var optimized = donut ? parseInt(donut.getAttribute('data-bbai-donut-optimized') || '0', 10) : 0;
                                        var weak = donut ? parseInt(donut.getAttribute('data-bbai-donut-weak') || '0', 10) : 0;
                                        var missing = donut ? parseInt(donut.getAttribute('data-bbai-donut-missing') || '0', 10) : 0;
                                        var total = donut ? parseInt(donut.getAttribute('data-bbai-donut-total') || '0', 10) : 0;
                                        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                                        var startTs = null;
                                        var duration = 960;

                                        function getGradient(progress) {
                                            var safeProgress = Math.min(1, Math.max(0, progress || 0));
                                            var optimizedEnd;
                                            var weakEnd;
                                            var missingEnd;

                                            if (!total || safeProgress <= 0) {
                                                return 'conic-gradient(#e2e8f0 0deg 360deg)';
                                            }

                                            optimizedEnd = ((360 * optimized) / total) * safeProgress;
                                            weakEnd = optimizedEnd + (((360 * weak) / total) * safeProgress);
                                            missingEnd = weakEnd + (((360 * missing) / total) * safeProgress);

                                            return 'conic-gradient(' +
                                                '#3fa66f 0deg ' + optimizedEnd.toFixed(3) + 'deg, ' +
                                                '#f59e0b ' + optimizedEnd.toFixed(3) + 'deg ' + weakEnd.toFixed(3) + 'deg, ' +
                                                '#ef4444 ' + weakEnd.toFixed(3) + 'deg ' + missingEnd.toFixed(3) + 'deg, ' +
                                                '#e2e8f0 ' + missingEnd.toFixed(3) + 'deg 360deg' +
                                                ')';
                                        }

                                        function easeOutCubic(progress) {
                                            return 1 - Math.pow(1 - Math.min(1, Math.max(0, progress)), 3);
                                        }

                                        function finish() {
                                            donut.style.transition = 'background 0.45s cubic-bezier(0.2, 0.8, 0.2, 1)';
                                            donut.style.background = getGradient(1);
                                            donut.setAttribute('data-bbai-donut-intro-played', '1');
                                        }

                                        function tick(now) {
                                            var progress;

                                            if (!donut || !document.contains(donut)) {
                                                return;
                                            }

                                            if (startTs === null) {
                                                startTs = now;
                                            }

                                            progress = Math.min(1, (now - startTs) / duration);
                                            donut.style.transition = 'none';
                                            donut.style.background = getGradient(easeOutCubic(progress));

                                            if (progress < 1) {
                                                window.requestAnimationFrame(tick);
                                                return;
                                            }

                                            finish();
                                        }

                                        if (!donut || donut.getAttribute('data-bbai-inline-donut-ready') === '1' || donut.getAttribute('data-bbai-donut-intro-played') === '1') {
                                            return;
                                        }

                                        donut.setAttribute('data-bbai-inline-donut-ready', '1');

                                        if (!total || !window.requestAnimationFrame || reducedMotion) {
                                            finish();
                                            return;
                                        }

                                        donut.style.transition = 'none';
                                        donut.style.background = getGradient(0);
                                        window.requestAnimationFrame(tick);
                                    })();
                                </script>
                            <?php endif; ?>

                            <div class="bbai-command-status__content">
                                <p class="bbai-command-status__label"><?php esc_html_e('Coverage', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <div class="bbai-command-status__value-group">
                                    <p class="bbai-command-status__value<?php echo $bbai_coverage_processing_ui ? ' bbai-command-status__value--processing' : ''; ?>">
                                        <span class="bbai-command-status__coverage-percent-wrap" data-bbai-status-coverage-percent-wrap<?php echo $bbai_coverage_processing_ui ? ' hidden' : ''; ?>>
                                            <span data-bbai-status-coverage-value><?php echo esc_html(number_format_i18n($bbai_state_coverage_percent)); ?></span><span>%</span>
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
                                        'count' => $bbai_state_total_images,
                                        'href' => $bbai_library_url,
                                        'status_url' => $bbai_library_url,
                                        'item_aria_label' => __('View all images in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'all',
                                    ],
                                    [
                                        'key' => 'optimized',
                                        'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $bbai_state_optimized_count,
                                        'href' => $bbai_optimized_library_url,
                                        'status_url' => $bbai_optimized_library_url,
                                        'item_aria_label' => __('View optimized images in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'optimized',
                                    ],
                                    [
                                        'key' => 'weak',
                                        'label' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $bbai_state_weak_count,
                                        'href' => $bbai_needs_review_library_url,
                                        'status_url' => $bbai_needs_review_library_url,
                                        'item_aria_label' => __('View images needing review in ALT Library', 'beepbeep-ai-alt-text-generator'),
                                        'metric_key' => 'weak',
                                    ],
                                    [
                                        'key' => 'missing',
                                        'label' => $bbai_state_missing_count > 0
                                            ? __('Missing · Needs ALT', 'beepbeep-ai-alt-text-generator')
                                            : __('Missing', 'beepbeep-ai-alt-text-generator'),
                                        'count' => $bbai_state_missing_count,
                                        'href' => $bbai_missing_library_url,
                                        'status_url' => $bbai_missing_library_url,
                                        'item_aria_label' => $bbai_state_missing_count > 0
                                            ? sprintf(
                                                /* translators: %s: number of images that need ALT text. */
                                                _n(
                                                    'Open %s image that needs ALT text in ALT Library',
                                                    'Open %s images that need ALT text in ALT Library',
                                                    $bbai_state_missing_count,
                                                    'beepbeep-ai-alt-text-generator'
                                                ),
                                                number_format_i18n($bbai_state_missing_count)
                                            )
                                            : __('View images missing ALT text in ALT Library', 'beepbeep-ai-alt-text-generator'),
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
                    $bbai_has_enough_credits = $bbai_state_missing_count > 0 && $bbai_state_credits_remaining >= $bbai_state_missing_count;
                    $bbai_show_plan_note = (!$bbai_state_is_pro_plan || $bbai_state_missing_count > 0)
                        && !bbai_banner_suppress_dashboard_plan_attention_note(
                            $bbai_primary_banner_state,
                            $bbai_state_missing_count,
                            $bbai_state_weak_count
                        )
                        && ! $bbai_dashboard_primary_missing;
                    if ($bbai_state_is_pro_plan && $bbai_state_missing_count > 0) {
                        $bbai_plan_coverage_gap_total = $bbai_state_missing_count + $bbai_state_weak_count;
                        $bbai_plan_note_lead = 1 === $bbai_plan_coverage_gap_total
                            ? __('One fix away from complete coverage.', 'beepbeep-ai-alt-text-generator')
                            : sprintf(
                                /* translators: %s: number of images (missing ALT plus needs-review) still to address. */
                                __('%s images from complete coverage.', 'beepbeep-ai-alt-text-generator'),
                                number_format_i18n($bbai_plan_coverage_gap_total)
                            );
                        $bbai_plan_note_sub = $bbai_has_enough_credits
                            ? __('You have enough credits to fix this now.', 'beepbeep-ai-alt-text-generator')
                            : __('Fix the remaining to maximise your traffic potential.', 'beepbeep-ai-alt-text-generator');
                    } elseif (!$bbai_state_is_pro_plan && $bbai_has_enough_credits) {
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
                if (!empty($bbai_plan_card_hide_monetisation)) {
                    $bbai_show_plan_note = false;
                }
                ?>
                <article class="bbai-dashboard-card bbai-command-card bbai-command-card--plan" aria-labelledby="bbai-plan-title">
                    <header class="bbai-command-card__header">
                        <div>
                            <p class="bbai-command-card__eyebrow bbai-section-label bbai-card-label"><?php echo esc_html($bbai_plan_section_label); ?></p>
                            <h2 id="bbai-plan-title" class="bbai-command-card__title bbai-section-title bbai-card-title"><?php echo esc_html($bbai_plan_title_text); ?></h2>
                        </div>
                        <div class="bbai-command-card__meta-group bbai-command-card__meta-group--plan-trailing">
                            <span class="bbai-command-plan__low-credits-badge" data-bbai-plan-low-credits-badge<?php echo $bbai_state_is_low_credits ? '' : ' hidden'; ?>><?php echo esc_html($bbai_plan_low_badge_text); ?></span>
                        </div>
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
                                <span class="bbai-command-meter__fill bbai-command-meter__fill--plan" data-bbai-plan-usage-progress style="width: <?php echo esc_attr($bbai_state_usage_percent); ?>%;" data-bbai-plan-usage-progress-target="<?php echo esc_attr($bbai_state_usage_percent); ?>"></span>
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
                        <?php if (empty($bbai_plan_card_hide_monetisation)) : ?>
                        <p class="bbai-command-plan__upgrade-note" data-bbai-plan-upgrade-note<?php echo $bbai_show_plan_note ? '' : ' hidden'; ?>>
                            <span class="bbai-command-plan__upgrade-lead" data-bbai-plan-upgrade-lead><?php echo esc_html($bbai_plan_note_lead); ?></span>
                            <span class="bbai-command-plan__upgrade-sub" data-bbai-plan-upgrade-sub><?php echo esc_html($bbai_plan_note_sub); ?></span>
                        </p>
                        <div class="bbai-command-plan__actions">
                            <?php $bbai_render_action_link($bbai_plan_primary_action, $bbai_plan_primary_class, 'data-bbai-plan-action-primary'); ?>
                            <?php $bbai_render_action_link($bbai_plan_secondary_action, $bbai_plan_secondary_class, 'data-bbai-plan-action-secondary'); ?>
                        </div>
                        <p class="bbai-command-plan__cta-sub" data-bbai-plan-upgrade-cta-sub<?php echo $bbai_state_is_pro_plan ? ' hidden' : ''; ?>>
                            <?php echo esc_html($bbai_plan_cta_sub_text); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <?php
            if (
                !$bbai_ftue_show_hero
                && empty($bbai_suppress_dashboard_retention_for_plan_banner)
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
