<?php
/**
 * Unified Banner component wrapper.
 *
 * Props:
 * - type: warning|error|info
 * - title: string
 * - description: string
 * - primaryCTA: [label, href?, attributes?]
 * - secondaryCTA: [label, href?, attributes?] (optional)
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_type = (string) ($bbai_ui['type'] ?? 'info');
$bbai_title = trim((string) ($bbai_ui['title'] ?? ''));
$bbai_description = trim((string) ($bbai_ui['description'] ?? ''));
$bbai_primary = isset($bbai_ui['primaryCTA']) && is_array($bbai_ui['primaryCTA']) ? $bbai_ui['primaryCTA'] : [];
$bbai_secondary = isset($bbai_ui['secondaryCTA']) && is_array($bbai_ui['secondaryCTA']) ? $bbai_ui['secondaryCTA'] : [];
$bbai_extra_class = trim((string) ($bbai_ui['extra_class'] ?? ''));
$bbai_subtext_attrs = isset($bbai_ui['description_attrs']) && is_array($bbai_ui['description_attrs']) ? $bbai_ui['description_attrs'] : [];

if ('' === $bbai_title) {
    return;
}

require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/banner-system.php';

$bbai_variant = 'info';
$bbai_tone = 'attention';
if ('warning' === $bbai_type) {
    $bbai_variant = 'warning';
    $bbai_tone = 'attention';
} elseif ('error' === $bbai_type) {
    $bbai_variant = 'warning';
    $bbai_tone = 'paused';
}

$bbai_primary_html = '';
$bbai_secondary_html = '';

if (!empty($bbai_primary['label'])) {
    ob_start();
    bbai_ui_render(
        'ui-button',
        [
            'label'      => (string) $bbai_primary['label'],
            'variant'    => 'primary',
            'href'       => (string) ($bbai_primary['href'] ?? ''),
            'attributes' => isset($bbai_primary['attributes']) && is_array($bbai_primary['attributes']) ? $bbai_primary['attributes'] : [],
        ]
    );
    $bbai_primary_html = (string) ob_get_clean();
}

if (!empty($bbai_secondary['label'])) {
    ob_start();
    bbai_ui_render(
        'ui-button',
        [
            'label'      => (string) $bbai_secondary['label'],
            'variant'    => 'secondary',
            'href'       => (string) ($bbai_secondary['href'] ?? ''),
            'attributes' => isset($bbai_secondary['attributes']) && is_array($bbai_secondary['attributes']) ? $bbai_secondary['attributes'] : [],
        ]
    );
    $bbai_secondary_html = (string) ob_get_clean();
}

$bbai_payload = [
    'variant'            => $bbai_variant,
    'tone'               => $bbai_tone,
    'title'              => $bbai_title,
    'body'               => $bbai_description,
    'heading_tag'        => 'h2',
    'primary_html'       => $bbai_primary_html,
    'secondary_html'     => $bbai_secondary_html,
    'subtext_attrs'      => $bbai_subtext_attrs,
    'section_extra_class'=> trim('bbai-unified-banner ' . $bbai_extra_class),
];

bbai_ui_render(
    'bbai-banner',
    ['command_hero' => bbai_banner_inline_payload_to_command_hero($bbai_payload)]
);

