<?php
/**
 * Phase 14 — Returning-user retention strip (dashboard).
 *
 * Expects: $bbai_retention_strip from bbai_retention_build_strip_model().
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($bbai_retention_strip) || empty($bbai_retention_strip['show'])) {
    return;
}

$bbai_rs = $bbai_retention_strip;
$bbai_rs_trigger = isset($bbai_rs['trigger']) ? sanitize_key((string) $bbai_rs['trigger']) : '';
$bbai_rs_tel = isset($bbai_rs['telemetry']) && is_array($bbai_rs['telemetry']) ? $bbai_rs['telemetry'] : [];

$bbai_rs_render_attrs = static function (array $action): string {
    $href = isset($action['href']) && '' !== (string) $action['href'] ? (string) $action['href'] : '#';
    $parts = [
        'href="' . esc_url($href) . '"',
    ];
    if (!empty($action['action'])) {
        $parts[] = 'data-action="' . esc_attr((string) $action['action']) . '"';
    }
    if (!empty($action['bbai_action'])) {
        $parts[] = 'data-bbai-action="' . esc_attr((string) $action['bbai_action']) . '"';
    }
    if (!empty($action['aria_label'])) {
        $parts[] = 'aria-label="' . esc_attr((string) $action['aria_label']) . '"';
    }
    return implode(' ', $parts);
};

$bbai_rs_primary = $bbai_rs['primary'] ?? [];
$bbai_rs_secondary = $bbai_rs['secondary'] ?? [];
$bbai_rs_pp = (int) ($bbai_rs['progress_pct'] ?? 0);
?>

<section
    class="bbai-status-strip bbai-retention-strip"
    data-bbai-retention-strip="1"
    data-bbai-surface="status-strip"
    data-bbai-retention-trigger="<?php echo esc_attr($bbai_rs_trigger); ?>"
    data-bbai-telemetry-retention="dashboard"
    data-bbai-retention-telemetry="<?php echo esc_attr(wp_json_encode($bbai_rs_tel)); ?>"
    aria-label="<?php esc_attr_e('Optimisation status and next steps', 'beepbeep-ai-alt-text-generator'); ?>"
>
    <div class="bbai-retention-strip__main">
        <p class="bbai-retention-strip__headline"><?php echo esc_html((string) ($bbai_rs['headline'] ?? '')); ?></p>
        <?php if (!empty($bbai_rs['supporting'])) : ?>
            <p class="bbai-retention-strip__supporting"><?php echo esc_html((string) $bbai_rs['supporting']); ?></p>
        <?php endif; ?>
        <?php if (!empty($bbai_rs['delta_line'])) : ?>
            <p class="bbai-retention-strip__delta"><?php echo esc_html((string) $bbai_rs['delta_line']); ?></p>
        <?php endif; ?>
    </div>

    <div class="bbai-retention-strip__progress" role="group" aria-label="<?php esc_attr_e('Coverage progress', 'beepbeep-ai-alt-text-generator'); ?>">
        <div class="bbai-retention-strip__bar" role="progressbar" aria-valuenow="<?php echo esc_attr((string) $bbai_rs_pp); ?>" aria-valuemin="0" aria-valuemax="100">
            <span class="bbai-retention-strip__bar-fill" style="width: <?php echo esc_attr((string) $bbai_rs_pp); ?>%;"></span>
        </div>
        <?php if (!empty($bbai_rs['processed_line'])) : ?>
            <p class="bbai-retention-strip__processed"><?php echo esc_html((string) $bbai_rs['processed_line']); ?></p>
        <?php endif; ?>
    </div>

    <div class="bbai-retention-strip__actions">
        <?php if (!empty($bbai_rs_primary['label'])) : ?>
            <a class="bbai-btn bbai-btn-primary bbai-btn-sm" <?php echo $bbai_rs_render_attrs($bbai_rs_primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php echo esc_html((string) $bbai_rs_primary['label']); ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($bbai_rs_secondary['label'])) : ?>
            <a class="bbai-btn bbai-btn-secondary bbai-btn-sm" <?php echo $bbai_rs_render_attrs($bbai_rs_secondary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php echo esc_html((string) $bbai_rs_secondary['label']); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($bbai_rs['upgrade_hint'])) : ?>
        <p class="bbai-retention-strip__upgrade-hint"><?php echo esc_html((string) $bbai_rs['upgrade_hint']); ?></p>
    <?php endif; ?>
</section>
