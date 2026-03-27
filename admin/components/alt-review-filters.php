<?php
/**
 * Smart Review Filters component.
 * Quick filter buttons for ALT Library table.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_filter_all = isset($bbai_coverage['total_images']) ? (int) $bbai_coverage['total_images'] : 0;
$bbai_filter_needs_review = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
$bbai_filter_missing = isset($bbai_coverage['images_missing_alt']) ? (int) $bbai_coverage['images_missing_alt'] : 0;
$bbai_filter_auto = isset($bbai_coverage['ai_source_count']) ? (int) $bbai_coverage['ai_source_count'] : 0;
$bbai_filter_optimized = isset($bbai_coverage['optimized_count']) ? (int) $bbai_coverage['optimized_count'] : 0;
?>

<div class="bbai-alt-review-filters" role="group" aria-labelledby="bbai-alt-review-filters-label">
    <span id="bbai-alt-review-filters-label" class="bbai-sr-only"><?php esc_html_e('Filter images by review priority', 'beepbeep-ai-alt-text-generator'); ?></span>
    <div class="bbai-alt-review-filters__list">
        <button type="button"
                class="bbai-filter-pill bbai-filter-pill--all bbai-alt-review-filters__btn bbai-alt-review-filters__btn--active"
                data-filter="all"
                aria-pressed="true"
                aria-label="<?php echo esc_attr(sprintf(/* translators: %d: total image count */ __('Show all images (%d)', 'beepbeep-ai-alt-text-generator'), $bbai_filter_all)); ?>">
            <?php esc_html_e('All', 'beepbeep-ai-alt-text-generator'); ?>
            <span class="bbai-alt-review-filters__count">(<?php echo esc_html(number_format_i18n($bbai_filter_all)); ?>)</span>
        </button>
        <button type="button"
                class="bbai-filter-pill bbai-filter-pill--needs-review bbai-alt-review-filters__btn"
                data-filter="weak"
                aria-pressed="false"
                aria-label="<?php echo esc_attr(sprintf(/* translators: %d: count of images needing review */ __('Filter by needs review (%d)', 'beepbeep-ai-alt-text-generator'), $bbai_filter_needs_review)); ?>">
            <?php esc_html_e('Needs review', 'beepbeep-ai-alt-text-generator'); ?>
            <span class="bbai-alt-review-filters__count">(<?php echo esc_html(number_format_i18n($bbai_filter_needs_review)); ?>)</span>
        </button>
        <button type="button"
                class="bbai-filter-pill bbai-filter-pill--missing bbai-alt-review-filters__btn"
                data-filter="missing"
                aria-pressed="false"
                aria-label="<?php echo esc_attr(sprintf(/* translators: %d: count of images missing ALT */ __('Filter by missing ALT (%d)', 'beepbeep-ai-alt-text-generator'), $bbai_filter_missing)); ?>">
            <?php esc_html_e('Missing', 'beepbeep-ai-alt-text-generator'); ?>
            <span class="bbai-alt-review-filters__count">(<?php echo esc_html(number_format_i18n($bbai_filter_missing)); ?>)</span>
        </button>
        <button type="button"
                class="bbai-filter-pill bbai-filter-pill--optimized bbai-alt-review-filters__btn"
                data-filter="optimized"
                aria-pressed="false"
                aria-label="<?php echo esc_attr(sprintf(/* translators: %d: count of optimized images */ __('Filter by optimized (%d)', 'beepbeep-ai-alt-text-generator'), $bbai_filter_optimized)); ?>">
            <?php esc_html_e('Optimized', 'beepbeep-ai-alt-text-generator'); ?>
            <span class="bbai-alt-review-filters__count">(<?php echo esc_html(number_format_i18n($bbai_filter_optimized)); ?>)</span>
        </button>
    </div>
</div>
