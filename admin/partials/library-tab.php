<?php
/**
 * ALT Library tab content partial.
 * Modernized to match BeepBeep AI SaaS style with unified.css components
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\BBAI_Cache;

// Pagination setup
$bbai_per_page = 10;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
$bbai_alt_page_input = isset($_GET['alt_page']) ? absint(wp_unslash($_GET['alt_page'])) : 0;
$bbai_current_page = max(1, $bbai_alt_page_input);
$bbai_offset = ($bbai_current_page - 1) * $bbai_per_page;

global $wpdb;

$bbai_image_mime_like = $wpdb->esc_like('image/') . '%';

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

// Get all images with their alt text (cached per page).
$bbai_images_cache_suffix = 'images_' . $bbai_current_page . '_' . $bbai_per_page;
$bbai_all_images = BBAI_Cache::get( 'library', $bbai_images_cache_suffix );
if ( false === $bbai_all_images ) {
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
    BBAI_Cache::set( 'library', $bbai_images_cache_suffix, $bbai_all_images, BBAI_Cache::DEFAULT_TTL );
}

$bbai_total_count = $bbai_total_images;
$bbai_total_pages = ceil($bbai_total_count / $bbai_per_page);

// Get plan info
$bbai_has_license = $this->api_client->has_active_license();
$bbai_license_data = $this->api_client->get_license_data();
$bbai_plan_slug = 'free';

// Try to get plan from usage_stats if available
if (isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan'])) {
    $bbai_plan_slug = strtolower($bbai_usage_stats['plan']);
}

if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization']) && is_array($bbai_license_data['organization'])) {
    $bbai_license_plan = !empty($bbai_license_data['organization']['plan']) ? strtolower($bbai_license_data['organization']['plan']) : 'free';
    if ($bbai_license_plan !== 'free') {
        $bbai_plan_slug = $bbai_license_plan;
    }
}

// Map 'pro' to 'growth' for consistency
if ($bbai_plan_slug === 'pro') {
    $bbai_plan_slug = 'growth';
}

$bbai_is_free = ($bbai_plan_slug === 'free');
$bbai_is_growth = ($bbai_plan_slug === 'growth');
$bbai_is_agency = ($bbai_plan_slug === 'agency');
$bbai_is_pro = ($bbai_is_growth || $bbai_is_agency);

// Calculate stats percentages
$bbai_optimized_percent = $bbai_total_images > 0 ? round(($bbai_with_alt_count / $bbai_total_images) * 100) : 0;

// Get ALT coverage for Smart Review Filters (optimized, needs_review, missing, auto_generated).
$bbai_coverage = isset($this) && method_exists($this, 'get_alt_text_coverage_scan') ? $this->get_alt_text_coverage_scan(false) : [];
if (empty($bbai_coverage) || !isset($bbai_coverage['total_images'])) {
    $bbai_coverage = [
        'total_images' => $bbai_total_images,
        'images_missing_alt' => $bbai_missing_count,
        'optimized_count' => $bbai_with_alt_count,
        'needs_review_count' => 0,
        'ai_source_count' => 0,
    ];
}
?>

<div class="bbai-library-container" data-bbai-empty-filter="<?php echo esc_attr__('No images match this filter.', 'beepbeep-ai-alt-text-generator'); ?>">
    <style id="bbai-library-polish">
    /* Visibility + polish for ALT Library */
    #bbai-alt-coverage-card,
    #bbai-review-filter-tabs { display: block !important; visibility: visible !important; opacity: 1 !important; }
    #bbai-review-filter-tabs { margin-top: 12px; margin-bottom: 20px; display: flex !important; gap: 8px; flex-wrap: wrap; }
    .bbai-toolbar.bbai-library-batch-actions { margin-bottom: 20px; }
    .bbai-saas-table .bbai-library-col-alt,
    .bbai-saas-table th.bbai-library-col-alt,
    .bbai-library-table .bbai-library-col-alt,
    .bbai-library-table th.bbai-library-col-alt,
    .bbai-library-cell--alt-text,
    .bbai-library-table td.bbai-library-cell--alt-text { display: table-cell !important; visibility: visible !important; width: 340px !important; min-width: 200px !important; max-width: 340px !important; }
    /* Coverage card */
    .bbai-alt-coverage-card-inline { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .bbai-alt-coverage-stats { display: flex; flex-wrap: wrap; gap: 28px; margin-bottom: 14px; }
    .bbai-alt-stat span { display: block; font-size: 12px; color: #6b7280; }
    .bbai-alt-stat strong { display: block; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.1; }
    .bbai-alt-coverage-title { font-size: 15px; font-weight: 600; margin: 0 0 12px; color: #111827; }
    /* Progress bar */
    .bbai-alt-coverage-progress { height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden; display: flex; }
    .bbai-alt-coverage-progress span { height: 100%; display: block; }
    /* Review filter tabs (pills) - !important to override unified.css */
    .bbai-alt-review-filters__btn { background: #ffffff !important; border: 1px solid #d1d5db !important; color: #374151 !important; padding: 7px 14px !important; border-radius: 999px !important; font-size: 13px !important; font-weight: 500 !important; cursor: pointer; transition: all .15s ease; }
    .bbai-alt-review-filters__btn:hover { background: #f9fafb !important; border-color: #9ca3af !important; }
    .bbai-alt-review-filters__btn--active { background: #22c55e !important; border-color: #22c55e !important; color: #ffffff !important; }
    /* Table header */
    .bbai-library-table thead th { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: #374151; }
    /* Table body */
    .bbai-library-table tbody td { border-bottom: 1px solid #f1f5f9; }
    .bbai-library-table tbody tr:hover { background: #f9fafb; }
    /* ALT text preview */
    .bbai-alt-text-preview { font-size: 13px; line-height: 1.45; color: #374151; max-width: 340px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .bbai-alt-text-missing { font-size: 12px; color: #ef4444; font-style: italic; }
    /* Table wrapper */
    .bbai-library-table-wrapper { margin-top: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    /* Column widths */
    .bbai-library-col-image { width: 72px !important; }
    .bbai-library-col-info { width: 220px !important; }
    .bbai-library-col-alt { width: 340px !important; }
    .bbai-library-col-status { width: 120px !important; }
    .bbai-library-col-updated { width: 120px !important; }
    .bbai-library-col-actions { width: 120px !important; }
    .bbai-alt-success { font-size: 13px; color: #16a34a; margin-top: 6px; }
    /* Image preview modal polish */
    .bbai-library-preview-modal__dialog { border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 24px; }
    @media (max-width: 640px) {
        .bbai-alt-coverage-stats { flex-wrap: wrap; gap: 16px; }
    }
    </style>
    <!-- Header -->
    <div class="bbai-page-header bbai-mb-6">
        <div class="bbai-page-header-content">
            <h1 class="bbai-heading-1"><?php esc_html_e('Image ALT Text Library', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-subtitle"><?php esc_html_e('Review, edit, and regenerate AI alt text for images in your media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </div>

    <?php
    // Bulk scan recommendation: show when library has many images and some are missing
    $bbai_show_scan_banner = $bbai_total_images >= 50 && $bbai_missing_count > 0;
    if ($bbai_show_scan_banner) :
        $bbai_scan_library_url = add_query_arg(['page' => 'bbai-library', 'status' => 'missing'], admin_url('admin.php'));
        ?>
        <div class="bbai-scan-banner bbai-mb-4">
            <p class="bbai-scan-banner__text">
                <?php
                printf(
                    /* translators: %d: number of images */
                    esc_html__('Your site has %s images. Scan now to find missing ALT text and improve media SEO.', 'beepbeep-ai-alt-text-generator'),
                    '<strong>' . esc_html(number_format_i18n($bbai_total_images)) . '</strong>'
                );
                ?>
            </p>
            <a href="<?php echo esc_url($bbai_scan_library_url); ?>" class="bbai-scan-banner__cta bbai-btn bbai-btn-primary bbai-btn-sm">
                <?php esc_html_e('Scan Library', 'beepbeep-ai-alt-text-generator'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php
    // Precompute limit state for quick-actions (same logic as bulk actions block)
    $bbai_plan = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
    $bbai_has_quota = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && isset($bbai_usage_stats['remaining']) ? (intval($bbai_usage_stats['remaining']) > 0) : false;
    $bbai_is_premium = in_array($bbai_plan, ['pro', 'growth', 'agency'], true);
    $bbai_out_of_credits = !$bbai_has_quota && !$bbai_is_premium;
    $bbai_limit_reached_state = $bbai_out_of_credits;
    $bbai_stats = isset($bbai_stats) ? $bbai_stats : ['total' => $bbai_total_images, 'with_alt' => $bbai_with_alt_count, 'missing' => $bbai_missing_count];

    // No stats slot on library page — Quick Actions go straight to action cards.
    $bbai_quick_actions_stats_slot = '';
    ?>

    <?php
    $bbai_quick_actions_path = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/quick-actions.php';
    if (file_exists($bbai_quick_actions_path)) {
        include $bbai_quick_actions_path;
    }
    $bbai_quick_actions_stats_slot = '';

    // Coverage stats (unconditional - always compute)
    $bbai_cov_optimized = isset($bbai_coverage['optimized_count']) ? (int) $bbai_coverage['optimized_count'] : $bbai_with_alt_count;
    $bbai_cov_needs_review = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
    $bbai_cov_missing = isset($bbai_coverage['images_missing_alt']) ? (int) $bbai_coverage['images_missing_alt'] : $bbai_missing_count;
    $bbai_cov_auto = isset($bbai_coverage['ai_source_count']) ? (int) $bbai_coverage['ai_source_count'] : 0;
    $bbai_cov_total = isset($bbai_coverage['total_images']) ? (int) $bbai_coverage['total_images'] : $bbai_total_images;
    $bbai_cov_opt_pct = $bbai_cov_total > 0 ? round(($bbai_cov_optimized / $bbai_cov_total) * 100) : 0;
    $bbai_cov_review_pct = $bbai_cov_total > 0 ? round(($bbai_cov_needs_review / $bbai_cov_total) * 100) : 0;
    $bbai_cov_miss_pct = $bbai_cov_total > 0 ? round(($bbai_cov_missing / $bbai_cov_total) * 100) : 0;
    ?>
    <!-- ALT Coverage card - immediately after Quick Actions -->
    <div id="bbai-alt-coverage-card" class="bbai-alt-coverage-card-inline">
        <h2 id="bbai-alt-coverage-title" class="bbai-alt-coverage-title"><?php esc_html_e('ALT Coverage', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <div class="bbai-alt-coverage-stats">
            <div class="bbai-alt-stat">
                <span><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($bbai_cov_optimized)); ?></strong>
            </div>
            <div class="bbai-alt-stat">
                <span><?php esc_html_e('Needs Review', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($bbai_cov_needs_review)); ?></strong>
            </div>
            <div class="bbai-alt-stat">
                <span><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($bbai_cov_missing)); ?></strong>
            </div>
            <div class="bbai-alt-stat">
                <span><?php esc_html_e('Auto Generated', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong><?php echo esc_html(number_format_i18n($bbai_cov_auto)); ?></strong>
            </div>
        </div>
        <div class="bbai-alt-coverage-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_cov_opt_pct); ?>" aria-valuemin="0" aria-valuemax="100">
            <span style="background:#3b82f6;flex:0 0 <?php echo esc_attr($bbai_cov_opt_pct); ?>%;"></span>
            <span style="background:#f59e0b;flex:0 0 <?php echo esc_attr($bbai_cov_review_pct); ?>%;"></span>
            <span style="background:#ef4444;flex:0 0 <?php echo esc_attr($bbai_cov_miss_pct); ?>%;"></span>
        </div>
        <?php if ($bbai_missing_count == 0) : ?>
        <div class="bbai-alt-success">✓ <?php esc_html_e('All images now have ALT text', 'beepbeep-ai-alt-text-generator'); ?></div>
        <?php endif; ?>
    </div>
    <!-- Review filter tabs (bbai-alt-review-filters for alt-library-filters.js) -->
    <div id="bbai-review-filter-tabs" class="bbai-alt-review-filters">
        <button type="button" class="bbai-alt-review-filters__btn bbai-alt-review-filters__btn--active" data-filter="all"><?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_total)); ?>)</button>
        <button type="button" class="bbai-alt-review-filters__btn" data-filter="needs-review"><?php esc_html_e('Needs Review', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_needs_review)); ?>)</button>
        <button type="button" class="bbai-alt-review-filters__btn" data-filter="missing"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_missing)); ?>)</button>
        <button type="button" class="bbai-alt-review-filters__btn" data-filter="optimized"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_optimized)); ?>)</button>
    </div>

    <?php if ($bbai_total_images > 0) : ?>
        <?php
        // Check if user can generate (same logic as dashboard).
        $bbai_plan = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && !empty($bbai_usage_stats['plan']) ? $bbai_usage_stats['plan'] : 'free';
        $bbai_has_quota = isset($bbai_usage_stats) && is_array($bbai_usage_stats) && isset($bbai_usage_stats['remaining']) ? (intval($bbai_usage_stats['remaining']) > 0) : false;
        $bbai_is_premium = in_array($bbai_plan, ['pro', 'growth', 'agency'], true);
        $bbai_can_generate = $bbai_has_quota || $bbai_is_premium;
        $bbai_remaining_credits = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? intval($bbai_usage_stats['remaining'] ?? 0) : 0;
        $bbai_used_credits = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? intval($bbai_usage_stats['used'] ?? 0) : 0;
        $bbai_limit_credits = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? intval($bbai_usage_stats['limit'] ?? 0) : 0;
        $bbai_usage_limit_hit = ($bbai_limit_credits > 0 && $bbai_used_credits >= $bbai_limit_credits);
        $bbai_out_of_credits = ($bbai_remaining_credits <= 0 || $bbai_usage_limit_hit);
        $bbai_limit_reached_state = !$bbai_is_premium && $bbai_out_of_credits;
        $bbai_generate_missing_disabled = $bbai_limit_reached_state || !$bbai_can_generate || $bbai_missing_count <= 0;
        $bbai_reoptimize_disabled = $bbai_limit_reached_state || !$bbai_can_generate;
        $bbai_show_review_existing_action = $bbai_limit_reached_state && $bbai_missing_count <= 0;
        ?>
    <?php endif; ?>

    <!-- Toolbar: bulk actions (when selected) + search + filters -->
    <div class="bbai-toolbar bbai-library-batch-actions" id="bbai-batch-actions" aria-live="polite">
        <div class="bbai-toolbar__bulk-actions" id="bbai-bulk-actions-bar">
            <span class="bbai-toolbar__bulk-count" id="bbai-selected-count">0 <?php esc_html_e('images selected', 'beepbeep-ai-alt-text-generator'); ?></span>
            <div class="bbai-toolbar__bulk-btns">
                <button type="button" class="bbai-toolbar__btn bbai-toolbar__btn--primary <?php echo esc_attr(isset($bbai_limit_reached_state) && $bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>" id="bbai-batch-regenerate" data-action="regenerate-selected"
                    <?php if (isset($bbai_limit_reached_state) && $bbai_limit_reached_state) : ?>
                        data-bbai-action="open-upgrade"
                        data-bbai-locked-cta="1"
                        data-bbai-lock-reason="reoptimize_all"
                        data-bbai-locked-source="library-batch-regenerate"
                        aria-disabled="true"
                        title="<?php esc_attr_e('Upgrade to let AI finish the remaining images', 'beepbeep-ai-alt-text-generator'); ?>"
                    <?php endif; ?>
                    aria-label="<?php esc_attr_e('Re-optimise selected images', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 8a6 6 0 0110.3-4.2M14 8a6 6 0 01-10.3 4.2" stroke-linecap="round"/><path d="M12 1v3.5h-3.5M4 15v-3.5h3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php esc_html_e('Re-optimise selected', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-toolbar__btn" id="bbai-batch-reviewed" data-action="mark-reviewed" aria-label="<?php esc_attr_e('Mark selected as reviewed', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 8l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <button type="button" class="bbai-toolbar__btn" id="bbai-batch-export" data-action="export-alt-text" aria-label="<?php esc_attr_e('Export ALT text for selected', 'beepbeep-ai-alt-text-generator'); ?>">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 10v3h10v-3M8 2v8M5 5l3-3 3 3"/></svg>
                    <?php esc_html_e('Export ALT text', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>
        <div class="bbai-toolbar__right">
            <div class="bbai-toolbar__search">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                    <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <input type="text" id="bbai-library-search" class="bbai-toolbar__search-input" placeholder="<?php esc_attr_e('Search', 'beepbeep-ai-alt-text-generator'); ?>" />
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

    <!-- Select-all checkbox (no duplicate heading - page title is "Image ALT Text Library") -->
    <div class="bbai-section-heading bbai-mb-4" style="display:flex;align-items:center;">
        <label class="bbai-section-heading__check">
            <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
        </label>
    </div>

    <!-- Table + Upgrade sidebar -->
    <?php if (!empty($bbai_all_images)) : ?>
        <div class="bbai-library-table-area bbai-library-table-wrapper">
        <div class="bbai-library-table-column">
        <div class="bbai-table-card">
            <div class="bbai-table-wrap">
                <table class="bbai-table bbai-library-table bbai-saas-table">
                    <thead>
                        <tr>
                            <th class="bbai-library-col-select">
                                <input type="checkbox" class="bbai-checkbox bbai-select-all-table" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
                            </th>
                            <th class="bbai-library-col-image bbai-sortable" data-sort="image">
                                <span class="bbai-th-sortable">
                                    <span class="bbai-sort-dot"></span>
                                    <?php esc_html_e('IMAGE', 'beepbeep-ai-alt-text-generator'); ?>
                                    <svg class="bbai-sort-icon" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2.5 4L5 6.5L7.5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                </span>
                            </th>
                            <th class="bbai-library-col-info"><?php esc_html_e('IMAGE INFO', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-alt" style="width:340px;min-width:200px;"><?php esc_html_e('AI ALT TEXT', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-status bbai-sortable" data-sort="status">
                                <span class="bbai-th-sortable">
                                    <?php esc_html_e('QUALITY', 'beepbeep-ai-alt-text-generator'); ?>
                                    <svg class="bbai-sort-icon" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3 4L5 2L7 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M3 6L5 8L7 6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                </span>
                            </th>
                            <th class="bbai-library-col-updated bbai-sortable" data-sort="updated">
                                <span class="bbai-th-sortable">
                                    <?php esc_html_e('UPDATED', 'beepbeep-ai-alt-text-generator'); ?>
                                    <svg class="bbai-sort-icon" width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M3 4L5 2L7 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M3 6L5 8L7 6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                </span>
                            </th>
                            <th class="bbai-library-col-actions"><?php esc_html_e('ACTIONS', 'beepbeep-ai-alt-text-generator'); ?></th>
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

                            // Determine status
                            $status = 'missing';
                            $bbai_status_label = __('Missing', 'beepbeep-ai-alt-text-generator');
                            $bbai_quality_class = 'poor';
                            $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
                            $bbai_quality_score = 0;
                            $bbai_word_count = 0;

                            if ($bbai_has_alt) {
                                $status = 'optimized';
                                $bbai_status_label = __('Optimized', 'beepbeep-ai-alt-text-generator');
                                $bbai_word_count = str_word_count($bbai_clean_alt);
                                $bbai_analysis = method_exists($this, 'evaluate_alt_health')
                                    ? $this->evaluate_alt_health($bbai_attachment_id, $bbai_clean_alt)
                                    : null;
                                if ($bbai_analysis && isset($bbai_analysis['score'])) {
                                    $bbai_quality_score = (int) $bbai_analysis['score'];
                                    $bbai_grade = isset($bbai_analysis['grade']) ? (string) $bbai_analysis['grade'] : '';
                                    if (stripos($bbai_grade, 'Excellent') !== false || $bbai_quality_score >= 90) {
                                        $bbai_quality_class = 'excellent';
                                        $bbai_quality_label = __('Excellent', 'beepbeep-ai-alt-text-generator');
                                    } elseif (stripos($bbai_grade, 'Strong') !== false || $bbai_quality_score >= 75) {
                                        $bbai_quality_class = 'good';
                                        $bbai_quality_label = __('Good', 'beepbeep-ai-alt-text-generator');
                                    } elseif (stripos($bbai_grade, 'Needs review') !== false || $bbai_quality_score >= 60) {
                                        $bbai_quality_class = 'needs-review';
                                        $bbai_quality_label = __('Needs review', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        $bbai_quality_class = 'poor';
                                        $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
                                    }
                                } else {
                                    $bbai_quality_score = function_exists('bbai_calculate_alt_quality_score') ? bbai_calculate_alt_quality_score($bbai_clean_alt) : 50;
                                    if ($bbai_quality_score >= 90) {
                                        $bbai_quality_class = 'excellent';
                                        $bbai_quality_label = __('Excellent', 'beepbeep-ai-alt-text-generator');
                                    } elseif ($bbai_quality_score >= 80) {
                                        $bbai_quality_class = 'good';
                                        $bbai_quality_label = __('Good', 'beepbeep-ai-alt-text-generator');
                                    } elseif ($bbai_quality_score >= 60) {
                                        $bbai_quality_class = 'needs-review';
                                        $bbai_quality_label = __('Needs review', 'beepbeep-ai-alt-text-generator');
                                    } else {
                                        $bbai_quality_class = 'poor';
                                        $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
                                    }
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
                            ?>
                            <tr class="bbai-library-row"
                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                data-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                data-status="<?php echo esc_attr($status); ?>"
                                data-quality="<?php echo esc_attr($bbai_quality_class); ?>"
                                data-ai-source="<?php echo esc_attr($bbai_ai_source); ?>"
                                data-alt-missing="<?php echo $bbai_has_alt ? 'false' : 'true'; ?>"
                                data-alt-full="<?php echo esc_attr($bbai_clean_alt); ?>"
                                data-image-title="<?php echo esc_attr($bbai_image->post_title); ?>"
                                data-image-url="<?php echo esc_url($bbai_modal_image_url); ?>"
                                data-file-name="<?php echo esc_attr(!empty($bbai_filename) ? $bbai_filename : $bbai_display_filename); ?>"
                                data-file-meta="<?php echo esc_attr($bbai_file_meta); ?>"
                                data-last-updated="<?php echo esc_attr($bbai_modified_date); ?>"
                                data-quality-label="<?php echo esc_attr($bbai_quality_label); ?>"
                                data-quality-class="<?php echo esc_attr($bbai_quality_class); ?>">
                                <td class="bbai-library-cell--select">
                                    <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($bbai_attachment_id); ?>" data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>" aria-label="<?php
                                    /* translators: 1: image title */
                                    printf(esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'), esc_attr($bbai_image->post_title));
                                    ?>" />
                                </td>
                                <td class="bbai-library-cell--image">
                                    <?php if ($bbai_thumb_url) : ?>
                                        <button type="button"
                                                class="bbai-library-thumbnail-button"
                                                data-action="preview-image"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                aria-label="<?php esc_attr_e('Preview image', 'beepbeep-ai-alt-text-generator'); ?>">
                                            <img src="<?php echo esc_url($bbai_thumb_url[0]); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
                                        </button>
                                    <?php else : ?>
                                        <div class="bbai-library-thumbnail-placeholder">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="bbai-library-cell--info">
                                    <div class="bbai-library-info-cell">
                                        <span class="bbai-library-info-name" title="<?php echo esc_attr($bbai_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></span>
                                        <?php if (!empty($bbai_file_meta)) : ?>
                                            <span class="bbai-library-info-meta"><?php echo esc_html($bbai_file_meta); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--alt-text" style="width:340px;min-width:200px;max-width:340px;">
                                    <div class="bbai-alt-text-content">
                                        <?php if ($bbai_has_alt) : ?>
                                            <div class="bbai-alt-text-preview bbai-alt-text-preview-inline" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></div>
                                        <?php else : ?>
                                            <span class="bbai-alt-text-missing"><?php esc_html_e('No alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--status">
                                    <?php if ($bbai_has_alt) : ?>
                                        <span class="bbai-quality-pill bbai-quality-pill--<?php echo esc_attr($bbai_quality_class); ?>">
                                            <?php echo esc_html(strtoupper($bbai_quality_label)); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="bbai-quality-pill bbai-quality-pill--missing">
                                            <?php esc_html_e('MISSING', 'beepbeep-ai-alt-text-generator'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="bbai-library-cell--updated">
                                    <span class="bbai-text-muted bbai-text-sm"><?php echo esc_html($bbai_modified_date); ?></span>
                                </td>
                                <td class="bbai-library-cell--actions">
                                    <div class="bbai-library-actions">
                                        <button type="button"
                                                class="bbai-row-view-btn"
                                                data-action="preview-image"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                title="<?php esc_attr_e('View image', 'beepbeep-ai-alt-text-generator'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="10" height="10" rx="2"/><path d="M6 8h4"/><path d="M8 6v4"/></svg>
                                            <?php esc_html_e('View', 'beepbeep-ai-alt-text-generator'); ?>
                                            <svg width="8" height="8" viewBox="0 0 8 8" fill="none"><path d="M2 3l2 2 2-2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                        </button>
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
            <div class="bbai-pagination">
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
                $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
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
        <!-- Empty State -->
        <div class="bbai-library-empty-state bbai-empty-state">
            <div class="bbai-card bbai-text-center bbai-p-12">
                <div class="bbai-library-empty-content">
                    <div class="bbai-library-empty-icon" aria-hidden="true">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none">
                            <path d="M4 5.5A1.5 1.5 0 015.5 4h13A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5h-13A1.5 1.5 0 014 18.5v-13z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M8 15l2.5-2.5L13 15l3-3 2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="9" cy="9" r="1.2" fill="currentColor"/>
                        </svg>
                    </div>
                    <h3 class="bbai-empty-state-title"><?php esc_html_e('No images analyzed yet', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-empty-state-description">
                        <?php esc_html_e('Upload images to your media library and BeepBeep will generate alt text here.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <div class="bbai-library-empty-actions bbai-flex bbai-gap-3 bbai-items-center bbai-justify-center bbai-flex-wrap">
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-primary bbai-btn-sm">
                            <?php esc_html_e('Go to Media Library', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                        <?php if ($bbai_empty_show_generate) : ?>
                            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="generate-missing">
                                <?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics (always show) -->
        <?php
        // Prepare data for reusable metric cards component.
        $bbai_alt_texts_generated = isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? ($bbai_usage_stats['used'] ?? 0) : 0;
        $bbai_description_mode = ($bbai_alt_texts_generated > 0 || $bbai_with_alt_count > 0) ? 'active' : 'empty';
        $bbai_wrapper_class = 'bbai-premium-metrics-grid bbai-mt-6';
        $bbai_metric_cards_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/metric-cards.php';
        if (file_exists($bbai_metric_cards_partial)) {
            include $bbai_metric_cards_partial;
        }
        ?>
    <?php endif; ?>

</div>
