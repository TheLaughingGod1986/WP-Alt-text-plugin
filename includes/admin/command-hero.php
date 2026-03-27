<?php
/**
 * Shared command hero / banner helpers (Dashboard, Library, Analytics).
 *
 * @package BeepBeep_AI
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SVG icon markup by visual tone (matches dashboard-success-banner).
 */
function bbai_command_hero_icon_markup(string $tone): string
{
    if ('setup' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/><path d="M20 20L16.2 16.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    if ('attention' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M12 3L21 19H3L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9V13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="currentColor"/></svg>';
    }

    if ('paused' === $tone) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M10 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 9V15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M22 11.08V12A10 10 0 1 1 12 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01L9 11.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * Icon for unified inline banners (variant + tone).
 *
 * @param string $variant success|warning|info|neutral
 */
function bbai_command_hero_icon_markup_for_banner_variant(string $variant, string $tone = 'healthy'): string
{
    if ('warning' === $variant) {
        return bbai_command_hero_icon_markup('paused' === $tone ? 'paused' : ('attention' === $tone ? 'attention' : 'attention'));
    }
    if ('info' === $variant) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 10V16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="7" r="1" fill="currentColor"/></svg>';
    }
    if ('neutral' === $variant) {
        return '<svg viewBox="0 0 24 24" fill="none" focusable="false" width="22" height="22"><path d="M4 19V5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 15h4l3-6 4 10 3-4h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    return bbai_command_hero_icon_markup('healthy');
}

/**
 * Render primary / secondary / tertiary CTA using dashboard command-hero classes.
 *
 * @param array<string, mixed>|null $action Same shape as analytics-tab make_button_action / make_link_action.
 * @param string                    $kind   primary|secondary|tertiary
 */
function bbai_command_hero_render_action(?array $action, string $kind): string
{
    if (empty($action['label'])) {
        return '';
    }

    if (!function_exists('bbai_ui_render')) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
    }

    $variant = 'secondary';
    if ('primary' === $kind) {
        $variant = 'primary';
    } elseif ('tertiary' === $kind) {
        $variant = 'tertiary';
    }

    $attrs = isset($action['attributes']) && is_array($action['attributes']) ? $action['attributes'] : [];
    if ('primary' === $kind) {
        $attrs['data-bbai-hero-generator-cta'] = '1';
    } elseif ('secondary' === $kind) {
        $attrs['data-bbai-hero-library-cta'] = '1';
    }

    ob_start();
    bbai_ui_render(
        'ui-button',
        [
            'label'      => (string) $action['label'],
            'variant'    => $variant,
            'href'       => (string) ($action['href'] ?? ''),
            'attributes' => $attrs,
        ]
    );

    return (string) ob_get_clean();
}

/**
 * Render a Library workspace action (label + raw HTML attribute string) as command-hero CTA markup.
 *
 * Same visual classes as bbai_command_hero_render_action(); attrs are assembled in library-workspace
 * via $bbai_build_action (already escaped).
 *
 * @param array<string, mixed>|null $action Keys: label, attrs (string), optional is_locked.
 */
function bbai_command_hero_render_library_action(?array $action, string $kind): string
{
    if (empty($action['label'])) {
        return '';
    }

    if (!function_exists('bbai_ui_render')) {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/ui-components.php';
    }

    ob_start();
    bbai_ui_render(
        'ui-button',
        [
            'label'      => (string) $action['label'],
            'variant'    => 'primary' === $kind ? 'primary' : 'secondary',
            'attrs_raw'  => trim((string) ($action['attrs'] ?? '')),
            'is_locked'  => !empty($action['is_locked']),
        ]
    );

    return (string) ob_get_clean();
}
