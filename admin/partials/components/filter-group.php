<?php
/**
 * Shared status filter group.
 *
 * @package BeepBeep_AI
 *
 * Expected $bbai_ui keys:
 * - variant: 'horizontal' (default)
 * - size: 'standard' | 'compact'
 * - interaction_mode: 'filter' | 'navigate'
 * - id, aria_label, default_filter (filter mode), root_class, root_attrs
 * - items: arrays with:
 *   - key: 'all'|'optimized'|'weak'|'missing'
 *   - label, count (int, optional), active (bool)
 *   - attention, locked, disabled (bool, optional)
 *   - data_filter, filter_label_attr (filter mode)
 *   - href, item_aria_label, status_segment, status_filter, status_url, metric_key (navigate mode)
 *   - extra_class, attrs, label_attrs, count_attrs
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_fg_variant = 'horizontal';
$bbai_fg_mode = (($bbai_ui['interaction_mode'] ?? $bbai_ui['mode'] ?? 'filter') === 'navigate') ? 'navigate' : 'filter';
$bbai_fg_size = (($bbai_ui['size'] ?? 'standard') === 'compact') ? 'compact' : 'standard';
$bbai_fg_items = isset($bbai_ui['items']) && is_array($bbai_ui['items']) ? $bbai_ui['items'] : [];
$bbai_fg_id = trim((string) ($bbai_ui['id'] ?? ''));
$bbai_fg_aria = trim((string) ($bbai_ui['aria_label'] ?? ''));
$bbai_fg_default = (string) ($bbai_ui['default_filter'] ?? '');
$bbai_fg_root_class = trim((string) ($bbai_ui['root_class'] ?? ''));
$bbai_fg_root_attrs = isset($bbai_ui['root_attrs']) && is_array($bbai_ui['root_attrs']) ? $bbai_ui['root_attrs'] : [];

$bbai_fg_classes = array_filter([
    'bbai-filter-group',
    'bbai-filter-group--status',
    'bbai-filter-group--' . $bbai_fg_variant,
    'bbai-filter-group--' . $bbai_fg_mode,
    'bbai-filter-group--' . $bbai_fg_size,
    $bbai_fg_root_class,
]);

$bbai_fg_label = $bbai_fg_aria !== '' ? $bbai_fg_aria : (
    $bbai_fg_mode === 'navigate'
        ? __('Open filtered views in ALT Library', 'beepbeep-ai-alt-text-generator')
        : __('Filter images by state', 'beepbeep-ai-alt-text-generator')
);

$bbai_merge_attrs = static function (array $base, array $extra): array {
    foreach ($extra as $key => $value) {
        if ($key === 'class') {
            $base['class'] = trim((string) ($base['class'] ?? '') . ' ' . (string) $value);
            continue;
        }
        $base[$key] = $value;
    }

    return $base;
};

$bbai_open = static function (string $tag, array $attrs): void {
    $parts = [];
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === false || $v === '') {
            continue;
        }
        $parts[] = sprintf('%s="%s"', $k, esc_attr(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v));
    }
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above.
    echo '<' . $tag . ($parts ? ' ' . implode(' ', $parts) : '') . '>';
};

$bbai_root_tag = $bbai_fg_mode === 'navigate' ? 'nav' : 'div';
$bbai_root_attrs = [
    'class' => implode(' ', $bbai_fg_classes),
    'aria-label' => $bbai_fg_label,
];

if ($bbai_fg_mode === 'filter') {
    $bbai_root_attrs['role'] = 'group';
}

if ($bbai_fg_id !== '') {
    $bbai_root_attrs['id'] = $bbai_fg_id;
}

if ($bbai_fg_default !== '') {
    $bbai_root_attrs['data-bbai-default-filter'] = $bbai_fg_default;
}

$bbai_root_attrs = $bbai_merge_attrs($bbai_root_attrs, $bbai_fg_root_attrs);
$bbai_open($bbai_root_tag, $bbai_root_attrs);

foreach ($bbai_fg_items as $bbai_item) {
    if (!is_array($bbai_item)) {
        continue;
    }

    $bbai_key = isset($bbai_item['key']) ? sanitize_key((string) $bbai_item['key']) : '';
    if ($bbai_key === '') {
        continue;
    }

    $bbai_slug = preg_replace('/[^a-z]/', '', $bbai_key);
    if ($bbai_slug === '') {
        $bbai_slug = 'all';
    }

    $bbai_label = (string) ($bbai_item['label'] ?? '');
    $bbai_count = array_key_exists('count', $bbai_item) ? (int) $bbai_item['count'] : 0;
    $bbai_active = !empty($bbai_item['active']);
    $bbai_attention = !empty($bbai_item['attention']) || !empty($bbai_item['problem']);
    $bbai_locked = !empty($bbai_item['locked']);
    $bbai_disabled = !empty($bbai_item['disabled']);
    $bbai_extra_class = trim((string) ($bbai_item['extra_class'] ?? ''));
    $bbai_item_attrs = isset($bbai_item['attrs']) && is_array($bbai_item['attrs']) ? $bbai_item['attrs'] : [];
    $bbai_label_attrs = isset($bbai_item['label_attrs']) && is_array($bbai_item['label_attrs']) ? $bbai_item['label_attrs'] : [];
    $bbai_count_attrs = isset($bbai_item['count_attrs']) && is_array($bbai_item['count_attrs']) ? $bbai_item['count_attrs'] : [];

    $bbai_item_classes = array_filter([
        'bbai-filter-group__item',
        'bbai-filter-group__item--' . $bbai_fg_mode,
        'bbai-filter-group__item--status-' . $bbai_slug,
        $bbai_active ? 'bbai-filter-group__item--active' : '',
        $bbai_attention ? 'bbai-filter-group__item--attention' : '',
        $bbai_locked ? 'bbai-filter-group__item--locked' : '',
        $bbai_disabled ? 'bbai-filter-group__item--disabled' : '',
        $bbai_extra_class,
    ]);

    if ($bbai_fg_mode === 'navigate') {
        $bbai_href = isset($bbai_item['href']) ? (string) $bbai_item['href'] : '';
        $bbai_item_aria = isset($bbai_item['item_aria_label']) ? (string) $bbai_item['item_aria_label'] : $bbai_label;
        $bbai_seg = array_key_exists('status_segment', $bbai_item)
            ? (string) $bbai_item['status_segment']
            : ($bbai_key === 'all' ? 'all' : $bbai_key);
        $bbai_filt_default = '';
        if ($bbai_key === 'weak') {
            $bbai_filt_default = 'needs_review';
        } elseif ($bbai_key !== 'all') {
            $bbai_filt_default = $bbai_key;
        }
        $bbai_filt = array_key_exists('status_filter', $bbai_item) ? (string) $bbai_item['status_filter'] : $bbai_filt_default;
        $bbai_status_url = isset($bbai_item['status_url']) ? (string) $bbai_item['status_url'] : $bbai_href;
        $bbai_metric = isset($bbai_item['metric_key']) ? (string) $bbai_item['metric_key'] : $bbai_key;
        $bbai_tag = (!$bbai_disabled && !$bbai_locked && $bbai_href !== '') ? 'a' : 'button';
        $bbai_attrs = [
            'class' => implode(' ', $bbai_item_classes),
            'data-bbai-status-row' => '1',
            'data-bbai-status-segment' => $bbai_seg,
            'aria-label' => $bbai_item_aria,
        ];

        if ($bbai_tag === 'a') {
            $bbai_attrs['href'] = esc_url($bbai_href);
        } else {
            $bbai_attrs['type'] = 'button';
        }

        if ($bbai_filt !== '') {
            $bbai_attrs['data-bbai-status-filter'] = $bbai_filt;
        }

        if ($bbai_status_url !== '') {
            $bbai_attrs['data-bbai-status-url'] = esc_url($bbai_status_url);
        }

        if ($bbai_active) {
            $bbai_attrs['aria-current'] = 'true';
        }

        if ($bbai_locked) {
            $bbai_attrs['aria-disabled'] = 'true';
        }

        if ($bbai_disabled) {
            $bbai_attrs['disabled'] = 'disabled';
            $bbai_attrs['aria-disabled'] = 'true';
        }

        if ('missing' === $bbai_seg && $bbai_count > 0) {
            $bbai_attrs['data-bbai-missing-row-pulse'] = '1';
        }

        $bbai_attrs = $bbai_merge_attrs($bbai_attrs, $bbai_item_attrs);
        $bbai_count_attrs = $bbai_merge_attrs(
            [
                'class' => 'bbai-filter-group__count',
                'data-bbai-status-metric' => $bbai_metric,
            ],
            $bbai_count_attrs
        );

        $bbai_open($bbai_tag, $bbai_attrs);
        ?>
        <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
        <?php $bbai_open('span', $bbai_merge_attrs(['class' => 'bbai-filter-group__label'], $bbai_label_attrs)); ?>
        <?php echo esc_html($bbai_label); ?>
        </span>
        <?php $bbai_open('span', $bbai_count_attrs); ?>
        <?php echo esc_html(number_format_i18n($bbai_count)); ?>
        </span>
        </<?php echo esc_html($bbai_tag); ?>>
        <?php
        continue;
    }

    $bbai_df = isset($bbai_item['data_filter']) ? sanitize_key((string) $bbai_item['data_filter']) : $bbai_key;
    $bbai_fl = isset($bbai_item['filter_label_attr']) ? (string) $bbai_item['filter_label_attr'] : $bbai_label;
    $bbai_attrs = [
        'type' => 'button',
        'class' => implode(' ', $bbai_item_classes),
        'data-filter' => $bbai_df,
        'data-bbai-filter-label' => $bbai_fl,
        'aria-pressed' => $bbai_active ? 'true' : 'false',
    ];

    if ($bbai_active) {
        $bbai_attrs['aria-current'] = 'true';
    }

    if ($bbai_locked) {
        $bbai_attrs['aria-disabled'] = 'true';
    }

    if ($bbai_disabled) {
        $bbai_attrs['disabled'] = 'disabled';
        $bbai_attrs['aria-disabled'] = 'true';
    }

    $bbai_attrs = $bbai_merge_attrs($bbai_attrs, $bbai_item_attrs);
    $bbai_count_attrs = $bbai_merge_attrs(['class' => 'bbai-filter-group__count'], $bbai_count_attrs);

    $bbai_open('button', $bbai_attrs);
    ?>
    <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
    <?php $bbai_open('span', $bbai_merge_attrs(['class' => 'bbai-filter-group__label'], $bbai_label_attrs)); ?>
    <?php echo esc_html($bbai_label); ?>
    </span>
    <?php $bbai_open('span', $bbai_count_attrs); ?>
    <?php echo esc_html(number_format_i18n($bbai_count)); ?>
    </span>
    </button>
    <?php
}

echo '</' . $bbai_root_tag . '>';
