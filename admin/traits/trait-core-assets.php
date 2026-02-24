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
use BeepBeepAI\AltTextGenerator\Debug_Log;

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
            return $source_base . $name . ".$type";
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
        ]);
        wp_localize_script('bbai-admin', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
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

        // Helper closures
        $asset_version = function(string $relative, string $fallback) use ($base_path): string {
            return $this->get_asset_version($relative, $fallback, $base_path);
        };
        $asset_path = function(string $base, string $name, bool $debug, string $type) use ($base_path): string {
            return $this->get_asset_path($base, $name, $debug, $type, $base_path);
        };

        // Unified CSS bundle
        $unified_css = $use_debug_assets ? 'assets/css/unified.css' : 'assets/css/unified.min.css';
        wp_enqueue_style(
            'bbai-unified',
            $base_url . $unified_css,
            [],
            $asset_version($unified_css, '6.0.0')
        );

        // Modern bundle CSS
        wp_enqueue_style(
            'bbai-modern',
            $base_url . 'assets/css/modern.bundle.min.css',
            ['bbai-unified'],
            $asset_version('assets/css/modern.bundle.min.css', '5.0.0')
        );

        // Modal CSS
        wp_enqueue_style(
            'bbai-modal',
            $base_url . $asset_path($css_base, 'bbai-modal', $use_debug_assets, 'css'),
            ['bbai-unified'],
            $asset_version($asset_path($css_base, 'bbai-modal', $use_debug_assets, 'css'), '4.3.0')
        );

        // Tooltips CSS
        wp_enqueue_style(
            'bbai-tooltips',
            $base_url . $asset_path($css_base, 'bbai-tooltips', $use_debug_assets, 'css'),
            ['bbai-unified'],
            $asset_version($asset_path($css_base, 'bbai-tooltips', $use_debug_assets, 'css'), '4.3.0')
        );

        // JavaScript files
        $js_file = $asset_path($js_base, 'bbai-dashboard', $use_debug_assets, 'js');
        $upgrade_js = $asset_path($js_base, 'upgrade-modal', $use_debug_assets, 'js');
        $auth_js = $asset_path($js_base, 'auth-modal', $use_debug_assets, 'js');

        // Logger (must load first)
        wp_enqueue_script(
            'bbai-logger',
            $base_url . $asset_path($js_base, 'bbai-logger', $use_debug_assets, 'js'),
            [],
            $asset_version($asset_path($js_base, 'bbai-logger', $use_debug_assets, 'js'), '4.3.0'),
            true
        );

        // Modal JS
        wp_enqueue_script(
            'bbai-modal',
            $base_url . $asset_path($js_base, 'bbai-modal', $use_debug_assets, 'js'),
            ['jquery', 'bbai-logger', 'wp-i18n'],
            $asset_version($asset_path($js_base, 'bbai-modal', $use_debug_assets, 'js'), '4.3.0'),
            true
        );

        // Tooltips JS
        wp_enqueue_script(
            'bbai-tooltips',
            $base_url . $asset_path($js_base, 'bbai-tooltips', $use_debug_assets, 'js'),
            ['jquery', 'wp-i18n'],
            $asset_version($asset_path($js_base, 'bbai-tooltips', $use_debug_assets, 'js'), '4.3.0'),
            true
        );

        // Loading States JS
        wp_enqueue_script(
            'bbai-loading-states',
            $base_url . $asset_path($js_base, 'bbai-loading-states', $use_debug_assets, 'js'),
            [],
            $asset_version($asset_path($js_base, 'bbai-loading-states', $use_debug_assets, 'js'), '1.0.0'),
            true
        );

        // Performance Optimizations JS - Load early, no dependencies
        $perf_path = $asset_path($js_base, 'bbai-performance', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-performance',
            $base_url . $perf_path,
            [],
            $asset_version($perf_path, file_exists($base_path . $perf_path) ? filemtime($base_path . $perf_path) : '1.0.0'),
            true
        );

        // Accessibility JS - Load early, no dependencies
        $access_path = $asset_path($js_base, 'bbai-accessibility', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-accessibility',
            $base_url . $access_path,
            [],
            $asset_version($access_path, file_exists($base_path . $access_path) ? filemtime($base_path . $access_path) : '1.0.0'),
            true
        );

        // Toast Notifications JS - Load early, needed by other scripts
        $toast_path = $asset_path($js_base, 'bbai-toast', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-toast',
            $base_url . $toast_path,
            [],
            $asset_version($toast_path, file_exists($base_path . $toast_path) ? filemtime($base_path . $toast_path) : '1.0.0'),
            true
        );

        // Error Handler JS - Depends on jQuery for AJAX interception
        $error_path = $asset_path($js_base, 'bbai-error-handler', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-error-handler',
            $base_url . $error_path,
            ['jquery', 'wp-i18n'],
            $asset_version($error_path, file_exists($base_path . $error_path) ? filemtime($base_path . $error_path) : '1.0.0'),
            true
        );

        // Onboarding JS - Load on all pages (including dedicated onboarding page)
        $onboard_path = $asset_path($js_base, 'bbai-onboarding', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-onboarding',
            $base_url . $onboard_path,
            ['jquery', 'bbai-modal', 'wp-i18n'],
            $asset_version($onboard_path, file_exists($base_path . $onboard_path) ? filemtime($base_path . $onboard_path) : '1.0.0'),
            true
        );

        // Social Proof JS - Depends on jQuery for carousel functionality
        $social_path = $asset_path($js_base, 'bbai-social-proof', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-social-proof',
            $base_url . $social_path,
            ['jquery'],
            $asset_version($social_path, file_exists($base_path . $social_path) ? filemtime($base_path . $social_path) : '1.0.0'),
            true
        );

        // Celebrations JS - Depends on toast notifications
        $celeb_path = $asset_path($js_base, 'bbai-celebrations', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-celebrations',
            $base_url . $celeb_path,
            ['jquery', 'bbai-toast'],
            $asset_version($celeb_path, file_exists($base_path . $celeb_path) ? filemtime($base_path . $celeb_path) : '1.0.0'),
            true
        );

        // Context-Aware Upgrades JS - Depends on toast notifications
        $context_path = $asset_path($js_base, 'bbai-context-upgrades', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-context-upgrades',
            $base_url . $context_path,
            ['jquery', 'bbai-toast'],
            $asset_version($context_path, file_exists($base_path . $context_path) ? filemtime($base_path . $context_path) : '1.0.0'),
            true
        );

        // Analytics JS - Depends on jQuery for AJAX and DOM manipulation
        $analytics_path = $asset_path($js_base, 'bbai-analytics', $use_debug_assets, 'js');
        wp_enqueue_script(
            'bbai-analytics',
            $base_url . $analytics_path,
            ['jquery', 'wp-i18n'],
            $asset_version($analytics_path, file_exists($base_path . $analytics_path) ? filemtime($base_path . $analytics_path) : '1.0.0'),
            true
        );

        // Copy & Export JS - Depends on jQuery for DOM manipulation
        $copy_path = $asset_path($js_base, 'bbai-copy-export', $use_debug_assets, 'js');
	        wp_enqueue_script(
	            'bbai-copy-export',
	            $base_url . $copy_path,
	            ['jquery', 'wp-i18n'],
	            $asset_version($copy_path, file_exists($base_path . $copy_path) ? filemtime($base_path . $copy_path) : '1.0.0'),
	            true
	        );

        // Dashboard JS
        $stats_data = $this->get_media_stats();
        $usage_data = Usage_Tracker::get_stats_display();

        // Monthly reset insight modal — detect if a new billing period started.
        $reset_modal_data = null;
        if ( $this->api_client->is_authenticated() && Usage_Tracker::detect_reset() ) {
            $reset_modal_data = Usage_Tracker::get_reset_modal_data();
            // Store current usage for next month's comparison.
            Usage_Tracker::store_previous_month_data(
                absint( $usage_data['used'] ),
                absint( $usage_data['limit'] )
            );
        } elseif ( $this->api_client->is_authenticated() && absint( $usage_data['used'] ) > 0 ) {
            // Keep stored usage up to date for next month's reset comparison.
            Usage_Tracker::store_previous_month_data(
                absint( $usage_data['used'] ),
                absint( $usage_data['limit'] )
            );
        }

        wp_enqueue_script(
            'bbai-dashboard',
            $base_url . $js_file,
            ['jquery', 'wp-api-fetch', 'wp-i18n'],
            $asset_version($js_file, '3.0.0'),
            true
        );

        // Upgrade modal JS
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

        // Debug JS
        wp_enqueue_script(
            'bbai-debug',
            $base_url . $asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'),
            ['jquery'],
            $asset_version($asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'), '1.0.0'),
            true
        );

        // Admin JS (for Generate Missing and Re-optimize All buttons)
        $admin_file = $asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js');
        $admin_version = $asset_version($admin_file, '3.0.0');
        wp_enqueue_script(
            'bbai-admin',
            $base_url . $admin_file,
            ['jquery', 'bbai-dashboard'],
            $admin_version,
            true
        );

        // Localize scripts (BBAI_DASH is shared by both bbai-dashboard and bbai-admin)
        $this->localize_dashboard_scripts($stats_data, $usage_data, $checkout_prices, $l10n_common);
    }

    /**
     * Localize dashboard scripts with data
     *
     * @param array $stats_data     Media stats
     * @param array $usage_data     Usage stats
     * @param array $checkout_prices Checkout prices
     * @param array $l10n_common    Common L10n strings
     */
    private function localize_dashboard_scripts(array $stats_data, array $usage_data, array $checkout_prices, array $l10n_common): void {
        // Debug script
        wp_localize_script('bbai-debug', 'BBAI_DEBUG', [
            'restLogs' => esc_url_raw(rest_url('bbai/v1/logs')),
            'restClear' => esc_url_raw(rest_url('bbai/v1/logs/clear')),
            'nonce' => wp_create_nonce('wp_rest'),
            'initial' => class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log') ? Debug_Log::get_logs([
                'per_page' => 10,
                'page' => 1,
            ]) : [
                'logs' => [],
                'pagination' => ['page' => 1, 'per_page' => 10, 'total_pages' => 1, 'total_items' => 0],
                'stats' => ['total' => 0, 'warnings' => 0, 'errors' => 0, 'last_event' => null, 'last_api' => null],
            ],
            'strings' => [
                'noLogs' => __('No logs recorded yet.', 'beepbeep-ai-alt-text-generator'),
                'contextTitle' => __('Log Context', 'beepbeep-ai-alt-text-generator'),
                'contextHide' => __('Hide Context', 'beepbeep-ai-alt-text-generator'),
                'clearConfirm' => __('This will permanently delete all debug logs. Continue?', 'beepbeep-ai-alt-text-generator'),
                'errorGeneric' => __('Unable to load debug logs. Please try again.', 'beepbeep-ai-alt-text-generator'),
                'emptyContext' => __('No additional context was provided for this entry.', 'beepbeep-ai-alt-text-generator'),
                'cleared' => __('Logs cleared successfully.', 'beepbeep-ai-alt-text-generator'),
            ],
        ]);

        // Dashboard script (shared with bbai-admin)
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
            'resetModal'  => $reset_modal_data,
        ];
        
        wp_localize_script('bbai-dashboard', 'BBAI_DASH', $bbai_dash_data);
        
        // Also localize for bbai-admin (it depends on bbai-dashboard, but this ensures it's available)
        wp_localize_script('bbai-admin', 'BBAI_DASH', $bbai_dash_data);

        // AJAX vars
        $options = get_option(self::OPTION_KEY, []);
        $api_url = 'https://alttext-ai-backend.onrender.com';

        wp_localize_script('bbai-dashboard', 'bbai_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('beepbeepai_nonce'),
            'api_url' => $api_url,
            'is_authenticated' => $this->api_client->is_authenticated(),
            'user_data' => $this->api_client->get_user_data(),
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
        $base_path = BBAI_PLUGIN_DIR;
        $base_url  = BBAI_PLUGIN_URL;

        $is_bbai_page = $this->is_bbai_admin_page($hook);

        // Load on Media Library, attachment edit, and BeepBeep AI screens
        if (in_array($hook, ['upload.php', 'post.php', 'post-new.php'], true) || $is_bbai_page) {
            $this->enqueue_media_library_assets($hook, $base_url, $base_path);
        }

        // Load dashboard assets on BBAI pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $current_page = $page_input;
        if ($is_bbai_page || (!empty($current_page) && strpos($current_page, 'bbai') === 0)) {
            $this->enqueue_dashboard_assets($base_url, $base_path);
        }
    }
}
