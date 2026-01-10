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
<div class="bbai-wrap">
    <h1 class="bbai-credit-page-title">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <?php if ($view === 'user_detail' && $user_id > 0) : ?>
        <p class="bbai-mb-6">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-back-btn">
                <?php esc_html_e('← Back to Summary', 'beepbeep-ai-alt-text-generator'); ?>
            </a>
        </p>
    <?php endif; ?>

    <!-- Usage Summary Cards -->
    <div class="bbai-usage-summary-grid">
        <div class="bbai-credit-stat-card">
            <h3 class="bbai-credit-stat-title">
                <?php esc_html_e('Total Credits Allocated', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-credit-stat-value bbai-credit-stat-value--primary">
                <?php echo esc_html(number_format_i18n($current_usage['limit'])); ?>
            </p>
            <p class="bbai-credit-stat-desc">
                <?php
                $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                echo esc_html(ucfirst($plan));
                ?> <?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
        <div class="bbai-credit-stat-card">
            <h3 class="bbai-credit-stat-title">
                <?php esc_html_e('Total Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-credit-stat-value bbai-credit-stat-value--secondary">
                <?php echo esc_html(number_format_i18n($current_usage['used'])); ?>
            </p>
            <p class="bbai-credit-stat-desc">
                <?php
                $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                echo esc_html(ucfirst($plan));
                ?> <?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?>
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
            <p class="bbai-credit-stat-value bbai-credit-stat-value--primary-brand">
                <?php echo esc_html(number_format_i18n($current_usage['percentage'])); ?>%
            </p>
            <p class="bbai-credit-stat-desc">
                <?php esc_html_e('of monthly credits used', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="bbai-filter-form">
        <input type="hidden" name="page" value="bbai-credit-usage" />
        <div>
            <label class="bbai-filter-label"><?php esc_html_e('Date From', 'beepbeep-ai-alt-text-generator'); ?></label>
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="bbai-filter-input">
        </div>
        <div>
            <label class="bbai-filter-label"><?php esc_html_e('Date To', 'beepbeep-ai-alt-text-generator'); ?></label>
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="bbai-filter-input">
        </div>
        <div>
            <label class="bbai-filter-label"><?php esc_html_e('Source', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select name="source" class="bbai-filter-select">
                <option value=""><?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="api" <?php selected($source, 'api'); ?>><?php esc_html_e('API', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="upload" <?php selected($source, 'upload'); ?>><?php esc_html_e('Upload', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="bulk" <?php selected($source, 'bulk'); ?>><?php esc_html_e('Bulk', 'beepbeep-ai-alt-text-generator'); ?></option>
            </select>
        </div>
        <div>
            <label class="bbai-filter-label"><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select name="user_id" class="bbai-filter-select">
                <option value="0"><?php esc_html_e('All Users', 'beepbeep-ai-alt-text-generator'); ?></option>
                <?php foreach ($all_users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bbai-filter-submit">
            <button type="submit" class="bbai-btn bbai-btn-primary"><?php esc_html_e('Filter', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </form>

    <?php if ($view === 'user_detail' && $user_details) : ?>
        <div class="bbai-user-details-card">
            <h2 class="bbai-user-details-title"><?php esc_html_e('User Details', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p><strong><?php esc_html_e('Name:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['name'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Email:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['email'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Total Credits Used:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['total_credits'] ?? 0)); ?></p>
            <p><strong><?php esc_html_e('Images Processed:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['images_processed'] ?? 0)); ?></p>
        </div>
    <?php endif; ?>

    <!-- Usage Table -->
    <div class="bbai-table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 class="bbai-table-title" style="margin: 0;"><?php esc_html_e('Usage by User', 'beepbeep-ai-alt-text-generator'); ?></h2>
        </div>
        
        <?php if (empty($date_from) && empty($date_to)) : ?>
            <div class="notice notice-info" style="margin-bottom: 16px; padding: 12px;">
                <p style="margin: 0;">
                    <strong><?php esc_html_e('Note:', 'beepbeep-ai-alt-text-generator'); ?></strong>
                    <?php esc_html_e('The summary cards above show your current accurate usage from the backend. The table below shows historical WordPress user activity, which may include data from previous periods or different sites. Use the date filters to view recent usage only.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (!empty($usage_by_user['users'])) : ?>
            <table class="widefat fixed striped bbai-mt-4">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Images Processed', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Latest Activity', 'beepbeep-ai-alt-text-generator'); ?></th>
                        <th><?php esc_html_e('Actions', 'beepbeep-ai-alt-text-generator'); ?></th>
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
                            <td><?php echo esc_html(number_format_i18n($user_usage['images_processed'] ?? 0)); ?></td>
                            <td><?php echo esc_html($user_usage['latest_activity'] ?? '—'); ?></td>
                            <td>
                                <a class="bbai-btn bbai-btn-secondary bbai-btn-sm" href="<?php echo esc_url(add_query_arg(['view' => 'user_detail', 'user_id' => absint($user_usage['user_id'])])); ?>">
                                    <?php esc_html_e('View Details', 'beepbeep-ai-alt-text-generator'); ?>
                                </a>
                            </td>
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
            <p><?php esc_html_e('No usage data found for the selected filters.', 'beepbeep-ai-alt-text-generator'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Debug Info -->
    <details class="bbai-mt-5">
        <summary class="bbai-font-semibold" style="cursor: pointer;"><?php esc_html_e('Debug Info', 'beepbeep-ai-alt-text-generator'); ?></summary>
        <pre><?php echo esc_html(wp_json_encode($debug_info, JSON_PRETTY_PRINT)); ?></pre>
    </details>

</div>
