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
$bbai_used          = (int) ($bbai_usage_surface['creditsUsed']      ?? ($current_usage['used']      ?? 0));
$bbai_limit         = max(1, (int) ($bbai_usage_surface['creditsLimit'] ?? ($current_usage['limit'] ?? 50)));
$bbai_remaining     = (int) ($bbai_usage_surface['creditsRemaining'] ?? ($current_usage['remaining'] ?? 0));
$bbai_progress_pct  = min(100, max(0, (int) ($bbai_usage_surface['usagePercent'] ?? 0)));
$bbai_reset_date        = $current_usage['reset_date'] ?? '';
$bbai_has_usage_activity    = !empty($bbai_usage_surface['hasUsageActivity']);
$bbai_has_filtered_results  = !empty($bbai_usage_surface['hasFilteredResults']);
$bbai_is_pro_plan           = !empty($bbai_usage_surface['isProPlan']);
$bbai_has_active_usage_filters = !empty($date_from) || !empty($date_to) || !empty($source) || $user_id > 0;
$bbai_usage_activity_items  = isset($usage_activity['items']) && is_array($usage_activity['items']) ? $usage_activity['items'] : [];
$bbai_activity_total    = (int) ($usage_activity['total']    ?? 0);
$bbai_activity_pages    = max(0, (int) ($usage_activity['pages']    ?? 0));
$bbai_activity_per_page = max(1, (int) ($usage_activity['per_page'] ?? 20));
$bbai_activity_start    = $bbai_activity_total > 0 ? (($page - 1) * $bbai_activity_per_page) + 1 : 0;
$bbai_activity_end      = $bbai_activity_total > 0 ? min($bbai_activity_total, $bbai_activity_start + count($bbai_usage_activity_items) - 1) : 0;

$bbai_summary_reset_copy = (string) ($bbai_usage_surface['summaryResetCopy'] ?? '');
$bbai_surf_plan_label    = (string) ($bbai_usage_surface['planLabel'] ?? '');

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-upgrade-state.php';

$bbai_activity_base_url = add_query_arg(
    array_filter(
        [
            'page'      => 'bbai-credit-usage',
            'date_from' => $date_from ?: null,
            'date_to'   => $date_to   ?: null,
            'source'    => $source    ?: null,
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

// Estimate time saved: ~2 minutes per image optimised.
$bbai_time_saved_min = $bbai_used * 2;
if ($bbai_time_saved_min >= 120) {
    $bbai_time_saved_label = sprintf(
        /* translators: %s: hours saved as decimal */
        __('~%s hours saved', 'beepbeep-ai-alt-text-generator'),
        number_format_i18n(round($bbai_time_saved_min / 60, 1))
    );
} elseif ($bbai_time_saved_min >= 60) {
    $bbai_time_saved_label = __('~1 hour saved', 'beepbeep-ai-alt-text-generator');
} else {
    $bbai_time_saved_label = sprintf(
        /* translators: %d: minutes saved */
        __('~%d minutes saved', 'beepbeep-ai-alt-text-generator'),
        max(1, $bbai_time_saved_min)
    );
}

$bbai_automation_url  = (string) ($bbai_usage_surface['automationUrl'] ?? '');
$bbai_upgrade_cta_url = (string) ($bbai_usage_surface['upgradeCtaUrl'] ?? '');
$bbai_plan_url        = !empty($bbai_upgrade_cta_url) ? $bbai_upgrade_cta_url : 'https://beepbeep.ai/pricing';
$bbai_usage_billing_portal = class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')
    ? (string) \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url()
    : '';
$bbai_usage_upgrade_url = class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')
    ? (string) \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_upgrade_url()
    : '';

$bbai_usage_insights = isset($usage_insights) && is_array($usage_insights) ? $usage_insights : [];
$bbai_ins_forecast   = (string) ($bbai_usage_insights['forecast'] ?? '');
$bbai_ins_driver     = (string) ($bbai_usage_insights['driver'] ?? '');
$bbai_ins_recommend  = (string) ($bbai_usage_insights['recommend'] ?? '');
$bbai_ins_tone       = (string) ($bbai_usage_insights['tone'] ?? 'healthy');
if (!in_array($bbai_ins_tone, ['healthy', 'warning', 'danger'], true)) {
    $bbai_ins_tone = 'healthy';
}
$bbai_ins_chips = isset($bbai_usage_insights['chips']) && is_array($bbai_usage_insights['chips']) ? $bbai_usage_insights['chips'] : [];
?>

<div class="bbai-container bbai-usage-workspace bbai-ui-page-shell bbai-credit-usage-page">

    <?php if ($view === 'user_detail' && $user_id > 0) : ?>
        <div class="bbai-usage-back-wrap">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-back-btn">
                <?php esc_html_e('← Back to Summary', 'beepbeep-ai-alt-text-generator'); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/page-hero.php';
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';
    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
    $bbai_usage_banner_missing = (int) ( $bbai_usage_surface['bannerMissingCount'] ?? $bbai_usage_surface['missingCount'] ?? 0 );
    $bbai_usage_banner_weak    = (int) ( $bbai_usage_surface['bannerWeakCount'] ?? $bbai_usage_surface['weakCount'] ?? 0 );
    $bbai_usage_banner_total   = (int) ( $bbai_usage_surface['bannerTotalImages'] ?? 0 );
    $bbai_command_hero = bbai_page_hero_usage_command_hero(
        [
            'billing_portal_url'  => $bbai_usage_billing_portal,
            'upgrade_url'         => $bbai_usage_upgrade_url,
            'missing_count'       => $bbai_usage_banner_missing,
            'weak_count'          => $bbai_usage_banner_weak,
            'total_images'        => $bbai_usage_banner_total,
            'credits_used'        => $bbai_used,
            'credits_limit'       => $bbai_limit,
            'credits_remaining'   => $bbai_remaining,
            'usage_percent'       => $bbai_progress_pct,
            'is_pro_plan'         => $bbai_is_pro_plan,
            'is_low_credits'      => $bbai_remaining > 0 && $bbai_remaining <= BBAI_BANNER_LOW_CREDITS_THRESHOLD,
            'is_out_of_credits'   => $bbai_remaining <= 0,
            'is_first_run'        => false,
            'plan_label'          => (string) ( $bbai_usage_surface['planLabel'] ?? '' ),
            'library_url'         => admin_url( 'admin.php?page=bbai-library' ),
            'missing_library_url' => add_query_arg( [ 'page' => 'bbai-library', 'status' => 'missing' ], admin_url( 'admin.php' ) ),
            'needs_review_library_url' => bbai_alt_library_needs_review_url(),
            'usage_url'           => admin_url( 'admin.php?page=bbai-credit-usage' ),
            'settings_url'        => admin_url( 'admin.php?page=bbai-settings' ),
            'guide_url'           => admin_url( 'admin.php?page=bbai-guide' ),
        ]
    );
    bbai_ui_render('bbai-banner', [ 'command_hero' => $bbai_command_hero ]);
    ?>

    <div class="bbai-usage-after-banner" id="bbai-usage-overview">
        <?php
        $bbai_ins_stack_lines = [
            __('How your credits are tracking this cycle and what to do next.', 'beepbeep-ai-alt-text-generator'),
        ];
        if ('' !== trim($bbai_ins_forecast)) {
            $bbai_ins_stack_lines[] = sprintf(
                /* translators: 1: section label, 2: insight text */
                __('%1$s — %2$s', 'beepbeep-ai-alt-text-generator'),
                __('Forecast', 'beepbeep-ai-alt-text-generator'),
                $bbai_ins_forecast
            );
        }
        if ('' !== trim($bbai_ins_driver)) {
            $bbai_ins_stack_lines[] = sprintf(
                /* translators: 1: section label, 2: insight text */
                __('%1$s — %2$s', 'beepbeep-ai-alt-text-generator'),
                __('What is driving usage', 'beepbeep-ai-alt-text-generator'),
                $bbai_ins_driver
            );
        }
        if ('' !== trim($bbai_ins_recommend)) {
            $bbai_ins_stack_lines[] = sprintf(
                /* translators: 1: section label, 2: insight text */
                __('%1$s — %2$s', 'beepbeep-ai-alt-text-generator'),
                __('Suggested next step', 'beepbeep-ai-alt-text-generator'),
                $bbai_ins_recommend
            );
        }

        require BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/components/usage-insights-card.php';
        ?>
    </div>

    <section class="bbai-usage-activity-section bbai-card" id="bbai-usage-activity" aria-labelledby="bbai-usage-activity-heading">
        <header class="bbai-usage-activity-section__header bbai-ui-section-header bbai-section-header">
            <div class="bbai-ui-section-header__text">
                <p class="bbai-section-label bbai-card-label"><?php esc_html_e('Usage', 'beepbeep-ai-alt-text-generator'); ?></p>
                <h2 id="bbai-usage-activity-heading" class="bbai-usage-activity-section__heading bbai-section-title bbai-card-title"><?php esc_html_e('Usage activity', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <p class="bbai-usage-activity-section__lead bbai-section-description bbai-card-description"><?php esc_html_e('Operational detail: every logged credit event. Filter by date, source, or user to find what consumed your quota fastest.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </header>

        <div class="bbai-usage-activity-section__body">

            <!-- Filter grid -->
            <form method="get" class="bbai-credit-filter-form">
                <input type="hidden" name="page" value="bbai-credit-usage" />
                <div class="bbai-credit-filter-row">
                    <div class="bbai-credit-filter-field">
                        <label class="bbai-credit-filter-label" for="bbai-date-from"><?php esc_html_e('Date From', 'beepbeep-ai-alt-text-generator'); ?></label>
                        <div class="bbai-credit-filter-input-wrapper">
                            <input type="date" id="bbai-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="bbai-credit-filter-input bbai-input" placeholder="mm/dd/yyyy">
                            <svg class="bbai-credit-filter-calendar-icon" width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="bbai-credit-filter-field">
                        <label class="bbai-credit-filter-label" for="bbai-date-to"><?php esc_html_e('Date To', 'beepbeep-ai-alt-text-generator'); ?></label>
                        <div class="bbai-credit-filter-input-wrapper">
                            <input type="date" id="bbai-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="bbai-credit-filter-input bbai-input" placeholder="mm/dd/yyyy">
                            <svg class="bbai-credit-filter-calendar-icon" width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <rect x="2" y="3" width="12" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M5 1V4M11 1V4M2 7H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="bbai-credit-filter-field">
                        <label class="bbai-credit-filter-label" for="bbai-source-filter"><?php esc_html_e('Source', 'beepbeep-ai-alt-text-generator'); ?></label>
                        <select id="bbai-source-filter" name="source" class="bbai-credit-filter-select bbai-select">
                            <?php foreach (($source_options ?? []) as $bbai_source_value => $bbai_source_label) : ?>
                                <option value="<?php echo esc_attr($bbai_source_value); ?>" <?php selected($source, $bbai_source_value); ?>>
                                    <?php echo esc_html($bbai_source_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bbai-credit-filter-field">
                        <label class="bbai-credit-filter-label" for="bbai-user-filter"><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></label>
                        <select id="bbai-user-filter" name="user_id" class="bbai-credit-filter-select bbai-select">
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
                    <button type="submit" class="bbai-btn bbai-btn-primary bbai-btn-sm"><?php esc_html_e('Apply filters', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-clear-filter-link bbai-link bbai-link--muted"><?php esc_html_e('Clear', 'beepbeep-ai-alt-text-generator'); ?></a>
                </div>
            </form>

            <!-- Activity results -->
            <div class="bbai-credit-activity-shell">
                <?php if (!$bbai_has_usage_activity) : ?>
                    <?php
                    ob_start();
                    ?>
                    <button type="button" class="bbai-btn bbai-btn-primary bbai-btn-sm" data-bbai-action="scan-opportunity"><?php echo esc_html(bbai_copy_cta_scan_media_library()); ?></button>
                    <?php if ($bbai_is_pro_plan) : ?>
                        <a href="<?php echo esc_url($bbai_automation_url); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Automation settings', 'beepbeep-ai-alt-text-generator'); ?></a>
                    <?php else : ?>
                        <button type="button" class="bbai-btn bbai-btn-secondary bbai-btn-sm" data-action="show-upgrade-modal"><?php esc_html_e('Enable auto-optimisation', 'beepbeep-ai-alt-text-generator'); ?></button>
                    <?php endif; ?>
                    <?php
                    $bbai_usage_empty_actions = ob_get_clean();
                    bbai_ui_render(
                        'product-state',
                        [
                            'variant'      => 'empty',
                            'title'        => __('No usage yet', 'beepbeep-ai-alt-text-generator'),
                            'body'         => __('Credit activity will appear here when images are scanned, generated, or reviewed.', 'beepbeep-ai-alt-text-generator'),
                            'actions_html' => $bbai_usage_empty_actions,
                            'root_class'   => 'bbai-credit-activity-state',
                        ]
                    );
                    ?>
                <?php elseif (!$bbai_has_filtered_results) : ?>
                    <?php
                    ob_start();
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-credit-usage')); ?>" class="bbai-btn bbai-btn-secondary bbai-btn-sm"><?php esc_html_e('Clear filters', 'beepbeep-ai-alt-text-generator'); ?></a>
                    <?php
                    $bbai_usage_filter_empty_actions = ob_get_clean();
                    bbai_ui_render(
                        'product-state',
                        [
                            'variant'      => 'empty',
                            'title'        => __('No matching activity found', 'beepbeep-ai-alt-text-generator'),
                            'body'         => __('Try a different date range, source, or user filter.', 'beepbeep-ai-alt-text-generator'),
                            'actions_html' => $bbai_usage_filter_empty_actions,
                            'root_class'   => 'bbai-credit-activity-state',
                        ]
                    );
                    ?>
                <?php else : ?>
                    <div class="bbai-usage-table-card bbai-credit-activity-results">
                        <div class="bbai-credit-activity-results-header">
                            <div>
                                <h3 class="bbai-card-title"><?php esc_html_e('Recent credit activity', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <p class="bbai-credit-activity-results-copy">
                                    <?php
                                    printf(
                                        /* translators: 1: first visible row number, 2: last visible row number, 3: total activity row count. */
                                        esc_html__('Showing %1$s–%2$s of %3$s logged credit events.', 'beepbeep-ai-alt-text-generator'),
                                        esc_html(number_format_i18n($bbai_activity_start)),
                                        esc_html(number_format_i18n($bbai_activity_end)),
                                        esc_html(number_format_i18n($bbai_activity_total))
                                    );
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="bbai-credit-activity-table-wrap">
                            <table class="widefat fixed striped bbai-usage-table">
                                <caption class="screen-reader-text"><?php esc_html_e('Credit usage log for the current filters', 'beepbeep-ai-alt-text-generator'); ?></caption>
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
                                        $bbai_related_meta  = $bbai_activity_item['item_meta']  ?? '';
                                        $bbai_related_url   = $bbai_activity_item['item_url']   ?? '';
                                        ?>
                                        <tr
                                            class="bbai-usage-table__row<?php echo !empty($bbai_related_url) ? ' is-clickable' : ''; ?>"
                                            <?php if (!empty($bbai_related_url)) : ?>
                                                data-href="<?php echo esc_url($bbai_related_url); ?>"
                                                tabindex="0"
                                                role="link"
                                                aria-label="<?php echo esc_attr(sprintf(
                                                    /* translators: %s: related item label. */
                                                    __('Open %s', 'beepbeep-ai-alt-text-generator'),
                                                    $bbai_related_label ?: __('related item', 'beepbeep-ai-alt-text-generator')
                                                )); ?>"
                                            <?php endif; ?>
                                        >
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
            </div><!-- /.bbai-credit-activity-shell -->

        </div><!-- /.bbai-usage-activity-section__body -->
    </section><!-- /.bbai-usage-activity-section -->

    <section class="bbai-usage-billing" aria-labelledby="bbai-usage-billing-heading">
        <h2 id="bbai-usage-billing-heading" class="bbai-usage-billing__title bbai-ui-section-header__title bbai-section-title"><?php esc_html_e('Billing & cycle', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <dl class="bbai-usage-billing__grid">
            <div class="bbai-usage-billing__item">
                <dt><?php esc_html_e('Plan', 'beepbeep-ai-alt-text-generator'); ?></dt>
                <dd><?php echo '' !== $bbai_surf_plan_label ? esc_html($bbai_surf_plan_label) : esc_html('—'); ?></dd>
            </div>
            <div class="bbai-usage-billing__item">
                <dt><?php esc_html_e('Cycle reset', 'beepbeep-ai-alt-text-generator'); ?></dt>
                <dd><?php echo '' !== $bbai_summary_reset_copy ? esc_html($bbai_summary_reset_copy) : esc_html('—'); ?></dd>
            </div>
            <?php if (!empty($backend_user_activity['period_start']) && !empty($backend_user_activity['period_end'])) : ?>
                <div class="bbai-usage-billing__item">
                    <dt><?php esc_html_e('Reporting period', 'beepbeep-ai-alt-text-generator'); ?></dt>
                    <dd>
                        <?php
                        printf(
                            /* translators: 1: period start date, 2: period end date */
                            esc_html__('%1$s – %2$s', 'beepbeep-ai-alt-text-generator'),
                            esc_html(date_i18n(get_option('date_format'), strtotime($backend_user_activity['period_start']))),
                            esc_html(date_i18n(get_option('date_format'), strtotime($backend_user_activity['period_end'])))
                        );
                        ?>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>
        <?php if ($bbai_used > 0) : ?>
            <p class="bbai-usage-billing__note">
                <?php
                printf(
                    /* translators: %s: estimated time saved label, e.g. "~1 hour" */
                    esc_html__('Approximate manual editing time offset this cycle: %s.', 'beepbeep-ai-alt-text-generator'),
                    esc_html($bbai_time_saved_label)
                );
                ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($bbai_usage_billing_portal) || !$bbai_is_pro_plan) : ?>
        <div class="bbai-usage-billing__links">
            <?php if (!empty($bbai_usage_billing_portal)) : ?>
                <a href="<?php echo esc_url($bbai_usage_billing_portal); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Customer portal & invoices', 'beepbeep-ai-alt-text-generator'); ?></a>
            <?php endif; ?>
            <?php if (!$bbai_is_pro_plan) : ?>
                <a href="<?php echo esc_url($bbai_plan_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Plans & pricing', 'beepbeep-ai-alt-text-generator'); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($view === 'user_detail' && $user_details) : ?>
    <div class="bbai-usage-supplemental">
        <div class="bbai-card bbai-user-details-card">
            <h2 class="bbai-card-title"><?php esc_html_e('User details', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <p><strong><?php esc_html_e('Name:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['name'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Email:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html($user_details['email'] ?? ''); ?></p>
            <p><strong><?php esc_html_e('Total credits used:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['total_credits'] ?? 0)); ?></p>
            <p><strong><?php esc_html_e('Images processed:', 'beepbeep-ai-alt-text-generator'); ?></strong> <?php echo esc_html(number_format_i18n($user_details['images_processed'] ?? 0)); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'summary' && !empty($backend_user_activity['users'])) : ?>
    <section class="bbai-usage-contributors" aria-labelledby="bbai-usage-contributors-heading">
        <h2 id="bbai-usage-contributors-heading" class="bbai-usage-contributors__title bbai-section-title"><?php esc_html_e('Top contributors', 'beepbeep-ai-alt-text-generator'); ?></h2>
        <p class="bbai-usage-contributors__lead bbai-ui-section-header__description"><?php esc_html_e('Users who consumed the most credits in the current reporting period.', 'beepbeep-ai-alt-text-generator'); ?></p>
        <table class="widefat fixed striped">
            <caption class="screen-reader-text"><?php esc_html_e('Top contributors by credits used this period', 'beepbeep-ai-alt-text-generator'); ?></caption>
            <thead>
                <tr>
                    <th scope="col" style="width: 4rem;"><?php esc_html_e('#', 'beepbeep-ai-alt-text-generator'); ?></th>
                    <th scope="col"><?php esc_html_e('User', 'beepbeep-ai-alt-text-generator'); ?></th>
                    <th scope="col"><?php esc_html_e('Credits used', 'beepbeep-ai-alt-text-generator'); ?></th>
                    <th scope="col"><?php esc_html_e('Last activity', 'beepbeep-ai-alt-text-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $bbai_rank = 1;
                foreach ($backend_user_activity['users'] as $bbai_hero) :
                    $bbai_hero_name  = $bbai_hero['display_name'] ?? $bbai_hero['user_name'] ?? $bbai_hero['name'] ?? $bbai_hero['user_email'] ?? $bbai_hero['email'] ?? __('Unknown user', 'beepbeep-ai-alt-text-generator');
                    $bbai_hero_email = $bbai_hero['user_email'] ?? $bbai_hero['email'] ?? '';
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $bbai_rank); ?></td>
                        <td>
                            <strong><?php echo esc_html($bbai_hero_name); ?></strong>
                            <?php if (!empty($bbai_hero_email) && $bbai_hero_email !== $bbai_hero_name) : ?>
                                <div class="bbai-table-subtitle"><?php echo esc_html($bbai_hero_email); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(number_format_i18n($bbai_hero['credits_used'] ?? 0)); ?></td>
                        <td>
                            <?php
                            $bbai_last_activity = $bbai_hero['last_activity'] ?? null;
                            if ($bbai_last_activity) {
                                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($bbai_last_activity)));
                            } else {
                                echo '&mdash;';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                    ++$bbai_rank;
                endforeach;
                ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

</div><!-- /.bbai-usage-workspace -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.bbai-usage-table__row.is-clickable').forEach(function(row) {
        var href = row.getAttribute('data-href');
        if (!href) {
            return;
        }

        row.addEventListener('click', function(event) {
            if (event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }
            window.location.href = href;
        });

        row.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                window.location.href = href;
            }
        });
    });
});
</script>
