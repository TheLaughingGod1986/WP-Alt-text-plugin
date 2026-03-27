<?php
/**
 * Command-hero / banner CTA — shared variant system (primary, secondary, tertiary, warning).
 *
 * $bbai_ui keys:
 * - label: string
 * - variant: primary | secondary | tertiary | warning
 * - href: optional URL for <a>
 * - attributes: array<string, scalar> extra attributes (escaped)
 * - attrs_raw: optional raw attribute string (Library workspace; pre-escaped)
 * - is_locked: bool on primary
 *
 * @package BeepBeep_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

$bbai_ui = isset($bbai_ui) && is_array($bbai_ui) ? $bbai_ui : [];
$bbai_label = isset($bbai_ui['label']) ? (string) $bbai_ui['label'] : '';
if ('' === trim($bbai_label)) {
    return;
}

$bbai_variant = isset($bbai_ui['variant']) ? strtolower((string) $bbai_ui['variant']) : 'secondary';
$bbai_href = isset($bbai_ui['href']) ? trim((string) $bbai_ui['href']) : '';
$bbai_attrs = isset($bbai_ui['attributes']) && is_array($bbai_ui['attributes']) ? $bbai_ui['attributes'] : [];
$bbai_raw = trim((string) ($bbai_ui['attrs_raw'] ?? ''));
$bbai_locked = !empty($bbai_ui['is_locked']);

$bbai_pairs = [];
foreach ($bbai_attrs as $bbai_n => $bbai_v) {
    if (null === $bbai_v || '' === $bbai_v) {
        continue;
    }
    $bbai_pairs[] = sprintf('%s="%s"', esc_attr((string) $bbai_n), esc_attr((string) $bbai_v));
}
$bbai_from_array = $bbai_pairs ? implode(' ', $bbai_pairs) : '';

$bbai_cls = '';
if ('primary' === $bbai_variant) {
    $bbai_cls = 'bbai-dashboard-hero__cta bbai-dashboard-hero__cta--primary bbai-banner__cta bbai-banner__cta--primary bbai-ui-btn bbai-ui-btn--primary bbai-btn bbai-btn-primary';
    if ($bbai_locked) {
        $bbai_cls .= ' bbai-is-locked';
    }
} elseif ('tertiary' === $bbai_variant) {
    $bbai_cls = 'bbai-dashboard-hero__link bbai-dashboard-hero__link--tertiary bbai-banner__link bbai-banner__link--tertiary bbai-ui-btn bbai-ui-btn--tertiary bbai-btn bbai-btn-ghost';
} elseif ('warning' === $bbai_variant) {
    $bbai_cls = 'bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary bbai-banner__link bbai-banner__link--secondary bbai-ui-btn bbai-ui-btn--secondary bbai-ui-btn--warning bbai-btn bbai-btn-secondary';
} else {
    $bbai_cls = 'bbai-dashboard-hero__link bbai-dashboard-hero__link--secondary bbai-banner__link bbai-banner__link--secondary bbai-ui-btn bbai-ui-btn--secondary bbai-btn bbai-btn-secondary';
}

$bbai_label_e = esc_html($bbai_label);

if ('' !== $bbai_raw && preg_match('/\bhref\s*=\s*"/', $bbai_raw)) {
    printf('<a class="%s" %s>%s</a>', esc_attr(trim($bbai_cls)), $bbai_raw, $bbai_label_e); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

if ('' !== $bbai_href) {
    $bbai_a = '' !== $bbai_from_array ? ' ' . $bbai_from_array : '';
    printf('<a href="%s" class="%s"%s>%s</a>', esc_url($bbai_href), esc_attr(trim($bbai_cls)), $bbai_a, $bbai_label_e); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}

$bbai_tail = trim($bbai_from_array . ($bbai_raw !== '' ? ' ' . $bbai_raw : ''));
$bbai_type = (false === stripos($bbai_tail, 'type=')) ? 'type="button" ' : '';
$bbai_sp = '' !== $bbai_tail ? ' ' : '';
printf('<button %sclass="%s"%s%s>%s</button>', $bbai_type, esc_attr(trim($bbai_cls)), $bbai_sp, $bbai_tail, $bbai_label_e); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
