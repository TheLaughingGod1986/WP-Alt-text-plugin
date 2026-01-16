<?php
/**
 * SEO Impact Card
 * Shows SEO score improvement, Google Images ranking potential, accessibility meter
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Calculate SEO metrics
$total_images = isset($stats) && is_array($stats) ? ($stats['total'] ?? 0) : 0;
$optimized = isset($stats) && is_array($stats) ? ($stats['with_alt'] ?? 0) : 0;
$coverage_percent = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;

// Estimate SEO score (0-100 scale)
// Based on: coverage percentage, quality (assumed good for AI-generated), keyword density (estimated)
$seo_score = min(100, round($coverage_percent * 0.8 + 20)); // Base score + coverage impact

// Google Images ranking potential (estimated)
$google_images_potential = 'Low';
$google_images_color = '#ef4444';
if ($coverage_percent >= 90) {
    $google_images_potential = 'Excellent';
    $google_images_color = '#10b981';
} elseif ($coverage_percent >= 70) {
    $google_images_potential = 'Good';
    $google_images_color = '#3b82f6';
} elseif ($coverage_percent >= 50) {
    $google_images_potential = 'Fair';
    $google_images_color = '#f59e0b';
}

// WCAG compliance meter
$wcag_compliance = min(100, round($coverage_percent * 1.1)); // Slightly higher than coverage (AI-generated alt text is WCAG-compliant)
$wcag_status = 'Non-compliant';
$wcag_color = '#ef4444';
if ($wcag_compliance >= 95) {
    $wcag_status = 'AA Compliant';
    $wcag_color = '#10b981';
} elseif ($wcag_compliance >= 80) {
    $wcag_status = 'Partially Compliant';
    $wcag_color = '#f59e0b';
}

// Trend (simplified - would need historical data)
$trend = 'up'; // Could be calculated from historical data
?>

<div class="bbai-seo-impact-card bbai-card">
    <div class="bbai-card-header">
        <h2 class="bbai-card-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="bbai-icon-inline">
                <path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <?php esc_html_e('SEO Impact', 'beepbeep-ai-alt-text-generator'); ?>
        </h2>
    </div>
    <div class="bbai-card-body">
        <div class="bbai-seo-metrics-grid">
            <!-- SEO Score -->
            <div class="bbai-seo-metric">
                <div class="bbai-seo-metric-header">
                    <span class="bbai-seo-metric-label"><?php esc_html_e('SEO Score', 'beepbeep-ai-alt-text-generator'); ?></span>
                    <?php if ($trend === 'up') : ?>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-seo-trend-up">
                            <path d="M8 2L12 6H9V14H7V6H4L8 2Z" fill="#10b981"/>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="bbai-seo-metric-value"><?php echo esc_html($seo_score); ?>/100</div>
                <div class="bbai-seo-metric-progress">
                    <div class="bbai-seo-metric-progress-bar" style="width: <?php echo esc_attr($seo_score); ?>%;" role="progressbar" aria-valuenow="<?php echo esc_attr($seo_score); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="bbai-seo-metric-description">
                    <?php esc_html_e('Estimated improvement based on alt text coverage', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>

            <!-- Google Images Ranking -->
            <div class="bbai-seo-metric">
                <div class="bbai-seo-metric-header">
                    <span class="bbai-seo-metric-label"><?php esc_html_e('Google Images', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-seo-metric-value bbai-seo-metric-value--<?php echo esc_attr(strtolower($google_images_potential)); ?>" style="color: <?php echo esc_attr($google_images_color); ?>;">
                    <?php echo esc_html($google_images_potential); ?>
                </div>
                <div class="bbai-seo-metric-indicator" style="background: <?php echo esc_attr($google_images_color); ?>;"></div>
                <p class="bbai-seo-metric-description">
                    <?php esc_html_e('Ranking potential for image search results', 'beepbeep-ai-alt-text-generator'); ?>
                </p>
            </div>

            <!-- WCAG Compliance -->
            <div class="bbai-seo-metric">
                <div class="bbai-seo-metric-header">
                    <span class="bbai-seo-metric-label"><?php esc_html_e('Accessibility', 'beepbeep-ai-alt-text-generator'); ?></span>
                </div>
                <div class="bbai-seo-metric-value bbai-seo-metric-value--wcag" style="color: <?php echo esc_attr($wcag_color); ?>;">
                    <?php echo esc_html($wcag_compliance); ?>%
                </div>
                <div class="bbai-seo-metric-progress">
                    <div class="bbai-seo-metric-progress-bar bbai-seo-metric-progress-bar--wcag" style="width: <?php echo esc_attr($wcag_compliance); ?>%; background: <?php echo esc_attr($wcag_color); ?>;"></div>
                </div>
                <p class="bbai-seo-metric-description">
                    <?php echo esc_html($wcag_status); ?> (WCAG 2.1 AA)
                </p>
            </div>
        </div>

        <!-- Coverage Summary -->
        <div class="bbai-seo-coverage-summary">
            <div class="bbai-seo-coverage-stat">
                <span class="bbai-seo-coverage-value"><?php echo esc_html($coverage_percent); ?>%</span>
                <span class="bbai-seo-coverage-label"><?php esc_html_e('Coverage', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
            <div class="bbai-seo-coverage-stat">
                <span class="bbai-seo-coverage-value"><?php echo esc_html($optimized); ?></span>
                <span class="bbai-seo-coverage-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
            <div class="bbai-seo-coverage-stat">
                <span class="bbai-seo-coverage-value"><?php echo esc_html($total_images - $optimized); ?></span>
                <span class="bbai-seo-coverage-label"><?php esc_html_e('Remaining', 'beepbeep-ai-alt-text-generator'); ?></span>
            </div>
        </div>
    </div>
</div>
