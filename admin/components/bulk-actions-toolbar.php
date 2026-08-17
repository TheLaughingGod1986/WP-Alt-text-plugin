<?php
/**
 * Bulk Actions Toolbar component.
 * Appears when one or more table rows are selected.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="bbai-bulk-actions-toolbar" id="bbai-bulk-actions-toolbar" aria-live="polite" aria-hidden="true" style="display: none;">
    <span class="bbai-bulk-actions-toolbar__count" id="bbai-selected-count">0 <?php esc_html_e('images selected', 'beepbeep-ai-alt-text-generator'); ?></span>
    <div class="bbai-bulk-actions-toolbar__actions">
        <button type="button"
                class="bbai-bulk-actions-toolbar__btn bbai-bulk-actions-toolbar__btn--primary <?php echo esc_attr(isset($bbai_limit_reached_state) && $bbai_limit_reached_state ? 'bbai-is-locked' : ''); ?>"
                id="bbai-batch-regenerate"
                data-action="regenerate-selected"
                <?php if (isset($bbai_limit_reached_state) && $bbai_limit_reached_state) : ?>
                    data-bbai-action="open-upgrade"
                    data-bbai-locked-cta="1"
                    data-bbai-lock-reason="reoptimize_all"
                    data-bbai-locked-source="library-batch-regenerate"
                    aria-disabled="true"
                    title="<?php esc_attr_e('Upgrade to let AI finish the remaining images', 'beepbeep-ai-alt-text-generator'); ?>"
                <?php endif; ?>
                aria-label="<?php esc_attr_e('Re-optimise selected images', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 8a6 6 0 0110.3-4.2M14 8a6 6 0 01-10.3 4.2" stroke-linecap="round"/><path d="M12 1v3.5h-3.5M4 15v-3.5h3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php esc_html_e('Re-optimise selected', 'beepbeep-ai-alt-text-generator'); ?>
        </button>
        <button type="button" class="bbai-bulk-actions-toolbar__btn" id="bbai-batch-reviewed" data-action="mark-reviewed" aria-label="<?php esc_attr_e('Mark selected as reviewed', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 8l3 3 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?php esc_html_e('Mark reviewed', 'beepbeep-ai-alt-text-generator'); ?>
        </button>
        <button type="button" class="bbai-bulk-actions-toolbar__btn" id="bbai-batch-export" data-action="export-alt-text" aria-label="<?php esc_attr_e('Export ALT text for selected', 'beepbeep-ai-alt-text-generator'); ?>">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 10v3h10v-3M8 2v8M5 5l3-3 3 3"/></svg>
            <?php esc_html_e('Export ALT text', 'beepbeep-ai-alt-text-generator'); ?>
        </button>
    </div>
</div>
