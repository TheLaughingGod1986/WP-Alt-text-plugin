<?php
/**
 * Section title block: optional eyebrow, heading, description, optional actions column.
 *
 * $bbai_ui keys:
 * - class: root element class(es)
 * - eyebrow, title, description: strings
 * - eyebrow_class, title_class, description_class: override element classes
 * - actions_html: pre-escaped HTML for right column
 * - title_tag: h2 | h3 (default h2)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_root_class = isset($bbai_ui['class']) ? trim((string) $bbai_ui['class']) : 'bbai-ui-section-header bbai-section-header';
$bbai_eyebrow = trim((string) ($bbai_ui['eyebrow'] ?? ''));
$bbai_title = (string) ($bbai_ui['title'] ?? '');
$bbai_description = trim((string) ($bbai_ui['description'] ?? ''));
$bbai_actions = (string) ($bbai_ui['actions_html'] ?? '');
$bbai_eyebrow_class = isset($bbai_ui['eyebrow_class']) ? trim((string) $bbai_ui['eyebrow_class']) : 'bbai-ui-section-header__eyebrow bbai-section-label bbai-card-label';
$bbai_title_class = isset($bbai_ui['title_class']) ? trim((string) $bbai_ui['title_class']) : 'bbai-ui-section-header__title bbai-section-title bbai-card-title';
$bbai_desc_class = isset($bbai_ui['description_class']) ? trim((string) $bbai_ui['description_class']) : 'bbai-ui-section-header__description bbai-section-description bbai-card-description';
$bbai_title_tag = isset($bbai_ui['title_tag']) && 'h3' === (string) $bbai_ui['title_tag'] ? 'h3' : 'h2';
?>
<header class="<?php echo esc_attr($bbai_root_class); ?>">
    <div class="bbai-ui-section-header__text">
        <?php if ('' !== $bbai_eyebrow) : ?>
            <p class="<?php echo esc_attr($bbai_eyebrow_class); ?>"><?php echo esc_html($bbai_eyebrow); ?></p>
        <?php endif; ?>
        <?php if ('' !== $bbai_title) : ?>
            <<?php echo esc_attr($bbai_title_tag); ?> class="<?php echo esc_attr($bbai_title_class); ?>"><?php echo esc_html($bbai_title); ?></<?php echo esc_attr($bbai_title_tag); ?>>
        <?php endif; ?>
        <?php if ('' !== $bbai_description) : ?>
            <p class="<?php echo esc_attr($bbai_desc_class); ?>"><?php echo esc_html($bbai_description); ?></p>
        <?php endif; ?>
    </div>
    <?php if ('' !== $bbai_actions) : ?>
        <div class="bbai-ui-section-header__actions"><?php echo $bbai_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>
</header>
