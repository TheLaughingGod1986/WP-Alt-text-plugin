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
                    <?php esc_html_e('← Back to Summary', 'beepbeep-ai-alt-text-generator'); ?>
                </a>
            </div>
        <?php endif; ?>
        <div class="bbai-page-header-content">
            <h1 class="bbai-page-title">
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <p class="bbai-page-subtitle">
                <?php esc_html_e('Track your credit usage, view detailed activity by user, and monitor your monthly quota. Use filters to analyze usage patterns over time.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
    </div>

    <!-- Usage Summary Cards -->
    <div class="bbai-card bbai-credit-summary-cards">
        <div class="bbai-credit-stats-grid">
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Total Credits Allocated', 'beepbeep-ai-alt-text-generator'); ?>
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
                    <?php esc_html_e('Total Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
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
                    <?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?>
                </h3>
                <p class="bbai-credit-stat-value bbai-credit-stat-value--success">
                    <?php echo esc_html(number_format_i18n($current_usage['remaining'])); ?>
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php esc_html_e('Image descriptions left this month', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Usage Percentage', 'beepbeep-ai-alt-text-generator'); ?>
                </h3>
                <p class="bbai-credit-stat-value bbai-credit-stat-value--success">
                    <?php echo esc_html(number_format_i18n($current_usage['percentage'])); ?>%
                </p>
                <p class="bbai-credit-stat-desc">
                    <?php esc_html_e('of monthly credits used', 'beepbeep-ai-alt-text-generator'); ?>
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
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Date From', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <div class="bbai-credit-filter-input-wrapper">
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="bbai-credit-filter-input" placeholder="mm/dd/yyyy">
                        <svg class="bbai-credit-filter-calendar-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Date To', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <div class="bbai-credit-filter-input-wrapper">
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="bbai-credit-filter-input" placeholder="mm/dd/yyyy">
                        <svg class="bbai-credit-filter-calendar-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('Source', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <select name="source" class="bbai-credit-filter-select">
                        <option value=""><?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="api" <?php selected($source, 'api'); ?>><?php esc_html_e('API', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="upload" <?php selected($source, 'upload'); ?>><?php esc_html_e('Upload', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <option value="bulk" <?php selected($source, 'bulk'); ?>><?php esc_html_e('Bulk', 'beepbeep-ai-alt-text-generator'); ?></option>
                    </select>
                </div>
                <div class="bbai-credit-filter-field">
                    <label class="bbai-credit-filter-label"><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></label>
                    <select name="user_id" class="bbai-credit-filter-select">
                        <option value="0"><?php esc_html_e('All Users', 'beepbeep-ai-alt-text-generator'); ?></option>
                        <?php foreach ($all_users as $user) : ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="bbai-credit-filter-actions">
                <button type="submit" class="bbai-btn bbai-btn-primary"><?php esc_html_e('Filter', 'beepbeep-ai-alt-text-generator'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-clear-filter-link"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </form>
    </div>

    <?php if ($view === 'user_detail' && $user_details) : ?>
        <div class="bbai-card bbai-user-details-card">
            <h2 class="bbai-card-title"><?php esc_html_e('User Details', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p><strong><?php esc_html_e('Name:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['name'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Email:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['email'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Total Credits Used:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['total_credits'] ?? 0)); ?></p>
            <p><strong><?php esc_html_e('Images Processed:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['images_processed'] ?? 0)); ?></p>
        </div>
    <?php endif; ?>

    <!-- Usage Table -->
    <div class="bbai-card bbai-usage-table-card">
        <h2 class="bbai-card-title" style="margin-bottom: 16px;"><?php esc_html_e('Usage by User', 'beepbeep-ai-alt-text-generator'); ?></h2>
        
        <?php if (empty($date_from) && empty($date_to)) : ?>
            <div class="bbai-info-notice bbai-mb-4">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="9" stroke="#3b82f6" stroke-width="1.5" fill="none"/>
                    <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span>
                    <strong><?php esc_html_e('Note:', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <?php esc_html_e('The summary cards above show your current accurate usage from the backend. The table below shows historical WordPress user activity, which may include data from previous periods or different filters. Use the filters to view recent usage only.', 'beepbeep-ai-alt-text-generator'); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($usage_by_user['users'])) : ?>
            <table class="widefat fixed striped bbai-mt-4">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Last Activity', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Avg Per Day', 'beepbeep-ai-alt-text-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usage_by_user['users'] as $user_usage) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html($user_usage['display_name'] ?? __('Unknown', 'beepbeep-ai-alt-text-generator')); ?>
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
                            'prev_text' => __('&laquo;', 'beepbeep-ai-alt-text-generator'),
                            'next_text' => __('&raquo;', 'beepbeep-ai-alt-text-generator'),
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
            <p class="bbai-empty-table-message"><?php esc_html_e('No usage data found for selected filters.', 'beepbeep-ai-alt-text-generator'); ?></p>
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
    
    $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
    if (file_exists($bottom_upsell_partial)) {
        include $bottom_upsell_partial;
    }
    ?>
</div>
