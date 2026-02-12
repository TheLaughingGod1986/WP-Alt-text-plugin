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
$alt_texts_generated = isset($alt_texts_generated) ? $alt_texts_generated : (isset($usage_stats) && is_array($usage_stats) ? ($usage_stats['used'] ?? 0) : 0);
$minutes_per_alt_text = 2.5;

// Use provided time_saved_hours if available, otherwise calculate
if (isset($time_saved_hours) && is_numeric($time_saved_hours)) {
    $hours_saved = floatval($time_saved_hours);
} else {
    $hours_saved = round(($alt_texts_generated * $minutes_per_alt_text) / 60, 1);
}
// Always format to 1 decimal place for consistency
$hours_saved = round($hours_saved, 1);

// Get image counts (from stats array or individual variables)
$total_images = isset($stats['total']) ? $stats['total'] : (isset($total_images) ? $total_images : 0);
$optimized = isset($stats['with_alt']) ? $stats['with_alt'] : (isset($with_alt_count) ? $with_alt_count : (isset($optimized) ? $optimized : 0));
$coverage_percent = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;
$seo_impact_percent = $coverage_percent;

// Description text mode: 'empty' for new users, 'active' for users with data
$description_mode = isset($description_mode) ? $description_mode : ($hours_saved > 0 || $optimized > 0 ? 'active' : 'empty');

// Custom wrapper class (optional)
$wrapper_class = isset($wrapper_class) ? $wrapper_class : 'bbai-premium-metrics-grid';
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <!-- Time Saved Card -->
    <div class="bbai-premium-card bbai-metric-card">
        <div class="bbai-metric-icon" style="color: #10B981;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="bbai-metric-value" style="color: #10B981;"><?php echo esc_html(number_format($hours_saved, 1)); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('HRS', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('TIME SAVED', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($description_mode === 'empty') {
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
        <div class="bbai-metric-value"><?php echo esc_html(number_format_i18n($optimized)); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('IMAGES', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('OPTIMIZED', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($description_mode === 'empty') {
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
        $seo_display = isset($seo_impact_percent) ? $seo_impact_percent : $coverage_percent;
        $seo_display_formatted = $seo_display > 0 ? '+' . $seo_display . '%' : '0%';
        ?>
        <div class="bbai-metric-value" style="color: #10B981;"><?php echo esc_html($seo_display_formatted); ?></div>
        <div class="bbai-metric-label"><?php esc_html_e('SEO', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-sublabel"><?php esc_html_e('IMPACT', 'beepbeep-ai-alt-text-generator'); ?></div>
        <div class="bbai-metric-description">
            <?php 
            if ($description_mode === 'empty') {
                esc_html_e('Start optimizing to boost your SEO score', 'beepbeep-ai-alt-text-generator');
            } else {
                esc_html_e('Estimated improvement in alt text coverage.', 'beepbeep-ai-alt-text-generator');
            }
            ?>
        </div>
    </div>
</div>
