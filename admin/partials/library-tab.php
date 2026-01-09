<?php
/**
 * ALT Library tab content partial.
 *
 * Expects $this (core class), $usage_stats, and $wpdb in scope.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
                <!-- ALT Library Table -->
                <?php
                // Pagination setup
                $per_page = 10;
                $alt_page_raw = isset($_GET['alt_page']) ? wp_unslash($_GET['alt_page']) : '';
                $current_page = max(1, absint($alt_page_raw));
                $offset = ($current_page - 1) * $per_page;
                
                // Get all images (with or without alt text) for proper filtering
                global $wpdb;
                
                // Get total count of all images
                $total_images = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
                    'attachment', 'image/%'
                ));
                
                // Get images with alt text count
                $with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_mime_type LIKE %s
                     AND p.post_status = %s
                     AND pm.meta_key = %s
                     AND TRIM(pm.meta_value) <> ''",
                    'attachment',
                    'image/%',
                    'inherit',
                    '_wp_attachment_image_alt'
                ));
                
                // Calculate optimization percentage
                $optimization_percentage = $total_images > 0 ? round(($with_alt_count / $total_images) * 100) : 0;
                
                // Get all images with their alt text status
                // Use GROUP BY to prevent duplicate rows if multiple meta entries exist
                // Since each attachment should have only one _wp_attachment_image_alt meta entry,
                // we use MAX() to handle any edge cases where duplicates might exist
                $all_images = $wpdb->get_results($wpdb->prepare("
                    SELECT p.ID,
                           p.post_title,
                           p.post_excerpt,
                           p.post_content,
                           p.post_author,
                           p.post_date,
                           p.post_date_gmt,
                           p.post_modified,
                           p.post_modified_gmt,
                           p.post_parent,
                           p.post_mime_type,
                           p.post_status,
                           MAX(COALESCE(pm.meta_value, '')) as alt_text,
                           MAX(CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> '' THEN 1 ELSE 0 END) as has_alt
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND p.post_status = 'inherit'
                    GROUP BY p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_author, p.post_date, p.post_date_gmt, p.post_modified, p.post_modified_gmt, p.post_parent, p.post_mime_type, p.post_status
                    ORDER BY p.post_date DESC
                    LIMIT %d OFFSET %d
                ", $per_page, $offset));

                $total_count = $total_images;
                $image_count = count($all_images);
                $total_pages = ceil($total_count / $per_page);
                $optimized_images = $all_images; // Use all_images for the table
                
                // Get plan info for upgrade card - check license first
                $has_license = $this->api_client->has_active_license();
                $license_data = $this->api_client->get_license_data();
                $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
                
                // If using license, check license plan
                if ($has_license && $license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    if ($license_plan !== 'free') {
                        $plan_slug = $license_plan;
                    }
                }
                
                $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                $is_agency = ($plan_slug === 'agency');
                
                ?>
                <div class="bbai-dashboard-container">
                    <!-- Header Section -->
                    <div class="bbai-dashboard-header-section">
                        <h1 class="bbai-dashboard-title"><?php esc_html_e('Image Alt Text Library', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-dashboard-subtitle"><?php esc_html_e('Browse, search, and regenerate SEO-optimized alt text for all images in your media library. Boost Google Images rankings and improve accessibility instantly.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        
                        <!-- Optimization Progress Badge -->
                        <div class="bbai-library-progress-badge" style="margin-top: 24px; display: inline-flex;">
                            <div class="bbai-library-progress-value"><?php echo esc_html($optimization_percentage); ?>%</div>
                            <div class="bbai-library-progress-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
                        </div>
                        
                        <!-- Optimization Notice -->
                        <?php if ($optimization_percentage >= 100) : ?>
                            <div class="bbai-library-success-notice" style="margin-top: 16px;">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="bbai-library-success-text">
                                    <?php esc_html_e('100% of your library is fully optimized!', 'beepbeep-ai-alt-text-generator'); ?>
                                </span>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="bbai-library-success-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade to Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($optimization_percentage > 0) : ?>
                            <div class="bbai-library-notice" style="margin-top: 16px;">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M10 5v5M10 13h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span class="bbai-library-notice-text">
                                    <?php 
                                    printf(
                                        esc_html__('%1$d%% of your library is optimized', 'beepbeep-ai-alt-text-generator'),
                                        $optimization_percentage
                                    );
                                    ?>
                                </span>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="bbai-library-notice-link" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade for bulk optimization', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filters Row -->
                    <div class="bbai-library-controls">
                        <div class="bbai-library-search-wrapper">
                            <svg class="bbai-library-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="text" 
                                   id="bbai-library-search" 
                                   class="bbai-library-search-input" 
                                   placeholder="<?php esc_attr_e('Search images or alt text…', 'beepbeep-ai-alt-text-generator'); ?>"
                                   aria-label="<?php esc_attr_e('Search images or alt text', 'beepbeep-ai-alt-text-generator'); ?>"
                            />
                        </div>
                        <div class="bbai-library-filters">
                            <select id="bbai-status-filter" class="bbai-library-filter-select" aria-label="<?php esc_attr_e('Filter by status', 'beepbeep-ai-alt-text-generator'); ?>">
                                <option value="all"><?php esc_html_e('All Status', 'beepbeep-ai-alt-text-generator'); ?></option>
                                <option value="optimized"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></option>
                                <option value="missing"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></option>
                                <option value="errors"><?php esc_html_e('Errors', 'beepbeep-ai-alt-text-generator'); ?></option>
                            </select>
                            <select id="bbai-time-filter" class="bbai-library-filter-select" aria-label="<?php esc_attr_e('Filter by time', 'beepbeep-ai-alt-text-generator'); ?>">
                                <option value="all-time"><?php esc_html_e('All Time', 'beepbeep-ai-alt-text-generator'); ?></option>
                                <option value="this-month"><?php esc_html_e('This Month', 'beepbeep-ai-alt-text-generator'); ?></option>
                                <option value="last-month"><?php esc_html_e('Last Month', 'beepbeep-ai-alt-text-generator'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Table Card -->
                    <div class="bbai-library-table-card<?php echo esc_attr($is_agency ? ' bbai-library-table-card--full-width' : ''); ?>">
                        <div class="bbai-table-scroll">
                            <table class="bbai-library-table" role="table" aria-label="<?php esc_attr_e('Image alt text library', 'beepbeep-ai-alt-text-generator'); ?>">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Image', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th scope="col"><?php esc_html_e('Status', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th scope="col"><?php esc_html_e('Date', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th scope="col"><?php esc_html_e('Alt Text', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th scope="col" class="bbai-library-actions-header"><?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?></th>
                                    </tr>
                                </thead>
                        <tbody>
                            <?php if (!empty($optimized_images)) : ?>
                                <?php $row_index = 0; ?>
                                <?php foreach ($optimized_images as $image) : ?>
                                    <?php
                                    $attachment_id = $image->ID;
                                    $old_alt = get_post_meta($attachment_id, '_bbai_original', true) ?: '';
                                    $current_alt = $image->alt_text ?? get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                                    $thumb_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                                    $attachment_title_raw = get_the_title($attachment_id);
                                    $attachment_title = is_string($attachment_title_raw) ? $attachment_title_raw : '';
                                    $edit_link = get_edit_post_link($attachment_id, '');
                                    
                                    $clean_current_alt = is_string($current_alt) ? trim($current_alt) : '';
                                    $clean_old_alt = is_string($old_alt) ? trim($old_alt) : '';
                                    $has_alt = !empty($clean_current_alt);
                                    
                                    $status_key = $has_alt ? 'optimized' : 'missing';
                                    $status_label = $has_alt ? __('✅ Optimized', 'beepbeep-ai-alt-text-generator') : __('🟠 Missing', 'beepbeep-ai-alt-text-generator');
                                    if ($has_alt && $clean_old_alt && strcasecmp($clean_old_alt, $clean_current_alt) !== 0) {
                                        $status_key = 'regenerated';
                                        $status_label = __('🔁 Regenerated', 'beepbeep-ai-alt-text-generator');
                                    }
                                    
                                    // Get the date alt text was generated (use modified date)
                                    $post = get_post($attachment_id);
                                    $modified_date = $post ? get_the_modified_date('M j, Y', $post) : '';
                                    $modified_time = $post ? get_the_modified_time('g:i a', $post) : '';

                                    $row_index++;
                                    
                                    // Truncate alt text to 55 characters
                                    $truncated_alt = $clean_current_alt;
                                    if (strlen($truncated_alt) > 55) {
                                        $truncated_alt = substr($truncated_alt, 0, 55) . '...';
                                    }
                                    
                                    // Status badge class names for CSS styling
                                    $status_class = 'bbai-status-badge';
                                    if ($status_key === 'optimized') {
                                        $status_class .= ' bbai-status-badge--optimized';
                                    } elseif ($status_key === 'missing') {
                                        $status_class .= ' bbai-status-badge--missing';
                                    } else {
                                        $status_class .= ' bbai-status-badge--regenerated';
                                    }
                                    ?>
                                    <tr class="bbai-library-row" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-status="<?php echo esc_attr($status_key); ?>">
                                        <td class="bbai-library-cell bbai-library-cell--image">
                                            <?php if ($thumb_url) : ?>
                                                <img src="<?php echo esc_url($thumb_url[0]); ?>" alt="<?php echo esc_attr($attachment_title); ?>" class="bbai-library-thumbnail" />
                                            <?php else : ?>
                                                <div class="bbai-library-thumbnail-placeholder">
                                                    <?php esc_html_e('—', 'beepbeep-ai-alt-text-generator'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--status">
                                            <span class="<?php echo esc_attr($status_class); ?>">
                                                <?php 
                                                if ($status_key === 'optimized') {
                                                    esc_html_e('Optimised', 'beepbeep-ai-alt-text-generator');
                                                } elseif ($status_key === 'missing') {
                                                    esc_html_e('Missing', 'beepbeep-ai-alt-text-generator');
                                                } else {
                                                    esc_html_e('Regenerated', 'beepbeep-ai-alt-text-generator');
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--date">
                                            <?php echo esc_html($modified_date ?: '—'); ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--alt-text new-alt-cell-<?php echo esc_attr($attachment_id); ?>">
                                            <?php if ($has_alt) : ?>
                                                <?php
                                                $char_count = mb_strlen($clean_current_alt);
                                                ?>
                                                <div class="bbai-alt-text-content">
                                                    <div class="bbai-alt-text-preview" title="<?php echo esc_attr($clean_current_alt); ?>">
                                                        <?php echo esc_html($truncated_alt); ?>
                                                    </div>
                                                    <div class="bbai-alt-text-meta">
                                                        <?php
                                                        // Use unified badge that combines grade + char count
                                                        if (class_exists('BBAI_SEO_Quality_Checker')) {
                                                            echo BBAI_SEO_Quality_Checker::create_unified_badge($clean_current_alt);
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php else : ?>
                                                <div class="bbai-alt-text-content">
                                                    <span class="bbai-alt-text-missing"><?php esc_html_e('No alt text', 'beepbeep-ai-alt-text-generator'); ?></span>
                                                    <div class="bbai-alt-text-meta">
                                                        <?php
                                                        if (class_exists('BBAI_SEO_Quality_Checker')) {
                                                            echo BBAI_SEO_Quality_Checker::create_unified_badge('');
                                                        } else {
                                                            echo '<span class="bbai-seo-unified-badge bbai-seo-unified-badge--empty">—</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--actions">
                                            <?php 
                                            $is_local_dev = defined('WP_LOCAL_DEV') && WP_LOCAL_DEV;
                                            $can_regenerate = $is_local_dev || $this->api_client->is_authenticated();
                                            ?>
                                            <button type="button"
                                                    class="bbai-btn-regenerate"
                                                    data-action="regenerate-single"
                                                    data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                                    data-image-id="<?php echo esc_attr($attachment_id); ?>"
                                                    data-id="<?php echo esc_attr($attachment_id); ?>"
                                                    id="bbai-regenerate-btn-<?php echo esc_attr($attachment_id); ?>"
                                                    data-bbai-tooltip="<?php esc_attr_e('Generate a new alt text description for this image. Previous alt text will be replaced.', 'beepbeep-ai-alt-text-generator'); ?>"
                                                    data-bbai-tooltip-position="left"
                                                    <?php echo !$can_regenerate ? 'disabled title="' . esc_attr__('Please log in to regenerate alt text', 'beepbeep-ai-alt-text-generator') . '"' : ''; ?>>
                                                <?php esc_html_e('Regenerate', 'beepbeep-ai-alt-text-generator'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="bbai-library-empty-state">
                                        <div class="bbai-library-empty-content">
                                            <div class="bbai-library-empty-title">
                                                <?php esc_html_e('No images found', 'beepbeep-ai-alt-text-generator'); ?>
                                            </div>
                                            <div class="bbai-library-empty-subtitle">
                                                <?php esc_html_e('Upload images to your Media Library to get started.', 'beepbeep-ai-alt-text-generator'); ?>
                                            </div>
                                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-library-empty-btn">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                                <?php esc_html_e('Upload Images', 'beepbeep-ai-alt-text-generator'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="bbai-pagination" role="navigation" aria-label="<?php esc_attr_e('Pagination', 'beepbeep-ai-alt-text-generator'); ?>">
                            <div class="bbai-pagination-info">
                                <?php 
                                $start = $offset + 1;
                                $end = min($offset + $per_page, $total_count);
                                printf(
                                    esc_html__('Showing %1$d-%2$d of %3$d images', 'beepbeep-ai-alt-text-generator'),
                                    $start,
                                    $end,
                                    $total_count
                                );
                                ?>
                            </div>
                            
                            <div class="bbai-pagination-controls">
                                <?php if ($current_page > 1) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', 1)); ?>" 
                                       class="bbai-pagination-btn bbai-pagination-btn--first" 
                                       aria-label="<?php esc_attr_e('First page', 'beepbeep-ai-alt-text-generator'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M11 2L6 8l5 6M5 2L0 8l5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span><?php esc_html_e('First', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page - 1)); ?>" 
                                       class="bbai-pagination-btn bbai-pagination-btn--prev" 
                                       aria-label="<?php esc_attr_e('Previous page', 'beepbeep-ai-alt-text-generator'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M10 2L5 8l5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span><?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </a>
                                <?php else : ?>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled" aria-disabled="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M11 2L6 8l5 6M5 2L0 8l5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>
                                        </svg>
                                        <span><?php esc_html_e('First', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </span>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled" aria-disabled="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M10 2L5 8l5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>
                                        </svg>
                                        <span><?php esc_html_e('Previous', 'beepbeep-ai-alt-text-generator'); ?></span>
                                    </span>
                                <?php endif; ?>
                                
                                <div class="bbai-pagination-pages" role="list">
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="bbai-pagination-btn" role="listitem" aria-label="' . esc_attr(sprintf(__('Page %d', 'beepbeep-ai-alt-text-generator'), 1)) . '">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="bbai-pagination-ellipsis" aria-hidden="true">…</span>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        if ($i == $current_page) {
                                            echo '<span class="bbai-pagination-btn bbai-pagination-btn--current" role="listitem" aria-current="page" aria-label="' . esc_attr(sprintf(__('Page %d, current page', 'beepbeep-ai-alt-text-generator'), $i)) . '">' . esc_html($i) . '</span>';
                                        } else {
                                            echo '<a href="' . esc_url(add_query_arg('alt_page', $i)) . '" class="bbai-pagination-btn" role="listitem" aria-label="' . esc_attr(sprintf(__('Page %d', 'beepbeep-ai-alt-text-generator'), $i)) . '">' . esc_html($i) . '</a>';
                                        }
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="bbai-pagination-ellipsis" aria-hidden="true">…</span>';
                                        }
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', $total_pages)) . '" class="bbai-pagination-btn" role="listitem" aria-label="' . esc_attr(sprintf(__('Page %d', 'beepbeep-ai-alt-text-generator'), $total_pages)) . '">' . esc_html($total_pages) . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($current_page < $total_pages) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page + 1)); ?>" 
                                       class="bbai-pagination-btn bbai-pagination-btn--next" 
                                       aria-label="<?php esc_attr_e('Next page', 'beepbeep-ai-alt-text-generator'); ?>">
                                        <span><?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M6 2l5 6-5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $total_pages)); ?>" 
                                       class="bbai-pagination-btn bbai-pagination-btn--last" 
                                       aria-label="<?php esc_attr_e('Last page', 'beepbeep-ai-alt-text-generator'); ?>">
                                        <span><?php esc_html_e('Last', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M5 2l5 6-5 6M11 2l5 6-5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                <?php else : ?>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled" aria-disabled="true">
                                        <span><?php esc_html_e('Next', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M6 2l5 6-5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>
                                        </svg>
                                    </span>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled" aria-disabled="true">
                                        <span><?php esc_html_e('Last', 'beepbeep-ai-alt-text-generator'); ?></span>
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                            <path d="M5 2l5 6-5 6M11 2l5 6-5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                                </div>
                    </div> <!-- End Table Card -->
                    
                    <!-- Upgrade Card (hidden for Agency, visible for Free/Pro) -->
                    <?php if (!$is_agency) : ?>
                    <div class="bbai-library-upgrade-card">
                        <div class="bbai-library-upgrade-content">
                            <div>
                                <h3 class="bbai-library-upgrade-title">
                                    <?php esc_html_e('Upgrade to Pro', 'beepbeep-ai-alt-text-generator'); ?>
                                </h3>
                                <p class="bbai-library-upgrade-subtitle">
                                    <?php esc_html_e('Unlock powerful features for bulk optimization and advanced control', 'beepbeep-ai-alt-text-generator'); ?>
                                </p>
                            </div>
                            <div class="bbai-library-upgrade-features">
                                <div class="bbai-library-upgrade-feature">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Bulk ALT optimisation', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                                <div class="bbai-library-upgrade-feature">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Unlimited background queue', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                                <div class="bbai-library-upgrade-feature">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Smart tone tuning', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                                <div class="bbai-library-upgrade-feature">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Priority support', 'beepbeep-ai-alt-text-generator'); ?></span>
                                </div>
                            </div>
                        </div>
                        <button type="button" 
                                class="bbai-library-upgrade-btn"
                                data-action="show-upgrade-modal"
                                aria-label="<?php esc_attr_e('View upgrade plans', 'beepbeep-ai-alt-text-generator'); ?>">
                            <?php esc_html_e('View Plans', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    </div> <!-- End Dashboard Container -->
                
                <!-- Status for AJAX operations -->
                <div class="bbai-dashboard__status" data-progress-status role="status" aria-live="polite" style="display: none;"></div>
                

            </div>


