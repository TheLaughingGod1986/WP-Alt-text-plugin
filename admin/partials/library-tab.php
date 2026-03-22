<?php
/**
 * ALT Library tab content partial.
 * Modernized to match BeepBeep AI SaaS style with unified.css components
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\BBAI_Cache;

$bbai_library_template_buffer_level = ob_get_level();
ob_start();

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
?>

<div class="bbai-library-container" data-bbai-empty-filter="<?php echo esc_attr__('No images match this filter.', 'beepbeep-ai-alt-text-generator'); ?>">
    <style id="bbai-library-polish">
    #bbai-alt-coverage-card,
    #bbai-review-filter-tabs { display: block !important; visibility: visible !important; opacity: 1 !important; }
    .bbai-toolbar.bbai-library-toolbar { margin: 18px 0 16px; }
    .bbai-library-summary-card,
    .bbai-library-guidance,
    .bbai-library-selection-bar {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
    }
    .bbai-library-summary-card { padding: 24px; margin: 20px 0 16px; }
    .bbai-library-summary-card__head,
    .bbai-library-guidance { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
    .bbai-library-summary-card__eyebrow,
    .bbai-library-guidance__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 8px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #0f766e;
    }
    .bbai-library-summary-card__title,
    .bbai-library-guidance__title { margin: 0; font-size: 24px; line-height: 1.15; color: #0f172a; }
    .bbai-library-summary-card__copy,
    .bbai-library-guidance__copy { margin: 8px 0 0; max-width: 640px; color: #475569; }
    .bbai-library-summary-card__stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin: 22px 0 18px;
    }
    .bbai-library-summary-card__stat {
        padding: 14px 16px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
    }
    .bbai-library-summary-card__stat-label { display: block; font-size: 12px; color: #64748b; }
    .bbai-library-summary-card__stat-value { display: block; margin-top: 6px; font-size: 26px; font-weight: 700; color: #0f172a; }
    .bbai-library-summary-card__stat--optimized { border-color: #bbf7d0; background: #f0fdf4; }
    .bbai-library-summary-card__stat--weak { border-color: #fde68a; background: #fffbeb; }
    .bbai-library-summary-card__stat--missing { border-color: #fecaca; background: #fef2f2; }
    .bbai-library-summary-card__bar {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        align-items: center;
    }
    .bbai-library-summary-card__progress {
        grid-column: 1 / -1;
        height: 12px;
        border-radius: 999px;
        overflow: hidden;
        background: #e2e8f0;
        display: flex;
    }
    .bbai-library-summary-card__segment { height: 100%; display: block; }
    .bbai-library-summary-card__segment--optimized { background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%); }
    .bbai-library-summary-card__segment--weak { background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); }
    .bbai-library-summary-card__segment--missing { background: linear-gradient(90deg, #ef4444 0%, #f87171 100%); }
    .bbai-library-summary-card__foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 10px;
        color: #475569;
        font-size: 13px;
    }
    .bbai-library-summary-card__foot strong { color: #0f172a; }
    .bbai-library-guidance {
        margin-bottom: 16px;
        padding: 20px 22px;
    }
    .bbai-library-bulk-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin: 0 0 16px;
        padding: 20px 22px;
        border-radius: 18px;
        border: 1px solid #fcd34d;
        background: linear-gradient(180deg, #fffdf5 0%, #fffbeb 100%);
        box-shadow: 0 18px 40px rgba(120, 53, 15, 0.08);
    }
    .bbai-library-bulk-banner__main {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        min-width: 0;
        flex: 1;
    }
    .bbai-library-bulk-banner__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 12px;
        color: #b45309;
        background: rgba(251, 191, 36, 0.18);
    }
    .bbai-library-bulk-banner__eyebrow {
        margin: 0 0 6px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #92400e;
    }
    .bbai-library-bulk-banner__title {
        margin: 0;
        font-size: 22px;
        line-height: 1.15;
        color: #111827;
    }
    .bbai-library-bulk-banner__copy {
        margin: 8px 0 0;
        color: #4b5563;
        font-size: 14px;
    }
    .bbai-library-bulk-banner__meta {
        margin: 10px 0 0;
        color: #92400e;
        font-size: 13px;
        font-weight: 600;
    }
    .bbai-library-bulk-banner__actions {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
        flex: 0 0 auto;
    }
    .bbai-library-bulk-banner__cta {
        min-width: 232px;
        justify-content: center;
        box-shadow: 0 12px 24px rgba(217, 119, 6, 0.18);
    }
    .bbai-library-bulk-banner__plan-note {
        margin: 0;
        color: #92400e;
        font-size: 12px;
        font-weight: 700;
    }
    .bbai-library-guidance[data-state="healthy"] {
        border-color: #bbf7d0;
        background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
    }
    .bbai-library-guidance__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    #bbai-review-filter-tabs {
        margin: 0 0 16px;
        display: flex !important;
        gap: 10px;
        flex-wrap: wrap;
    }
    .bbai-alt-review-filters__btn {
        background: #ffffff !important;
        border: 1px solid #d1d5db !important;
        color: #334155 !important;
        padding: 9px 15px !important;
        border-radius: 999px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer;
        transition: all .15s ease;
    }
    .bbai-alt-review-filters__btn:hover { background: #f8fafc !important; border-color: #94a3b8 !important; }
    .bbai-alt-review-filters__btn--problem { border-color: #f59e0b !important; background: #fffbeb !important; }
    .bbai-alt-review-filters__btn--active {
        background: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
    }
    .bbai-library-selection-bar {
        position: sticky;
        top: 24px;
        z-index: 6;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 18px;
        margin: 0 0 14px;
    }
    .bbai-library-selection-bar__summary {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        flex-wrap: wrap;
    }
    .bbai-library-selection-bar__lead {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #0f172a;
    }
    .bbai-library-selection-bar__count { font-size: 14px; color: #475569; }
    .bbai-library-selection-bar__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .bbai-library-selection-bar .bbai-toolbar__btn { box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
    .bbai-library-selection-bar .bbai-toolbar__btn:disabled { opacity: 0.52; cursor: not-allowed; }
    .bbai-library-table-wrapper { margin-top: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.05); }
    .bbai-library-table thead th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: #64748b;
        background: #f8fafc;
    }
    .bbai-library-table tbody td { vertical-align: top; border-bottom: 1px solid #edf2f7; }
    .bbai-library-table tbody tr:hover,
    .bbai-library-table tbody tr:hover > td { background: #fcfdff !important; }
    .bbai-library-row--hidden { display: none; }
    .bbai-library-cell--select { width: 56px; }
    .bbai-library-cell--asset { width: 270px; }
    .bbai-library-cell--alt-text { width: 42%; min-width: 320px; }
    .bbai-library-cell--status { width: 280px; }
    .bbai-library-asset { display: flex; gap: 14px; }
    .bbai-library-thumbnail-button {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        background: transparent;
        padding: 0;
        cursor: pointer;
    }
    .bbai-library-thumbnail,
    .bbai-library-thumbnail-placeholder {
        width: 72px;
        height: 72px;
        border-radius: 16px;
        object-fit: cover;
        background: #e2e8f0;
        border: 1px solid #dbeafe;
    }
    .bbai-library-hover-preview {
        position: absolute;
        left: calc(100% + 14px);
        top: 50%;
        transform: translateY(-50%);
        width: 190px;
        padding: 10px;
        border-radius: 18px;
        background: rgba(15, 23, 42, 0.96);
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
        opacity: 0;
        pointer-events: none;
        transition: opacity .18s ease, transform .18s ease;
        z-index: 4;
    }
    .bbai-library-hover-preview img { display: block; width: 100%; border-radius: 12px; }
    .bbai-library-thumbnail-button:hover .bbai-library-hover-preview,
    .bbai-library-thumbnail-button:focus-visible .bbai-library-hover-preview {
        opacity: 1;
        transform: translateY(-50%) scale(1.01);
    }
    .bbai-library-info-cell { display: flex; flex-direction: column; gap: 6px; }
    .bbai-library-info-name { font-size: 14px; font-weight: 700; color: #0f172a; word-break: break-word; }
    .bbai-library-info-meta,
    .bbai-library-info-updated { font-size: 12px; color: #64748b; }
    .bbai-library-review-block { display: flex; flex-direction: column; gap: 8px; }
    .bbai-library-review-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .bbai-library-review-tip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        color: #475569;
        background: #ffffff;
    }
    .bbai-library-alt-trigger {
        width: 100%;
        min-height: 70px;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid #dbe4ee;
        background: #ffffff;
        text-align: left;
        cursor: text;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .bbai-library-alt-trigger:hover,
    .bbai-library-alt-trigger:focus-visible {
        border-color: #38bdf8;
        box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.12);
        outline: none;
    }
    .bbai-alt-text-preview {
        font-size: 14px;
        line-height: 1.55;
        color: #0f172a;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: normal;
    }
    .bbai-alt-text-missing { font-size: 13px; color: #dc2626; font-style: italic; }
    .bbai-library-alt-helper { margin: 0; font-size: 12px; color: #64748b; }
    .bbai-library-alt-summary { margin: 0; font-size: 12px; color: #92400e; }
    .bbai-library-inline-edit { display: flex; flex-direction: column; gap: 10px; }
    .bbai-library-inline-edit__textarea {
        width: 100%;
        min-height: 96px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid #38bdf8;
        box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.12);
        resize: vertical;
    }
    .bbai-library-inline-edit__actions { display: flex; align-items: center; gap: 8px; }
    .bbai-library-inline-edit__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 999px;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #334155;
        cursor: pointer;
    }
    .bbai-library-inline-edit__icon--save { background: #dcfce7; border-color: #86efac; color: #166534; }
    .bbai-library-inline-edit__error { margin: 0; min-height: 18px; font-size: 12px; color: #dc2626; }
    .bbai-library-status-stack { display: flex; flex-direction: column; gap: 12px; }
    .bbai-library-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
        width: fit-content;
    }
    .bbai-library-status-badge--optimized { background: #dcfce7; color: #166534; }
    .bbai-library-status-badge--weak { background: #fef3c7; color: #b45309; }
    .bbai-library-status-badge--missing { background: #fee2e2; color: #b91c1c; }
    .bbai-library-status-copy { margin: 0; font-size: 12px; color: #64748b; }
    .bbai-library-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .bbai-row-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        transition: all .15s ease;
    }
    .bbai-row-action-btn:hover:not(:disabled) { background: #f8fafc; border-color: #94a3b8; }
    .bbai-row-action-btn svg { flex: 0 0 auto; }
    .bbai-row-action-btn--primary { background: #0f172a; border-color: #0f172a; color: #ffffff; }
    .bbai-row-action-btn--primary:hover:not(:disabled) { background: #1e293b; border-color: #1e293b; }
    .bbai-row-action-btn--review { background: #dcfce7; border-color: #86efac; color: #166534; }
    .bbai-row-action-btn--ghost { background: #ffffff; }
    .bbai-row-action-btn.is-loading { opacity: .75; pointer-events: none; }
    .bbai-row-action-spinner {
        width: 12px;
        height: 12px;
        border-radius: 999px;
        border: 2px solid currentColor;
        border-right-color: transparent;
        animation: bbaiSpin .8s linear infinite;
    }
    .bbai-library-reviewing { display: inline-flex; align-items: center; gap: 8px; color: #0f172a; font-size: 13px; font-weight: 600; }
    .bbai-library-filter-empty__cell { padding: 28px !important; text-align: center; color: #64748b; }
    .bbai-library-preview-modal__dialog { border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 24px; }
    @keyframes bbaiSpin { to { transform: rotate(360deg); } }
    @media (max-width: 960px) {
        .bbai-library-summary-card__stats { grid-template-columns: 1fr; }
        .bbai-library-summary-card__head,
        .bbai-library-guidance,
        .bbai-library-selection-bar { flex-direction: column; align-items: stretch; }
        .bbai-library-bulk-banner { flex-direction: column; align-items: stretch; }
        .bbai-library-bulk-banner__actions { width: 100%; }
        .bbai-library-bulk-banner__cta { width: 100%; min-width: 0; }
        .bbai-library-selection-bar__actions { width: 100%; }
    }
    @media (max-width: 782px) {
        .bbai-library-table thead { display: none; }
        .bbai-library-table,
        .bbai-library-table tbody,
        .bbai-library-table tr,
        .bbai-library-table td { display: block; width: 100%; }
        .bbai-library-table tbody tr { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
        .bbai-library-cell--select { padding-bottom: 4px !important; }
        .bbai-library-cell--alt-text,
        .bbai-library-cell--status,
        .bbai-library-cell--asset { width: 100%; min-width: 0; }
        .bbai-library-hover-preview { display: none; }
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
    $bbai_requested_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
    $bbai_requested_filter_map = [
        'all'          => 'all',
        'missing'      => 'missing',
        'optimized'    => 'optimized',
        'weak'         => 'weak',
        'needs_review' => 'weak',
        'needs-review' => 'weak',
    ];
    $bbai_default_review_filter = 'all';
    if (isset($bbai_requested_filter_map[$bbai_requested_status])) {
        $bbai_default_review_filter = $bbai_requested_filter_map[$bbai_requested_status];
    } elseif ($bbai_cov_missing > 0) {
        $bbai_default_review_filter = 'missing';
    } elseif ($bbai_cov_needs_review > 0) {
        $bbai_default_review_filter = 'weak';
    }
    $bbai_bulk_issue_count = $bbai_cov_missing + $bbai_cov_needs_review;
    $bbai_show_bulk_optimization_banner = $bbai_bulk_issue_count > 0;
    $bbai_bulk_optimization_available = $bbai_is_pro;
    $bbai_bulk_estimated_seconds = max(0, $bbai_bulk_issue_count * 20);
    if ($bbai_bulk_estimated_seconds >= 60) {
        $bbai_bulk_estimated_minutes = (int) ceil($bbai_bulk_estimated_seconds / 60);
        $bbai_bulk_time_saved_label = sprintf(
            /* translators: %d: number of minutes */
            _n('~%d minute', '~%d minutes', $bbai_bulk_estimated_minutes, 'beepbeep-ai-alt-text-generator'),
            $bbai_bulk_estimated_minutes
        );
    } else {
        $bbai_bulk_time_saved_label = sprintf(
            /* translators: %d: number of seconds */
            _n('~%d second', '~%d seconds', $bbai_bulk_estimated_seconds, 'beepbeep-ai-alt-text-generator'),
            $bbai_bulk_estimated_seconds
        );
    }
    $bbai_has_review_issues = ($bbai_cov_missing + $bbai_cov_needs_review) > 0;
    $bbai_library_healthy = $bbai_cov_total > 0 && !$bbai_has_review_issues;

    $bbai_workspace_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-workspace.php';
    if (file_exists($bbai_workspace_partial)) {
        while (ob_get_level() > $bbai_library_template_buffer_level) {
            ob_end_clean();
        }

        include $bbai_workspace_partial;
        return;
    }
    ?>
    <div id="bbai-alt-coverage-card"
         class="bbai-library-summary-card"
         data-bbai-coverage-card
         data-bbai-free-plan-limit="<?php echo esc_attr((int) ($bbai_coverage['free_plan_limit'] ?? 50)); ?>">
        <div class="bbai-library-summary-card__head">
            <div>
                <p class="bbai-library-summary-card__eyebrow"><?php esc_html_e('ALT Optimization Progress', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 class="bbai-library-summary-card__title"><span data-bbai-library-progress-label><?php echo esc_html(sprintf(__('%s%% optimized', 'beepbeep-ai-alt-text-generator'), number_format_i18n($bbai_cov_opt_pct))); ?></span></h2>
                <p class="bbai-library-summary-card__copy"><?php esc_html_e('Move through the queue by fixing missing ALT first, then approving or improving weaker descriptions.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="rescan-media-library">
                <?php esc_html_e('Scan for new uploads', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
        </div>
        <div class="bbai-library-summary-card__stats">
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--optimized">
                <span class="bbai-library-summary-card__stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong class="bbai-library-summary-card__stat-value" data-bbai-library-optimized><?php echo esc_html(number_format_i18n($bbai_cov_optimized)); ?></strong>
            </div>
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--weak">
                <span class="bbai-library-summary-card__stat-label"><?php esc_html_e('Need improvement', 'beepbeep-ai-alt-text-generator'); ?></span>
                <strong class="bbai-library-summary-card__stat-value" data-bbai-library-weak><?php echo esc_html(number_format_i18n($bbai_cov_needs_review)); ?></strong>
            </div>
            <div class="bbai-library-summary-card__stat bbai-library-summary-card__stat--missing">
                <span class="bbai-library-summary-card__stat-label"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
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
            <strong data-bbai-coverage-score-inline><?php echo esc_html(sprintf(__('%s%% optimized', 'beepbeep-ai-alt-text-generator'), number_format_i18n($bbai_cov_opt_pct))); ?></strong>
        </div>
    </div>

    <?php if ($bbai_library_healthy) : ?>
        <div class="bbai-library-guidance" data-bbai-library-guidance data-state="healthy">
            <div>
                <p class="bbai-library-guidance__eyebrow"><?php esc_html_e('Healthy Library', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 class="bbai-library-guidance__title"><?php esc_html_e('Your media library is fully optimized', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-library-guidance__copy"><?php esc_html_e('All images have ALT text. New uploads will be scanned automatically.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-library-guidance__actions">
                <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-action="rescan-media-library"><?php esc_html_e('Scan for new uploads', 'beepbeep-ai-alt-text-generator'); ?></button>
            </div>
        </div>
    <?php elseif ($bbai_cov_missing > 0) : ?>
        <div class="bbai-library-guidance" data-bbai-library-guidance data-state="issues">
            <div>
                <p class="bbai-library-guidance__eyebrow"><?php esc_html_e('Next Task', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 class="bbai-library-guidance__title">
                    <?php
                    printf(
                        /* translators: %s: number of missing images */
                        esc_html__('%s images are missing ALT text', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format_i18n($bbai_cov_missing))
                    );
                    ?>
                </h2>
                <p class="bbai-library-guidance__copy"><?php esc_html_e('Generate ALT text to improve accessibility and SEO before reviewing weaker descriptions.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-library-guidance__actions">
                <button type="button"
                        class="bbai-btn bbai-btn-primary bbai-btn-sm <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                        data-action="generate-missing"
                        data-bbai-lock-preserve-label="1"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-bbai-action="open-upgrade"
                            data-bbai-locked-cta="1"
                            data-bbai-lock-reason="generate_missing"
                            data-bbai-locked-source="library-guidance"
                            aria-disabled="true"
                        <?php endif; ?>>
                    <?php esc_html_e('Generate ALT for missing images', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
            </div>
        </div>
    <?php elseif ($bbai_cov_needs_review > 0) : ?>
        <div class="bbai-library-guidance" data-bbai-library-guidance data-state="issues">
            <div>
                <p class="bbai-library-guidance__eyebrow"><?php esc_html_e('Next Task', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 class="bbai-library-guidance__title">
                    <?php
                    printf(
                        /* translators: %s: number of weak images */
                        esc_html__('%s images need a stronger ALT description', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format_i18n($bbai_cov_needs_review))
                    );
                    ?>
                </h2>
                <p class="bbai-library-guidance__copy"><?php esc_html_e('Approve copy you are happy with, or regenerate weaker ALT text to move through the review queue quickly.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <div class="bbai-library-guidance__actions">
                <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-bbai-filter-target="weak"><?php esc_html_e('Review weak ALT', 'beepbeep-ai-alt-text-generator'); ?></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($bbai_show_bulk_optimization_banner) : ?>
        <div class="bbai-library-bulk-banner"
             data-bbai-bulk-optimization-banner
             data-bbai-issue-count="<?php echo esc_attr($bbai_bulk_issue_count); ?>"
             data-bbai-missing-count="<?php echo esc_attr($bbai_cov_missing); ?>"
             data-bbai-weak-count="<?php echo esc_attr($bbai_cov_needs_review); ?>"
             data-bbai-growth-enabled="<?php echo esc_attr($bbai_bulk_optimization_available ? 'true' : 'false'); ?>">
            <div class="bbai-library-bulk-banner__main">
                <div class="bbai-library-bulk-banner__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 2.5L18 17.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M10 7.2V11.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        <circle cx="10" cy="14.3" r="1" fill="currentColor"/>
                    </svg>
                </div>
                <div>
                    <p class="bbai-library-bulk-banner__eyebrow"><?php esc_html_e('Accessibility improvements available', 'beepbeep-ai-alt-text-generator'); ?></p>
                    <h2 class="bbai-library-bulk-banner__title"><?php esc_html_e('Fix all ALT issues in one pass', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p class="bbai-library-bulk-banner__copy" data-bbai-bulk-optimization-count>
                        <?php
                        printf(
                            /* translators: %s: number of images */
                            esc_html(_n('%s image could be optimized automatically', '%s images could be optimized automatically', $bbai_bulk_issue_count, 'beepbeep-ai-alt-text-generator')),
                            esc_html(number_format_i18n($bbai_bulk_issue_count))
                        );
                        ?>
                    </p>
                    <p class="bbai-library-bulk-banner__meta" data-bbai-bulk-optimization-time>
                        <?php
                        printf(
                            /* translators: %s: estimated time saved */
                            esc_html__('Estimated time saved: %s', 'beepbeep-ai-alt-text-generator'),
                            esc_html($bbai_bulk_time_saved_label)
                        );
                        ?>
                    </p>
                </div>
            </div>
            <div class="bbai-library-bulk-banner__actions">
                <button type="button"
                        class="bbai-btn bbai-btn-primary bbai-library-bulk-banner__cta"
                        data-action="fix-all-images-automatically"
                        data-bbai-issue-count="<?php echo esc_attr($bbai_bulk_issue_count); ?>"
                        <?php if (!$bbai_bulk_optimization_available) : ?>
                            data-bbai-action="open-upgrade"
                            data-bbai-direct-upgrade-modal="1"
                            data-bbai-lock-reason="reoptimize_all"
                            data-bbai-locked-source="library-bulk-optimization-banner"
                        <?php endif; ?>>
                    <?php esc_html_e('Automatically optimize every new image you upload', 'beepbeep-ai-alt-text-generator'); ?>
                </button>
                <?php if (!$bbai_bulk_optimization_available) : ?>
                    <p class="bbai-library-bulk-banner__plan-note"><?php esc_html_e('Never worry about missing ALT text again.', 'beepbeep-ai-alt-text-generator'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="bbai-review-filter-tabs" class="bbai-alt-review-filters" data-bbai-default-filter="<?php echo esc_attr($bbai_default_review_filter); ?>">
        <button type="button"
                class="bbai-alt-review-filters__btn <?php echo $bbai_default_review_filter === 'missing' ? 'bbai-alt-review-filters__btn--active' : ''; ?> <?php echo $bbai_cov_missing > 0 ? 'bbai-alt-review-filters__btn--problem' : ''; ?>"
                data-filter="missing"
                data-bbai-filter-label="<?php esc_attr_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?>">
            <?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_missing)); ?>)
        </button>
        <button type="button"
                class="bbai-alt-review-filters__btn <?php echo $bbai_default_review_filter === 'weak' ? 'bbai-alt-review-filters__btn--active' : ''; ?> <?php echo $bbai_cov_needs_review > 0 ? 'bbai-alt-review-filters__btn--problem' : ''; ?>"
                data-filter="weak"
                data-bbai-filter-label="<?php esc_attr_e('Weak ALT', 'beepbeep-ai-alt-text-generator'); ?>">
            <?php esc_html_e('Weak ALT', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_needs_review)); ?>)
        </button>
        <button type="button"
                class="bbai-alt-review-filters__btn <?php echo $bbai_default_review_filter === 'all' ? 'bbai-alt-review-filters__btn--active' : ''; ?>"
                data-filter="all"
                data-bbai-filter-label="<?php esc_attr_e('All', 'beepbeep-ai-alt-text-generator'); ?>">
            <?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_total)); ?>)
        </button>
        <button type="button"
                class="bbai-alt-review-filters__btn <?php echo $bbai_default_review_filter === 'optimized' ? 'bbai-alt-review-filters__btn--active' : ''; ?>"
                data-filter="optimized"
                data-bbai-filter-label="<?php esc_attr_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?>">
            <?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?> (<?php echo esc_html(number_format_i18n($bbai_cov_optimized)); ?>)
        </button>
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

    <!-- Toolbar: search + filters -->
    <div class="bbai-toolbar bbai-library-toolbar" id="bbai-library-toolbar" aria-live="polite">
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
                    <?php esc_html_e('Generate ALT', 'beepbeep-ai-alt-text-generator'); ?>
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
                            $bbai_status_label = __('Missing ALT', 'beepbeep-ai-alt-text-generator');
                            $bbai_quality_class = 'poor';
                            $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
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

                                if ($bbai_is_user_approved || in_array($bbai_quality_class, ['excellent', 'good'], true)) {
                                    $status = 'optimized';
                                    $bbai_status_label = __('Optimized', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    $status = 'weak';
                                    $bbai_status_label = __('Weak ALT', 'beepbeep-ai-alt-text-generator');
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
                            if ($bbai_has_alt) {
                                if ($bbai_quality_class === 'excellent') {
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
                                data-quality-tooltip="<?php echo esc_attr($bbai_quality_tooltip); ?>">
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
                                            <button type="button"
                                                    class="bbai-row-action-btn"
                                                    data-action="edit-alt-inline"
                                                    data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                <?php esc_html_e('Edit', 'beepbeep-ai-alt-text-generator'); ?>
                                            </button>
                                            <button type="button"
                                                    class="bbai-row-action-btn bbai-row-action-btn--primary <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                                                    data-action="regenerate-single"
                                                    data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                    data-bbai-lock-preserve-label="1"
                                                    title="<?php echo esc_attr($bbai_has_alt ? __('Regenerate ALT text with AI', 'beepbeep-ai-alt-text-generator') : __('Generate ALT text with AI', 'beepbeep-ai-alt-text-generator')); ?>"
                                                    <?php if ($bbai_limit_reached_state) : ?>
                                                        data-bbai-action="open-upgrade"
                                                        data-bbai-locked-cta="1"
                                                        data-bbai-lock-reason="regenerate_single"
                                                        data-bbai-locked-source="library-row-regenerate"
                                                        aria-disabled="true"
                                                    <?php endif; ?>>
                                                <?php echo esc_html($bbai_has_alt ? __('Regenerate', 'beepbeep-ai-alt-text-generator') : __('Generate ALT', 'beepbeep-ai-alt-text-generator')); ?>
                                            </button>
                                            <?php if ($bbai_has_alt && $status === 'weak') : ?>
                                                <button type="button"
                                                        class="bbai-row-action-btn bbai-row-action-btn--review"
                                                        data-action="approve-alt-inline"
                                                        data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                                    <?php esc_html_e('Approve', 'beepbeep-ai-alt-text-generator'); ?>
                                                </button>
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
        $bbai_metric_cards_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/metric-cards.php';
        if (file_exists($bbai_metric_cards_partial)) {
            include $bbai_metric_cards_partial;
        }
        ?>
    <?php endif; ?>

</div>
