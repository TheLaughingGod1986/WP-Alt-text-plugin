<?php
/**
 * ALT Library tab content partial.
 * Modernized to match BeepBeep AI SaaS style with unified.css components
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\BBAI_Cache;

/**
 * Calculate alt text quality score (0-100)
 *
 * @param string $alt_text Alt text to analyze
 * @return int Quality score 0-100
 */
function bbai_calculate_alt_quality_score($alt_text) {
    if (empty($alt_text) || !is_string($alt_text)) {
        return 0;
    }

    $score = 0;
    $bbai_word_count = str_word_count($alt_text);
    $char_count = strlen($alt_text);

    // Length score (optimal: 5-15 words, 50-150 chars)
    if ($bbai_word_count >= 5 && $bbai_word_count <= 15) {
        $score += 30; // Perfect length
    } elseif ($bbai_word_count >= 3 && $bbai_word_count <= 20) {
        $score += 20; // Acceptable length
    } elseif ($bbai_word_count > 0) {
        $score += 10; // Too short or too long
    }

    // Character length score
    if ($char_count >= 50 && $char_count <= 150) {
        $score += 20;
    } elseif ($char_count >= 30 && $char_count <= 200) {
        $score += 15;
    } elseif ($char_count > 0) {
        $score += 5;
    }

    // Descriptiveness (check for common descriptive words)
    $descriptive_words = ['showing', 'depicting', 'displaying', 'featuring', 'containing', 'with', 'of', 'in', 'on', 'at'];
    $lower_alt = strtolower($alt_text);
    $descriptive_count = 0;
    foreach ($descriptive_words as $word) {
        if (strpos($lower_alt, $word) !== false) {
            $descriptive_count++;
        }
    }
    $score += min(20, $descriptive_count * 5);

    // No generic words penalty
    $generic_words = ['image', 'picture', 'photo', 'graphic'];
    $has_generic = false;
    foreach ($generic_words as $word) {
        if (stripos($alt_text, $word) !== false && $bbai_word_count < 5) {
            $has_generic = true;
            break;
        }
    }
    if (!$has_generic) {
        $score += 15;
    }

    // Specificity bonus (check for numbers, proper nouns, specific terms)
    if (preg_match('/\d+/', $alt_text)) {
        $score += 5; // Contains numbers
    }
    if (preg_match('/[A-Z][a-z]+/', $alt_text)) {
        $score += 5; // Contains proper nouns
    }

    // Penalty for very short or very long
    if ($bbai_word_count < 3) {
        $score -= 20;
    }
    if ($bbai_word_count > 25) {
        $score -= 10;
    }

    return max(0, min(100, $score));
}

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
        'SELECT p.ID, p.post_title, p.post_date, p.post_modified, MAX(COALESCE(pm.meta_value, %s)) as alt_text, MAX(CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> %s THEN 1 ELSE 0 END) as has_alt FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id AND pm.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s GROUP BY p.ID, p.post_title, p.post_date, p.post_modified ORDER BY p.post_date DESC LIMIT %d OFFSET %d',
        '',
        '',
        '_wp_attachment_image_alt',
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
?>

<div class="bbai-library-container">
    <!-- Header -->
    <div class="bbai-page-header bbai-mb-6">
        <div class="bbai-page-header-content">
            <h1 class="bbai-heading-1"><?php esc_html_e('Image ALT Text Library', 'beepbeep-ai-alt-text-generator'); ?></h1>
            <p class="bbai-subtitle"><?php esc_html_e('Search, review, and regenerate AI alt text for images on this site.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="bbai-library-filters bbai-mb-6">
        <div class="bbai-library-search-wrapper">
            <svg class="bbai-library-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <input type="text"
                   id="bbai-library-search"
                   class="bbai-library-search-input"
                   placeholder="<?php esc_attr_e('Search images or alt text', 'beepbeep-ai-alt-text-generator'); ?>"
            />
        </div>
        <div class="bbai-library-filters-group">
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-status-filter-btn">
                <?php esc_html_e('Status: All', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
            <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" id="bbai-date-filter-btn">
                <?php esc_html_e('Date Range', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
            <a href="#" class="bbai-link-btn bbai-link-sm" id="bbai-clear-filters">
                × <?php esc_html_e('Clear filters', 'beepbeep-ai-alt-text-generator'); ?>
            </a>
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
    </div>

    <!-- Stats Row (only when data exists) -->
    <?php if ($bbai_total_images > 0) : ?>
        <div class="bbai-library-stats bbai-mb-6">
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($bbai_with_alt_count)); ?></div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($bbai_missing_count)); ?></div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($bbai_optimized_percent)); ?>%</div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Complete', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </div>
        
        <!-- Bulk Actions Row -->
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
        ?>
        <div class="bbai-card bbai-library-actions-card bbai-mb-5">
            <?php if ($bbai_limit_reached_state) : ?>
                <p class="bbai-optimization-actions-helper">
                    <?php esc_html_e('Upgrade required. You can still review existing alt text below.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            <?php endif; ?>
            <div class="bbai-optimization-actions bbai-flex bbai-gap-3 bbai-flex-wrap bbai-items-center">
                <div class="bbai-flex bbai-gap-3 bbai-flex-wrap bbai-items-center">
                    <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked bbai-optimization-cta--locked' : ''); ?> <?php echo esc_attr(($bbai_missing_count === 0 && ! $bbai_limit_reached_state) ? 'bbai-optimization-cta--disabled' : ''); ?>"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-action="generate-missing"
                            data-bbai-lock-control="1"
                            aria-disabled="true"
                            disabled
                            tabindex="-1"
                            title="<?php esc_attr_e('Monthly credits used up. Upgrade to continue.', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php elseif (!$bbai_generate_missing_disabled) : ?>
                            data-action="generate-missing"
                        <?php endif; ?>
                        <?php if (!$bbai_limit_reached_state && $bbai_generate_missing_disabled) : ?>
                            disabled
                            data-bbai-lock-control="1"
                            aria-disabled="true"
                            tabindex="-1"
                            <?php if ($bbai_missing_count === 0) : ?>
                                title="<?php esc_attr_e('All images already have alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                            <?php else : ?>
                                title="<?php esc_attr_e('Out of credits this month. Upgrade now or wait for reset.', 'beepbeep-ai-alt-text-generator'); ?>"
                            <?php endif; ?>
                        <?php else : ?>
                            data-bbai-tooltip="<?php esc_attr_e('Automatically generate alt text for all images that don\'t have any. Processes in the background without slowing down your site.', 'beepbeep-ai-alt-text-generator'); ?>"
                            data-bbai-tooltip-position="bottom"
                        <?php endif; ?>
                    >
                        <?php if ($bbai_limit_reached_state) : ?>
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php elseif ($bbai_generate_missing_disabled) : ?>
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php else : ?>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span><?php esc_html_e('Generate Missing Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked bbai-optimization-cta--locked' : ''); ?>"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-action="regenerate-all"
                            data-bbai-lock-control="1"
                            aria-disabled="true"
                            disabled
                            tabindex="-1"
                            title="<?php esc_attr_e('Monthly credits used up. Upgrade to continue.', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php elseif (!$bbai_reoptimize_disabled) : ?>
                            data-action="regenerate-all"
                            data-bbai-tooltip="<?php esc_attr_e('Regenerate alt text for ALL images, even those that already have it. Useful after changing your tone/style settings or brand guidelines.', 'beepbeep-ai-alt-text-generator'); ?>"
                            data-bbai-tooltip-position="bottom"
                        <?php else : ?>
                            disabled
                            data-bbai-lock-control="1"
                            aria-disabled="true"
                            tabindex="-1"
                            title="<?php esc_attr_e('Out of credits this month. Upgrade now or wait for reset.', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php endif; ?>
                    >
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                            <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                        </svg>
                        <span>
                            <?php
                            esc_html_e('Re-optimise All Alt Text', 'beepbeep-ai-alt-text-generator');
                            ?>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($bbai_all_images)) : ?>
        <div class="bbai-card bbai-mb-6">
            <div class="bbai-library-batch-actions bbai-mb-4" id="bbai-batch-actions" aria-live="polite">
                <div class="bbai-flex bbai-gap-3 bbai-items-center bbai-flex-wrap bbai-library-batch-actions__inner">
                    <span class="bbai-library-batch-actions__label" id="bbai-selected-count">0 <?php esc_html_e('selected', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <button type="button" class="bbai-btn-secondary bbai-btn-sm <?php echo esc_attr($bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>" id="bbai-batch-regenerate" data-action="regenerate-selected"
                        <?php if ($bbai_limit_reached_state) : ?>
                            data-bbai-action="open-upgrade"
                            data-bbai-locked-cta="1"
                            data-bbai-lock-reason="reoptimize_all"
                            data-bbai-locked-source="library-batch-regenerate"
                            aria-disabled="true"
                            title="<?php esc_attr_e('Upgrade to continue bulk regeneration', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php endif; ?>
                    >
                        <?php esc_html_e('Regenerate Selected', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </div>
            <div class="bbai-table-wrap">
                <table class="bbai-table bbai-library-table">
                    <thead>
                        <tr>
                            <th class="bbai-library-col-select" style="width: 40px;">
                                <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
                            </th>
                            <th class="bbai-library-col-image"><?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-file"><?php esc_html_e('File Name', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-alt"><?php esc_html_e('AI Alt Text', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-status"><?php esc_html_e('Status', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-library-col-updated"><?php esc_html_e('Last Updated', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-text-right bbai-library-actions-header"><?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?></th>
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
                                $bbai_quality_score = bbai_calculate_alt_quality_score($bbai_clean_alt);
                                $bbai_quality_class = 'good';
                                $bbai_quality_label = __('Good', 'beepbeep-ai-alt-text-generator');
                                if ($bbai_quality_score < 60) {
                                    $bbai_quality_class = 'poor';
                                    $bbai_quality_label = __('Poor', 'beepbeep-ai-alt-text-generator');
                                } elseif ($bbai_quality_score < 80) {
                                    $bbai_quality_class = 'fair';
                                    $bbai_quality_label = __('Fair', 'beepbeep-ai-alt-text-generator');
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
                            ?>
                            <tr class="bbai-library-row"
                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                data-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                data-status="<?php echo esc_attr($status); ?>"
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
                                <td class="bbai-library-cell--file">
                                    <div class="bbai-library-filename-cell">
                                        <span class="bbai-library-filename" title="<?php echo esc_attr($bbai_filename); ?>"><?php echo esc_html($bbai_display_filename); ?></span>
                                        <?php if (!empty($bbai_file_meta)) : ?>
                                            <span class="bbai-library-filename-meta">
                                                <?php echo esc_html($bbai_file_meta); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--alt-text">
                                    <div class="bbai-alt-text-content">
                                        <?php if ($bbai_has_alt) : ?>
                                            <div class="bbai-alt-text-preview" title="<?php echo esc_attr($bbai_clean_alt); ?>"><?php echo esc_html($bbai_alt_preview); ?></div>
                                            <div class="bbai-quality-insights">
                                                <span class="bbai-quality-badge bbai-quality-badge--<?php echo esc_attr($bbai_quality_class); ?>" data-bbai-tooltip="<?php
                                                printf(
                                                    /* translators: 1: quality score, 2: word count */
                                                    esc_attr__('Quality Score: %1$s/100. Length: %2$s words (optimal: 5-15 words).', 'beepbeep-ai-alt-text-generator'),
                                                    esc_attr(number_format_i18n($bbai_quality_score)),
                                                    esc_attr(number_format_i18n($bbai_word_count))
                                                );
                                                ?>" data-bbai-tooltip-position="top">
                                                    <?php echo esc_html($bbai_quality_label); ?>
                                                </span>
                                                <?php if ($bbai_word_count < 5 || $bbai_word_count > 15) : ?>
                                                    <span class="bbai-quality-hint" data-bbai-tooltip="<?php esc_attr_e('Optimal length is 5-15 words for SEO and accessibility.', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                                            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                            <path d="M8 5V5.01M8 11H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        </svg>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="bbai-alt-text-missing"><?php esc_html_e('No alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="bbai-library-cell--status">
                                    <span class="bbai-status-badge bbai-status-badge--<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($bbai_status_label); ?>
                                    </span>
                                </td>
                                <td class="bbai-library-cell--updated">
                                    <span class="bbai-text-muted bbai-text-sm"><?php echo esc_html($bbai_modified_date); ?></span>
                                </td>
                                <td class="bbai-text-right bbai-library-cell--actions">
                                    <div class="bbai-library-actions">
                                        <button type="button"
                                                class="bbai-link-sm"
                                                data-action="preview-image"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                            <?php esc_html_e('View', 'beepbeep-ai-alt-text-generator'); ?>
                                        </button>
                                        <button type="button"
                                                class="bbai-link-sm"
                                                data-action="edit-alt-inline"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>">
                                            <?php esc_html_e('Edit', 'beepbeep-ai-alt-text-generator'); ?>
                                        </button>
                                        <button type="button"
                                                class="bbai-link-sm<?php echo ! $bbai_can_generate ? ' bbai-link-sm--disabled bbai-is-locked' : ''; ?>"
                                                data-action="regenerate-single"
                                                data-attachment-id="<?php echo esc_attr($bbai_attachment_id); ?>"
                                                <?php if ( ! $bbai_can_generate ) : ?>
                                                    data-bbai-action="open-upgrade"
                                                    data-bbai-locked-cta="1"
                                                    data-bbai-lock-reason="regenerate_single"
                                                    data-bbai-locked-source="library-row-regenerate"
                                                    aria-disabled="true"
                                                    title="<?php esc_attr_e( 'Monthly quota reached — upgrade to continue generating.', 'beepbeep-ai-alt-text-generator' ); ?>"
                                                <?php endif; ?>>
                                            <?php esc_html_e('Regen', 'beepbeep-ai-alt-text-generator'); ?>
                                            <?php if ( ! $bbai_can_generate ) : ?>
                                                <span class="bbai-library-action-lock" aria-hidden="true">[Locked]</span>
                                            <?php endif; ?>
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

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bbai_bottom_upsell_partial)) {
        include $bbai_bottom_upsell_partial;
    }
    ?>
</div>
