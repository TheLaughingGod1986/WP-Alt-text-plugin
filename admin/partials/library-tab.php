<?php
/**
 * ALT Library tab content partial.
 * Modernized to match BeepBeep AI SaaS style with unified.css components
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    $word_count = str_word_count($alt_text);
    $char_count = strlen($alt_text);

    // Length score (optimal: 5-15 words, 50-150 chars)
    if ($word_count >= 5 && $word_count <= 15) {
        $score += 30; // Perfect length
    } elseif ($word_count >= 3 && $word_count <= 20) {
        $score += 20; // Acceptable length
    } elseif ($word_count > 0) {
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
        if (stripos($alt_text, $word) !== false && $word_count < 5) {
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
    if ($word_count < 3) {
        $score -= 20;
    }
    if ($word_count > 25) {
        $score -= 10;
    }

    return max(0, min(100, $score));
}

// Pagination setup
$per_page = 10;
$alt_page_raw = isset($_GET['alt_page']) ? wp_unslash($_GET['alt_page']) : '';
$current_page = max(1, absint($alt_page_raw));
$offset = ($current_page - 1) * $per_page;

global $wpdb;

$image_mime_like = $wpdb->esc_like('image/') . '%';

// Get total count of all images
$total_images = (int) $wpdb->get_var($wpdb->prepare(
    'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
    'attachment', 'inherit', $image_mime_like
));

// Get images with alt text count
$with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
    'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s AND pm.meta_key = %s AND TRIM(pm.meta_value) <> %s',
    'attachment', $image_mime_like, 'inherit', '_wp_attachment_image_alt', ''
));

$missing_count = $total_images - $with_alt_count;

// Get all images with their alt text
$all_images = $wpdb->get_results($wpdb->prepare(
    'SELECT p.ID, p.post_title, p.post_date, p.post_modified, MAX(COALESCE(pm.meta_value, %s)) as alt_text, MAX(CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> %s THEN 1 ELSE 0 END) as has_alt FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' pm ON p.ID = pm.post_id AND pm.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s GROUP BY p.ID, p.post_title, p.post_date, p.post_modified ORDER BY p.post_date DESC LIMIT %d OFFSET %d',
    '',
    '',
    '_wp_attachment_image_alt',
    'attachment',
    $image_mime_like,
    'inherit',
    $per_page,
    $offset
));

$total_count = $total_images;
$total_pages = ceil($total_count / $per_page);

// Get plan info
$has_license = $this->api_client->has_active_license();
$license_data = $this->api_client->get_license_data();
$plan_slug = 'free';

// Try to get plan from usage_stats if available
if (isset($usage_stats) && is_array($usage_stats) && !empty($usage_stats['plan'])) {
    $plan_slug = strtolower($usage_stats['plan']);
}

if ($has_license && $license_data && isset($license_data['organization']) && is_array($license_data['organization'])) {
    $license_plan = !empty($license_data['organization']['plan']) ? strtolower($license_data['organization']['plan']) : 'free';
    if ($license_plan !== 'free') {
        $plan_slug = $license_plan;
    }
}

// Map 'pro' to 'growth' for consistency
if ($plan_slug === 'pro') {
    $plan_slug = 'growth';
}

$is_free = ($plan_slug === 'free');
$is_growth = ($plan_slug === 'growth');
$is_agency = ($plan_slug === 'agency');
$is_pro = ($is_growth || $is_agency);

// Calculate stats percentages
$optimized_percent = $total_images > 0 ? round(($with_alt_count / $total_images) * 100) : 0;
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
    <?php if ($total_images > 0) : ?>
        <div class="bbai-library-stats bbai-mb-6">
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($with_alt_count)); ?></div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($missing_count)); ?></div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
            <div class="bbai-library-stat-card">
                <div class="bbai-library-stat-value"><?php echo esc_html(number_format_i18n($optimized_percent)); ?>%</div>
                <div class="bbai-library-stat-label"><?php esc_html_e('Complete', 'beepbeep-ai-alt-text-generator'); ?></div>
            </div>
        </div>
        
        <!-- Bulk Actions Row -->
        <?php
        // Check if user can generate (same logic as dashboard)
        $plan = isset($usage_stats) && is_array($usage_stats) && !empty($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
        $has_quota = isset($usage_stats) && is_array($usage_stats) && !empty($usage_stats['remaining']) ? ($usage_stats['remaining'] > 0) : false;
        $is_premium = in_array($plan, ['pro', 'growth', 'agency'], true);
        $can_generate = $has_quota || $is_premium;
        ?>
        <div class="bbai-card bbai-mb-6">
            <div class="bbai-optimization-actions bbai-flex bbai-gap-3 bbai-flex-wrap bbai-items-center bbai-justify-between">
                <div class="bbai-flex bbai-gap-3 bbai-flex-wrap">
                <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?> <?php echo esc_attr(($missing_count === 0) ? 'bbai-optimization-cta--disabled' : ''); ?>" data-action="generate-missing"
                    <?php if (!$can_generate || $missing_count === 0) : ?>
                        disabled
                        <?php if ($missing_count === 0) : ?>
                            title="<?php esc_attr_e('All images already have alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php else : ?>
                            title="<?php esc_attr_e('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator'); ?>"
                        <?php endif; ?>
                    <?php else : ?>
                        data-bbai-tooltip="<?php esc_attr_e('Automatically generate alt text for all images that don\'t have any. Processes in the background without slowing down your site.', 'beepbeep-ai-alt-text-generator'); ?>"
                        data-bbai-tooltip-position="bottom"
                    <?php endif; ?>
                >
                    <?php if (!$can_generate) : ?>
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
                <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary bbai-cta-glow-blue <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="regenerate-all"
                    <?php if (!$can_generate) : ?>
                        disabled
                        title="<?php esc_attr_e('Unlock 1,000 alt text generations with Growth →', 'beepbeep-ai-alt-text-generator'); ?>"
                    <?php else : ?>
                        data-bbai-tooltip="<?php esc_attr_e('Regenerate alt text for ALL images, even those that already have it. Useful after changing your tone/style settings or brand guidelines.', 'beepbeep-ai-alt-text-generator'); ?>"
                        data-bbai-tooltip-position="bottom"
                    <?php endif; ?>
                >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                        <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                    </svg>
                    <span><?php esc_html_e('Re-optimise All Alt Text', 'beepbeep-ai-alt-text-generator'); ?></span>
                </button>
                </div>
                <div class="bbai-flex bbai-gap-2 bbai-flex-wrap">
                    <div class="bbai-dropdown" style="position: relative;">
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="export-alt-text" data-format="csv" data-bbai-tooltip="<?php esc_attr_e('Export alt text data', 'beepbeep-ai-alt-text-generator'); ?>" data-bbai-tooltip-position="top">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                <path d="M14 10V12.6667C14 13.403 13.403 14 12.6667 14H3.33333C2.59695 14 2 13.403 2 12.6667V10M11.3333 5.33333L8 2M8 2L4.66667 5.33333M8 2V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Export', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                        <div class="bbai-dropdown-menu bbai-hidden" style="position: absolute; top: 100%; right: 0; margin-top: var(--bbai-space-2); background: var(--bbai-bg); border: 1px solid var(--bbai-border); border-radius: var(--bbai-radius); box-shadow: var(--bbai-shadow-lg); z-index: 1000; min-width: 150px;">
                            <button type="button" class="bbai-dropdown-item" data-action="export-alt-text" data-format="csv"><?php esc_html_e('Export as CSV', 'beepbeep-ai-alt-text-generator'); ?></button>
                            <button type="button" class="bbai-dropdown-item" data-action="export-alt-text" data-format="json"><?php esc_html_e('Export as JSON', 'beepbeep-ai-alt-text-generator'); ?></button>
                            <button type="button" class="bbai-dropdown-item" data-action="export-alt-text" data-format="txt"><?php esc_html_e('Export as TXT', 'beepbeep-ai-alt-text-generator'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($all_images)) : ?>
        <div class="bbai-card bbai-mb-6">
            <div class="bbai-table-wrap">
                <table class="bbai-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="bbai-select-all" class="bbai-checkbox" aria-label="<?php esc_attr_e('Select all images', 'beepbeep-ai-alt-text-generator'); ?>" />
                            </th>
                            <th><?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('File Name', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('AI Alt Text', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Status', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'beepbeep-ai-alt-text-generator'); ?></th>
                            <th class="bbai-text-right"><?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="bbai-library-table-body">
                        <?php foreach ($all_images as $image) : ?>
                            <?php
                            $attachment_id = $image->ID;
                            $current_alt = $image->alt_text ?? '';
                            $clean_alt = is_string($current_alt) ? trim($current_alt) : '';
                            $has_alt = !empty($clean_alt);

                            $thumb_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                            $attached_file = get_attached_file($attachment_id);
                            $filename = $attached_file ? basename($attached_file) : '';
                            $edit_link = get_edit_post_link($attachment_id, '');

                            // Format date
                            $modified_date = date_i18n('j M Y', strtotime($image->post_modified));

                            // Truncate alt text for preview (2 lines)
                            $display_alt = $clean_alt;
                            $alt_preview = $clean_alt ?: '';
                            if (!empty($alt_preview) && is_string($alt_preview) && strlen($alt_preview) > 120) {
                                $alt_preview = substr($alt_preview, 0, 117) . '...';
                            }

                            // Truncate filename for display
                            $display_filename = !empty($filename) && is_string($filename) ? $filename : __('Unknown file', 'beepbeep-ai-alt-text-generator');
                            if (is_string($display_filename) && strlen($display_filename) > 35) {
                                $display_filename = substr($display_filename, 0, 32) . '...';
                            }
                            
                            // Get file extension and size safely
                            $file_extension = '';
                            $file_size = '';
                            if ($attached_file && is_string($attached_file) && file_exists($attached_file)) {
                                if (!empty($filename) && is_string($filename)) {
                                    $path_info = pathinfo($filename);
                                    $file_extension = !empty($path_info['extension']) && is_string($path_info['extension']) ? strtoupper($path_info['extension']) : '';
                                }
                                $file_size_bytes = filesize($attached_file);
                                if ($file_size_bytes !== false && is_numeric($file_size_bytes)) {
                                    $file_size = size_format($file_size_bytes);
                                }
                            }

                            // Determine status
                            $status = 'missing';
                            $status_label = __('Missing', 'beepbeep-ai-alt-text-generator');
                            if ($has_alt) {
                                $status = 'optimized';
                                $status_label = __('Optimized', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                            <tr class="bbai-library-row" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-id="<?php echo esc_attr($attachment_id); ?>" data-status="<?php echo esc_attr($status); ?>">
                                <td>
                                    <input type="checkbox" class="bbai-checkbox bbai-library-row-check bbai-image-checkbox" value="<?php echo esc_attr($attachment_id); ?>" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" aria-label="<?php
                                    /* translators: 1: image title */
                                    printf(esc_attr__('Select image %s', 'beepbeep-ai-alt-text-generator'), esc_attr($image->post_title));
                                    ?>" />
                                </td>
                                <td>
                                    <?php if ($thumb_url) : ?>
                                        <img src="<?php echo esc_url($thumb_url[0]); ?>" alt="" class="bbai-library-thumbnail" loading="lazy" decoding="async" />
                                    <?php else : ?>
                                        <div class="bbai-library-thumbnail-placeholder">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M2 6L10 1L18 6V16C18 16.5304 17.7893 17.0391 17.4142 17.4142C17.0391 17.7893 16.5304 18 16 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M7 18V10H13V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="bbai-library-filename-cell">
                                        <span class="bbai-library-filename" title="<?php echo esc_attr($filename); ?>"><?php echo esc_html($display_filename); ?></span>
                                        <?php if ($file_extension || $file_size) : ?>
                                            <span class="bbai-library-filename-meta">
                                                <?php if ($file_extension) : ?>
                                                    <?php echo esc_html($file_extension); ?>
                                                <?php endif; ?>
                                                <?php if ($file_extension && $file_size) : ?>
                                                    • 
                                                <?php endif; ?>
                                                <?php if ($file_size) : ?>
                                                    <?php echo esc_html($file_size); ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="bbai-alt-text-content">
                                        <?php if ($has_alt) : ?>
                                            <div class="bbai-alt-text-preview" title="<?php echo esc_attr($clean_alt); ?>"><?php echo esc_html($alt_preview); ?></div>
                                            <?php
                                            // Quality insights
                                            $alt_length = strlen($clean_alt);
                                            $word_count = str_word_count($clean_alt);
                                            $quality_score = bbai_calculate_alt_quality_score($clean_alt);
                                            $quality_class = 'good';
                                            $quality_label = __('Good', 'beepbeep-ai-alt-text-generator');
                                            if ($quality_score < 60) {
                                                $quality_class = 'poor';
                                                $quality_label = __('Needs improvement', 'beepbeep-ai-alt-text-generator');
                                            } elseif ($quality_score < 80) {
                                                $quality_class = 'fair';
                                                $quality_label = __('Fair', 'beepbeep-ai-alt-text-generator');
                                            }
                                            ?>
                                            <div class="bbai-quality-insights">
                                                <span class="bbai-quality-badge bbai-quality-badge--<?php echo esc_attr($quality_class); ?>" data-bbai-tooltip="<?php
                                                printf(
                                                    /* translators: 1: quality score, 2: word count */
                                                    esc_attr__('Quality Score: %1$s/100. Length: %2$s words (optimal: 5-15 words).', 'beepbeep-ai-alt-text-generator'),
                                                    esc_attr(number_format_i18n($quality_score)),
                                                    esc_attr(number_format_i18n($word_count))
                                                );
                                                ?>" data-bbai-tooltip-position="top">
                                                    <?php echo esc_html($quality_label); ?>
                                                </span>
                                                <?php if ($word_count < 5 || $word_count > 15) : ?>
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
                                <td>
                                    <span class="bbai-status-badge bbai-status-badge--<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="bbai-text-muted bbai-text-sm"><?php echo esc_html($modified_date); ?></span>
                                </td>
                                <td class="bbai-text-right">
                                    <div class="bbai-library-actions">
                                        <a href="<?php echo esc_url($edit_link); ?>" class="bbai-link-sm"><?php esc_html_e('View', 'beepbeep-ai-alt-text-generator'); ?></a>
                                        <button type="button"
                                                class="bbai-link-sm"
                                                data-action="regenerate-single"
                                                data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
                                            <?php esc_html_e('Regen', 'beepbeep-ai-alt-text-generator'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Batch Actions Footer (appears when items selected) -->
        <div class="bbai-library-batch-actions bbai-hidden bbai-mb-6" id="bbai-batch-actions">
            <div class="bbai-card">
                <div class="bbai-flex bbai-gap-4 bbai-items-center bbai-justify-between bbai-flex-wrap">
                    <div class="bbai-flex bbai-gap-2 bbai-items-center">
                        <span class="bbai-text-sm bbai-text-muted" id="bbai-selected-count">0 <?php esc_html_e('selected', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <div class="bbai-flex bbai-gap-2 bbai-flex-wrap">
                        <button type="button" class="bbai-btn-secondary bbai-btn-sm" id="bbai-batch-regenerate"
                            <?php if ($is_free) : ?>
                                disabled
                                title="<?php esc_attr_e('Upgrade to Growth to enable bulk regeneration', 'beepbeep-ai-alt-text-generator'); ?>"
                            <?php endif; ?>
                        >
                            <?php esc_html_e('Regenerate Selected', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                        <button type="button" class="bbai-btn-ghost bbai-btn-sm" id="bbai-clear-selection">
                            <?php esc_html_e('Clear Selection', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1) : ?>
            <div class="bbai-pagination">
                <span class="bbai-pagination-info">
                    <?php
                    $start = $offset + 1;
                    $end = min($offset + $per_page, $total_count);
                    printf(
                        /* translators: 1: start number, 2: end number, 3: total count */
                        esc_html__('Showing %1$s-%2$s of %3$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html(number_format_i18n($start)),
                        esc_html(number_format_i18n($end)),
                        esc_html(number_format_i18n($total_count))
                    );
                    ?>
                </span>
                <div class="bbai-pagination-controls">
                    <?php if ($current_page > 1) : ?>
                        <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page - 1)); ?>" class="bbai-pagination-btn">
                            <?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php endif; ?>
                    <?php
                    // Show page numbers
                    $page_range = 2;
                    $start_page = max(1, $current_page - $page_range);
                    $end_page = min($total_pages, $current_page + $page_range);
                    
                    if ($start_page > 1) {
                        echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="bbai-pagination-btn">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="bbai-pagination-ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $class = ($i === $current_page) ? 'bbai-pagination-btn bbai-pagination-btn--current' : 'bbai-pagination-btn';
                        echo '<a href="' . esc_url(add_query_arg('alt_page', $i)) . '" class="' . esc_attr($class) . '">' . esc_html($i) . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="bbai-pagination-ellipsis">...</span>';
                        }
                        echo '<a href="' . esc_url(add_query_arg('alt_page', $total_pages)) . '" class="bbai-pagination-btn">' . esc_html($total_pages) . '</a>';
                    }
                    ?>
                    <?php if ($current_page < $total_pages) : ?>
                        <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page + 1)); ?>" class="bbai-pagination-btn">
                            <?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <!-- Empty State -->
        <div class="bbai-library-empty-state bbai-empty-state">
            <div class="bbai-card bbai-text-center bbai-p-12">
                <div class="bbai-library-empty-content">
                    <h3 class="bbai-empty-state-title"><?php esc_html_e('No images yet', 'beepbeep-ai-alt-text-generator'); ?></h3>
                    <p class="bbai-empty-state-description">
                        <?php esc_html_e('Upload images to start automating SEO & accessibility compliance.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>
                    <div class="bbai-empty-state-benefits">
                        <div class="bbai-empty-state-benefit">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#10B981" stroke-width="2"/>
                                <path d="M6 10l2.5 2.5L14 7" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('WCAG-compliant alt text for accessibility', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                        <div class="bbai-empty-state-benefit">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#10B981" stroke-width="2"/>
                                <path d="M6 10l2.5 2.5L14 7" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Optimized for Google Images & SEO insights', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <a href="#" class="bbai-empty-state-sync-link">
                        <?php esc_html_e('Or sync from Media Library automatically', 'beepbeep-ai-alt-text-generator'); ?> &gt;
                    </a>
                    <div class="bbai-empty-state-actions">
                        <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn bbai-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                <path d="M8 2V14M2 8H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('+ Upload Images', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                        <a href="#tab-guide" class="bbai-btn bbai-btn-secondary" onclick="document.querySelector('[data-tab=\"guide\"]')?.click(); return false;">
                            <?php esc_html_e('Learn More', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        </div>
        
        <!-- Summary Statistics (always show) -->
        <?php
        // Prepare data for reusable metric cards component
        $alt_texts_generated = isset($usage_stats) && is_array($usage_stats) ? ($usage_stats['used'] ?? 0) : 0;
        $description_mode = ($alt_texts_generated > 0 || $with_alt_count > 0) ? 'active' : 'empty';
        $wrapper_class = 'bbai-premium-metrics-grid bbai-mt-6';
        $metric_cards_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/metric-cards.php';
        if (file_exists($metric_cards_partial)) {
            include $metric_cards_partial;
        }
        ?>
    <?php endif; ?>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    $bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
</div>
