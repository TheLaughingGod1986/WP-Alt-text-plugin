<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('OPPTIAI_ALT_PLUGIN_FILE')) {
    define('OPPTIAI_ALT_PLUGIN_FILE', dirname(__FILE__, 2) . '/opptiai-alt.php');
}

if (!defined('OPPTIAI_ALT_PLUGIN_DIR')) {
    define('OPPTIAI_ALT_PLUGIN_DIR', plugin_dir_path(OPPTIAI_ALT_PLUGIN_FILE));
}

if (!defined('OPPTIAI_ALT_PLUGIN_URL')) {
    define('OPPTIAI_ALT_PLUGIN_URL', plugin_dir_url(OPPTIAI_ALT_PLUGIN_FILE));
}

if (!defined('OPPTIAI_ALT_PLUGIN_BASENAME')) {
    define('OPPTIAI_ALT_PLUGIN_BASENAME', plugin_basename(OPPTIAI_ALT_PLUGIN_FILE));
}

// Suppress PHP 8.3 deprecation warnings from WordPress core
// WordPress core has PHP 8.3 compatibility issues (wp-includes/functions.php lines 7360, 2195)
// These errors originate from WordPress core itself, not this plugin
// This is a temporary workaround until WordPress core is fully PHP 8.3 compatible
if (PHP_VERSION_ID >= 80300) {
    $old_error_reporting = error_reporting();
    // Suppress only E_DEPRECATED, keep all other error reporting
    error_reporting($old_error_reporting & ~E_DEPRECATED);
}

// Load API clients, usage tracker, and queue infrastructure
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-queue.php';
require_once OPPTIAI_ALT_PLUGIN_DIR . 'includes/class-debug-log.php';

class Opptiai_Alt_Core {
    const OPTION_KEY = 'opptiai_alt_settings';
    const NONCE_KEY  = 'opptiai_alt_nonce';
    const CAPABILITY = 'manage_ai_alt_text';

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
        $this->api_client = new AltText_AI_API_Client_V2();
        // Soft-migrate legacy options to new prefixed keys
        $current = get_option(self::OPTION_KEY, null);
        if ($current === null) {
            foreach (['ai_alt_gpt_settings', 'opptiai_settings'] as $legacy_key) {
                $legacy_value = get_option($legacy_key, null);
                if ($legacy_value !== null) {
                    update_option(self::OPTION_KEY, $legacy_value, false);
                    break;
                }
            }
        }

        if (class_exists('AltText_AI_Debug_Log')) {
            // Ensure table exists
            AltText_AI_Debug_Log::create_table();
            
            // Log initialization
            AltText_AI_Debug_Log::log('info', 'AI Alt Text plugin initialized', [
                'version' => OPPTIAI_ALT_VERSION,
                'authenticated' => $this->api_client->is_authenticated() ? 'yes' : 'no',
            ], 'core');
            
            update_option('opptiai_alt_logs_ready', true, false);
        }
    }

    /**
     * Expose API client for collaborators (REST controller, admin UI, etc.).
     *
     * @return AltText_AI_API_Client_V2
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
            set_transient('opptiai_token_notice', [
                'total' => $current['total'],
                'limit' => $opts['token_limit'],
            ], DAY_IN_SECONDS);
            $this->send_notification(
                __('AI Alt Text token usage alert', 'opptiai-alt-text-generator'),
                sprintf(
                    __('Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'opptiai-alt-text-generator'),
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

        $cache_key = 'opptiai_alt_usage_refresh_lock';
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

        AltText_AI_Usage_Tracker::update_usage($latest_usage);
        set_transient($cache_key, time(), MINUTE_IN_SECONDS);
    }

    private function get_debug_bootstrap($force_refresh = false) {
        if ($force_refresh || $this->debug_bootstrap === null) {
            if (class_exists('AltText_AI_Debug_Log')) {
                $this->debug_bootstrap = AltText_AI_Debug_Log::get_logs([
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
        $data = get_transient('opptiai_token_notice');
        if (!$data) {
            // Fallback to legacy transient name during transition
            $data = get_transient('opptiai_alt_token_notice');
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
        if ($page !== 'opptiai-alt-checkout') { return; }

        if (!$this->user_can_manage()) {
            wp_die(__('You do not have permission to perform this action.', 'opptiai-alt-text-generator'));
        }

        $nonce = isset($_GET['_opptiai_alt_nonce']) && $_GET['_opptiai_alt_nonce'] !== null ? sanitize_text_field(wp_unslash($_GET['_opptiai_alt_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'opptiai_alt_direct_checkout')) {
            wp_die(__('Security check failed. Please try again from the dashboard.', 'opptiai-alt-text-generator'));
        }

        $plan_param = sanitize_key($_GET['plan'] ?? $_GET['type'] ?? '');
        $price_id = isset($_GET['price_id']) && $_GET['price_id'] !== null ? sanitize_text_field(wp_unslash($_GET['price_id'])) : '';
        $fallback = AltText_AI_Usage_Tracker::get_upgrade_url();

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

        $success_url = admin_url('upload.php?page=opptiai-alt&checkout=success');
        $cancel_url  = admin_url('upload.php?page=opptiai-alt&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result) || empty($result['url'])) {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Unable to start checkout. Please try again.', 'opptiai-alt-text-generator');
            $plan_param = sanitize_key($_GET['plan'] ?? $_GET['type'] ?? '');
            $query_args = [
                'page'            => 'opptiai-alt-text-generator',
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

        $cached = get_transient('opptiai_alt_remote_price_ids');
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
                    set_transient('opptiai_alt_remote_price_ids', $remote, 10 * MINUTE_IN_SECONDS);
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
        $stored = get_option('opptiai_alt_checkout_prices', []);
        if (is_array($stored) && !empty($stored)) {
            foreach ($stored as $key => $value) {
                $key = is_string($key) ? sanitize_key($key) : '';
                $value = is_string($value) ? sanitize_text_field($value) : '';
                if ($key && $value && empty($prices[$key])) {
                    $prices[$key] = $value;
                }
            }
        }

        $prices = apply_filters('opptiai_alt_checkout_price_ids', $prices);
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
        return apply_filters('opptiai_alt_checkout_price_id', $price_id, $plan, $prices);
    }

    /**
     * Surface checkout success/error notices in WP Admin
     */
    public function maybe_render_checkout_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'opptiai-alt-text-generator') {
            return;
        }

        if (!empty($_GET['checkout_error'])) {
            $message = !empty($_GET['checkout_error']) && $_GET['checkout_error'] !== null ? sanitize_text_field(wp_unslash($_GET['checkout_error'])) : '';
            $plan    = !empty($_GET['plan']) && $_GET['plan'] !== null ? sanitize_text_field(wp_unslash($_GET['plan'])) : '';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Checkout Failed', 'opptiai-alt-text-generator'); ?>:</strong>
                    <?php echo esc_html($message); ?>
                    <?php if ($plan) : ?>
                        (<?php echo esc_html(sprintf(__('Plan: %s', 'opptiai-alt-text-generator'), $plan)); ?>)
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Verify your AltText AI account credentials and Stripe configuration, then try again.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        } elseif (!empty($_GET['checkout']) && $_GET['checkout'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Checkout session started. Complete the payment in the newly opened tab.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        } elseif (!empty($_GET['checkout']) && $_GET['checkout'] === 'cancel') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php esc_html_e('Checkout was cancelled. No changes were made to your plan.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        // Password reset notices
        if (!empty($_GET['password_reset']) && $_GET['password_reset'] === 'requested') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Email Sent', 'opptiai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        if (!empty($_GET['password_reset']) && $_GET['password_reset'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Successful', 'opptiai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your password has been updated. You can now sign in with your new password.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        // Subscription update notices
        if (!empty($_GET['subscription_updated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Subscription Updated', 'opptiai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your subscription information has been refreshed.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        if (!empty($_GET['portal_return']) && $_GET['portal_return'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Billing Updated', 'opptiai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'opptiai-alt-text-generator'); ?></p>
            </div>
            <?php
        }
    }

    public function render_token_notice(){
        if (empty($this->token_notice)){
            return;
        }
        delete_transient('opptiai_token_notice');
        delete_transient('opptiai_alt_token_notice');
        $total = number_format_i18n($this->token_notice['total'] ?? 0);
        $limit = number_format_i18n($this->token_notice['limit'] ?? 0);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(__('AI Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'opptiai-alt-text-generator'), $total, $limit)) . '</p></div>';
        $this->token_notice = null;
    }

    public function maybe_render_queue_notice(){
        if (!isset($_GET['ai_alt_queued'])) {
            return;
        }
        $count = intval($_GET['ai_alt_queued']);
        if ($count <= 0) {
            return;
        }
        $message = $count === 1
            ? __('1 image queued for background optimisation. The alt text will appear shortly.', 'opptiai-alt-text-generator')
            : sprintf(__('Queued %d images for background optimisation. Alt text will be generated shortly.', 'opptiai-alt-text-generator'), $count);
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public function deactivate(){
        wp_clear_scheduled_hook(AltText_AI_Queue::CRON_HOOK);
    }

    public function activate() {
        global $wpdb;

        AltText_AI_Queue::create_table();
        AltText_AI_Queue::schedule_processing(10);
        AltText_AI_Debug_Log::create_table();
        update_option('alttextai_logs_ready', true, false);

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

        // ALWAYS force production API URL (never use saved localhost URLs)
        $updated['api_url'] = 'https://alttext-ai-backend.onrender.com';

        update_option(self::OPTION_KEY, $updated, false);

        // Clear any invalid cached tokens
        delete_option('opptiai_alt_jwt_token');
        delete_option('opptiai_alt_user_data');
        delete_transient('opptiai_alt_token_last_check');

        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)){
            $role->add_cap(self::CAPABILITY);
        }
    }
    
    private function create_performance_indexes() {
        global $wpdb;
        
        // Suppress errors for better compatibility across MySQL versions
        $wpdb->suppress_errors(true);
        
        // Index for _ai_alt_generated_at (used in sorting and stats)
        $wpdb->query("
            CREATE INDEX idx_ai_alt_generated_at 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        ");
        
        // Index for _ai_alt_source (used in stats aggregation)
        $wpdb->query("
            CREATE INDEX idx_ai_alt_source 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(50))
        ");
        
        // Index for _wp_attachment_image_alt (used in coverage stats)
        $wpdb->query("
            CREATE INDEX idx_wp_attachment_alt 
            ON {$wpdb->postmeta} (meta_key(50), meta_value(100))
        ");
        
        // Composite index for attachment queries
        $wpdb->query("
            CREATE INDEX idx_posts_attachment_image 
            ON {$wpdb->posts} (post_type(20), post_mime_type(20), post_status(20))
        ");
        
        $wpdb->suppress_errors(false);
    }

    public function add_settings_page() {
        $cap = current_user_can(self::CAPABILITY) ? self::CAPABILITY : 'manage_options';
        add_media_page(
            'AI Alt Text Generation',
            'AI Alt Text Generation',
            $cap,
            'opptiai-alt',
            [$this, 'render_settings_page']
        );

        // Hidden checkout redirect page
        add_submenu_page(
            null, // No parent = hidden from menu
            'Checkout',
            'Checkout',
            $cap,
            'opptiai-alt-checkout',
            [$this, 'handle_checkout_redirect']
        );
    }

    public function handle_checkout_redirect() {
        if (!$this->api_client->is_authenticated()) {
            wp_die('Please sign in first to upgrade.');
        }

        $price_id = sanitize_text_field($_GET['price_id'] ?? '');
        if (empty($price_id)) {
            wp_die('Invalid checkout request.');
        }

        $success_url = admin_url('upload.php?page=opptiai-alt&checkout=success');
        $cancel_url = admin_url('upload.php?page=opptiai-alt&checkout=cancel');

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
        register_setting('opptiai_alt_group', self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => function($input){
                $existing = get_option(self::OPTION_KEY, []);
                $out = [];
                $out['api_url']          = esc_url_raw($input['api_url'] ?? 'https://alttext-ai-backend.onrender.com');
                $out['model']            = sanitize_text_field($input['model'] ?? 'gpt-4o-mini');
                $out['max_words']        = max(4, intval($input['max_words'] ?? 16));
                $lang_input = sanitize_text_field($input['language'] ?? 'en-GB');
                $custom_input = sanitize_text_field($input['language_custom'] ?? '');
                if ($lang_input === 'custom'){
                    $out['language'] = $custom_input ? $custom_input : 'en-GB';
                    $out['language_custom'] = $custom_input;
                } else {
                    $out['language'] = $lang_input ?: 'en-GB';
                    $out['language_custom'] = '';
                }
                $out['enable_on_upload'] = !empty($input['enable_on_upload']);
                $out['tone']             = sanitize_text_field($input['tone'] ?? 'professional, accessible');
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
                $out['custom_prompt'] = wp_kses_post($input['custom_prompt'] ?? '');
                $notify = sanitize_text_field($input['notify_email'] ?? ($existing['notify_email'] ?? get_option('admin_email')));
                $out['notify_email'] = is_email($notify) ? $notify : ($existing['notify_email'] ?? get_option('admin_email'));
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
                'dashboard' => __('Dashboard', 'opptiai-alt-text-generator'),
                'guide'     => __('How to', 'opptiai-alt-text-generator'),
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
                'dashboard' => __('Dashboard', 'opptiai-alt-text-generator'),
                'library'   => __('ALT Library', 'opptiai-alt-text-generator'),
                'guide'     => __('How to', 'opptiai-alt-text-generator'),
            ];
            
            // For agency and pro: add Admin tab (contains Debug Logs and Settings)
            // For non-premium authenticated users: show Debug Logs and Settings tabs
            if ($is_pro) {
                $tabs['admin'] = __('Admin', 'opptiai-alt-text-generator');
            } elseif ($is_authenticated) {
                $tabs['debug'] = __('Debug Logs', 'opptiai-alt-text-generator');
                $tabs['settings'] = __('Settings', 'opptiai-alt-text-generator');
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
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=opptiai_alt_usage_export'), 'opptiai_alt_usage_export');
        $audit_rows = $stats['audit'] ?? [];
        $debug_bootstrap = $this->get_debug_bootstrap();
        ?>
        <div class="wrap ai-alt-wrap alttextai-modern">
            <!-- Dark Header -->
            <div class="alttextai-header">
                <div class="alttextai-header-content">
                    <div class="alttextai-logo">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="alttextai-logo-icon">
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
                        <div class="alttextai-logo-content">
                            <span class="alttextai-logo-text"><?php esc_html_e('AltText AI by OpptiAI', 'opptiai-alt-text-generator'); ?></span>
                            <span class="alttextai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <nav class="alttextai-nav">
                        <?php foreach ($tabs as $slug => $label) :
                            $url = esc_url(add_query_arg(['tab' => $slug]));
                            $active = $tab === $slug ? ' active' : '';
                        ?>
                            <a href="<?php echo $url; ?>" class="alttextai-nav-link<?php echo esc_attr($active); ?>"><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <!-- Auth & Subscription Actions -->
                    <div class="alttextai-header-actions">
                        <?php
                        $has_license = $this->api_client->has_active_license();
                        $is_authenticated = $this->api_client->is_authenticated();

                        if ($is_authenticated || $has_license) :
                            $usage_stats = AltText_AI_Usage_Tracker::get_stats_display();
                            $account_summary = $is_authenticated ? $this->get_account_summary($usage_stats) : null;
                            $plan_slug  = $usage_stats['plan'] ?? 'free';
                            $plan_label = $usage_stats['plan_label'] ?? ucfirst($plan_slug);
                            $connected_email = $account_summary['email'] ?? '';
                            $billing_portal = AltText_AI_Usage_Tracker::get_billing_portal_url();

                            // If license-only mode (no personal login), show license info
                            if ($has_license && !$is_authenticated) {
                                $license_data = $this->api_client->get_license_data();
                                $connected_email = $license_data['organization']['name'] ?? __('License Active', 'opptiai-alt-text-generator');
                            }
                        ?>
                            <!-- Compact Account Bar in Header -->
                            <div class="alttextai-header-account-bar">
                                <span class="alttextai-header-account-email"><?php echo esc_html($connected_email ?: __('Connected', 'opptiai-alt-text-generator')); ?></span>
                                <span class="alttextai-header-plan-badge"><?php echo esc_html($plan_label); ?></span>
                                <?php if ($plan_slug === 'free' && !$has_license) : ?>
                                    <button type="button" class="alttextai-header-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade Plan', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                <?php elseif (!empty($billing_portal) && $is_authenticated) : ?>
                                    <button type="button" class="alttextai-header-manage-btn" data-action="open-billing-portal">
                                        <?php esc_html_e('Manage', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($is_authenticated) : ?>
                                <button type="button" class="alttextai-header-disconnect-btn" data-action="disconnect-account">
                                    <?php esc_html_e('Disconnect', 'opptiai-alt-text-generator'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <button type="button" class="alttextai-btn-primary" data-action="show-auth-modal" data-auth-tab="login">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span><?php esc_html_e('Login', 'opptiai-alt-text-generator'); ?></span>
                            </button>
                        <?php endif; ?>
                </div>
                </div>
            </div>
            
            <!-- Main Content Container -->
            <div class="alttextai-container">

            <?php if ($tab === 'dashboard') : ?>
            <?php
                $coverage_numeric = max(0, min(100, floatval($stats['coverage'])));
                $coverage_decimals = $coverage_numeric === floor($coverage_numeric) ? 0 : 1;
                $coverage_display = number_format_i18n($coverage_numeric, $coverage_decimals);
                /* translators: %s: Percentage value */
                $coverage_text = $coverage_display . '%';
                /* translators: %s: Percentage value */
                $coverage_value_text = sprintf(__('ALT coverage at %s', 'opptiai-alt-text-generator'), $coverage_text);
            ?>

            <?php
                $checkout_nonce = wp_create_nonce('opptiai_alt_direct_checkout');
                $checkout_base  = admin_url('admin.php');
                $price_ids      = $this->get_checkout_price_ids();

                $pro_plan  = [
                    'page'             => 'opptiai-alt-checkout',
                    'plan'             => 'pro',
                    'price_id'         => $price_ids['pro'] ?? '',
                    '_opptiai_alt_nonce' => $checkout_nonce,
                ];
                $agency_plan = [
                    'page'             => 'opptiai-alt-checkout',
                    'plan'             => 'agency',
                    'price_id'         => $price_ids['agency'] ?? '',
                    '_opptiai_alt_nonce' => $checkout_nonce,
                ];
                $credits_plan = [
                    'page'             => 'opptiai-alt-checkout',
                    'type'             => 'credits',
                    'price_id'         => $price_ids['credits'] ?? '',
                    '_opptiai_alt_nonce' => $checkout_nonce,
                ];
                $pro_test_url     = esc_url(add_query_arg($pro_plan, $checkout_base));
                $agency_test_url  = esc_url(add_query_arg($agency_plan, $checkout_base));
                $credits_test_url = esc_url(add_query_arg($credits_plan, $checkout_base));
            ?>

            <div class="alttextai-clean-dashboard" data-stats='<?php echo esc_attr(wp_json_encode($stats)); ?>'>
                <?php
                // Get usage stats
                $usage_stats = AltText_AI_Usage_Tracker::get_stats_display();
                
                // Pull fresh usage from backend to avoid stale cache - same logic as Settings tab
                if (isset($this->api_client)) {
                    $live_usage = $this->api_client->get_usage();
                    if (is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                        // Update cache with fresh API data
                        AltText_AI_Usage_Tracker::update_usage($live_usage);
                    }
                }
                // Get stats - will use the just-updated cache
                $usage_stats = AltText_AI_Usage_Tracker::get_stats_display(false);
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
                        $usage_stats['percentage_display'] = AltText_AI_Usage_Tracker::format_percentage_label($usage_stats['percentage']);
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
                $usage_stats['percentage_display'] = AltText_AI_Usage_Tracker::format_percentage_label($percentage);
                ?>
                
                <!-- Clean Dashboard Design -->
                <div class="alttextai-dashboard-shell max-w-5xl mx-auto px-6">

                     <!-- HERO Section Styles -->
                     <style>
                         .alttextai-hero-section {
                             background: linear-gradient(to bottom, #f7fee7 0%, #ffffff 100%);
                             border-radius: 24px;
                             margin-bottom: 32px;
                             padding: 48px 40px;
                             text-align: center;
                             box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                         }
                         .alttextai-hero-content {
                             margin-bottom: 32px;
                         }
                         .alttextai-hero-title {
                             margin: 0 0 16px 0;
                             font-size: 2.5rem;
                             font-weight: 700;
                             color: #0f172a;
                             line-height: 1.2;
                         }
                         .alttextai-hero-subtitle {
                             margin: 0;
                             font-size: 1.125rem;
                             color: #475569;
                             line-height: 1.6;
                             max-width: 600px;
                             margin-left: auto;
                             margin-right: auto;
                         }
                         .alttextai-hero-actions {
                             display: flex;
                             flex-direction: column;
                             align-items: center;
                             gap: 16px;
                             margin-bottom: 24px;
                         }
                         .alttextai-hero-btn-primary {
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
                         .alttextai-hero-btn-primary:hover {
                             opacity: 0.9;
                         }
                         .alttextai-hero-link-secondary {
                             background: transparent;
                             border: none;
                             color: #6b7280;
                             text-decoration: underline;
                             font-size: 14px;
                             cursor: pointer;
                             transition: color 0.2s ease;
                             padding: 0;
                         }
                         .alttextai-hero-link-secondary:hover {
                             color: #14b8a6;
                         }
                         .alttextai-hero-micro-copy {
                             font-size: 14px;
                             color: #64748b;
                             font-weight: 500;
                         }
                     </style>

                     <?php if (!$this->api_client->is_authenticated()) : ?>
                     <!-- HERO Section - Not Authenticated -->
                     <div class="alttextai-hero-section">
                         <div class="alttextai-hero-content">
                             <h2 class="alttextai-hero-title">
                                 <?php esc_html_e('🎉 Boost Your Site\'s SEO Automatically', 'opptiai-alt-text-generator'); ?>
                             </h2>
                             <p class="alttextai-hero-subtitle">
                                 <?php esc_html_e('Let AI write perfect, accessibility-friendly alt text for every image — free each month.', 'opptiai-alt-text-generator'); ?>
                             </p>
                         </div>
                         <div class="alttextai-hero-actions">
                             <button type="button" class="alttextai-hero-btn-primary" id="alttextai-show-auth-banner-btn">
                                <?php esc_html_e('Start Free — Generate 50 AI Descriptions', 'opptiai-alt-text-generator'); ?>
                             </button>
                             <button type="button" class="alttextai-hero-link-secondary" id="alttextai-show-auth-login-btn">
                                 <?php esc_html_e('Already a user? Log in', 'opptiai-alt-text-generator'); ?>
                             </button>
                         </div>
                         <div class="alttextai-hero-micro-copy">
                             <?php esc_html_e('⚡ SEO Boost · 🦾 Accessibility · 🕒 Saves Hours', 'opptiai-alt-text-generator'); ?>
                         </div>
                     </div>
                     <?php endif; ?>
                     <!-- Subscription management now in header -->


                    <!-- Tab Content: Dashboard -->
                    <div class="alttextai-tab-content active" id="tab-dashboard">
                    <!-- Premium Dashboard Container -->
                    <div class="alttextai-premium-dashboard">
                        <!-- Subtle Header Section -->
                        <div class="alttextai-dashboard-header-section">
                            <h1 class="alttextai-dashboard-title"><?php esc_html_e('Dashboard', 'opptiai-alt-text-generator'); ?></h1>
                            <p class="alttextai-dashboard-subtitle"><?php esc_html_e('Automated, accessible alt text generation for your WordPress media library.', 'opptiai-alt-text-generator'); ?></p>
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
                            $plan_badge_class = 'alttextai-usage-plan-badge';
                            $is_agency = ($plan_slug === 'agency');
                            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                            
                            if ($plan_slug === 'agency') {
                                $plan_badge_text = esc_html__('AGENCY', 'opptiai-alt-text-generator');
                                $plan_badge_class .= ' alttextai-usage-plan-badge--agency';
                            } elseif ($plan_slug === 'pro') {
                                $plan_badge_text = esc_html__('PRO', 'opptiai-alt-text-generator');
                                $plan_badge_class .= ' alttextai-usage-plan-badge--pro';
                            } else {
                                $plan_badge_text = esc_html__('FREE', 'opptiai-alt-text-generator');
                                $plan_badge_class .= ' alttextai-usage-plan-badge--free';
                            }
                        ?>
                        <!-- Premium Stats Grid -->
                        <div class="alttextai-premium-stats-grid<?php echo $is_agency ? ' alttextai-premium-stats-grid--single' : ''; ?>">
                            <!-- Usage Card with Circular Progress -->
                            <div class="alttextai-premium-card alttextai-usage-card<?php echo $is_agency ? ' alttextai-usage-card--full-width' : ''; ?>">
                                <?php if ($is_agency) : ?>
                                <!-- Soft purple gradient badge for Agency -->
                                <span class="alttextai-usage-plan-badge alttextai-usage-plan-badge--agency-polished"><?php echo esc_html__('AGENCY', 'opptiai-alt-text-generator'); ?></span>
                                <?php else : ?>
                                <span class="<?php echo esc_attr($plan_badge_class); ?>"><?php echo $plan_badge_text; ?></span>
                                <?php endif; ?>
                                <?php
                                $percentage = min(100, max(0, $usage_stats['percentage'] ?? 0));
                                $percentage_display = $usage_stats['percentage_display'] ?? AltText_AI_Usage_Tracker::format_percentage_label($percentage);
                                $radius = 54;
                                $circumference = 2 * M_PI * $radius;
                                // Calculate offset: at 0% = full circumference (hidden), at 100% = 0 (fully visible)
                                $stroke_dashoffset = $circumference * (1 - ($percentage / 100));
                                $gradient_id = 'grad-' . wp_generate_password(8, false);
                                ?>
                                <?php if ($is_agency) : ?>
                                <!-- Full-width agency layout - Polished Design -->
                                <div class="alttextai-usage-card-layout-full">
                                    <div class="alttextai-usage-card-left">
                                        <h3 class="alttextai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'opptiai-alt-text-generator'); ?></h3>
                                        <div class="alttextai-usage-card-stats">
                                            <div class="alttextai-usage-stat-item">
                                                <div class="alttextai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['used'])); ?></div>
                                                <div class="alttextai-usage-stat-label"><?php esc_html_e('Generated', 'opptiai-alt-text-generator'); ?></div>
                                            </div>
                                            <div class="alttextai-usage-stat-divider"></div>
                                            <div class="alttextai-usage-stat-item">
                                                <div class="alttextai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['limit'])); ?></div>
                                                <div class="alttextai-usage-stat-label"><?php esc_html_e('Monthly Limit', 'opptiai-alt-text-generator'); ?></div>
                                            </div>
                                            <div class="alttextai-usage-stat-divider"></div>
                                            <div class="alttextai-usage-stat-item">
                                                <div class="alttextai-usage-stat-value"><?php echo esc_html(number_format_i18n($usage_stats['remaining'] ?? 0)); ?></div>
                                                <div class="alttextai-usage-stat-label"><?php esc_html_e('Remaining', 'opptiai-alt-text-generator'); ?></div>
                                            </div>
                                        </div>
                                        <div class="alttextai-usage-card-reset">
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
                                                    printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($formatted_date));
                                                } else {
                                                    printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($reset_date));
                                                }
                                            } else {
                                                esc_html_e('Resets monthly', 'opptiai-alt-text-generator');
                                            }
                                            ?>
                                            </span>
                                        </div>
                                        <?php 
                                        $plan_slug = $usage_stats['plan'] ?? 'free';
                                        $billing_portal = AltText_AI_Usage_Tracker::get_billing_portal_url();
                                        ?>
                                        <?php if (!empty($billing_portal)) : ?>
                                        <div class="alttextai-usage-card-actions">
                                            <a href="#" class="alttextai-usage-billing-link" data-action="open-billing-portal">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;" aria-hidden="true">
                                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                                </svg>
                                                <?php esc_html_e('Manage Billing', 'opptiai-alt-text-generator'); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alttextai-usage-card-divider" aria-hidden="true"></div>
                                    <div class="alttextai-usage-card-right">
                                        <div class="alttextai-usage-ring-wrapper">
                                            <?php
                                            // Modern thin stroke ring gauge for agency
                                            $agency_radius = 60;
                                            $agency_circumference = 2 * M_PI * $agency_radius;
                                            $agency_stroke_dashoffset = $agency_circumference * (1 - ($percentage / 100));
                                            $agency_gradient_id = 'grad-agency-' . wp_generate_password(8, false);
                                            ?>
                                            <div class="alttextai-circular-progress alttextai-circular-progress--agency" 
                                                 data-percentage="<?php echo esc_attr($percentage); ?>"
                                                 aria-label="<?php printf(esc_attr__('Credits used: %s%%', 'opptiai-alt-text-generator'), esc_attr($percentage_display)); ?>"
                                                 role="progressbar"
                                                 aria-valuenow="<?php echo esc_attr($percentage); ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                                <svg class="alttextai-circular-progress-svg" viewBox="0 0 140 140" aria-hidden="true">
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
                                                        class="alttextai-circular-progress-bg" />
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
                                                        class="alttextai-circular-progress-bar"
                                                        data-circumference="<?php echo esc_attr($agency_circumference); ?>"
                                                        data-offset="<?php echo esc_attr($agency_stroke_dashoffset); ?>"
                                                        transform="rotate(-90 70 70)" />
                                                </svg>
                                                <div class="alttextai-circular-progress-text">
                                                    <div class="alttextai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                                    <div class="alttextai-circular-progress-label"><?php esc_html_e('Credits Used', 'opptiai-alt-text-generator'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else : ?>
                                <!-- Standard vertical layout -->
                                <h3 class="alttextai-usage-card-title"><?php esc_html_e('Alt Text Generated This Month', 'opptiai-alt-text-generator'); ?></h3>
                                <div class="alttextai-usage-ring-wrapper">
                                    <div class="alttextai-circular-progress" data-percentage="<?php echo esc_attr($percentage); ?>">
                                        <svg class="alttextai-circular-progress-svg" viewBox="0 0 120 120">
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
                                                class="alttextai-circular-progress-bg" />
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
                                                class="alttextai-circular-progress-bar"
                                                data-circumference="<?php echo esc_attr($circumference); ?>"
                                                data-offset="<?php echo esc_attr($stroke_dashoffset); ?>" />
                                        </svg>
                                        <div class="alttextai-circular-progress-text">
                                            <div class="alttextai-circular-progress-percent"><?php echo esc_html($percentage_display); ?>%</div>
                                            <div class="alttextai-circular-progress-label"><?php esc_html_e('credits used', 'opptiai-alt-text-generator'); ?></div>
                                        </div>
                                    </div>
                                    <button type="button" class="alttextai-usage-tooltip" aria-label="<?php esc_attr_e('How quotas work', 'opptiai-alt-text-generator'); ?>" title="<?php esc_attr_e('Your monthly quota resets on the first of each month. Upgrade to Pro for unlimited generations.', 'opptiai-alt-text-generator'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                            <path d="M8 5V5.01M8 11H8.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="alttextai-usage-details">
                                    <div class="alttextai-usage-text">
                                        <strong><?php echo esc_html($usage_stats['used']); ?></strong> / <strong><?php echo esc_html($usage_stats['limit']); ?></strong>
                                    </div>
                                    <div class="alttextai-usage-microcopy">
                                        <?php 
                                        $reset_date = $usage_stats['reset_date'] ?? '';
                                        if (!empty($reset_date)) {
                                            // Format as "resets MONTH DAY, YEAR"
                                            $reset_timestamp = strtotime($reset_date);
                                            if ($reset_timestamp !== false) {
                                                $formatted_date = date_i18n('F j, Y', $reset_timestamp);
                                                printf(
                                                    esc_html__('Resets %s', 'opptiai-alt-text-generator'),
                                                    esc_html($formatted_date)
                                                );
                                            } else {
                                                printf(
                                                    esc_html__('Resets %s', 'opptiai-alt-text-generator'),
                                                    esc_html($reset_date)
                                                );
                                            }
                                        } else {
                                            esc_html_e('Resets monthly', 'opptiai-alt-text-generator');
                                        }
                                        ?>
                                    </div>
                                    <?php 
                                    $plan_slug = $usage_stats['plan'] ?? 'free';
                                    $billing_portal = AltText_AI_Usage_Tracker::get_billing_portal_url();
                                    $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
                                    ?>
                                    <?php if (!$is_pro) : ?>
                                        <a href="#" class="alttextai-usage-upgrade-link" data-action="show-upgrade-modal">
                                            <?php esc_html_e('Upgrade for unlimited images', 'opptiai-alt-text-generator'); ?> →
                                        </a>
                                    <?php elseif (!empty($billing_portal)) : ?>
                                        <a href="#" class="alttextai-usage-billing-link" data-action="open-billing-portal">
                                            <?php esc_html_e('Manage billing & invoices', 'opptiai-alt-text-generator'); ?> →
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Premium Upsell Card -->
                            <?php if (!$is_agency) : ?>
                            <div class="alttextai-premium-card alttextai-upsell-card">
                                <h3 class="alttextai-upsell-title"><?php esc_html_e('Upgrade to Pro — Unlock Unlimited AI Power', 'opptiai-alt-text-generator'); ?></h3>
                                <ul class="alttextai-upsell-features">
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Unlimited image generations', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Priority queue processing', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Bulk optimisation for large libraries', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Multilingual AI alt text', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                    <li>
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="10" fill="#0EAD4B"/>
                                            <path d="M6 10l2.5 2.5L14 7" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <?php esc_html_e('Faster & more descriptive alt text from improved Vision models', 'opptiai-alt-text-generator'); ?>
                                    </li>
                                </ul>
                                <button type="button" class="alttextai-upsell-cta alttextai-upsell-cta--large" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Pro or Agency', 'opptiai-alt-text-generator'); ?>
                                </button>
                                <p class="alttextai-upsell-microcopy">
                                    <?php esc_html_e('Save 15+ hours/month with automated SEO alt generation.', 'opptiai-alt-text-generator'); ?>
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
                        <div class="alttextai-premium-metrics-grid">
                            <!-- Time Saved Card -->
                            <div class="alttextai-premium-card alttextai-metric-card">
                                <div class="alttextai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="alttextai-metric-value"><?php echo esc_html($hours_saved); ?> hrs</div>
                                <div class="alttextai-metric-label"><?php esc_html_e('TIME SAVED', 'opptiai-alt-text-generator'); ?></div>
                                <div class="alttextai-metric-description"><?php esc_html_e('vs manual optimisation', 'opptiai-alt-text-generator'); ?></div>
                            </div>
                            
                            <!-- Images Optimized Card -->
                            <div class="alttextai-premium-card alttextai-metric-card">
                                <div class="alttextai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="alttextai-metric-value"><?php echo esc_html($optimized); ?></div>
                                <div class="alttextai-metric-label"><?php esc_html_e('IMAGES OPTIMIZED', 'opptiai-alt-text-generator'); ?></div>
                                <div class="alttextai-metric-description"><?php esc_html_e('with generated alt text', 'opptiai-alt-text-generator'); ?></div>
                            </div>
                            
                            <!-- Estimated SEO Impact Card -->
                            <div class="alttextai-premium-card alttextai-metric-card">
                                <div class="alttextai-metric-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                        <path d="M2 12L12 2L22 12M12 8V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="alttextai-metric-value"><?php echo esc_html($coverage_percent); ?>%</div>
                                <div class="alttextai-metric-label"><?php esc_html_e('ESTIMATED SEO IMPACT', 'opptiai-alt-text-generator'); ?></div>
                            </div>
                        </div>
                        
                        <!-- Site-Wide Licensing Notice -->
                        <div class="alttextai-premium-card alttextai-info-notice">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <circle cx="10" cy="10" r="9" stroke="#0ea5e9" stroke-width="1.5" fill="none"/>
                                <path d="M10 6V10M10 14H10.01" stroke="#0ea5e9" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span>
                                <?php 
                                $site_name = trim(get_bloginfo('name'));
                                $site_label = $site_name !== '' ? $site_name : __('this WordPress site', 'opptiai-alt-text-generator');
                                printf(
                                    esc_html__('Quota shared across all users on %s', 'opptiai-alt-text-generator'),
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
                        <div class="alttextai-premium-card alttextai-optimization-card <?php echo ($total_images > 0 && $remaining_imgs === 0) ? 'alttextai-optimization-card--complete' : ''; ?>">
                            <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                <div class="alttextai-optimization-accent-bar"></div>
                            <?php endif; ?>
                            <div class="alttextai-optimization-header">
                                <?php if ($total_images > 0 && $remaining_imgs === 0) : ?>
                                    <div class="alttextai-optimization-success-chip">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                                            <path d="M13 4L6 11L3 8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <h2 class="alttextai-optimization-title">
                                    <?php 
                                    if ($total_images > 0) {
                                        if ($remaining_imgs > 0) {
                                            printf(
                                                esc_html__('%1$d of %2$d images optimized', 'opptiai-alt-text-generator'),
                                                $optimized,
                                                $total_images
                                            );
                                        } else {
                                            // Show checkmark icon for "All images optimized!"
                                            echo '<span class="alttextai-optimization-check-icon">✔</span> ';
                                            printf(
                                                esc_html__('All %1$d images optimized!', 'opptiai-alt-text-generator'),
                                                $total_images
                                            );
                                        }
                                    } else {
                                        esc_html_e('Ready to optimize images', 'opptiai-alt-text-generator');
                                    }
                                    ?>
                                </h2>
                            </div>
                            
                            <?php if ($total_images > 0) : ?>
                                <div class="alttextai-optimization-progress">
                                    <div class="alttextai-optimization-progress-bar">
                                        <div class="alttextai-optimization-progress-fill" style="width: <?php echo esc_attr($coverage_pct); ?>%; background: <?php echo ($remaining_imgs === 0) ? '#10b981' : '#9b5cff'; ?>;"></div>
                                    </div>
                                    <div class="alttextai-optimization-stats">
                                        <div class="alttextai-optimization-stat">
                                            <span class="alttextai-optimization-stat-label"><?php esc_html_e('Optimized', 'opptiai-alt-text-generator'); ?></span>
                                            <span class="alttextai-optimization-stat-value"><?php echo esc_html($optimized); ?></span>
                                        </div>
                                        <div class="alttextai-optimization-stat">
                                            <span class="alttextai-optimization-stat-label"><?php esc_html_e('Remaining', 'opptiai-alt-text-generator'); ?></span>
                                            <span class="alttextai-optimization-stat-value"><?php echo esc_html($remaining_imgs); ?></span>
                                        </div>
                                        <div class="alttextai-optimization-stat">
                                            <span class="alttextai-optimization-stat-label"><?php esc_html_e('Total', 'opptiai-alt-text-generator'); ?></span>
                                            <span class="alttextai-optimization-stat-value"><?php echo esc_html($total_images); ?></span>
                                        </div>
                                    </div>
                                    <div class="alttextai-optimization-actions">
                                        <button type="button" class="alttextai-optimization-cta alttextai-optimization-cta--primary <?php echo (!$can_generate) ? 'alttextai-optimization-cta--locked' : ''; ?>" data-action="generate-missing" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock unlimited alt text with Pro →', 'opptiai-alt-text-generator') . '"' : ''; ?>>
                                            <?php if (!$can_generate) : ?>
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="alttextai-btn-icon">
                                                    <path d="M12 6V4a4 4 0 00-8 0v2M4 6h8l1 8H3L4 6z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'opptiai-alt-text-generator'); ?></span>
                                            <?php else : ?>
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="alttextai-btn-icon">
                                                    <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M6 6H10M6 10H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <span><?php esc_html_e('Generate Missing Alt Text', 'opptiai-alt-text-generator'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="alttextai-optimization-cta alttextai-optimization-cta--secondary <?php echo (!$can_generate) ? 'alttextai-optimization-cta--locked' : ''; ?>" data-action="regenerate-all" <?php echo (!$can_generate) ? 'disabled title="' . esc_attr__('Unlock unlimited alt text with Pro →', 'opptiai-alt-text-generator') . '"' : ''; ?>>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="alttextai-btn-icon">
                                                <path d="M8 2L10 6L14 8L10 10L8 14L6 10L2 8L6 6L8 2Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Re-optimize All Alt Text', 'opptiai-alt-text-generator'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="alttextai-optimization-empty">
                                    <p><?php esc_html_e('Upload images to your WordPress Media Library to get started with AI-powered alt text generation.', 'opptiai-alt-text-generator'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="alttextai-btn-primary">
                                        <?php esc_html_e('Go to Media Library', 'opptiai-alt-text-generator'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Footer Cross-Sell -->
                        <div class="alttextai-premium-footer-cta">
                            <p class="alttextai-footer-cta-text">
                                <?php esc_html_e('Complete your SEO stack', 'opptiai-alt-text-generator'); ?> 
                                <span class="alttextai-footer-cta-link alttextai-footer-cta-link--coming-soon">
                                    <?php esc_html_e('Try our SEO Meta Generator AI', 'opptiai-alt-text-generator'); ?>
                                    <span class="alttextai-footer-cta-badge-new"><?php esc_html_e('New', 'opptiai-alt-text-generator'); ?></span>
                                    <span class="alttextai-footer-cta-badge-coming-soon"><?php esc_html_e('Coming Soon', 'opptiai-alt-text-generator'); ?></span>
                                </span>
                                <span class="alttextai-footer-cta-badge"><?php esc_html_e('(included in free plan)', 'opptiai-alt-text-generator'); ?></span>
                            </p>
                        </div>
                        
                        <!-- Powered by OpttiAI -->
                        <div class="alttextai-premium-footer-divider"></div>
                        <div class="alttextai-premium-footer-branding">
                            <span><?php esc_html_e('Powered by', 'opptiai-alt-text-generator'); ?> <strong>OpttiAI</strong></span>
                        </div>
                        
                        <!-- Circular Progress Animation Script -->
                        <script>
                        (function() {
                            function initProgressRings() {
                                var rings = document.querySelectorAll('.alttextai-circular-progress-bar[data-offset]');
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
                        <div class="alttextai-demo-preview">
                            <!-- Demo Badge Overlay -->
                            <div class="alttextai-demo-badge-overlay">
                                <span class="alttextai-demo-badge-text"><?php esc_html_e('DEMO PREVIEW', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            
                            <!-- Usage Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-dashboard-card--featured alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('USAGE STATUS', 'opptiai-alt-text-generator'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('0 of 50 image descriptions generated this month.', 'opptiai-alt-text-generator'); ?>
                                    </h2>
                                    <p style="margin: 12px 0 0 0; font-size: 14px; color: #6b7280;">
                                        <?php esc_html_e('Sign in to track your usage and access premium features.', 'opptiai-alt-text-generator'); ?>
                                    </p>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-bar">
                                    <div class="alttextai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-stats">
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Used', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">50</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Resets', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value"><?php echo esc_html(date_i18n('F j, Y', strtotime('first day of next month'))); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Saved Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-time-saved-card alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('TIME SAVED', 'opptiai-alt-text-generator'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">⏱️</span>
                                        <?php esc_html_e('Ready to optimize your images', 'opptiai-alt-text-generator'); ?>
                                    </h2>
                                    <p class="alttextai-seo-impact" style="margin-top: 8px; font-size: 14px; color: #6b7280;"><?php esc_html_e('Start generating alt text to improve SEO and accessibility', 'opptiai-alt-text-generator'); ?></p>
                                </div>
                            </div>

                            <!-- Image Optimization Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('IMAGE OPTIMIZATION', 'opptiai-alt-text-generator'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('Ready to optimize images', 'opptiai-alt-text-generator'); ?>
                                    </h2>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-bar">
                                    <div class="alttextai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-stats">
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Optimized', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">—</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Total', 'opptiai-alt-text-generator'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">—</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo CTA -->
                            <div class="alttextai-demo-cta">
                                <p class="alttextai-demo-cta-text"><?php esc_html_e('✨ Sign up now to start generating alt text for your images!', 'opptiai-alt-text-generator'); ?></p>
                                <button type="button" class="alttextai-btn-primary alttextai-btn-icon" id="alttextai-demo-signup-btn">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Get Started Free', 'opptiai-alt-text-generator'); ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endif; // End is_authenticated check for usage/stats cards ?>

                    <?php if ($this->api_client->is_authenticated() && ($usage_stats['remaining'] ?? 0) <= 0) : ?>
                        <div class="alttextai-limit-reached">
                            <div class="alttextai-limit-header-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3 class="alttextai-limit-title"><?php esc_html_e('Monthly limit reached — keep the momentum going!', 'opptiai-alt-text-generator'); ?></h3>
                            <p class="alttextai-limit-description">
                                <?php
                                    $reset_label = $usage_stats['reset_date'] ?? '';
                                    printf(
                                        esc_html__('You\'ve used all %1$d free generations this month. Your quota resets on %2$s.', 'opptiai-alt-text-generator'),
                                        $usage_stats['limit'],
                                        esc_html($reset_label)
                                    );
                                ?>
                            </p>

                            <div class="alttextai-countdown" data-countdown="<?php echo esc_attr($usage_stats['seconds_until_reset'] ?? 0); ?>" data-reset-timestamp="<?php echo esc_attr($usage_stats['reset_timestamp'] ?? 0); ?>">
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-days>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('days', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                                <div class="alttextai-countdown-separator">—</div>
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-hours>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('hours', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                                <div class="alttextai-countdown-separator">—</div>
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-minutes>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('mins', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>

                            <div class="alttextai-limit-cta">
                                <button type="button" class="alttextai-limit-upgrade-btn alttextai-limit-upgrade-btn--full" data-action="show-upgrade-modal" data-upgrade-source="upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Testimonial Block -->
                    <div class="alttextai-testimonials-grid">
                        <div class="alttextai-testimonial-block">
                            <div class="alttextai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
                            <blockquote class="alttextai-testimonial-quote">
                                <?php esc_html_e('"Generated 1,200 alt texts for our agency in minutes."', 'opptiai-alt-text-generator'); ?>
                            </blockquote>
                            <div class="alttextai-testimonial-author-wrapper">
                                <div class="alttextai-testimonial-avatar">SW</div>
                                <cite class="alttextai-testimonial-author"><?php esc_html_e('Sarah W.', 'opptiai-alt-text-generator'); ?></cite>
                            </div>
                        </div>
                        <div class="alttextai-testimonial-block">
                            <div class="alttextai-testimonial-stars">⭐️⭐️⭐️⭐️⭐️</div>
                            <blockquote class="alttextai-testimonial-quote">
                                <?php esc_html_e('"We automated 4,800 alt text entries for our WooCommerce store."', 'opptiai-alt-text-generator'); ?>
                            </blockquote>
                            <div class="alttextai-testimonial-author-wrapper">
                                <div class="alttextai-testimonial-avatar">MP</div>
                                <cite class="alttextai-testimonial-author"><?php esc_html_e('Martin P.', 'opptiai-alt-text-generator'); ?></cite>
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
                $current_page = isset($_GET['alt_page']) ? max(1, intval($_GET['alt_page'])) : 1;
                $offset = ($current_page - 1) * $per_page;
                
                // Get all images (with or without alt text) for proper filtering
                global $wpdb;
                
                // Get total count of all images
                $total_images = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'inherit' AND post_mime_type LIKE %s",
                    'attachment', 'image/%'
                ));
                
                // Get images with alt text count
                $with_alt_count = (int) $wpdb->get_var(
                    "SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = 'attachment'
                     AND p.post_mime_type LIKE 'image/%'
                     AND p.post_status = 'inherit'
                     AND pm.meta_key = '_wp_attachment_image_alt'
                     AND TRIM(pm.meta_value) <> ''"
                );
                
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
                <div class="alttextai-dashboard-container">
                    <!-- Header Section -->
                    <div class="alttextai-library-header">
                        <h1 class="alttextai-library-title"><?php esc_html_e('ALT Library', 'opptiai-alt-text-generator'); ?></h1>
                        <p class="alttextai-library-subtitle"><?php esc_html_e('Browse, search, and regenerate AI-generated alt text for images in your media library.', 'opptiai-alt-text-generator'); ?></p>
                        
                        <!-- Optimization Notice -->
                        <?php if ($optimization_percentage >= 100) : ?>
                            <div class="alttextai-library-success-notice">
                                <span class="alttextai-library-success-text">
                                    <?php esc_html_e('100% of your library is fully optimized — great progress!', 'opptiai-alt-text-generator'); ?>
                                </span>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="alttextai-library-success-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Pro or Agency', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="alttextai-library-notice">
                                <?php 
                                printf(
                                    esc_html__('%1$d%% of your library is fully optimized — great progress!', 'opptiai-alt-text-generator'),
                                    $optimization_percentage
                                );
                                ?>
                                <?php if (!$is_pro) : ?>
                                    <button type="button" class="alttextai-library-notice-link" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Pro or Agency', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filters Row -->
                    <div class="alttextai-library-controls">
                        <div class="alttextai-library-search-wrapper">
                            <svg class="alttextai-library-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M11 11L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="text" 
                                   id="alttextai-library-search" 
                                   class="alttextai-library-search-input" 
                                   placeholder="<?php esc_attr_e('Search images or alt text…', 'opptiai-alt-text-generator'); ?>"
                            />
                        </div>
                        <select id="alttextai-status-filter" class="alttextai-library-filter-select">
                            <option value="all"><?php esc_html_e('All', 'opptiai-alt-text-generator'); ?></option>
                            <option value="optimized"><?php esc_html_e('Optimized', 'opptiai-alt-text-generator'); ?></option>
                            <option value="missing"><?php esc_html_e('Missing ALT', 'opptiai-alt-text-generator'); ?></option>
                            <option value="errors"><?php esc_html_e('Errors', 'opptiai-alt-text-generator'); ?></option>
                        </select>
                        <select id="alttextai-time-filter" class="alttextai-library-filter-select">
                            <option value="this-month"><?php esc_html_e('This month', 'opptiai-alt-text-generator'); ?></option>
                            <option value="last-month"><?php esc_html_e('Last month', 'opptiai-alt-text-generator'); ?></option>
                            <option value="all-time"><?php esc_html_e('All time', 'opptiai-alt-text-generator'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Table Card - Full Width -->
                    <div class="alttextai-library-table-card<?php echo $is_agency ? ' alttextai-library-table-card--full-width' : ''; ?>">
                        <div class="alttextai-table-scroll">
                            <table class="alttextai-library-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('IMAGE', 'opptiai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('STATUS', 'opptiai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('DATE', 'opptiai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('ALT TEXT', 'opptiai-alt-text-generator'); ?></th>
                                        <th><?php esc_html_e('ACTIONS', 'opptiai-alt-text-generator'); ?></th>
                                    </tr>
                                </thead>
                        <tbody>
                            <?php if (!empty($optimized_images)) : ?>
                                <?php $row_index = 0; ?>
                                <?php foreach ($optimized_images as $image) : ?>
                                    <?php
                                    $attachment_id = $image->ID;
                                    $old_alt = get_post_meta($attachment_id, '_ai_alt_original', true) ?: '';
                                    $current_alt = $image->alt_text ?? get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                                    $thumb_url = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                                    $attachment_title = get_the_title($attachment_id);
                                    $edit_link = get_edit_post_link($attachment_id, '');
                                    
                                    $clean_current_alt = is_string($current_alt) ? trim($current_alt) : '';
                                    $clean_old_alt = is_string($old_alt) ? trim($old_alt) : '';
                                    $has_alt = !empty($clean_current_alt);
                                    
                                    $status_key = $has_alt ? 'optimized' : 'missing';
                                    $status_label = $has_alt ? __('✅ Optimized', 'opptiai-alt-text-generator') : __('🟠 Missing', 'opptiai-alt-text-generator');
                                    if ($has_alt && $clean_old_alt && strcasecmp($clean_old_alt, $clean_current_alt) !== 0) {
                                        $status_key = 'regenerated';
                                        $status_label = __('🔁 Regenerated', 'opptiai-alt-text-generator');
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
                                    $status_class = 'alttextai-status-badge';
                                    if ($status_key === 'optimized') {
                                        $status_class .= ' alttextai-status-badge--optimized';
                                    } elseif ($status_key === 'missing') {
                                        $status_class .= ' alttextai-status-badge--missing';
                                    } else {
                                        $status_class .= ' alttextai-status-badge--regenerated';
                                    }
                                    ?>
                                    <tr class="alttextai-library-row" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-status="<?php echo esc_attr($status_key); ?>">
                                        <td class="alttextai-library-cell alttextai-library-cell--image">
                                            <?php if ($thumb_url) : ?>
                                                <img src="<?php echo esc_url($thumb_url[0]); ?>" alt="<?php echo esc_attr($attachment_title); ?>" class="alttextai-library-thumbnail" />
                                            <?php else : ?>
                                                <div class="alttextai-library-thumbnail-placeholder">
                                                    <?php esc_html_e('—', 'opptiai-alt-text-generator'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="alttextai-library-cell alttextai-library-cell--status">
                                            <span class="<?php echo esc_attr($status_class); ?>">
                                                <?php if ($status_key === 'optimized') : ?>
                                                    <?php esc_html_e('Optimised', 'opptiai-alt-text-generator'); ?>
                                                <?php elseif ($status_key === 'missing') : ?>
                                                    <?php esc_html_e('Missing', 'opptiai-alt-text-generator'); ?>
                                                <?php else : ?>
                                                    <?php esc_html_e('Regenerated', 'opptiai-alt-text-generator'); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="alttextai-library-cell alttextai-library-cell--date">
                                            <?php echo esc_html($modified_date ?: '—'); ?>
                                        </td>
                                        <td class="alttextai-library-cell alttextai-library-cell--alt-text new-alt-cell-<?php echo esc_attr($attachment_id); ?>">
                                            <?php if ($has_alt) : ?>
                                                <div class="alttextai-library-alt-text" title="<?php echo esc_attr($clean_current_alt); ?>">
                                                    <?php echo esc_html($truncated_alt); ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="alttextai-library-no-alt"><?php esc_html_e('No alt text', 'opptiai-alt-text-generator'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="alttextai-library-cell alttextai-library-cell--actions">
                                            <?php 
                                            $is_local_dev = defined('WP_LOCAL_DEV') && WP_LOCAL_DEV;
                                            $can_regenerate = $is_local_dev || $this->api_client->is_authenticated();
                                            ?>
                                            <button type="button" 
                                                    class="alttextai-btn-regenerate" 
                                                    data-action="regenerate-single" 
                                                    data-attachment-id="<?php echo esc_attr($attachment_id); ?>"
                                                    <?php echo !$can_regenerate ? 'disabled title="' . esc_attr__('Please log in to regenerate alt text', 'opptiai-alt-text-generator') . '"' : ''; ?>>
                                                <?php esc_html_e('Regenerate', 'opptiai-alt-text-generator'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="alttextai-library-empty-state">
                                        <div class="alttextai-library-empty-content">
                                            <div class="alttextai-library-empty-title">
                                                <?php esc_html_e('No images found', 'opptiai-alt-text-generator'); ?>
                                            </div>
                                            <div class="alttextai-library-empty-subtitle">
                                                <?php esc_html_e('Upload images to your Media Library to get started.', 'opptiai-alt-text-generator'); ?>
                                            </div>
                                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="alttextai-library-empty-btn">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                                <?php esc_html_e('Upload Images', 'opptiai-alt-text-generator'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="alttextai-pagination">
                            <div class="alttextai-pagination-info">
                                <?php 
                                $start = $offset + 1;
                                $end = min($offset + $per_page, $total_count);
                                printf(
                                    esc_html__('Showing %1$d-%2$d of %3$d images', 'opptiai-alt-text-generator'),
                                    $start,
                                    $end,
                                    $total_count
                                );
                                ?>
                            </div>
                            
                            <div class="alttextai-pagination-controls">
                                <?php if ($current_page > 1) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--first" title="<?php esc_attr_e('First page', 'opptiai-alt-text-generator'); ?>">
                                        <?php esc_html_e('First', 'opptiai-alt-text-generator'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page - 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--prev" title="<?php esc_attr_e('Previous page', 'opptiai-alt-text-generator'); ?>">
                                        <?php esc_html_e('Previous', 'opptiai-alt-text-generator'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('First', 'opptiai-alt-text-generator'); ?></span>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Previous', 'opptiai-alt-text-generator'); ?></span>
                                <?php endif; ?>
                                
                                <div class="alttextai-pagination-pages">
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', 1)) . '" class="alttextai-pagination-btn">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="alttextai-pagination-ellipsis">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        if ($i == $current_page) {
                                            echo '<span class="alttextai-pagination-btn alttextai-pagination-btn--current">' . $i . '</span>';
                                        } else {
                                            echo '<a href="' . esc_url(add_query_arg('alt_page', $i)) . '" class="alttextai-pagination-btn">' . $i . '</a>';
                                        }
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="alttextai-pagination-ellipsis">...</span>';
                                        }
                                        echo '<a href="' . esc_url(add_query_arg('alt_page', $total_pages)) . '" class="alttextai-pagination-btn">' . $total_pages . '</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($current_page < $total_pages) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page + 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--next" title="<?php esc_attr_e('Next page', 'opptiai-alt-text-generator'); ?>">
                                        <?php esc_html_e('Next', 'opptiai-alt-text-generator'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $total_pages)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--last" title="<?php esc_attr_e('Last page', 'opptiai-alt-text-generator'); ?>">
                                        <?php esc_html_e('Last', 'opptiai-alt-text-generator'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Next', 'opptiai-alt-text-generator'); ?></span>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Last', 'opptiai-alt-text-generator'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                                </div>
                    </div> <!-- End Table Card -->
                    
                    <!-- Upgrade Card - Full Width (hidden only for Agency, visible for Pro) -->
                    <?php if (!$is_agency) : ?>
                    <div class="alttextai-library-upgrade-card">
                        <h3 class="alttextai-library-upgrade-title">
                            <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?>
                        </h3>
                        <div class="alttextai-library-upgrade-features">
                            <div class="alttextai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Bulk ALT optimisation', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <div class="alttextai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited background queue', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <div class="alttextai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Smart tone tuning', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <div class="alttextai-library-upgrade-feature">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority support', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                        </div>
                        <button type="button" 
                                class="alttextai-library-upgrade-btn"
                                data-action="show-upgrade-modal">
                            <?php esc_html_e('View Plans', 'opptiai-alt-text-generator'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    </div> <!-- End Dashboard Container -->
                
                <!-- Status for AJAX operations -->
                <div class="ai-alt-dashboard__status" data-progress-status role="status" aria-live="polite" style="display: none;"></div>
                

            </div>

<?php elseif ($tab === 'debug') : ?>
    <?php if (!$this->api_client->is_authenticated()) : ?>
        <!-- Debug Logs require authentication -->
        <div class="alttextai-settings-required">
            <div class="alttextai-settings-required-content">
                <div class="alttextai-settings-required-icon">🔒</div>
                <h2><?php esc_html_e('Authentication Required', 'opptiai-alt-text-generator'); ?></h2>
                <p><?php esc_html_e('Debug Logs are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'opptiai-alt-text-generator'); ?></p>
                <p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
                    <?php esc_html_e('If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'opptiai-alt-text-generator'); ?>
                </p>
                <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                    </svg>
                    <?php esc_html_e('Log In', 'opptiai-alt-text-generator'); ?>
                </button>
            </div>
        </div>
    <?php elseif (!class_exists('AltText_AI_Debug_Log')) : ?>
        <div class="alttextai-section">
            <div class="notice notice-warning">
                <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'opptiai-alt-text-generator'); ?></p>
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
                admin_url('admin-post.php?action=opptiai_alt_debug_export'),
                'opptiai_alt_debug_export'
            );
            
            // Get plan info for upgrade card
            $plan_slug = isset($usage_stats) && isset($usage_stats['plan']) ? $usage_stats['plan'] : 'free';
            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
        ?>
        <style>
            /* Responsive Debug Stats Grid */
            @media (max-width: 768px) {
                [data-alttextai-debug-panel] .debug-stats-grid {
                    grid-template-columns: repeat(2, 1fr) !important;
                    gap: 16px !important;
                }
            }
            @media (max-width: 480px) {
                [data-alttextai-debug-panel] .debug-stats-grid {
                    grid-template-columns: 1fr !important;
                    gap: 16px !important;
                }
            }
        </style>
        <div class="alttextai-dashboard-container" data-alttextai-debug-panel>
            <!-- Header Section -->
            <div class="alttextai-debug-header">
                <h1 class="alttextai-dashboard-title"><?php esc_html_e('Debug Logs', 'opptiai-alt-text-generator'); ?></h1>
                <p class="alttextai-dashboard-subtitle"><?php esc_html_e('Monitor API calls, queue events, and any errors generated by the plugin.', 'opptiai-alt-text-generator'); ?></p>
                
                <!-- Usage Info -->
                <?php if (isset($usage_stats)) : ?>
                <div class="alttextai-debug-usage-info">
                    <?php 
                    printf(
                        esc_html__('%1$d of %2$d image descriptions generated this month', 'opptiai-alt-text-generator'),
                        $usage_stats['used'] ?? 0,
                        $usage_stats['limit'] ?? 0
                    ); 
                    ?>
                    <span class="alttextai-debug-usage-separator">•</span>
                    <?php 
                    $reset_date = $usage_stats['reset_date'] ?? '';
                    if (!empty($reset_date)) {
                        $reset_timestamp = strtotime($reset_date);
                        if ($reset_timestamp !== false) {
                            $formatted_reset = date_i18n('F j, Y', $reset_timestamp);
                            printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($formatted_reset));
                        } else {
                            printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($reset_date));
                        }
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Log Statistics Card -->
            <div class="alttextai-debug-stats-card">
                <div class="alttextai-debug-stats-header">
                    <div class="alttextai-debug-stats-title">
                        <h3><?php esc_html_e('Log Statistics', 'opptiai-alt-text-generator'); ?></h3>
                    </div>
                    <div class="alttextai-debug-stats-actions">
                        <button type="button" class="alttextai-debug-btn alttextai-debug-btn--secondary" data-debug-clear>
                            <?php esc_html_e('Clear Logs', 'opptiai-alt-text-generator'); ?>
                        </button>
                        <a href="<?php echo esc_url($debug_export_url); ?>" class="alttextai-debug-btn alttextai-debug-btn--primary">
                            <?php esc_html_e('Export CSV', 'opptiai-alt-text-generator'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="alttextai-debug-stats-grid">
                    <div class="alttextai-debug-stat-item">
                        <div class="alttextai-debug-stat-label"><?php esc_html_e('TOTAL LOGS', 'opptiai-alt-text-generator'); ?></div>
                        <div class="alttextai-debug-stat-value" data-debug-stat="total">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['total'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="alttextai-debug-stat-item alttextai-debug-stat-item--warning">
                        <div class="alttextai-debug-stat-label"><?php esc_html_e('WARNINGS', 'opptiai-alt-text-generator'); ?></div>
                        <div class="alttextai-debug-stat-value alttextai-debug-stat-value--warning" data-debug-stat="warnings">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['warnings'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="alttextai-debug-stat-item alttextai-debug-stat-item--error">
                        <div class="alttextai-debug-stat-label"><?php esc_html_e('ERRORS', 'opptiai-alt-text-generator'); ?></div>
                        <div class="alttextai-debug-stat-value alttextai-debug-stat-value--error" data-debug-stat="errors">
                            <?php echo esc_html(number_format_i18n(intval($debug_stats['errors'] ?? 0))); ?>
                        </div>
                    </div>
                    <div class="alttextai-debug-stat-item">
                        <div class="alttextai-debug-stat-label"><?php esc_html_e('LAST API EVENT', 'opptiai-alt-text-generator'); ?></div>
                        <div class="alttextai-debug-stat-value alttextai-debug-stat-value--small" data-debug-stat="last_api">
                            <?php echo esc_html($debug_stats['last_api'] ?? '—'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Panel -->
            <div class="alttextai-debug-filters-card">
                <form data-debug-filter class="alttextai-debug-filters-form">
                    <div class="alttextai-debug-filter-group">
                        <label for="alttextai-debug-level" class="alttextai-debug-filter-label">
                            <?php esc_html_e('Level', 'opptiai-alt-text-generator'); ?>
                        </label>
                        <select id="alttextai-debug-level" name="level" class="alttextai-debug-filter-input">
                            <option value=""><?php esc_html_e('All levels', 'opptiai-alt-text-generator'); ?></option>
                            <option value="info"><?php esc_html_e('Info', 'opptiai-alt-text-generator'); ?></option>
                            <option value="warning"><?php esc_html_e('Warning', 'opptiai-alt-text-generator'); ?></option>
                            <option value="error"><?php esc_html_e('Error', 'opptiai-alt-text-generator'); ?></option>
                        </select>
                    </div>
                    <div class="alttextai-debug-filter-group">
                        <label for="alttextai-debug-date" class="alttextai-debug-filter-label">
                            <?php esc_html_e('Date', 'opptiai-alt-text-generator'); ?>
                        </label>
                        <input type="date" id="alttextai-debug-date" name="date" class="alttextai-debug-filter-input" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="alttextai-debug-filter-group alttextai-debug-filter-group--search">
                        <label for="alttextai-debug-search" class="alttextai-debug-filter-label">
                            <?php esc_html_e('Search', 'opptiai-alt-text-generator'); ?>
                        </label>
                        <div class="alttextai-debug-search-wrapper">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="alttextai-debug-search-icon">
                                <circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <input type="search" id="alttextai-debug-search" name="search" placeholder="<?php esc_attr_e('Search logs…', 'opptiai-alt-text-generator'); ?>" class="alttextai-debug-filter-input alttextai-debug-filter-input--search">
                        </div>
                    </div>
                    <div class="alttextai-debug-filter-actions">
                        <button type="submit" class="alttextai-debug-btn alttextai-debug-btn--primary">
                            <?php esc_html_e('Apply', 'opptiai-alt-text-generator'); ?>
                        </button>
                        <button type="button" class="alttextai-debug-btn alttextai-debug-btn--ghost" data-debug-reset>
                            <?php esc_html_e('Reset', 'opptiai-alt-text-generator'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table Card -->
            <div class="alttextai-debug-table-card">
                <table class="alttextai-debug-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('TIMESTAMP', 'opptiai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('LEVEL', 'opptiai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('MESSAGE', 'opptiai-alt-text-generator'); ?></th>
                            <th><?php esc_html_e('CONTEXT', 'opptiai-alt-text-generator'); ?></th>
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
                                $badge_class = 'alttextai-debug-badge alttextai-debug-badge--' . esc_attr($level_slug);
                            ?>
                            <tr class="alttextai-debug-table-row" data-row-index="<?php echo esc_attr($row_index); ?>">
                                <td class="alttextai-debug-table-cell alttextai-debug-table-cell--timestamp">
                                    <?php echo esc_html($formatted_date); ?>
                                </td>
                                <td class="alttextai-debug-table-cell alttextai-debug-table-cell--level">
                                    <span class="<?php echo esc_attr($badge_class); ?>">
                                        <span class="alttextai-debug-badge-text"><?php echo esc_html(ucfirst($level_slug)); ?></span>
                                    </span>
                                </td>
                                <td class="alttextai-debug-table-cell alttextai-debug-table-cell--message">
                                    <?php echo esc_html($log['message'] ?? ''); ?>
                                </td>
                                <td class="alttextai-debug-table-cell alttextai-debug-table-cell--context">
                                    <?php if ($context_attr) : ?>
                                        <button type="button" 
                                                class="alttextai-debug-context-btn" 
                                                data-context-data="<?php echo esc_attr($context_attr); ?>"
                                                data-row-index="<?php echo esc_attr($row_index); ?>">
                                            <?php esc_html_e('Log Context', 'opptiai-alt-text-generator'); ?>
                                        </button>
                                    <?php else : ?>
                                        <span class="alttextai-debug-context-empty">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($context_attr) : ?>
                            <tr class="alttextai-debug-context-row" data-row-index="<?php echo esc_attr($row_index); ?>" style="display: none;">
                                <td colspan="4" class="alttextai-debug-context-cell">
                                    <div class="alttextai-debug-context-content">
                                        <pre class="alttextai-debug-context-json"></pre>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="4" class="alttextai-debug-table-empty">
                                    <?php esc_html_e('No logs recorded yet.', 'opptiai-alt-text-generator'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($debug_pages > 1) : ?>
            <div class="alttextai-debug-pagination">
                <button type="button" class="alttextai-debug-pagination-btn" data-debug-page="prev" <?php disabled($debug_page <= 1); ?>>
                    <?php esc_html_e('Previous', 'opptiai-alt-text-generator'); ?>
                </button>
                <span class="alttextai-debug-pagination-info" data-debug-page-indicator>
                    <?php
                        printf(
                            esc_html__('Page %1$s of %2$s', 'opptiai-alt-text-generator'),
                            esc_html(number_format_i18n($debug_page)),
                            esc_html(number_format_i18n($debug_pages))
                        );
                    ?>
                </span>
                <button type="button" class="alttextai-debug-pagination-btn" data-debug-page="next" <?php disabled($debug_page >= $debug_pages); ?>>
                    <?php esc_html_e('Next', 'opptiai-alt-text-generator'); ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Pro Upsell Section -->
            <?php if (!$is_pro) : ?>
            <div class="alttextai-debug-upsell-card">
                <h3 class="alttextai-debug-upsell-title"><?php esc_html_e('Unlock Pro Debug Console', 'opptiai-alt-text-generator'); ?></h3>
                <ul class="alttextai-debug-upsell-features">
                    <li class="alttextai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Long-term log retention', 'opptiai-alt-text-generator'); ?></span>
                    </li>
                    <li class="alttextai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Priority support', 'opptiai-alt-text-generator'); ?></span>
                    </li>
                    <li class="alttextai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('High-speed global search', 'opptiai-alt-text-generator'); ?></span>
                    </li>
                    <li class="alttextai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('Full CSV export of all logs', 'opptiai-alt-text-generator'); ?></span>
                    </li>
                    <li class="alttextai-debug-upsell-feature">
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                            <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php esc_html_e('API performance insights', 'opptiai-alt-text-generator'); ?></span>
                    </li>
                </ul>
                <button type="button" class="alttextai-debug-upsell-btn" data-action="show-upgrade-modal">
                    <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?> →
                </button>
            </div>
            <?php endif; ?>

            <div class="alttextai-debug-toast" data-debug-toast hidden></div>
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
                    if (nextSibling.classList.contains('alttextai-debug-context-row') && 
                        nextSibling.getAttribute('data-row-index') === rowIndex) {
                        contextRow = nextSibling;
                        break;
                    }
                    if (!nextSibling.classList.contains('alttextai-debug-context-row')) {
                        break;
                    }
                    nextSibling = nextSibling.nextElementSibling;
                }
                
                // If context row doesn't exist, create it
                if (!contextRow) {
                    contextRow = document.createElement('tr');
                    contextRow.className = 'alttextai-debug-context-row';
                    contextRow.setAttribute('data-row-index', rowIndex);
                    contextRow.style.display = 'none';
                    
                    var cell = document.createElement('td');
                    cell.className = 'alttextai-debug-context-cell';
                    cell.colSpan = 4;
                    
                    var content = document.createElement('div');
                    content.className = 'alttextai-debug-context-content';
                    
                    var pre = document.createElement('pre');
                    pre.className = 'alttextai-debug-context-json';
                    
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
                
                var preElement = contextRow.querySelector('pre.alttextai-debug-context-json');
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
                    if (btn.classList && btn.classList.contains('alttextai-debug-context-btn')) {
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
            <div class="alttextai-guide-container">
                <!-- Header Section -->
                <div class="alttextai-guide-header">
                    <h1 class="alttextai-guide-title"><?php esc_html_e('How to Use AltText AI', 'opptiai-alt-text-generator'); ?></h1>
                    <p class="alttextai-guide-subtitle"><?php esc_html_e('Learn how to generate and manage alt text for your images.', 'opptiai-alt-text-generator'); ?></p>
                </div>

                <!-- Pro Features Card (LOCKED) -->
                <?php if (!$is_pro) : ?>
                <div class="alttextai-guide-pro-card">
                    <div class="alttextai-guide-pro-ribbon">
                        <?php esc_html_e('LOCKED PRO FEATURES', 'opptiai-alt-text-generator'); ?>
                    </div>
                    <div class="alttextai-guide-pro-features">
                        <div class="alttextai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Priority queue generation', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Bulk optimisation for large libraries', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Multilingual alt text', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Smart tone + style tuning', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                        <div class="alttextai-guide-pro-feature">
                            <svg width="18" height="18" viewBox="0 0 16 16" fill="none">
                                <circle cx="8" cy="8" r="8" fill="#16a34a"/>
                                <path d="M5 8l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Unlimited alt text generation', 'opptiai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <div class="alttextai-guide-pro-cta">
                        <a href="#" class="alttextai-guide-pro-link" data-action="show-upgrade-modal">
                            <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?> →
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Getting Started Card -->
                <div class="alttextai-guide-steps-card">
                    <h2 class="alttextai-guide-steps-title">
                        <?php esc_html_e('Getting Started in 4 Easy Steps', 'opptiai-alt-text-generator'); ?>
                    </h2>
                    <div class="alttextai-guide-steps-list">
                        <div class="alttextai-guide-step">
                            <div class="alttextai-guide-step-badge">
                                <span class="alttextai-guide-step-number">1</span>
                            </div>
                            <div class="alttextai-guide-step-content">
                                <h3 class="alttextai-guide-step-title"><?php esc_html_e('Upload Images', 'opptiai-alt-text-generator'); ?></h3>
                                <p class="alttextai-guide-step-description"><?php esc_html_e('Add images to your WordPress Media Library.', 'opptiai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="alttextai-guide-step">
                            <div class="alttextai-guide-step-badge">
                                <span class="alttextai-guide-step-number">2</span>
                            </div>
                            <div class="alttextai-guide-step-content">
                                <h3 class="alttextai-guide-step-title"><?php esc_html_e('Bulk Optimize', 'opptiai-alt-text-generator'); ?></h3>
                                <p class="alttextai-guide-step-description"><?php esc_html_e('Generate alt text for multiple images at once from the Dashboard.', 'opptiai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="alttextai-guide-step">
                            <div class="alttextai-guide-step-badge">
                                <span class="alttextai-guide-step-number">3</span>
                            </div>
                            <div class="alttextai-guide-step-content">
                                <h3 class="alttextai-guide-step-title"><?php esc_html_e('Review & Edit', 'opptiai-alt-text-generator'); ?></h3>
                                <p class="alttextai-guide-step-description"><?php esc_html_e('Refine generated alt text in the ALT Library.', 'opptiai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                        <div class="alttextai-guide-step">
                            <div class="alttextai-guide-step-badge">
                                <span class="alttextai-guide-step-number">4</span>
                            </div>
                            <div class="alttextai-guide-step-content">
                                <h3 class="alttextai-guide-step-title"><?php esc_html_e('Regenerate if Needed', 'opptiai-alt-text-generator'); ?></h3>
                                <p class="alttextai-guide-step-description"><?php esc_html_e('Use the regenerate feature to improve alt text quality anytime.', 'opptiai-alt-text-generator'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Why Alt Text Matters Section -->
                <div class="alttextai-guide-why-card">
                    <div class="alttextai-guide-why-icon">💡</div>
                    <h3 class="alttextai-guide-why-title">
                        <?php esc_html_e('Why Alt Text Matters', 'opptiai-alt-text-generator'); ?>
                    </h3>
                    <ul class="alttextai-guide-why-list">
                        <li class="alttextai-guide-why-item">
                            <span class="alttextai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Boosts SEO visibility by up to 20%', 'opptiai-alt-text-generator'); ?></span>
                        </li>
                        <li class="alttextai-guide-why-item">
                            <span class="alttextai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Improves Google Images ranking', 'opptiai-alt-text-generator'); ?></span>
                        </li>
                        <li class="alttextai-guide-why-item">
                            <span class="alttextai-guide-why-check">✓</span>
                            <span><?php esc_html_e('Helps achieve WCAG compliance for accessibility', 'opptiai-alt-text-generator'); ?></span>
                        </li>
                    </ul>
                </div>

                <!-- Two Column Layout -->
                <div class="alttextai-guide-grid">
                    <!-- Tips Card -->
                    <div class="alttextai-guide-card">
                        <h3 class="alttextai-guide-card-title">
                            <?php esc_html_e('Tips for Better Alt Text', 'opptiai-alt-text-generator'); ?>
                        </h3>
                        <div class="alttextai-guide-tips-list">
                            <div class="alttextai-guide-tip">
                                <div class="alttextai-guide-tip-icon">✓</div>
                                <div class="alttextai-guide-tip-content">
                                    <div class="alttextai-guide-tip-title"><?php esc_html_e('Keep it concise', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                            <div class="alttextai-guide-tip">
                                <div class="alttextai-guide-tip-icon">✓</div>
                                <div class="alttextai-guide-tip-content">
                                    <div class="alttextai-guide-tip-title"><?php esc_html_e('Be specific', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                            <div class="alttextai-guide-tip">
                                <div class="alttextai-guide-tip-icon">✓</div>
                                <div class="alttextai-guide-tip-content">
                                    <div class="alttextai-guide-tip-title"><?php esc_html_e('Avoid redundancy', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                            <div class="alttextai-guide-tip">
                                <div class="alttextai-guide-tip-icon">✓</div>
                                <div class="alttextai-guide-tip-content">
                                    <div class="alttextai-guide-tip-title"><?php esc_html_e('Think context', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Features Card -->
                    <div class="alttextai-guide-card">
                        <h3 class="alttextai-guide-card-title">
                            <?php esc_html_e('Key Features', 'opptiai-alt-text-generator'); ?>
                        </h3>
                        <div class="alttextai-guide-features-list">
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">🤖</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title"><?php esc_html_e('AI-Powered', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">≡</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title">
                                        <?php esc_html_e('Bulk Processing', 'opptiai-alt-text-generator'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="alttextai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">◆</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title"><?php esc_html_e('SEO Optimized', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">🎨</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title">
                                        <?php esc_html_e('Smart tone tuning', 'opptiai-alt-text-generator'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="alttextai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">🌍</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title">
                                        <?php esc_html_e('Multilingual alt text', 'opptiai-alt-text-generator'); ?>
                                        <?php if (!$is_pro) : ?>
                                            <span class="alttextai-guide-feature-lock">🔒</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="alttextai-guide-feature">
                                <div class="alttextai-guide-feature-icon">♿</div>
                                <div class="alttextai-guide-feature-content">
                                    <div class="alttextai-guide-feature-title"><?php esc_html_e('Accessibility', 'opptiai-alt-text-generator'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upgrade CTA Banner -->
                <?php if (!$is_agency) : ?>
                <div class="alttextai-guide-cta-card">
                    <h3 class="alttextai-guide-cta-title">
                        <span class="alttextai-guide-cta-icon">⚡</span>
                        <?php esc_html_e('Ready for More?', 'opptiai-alt-text-generator'); ?>
                    </h3>
                    <p class="alttextai-guide-cta-text">
                        <?php esc_html_e('Save hours each month with automated alt text generation. Upgrade for 1,000 images/month and priority processing.', 'opptiai-alt-text-generator'); ?>
                    </p>
                    <button type="button" class="alttextai-guide-cta-btn" data-action="show-upgrade-modal">
                        <span><?php esc_html_e('View Plans & Pricing', 'opptiai-alt-text-generator'); ?></span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="alttextai-guide-cta-badge-new"><?php esc_html_e('NEW', 'opptiai-alt-text-generator'); ?></span>
                    </button>
                </div>
                <?php endif; ?>
            </div>


<?php elseif ($tab === 'settings') : ?>
            <?php if (!$this->api_client->is_authenticated()) : ?>
                <!-- Settings require authentication -->
                <div class="alttextai-settings-required">
                    <div class="alttextai-settings-required-content">
                        <div class="alttextai-settings-required-icon">🔒</div>
                        <h2><?php esc_html_e('Authentication Required', 'opptiai-alt-text-generator'); ?></h2>
                        <p><?php esc_html_e('Settings are only available to authenticated agency users. Please log in with your agency credentials to access this section.', 'opptiai-alt-text-generator'); ?></p>
                        <p style="margin-top: 12px; font-size: 14px; color: #6b7280;">
                            <?php esc_html_e('If you don\'t have agency credentials, please contact your agency administrator or log in with the correct account.', 'opptiai-alt-text-generator'); ?>
                        </p>
                        <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-auth-modal" data-auth-tab="login" style="margin-top: 20px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Log In', 'opptiai-alt-text-generator'); ?></span>
                        </button>
                    </div>
                </div>
            <?php else : ?>
            <!-- Settings Page -->
            <div class="alttextai-settings-page">
                <?php
                // Pull fresh usage from backend to avoid stale cache in Settings
                if (isset($this->api_client)) {
                    $live = $this->api_client->get_usage();
                    if (is_array($live) && !empty($live)) { AltText_AI_Usage_Tracker::update_usage($live); }
                }
                $usage_box = AltText_AI_Usage_Tracker::get_stats_display();
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
                    $plan_badge_text = esc_html__('AGENCY', 'opptiai-alt-text-generator');
                } elseif ($is_pro) {
                    $plan_badge_text = esc_html__('PRO', 'opptiai-alt-text-generator');
                } else {
                    $plan_badge_text = esc_html__('FREE', 'opptiai-alt-text-generator');
                }
                ?>

                <!-- Header Section -->
                <div class="alttextai-settings-page-header">
                    <h1 class="alttextai-settings-page-title"><?php esc_html_e('Settings', 'opptiai-alt-text-generator'); ?></h1>
                    <p class="alttextai-settings-page-subtitle"><?php esc_html_e('Configure generation preferences and manage your account.', 'opptiai-alt-text-generator'); ?></p>
                </div>

                <!-- Site-Wide Settings Banner -->
                <div class="alttextai-settings-sitewide-banner">
                    <svg class="alttextai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
                        <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="alttextai-settings-sitewide-content">
                        <strong class="alttextai-settings-sitewide-title"><?php esc_html_e('Site-Wide Settings', 'opptiai-alt-text-generator'); ?></strong>
                        <span class="alttextai-settings-sitewide-text">
                            <?php esc_html_e('These settings apply to all users on this WordPress site.', 'opptiai-alt-text-generator'); ?>
                        </span>
                    </div>
                </div>

                <!-- Plan Summary Card -->
                <div class="alttextai-settings-plan-summary-card">
                    <div class="alttextai-settings-plan-badge-top">
                        <span class="alttextai-settings-plan-badge-text"><?php echo $plan_badge_text; ?></span>
                    </div>
                    <div class="alttextai-settings-plan-quota">
                        <div class="alttextai-settings-plan-quota-meter">
                            <span class="alttextai-settings-plan-quota-used"><?php echo esc_html($usage_box['used']); ?></span>
                            <span class="alttextai-settings-plan-quota-divider">/</span>
                            <span class="alttextai-settings-plan-quota-limit"><?php echo esc_html($usage_box['limit']); ?></span>
                        </div>
                        <div class="alttextai-settings-plan-quota-label">
                            <?php esc_html_e('image descriptions', 'opptiai-alt-text-generator'); ?>
                        </div>
                    </div>
                    <div class="alttextai-settings-plan-info">
                        <?php if (!$is_pro && !$is_agency) : ?>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span>
                                    <?php
                                    if (isset($usage_box['reset_date'])) {
                                        printf(
                                            esc_html__('Resets %s', 'opptiai-alt-text-generator'),
                                            '<strong>' . esc_html($usage_box['reset_date']) . '</strong>'
                                        );
                                    } else {
                                        esc_html_e('Monthly quota', 'opptiai-alt-text-generator');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Shared across all users', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                        <?php elseif ($is_agency) : ?>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Multi-site license', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php echo sprintf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), '<strong>' . esc_html($usage_box['reset_date'] ?? 'Monthly') . '</strong>'); ?></span>
                            </div>
                        <?php else : ?>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited generations', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                            <div class="alttextai-settings-plan-info-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority support', 'opptiai-alt-text-generator'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <button type="button" class="alttextai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
                        <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?>
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

                <div class="alttextai-settings-card">
                    <div class="alttextai-settings-card-header">
                        <div class="alttextai-settings-card-header-icon">
                            <span class="alttextai-settings-card-icon-emoji">🔑</span>
                        </div>
                        <h3 class="alttextai-settings-card-title"><?php esc_html_e('License', 'opptiai-alt-text-generator'); ?></h3>
                    </div>

                    <?php if ($has_license && $license_data) : ?>
                        <?php
                        $org = $license_data['organization'] ?? null;
                        if ($org) :
                        ?>
                        <!-- Active License Display -->
                        <div class="alttextai-settings-license-active">
                            <div class="alttextai-settings-license-status">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                    <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div>
                                    <div class="alttextai-settings-license-title"><?php esc_html_e('License Active', 'opptiai-alt-text-generator'); ?></div>
                                    <div class="alttextai-settings-license-subtitle"><?php echo esc_html($org['name'] ?? ''); ?></div>
                                    <?php 
                                    // Display license key for Pro and Agency users
                                    $license_key = $this->api_client->get_license_key();
                                    if (!empty($license_key)) :
                                        $license_plan = strtolower($org['plan'] ?? 'free');
                                        if ($license_plan === 'pro' || $license_plan === 'agency') :
                                    ?>
                                    <div class="alttextai-settings-license-key" style="margin-top: 8px; font-size: 12px; color: #6b7280; font-family: monospace; word-break: break-all;">
                                        <strong><?php esc_html_e('License Key:', 'opptiai-alt-text-generator'); ?></strong> <?php echo esc_html($license_key); ?>
                                    </div>
                                    <?php 
                                        endif;
                                    endif; 
                                    ?>
                                </div>
                            </div>
                            <button type="button" class="alttextai-settings-license-deactivate-btn" data-action="deactivate-license">
                                <?php esc_html_e('Deactivate', 'opptiai-alt-text-generator'); ?>
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
                        <div class="alttextai-settings-license-sites" id="alttextai-license-sites">
                            <div class="alttextai-settings-license-sites-header">
                                <h3 class="alttextai-settings-license-sites-title">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                        <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                    </svg>
                                    <?php esc_html_e('Sites Using This License', 'opptiai-alt-text-generator'); ?>
                                </h3>
                            </div>
                            <div class="alttextai-settings-license-sites-content" id="alttextai-license-sites-content">
                                <div class="alttextai-settings-license-sites-loading">
                                    <span class="alttextai-spinner"></span>
                                    <?php esc_html_e('Loading site usage...', 'opptiai-alt-text-generator'); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php endif; ?>
                    <?php else : ?>
                        <!-- License Activation Form -->
                        <div class="alttextai-settings-license-form">
                            <p class="alttextai-settings-license-description">
                                <?php esc_html_e('Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'opptiai-alt-text-generator'); ?>
                            </p>
                            <form id="license-activation-form">
                                <div class="alttextai-settings-license-input-group">
                                    <label for="license-key-input" class="alttextai-settings-license-label">
                                        <?php esc_html_e('License Key', 'opptiai-alt-text-generator'); ?>
                                    </label>
                                    <input type="text"
                                           id="license-key-input"
                                           name="license_key"
                                           class="alttextai-settings-license-input"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                           pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
                                           required>
                                </div>
                                <div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                                <button type="submit" id="activate-license-btn" class="alttextai-settings-license-activate-btn">
                                    <?php esc_html_e('Activate License', 'opptiai-alt-text-generator'); ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Hidden nonce for AJAX requests -->
                <input type="hidden" id="license-nonce" value="<?php echo esc_attr(wp_create_nonce('opptiai_alt_license_action')); ?>">

                <!-- Account Management Card -->
                <div class="alttextai-settings-card">
                    <div class="alttextai-settings-card-header">
                        <div class="alttextai-settings-card-header-icon">
                            <span class="alttextai-settings-card-icon-emoji">👤</span>
                        </div>
                        <h3 class="alttextai-settings-card-title"><?php esc_html_e('Account Management', 'opptiai-alt-text-generator'); ?></h3>
                    </div>
                    
                    <?php if (!$is_pro && !$is_agency) : ?>
                    <div class="alttextai-settings-account-info-banner">
                        <span><?php esc_html_e('You are on the free plan.', 'opptiai-alt-text-generator'); ?></span>
                    </div>
                    <div class="alttextai-settings-account-upgrade-link">
                        <button type="button" class="alttextai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
                            <?php esc_html_e('Upgrade Now', 'opptiai-alt-text-generator'); ?>
                        </button>
                    </div>
                    <?php else : ?>
                    <div class="alttextai-settings-account-status">
                        <span class="alttextai-settings-account-status-label"><?php esc_html_e('Current Plan:', 'opptiai-alt-text-generator'); ?></span>
                        <span class="alttextai-settings-account-status-value"><?php 
                            if ($is_agency) {
                                esc_html_e('Agency', 'opptiai-alt-text-generator');
                            } else {
                                esc_html_e('Pro', 'opptiai-alt-text-generator');
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
                    <div class="alttextai-settings-account-actions">
                        <div class="alttextai-settings-account-action-info">
                            <p><strong><?php esc_html_e('License-Based Plan', 'opptiai-alt-text-generator'); ?></strong></p>
                            <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'opptiai-alt-text-generator'); ?></p>
                            <ul>
                                <li><?php esc_html_e('Contact your license administrator', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Email support for billing inquiries', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('View license details in the License section above', 'opptiai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php elseif ($is_authenticated_for_account) : 
                        // Authenticated user - show Stripe portal
                    ?>
                    <div class="alttextai-settings-account-actions">
                        <button type="button" class="alttextai-settings-account-action-btn" data-action="manage-subscription">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Manage Subscription', 'opptiai-alt-text-generator'); ?></span>
                        </button>
                        <div class="alttextai-settings-account-action-info">
                            <p><?php esc_html_e('In Stripe Customer Portal you can:', 'opptiai-alt-text-generator'); ?></p>
                            <ul>
                                <li><?php esc_html_e('View and download invoices', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Update payment method', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('View payment history', 'opptiai-alt-text-generator'); ?></li>
                                <li><?php esc_html_e('Cancel or modify subscription', 'opptiai-alt-text-generator'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Settings Form -->
                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields('opptiai_alt_group'); ?>

                    <!-- Generation Settings Card -->
                    <div class="alttextai-settings-card">
                        <h3 class="alttextai-settings-generation-title"><?php esc_html_e('Generation Settings', 'opptiai-alt-text-generator'); ?></h3>

                        <div class="alttextai-settings-form-group">
                            <div class="alttextai-settings-form-field alttextai-settings-form-field--toggle">
                                <div class="alttextai-settings-form-field-content">
                                    <label for="ai-alt-enable-on-upload" class="alttextai-settings-form-label">
                                        <?php esc_html_e('Auto-generate on Image Upload', 'opptiai-alt-text-generator'); ?>
                                    </label>
                                    <p class="alttextai-settings-form-description">
                                        <?php esc_html_e('Automatically generate alt text when new images are uploaded to your media library.', 'opptiai-alt-text-generator'); ?>
                                    </p>
                                </div>
                                <label class="alttextai-settings-toggle">
                                    <input 
                                        type="checkbox" 
                                        id="ai-alt-enable-on-upload"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                        value="1"
                                        <?php checked(!empty($o['enable_on_upload'] ?? true)); ?>
                                    >
                                    <span class="alttextai-settings-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="alttextai-settings-form-group">
                            <label for="ai-alt-tone" class="alttextai-settings-form-label">
                                <?php esc_html_e('Tone & Style', 'opptiai-alt-text-generator'); ?>
                            </label>
                            <input
                                type="text"
                                id="ai-alt-tone"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                placeholder="<?php esc_attr_e('professional, accessible', 'opptiai-alt-text-generator'); ?>"
                                class="alttextai-settings-form-input"
                            />
                        </div>

                        <div class="alttextai-settings-form-group">
                            <label for="ai-alt-custom-prompt" class="alttextai-settings-form-label">
                                <?php esc_html_e('Additional Instructions', 'opptiai-alt-text-generator'); ?>
                            </label>
                            <textarea
                                id="ai-alt-custom-prompt"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                rows="4"
                                placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'opptiai-alt-text-generator'); ?>"
                                class="alttextai-settings-form-textarea"
                            ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                        </div>

                        <div class="alttextai-settings-form-actions">
                            <button type="submit" class="alttextai-settings-save-btn">
                                <?php esc_html_e('Save Settings', 'opptiai-alt-text-generator'); ?>
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
                    <div class="alttextai-settings-pro-upsell-banner">
                    <div class="alttextai-settings-pro-upsell-content">
                        <h3 class="alttextai-settings-pro-upsell-title">
                            <?php esc_html_e('Want unlimited AI alt text and faster processing?', 'opptiai-alt-text-generator'); ?>
                        </h3>
                        <ul class="alttextai-settings-pro-upsell-features">
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Unlimited monthly AI generations', 'opptiai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Priority queue', 'opptiai-alt-text-generator'); ?></span>
                            </li>
                            <li>
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M13 4L6 11L3 8" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Large library batch mode', 'opptiai-alt-text-generator'); ?></span>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="alttextai-settings-pro-upsell-btn" data-action="show-upgrade-modal">
                        <?php esc_html_e('View Plans & Pricing', 'opptiai-alt-text-generator'); ?> →
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
                <div class="alttextai-admin-login">
                    <div class="alttextai-admin-login-content">
                        <div class="alttextai-admin-login-header">
                            <h2 class="alttextai-admin-login-title">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="margin-right: 12px; vertical-align: middle;">
                                    <path d="M12 1L23 12L12 23L1 12L12 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="12" cy="12" r="3" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Access', 'opptiai-alt-text-generator'); ?>
                            </h2>
                            <p class="alttextai-admin-login-subtitle">
                                <?php 
                                if ($is_agency_for_admin) {
                                    esc_html_e('Enter your agency credentials to access Debug Logs and Settings.', 'opptiai-alt-text-generator');
                                } else {
                                    esc_html_e('Enter your pro credentials to access Debug Logs and Settings.', 'opptiai-alt-text-generator');
                                }
                                ?>
                            </p>
                        </div>
                        
                        <form id="alttextai-admin-login-form" class="alttextai-admin-login-form">
                            <div id="alttextai-admin-login-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                            
                            <div class="alttextai-admin-login-field">
                                <label for="admin-login-email" class="alttextai-admin-login-label">
                                    <?php esc_html_e('Email', 'opptiai-alt-text-generator'); ?>
                                </label>
                                <input type="email" 
                                       id="admin-login-email" 
                                       name="email" 
                                       class="alttextai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('your-email@example.com', 'opptiai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <div class="alttextai-admin-login-field">
                                <label for="admin-login-password" class="alttextai-admin-login-label">
                                    <?php esc_html_e('Password', 'opptiai-alt-text-generator'); ?>
                                </label>
                                <input type="password" 
                                       id="admin-login-password" 
                                       name="password" 
                                       class="alttextai-admin-login-input" 
                                       placeholder="<?php esc_attr_e('Enter your password', 'opptiai-alt-text-generator'); ?>"
                                       required>
                            </div>
                            
                            <button type="submit" id="admin-login-submit-btn" class="alttextai-admin-login-btn">
                                <span class="alttextai-btn__text"><?php esc_html_e('Log In', 'opptiai-alt-text-generator'); ?></span>
                                <span class="alttextai-btn__spinner" style="display: none;">
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
                    
                    $('#alttextai-admin-login-form').on('submit', function(e) {
                        e.preventDefault();
                        
                        const $form = $(this);
                        const $status = $('#alttextai-admin-login-status');
                        const $btn = $('#admin-login-submit-btn');
                        const $btnText = $btn.find('.alttextai-btn__text');
                        const $btnSpinner = $btn.find('.alttextai-btn__spinner');
                        
                        const email = $('#admin-login-email').val().trim();
                        const password = $('#admin-login-password').val();
                        
                        // Show loading
                        $btn.prop('disabled', true);
                        $btnText.hide();
                        $btnSpinner.show();
                        $status.hide();
                        
                        $.ajax({
                            url: window.alttextai_ajax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'alttextai_admin_login',
                                nonce: window.alttextai_ajax.nonce,
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
                <div class="alttextai-admin-content">
                    <!-- Admin Header with Logout -->
                    <div class="alttextai-admin-header">
                        <div class="alttextai-admin-header-info">
                            <h2 class="alttextai-admin-header-title">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" style="margin-right: 10px; vertical-align: middle;">
                                    <path d="M10 1L19 10L10 19L1 10L10 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    <circle cx="10" cy="10" r="2.5" fill="currentColor"/>
                                </svg>
                                <?php esc_html_e('Admin Panel', 'opptiai-alt-text-generator'); ?>
                            </h2>
                            <p class="alttextai-admin-header-subtitle">
                                <?php esc_html_e('Debug Logs and Settings', 'opptiai-alt-text-generator'); ?>
                            </p>
                        </div>
                        <button type="button" class="alttextai-admin-logout-btn" id="alttextai-admin-logout-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 14H3C2.44772 14 2 13.5523 2 13V3C2 2.44772 2.44772 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M10 11L13 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Log Out', 'opptiai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <!-- Admin Tabs Navigation -->
                    <div class="alttextai-admin-tabs">
                        <button type="button" class="alttextai-admin-tab active" data-admin-tab="debug">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <?php esc_html_e('Debug Logs', 'opptiai-alt-text-generator'); ?>
                        </button>
                        <button type="button" class="alttextai-admin-tab" data-admin-tab="settings">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Settings', 'opptiai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <!-- Debug Logs Section -->
                    <div class="alttextai-admin-section alttextai-admin-tab-content" data-admin-tab-content="debug">
                        <div class="alttextai-admin-section-header">
                            <h3 class="alttextai-admin-section-title"><?php esc_html_e('Debug Logs', 'opptiai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        // Reuse debug logs content
                        if (!class_exists('AltText_AI_Debug_Log')) : ?>
                            <div class="alttextai-section">
                                <div class="notice notice-warning">
                                    <p><?php esc_html_e('Debug logging is not available on this site. Please reinstall the logging module.', 'opptiai-alt-text-generator'); ?></p>
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
                                admin_url('admin-post.php?action=opptiai_alt_debug_export'),
                                'opptiai_alt_debug_export'
                            );
                        ?>
                            <div class="alttextai-dashboard-container" data-alttextai-debug-panel>
                                <!-- Debug Logs content (same as debug tab) - inline all content here -->
                                <!-- Header Section -->
                                <div class="alttextai-debug-header">
                                    <h1 class="alttextai-dashboard-title"><?php esc_html_e('Debug Logs', 'opptiai-alt-text-generator'); ?></h1>
                                    <p class="alttextai-dashboard-subtitle"><?php esc_html_e('Monitor API calls, queue events, and any errors generated by the plugin.', 'opptiai-alt-text-generator'); ?></p>
                                    
                                    <!-- Usage Info -->
                                    <?php if (isset($usage_stats)) : ?>
                                    <div class="alttextai-debug-usage-info">
                                        <?php 
                                        printf(
                                            esc_html__('%1$d of %2$d image descriptions generated this month', 'opptiai-alt-text-generator'),
                                            $usage_stats['used'] ?? 0,
                                            $usage_stats['limit'] ?? 0
                                        ); 
                                        ?>
                                        <span class="alttextai-debug-usage-separator">•</span>
                                        <?php 
                                        $reset_date = $usage_stats['reset_date'] ?? '';
                                        if (!empty($reset_date)) {
                                            $reset_timestamp = strtotime($reset_date);
                                            if ($reset_timestamp !== false) {
                                                $formatted_reset = date_i18n('F j, Y', $reset_timestamp);
                                                printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($formatted_reset));
                                            } else {
                                                printf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), esc_html($reset_date));
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Log Statistics Card -->
                                <div class="alttextai-debug-stats-card">
                                    <div class="alttextai-debug-stats-header">
                                        <div class="alttextai-debug-stats-title">
                                            <h3><?php esc_html_e('Log Statistics', 'opptiai-alt-text-generator'); ?></h3>
                                        </div>
                                        <div class="alttextai-debug-stats-actions">
                                            <button type="button" class="alttextai-debug-btn alttextai-debug-btn--secondary" data-debug-clear>
                                                <?php esc_html_e('Clear Logs', 'opptiai-alt-text-generator'); ?>
                                            </button>
                                            <a href="<?php echo esc_url($debug_export_url); ?>" class="alttextai-debug-btn alttextai-debug-btn--primary">
                                                <?php esc_html_e('Export CSV', 'opptiai-alt-text-generator'); ?>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Stats Grid -->
                                    <div class="alttextai-debug-stats-grid">
                                        <div class="alttextai-debug-stat-item">
                                            <div class="alttextai-debug-stat-label"><?php esc_html_e('TOTAL LOGS', 'opptiai-alt-text-generator'); ?></div>
                                            <div class="alttextai-debug-stat-value" data-debug-stat="total">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['total'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="alttextai-debug-stat-item alttextai-debug-stat-item--warning">
                                            <div class="alttextai-debug-stat-label"><?php esc_html_e('WARNINGS', 'opptiai-alt-text-generator'); ?></div>
                                            <div class="alttextai-debug-stat-value alttextai-debug-stat-value--warning" data-debug-stat="warnings">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['warnings'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="alttextai-debug-stat-item alttextai-debug-stat-item--error">
                                            <div class="alttextai-debug-stat-label"><?php esc_html_e('ERRORS', 'opptiai-alt-text-generator'); ?></div>
                                            <div class="alttextai-debug-stat-value alttextai-debug-stat-value--error" data-debug-stat="errors">
                                                <?php echo esc_html(number_format_i18n(intval($debug_stats['errors'] ?? 0))); ?>
                                            </div>
                                        </div>
                                        <div class="alttextai-debug-stat-item">
                                            <div class="alttextai-debug-stat-label"><?php esc_html_e('LAST API EVENT', 'opptiai-alt-text-generator'); ?></div>
                                            <div class="alttextai-debug-stat-value alttextai-debug-stat-value--small" data-debug-stat="last_api">
                                                <?php echo esc_html($debug_stats['last_api'] ?? '—'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filters Panel -->
                                <div class="alttextai-debug-filters-card">
                                    <form data-debug-filter class="alttextai-debug-filters-form">
                                        <div class="alttextai-debug-filter-group">
                                            <label for="alttextai-debug-level" class="alttextai-debug-filter-label">
                                                <?php esc_html_e('Level', 'opptiai-alt-text-generator'); ?>
                                            </label>
                                            <select id="alttextai-debug-level" name="level" class="alttextai-debug-filter-input">
                                                <option value=""><?php esc_html_e('All levels', 'opptiai-alt-text-generator'); ?></option>
                                                <option value="info"><?php esc_html_e('Info', 'opptiai-alt-text-generator'); ?></option>
                                                <option value="warning"><?php esc_html_e('Warning', 'opptiai-alt-text-generator'); ?></option>
                                                <option value="error"><?php esc_html_e('Error', 'opptiai-alt-text-generator'); ?></option>
                                            </select>
                                        </div>
                                        <div class="alttextai-debug-filter-group">
                                            <label for="alttextai-debug-date" class="alttextai-debug-filter-label">
                                                <?php esc_html_e('Date', 'opptiai-alt-text-generator'); ?>
                                            </label>
                                            <input type="date" id="alttextai-debug-date" name="date" class="alttextai-debug-filter-input" placeholder="dd/mm/yyyy">
                                        </div>
                                        <div class="alttextai-debug-filter-group alttextai-debug-filter-group--search">
                                            <label for="alttextai-debug-search" class="alttextai-debug-filter-label">
                                                <?php esc_html_e('Search', 'opptiai-alt-text-generator'); ?>
                                            </label>
                                            <div class="alttextai-debug-search-wrapper">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="alttextai-debug-search-icon">
                                                    <circle cx="7" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <path d="M10 10L13 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <input type="search" id="alttextai-debug-search" name="search" placeholder="<?php esc_attr_e('Search logs…', 'opptiai-alt-text-generator'); ?>" class="alttextai-debug-filter-input alttextai-debug-filter-input--search">
                                            </div>
                                        </div>
                                        <div class="alttextai-debug-filter-actions">
                                            <button type="submit" class="alttextai-debug-btn alttextai-debug-btn--primary">
                                                <?php esc_html_e('Apply', 'opptiai-alt-text-generator'); ?>
                                            </button>
                                            <button type="button" class="alttextai-debug-btn alttextai-debug-btn--ghost" data-debug-reset>
                                                <?php esc_html_e('Reset', 'opptiai-alt-text-generator'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Table Card -->
                                <div class="alttextai-debug-table-card">
                                    <table class="alttextai-debug-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('TIMESTAMP', 'opptiai-alt-text-generator'); ?></th>
                                                <th><?php esc_html_e('LEVEL', 'opptiai-alt-text-generator'); ?></th>
                                                <th><?php esc_html_e('MESSAGE', 'opptiai-alt-text-generator'); ?></th>
                                                <th><?php esc_html_e('CONTEXT', 'opptiai-alt-text-generator'); ?></th>
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
                                                    $badge_class = 'alttextai-debug-badge alttextai-debug-badge--' . esc_attr($level_slug);
                                                ?>
                                                <tr class="alttextai-debug-table-row" data-row-index="<?php echo esc_attr($row_index); ?>">
                                                    <td class="alttextai-debug-table-cell alttextai-debug-table-cell--timestamp">
                                                        <?php echo esc_html($formatted_date); ?>
                                                    </td>
                                                    <td class="alttextai-debug-table-cell alttextai-debug-table-cell--level">
                                                        <span class="<?php echo esc_attr($badge_class); ?>">
                                                            <span class="alttextai-debug-badge-text"><?php echo esc_html(ucfirst($level_slug)); ?></span>
                                                        </span>
                                                    </td>
                                                    <td class="alttextai-debug-table-cell alttextai-debug-table-cell--message">
                                                        <?php echo esc_html($log['message'] ?? ''); ?>
                                                    </td>
                                                    <td class="alttextai-debug-table-cell alttextai-debug-table-cell--context">
                                                        <?php if ($context_attr) : ?>
                                                            <button type="button" 
                                                                    class="alttextai-debug-context-btn" 
                                                                    data-context-data="<?php echo esc_attr($context_attr); ?>"
                                                                    data-row-index="<?php echo esc_attr($row_index); ?>">
                                                                <?php esc_html_e('Log Context', 'opptiai-alt-text-generator'); ?>
                                                            </button>
                                                        <?php else : ?>
                                                            <span class="alttextai-debug-context-empty">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php if ($context_attr) : ?>
                                                <tr class="alttextai-debug-context-row" data-row-index="<?php echo esc_attr($row_index); ?>" style="display: none;">
                                                    <td colspan="4" class="alttextai-debug-context-cell">
                                                        <div class="alttextai-debug-context-content">
                                                            <pre class="alttextai-debug-context-json"></pre>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <tr>
                                                    <td colspan="4" class="alttextai-debug-table-empty">
                                                        <?php esc_html_e('No logs recorded yet.', 'opptiai-alt-text-generator'); ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($debug_pages > 1) : ?>
                                <div class="alttextai-debug-pagination">
                                    <button type="button" class="alttextai-debug-pagination-btn" data-debug-page="prev" <?php disabled($debug_page <= 1); ?>>
                                        <?php esc_html_e('Previous', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                    <span class="alttextai-debug-pagination-info" data-debug-page-indicator>
                                        <?php
                                            printf(
                                                esc_html__('Page %1$s of %2$s', 'opptiai-alt-text-generator'),
                                                esc_html(number_format_i18n($debug_page)),
                                                esc_html(number_format_i18n($debug_pages))
                                            );
                                        ?>
                                    </span>
                                    <button type="button" class="alttextai-debug-pagination-btn" data-debug-page="next" <?php disabled($debug_page >= $debug_pages); ?>>
                                        <?php esc_html_e('Next', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                </div>
                                <?php endif; ?>

                                <div class="alttextai-debug-toast" data-debug-toast hidden></div>
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
                                        if (nextSibling.classList.contains('alttextai-debug-context-row') && 
                                            nextSibling.getAttribute('data-row-index') === rowIndex) {
                                            contextRow = nextSibling;
                                            break;
                                        }
                                        if (!nextSibling.classList.contains('alttextai-debug-context-row')) {
                                            break;
                                        }
                                        nextSibling = nextSibling.nextElementSibling;
                                    }
                                    
                                    // If context row doesn't exist, create it
                                    if (!contextRow) {
                                        contextRow = document.createElement('tr');
                                        contextRow.className = 'alttextai-debug-context-row';
                                        contextRow.setAttribute('data-row-index', rowIndex);
                                        contextRow.style.display = 'none';
                                        
                                        var cell = document.createElement('td');
                                        cell.className = 'alttextai-debug-context-cell';
                                        cell.colSpan = 4;
                                        
                                        var content = document.createElement('div');
                                        content.className = 'alttextai-debug-context-content';
                                        
                                        var pre = document.createElement('pre');
                                        pre.className = 'alttextai-debug-context-json';
                                        
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
                                    
                                    var preElement = contextRow.querySelector('pre.alttextai-debug-context-json');
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
                                        if (btn.classList && btn.classList.contains('alttextai-debug-context-btn')) {
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
                    <div class="alttextai-admin-section alttextai-admin-tab-content" data-admin-tab-content="settings" style="display: none;">
                        <div class="alttextai-admin-section-header">
                            <h3 class="alttextai-admin-section-title"><?php esc_html_e('Settings', 'opptiai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        // Reuse settings content from settings tab (same as starting from line 2716)
                        // Pull fresh usage from backend to avoid stale cache in Settings
                        if (isset($this->api_client)) {
                            $live = $this->api_client->get_usage();
                            if (is_array($live) && !empty($live)) { AltText_AI_Usage_Tracker::update_usage($live); }
                        }
                        $usage_box = AltText_AI_Usage_Tracker::get_stats_display();
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
                            $plan_badge_text = esc_html__('AGENCY', 'opptiai-alt-text-generator');
                        } elseif ($is_pro) {
                            $plan_badge_text = esc_html__('PRO', 'opptiai-alt-text-generator');
                        } else {
                            $plan_badge_text = esc_html__('FREE', 'opptiai-alt-text-generator');
                        }
                        ?>
                        <!-- Settings Page Content (full content from settings tab) -->
                        <div class="alttextai-settings-page">
                            <!-- Site-Wide Settings Banner -->
                            <div class="alttextai-settings-sitewide-banner">
                                <svg class="alttextai-settings-sitewide-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <circle cx="10" cy="10" r="8" stroke="#3b82f6" stroke-width="2" fill="none"/>
                                    <path d="M10 6V10M10 14H10.01" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <div class="alttextai-settings-sitewide-content">
                                    <strong class="alttextai-settings-sitewide-title"><?php esc_html_e('Site-Wide Settings', 'opptiai-alt-text-generator'); ?></strong>
                                    <span class="alttextai-settings-sitewide-text">
                                        <?php esc_html_e('These settings apply to all users on this WordPress site.', 'opptiai-alt-text-generator'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Plan Summary Card -->
                            <div class="alttextai-settings-plan-summary-card">
                                <div class="alttextai-settings-plan-badge-top">
                                    <span class="alttextai-settings-plan-badge-text"><?php echo $plan_badge_text; ?></span>
                                </div>
                                <div class="alttextai-settings-plan-quota">
                                    <div class="alttextai-settings-plan-quota-meter">
                                        <span class="alttextai-settings-plan-quota-used"><?php echo esc_html($usage_box['used']); ?></span>
                                        <span class="alttextai-settings-plan-quota-divider">/</span>
                                        <span class="alttextai-settings-plan-quota-limit"><?php echo esc_html($usage_box['limit']); ?></span>
                                    </div>
                                    <div class="alttextai-settings-plan-quota-label">
                                        <?php esc_html_e('image descriptions', 'opptiai-alt-text-generator'); ?>
                                    </div>
                                </div>
                                <div class="alttextai-settings-plan-info">
                                    <?php if (!$is_pro && !$is_agency) : ?>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <span>
                                                <?php
                                                if (isset($usage_box['reset_date'])) {
                                                    printf(
                                                        esc_html__('Resets %s', 'opptiai-alt-text-generator'),
                                                        '<strong>' . esc_html($usage_box['reset_date']) . '</strong>'
                                                    );
                                                } else {
                                                    esc_html_e('Monthly quota', 'opptiai-alt-text-generator');
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Shared across all users', 'opptiai-alt-text-generator'); ?></span>
                                        </div>
                                    <?php elseif ($is_agency) : ?>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Multi-site license', 'opptiai-alt-text-generator'); ?></span>
                                        </div>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php echo sprintf(esc_html__('Resets %s', 'opptiai-alt-text-generator'), '<strong>' . esc_html($usage_box['reset_date'] ?? 'Monthly') . '</strong>'); ?></span>
                                        </div>
                                    <?php else : ?>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Unlimited generations', 'opptiai-alt-text-generator'); ?></span>
                                        </div>
                                        <div class="alttextai-settings-plan-info-item">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span><?php esc_html_e('Priority support', 'opptiai-alt-text-generator'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$is_pro && !$is_agency) : ?>
                                <button type="button" class="alttextai-settings-plan-upgrade-btn-large" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'opptiai-alt-text-generator'); ?>
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

                            <div class="alttextai-settings-card">
                                <div class="alttextai-settings-card-header">
                                    <div class="alttextai-settings-card-header-icon">
                                        <span class="alttextai-settings-card-icon-emoji">🔑</span>
                                    </div>
                                    <h3 class="alttextai-settings-card-title"><?php esc_html_e('License', 'opptiai-alt-text-generator'); ?></h3>
                                </div>

                                <?php if ($has_license && $license_data) : ?>
                                    <?php
                                    $org = $license_data['organization'] ?? null;
                                    if ($org) :
                                    ?>
                                    <!-- Active License Display -->
                                    <div class="alttextai-settings-license-active">
                                        <div class="alttextai-settings-license-status">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <circle cx="10" cy="10" r="8" fill="#10b981" opacity="0.1"/>
                                                <path d="M6 10L9 13L14 7" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <div>
                                                <div class="alttextai-settings-license-title"><?php esc_html_e('License Active', 'opptiai-alt-text-generator'); ?></div>
                                                <div class="alttextai-settings-license-subtitle"><?php echo esc_html($org['name'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                        <button type="button" class="alttextai-settings-license-deactivate-btn" data-action="deactivate-license">
                                            <?php esc_html_e('Deactivate', 'opptiai-alt-text-generator'); ?>
                                        </button>
                                    </div>
                                    
                                    <?php 
                                    // Show site usage for agency licenses when authenticated
                                    $is_authenticated_for_license = $this->api_client->is_authenticated();
                                    $is_agency_license = isset($license_data['organization']['plan']) && $license_data['organization']['plan'] === 'agency';
                                    
                                    if ($is_agency_license && $is_authenticated_for_license) :
                                    ?>
                                    <!-- License Site Usage Section -->
                                    <div class="alttextai-settings-license-sites" id="alttextai-license-sites">
                                        <div class="alttextai-settings-license-sites-header">
                                            <h3 class="alttextai-settings-license-sites-title">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                                    <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                                    <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                                </svg>
                                                <?php esc_html_e('Sites Using This License', 'opptiai-alt-text-generator'); ?>
                                            </h3>
                                        </div>
                                        <div class="alttextai-settings-license-sites-content" id="alttextai-license-sites-content">
                                            <div class="alttextai-settings-license-sites-loading">
                                                <span class="alttextai-spinner"></span>
                                                <?php esc_html_e('Loading site usage...', 'opptiai-alt-text-generator'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php elseif ($is_agency_license && !$is_authenticated_for_license) : ?>
                                    <!-- Prompt to login for site usage -->
                                    <div class="alttextai-settings-license-sites-auth">
                                        <p class="alttextai-settings-license-sites-auth-text">
                                            <?php esc_html_e('Log in to view site usage and generation statistics for this license.', 'opptiai-alt-text-generator'); ?>
                                        </p>
                                        <button type="button" class="alttextai-settings-license-sites-auth-btn" data-action="show-auth-modal" data-auth-tab="login">
                                            <?php esc_html_e('Log In', 'opptiai-alt-text-generator'); ?>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php endif; ?>
                                <?php else : ?>
                                    <!-- License Activation Form -->
                                    <div class="alttextai-settings-license-form">
                                        <p class="alttextai-settings-license-description">
                                            <?php esc_html_e('Enter your license key to activate this site. Agency licenses can be used across multiple sites.', 'opptiai-alt-text-generator'); ?>
                                        </p>
                                        <form id="license-activation-form">
                                            <div class="alttextai-settings-license-input-group">
                                                <label for="license-key-input" class="alttextai-settings-license-label">
                                                    <?php esc_html_e('License Key', 'opptiai-alt-text-generator'); ?>
                                                </label>
                                                <input type="text"
                                                       id="license-key-input"
                                                       name="license_key"
                                                       class="alttextai-settings-license-input"
                                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                       pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}"
                                                       required>
                                            </div>
                                            <div id="license-activation-status" style="display: none; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px;"></div>
                                            <button type="submit" id="activate-license-btn" class="alttextai-settings-license-activate-btn">
                                                <?php esc_html_e('Activate License', 'opptiai-alt-text-generator'); ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Account Management Card -->
                            <div class="alttextai-settings-card">
                                <div class="alttextai-settings-card-header">
                                    <div class="alttextai-settings-card-header-icon">
                                        <span class="alttextai-settings-card-icon-emoji">👤</span>
                                    </div>
                                    <h3 class="alttextai-settings-card-title"><?php esc_html_e('Account Management', 'opptiai-alt-text-generator'); ?></h3>
                                </div>
                                
                                <?php if (!$is_pro && !$is_agency) : ?>
                                <div class="alttextai-settings-account-info-banner">
                                    <span><?php esc_html_e('You are on the free plan.', 'opptiai-alt-text-generator'); ?></span>
                                </div>
                                <div class="alttextai-settings-account-upgrade-link">
                                    <button type="button" class="alttextai-settings-account-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade Now', 'opptiai-alt-text-generator'); ?>
                                    </button>
                                </div>
                                <?php else : ?>
                                <div class="alttextai-settings-account-status">
                                    <span class="alttextai-settings-account-status-label"><?php esc_html_e('Current Plan:', 'opptiai-alt-text-generator'); ?></span>
                                    <span class="alttextai-settings-account-status-value"><?php 
                                        if ($is_agency) {
                                            esc_html_e('Agency', 'opptiai-alt-text-generator');
                                        } else {
                                            esc_html_e('Pro', 'opptiai-alt-text-generator');
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
                                <div class="alttextai-settings-account-actions">
                                    <div class="alttextai-settings-account-action-info">
                                        <p><strong><?php esc_html_e('License-Based Plan', 'opptiai-alt-text-generator'); ?></strong></p>
                                        <p><?php esc_html_e('Your subscription is managed through your license. To manage billing, invoices, or update your subscription:', 'opptiai-alt-text-generator'); ?></p>
                                        <ul>
                                            <li><?php esc_html_e('Contact your license administrator', 'opptiai-alt-text-generator'); ?></li>
                                            <li><?php esc_html_e('Email support for billing inquiries', 'opptiai-alt-text-generator'); ?></li>
                                            <li><?php esc_html_e('View license details in the License section above', 'opptiai-alt-text-generator'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                <?php elseif ($is_authenticated_for_account) : 
                                    // Authenticated user - show Stripe portal
                                ?>
                                <div class="alttextai-settings-account-actions">
                                    <button type="button" class="alttextai-settings-account-action-btn" data-action="manage-subscription">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                            <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                                        </svg>
                                        <span><?php esc_html_e('Manage Subscription', 'opptiai-alt-text-generator'); ?></span>
                                    </button>
                                    <div class="alttextai-settings-account-action-info">
                                        <p><?php esc_html_e('In Stripe Customer Portal you can:', 'opptiai-alt-text-generator'); ?></p>
                                        <ul>
                                            <li><?php esc_html_e('View and download invoices', 'opptiai-alt-text-generator'); ?></li>
                                            <li><?php esc_html_e('Update payment method', 'opptiai-alt-text-generator'); ?></li>
                                            <li><?php esc_html_e('View payment history', 'opptiai-alt-text-generator'); ?></li>
                                            <li><?php esc_html_e('Cancel or modify subscription', 'opptiai-alt-text-generator'); ?></li>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Settings Form -->
                            <form method="post" action="options.php" autocomplete="off">
                                <?php settings_fields('opptiai_alt_group'); ?>

                                <!-- Generation Settings Card -->
                                <div class="alttextai-settings-card">
                                    <h3 class="alttextai-settings-generation-title"><?php esc_html_e('Generation Settings', 'opptiai-alt-text-generator'); ?></h3>

                                    <div class="alttextai-settings-form-group">
                                        <div class="alttextai-settings-form-field alttextai-settings-form-field--toggle">
                                            <div class="alttextai-settings-form-field-content">
                                                <label for="ai-alt-enable-on-upload" class="alttextai-settings-form-label">
                                                    <?php esc_html_e('Auto-generate on Image Upload', 'opptiai-alt-text-generator'); ?>
                                                </label>
                                                <p class="alttextai-settings-form-description">
                                                    <?php esc_html_e('Automatically generate alt text when new images are uploaded to your media library.', 'opptiai-alt-text-generator'); ?>
                                                </p>
                                            </div>
                                            <label class="alttextai-settings-toggle">
                                                <input 
                                                    type="checkbox" 
                                                    id="ai-alt-enable-on-upload"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" 
                                                    value="1"
                                                    <?php checked(!empty($o['enable_on_upload'] ?? true)); ?>
                                                >
                                                <span class="alttextai-settings-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="alttextai-settings-form-group">
                                        <label for="ai-alt-tone" class="alttextai-settings-form-label">
                                            <?php esc_html_e('Tone & Style', 'opptiai-alt-text-generator'); ?>
                                        </label>
                                        <input
                                            type="text"
                                            id="ai-alt-tone"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                            value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                            placeholder="<?php esc_attr_e('professional, accessible', 'opptiai-alt-text-generator'); ?>"
                                            class="alttextai-settings-form-input"
                                        />
                                    </div>

                                    <div class="alttextai-settings-form-group">
                                        <label for="ai-alt-custom-prompt" class="alttextai-settings-form-label">
                                            <?php esc_html_e('Additional Instructions', 'opptiai-alt-text-generator'); ?>
                                        </label>
                                        <textarea
                                            id="ai-alt-custom-prompt"
                                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                            rows="4"
                                            placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'opptiai-alt-text-generator'); ?>"
                                            class="alttextai-settings-form-textarea"
                                        ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="alttextai-settings-form-actions">
                                        <button type="submit" class="alttextai-settings-save-btn">
                                            <?php esc_html_e('Save Settings', 'opptiai-alt-text-generator'); ?>
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Hidden nonce for AJAX requests -->
                            <input type="hidden" id="license-nonce" value="<?php echo esc_attr(wp_create_nonce('opptiai_alt_license_action')); ?>">
                        </div>
                    </div>
                </div>
                
                <script>
                (function($) {
                    'use strict';
                    
                    // Admin tab switching
                    $('.alttextai-admin-tab').on('click', function(e) {
                        e.preventDefault();
                        
                        const $tab = $(this);
                        const tabName = $tab.data('admin-tab');
                        
                        // Update active tab
                        $('.alttextai-admin-tab').removeClass('active');
                        $tab.addClass('active');
                        
                        // Show/hide content
                        $('.alttextai-admin-tab-content').hide();
                        $('.alttextai-admin-tab-content[data-admin-tab-content="' + tabName + '"]').show();
                        
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
                        $('.alttextai-admin-tab[data-admin-tab="' + hash + '"]').trigger('click');
                    }
                    
                    // Logout handler
                    $('#alttextai-admin-logout-btn').on('click', function(e) {
                        e.preventDefault();
                        
                        if (!confirm('<?php echo esc_js(__('Are you sure you want to log out of the admin panel?', 'opptiai-alt-text-generator')); ?>')) {
                            return;
                        }
                        
                        const $btn = $(this);
                        $btn.prop('disabled', true);
                        
                        $.ajax({
                            url: window.alttextai_ajax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'alttextai_admin_logout',
                                nonce: window.alttextai_ajax.nonce
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
            </div><!-- .alttextai-container -->
            
            <!-- Footer -->
            <div style="text-align: center; padding: 24px 0; margin-top: 48px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 13px;">
                <?php esc_html_e('OpptiAI • WordPress AI Tools', 'opptiai-alt-text-generator'); ?> — <a href="https://oppti.ai" target="_blank" rel="noopener noreferrer" style="color: #14b8a6; text-decoration: none;">https://oppti.ai</a>
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
        include OPPTIAI_ALT_PLUGIN_DIR . 'templates/upgrade-modal.php';
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
        $filename = $file ? wp_basename($file) : get_the_title($attachment_id);
        $title    = get_the_title($attachment_id);
        $caption  = wp_get_attachment_caption($attachment_id);
        $parent   = get_post_field('post_title', wp_get_post_parent_id($attachment_id));
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
        return apply_filters('opptiai_alt_prompt', $prompt, $attachment_id, $opts);
    }

    private function is_image($attachment_id){
        $mime = get_post_mime_type($attachment_id);
        return strpos((string)$mime, 'image/') === 0;
    }

    public function invalidate_stats_cache(){
        wp_cache_delete('ai_alt_stats', 'opptiai_alt');
        delete_transient('ai_alt_stats_v3');
        $this->stats_cache = null;
    }

    public function get_media_stats(){
        // Check in-memory cache first
        if (is_array($this->stats_cache)){
            return $this->stats_cache;
        }

        // Check object cache (Redis/Memcached if available)
        $cache_key = 'ai_alt_stats';
        $cache_group = 'opptiai_alt';
        $cached = wp_cache_get($cache_key, $cache_group);
        if (false !== $cached && is_array($cached)){
            $this->stats_cache = $cached;
            return $cached;
        }

        // Check transient cache (15 minute TTL for DB queries - optimized for performance)
        $transient_key = 'ai_alt_stats_v3';
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

        $with_alt = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE p.post_type = 'attachment'
               AND p.post_status = 'inherit'
               AND p.post_mime_type LIKE 'image/%'
               AND m.meta_key = '_wp_attachment_image_alt'
               AND TRIM(m.meta_value) <> ''"
        );

        $generated = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_ai_alt_generated_at'"
        );

        $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
        $missing  = max(0, $total - $with_alt);

        $opts = get_option(self::OPTION_KEY, []);
        $usage = $opts['usage'] ?? $this->default_usage();
        if (!empty($usage['last_request'])){
            $date_format = get_option('date_format');
            $time_format = get_option('time_format');
            $format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
            $usage['last_request_formatted'] = mysql2date($format, $usage['last_request']);
        }

        $latest_generated_raw = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ai_alt_generated_at' ORDER BY meta_value DESC LIMIT 1"
        );
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        $format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';
        $latest_generated = $latest_generated_raw ? mysql2date($format, $latest_generated_raw) : '';

        $top_source_row = $wpdb->get_row(
            "SELECT meta_value AS source, COUNT(*) AS count
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_ai_alt_source' AND meta_value <> ''
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
    }

    public function prepare_attachment_snapshot($attachment_id){
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0){
            return [];
        }

        $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $tokens = intval(get_post_meta($attachment_id, '_ai_alt_tokens_total', true));
        $prompt = intval(get_post_meta($attachment_id, '_ai_alt_tokens_prompt', true));
        $completion = intval(get_post_meta($attachment_id, '_ai_alt_tokens_completion', true));
        $generated_raw = get_post_meta($attachment_id, '_ai_alt_generated_at', true);
        $generated = $generated_raw ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $generated_raw) : '';
        $source_key = sanitize_key(get_post_meta($attachment_id, '_ai_alt_source', true) ?: 'unknown');
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
            '_ai_alt_review_score',
            '_ai_alt_review_status',
            '_ai_alt_review_grade',
            '_ai_alt_review_summary',
            '_ai_alt_review_issues',
            '_ai_alt_review_model',
            '_ai_alt_reviewed_at',
            '_ai_alt_review_alt_hash',
        ];
        foreach ($keys as $key){
            delete_post_meta($attachment_id, $key);
        }
    }

    private function get_review_snapshot(int $attachment_id, string $current_alt = ''): ?array{
        $score = intval(get_post_meta($attachment_id, '_ai_alt_review_score', true));
        if ($score <= 0){
            return null;
        }

        $stored_hash = get_post_meta($attachment_id, '_ai_alt_review_alt_hash', true);
        if ($current_alt !== ''){
            $current_hash = $this->hash_alt_text($current_alt);
            if ($stored_hash && !hash_equals($stored_hash, $current_hash)){
                $this->purge_review_meta($attachment_id);
                return null;
            }
        }

        $status     = sanitize_key(get_post_meta($attachment_id, '_ai_alt_review_status', true));
        $grade_raw  = get_post_meta($attachment_id, '_ai_alt_review_grade', true);
        $summary    = get_post_meta($attachment_id, '_ai_alt_review_summary', true);
        $model      = get_post_meta($attachment_id, '_ai_alt_review_model', true);
        $reviewed_at = get_post_meta($attachment_id, '_ai_alt_reviewed_at', true);

        $issues_raw = get_post_meta($attachment_id, '_ai_alt_review_issues', true);
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
                'grade' => __('Missing', 'opptiai-alt-text-generator'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'opptiai-alt-text-generator')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'opptiai-alt-text-generator'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'opptiai-alt-text-generator')],
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
                'grade' => __('Critical', 'opptiai-alt-text-generator'),
                'status' => 'critical',
                'issues' => [__('ALT text looks like placeholder content and must be rewritten.', 'opptiai-alt-text-generator')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Critical', 'opptiai-alt-text-generator'),
                    'status'=> 'critical',
                    'issues'=> [__('ALT text looks like placeholder content and must be rewritten.', 'opptiai-alt-text-generator')],
                ],
                'review' => null,
            ];
        }

        $length = function_exists('mb_strlen') ? mb_strlen($alt) : strlen($alt);
        if ($length < 45){
            $score -= 35;
            $issues[] = __('Too short – add a richer description (45+ characters).', 'opptiai-alt-text-generator');
        } elseif ($length > 160){
            $score -= 15;
            $issues[] = __('Very long – trim to keep the description concise (under 160 characters).', 'opptiai-alt-text-generator');
        }

        if (preg_match('/\b(image|picture|photo|screenshot)\b/i', $alt)){
            $score -= 10;
            $issues[] = __('Contains generic filler words like “image” or “photo”.', 'opptiai-alt-text-generator');
        }

        if (preg_match('/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt)){
            $score = min($score - 80, 5);
            $issues[] = __('Contains placeholder wording such as “test” or “sample”. Replace with a real description.', 'opptiai-alt-text-generator');
        }

        $word_count = str_word_count($alt, 0, '0123456789');
        if ($word_count < 4){
            $score -= 70;
            $score = min($score, 5);
            $issues[] = __('ALT text is extremely brief – add meaningful descriptive words.', 'opptiai-alt-text-generator');
        } elseif ($word_count < 6){
            $score -= 50;
            $score = min($score, 20);
            $issues[] = __('ALT text is too short to convey the subject in detail.', 'opptiai-alt-text-generator');
        } elseif ($word_count < 8){
            $score -= 35;
            $score = min($score, 40);
            $issues[] = __('ALT text could use a few more descriptive words.', 'opptiai-alt-text-generator');
        }

        if ($score > 40 && $length < 30){
            $score = min($score, 40);
            $issues[] = __('Expand the description with one or two concrete details.', 'opptiai-alt-text-generator');
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
                $issues[] = __('Matches the attachment title – add more unique detail.', 'opptiai-alt-text-generator');
            }
        }

        $file = get_attached_file($attachment_id);
        if ($file && $normalized_alt !== ''){
            $base = pathinfo($file, PATHINFO_FILENAME);
            $normalized_base = $normalize($base);
            if ($normalized_base !== '' && $normalized_alt === $normalized_base){
                $score -= 20;
                $issues[] = __('Matches the file name – rewrite it to describe the image.', 'opptiai-alt-text-generator');
            }
        }

        if (!preg_match('/[a-z]{4,}/i', $alt)){
            $score -= 15;
            $issues[] = __('Lacks descriptive language – include meaningful nouns or adjectives.', 'opptiai-alt-text-generator');
        }

        if (!preg_match('/\b[a-z]/i', $alt)){
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        if ($status === 'review' && empty($issues)){
            $issues[] = __('Give this ALT another look to ensure it reflects the image details.', 'opptiai-alt-text-generator');
        } elseif ($status === 'critical' && empty($issues)){
            $issues[] = __('ALT text should be rewritten for accessibility.', 'opptiai-alt-text-generator');
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
                return __('Excellent', 'opptiai-alt-text-generator');
            case 'good':
                return __('Strong', 'opptiai-alt-text-generator');
            case 'review':
                return __('Needs review', 'opptiai-alt-text-generator');
            default:
                return __('Critical', 'opptiai-alt-text-generator');
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
             LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = '_ai_alt_generated_at'
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

        $cache_key = 'ai_alt_usage_rows_' . md5($limit . '|' . ($include_all ? 'all' : 'slice'));
        if (!$include_all) {
            $cached = wp_cache_get($cache_key, 'opptiai_alt');
            if ($cached !== false) {
                return $cached;
            }
        }
        $sql = "SELECT p.ID,
                       tokens.meta_value AS tokens_total,
                       prompt.meta_value AS tokens_prompt,
                       completion.meta_value AS tokens_completion,
                       alt.meta_value AS alt_text,
                       src.meta_value AS source,
                       model.meta_value AS model,
                       gen.meta_value AS generated_at
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} tokens ON tokens.post_id = p.ID AND tokens.meta_key = '_ai_alt_tokens_total'
                LEFT JOIN {$wpdb->postmeta} prompt ON prompt.post_id = p.ID AND prompt.meta_key = '_ai_alt_tokens_prompt'
                LEFT JOIN {$wpdb->postmeta} completion ON completion.post_id = p.ID AND completion.meta_key = '_ai_alt_tokens_completion'
                LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
                LEFT JOIN {$wpdb->postmeta} src ON src.post_id = p.ID AND src.meta_key = '_ai_alt_source'
                LEFT JOIN {$wpdb->postmeta} model ON model.post_id = p.ID AND model.meta_key = '_ai_alt_model'
                LEFT JOIN {$wpdb->postmeta} gen ON gen.post_id = p.ID AND gen.meta_key = '_ai_alt_generated_at'
                WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
                ORDER BY
                    CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC,
                    CAST(tokens.meta_value AS UNSIGNED) DESC";

        if (!$include_all){
            $sql .= $wpdb->prepare(' LIMIT %d', $limit);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (empty($rows)){
            if (!$include_all) {
                wp_cache_set($cache_key, [], 'opptiai_alt', MINUTE_IN_SECONDS * 2);
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
            wp_cache_set($cache_key, $formatted, 'opptiai_alt', MINUTE_IN_SECONDS * 5);
        }

        return $formatted;
    }

    private function get_source_meta_map(){
        return [
            'auto'     => [
                'label' => __('Auto (upload)', 'opptiai-alt-text-generator'),
                'description' => __('Generated automatically when the image was uploaded.', 'opptiai-alt-text-generator'),
            ],
            'ajax'     => [
                'label' => __('Media Library (single)', 'opptiai-alt-text-generator'),
                'description' => __('Triggered from the Media Library row action or attachment details screen.', 'opptiai-alt-text-generator'),
            ],
            'bulk'     => [
                'label' => __('Media Library (bulk)', 'opptiai-alt-text-generator'),
                'description' => __('Generated via the Media Library bulk action.', 'opptiai-alt-text-generator'),
            ],
            'dashboard' => [
                'label' => __('Dashboard quick actions', 'opptiai-alt-text-generator'),
                'description' => __('Generated from the dashboard buttons.', 'opptiai-alt-text-generator'),
            ],
            'wpcli'    => [
                'label' => __('WP-CLI', 'opptiai-alt-text-generator'),
                'description' => __('Generated via the wp ai-alt CLI command.', 'opptiai-alt-text-generator'),
            ],
            'manual'   => [
                'label' => __('Manual / custom', 'opptiai-alt-text-generator'),
                'description' => __('Generated by custom code or integration.', 'opptiai-alt-text-generator'),
            ],
            'unknown'  => [
                'label' => __('Unknown', 'opptiai-alt-text-generator'),
                'description' => __('Source not recorded for this ALT text.', 'opptiai-alt-text-generator'),
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
            wp_die(__('You do not have permission to export usage data.', 'opptiai-alt-text-generator'));
        }
        check_admin_referer('opptiai_alt_usage_export');

        $rows = $this->get_usage_rows(10, true);
        $filename = 'ai-alt-usage-' . gmdate('Ymd-His') . '.csv';

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
            wp_die(__('You do not have permission to export debug logs.', 'opptiai-alt-text-generator'));
        }
        check_admin_referer('opptiai_alt_debug_export');

        if (!class_exists('AltText_AI_Debug_Log')) {
            wp_die(__('Debug logging is not available.', 'opptiai-alt-text-generator'));
        }

        global $wpdb;
        $table = AltText_AI_Debug_Log::table();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=alttextai-debug-logs-' . gmdate('Ymd-His') . '.csv');

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

        if ($content !== '' && $content[0] !== '{'){
            $start = strpos($content, '{');
            $end   = strrpos($content, '}');
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
            if (strpos($message, $needle) !== false){
                return true;
            }
        }

        $data = $error->get_error_data();
        if (is_array($data)){
            if (!empty($data['message']) && is_string($data['message'])){
                $msg = strtolower($data['message']);
                foreach ($needles as $needle){
                    if (strpos($msg, $needle) !== false){
                        return true;
                    }
                }
            }
            if (!empty($data['body']['error']['message']) && is_string($data['body']['error']['message'])){
                $msg = strtolower($data['body']['error']['message']);
                foreach ($needles as $needle){
                    if (strpos($msg, $needle) !== false){
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
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'opptiai-alt-text-generator'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0){
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'opptiai-alt-text-generator'));
        }

        $limit = apply_filters('opptiai_alt_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit){
            return new \WP_Error('inline_image_too_large', __('Image exceeds the inline embedding size limit.', 'opptiai-alt-text-generator'), ['size' => $size, 'limit' => $limit]);
        }

        $contents = file_get_contents($file);
        if ($contents === false){
            return new \WP_Error('inline_image_read_failed', __('Unable to read the image file for inline embedding.', 'opptiai-alt-text-generator'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (empty($mime)){
            $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'image/jpeg';
        }

        $base64 = base64_encode($contents);
        if (!$base64){
            return new \WP_Error('inline_image_encode_failed', __('Failed to encode the image for inline embedding.', 'opptiai-alt-text-generator'));
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
            return new \WP_Error('review_skipped', __('ALT text is empty; skipped review.', 'opptiai-alt-text-generator'));
        }

        $review_model = $opts['review_model'] ?? ($opts['model'] ?? 'gpt-4o-mini');
        $review_model = apply_filters('opptiai_alt_review_model', $review_model, $attachment_id, $opts);
        if (!$review_model){
            return new \WP_Error('review_model_missing', __('No review model configured.', 'opptiai-alt-text-generator'));
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
            $context_lines[] = sprintf(__('Media title: %s', 'opptiai-alt-text-generator'), $title);
        }
        if ($filename){
            $context_lines[] = sprintf(__('Filename: %s', 'opptiai-alt-text-generator'), $filename);
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
            return new \WP_Error('review_parse_failed', __('Unable to parse review response.', 'opptiai-alt-text-generator'), ['response' => $content]);
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
        update_post_meta($attachment_id, '_ai_alt_source', $source);
        update_post_meta($attachment_id, '_ai_alt_model', $model);
        update_post_meta($attachment_id, '_ai_alt_generated_at', current_time('mysql'));
        update_post_meta($attachment_id, '_ai_alt_tokens_prompt', $usage_summary['prompt']);
        update_post_meta($attachment_id, '_ai_alt_tokens_completion', $usage_summary['completion']);
        update_post_meta($attachment_id, '_ai_alt_tokens_total', $usage_summary['total']);

        if ($image_strategy === 'remote'){
            delete_post_meta($attachment_id, '_ai_alt_image_reference');
        } else {
            update_post_meta($attachment_id, '_ai_alt_image_reference', $image_strategy);
        }

        if (!is_wp_error($review_result)){
            update_post_meta($attachment_id, '_ai_alt_review_score', $review_result['score']);
            update_post_meta($attachment_id, '_ai_alt_review_status', $review_result['status']);
            update_post_meta($attachment_id, '_ai_alt_review_grade', $review_result['grade']);
            update_post_meta($attachment_id, '_ai_alt_review_summary', $review_result['summary']);
            update_post_meta($attachment_id, '_ai_alt_review_issues', wp_json_encode($review_result['issues']));
            update_post_meta($attachment_id, '_ai_alt_review_model', $review_result['model']);
            update_post_meta($attachment_id, '_ai_alt_reviewed_at', current_time('mysql'));
            update_post_meta($attachment_id, '_ai_alt_review_alt_hash', $this->hash_alt_text($alt));
            delete_post_meta($attachment_id, '_ai_alt_review_error');
            if (!empty($review_result['usage'])){
                $this->record_usage($review_result['usage']);
            }
        } else {
            update_post_meta($attachment_id, '_ai_alt_review_error', $review_result->get_error_message());
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
            set_transient('opptiai_limit_notice', 1, MINUTE_IN_SECONDS * 10);
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
            if ($this->api_client->has_reached_limit()) {
                return new \WP_Error('limit_reached', __('Monthly generation limit reached. Please upgrade to continue.', 'opptiai-alt-text-generator'));
            }
        }

        if (!$this->is_image($attachment_id)) return new \WP_Error('not_image', 'Attachment is not an image.');

        // Prefer higher-quality default for better accuracy
        $model = apply_filters('opptiai_alt_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts);
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $prompt = $this->build_prompt($attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback);

        if (!empty($opts['dry_run'])){
            update_post_meta($attachment_id, '_ai_alt_last_prompt', $prompt);
            update_post_meta($attachment_id, '_ai_alt_source', 'dry-run');
            update_post_meta($attachment_id, '_ai_alt_model', $model);
            update_post_meta($attachment_id, '_ai_alt_generated_at', current_time('mysql'));
            $this->stats_cache = null;
            return new \WP_Error('ai_alt_dry_run', __('Dry run enabled. Prompt stored for review; ALT text not updated.', 'opptiai-alt-text-generator'), ['prompt' => $prompt]);
        }

        // Build context for API
        $post = get_post($attachment_id);
        $file_path = get_attached_file($attachment_id);
        $filename = $file_path ? basename($file_path) : '';
        $context = [
            'filename' => $filename,
            'title' => get_the_title($attachment_id),
            'caption' => $post->post_excerpt ?? '',
            'post_title' => '',
        ];
        
        // Get parent post context if available
        if ($post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                $context['post_title'] = $parent->post_title;
            }
        }

        // Always call the real API to generate actual alt text
        // (Mock mode disabled - we want real AI-generated descriptions)
        $api_response = $this->api_client->generate_alt_text($attachment_id, $context, $regenerate);

        if (is_wp_error($api_response)) {
            if (class_exists('AltText_AI_Debug_Log')) {
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
                
                AltText_AI_Debug_Log::log(
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
            $error_message = __('Failed to generate alt text', 'opptiai-alt-text-generator');
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
            if (class_exists('AltText_AI_Debug_Log')) {
                AltText_AI_Debug_Log::log('error', 'Alt text generation failed - invalid response', [
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
            AltText_AI_Usage_Tracker::clear_cache();
        }

        if (!empty($api_response['usage']) && is_array($api_response['usage'])) {
            AltText_AI_Usage_Tracker::update_usage($api_response['usage']);

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
                AltText_AI_Usage_Tracker::clear_cache();
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
                    __('Generated ALT text matched the existing value.', 'opptiai-alt-text-generator'),
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

        if (class_exists('AltText_AI_Debug_Log')) {
            AltText_AI_Debug_Log::log('info', 'Alt text updated', [
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

        return AltText_AI_Queue::enqueue($attachment_id, $source ? sanitize_key($source) : 'auto');
    }

    public function register_bulk_action($bulk_actions){
        $bulk_actions['ai_alt_generate'] = __('Generate Alt Text (AI)', 'opptiai-alt-text-generator');
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids){
        if ($doaction !== 'ai_alt_generate') return $redirect_to;
        $queued = 0;
        foreach ($post_ids as $id){
            if ($this->queue_attachment($id, 'bulk')) {
                $queued++;
            }
        }
        if ($queued > 0) {
            AltText_AI_Queue::schedule_processing(10);
            
            // Log bulk operation
            if (class_exists('AltText_AI_Debug_Log')) {
                AltText_AI_Debug_Log::log('info', 'Bulk alt text generation queued', [
                    'count' => $queued,
                    'total_selected' => count($post_ids),
                ], 'bulk');
            }
        }
        return add_query_arg(['ai_alt_queued' => $queued], $redirect_to);
    }

    public function row_action_link($actions, $post){
        if ($post->post_type === 'attachment' && $this->is_image($post->ID)){
            $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $generate_label   = __('Generate Alt Text (AI)', 'opptiai-alt-text-generator');
            $regenerate_label = __('Regenerate Alt Text (AI)', 'opptiai-alt-text-generator');
            $text = $has_alt ? $regenerate_label : $generate_label;
            $actions['ai_alt_generate_single'] = '<a href="#" class="ai-alt-generate" data-id="' . intval($post->ID) . '" data-has-alt="' . ($has_alt ? '1' : '0') . '" data-label-generate="' . esc_attr($generate_label) . '" data-label-regenerate="' . esc_attr($regenerate_label) . '">' . esc_html($text) . '</a>';
        }
        return $actions;
    }

    public function attachment_fields_to_edit($fields, $post){
        if (!$this->is_image($post->ID)){
            return $fields;
        }

        $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $label_generate   = __('Generate Alt', 'opptiai-alt-text-generator');
        $label_regenerate = __('Regenerate Alt', 'opptiai-alt-text-generator');
        $current_label    = $has_alt ? $label_regenerate : $label_generate;
        $is_authenticated = $this->api_client->is_authenticated();
        $disabled_attr    = !$is_authenticated ? ' disabled title="' . esc_attr__('Please log in to generate alt text', 'opptiai-alt-text-generator') . '"' : '';
        $button = sprintf(
            '<button type="button" class="button ai-alt-generate" data-id="%1$d" data-has-alt="%2$d" data-label-generate="%3$s" data-label-regenerate="%4$s"%5$s>%6$s</button>',
            intval($post->ID),
            $has_alt ? 1 : 0,
            esc_attr($label_generate),
            esc_attr($label_regenerate),
            $disabled_attr,
            esc_html($current_label)
        );

        // Hide attachment screen field by default to avoid confusion; can be re-enabled via filter
        if (apply_filters('ai_alt_show_attachment_button', false)){
        $fields['ai_alt_generate'] = [
            'label' => __('AI Alt Text', 'opptiai-alt-text-generator'),
            'input' => 'html',
            'html'  => $button . '<p class="description">' . esc_html__('Use AI to suggest alternative text for this image.', 'opptiai-alt-text-generator') . '</p>',
        ];
        }

        return $fields;
    }

	/**
	 * @deprecated 4.3.0 Use Opptiai_Alt_REST_Controller::register_routes().
	 */
	public function register_rest_routes(){
		if ( ! class_exists( 'Opptiai_Alt_REST_Controller' ) ) {
			require_once OPPTIAI_ALT_PLUGIN_DIR . 'admin/class-opptiai-alt-rest-controller.php';
		}

		( new Opptiai_Alt_REST_Controller( $this ) )->register_routes();
	}

    public function enqueue_admin($hook){
        $base_path = OPPTIAI_ALT_PLUGIN_DIR;
        $base_url  = OPPTIAI_ALT_PLUGIN_URL;

        $asset_version = static function(string $relative, string $fallback = '1.0.0') use ($base_path): string {
            $relative = ltrim($relative, '/');
            $path = $base_path . $relative;
            return file_exists($path) ? (string) filemtime($path) : $fallback;
        };

        $use_debug_assets = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $js_base  = $use_debug_assets ? 'assets/src/js/' : 'assets/dist/js/';
        $css_base = $use_debug_assets ? 'assets/src/css/' : 'assets/dist/css/';
        $asset_path = static function(string $base, string $name, bool $debug, string $type): string {
            $extension = $debug ? ".$type" : ".min.$type";
            return $base . $name . $extension;
        };

        $admin_file    = $asset_path($js_base, 'ai-alt-admin', $use_debug_assets, 'js');
        $admin_version = $asset_version($admin_file, '3.0.0');
        
        $checkout_prices = $this->get_checkout_price_ids();

        $l10n_common = [
            'reviewCue'           => __('Visit the ALT Library to double-check the wording.', 'opptiai-alt-text-generator'),
            'statusReady'         => '',
            'previewAltHeading'   => __('Review generated ALT text', 'opptiai-alt-text-generator'),
            'previewAltHint'      => __('Review the generated description before applying it to your media item.', 'opptiai-alt-text-generator'),
            'previewAltApply'     => __('Use this ALT', 'opptiai-alt-text-generator'),
            'previewAltCancel'    => __('Keep current ALT', 'opptiai-alt-text-generator'),
            'previewAltDismissed' => __('Preview dismissed. Existing ALT kept.', 'opptiai-alt-text-generator'),
            'previewAltShortcut'  => __('Shift + Enter for newline.', 'opptiai-alt-text-generator'),
        ];

        // Load on Media Library and attachment edit contexts (modal also)
        if (in_array($hook, ['upload.php', 'post.php', 'post-new.php', 'media_page_opptiai-alt'], true)){
            wp_enqueue_script('opptiai-alt-admin', $base_url . $admin_file, ['jquery'], $admin_version, true);
            wp_localize_script('opptiai-alt-admin', 'OPPTIAI_ALT', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'rest'      => esc_url_raw( rest_url('opptiai/v1/generate/') ),
                'restAlt'   => esc_url_raw( rest_url('opptiai/v1/alt/') ),
                'restStats' => esc_url_raw( rest_url('opptiai/v1/stats') ),
                'restUsage' => esc_url_raw( rest_url('opptiai/v1/usage') ),
                'restMissing'=> esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('opptiai/v1/list')) ),
                'restAll'    => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('opptiai/v1/list')) ),
                'restQueue'  => esc_url_raw( rest_url('opptiai/v1/queue') ),
                'restRoot'  => esc_url_raw( rest_url() ),
                'restPlans' => esc_url_raw( rest_url('opptiai/v1/plans') ),
                'l10n'      => $l10n_common,
                'upgradeUrl'=> esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'canManage' => $this->user_can_manage(),
                'inlineBatchSize' => defined('OPPTIAI_ALT_INLINE_BATCH') ? max(1, intval(OPPTIAI_ALT_INLINE_BATCH)) : 1,
            ]);
            // Also add alttextai_ajax for regenerate functionality
            wp_localize_script('opptiai-alt-admin', 'alttextai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('opptiai_alt_upgrade_nonce'),
                'can_manage' => $this->user_can_manage(),
            ]);
        }

        if ($hook === 'media_page_opptiai-alt'){
            $css_file    = $asset_path($css_base, 'ai-alt-dashboard', $use_debug_assets, 'css');
            $js_file     = $asset_path($js_base, 'ai-alt-dashboard', $use_debug_assets, 'js');
            $upgrade_css = $asset_path($css_base, 'upgrade-modal', $use_debug_assets, 'css');
            $upgrade_js  = $asset_path($js_base, 'upgrade-modal', $use_debug_assets, 'js');
            $auth_css    = $asset_path($css_base, 'auth-modal', $use_debug_assets, 'css');
            $auth_js     = $asset_path($js_base, 'auth-modal', $use_debug_assets, 'js');

            // Enqueue design system (FIRST - foundation for all styles)
            wp_enqueue_style(
                'opptiai-alt-design-system',
                $base_url . $asset_path($css_base, 'design-system', $use_debug_assets, 'css'),
                [],
                $asset_version($asset_path($css_base, 'design-system', $use_debug_assets, 'css'), '1.0.0')
            );

            // Enqueue reusable components (SECOND - uses design tokens)
            wp_enqueue_style(
                'opptiai-alt-components',
                $base_url . $asset_path($css_base, 'components', $use_debug_assets, 'css'),
                ['opptiai-alt-design-system'],
                $asset_version($asset_path($css_base, 'components', $use_debug_assets, 'css'), '1.0.0')
            );

            // Enqueue page-specific styles (use design system + components)
            wp_enqueue_style(
                'opptiai-alt-dashboard',
                $base_url . $css_file,
                ['opptiai-alt-components'],
                $asset_version($css_file, '3.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-modern',
                $base_url . $asset_path($css_base, 'modern-style', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'modern-style', $use_debug_assets, 'css'), '4.1.0')
            );
            wp_enqueue_style(
                'opptiai-alt-ui',
                $base_url . $asset_path($css_base, 'ui', $use_debug_assets, 'css'),
                ['opptiai-alt-modern'],
                $asset_version($asset_path($css_base, 'ui', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-upgrade',
                $base_url . $upgrade_css,
                ['opptiai-alt-components'],
                $asset_version($upgrade_css, '3.1.0')
            );
            wp_enqueue_style(
                'opptiai-alt-auth',
                $base_url . $auth_css,
                ['opptiai-alt-components'],
                $asset_version($auth_css, '4.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-button-enhancements',
                $base_url . $asset_path($css_base, 'button-enhancements', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'button-enhancements', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-guide-settings',
                $base_url . $asset_path($css_base, 'guide-settings-pages', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'guide-settings-pages', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-debug-styles',
                $base_url . $asset_path($css_base, 'ai-alt-debug', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'ai-alt-debug', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-bulk-progress',
                $base_url . $asset_path($css_base, 'bulk-progress-modal', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'bulk-progress-modal', $use_debug_assets, 'css'), '1.0.0')
            );
            wp_enqueue_style(
                'opptiai-alt-success-modal',
                $base_url . $asset_path($css_base, 'success-modal', $use_debug_assets, 'css'),
                ['opptiai-alt-components'],
                $asset_version($asset_path($css_base, 'success-modal', $use_debug_assets, 'css'), '1.0.0')
            );


            $stats_data = $this->get_media_stats();
            $usage_data = AltText_AI_Usage_Tracker::get_stats_display();
            
            wp_enqueue_script(
                'opptiai-alt-dashboard',
                $base_url . $js_file,
                ['jquery', 'wp-api-fetch'],
                $asset_version($js_file, '3.0.0'),
                true
            );
            wp_enqueue_script(
                'opptiai-alt-upgrade',
                $base_url . $upgrade_js,
                ['jquery'],
                $asset_version($upgrade_js, '3.1.0'),
                true
            );
            wp_enqueue_script(
                'opptiai-alt-auth',
                $base_url . $auth_js,
                ['jquery'],
                $asset_version($auth_js, '4.0.0'),
                true
            );
            wp_enqueue_script(
                'opptiai-alt-debug',
                $base_url . $asset_path($js_base, 'ai-alt-debug', $use_debug_assets, 'js'),
                ['jquery'],
                $asset_version($asset_path($js_base, 'ai-alt-debug', $use_debug_assets, 'js'), '1.0.0'),
                true
            );
            
            // Localize debug script configuration
            wp_localize_script('opptiai-alt-debug', 'OPPTIAI_ALT_DEBUG', [
                'restLogs' => esc_url_raw( rest_url('opptiai/v1/debug/logs') ),
                'restClear' => esc_url_raw( rest_url('opptiai/v1/debug/clear') ),
                'nonce' => wp_create_nonce('wp_rest'),
                'initial' => class_exists('AltText_AI_Debug_Log') ? AltText_AI_Debug_Log::get_logs([
                    'per_page' => 10,
                    'page' => 1,
                ]) : [
                    'logs' => [],
                    'pagination' => ['page' => 1, 'per_page' => 10, 'total_pages' => 1, 'total_items' => 0],
                    'stats' => ['total' => 0, 'warnings' => 0, 'errors' => 0, 'last_api' => null],
                ],
                'strings' => [
                    'noLogs' => __('No logs recorded yet.', 'opptiai-alt-text-generator'),
                    'contextTitle' => __('Log Context', 'opptiai-alt-text-generator'),
                    'clearConfirm' => __('This will permanently delete all debug logs. Continue?', 'opptiai-alt-text-generator'),
                    'errorGeneric' => __('Unable to load debug logs. Please try again.', 'opptiai-alt-text-generator'),
                    'emptyContext' => __('No additional context was provided for this entry.', 'opptiai-alt-text-generator'),
                    'cleared' => __('Logs cleared successfully.', 'opptiai-alt-text-generator'),
                ],
            ]);

            wp_localize_script('opptiai-alt-dashboard', 'OPPTIAI_ALT_DASH', [
                'nonce'       => wp_create_nonce('wp_rest'),
                'rest'        => esc_url_raw( rest_url('opptiai/v1/generate/') ),
                'restStats'   => esc_url_raw( rest_url('opptiai/v1/stats') ),
                'restUsage'   => esc_url_raw( rest_url('opptiai/v1/usage') ),
                'restMissing' => esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('opptiai/v1/list')) ),
                'restAll'     => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('opptiai/v1/list')) ),
                'restQueue'   => esc_url_raw( rest_url('opptiai/v1/queue') ),
                'restRoot'    => esc_url_raw( rest_url() ),
                'restPlans'   => esc_url_raw( rest_url('opptiai/v1/plans') ),
                'upgradeUrl'  => esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl'=> esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'stats'       => $stats_data,
                'initialUsage'=> $usage_data,
            ]);
            
            // Add AJAX variables for regenerate functionality and auth
            $options = get_option(self::OPTION_KEY, []);
            // Use mock backend for local development
            $api_url = defined('WP_LOCAL_DEV') && WP_LOCAL_DEV ? 'http://host.docker.internal:3001' : ($options['api_url'] ?? 'https://alttext-ai-backend.onrender.com');

            wp_localize_script('opptiai-alt-dashboard', 'alttextai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('opptiai_alt_upgrade_nonce'),
                'api_url' => $api_url,
                'is_authenticated' => $this->api_client->is_authenticated(),
                'user_data' => $this->api_client->get_user_data(),
                'can_manage' => $this->user_can_manage(),
            ]);
            
            wp_localize_script('opptiai-alt-dashboard', 'OPPTIAI_ALT_DASH_L10N', [
                'l10n'        => array_merge([
                    'processing'         => __('Generating ALT text…', 'opptiai-alt-text-generator'),
                    'processingMissing'  => __('Generating ALT for #%d…', 'opptiai-alt-text-generator'),
                    'error'              => __('Something went wrong. Check console for details.', 'opptiai-alt-text-generator'),
                    'summary'            => __('Generated %1$d images (%2$d errors).', 'opptiai-alt-text-generator'),
                    'restUnavailable'    => __('REST endpoint unavailable', 'opptiai-alt-text-generator'),
                    'prepareBatch'       => __('Preparing image list…', 'opptiai-alt-text-generator'),
                    'coverageCopy'       => __('of images currently include ALT text.', 'opptiai-alt-text-generator'),
                    'noRequests'         => __('None yet', 'opptiai-alt-text-generator'),
                    'noAudit'            => __('No usage data recorded yet.', 'opptiai-alt-text-generator'),
                    'nothingToProcess'   => __('No images to process.', 'opptiai-alt-text-generator'),
                    'batchStart'         => __('Starting batch…', 'opptiai-alt-text-generator'),
                    'batchComplete'      => __('Batch complete.', 'opptiai-alt-text-generator'),
                    'batchCompleteAt'    => __('Batch complete at %s', 'opptiai-alt-text-generator'),
                    'completedItem'      => __('Finished #%d', 'opptiai-alt-text-generator'),
                    'failedItem'         => __('Failed #%d', 'opptiai-alt-text-generator'),
                    'loadingButton'      => __('Processing…', 'opptiai-alt-text-generator'),
                ], $l10n_common),
            ]);
            
            // Localize upgrade modal script
            wp_localize_script('opptiai-alt-upgrade', 'AltTextAI', [
                'nonce' => wp_create_nonce('alttextai_upgrade_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'usage' => $usage_data,
                'upgradeUrl' => esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'priceIds' => $checkout_prices,
                'restPlans' => esc_url_raw( rest_url('opptiai/v1/plans') ),
                'canManage' => $this->user_can_manage(),
            ]);

            $debug_bootstrap = $this->get_debug_bootstrap();
            wp_localize_script('opptiai-alt-debug', 'OPPTIAI_ALT_DEBUG', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'restLogs'  => esc_url_raw( rest_url('opptiai/v1/logs') ),
                'restClear' => esc_url_raw( rest_url('opptiai/v1/logs/clear') ),
                'initial'   => $debug_bootstrap,
                'strings'   => [
                    'noLogs'       => __('No logs recorded yet.', 'opptiai-alt-text-generator'),
                    'contextTitle' => __('Log Context', 'opptiai-alt-text-generator'),
                    'clearConfirm' => __('This will permanently delete all debug logs. Continue?', 'opptiai-alt-text-generator'),
                    'errorGeneric' => __('Unable to load debug logs. Please try again.', 'opptiai-alt-text-generator'),
                    'emptyContext' => __('No additional context was provided for this entry.', 'opptiai-alt-text-generator'),
                    'cleared'      => __('Logs cleared successfully.', 'opptiai-alt-text-generator'),
                    'pageIndicator'=> __('Page %1$d of %2$d', 'opptiai-alt-text-generator'),
                ],
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
            if ($res->get_error_code() === 'ai_alt_dry_run') {
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
    public function ajax_dismiss_upgrade() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        AltText_AI_Usage_Tracker::dismiss_upgrade_notice();
        setcookie('opptiai_alt_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_queue_retry_job() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }
        $job_id = intval($_POST['job_id'] ?? 0);
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'opptiai-alt-text-generator')]);
        }
        AltText_AI_Queue::retry_job($job_id);
        AltText_AI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'opptiai-alt-text-generator')]);
    }

    public function ajax_queue_retry_failed() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }
        AltText_AI_Queue::retry_failed();
        AltText_AI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'opptiai-alt-text-generator')]);
    }

    public function ajax_queue_clear_completed() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }
        AltText_AI_Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'opptiai-alt-text-generator')]);
    }

    public function ajax_queue_stats() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }
        
        $stats = AltText_AI_Queue::get_stats();
        $failures = AltText_AI_Queue::get_failures();
        
        wp_send_json_success([
            'stats' => $stats,
            'failures' => $failures
        ]);
    }

    public function ajax_track_upgrade() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $source = sanitize_key($_POST['source'] ?? 'dashboard');
        $event  = [
            'source' => $source,
            'user_id' => get_current_user_id(),
            'time'   => current_time('mysql'),
        ];

        update_option('opptiai_alt_last_upgrade_click', $event, false);
        do_action('opptiai_alt_upgrade_clicked', $event);

        wp_send_json_success(['recorded' => true]);
    }

    public function ajax_refresh_usage() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        // Clear cache and fetch fresh data
        AltText_AI_Usage_Tracker::clear_cache();
        $usage = $this->api_client->get_usage();
        
        if ($usage) {
            $stats = AltText_AI_Usage_Tracker::get_stats_display();
            wp_send_json_success($stats);
        } else {
            wp_send_json_error(['message' => 'Failed to fetch usage data']);
        }
    }

    /**
     * AJAX handler: Regenerate single image alt text
     */
    public function ajax_regenerate_single() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
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

        AltText_AI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        wp_send_json_success([
            'message'        => __('Alt text generated successfully.', 'opptiai-alt-text-generator'),
            'alt_text'       => $result,
            'attachment_id'  => $attachment_id,
        ]);
    }

    /**
     * AJAX handler: Bulk queue images for processing
     */
    public function ajax_bulk_queue() {
        // Log for debugging
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            error_log('ajax_bulk_queue called');
            error_log('POST data: ' . print_r($_POST, true));
        }
        
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $attachment_ids = isset($_POST['attachment_ids']) ? $_POST['attachment_ids'] : [];
        $source = sanitize_text_field($_POST['source'] ?? 'bulk');
        
        if (!is_array($attachment_ids) || empty($attachment_ids)) {
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
            if (is_wp_error($usage) || !$usage || ($usage['remaining'] ?? 0) <= 0) {
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => is_wp_error($usage) ? null : $usage
                ]);
            }
            
            // Check how many we can queue
            $remaining = $usage['remaining'] ?? 0;
            if (count($ids) > $remaining) {
                wp_send_json_error([
                    'message' => sprintf(__('You only have %d generations remaining. Please upgrade or select fewer images.', 'opptiai-alt-text-generator'), $remaining),
                    'code' => 'insufficient_credits',
                    'remaining' => $remaining
                ]);
            }
        }
        
        // Queue images (will clear existing entries for bulk-regenerate)
        $queued = AltText_AI_Queue::enqueue_many($ids, $source);
        
        // Log bulk queue operation
        if (class_exists('AltText_AI_Debug_Log')) {
            AltText_AI_Debug_Log::log('info', 'Bulk queue operation', [
                'queued' => $queued,
                'requested' => count($ids),
                'source' => $source,
            ], 'bulk');
        }
        
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            error_log("Queued {$queued} images out of " . count($ids) . " requested");
        }
        
        if ($queued > 0) {
            // Schedule queue processing
            AltText_AI_Queue::schedule_processing();
            
            wp_send_json_success([
                'message' => sprintf(__('%d image(s) queued for processing', 'opptiai-alt-text-generator'), $queued),
                'queued' => $queued,
                'total' => count($ids)
            ]);
        } else {
            // For regeneration, if nothing was queued, it might mean they're already completed
            // Check if images already have alt text and suggest direct regeneration instead
            if ($source === 'bulk-regenerate') {
                wp_send_json_error([
                    'message' => __('No images were queued. They may already be processing or have completed. Try refreshing the page.', 'opptiai-alt-text-generator'),
                    'code' => 'already_queued'
                ]);
            } else {
                wp_send_json_error([
                    'message' => __('Failed to queue images. They may already be queued or processing.', 'opptiai-alt-text-generator')
                ]);
            }
        }
    }

    public function process_queue() {
        $batch_size = apply_filters('ai_alt_queue_batch_size', 3);
        $max_attempts = apply_filters('ai_alt_queue_max_attempts', 3);

        AltText_AI_Queue::reset_stale(apply_filters('ai_alt_queue_stale_timeout', 10 * MINUTE_IN_SECONDS));

        $jobs = AltText_AI_Queue::claim_batch($batch_size);
        if (empty($jobs)) {
            AltText_AI_Queue::purge_completed(apply_filters('ai_alt_queue_purge_age', DAY_IN_SECONDS * 2));
            return;
        }

        foreach ($jobs as $job) {
            $attachment_id = intval($job->attachment_id);
            if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
                AltText_AI_Queue::mark_complete($job->id);
                continue;
            }

            $result = $this->generate_and_save($attachment_id, $job->source ?? 'queue', max(0, intval($job->attempts) - 1));

            if (is_wp_error($result)) {
                $code    = $result->get_error_code();
                $message = $result->get_error_message();

                if ($code === 'limit_reached') {
                    AltText_AI_Queue::mark_retry($job->id, $message);
                    AltText_AI_Queue::schedule_processing(apply_filters('ai_alt_queue_limit_delay', HOUR_IN_SECONDS));
                    break;
                }

                if (intval($job->attempts) >= $max_attempts) {
                    AltText_AI_Queue::mark_failed($job->id, $message);
                } else {
                    AltText_AI_Queue::mark_retry($job->id, $message);
                }
                continue;
            }

            AltText_AI_Queue::mark_complete($job->id);
        }

        AltText_AI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();
        $stats = AltText_AI_Queue::get_stats();
        if (!empty($stats['pending'])) {
            AltText_AI_Queue::schedule_processing(apply_filters('ai_alt_queue_next_delay', 45));
        }

        AltText_AI_Queue::purge_completed(apply_filters('ai_alt_queue_purge_age', DAY_IN_SECONDS * 2));
    }

    public function handle_media_change($attachment_id = 0) {
        $this->invalidate_stats_cache();

        if (current_filter() === 'delete_attachment') {
            AltText_AI_Queue::schedule_processing(30);
            return;
        }

        $opts = get_option(self::OPTION_KEY, []);
        if (empty($opts['enable_on_upload'])) {
            return;
        }

        $this->queue_attachment($attachment_id, 'upload');
        AltText_AI_Queue::schedule_processing(15);
    }

    public function handle_media_metadata_update($data, $post_id) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'metadata');
        AltText_AI_Queue::schedule_processing(20);
        return $data;
    }

    public function handle_attachment_updated($post_id, $post_after, $post_before) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'update');
        AltText_AI_Queue::schedule_processing(20);
    }

    public function handle_post_save($post_ID, $post, $update) {
        if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
            $this->invalidate_stats_cache();
            if ($update) {
                $this->queue_attachment($post_ID, 'save');
                AltText_AI_Queue::schedule_processing(20);
            }
        }
    }

    private function get_account_summary(array $usage_stats = null) {
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
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'opptiai-alt-text-generator')]);
        }

        $result = $this->api_client->register($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Account created successfully', 'opptiai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User login
     */
    public function ajax_login() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'opptiai-alt-text-generator')]);
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'opptiai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     */
    public function ajax_logout() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $this->api_client->clear_token();

        wp_send_json_success(['message' => __('Logged out successfully', 'opptiai-alt-text-generator')]);
    }

    public function ajax_disconnect_account() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $this->api_client->clear_token();
        AltText_AI_Usage_Tracker::clear_cache();
        delete_transient('opptiai_alt_usage_cache');
        delete_transient('opptiai_alt_token_last_check');

        wp_send_json_success([
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'opptiai-alt-text-generator'),
        ]);
    }

    /**
     * AJAX handler: Activate license key
     */
    public function ajax_activate_license() {
        check_ajax_referer('opptiai_alt_license_action', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $license_key = isset($_POST['license_key']) && is_string($_POST['license_key'])
            ? sanitize_text_field($_POST['license_key'])
            : '';

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'opptiai-alt-text-generator')]);
        }

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key)) {
            wp_send_json_error(['message' => __('Invalid license key format', 'opptiai-alt-text-generator')]);
        }

        $result = $this->api_client->activate_license($license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Clear cached usage data
        AltText_AI_Usage_Tracker::clear_cache();
        delete_transient('opptiai_alt_usage_cache');

        wp_send_json_success([
            'message' => __('License activated successfully', 'opptiai-alt-text-generator'),
            'organization' => $result['organization'] ?? null,
            'site' => $result['site'] ?? null
        ]);
    }

    /**
     * AJAX handler: Deactivate license key
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('opptiai_alt_license_action', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $result = $this->api_client->deactivate_license();

        // Clear cached usage data
        AltText_AI_Usage_Tracker::clear_cache();
        delete_transient('opptiai_alt_usage_cache');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('License deactivated successfully', 'opptiai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Get license site usage
     */
    public function ajax_get_license_sites() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        // Must be authenticated to view license site usage
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view license site usage', 'opptiai-alt-text-generator')
            ]);
        }

        // Fetch license site usage from API
        $result = $this->api_client->get_license_sites();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to fetch license site usage', 'opptiai-alt-text-generator')
            ]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Disconnect a site from the license
     */
    public function ajax_disconnect_license_site() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        // Must be authenticated to disconnect license sites
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to disconnect license sites', 'opptiai-alt-text-generator')
            ]);
        }

        $site_id = isset($_POST['site_id']) ? sanitize_text_field($_POST['site_id']) : '';
        if (empty($site_id)) {
            wp_send_json_error([
                'message' => __('Site ID is required', 'opptiai-alt-text-generator')
            ]);
        }

        // Disconnect the site from the license
        $result = $this->api_client->disconnect_license_site($site_id);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to disconnect site', 'opptiai-alt-text-generator')
            ]);
        }

        wp_send_json_success([
            'message' => __('Site disconnected successfully', 'opptiai-alt-text-generator'),
            'data' => $result
        ]);
    }

    /**
     * Check if admin is authenticated (separate from regular user auth)
     */
    private function is_admin_authenticated() {
        // Check if we have a valid admin session
        $admin_session = get_transient('alttextai_admin_session_' . get_current_user_id());
        if ($admin_session === false || empty($admin_session)) {
            return false;
        }
        
        // Verify session hasn't expired (24 hours)
        $session_time = get_transient('alttextai_admin_session_time_' . get_current_user_id());
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
        set_transient('alttextai_admin_session_' . $user_id, 'authenticated', DAY_IN_SECONDS);
        set_transient('alttextai_admin_session_time_' . $user_id, time(), DAY_IN_SECONDS);
    }

    /**
     * Clear admin session
     */
    private function clear_admin_session() {
        $user_id = get_current_user_id();
        delete_transient('alttextai_admin_session_' . $user_id);
        delete_transient('alttextai_admin_session_time_' . $user_id);
    }

    /**
     * AJAX handler: Admin login for agency users
     */
    public function ajax_admin_login() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
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
                'message' => __('Admin access is only available for agency licenses', 'opptiai-alt-text-generator')
            ]);
        }

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'opptiai-alt-text-generator')
            ]);
        }

        if (empty($password)) {
            wp_send_json_error([
                'message' => __('Please enter your password', 'opptiai-alt-text-generator')
            ]);
        }

        // Attempt login
        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Login failed. Please check your credentials.', 'opptiai-alt-text-generator')
            ]);
        }

        // Set admin session
        $this->set_admin_session();

        wp_send_json_success([
            'message' => __('Successfully logged in', 'opptiai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=opptiai-alt'))
        ]);
    }

    /**
     * AJAX handler: Admin logout
     */
    public function ajax_admin_logout() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $this->clear_admin_session();

        wp_send_json_success([
            'message' => __('Logged out successfully', 'opptiai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=opptiai-alt'))
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'opptiai-alt-text-generator'),
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
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to upgrade', 'opptiai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
        }

        $price_id = sanitize_text_field($_POST['price_id'] ?? '');
        if (empty($price_id)) {
            wp_send_json_error(['message' => __('Price ID is required', 'opptiai-alt-text-generator')]);
        }

        $success_url = admin_url('upload.php?page=opptiai-alt&checkout=success');
        $cancel_url = admin_url('upload.php?page=opptiai-alt&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
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
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
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
                'message' => __('Please log in to manage billing', 'opptiai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
        }

        // For admin-authenticated users with license, try using stored portal URL first
        if ($is_admin_authenticated && $has_agency_license && !$is_authenticated) {
            $stored_portal_url = AltText_AI_Usage_Tracker::get_billing_portal_url();
            if (!empty($stored_portal_url)) {
                wp_send_json_success([
                    'url' => $stored_portal_url
                ]);
                return;
            }
        }

        $return_url = admin_url('upload.php?page=opptiai-alt');
        $result = $this->api_client->create_customer_portal_session($return_url);

        if (is_wp_error($result)) {
            // If backend doesn't support license key auth for portal, provide helpful message
            $error_message = $result->get_error_message();
            if (strpos($error_message, 'Authentication required') !== false || 
                strpos($error_message, 'Unauthorized') !== false ||
                strpos($error_message, 'not_authenticated') !== false) {
                wp_send_json_error([
                    'message' => __('To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'opptiai-alt-text-generator'),
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
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'opptiai-alt-text-generator')
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
            'message' => __('Password reset link has been sent to your email. Please check your inbox and spam folder.', 'opptiai-alt-text-generator'),
        ];
        
        // Include reset link if provided (for development/testing when email service isn't configured)
        if (isset($result['resetLink'])) {
            $response_data['resetLink'] = $result['resetLink'];
            $response_data['note'] = $result['note'] ?? __('Email service is in development mode. Use this link to reset your password.', 'opptiai-alt-text-generator');
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Override plugin information to use our local readme.txt instead of fetching from WordPress.org
     * This prevents WordPress from showing wrong plugin details from repository
     */
    public function override_plugin_information($result, $action, $args) {
        // Get our plugin file path
        $plugin_file = OPPTIAI_ALT_PLUGIN_BASENAME;
        $plugin_slug = dirname($plugin_file);
        
        // Check if this request is for our plugin
        $is_our_plugin = false;
        if (isset($args->slug)) {
            // Check against various possible slugs
            $possible_slugs = [
                $plugin_slug,
                'opptiai-alt-text-generator',
                'seo-opptiai-alt-text-generator',
                'seo-opptiai-alt-text-generator-auto-image-seo-accessibility',
                'opptiai-alt-text-generator'
            ];
            $is_our_plugin = in_array($args->slug, $possible_slugs, true);
        }
        
        // If it's our plugin, return false to use local readme.txt
        // Returning false tells WordPress to use the local readme.txt file
        if ($is_our_plugin) {
            return false;
        }
        
        return $result;
    }
    
    /**
     * Filter plugin row meta to use our information
     */
    public function plugin_row_meta($plugin_meta, $plugin_file, $plugin_data, $status) {
        if ($plugin_file === OPPTIAI_ALT_PLUGIN_BASENAME) {
            // Ensure our plugin uses local data
            // WordPress will use readme.txt from the plugin directory
        }
        return $plugin_meta;
    }

    /**
     * AJAX handler: Reset password with token
     */
    public function ajax_reset_password() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'opptiai-alt-text-generator')
            ]);
        }

        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Reset token is required', 'opptiai-alt-text-generator')
            ]);
        }

        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long', 'opptiai-alt-text-generator')
            ]);
        }

        $result = $this->api_client->reset_password($email, $token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now sign in with your new password.', 'opptiai-alt-text-generator'),
            'redirect' => admin_url('upload.php?page=opptiai-alt&password_reset=success')
        ]);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view subscription information', 'opptiai-alt-text-generator'),
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
        check_ajax_referer('opptiai_alt_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt-text-generator')]);
        }

        $attachment_ids = isset($_POST['attachment_ids']) ? (array) $_POST['attachment_ids'] : [];
        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No attachment IDs provided.', 'opptiai-alt-text-generator')]);
        }

        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            wp_send_json_error(['message' => __('Invalid attachment IDs.', 'opptiai-alt-text-generator')]);
        }

        $results = [];
        foreach ($ids as $id) {
            if (!$this->is_image($id)) {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => __('Attachment is not an image.', 'opptiai-alt-text-generator'),
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

        AltText_AI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        wp_send_json_success([
            'results' => $results,
        ]);
    }
}

// Class instantiation moved to class-opptiai-alt-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Inline JS fallback to add row-action behaviour
add_action('admin_footer-upload.php', function(){
    ?>
    <script>
    (function($){
        function refreshDashboard(){
            if (!window.OPPTIAI_ALT || !OPPTIAI_ALT.restStats || !window.fetch){
                return;
            }

            var nonce = (OPPTIAI_ALT.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''));
            var headers = {
                'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
            };
            var statsUrl = OPPTIAI_ALT.restStats + (OPPTIAI_ALT.restStats.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';
            var usageUrl = OPPTIAI_ALT.restUsage || '';

            Promise.all([
                fetch(statsUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }),
                usageUrl ? fetch(usageUrl, { credentials: 'same-origin', headers: headers }).then(function(res){ return res.ok ? res.json() : null; }) : Promise.resolve(null)
            ])
            .then(function(results){
                var stats = results[0], usage = results[1];
                if (!stats){ return; }
                if (typeof window.dispatchEvent === 'function'){
                    try {
                        window.dispatchEvent(new CustomEvent('ai-alt-stats-update', { detail: { stats: stats, usage: usage } }));
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
                    var nonce = (OPPTIAI_ALT && OPPTIAI_ALT.nonce) ? OPPTIAI_ALT.nonce : (window.wpApiSettings ? wpApiSettings.nonce : '');
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
            return !!(window.OPPTIAI_ALT && OPPTIAI_ALT.canManage);
        }

        function handleLimitReachedNotice(payload){
            var message = (payload && payload.message) ? payload.message : 'Monthly limit reached. Please contact a site administrator.';
            pushNotice('warning', message);

            if (!canManageAccount()){
                return;
            }

            try {
                if (window.AltTextAI && AltTextAI.upgradeUrl) {
                    window.open(AltTextAI.upgradeUrl, '_blank');
                }
            } catch(e){}

            if (jQuery('.alttextai-upgrade-banner').length){
                jQuery('.alttextai-upgrade-banner .show-upgrade-modal').trigger('click');
            }
        }

        $(document).on('click', '.ai-alt-generate', function(e){
            e.preventDefault();
            if (!window.OPPTIAI_ALT || !OPPTIAI_ALT.rest){
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

            var headers = {'X-WP-Nonce': (OPPTIAI_ALT.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''))};
            var context = btn.closest('.compat-item, .attachment-details, .media-modal');

            fetch(OPPTIAI_ALT.rest + id, { method:'POST', headers: headers })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data && data.alt){
                        updateAltField(id, data.alt, context);
                        pushNotice('success', 'ALT generated: ' + data.alt);
                        if (!context.length){
                        location.reload();
                        }
                        refreshDashboard();
                    } else if (data && data.code === 'ai_alt_dry_run'){
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
