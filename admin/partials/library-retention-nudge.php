<?php
/**
 * Phase 14 — Compact retention nudge on ALT Library workspace.
 *
 * Expects: $bbai_retention_nudge from bbai_retention_build_library_nudge().
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($bbai_retention_nudge) || empty($bbai_retention_nudge['line'])) {
    return;
}

$bbai_rn = $bbai_retention_nudge;
$bbai_rn_primary = $bbai_rn['primary'] ?? [];
$bbai_rn_tel = isset($bbai_rn['telemetry']) && is_array($bbai_rn['telemetry']) ? $bbai_rn['telemetry'] : [];

$bbai_rn_attrs = static function (array $action): string {
    $href = isset($action['href']) && '' !== (string) $action['href'] ? (string) $action['href'] : '#';
    $parts = [ 'href="' . esc_url($href) . '"' ];
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
?>

<div
    class="bbai-retention-library-nudge"
    data-bbai-retention-strip="1"
    data-bbai-retention-trigger="library_compact"
    data-bbai-telemetry-retention="alt_library"
    data-bbai-retention-telemetry="<?php echo esc_attr(wp_json_encode($bbai_rn_tel)); ?>"
    role="status"
>
    <p class="bbai-retention-library-nudge__line">
        <span class="bbai-retention-library-nudge__text"><?php echo esc_html((string) $bbai_rn['line']); ?></span>
        <?php if (!empty($bbai_rn['meta'])) : ?>
            <span class="bbai-retention-library-nudge__meta"><?php echo esc_html((string) $bbai_rn['meta']); ?></span>
        <?php endif; ?>
    </p>
    <?php if (!empty($bbai_rn_primary['label'])) : ?>
        <a class="bbai-retention-library-nudge__cta" <?php echo $bbai_rn_attrs($bbai_rn_primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php echo esc_html((string) $bbai_rn_primary['label']); ?>
        </a>
    <?php endif; ?>
</div>
