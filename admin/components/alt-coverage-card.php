<?php
/**
 * ALT Coverage card component.
 * Visual summary of image states: Optimized, Needs Review, Missing ALT, Auto Generated.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_total = isset($bbai_coverage['total_images']) ? (int) $bbai_coverage['total_images'] : 0;
$bbai_optimized = isset($bbai_coverage['optimized_count']) ? (int) $bbai_coverage['optimized_count'] : 0;
$bbai_needs_review = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
$bbai_missing = isset($bbai_coverage['images_missing_alt']) ? (int) $bbai_coverage['images_missing_alt'] : 0;
$bbai_auto_generated = isset($bbai_coverage['ai_source_count']) ? (int) $bbai_coverage['ai_source_count'] : 0;
$bbai_optimized_pct = $bbai_total > 0 ? round(($bbai_optimized / $bbai_total) * 100) : 0;
$bbai_needs_review_pct = $bbai_total > 0 ? round(($bbai_needs_review / $bbai_total) * 100) : 0;
$bbai_missing_pct = $bbai_total > 0 ? round(($bbai_missing / $bbai_total) * 100) : 0;
?>

<div class="bbai-alt-coverage-card" role="region" aria-labelledby="bbai-alt-coverage-title">
    <h2 id="bbai-alt-coverage-title" class="bbai-alt-coverage-card__title">
        <?php esc_html_e('ALT Coverage', 'beepbeep-ai-alt-text-generator'); ?>
    </h2>

    <div class="bbai-alt-coverage-card__stats">
        <div class="bbai-alt-coverage-card__stat">
            <span class="bbai-alt-coverage-card__stat-label"><?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?>:</span>
            <span class="bbai-alt-coverage-card__stat-value" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: number of images optimized */ __('%d images optimized', 'beepbeep-ai-alt-text-generator'), $bbai_optimized)); ?>"><?php echo esc_html(number_format_i18n($bbai_optimized)); ?></span>
        </div>
        <div class="bbai-alt-coverage-card__stat">
            <span class="bbai-alt-coverage-card__stat-label"><?php esc_html_e('Needs Review', 'beepbeep-ai-alt-text-generator'); ?>:</span>
            <span class="bbai-alt-coverage-card__stat-value" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: number of images needing review */ __('%d images need review', 'beepbeep-ai-alt-text-generator'), $bbai_needs_review)); ?>"><?php echo esc_html(number_format_i18n($bbai_needs_review)); ?></span>
        </div>
        <div class="bbai-alt-coverage-card__stat">
            <span class="bbai-alt-coverage-card__stat-label"><?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?>:</span>
            <span class="bbai-alt-coverage-card__stat-value" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: number of images missing alt text */ __('%d images missing alt text', 'beepbeep-ai-alt-text-generator'), $bbai_missing)); ?>"><?php echo esc_html(number_format_i18n($bbai_missing)); ?></span>
        </div>
        <div class="bbai-alt-coverage-card__stat">
            <span class="bbai-alt-coverage-card__stat-label"><?php esc_html_e('Auto Generated', 'beepbeep-ai-alt-text-generator'); ?>:</span>
            <span class="bbai-alt-coverage-card__stat-value" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: number of images auto-generated */ __('%d images auto generated', 'beepbeep-ai-alt-text-generator'), $bbai_auto_generated)); ?>"><?php echo esc_html(number_format_i18n($bbai_auto_generated)); ?></span>
        </div>
    </div>

    <div class="bbai-alt-coverage-card__progress" role="progressbar" aria-valuenow="<?php echo esc_attr($bbai_optimized_pct); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e('Overall optimization coverage', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-alt-coverage-card__progress-bar">
            <div class="bbai-alt-coverage-card__progress-segment bbai-alt-coverage-card__progress-segment--optimized" style="width: <?php echo esc_attr($bbai_optimized_pct); ?>%;"></div>
            <div class="bbai-alt-coverage-card__progress-segment bbai-alt-coverage-card__progress-segment--needs-review" style="width: <?php echo esc_attr($bbai_needs_review_pct); ?>%;"></div>
            <div class="bbai-alt-coverage-card__progress-segment bbai-alt-coverage-card__progress-segment--missing" style="width: <?php echo esc_attr($bbai_missing_pct); ?>%;"></div>
        </div>
        <div class="bbai-alt-coverage-card__progress-labels">
            <span class="bbai-alt-coverage-card__progress-label bbai-alt-coverage-card__progress-label--optimized"><?php echo esc_html($bbai_optimized_pct); ?>% <?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-alt-coverage-card__progress-label bbai-alt-coverage-card__progress-label--needs-review"><?php echo esc_html($bbai_needs_review_pct); ?>% <?php esc_html_e('Needs Review', 'beepbeep-ai-alt-text-generator'); ?></span>
            <span class="bbai-alt-coverage-card__progress-label bbai-alt-coverage-card__progress-label--missing"><?php echo esc_html($bbai_missing_pct); ?>% <?php esc_html_e('Missing ALT', 'beepbeep-ai-alt-text-generator'); ?></span>
        </div>
    </div>
</div>
