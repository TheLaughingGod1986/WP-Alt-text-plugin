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

use BeepBeepAI\AltTextGenerator\BBAI_Telemetry;
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
     * Resolve a plugin admin JS/CSS file relative to the plugin root.
     *
     * Shipped packages use `assets/js/` and `assets/css/` (not `assets/src/` or `assets/dist/`).
     * The `$base` argument is ignored and kept only for call-site compatibility.
     *
     * Resolution order:
     * - Non-debug: `assets/{js|css}/{name}.min.{ext}` then `{name}.{ext}`
     * - Debug: `{name}.{ext}` under assets/{js|css}/ first, then optional `assets/src/{js|css}/`
     * - Then legacy `assets/dist/{js|css}/` (min then full) if present
     * - Optional `.bundle.min.{ext}` in assets/{js|css}/ for bundled builds
     *
     * @param string $base       Deprecated; unused.
     * @param string $name       File basename without extension (e.g. bbai-dashboard).
     * @param bool   $debug      SCRIPT_DEBUG — prefer non-minified when true.
     * @param string $type       `js` or `css`.
     * @param string $base_path  Absolute plugin directory path.
     * @return string Relative path from plugin root (use with BEEPBEEP_AI_PLUGIN_URL).
     */
    private function get_asset_path(string $base, string $name, bool $debug, string $type, string $base_path): string {
        unset($base);

        $type = ('css' === $type) ? 'css' : 'js';
        $dir  = 'assets/' . $type . '/';

        $candidates = [];

        if ($debug) {
            $candidates[] = $dir . $name . '.' . $type;
            $candidates[] = 'assets/src/' . $type . '/' . $name . '.' . $type;
        } else {
            $candidates[] = $dir . $name . '.min.' . $type;
            $candidates[] = $dir . $name . '.' . $type;
        }

        $candidates[] = 'assets/dist/' . $type . '/' . $name . '.min.' . $type;
        $candidates[] = 'assets/dist/' . $type . '/' . $name . '.' . $type;
        $candidates[] = $dir . $name . '.bundle.min.' . $type;

        foreach ($candidates as $rel) {
            if (is_readable($base_path . $rel)) {
                return $rel;
            }
        }

        return $dir . $name . '.' . $type;
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
     * Check if current tab is analytics.
     *
     * @return bool
     */
    private function is_analytics_tab(): bool {
        return $this->get_resolved_tab() === 'analytics';
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
            'bbai-ui-kit' => 'ui-kit',
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

        $toast_file = $this->get_asset_path($js_base, 'toast', $use_debug_assets, 'js', $base_path);
        $toast_version = $this->get_asset_version($toast_file, '1.0.0', $base_path);
        $admin_file = $this->get_asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js', $base_path);
        $admin_version = $this->get_asset_version($admin_file, '3.0.3', $base_path);

        $checkout_prices = $this->get_checkout_price_ids();
        $l10n_common = $this->get_common_l10n();
        $usage_data = Usage_Tracker::get_stats_display();

        wp_enqueue_script('bbai-toast', $base_url . $toast_file, [], $toast_version, true);

        $bbai_banner_message_js = 'assets/js/bbai-banner-message.js';
        $bbai_banner_message_ver = file_exists($base_path . $bbai_banner_message_js)
            ? (string) filemtime($base_path . $bbai_banner_message_js)
            : '1.0.0';
        wp_enqueue_script(
            'bbai-banner-message',
            $base_url . $bbai_banner_message_js,
            ['wp-i18n'],
            $bbai_banner_message_ver,
            true
        );

        wp_enqueue_script('bbai-admin', $base_url . $admin_file, ['jquery', 'wp-i18n', 'bbai-toast', 'bbai-banner-message', 'bbai-telemetry'], $admin_version, true);
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
            'initialUsage' => $usage_data,
            'usage' => $usage_data,
            'quota' => $usage_data['quota'] ?? [],
            'wizard' => $this->get_setup_wizard_bootstrap(),
            'monetisation' => $this->get_monetisation_bootstrap_for_localize(),
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
            $asset_version( $unified_css, '6.0.14' )
        );

        $admin_foundation_tokens_css = 'assets/css/system/bbai-admin-foundation-tokens.css';
        if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
            wp_enqueue_style(
                'bbai-admin-foundation-tokens',
                $base_url . $admin_foundation_tokens_css,
                [ 'bbai-unified' ],
                $asset_version( $admin_foundation_tokens_css, '1.0.1' )
            );
        }

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
            $asset_version($admin_css, '1.0.4')
        );

        $status_card_css = 'assets/css/features/dashboard/status-card-refresh.css';
        wp_enqueue_style(
            'bbai-dashboard-status-card-refresh',
            $base_url . $status_card_css,
            [ 'bbai-modern', 'bbai-admin-wizard' ],
            $asset_version( $status_card_css, '1.1.9' )
        );

        $command_hero_host_css = 'assets/css/features/dashboard/command-hero-host.css';
        wp_enqueue_style(
            'bbai-command-hero-host',
            $base_url . $command_hero_host_css,
            [ 'bbai-dashboard-status-card-refresh' ],
            $asset_version( $command_hero_host_css, '1.4.7' )
        );

        $filter_group_css = 'assets/css/features/dashboard/filter-group.css';
        wp_enqueue_style(
            'bbai-filter-group',
            $base_url . $filter_group_css,
            [ 'bbai-command-hero-host' ],
            $asset_version( $filter_group_css, '1.0.1' )
        );

        $queue_workflow_css = 'assets/css/features/dashboard/queue-workflow.css';
        wp_enqueue_style(
            'bbai-queue-workflow',
            $base_url . $queue_workflow_css,
            [ 'bbai-command-hero-host' ],
            $asset_version( $queue_workflow_css, '1.0.0' )
        );

        $activation_ftue_css = 'assets/css/features/dashboard/dashboard-activation-ftue.css';
        if ( file_exists( $base_path . $activation_ftue_css ) ) {
            wp_enqueue_style(
                'bbai-dashboard-activation-ftue',
                $base_url . $activation_ftue_css,
                [ 'bbai-queue-workflow' ],
                $asset_version( $activation_ftue_css, '1.1.0' )
            );
        }

        $retention_strip_css = 'assets/css/features/dashboard/dashboard-retention-strip.css';
        if ( file_exists( $base_path . $retention_strip_css ) ) {
            $retention_dep = file_exists( $base_path . $activation_ftue_css )
                ? 'bbai-dashboard-activation-ftue'
                : 'bbai-queue-workflow';
            wp_enqueue_style(
                'bbai-dashboard-retention-strip',
                $base_url . $retention_strip_css,
                [ $retention_dep ],
                $asset_version( $retention_strip_css, '1.0.1' )
            );
        }

        $upgrade_modal_refresh_css = 'assets/css/features/pricing/upgrade-modal-refresh.css';
        wp_enqueue_style(
            'bbai-upgrade-modal-refresh',
            $base_url . $upgrade_modal_refresh_css,
            [ 'bbai-queue-workflow' ],
            $asset_version( $upgrade_modal_refresh_css, '1.0.2' )
        );

        if ($this->is_onboarding_page()) {
            $onboarding_css = 'assets/css/onboarding.css';
            $onboarding_js  = 'assets/js/onboarding.js';

            wp_enqueue_style(
                'bbai-onboarding',
                $base_url . $onboarding_css,
                ['bbai-unified', 'bbai-modern'],
                $asset_version($onboarding_css, '1.0.1')
            );

            wp_enqueue_script(
                'bbai-onboarding',
                $base_url . $onboarding_js,
                ['jquery', 'wp-i18n'],
                $asset_version($onboarding_js, '1.0.1'),
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

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only onboarding step for localized strings.
            $bbai_onboarding_step_param = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 1;
            if ($bbai_onboarding_step_param < 1 || $bbai_onboarding_step_param > 3) {
                $bbai_onboarding_step_param = 1;
            }

            wp_localize_script('bbai-onboarding', 'bbaiOnboarding', [
                'ajaxUrl'               => admin_url('admin-ajax.php'),
                'nonce'                 => wp_create_nonce('bbai_onboarding'),
                'queueStatsNonce'       => wp_create_nonce('beepbeepai_nonce'),
                'dashboardUrl'          => admin_url('admin.php?page=bbai'),
                'libraryUrl'            => admin_url('admin.php?page=' . self::MENU_SLUG_LIBRARY),
                'step3Url'              => admin_url('admin.php?page=bbai-onboarding&step=3'),
                'currentStep'           => $bbai_onboarding_step_param,
                'isAuthenticated'       => bbai_is_authenticated(),
                'isOnboardingCompleted' => $is_onboarding_completed,
                'strings'               => [
                    'scanStart'      => __('Starting your scan...', 'beepbeep-ai-alt-text-generator'),
                    'generateStart'  => __('Starting generation...', 'beepbeep-ai-alt-text-generator'),
                    'scanQueued'     => __('Scan queued.', 'beepbeep-ai-alt-text-generator'),
                    'scanFailed'     => __('Unable to start the scan. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'generateFailed' => __('Unable to start generation. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'skipFailed'     => __('Unable to skip onboarding. Please try again.', 'beepbeep-ai-alt-text-generator'),
                    'scanLabel'      => __('Scanning...', 'beepbeep-ai-alt-text-generator'),
                    'generateLabel'  => __('Generating...', 'beepbeep-ai-alt-text-generator'),
                    'skipLabel'      => __('Skipping...', 'beepbeep-ai-alt-text-generator'),
                    'statsLoading'   => __('Loading...', 'beepbeep-ai-alt-text-generator'),
                    'statsFailed'    => __('Unable to load stats.', 'beepbeep-ai-alt-text-generator'),
                ],
            ]);
        }

        wp_add_inline_style('bbai-unified', $this->get_dashboard_hero_inline_css());

        $saas_consistency_css = 'assets/css/features/dashboard/saas-consistency.css';
        wp_enqueue_style(
            'bbai-saas-consistency',
            $base_url . $saas_consistency_css,
            [ 'bbai-upgrade-modal-refresh' ],
            $asset_version($saas_consistency_css, '1.0.22')
        );

        $product_banner_css = 'assets/css/features/dashboard/product-banner.css';
        wp_enqueue_style(
            'bbai-product-banner',
            $base_url . $product_banner_css,
            [ 'bbai-saas-consistency' ],
            $asset_version($product_banner_css, '1.0.17')
        );

        $page_hero_css = 'assets/css/features/dashboard/page-hero.css';
        wp_enqueue_style(
            'bbai-page-hero',
            $base_url . $page_hero_css,
            [ 'bbai-product-banner' ],
            $asset_version($page_hero_css, '1.0.1')
        );

        $admin_ui_components_css = 'assets/css/features/dashboard/admin-ui-components.css';
        wp_enqueue_style(
            'bbai-admin-ui-components',
            $base_url . $admin_ui_components_css,
            [ 'bbai-page-hero' ],
            $asset_version($admin_ui_components_css, '1.0.3')
        );

        $micro_motion_css = 'assets/css/features/dashboard/micro-motion.css';
        wp_enqueue_style(
            'bbai-admin-micro-motion',
            $base_url . $micro_motion_css,
            [ 'bbai-admin-ui-components' ],
            $asset_version($micro_motion_css, '1.0.1')
        );

        if ($this->is_analytics_tab()) {
            $analytics_page_css = 'assets/css/features/dashboard/analytics-page.css';
            wp_enqueue_style(
                'bbai-analytics-page',
                $base_url . $analytics_page_css,
                [ 'bbai-admin-micro-motion' ],
                $asset_version($analytics_page_css, '1.4.1')
            );
        }

        $card_rhythm_css = 'assets/css/system/bbai-card-rhythm.css';
        if ( file_exists( $base_path . $card_rhythm_css ) ) {
            wp_enqueue_style(
                'bbai-card-rhythm',
                $base_url . $card_rhythm_css,
                [],
                $asset_version( $card_rhythm_css, '1.0.1' )
            );
        }

        $section_header_css = 'assets/css/features/dashboard/bbai-section-header.css';
        $section_header_deps = [ 'bbai-admin-micro-motion' ];
        if ( file_exists( $base_path . $card_rhythm_css ) ) {
            array_unshift( $section_header_deps, 'bbai-card-rhythm' );
        }
        if ($this->is_analytics_tab()) {
            $section_header_deps[] = 'bbai-analytics-page';
        }
        wp_enqueue_style(
            'bbai-section-header',
            $base_url . $section_header_css,
            $section_header_deps,
            $asset_version($section_header_css, '1.0.4')
        );

        $admin_controls_css = 'assets/css/system/bbai-admin-controls.css';
        $admin_interactions_css = 'assets/css/system/bbai-admin-interactions.css';
        if ( file_exists( $base_path . $admin_controls_css ) ) {
            $admin_controls_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_controls_deps[] = 'bbai-admin-foundation-tokens';
            }
            wp_enqueue_style(
                'bbai-admin-controls',
                $base_url . $admin_controls_css,
                $admin_controls_deps,
                $asset_version( $admin_controls_css, '1.0.0' )
            );
        }

        if ( file_exists( $base_path . $admin_interactions_css ) ) {
            $admin_ix_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_ix_deps[] = 'bbai-admin-foundation-tokens';
            }
            if ( file_exists( $base_path . $admin_controls_css ) ) {
                $admin_ix_deps[] = 'bbai-admin-controls';
            }
            wp_enqueue_style(
                'bbai-admin-interactions',
                $base_url . $admin_interactions_css,
                $admin_ix_deps,
                $asset_version( $admin_interactions_css, '1.0.0' )
            );
        }

        $admin_product_states_css = 'assets/css/system/bbai-admin-product-states.css';
        if ( file_exists( $base_path . $admin_product_states_css ) ) {
            $admin_ps_deps = [ 'bbai-admin-interactions' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                array_unshift( $admin_ps_deps, 'bbai-admin-foundation-tokens' );
            }
            wp_enqueue_style(
                'bbai-admin-product-states',
                $base_url . $admin_product_states_css,
                array_values( array_unique( $admin_ps_deps ) ),
                $asset_version( $admin_product_states_css, '1.0.0' )
            );
        }

        $admin_surfaces_css = 'assets/css/system/bbai-admin-surfaces.css';
        if ( file_exists( $base_path . $admin_surfaces_css ) ) {
            $admin_surfaces_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_surfaces_deps[] = 'bbai-admin-foundation-tokens';
            }
            if ( file_exists( $base_path . $admin_controls_css ) ) {
                $admin_surfaces_deps[] = 'bbai-admin-controls';
            }
            if ( file_exists( $base_path . $admin_interactions_css ) ) {
                $admin_surfaces_deps[] = 'bbai-admin-interactions';
            }
            wp_enqueue_style(
                'bbai-admin-surfaces',
                $base_url . $admin_surfaces_css,
                $admin_surfaces_deps,
                $asset_version( $admin_surfaces_css, '1.0.1' )
            );
        }

        $admin_design_system_css = 'assets/css/system/bbai-admin-design-system.css';
        if ( file_exists( $base_path . $admin_design_system_css ) ) {
            $admin_ds_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_ds_deps[] = 'bbai-admin-foundation-tokens';
            }
            if ( file_exists( $base_path . $admin_controls_css ) ) {
                $admin_ds_deps[] = 'bbai-admin-controls';
            }
            if ( file_exists( $base_path . $admin_surfaces_css ) ) {
                $admin_ds_deps[] = 'bbai-admin-surfaces';
            }
            if ( file_exists( $base_path . $admin_interactions_css ) ) {
                $admin_ds_deps[] = 'bbai-admin-interactions';
            }
            wp_enqueue_style(
                'bbai-admin-design-system',
                $base_url . $admin_design_system_css,
                $admin_ds_deps,
                $asset_version( $admin_design_system_css, '1.0.0' )
            );
        }

        $admin_semantic_status_css = 'assets/css/system/bbai-admin-semantic-status.css';
        if ( file_exists( $base_path . $admin_semantic_status_css ) ) {
            $admin_semantic_status_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_semantic_status_deps[] = 'bbai-admin-foundation-tokens';
            }
            if ( file_exists( $base_path . $admin_design_system_css ) ) {
                $admin_semantic_status_deps[] = 'bbai-admin-design-system';
            }
            wp_enqueue_style(
                'bbai-admin-semantic-status',
                $base_url . $admin_semantic_status_css,
                $admin_semantic_status_deps,
                $asset_version( $admin_semantic_status_css, '1.0.0' )
            );
        }

        $admin_table_workspace_css = 'assets/css/system/bbai-admin-table-workspace.css';
        if ( file_exists( $base_path . $admin_table_workspace_css ) ) {
            $admin_table_ws_deps = [ 'bbai-section-header' ];
            if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
                $admin_table_ws_deps[] = 'bbai-admin-foundation-tokens';
            }
            if ( file_exists( $base_path . $admin_semantic_status_css ) ) {
                $admin_table_ws_deps[] = 'bbai-admin-semantic-status';
            } elseif ( file_exists( $base_path . $admin_design_system_css ) ) {
                $admin_table_ws_deps[] = 'bbai-admin-design-system';
            }
            if ( file_exists( $base_path . $admin_interactions_css ) ) {
                $admin_table_ws_deps[] = 'bbai-admin-interactions';
            }
            if ( file_exists( $base_path . $admin_product_states_css ) ) {
                $admin_table_ws_deps[] = 'bbai-admin-product-states';
            }
            wp_enqueue_style(
                'bbai-admin-table-workspace',
                $base_url . $admin_table_workspace_css,
                $admin_table_ws_deps,
                $asset_version( $admin_table_workspace_css, '1.0.0' )
            );

            $admin_page_adoption_css = 'assets/css/system/bbai-admin-page-adoption.css';
            if ( file_exists( $base_path . $admin_page_adoption_css ) ) {
                $admin_page_adoption_deps = [ 'bbai-admin-table-workspace' ];
                if ( file_exists( $base_path . $admin_surfaces_css ) ) {
                    array_unshift( $admin_page_adoption_deps, 'bbai-admin-surfaces' );
                }
                wp_enqueue_style(
                    'bbai-admin-page-adoption',
                    $base_url . $admin_page_adoption_css,
                    array_values( array_unique( $admin_page_adoption_deps ) ),
                    $asset_version( $admin_page_adoption_css, '1.0.1' )
                );
            }

            $admin_surface_taxonomy_css = 'assets/css/system/bbai-admin-surface-taxonomy.css';
            if ( file_exists( $base_path . $admin_surface_taxonomy_css ) ) {
                $admin_surface_taxonomy_deps = [ 'bbai-admin-table-workspace' ];
                if ( file_exists( $base_path . $admin_page_adoption_css ) ) {
                    $admin_surface_taxonomy_deps = [ 'bbai-admin-page-adoption' ];
                }
                wp_enqueue_style(
                    'bbai-admin-surface-taxonomy',
                    $base_url . $admin_surface_taxonomy_css,
                    $admin_surface_taxonomy_deps,
                    $asset_version( $admin_surface_taxonomy_css, '1.0.1' )
                );
            }

            $admin_library_workspace_polish_css = 'assets/css/system/bbai-admin-library-workspace-polish.css';
            if ( file_exists( $base_path . $admin_library_workspace_polish_css ) ) {
                wp_enqueue_style(
                    'bbai-admin-library-workspace-polish',
                    $base_url . $admin_library_workspace_polish_css,
                    [ 'bbai-admin-table-workspace' ],
                    $asset_version( $admin_library_workspace_polish_css, '1.0.0' )
                );
            }
        }

        $ui_kit_preview_css = 'assets/css/system/bbai-ui-kit-preview.css';
        if (
            'bbai-ui-kit' === $this->get_current_admin_page()
            && $this->can_show_ui_kit_page()
            && file_exists($base_path . $ui_kit_preview_css)
        ) {
            $ui_kit_dep = 'bbai-section-header';
            if (file_exists($base_path . 'assets/css/system/bbai-admin-library-workspace-polish.css')) {
                $ui_kit_dep = 'bbai-admin-library-workspace-polish';
            } elseif (file_exists($base_path . 'assets/css/system/bbai-admin-page-adoption.css')) {
                $ui_kit_dep = 'bbai-admin-page-adoption';
            } elseif (file_exists($base_path . 'assets/css/system/bbai-admin-table-workspace.css')) {
                $ui_kit_dep = 'bbai-admin-table-workspace';
            }
            wp_enqueue_style(
                'bbai-ui-kit-preview',
                $base_url . $ui_kit_preview_css,
                [$ui_kit_dep],
                $asset_version($ui_kit_preview_css, '1.0.0')
            );
        }

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
            ['jquery', 'wp-api-fetch', 'wp-i18n', 'bbai-toast', 'bbai-banner-message', 'bbai-telemetry'],
            $asset_version($dashboard_js, '3.0.2'),
            true
        );

        wp_enqueue_script(
            'bbai-analytics',
            $base_url . $analytics_js,
            ['jquery', 'bbai-dashboard'],
            $asset_version($analytics_js, '1.1.1'),
            true
        );

        wp_enqueue_script(
            'bbai-upgrade',
            $base_url . $upgrade_js,
            ['jquery', 'bbai-telemetry'],
            $asset_version($upgrade_js, '3.1.0'),
            true
        );

        // Auth modal JS (after bbai-dashboard so bbai_ajax / showAuthModal exist; avoids race on first paint).
        wp_enqueue_script(
            'bbai-auth',
            $base_url . $auth_js,
            ['jquery', 'wp-i18n', 'bbai-dashboard'],
            $asset_version($auth_js, '4.0.1'),
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

        $phase17_assistant_js  = 'assets/js/bbai-phase17-assistant.js';
        $phase17_assistant_css = 'assets/css/features/automation/phase17-assistant.css';
        if ( file_exists( $base_path . $phase17_assistant_js ) ) {
            if ( file_exists( $base_path . $phase17_assistant_css ) ) {
                wp_enqueue_style(
                    'bbai-phase17-assistant',
                    $base_url . $phase17_assistant_css,
                    [ 'bbai-dashboard-status-card-refresh' ],
                    $asset_version( $phase17_assistant_css, '1.0.0' )
                );
            }
            wp_enqueue_script(
                'bbai-phase17-assistant',
                $base_url . $phase17_assistant_js,
                [ 'bbai-admin' ],
                $asset_version( $phase17_assistant_js, '1.0.0' ),
                true
            );
            wp_localize_script(
                'bbai-phase17-assistant',
                'BBAI_PHASE17',
                [
                    'restUrl'              => esc_url_raw( rest_url( 'bbai/v1/assistant/chat' ) ),
                    'improveUrlTemplate'   => esc_url_raw( rest_url( 'bbai/v1/improve-alt/' ) ),
                    'nonce'                => wp_create_nonce( 'wp_rest' ),
                    'strings'              => [
                        'fab'         => __( 'Help', 'beepbeep-ai-alt-text-generator' ),
                        'title'       => __( 'BeepBeep guide', 'beepbeep-ai-alt-text-generator' ),
                        'close'       => __( 'Close', 'beepbeep-ai-alt-text-generator' ),
                        'ask'         => __( 'Your question', 'beepbeep-ai-alt-text-generator' ),
                        'placeholder' => __( 'e.g. How do credits work? What does “needs review” mean?', 'beepbeep-ai-alt-text-generator' ),
                        'send'        => __( 'Ask', 'beepbeep-ai-alt-text-generator' ),
                        'thinking'       => __( 'Thinking…', 'beepbeep-ai-alt-text-generator' ),
                        'error'          => __( 'Could not reach the assistant. Check your connection and try again.', 'beepbeep-ai-alt-text-generator' ),
                        'improveWorking' => __( 'Improving ALT text…', 'beepbeep-ai-alt-text-generator' ),
                        'improveDone'    => __( 'ALT text updated. Refreshing…', 'beepbeep-ai-alt-text-generator' ),
                        'improveFail'    => __( 'Could not improve ALT text. Try again or use Regenerate.', 'beepbeep-ai-alt-text-generator' ),
                        'improveTip'     => __( 'Tip', 'beepbeep-ai-alt-text-generator' ),
                    ],
                ]
            );
        }

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
    background: #10b981;
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 16px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.bbai-hero-btn-primary:hover {
    background: #059669;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}
.bbai-hero-btn-primary:active {
    background: #047857;
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
            'usage'      => $usage_data,
            'quota'      => $usage_data['quota'] ?? [],
            'pendingUpgradeTriggers' => [
                'newUpload' => $this->consume_media_upload_upgrade_trigger(),
            ],
            'wizard'      => $this->get_setup_wizard_bootstrap(),
            'monetisation' => $this->get_monetisation_bootstrap_for_localize(),
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
        static $bbai_open_auth_inline_added = false;
        if ( ! $bbai_open_auth_inline_added ) {
            $bbai_open_auth_inline_added = true;
            wp_add_inline_script(
                'bbai-dashboard',
                '(function(){function bbaiTryOpenAuthModal(){if(typeof window.showAuthModal==="function"){window.showAuthModal("login");return true;}if(window.authModal&&typeof window.authModal.show==="function"){window.authModal.show();if(typeof window.authModal.showLoginForm==="function"){window.authModal.showLoginForm();}return true;}return false;}function bbaiOpenAuthFromQuery(){try{var u=new URL(window.location.href);if(u.searchParams.get("bbai_open_auth")!=="1"){return;}u.searchParams.delete("bbai_open_auth");var q=u.searchParams.toString();var next=u.pathname+(q?"?"+q:"")+u.hash;window.history.replaceState({},document.title,next);if(bbaiTryOpenAuthModal()){return;}var n=0;var t=window.setInterval(function(){n+=1;if(bbaiTryOpenAuthModal()||n>40){window.clearInterval(t);}},75);}catch(e){}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bbaiOpenAuthFromQuery);}else{bbaiOpenAuthFromQuery();}})();',
                'after'
            );
        }

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
     * Phase 15 — tier + feature flags for client-side upgrade context (no PII).
     *
     * @return array<string, mixed>
     */
    private function get_monetisation_bootstrap_for_localize(): array {
        $path = BEEPBEEP_AI_PLUGIN_DIR . 'includes/admin/monetisation-phase15.php';
        if (is_readable($path) && !function_exists('bbai_monetisation_client_bootstrap')) {
            require_once $path;
        }
        return function_exists('bbai_monetisation_client_bootstrap') ? bbai_monetisation_client_bootstrap() : [];
    }

    /**
     * Determine whether the current plugin session is connected to a BeepBeep AI account.
     */
    private function is_bbai_account_authenticated(): bool {
        if (isset($this->api_client) && $this->api_client) {
            if ($this->api_client->is_authenticated() || $this->api_client->has_active_license()) {
                return true;
            }
        }

        return function_exists('bbai_is_authenticated') ? \bbai_is_authenticated() : false;
    }

    /**
     * PostHog page key used for admin analytics context.
     */
    private function get_posthog_page_key(): string {
        $page = $this->get_resolved_tab();
        $map  = [
            'dashboard'       => 'dashboard',
            'library'         => 'alt_library',
            'analytics'       => 'analytics',
            'usage'           => 'usage',
            'settings'        => 'settings',
            'help'            => 'settings',
            'ui-kit'          => 'settings',
            'agency-overview' => 'dashboard',
            'onboarding'      => 'onboarding',
        ];

        $resolved = $map[ $page ] ?? 'dashboard';
        if ( 'dashboard' === $resolved && ! $this->is_bbai_account_authenticated() ) {
            return 'guest_dashboard';
        }

        return $resolved;
    }

    /**
     * Stable site hash for client analytics.
     */
    private function get_posthog_site_hash(): string {
        if (!function_exists('\BeepBeepAI\AltTextGenerator\get_site_identifier')) {
            $site_id_helper = BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
            if (is_readable($site_id_helper)) {
                require_once $site_id_helper;
            }
        }

        $site_identifier = function_exists('\BeepBeepAI\AltTextGenerator\get_site_identifier')
            ? (string) \BeepBeepAI\AltTextGenerator\get_site_identifier()
            : (string) home_url('/');

        return hash('sha256', $site_identifier);
    }

    /**
     * Shared client bootstrap for the PostHog wrapper.
     *
     * @return array<string, mixed>
     */
    private function get_posthog_client_config(): array {
        $usage_data = Usage_Tracker::get_stats_display();
        $stats_data = $this->get_dashboard_stats_payload();
        $user_data  = isset($this->api_client) ? $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data()) : [];
        $license_data = (isset($this->api_client) && method_exists($this->api_client, 'get_license_data'))
            ? $this->api_client->get_license_data()
            : [];
        $is_logged_in = $this->is_bbai_account_authenticated();
        $page_key     = $this->get_posthog_page_key();

        $plan_type = sanitize_key(
            (string) (
                $usage_data['plan']
                ?? $usage_data['plan_type']
                ?? $user_data['planSlug']
                ?? $user_data['plan']
                ?? $user_data['plan_type']
                ?? ( is_array($license_data) ? ( $license_data['organization']['plan'] ?? '' ) : '' )
            )
        );
        if ('' === $plan_type) {
            $plan_type = 'free';
        }

        $quota_remaining = null;
        if (isset($usage_data['quota']['remaining'])) {
            $quota_remaining = max(0, (int) $usage_data['quota']['remaining']);
        } elseif (isset($usage_data['remaining'])) {
            $quota_remaining = max(0, (int) $usage_data['remaining']);
        }

        $quota_limit = null;
        if (isset($usage_data['quota']['limit'])) {
            $quota_limit = max(0, (int) $usage_data['quota']['limit']);
        } elseif (isset($usage_data['limit'])) {
            $quota_limit = max(0, (int) $usage_data['limit']);
        }

        $trial_status = method_exists($this, 'get_trial_status')
            ? $this->get_trial_status()
            : [
                'remaining'   => 0,
                'should_gate' => false,
            ];
        $remaining_free_images = null;
        if (! $is_logged_in && ! empty($trial_status['should_gate']) && isset($trial_status['remaining'])) {
            $remaining_free_images = max(0, (int) $trial_status['remaining']);
        }

        $trial_exhausted = ! $is_logged_in
            && ! empty($trial_status['should_gate'])
            && isset($trial_status['remaining'])
            && (int) $trial_status['remaining'] <= 0;

        $identify_id = '';
        if ($is_logged_in) {
            $candidate_ids = [
                isset($user_data['id']) ? 'user:' . $user_data['id'] : '',
                isset($user_data['_id']) ? 'user:' . $user_data['_id'] : '',
                isset($user_data['organization']['id']) ? 'org:' . $user_data['organization']['id'] : '',
                isset($user_data['organization']['_id']) ? 'org:' . $user_data['organization']['_id'] : '',
                (is_array($license_data) && isset($license_data['organization']['id'])) ? 'org:' . $license_data['organization']['id'] : '',
                (is_array($license_data) && isset($license_data['organization']['_id'])) ? 'org:' . $license_data['organization']['_id'] : '',
                (is_array($license_data) && isset($license_data['site']['id'])) ? 'site:' . $license_data['site']['id'] : '',
                (is_array($license_data) && isset($license_data['site']['_id'])) ? 'site:' . $license_data['site']['_id'] : '',
            ];

            foreach ($candidate_ids as $candidate_id) {
                $candidate_id = sanitize_text_field((string) $candidate_id);
                if ('' !== $candidate_id) {
                    $identify_id = $candidate_id;
                    break;
                }
            }
        }

        $page_view_events = [
            'dashboard'       => 'dashboard_viewed',
            'guest_dashboard' => 'guest_dashboard_viewed',
            'alt_library'     => 'alt_library_viewed',
            'analytics'       => 'analytics_viewed',
            'usage'           => 'usage_viewed',
            'settings'        => 'settings_viewed',
            'onboarding'      => 'onboarding_viewed',
        ];

        return [
            'enabled'       => true,
            'apiKey'        => 'phc_6L7JzpjYRC8Gk4Br3YevTmjZnJsJPvoy9GK7RFdo72s',
            'apiHost'       => 'https://us.i.posthog.com',
            'defaults'      => '2026-01-30',
            'instanceName'  => 'bbaiPosthog',
            'pageViewEvents' => $page_view_events,
            'context'       => [
                'page'               => $page_key,
                'site_hash'          => $this->get_posthog_site_hash(),
                'site_url'           => esc_url_raw(home_url('/')),
                'is_logged_in'       => $is_logged_in,
                'plan_type'          => $plan_type,
                'quota_remaining'    => $quota_remaining,
                'quota_limit'        => $quota_limit,
                'trial_exhausted'    => $trial_exhausted,
                'remaining_free_images' => $remaining_free_images,
                'missing_alt_count'  => max(0, (int) ($stats_data['images_missing_alt'] ?? 0)),
                'needs_review_count' => max(0, (int) ($stats_data['needs_review_count'] ?? 0)),
                'optimized_count'    => max(0, (int) ($stats_data['optimized_count'] ?? 0)),
                'plugin_version'     => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
            ],
            'identify'      => [
                'id'                => $identify_id,
                'person_properties' => [
                    'plan_type'      => $plan_type,
                    'site_hash'      => $this->get_posthog_site_hash(),
                    'plugin_version' => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
                ],
            ],
        ];
    }

    /**
     * Register + enqueue the PostHog bridge for BeepBeep AI admin pages only.
     */
    private function enqueue_posthog_layer(string $base_url, string $base_path): void {
        $rel  = 'assets/js/bbai-posthog.js';
        $path = $base_path . $rel;

        if (!is_readable($path)) {
            return;
        }

        wp_register_script(
            'bbai-posthog',
            $base_url . $rel,
            [],
            (string) filemtime($path),
            true
        );
        wp_enqueue_script('bbai-posthog');
        wp_localize_script('bbai-posthog', 'BBAI_POSTHOG', $this->get_posthog_client_config());
    }

    /**
     * Register + enqueue client telemetry (Phase 12). Must run before bbai-admin / bbai-dashboard.
     */
    private function enqueue_telemetry_layer(string $base_url, string $base_path): void {
        $rel  = 'assets/js/bbai-telemetry.js';
        $path = $base_path . $rel;
        if (! is_readable($path)) {
            return;
        }

        wp_register_script(
            'bbai-telemetry',
            $base_url . $rel,
            ['jquery', 'bbai-posthog'],
            (string) filemtime($path),
            true
        );
        wp_enqueue_script('bbai-telemetry');
        wp_localize_script('bbai-telemetry', 'BBAI_TELEMETRY', $this->get_telemetry_client_config());
    }

    /**
     * Client-safe bootstrap for bbai-telemetry.js (no PII).
     *
     * @return array<string,mixed>
     */
    private function get_telemetry_client_config(): array {
        $usage = Usage_Tracker::get_stats_display();
        $plan  = isset($usage['plan']) ? sanitize_key((string) $usage['plan']) : 'free';
        $plan_type = in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true) ? 'pro' : ('free' === $plan ? 'free' : 'unknown');

        $uid = get_current_user_id();
        $key = '_bbai_telemetry_session_images_' . gmdate('Ymd');
        $session_images = $uid > 0 ? (int) get_user_meta($uid, $key, true) : 0;

        BBAI_Telemetry::touch_last_active();
        $days_since = BBAI_Telemetry::inactive_days_at_session_start();

        return [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('beepbeepai_nonce'),
            'action'      => 'beepbeepai_telemetry',
            'context'     => [
                'user_id'                  => $uid,
                'plan_type'                => $plan_type,
                'plugin_version'           => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
                'page'                     => $this->get_telemetry_page_key(),
                'page_variant'             => $this->get_posthog_page_key(),
                'days_since_last_active'   => $days_since,
                'images_processed_session' => $session_images,
            ],
        ];
    }

    /**
     * Resolved product area for telemetry (matches server-side slug map).
     */
    private function get_telemetry_page_key(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $map  = [
            'bbai'               => 'dashboard',
            'bbai-library'       => 'alt_library',
            'bbai-analytics'     => 'analytics',
            'bbai-credit-usage'  => 'usage',
            'bbai-settings'      => 'settings',
            'bbai-debug'         => 'settings',
            'bbai-guide'         => 'help',
            'bbai-onboarding'    => 'onboarding',
            'bbai-ui-kit'        => 'ui_kit',
            'bbai-agency-overview' => 'agency_overview',
        ];
        if (isset($map[$page])) {
            return $map[$page];
        }
        return '' !== $page ? 'other' : 'unknown';
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

        $this->enqueue_posthog_layer($base_url, $base_path);
        $this->enqueue_telemetry_layer($base_url, $base_path);
        $this->maybe_telemetry_admin_query_events();
        $this->enqueue_media_library_assets($hook, $base_url, $base_path);
        $this->enqueue_dashboard_assets($base_url, $base_path);
    }

    /**
     * One-shot server events from admin URL query params (checkout return, portal refresh).
     */
    private function maybe_telemetry_admin_query_events(): void {
        if ( ! function_exists( 'bbai_telemetry_emit' ) ) {
            return;
        }
        $uid = get_current_user_id();
        if ( $uid <= 0 ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query flags for analytics.
        if ( ! empty( $_GET['subscription_updated'] ) ) {
            $key = 'bbai_tel_subupd_' . $uid;
            if ( ! get_transient( $key ) ) {
                set_transient( $key, 1, 180 );
                bbai_telemetry_emit(
                    'plan_changed',
                    [
                        'source'  => 'subscription_portal',
                        'outcome' => 'refreshed',
                    ]
                );
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkout = isset( $_GET['checkout'] ) ? sanitize_key( wp_unslash( $_GET['checkout'] ) ) : '';
        if ( 'success' === $checkout ) {
            $key = 'bbai_tel_chk_' . $uid;
            if ( ! get_transient( $key ) ) {
                set_transient( $key, 1, 600 );
                bbai_telemetry_emit(
                    'upgrade_completed',
                    [
                        'source' => 'checkout_return',
                    ]
                );
            }
        }
    }
}
