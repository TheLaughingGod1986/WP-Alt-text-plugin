<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('AI_ALT_GPT_PLUGIN_FILE')) {
    define('AI_ALT_GPT_PLUGIN_FILE', dirname(__FILE__, 2) . '/ai-alt-gpt.php');
}

if (!defined('AI_ALT_GPT_PLUGIN_DIR')) {
    define('AI_ALT_GPT_PLUGIN_DIR', plugin_dir_path(AI_ALT_GPT_PLUGIN_FILE));
}

if (!defined('AI_ALT_GPT_PLUGIN_URL')) {
    define('AI_ALT_GPT_PLUGIN_URL', plugin_dir_url(AI_ALT_GPT_PLUGIN_FILE));
}

if (!defined('AI_ALT_GPT_PLUGIN_BASENAME')) {
    define('AI_ALT_GPT_PLUGIN_BASENAME', plugin_basename(AI_ALT_GPT_PLUGIN_FILE));
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
require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once AI_ALT_GPT_PLUGIN_DIR . 'includes/class-queue.php';

class AI_Alt_Text_Generator_GPT {
    const OPTION_KEY = 'ai_alt_gpt_settings';
    const NONCE_KEY  = 'ai_alt_gpt_nonce';
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

    private function user_can_manage(){
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public function __construct() {
        // Use Phase 2 API client (JWT-based authentication)
        $this->api_client = new AltText_AI_API_Client_V2();
    }

    private function default_usage(){
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
            set_transient('ai_alt_gpt_token_notice', [
                'total' => $current['total'],
                'limit' => $opts['token_limit'],
            ], DAY_IN_SECONDS);
            $this->send_notification(
                __('AI Alt Text token usage alert', 'ai-alt-gpt'),
                sprintf(
                    __('Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'ai-alt-gpt'),
                    $current['total'],
                    $opts['token_limit']
                )
            );
        }

        update_option(self::OPTION_KEY, $opts, false);
        $this->stats_cache = null;
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
        $data = get_transient('ai_alt_gpt_token_notice');
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
        if ($page !== 'ai-alt-gpt-checkout') { return; }

        if (!$this->user_can_manage()) {
            wp_die(__('You do not have permission to perform this action.', 'ai-alt-gpt'));
        }

        $nonce = isset($_GET['_alttextai_nonce']) && $_GET['_alttextai_nonce'] !== null ? sanitize_text_field(wp_unslash($_GET['_alttextai_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'alttextai_direct_checkout')) {
            wp_die(__('Security check failed. Please try again from the dashboard.', 'ai-alt-gpt'));
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

        $success_url = admin_url('upload.php?page=ai-alt-gpt&checkout=success');
        $cancel_url  = admin_url('upload.php?page=ai-alt-gpt&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result) || empty($result['url'])) {
            $message = is_wp_error($result) ? $result->get_error_message() : __('Unable to start checkout. Please try again.', 'ai-alt-gpt');
            $plan_param = sanitize_key($_GET['plan'] ?? $_GET['type'] ?? '');
            $query_args = [
                'page'            => 'ai-alt-gpt',
                'checkout_error'  => rawurlencode($message),
            ];
            if (!empty($plan_param)) {
                $query_args['plan'] = $plan_param;
            }
            $redirect = add_query_arg($query_args, admin_url('upload.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        // Use JavaScript instead of redirect for CSP compliance
        echo "<script>window.open(\"" . $result["url"] . "\", \"_blank\");</script>";
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

        $cached = get_transient('alttextai_remote_price_ids');
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
                    set_transient('alttextai_remote_price_ids', $remote, 10 * MINUTE_IN_SECONDS);
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
        $stored = get_option('alttextai_checkout_prices', []);
        if (is_array($stored) && !empty($stored)) {
            foreach ($stored as $key => $value) {
                $key = is_string($key) ? sanitize_key($key) : '';
                $value = is_string($value) ? sanitize_text_field($value) : '';
                if ($key && $value && empty($prices[$key])) {
                    $prices[$key] = $value;
                }
            }
        }

        $prices = apply_filters('alttextai_checkout_price_ids', $prices);
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
        return apply_filters('alttextai_checkout_price_id', $price_id, $plan, $prices);
    }

    /**
     * Surface checkout success/error notices in WP Admin
     */
    public function maybe_render_checkout_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'ai-alt-gpt') {
            return;
        }

        if (!empty($_GET['checkout_error'])) {
            $message = !empty($_GET['checkout_error']) && $_GET['checkout_error'] !== null ? sanitize_text_field(wp_unslash($_GET['checkout_error'])) : '';
            $plan    = !empty($_GET['plan']) && $_GET['plan'] !== null ? sanitize_text_field(wp_unslash($_GET['plan'])) : '';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Checkout Failed', 'ai-alt-gpt'); ?>:</strong>
                    <?php echo esc_html($message); ?>
                    <?php if ($plan) : ?>
                        (<?php echo esc_html(sprintf(__('Plan: %s', 'ai-alt-gpt'), $plan)); ?>)
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Verify your AltText AI account credentials and Stripe configuration, then try again.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        } elseif (!empty($_GET['checkout']) && $_GET['checkout'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Checkout session started. Complete the payment in the newly opened tab.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        } elseif (!empty($_GET['checkout']) && $_GET['checkout'] === 'cancel') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php esc_html_e('Checkout was cancelled. No changes were made to your plan.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        }

        // Password reset notices
        if (!empty($_GET['password_reset']) && $_GET['password_reset'] === 'requested') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Email Sent', 'ai-alt-gpt'); ?></strong></p>
                <p><?php esc_html_e('Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        }

        if (!empty($_GET['password_reset']) && $_GET['password_reset'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Successful', 'ai-alt-gpt'); ?></strong></p>
                <p><?php esc_html_e('Your password has been updated. You can now sign in with your new password.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        }

        // Subscription update notices
        if (!empty($_GET['subscription_updated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Subscription Updated', 'ai-alt-gpt'); ?></strong></p>
                <p><?php esc_html_e('Your subscription information has been refreshed.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        }

        if (!empty($_GET['portal_return']) && $_GET['portal_return'] === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Billing Updated', 'ai-alt-gpt'); ?></strong></p>
                <p><?php esc_html_e('Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'ai-alt-gpt'); ?></p>
            </div>
            <?php
        }
    }

    public function render_token_notice(){
        if (empty($this->token_notice)){
            return;
        }
        delete_transient('ai_alt_gpt_token_notice');
        $total = number_format_i18n($this->token_notice['total'] ?? 0);
        $limit = number_format_i18n($this->token_notice['limit'] ?? 0);
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(__('AI Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'ai-alt-gpt'), $total, $limit)) . '</p></div>';
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
            ? __('1 image queued for background optimisation. The alt text will appear shortly.', 'ai-alt-gpt')
            : sprintf(__('Queued %d images for background optimisation. Alt text will be generated shortly.', 'ai-alt-gpt'), $count);
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public function deactivate(){
        wp_clear_scheduled_hook(AltText_AI_Queue::CRON_HOOK);
    }

    public function activate() {
        global $wpdb;

        AltText_AI_Queue::create_table();
        AltText_AI_Queue::schedule_processing(10);

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
        delete_option('alttextai_jwt_token');
        delete_option('alttextai_user_data');
        delete_transient('alttextai_token_last_check');

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
            'ai-alt-gpt',
            [$this, 'render_settings_page']
        );

        // Hidden checkout redirect page
        add_submenu_page(
            null, // No parent = hidden from menu
            'Checkout',
            'Checkout',
            $cap,
            'ai-alt-gpt-checkout',
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

        $success_url = admin_url('upload.php?page=ai-alt-gpt&checkout=success');
        $cancel_url = admin_url('upload.php?page=ai-alt-gpt&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            wp_die('Checkout error: ' . $result->get_error_message());
        }

        if (!empty($result['url'])) {
            // Use JavaScript instead of redirect for CSP compliance
        echo "<script>window.open(\"" . $result["url"] . "\", \"_blank\");</script>";
        exit;
            exit;
        }

        wp_die('Failed to create checkout session.');
    }

    public function register_settings() {
        register_setting('ai_alt_gpt_group', self::OPTION_KEY, [
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
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        // Build tabs - hide settings if not authenticated
        $tabs = [
            'dashboard' => __('Dashboard', 'ai-alt-gpt'),
            'guide'     => __('How to', 'ai-alt-gpt'),
        ];
        
        // Only show settings tab if user is authenticated
        if ($this->api_client->is_authenticated()) {
            $tabs['settings'] = __('Settings', 'ai-alt-gpt');
        }
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ai_alt_usage_export'), 'ai_alt_usage_export');
        $audit_rows = $stats['audit'] ?? [];
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
                            <span class="alttextai-logo-text">AltText AI</span>
                            <span class="alttextai-logo-tagline">Auto Image SEO & Accessibility</span>
                        </div>
                    </div>
                    <?php if (!empty($tabs) && count($tabs) > 1) : ?>
                    <nav class="alttextai-nav">
                <?php foreach ($tabs as $slug => $label) :
                    $url = esc_url(add_query_arg(['tab' => $slug]));
                            $active = $tab === $slug ? ' active' : '';
                ?>
                            <a href="<?php echo $url; ?>" class="alttextai-nav-link<?php echo esc_attr($active); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                    <?php if ($this->api_client->is_authenticated()) : 
                        $user_info = $this->api_client->get_user_info();
                        $user_email = !empty($user_info['email']) ? $user_info['email'] : __('Logged In', 'ai-alt-gpt');
                    ?>
                    <!-- Logout Button (Appears on All Tabs) -->
                    <div class="alttextai-auth-badge">
                        <span class="alttextai-auth-badge__user"><?php echo esc_html($user_email); ?></span>
                        <button type="button" class="alttextai-auth-badge__logout" id="alttextai-logout-btn" title="<?php esc_attr_e('Logout', 'ai-alt-gpt'); ?>">
                            <?php esc_html_e('Logout', 'ai-alt-gpt'); ?>
                        </button>
                    </div>
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
                $coverage_value_text = sprintf(__('ALT coverage at %s', 'ai-alt-gpt'), $coverage_text);
            ?>

            <?php
                $checkout_nonce = wp_create_nonce('alttextai_direct_checkout');
                $checkout_base  = admin_url('admin.php');
                $price_ids      = $this->get_checkout_price_ids();

                $pro_plan  = [
                    'page'             => 'ai-alt-gpt-checkout',
                    'plan'             => 'pro',
                    'price_id'         => $price_ids['pro'] ?? '',
                    '_alttextai_nonce' => $checkout_nonce,
                ];
                $agency_plan = [
                    'page'             => 'ai-alt-gpt-checkout',
                    'plan'             => 'agency',
                    'price_id'         => $price_ids['agency'] ?? '',
                    '_alttextai_nonce' => $checkout_nonce,
                ];
                $credits_plan = [
                    'page'             => 'ai-alt-gpt-checkout',
                    'type'             => 'credits',
                    'price_id'         => $price_ids['credits'] ?? '',
                    '_alttextai_nonce' => $checkout_nonce,
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
                
                // If stats show 0 but we have API data, use API data directly
                if (isset($live_usage) && is_array($live_usage) && !empty($live_usage) && !is_wp_error($live_usage)) {
                    if (($usage_stats['used'] ?? 0) == 0 && ($live_usage['used'] ?? 0) > 0) {
                        // Cache hasn't updated yet, use API data directly
                        $usage_stats['used'] = max(0, intval($live_usage['used'] ?? 0));
                        $usage_stats['limit'] = max(1, intval($live_usage['limit'] ?? 50));
                        $usage_stats['remaining'] = max(0, intval($live_usage['remaining'] ?? 50));
                        // Recalculate percentage
                        $usage_stats['percentage'] = $usage_stats['limit'] > 0 ? round(($usage_stats['used'] / $usage_stats['limit']) * 100) : 0;
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
                $percentage = $dashboard_limit > 0 ? round(($dashboard_used / $dashboard_limit) * 100) : 0;
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
                                 <?php esc_html_e('🎉 Boost Your Site\'s SEO Automatically', 'ai-alt-gpt'); ?>
                             </h2>
                             <p class="alttextai-hero-subtitle">
                                 <?php esc_html_e('Let AI write perfect, accessibility-friendly alt text for every image — free each month.', 'ai-alt-gpt'); ?>
                             </p>
                         </div>
                         <div class="alttextai-hero-actions">
                             <button type="button" class="alttextai-hero-btn-primary" id="alttextai-show-auth-banner-btn">
                                <?php esc_html_e('Start Free — Generate 50 AI Descriptions', 'ai-alt-gpt'); ?>
                             </button>
                             <button type="button" class="alttextai-hero-link-secondary" id="alttextai-show-auth-login-btn">
                                 <?php esc_html_e('Already a user? Log in', 'ai-alt-gpt'); ?>
                             </button>
                         </div>
                         <div class="alttextai-hero-micro-copy">
                             <?php esc_html_e('⚡ SEO Boost · 🦾 Accessibility · 🕒 Saves Hours', 'ai-alt-gpt'); ?>
                         </div>
                     </div>
                     <?php endif; ?>
                     <!-- Note: Auth badge now appears above tabs for consistency across all pages -->

                    <?php if ($this->api_client->is_authenticated()) : ?>
                    <?php
                        $plan_label = $usage_stats['plan_label'] ?? __('Free', 'ai-alt-gpt');
                        $billing_portal = AltText_AI_Usage_Tracker::get_billing_portal_url();
                        if (empty($billing_portal)) {
                            $billing_portal = AltText_AI_Usage_Tracker::get_upgrade_url();
                        }
                        $manage_billing_url = apply_filters('alttextai_manage_billing_url', $billing_portal);
                        $upgrade_url = AltText_AI_Usage_Tracker::get_upgrade_url();
                    ?>
                    <div class="alttextai-plan-banner <?php echo $usage_stats['is_free'] ? 'alttextai-plan-banner--free' : 'alttextai-plan-banner--pro'; ?>">
                        <div class="alttextai-plan-banner__copy">
                            <?php if (!empty($usage_stats['is_free'])) : ?>
                                <span class="alttextai-plan-badge"><?php esc_html_e('Current Plan', 'ai-alt-gpt'); ?></span>
                                <strong><?php printf(esc_html__('Free — %d images/month', 'ai-alt-gpt'), $usage_stats['limit']); ?></strong>
                            <?php else : ?>
                                <span class="alttextai-plan-badge alttextai-plan-badge--pro"><?php esc_html_e('Pro Plan', 'ai-alt-gpt'); ?></span>
                                <strong><?php printf(esc_html__('%1$s — %2$d images/month, renews %3$s', 'ai-alt-gpt'), esc_html($plan_label), $usage_stats['limit'], esc_html($usage_stats['reset_date'] ?? '')); ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="alttextai-plan-banner__actions">
                            <?php if (!empty($usage_stats['is_free'])) : ?>
                                <button type="button" class="alttextai-plan-upgrade" data-action="show-upgrade-modal" data-upgrade-source="plan-banner"><?php esc_html_e('Upgrade →', 'ai-alt-gpt'); ?></button>
                            <?php else : ?>
                                <?php if (!empty($billing_portal) && $manage_billing_url === $billing_portal) : ?>
                                    <a href="#billing-portal" class="alttextai-plan-manage" data-action="open-billing-portal"><?php esc_html_e('Manage Billing', 'ai-alt-gpt'); ?></a>
                                <?php elseif (!empty($manage_billing_url)) : ?>
                                    <a href="<?php echo esc_url($manage_billing_url); ?>" target="_blank" rel="noopener noreferrer" class="alttextai-plan-manage"><?php esc_html_e('Manage Billing', 'ai-alt-gpt'); ?></a>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" rel="noopener noreferrer" class="alttextai-plan-manage"><?php esc_html_e('Upgrade Options', 'ai-alt-gpt'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Dashboard Container -->
                    <div class="alttextai-dashboard-container">
                        <!-- Header Section -->
                        <div class="alttextai-dashboard-header">
                            <div class="alttextai-dashboard-header-icon">
                                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="48" height="48" rx="12" fill="url(#dashboard-gradient)"/>
                                    <path d="M24 8L16 16V32L24 40L32 32V16L24 8Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M24 8V40M16 16L32 32M32 16L16 32" stroke="white" stroke-width="2" opacity="0.5"/>
                                    <defs>
                                        <linearGradient id="dashboard-gradient" x1="0" y1="0" x2="48" y2="48">
                                            <stop stop-color="#8b5cf6"/>
                                            <stop offset="1" stop-color="#a855f7"/>
                                        </linearGradient>
                                    </defs>
                                </svg>
                            </div>
                            <h1 class="alttextai-dashboard-title"><?php esc_html_e('Dashboard', 'ai-alt-gpt'); ?></h1>
                            <p class="alttextai-dashboard-subtitle"><?php esc_html_e('Generate perfect SEO alt text automatically', 'ai-alt-gpt'); ?></p>
                        </div>

                        <?php if ($this->api_client->is_authenticated()) : ?>
                        <!-- Usage Card -->
                        <div class="alttextai-dashboard-card alttextai-dashboard-card--featured">
                            <div class="alttextai-dashboard-card-header">
                                <div class="alttextai-dashboard-card-badge"><?php esc_html_e('USAGE STATUS', 'ai-alt-gpt'); ?></div>
                                <h2 class="alttextai-dashboard-card-title">
                                    <span class="alttextai-dashboard-emoji">📊</span>
                                    <?php printf(esc_html__('%1$d of %2$d generations used', 'ai-alt-gpt'), $usage_stats['used'], $usage_stats['limit']); ?>
                                </h2>
                            </div>
                            
                            <div class="alttextai-dashboard-usage-bar">
                                <div class="alttextai-dashboard-usage-bar-fill" style="width: <?php echo esc_attr($usage_stats['percentage']); ?>%;"></div>
                            </div>
                            
                            <div class="alttextai-dashboard-usage-stats">
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Used', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($usage_stats['used']); ?></span>
                                </div>
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($usage_stats['remaining']); ?></span>
                                </div>
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Resets', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($usage_stats['reset_date'] ?? ''); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($usage_stats['remaining'] <= 0) : ?>
                            <div class="alttextai-dashboard-upgrade-prompt">
                                <p><?php esc_html_e('🎯 You\'ve reached your free limit — upgrade for more AI power!', 'ai-alt-gpt'); ?></p>
                                <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-upgrade-modal" data-upgrade-source="usage-limit">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></span>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Time Saved Section -->
                        <?php
                        // Calculate time saved metrics
                        $alt_texts_generated = $usage_stats['used'] ?? 0;
                        $minutes_per_alt_text = 2.5; // Average time to manually create an alt text
                        $hours_saved = round(($alt_texts_generated * $minutes_per_alt_text) / 60, 1);
                        $remaining_images = $stats['missing'] ?? 0;
                        $estimated_hours_for_remaining = round(($remaining_images * $minutes_per_alt_text) / 60, 1);
                        ?>
                        
                        <?php if ($alt_texts_generated > 0 || $remaining_images > 0) : ?>
                        <div class="alttextai-dashboard-card alttextai-time-saved-card">
                            <div class="alttextai-dashboard-card-header">
                                <div class="alttextai-dashboard-card-badge"><?php esc_html_e('TIME SAVED', 'ai-alt-gpt'); ?></div>
                                <?php if ($alt_texts_generated > 0) : ?>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">⏱️</span>
                                        <?php 
                                        printf(
                                            esc_html__('%1$d alt text%2$s added has saved you %3$s hour%4$s', 'ai-alt-gpt'),
                                            $alt_texts_generated,
                                            $alt_texts_generated === 1 ? '' : 's',
                                            $hours_saved,
                                            $hours_saved == 1 ? '' : 's'
                                        );
                                        ?>
                                    </h2>
                                    <p class="alttextai-seo-impact"><?php esc_html_e('✨ This has had a positive SEO impact on your website', 'ai-alt-gpt'); ?></p>
                                <?php else : ?>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('Ready to optimize your images', 'ai-alt-gpt'); ?>
                                    </h2>
                                    <p class="alttextai-seo-impact"><?php esc_html_e('Start generating alt text to improve SEO and accessibility', 'ai-alt-gpt'); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($remaining_images > 0) : ?>
                            <div class="alttextai-remaining-optimization">
                                <div class="alttextai-remaining-info">
                                    <span class="alttextai-remaining-label"><?php esc_html_e('Images left to optimize:', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-remaining-count"><?php echo esc_html($remaining_images); ?></span>
                                </div>
                                <div class="alttextai-hours-estimate">
                                    <span class="alttextai-estimate-label"><?php esc_html_e('Estimated time saved:', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-estimate-value"><?php 
                                        printf(
                                            esc_html__('%1$s hour%2$s', 'ai-alt-gpt'),
                                            $estimated_hours_for_remaining,
                                            $estimated_hours_for_remaining == 1 ? '' : 's'
                                        );
                                    ?></span>
                                </div>
                            </div>
                            <?php elseif ($alt_texts_generated > 0) : ?>
                            <div class="alttextai-all-optimized">
                                <p class="alttextai-optimized-message"><?php esc_html_e('🎉 All images are optimized! Great job!', 'ai-alt-gpt'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    <!-- Optimization Progress -->
                    <?php
                    $total_images = $stats['total'] ?? 0;
                    $optimized = $stats['with_alt'] ?? 0;
                    $remaining = $stats['missing'] ?? 0;
                    ?>
                    <div class="alttextai-dashboard-card">
                        <div class="alttextai-dashboard-card-header">
                            <div class="alttextai-dashboard-card-badge"><?php esc_html_e('IMAGE OPTIMIZATION', 'ai-alt-gpt'); ?></div>
                            <h2 class="alttextai-dashboard-card-title">
                                <span class="alttextai-dashboard-emoji">📊</span>
                                <?php 
                                if ($total_images > 0) {
                                    if ($remaining > 0) {
                                        printf(
                                            esc_html__('%1$d of %2$d images optimized', 'ai-alt-gpt'),
                                            $optimized,
                                            $total_images
                                        );
                                    } else {
                                        printf(
                                            esc_html__('All %1$d images optimized! 🎉', 'ai-alt-gpt'),
                                            $total_images
                                        );
                                    }
                                } else {
                                    esc_html_e('Ready to optimize images', 'ai-alt-gpt');
                                }
                                ?>
                            </h2>
                        </div>
                        
                        <?php if ($total_images > 0) : ?>
                            <div class="alttextai-dashboard-usage-bar">
                                <div class="alttextai-dashboard-usage-bar-fill" style="width: <?php echo esc_attr($stats['coverage'] ?? 0); ?>%;"></div>
                            </div>
                            
                            <div class="alttextai-dashboard-usage-stats">
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Optimized', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($optimized); ?></span>
                                </div>
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($remaining); ?></span>
                                </div>
                                <div class="alttextai-dashboard-usage-stat">
                                    <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Total', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-dashboard-usage-value"><?php echo esc_html($total_images); ?></span>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="alttextai-empty-state">
                                <p class="alttextai-empty-state-text"><?php esc_html_e('Upload images to your WordPress Media Library to get started with AI-powered alt text generation.', 'ai-alt-gpt'); ?></p>
                                <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="alttextai-btn-primary alttextai-btn-icon">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Go to Media Library', 'ai-alt-gpt'); ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    </div> <!-- End Dashboard Container -->
                    <?php else : ?>
                        <!-- Not Authenticated - Demo Preview (Using Real Dashboard Structure) -->
                        <div class="alttextai-demo-preview">
                            <!-- Demo Badge Overlay -->
                            <div class="alttextai-demo-badge-overlay">
                                <span class="alttextai-demo-badge-text"><?php esc_html_e('DEMO PREVIEW', 'ai-alt-gpt'); ?></span>
                            </div>
                            
                            <!-- Usage Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-dashboard-card--featured alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('USAGE STATUS', 'ai-alt-gpt'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('0 of 50 generations used', 'ai-alt-gpt'); ?>
                                    </h2>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-bar">
                                    <div class="alttextai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-stats">
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Used', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">50</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Resets', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value"><?php echo esc_html(date_i18n('F j, Y', strtotime('first day of next month'))); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time Saved Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-time-saved-card alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('TIME SAVED', 'ai-alt-gpt'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">⏱️</span>
                                        <?php esc_html_e('Ready to optimize your images', 'ai-alt-gpt'); ?>
                                    </h2>
                                    <p class="alttextai-seo-impact"><?php esc_html_e('✨ Start generating alt text to improve SEO and accessibility', 'ai-alt-gpt'); ?></p>
                                </div>
                            </div>

                            <!-- Image Optimization Card (Demo) -->
                            <div class="alttextai-dashboard-card alttextai-demo-mode">
                                <div class="alttextai-dashboard-card-header">
                                    <div class="alttextai-dashboard-card-badge"><?php esc_html_e('IMAGE OPTIMIZATION', 'ai-alt-gpt'); ?></div>
                                    <h2 class="alttextai-dashboard-card-title">
                                        <span class="alttextai-dashboard-emoji">📊</span>
                                        <?php esc_html_e('Ready to optimize images', 'ai-alt-gpt'); ?>
                                    </h2>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-bar">
                                    <div class="alttextai-dashboard-usage-bar-fill" style="width: 0%;"></div>
                                </div>
                                
                                <div class="alttextai-dashboard-usage-stats">
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Optimized', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">0</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Remaining', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">—</span>
                                    </div>
                                    <div class="alttextai-dashboard-usage-stat">
                                        <span class="alttextai-dashboard-usage-label"><?php esc_html_e('Total', 'ai-alt-gpt'); ?></span>
                                        <span class="alttextai-dashboard-usage-value">—</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Demo CTA -->
                            <div class="alttextai-demo-cta">
                                <p class="alttextai-demo-cta-text"><?php esc_html_e('✨ Sign up now to start generating alt text for your images!', 'ai-alt-gpt'); ?></p>
                                <button type="button" class="alttextai-btn-primary alttextai-btn-icon" id="alttextai-demo-signup-btn">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Get Started Free', 'ai-alt-gpt'); ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endif; // End is_authenticated check for usage/stats cards ?>

                    <?php if ($this->api_client->is_authenticated()) : ?>
                    <!-- Main CTA Buttons -->
                    <div class="alttextai-main-cta">
                        <?php
                        $remaining_images = $stats['missing'] ?? 0;
                        $total_images = $stats['total'] ?? 0;

                        // Show CTAs only when there are images
                        if ($total_images > 0) :
                            if ($usage_stats['remaining'] <= 0) : ?>
                                <!-- Upgrade CTA when out of credits -->
                                <div class="alttextai-upgrade-cta-card">
                                    <div class="alttextai-upgrade-cta-content">
                                        <div class="alttextai-upgrade-cta-icon">⚡</div>
                                        <div class="alttextai-upgrade-cta-text">
                                            <h3><?php printf(esc_html__('%d images waiting for alt text', 'ai-alt-gpt'), $remaining_images); ?></h3>
                                            <p><?php esc_html_e('Upgrade to Pro to generate alt text for all your images', 'ai-alt-gpt'); ?></p>
                                        </div>
                                        <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-upgrade-modal" data-upgrade-source="bulk-limit">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                            </svg>
                                            <span><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <!-- Generate Missing Alt Text Button -->
                                <button type="button" class="alttextai-btn-primary alttextai-btn-icon alttextai-main-action-btn" data-action="generate-missing" <?php echo $remaining_images <= 0 ? 'disabled' : ''; ?>>
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php 
                                        if ($remaining_images > 0) {
                                            printf(esc_html__('Generate Alt Text for %d Images', 'ai-alt-gpt'), $remaining_images);
                                        } else {
                                            esc_html_e('All Images Have Alt Text', 'ai-alt-gpt');
                                        }
                                    ?></span>
                                </button>

                                <!-- Secondary: Regenerate All -->
                                <?php if ($optimized > 0) : ?>
                                <button type="button" class="alttextai-btn-secondary alttextai-btn-icon" data-action="regenerate-all" title="<?php esc_attr_e('Regenerate alt text for all images (replaces existing)', 'ai-alt-gpt'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 1v4M8 11v4M3 8h4M9 8h4M4.5 4.5L7 7M9 7l2.5-2.5M4.5 11.5L7 9M9 9l2.5 2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                    <span><?php printf(esc_html__('Regenerate All (%d)', 'ai-alt-gpt'), $total_images); ?></span>
                                </button>
                                <?php endif; ?>

                                <?php if ($remaining_images > 0) : ?>
                                <p class="alttextai-action-hint">
                                    <?php esc_html_e('This will generate alt text only for images that don\'t have any yet.', 'ai-alt-gpt'); ?>
                                </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                                            <div class="alttextai-queue-card shadow-sm hover:shadow-md transition-shadow rounded-2xl bg-white p-6 mt-8">
                            <div class="alttextai-queue-header">
                                <div>
                                    <h2 class="alttextai-queue-title"><?php esc_html_e('Background Queue', 'ai-alt-gpt'); ?></h2>
                                    <p class="alttextai-queue-subtitle"><?php esc_html_e('Monitor queued jobs and retry failures without leaving the dashboard.', 'ai-alt-gpt'); ?></p>
                                </div>
                                <div class="alttextai-queue-actions">
                                    <button type="button" class="alttextai-queue-btn" data-action="refresh-queue"><?php esc_html_e('Refresh', 'ai-alt-gpt'); ?></button>
                                    <button type="button" class="alttextai-queue-btn alttextai-queue-btn--secondary" data-action="retry-failed"><?php esc_html_e('Retry Failed', 'ai-alt-gpt'); ?></button>
                                    <button type="button" class="alttextai-queue-btn alttextai-queue-btn--ghost" data-action="clear-completed"><?php esc_html_e('Clear Completed', 'ai-alt-gpt'); ?></button>
                                </div>
                            </div>
                            <div class="alttextai-queue-stats" data-queue-stats>
                                <div class="alttextai-queue-stat">
                                    <span class="alttextai-queue-stat__label"><?php esc_html_e('Pending', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-queue-stat__value" data-queue-pending>0</span>
                                </div>
                                <div class="alttextai-queue-stat">
                                    <span class="alttextai-queue-stat__label"><?php esc_html_e('Processing', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-queue-stat__value" data-queue-processing>0</span>
                                </div>
                                <div class="alttextai-queue-stat">
                                    <span class="alttextai-queue-stat__label"><?php esc_html_e('Failed', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-queue-stat__value alttextai-queue-stat__value--failed" data-queue-failed>0</span>
                                </div>
                                <div class="alttextai-queue-stat">
                                    <span class="alttextai-queue-stat__label"><?php esc_html_e('Completed (24h)', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-queue-stat__value" data-queue-completed>0</span>
                                </div>
                            </div>
                            <div class="alttextai-queue-recent">
                                <h3 class="alttextai-queue-recent__title"><?php esc_html_e('Recent activity', 'ai-alt-gpt'); ?></h3>
                                <div class="alttextai-queue-recent__list" data-queue-recent>
                                    <p class="alttextai-queue-empty"><?php esc_html_e('Jobs will appear here when the queue runs.', 'ai-alt-gpt'); ?></p>
                                </div>
                            </div>
                            <div class="alttextai-queue-failures" data-queue-failures-wrapper style="display:none;">
                                <h3 class="alttextai-queue-failures__title"><?php esc_html_e('Needs attention', 'ai-alt-gpt'); ?></h3>
                                <div class="alttextai-queue-failures__list" data-queue-failures></div>
                            </div>
                        </div>

<div class="alttextai-post-optimize-banner hidden" data-post-optimize-banner>
                        <div class="alttextai-post-optimize-banner__content">
                            <p><?php esc_html_e('🎉 All your images are SEO-ready! Unlock keyword optimization with AltText AI Pro.', 'ai-alt-gpt'); ?></p>
                            <button type="button" class="alttextai-post-optimize-btn" data-action="show-upgrade-modal" data-upgrade-source="post-optimize"><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></button>
                        </div>
                    </div>

                        <?php if ($usage_stats['remaining'] > 0) : ?>
                            <!-- Progress Bar (Hidden by default) -->
                            <div class="ai-alt-bulk-progress" data-bulk-progress hidden>
                                <div class="ai-alt-bulk-progress__header">
                                    <span class="ai-alt-bulk-progress__label" data-bulk-progress-label><?php esc_html_e('Preparing bulk run…', 'ai-alt-gpt'); ?></span>
                                    <span class="ai-alt-bulk-progress__counts" data-bulk-progress-counts></span>
                                </div>
                                <div class="ai-alt-bulk-progress__bar"><span data-bulk-progress-bar></span></div>
                            </div>
                        <?php endif; ?>

                    <?php if ($usage_stats['remaining'] <= 0) : ?>
                        <div class="alttextai-limit-reached">
                            <div class="alttextai-limit-header">
                                <div class="alttextai-limit-icon">⚡</div>
                                <div class="alttextai-limit-content">
                                    <h3><?php esc_html_e('🎯 You’ve reached your free limit — upgrade for more AI power!', 'ai-alt-gpt'); ?></h3>
                                    <p class="alttextai-limit-description">
                                        <?php
                                            $reset_label = $usage_stats['reset_date'] ?? '';
                                            if (!empty($usage_stats['days_until_reset']) && $usage_stats['days_until_reset'] > 0) {
                                                printf(
                                                    esc_html__('You\'ve used all %1$d free generations this month. Your allowance resets in %2$d days (%3$s).', 'ai-alt-gpt'),
                                                    $usage_stats['limit'],
                                                    intval($usage_stats['days_until_reset']),
                                                    esc_html($reset_label)
                                                );
                                            } else {
                                                printf(
                                                    esc_html__('You\'ve used all %1$d free generations this month. Your allowance resets on %2$s.', 'ai-alt-gpt'),
                                                    $usage_stats['limit'],
                                                    esc_html($reset_label)
                                                );
                                            }
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <div class="alttextai-countdown" data-countdown="<?php echo esc_attr($usage_stats['seconds_until_reset'] ?? 0); ?>">
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-days>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('Days', 'ai-alt-gpt'); ?></div>
                                </div>
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-hours>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('Hours', 'ai-alt-gpt'); ?></div>
                                </div>
                                <div class="alttextai-countdown-item">
                                    <div class="alttextai-countdown-number" data-minutes>0</div>
                                    <div class="alttextai-countdown-label"><?php esc_html_e('Minutes', 'ai-alt-gpt'); ?></div>
                                </div>
                            </div>

                            <div class="alttextai-limit-cta">
                                <button type="button" class="alttextai-pricing-btn alttextai-pricing-btn--primary" data-action="show-upgrade-modal" data-upgrade-source="upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div> <!-- End Main CTA -->
                    <?php endif; // End is_authenticated check for main CTA buttons ?>

                    <?php if ($this->api_client->is_authenticated()) : ?>
                <!-- ALT Library Table -->
                <?php
                // Pagination setup
                $per_page = 10;
                $current_page = isset($_GET['alt_page']) ? max(1, intval($_GET['alt_page'])) : 1;
                $offset = ($current_page - 1) * $per_page;
                
                // Use a direct database query to get images with alt text
                global $wpdb;
                
                // First get total count
                $total_count = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT p.ID)
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND p.post_status = 'inherit'
                    AND pm.meta_key = '_wp_attachment_image_alt'
                    AND pm.meta_value != ''
                "));
                
                // Get paginated results
                $optimized_images = $wpdb->get_results($wpdb->prepare("
                    SELECT p.*, pm.meta_value as alt_text
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND p.post_status = 'inherit'
                    AND pm.meta_key = '_wp_attachment_image_alt'
                    AND pm.meta_value != ''
                    ORDER BY p.post_date DESC
                    LIMIT %d OFFSET %d
                ", $per_page, $offset));

                // If no optimized images, get recent images (with or without alt text)
                if (empty($optimized_images)) {
                    $recent_images_query = new \WP_Query([
                        'post_type' => 'attachment',
                        'post_mime_type' => 'image',
                        'posts_per_page' => $per_page,
                        'paged' => $current_page,
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ]);
                    $optimized_images = $recent_images_query->posts;
                    $total_count = $recent_images_query->found_posts;
                }

                $image_count = count($optimized_images);
                $total_pages = ceil($total_count / $per_page);
                
                // Use the same count as the progress bar for consistency
                $display_count = $stats['with_alt'] ?? $total_count;
                
                ?>
                <div class="alttextai-table-container">
                    <h2 class="alttextai-table-title"><?php esc_html_e('ALT Library', 'ai-alt-gpt'); ?> <small style="font-size: 0.875rem; font-weight: normal; color: #6b7280;">(<?php echo $display_count; ?> optimized images)</small></h2>
                    
                    <table class="alttextai-table shadow-sm">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Image', 'ai-alt-gpt'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-alt-gpt'); ?></th>
                                <th><?php esc_html_e('Previous Alt Text', 'ai-alt-gpt'); ?></th>
                                <th><?php esc_html_e('AI Alt Text', 'ai-alt-gpt'); ?></th>
                                <th><?php esc_html_e('Regenerate', 'ai-alt-gpt'); ?></th>
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
                                    $status_label = $has_alt ? __('✅ Optimized', 'ai-alt-gpt') : __('🟠 Missing', 'ai-alt-gpt');
                                    if ($has_alt && $clean_old_alt && strcasecmp($clean_old_alt, $clean_current_alt) !== 0) {
                                        $status_key = 'regenerated';
                                        $status_label = __('🔁 Regenerated', 'ai-alt-gpt');
                                    }

                                    $row_class = ($row_index % 2 === 0) ? 'bg-white' : 'bg-gray-50';
                                    $row_index++;
                                    ?>
                                    <tr class="alttextai-table-row <?php echo esc_attr($row_class); ?> hover:bg-gray-100 transition-colors" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" data-status="<?php echo esc_attr($status_key); ?>">
                                        <td class="alttextai-table__cell alttextai-table__cell--media">
                                            <div class="alttextai-media-cell">
                                                <div class="alttextai-thumb-wrapper">
                                                    <?php if ($thumb_url) : ?>
                                                        <img src="<?php echo esc_url($thumb_url[0]); ?>" alt="<?php echo esc_attr($attachment_title); ?>" class="alttextai-thumb" />
                                                    <?php else : ?>
                                                        <div class="alttextai-thumb alttextai-thumb--placeholder">
                                                            <?php esc_html_e('No image', 'ai-alt-gpt'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="alttextai-media-meta">
                                                    <span class="alttextai-media-title"><?php echo esc_html($attachment_title ?: sprintf(__('Image #%d', 'ai-alt-gpt'), $attachment_id)); ?></span>
                                                    <?php if ($edit_link) : ?>
                                                        <a href="<?php echo esc_url($edit_link); ?>" class="alttextai-media-link" target="_blank" rel="noopener noreferrer">
                                                            <?php esc_html_e('Open in Media Library', 'ai-alt-gpt'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="alttextai-table__cell alttextai-table__cell--status">
                                            <span class="alttextai-status-badge alttextai-status-badge--<?php echo esc_attr($status_key); ?>">
                                                <?php echo esc_html($status_label); ?>
                                            </span>
                                        </td>
                                        <td class="alttextai-table__cell alttextai-table__cell--old">
                                            <?php if ($clean_old_alt) : ?>
                                                <span class="alttextai-alt-text"><?php echo esc_html($clean_old_alt); ?></span>
                                            <?php else : ?>
                                                <span class="text-muted"><?php esc_html_e('No previous alt text', 'ai-alt-gpt'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="alttextai-table__cell alttextai-table__cell--new new-alt-cell-<?php echo esc_attr($attachment_id); ?>">
                                            <?php if ($has_alt) : ?>
                                                <button type="button" class="alttextai-copy-trigger" data-copy-alt="<?php echo esc_attr($clean_current_alt); ?>" aria-label="<?php esc_attr_e('Copy alt text to clipboard', 'ai-alt-gpt'); ?>">
                                                    <span class="alttextai-copy-text"><?php echo esc_html($clean_current_alt); ?></span>
                                                    <span class="alttextai-copy-icon" aria-hidden="true">📋</span>
                                                </button>
                                            <?php else : ?>
                                                <span class="text-muted"><?php esc_html_e('No alt text yet', 'ai-alt-gpt'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="alttextai-table__cell alttextai-table__cell--action">
                                            <button type="button" class="alttextai-btn-regenerate" data-action="regenerate-single" data-attachment-id="<?php echo esc_attr($attachment_id); ?>" <?php echo !$this->api_client->is_authenticated() ? 'disabled title="' . esc_attr__('Please login to regenerate alt text', 'ai-alt-gpt') . '"' : ''; ?>>
                                                <?php esc_html_e('Regenerate', 'ai-alt-gpt'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 2rem; color: #6b7280;">
                                        <?php esc_html_e('No images found. Upload some images to get started!', 'ai-alt-gpt'); ?>
                                        <br><small style="margin-top: 0.5rem; display: block;">
                                            <?php esc_html_e('Once you upload images, they will appear here with their alt text and regenerate options.', 'ai-alt-gpt'); ?>
                                        </small>
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
                                    esc_html__('Showing %1$d-%2$d of %3$d images', 'ai-alt-gpt'),
                                    $start,
                                    $end,
                                    $total_count
                                );
                                ?>
                            </div>
                            
                            <div class="alttextai-pagination-controls">
                                <?php if ($current_page > 1) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--first" title="<?php esc_attr_e('First page', 'ai-alt-gpt'); ?>">
                                        <?php esc_html_e('First', 'ai-alt-gpt'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page - 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--prev" title="<?php esc_attr_e('Previous page', 'ai-alt-gpt'); ?>">
                                        <?php esc_html_e('Previous', 'ai-alt-gpt'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('First', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Previous', 'ai-alt-gpt'); ?></span>
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
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $current_page + 1)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--next" title="<?php esc_attr_e('Next page', 'ai-alt-gpt'); ?>">
                                        <?php esc_html_e('Next', 'ai-alt-gpt'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg('alt_page', $total_pages)); ?>" class="alttextai-pagination-btn alttextai-pagination-btn--last" title="<?php esc_attr_e('Last page', 'ai-alt-gpt'); ?>">
                                        <?php esc_html_e('Last', 'ai-alt-gpt'); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Next', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-pagination-btn alttextai-pagination-btn--disabled"><?php esc_html_e('Last', 'ai-alt-gpt'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div> <!-- End ALT Library -->
                    <?php endif; // End is_authenticated check for ALT Library ?>
                
                    <?php if ($this->api_client->is_authenticated()) : ?>
                    <!-- Upgrade Footer -->
                    <div class="alttextai-footer">
                        <a href="#" class="alttextai-upgrade-link" data-action="show-upgrade-modal" data-upgrade-source="footer">
                            <?php esc_html_e('Upgrade to Pro for unlimited AI generations', 'ai-alt-gpt'); ?> →
                        </a>
                    </div>
                    <?php endif; ?>
                
                <!-- Status for AJAX operations -->
                <div class="ai-alt-dashboard__status" data-progress-status role="status" aria-live="polite" style="display: none;"></div>
                
                <!-- Detailed Progress Log -->
                <div id="ai-alt-bulk-progress" class="alttextai-progress-log" style="display: none;">
                    <div class="alttextai-progress-header">
                        <h3><?php esc_html_e('Bulk Optimization Progress', 'ai-alt-gpt'); ?></h3>
                        <button type="button" class="alttextai-progress-close" aria-label="<?php esc_attr_e('Close progress log', 'ai-alt-gpt'); ?>">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 8.707l3.646 3.647.708-.707L8.707 8l3.647-3.646-.707-.708L8 7.293 4.354 3.646l-.707.708L7.293 8l-3.646 3.646.707.708L8 8.707z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alttextai-progress-stats">
                        <div class="alttextai-progress-stat">
                            <span class="alttextai-progress-label"><?php esc_html_e('Progress', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-progress-percentage">0%</span>
                        </div>
                        <div class="alttextai-progress-stat">
                            <span class="alttextai-progress-label"><?php esc_html_e('Processed', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-progress-count">0 / 0</span>
                        </div>
                        <div class="alttextai-progress-stat">
                            <span class="alttextai-progress-label"><?php esc_html_e('Success', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-progress-success">0</span>
                        </div>
                        <div class="alttextai-progress-stat">
                            <span class="alttextai-progress-label"><?php esc_html_e('Errors', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-progress-errors">0</span>
                        </div>
                    </div>
                    <div class="alttextai-progress-bar-container">
                        <div class="alttextai-progress-bar">
                            <div class="alttextai-progress-bar-fill"></div>
                        </div>
                    </div>
                    <div class="alttextai-progress-log-container">
                        <div class="alttextai-progress-log-content">
                            <div class="alttextai-progress-log-entry alttextai-progress-log-entry--info">
                                <span class="alttextai-progress-log-time"><?php echo esc_html(current_time('H:i:s')); ?></span>
                                <span class="alttextai-progress-log-message"><?php esc_html_e('Starting bulk optimization...', 'ai-alt-gpt'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="alttextai-progress-actions">
                        <button type="button" class="alttextai-progress-cancel" disabled>
                            <?php esc_html_e('Cancel Operation', 'ai-alt-gpt'); ?>
                        </button>
                    </div>
                </div>

            </div>
            <?php elseif ($tab === 'usage') : ?>
            <?php $audit_rows = $stats['audit'] ?? []; $export_url = wp_nonce_url(admin_url('admin-post.php?action=ai_alt_usage_export'), 'ai_alt_usage_export'); ?>
            <div class="ai-alt-dashboard ai-alt-dashboard--usage" data-stats='<?php echo esc_attr(wp_json_encode($stats)); ?>'>
                <div class="ai-alt-dashboard__intro ai-alt-usage__intro">
                    <h2 id="ai-alt-usage-heading"><?php esc_html_e('Usage snapshot', 'ai-alt-gpt'); ?></h2>
                    <p><?php esc_html_e('This tab highlights recent activity and token spend. For a full review and regeneration workflow, head to the ALT Library.', 'ai-alt-gpt'); ?></p>
                </div>
                <div class="ai-alt-dashboard__microcards ai-alt-usage__grid" role="group" aria-labelledby="ai-alt-usage-heading">
                    <div class="ai-alt-microcard ai-alt-usage__card">
                        <span class="ai-alt-microcard__label"><?php esc_html_e('API requests', 'ai-alt-gpt'); ?></span>
                        <span class="ai-alt-microcard__value ai-alt-usage__value ai-alt-usage__value--requests"><?php echo esc_html(number_format_i18n($stats['usage']['requests'] ?? 0)); ?></span>
                        <span class="ai-alt-usage__hint"><?php esc_html_e('Total calls recorded', 'ai-alt-gpt'); ?></span>
                    </div>
                    <div class="ai-alt-microcard ai-alt-usage__card">
                        <span class="ai-alt-microcard__label"><?php esc_html_e('Prompt tokens', 'ai-alt-gpt'); ?></span>
                        <span class="ai-alt-microcard__value ai-alt-usage__value ai-alt-usage__value--prompt"><?php echo esc_html(number_format_i18n($stats['usage']['prompt'] ?? 0)); ?></span>
                        <span class="ai-alt-usage__hint"><?php esc_html_e('Requests sent', 'ai-alt-gpt'); ?></span>
                    </div>
                    <div class="ai-alt-microcard ai-alt-usage__card">
                        <span class="ai-alt-microcard__label"><?php esc_html_e('Completion tokens', 'ai-alt-gpt'); ?></span>
                        <span class="ai-alt-microcard__value ai-alt-usage__value ai-alt-usage__value--completion"><?php echo esc_html(number_format_i18n($stats['usage']['completion'] ?? 0)); ?></span>
                        <span class="ai-alt-usage__hint"><?php esc_html_e('Responses returned', 'ai-alt-gpt'); ?></span>
                    </div>
                    <div class="ai-alt-microcard ai-alt-usage__card">
                        <span class="ai-alt-microcard__label"><?php esc_html_e('Last request', 'ai-alt-gpt'); ?></span>
                        <span class="ai-alt-microcard__value ai-alt-usage__value ai-alt-usage__value--last"><?php echo esc_html($stats['usage']['last_request_formatted'] ?? ($stats['usage']['last_request'] ?? __('None yet', 'ai-alt-gpt'))); ?></span>
                        <span class="ai-alt-usage__hint"><?php esc_html_e('Most recent ALT generation', 'ai-alt-gpt'); ?></span>
                    </div>
                </div>
                <div class="ai-alt-usage__cta">
                    <span class="ai-alt-usage__cta-label"><?php esc_html_e('Need deeper insight?', 'ai-alt-gpt'); ?></span>
                    <a href="https://platform.openai.com/usage" target="_blank" rel="noopener noreferrer" class="ai-alt-usage__cta-link"><?php esc_html_e('View detailed usage in OpenAI dashboard', 'ai-alt-gpt'); ?></a>
                </div>
                <div class="ai-alt-audit">
                    <div class="ai-alt-audit__header">
                        <h3><?php esc_html_e('Usage Audit', 'ai-alt-gpt'); ?></h3>
                        <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary ai-alt-export"><?php esc_html_e('Download CSV', 'ai-alt-gpt'); ?></a>
                    </div>
                    <table class="ai-alt-audit__table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Attachment', 'ai-alt-gpt'); ?></th>
                                <th scope="col"><?php esc_html_e('Source', 'ai-alt-gpt'); ?></th>
                                
                                <th scope="col"><?php esc_html_e('Prompt', 'ai-alt-gpt'); ?></th>
                                <th scope="col"><?php esc_html_e('Completion', 'ai-alt-gpt'); ?></th>
                                
                            </tr>
                        </thead>
                        <tbody class="ai-alt-audit-rows">
                            <?php if (!empty($audit_rows)) : ?>
                                <?php foreach ($audit_rows as $row) : ?>
                                    <tr data-id="<?php echo esc_attr($row['id']); ?>">
                                        <td>
                                            <div class="ai-alt-audit__attachment">
                                                <?php if (!empty($row['thumb'])) : ?>
                                                    <a href="<?php echo esc_url($row['details_url']); ?>" class="ai-alt-audit__thumb" aria-hidden="true"><img src="<?php echo esc_url($row['thumb']); ?>" alt="" loading="lazy" /></a>
                                                <?php endif; ?>
                                                <div class="ai-alt-audit__details">
                                                    <a href="<?php echo esc_url($row['details_url']); ?>" class="ai-alt-audit__details-link"><?php esc_html_e('Attachment', 'ai-alt-gpt'); ?></a>
                                                    <div class="ai-alt-audit__meta"><code>#<?php echo esc_html($row['id']); ?></code><?php if (!empty($row['view_url'])) : ?><a href="<?php echo esc_url($row['view_url']); ?>" target="_blank" rel="noopener noreferrer" class="ai-alt-audit__preview"><?php esc_html_e('Preview', 'ai-alt-gpt'); ?></a><?php endif; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="ai-alt-audit__source"><span class="ai-alt-badge ai-alt-badge--<?php echo esc_attr($row['source'] ?: 'unknown'); ?>" title="<?php echo esc_attr($row['source_description']); ?>"><?php echo esc_html($row['source_label']); ?></span></td>
                                        
                                        <td><?php echo esc_html(number_format_i18n($row['prompt'])); ?></td>
                                        <td><?php echo esc_html(number_format_i18n($row['completion'])); ?></td>
                                        
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr class="ai-alt-audit__empty"><td colspan="6"><?php esc_html_e('No usage data recorded yet.', 'ai-alt-gpt'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            
            <?php elseif ($tab === 'guide') : ?>
            <!-- How to Use Page - Modern Design -->
            <div class="alttextai-guide-container">
                <!-- Header Section -->
                <div class="alttextai-guide-header">
                    <div class="alttextai-guide-header-icon">
                        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="48" height="48" rx="12" fill="url(#guide-gradient)"/>
                            <path d="M24 14L16 20V32L24 38L32 32V20L24 14Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M24 14V38M16 20L32 32M32 20L16 32" stroke="white" stroke-width="2" opacity="0.5"/>
                            <defs>
                                <linearGradient id="guide-gradient" x1="0" y1="0" x2="48" y2="48">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#0891b2"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <h1 class="alttextai-guide-title"><?php esc_html_e('How to Use AltText AI', 'ai-alt-gpt'); ?></h1>
                    <p class="alttextai-guide-subtitle"><?php esc_html_e('Master AI-powered alt text generation in minutes', 'ai-alt-gpt'); ?></p>
                </div>

                <!-- Quick Start Card -->
                <div class="alttextai-guide-card alttextai-guide-card--featured">
                    <div class="alttextai-guide-card-badge"><?php esc_html_e('QUICK START', 'ai-alt-gpt'); ?></div>
                    <h2 class="alttextai-guide-card-title">
                        <span class="alttextai-guide-emoji">🚀</span>
                        <?php esc_html_e('Getting Started in 4 Easy Steps', 'ai-alt-gpt'); ?>
                    </h2>
                    <ol class="alttextai-guide-steps">
                        <li class="alttextai-guide-step">
                            <span class="alttextai-guide-step-number">1</span>
                            <div class="alttextai-guide-step-content">
                                <h3><?php esc_html_e('Upload Images', 'ai-alt-gpt'); ?></h3>
                                <p><?php esc_html_e('Add images to your Media Library as you normally would', 'ai-alt-gpt'); ?></p>
                            </div>
                        </li>
                        <li class="alttextai-guide-step">
                            <span class="alttextai-guide-step-number">2</span>
                            <div class="alttextai-guide-step-content">
                                <h3><?php esc_html_e('Bulk Optimize', 'ai-alt-gpt'); ?></h3>
                                <p><?php esc_html_e('Click "Optimize Missing Alt Text" to generate alt text for all images at once', 'ai-alt-gpt'); ?></p>
                            </div>
                        </li>
                        <li class="alttextai-guide-step">
                            <span class="alttextai-guide-step-number">3</span>
                            <div class="alttextai-guide-step-content">
                                <h3><?php esc_html_e('Review & Edit', 'ai-alt-gpt'); ?></h3>
                                <p><?php esc_html_e('Check the ALT Library table and make any adjustments you need', 'ai-alt-gpt'); ?></p>
                            </div>
                        </li>
                        <li class="alttextai-guide-step">
                            <span class="alttextai-guide-step-number">4</span>
                            <div class="alttextai-guide-step-content">
                                <h3><?php esc_html_e('Regenerate if Needed', 'ai-alt-gpt'); ?></h3>
                                <p><?php esc_html_e('Use the "Regenerate" button to create new variations for any image', 'ai-alt-gpt'); ?></p>
                            </div>
                        </li>
                    </ol>
                </div>

                <!-- Two Column Layout -->
                <div class="alttextai-guide-grid">
                    <!-- Tips Card -->
                    <div class="alttextai-guide-card">
                        <h2 class="alttextai-guide-card-title">
                            <span class="alttextai-guide-emoji">💡</span>
                            <?php esc_html_e('Tips for Better Alt Text', 'ai-alt-gpt'); ?>
                        </h2>
                        <ul class="alttextai-guide-tips">
                            <li class="alttextai-guide-tip">
                                <span class="alttextai-guide-tip-icon">✓</span>
                                <div>
                                    <strong><?php esc_html_e('Keep it concise', 'ai-alt-gpt'); ?></strong>
                                    <p><?php esc_html_e('Aim for 5-15 words that capture the essence', 'ai-alt-gpt'); ?></p>
                                </div>
                            </li>
                            <li class="alttextai-guide-tip">
                                <span class="alttextai-guide-tip-icon">✓</span>
                                <div>
                                    <strong><?php esc_html_e('Be specific', 'ai-alt-gpt'); ?></strong>
                                    <p><?php esc_html_e('Include important visual details and context', 'ai-alt-gpt'); ?></p>
                                </div>
                            </li>
                            <li class="alttextai-guide-tip">
                                <span class="alttextai-guide-tip-icon">✓</span>
                                <div>
                                    <strong><?php esc_html_e('Avoid redundancy', 'ai-alt-gpt'); ?></strong>
                                    <p><?php esc_html_e('Skip "image of" or "picture of" - get straight to the point', 'ai-alt-gpt'); ?></p>
                                </div>
                            </li>
                            <li class="alttextai-guide-tip">
                                <span class="alttextai-guide-tip-icon">✓</span>
                                <div>
                                    <strong><?php esc_html_e('Think context', 'ai-alt-gpt'); ?></strong>
                                    <p><?php esc_html_e('Consider why this image is in your content', 'ai-alt-gpt'); ?></p>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Features Card -->
                    <div class="alttextai-guide-card">
                        <h2 class="alttextai-guide-card-title">
                            <span class="alttextai-guide-emoji">⚡</span>
                            <?php esc_html_e('Powerful Features', 'ai-alt-gpt'); ?>
                        </h2>
                        <ul class="alttextai-guide-features">
                            <li>
                                <span class="alttextai-guide-feature-icon">🤖</span>
                                <strong><?php esc_html_e('AI-Powered', 'ai-alt-gpt'); ?></strong>
                                <p><?php esc_html_e('Advanced GPT-4 Vision technology', 'ai-alt-gpt'); ?></p>
                            </li>
                            <li>
                                <span class="alttextai-guide-feature-icon">📊</span>
                                <strong><?php esc_html_e('Bulk Processing', 'ai-alt-gpt'); ?></strong>
                                <p><?php esc_html_e('Generate hundreds at once', 'ai-alt-gpt'); ?></p>
                            </li>
                            <li>
                                <span class="alttextai-guide-feature-icon">🎯</span>
                                <strong><?php esc_html_e('SEO Optimized', 'ai-alt-gpt'); ?></strong>
                                <p><?php esc_html_e('Improve search rankings automatically', 'ai-alt-gpt'); ?></p>
                            </li>
                            <li>
                                <span class="alttextai-guide-feature-icon">♿</span>
                                <strong><?php esc_html_e('Accessibility', 'ai-alt-gpt'); ?></strong>
                                <p><?php esc_html_e('WCAG 2.1 compliant descriptions', 'ai-alt-gpt'); ?></p>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Upgrade CTA Card -->
                <div class="alttextai-guide-card alttextai-guide-card--cta">
                    <div class="alttextai-guide-cta-content">
                        <h2 class="alttextai-guide-cta-title"><?php esc_html_e('Ready for More?', 'ai-alt-gpt'); ?></h2>
                        <p class="alttextai-guide-cta-text">
                            <?php esc_html_e('Free plan: 10 images/month. Upgrade to Pro for 1,000 images and priority processing!', 'ai-alt-gpt'); ?>
                        </p>
                        <button type="button" class="alttextai-guide-cta-button" data-action="show-upgrade-modal">
                            <span><?php esc_html_e('View Plans & Pricing', 'ai-alt-gpt'); ?></span>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M6 12L10 8L6 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="alttextai-guide-cta-decoration">
                        <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="60" cy="60" r="50" fill="url(#cta-gradient)" opacity="0.2"/>
                            <path d="M60 30L48 42V78L60 90L72 78V42L60 30Z" stroke="url(#cta-gradient)" stroke-width="3"/>
                            <defs>
                                <linearGradient id="cta-gradient" x1="0" y1="0" x2="120" y2="120">
                                    <stop stop-color="#14b8a6"/>
                                    <stop offset="1" stop-color="#0891b2"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                </div>
            </div>
            <?php elseif ($tab === 'settings') : ?>
            <?php if (!$this->api_client->is_authenticated()) : ?>
                <!-- Settings not available for non-authenticated users -->
                <div class="alttextai-settings-required">
                    <div class="alttextai-settings-required-content">
                        <div class="alttextai-settings-required-icon">🔒</div>
                        <h2><?php esc_html_e('Sign up required', 'ai-alt-gpt'); ?></h2>
                        <p><?php esc_html_e('Please create a free account to access settings and configure your alt text generation preferences.', 'ai-alt-gpt'); ?></p>
                        <button type="button" class="alttextai-btn-primary alttextai-btn-icon" id="alttextai-settings-signup-btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                            </svg>
                            <span><?php esc_html_e('Sign Up Free', 'ai-alt-gpt'); ?></span>
                        </button>
                    </div>
                </div>
            <?php else : ?>
            <!-- Settings Page - Modern Design -->
            <div class="alttextai-settings-container">
                <?php
                // Pull fresh usage from backend to avoid stale cache in Settings
                if (isset($this->api_client)) {
                    $live = $this->api_client->get_usage();
                    if (is_array($live) && !empty($live)) { AltText_AI_Usage_Tracker::update_usage($live); }
                }
                $usage_box = AltText_AI_Usage_Tracker::get_stats_display();
                $o = wp_parse_args($opts, []);
                $is_pro = $usage_box['plan'] === 'pro';
                $usage_percent = $usage_box['limit'] > 0 ? ($usage_box['used'] / $usage_box['limit'] * 100) : 0;
                ?>

                <!-- Header Section -->
                <div class="alttextai-settings-header">
                    <div class="alttextai-settings-header-icon">
                        <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="48" height="48" rx="12" fill="url(#settings-gradient)"/>
                            <path d="M24 8C15.163 8 8 15.163 8 24s7.163 16 16 16 16-7.163 16-16S32.837 8 24 8zm0 28c-6.627 0-12-5.373-12-12s5.373-12 12-12 12 5.373 12 12-5.373 12-12 12z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M24 16v8l6 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <defs>
                                <linearGradient id="settings-gradient" x1="0" y1="0" x2="48" y2="48">
                                    <stop stop-color="#f59e0b"/>
                                    <stop offset="1" stop-color="#d97706"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <div>
                        <h1 class="alttextai-settings-title"><?php esc_html_e('Settings', 'ai-alt-gpt'); ?></h1>
                        <p class="alttextai-settings-subtitle"><?php esc_html_e('Configure your alt text generation preferences', 'ai-alt-gpt'); ?></p>
                    </div>
                </div>

                <!-- Plan Status Card -->
                <div class="alttextai-settings-plan-card <?php echo $is_pro ? 'alttextai-settings-plan-card--pro' : ''; ?>">
                    <div class="alttextai-settings-plan-header">
                        <div class="alttextai-settings-plan-badge <?php echo $is_pro ? 'alttextai-settings-plan-badge--pro' : ''; ?>">
                            <?php echo $is_pro ? '⭐ PRO' : '🆓 FREE'; ?>
                        </div>
                        <h3 class="alttextai-settings-plan-name"><?php echo esc_html($usage_box['plan_label']); ?></h3>
                    </div>

                    <div class="alttextai-settings-usage-bar">
                        <div class="alttextai-settings-usage-bar-fill" style="width: <?php echo min(100, $usage_percent); ?>%;"></div>
                    </div>

                    <div class="alttextai-settings-usage-stats">
                        <div class="alttextai-settings-usage-stat">
                            <span class="alttextai-settings-usage-label"><?php esc_html_e('Used', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-settings-usage-value"><?php echo esc_html($usage_box['used']); ?></span>
                        </div>
                        <div class="alttextai-settings-usage-stat">
                            <span class="alttextai-settings-usage-label"><?php esc_html_e('Limit', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-settings-usage-value"><?php echo esc_html($usage_box['limit']); ?></span>
                        </div>
                        <div class="alttextai-settings-usage-stat">
                            <span class="alttextai-settings-usage-label"><?php esc_html_e('Resets', 'ai-alt-gpt'); ?></span>
                            <span class="alttextai-settings-usage-value"><?php echo esc_html($usage_box['reset_date']); ?></span>
                        </div>
                    </div>

                    <?php if (!$is_pro) : ?>
                    <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-upgrade-modal">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                        </svg>
                        <span><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Account Management Card -->
                <?php if ($this->api_client->is_authenticated()) : ?>
                <div class="alttextai-settings-card alttextai-account-management-card" id="alttextai-account-management">
                    <div class="alttextai-settings-card-header">
                        <h2 class="alttextai-settings-card-title">
                            <span class="alttextai-settings-icon">💳</span>
                            <?php esc_html_e('Account Management', 'ai-alt-gpt'); ?>
                        </h2>
                        <p class="alttextai-settings-card-desc"><?php esc_html_e('Manage your subscription, billing, and payment methods', 'ai-alt-gpt'); ?></p>
                    </div>

                    <div class="alttextai-account-management-content">
                        <!-- Loading State -->
                        <div class="alttextai-account-loading" id="alttextai-subscription-loading">
                            <div class="alttextai-loading-spinner"></div>
                            <p><?php esc_html_e('Loading subscription information...', 'ai-alt-gpt'); ?></p>
                        </div>

                        <!-- Error State -->
                        <div class="alttextai-account-error" id="alttextai-subscription-error" style="display: none;">
                            <p class="alttextai-error-message"></p>
                        <button type="button" class="alttextai-btn-secondary" id="alttextai-retry-subscription" aria-label="<?php esc_attr_e('Retry loading subscription information', 'ai-alt-gpt'); ?>">
                            <?php esc_html_e('Retry', 'ai-alt-gpt'); ?>
                        </button>
                        </div>

                        <!-- Subscription Info -->
                        <div class="alttextai-subscription-info" id="alttextai-subscription-info" style="display: none;">
                            <!-- Subscription Status -->
                            <div class="alttextai-subscription-status">
                                <div class="alttextai-subscription-status-badge" id="alttextai-status-badge">
                                    <span class="alttextai-status-label"></span>
                                </div>
                                <div class="alttextai-subscription-cancel-warning" id="alttextai-cancel-warning" style="display: none;">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 1L1 3v6c0 4.418 3.582 8 8 8s8-3.582 8-8V3L8 1z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                        <path d="M8 5v4M8 11h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                    <span><?php esc_html_e('Your subscription will cancel at the end of the current billing period.', 'ai-alt-gpt'); ?></span>
                                </div>
                            </div>

                            <!-- Plan Details -->
                            <div class="alttextai-subscription-details">
                                <div class="alttextai-subscription-detail-item">
                                    <span class="alttextai-detail-label"><?php esc_html_e('Current Plan', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-detail-value" id="alttextai-plan-name">-</span>
                                </div>
                                <div class="alttextai-subscription-detail-item">
                                    <span class="alttextai-detail-label"><?php esc_html_e('Billing Cycle', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-detail-value" id="alttextai-billing-cycle">-</span>
                                </div>
                                <div class="alttextai-subscription-detail-item">
                                    <span class="alttextai-detail-label"><?php esc_html_e('Next Billing Date', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-detail-value" id="alttextai-next-billing">-</span>
                                </div>
                                <div class="alttextai-subscription-detail-item">
                                    <span class="alttextai-detail-label"><?php esc_html_e('Next Charge', 'ai-alt-gpt'); ?></span>
                                    <span class="alttextai-detail-value" id="alttextai-next-charge">-</span>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="alttextai-payment-method" id="alttextai-payment-method" style="display: none;">
                                <div class="alttextai-payment-method-header">
                                    <span class="alttextai-detail-label"><?php esc_html_e('Payment Method', 'ai-alt-gpt'); ?></span>
                                </div>
                                <div class="alttextai-payment-method-info">
                                    <span class="alttextai-card-brand" id="alttextai-card-brand"></span>
                                    <span class="alttextai-card-last4" id="alttextai-card-last4"></span>
                                    <span class="alttextai-card-expiry" id="alttextai-card-expiry"></span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="alttextai-account-actions">
                                <button type="button" class="alttextai-btn-primary alttextai-btn-icon" id="alttextai-update-payment-method" aria-label="<?php esc_attr_e('Update payment method in Stripe Customer Portal', 'ai-alt-gpt'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M8 1L1 3v6c0 4.418 3.582 8 8 8s8-3.582 8-8V3L8 1z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                    </svg>
                                    <span><?php esc_html_e('Update Payment Method', 'ai-alt-gpt'); ?></span>
                                </button>
                                <button type="button" class="alttextai-btn-secondary alttextai-btn-icon" id="alttextai-manage-subscription" aria-label="<?php esc_attr_e('Manage subscription, change plan, or cancel in Stripe Customer Portal', 'ai-alt-gpt'); ?>">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                        <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                    </svg>
                                    <span><?php esc_html_e('Manage Subscription', 'ai-alt-gpt'); ?></span>
                                </button>
                            </div>
                        </div>

                        <!-- Free Plan Message -->
                        <div class="alttextai-free-plan-message" id="alttextai-free-plan-message" style="display: none;">
                            <p><?php esc_html_e('You are currently on the Free plan. Upgrade to access more features and unlimited alt text generation.', 'ai-alt-gpt'); ?></p>
                            <button type="button" class="alttextai-btn-primary alttextai-btn-icon" data-action="show-upgrade-modal">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 2L6 6H2L6 9L4 14L8 11L12 14L10 9L14 6H10L8 2Z" fill="currentColor"/>
                                </svg>
                                <span><?php esc_html_e('Upgrade Now', 'ai-alt-gpt'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Settings Form -->
                <form method="post" action="options.php" class="alttextai-settings-form">
                    <?php settings_fields('ai_alt_gpt_group'); ?>

                    <!-- Generation Settings Card -->
                    <div class="alttextai-settings-card">
                        <div class="alttextai-settings-card-header">
                            <h2 class="alttextai-settings-card-title">
                                <span class="alttextai-settings-icon">⚙️</span>
                                <?php esc_html_e('Generation Settings', 'ai-alt-gpt'); ?>
                            </h2>
                            <p class="alttextai-settings-card-desc"><?php esc_html_e('Control when and how alt text is generated', 'ai-alt-gpt'); ?></p>
                        </div>

                        <div class="alttextai-settings-field">
                            <div class="alttextai-settings-field-header">
                                <label class="alttextai-settings-label">
                                    <?php esc_html_e('Auto-Generate on Upload', 'ai-alt-gpt'); ?>
                                </label>
                            </div>
                            <div class="alttextai-settings-toggle-wrapper">
                                <label class="alttextai-settings-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" value="1" <?php checked(!isset($o['enable_on_upload']) || !empty($o['enable_on_upload'])); ?> />
                                    <span class="alttextai-settings-toggle-slider"></span>
                                </label>
                                <span class="alttextai-settings-toggle-label">
                                    <?php esc_html_e('Automatically generate alt text when images are uploaded', 'ai-alt-gpt'); ?>
                                </span>
                            </div>
                            <p class="alttextai-settings-help">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M7 10V7M7 4.5V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e('If monthly credits are exhausted, generation is skipped and an upgrade prompt is shown.', 'ai-alt-gpt'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Customization Settings Card -->
                    <div class="alttextai-settings-card">
                        <div class="alttextai-settings-card-header">
                            <h2 class="alttextai-settings-card-title">
                                <span class="alttextai-settings-icon">✨</span>
                                <?php esc_html_e('Customization', 'ai-alt-gpt'); ?>
                            </h2>
                            <p class="alttextai-settings-card-desc"><?php esc_html_e('Personalize the AI output to match your brand voice', 'ai-alt-gpt'); ?></p>
                        </div>

                        <div class="alttextai-settings-field">
                            <label class="alttextai-settings-label" for="ai-alt-tone">
                                <?php esc_html_e('Tone & Style', 'ai-alt-gpt'); ?>
                            </label>
                            <input
                                type="text"
                                id="ai-alt-tone"
                                class="alttextai-settings-input"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]"
                                value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>"
                                placeholder="<?php esc_attr_e('e.g., professional, accessible', 'ai-alt-gpt'); ?>"
                            />
                            <p class="alttextai-settings-help">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M7 10V7M7 4.5V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e('Describe the overall voice (e.g., "professional, accessible" or "friendly, concise").', 'ai-alt-gpt'); ?>
                            </p>
                        </div>

                        <div class="alttextai-settings-field">
                            <label class="alttextai-settings-label" for="ai-alt-custom-prompt">
                                <?php esc_html_e('Additional Instructions', 'ai-alt-gpt'); ?>
                            </label>
                            <textarea
                                id="ai-alt-custom-prompt"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]"
                                rows="4"
                                class="alttextai-settings-textarea"
                                placeholder="<?php esc_attr_e('Enter any specific instructions for the AI...', 'ai-alt-gpt'); ?>"
                            ><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                            <p class="alttextai-settings-help">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M7 10V7M7 4.5V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e('Optional. This text is prepended to every request. Useful for accessibility or brand rules.', 'ai-alt-gpt'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="alttextai-settings-actions">
                        <button type="submit" class="alttextai-settings-save-button">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M13 4L6 11L3 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?php esc_html_e('Save Settings', 'ai-alt-gpt'); ?></span>
                        </button>
                        <p class="alttextai-settings-save-note">
                            <?php esc_html_e('Changes will apply to all future generations', 'ai-alt-gpt'); ?>
                        </p>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php
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
            
            // Include upgrade modal on all tabs
            $checkout_prices = $this->get_checkout_price_ids();
            include AI_ALT_GPT_PLUGIN_DIR . 'templates/upgrade-modal.php';
            ?>
            </div><!-- .alttextai-container -->
        </div>
        <?php
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
        return apply_filters('ai_alt_gpt_prompt', $prompt, $attachment_id, $opts);
    }

    private function is_image($attachment_id){
        $mime = get_post_mime_type($attachment_id);
        return strpos((string)$mime, 'image/') === 0;
    }

    private function invalidate_stats_cache(){
        wp_cache_delete('ai_alt_stats', 'ai_alt_gpt');
        delete_transient('ai_alt_stats_v3');
        $this->stats_cache = null;
    }

    private function get_media_stats(){
        // Check in-memory cache first
        if (is_array($this->stats_cache)){
            return $this->stats_cache;
        }

        // Check object cache (Redis/Memcached if available)
        $cache_key = 'ai_alt_stats';
        $cache_group = 'ai_alt_gpt';
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

    private function prepare_attachment_snapshot($attachment_id){
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
                'grade' => __('Missing', 'ai-alt-gpt'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'ai-alt-gpt')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'ai-alt-gpt'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'ai-alt-gpt')],
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
                'grade' => __('Critical', 'ai-alt-gpt'),
                'status' => 'critical',
                'issues' => [__('ALT text looks like placeholder content and must be rewritten.', 'ai-alt-gpt')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Critical', 'ai-alt-gpt'),
                    'status'=> 'critical',
                    'issues'=> [__('ALT text looks like placeholder content and must be rewritten.', 'ai-alt-gpt')],
                ],
                'review' => null,
            ];
        }

        $length = function_exists('mb_strlen') ? mb_strlen($alt) : strlen($alt);
        if ($length < 45){
            $score -= 35;
            $issues[] = __('Too short – add a richer description (45+ characters).', 'ai-alt-gpt');
        } elseif ($length > 160){
            $score -= 15;
            $issues[] = __('Very long – trim to keep the description concise (under 160 characters).', 'ai-alt-gpt');
        }

        if (preg_match('/\b(image|picture|photo|screenshot)\b/i', $alt)){
            $score -= 10;
            $issues[] = __('Contains generic filler words like “image” or “photo”.', 'ai-alt-gpt');
        }

        if (preg_match('/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt)){
            $score = min($score - 80, 5);
            $issues[] = __('Contains placeholder wording such as “test” or “sample”. Replace with a real description.', 'ai-alt-gpt');
        }

        $word_count = str_word_count($alt, 0, '0123456789');
        if ($word_count < 4){
            $score -= 70;
            $score = min($score, 5);
            $issues[] = __('ALT text is extremely brief – add meaningful descriptive words.', 'ai-alt-gpt');
        } elseif ($word_count < 6){
            $score -= 50;
            $score = min($score, 20);
            $issues[] = __('ALT text is too short to convey the subject in detail.', 'ai-alt-gpt');
        } elseif ($word_count < 8){
            $score -= 35;
            $score = min($score, 40);
            $issues[] = __('ALT text could use a few more descriptive words.', 'ai-alt-gpt');
        }

        if ($score > 40 && $length < 30){
            $score = min($score, 40);
            $issues[] = __('Expand the description with one or two concrete details.', 'ai-alt-gpt');
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
                $issues[] = __('Matches the attachment title – add more unique detail.', 'ai-alt-gpt');
            }
        }

        $file = get_attached_file($attachment_id);
        if ($file && $normalized_alt !== ''){
            $base = pathinfo($file, PATHINFO_FILENAME);
            $normalized_base = $normalize($base);
            if ($normalized_base !== '' && $normalized_alt === $normalized_base){
                $score -= 20;
                $issues[] = __('Matches the file name – rewrite it to describe the image.', 'ai-alt-gpt');
            }
        }

        if (!preg_match('/[a-z]{4,}/i', $alt)){
            $score -= 15;
            $issues[] = __('Lacks descriptive language – include meaningful nouns or adjectives.', 'ai-alt-gpt');
        }

        if (!preg_match('/\b[a-z]/i', $alt)){
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        if ($status === 'review' && empty($issues)){
            $issues[] = __('Give this ALT another look to ensure it reflects the image details.', 'ai-alt-gpt');
        } elseif ($status === 'critical' && empty($issues)){
            $issues[] = __('ALT text should be rewritten for accessibility.', 'ai-alt-gpt');
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
                return __('Excellent', 'ai-alt-gpt');
            case 'good':
                return __('Strong', 'ai-alt-gpt');
            case 'review':
                return __('Needs review', 'ai-alt-gpt');
            default:
                return __('Critical', 'ai-alt-gpt');
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

    private function get_missing_attachment_ids($limit = 5){
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

    private function get_all_attachment_ids($limit = 5, $offset = 0){
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
            $cached = wp_cache_get($cache_key, 'ai_alt_gpt');
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
                wp_cache_set($cache_key, [], 'ai_alt_gpt', MINUTE_IN_SECONDS * 2);
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
            wp_cache_set($cache_key, $formatted, 'ai_alt_gpt', MINUTE_IN_SECONDS * 5);
        }

        return $formatted;
    }

    private function get_source_meta_map(){
        return [
            'auto'     => [
                'label' => __('Auto (upload)', 'ai-alt-gpt'),
                'description' => __('Generated automatically when the image was uploaded.', 'ai-alt-gpt'),
            ],
            'ajax'     => [
                'label' => __('Media Library (single)', 'ai-alt-gpt'),
                'description' => __('Triggered from the Media Library row action or attachment details screen.', 'ai-alt-gpt'),
            ],
            'bulk'     => [
                'label' => __('Media Library (bulk)', 'ai-alt-gpt'),
                'description' => __('Generated via the Media Library bulk action.', 'ai-alt-gpt'),
            ],
            'dashboard' => [
                'label' => __('Dashboard quick actions', 'ai-alt-gpt'),
                'description' => __('Generated from the dashboard buttons.', 'ai-alt-gpt'),
            ],
            'wpcli'    => [
                'label' => __('WP-CLI', 'ai-alt-gpt'),
                'description' => __('Generated via the wp ai-alt CLI command.', 'ai-alt-gpt'),
            ],
            'manual'   => [
                'label' => __('Manual / custom', 'ai-alt-gpt'),
                'description' => __('Generated by custom code or integration.', 'ai-alt-gpt'),
            ],
            'unknown'  => [
                'label' => __('Unknown', 'ai-alt-gpt'),
                'description' => __('Source not recorded for this ALT text.', 'ai-alt-gpt'),
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
            wp_die(__('You do not have permission to export usage data.', 'ai-alt-gpt'));
        }
        check_admin_referer('ai_alt_usage_export');

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
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'ai-alt-gpt'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0){
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'ai-alt-gpt'));
        }

        $limit = apply_filters('ai_alt_gpt_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit){
            return new \WP_Error('inline_image_too_large', __('Image exceeds the inline embedding size limit.', 'ai-alt-gpt'), ['size' => $size, 'limit' => $limit]);
        }

        $contents = file_get_contents($file);
        if ($contents === false){
            return new \WP_Error('inline_image_read_failed', __('Unable to read the image file for inline embedding.', 'ai-alt-gpt'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (empty($mime)){
            $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'image/jpeg';
        }

        $base64 = base64_encode($contents);
        if (!$base64){
            return new \WP_Error('inline_image_encode_failed', __('Failed to encode the image for inline embedding.', 'ai-alt-gpt'));
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
            return new \WP_Error('review_skipped', __('ALT text is empty; skipped review.', 'ai-alt-gpt'));
        }

        $review_model = $opts['review_model'] ?? ($opts['model'] ?? 'gpt-4o-mini');
        $review_model = apply_filters('ai_alt_gpt_review_model', $review_model, $attachment_id, $opts);
        if (!$review_model){
            return new \WP_Error('review_model_missing', __('No review model configured.', 'ai-alt-gpt'));
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
            $context_lines[] = sprintf(__('Media title: %s', 'ai-alt-gpt'), $title);
        }
        if ($filename){
            $context_lines[] = sprintf(__('Filename: %s', 'ai-alt-gpt'), $filename);
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
            return new \WP_Error('review_parse_failed', __('Unable to parse review response.', 'ai-alt-gpt'), ['response' => $content]);
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

    private function persist_generation_result(int $attachment_id, string $alt, array $usage_summary, string $source, string $model, string $image_strategy, $review_result): void{
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
            set_transient('ai_alt_limit_notice', 1, MINUTE_IN_SECONDS * 10);
            return;
        }
        $this->generate_and_save($attachment_id, 'auto');
    }

    public function generate_and_save($attachment_id, $source='manual', int $retry_count = 0, array $feedback = []){
        $opts = get_option(self::OPTION_KEY, []);

        // Skip authentication check in local development mode
        if (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV) {
            // Check if at usage limit (only in production)
            if ($this->api_client->has_reached_limit()) {
                return new \WP_Error('limit_reached', __('Monthly generation limit reached. Please upgrade to continue.', 'ai-alt-gpt'));
            }
        }

        if (!$this->is_image($attachment_id)) return new \WP_Error('not_image', 'Attachment is not an image.');

        // Prefer higher-quality default for better accuracy
        $model = apply_filters('ai_alt_gpt_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts);
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $prompt = $this->build_prompt($attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback);

        if (!empty($opts['dry_run'])){
            update_post_meta($attachment_id, '_ai_alt_last_prompt', $prompt);
            update_post_meta($attachment_id, '_ai_alt_source', 'dry-run');
            update_post_meta($attachment_id, '_ai_alt_model', $model);
            update_post_meta($attachment_id, '_ai_alt_generated_at', current_time('mysql'));
            $this->stats_cache = null;
            return new \WP_Error('ai_alt_dry_run', __('Dry run enabled. Prompt stored for review; ALT text not updated.', 'ai-alt-gpt'), ['prompt' => $prompt]);
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
        
        // For local development, use simple mock response
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            $api_response = [
                'success' => true,
                'alt_text' => 'Generated alt text for image ' . $attachment_id,
                'tokens' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15
                ]
            ];
        } else {
            // Call proxy API to generate alt text
            $api_response = $this->api_client->generate_alt_text($attachment_id, $context);
            
            if (is_wp_error($api_response)) {
                return $api_response;
            }
        }
        
        if (!isset($api_response['success']) || !$api_response['success']) {
            return new \WP_Error('api_error', __('Failed to generate alt text', 'ai-alt-gpt'));
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
            if (!is_wp_error($result) && $existing_alt){
                $generated = trim($result['alt']);
                if (strcasecmp($generated, trim($existing_alt)) === 0){
                $result = new \WP_Error(
                    'duplicate_alt',
                    __('Generated ALT text matched the existing value.', 'ai-alt-gpt'),
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

        // Note: QA review is disabled for API proxy version (quality handled server-side)
        // Persist the generated alt text
        $this->persist_generation_result($attachment_id, $alt, $usage_summary, $source, $model, $image_strategy, $review_result);

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
        $bulk_actions['ai_alt_generate'] = __('Generate Alt Text (AI)', 'ai-alt-gpt');
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
        }
        return add_query_arg(['ai_alt_queued' => $queued], $redirect_to);
    }

    public function row_action_link($actions, $post){
        if ($post->post_type === 'attachment' && $this->is_image($post->ID)){
            $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $generate_label   = __('Generate Alt Text (AI)', 'ai-alt-gpt');
            $regenerate_label = __('Regenerate Alt Text (AI)', 'ai-alt-gpt');
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
        $label_generate   = __('Generate Alt', 'ai-alt-gpt');
        $label_regenerate = __('Regenerate Alt', 'ai-alt-gpt');
        $current_label    = $has_alt ? $label_regenerate : $label_generate;
        $is_authenticated = $this->api_client->is_authenticated();
        $disabled_attr    = !$is_authenticated ? ' disabled title="' . esc_attr__('Please login to generate alt text', 'ai-alt-gpt') . '"' : '';
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
            'label' => __('AI Alt Text', 'ai-alt-gpt'),
            'input' => 'html',
            'html'  => $button . '<p class="description">' . esc_html__('Use AI to suggest alternative text for this image.', 'ai-alt-gpt') . '</p>',
        ];
        }

        return $fields;
    }

    public function register_rest_routes(){
        register_rest_route('ai-alt/v1', '/generate/(?P<id>\d+)', [
            'methods'  => 'POST',
            'callback' => function($req){
                if (!current_user_can('edit_posts')) return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                $id = intval($req['id']);
                $alt = $this->generate_and_save($id, 'ajax');
                if (is_wp_error($alt)) {
                    if ($alt->get_error_code() === 'ai_alt_dry_run'){
                        return [
                            'id'      => $id,
                            'code'    => $alt->get_error_code(),
                            'message' => $alt->get_error_message(),
                            'prompt'  => $alt->get_error_data()['prompt'] ?? '',
                            'stats'   => $this->get_media_stats(),
                        ];
                    }
                    return $alt;
                }
                return [
                    'id'   => $id,
                    'alt'  => $alt,
                    'meta' => $this->prepare_attachment_snapshot($id),
                    'stats'=> $this->get_media_stats(),
                ];
            },
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('ai-alt/v1', '/alt/(?P<id>\d+)', [
            'methods'  => 'POST',
            'callback' => function($req){
                if (!current_user_can('edit_posts')) {
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }

                $id  = intval($req['id']);
                $alt = trim((string) $req->get_param('alt'));

                if ($id <= 0) {
                    return new \WP_Error('invalid_attachment', 'Invalid attachment ID.', ['status' => 400]);
                }

                if ($alt === '') {
                    return new \WP_Error('invalid_alt', __('ALT text cannot be empty.', 'ai-alt-gpt'), ['status' => 400]);
                }

                $alt_sanitized = wp_strip_all_tags($alt);

                $usage = [
                    'prompt' => 0,
                    'completion' => 0,
                    'total' => 0,
                ];

                $post = get_post($id);
                $file_path = get_attached_file($id);
                $context = [
                    'filename' => $file_path ? basename($file_path) : '',
                    'title' => get_the_title($id),
                    'caption' => $post->post_excerpt ?? '',
                    'post_title' => '',
                ];

                if ($post && $post->post_parent) {
                    $parent = get_post($post->post_parent);
                    if ($parent) {
                        $context['post_title'] = $parent->post_title;
                    }
                }

                $review_result = null;
                if ($this->api_client) {
                    $review_response = $this->api_client->review_alt_text($id, $alt_sanitized, $context);
                    if (!is_wp_error($review_response) && !empty($review_response['review'])) {
                        $review = $review_response['review'];
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
                }

                $this->persist_generation_result(
                    $id,
                    $alt_sanitized,
                    $usage,
                    'manual-edit',
                    'manual-input',
                    'manual',
                    $review_result
                );

                return [
                    'id'   => $id,
                    'alt'  => $alt_sanitized,
                    'meta' => $this->prepare_attachment_snapshot($id),
                    'stats'=> $this->get_media_stats(),
                    'source' => 'manual-edit',
                ];
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-alt/v1', '/list', [
            'methods'  => 'GET',
            'callback' => function($req){
                if (!current_user_can('edit_posts')){
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }

                $scope = $req->get_param('scope') === 'all' ? 'all' : 'missing';
                $limit = max(1, min(500, intval($req->get_param('limit') ?: 100)));

                if ($scope === 'missing'){
                    $ids = $this->get_missing_attachment_ids($limit);
                } else {
                    $ids = $this->get_all_attachment_ids($limit, 0);
                }

                return ['ids' => array_map('intval', $ids)];
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-alt/v1', '/stats', [
            'methods'  => 'GET',
            'callback' => function($req){
                if (!current_user_can('edit_posts')){
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }

                $fresh = $req->get_param('fresh');
                if ($fresh && filter_var($fresh, FILTER_VALIDATE_BOOLEAN)) {
                    $this->invalidate_stats_cache();
                }

                return $this->get_media_stats();
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-alt/v1', '/usage', [
            'methods'  => 'GET',
            'callback' => function(){
                if (!current_user_can('edit_posts')){
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }
                $usage = $this->api_client->get_usage();
                if (is_wp_error($usage)) {
                    return $usage;
                }
                return $usage;
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-alt/v1', '/plans', [
            'methods'  => 'GET',
            'callback' => function(){
                if (!current_user_can('edit_posts')){
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }

                return [
                    'prices' => $this->get_checkout_price_ids(),
                ];
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);

        register_rest_route('ai-alt/v1', '/queue', [
            'methods'  => 'GET',
            'callback' => function(){
                if (!current_user_can('edit_posts')){
                    return new \WP_Error('forbidden', 'No permission', ['status' => 403]);
                }

                $stats = AltText_AI_Queue::get_stats();
                $recent = AltText_AI_Queue::get_recent(apply_filters('ai_alt_queue_recent_limit', 10));
                $failures = AltText_AI_Queue::get_recent_failures(apply_filters('ai_alt_queue_fail_limit', 5));

                $sanitize_job = function($row){
                    return [
                        'id'            => intval($row['id'] ?? 0),
                        'attachment_id' => intval($row['attachment_id'] ?? 0),
                        'status'        => sanitize_key($row['status'] ?? ''),
                        'attempts'      => intval($row['attempts'] ?? 0),
                        'source'        => sanitize_key($row['source'] ?? ''),
                        'last_error'    => isset($row['last_error']) ? wp_kses_post($row['last_error']) : '',
                        'enqueued_at'   => $row['enqueued_at'] ?? '',
                        'locked_at'     => $row['locked_at'] ?? '',
                        'completed_at'  => $row['completed_at'] ?? '',
                        'attachment_title' => get_the_title(intval($row['attachment_id'] ?? 0)),
                        'edit_url'      => esc_url_raw(add_query_arg('item', intval($row['attachment_id'] ?? 0), admin_url('upload.php'))),
                    ];
                };

                return [
                    'stats'    => $stats,
                    'recent'   => array_map($sanitize_job, $recent),
                    'failures' => array_map($sanitize_job, $failures),
                ];
            },
            'permission_callback' => function(){ return current_user_can('edit_posts'); },
        ]);
    }

    public function enqueue_admin($hook){
        $base_path = AI_ALT_GPT_PLUGIN_DIR;
        $base_url  = AI_ALT_GPT_PLUGIN_URL;

        $asset_version = static function(string $relative, string $fallback = '1.0.0') use ($base_path): string {
            $relative = ltrim($relative, '/');
            $path = $base_path . $relative;
            return file_exists($path) ? (string) filemtime($path) : $fallback;
        };

        // Use minified files in production, full files in development
        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        $admin_file = "assets/ai-alt-admin{$suffix}.js";
        $admin_version = $asset_version($admin_file, '3.0.0');
        
        $checkout_prices = $this->get_checkout_price_ids();

        $l10n_common = [
            'reviewCue'           => __('Visit the ALT Library to double-check the wording.', 'ai-alt-gpt'),
            'statusReady'         => '',
            'previewAltHeading'   => __('Review generated ALT text', 'ai-alt-gpt'),
            'previewAltHint'      => __('Review the generated description before applying it to your media item.', 'ai-alt-gpt'),
            'previewAltApply'     => __('Use this ALT', 'ai-alt-gpt'),
            'previewAltCancel'    => __('Keep current ALT', 'ai-alt-gpt'),
            'previewAltDismissed' => __('Preview dismissed. Existing ALT kept.', 'ai-alt-gpt'),
            'previewAltShortcut'  => __('Shift + Enter for newline.', 'ai-alt-gpt'),
        ];

        // Load on Media Library and attachment edit contexts (modal also)
        if (in_array($hook, ['upload.php', 'post.php', 'post-new.php', 'media_page_ai-alt-gpt'], true)){
            wp_enqueue_script('ai-alt-gpt-admin', $base_url . $admin_file, ['jquery'], $admin_version, true);
            wp_localize_script('ai-alt-gpt-admin', 'AI_ALT_GPT', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'rest'      => esc_url_raw( rest_url('ai-alt/v1/generate/') ),
                'restAlt'   => esc_url_raw( rest_url('ai-alt/v1/alt/') ),
                'restStats' => esc_url_raw( rest_url('ai-alt/v1/stats') ),
                'restUsage' => esc_url_raw( rest_url('ai-alt/v1/usage') ),
                'restMissing'=> esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('ai-alt/v1/list')) ),
                'restAll'    => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('ai-alt/v1/list')) ),
                'restQueue'  => esc_url_raw( rest_url('ai-alt/v1/queue') ),
                'restRoot'  => esc_url_raw( rest_url() ),
                'restPlans' => esc_url_raw( rest_url('ai-alt/v1/plans') ),
                'l10n'      => $l10n_common,
                'upgradeUrl'=> esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
            ]);
        }

        if ($hook === 'media_page_ai-alt-gpt'){
            $css_file = "assets/ai-alt-dashboard{$suffix}.css";
            $js_file = "assets/ai-alt-dashboard{$suffix}.js";
            $upgrade_css = "assets/upgrade-modal{$suffix}.css";
            $upgrade_js = "assets/upgrade-modal{$suffix}.js";
            $auth_css = "assets/auth-modal{$suffix}.css";
            $auth_js = "assets/auth-modal{$suffix}.js";

            // Enqueue design system (FIRST - foundation for all styles)
            wp_enqueue_style(
                'ai-alt-gpt-design-system',
                $base_url . "assets/design-system{$suffix}.css",
                [],
                $asset_version("assets/design-system{$suffix}.css", '1.0.0')
            );

            // Enqueue reusable components (SECOND - uses design tokens)
            wp_enqueue_style(
                'ai-alt-gpt-components',
                $base_url . "assets/components{$suffix}.css",
                ['ai-alt-gpt-design-system'],
                $asset_version("assets/components{$suffix}.css", '1.0.0')
            );

            // Enqueue page-specific styles (use design system + components)
            wp_enqueue_style(
                'ai-alt-gpt-dashboard',
                $base_url . $css_file,
                ['ai-alt-gpt-components'],
                $asset_version($css_file, '3.0.0')
            );
            wp_enqueue_style(
                'ai-alt-gpt-modern',
                $base_url . "assets/modern-style{$suffix}.css",
                ['ai-alt-gpt-components'],
                $asset_version("assets/modern-style{$suffix}.css", '4.1.0')
            );
            wp_enqueue_style(
                'ai-alt-gpt-upgrade',
                $base_url . $upgrade_css,
                ['ai-alt-gpt-components'],
                $asset_version($upgrade_css, '3.1.0')
            );
            wp_enqueue_style(
                'ai-alt-gpt-auth',
                $base_url . $auth_css,
                ['ai-alt-gpt-components'],
                $asset_version($auth_css, '4.0.0')
            );
            wp_enqueue_style(
                'ai-alt-gpt-button-enhancements',
                $base_url . "assets/button-enhancements{$suffix}.css",
                ['ai-alt-gpt-components'],
                $asset_version("assets/button-enhancements{$suffix}.css", '1.0.0')
            );
            wp_enqueue_style(
                'ai-alt-gpt-guide-settings',
                $base_url . "assets/guide-settings-pages{$suffix}.css",
                ['ai-alt-gpt-components'],
                $asset_version("assets/guide-settings-pages{$suffix}.css", '1.0.0')
            );

            $stats_data = $this->get_media_stats();
            $usage_data = AltText_AI_Usage_Tracker::get_stats_display();
            
            wp_enqueue_script(
                'ai-alt-gpt-dashboard',
                $base_url . $js_file,
                ['jquery', 'wp-api-fetch'],
                $asset_version($js_file, '3.0.0'),
                true
            );
            wp_enqueue_script(
                'ai-alt-gpt-upgrade',
                $base_url . $upgrade_js,
                ['jquery'],
                $asset_version($upgrade_js, '3.1.0'),
                true
            );
            wp_enqueue_script(
                'ai-alt-gpt-auth',
                $base_url . $auth_js,
                ['jquery'],
                $asset_version($auth_js, '4.0.0'),
                true
            );

            wp_localize_script('ai-alt-gpt-dashboard', 'AI_ALT_GPT_DASH', [
                'nonce'       => wp_create_nonce('wp_rest'),
                'rest'        => esc_url_raw( rest_url('ai-alt/v1/generate/') ),
                'restStats'   => esc_url_raw( rest_url('ai-alt/v1/stats') ),
                'restUsage'   => esc_url_raw( rest_url('ai-alt/v1/usage') ),
                'restMissing' => esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('ai-alt/v1/list')) ),
                'restAll'     => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('ai-alt/v1/list')) ),
                'restQueue'   => esc_url_raw( rest_url('ai-alt/v1/queue') ),
                'restRoot'    => esc_url_raw( rest_url() ),
                'restPlans'   => esc_url_raw( rest_url('ai-alt/v1/plans') ),
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

            wp_localize_script('ai-alt-gpt-dashboard', 'alttextai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('alttextai_upgrade_nonce'),
                'api_url' => $api_url,
                'is_authenticated' => $this->api_client->is_authenticated(),
                'user_data' => $this->api_client->get_user_data(),
            ]);
            
            wp_localize_script('ai-alt-gpt-dashboard', 'AI_ALT_GPT_DASH_L10N', [
                'l10n'        => array_merge([
                    'processing'         => __('Generating ALT text…', 'ai-alt-gpt'),
                    'processingMissing'  => __('Generating ALT for #%d…', 'ai-alt-gpt'),
                    'error'              => __('Something went wrong. Check console for details.', 'ai-alt-gpt'),
                    'summary'            => __('Generated %1$d images (%2$d errors).', 'ai-alt-gpt'),
                    'restUnavailable'    => __('REST endpoint unavailable', 'ai-alt-gpt'),
                    'prepareBatch'       => __('Preparing image list…', 'ai-alt-gpt'),
                    'coverageCopy'       => __('of images currently include ALT text.', 'ai-alt-gpt'),
                    'noRequests'         => __('None yet', 'ai-alt-gpt'),
                    'noAudit'            => __('No usage data recorded yet.', 'ai-alt-gpt'),
                    'nothingToProcess'   => __('No images to process.', 'ai-alt-gpt'),
                    'batchStart'         => __('Starting batch…', 'ai-alt-gpt'),
                    'batchComplete'      => __('Batch complete.', 'ai-alt-gpt'),
                    'batchCompleteAt'    => __('Batch complete at %s', 'ai-alt-gpt'),
                    'completedItem'      => __('Finished #%d', 'ai-alt-gpt'),
                    'failedItem'         => __('Failed #%d', 'ai-alt-gpt'),
                    'loadingButton'      => __('Processing…', 'ai-alt-gpt'),
                ], $l10n_common),
            ]);
            
            // Localize upgrade modal script
            wp_localize_script('ai-alt-gpt-upgrade', 'AltTextAI', [
                'nonce' => wp_create_nonce('alttextai_upgrade_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'usage' => $usage_data,
                'upgradeUrl' => esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'priceIds' => $checkout_prices,
                'restPlans' => esc_url_raw( rest_url('ai-alt/v1/plans') ),
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
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        AltText_AI_Usage_Tracker::dismiss_upgrade_notice();
        setcookie('alttextai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_queue_retry_job() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'ai-alt-gpt')]);
        }
        $job_id = intval($_POST['job_id'] ?? 0);
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'ai-alt-gpt')]);
        }
        AltText_AI_Queue::retry_job($job_id);
        AltText_AI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'ai-alt-gpt')]);
    }

    public function ajax_queue_retry_failed() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'ai-alt-gpt')]);
        }
        AltText_AI_Queue::retry_failed();
        AltText_AI_Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'ai-alt-gpt')]);
    }

    public function ajax_queue_clear_completed() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'ai-alt-gpt')]);
        }
        AltText_AI_Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'ai-alt-gpt')]);
    }

    public function ajax_queue_stats() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'ai-alt-gpt')]);
        }
        
        $stats = AltText_AI_Queue::get_stats();
        $failures = AltText_AI_Queue::get_failures();
        
        wp_send_json_success([
            'stats' => $stats,
            'failures' => $failures
        ]);
    }

    public function ajax_track_upgrade() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'ai-alt-gpt')]);
        }

        $source = sanitize_key($_POST['source'] ?? 'dashboard');
        $event  = [
            'source' => $source,
            'user_id' => get_current_user_id(),
            'time'   => current_time('mysql'),
        ];

        update_option('alttextai_last_upgrade_click', $event, false);
        do_action('alttextai_upgrade_clicked', $event);

        wp_send_json_success(['recorded' => true]);
    }

    public function ajax_refresh_usage() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        
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
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        // Check if user has remaining usage
        $usage = $this->api_client->get_usage();
        if (!$usage || $usage['remaining'] <= 0) {
            wp_send_json_error([
                'message' => 'Monthly limit reached',
                'code' => 'limit_reached',
                'usage' => $usage
            ]);
        }
        
        $result = $this->generate_and_save($attachment_id, 'ajax');

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ]);
        }

        AltText_AI_Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        wp_send_json_success([
            'message'        => __('Alt text generated successfully', 'ai-alt-gpt'),
            'alt_text'       => $result,
            'attachment_id'  => $attachment_id,
        ]);
    }

    /**
     * AJAX handler: Bulk queue images for processing
     */
    public function ajax_bulk_queue() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');
        
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
        
        // Check if user has remaining usage
        $usage = $this->api_client->get_usage();
        if (!$usage || $usage['remaining'] <= 0) {
            wp_send_json_error([
                'message' => 'Monthly limit reached',
                'code' => 'limit_reached',
                'usage' => $usage
            ]);
        }
        
        // Check how many we can queue
        $remaining = $usage['remaining'] ?? 0;
        if (count($ids) > $remaining) {
            wp_send_json_error([
                'message' => sprintf(__('You only have %d generations remaining. Please upgrade or select fewer images.', 'ai-alt-gpt'), $remaining),
                'code' => 'insufficient_credits',
                'remaining' => $remaining
            ]);
        }
        
        // Queue images
        $queued = AltText_AI_Queue::enqueue_many($ids, $source);
        
        if ($queued > 0) {
            // Schedule queue processing
            AltText_AI_Queue::schedule_processing();
            
            wp_send_json_success([
                'message' => sprintf(__('%d image(s) queued for processing', 'ai-alt-gpt'), $queued),
                'queued' => $queued,
                'total' => count($ids)
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to queue images. They may already be queued or processing.', 'ai-alt-gpt')
            ]);
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

    /**
     * Phase 2 Authentication AJAX Handlers
     */

    /**
     * AJAX handler: User registration
     */
    public function ajax_register() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'ai-alt-gpt')]);
        }

        $result = $this->api_client->register($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Account created successfully', 'ai-alt-gpt'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User login
     */
    public function ajax_login() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'ai-alt-gpt')]);
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'ai-alt-gpt'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     */
    public function ajax_logout() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        $this->api_client->clear_token();

        wp_send_json_success(['message' => __('Logged out successfully', 'ai-alt-gpt')]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'ai-alt-gpt'),
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
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please login to upgrade', 'ai-alt-gpt'),
                'code' => 'not_authenticated'
            ]);
        }

        $price_id = sanitize_text_field($_POST['price_id'] ?? '');
        if (empty($price_id)) {
            wp_send_json_error(['message' => __('Price ID is required', 'ai-alt-gpt')]);
        }

        $success_url = admin_url('upload.php?page=ai-alt-gpt&checkout=success');
        $cancel_url = admin_url('upload.php?page=ai-alt-gpt&checkout=cancel');

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
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please login to manage billing', 'ai-alt-gpt'),
                'code' => 'not_authenticated'
            ]);
        }

        $return_url = admin_url('upload.php?page=ai-alt-gpt');
        $result = $this->api_client->create_customer_portal_session($return_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'url' => $result['url'] ?? ''
        ]);
    }

    /**
     * AJAX handler: Forgot password request
     */
    public function ajax_forgot_password() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'ai-alt-gpt')
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
            'message' => __('Password reset link has been sent to your email. Please check your inbox and spam folder.', 'ai-alt-gpt'),
        ];
        
        // Include reset link if provided (for development/testing when email service isn't configured)
        if (isset($result['resetLink'])) {
            $response_data['resetLink'] = $result['resetLink'];
            $response_data['note'] = $result['note'] ?? __('Email service is in development mode. Use this link to reset your password.', 'ai-alt-gpt');
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Override plugin information to use our local readme.txt instead of fetching from WordPress.org
     * This prevents WordPress from showing wrong plugin details from repository
     */
    public function override_plugin_information($result, $action, $args) {
        // Get our plugin file path
        $plugin_file = AI_ALT_GPT_PLUGIN_BASENAME;
        $plugin_slug = dirname($plugin_file);
        
        // Check if this request is for our plugin
        $is_our_plugin = false;
        if (isset($args->slug)) {
            // Check against various possible slugs
            $possible_slugs = [
                $plugin_slug,
                'ai-alt-text-generator',
                'seo-ai-alt-text-generator',
                'seo-ai-alt-text-generator-auto-image-seo-accessibility',
                'ai-alt-gpt'
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
        if ($plugin_file === AI_ALT_GPT_PLUGIN_BASENAME) {
            // Ensure our plugin uses local data
            // WordPress will use readme.txt from the plugin directory
        }
        return $plugin_meta;
    }

    /**
     * AJAX handler: Reset password with token
     */
    public function ajax_reset_password() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'ai-alt-gpt')
            ]);
        }

        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Reset token is required', 'ai-alt-gpt')
            ]);
        }

        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long', 'ai-alt-gpt')
            ]);
        }

        $result = $this->api_client->reset_password($email, $token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now sign in with your new password.', 'ai-alt-gpt'),
            'redirect' => admin_url('upload.php?page=ai-alt-gpt&password_reset=success')
        ]);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        check_ajax_referer('alttextai_upgrade_nonce', 'nonce');

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please login to view subscription information', 'ai-alt-gpt'),
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
}

// Class instantiation moved to class-ai-alt-gpt-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Inline JS fallback to add row-action behaviour
add_action('admin_footer-upload.php', function(){
    ?>
    <script>
    (function($){
        function refreshDashboard(){
            if (!window.AI_ALT_GPT || !AI_ALT_GPT.restStats || !window.fetch){
                return;
            }

            var nonce = (AI_ALT_GPT.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''));
            var headers = {
                'X-WP-Nonce': nonce,
                    'Accept': 'application/json'
            };
            var statsUrl = AI_ALT_GPT.restStats + (AI_ALT_GPT.restStats.indexOf('?') === -1 ? '?' : '&') + 'fresh=1';
            var usageUrl = AI_ALT_GPT.restUsage || '';

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
                    var nonce = (AI_ALT_GPT && AI_ALT_GPT.nonce) ? AI_ALT_GPT.nonce : (window.wpApiSettings ? wpApiSettings.nonce : '');
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

        $(document).on('click', '.ai-alt-generate', function(e){
            e.preventDefault();
            if (!window.AI_ALT_GPT || !AI_ALT_GPT.rest){
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

            var headers = {'X-WP-Nonce': (AI_ALT_GPT.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''))};
            var context = btn.closest('.compat-item, .attachment-details, .media-modal');

            fetch(AI_ALT_GPT.rest + id, { method:'POST', headers: headers })
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
                        pushNotice('warning', (data.message || 'Monthly limit reached. Please upgrade to continue.'));
                        try { if (window.AltTextAI && AltTextAI.upgradeUrl) { window.open(AltTextAI.upgradeUrl, '_blank'); } } catch(e){}
                        if (jQuery('.alttextai-upgrade-banner').length){ jQuery('.alttextai-upgrade-banner .show-upgrade-modal').trigger('click'); }
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

// Ensure the same behaviour on the attachment edit screen (post.php for attachments)
add_action('admin_footer-post.php', function(){
    $screen = get_current_screen();
    if (empty($screen) || $screen->post_type !== 'attachment') { return; }
    ?>
    <script>
    (function($){
        // Reuse the exact same logic used on the upload.php screen
        $(document).off('click.ai-alt-generate').on('click', '.ai-alt-generate', function(e){
            e.preventDefault();
            if (!window.AI_ALT_GPT || !AI_ALT_GPT.rest){ return alert('AI ALT: REST URL missing.'); }
            var btn = $(this);
            var id = btn.data('id');
            if (!id){ return alert('AI ALT: Attachment ID missing.'); }
            var original = btn.text();
            btn.text('Generating…').prop('disabled', true);
            var headers = {'X-WP-Nonce': (AI_ALT_GPT.nonce || (window.wpApiSettings ? wpApiSettings.nonce : ''))};
            var context = $('.wrap');
            fetch(AI_ALT_GPT.rest + id, { method:'POST', headers: headers })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data && data.alt){
                        // Try to populate the visible alt field directly
                        var field = $('#attachment_alt');
                        if (!field.length){ field = $('textarea[name="_wp_attachment_image_alt"]'); }
                        if (!field.length){ field = $('textarea[aria-label="Alternative Text"]'); }
                        if (field.length){ field.val(data.alt).text(data.alt).attr('value', data.alt).trigger('input').trigger('change'); }
                        // Also persist via REST to be safe
                        try {
                            var mediaUrl = (window.wp && window.wpApiSettings && window.wpApiSettings.root) ? window.wpApiSettings.root : (window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', 'index.php?rest_route=/') : '/wp-json/');
                            var nonce = (AI_ALT_GPT && AI_ALT_GPT.nonce) ? AI_ALT_GPT.nonce : (window.wpApiSettings ? wpApiSettings.nonce : '');
                            if (mediaUrl && nonce){
                                fetch(mediaUrl + 'wp/v2/media/' + id, { method: 'POST', headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ alt_text: data.alt }) });
                            }
                        } catch(e){}
                    } else if (data && data.code === 'limit_reached'){
                        alert((data.message || 'Monthly limit reached. Please upgrade to continue.'));
                    } else {
                        var message = (data && (data.message || (data.data && data.data.message))) || 'Failed to generate ALT';
                        alert(message);
                    }
                })
                .catch(function(err){ alert((err && err.message) ? err.message : 'Request failed.'); })
                .then(function(){ btn.text(original || 'Generate Alt').prop('disabled', false); });
        });
    })(jQuery);
    </script>
    <?php
});

// Hide the Generate button inside the Media Library modal to avoid confusion until modal support is finalized
add_action('admin_head', function(){
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'upload') { return; }
    echo '<style>.attachment-details .ai-alt-generate{display:none!important;}</style>';
});
