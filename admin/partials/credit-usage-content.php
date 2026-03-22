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
$bbai_usage_surface = isset($usage_surface) && is_array($usage_surface) ? $usage_surface : [];
$bbai_used = (int) ($bbai_usage_surface['creditsUsed'] ?? ($current_usage['used'] ?? 0));
$bbai_limit = max(1, (int) ($bbai_usage_surface['creditsLimit'] ?? ($current_usage['limit'] ?? 50)));
$bbai_remaining = (int) ($bbai_usage_surface['creditsRemaining'] ?? ($current_usage['remaining'] ?? 0));
$bbai_progress_pct = min(100, max(0, (int) ($bbai_usage_surface['usagePercent'] ?? 0)));
$bbai_days_reset = isset($bbai_usage_surface['daysUntilReset'])
    ? max(0, (int) $bbai_usage_surface['daysUntilReset'])
    : (isset($current_usage['days_until_reset']) && is_numeric($current_usage['days_until_reset']) ? max(0, (int) $current_usage['days_until_reset']) : 0);
$bbai_reset_date = $current_usage['reset_date'] ?? '';
$bbai_plan_label = (string) ($bbai_usage_surface['planLabel'] ?? ucfirst((string) ($current_usage['plan'] ?? 'free')));
$bbai_summary_tone = (string) ($bbai_usage_surface['summaryTone'] ?? 'healthy');
$bbai_insight_tone = (string) ($bbai_usage_surface['insightTone'] ?? 'healthy');
$bbai_has_usage_activity = !empty($bbai_usage_surface['hasUsageActivity']);
$bbai_has_filtered_results = !empty($bbai_usage_surface['hasFilteredResults']);
$bbai_is_pro_plan = !empty($bbai_usage_surface['isProPlan']);
$bbai_has_active_usage_filters = !empty($date_from) || !empty($date_to) || !empty($source) || $user_id > 0;
$bbai_usage_activity_items = isset($usage_activity['items']) && is_array($usage_activity['items']) ? $usage_activity['items'] : [];
$bbai_activity_total = (int) ($usage_activity['total'] ?? 0);
$bbai_activity_pages = max(0, (int) ($usage_activity['pages'] ?? 0));
$bbai_activity_per_page = max(1, (int) ($usage_activity['per_page'] ?? 20));
$bbai_activity_start = $bbai_activity_total > 0 ? (($page - 1) * $bbai_activity_per_page) + 1 : 0;
$bbai_activity_end = $bbai_activity_total > 0 ? min($bbai_activity_total, $bbai_activity_start + count($bbai_usage_activity_items) - 1) : 0;
$bbai_remaining_pct = $bbai_limit > 0 ? ($bbai_remaining / $bbai_limit) * 100 : 100;
$bbai_remaining_class = 'bbai-credit-stat-value--success';
if ($bbai_remaining_pct <= 10) {
    $bbai_remaining_class = 'bbai-credit-stat-value--danger';
} elseif ($bbai_remaining_pct <= 30) {
    $bbai_remaining_class = 'bbai-credit-stat-value--warning';
}

$bbai_activity_base_url = add_query_arg(
    array_filter(
        [
            'page'      => 'bbai-credit-usage',
            'date_from' => $date_from ?: null,
            'date_to'   => $date_to ?: null,
            'source'    => $source ?: null,
            'user_id'   => $user_id > 0 ? $user_id : null,
            'view'      => $view !== 'summary' ? $view : null,
        ],
        static function ($value) {
            return null !== $value && '' !== $value;
        }
    ),
    admin_url('admin.php')
);

$bbai_activity_pagination = $bbai_activity_pages > 1
    ? paginate_links(
        [
            'base'      => add_query_arg('paged', '%#%', $bbai_activity_base_url),
            'format'    => '',
            'current'   => max(1, $page),
            'total'     => $bbai_activity_pages,
            'type'      => 'plain',
            'prev_text' => __('Previous', 'beepbeep-ai-alt-text-generator'),
            'next_text' => __('Next', 'beepbeep-ai-alt-text-generator'),
        ]
    )
    : '';
?>
<div class="bbai-credit-usage-page">
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
                <?php esc_html_e('Track credits, generation activity, and plan usage across your site. Use filters to review usage patterns over time.', 'beepbeep-ai-alt-text-generator'); ?>
            </p>
        </div>
    </div>

    <!-- 2. Credit usage meter -->
    <div class="bbai-card bbai-credit-usage-meter-card bbai-credit-usage-meter-card--<?php echo esc_attr($bbai_summary_tone); ?>">
        <h3 class="bbai-credit-meter-title"><?php esc_html_e('Usage Summary', 'beepbeep-ai-alt-text-generator'); ?></h3>
        <?php if ($bbai_remaining === 0) : ?>
            <div class="bbai-limit-banner">
                <strong><?php esc_html_e('Credits exhausted', 'beepbeep-ai-alt-text-generator'); ?></strong>
                <span><?php echo esc_html($bbai_usage_surface['insightCopy'] ?? ''); ?></span>
            </div>
        <?php endif; ?>
        <div class="bbai-credit-bar bbai-credit-bar--<?php echo esc_attr($bbai_summary_tone); ?>" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_progress_pct); ?>" aria-valuemin="0" aria-valuemax="100">
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
        <div class="bbai-credit-summary-copy">
            <p class="bbai-credit-summary-line bbai-credit-summary-line--<?php echo esc_attr($bbai_summary_tone); ?>">
                <?php echo esc_html($bbai_usage_surface['summaryPositionCopy'] ?? ''); ?>
            </p>
            <div class="bbai-credit-summary-meta" aria-label="<?php esc_attr_e('Current usage details', 'beepbeep-ai-alt-text-generator'); ?>">
                <span class="bbai-credit-summary-pill"><?php echo esc_html($bbai_usage_surface['summaryRemainingCopy'] ?? ''); ?></span>
                <span class="bbai-credit-summary-pill"><?php echo esc_html($bbai_usage_surface['summaryResetCopy'] ?? ''); ?></span>
            </div>
            <div class="bbai-credit-insight bbai-credit-insight--<?php echo esc_attr($bbai_insight_tone); ?>">
                <span class="bbai-credit-insight-label"><?php esc_html_e('Usage insight', 'beepbeep-ai-alt-text-generator'); ?></span>
                <p><?php echo esc_html($bbai_usage_surface['insightCopy'] ?? ''); ?></p>
            </div>
            <p class="bbai-credit-automation-note">
                <?php echo esc_html($bbai_usage_surface['automationCopy'] ?? ''); ?>
            </p>
        </div>
    </div>

    <!-- 3. Stats cards + 4. Upgrade card -->
    <div class="bbai-credit-stats-upgrade-grid">
        <div class="bbai-credit-stats-grid">
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-credit-stat-value"><?php echo esc_html($bbai_plan_label); ?></span>
            </div>
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></span>
                <span class="bbai-credit-stat-value"><?php echo esc_html(number_format_i18n($bbai_used) . ' / ' . number_format_i18n($bbai_limit)); ?></span>
            </div>
            <div class="bbai-card bbai-credit-stat-card">
                <span class="bbai-credit-stat-title"><?php esc_html_e('Credits Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
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
            <p class="bbai-credit-upgrade-eyebrow"><?php echo esc_html($bbai_usage_surface['upgradeEyebrow'] ?? ''); ?></p>
            <p class="bbai-credit-upgrade-context"><?php echo esc_html($bbai_usage_surface['upgradeContext'] ?? ''); ?></p>
            <h3 class="bbai-credit-upgrade-title"><?php echo esc_html($bbai_usage_surface['upgradeTitle'] ?? ''); ?></h3>
            <p class="bbai-credit-upgrade-desc">
                <?php echo esc_html($bbai_usage_surface['upgradeDescription'] ?? ''); ?>
            </p>
            <ul class="bbai-credit-upgrade-features">
                <?php foreach (($bbai_usage_surface['upgradeBenefits'] ?? []) as $bbai_benefit) : ?>
                    <li><?php echo esc_html($bbai_benefit); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (($bbai_usage_surface['upgradeCtaType'] ?? '') === 'link') : ?>
                <a href="<?php echo esc_url($bbai_usage_surface['upgradeCtaUrl'] ?? ''); ?>" class="bbai-btn bbai-btn-primary bbai-credit-upgrade-cta">
                    <?php echo esc_html($bbai_usage_surface['upgradeCtaLabel'] ?? ''); ?>
                </a>
            <?php else : ?>
                <button type="button" class="bbai-btn bbai-btn-primary bbai-credit-upgrade-cta" data-action="show-upgrade-modal">
                    <?php echo esc_html($bbai_usage_surface['upgradeCtaLabel'] ?? ''); ?>
                </button>
            <?php endif; ?>
            <p class="bbai-credit-upgrade-footer"><?php echo esc_html($bbai_usage_surface['upgradeFooter'] ?? ''); ?></p>
        </div>
    </div>

    <!-- 5. Usage activity section -->
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
                            <?php foreach (($source_options ?? []) as $bbai_source_value => $bbai_source_label) : ?>
                                <option value="<?php echo esc_attr($bbai_source_value); ?>" <?php selected($source, $bbai_source_value); ?>>
                                    <?php echo esc_html($bbai_source_label); ?>
                                </option>
                            <?php endforeach; ?>
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
            <div class="bbai-credit-activity-shell">
                <?php if (!$bbai_has_usage_activity) : ?>
                    <div class="bbai-credit-activity-state bbai-credit-activity-state--empty">
                        <div class="bbai-credit-activity-state__copy">
                            <h4 class="bbai-credit-activity-state__title"><?php esc_html_e('No usage yet', 'beepbeep-ai-alt-text-generator'); ?></h4>
                            <p class="bbai-credit-activity-state__description"><?php esc_html_e('Credit activity will appear here when images are scanned, generated, or reviewed.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <div class="bbai-credit-activity-state__actions">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-bbai-action="scan-opportunity"><?php esc_html_e('Scan Media Library', 'beepbeep-ai-alt-text-generator'); ?></button>
                            <?php if ($bbai_is_pro_plan) : ?>
                                <a href="<?php echo esc_url($bbai_usage_surface['automationUrl'] ?? ''); ?>" class="bbai-btn bbai-btn-secondary"><?php esc_html_e('Automation settings', 'beepbeep-ai-alt-text-generator'); ?></a>
                            <?php else : ?>
                                <button type="button" class="bbai-btn bbai-btn-secondary" data-action="show-upgrade-modal"><?php esc_html_e('Enable auto-optimization', 'beepbeep-ai-alt-text-generator'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (!$bbai_has_filtered_results) : ?>
                    <div class="bbai-credit-activity-state bbai-credit-activity-state--empty">
                        <div class="bbai-credit-activity-state__copy">
                            <h4 class="bbai-credit-activity-state__title"><?php esc_html_e('No matching activity found', 'beepbeep-ai-alt-text-generator'); ?></h4>
                            <p class="bbai-credit-activity-state__description"><?php esc_html_e('Try a different date range, source, or user filter.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <div class="bbai-credit-activity-state__actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-btn bbai-btn-secondary"><?php esc_html_e('Clear filters', 'beepbeep-ai-alt-text-generator'); ?></a>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="bbai-usage-table-card bbai-credit-activity-results">
                        <div class="bbai-credit-activity-results-header">
                            <div>
                                <h4 class="bbai-card-title"><?php esc_html_e('Recent credit activity', 'beepbeep-ai-alt-text-generator'); ?></h4>
                                <p class="bbai-credit-activity-results-copy">
                                    <?php
                                    printf(
                                        /* translators: 1: first visible row number, 2: last visible row number, 3: total activity row count. */
                                        esc_html__('Showing %1$s-%2$s of %3$s logged credit events.', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_activity_start)),
                                        esc_html(number_format_i18n($bbai_activity_end)),
                                        esc_html(number_format_i18n($bbai_activity_total))
                                    );
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="bbai-credit-activity-table-wrap">
                            <table class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Date', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('Action / Source', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('Credits Used', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('Related Item', 'beepbeep-ai-alt-text-generator'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bbai_usage_activity_items as $bbai_activity_item) : ?>
                                        <?php
                                        $bbai_activity_timestamp = !empty($bbai_activity_item['generated_at']) ? strtotime($bbai_activity_item['generated_at']) : false;
                                        $bbai_related_label = $bbai_activity_item['item_label'] ?? '';
                                        $bbai_related_meta = $bbai_activity_item['item_meta'] ?? '';
                                        $bbai_related_url = $bbai_activity_item['item_url'] ?? '';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($bbai_activity_timestamp) : ?>
                                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $bbai_activity_timestamp)); ?>
                                                <?php else : ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($bbai_activity_item['source_label'] ?? __('Manual', 'beepbeep-ai-alt-text-generator')); ?></strong>
                                                <?php if (!empty($bbai_activity_item['model'])) : ?>
                                                    <div class="bbai-table-subtitle">
                                                        <?php
                                                        printf(
                                                            /* translators: %s: model name. */
                                                            esc_html__('Model: %s', 'beepbeep-ai-alt-text-generator'),
                                                            esc_html($bbai_activity_item['model'])
                                                        );
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="bbai-credit-activity-credits"><?php echo esc_html(number_format_i18n((int) ($bbai_activity_item['credits_used'] ?? 0))); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($bbai_activity_item['display_name'] ?? __('System', 'beepbeep-ai-alt-text-generator')); ?></strong>
                                                <?php if (!empty($bbai_activity_item['user_email'])) : ?>
                                                    <div class="bbai-table-subtitle"><?php echo esc_html($bbai_activity_item['user_email']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($bbai_related_label) && !empty($bbai_related_url)) : ?>
                                                    <a href="<?php echo esc_url($bbai_related_url); ?>" class="bbai-credit-activity-link"><?php echo esc_html($bbai_related_label); ?></a>
                                                <?php elseif (!empty($bbai_related_label)) : ?>
                                                    <span><?php echo esc_html($bbai_related_label); ?></span>
                                                <?php else : ?>
                                                    <span>&mdash;</span>
                                                <?php endif; ?>
                                                <?php if (!empty($bbai_related_meta)) : ?>
                                                    <div class="bbai-table-subtitle"><?php echo esc_html($bbai_related_meta); ?></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (!empty($bbai_activity_pagination)) : ?>
                            <nav class="bbai-credit-activity-pagination" aria-label="<?php esc_attr_e('Usage activity pages', 'beepbeep-ai-alt-text-generator'); ?>">
                                <?php echo wp_kses_post($bbai_activity_pagination); ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
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
