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
<div class="wrap" style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <h1 style="font-size: 28px; font-weight: 600; color: #0F172A; margin-bottom: 24px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <?php if ($view === 'user_detail' && $user_id > 0) : ?>
        <p style="margin-bottom: 24px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" 
               class="button" 
               style="background: #2563EB; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; font-size: 14px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; text-decoration: none; transition: background 0.15s; display: inline-block;"
               onmouseover="this.style.background='#1D4ED8'" 
               onmouseout="this.style.background='#2563EB'">
                <?php esc_html_e('← Back to Summary', 'beepbeep-ai-alt-text-generator'); ?>
            </a>
        </p>
    <?php endif; ?>

    <!-- Usage Summary Cards -->
    <div class="bbai-usage-summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin: 32px 0;">
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
            <h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('Total Credits Allocated', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #0F172A; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
                <?php echo esc_html(number_format_i18n($current_usage['limit'])); ?>
            </p>
            <p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php 
                $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                echo esc_html(ucfirst($plan)); 
                ?> <?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
            <h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('Total Credits Used', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #334155; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
                <?php echo esc_html(number_format_i18n($current_usage['used'])); ?>
            </p>
            <p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php 
                $plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
                echo esc_html(ucfirst($plan)); 
                ?> <?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
            <h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #10B981; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
                <?php echo esc_html(number_format_i18n($current_usage['remaining'])); ?>
            </p>
            <p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('Image descriptions left this month', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
        <div class="card" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); transition: box-shadow 0.2s;">
            <h3 style="font-size: 14px; font-weight: 600; color: #0F172A; margin: 0 0 12px 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('Usage Percentage', 'beepbeep-ai-alt-text-generator'); ?>
            </h3>
            <p class="bbai-card-value" style="font-size: 32px; font-weight: 700; margin: 8px 0; color: #6366F1; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; letter-spacing: -0.02em;">
                <?php echo esc_html(number_format_i18n($current_usage['percentage'])); ?>%
            </p>
            <p style="color: #64748B; font-size: 14px; margin: 0; font-weight: 400; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?php esc_html_e('of monthly credits used', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; margin-bottom: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <input type="hidden" name="page" value="bbai-credit-usage" />
        <div>
            <label style="display:block; font-weight: 600; margin-bottom: 8px; color: #0F172A;"><?php esc_html_e('Date From', 'beepbeep-ai-alt-text-generator'); ?></label>
            <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="width: 100%; padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px;">
        </div>
        <div>
            <label style="display:block; font-weight: 600; margin-bottom: 8px; color: #0F172A;"><?php esc_html_e('Date To', 'beepbeep-ai-alt-text-generator'); ?></label>
            <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="width: 100%; padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px;">
        </div>
        <div>
            <label style="display:block; font-weight: 600; margin-bottom: 8px; color: #0F172A;"><?php esc_html_e('Source', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select name="source" style="width: 100%; padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px;">
                <option value=""><?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="api" <?php selected($source, 'api'); ?>><?php esc_html_e('API', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="upload" <?php selected($source, 'upload'); ?>><?php esc_html_e('Upload', 'beepbeep-ai-alt-text-generator'); ?></option>
                <option value="bulk" <?php selected($source, 'bulk'); ?>><?php esc_html_e('Bulk', 'beepbeep-ai-alt-text-generator'); ?></option>
            </select>
        </div>
        <div>
            <label style="display:block; font-weight: 600; margin-bottom: 8px; color: #0F172A;"><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></label>
            <select name="user_id" style="width: 100%; padding: 10px; border: 1px solid #E2E8F0; border-radius: 8px;">
                <option value="0"><?php esc_html_e('All Users', 'beepbeep-ai-alt-text-generator'); ?></option>
                <?php foreach ($all_users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="align-self: flex-end;">
            <button type="submit" class="button button-primary" style="padding: 10px 16px;"><?php esc_html_e('Filter', 'beepbeep-ai-alt-text-generator'); ?></button>
        </div>
    </form>

    <?php if ($view === 'user_detail' && $user_details) : ?>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <h2 style="font-size: 20px; font-weight: 700; margin-top: 0;"><?php esc_html_e('User Details', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p><strong><?php esc_html_e('Name:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['name'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Email:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['email'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Total Credits Used:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['total_credits'] ?? 0)); ?></p>
            <p><strong><?php esc_html_e('Images Processed:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['images_processed'] ?? 0)); ?></p>
        </div>
    <?php endif; ?>

    <!-- Usage Table -->
    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-top: 0;"><?php esc_html_e('Usage by User', 'beepbeep-ai-alt-text-generator'); ?></h2>

        <?php if (!empty($usage_by_user['users'])) : ?>
            <table class="widefat fixed striped" style="margin-top: 12px;">
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
                                <div style="color: #64748B; font-size: 12px;"><?php echo esc_html($user_usage['user_email'] ?? ''); ?></div>
                            </td>
                            <td><?php echo esc_html(number_format_i18n($user_usage['total_credits'] ?? 0)); ?></td>
                            <td><?php echo esc_html(number_format_i18n($user_usage['images_processed'] ?? 0)); ?></td>
                            <td><?php echo esc_html($user_usage['latest_activity'] ?? '—'); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(add_query_arg(['view' => 'user_detail', 'user_id' => absint($user_usage['user_id'])])); ?>">
                                    <?php esc_html_e('View Details', 'beepbeep-ai-alt-text-generator'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!empty($usage_by_user['total'])) : ?>
                <div class="tablenav" style="margin-top: 12px;">
                    <div class="tablenav-pages">
                        <?php
                        $pagination = paginate_links([
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total'     => ceil($usage_by_user['total'] / 50),
                            'current'   => $page,
                            'type'      => 'array',
                        ]);

                        if (!empty($pagination)) {
                            echo '<span class="pagination-links" style="display: inline-flex; gap: 4px;">';
                            foreach ($pagination as $link) {
                                echo $link;
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
    <details style="margin-top: 20px;">
        <summary style="cursor: pointer; font-weight: 600;"><?php esc_html_e('Debug Info', 'beepbeep-ai-alt-text-generator'); ?></summary>
        <pre><?php echo esc_html(wp_json_encode($debug_info, JSON_PRETTY_PRINT)); ?></pre>
    </details>

    <!-- Manual Table Creation -->
    <div style="margin-top: 24px; padding: 16px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px;">
        <h2 style="margin-top: 0; color: #B91C1C;"><?php esc_html_e('Table Maintenance', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <p style="margin-bottom: 12px;"><?php esc_html_e('If the credit usage table is missing, you can recreate it here.', 'beepbeep-ai-alt-text-generator'); ?></p>
        <form method="post">
            <?php wp_nonce_field('bbai_create_credit_table', 'bbai_create_table_nonce'); ?>
            <input type="hidden" name="bbai_action" value="create_credit_table">
            <button type="submit" class="button button-primary"><?php esc_html_e('Create Credit Usage Table', 'beepbeep-ai-alt-text-generator'); ?></button>
        </form>
    </div>
</div>
