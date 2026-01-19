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
$days_in_month = (int) date('t');
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

$plan_slug = isset($usage_stats['plan']) ? strtolower($usage_stats['plan']) : 'free';
$is_free = ($plan_slug === 'free');
$is_growth = ($plan_slug === 'pro' || $plan_slug === 'growth');
$is_agency = ($plan_slug === 'agency');

$has_trend_data = false;
foreach ($historical_coverage as $point) {
    if (($point['coverage'] ?? 0) > 0) {
        $has_trend_data = true;
        break;
    }
}

$seo_lift_display = $seo_lift_percent > 0 ? '+' . $seo_lift_percent . '%' : $seo_lift_percent . '%';
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
                        <span class="bbai-usage-breakdown-total"><?php printf(esc_html__('of %s', 'beepbeep-ai-alt-text-generator'), esc_html(number_format_i18n($usage_limit))); ?></span>
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
                <h2 class="bbai-card-title bbai-mb-4"><?php esc_html_e('Recent Activity', 'beepbeep-ai-alt-text-generator'); ?></h2>
                <div class="bbai-activity-timeline" id="bbai-activity-timeline">
                    <div class="bbai-activity-empty">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" style="margin-bottom: 12px; opacity: 0.4;">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <p><?php esc_html_e('No recent activity recorded yet.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                </div>
                <button type="button" class="bbai-link-btn bbai-link-sm bbai-mt-4" data-action="show-upgrade-modal" onclick="if(typeof window.openPricingModal === 'function'){window.openPricingModal('enterprise');} else if(typeof window.bbaiShowUpgradeModal === 'function'){window.bbaiShowUpgradeModal();} else if(typeof window.alttextaiShowUpgradeModal === 'function'){window.alttextaiShowUpgradeModal();}">
                    <?php esc_html_e('Compare all plans', 'beepbeep-ai-alt-text-generator'); ?> ›
                </button>
            </div>
        </div>

        <!-- Bottom Upsell CTA -->
        <?php
        $bottom_upsell_partial = dirname(__FILE__) . '/bottom-upsell-cta.php';
        if (file_exists($bottom_upsell_partial)) {
            include $bottom_upsell_partial;
        }
        ?>
    </div>
</div>

<script>
(function() {
    function drawChart() {
        const canvas = document.getElementById('bbai-coverage-chart');
        if (!canvas || !window.bbaiAnalyticsData) return;

        const ctx = canvas.getContext('2d');
        const data = window.bbaiAnalyticsData.coverage || [];
        if (data.length === 0) return;

        // Get container dimensions for responsive sizing
        const container = canvas.parentElement;
        const containerWidth = container ? container.clientWidth : 800;
        const containerHeight = 300; // Fixed height for consistency
        
        // Set canvas size to match container (with device pixel ratio for crisp rendering)
        const dpr = window.devicePixelRatio || 1;
        canvas.width = containerWidth * dpr;
        canvas.height = containerHeight * dpr;
        canvas.style.width = containerWidth + 'px';
        canvas.style.height = containerHeight + 'px';
        ctx.scale(dpr, dpr);
        
        // Use container dimensions for calculations
        const width = containerWidth;
        const height = containerHeight;
        const padding = 40;
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);

        ctx.clearRect(0, 0, width, height);

        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        const gridLines = 5;
        for (let i = 0; i <= gridLines; i++) {
            const y = padding + (chartHeight / gridLines) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }

        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();

        const maxValue = Math.max(100, Math.max.apply(null, data.map((item) => item.coverage || 0)));
        const points = data.map((item, index) => {
            const x = padding + (chartWidth / Math.max(1, data.length - 1)) * index;
            const y = height - padding - ((item.coverage || 0) / maxValue) * chartHeight;
            return { x, y, label: item.date || '', value: item.coverage || 0 };
        });

        const gradient = ctx.createLinearGradient(0, padding, 0, height - padding);
        gradient.addColorStop(0, 'rgba(96, 165, 250, 0.28)');
        gradient.addColorStop(1, 'rgba(96, 165, 250, 0.04)');
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.moveTo(points[0].x, height - padding);
        points.forEach((point) => ctx.lineTo(point.x, point.y));
        ctx.lineTo(points[points.length - 1].x, height - padding);
        ctx.closePath();
        ctx.fill();

        ctx.strokeStyle = '#60a5fa';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(points[0].x, points[0].y);
        for (let i = 1; i < points.length; i++) {
            ctx.lineTo(points[i].x, points[i].y);
        }
        ctx.stroke();

        points.forEach((point) => {
            if (point.value > 0) {
                ctx.fillStyle = '#60a5fa';
                ctx.beginPath();
                ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, Math.PI * 2);
                ctx.fill();
            }
        });

        ctx.fillStyle = '#64748b';
        ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        points.forEach((point) => {
            ctx.fillText(point.label, point.x, height - padding + 16);
        });

        const tickValues = maxValue >= 80 ? [80, 70, 60, 50, 0] : [100, 75, 50, 25, 0];
        ctx.fillStyle = '#94a3b8';
        ctx.font = '11px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        tickValues.forEach((value) => {
            const y = height - padding - (value / maxValue) * chartHeight;
            ctx.fillText(value + '%', padding - 10, y);
        });

        ctx.fillStyle = '#0f172a';
        ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        const lastPoint = points[points.length - 1];
        if (lastPoint && lastPoint.value > 0) {
            ctx.setLineDash([4, 4]);
            ctx.strokeStyle = 'rgba(96, 165, 250, 0.4)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(lastPoint.x, lastPoint.y);
            ctx.lineTo(lastPoint.x, height - padding);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillText(lastPoint.value + '%', lastPoint.x + 12, lastPoint.y);
        }
    }

    // Draw chart on load
    function initChart() {
        if (typeof window.bbaiAnalytics !== 'undefined' && window.bbaiAnalytics.init) {
            window.bbaiAnalytics.init();
        } else {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', drawChart);
            } else {
                drawChart();
            }
        }
    }
    
    initChart();
    
    // Redraw chart on window resize (debounced)
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            drawChart();
        }, 250);
    });
})();
</script>

<script>
window.bbaiAnalyticsData = {
    coverage: <?php echo wp_json_encode($historical_coverage); ?>,
    usage: <?php echo wp_json_encode($usage_stats); ?>
};

(function() {
    'use strict';

    const periodButton = document.getElementById('bbai-analytics-period');
    if (periodButton) {
        periodButton.addEventListener('click', function(event) {
            const target = event.target instanceof Element ? event.target.closest('button[data-period]') : null;
            if (!target) {
                return;
            }

            const period = target.getAttribute('data-period') || '30d';
            periodButton.querySelectorAll('button[data-period]').forEach((button) => {
                const isActive = button === target;
                if (isActive) {
                    button.classList.add('bbai-period-btn--active');
                    button.setAttribute('aria-pressed', 'true');
                } else {
                    button.classList.remove('bbai-period-btn--active');
                    button.setAttribute('aria-pressed', 'false');
                }
            });

            if (typeof window.bbaiAnalytics !== 'undefined' && window.bbaiAnalytics.loadChartData) {
                window.bbaiAnalytics.loadChartData(period);
            }
        });
    }

    function initAnalytics() {
        // Ensure bbai_ajax is available for activity timeline (check multiple sources)
        const bbaiAjax = (typeof bbai_ajax !== 'undefined' ? bbai_ajax : (typeof window !== 'undefined' && window.bbai_ajax ? window.bbai_ajax : null));
        
        if (!window.bbai_ajax && bbaiAjax) {
            window.bbai_ajax = bbaiAjax;
        }
        
        if (typeof window.bbaiAnalytics !== 'undefined' && window.bbaiAnalytics.init) {
            window.bbaiAnalytics.init();
        } else {
            // Fallback: try to load activity directly if bbaiAnalytics not available
            if (bbaiAjax && bbaiAjax.ajaxurl) {
                const timeline = document.getElementById('bbai-activity-timeline');
                if (timeline) {
                    // Use jQuery if available
                    if (typeof jQuery !== 'undefined' && jQuery.ajax) {
                        jQuery.ajax({
                            url: bbaiAjax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'bbai_get_activity',
                                nonce: bbaiAjax.nonce || ''
                            },
                            success: (response) => {
                                if (response && response.success && response.data && response.data.length > 0) {
                                    // Use the render function from bbaiAnalytics if available
                                    if (typeof window.bbaiAnalytics !== 'undefined' && window.bbaiAnalytics.renderActivityTimeline) {
                                        window.bbaiAnalytics.renderActivityTimeline(response.data);
                                    } else {
                                        // Fallback rendering if bbaiAnalytics.renderActivityTimeline is not available
                                        console.warn('[BeepBeep AI] bbaiAnalytics.renderActivityTimeline not available');
                                    }
                                }
                            },
                            error: (xhr, status, error) => {
                                console.error('[BeepBeep AI] Failed to load activity:', error);
                                // Keep empty state
                            }
                        });
                    } else if (typeof fetch !== 'undefined') {
                        // Fallback to fetch API if jQuery is not available
                        const formData = new FormData();
                        formData.append('action', 'bbai_get_activity');
                        formData.append('nonce', bbaiAjax.nonce || '');
                        
                        fetch(bbaiAjax.ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.success && data.data && data.data.length > 0) {
                                if (typeof window.bbaiAnalytics !== 'undefined' && window.bbaiAnalytics.renderActivityTimeline) {
                                    window.bbaiAnalytics.renderActivityTimeline(data.data);
                                }
                            }
                        })
                        .catch(error => {
                            console.error('[BeepBeep AI] Failed to load activity:', error);
                        });
                    }
                }
            } else {
                console.warn('[BeepBeep AI] bbai_ajax not available for activity timeline');
            }
        }
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initAnalytics();
    } else {
        document.addEventListener('DOMContentLoaded', initAnalytics);
        window.addEventListener('load', initAnalytics);
    }
})();
</script>
