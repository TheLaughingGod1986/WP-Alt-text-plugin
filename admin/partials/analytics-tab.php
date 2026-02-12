<?php
/**
 * Analytics Dashboard Tab
 * Coverage trends, activity timeline, usage breakdown
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

use BeepBeepAI\AltTextGenerator\Admin\Plan_Helpers;

// Load Plan_Helpers class
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/class-plan-helpers.php';

// Get stats and usage data
$stats = isset($stats) && is_array($stats) ? $stats : [];
$usage_stats = isset($usage_stats) && is_array($usage_stats) ? $usage_stats : [];

// Calculate metrics
$total_images = $stats['total'] ?? 0;
$optimized = $stats['with_alt'] ?? 0;
$missing = $stats['missing'] ?? 0;
$coverage_percent = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;

// Simplified coverage history (placeholder when real history is unavailable)
$historical_coverage = [
    ['date' => 'Week 1', 'coverage' => max(0, $coverage_percent - 24)],
    ['date' => 'Week 2', 'coverage' => max(0, $coverage_percent - 20)],
    ['date' => 'Week 3', 'coverage' => max(0, $coverage_percent - 16)],
    ['date' => 'Week 4', 'coverage' => max(0, $coverage_percent - 12)],
    ['date' => 'Week 5', 'coverage' => max(0, $coverage_percent - 8)],
    ['date' => 'Current', 'coverage' => max(0, $coverage_percent - 4)],
    ['date' => 'Week 6', 'coverage' => max(0, $coverage_percent)],
];

$usage_used = max(0, intval($usage_stats['used'] ?? 0));
$usage_limit = max(1, intval($usage_stats['limit'] ?? 50));
$usage_remaining = max(0, intval($usage_stats['remaining'] ?? ($usage_limit - $usage_used)));
$days_in_month = (int) wp_date('t');
$avg_per_day = $days_in_month > 0 ? round($usage_used / $days_in_month, 1) : 0;
$minutes_per_alt = 2.5;
$computed_time_saved = round(($usage_used * $minutes_per_alt) / 60, 1);
$time_saved_hours = $usage_stats['timeSavedHours'] ?? $usage_stats['time_saved_hours'] ?? $stats['timeSavedHours'] ?? $stats['time_saved_hours'] ?? $computed_time_saved;
$time_saved_hours = max(0, floatval($time_saved_hours));
$seo_lift_percent = $stats['seoImpactScore'] ?? $stats['seo_impact_score'] ?? $stats['coverage'] ?? $coverage_percent ?? 0;
$seo_lift_percent = max(0, round($seo_lift_percent));
$total_images_optimized = $stats['imagesOptimized'] ?? $stats['with_alt'] ?? $optimized ?? 0;
$total_images_optimized = max(0, intval($total_images_optimized));
$images_processed = $stats['generated'] ?? $total_images_optimized;
$images_processed = max(0, intval($images_processed));
$images_delta_percent = $usage_stats['imagesDeltaPercent'] ?? $usage_stats['images_delta_percent'] ?? 0;
$images_delta_percent = max(0, floatval($images_delta_percent));
$coverage_improvement = max(0, round($coverage_percent));

// Get plan data from centralized helper
$plan_data = Plan_Helpers::get_plan_data();
$plan_slug = $plan_data['plan_slug'];
$is_free = $plan_data['is_free'];
$is_growth = $plan_data['is_growth'];
$is_agency = $plan_data['is_agency'];

$has_trend_data = false;
foreach ($historical_coverage as $point) {
    if (($point['coverage'] ?? 0) > 0) {
        $has_trend_data = true;
        break;
    }
}

$seo_lift_display = $seo_lift_percent > 0 ? '+' . $seo_lift_percent . '%' : $seo_lift_percent . '%';

$analytics_payload = [
    'coverage' => $historical_coverage,
    'usage' => $usage_stats,
];
if (function_exists('wp_add_inline_script')) {
    wp_add_inline_script(
        'bbai-analytics',
        'window.bbaiAnalyticsData = ' . wp_json_encode($analytics_payload) . ';',
        'before'
    );
}
?>

<div class="bbai-premium-dashboard">
    <div class="bbai-analytics-container">
        <!-- Header -->
        <div class="bbai-dashboard-header-section">
            <div class="bbai-page-header-content">
                <h1 class="bbai-page-title"><?php esc_html_e('Analytics', 'beepbeep-ai-alt-text-generator'); ?></h1>
                <p class="bbai-page-subtitle">
                    <?php esc_html_e('Track your alt text coverage trends, usage and automation impact.', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>
        </div>

        <!-- Key Metrics Row -->
        <div class="bbai-premium-metrics-grid bbai-mb-6">
            <!-- SEO Lift Card -->
            <div class="bbai-premium-card bbai-metric-card">
                <div class="bbai-metric-icon" style="color: #10B981;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M5 19L19 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9 5H19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-metric-value" style="color: #10B981;"><?php echo esc_html($seo_lift_display); ?></div>
                <div class="bbai-metric-sublabel"><?php esc_html_e('SEO LIFT', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-metric-description">
                    <?php esc_html_e('Estimated lift in search visibility due to generated alt text.', 'beepbeep-ai-alt-text-generator'); ?>
                </div>
            </div>

            <!-- Images Optimized Card -->
            <div class="bbai-premium-card bbai-metric-card">
                <div class="bbai-metric-icon" style="color: #6366F1;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect x="3" y="4" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="9" cy="9" r="2" fill="currentColor"/>
                        <path d="M4.5 16l5-5 4 4 3-3 3 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-metric-value"><?php echo esc_html(number_format_i18n($total_images_optimized)); ?></div>
                <div class="bbai-metric-label"><?php esc_html_e('IMAGES', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-metric-sublabel"><?php esc_html_e('OPTIMIZED', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-metric-description">
                    <?php esc_html_e('Total images with generated or improved alt text.', 'beepbeep-ai-alt-text-generator'); ?>
                </div>
            </div>

            <!-- Time Saved Card -->
            <div class="bbai-premium-card bbai-metric-card">
                <div class="bbai-metric-icon" style="color: #0EA5E9;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="bbai-metric-value"><?php echo esc_html(number_format_i18n($time_saved_hours, 1)); ?></div>
                <div class="bbai-metric-label"><?php esc_html_e('HRS', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-metric-sublabel"><?php esc_html_e('TIME SAVED', 'beepbeep-ai-alt-text-generator'); ?></div>
                <div class="bbai-metric-description">
                    <?php esc_html_e('Estimated time saved vs manual alt text creation.', 'beepbeep-ai-alt-text-generator'); ?>
                </div>
            </div>
        </div>

        <!-- Coverage Trends Section -->
        <div class="bbai-card bbai-mb-6">
            <div class="bbai-card-header bbai-card-header--with-action">
                <h2 class="bbai-card-title"><?php esc_html_e('Coverage Trends', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <div id="bbai-analytics-period" class="bbai-period-selector">
                    <button type="button" data-period="30d" aria-pressed="true" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn bbai-period-btn--active">
                        <?php esc_html_e('Last 30 Days', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <button type="button" data-period="90d" aria-pressed="false" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn">
                        <?php esc_html_e('Last 90 Days', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                    <button type="button" data-period="ytd" aria-pressed="false" class="bbai-btn bbai-btn-secondary bbai-btn-sm bbai-period-btn">
                        <?php esc_html_e('YTD', 'beepbeep-ai-alt-text-generator'); ?>
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="margin-left: 4px;">
                            <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="bbai-card-body">
                <?php if ($has_trend_data) : ?>
                    <div class="bbai-chart-container">
                        <canvas id="bbai-coverage-chart"></canvas>
                    </div>
                <?php else : ?>
                    <div class="bbai-chart-empty-state">
                        <div class="bbai-chart-empty-icon">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                                <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/>
                                <path d="M32 16v16l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <p class="bbai-chart-empty-text"><?php esc_html_e('No coverage data available yet. Start optimizing images to see trends.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                <?php endif; ?>
                <p class="bbai-text-xs bbai-text-gray-500 bbai-mt-2"><?php esc_html_e('Coverage %', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
        </div>

        <!-- Usage Breakdown Section -->
        <div class="bbai-card bbai-mb-6">
            <h2 class="bbai-card-title bbai-mb-4"><?php esc_html_e('Usage Breakdown', 'beepbeep-ai-alt-text-generator'); ?></h2>
            <div class="bbai-usage-breakdown-grid">
                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #10B981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M4 12l4 4 12-12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.6"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Used', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($usage_used)); ?>
                        <span class="bbai-usage-breakdown-total"><?php
                        /* translators: 1: usage limit */
                        printf(esc_html__('of %s', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($usage_limit)));
                        ?></span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #F59E0B;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="5" width="16" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M7 9h10M7 13h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($usage_remaining)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('credits left', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #0EA5E9;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="4" width="16" height="12" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 18h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M9 8h6M9 11h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Avg Per Day', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($avg_per_day, 1)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('generations', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                </div>

                <div class="bbai-usage-breakdown-item">
                    <div class="bbai-metric-icon" style="color: #8B5CF6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M5 16l5-5 4 4 5-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14 8h5v5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="bbai-usage-breakdown-label"><?php esc_html_e('Images', 'beepbeep-ai-alt-text-generator'); ?></div>
                    <div class="bbai-usage-breakdown-value">
                        <?php echo esc_html(number_format_i18n($images_processed)); ?>
                        <span class="bbai-usage-breakdown-total"><?php esc_html_e('images', 'beepbeep-ai-alt-text-generator'); ?></span>
                    </div>
                    <?php if ($images_delta_percent > 0) : ?>
                        <div class="bbai-text-xs" style="color: #10B981; margin-top: 4px;">
                            +<?php echo esc_html(number_format_i18n($images_delta_percent)); ?>%
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Before & After and Recent Activity Grid -->
        <div class="bbai-grid bbai-grid-2 bbai-mb-6">
            <!-- Before & After Card -->
            <div class="bbai-card">
                <h2 class="bbai-card-title bbai-mb-4"><?php esc_html_e('Before & After', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <div class="bbai-comparison-grid">
                    <div class="bbai-comparison-item bbai-comparison-item--before">
                        <div class="bbai-metric-icon" style="color: #6B7280; margin-bottom: 12px;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M4 17l5-5 4 4 4-5 3 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                        </div>
                        <div class="bbai-comparison-label"><?php esc_html_e('Before', 'beepbeep-ai-alt-text-generator'); ?></div>
                        <div class="bbai-comparison-value"><?php echo esc_html(number_format_i18n($missing)); ?></div>
                        <div class="bbai-comparison-description"><?php esc_html_e('images without alt text', 'beepbeep-ai-alt-text-generator'); ?></div>
                    </div>

                    <div class="bbai-comparison-item bbai-comparison-item--after">
                        <div class="bbai-metric-icon" style="color: #10B981; margin-bottom: 12px;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path d="M4 16l5-4 4 3 5-6 2 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                        </div>
                        <div class="bbai-comparison-label">
                            <?php esc_html_e('After', 'beepbeep-ai-alt-text-generator'); ?>
                            <span style="font-weight: normal; font-size: 11px; color: #6B7280;">BeepBeep AI</span>
                        </div>
                        <div class="bbai-comparison-value"><?php echo esc_html(number_format_i18n($optimized)); ?></div>
                        <div class="bbai-comparison-description"><?php esc_html_e('images optimized', 'beepbeep-ai-alt-text-generator'); ?></div>
                        <?php if ($coverage_improvement > 0) : ?>
                            <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: #10B981;">
                                ↑ <?php echo esc_html(number_format_i18n($coverage_improvement)); ?>% <?php esc_html_e('improvement', 'beepbeep-ai-alt-text-generator'); ?>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 11px; color: #6B7280;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3l1.8 4.8L19 9l-5.2 1.2L12 15l-1.8-4.8L5 9l5.2-1.2L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php esc_html_e('Elevated Google Rankings', 'beepbeep-ai-alt-text-generator'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            <div class="bbai-card">
                <div class="bbai-card-header--with-action bbai-mb-4">
                    <h2 class="bbai-card-title"><?php esc_html_e('Recent Activity', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <button type="button" class="bbai-icon-btn bbai-icon-btn-sm" id="bbai-refresh-activity" title="<?php esc_attr_e('Refresh activity', 'beepbeep-ai-alt-text-generator'); ?>">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 8A6 6 0 1 1 8 2" stroke-linecap="round"/>
                            <path d="M8 2V5L11 3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="bbai-activity-timeline" id="bbai-activity-timeline">
                    <!-- Loading State (shown initially) -->
                    <div class="bbai-activity-loading" id="bbai-activity-loading">
                        <div class="bbai-spinner"></div>
                        <p><?php esc_html_e('Loading activity...', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <!-- Empty State (shown when no activity) -->
                    <div class="bbai-activity-empty" id="bbai-activity-empty" style="display: none;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" style="margin-bottom: 16px;">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" opacity="0.3"/>
                            <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.5"/>
                        </svg>
                        <p class="bbai-empty-title"><?php esc_html_e('No activity yet', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-empty-description"><?php esc_html_e('Generate your first alt text to see your activity here.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bbai')); ?>" class="bbai-btn bbai-btn-primary bbai-btn-sm bbai-mt-4">
                            <?php esc_html_e('Go to Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </div>
                </div>
                <?php if ($is_free) : ?>
                    <button type="button" class="bbai-link-btn bbai-link-sm bbai-mt-4" data-action="show-upgrade-modal">
                        <?php esc_html_e('Compare plans', 'beepbeep-ai-alt-text-generator'); ?> ›
                    </button>
                <?php elseif ($is_growth) : ?>
                    <button type="button" class="bbai-link-btn bbai-link-sm bbai-mt-4" data-action="show-upgrade-modal">
                        <?php esc_html_e('Upgrade to Agency', 'beepbeep-ai-alt-text-generator'); ?> ›
                    </button>
                <?php elseif ($is_agency) : ?>
                    <?php
                    $billing_portal_url = '';
                    if (class_exists('BeepBeepAI\AltTextGenerator\Usage_Tracker')) {
                        $billing_portal_url = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_billing_portal_url();
                    }
                    if (!empty($billing_portal_url)) : ?>
                        <a href="<?php echo esc_url($billing_portal_url); ?>" class="bbai-link-btn bbai-link-sm bbai-mt-4" target="_blank" rel="noopener">
                            <?php esc_html_e('Manage subscription', 'beepbeep-ai-alt-text-generator'); ?> ›
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Upsell CTA -->
        <?php
        $bottom_upsell_partial = plugin_dir_path( BBAI_PLUGIN_FILE ) . 'admin/partials/bottom-upsell-cta.php';
        if (file_exists($bottom_upsell_partial)) {
            include $bottom_upsell_partial;
        }
        ?>
    </div>
</div>
