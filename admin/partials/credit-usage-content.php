<?php
/**
 * Credit Usage tab content partial.
 *
 * Expects the following variables to be defined:
 * $view, $user_id, $current_usage, $usage_by_user, $site_usage, $debug_info,
 * $all_users, $user_details, $date_from, $date_to, $source, $page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bbai-dashboard-container">
    <!-- Header Section -->
    <div class="bbai-credit-usage-header-section">
        <?php if ($view === 'user_detail' && $user_id > 0) : ?>
            <div style="margin-bottom: 16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-back-btn">
                    <?php esc_html_e('← Back to Summary', 'opptiai-alt'); ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="bbai-page-header-content">
            <h1 class="bbai-page-title">
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <p class="bbai-page-subtitle">
                <?php esc_html_e('Track your credit usage, view detailed activity by user, and monitor your monthly quota. Use filters to analyze usage patterns over time.', 'opptiai-alt'); ?>
            </p>
        </div>
    </div>

    <!-- Usage Summary Cards -->
    <div class="bbai-card bbai-credit-summary-cards">
        <div class="bbai-credit-stats-grid">
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Total Credits Allocated', 'opptiai-alt'); ?>
                </h3>
                <p class="bbai-credit-stat-value">
                    <?php echo esc_html(number_format_i18n($current_usage['limit'])); ?>
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php
                    $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                    echo esc_html(ucfirst($plan) . ' Plan');
                    ?>
                </p>
            </div>
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Total Credits Used', 'opptiai-alt'); ?>
                </h3>
                <p class="bbai-credit-stat-value">
                    <?php echo esc_html(number_format_i18n($current_usage['used'])); ?>
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php
                    $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                    echo esc_html(ucfirst($plan) . ' Plan');
                    ?>
                </p>
            </div>
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Credits Remaining', 'opptiai-alt'); ?>
                </h3>
                <?php
                // Determine status class based on remaining percentage
                $remaining_pct = $current_usage['limit'] > 0 ? ($current_usage['remaining'] / $current_usage['limit']) * 100 : 100;
                $remaining_class = 'bbai-credit-stat-value--success';
                if ($remaining_pct <= 10) {
                    $remaining_class = 'bbai-credit-stat-value--danger';
                } elseif ($remaining_pct <= 30) {
                    $remaining_class = 'bbai-credit-stat-value--warning';
                }
                ?>
                <p class="bbai-credit-stat-value <?php echo esc_attr($remaining_class); ?>">
                    <?php echo esc_html(number_format_i18n($current_usage['remaining'])); ?>
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php esc_html_e('Image descriptions left this month', 'opptiai-alt'); ?>
                </p>
            </div>
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Usage Percentage', 'opptiai-alt'); ?>
                </h3>
                <?php
                // Status-aware coloring: success <70%, warning 70-90%, danger >90%
                $usage_pct = $current_usage['percentage'];
                $usage_class = 'bbai-credit-stat-value--success';
                if ($usage_pct >= 90) {
                    $usage_class = 'bbai-credit-stat-value--danger';
                } elseif ($usage_pct >= 70) {
                    $usage_class = 'bbai-credit-stat-value--warning';
                }
                ?>
                <p class="bbai-credit-stat-value <?php echo esc_attr($usage_class); ?>">
                    <?php echo esc_html(number_format_i18n($current_usage['percentage'])); ?>%
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php esc_html_e('of monthly credits used', 'opptiai-alt'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bbai-card bbai-credit-filters-card">
        <form method="get" class="bbai-credit-filter-form">
            <input type="hidden" name="page" value="bbai-credit-usage" />
            <div class="bbai-credit-filter-row">
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Date From', 'opptiai-alt'); ?></label>
                    <div class="bbai-credit-filter-input-wrapper">
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="bbai-credit-filter-input" placeholder="mm/dd/yyyy">
                        <svg class="bbai-credit-filter-calendar-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Date To', 'opptiai-alt'); ?></label>
                    <div class="bbai-credit-filter-input-wrapper">
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="bbai-credit-filter-input" placeholder="mm/dd/yyyy">
                        <svg class="bbai-credit-filter-calendar-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Source', 'opptiai-alt'); ?></label>
                    <select name="source" class="bbai-credit-filter-select">
                        <option value=""><?php esc_html_e('All', 'opptiai-alt'); ?></option>
                        <option value="api" <?php selected($source, 'api'); ?>><?php esc_html_e('API', 'opptiai-alt'); ?></option>
                        <option value="upload" <?php selected($source, 'upload'); ?>><?php esc_html_e('Upload', 'opptiai-alt'); ?></option>
                        <option value="bulk" <?php selected($source, 'bulk'); ?>><?php esc_html_e('Bulk', 'opptiai-alt'); ?></option>
                    </select>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('User', 'opptiai-alt'); ?></label>
                    <select name="user_id" class="bbai-credit-filter-select">
                        <option value="0"><?php esc_html_e('All Users', 'opptiai-alt'); ?></option>
                        <?php foreach ($all_users as $user) : ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="bbai-credit-filter-actions">
                <button type="submit" class="bbai-btn bbai-btn-primary"><?php esc_html_e('Filter', 'opptiai-alt'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-clear-filter-link"><?php esc_html_e('Clear', 'opptiai-alt'); ?></a>
            </div>
        </form>
    </div>

    <?php if ($view === 'user_detail' && $user_details) : ?>
        <div class="bbai-card bbai-user-details-card">
            <h2 class="bbai-card-title"><?php esc_html_e('User Details', 'opptiai-alt'); ?></h2>
            <p><strong><?php esc_html_e('Name:', 'opptiai-alt'); ?></strong> <?php echo esc_html($user_details['name'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Email:', 'opptiai-alt'); ?></strong> <?php echo esc_html($user_details['email'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Total Credits Used:', 'opptiai-alt'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['total_credits'] ?? 0)); ?></p>
            <p><strong><?php esc_html_e('Images Processed:', 'opptiai-alt'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['images_processed'] ?? 0)); ?></p>
        </div>
    <?php endif; ?>

    <!-- SEO Heroes - Backend User Activity -->
    <?php if (!empty($backend_user_activity['users'])) : ?>
        <div class="bbai-card bbai-seo-heroes-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#F59E0B" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2 class="bbai-card-title" style="margin: 0;"><?php esc_html_e('SEO Heroes', 'opptiai-alt'); ?></h2>
            </div>
            <p class="bbai-card-subtitle" style="color: #64748b; margin-bottom: 16px;">
                <?php esc_html_e('Top contributors improving your site\'s accessibility and SEO this billing period.', 'opptiai-alt'); ?>
            </p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e('Rank', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('User', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('Images Optimized', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('Last Activity', 'opptiai-alt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    foreach ($backend_user_activity['users'] as $hero) :
                        $rank_icon = '';
                        if ($rank === 1) {
                            $rank_icon = '<span style="color: #F59E0B; font-size: 18px;">🥇</span>';
                        } elseif ($rank === 2) {
                            $rank_icon = '<span style="color: #94A3B8; font-size: 18px;">🥈</span>';
                        } elseif ($rank === 3) {
                            $rank_icon = '<span style="color: #CD7F32; font-size: 18px;">🥉</span>';
                        }
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if ($rank_icon) : ?>
                                    <?php echo wp_kses_post($rank_icon); ?>
                                <?php else : ?>
                                    <?php echo esc_html($rank); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($hero['user_email'] ?? __('Unknown User', 'opptiai-alt')); ?></strong>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #059669;"><?php echo esc_html(number_format_i18n($hero['credits_used'] ?? 0)); ?></span>
                            </td>
                            <td>
                                <?php
                                $last_activity = $hero['last_activity'] ?? null;
                                if ($last_activity) {
                                    $timestamp = strtotime($last_activity);
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    $rank++;
                    endforeach;
                    ?>
                </tbody>
            </table>
            <?php if (!empty($backend_user_activity['period_start']) && !empty($backend_user_activity['period_end'])) : ?>
                <p style="margin-top: 12px; font-size: 12px; color: #94A3B8;">
                    <?php
                    $period_start = strtotime($backend_user_activity['period_start']);
                    $period_end = strtotime($backend_user_activity['period_end']);
                    printf(
                        /* translators: 1: period start date, 2: period end date */
                        esc_html__('Billing period: %1$s - %2$s', 'opptiai-alt'),
                        esc_html(date_i18n(get_option('date_format'), $period_start)),
                        esc_html(date_i18n(get_option('date_format'), $period_end))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Usage Table -->
    <div class="bbai-card bbai-usage-table-card">
        <h2 class="bbai-card-title" style="margin-bottom: 16px;"><?php esc_html_e('Usage by User', 'opptiai-alt'); ?></h2>
        
        <?php if (empty($date_from) && empty($date_to)) : ?>
            <div class="bbai-info-notice bbai-mb-4">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="9" stroke="#3b82f6" stroke-width="1.5" fill="none"/>
                    <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span>
                    <strong><?php esc_html_e('Note:', 'opptiai-alt'); ?></strong>
                    <?php esc_html_e('The summary cards above show your current accurate usage from the backend. The table below shows historical WordPress user activity, which may include data from previous periods or different filters. Use the filters to view recent usage only.', 'opptiai-alt'); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($usage_by_user['users'])) : ?>
            <table class="widefat fixed striped bbai-mt-4">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('Credits Used', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('Last Activity', 'opptiai-alt'); ?></th>
                        <th><?php esc_html_e('Avg Per Day', 'opptiai-alt'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usage_by_user['users'] as $user_usage) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html($user_usage['display_name'] ?? __('Unknown', 'opptiai-alt')); ?>
                                <div class="bbai-table-subtitle"><?php echo esc_html($user_usage['user_email'] ?? ''); ?></div>
                            </td>
                            <td><?php echo esc_html(number_format_i18n($user_usage['total_credits'] ?? 0)); ?></td>
                            <td><?php echo esc_html($user_usage['latest_activity'] ?? '—'); ?></td>
                            <td><?php 
                                // Calculate average per day if we have date range
                                $avg_per_day = '—';
                                if (!empty($date_from) && !empty($date_to)) {
                                    $days = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400);
                                    $credits = $user_usage['total_credits'] ?? 0;
                                    $avg_per_day = number_format_i18n($credits / $days, 1);
                                }
                                echo esc_html($avg_per_day);
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!empty($usage_by_user['total'])) : ?>
                <div class="tablenav bbai-mt-4">
                    <div class="tablenav-pages">
                        <?php
                        $pagination = paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => __('&laquo;', 'opptiai-alt'),
                            'next_text' => __('&raquo;', 'opptiai-alt'),
                            'total'     => ceil($usage_by_user['total'] / 50),
                            'current'   => $page,
                            'type'      => 'array',
                        ]);

                        if (!empty($pagination)) {
                            echo '<span class="bbai-pagination">';
                            foreach ($pagination as $link) {
                                // $link is generated by paginate_links() which returns safe HTML
                                echo wp_kses_post($link);
                            }
                            echo '</span>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <p class="bbai-empty-table-message"><?php esc_html_e('No usage data found for selected filters.', 'opptiai-alt'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    // Set plan variables for bottom upsell CTA
    $plan_slug = isset($current_usage['plan']) && !empty($current_usage['plan']) 
        ? strtolower($current_usage['plan']) 
        : 'free';
    $is_free = ($plan_slug === 'free');
    $is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
    $is_agency = ($plan_slug === 'agency');
    $usage_stats = $current_usage; // Map current_usage to usage_stats for the component
    
    $bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
</div>
