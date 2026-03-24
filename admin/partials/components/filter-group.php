<?php
/**
 * Shared dataset filter group — horizontal (ALT Library) or vertical (dashboard → library).
 *
 * @package BeepBeep_AI
 *
 * Expected $bbai_ui keys:
 * - variant: 'horizontal' | 'vertical'
 * - interaction_mode: 'filter' | 'navigate'
 * - id, aria_label, default_filter (filter mode), root_class (extra classes on root)
 * - items: arrays with:
 *   - key: 'all'|'optimized'|'weak'|'missing' (semantic / CSS)
 *   - data_filter: optional library filter value (default: key, use 'weak' for needs review)
 *   - label, count (int, optional), active (bool, filter mode)
 *   - filter_label_attr: optional data-bbai-filter-label
 *   - attention: bool (missing/weak emphasis in library)
 *   Navigate-only: href, item_aria_label, status_segment, status_filter, status_url, metric_key (dashboard JS)
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_fg_variant = (($bbai_ui['variant'] ?? 'horizontal') === 'vertical') ? 'vertical' : 'horizontal';
$bbai_fg_mode = (($bbai_ui['interaction_mode'] ?? $bbai_ui['mode'] ?? 'filter') === 'navigate') ? 'navigate' : 'filter';
$bbai_fg_items = isset($bbai_ui['items']) && is_array($bbai_ui['items']) ? $bbai_ui['items'] : [];
$bbai_fg_id = trim((string) ($bbai_ui['id'] ?? ''));
$bbai_fg_aria = trim((string) ($bbai_ui['aria_label'] ?? ''));
$bbai_fg_default = (string) ($bbai_ui['default_filter'] ?? '');
$bbai_fg_root_class = trim((string) ($bbai_ui['root_class'] ?? ''));

$bbai_fg_classes = array_filter([
    'bbai-filter-group',
    'bbai-filter-group--' . $bbai_fg_variant,
    'bbai-filter-group--' . $bbai_fg_mode,
    $bbai_fg_root_class,
]);

$bbai_fg_label = $bbai_fg_aria !== '' ? $bbai_fg_aria : (
    $bbai_fg_mode === 'navigate'
        ? __('Open filtered views in ALT Library', 'beepbeep-ai-alt-text-generator')
        : __('Filter images by state', 'beepbeep-ai-alt-text-generator')
);

$bbai_open = static function (string $tag, array $attrs): void {
    $parts = [];
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === false || $v === '') {
            continue;
        }
        $parts[] = sprintf('%s="%s"', $k, esc_attr(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v));
    }
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above.
    echo '<' . $tag . ( $parts ? ' ' . implode(' ', $parts) : '' ) . '>';
};

if ($bbai_fg_mode === 'navigate') {
    $bbai_open('nav', [
        'class' => implode(' ', $bbai_fg_classes),
        'id' => $bbai_fg_id !== '' ? $bbai_fg_id : null,
        'aria-label' => $bbai_fg_label,
    ]);
} else {
    $nav_attrs = [
        'class' => implode(' ', $bbai_fg_classes),
        'role' => 'group',
        'aria-label' => $bbai_fg_label,
    ];
    if ($bbai_fg_id !== '') {
        $nav_attrs['id'] = $bbai_fg_id;
    }
    if ($bbai_fg_default !== '') {
        $nav_attrs['data-bbai-default-filter'] = $bbai_fg_default;
    }
    $bbai_open('div', $nav_attrs);
}

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

    if ($bbai_fg_mode === 'navigate') {
        $bbai_href = isset($bbai_item['href']) ? (string) $bbai_item['href'] : '';
        $bbai_item_aria = isset($bbai_item['item_aria_label']) ? (string) $bbai_item['item_aria_label'] : '';
        $bbai_filt_default = '';
        if ($bbai_key === 'weak') {
            $bbai_filt_default = 'needs_review';
        } elseif ($bbai_key !== 'all') {
            $bbai_filt_default = $bbai_key;
        }
        $bbai_seg = array_key_exists('status_segment', $bbai_item)
            ? (string) $bbai_item['status_segment']
            : ($bbai_key === 'all' ? '' : $bbai_key);
        $bbai_filt = array_key_exists('status_filter', $bbai_item) ? (string) $bbai_item['status_filter'] : $bbai_filt_default;
        $bbai_status_url = isset($bbai_item['status_url']) ? (string) $bbai_item['status_url'] : $bbai_href;
        $bbai_metric = isset($bbai_item['metric_key']) ? (string) $bbai_item['metric_key'] : $bbai_key;
        $bbai_count = array_key_exists('count', $bbai_item) ? (int) $bbai_item['count'] : 0;

        $bbai_link_classes = array_filter([
            'bbai-filter-group__item',
            'bbai-filter-group__item--vertical',
            'bbai-command-breakdown',
            'bbai-command-breakdown--' . $bbai_slug,
        ]);

        $bbai_anchor_attrs = [
            'class' => implode(' ', $bbai_link_classes),
            'href' => $bbai_href !== '' ? esc_url($bbai_href) : esc_url(admin_url('admin.php?page=bbai-library')),
            'data-bbai-status-row' => '1',
            'aria-label' => $bbai_item_aria !== '' ? $bbai_item_aria : $bbai_item['label'],
        ];

        if ($bbai_seg !== '') {
            $bbai_anchor_attrs['data-bbai-status-segment'] = $bbai_seg;
        }

        if ($bbai_filt !== '') {
            $bbai_anchor_attrs['data-bbai-status-filter'] = $bbai_filt;
        }

        if ($bbai_status_url !== '') {
            $bbai_anchor_attrs['data-bbai-status-url'] = esc_url($bbai_status_url);
        }

        $bbai_open('a', $bbai_anchor_attrs);
        ?>
        <span class="bbai-command-breakdown__label"><?php echo esc_html((string) ($bbai_item['label'] ?? '')); ?></span>
        <span class="bbai-command-breakdown__meta">
            <span class="bbai-command-breakdown__value" data-bbai-status-metric="<?php echo esc_attr($bbai_metric); ?>"><?php echo esc_html(number_format_i18n($bbai_count)); ?></span>
            <span class="bbai-command-breakdown__arrow" aria-hidden="true">→</span>
        </span>
        </a>
        <?php
        continue;
    }

    // Filter mode (library).
    $bbai_df = isset($bbai_item['data_filter']) ? sanitize_key((string) $bbai_item['data_filter']) : $bbai_key;
    $bbai_active = !empty($bbai_item['active']);
    $bbai_attention = !empty($bbai_item['attention']);
    $bbai_fl = isset($bbai_item['filter_label_attr']) ? (string) $bbai_item['filter_label_attr'] : (string) ($bbai_item['label'] ?? '');
    // Use array_key_exists so 0 is kept; isset() is false when count is null.
    $bbai_count_f = array_key_exists('count', $bbai_item) ? (int) $bbai_item['count'] : 0;

    $bbai_btn_classes = array_filter([
        'bbai-filter-group__item',
        'bbai-filter-group__item--horizontal',
        'bbai-filter-group__item--status-' . $bbai_slug,
        $bbai_active ? 'bbai-filter-group__item--active' : '',
        $bbai_attention ? 'bbai-filter-group__item--attention' : '',
    ]);

    $bbai_btn_attrs = [
        'type' => 'button',
        'class' => trim(implode(' ', $bbai_btn_classes)),
        'data-filter' => $bbai_df,
        'data-bbai-filter-label' => $bbai_fl,
        'aria-pressed' => $bbai_active ? 'true' : 'false',
    ];

    if ($bbai_active) {
        $bbai_btn_attrs['aria-current'] = 'true';
    }

    $bbai_open('button', $bbai_btn_attrs);
    ?>
    <span class="bbai-filter-group__status-dot" aria-hidden="true"></span>
    <span class="bbai-filter-group__label"><?php echo esc_html((string) ($bbai_item['label'] ?? '')); ?></span>
    <span class="bbai-filter-group__count"><?php echo esc_html(number_format_i18n($bbai_count_f)); ?></span>
    </button>
    <?php
}

echo $bbai_fg_mode === 'navigate' ? '</nav>' : '</div>';
