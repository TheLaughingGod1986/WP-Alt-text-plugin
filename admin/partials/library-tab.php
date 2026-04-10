<?php
/**
 * ALT Library tab content partial.
 * Modernized to match BeepBeep AI SaaS style with unified.css components
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/page-hero.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-usage-helper.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-dashboard-state.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-trial-quota.php';

use BeepBeepAI\AltTextGenerator\BBAI_Cache;
use BeepBeepAI\AltTextGenerator\Trial_Quota;
use BeepBeepAI\AltTextGenerator\Services\Dashboard_State;
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;

$bbai_library_template_buffer_level = ob_get_level();
ob_start();

// Pagination setup
$bbai_per_page = 10;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_alt_page_input = isset($_GET['alt_page']) ? absint(wp_unslash($_GET['alt_page'])) : 0;
$bbai_current_page = max(1, $bbai_alt_page_input);

global $wpdb;

$bbai_image_mime_like = $wpdb->esc_like('image/') . '%';

$bbai_library_workspace_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-workspace.php';
$bbai_use_library_workspace       = is_readable($bbai_library_workspace_partial );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_requested_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_filter_param = isset($_GET['filter']) ? sanitize_key(wp_unslash($_GET['filter'])) : '';
if ($bbai_requested_status === '' && $bbai_filter_param !== '') {
    $bbai_filter_norm = strtolower(str_replace('-', '_', $bbai_filter_param));
    if ('needs_review' === $bbai_filter_norm) {
        $bbai_requested_status = 'needs_review';
    }
}
$bbai_workspace_status_map = [
    'missing'      => 'missing',
    'optimized'    => 'optimized',
    'weak'         => 'weak',
    'needs_review' => 'weak',
    'needs-review' => 'weak',
    'attention'    => 'weak',
];
$bbai_default_review_filter = 'all';
$bbai_review_filter_from_url  = false;
if ($bbai_requested_status !== '' && isset($bbai_workspace_status_map[ $bbai_requested_status ])) {
    $bbai_default_review_filter = $bbai_workspace_status_map[ $bbai_requested_status ];
    $bbai_review_filter_from_url = true;
}
$bbai_active_library_filter = $bbai_default_review_filter;

// Get total count of all images (cached).
$bbai_total_images = BBAI_Cache::get( 'library', 'total' );
if ( false === $bbai_total_images ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bbai_total_images = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
        'attachment', 'inherit', $bbai_image_mime_like
    ));
    BBAI_Cache::set( 'library', 'total', $bbai_total_images, BBAI_Cache::DEFAULT_TTL );
}
$bbai_total_images = (int) $bbai_total_images;

// Get images with alt text count (cached).
$bbai_with_alt_count = BBAI_Cache::get( 'library', 'with_alt' );
if ( false === $bbai_with_alt_count ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bbai_with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s AND pm.meta_key = %s AND TRIM(pm.meta_value) <> %s',
        'attachment', $bbai_image_mime_like, 'inherit', '_wp_attachment_image_alt', ''
    ));
    BBAI_Cache::set( 'library', 'with_alt', $bbai_with_alt_count, BBAI_Cache::DEFAULT_TTL );
}
$bbai_with_alt_count = (int) $bbai_with_alt_count;

$bbai_missing_count = $bbai_total_images - $bbai_with_alt_count;

if ($bbai_use_library_workspace) {
    // Full library dataset (no LIMIT): normalize each row, then filter, then paginate.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $bbai_library_full_images = $wpdb->get_results($wpdb->prepare(
        'SELECT p.ID, p.post_title, p.post_date, p.post_modified, MAX(COALESCE(pm.meta_value, %s)) as alt_text, MAX(CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> %s THEN 1 ELSE 0 END) as has_alt, MAX(src.meta_value) as ai_source FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id AND pm.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON p.ID = src.post_id AND src.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s GROUP BY p.ID, p.post_title, p.post_date, p.post_modified ORDER BY p.post_date DESC',
        '',
        '',
        '_wp_attachment_image_alt',
        '_bbai_source',
        'attachment',
        $bbai_image_mime_like,
        'inherit'
    ));

    $bbai_library_normalized_rows = [];
    $bbai_table_filter_counts     = [
        'all'       => 0,
        'missing'   => 0,
        'weak'      => 0,
        'optimized' => 0,
    ];

    foreach ($bbai_library_full_images as $bbai_full_row_image) {
        $bbai_row_state  = $this->get_library_workspace_row_state($bbai_full_row_image);
        $bbai_row_status = isset($bbai_row_state['status']) ? (string) $bbai_row_state['status'] : 'weak';
        if ('missing' === $bbai_row_status) {
            ++$bbai_table_filter_counts['missing'];
        } elseif ('weak' === $bbai_row_status) {
            ++$bbai_table_filter_counts['weak'];
        } else {
            ++$bbai_table_filter_counts['optimized'];
        }
        $bbai_library_normalized_rows[] = [
            'image' => $bbai_full_row_image,
            'state' => $bbai_row_state,
        ];
    }
    $bbai_table_filter_counts['all'] = count($bbai_library_normalized_rows);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        $bbai_count_sum_check = $bbai_table_filter_counts['missing'] + $bbai_table_filter_counts['weak'] + $bbai_table_filter_counts['optimized'];
        if ($bbai_count_sum_check !== $bbai_table_filter_counts['all']) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug invariant only.
            error_log('BBAI library: filter count invariant failed (missing + weak + optimized !== all).');
        }
    }

    $bbai_filtered_normalized = array_values(
        array_filter(
            $bbai_library_normalized_rows,
            static function (array $row) use ($bbai_active_library_filter) : bool {
                if ('all' === $bbai_active_library_filter) {
                    return true;
                }
                $st = isset($row['state']['status']) ? (string) $row['state']['status'] : '';

                return $st === $bbai_active_library_filter;
            }
        )
    );

    $bbai_library_filtered_total = count($bbai_filtered_normalized);
    $bbai_library_total_pages    = $bbai_per_page > 0 ? (int) ceil($bbai_library_filtered_total / $bbai_per_page) : 1;
    if ($bbai_library_total_pages < 1) {
        $bbai_library_total_pages = 1;
    }
    if ($bbai_current_page > $bbai_library_total_pages) {
        $bbai_current_page = $bbai_library_total_pages;
    }
    $bbai_offset               = ($bbai_current_page - 1) * $bbai_per_page;
    $bbai_page_normalized_slice = array_slice($bbai_filtered_normalized, $bbai_offset, $bbai_per_page);

    $bbai_all_images         = [];
    $bbai_library_row_states = [];
    foreach ($bbai_page_normalized_slice as $bbai_slice_idx => $bbai_norm_row) {
        $bbai_all_images[]                         = $bbai_norm_row['image'];
        $bbai_library_row_states[ $bbai_slice_idx ] = $bbai_norm_row['state'];
    }

    $bbai_total_pages = $bbai_library_total_pages;
    $bbai_total_count = $bbai_library_filtered_total;
} else {
    $bbai_offset              = ($bbai_current_page - 1) * $bbai_per_page;
    $bbai_images_cache_suffix = 'images_' . $bbai_current_page . '_' . $bbai_per_page;
    $bbai_all_images          = BBAI_Cache::get('library', $bbai_images_cache_suffix);
    if (false === $bbai_all_images) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $bbai_all_images = $wpdb->get_results($wpdb->prepare(
            'SELECT p.ID, p.post_title, p.post_date, p.post_modified, MAX(COALESCE(pm.meta_value, %s)) as alt_text, MAX(CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> %s THEN 1 ELSE 0 END) as has_alt, MAX(src.meta_value) as ai_source FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id AND pm.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON p.ID = src.post_id AND src.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s GROUP BY p.ID, p.post_title, p.post_date, p.post_modified ORDER BY p.post_date DESC LIMIT %d OFFSET %d',
            '',
            '',
            '_wp_attachment_image_alt',
            '_bbai_source',
            'attachment',
            $bbai_image_mime_like,
            'inherit',
            $bbai_per_page,
            $bbai_offset
        ));
        BBAI_Cache::set('library', $bbai_images_cache_suffix, $bbai_all_images, BBAI_Cache::DEFAULT_TTL);
    }
    $bbai_table_filter_counts    = null;
    $bbai_library_row_states     = null;
    $bbai_library_filtered_total = null;
    $bbai_total_count            = $bbai_total_images;
    $bbai_total_pages            = $bbai_per_page > 0 ? (int) ceil($bbai_total_count / $bbai_per_page) : 0;
}

// Get plan info
$bbai_usage_stats = (isset($bbai_usage_stats) && is_array($bbai_usage_stats))
    ? $bbai_usage_stats
    : Usage_Helper::get_usage($this->api_client, (bool) ($bbai_has_connected_account ?? $bbai_has_registered_user ?? false));
$bbai_has_license = $this->api_client->has_active_license();
$bbai_license_data = $this->api_client->get_license_data();

$bbai_has_connected_account = (bool) ($bbai_has_connected_account ?? $bbai_has_registered_user ?? false);
$bbai_usage_auth_state = isset($bbai_usage_stats['auth_state']) ? strtolower((string) $bbai_usage_stats['auth_state']) : '';
$bbai_usage_quota_type = isset($bbai_usage_stats['quota_type']) ? strtolower((string) $bbai_usage_stats['quota_type']) : '';

// Guest shell when there is no SaaS account (same idea as dashboard-body.php): usage can lag as "authenticated" + paid plan.
if (!$bbai_has_connected_account && is_array($bbai_usage_stats)) {
    $bbai_usage_stats['auth_state'] = 'anonymous';
    $bbai_usage_stats['quota_type'] = 'trial';
    $bbai_usage_auth_state = 'anonymous';
    $bbai_usage_quota_type = 'trial';
}

$bbai_is_guest_trial = 'anonymous' === $bbai_usage_auth_state || 'trial' === $bbai_usage_quota_type || !empty($bbai_usage_stats['is_trial']);
$bbai_plan_slug = $bbai_is_guest_trial ? 'trial' : 'free';

if ($bbai_has_connected_account && isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan'])) {
    $bbai_plan_slug = strtolower((string) $bbai_usage_stats['plan']);
}

if ($bbai_has_connected_account && $bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization']) && is_array($bbai_license_data['organization'])) {
    $bbai_license_plan = !empty($bbai_license_data['organization']['plan']) ? strtolower((string) $bbai_license_data['organization']['plan']) : 'free';
    if ('free' !== $bbai_license_plan) {
        $bbai_plan_slug = $bbai_license_plan;
    }
}

// Map 'pro' to 'growth' for consistency
if ($bbai_plan_slug === 'pro') {
    $bbai_plan_slug = 'growth';
}

$bbai_is_free = in_array($bbai_plan_slug, ['free', 'trial'], true);
$bbai_is_growth = ($bbai_plan_slug === 'growth');
$bbai_is_agency = ($bbai_plan_slug === 'agency');
$bbai_is_pro = ($bbai_is_growth || $bbai_is_agency);

// Calculate stats percentages
$bbai_optimized_percent = $bbai_total_images > 0 ? round(($bbai_with_alt_count / $bbai_total_images) * 100) : 0;

// Get fresh ALT coverage for workflow/progress/filter accuracy on the library page.
$bbai_coverage = isset($this) && method_exists($this, 'get_alt_text_coverage_scan') ? $this->get_alt_text_coverage_scan(true) : [];
if (empty($bbai_coverage) || !isset($bbai_coverage['total_images'])) {
    $bbai_coverage = [
        'total_images' => $bbai_total_images,
        'images_missing_alt' => $bbai_missing_count,
        'optimized_count' => $bbai_with_alt_count,
        'needs_review_count' => 0,
        'ai_source_count' => 0,
    ];
}

// Workspace: align coverage payload with the same normalized row dataset used for filter chips and filtering.
if ($bbai_use_library_workspace && is_array($bbai_table_filter_counts)) {
    $bbai_coverage['total_images']       = $bbai_table_filter_counts['all'];
    $bbai_coverage['images_missing_alt'] = $bbai_table_filter_counts['missing'];
    $bbai_coverage['needs_review_count'] = $bbai_table_filter_counts['weak'];
    $bbai_coverage['optimized_count']    = $bbai_table_filter_counts['optimized'];
}
?>

<div class="bbai-library-container bbai-container" data-bbai-empty-filter="<?php echo esc_attr__('No images match this filter.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-empty-filter-hint="<?php echo esc_attr__('Try another filter or show all images.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-library-is-pro="<?php echo $bbai_is_pro ? '1' : '0'; ?>" data-bbai-settings-url="<?php echo esc_url(admin_url('admin.php?page=bbai-settings')); ?>">
    <?php
    // Coverage stats (unconditional - always compute)
    $bbai_cov_optimized = isset($bbai_coverage['optimized_count']) ? (int) $bbai_coverage['optimized_count'] : $bbai_with_alt_count;
    $bbai_cov_needs_review = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
    $bbai_cov_missing = isset($bbai_coverage['images_missing_alt']) ? (int) $bbai_coverage['images_missing_alt'] : $bbai_missing_count;
    $bbai_cov_auto = isset($bbai_coverage['ai_source_count']) ? (int) $bbai_coverage['ai_source_count'] : 0;
    $bbai_cov_total = isset($bbai_coverage['total_images']) ? (int) $bbai_coverage['total_images'] : $bbai_total_images;
    $bbai_cov_opt_pct = $bbai_cov_total > 0 ? round(($bbai_cov_optimized / $bbai_cov_total) * 100) : 0;
    $bbai_cov_review_pct = $bbai_cov_total > 0 ? round(($bbai_cov_needs_review / $bbai_cov_total) * 100) : 0;
    $bbai_cov_miss_pct = $bbai_cov_total > 0 ? round(($bbai_cov_missing / $bbai_cov_total) * 100) : 0;
    // $bbai_default_review_filter / $bbai_review_filter_from_url are set at the top of this partial.

    $bbai_trial_status = $bbai_is_guest_trial ? Trial_Quota::get_status() : [];
    $bbai_plan = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
    $bbai_has_quota = false;
    if (isset($bbai_usage_stats) && is_array($bbai_usage_stats)) {
        if (isset($bbai_usage_stats['remaining'])) {
            $bbai_has_quota = intval($bbai_usage_stats['remaining']) > 0;
        } elseif (isset($bbai_usage_stats['credits_remaining'])) {
            $bbai_has_quota = intval($bbai_usage_stats['credits_remaining']) > 0;
        }
    }
    $bbai_is_premium = in_array($bbai_plan, ['pro', 'growth', 'agency'], true);
    $bbai_out_of_credits = !$bbai_has_quota && !$bbai_is_premium;
    $bbai_product_state_model = Dashboard_State::resolve(
        [
            'is_guest_trial' => $bbai_is_guest_trial,
            'is_premium'     => $bbai_is_premium,
            'plan_slug'      => strtolower((string) $bbai_plan),
            'usage_stats'    => $bbai_usage_stats,
            'trial_status'   => $bbai_trial_status,
            'missing_count'  => $bbai_cov_missing,
            'weak_count'     => $bbai_cov_needs_review,
        ]
    );
    $bbai_limit_reached_state = !empty($bbai_product_state_model['flags']['lock_generation_actions']);
    $bbai_stats = isset($bbai_stats) ? $bbai_stats : ['total' => $bbai_total_images, 'with_alt' => $bbai_with_alt_count, 'missing' => $bbai_missing_count];

    // No stats slot on library page — Quick Actions go straight to action cards.
    $bbai_quick_actions_stats_slot = '';
    $bbai_quick_actions_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/quick-actions.php';
    if (file_exists($bbai_quick_actions_path)) {
        include $bbai_quick_actions_path;
    }
    $bbai_quick_actions_stats_slot = '';

    if ($bbai_use_library_workspace) {
        while (ob_get_level() > $bbai_library_template_buffer_level) {
            ob_end_clean();
        }

        include $bbai_library_workspace_partial;
        return;
    }
    ?>
    <div id="bbai-alt-coverage-card"
         class="bbai-library-summary-card"
         data-bbai-coverage-card
         data-bbai-free-plan-limit="<?php echo esc_attr((int) ($bbai_coverage['free_plan_limit'] ?? 50)); ?>">
        <div class="bbai-library-summary-card__head">
            <div>
                <p class="bbai-library-summary-card__eyebrow"><?php echo esc_html(bbai_copy_section_library_progress()); ?></p>
                <h2 class="bbai-library-summary-card__title"><span data-bbai-library-progress-label><?php echo esc_html(sprintf(
                    /* translators: %s: optimization percentage. */
                    __('%s%% optimized', 'beepbeep-ai-alt-text-generator'),
                    number_format_i18n($bbai_cov_opt_pct)
                )); ?></span></h2>
                <p class="bbai-library-summary-card__copy"><?php echo esc_html(bbai_copy_helper_queue_missing_then_review()); ?></p>
            </div>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="rescan-media-library">
                <?php echo esc_html(bbai_copy_cta_scan_new_uploads()); ?>
            </button>
        </div>
        <div class="bbai-library-summary-card__stats">
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--optimized">
                <span class="bbai-library-summary-card__stat-label"><?php echo esc_html(bbai_copy_status_optimized()); ?></span>
                <strong class="bbai-library-summary-card__stat-value" data-bbai-library-optimized><?php echo esc_html(number_format_i18n($bbai_cov_optimized)); ?></strong>
            </div>
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--weak">
                <span class="bbai-library-summary-card__stat-label"><?php echo esc_html(bbai_copy_status_needs_review()); ?></span>
                <strong class="bbai-library-summary-card__stat-value" data-bbai-library-weak><?php echo esc_html(number_format_i18n($bbai_cov_needs_review)); ?></strong>
            </div>
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--missing">
                <span class="bbai-library-summary-card__stat-label"><?php echo esc_html(bbai_copy_status_missing()); ?></span>
                <strong class="bbai-library-summary-card__stat-value" data-bbai-library-missing><?php echo esc_html(number_format_i18n($bbai_cov_missing)); ?></strong>
            </div>
        </div>
        <div class="bbai-library-summary-card__bar">
            <div class="bbai-library-summary-card__progress" role="progressbar" data-bbai-library-progressbar aria-valuenow="<?php echo esc_attr($bbai_cov_opt_pct); ?>" aria-valuemin="0" aria-valuemax="100">
                <span class="bbai-library-summary-card__segment bbai-library-summary-card__segment--optimized" data-bbai-library-progress-optimized style="flex:0 0 <?php echo esc_attr($bbai_cov_opt_pct); ?>%;"></span>
                <span class="bbai-library-summary-card__segment bbai-library-summary-card__segment--weak" data-bbai-library-progress-weak style="flex:0 0 <?php echo esc_attr($bbai_cov_review_pct); ?>%;"></span>
                <span class="bbai-library-summary-card__segment bbai-library-summary-card__segment--missing" data-bbai-library-progress-missing style="flex:0 0 <?php echo esc_attr($bbai_cov_miss_pct); ?>%;"></span>
            </div>
        </div>
        <div class="bbai-library-summary-card__foot">
            <span><?php esc_html_e('Pagination and lazy-loaded thumbnails keep larger libraries responsive.', 'beepbeep-ai-alt-text-generator'); ?></span>
            <strong data-bbai-coverage-score-inline><?php echo esc_html(sprintf(
                /* translators: %s: optimization percentage. */
                __('%s%% optimized', 'beepbeep-ai-alt-text-generator'),
                number_format_i18n($bbai_cov_opt_pct)
            )); ?></strong>
        </div>
    </div>

    <?php
    $bbai_usage_used = max(0, (int) ($bbai_usage_stats['used'] ?? 0));
    $bbai_usage_limit = max(1, (int) ($bbai_usage_stats['limit'] ?? 50));
    $bbai_usage_remaining = max(0, (int) ($bbai_usage_stats['remaining'] ?? 0));
    $bbai_usage_pct = min(100, max(0, (int) round($bbai_usage_limit ? ($bbai_usage_used / $bbai_usage_limit) * 100 : 0)));
    $bbai_settings_automation_url = admin_url('admin.php?page=bbai-settings#bbai-enable-on-upload');
    $bbai_review_usage_url = admin_url('admin.php?page=bbai-credit-usage');
    $bbai_is_low_credits = $bbai_usage_remaining > 0 && $bbai_usage_remaining <= BBAI_BANNER_LOW_CREDITS_THRESHOLD;
    $bbai_is_out_of_credits = (0 === $bbai_usage_remaining);

    $bbai_command_hero = bbai_page_hero_library_command_hero(
        [
            'cov_total'                => $bbai_cov_total,
            'cov_missing'              => $bbai_cov_missing,
            'cov_needs_review'         => $bbai_cov_needs_review,
            'is_pro'                   => $bbai_is_pro,
            'settings_automation_url'  => $bbai_settings_automation_url,
            'is_low_credits'           => $bbai_is_low_credits,
            'is_out_of_credits'        => $bbai_is_out_of_credits,
            'credits_remaining'        => $bbai_usage_remaining,
            'credits_used'             => $bbai_usage_used,
            'credits_limit'            => $bbai_usage_limit,
            'usage_percent'            => $bbai_usage_pct,
            'library_url'              => add_query_arg(['page' => 'bbai-library'], admin_url('admin.php')),
            'missing_library_url'      => add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php')),
            'needs_review_library_url' => bbai_alt_library_needs_review_url(),
            'usage_url'                => $bbai_review_usage_url,
            'plan_label'               => isset($bbai_usage_stats['plan_label']) ? (string) $bbai_usage_stats['plan_label'] : '',
            'remaining_line'           => '',
            'reset_timing'             => isset($bbai_usage_stats['days_until_reset'])
                ? sprintf(
                    /* translators: %d: days until credit reset */
                    _n('%d day until reset', '%d days until reset', (int) $bbai_usage_stats['days_until_reset'], 'beepbeep-ai-alt-text-generator'),
                    (int) $bbai_usage_stats['days_until_reset']
                )
                : '',
        ]
    );
    $bbai_command_hero['wrapper_extra_class'] = trim(
        ((string) ($bbai_command_hero['wrapper_extra_class'] ?? '')) . ' bbai-library-guidance-host'
    );
    $bbai_command_hero['section_extra_class'] = trim(
        ((string) ($bbai_command_hero['section_extra_class'] ?? '')) . ' bbai-library-guidance-banner'
    );
    if (!isset($bbai_command_hero['section_data_attrs']) || !is_array($bbai_command_hero['section_data_attrs'])) {
        $bbai_command_hero['section_data_attrs'] = [];
    }
    $bbai_command_hero['section_data_attrs']['data-bbai-library-guidance'] = '1';
    $bbai_command_hero['section_data_attrs']['data-bbai-library-guidance-limit-reached'] = $bbai_limit_reached_state ? '1' : '0';
    $bbai_banner_slot = bbai_get_active_banner_slot_from_state((string) ($bbai_command_hero['banner_logical_state'] ?? ''));
    $bbai_command_hero['section_data_attrs']['data-bbai-active-banner-slot'] = (string) ($bbai_banner_slot ?? '');

    bbai_ui_render('bbai-banner', ['command_hero' => $bbai_command_hero]);
    ?>

    <?php
    $bbai_library_filter_items = [
        [
            'key' => 'all',
            'label' => bbai_copy_filter_all(),
            'count' => (int) $bbai_cov_total,
            'active' => 'all' === $bbai_default_review_filter,
            'filter_label_attr' => bbai_copy_filter_all(),
        ],
        [
            'key' => 'missing',
            'label' => bbai_copy_status_missing(),
            'count' => (int) $bbai_cov_missing,
            'active' => 'missing' === $bbai_default_review_filter,
            'filter_label_attr' => bbai_copy_status_missing(),
            'attention' => $bbai_cov_missing > 0,
        ],
        [
            'key' => 'weak',
            'label' => bbai_copy_status_needs_review(),
            'count' => (int) $bbai_cov_needs_review,
            'active' => 'weak' === $bbai_default_review_filter,
            'filter_label_attr' => bbai_copy_status_needs_review(),
            'attention' => $bbai_cov_needs_review > 0,
        ],
        [
            'key' => 'optimized',
            'label' => bbai_copy_status_optimized(),
            'count' => (int) $bbai_cov_optimized,
            'active' => 'optimized' === $bbai_default_review_filter,
            'filter_label_attr' => bbai_copy_status_optimized(),
        ],
    ];

    bbai_ui_render(
        'filter-group',
        [
            'id' => 'bbai-review-filter-tabs',
            'aria_label' => __('Filter images by status', 'beepbeep-ai-alt-text-generator'),
            'interaction_mode' => 'filter',
            'default_filter' => $bbai_default_review_filter,
            'items' => $bbai_library_filter_items,
        ]
    );
    ?>

    <?php if ($bbai_total_images > 0) : ?>
        <?php
        // Check if user can generate (same logic as dashboard).
        $bbai_plan = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
        $bbai_has_quota = false;
        if (isset($bbai_usage_stats) && is_array($bbai_usage_stats)) {
            if (isset($bbai_usage_stats['remaining'])) {
                $bbai_has_quota = intval($bbai_usage_stats['remaining']) > 0;
            } elseif (isset($bbai_usage_stats['credits_remaining'])) {
                $bbai_has_quota = intval($bbai_usage_stats['credits_remaining']) > 0;
            }
        }
        $bbai_is_premium = in_array($bbai_plan, ['pro', 'growth', 'agency'], true);
        $bbai_can_generate = $bbai_has_quota || $bbai_is_premium;
        $bbai_remaining_credits = 0;
        $bbai_used_credits      = 0;
        $bbai_limit_credits     = 0;
        if (isset($bbai_usage_stats) && is_array($bbai_usage_stats)) {
            $bbai_used_credits  = max(0, (int) ($bbai_usage_stats['credits_used'] ?? $bbai_usage_stats['creditsUsed'] ?? $bbai_usage_stats['used'] ?? 0));
            $bbai_limit_credits = max(0, (int) ($bbai_usage_stats['credits_total'] ?? $bbai_usage_stats['creditsTotal'] ?? $bbai_usage_stats['limit'] ?? 0));
            $bbai_credits_remaining_raw = $bbai_usage_stats['credits_remaining'] ?? $bbai_usage_stats['creditsRemaining'] ?? $bbai_usage_stats['remaining'] ?? null;
            if (null !== $bbai_credits_remaining_raw && '' !== $bbai_credits_remaining_raw) {
                $bbai_remaining_credits = max(0, (int) $bbai_credits_remaining_raw);
            } elseif ($bbai_limit_credits > 0) {
                $bbai_remaining_credits = max(0, $bbai_limit_credits - $bbai_used_credits);
            }
        }
        $bbai_usage_limit_hit = ($bbai_limit_credits > 0 && $bbai_used_credits >= $bbai_limit_credits);
        $bbai_out_of_credits = ($bbai_remaining_credits <= 0 || $bbai_usage_limit_hit);
        $bbai_limit_reached_state = !$bbai_is_premium && $bbai_out_of_credits;
        $bbai_generate_missing_disabled = $bbai_limit_reached_state || !$bbai_can_generate || $bbai_missing_count <= 0;
        $bbai_reoptimize_disabled = $bbai_limit_reached_state || !$bbai_can_generate;
        $bbai_show_review_existing_action = $bbai_limit_reached_state && $bbai_missing_count <= 0;
        ?>
    <?php endif; ?>

    <!-- Toolbar: search + filters -->
    <div class="bbai-toolbar bbai-library-toolbar" id="bbai-library-toolbar" aria-live="polite">
        <div class="bbai-toolbar__right">
            <div class="bbai-toolbar__search">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                    <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input type="text" id="bbai-library-search" class="bbai-toolbar__search-input bbai-input" placeholder="<?php esc_attr_e('Search', 'beepbeep-ai-alt-text-generator'); ?>" />
            </div>
            <div class="bbai-toolbar__filters">
                <button type="button" class="bbai-toolbar__icon-btn" id="bbai-status-filter-btn" title="<?php esc_attr_e('Filter', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 4h12M4 8h8M6 12h4"/></svg>
                </button>
                <button type="button" class="bbai-toolbar__icon-btn" id="bbai-quality-filter-btn" title="<?php esc_attr_e('Quality filter', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3h4v4H3zM9 3h4v4H9zM3 9h4v4H3z"/><path d="M9 9h4v4H9z"/></svg>
                </button>
                <button type="button" class="bbai-toolbar__icon-btn" id="bbai-date-filter-btn" title="<?php esc_attr_e('Date filter', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="11" rx="2"/><path d="M2 7h12M5 1v4M11 1v4"/></svg>
                </button>
                <button type="button" class="bbai-toolbar__icon-btn" id="bbai-critical-issues" title="<?php esc_attr_e('Settings', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="2.5"/><path d="M8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.1 3.1l1.4 1.4M11.5 11.5l1.4 1.4M3.1 12.9l1.4-1.4M11.5 4.5l1.4-1.4"/></svg>
                </button>
            </div>
        </div>
        <!-- Hidden selects for functionality -->
        <select id="bbai-status-filter" class="bbai-library-filter-select" style="display: none;">
            <option value="all"><?php esc_html_e('Status: All', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="optimized"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="missing"><?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="needs-work"><?php esc_html_e('Needs work', 'beepbeep-ai-alt-text-generator'); ?></option>
        </select>
        <select id="bbai-date-filter" class="bbai-library-filter-select" style="display: none;">
            <option value="all"><?php esc_html_e('Date Range', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="today"><?php esc_html_e('Today', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="week"><?php esc_html_e('This Week', 'beepbeep-ai-alt-text-generator'); ?></option>
            <option value="month"><?php esc_html_e('This Month', 'beepbeep-ai-alt-text-generator'); ?></option>
        </select>
    </div>

    <!-- Table + Upgrade sidebar -->
    <?php if (!empty($bbai_all_images)) : ?>
        <div class="bbai-library-selection-bar" id="bbai-library-selection-bar" aria-live="polite" aria-hidden="false">
            <div class="bbai-library-selection-bar__summary">
                <label class="bbai-library-selection-bar__lead">
                    <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
                    <?php esc_html_e('Select images', 'beepbeep-ai-alt-text-generator'); ?>
                </label>
                <span class="bbai-library-selection-bar__count" id="bbai-selected-count" data-bbai-selected-count>0 <?php esc_html_e('images selected', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
            <div class="bbai-library-selection-bar__actions">
                <button type="button"
                        class="bbai-toolbar__btn bbai-toolbar__btn--primary <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                        id="bbai-batch-generate"
                        data-action="generate-selected"
                        data-bbai-lock-preserve-label="1"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-bbai-action="open-upgrade"
                            data-bbai-locked-cta="1"
                            data-bbai-lock-reason="generate_missing"
                            data-bbai-locked-source="library-batch-generate"
                            aria-disabled="true"
                            title="<?php esc_attr_e('Upgrade to generate ALT text for selected images', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php endif; ?>>
                    <?php echo esc_html(bbai_copy_cta_generate_alt()); ?>
                </button>
                <button type="button"
                        class="bbai-toolbar__btn <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                        id="bbai-batch-regenerate"
                        data-action="regenerate-selected"
                        data-bbai-lock-preserve-label="1"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-bbai-action="open-upgrade"
                            data-bbai-locked-cta="1"
                            data-bbai-lock-reason="reoptimize_all"
                            data-bbai-locked-source="library-batch-regenerate"
                            aria-disabled="true"
                            title="<?php esc_attr_e('Upgrade to improve ALT text for selected images', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php endif; ?>>
                    <?php esc_html_e('Regenerate ALT', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button"
                        class="bbai-toolbar__btn"
                        id="bbai-batch-reviewed"
                        data-action="mark-reviewed"
                        aria-label="<?php esc_attr_e('Mark selected images as reviewed', 'beepbeep-ai-alt-text-generator'); ?>">
                    <?php esc_html_e('Mark as reviewed', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-toolbar__btn" id="bbai-batch-export" data-action="export-alt-text" aria-label="<?php esc_attr_e('Export ALT text for selected', 'beepbeep-ai-alt-text-generator'); ?>">
                    <?php esc_html_e('Export', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-toolbar__btn" id="bbai-batch-clear" data-action="clear-selection" aria-label="<?php esc_attr_e('Clear image selection', 'beepbeep-ai-alt-text-generator'); ?>">
                    <?php esc_html_e('Clear selection', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>

        <div id="bbai-alt-table" class="bbai-library-table-area bbai-library-table-wrapper">
        <div class="bbai-library-table-column">
        <div class="bbai-table-card">
            <div class="bbai-table-wrap">
	                <table class="bbai-table bbai-library-table bbai-saas-table">
	                    <thead>
	                        <tr>
	                            <th class="bbai-library-col-select">
	                                <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
	                            </th>
	                            <th class="bbai-library-col-image"><?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?></th>
	                            <th class="bbai-library-col-alt"><?php esc_html_e('ALT Review', 'beepbeep-ai-alt-text-generator'); ?></th>
	                            <th class="bbai-library-col-status"><?php esc_html_e('Workflow', 'beepbeep-ai-alt-text-generator'); ?></th>
	                        </tr>
	                    </thead>
                    <tbody id="bbai-library-table-body">
                        <?php foreach ($bbai_all_images as $bbai_image) : ?>
                            <?php
                            $bbai_attachment_id = $bbai_image->ID;
                            $bbai_current_alt = $bbai_image->alt_text ?? '';
                            $bbai_clean_alt = is_string($bbai_current_alt) ? trim($bbai_current_alt) : '';
                            $bbai_has_alt = !empty($bbai_clean_alt);

                            $bbai_thumb_url = wp_get_attachment_image_src($bbai_attachment_id, 'thumbnail');
                            $bbai_attached_file = get_attached_file($bbai_attachment_id);
                            $bbai_filename = $bbai_attached_file ? basename($bbai_attached_file) : '';
                            // Format date
                            $bbai_modified_date = date_i18n('j M Y', strtotime($bbai_image->post_modified));

                            // Keep full alt text in DOM and clamp with CSS for readable ellipsis.
                            $bbai_alt_preview = $bbai_clean_alt ?: '';

                            // Truncate filename for display.
                            $bbai_display_filename = !empty($bbai_filename) && is_string($bbai_filename) ? $bbai_filename : __('Unknown file', 'beepbeep-ai-alt-text-generator');
                            if (is_string($bbai_display_filename) && strlen($bbai_display_filename) > 35) {
                                $bbai_display_filename = wp_html_excerpt($bbai_display_filename, 32, '...');
                            }
                            
                            // Get file extension, dimensions, and size safely.
                            $bbai_file_extension = '';
                            $bbai_file_dimensions = '';
                            $bbai_file_size = '';
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
                                        $bbai_file_dimensions = $bbai_width . '×' . $bbai_height;
                                    }
                                }

                                $bbai_file_size_bytes = filesize($bbai_attached_file);
                                if ($bbai_file_size_bytes !== false && is_numeric($bbai_file_size_bytes)) {
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

                            // Determine queue state
                            $status = 'missing';
                            $bbai_status_label = bbai_copy_status_missing();
                            $bbai_quality_class = 'poor';
                            $bbai_quality_label = bbai_copy_score_poor();
                            $bbai_quality_score = 0;
                            $bbai_word_count = 0;
                            $bbai_analysis = null;
                            $bbai_is_user_approved = false;

                            if ($bbai_has_alt) {
                                $bbai_word_count = str_word_count($bbai_clean_alt);
                                $bbai_analysis = method_exists($this, 'evaluate_alt_health')
                                    ? $this->evaluate_alt_health($bbai_attachment_id, $bbai_clean_alt)
                                    : null;
                                if ($bbai_analysis && isset($bbai_analysis['score'])) {
                                    $bbai_quality_score = (int) $bbai_analysis['score'];
                                    $bbai_grade = isset($bbai_analysis['grade']) ? (string) $bbai_analysis['grade'] : '';
                                    $bbai_is_user_approved = !empty($bbai_analysis['user_approved']);
                                    if (stripos($bbai_grade, 'Excellent') !== false || $bbai_quality_score >= 90) {
                                        $bbai_quality_class = 'excellent';
                                        $bbai_quality_label = bbai_copy_score_excellent();
                                    } elseif (stripos($bbai_grade, 'Strong') !== false || stripos($bbai_grade, 'Good') !== false || $bbai_quality_score >= 75) {
                                        $bbai_quality_class = 'good';
                                        $bbai_quality_label = bbai_copy_score_good();
                                    } elseif (stripos($bbai_grade, 'Needs review') !== false || stripos($bbai_grade, 'Needs improvement') !== false || $bbai_quality_score >= 60) {
                                        $bbai_quality_class = 'needs-review';
                                        $bbai_quality_label = bbai_copy_score_needs_improvement();
                                    } else {
                                        $bbai_quality_class = 'poor';
                                        $bbai_quality_label = bbai_copy_score_poor();
                                    }
                                } else {
                                    $bbai_quality_score = function_exists('bbai_calculate_alt_quality_score') ? bbai_calculate_alt_quality_score($bbai_clean_alt) : 50;
                                    if ($bbai_quality_score >= 90) {
                                        $bbai_quality_class = 'excellent';
                                        $bbai_quality_label = bbai_copy_score_excellent();
                                    } elseif ($bbai_quality_score >= 80) {
                                        $bbai_quality_class = 'good';
                                        $bbai_quality_label = bbai_copy_score_good();
                                    } elseif ($bbai_quality_score >= 60) {
                                        $bbai_quality_class = 'needs-review';
                                        $bbai_quality_label = bbai_copy_score_needs_improvement();
                                    } else {
                                        $bbai_quality_class = 'poor';
                                        $bbai_quality_label = bbai_copy_score_poor();
                                    }
                                }

                                // Hard-fail safety net: re-check with unified scorer
                                // to ensure bad ALT can never display as "Good".
                                if (in_array($bbai_quality_class, ['excellent', 'good'], true)
                                    && class_exists('BBAI_Alt_Quality_Scorer')) {
                                    $bbai_safety_check = BBAI_Alt_Quality_Scorer::score($bbai_clean_alt);
                                    if (!empty($bbai_safety_check['hard_fail'])) {
                                        $bbai_quality_score = $bbai_safety_check['score'];
                                        $bbai_badge_map = array(
                                            'excellent' => 'excellent',
                                            'good'      => 'good',
                                            'fair'      => 'needs-review',
                                            'poor'      => 'poor',
                                            'needs-work'=> 'poor',
                                        );
                                        $bbai_sbadge = isset($bbai_safety_check['badge']) ? (string) $bbai_safety_check['badge'] : '';
                                        $bbai_quality_class = isset($bbai_badge_map[ $bbai_sbadge ]) ? $bbai_badge_map[ $bbai_sbadge ] : 'poor';
                                        $bbai_quality_label = isset($bbai_safety_check['label']) ? (string) $bbai_safety_check['label'] : bbai_copy_score_poor();
                                        $bbai_analysis['breakdown']          = $bbai_safety_check['breakdown'];
                                        $bbai_analysis['issues']             = $bbai_safety_check['issues'];
                                        $bbai_analysis['suggestions']       = $bbai_safety_check['suggestions'];
                                        $bbai_analysis['hard_fail']          = true;
                                        $bbai_analysis['optimized_eligible'] = false;
                                    }
                                }

                                $bbai_hard_fail_lt = ! empty( $bbai_analysis['hard_fail'] );
                                $bbai_breakdown_lt = isset( $bbai_analysis['breakdown'] ) && is_array( $bbai_analysis['breakdown'] ) ? $bbai_analysis['breakdown'] : null;
                                $bbai_opt_eligible_lt = class_exists( 'BBAI_Alt_Quality_Scorer' )
                                    ? BBAI_Alt_Quality_Scorer::passes_optimized_row_gates( $bbai_clean_alt, $bbai_quality_score, $bbai_breakdown_lt, $bbai_hard_fail_lt )
                                    : false;
                                $bbai_row_optimized_lt = $bbai_opt_eligible_lt && $bbai_quality_score >= 70;

                                if ( $bbai_row_optimized_lt ) {
                                    $status = 'optimized';
                                    $bbai_status_label = bbai_copy_status_optimized();
                                } else {
                                    $status = 'weak';
                                    $bbai_status_label = bbai_copy_status_needs_review();
                                }
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
                            $bbai_breakdown = isset($bbai_analysis['breakdown']) ? $bbai_analysis['breakdown'] : null;
                            $bbai_suggestions = isset($bbai_analysis['suggestions']) ? $bbai_analysis['suggestions'] : array();
                            if ($bbai_has_alt) {
                                // Build a detailed tooltip with score breakdown when available.
                                if ($bbai_breakdown && is_array($bbai_breakdown)) {
                                    $bbai_tooltip_lines = array();
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: 1: label, 2: score */
                                        __('Quality: %1$s (%2$d/100)', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_quality_label,
                                        $bbai_quality_score
                                    );
                                    $bbai_tooltip_lines[] = '';
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: %d: descriptiveness score out of 100. */
                                        __('Descriptiveness: %d', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_breakdown['descriptiveness']
                                    );
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: %d: relevance score out of 100. */
                                        __('Relevance: %d', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_breakdown['relevance']
                                    );
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: %d: accessibility score out of 100. */
                                        __('Accessibility: %d', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_breakdown['accessibility']
                                    );
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: %d: SEO score out of 100. */
                                        __('SEO: %d', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_breakdown['seo']
                                    );
                                    $bbai_tooltip_lines[] = sprintf(
                                        /* translators: %d: conciseness score out of 100. */
                                        __('Conciseness: %d', 'beepbeep-ai-alt-text-generator'),
                                        $bbai_breakdown['conciseness']
                                    );
                                    if (!empty($bbai_analysis['issues'])) {
                                        $bbai_tooltip_lines[] = '';
                                        foreach (array_slice($bbai_analysis['issues'], 0, 3) as $bbai_issue_line) {
                                            $bbai_tooltip_lines[] = '• ' . $bbai_issue_line;
                                        }
                                    }
                                    $bbai_quality_tooltip = implode("\n", $bbai_tooltip_lines);
                                } elseif ($bbai_quality_class === 'excellent') {
                                    $bbai_quality_tooltip = __('ALT text is descriptive and SEO-friendly', 'beepbeep-ai-alt-text-generator');
                                } elseif ($bbai_quality_class === 'good') {
                                    $bbai_quality_tooltip = __('ALT text is clear and descriptive', 'beepbeep-ai-alt-text-generator');
                                } elseif ($bbai_quality_class === 'needs-review') {
                                    $bbai_quality_tooltip = __('ALT text could use more descriptive detail', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    $bbai_quality_tooltip = __('ALT text is too short or lacks descriptive detail', 'beepbeep-ai-alt-text-generator');
                                }
                            }
                            $bbai_row_summary = $bbai_quality_tooltip;
                            if (!$bbai_has_alt) {
                                $bbai_row_summary = __('No ALT text yet. Add one inline or generate it with AI.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($bbai_is_user_approved) {
                                $bbai_row_summary = __('Reviewed and approved by you.', 'beepbeep-ai-alt-text-generator');
                            } elseif ($status === 'weak' && !empty($bbai_analysis['issues'][0])) {
                                $bbai_row_summary = (string) $bbai_analysis['issues'][0];
                            } elseif ($status === 'optimized') {
                                $bbai_row_summary = __('Ready to publish.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                            <tr class="bbai-library-row"
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
                                data-file-meta="<?php echo esc_attr($bbai_file_meta); ?>"
                                data-last-updated="<?php echo esc_attr($bbai_modified_date); ?>"
                                data-status-label="<?php echo esc_attr($bbai_status_label); ?>"
                                data-review-summary="<?php echo esc_attr($bbai_row_summary); ?>"
                                data-quality-label="<?php echo esc_attr($bbai_quality_label); ?>"
                                data-quality-class="<?php echo esc_attr($bbai_quality_class); ?>"
                                data-quality-tooltip="<?php echo esc_attr($bbai_quality_tooltip); ?>"
                                data-quality-score="<?php echo esc_attr($bbai_quality_score); ?>"
                                <?php if ($bbai_breakdown) : ?>data-quality-breakdown="<?php echo esc_attr(wp_json_encode($bbai_breakdown)); ?>"<?php endif; ?>
                                <?php if (!empty($bbai_suggestions)) : ?>data-quality-suggestions="<?php echo esc_attr(wp_json_encode(array_slice($bbai_suggestions, 0, 4))); ?>"<?php endif; ?>
                                <?php if (!empty($bbai_analysis['issues'])) : ?>data-quality-issues="<?php echo esc_attr(wp_json_encode(array_slice($bbai_analysis['issues'], 0, 5))); ?>"<?php endif; ?>>
                                <td class="bbai-library-cell--select">
                                    <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($bbai_attachment_id); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php
                                    /* translators: 1: image title */
                                    printf(esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'), esc_attr($bbai_image->post_title));
                                    ?>" />
                                </td>
                                <td class="bbai-library-cell--asset">
                                    <div class="bbai-library-asset">
                                        <?php if ($bbai_thumb_url) : ?>
                                            <button type="button"
                                                    class="bbai-library-thumbnail-button"
                                                    data-action="preview-image"
                                                    data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                    aria-label="<?php esc_attr_e('Preview image', 'beepbeep-ai-alt-text-generator'); ?>">
                                                <img src="<?php echo esc_url($bbai_thumb_url[0]); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
                                                <?php if (!empty($bbai_modal_image_url)) : ?>
                                                    <span class="bbai-library-hover-preview" aria-hidden="true">
                                                        <img src="<?php echo esc_url($bbai_modal_image_url); ?>" alt="" loading="lazy" decoding="async" />
                                                    </span>
                                                <?php endif; ?>
                                            </button>
                                        <?php else : ?>
                                            <div class="bbai-library-thumbnail-placeholder">
                                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                    <path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="bbai-library-info-cell">
                                            <span class="bbai-library-info-name" title="<?php echo esc_attr($bbai_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></span>
                                            <?php if (!empty($bbai_file_meta)) : ?>
                                                <span class="bbai-library-info-meta"><?php echo esc_html($bbai_file_meta); ?></span>
                                            <?php endif; ?>
                                            <span class="bbai-library-info-updated">
                                                <?php
                                                printf(
                                                    /* translators: %s: updated date */
                                                    esc_html__('Updated %s', 'beepbeep-ai-alt-text-generator'),
                                                    esc_html($bbai_modified_date)
                                                );
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--alt-text">
                                    <div class="bbai-library-review-block">
                                        <div class="bbai-library-review-label">
                                            <?php esc_html_e('ALT text', 'beepbeep-ai-alt-text-generator'); ?>
                                            <span class="bbai-library-review-tip"
                                                  data-bbai-tooltip="<?php esc_attr_e('ALT text should describe the image clearly for accessibility.', 'beepbeep-ai-alt-text-generator'); ?>"
                                                  data-bbai-tooltip-position="top"
                                                  tabindex="0">i</span>
                                        </div>
                                        <div class="bbai-library-alt-slot" data-bbai-alt-slot="1">
                                        <button type="button"
                                                class="bbai-library-alt-trigger"
                                                data-action="edit-alt-inline"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                            <?php if ($bbai_has_alt) : ?>
                                                <span class="bbai-alt-text-preview" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></span>
                                            <?php else : ?>
                                                <span class="bbai-alt-text-missing"><?php esc_html_e('No alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                        </div>
                                        <p class="bbai-library-alt-helper"><?php echo esc_html($bbai_has_alt ? __('Click the ALT text to edit inline and save without leaving the page.', 'beepbeep-ai-alt-text-generator') : __('Add ALT text inline or generate it with AI.', 'beepbeep-ai-alt-text-generator')); ?></p>
                                        <?php if ($status === 'weak') : ?>
                                            <p class="bbai-library-alt-summary"><?php echo esc_html($bbai_row_summary); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--status">
                                    <div class="bbai-library-status-stack">
                                        <span class="bbai-library-status-badge bbai-library-status-badge--<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html($bbai_status_label); ?>
                                        </span>
                                        <p class="bbai-library-status-copy"><?php echo esc_html($bbai_row_summary); ?></p>
                                        <div class="bbai-library-actions">
                                            <div class="bbai-library-actions__main">
                                                <button type="button"
                                                        class="bbai-row-action-btn bbai-row-action-btn--primary <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                                                        data-action="regenerate-single"
                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                        data-bbai-lock-preserve-label="1"
                                                        title="<?php echo esc_attr($bbai_has_alt ? __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator') : bbai_copy_cta_generate_missing_images()); ?>"
                                                        <?php if ($bbai_limit_reached_state) : ?>
                                                            data-bbai-action="open-upgrade"
                                                            data-bbai-locked-cta="1"
                                                            data-bbai-lock-reason="regenerate_single"
                                                            data-bbai-locked-source="library-row-regenerate"
                                                            aria-disabled="true"
                                                        <?php endif; ?>>
                                                    <?php echo esc_html($bbai_has_alt ? __('Regenerate', 'beepbeep-ai-alt-text-generator') : __('Generate', 'beepbeep-ai-alt-text-generator')); ?>
                                                </button>
                                                <button type="button"
                                                        class="bbai-row-action-btn"
                                                        data-action="edit-alt-inline"
                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                        title="<?php echo esc_attr(__('Edit ALT text manually', 'beepbeep-ai-alt-text-generator')); ?>">
                                                    <?php esc_html_e('Edit manually', 'beepbeep-ai-alt-text-generator'); ?>
                                                </button>
                                            </div>
                                            <?php
                                            $bbai_table_extra_copy = $bbai_has_alt;
                                            $bbai_table_extra_improve = $bbai_has_alt && 'weak' === $status;
                                            $bbai_table_extra_review = 'weak' === $status && $bbai_has_alt && !$bbai_is_user_approved;
                                            $bbai_table_has_extras = $bbai_table_extra_copy || $bbai_table_extra_improve || $bbai_table_extra_review;
                                            ?>
                                            <?php if ($bbai_table_has_extras) : ?>
                                                <div class="bbai-library-actions__extras" role="group" aria-label="<?php esc_attr_e('Additional actions', 'beepbeep-ai-alt-text-generator'); ?>">
                                                    <?php if ($bbai_table_extra_copy) : ?>
                                                        <button type="button"
                                                                class="bbai-row-action-btn bbai-row-action-btn--ghost"
                                                                data-action="copy-alt-text"
                                                                data-alt-text="<?php echo esc_attr($bbai_clean_alt); ?>"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                            <?php esc_html_e('Copy ALT', 'beepbeep-ai-alt-text-generator'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($bbai_table_extra_improve) : ?>
                                                        <button type="button"
                                                                class="bbai-row-action-btn bbai-row-action-btn--ghost <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                                                                data-action="phase17-improve-alt"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                                title="<?php echo esc_attr($bbai_limit_reached_state ? __('Upgrade to unlock AI improvements', 'beepbeep-ai-alt-text-generator') : __('Improve ALT with AI (uses one credit)', 'beepbeep-ai-alt-text-generator')); ?>"
                                                                <?php if ($bbai_limit_reached_state) : ?>
                                                                    data-bbai-action="open-upgrade"
                                                                    data-bbai-locked-cta="1"
                                                                    data-bbai-lock-reason="regenerate_single"
                                                                    data-bbai-locked-source="library-table-phase17-improve"
                                                                    aria-disabled="true"
                                                                <?php endif; ?>>
                                                            <?php esc_html_e('Improve ALT', 'beepbeep-ai-alt-text-generator'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($bbai_table_extra_review) : ?>
                                                        <button type="button"
                                                                class="bbai-row-action-btn bbai-row-action-btn--ghost"
                                                                data-action="approve-alt-inline"
                                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                            <?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($bbai_total_pages > 1) : ?>
            <div class="bbai-pagination bbai-table-meta">
                <span class="bbai-pagination-info">
                    <?php
                    $bbai_start = $bbai_offset + 1;
                    $bbai_end = min($bbai_offset + $bbai_per_page, $bbai_total_count);
                    printf(
                        /* translators: 1: start number, 2: end number, 3: total count */
                        esc_html__('Showing %1$s-%2$s of %3$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format_i18n($bbai_start)),
                        esc_html(number_format_i18n($bbai_end)),
                        esc_html(number_format_i18n($bbai_total_count))
                    );
                    ?>
                </span>
                <div class="bbai-pagination-controls">
                    <?php if ($bbai_current_page > 1) : ?>
                        <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page - 1)); ?>" class="bbai-pagination-btn">
                            <?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php endif; ?>
                    <?php
                    // Show page numbers
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
                    <?php if ($bbai_current_page < $bbai_total_pages) : ?>
                        <a href="<?php echo esc_url(add_query_arg('alt_page', $bbai_current_page + 1)); ?>" class="bbai-pagination-btn">
                            <?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- /.bbai-library-table-column -->

        <!-- Upgrade Sidebar (second column, never overlapping) -->
        <?php if ($bbai_is_free) : ?>
            <div class="bbai-library-upgrade-float">
                <?php
                $bbai_bottom_upsell_compact = true;
                $bbai_bottom_upsell_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/bottom-upsell-cta.php';
                if (file_exists($bbai_bottom_upsell_partial)) {
                    include $bbai_bottom_upsell_partial;
                }
                ?>
            </div>
        <?php endif; ?>
        </div><!-- /.bbai-library-table-area -->
    <?php else : ?>
        <?php
        $bbai_empty_missing_count = isset($bbai_missing_count) ? max(0, (int) $bbai_missing_count) : 0;
        $bbai_empty_can_generate = isset($bbai_can_generate) ? (bool) $bbai_can_generate : false;
        $bbai_empty_show_generate = $bbai_empty_can_generate && $bbai_empty_missing_count > 0;
        ?>
        <!-- Empty State (shared product-state surface) -->
        <div class="bbai-library-empty-state bbai-empty-state">
            <div class="bbai-card bbai-text-center bbai-p-12">
                <?php
                $bbai_lib_empty_icon = '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
                    . '<path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z" stroke="currentColor" stroke-width="1.5"/>'
                    . '<path d="M8 15l2.5-2.5L13 15l3-3 2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
                    . '<circle cx="9" cy="9" r="1.2" fill="currentColor"/>'
                    . '</svg>';
                ob_start();
                ?>
                <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-primary bbai-btn-sm">
                    <?php esc_html_e('Go to Media Library', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
                <?php if ($bbai_empty_show_generate) : ?>
                    <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="generate-missing">
                        <?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?>
                    </button>
                <?php endif; ?>
                <?php
                $bbai_lib_empty_actions = ob_get_clean();
                bbai_ui_render(
                    'product-state',
                    [
                        'variant'      => 'empty',
                        'icon_html'    => $bbai_lib_empty_icon,
                        'title'        => __('No images analyzed yet', 'beepbeep-ai-alt-text-generator'),
                        'body'         => __('Upload images to your media library and BeepBeep will generate alt text here.', 'beepbeep-ai-alt-text-generator'),
                        'actions_html' => $bbai_lib_empty_actions,
                    ]
                );
                ?>
            </div>
        </div>

        <!-- Summary Statistics (always show) -->
        <?php
        // Prepare data for reusable metric cards component.
        $bbai_alt_texts_generated = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? ($bbai_usage_stats['used'] ?? 0) : 0;
        $bbai_description_mode = ($bbai_alt_texts_generated > 0 || $bbai_with_alt_count > 0) ? 'active' : 'empty';
        $bbai_wrapper_class = 'bbai-premium-metrics-grid bbai-mt-6';
        $bbai_metric_cards_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/metric-cards.php';
        if (file_exists($bbai_metric_cards_partial)) {
            include $bbai_metric_cards_partial;
        }
        ?>
    <?php endif; ?>

</div>
