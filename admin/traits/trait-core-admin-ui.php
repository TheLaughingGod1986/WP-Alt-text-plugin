<?php
/**
 * Core Admin UI Trait
 * Handles admin menu registration, settings page rendering, onboarding flow, and checkout redirect.
 *
 * @package BeepBeep_AI
 * @since 8.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Admin_UI {

    public function add_settings_page() {
        $cap = current_user_can(self::CAPABILITY) ? self::CAPABILITY : 'manage_options';
        
        // Check authentication status (same logic as render_settings_page)
        try {
            $bbai_is_authenticated = $this->api_client->is_authenticated();
            $bbai_has_license = $this->api_client->has_active_license();
        } catch (\Exception $e) {
            $bbai_is_authenticated = false;
            $bbai_has_license = false;
        } catch (\Error $e) {
            $bbai_is_authenticated = false;
            $bbai_has_license = false;
        }

        // Also check stored credentials
        $bbai_stored_token = get_option('beepbeepai_jwt_token', '');
        $bbai_has_stored_token = !empty($bbai_stored_token);

        $bbai_stored_license = '';
        try {
            $bbai_stored_license = $this->api_client->get_license_key();
        } catch (\Exception $e) {
            $bbai_stored_license = '';
        } catch (\Error $e) {
            $bbai_stored_license = '';
        }
        $bbai_has_stored_license = !empty($bbai_stored_license);
        
        // Determine if user is registered (authenticated/licensed).
        // We still show the top-level menu for non-authenticated users so they can access onboarding/login.
        $bbai_has_registered_user = $bbai_is_authenticated || $bbai_has_license || $bbai_has_stored_token || $bbai_has_stored_license;

        // Top-level menu uses the brand name; the first submenu is "Dashboard".
        add_menu_page(
            __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
            $cap,
            self::MENU_SLUG_DASHBOARD,
            [$this, 'render_settings_page'],
            'dashicons-format-image',
            30
        );

        // Sidebar submenus are visible only for authenticated/licensed users.
        if ($bbai_has_registered_user) {
            // Explicit first submenu replaces the auto-generated duplicate.
            // Order matches the top header nav.
            add_submenu_page(
                self::MENU_SLUG_DASHBOARD,
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_DASHBOARD,
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                self::MENU_SLUG_DASHBOARD,
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_LIBRARY,
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Analytics', 'beepbeep-ai-alt-text-generator'),
                __('Analytics', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-analytics',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Usage', 'beepbeep-ai-alt-text-generator'),
                __('Usage', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-credit-usage',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('Settings', 'beepbeep-ai-alt-text-generator'),
                __('Settings', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-settings',
                [$this, 'render_settings_page']
            );
        } else {
            // Keep trial link destinations routable without exposing sidebar navigation.
            add_submenu_page(
                '',
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_LIBRARY,
                [$this, 'render_settings_page']
            );

            // Remove the auto-generated duplicate submenu under the top-level menu.
            remove_submenu_page(self::MENU_SLUG_DASHBOARD, self::MENU_SLUG_DASHBOARD);
        }

        // Keep utility routes addressable without exposing them in the primary submenu stack.
        add_submenu_page(
            '',
            __('Help', 'beepbeep-ai-alt-text-generator'),
            __('Help', 'beepbeep-ai-alt-text-generator'),
            $cap,
            'bbai-guide',
            [$this, 'render_settings_page']
        );

        if ($this->can_show_debug_logs_tab()) {
            add_submenu_page(
                '',
                __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-debug',
                [$this, 'render_settings_page']
            );
        }

	        // Prevent PHP 8.1+ strip_tags() null deprecation on hidden pages.
	        add_action('current_screen', static function () {
	            global $title;
	            if ( ! isset( $title ) || $title === null ) {
	                $title = '';
	            }
	        });

	        // Hidden checkout redirect page
	        add_submenu_page(
	            '', // No parent = hidden from menu (avoid PHP 8.1+ deprecations in plugin_basename()).
	            'Checkout',
	            'Checkout',
	            $cap,
	            'bbai-checkout',
	            [$this, 'handle_checkout_redirect']
	        );
	        
	        // Hidden onboarding/guide page (accessible but not shown in menu)
	        add_submenu_page(
	            '', // No parent = hidden from menu
                __('Getting Started', 'beepbeep-ai-alt-text-generator'),
                __('Getting Started', 'beepbeep-ai-alt-text-generator'),
                $cap,
                self::MENU_SLUG_ONBOARDING,
                [$this, 'render_bbai_onboarding_step2']
        );
	    }

	    public function handle_checkout_redirect() {
	        // Verify nonce for CSRF protection (first).
	        $action = 'bbai_checkout_redirect';	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), $action ) ) {
	            wp_die(esc_html__('Security check failed.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->api_client->is_authenticated()) {
	            wp_die(esc_html__('Please sign in first to upgrade.', 'beepbeep-ai-alt-text-generator'));
	        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
        $price_id = isset($_GET['price_id']) ? sanitize_text_field(wp_unslash($_GET['price_id'])) : '';
        if (empty($price_id)) {
            wp_die(esc_html__('Invalid checkout request.', 'beepbeep-ai-alt-text-generator'));
        }

        $success_url = admin_url('admin.php?page=bbai&checkout=success');
        $cancel_url = admin_url('admin.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_message = is_string($error_message) ? sanitize_text_field($error_message) : '';
            wp_die(esc_html(sprintf(
                /* translators: 1: error message */
                __('Checkout error: %s', 'beepbeep-ai-alt-text-generator'),
                $error_message
            )));
        }

        if (!empty($result['url'])) {
            wp_safe_redirect( $result['url'] );
            exit;
        }

        wp_die(esc_html__('Failed to create checkout session.', 'beepbeep-ai-alt-text-generator'));
    }

    public function maybe_redirect_to_onboarding(): void {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        if (function_exists('is_network_admin') && is_network_admin()) {
            return;
        }

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!$this->user_can_manage()) {
            return;
        }

	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	        $bbai_current_page = $bbai_page_input;
	        $is_onboarding_page = ($bbai_current_page === 'bbai-onboarding');
        $is_plugin_page = (!empty($bbai_current_page) && strpos($bbai_current_page, 'bbai') === 0);

        // Never hijack unrelated wp-admin pages. Keep onboarding redirect scoped to plugin screens.
        if (!$is_plugin_page) {
            return;
        }

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Auth_State') || !class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            return;
        }

        $bbai_auth = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
        if (empty($bbai_auth['has_registered_user'])) {
            return;
        }

        $completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed();

        if ($completed) {
            // Allow onboarding pages to be viewed even if completed (user may want to revisit the guide)
            // and don't force redirects once setup is done.
            return;
        }

        // Redirect only from the dashboard entrypoint, not every wp-admin page.
        if (!$is_onboarding_page && $bbai_current_page === self::MENU_SLUG_DASHBOARD) {
            wp_safe_redirect(admin_url('admin.php?page=bbai-onboarding'));
            exit;
        }
    }

    public function register_settings() {
        register_setting('bbai_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => function($input){
                $existing = get_option(self::OPTION_KEY, []);
                $input = is_array($input) ? $input : [];
                $out = [];
                // ALWAYS force production API URL - no user input allowed
                $production_url = 'https://alttext-ai-backend.onrender.com';
                $out['api_url'] = $production_url;
                $model = isset($input['model']) ? (string)$input['model'] : 'gpt-4o-mini';
                $out['model'] = $model ? sanitize_text_field($model) : 'gpt-4o-mini';
                $out['max_words']        = max(4, intval($input['max_words'] ?? 16));
                $lang_input_input = isset($input['language']) ? (string)$input['language'] : 'en-GB';
                $lang_input = $lang_input_input ? sanitize_text_field($lang_input_input) : 'en-GB';
                $custom_input_input = isset($input['language_custom']) ? (string)$input['language_custom'] : '';
                $custom_input = $custom_input_input ? sanitize_text_field($custom_input_input) : '';
                if ($lang_input === 'custom'){
                    $out['language'] = $custom_input ?: 'en-GB';
                    $out['language_custom'] = $custom_input;
                } else {
                    $out['language'] = $lang_input ?: 'en-GB';
                    $out['language_custom'] = '';
                }
                $out['enable_on_upload'] = !empty($input['enable_on_upload']);
                $tone = isset($input['tone']) ? (string)$input['tone'] : 'professional, accessible';
                $out['tone'] = $tone ? sanitize_text_field($tone) : 'professional, accessible';
                $out['force_overwrite']  = !empty($input['force_overwrite']);
                $out['token_limit']      = max(0, intval($input['token_limit'] ?? 0));
                if ($out['token_limit'] === 0){
                    $out['token_alert_sent'] = false;
                } elseif (intval($existing['token_limit'] ?? 0) !== $out['token_limit']){
                    $out['token_alert_sent'] = false;
                } else {
                    $out['token_alert_sent'] = !empty($existing['token_alert_sent']);
                }
                $out['dry_run'] = !empty($input['dry_run']);
                $custom_prompt = isset($input['custom_prompt']) ? (string)$input['custom_prompt'] : '';
                $out['custom_prompt'] = $custom_prompt ? wp_kses_post($custom_prompt) : '';
                $notify_input = $input['notify_email'] ?? ($existing['notify_email'] ?? get_option('admin_email'));
                $notify = is_string($notify_input) ? sanitize_text_field($notify_input) : '';
                $out['notify_email'] = $notify && is_email($notify) ? $notify : ($existing['notify_email'] ?? get_option('admin_email'));
                $out['usage']            = $existing['usage'] ?? $this->default_usage();

                return $out;
            }
        ]);
    }

    /**
     * Render Step 1 of onboarding - Welcome page
     */
    private function render_bbai_onboarding_step1() {
        $dashboard_url = admin_url('admin.php?page=bbai');
        $step2_url = admin_url('admin.php?page=bbai-onboarding&step=2');
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient-s1)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient-s1" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('AI-Powered Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('BeepBeep AI will scan your WordPress media library and find images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 1 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Scan', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('BeepBeep AI will scan your WordPress media library and find images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>

                        <div class="bbai-onboarding-features bbai-mt-4">
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Step 1: Scan all uploads', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Find images in your media library that need alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></strong>
                                    <p><?php esc_html_e('Create clear alt text for each image missing it.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Step 3: Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Review the generated results before publishing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bbai-btn-group bbai-mt-6">
                            <a href="<?php echo esc_url($step2_url); ?>" class="bbai-btn bbai-btn-primary">
                                <?php echo esc_html(bbai_copy_cta_scan_media_library()); ?>
                            </a>
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-onboarding-action="skip">
                                <?php esc_html_e('Skip setup', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                        <div class="bbai-onboarding-status" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="bbai-grid bbai-grid-3 bbai-mb-6">
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon bbai-onboarding-step-icon--active" aria-hidden="true">
                                <span class="bbai-step-number">1</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Scan your media library', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">2</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">3</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_bbai_onboarding_step2() {
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
        }

	        // Route based on step query param (default to step 1)
	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $step = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 1;

        if ($step === 1) {
            $this->render_bbai_onboarding_step1();
            return;
        }

        if ($step === 3) {
            $this->render_bbai_onboarding_step3();
            return;
        }

        // Step 2 continues below

        $dashboard_url = admin_url('admin.php?page=bbai');
        $upgrade_url = class_exists('\BeepBeepAI\AltTextGenerator\Usage_Tracker')
            ? \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_upgrade_url()
            : $dashboard_url;
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php
                        // Only show Dashboard tab if onboarding is completed
                        if (\BeepBeepAI\AltTextGenerator\Onboarding::is_completed()) :
                        ?>
                            <a class="bbai-nav-link" href="<?php echo esc_url($dashboard_url); ?>">
                                <?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Scan your media library and generate alt text for images that are missing it.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 2 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Generate', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('BeepBeep AI will scan your WordPress media library and find images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>
                        <div class="bbai-btn-group bbai-mt-4">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-bbai-onboarding-action="start-scan">
                                <?php echo esc_html(bbai_copy_cta_scan_media_library()); ?>
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-onboarding-action="skip">
                                <?php esc_html_e('Skip setup', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                        <div class="bbai-onboarding-scan-meta bbai-mt-4" data-bbai-scan-meta hidden>
                            <p class="bbai-onboarding-scan-count" data-bbai-scan-count><?php esc_html_e('Scanning 0 images', 'beepbeep-ai-alt-text-generator'); ?></p>
                            <p class="bbai-onboarding-scan-time" data-bbai-scan-time><?php esc_html_e('Estimated time: ~0 seconds', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <div class="bbai-onboarding-scan-progress bbai-mt-3" data-bbai-scan-progress hidden>
                            <div class="bbai-onboarding-scan-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e('Scan progress', 'beepbeep-ai-alt-text-generator'); ?>">
                                <span class="bbai-onboarding-scan-progress-fill" data-bbai-scan-progress-fill style="width: 0%;"></span>
                            </div>
                            <p class="bbai-onboarding-scan-progress-text" data-bbai-scan-progress-text><?php esc_html_e('Preparing scan...', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <p class="bbai-onboarding-reassurance"><?php esc_html_e('Only images without alt text are processed. Review everything before publishing.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <p class="bbai-onboarding-helper"><?php esc_html_e('Initial scan processes up to 500 images. You can adjust this later.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-status" role="status" aria-live="polite"></div>
                    </div>
                </div>

                <div class="bbai-mb-6">
                    <h2 class="bbai-section-title"><?php esc_html_e('How it works', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <div class="bbai-grid bbai-grid-3 bbai-gap-4">
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="2" />
                                        <path d="M20 20l-4.2-4.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php esc_html_e('Scan your library', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Find images missing alt text in your library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <path d="M12 3l1.6 3.6L17 8l-3.4 1.4L12 13l-1.6-3.6L7 8l3.4-1.4L12 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php echo esc_html(bbai_copy_cta_generate_missing_images()); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Generate clear, SEO-ready alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                            <div class="bbai-card-body">
                                <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" role="presentation" focusable="false">
                                        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2" />
                                        <path d="M8.5 12.5l2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <h3 class="bbai-card-title"><?php esc_html_e('Review and publish', 'beepbeep-ai-alt-text-generator'); ?></h3>
                                <p class="bbai-onboarding-step-text"><?php esc_html_e('Review and publish when you are ready.', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--compact bbai-onboarding-upsell">
                    <div class="bbai-card-body bbai-onboarding-upsell-body">
                        <div>
                            <h3 class="bbai-card-title"><?php esc_html_e('Want faster processing?', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-card-subtitle"><?php esc_html_e('Upgrade to Growth for priority queue and bulk optimisation across your full media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                            <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Include upgrade modal for "View plans & pricing" button
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        include BEEPBEEP_AI_PLUGIN_DIR . 'templates/upgrade-modal.php';
    }

    /**
     * Render Onboarding Step 3 - Review ready screen
     * Shown after scan started or skip, marks onboarding completed on view.
     */
    public function render_bbai_onboarding_step3() {
        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
        }

        // Note: Onboarding completion is now triggered via AJAX when queue finishes
        // (pending + processing === 0). This prevents premature completion while work is running.
        // The JS calls bbai_complete_onboarding when stats show queue is empty.

        $dashboard_url = admin_url('admin.php?page=bbai');
        $library_url = admin_url('admin.php?page=' . self::MENU_SLUG_LIBRARY);
        $bbai_is_authenticated = bbai_is_authenticated();

        // Mark onboarding complete as soon as Step 3 is reached to prevent redirect loops.
        if ($bbai_is_authenticated && class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            \BeepBeepAI\AltTextGenerator\Onboarding::mark_completed();
            \BeepBeepAI\AltTextGenerator\Onboarding::update_last_seen();
        }
        ?>
        <div class="wrap bbai-wrap bbai-modern bbai-onboarding bbai-onboarding-step3">
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <a class="bbai-logo" href="<?php echo esc_url($dashboard_url); ?>">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon" aria-hidden="true">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient-s3)"/>
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient-s3" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </a>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php
                        // Only show Dashboard tab if onboarding is completed
                        if (\BeepBeepAI\AltTextGenerator\Onboarding::is_completed()) :
                        ?>
                            <a class="bbai-nav-link" href="<?php echo esc_url($dashboard_url); ?>">
                                <?php esc_html_e('Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="bbai-nav-link active" href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>">
                            <?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                </div>
            </div>
            <div class="bbai-container">
                <div class="bbai-page-header bbai-mb-6">
                    <div class="bbai-page-header-content">
                        <h1 class="bbai-page-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Alt text is generating in the background. Review your first results as they arrive.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 3 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Setup complete', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$bbai_is_authenticated) : ?>
                <!-- Unauthenticated state: Show sign-in CTA -->
                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('Sign in to start generating', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('Sign in to start generating and reviewing alt text.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>
                        <div class="bbai-btn-group bbai-mt-4">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <?php esc_html_e('Sign in', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                                <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php else : ?>
                <!-- Authenticated state: Show queue stats and CTAs -->
                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('Review ALT text', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('Your images are being processed. Check the stats below and start reviewing when ready.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>

                        <!-- KPI Stats Row -->
                        <div class="bbai-onboarding-kpi-row bbai-mt-4" data-bbai-step3-stats>
                            <div class="bbai-onboarding-kpi">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('In queue', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="queued">
                                    <span class="bbai-onboarding-kpi-loading">&hellip;</span>
                                </span>
                            </div>
                            <div class="bbai-onboarding-kpi">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('Processed', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="processed">
                                    <span class="bbai-onboarding-kpi-loading">&hellip;</span>
                                </span>
                            </div>
                            <div class="bbai-onboarding-kpi bbai-onboarding-kpi--errors" data-stat-errors-wrapper style="display: none;">
                                <span class="bbai-onboarding-kpi-label"><?php esc_html_e('Errors', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-onboarding-kpi-value" data-stat="errors">0</span>
                            </div>
                        </div>

                        <div class="bbai-btn-group bbai-mt-4">
                            <a href="<?php echo esc_url($library_url); ?>" class="bbai-btn bbai-btn-primary">
                                <?php esc_html_e('Open ALT Library', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                            <a href="<?php echo esc_url($dashboard_url); ?>" class="bbai-btn bbai-btn-secondary">
                                <?php esc_html_e('Go to Dashboard', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bbai-card bbai-card--compact bbai-mb-6">
                    <div class="bbai-card-body">
                        <h3 class="bbai-card-title"><?php esc_html_e('Example result', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <div class="bbai-onboarding-result-example">
                            <div class="bbai-onboarding-result-example__item">
                                <p class="bbai-onboarding-result-example__label"><?php esc_html_e('Before', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <p class="bbai-onboarding-result-example__filename">image.jpg</p>
                                <p class="bbai-onboarding-result-example__alt"><?php esc_html_e('ALT: empty', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                            <div class="bbai-onboarding-result-example__item bbai-onboarding-result-example__item--after">
                                <p class="bbai-onboarding-result-example__label"><?php esc_html_e('After', 'beepbeep-ai-alt-text-generator'); ?></p>
                                <p class="bbai-onboarding-result-example__generated"><?php esc_html_e('Generated alt text example', 'beepbeep-ai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- What happens next card -->
                <div class="bbai-card bbai-card--compact bbai-mb-6">
                    <div class="bbai-card-body">
                        <h3 class="bbai-card-title"><?php esc_html_e('What happens next', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        <ul class="bbai-onboarding-next-steps">
                            <li><?php esc_html_e('We generate drafts for images missing alt text.', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('You review and edit anything before publishing.', 'beepbeep-ai-alt-text-generator'); ?></li>
                            <li><?php esc_html_e('You can re-run scans anytime from the Dashboard.', 'beepbeep-ai-alt-text-generator'); ?></li>
                        </ul>
                    </div>
                </div>

                <!-- Upsell banner -->
                <div class="bbai-card bbai-card--compact bbai-onboarding-upsell">
                    <div class="bbai-card-body bbai-onboarding-upsell-body">
                        <div>
                            <h3 class="bbai-card-title"><?php esc_html_e('Want faster processing?', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            <p class="bbai-card-subtitle"><?php esc_html_e('Upgrade to Growth for priority queue and bulk optimisation across your full media library.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                        <button type="button" class="bbai-btn bbai-btn-outline-primary" data-action="show-upgrade-modal" data-upgrade-trigger="true">
                            <?php esc_html_e('View plans & pricing', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Include upgrade modal for "View plans & pricing" button
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        include BEEPBEEP_AI_PLUGIN_DIR . 'templates/upgrade-modal.php';
    }

    public function render_settings_page() {
        if (!$this->user_can_manage()) return;

        // Allocate free credits on first dashboard view so usage displays correctly
        Usage_Tracker::allocate_free_credits_if_needed();

        $opts  = get_option(self::OPTION_KEY, []);
        $bbai_stats = $this->get_media_stats();
        $nonce = wp_create_nonce(self::NONCE_KEY);
        
        // Check if there's a registered user (authenticated or has active license)
        // Use try-catch to prevent errors from breaking the page
	        try {
	            $bbai_is_authenticated = $this->api_client->is_authenticated();
	            $bbai_has_license = $this->api_client->has_active_license();
		        } catch (\Exception $e) {
		            // If authentication check fails, default to showing limited tabs.
		            \bbai_debug_log(
		                'Authentication check failed in render_settings_page',
		                [
		                    'error' => $e->getMessage(),
		                ]
		            );
		            $bbai_is_authenticated = false;
		            $bbai_has_license = false;
		        } catch (\Error $e) {
		            // Catch fatal errors too.
		            \bbai_debug_log(
		                'Authentication fatal error in render_settings_page',
		                [
		                    'error' => $e->getMessage(),
		                ]
		            );
		            $bbai_is_authenticated = false;
		            $bbai_has_license = false;
		        }
        
        // Also check if user has a token stored in options (indicates they've logged in before)
        // Token is stored in beepbeepai_jwt_token option (not in beepbeepai_settings)
        $bbai_stored_token = get_option('beepbeepai_jwt_token', '');
        $bbai_has_stored_token = !empty($bbai_stored_token);
        
        // Check for license key using the API client method (handles both new and legacy storage)
        $bbai_stored_license = '';
        try {
            $bbai_stored_license = $this->api_client->get_license_key();
        } catch (\Exception $e) {
            $bbai_stored_license = '';
        } catch (\Error $e) {
            $bbai_stored_license = '';
        }
        $bbai_has_stored_license = !empty($bbai_stored_license);
        
        // Update authentication flags to include stored credentials
        // This ensures tabs show even if current API validation fails
        $bbai_is_authenticated = $bbai_is_authenticated || $bbai_has_stored_token;
        $bbai_has_license = $bbai_has_license || $bbai_has_stored_license;
        
        // Consider user registered if authenticated, has license, or has stored credentials
        // This ensures tabs show even if current auth check fails
        $bbai_has_registered_user = $bbai_is_authenticated || $bbai_has_license || $bbai_has_stored_token || $bbai_has_stored_license;
	        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	        $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	        $bbai_page_slug = $bbai_page_input;
        
        // Primary nav is limited to the product workflow. Utility routes stay accessible but hidden.
        $bbai_tabs = [];
        $bbai_active_nav_tab = '';
        $bbai_help_is_active = false;
        $bbai_settings_section = 'general';
        
        // Non-registered users: show logged-out dashboard only (no header nav tabs).
        if (!$bbai_has_registered_user) {
            $bbai_tabs = [];
            $bbai_tab = 'dashboard';

            $bbai_is_pro_for_admin = false;
            $bbai_is_agency_for_admin = false;
            $bbai_can_show_debug_tab = false;
        } else {
            // Determine if agency license
            // Check API first, then include stored credentials
            $bbai_has_license_api = $this->api_client->has_active_license();
            $bbai_has_license = $bbai_has_license_api || $bbai_has_stored_license;
            $bbai_license_data = $this->api_client->get_license_data();
            $bbai_plan_slug = 'free'; // Default to free
            
            // If using license, check license plan
            if ($bbai_has_license && $bbai_license_data && isset($bbai_license_data['organization'])) {
                $bbai_license_plan = strtolower($bbai_license_data['organization']['plan'] ?? 'free');
                if ($bbai_license_plan !== 'free') {
                    $bbai_plan_slug = $bbai_license_plan;
                }
            } elseif ($bbai_is_authenticated) {
                // For authenticated users without license, try to get plan from usage stats
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $bbai_usage_stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(false);
                if (isset($bbai_usage_stats['plan']) && $bbai_usage_stats['plan'] !== 'free') {
                    $bbai_plan_slug = $bbai_usage_stats['plan'];
                }
            }
            
            $bbai_is_agency = ($bbai_plan_slug === 'agency');
            $bbai_is_pro = ($bbai_plan_slug === 'pro' || $bbai_plan_slug === 'agency');

            // Visible primary navigation only includes the main product workflow areas.
            $bbai_tabs = [
                'dashboard' => __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                'library'   => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                'analytics' => __('Analytics', 'beepbeep-ai-alt-text-generator'),
                'usage'     => __('Usage', 'beepbeep-ai-alt-text-generator'),
                'settings'  => __('Settings', 'beepbeep-ai-alt-text-generator'),
            ];

            $bbai_can_show_debug_tab = $this->can_show_debug_logs_tab();
            $bbai_allowed_tabs = $bbai_tabs + [
                'help' => __('Help', 'beepbeep-ai-alt-text-generator'),
            ];

            if ($bbai_can_show_debug_tab) {
                $bbai_allowed_tabs['debug'] = __('Debug Logs', 'beepbeep-ai-alt-text-generator');
            }

            // Keep internal/admin routes available without promoting them into primary nav.
            if ($bbai_is_pro) {
                $bbai_allowed_tabs['admin'] = __('Admin', 'beepbeep-ai-alt-text-generator');
            }

            if ($bbai_is_agency) {
                $bbai_allowed_tabs['agency-overview'] = __('Agency Overview', 'beepbeep-ai-alt-text-generator');
            }

            // Normalize legacy page slugs and tab params into the simplified nav model.
            $bbai_page_to_tab = [
                'bbai'              => 'dashboard',
                'bbai-library'      => 'library',
                'bbai-analytics'    => 'analytics',
                'bbai-credit-usage' => 'usage',
                'bbai-guide'        => 'help',
                'bbai-settings'     => 'settings',
                'bbai-debug'        => 'debug',
                'bbai-agency-overview' => 'agency-overview',
            ];
            $bbai_tab_aliases = [
                'credit-usage' => 'usage',
                'guide'        => 'help',
            ];
            
	            // Determine current tab from URL
	            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	            $bbai_page_input = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
	            $bbai_current_page = $bbai_page_input ?: 'bbai';
	            $tab_from_page = $bbai_page_to_tab[$bbai_current_page] ?? 'dashboard';
                $tab_from_page = $bbai_tab_aliases[$tab_from_page] ?? $tab_from_page;
	            
	            // Use tab from URL parameter if provided, otherwise use page slug mapping
	            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
	            $bbai_tab_input = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
	            $bbai_requested_tab = $bbai_tab_input !== '' ? $bbai_tab_input : $tab_from_page;
                $bbai_requested_tab = $bbai_tab_aliases[$bbai_requested_tab] ?? $bbai_requested_tab;

                if ('debug' === $bbai_requested_tab && $bbai_can_show_debug_tab) {
                    $bbai_tab = 'settings';
                    $bbai_settings_section = 'debug';
                } else {
                    $bbai_tab = $bbai_requested_tab;
                }

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin page routing, not form processing.
                $bbai_section_input = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
                if ('settings' === $bbai_tab && 'debug' === $bbai_section_input && $bbai_can_show_debug_tab) {
                    $bbai_settings_section = 'debug';
                }

            // If trying to access restricted tabs, redirect to dashboard
            if (!isset($bbai_allowed_tabs) || !is_array($bbai_allowed_tabs) || !in_array($bbai_requested_tab, array_keys($bbai_allowed_tabs), true)) {
                $bbai_tab = 'dashboard';
                $bbai_settings_section = 'general';
            }

            $bbai_active_nav_map = [
                'dashboard'       => 'dashboard',
                'library'         => 'library',
                'analytics'       => 'analytics',
                'usage'           => 'usage',
                'settings'        => 'settings',
                'debug'           => 'settings',
                'admin'           => 'settings',
                'agency-overview' => 'settings',
            ];
            $bbai_active_nav_tab = $bbai_active_nav_map[$bbai_requested_tab] ?? ($bbai_active_nav_map[$bbai_tab] ?? '');
            $bbai_help_is_active = ('help' === $bbai_requested_tab);
            
            // Set variables for Admin tab access (used later in template)
            $bbai_is_pro_for_admin = $bbai_is_pro;
            $bbai_is_agency_for_admin = $bbai_is_agency;
        }
        $bbai_export_url = wp_nonce_url(admin_url('admin-post.php?action=beepbeepai_usage_export'), 'bbai_usage_export');
        $bbai_audit_rows = $bbai_stats['audit'] ?? [];
        $bbai_debug_bootstrap = $this->get_debug_bootstrap();
        ?>
        <div class="wrap bbai-wrap bbai-modern">
            <!-- Dark Header -->
            <div class="bbai-header">
                <div class="bbai-header-content">
                    <div class="bbai-logo">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="bbai-logo-icon">
                            <rect width="40" height="40" rx="10" fill="url(#logo-gradient)"/>
                            <!-- AI/Sparkle icon representing intelligence -->
                            <circle cx="20" cy="20" r="8" fill="white" opacity="0.15"/>
                            <path d="M20 12L20.8 15.2L24 16L20.8 16.8L20 20L19.2 16.8L16 16L19.2 15.2L20 12Z" fill="white"/>
                            <path d="M28 22L28.6 24.2L30.8 24.8L28.6 25.4L28 28L27.4 25.4L25.2 24.8L27.4 24.2L28 22Z" fill="white" opacity="0.8"/>
                            <path d="M12 26L12.4 27.4L13.8 27.8L12.4 28.2L12 30L11.6 28.2L10.2 27.8L11.6 27.4L12 26Z" fill="white" opacity="0.6"/>
                            <!-- Image frame representing image optimization -->
                            <rect x="14" y="18" width="12" height="8" rx="1" stroke="white" stroke-width="1.5" fill="none"/>
                            <defs>
                                <linearGradient id="logo-gradient" x1="0" y1="0" x2="40" y2="40">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#10b981"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="bbai-logo-content">
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <?php if ($bbai_has_registered_user) : ?>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <div class="bbai-nav__primary">
                        <?php
                        if (!isset($bbai_tabs) || !is_array($bbai_tabs)) {
                            $bbai_tabs = [];
                        }

                        $bbai_tab_to_page = [
                            'dashboard' => 'bbai',
                            'library'   => 'bbai-library',
                            'analytics' => 'bbai-analytics',
                            'usage'     => 'bbai-credit-usage',
                            'settings'  => 'bbai-settings',
                        ];

                        foreach ($bbai_tabs as $slug => $label) :
                            $page_slug = $bbai_tab_to_page[$slug] ?? 'bbai';
                            $url = admin_url('admin.php?page=' . $page_slug);
                            $active = (isset($bbai_active_nav_tab) && $bbai_active_nav_tab === $slug) ? ' active' : '';
                        ?>
                            <a href="<?php echo esc_url($url); ?>" class="bbai-nav-link<?php echo esc_attr($active); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-guide')); ?>" class="bbai-header-guide-link<?php echo !empty($bbai_help_is_active) ? ' active' : ''; ?>" title="<?php esc_attr_e('Help and troubleshooting', 'beepbeep-ai-alt-text-generator'); ?>"<?php echo !empty($bbai_help_is_active) ? ' aria-current="page"' : ''; ?>>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 11.5V11M8 9C8 7.5 9.5 7.5 9.5 6C9.5 5.17 8.83 4.5 8 4.5C7.17 4.5 6.5 5.17 6.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span class="bbai-header-guide-text"><?php esc_html_e('Help', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </a>
                    </nav>
                    <?php endif; ?>
                    <!-- Auth & Subscription Actions -->
                    <div class="bbai-header-actions">
                        <?php
                        // Use stored credentials check (same as tab rendering logic)
                        // This ensures header shows correct state even if API validation fails
                        $bbai_has_license_api = $this->api_client->has_active_license();
                        $is_authenticated_api = $this->api_client->is_authenticated();
                        $bbai_has_license = $bbai_has_license_api || $bbai_has_stored_license;
                        $bbai_is_authenticated = $is_authenticated_api || $bbai_has_stored_token;

                        if ($bbai_is_authenticated || $bbai_has_license) :
                            $bbai_usage_stats = Usage_Tracker::get_stats_display();
                            $bbai_account_summary = $bbai_is_authenticated ? $this->get_account_summary($bbai_usage_stats) : null;
                            $bbai_plan_slug  = $bbai_usage_stats['plan'] ?? 'free';
                            $plan_label = isset($bbai_usage_stats['plan_label']) ? (string)$bbai_usage_stats['plan_label'] : ucfirst($bbai_plan_slug);
                            $connected_email = isset($bbai_account_summary['email']) ? (string)$bbai_account_summary['email'] : '';
                            $billing_portal = Usage_Tracker::get_billing_portal_url();

                            // If license-only mode (no personal login), show license info
                            if ($bbai_has_license && !$bbai_is_authenticated) {
                                $bbai_license_data = $this->api_client->get_license_data();
                                $org_name = isset($bbai_license_data['organization']['name']) ? (string)$bbai_license_data['organization']['name'] : '';
                                $connected_email = $org_name ?: __('License Active', 'beepbeep-ai-alt-text-generator');
                            }
                        ?>
                            <!-- Compact Account Bar in Header -->
                            <div class="bbai-header-account-bar">
                                <span class="bbai-header-account-email"><?php echo esc_html(is_string($connected_email) ? $connected_email : __('Connected', 'beepbeep-ai-alt-text-generator')); ?></span>
                                <span class="bbai-header-plan-badge"><?php echo esc_html(is_string($plan_label) ? $plan_label : ucfirst($bbai_plan_slug ?? 'free')); ?></span>
                                <?php if ($bbai_plan_slug === 'free' && !$bbai_has_license && $bbai_tab !== 'dashboard') : ?>
                                    <button type="button" class="bbai-header-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php elseif (!empty($billing_portal) && $bbai_is_authenticated) : ?>
                                    <button type="button" class="bbai-header-manage-btn" data-action="open-billing-portal">
                                        <?php esc_html_e('Manage', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($bbai_is_authenticated || $bbai_has_license) : ?>
                                <button type="button" class="bbai-header-logout-btn" data-action="logout">
                                    <?php esc_html_e('Logout', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <?php
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing for login fallback URL.
                            $bbai_header_login_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'bbai';
                            if ('' === $bbai_header_login_page || 0 !== strpos($bbai_header_login_page, 'bbai')) {
                                $bbai_header_login_page = 'bbai';
                            }
                            $bbai_header_login_href = add_query_arg(
                                'bbai_open_auth',
                                '1',
                                admin_url('admin.php?page=' . $bbai_header_login_page)
                            );
                            ?>
                            <a
                                href="<?php echo esc_url($bbai_header_login_href); ?>"
                                class="bbai-header-login-btn"
                                role="button"
                                data-action="show-auth-modal"
                                data-auth-tab="login"
                            >
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false">
                                    <path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span><?php esc_html_e('Login', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </a>
                        <?php endif; ?>
                </div>
                </div>
            </div>
            
            <!-- Main Content Container - uniform width across all tabs -->
            <div class="bbai-container bbai-content-shell<?php echo ( isset( $bbai_tab ) && $bbai_tab === 'dashboard' ) ? ' bbai-dashboard-shell' : ''; ?>">

            <?php
            // Ensure usage stats for banner when on dashboard.
            if (
                ( $bbai_tab === 'dashboard' || $bbai_tab === 'library' || $bbai_tab === 'analytics' || $bbai_tab === 'usage' ) &&
                ( $bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user ) &&
                ( ! isset( $bbai_usage_stats ) || ! is_array( $bbai_usage_stats ) )
            ) {
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $bbai_usage_stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display();
            }
            // Usage limit banner - dashboard only, when monthly limit reached.
            $bbai_banner_limit_reached = false;
            if (
                $bbai_tab === 'dashboard' &&
                isset( $bbai_usage_stats ) &&
                is_array( $bbai_usage_stats ) &&
                ( $bbai_is_authenticated || $bbai_has_license || $bbai_has_registered_user )
            ) {
                $bbai_banner_used = max( 0, (int) ( $bbai_usage_stats['used'] ?? 0 ) );
                $bbai_banner_limit = max( 1, (int) ( $bbai_usage_stats['limit'] ?? 50 ) );
                $bbai_banner_remaining = isset( $bbai_usage_stats['remaining'] )
                    ? max( 0, (int) $bbai_usage_stats['remaining'] )
                    : max( 0, $bbai_banner_limit - $bbai_banner_used );
                $bbai_banner_limit_reached = ( $bbai_banner_remaining <= 0 );
            }
            /* Banner is rendered inside dashboard-body.php when on dashboard tab */
            ?>

            <?php if ($bbai_tab === 'dashboard') : ?>
    <?php
    $bbai_dashboard_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-tab.php';
    bbai_render_layout_template(
        $bbai_dashboard_partial,
        get_defined_vars(),
        __('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'library' && ($bbai_is_authenticated || $bbai_has_license || !$bbai_has_registered_user)) : ?>
    <?php
    $bbai_library_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-tab.php';
    bbai_render_layout_template(
        $bbai_library_partial,
        get_defined_vars(),
        __('Library content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'help' || $bbai_page_slug === 'bbai-guide') : ?>
            <?php
            $bbai_guide_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/guide-tab.php';
            bbai_render_layout_template(
                $bbai_guide_partial,
                get_defined_vars(),
                __('Help content unavailable.', 'beepbeep-ai-alt-text-generator'),
                $this
            );
            ?>

<?php elseif ($bbai_tab === 'usage' && ($bbai_is_authenticated || $bbai_has_license)) : ?>
    <?php
    $bbai_credit_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/credit-usage-tab.php';
    bbai_render_layout_template(
        $bbai_credit_partial,
        get_defined_vars(),
        __('Usage content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>
<?php elseif ($bbai_tab === 'agency-overview' && $bbai_is_agency) : ?>
    <?php
    $bbai_agency_overview_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/agency-overview-tab.php';
    if (file_exists($bbai_agency_overview_partial)) {
        include $bbai_agency_overview_partial;
    } else {
        esc_html_e('Agency overview content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
<?php elseif ($bbai_tab === 'analytics' && ($bbai_is_authenticated || $bbai_has_license)) : ?>
    <?php
    // Ensure usage_stats is available for analytics tab
    if (!isset($bbai_usage_stats)) {
        $bbai_usage_stats = Usage_Tracker::get_stats_display();
    }
    $bbai_analytics_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/analytics-tab.php';
    bbai_render_layout_template(
        $bbai_analytics_partial,
        get_defined_vars(),
        __('Analytics content unavailable.', 'beepbeep-ai-alt-text-generator'),
        $this
    );
    ?>

<?php elseif ($bbai_tab === 'settings') : ?>
            <?php
            $bbai_settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
            bbai_render_layout_template(
                $bbai_settings_partial,
                get_defined_vars(),
                __('Settings content unavailable.', 'beepbeep-ai-alt-text-generator'),
                $this
            );
            ?>

            <?php elseif ($bbai_tab === 'admin' && $bbai_is_pro_for_admin) : ?>
            <!-- Admin Tab - Debug Logs and Settings for Pro and Agency -->
            <?php
            // Check if user is authenticated via API (JWT token or license) OR has admin session
            $api_authenticated = $this->api_client->is_authenticated();
            $has_active_license = $this->api_client->has_active_license();
            $admin_session_authenticated = $this->is_admin_authenticated();

            // Grant access if authenticated via any method
            $admin_authenticated = $api_authenticated || $has_active_license || $admin_session_authenticated;
            ?>
            <?php if (!$admin_authenticated) : ?>
                <!-- Admin Login Required -->
                <div class="bbai-admin-login">
                    <div class="bbai-admin-login-content">
                        <div class="bbai-admin-login-header">
                            <h2 class="bbai-admin-login-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="margin-right: 12px; vertical-align: middle;">
                                    <path d="M12 1L23 12L12 23L1 12L12 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="12" cy="12" r="3" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Access', 'beepbeep-ai-alt-text-generator'); ?>
                            </h2>
                            <p class="bbai-admin-login-subtitle">
                                <?php 
                                if ($bbai_is_agency_for_admin) {
                                    esc_html_e('Enter your agency credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    esc_html_e('Enter your pro credentials to access Debug Logs and Settings.', 'beepbeep-ai-alt-text-generator');
                                }
                                ?>
                            </p>
                        </div>
                        
                        <form id="bbai-admin-login-form" class="bbai-admin-login-form">
                            <div id="bbai-admin-login-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-email" class="bbai-admin-login-label">
                                    <?php esc_html_e('Email', 'beepbeep-ai-alt-text-generator'); ?>
                                </label>
                                <input type="email" 
                                       id="admin-login-email" 
                                       name="email" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('your-email@example.com', 'beepbeep-ai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-password" class="bbai-admin-login-label">
                                    <?php esc_html_e('Password', 'beepbeep-ai-alt-text-generator'); ?>
                                </label>
                                <input type="password" 
                                       id="admin-login-password" 
                                       name="password" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('Enter your password', 'beepbeep-ai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <button type="submit" id="admin-login-submit-btn" class="bbai-admin-login-btn">
                                <span class="bbai-btn__text"><?php esc_html_e('Log In', 'beepbeep-ai-alt-text-generator'); ?></span>
                                <span class="bbai-btn__spinner" style="display: none;">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="2" stroke-dasharray="43.98" stroke-dashoffset="10.99" fill="none" opacity="0.5">
                                            <animateTransform attributeName="transform" type="rotate" from="0 8 8" to="360 8 8" dur="1s" repeatCount="indefinite"/>
                                        </circle>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else : ?>
                <!-- Admin Content: Debug Logs and Settings -->
                <?php $bbai_can_show_admin_debug = $this->can_show_debug_logs_tab(); ?>
                <div class="bbai-admin-content">
                    <!-- Admin Header with Logout -->
                    <div class="bbai-admin-header">
                        <div class="bbai-admin-header-info">
                            <h2 class="bbai-admin-header-title">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="margin-right: 10px; vertical-align: middle;">
                                    <path d="M10 1L19 10L10 19L1 10L10 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="10" cy="10" r="2.5" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Panel', 'beepbeep-ai-alt-text-generator'); ?>
                            </h2>
                            <p class="bbai-admin-header-subtitle">
                                <?php
                                if ($bbai_can_show_admin_debug) {
                                    esc_html_e('Debug Logs and Settings', 'beepbeep-ai-alt-text-generator');
                                } else {
                                    esc_html_e('Settings', 'beepbeep-ai-alt-text-generator');
                                }
                                ?>
                            </p>
                        </div>
                        <button type="button" class="bbai-admin-logout-btn" id="bbai-admin-logout-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M10 11L13 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Log Out', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <!-- Admin Tabs Navigation -->
                    <div class="bbai-admin-tabs">
                        <?php if ($bbai_can_show_admin_debug) : ?>
                            <button type="button" class="bbai-admin-tab active" data-admin-tab="debug">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="bbai-admin-tab <?php echo esc_attr($bbai_can_show_admin_debug ? '' : 'active'); ?>" data-admin-tab="settings">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <?php if ($bbai_can_show_admin_debug) : ?>
                        <!-- Debug Logs Section -->
                        <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="debug">
                            <div class="bbai-admin-section-header">
                                <h3 class="bbai-admin-section-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h3>
                            </div>
                            <?php
                            $bbai_debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
                            if (file_exists($bbai_debug_partial)) {
                                include $bbai_debug_partial;
                            } else {
                                esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Settings Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="settings" style="<?php echo esc_attr($bbai_can_show_admin_debug ? 'display: none;' : 'display: block;'); ?>">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        $bbai_settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
                        if (file_exists($bbai_settings_partial)) {
                            include $bbai_settings_partial;
                        } else {
                            esc_html_e('Settings content unavailable.', 'beepbeep-ai-alt-text-generator');
                        }
                        ?>
                    </div>
            <?php endif; ?>
            </div><!-- .bbai-container -->
            
            <!-- Footer -->
            <div class="bbai-footer">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-ai-alt-text-generator'); ?></a>
            <?php else : ?>
                <!-- Fallback: No tab matched -->
                <div class="bbai-container bbai-unauth-container">
                    <h2><?php esc_html_e('Tab not found', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    <p><?php
                    printf(
                        /* translators: 1: requested tab, 2: available tabs list */
                        esc_html__('The requested tab "%1$s" could not be loaded. Available tabs: %2$s', 'beepbeep-ai-alt-text-generator'),
                        esc_html($bbai_tab),
                        esc_html(implode(', ', array_keys($bbai_tabs ?? [])))
                    );
                    ?></p>
                    <p><strong><?php esc_html_e('Debug info:', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                    <ul class="bbai-unauth-list">
                        <li><?php
                        /* translators: 1: tab identifier */
                        printf(esc_html__('Tab: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_tab));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Is Authenticated: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_is_authenticated ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_license ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored Token: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_stored_token ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($bbai_has_stored_license ? 'Yes' : 'No'));
                        ?></li>
                    </ul>
                </div>
            </div><!-- .bbai-container -->

            <!-- Footer -->
            <div class="bbai-footer">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
        
        <?php endif; // End tab check (dashboard/library/help/usage/settings/admin views)
        
        // Include upgrade modal OUTSIDE of tab conditionals so it's always available
        // Set up currency for upgrade modal - Always use GBP (£) with Stripe prices
        // GBP prices: Growth £12.99, Agency £49.99, Credits £9.99 (matching Stripe payment links)
        $bbai_currency = ['symbol' => '£', 'code' => 'GBP', 'free' => 0, 'growth' => 12.99, 'pro' => 12.99, 'agency' => 49.99, 'credits' => 9.99];
        
        // Include upgrade modal - always available for all tabs
        $bbai_checkout_prices = $this->get_checkout_price_ids();
        include BEEPBEEP_AI_PLUGIN_DIR . 'templates/upgrade-modal.php';
        $bbai_feature_modal = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/feature-unlock-modal.php';
        if ( file_exists( $bbai_feature_modal ) ) {
            include $bbai_feature_modal;
        }
    }
}
