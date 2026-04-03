<?php
/**
 * Shared admin page hero (Analytics, ALT Library, future screens).
 * Renders via admin/partials/components/bbai-banner.php (status-banner.php) + page-hero.css.
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve SaaS page-hero modifier class (bbai-page-hero--{variant}).
 *
 * @param string               $tone           healthy|attention|setup|paused|neutral.
 * @param string               $semantic_state Logical page state slug.
 * @param string               $banner_variant success|warning from shell.
 * @param string               $explicit       Optional override from caller (success|warning|neutral).
 */
function bbai_page_hero_resolve_variant(string $tone, string $semantic_state, string $banner_variant, string $explicit = ''): string {
    $explicit = trim($explicit);
    if (in_array($explicit, ['success', 'warning', 'neutral', 'info'], true)) {
        return $explicit;
    }

    if (in_array($semantic_state, ['empty', 'first_scan', 'usage_billing'], true)) {
        return 'neutral';
    }

    if ('warning' === $banner_variant) {
        return 'warning';
    }

    if ('healthy' === $tone && 'healthy' === $semantic_state) {
        return 'success';
    }

    return 'success';
}

/**
 * Build $bbai_command_hero for ALT Library via shared banner resolver + library surface hooks.
 *
 * @param array<string, mixed> $args {
 *     @type int    $cov_total,$cov_missing,$cov_needs_review
 *     @type bool   $is_pro
 *     @type string $settings_automation_url
 *     @type bool   $is_low_credits,$is_out_of_credits Optional credit gates for priority.
 *     @type int    $credits_remaining,$credits_used,$credits_limit,$usage_percent Optional.
 * }
 * @return array<string, mixed>
 */
function bbai_page_hero_library_command_hero(array $args): array
{
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/plan-top-banner.php';

    $lib     = admin_url('admin.php?page=bbai-library');
    $surface = bbai_banner_library_surface_attr_opts();
    $auth    = strtolower(trim((string) ($args['auth_state'] ?? '')));
    $quota   = strtolower(trim((string) ($args['quota_type'] ?? '')));
    $guest   = 'anonymous' === $auth || 'trial' === $quota || !empty($args['is_trial']) || !empty($args['is_guest_trial']);

    $plan_input = [
        'has_connected_account' => array_key_exists('has_connected_account', $args)
            ? !empty($args['has_connected_account'])
            : !$guest,
        'is_guest_trial'        => $guest,
        'is_anonymous_trial'    => $guest,
        'plan_slug'             => strtolower((string) ($args['plan_slug'] ?? 'free')),
        'is_agency'             => !empty($args['is_agency']),
        'free_plan_offer'       => max(0, (int) ($args['free_plan_offer'] ?? 50)),
        'usage_url'             => (string) ($args['usage_url'] ?? admin_url('admin.php?page=bbai-credit-usage')),
        'billing_portal_url'    => (string) ($args['billing_portal_url'] ?? ''),
        'settings_url'          => trim((string) ($args['settings_automation_url'] ?? admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload'))),
    ];

    $section_base = array_merge(
        $surface['section_data_attrs'],
        [
            'data-bbai-banner-used' => (string) max(0, (int) ($args['credits_used'] ?? 0)),
            'data-bbai-banner-limit' => (string) max(1, (int) ($args['credits_limit'] ?? 50)),
            'data-bbai-banner-remaining' => (string) max(0, (int) ($args['credits_remaining'] ?? 0)),
            'data-bbai-banner-auth-state' => (string) ($args['auth_state'] ?? ''),
            'data-bbai-banner-quota-type' => (string) ($args['quota_type'] ?? ''),
            'data-bbai-banner-quota-state' => (string) ($args['quota_state'] ?? ''),
            'data-bbai-banner-signup-required' => !empty($args['signup_required']) ? '1' : '0',
            'data-bbai-banner-free-plan-offer' => (string) max(0, (int) ($args['free_plan_offer'] ?? 50)),
            'data-bbai-banner-low-credit-threshold' => (string) max(0, (int) ($args['low_credit_threshold'] ?? 0)),
            'data-bbai-banner-library-url' => (string) ($args['library_url'] ?? $lib),
        ]
    );

    $cov_total   = max(0, (int) ($args['cov_total'] ?? 0));
    $cov_missing = max(0, (int) ($args['cov_missing'] ?? 0));
    $cov_weak    = max(0, (int) ($args['cov_needs_review'] ?? 0));
    $optimized   = isset($args['optimized_count'])
        ? max(0, (int) $args['optimized_count'])
        : max(0, $cov_total - $cov_missing - $cov_weak);
    $credits_used = max(0, (int) ($args['credits_used'] ?? 0));
    $has_connected = array_key_exists('has_connected_account', $args)
        ? !empty($args['has_connected_account'])
        : !$guest;
    $has_ever = !empty($args['has_ever_generated_alt']) || $optimized > 0 || $credits_used > 0;
    $total_images = $cov_total > 0 ? $cov_total : ($cov_missing + $cov_weak + $optimized);

    $snap_overrides = [
        'missing_count'          => $cov_missing,
        'weak_count'             => $cov_weak,
        'total_images'           => $total_images,
        'credits_used'           => $credits_used,
        'credits_limit'          => max(1, (int) ($args['credits_limit'] ?? 50)),
        'credits_remaining'      => max(0, (int) ($args['credits_remaining'] ?? 0)),
        'usage_percent'          => min(100, max(0, (int) ($args['usage_percent'] ?? 0))),
        'is_pro_plan'            => !empty($args['is_pro']),
        'is_low_credits'         => !empty($args['is_low_credits']),
        'is_out_of_credits'      => !empty($args['is_out_of_credits']),
        'has_ever_generated_alt' => $has_ever,
        'optimized_count'        => $optimized,
        'auth_state'             => (string) ($args['auth_state'] ?? ''),
        'quota_type'             => (string) ($args['quota_type'] ?? ''),
        'quota_state'            => (string) ($args['quota_state'] ?? ''),
        'signup_required'        => !empty($args['signup_required']),
        'upgrade_required'       => !empty($args['upgrade_required']),
        'is_trial'               => $guest,
        'free_plan_offer'        => max(0, (int) ($args['free_plan_offer'] ?? 50)),
        'low_credit_threshold'   => max(0, (int) ($args['low_credit_threshold'] ?? 0)),
        'plan_slug'              => strtolower((string) ($args['plan_slug'] ?? 'free')),
        'plan_label'             => (string) ($args['plan_label'] ?? ''),
        'remaining_line'         => (string) ($args['remaining_line'] ?? ''),
        'reset_timing'           => (string) ($args['reset_timing'] ?? ''),
        'has_connected_account'  => $has_connected,
        'library_url'            => (string) ($args['library_url'] ?? $lib),
        'usage_url'              => (string) ($args['usage_url'] ?? admin_url('admin.php?page=bbai-credit-usage')),
        'settings_url'           => trim((string) ($args['settings_automation_url'] ?? admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload'))),
        'guide_url'              => (string) ($args['guide_url'] ?? admin_url('admin.php?page=bbai-guide')),
    ];
    $billing_arg = trim((string) ($args['billing_portal_url'] ?? ''));
    if ('' !== $billing_arg) {
        $snap_overrides['billing_portal_url'] = $billing_arg;
    }
    foreach (['missing_library_url', 'needs_review_library_url'] as $url_key) {
        if (!empty($args[$url_key])) {
            $snap_overrides[$url_key] = (string) $args[$url_key];
        }
    }

    $snap = bbai_banner_snapshot_merge($snap_overrides);

    $lib_priority = bbai_get_primary_banner_state($snap, BBAI_BANNER_CTX_LIBRARY);

    if (BBAI_BANNER_STATE_NONE !== $lib_priority) {
        $lib_page_var = 'neutral';
        if (BBAI_BANNER_STATE_HEALTHY === $lib_priority || BBAI_BANNER_STATE_FIRST_SUCCESS === $lib_priority) {
            $lib_page_var = 'success';
        } elseif (in_array(
            $lib_priority,
            [
                BBAI_BANNER_STATE_NEEDS_ATTENTION,
                BBAI_BANNER_STATE_LOW_CREDITS,
                BBAI_BANNER_STATE_OUT_OF_CREDITS,
            ],
            true
        )) {
            $lib_page_var = 'warning';
        }

        return bbai_banner_build_command_hero(
            BBAI_BANNER_CTX_LIBRARY,
            $snap,
            [
                'page_hero_variant'   => $lib_page_var,
                'aria_label'          => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                'icon_wrapper_attrs'  => $surface['icon_wrapper_attrs'],
                'headline_attrs'      => $surface['headline_attrs'],
                'subtext_attrs'       => $surface['subtext_attrs'],
                'section_data_attrs'  => $section_base,
            ]
        );
    }

    return bbai_plan_top_banner_build_command_hero(
        BBAI_BANNER_CTX_LIBRARY,
        $plan_input,
        [
            'aria_label'         => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
            'icon_wrapper_attrs' => $surface['icon_wrapper_attrs'],
            'headline_attrs'     => $surface['headline_attrs'],
            'subtext_attrs'      => $surface['subtext_attrs'],
            'section_data_attrs' => $section_base,
        ]
    );
}

/**
 * Build $bbai_command_hero for Usage & Billing (shared banner resolver, usage priority).
 *
 * @param array<string, mixed> $args Snapshot keys for bbai_banner_snapshot_merge plus:
 *     @type string $billing_portal_url
 *     @type string $upgrade_url
 *     @type string $overview_anchor Fragment for primary “Review usage” (default bbai-usage-overview).
 * @return array<string, mixed>
 */
function bbai_page_hero_usage_command_hero(array $args = []): array
{
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

    $billing = trim((string) ($args['billing_portal_url'] ?? ''));
    $upgrade = trim((string) ($args['upgrade_url'] ?? ''));
    $anchor  = ltrim(trim((string) ($args['overview_anchor'] ?? 'bbai-usage-overview')), '#');

    $reserved = ['billing_portal_url', 'upgrade_url', 'overview_anchor', 'wrapper_extra_class', 'section_data_attrs'];
    $snap_in  = array_diff_key($args, array_flip($reserved));

    $snap = bbai_banner_snapshot_merge(
        array_merge(
            $snap_in,
            [
                'billing_portal_url' => $billing,
                'upgrade_url'        => $upgrade,
            ]
        )
    );

    $logical = bbai_banner_pick_state(BBAI_BANNER_CTX_USAGE, $snap);
    $page_var = 'neutral';
    if (BBAI_BANNER_STATE_HEALTHY === $logical) {
        $page_var = 'success';
    } elseif (in_array(
        $logical,
        [BBAI_BANNER_STATE_NEEDS_ATTENTION, BBAI_BANNER_STATE_LOW_CREDITS, BBAI_BANNER_STATE_OUT_OF_CREDITS],
        true
    )) {
        $page_var = 'warning';
    }

    $hero = bbai_banner_build_command_hero(
        BBAI_BANNER_CTX_USAGE,
        $snap,
        [
            'page_hero_variant'   => $page_var,
            'usage_anchor'        => $anchor,
            'aria_label'          => __('Usage and billing', 'beepbeep-ai-alt-text-generator'),
            'show_hero_loop'      => false,
            'wrapper_extra_class' => trim('bbai-usage-page-hero-host ' . (string) ($args['wrapper_extra_class'] ?? '')),
            'section_data_attrs'  => array_merge(
                ['data-bbai-usage-page-hero' => '1'],
                isset($args['section_data_attrs']) && is_array($args['section_data_attrs']) ? $args['section_data_attrs'] : []
            ),
        ]
    );
    $hero['banner_logical_state'] = $logical;
    $usage_slot = bbai_get_active_banner_slot_from_state($logical);
    if (!isset($hero['section_data_attrs']) || !is_array($hero['section_data_attrs'])) {
        $hero['section_data_attrs'] = [];
    }
    $hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($usage_slot ?? '');

    return $hero;
}
