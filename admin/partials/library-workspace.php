<?php
/**
 * ALT Library premium workspace layout.
 *
 * Expects the data/query variables prepared in library-tab.php.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-upgrade-state.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/command-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/page-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

// $bbai_default_review_filter, $bbai_review_filter_from_url: set in library-tab.php before this partial is included.

$bbai_auth_state = isset($bbai_usage_stats['auth_state']) && is_string($bbai_usage_stats['auth_state']) && '' !== trim($bbai_usage_stats['auth_state'])
    ? sanitize_key($bbai_usage_stats['auth_state'])
    : '';
$bbai_quota_type = isset($bbai_usage_stats['quota_type']) && is_string($bbai_usage_stats['quota_type']) && '' !== trim($bbai_usage_stats['quota_type'])
    ? sanitize_key($bbai_usage_stats['quota_type'])
    : '';
$bbai_is_anonymous_trial = ('anonymous' === $bbai_auth_state) || ('trial' === $bbai_quota_type) || !empty($bbai_usage_stats['is_trial']);
$bbai_free_plan_offer = max(0, (int) ($bbai_usage_stats['free_plan_offer'] ?? 50));
$bbai_usage_used_seed = max(0, (int) ($bbai_usage_stats['credits_used'] ?? $bbai_usage_stats['creditsUsed'] ?? $bbai_usage_stats['used'] ?? 0));
$bbai_usage_limit_seed = max(1, (int) ($bbai_usage_stats['credits_total'] ?? $bbai_usage_stats['creditsTotal'] ?? $bbai_usage_stats['limit'] ?? 50));
$bbai_usage_remaining_seed = max(0, (int) ($bbai_usage_stats['credits_remaining'] ?? $bbai_usage_stats['creditsRemaining'] ?? $bbai_usage_stats['remaining'] ?? max(0, $bbai_usage_limit_seed - $bbai_usage_used_seed)));
$bbai_low_credit_threshold = max(
    0,
    (int) ($bbai_usage_stats['low_credit_threshold'] ?? ($bbai_is_anonymous_trial
        ? min(2, max(1, $bbai_usage_limit_seed - 1))
        : BBAI_BANNER_LOW_CREDITS_THRESHOLD))
);
$bbai_quota_state = isset($bbai_usage_stats['quota_state']) && is_string($bbai_usage_stats['quota_state']) && '' !== trim($bbai_usage_stats['quota_state'])
    ? sanitize_key($bbai_usage_stats['quota_state'])
    : ($bbai_usage_remaining_seed <= 0
        ? 'exhausted'
        : ($bbai_usage_remaining_seed <= $bbai_low_credit_threshold
            ? 'near_limit'
            : 'active'));
$bbai_signup_required = !empty($bbai_usage_stats['signup_required']) || ($bbai_is_anonymous_trial && 'exhausted' === $bbai_quota_state);
$bbai_product_state_model = isset($bbai_product_state_model) && is_array($bbai_product_state_model)
    ? $bbai_product_state_model
    : [];
$bbai_locked_cta_mode_ws = isset($bbai_product_state_model['cta']['locked_mode']) ? (string) $bbai_product_state_model['cta']['locked_mode'] : '';

if ('create_account' === $bbai_locked_cta_mode_ws) {
    $bbai_locked_quota_action = 'open-signup';
    $bbai_locked_quota_label  = __('Create a free account to continue', 'beepbeep-ai-alt-text-generator');
    $bbai_locked_auth_attr    = ' data-auth-tab="register"';
} elseif ('manage_plan' === $bbai_locked_cta_mode_ws) {
    $bbai_locked_quota_action = 'open-usage';
    $bbai_locked_quota_label  = __('Usage & billing', 'beepbeep-ai-alt-text-generator');
    $bbai_locked_auth_attr    = '';
} elseif ('upgrade_agency' === $bbai_locked_cta_mode_ws) {
    $bbai_locked_quota_action = 'open-upgrade';
    $bbai_locked_quota_label  = function_exists('bbai_copy_cta_upgrade_agency') ? bbai_copy_cta_upgrade_agency() : __('Upgrade to Agency', 'beepbeep-ai-alt-text-generator');
    $bbai_locked_auth_attr    = '';
} elseif ('upgrade_growth' === $bbai_locked_cta_mode_ws) {
    $bbai_locked_quota_action = 'open-upgrade';
    $bbai_locked_quota_label  = bbai_copy_cta_upgrade_growth();
    $bbai_locked_auth_attr    = '';
} else {
    $bbai_locked_quota_action = $bbai_is_anonymous_trial ? 'open-signup' : 'open-upgrade';
    $bbai_locked_quota_label  = $bbai_is_anonymous_trial
        ? __('Create a free account to continue', 'beepbeep-ai-alt-text-generator')
        : __('Upgrade to continue', 'beepbeep-ai-alt-text-generator');
    $bbai_locked_auth_attr = $bbai_is_anonymous_trial ? ' data-auth-tab="register"' : '';
}

$bbai_build_action = static function (array $config) use ($bbai_limit_reached_state, $bbai_locked_quota_action, $bbai_is_anonymous_trial, $bbai_locked_cta_mode_ws) {
    $config = wp_parse_args(
        $config,
        [
            'label'       => '',
            'action'      => '',
            'attrs'       => '',
            'locked'      => false,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-workspace',
        ]
    );

    $attrs = trim((string) $config['attrs']);

    if (!empty($config['action'])) {
        $attrs .= ' data-action="' . esc_attr($config['action']) . '"';
    }

    $config['is_locked'] = false;
    if (!empty($config['locked']) && $bbai_limit_reached_state) {
        $attrs .= ' data-bbai-action="' . esc_attr($bbai_locked_quota_action) . '"';
        $attrs .= ' data-bbai-locked-cta="1"';
        $attrs .= ' data-bbai-lock-reason="' . esc_attr((string) $config['lock_reason']) . '"';
        $attrs .= ' data-bbai-locked-source="' . esc_attr((string) $config['lock_source']) . '"';
        $attrs .= ' aria-disabled="true"';
        if ($bbai_is_anonymous_trial) {
            $attrs .= ' data-auth-tab="register"';
        }
        if ('upgrade_agency' === $bbai_locked_cta_mode_ws) {
            $attrs .= ' data-bbai-pricing-variant="agency"';
        } elseif ('upgrade_growth' === $bbai_locked_cta_mode_ws) {
            $attrs .= ' data-bbai-pricing-variant="growth"';
        }
        $config['is_locked'] = true;
    }

    $config['attrs'] = trim($attrs);

    return $config;
};

$bbai_scan_action = $bbai_build_action(
    [
        'label' => bbai_copy_cta_scan_media_library(),
        'attrs' => 'data-bbai-action="scan-opportunity"',
    ]
);

$bbai_review_optimized_action = $bbai_build_action(
    [
        'label' => bbai_copy_cta_review_optimized_images(),
        'attrs' => 'data-bbai-filter-target="optimized"',
    ]
);

$bbai_scan_new_uploads_action = $bbai_build_action(
    [
        'label'  => bbai_copy_cta_scan_new_uploads(),
        'action' => 'rescan-media-library',
    ]
);

$bbai_scan_manual_action = $bbai_build_action(
    [
        'label'  => bbai_copy_cta_rescan_media_library(),
        'action' => 'rescan-media-library',
    ]
);

$bbai_settings_automation_url = $bbai_is_anonymous_trial
    ? ''
    : admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload');
$bbai_review_usage_url        = $bbai_is_anonymous_trial
    ? admin_url('admin.php?page=bbai')
    : admin_url('admin.php?page=bbai-credit-usage');

$bbai_surface_automation_action = $bbai_is_anonymous_trial
    ? $bbai_build_action(
        [
            'label' => __('Create free account', 'beepbeep-ai-alt-text-generator'),
            'attrs' => 'data-action="show-auth-modal" data-auth-tab="register"',
        ]
    )
    : ($bbai_is_pro
    ? $bbai_build_action(
        [
            'label' => bbai_copy_cta_automation_settings(),
            'attrs' => 'href="' . esc_url($bbai_settings_automation_url) . '"',
        ]
    )
    : $bbai_build_action(
        [
            'label' => bbai_copy_cta_enable_auto_optimization(),
            'attrs' => 'data-bbai-action="open-upgrade" data-bbai-locked-cta="1" data-bbai-lock-reason="automation" data-bbai-locked-source="library-summary-automation"',
    ]
));

if ($bbai_cov_missing > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_fix_missing_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_generate_alt(),
            'action'      => 'generate-missing',
            'locked'      => true,
            'lock_reason' => 'generate_missing',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_next_task();
    $bbai_task_title   = bbai_copy_library_missing_alt_headline($bbai_cov_missing);
    $bbai_task_copy    = bbai_copy_helper_generate_then_review();

    $bbai_queue_title = bbai_copy_library_missing_alt_headline($bbai_cov_missing);
    $bbai_queue_copy  = bbai_copy_helper_queue_missing_then_review();
} elseif ($bbai_cov_needs_review > 0) {
    $bbai_header_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-header-primary',
        ]
    );
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-summary-primary',
        ]
    );
    $bbai_task_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-task-banner-primary',
        ]
    );
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label'       => bbai_copy_cta_improve_alt(),
            'action'      => 'regenerate-all',
            'attrs'       => 'data-bbai-regenerate-scope="needs-review" data-bbai-generation-source="regenerate-weak"',
            'locked'      => true,
            'lock_reason' => 'reoptimize_all',
            'lock_source' => 'library-sidebar-queue',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_descriptions_can_improve();
    $bbai_task_title   = bbai_copy_library_needs_review_headline($bbai_cov_needs_review);
    $bbai_task_copy    = bbai_copy_helper_needs_review_actions();

    $bbai_queue_title = bbai_copy_queue_needs_review_title($bbai_cov_needs_review);
    $bbai_queue_copy  = bbai_copy_helper_finish_review_queue();
} else {
    $bbai_header_primary_action = $bbai_surface_automation_action;
    $bbai_summary_primary_action = $bbai_build_action(
        [
            'label' => bbai_copy_cta_review_optimized_images(),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );
    $bbai_task_primary_action = $bbai_surface_automation_action;
    $bbai_queue_primary_action = $bbai_build_action(
        [
            'label' => bbai_copy_cta_review_optimized_images(),
            'attrs' => 'data-bbai-filter-target="optimized"',
        ]
    );

    $bbai_task_eyebrow = bbai_copy_section_all_images_current();
    $bbai_task_title   = bbai_copy_library_fully_optimized_title();
    $bbai_task_copy    = bbai_copy_helper_healthy_keep_coverage();

    $bbai_queue_title = bbai_copy_queue_clear_title();
    $bbai_queue_copy  = bbai_copy_helper_healthy_keep_coverage();
}

$bbai_total_automated_images = max(
    (int) ($bbai_usage_stats['credits_used'] ?? $bbai_usage_stats['creditsUsed'] ?? $bbai_usage_stats['used'] ?? 0),
    $bbai_cov_optimized,
    $bbai_cov_auto
);
$bbai_saved_minutes = max(1, (int) ceil($bbai_total_automated_images * 0.5));
$bbai_saved_time_label = sprintf(
    /* translators: %d: estimated whole minutes of manual work saved by automation. */
    _n('~%d minute', '~%d minutes', $bbai_saved_minutes, 'beepbeep-ai-alt-text-generator'),
    $bbai_saved_minutes
);

$bbai_usage_used = $bbai_usage_used_seed;
$bbai_usage_limit = $bbai_usage_limit_seed;
$bbai_usage_remaining = $bbai_usage_remaining_seed;
$bbai_usage_pct = $bbai_usage_limit > 0 ? min(100, (int) round(($bbai_usage_used / $bbai_usage_limit) * 100)) : 0;
$bbai_usage_line = $bbai_is_anonymous_trial
    ? sprintf(
        /* translators: %1$s: trial generations used (formatted). %2$s: trial generation limit (formatted). */
        __('%1$s of %2$s free trial generations used', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_used),
        number_format_i18n($bbai_usage_limit)
    )
    : sprintf(
        /* translators: %1$s: AI generations used this cycle (formatted). %2$s: plan generation limit (formatted). */
        __('%1$s of %2$s AI generations used', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n($bbai_usage_used),
        number_format_i18n($bbai_usage_limit)
    );
$bbai_usage_copy = $bbai_is_anonymous_trial
    ? ($bbai_usage_remaining > 0
        ? sprintf(
            /* translators: %s: number of remaining trial generations (formatted). */
            _n('%s trial generation remaining', '%s trial generations remaining', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_usage_remaining)
        )
        : sprintf(
            /* translators: %d: free account generations per month. */
            __('Free trial complete. Create a free account to unlock %d generations per month.', 'beepbeep-ai-alt-text-generator'),
            $bbai_free_plan_offer
        ))
    : ($bbai_usage_remaining > 0
        ? sprintf(
            /* translators: %s: number of remaining credits this billing cycle (formatted). */
            _n('%s credit remaining this cycle', '%s credits remaining this cycle', $bbai_usage_remaining, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_usage_remaining)
        )
        : __('No credits remaining this cycle', 'beepbeep-ai-alt-text-generator'));
$bbai_optimized_count = $bbai_cov_optimized;
$bbai_missing_count = $bbai_cov_missing;
$bbai_weak_count = $bbai_cov_needs_review;
$bbai_total_count = $bbai_cov_total;
$bbai_library_all_count = $bbai_optimized_count + $bbai_weak_count + $bbai_missing_count;
$bbai_credits_used = $bbai_usage_used;
$bbai_credits_limit = $bbai_usage_limit;
$bbai_credits_remaining = $bbai_usage_remaining;
$bbai_is_healthy = 0 === $bbai_missing_count && 0 === $bbai_weak_count && $bbai_library_all_count > 0;
// Default workspace filter: all (single active tab). Deep links (?status=) still apply via $bbai_review_filter_from_url.
if (!$bbai_review_filter_from_url) {
    $bbai_default_review_filter = 'all';
}
if (!in_array($bbai_default_review_filter, ['all', 'missing', 'optimized', 'weak'], true)) {
    $bbai_default_review_filter = 'all';
}
$bbai_is_low_credits = $bbai_credits_remaining > 0 && $bbai_credits_remaining <= $bbai_low_credit_threshold;
$bbai_is_out_of_credits = 0 === $bbai_credits_remaining;
$bbai_has_search_query = false;

$bbai_lib_has_connected = empty($bbai_is_guest_trial);
$bbai_lib_plan_slug     = $bbai_lib_has_connected
    ? strtolower((string) ($bbai_usage_stats['plan'] ?? 'free'))
    : 'free';

$bbai_command_hero = bbai_page_hero_library_command_hero(
    [
        'cov_total'                => $bbai_library_all_count,
        'cov_missing'              => $bbai_cov_missing,
        'cov_needs_review'         => $bbai_cov_needs_review,
        'is_pro'                   => $bbai_is_pro,
        'settings_automation_url'  => $bbai_settings_automation_url,
        'is_low_credits'           => $bbai_is_low_credits,
        'is_out_of_credits'        => $bbai_is_out_of_credits,
        'credits_remaining'        => $bbai_credits_remaining,
        'credits_used'             => $bbai_credits_used,
        'credits_limit'            => $bbai_credits_limit,
        'usage_percent'            => $bbai_usage_pct,
        'library_url'              => add_query_arg(['page' => 'bbai-library'], admin_url('admin.php')),
        'missing_library_url'      => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
        'needs_review_library_url' => bbai_alt_library_needs_review_url(),
        'usage_url'                => $bbai_review_usage_url,
        'billing_portal_url'       => (string) Usage_Tracker::get_billing_portal_url(),
        'plan_label'               => $bbai_is_anonymous_trial
            ? __('Free trial', 'beepbeep-ai-alt-text-generator')
            : (isset($bbai_usage_stats['plan_label']) ? (string) $bbai_usage_stats['plan_label'] : ''),
        'auth_state'               => $bbai_auth_state,
        'quota_type'               => $bbai_quota_type,
        'quota_state'              => $bbai_quota_state,
        'signup_required'          => $bbai_signup_required,
        'has_connected_account'    => $bbai_lib_has_connected,
        'plan_slug'                => $bbai_lib_plan_slug,
        'is_agency'                => !empty($bbai_is_agency),
        'free_plan_offer'          => $bbai_free_plan_offer,
        'low_credit_threshold'     => $bbai_low_credit_threshold,
        'is_guest_trial'           => $bbai_is_anonymous_trial,
        'remaining_line'           => $bbai_usage_copy,
        'reset_timing'             => $bbai_is_anonymous_trial
            ? sprintf(
                /* translators: %d: free account generations per month. */
                __('Create a free account for %d generations per month', 'beepbeep-ai-alt-text-generator'),
                $bbai_free_plan_offer
            )
            : (isset($bbai_usage_stats['days_until_reset'])
            ? sprintf(
                /* translators: %d: days until credit reset */
                _n('%d day until reset', '%d days until reset', (int) $bbai_usage_stats['days_until_reset'], 'beepbeep-ai-alt-text-generator'),
                (int) $bbai_usage_stats['days_until_reset']
            )
            : ''),
    ]
);

$bbai_workspace_banner_slot = bbai_get_active_banner_slot_from_state((string) ($bbai_command_hero['banner_logical_state'] ?? ''));
if (!isset($bbai_command_hero['section_data_attrs']) || !is_array($bbai_command_hero['section_data_attrs'])) {
    $bbai_command_hero['section_data_attrs'] = [];
}
$bbai_command_hero['wrapper_extra_class'] = trim(((string) ($bbai_command_hero['wrapper_extra_class'] ?? '')) . ' bbai-library-top-hero-host');
$bbai_command_hero['section_extra_class'] = trim(((string) ($bbai_command_hero['section_extra_class'] ?? '')) . ' bbai-library-top-hero');
$bbai_command_hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($bbai_workspace_banner_slot ?? '');
$bbai_command_hero['section_data_attrs']['data-bbai-auth-state'] = (string) $bbai_auth_state;
$bbai_command_hero['section_data_attrs']['data-bbai-quota-type'] = (string) $bbai_quota_type;
$bbai_command_hero['section_data_attrs']['data-bbai-quota-state'] = (string) $bbai_quota_state;
$bbai_command_hero['section_data_attrs']['data-bbai-signup-required'] = $bbai_signup_required ? '1' : '0';
$bbai_command_hero['section_data_attrs']['data-bbai-free-plan-offer'] = (string) $bbai_free_plan_offer;

$bbai_surface_semantic = (string) ($bbai_command_hero['semantic_state'] ?? 'healthy');
$bbai_surface_state    = 'healthy';
if ('empty' === $bbai_surface_semantic) {
    $bbai_surface_state = 'empty';
} elseif ('attention_missing' === $bbai_surface_semantic) {
    $bbai_surface_state = 'missing';
} elseif ('attention_weak' === $bbai_surface_semantic) {
    $bbai_surface_state = 'weak';
} elseif ('low_credits' === $bbai_surface_semantic) {
    $bbai_surface_state = 'low_credits';
} elseif ('out_of_credits' === $bbai_surface_semantic) {
    $bbai_surface_state = 'out_of_credits';
} elseif (0 === strpos($bbai_surface_semantic, 'plan-')) {
    $bbai_surface_state = 'healthy';
}

$bbai_state = [
    'total_images'        => $bbai_library_all_count,
    'optimized_count'     => $bbai_cov_optimized,
    'missing_alts'        => $bbai_cov_missing,
    'needs_review_count'  => $bbai_cov_needs_review,
    'has_scan_results'    => $bbai_library_all_count > 0,
    'last_scan_timestamp' => time(),
];

// Row states: precomputed in library-tab.php for the current page when the workspace dataset pipeline runs.
if (!isset($bbai_library_row_states) || !is_array($bbai_library_row_states)) {
    $bbai_library_row_states = [];
    $bbai_library_images_for_table = isset($bbai_all_images) && is_array($bbai_all_images) ? $bbai_all_images : [];
    if (!empty($bbai_library_images_for_table)) {
        foreach ($bbai_library_images_for_table as $bbai_row_idx => $bbai_row_image) {
            $bbai_library_row_states[ $bbai_row_idx ] = $this->get_library_workspace_row_state($bbai_row_image);
        }
    }
}

// Filter chip counts: from full normalized dataset (library-tab.php); fallback to coverage fields.
if (!isset($bbai_table_filter_counts) || !is_array($bbai_table_filter_counts)) {
    $bbai_table_filter_counts = [
        'all'       => max(0, (int) $bbai_cov_total),
        'missing'   => max(0, (int) $bbai_cov_missing),
        'weak'      => max(0, (int) $bbai_cov_needs_review),
        'optimized' => max(0, (int) $bbai_cov_optimized),
    ];
}
$bbai_has_filters = $bbai_table_filter_counts['all'] > 0;

$bbai_results_total = isset($bbai_library_filtered_total) ? (int) $bbai_library_filtered_total : (int) $bbai_total_count;
$bbai_row_count       = isset($bbai_all_images) && is_array($bbai_all_images) ? count($bbai_all_images) : 0;
$bbai_show_start      = $bbai_row_count > 0 ? $bbai_offset + 1 : 0;
$bbai_show_end        = $bbai_row_count > 0 ? min($bbai_offset + $bbai_row_count, $bbai_results_total) : 0;

// Hint under results heading: whether the current page has any missing/weak rows (not global counts).
$bbai_page_missing_or_weak_count = 0;
foreach ($bbai_library_row_states as $bbai_rs_hint) {
    if (!empty($bbai_rs_hint['status']) && in_array($bbai_rs_hint['status'], ['missing', 'weak'], true)) {
        ++$bbai_page_missing_or_weak_count;
    }
}

// Status filters: All → Missing → Needs review → Optimized (data-filter weak = needs review queue).
$bbai_library_workspace_filter_items = [
    [
        'key'               => 'all',
        'label'             => __('All', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['all'],
        'active'            => $bbai_default_review_filter === 'all',
        'filter_label_attr' => __('All', 'beepbeep-ai-alt-text-generator'),
        'problem'           => false,
    ],
    [
        'key'               => 'missing',
        'label'             => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['missing'],
        'active'            => $bbai_default_review_filter === 'missing',
        'filter_label_attr' => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'problem'           => $bbai_table_filter_counts['missing'] > 0,
    ],
    [
        'key'               => 'weak',
        'label'             => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['weak'],
        'active'            => $bbai_default_review_filter === 'weak',
        'filter_label_attr' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'problem'           => $bbai_table_filter_counts['weak'] > 0,
    ],
    [
        'key'               => 'optimized',
        'label'             => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'count'             => $bbai_table_filter_counts['optimized'],
        'active'            => $bbai_default_review_filter === 'optimized',
        'filter_label_attr' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'problem'           => false,
    ],
];
?>

<div
    class="bbai-library-container bbai-library-workspace bbai-container"
    data-bbai-library-workspace-root="1"
    data-bbai-dashboard-state-root="1"
    data-bbai-dashboard-state="<?php echo esc_attr((string) ($bbai_product_state_model['state'] ?? '')); ?>"
    data-bbai-dashboard-base-state="<?php echo esc_attr((string) ($bbai_product_state_model['base_state'] ?? '')); ?>"
    data-bbai-dashboard-runtime-state="<?php echo esc_attr((string) ($bbai_product_state_model['runtime_state'] ?? 'idle')); ?>"
    data-bbai-current-page="alt-library"
    data-bbai-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library'], admin_url('admin.php'))); ?>"
    data-bbai-missing-library-url="<?php echo esc_url(add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'))); ?>"
    data-bbai-needs-review-library-url="<?php echo esc_url(bbai_alt_library_needs_review_url()); ?>"
    data-bbai-usage-url="<?php echo esc_url($bbai_review_usage_url); ?>"
    data-bbai-guide-url="<?php echo esc_url(admin_url('admin.php?page=bbai-guide')); ?>"
    data-bbai-automation-settings-url="<?php echo esc_url($bbai_settings_automation_url); ?>"
    data-bbai-empty-filter="<?php echo esc_attr__('No images match this filter.', 'beepbeep-ai-alt-text-generator'); ?>"
    data-bbai-empty-filter-hint="<?php echo esc_attr__('Try another filter or show all images.', 'beepbeep-ai-alt-text-generator'); ?>"
    data-bbai-auth-state="<?php echo esc_attr($bbai_auth_state); ?>"
    data-bbai-quota-type="<?php echo esc_attr($bbai_quota_type); ?>"
    data-bbai-quota-state="<?php echo esc_attr($bbai_quota_state); ?>"
    data-bbai-signup-required="<?php echo esc_attr($bbai_signup_required ? '1' : '0'); ?>"
    data-bbai-free-plan-offer="<?php echo esc_attr($bbai_free_plan_offer); ?>"
    data-bbai-low-credit-threshold="<?php echo esc_attr($bbai_low_credit_threshold); ?>"
    data-bbai-credits-used="<?php echo esc_attr((string) $bbai_credits_used); ?>"
    data-bbai-credits-total="<?php echo esc_attr((string) $bbai_credits_limit); ?>"
    data-bbai-credits-remaining="<?php echo esc_attr((string) $bbai_credits_remaining); ?>"
    data-bbai-is-guest-trial="<?php echo esc_attr($bbai_is_anonymous_trial ? '1' : '0'); ?>"
    data-bbai-trial-limit="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['limit'] ?? 0)); ?>"
    data-bbai-trial-used="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['used'] ?? 0)); ?>"
    data-bbai-trial-remaining="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['remaining'] ?? 0)); ?>"
    data-bbai-trial-exhausted="<?php echo esc_attr(!empty($bbai_product_state_model['trial']['exhausted']) ? '1' : '0'); ?>"
    data-bbai-locked-cta-mode="<?php echo esc_attr((string) ($bbai_product_state_model['cta']['locked_mode'] ?? '')); ?>"
    data-bbai-free-account-monthly-limit="<?php echo esc_attr((string) ($bbai_product_state_model['trial']['monthly_free_limit'] ?? $bbai_free_plan_offer)); ?>"
    data-bbai-is-healthy="<?php echo $bbai_is_healthy ? 'true' : 'false'; ?>"
    data-bbai-is-low-credits="<?php echo $bbai_is_low_credits ? 'true' : 'false'; ?>"
    data-bbai-is-out-of-credits="<?php echo $bbai_is_out_of_credits ? 'true' : 'false'; ?>"
    data-bbai-is-pro-plan="<?php echo $bbai_is_pro ? 'true' : 'false'; ?>"
    data-bbai-has-filters="<?php echo $bbai_has_filters ? 'true' : 'false'; ?>"
    data-bbai-has-search-query="<?php echo $bbai_has_search_query ? 'true' : 'false'; ?>"
    data-bbai-total-count="<?php echo esc_attr((string) $bbai_cov_total); ?>"
    data-bbai-missing-count="<?php echo esc_attr((string) $bbai_cov_missing); ?>"
    data-bbai-weak-count="<?php echo esc_attr((string) $bbai_cov_needs_review); ?>"
    data-bbai-optimized-count="<?php echo esc_attr((string) $bbai_cov_optimized); ?>"
    data-bbai-library-server-filter="1"
>
    <?php
    /* Workspace surface styles now live in assets/css/system/bbai-admin-library-workspace-polish.css. */
    /* Queue workflow suppressed — replaced by new premium top-card layout below. */
    $bbai_queue_workflow_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/queue-workflow.php';
    ?>

    <!-- ── Library surface: shared banner + compact summary (scan JS: data-bbai-coverage-card) ── -->
    <div
        id="bbai-alt-coverage-card"
        class="bbai-library-surface-root"
        data-bbai-coverage-card="1"
        data-bbai-library-surface="1"
        data-state="<?php echo esc_attr($bbai_surface_state); ?>"
        data-bbai-free-plan-limit="<?php echo esc_attr((int) (($bbai_coverage ?? [])['free_plan_limit'] ?? 50)); ?>"
    >
        <?php bbai_ui_render('bbai-banner', ['command_hero' => $bbai_command_hero]); ?>

        <div class="bbai-sr-only" aria-hidden="true">
            <span data-bbai-library-optimized><?php echo esc_html(number_format_i18n($bbai_optimized_count)); ?></span>
            <span data-bbai-library-weak><?php echo esc_html(number_format_i18n($bbai_weak_count)); ?></span>
            <span data-bbai-library-missing><?php echo esc_html(number_format_i18n($bbai_missing_count)); ?></span>
            <span data-bbai-coverage-total><?php echo esc_html(number_format_i18n($bbai_library_all_count)); ?></span>
            <p data-bbai-library-progress-label>
                <?php
                echo esc_html(
                    $bbai_cov_opt_pct >= 100
                        ? __('Fully optimized', 'beepbeep-ai-alt-text-generator')
                        : sprintf(
                            /* translators: %s: percentage of library images with optimized ALT text (formatted). */
                            __('%s%% optimized', 'beepbeep-ai-alt-text-generator'),
                            number_format_i18n($bbai_cov_opt_pct)
                        )
                );
                ?>
            </p>
            <span data-bbai-coverage-score-inline><?php echo esc_html(sprintf(
                /* translators: %s: coverage percentage number (formatted; literal %% becomes % in output). */
                __('%s%%', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_cov_opt_pct)
            )); ?></span>
            <div>
                <span data-bbai-library-progress-optimized style="flex-basis: <?php echo esc_attr($bbai_cov_opt_pct); ?>%;"></span>
                <span data-bbai-library-progress-weak style="flex-basis: <?php echo esc_attr($bbai_cov_review_pct); ?>%;"></span>
                <span data-bbai-library-progress-missing style="flex-basis: <?php echo esc_attr($bbai_cov_miss_pct); ?>%;"></span>
                </div>
            <div data-bbai-library-core-usage>
                <p data-bbai-library-usage-line><strong><?php echo esc_html($bbai_usage_line); ?></strong></p>
                <div role="progressbar" data-bbai-library-usage-progressbar aria-valuenow="<?php echo esc_attr($bbai_usage_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <span data-bbai-library-usage-progress style="width: <?php echo esc_attr($bbai_usage_pct); ?>%;"></span>
            </div>
                <p data-bbai-library-usage-copy><?php echo esc_html($bbai_usage_copy); ?></p>
                <span class="bbai-sr-only" data-bbai-library-credits-remaining><?php echo esc_html(number_format_i18n($bbai_credits_remaining)); ?></span>
                    </div>
                </div>
    </div>

    <section class="bbai-library-scan-feedback" data-bbai-scan-feedback-banner data-state="attention" hidden>
        <div class="bbai-library-scan-feedback__copy" role="status" aria-live="polite" aria-atomic="true">
            <p class="bbai-library-scan-feedback__eyebrow"><?php esc_html_e('Scan complete', 'beepbeep-ai-alt-text-generator'); ?></p>
            <p class="bbai-library-scan-feedback__summary" data-bbai-scan-feedback-summary></p>
            <p class="bbai-library-scan-feedback__detail" data-bbai-scan-feedback-detail hidden></p>
        </div>
        <div class="bbai-library-scan-feedback__actions">
            <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="jump-scan-results" hidden><?php esc_html_e('View results', 'beepbeep-ai-alt-text-generator'); ?></button>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="dismiss-scan-feedback"><?php esc_html_e('Close', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </section>

    <?php
    $bbai_ws_pages      = isset($bbai_total_pages) ? max(1, (int) $bbai_total_pages) : 1;
    $bbai_ws_multi_page = $bbai_ws_pages > 1;
    $bbai_ws_meta_main  = '';
    if ($bbai_results_total < 1) {
        $bbai_ws_meta_main = __('No images', 'beepbeep-ai-alt-text-generator');
    } elseif ($bbai_row_count < 1) {
        $bbai_ws_meta_main = sprintf(
            /* translators: %s: total matching images */
            __('0 of %s', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_results_total)
        );
    } elseif ($bbai_ws_multi_page) {
        $bbai_ws_meta_main = sprintf(
            /* translators: 1: start rank, 2: end rank, 3: total in current result set */
            __('Showing %1$s–%2$s of %3$s', 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_show_start),
            number_format_i18n($bbai_show_end),
            number_format_i18n($bbai_results_total)
        );
    } else {
        $bbai_ws_meta_main = sprintf(
            /* translators: %s: formatted image count */
            _n('%s image', '%s images', $bbai_results_total, 'beepbeep-ai-alt-text-generator'),
            number_format_i18n($bbai_results_total)
        );
    }
    ?>
    <div class="bbai-library-workspace-card bbai-card bbai-dashboard-card bbai-command-card bbai-command-card--workspace">
        <header class="bbai-library-workspace-card__head bbai-ui-section-header bbai-section-header bbai-section-header--split-end" aria-labelledby="bbai-library-results-title">
            <div class="bbai-ui-section-header__text">
                <h2 id="bbai-library-results-title" class="bbai-ui-section-header__title bbai-command-card__title bbai-section-title bbai-card-title" data-bbai-results-heading tabindex="-1"><?php esc_html_e('ALT Library', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>
            <p class="bbai-command-card__meta bbai-section-meta" aria-live="polite"><?php echo esc_html($bbai_ws_meta_main); ?></p>
        </header>

        <div class="bbai-library-workspace-card__filter-toolbar">
            <div
                id="bbai-review-filter-tabs"
                class="bbai-alt-review-filters bbai-library-workspace-card__filters"
                role="group"
                aria-label="<?php esc_attr_e('Filter images by status', 'beepbeep-ai-alt-text-generator'); ?>"
                data-bbai-default-filter="<?php echo esc_attr($bbai_default_review_filter); ?>"
            >
                <div class="bbai-alt-review-filters__list">
                    <?php foreach ($bbai_library_workspace_filter_items as $bbai_filt_item) : ?>
                        <?php
                        if (!is_array($bbai_filt_item)) {
                            continue;
                        }
                        $bbai_fk          = isset($bbai_filt_item['key']) ? (string) $bbai_filt_item['key'] : '';
                        $bbai_data_filter = ('weak' === $bbai_fk) ? 'weak' : $bbai_fk;
                        if ('' === $bbai_data_filter) {
                            continue;
                        }
                        $bbai_f_active  = !empty($bbai_filt_item['active']);
                        $bbai_f_problem = !empty($bbai_filt_item['problem']);
                        $bbai_f_count   = isset($bbai_filt_item['count']) ? (int) $bbai_filt_item['count'] : 0;
                        $bbai_f_label   = isset($bbai_filt_item['label']) ? (string) $bbai_filt_item['label'] : '';
                        $bbai_fl_attr   = isset($bbai_filt_item['filter_label_attr']) ? (string) $bbai_filt_item['filter_label_attr'] : $bbai_f_label;
                        $bbai_btn_class = 'bbai-filter-pill bbai-alt-review-filters__btn';
                        if ('all' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--all';
                        } elseif ('missing' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--missing';
                        } elseif ('weak' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--needs-review';
                        } elseif ('optimized' === $bbai_data_filter) {
                            $bbai_btn_class .= ' bbai-filter-pill--optimized';
                        }
                        if ($bbai_f_active) {
                            $bbai_btn_class .= ' bbai-alt-review-filters__btn--active';
                        }
                        if ($bbai_f_problem) {
                            $bbai_btn_class .= ' bbai-alt-review-filters__btn--problem';
                        }
                        ?>
                        <button
                            type="button"
                            class="<?php echo esc_attr($bbai_btn_class); ?>"
                            data-filter="<?php echo esc_attr($bbai_data_filter); ?>"
                            data-bbai-filter-label="<?php echo esc_attr($bbai_fl_attr); ?>"
                            aria-pressed="<?php echo $bbai_f_active ? 'true' : 'false'; ?>"
                            <?php echo $bbai_f_active ? ' aria-current="true"' : ''; ?>
                        >
                            <span class="bbai-alt-review-filters__label"><?php echo esc_html($bbai_f_label); ?></span>
                            <span class="bbai-alt-review-filters__count"><?php echo esc_html(number_format_i18n($bbai_f_count)); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div
            id="bbai-library-missing-bulk-bar"
            class="bbai-library-missing-bulk-bar"
            data-bbai-missing-bulk-bar="1"
            hidden
        >
            <div class="bbai-library-missing-bulk-bar__inner">
                <div class="bbai-library-missing-bulk-bar__ready" data-bbai-missing-bulk-ready>
                    <button
                        type="button"
                        class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>"
                        id="bbai-generate-all-missing"
                        data-action="generate-missing"
                        data-bbai-lock-preserve-label="1"
                        data-bbai-locked-source="library-missing-generate-all"
                        <?php if ($bbai_limit_reached_state) : ?>
                        data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>"
                        data-bbai-locked-cta="1"
                        data-bbai-lock-reason="generate_missing"
                        aria-disabled="true"
                        <?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?>
                        <?php endif; ?>
                    ><?php esc_html_e('Generate all missing ALT text', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <p class="bbai-library-missing-bulk-bar__helper" data-bbai-missing-bulk-helper aria-live="polite"></p>
                </div>
                <div class="bbai-library-missing-bulk-bar__no-credits" data-bbai-missing-bulk-no-credits hidden>
                    <p class="bbai-library-missing-bulk-bar__no-credits-text">
                        <?php esc_html_e('No credits remaining. Create an account or upgrade to continue fixing missing ALT text.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <button
                        type="button"
                        class="bbai-btn bbai-btn-secondary bbai-btn-sm"
                        data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>"
                        data-bbai-locked-cta="1"
                        data-bbai-lock-reason="generate_missing"
                        data-bbai-locked-source="library-missing-generate-all-upgrade"
                        aria-disabled="true"
                        <?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?>
                    ><?php echo esc_html($bbai_locked_quota_label); ?></button>
                </div>
            </div>
        </div>

        <div class="bbai-library-workspace-card__table-surface">
        <div class="bbai-library-workspace-card__controls-row" id="bbai-library-toolbar" aria-live="polite">
            <label class="bbai-library-search" for="bbai-library-search">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"></circle>
                    <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                </svg>
                <input type="text" id="bbai-library-search" class="bbai-input" placeholder="<?php esc_attr_e('Search ALT text or filenames', 'beepbeep-ai-alt-text-generator'); ?>" />
            </label>
            <label class="bbai-sr-only" for="bbai-library-sort"><?php esc_html_e('Sort images', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select id="bbai-library-sort" class="bbai-library-select bbai-select bbai-input">
                <option value="recently-updated" selected><?php esc_html_e('Recently updated', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="score-asc"><?php esc_html_e('Lowest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="score-desc"><?php esc_html_e('Highest score first', 'beepbeep-ai-alt-text-generator'); ?></option>
            </select>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-library-bulk-toggle" id="bbai-library-bulk-toggle" data-action="toggle-library-bulk-mode" aria-pressed="false"><?php esc_html_e('Select multiple', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>

    <main class="bbai-workspace bbai-library-main" data-bbai-bulk-mode="false">
            <?php if (!empty($bbai_all_images)) : ?>
                <div class="bbai-library-selection-bar" id="bbai-library-selection-bar" aria-live="polite" hidden>
                    <div class="bbai-selection-bar-left">
                        <label class="bbai-selection-all-label" for="bbai-select-all">
                            <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <span><?php esc_html_e('Select page', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </label>
                        <span class="bbai-selection-bar-sep" aria-hidden="true">|</span>
                        <span class="bbai-selection-bar-count" data-bbai-selected-count>0 <?php esc_html_e('selected', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <span class="bbai-library-selection-bar__credits" data-bbai-selected-credits></span>
                    </div>
                    <div class="bbai-library-selection-bar__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-generate" data-action="generate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>" data-bbai-locked-cta="1" data-bbai-lock-reason="generate_missing" data-bbai-locked-source="library-selection-bar-generate" aria-disabled="true"<?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?><?php endif; ?>><?php echo esc_html(bbai_copy_cta_generate_alt()); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>" id="bbai-batch-regenerate" data-action="regenerate-selected" data-bbai-lock-preserve-label="1"<?php if ($bbai_limit_reached_state) : ?> data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>" data-bbai-locked-cta="1" data-bbai-lock-reason="reoptimize_all" data-bbai-locked-source="library-selection-bar-regenerate" aria-disabled="true"<?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?><?php endif; ?>><?php esc_html_e('Regenerate ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-reviewed" data-action="mark-reviewed"><?php esc_html_e('Mark as reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-clear-alt" data-action="clear-alt-selected"><?php esc_html_e('Delete ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-batch-export" data-action="export-alt-text"><?php esc_html_e('Export', 'beepbeep-ai-alt-text-generator'); ?></button>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-selection-clear" id="bbai-batch-clear" data-action="clear-selection"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></button>
                    </div>
                </div>

                <section id="bbai-alt-table" class="bbai-library-table-shell bbai-table bbai-table--workspace" data-bbai-scan-results-section aria-labelledby="bbai-library-results-title">
                    <div class="bbai-library-table-head bbai-library-table-head--bulk-only">
                        <label class="bbai-library-head-bulk bbai-library-head-bulk--selectall">
                            <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images on this page', 'beepbeep-ai-alt-text-generator'); ?>" title="<?php esc_attr_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?>" />
                            <span class="bbai-library-head-bulk__label"><?php esc_html_e('Select all on this page', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </label>
                    </div>

                    <div class="bbai-table-wrap bbai-table__scroll">
                        <div id="bbai-library-table-body" class="bbai-library-review-queue bbai-table__body" role="list">
                                <?php foreach ($bbai_all_images as $bbai_row_idx => $bbai_image) : ?>
                                    <?php
                                    $bbai_attachment_id = $bbai_image->ID;
                                    $bbai_rs = $bbai_library_row_states[ $bbai_row_idx ] ?? $this->get_library_workspace_row_state($bbai_image);
                                    $bbai_current_alt = $bbai_image->alt_text ?? '';
                                    $bbai_clean_alt = $bbai_rs['clean_alt'];
                                    $bbai_has_alt = !empty($bbai_rs['has_alt']);

                                    $bbai_thumb_url = wp_get_attachment_image_src($bbai_attachment_id, 'thumbnail');
                                    $bbai_attached_file = get_attached_file($bbai_attachment_id);
                                    $bbai_filename = $bbai_attached_file ? basename($bbai_attached_file) : '';
                                    $bbai_modified_date = date_i18n('j M Y', strtotime($bbai_image->post_modified));
                                    $bbai_created_timestamp = strtotime((string) $bbai_image->post_date);
                                    $bbai_updated_timestamp = strtotime((string) $bbai_image->post_modified);

                                    $bbai_alt_preview = $bbai_clean_alt ?: '';
                                    $bbai_alt_preview_id = 'bbai-alt-preview-' . $bbai_attachment_id;
                                    $bbai_alt_char_count = function_exists('mb_strlen') ? mb_strlen($bbai_alt_preview) : strlen($bbai_alt_preview);
                                    $bbai_has_long_alt_preview = $bbai_alt_char_count > 160;

                                    $bbai_display_filename = !empty($bbai_filename) && is_string($bbai_filename) ? $bbai_filename : __('Unknown file', 'beepbeep-ai-alt-text-generator');
                                    if (is_string($bbai_display_filename) && strlen($bbai_display_filename) > 88) {
                                        $bbai_display_filename = wp_html_excerpt($bbai_display_filename, 85, '...');
                                    }
                                    $bbai_filename_sort = strtolower(!empty($bbai_filename) ? $bbai_filename : $bbai_display_filename);

                                    $bbai_file_extension = '';
                                    $bbai_file_dimensions = '';
                                    $bbai_file_size = '';
                                    $bbai_file_size_bytes = 0;
                                    if ($bbai_attached_file && is_string($bbai_attached_file) && file_exists($bbai_attached_file)) {
                                        if (!empty($bbai_filename) && is_string($bbai_filename)) {
                                            $bbai_path_info = pathinfo($bbai_filename);
                                            $bbai_file_extension = !empty($bbai_path_info['extension']) && is_string($bbai_path_info['extension']) ? strtoupper($bbai_path_info['extension']) : '';
                                        }

                                        $bbai_attachment_meta = wp_get_attachment_metadata($bbai_attachment_id);
                                        if (is_array($bbai_attachment_meta)) {
                                            $bbai_width = isset($bbai_attachment_meta['width']) ? absint($bbai_attachment_meta['width']) : 0;
                                            $bbai_height = isset($bbai_attachment_meta['height']) ? absint($bbai_attachment_meta['height']) : 0;
                                            if ($bbai_width > 0 && $bbai_height > 0) {
                                                $bbai_file_dimensions = $bbai_width . 'x' . $bbai_height;
                                            }
                                        }

                                        $bbai_file_size_bytes = filesize($bbai_attached_file);
                                        if (false !== $bbai_file_size_bytes && is_numeric($bbai_file_size_bytes)) {
                                            $bbai_file_size_bytes = (int) $bbai_file_size_bytes;
                                            $bbai_file_size = size_format($bbai_file_size_bytes);
                                        }
                                    }

                                    $bbai_file_meta_parts = [];
                                    if ($bbai_file_extension) {
                                        $bbai_file_meta_parts[] = $bbai_file_extension;
                                    }
                                    if ($bbai_file_dimensions) {
                                        $bbai_file_meta_parts[] = $bbai_file_dimensions;
                                    }
                                    if ($bbai_file_size) {
                                        $bbai_file_meta_parts[] = $bbai_file_size;
                                    }
                                    $bbai_file_meta = implode(' • ', $bbai_file_meta_parts);

                                    $status = $bbai_rs['status'];
                                    $bbai_status_label = $bbai_rs['status_label'];
                                    $bbai_quality_class = $bbai_rs['quality_class'];
                                    $bbai_quality_label = $bbai_rs['quality_label'];
                                    $bbai_quality_score = (int) $bbai_rs['quality_score'];
                                    $bbai_score_tier = $bbai_rs['score_tier'];
                                    $bbai_score_tier_label = $bbai_rs['score_tier_label'];
                                    $bbai_analysis = $bbai_rs['analysis'];
                                    $bbai_is_user_approved = !empty($bbai_rs['user_approved']);

                                    $bbai_score_hint = '';
                                    if (!$bbai_has_alt) {
                                        $bbai_score_hint = __('Add or generate ALT to score this image.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('weak' === $bbai_score_tier) {
                                        $bbai_score_hint = __('Low score — regenerating often helps.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('review' === $bbai_score_tier) {
                                        $bbai_score_hint = __('Consider a quick manual edit for stronger context.', 'beepbeep-ai-alt-text-generator');
                                    }

                                    $bbai_preview_image_url = $bbai_thumb_url && isset($bbai_thumb_url[0]) ? $bbai_thumb_url[0] : '';
                                    $bbai_modal_image_url = wp_get_attachment_image_url($bbai_attachment_id, 'large');
                                    if (empty($bbai_modal_image_url)) {
                                        $bbai_modal_image_url = wp_get_attachment_image_url($bbai_attachment_id, 'full');
                                    }
                                    if (empty($bbai_modal_image_url)) {
                                        $bbai_modal_image_url = $bbai_preview_image_url;
                                    }

                                    $bbai_ai_source_raw = isset($bbai_image->ai_source) ? strtolower(trim((string) $bbai_image->ai_source)) : '';
                                    $bbai_ai_source = in_array($bbai_ai_source_raw, ['ai', 'openai'], true) ? 'ai' : '';
                                    $bbai_quality_tooltip = __('No ALT text detected', 'beepbeep-ai-alt-text-generator');
                                    if ($bbai_has_alt) {
                                        if ($bbai_quality_score >= 85) {
                                            $bbai_quality_tooltip = __('Strong quality — safe to keep or fine-tune.', 'beepbeep-ai-alt-text-generator');
                                        } elseif ($bbai_quality_score >= 70) {
                                            $bbai_quality_tooltip = __('Adequate but could use a sharper edit.', 'beepbeep-ai-alt-text-generator');
                                        } else {
                                            $bbai_quality_tooltip = __('Low confidence — consider regenerating or rewriting.', 'beepbeep-ai-alt-text-generator');
                                        }
                                    }

                                    if (!$bbai_has_alt) {
                                        $bbai_row_summary = __('No ALT yet — generate or add manually.', 'beepbeep-ai-alt-text-generator');
                                    } elseif ('weak' === $status) {
                                        $bbai_row_summary = !empty($bbai_analysis['issues'][0])
                                            ? (string) $bbai_analysis['issues'][0]
                                            : __('This description may be too generic or low quality.', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        $bbai_row_summary = $bbai_is_user_approved
                                            ? __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator')
                                            : __('Looks good, but you can still review or edit it.', 'beepbeep-ai-alt-text-generator');
                                    }

                                    $bbai_row_edit_label = __('Edit manually', 'beepbeep-ai-alt-text-generator');
                                    $bbai_show_review_action = 'weak' === $status && !$bbai_is_user_approved;
                                    $bbai_meta_parts_row = [];
                                    if (!empty($bbai_file_meta)) {
                                        $bbai_meta_parts_row[] = $bbai_file_meta;
                                    }
                                    if ($bbai_modified_date) {
                                        $bbai_meta_parts_row[] = sprintf(
                                            /* translators: %s: human-readable last updated date/time for the image. */
                                            __('Updated %s', 'beepbeep-ai-alt-text-generator'),
                                            $bbai_modified_date
                                        );
                                    }
                                    $bbai_card_meta_line = implode(' • ', $bbai_meta_parts_row);

                                    $bbai_row_state_rank = 'missing' === $status ? 0 : ('weak' === $status ? 1 : 2);

                                    $bbai_regen_label = $bbai_has_alt ? __('Regenerate', 'beepbeep-ai-alt-text-generator') : bbai_copy_cta_generate_missing_images();
                                    $bbai_regen_title = $bbai_limit_reached_state
                                        ? (
                                            'create_account' === $bbai_locked_cta_mode_ws
                                                ? __('Create a free account to unlock AI regenerations', 'beepbeep-ai-alt-text-generator')
                                                : __('Upgrade to unlock AI regenerations', 'beepbeep-ai-alt-text-generator')
                                        )
                                        : (!$bbai_has_alt
                                            ? bbai_copy_cta_generate_missing_images()
                                            : __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator'));
                                    $bbai_edit_title = __('Edit ALT text manually', 'beepbeep-ai-alt-text-generator');

                                    /* Primary = Generate/Regenerate; secondary = Edit (consistent hierarchy). */
                                    $bbai_q1_class = 'bbai-library-card__quick-action--primary';
                                    $bbai_q1_action = 'regenerate-single';
                                    $bbai_q1_label = $bbai_regen_label;
                                    $bbai_q1_title = $bbai_regen_title;
                                    $bbai_q2_class = 'bbai-library-card__quick-action--secondary';
                                    $bbai_q2_action = 'edit-alt-inline';
                                    $bbai_q2_label = $bbai_row_edit_label;
                                    $bbai_q2_title = $bbai_edit_title;

                                    $bbai_row_extra_copy    = $bbai_has_alt;
                                    $bbai_row_extra_improve = ('weak' === $status && $bbai_has_alt);
                                    $bbai_row_has_extras    = $bbai_row_extra_copy || $bbai_row_extra_improve || $bbai_show_review_action;
                                    ?>
                                    <article class="bbai-library-row bbai-library-review-card bbai-row"
                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                        data-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                        data-status="<?php echo esc_attr($status); ?>"
                                        data-review-state="<?php echo esc_attr($status); ?>"
                                        data-quality="<?php echo esc_attr($bbai_quality_class); ?>"
                                        data-ai-source="<?php echo esc_attr($bbai_ai_source); ?>"
                                        data-alt-missing="<?php echo $bbai_has_alt ? 'false' : 'true'; ?>"
                                        data-approved="<?php echo $bbai_is_user_approved ? 'true' : 'false'; ?>"
                                        data-alt-full="<?php echo esc_attr($bbai_clean_alt); ?>"
                                        data-image-title="<?php echo esc_attr($bbai_image->post_title); ?>"
                                        data-image-url="<?php echo esc_url($bbai_modal_image_url); ?>"
                                        data-file-name="<?php echo esc_attr(!empty($bbai_filename) ? $bbai_filename : $bbai_display_filename); ?>"
                                        data-file-name-sort="<?php echo esc_attr($bbai_filename_sort); ?>"
                                        data-file-meta="<?php echo esc_attr($bbai_file_meta); ?>"
                                        data-file-size-bytes="<?php echo esc_attr((string) $bbai_file_size_bytes); ?>"
                                        data-last-updated="<?php echo esc_attr($bbai_modified_date); ?>"
                                        data-created-ts="<?php echo esc_attr($bbai_created_timestamp ? (string) $bbai_created_timestamp : '0'); ?>"
                                        data-updated-ts="<?php echo esc_attr($bbai_updated_timestamp ? (string) $bbai_updated_timestamp : '0'); ?>"
                                        data-state-rank="<?php echo esc_attr((string) $bbai_row_state_rank); ?>"
                                        data-status-label="<?php echo esc_attr($bbai_status_label); ?>"
                                        data-review-summary="<?php echo esc_attr($bbai_row_summary); ?>"
                                        data-quality-label="<?php echo esc_attr($bbai_quality_label); ?>"
                                        data-quality-class="<?php echo esc_attr($bbai_quality_class); ?>"
                                        data-quality-score="<?php echo esc_attr((string) (int) $bbai_quality_score); ?>"
                                        data-score-tier="<?php echo esc_attr($bbai_score_tier); ?>"
                                        data-quality-tooltip="<?php echo esc_attr($bbai_quality_tooltip); ?>"
                                        role="listitem">
                                        <div class="bbai-library-card__select">
                                            <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($bbai_attachment_id); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php
                                            printf(
                                                /* translators: %s: image title used in the "select row" checkbox label. */
                                                esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'),
                                                esc_attr($bbai_image->post_title)
                                            );
                                            ?>" />
                                        </div>
                                        <div class="bbai-library-card__thumb bbai-row__media">
                                            <?php if ($bbai_thumb_url) : ?>
                                                <button type="button" class="bbai-library-thumbnail-button" data-action="preview-image" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php esc_attr_e('Preview image', 'beepbeep-ai-alt-text-generator'); ?>">
                                                    <img src="<?php echo esc_url($bbai_thumb_url[0]); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
                                                    <?php if (!empty($bbai_modal_image_url)) : ?>
                                                        <span class="bbai-library-hover-preview" aria-hidden="true">
                                                            <img src="<?php echo esc_url($bbai_modal_image_url); ?>" alt="" loading="lazy" decoding="async" />
                                                        </span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php else : ?>
                                                <div class="bbai-library-thumbnail-placeholder">
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                        <path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        <path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="bbai-library-cell--alt-text bbai-library-card__main bbai-row__main">
                                            <div class="bbai-library-card__col bbai-library-card__col--meta bbai-row__meta">
                                                <div class="bbai-library-card__meta-wrap">
                                                    <p class="bbai-library-card__filename" title="<?php echo esc_attr($bbai_display_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></p>
                                                    <p class="bbai-library-card__meta"><?php echo esc_html($bbai_card_meta_line); ?></p>
                                                </div>
                                                <?php if ('' !== $bbai_score_hint) : ?>
                                                    <p class="bbai-library-card__score-hint"><?php echo esc_html($bbai_score_hint); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bbai-library-card__col bbai-library-card__col--status bbai-row__status">
                                                <div class="bbai-library-status-tags" role="group" aria-label="<?php esc_attr_e('Status and quality score', 'beepbeep-ai-alt-text-generator'); ?>">
                                                    <span class="bbai-library-status-badge bbai-library-status-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html($bbai_status_label); ?></span>
                                                    <?php if ($bbai_has_alt) : ?>
                                                        <span class="bbai-library-score-badge bbai-library-score-badge--<?php echo esc_attr($bbai_score_tier); ?>" title="<?php echo esc_attr($bbai_quality_tooltip); ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: numeric score */ __('Score %s', 'beepbeep-ai-alt-text-generator'), (string) (int) $bbai_quality_score)); ?>">
                                                            <span class="bbai-library-score-badge__value" aria-hidden="true"><?php echo esc_html((string) (int) $bbai_quality_score); ?></span>
                                                            <span class="bbai-library-score-badge__label" aria-hidden="true"><?php esc_html_e('Score', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                        </span>
                                                    <?php else : ?>
                                                        <span class="bbai-library-score-badge bbai-library-score-badge--missing" title="<?php echo esc_attr($bbai_quality_tooltip); ?>">
                                                            <span class="bbai-library-score-badge__value" aria-hidden="true">—</span>
                                                            <span class="bbai-library-score-badge__label"><?php esc_html_e('No ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="bbai-library-card__review-body">
                                                <div class="bbai-library-card__col bbai-library-card__col--alt bbai-row__content">
                                                    <div class="bbai-library-alt-slot" data-bbai-alt-slot="1">
                                                        <div class="bbai-library-alt-preview-card bbai-library-alt-preview-card--v2 bbai-library-alt-preview-card--queue<?php echo $bbai_has_long_alt_preview ? ' bbai-library-alt-preview-card--collapsible' : ''; ?>" data-bbai-alt-preview-card data-action="edit-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                            <?php if ($bbai_has_alt) : ?>
                                                                <p id="<?php echo esc_attr($bbai_alt_preview_id); ?>" class="bbai-alt-text-preview" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></p>
                                                                <?php if ($bbai_has_long_alt_preview) : ?>
                                                                    <button type="button" class="bbai-library-alt-expand" data-action="toggle-alt-preview" aria-expanded="false" aria-controls="<?php echo esc_attr($bbai_alt_preview_id); ?>"><?php esc_html_e('Show more', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                            <?php else : ?>
                                                                <span class="bbai-alt-text-missing"><?php esc_html_e('No ALT yet — click to add or generate.', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="bbai-library-card__col bbai-library-card__col--actions bbai-row__actions">
                                                    <div class="bbai-library-card__tool-row bbai-library-card__action-cluster bbai-row-actions">
                                                        <div class="bbai-library-card__quick-actions" aria-label="<?php esc_attr_e('Row actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action bbai-row-actions__primary <?php echo esc_attr($bbai_q1_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q1_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q1_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q1_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q1_action) : ?>data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?><?php endif; ?>>
                                                                <?php echo esc_html($bbai_q1_label); ?>
                                                            </button>
                                                            <button type="button"
                                                                class="bbai-library-card__quick-action bbai-row-actions__secondary <?php echo esc_attr($bbai_q2_class); ?><?php echo ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) ? ' bbai-is-locked' : ''; ?>"
                                                                data-action="<?php echo esc_attr($bbai_q2_action); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_q2_title); ?>"
                                                                <?php if ('regenerate-single' === $bbai_q2_action) : ?>data-bbai-lock-preserve-label="1"<?php endif; ?>
                                                                <?php if ($bbai_limit_reached_state && 'regenerate-single' === $bbai_q2_action) : ?>data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-regenerate-quick" aria-disabled="true"<?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?><?php endif; ?>>
                                                                <?php echo esc_html($bbai_q2_label); ?>
                                                            </button>
                                                        </div>
                                                        <?php if ($bbai_row_has_extras) : ?>
                                                            <div class="bbai-library-card__extra-actions" role="group" aria-label="<?php esc_attr_e('Additional actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                                <?php if ($bbai_row_extra_copy) : ?>
                                                                    <button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="copy-alt-text" data-alt-text="<?php echo esc_attr($bbai_clean_alt); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Copy ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                                <?php if ($bbai_row_extra_improve) : ?>
                                                                    <button type="button"
                                                                        class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra<?php echo $bbai_limit_reached_state ? ' bbai-is-locked' : ''; ?>"
                                                                        data-action="phase17-improve-alt"
                                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                        title="<?php echo esc_attr($bbai_limit_reached_state ? $bbai_locked_quota_label : __('Improve ALT with AI (uses one credit)', 'beepbeep-ai-alt-text-generator')); ?>"
                                                                        <?php if ($bbai_limit_reached_state) : ?>data-bbai-action="<?php echo esc_attr($bbai_locked_quota_action); ?>" data-bbai-locked-cta="1" data-bbai-lock-reason="regenerate_single" data-bbai-locked-source="library-row-phase17-improve" aria-disabled="true"<?php echo $bbai_locked_auth_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static escaped fragment. ?><?php endif; ?>>
                                                                        <?php esc_html_e('Improve ALT', 'beepbeep-ai-alt-text-generator'); ?>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($bbai_show_review_action) : ?>
                                                                    <button type="button" class="bbai-library-card__quick-action bbai-library-card__quick-action--ghost bbai-row-actions__extra" data-action="approve-alt-inline" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"><?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?></button>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <?php if ($bbai_total_pages > 1) : ?>
                    <nav class="bbai-pagination bbai-table-meta" aria-label="<?php esc_attr_e('ALT Library pagination', 'beepbeep-ai-alt-text-generator'); ?>">
                        <span class="bbai-pagination-info">
                            <?php
                            if ('missing' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total missing images */
                                    esc_html__('Showing %1$s-%2$s of %3$s missing images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } elseif ('weak' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total images needing review */
                                    esc_html__('Showing %1$s-%2$s of %3$s images needing review', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } elseif ('optimized' === $bbai_default_review_filter) {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total optimized images */
                                    esc_html__('Showing %1$s-%2$s of %3$s optimized images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            } else {
                                printf(
                                    /* translators: 1: start number, 2: end number, 3: total count in result set */
                                    esc_html__('Showing %1$s-%2$s of %3$s images', 'beepbeep-ai-alt-text-generator'),
                                    esc_html(number_format_i18n($bbai_show_start)),
                                    esc_html(number_format_i18n($bbai_show_end)),
                                    esc_html(number_format_i18n($bbai_results_total))
                                );
                            }
                            ?>
                        </span>

                        <div class="bbai-pagination-controls">
                            <?php if ($bbai_current_page > 1) : ?>
                                <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page - 1)); ?>" class="bbai-pagination-btn"><?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?></a>
                            <?php endif; ?>

                            <div class="bbai-pagination-pages">
                                <?php
                                $bbai_page_range = 2;
                                $bbai_start_page = max(1, $bbai_current_page - $bbai_page_range);
                                $bbai_end_page = min($bbai_total_pages, $bbai_current_page + $bbai_page_range);

                                if ($bbai_start_page > 1) {
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="bbai-pagination-btn">1</a>';
                                    if ($bbai_start_page > 2) {
                                        echo '<span class="bbai-pagination-ellipsis">...</span>';
                                    }
                                }

                                for ($bbai_i = $bbai_start_page; $bbai_i <= $bbai_end_page; $bbai_i++) {
                                    $bbai_class = ($bbai_i === $bbai_current_page) ? 'bbai-pagination-btn bbai-pagination-btn--current' : 'bbai-pagination-btn';
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', $bbai_i)) . '" class="' . esc_attr($bbai_class) . '">' . esc_html($bbai_i) . '</a>';
                                }

                                if ($bbai_end_page < $bbai_total_pages) {
                                    if ($bbai_end_page < $bbai_total_pages - 1) {
                                        echo '<span class="bbai-pagination-ellipsis">...</span>';
                                    }
                                    echo '<a href="' . esc_url(add_query_arg('alt_page', $bbai_total_pages)) . '" class="bbai-pagination-btn">' . esc_html($bbai_total_pages) . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($bbai_current_page < $bbai_total_pages) : ?>
                                <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page + 1)); ?>" class="bbai-pagination-btn"><?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?></a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <section class="bbai-library-empty-state bbai-table-empty">
                    <div class="bbai-library-empty-state__icon bbai-table-empty__icon" aria-hidden="true">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z" stroke="currentColor" stroke-width="1.5"></path>
                            <path d="M8 15l2.5-2.5L13 15l3-3 2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <circle cx="9" cy="9" r="1.2" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <h2 class="bbai-library-empty-state__title bbai-table-empty__title"><?php esc_html_e('No images found', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-library-empty-state__copy bbai-table-empty__copy"><?php esc_html_e('Scan your media library to get started, then review generated ALT text here as your library grows.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <div class="bbai-library-empty-state__actions bbai-table-empty__actions">
                        <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" <?php echo $bbai_scan_action['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes assembled from escaped values. ?>><?php echo esc_html($bbai_scan_action['label']); ?></button>
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Open Media Library', 'beepbeep-ai-alt-text-generator'); ?></a>
                    </div>
                </section>
            <?php endif; ?>
        </main>
        </div><!-- /.bbai-library-workspace-card__table-surface -->
    </div><!-- /.bbai-library-workspace-card -->
</div>
