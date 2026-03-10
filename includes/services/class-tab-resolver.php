<?php
/**
 * Tab resolution helper for BeepBeep AI.
 *
 * Maps page slugs and URL parameters to a valid tab key.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class Tab_Resolver {
    /**
     * Resolve a tab value from GET and page slug against an allowed tab list.
     *
     * @param array  $tabs      Allowed tabs (key => label).
     * @param string $page_slug Current page slug (e.g., bbai-guide).
     * @param string|null $get_tab Tab from URL param.
     * @param string $default_tab Fallback tab key.
     * @return string Resolved tab key.
     */
    public static function resolve(array $tabs, string $page_slug, ?string $get_tab, string $default_tab = 'dashboard'): string {
        $allowed = array_keys($tabs);
        // Use explicit tab if valid.
        if ($get_tab && in_array($get_tab, $allowed, true)) {
            return $get_tab;
        }

        // Map page slug to tab if valid.
        $mapped = self::map_page_to_tab($page_slug);
        if ($mapped && in_array($mapped, $allowed, true)) {
            return $mapped;
        }

        // Fallback to default or first tab.
        if (in_array($default_tab, $allowed, true)) {
            return $default_tab;
        }
        return reset($allowed) ?: $default_tab;
    }

    /**
     * Map a WP admin page slug to a tab key.
     *
     * @param string $page_slug
     * @return string|null
     */
    public static function map_page_to_tab(string $page_slug): ?string {
        switch ($page_slug) {
            case 'bbai-library':
                return 'library';
            case 'bbai-credit-usage':
                return 'credit-usage';
            case 'bbai-guide':
                return 'guide';
            case 'bbai-settings':
                return 'settings';
            case 'bbai':
                return 'dashboard';
            default:
                return null;
        }
    }
}
