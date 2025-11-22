<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('BBAI_PLUGIN_FILE')) {
    define('BBAI_PLUGIN_FILE', dirname(__FILE__, 2) . '/beepbeep-bbai-text-generator.php');
}

if (!defined('BBAI_PLUGIN_DIR')) {
    define('BBAI_PLUGIN_DIR', plugin_dir_path(BBAI_PLUGIN_FILE));
}

if (!defined('BBAI_PLUGIN_URL')) {
    define('BBAI_PLUGIN_URL', plugin_dir_url(BBAI_PLUGIN_FILE));
}

if (!defined('BBAI_PLUGIN_BASENAME')) {
    define('BBAI_PLUGIN_BASENAME', plugin_basename(BBAI_PLUGIN_FILE));
}

// Load API clients, usage tracker, and queue infrastructure
require_once BBAI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BBAI_PLUGIN_DIR . 'includes/class-debug-log.php';

class BbAI_Core {
    const OPTION_KEY = 'bbai_settings';
    const NONCE_KEY  = 'bbai_nonce';
    const CAPABILITY = 'manage_bbbeepbeepai_text';

    private const DEFAULT_CHECKOUT_PRICE_IDS = [
        'pro'     => 'price_1SMrxaJl9Rm418cMM4iikjlJ',
        'agency'  => 'price_1SMrxaJl9Rm418cMnJTShXSY',
        'credits' => 'price_1SMrxbJl9Rm418cM0gkzZQZt',
    ];

    private $stats_cache = null;
    private $token_notice = null;
    private $api_client = null;
    private $checkout_price_cache = null;
    private $debug_bootstrap = null;
    private $account_summary = null;

    public function user_can_manage(){
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public function __construct() {
        // Use Phase 2 API client (JWT-based authentication)
        $this->api_client = new BbAI_API_Client_V2();
        // Soft-migrate legacy options to new prefixed keys
        $current = get_option(self::OPTION_KEY, null);
        if ($current === null) {
            foreach (['beepbeepai_gpt_settings', 'beepbeepai_settings', 'beepbeepbeepbeepai_settings', 'bbai_settings'] as $legacy_key) {
                $legacy_value = get_option($legacy_key, null);
                if ($legacy_value !== null) {
                    update_option(self::OPTION_KEY, $legacy_value, false);
                    break;
                }
            }
        }

        if (class_exists('BbAI_Debug_Log')) {
            // Ensure table exists
            BbAI_Debug_Log::create_table();
            
            // Log initialization
            BbAI_Debug_Log::log('info', 'AI Alt Text plugin initialized', [
                'version' => BBAI_VERSION,
                'authenticated' => $this->api_client->is_authenticated() ? 'yes' : 'no',
            ], 'core');
            
            update_option('bbai_logs_ready', true, false);
        }
    }

    /**
     * Expose API client for collaborators (REST controller, admin UI, etc.).
     *
     * @return BbAI_API_Client_V2
     */
    public function get_api_client() {
        return $this->api_client;
    }

    public function default_usage(){
        return [
            'prompt'      => 0,
            'completion'  => 0,
            'total'       => 0,
            'requests'    => 0,
            'last_request'=> null,
        ];
    }

    private function record_usage($usage){
        $prompt     = isset($usage['prompt']) ? max(0, intval($usage['prompt'])) : 0;
        $completion = isset($usage['completion']) ? max(0, intval($usage['completion'])) : 0;
        $total      = isset($usage['total']) ? max(0, intval($usage['total'])) : ($prompt + $completion);

        if (!$prompt && !$completion && !$total){
            return;
        }

        $opts = get_option(self::OPTION_KEY, []);
        $current = $opts['usage'] ?? $this->default_usage();
        $current['prompt']     += $prompt;
        $current['completion'] += $completion;
        $current['total']      += $total;
        $current['requests']   += 1;
        $current['last_request'] = current_time('mysql');

        $opts['usage'] = $current;
        $opts['token_alert_sent'] = $opts['token_alert_sent'] ?? false;
        $opts['token_limit'] = $opts['token_limit'] ?? 0;

        if (!empty($opts['token_limit']) && !$opts['token_alert_sent'] && $current['total'] >= $opts['token_limit']){
            $opts['token_alert_sent'] = true;
            set_transient('beepbeepai_token_notice', [
                'total' => $current['total'],
                'limit' => $opts['token_limit'],
            ], DAY_IN_SECONDS);
            $this->send_notification(
                __('AI Alt Text token usage alert', 'wp-alt-text-plugin'),
                sprintf(
                    __('Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'wp-alt-text-plugin'),
                    $current['total'],
                    $opts['token_limit']
                )
            );
        }

        update_option(self::OPTION_KEY, $opts, false);
        $this->stats_cache = null;
    }

    /**
     * Refresh usage snapshot from backend when a site license is active.
     * Throttled to avoid hammering the API during bulk jobs.
     */
    private function refresh_license_usage_snapshot($force = false) {
        if (!$this->api_client->has_active_license()) {
            return;
        }

        $cache_key = 'bbai_usage_refresh_lock';
        if (!$force) {
            $last_refresh = get_transient($cache_key);
            if (!empty($last_refresh)) {
                $elapsed = time() - intval($last_refresh);
                if ($elapsed < 60) {
                    return;
                }
            }
        }

        $latest_usage = $this->api_client->get_usage();
        if (is_wp_error($latest_usage) || !is_array($latest_usage)) {
            return;
        }

        BbAI_Usage_Tracker::update_usage($latest_usage);
        set_transient($cache_key, time(), MINUTE_IN_SECONDS);
    }

    private function get_debug_bootstrap($force_refresh = false) {
        if ($force_refresh || $this->debug_bootstrap === null) {
            if (class_exists('BbAI_Debug_Log')) {
                $this->debug_bootstrap = BbAI_Debug_Log::get_logs([
                    'per_page' => 10,
                    'page' => 1,
                ]);
            } else {
                $this->debug_bootstrap = [
                    'logs' => [],
                    'pagination' => [
                        'page' => 1,
                        'per_page' => 10,
                        'total_pages' => 1,
                        'total_items' => 0,
                    ],
                    'stats' => [
                        'total' => 0,
                        'warnings' => 0,
                        'errors' => 0,
                        'last_api' => null,
                    ],
                ];
            }
        }

        return $this->debug_bootstrap;
    }

    private function send_notification($subject, $message){
        $opts = get_option(self::OPTION_KEY, []);
        $email = $opts['notify_email'] ?? get_option('admin_email');
        $email = is_email($email) ? $email : get_option('admin_email');
        if (!$email){
            return;
        }
        wp_mail($email, $subject, $message);
    }

    public function ensure_capability(){
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)){
            $role->add_cap(self::CAPABILITY);
        }
    }

    public function maybe_display_threshold_notice(){
        if (!$this->user_can_manage()){
            return;
        }
        $data = get_transient('beepbeepai_token_notice');
        if (!$data) {
            // Fallback to legacy transient name during transition
            $data = get_transient('bbai_token_notice');
        }
        if ($data){
            $this->token_notice = $data;
            add_action('admin_notices', [$this, 'render_token_notice']);
        }
    }

    /**
     * Allow direct checkout links to create Stripe sessions without JavaScript
     */
    public function maybe_handle_direct_checkout() {
        if (!is_admin()) { return; }
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page !== 'bbai-checkout') { return; }

        if (!$this->user_can_manage()) {
            wp_die(__('You do not have permission to perform this action.', 'wp-alt-text-plugin'));
        }

        $nonce_raw = isset($_GET['_bbai_nonce']) && $_GET['_bbai_nonce'] !== null ? wp_unslash($_GET['_bbai_nonce']) : '';
        $nonce = is_string($nonce_raw) ? sanitize_text_field($nonce_raw) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'bbai_direct_checkout')) {
            wp_die(__('Security check failed. Please try again from the dashboard.', 'wp-alt-text-plugin'));
        }

        $plan_raw = isset($_GET['plan']) ? wp_unslash($_GET['plan']) : (isset($_GET['type']) ? wp_unslash($_GET['type']) : '');
        $plan_param = sanitize_key($plan_raw);
        $price_id_raw = isset($_GET['price_id']) && $_GET['price_id'] !== null ? wp_unslash($_GET['price_id']) : '';
        $price_id = is_string($price_id_raw) ? sanitize_text_field($price_id_raw) : '';
        $fallback = BbAI_Usage_Tracker::get_upgrade_url();

        if ($plan_param) {
            $mapped_price = $this->get_checkout_price_id($plan_param);
            if (!empty($mapped_price)) {
                $price_id = $mapped_price;
            }
        }

        if (empty($price_id)) {
            wp_safe_redirect($fallback);
            exit;
        }

        $success_url = admin_url('upload.php?page=bbai&checkout=success');
        $cancel_url  = admin_url('upload.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result) || empty($result['url'])) {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Unable to start checkout. Please try again.', 'wp-alt-text-plugin');
            $plan_raw = isset($_GET['plan']) ? wp_unslash($_GET['plan']) : (isset($_GET['type']) ? wp_unslash($_GET['type']) : '');
        $plan_param = sanitize_key($plan_raw);
            $query_args = [
                'page'            => 'wp-alt-text-plugin',
                'checkout_error'  => rawurlencode($message),
            ];
            if (!empty($plan_param)) {
                $query_args['plan'] = $plan_param;
            }
            $redirect = add_query_arg($query_args, admin_url('upload.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        // Redirect to Stripe checkout
        wp_safe_redirect( $result['url'] );
        exit;
        exit;
    }

    /**
     * Retrieve checkout price IDs sourced from the backend
     */
    public function get_checkout_price_ids() {
        if (is_array($this->checkout_price_cache)) {
            return $this->checkout_price_cache;
        }

        $prices = self::DEFAULT_CHECKOUT_PRICE_IDS;

        $cached = get_transient('bbai_remote_price_ids');
        if (!is_array($cached)) {
            $plans = $this->api_client->get_plans();
            if (!is_wp_error($plans) && !empty($plans)) {
                $remote = [];
                foreach ($plans as $plan) {
                    if (!is_array($plan)) {
                        continue;
                    }
                    $plan_id = isset($plan['id']) && is_string($plan['id']) ? sanitize_key($plan['id']) : '';
                    $price_id = !empty($plan['priceId']) && is_string($plan['priceId']) ? sanitize_text_field($plan['priceId']) : '';
                    if ($plan_id && $price_id) {
                        $remote[$plan_id] = $price_id;
                    }
                }
                if (!empty($remote)) {
                    set_transient('bbai_remote_price_ids', $remote, 10 * MINUTE_IN_SECONDS);
                    $cached = $remote;
                }
            }
        }

        if (is_array($cached)) {
            foreach ($cached as $plan_id => $price_id) {
                $plan_id = is_string($plan_id) ? sanitize_key($plan_id) : '';
                $price_id = is_string($price_id) ? sanitize_text_field($price_id) : '';
                if ($plan_id && $price_id) {
                    $prices[$plan_id] = $price_id;
                }
            }
        }

        // Backwards compatibility: use saved overrides when a plan is missing a mapped price.
        $stored = get_option('bbai_checkout_prices', []);
        if (is_array($stored) && !empty($stored)) {
            foreach ($stored as $key => $value) {
                $key = is_string($key) ? sanitize_key($key) : '';
                $value = is_string($value) ? sanitize_text_field($value) : '';
                if ($key && $value && empty($prices[$key])) {
                    $prices[$key] = $value;
                }
            }
        }

        $prices = apply_filters('bbai_checkout_price_ids', $prices);
        $this->checkout_price_cache = $prices;
        return $prices;
    }

    /**
     * Helper to grab a single price ID
     */
    public function get_checkout_price_id($plan) {
        $prices = $this->get_checkout_price_ids();
        $plan = is_string($plan) ? sanitize_key($plan) : '';
        $price_id = $prices[$plan] ?? '';
        return apply_filters('bbai_checkout_price_id', $price_id, $plan, $prices);
    }

    /**
     * Surface checkout success/error notices in WP Admin
     */
    public function maybe_render_checkout_notices() {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page !== 'wp-alt-text-plugin') {
            return;
        }

        $checkout_error = isset($_GET['checkout_error']) ? wp_unslash($_GET['checkout_error']) : '';
        if (!empty($checkout_error)) {
            $message = is_string($checkout_error) ? sanitize_text_field($checkout_error) : '';
            $plan_raw = isset($_GET['plan']) ? wp_unslash($_GET['plan']) : '';
            $plan = is_string($plan_raw) ? sanitize_text_field($plan_raw) : '';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Checkout Failed', 'wp-alt-text-plugin'); ?>:</strong>
                    <?php echo esc_html($message); ?>
                    <?php if ($plan) : ?>
                        (<?php echo esc_html(sprintf(__('Plan: %s', 'wp-alt-text-plugin'), $plan)); ?>)
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Verify your BeepBeep AI account credentials and Stripe configuration, then try again.', 'wp-alt-text-plugin'); ?></p>
            </div>
            <?php
        } else {
            $checkout = isset($_GET['checkout']) ? sanitize_key(wp_unslash($_GET['checkout'])) : '';
            if ($checkout === 'success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Checkout session started. Complete the payment in the newly opened tab.', 'wp-alt-text-plugin'); ?></p>
                </div>
                <?php
            } elseif ($checkout === 'cancel') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php esc_html_e('Checkout was cancelled. No changes were made to your plan.', 'wp-alt-text-plugin'); ?></p>
                </div>
                <?php
            }
        }

        // Password reset notices
        $password_reset = isset($_GET['password_reset']) ? sanitize_key(wp_unslash($_GET['password_reset'])) : '';
        if ($password_reset === 'requested') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Email Sent', 'wp-alt-text-plugin'); ?></strong></p>
                <p><?php esc_html_e('Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'wp-alt-text-plugin'); ?></p>
            </div>
            <?php
        } elseif ($password_reset === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Successful', 'wp-alt-text-plugin'); ?></strong></p>
                <p><?php esc_html_e('Your password has been updated. You can now sign in with your new password.', 'wp-alt-text-plugin'); ?></p>
            </div>
            <?php
        }

        // Subscription update notices
        $subscription_updated = isset($_GET['subscription_updated']) ? sanitize_key(wp_unslash($_GET['subscription_updated'])) : '';
        if (!empty($subscription_updated)) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Subscription Updated', 'wp-alt-text-plugin'); ?></strong></p>
                <p><?php esc_html_e('Your subscription information has been refreshed.', 'wp-alt-text-plugin'); ?></p>
            </div>
            <?php
        }

        $portal_return = isset($_GET['portal_return']) ? sanitize_key(wp_unslash($_GET['portal_return'])) : '';
        if ($portal_return === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Billing Updated', 'wp-alt-text-plugin'); ?></strong></p>
                <p><?php esc_html_e('Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'wp-alt-text-plugin'); ?></p>
            </div>
            <?php
        }
    }

    public function render_token_notice(){
        if (empty($this->token_notice)){
            return;
        }
        delete_transient('beepbeepai_token_notice');
        delete_transient('bbai_token_notice');
        $total = number_format_i18n($this->token_notice['total'] ?? 0);
        $limit = number_format_i18n($this->token_notice['limit'] ?? 0);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(__('AI Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'wp-alt-text-plugin'), $total, $limit)) . '</p></div>';
        $this->token_notice = null;
    }

    public function maybe_render_queue_notice(){
        if (!isset($_GET['beepbeepai_queued'])) {
            return;
        }
        $count_raw = isset($_GET['beepbeepai_queued']) ? wp_unslash($_GET['beepbeepai_queued']) : '';
        $count = absint($count_raw);
        if ($count <= 0) {
            return;
        }
        $message = $count === 1
            ? __('1 image queued for background optimisation. The alt text will appear shortly.', 'wp-alt-text-plugin')
            : sprintf(__('Queued %d images for background optimisation. Alt text will be generated shortly.', 'wp-alt-text-plugin'), $count);
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Display external API compliance modal (WordPress.org requirement).
     * Shows once as a popup after activation to inform users about external service usage.
     * Rendered in admin_footer so it appears as a modal overlay.
     */
    public function maybe_render_external_api_notice() {
        // Only show on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || !isset($screen->id) || !is_string($screen->id)) {
            return;
        }
        $screen_id = (string)$screen->id;
        if (strpos($screen_id, 'bbai') === false && strpos($screen_id, 'ai-alt') === false) {
            return;
        }

        // Check if modal has been dismissed (site-wide option, shows once for all users)
        $dismissed = get_option('wp_alt_text_api_notice_dismissed', false);
        if ($dismissed) {
            return;
        }

        // Show modal popup if not dismissed
        $api_url = 'https://alttext-ai-backend.onrender.com';
        $privacy_url = 'https://wordpress.org/plugins/beepbeep-bbai-text-generator/';
        $terms_url = 'https://wordpress.org/plugins/beepbeep-bbai-text-generator/';
        $nonce = wp_create_nonce('wp_alt_text_dismiss_api_notice');
        ?>
        <div id="bbai-api-notice-modal" class="bbai-modal-backdrop" style="display: none; opacity: 0;" role="dialog" aria-modal="true" aria-labelledby="bbai-api-notice-title" aria-describedby="bbai-api-notice-desc">
            <div class="bbai-upgrade-modal__content bbai-api-notice-modal-content" style="max-width: 600px;">
                <div class="bbai-upgrade-modal__header">
                    <div class="bbai-upgrade-modal__header-content">
                        <h2 id="wp-alt-text-api-notice-title"><?php esc_html_e('External Service Notice', 'wp-alt-text-plugin'); ?></h2>
                    </div>
                    <button type="button" class="bbai-modal-close" onclick="bbaiCloseApiNotice();" aria-label="<?php esc_attr_e('Close notice', 'wp-alt-text-plugin'); ?>">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                
                <div class="bbai-upgrade-modal__body" id="bbai-api-notice-desc" style="padding: 24px;">
                    <p style="margin: 0 0 20px 0; color: #374151; line-height: 1.6; font-size: 14px;">
                        <?php esc_html_e('This plugin connects to an external API service to generate alt text. Image data is transmitted securely to process generation. No personal user data is collected.', 'wp-alt-text-plugin'); ?>
                    </p>
                    
                    <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; margin-bottom: 0;">
                        <p style="margin: 0 0 12px 0; font-weight: 600; color: #111827; font-size: 14px;">
                            <?php esc_html_e('API Endpoint:', 'wp-alt-text-plugin'); ?>
                        </p>
                        <p style="margin: 0 0 16px 0; color: #6B7280; font-size: 13px; font-family: monospace; word-break: break-all; line-height: 1.5;">
                            <?php echo esc_html($api_url); ?>
                        </p>
                        
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #111827; font-size: 14px;">
                            <?php esc_html_e('Privacy Policy:', 'wp-alt-text-plugin'); ?>
                        </p>
                        <p style="margin: 0 0 16px 0;">
                            <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener" style="color: #2563EB; text-decoration: underline; font-size: 13px;">
                                <?php echo esc_html($privacy_url); ?>
                            </a>
                        </p>
                        
                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #111827; font-size: 14px;">
                            <?php esc_html_e('Terms of Service:', 'wp-alt-text-plugin'); ?>
                        </p>
                        <p style="margin: 0;">
                            <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener" style="color: #2563EB; text-decoration: underline; font-size: 13px;">
                                <?php echo esc_html($terms_url); ?>
                            </a>
                        </p>
                    </div>
                </div>
                
                <div class="bbai-upgrade-modal__footer" style="padding: 20px 24px; border-top: 1px solid #E5E7EB; text-align: right; background: #FFFFFF;">
                    <button type="button" class="button button-primary" onclick="bbaiCloseApiNotice();" style="min-width: 100px;">
                        <?php esc_html_e('Got it', 'wp-alt-text-plugin'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        (function($) {
            // Ensure jQuery and ajaxurl are available
            if (typeof $ === 'undefined') {
                console.error('[WP Alt Text AI] jQuery is required for the API notice modal');
                return;
            }
            
            // Show modal on page load with a small delay to ensure styles are loaded
            $(document).ready(function() {
                var $modal = $('#wp-alt-text-api-notice-modal');
                if ($modal.length === 0) {
                    return;
                }
                
                // Small delay to ensure CSS is loaded
                setTimeout(function() {
                    $modal.css({
                        'display': 'flex',
                        'opacity': '0'
                    }).animate({
                        'opacity': '1'
                    }, 300);
                }, 500);
            });
            
            // Handle close button
            window.bbaiCloseApiNotice = function() {
                var $modal = $('#wp-alt-text-api-notice-modal');
                if ($modal.length === 0) {
                    return;
                }
                
                // Fade out and remove
                $modal.animate({
                    'opacity': '0'
                }, 200, function() {
                    // Dismiss via AJAX
                    var ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wp_alt_text_dismiss_api_notice',
                            nonce: '<?php echo esc_js($nonce); ?>'
                        },
                        success: function(response) {
                            console.log('[WP Alt Text AI] API notice dismissed');
                        },
                        error: function() {
                            console.log('[WP Alt Text AI] Failed to dismiss notice');
                        }
                    });
                    
                    // Remove modal from DOM
                    $modal.remove();
                });
            };
            
            // Close on backdrop click (use event delegation)
            $(document).on('click', '#bbai-api-notice-modal.bbai-modal-backdrop', function(e) {
                if (e.target === this) {
                    e.preventDefault();
                    e.stopPropagation();
                    wpAltTextCloseApiNotice();
                }
            });
            
            // Close on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    var $modal = $('#wp-alt-text-api-notice-modal');
                    if ($modal.length > 0 && $modal.is(':visible')) {
                        wpAltTextCloseApiNotice();
                    }
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    public function deactivate(){
        wp_clear_scheduled_hook(BbAI_Queue::CRON_HOOK);
    }

    public function activate() {
        global $wpdb;

        BbAI_Queue::create_table();
        BbAI_Queue::schedule_processing(10);
        BbAI_Debug_Log::create_table();
        update_option('bbai_logs_ready', true, false);

        // Create database indexes for performance
        $this->create_performance_indexes();

        $defaults = [
            'api_url'          => 'https://alttext-ai-backend.onrender.com',
            'model'            => 'gpt-4o-mini',
            'max_words'        => 16,
            'language'         => 'en-GB',
            'language_custom'  => '',
            'enable_on_upload' => true,
            'tone'             => 'professional, accessible',
            'force_overwrite'  => false,
            'token_limit'      => 0,
            'token_alert_sent' => false,
            'dry_run'          => false,
            'custom_prompt'    => '',
            'notify_email'     => get_option('admin_email'),
            'usage'            => $this->default_usage(),
        ];
        $existing = get_option(self::OPTION_KEY, []);
        $updated = wp_parse_args($existing, $defaults);

        // ALWAYS force production API URL
        $updated['api_url'] = 'https://alttext-ai-backend.onrender.com';

        update_option(self::OPTION_KEY, $updated, false);

        // Clear any invalid cached tokens
        delete_option('bbai_jwt_token');
        delete_option('bbai_user_data');
        delete_transient('bbai_token_last_check');

        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)){
            $role->add_cap(self::CAPABILITY);
        }
    }
    
    private function create_performance_indexes() {
        global $wpdb;
        
        // Index for _beepbeepai_generated_at (used in sorting and stats)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
        $wpdb->query("
            CREATE INDEX idx_beepbeepai_generated_at 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        ");
        
        // Index for _beepbeepai_source (used in stats aggregation)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
        $wpdb->query("
            CREATE INDEX idx_beepbeepai_source 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        ");
        
        // Index for _wp_attachment_image_alt (used in coverage stats)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
        $wpdb->query("
            CREATE INDEX idx_wp_attachment_alt 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(100))
        ");
        
        // Composite index for attachment queries
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table names are safe
        $wpdb->query("
            CREATE INDEX idx_posts_attachment_image 
            ON {$wpdb->posts} (post_type(20), post_mime_type(20), post_status(20))
        ");
    }

    public function add_settings_page() {
        $cap = current_user_can(self::CAPABILITY) ? self::CAPABILITY : 'manage_options';
        add_media_page(
            'AI Alt Text Generation',
            'AI Alt Text Generation',
            $cap,
            'bbai',
            [$this, 'render_settings_page']
        );

        // Hidden checkout redirect page
        add_submenu_page(
            null, // No parent = hidden from menu
            'Checkout',
            'Checkout',
            $cap,
            'bbai-checkout',
            [$this, 'handle_checkout_redirect']
        );
    }

    public function handle_checkout_redirect() {
        if (!$this->api_client->is_authenticated()) {
            wp_die('Please sign in first to upgrade.');
        }

        $price_id_raw = isset($_GET['price_id']) ? wp_unslash($_GET['price_id']) : '';
        $price_id = is_string($price_id_raw) ? sanitize_text_field($price_id_raw) : '';
        if (empty($price_id)) {
            wp_die('Invalid checkout request.');
        }

        $success_url = admin_url('upload.php?page=bbai&checkout=success');
        $cancel_url = admin_url('upload.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            wp_die('Checkout error: ' . $result->get_error_message());
        }

        if (!empty($result['url'])) {
            wp_safe_redirect( $result['url'] );
            exit;
        }

        wp_die('Failed to create checkout session.');
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
                $lang_input_raw = isset($input['language']) ? (string)$input['language'] : 'en-GB';
                $lang_input = $lang_input_raw ? sanitize_text_field($lang_input_raw) : 'en-GB';
                $custom_input_raw = isset($input['language_custom']) ? (string)$input['language_custom'] : '';
                $custom_input = $custom_input_raw ? sanitize_text_field($custom_input_raw) : '';
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
                $notify_raw = $input['notify_email'] ?? ($existing['notify_email'] ?? get_option('admin_email'));
                $notify = is_string($notify_raw) ? sanitize_text_field($notify_raw) : '';
                $out['notify_email'] = $notify && is_email($notify) ? $notify : ($existing['notify_email'] ?? get_option('admin_email'));
                $out['usage']            = $existing['usage'] ?? $this->default_usage();

                return $out;
            }
        ]);
    }

    public function render_settings_page() {
        if (!$this->user_can_manage()) return;
        $opts  = get_option(self::OPTION_KEY, []);
        $stats = $this->get_media_stats();
        $nonce = wp_create_nonce(self::NONCE_KEY);
        
        // Check if there's a registered user (authenticated or has active license)
        $is_authenticated = $this->api_client->is_authenticated();
        $has_license = $this->api_client->has_active_license();
        $has_registered_user = $is_authenticated || $has_license;
        
        // Build tabs - show only Dashboard and How to if no registered user
        if (!$has_registered_user) {
            $tabs = [
                'dashboard' => __('Dashboard', 'wp-alt-text-plugin'),
                'guide'     => __('How to', 'wp-alt-text-plugin'),
            ];
            
            // Force dashboard tab if trying to access restricted tabs
            $allowed_tabs = ['dashboard', 'guide'];
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
            if (!in_array($tab, $allowed_tabs)) {
                $tab = 'dashboard';
            }
            
            // Not a registered user - set defaults
            $is_pro_for_admin = false;
            $is_agency_for_admin = false;
        } else {
            // Determine if agency license
            $has_license = $this->api_client->has_active_license();
            $license_data = $this->api_client->get_license_data();
            $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
            
            // If using license, check license plan
            if ($has_license && $license_data && isset($license_data['organization'])) {
                $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                if ($license_plan !== 'free') {
                    $plan_slug = $license_plan;
                }
            }
            
            $is_agency = ($plan_slug === 'agency');
            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
            
            // Show all tabs for registered users
        $tabs = [
                'dashboard' => __('Dashboard', 'wp-alt-text-plugin'),
                'library'   => __('ALT Library', 'wp-alt-text-plugin'),
                'guide'     => __('How to', 'wp-alt-text-plugin'),
            ];
            
            // For agency and pro: add Admin tab (contains Debug Logs and Settings)
            // For non-premium authenticated users: show Debug Logs and Settings tabs
            if ($is_pro) {
                $tabs['admin'] = __('Admin', 'wp-alt-text-plugin');
            } elseif ($is_authenticated) {
                $tabs['debug'] = __('Debug Logs', 'wp-alt-text-plugin');
                $tabs['settings'] = __('Settings', 'wp-alt-text-plugin');
            }
            
            $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

            // If trying to access restricted tabs, redirect to dashboard
            if (!in_array($tab, array_keys($tabs))) {
                $tab = 'dashboard';
            }
            
            // Set variables for Admin tab access (used later in template)
            $is_pro_for_admin = $is_pro;
            $is_agency_for_admin = $is_agency;
        }
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=bbai_usage_export'), 'bbai_usage_export');
        $audit_rows = $stats['audit'] ?? [];
        $debug_bootstrap = $this->get_debug_bootstrap();
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
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'wp-alt-text-plugin'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'wp-alt-text-plugin'); ?></span>
                        </div>
                    </div>
                    <nav class="bbai-nav">
                        <?php foreach ($tabs as $slug => $label) :
                            $url = esc_url(add_query_arg(['tab' => $slug]));
                            $active = $tab === $slug ? ' active' : '';
                        ?>
                            <a href="<?php echo esc_url($url); ?>" class="bbai-nav-link<?php echo esc_attr($active); ?>"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <!-- Auth & Subscription Actions -->
                    <div class="bbai-header-actions">
                        <?php
                        $has_license = $this->api_client->has_active_license();
                        $is_authenticated = $this->api_client->is_authenticated();

                        if ($is_authenticated || $has_license) :
                            $usage_stats = BbAI_Usage_Tracker::get_stats_display();
                            $account_summary = $is_authenticated ? $this->get_account_summary($usage_stats) : null;
                            $plan_slug  = $usage_stats['plan'] ?? 'free';
                            $plan_label = isset($usage_stats['plan_label']) ? (string)$usage_stats['plan_label'] : ucfirst($plan_slug);
                            $connected_email = isset($account_summary['email']) ? (string)$account_summary['email'] : '';
                            $billing_portal = BbAI_Usage_Tracker::get_billing_portal_url();

                            // If license-only mode (no personal login), show license info
                            if ($has_license && !$is_authenticated) {
                                $license_data = $this->api_client->get_license_data();
                                $org_name = isset($license_data['organization']['name']) ? (string)$license_data['organization']['name'] : '';
                                $connected_email = $org_name ?: __('License Active', 'wp-alt-text-plugin');
                            }
                        ?>
                            <!-- Compact Account Bar in Header -->
                            <div class="bbai-header-account-bar">
                                <span class="bbai-header-account-email"><?php echo esc_html(is_string($connected_email) ? $connected_email : __('Connected', 'wp-alt-text-plugin')); ?></span>
                                <span class="bbai-header-plan-badge"><?php echo esc_html(is_string($plan_label) ? $plan_label : ucfirst($plan_slug ?? 'free')); ?></span>
                                <?php if ($plan_slug === 'free' && !$has_license) : ?>
                                    <button type="button" class="bbai-header-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade Plan', 'wp-alt-text-plugin'); ?>
                                    </button>
                                <?php elseif (!empty($billing_portal) && $is_authenticated) : ?>
                                    <button type="button" class="bbai-header-manage-btn" data-action="open-billing-portal">
                                        <?php esc_html_e('Manage', 'wp-alt-text-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($is_authenticated) : ?>
                                <button type="button" class="bbai-header-disconnect-btn" data-action="disconnect-account">
                                    <?php esc_html_e('Disconnect', 'wp-alt-text-plugin'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <button type="button" class="bbai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span><?php esc_html_e('Login', 'wp-alt-text-plugin'); ?></span>
                            </button>
                        <?php endif; ?>
                </div>
                </div>
            </div>
            
            <!-- Main Content Container -->
            <div class="bbai-container">

            <?php if ($tab === 'dashboard') : ?>
            <?php
                $coverage_numeric = max(0, min(100, floatval($stats['coverage'])));
                $coverage_decimals = $coverage_numeric === floor($coverage_numeric) ? 0 : 1;
                $coverage_display = number_format_i18n($coverage_numeric, $coverage_decimals);
                /* translators: %s: Percentage value */
                $coverage_text = $coverage_display . '%';
                /* translators: %s: Percentage value */
                $coverage_value_text = sprintf(__('ALT coverage at %s', 'wp-alt-text-plugin'), $coverage_text);
            ?>

            <?php
                $checkout_nonce = wp_create_nonce('bbai_direct_checkout');
                $checkout_base  = admin_url('admin.php');
                $price_ids      = $this->get_checkout_price_ids();

                $pro_plan  = [
                    'page'             => 'bbai-checkout',
                    'plan'             => 'pro',
                    'price_id'         => $price_ids['pro'] ?? '',
                    '_bbai_nonce' => $checkout_nonce,
                ];
                $agency_plan = [
                    'page'             => 'bbai-checkout',
                    'plan'             => 'agency',
                    'price_id'         => $price_ids['agency'] ?? '',
                    '_bbai_nonce' => $checkout_nonce,
                ];
                $credits_plan = [
                    'page'             => 'bbai-checkout',
                    'type'             => 'credits',
                    'price_id'         => $price_ids['credits'] ?? '',
                    '_bbai_nonce' => $checkout_nonce,
                ];
                $pro_test_url     = esc_url(add_query_arg($pro_plan, $checkout_base));
                $agency_test_url  = esc_url(add_query_arg($agency_plan, $checkout_base));
                $credits_test_url = esc_url(add_query_arg($credits_plan, $checkout_base));
            ?>

            <div class="bbai-clean-dashboard" data-stats='<?php echo esc_attr(wp_json_encode($stats)); ?>'>
                <?php
                // Get usage stats
                $usage_stats = BbAI_Usage_Tracker::get_stats_display();
                
                // Pull fresh usage from backend to avoid stale cache - same logic as Settings tab
                if (isset($this->api_client)) {
                    $live_usage = $this->api_client->get_usage();
                    if (is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                        // Update cache with fresh API data
                        BbAI_Usage_Tracker::update_usage($live_usage);
                    }
                }
                // Get stats - will use the just-updated cache
                $usage_stats = BbAI_Usage_Tracker::get_stats_display(false);
                $account_summary = $this->api_client->is_authenticated() ? $this->get_account_summary($usage_stats) : null;
                
                // If stats show 0 but we have API data, use API data directly
                if (isset($live_usage) && is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                    if (($usage_stats['used'] ?? 0) == 0 && ($live_usage['used'] ?? 0) > 0) {
                        // Cache hasn't updated yet, use API data directly
                        $usage_stats['used'] = max(0, intval($live_usage['used'] ?? 0));
                        $usage_stats['limit'] = max(1, intval($live_usage['limit'] ?? 50));
                        $usage_stats['remaining'] = max(0, intval($live_usage['remaining'] ?? 50));
                        // Recalculate percentage
                        $usage_stats['percentage'] = $usage_stats['limit'] > 0 ? (($usage_stats['used'] / $usage_stats['limit']) * 100) : 0;
                        $usage_stats['percentage'] = min(100, max(0, $usage_stats['percentage']));
                        $usage_stats['percentage_display'] = BbAI_Usage_Tracker::format_percentage_label($usage_stats['percentage']);
                    }
                }
                
                // Get raw values directly from the stats array - same calculation method as Settings tab
                $dashboard_used = max(0, intval($usage_stats['used'] ?? 0));
                $dashboard_limit = max(1, intval($usage_stats['limit'] ?? 50));
                $dashboard_remaining = max(0, intval($usage_stats['remaining'] ?? 50));
                
                // Recalculate remaining to ensure accuracy
                $dashboard_remaining = max(0, $dashboard_limit - $dashboard_used);
                
                // Cap used at limit to prevent showing > 100%
                if ($dashboard_used > $dashboard_limit) {
                    $dashboard_used = $dashboard_limit;
                    $dashboard_remaining = 0;
                }
                
                // Calculate percentage - same way as Settings tab
                $percentage = $dashboard_limit > 0 ? (($dashboard_used / $dashboard_limit) * 100) : 0;
                $percentage = min(100, max(0, $percentage));
                
                // If at limit, ensure it shows 100%
                if ($dashboard_used >= $dashboard_limit && $dashboard_remaining <= 0) {
                    $percentage = 100;
                }
                
                // Update the stats with calculated values for display
                $usage_stats['used'] = $dashboard_used;
                $usage_stats['limit'] = $dashboard_limit;
                $usage_stats['remaining'] = $dashboard_remaining;
                $usage_stats['percentage'] = $percentage;
                $usage_stats['percentage_display'] = BbAI_Usage_Tracker::format_percentage_label($percentage);
                ?>
                
                <!-- Clean Dashboard Design -->
                <div class="bbai-dashboard-shell max-w-5xl mx-auto px-6">

                     <!-- HERO Section Styles -->
                     <style>
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
                     </style>

                     <?php if (!$this->api_client->is_authenticated()) : ?>
                     <!-- HERO Section - Not Authenticated -->
                     <div class="bbai-hero-section">
                         <div class="bbai-hero-content">
                             <h2 class="bbai-hero-title">
                                 <?php esc_html_e('🎉 Boost Your Site\'s SEO Automatically', 'wp-alt-text-plugin'); ?>
                             </h2>
                             <p class="bbai-hero-subtitle">
                                 <?php esc_html_e('Let AI write perfect, accessibility-friendly alt text for every image — free each month.', 'wp-alt-text-plugin'); ?>
                             </p>
                         </div>
                         <div class="bbai-hero-actions">
                             <button type="button" class="bbai-hero-btn-primary" id="bbai-show-auth-banner-btn">
                                <?php esc_html_e('Start Free — Generate 50 AI Descriptions', 'wp-alt-text-plugin'); ?>
                             </button>
                             <button type="button" class="bbai-hero-link-secondary" id="bbai-show-auth-login-btn">
                                 <?php esc_html_e('Already a user? Log in', 'wp-alt-text-plugin'); ?>
                             </button>
                         </div>
                         <div class="bbai-hero-micro-copy">
                             <?php esc_html_e('⚡ SEO Boost · 🦾 Accessibility · 🕒 Saves Hours', 'wp-alt-text-plugin'); ?>
                         </div>
                     </div>
                     <?php endif; ?>
                     <!-- Subscription management now in header -->


                    <!-- Tab Content: Dashboard -->
                    <div class="bbai-tab-content active" id="tab-dashboard">
                    <!-- Premium Dashboard Container -->
                    <div class="bbai-premium-dashboard">
                        <!-- Subtle Header Section -->
                        <div class="bbai-dashboard-header-section">
                            <h1 class="bbai-dashboard-title"><?php esc_html_e('Dashboard', 'wp-alt-text-plugin'); ?></h1>
                            <p class="bbai-dashboard-subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'wp-alt-text-plugin'); ?></p>
                        </div>

                        <?php 
                        $is_authenticated = $this->api_client->is_authenticated();
                        $has_license = $this->api_client->has_active_license();
                        if ($is_authenticated || $has_license) : 
                            // Get plan from usage stats or license
                            $plan_slug = $usage_stats['plan'] ?? 'free';
                            
                            // If using license, check license plan
                            if ($has_license && $plan_slug === 'free') {
                                $license_data = $this->api_client->get_license_data();
                                if ($license_data && isset($license_data['organization'])) {
                                    $plan_slug = strtolower($license_data['organization']['plan'] ?? 'free');
                                }
                            }
                            
                            // Determine badge text and class
                            $plan_badge_class = 'bbai-usage-plan-badge';
                            $is_agency = ($plan_slug === 'agency');
                            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                            
                            if ($plan_slug === 'agency') {
                                $plan_badge_text = esc_html__('AGENCY', 'wp-alt-text-plugin');
                                $plan_badge_class .= ' bbai-usage-plan-badge--agency';
                            } elseif ($plan_slug === 'pro') {
                                $plan_badge_text = esc_html__('PRO', 'wp-alt-text-plugin');
                                $plan_badge_class .= ' bbai-usage-plan-badge--pro';
                            } else {
                                $plan_badge_text = esc_html__('FREE', 'wp-alt-text-plugin');
                                $plan_badge_class .= ' bbai-usage-plan-badge--free';
                            }
                        ?>
                        <!-- Premium Stats Grid -->
                        <div class="bbai-premium-stats-grid<?php echo esc_attr($is_agency ? ' bbai-premium-stats-grid--single' : ''); ?>">
                            <!-- Usage Card with Circular Progress -->
                            <div class="bbai-premium-card bbai-usage-card<?php echo esc_attr($is_agency ? ' bbai-usage-card--full-width' : ''); ?>">
                                <?php if ($is_agency) : ?>
                                <!-- Soft purple gradient badge for Agency -->
                                <span class="bbai-usage-plan-badge bbai-usage-plan-badge--agency-polished"><?php echo esc_html__('AGENCY', 'wp-alt-text-plugin'); ?></span>
                                <?php else : ?>
                                <span class="<?php echo esc_attr($plan_badge_class); ?>"><?php echo esc_html($plan_badge_text); ?></span>
                                <?php endif; ?>
                                <?php
                                $percentage = min(100, max(0, $usage_stats['percentage'] ?? 0));
                                $percentage_display = $usage_stats['percentage_display'] ?? BbAI_Usage_Tracker::format_percentage_label($percentage);
                                $radius = 54;
                                $circumference = 2 * M_PI * $radius;
                                // Calculate offset: at 0% = full circumference (hidden), at 100% = 0 (fully visible)
                                $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
                                $gradient_id = 'grad-' . wp_generate_password(8, false);
                                ?>
                                <?php if ($is_agency) : ?>
                                <!-- Full-width agency layout - Polished Design -->
                                <div class="bbai-usage-card-layout-full">
                                    <div class="bbai-usage-card-left">
                                        <h3 class="bbai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'wp-alt-text-plugin'); ?></h3>
                                        <div class="bbai-usage-card-stats">
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['used'])); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Generated', 'wp-alt-text-plugin'); ?></div>
                                            </div>
                                            <div class="bbai-usage-stat-divider"></div>
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['limit'])); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Monthly Limit', 'wp-alt-text-plugin'); ?></div>
                                            </div>
                                            <div class="bbai-usage-stat-divider"></div>
                                            <div class="bbai-usage-stat-item">
                                                <div class="bbai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['remaining'] ?? 0)); ?></div>
                                                <div class="bbai-usage-stat-label"><?php esc_html_e('Remaining', 'wp-alt-text-plugin'); ?></div>
                                            </div>
                                        </div>
                                        <div class="bbai-usage-card-reset">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;" aria-hidden="true">
                                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <span>
                                            <?php 
                                            $reset_date = $usage_stats['reset_date'] ?? '';
                                            if (!empty($reset_date)) {
                                                $reset_timestamp = strtotime($reset_date);
                                                if ($reset_timestamp !== false) {
                                                    $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                                    printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($formatted_date));
                                                } else {
                                                    printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($reset_date));
                                                }
                                            } else {
                                                esc_html_e('Resets monthly', 'wp-alt-text-plugin');
                                            }
                                            ?>
                                            </span>
                                        </div>
                                        <?php 
                                        $plan_slug = $usage_stats['plan'] ?? 'free';
                                        $billing_portal = BbAI_Usage_Tracker::get_billing_portal_url();
                                        ?>
                                        <?php if (!empty($billing_portal)) : ?>
                                        <div class="bbai-usage-card-actions">
                                            <a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;" aria-hidden="true">
                                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                                </svg>
                                                <?php esc_html_e('Manage Billing', 'wp-alt-text-plugin'); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bbai-usage-card-divider" aria-hidden="true"></div>
                                    <div class="bbai-usage-card-right">
                                <div class="bbai-usage-ring-wrapper">
                                            <?php
                                            // Modern thin stroke ring gauge for agency
                                            $agency_radius = 60;
                                            $agency_circumference = 2 * M_PI * $agency_radius;
                                            $agency_stroke_dashoffset = $agency_circumference * (1 - ($percentage / 100));
                                            $agency_gradient_id = 'grad-agency-' . wp_generate_password(8, false);
                                            ?>
                                            <div class="bbai-circular-progress bbai-circular-progress--agency" 
                                                 data-percentage="<?php echo esc_attr($percentage); ?>"
                                                 aria-label="<?php printf(esc_attr__('Credits used: %s%%', 'wp-alt-text-plugin'), esc_attr($percentage_display)); ?>"
                                                 role="progressbar"
                                                 aria-valuenow="<?php echo esc_attr($percentage); ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                                <svg class="bbai-circular-progress-svg" viewBox="0 0 140 140" aria-hidden="true">
                                                    <defs>
                                                        <linearGradient id="<?php echo esc_attr($agency_gradient_id); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                            <stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
                                                            <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
                                                        </linearGradient>
                                                    </defs>
                                                    <!-- Background circle -->
                                                    <circle 
                                                    cx="70" 
                                                    cy="70" 
                                                        r="<?php echo esc_attr($agency_radius); ?>" 
                                                    fill="none"
                                                        stroke="#f3f4f6" 
                                                        stroke-width="8" 
                                                        class="bbai-circular-progress-bg" />
                                                    <!-- Progress circle -->
                                                    <circle 
                                                        cx="70" 
                                                        cy="70" 
                                                        r="<?php echo esc_attr($agency_radius); ?>" 
                                                        fill="none"
                                                        stroke="url(#<?php echo esc_attr($agency_gradient_id); ?>)"
                                                        stroke-width="8"
                                                    stroke-linecap="round"
                                                        stroke-dasharray="<?php echo esc_attr($agency_circumference); ?>"
                                                        stroke-dashoffset="<?php echo esc_attr($agency_stroke_dashoffset); ?>"
                                                        class="bbai-circular-progress-bar"
                                                        data-circumference="<?php echo esc_attr($agency_circumference); ?>"
                                                        data-offset="<?php echo esc_attr($agency_stroke_dashoffset); ?>"
                                                        transform="rotate(-90 70 70)" />
                                                </svg>
                                                <div class="bbai-circular-progress-text">
                                                    <div class="bbai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                                    <div class="bbai-circular-progress-label"><?php esc_html_e('Credits Used', 'wp-alt-text-plugin'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else : ?>
                                <!-- Standard vertical layout -->
                                <h3 class="bbai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'wp-alt-text-plugin'); ?></h3>
                                <div class="bbai-usage-ring-wrapper">
                                    <div class="bbai-circular-progress" data-percentage="<?php echo esc_attr($percentage); ?>">
                                        <svg class="bbai-circular-progress-svg" viewBox="0 0 120 120">
                                            <defs>
                                                <linearGradient id="<?php echo esc_attr($gradient_id); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                    <stop offset="0%" style="stop-color:#9b5cff;stop-opacity:1" />
                                                    <stop offset="100%" style="stop-color:#7c3aed;stop-opacity:1" />
                                                </linearGradient>
                                            </defs>
                                            <!-- Background circle -->
                                            <circle 
                                                cx="60" 
                                                cy="60" 
                                                r="<?php echo esc_attr($radius); ?>" 
                                                fill="none" 
                                                stroke="#f3f4f6" 
                                                stroke-width="12" 
                                                class="bbai-circular-progress-bg" />
                                            <!-- Progress circle -->
                                            <circle 
                                                cx="60" 
                                                cy="60" 
                                                r="<?php echo esc_attr($radius); ?>" 
                                                fill="none"
                                                stroke="url(#<?php echo esc_attr($gradient_id); ?>)"
                                                stroke-width="12"
                                                stroke-linecap="round"
                                                stroke-dasharray="<?php echo esc_attr($circumference); ?>"
                                                stroke-dashoffset="<?php echo esc_attr($stroke_dashoffset); ?>"
                                                class="bbai-circular-progress-bar"
                                                data-circumference="<?php echo esc_attr($circumference); ?>"
                                                data-offset="<?php echo esc_attr($stroke_dashoffset); ?>" />
                                        </svg>
                                        <div class="bbai-circular-progress-text">
                                            <div class="bbai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                            <div class="bbai-circular-progress-label"><?php esc_html_e('credits used', 'wp-alt-text-plugin'); ?></div>
                                        </div>
                                    </div>
                                    <button type="button" class="bbai-usage-tooltip" aria-label="<?php esc_attr_e('How quotas work', 'wp-alt-text-plugin'); ?>" title="<?php esc_attr_e('Your monthly quota resets on the first of each month. Upgrade to Pro for unlimited generations.', 'wp-alt-text-plugin'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                            <path d="M8 5V5.01M8 11H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="bbai-usage-details">
                                    <div class="bbai-usage-text">
                                        <strong><?php echo esc_html($usage_stats['used']); ?></strong> / <strong><?php echo esc_html($usage_stats['limit']); ?></strong>
                                    </div>
                                    <div class="bbai-usage-microcopy">
                                        <?php 
                                        $reset_date = $usage_stats['reset_date'] ?? '';
                                        if (!empty($reset_date)) {
                                            // Format as "resets MONTH DAY, YEAR"
                                            $reset_timestamp = strtotime($reset_date);
                                            if ($reset_timestamp !== false) {
                                                $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                                printf(
                                                    esc_html__('Resets %s', 'wp-alt-text-plugin'),
                                                    esc_html($formatted_date)
                                                );
                                            } else {
                                                printf(
                                                    esc_html__('Resets %s', 'wp-alt-text-plugin'),
                                                    esc_html($reset_date)
                                                );
                                            }
                                        } else {
                                            esc_html_e('Resets monthly', 'wp-alt-text-plugin');
                                        }
                                        ?>
                                    </div>
                                    <?php 
                                    $plan_slug = $usage_stats['plan'] ?? 'free';
                                    $billing_portal = BbAI_Usage_Tracker::get_billing_portal_url();
                                    $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                                    ?>
                                    <?php if (!$is_pro) : ?>
                                    <a href="#" class="bbai-usage-upgrade-link" data-action="show-upgrade-modal">
                                            <?php esc_html_e('Upgrade for unlimited images', 'wp-alt-text-plugin'); ?> →
                                    </a>
                                    <?php elseif (!empty($billing_portal)) : ?>
                                        <a href="#" class="bbai-usage-billing-link" data-action="open-billing-portal">
                                            <?php esc_html_e('Manage billing & invoices', 'wp-alt-text-plugin'); ?> →
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Premium Upsell Card -->
                            <?php if (!$is_agency) : ?>
                            <div class="bbai-premium-card bbai-upsell-card">
                                <h3 class="bbai-upsell-title"><?php esc_html_e('Upgrade to Pro — Unlock Unlimited AI Power', 'wp-alt-text-plugin'); ?></h3>
                                <ul class="bbai-upsell-features">
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Unlimited image generations', 'wp-alt-text-plugin'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Priority queue processing', 'wp-alt-text-plugin'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Bulk optimisation for large libraries', 'wp-alt-text-plugin'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Multilingual AI alt text', 'wp-alt-text-plugin'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Faster & more descriptive alt text from improved Vision models', 'wp-alt-text-plugin'); ?>
                                    </li>
                                </ul>
                                <button type="button" class="bbai-upsell-cta bbai-upsell-cta--large" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Pro or Agency', 'wp-alt-text-plugin'); ?>
                                </button>
                                <p class="bbai-upsell-microcopy">
                                    <?php esc_html_e('Save 15+ hours/month with automated SEO alt generation.', 'wp-alt-text-plugin'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats Cards Row -->
                        <?php
                        $alt_texts_generated = $usage_stats['used'] ?? 0;
                        $minutes_per_alt_text = 2.5;
                        $hours_saved = round(($alt_texts_generated * $minutes_per_alt_text) / 60, 1);
                        $total_images = $stats['total'] ?? 0;
                        $optimized = $stats['with_alt'] ?? 0;
                        $remaining_images = $stats['missing'] ?? 0;
                        $coverage_percent = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;
                        ?>
                        <div class="bbai-premium-metrics-grid">
                            <!-- Time Saved Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value"><?php echo esc_html($hours_saved); ?> hrs</div>
                                <div class="bbai-metric-label"><?php esc_html_e('TIME SAVED', 'wp-alt-text-plugin'); ?></div>
                                <div class="bbai-metric-description"><?php esc_html_e('vs manual optimisation', 'wp-alt-text-plugin'); ?></div>
                            </div>
                            
                            <!-- Images Optimized Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value"><?php echo esc_html($optimized); ?></div>
                                <div class="bbai-metric-label"><?php esc_html_e('IMAGES OPTIMIZED', 'wp-alt-text-plugin'); ?></div>
                                <div class="bbai-metric-description"><?php esc_html_e('with generated alt text', 'wp-alt-text-plugin'); ?></div>
                            </div>
                            
                            <!-- Estimated SEO Impact Card -->
                            <div class="bbai-premium-card bbai-metric-card">
                                <div class="bbai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="bbai-metric-value"><?php echo esc_html($coverage_percent); ?>%</div>
                                <div class="bbai-metric-label"><?php esc_html_e('ESTIMATED SEO IMPACT', 'wp-alt-text-plugin'); ?></div>
                            </div>
                        </div>
                        
                        <!-- Site-Wide Licensing Notice -->
                        <div class="bbai-premium-card bbai-info-notice">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#0ea5e9" stroke-width="1.5" fill="none"/>
                                <path d="M10 6V10M10 14H10.01" stroke="#0ea5e9" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>
                                <?php 
                                $site_name = trim(get_bloginfo('name'));
                                $site_label = $site_name !== '' ? $site_name : __('this WordPress site', 'wp-alt-text-plugin');
                                printf(
                                    esc_html__('Quota shared across all users on %s', 'wp-alt-text-plugin'),
                                    '<strong>' . esc_html($site_label) . '</strong>'
                                );
                                ?>
                            </span>
                        </div>

                        <!-- Image Optimization Card (Full Width Pill) -->
                        <?php
                        $total_images = $stats['total'] ?? 0;
                        $optimized = $stats['with_alt'] ?? 0;
                        $remaining_imgs = $stats['missing'] ?? 0;
                        $coverage_pct = $total_images > 0 ? round(($optimized / $total_images) * 100) : 0;

                        // Check if user has quota remaining (for free users) or is on pro/agency plan
                        $plan = $usage_stats['plan'] ?? 'free';
                        $has_quota = ($usage_stats['remaining'] ?? 0) > 0;
                        $is_premium = in_array($plan, ['pro', 'agency'], true);
                        $can_generate = $has_quota || $is_premium;
                        ?>
                        <div class="bbai-premium-card bbai-optimization-card <?php echo esc_attr(($total_images > 0 && $remaining_imgs === 0) ? 'bbai-optimization-card--complete' : ''); ?>">
                            <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                <div class="bbai-optimization-accent-bar"></div>
                            <?php endif; ?>
                            <div class="bbai-optimization-header">
                                <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                    <div class="bbai-optimization-success-chip">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                            <path d="M13 4L6 11L3 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h2 class="bbai-optimization-title">
                                    <?php 
                                    if ($total_images > 0) {
                                        if ($remaining_imgs > 0) {
                                            printf(
                                                esc_html__('%1$d of %2$d images optimized', 'wp-alt-text-plugin'),
                                                $optimized,
                                                $total_images
                                            );
                                        } else {
                                            // Success chip with checkmark icon is already shown above
                                            printf(
                                                esc_html__('All %1$d images optimized!', 'wp-alt-text-plugin'),
                                                $total_images
                                            );
                                        }
                                    } else {
                                        esc_html_e('Ready to optimize images', 'wp-alt-text-plugin');
                                    }
                                    ?>
                                </h2>
                            </div>
                            
                            <?php if ($total_images > 0) : ?>
                                <div class="bbai-optimization-progress">
                                    <div class="bbai-optimization-progress-bar">
                                        <div class="bbai-optimization-progress-fill" style="width: <?php echo esc_attr($coverage_pct); ?>%; background: <?php echo esc_attr(($remaining_imgs === 0) ? '#10b981' : '#9b5cff'); ?>;"></div>
                                    </div>
                                    <div class="bbai-optimization-stats">
                                        <div class="bbai-optimization-stat">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Optimized', 'wp-alt-text-plugin'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($optimized); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Remaining', 'wp-alt-text-plugin'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($remaining_imgs); ?></span>
                                        </div>
                                        <div class="bbai-optimization-stat">
                                            <span class="bbai-optimization-stat-label"><?php esc_html_e('Total', 'wp-alt-text-plugin'); ?></span>
                                            <span class="bbai-optimization-stat-value"><?php echo esc_html($total_images); ?></span>
                                        </div>
                                    </div>
                                    <div class="bbai-optimization-actions">
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--primary <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="generate-missing" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock unlimited alt text with Pro →', 'wp-alt-text-plugin') . '"' : ''; ?>>
                                            <?php if (!$can_generate) : ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'wp-alt-text-plugin'); ?></span>
                                            <?php else : ?>
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'wp-alt-text-plugin'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="bbai-optimization-cta bbai-optimization-cta--secondary <?php echo esc_attr((!$can_generate) ? 'bbai-optimization-cta--locked' : ''); ?>" data-action="regenerate-all" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock unlimited alt text with Pro →', 'wp-alt-text-plugin') . '"' : ''; ?>>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-btn-icon">
                                                <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Re-optimize All Alt Text', 'wp-alt-text-plugin'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="bbai-optimization-empty">
                                    <p><?php esc_html_e('Upload images to your WordPress Media Library to get started with AI-powered alt text generation.', 'wp-alt-text-plugin'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-btn-primary">
                                        <?php esc_html_e('Go to Media Library', 'wp-alt-text-plugin'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Footer Cross-Sell -->
                        <div class="bbai-premium-footer-cta">
                            <p class="bbai-footer-cta-text">
                                <?php esc_html_e('Complete your SEO stack', 'wp-alt-text-plugin'); ?> 
                                <span class="bbai-footer-cta-link bbai-footer-cta-link--coming-soon">
                                    <?php esc_html_e('Try our SEO Meta Generator AI', 'wp-alt-text-plugin'); ?>
                                    <span class="bbai-footer-cta-badge-new"><?php esc_html_e('New', 'wp-alt-text-plugin'); ?></span>
                                    <span class="bbai-footer-cta-badge-coming-soon"><?php esc_html_e('Coming Soon', 'wp-alt-text-plugin'); ?></span>
                                </span>
                                <span class="bbai-footer-cta-badge"><?php esc_html_e('(included in free plan)', 'wp-alt-text-plugin'); ?></span>
                            </p>
                        </div>
                        
                        <!-- Powered by OpttiAI -->
                        <div class="bbai-premium-footer-divider"></div>
                        <div class="bbai-premium-footer-branding">
                            <span><?php esc_html_e('Powered by', 'wp-alt-text-plugin'); ?> <strong>OpttiAI</strong></span>
                        </div>
                        
                        <!-- Circular Progress Animation Script -->
                        <script>
                        (function() {
                            function initProgressRings() {
                                var rings = document.querySelectorAll('.bbai-circular-progress-bar[data-offset]');
                                rings.forEach(function(ring) {
                                    var circumference = parseFloat(ring.getAttribute('data-circumference'));
                                    var targetOffset = parseFloat(ring.getAttribute('data-offset'));
                                    
                                    if (!isNaN(circumference) && !isNaN(targetOffset)) {
                                        // Start from full (hidden)
                                        ring.style.strokeDashoffset = circumference;
                                        ring.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1)';
                                        
                                        // Animate to target
                                        requestAnimationFrame(function() {
                                            ring.style.strokeDashoffset = targetOffset;
                                        });
                                    }
                                });
                            }
                            
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', initProgressRings);
                            } else {
                                setTimeout(initProgressRings, 50);
                            }
                        })();
                        </script>
                    <?php else : ?>
                        <!-- Not Authenticated - Demo Preview (Using Real Dashboard Structure) -->
                        <div class="bbai-demo-preview">
                            <!-- Demo Badge Overlay -->
                            <div class="bbai-demo-badge-overlay">
                                <span class="bbai-demo-badge-text"><?php esc_html_e('DEMO PREVIEW', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            
                            <!-- Usage Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-dashboard-card--featured bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('USAGE STATUS', 'wp-alt-text-plugin'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('0 of 50 image descriptions generated this month.', 'wp-alt-text-plugin'); ?>
                                    </h2>
                                    <p style="margin: 12px 0 0 0; font-size: 14px; color: #6b7280;">
                                        <?php esc_html_e('Sign in to track your usage and access premium features.', 'wp-alt-text-plugin'); ?>
                                    </p>
                                </div>
                                
                                <div class="bbai-dashboard-usage-bar">
                                    <div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="bbai-dashboard-usage-stats">
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Used', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Remaining', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value">50</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Resets', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value"><?php echo esc_html(date_i18n('F j, Y', strtotime('first day of next month'))); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Saved Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-time-saved-card bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('TIME SAVED', 'wp-alt-text-plugin'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">⏱️</span>
                                        <?php esc_html_e('Ready to optimize your images', 'wp-alt-text-plugin'); ?>
                                    </h2>
                                    <p class="bbai-seo-impact" style="margin-top: 8px; font-size: 14px; color: #6b7280;"><?php esc_html_e('Start generating alt text to improve SEO and accessibility', 'wp-alt-text-plugin'); ?></p>
                                </div>
                            </div>

                            <!-- Image Optimization Card (Demo) -->
                            <div class="bbai-dashboard-card bbai-demo-mode">
                                <div class="bbai-dashboard-card-header">
                                    <div class="bbai-dashboard-card-badge"><?php esc_html_e('IMAGE OPTIMIZATION', 'wp-alt-text-plugin'); ?></div>
                                    <h2 class="bbai-dashboard-card-title">
                                        <span class="bbai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('Ready to optimize images', 'wp-alt-text-plugin'); ?>
                                    </h2>
                                </div>
                                
                                <div class="bbai-dashboard-usage-bar">
                                    <div class="bbai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="bbai-dashboard-usage-stats">
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Optimized', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Remaining', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value">—</span>
                                    </div>
                                    <div class="bbai-dashboard-usage-stat">
                                        <span class="bbai-dashboard-usage-label"><?php esc_html_e('Total', 'wp-alt-text-plugin'); ?></span>
                                        <span class="bbai-dashboard-usage-value">—</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo CTA -->
                            <div class="bbai-demo-cta">
                                <p class="bbai-demo-cta-text"><?php esc_html_e('✨ Sign up now to start generating alt text for your images!', 'wp-alt-text-plugin'); ?></p>
                                <button type="button" class="bbai-btn-primary bbai-btn-icon" id="bbai-demo-signup-btn">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Get Started Free', 'wp-alt-text-plugin'); ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endif; // End is_authenticated check for usage/stats cards ?>

                    <?php if ($this->api_client->is_authenticated() && ($usage_stats['remaining'] ?? 0) <= 0) : ?>
                        <div class="bbai-limit-reached">
                            <div class="bbai-limit-header-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3 class="bbai-limit-title"><?php esc_html_e('Monthly limit reached — keep the momentum going!', 'wp-alt-text-plugin'); ?></h3>
                            <p class="bbai-limit-description">
                                <?php
                                    $reset_label = $usage_stats['reset_date'] ?? '';
                                    printf(
                                        esc_html__('You\'ve used all %1$d free generations this month. Your quota resets on %2$s.', 'wp-alt-text-plugin'),
                                        $usage_stats['limit'],
                                        esc_html($reset_label)
                                    );
                                ?>
                            </p>

                            <div class="bbai-countdown" data-countdown="<?php echo esc_attr($usage_stats['seconds_until_reset'] ?? 0); ?>" data-reset-timestamp="<?php echo esc_attr($usage_stats['reset_timestamp'] ?? 0); ?>">
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-days>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('days', 'wp-alt-text-plugin'); ?></div>
                                </div>
                                <div class="bbai-countdown-separator">—</div>
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-hours>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('hours', 'wp-alt-text-plugin'); ?></div>
                                </div>
                                <div class="bbai-countdown-separator">—</div>
                                <div class="bbai-countdown-item">
                                    <div class="bbai-countdown-number" data-minutes>0</div>
                                    <div class="bbai-countdown-label"><?php esc_html_e('mins', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>

                            <div class="bbai-limit-cta">
                                <button type="button" class="bbai-limit-upgrade-btn bbai-limit-upgrade-btn--full" data-action="show-upgrade-modal" data-upgrade-source="upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Testimonial Block -->
                    <div class="bbai-testimonials-grid">
                        <div class="bbai-testimonial-block">
                            <div class="bbai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
                            <blockquote class="bbai-testimonial-quote">
                                <?php esc_html_e('"Generated 1,200 alt texts for our agency in minutes."', 'wp-alt-text-plugin'); ?>
                            </blockquote>
                            <div class="bbai-testimonial-author-wrapper">
                                <div class="bbai-testimonial-avatar">SW</div>
                                <cite class="bbai-testimonial-author"><?php esc_html_e('Sarah W.', 'wp-alt-text-plugin'); ?></cite>
                            </div>
                        </div>
                        <div class="bbai-testimonial-block">
                            <div class="bbai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
                            <blockquote class="bbai-testimonial-quote">
                                <?php esc_html_e('"We automated 4,800 alt text entries for our WooCommerce store."', 'wp-alt-text-plugin'); ?>
                            </blockquote>
                            <div class="bbai-testimonial-author-wrapper">
                                <div class="bbai-testimonial-avatar">MP</div>
                                <cite class="bbai-testimonial-author"><?php esc_html_e('Martin P.', 'wp-alt-text-plugin'); ?></cite>
                            </div>
                        </div>
                    </div>

                    </div> <!-- End Dashboard Container -->
                    </div> <!-- End Premium Dashboard -->
                    </div> <!-- End Tab Content: Dashboard -->

            <?php elseif ($tab === 'library' && $this->api_client->is_authenticated()) : ?>
                <!-- ALT Library Table -->
                <?php
                // Pagination setup
                $per_page = 10;
                $alt_page_raw = isset($_GET['alt_page']) ? wp_unslash($_GET['alt_page']) : '';
                $current_page = max(1, absint($alt_page_raw));
                $offset = ($current_page - 1) * $per_page;
                
                // Get all images (with or without alt text) for proper filtering
                global $wpdb;
                
                // Get total count of all images
                $total_images = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
                    'attachment', 'image/%'
                ));
                
                // Get images with alt text count
                $with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_mime_type LIKE %s
                     AND p.post_status = %s
                     AND pm.meta_key = %s
                     AND TRIM(pm.meta_value) <> ''",
                    'attachment',
                    'image/%',
                    'inherit',
                    '_wp_attachment_image_alt'
                ));
                
                // Calculate optimization percentage
                $optimization_percentage = $total_images > 0 ? round(($with_alt_count / $total_images) * 100) : 0;
                
                // Get all images with their alt text status
                $all_images = $wpdb->get_results($wpdb->prepare("
                    SELECT p.*, 
                           COALESCE(pm.meta_value, '') as alt_text,
                           CASE WHEN pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> '' THEN 1 ELSE 0 END as has_alt
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND p.post_status = 'inherit'
                    ORDER BY p.post_date DESC
                    LIMIT %d OFFSET %d
                ", $per_page, $offset));
                
                $total_count = $total_images;
                $image_count = count($all_images);
                $total_pages = ceil($total_count / $per_page);
                $optimized_images = $all_images; // Use all_images for the table
                
                // Get plan info for upgrade card - check license first
                $has_license = $this->api_client->has_active_license();
                $license_data = $this->api_client->get_license_data();
                $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
                
                // If using license, check license plan
                if ($has_license && $license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    if ($license_plan !== 'free') {
                        $plan_slug = $license_plan;
                    }
                }
                
                $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                $is_agency = ($plan_slug === 'agency');
                
                ?>
                <div class="bbai-dashboard-container">
                    <!-- Header Section -->
                    <div class="bbai-library-header">
                        <h1 class="bbai-library-title"><?php esc_html_e('ALT Library', 'wp-alt-text-plugin'); ?></h1>
                        <p class="bbai-library-subtitle"><?php esc_html_e('Browse, search, and regenerate AI-generated alt text for images in your media library.', 'wp-alt-text-plugin'); ?></p>
                        
                        <!-- Optimization Notice -->
                        <?php if ($optimization_percentage >= 100) : ?>
                            <div class="bbai-library-success-notice">
                                <span class="bbai-library-success-text">
                                    <?php esc_html_e('100% of your library is fully optimized — great progress!', 'wp-alt-text-plugin'); ?>
                                </span>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="bbai-library-success-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Pro or Agency', 'wp-alt-text-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="bbai-library-notice">
                                <?php 
                                printf(
                                    esc_html__('%1$d%% of your library is fully optimized — great progress!', 'wp-alt-text-plugin'),
                                    $optimization_percentage
                                );
                                ?>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="bbai-library-notice-link" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Pro or Agency', 'wp-alt-text-plugin'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filters Row -->
                    <div class="bbai-library-controls">
                        <div class="bbai-library-search-wrapper">
                            <svg class="bbai-library-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="text" 
                                   id="bbai-library-search" 
                                   class="bbai-library-search-input" 
                                   placeholder="<?php esc_attr_e('Search images or alt text…', 'wp-alt-text-plugin'); ?>"
                            />
                        </div>
                        <select id="bbai-status-filter" class="bbai-library-filter-select">
                            <option value="all"><?php esc_html_e('All', 'wp-alt-text-plugin'); ?></option>
                            <option value="optimized"><?php esc_html_e('Optimized', 'wp-alt-text-plugin'); ?></option>
                            <option value="missing"><?php esc_html_e('Missing ALT', 'wp-alt-text-plugin'); ?></option>
                            <option value="errors"><?php esc_html_e('Errors', 'wp-alt-text-plugin'); ?></option>
                        </select>
                        <select id="bbai-time-filter" class="bbai-library-filter-select">
                            <option value="this-month"><?php esc_html_e('This month', 'wp-alt-text-plugin'); ?></option>
                            <option value="last-month"><?php esc_html_e('Last month', 'wp-alt-text-plugin'); ?></option>
                            <option value="all-time"><?php esc_html_e('All time', 'wp-alt-text-plugin'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Table Card - Full Width -->
                    <div class="bbai-library-table-card<?php echo esc_attr($is_agency ? ' bbai-library-table-card--full-width' : ''); ?>">
                        <div class="bbai-table-scroll">
                            <table class="bbai-library-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('IMAGE', 'wp-alt-text-plugin'); ?></th>
                                        <th><?php esc_html_e('STATUS', 'wp-alt-text-plugin'); ?></th>
                                        <th><?php esc_html_e('DATE', 'wp-alt-text-plugin'); ?></th>
                                        <th><?php esc_html_e('ALT TEXT', 'wp-alt-text-plugin'); ?></th>
                                        <th><?php esc_html_e('ACTIONS', 'wp-alt-text-plugin'); ?></th>
                                    </tr>
                                </thead>
                        <tbody>
                            <?php if (!empty($optimized_images)) : ?>
                                <?php $row_index = 0; ?>
                                <?php foreach ($optimized_images as $image) : ?>
                                    <?php
                                    $attachment_id = $image->ID;
                                    $old_alt = get_post_meta($attachment_id, '_beepbeepbeepbeepai_original', true) ?: '';
                                    if (!$old_alt) {
                                        // Backward compatibility - check old key and migrate
                                        $old_alt = get_post_meta($attachment_id, '_beepbeepai_original', true) ?: '';
                                        if ($old_alt) {
                                            update_post_meta($attachment_id, '_beepbeepbeepbeepai_original', $old_alt);
                                            delete_post_meta($attachment_id, '_beepbeepai_original');
                                        }
                                    }
                                    $current_alt = $image->alt_text ?? get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                                    $thumb_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                                    $attachment_title_raw = get_the_title($attachment_id);
                                    $attachment_title = is_string($attachment_title_raw) ? $attachment_title_raw : '';
                                    $edit_link = get_edit_post_link($attachment_id, '');
                                    
                                    $clean_current_alt = is_string($current_alt) ? trim($current_alt) : '';
                                    $clean_old_alt = is_string($old_alt) ? trim($old_alt) : '';
                                    $has_alt = !empty($clean_current_alt);
                                    
                                    $status_key = $has_alt ? 'optimized' : 'missing';
                                    $status_label = $has_alt ? __('✅ Optimized', 'wp-alt-text-plugin') : __('🟠 Missing', 'wp-alt-text-plugin');
                                    if ($has_alt && $clean_old_alt && strcasecmp($clean_old_alt, $clean_current_alt) !== 0) {
                                        $status_key = 'regenerated';
                                        $status_label = __('🔁 Regenerated', 'wp-alt-text-plugin');
                                    }
                                    
                                    // Get the date alt text was generated (use modified date)
                                    $post = get_post($attachment_id);
                                    $modified_date = $post ? get_the_modified_date('M j, Y', $post) : '';
                                    $modified_time = $post ? get_the_modified_time('g:i a', $post) : '';

                                    $row_index++;
                                    
                                    // Truncate alt text to 55 characters
                                    $truncated_alt = $clean_current_alt;
                                    if (strlen($truncated_alt) > 55) {
                                        $truncated_alt = substr($truncated_alt, 0, 55) . '...';
                                    }
                                    
                                    // Status badge class names for CSS styling
                                    $status_class = 'bbai-status-badge';
                                    if ($status_key === 'optimized') {
                                        $status_class .= ' bbai-status-badge--optimized';
                                    } elseif ($status_key === 'missing') {
                                        $status_class .= ' bbai-status-badge--missing';
                                    } else {
                                        $status_class .= ' bbai-status-badge--regenerated';
                                    }
                                    ?>
                                    <tr class="bbai-library-row" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-status="<?php echo esc_attr($status_key); ?>">
                                        <td class="bbai-library-cell bbai-library-cell--image">
                                            <?php if ($thumb_url) : ?>
                                                <img src="<?php echo esc_url($thumb_url[0]); ?>" alt="<?php echo esc_attr($attachment_title); ?>" class="bbai-library-thumbnail" />
                                            <?php else : ?>
                                                <div class="bbai-library-thumbnail-placeholder">
                                                    <?php esc_html_e('—', 'wp-alt-text-plugin'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--status">
                                            <span class="<?php echo esc_attr($status_class); ?>">
                                                <?php if ($status_key === 'optimized') : ?>
                                                    <?php esc_html_e('Optimised', 'wp-alt-text-plugin'); ?>
                                                <?php elseif ($status_key === 'missing') : ?>
                                                    <?php esc_html_e('Missing', 'wp-alt-text-plugin'); ?>
                                                <?php else : ?>
                                                    <?php esc_html_e('Regenerated', 'wp-alt-text-plugin'); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--date">
                                            <?php echo esc_html($modified_date ?: '—'); ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--alt-text new-alt-cell-<?php echo esc_attr($attachment_id); ?>">
                                            <?php if ($has_alt) : ?>
                                                <div class="bbai-library-alt-text" title="<?php echo esc_attr($clean_current_alt); ?>">
                                                    <?php echo esc_html($truncated_alt); ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="bbai-library-no-alt"><?php esc_html_e('No alt text', 'wp-alt-text-plugin'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="bbai-library-cell bbai-library-cell--actions">
                                            <?php 
                                            $is_local_dev = defined('WP_LOCAL_DEV') && WP_LOCAL_DEV;
                                            $can_regenerate = $is_local_dev || $this->api_client->is_authenticated();
                                            ?>
                                            <button type="button" 
                                                    class="bbai-btn-regenerate" 
                                                    data-action="regenerate-single" 
                                                    data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                                    <?php echo !$can_regenerate ? 'disabled title="' . esc_attr__('Please log in to regenerate alt text', 'wp-alt-text-plugin') . '"' : ''; ?>>
                                                <?php esc_html_e('Regenerate', 'wp-alt-text-plugin'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="bbai-library-empty-state">
                                        <div class="bbai-library-empty-content">
                                            <div class="bbai-library-empty-title">
                                                <?php esc_html_e('No images found', 'wp-alt-text-plugin'); ?>
                                            </div>
                                            <div class="bbai-library-empty-subtitle">
                                                <?php esc_html_e('Upload images to your Media Library to get started.', 'wp-alt-text-plugin'); ?>
                                            </div>
                                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="bbai-library-empty-btn">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                                <?php esc_html_e('Upload Images', 'wp-alt-text-plugin'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="bbai-pagination">
                            <div class="bbai-pagination-info">
                                <?php 
                                $start = $offset + 1;
                                $end = min($offset + $per_page, $total_count);
                                printf(
                                    esc_html__('Showing %1$d-%2$d of %3$d images', 'wp-alt-text-plugin'),
                                    $start,
                                    $end,
                                    $total_count
                                );
                                ?>
                            </div>
                            
                            <div class="bbai-pagination-controls">
                                <?php if ($current_page > 1) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', 1)); ?>" class="bbai-pagination-btn bbai-pagination-btn--first" title="<?php esc_attr_e('First page', 'wp-alt-text-plugin'); ?>">
                                        <?php esc_html_e('First', 'wp-alt-text-plugin'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page - 1)); ?>" class="bbai-pagination-btn bbai-pagination-btn--prev" title="<?php esc_attr_e('Previous page', 'wp-alt-text-plugin'); ?>">
                                        <?php esc_html_e('Previous', 'wp-alt-text-plugin'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e('First', 'wp-alt-text-plugin'); ?></span>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e('Previous', 'wp-alt-text-plugin'); ?></span>
                                <?php endif; ?>
                                
                                <div class="bbai-pagination-pages">
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="bbai-pagination-btn">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="bbai-pagination-ellipsis">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        if ($i == $current_page) {
                                            echo '<span class="bbai-pagination-btn bbai-pagination-btn--current">' . esc_html($i) . '</span>';
                                        } else {
                                            echo '<a href="' . esc_url(add_query_arg('alt_page', $i)) . '" class="bbai-pagination-btn">' . esc_html($i) . '</a>';
                                        }
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="bbai-pagination-ellipsis">...</span>';
                                        }
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', $total_pages)) . '" class="bbai-pagination-btn">' . esc_html($total_pages) . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($current_page < $total_pages) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page + 1)); ?>" class="bbai-pagination-btn bbai-pagination-btn--next" title="<?php esc_attr_e('Next page', 'wp-alt-text-plugin'); ?>">
                                        <?php esc_html_e('Next', 'wp-alt-text-plugin'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $total_pages)); ?>" class="bbai-pagination-btn bbai-pagination-btn--last" title="<?php esc_attr_e('Last page', 'wp-alt-text-plugin'); ?>">
                                        <?php esc_html_e('Last', 'wp-alt-text-plugin'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e('Next', 'wp-alt-text-plugin'); ?></span>
                                    <span class="bbai-pagination-btn bbai-pagination-btn--disabled"><?php esc_html_e('Last', 'wp-alt-text-plugin'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                                </div>
                    </div> <!-- End Table Card -->
                    
                    <!-- Upgrade Card - Full Width (hidden only for Agency, visible for Pro) -->
                    <?php if (!$is_agency) : ?>
                    <div class="bbai-library-upgrade-card">
                        <h3 class="bbai-library-upgrade-title">
                            <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?>
                        </h3>
                        <div class="bbai-library-upgrade-features">
                            <div class="bbai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Bulk ALT optimisation', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            <div class="bbai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited background queue', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            <div class="bbai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Smart tone tuning', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            <div class="bbai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority support', 'wp-alt-text-plugin'); ?></span>
                            </div>
                        </div>
                        <button type="button" 
                                class="bbai-library-upgrade-btn"
                                data-action="show-upgrade-modal">
                            <?php esc_html_e('View Plans', 'wp-alt-text-plugin'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    </div> <!-- End Dashboard Container -->
                
                <!-- Status for AJAX operations -->
                <div class="bbai-dashboard__status" data-progress-status role="status" aria-live="polite" style="display: none;"></div>
                

            </div>

<?php elseif ($tab === 'debug') : ?>
    <?php if (!$this->api_client->is_authenticated()) : ?>
        <!-- Debug Logs require authentication -->
        <div class="bbai-settings-required">
            <div class="bbai-settings-required-content">
                <div class="bbai-settings-required-icon">🔒</div>
                <h2><?php esc_html_e('Authentication Required', 'wp-alt-text-plugin'); ?></h2>
                <p><?php esc_html_e('Debug Logs are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'wp-alt-text-plugin'); ?></p>
                <p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
                    <?php esc_html_e('If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'wp-alt-text-plugin'); ?>
                </p>
                <button type="button" class="bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                    </svg>
                    <?php esc_html_e('Log In', 'wp-alt-text-plugin'); ?>
                </button>
            </div>
        </div>
    <?php elseif (!class_exists('BbAI_Debug_Log')) : ?>
        <div class="bbai-section">
            <div class="notice notice-warning">
                <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'wp-alt-text-plugin'); ?></p>
            </div>
        </div>
    <?php else : ?>
        <?php
            $debug_logs       = $debug_bootstrap['logs'] ?? [];
            $debug_stats      = $debug_bootstrap['stats'] ?? [];
            $debug_pagination = $debug_bootstrap['pagination'] ?? [];
            $debug_page       = max(1, intval($debug_pagination['page'] ?? 1));
            $debug_pages      = max(1, intval($debug_pagination['total_pages'] ?? 1));
            $debug_export_url = wp_nonce_url(
                admin_url('admin-post.php?action=bbai_debug_export'),
                'bbai_debug_export'
            );
            
            // Get plan info for upgrade card
            $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
        ?>
        <style>
            /* Responsive Debug Stats Grid */
            @media (max-width: 768px) {
                [data-bbai-debug-panel] .debug-stats-grid {
                    grid-template-columns: repeat(2, 1fr) !important;
                    gap: 16px !important;
                }
            }
            @media (max-width: 480px) {
                [data-bbai-debug-panel] .debug-stats-grid {
                    grid-template-columns: 1fr !important;
                    gap: 16px !important;
                }
            }
        </style>
        <div class="bbai-dashboard-container" data-bbai-debug-panel>
            <!-- Header Section -->
            <div class="bbai-debug-header">
                <h1 class="bbai-dashboard-title"><?php esc_html_e('Debug Logs', 'wp-alt-text-plugin'); ?></h1>
                <p class="bbai-dashboard-subtitle"><?php esc_html_e('Monitor API calls, queue events, and any errors generated by the plugin.', 'wp-alt-text-plugin'); ?></p>
            
                <!-- Usage Info -->
            <?php if (isset($usage_stats)) : ?>
                <div class="bbai-debug-usage-info">
                    <?php 
                    printf(
                        esc_html__('%1$d of %2$d image descriptions generated this month', 'wp-alt-text-plugin'),
                        $usage_stats['used'] ?? 0,
                        $usage_stats['limit'] ?? 0
                    ); 
                    ?>
                    <span class="bbai-debug-usage-separator">•</span>
                    <?php 
                    $reset_date = $usage_stats['reset_date'] ?? '';
                    if (!empty($reset_date)) {
                        $reset_timestamp = strtotime($reset_date);
                        if ($reset_timestamp !== false) {
                            $formatted_reset = date_i18n('F j, Y', $reset_timestamp);
                            printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($formatted_reset));
                        } else {
                            printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($reset_date));
                        }
                    }
                    ?>
            </div>
            <?php endif; ?>
            </div>

            <!-- Log Statistics Card -->
            <div class="bbai-debug-stats-card">
                <div class="bbai-debug-stats-header">
                    <div class="bbai-debug-stats-title">
                        <h3><?php esc_html_e('Log Statistics', 'wp-alt-text-plugin'); ?></h3>
                    </div>
                    <div class="bbai-debug-stats-actions">
                        <button type="button" class="bbai-debug-btn bbai-debug-btn--secondary" data-debug-clear>
                            <?php esc_html_e('Clear Logs', 'wp-alt-text-plugin'); ?>
                        </button>
                        <a href="<?php echo esc_url($debug_export_url); ?>" class="bbai-debug-btn bbai-debug-btn--primary">
                            <?php esc_html_e('Export CSV', 'wp-alt-text-plugin'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="bbai-debug-stats-grid">
                    <div class="bbai-debug-stat-item">
                        <div class="bbai-debug-stat-label"><?php esc_html_e('TOTAL LOGS', 'wp-alt-text-plugin'); ?></div>
                        <div class="bbai-debug-stat-value" data-debug-stat="total">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['total'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="bbai-debug-stat-item bbai-debug-stat-item--warning">
                        <div class="bbai-debug-stat-label"><?php esc_html_e('WARNINGS', 'wp-alt-text-plugin'); ?></div>
                        <div class="bbai-debug-stat-value bbai-debug-stat-value--warning" data-debug-stat="warnings">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['warnings'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="bbai-debug-stat-item bbai-debug-stat-item--error">
                        <div class="bbai-debug-stat-label"><?php esc_html_e('ERRORS', 'wp-alt-text-plugin'); ?></div>
                        <div class="bbai-debug-stat-value bbai-debug-stat-value--error" data-debug-stat="errors">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['errors'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="bbai-debug-stat-item">
                        <div class="bbai-debug-stat-label"><?php esc_html_e('LAST API EVENT', 'wp-alt-text-plugin'); ?></div>
                        <div class="bbai-debug-stat-value bbai-debug-stat-value--small" data-debug-stat="last_api">
                            <?php echo esc_html($debug_stats['last_api'] ?? '—'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Panel -->
            <div class="bbai-debug-filters-card">
                <form data-debug-filter class="bbai-debug-filters-form">
                    <div class="bbai-debug-filter-group">
                        <label for="bbai-debug-level" class="bbai-debug-filter-label">
                            <?php esc_html_e('Level', 'wp-alt-text-plugin'); ?>
                        </label>
                        <select id="bbai-debug-level" name="level" class="bbai-debug-filter-input">
                            <option value=""><?php esc_html_e('All levels', 'wp-alt-text-plugin'); ?></option>
                            <option value="info"><?php esc_html_e('Info', 'wp-alt-text-plugin'); ?></option>
                            <option value="warning"><?php esc_html_e('Warning', 'wp-alt-text-plugin'); ?></option>
                            <option value="error"><?php esc_html_e('Error', 'wp-alt-text-plugin'); ?></option>
                        </select>
                    </div>
                    <div class="bbai-debug-filter-group">
                        <label for="bbai-debug-date" class="bbai-debug-filter-label">
                            <?php esc_html_e('Date', 'wp-alt-text-plugin'); ?>
                        </label>
                        <input type="date" id="bbai-debug-date" name="date" class="bbai-debug-filter-input" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="bbai-debug-filter-group bbai-debug-filter-group--search">
                        <label for="bbai-debug-search" class="bbai-debug-filter-label">
                            <?php esc_html_e('Search', 'wp-alt-text-plugin'); ?>
                        </label>
                        <div class="bbai-debug-search-wrapper">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-debug-search-icon">
                                <circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="search" id="bbai-debug-search" name="search" placeholder="<?php esc_attr_e('Search logs…', 'wp-alt-text-plugin'); ?>" class="bbai-debug-filter-input bbai-debug-filter-input--search">
                        </div>
                    </div>
                    <div class="bbai-debug-filter-actions">
                        <button type="submit" class="bbai-debug-btn bbai-debug-btn--primary">
                            <?php esc_html_e('Apply', 'wp-alt-text-plugin'); ?>
                        </button>
                        <button type="button" class="bbai-debug-btn bbai-debug-btn--ghost" data-debug-reset>
                            <?php esc_html_e('Reset', 'wp-alt-text-plugin'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table Card -->
            <div class="bbai-debug-table-card">
                <table class="bbai-debug-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('TIMESTAMP', 'wp-alt-text-plugin'); ?></th>
                            <th><?php esc_html_e('LEVEL', 'wp-alt-text-plugin'); ?></th>
                            <th><?php esc_html_e('MESSAGE', 'wp-alt-text-plugin'); ?></th>
                            <th><?php esc_html_e('CONTEXT', 'wp-alt-text-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-debug-rows>
                        <?php if (!empty($debug_logs)) : ?>
                            <?php 
                            $row_index = 0;
                            foreach ($debug_logs as $log) :
                                $row_index++;
                                $level = strtolower($log['level'] ?? 'info');
                                $level_slug = preg_replace('/[^a-z0-9_-]/i', '', $level) ?: 'info';
                                $context_attr = '';
                                $context_source = $log['context'] ?? [];
                                if (!empty($context_source)) {
                                    if (is_array($context_source)) {
                                        $json = wp_json_encode($context_source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                        $context_attr = base64_encode($json);
                                    } else {
                                        $context_str = (string) $context_source;
                                        $decoded = json_decode($context_str, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $json = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                            $context_attr = base64_encode($json);
                                        } else {
                                            $context_attr = base64_encode($context_str);
                                        }
                                    }
                                }
                                
                                // Format date: "Nov 13, 2025 — 12:45 PM"
                                $created_at = $log['created_at'] ?? '';
                                $formatted_date = $created_at;
                                if ($created_at) {
                                    $timestamp = strtotime($created_at);
                                    if ($timestamp !== false) {
                                        $formatted_date = date('M j, Y — g:i A', $timestamp);
                                    }
                                }
                                
                                // Severity badge classes
                                $badge_class = 'bbai-debug-badge bbai-debug-badge--' . esc_attr($level_slug);
                            ?>
                            <tr class="bbai-debug-table-row" data-row-index="<?php echo esc_attr($row_index); ?>">
                                <td class="bbai-debug-table-cell bbai-debug-table-cell--timestamp">
                                    <?php echo esc_html($formatted_date); ?>
                                </td>
                                <td class="bbai-debug-table-cell bbai-debug-table-cell--level">
                                    <span class="<?php echo esc_attr($badge_class); ?>">
                                        <span class="bbai-debug-badge-text"><?php echo esc_html(ucfirst($level_slug)); ?></span>
                                    </span>
                                </td>
                                <td class="bbai-debug-table-cell bbai-debug-table-cell--message">
                                    <?php echo esc_html($log['message'] ?? ''); ?>
                                </td>
                                <td class="bbai-debug-table-cell bbai-debug-table-cell--context">
                                    <?php if ($context_attr) : ?>
                                        <button type="button" 
                                                class="bbai-debug-context-btn" 
                                                data-context-data="<?php echo esc_attr($context_attr); ?>"
                                                data-row-index="<?php echo esc_attr($row_index); ?>">
                                            <?php esc_html_e('Log Context', 'wp-alt-text-plugin'); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="bbai-debug-context-empty">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($context_attr) : ?>
                            <tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr($row_index); ?>" style="display: none;">
                                <td colspan="4" class="bbai-debug-context-cell">
                                    <div class="bbai-debug-context-content">
                                        <pre class="bbai-debug-context-json"></pre>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" class="bbai-debug-table-empty">
                                    <?php esc_html_e('No logs recorded yet.', 'wp-alt-text-plugin'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($debug_pages > 1) : ?>
            <div class="bbai-debug-pagination">
                <button type="button" class="bbai-debug-pagination-btn" data-debug-page="prev" <?php disabled($debug_page <= 1); ?>>
                    <?php esc_html_e('Previous', 'wp-alt-text-plugin'); ?>
                </button>
                <span class="bbai-debug-pagination-info" data-debug-page-indicator>
                    <?php
                        printf(
                            esc_html__('Page %1$s of %2$s', 'wp-alt-text-plugin'),
                            esc_html(number_format_i18n($debug_page)),
                            esc_html(number_format_i18n($debug_pages))
                        );
                    ?>
                </span>
                <button type="button" class="bbai-debug-pagination-btn" data-debug-page="next" <?php disabled($debug_page >= $debug_pages); ?>>
                    <?php esc_html_e('Next', 'wp-alt-text-plugin'); ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Pro Upsell Section -->
            <?php if (!$is_pro) : ?>
            <div class="bbai-debug-upsell-card">
                <h3 class="bbai-debug-upsell-title"><?php esc_html_e('Unlock Pro Debug Console', 'wp-alt-text-plugin'); ?></h3>
                <ul class="bbai-debug-upsell-features">
                    <li class="bbai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Long-term log retention', 'wp-alt-text-plugin'); ?></span>
                    </li>
                    <li class="bbai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Priority support', 'wp-alt-text-plugin'); ?></span>
                    </li>
                    <li class="bbai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('High-speed global search', 'wp-alt-text-plugin'); ?></span>
                    </li>
                    <li class="bbai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Full CSV export of all logs', 'wp-alt-text-plugin'); ?></span>
                    </li>
                    <li class="bbai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('API performance insights', 'wp-alt-text-plugin'); ?></span>
                    </li>
                </ul>
                <button type="button" class="bbai-debug-upsell-btn" data-action="show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?> →
                </button>
            </div>
            <?php endif; ?>

            <div class="bbai-debug-toast" data-debug-toast hidden></div>
        </div>
        
        <!-- Context Button Handler - Rebuilt from Scratch -->
        <script>
        (function() {
            function toggleContext(button) {
                var contextData = button.getAttribute('data-context-data');
                var rowIndex = button.getAttribute('data-row-index');
                
                if (!contextData || !rowIndex) {
                    console.error('Missing context data or row index');
                    return;
                }
                
                // Find the parent row
                var row = button;
                while (row && row.tagName !== 'TR') {
                    row = row.parentElement;
                }
                if (!row) return;
                
                // Find the context row
                var contextRow = null;
                var nextSibling = row.nextElementSibling;
                while (nextSibling) {
                    if (nextSibling.classList.contains('bbai-debug-context-row') && 
                        nextSibling.getAttribute('data-row-index') === rowIndex) {
                        contextRow = nextSibling;
                        break;
                    }
                    if (!nextSibling.classList.contains('bbai-debug-context-row')) {
                        break;
                    }
                    nextSibling = nextSibling.nextElementSibling;
                }
                
                // If context row doesn't exist, create it
                if (!contextRow) {
                    contextRow = document.createElement('tr');
                    contextRow.className = 'bbai-debug-context-row';
                    contextRow.setAttribute('data-row-index', rowIndex);
                    contextRow.style.display = 'none';
                    
                    var cell = document.createElement('td');
                    cell.className = 'bbai-debug-context-cell';
                    cell.colSpan = 4;
                    
                    var content = document.createElement('div');
                    content.className = 'bbai-debug-context-content';
                    
                    var pre = document.createElement('pre');
                    pre.className = 'bbai-debug-context-json';
                    
                    content.appendChild(pre);
                    cell.appendChild(content);
                    contextRow.appendChild(cell);
                    
                    // Insert after the current row
                    if (row.nextSibling) {
                        row.parentNode.insertBefore(contextRow, row.nextSibling);
                    } else {
                        row.parentNode.appendChild(contextRow);
                    }
                }
                
                var preElement = contextRow.querySelector('pre.bbai-debug-context-json');
                if (!preElement) return;
                
                var isVisible = contextRow.style.display !== 'none';
                
                if (isVisible) {
                    // Hide
                    contextRow.style.display = 'none';
                    button.textContent = 'Log Context';
                    button.classList.remove('is-expanded');
                } else {
                    // Show - decode and display
                    var decoded = null;
                    
                    // Try to decode base64
                    try {
                        if (/^[A-Za-z0-9+\/]*={0,2}$/.test(contextData)) {
                            var decodedStr = decodeURIComponent(escape(atob(contextData)));
                            decoded = JSON.parse(decodedStr);
                        }
                    } catch(e1) {
                        // Try direct JSON parse
                        try {
                            decoded = JSON.parse(contextData);
                        } catch(e2) {
                            // Try URL decode then parse
                            try {
                                decoded = JSON.parse(decodeURIComponent(contextData));
                            } catch(e3) {
                                decoded = { error: 'Unable to decode context data' };
                            }
                        }
                    }
                    
                    var output = JSON.stringify(decoded, null, 2);
                    preElement.textContent = output;
                    contextRow.style.display = 'table-row';
                    button.textContent = 'Hide Context';
                    button.classList.add('is-expanded');
                }
            }
            
            // Bind to all context buttons
            document.addEventListener('click', function(e) {
                var btn = e.target;
                while (btn && btn !== document.body) {
                    if (btn.classList && btn.classList.contains('bbai-debug-context-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleContext(btn);
                        return;
                    }
                    btn = btn.parentElement;
                }
            });
        })();
        </script>
    <?php endif; ?>

<?php elseif ($tab === 'guide') : ?>
            <!-- How to Use Page -->
            <?php
            // Get plan info for upgrade card - check license first
            $has_license = $this->api_client->has_active_license();
            $license_data = $this->api_client->get_license_data();
            $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
            
            // If using license, check license plan
            if ($has_license && $license_data && isset($license_data['organization'])) {
                $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                if ($license_plan !== 'free') {
                    $plan_slug = $license_plan;
                }
            }
            
            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
            $is_agency = ($plan_slug === 'agency');
            ?>
            <div class="bbai-guide-container">
                <!-- Header Section -->
                <div class="bbai-guide-header">
                    <h1 class="bbai-guide-title"><?php esc_html_e('How to Use BeepBeep AI', 'wp-alt-text-plugin'); ?></h1>
                    <p class="bbai-guide-subtitle"><?php esc_html_e('Learn how to generate and manage alt text for your images.', 'wp-alt-text-plugin'); ?></p>
                </div>

                <!-- Pro Features Card (LOCKED) -->
                <?php if (!$is_pro) : ?>
                <div class="bbai-guide-pro-card">
                    <div class="bbai-guide-pro-ribbon">
                        <?php esc_html_e('LOCKED PRO FEATURES', 'wp-alt-text-plugin'); ?>
                    </div>
                    <div class="bbai-guide-pro-features">
                        <div class="bbai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Priority queue generation', 'wp-alt-text-plugin'); ?></span>
                        </div>
                        <div class="bbai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Bulk optimisation for large libraries', 'wp-alt-text-plugin'); ?></span>
                        </div>
                        <div class="bbai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Multilingual alt text', 'wp-alt-text-plugin'); ?></span>
                        </div>
                        <div class="bbai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Smart tone + style tuning', 'wp-alt-text-plugin'); ?></span>
                        </div>
                        <div class="bbai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Unlimited alt text generation', 'wp-alt-text-plugin'); ?></span>
                        </div>
                    </div>
                    <div class="bbai-guide-pro-cta">
                        <a href="#" class="bbai-guide-pro-link" data-action="show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?> →
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Getting Started Card -->
                <div class="bbai-guide-steps-card">
                    <h2 class="bbai-guide-steps-title">
                        <?php esc_html_e('Getting Started in 4 Easy Steps', 'wp-alt-text-plugin'); ?>
                    </h2>
                    <div class="bbai-guide-steps-list">
                        <div class="bbai-guide-step">
                            <div class="bbai-guide-step-badge">
                                <span class="bbai-guide-step-number">1</span>
                            </div>
                            <div class="bbai-guide-step-content">
                                <h3 class="bbai-guide-step-title"><?php esc_html_e('Upload Images', 'wp-alt-text-plugin'); ?></h3>
                                <p class="bbai-guide-step-description"><?php esc_html_e('Add images to your WordPress Media Library.', 'wp-alt-text-plugin'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-guide-step">
                            <div class="bbai-guide-step-badge">
                                <span class="bbai-guide-step-number">2</span>
                            </div>
                            <div class="bbai-guide-step-content">
                                <h3 class="bbai-guide-step-title"><?php esc_html_e('Bulk Optimize', 'wp-alt-text-plugin'); ?></h3>
                                <p class="bbai-guide-step-description"><?php esc_html_e('Generate alt text for multiple images at once from the Dashboard.', 'wp-alt-text-plugin'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-guide-step">
                            <div class="bbai-guide-step-badge">
                                <span class="bbai-guide-step-number">3</span>
                            </div>
                            <div class="bbai-guide-step-content">
                                <h3 class="bbai-guide-step-title"><?php esc_html_e('Review & Edit', 'wp-alt-text-plugin'); ?></h3>
                                <p class="bbai-guide-step-description"><?php esc_html_e('Refine generated alt text in the ALT Library.', 'wp-alt-text-plugin'); ?></p>
                            </div>
                        </div>
                        <div class="bbai-guide-step">
                            <div class="bbai-guide-step-badge">
                                <span class="bbai-guide-step-number">4</span>
                            </div>
                            <div class="bbai-guide-step-content">
                                <h3 class="bbai-guide-step-title"><?php esc_html_e('Regenerate if Needed', 'wp-alt-text-plugin'); ?></h3>
                                <p class="bbai-guide-step-description"><?php esc_html_e('Use the regenerate feature to improve alt text quality anytime.', 'wp-alt-text-plugin'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Why Alt Text Matters Section -->
                <div class="bbai-guide-why-card">
                    <div class="bbai-guide-why-icon">💡</div>
                    <h3 class="bbai-guide-why-title">
                        <?php esc_html_e('Why Alt Text Matters', 'wp-alt-text-plugin'); ?>
                    </h3>
                    <ul class="bbai-guide-why-list">
                        <li class="bbai-guide-why-item">
                            <span class="bbai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Boosts SEO visibility by up to 20%', 'wp-alt-text-plugin'); ?></span>
                        </li>
                        <li class="bbai-guide-why-item">
                            <span class="bbai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Improves Google Images ranking', 'wp-alt-text-plugin'); ?></span>
                        </li>
                        <li class="bbai-guide-why-item">
                            <span class="bbai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Helps achieve WCAG compliance for accessibility', 'wp-alt-text-plugin'); ?></span>
                        </li>
                    </ul>
                </div>

                <!-- Two Column Layout -->
                <div class="bbai-guide-grid">
                    <!-- Tips Card -->
                    <div class="bbai-guide-card">
                        <h3 class="bbai-guide-card-title">
                            <?php esc_html_e('Tips for Better Alt Text', 'wp-alt-text-plugin'); ?>
                        </h3>
                        <div class="bbai-guide-tips-list">
                            <div class="bbai-guide-tip">
                                <div class="bbai-guide-tip-icon">✓</div>
                                <div class="bbai-guide-tip-content">
                                    <div class="bbai-guide-tip-title"><?php esc_html_e('Keep it concise', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                            <div class="bbai-guide-tip">
                                <div class="bbai-guide-tip-icon">✓</div>
                                <div class="bbai-guide-tip-content">
                                    <div class="bbai-guide-tip-title"><?php esc_html_e('Be specific', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                            <div class="bbai-guide-tip">
                                <div class="bbai-guide-tip-icon">✓</div>
                                <div class="bbai-guide-tip-content">
                                    <div class="bbai-guide-tip-title"><?php esc_html_e('Avoid redundancy', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                            <div class="bbai-guide-tip">
                                <div class="bbai-guide-tip-icon">✓</div>
                                <div class="bbai-guide-tip-content">
                                    <div class="bbai-guide-tip-title"><?php esc_html_e('Think context', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Features Card -->
                    <div class="bbai-guide-card">
                        <h3 class="bbai-guide-card-title">
                            <?php esc_html_e('Key Features', 'wp-alt-text-plugin'); ?>
                        </h3>
                        <div class="bbai-guide-features-list">
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">🤖</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title"><?php esc_html_e('AI-Powered', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">≡</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title">
                                        <?php esc_html_e('Bulk Processing', 'wp-alt-text-plugin'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="bbai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">◆</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title"><?php esc_html_e('SEO Optimized', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">🎨</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title">
                                        <?php esc_html_e('Smart tone tuning', 'wp-alt-text-plugin'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="bbai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">🌍</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title">
                                        <?php esc_html_e('Multilingual alt text', 'wp-alt-text-plugin'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="bbai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="bbai-guide-feature">
                                <div class="bbai-guide-feature-icon">♿</div>
                                <div class="bbai-guide-feature-content">
                                    <div class="bbai-guide-feature-title"><?php esc_html_e('Accessibility', 'wp-alt-text-plugin'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upgrade CTA Banner -->
                <?php if (!$is_agency) : ?>
                <div class="bbai-guide-cta-card">
                    <h3 class="bbai-guide-cta-title">
                        <span class="bbai-guide-cta-icon">⚡</span>
                        <?php esc_html_e('Ready for More?', 'wp-alt-text-plugin'); ?>
                    </h3>
                    <p class="bbai-guide-cta-text">
                        <?php esc_html_e('Save hours each month with automated alt text generation. Upgrade for 1,000 images/month and priority processing.', 'wp-alt-text-plugin'); ?>
                    </p>
                    <button type="button" class="bbai-guide-cta-btn" data-action="show-upgrade-modal">
                        <span><?php esc_html_e('View Plans & Pricing', 'wp-alt-text-plugin'); ?></span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="bbai-guide-cta-badge-new"><?php esc_html_e('NEW', 'wp-alt-text-plugin'); ?></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>


<?php elseif ($tab === 'settings') : ?>
            <?php if (!$this->api_client->is_authenticated()) : ?>
                <!-- Settings require authentication -->
                <div class="bbai-settings-required">
                    <div class="bbai-settings-required-content">
                        <div class="bbai-settings-required-icon">🔒</div>
                        <h2><?php esc_html_e('Authentication Required', 'wp-alt-text-plugin'); ?></h2>
                        <p><?php esc_html_e('Settings are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'wp-alt-text-plugin'); ?></p>
                        <p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
                            <?php esc_html_e('If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'wp-alt-text-plugin'); ?>
                        </p>
                        <button type="button" class="bbai-btn-primary bbai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Log In', 'wp-alt-text-plugin'); ?></span>
                        </button>
                    </div>
                </div>
            <?php else : ?>
            <!-- Settings Page -->
            <div class="bbai-settings-page">
                <?php
                // Pull fresh usage from backend to avoid stale cache in Settings
                if (isset($this->api_client)) {
                    $live = $this->api_client->get_usage();
                    if (is_array($live) && !empty($live)) { BbAI_Usage_Tracker::update_usage($live); }
                }
                $usage_box = BbAI_Usage_Tracker::get_stats_display();
                $o = wp_parse_args($opts, []);
                
                // Check for license plan first
                $has_license = $this->api_client->has_active_license();
                $license_data = $this->api_client->get_license_data();
                
                $plan = $usage_box['plan'] ?? 'free';
                
                // If license is active, use license plan
                if ($has_license && $license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    if ($license_plan !== 'free') {
                        $plan = $license_plan;
                    }
                }
                
                $is_pro = $plan === 'pro';
                $is_agency = $plan === 'agency';
                $usage_percent = $usage_box['limit'] > 0 ? ($usage_box['used'] / $usage_box['limit'] * 100) : 0;

                // Determine plan badge text
                if ($is_agency) {
                    $plan_badge_text = esc_html__('AGENCY', 'wp-alt-text-plugin');
                } elseif ($is_pro) {
                    $plan_badge_text = esc_html__('PRO', 'wp-alt-text-plugin');
                } else {
                    $plan_badge_text = esc_html__('FREE', 'wp-alt-text-plugin');
                }
                ?>

                <!-- Header Section -->
                <div class="bbai-settings-page-header">
                    <h1 class="bbai-settings-page-title"><?php esc_html_e('Settings', 'wp-alt-text-plugin'); ?></h1>
                    <p class="bbai-settings-page-subtitle"><?php esc_html_e('Configure generation preferences and manage your account.', 'wp-alt-text-plugin'); ?></p>
                </div>
                
                <!-- Site-Wide Settings Banner -->
                <div class="bbai-settings-sitewide-banner">
                    <svg class="bbai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
                        <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="bbai-settings-sitewide-content">
                        <strong class="bbai-settings-sitewide-title"><?php esc_html_e('Site-Wide Settings', 'wp-alt-text-plugin'); ?></strong>
                        <span class="bbai-settings-sitewide-text">
                            <?php esc_html_e('These settings apply to all users on this WordPress site.', 'wp-alt-text-plugin'); ?>
                        </span>
                    </div>
                </div>

                <!-- Plan Summary Card -->
                <div class="bbai-settings-plan-summary-card">
                    <div class="bbai-settings-plan-badge-top">
                        <span class="bbai-settings-plan-badge-text"><?php echo esc_html($plan_badge_text); ?></span>
                    </div>
                    <div class="bbai-settings-plan-quota">
                        <div class="bbai-settings-plan-quota-meter">
                            <span class="bbai-settings-plan-quota-used"><?php echo esc_html($usage_box['used']); ?></span>
                            <span class="bbai-settings-plan-quota-divider">/</span>
                            <span class="bbai-settings-plan-quota-limit"><?php echo esc_html($usage_box['limit']); ?></span>
                        </div>
                        <div class="bbai-settings-plan-quota-label">
                            <?php esc_html_e('image descriptions', 'wp-alt-text-plugin'); ?>
                    </div>
                    </div>
                    <div class="bbai-settings-plan-info">
                        <?php if (!$is_pro && !$is_agency) : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span>
                                    <?php
                                    if (isset($usage_box['reset_date'])) {
                                        printf(
                                            esc_html__('Resets %s', 'wp-alt-text-plugin'),
                                            '<strong>' . esc_html($usage_box['reset_date']) . '</strong>'
                                        );
                                    } else {
                                        esc_html_e('Monthly quota', 'wp-alt-text-plugin');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Shared across all users', 'wp-alt-text-plugin'); ?></span>
                            </div>
                        <?php elseif ($is_agency) : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Multi-site license', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php echo sprintf(esc_html__('Resets %s', 'wp-alt-text-plugin'), '<strong>' . esc_html($usage_box['reset_date'] ?? 'Monthly') . '</strong>'); ?></span>
                            </div>
                        <?php else : ?>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited generations', 'wp-alt-text-plugin'); ?></span>
                            </div>
                            <div class="bbai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority support', 'wp-alt-text-plugin'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <button type="button" class="bbai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
                        <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- License Management Card -->
                <?php
                // Reuse license variables if already set above
                if (!isset($has_license)) {
                    $has_license = $this->api_client->has_active_license();
                    $license_data = $this->api_client->get_license_data();
                }
                ?>

                <div class="bbai-settings-card">
                    <div class="bbai-settings-card-header">
                        <div class="bbai-settings-card-header-icon">
                            <span class="bbai-settings-card-icon-emoji">🔑</span>
                        </div>
                        <h3 class="bbai-settings-card-title"><?php esc_html_e('License', 'wp-alt-text-plugin'); ?></h3>
                    </div>

                    <?php if ($has_license && $license_data) : ?>
                        <?php
                        $org = $license_data['organization'] ?? null;
                        if ($org) :
                        ?>
                        <!-- Active License Display -->
                        <div class="bbai-settings-license-active">
                            <div class="bbai-settings-license-status">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                    <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div>
                                    <div class="bbai-settings-license-title"><?php esc_html_e('License Active', 'wp-alt-text-plugin'); ?></div>
                                    <div class="bbai-settings-license-subtitle"><?php echo esc_html($org['name'] ?? ''); ?></div>
                                    <?php 
                                    // Display license key for Pro and Agency users
                                    $license_key = $this->api_client->get_license_key();
                                    if (!empty($license_key)) :
                                        $license_plan = strtolower($org['plan'] ?? 'free');
                                        if ($license_plan === 'pro' || $license_plan === 'agency') :
                                    ?>
                                    <div class="bbai-settings-license-key" style="margin-top: 8px; font-size: 12px; color: #6b7280; font-family: monospace; word-break: break-all;">
                                        <strong><?php esc_html_e('License Key:', 'wp-alt-text-plugin'); ?></strong> <?php echo esc_html($license_key); ?>
                                    </div>
                                    <?php 
                                        endif;
                                    endif; 
                                    ?>
                                </div>
                            </div>
                            <button type="button" class="bbai-settings-license-deactivate-btn" data-action="deactivate-license">
                                <?php esc_html_e('Deactivate', 'wp-alt-text-plugin'); ?>
                            </button>
                        </div>
                        
                        <?php 
                        // Show site usage for agency licenses (can use license key or JWT auth)
                        $is_authenticated = $this->api_client->is_authenticated();
                        $has_license = $this->api_client->has_active_license();
                        $is_agency_license = isset($license_data['organization']['plan']) && $license_data['organization']['plan'] === 'agency';
                        
                        // Show for agency licenses with either JWT auth or license key
                        if ($is_agency_license && ($is_authenticated || $has_license)) :
                        ?>
                        <!-- License Site Usage Section -->
                        <div class="bbai-settings-license-sites" id="bbai-license-sites">
                            <div class="bbai-settings-license-sites-header">
                                <h3 class="bbai-settings-license-sites-title">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                        <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                    </svg>
                                    <?php esc_html_e('Sites Using This License', 'wp-alt-text-plugin'); ?>
                                </h3>
                            </div>
                            <div class="bbai-settings-license-sites-content" id="bbai-license-sites-content">
                                <div class="bbai-settings-license-sites-loading">
                                    <span class="bbai-spinner"></span>
                                    <?php esc_html_e('Loading site usage...', 'wp-alt-text-plugin'); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    <?php else : ?>
                        <!-- License Activation Form -->
                        <div class="bbai-settings-license-form">
                            <p class="bbai-settings-license-description">
                                <?php esc_html_e('Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'wp-alt-text-plugin'); ?>
                            </p>
                            <form id="license-activation-form">
                                <div class="bbai-settings-license-input-group">
                                    <label for="license-key-input" class="bbai-settings-license-label">
                                        <?php esc_html_e('License Key', 'wp-alt-text-plugin'); ?>
                                    </label>
                                    <input type="text"
                                           id="license-key-input"
                                           name="license_key"
                                           class="bbai-settings-license-input"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                           pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
                                           required>
                                </div>
                                <div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                                <button type="submit" id="activate-license-btn" class="bbai-settings-license-activate-btn">
                                    <?php esc_html_e('Activate License', 'wp-alt-text-plugin'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden nonce for AJAX requests -->
                <input type="hidden" id="license-nonce" value="<?php echo esc_attr(wp_create_nonce('bbai_license_action')); ?>">

                <!-- Account Management Card -->
                <div class="bbai-settings-card">
                    <div class="bbai-settings-card-header">
                        <div class="bbai-settings-card-header-icon">
                            <span class="bbai-settings-card-icon-emoji">👤</span>
                        </div>
                        <h3 class="bbai-settings-card-title"><?php esc_html_e('Account Management', 'wp-alt-text-plugin'); ?></h3>
                    </div>
                    
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <div class="bbai-settings-account-info-banner">
                        <span><?php esc_html_e('You are on the free plan.', 'wp-alt-text-plugin'); ?></span>
                    </div>
                    <div class="bbai-settings-account-upgrade-link">
                        <button type="button" class="bbai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
                            <?php esc_html_e('Upgrade Now', 'wp-alt-text-plugin'); ?>
                        </button>
                    </div>
                    <?php else : ?>
                    <div class="bbai-settings-account-status">
                        <span class="bbai-settings-account-status-label"><?php esc_html_e('Current Plan:', 'wp-alt-text-plugin'); ?></span>
                        <span class="bbai-settings-account-status-value"><?php 
                            if ($is_agency) {
                                esc_html_e('Agency', 'wp-alt-text-plugin');
                            } else {
                                esc_html_e('Pro', 'wp-alt-text-plugin');
                            }
                        ?></span>
                    </div>
                    <?php 
                    // Check if using license vs authenticated account
                    $is_authenticated_for_account = $this->api_client->is_authenticated();
                    $is_license_only = $has_license && !$is_authenticated_for_account;
                    
                    if ($is_license_only) : 
                        // License-based plan - provide contact info
                    ?>
                    <div class="bbai-settings-account-actions">
                        <div class="bbai-settings-account-action-info">
                            <p><strong><?php esc_html_e('License-Based Plan', 'wp-alt-text-plugin'); ?></strong></p>
                            <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'wp-alt-text-plugin'); ?></p>
                            <ul>
                                <li><?php esc_html_e('Contact your license administrator', 'wp-alt-text-plugin'); ?></li>
                                <li><?php esc_html_e('Email support for billing inquiries', 'wp-alt-text-plugin'); ?></li>
                                <li><?php esc_html_e('View license details in the License section above', 'wp-alt-text-plugin'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php elseif ($is_authenticated_for_account) : 
                        // Authenticated user - show Stripe portal
                    ?>
                    <div class="bbai-settings-account-actions">
                        <button type="button" class="bbai-settings-account-action-btn" data-action="manage-subscription">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Manage Subscription', 'wp-alt-text-plugin'); ?></span>
                        </button>
                        <div class="bbai-settings-account-action-info">
                            <p><?php esc_html_e('In Stripe Customer Portal you can:', 'wp-alt-text-plugin'); ?></p>
                            <ul>
                                <li><?php esc_html_e('View and download invoices', 'wp-alt-text-plugin'); ?></li>
                                <li><?php esc_html_e('Update payment method', 'wp-alt-text-plugin'); ?></li>
                                <li><?php esc_html_e('View payment history', 'wp-alt-text-plugin'); ?></li>
                                <li><?php esc_html_e('Cancel or modify subscription', 'wp-alt-text-plugin'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields('bbai_group'); ?>

                    <!-- Generation Settings Card -->
                    <div class="bbai-settings-card">
                        <h3 class="bbai-settings-generation-title"><?php esc_html_e('Generation Settings', 'wp-alt-text-plugin'); ?></h3>

                        <div class="bbai-settings-form-group">
                            <div class="bbai-settings-form-field bbai-settings-form-field--toggle">
                                <div class="bbai-settings-form-field-content">
                                    <label for="bbai-enable-on-upload" class="bbai-settings-form-label">
                                        <?php esc_html_e('Auto-generate on Image Upload', 'wp-alt-text-plugin'); ?>
                                    </label>
                                    <p class="bbai-settings-form-description">
                                        <?php esc_html_e('Automatically generate alt text when new images are uploaded to your media library.', 'wp-alt-text-plugin'); ?>
                                    </p>
                                </div>
                                <label class="bbai-settings-toggle">
                                    <input 
                                        type="checkbox" 
                                        id="bbai-enable-on-upload"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                        value="1"
                                        <?php checked(!empty($o['enable_on_upload'] ?? true)); ?>
                                    >
                                    <span class="bbai-settings-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-tone" class="bbai-settings-form-label">
                                <?php esc_html_e('Tone & Style', 'wp-alt-text-plugin'); ?>
                            </label>
                            <input
                                type="text"
                                id="bbai-tone"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                placeholder="<?php esc_attr_e('professional, accessible', 'wp-alt-text-plugin'); ?>"
                                class="bbai-settings-form-input"
                            />
                        </div>

                        <div class="bbai-settings-form-group">
                            <label for="bbai-custom-prompt" class="bbai-settings-form-label">
                                <?php esc_html_e('Additional Instructions', 'wp-alt-text-plugin'); ?>
                            </label>
                            <textarea
                                id="bbai-custom-prompt"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                rows="4"
                                placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'wp-alt-text-plugin'); ?>"
                                class="bbai-settings-form-textarea"
                            ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                        </div>

                        <div class="bbai-settings-form-actions">
                            <button type="submit" class="bbai-settings-save-btn">
                                <?php esc_html_e('Save Settings', 'wp-alt-text-plugin'); ?>
                            </button>
                        </div>
                    </div>
                </form>

                <script>
                (function($) {
                    'use strict';
                    // Toggle is handled by CSS, no JavaScript needed for visual updates
                })(jQuery);
                </script>

                <!-- Pro Upsell Banner -->
                    <?php if (!$is_agency) : ?>
                <div class="bbai-settings-pro-upsell-banner">
                    <div class="bbai-settings-pro-upsell-content">
                        <h3 class="bbai-settings-pro-upsell-title">
                            <?php esc_html_e('Want unlimited AI alt text and faster processing?', 'wp-alt-text-plugin'); ?>
                        </h3>
                        <ul class="bbai-settings-pro-upsell-features">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited monthly AI generations', 'wp-alt-text-plugin'); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority queue', 'wp-alt-text-plugin'); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Large library batch mode', 'wp-alt-text-plugin'); ?></span>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="bbai-settings-pro-upsell-btn" data-action="show-upgrade-modal">
                        <?php esc_html_e('View Plans & Pricing', 'wp-alt-text-plugin'); ?> →
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; // End if/else for authentication check in settings tab ?>
            <?php elseif ($tab === 'admin' && $is_pro_for_admin) : ?>
            <!-- Admin Tab - Debug Logs and Settings for Pro and Agency -->
            <?php
            $admin_authenticated = $this->is_admin_authenticated();
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
                                <?php esc_html_e('Admin Access', 'wp-alt-text-plugin'); ?>
                            </h2>
                            <p class="bbai-admin-login-subtitle">
                                <?php 
                                if ($is_agency_for_admin) {
                                    esc_html_e('Enter your agency credentials to access Debug Logs and Settings.', 'wp-alt-text-plugin');
                                } else {
                                    esc_html_e('Enter your pro credentials to access Debug Logs and Settings.', 'wp-alt-text-plugin');
                                }
                                ?>
                            </p>
                        </div>
                        
                        <form id="bbai-admin-login-form" class="bbai-admin-login-form">
                            <div id="bbai-admin-login-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-email" class="bbai-admin-login-label">
                                    <?php esc_html_e('Email', 'wp-alt-text-plugin'); ?>
                                </label>
                                <input type="email" 
                                       id="admin-login-email" 
                                       name="email" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('your-email@example.com', 'wp-alt-text-plugin'); ?>"
                                       required>
                            </div>
                            
                            <div class="bbai-admin-login-field">
                                <label for="admin-login-password" class="bbai-admin-login-label">
                                    <?php esc_html_e('Password', 'wp-alt-text-plugin'); ?>
                                </label>
                                <input type="password" 
                                       id="admin-login-password" 
                                       name="password" 
                                       class="bbai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('Enter your password', 'wp-alt-text-plugin'); ?>"
                                       required>
                            </div>
                            
                            <button type="submit" id="admin-login-submit-btn" class="bbai-admin-login-btn">
                                <span class="bbai-btn__text"><?php esc_html_e('Log In', 'wp-alt-text-plugin'); ?></span>
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
                
                <script>
                (function($) {
                    'use strict';
                    
                    $('#bbai-admin-login-form').on('submit', function(e) {
                        e.preventDefault();
                        
                        const $form = $(this);
                        const $status = $('#bbai-admin-login-status');
                        const $btn = $('#admin-login-submit-btn');
                        const $btnText = $btn.find('.bbai-btn__text');
                        const $btnSpinner = $btn.find('.bbai-btn__spinner');
                        
                        const email = $('#admin-login-email').val().trim();
                        const password = $('#admin-login-password').val();
                        
                        // Show loading
                        $btn.prop('disabled', true);
                        $btnText.hide();
                        $btnSpinner.show();
                        $status.hide();
                        
                        $.ajax({
                            url: window.bbai_ajax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'bbai_admin_login',
                                nonce: window.bbai_ajax.nonce,
                                email: email,
                                password: password
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.removeClass('error').addClass('success').text(response.data.message || 'Successfully logged in').show();
                                    setTimeout(function() {
                                        window.location.href = response.data.redirect || window.location.href;
                                    }, 1000);
                                } else {
                                    $status.removeClass('success').addClass('error').text(response.data?.message || 'Login failed').show();
                                    $btn.prop('disabled', false);
                                    $btnText.show();
                                    $btnSpinner.hide();
                                }
                            },
                            error: function() {
                                $status.removeClass('success').addClass('error').text('Network error. Please try again.').show();
                                $btn.prop('disabled', false);
                                $btnText.show();
                                $btnSpinner.hide();
                            }
                        });
                    });
                })(jQuery);
                </script>
            <?php else : ?>
                <!-- Admin Content: Debug Logs and Settings -->
                <div class="bbai-admin-content">
                    <!-- Admin Header with Logout -->
                    <div class="bbai-admin-header">
                        <div class="bbai-admin-header-info">
                            <h2 class="bbai-admin-header-title">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="margin-right: 10px; vertical-align: middle;">
                                    <path d="M10 1L19 10L10 19L1 10L10 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="10" cy="10" r="2.5" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Panel', 'wp-alt-text-plugin'); ?>
                            </h2>
                            <p class="bbai-admin-header-subtitle">
                                <?php esc_html_e('Debug Logs and Settings', 'wp-alt-text-plugin'); ?>
                            </p>
                        </div>
                        <button type="button" class="bbai-admin-logout-btn" id="bbai-admin-logout-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M10 11L13 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Log Out', 'wp-alt-text-plugin'); ?>
                        </button>
                    </div>

                    <!-- Admin Tabs Navigation -->
                    <div class="bbai-admin-tabs">
                        <button type="button" class="bbai-admin-tab active" data-admin-tab="debug">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <?php esc_html_e('Debug Logs', 'wp-alt-text-plugin'); ?>
                        </button>
                        <button type="button" class="bbai-admin-tab" data-admin-tab="settings">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Settings', 'wp-alt-text-plugin'); ?>
                        </button>
                    </div>

                    <!-- Debug Logs Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="debug">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Debug Logs', 'wp-alt-text-plugin'); ?></h3>
                        </div>
                        <?php
                        // Reuse debug logs content
                        if (!class_exists('BbAI_Debug_Log')) : ?>
                            <div class="bbai-section">
                                <div class="notice notice-warning">
                                    <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'wp-alt-text-plugin'); ?></p>
                                </div>
                            </div>
                        <?php else :
                            // Inline debug logs content (same as debug tab)
                            $debug_logs       = $debug_bootstrap['logs'] ?? [];
                            $debug_stats      = $debug_bootstrap['stats'] ?? [];
                            $debug_pagination = $debug_bootstrap['pagination'] ?? [];
                            $debug_page       = max(1, intval($debug_pagination['page'] ?? 1));
                            $debug_pages      = max(1, intval($debug_pagination['total_pages'] ?? 1));
                            $debug_export_url = wp_nonce_url(
                                admin_url('admin-post.php?action=bbai_debug_export'),
                                'bbai_debug_export'
                            );
                        ?>
                            <div class="bbai-dashboard-container" data-bbai-debug-panel>
                                <!-- Debug Logs content (same as debug tab) - inline all content here -->
                                <!-- Header Section -->
                                <div class="bbai-debug-header">
                                    <h1 class="bbai-dashboard-title"><?php esc_html_e('Debug Logs', 'wp-alt-text-plugin'); ?></h1>
                                    <p class="bbai-dashboard-subtitle"><?php esc_html_e('Monitor API calls, queue events, and any errors generated by the plugin.', 'wp-alt-text-plugin'); ?></p>
                                    
                                    <!-- Usage Info -->
                                    <?php if (isset($usage_stats)) : ?>
                                    <div class="bbai-debug-usage-info">
                                        <?php 
                                        printf(
                                            esc_html__('%1$d of %2$d image descriptions generated this month', 'wp-alt-text-plugin'),
                                            $usage_stats['used'] ?? 0,
                                            $usage_stats['limit'] ?? 0
                                        ); 
                                        ?>
                                        <span class="bbai-debug-usage-separator">•</span>
                                        <?php 
                                        $reset_date = $usage_stats['reset_date'] ?? '';
                                        if (!empty($reset_date)) {
                                            $reset_timestamp = strtotime($reset_date);
                                            if ($reset_timestamp !== false) {
                                                $formatted_reset = date_i18n('F j, Y', $reset_timestamp);
                                                printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($formatted_reset));
                                            } else {
                                                printf(esc_html__('Resets %s', 'wp-alt-text-plugin'), esc_html($reset_date));
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Log Statistics Card -->
                                <div class="bbai-debug-stats-card">
                                    <div class="bbai-debug-stats-header">
                                        <div class="bbai-debug-stats-title">
                                            <h3><?php esc_html_e('Log Statistics', 'wp-alt-text-plugin'); ?></h3>
                                        </div>
                                        <div class="bbai-debug-stats-actions">
                                            <button type="button" class="bbai-debug-btn bbai-debug-btn--secondary" data-debug-clear>
                                                <?php esc_html_e('Clear Logs', 'wp-alt-text-plugin'); ?>
                                            </button>
                                            <a href="<?php echo esc_url($debug_export_url); ?>" class="bbai-debug-btn bbai-debug-btn--primary">
                                                <?php esc_html_e('Export CSV', 'wp-alt-text-plugin'); ?>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Stats Grid -->
                                    <div class="bbai-debug-stats-grid">
                                        <div class="bbai-debug-stat-item">
                                            <div class="bbai-debug-stat-label"><?php esc_html_e('TOTAL LOGS', 'wp-alt-text-plugin'); ?></div>
                                            <div class="bbai-debug-stat-value" data-debug-stat="total">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['total'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="bbai-debug-stat-item bbai-debug-stat-item--warning">
                                            <div class="bbai-debug-stat-label"><?php esc_html_e('WARNINGS', 'wp-alt-text-plugin'); ?></div>
                                            <div class="bbai-debug-stat-value bbai-debug-stat-value--warning" data-debug-stat="warnings">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['warnings'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="bbai-debug-stat-item bbai-debug-stat-item--error">
                                            <div class="bbai-debug-stat-label"><?php esc_html_e('ERRORS', 'wp-alt-text-plugin'); ?></div>
                                            <div class="bbai-debug-stat-value bbai-debug-stat-value--error" data-debug-stat="errors">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['errors'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="bbai-debug-stat-item">
                                            <div class="bbai-debug-stat-label"><?php esc_html_e('LAST API EVENT', 'wp-alt-text-plugin'); ?></div>
                                            <div class="bbai-debug-stat-value bbai-debug-stat-value--small" data-debug-stat="last_api">
                                                <?php echo esc_html($debug_stats['last_api'] ?? '—'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filters Panel -->
                                <div class="bbai-debug-filters-card">
                                    <form data-debug-filter class="bbai-debug-filters-form">
                                        <div class="bbai-debug-filter-group">
                                            <label for="bbai-debug-level" class="bbai-debug-filter-label">
                                                <?php esc_html_e('Level', 'wp-alt-text-plugin'); ?>
                                            </label>
                                            <select id="bbai-debug-level" name="level" class="bbai-debug-filter-input">
                                                <option value=""><?php esc_html_e('All levels', 'wp-alt-text-plugin'); ?></option>
                                                <option value="info"><?php esc_html_e('Info', 'wp-alt-text-plugin'); ?></option>
                                                <option value="warning"><?php esc_html_e('Warning', 'wp-alt-text-plugin'); ?></option>
                                                <option value="error"><?php esc_html_e('Error', 'wp-alt-text-plugin'); ?></option>
                                            </select>
                                        </div>
                                        <div class="bbai-debug-filter-group">
                                            <label for="bbai-debug-date" class="bbai-debug-filter-label">
                                                <?php esc_html_e('Date', 'wp-alt-text-plugin'); ?>
                                            </label>
                                            <input type="date" id="bbai-debug-date" name="date" class="bbai-debug-filter-input" placeholder="dd/mm/yyyy">
                                        </div>
                                        <div class="bbai-debug-filter-group bbai-debug-filter-group--search">
                                            <label for="bbai-debug-search" class="bbai-debug-filter-label">
                                                <?php esc_html_e('Search', 'wp-alt-text-plugin'); ?>
                                            </label>
                                            <div class="bbai-debug-search-wrapper">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="bbai-debug-search-icon">
                                                    <circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <input type="search" id="bbai-debug-search" name="search" placeholder="<?php esc_attr_e('Search logs…', 'wp-alt-text-plugin'); ?>" class="bbai-debug-filter-input bbai-debug-filter-input--search">
                                            </div>
                                        </div>
                                        <div class="bbai-debug-filter-actions">
                                            <button type="submit" class="bbai-debug-btn bbai-debug-btn--primary">
                                                <?php esc_html_e('Apply', 'wp-alt-text-plugin'); ?>
                                            </button>
                                            <button type="button" class="bbai-debug-btn bbai-debug-btn--ghost" data-debug-reset>
                                                <?php esc_html_e('Reset', 'wp-alt-text-plugin'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Table Card -->
                                <div class="bbai-debug-table-card">
                                    <table class="bbai-debug-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('TIMESTAMP', 'wp-alt-text-plugin'); ?></th>
                                                <th><?php esc_html_e('LEVEL', 'wp-alt-text-plugin'); ?></th>
                                                <th><?php esc_html_e('MESSAGE', 'wp-alt-text-plugin'); ?></th>
                                                <th><?php esc_html_e('CONTEXT', 'wp-alt-text-plugin'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody data-debug-rows>
                                            <?php if (!empty($debug_logs)) : ?>
                                                <?php 
                                                $row_index = 0;
                                                foreach ($debug_logs as $log) :
                                                    $row_index++;
                                                    $level = strtolower($log['level'] ?? 'info');
                                                    $level_slug = preg_replace('/[^a-z0-9_-]/i', '', $level) ?: 'info';
                                                    $context_attr = '';
                                                    $context_source = $log['context'] ?? [];
                                                    if (!empty($context_source)) {
                                                        if (is_array($context_source)) {
                                                            $json = wp_json_encode($context_source, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                                            $context_attr = base64_encode($json);
                                                        } else {
                                                            $context_str = (string) $context_source;
                                                            $decoded = json_decode($context_str, true);
                                                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                                $json = wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                                                $context_attr = base64_encode($json);
                                                            } else {
                                                                $context_attr = base64_encode($context_str);
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Format date: "Nov 13, 2025 — 12:45 PM"
                                                    $created_at = $log['created_at'] ?? '';
                                                    $formatted_date = $created_at;
                                                    if ($created_at) {
                                                        $timestamp = strtotime($created_at);
                                                        if ($timestamp !== false) {
                                                            $formatted_date = date('M j, Y — g:i A', $timestamp);
                                                        }
                                                    }
                                                    
                                                    // Severity badge classes
                                                    $badge_class = 'bbai-debug-badge bbai-debug-badge--' . esc_attr($level_slug);
                                                ?>
                                                <tr class="bbai-debug-table-row" data-row-index="<?php echo esc_attr($row_index); ?>">
                                                    <td class="bbai-debug-table-cell bbai-debug-table-cell--timestamp">
                                                        <?php echo esc_html($formatted_date); ?>
                                                    </td>
                                                    <td class="bbai-debug-table-cell bbai-debug-table-cell--level">
                                                        <span class="<?php echo esc_attr($badge_class); ?>">
                                                            <span class="bbai-debug-badge-text"><?php echo esc_html(ucfirst($level_slug)); ?></span>
                                                        </span>
                                                    </td>
                                                    <td class="bbai-debug-table-cell bbai-debug-table-cell--message">
                                                        <?php echo esc_html($log['message'] ?? ''); ?>
                                                    </td>
                                                    <td class="bbai-debug-table-cell bbai-debug-table-cell--context">
                                                        <?php if ($context_attr) : ?>
                                                            <button type="button" 
                                                                    class="bbai-debug-context-btn" 
                                                                    data-context-data="<?php echo esc_attr($context_attr); ?>"
                                                                    data-row-index="<?php echo esc_attr($row_index); ?>">
                                                                <?php esc_html_e('Log Context', 'wp-alt-text-plugin'); ?>
                                                            </button>
                                                        <?php else : ?>
                                                            <span class="bbai-debug-context-empty">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if ($context_attr) : ?>
                                                <tr class="bbai-debug-context-row" data-row-index="<?php echo esc_attr($row_index); ?>" style="display: none;">
                                                    <td colspan="4" class="bbai-debug-context-cell">
                                                        <div class="bbai-debug-context-content">
                                                            <pre class="bbai-debug-context-json"></pre>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <tr>
                                                    <td colspan="4" class="bbai-debug-table-empty">
                                                        <?php esc_html_e('No logs recorded yet.', 'wp-alt-text-plugin'); ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($debug_pages > 1) : ?>
                                <div class="bbai-debug-pagination">
                                    <button type="button" class="bbai-debug-pagination-btn" data-debug-page="prev" <?php disabled($debug_page <= 1); ?>>
                                        <?php esc_html_e('Previous', 'wp-alt-text-plugin'); ?>
                                    </button>
                                    <span class="bbai-debug-pagination-info" data-debug-page-indicator>
                                        <?php
                                            printf(
                                                esc_html__('Page %1$s of %2$s', 'wp-alt-text-plugin'),
                                                esc_html(number_format_i18n($debug_page)),
                                                esc_html(number_format_i18n($debug_pages))
                                            );
                                        ?>
                                    </span>
                                    <button type="button" class="bbai-debug-pagination-btn" data-debug-page="next" <?php disabled($debug_page >= $debug_pages); ?>>
                                        <?php esc_html_e('Next', 'wp-alt-text-plugin'); ?>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <div class="bbai-debug-toast" data-debug-toast hidden></div>
                            </div>
                            
                            <!-- Context Button Handler (same as debug tab) -->
                            <script>
                            (function() {
                                function toggleContext(button) {
                                    var contextData = button.getAttribute('data-context-data');
                                    var rowIndex = button.getAttribute('data-row-index');
                                    
                                    if (!contextData || !rowIndex) {
                                        console.error('Missing context data or row index');
                                        return;
                                    }
                                    
                                    // Find the parent row
                                    var row = button;
                                    while (row && row.tagName !== 'TR') {
                                        row = row.parentElement;
                                    }
                                    if (!row) return;
                                    
                                    // Find the context row
                                    var contextRow = null;
                                    var nextSibling = row.nextElementSibling;
                                    while (nextSibling) {
                                        if (nextSibling.classList.contains('bbai-debug-context-row') && 
                                            nextSibling.getAttribute('data-row-index') === rowIndex) {
                                            contextRow = nextSibling;
                                            break;
                                        }
                                        if (!nextSibling.classList.contains('bbai-debug-context-row')) {
                                            break;
                                        }
                                        nextSibling = nextSibling.nextElementSibling;
                                    }
                                    
                                    // If context row doesn't exist, create it
                                    if (!contextRow) {
                                        contextRow = document.createElement('tr');
                                        contextRow.className = 'bbai-debug-context-row';
                                        contextRow.setAttribute('data-row-index', rowIndex);
                                        contextRow.style.display = 'none';
                                        
                                        var cell = document.createElement('td');
                                        cell.className = 'bbai-debug-context-cell';
                                        cell.colSpan = 4;
                                        
                                        var content = document.createElement('div');
                                        content.className = 'bbai-debug-context-content';
                                        
                                        var pre = document.createElement('pre');
                                        pre.className = 'bbai-debug-context-json';
                                        
                                        content.appendChild(pre);
                                        cell.appendChild(content);
                                        contextRow.appendChild(cell);
                                        
                                        // Insert after the current row
                                        if (row.nextSibling) {
                                            row.parentNode.insertBefore(contextRow, row.nextSibling);
                                        } else {
                                            row.parentNode.appendChild(contextRow);
                                        }
                                    }
                                    
                                    var preElement = contextRow.querySelector('pre.bbai-debug-context-json');
                                    if (!preElement) return;
                                    
                                    var isVisible = contextRow.style.display !== 'none';
                                    
                                    if (isVisible) {
                                        // Hide
                                        contextRow.style.display = 'none';
                                        button.textContent = 'Log Context';
                                        button.classList.remove('is-expanded');
                                    } else {
                                        // Show - decode and display
                                        var decoded = null;
                                        
                                        // Try to decode base64
                                        try {
                                            if (/^[A-Za-z0-9+\/]*={0,2}$/.test(contextData)) {
                                                var decodedStr = decodeURIComponent(escape(atob(contextData)));
                                                decoded = JSON.parse(decodedStr);
                                            }
                                        } catch(e1) {
                                            // Try direct JSON parse
                                            try {
                                                decoded = JSON.parse(contextData);
                                            } catch(e2) {
                                                // Try URL decode then parse
                                                try {
                                                    decoded = JSON.parse(decodeURIComponent(contextData));
                                                } catch(e3) {
                                                    decoded = { error: 'Unable to decode context data' };
                                                }
                                            }
                                        }
                                        
                                        var output = JSON.stringify(decoded, null, 2);
                                        preElement.textContent = output;
                                        contextRow.style.display = 'table-row';
                                        button.textContent = 'Hide Context';
                                        button.classList.add('is-expanded');
                                    }
                                }
                                
                                // Bind to all context buttons
                                document.addEventListener('click', function(e) {
                                    var btn = e.target;
                                    while (btn && btn !== document.body) {
                                        if (btn.classList && btn.classList.contains('bbai-debug-context-btn')) {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            toggleContext(btn);
                                            return;
                                        }
                                        btn = btn.parentElement;
                                    }
                                });
                            })();
                            </script>
                        <?php endif; ?>
                    </div>

                    <!-- Settings Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="settings" style="display: none;">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Settings', 'wp-alt-text-plugin'); ?></h3>
                        </div>
                        <?php
                        // Reuse settings content from settings tab (same as starting from line 2716)
                        // Pull fresh usage from backend to avoid stale cache in Settings
                        if (isset($this->api_client)) {
                            $live = $this->api_client->get_usage();
                            if (is_array($live) && !empty($live)) { BbAI_Usage_Tracker::update_usage($live); }
                        }
                        $usage_box = BbAI_Usage_Tracker::get_stats_display();
                        $o = wp_parse_args($opts, []);
                        
                        // Check for license plan first
                        $has_license = $this->api_client->has_active_license();
                        $license_data = $this->api_client->get_license_data();
                        
                        $plan = $usage_box['plan'] ?? 'free';
                        
                        // If license is active, use license plan
                        if ($has_license && $license_data && isset($license_data['organization'])) {
                            $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                            if ($license_plan !== 'free') {
                                $plan = $license_plan;
                            }
                        }
                        
                        $is_pro = $plan === 'pro';
                        $is_agency = $plan === 'agency';
                        $usage_percent = $usage_box['limit'] > 0 ? ($usage_box['used'] / $usage_box['limit'] * 100) : 0;

                        // Determine plan badge text
                        if ($is_agency) {
                            $plan_badge_text = esc_html__('AGENCY', 'wp-alt-text-plugin');
                        } elseif ($is_pro) {
                            $plan_badge_text = esc_html__('PRO', 'wp-alt-text-plugin');
                        } else {
                            $plan_badge_text = esc_html__('FREE', 'wp-alt-text-plugin');
                        }
                        ?>
                        <!-- Settings Page Content (full content from settings tab) -->
                        <div class="bbai-settings-page">
                            <!-- Site-Wide Settings Banner -->
                            <div class="bbai-settings-sitewide-banner">
                                <svg class="bbai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
                                    <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <div class="bbai-settings-sitewide-content">
                                    <strong class="bbai-settings-sitewide-title"><?php esc_html_e('Site-Wide Settings', 'wp-alt-text-plugin'); ?></strong>
                                    <span class="bbai-settings-sitewide-text">
                                        <?php esc_html_e('These settings apply to all users on this WordPress site.', 'wp-alt-text-plugin'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Plan Summary Card -->
                            <div class="bbai-settings-plan-summary-card">
                                <div class="bbai-settings-plan-badge-top">
                                    <span class="bbai-settings-plan-badge-text"><?php echo esc_html($plan_badge_text); ?></span>
                                </div>
                                <div class="bbai-settings-plan-quota">
                                    <div class="bbai-settings-plan-quota-meter">
                                        <span class="bbai-settings-plan-quota-used"><?php echo esc_html($usage_box['used']); ?></span>
                                        <span class="bbai-settings-plan-quota-divider">/</span>
                                        <span class="bbai-settings-plan-quota-limit"><?php echo esc_html($usage_box['limit']); ?></span>
                                    </div>
                                    <div class="bbai-settings-plan-quota-label">
                                        <?php esc_html_e('image descriptions', 'wp-alt-text-plugin'); ?>
                                    </div>
                                </div>
                                <div class="bbai-settings-plan-info">
                                    <?php if (!$is_pro && !$is_agency) : ?>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <span>
                                                <?php
                                                if (isset($usage_box['reset_date'])) {
                                                    printf(
                                                        esc_html__('Resets %s', 'wp-alt-text-plugin'),
                                                        '<strong>' . esc_html($usage_box['reset_date']) . '</strong>'
                                                    );
                                                } else {
                                                    esc_html_e('Monthly quota', 'wp-alt-text-plugin');
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Shared across all users', 'wp-alt-text-plugin'); ?></span>
                                        </div>
                                    <?php elseif ($is_agency) : ?>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Multi-site license', 'wp-alt-text-plugin'); ?></span>
                                        </div>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php echo sprintf(esc_html__('Resets %s', 'wp-alt-text-plugin'), '<strong>' . esc_html($usage_box['reset_date'] ?? 'Monthly') . '</strong>'); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Unlimited generations', 'wp-alt-text-plugin'); ?></span>
                                        </div>
                                        <div class="bbai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Priority support', 'wp-alt-text-plugin'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$is_pro && !$is_agency) : ?>
                                <button type="button" class="bbai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'wp-alt-text-plugin'); ?>
                                </button>
                                <?php endif; ?>
                            </div>

                            <!-- License Management Card -->
                            <?php
                            // Reuse license variables if already set above
                            if (!isset($has_license)) {
                                $has_license = $this->api_client->has_active_license();
                                $license_data = $this->api_client->get_license_data();
                            }
                            ?>

                            <div class="bbai-settings-card">
                                <div class="bbai-settings-card-header">
                                    <div class="bbai-settings-card-header-icon">
                                        <span class="bbai-settings-card-icon-emoji">🔑</span>
                                    </div>
                                    <h3 class="bbai-settings-card-title"><?php esc_html_e('License', 'wp-alt-text-plugin'); ?></h3>
                                </div>

                                <?php if ($has_license && $license_data) : ?>
                                    <?php
                                    $org = $license_data['organization'] ?? null;
                                    if ($org) :
                                    ?>
                                    <!-- Active License Display -->
                                    <div class="bbai-settings-license-active">
                                        <div class="bbai-settings-license-status">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                                <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <div>
                                                <div class="bbai-settings-license-title"><?php esc_html_e('License Active', 'wp-alt-text-plugin'); ?></div>
                                                <div class="bbai-settings-license-subtitle"><?php echo esc_html($org['name'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                        <button type="button" class="bbai-settings-license-deactivate-btn" data-action="deactivate-license">
                                            <?php esc_html_e('Deactivate', 'wp-alt-text-plugin'); ?>
                                        </button>
                                    </div>
                                    
                                    <?php 
                                    // Show site usage for agency licenses when authenticated
                                    $is_authenticated_for_license = $this->api_client->is_authenticated();
                                    $is_agency_license = isset($license_data['organization']['plan']) && $license_data['organization']['plan'] === 'agency';
                                    
                                    if ($is_agency_license && $is_authenticated_for_license) :
                                    ?>
                                    <!-- License Site Usage Section -->
                                    <div class="bbai-settings-license-sites" id="bbai-license-sites">
                                        <div class="bbai-settings-license-sites-header">
                                            <h3 class="bbai-settings-license-sites-title">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                                </svg>
                                                <?php esc_html_e('Sites Using This License', 'wp-alt-text-plugin'); ?>
                                            </h3>
                                        </div>
                                        <div class="bbai-settings-license-sites-content" id="bbai-license-sites-content">
                                            <div class="bbai-settings-license-sites-loading">
                                                <span class="bbai-spinner"></span>
                                                <?php esc_html_e('Loading site usage...', 'wp-alt-text-plugin'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php elseif ($is_agency_license && !$is_authenticated_for_license) : ?>
                                    <!-- Prompt to login for site usage -->
                                    <div class="bbai-settings-license-sites-auth">
                                        <p class="bbai-settings-license-sites-auth-text">
                                            <?php esc_html_e('Log in to view site usage and generation statistics for this license.', 'wp-alt-text-plugin'); ?>
                                        </p>
                                        <button type="button" class="bbai-settings-license-sites-auth-btn" data-action="show-auth-modal" data-auth-tab="login">
                                            <?php esc_html_e('Log In', 'wp-alt-text-plugin'); ?>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php endif; ?>
                                <?php else : ?>
                                    <!-- License Activation Form -->
                                    <div class="bbai-settings-license-form">
                                        <p class="bbai-settings-license-description">
                                            <?php esc_html_e('Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'wp-alt-text-plugin'); ?>
                                        </p>
                                        <form id="license-activation-form">
                                            <div class="bbai-settings-license-input-group">
                                                <label for="license-key-input" class="bbai-settings-license-label">
                                                    <?php esc_html_e('License Key', 'wp-alt-text-plugin'); ?>
                                                </label>
                                                <input type="text"
                                                       id="license-key-input"
                                                       name="license_key"
                                                       class="bbai-settings-license-input"
                                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                       pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
                                                       required>
                                            </div>
                                            <div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                                            <button type="submit" id="activate-license-btn" class="bbai-settings-license-activate-btn">
                                                <?php esc_html_e('Activate License', 'wp-alt-text-plugin'); ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Account Management Card -->
                            <div class="bbai-settings-card">
                                <div class="bbai-settings-card-header">
                                    <div class="bbai-settings-card-header-icon">
                                        <span class="bbai-settings-card-icon-emoji">👤</span>
                                    </div>
                                    <h3 class="bbai-settings-card-title"><?php esc_html_e('Account Management', 'wp-alt-text-plugin'); ?></h3>
                                </div>
                                
                                <?php if (!$is_pro && !$is_agency) : ?>
                                <div class="bbai-settings-account-info-banner">
                                    <span><?php esc_html_e('You are on the free plan.', 'wp-alt-text-plugin'); ?></span>
                                </div>
                                <div class="bbai-settings-account-upgrade-link">
                                    <button type="button" class="bbai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade Now', 'wp-alt-text-plugin'); ?>
                                    </button>
                                </div>
                                <?php else : ?>
                                <div class="bbai-settings-account-status">
                                    <span class="bbai-settings-account-status-label"><?php esc_html_e('Current Plan:', 'wp-alt-text-plugin'); ?></span>
                                    <span class="bbai-settings-account-status-value"><?php 
                                        if ($is_agency) {
                                            esc_html_e('Agency', 'wp-alt-text-plugin');
                                        } else {
                                            esc_html_e('Pro', 'wp-alt-text-plugin');
                                        }
                                    ?></span>
                                </div>
                                <?php 
                                // Check if using license vs authenticated account
                                $is_authenticated_for_account = $this->api_client->is_authenticated();
                                $is_license_only = $has_license && !$is_authenticated_for_account;
                                
                                if ($is_license_only) : 
                                    // License-based plan - provide contact info
                                ?>
                                <div class="bbai-settings-account-actions">
                                    <div class="bbai-settings-account-action-info">
                                        <p><strong><?php esc_html_e('License-Based Plan', 'wp-alt-text-plugin'); ?></strong></p>
                                        <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'wp-alt-text-plugin'); ?></p>
                                        <ul>
                                            <li><?php esc_html_e('Contact your license administrator', 'wp-alt-text-plugin'); ?></li>
                                            <li><?php esc_html_e('Email support for billing inquiries', 'wp-alt-text-plugin'); ?></li>
                                            <li><?php esc_html_e('View license details in the License section above', 'wp-alt-text-plugin'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                <?php elseif ($is_authenticated_for_account) : 
                                    // Authenticated user - show Stripe portal
                                ?>
                                <div class="bbai-settings-account-actions">
                                    <button type="button" class="bbai-settings-account-action-btn" data-action="manage-subscription">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                        </svg>
                                        <span><?php esc_html_e('Manage Subscription', 'wp-alt-text-plugin'); ?></span>
                                    </button>
                                    <div class="bbai-settings-account-action-info">
                                        <p><?php esc_html_e('In Stripe Customer Portal you can:', 'wp-alt-text-plugin'); ?></p>
                                        <ul>
                                            <li><?php esc_html_e('View and download invoices', 'wp-alt-text-plugin'); ?></li>
                                            <li><?php esc_html_e('Update payment method', 'wp-alt-text-plugin'); ?></li>
                                            <li><?php esc_html_e('View payment history', 'wp-alt-text-plugin'); ?></li>
                                            <li><?php esc_html_e('Cancel or modify subscription', 'wp-alt-text-plugin'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Settings Form -->
                            <form method="post" action="options.php" autocomplete="off">
                                <?php settings_fields('bbai_group'); ?>

                                <!-- Generation Settings Card -->
                                <div class="bbai-settings-card">
                                    <h3 class="bbai-settings-generation-title"><?php esc_html_e('Generation Settings', 'wp-alt-text-plugin'); ?></h3>

                                    <div class="bbai-settings-form-group">
                                        <div class="bbai-settings-form-field bbai-settings-form-field--toggle">
                                            <div class="bbai-settings-form-field-content">
                                                <label for="bbai-enable-on-upload" class="bbai-settings-form-label">
                                                    <?php esc_html_e('Auto-generate on Image Upload', 'wp-alt-text-plugin'); ?>
                                                </label>
                                                <p class="bbai-settings-form-description">
                                                    <?php esc_html_e('Automatically generate alt text when new images are uploaded to your media library.', 'wp-alt-text-plugin'); ?>
                                                </p>
                                            </div>
                                            <label class="bbai-settings-toggle">
                                                <input 
                                                    type="checkbox" 
                                                    id="bbai-enable-on-upload"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                                    value="1"
                                                    <?php checked(!empty($o['enable_on_upload'] ?? true)); ?>
                                                >
                                                <span class="bbai-settings-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="bbai-settings-form-group">
                                        <label for="bbai-tone" class="bbai-settings-form-label">
                                            <?php esc_html_e('Tone & Style', 'wp-alt-text-plugin'); ?>
                                        </label>
                                        <input
                                            type="text"
                                            id="bbai-tone"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                            value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                            placeholder="<?php esc_attr_e('professional, accessible', 'wp-alt-text-plugin'); ?>"
                                            class="bbai-settings-form-input"
                                        />
                                    </div>

                                    <div class="bbai-settings-form-group">
                                        <label for="bbai-custom-prompt" class="bbai-settings-form-label">
                                            <?php esc_html_e('Additional Instructions', 'wp-alt-text-plugin'); ?>
                                        </label>
                                        <textarea
                                            id="bbai-custom-prompt"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                            rows="4"
                                            placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'wp-alt-text-plugin'); ?>"
                                            class="bbai-settings-form-textarea"
                                        ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="bbai-settings-form-actions">
                                        <button type="submit" class="bbai-settings-save-btn">
                                            <?php esc_html_e('Save Settings', 'wp-alt-text-plugin'); ?>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Hidden nonce for AJAX requests -->
                            <input type="hidden" id="license-nonce" value="<?php echo esc_attr(wp_create_nonce('bbai_license_action')); ?>">
                        </div>
                    </div>
                </div>
                
                <script>
                (function($) {
                    'use strict';
                    
                    // Admin tab switching
                    $('.bbai-admin-tab').on('click', function(e) {
                        e.preventDefault();
                        
                        const $tab = $(this);
                        const tabName = $tab.data('admin-tab');
                        
                        // Update active tab
                        $('.bbai-admin-tab').removeClass('active');
                        $tab.addClass('active');
                        
                        // Show/hide content
                        $('.bbai-admin-tab-content').hide();
                        $('.bbai-admin-tab-content[data-admin-tab-content="' + tabName + '"]').show();
                        
                        // Load license site usage when switching to settings tab
                        if (tabName === 'settings' && typeof window.loadLicenseSiteUsage === 'function') {
                            // Small delay to ensure DOM is updated
                            setTimeout(function() {
                                window.loadLicenseSiteUsage();
                            }, 100);
                        }
                        
                        // Update URL hash without scrolling
                        if (history.pushState) {
                            history.pushState(null, null, '#' + tabName);
                        }
                    });
                    
                    // Check for hash on load
                    const hash = window.location.hash.replace('#', '');
                    if (hash === 'debug' || hash === 'settings') {
                        $('.bbai-admin-tab[data-admin-tab="' + hash + '"]').trigger('click');
                    }
                    
                    // Logout handler
                    $('#bbai-admin-logout-btn').on('click', function(e) {
                        e.preventDefault();
                        
                        if (!confirm('<?php echo esc_js(__('Are you sure you want to log out of the admin panel?', 'wp-alt-text-plugin')); ?>')) {
                            return;
                        }
                        
                        const $btn = $(this);
                        $btn.prop('disabled', true);
                        
                        $.ajax({
                            url: window.bbai_ajax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'bbai_admin_logout',
                                nonce: window.bbai_ajax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.location.href = response.data.redirect || window.location.href;
                                } else {
                                    alert(response.data?.message || 'Logout failed');
                                    $btn.prop('disabled', false);
                                }
                            },
                            error: function() {
                                alert('Network error. Please try again.');
                                $btn.prop('disabled', false);
                            }
                        });
                    });
                })(jQuery);
                </script>
            <?php endif; ?>
            </div><!-- .bbai-container -->
            
            <!-- Footer -->
            <div style="text-align: center; padding: 24px 0; margin-top: 48px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-bbai-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-bbai-text-generator/'); ?>" target="_blank" rel="noopener noreferrer" style="color: #14b8a6; text-decoration: none;"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-bbai-text-generator'); ?></a>
            </div>
        </div>
        
        <?php endif; // End tab check (dashboard/library/guide/debug/settings)
        
        // Include upgrade modal OUTSIDE of tab conditionals so it's always available
        // Set up currency for upgrade modal
        $locale = get_locale();
        // Default to USD
        $currency = ['symbol' => '$', 'code' => 'USD', 'pro' => 14.99, 'agency' => 34, 'credits' => 11.99];
        
        // Check for UK/British
        if (in_array($locale, ['en_GB', 'en_IM', 'en_JE', 'en_GG'])) {
            $currency = ['symbol' => '£', 'code' => 'GBP', 'pro' => 12.99, 'agency' => 29, 'credits' => 9.99];
        }
        // Check for European countries
        else if (in_array(substr($locale, 0, 2), ['de', 'fr', 'it', 'es', 'pt', 'nl', 'pl', 'el', 'cs', 'sk', 'hu', 'ro', 'bg', 'hr', 'sl', 'lt', 'lv', 'et', 'fi', 'sv', 'da'])) {
            $currency = ['symbol' => '€', 'code' => 'EUR', 'pro' => 12.99, 'agency' => 29, 'credits' => 9.99];
        }
        
        // Include upgrade modal - always available for all tabs
        $checkout_prices = $this->get_checkout_price_ids();
        include BBAI_PLUGIN_DIR . 'templates/upgrade-modal.php';
    }

    /**
     * Sanitize error messages to prevent exposing sensitive API information
     */
    private function sanitize_error_message($message) {
        if (!is_string($message)) {
            return $message;
        }
        
        // Remove URLs, tokens, API keys, and other sensitive data
        $sanitized = preg_replace(
            [
                '/https?:\/\/[^\s]+/i',  // Remove URLs
                '/Bearer\s+[A-Za-z0-9\-_\.]+/i',  // Remove Bearer tokens
                '/token[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove token values
                '/api[_-]?key[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove API keys
                '/secret[=:]\s*[A-Za-z0-9\-_\.]+/i',  // Remove secrets
                '/password[=:]\s*[^\s]+/i',  // Remove passwords
            ],
            '[REDACTED]',
            $message
        );
        
        return $sanitized;
    }

    private function build_prompt($attachment_id, $opts, $existing_alt = '', bool $is_retry = false, array $feedback = []){
        $file     = get_attached_file($attachment_id);
        $title_raw = get_the_title($attachment_id);
        $filename = $file ? wp_basename($file) : (is_string($title_raw) ? $title_raw : '');
        $title    = is_string($title_raw) ? $title_raw : '';
        $caption  = wp_get_attachment_caption($attachment_id);
        $parent_raw = get_post_field('post_title', wp_get_post_parent_id($attachment_id));
        $parent   = is_string($parent_raw) ? $parent_raw : '';
        $lang_raw = $opts['language'] ?? 'en-GB';
        if ($lang_raw === 'custom' && !empty($opts['language_custom'])){
            $lang = sanitize_text_field($opts['language_custom']);
        } else {
            $lang = $lang_raw;
        }
        $tone     = $opts['tone'] ?? 'professional, accessible';
        $max      = max(4, intval($opts['max_words'] ?? 16));

        $existing_alt = is_string($existing_alt) ? trim($existing_alt) : '';
        $context_bits = array_filter([$title, $caption, $parent, $existing_alt ? ('Existing ALT: ' . $existing_alt) : '']);
        $context = $context_bits ? ("Context: " . implode(' | ', $context_bits)) : '';

        $custom = trim($opts['custom_prompt'] ?? '');
        $instruction = "Write concise, descriptive ALT text in {$lang} for the provided image. "
               . "Limit to {$max} words. Tone: {$tone}. "
               . "Describe the primary subject with concrete nouns; include one visible colour/texture and any clearly visible background. "
               . "Only describe what is visible; no guessing about intent, brand, or location unless unmistakable. "
               . "If the image is a text/wordmark/logo (e.g., filename/title contains 'logo', 'icon', 'wordmark', or the image is mostly text), respond with a short accurate phrase like 'Red “TEST” wordmark' rather than a scene description. "
               . "Avoid 'image of' / 'photo of' and never output placeholders like 'test' or 'sample'. "
               . "Return only the ALT text sentence.";

        if ($existing_alt){
            $instruction .= " The previous ALT text is provided for context and must be improved upon.";
        }

        if ($is_retry){
            $instruction .= " The previous attempt was rejected; ensure this version corrects the issues listed below and adds concrete, specific detail.";
        }

        $feedback_lines = array_filter(array_map('trim', $feedback));
        $feedback_block = '';
        if ($feedback_lines){
            $feedback_block = "\nReviewer feedback:";
            foreach ($feedback_lines as $line){
                $feedback_block .= "\n- " . sanitize_text_field($line);
            }
            $feedback_block .= "\n";
        }

        $prompt = ($custom ? $custom . "\n\n" : '')
               . $instruction
               . "\nFilename: {$filename}\n{$context}\n" . $feedback_block;
        return apply_filters('bbai_prompt', $prompt, $attachment_id, $opts);
    }

    private function is_image($attachment_id){
        $mime = get_post_mime_type($attachment_id);
        return strpos((string)$mime, 'image/') === 0;
    }

    public function invalidate_stats_cache(){
        wp_cache_delete('beepbeepai_stats', 'bbai');
        delete_transient('beepbeepai_stats_v3');
        $this->stats_cache = null;
    }

    public function get_media_stats(){
        try {
            // Check in-memory cache first
            if (is_array($this->stats_cache)){
                return $this->stats_cache;
            }

            // Check object cache (Redis/Memcached if available)
            $cache_key = 'beepbeepai_stats';
            $cache_group = 'bbai';
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)){
                $this->stats_cache = $cached;
                return $cached;
            }

            // Check transient cache (15 minute TTL for DB queries - optimized for performance)
            $transient_key = 'beepbeepai_stats_v3';
            $cached = get_transient($transient_key);
            if (false !== $cached && is_array($cached)){
                // Also populate object cache for next request
                wp_cache_set($cache_key, $cached, $cache_group, 15 * MINUTE_IN_SECONDS);
                $this->stats_cache = $cached;
                return $cached;
            }

            global $wpdb;

            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
                'attachment', 'image/%'
            ));

            $with_alt = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                 WHERE p.post_type = %s
                   AND p.post_status = %s
                   AND p.post_mime_type LIKE %s
                   AND m.meta_key = %s
                   AND TRIM(m.meta_value) <> ''",
                'attachment',
                'inherit',
                'image/%',
                '_wp_attachment_image_alt'
            ));

            $generated = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_beepbeepai_generated_at'
            ));

            $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
            $missing  = max(0, $total - $with_alt);

            $opts = get_option(self::OPTION_KEY, []);
            $usage = $opts['usage'] ?? $this->default_usage();
            if (!empty($usage['last_request'])){
                $date_format_raw = get_option('date_format');
                $time_format_raw = get_option('time_format');
                $date_format = is_string($date_format_raw) ? $date_format_raw : '';
                $time_format = is_string($time_format_raw) ? $time_format_raw : '';
                $format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
                $usage['last_request_formatted'] = mysql2date($format, $usage['last_request']);
            }

            $latest_generated_raw = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1",
                '_beepbeepai_generated_at'
            ));
            $date_format_raw = get_option('date_format');
            $time_format_raw = get_option('time_format');
            $date_format = is_string($date_format_raw) ? $date_format_raw : '';
            $time_format = is_string($time_format_raw) ? $time_format_raw : '';
            $format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
            $latest_generated = $latest_generated_raw ? mysql2date($format, $latest_generated_raw) : '';

            $top_source_row = $wpdb->get_row(
                "SELECT meta_value AS source, COUNT(*) AS count
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_beepbeepai_source' AND meta_value <> ''
                 GROUP BY meta_value
                 ORDER BY COUNT(*) DESC
                 LIMIT 1",
                ARRAY_A
            );
            $top_source_key = sanitize_key($top_source_row['source'] ?? '');
            $top_source_count = intval($top_source_row['count'] ?? 0);

            $this->stats_cache = [
                'total'     => $total,
                'with_alt'  => $with_alt,
                'missing'   => $missing,
                'generated' => $generated,
                'coverage'  => $coverage,
                'usage'     => $usage,
                'token_limit' => intval($opts['token_limit'] ?? 0),
                'latest_generated' => $latest_generated,
                'latest_generated_raw' => $latest_generated_raw,
                'top_source_key' => $top_source_key,
                'top_source_count' => $top_source_count,
                'dry_run_enabled' => !empty($opts['dry_run']),
                'audit' => $this->get_usage_rows(10),
            ];

            // Cache for 15 minutes (optimized - stats don't change frequently)
            wp_cache_set($cache_key, $this->stats_cache, $cache_group, 15 * MINUTE_IN_SECONDS);
            set_transient($transient_key, $this->stats_cache, 15 * MINUTE_IN_SECONDS);

            return $this->stats_cache;
        } catch ( \Exception $e ) {
            // If stats query fails, return empty stats array to prevent breaking REST responses
            // Silent failure - stats are non-critical
            return [
                'total' => 0,
                'with_alt' => 0,
                'missing_alt' => 0,
                'ai_generated' => 0,
                'manual' => 0,
                'coverage' => 0,
            ];
        }
    }

    public function prepare_attachment_snapshot($attachment_id){
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0){
            return [];
        }

        $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $tokens = intval(get_post_meta($attachment_id, '_beepbeepai_tokens_total', true));
        $prompt = intval(get_post_meta($attachment_id, '_beepbeepai_tokens_prompt', true));
        $completion = intval(get_post_meta($attachment_id, '_beepbeepai_tokens_completion', true));
        $generated_raw = get_post_meta($attachment_id, '_beepbeepai_generated_at', true);
        $generated = $generated_raw ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $generated_raw) : '';
        $source_key = sanitize_key(get_post_meta($attachment_id, '_beepbeepai_source', true) ?: 'unknown');
        if (!$source_key){
            $source_key = 'unknown';
        }

        $analysis = $this->evaluate_alt_health($attachment_id, $alt);

        return [
            'id' => $attachment_id,
            'alt' => $alt,
            'tokens' => $tokens,
            'prompt' => $prompt,
            'completion' => $completion,
            'generated_raw' => $generated_raw,
            'generated' => $generated,
            'source_key' => $source_key,
            'source_label' => $this->format_source_label($source_key),
            'source_description' => $this->format_source_description($source_key),
            'score' => $analysis['score'],
            'score_grade' => $analysis['grade'],
            'score_status' => $analysis['status'],
            'score_issues' => $analysis['issues'],
            'score_summary' => $analysis['review']['summary'] ?? '',
            'analysis' => $analysis,
        ];
    }

    private function hash_alt_text(string $alt): string{
        $alt = strtolower(trim((string) $alt));
        $alt = preg_replace('/\s+/', ' ', $alt);
        return wp_hash($alt);
    }

    private function purge_review_meta(int $attachment_id): void{
        $keys = [
            '_beepbeepai_review_score',
            '_beepbeepai_review_status',
            '_beepbeepai_review_grade',
            '_beepbeepai_review_summary',
            '_beepbeepai_review_issues',
            '_beepbeepai_review_model',
            '_beepbeepai_reviewed_at',
            '_beepbeepai_review_alt_hash',
        ];
        foreach ($keys as $key){
            delete_post_meta($attachment_id, $key);
        }
    }

    private function get_review_snapshot(int $attachment_id, string $current_alt = ''): ?array{
        $score = intval(get_post_meta($attachment_id, '_beepbeepai_review_score', true));
        if ($score <= 0){
            return null;
        }

        $stored_hash = get_post_meta($attachment_id, '_beepbeepai_review_alt_hash', true);
        if ($current_alt !== ''){
            $current_hash = $this->hash_alt_text($current_alt);
            if ($stored_hash && !hash_equals($stored_hash, $current_hash)){
                $this->purge_review_meta($attachment_id);
                return null;
            }
        }

        $status     = sanitize_key(get_post_meta($attachment_id, '_beepbeepai_review_status', true));
        $grade_raw  = get_post_meta($attachment_id, '_beepbeepai_review_grade', true);
        $summary    = get_post_meta($attachment_id, '_beepbeepai_review_summary', true);
        $model      = get_post_meta($attachment_id, '_beepbeepai_review_model', true);
        $reviewed_at = get_post_meta($attachment_id, '_beepbeepai_reviewed_at', true);

        $issues_raw = get_post_meta($attachment_id, '_beepbeepai_review_issues', true);
        $issues = [];
        if ($issues_raw){
            $decoded = json_decode($issues_raw, true);
            if (is_array($decoded)){
                foreach ($decoded as $issue){
                    if (is_string($issue)){
                        $issue = sanitize_text_field($issue);
                        if ($issue !== ''){
                            $issues[] = $issue;
                        }
                    }
                }
            }
        }

        return [
            'score'   => max(0, min(100, $score)),
            'status'  => $status ?: null,
            'grade'   => is_string($grade_raw) ? sanitize_text_field($grade_raw) : null,
            'summary' => is_string($summary) ? sanitize_text_field($summary) : '',
            'issues'  => $issues,
            'model'   => is_string($model) ? sanitize_text_field($model) : '',
            'reviewed_at' => is_string($reviewed_at) ? $reviewed_at : '',
            'hash_present' => !empty($stored_hash),
        ];
    }

    private function evaluate_alt_health(int $attachment_id, string $alt): array{
        $alt = trim((string) $alt);
        if ($alt === ''){
            return [
                'score' => 0,
                'grade' => __('Missing', 'wp-alt-text-plugin'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'wp-alt-text-plugin')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'wp-alt-text-plugin'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'wp-alt-text-plugin')],
                ],
                'review' => null,
            ];
        }

        $score = 100;
        $issues = [];

        $normalized = strtolower(trim($alt));
        $placeholder_pattern = '/^(test|testing|sample|example|dummy|placeholder|alt(?:\s+text)?|image|photo|picture|n\/a|none|lorem)$/';
        if ($normalized === '' || preg_match($placeholder_pattern, $normalized)){
            return [
                'score' => 0,
                'grade' => __('Critical', 'wp-alt-text-plugin'),
                'status' => 'critical',
                'issues' => [__('ALT text looks like placeholder content and must be rewritten.', 'wp-alt-text-plugin')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Critical', 'wp-alt-text-plugin'),
                    'status'=> 'critical',
                    'issues'=> [__('ALT text looks like placeholder content and must be rewritten.', 'wp-alt-text-plugin')],
                ],
                'review' => null,
            ];
        }

        $length = function_exists('mb_strlen') ? mb_strlen($alt) : strlen($alt);
        if ($length < 45){
            $score -= 35;
            $issues[] = __('Too short – add a richer description (45+ characters).', 'wp-alt-text-plugin');
        } elseif ($length > 160){
            $score -= 15;
            $issues[] = __('Very long – trim to keep the description concise (under 160 characters).', 'wp-alt-text-plugin');
        }

        if (preg_match('/\b(image|picture|photo|screenshot)\b/i', $alt)){
            $score -= 10;
            $issues[] = __('Contains generic filler words like “image” or “photo”.', 'wp-alt-text-plugin');
        }

        if (preg_match('/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt)){
            $score = min($score - 80, 5);
            $issues[] = __('Contains placeholder wording such as “test” or “sample”. Replace with a real description.', 'wp-alt-text-plugin');
        }

        $word_count = str_word_count($alt, 0, '0123456789');
        if ($word_count < 4){
            $score -= 70;
            $score = min($score, 5);
            $issues[] = __('ALT text is extremely brief – add meaningful descriptive words.', 'wp-alt-text-plugin');
        } elseif ($word_count < 6){
            $score -= 50;
            $score = min($score, 20);
            $issues[] = __('ALT text is too short to convey the subject in detail.', 'wp-alt-text-plugin');
        } elseif ($word_count < 8){
            $score -= 35;
            $score = min($score, 40);
            $issues[] = __('ALT text could use a few more descriptive words.', 'wp-alt-text-plugin');
        }

        if ($score > 40 && $length < 30){
            $score = min($score, 40);
            $issues[] = __('Expand the description with one or two concrete details.', 'wp-alt-text-plugin');
        }

        $normalize = static function($value){
            $value = strtolower((string) $value);
            $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
            return trim(preg_replace('/\s+/', ' ', $value));
        };

        $normalized_alt = $normalize($alt);
        $title = get_the_title($attachment_id);
        if ($title && $normalized_alt !== ''){
            $normalized_title = $normalize($title);
            if ($normalized_title !== '' && $normalized_alt === $normalized_title){
                $score -= 12;
                $issues[] = __('Matches the attachment title – add more unique detail.', 'wp-alt-text-plugin');
            }
        }

        $file = get_attached_file($attachment_id);
        if ($file && $normalized_alt !== ''){
            $base = pathinfo($file, PATHINFO_FILENAME);
            $normalized_base = $normalize($base);
            if ($normalized_base !== '' && $normalized_alt === $normalized_base){
                $score -= 20;
                $issues[] = __('Matches the file name – rewrite it to describe the image.', 'wp-alt-text-plugin');
            }
        }

        if (!preg_match('/[a-z]{4,}/i', $alt)){
            $score -= 15;
            $issues[] = __('Lacks descriptive language – include meaningful nouns or adjectives.', 'wp-alt-text-plugin');
        }

        if (!preg_match('/\b[a-z]/i', $alt)){
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        if ($status === 'review' && empty($issues)){
            $issues[] = __('Give this ALT another look to ensure it reflects the image details.', 'wp-alt-text-plugin');
        } elseif ($status === 'critical' && empty($issues)){
            $issues[] = __('ALT text should be rewritten for accessibility.', 'wp-alt-text-plugin');
        }

        $heuristic = [
            'score' => $score,
            'grade' => $grade,
            'status'=> $status,
            'issues'=> array_values(array_unique($issues)),
        ];

        $review = $this->get_review_snapshot($attachment_id, $alt);
        if ($review && empty($review['hash_present']) && $heuristic['score'] < $review['score']){
            $review = null;
        }
        if ($review){
            $final_score = min($heuristic['score'], $review['score']);
            $review_status = $review['status'] ?: $this->status_from_score($review['score']);
            $final_status = $this->worst_status($heuristic['status'], $review_status);
            $final_grade  = $review['grade'] ?: $this->grade_from_status($final_status);

            $combined_issues = [];
            if (!empty($review['summary'])){
                $combined_issues[] = $review['summary'];
            }
            if (!empty($review['issues'])){
                $combined_issues = array_merge($combined_issues, $review['issues']);
            }
            $combined_issues = array_merge($combined_issues, $heuristic['issues']);
            $combined_issues = array_values(array_unique(array_filter($combined_issues)));

            return [
                'score' => $final_score,
                'grade' => $final_grade,
                'status'=> $final_status,
                'issues'=> $combined_issues,
                'heuristic' => $heuristic,
                'review'    => $review,
            ];
        }

        return [
            'score' => $heuristic['score'],
            'grade' => $heuristic['grade'],
            'status'=> $heuristic['status'],
            'issues'=> $heuristic['issues'],
            'heuristic' => $heuristic,
            'review'    => null,
        ];
    }

    private function status_from_score(int $score): string{
        if ($score >= 90){
            return 'great';
        }
        if ($score >= 75){
            return 'good';
        }
        if ($score >= 60){
            return 'review';
        }
        return 'critical';
    }

    private function grade_from_status(string $status): string{
        switch ($status){
            case 'great':
                return __('Excellent', 'wp-alt-text-plugin');
            case 'good':
                return __('Strong', 'wp-alt-text-plugin');
            case 'review':
                return __('Needs review', 'wp-alt-text-plugin');
            default:
                return __('Critical', 'wp-alt-text-plugin');
        }
    }

    private function worst_status(string $first, string $second): string{
        $weights = [
            'great' => 1,
            'good' => 2,
            'review' => 3,
            'critical' => 4,
        ];
        $first_weight = $weights[$first] ?? 2;
        $second_weight = $weights[$second] ?? 2;
        return $first_weight >= $second_weight ? $first : $second;
    }

    public function get_missing_attachment_ids($limit = 5){
        global $wpdb;
        $limit = intval($limit);
        if ($limit <= 0){
            $limit = 5;
        }

        $sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m
               ON (p.ID = m.post_id AND m.meta_key = '_wp_attachment_image_alt')
             WHERE p.post_type = %s
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE %s
               AND (m.meta_value IS NULL OR TRIM(m.meta_value) = '')
             ORDER BY p.ID DESC
             LIMIT %d",
            'attachment', 'image/%', $limit
        );

        return array_map('intval', (array) $wpdb->get_col($sql));
    }

    public function get_all_attachment_ids($limit = 5, $offset = 0){
        global $wpdb;
        $limit  = max(1, intval($limit));
        $offset = max(0, intval($offset));

        $sql = $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = '_beepbeepai_generated_at'
             WHERE p.post_type = %s
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE %s
             ORDER BY
                 CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC,
                 p.ID DESC
             LIMIT %d OFFSET %d",
            'attachment', 'image/%', $limit, $offset
        );

        $rows = $wpdb->get_col($sql);
        return array_map('intval', (array) $rows);
    }

    private function get_usage_rows($limit = 10, $include_all = false){
        global $wpdb;
        $limit = max(1, intval($limit));

        $cache_key = 'beepbeepai_usage_rows_' . md5($limit . '|' . ($include_all ? 'all' : 'slice'));
        if (!$include_all) {
            $cached = wp_cache_get($cache_key, 'bbai');
            if ($cached !== false) {
                return $cached;
            }
        }
        $base_query = "SELECT p.ID,
                       tokens.meta_value AS tokens_total,
                       prompt.meta_value AS tokens_prompt,
                       completion.meta_value AS tokens_completion,
                       alt.meta_value AS alt_text,
                       src.meta_value AS source,
                       model.meta_value AS model,
                       gen.meta_value AS generated_at
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} completion ON completion.post_id = p.ID AND completion.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} model ON model.post_id = p.ID AND model.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = %s
                WHERE p.post_type = %s AND p.post_mime_type LIKE %s
                ORDER BY
                    CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC,
                    CAST(tokens.meta_value AS UNSIGNED) DESC";

        $prepare_params = [
            '_beepbeepai_tokens_total',
            '_beepbeepai_tokens_prompt',
            '_beepbeepai_tokens_completion',
            '_wp_attachment_image_alt',
            '_beepbeepai_source',
            '_beepbeepai_model',
            '_beepbeepai_generated_at',
            'attachment',
            'image/%'
        ];

        if (!$include_all){
            $base_query .= ' LIMIT %d';
            $prepare_params[] = $limit;
        }

        $sql = $wpdb->prepare($base_query, ...$prepare_params);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)){
            if (!$include_all) {
                wp_cache_set($cache_key, [], 'bbai', MINUTE_IN_SECONDS * 2);
            }
            return [];
        }

        $formatted = array_map(function($row){
            $generated = $row['generated_at'] ?? '';
            if ($generated){
                $generated = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $generated);
            }

            $source = sanitize_key($row['source'] ?? 'unknown');
            if (!$source){ $source = 'unknown'; }

            $thumb = wp_get_attachment_image_src($row['ID'], 'thumbnail');

            return [
                'id'         => intval($row['ID']),
                'title'      => get_the_title($row['ID']),
                'alt'        => $row['alt_text'] ?? '',
                'tokens'     => intval($row['tokens_total'] ?? 0),
                'prompt'     => intval($row['tokens_prompt'] ?? 0),
                'completion' => intval($row['tokens_completion'] ?? 0),
                'source'     => $source,
                'source_label' => $this->format_source_label($source),
                'source_description' => $this->format_source_description($source),
                'model'      => $row['model'] ?? '',
                'generated'  => $generated,
                'thumb'      => $thumb ? $thumb[0] : '',
                'details_url'=> add_query_arg('item', $row['ID'], admin_url('upload.php')) . '#attachment_alt',
                'view_url'   => get_attachment_link($row['ID']),
            ];
        }, $rows);

        if (!$include_all) {
            wp_cache_set($cache_key, $formatted, 'bbai', MINUTE_IN_SECONDS * 5);
        }

        return $formatted;
    }

    private function get_source_meta_map(){
        return [
            'auto'     => [
                'label' => __('Auto (upload)', 'wp-alt-text-plugin'),
                'description' => __('Generated automatically when the image was uploaded.', 'wp-alt-text-plugin'),
            ],
            'ajax'     => [
                'label' => __('Media Library (single)', 'wp-alt-text-plugin'),
                'description' => __('Triggered from the Media Library row action or attachment details screen.', 'wp-alt-text-plugin'),
            ],
            'bulk'     => [
                'label' => __('Media Library (bulk)', 'wp-alt-text-plugin'),
                'description' => __('Generated via the Media Library bulk action.', 'wp-alt-text-plugin'),
            ],
            'dashboard' => [
                'label' => __('Dashboard quick actions', 'wp-alt-text-plugin'),
                'description' => __('Generated from the dashboard buttons.', 'wp-alt-text-plugin'),
            ],
            'wpcli'    => [
                'label' => __('WP-CLI', 'wp-alt-text-plugin'),
                'description' => __('Generated via the wp ai-alt CLI command.', 'wp-alt-text-plugin'),
            ],
            'manual'   => [
                'label' => __('Manual / custom', 'wp-alt-text-plugin'),
                'description' => __('Generated by custom code or integration.', 'wp-alt-text-plugin'),
            ],
            'unknown'  => [
                'label' => __('Unknown', 'wp-alt-text-plugin'),
                'description' => __('Source not recorded for this ALT text.', 'wp-alt-text-plugin'),
            ],
        ];
    }

    private function format_source_label($key){
        $map = $this->get_source_meta_map();
        $key = sanitize_key($key ?: 'unknown');
        return $map[$key]['label'] ?? $map['unknown']['label'];
    }

    private function format_source_description($key){
        $map = $this->get_source_meta_map();
        $key = sanitize_key($key ?: 'unknown');
        return $map[$key]['description'] ?? $map['unknown']['description'];
    }

    public function handle_usage_export(){
        if (!$this->user_can_manage()){
            wp_die(__('You do not have permission to export usage data.', 'wp-alt-text-plugin'));
        }
        check_admin_referer('bbai_usage_export');

        $rows = $this->get_usage_rows(10, true);
        $filename = 'bbai-usage-' . gmdate('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Attachment ID', 'Title', 'ALT Text', 'Source', 'Model', 'Generated At']);
        foreach ($rows as $row){
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['alt'],
                $row['source'],
                $row['tokens'],
                $row['prompt'],
                $row['completion'],
                $row['model'],
                '',
            ]);
        }
        fclose($output);
        exit;
    }

    public function handle_debug_log_export() {
        if (!$this->user_can_manage()){
            wp_die(__('You do not have permission to export debug logs.', 'wp-alt-text-plugin'));
        }
        check_admin_referer('bbai_debug_export');

        if (!class_exists('BbAI_Debug_Log')) {
            wp_die(__('Debug logging is not available.', 'wp-alt-text-plugin'));
        }

        global $wpdb;
        $table = BbAI_Debug_Log::table();
        // Table name is validated by the class, but ensure it's safe
        $table = esc_sql($table);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped by class method
        $rows = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bbai-debug-logs-' . gmdate('Ymd-His') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'Level', 'Message', 'Source', 'Context']);
        foreach ($rows as $row){
            $context = $row['context'] ?? '';
            fputcsv($output, [
                $row['created_at'],
                $row['level'],
                $row['message'],
                $row['source'],
                $context,
            ]);
        }
        fclose($output);
        exit;
    }

    private function redact_api_token($message){
        if (!is_string($message) || $message === ''){
            return $message;
        }

        $mask = function($token){
            $len = strlen($token);
            if ($len <= 8){
                return str_repeat('*', $len);
            }
            return substr($token, 0, 4) . str_repeat('*', $len - 8) . substr($token, -4);
        };

        $message = preg_replace_callback('/(Incorrect API key provided:\s*)(\S+)/i', function($matches) use ($mask){
            return $matches[1] . $mask($matches[2]);
        }, $message);

        $message = preg_replace_callback('/(sk-[A-Za-z0-9]{4})([A-Za-z0-9]{10,})([A-Za-z0-9]{4})/i', function($matches){
            return $matches[1] . str_repeat('*', strlen($matches[2])) . $matches[3];
        }, $message);

        return $message;
    }

    private function extract_json_object(string $content){
        $content = trim($content);
        if ($content === ''){
            return null;
        }

        if (stripos($content, '```') !== false){
            $content = preg_replace('/```json/i', '', $content);
            $content = str_replace('```', '', $content);
            $content = trim($content);
        }

        if ($content !== '' && is_string($content) && isset($content[0]) && $content[0] !== '{'){
            $start = strpos((string)$content, '{');
            $end   = strrpos((string)$content, '}');
            if ($start !== false && $end !== false && $end > $start){
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
            return $decoded;
        }

        return null;
    }

    private function should_retry_without_image($error){
        if (!is_wp_error($error)){
            return false;
        }

        if ($error->get_error_code() !== 'api_error'){
            return false;
        }

        $error_message = $error->get_error_message();
        if (!is_string($error_message) || empty($error_message)) {
            return false;
        }
        $message = strtolower($error_message);
        $needles = ['error while downloading', 'failed to download', 'unsupported image url'];
        foreach ($needles as $needle){
            if (is_string($message) && strpos((string)$message, (string)$needle) !== false){
                return true;
            }
        }

        $data = $error->get_error_data();
        if (is_array($data)){
            if (!empty($data['message']) && is_string($data['message'])){
                $msg = strtolower($data['message']);
                foreach ($needles as $needle){
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false){
                        return true;
                    }
                }
            }
            if (!empty($data['body']['error']['message']) && is_string($data['body']['error']['message'])){
                $msg = strtolower($data['body']['error']['message']);
                foreach ($needles as $needle){
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false){
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function build_inline_image_payload($attachment_id){
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)){
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'wp-alt-text-plugin'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0){
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'wp-alt-text-plugin'));
        }

        $limit = apply_filters('bbai_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit){
            return new \WP_Error('inline_image_too_large', __('Image exceeds the inline embedding size limit.', 'wp-alt-text-plugin'), ['size' => $size, 'limit' => $limit]);
        }

        $contents = file_get_contents($file);
        if ($contents === false){
            return new \WP_Error('inline_image_read_failed', __('Unable to read the image file for inline embedding.', 'wp-alt-text-plugin'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (empty($mime)){
            $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'image/jpeg';
        }

        $base64 = base64_encode($contents);
        if (!$base64){
            return new \WP_Error('inline_image_encode_failed', __('Failed to encode the image for inline embedding.', 'wp-alt-text-plugin'));
        }

        unset($contents);

        return [
            'payload' => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . $base64,
                ],
            ],
        ];
    }

    private function review_alt_text_with_model(int $attachment_id, string $alt, string $image_strategy, $image_payload_used, array $opts, string $api_key){
        $alt = trim((string) $alt);
        if ($alt === ''){
            return new \WP_Error('review_skipped', __('ALT text is empty; skipped review.', 'wp-alt-text-plugin'));
        }

        $review_model = $opts['review_model'] ?? ($opts['model'] ?? 'gpt-4o-mini');
        $review_model = apply_filters('bbai_review_model', $review_model, $attachment_id, $opts);
        if (!$review_model){
            return new \WP_Error('review_model_missing', __('No review model configured.', 'wp-alt-text-plugin'));
        }

        $image_payload = $image_payload_used;
        if (!$image_payload) {
            if ($image_strategy === 'inline') {
                $inline = $this->build_inline_image_payload($attachment_id);
                if (!is_wp_error($inline)) {
                    $image_payload = $inline['payload'];
                }
            } else {
                $url = wp_get_attachment_url($attachment_id);
                if ($url) {
                    $image_payload = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $url,
                        ],
                    ];
                }
            }
        }

        $title = get_the_title($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $filename = $file_path ? wp_basename($file_path) : '';

        $context_lines = [];
        if ($title){
            $context_lines[] = sprintf(__('Media title: %s', 'wp-alt-text-plugin'), $title);
        }
        if ($filename){
            $context_lines[] = sprintf(__('Filename: %s', 'wp-alt-text-plugin'), $filename);
        }

        $quoted_alt = str_replace('"', '\"', (string)($alt ?? ''));

        $instructions = "You are an accessibility QA assistant. Review the provided ALT text for the accompanying image. "
            . "Flag hallucinated details, inaccurate descriptions, missing primary subjects, demographic assumptions, or awkward phrasing. "
            . "Confirm the sentence mentions the main subject and at least one visible attribute such as colour, texture, motion, or background context. "
            . "Score strictly: reward ALT text only when it accurately and concisely describes the image. "
            . "If the ALT text contains placeholder wording (for example ‘test’, ‘sample’, ‘dummy text’, ‘image’, ‘photo’) anywhere in the sentence, or omits the primary subject, score it 10 or lower. "
            . "Extremely short descriptions (fewer than six words) should rarely exceed a score of 30.";

        $text_block = $instructions . "\n\n"
            . "ALT text candidate: \"" . $quoted_alt . "\"\n";

        if ($context_lines){
            $text_block .= implode("\n", $context_lines) . "\n";
        }

        $text_block .= "\nReturn valid JSON with keys: "
            . "score (integer 0-100), verdict (excellent, good, review, or critical), "
            . "summary (short sentence), and issues (array of short strings). "
            . "Do not include any additional keys or explanatory prose.";

        $user_content = [
            [
                'type' => 'text',
                'text' => $text_block,
            ],
        ];

        if ($image_payload){
            $user_content[] = $image_payload;
        }

        $body = [
            'model' => $review_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an impartial accessibility QA reviewer. Always return strict JSON and be conservative when scoring.',
                ],
                [
                    'role' => 'user',
                    'content' => $user_content,
                ],
            ],
            'temperature' => 0.1,
            'max_tokens' => 280,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)){
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        if ($code >= 300 || empty($data['choices'][0]['message']['content'])){
            $api_message = isset($data['error']['message']) ? $data['error']['message'] : ($raw_body ?: 'OpenAI review failed.');
            $api_message = $this->redact_api_token($api_message);
            return new \WP_Error('review_api_error', $api_message, ['status' => $code, 'body' => $data]);
        }

        $content = $data['choices'][0]['message']['content'];
        $parsed = $this->extract_json_object($content);
        if (!$parsed){
            return new \WP_Error('review_parse_failed', __('Unable to parse review response.', 'wp-alt-text-plugin'), ['response' => $content]);
        }

        $score = isset($parsed['score']) ? intval($parsed['score']) : 0;
        $score = max(0, min(100, $score));

        $verdict = isset($parsed['verdict']) ? strtolower(trim((string) $parsed['verdict'])) : '';
        $status_map = [
            'excellent' => 'great',
            'great'     => 'great',
            'good'      => 'good',
            'strong'    => 'good',
            'review'    => 'review',
            'needs review' => 'review',
            'warning'   => 'review',
            'critical'  => 'critical',
            'fail'      => 'critical',
            'poor'      => 'critical',
        ];
        $status = $status_map[$verdict] ?? null;
        if (!$status){
            $status = $this->status_from_score($score);
        }

        $summary = isset($parsed['summary']) ? sanitize_text_field($parsed['summary']) : '';
        if (!$summary && isset($parsed['justification'])){
            $summary = sanitize_text_field($parsed['justification']);
        }

        $issues = [];
        if (!empty($parsed['issues']) && is_array($parsed['issues'])){
            foreach ($parsed['issues'] as $issue){
                $issue = sanitize_text_field($issue);
                if ($issue !== ''){
                    $issues[] = $issue;
                }
            }
        }

        $issues = array_values(array_unique($issues));

        $usage_summary = [
            'prompt'     => intval($data['usage']['prompt_tokens'] ?? 0),
            'completion' => intval($data['usage']['completion_tokens'] ?? 0),
            'total'      => intval($data['usage']['total_tokens'] ?? 0),
        ];

        return [
            'score'   => $score,
            'status'  => $status,
            'grade'   => $this->grade_from_status($status),
            'summary' => $summary,
            'issues'  => $issues,
            'model'   => $review_model,
            'usage'   => $usage_summary,
            'verdict' => $verdict,
        ];
    }

    public function persist_generation_result(int $attachment_id, string $alt, array $usage_summary, string $source, string $model, string $image_strategy, $review_result): void{
        update_post_meta($attachment_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
        update_post_meta($attachment_id, '_beepbeepai_source', $source);
        update_post_meta($attachment_id, '_beepbeepai_model', $model);
        update_post_meta($attachment_id, '_beepbeepai_generated_at', current_time('mysql'));
        update_post_meta($attachment_id, '_beepbeepai_tokens_prompt', $usage_summary['prompt']);
        update_post_meta($attachment_id, '_beepbeepai_tokens_completion', $usage_summary['completion']);
        update_post_meta($attachment_id, '_beepbeepai_tokens_total', $usage_summary['total']);

        if ($image_strategy === 'remote'){
            delete_post_meta($attachment_id, '_beepbeepai_image_reference');
        } else {
            update_post_meta($attachment_id, '_beepbeepai_image_reference', $image_strategy);
        }

        if (!is_wp_error($review_result)){
            update_post_meta($attachment_id, '_beepbeepai_review_score', $review_result['score']);
            update_post_meta($attachment_id, '_beepbeepai_review_status', $review_result['status']);
            update_post_meta($attachment_id, '_beepbeepai_review_grade', $review_result['grade']);
            update_post_meta($attachment_id, '_beepbeepai_review_summary', $review_result['summary']);
            update_post_meta($attachment_id, '_beepbeepai_review_issues', wp_json_encode($review_result['issues']));
            update_post_meta($attachment_id, '_beepbeepai_review_model', $review_result['model']);
            update_post_meta($attachment_id, '_beepbeepai_reviewed_at', current_time('mysql'));
            update_post_meta($attachment_id, '_beepbeepai_review_alt_hash', $this->hash_alt_text($alt));
            delete_post_meta($attachment_id, '_beepbeepai_review_error');
            if (!empty($review_result['usage'])){
                $this->record_usage($review_result['usage']);
            }
        } else {
            update_post_meta($attachment_id, '_beepbeepai_review_error', $review_result->get_error_message());
        }

        // Invalidate stats cache after persisting all generation data
        $this->invalidate_stats_cache();
    }

    public function maybe_generate_on_upload($attachment_id){
        $opts = get_option(self::OPTION_KEY, []);
        // Default to enabled if option not explicitly disabled
        if (array_key_exists('enable_on_upload', $opts) && empty($opts['enable_on_upload'])) return;
        if (!$this->is_image($attachment_id)) return;
        $this->invalidate_stats_cache();
        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($existing && empty($opts['force_overwrite'])) return;
        // Respect monthly limit and surface upgrade prompt as admin notice
        if ($this->api_client->has_reached_limit()){
            set_transient('beepbeepai_limit_notice', 1, MINUTE_IN_SECONDS * 10);
            return;
        }
        $this->generate_and_save($attachment_id, 'auto');
    }

    public function generate_and_save($attachment_id, $source='manual', int $retry_count = 0, array $feedback = [], $regenerate = false){
        $opts = get_option(self::OPTION_KEY, []);

        // Skip authentication check in local development mode
        $has_license = $this->api_client->has_active_license();
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            // Check if at usage limit (only in production)
            // Wrap in try-catch to prevent PHP errors from breaking REST responses
            try {
                if ($this->api_client->has_reached_limit()) {
                    return new \WP_Error('limit_reached', __('Monthly generation limit reached. Please upgrade to continue.', 'wp-alt-text-plugin'));
                }
            } catch ( \Exception $e ) {
                // If limit check fails due to error, don't block generation
                // Backend will handle usage limits
                // Silent failure - generation will proceed
            }
        }

        if (!$this->is_image($attachment_id)) return new \WP_Error('not_image', 'Attachment is not an image.');

        // Prefer higher-quality default for better accuracy
        $model = apply_filters('bbai_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts);
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $prompt = $this->build_prompt($attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback);

        if (!empty($opts['dry_run'])){
            update_post_meta($attachment_id, '_beepbeepai_last_prompt', $prompt);
            update_post_meta($attachment_id, '_beepbeepai_source', 'dry-run');
            update_post_meta($attachment_id, '_beepbeepai_model', $model);
            update_post_meta($attachment_id, '_beepbeepai_generated_at', current_time('mysql'));
            $this->stats_cache = null;
            return new \WP_Error('beepbeepai_dry_run', __('Dry run enabled. Prompt stored for review; ALT text not updated.', 'wp-alt-text-plugin'), ['prompt' => $prompt]);
        }

        // Build context for API
        $post = get_post($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $filename = $file_path ? basename($file_path) : '';
        $title = get_the_title($attachment_id);
        $context = [
            'filename' => $filename,
            'title' => is_string($title) ? $title : '',
            'caption' => $post && isset($post->post_excerpt) ? (string)$post->post_excerpt : '',
            'post_title' => '',
        ];
        
        // Get parent post context if available
        if ($post && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent && isset($parent->post_title)) {
                $context['post_title'] = is_string($parent->post_title) ? $parent->post_title : '';
            }
        }

        // Always call the real API to generate actual alt text
        // (Mock mode disabled - we want real AI-generated descriptions)
        $api_response = $this->api_client->generate_alt_text($attachment_id, $context, $regenerate);

        if (is_wp_error($api_response)) {
            if (class_exists('BbAI_Debug_Log')) {
                // Get error data for detailed logging
                $error_data = $api_response->get_error_data();
                $error_code = $api_response->get_error_code();
                $error_message = $api_response->get_error_message();
                
                // Sanitize error message to prevent exposing sensitive API information
                $sanitized_message = $this->sanitize_error_message($error_message);
                
                // Build detailed context for logging
                $log_context = [
                    'attachment_id' => $attachment_id,
                    'code' => $error_code,
                    'message' => $sanitized_message,
                ];
                
                // Include additional error data if available (but sanitize it)
                if (is_array($error_data)) {
                    if (isset($error_data['status_code'])) {
                        $log_context['status_code'] = $error_data['status_code'];
                    }
                    if (isset($error_data['api_response']) && is_array($error_data['api_response'])) {
                        // Include API response details but sanitize sensitive fields
                        $api_resp = $error_data['api_response'];
                        $log_context['api_error_code'] = $api_resp['code'] ?? null;
                        $log_context['api_error_message'] = isset($api_resp['message']) ? $this->sanitize_error_message($api_resp['message']) : null;
                    }
                }
                
                BbAI_Debug_Log::log(
                    'error',
                    'Alt text generation failed',
                    $log_context,
                    'generation'
                );
            }
            return $api_response;
        }

        if (!isset($api_response['success']) || !$api_response['success']) {
            // Extract error details from API response if available
            $error_message = __('Failed to generate alt text', 'wp-alt-text-plugin');
            $error_code = 'api_error';
            
            if (isset($api_response['message'])) {
                $error_message = $api_response['message'];
            } elseif (isset($api_response['error'])) {
                $error_message = is_string($api_response['error']) ? $api_response['error'] : $error_message;
            }
            
            if (isset($api_response['code'])) {
                $error_code = $api_response['code'];
            }
            
            // Log this case as well
            if (class_exists('BbAI_Debug_Log')) {
                BbAI_Debug_Log::log('error', 'Alt text generation failed - invalid response', [
                    'attachment_id' => $attachment_id,
                    'code' => $error_code,
                    'message' => $this->sanitize_error_message($error_message),
                    'response_structure' => array_keys($api_response),
                ], 'generation');
            }
            
            return new \WP_Error('api_error', $error_message, ['code' => $error_code]);
        }
        
        // Refresh license usage data when backend returns updated organization details
        if ($has_license && !empty($api_response['organization'])) {
            $existing_license = $this->api_client->get_license_data() ?? [];
            $updated_license  = $existing_license;
            $updated_license['organization'] = array_merge(
                $existing_license['organization'] ?? [],
                $api_response['organization']
            );
            if (!empty($api_response['site'])) {
                $updated_license['site'] = $api_response['site'];
            }
            $updated_license['updated_at'] = current_time('mysql');
            $this->api_client->set_license_data($updated_license);
            BbAI_Usage_Tracker::clear_cache();
        }

        if (!empty($api_response['usage']) && is_array($api_response['usage'])) {
            BbAI_Usage_Tracker::update_usage($api_response['usage']);

            if ($has_license) {
                $existing_license = $this->api_client->get_license_data() ?? [];
                $updated_license  = $existing_license ?: [];
                $organization     = $updated_license['organization'] ?? [];

                if (isset($api_response['usage']['limit'])) {
                    $organization['tokenLimit'] = intval($api_response['usage']['limit']);
                }

                if (isset($api_response['usage']['remaining'])) {
                    $organization['tokensRemaining'] = max(0, intval($api_response['usage']['remaining']));
                } elseif (isset($api_response['usage']['used']) && isset($organization['tokenLimit'])) {
                    $organization['tokensRemaining'] = max(0, intval($organization['tokenLimit']) - intval($api_response['usage']['used']));
                }

                if (!empty($api_response['usage']['resetDate'])) {
                    $organization['resetDate'] = sanitize_text_field($api_response['usage']['resetDate']);
                } elseif (!empty($api_response['usage']['nextReset'])) {
                    $organization['resetDate'] = sanitize_text_field($api_response['usage']['nextReset']);
                }

                if (!empty($api_response['usage']['plan'])) {
                    $organization['plan'] = sanitize_key($api_response['usage']['plan']);
                }

                $updated_license['organization'] = $organization;
                $updated_license['updated_at'] = current_time('mysql');
                $this->api_client->set_license_data($updated_license);
                BbAI_Usage_Tracker::clear_cache();
            }
        }
        
        $alt = trim($api_response['alt_text']);
        $usage_summary = $api_response['tokens'] ?? ['prompt' => 0, 'completion' => 0, 'total' => 0];
        
        $result = [
            'alt' => $alt,
            'usage' => [
                'prompt' => intval($usage_summary['prompt_tokens'] ?? 0),
                'completion' => intval($usage_summary['completion_tokens'] ?? 0),
                'total' => intval($usage_summary['total_tokens'] ?? 0),
            ]
        ];

        $image_strategy = 'api-proxy';

        $review_result = null;
        if (!empty($api_response['review']) && is_array($api_response['review'])) {
            $review = $api_response['review'];
            $issues = [];
            if (!empty($review['issues']) && is_array($review['issues'])) {
                foreach ($review['issues'] as $issue) {
                    if (is_string($issue) && $issue !== '') {
                        $issues[] = sanitize_text_field($issue);
                    }
                }
            }

            $review_usage = [
                'prompt' => intval($review['usage']['prompt_tokens'] ?? 0),
                'completion' => intval($review['usage']['completion_tokens'] ?? 0),
                'total' => intval($review['usage']['total_tokens'] ?? 0),
            ];

            $review_result = [
                'score' => intval($review['score'] ?? 0),
                'status' => sanitize_key($review['status'] ?? ''),
                'grade' => sanitize_text_field($review['grade'] ?? ''),
                'summary' => isset($review['summary']) ? sanitize_text_field($review['summary']) : '',
                'issues' => $issues,
                'model' => sanitize_text_field($review['model'] ?? ''),
                'usage' => $review_usage,
            ];
        }

        // Check if generated alt is same as existing (unlikely but possible)
        // Skip this check when regenerating - user explicitly wants to regenerate
            if (!is_wp_error($result) && $existing_alt && !$regenerate){
                $generated = trim($result['alt']);
                if (strcasecmp($generated, trim($existing_alt)) === 0){
                $result = new \WP_Error(
                    'duplicate_alt',
                    __('Generated ALT text matched the existing value.', 'wp-alt-text-plugin'),
                    [
                        'existing' => $existing_alt,
                        'generated' => $generated,
                    ]
                );
            }
        }

        if (is_wp_error($result)){
            return $result;
        }

        $usage_summary = $result['usage'];
        $alt = $result['alt'];

        $this->record_usage($usage_summary);
        if ($has_license) {
            $this->refresh_license_usage_snapshot();
        }

        // Note: QA review is disabled for API proxy version (quality handled server-side)
        // Persist the generated alt text
        $this->persist_generation_result($attachment_id, $alt, $usage_summary, $source, $model, $image_strategy, $review_result);

        if (class_exists('BbAI_Debug_Log')) {
            BbAI_Debug_Log::log('info', 'Alt text updated', [
                'attachment_id' => $attachment_id,
                'source' => $source,
                'regenerate' => (bool) $regenerate,
            ], 'generation');
        }

        return $alt;
    }

    private function queue_attachment($attachment_id, $source = 'auto'){
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
            return false;
        }

        $opts = get_option(self::OPTION_KEY, []);

        $existing = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($existing && empty($opts['force_overwrite'])) {
            return false;
        }

        return BbAI_Queue::enqueue($attachment_id, $source ? sanitize_key($source) : 'auto');
    }

    public function register_bulk_action($bulk_actions){
        $bulk_actions['beepbeepai_generate'] = __('Generate Alt Text (AI)', 'wp-alt-text-plugin');
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids){
        if ($doaction !== 'beepbeepai_generate') return $redirect_to;
        $queued = 0;
        foreach ($post_ids as $id){
            if ($this->queue_attachment($id, 'bulk')) {
                $queued++;
            }
        }
        if ($queued > 0) {
            BbAI_Queue::schedule_processing(10);
            
            // Log bulk operation
            if (class_exists('BbAI_Debug_Log')) {
                BbAI_Debug_Log::log('info', 'Bulk alt text generation queued', [
                    'count' => $queued,
                    'total_selected' => count($post_ids),
                ], 'bulk');
            }
        }
        return add_query_arg(['beepbeepai_queued' => $queued], $redirect_to);
    }

    public function row_action_link($actions, $post){
        if ($post->post_type === 'attachment' && $this->is_image($post->ID)){
            $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $generate_label   = __('Generate Alt Text (AI)', 'wp-alt-text-plugin');
            $regenerate_label = __('Regenerate Alt Text (AI)', 'wp-alt-text-plugin');
            $text = $has_alt ? $regenerate_label : $generate_label;
            $actions['beepbeepai_generate_single'] = '<a href="#" class="bbai-generate" data-id="' . intval($post->ID) . '" data-has-alt="' . ($has_alt ? '1' : '0') . '" data-label-generate="' . esc_attr($generate_label) . '" data-label-regenerate="' . esc_attr($regenerate_label) . '">' . esc_html($text) . '</a>';
        }
        return $actions;
    }

    public function attachment_fields_to_edit($fields, $post){
        if (!$this->is_image($post->ID)){
            return $fields;
        }

        $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $label_generate   = __('Generate Alt', 'wp-alt-text-plugin');
        $label_regenerate = __('Regenerate Alt', 'wp-alt-text-plugin');
        $current_label    = $has_alt ? $label_regenerate : $label_generate;
        $is_authenticated = $this->api_client->is_authenticated();
        $disabled_attr    = !$is_authenticated ? ' disabled title="' . esc_attr__('Please log in to generate alt text', 'wp-alt-text-plugin') . '"' : '';
        $button = sprintf(
            '<button type="button" class="button bbai-generate" data-id="%1$d" data-has-alt="%2$d" data-label-generate="%3$s" data-label-regenerate="%4$s"%5$s>%6$s</button>',
            intval($post->ID),
            $has_alt ? 1 : 0,
            esc_attr($label_generate),
            esc_attr($label_regenerate),
            $disabled_attr,
            esc_html($current_label)
        );

        // Hide attachment screen field by default to avoid confusion; can be re-enabled via filter
        if (apply_filters('beepbeepai_show_attachment_button', false)){
        $fields['beepbeepai_generate'] = [
            'label' => __('AI Alt Text', 'wp-alt-text-plugin'),
            'input' => 'html',
            'html'  => $button . '<p class="description">' . esc_html__('Use AI to suggest alternative text for this image.', 'wp-alt-text-plugin') . '</p>',
        ];
        }

        return $fields;
    }

	/**
	 * @deprecated 4.3.0 Use BbAI_REST_Controller::register_routes().
	 */
	public function register_rest_routes(){
		if ( ! class_exists( 'BbAI_REST_Controller' ) ) {
			require_once BBAI_PLUGIN_DIR . 'admin/class-opptibbai-rest-controller.php';
		}

		( new BbAI_REST_Controller( $this ) )->register_routes();
	}

    public function enqueue_admin($hook){
        $base_path = BBAI_PLUGIN_DIR;
        $base_url  = BBAI_PLUGIN_URL;

        $asset_version = static function(string $relative, string $fallback = '1.0.0') use ($base_path): string {
            $relative = ltrim($relative, '/');
            $path = $base_path . $relative;
            return file_exists($path) ? (string) filemtime($path) : $fallback;
        };

        $use_debug_assets = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $js_base  = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';
        $css_base = $use_debug_assets ? 'assets/src/css/' : 'assets/dist/css/';
        $asset_path = static function(string $base, string $name, bool $debug, string $type) use ($base_path): string {
            $extension = $debug ? ".$type" : ".min.$type";
            $minified_path = $base . $name . $extension;
            // If minified file doesn't exist, fall back to source file
            if (!$debug && !file_exists($base_path . $minified_path)) {
                $source_base = str_replace('assets/dist/', 'assets/src/', $base);
                return $source_base . $name . ".$type";
            }
            return $minified_path;
        };

        $admin_file    = $asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js');
        $admin_version = $asset_version($admin_file, '3.0.0');
        
        $checkout_prices = $this->get_checkout_price_ids();

        $l10n_common = [
            'reviewCue'           => __('Visit the ALT Library to double-check the wording.', 'wp-alt-text-plugin'),
            'statusReady'         => '',
            'previewAltHeading'   => __('Review generated ALT text', 'wp-alt-text-plugin'),
            'previewAltHint'      => __('Review the generated description before applying it to your media item.', 'wp-alt-text-plugin'),
            'previewAltApply'     => __('Use this ALT', 'wp-alt-text-plugin'),
            'previewAltCancel'    => __('Keep current ALT', 'wp-alt-text-plugin'),
            'previewAltDismissed' => __('Preview dismissed. Existing ALT kept.', 'wp-alt-text-plugin'),
            'previewAltShortcut'  => __('Shift + Enter for newline.', 'wp-alt-text-plugin'),
        ];

        // Load on Media Library and attachment edit contexts (modal also)
        if (in_array($hook, ['upload.php', 'post.php', 'post-new.php', 'media_page_bbai'], true)){
            wp_enqueue_script('bbai-admin', $base_url . $admin_file, ['jquery'], $admin_version, true);
            wp_localize_script('bbai-admin', 'BBAI', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'rest'      => esc_url_raw( rest_url('bbai/v1/') ),
                'restAlt'   => esc_url_raw( rest_url('bbai/v1/alt/') ),
                'restStats' => esc_url_raw( rest_url('bbai/v1/stats') ),
                'restUsage' => esc_url_raw( rest_url('bbai/v1/usage') ),
                'restMissing'=> esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('bbai/v1/list')) ),
                'restAll'    => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('bbai/v1/list')) ),
                'restQueue'  => esc_url_raw( rest_url('bbai/v1/queue') ),
                'restRoot'  => esc_url_raw( rest_url() ),
                'restPlans' => esc_url_raw( rest_url('bbai/v1/plans') ),
                'l10n'      => $l10n_common,
                'upgradeUrl'=> esc_url( BbAI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( BbAI_Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'canManage' => $this->user_can_manage(),
                'inlineBatchSize' => defined('BBAI_INLINE_BATCH') ? max(1, intval(BBAI_INLINE_BATCH)) : 1,
            ]);
            // Also add bbai_ajax for regenerate functionality
            wp_localize_script('bbai-admin', 'bbai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bbai_upgrade_nonce'),
                'can_manage' => $this->user_can_manage(),
            ]);
        }

        if ($hook === 'media_page_bbai'){
            $css_file    = $asset_path($css_base, 'bbai-dashboard', $use_debug_assets, 'css');
            $js_file     = $asset_path($js_base, 'bbai-dashboard', $use_debug_assets, 'js');
            $upgrade_css = $asset_path($css_base, 'upgrade-modal', $use_debug_assets, 'css');
            $upgrade_js  = $asset_path($js_base, 'upgrade-modal', $use_debug_assets, 'js');
            $auth_css    = $asset_path($css_base, 'auth-modal', $use_debug_assets, 'css');
            $auth_js     = $asset_path($js_base, 'auth-modal', $use_debug_assets, 'js');

            // Enqueue design system (FIRST - foundation for all styles)
            wp_enqueue_style(
                'bbai-design-system',
                $base_url . $asset_path($css_base, 'design-system', $use_debug_assets, 'css'),
                [],
                $asset_version($asset_path($css_base, 'design-system', $use_debug_assets, 'css'), '1.0.0')
            );

            // Enqueue reusable components (SECOND - uses design tokens)
            wp_enqueue_style(
                'bbai-components',
                $base_url . $asset_path($css_base, 'components', $use_debug_assets, 'css'),
                ['bbai-design-system'],
                $asset_version($asset_path($css_base, 'components', $use_debug_assets, 'css'), '1.0.0')
            );

            // Enqueue page-specific styles (use design system + components)
            wp_enqueue_style(
                'bbai-dashboard',
                $base_url . $css_file,
                ['bbai-components'],
                $asset_version($css_file, '3.0.0')
            );
            wp_enqueue_style(
                'bbai-modern',
                $base_url . $asset_path($css_base, 'modern-style', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'modern-style', $use_debug_assets, 'css'), '4.1.0')
            );
            wp_enqueue_style(
                'bbai-ui',
                $base_url . $asset_path($css_base, 'ui', $use_debug_assets, 'css'),
                ['bbai-modern'],
                $asset_version($asset_path($css_base, 'ui', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'bbai-upgrade',
                $base_url . $upgrade_css,
                ['bbai-components'],
                $asset_version($upgrade_css, '3.1.0')
            );
            wp_enqueue_style(
                'bbai-auth',
                $base_url . $auth_css,
                ['bbai-components'],
                $asset_version($auth_css, '4.0.0')
            );
            wp_enqueue_style(
                'bbai-button-enhancements',
                $base_url . $asset_path($css_base, 'button-enhancements', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'button-enhancements', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'bbai-guide-settings',
                $base_url . $asset_path($css_base, 'guide-settings-pages', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'guide-settings-pages', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'bbai-debug-styles',
                $base_url . $asset_path($css_base, 'bbai-debug', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'bbai-debug', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'bbai-bulk-progress',
                $base_url . $asset_path($css_base, 'bulk-progress-modal', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'bulk-progress-modal', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'bbai-success-modal',
                $base_url . $asset_path($css_base, 'success-modal', $use_debug_assets, 'css'),
                ['bbai-components'],
                $asset_version($asset_path($css_base, 'success-modal', $use_debug_assets, 'css'), '1.0.0')
            );


            $stats_data = $this->get_media_stats();
            $usage_data = BbAI_Usage_Tracker::get_stats_display();
            
            wp_enqueue_script(
                'bbai-dashboard',
                $base_url . $js_file,
                ['jquery', 'wp-api-fetch'],
                $asset_version($js_file, '3.0.0'),
                true
            );
            wp_enqueue_script(
                'bbai-upgrade',
                $base_url . $upgrade_js,
                ['jquery'],
                $asset_version($upgrade_js, '3.1.0'),
                true
            );
            wp_enqueue_script(
                'bbai-auth',
                $base_url . $auth_js,
                ['jquery'],
                $asset_version($auth_js, '4.0.0'),
                true
            );
            wp_enqueue_script(
                'bbai-debug',
                $base_url . $asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'),
                ['jquery'],
                $asset_version($asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'), '1.0.0'),
                true
            );
            
            // Localize debug script configuration
            wp_localize_script('bbai-debug', 'BBAI_DEBUG', [
                'restLogs' => esc_url_raw( rest_url('bbai/v1/debug/logs') ),
                'restClear' => esc_url_raw( rest_url('bbai/v1/debug/clear') ),
                'nonce' => wp_create_nonce('wp_rest'),
                'initial' => class_exists('BbAI_Debug_Log') ? BbAI_Debug_Log::get_logs([
                    'per_page' => 10,
                    'page' => 1,
                ]) : [
                    'logs' => [],
                    'pagination' => ['page' => 1, 'per_page' => 10, 'total_pages' => 1, 'total_items' => 0],
                    'stats' => ['total' => 0, 'warnings' => 0, 'errors' => 0, 'last_api' => null],
                ],
                'strings' => [
                    'noLogs' => __('No logs recorded yet.', 'wp-alt-text-plugin'),
                    'contextTitle' => __('Log Context', 'wp-alt-text-plugin'),
                    'clearConfirm' => __('This will permanently delete all debug logs. Continue?', 'wp-alt-text-plugin'),
                    'errorGeneric' => __('Unable to load debug logs. Please try again.', 'wp-alt-text-plugin'),
                    'emptyContext' => __('No additional context was provided for this entry.', 'wp-alt-text-plugin'),
                    'cleared' => __('Logs cleared successfully.', 'wp-alt-text-plugin'),
                ],
            ]);

            wp_localize_script('bbai-dashboard', 'BBAI_DASH', [
                'nonce'       => wp_create_nonce('wp_rest'),
                'rest'        => esc_url_raw( rest_url('bbai/v1/generate/') ),
                'restStats'   => esc_url_raw( rest_url('bbai/v1/stats') ),
                'restUsage'   => esc_url_raw( rest_url('bbai/v1/usage') ),
                'restMissing' => esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('bbai/v1/list')) ),
                'restAll'     => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('bbai/v1/list')) ),
                'restQueue'   => esc_url_raw( rest_url('bbai/v1/queue') ),
                'restRoot'    => esc_url_raw( rest_url() ),
                'restPlans'   => esc_url_raw( rest_url('bbai/v1/plans') ),
                'upgradeUrl'  => esc_url( BbAI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl'=> esc_url( BbAI_Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'stats'       => $stats_data,
                'initialUsage'=> $usage_data,
            ]);
            
            // Add AJAX variables for regenerate functionality and auth
            $options = get_option(self::OPTION_KEY, []);
            // Production API URL - always use production
            $production_url = 'https://alttext-ai-backend.onrender.com';
            $api_url = $production_url;

            wp_localize_script('bbai-dashboard', 'bbai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('bbai_upgrade_nonce'),
                'api_url' => $api_url,
                'is_authenticated' => $this->api_client->is_authenticated(),
                'user_data' => $this->api_client->get_user_data(),
                'can_manage' => $this->user_can_manage(),
            ]);
            
            wp_localize_script('bbai-dashboard', 'BBAI_DASH_L10N', [
                'l10n'        => array_merge([
                    'processing'         => __('Generating ALT text…', 'wp-alt-text-plugin'),
                    'processingMissing'  => __('Generating ALT for #%d…', 'wp-alt-text-plugin'),
                    'error'              => __('Something went wrong. Check console for details.', 'wp-alt-text-plugin'),
                    'summary'            => __('Generated %1$d images (%2$d errors).', 'wp-alt-text-plugin'),
                    'restUnavailable'    => __('REST endpoint unavailable', 'wp-alt-text-plugin'),
                    'prepareBatch'       => __('Preparing image list…', 'wp-alt-text-plugin'),
                    'coverageCopy'       => __('of images currently include ALT text.', 'wp-alt-text-plugin'),
                    'noRequests'         => __('None yet', 'wp-alt-text-plugin'),
                    'noAudit'            => __('No usage data recorded yet.', 'wp-alt-text-plugin'),
                    'nothingToProcess'   => __('No images to process.', 'wp-alt-text-plugin'),
                    'batchStart'         => __('Starting batch…', 'wp-alt-text-plugin'),
                    'batchComplete'      => __('Batch complete.', 'wp-alt-text-plugin'),
                    'batchCompleteAt'    => __('Batch complete at %s', 'wp-alt-text-plugin'),
                    'completedItem'      => __('Finished #%d', 'wp-alt-text-plugin'),
                    'failedItem'         => __('Failed #%d', 'wp-alt-text-plugin'),
                    'loadingButton'      => __('Processing…', 'wp-alt-text-plugin'),
                ], $l10n_common),
            ]);
            
            // Localize upgrade modal script
            wp_localize_script('bbai-upgrade', 'BBAI_UPGRADE', [
                'nonce' => wp_create_nonce('bbai_upgrade_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'usage' => $usage_data,
                'upgradeUrl' => esc_url( BbAI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( BbAI_Usage_Tracker::get_billing_portal_url() ),
                'priceIds' => $checkout_prices,
                'restPlans' => esc_url_raw( rest_url('bbai/v1/plans') ),
                'canManage' => $this->user_can_manage(),
            ]);

        }
    }

    public function wpcli_command($args, $assoc){
        if (!class_exists('WP_CLI')) return;
        $id  = isset($assoc['post_id']) ? intval($assoc['post_id']) : 0;
        if (!$id){
            \WP_CLI::error('Provide --post_id=<attachment_id>');
        }

        $res = $this->generate_and_save($id, 'wpcli');
        if (is_wp_error($res)) {
            if ($res->get_error_code() === 'beepbeepai_dry_run') {
                \WP_CLI::success("ID $id dry-run: " . $res->get_error_message());
            } else {
                \WP_CLI::error($res->get_error_message());
            }
        } else {
            \WP_CLI::success("Generated ALT for $id: $res");
        }
    }
    
    /**
     * AJAX handler: Dismiss upgrade notice
     */
    /**
     * Handle AJAX request to dismiss external API notice.
     * Uses site option so it shows only once for all users.
     */
    public function ajax_dismiss_api_notice() {
        check_ajax_referer('wp_alt_text_dismiss_api_notice', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }
        
        // Store as site option so it shows only once globally, not per user
        update_option('wp_alt_text_api_notice_dismissed', true, false);
        wp_send_json_success(['message' => __('Notice dismissed', 'wp-alt-text-plugin')]);
    }

    public function ajax_dismiss_upgrade() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        BbAI_Usage_Tracker::dismiss_upgrade_notice();
        setcookie('bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_queue_retry_job() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }
        $job_id_raw = isset($_POST['job_id']) ? wp_unslash($_POST['job_id']) : '';
        $job_id = absint($job_id_raw);
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'wp-alt-text-plugin')]);
        }
        BbAI_Queue::retry_job($job_id);
        BbAI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'wp-alt-text-plugin')]);
    }

    public function ajax_queue_retry_failed() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }
        BbAI_Queue::retry_failed();
        BbAI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'wp-alt-text-plugin')]);
    }

    public function ajax_queue_clear_completed() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }
        BbAI_Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'wp-alt-text-plugin')]);
    }

    public function ajax_queue_stats() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }
        
        $stats = BbAI_Queue::get_stats();
        $failures = BbAI_Queue::get_failures();
        
        wp_send_json_success([
            'stats' => $stats,
            'failures' => $failures
        ]);
    }

    public function ajax_track_upgrade() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $source_raw = isset($_POST['source']) ? wp_unslash($_POST['source']) : 'dashboard';
        $source = sanitize_key($source_raw);
        $event  = [
            'source' => $source,
            'user_id' => get_current_user_id(),
            'time'   => current_time('mysql'),
        ];

        update_option('bbai_last_upgrade_click', $event, false);
        do_action('bbai_upgrade_clicked', $event);

        wp_send_json_success(['recorded' => true]);
    }

    public function ajax_refresh_usage() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Clear cache and fetch fresh data
        BbAI_Usage_Tracker::clear_cache();
        $usage = $this->api_client->get_usage();
        
        if ($usage) {
            $stats = BbAI_Usage_Tracker::get_stats_display();
            wp_send_json_success($stats);
        } else {
            wp_send_json_error(['message' => 'Failed to fetch usage data']);
        }
    }

    /**
     * AJAX handler: Regenerate single image alt text
     */
    public function ajax_regenerate_single() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $attachment_id_raw = isset($_POST['attachment_id']) ? wp_unslash($_POST['attachment_id']) : '';
        $attachment_id = absint($attachment_id_raw);
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        $has_license = $this->api_client->has_active_license();

        // Check if user has remaining usage (skip in local dev mode and for license accounts)
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            $usage = $this->api_client->get_usage();
            if (is_wp_error($usage) || !$usage || ($usage['remaining'] ?? 0) <= 0) {
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => is_wp_error($usage) ? null : $usage
                ]);
            }
        }
        
        $result = $this->generate_and_save($attachment_id, 'ajax', 1, [], true);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ]);
        }

        BbAI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        wp_send_json_success([
            'message'        => __('Alt text generated successfully.', 'wp-alt-text-plugin'),
            'alt_text'       => $result,
            'attachment_id'  => $attachment_id,
        ]);
    }

    /**
     * AJAX handler: Bulk queue images for processing
     */
    public function ajax_bulk_queue() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', $attachment_ids_raw) : [];
        $source_raw = isset($_POST['source']) ? wp_unslash($_POST['source']) : 'bulk';
        $source = sanitize_text_field($source_raw);
        
        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => 'Invalid attachment IDs']);
        }
        
        // Sanitize all IDs
        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0 && $this->is_image($id);
        });
        
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No valid images found']);
        }
        
        $has_license = $this->api_client->has_active_license();

        // Check if user has remaining usage (skip in local dev mode or when license active)
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            $usage = $this->api_client->get_usage();
            
            // If usage check fails due to authentication, allow queueing but warn user
            if (is_wp_error($usage)) {
                $error_code = $usage->get_error_code();
                // If it's an auth error, allow queueing to proceed (backend will handle it)
                // Don't block queueing on temporary auth issues
                if ($error_code === 'auth_required' || $error_code === 'user_not_found') {
                    // Allow queueing - authentication can be handled later during processing
                } else {
                    // For other errors (server issues, etc.), still allow queueing
                    // The backend will handle usage limits during processing
                }
            } elseif (!$usage || ($usage['remaining'] ?? 0) <= 0) {
                // Only block if we have a valid usage response showing limit reached
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => $usage
                ]);
            } else {
                // Check how many we can queue
                $remaining = $usage['remaining'] ?? 0;
                if (count($ids) > $remaining) {
                    wp_send_json_error([
                        'message' => sprintf(__('You only have %d generations remaining. Please upgrade or select fewer images.', 'wp-alt-text-plugin'), $remaining),
                        'code' => 'insufficient_credits',
                        'remaining' => $remaining
                    ]);
                }
            }
        }
        
        try {
            // Queue images (will clear existing entries for bulk-regenerate)
            $queued = BbAI_Queue::enqueue_many($ids, $source);
            
            // Log bulk queue operation
            if (class_exists('BbAI_Debug_Log')) {
                BbAI_Debug_Log::log('info', 'Bulk queue operation', [
                    'queued' => $queued,
                    'requested' => count($ids),
                    'source' => $source,
                ], 'bulk');
            }
            
            if ($queued > 0) {
                // Schedule queue processing
                BbAI_Queue::schedule_processing();
                
                wp_send_json_success([
                    'message' => sprintf(__('%d image(s) queued for processing', 'wp-alt-text-plugin'), $queued),
                    'queued' => $queued,
                    'total' => count($ids)
                ]);
            } else {
                // For regeneration, if nothing was queued, it might mean they're already completed
                // Check if images already have alt text and suggest direct regeneration instead
                
                if ($source === 'bulk-regenerate') {
                    wp_send_json_error([
                        'message' => __('No images were queued. They may already be processing or have completed. Try refreshing the page.', 'wp-alt-text-plugin'),
                        'code' => 'already_queued'
                    ]);
                } else {
                    wp_send_json_error([
                        'message' => __('Failed to queue images. They may already be queued or processing.', 'wp-alt-text-plugin')
                    ]);
                }
            }
        } catch ( \Exception $e ) {
            // Return proper JSON error instead of letting WordPress output HTML
            wp_send_json_error([
                'message' => __('Failed to queue images due to a database error. Please try again.', 'wp-alt-text-plugin'),
                'code' => 'queue_failed'
            ]);
        }
    }

    public function process_queue() {
        $batch_size = apply_filters('beepbeepai_queue_batch_size', 3);
        $max_attempts = apply_filters('beepbeepai_queue_max_attempts', 3);

        BbAI_Queue::reset_stale(apply_filters('beepbeepai_queue_stale_timeout', 10 * MINUTE_IN_SECONDS));

        $jobs = BbAI_Queue::claim_batch($batch_size);
        if (empty($jobs)) {
            BbAI_Queue::purge_completed(apply_filters('beepbeepai_queue_purge_age', DAY_IN_SECONDS * 2));
            return;
        }

        foreach ($jobs as $job) {
            $attachment_id = intval($job->attachment_id);
            if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
                BbAI_Queue::mark_complete($job->id);
                continue;
            }

            $result = $this->generate_and_save($attachment_id, $job->source ?? 'queue', max(0, intval($job->attempts) - 1));

            if (is_wp_error($result)) {
                $code    = $result->get_error_code();
                $message = $result->get_error_message();

                if ($code === 'limit_reached') {
                    BbAI_Queue::mark_retry($job->id, $message);
                    BbAI_Queue::schedule_processing(apply_filters('beepbeepai_queue_limit_delay', HOUR_IN_SECONDS));
                    break;
                }

                if (intval($job->attempts) >= $max_attempts) {
                    BbAI_Queue::mark_failed($job->id, $message);
                } else {
                    BbAI_Queue::mark_retry($job->id, $message);
                }
                continue;
            }

            BbAI_Queue::mark_complete($job->id);
        }

        BbAI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();
        $stats = BbAI_Queue::get_stats();
        if (!empty($stats['pending'])) {
            BbAI_Queue::schedule_processing(apply_filters('beepbeepai_queue_next_delay', 45));
        }

        BbAI_Queue::purge_completed(apply_filters('beepbeepai_queue_purge_age', DAY_IN_SECONDS * 2));
    }

    public function handle_media_change($attachment_id = 0) {
        $this->invalidate_stats_cache();

        if (current_filter() === 'delete_attachment') {
            BbAI_Queue::schedule_processing(30);
            return;
        }

        $opts = get_option(self::OPTION_KEY, []);
        if (empty($opts['enable_on_upload'])) {
            return;
        }

        $this->queue_attachment($attachment_id, 'upload');
        BbAI_Queue::schedule_processing(15);
    }

    public function handle_media_metadata_update($data, $post_id) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'metadata');
        BbAI_Queue::schedule_processing(20);
        return $data;
    }

    public function handle_attachment_updated($post_id, $post_after, $post_before) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'update');
        BbAI_Queue::schedule_processing(20);
    }

    public function handle_post_save($post_ID, $post, $update) {
        if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
            $this->invalidate_stats_cache();
            if ($update) {
                $this->queue_attachment($post_ID, 'save');
                BbAI_Queue::schedule_processing(20);
            }
        }
    }

    private function get_account_summary(?array $usage_stats = null) {
        if ($this->account_summary !== null) {
            return $this->account_summary;
        }

        $summary = [
            'email'      => '',
            'name'       => '',
            'plan'       => $usage_stats['plan'] ?? '',
            'plan_label' => $usage_stats['plan_label'] ?? '',
        ];

        if (!$this->api_client->is_authenticated()) {
            $this->account_summary = $summary;
            return $this->account_summary;
        }

        $user = $this->api_client->get_user_data();
        if ((!is_array($user) || empty($user['email']))) {
            $fresh = $this->api_client->get_user_info();
            if (!is_wp_error($fresh) && is_array($fresh)) {
                $user = $fresh;
                $this->api_client->set_user_data($fresh);
            }
        }

        if (is_array($user)) {
            $summary['email'] = $user['email'] ?? '';
            $summary['name']  = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        }

        $this->account_summary = $summary;
        return $this->account_summary;
    }

    /**
     * Phase 2 Authentication AJAX Handlers
     */

    /**
     * AJAX handler: User registration
     */
    public function ajax_register() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'wp-alt-text-plugin')]);
        }

        $result = $this->api_client->register($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Account created successfully', 'wp-alt-text-plugin'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User login
     */
    public function ajax_login() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'wp-alt-text-plugin')]);
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'wp-alt-text-plugin'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     */
    public function ajax_logout() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $this->api_client->clear_token();

        wp_send_json_success(['message' => __('Logged out successfully', 'wp-alt-text-plugin')]);
    }

    public function ajax_disconnect_account() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Clear JWT token (for authenticated users)
        $this->api_client->clear_token();
        
        // Clear license key (for agency/license-based users)
        // This prevents automatic reconnection when using license keys
        $this->api_client->clear_license_key();
        
        // Clear user data
        delete_option('beepbeepai_user_data');
        delete_option('beepbeepai_site_id');
        
        // Clear usage cache
        BbAI_Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('beepbeepai_usage_cache');
        delete_transient('beepbeepai_token_last_check');

        wp_send_json_success([
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'wp-alt-text-plugin'),
        ]);
    }

    /**
     * AJAX handler: Activate license key
     */
    public function ajax_activate_license() {
        check_ajax_referer('bbai_license_action', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $license_key_raw = isset($_POST['license_key']) ? wp_unslash($_POST['license_key']) : '';
        $license_key = is_string($license_key_raw) ? sanitize_text_field($license_key_raw) : '';

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'wp-alt-text-plugin')]);
        }

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key)) {
            wp_send_json_error(['message' => __('Invalid license key format', 'wp-alt-text-plugin')]);
        }

        $result = $this->api_client->activate_license($license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Clear cached usage data
        BbAI_Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('beepbeepai_usage_cache');

        wp_send_json_success([
            'message' => __('License activated successfully', 'wp-alt-text-plugin'),
            'organization' => $result['organization'] ?? null,
            'site' => $result['site'] ?? null
        ]);
    }

    /**
     * AJAX handler: Deactivate license key
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('bbai_license_action', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $result = $this->api_client->deactivate_license();

        // Clear cached usage data
        BbAI_Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('beepbeepai_usage_cache');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('License deactivated successfully', 'wp-alt-text-plugin')
        ]);
    }

    /**
     * AJAX handler: Get license site usage
     */
    public function ajax_get_license_sites() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Must be authenticated to view license site usage
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view license site usage', 'wp-alt-text-plugin')
            ]);
        }

        // Fetch license site usage from API
        $result = $this->api_client->get_license_sites();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to fetch license site usage', 'wp-alt-text-plugin')
            ]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Disconnect a site from the license
     */
    public function ajax_disconnect_license_site() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Must be authenticated to disconnect license sites
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to disconnect license sites', 'wp-alt-text-plugin')
            ]);
        }

        $site_id_raw = isset($_POST['site_id']) ? wp_unslash($_POST['site_id']) : '';
        $site_id = is_string($site_id_raw) ? sanitize_text_field($site_id_raw) : '';
        if (empty($site_id)) {
            wp_send_json_error([
                'message' => __('Site ID is required', 'wp-alt-text-plugin')
            ]);
        }

        // Disconnect the site from the license
        $result = $this->api_client->disconnect_license_site($site_id);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to disconnect site', 'wp-alt-text-plugin')
            ]);
        }

        wp_send_json_success([
            'message' => __('Site disconnected successfully', 'wp-alt-text-plugin'),
            'data' => $result
        ]);
    }

    /**
     * Check if admin is authenticated (separate from regular user auth)
     */
    private function is_admin_authenticated() {
        // Check if we have a valid admin session
        $admin_session = get_transient('bbai_admin_session_' . get_current_user_id());
        if ($admin_session === false || empty($admin_session)) {
            return false;
        }
        
        // Verify session hasn't expired (24 hours)
        $session_time = get_transient('bbai_admin_session_time_' . get_current_user_id());
        if ($session_time === false || (time() - intval($session_time)) > (24 * HOUR_IN_SECONDS)) {
            $this->clear_admin_session();
            return false;
        }
        
        return true;
    }

    /**
     * Set admin session
     */
    private function set_admin_session() {
        $user_id = get_current_user_id();
        set_transient('bbai_admin_session_' . $user_id, 'authenticated', DAY_IN_SECONDS);
        set_transient('bbai_admin_session_time_' . $user_id, time(), DAY_IN_SECONDS);
    }

    /**
     * Clear admin session
     */
    private function clear_admin_session() {
        $user_id = get_current_user_id();
        delete_transient('bbai_admin_session_' . $user_id);
        delete_transient('bbai_admin_session_time_' . $user_id);
    }

    /**
     * AJAX handler: Admin login for agency users
     */
    public function ajax_admin_login() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Verify agency license
        $has_license = $this->api_client->has_active_license();
        $license_data = $this->api_client->get_license_data();
        $is_agency = false;
        
        if ($has_license && $license_data && isset($license_data['organization'])) {
            $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
            $is_agency = ($license_plan === 'agency');
        }
        
        if (!$is_agency) {
            wp_send_json_error([
                'message' => __('Admin access is only available for agency licenses', 'wp-alt-text-plugin')
            ]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password_raw = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $password = is_string($password_raw) ? $password_raw : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'wp-alt-text-plugin')
            ]);
        }

        if (empty($password)) {
            wp_send_json_error([
                'message' => __('Please enter your password', 'wp-alt-text-plugin')
            ]);
        }

        // Attempt login
        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Login failed. Please check your credentials.', 'wp-alt-text-plugin')
            ]);
        }

        // Set admin session
        $this->set_admin_session();

        wp_send_json_success([
            'message' => __('Successfully logged in', 'wp-alt-text-plugin'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Admin logout
     */
    public function ajax_admin_logout() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $this->clear_admin_session();

        wp_send_json_success([
            'message' => __('Logged out successfully', 'wp-alt-text-plugin'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'wp-alt-text-plugin'),
                'code' => 'not_authenticated'
            ]);
        }

        $user_info = $this->api_client->get_user_info();
        $usage = $this->api_client->get_usage();

        if (is_wp_error($user_info)) {
            wp_send_json_error(['message' => $user_info->get_error_message()]);
        }

        wp_send_json_success([
            'user' => $user_info,
            'usage' => is_wp_error($usage) ? null : $usage
        ]);
    }

    /**
     * AJAX handler: Create Stripe checkout session
     */
    public function ajax_create_checkout() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Allow checkout without authentication - users can create account during checkout
        // Authentication is optional for checkout, backend will handle account creation

        // Accept either price_id (direct) or plan_id (to map to price_id)
        $price_id_raw = isset($_POST['price_id']) ? wp_unslash($_POST['price_id']) : '';
        $plan_id_raw = isset($_POST['plan_id']) ? wp_unslash($_POST['plan_id']) : '';
        
        $price_id = '';
        
        // If plan_id provided, map to price_id
        if (!empty($plan_id_raw)) {
            $plan_id = sanitize_key($plan_id_raw);
            $price_ids = $this->get_checkout_price_ids();
            
            // Try direct lookup first (if price_ids has plan_id as key)
            if (isset($price_ids[$plan_id]) && !empty($price_ids[$plan_id])) {
                $price_id = $price_ids[$plan_id];
            } else {
                // Map plan IDs to price IDs - check various possible key formats
                if ($plan_id === 'pro') {
                    $price_id = $price_ids['pro_monthly'] ?? $price_ids['pro'] ?? '';
                } elseif ($plan_id === 'agency') {
                    $price_id = $price_ids['agency_monthly'] ?? $price_ids['agency'] ?? '';
                } elseif ($plan_id === 'pack-small' || $plan_id === 'pack-medium' || $plan_id === 'pack-large') {
                    // For credits packs, use the credits price ID (one-time payment)
                    $price_id = $price_ids['credits'] ?? '';
                }
            }
        }
        
        // Fallback to direct price_id
        if (empty($price_id) && !empty($price_id_raw)) {
            $price_id = sanitize_text_field($price_id_raw);
        }
        
        if (empty($price_id)) {
            wp_send_json_error(['message' => __('Price ID or Plan ID is required', 'wp-alt-text-plugin')]);
        }

        // Get context data for backend (sent via headers by API client automatically)
        // site_url, user_id, and wordpress_site_id are sent via X-Site-URL, X-WP-User-ID, and X-Site-ID headers
        $site_url = get_site_url();
        $user_id = get_current_user_id();
        $wordpress_site_id = get_option('beepbeepai_site_id', '');

        $success_url = admin_url('upload.php?page=bbai&checkout=success');
        $cancel_url = admin_url('upload.php?page=bbai&checkout=cancel');

        // Create checkout session - works for both authenticated and unauthenticated users
        // If token is invalid, it will retry without token for guest checkout
        // site_url, user_id, and wordpress_site_id are sent via headers by API client
        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_code = $result->get_error_code();
            
            // Don't show "session expired" messages for checkout - just show generic error
            if ($error_code === 'auth_required' || 
                strpos(strtolower($error_message), 'session') !== false ||
                strpos(strtolower($error_message), 'log in') !== false) {
                $error_message = __('Unable to create checkout session. Please try again or contact support.', 'wp-alt-text-plugin');
            }
            
            wp_send_json_error(['message' => $error_message]);
        }

        wp_send_json_success([
            'url' => $result['url'] ?? '',
            'session_id' => $result['sessionId'] ?? ''
        ]);
    }

    /**
     * AJAX handler: Create customer portal session
     */
    public function ajax_create_portal() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        // Check if user is authenticated via JWT token OR admin session with agency license
        $is_authenticated = $this->api_client->is_authenticated();
        $is_admin_authenticated = $this->is_admin_authenticated();
        $has_agency_license = false;
        
        if ($is_admin_authenticated || !$is_authenticated) {
            // Check if there's an agency license active
            $has_license = $this->api_client->has_active_license();
            if ($has_license) {
                $license_data = $this->api_client->get_license_data();
                if ($license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    $has_agency_license = ($license_plan === 'agency' || $license_plan === 'pro');
                }
            }
        }

        // Allow if authenticated via JWT OR admin-authenticated with agency/pro license
        if (!$is_authenticated && !($is_admin_authenticated && $has_agency_license)) {
            wp_send_json_error([
                'message' => __('Please log in to manage billing', 'wp-alt-text-plugin'),
                'code' => 'not_authenticated'
            ]);
        }

        // For admin-authenticated users with license, try using stored portal URL first
        if ($is_admin_authenticated && $has_agency_license && !$is_authenticated) {
            $stored_portal_url = BbAI_Usage_Tracker::get_billing_portal_url();
            if (!empty($stored_portal_url)) {
                wp_send_json_success([
                    'url' => $stored_portal_url
                ]);
                return;
            }
        }

        $return_url = admin_url('upload.php?page=bbai');
        $result = $this->api_client->create_customer_portal_session($return_url);

        if (is_wp_error($result)) {
            // If backend doesn't support license key auth for portal, provide helpful message
            $error_message = $result->get_error_message();
            $error_message = is_string($error_message) ? $error_message : '';
            if (is_string($error_message) && $error_message && (strpos((string)$error_message, 'Authentication required') !== false || 
                strpos((string)$error_message, 'Unauthorized') !== false ||
                strpos((string)$error_message, 'not_authenticated') !== false)) {
                wp_send_json_error([
                    'message' => __('To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'wp-alt-text-plugin'),
                    'code' => 'not_authenticated'
                ]);
                return;
            }
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        wp_send_json_success([
            'url' => $result['url'] ?? ''
        ]);
    }

    /**
     * AJAX handler: Forgot password request
     */
    public function ajax_forgot_password() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'wp-alt-text-plugin')
            ]);
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        // Pass through all data from backend, including reset link if provided
        $response_data = [
            'message' => __('Password reset link has been sent to your email. Please check your inbox and spam folder.', 'wp-alt-text-plugin'),
        ];
        
        // Include reset link if provided (for development/testing when email service isn't configured)
        if (isset($result['resetLink'])) {
            $response_data['resetLink'] = $result['resetLink'];
            $response_data['note'] = $result['note'] ?? __('Email service is in development mode. Use this link to reset your password.', 'wp-alt-text-plugin');
        }
        
        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler: Reset password with token
     */
    public function ajax_reset_password() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $token_raw = isset($_POST['token']) ? wp_unslash($_POST['token']) : '';
        $token = is_string($token_raw) ? sanitize_text_field($token_raw) : '';
        $password_raw = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
        $password = is_string($password_raw) ? $password_raw : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'wp-alt-text-plugin')
            ]);
        }

        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Reset token is required', 'wp-alt-text-plugin')
            ]);
        }

        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long', 'wp-alt-text-plugin')
            ]);
        }

        $result = $this->api_client->reset_password($email, $token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now sign in with your new password.', 'wp-alt-text-plugin'),
            'redirect' => admin_url('upload.php?page=bbai&password_reset=success')
        ]);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view subscription information', 'wp-alt-text-plugin'),
                'code' => 'not_authenticated'
            ]);
        }

        $subscription_info = $this->api_client->get_subscription_info();

        if (is_wp_error($subscription_info)) {
            wp_send_json_error([
                'message' => $subscription_info->get_error_message()
            ]);
        }

        wp_send_json_success($subscription_info);
    }

    /**
     * AJAX handler: Inline generation for selected attachment IDs (used by progress modal)
     */
    public function ajax_inline_generate() {
        check_ajax_referer('bbai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-alt-text-plugin')]);
        }

        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', (array) $attachment_ids_raw) : [];
        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No attachment IDs provided.', 'wp-alt-text-plugin')]);
        }

        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            wp_send_json_error(['message' => __('Invalid attachment IDs.', 'wp-alt-text-plugin')]);
        }

        $results = [];
        foreach ($ids as $id) {
            if (!$this->is_image($id)) {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => __('Attachment is not an image.', 'wp-alt-text-plugin'),
                ];
                continue;
            }

            $generation = $this->generate_and_save($id, 'inline', 1, [], true);
            if (is_wp_error($generation)) {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => $generation->get_error_message(),
                    'code'    => $generation->get_error_code(),
                ];
            } else {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => true,
                    'alt_text' => $generation,
                    'title'    => get_the_title($id),
                ];
            }
        }

        BbAI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        wp_send_json_success([
            'results' => $results,
        ]);
    }
}

// Class instantiation moved to class-opptibbai-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Inline JS fallback to add row-action behaviour
add_action('admin_footer-upload.php', function(){
    ?>
    <script>
    (function($){
        function refreshDashboard(){
            if (!window.BBAI || !BBAI.restStats || !window.fetch){
                return;
            }

            var nonce = (BBAI.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''));
            var headers = {
                'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
            };
            var statsUrl = BBAI.restStats + (BBAI.restStats.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';
            var usageUrl = BBAI.restUsage || '';

            Promise.all([
                fetch(statsUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }),
                usageUrl ? fetch(usageUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }) : Promise.resolve(null)
            ])
            .then(function(results){
                var stats = results[0], usage = results[1];
                if (!stats){ return; }
                if (typeof window.dispatchEvent === 'function'){
                    try {
                        window.dispatchEvent(new CustomEvent('bbai-stats-update', { detail: { stats: stats, usage: usage } }));
                    } catch(e){}
                }
            })
            .catch(function(){});
        }

        function restore(btn){
            var original = btn.data('original-text');
            btn.text(original || 'Generate Alt');
            if (btn.is('button, input')){
                btn.prop('disabled', false);
            }
        }

        function updateAltField(id, value, context){
            var selectors = [
                '#attachment_alt',
                '#attachments-' + id + '-alt',
                '[data-setting="alt"] textarea',
                '[data-setting="alt"] input',
                '[name="attachments[' + id + '][alt]"]',
                '[name="attachments[' + id + '][_wp_attachment_image_alt]"]',
                '[name="attachments[' + id + '][image_alt]"]',
                'textarea[name="_wp_attachment_image_alt"]',
                'input[name="_wp_attachment_image_alt"]',
                'textarea[aria-label="Alternative Text"]',
                '.attachment-details textarea',
                '.attachment-details input[name*="_wp_attachment_image_alt"]'
            ];
            var field;
            selectors.some(function(sel){
                var scoped = context && context.length ? context.find(sel) : $(sel);
                if (scoped.length){
                    field = scoped.first();
                    return true;
                }
                return false;
            });
            // Hard fallback: directly probe common fields on the attachment edit screen
            if ((!field || !field.length)){
                var fallback = $('#attachment_alt');
                if (!fallback.length){ fallback = $('textarea[name="_wp_attachment_image_alt"]'); }
                if (!fallback.length){ fallback = $('textarea[aria-label="Alternative Text"]'); }
                if (fallback.length){ field = fallback.first(); }
            }
            if (field && field.length){
                field.val(value);
                field.text(value);
                field.attr('value', value);
                field.trigger('input').trigger('change');
            } else {
                // Fallback: update via REST media endpoint (alt_text)
                try {
                    var mediaUrl = (window.wp && window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'index.php?rest_route=/') : '/wp-json/');
                    var nonce = (BBAI && BBAI.nonce) ? BBAI.nonce : (window.wpApiSettings ? wpApiSettings.nonce : '');
                    if (mediaUrl && nonce){
                        fetch(mediaUrl + 'wp/v2/media/' + id, {
                            method: 'POST',
                            headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
                            body: JSON.stringify({ alt_text: value })
                        }).then(function(){
                            var c = context && context.length ? context : $('.attachment-details');
                            var tf = c.find('textarea, input').filter('[name*="_wp_attachment_image_alt"], [aria-label="Alternative Text"], #attachment_alt').first();
                            if (tf && tf.length){ tf.val(value).text(value).attr('value', value).trigger('input').trigger('change'); }
                        }).catch(function(){});
                    }
                } catch(e){}
            }

            if (window.wp && wp.media && typeof wp.media.attachment === 'function'){
                var attachment = wp.media.attachment(id);
                if (attachment){
                    try { attachment.set('alt', value); } catch (err) {}
                }
            }
        }

        function pushNotice(type, message){
            if (window.wp && wp.data && wp.data.dispatch){
                try {
                    wp.data.dispatch('core/notices').createNotice(type, message, { isDismissible: true });
                    return;
                } catch(err) {}
            }
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            var $target = $('#wpbody-content').find('.wrap').first();
            if ($target.length){
                $target.prepend($notice);
            } else {
                $('#wpbody-content').prepend($notice);
            }
        }

        function canManageAccount(){
            return !!(window.BBAI && BBAI.canManage);
        }

        function handleLimitReachedNotice(payload){
            var message = (payload && payload.message) ? payload.message : 'Monthly limit reached. Please contact a site administrator.';
            pushNotice('warning', message);

            if (!canManageAccount()){
                return;
            }

            try {
                // Try bbaiApp namespace first
                if (window.bbaiApp && bbaiApp.upgradeUrl) {
                    window.open(bbaiApp.upgradeUrl, '_blank');
                } else if (window.BBAI && BBAI.upgradeUrl) {
                    window.open(BBAI.upgradeUrl, '_blank');
                } else if (window.BBAI && BBAI.upgradeUrl) {
                    // Legacy fallback
                    window.open(BBAI.upgradeUrl, '_blank');
                }
            } catch(e){}

            if (jQuery('.bbai-upgrade-banner').length){
                jQuery('.bbai-upgrade-banner .show-upgrade-modal').trigger('click');
            }
        }

        $(document).on('click', '.bbai-generate', function(e){
            e.preventDefault();
            if (!window.BBAI || !BBAI.rest){
                return pushNotice('error', 'AI ALT: REST URL missing.');
            }

            var btn = $(this);
            var id = btn.data('id');
            if (!id){ return pushNotice('error', 'AI ALT: Attachment ID missing.'); }

            if (typeof btn.data('original-text') === 'undefined'){
                btn.data('original-text', btn.text());
            }

            btn.text('Generating…');
            if (btn.is('button, input')){
                btn.prop('disabled', true);
            }

            var headers = {'X-WP-Nonce': (BBAI.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''))};
            var context = btn.closest('.compat-item, .attachment-details, .media-modal');

            fetch(BBAI.rest + id, { method:'POST', headers: headers })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data && data.alt){
                        updateAltField(id, data.alt, context);
                        pushNotice('success', 'ALT generated: ' + data.alt);
                        if (!context.length){
                        location.reload();
                        }
                        refreshDashboard();
                    } else if (data && data.code === 'beepbeepai_dry_run'){
                        pushNotice('info', data.message || 'Dry run enabled. Prompt stored for review.');
                        refreshDashboard();
                    } else if (data && data.code === 'limit_reached'){
                        handleLimitReachedNotice(data);
                    } else {
                        var message = (data && (data.message || (data.data && data.data.message))) || 'Failed to generate ALT';
                        pushNotice('error', message);
                    }
                })
                .catch(function(err){
                    var message = (err && err.message) ? err.message : 'Request failed.';
                    pushNotice('error', message);
                })
                .then(function(){ restore(btn); });
        });
    })(jQuery);
    </script>
    <?php
});

// Attachment edit screen behaviour handled via enqueued scripts; inline scripts removed for compliance.

// Inline admin CSS removed; if needed, add via wp_add_inline_style on an enqueued handle.
