<?php
/**
 * Core Assets Trait
 * Handles script and style enqueuing for admin pages
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Assets {

    /**
     * Get asset version based on file modification time
     *
     * @param string $relative  Relative path to asset
     * @param string $fallback  Fallback version
     * @param string $base_path Base path to plugin
     * @return string Version string
     */
    private function get_asset_version(string $relative, string $fallback, string $base_path): string {
        $relative = ltrim($relative, '/');
        $path = $base_path . $relative;
        return file_exists($path) ? (string) filemtime($path) : $fallback;
    }

    /**
     * Get asset path with fallback to source if minified doesn't exist
     *
     * @param string $base       Base path (js/ or css/)
     * @param string $name       Asset name
     * @param bool   $debug      Whether in debug mode
     * @param string $type       Asset type (js or css)
     * @param string $base_path  Plugin base path
     * @return string Asset path
     */
    private function get_asset_path(string $base, string $name, bool $debug, string $type, string $base_path): string {
        $extension = $debug ? ".$type" : ".min.$type";
        $path = $base . $name . $extension;

        if ($debug && !file_exists($base_path . $path)) {
            $dist_base = str_replace('assets/src/', 'assets/dist/', $base);
            $dist_path = $dist_base . $name . ".min.$type";
            if (file_exists($base_path . $dist_path)) {
                return $dist_path;
            }
        }

        if (!$debug && !file_exists($base_path . $path)) {
            $source_base = str_replace('assets/dist/', 'assets/src/', $base);
            $source_path = $source_base . $name . ".$type";
            if (file_exists($base_path . $source_path)) {
                return $source_path;
            }
            // Final fallback: assets/js/ or assets/css/ (ZIP build moves src into these)
            $flat_path = "assets/$type/" . $name . ".$type";
            if (file_exists($base_path . $flat_path)) {
                return $flat_path;
            }
            // Fallback for bundle output (build-assets.js outputs .bundle.min.js)
            $bundle_path = "assets/$type/" . $name . ".bundle.min.$type";
            if (file_exists($base_path . $bundle_path)) {
                return $bundle_path;
            }
            return $source_path;
        }

        return $path;
    }

    /**
     * Get common localization strings
     *
     * @return array L10n strings
     */
    private function get_common_l10n(): array {
        return [
            'reviewCue'           => __('Visit the ALT Library to double-check the wording.', 'beepbeep-ai-alt-text-generator'),
            'statusReady'         => '',
            'previewAltHeading'   => __('Review generated ALT text', 'beepbeep-ai-alt-text-generator'),
            'previewAltHint'      => __('Review the generated description before applying it to your media item.', 'beepbeep-ai-alt-text-generator'),
            'previewAltApply'     => __('Use this ALT', 'beepbeep-ai-alt-text-generator'),
            'previewAltCancel'    => __('Keep current ALT', 'beepbeep-ai-alt-text-generator'),
            'previewAltDismissed' => __('Preview dismissed. Existing ALT kept.', 'beepbeep-ai-alt-text-generator'),
            'previewAltShortcut'  => __('Shift + Enter for newline.', 'beepbeep-ai-alt-text-generator'),
        ];
    }

    /**
     * Check if current hook is a BeepBeep AI admin page
     *
     * @param string $hook WordPress admin hook
     * @return bool
     */
    private function is_bbai_admin_page(string $hook): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $current_page = $page_input;
        return strpos($hook, 'toplevel_page_bbai') === 0
            || strpos($hook, 'bbai_page_bbai') === 0
            || strpos($hook, 'media_page_bbai') === 0
            || strpos($hook, '_page_bbai') !== false
            || (!empty($current_page) && strpos($current_page, 'bbai') === 0);
    }

    /**
     * Check if current tab is the dashboard (for lazy-loading dashboard scripts).
     *
     * @return bool
     */
    private function is_dashboard_tab(): bool {
        return $this->is_dashboard_main_page() && $this->get_resolved_tab() === 'dashboard';
    }

    /**
     * Check if current tab is the library (for enqueuing library filter script).
     *
     * @return bool
     */
    private function is_library_tab(): bool {
        return $this->get_resolved_tab() === 'library';
    }

    /**
     * Get the current BeepBeep AI admin page slug.
     *
     * @return string
     */
    private function get_current_admin_page(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    /**
     * Check if the current page is the main dashboard page.
     *
     * @return bool
     */
    private function is_dashboard_main_page(): bool {
        return $this->get_current_admin_page() === self::MENU_SLUG_DASHBOARD;
    }

    /**
     * Check if the current page is the dedicated onboarding page.
     *
     * @return bool
     */
    private function is_onboarding_page(): bool {
        return $this->get_current_admin_page() === self::MENU_SLUG_ONBOARDING;
    }

    /**
     * Resolve current tab from page slug and URL param.
     *
     * @return string
     */
    private function get_resolved_tab(): string {
        $page = $this->get_current_admin_page();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        $tab_aliases = [
            'credit-usage' => 'usage',
            'guide'        => 'help',
            'debug'        => 'settings',
        ];
        $page_to_tab = [
            'bbai' => 'dashboard',
            'bbai-library' => 'library',
            'bbai-analytics' => 'analytics',
            'bbai-credit-usage' => 'usage',
            'bbai-guide' => 'help',
            'bbai-settings' => 'settings',
            'bbai-debug' => 'settings',
            'bbai-agency-overview' => 'agency-overview',
            'bbai-onboarding' => 'onboarding',
        ];

        if ($tab !== '') {
            return $tab_aliases[ $tab ] ?? $tab;
        }

        $resolved = $page_to_tab[ $page ] ?? 'dashboard';
        return $tab_aliases[ $resolved ] ?? $resolved;
    }

    /**
     * Enqueue admin scripts for media library pages
     *
     * @param string $hook  Current admin hook
     * @param string $base_url Plugin base URL
     * @param string $base_path Plugin base path
     */
    private function enqueue_media_library_assets(string $hook, string $base_url, string $base_path): void {
        $use_debug_assets = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $js_base  = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';

        $admin_file = $this->get_asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js', $base_path);
        $admin_version = $this->get_asset_version($admin_file, '3.0.0', $base_path);

        $checkout_prices = $this->get_checkout_price_ids();
        $l10n_common = $this->get_common_l10n();

        wp_enqueue_script('bbai-admin', $base_url . $admin_file, ['jquery', 'wp-i18n'], $admin_version, true);
        wp_localize_script('bbai-admin', 'BBAI', [
            'nonce'     => wp_create_nonce('wp_rest'),
            'rest'      => esc_url_raw(rest_url('bbai/v1/')),
            'restAlt'   => esc_url_raw(rest_url('bbai/v1/alt/')),
            'restStats' => esc_url_raw(rest_url('bbai/v1/stats')),
            'restUsage' => esc_url_raw(rest_url('bbai/v1/usage')),
            'restMissing' => esc_url_raw(add_query_arg(['scope' => 'missing'], rest_url('bbai/v1/list'))),
            'restAll'    => esc_url_raw(add_query_arg(['scope' => 'all'], rest_url('bbai/v1/list'))),
            'restQueue'  => esc_url_raw(rest_url('bbai/v1/queue')),
            'restRoot'  => esc_url_raw(rest_url()),
            'restPlans' => esc_url_raw(rest_url('bbai/v1/plans')),
            'l10n'      => $l10n_common,
            'upgradeUrl' => esc_url(Usage_Tracker::get_upgrade_url()),
            'billingPortalUrl' => esc_url(Usage_Tracker::get_billing_portal_url()),
            'checkoutPrices' => $checkout_prices,
            'canManage' => $this->user_can_manage(),
            'inlineBatchSize' => defined('BBAI_INLINE_BATCH') ? max(1, intval(BBAI_INLINE_BATCH)) : 1,
            'wizard' => $this->get_setup_wizard_bootstrap(),
        ]);
        wp_localize_script('bbai-admin', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
            'admin_url' => admin_url('admin.php'),
            'admin_logout_confirm' => __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator'),
            'can_manage' => $this->user_can_manage(),
            'logout_redirect' => admin_url('admin.php?page=bbai'),
        ]);
        wp_localize_script('bbai-admin', 'bbai_env', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'upload_url'=> admin_url('upload.php'),
            'rest_root' => esc_url_raw(rest_url()),
            'nonce'     => wp_create_nonce('bbai_ajax_nonce'),
        ]);
    }

    /**
     * Enqueue dashboard page assets (CSS and JS)
     *
     * @param string $base_url  Plugin base URL
     * @param string $base_path Plugin base path
     */
    private function enqueue_dashboard_assets(string $base_url, string $base_path): void {
        $use_debug_assets = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $js_base  = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';
        $css_base = $use_debug_assets ? 'assets/src/css/' : 'assets/dist/css/';

        $checkout_prices = $this->get_checkout_price_ids();
        $l10n_common = $this->get_common_l10n();

        $asset_version = function(string $relative, string $fallback) use ($base_path): string {
            return $this->get_asset_version($relative, $fallback, $base_path);
        };

        $dashboard_js = $this->get_asset_path($js_base, 'bbai-dashboard', $use_debug_assets, 'js', $base_path);
        $usage_bridge_js = $this->get_asset_path($js_base, 'usage-components-bridge', $use_debug_assets, 'js', $base_path);
        $upgrade_js = $this->get_asset_path($js_base, 'upgrade-modal', $use_debug_assets, 'js', $base_path);
        $auth_js = $this->get_asset_path($js_base, 'auth-modal', $use_debug_assets, 'js', $base_path);
        $dashboard_scripts_js = $this->get_asset_path($js_base, 'bbai-dashboard-scripts', $use_debug_assets, 'js', $base_path);
        $admin_panel_js = $this->get_asset_path($js_base, 'bbai-admin-panel', $use_debug_assets, 'js', $base_path);
        $logger_js = $this->get_asset_path($js_base, 'bbai-logger', $use_debug_assets, 'js', $base_path);
        $tooltips_js = $this->get_asset_path($js_base, 'bbai-tooltips', $use_debug_assets, 'js', $base_path);
        $modal_js = $this->get_asset_path($js_base, 'bbai-modal', $use_debug_assets, 'js', $base_path);
        $analytics_js = $this->get_asset_path($js_base, 'bbai-analytics', $use_debug_assets, 'js', $base_path);
        $debug_js = $this->get_asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js', $base_path);
        $contact_modal_js = $this->get_asset_path($js_base, 'bbai-contact-modal', $use_debug_assets, 'js', $base_path);
        $modal_css = $this->get_asset_path($css_base, 'bbai-modal', $use_debug_assets, 'css', $base_path);
        $tooltips_css = $this->get_asset_path($css_base, 'bbai-tooltips', $use_debug_assets, 'css', $base_path);
        $admin_css = $this->get_asset_path($css_base, 'bbai-admin', $use_debug_assets, 'css', $base_path);
        $contact_modal_css = $this->get_asset_path($css_base, 'bbai-contact-modal', $use_debug_assets, 'css', $base_path);

        // Unified CSS bundle (with fallback if minified missing)
        $unified_css = $use_debug_assets ? 'assets/css/unified.css' : 'assets/css/unified.min.css';
        if ( ! file_exists( $base_path . $unified_css ) ) {
            $unified_css = $use_debug_assets ? 'assets/css/unified.min.css' : 'assets/css/unified.css';
        }
        wp_enqueue_style(
            'bbai-unified',
            $base_url . $unified_css,
            [],
            $asset_version( $unified_css, '6.0.5' )
        );

        // Modern bundle CSS (with fallback)
        $modern_css = 'assets/css/modern.bundle.min.css';
        if ( ! file_exists( $base_path . $modern_css ) ) {
            $modern_css = 'assets/css/modern.bundle.css';
        }
        wp_enqueue_style(
            'bbai-modern',
            $base_url . $modern_css,
            [ 'bbai-unified' ],
            $asset_version( $modern_css, '5.0.0' )
        );

        wp_enqueue_style(
            'bbai-admin-wizard',
            $base_url . $admin_css,
            ['bbai-unified'],
            $asset_version($admin_css, '1.0.0')
        );

        $status_card_css = 'assets/css/features/dashboard/status-card-refresh.css';
        wp_enqueue_style(
            'bbai-dashboard-status-card-refresh',
            $base_url . $status_card_css,
            [ 'bbai-modern', 'bbai-admin-wizard' ],
            $asset_version( $status_card_css, '1.0.0' )
        );

        $upgrade_modal_refresh_css = 'assets/css/features/pricing/upgrade-modal-refresh.css';
        wp_enqueue_style(
            'bbai-upgrade-modal-refresh',
            $base_url . $upgrade_modal_refresh_css,
            [ 'bbai-dashboard-status-card-refresh' ],
            $asset_version( $upgrade_modal_refresh_css, '1.0.0' )
        );

        if ($this->is_onboarding_page()) {
            $onboarding_css = 'assets/css/onboarding.css';
            $onboarding_js  = 'assets/js/onboarding.js';

            wp_enqueue_style(
                'bbai-onboarding',
                $base_url . $onboarding_css,
                ['bbai-unified', 'bbai-modern'],
                $asset_version($onboarding_css, '1.0.0')
            );

            wp_enqueue_script(
                'bbai-onboarding',
                $base_url . $onboarding_js,
                ['jquery', 'wp-i18n'],
                $asset_version($onboarding_js, '1.0.0'),
                true
            );

            $is_onboarding_completed = class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')
                ? \BeepBeepAI\AltTextGenerator\Onboarding::is_completed()
                : false;

            wp_localize_script('bbai-onboarding', 'bbai_env', [
                'ajax_url'  => admin_url('admin-ajax.php'),
                'admin_url' => admin_url(),
                'upload_url'=> admin_url('upload.php'),
                'rest_root' => esc_url_raw(rest_url()),
                'nonce'     => wp_create_nonce('bbai_ajax_nonce'),
            ]);

            wp_localize_script('bbai-onboarding', 'bbaiOnboarding', [
                'ajaxUrl'               => admin_url('admin-ajax.php'),
                'nonce'                 => wp_create_nonce('bbai_onboarding'),
                'queueStatsNonce'       => wp_create_nonce('beepbeepai_nonce'),
                'dashboardUrl'          => admin_url('admin.php?page=bbai'),
                'libraryUrl'            => admin_url('admin.php?page=' . self::MENU_SLUG_LIBRARY),
                'step3Url'              => admin_url('admin.php?page=bbai-onboarding&step=3'),
                'isAuthenticated'       => bbai_is_authenticated(),
                'isOnboardingCompleted' => $is_onboarding_completed,
                'strings'               => [
                    'scanStart'    => __('Starting your scan...', 'beepbeep-ai-alt-text-generator'),
                    'scanQueued'   => __('Scan queued.', 'beepbeep-ai-alt-text-generator'),
                    'scanFailed'   => __('Unable to start the scan. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'skipFailed'   => __('Unable to skip onboarding. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'scanLabel'    => __('Scanning...', 'beepbeep-ai-alt-text-generator'),
                    'skipLabel'    => __('Skipping...', 'beepbeep-ai-alt-text-generator'),
                    'statsLoading' => __('Loading...', 'beepbeep-ai-alt-text-generator'),
                    'statsFailed'  => __('Unable to load stats.', 'beepbeep-ai-alt-text-generator'),
                ],
            ]);
        }

        wp_add_inline_style('bbai-unified', $this->get_dashboard_hero_inline_css());

        wp_enqueue_style(
            'bbai-modal',
            $base_url . $modal_css,
            ['bbai-unified'],
            $asset_version($modal_css, '4.3.0')
        );

        $stats_data = $this->get_dashboard_stats_payload();
        $usage_data = Usage_Tracker::get_stats_display();

        wp_enqueue_script(
            'bbai-dashboard',
            $base_url . $dashboard_js,
            ['jquery', 'wp-api-fetch', 'wp-i18n'],
            $asset_version($dashboard_js, '3.0.0'),
            true
        );

        wp_enqueue_script(
            'bbai-analytics',
            $base_url . $analytics_js,
            ['jquery', 'bbai-dashboard'],
            $asset_version($analytics_js, '1.0.0'),
            true
        );

        wp_enqueue_script(
            'bbai-upgrade',
            $base_url . $upgrade_js,
            ['jquery'],
            $asset_version($upgrade_js, '3.1.0'),
            true
        );

        // Auth modal JS
        wp_enqueue_script(
            'bbai-auth',
            $base_url . $auth_js,
            ['jquery', 'wp-i18n'],
            $asset_version($auth_js, '4.0.0'),
            true
        );

        if (!$this->is_dashboard_tab()) {
            wp_enqueue_script(
                'bbai-admin-panel',
                $base_url . $admin_panel_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($admin_panel_js, '1.0.0'),
                true
            );
        }

        wp_enqueue_script(
            'bbai-dashboard-scripts',
            $base_url . $dashboard_scripts_js,
            ['jquery', 'bbai-dashboard'],
            $asset_version($dashboard_scripts_js, '1.0.0'),
            true
        );

        wp_enqueue_script(
            'bbai-logger',
            $base_url . $logger_js,
            [],
            $asset_version($logger_js, '4.3.0'),
            true
        );

        wp_enqueue_style(
            'bbai-tooltips',
            $base_url . $tooltips_css,
            ['bbai-unified'],
            $asset_version($tooltips_css, '4.3.0')
        );

        wp_enqueue_script(
            'bbai-tooltips',
            $base_url . $tooltips_js,
            ['jquery', 'wp-i18n'],
            $asset_version($tooltips_js, '4.3.0'),
            true
        );

        wp_enqueue_script(
            'bbai-modal',
            $base_url . $modal_js,
            ['jquery', 'bbai-logger', 'wp-i18n'],
            $asset_version($modal_js, '4.3.0'),
            true
        );

        $pricing_bridge_path = 'admin/components/pricing-modal-bridge.js';
        $pricing_bridge_full_path = $base_path . $pricing_bridge_path;
        if (file_exists($pricing_bridge_full_path)) {
            wp_enqueue_script(
                'bbai-pricing-modal-bridge',
                $base_url . $pricing_bridge_path,
                ['jquery', 'bbai-dashboard'],
                filemtime($pricing_bridge_full_path),
                true
            );
        }

        if (file_exists($base_path . $usage_bridge_js)) {
            wp_enqueue_script(
                'bbai-usage-bridge',
                $base_url . $usage_bridge_js,
                ['bbai-dashboard', 'wp-element'],
                $asset_version($usage_bridge_js, '1.0.0'),
                true
            );
        }

        wp_enqueue_script(
            'bbai-debug',
            $base_url . $debug_js,
            ['jquery'],
            $asset_version($debug_js, '1.0.0'),
            true
        );

        wp_enqueue_script(
            'bbai-contact-modal',
            $base_url . $contact_modal_js,
            ['jquery', 'wp-i18n'],
            $asset_version($contact_modal_js, '1.0.0'),
            true
        );

        wp_enqueue_style(
            'bbai-contact-modal',
            $base_url . $contact_modal_css,
            ['bbai-unified'],
            $asset_version($contact_modal_css, '1.0.0')
        );

        wp_localize_script('bbai-contact-modal', 'bbaiContactData', [
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => BEEPBEEP_AI_VERSION,
        ]);

        $this->localize_dashboard_scripts($stats_data, $usage_data, $checkout_prices, $l10n_common);
    }

    /**
     * Shared inline CSS injected for the dashboard hero treatment.
     *
     * @return string
     */
    private function get_dashboard_hero_inline_css(): string {
        return <<<'CSS'
.bbai-hero-section {
    background: linear-gradient(to bottom, #f7fee7 0%, #ffffff 100%);
    border-radius: 24px;
    margin-bottom: 32px;
    padding: 48px 40px;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.bbai-hero-content {
    margin-bottom: 32px;
}
.bbai-hero-title {
    margin: 0 0 16px 0;
    font-size: 2.5rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.bbai-hero-subtitle {
    margin: 0;
    font-size: 1.125rem;
    color: #475569;
    line-height: 1.6;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}
.bbai-hero-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
}
.bbai-hero-btn-primary {
    background: linear-gradient(135deg, #14b8a6 0%, #84cc16 100%);
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.2s ease;
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
}
.bbai-hero-btn-primary:hover {
    opacity: 0.9;
}
.bbai-hero-link-secondary {
    background: transparent;
    border: none;
    color: #6b7280;
    text-decoration: underline;
    font-size: 14px;
    cursor: pointer;
    transition: color 0.2s ease;
    padding: 0;
}
.bbai-hero-link-secondary:hover {
    color: #14b8a6;
}
.bbai-hero-micro-copy {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
}
CSS;
    }

    /**
     * Localize dashboard scripts with data
     *
     * @param array      $stats_data       Media stats
     * @param array      $usage_data       Usage stats
     * @param array $checkout_prices Checkout prices
     * @param array $l10n_common     Common L10n strings
     */
    private function localize_dashboard_scripts(array $stats_data, array $usage_data, array $checkout_prices, array $l10n_common): void {
        wp_localize_script('bbai-debug', 'BBAI_DEBUG', [
            'restLogs' => esc_url_raw(add_query_arg('action', 'bbai_debug_logs', admin_url('admin-ajax.php'))),
            'restClear' => esc_url_raw(add_query_arg('action', 'bbai_debug_logs_clear', admin_url('admin-ajax.php'))),
            'nonce' => wp_create_nonce('wp_rest'),
            'initial' => $this->get_debug_bootstrap(),
            'strings' => [
                'noLogs' => __('No logs recorded yet.', 'beepbeep-ai-alt-text-generator'),
                'contextTitle' => __('View Details', 'beepbeep-ai-alt-text-generator'),
                'clearConfirm' => __('This will permanently delete all debug logs. Continue?', 'beepbeep-ai-alt-text-generator'),
                'errorGeneric' => __('Unable to load debug logs. Please try again.', 'beepbeep-ai-alt-text-generator'),
                'emptyContext' => __('No additional context was provided for this entry.', 'beepbeep-ai-alt-text-generator'),
                'emptyPayload' => __('No request payload was captured for this entry.', 'beepbeep-ai-alt-text-generator'),
                'emptyResponse' => __('No response payload was captured for this entry.', 'beepbeep-ai-alt-text-generator'),
                'emptyStack' => __('No stack trace was captured for this entry.', 'beepbeep-ai-alt-text-generator'),
                'modalTitle' => __('Log Context Details', 'beepbeep-ai-alt-text-generator'),
                'cleared' => __('Logs cleared successfully.', 'beepbeep-ai-alt-text-generator'),
                'copied' => __('Debug info copied to clipboard.', 'beepbeep-ai-alt-text-generator'),
                'copyFailed' => __('Unable to copy debug info. Please copy it manually.', 'beepbeep-ai-alt-text-generator'),
                'connected' => __('Connected', 'beepbeep-ai-alt-text-generator'),
                'failed' => __('Failed', 'beepbeep-ai-alt-text-generator'),
                'noWarnings' => __('No warnings or errors recorded yet.', 'beepbeep-ai-alt-text-generator'),
            ],
        ]);

        $bbai_dash_data = [
            'nonce'       => wp_create_nonce('wp_rest'),
            'rest'        => esc_url_raw(rest_url('bbai/v1/generate/')),
            'restStats'   => esc_url_raw(rest_url('bbai/v1/stats')),
            'restUsage'   => esc_url_raw(rest_url('bbai/v1/usage')),
            'restMissing' => esc_url_raw(add_query_arg(['scope' => 'missing'], rest_url('bbai/v1/list'))),
            'restAll'     => esc_url_raw(add_query_arg(['scope' => 'all'], rest_url('bbai/v1/list'))),
            'restQueue'   => esc_url_raw(rest_url('bbai/v1/queue')),
            'restRoot'    => esc_url_raw(rest_url()),
            'restPlans'   => esc_url_raw(rest_url('bbai/v1/plans')),
            'upgradeUrl'  => esc_url(Usage_Tracker::get_upgrade_url()),
            'billingPortalUrl' => esc_url(Usage_Tracker::get_billing_portal_url()),
            'checkoutPrices' => $checkout_prices,
            'stats'       => $stats_data,
            'initialUsage' => $usage_data,
            'wizard'      => $this->get_setup_wizard_bootstrap(),
        ];

        wp_localize_script('bbai-dashboard', 'BBAI_DASH', $bbai_dash_data);

        $api_url = 'https://alttext-ai-backend.onrender.com';
        $sanitized_user_data = $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data());

        wp_localize_script('bbai-dashboard', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
            'api_url' => $api_url,
            'is_authenticated' => $this->api_client->is_authenticated(),
            'user_data' => $sanitized_user_data,
            'admin_url' => admin_url('admin.php'),
            'admin_logout_confirm' => __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator'),
            'can_manage' => $this->user_can_manage(),
            'logout_redirect' => admin_url('admin.php?page=bbai'),
            'stripe_links' => self::DEFAULT_STRIPE_LINKS,
        ]);
        wp_localize_script('bbai-dashboard', 'bbai_env', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'upload_url'=> admin_url('upload.php'),
            'rest_root' => esc_url_raw(rest_url()),
            'nonce'     => wp_create_nonce('bbai_ajax_nonce'),
        ]);

        // Dashboard L10n
        wp_localize_script('bbai-dashboard', 'BBAI_DASH_L10N', [
            'l10n' => array_merge([
                'processing'         => __('Generating ALT text…', 'beepbeep-ai-alt-text-generator'),
                /* translators: 1: image ID */
                'processingMissing'  => __('Generating ALT for #%d…', 'beepbeep-ai-alt-text-generator'),
                'error'              => __('Something went wrong. Check console for details.', 'beepbeep-ai-alt-text-generator'),
                /* translators: 1: images generated, 2: error count */
                'summary'            => __('Generated %1$d images (%2$d errors).', 'beepbeep-ai-alt-text-generator'),
                'restUnavailable'    => __('REST endpoint unavailable', 'beepbeep-ai-alt-text-generator'),
                'prepareBatch'       => __('Preparing image list…', 'beepbeep-ai-alt-text-generator'),
                'coverageCopy'       => __('of images currently include ALT text.', 'beepbeep-ai-alt-text-generator'),
                'noRequests'         => __('None yet', 'beepbeep-ai-alt-text-generator'),
                'noAudit'            => __('No usage data recorded yet.', 'beepbeep-ai-alt-text-generator'),
                'nothingToProcess'   => __('No images to process.', 'beepbeep-ai-alt-text-generator'),
                'batchStart'         => __('Starting batch…', 'beepbeep-ai-alt-text-generator'),
                'batchComplete'      => __('Batch complete.', 'beepbeep-ai-alt-text-generator'),
                /* translators: 1: completion time */
                'batchCompleteAt'    => __('Batch complete at %s', 'beepbeep-ai-alt-text-generator'),
                /* translators: 1: image ID */
                'completedItem'      => __('Finished #%d', 'beepbeep-ai-alt-text-generator'),
                /* translators: 1: image ID */
                'failedItem'         => __('Failed #%d', 'beepbeep-ai-alt-text-generator'),
                'loadingButton'      => __('Processing…', 'beepbeep-ai-alt-text-generator'),
            ], $l10n_common),
        ]);

        // Upgrade modal
        wp_localize_script('bbai-upgrade', 'BBAI_UPGRADE', [
            'nonce' => wp_create_nonce('beepbeepai_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'usage' => $usage_data,
            'upgradeUrl' => esc_url(Usage_Tracker::get_upgrade_url()),
            'billingPortalUrl' => esc_url(Usage_Tracker::get_billing_portal_url()),
            'priceIds' => $checkout_prices,
            'restPlans' => esc_url_raw(rest_url('bbai/v1/plans')),
            'canManage' => $this->user_can_manage(),
        ]);
    }

    /**
     * Main enqueue method for admin pages
     *
     * @param string $hook Current admin hook
     */
    public function enqueue_admin($hook): void {
        $base_path = BEEPBEEP_AI_PLUGIN_DIR;
        $base_url  = BEEPBEEP_AI_PLUGIN_URL;

        $is_bbai_page = $this->is_bbai_admin_page($hook);

        // Restrict plugin assets to BeepBeep AI admin pages.
        if (!$is_bbai_page) {
            return;
        }

        wp_dequeue_script('wp-auth-check');
        wp_deregister_script('wp-auth-check');
        remove_action('admin_print_footer_scripts', 'wp_auth_check_html', 5);

        $this->enqueue_media_library_assets($hook, $base_url, $base_path);
        $this->enqueue_dashboard_assets($base_url, $base_path);
    }
}
