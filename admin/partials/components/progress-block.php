<?php
/**
 * Simple single-bar progress (Usage hub, future shared meters).
 * Library multi-segment coverage keeps bespoke markup + bbai-ui-progress-block wrapper in situ.
 *
 * $bbai_ui keys:
 * - title: main label
 * - subtitle: optional smaller line
 * - percent: 0–100
 * - helper: optional copy below bar
 * - variant: success | warning | info | neutral
 * - bar_class: optional extra class on the track
 * - fill_class: optional extra class on the fill
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_title = (string) ($bbai_ui['title'] ?? '');
$bbai_subtitle = trim((string) ($bbai_ui['subtitle'] ?? ''));
$bbai_percent = isset($bbai_ui['percent']) ? (int) $bbai_ui['percent'] : 0;
$bbai_percent = min(100, max(0, $bbai_percent));
$bbai_helper = trim((string) ($bbai_ui['helper'] ?? ''));
$bbai_variant_in = isset($bbai_ui['variant']) ? (string) $bbai_ui['variant'] : 'neutral';
$bbai_variant = in_array($bbai_variant_in, ['success', 'warning', 'info', 'neutral'], true) ? $bbai_variant_in : 'neutral';
$bbai_bar_class = isset($bbai_ui['bar_class']) ? trim((string) $bbai_ui['bar_class']) : '';
$bbai_fill_class = isset($bbai_ui['fill_class']) ? trim((string) $bbai_ui['fill_class']) : '';
$bbai_track = trim('bbai-ui-progress-block__track ' . $bbai_bar_class);
$bbai_fill = trim('bbai-ui-progress-block__fill bbai-ui-progress-block__fill--' . $bbai_variant . ' ' . $bbai_fill_class);
?>
<div class="bbai-ui-progress-block bbai-ui-progress-block--simple bbai-ui-progress-block--<?php echo esc_attr($bbai_variant); ?>">
    <?php if ('' !== $bbai_title || '' !== $bbai_subtitle) : ?>
        <div class="bbai-ui-progress-block__head">
            <?php if ('' !== $bbai_title) : ?>
                <p class="bbai-ui-progress-block__title"><?php echo esc_html($bbai_title); ?></p>
            <?php endif; ?>
            <?php if ('' !== $bbai_subtitle) : ?>
                <p class="bbai-ui-progress-block__subtitle"><?php echo esc_html($bbai_subtitle); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="<?php echo esc_attr($bbai_track); ?>" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $bbai_percent); ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="<?php echo esc_attr(trim($bbai_fill)); ?>" style="width: <?php echo esc_attr((string) $bbai_percent); ?>%;"></span>
    </div>
    <?php if ('' !== $bbai_helper) : ?>
        <p class="bbai-ui-progress-block__helper"><?php echo esc_html($bbai_helper); ?></p>
    <?php endif; ?>
</div>
