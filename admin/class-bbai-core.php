<?php
/**
 * Core implementation for the Alt Text AI plugin.
 *
 * This file contains the original plugin implementation and is loaded
 * by the WordPress Plugin Boilerplate friendly bootstrap.
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

// Constants should be defined in main plugin file, but provide minimal fallbacks.
$bbai_plugin_file = '';
if (defined('BEEPBEEP_AI_PLUGIN_FILE') && is_string(BEEPBEEP_AI_PLUGIN_FILE) && BEEPBEEP_AI_PLUGIN_FILE !== '') {
    $bbai_plugin_file = BEEPBEEP_AI_PLUGIN_FILE;
} elseif (defined('BBAI_PLUGIN_FILE') && is_string(BBAI_PLUGIN_FILE) && BBAI_PLUGIN_FILE !== '') {
    $bbai_plugin_file = BBAI_PLUGIN_FILE;
}
if ($bbai_plugin_file === '') {
    $bbai_plugin_file = __FILE__;
}

if (!defined('BBAI_PLUGIN_FILE')) {
    define('BBAI_PLUGIN_FILE', $bbai_plugin_file);
}

if (!defined('BBAI_PLUGIN_DIR')) {
    $bbai_dir = (defined('BEEPBEEP_AI_PLUGIN_DIR') && is_string(BEEPBEEP_AI_PLUGIN_DIR) && BEEPBEEP_AI_PLUGIN_DIR !== '')
        ? BEEPBEEP_AI_PLUGIN_DIR
        : plugin_dir_path($bbai_plugin_file);
    define('BBAI_PLUGIN_DIR', $bbai_dir);
}

if (!defined('BBAI_PLUGIN_URL')) {
    $bbai_url = (defined('BEEPBEEP_AI_PLUGIN_URL') && is_string(BEEPBEEP_AI_PLUGIN_URL) && BEEPBEEP_AI_PLUGIN_URL !== '')
        ? BEEPBEEP_AI_PLUGIN_URL
        : plugin_dir_url($bbai_plugin_file);
    define('BBAI_PLUGIN_URL', $bbai_url);
}

if (!defined('BBAI_PLUGIN_BASENAME')) {
    $plugin_basename = (defined('BEEPBEEP_AI_PLUGIN_BASENAME') && is_string(BEEPBEEP_AI_PLUGIN_BASENAME) && BEEPBEEP_AI_PLUGIN_BASENAME !== '')
        ? BEEPBEEP_AI_PLUGIN_BASENAME
        : '';
    if ($plugin_basename === '') {
        $plugin_basename = plugin_basename($bbai_plugin_file);
    }
    if ($plugin_basename === '' || !is_string($plugin_basename)) {
        $plugin_basename = 'beepbeep-ai-alt-text-generator/beepbeep-ai-alt-text-generator.php';
    }
    define('BBAI_PLUGIN_BASENAME', $plugin_basename);
}

if (!defined('BBAI_VERSION')) {
    define('BBAI_VERSION', defined('BEEPBEEP_AI_VERSION') ? BEEPBEEP_AI_VERSION : '4.4.1');
}

// Load API clients, usage tracker, and queue infrastructure
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-api-client-v2.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-onboarding.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/services/class-auth-state.php';

// Load Core class traits
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-auth.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-license.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-billing.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-ajax-queue.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-media.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-generation.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-review.php';
require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/traits/trait-core-assets.php';

use BeepBeepAI\AltTextGenerator\Queue;
use BeepBeepAI\AltTextGenerator\Debug_Log;
use BeepBeepAI\AltTextGenerator\Usage_Tracker;
use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Auth;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_License;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Billing;
use BeepBeepAI\AltTextGenerator\Traits\Core_Ajax_Queue;
use BeepBeepAI\AltTextGenerator\Traits\Core_Media;
use BeepBeepAI\AltTextGenerator\Traits\Core_Generation;
use BeepBeepAI\AltTextGenerator\Traits\Core_Review;
use BeepBeepAI\AltTextGenerator\Traits\Core_Assets;

class Core {
    // Use traits for modular functionality
    use Core_Ajax_Auth;
    use Core_Ajax_License;
    use Core_Ajax_Billing;
    use Core_Ajax_Queue;
    use Core_Media;
    use Core_Generation;
    use Core_Review;
    use Core_Assets;
    const OPTION_KEY = 'bbai_settings';
    const NONCE_KEY  = 'bbai_nonce';
    const CAPABILITY = 'manage_bbbbai_text';

    private const DEFAULT_CHECKOUT_PRICE_IDS = [
        'pro'     => 'price_1SMrxaJl9Rm418cMM4iikjlJ',
        'growth'  => 'price_1SMrxaJl9Rm418cMM4iikjlJ', // alias for pro
        'agency'  => 'price_1SMrxaJl9Rm418cMnJTShXSY',
        'credits' => 'price_1SMrxbJl9Rm418cM0gkzZQZt',
    ];

    /**
     * Stripe Payment Link URLs (direct buy links that bypass checkout session creation).
     */
    private const DEFAULT_STRIPE_LINKS = [
        'pro'     => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
        'growth'  => 'https://buy.stripe.com/dRm28s4rc5Raf0GbY77ss02',
        'agency'  => 'https://buy.stripe.com/28E14og9U0wQ19Q4vF7ss01',
        'credits' => 'https://buy.stripe.com/6oU9AUf5Q2EYaKq0fp7ss00',
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
        $this->api_client = new API_Client_V2();
        // Soft-migrate legacy options to new prefixed keys
        $current = get_option(self::OPTION_KEY, null);
        if ($current === null) {
            foreach (['bbai_gpt_settings', 'beepbeepai_settings', 'opptibbai_settings', 'bbai_settings'] as $legacy_key) {
                $legacy_value = get_option($legacy_key, null);
                if ($legacy_value !== null) {
                    update_option(self::OPTION_KEY, $legacy_value, false);
                    break;
                }
            }
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            // Ensure table exists
            Debug_Log::create_table();
            
            // Log initialization
            Debug_Log::log('info', 'AI Alt Text plugin initialized', [
                'version' => BBAI_VERSION,
                'authenticated' => $this->api_client->is_authenticated() ? 'yes' : 'no',
            ], 'core');
            
            update_option('bbai_logs_ready', true, false);
        }

        // Initialize credit usage logger hooks
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::init_hooks();

        // Check for migration on admin init
        add_action('admin_init', [__CLASS__, 'maybe_run_migration'], 5);
    }

    /**
     * Check and run migration if needed.
     */
    public static function maybe_run_migration() {
        // Only run in admin and if not already migrated
        if (!is_admin()) {
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
        if (!\BeepBeepAI\AltTextGenerator\Migrate_Usage::is_migrated()) {
            // Run migration in background (don't block admin page load)
            // Migration will run on first admin page load after activation
            if (!wp_next_scheduled('beepbeepai_run_migration')) {
                wp_schedule_single_event(time() + 30, 'beepbeepai_run_migration');
            }
        }
    }

    /**
     * Run migration (called by cron).
     */
    public static function run_migration() {
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-bbai-migrate-usage.php';
        \BeepBeepAI\AltTextGenerator\Migrate_Usage::migrate();
    }

    /**
     * Expose API client for collaborators (REST controller, admin UI, etc.).
     *
     * @return API_Client_V2
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

    /**
     * Get post meta with backward compatibility for old ai_alt_ keys.
     * Automatically migrates old keys to new beepbeepai_ keys.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param bool   $single  Whether to return a single value.
     * @return mixed Meta value.
     */
    private function get_meta_with_compat($post_id, $key, $single = true) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        // Check for new key first
        $value = get_post_meta($post_id, $new_key, $single);
        if ($value !== '' && $value !== false && $value !== null) {
            return $value;
        }
        
        // Check for old key and migrate if found
        $old_value = get_post_meta($post_id, $old_key, $single);
        if ($old_value !== '' && $old_value !== false && $old_value !== null) {
            // Migrate to new key
            update_post_meta($post_id, $new_key, $old_value);
            // Delete old key after migration
            delete_post_meta($post_id, $old_key);
            return $old_value;
        }
        
        return $single ? '' : [];
    }
    
    /**
     * Update post meta using new beepbeepai_ prefix.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @param mixed  $value   Meta value.
     * @return bool|int Result of update_post_meta.
     */
    private function update_meta_with_compat($post_id, $key, $value) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        // Update new key
        $result = update_post_meta($post_id, $new_key, $value);
        
        // Delete old key if it exists (migration cleanup)
        if (metadata_exists('post', $post_id, $old_key)) {
            delete_post_meta($post_id, $old_key);
        }
        
        return $result;
    }
    
    /**
     * Delete post meta from both old and new keys.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key (without prefix).
     * @return bool Result of delete_post_meta.
     */
    private function delete_meta_with_compat($post_id, $key) {
        $new_key = '_beepbeepai_' . $key;
        $old_key = '_ai_alt_' . $key;
        
        $result1 = delete_post_meta($post_id, $new_key);
        $result2 = delete_post_meta($post_id, $old_key);
        
        return $result1 || $result2;
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
                __('AI Alt Text token usage alert', 'beepbeep-ai-alt-text-generator'),
                sprintf(
                    /* translators: 1: total tokens, 2: token limit */
                    __('Cumulative token usage has reached %1$d (threshold %2$d). Consider reviewing your OpenAI usage.', 'beepbeep-ai-alt-text-generator'),
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

        Usage_Tracker::update_usage($latest_usage);
        set_transient($cache_key, time(), MINUTE_IN_SECONDS);
    }

    private function get_debug_bootstrap($force_refresh = false) {
        if ($force_refresh || $this->debug_bootstrap === null) {
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                $this->debug_bootstrap = Debug_Log::get_logs([
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
                        'last_event' => null,
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
            return false;
        }
        $result = wp_mail($email, $subject, $message);
        if (!$result && class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Email notification failed to send', [
                'email' => $email,
                'subject' => $subject
            ]);
        }
        return $result;
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
	        $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	        $page = is_string($page_raw) ? sanitize_key($page_raw) : '';
	        if ($page !== 'bbai-checkout') { return; }

	        $action = 'bbai_direct_checkout';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_bbai_nonce'] ?? '' ) ), $action ) ) {
	            wp_die(esc_html__('Security check failed. Please try again from the dashboard.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('You do not have permission to perform this action.', 'beepbeep-ai-alt-text-generator'));
	        }

        $plan_raw = isset($_GET['plan']) ? wp_unslash($_GET['plan']) : (isset($_GET['type']) ? wp_unslash($_GET['type']) : '');
        $plan_param = sanitize_key($plan_raw);
        $price_id_raw = isset($_GET['price_id']) && $_GET['price_id'] !== null ? wp_unslash($_GET['price_id']) : '';
        $price_id = is_string($price_id_raw) ? sanitize_text_field($price_id_raw) : '';
        $fallback = Usage_Tracker::get_upgrade_url();

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
            $message = is_wp_error($result) ? $result->get_error_message() : __('Unable to start checkout. Please try again.', 'beepbeep-ai-alt-text-generator');
            $query_args = [
                'page'            => 'beepbeep-ai-alt-text-generator',
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
	        $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	        $page = is_string($page_raw) ? sanitize_key($page_raw) : '';
	        if ($page !== 'beepbeep-ai-alt-text-generator') {
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
                    <strong><?php esc_html_e('Unable to start checkout', 'beepbeep-ai-alt-text-generator'); ?>:</strong>
                    <?php echo esc_html($message); ?>
                    <?php if ($plan) : ?>
                        (<?php
                        /* translators: 1: plan name */
                        echo esc_html(sprintf(__('Plan: %s', 'beepbeep-ai-alt-text-generator'), $plan));
                        ?>)
                    <?php endif; ?>
                </p>
                <p><?php esc_html_e('Please check your account connection and try again. If the problem persists, contact support.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        } else {
            $checkout = isset($_GET['checkout']) ? sanitize_key(wp_unslash($_GET['checkout'])) : '';
            if ($checkout === 'success') {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Redirecting to secure checkout... Complete your payment to unlock up to 1,000 alt text generations per month with Growth.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <?php
            } elseif ($checkout === 'cancel') {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php esc_html_e('Checkout cancelled. Your plan remains unchanged. Upgrade anytime to unlock 1,000 generations per month with Growth.', 'beepbeep-ai-alt-text-generator'); ?></p>
                </div>
                <?php
            }
        }

        // Password reset notices
        $password_reset = isset($_GET['password_reset']) ? sanitize_key(wp_unslash($_GET['password_reset'])) : '';
        if ($password_reset === 'requested') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Email Sent', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Check your email inbox (and spam folder) for password reset instructions. The link will expire in 1 hour.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        } elseif ($password_reset === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Password Reset Successful', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your password has been updated. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        // Subscription update notices
        $subscription_updated = isset($_GET['subscription_updated']) ? sanitize_key(wp_unslash($_GET['subscription_updated'])) : '';
        if (!empty($subscription_updated)) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Subscription Updated', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your subscription information has been refreshed.', 'beepbeep-ai-alt-text-generator'); ?></p>
            </div>
            <?php
        }

        $portal_return = isset($_GET['portal_return']) ? sanitize_key(wp_unslash($_GET['portal_return'])) : '';
        if ($portal_return === 'success') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('Billing Updated', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                <p><?php esc_html_e('Your billing information has been updated successfully. Changes may take a few moments to reflect.', 'beepbeep-ai-alt-text-generator'); ?></p>
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
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(sprintf(
            /* translators: 1: tokens used, 2: token threshold */
            __('BeepBeep AI – Alt Text Generator has used %1$s tokens (threshold %2$s). Consider reviewing usage.', 'beepbeep-ai-alt-text-generator'),
            $total,
            $limit
        )) . '</p></div>';
        $this->token_notice = null;
    }

    public function maybe_render_queue_notice(){
        if (!isset($_GET['bbai_queued'])) {
            return;
        }
        $count_raw = isset($_GET['bbai_queued']) ? wp_unslash($_GET['bbai_queued']) : '';
        $count = absint($count_raw);
        if ($count <= 0) {
            return;
        }
        $message = $count === 1
            ? __('1 image queued for background optimisation. The alt text will appear shortly.', 'beepbeep-ai-alt-text-generator')
            : sprintf(
                /* translators: 1: number of images queued */
                __('Queued %d images for background optimisation. Alt text will be generated shortly.', 'beepbeep-ai-alt-text-generator'),
                $count
            );
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

        // Migrate legacy option to plugin-prefixed name (site-wide).
        $legacy_dismissed = get_option('wp_alt_text_api_notice_dismissed', null);
        if (null !== $legacy_dismissed) {
            update_option('bbai_api_notice_dismissed', (bool) $legacy_dismissed, false);
            delete_option('wp_alt_text_api_notice_dismissed');
        }

        // Check if modal has been dismissed (site-wide option, shows once for all users)
        $dismissed = get_option('bbai_api_notice_dismissed', false);
        if ($dismissed) {
            return;
        }

        // Show modal popup if not dismissed
        $api_url = 'https://alttext-ai-backend.onrender.com';
        $privacy_url = 'https://oppti.dev/privacy';
        $terms_url = 'https://oppti.dev/terms';
        $nonce = wp_create_nonce('beepbeepai_nonce');
        ?>
        <div id="bbai-api-notice-modal" class="bbai-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="bbai-api-notice-title" aria-describedby="bbai-api-notice-desc">
            <div class="bbai-upgrade-modal__content bbai-api-notice-modal-content">
                <div class="bbai-upgrade-modal__header">
                    <div class="bbai-upgrade-modal__header-content">
                        <h2 id="wp-alt-text-api-notice-title"><?php esc_html_e('External Service Notice', 'beepbeep-ai-alt-text-generator'); ?></h2>
                    </div>
                    <button type="button" class="bbai-modal-close" onclick="bbaiCloseApiNotice();" aria-label="<?php esc_attr_e('Close notice', 'beepbeep-ai-alt-text-generator'); ?>">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="bbai-upgrade-modal__body bbai-api-notice-body" id="bbai-api-notice-desc">
                    <p class="bbai-api-notice-text">
                        <?php esc_html_e('This plugin connects to an external API service to generate alt text. Image data and site/account identifiers may be transmitted for processing, and account/usage data may be stored locally. See the Privacy Policy for details.', 'beepbeep-ai-alt-text-generator'); ?>
                    </p>

                    <div class="bbai-api-notice-box">
                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('API Endpoint:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <?php echo esc_html($api_url); ?>
                        </p>

                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('Privacy Policy:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener" class="bbai-api-notice-link">
                                <?php echo esc_html($privacy_url); ?>
                            </a>
                        </p>

                        <p class="bbai-api-notice-label">
                            <?php esc_html_e('Terms of Service:', 'beepbeep-ai-alt-text-generator'); ?>
                        </p>
                        <p class="bbai-api-notice-value">
                            <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener" class="bbai-api-notice-link">
                                <?php echo esc_html($terms_url); ?>
                            </a>
                        </p>
                    </div>
                </div>

                <div class="bbai-upgrade-modal__footer bbai-api-notice-footer">
                    <button type="button" class="button button-primary" onclick="bbaiCloseApiNotice();">
                        <?php esc_html_e('Got it', 'beepbeep-ai-alt-text-generator'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function deactivate(){
        wp_clear_scheduled_hook(Queue::CRON_HOOK);
    }

    public function activate() {
        global $wpdb;

        Queue::create_table();
        Queue::schedule_processing(10);
        Debug_Log::create_table();
        update_option('bbai_logs_ready', true, false);

        // Create credit usage table
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::create_table();

        // Create usage logs table for multi-user visualization
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
        \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::create_table();
        
        // Create contact submissions table
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';
        \BeepBeepAI\AltTextGenerator\Contact_Submissions::create_table();
        
        // Migrate existing usage logs table to include site_id if needed
        $this->migrate_usage_logs_table();

        // Generate site fingerprint (one-time per site)
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
        \BeepBeepAI\AltTextGenerator\Site_Fingerprint::generate();
        
        // Ensure site identifier exists
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        \BeepBeepAI\AltTextGenerator\get_site_identifier();
        
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
		        $postmeta_table = esc_sql( $wpdb->postmeta );
		        $posts_table = esc_sql( $wpdb->posts );
		        
		        // Index for _bbai_generated_at (used in sorting and stats)
		        $wpdb->query(
			            $wpdb->prepare(
			                "CREATE INDEX idx_bbai_generated_at ON `{$postmeta_table}` (meta_key(50), meta_value(50)) /* %d */",
			                1
			            )
			        );
	        
		        // Index for _bbai_source (used in stats aggregation)
		        $wpdb->query(
			            $wpdb->prepare(
			                "CREATE INDEX idx_bbai_source ON `{$postmeta_table}` (meta_key(50), meta_value(50)) /* %d */",
			                1
			            )
			        );
	        
		        // Index for _wp_attachment_image_alt (used in coverage stats)
		        $wpdb->query(
			            $wpdb->prepare(
			                "CREATE INDEX idx_wp_attachment_alt ON `{$postmeta_table}` (meta_key(50), meta_value(100)) /* %d */",
			                1
			            )
			        );
	        
		        // Composite index for attachment queries
		        $wpdb->query(
				            $wpdb->prepare(
				                "CREATE INDEX idx_posts_attachment_image ON `{$posts_table}` (post_type(20), post_mime_type(20), post_status(20)) /* %d */",
				                1
				            )
				        );
		    }

    /**
     * Migrate usage logs table to include site_id column (backwards compatibility)
     */
    private function migrate_usage_logs_table() {
        global $wpdb;
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
        
				        $table_name = esc_sql( \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::table() );
		        
				        // Check if site_id column exists
				        $column_exists = $wpdb->get_results(
					            $wpdb->prepare(
					                "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
					                'site_id'
				            )
				        );
        
        if (empty($column_exists)) {
            // Add site_id column
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
            $site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
            
		            $wpdb->query(
			                $wpdb->prepare(
			                    "ALTER TABLE `{$table_name}` ADD COLUMN site_id VARCHAR(64) NOT NULL DEFAULT %s AFTER user_id, ADD KEY site_id (site_id), ADD KEY site_created (site_id, created_at) /* %d */",
			                    '',
			                    1
			                )
		            );
            
            // Update existing rows with current site_id
		            $wpdb->query(
				                $wpdb->prepare(
				                    "UPDATE `{$table_name}` SET site_id = %s WHERE site_id = %s",
				                    $site_id,
				                    ''
				                )
		            );
	        }
	    }

    public function add_settings_page() {
        $cap = current_user_can(self::CAPABILITY) ? self::CAPABILITY : 'manage_options';
        
        // Check authentication status (same logic as render_settings_page)
        try {
            $is_authenticated = $this->api_client->is_authenticated();
            $has_license = $this->api_client->has_active_license();
        } catch (Exception $e) {
            $is_authenticated = false;
            $has_license = false;
        } catch (Error $e) {
            $is_authenticated = false;
            $has_license = false;
        }
        
        // Also check stored credentials
        $stored_token = get_option('beepbeepai_jwt_token', '');
        $has_stored_token = !empty($stored_token);
        
        $stored_license = '';
        try {
            $stored_license = $this->api_client->get_license_key();
        } catch (Exception $e) {
            $stored_license = '';
        } catch (Error $e) {
            $stored_license = '';
        }
        $has_stored_license = !empty($stored_license);
        
        // Determine if user is registered (authenticated/licensed)
        $has_registered_user = $is_authenticated || $has_license || $has_stored_token || $has_stored_license;
        
        // Only show menu items if user is registered
        if ($has_registered_user) {
            // Top-level menu uses the brand name; the first submenu is "Dashboard".
            add_menu_page(
                __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
                __('BeepBeep AI', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai',
                [$this, 'render_settings_page'],
                'dashicons-format-image',
                30
            );

            // Explicit first submenu replaces the auto-generated duplicate.
            // Order matches the top header nav.
            add_submenu_page(
                'bbai',
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-library',
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
                __('Credit Usage', 'beepbeep-ai-alt-text-generator'),
                __('Credit Usage', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-credit-usage',
                [$this, 'render_settings_page']
            );

            add_submenu_page(
                'bbai',
                __('How to', 'beepbeep-ai-alt-text-generator'),
                __('How to', 'beepbeep-ai-alt-text-generator'),
                $cap,
                'bbai-guide',
                [$this, 'render_settings_page']
            );

            // Settings and Debug only for authenticated/licensed users
            if ($is_authenticated || $has_license || $has_stored_token || $has_stored_license) {
                add_submenu_page(
                    'bbai',
                    __('Settings', 'beepbeep-ai-alt-text-generator'),
                    __('Settings', 'beepbeep-ai-alt-text-generator'),
                    $cap,
                    'bbai-settings',
                    [$this, 'render_settings_page']
                );

                add_submenu_page(
                    'bbai',
                    __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                    __('Debug Logs', 'beepbeep-ai-alt-text-generator'),
                    $cap,
                    'bbai-debug',
                    [$this, 'render_settings_page']
                );
            }
        }

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
	            'bbai-onboarding',
	            [$this, 'render_bbai_onboarding_step2']
	        );
	    }

	    public function handle_checkout_redirect() {
	        // Verify nonce for CSRF protection (first).
	        $action = 'bbai_checkout_redirect';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), $action ) ) {
	            wp_die(esc_html__('Security check failed.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('Unauthorized access.', 'beepbeep-ai-alt-text-generator'));
	        }

	        if (!$this->api_client->is_authenticated()) {
	            wp_die(esc_html__('Please sign in first to upgrade.', 'beepbeep-ai-alt-text-generator'));
	        }

        $price_id_raw = isset($_GET['price_id']) ? wp_unslash($_GET['price_id']) : '';
        $price_id = is_string($price_id_raw) ? sanitize_text_field($price_id_raw) : '';
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

        if (wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!$this->user_can_manage()) {
            return;
        }

	        $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	        $current_page = is_string($page_raw) ? sanitize_key($page_raw) : '';
	        $current_step = isset($_GET['step']) ? absint(wp_unslash($_GET['step'])) : 0;
	        $is_onboarding_page = ($current_page === 'bbai-onboarding');
	        $is_step3 = ($is_onboarding_page && $current_step === 3);

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Auth_State') || !class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            return;
        }

        $auth = \BeepBeepAI\AltTextGenerator\Auth_State::resolve($this->api_client);
        if (empty($auth['has_registered_user'])) {
            return;
        }

        $completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed();

        if ($completed) {
            // Allow onboarding pages to be viewed even if completed (user may want to revisit the guide)
            // Only redirect away from non-plugin pages
            return;
        }

        if (!$is_onboarding_page) {
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
                        <h1 class="bbai-page-title"><?php esc_html_e('Welcome to BeepBeep AI', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Generate SEO-ready, accessible alt text for your WordPress media library', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 1 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Welcome', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('What BeepBeep AI does for you', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e('Our AI analyzes your images and generates descriptive, SEO-friendly alt text automatically.', 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>

                        <div class="bbai-onboarding-features bbai-mt-4">
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Improve Accessibility', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Help screen readers describe your images to visually impaired visitors.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Boost SEO Rankings', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Search engines use alt text to understand and index your images.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                            <div class="bbai-feature-item">
                                <span class="bbai-feature-icon">✓</span>
                                <div class="bbai-feature-content">
                                    <strong><?php esc_html_e('Save Time', 'beepbeep-ai-alt-text-generator'); ?></strong>
                                    <p><?php esc_html_e('Generate alt text for hundreds of images in minutes, not hours.', 'beepbeep-ai-alt-text-generator'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bbai-btn-group bbai-mt-6">
                            <a href="<?php echo esc_url($step2_url); ?>" class="bbai-btn bbai-btn-primary">
                                <?php esc_html_e('Get Started', 'beepbeep-ai-alt-text-generator'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="bbai-grid bbai-grid-3 bbai-mb-6">
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon bbai-onboarding-step-icon--active" aria-hidden="true">
                                <span class="bbai-step-number">1</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Learn what BeepBeep AI can do', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">2</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Scan and generate alt text', 'beepbeep-ai-alt-text-generator'); ?></p>
                        </div>
                    </div>
                    <div class="bbai-card bbai-card--compact bbai-onboarding-step-card">
                        <div class="bbai-card-body">
                            <span class="bbai-onboarding-step-icon" aria-hidden="true">
                                <span class="bbai-step-number">3</span>
                            </span>
                            <p class="bbai-onboarding-step-text"><?php esc_html_e('Review and start using', 'beepbeep-ai-alt-text-generator'); ?></p>
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
                        <h1 class="bbai-page-title"><?php esc_html_e('Getting started', 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Generate SEO-ready, accessible alt text for your media library', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 2 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Optimize your images', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bbai-card bbai-card--large bbai-onboarding-hero bbai-mb-6">
                    <div class="bbai-card-body">
                        <h2 class="bbai-card-title"><?php esc_html_e('Generate alt text for your images', 'beepbeep-ai-alt-text-generator'); ?></h2>
                        <p class="bbai-card-subtitle"><?php esc_html_e("We'll scan your media library and generate alt text only for images that are missing it.", 'beepbeep-ai-alt-text-generator'); ?></p>
                        <div class="bbai-onboarding-divider" aria-hidden="true"></div>
                        <div class="bbai-btn-group bbai-mt-4">
                            <button type="button" class="bbai-btn bbai-btn-primary" data-bbai-onboarding-action="start-scan">
                                <?php esc_html_e('Generate alt text', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
                            <button type="button" class="bbai-btn bbai-btn-secondary" data-bbai-onboarding-action="skip">
                                <?php esc_html_e('Skip for now', 'beepbeep-ai-alt-text-generator'); ?>
                            </button>
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
                                <h3 class="bbai-card-title"><?php esc_html_e('Generate alt text', 'beepbeep-ai-alt-text-generator'); ?></h3>
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
        $checkout_prices = $this->get_checkout_price_ids();
        include BBAI_PLUGIN_DIR . 'templates/upgrade-modal.php';
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
        // TODO: Update this if ALT Library slug changes
        $library_url = admin_url('admin.php?page=bbai-library');
        $is_authenticated = bbai_is_authenticated();
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
                        <h1 class="bbai-page-title"><?php esc_html_e("You're ready to review", 'beepbeep-ai-alt-text-generator'); ?></h1>
                        <p class="bbai-page-subtitle"><?php esc_html_e('Alt text is generating in the background. You can start reviewing immediately.', 'beepbeep-ai-alt-text-generator'); ?></p>
                    </div>
                    <div class="bbai-page-header-actions">
                        <div class="bbai-onboarding-progress">
                            <span class="bbai-onboarding-progress-text"><?php esc_html_e('Step 3 of 3', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-badge bbai-badge--getting-started"><?php esc_html_e('Setup complete', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$is_authenticated) : ?>
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
                        <h2 class="bbai-card-title"><?php esc_html_e('Review your first results', 'beepbeep-ai-alt-text-generator'); ?></h2>
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
        $checkout_prices = $this->get_checkout_price_ids();
        include BBAI_PLUGIN_DIR . 'templates/upgrade-modal.php';
    }

    public function render_settings_page() {
        if (!$this->user_can_manage()) return;

        // Allocate free credits on first dashboard view so usage displays correctly
        Usage_Tracker::allocate_free_credits_if_needed();

        $opts  = get_option(self::OPTION_KEY, []);
        $stats = $this->get_media_stats();
        $nonce = wp_create_nonce(self::NONCE_KEY);
        
        // Check if there's a registered user (authenticated or has active license)
        // Use try-catch to prevent errors from breaking the page
	        try {
	            $is_authenticated = $this->api_client->is_authenticated();
	            $has_license = $this->api_client->has_active_license();
		        } catch (Exception $e) {
		            // If authentication check fails, default to showing limited tabs
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Authentication check failed: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
		            $is_authenticated = false;
		            $has_license = false;
		        } catch (Error $e) {
		            // Catch fatal errors too
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Authentication check fatal error: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
		            $is_authenticated = false;
		            $has_license = false;
		        }
        
        // Also check if user has a token stored in options (indicates they've logged in before)
        // Token is stored in beepbeepai_jwt_token option (not in beepbeepai_settings)
        $stored_token = get_option('beepbeepai_jwt_token', '');
        $has_stored_token = !empty($stored_token);
        
        // Check for license key using the API client method (handles both new and legacy storage)
        $stored_license = '';
        try {
            $stored_license = $this->api_client->get_license_key();
        } catch (Exception $e) {
            $stored_license = '';
        } catch (Error $e) {
            $stored_license = '';
        }
        $has_stored_license = !empty($stored_license);
        
        // Update authentication flags to include stored credentials
        // This ensures tabs show even if current API validation fails
        $is_authenticated = $is_authenticated || $has_stored_token;
        $has_license = $has_license || $has_stored_license;
        
        // Consider user registered if authenticated, has license, or has stored credentials
        // This ensures tabs show even if current auth check fails
        $has_registered_user = $is_authenticated || $has_license || $has_stored_token || $has_stored_license;
	        $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	        $bbai_page_slug = is_string($page_raw) ? sanitize_key($page_raw) : '';
        
        // Initialize $tabs array (will be populated in if/else blocks below)
        $tabs = [];
        
        // No tabs for non-registered users - show onboarding page only
        if (!$has_registered_user) {
            $tabs = [];
            $tab = 'dashboard'; // Default to dashboard/onboarding view
            $is_pro_for_admin = false;
            $is_agency_for_admin = false;
        } else {
            // Determine if agency license
            // Check API first, then include stored credentials
            $has_license_api = $this->api_client->has_active_license();
            $has_license = $has_license_api || $has_stored_license;
            $license_data = $this->api_client->get_license_data();
            $plan_slug = 'free'; // Default to free
            
            // If using license, check license plan
            if ($has_license && $license_data && isset($license_data['organization'])) {
                $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                if ($license_plan !== 'free') {
                    $plan_slug = $license_plan;
                }
            } elseif ($is_authenticated) {
                // For authenticated users without license, try to get plan from usage stats
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $usage_stats = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_stats_display(false);
                if (isset($usage_stats['plan']) && $usage_stats['plan'] !== 'free') {
                    $plan_slug = $usage_stats['plan'];
                }
            }
            
            $is_agency = ($plan_slug === 'agency');
            $is_pro = ($plan_slug === 'pro' || $plan_slug === 'agency');
            
            // Build tabs array for registered users
            // Core tabs always available
            $tabs = [
                'dashboard'    => __('Dashboard', 'beepbeep-ai-alt-text-generator'),
                'library'      => __('ALT Library', 'beepbeep-ai-alt-text-generator'),
                'analytics'    => __('Analytics', 'beepbeep-ai-alt-text-generator'),
                'credit-usage' => __('Credit Usage', 'beepbeep-ai-alt-text-generator'),
                'guide'        => __('How to', 'beepbeep-ai-alt-text-generator'),
            ];
            
            // Additional tabs for authenticated/licensed users
            if ($is_authenticated || $has_license) {
                $tabs['settings'] = __('Settings', 'beepbeep-ai-alt-text-generator');
                $tabs['debug'] = __('Debug Logs', 'beepbeep-ai-alt-text-generator');
            }
            
            // Admin tab for Pro/Agency plans (replaces Debug Logs and Settings)
            if ($is_pro) {
                // Remove individual debug/settings tabs and add admin tab
                unset($tabs['debug'], $tabs['settings']);
                $tabs['admin'] = __('Admin', 'beepbeep-ai-alt-text-generator');
            }
            
            // Agency Overview tab for Agency plans only
            if ($is_agency) {
                $tabs['agency-overview'] = __('Agency Overview', 'beepbeep-ai-alt-text-generator');
            }
            
            // Map page slugs to tab slugs (for determining current tab from URL)
            $page_to_tab = [
                'bbai' => 'dashboard',
                'bbai-library' => 'library',
                'bbai-analytics' => 'analytics',
                'bbai-credit-usage' => 'credit-usage',
                'bbai-guide' => 'guide',
                'bbai-settings' => 'settings',
                'bbai-debug' => 'debug',
                'bbai-agency-overview' => 'agency-overview',
            ];
            
	            // Determine current tab from URL
	            $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : 'bbai';
	            $current_page = is_string($page_raw) ? sanitize_key($page_raw) : 'bbai';
	            $tab_from_page = $page_to_tab[$current_page] ?? 'dashboard';
	            
	            // Use tab from URL parameter if provided, otherwise use page slug mapping
	            $tab_raw = isset($_GET['tab']) ? wp_unslash($_GET['tab']) : '';
	            $tab = (is_string($tab_raw) && $tab_raw !== '') ? sanitize_key($tab_raw) : $tab_from_page;

            // If trying to access restricted tabs, redirect to dashboard
            if (!isset($tabs) || !is_array($tabs) || !in_array($tab, array_keys($tabs))) {
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
                            <span class="bbai-logo-text"><?php esc_html_e('BeepBeep AI – Alt Text Generator', 'beepbeep-ai-alt-text-generator'); ?></span>
                            <span class="bbai-logo-tagline"><?php esc_html_e('WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?></span>
                        </div>
                    </div>
                    <nav class="bbai-nav" role="navigation" aria-label="<?php esc_attr_e('Main navigation', 'beepbeep-ai-alt-text-generator'); ?>">
                        <?php
                        // Ensure $tabs is defined (empty array is valid for non-registered users)
                        if (!isset($tabs) || !is_array($tabs)) {
                            $tabs = [];
                        }

                        // Map tab slugs to page slugs for proper URL generation
                        $tab_to_page = [
                            'dashboard' => 'bbai',
                            'library' => 'bbai-library',
                            'analytics' => 'bbai-analytics',
                            'credit-usage' => 'bbai-credit-usage',
                            'guide' => 'bbai-guide',
                            'settings' => 'bbai-settings',
                            'debug' => 'bbai-debug', // Debug now has its own page
                            'admin' => 'bbai', // Admin uses dashboard page with tab parameter
                            'agency-overview' => 'bbai', // Agency Overview uses dashboard page with tab parameter
                        ];
                        
                        foreach ($tabs as $slug => $label) :
                            // Generate proper URL using page slug if available, otherwise use tab parameter
                            $page_slug = $tab_to_page[$slug] ?? 'bbai';
                            if ($slug === 'dashboard' || $slug === 'admin' || $slug === 'agency-overview') {
                                // These tabs use the main page with tab parameter
                                $url = admin_url('admin.php?page=' . $page_slug . ($slug !== 'dashboard' ? '&tab=' . $slug : ''));
                            } else {
                                // Other tabs use their dedicated page
                                $url = admin_url('admin.php?page=' . $page_slug);
                            }
                            
                            $active = (isset($tab) && $tab === $slug) ? ' active' : '';
                            $aria_current = $active ? ' aria-current="page"' : '';
                        ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="bbai-nav-link<?php echo esc_attr($active); ?>"
                               <?php echo esc_attr($aria_current); ?>>
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endforeach; ?>
                        <!-- Help/Guide Link (inside nav) -->
                        <a href="<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>" class="bbai-nav-link" id="bbai-guide-link" title="<?php esc_attr_e('Getting started guide', 'beepbeep-ai-alt-text-generator'); ?>" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=bbai-onboarding')); ?>'; return false;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="margin-right:4px;">
                                <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 11.5V11M8 9C8 7.5 9.5 7.5 9.5 6C9.5 5.17 8.83 4.5 8 4.5C7.17 4.5 6.5 5.17 6.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Guide', 'beepbeep-ai-alt-text-generator'); ?>
                        </a>
                    </nav>
                    <!-- Auth & Subscription Actions -->
                    <div class="bbai-header-actions">
                        <?php
                        // Use stored credentials check (same as tab rendering logic)
                        // This ensures header shows correct state even if API validation fails
                        $has_license_api = $this->api_client->has_active_license();
                        $is_authenticated_api = $this->api_client->is_authenticated();
                        $has_license = $has_license_api || $has_stored_license;
                        $is_authenticated = $is_authenticated_api || $has_stored_token;

                        if ($is_authenticated || $has_license) :
                            $usage_stats = Usage_Tracker::get_stats_display();
                            $account_summary = $is_authenticated ? $this->get_account_summary($usage_stats) : null;
                            $plan_slug  = $usage_stats['plan'] ?? 'free';
                            $plan_label = isset($usage_stats['plan_label']) ? (string)$usage_stats['plan_label'] : ucfirst($plan_slug);
                            $connected_email = isset($account_summary['email']) ? (string)$account_summary['email'] : '';
                            $billing_portal = Usage_Tracker::get_billing_portal_url();

                            // If license-only mode (no personal login), show license info
                            if ($has_license && !$is_authenticated) {
                                $license_data = $this->api_client->get_license_data();
                                $org_name = isset($license_data['organization']['name']) ? (string)$license_data['organization']['name'] : '';
                                $connected_email = $org_name ?: __('License Active', 'beepbeep-ai-alt-text-generator');
                            }
                        ?>
                            <!-- Compact Account Bar in Header -->
                            <div class="bbai-header-account-bar">
                                <span class="bbai-header-account-email"><?php echo esc_html(is_string($connected_email) ? $connected_email : __('Connected', 'beepbeep-ai-alt-text-generator')); ?></span>
                                <span class="bbai-header-plan-badge"><?php echo esc_html(is_string($plan_label) ? $plan_label : ucfirst($plan_slug ?? 'free')); ?></span>
                                <?php if ($plan_slug === 'free' && !$has_license) : ?>
                                    <button type="button" class="bbai-header-upgrade-btn" data-action="show-upgrade-modal">
                                        <?php esc_html_e('Upgrade to Growth — 1,000 Generations Monthly', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php elseif (!empty($billing_portal) && $is_authenticated) : ?>
                                    <button type="button" class="bbai-header-manage-btn" data-action="open-billing-portal">
                                        <?php esc_html_e('Manage', 'beepbeep-ai-alt-text-generator'); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($is_authenticated || $has_license) : ?>
                                <button type="button" class="bbai-header-logout-btn" data-action="logout">
                                    <?php esc_html_e('Logout', 'beepbeep-ai-alt-text-generator'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <button type="button" class="bbai-header-login-btn" data-action="show-auth-modal" data-auth-tab="login">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M10 14H13C13.5523 14 14 13.5523 14 13V3C14 2.44772 13.5523 2 13 2H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M5 11L2 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M2 8H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <span><?php esc_html_e('Login', 'beepbeep-ai-alt-text-generator'); ?></span>
                            </button>
                        <?php endif; ?>
                </div>
                </div>
            </div>
            
            <!-- Main Content Container -->
            <div class="bbai-container">

            <?php if ($tab === 'dashboard') : ?>
    <?php
    $dashboard_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/dashboard-tab.php';
    if (file_exists($dashboard_partial)) {
        include $dashboard_partial;
    } else {
        esc_html_e('Dashboard content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>

<?php elseif ($tab === 'library' && ($is_authenticated || $has_license)) : ?>
    <?php
    $library_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/library-tab.php';
    if (file_exists($library_partial)) {
        include $library_partial;
    } else {
        esc_html_e('Library content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>

<?php elseif ($tab === 'debug') : ?>
    <?php
    $debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
    if (file_exists($debug_partial)) {
        include $debug_partial;
    } else {
        esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
<?php elseif ($tab === 'guide' || $bbai_page_slug === 'bbai-guide') : ?>
            <?php
            $guide_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/guide-tab.php';
            if (file_exists($guide_partial)) {
                include $guide_partial;
            } else {
                esc_html_e('Guide content unavailable.', 'beepbeep-ai-alt-text-generator');
            }
            ?>

<?php elseif ($tab === 'credit-usage' && ($is_authenticated || $has_license)) : ?>
    <?php
    $credit_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/credit-usage-tab.php';
    if (file_exists($credit_partial)) {
        include $credit_partial;
    } else {
        esc_html_e('Credit usage content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
<?php elseif ($tab === 'agency-overview' && $is_agency) : ?>
    <?php
    $agency_overview_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/agency-overview-tab.php';
    if (file_exists($agency_overview_partial)) {
        include $agency_overview_partial;
    } else {
        esc_html_e('Agency overview content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>
<?php elseif ($tab === 'analytics' && ($is_authenticated || $has_license)) : ?>
    <?php
    // Ensure usage_stats is available for analytics tab
    if (!isset($usage_stats)) {
        $usage_stats = Usage_Tracker::get_stats_display();
    }
    $analytics_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/analytics-tab.php';
    if (file_exists($analytics_partial)) {
        include $analytics_partial;
    } else {
        esc_html_e('Analytics content unavailable.', 'beepbeep-ai-alt-text-generator');
    }
    ?>

<?php elseif ($tab === 'settings') : ?>
            <?php
            $settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
            if (file_exists($settings_partial)) {
                include $settings_partial;
            } else {
                esc_html_e('Settings content unavailable.', 'beepbeep-ai-alt-text-generator');
            }
            ?>
            
<?php elseif ($tab === 'debug' && ($is_authenticated || $has_license)) : ?>
            <?php
            $debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
            if (file_exists($debug_partial)) {
                include $debug_partial;
            } else {
                esc_html_e('Debug logs content unavailable.', 'beepbeep-ai-alt-text-generator');
            }
            ?>

            <?php elseif ($tab === 'admin' && $is_pro_for_admin) : ?>
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
                                if ($is_agency_for_admin) {
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
                                <?php esc_html_e('Debug Logs and Settings', 'beepbeep-ai-alt-text-generator'); ?>
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
                        <button type="button" class="bbai-admin-tab active" data-admin-tab="debug">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <path d="M8 1L15 8L8 15L1 8L8 1Z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <circle cx="8" cy="8" r="2" fill="currentColor"/>
                            </svg>
                            <?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                        <button type="button" class="bbai-admin-tab" data-admin-tab="settings">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="margin-right: 8px;">
                                <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M8 4V8L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?>
                        </button>
                    </div>

                    <!-- Debug Logs Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="debug">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Debug Logs', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        $debug_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/debug-tab.php';
                        if (file_exists($debug_partial)) {
                            include $debug_partial;
                        } else {
                            esc_html_e('Debug content unavailable.', 'beepbeep-ai-alt-text-generator');
                        }
                        ?>
                    </div>

                    <!-- Settings Section -->
                    <div class="bbai-admin-section bbai-admin-tab-content" data-admin-tab-content="settings" style="display: none;">
                        <div class="bbai-admin-section-header">
                            <h3 class="bbai-admin-section-title"><?php esc_html_e('Settings', 'beepbeep-ai-alt-text-generator'); ?></h3>
                        </div>
                        <?php
                        $settings_partial = BEEPBEEP_AI_PLUGIN_DIR . 'admin/partials/settings-tab.php';
                        if (file_exists($settings_partial)) {
                            include $settings_partial;
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
                        esc_html($tab),
                        esc_html(implode(', ', array_keys($tabs ?? [])))
                    );
                    ?></p>
                    <p><strong><?php esc_html_e('Debug info:', 'beepbeep-ai-alt-text-generator'); ?></strong></p>
                    <ul class="bbai-unauth-list">
                        <li><?php
                        /* translators: 1: tab identifier */
                        printf(esc_html__('Tab: %s', 'beepbeep-ai-alt-text-generator'), esc_html($tab));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Is Authenticated: %s', 'beepbeep-ai-alt-text-generator'), esc_html($is_authenticated ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($has_license ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored Token: %s', 'beepbeep-ai-alt-text-generator'), esc_html($has_stored_token ? 'Yes' : 'No'));
                        ?></li>
                        <li><?php
                        /* translators: 1: yes/no value */
                        printf(esc_html__('Has Stored License: %s', 'beepbeep-ai-alt-text-generator'), esc_html($has_stored_license ? 'Yes' : 'No'));
                        ?></li>
                    </ul>
                </div>
            </div><!-- .bbai-container -->

            <!-- Footer -->
            <div class="bbai-footer">
                <?php esc_html_e('BeepBeep AI • WordPress AI Tools', 'beepbeep-ai-alt-text-generator'); ?> — <a href="<?php echo esc_url('https://wordpress.org/plugins/beepbeep-ai-alt-text-generator/'); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('WordPress.org Plugin', 'beepbeep-ai-alt-text-generator'); ?></a>
            </div>
        </div>
        
        <?php endif; // End tab check (dashboard/library/guide/debug/settings/credit-usage)
        
        // Include upgrade modal OUTSIDE of tab conditionals so it's always available
        // Set up currency for upgrade modal - Always use GBP (£) with Stripe prices
        // GBP prices: Growth £12.99, Agency £49.99, Credits £9.99 (matching Stripe payment links)
        $currency = ['symbol' => '£', 'code' => 'GBP', 'free' => 0, 'growth' => 12.99, 'pro' => 12.99, 'agency' => 49.99, 'credits' => 9.99];
        
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
        wp_cache_delete('bbai_stats', 'bbai');
        delete_transient('bbai_stats_v3');
        $this->stats_cache = null;
    }

    public function get_media_stats(){
        try {
            // Check in-memory cache first
            if (is_array($this->stats_cache)){
                return $this->stats_cache;
            }

            // Check object cache (Redis/Memcached if available)
            $cache_key = 'bbai_stats';
            $cache_group = 'bbai';
            $cached = wp_cache_get($cache_key, $cache_group);
            if (false !== $cached && is_array($cached)){
                $this->stats_cache = $cached;
                return $cached;
            }

            // Check transient cache (15 minute TTL for DB queries - optimized for performance)
            $transient_key = 'bbai_stats_v3';
            $cached = get_transient($transient_key);
            if (false !== $cached && is_array($cached)){
                // Also populate object cache for next request
                wp_cache_set($cache_key, $cached, $cache_group, 15 * MINUTE_IN_SECONDS);
                $this->stats_cache = $cached;
                return $cached;
	            }
	
	            global $wpdb;
		
		            $image_mime_like = $wpdb->esc_like('image/') . '%';
		
	            $total = (int) $wpdb->get_var($wpdb->prepare(
		                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
		                'SELECT COUNT(*) FROM ' . $wpdb->posts . ' WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s',
		                'attachment', 'inherit', $image_mime_like
	            ));

	            $with_alt = (int) $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT COUNT(DISTINCT p.ID) FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND m.meta_key = %s AND TRIM(m.meta_value) <> %s',
	                'attachment',
	                'inherit',
	                $image_mime_like,
	                '_wp_attachment_image_alt',
	                ''
	            ));
	
	            $generated = (int) $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT COUNT(DISTINCT post_id) FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s',
	                '_bbai_generated_at'
	            ));

            $coverage = $total ? round(($with_alt / $total) * 100, 1) : 0;
            $missing  = max(0, $total - $with_alt);

            // Cache date/time format to avoid duplicate get_option() calls
            $date_format_raw = get_option('date_format');
            $time_format_raw = get_option('time_format');
            $date_format = is_string($date_format_raw) ? $date_format_raw : '';
            $time_format = is_string($time_format_raw) ? $time_format_raw : '';
            $datetime_format = (!empty($date_format) && !empty($time_format)) ? $date_format . ' ' . $time_format : 'Y-m-d H:i:s';

            $opts = get_option(self::OPTION_KEY, []);
            $usage = $opts['usage'] ?? $this->default_usage();
	            if (!empty($usage['last_request'])){
	                $usage['last_request_formatted'] = mysql2date($datetime_format, $usage['last_request']);
	            }
	
	            $latest_generated_raw = $wpdb->get_var($wpdb->prepare(
	                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s ORDER BY meta_value DESC LIMIT 1',
	                '_bbai_generated_at'
	            ));
	            $latest_generated = $latest_generated_raw ? mysql2date($datetime_format, $latest_generated_raw) : '';
	
	            $top_source_row = $wpdb->get_row(
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT meta_value AS source, COUNT(*) AS count FROM ' . $wpdb->postmeta . ' WHERE meta_key = %s AND meta_value <> %s GROUP BY meta_value ORDER BY COUNT(*) DESC LIMIT 1',
	                    '_beepbeepai_source',
	                    ''
	                ),
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
        $tokens = intval(get_post_meta($attachment_id, '_bbai_tokens_total', true));
        $prompt = intval(get_post_meta($attachment_id, '_bbai_tokens_prompt', true));
        $completion = intval(get_post_meta($attachment_id, '_bbai_tokens_completion', true));
        $generated_raw = get_post_meta($attachment_id, '_bbai_generated_at', true);
        $date_format_raw = get_option('date_format');
        $time_format_raw = get_option('time_format');
        $date_format = is_string($date_format_raw) && !empty($date_format_raw) ? $date_format_raw : 'Y-m-d';
        $time_format = is_string($time_format_raw) && !empty($time_format_raw) ? $time_format_raw : 'H:i:s';
        $generated = $generated_raw ? mysql2date($date_format . ' ' . $time_format, $generated_raw) : '';
        $source_key = sanitize_key(get_post_meta($attachment_id, '_bbai_source', true) ?: 'unknown');
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
            '_bbai_review_score',
            '_bbai_review_status',
            '_bbai_review_grade',
            '_bbai_review_summary',
            '_bbai_review_issues',
            '_bbai_review_model',
            '_bbai_reviewed_at',
            '_bbai_review_alt_hash',
        ];
        foreach ($keys as $key){
            delete_post_meta($attachment_id, $key);
        }
    }

    private function get_review_snapshot(int $attachment_id, string $current_alt = ''): ?array{
        $score = intval(get_post_meta($attachment_id, '_bbai_review_score', true));
        if ($score <= 0){
            return null;
        }

        $stored_hash = get_post_meta($attachment_id, '_bbai_review_alt_hash', true);
        if ($current_alt !== ''){
            $current_hash = $this->hash_alt_text($current_alt);
            if ($stored_hash && !hash_equals($stored_hash, $current_hash)){
                $this->purge_review_meta($attachment_id);
                return null;
            }
        }

        $status     = sanitize_key(get_post_meta($attachment_id, '_bbai_review_status', true));
        $grade_raw  = get_post_meta($attachment_id, '_bbai_review_grade', true);
        $summary    = get_post_meta($attachment_id, '_bbai_review_summary', true);
        $model      = get_post_meta($attachment_id, '_bbai_review_model', true);
        $reviewed_at = get_post_meta($attachment_id, '_bbai_reviewed_at', true);

        $issues_raw = get_post_meta($attachment_id, '_bbai_review_issues', true);
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
                'grade' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                'status' => 'critical',
                'issues' => [__('ALT text is missing.', 'beepbeep-ai-alt-text-generator')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Missing', 'beepbeep-ai-alt-text-generator'),
                    'status' => 'critical',
                    'issues' => [__('ALT text is missing.', 'beepbeep-ai-alt-text-generator')],
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
                'grade' => __('Critical', 'beepbeep-ai-alt-text-generator'),
                'status' => 'critical',
                'issues' => [__('ALT text looks like placeholder content and must be rewritten.', 'beepbeep-ai-alt-text-generator')],
                'heuristic' => [
                    'score' => 0,
                    'grade' => __('Critical', 'beepbeep-ai-alt-text-generator'),
                    'status'=> 'critical',
                    'issues'=> [__('ALT text looks like placeholder content and must be rewritten.', 'beepbeep-ai-alt-text-generator')],
                ],
                'review' => null,
            ];
        }

        $length = function_exists('mb_strlen') ? mb_strlen($alt) : strlen($alt);
        if ($length < 45){
            $score -= 35;
            $issues[] = __('Too short – add a richer description (45+ characters).', 'beepbeep-ai-alt-text-generator');
        } elseif ($length > 160){
            $score -= 15;
            $issues[] = __('Very long – trim to keep the description concise (under 160 characters).', 'beepbeep-ai-alt-text-generator');
        }

        if (preg_match('/\b(image|picture|photo|screenshot)\b/i', $alt)){
            $score -= 10;
            $issues[] = __('Contains generic filler words like “image” or “photo”.', 'beepbeep-ai-alt-text-generator');
        }

        if (preg_match('/\b(test|testing|sample|example|dummy|placeholder|lorem|alt text)\b/i', $alt)){
            $score = min($score - 80, 5);
            $issues[] = __('Contains placeholder wording such as “test” or “sample”. Replace with a real description.', 'beepbeep-ai-alt-text-generator');
        }

        $word_count = str_word_count($alt, 0, '0123456789');
        if ($word_count < 4){
            $score -= 70;
            $score = min($score, 5);
            $issues[] = __('ALT text is extremely brief – add meaningful descriptive words.', 'beepbeep-ai-alt-text-generator');
        } elseif ($word_count < 6){
            $score -= 50;
            $score = min($score, 20);
            $issues[] = __('ALT text is too short to convey the subject in detail.', 'beepbeep-ai-alt-text-generator');
        } elseif ($word_count < 8){
            $score -= 35;
            $score = min($score, 40);
            $issues[] = __('ALT text could use a few more descriptive words.', 'beepbeep-ai-alt-text-generator');
        }

        if ($score > 40 && $length < 30){
            $score = min($score, 40);
            $issues[] = __('Expand the description with one or two concrete details.', 'beepbeep-ai-alt-text-generator');
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
                $issues[] = __('Matches the attachment title – add more unique detail.', 'beepbeep-ai-alt-text-generator');
            }
        }

        $file = get_attached_file($attachment_id);
        if ($file && $normalized_alt !== ''){
            $base = pathinfo($file, PATHINFO_FILENAME);
            $normalized_base = $normalize($base);
            if ($normalized_base !== '' && $normalized_alt === $normalized_base){
                $score -= 20;
                $issues[] = __('Matches the file name – rewrite it to describe the image.', 'beepbeep-ai-alt-text-generator');
            }
        }

        if (!preg_match('/[a-z]{4,}/i', $alt)){
            $score -= 15;
            $issues[] = __('Lacks descriptive language – include meaningful nouns or adjectives.', 'beepbeep-ai-alt-text-generator');
        }

        if (!preg_match('/\b[a-z]/i', $alt)){
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $status = $this->status_from_score($score);
        $grade  = $this->grade_from_status($status);

        if ($status === 'review' && empty($issues)){
            $issues[] = __('Give this ALT another look to ensure it reflects the image details.', 'beepbeep-ai-alt-text-generator');
        } elseif ($status === 'critical' && empty($issues)){
            $issues[] = __('ALT text should be rewritten for accessibility.', 'beepbeep-ai-alt-text-generator');
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
                return __('Excellent', 'beepbeep-ai-alt-text-generator');
            case 'good':
                return __('Strong', 'beepbeep-ai-alt-text-generator');
            case 'review':
                return __('Needs review', 'beepbeep-ai-alt-text-generator');
            default:
                return __('Critical', 'beepbeep-ai-alt-text-generator');
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
	        if ($limit <= 0){ $limit = 5; }
	        $image_mime_like = $wpdb->esc_like('image/') . '%';
	        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
	            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' m ON (p.ID = m.post_id AND m.meta_key = %s) WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s AND (m.meta_value IS NULL OR TRIM(m.meta_value) = %s) ORDER BY p.ID DESC LIMIT %d',
	            '_wp_attachment_image_alt', 'attachment', 'inherit', $image_mime_like, '', $limit
	        )));
	    }

	    public function get_all_attachment_ids($limit = 5, $offset = 0){
	        global $wpdb;
	        $limit  = max(1, intval($limit));
	        $offset = max(0, intval($offset));
	        $image_mime_like = $wpdb->esc_like('image/') . '%';
	        $rows = $wpdb->get_col($wpdb->prepare(
	            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	            'SELECT p.ID FROM ' . $wpdb->posts . ' p LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s WHERE p.post_type = %s AND p.post_status = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, p.ID DESC LIMIT %d OFFSET %d',
	            '_bbai_generated_at', 'attachment', 'inherit', $image_mime_like, $limit, $offset
	        ));
	        return array_map('intval', (array) $rows);
	    }

	    private function get_usage_rows($limit = 10, $include_all = false){
	        global $wpdb;
	        $limit = max(1, intval($limit));

	        $image_mime_like = $wpdb->esc_like('image/') . '%';

	        $cache_key = 'bbai_usage_rows_' . md5($limit . '|' . ($include_all ? 'all' : 'slice'));
	        if (!$include_all) {
	            $cached = wp_cache_get($cache_key, 'bbai');
	            if ($cached !== false) {
	                return $cached;
	            }
	        }
	        $prepare_params = [
	            '_bbai_tokens_total',
	            '_bbai_tokens_prompt',
	            '_bbai_tokens_completion',
	            '_wp_attachment_image_alt',
	            '_bbai_source',
	            '_bbai_model',
	            '_bbai_generated_at',
	            '_wp_attachment_metadata',
	            'attachment',
	            $image_mime_like
	        ];

	        if (!$include_all){
	            $query_params   = $prepare_params;
	            $query_params[] = $limit;
	            $rows = $wpdb->get_results(
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT p.ID, p.post_title, p.guid, tokens.meta_value AS tokens_total, prompt.meta_value AS tokens_prompt, completion.meta_value AS tokens_completion, alt.meta_value AS alt_text, src.meta_value AS source, model.meta_value AS model, gen.meta_value AS generated_at, thumb.meta_value AS thumbnail_metadata FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' completion ON completion.post_id = p.ID AND completion.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' alt ON alt.post_id = p.ID AND alt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON src.post_id = p.ID AND src.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' model ON model.post_id = p.ID AND model.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' thumb ON thumb.post_id = p.ID AND thumb.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, CAST(tokens.meta_value AS UNSIGNED) DESC LIMIT %d',
	                    $query_params
	                ),
	                ARRAY_A
	            );
	        } else {
	            $rows = $wpdb->get_results(
	                $wpdb->prepare(
	                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.UnescapedDBParameter -- Table identifiers come from trusted core $wpdb properties; value placeholders remain prepared.
	                    'SELECT p.ID, p.post_title, p.guid, tokens.meta_value AS tokens_total, prompt.meta_value AS tokens_prompt, completion.meta_value AS tokens_completion, alt.meta_value AS alt_text, src.meta_value AS source, model.meta_value AS model, gen.meta_value AS generated_at, thumb.meta_value AS thumbnail_metadata FROM ' . $wpdb->posts . ' p INNER JOIN ' . $wpdb->postmeta . ' tokens ON tokens.post_id = p.ID AND tokens.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' prompt ON prompt.post_id = p.ID AND prompt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' completion ON completion.post_id = p.ID AND completion.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' alt ON alt.post_id = p.ID AND alt.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' src ON src.post_id = p.ID AND src.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' model ON model.post_id = p.ID AND model.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' gen ON gen.post_id = p.ID AND gen.meta_key = %s LEFT JOIN ' . $wpdb->postmeta . ' thumb ON thumb.post_id = p.ID AND thumb.meta_key = %s WHERE p.post_type = %s AND p.post_mime_type LIKE %s ORDER BY CASE WHEN gen.meta_value IS NOT NULL THEN gen.meta_value ELSE p.post_date END DESC, CAST(tokens.meta_value AS UNSIGNED) DESC',
	                    $prepare_params
	                ),
	                ARRAY_A
	            );
	        }
	        if (empty($rows)){
	            if (!$include_all) {
	                wp_cache_set($cache_key, [], 'bbai', MINUTE_IN_SECONDS * 2);
	            }
	            return [];
	        }

        // Cache date format to avoid repeated get_option() calls
        $date_format_raw = get_option('date_format');
        $time_format_raw = get_option('time_format');
        $date_format = is_string($date_format_raw) && !empty($date_format_raw) ? $date_format_raw : 'Y-m-d';
        $time_format = is_string($time_format_raw) && !empty($time_format_raw) ? $time_format_raw : 'H:i:s';
        $date_time_format = $date_format . ' ' . $time_format;
        $upload_dir = wp_upload_dir();

        $formatted = array_map(function($row) use ($date_time_format, $upload_dir){
            $generated = $row['generated_at'] ?? '';
            if ($generated){
                $generated = mysql2date($date_time_format, $generated);
            }

            $source = sanitize_key($row['source'] ?? 'unknown');
            if (!$source){ $source = 'unknown'; }

            // Get thumbnail URL from metadata instead of separate query
            $thumb_url = '';
            if (!empty($row['thumbnail_metadata'])) {
                $metadata = maybe_unserialize($row['thumbnail_metadata']);
                if (isset($metadata['sizes']['thumbnail']['file'])) {
                    $dir = dirname($metadata['file']);
                    $thumb_url = $upload_dir['baseurl'] . '/' . ($dir !== '.' ? $dir . '/' : '') . $metadata['sizes']['thumbnail']['file'];
                } elseif (!empty($row['guid'])) {
                    $thumb_url = $row['guid'];
                }
            } elseif (!empty($row['guid'])) {
                $thumb_url = $row['guid'];
            }

            return [
                'id'         => intval($row['ID']),
                'title'      => $row['post_title'] ?? '',
                'alt'        => $row['alt_text'] ?? '',
                'tokens'     => intval($row['tokens_total'] ?? 0),
                'prompt'     => intval($row['tokens_prompt'] ?? 0),
                'completion' => intval($row['tokens_completion'] ?? 0),
                'source'     => $source,
                'source_label' => $this->format_source_label($source),
                'source_description' => $this->format_source_description($source),
                'model'      => $row['model'] ?? '',
                'generated'  => $generated,
                'thumb'      => $thumb_url,
                'details_url'=> add_query_arg('item', $row['ID'], admin_url('upload.php')) . '#attachment_alt',
                'view_url'   => get_permalink($row['ID']) ?: $row['guid'],
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
                'label' => __('Auto (upload)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated automatically when the image was uploaded.', 'beepbeep-ai-alt-text-generator'),
            ],
            'ajax'     => [
                'label' => __('Media Library (single)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Triggered from the Media Library row action or attachment details screen.', 'beepbeep-ai-alt-text-generator'),
            ],
            'bulk'     => [
                'label' => __('Media Library (bulk)', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via the Media Library bulk action.', 'beepbeep-ai-alt-text-generator'),
            ],
            'dashboard' => [
                'label' => __('Dashboard quick actions', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated from the dashboard buttons.', 'beepbeep-ai-alt-text-generator'),
            ],
            'wpcli'    => [
                'label' => __('WP-CLI', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated via the wp ai-alt CLI command.', 'beepbeep-ai-alt-text-generator'),
            ],
            'manual'   => [
                'label' => __('Manual / custom', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Generated by custom code or integration.', 'beepbeep-ai-alt-text-generator'),
            ],
            'unknown'  => [
                'label' => __('Unknown', 'beepbeep-ai-alt-text-generator'),
                'description' => __('Source not recorded for this ALT text.', 'beepbeep-ai-alt-text-generator'),
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

    /**
     * Ensure a direct WP_Filesystem instance is available for plugin file reads.
     *
     * @return \WP_Filesystem_Base|null
     */
    private function bbai_init_wp_filesystem() {
        global $wp_filesystem;

        if (is_object($wp_filesystem) && isset($wp_filesystem->method) && $wp_filesystem->method === 'direct') {
            return $wp_filesystem;
        }

        if (!class_exists('\WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $direct = new \WP_Filesystem_Direct(null);

        // Only populate the global if nothing else has been set.
        if (!is_object($wp_filesystem)) {
            $wp_filesystem = $direct;
        }

        return $direct;
    }

    /**
     * Sanitize and neutralize CSV cell values to prevent spreadsheet formula injection.
     *
     * @param mixed $value
     * @param bool  $allow_html Whether to allow safe post HTML via wp_kses_post().
     * @return string
     */
    private function bbai_csv_safe_cell($value, bool $allow_html = false): string {
        if ($value === null) {
            $value = '';
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $value = (string) $value;
        } else {
            $encoded = wp_json_encode($value);
            $value = $encoded === false ? '' : (string) $encoded;
        }

        // CSV isn't HTML, but output is user-controlled; sanitize to avoid exporting unsafe content.
        $value = $allow_html ? wp_kses_post($value) : sanitize_text_field($value);

        // Prefix every non-empty value to prevent CSV injection in spreadsheet software.
        if ($value !== '' && strpos($value, "\t") !== 0) {
            $value = "\t" . $value;
        }

        return $value;
    }

    /**
     * Build a single CSV row (RFC4180-style) without using file handles.
     *
     * @param array $fields
     * @return string
     */
    private function bbai_csv_row(array $fields): string {
        $delimiter = ',';
        $enclosure = '"';

        $out = [];
        foreach ($fields as $field) {
            if ($field === null) {
                $field = '';
            } elseif (is_bool($field)) {
                $field = $field ? '1' : '0';
            } elseif (is_scalar($field)) {
                $field = (string) $field;
            } else {
                $encoded = wp_json_encode($field);
                $field = $encoded === false ? '' : (string) $encoded;
            }

            $needs_enclosure = strpbrk($field, $delimiter . $enclosure . "\r\n\t\v\0") !== false;
            if ($needs_enclosure) {
                $field = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
            }
            $out[] = $field;
        }

        return implode($delimiter, $out) . "\n";
    }

    /**
     * Stream raw CSV/text bytes to the HTTP response.
     *
     * @param string $contents
     * @return void
     */
    private function bbai_output_contents(string $contents): void {
        // Streaming CSV/text downloads; escaping would corrupt the file content.
        echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Clean active output buffers before streaming file downloads.
     *
     * @return void
     */
    private function bbai_prepare_download_stream(): void {
        while (ob_get_level() > 0) {
            $status = ob_get_status();
            $flags = isset($status['flags']) ? (int) $status['flags'] : 0;
            if (($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                break;
            }
            ob_end_clean();
        }
    }

    public function handle_usage_export(){
        if (!$this->user_can_manage()){
            wp_die(esc_html__('You do not have permission to export usage data.', 'beepbeep-ai-alt-text-generator'));
        }
        check_admin_referer('bbai_usage_export');

        $rows = $this->get_usage_rows(10, true);
        $filename = 'bbai-usage-' . gmdate('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $this->bbai_prepare_download_stream();
        $this->bbai_output_contents($this->bbai_csv_row(['Attachment ID', 'Title', 'ALT Text', 'Source', 'Model', 'Generated At']));
        foreach ($rows as $row){
            $this->bbai_output_contents($this->bbai_csv_row([
                $this->bbai_csv_safe_cell($row['id'] ?? ''),
                $this->bbai_csv_safe_cell($row['title'] ?? ''),
                $this->bbai_csv_safe_cell($row['alt'] ?? ''),
                $this->bbai_csv_safe_cell($row['source'] ?? ''),
                $this->bbai_csv_safe_cell($row['tokens'] ?? ''),
                $this->bbai_csv_safe_cell($row['prompt'] ?? ''),
                $this->bbai_csv_safe_cell($row['completion'] ?? ''),
                $this->bbai_csv_safe_cell($row['model'] ?? ''),
                '',
            ]));
        }
        exit;
    }

    public function handle_debug_log_export() {
        if (!$this->user_can_manage()){
            wp_die(esc_html__('You do not have permission to export debug logs.', 'beepbeep-ai-alt-text-generator'));
        }
        check_admin_referer('bbai_debug_export');

        if (!class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            wp_die(esc_html__('Debug logging is not available.', 'beepbeep-ai-alt-text-generator'));
        }

				        global $wpdb;
				        $table = esc_sql( Debug_Log::table() );
				        $rows = $wpdb->get_results(
				            $wpdb->prepare(
				                "SELECT * FROM `{$table}` WHERE %d = %d ORDER BY created_at DESC",
				                1,
				                1
				            ),
			            ARRAY_A
			        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bbai-debug-logs-' . gmdate('Ymd-His') . '.csv');

        $this->bbai_prepare_download_stream();
        $this->bbai_output_contents($this->bbai_csv_row(['Timestamp', 'Level', 'Message', 'Source', 'Context']));
        foreach ($rows as $row){
            $context = $row['context'] ?? '';
            $this->bbai_output_contents($this->bbai_csv_row([
                $this->bbai_csv_safe_cell($row['created_at'] ?? ''),
                $this->bbai_csv_safe_cell($row['level'] ?? ''),
                $this->bbai_csv_safe_cell($row['message'] ?? ''),
                $this->bbai_csv_safe_cell($row['source'] ?? ''),
                $this->bbai_csv_safe_cell($context),
            ]));
        }
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

        if (stripos((string)$content, '```') !== false){
            $content = preg_replace('/```json/i', '', (string)$content);
            $content = str_replace('```', '', (string)$content);
            $content = trim($content);
        }

        if ($content !== '' && is_string($content) && isset($content[0]) && $content[0] !== '{'){
            $start = strpos((string)$content, '{');
            $end   = strrpos((string)$content, '}');
            if ($start !== false && $end !== false && $end > $start){
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
        }
        $decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $content ) : null;
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        return [];
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
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0){
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $limit = apply_filters('bbai_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit){
            return new \WP_Error('inline_image_too_large', __('Image exceeds the inline embedding size limit.', 'beepbeep-ai-alt-text-generator'), ['size' => $size, 'limit' => $limit]);
        }

        $fs = $this->bbai_init_wp_filesystem();
        $contents = (is_object($fs) && method_exists($fs, 'get_contents')) ? $fs->get_contents($file) : false;
        if ($contents === false){
            return new \WP_Error('inline_image_read_failed', __('Unable to read the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $mime = get_post_mime_type($attachment_id);
        if (empty($mime)){
            $mime = function_exists('mime_content_type') ? mime_content_type($file) : 'image/jpeg';
        }

        $base64 = base64_encode($contents);
        if (!$base64){
            return new \WP_Error('inline_image_encode_failed', __('Failed to encode the image for inline embedding.', 'beepbeep-ai-alt-text-generator'));
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
            return new \WP_Error('review_skipped', __('ALT text is empty; skipped review.', 'beepbeep-ai-alt-text-generator'));
        }

        $review_model = $opts['review_model'] ?? ($opts['model'] ?? 'gpt-4o-mini');
        $review_model = apply_filters('bbai_review_model', $review_model, $attachment_id, $opts);
        if (!$review_model){
            return new \WP_Error('review_model_missing', __('No review model configured.', 'beepbeep-ai-alt-text-generator'));
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
            /* translators: 1: media title */
            $context_lines[] = sprintf(__('Media title: %s', 'beepbeep-ai-alt-text-generator'), $title);
        }
        if ($filename){
            /* translators: 1: filename */
            $context_lines[] = sprintf(__('Filename: %s', 'beepbeep-ai-alt-text-generator'), $filename);
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
        if ( ! function_exists( 'bbai_json_decode_array' ) && defined( 'BEEPBEEP_AI_PLUGIN_DIR' ) ) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-json.php';
        }
        $decoded = function_exists( 'bbai_json_decode_array' ) ? bbai_json_decode_array( $raw_body ) : null;
        $data = is_array( $decoded ) ? $decoded : [];

        if ($code >= 300 || empty($data['choices'][0]['message']['content'])){
            $api_message = isset($data['error']['message']) ? $data['error']['message'] : ($raw_body ?: 'OpenAI review failed.');
            $api_message = $this->redact_api_token($api_message);
            return new \WP_Error('review_api_error', $api_message, ['status' => $code, 'body' => $data]);
        }

        $content = $data['choices'][0]['message']['content'];
        $parsed = $this->extract_json_object($content);
        if (!$parsed){
            return new \WP_Error('review_parse_failed', __('Unable to parse review response.', 'beepbeep-ai-alt-text-generator'), ['response' => $content]);
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
        update_post_meta($attachment_id, '_bbai_source', $source);
        update_post_meta($attachment_id, '_bbai_model', $model);
        update_post_meta($attachment_id, '_bbai_generated_at', current_time('mysql'));
        update_post_meta($attachment_id, '_bbai_tokens_prompt', $usage_summary['prompt']);
        update_post_meta($attachment_id, '_bbai_tokens_completion', $usage_summary['completion']);
        update_post_meta($attachment_id, '_bbai_tokens_total', $usage_summary['total']);

        if ($image_strategy === 'remote'){
            delete_post_meta($attachment_id, '_bbai_image_reference');
        } else {
            update_post_meta($attachment_id, '_bbai_image_reference', $image_strategy);
        }

        if (!is_wp_error($review_result)){
            update_post_meta($attachment_id, '_bbai_review_score', $review_result['score']);
            update_post_meta($attachment_id, '_bbai_review_status', $review_result['status']);
            update_post_meta($attachment_id, '_bbai_review_grade', $review_result['grade']);
            update_post_meta($attachment_id, '_bbai_review_summary', $review_result['summary']);
            update_post_meta($attachment_id, '_bbai_review_issues', wp_json_encode($review_result['issues']));
            update_post_meta($attachment_id, '_bbai_review_model', $review_result['model']);
            update_post_meta($attachment_id, '_bbai_reviewed_at', current_time('mysql'));
            update_post_meta($attachment_id, '_bbai_review_alt_hash', $this->hash_alt_text($alt));
            delete_post_meta($attachment_id, '_bbai_review_error');
            if (!empty($review_result['usage'])){
                $this->record_usage($review_result['usage']);
            }
        } else {
            update_post_meta($attachment_id, '_bbai_review_error', $review_result->get_error_message());
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

        // Allocate free credits on first generation request (one-time per site)
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
        \BeepBeepAI\AltTextGenerator\Usage_Tracker::allocate_free_credits_if_needed();

        // Capture current user ID for credit tracking
        // Use 0 for anonymous/system users (auto-upload, queue processing, etc.)
        $user_id = get_current_user_id();
        
        // For AJAX/REST calls, try to get user from nonce/authentication
        if ($user_id <= 0 && ($source === 'ajax' || $source === 'inline' || $source === 'manual')) {
            // Check if we're in a REST API context
            if (defined('REST_REQUEST') && REST_REQUEST) {
                // REST API should have authenticated user via cookie/nonce
                $user_id = get_current_user_id();
            }
            // For AJAX calls, get_current_user_id() should work if user is logged in
            // If still 0, it means user is not authenticated
        }
        
        // Set to 0 for system/automated operations only
        if ($user_id <= 0 || $source === 'auto' || $source === 'queue' || $source === 'wpcli') {
            $user_id = 0; // Track as "System" for anonymous/automated operations
        }

        // Skip authentication check in local development mode
        $has_license = $this->api_client->has_active_license();
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            // Check site-wide quota before generation
            // Wrap in try-catch to prevent PHP errors from breaking REST responses
            // Use has_reached_limit() instead of Token_Quota_Service for consistency
            // has_reached_limit() already includes proper cache checking and fallback logic
            // This prevents false "quota exhausted" errors from stale cache data
            try {
                if ($this->api_client->has_reached_limit()) {
                    // Get usage data for the error response
                    $usage = $this->api_client->get_usage();
                    if (is_wp_error($usage)) {
                        // Fall back to cached usage for display
                        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                        $usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
                    }
                    
                    $reset_date = isset($usage['resetDate']) ? $usage['resetDate'] : null;
                    $reset_message = __('Monthly quota exhausted. Upgrade to Growth for 1,000 generations per month, or wait for your quota to reset. You can manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator');
                    
                    if ($reset_date) {
                        try {
                            $reset_ts = strtotime($reset_date);
                            if ($reset_ts !== false) {
                                $formatted_date = date_i18n('F j, Y', $reset_ts);
                                $reset_message = sprintf(
                                    /* translators: 1: reset date */
                                    __('Monthly quota exhausted. Your quota will reset on %s. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator'),
                                    $formatted_date
                                );
                            }
                        } catch (\Exception $e) {
                            // Keep default message if date parsing fails
                        }
                    }
                    
                    return new \WP_Error(
                        'limit_reached',
                        $reset_message,
                        ['code' => 'quota_exhausted', 'usage' => is_array($usage) ? $usage : null]
                    );
                }
            } catch ( \Exception $e ) {
                // If quota check fails due to error, don't block generation
                // Backend will handle usage limits
                // Silent failure - generation will proceed
            }
        }

        if (!$this->is_image($attachment_id)) {
            return new \WP_Error('not_image', __('Attachment is not an image.', 'beepbeep-ai-alt-text-generator'));
        }

        // Prefer higher-quality default for better accuracy
        $model = apply_filters('bbai_model', $opts['model'] ?? 'gpt-4o', $attachment_id, $opts);
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $prompt = $this->build_prompt($attachment_id, $opts, $existing_alt, $retry_count > 0, $feedback);

        if (!empty($opts['dry_run'])){
            update_post_meta($attachment_id, '_bbai_last_prompt', $prompt);
            update_post_meta($attachment_id, '_bbai_source', 'dry-run');
            update_post_meta($attachment_id, '_bbai_model', $model);
            update_post_meta($attachment_id, '_bbai_generated_at', current_time('mysql'));
            $this->stats_cache = null;
            return new \WP_Error('bbai_dry_run', __('Dry run enabled. Prompt stored for review; ALT text not updated.', 'beepbeep-ai-alt-text-generator'), ['prompt' => $prompt]);
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
            $error_code = $api_response->get_error_code();
            $error_message = $api_response->get_error_message();
            $error_data = $api_response->get_error_data();
            
            // Check if this is a quota/limit error - verify against cached usage
            // The backend API might incorrectly report quota exhausted when credits are available
            $error_message_lower = strtolower($error_message);
            $is_quota_error = ($error_code === 'limit_reached' || $error_code === 'quota_exhausted' || $error_code === 'quota_check_mismatch' ||
                             strpos($error_message_lower, 'quota exhausted') !== false ||
                             strpos($error_message_lower, 'monthly limit') !== false ||
                             strpos($error_message_lower, 'monthly quota') !== false ||
                             (is_array($error_data) && isset($error_data['status_code']) && intval($error_data['status_code']) === 429));
            
            // If it's a quota_check_mismatch error (from API client cache check), allow retry
            if ($error_code === 'quota_check_mismatch') {
                // This is from our cache validation - suggest retry but still return the error
                // The frontend should handle retry based on the error code
            } elseif ($is_quota_error) {
                // Check cached usage before accepting the backend's quota error
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
                
                if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] > 0) {
                    // Cached usage shows credits available - backend error might be incorrect
                    // Clear cache and do a fresh check to see actual status
                    \BeepBeepAI\AltTextGenerator\Usage_Tracker::clear_cache();
                    $fresh_usage = $this->api_client->get_usage();
                    
                    if (!is_wp_error($fresh_usage) && is_array($fresh_usage) && isset($fresh_usage['remaining']) && is_numeric($fresh_usage['remaining']) && $fresh_usage['remaining'] > 0) {
                        // Fresh API check shows credits available - backend quota error was wrong
                        // Update cache and return a retry error instead of blocking
                        \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($fresh_usage);
                        
                        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                            Debug_Log::log('warning', 'Backend reported quota exhausted but cache and fresh API check show credits available', [
                                'attachment_id' => $attachment_id,
                                'cached_remaining' => $cached_usage['remaining'],
                                'api_remaining' => $fresh_usage['remaining'],
                                'backend_error' => $error_message,
                                'error_code' => $error_code,
                            ], 'generation');
                        }
                        
                        // Return a retry error instead of blocking
                        return new \WP_Error(
                            'quota_check_mismatch',
                            __('Backend reported quota limit, but credits appear available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator'),
                            ['code' => 'quota_check_mismatch', 'retry_after' => 3, 'usage' => $fresh_usage]
                        );
                    }
                }
            }
            
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                // Get error data for detailed logging
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
                
                Debug_Log::log(
                    'error',
                    'Alt text generation failed',
                    $log_context,
                    'generation'
                );
            }
            return $api_response;
        }

        // The api_response is $response['data'] from generate_alt_text()
        // If generate_alt_text() returns WP_Error, it's already handled above
        // So at this point, api_response should be the data object
        // Validate that altText (camelCase) or alt_text (snake_case) exists in the response
        // Backend returns altText (camelCase), but support both for compatibility (and nested payloads)
        $alt_source = null;
        $alt_text = $this->extract_alt_text_from_response($api_response, $alt_source);
        if (empty($alt_text)) {
            $error_message = __('Backend API returned response but no alt text was generated.', 'beepbeep-ai-alt-text-generator');
            
            // Log this error with full response structure for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('error', 'Alt text generation failed - missing altText/alt_text in response', [
                    'attachment_id' => $attachment_id,
                    'response_keys' => is_array($api_response) ? array_keys($api_response) : 'not array',
                    'response_type' => gettype($api_response),
                    'has_altText' => isset($api_response['altText']),
                    'has_alt_text' => isset($api_response['alt_text']),
                    'alt_text_source' => $alt_source ?? 'none',
                    'has_usage' => isset($api_response['usage']),
                    'has_tokens' => isset($api_response['tokens']),
                    'response_preview' => is_array($api_response) ? wp_json_encode(array_slice($api_response, 0, 5)) : 'not array',
                ], 'generation');
            }
            
            // DO NOT update usage or log credits if alt_text is missing
            // The backend may have consumed credits, but we shouldn't record it as successful usage
            return new \WP_Error('missing_alt_text', $error_message, ['code' => 'api_response_invalid']);
        }
        
        // Normalize to alt_text for consistent usage throughout the codebase
        $api_response['alt_text'] = $alt_text;

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
            Usage_Tracker::clear_cache();
        }

        // CRITICAL: Only update usage AFTER we've confirmed alt_text exists
        // This ensures we NEVER log credits for failed generations, even if backend consumed them
        // The backend may have consumed credits when calling OpenAI, but if alt_text is missing,
        // we should NOT record it as successful usage locally
        
        // Backend returns credits_used and credits_remaining at root level, not nested in 'usage'
        // The 'usage' key contains token information (prompt_tokens, completion_tokens, etc.)
        // Build usage data from both root-level credits and nested usage token info
        $usage_data = [];
        
        // Get credits from root level (primary source)
        if (isset($api_response['credits_used'])) {
            $usage_data['used'] = intval($api_response['credits_used']);
        }
        if (isset($api_response['credits_remaining'])) {
            $usage_data['remaining'] = intval($api_response['credits_remaining']);
        }
        // Get limit from root level if provided (check both 'total_limit' and 'limit' for compatibility)
        if (isset($api_response['total_limit'])) {
            $usage_data['limit'] = intval($api_response['total_limit']);
        } elseif (isset($api_response['limit'])) {
            $usage_data['limit'] = intval($api_response['limit']);
        } elseif (isset($usage_data['used']) && isset($usage_data['remaining'])) {
            $usage_data['limit'] = $usage_data['used'] + $usage_data['remaining'];
        }
        
        // Get token info from nested usage object if available
        if (!empty($api_response['usage']) && is_array($api_response['usage'])) {
            if (isset($api_response['usage']['prompt_tokens'])) {
                $usage_data['prompt_tokens'] = intval($api_response['usage']['prompt_tokens']);
            }
            if (isset($api_response['usage']['completion_tokens'])) {
                $usage_data['completion_tokens'] = intval($api_response['usage']['completion_tokens']);
            }
            if (isset($api_response['usage']['total_tokens'])) {
                $usage_data['total_tokens'] = intval($api_response['usage']['total_tokens']);
            }
        }
        
        // Log token usage prominently for each generation
        if (!empty($usage_data) && (isset($usage_data['prompt_tokens']) || isset($usage_data['completion_tokens']) || isset($usage_data['total_tokens']))) {
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                $prompt_tokens = isset($usage_data['prompt_tokens']) ? intval($usage_data['prompt_tokens']) : 0;
                $completion_tokens = isset($usage_data['completion_tokens']) ? intval($usage_data['completion_tokens']) : 0;
                $total_tokens = isset($usage_data['total_tokens']) ? intval($usage_data['total_tokens']) : 0;
                
                $token_summary = sprintf(
                    'Token Usage: %s prompt + %s completion = %s total tokens',
                    $prompt_tokens > 0 ? number_format($prompt_tokens) : 'N/A',
                    $completion_tokens > 0 ? number_format($completion_tokens) : 'N/A',
                    $total_tokens > 0 ? number_format($total_tokens) : 'N/A'
                );
                
                Debug_Log::log('info', $token_summary, [
                    'attachment_id' => $attachment_id,
                    'prompt_tokens' => $usage_data['prompt_tokens'] ?? 0,
                    'completion_tokens' => $usage_data['completion_tokens'] ?? 0,
                    'total_tokens' => $usage_data['total_tokens'] ?? 0,
                    'alt_text_length' => strlen($alt_text ?? ''),
                    'model' => (isset($api_response['meta']) && is_array($api_response['meta'])) ? ($api_response['meta']['modelUsed'] ?? 'unknown') : 'unknown',
                    'generation_time_ms' => (isset($api_response['meta']) && is_array($api_response['meta'])) ? ($api_response['meta']['generation_time_ms'] ?? null) : null,
                ], 'generation');
            }
        }
        
        // Update usage if we have credits information from generation response
        if (!empty($usage_data) && (isset($usage_data['used']) || isset($usage_data['remaining']))) {
            // Log what we're updating for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Updating usage cache after generation', [
                    'usage_data' => $usage_data,
                    'has_used' => isset($usage_data['used']),
                    'has_remaining' => isset($usage_data['remaining']),
                    'has_limit' => isset($usage_data['limit']),
                    'api_response_keys' => array_keys($api_response),
                    'credits_used_in_response' => $api_response['credits_used'] ?? 'not set',
                    'credits_remaining_in_response' => $api_response['credits_remaining'] ?? 'not set',
                    'total_limit_in_response' => $api_response['total_limit'] ?? 'not set',
                    'limit_in_response' => $api_response['limit'] ?? 'not set',
                ], 'generation');
            }

            Usage_Tracker::update_usage($usage_data);
        } else {
            // Generation response didn't include credits info - fetch fresh usage from API
            // This ensures the dashboard shows accurate counts even if the backend doesn't
            // return credits in the generation response
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Generation response missing credits info, fetching fresh usage from API', [
                    'api_response_keys' => is_array($api_response) ? array_keys($api_response) : 'not array',
                ], 'generation');
            }

            // Clear the cached usage to force a fresh fetch
            Usage_Tracker::clear_cache();

            // Fetch fresh usage from the API
            $fresh_usage = $this->api_client->get_usage();
            if (!is_wp_error($fresh_usage) && is_array($fresh_usage)) {
                Usage_Tracker::update_usage($fresh_usage);
                $usage_data = $fresh_usage;

                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('info', 'Fresh usage fetched and cached after generation', [
                        'used' => $fresh_usage['used'] ?? 'not set',
                        'remaining' => $fresh_usage['remaining'] ?? 'not set',
                        'limit' => $fresh_usage['limit'] ?? 'not set',
                    ], 'generation');
                }
            }
        }

        // Also log what was actually cached (runs after either path updates usage)
        $cached_after = Usage_Tracker::get_cached_usage(false);
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Usage cache updated - verifying', [
                'cached_usage' => $cached_after,
                'cached_used' => $cached_after['used'] ?? 'not set',
                'cached_remaining' => $cached_after['remaining'] ?? 'not set',
                'cached_limit' => $cached_after['limit'] ?? 'not set',
            ], 'generation');
        }

        // Update license data if user has a license
        if ($has_license && !empty($usage_data)) {
            $existing_license = $this->api_client->get_license_data() ?? [];
            $updated_license  = $existing_license ?: [];
            $organization     = $updated_license['organization'] ?? [];

            // Update limit first
            if (isset($usage_data['limit'])) {
                $organization['tokenLimit'] = intval($usage_data['limit']);
            } elseif (isset($usage_data['used']) && isset($usage_data['remaining'])) {
                // Calculate limit from used + remaining if not provided
                $organization['tokenLimit'] = intval($usage_data['used']) + intval($usage_data['remaining']);
            }

            // Update remaining credits (this is the critical value for display)
            if (isset($usage_data['remaining'])) {
                $organization['tokensRemaining'] = max(0, intval($usage_data['remaining']));
            } elseif (isset($usage_data['used']) && isset($organization['tokenLimit'])) {
                // Calculate remaining from limit and used
                $organization['tokensRemaining'] = max(0, intval($organization['tokenLimit']) - intval($usage_data['used']));
            } elseif (isset($usage_data['limit']) && isset($usage_data['used'])) {
                // Calculate remaining from limit and used if remaining not provided
                $organization['tokensRemaining'] = max(0, intval($usage_data['limit']) - intval($usage_data['used']));
                if (!isset($organization['tokenLimit'])) {
                    $organization['tokenLimit'] = intval($usage_data['limit']);
                }
            }

            // Log the update for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Updating license organization data with usage', [
                    'tokensRemaining' => $organization['tokensRemaining'] ?? 'not set',
                    'tokenLimit' => $organization['tokenLimit'] ?? 'not set',
                    'usage_data' => $usage_data,
                ], 'generation');
            }

            // Get reset date from api_response root level if available
            if (isset($api_response['resetDate']) && !empty($api_response['resetDate'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['resetDate']);
            } elseif (isset($api_response['reset_date']) && !empty($api_response['reset_date'])) {
                $organization['resetDate'] = sanitize_text_field($api_response['reset_date']);
            } elseif (!empty($api_response['usage']['resetDate'])) {
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
                    __('Generated ALT text matched the existing value.', 'beepbeep-ai-alt-text-generator'),
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

        // Log credit usage for this generation
        // Backend tracks 1 credit per generation, not based on tokens
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-credit-usage-logger.php';
        // Always use 1 credit per generation (backend API charges 1 credit per alt text generation)
        $credits_used = 1;
        // Try to get token cost from usage summary if available (for reporting)
        $token_cost = null;
        if (isset($usage_summary['cost']) && is_numeric($usage_summary['cost'])) {
            $token_cost = floatval($usage_summary['cost']);
        }
        // Get model from usage summary or use default
        $model_used = isset($usage_summary['model']) ? sanitize_text_field($usage_summary['model']) : $model;
        \BeepBeepAI\AltTextGenerator\Credit_Usage_Logger::log_usage(
            $attachment_id,
            $user_id,
            $credits_used,
            $token_cost,
            $model_used,
            $source
        );

        // Log usage event for multi-user visualization
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-helpers.php';
        $action_type = $regenerate ? 'regenerate' : ($source === 'bulk' || $source === 'bulk-regenerate' ? 'bulk' : 'generate');
        $tokens_used = isset($usage_summary['total']) ? intval($usage_summary['total']) : (isset($usage_summary['total_tokens']) ? intval($usage_summary['total_tokens']) : 1);
        $context = [
            'image_id' => $attachment_id,
            'post_id'  => null,
        ];
        // Get post ID if attachment has a parent
        $attachment = get_post($attachment_id);
        if ($attachment && $attachment->post_parent > 0) {
            $context['post_id'] = $attachment->post_parent;
        }
        \BeepBeepAI\AltTextGenerator\Usage\record_usage_event($user_id, $tokens_used, $action_type, $context);

        $this->record_usage($usage_summary);
        
        // Note: We do NOT call Token_Quota_Service::record_local_usage() here because:
        // 1. We already update usage correctly via Usage_Tracker::update_usage() above (line 3303)
        // 2. Usage_Tracker gets the correct credits from the backend API response
        // 3. Calling record_local_usage() with token counts would double-count and treat tokens as credits
        // 4. The backend API response already contains the accurate credits_used value
        
        if ($has_license) {
            $this->refresh_license_usage_snapshot();
        }

        // Note: QA review is disabled for API proxy version (quality handled server-side)
        // Persist the generated alt text
        $this->persist_generation_result($attachment_id, $alt, $usage_summary, $source, $model, $image_strategy, $review_result);

        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Alt text updated', [
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

        return Queue::enqueue($attachment_id, $source ? sanitize_key($source) : 'auto');
    }

    public function register_bulk_action($bulk_actions){
        $bulk_actions['bbai_generate'] = __('Generate Alt Text (AI)', 'beepbeep-ai-alt-text-generator');
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids){
        if ($doaction !== 'bbai_generate') return $redirect_to;
        if (!$this->user_can_manage()) return $redirect_to;
        $queued = 0;
        foreach ($post_ids as $id){
            if ($this->queue_attachment($id, 'bulk')) {
                $queued++;
            }
        }
        if ($queued > 0) {
            Queue::schedule_processing(10);
            
            // Log bulk operation
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Bulk alt text generation queued', [
                    'count' => $queued,
                    'total_selected' => count($post_ids),
                ], 'bulk');
            }
        }
        return add_query_arg(['bbai_queued' => $queued], $redirect_to);
    }

    public function row_action_link($actions, $post){
        if ($post->post_type === 'attachment' && $this->is_image($post->ID)){
            $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            $generate_label   = __('Generate Alt Text (AI)', 'beepbeep-ai-alt-text-generator');
            $regenerate_label = __('Regenerate Alt Text (AI)', 'beepbeep-ai-alt-text-generator');
            $text = $has_alt ? $regenerate_label : $generate_label;
            $actions['bbai_generate_single'] = '<a href="#" class="bbai-generate" data-id="' . intval($post->ID) . '" data-has-alt="' . ($has_alt ? '1' : '0') . '" data-label-generate="' . esc_attr($generate_label) . '" data-label-regenerate="' . esc_attr($regenerate_label) . '">' . esc_html($text) . '</a>';
        }
        return $actions;
    }

    public function attachment_fields_to_edit($fields, $post){
        if (!$this->is_image($post->ID)){
            return $fields;
        }

        $has_alt = (bool) get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $label_generate   = __('Generate Alt Text', 'beepbeep-ai-alt-text-generator');
        $label_regenerate = __('Regenerate Alt Text', 'beepbeep-ai-alt-text-generator');
        $current_label    = $has_alt ? $label_regenerate : $label_generate;
        $is_authenticated = $this->api_client->is_authenticated();
        $disabled_attr    = !$is_authenticated ? ' disabled title="' . esc_attr__('Please log in to generate alt text', 'beepbeep-ai-alt-text-generator') . '"' : '';
        
        // Use unified button classes and correct data attributes for JavaScript handler
        $button = sprintf(
            '<button type="button" class="button button-secondary bbai-btn bbai-btn-secondary" data-action="regenerate-single" data-attachment-id="%1$d"%2$s>%3$s</button>',
            intval($post->ID),
            $disabled_attr,
            esc_html($current_label)
        );

        // Always show the regenerate button on attachment edit screen
        $fields['bbai_regenerate'] = [
            'label' => __('AI Alt Text', 'beepbeep-ai-alt-text-generator'),
            'input' => 'html',
            'html'  => $button . '<p class="description">' . esc_html__('Use AI to generate or regenerate alternative text for this image.', 'beepbeep-ai-alt-text-generator') . '</p>',
        ];

        return $fields;
    }

	/**
	 * @deprecated 4.3.0 Use REST_Controller::register_routes().
	 */
	public function register_rest_routes(){
		if ( ! class_exists( '\BeepBeepAI\AltTextGenerator\REST_Controller' ) ) {
			require_once BEEPBEEP_AI_PLUGIN_DIR . 'admin/class-bbai-rest-controller.php';
		}

		( new REST_Controller( $this ) )->register_routes();
	}

    /**
     * Whitelist and sanitize API user data before exposing it to JS via wp_localize_script().
     *
     * The API response can include sensitive fields (for example license keys). Only
     * include the minimal safe subset needed by the UI.
     *
     * @param mixed $user_data Raw user data from API client / options.
     * @return array Sanitized user data safe for client-side consumption.
     */
    private function sanitize_api_user_data_for_localize($user_data): array {
        if (!is_array($user_data)) {
            return [];
        }

        $sanitized = [];

        // Flat allowlist: key => sanitizer.
        $allowed = [
            'id'              => 'sanitize_text_field',
            '_id'             => 'sanitize_text_field',
            'email'           => 'sanitize_email',
            'firstName'       => 'sanitize_text_field',
            'lastName'        => 'sanitize_text_field',
            'plan'            => 'sanitize_key',
            'planSlug'        => 'sanitize_key',
            'plan_type'       => 'sanitize_key',
            'tokensRemaining' => 'absint',
            'tokenLimit'      => 'absint',
        ];

        foreach ($allowed as $key => $sanitizer) {
            if (!array_key_exists($key, $user_data)) {
                continue;
            }

            $value = $user_data[$key];
            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($sanitizer === 'absint') {
                $sanitized[$key] = absint($value);
                continue;
            }

            $sanitized[$key] = call_user_func($sanitizer, (string) $value);
        }

        // Optional nested org summary (still allowlisted).
        if (isset($user_data['organization']) && is_array($user_data['organization'])) {
            $org_allowed = [
                'id'              => 'sanitize_text_field',
                '_id'             => 'sanitize_text_field',
                'name'            => 'sanitize_text_field',
                'plan'            => 'sanitize_key',
                'tokenLimit'      => 'absint',
                'tokensRemaining' => 'absint',
                'resetDate'       => 'sanitize_text_field',
            ];

            $org_sanitized = [];
            foreach ($org_allowed as $key => $sanitizer) {
                if (!array_key_exists($key, $user_data['organization'])) {
                    continue;
                }

                $value = $user_data['organization'][$key];
                if (is_array($value) || is_object($value)) {
                    continue;
                }

                if ($sanitizer === 'absint') {
                    $org_sanitized[$key] = absint($value);
                    continue;
                }

                $org_sanitized[$key] = call_user_func($sanitizer, (string) $value);
            }

            if (!empty($org_sanitized)) {
                $sanitized['organization'] = $org_sanitized;
            }
        }

        return $sanitized;
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
            $path = $base . $name . $extension;

            if ($debug && !file_exists($base_path . $path)) {
                $dist_base = str_replace('assets/src/', 'assets/dist/', $base);
                $dist_path = $dist_base . $name . ".min.$type";
                if (file_exists($base_path . $dist_path)) {
                    return $dist_path;
                }
            }

            // If minified file doesn't exist, fall back to source file
            if (!$debug && !file_exists($base_path . $path)) {
                $source_base = str_replace('assets/dist/', 'assets/src/', $base);
                return $source_base . $name . ".$type";
            }

            return $path;
        };

        $admin_file    = $asset_path($js_base, 'bbai-admin', $use_debug_assets, 'js');
        $admin_version = $asset_version($admin_file, '3.0.0');
        
        $checkout_prices = $this->get_checkout_price_ids();

        $l10n_common = [
            'reviewCue'           => __('Visit the ALT Library to double-check the wording.', 'beepbeep-ai-alt-text-generator'),
            'statusReady'         => '',
            'previewAltHeading'   => __('Review generated ALT text', 'beepbeep-ai-alt-text-generator'),
            'previewAltHint'      => __('Review the generated description before applying it to your media item.', 'beepbeep-ai-alt-text-generator'),
            'previewAltApply'     => __('Use this ALT', 'beepbeep-ai-alt-text-generator'),
            'previewAltCancel'    => __('Keep current ALT', 'beepbeep-ai-alt-text-generator'),
            'previewAltDismissed' => __('Preview dismissed. Existing ALT kept.', 'beepbeep-ai-alt-text-generator'),
            'previewAltShortcut'  => __('Shift + Enter for newline.', 'beepbeep-ai-alt-text-generator'),
        ];

        // Detect any BeepBeep AI admin screens (top-level or subpages)
        // Check hook name first, then fallback to $_GET['page'] parameter
	        $page_raw = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	        $current_page = is_string($page_raw) ? sanitize_key($page_raw) : '';
        $hook_str = (string)($hook ?? '');
        $is_bbai_page = strpos($hook_str, 'toplevel_page_bbai') === 0
            || strpos($hook_str, 'bbai_page_bbai') === 0
            || strpos($hook_str, 'media_page_bbai') === 0
            || strpos($hook_str, '_page_bbai') !== false
            || (!empty($current_page) && strpos($current_page, 'bbai') === 0);

        // Load on Media Library, attachment edit, and any BeepBeep AI admin screen
        if (in_array($hook, ['upload.php', 'post.php', 'post-new.php'], true) || $is_bbai_page){
            wp_enqueue_script('bbai-admin', $base_url . $admin_file, ['jquery', 'wp-i18n'], $admin_version, true);
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
                'upgradeUrl'=> esc_url( Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'canManage' => $this->user_can_manage(),
                'inlineBatchSize' => defined('BBAI_INLINE_BATCH') ? max(1, intval(BBAI_INLINE_BATCH)) : 1,
            ]);
            // Also add bbai_ajax for regenerate functionality
            wp_localize_script('bbai-admin', 'bbai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
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

            if (in_array($hook, ['upload.php', 'post.php', 'post-new.php'], true)) {
                $upload_fallback = $asset_path($js_base, 'bbai-upload-fallback', $use_debug_assets, 'js');
                wp_enqueue_script(
                    'bbai-upload-fallback',
                    $base_url . $upload_fallback,
                    ['jquery', 'bbai-admin'],
                    $asset_version($upload_fallback, '1.0.0'),
                    true
                );
            }
        }

        // Always load dashboard CSS/JS if on a bbai page (double-check with $_GET)
        if ($is_bbai_page || (!empty($current_page) && strpos($current_page, 'bbai') === 0)){
            $css_file    = $asset_path($css_base, 'bbai-dashboard', $use_debug_assets, 'css');
            $js_file     = $asset_path($js_base, 'bbai-dashboard', $use_debug_assets, 'js');
            $usage_bridge = $asset_path($js_base, 'usage-components-bridge', $use_debug_assets, 'js');
            $upgrade_css = $asset_path($css_base, 'upgrade-modal', $use_debug_assets, 'css');
            $upgrade_js  = $asset_path($js_base, 'upgrade-modal', $use_debug_assets, 'js');
            $auth_css    = $asset_path($css_base, 'auth-modal', $use_debug_assets, 'css');
            $auth_js     = $asset_path($js_base, 'auth-modal', $use_debug_assets, 'js');
            $dashboard_scripts_js = $asset_path($js_base, 'bbai-dashboard-scripts', $use_debug_assets, 'js');
            $admin_panel_js = $asset_path($js_base, 'bbai-admin-panel', $use_debug_assets, 'js');

            // Enqueue unified CSS bundle (v6.0 - single optimized stylesheet)
            // This replaces: design-system, components, dashboard, ui, button-enhancements,
            // guide-settings, debug, bulk-progress, success-modal, auth-modal, upgrade-modal
            $unified_css = $use_debug_assets ? 'assets/css/unified.css' : 'assets/css/unified.min.css';
            wp_enqueue_style(
                'bbai-unified',
                $base_url . $unified_css,
                [],
                $asset_version($unified_css, '6.0.0')
            );

            // Enqueue modern bundle CSS (contains page-specific layouts from modern-style.css)
            wp_enqueue_style(
                'bbai-modern',
                $base_url . 'assets/css/modern.bundle.min.css',
                ['bbai-unified'],
                $asset_version('assets/css/modern.bundle.min.css', '5.0.0')
            );

            if ($current_page === 'bbai-onboarding') {
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

                // Check if onboarding is already completed (for idempotent JS behavior)
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
                    'libraryUrl'            => admin_url('admin.php?page=bbai-library'),
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

            $hero_css = ".bbai-hero-section {\n" .
                "    background: linear-gradient(to bottom, #f7fee7 0%, #ffffff 100%);\n" .
                "    border-radius: 24px;\n" .
                "    margin-bottom: 32px;\n" .
                "    padding: 48px 40px;\n" .
                "    text-align: center;\n" .
                "    box-shadow: 0 4px 16px rgba(0,0,0,0.08);\n" .
                "}\n" .
                ".bbai-hero-content {\n" .
                "    margin-bottom: 32px;\n" .
                "}\n" .
                ".bbai-hero-title {\n" .
                "    margin: 0 0 16px 0;\n" .
                "    font-size: 2.5rem;\n" .
                "    font-weight: 700;\n" .
                "    color: #0f172a;\n" .
                "    line-height: 1.2;\n" .
                "}\n" .
                ".bbai-hero-subtitle {\n" .
                "    margin: 0;\n" .
                "    font-size: 1.125rem;\n" .
                "    color: #475569;\n" .
                "    line-height: 1.6;\n" .
                "    max-width: 600px;\n" .
                "    margin-left: auto;\n" .
                "    margin-right: auto;\n" .
                "}\n" .
                ".bbai-hero-actions {\n" .
                "    display: flex;\n" .
                "    flex-direction: column;\n" .
                "    align-items: center;\n" .
                "    gap: 16px;\n" .
                "    margin-bottom: 24px;\n" .
                "}\n" .
                ".bbai-hero-btn-primary {\n" .
                "    background: linear-gradient(135deg, #14b8a6 0%, #84cc16 100%);\n" .
                "    color: white;\n" .
                "    border: none;\n" .
                "    padding: 16px 32px;\n" .
                "    border-radius: 16px;\n" .
                "    font-size: 16px;\n" .
                "    font-weight: 600;\n" .
                "    cursor: pointer;\n" .
                "    transition: opacity 0.2s ease;\n" .
                "    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);\n" .
                "}\n" .
                ".bbai-hero-btn-primary:hover {\n" .
                "    opacity: 0.9;\n" .
                "}\n" .
                ".bbai-hero-link-secondary {\n" .
                "    background: transparent;\n" .
                "    border: none;\n" .
                "    color: #6b7280;\n" .
                "    text-decoration: underline;\n" .
                "    font-size: 14px;\n" .
                "    cursor: pointer;\n" .
                "    transition: color 0.2s ease;\n" .
                "    padding: 0;\n" .
                "}\n" .
                ".bbai-hero-link-secondary:hover {\n" .
                "    color: #14b8a6;\n" .
                "}\n" .
                ".bbai-hero-micro-copy {\n" .
                "    font-size: 14px;\n" .
                "    color: #64748b;\n" .
                "    font-weight: 500;\n" .
                "}\n";

            wp_add_inline_style('bbai-unified', $hero_css);

            // Custom modal system (replaces native alert())
            wp_enqueue_style(
                'bbai-modal',
                $base_url . $asset_path($css_base, 'bbai-modal', $use_debug_assets, 'css'),
                ['bbai-unified'],
                $asset_version($asset_path($css_base, 'bbai-modal', $use_debug_assets, 'css'), '4.3.0')
            );


            $stats_data = $this->get_media_stats();
            $usage_data = Usage_Tracker::get_stats_display();
            
            wp_enqueue_script(
                'bbai-dashboard',
                $base_url . $js_file,
                ['jquery', 'wp-api-fetch', 'wp-i18n'],
                $asset_version($js_file, '3.0.0'),
                true
            );

            $analytics_js = $asset_path($js_base, 'bbai-analytics', $use_debug_assets, 'js');
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
            wp_enqueue_script(
                'bbai-auth',
                $base_url . $auth_js,
                ['jquery', 'wp-i18n'],
                $asset_version($auth_js, '4.0.0'),
                true
            );

            wp_enqueue_script(
                'bbai-admin-panel',
                $base_url . $admin_panel_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($admin_panel_js, '1.0.0'),
                true
            );

            wp_enqueue_script(
                'bbai-dashboard-scripts',
                $base_url . $dashboard_scripts_js,
                ['jquery', 'bbai-dashboard'],
                $asset_version($dashboard_scripts_js, '1.0.0'),
                true
            );

            // Debug logger (must load first)
            wp_enqueue_script(
                'bbai-logger',
                $base_url . $asset_path($js_base, 'bbai-logger', $use_debug_assets, 'js'),
                [],
                $asset_version($asset_path($js_base, 'bbai-logger', $use_debug_assets, 'js'), '4.3.0'),
                true
            );

            // Tooltip system
            wp_enqueue_style(
                'bbai-tooltips',
                $base_url . $asset_path($css_base, 'bbai-tooltips', $use_debug_assets, 'css'),
                ['bbai-unified'],
                $asset_version($asset_path($css_base, 'bbai-tooltips', $use_debug_assets, 'css'), '4.3.0')
            );
            wp_enqueue_script(
                'bbai-tooltips',
                $base_url . $asset_path($js_base, 'bbai-tooltips', $use_debug_assets, 'js'),
                ['jquery', 'wp-i18n'],
                $asset_version($asset_path($js_base, 'bbai-tooltips', $use_debug_assets, 'js'), '4.3.0'),
                true
            );

            // Custom modal system (must load before other scripts that use it)
            wp_enqueue_script(
                'bbai-modal',
                $base_url . $asset_path($js_base, 'bbai-modal', $use_debug_assets, 'js'),
                ['jquery', 'bbai-logger', 'wp-i18n'],
                $asset_version($asset_path($js_base, 'bbai-modal', $use_debug_assets, 'js'), '4.3.0'),
                true
            );

            // Pricing modal bridge (handles upgrade modal triggers)
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

            // Enqueue usage components bridge (requires React/ReactDOM to be loaded separately)
            // This is optional and should not block other functionality if missing
            // Only load if React is detected (check via wp_script_is or similar)
            // For now, we'll comment this out to prevent blocking core functionality
            // TODO: Re-enable when React components are properly built
            /*
            if (file_exists($base_path . $usage_bridge)) {
                wp_enqueue_script(
                    'bbai-usage-bridge',
                    $base_url . $usage_bridge,
                    ['bbai-dashboard'],
                    $asset_version($usage_bridge, '1.0.0'),
                    true
                );
            }
            */
            wp_enqueue_script(
                'bbai-debug',
                $base_url . $asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'),
                ['jquery'],
                $asset_version($asset_path($js_base, 'bbai-debug', $use_debug_assets, 'js'), '1.0.0'),
                true
            );

            // Enqueue contact modal script and styles
            wp_enqueue_script(
                'bbai-contact-modal',
                $base_url . $asset_path($js_base, 'bbai-contact-modal', $use_debug_assets, 'js'),
                ['jquery', 'wp-i18n'],
                $asset_version($asset_path($js_base, 'bbai-contact-modal', $use_debug_assets, 'js'), '1.0.0'),
                true
            );

            wp_enqueue_style(
                'bbai-contact-modal',
                $base_url . $asset_path($css_base, 'bbai-contact-modal', $use_debug_assets, 'css'),
                ['bbai-unified'],
                $asset_version($asset_path($css_base, 'bbai-contact-modal', $use_debug_assets, 'css'), '1.0.0')
            );

            // Localize contact modal script
            wp_localize_script('bbai-contact-modal', 'bbaiContactData', [
                'wp_version' => get_bloginfo('version'),
                'plugin_version' => BEEPBEEP_AI_VERSION,
            ]);
            
            // Localize debug script configuration
            wp_localize_script('bbai-debug', 'BBAI_DEBUG', [
                'restLogs' => esc_url_raw( rest_url('bbai/v1/logs') ),
                'restClear' => esc_url_raw( rest_url('bbai/v1/logs/clear') ),
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
                'upgradeUrl'  => esc_url( Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl'=> esc_url( Usage_Tracker::get_billing_portal_url() ),
                'checkoutPrices' => $checkout_prices,
                'stats'       => $stats_data,
                'initialUsage'=> $usage_data,
            ]);
            
            // Add AJAX variables for regenerate functionality and auth
            $options = get_option(self::OPTION_KEY, []);
            // Production API URL - always use production
            $production_url = 'https://alttext-ai-backend.onrender.com';
            $api_url = $production_url;
            $sanitized_user_data = $this->sanitize_api_user_data_for_localize($this->api_client->get_user_data());

            wp_localize_script('bbai-dashboard', 'bbai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'ajax_url'=> admin_url('admin-ajax.php'),
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
            
            wp_localize_script('bbai-dashboard', 'BBAI_DASH_L10N', [
                'l10n'        => array_merge([
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
            
            // Localize upgrade modal script
            wp_localize_script('bbai-upgrade', 'BBAI_UPGRADE', [
                'nonce' => wp_create_nonce('beepbeepai_nonce'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'usage' => $usage_data,
                'upgradeUrl' => esc_url( Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl' => esc_url( Usage_Tracker::get_billing_portal_url() ),
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
            if ($res->get_error_code() === 'bbai_dry_run') {
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
    /**
     * Check onboarding status
     */
    public function ajax_check_onboarding() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $completed = false;
        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            $completed = \BeepBeepAI\AltTextGenerator\Onboarding::is_completed();
        }

        wp_send_json_success(['completed' => $completed]);
    }

    /**
     * Check milestone
     */
    public function ajax_check_milestone() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $milestone_raw = isset($_POST['milestone']) ? wp_unslash($_POST['milestone']) : 0;
        $milestone = absint($milestone_raw);
        if ($milestone <= 0) {
            wp_send_json_error(['message' => __('Invalid milestone.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            $user_milestones = \BeepBeepAI\AltTextGenerator\Onboarding::get_milestones();
            $new_milestone = !in_array($milestone, $user_milestones, true);
            wp_send_json_success(['new_milestone' => $new_milestone]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Track milestone
     */
    public function ajax_track_milestone() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $milestone_raw = isset($_POST['milestone']) ? wp_unslash($_POST['milestone']) : 0;
        $milestone = absint($milestone_raw);
        if ($milestone <= 0) {
            wp_send_json_error(['message' => __('Invalid milestone.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            \BeepBeepAI\AltTextGenerator\Onboarding::add_milestone($milestone);
            wp_send_json_success(['message' => __('Milestone tracked.', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Complete onboarding
     */
    public function ajax_complete_onboarding() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Only mark completed if user is authenticated with BeepBeep
        if (!bbai_is_authenticated()) {
            wp_send_json_error(['message' => __('Authentication required.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (class_exists('\BeepBeepAI\AltTextGenerator\Onboarding')) {
            // Idempotent: safe to call multiple times
            \BeepBeepAI\AltTextGenerator\Onboarding::mark_completed();
            \BeepBeepAI\AltTextGenerator\Onboarding::update_last_seen();
            wp_send_json_success(['message' => __('Onboarding completed.', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Onboarding class not found.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * Dismiss the monthly reset insight modal for the current billing period.
     */
    public function ajax_dismiss_reset_modal() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        \BeepBeepAI\AltTextGenerator\Usage_Tracker::mark_reset_shown();
        wp_send_json_success( [ 'message' => __( 'Reset modal dismissed.', 'beepbeep-ai-alt-text-generator' ) ] );
    }

    /**
     * Start onboarding scan (queue missing images).
     */
    public function ajax_start_scan() {
        $action = "bbai_onboarding";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $limit = apply_filters('bbai_onboarding_scan_limit', 500);
        $limit = max(1, min(500, absint($limit)));

        $ids = $this->get_missing_attachment_ids($limit);
        if (empty($ids)) {
            // No images need alt text - still proceed to Step 3 for full onboarding experience
            // Onboarding will be marked complete when Step 3 is viewed
            $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');
            wp_send_json_success([
                'message' => __('Your media library is already optimized! All images have alt text.', 'beepbeep-ai-alt-text-generator'),
                'queued'  => 0,
                'total'   => 0,
                'redirect' => $step3_url,
            ]);
        }

        $queued = Queue::enqueue_many($ids, 'onboarding');
        if ($queued > 0) {
            Queue::schedule_processing();
        }

        // Note: We no longer mark completed here - Step 3 will mark it when viewed
        // This ensures user always sees Step 3 at least once

        // Redirect to Step 3 review screen
        $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: number of images queued */
                _n('%d image queued for processing.', '%d images queued for processing.', $queued, 'beepbeep-ai-alt-text-generator'),
                intval($queued)
            ),
            'queued'   => intval($queued),
            'total'    => count($ids),
            'redirect' => $step3_url,
        ]);
    }

    /**
     * Skip onboarding step and redirect to Step 3.
     * Note: Onboarding is marked completed when Step 3 is viewed, not here.
     */
    public function ajax_onboarding_skip() {
        $action = "bbai_onboarding";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Permission denied.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Note: We no longer mark completed here - Step 3 will mark it when viewed
        // This ensures user always sees Step 3 at least once

        // Redirect to Step 3 review screen so user learns where to review
        $step3_url = admin_url('admin.php?page=bbai-onboarding&step=3');

        wp_send_json_success([
            'message'  => __('Onboarding skipped.', 'beepbeep-ai-alt-text-generator'),
            'redirect' => $step3_url,
        ]);
    }

    public function ajax_dismiss_api_notice() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        
        // Store as site option so it shows only once globally, not per user
        update_option('bbai_api_notice_dismissed', true, false);
        delete_option('wp_alt_text_api_notice_dismissed');
        wp_send_json_success(['message' => __('Notice dismissed', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_dismiss_upgrade() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        Usage_Tracker::dismiss_upgrade_notice();
        setcookie('bbai_upgrade_dismissed', '1', time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        
        wp_send_json_success(['message' => 'Notice dismissed']);
    }
    
    /**
     * AJAX handler: Refresh usage data
     */
    public function ajax_queue_retry_job() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        $job_id_raw = isset($_POST['job_id']) ? wp_unslash($_POST['job_id']) : '';
        $job_id = absint($job_id_raw);
        if ($job_id <= 0) {
            wp_send_json_error(['message' => __('Invalid job ID.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::retry_job($job_id);
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Job re-queued.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_retry_failed() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::retry_failed();
        Queue::schedule_processing(10);
        wp_send_json_success(['message' => __('Retry scheduled for failed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_clear_completed() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        Queue::clear_completed();
        wp_send_json_success(['message' => __('Cleared completed jobs.', 'beepbeep-ai-alt-text-generator')]);
    }

    public function ajax_queue_stats() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
        
        $stats = Queue::get_stats();
        $failures = Queue::get_failures();
        
        wp_send_json_success([
            'stats' => $stats,
            'failures' => $failures
        ]);
    }

    public function ajax_track_upgrade() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
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
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        // Clear cache and fetch fresh data
        Usage_Tracker::clear_cache();
        $usage = $this->api_client->get_usage();
        
	        if ($usage) {
	            $stats = Usage_Tracker::get_stats_display();
	            wp_send_json_success($stats);
	        } else {
	            wp_send_json_error(['message' => __('Failed to fetch usage data', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
    }

    /**
     * AJAX handler: Regenerate single image alt text
     */
    public function ajax_regenerate_single() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        $attachment_id_raw = isset($_POST['attachment_id']) ? wp_unslash($_POST['attachment_id']) : '';
	        $attachment_id = absint($attachment_id_raw);
	        if (!$attachment_id) {
	            wp_send_json_error(['message' => __('Invalid attachment ID', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        // Log the attachment_id being received for debugging
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('info', 'Regenerate request received', [
                'attachment_id_raw' => $attachment_id_raw,
                'attachment_id' => $attachment_id,
            ], 'generation');
        }
        
        $has_license = $this->api_client->has_active_license();

        // Check if user has reached their limit (skip in local dev mode and for license accounts)
        // Use has_reached_limit() which includes cached usage fallback for better reliability
        if (!$has_license && (!defined('WP_LOCAL_DEV') || !WP_LOCAL_DEV)) {
            if ($this->api_client->has_reached_limit()) {
                // Get usage data for the error response (prefer cached if API failed)
                $usage = $this->api_client->get_usage();
                if (is_wp_error($usage)) {
                    // Fall back to cached usage for display
                    require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                    $usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
                }
                
                wp_send_json_error([
                    'message' => 'Monthly limit reached',
                    'code' => 'limit_reached',
                    'usage' => is_array($usage) ? $usage : null
                ]);
                return;
            }
        }
        
        $result = $this->generate_and_save($attachment_id, 'ajax', 1, [], true);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            // Provide more user-friendly error messages
            $user_message = $error_message;
            
            // Handle specific error codes with better messages
            if ($error_code === 'missing_alt_text') {
                $user_message = __('The API returned a response but no alt text was generated. This may be a temporary issue. Please try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_response_invalid') {
                $user_message = __('The API response was invalid. Please try again in a moment.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'quota_check_mismatch') {
                $user_message = __('Credits appear available but the backend reported a limit. Please try again in a moment.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'limit_reached' || $error_code === 'quota_exhausted') {
                $reset_date = null;
                if (is_array($error_data) && isset($error_data['usage']) && is_array($error_data['usage'])) {
                    $reset_date = $error_data['usage']['resetDate'] ?? null;
                }
                
                $user_message = __('Monthly quota exhausted. Your quota will reset on the first of next month. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator');
                
                if ($reset_date) {
                    try {
                        $reset_ts = strtotime($reset_date);
                        if ($reset_ts !== false) {
                            $formatted_date = date_i18n('F j, Y', $reset_ts);
                            $user_message = sprintf(
                                /* translators: 1: reset date */
                                __('Monthly quota exhausted. Your quota will reset on %s. Upgrade to Growth for 1,000 generations per month, or manage your subscription in Settings.', 'beepbeep-ai-alt-text-generator'),
                                $formatted_date
                            );
                        }
                    } catch (\Exception $e) {
                        // Keep default message if date parsing fails
                    }
                }
            } elseif ($error_code === 'api_timeout') {
                $user_message = __('The request timed out. Please try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_unreachable') {
                $user_message = __('Unable to reach the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
            }
            
            wp_send_json_error([
                'message' => $user_message,
                'code'    => $error_code,
                'data'    => $error_data,
            ]);
            return;
        }

        // Get updated usage AFTER generation
        // generate_and_save() updates the cache, but we need to ensure we have the latest data
        // For free users, fetch fresh from API to ensure accuracy
        // For license users, read from license data
        
        $updated_usage = null;
        
        // For license users, read directly from license data
        if ($this->api_client->has_active_license()) {
            $license_data = $this->api_client->get_license_data();
            if ($license_data && isset($license_data['organization'])) {
                $org = $license_data['organization'];
                $plan = isset($org['plan']) ? strtolower($org['plan']) : 'free';
                $limit = isset($org['tokenLimit']) ? intval($org['tokenLimit']) : 
                         ($plan === 'free' ? 50 : ($plan === 'pro' ? 1000 : 10000));
                $tokens_remaining = isset($org['tokensRemaining']) ? max(0, intval($org['tokensRemaining'])) : $limit;
                $used = max(0, $limit - $tokens_remaining);
                
                $reset_ts = strtotime('first day of next month');
                if (!empty($org['resetDate'])) {
                    $parsed = strtotime($org['resetDate']);
                    if ($parsed > 0) {
                        $reset_ts = $parsed;
                    }
                }

                $updated_usage = [
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => $tokens_remaining,
                    'plan' => $plan,
                    'resetDate' => wp_date('Y-m-d', $reset_ts),
                    'reset_timestamp' => $reset_ts,
                    'seconds_until_reset' => max(0, $reset_ts - time()),
                ];
                
                // Update the cache with this fresh data
                Usage_Tracker::update_usage($updated_usage);
            }
        }
        
        // For free users OR if license data didn't provide usage, fetch fresh from API
        // This ensures we have the most up-to-date usage data from the backend
        if (!$updated_usage || !is_array($updated_usage) || !isset($updated_usage['used'])) {
            // Clear cache first to force fresh fetch
            Usage_Tracker::clear_cache();
            
            // Fetch fresh usage from API - this should reflect the generation we just did
            $fresh_usage = $this->api_client->get_usage();
            if (!is_wp_error($fresh_usage) && is_array($fresh_usage)) {
                Usage_Tracker::update_usage($fresh_usage);
                $updated_usage = $fresh_usage;
                
                // Log for debugging
                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('info', 'Fetched fresh usage from API after regeneration', [
                        'used' => $fresh_usage['used'] ?? 'not set',
                        'remaining' => $fresh_usage['remaining'] ?? 'not set',
                        'limit' => $fresh_usage['limit'] ?? 'not set',
                    ], 'generation');
                }
            } else {
                // Fallback to cached usage if API fails
                $updated_usage = Usage_Tracker::get_cached_usage(false);
                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    Debug_Log::log('warning', 'API usage fetch failed, using cached usage', [
                        'api_error' => is_wp_error($fresh_usage) ? $fresh_usage->get_error_message() : 'unknown error',
                        'cached_usage' => $updated_usage,
                    ], 'generation');
                }
            }
        }
        
        // Only clear stats cache, not usage cache (we want to keep the fresh usage)
        $this->invalidate_stats_cache();
        
        // Ensure we have valid usage data to return
        if (!$updated_usage || !is_array($updated_usage) || !isset($updated_usage['used'])) {
            // Last resort: try to get cached usage
            $updated_usage = Usage_Tracker::get_cached_usage(false);
            
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('warning', 'No usage data available after regeneration, using cached fallback', [
                    'updated_usage_was_null' => $updated_usage === null,
                    'final_usage' => $updated_usage,
                ], 'generation');
            }
        }
        
        // Log final usage data being sent to frontend
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Sending usage data in AJAX response', [
                'usage' => $updated_usage,
                'has_used' => isset($updated_usage['used']),
                'has_remaining' => isset($updated_usage['remaining']),
                'has_limit' => isset($updated_usage['limit']),
            ], 'generation');
        }

        wp_send_json_success([
            'message'        => __('Alt text generated successfully.', 'beepbeep-ai-alt-text-generator'),
            'alt_text'       => $result,
            'altText'        => $result, // Also include camelCase for compatibility
            'attachment_id'  => $attachment_id,
            'usage'          => $updated_usage ?: null, // Include updated usage in response
            'data'           => [
                'alt_text' => $result,
                'altText'  => $result,
                'usage'    => $updated_usage ?: null,
            ],
        ]);
    }

    /**
     * AJAX handler: Bulk queue images for processing
     */
    public function ajax_bulk_queue() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', $attachment_ids_raw) : [];
        $source_raw = isset($_POST['source']) ? wp_unslash($_POST['source']) : 'bulk';
        $source = sanitize_text_field($source_raw);
        
	        if (empty($attachment_ids)) {
	            wp_send_json_error(['message' => __('Invalid attachment IDs', 'beepbeep-ai-alt-text-generator')]);
	            return;
	        }
        
        // Sanitize all IDs
        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0 && $this->is_image($id);
        });
        
	        if (empty($ids)) {
	            wp_send_json_error(['message' => __('No valid images found', 'beepbeep-ai-alt-text-generator')]);
	            return;
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
                return;
            } else {
                // Check how many we can queue
                $remaining = $usage['remaining'] ?? 0;
                if (count($ids) > $remaining) {
                    wp_send_json_error([
                        'message' => sprintf(
                            /* translators: 1: remaining generations */
                            __('You only have %d generations remaining. Please upgrade or select fewer images.', 'beepbeep-ai-alt-text-generator'),
                            $remaining
                        ),
                        'code' => 'insufficient_credits',
                        'remaining' => $remaining
                    ]);
                    return;
                }
            }
        }
        
        try {
            // Queue images (will clear existing entries for bulk-regenerate)
            $queued = Queue::enqueue_many($ids, $source);
            
            // Log bulk queue operation
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('info', 'Bulk queue operation', [
                    'queued' => $queued,
                    'requested' => count($ids),
                    'source' => $source,
                ], 'bulk');
            }
            
            if ($queued > 0) {
                // Schedule queue processing
                Queue::schedule_processing();
                
                wp_send_json_success([
                    'message' => sprintf(
                        /* translators: 1: number of images queued */
                        __('%d image(s) queued for processing', 'beepbeep-ai-alt-text-generator'),
                        $queued
                    ),
                    'queued' => $queued,
                    'total' => count($ids)
                ]);
            } else {
                // For regeneration, if nothing was queued, it might mean they're already completed
                // Check if images already have alt text and suggest direct regeneration instead
                
	                if ($source === 'bulk-regenerate') {
	                    wp_send_json_error([
	                        'message' => __('No images queued. Images may already be processing or have alt text. Refresh the page to see current status.', 'beepbeep-ai-alt-text-generator'),
	                        'code' => 'already_queued'
	                    ]);
	                    return;
	                } else {
	                    wp_send_json_error([
	                        'message' => __('Failed to queue images. They may already be queued or processing.', 'beepbeep-ai-alt-text-generator')
	                    ]);
	                    return;
	                }
	            }
	        } catch ( \Exception $e ) {
	            // Return proper JSON error instead of letting WordPress output HTML
	            wp_send_json_error([
	                'message' => __('Failed to queue images due to a database error. Please try again.', 'beepbeep-ai-alt-text-generator'),
	                'code' => 'queue_failed'
	            ]);
	            return;
	        }
	    }

    public function process_queue() {
        $batch_size = apply_filters('bbai_queue_batch_size', 3);
        $max_attempts = apply_filters('bbai_queue_max_attempts', 3);

        Queue::reset_stale(apply_filters('bbai_queue_stale_timeout', 10 * MINUTE_IN_SECONDS));

        $jobs = Queue::claim_batch($batch_size);
        if (empty($jobs)) {
            Queue::purge_completed(apply_filters('bbai_queue_purge_age', DAY_IN_SECONDS * 2));
            return;
        }

        foreach ($jobs as $job) {
            $attachment_id = intval($job->attachment_id);
            if ($attachment_id <= 0 || !$this->is_image($attachment_id)) {
                Queue::mark_complete($job->id);
                continue;
            }

            $result = $this->generate_and_save($attachment_id, $job->source ?? 'queue', max(0, intval($job->attempts) - 1));

            if (is_wp_error($result)) {
                $code    = $result->get_error_code();
                $message = $result->get_error_message();

                if ($code === 'limit_reached') {
                    Queue::mark_retry($job->id, $message);
                    Queue::schedule_processing(apply_filters('bbai_queue_limit_delay', HOUR_IN_SECONDS));
                    break;
                }

                if (intval($job->attempts) >= $max_attempts) {
                    Queue::mark_failed($job->id, $message);
                } else {
                    Queue::mark_retry($job->id, $message);
                }
                continue;
            }

            Queue::mark_complete($job->id);
        }

        Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();
        $stats = Queue::get_stats();
        if (!empty($stats['pending'])) {
            Queue::schedule_processing(apply_filters('bbai_queue_next_delay', 45));
        }

        Queue::purge_completed(apply_filters('bbai_queue_purge_age', DAY_IN_SECONDS * 2));
    }

    public function handle_media_change($attachment_id = 0) {
        $this->invalidate_stats_cache();

        if (current_filter() === 'delete_attachment') {
            Queue::schedule_processing(30);
            return;
        }

        $opts = get_option(self::OPTION_KEY, []);
        if (empty($opts['enable_on_upload'])) {
            return;
        }

        $this->queue_attachment($attachment_id, 'upload');
        Queue::schedule_processing(15);
    }

    public function handle_media_metadata_update($data, $post_id) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'metadata');
        Queue::schedule_processing(20);
        return $data;
    }

    public function handle_attachment_updated($post_id, $post_after, $post_before) {
        $this->invalidate_stats_cache();
        $this->queue_attachment($post_id, 'update');
        Queue::schedule_processing(20);
    }

    public function handle_post_save($post_ID, $post, $update) {
        if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
            $this->invalidate_stats_cache();
            if ($update) {
                $this->queue_attachment($post_ID, 'save');
                Queue::schedule_processing(20);
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
     * NOTE: ajax_register and ajax_login are defined in Core_Ajax_Auth trait
     */

    /**
     * AJAX handler: User login (duplicate removed - using trait version)
     */
    public function ajax_login() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

	        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
	        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
	        // Passwords may contain characters that sanitize_text_field() would alter.
	        $password_raw = wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	        $password     = is_string( $password_raw ) ? $password_raw : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'beepbeep-ai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     * Clears authentication token, license key, and all cached data
     */
    public function ajax_logout() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Clear JWT token (for authenticated users)
        $this->api_client->clear_token();
        
        // Clear license key (for agency/license-based users)
        $this->api_client->clear_license_key();
        
        // Clear user data
        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');
        
        // Clear usage cache
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');
        delete_transient('opptibbai_token_last_check');

        wp_send_json_success([
            'message' => __('Logged out successfully', 'beepbeep-ai-alt-text-generator'),
            'redirect' => admin_url('admin.php?page=bbai')
        ]);
    }

    /**
     * Handle logout via form submission (admin-post handler)
     */
		    public function handle_logout() {
		        // Log for debugging (only in debug mode)
		        if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		            error_log('BeepBeep AI: handle_logout called'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		        }

	        // Verify nonce
	        $action = 'bbai_logout_action';
		        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbai_logout_nonce'] ?? '' ) ), $action ) ) {
			            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
			                error_log('BeepBeep AI: Nonce verification failed'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
			            }
			            wp_die(esc_html__('Security check failed', 'beepbeep-ai-alt-text-generator'));
			        }

	        // Check permissions
		        if (!$this->user_can_manage()) {
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('BeepBeep AI: Permission check failed'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
		            wp_die(esc_html__('Unauthorized', 'beepbeep-ai-alt-text-generator'));
		        }

	        // Clear token and user data
		        if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		            error_log('BeepBeep AI: Clearing token'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		        }
	        $this->api_client->clear_token();

	        // ALSO clear license key (otherwise user stays authenticated via license)
		        if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		            error_log('BeepBeep AI: Clearing license key'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		        }
	        $this->api_client->clear_license_key();

        // Also clear any usage cache
        delete_transient('bbai_usage_cache');
	        delete_transient('opptibbai_usage_cache');

	        // Verify everything was cleared (only in debug mode)
		        if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		            $remaining_token = get_option('beepbeepai_jwt_token', '');
		            $remaining_license = get_option('beepbeepai_license_key', '');
		            error_log('BeepBeep AI: JWT Token after clear: ' . ($remaining_token ? 'STILL EXISTS!' : 'cleared')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            error_log('BeepBeep AI: License key after clear: ' . ($remaining_license ? 'STILL EXISTS!' : 'cleared')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		        }

	        // Redirect to dashboard with cache buster
		        if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		            error_log('BeepBeep AI: Redirecting to dashboard'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		        }
	        wp_safe_redirect(add_query_arg('nocache', time(), admin_url('admin.php?page=bbai')));
	        exit;
	    }

    public function ajax_disconnect_account() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Clear JWT token (for authenticated users)
        $this->api_client->clear_token();
        
        // Clear license key (for agency/license-based users)
        // This prevents automatic reconnection when using license keys
        $this->api_client->clear_license_key();
        
        // Clear user data
        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');
        
        // Clear usage cache
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');
        delete_transient('opptibbai_token_last_check');

        wp_send_json_success([
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'beepbeep-ai-alt-text-generator'),
        ]);
    }

    /**
     * AJAX handler: Activate license key
     */
    public function ajax_activate_license() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $license_key_raw = isset($_POST['license_key']) ? wp_unslash($_POST['license_key']) : '';
        $license_key = is_string($license_key_raw) ? sanitize_text_field($license_key_raw) : '';

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Validate UUID format
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key)) {
            wp_send_json_error(['message' => __('Invalid license key format', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->activate_license($license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        // Clear cached usage data
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');

        wp_send_json_success([
            'message' => __('License activated successfully', 'beepbeep-ai-alt-text-generator'),
            'organization' => $result['organization'] ?? null,
            'site' => $result['site'] ?? null
        ]);
    }

    /**
     * AJAX handler: Deactivate license key
     */
    public function ajax_deactivate_license() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->deactivate_license();

        // Clear cached usage data
        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => __('License deactivated successfully', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Get license site usage
     */
    public function ajax_get_license_sites() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Must be authenticated to view license site usage
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view license site usage', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Fetch license site usage from API
        $result = $this->api_client->get_license_sites();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to fetch license site usage', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Disconnect a site from the license
     */
    public function ajax_disconnect_license_site() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Must be authenticated to disconnect license sites
        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to disconnect license sites', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $site_id_raw = isset($_POST['site_id']) ? wp_unslash($_POST['site_id']) : '';
        $site_id = is_string($site_id_raw) ? sanitize_text_field($site_id_raw) : '';
        if (empty($site_id)) {
            wp_send_json_error([
                'message' => __('Site ID is required', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Disconnect the site from the license
        $result = $this->api_client->disconnect_license_site($site_id);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Failed to disconnect site', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Site disconnected successfully', 'beepbeep-ai-alt-text-generator'),
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
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
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
                'message' => __('Admin access is only available for agency licenses', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

	        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
	        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
	        // Passwords may contain characters that sanitize_text_field() would alter.
	        $password_raw = wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	        $password     = is_string( $password_raw ) ? $password_raw : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($password)) {
            wp_send_json_error([
                'message' => __('Please enter your password', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Attempt login
        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Login failed. Please check your credentials.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Set admin session
        $this->set_admin_session();

        wp_send_json_success([
            'message' => __('Successfully logged in', 'beepbeep-ai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Admin logout
     */
    public function ajax_admin_logout() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $this->clear_admin_session();

        wp_send_json_success([
            'message' => __('Logged out successfully', 'beepbeep-ai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        $user_info = $this->api_client->get_user_info();
        $usage = $this->api_client->get_usage();

        if (is_wp_error($user_info)) {
            wp_send_json_error(['message' => $user_info->get_error_message()]);
            return;
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
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Allow checkout without authentication - users can create account during checkout
        // Authentication is optional for checkout, backend will handle account creation

        $price_id_raw = isset($_POST['price_id']) ? wp_unslash($_POST['price_id']) : '';
        $price_id = is_string($price_id_raw) ? sanitize_text_field($price_id_raw) : '';

        // Resolve plan_id to a Stripe price ID when price_id is not provided directly.
        if (empty($price_id)) {
            $plan_id_raw = isset($_POST['plan_id']) ? wp_unslash($_POST['plan_id']) : '';
            $plan_id = is_string($plan_id_raw) ? sanitize_key($plan_id_raw) : '';
            if (!empty($plan_id)) {
                $price_id = $this->get_checkout_price_id($plan_id);
            }
        }

        if (empty($price_id)) {
            wp_send_json_error(['message' => __('Price ID is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $success_url = admin_url('upload.php?page=bbai&checkout=success');
        $cancel_url = admin_url('upload.php?page=bbai&checkout=cancel');

        // Create checkout session - works for both authenticated and unauthenticated users
        // If token is invalid, it will retry without token for guest checkout
        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_code = $result->get_error_code();
            
            // Don't show "session expired" messages for checkout - just show generic error
            $error_message_lower = is_string($error_message) ? strtolower($error_message) : '';
            if ($error_code === 'auth_required' ||
                strpos($error_message_lower, 'session') !== false ||
                strpos($error_message_lower, 'log in') !== false) {
                $error_message = __('Unable to create checkout session. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');
            }
            
            wp_send_json_error(['message' => $error_message]);
            return;
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
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
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
                'message' => __('Please log in to manage billing', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        // For admin-authenticated users with license, try using stored portal URL first
        if ($is_admin_authenticated && $has_agency_license && !$is_authenticated) {
            $stored_portal_url = Usage_Tracker::get_billing_portal_url();
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
                    'message' => __('To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'beepbeep-ai-alt-text-generator'),
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
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }

        // Pass through all data from backend, including reset link if provided
        $response_data = [
            'message' => __('Password reset link has been sent to your email. Please check your inbox and spam folder.', 'beepbeep-ai-alt-text-generator'),
        ];
        
        // Include reset link if provided (for development/testing when email service isn't configured)
        if (isset($result['resetLink'])) {
            $response_data['resetLink'] = $result['resetLink'];
            $response_data['note'] = $result['note'] ?? __('Email service is in development mode. Use this link to reset your password.', 'beepbeep-ai-alt-text-generator');
        }
        
        wp_send_json_success($response_data);
    }

    /**
     * AJAX handler: Reset password with token
     */
    public function ajax_reset_password() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

	        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
	        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
	        $token_raw = isset($_POST['token']) ? wp_unslash($_POST['token']) : '';
	        $token = is_string($token_raw) ? sanitize_text_field($token_raw) : '';
	        // Passwords may contain characters that sanitize_text_field() would alter.
	        $password_raw = wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	        $password     = is_string( $password_raw ) ? $password_raw : '';
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error([
                'message' => __('Please enter a valid email address', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($token)) {
            wp_send_json_error([
                'message' => __('Reset token is required', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (empty($password) || strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $result = $this->api_client->reset_password($email, $token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
            return;
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now sign in with your new password.', 'beepbeep-ai-alt-text-generator'),
            'redirect' => admin_url('upload.php?page=bbai&password_reset=success')
        ]);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view subscription information', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        $subscription_info = $this->api_client->get_subscription_info();

        if (is_wp_error($subscription_info)) {
            wp_send_json_error([
                'message' => $subscription_info->get_error_message()
            ]);
            return;
        }

        wp_send_json_success($subscription_info);
    }

    /**
     * AJAX handler: Inline generation for selected attachment IDs (used by progress modal)
     */
    public function ajax_inline_generate() {
        // Start output buffering for AJAX to prevent any output from breaking JSON response
        // This is critical - any echo, warning, or error before wp_send_json_success() will break the response
        if (ob_get_level() === 0) {
            ob_start();
        } else {
            ob_clean();
        }
        
        // Fix nonce check - use beepbeepai_nonce to match JavaScript
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $attachment_ids_raw = isset($_POST['attachment_ids']) ? wp_unslash($_POST['attachment_ids']) : [];
        $attachment_ids = is_array($attachment_ids_raw) ? array_map('absint', (array) $attachment_ids_raw) : [];
        if (empty($attachment_ids)) {
            wp_send_json_error(['message' => __('No attachment IDs provided.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $ids = array_map('intval', $attachment_ids);
        $ids = array_filter($ids, function($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            wp_send_json_error(['message' => __('Invalid attachment IDs.', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $results = [];
        foreach ($ids as $id) {
            if (!$this->is_image($id)) {
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => __('Attachment is not an image.', 'beepbeep-ai-alt-text-generator'),
                ];
                continue;
            }

            try {
                // CRITICAL: generate_and_save() will only log credits if alt_text is successfully generated
                // It validates alt_text exists before updating usage or logging credits
                $generation = $this->generate_and_save($id, 'inline', 1, [], true);
                
                if (is_wp_error($generation)) {
                    // Generation failed - credits should NOT be logged (handled in generate_and_save)
                    $results[] = [
                        'attachment_id' => $id,
                        'success' => false,
                        'message' => $generation->get_error_message(),
                        'code'    => $generation->get_error_code(),
                    ];
                } else {
                    // Generation succeeded - credits were already logged in generate_and_save()
                    $results[] = [
                        'attachment_id' => $id,
                        'success' => true,
                        'alt_text' => $generation,
                        'title'    => get_the_title($id),
                    ];
                }
            } catch (\Exception $e) {
                // Catch any unexpected errors during generation
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: error message */
                        __('Unexpected error during generation: %s', 'beepbeep-ai-alt-text-generator'),
                        $e->getMessage()
                    ),
                    'code'    => 'generation_exception',
                ];
            } catch (\Error $e) {
                // Catch PHP 7+ fatal errors
                $results[] = [
                    'attachment_id' => $id,
                    'success' => false,
                    'message' => sprintf(
                        /* translators: 1: error message */
                        __('Fatal error during generation: %s', 'beepbeep-ai-alt-text-generator'),
                        $e->getMessage()
                    ),
                    'code'    => 'generation_fatal',
                ];
            }
        }

        // Clean any output that might have been generated during processing
        // This is critical - any output before wp_send_json_success() will break the JSON response
        if (ob_get_level() > 0) {
            $ob_contents = ob_get_contents();
            if (!empty($ob_contents)) {
                // Log what was output (for debugging) but don't send it
                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Output detected before JSON response in ajax_inline_generate', [
                        'output_length' => strlen($ob_contents),
                        'output_preview' => substr($ob_contents, 0, 200),
                    ], 'ajax');
                }
            }
            ob_clean();
        }

        Usage_Tracker::clear_cache();
        $this->invalidate_stats_cache();

        // Ensure headers haven't been sent (which would break JSON response)
        if (headers_sent($file, $line)) {
            // Headers already sent - this is a critical error
            // Log it and try to send error response anyway
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'Headers already sent in ajax_inline_generate', [
                    'file' => $file,
                    'line' => $line,
                ], 'ajax');
            }
            // Still try to send JSON - wp_send_json_success handles this
        }
        
        // wp_send_json_success() will send headers and output, then exit
        // This ensures no output interferes with the JSON response
        wp_send_json_success([
            'results' => $results,
        ]);
        
        // This line should never be reached (wp_send_json_success exits)
        // But included for safety
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Export analytics report
     */
    /**
     * AJAX handler: Get recent activity for timeline
     */
    public function ajax_get_activity() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Get recent activity from usage logs
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/usage/class-usage-logs.php';
        
        $limit_raw = isset($_POST['limit']) ? wp_unslash($_POST['limit']) : 10;
        $limit = absint($limit_raw);
        $filters = [
            'per_page' => min($limit, 50), // Max 50 items
            'page' => 1,
        ];

        $result = \BeepBeepAI\AltTextGenerator\Usage\Usage_Logs::get_usage_events($filters);
        $events = isset($result['events']) ? $result['events'] : [];

        // Format events for the timeline display
        $activities = [];
        foreach ($events as $event) {
            $action_type = isset($event['action_type']) ? sanitize_text_field($event['action_type']) : 'generate';
            $image_id = isset($event['image_id']) ? absint($event['image_id']) : 0;
            $user_name = isset($event['display_name']) ? sanitize_text_field($event['display_name']) : __('System', 'beepbeep-ai-alt-text-generator');
            $created_at = isset($event['created_at']) ? $event['created_at'] : '';
            
            // Determine activity type and message
            $type = 'generate';
            $title = '';
            $description = '';
            
            if (strpos($action_type, 'bulk') !== false) {
                $type = 'bulk';
                $title = sprintf(__('Bulk alt text generation', 'beepbeep-ai-alt-text-generator'));
                $description = sprintf(
                    /* translators: 1: user name */
                    __('Processed by %s', 'beepbeep-ai-alt-text-generator'),
                    $user_name
                );
            } elseif (strpos($action_type, 'regenerate') !== false || strpos($action_type, 'reopt') !== false) {
                $type = 'regenerate';
                $title = sprintf(__('Alt text regenerated', 'beepbeep-ai-alt-text-generator'));
                if ($image_id > 0) {
                    $image_title = get_the_title($image_id);
                    $description = $image_title
                        ? sprintf(
                            /* translators: 1: image title */
                            __('Image: %s', 'beepbeep-ai-alt-text-generator'),
                            $image_title
                        )
                        : sprintf(
                            /* translators: 1: image ID */
                            __('Image ID: %d', 'beepbeep-ai-alt-text-generator'),
                            $image_id
                        );
                }
            } else {
                $type = 'generate';
                $title = sprintf(__('Alt text generated', 'beepbeep-ai-alt-text-generator'));
                if ($image_id > 0) {
                    $image_title = get_the_title($image_id);
                    $description = $image_title
                        ? sprintf(
                            /* translators: 1: image title */
                            __('Image: %s', 'beepbeep-ai-alt-text-generator'),
                            $image_title
                        )
                        : sprintf(
                            /* translators: 1: image ID */
                            __('Image ID: %d', 'beepbeep-ai-alt-text-generator'),
                            $image_id
                        );
                }
            }

            $activities[] = [
                'type' => $type,
                'action' => $action_type,
                'title' => $title,
                'description' => $description,
                'details' => $description,
                'timestamp' => $created_at,
                'timeAgo' => $this->format_time_ago($created_at),
            ];
        }

        wp_send_json_success($activities);
    }

    /**
     * Format timestamp as "time ago" string
     */
    private function format_time_ago($timestamp) {
        if (empty($timestamp)) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        }

        $time = strtotime($timestamp);
        if ($time === false || $time <= 0) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        }

        $diff = time() - $time;
        $minutes = floor($diff / 60);
        $hours = floor($diff / 3600);
        $days = floor($diff / 86400);

        if ($minutes < 1) {
            return __('Just now', 'beepbeep-ai-alt-text-generator');
        } elseif ($minutes < 60) {
            return sprintf(
                /* translators: 1: number of minutes */
                _n('%d minute ago', '%d minutes ago', $minutes, 'beepbeep-ai-alt-text-generator'),
                $minutes
            );
        } elseif ($hours < 24) {
            return sprintf(
                /* translators: 1: number of hours */
                _n('%d hour ago', '%d hours ago', $hours, 'beepbeep-ai-alt-text-generator'),
                $hours
            );
        } elseif ($days < 7) {
            return sprintf(
                /* translators: 1: number of days */
                _n('%d day ago', '%d days ago', $days, 'beepbeep-ai-alt-text-generator'),
                $days
            );
        } else {
            return date_i18n(get_option('date_format'), $time);
        }
    }

    /**
     * AJAX handler: Send contact form via Resend
     */
    public function ajax_send_contact_form() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        // Rate limiting check (3 submissions per hour per user)
        $user_id = get_current_user_id();
        $current_hour = wp_date('Y-m-d-H');
        $rate_limit_key = 'bbai_contact_limit_' . $user_id . '_' . $current_hour;
        $submission_count = get_transient($rate_limit_key);
        
        if ($submission_count !== false && intval($submission_count) >= 3) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before submitting another message.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Sanitize and validate input
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $wp_version = isset($_POST['wp_version']) ? sanitize_text_field(wp_unslash($_POST['wp_version'])) : get_bloginfo('version');
        $plugin_version = isset($_POST['plugin_version']) ? sanitize_text_field(wp_unslash($_POST['plugin_version'])) : BEEPBEEP_AI_VERSION;

        // Validate required fields
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            wp_send_json_error([
                'message' => __('Please fill in all required fields.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Invalid email address format.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        // Prepare contact data
        $contact_data = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'wp_version' => $wp_version,
            'plugin_version' => $plugin_version
        ];

        // Send via backend API (backend has Resend configured)
        $backend_response = $this->api_client->send_contact_email($contact_data);
        
        if (is_wp_error($backend_response)) {
            // Backend API failed - show user-friendly error
            $error_code = $backend_response->get_error_code();
            $error_message = $backend_response->get_error_message();
            
            // Provide more helpful error messages based on error type
            if ($error_code === 'auth_required' || $error_code === 'license_required') {
                $user_message = __('Unable to send message. Please ensure you are logged in and try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'api_unreachable' || $error_code === 'api_timeout') {
                $user_message = __('Unable to connect to the server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator');
            } elseif ($error_code === 'contact_email_failed') {
                $user_message = $error_message ?: __('Failed to send message. Please try again later.', 'beepbeep-ai-alt-text-generator');
            } else {
                $user_message = sprintf(
                    /* translators: 1: error message */
                    __('Unable to send message: %s', 'beepbeep-ai-alt-text-generator'),
                    $error_message ?: __('Unknown error', 'beepbeep-ai-alt-text-generator')
                );
            }
            
            wp_send_json_error([
                'message' => $user_message
            ]);
            return;
        }
        
        // Success via backend
        $result = $backend_response;

        // Save to database for viewing in admin
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        
        $site_hash = \BeepBeepAI\AltTextGenerator\get_site_identifier();
        $license_key = $this->api_client->get_license_key();
        
        $contact_data['site_url'] = get_site_url();
        $contact_data['site_hash'] = $site_hash;
        $contact_data['license_key'] = $license_key ?: null;
        
        \BeepBeepAI\AltTextGenerator\Contact_Submissions::save_submission($contact_data);

        // Increment rate limit counter
        if ($submission_count === false) {
            set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
        } else {
            set_transient($rate_limit_key, intval($submission_count) + 1, HOUR_IN_SECONDS);
        }

        // Success
        wp_send_json_success([
            'message' => $result['message'] ?? __('Your message has been sent successfully. We\'ll get back to you soon!', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Get contact form submissions
     */
    public function ajax_get_contact_submissions() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $args = [
            'per_page' => isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20,
            'page'     => isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
            'status'   => isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '',
            'search'   => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'orderby'  => isset($_POST['orderby']) ? sanitize_text_field(wp_unslash($_POST['orderby'])) : 'created_at',
            'order'    => isset($_POST['order']) ? sanitize_text_field(wp_unslash($_POST['order'])) : 'DESC',
        ];

        // If ID is provided, get single submission
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = absint(wp_unslash($_POST['id']));
            $submission = \BeepBeepAI\AltTextGenerator\Contact_Submissions::get_submission($id);
            if ($submission) {
                wp_send_json_success([
                    'items' => [$submission],
                    'total' => 1,
                    'pages' => 1
                ]);
            } else {
                wp_send_json_error(['message' => __('Submission not found', 'beepbeep-ai-alt-text-generator')]);
                return;
            }
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::get_submissions($args);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler: Update contact submission status
     */
    public function ajax_update_contact_submission_status() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::update_status($id, $status);

        if ($result) {
            wp_send_json_success(['message' => __('Status updated successfully', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update status', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

    /**
     * AJAX handler: Delete contact submission
     */
    public function ajax_delete_contact_submission() {
        $action = "beepbeepai_nonce";
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
            return;
        }
        
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-contact-submissions.php';

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid submission ID', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = \BeepBeepAI\AltTextGenerator\Contact_Submissions::delete_submission($id);

        if ($result) {
            wp_send_json_success(['message' => __('Submission deleted successfully', 'beepbeep-ai-alt-text-generator')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete submission', 'beepbeep-ai-alt-text-generator')]);
            return;
        }
    }

	    public function ajax_export_analytics() {
	        $action = 'beepbeepai_nonce';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
	            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
	            return;
	        }

	        if (!$this->user_can_manage()) {
	            wp_die(esc_html__('Permission denied.', 'beepbeep-ai-alt-text-generator'));
	        }

		        global $wpdb;
		        $posts_table    = esc_sql( $wpdb->posts );
		        $postmeta_table = esc_sql( $wpdb->postmeta );
		        $image_mime_like = $wpdb->esc_like('image/') . '%';

		        // Get stats
		        $total_images = (int) $wpdb->get_var($wpdb->prepare(
		            "SELECT COUNT(*) FROM `{$posts_table}` WHERE post_type = %s AND post_status = %s AND post_mime_type LIKE %s",
		            'attachment', 'inherit', $image_mime_like
		        ));

		        $with_alt_count = (int) $wpdb->get_var($wpdb->prepare(
		            "SELECT COUNT(DISTINCT p.ID) FROM `{$posts_table}` p INNER JOIN `{$postmeta_table}` pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_mime_type LIKE %s AND p.post_status = %s AND pm.meta_key = %s AND TRIM(pm.meta_value) <> %s",
		            'attachment', $image_mime_like, 'inherit', '_wp_attachment_image_alt', ''
		        ));

	        $missing_count = $total_images - $with_alt_count;
        $coverage_percent = $total_images > 0 ? round(($with_alt_count / $total_images) * 100) : 0;

        // Get usage stats
        $usage_stats = $this->api_client->get_usage_stats();
	        $alt_texts_generated = isset($usage_stats['used']) ? (int) $usage_stats['used'] : 0;
	
	        // Generate CSV
	        $filename = 'beepbeep-ai-analytics-' . wp_date('Y-m-d') . '.csv';
	        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $this->bbai_prepare_download_stream();
        // BOM for Excel compatibility
        $this->bbai_output_contents(chr(0xEF).chr(0xBB).chr(0xBF));

	        // Rows
	        $this->bbai_output_contents($this->bbai_csv_row(['Metric', 'Value']));
	        $this->bbai_output_contents($this->bbai_csv_row(['Total Images', $this->bbai_csv_safe_cell($total_images)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Images with Alt Text', $this->bbai_csv_safe_cell($with_alt_count)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Images Missing Alt Text', $this->bbai_csv_safe_cell($missing_count)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Coverage Percentage', $this->bbai_csv_safe_cell($coverage_percent . '%')]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Alt Texts Generated', $this->bbai_csv_safe_cell($alt_texts_generated)]));
	        $this->bbai_output_contents($this->bbai_csv_row(['Export Date', $this->bbai_csv_safe_cell(current_time('Y-m-d H:i:s'))]));
	        exit;
	    }

    /**
     * Extract ALT text from a backend response, supporting nested payloads.
     *
     * @param mixed       $api_response Response data array.
     * @param string|null $source       Output: where the alt text was found.
     * @return string
     */
    private function extract_alt_text_from_response($api_response, &$source = null): string {
        if (!is_array($api_response)) {
            return '';
        }

        $alt_text = $this->extract_alt_text_from_array($api_response, $source);
        if ($alt_text !== '') {
            return $alt_text;
        }

        $nested_keys = ['data', 'result', 'output', 'response'];
        foreach ($nested_keys as $key) {
            if (isset($api_response[$key]) && is_array($api_response[$key])) {
                $nested_source = null;
                $alt_text = $this->extract_alt_text_from_array($api_response[$key], $nested_source);
                if ($alt_text !== '') {
                    $source = $key . ($nested_source ? ':' . $nested_source : '');
                    return $alt_text;
                }
            }
        }

        // OpenAI-style payloads (if backend returns raw response)
        if (isset($api_response['choices'][0]['message']['content']) && is_string($api_response['choices'][0]['message']['content'])) {
            $source = 'choices.message.content';
            return $this->normalize_alt_text($api_response['choices'][0]['message']['content'], $source);
        }
        if (isset($api_response['choices'][0]['text']) && is_string($api_response['choices'][0]['text'])) {
            $source = 'choices.text';
            return $this->normalize_alt_text($api_response['choices'][0]['text'], $source);
        }

        return '';
    }

    /**
     * Extract ALT text from a flat associative array.
     *
     * @param array       $data   Response data.
     * @param string|null $source Output: which key matched.
     * @return string
     */
    private function extract_alt_text_from_array(array $data, &$source = null): string {
        $keys = ['altText', 'alt_text', 'alttext', 'alt', 'description', 'text'];
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $source = $key;
                return $this->normalize_alt_text($data[$key], $source);
            }
        }
        return '';
    }

    /**
     * Normalize ALT text and unwrap JSON if it contains alt text fields.
     *
     * @param string      $value  Raw text value.
     * @param string|null $source Output: updated source if JSON unwrap happens.
     * @return string
     */
    private function normalize_alt_text(string $value, &$source = null): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // If the response is JSON, attempt to pull alt text from it.
        if (isset($value[0]) && $value[0] === '{') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $nested_source = null;
                $alt_text = $this->extract_alt_text_from_array($decoded, $nested_source);
                if ($alt_text !== '') {
                    $source = 'json:' . ($source ?? 'text') . ($nested_source ? ':' . $nested_source : '');
                    return $alt_text;
                }
            }
        }

        return $value;
    }
}

// Class instantiation moved to class-bbai-admin.php bootstrap_core()
// to prevent duplicate menu registration

// Attachment edit screen behaviour handled via enqueued scripts; inline scripts removed for compliance.
