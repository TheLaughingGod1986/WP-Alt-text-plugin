<?php
/**
 * Dashboard status card.
 *
 * Expected parent scope:
 * - $bbai_state_total_images
 * - $bbai_state_optimized_count
 * - $bbai_state_weak_count
 * - $bbai_state_missing_count
 * - $bbai_library_url
 * - $bbai_optimized_library_url
 * - $bbai_needs_review_library_url
 * - $bbai_missing_library_url
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_status_row_variant = isset( $bbai_status_row_variant ) ? (string) $bbai_status_row_variant : 'default';
$bbai_status_row_is_hero = 'hero' === $bbai_status_row_variant;
$bbai_status_active = 'all';
$bbai_status_interaction_mode = ( ! empty( $bbai_is_anonymous_trial ) && empty( $bbai_has_connected_account ) )
    ? 'filter'
    : 'navigate';

if ($bbai_state_missing_count > 0) {
    $bbai_status_active = 'missing';
} elseif ($bbai_state_weak_count > 0) {
    $bbai_status_active = 'weak';
} elseif ($bbai_state_optimized_count > 0 && $bbai_state_total_images > 0) {
    $bbai_status_active = 'optimized';
}

$bbai_status_items = [
    [
        'key' => 'all',
        'label' => __('All', 'beepbeep-ai-alt-text-generator'),
        'count' => (int) $bbai_state_total_images,
        'active' => 'all' === $bbai_status_active,
        'href' => $bbai_library_url,
        'data_filter' => 'all',
        'status_segment' => 'all',
        'status_filter' => 'all',
        'status_url' => $bbai_library_url,
        'metric_key' => 'all',
        'attrs' => [
            'data-bbai-dashboard-status-pill' => '1',
            'data-bbai-status-segment' => 'all',
            'data-bbai-modal-context' => 'tabs',
        ],
        'count_attrs' => [
            'data-bbai-dashboard-status-count' => 'all',
        ],
    ],
    [
        'key' => 'missing',
        'label' => __('Missing', 'beepbeep-ai-alt-text-generator'),
        'count' => (int) $bbai_state_missing_count,
        'active' => 'missing' === $bbai_status_active,
        'href' => $bbai_missing_library_url,
        'data_filter' => 'missing',
        'status_segment' => 'missing',
        'status_filter' => 'missing',
        'status_url' => $bbai_missing_library_url,
        'metric_key' => 'missing',
        'attrs' => [
            'data-bbai-dashboard-status-pill' => '1',
            'data-bbai-status-segment' => 'missing',
            'data-bbai-modal-context' => 'tabs',
        ],
        'count_attrs' => [
            'data-bbai-dashboard-status-count' => 'missing',
        ],
    ],
    [
        'key' => 'weak',
        'label' => __('Needs review', 'beepbeep-ai-alt-text-generator'),
        'count' => (int) $bbai_state_weak_count,
        'active' => 'weak' === $bbai_status_active,
        'href' => $bbai_needs_review_library_url,
        'data_filter' => 'weak',
        'status_segment' => 'weak',
        'status_filter' => 'needs_review',
        'status_url' => $bbai_needs_review_library_url,
        'metric_key' => 'weak',
        'attrs' => [
            'data-bbai-dashboard-status-pill' => '1',
            'data-bbai-status-segment' => 'weak',
            'data-bbai-modal-context' => 'tabs',
        ],
        'count_attrs' => [
            'data-bbai-dashboard-status-count' => 'weak',
        ],
    ],
    [
        'key' => 'optimized',
        'label' => __('Optimized', 'beepbeep-ai-alt-text-generator'),
        'count' => (int) $bbai_state_optimized_count,
        'active' => 'optimized' === $bbai_status_active,
        'href' => $bbai_optimized_library_url,
        'data_filter' => 'optimized',
        'status_segment' => 'optimized',
        'status_filter' => 'optimized',
        'status_url' => $bbai_optimized_library_url,
        'metric_key' => 'optimized',
        'attrs' => [
            'data-bbai-dashboard-status-pill' => '1',
            'data-bbai-status-segment' => 'optimized',
            'data-bbai-modal-context' => 'tabs',
        ],
        'count_attrs' => [
            'data-bbai-dashboard-status-count' => 'optimized',
        ],
    ],
];
?>
<section
    id="bbai-dashboard-status-card"
    class="bbai-dashboard-status-card<?php echo $bbai_status_row_is_hero ? ' bbai-dashboard-status-card--hero' : ''; ?>"
    data-bbai-dashboard-status-card="1"
    data-bbai-status-active-segment="<?php echo esc_attr($bbai_status_active); ?>"
    data-bbai-status-selected-segment="<?php echo esc_attr($bbai_status_active); ?>"
    aria-labelledby="bbai-dashboard-status-card-heading"
>
    <h2 id="bbai-dashboard-status-card-heading" class="screen-reader-text"><?php esc_html_e('Image status', 'beepbeep-ai-alt-text-generator'); ?></h2>
    <?php
    bbai_ui_render(
        'filter-group',
        [
            'id' => 'bbai-dashboard-status-nav',
            'aria_label' => __('Review images filtered by status', 'beepbeep-ai-alt-text-generator'),
            'interaction_mode' => $bbai_status_interaction_mode,
            'size' => $bbai_status_row_is_hero ? 'compact' : 'standard',
            'root_class' => 'bbai-dashboard-status-card__filters' . ( $bbai_status_row_is_hero ? ' bbai-dashboard-status-card__filters--hero' : '' ),
            'root_attrs' => [
                'data-bbai-dashboard-status-nav' => '1',
                'data-bbai-status-active-segment' => $bbai_status_active,
                'data-bbai-status-selected-segment' => $bbai_status_active,
                'data-bbai-status-interaction-mode' => $bbai_status_interaction_mode,
            ],
            'items' => $bbai_status_items,
        ]
    );
    ?>
</section>
