<?php
/**
 * Metric Cards Component - Reusable
 * Displays Time Saved, Images Optimized, and SEO Impact metrics
 *
 * @package BeepBeep_AI
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Calculate metrics based on provided data or defaults
$bbai_alt_texts_generated = isset($bbai_alt_texts_generated) ? $bbai_alt_texts_generated : (isset($bbai_usage_stats) && is_array($bbai_usage_stats) ? ($bbai_usage_stats['used'] ?? 0) : 0);
$bbai_minutes_per_alt_text = 2.5;

// Use provided time_saved_hours if available, otherwise calculate
if (isset($bbai_time_saved_hours) && is_numeric($bbai_time_saved_hours)) {
    $bbai_hours_saved = floatval($bbai_time_saved_hours);
} else {
    $bbai_hours_saved = round(($bbai_alt_texts_generated * $bbai_minutes_per_alt_text) / 60, 1);
}
// Always format to 1 decimal place for consistency
$bbai_hours_saved = round($bbai_hours_saved, 1);

// Get image counts (from stats array or individual variables)
$bbai_total_images = isset($bbai_stats['total']) ? $bbai_stats['total'] : (isset($bbai_total_images) ? $bbai_total_images : 0);
$bbai_optimized = isset($bbai_stats['with_alt']) ? $bbai_stats['with_alt'] : (isset($with_alt_count) ? $with_alt_count : (isset($bbai_optimized) ? $bbai_optimized : 0));
$bbai_coverage_percent = $bbai_total_images > 0 ? round(($bbai_optimized / $bbai_total_images) * 100) : 0;
$bbai_seo_impact_percent = $bbai_coverage_percent;

// Description text mode: 'empty' for new users, 'active' for users with data
$bbai_description_mode = isset($bbai_description_mode) ? $bbai_description_mode : ($bbai_hours_saved > 0 || $bbai_optimized > 0 ? 'active' : 'empty');

// Custom wrapper class (optional)
$bbai_wrapper_class = isset($bbai_wrapper_class) ? $bbai_wrapper_class : 'bbai-premium-metrics-grid';
?>

<div class="<?php echo esc_attr($bbai_wrapper_class); ?>">
    <!-- Time Saved Card -->
    <div class="bbai-premium-card bbai-metric-card">
        <div class="bbai-metric-icon" style="color: #10B981;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="bbai-metric-value" style="color: #10B981;"><?php echo esc_html(number_format($bbai_hours_saved, 1)); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('HRS', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('TIME SAVED', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($bbai_description_mode === 'empty') {
                esc_html_e('Start optimizing to see your time savings', 'beepbeep-ai-alt-text-generator');
            } else {
                esc_html_e('Estimated manual work saved.', 'beepbeep-ai-alt-text-generator');
            }
            ?>
        </div>
    </div>
    
    <!-- Images Optimized Card -->
    <div class="bbai-premium-card bbai-metric-card">
        <div class="bbai-metric-icon" style="color: #3B82F6;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="bbai-metric-value"><?php echo esc_html(number_format_i18n($bbai_optimized)); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('IMAGES', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('OPTIMIZED', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($bbai_description_mode === 'empty') {
                esc_html_e('Start optimizing to see your progress', 'beepbeep-ai-alt-text-generator');
            } else {
                esc_html_e('Automatically generates alt text for existing images.', 'beepbeep-ai-alt-text-generator');
            }
            ?>
        </div>
    </div>
    
    <!-- Estimated SEO Impact Card -->
    <div class="bbai-premium-card bbai-metric-card">
        <div class="bbai-metric-icon" style="color: #10B981;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <?php
        // Allow custom SEO impact value, or calculate from coverage
        $bbai_seo_display = isset($bbai_seo_impact_percent) ? $bbai_seo_impact_percent : $bbai_coverage_percent;
        $bbai_seo_display_formatted = $bbai_seo_display > 0 ? '+' . $bbai_seo_display . '%' : '0%';
        ?>
        <div class="bbai-metric-value" style="color: #10B981;"><?php echo esc_html($bbai_seo_display_formatted); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('SEO', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('IMPACT', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($bbai_description_mode === 'empty') {
                esc_html_e('Start optimizing to boost your SEO score', 'beepbeep-ai-alt-text-generator');
            } else {
                esc_html_e('Estimated improvement in alt text coverage.', 'beepbeep-ai-alt-text-generator');
            }
            ?>
        </div>
    </div>
</div>
