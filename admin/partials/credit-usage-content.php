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
                    $bbai_plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                    echo esc_html(ucfirst($bbai_plan) . ' Plan');
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
                    $bbai_plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                    echo esc_html(ucfirst($bbai_plan) . ' Plan');
                    ?>
                </p>
            </div>
            <div class="bbai-credit-stat-card">
                <h3 class="bbai-credit-stat-title">
                    <?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?>
                </h3>
                <?php
                // Determine status class based on remaining percentage
                $bbai_remaining_pct = $current_usage['limit'] > 0 ? ($current_usage['remaining'] / $current_usage['limit']) * 100 : 100;
                $bbai_remaining_class = 'bbai-credit-stat-value--success';
                if ($bbai_remaining_pct <= 10) {
                    $bbai_remaining_class = 'bbai-credit-stat-value--danger';
                } elseif ($bbai_remaining_pct <= 30) {
                    $bbai_remaining_class = 'bbai-credit-stat-value--warning';
                }
                ?>
                <p class="bbai-credit-stat-value <?php echo esc_attr($bbai_remaining_class); ?>">
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
                <?php
                // Status-aware coloring: success <70%, warning 70-90%, danger >90%
                $bbai_usage_pct = $current_usage['percentage'];
                $bbai_usage_class = 'bbai-credit-stat-value--success';
                if ($bbai_usage_pct >= 90) {
                    $bbai_usage_class = 'bbai-credit-stat-value--danger';
                } elseif ($bbai_usage_pct >= 70) {
                    $bbai_usage_class = 'bbai-credit-stat-value--warning';
                }
                ?>
                <p class="bbai-credit-stat-value <?php echo esc_attr($bbai_usage_class); ?>">
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
                        <?php foreach ($all_users as $bbai_user) : ?>
                            <option value="<?php echo esc_attr($bbai_user->ID); ?>" <?php selected($user_id, $bbai_user->ID); ?>>
                                <?php echo esc_html($bbai_user->display_name . ' (' . $bbai_user->user_email . ')'); ?>
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

    <!-- SEO Heroes - Backend User Activity -->
    <?php if (!empty($backend_user_activity['users'])) : ?>
        <div class="bbai-card bbai-seo-heroes-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="#F59E0B" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2 class="bbai-card-title" style="margin: 0;"><?php esc_html_e('SEO Heroes', 'beepbeep-ai-alt-text-generator'); ?></h2>
            </div>
            <p class="bbai-card-subtitle" style="color: #64748b; margin-bottom: 16px;">
                <?php esc_html_e('Top contributors improving your site\'s accessibility and SEO this billing period.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e('Rank', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Images Optimized', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Last Activity', 'beepbeep-ai-alt-text-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $bbai_rank = 1;
                    foreach ($backend_user_activity['users'] as $bbai_hero) :
                        $bbai_rank_icon = '';
                        if ($bbai_rank === 1) {
                            $bbai_rank_icon = '<span style="color: #F59E0B; font-size: 18px;">🥇</span>';
                        } elseif ($bbai_rank === 2) {
                            $bbai_rank_icon = '<span style="color: #94A3B8; font-size: 18px;">🥈</span>';
                        } elseif ($bbai_rank === 3) {
                            $bbai_rank_icon = '<span style="color: #CD7F32; font-size: 18px;">🥉</span>';
                        }
                    ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if ($bbai_rank_icon) : ?>
                                    <?php echo wp_kses_post($bbai_rank_icon); ?>
                                <?php else : ?>
                                    <?php echo esc_html($bbai_rank); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $bbai_hero_name = $bbai_hero['display_name'] ?? $bbai_hero['user_name'] ?? $bbai_hero['name'] ?? $bbai_hero['user_email'] ?? $bbai_hero['email'] ?? __('Unknown User', 'beepbeep-ai-alt-text-generator');
                                $bbai_hero_email = $bbai_hero['user_email'] ?? $bbai_hero['email'] ?? '';
                                ?>
                                <strong><?php echo esc_html($bbai_hero_name); ?></strong>
                                <?php if (!empty($bbai_hero_email) && $bbai_hero_email !== $bbai_hero_name) : ?>
                                    <div class="bbai-table-subtitle"><?php echo esc_html($bbai_hero_email); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #059669;"><?php echo esc_html(number_format_i18n($bbai_hero['credits_used'] ?? 0)); ?></span>
                            </td>
                            <td>
                                <?php
                                $bbai_last_activity = $bbai_hero['last_activity'] ?? null;
                                if ($bbai_last_activity) {
                                    $bbai_timestamp = strtotime($bbai_last_activity);
                                    echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $bbai_timestamp));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    $bbai_rank++;
                    endforeach;
                    ?>
                </tbody>
            </table>
            <?php if (!empty($backend_user_activity['period_start']) && !empty($backend_user_activity['period_end'])) : ?>
                <p style="margin-top: 12px; font-size: 12px; color: #94A3B8;">
                    <?php
                    $bbai_period_start = strtotime($backend_user_activity['period_start']);
                    $bbai_period_end = strtotime($backend_user_activity['period_end']);
                    printf(
                        /* translators: 1: period start date, 2: period end date */
                        esc_html__('Billing period: %1$s - %2$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html(date_i18n(get_option('date_format'), $bbai_period_start)),
                        esc_html(date_i18n(get_option('date_format'), $bbai_period_end))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Bottom Upsell CTA (reusable component) -->
    <?php
    // Set plan variables for bottom upsell CTA
    $bbai_plan_slug = isset($current_usage['plan']) && !empty($current_usage['plan']) 
        ? strtolower($current_usage['plan']) 
        : 'free';
    $bbai_is_free = ($bbai_plan_slug === 'free');
    $bbai_is_growth = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'growth');
    $bbai_is_agency = ($bbai_plan_slug === 'agency');
    $bbai_usage_stats = $current_usage; // Map current_usage to usage_stats for the component
    
    $bbai_bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
    if (file_exists($bbai_bottom_upsell_partial)) {
        include $bbai_bottom_upsell_partial;
    }
    ?>
</div>
