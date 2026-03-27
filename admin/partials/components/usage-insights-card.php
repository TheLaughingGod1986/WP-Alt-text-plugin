<?php
/**
 * Usage insights — standard card (not a banner). Same data as former inline-banner block.
 *
 * Expects in scope:
 * - $bbai_ins_stack_lines: string[] body lines (lead + detail rows)
 * - $bbai_ins_chips: optional array of [ 'label' => string ]
 * - $bbai_ins_tone: healthy|warning|danger
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_uic_stack = isset($bbai_ins_stack_lines) && is_array($bbai_ins_stack_lines) ? $bbai_ins_stack_lines : [];
$bbai_uic_chips = isset($bbai_ins_chips) && is_array($bbai_ins_chips) ? $bbai_ins_chips : [];
$bbai_uic_tone  = isset($bbai_ins_tone) ? (string) $bbai_ins_tone : 'healthy';
if (!in_array($bbai_uic_tone, ['healthy', 'warning', 'danger'], true)) {
    $bbai_uic_tone = 'healthy';
}

?>
<section
    id="bbai-usage-insights"
    class="bbai-card bbai-usage-insights-card bbai-usage-insights-card--<?php echo esc_attr($bbai_uic_tone); ?>"
    data-bbai-usage-insights="1"
    data-bbai-usage-insights-tone="<?php echo esc_attr($bbai_uic_tone); ?>"
    aria-labelledby="bbai-usage-insights-heading"
>
    <header class="bbai-usage-insights-card__header bbai-ui-section-header bbai-section-header">
        <div class="bbai-ui-section-header__text">
            <p class="bbai-section-label bbai-card-label"><?php esc_html_e('Insights', 'beepbeep-ai-alt-text-generator'); ?></p>
            <h2 id="bbai-usage-insights-heading" class="bbai-usage-insights-card__title bbai-section-title bbai-card-title"><?php esc_html_e('Usage insights', 'beepbeep-ai-alt-text-generator'); ?></h2>
        </div>
    </header>

    <div class="bbai-usage-insights-card__body">
        <div class="bbai-card-body-stack">
        <?php foreach ($bbai_uic_stack as $bbai_uic_line) : ?>
            <?php
            $bbai_uic_line = trim((string) $bbai_uic_line);
            if ('' === $bbai_uic_line) {
                continue;
            }
            ?>
            <p class="bbai-usage-insights-card__line"><?php echo esc_html($bbai_uic_line); ?></p>
        <?php endforeach; ?>
        </div>

        <?php if (!empty($bbai_uic_chips)) : ?>
            <ul class="bbai-usage-insights-card__chips bbai-card-chip-row" aria-label="<?php esc_attr_e('Supporting metrics', 'beepbeep-ai-alt-text-generator'); ?>">
                <?php foreach ($bbai_uic_chips as $bbai_ins_chip) : ?>
                    <?php
                    $bbai_chip_label = isset($bbai_ins_chip['label']) ? (string) $bbai_ins_chip['label'] : '';
                    if ('' === $bbai_chip_label) {
                        continue;
                    }
                    ?>
                    <li><span class="bbai-badge bbai-badge--neutral"><?php echo esc_html($bbai_chip_label); ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="bbai-usage-insights-card__footer bbai-card-section">
            <a href="#bbai-usage-activity" class="bbai-link bbai-link--action"><?php esc_html_e('Open usage activity log', 'beepbeep-ai-alt-text-generator'); ?></a>
        </p>
    </div>
</section>
