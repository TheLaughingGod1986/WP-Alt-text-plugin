<?php
/**
 * Right-column sidebar / product rail card.
 *
 * $bbai_ui keys:
 * - title: heading text
 * - body: supporting copy (plain text)
 * - context_class: page rail modifier (e.g. bbai-analytics-rail--context)
 * - aria_label: aside accessible name
 * - append_html: pre-escaped HTML after body (links, extra paragraphs)
 * - foot_html: pre-escaped inner foot content (e.g. rail actions); wrapped in bbai-product-rail__foot
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_title = (string) ($bbai_ui['title'] ?? '');
$bbai_body = (string) ($bbai_ui['body'] ?? '');
$bbai_body_class = 'bbai-product-rail__meta bbai-ui-sidebar-card__text';
$bbai_context = isset($bbai_ui['context_class']) ? trim((string) $bbai_ui['context_class']) : '';
$bbai_aria = isset($bbai_ui['aria_label']) ? trim((string) $bbai_ui['aria_label']) : '';
$bbai_append = (string) ($bbai_ui['append_html'] ?? '');
$bbai_foot = (string) ($bbai_ui['foot_html'] ?? '');
$bbai_aside_class = trim('bbai-sidebar bbai-sidebar-sticky bbai-product-rail bbai-ui-sidebar-card ' . $bbai_context);
$bbai_aria_attr = '' !== $bbai_aria ? ' aria-label="' . esc_attr($bbai_aria) . '"' : '';
?>
<aside class="<?php echo esc_attr($bbai_aside_class); ?>"<?php echo $bbai_aria_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <div class="bbai-product-rail__inner bbai-ui-sidebar-card__inner">
        <?php if ('' !== $bbai_title) : ?>
            <h3 class="bbai-product-rail__title bbai-ui-sidebar-card__title"><?php echo esc_html($bbai_title); ?></h3>
        <?php endif; ?>
        <?php if ('' !== $bbai_body) : ?>
            <p class="<?php echo esc_attr($bbai_body_class); ?> bbai-ui-sidebar-card__body"><?php echo esc_html($bbai_body); ?></p>
        <?php endif; ?>
        <?php echo $bbai_append; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if ('' !== $bbai_foot) : ?>
            <div class="bbai-product-rail__foot bbai-ui-sidebar-card__foot"><?php echo $bbai_foot; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <?php endif; ?>
    </div>
</aside>
