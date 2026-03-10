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
<?php
$bbai_used = (int) ($current_usage['used'] ?? 0);
$bbai_limit = max(1, (int) ($current_usage['limit'] ?? 50));
$bbai_remaining = (int) ($current_usage['remaining'] ?? 0);
$bbai_progress_pct = min(100, round(($bbai_used / $bbai_limit) * 100));
$bbai_days_reset = isset($current_usage['days_until_reset']) && is_numeric($current_usage['days_until_reset'])
    ? max(0, (int) $current_usage['days_until_reset'])
    : 0;
$bbai_reset_date = $current_usage['reset_date'] ?? '';
$bbai_plan = isset($current_usage['plan']) && !empty($current_usage['plan']) ? $current_usage['plan'] : 'free';
?>
<div class="bbai-dashboard-container bbai-credit-usage-page">
    <!-- 1. Page header -->
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

    <!-- 2. Credit usage meter -->
    <div class="bbai-card bbai-credit-usage-meter-card">
        <h3 class="bbai-credit-meter-title"><?php esc_html_e('Credit Usage', 'beepbeep-ai-alt-text-generator'); ?></h3>
        <?php if ($bbai_remaining === 0) : ?>
            <div class="bbai-limit-banner">
                <strong><?php esc_html_e('Limit reached', 'beepbeep-ai-alt-text-generator'); ?></strong>
                <?php
                printf(
                    /* translators: 1: credits limit (e.g. 50), 2: upgrade limit (e.g. 1,000) */
                    esc_html__('You\'ve used all %1$s free credits this month. Upgrade to generate up to %2$s ALT texts automatically.', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($bbai_limit)),
                    esc_html(number_format_i18n(1000))
                );
                ?>
            </div>
        <?php endif; ?>
        <div class="bbai-credit-bar" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_progress_pct); ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="bbai-credit-bar-fill" style="width: <?php echo esc_attr($bbai_progress_pct); ?>%;"></div>
        </div>
        <p class="bbai-credit-usage-text">
            <?php
            printf(
                /* translators: 1: credits used, 2: credits limit */
                esc_html__('%1$s / %2$s credits used', 'beepbeep-ai-alt-text-generator'),
                esc_html(number_format_i18n($bbai_used)),
                esc_html(number_format_i18n($bbai_limit))
            );
            ?>
        </p>
        <p class="bbai-credit-reset">
            <?php
            if ($bbai_days_reset > 0) {
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of days until reset */
                        _n('Resets in %d day', 'Resets in %d days', $bbai_days_reset, 'beepbeep-ai-alt-text-generator'),
                        $bbai_days_reset
                    )
                );
            } elseif (!empty($bbai_reset_date)) {
                printf(
                    /* translators: %s: reset date */
                    esc_html__('Resets on %s', 'beepbeep-ai-alt-text-generator'),
                    esc_html($bbai_reset_date)
                );
            } else {
                esc_html_e('Monthly reset', 'beepbeep-ai-alt-text-generator');
            }
            ?>
        </p>
        <p class="bbai-credit-helper">
            <?php
            if ($bbai_remaining === 0) {
                printf(
                    /* translators: 1: credits limit (e.g. 50), 2: upgrade limit (e.g. 1,000) */
                    esc_html__('You\'ve used all %1$s free credits this month. Upgrade to generate up to %2$s ALT texts automatically.', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($bbai_limit)),
                    esc_html(number_format_i18n(1000))
                );
            } else {
                printf(
                    /* translators: %s: credits remaining */
                    esc_html__('You have %s credits remaining this month.', 'beepbeep-ai-alt-text-generator'),
                    esc_html(number_format_i18n($bbai_remaining))
                );
            }
            ?>
        </p>
    </div>

    <!-- 3. Stats cards + 4. Upgrade card -->
    <div class="bbai-credit-stats-upgrade-grid">
        <div class="bbai-credit-stats-grid">
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-credit-stat-value"><?php echo esc_html(ucfirst($bbai_plan)); ?></span>
            </div>
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-credit-stat-value"><?php echo esc_html(number_format_i18n($bbai_used) . ' / ' . number_format_i18n($bbai_limit)); ?></span>
            </div>
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
                <?php
                $bbai_remaining_pct = $bbai_limit > 0 ? ($bbai_remaining / $bbai_limit) * 100 : 100;
                $bbai_remaining_class = 'bbai-credit-stat-value--success';
                if ($bbai_remaining_pct <= 10) {
                    $bbai_remaining_class = 'bbai-credit-stat-value--danger';
                } elseif ($bbai_remaining_pct <= 30) {
                    $bbai_remaining_class = 'bbai-credit-stat-value--warning';
                }
                ?>
                <span class="bbai-credit-stat-value <?php echo esc_attr($bbai_remaining_class); ?>"><?php echo esc_html(number_format_i18n($bbai_remaining)); ?></span>
            </div>
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Reset', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-credit-stat-value bbai-credit-stat-value--reset">
                    <?php
                    if ($bbai_days_reset > 0) {
                        echo esc_html(
                            sprintf(
                                /* translators: %d: days until reset */
                                _n('%d day', '%d days', $bbai_days_reset, 'beepbeep-ai-alt-text-generator'),
                                $bbai_days_reset
                            )
                        );
                    } else {
                        echo esc_html($bbai_reset_date ?: '—');
                    }
                    ?>
                </span>
            </div>
        </div>
        <div class="bbai-card bbai-credit-upgrade-card bbai-upgrade-growth-card">
            <h3 class="bbai-credit-upgrade-title"><?php esc_html_e('Upgrade to Growth', 'beepbeep-ai-alt-text-generator'); ?></h3>
            <p class="bbai-credit-upgrade-desc">
                <?php esc_html_e('Generate up to 1,000 ALT texts per month.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
            <ul class="bbai-credit-upgrade-features">
                <li><?php esc_html_e('Bulk processing for media library', 'beepbeep-ai-alt-text-generator'); ?></li>
                <li><?php esc_html_e('Priority queue for faster results', 'beepbeep-ai-alt-text-generator'); ?></li>
                <li><?php esc_html_e('WooCommerce product image support', 'beepbeep-ai-alt-text-generator'); ?></li>
                <li><?php esc_html_e('Multilingual SEO support', 'beepbeep-ai-alt-text-generator'); ?></li>
            </ul>
            <button type="button" class="bbai-btn bbai-btn-primary bbai-credit-upgrade-cta" data-action="show-upgrade-modal">
                <?php esc_html_e('Upgrade Plan', 'beepbeep-ai-alt-text-generator'); ?>
            </button>
            <p class="bbai-credit-upgrade-footer"><?php esc_html_e('£12.99/month • Cancel anytime', 'beepbeep-ai-alt-text-generator'); ?></p>
        </div>
    </div>

    <!-- 5. Usage activity section -->
    <?php
    $bbai_has_active_usage_filters = !empty($date_from) || !empty($date_to) || !empty($source) || $user_id > 0;
    ?>
    <details class="bbai-card bbai-credit-filters-card bbai-credit-usage-activity" <?php echo $bbai_has_active_usage_filters ? 'open' : ''; ?>>
        <summary class="bbai-credit-usage-activity-summary">
            <div class="bbai-credit-usage-activity-header">
                <span class="bbai-credit-usage-activity-title"><?php esc_html_e('Usage Activity', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
            </div>
            <p class="bbai-credit-usage-activity-desc"><?php esc_html_e('Track credit usage by date, source, and user.', 'beepbeep-ai-alt-text-generator'); ?></p>
        </summary>
        <div class="bbai-credit-usage-activity-body">
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
    </details>

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
</div>
