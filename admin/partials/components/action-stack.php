<?php
/**
 * Banner / hero CTA column — shared by status-banner only.
 *
 * Expects `$bbai_action_stack_cfg` (array):
 * - has_primary, has_secondary, has_tertiary (bool)
 * - primary_html, secondary_html, tertiary_html, actions_append (pre-escaped strings)
 * - alignment: optional string (default | dense) for future layout tokens
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_cfg = isset($bbai_action_stack_cfg) && is_array($bbai_action_stack_cfg) ? $bbai_action_stack_cfg : [];
$bbai_hasp = !empty($bbai_cfg['has_primary']);
$bbai_hass = !empty($bbai_cfg['has_secondary']);
$bbai_hast = !empty($bbai_cfg['has_tertiary']);
$bbai_ph = (string) ($bbai_cfg['primary_html'] ?? '');
$bbai_sh = (string) ($bbai_cfg['secondary_html'] ?? '');
$bbai_th = (string) ($bbai_cfg['tertiary_html'] ?? '');
$bbai_ap = (string) ($bbai_cfg['actions_append'] ?? '');
?>
<div class="bbai-dashboard-hero-actions bbai-banner__actions bbai-page-hero__actions bbai-ui-action-stack bbai-ui-hero-action-stack" aria-label="<?php esc_attr_e('Recommended actions', 'beepbeep-ai-alt-text-generator'); ?>">
    <div class="bbai-ui-action-stack__primary bbai-ui-hero-action-stack__primary bbai-dashboard-hero-actions__item bbai-dashboard-hero-actions__item--primary" data-bbai-hero-primary-item<?php echo $bbai_hasp ? '' : ' hidden'; ?>>
        <?php echo $bbai_ph; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <div class="bbai-dashboard-hero__secondary-actions bbai-banner__secondary-actions bbai-ui-action-stack__secondary bbai-ui-hero-action-stack__secondary">
        <div class="bbai-dashboard-hero-actions__item bbai-ui-action-stack__item bbai-ui-hero-action-stack__item" data-bbai-hero-secondary-item<?php echo $bbai_hass ? '' : ' hidden'; ?>>
            <?php echo $bbai_sh; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div class="bbai-dashboard-hero-actions__item bbai-ui-action-stack__item bbai-ui-hero-action-stack__item" data-bbai-hero-tertiary-item<?php echo $bbai_hast ? '' : ' hidden'; ?>>
            <?php echo $bbai_th; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </div>
    <?php echo $bbai_ap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
