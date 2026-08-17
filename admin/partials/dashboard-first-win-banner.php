<?php
/**
 * First successful ALT — compact status strip (not a second command banner).
 *
 * Expects: $bbai_coverage_percent (int 0–100), optimized count in $bbai_optimized_count or $optimizedCount.
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_fw_optimized = 0;
if (isset($bbai_optimized_count)) {
    $bbai_fw_optimized = max(0, (int) $bbai_optimized_count);
} elseif (isset($optimizedCount)) {
    $bbai_fw_optimized = max(0, (int) $optimizedCount);
}

if ($bbai_fw_optimized <= 0) {
    return;
}

$bbai_cov = isset($bbai_coverage_percent) ? max(0, min(100, (int) $bbai_coverage_percent)) : 0;

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';

ob_start();
bbai_ui_render(
    'ui-button',
    [
        'label'      => bbai_copy_cta_generate_missing_images(),
        'variant'    => 'primary',
        'attributes' => [
            'data-action'           => 'generate-missing',
            'data-bbai-starter-cap' => '10',
        ],
    ]
);
$bbai_fw_primary = (string) ob_get_clean();

ob_start();
bbai_ui_render(
    'ui-button',
    [
        'label'      => __('Got it', 'beepbeep-ai-alt-text-generator'),
        'variant'    => 'secondary',
        'attributes' => ['data-bbai-dismiss-first-win' => '1'],
    ]
);
$bbai_fw_secondary = (string) ob_get_clean();

$bbai_fw_body = sprintf(
    /* translators: %s: coverage percentage */
    __('Screen readers and search engines can better understand this image. Coverage is now about %s%% optimised — keep going across your library.', 'beepbeep-ai-alt-text-generator'),
    number_format_i18n($bbai_cov)
);

?>
<section
    class="bbai-status-strip bbai-first-win-strip"
    hidden
    data-bbai-first-win-strip="1"
    aria-label="<?php esc_attr_e('First success', 'beepbeep-ai-alt-text-generator'); ?>"
>
    <div class="bbai-status-strip__main">
        <p class="bbai-status-strip__eyebrow"><?php esc_html_e('Milestone', 'beepbeep-ai-alt-text-generator'); ?></p>
        <p class="bbai-status-strip__title"><?php esc_html_e('Nice — your first ALT is live', 'beepbeep-ai-alt-text-generator'); ?></p>
        <p class="bbai-status-strip__text"><?php echo esc_html($bbai_fw_body); ?></p>
    </div>
    <div class="bbai-status-strip__actions bbai-status-strip__actions--row">
        <?php echo $bbai_fw_primary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php echo $bbai_fw_secondary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</section>
<script>
(function () {
    var key = 'bbaiFirstWinDismissed_v1';
    try {
        if (window.localStorage && window.localStorage.getItem(key) === '1') {
            return;
        }
    } catch (e) {}
    var el = document.querySelector('[data-bbai-first-win-strip]');
    if (el) {
        el.removeAttribute('hidden');
    }
    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) {
            return;
        }
        var btn = t.closest('[data-bbai-dismiss-first-win]');
        if (!btn) {
            return;
        }
        try {
            window.localStorage && window.localStorage.setItem(key, '1');
        } catch (e2) {}
        var wrap = document.querySelector('[data-bbai-first-win-strip]');
        if (wrap) {
            wrap.setAttribute('hidden', 'hidden');
        }
    });
})();
</script>
