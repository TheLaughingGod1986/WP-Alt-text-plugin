<?php
/**
 * AJAX Authentication Trait
 * Handles user authentication AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Ajax_Auth {

    /**
     * AJAX handler: User registration
     * Prevents multiple free accounts per site
     */
    public function ajax_register() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Only administrators can connect accounts.', 'beepbeep-ai-alt-text-generator')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'beepbeep-ai-alt-text-generator')]);
        }

        $existing_token = $this->api_client->get_token();
        if (!empty($existing_token)) {
            $usage = $this->api_client->get_usage();
            if (!is_wp_error($usage) && isset($usage['plan']) && $usage['plan'] === 'free') {
                wp_send_json_error([
                    'message' => __('This site is already linked to a free account. Ask an administrator to upgrade to Pro or Agency for higher limits.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'free_plan_exists'
                ]);
            }
        }

        $result = $this->api_client->register($email, $password);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();

            if ($error_code === 'free_plan_exists' || (is_string($error_message) && strpos(strtolower($error_message), 'free plan') !== false)) {
                wp_send_json_error([
                    'message' => __('A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'free_plan_exists'
                ]);
            }

            wp_send_json_error(['message' => $error_message]);
        }

        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
        \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

        wp_send_json_success([
            'message' => __('Account created successfully', 'beepbeep-ai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User login
     */
    public function ajax_login() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'beepbeep-ai-alt-text-generator')]);
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'beepbeep-ai-alt-text-generator'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     */
    public function ajax_logout() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $this->api_client->clear_token();
        $this->api_client->clear_license_key();

        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');

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
     * Handle logout via form submission
     */
    public function handle_logout() {
        if (!isset($_POST['bbai_logout_nonce']) || !wp_verify_nonce($_POST['bbai_logout_nonce'], 'bbai_logout_action')) {
            wp_die(__('Security check failed', 'beepbeep-ai-alt-text-generator'));
        }

        if (!$this->user_can_manage()) {
            wp_die(__('Unauthorized', 'beepbeep-ai-alt-text-generator'));
        }

        $this->api_client->clear_token();
        $this->api_client->clear_license_key();

        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');

        wp_safe_redirect(add_query_arg('nocache', time(), admin_url('admin.php?page=bbai')));
        exit;
    }

    /**
     * AJAX handler: Disconnect account
     */
    public function ajax_disconnect_account() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $this->api_client->clear_token();
        $this->api_client->clear_license_key();

        delete_option('opptibbai_user_data');
        delete_option('opptibbai_site_id');

        Usage_Tracker::clear_cache();
        delete_transient('bbai_usage_cache');
        delete_transient('opptibbai_usage_cache');
        delete_transient('opptibbai_token_last_check');

        wp_send_json_success([
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'beepbeep-ai-alt-text-generator'),
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'beepbeep-ai-alt-text-generator'),
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
     * AJAX handler: Forgot password
     */
    public function ajax_forgot_password() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';

        if (empty($email)) {
            wp_send_json_error(['message' => __('Email is required', 'beepbeep-ai-alt-text-generator')]);
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Password reset instructions sent to your email', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Reset password
     */
    public function ajax_reset_password() {
        check_ajax_referer('beepbeepai_nonce', 'nonce');
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
        }

        $token_raw = isset($_POST['token']) ? wp_unslash($_POST['token']) : '';
        $token = is_string($token_raw) ? sanitize_text_field($token_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($token) || empty($password)) {
            wp_send_json_error(['message' => __('Token and password are required', 'beepbeep-ai-alt-text-generator')]);
        }

        $result = $this->api_client->reset_password($token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Password reset successfully', 'beepbeep-ai-alt-text-generator')
        ]);
    }
}
