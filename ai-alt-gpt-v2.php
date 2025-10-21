<?php
/**
 * Plugin Name: AI Alt Text Generator
 * Description: Automatically generate high-quality, accessible alt text for images using AI. Get 10 free generations per month. Improve accessibility, SEO, and user experience effortlessly.
 * Version: 4.0.0
 * Author: Benjamin Oats
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-alt-gpt
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

// Load Phase 2 API client and usage tracker
require_once plugin_dir_path(__FILE__) . 'includes/class-api-client-v2.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-usage-tracker.php';

class AI_Alt_Text_Generator_GPT_V2 {
    const OPTION_KEY = 'ai_alt_gpt_settings';
    const NONCE_KEY  = 'ai_alt_gpt_nonce';
    const CAPABILITY = 'manage_ai_alt_text';

    private $api_client = null;

    private function user_can_manage(){
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
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
        add_action('init', [$this, 'ensure_capability']);
        
        // AJAX handlers for Phase 2 functionality
        add_action('wp_ajax_alttextai_register', [$this, 'ajax_register']);
        add_action('wp_ajax_alttextai_login', [$this, 'ajax_login']);
        add_action('wp_ajax_alttextai_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_alttextai_refresh_usage', [$this, 'ajax_refresh_usage']);
        add_action('wp_ajax_alttextai_regenerate_single', [$this, 'ajax_regenerate_single']);
        add_action('wp_ajax_alttextai_get_billing_info', [$this, 'ajax_get_billing_info']);
        add_action('wp_ajax_alttextai_create_checkout', [$this, 'ajax_create_checkout']);
        add_action('wp_ajax_alttextai_create_portal', [$this, 'ajax_create_portal']);

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('ai-alt', [$this, 'wpcli_command']);
        }
    }

    public function activate() {
        $this->ensure_capability();
    }

    public function deactivate() {
        // Clean up if needed
    }

    private function ensure_capability() {
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)) {
            $role->add_cap(self::CAPABILITY);
        }
    }

    public function add_settings_page() {
        add_menu_page(
            __('AI Alt Text Generation', 'ai-alt-gpt'),
            __('AI Alt Text Generation', 'ai-alt-gpt'),
            self::CAPABILITY,
            'ai-alt-gpt',
            [$this, 'render_dashboard'],
            'dashicons-format-image',
            30
        );
    }

    public function register_settings() {
        register_setting('ai_alt_gpt_settings', self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings($input) {
        $output = [];
        
        if (isset($input['api_url'])) {
            $output['api_url'] = esc_url_raw($input['api_url']);
        }
        
        return $output;
    }

    public function enqueue_admin($hook) {
        if (strpos($hook, 'ai-alt-gpt') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        
        // Enqueue Phase 2 assets
        wp_enqueue_style(
            'ai-alt-gpt-v2-style',
            plugin_dir_url(__FILE__) . 'assets/modern-style.css',
            [],
            '4.0.0'
        );
        
        wp_enqueue_style(
            'ai-alt-auth-modal-style',
            plugin_dir_url(__FILE__) . 'assets/auth-modal.css',
            [],
            '4.0.0'
        );
        
        wp_enqueue_script(
            'ai-alt-auth-modal',
            plugin_dir_url(__FILE__) . 'assets/auth-modal.js',
            ['jquery'],
            '4.0.0',
            true
        );
        
        wp_enqueue_script(
            'ai-alt-dashboard-v2',
            plugin_dir_url(__FILE__) . 'assets/ai-alt-dashboard.js',
            ['jquery'],
            '4.0.0',
            true
        );

        // Localize script with Phase 2 data
        wp_localize_script('ai-alt-dashboard-v2', 'alttextai_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alttextai_nonce'),
            'api_url' => $this->api_client->api_url ?? 'https://alttext-ai-backend.onrender.com',
            'is_authenticated' => $this->api_client->is_authenticated(),
            'user_data' => $this->api_client->get_user_data(),
        ]);
    }

    public function render_dashboard() {
        if (!$this->user_can_manage()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'ai-alt-gpt'));
        }

        // Check authentication status
        $is_authenticated = $this->api_client->is_authenticated();
        $user_data = $this->api_client->get_user_data();
        
        if (!$is_authenticated) {
            $this->render_auth_required_page();
            return;
        }

        // Get usage data
        $usage = $this->api_client->get_usage();
        if (is_wp_error($usage)) {
            $usage = [
                'used' => 0,
                'limit' => 10,
                'remaining' => 10,
                'plan' => 'free',
                'credits' => 0,
                'resetDate' => date('Y-m-d', strtotime('+1 month'))
            ];
        }

        // Get billing info
        $billing = $this->api_client->get_billing_info();
        if (is_wp_error($billing)) {
            $billing = null;
        }

        // Get stats
        $stats = $this->get_stats();

        ?>
        <div class="wrap">
            <div class="alttextai-clean-dashboard">
                <div class="alttextai-dashboard-header">
                    <h1 class="alttextai-dashboard-title">AI Alt Text Generation</h1>
                    <div class="alttextai-user-info">
                        <span class="alttextai-user-email"><?php echo esc_html($user_data['email'] ?? ''); ?></span>
                        <span class="alttextai-user-plan"><?php echo esc_html(ucfirst($user_data['plan'] ?? 'free')); ?></span>
                        <button type="button" class="alttextai-logout-btn" onclick="alttextaiLogout()">Logout</button>
                    </div>
                </div>

                <div class="alttextai-usage-section">
                    <div class="alttextai-usage-stats">
                        <div class="alttextai-usage-stat">
                            <span class="alttextai-usage-number"><?php echo esc_html($usage['used']); ?></span>
                            <span class="alttextai-usage-label">Alt text added</span>
                        </div>
                        <div class="alttextai-usage-stat">
                            <span class="alttextai-usage-number"><?php echo esc_html($usage['remaining']); ?></span>
                            <span class="alttextai-usage-label">Left to do</span>
                        </div>
                    </div>
                    
                    <div class="alttextai-usage-bar">
                        <div class="alttextai-usage-bar-bg">
                            <div class="alttextai-usage-bar-fill" 
                                 style="width: <?php echo esc_attr(min(100, ($usage['used'] / max(1, $usage['limit'])) * 100)); ?>%"
                                 data-usage-bar></div>
                        </div>
                        <div class="alttextai-usage-text">
                            <?php echo esc_html($usage['used']); ?> of <?php echo esc_html($usage['limit']); ?> used
                        </div>
                    </div>
                </div>

                <?php if ($usage['remaining'] <= 0): ?>
                <div class="alttextai-limit-reached">
                    <div class="alttextai-limit-badge">LIMIT REACHED</div>
                    <p class="alttextai-limit-message">
                        You've reached your free limit — upgrade for more AI power!
                    </p>
                    <div class="alttextai-upgrade-options">
                        <button type="button" class="alttextai-upgrade-btn" onclick="showUpgradeModal()">
                            Upgrade Plan
                        </button>
                        <button type="button" class="alttextai-credits-btn" onclick="showCreditsModal()">
                            Buy 100 Credits - £9.99
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="alttextai-bulk-section">
                    <button type="button" class="alttextai-bulk-btn" onclick="startBulkOptimization()">
                        Bulk Optimize Missing Alt Text
                    </button>
                </div>
                <?php endif; ?>

                <div class="alttextai-library-section">
                    <h2 class="alttextai-library-title">
                        ALT Library (<?php echo esc_html($stats['with_alt']); ?> optimized images)
                    </h2>
                    <div class="alttextai-library-table">
                        <?php $this->render_alt_library_table(); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade Modal -->
        <div id="alttext-upgrade-modal" class="alttext-modal" style="display: none;">
            <div class="alttext-modal-overlay">
                <div class="alttext-modal-content">
                    <div class="alttext-modal-header">
                        <h3>Upgrade Your Plan</h3>
                        <button type="button" class="alttext-modal-close">&times;</button>
                    </div>
                    <div class="alttext-modal-body">
                        <div class="alttext-plans">
                            <div class="alttext-plan">
                                <h4>Pro Plan</h4>
                                <div class="alttext-plan-price">£12.99/month</div>
                                <ul>
                                    <li>1000 AI-generated alt texts</li>
                                    <li>Advanced quality scoring</li>
                                    <li>Bulk processing</li>
                                    <li>Priority support</li>
                                </ul>
                                <button type="button" class="alttext-plan-btn" onclick="createCheckout('pro')">
                                    Choose Pro
                                </button>
                            </div>
                            <div class="alttext-plan">
                                <h4>Agency Plan</h4>
                                <div class="alttext-plan-price">£49.99/month</div>
                                <ul>
                                    <li>10000 AI-generated alt texts</li>
                                    <li>Advanced quality scoring</li>
                                    <li>Bulk processing</li>
                                    <li>Priority support</li>
                                    <li>White-label options</li>
                                </ul>
                                <button type="button" class="alttext-plan-btn" onclick="createCheckout('agency')">
                                    Choose Agency
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credits Modal -->
        <div id="alttext-credits-modal" class="alttext-modal" style="display: none;">
            <div class="alttext-modal-overlay">
                <div class="alttext-modal-content">
                    <div class="alttext-modal-header">
                        <h3>Buy Credits</h3>
                        <button type="button" class="alttext-modal-close">&times;</button>
                    </div>
                    <div class="alttext-modal-body">
                        <div class="alttext-credits-info">
                            <h4>100 AI-Generated Alt Texts</h4>
                            <div class="alttext-credits-price">£9.99 (one-time)</div>
                            <p>Credits never expire and can be used alongside any plan.</p>
                            <button type="button" class="alttext-credits-btn" onclick="createCheckout('credits')">
                                Buy 100 Credits
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Phase 2 JavaScript functions
        function alttextaiLogout() {
            if (confirm('Are you sure you want to logout?')) {
                jQuery.post(ajaxurl, {
                    action: 'alttextai_logout',
                    nonce: '<?php echo wp_create_nonce('alttextai_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }

        function showUpgradeModal() {
            document.getElementById('alttext-upgrade-modal').style.display = 'block';
        }

        function showCreditsModal() {
            document.getElementById('alttext-credits-modal').style.display = 'block';
        }

        function createCheckout(type) {
            const priceIds = {
                'pro': '<?php echo get_option('alttextai_stripe_price_pro', ''); ?>',
                'agency': '<?php echo get_option('alttextai_stripe_price_agency', ''); ?>',
                'credits': '<?php echo get_option('alttextai_stripe_price_credits', ''); ?>'
            };

            jQuery.post(ajaxurl, {
                action: 'alttextai_create_checkout',
                nonce: '<?php echo wp_create_nonce('alttextai_nonce'); ?>',
                price_id: priceIds[type],
                success_url: window.location.href,
                cancel_url: window.location.href
            }, function(response) {
                if (response.success) {
                    window.location.href = response.data.url;
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }

        function startBulkOptimization() {
            // Implementation for bulk optimization
            alert('Bulk optimization feature coming soon!');
        }
        </script>
        <?php
    }

    private function render_auth_required_page() {
        ?>
        <div class="wrap">
            <div class="alttextai-auth-required">
                <h1>AI Alt Text Generation</h1>
                <div class="alttextai-auth-card">
                    <h2>Account Required</h2>
                    <p>To use AI Alt Text Generation, you need to create a free account.</p>
                    <div class="alttextai-auth-actions">
                        <button type="button" class="alttextai-auth-btn" onclick="showAuthModal()">
                            Create Account or Sign In
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function showAuthModal() {
            if (window.AltTextAuthModal) {
                window.AltTextAuthModal.show();
            }
        }
        </script>
        <?php
    }

    private function render_alt_library_table() {
        // Get recent images with alt text
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 20,
            'meta_query' => [
                [
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '!='
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $images = get_posts($args);

        if (empty($images)) {
            echo '<p>No optimized images found.</p>';
            return;
        }

        echo '<table class="alttextai-table">';
        echo '<thead><tr><th>Image</th><th>Alt Text</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($images as $image) {
            $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
            $image_url = wp_get_attachment_thumb_url($image->ID);
            
            echo '<tr>';
            echo '<td><img src="' . esc_url($image_url) . '" style="width: 50px; height: 50px; object-fit: cover;"></td>';
            echo '<td>' . esc_html($alt_text) . '</td>';
            echo '<td>';
            echo '<button type="button" class="alttextai-regenerate-btn" data-image-id="' . $image->ID . '">Regenerate</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private function get_stats() {
        global $wpdb;
        
        $total_images = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
        ");
        
        $with_alt = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_wp_attachment_image_alt'
            AND pm.meta_value != ''
        ");
        
        return [
            'total' => (int) $total_images,
            'with_alt' => (int) $with_alt,
            'without_alt' => (int) $total_images - (int) $with_alt
        ];
    }

    // AJAX handlers
    public function ajax_register() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        
        $result = $this->api_client->register($email, $password);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    public function ajax_login() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        
        $result = $this->api_client->login($email, $password);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    public function ajax_logout() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $this->api_client->clear_token();
        wp_send_json_success();
    }

    public function ajax_refresh_usage() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $usage = $this->api_client->get_usage();
        
        if (is_wp_error($usage)) {
            wp_send_json_error($usage->get_error_message());
        } else {
            wp_send_json_success($usage);
        }
    }

    public function ajax_get_billing_info() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $billing = $this->api_client->get_billing_info();
        
        if (is_wp_error($billing)) {
            wp_send_json_error($billing->get_error_message());
        } else {
            wp_send_json_success($billing);
        }
    }

    public function ajax_create_checkout() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $price_id = sanitize_text_field($_POST['price_id']);
        $success_url = esc_url_raw($_POST['success_url']);
        $cancel_url = esc_url_raw($_POST['cancel_url']);
        
        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    public function ajax_create_portal() {
        check_ajax_referer('alttextai_nonce', 'nonce');
        
        $return_url = esc_url_raw($_POST['return_url']);
        
        $result = $this->api_client->create_customer_portal_session($return_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }

    // Legacy methods for compatibility
    public function handle_media_change($attachment_id) {
        // Phase 2 implementation
    }

    public function handle_attachment_updated($attachment_id, $post_after, $post_before) {
        // Phase 2 implementation
    }

    public function handle_post_save($post_id, $post, $update) {
        // Phase 2 implementation
    }

    public function handle_media_metadata_update($metadata, $attachment_id) {
        // Phase 2 implementation
    }

    public function register_bulk_action($actions) {
        return $actions;
    }

    public function handle_bulk_action($redirect_to, $doaction, $post_ids) {
        return $redirect_to;
    }

    public function row_action_link($actions, $post) {
        return $actions;
    }

    public function attachment_fields_to_edit($form_fields, $post) {
        return $form_fields;
    }

    public function register_rest_routes() {
        // Phase 2 REST API routes
    }

    public function ajax_regenerate_single() {
        // Phase 2 implementation
    }

    public function wpcli_command($args, $assoc_args) {
        // Phase 2 CLI implementation
    }
}

// Initialize the plugin
new AI_Alt_Text_Generator_GPT_V2();
