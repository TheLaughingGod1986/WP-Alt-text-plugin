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
use BeepBeepAI\AltTextGenerator\Auth_State;
use BeepBeepAI\AltTextGenerator\Services\Usage_Helper;
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
     * Enqueue a tiny no-src bridge for account/admin logout controls.
     *
     * The main dashboard bundles own this behavior when present, but this
     * keeps logout functional if a bundle is unavailable or loaded late.
     */
    private function enqueue_logout_bridge_script(): void {
        $handle = 'bbai-logout-bridge';

        wp_register_script($handle, '', [], BEEPBEEP_AI_VERSION, true);
        wp_enqueue_script($handle);
        wp_localize_script($handle, 'bbaiLogoutBridgeData', [
            'ajax_url'             => admin_url('admin-ajax.php'),
            'nonce'                => wp_create_nonce('beepbeepai_nonce'),
            'logout_action'        => 'beepbeepai_logout',
            'admin_logout_action'  => 'beepbeepai_admin_logout',
            'logout_redirect'      => admin_url('admin.php?page=bbai'),
            'admin_logout_redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai')),
            'admin_logout_confirm' => __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator'),
            'logout_failed'        => __('Logout failed. Please try again.', 'beepbeep-ai-alt-text-generator'),
            'network_error'        => __('Network error. Please try again.', 'beepbeep-ai-alt-text-generator'),
        ]);

        wp_add_inline_script(
            $handle,
            <<<'JS'
(function() {
    'use strict';

    if (window.bbaiLogoutBridgeReady) {
        return;
    }
    window.bbaiLogoutBridgeReady = true;

    function getConfig() {
        var bridge = window.bbaiLogoutBridgeData || {};
        var legacy = window.bbai_ajax || {};
        return {
            ajaxUrl: bridge.ajax_url || legacy.ajax_url || legacy.ajaxurl || '',
            nonce: bridge.nonce || legacy.nonce || '',
            logoutAction: bridge.logout_action || 'beepbeepai_logout',
            adminLogoutAction: bridge.admin_logout_action || 'beepbeepai_admin_logout',
            logoutRedirect: bridge.logout_redirect || legacy.logout_redirect || window.location.href,
            adminLogoutRedirect: bridge.admin_logout_redirect || window.location.href,
            adminLogoutConfirm: bridge.admin_logout_confirm || legacy.admin_logout_confirm || 'Are you sure you want to log out of the admin panel?',
            logoutFailed: bridge.logout_failed || 'Logout failed. Please try again.',
            networkError: bridge.network_error || 'Network error. Please try again.'
        };
    }

    function clearClientAuthState() {
        try {
            if (window.localStorage) {
                window.localStorage.removeItem('alttextai_token');
                window.localStorage.removeItem('bbai_subscription_cache');
            }
        } catch (error) {
            /* Ignore storage access errors. */
        }
    }

    function setBusy(button, busy) {
        if (!button) {
            return;
        }
        button.disabled = !!busy;
        button.classList.toggle('is-loading', !!busy);
        button.classList.toggle('bbai-btn-loading', !!busy);
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function redirectFromResponse(payload, fallbackUrl) {
        var data = payload && payload.data && typeof payload.data === 'object' ? payload.data : {};
        var redirect = data.redirect || payload.redirect || fallbackUrl || window.location.href;
        window.location.href = redirect;
    }

    function showError(message) {
        if (window.bbaiModal && typeof window.bbaiModal.error === 'function') {
            window.bbaiModal.error(message);
            return;
        }
        window.alert(message);
    }

    function postLogout(action, config) {
        var body = new window.URLSearchParams();
        body.append('action', action);
        body.append('nonce', config.nonce);

        return window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        }).then(function(response) {
            return response.json().catch(function() {
                return {};
            });
        });
    }

    function handleLogoutClick(button, isAdminLogout) {
        var config = getConfig();
        var fallbackRedirect = isAdminLogout ? config.adminLogoutRedirect : config.logoutRedirect;
        var action = isAdminLogout ? config.adminLogoutAction : config.logoutAction;

        if (isAdminLogout && !window.confirm(config.adminLogoutConfirm)) {
            return;
        }

        clearClientAuthState();

        if (!config.ajaxUrl || !config.nonce) {
            window.location.href = fallbackRedirect;
            return;
        }

        setBusy(button, true);

        postLogout(action, config).then(function(payload) {
            if (payload && payload.success === false) {
                var data = payload.data && typeof payload.data === 'object' ? payload.data : {};
                throw new Error(data.message || config.logoutFailed);
            }
            redirectFromResponse(payload || {}, fallbackRedirect);
        }).catch(function(error) {
            setBusy(button, false);
            showError(error && error.message ? error.message : config.networkError);
        });
    }

    document.addEventListener('click', function(event) {
        var target = event.target;
        var button = target && target.closest ? target.closest('[data-action="logout"], #bbai-admin-logout-btn') : null;

        if (!button || button.disabled) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }

        handleLogoutClick(button, button.id === 'bbai-admin-logout-btn');
    }, true);

    window.handleLogout = window.handleLogout || function() {
        handleLogoutClick(document.querySelector('[data-action="logout"]'), false);
    };
})();
JS,
            'after'
        );
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
    private function enqueue_background_job_widget_assets(string $base_url, string $base_path, bool $include_recovery_client = false): void {
        $bbai_job_state_js = 'assets/js/admin/job-state.js';
        if ( file_exists( $base_path . $bbai_job_state_js ) ) {
            wp_enqueue_script(
                'bbai-job-state',
                $base_url . $bbai_job_state_js,
                [],
                $this->get_asset_version( $bbai_job_state_js, '1.0.0', $base_path ),
                true
            );
        }

        $bbai_job_widget_js = 'assets/js/admin/job-widget.js';
        if ( file_exists( $base_path . $bbai_job_widget_js ) ) {
            wp_enqueue_script(
                'bbai-job-widget',
                $base_url . $bbai_job_widget_js,
                [ 'jquery', 'bbai-job-state' ],
                $this->get_asset_version( $bbai_job_widget_js, '1.0.0', $base_path ),
                true
            );
            wp_localize_script(
                'bbai-job-widget',
                'bbaiJobWidget',
                [
                    'dashboardUrl' => esc_url_raw( admin_url( 'admin.php?page=bbai' ) ),
                ]
            );
        }

        if ( $include_recovery_client ) {
            $bbai_licensed_bulk_client = 'assets/js/admin/bbai-licensed-bulk-job-client.js';
            if ( is_readable( $base_path . $bbai_licensed_bulk_client ) ) {
                wp_enqueue_script(
                    'bbai-licensed-bulk-job-client',
                    $base_url . $bbai_licensed_bulk_client,
                    [ 'jquery', 'bbai-job-state' ],
                    $this->get_asset_version( $bbai_licensed_bulk_client, '1.0.0', $base_path ),
                    true
                );
                wp_localize_script(
                    'bbai-licensed-bulk-job-client',
                    'bbai_ajax',
                    [
                        'ajaxurl'                => admin_url( 'admin-ajax.php' ),
                        'ajax_url'               => admin_url( 'admin-ajax.php' ),
                        'nonce'                  => wp_create_nonce( 'beepbeepai_nonce' ),
                        'admin_url'              => admin_url( 'admin.php' ),
                        'use_licensed_bulk_jobs' => method_exists( $this, 'should_use_licensed_bulk_jobs_api' ) && $this->should_use_licensed_bulk_jobs_api(),
                    ]
                );
            }
        }

        $bbai_job_widget_css = 'assets/css/components/job-widget.css';
        $dashboard_css_bundle = 'assets/css/bbai-dashboard-page.bundle.css';
        if (
            file_exists( $base_path . $bbai_job_widget_css )
            && ! ( $this->is_dashboard_tab() && is_readable( $base_path . $dashboard_css_bundle ) )
        ) {
            wp_enqueue_style(
                'bbai-job-widget',
                $base_url . $bbai_job_widget_css,
                [],
                $this->get_asset_version( $bbai_job_widget_css, '1.0.0', $base_path )
            );
        }
    }

    private function enqueue_media_library_assets(string $hook, string $base_url, string $base_path): void {
        $use_debug_assets = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $js_base  = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';

        $toast_file = $this->get_asset_path($js_base, 'toast', $use_debug_assets, 'js', $base_path);
        $toast_version = $this->get_asset_version($toast_file, '1.0.0', $base_path);
        $admin_file = $this->get_asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js', $base_path);
        $admin_version = $this->get_asset_version($admin_file, '3.0.6', $base_path);

        $checkout_prices = $this->get_checkout_price_ids();
        $l10n_common = $this->get_common_l10n();
        $auth_state = Auth_State::resolve($this->api_client);
        $usage_data = !empty($auth_state['has_connected_account'])
            ? Usage_Tracker::get_local_usage_snapshot()
            : Usage_Helper::get_usage($this->api_client, false);
        $trial_status = method_exists($this, 'get_trial_status') ? $this->get_trial_status() : [];

        if ( file_exists( $base_path . $toast_file ) ) {
            wp_enqueue_script('bbai-toast', $base_url . $toast_file, [], $toast_version, true);
        } else {
            wp_register_script( 'bbai-toast', '', [], BEEPBEEP_AI_VERSION, true );
        }

        $bbai_banner_message_js = 'assets/js/bbai-banner-message.js';
        if ( file_exists( $base_path . $bbai_banner_message_js ) ) {
            $bbai_banner_message_ver = (string) filemtime($base_path . $bbai_banner_message_js);
            wp_enqueue_script(
                'bbai-banner-message',
                $base_url . $bbai_banner_message_js,
                ['wp-i18n'],
                $bbai_banner_message_ver,
                true
            );
        } else {
            wp_register_script( 'bbai-banner-message', '', [], BEEPBEEP_AI_VERSION, true );
        }

        $bbai_admin_script_deps = ['jquery', 'wp-i18n', 'bbai-toast', 'bbai-banner-message', 'bbai-telemetry'];
        $bbai_job_state_js = 'assets/js/admin/job-state.js';
        if ( file_exists( $base_path . $bbai_job_state_js ) ) {
            wp_enqueue_script(
                'bbai-job-state',
                $base_url . $bbai_job_state_js,
                [],
                $this->get_asset_version( $bbai_job_state_js, '1.0.0', $base_path ),
                true
            );
            $bbai_admin_script_deps[] = 'bbai-job-state';
        }

        $bbai_licensed_bulk_client = 'assets/js/admin/bbai-licensed-bulk-job-client.js';
        if ( is_readable( $base_path . $bbai_licensed_bulk_client ) ) {
            wp_enqueue_script(
                'bbai-licensed-bulk-job-client',
                $base_url . $bbai_licensed_bulk_client,
                ['jquery', 'bbai-job-state'],
                $this->get_asset_version( $bbai_licensed_bulk_client, '1.0.0', $base_path ),
                true
            );
            $bbai_admin_script_deps[] = 'bbai-licensed-bulk-job-client';
        }

        $bbai_micro_motion = 'assets/js/admin/micro-motion.js';
        if ( is_readable( $base_path . $bbai_micro_motion ) ) {
            wp_enqueue_script(
                'bbai-micro-motion',
                $base_url . $bbai_micro_motion,
                [],
                $this->get_asset_version( $bbai_micro_motion, '1.0.0', $base_path ),
                true
            );
        }

        if ( file_exists( $base_path . $admin_file ) ) {
            wp_enqueue_script('bbai-admin', $base_url . $admin_file, $bbai_admin_script_deps, $admin_version, true);
        } else {
            wp_register_script( 'bbai-admin', '', [], BEEPBEEP_AI_VERSION, true );
        }
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
            'trial' => $trial_status,
            'anonymous' => [
                'is_guest_trial' => !empty($trial_status['should_gate']),
                'anon_cookie_name' => function_exists('\BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name')
                    ? \BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name()
                    : 'bbai_anon_id',
            ],
            'wizard' => $this->get_setup_wizard_bootstrap(),
            'monetisation' => $this->get_monetisation_bootstrap_for_localize(),
        ]);
        $bbai_canonical_api_base = '';
        if ( isset( $this->api_client ) && is_object( $this->api_client ) && method_exists( $this->api_client, 'get_api_url' ) ) {
            $bbai_canonical_api_base = $this->api_client->get_api_url();
        } else {
            $bbai_canonical_api_base = \BeepBeepAI\AltTextGenerator\API_Client_V2::get_instance()->get_api_url();
        }

        wp_localize_script('bbai-admin', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
            'api_url' => esc_url_raw( untrailingslashit( $bbai_canonical_api_base ) ),
            'admin_url' => admin_url('admin.php'),
            'admin_logout_confirm' => __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator'),
            'can_manage' => $this->user_can_manage(),
            'logout_redirect' => admin_url('admin.php?page=bbai'),
            'is_guest_trial' => !empty($trial_status['should_gate']),
            'use_licensed_bulk_jobs' => method_exists($this, 'should_use_licensed_bulk_jobs_api') && $this->should_use_licensed_bulk_jobs_api(),
            'anon_cookie_name' => function_exists('\BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name')
                ? \BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name()
                : 'bbai_anon_id',
            // DEV safety flag: warning-only, no behavior changes.
            'dev_mode' => defined('BBAI_DEV_MODE') && (bool) BBAI_DEV_MODE,
        ]);
        wp_localize_script('bbai-admin', 'bbai_env', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'admin_url' => admin_url(),
            'upload_url'=> admin_url('upload.php'),
            'rest_root' => esc_url_raw(rest_url()),
            'nonce'     => wp_create_nonce('bbai_ajax_nonce'),
        ]);

        // Background job floating widget.
        $this->enqueue_background_job_widget_assets($base_url, $base_path);

        $bbai_funnel_state_js = 'assets/js/admin/funnel-state.js';
        if ( file_exists( $base_path . $bbai_funnel_state_js ) ) {
            wp_enqueue_script(
                'bbai-funnel-state',
                $base_url . $bbai_funnel_state_js,
                [ 'bbai-admin' ],
                $this->get_asset_version( $bbai_funnel_state_js, '1.0.2', $base_path ),
                true
            );
        }

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
        $current_page = $this->get_current_admin_page();
        $resolved_tab = $this->get_resolved_tab();
        $is_dashboard_tab = $this->is_dashboard_tab();
        $is_analytics_tab = $this->is_analytics_tab();
        $is_usage_tab = 'usage' === $resolved_tab || 'bbai-credit-usage' === $current_page;
        $is_debug_page = 'bbai-debug' === $current_page;
        $needs_contact_modal = in_array($resolved_tab, ['settings', 'help'], true) || in_array($current_page, ['bbai-settings', 'bbai-guide'], true);

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

        // Unified CSS bundle (prefer non-minified for static analysis / Plugin Check; min fallback only if needed)
        $unified_css = 'assets/css/unified.css';
        if ( ! file_exists( $base_path . $unified_css ) ) {
            $unified_css = 'assets/css/unified.min.css';
        }
        wp_enqueue_style(
            'bbai-unified',
            $base_url . $unified_css,
            [],
            $asset_version( $unified_css, '6.0.17' )
        );

        $admin_foundation_tokens_css = 'assets/css/system/bbai-admin-foundation-tokens.css';
        if ( file_exists( $base_path . $admin_foundation_tokens_css ) ) {
            wp_enqueue_style(
                'bbai-admin-foundation-tokens',
                $base_url . $admin_foundation_tokens_css,
                [ 'bbai-unified' ],
                $asset_version( $admin_foundation_tokens_css, '1.0.2' )
            );
        }

        // Modern bundle CSS (prefer non-minified for static analysis / Plugin Check)
        $modern_css = 'assets/css/modern.bundle.css';
        if ( ! file_exists( $base_path . $modern_css ) ) {
            $modern_css = 'assets/css/modern.bundle.min.css';
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
            $asset_version( $status_card_css, '1.2.4' )
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

        $funnel_hero_css = 'assets/css/features/dashboard/funnel-hero.css';
        if ( file_exists( $base_path . $funnel_hero_css ) ) {
            wp_enqueue_style(
                'bbai-funnel-hero',
                $base_url . $funnel_hero_css,
                [ 'bbai-queue-workflow' ],
                $asset_version( $funnel_hero_css, '1.0.3' )
            );
        }

        $usage_strip_css = 'assets/css/features/dashboard/usage-strip.css';
        if ( file_exists( $base_path . $usage_strip_css ) ) {
            wp_enqueue_style(
                'bbai-usage-strip',
                $base_url . $usage_strip_css,
                [ 'bbai-funnel-hero' ],
                $asset_version( $usage_strip_css, '1.0.0' )
            );
        }

        $retention_strip_css = 'assets/css/features/dashboard/dashboard-retention-strip.css';
        if ( file_exists( $base_path . $retention_strip_css ) ) {
            wp_enqueue_style(
                'bbai-dashboard-retention-strip',
                $base_url . $retention_strip_css,
                [ 'bbai-queue-workflow' ],
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

        // Legacy onboarding page assets removed.

        $saas_consistency_css = 'assets/css/features/dashboard/saas-consistency.css';
        wp_enqueue_style(
            'bbai-saas-consistency',
            $base_url . $saas_consistency_css,
            [ 'bbai-upgrade-modal-refresh' ],
            $asset_version($saas_consistency_css, '1.0.26')
        );

        $product_banner_css = 'assets/css/features/dashboard/product-banner.css';
        wp_enqueue_style(
            'bbai-product-banner',
            $base_url . $product_banner_css,
            [ 'bbai-saas-consistency' ],
            $asset_version($product_banner_css, '1.0.21')
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
            $asset_version($admin_ui_components_css, '1.0.4')
        );

        $micro_motion_css = 'assets/css/features/dashboard/micro-motion.css';
        wp_enqueue_style(
            'bbai-admin-micro-motion',
            $base_url . $micro_motion_css,
            [ 'bbai-admin-ui-components' ],
            $asset_version($micro_motion_css, '1.0.2')
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

        $dashboard_header_css = 'assets/css/features/dashboard/dashboard-header.css';
        wp_enqueue_style(
            'bbai-dashboard-header',
            $base_url . $dashboard_header_css,
            [ 'bbai-section-header' ],
            $asset_version( $dashboard_header_css, '1.0.0' )
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
                $asset_version( $admin_product_states_css, '1.0.5' )
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
                    $asset_version( $admin_library_workspace_polish_css, '1.0.4' )
                );
            }
        }

        $admin_button_system_css = 'assets/css/system/bbai-admin-button-system.css';
        if ( file_exists( $base_path . $admin_button_system_css ) ) {
            $admin_button_system_deps = [ 'bbai-saas-consistency' ];
            $admin_library_polish_path = 'assets/css/system/bbai-admin-library-workspace-polish.css';
            $admin_table_ws_path       = 'assets/css/system/bbai-admin-table-workspace.css';
            if (
                file_exists( $base_path . $admin_table_ws_path )
                && file_exists( $base_path . $admin_library_polish_path )
            ) {
                $admin_button_system_deps = [ 'bbai-admin-library-workspace-polish' ];
            } elseif ( file_exists( $base_path . $admin_table_ws_path ) ) {
                $admin_button_system_deps = [ 'bbai-admin-table-workspace' ];
            }
            wp_enqueue_style(
                'bbai-admin-button-system',
                $base_url . $admin_button_system_css,
                $admin_button_system_deps,
                $asset_version( $admin_button_system_css, '1.0.0' )
            );
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

        $bulk_progress_refresh_css = 'assets/css/system/bbai-bulk-progress-refresh.css';
        if ( file_exists( $base_path . $bulk_progress_refresh_css ) ) {
            wp_enqueue_style(
                'bbai-bulk-progress-refresh',
                $base_url . $bulk_progress_refresh_css,
                [ 'bbai-modal' ],
                $asset_version( $bulk_progress_refresh_css, '1.0.0' )
            );
        }

        $stats_data = $is_dashboard_tab
            ? $this->get_cached_dashboard_stats_context_for_assets()
            : $this->get_dashboard_stats_payload();
        $usage_data = Usage_Tracker::get_local_usage_snapshot();

        if ( file_exists( $base_path . $dashboard_js ) ) {
            wp_enqueue_script(
                'bbai-dashboard',
                $base_url . $dashboard_js,
                ['jquery', 'wp-i18n', 'bbai-toast', 'bbai-banner-message', 'bbai-telemetry', 'bbai-admin'],
                $asset_version($dashboard_js, '3.0.4'),
                true
            );
        } else {
            // Register an empty stub so dependent scripts don't break WP's dependency resolver.
            wp_register_script( 'bbai-dashboard', '', [], BEEPBEEP_AI_VERSION, true );
        }

        $dashboard_state_js = 'assets/js/bbai-dashboard-state.js';
        if ( ! $is_dashboard_tab && file_exists( $base_path . $dashboard_state_js ) ) {
            wp_enqueue_script(
                'bbai-dashboard-state',
                $base_url . $dashboard_state_js,
                [ 'bbai-dashboard' ],
                $asset_version( $dashboard_state_js, '1.0.0' ),
                true
            );
        }

        if ( $is_analytics_tab && file_exists( $base_path . $analytics_js ) ) {
            wp_enqueue_script(
                'bbai-analytics',
                $base_url . $analytics_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($analytics_js, '1.1.1'),
                true
            );
        }

        if ( file_exists( $base_path . $upgrade_js ) ) {
            wp_enqueue_script(
                'bbai-upgrade',
                $base_url . $upgrade_js,
                ['jquery', 'bbai-telemetry'],
                $asset_version($upgrade_js, '3.1.0'),
                true
            );
        }

        // Auth modal JS — only enqueue when the file exists.
        if ( file_exists( $base_path . $auth_js ) ) {
            wp_enqueue_script(
                'bbai-auth',
                $base_url . $auth_js,
                ['jquery', 'wp-i18n', 'bbai-dashboard'],
                $asset_version($auth_js, '4.0.1'),
                true
            );
        }

        if ( ! $this->is_dashboard_tab() && file_exists( $base_path . $admin_panel_js ) ) {
            wp_enqueue_script(
                'bbai-admin-panel',
                $base_url . $admin_panel_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($admin_panel_js, '1.0.0'),
                true
            );
        }

        if ( file_exists( $base_path . $dashboard_scripts_js ) ) {
            wp_enqueue_script(
                'bbai-dashboard-scripts',
                $base_url . $dashboard_scripts_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($dashboard_scripts_js, '1.0.0'),
                true
            );
        }

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

        if ( file_exists( $base_path . $logger_js ) ) {
            wp_enqueue_script(
                'bbai-logger',
                $base_url . $logger_js,
                [],
                $asset_version($logger_js, '4.3.0'),
                true
            );
        } else {
            // Stub so bbai-modal's dependency on bbai-logger doesn't cause a broken enqueue.
            wp_register_script( 'bbai-logger', '', [], BEEPBEEP_AI_VERSION, true );
        }

        wp_enqueue_style(
            'bbai-tooltips',
            $base_url . $tooltips_css,
            ['bbai-unified'],
            $asset_version($tooltips_css, '4.3.0')
        );

        if ( file_exists( $base_path . $tooltips_js ) ) {
            wp_enqueue_script(
                'bbai-tooltips',
                $base_url . $tooltips_js,
                ['jquery', 'wp-i18n'],
                $asset_version($tooltips_js, '4.3.0'),
                true
            );
        }

        if ( file_exists( $base_path . $modal_js ) ) {
            wp_enqueue_script(
                'bbai-modal',
                $base_url . $modal_js,
                ['jquery', 'bbai-logger', 'wp-i18n'],
                $asset_version($modal_js, '4.3.0'),
                true
            );
        }

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

        if ( $is_usage_tab && file_exists($base_path . $usage_bridge_js)) {
            wp_enqueue_script(
                'bbai-usage-bridge',
                $base_url . $usage_bridge_js,
                ['bbai-dashboard', 'wp-element'],
                $asset_version($usage_bridge_js, '1.0.0'),
                true
            );
        }

        if ( $is_debug_page && file_exists( $base_path . $debug_js ) ) {
            wp_enqueue_script(
                'bbai-debug',
                $base_url . $debug_js,
                ['jquery'],
                $asset_version($debug_js, '1.0.0'),
                true
            );
        }

        if ( $needs_contact_modal && file_exists( $base_path . $contact_modal_js ) ) {
            wp_enqueue_script(
                'bbai-contact-modal',
                $base_url . $contact_modal_js,
                ['jquery', 'wp-i18n'],
                $asset_version($contact_modal_js, '1.0.0'),
                true
            );
        }

        if ( $needs_contact_modal ) {
            wp_enqueue_style(
                'bbai-contact-modal',
                $base_url . $contact_modal_css,
                ['bbai-unified'],
                $asset_version($contact_modal_css, '1.0.0')
            );
        }

        if ( $needs_contact_modal ) {
            wp_localize_script('bbai-contact-modal', 'bbaiContactData', [
                'wp_version' => get_bloginfo('version'),
                'plugin_version' => BEEPBEEP_AI_VERSION,
            ]);
        }

        $this->enqueue_logout_bridge_script();

        // Logged-in dashboard controller: optional enhancement (polling / client re-render).
        // Dashboard is PHP-first; enable with: add_filter( 'bbai_enqueue_logged_in_dashboard_controller', '__return_true' );
        $bbai_li_controller_js = 'assets/js/admin/logged-in-dashboard-controller.js';
        if ( file_exists( $base_path . $bbai_li_controller_js ) && $this->is_dashboard_tab() && apply_filters( 'bbai_enqueue_logged_in_dashboard_controller', false ) ) {
            wp_enqueue_script(
                'bbai-logged-in-dashboard',
                $base_url . $bbai_li_controller_js,
                [ 'bbai-admin', 'bbai-dashboard' ],
                $asset_version( $bbai_li_controller_js, '1.0.0' ),
                true
            );
        }

        // Logged-in dashboard state CSS.
        $bbai_li_css = 'assets/css/features/dashboard/logged-in-state.css';
        if ( file_exists( $base_path . $bbai_li_css ) ) {
            wp_enqueue_style(
                'bbai-logged-in-state',
                $base_url . $bbai_li_css,
                [ 'bbai-section-header' ],
                $asset_version( $bbai_li_css, '1.0.0' )
            );
        }

        // Dashboard composition rebalance — loads after logged-in-state to win cascade.
        $bbai_composition_css = 'assets/css/features/dashboard/dashboard-composition.css';
        if ( file_exists( $base_path . $bbai_composition_css ) ) {
            wp_enqueue_style(
                'bbai-dashboard-composition',
                $base_url . $bbai_composition_css,
                [ 'bbai-logged-in-state', 'bbai-dashboard-header' ],
                $asset_version( $bbai_composition_css, '1.0.0' )
            );
        }

        // Inline CTA fallback handler — runs when the full modal JS bundle is absent.
        // Handles show-dashboard-auth and show-upgrade-modal with graceful navigation fallbacks.
        $bbai_signup_url  = esc_url_raw( 'https://app.beepbeep.ai/register' );
        $bbai_login_url   = esc_url_raw( 'https://app.beepbeep.ai/login' );
        $bbai_upgrade_url = esc_url_raw( \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_upgrade_url() );
        $bbai_cta_inline = implode( "\n", [
            '(function(){',
            '    var signupUrl  = ' . wp_json_encode( $bbai_signup_url )  . ';',
            '    var loginUrl   = ' . wp_json_encode( $bbai_login_url )   . ';',
            '    var upgradeUrl = ' . wp_json_encode( $bbai_upgrade_url ) . ';',
            '    if (!window.BBAI_LOG) {',
            '        window.BBAI_LOG = { log: function(){}, warn: function(){}, error: function(){}, info: function(){} };',
            '    }',
            '    function handleDashboardCta(e) {',
            '        var el = e.target.closest("[data-action]");',
            '        if (!el) return;',
            '        var action = el.getAttribute("data-action");',
            '        if (action === "show-dashboard-auth") {',
            '            if (typeof window.bbaiShowDashboardAuth === "function") return;',
            '            e.preventDefault();',
            '            var tab = el.getAttribute("data-auth-tab") || "register";',
            '            window.location.href = (tab === "login") ? loginUrl : signupUrl;',
            '            return;',
            '        }',
            '        if (action === "show-upgrade-modal") {',
            '            if (typeof window.showUpgradeModal === "function") return;',
            '            e.preventDefault();',
            '            window.location.href = upgradeUrl;',
            '        }',
            '    }',
            '    document.addEventListener("click", handleDashboardCta);',
            '})();',
        ] );
        // Attach to wp-i18n which is always enqueued on plugin admin pages.
        wp_add_inline_script( 'wp-i18n', $bbai_cta_inline );

        $this->localize_dashboard_scripts($stats_data, $usage_data, $checkout_prices, $l10n_common);
        $this->maybe_bundle_dashboard_styles($base_url, $base_path);
    }

    /**
     * Collapse dashboard CSS into one generated bundle on the dashboard page only.
     *
     * Source styles remain split for editing and non-dashboard admin pages.
     */
    private function maybe_bundle_dashboard_styles(string $base_url, string $base_path): void {
        if (! $this->is_dashboard_tab()) {
            return;
        }

        $bundle = 'assets/css/bbai-dashboard-page.bundle.css';
        if (! is_readable($base_path . $bundle)) {
            return;
        }

        $handles = [
            'bbai-job-widget',
            'bbai-unified',
            'bbai-admin-foundation-tokens',
            'bbai-modern',
            'bbai-admin-wizard',
            'bbai-dashboard-status-card-refresh',
            'bbai-command-hero-host',
            'bbai-filter-group',
            'bbai-queue-workflow',
            'bbai-funnel-hero',
            'bbai-usage-strip',
            'bbai-dashboard-retention-strip',
            'bbai-upgrade-modal-refresh',
            'bbai-saas-consistency',
            'bbai-product-banner',
            'bbai-page-hero',
            'bbai-admin-ui-components',
            'bbai-admin-micro-motion',
            'bbai-card-rhythm',
            'bbai-section-header',
            'bbai-dashboard-header',
            'bbai-admin-controls',
            'bbai-admin-interactions',
            'bbai-admin-product-states',
            'bbai-admin-surfaces',
            'bbai-admin-design-system',
            'bbai-admin-semantic-status',
            'bbai-admin-table-workspace',
            'bbai-admin-page-adoption',
            'bbai-admin-surface-taxonomy',
            'bbai-admin-library-workspace-polish',
            'bbai-admin-button-system',
            'bbai-modal',
            'bbai-bulk-progress-refresh',
            'bbai-phase17-assistant',
            'bbai-tooltips',
            'bbai-logged-in-state',
            'bbai-dashboard-composition',
        ];

        foreach ($handles as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }

        wp_enqueue_style(
            'bbai-dashboard-page-bundle',
            $base_url . $bundle,
            [],
            $this->get_asset_version($bundle, BEEPBEEP_AI_VERSION, $base_path)
        );
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
        $trial_status = method_exists($this, 'get_trial_status') ? $this->get_trial_status() : [];
        $anon_cookie_name = function_exists('\BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name')
            ? \BeepBeepAI\AltTextGenerator\bbai_get_anon_cookie_name()
            : 'bbai_anon_id';

        if (wp_script_is('bbai-debug', 'enqueued')) {
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
        }

        if (!class_exists(\BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::class, false)) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-path-resolver.php';
        }
        if (!class_exists(\BeepBeepAI\AltTextGenerator\Auth_State::class, false)) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';
        }
        $bbai_auth_upgrade_path = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
        $bbai_has_connected_upgrade_path = !empty($bbai_auth_upgrade_path['has_connected_account']);
        // Trial ladder (free account signup) when site is not linked to SaaS — not only when Trial_Quota::should_gate.
        $bbai_upgrade_path_guest = !empty($trial_status['should_gate']) || !$bbai_has_connected_upgrade_path;
        $bbai_upgrade_plan_slug = $bbai_upgrade_path_guest
            ? 'free'
            : strtolower((string)($usage_data['plan'] ?? $usage_data['plan_type'] ?? 'free'));
        $bbai_upgrade_path_model = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Path_Resolver::resolve([
            'is_guest_trial' => $bbai_upgrade_path_guest,
            'plan_slug' => $bbai_upgrade_plan_slug,
        ]);

        if (!class_exists(\BeepBeepAI\AltTextGenerator\Services\Upgrade_Cta_Resolver::class, false)) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-upgrade-cta-resolver.php';
        }
        $bbai_plan_slug_cta = $bbai_upgrade_path_guest
            ? 'free'
            : strtolower((string) ($usage_data['plan'] ?? $usage_data['plan_type'] ?? 'free'));
        if ('pro' === $bbai_plan_slug_cta) {
            $bbai_plan_slug_cta = 'growth';
        }
        $bbai_rem_cta = null;
        if (isset($usage_data['remaining'])) {
            $bbai_rem_cta = (int) $usage_data['remaining'];
        } elseif (isset($usage_data['credits_remaining'])) {
            $bbai_rem_cta = (int) $usage_data['credits_remaining'];
        }
        $bbai_quota_exhausted_cta = false;
        if ($bbai_has_connected_upgrade_path && in_array($bbai_plan_slug_cta, ['free', 'trial'], true) && null !== $bbai_rem_cta && $bbai_rem_cta <= 0) {
            $bbai_quota_exhausted_cta = true;
        }
        $bbai_upgrade_cta_ui = \BeepBeepAI\AltTextGenerator\Services\Upgrade_Cta_Resolver::for_localize_script([
            'has_connected_account' => $bbai_has_connected_upgrade_path,
            'plan_slug'             => $bbai_plan_slug_cta,
            'credits_remaining'     => $bbai_rem_cta,
            'quota_exhausted'       => $bbai_quota_exhausted_cta,
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
            'usageAdminUrl' => esc_url_raw(admin_url('admin.php?page=bbai-credit-usage')),
            'checkoutPrices' => $checkout_prices,
            'stats'       => $stats_data,
            'initialUsage' => $usage_data,
            'usage'      => $usage_data,
            'quota'      => $usage_data['quota'] ?? [],
            'trial'      => $trial_status,
            'upgradePath' => $bbai_upgrade_path_model,
            'upgradeCtaUi' => $bbai_upgrade_cta_ui,
            'anonymous'  => [
                'is_guest_trial' => $bbai_upgrade_path_guest,
                'anon_cookie_name' => $anon_cookie_name,
            ],
            'pendingUpgradeTriggers' => [
                'newUpload' => $this->consume_media_upload_upgrade_trigger(),
            ],
            'wizard'      => $this->get_setup_wizard_bootstrap(),
            'monetisation' => $this->get_monetisation_bootstrap_for_localize(),
        ];

        wp_localize_script('bbai-dashboard', 'BBAI_DASH', $bbai_dash_data);

        $bbai_canonical_api_base = '';
        if ( isset( $this->api_client ) && is_object( $this->api_client ) && method_exists( $this->api_client, 'get_api_url' ) ) {
            $bbai_canonical_api_base = $this->api_client->get_api_url();
        } else {
            $bbai_canonical_api_base = \BeepBeepAI\AltTextGenerator\API_Client_V2::get_instance()->get_api_url();
        }
        $api_url = esc_url_raw( untrailingslashit( $bbai_canonical_api_base ) );
        $sanitized_user_data = $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data());
        $bbai_client_is_authenticated = $this->is_bbai_account_authenticated();
        $bbai_client_user_email = $bbai_client_is_authenticated ? sanitize_email((string) ($sanitized_user_data['email'] ?? '')) : '';
        $bbai_client_user_id = $bbai_client_is_authenticated ? sanitize_text_field((string) ($sanitized_user_data['id'] ?? $sanitized_user_data['_id'] ?? $sanitized_user_data['user_id'] ?? '')) : '';

        wp_localize_script('bbai-dashboard', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
            'api_url' => $api_url,
            'is_authenticated' => $bbai_client_is_authenticated,
            'user_data' => $sanitized_user_data,
            'admin_url' => admin_url('admin.php'),
            'admin_logout_confirm' => __('Are you sure you want to log out of the admin panel?', 'beepbeep-ai-alt-text-generator'),
            'can_manage' => $this->user_can_manage(),
            'logout_redirect' => admin_url('admin.php?page=bbai'),
            'stripe_links' => self::DEFAULT_STRIPE_LINKS,
            'is_guest_trial' => $bbai_upgrade_path_guest,
            'use_licensed_bulk_jobs' => method_exists($this, 'should_use_licensed_bulk_jobs_api') && $this->should_use_licensed_bulk_jobs_api(),
            'anon_cookie_name' => $anon_cookie_name,
        ]);
        wp_localize_script('bbai-dashboard', 'bbaiUser', [
            'email' => $bbai_client_user_email,
            'id' => $bbai_client_user_id,
            'isGuest' => ! $bbai_client_is_authenticated,
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
                '(function(){function bbaiTryOpenAuthModal(tab,context){var mode=tab==="register"?"register":"login";var modalContext=context||(mode==="login"?"login":"fix");if(typeof window.showAuthModal==="function"){window.showAuthModal(mode,modalContext);return true;}if(window.authModal&&typeof window.authModal.show==="function"){if(typeof window.authModal.setModalContext==="function"){window.authModal.setModalContext(modalContext);}window.authModal.show({context:modalContext});if(mode==="register"&&typeof window.authModal.showRegisterForm==="function"){window.authModal.showRegisterForm(modalContext);return true;}if(mode!=="register"&&typeof window.authModal.showLoginForm==="function"){window.authModal.showLoginForm("login");return true;}return true;}return false;}function bbaiOpenAuthFromQuery(){try{var u=new URL(window.location.href);if(u.searchParams.get("bbai_open_auth")!=="1"){return;}var tab=u.searchParams.get("bbai_auth_tab")==="register"?"register":"login";var context=u.searchParams.get("bbai_auth_context")||"";u.searchParams.delete("bbai_open_auth");u.searchParams.delete("bbai_auth_tab");u.searchParams.delete("bbai_auth_context");var q=u.searchParams.toString();var next=u.pathname+(q?"?"+q:"")+u.hash;window.history.replaceState({},document.title,next);if(bbaiTryOpenAuthModal(tab,context)){return;}var n=0;var t=window.setInterval(function(){n+=1;if(bbaiTryOpenAuthModal(tab,context)||n>40){window.clearInterval(t);}},75);}catch(e){}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",bbaiOpenAuthFromQuery);}else{bbaiOpenAuthFromQuery();}})();',
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
            $has_token = false;
            $has_license = false;

            try {
                $has_token = method_exists($this->api_client, 'get_token') && '' !== (string) $this->api_client->get_token();
                $has_license = method_exists($this->api_client, 'has_active_license') && (bool) $this->api_client->has_active_license();
            } catch (\Exception $e) {
                $has_token = false;
                $has_license = false;
            } catch (\Error $e) {
                $has_token = false;
                $has_license = false;
            }

            if ($has_token || $has_license) {
                return true;
            }
        }

        return false;
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
     *
     * This intentionally matches the backend/Supabase site identity source of
     * truth sent as X-Site-Hash/site_hash/site_id/install_id.
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

        return $site_identifier;
    }

    /**
     * Legacy/debug-only SHA-256 form of the site identity.
     */
    private function get_posthog_site_hash_sha256(string $site_hash = ''): string {
        $raw_site_hash = '' !== $site_hash ? $site_hash : $this->get_posthog_site_hash();
        return hash('sha256', $raw_site_hash);
    }

    /**
     * Resolve whether client analytics should be marked internal/test traffic.
     */
    private function is_posthog_internal_environment(array $user_data = []): bool {
        if (!function_exists('\BeepBeepAI\AltTextGenerator\is_internal_environment')) {
            $site_id_helper = BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
            if (is_readable($site_id_helper)) {
                require_once $site_id_helper;
            }
        }

        $email = sanitize_email((string) ($user_data['email'] ?? $user_data['user_email'] ?? ''));

        return function_exists('\BeepBeepAI\AltTextGenerator\is_internal_environment')
            ? (bool) \BeepBeepAI\AltTextGenerator\is_internal_environment($email)
            : (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Resolve the authoritative license identity when present in cached auth data.
     *
     * @param array<string, mixed> $user_data    Sanitized account payload.
     * @param array<string, mixed> $license_data Cached license payload.
     */
    private function resolve_posthog_license_id(array $user_data, array $license_data): string {
        $candidates = [
            $license_data['license_id'] ?? null,
            $license_data['licenseId'] ?? null,
            $license_data['id'] ?? null,
            $license_data['_id'] ?? null,
            $license_data['license']['id'] ?? null,
            $license_data['license']['_id'] ?? null,
            $license_data['license']['license_id'] ?? null,
            $license_data['organization']['license_id'] ?? null,
            $license_data['organization']['licenseId'] ?? null,
            $license_data['organization']['id'] ?? null,
            $user_data['license_id'] ?? null,
            $user_data['licenseId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = sanitize_text_field((string) ($candidate ?? ''));
            if ('' !== $value) {
                return $value;
            }
        }

        return '';
    }

    /**
     * Build the stable internal identity payload exposed to the PostHog client.
     *
     * @param array<string, mixed> $user_data    Sanitized account payload.
     * @param array<string, mixed> $license_data Cached license payload.
     * @param string               $site_hash    Stable site hash.
     * @param string               $plan_type    Current plan slug.
     * @return array<string, mixed>
     */
    private function get_posthog_identity_context(array $user_data, array $license_data, string $site_hash, string $plan_type): array {
        $account_id = sanitize_text_field((string) ($user_data['id'] ?? $user_data['_id'] ?? ''));
        $user_id    = $account_id;
        $license_id = $this->resolve_posthog_license_id($user_data, $license_data);

        $site_id = '';
        if ( isset( $license_data['site'] ) && is_array( $license_data['site'] ) ) {
            $site_id = sanitize_text_field((string) ($license_data['site']['id'] ?? $license_data['site']['_id'] ?? ''));
        }

        $context = [
            'license_id'          => $license_id,
            'account_id'          => $account_id,
            'user_id'             => $user_id,
            'plan'                => $plan_type,
            'plan_type'           => $plan_type,
            'site_id'             => $site_id,
            'site_hash'           => $site_hash,
            'plugin_version'      => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
        ];

        return array_filter(
            $context,
            static function ( $value ) {
                return null !== $value && '' !== $value;
            }
        );
    }

    /**
     * Resolve the best PostHog distinct id for an authenticated plugin account.
     *
     * @param array<string, mixed> $identity_context Identity payload from get_posthog_identity_context().
     * @return string
     */
    private function resolve_posthog_identify_id(array $identity_context): string {
        foreach ( ['license_id', 'account_id', 'site_hash'] as $key ) {
            if ( empty( $identity_context[ $key ] ) ) {
                continue;
            }

            return sanitize_text_field((string) $identity_context[ $key ]);
        }

        return '';
    }

    /**
     * Shared client bootstrap for the PostHog wrapper.
     *
     * @return array<string, mixed>
     */
    private function get_posthog_client_config(): array {
        $usage_data = $this->get_cached_usage_context_for_assets();
        $stats_data = $this->get_cached_dashboard_stats_context_for_assets();
        $user_data  = isset($this->api_client) ? $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data()) : [];
        $license_data = (isset($this->api_client) && method_exists($this->api_client, 'get_license_data'))
            ? $this->api_client->get_license_data()
            : [];
        $is_logged_in = $this->is_bbai_account_authenticated();
        $page_key     = $this->get_posthog_page_key();
        $site_hash    = $this->get_posthog_site_hash();
        $site_hash_sha256 = $this->get_posthog_site_hash_sha256($site_hash);

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

        $identity_context = $this->get_posthog_identity_context(
            is_array($user_data) ? $user_data : [],
            is_array($license_data) ? $license_data : [],
            $site_hash,
            $plan_type
        );
        $identify_id = $this->resolve_posthog_identify_id($identity_context);
        $is_internal = $this->is_posthog_internal_environment(is_array($user_data) ? $user_data : []);

        $page_view_events = [
            'dashboard'       => 'dashboard_viewed',
            'guest_dashboard' => 'guest_dashboard_viewed',
            'alt_library'     => 'alt_library_viewed',
            'analytics'       => 'analytics_viewed',
            'usage'           => 'usage_viewed',
            'settings'        => 'settings_viewed',
            'onboarding'      => 'onboarding_viewed',
        ];

        $api_host = class_exists( BBAI_Telemetry::class )
            ? BBAI_Telemetry::get_posthog_api_host()
            : 'https://us.i.posthog.com';
        $asset_url = str_replace('.i.posthog.com', '-assets.i.posthog.com', untrailingslashit($api_host)) . '/static/array.js';
        $api_key = class_exists( BBAI_Telemetry::class )
            ? BBAI_Telemetry::get_posthog_api_key()
            : 'phc_6L7JzpjYRC8Gk4Br3YevTmjZnJsJPvoy9GK7RFdo72s';
        $site_host = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: '' );
        $environment_type = wp_get_environment_type();
        $is_local_site = (bool) preg_match(
            '/(^localhost$|^127\.|^0\.0\.0\.0$|^::1$|\.local$|\.test$|\.localhost$)/i',
            $site_host
        );
        $browser_capture_enabled = '' !== $api_key
            && '' !== $api_host
            && ! $is_local_site
            && ! in_array( $environment_type, [ 'local', 'development' ], true );
        $browser_capture_enabled = (bool) apply_filters(
            'bbai_posthog_browser_capture_enabled',
            $browser_capture_enabled,
            [
                'site_host'        => $site_host,
                'environment_type' => $environment_type,
                'is_local_site'    => $is_local_site,
            ]
        );

        return [
            'enabled'       => $browser_capture_enabled,
            'apiKey'        => $api_key,
            'apiHost'       => $api_host,
            'assetUrl'      => $asset_url,
            'defaults'      => '2026-01-30',
            'instanceName'  => 'bbaiPosthog',
            'debug_posthog' => (defined('BBAI_DEBUG_POSTHOG') && (bool) BBAI_DEBUG_POSTHOG) || (bool) getenv('BBAI_DEBUG_POSTHOG'),
            'debug'         => (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG),
            'replayEnabled' => true,
            'replaySampleRate' => 1,
            'forceReplayEverySession' => true,
            'manualReplayOnly' => false,
            'environment'   => wp_get_environment_type(),
            'pageViewEvents' => $page_view_events,
            'context'       => array_merge([
                'page'               => $page_key,
                'site_hash'          => $site_hash,
                'site_hash_sha256'   => $site_hash_sha256,
                'site_url'           => home_url('/'),
                'is_trial'           => ! $is_logged_in,
                'is_internal'        => $is_internal,
                'is_logged_in'       => $is_logged_in,
                'logged_in_state'    => $is_logged_in ? 'authenticated' : 'anonymous',
                'plan_type'          => $plan_type,
                'quota_remaining'    => $quota_remaining,
                'credits_remaining'  => $quota_remaining,
                'quota_limit'        => $quota_limit,
                'trial_exhausted'    => $trial_exhausted,
                'remaining_free_images' => $remaining_free_images,
                'missing_alt_count'  => max(0, (int) ($stats_data['images_missing_alt'] ?? 0)),
                'needs_review_count' => max(0, (int) ($stats_data['needs_review_count'] ?? 0)),
                'optimized_count'    => max(0, (int) ($stats_data['optimized_count'] ?? 0)),
                'plugin_version'     => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
                'wp_version'         => get_bloginfo('version'),
                'php_version'        => PHP_VERSION,
            ], $identity_context),
            'identify'      => [
                'id'                => $identify_id,
                'person_properties' => array_merge([
                    'plan_type'      => $plan_type,
                    'plan'           => $plan_type,
                    'site_hash'      => $site_hash,
                    'site_hash_sha256' => $site_hash_sha256,
                    'is_internal'    => $is_internal,
                    'plugin_version' => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
                    'wp_admin_page'  => $page_key,
                ], $identity_context),
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
            // Stub so bbai-telemetry's dependency on bbai-posthog doesn't break WP's resolver.
            wp_register_script( 'bbai-posthog', '', [], BEEPBEEP_AI_VERSION, true );
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
            // Stub so bbai-admin's dependency on bbai-telemetry doesn't break WP's resolver.
            wp_register_script( 'bbai-telemetry', '', [], BEEPBEEP_AI_VERSION, true );
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
        $usage = $this->get_cached_usage_context_for_assets();
        $plan  = isset($usage['plan']) ? sanitize_key((string) $usage['plan']) : 'free';
        $plan_type = in_array($plan, ['pro', 'growth', 'agency', 'enterprise'], true) ? 'pro' : ('free' === $plan ? 'free' : 'unknown');
        $site_hash = $this->get_posthog_site_hash();
        $site_hash_sha256 = $this->get_posthog_site_hash_sha256($site_hash);
        $sanitized_user_data = isset($this->api_client) ? $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data()) : [];
        $license_data = (isset($this->api_client) && method_exists($this->api_client, 'get_license_data'))
            ? $this->api_client->get_license_data()
            : [];
        $identity_context = $this->get_posthog_identity_context(
            is_array($sanitized_user_data) ? $sanitized_user_data : [],
            is_array($license_data) ? $license_data : [],
            $site_hash,
            $plan
        );
        $is_internal = $this->is_posthog_internal_environment(is_array($sanitized_user_data) ? $sanitized_user_data : []);

        $uid = get_current_user_id();
        $key = '_bbai_telemetry_session_images_' . gmdate('Ymd');
        $session_images = $uid > 0 ? (int) get_user_meta($uid, $key, true) : 0;

        BBAI_Telemetry::touch_last_active();
        $days_since = BBAI_Telemetry::inactive_days_at_session_start();

        return [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('beepbeepai_nonce'),
            'action'      => 'beepbeepai_telemetry',
            'debug_posthog' => (defined('BBAI_DEBUG_POSTHOG') && (bool) BBAI_DEBUG_POSTHOG) || (bool) getenv('BBAI_DEBUG_POSTHOG'),
            'debug'       => (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG),
            'context'     => [
                'user_id'                  => $uid,
                'plan_type'                => $plan_type,
                'plugin_version'           => defined('BEEPBEEP_AI_VERSION') ? (string) BEEPBEEP_AI_VERSION : '',
                'wp_version'               => get_bloginfo('version'),
                'php_version'              => PHP_VERSION,
                'environment'              => wp_get_environment_type(),
                'page'                     => $this->get_telemetry_page_key(),
                'page_variant'             => $this->get_posthog_page_key(),
                'days_since_last_active'   => $days_since,
                'images_processed_session' => $session_images,
                'account_id'               => $identity_context['account_id'] ?? '',
                'license_id'               => $identity_context['license_id'] ?? null,
                'site_id'                  => $identity_context['site_id'] ?? '',
                'site_hash'                => $site_hash,
                'site_hash_sha256'         => $site_hash_sha256,
                'site_url'                 => home_url('/'),
                'is_trial'                 => empty($identity_context['license_id']),
                'is_internal'              => $is_internal,
            ],
        ];
    }

    /**
     * Fast analytics context for asset localization. This must never force a
     * remote usage refresh because it runs before the dashboard can paint.
     *
     * @return array<string, mixed>
     */
    private function get_cached_usage_context_for_assets(): array {
        if (class_exists(Usage_Tracker::class) && method_exists(Usage_Tracker::class, 'get_local_usage_snapshot')) {
            $snapshot = Usage_Tracker::get_local_usage_snapshot();
            if (is_array($snapshot) && !empty($snapshot)) {
                return $snapshot;
            }
        }

        $cached = class_exists(Usage_Tracker::class) ? get_transient(Usage_Tracker::CACHE_KEY) : false;
        return is_array($cached) ? $cached : [];
    }

    /**
     * Fast dashboard stats for analytics context. Avoids kicking off expensive
     * media-library scans during the PHP document response.
     *
     * @return array<string, mixed>
     */
    private function get_cached_dashboard_stats_context_for_assets(): array {
        $stats = get_transient('bbai_stats_v3');
        $coverage = get_transient('bbai_alt_coverage_scan_v4');

        $payload = is_array($stats) ? $stats : [];
        if (is_array($coverage)) {
            $payload['total_images'] = max(0, (int) ($coverage['total_images'] ?? ($payload['total'] ?? 0)));
            $payload['images_with_alt'] = max(0, (int) ($coverage['images_with_alt'] ?? ($payload['with_alt'] ?? 0)));
            $payload['images_missing_alt'] = max(0, (int) ($coverage['images_missing_alt'] ?? ($payload['missing'] ?? 0)));
            $payload['needs_review_count'] = max(0, (int) ($coverage['needs_review_count'] ?? 0));
            $payload['optimized_count'] = max(0, (int) ($coverage['optimized_count'] ?? 0));
        }

        return $payload;
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
            $this->enqueue_background_job_widget_assets($base_url, $base_path, true);
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

        if ($this->is_dashboard_tab()) {
            $this->dequeue_unused_wp_editor_assets_for_dashboard();
        }
    }

    /**
     * The dashboard is a custom admin screen and does not use the block editor,
     * command palette, or React runtime. WordPress can enqueue those globals in
     * newer admin screens, which adds dozens of cached requests plus a users/me
     * REST call. Keep them off the dashboard while leaving usage/library screens
     * alone in case they need richer WP components later.
     */
    private function dequeue_unused_wp_editor_assets_for_dashboard(): void {
        $handles = [
            'wp-core-commands',
            'wp-commands',
            'wp-block-editor',
            'wp-blocks',
            'wp-core-data',
            'wp-preferences',
            'wp-preferences-persistence',
            'wp-notices',
            'wp-components',
            'wp-rich-text',
            'wp-data',
            'wp-compose',
            'wp-element',
            'wp-api-fetch',
            'react',
            'react-dom',
            'react-jsx-runtime',
            'wp-react-refresh-entry',
            'wp-react-refresh-runtime',
        ];

        foreach ($handles as $handle) {
            wp_dequeue_script($handle);
        }
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
                    'payment_succeeded',
                    [
                        'source' => 'checkout_return',
                    ]
                );
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $checkout_error = isset( $_GET['checkout_error'] ) ? sanitize_text_field( wp_unslash( $_GET['checkout_error'] ) ) : '';
        if ( '' !== $checkout_error ) {
            $key = 'bbai_tel_chkerr_' . $uid . '_' . md5( $checkout_error );
            if ( ! get_transient( $key ) ) {
                set_transient( $key, 1, 600 );
                bbai_telemetry_emit(
                    'checkout_failed',
                    [
                        'source'        => 'checkout_return',
                        'error_code'    => 'checkout_error',
                        'error_message' => $checkout_error,
                    ]
                );
            }
        }
    }
}
