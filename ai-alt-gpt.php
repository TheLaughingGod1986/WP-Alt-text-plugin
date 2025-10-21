<?php
/**
 * Plugin Name: AI Alt Text Generator
 * Description: Automatically generate high-quality, accessible alt text for images using AI. Get 10 free generations per month. Improve accessibility, SEO, and user experience effortlessly.
 * Version: 3.1.0
 * Author: Benjamin Oats
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-alt-gpt
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

// Load API client and usage tracker
require_once plugin_dir_path(__FILE__) . 'includes/class-api-client-v2.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-usage-tracker.php';

class AI_Alt_Text_Generator_GPT {
    const OPTION_KEY = 'ai_alt_gpt_settings';
    const NONCE_KEY  = 'ai_alt_gpt_nonce';
    const CAPABILITY = 'manage_ai_alt_text';

    private const QA_MAX_RETRY = 1;
    private const QA_RETRY_THRESHOLD = 70;

    private $stats_cache = null;
    private $token_notice = null;
    private $api_client = null;

    private function user_can_manage(){
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    /**
     * REST helper: enforce media management permissions.
     *
     * @return bool|\WP_Error
     */
    public function rest_permission_check() {
        if ($this->user_can_manage() || current_user_can('upload_files')) {
            return true;
        }
        return new \WP_Error(
            'rest_forbidden',
            __('You do not have permission to manage AltText AI resources.', 'ai-alt-gpt'),
            ['status' => 403]
        );
    }

    /**
     * Ensure REST errors always include an HTTP status.
     *
     * @param \WP_Error $error
     * @param int       $default_status
     * @return \WP_Error
     */
    private function with_rest_status(\WP_Error $error, int $default_status = 400): \WP_Error {
        $data = $error->get_error_data();
        if (!is_array($data)) {
            $error->add_data(['status' => $default_status]);
            return $error;
        }
        if (!array_key_exists('status', $data)) {
            $data['status'] = $default_status;
            $error->add_data($data);
        }
        return $error;
    }

    /**
     * Validate an attachment ID for REST/AJAX use.
     *
     * @param mixed $raw_id
     * @return int|\WP_Error
     */
    private function validate_managed_attachment($raw_id) {
        $id = intval($raw_id);
        if ($id <= 0) {
            return new \WP_Error('invalid_attachment', __('Invalid attachment ID.', 'ai-alt-gpt'), ['status' => 400]);
        }

        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') {
            return new \WP_Error('invalid_attachment', __('Attachment not found.', 'ai-alt-gpt'), ['status' => 404]);
        }

        if (!current_user_can('edit_post', $id)) {
            return new \WP_Error('forbidden_attachment', __('You are not allowed to edit this attachment.', 'ai-alt-gpt'), ['status' => 403]);
        }

        return $id;
    }

    public function __construct() {
        $this->api_client = new AltText_AI_API_Client_V2();
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_attachment', [$this, 'handle_media_change'], 5);
        add_action('delete_attachment', [$this, 'handle_media_change'], 5);
        add_action('attachment_updated', [$this, 'handle_attachment_updated'], 5, 3);
        add_action('save_post', [$this, 'handle_post_save'], 5, 3);
        add_filter('wp_update_attachment_metadata', [$this, 'handle_media_metadata_update'], 5, 2);

        add_filter('bulk_actions-upload', [$this, 'register_bulk_action']);
        add_filter('handle_bulk_actions-upload', [$this, 'handle_bulk_action'], 10, 3);

        add_filter('media_row_actions', [$this, 'row_action_link'], 10, 2);
        add_filter('attachment_fields_to_edit', [$this, 'attachment_fields_to_edit'], 15, 2);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('admin_init', [$this, 'maybe_display_threshold_notice']);
        add_action('admin_post_ai_alt_usage_export', [$this, 'handle_usage_export']);
        add_action('init', [$this, 'ensure_capability']);
        
        // AJAX handlers for upgrade functionality
        add_action('wp_ajax_alttextai_dismiss_upgrade', [$this, 'ajax_dismiss_upgrade']);
        add_action('wp_ajax_alttextai_refresh_usage', [$this, 'ajax_refresh_usage']);
        add_action('wp_ajax_alttextai_regenerate_single', [$this, 'ajax_regenerate_single']);
        
        // Authentication AJAX handlers
        add_action('wp_ajax_alttextai_register', [$this, 'ajax_register']);
        add_action('wp_ajax_alttextai_login', [$this, 'ajax_login']);
        add_action('wp_ajax_alttextai_verify_token', [$this, 'ajax_verify_token']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('ai-alt', [$this, 'wpcli_command']);
        }
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

    public function deactivate(){
    }

    public function activate() {
        global $wpdb;
        
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
        update_option(self::OPTION_KEY, wp_parse_args($existing, $defaults), false);

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
        $tabs = [
            'dashboard' => __('Dashboard', 'ai-alt-gpt'),
            'guide'     => __('How to', 'ai-alt-gpt'),
            'settings'  => __('Settings', 'ai-alt-gpt'),
        ];
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=ai_alt_usage_export'), 'ai_alt_usage_export');
        $audit_rows = $stats['audit'] ?? [];
        ?>
        <div class="wrap ai-alt-wrap alttextai-modern">
            <!-- Dark Header -->
            <div class="alttextai-header">
                <div class="alttextai-header-content">
                    <div class="alttextai-logo">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="8" fill="#10B981"/>
                            <path d="M12 10L16 22L20 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M13 18H19" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span class="alttextai-logo-text">AltText AI</span>
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
            <div class="alttextai-clean-dashboard" data-stats='<?php echo esc_attr(wp_json_encode($stats)); ?>'>
                <?php
                // Get usage stats
                $usage_stats = AltText_AI_Usage_Tracker::get_stats_display();
                
                // Force a live refresh when loading the dashboard to avoid stale 0/10
                if (isset($this->api_client)) { 
                    $live_usage = $this->api_client->get_usage();
                    if (is_array($live_usage) && !empty($live_usage)) { AltText_AI_Usage_Tracker::update_usage($live_usage); $usage_stats = AltText_AI_Usage_Tracker::get_stats_display(); }
                }
                
                // Calculate percentage properly
                $used = max(0, intval($usage_stats['used']));
                $limit = max(1, intval($usage_stats['limit']));
                $percentage = min(100, round(($used / $limit) * 100));
                
                // Update the stats with calculated values
                $usage_stats['used'] = $used;
                $usage_stats['limit'] = $limit;
                $usage_stats['percentage'] = $percentage;
                $usage_stats['remaining'] = max(0, $limit - $used);
                ?>
                
                <!-- Clean Dashboard Design -->
                <div class="alttextai-dashboard-shell max-w-5xl mx-auto px-6">
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
                                <button type="button" class="alttextai-plan-upgrade" data-action="show-upgrade-modal"><?php esc_html_e('Upgrade →', 'ai-alt-gpt'); ?></button>
                            <?php else : ?>
                                <?php if (!empty($manage_billing_url)) : ?>
                                    <a href="<?php echo esc_url($manage_billing_url); ?>" target="_blank" rel="noopener noreferrer" class="alttextai-plan-manage"><?php esc_html_e('Manage Billing', 'ai-alt-gpt'); ?></a>
                                <?php else : ?>
                                    <button type="button" class="alttextai-plan-upgrade alttextai-plan-upgrade--secondary" data-action="show-upgrade-modal"><?php esc_html_e('Upgrade Options', 'ai-alt-gpt'); ?></button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Authentication Section -->
                    <div class="alttextai-auth-section" id="alttextai-auth-section" style="display: none;">
                        <div class="alttextai-auth-card bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
                            <h2 class="text-xl font-semibold text-slate-800 mb-4"><?php esc_html_e('Account Setup', 'ai-alt-gpt'); ?></h2>
                            <p class="text-slate-600 mb-6"><?php esc_html_e('Create an account to track your usage and manage your subscription.', 'ai-alt-gpt'); ?></p>
                            
                            <div class="alttextai-auth-forms">
                                <!-- Registration Form -->
                                <div id="alttextai-register-form" class="alttextai-auth-form">
                                    <h3 class="text-lg font-medium text-slate-700 mb-4"><?php esc_html_e('Create Account', 'ai-alt-gpt'); ?></h3>
                                    <form id="alttextai-register" class="space-y-4">
                                        <div>
                                            <label for="register-email" class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e('Email', 'ai-alt-gpt'); ?></label>
                                            <input type="email" id="register-email" name="email" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label for="register-password" class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e('Password', 'ai-alt-gpt'); ?></label>
                                            <input type="password" id="register-password" name="password" required minlength="8" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <button type="submit" class="w-full bg-teal-600 text-white py-2 px-4 rounded-lg hover:bg-teal-700 transition-colors">
                                            <?php esc_html_e('Create Account', 'ai-alt-gpt'); ?>
                                        </button>
                                    </form>
                                </div>

                                <!-- Login Form -->
                                <div id="alttextai-login-form" class="alttextai-auth-form" style="display: none;">
                                    <h3 class="text-lg font-medium text-slate-700 mb-4"><?php esc_html_e('Sign In', 'ai-alt-gpt'); ?></h3>
                                    <form id="alttextai-login" class="space-y-4">
                                        <div>
                                            <label for="login-email" class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e('Email', 'ai-alt-gpt'); ?></label>
                                            <input type="email" id="login-email" name="email" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <div>
                                            <label for="login-password" class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e('Password', 'ai-alt-gpt'); ?></label>
                                            <input type="password" id="login-password" name="password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                                        </div>
                                        <button type="submit" class="w-full bg-teal-600 text-white py-2 px-4 rounded-lg hover:bg-teal-700 transition-colors">
                                            <?php esc_html_e('Sign In', 'ai-alt-gpt'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="alttextai-auth-switch mt-4 text-center">
                                <button type="button" id="alttextai-switch-to-login" class="text-teal-600 hover:text-teal-700 font-medium">
                                    <?php esc_html_e('Already have an account? Sign in', 'ai-alt-gpt'); ?>
                                </button>
                                <button type="button" id="alttextai-switch-to-register" class="text-teal-600 hover:text-teal-700 font-medium" style="display: none;">
                                    <?php esc_html_e('Need an account? Create one', 'ai-alt-gpt'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="alttextai-main-content">
                    <h1 class="alttextai-main-title"><?php esc_html_e('Generate perfect SEO alt text automatically', 'ai-alt-gpt'); ?></h1>
                    
                    <!-- Usage Display -->
                    <div class="alttextai-usage-display shadow-sm hover:shadow-md transition-shadow rounded-2xl bg-white p-6">
                        <div class="alttextai-usage-display__header">
                            <div class="alttextai-usage-display__text">
                                <p class="alttextai-usage-text text-slate-800 font-semibold" data-usage-text>
                                    <?php printf(esc_html__('%1$d of %2$d free generations used', 'ai-alt-gpt'), $usage_stats['used'], $usage_stats['limit']); ?>
                                </p>
                                <div class="alttextai-usage-meta text-sm text-slate-500 flex items-center gap-3">
                                    <?php if ($usage_stats['remaining'] <= 0) : ?>
                                        <span class="inline-flex items-center gap-1 alttextai-reset-info" title="<?php esc_attr_e('Your allowance refreshes at the start of each billing cycle.', 'ai-alt-gpt'); ?>">
                                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false" class="text-slate-400">
                                                <path fill="currentColor" d="M8 1.333a6.667 6.667 0 1 0 6.667 6.667A6.675 6.675 0 0 0 8 1.333Zm0 12A5.333 5.333 0 1 1 13.333 8 5.339 5.339 0 0 1 8 13.333Zm.667-8H8a.667.667 0 0 0-.667.667v3.333a.667.667 0 0 0 .667.667h2a.667.667 0 0 0 0-1.333H8.667V6a.667.667 0 0 0-.667-.667Z"/>
                                            </svg>
                                            <?php 
                                                $days_until_reset = $usage_stats['days_until_reset'] ?? 0;
                                                $reset_date = $usage_stats['reset_date'] ?? '';
                                                if ($days_until_reset > 0) {
                                                    printf(
                                                        esc_html__('Resets in %d days (%s)', 'ai-alt-gpt'),
                                                        $days_until_reset,
                                                        $reset_date
                                                    );
                                                } elseif (!empty($reset_date)) {
                                                    printf(
                                                        esc_html__('Resets %s', 'ai-alt-gpt'),
                                                        $reset_date
                                                    );
                                                }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <button type="button" class="alttextai-refresh-status inline-flex items-center gap-1 text-teal-600 font-medium" data-action="refresh-usage">
                                        <span aria-hidden="true">🔄</span><?php esc_html_e('Refresh status', 'ai-alt-gpt'); ?>
                                    </button>
                                </div>
                            </div>
                            <p class="alttextai-usage-alert text-sm text-emerald-600 font-medium">
                                <?php if ($usage_stats['remaining'] <= 0) : ?>
                                    <?php esc_html_e('🎯 You’ve reached your free limit — upgrade for more AI power!', 'ai-alt-gpt'); ?>
                                <?php else : ?>
                                    <?php printf(esc_html__('%d credits remain this cycle. Keep the momentum going!', 'ai-alt-gpt'), $usage_stats['remaining']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="alttextai-progress-bar-container">
                            <div class="alttextai-progress-bar-fill alttextai-progress-bar-fill--animated bg-gradient-to-r from-teal-400 to-lime-400" data-usage-bar style="width: <?php echo esc_attr($usage_stats['percentage']); ?>%"></div>
                        </div>
                        <?php
                            $minutes_saved = max(0, intval($stats['with_alt'] ?? 0) * 2);
                            $hours_saved = $minutes_saved / 60;
                            $hours_precision = $hours_saved >= 10 ? 0 : 1;
                            $savings_value = number_format_i18n($hours_saved, $hours_precision);
                        ?>
                        <p class="alttextai-savings text-sm text-slate-500 mt-3" data-savings-copy>
                            <?php printf(
                                esc_html__('You’ve saved %s hours of manual work! (est. 2 min/image)', 'ai-alt-gpt'),
                                esc_html($savings_value)
                            ); ?>
                        </p>
                    </div>

                    <!-- Optimization Progress -->
                    <div class="alttextai-optimization-progress shadow-sm hover:shadow-md transition-shadow rounded-2xl bg-white p-6 mt-8">
                        <div class="alttextai-optimization-stats">
                            <div class="alttextai-optimization-item">
                                <span class="alttextai-optimization-number"><?php echo esc_html($stats['with_alt'] ?? 0); ?></span>
                                <span class="alttextai-optimization-label"><?php esc_html_e('Optimized', 'ai-alt-gpt'); ?></span>
                            </div>
                            <div class="alttextai-optimization-separator">/</div>
                            <div class="alttextai-optimization-item">
                                <span class="alttextai-optimization-number"><?php echo esc_html(($stats['with_alt'] ?? 0) + ($stats['missing'] ?? 0)); ?></span>
                                <span class="alttextai-optimization-label"><?php esc_html_e('Total Images', 'ai-alt-gpt'); ?></span>
                            </div>
                        </div>
                        <div class="alttextai-optimization-bar">
                            <div class="alttextai-optimization-bar-fill bg-gradient-to-r from-teal-400 to-lime-400 transition-all" style="width: <?php echo esc_attr($stats['coverage'] ?? 0); ?>%"></div>
                        </div>
                        <div class="alttextai-optimization-text">
                            <?php 
                            $total_images = ($stats['with_alt'] ?? 0) + ($stats['missing'] ?? 0);
                            $optimized = $stats['with_alt'] ?? 0;
                            $remaining = $stats['missing'] ?? 0;
                            
                            if ($total_images > 0) {
                                if ($remaining > 0) {
                                    printf(
                                        esc_html__('%1$d of %2$d images optimized (%3$d remaining)', 'ai-alt-gpt'),
                                        $optimized,
                                        $total_images,
                                        $remaining
                                    );
                                } else {
                                    printf(
                                        esc_html__('All %1$d images optimized! 🎉', 'ai-alt-gpt'),
                                        $total_images
                                    );
                                }
                            } else {
                                esc_html_e('No images found. Upload some images to get started!', 'ai-alt-gpt');
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Main CTA Button -->
                    <div class="alttextai-main-cta mt-8">
                        <?php 
                        $remaining_images = $stats['missing'] ?? 0;
                        $button_text = $remaining_images > 0 
                            ? sprintf(esc_html__('Optimize %d Remaining Images', 'ai-alt-gpt'), $remaining_images)
                            : esc_html__('All Images Optimized!', 'ai-alt-gpt');
                        $waiting_text = $remaining_images > 0
                            ? sprintf(esc_html__('Upgrade to Unlock (%d images waiting) →', 'ai-alt-gpt'), $remaining_images)
                            : esc_html__('Upgrade to Unlock →', 'ai-alt-gpt');
                        ?>
                        
                        <?php if ($usage_stats['remaining'] <= 0) : ?>
                            <button type="button" class="alttextai-bulk-btn alttextai-bulk-btn--limit" data-action="show-upgrade-modal">
                                <?php echo esc_html($waiting_text); ?>
                            </button>
                        <?php else : ?>
                            <button type="button" class="alttextai-bulk-btn" data-action="generate-missing" <?php echo $remaining_images <= 0 ? 'disabled' : ''; ?>>
                                <?php echo esc_html($button_text); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="alttextai-post-optimize-banner hidden" data-post-optimize-banner>
                        <div class="alttextai-post-optimize-banner__content">
                            <p><?php esc_html_e('🎉 All your images are SEO-ready! Unlock keyword optimization with AltText AI Pro.', 'ai-alt-gpt'); ?></p>
                            <button type="button" class="alttextai-post-optimize-btn" data-action="show-upgrade-modal"><?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?></button>
                        </div>
                    </div>

                        <!-- Progress Bar (Hidden by default) -->
                        <div class="ai-alt-bulk-progress" data-bulk-progress hidden>
                            <div class="ai-alt-bulk-progress__header">
                                <span class="ai-alt-bulk-progress__label" data-bulk-progress-label><?php esc_html_e('Preparing bulk run…', 'ai-alt-gpt'); ?></span>
                                <span class="ai-alt-bulk-progress__counts" data-bulk-progress-counts></span>
                            </div>
                            <div class="ai-alt-bulk-progress__bar"><span data-bulk-progress-bar></span></div>
                        </div>

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
                                <button type="button" class="alttextai-pricing-btn alttextai-pricing-btn--primary" data-action="show-upgrade-modal">
                                    <?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                
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
                                            <button type="button" class="alttextai-btn-regenerate" data-action="regenerate-single" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
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
                </div>
                
                <!-- Upgrade Footer -->
                <div class="alttextai-footer">
                    <a href="<?php echo esc_url(AltText_AI_Usage_Tracker::get_upgrade_url()); ?>" class="alttextai-upgrade-link show-upgrade-modal">
                        <?php esc_html_e('Upgrade to Pro for unlimited AI generations', 'ai-alt-gpt'); ?> →
                    </a>
                </div>
                
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
            <div class="ai-alt-panel" style="background:#fff;border:1px solid #eef2f7;border-radius:12px;padding:24px;">
                <h2><?php esc_html_e('How to use AltText AI', 'ai-alt-gpt'); ?></h2>
                <div style="max-width: 600px;">
                    <h3><?php esc_html_e('Getting Started', 'ai-alt-gpt'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Upload images to your Media Library as usual', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Use the "Bulk Optimize Missing Alt Text" button to generate alt text for all images at once', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Review and edit generated alt text in the ALT Library table below', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Use the "Regenerate" button for any image to create new alt text', 'ai-alt-gpt'); ?></li>
                        </ol>
                    
                    <h3><?php esc_html_e('Tips for Better Alt Text', 'ai-alt-gpt'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Be descriptive but concise (aim for 5-15 words)', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Include important visual details and context', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Avoid phrases like "image of" or "picture of"', 'ai-alt-gpt'); ?></li>
                        <li><?php esc_html_e('Consider the purpose of the image in your content', 'ai-alt-gpt'); ?></li>
                    </ul>
                    
                    <h3><?php esc_html_e('Usage Limits', 'ai-alt-gpt'); ?></h3>
                    <p><?php esc_html_e('Free users get 100 AI generations per month. Upgrade to Pro for unlimited generations and advanced features.', 'ai-alt-gpt'); ?></p>
                </div>
                </div>
            <?php elseif ($tab === 'settings') : ?>
            <div class="ai-alt-panel" style="background:#fff;border:1px solid #eef2f7;border-radius:12px;padding:24px;max-width:820px;">
                <h2><?php esc_html_e('Settings', 'ai-alt-gpt'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('ai_alt_gpt_group'); ?>
                    <?php $o = wp_parse_args($opts, []); ?>
            <?php
                        // Pull fresh usage from backend to avoid stale cache in Settings
                        if (isset($this->api_client)) {
                            $live = $this->api_client->get_usage();
                            if (is_array($live) && !empty($live)) { AltText_AI_Usage_Tracker::update_usage($live); }
                        }
                        $usage_box = AltText_AI_Usage_Tracker::get_stats_display(); 
                    ?>
                    <div style="margin:16px 0 24px; padding:12px 16px; border:1px solid #e5e7eb; border-radius:10px; background:#f9fafb; display:flex; align-items:center; gap:12px;">
                        <span style="display:inline-block; width:10px; height:10px; border-radius:999px; background:<?php echo $usage_box['plan']==='pro' ? '#10b981' : '#f59e0b'; ?>"></span>
                        <strong><?php esc_html_e('Plan', 'ai-alt-gpt'); ?>:</strong>
                        <span><?php echo esc_html($usage_box['plan_label']); ?></span>
                        <span style="color:#64748b; margin-left:auto;">
                            <?php printf(esc_html__('%1$d of %2$d used · resets %3$s', 'ai-alt-gpt'), $usage_box['used'], $usage_box['limit'], $usage_box['reset_date']); ?>
                        </span>
                        <?php if ($usage_box['plan'] !== 'pro') : ?>
                            <a href="<?php echo esc_url(AltText_AI_Usage_Tracker::get_upgrade_url()); ?>" target="_blank" class="button button-primary" style="margin-left:12px;">
                                <?php esc_html_e('Upgrade to Pro', 'ai-alt-gpt'); ?>
                            </a>
                <?php endif; ?>
                    </div>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Generate on upload', 'ai-alt-gpt'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_on_upload]" value="1" <?php checked(!isset($o['enable_on_upload']) || !empty($o['enable_on_upload'])); ?> />
                                    <?php esc_html_e('Automatically generate alt text when images are uploaded', 'ai-alt-gpt'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('If monthly credits are exhausted, generation is skipped and an upgrade prompt is shown.', 'ai-alt-gpt'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Tone / style', 'ai-alt-gpt'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[tone]" value="<?php echo esc_attr($o['tone'] ?? 'professional, accessible'); ?>" />
                                <p class="description"><?php esc_html_e('Describe the overall voice (e.g., "professional, accessible" or "friendly, concise").', 'ai-alt-gpt'); ?></p>
                                    </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Additional instructions', 'ai-alt-gpt'); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_prompt]" rows="4" class="large-text code"><?php echo esc_textarea($o['custom_prompt'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('Optional. This text is prepended to every request. Useful for accessibility or brand rules.', 'ai-alt-gpt'); ?></p>
                                    </td>
                                </tr>
                </table>
                    <?php submit_button(); ?>
                </form>
                    </div>
                <?php endif; ?>
            
                                <?php
            // Include upgrade modal on all tabs
            include plugin_dir_path(__FILE__) . 'templates/upgrade-modal.php';
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

        // Check transient cache (5 minute TTL for DB queries)
        $transient_key = 'ai_alt_stats_v3';
        $cached = get_transient($transient_key);
        if (false !== $cached && is_array($cached)){
            // Also populate object cache for next request
            wp_cache_set($cache_key, $cached, $cache_group, 5 * MINUTE_IN_SECONDS);
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
            $usage['last_request_formatted'] = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $usage['last_request']);
        }

        $latest_generated_raw = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ai_alt_generated_at' ORDER BY meta_value DESC LIMIT 1"
        );
        $latest_generated = $latest_generated_raw ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $latest_generated_raw) : '';

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

        // Cache for 5 minutes (300 seconds)
        wp_cache_set($cache_key, $this->stats_cache, $cache_group, 5 * MINUTE_IN_SECONDS);
        set_transient($transient_key, $this->stats_cache, 5 * MINUTE_IN_SECONDS);

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
            return [];
        }

        return array_map(function($row){
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

        $message = strtolower($error->get_error_message());
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

        $quoted_alt = str_replace('"', '\"', $alt);

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
        
        // Check if at usage limit
        if ($this->api_client->has_reached_limit()) {
            return new \WP_Error('limit_reached', __('Monthly generation limit reached. Please upgrade to continue.', 'ai-alt-gpt'));
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
        
        // Call proxy API to generate alt text
        $api_response = $this->api_client->generate_alt_text($attachment_id, $context);
        
        if (is_wp_error($api_response)) {
            return $api_response;
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

    public function register_bulk_action($bulk_actions){
        $bulk_actions['ai_alt_generate'] = __('Generate Alt Text (AI)', 'ai-alt-gpt');
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids){
        if ($doaction !== 'ai_alt_generate') return $redirect_to;
        $count = 0; $errors = 0;
        foreach ($post_ids as $id){
            $res = $this->generate_and_save($id, 'bulk');
            if (is_wp_error($res)) {
                if ($res->get_error_code() === 'ai_alt_dry_run') { $count++; }
                else { $errors++; }
            } else { $count++; }
        }
        $redirect_to = add_query_arg(['ai_alt_generated' => $count, 'ai_alt_errors' => $errors], $redirect_to);
        return $redirect_to;
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
        $button = sprintf(
            '<button type="button" class="button ai-alt-generate" data-id="%1$d" data-has-alt="%2$d" data-label-generate="%3$s" data-label-regenerate="%4$s">%5$s</button>',
            intval($post->ID),
            $has_alt ? 1 : 0,
            esc_attr($label_generate),
            esc_attr($label_regenerate),
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
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_generate_alt'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('ai-alt/v1', '/alt/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'rest_save_alt'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args'                => [
                'id'  => [
                    'sanitize_callback' => 'absint',
                ],
                'alt' => [
                    'sanitize_callback' => 'wp_kses_post',
                ],
            ],
        ]);

        register_rest_route('ai-alt/v1', '/list', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_list_images'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);

        register_rest_route('ai-alt/v1', '/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_get_stats'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);

        register_rest_route('ai-alt/v1', '/usage', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_get_usage'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
    }

    public function rest_generate_alt(\WP_REST_Request $request){
        $id = $this->validate_managed_attachment($request->get_param('id'));
        if (is_wp_error($id)) {
            return $this->with_rest_status($id, $id->get_error_data()['status'] ?? 400);
        }

        $result = $this->generate_and_save($id, 'rest');
        if (is_wp_error($result)) {
            if ($result->get_error_code() === 'ai_alt_dry_run') {
                $data = $result->get_error_data();
                return rest_ensure_response([
                    'id'      => $id,
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                    'prompt'  => is_array($data) && isset($data['prompt']) ? $data['prompt'] : '',
                    'stats'   => $this->get_media_stats(),
                ]);
            }
            return $this->with_rest_status($result, 500);
        }

        return rest_ensure_response([
            'id'    => $id,
            'alt'   => $result,
            'meta'  => $this->prepare_attachment_snapshot($id),
            'stats' => $this->get_media_stats(),
        ]);
    }

    public function rest_save_alt(\WP_REST_Request $request){
        $id = $this->validate_managed_attachment($request->get_param('id'));
        if (is_wp_error($id)) {
            return $this->with_rest_status($id, $id->get_error_data()['status'] ?? 400);
        }

        $raw_alt = (string) $request->get_param('alt');
        $alt = trim(wp_strip_all_tags($raw_alt));

        if ($alt === '') {
            return new \WP_Error('invalid_alt', __('ALT text cannot be empty.', 'ai-alt-gpt'), ['status' => 400]);
        }

        $usage = [
            'prompt'     => 0,
            'completion' => 0,
            'total'      => 0,
        ];

        $post      = get_post($id);
        $file_path = get_attached_file($id);
        $context   = [
            'filename'   => $file_path ? basename($file_path) : '',
            'title'      => get_the_title($id),
            'caption'    => $post && isset($post->post_excerpt) ? $post->post_excerpt : '',
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
            $review_response = $this->api_client->review_alt_text($id, $alt, $context);
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
                    'prompt'     => intval($review['usage']['prompt_tokens'] ?? 0),
                    'completion' => intval($review['usage']['completion_tokens'] ?? 0),
                    'total'      => intval($review['usage']['total_tokens'] ?? 0),
                ];

                $review_result = [
                    'score'   => intval($review['score'] ?? 0),
                    'status'  => sanitize_key($review['status'] ?? ''),
                    'grade'   => sanitize_text_field($review['grade'] ?? ''),
                    'summary' => isset($review['summary']) ? sanitize_text_field($review['summary']) : '',
                    'issues'  => $issues,
                    'model'   => sanitize_text_field($review['model'] ?? ''),
                    'usage'   => $review_usage,
                ];
            }
        }

        $this->persist_generation_result(
            $id,
            $alt,
            $usage,
            'manual-edit',
            'manual-input',
            'manual',
            $review_result
        );

        return rest_ensure_response([
            'id'     => $id,
            'alt'    => $alt,
            'meta'   => $this->prepare_attachment_snapshot($id),
            'stats'  => $this->get_media_stats(),
            'source' => 'manual-edit',
        ]);
    }

    public function rest_list_images(\WP_REST_Request $request){
        $scope_raw = $request->get_param('scope');
        $scope     = is_string($scope_raw) ? strtolower($scope_raw) : 'missing';
        $scope     = $scope === 'all' ? 'all' : 'missing';

        $limit_raw = $request->get_param('limit');
        $limit     = is_numeric($limit_raw) ? intval($limit_raw) : 100;
        $limit     = max(1, min(500, $limit));

        if ($scope === 'missing') {
            $ids = $this->get_missing_attachment_ids($limit);
        } else {
            $ids = $this->get_all_attachment_ids($limit, 0);
        }

        return rest_ensure_response([
            'ids' => array_map('intval', $ids),
        ]);
    }

    public function rest_get_stats(\WP_REST_Request $request){
        $fresh = $request->get_param('fresh');
        if (!empty($fresh) && filter_var($fresh, FILTER_VALIDATE_BOOLEAN)) {
            $this->invalidate_stats_cache();
        }

        return rest_ensure_response($this->get_media_stats());
    }

    public function rest_get_usage() {
        $usage = $this->api_client->get_usage();
        if (is_wp_error($usage)) {
            return $this->with_rest_status($usage, 502);
        }

        if (empty($usage)) {
            return new \WP_Error('usage_unavailable', __('Usage data is currently unavailable.', 'ai-alt-gpt'), ['status' => 503]);
        }

        AltText_AI_Usage_Tracker::update_usage($usage);
        return rest_ensure_response(AltText_AI_Usage_Tracker::get_stats_display());
    }

    public function enqueue_admin($hook){
        $base_path = plugin_dir_path(__FILE__);
        $base_url  = plugin_dir_url(__FILE__);
        
        // Use minified assets in production, full versions when debugging
        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        
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
            $admin_file = "assets/ai-alt-admin{$suffix}.js";
            $admin_version = file_exists($base_path . $admin_file) ? filemtime($base_path . $admin_file) : '3.0.0';
            wp_enqueue_script('ai-alt-gpt-admin', $base_url . $admin_file, ['jquery'], $admin_version, true);
            wp_localize_script('ai-alt-gpt-admin', 'AI_ALT_GPT', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'rest'      => esc_url_raw( rest_url('ai-alt/v1/generate/') ),
                'restAlt'   => esc_url_raw( rest_url('ai-alt/v1/alt/') ),
                'restStats' => esc_url_raw( rest_url('ai-alt/v1/stats') ),
                'restUsage' => esc_url_raw( rest_url('ai-alt/v1/usage') ),
                'restMissing'=> esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('ai-alt/v1/list')) ),
                'restAll'    => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('ai-alt/v1/list')) ),
                'restRoot'  => esc_url_raw( rest_url() ),
                'l10n'      => $l10n_common,
                'upgradeUrl'=> esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl'=> esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
            ]);
        }

        if ($hook === 'media_page_ai-alt-gpt'){
            $css_file = "assets/ai-alt-dashboard{$suffix}.css";
            $js_file = "assets/ai-alt-dashboard{$suffix}.js";
            $admin_file = "assets/ai-alt-admin{$suffix}.js";
            $upgrade_css = "assets/upgrade-modal.css";
            $upgrade_js = "assets/upgrade-modal.js";
            
            $css_version = file_exists($base_path . $css_file) ? filemtime($base_path . $css_file) : '3.0.0';
            $js_version  = file_exists($base_path . $js_file) ? filemtime($base_path . $js_file) : '3.0.0';
            $admin_version = file_exists($base_path . $admin_file) ? filemtime($base_path . $admin_file) : '3.0.0';
            $upgrade_css_version = file_exists($base_path . $upgrade_css) ? filemtime($base_path . $upgrade_css) : '3.1.0';
            $upgrade_js_version = file_exists($base_path . $upgrade_js) ? filemtime($base_path . $upgrade_js) : '3.1.0';
            
            wp_enqueue_style('ai-alt-gpt-dashboard', $base_url . $css_file, [], $css_version);
            wp_enqueue_style('ai-alt-gpt-modern', $base_url . 'assets/modern-style.css', ['ai-alt-gpt-dashboard'], filemtime($base_path . 'assets/modern-style.css'));
            wp_enqueue_style('ai-alt-gpt-upgrade', $base_url . $upgrade_css, [], $upgrade_css_version);
            wp_enqueue_script('ai-alt-gpt-admin', $base_url . $admin_file, ['jquery'], $admin_version, true);
            wp_localize_script('ai-alt-gpt-admin', 'AI_ALT_GPT', [
                'nonce'     => wp_create_nonce('wp_rest'),
                'rest'      => esc_url_raw( rest_url('ai-alt/v1/generate/') ),
                'restAlt'   => esc_url_raw( rest_url('ai-alt/v1/alt/') ),
                'restStats' => esc_url_raw( rest_url('ai-alt/v1/stats') ),
                'restUsage' => esc_url_raw( rest_url('ai-alt/v1/usage') ),
                'restMissing'=> esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('ai-alt/v1/list')) ),
                'restAll'    => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('ai-alt/v1/list')) ),
                'restRoot'  => esc_url_raw( rest_url() ),
                'upgradeUrl'=> esc_url( AltText_AI_Usage_Tracker::get_upgrade_url() ),
                'billingPortalUrl'=> esc_url( AltText_AI_Usage_Tracker::get_billing_portal_url() ),
                'l10n'      => $l10n_common,
            ]);

            $stats_data = $this->get_media_stats();
            $usage_data = AltText_AI_Usage_Tracker::get_stats_display();
            
            wp_enqueue_script('ai-alt-gpt-dashboard', $base_url . $js_file, ['jquery', 'wp-api-fetch'], $js_version, true);
            wp_enqueue_script('ai-alt-gpt-upgrade', $base_url . $upgrade_js, ['jquery'], $upgrade_js_version, true);
            
            wp_localize_script('ai-alt-gpt-dashboard', 'AI_ALT_GPT_DASH', [
                'nonce'       => wp_create_nonce('wp_rest'),
                'rest'        => esc_url_raw( rest_url('ai-alt/v1/generate/') ),
                'restStats'   => esc_url_raw( rest_url('ai-alt/v1/stats') ),
                'restUsage'   => esc_url_raw( rest_url('ai-alt/v1/usage') ),
                'restMissing' => esc_url_raw( add_query_arg(['scope' => 'missing'], rest_url('ai-alt/v1/list')) ),
                'restAll'     => esc_url_raw( add_query_arg(['scope' => 'all'], rest_url('ai-alt/v1/list')) ),
                'restRoot'    => esc_url_raw( rest_url() ),
                'upgradeUrl'  => AltText_AI_Usage_Tracker::get_upgrade_url(),
                'stats'       => $stats_data,
                'initialUsage'=> $usage_data,
            ]);
            
            // Add AJAX variables for regenerate functionality
            wp_localize_script('ai-alt-gpt-dashboard', 'alttextai_ajax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('alttextai_upgrade_nonce'),
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
        
        // Generate alt text for the single image
        $result = $this->generate_alt_text_for_attachment($attachment_id);
        
        if ($result['success']) {
            // Update usage after successful generation
            $this->invalidate_stats_cache();
            AltText_AI_Usage_Tracker::clear_cache();

            wp_send_json_success([
                'message' => 'Alt text generated successfully',
                'alt_text' => $result['alt_text'],
                'attachment_id' => $attachment_id
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['error'] ?? 'Failed to generate alt text'
            ]);
        }
    }

    public function handle_media_change() {
        $this->invalidate_stats_cache();
    }

    public function handle_media_metadata_update($data, $post_id) {
        $this->invalidate_stats_cache();
        return $data;
    }

    public function handle_attachment_updated($post_id, $post_after, $post_before) {
        $this->invalidate_stats_cache();
    }

    public function handle_post_save($post_ID, $post, $update) {
        if ($post instanceof \WP_Post && $post->post_type === 'attachment') {
            $this->invalidate_stats_cache();
        }
    }

    /**
     * AJAX handler for user registration
     */
    public function ajax_register() {
        check_ajax_referer('alttextai_auth', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Email and password are required');
        }
        
        if (strlen($password) < 8) {
            wp_send_json_error('Password must be at least 8 characters');
        }

        $result = $this->api_client->register($email, $password);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'token' => $result['token'] ?? '',
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler for user login
     */
    public function ajax_login() {
        check_ajax_referer('alttextai_auth', 'nonce');
        
        $email = sanitize_email($_POST['email'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        
        if (empty($email) || empty($password)) {
            wp_send_json_error('Email and password are required');
        }

        $result = $this->api_client->login($email, $password);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'token' => $result['token'] ?? '',
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler for token verification
     */
    public function ajax_verify_token() {
        check_ajax_referer('alttextai_auth', 'nonce');
        
        $token = sanitize_text_field($_POST['token'] ?? '');
        
        if (empty($token)) {
            wp_send_json_error('Token is required');
        }

        // Set the token in the API client
        $this->api_client->set_jwt_token($token);
        
        // Try to get usage stats to verify the token
        $result = $this->api_client->get_usage_stats();
        
        if (is_wp_error($result)) {
            wp_send_json_error('Invalid token');
        }

        wp_send_json_success(['valid' => true]);
    }
}

new AI_Alt_Text_Generator_GPT();

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

        // Authentication handling
        function initAuth() {
            // Switch between login and register forms
            $('#alttextai-switch-to-login').on('click', function() {
                $('#alttextai-register-form').hide();
                $('#alttextai-login-form').show();
                $('#alttextai-switch-to-login').hide();
                $('#alttextai-switch-to-register').show();
            });

            $('#alttextai-switch-to-register').on('click', function() {
                $('#alttextai-login-form').hide();
                $('#alttextai-register-form').show();
                $('#alttextai-switch-to-register').hide();
                $('#alttextai-switch-to-login').show();
            });

            // Registration form
            $('#alttextai-register').on('submit', function(e) {
                e.preventDefault();
                const email = $('#register-email').val();
                const password = $('#register-password').val();
                
                if (!email || !password) {
                    alert('Please fill in all fields');
                    return;
                }

                // Call WordPress AJAX to register
                $.post(ajaxurl, {
                    action: 'alttextai_register',
                    email: email,
                    password: password,
                    nonce: '<?php echo wp_create_nonce('alttextai_auth'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Store JWT token
                        localStorage.setItem('alttextai_jwt_token', response.data.token);
                        // Hide auth section and show dashboard
                        $('#alttextai-auth-section').hide();
                        $('#alttextai-main-content').show();
                        // Refresh the page to load user data
                        location.reload();
                    } else {
                        alert('Registration failed: ' + (response.data || 'Unknown error'));
                    }
                });
            });

            // Login form
            $('#alttextai-login').on('submit', function(e) {
                e.preventDefault();
                const email = $('#login-email').val();
                const password = $('#login-password').val();
                
                if (!email || !password) {
                    alert('Please fill in all fields');
                    return;
                }

                // Call WordPress AJAX to login
                $.post(ajaxurl, {
                    action: 'alttextai_login',
                    email: email,
                    password: password,
                    nonce: '<?php echo wp_create_nonce('alttextai_auth'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Store JWT token
                        localStorage.setItem('alttextai_jwt_token', response.data.token);
                        // Hide auth section and show dashboard
                        $('#alttextai-auth-section').hide();
                        $('#alttextai-main-content').show();
                        // Refresh the page to load user data
                        location.reload();
                    } else {
                        alert('Login failed: ' + (response.data || 'Invalid credentials'));
                    }
                });
            });

            // Check if user is already authenticated
            const token = localStorage.getItem('alttextai_jwt_token');
            if (token) {
                // Verify token is still valid
                $.post(ajaxurl, {
                    action: 'alttextai_verify_token',
                    token: token,
                    nonce: '<?php echo wp_create_nonce('alttextai_auth'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Token is valid, hide auth section
                        $('#alttextai-auth-section').hide();
                        $('#alttextai-main-content').show();
                    } else {
                        // Token invalid, show auth section
                        $('#alttextai-auth-section').show();
                        $('#alttextai-main-content').hide();
                    }
                });
            } else {
                // No token, show auth section
                $('#alttextai-auth-section').show();
                $('#alttextai-main-content').hide();
            }
        }

        // Initialize auth when document is ready
        $(document).ready(function() {
            initAuth();
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
