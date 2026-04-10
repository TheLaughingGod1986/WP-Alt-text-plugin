<?php
/**
 * Deprecated ALT review filters wrapper.
 * Kept for backwards compatibility; renders the shared filter-group component.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

$bbai_filter_all = isset($bbai_coverage['total_images']) ? (int) $bbai_coverage['total_images'] : 0;
$bbai_filter_needs_review = isset($bbai_coverage['needs_review_count']) ? (int) $bbai_coverage['needs_review_count'] : 0;
$bbai_filter_missing = isset($bbai_coverage['images_missing_alt']) ? (int) $bbai_coverage['images_missing_alt'] : 0;
$bbai_filter_optimized = isset($bbai_coverage['optimized_count']) ? (int) $bbai_coverage['optimized_count'] : 0;

bbai_ui_render(
    'filter-group',
    [
        'id' => 'bbai-review-filter-tabs',
        'aria_label' => __('Filter images by review priority', 'beepbeep-ai-alt-text-generator'),
        'interaction_mode' => 'filter',
        'default_filter' => 'all',
        'items' => [
            [
                'key' => 'all',
                'label' => __('All', 'beepbeep-ai-alt-text-generator'),
                'count' => $bbai_filter_all,
                'active' => true,
            ],
            [
                'key' => 'missing',
                'label' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                'count' => $bbai_filter_missing,
                'attention' => $bbai_filter_missing > 0,
            ],
            [
                'key' => 'weak',
                'label' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
                'count' => $bbai_filter_needs_review,
                'attention' => $bbai_filter_needs_review > 0,
            ],
            [
                'key' => 'optimized',
                'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
                'count' => $bbai_filter_optimized,
            ],
        ],
    ]
);
