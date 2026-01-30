<?php
/**
 * Core Ajax Auth Trait
 * Handles user authentication AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Ajax_Auth {

    /**
     * AJAX handler: User registration
     * Prevents multiple free accounts per site
     */
    public function ajax_register() {
        // Set error handler to catch all errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            error_log('[BBAI] ajax_register started');
            $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
            if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
                wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
            }
            error_log('[BBAI] nonce verified');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Only administrators can connect accounts.', 'opptiai-alt')]);
            }

            $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
            $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
            $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

            if (empty($email) || empty($password)) {
                wp_send_json_error(['message' => __('Email and password are required', 'opptiai-alt')]);
            }

            error_log('[BBAI] Getting existing token');
            $existing_token = $this->api_client->get_token();
            error_log('[BBAI] Existing token: ' . ($existing_token ? 'yes' : 'no'));
            if (!empty($existing_token)) {
                error_log('[BBAI] Checking usage');
                $usage = $this->api_client->get_usage();
                if (!is_wp_error($usage) && isset($usage['plan']) && $usage['plan'] === 'free') {
                    wp_send_json_error([
                        'message' => __('This site is already linked to a free account. Ask an administrator to upgrade to Growth or Agency for higher limits.', 'opptiai-alt'),
                        'code' => 'free_plan_exists'
                    ]);
                }
            }

            error_log('[BBAI] Calling api_client->register');
            $result = $this->api_client->register($email, $password);
            error_log('[BBAI] Register result: ' . (is_wp_error($result) ? 'error' : 'success'));

            if (is_wp_error($result)) {
                $error_code = $result->get_error_code();
                $error_message = $result->get_error_message();
                $error_data = $result->get_error_data();

                // Handle site already has license (credit sharing)
                if ($error_code === 'site_has_license' || $error_code === 'SITE_HAS_LICENSE') {
                    $existing_email = isset($error_data['existing_email']) ? $error_data['existing_email'] : '';
                    wp_send_json_error([
                        'message' => sprintf(
                            /* translators: 1: existing account email, if available */
                            __('This site is already connected to an account%s. All WordPress users share the same credits. Please log in with the existing account credentials, or disconnect first to use a different account.', 'opptiai-alt'),
                            $existing_email ? ' (' . $existing_email . ')' : ''
                        ),
                        'code' => 'site_has_license',
                        'existing_email' => $existing_email
                    ]);
                }

                if ($error_code === 'free_plan_exists' || (is_string($error_message) && strpos(strtolower($error_message), 'free plan') !== false)) {
                    wp_send_json_error([
                        'message' => __('A free plan has already been used for this site. Upgrade to Growth or Agency to increase your quota.', 'opptiai-alt'),
                        'code' => 'free_plan_exists'
                    ]);
                }

                wp_send_json_error(['message' => $error_message]);
            }

            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
            \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

            wp_send_json_success([
                'message' => __('Account created successfully', 'opptiai-alt'),
                'user' => $result['user'] ?? null
            ]);
        } catch (\Throwable $e) {
            error_log('[BBAI] Registration exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $error_message = sanitize_text_field($e->getMessage());
            $error_file = sanitize_text_field(basename($e->getFile()));
            $error_line = (int) $e->getLine();
            restore_error_handler();
            wp_send_json_error([
                'message' => 'Registration error: ' . $error_message,
                'file' => $error_file,
                'line' => $error_line
            ]);
        }
        restore_error_handler();
    }

    /**
     * AJAX handler: User login
     */
    public function ajax_login() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => __('Email and password are required', 'opptiai-alt')]);
        }

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Logged in successfully', 'opptiai-alt'),
            'user' => $result['user'] ?? null
        ]);
    }

    /**
     * AJAX handler: User logout
     * Clears authentication token, license key, and all cached data
     */
    public function ajax_logout() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
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
            'message' => __('Logged out successfully', 'opptiai-alt'),
            'redirect' => admin_url('admin.php?page=bbai')
        ]);
    }

    /**
     * Handle logout via form submission (admin-post handler)
     */
    public function handle_logout() {
        $nonce_raw = isset($_POST['bbai_logout_nonce']) ? wp_unslash($_POST['bbai_logout_nonce']) : '';
        $nonce = is_string($nonce_raw) ? sanitize_text_field($nonce_raw) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'bbai_logout_action')) {
            wp_die(esc_html__('Security check failed', 'opptiai-alt'));
        }

        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized', 'opptiai-alt'));
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
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }

        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
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
            'message' => __('Account disconnected. Please sign in again to reconnect.', 'opptiai-alt'),
        ]);
    }

    /**
     * AJAX handler: Get user info
     */
    public function ajax_get_user_info() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'opptiai-alt')]);
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Not authenticated', 'opptiai-alt'),
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
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }

        $email_raw = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $email = is_string($email_raw) ? sanitize_email($email_raw) : '';

        if (empty($email)) {
            wp_send_json_error(['message' => __('Email is required', 'opptiai-alt')]);
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('If an account exists with this email, you will receive password reset instructions.', 'opptiai-alt')
        ]);
    }

    /**
     * AJAX handler: Reset password
     */
    public function ajax_reset_password() {
        $nonce = isset($_POST["nonce"]) ? sanitize_text_field(wp_unslash($_POST["nonce"])) : "";
        if (!$nonce || !wp_verify_nonce($nonce, "beepbeepai_nonce")) {
            wp_send_json_error(["message" => __("Invalid nonce.", "opptiai-alt")], 403);
        }

        $token_raw = isset($_POST['token']) ? wp_unslash($_POST['token']) : '';
        $token = is_string($token_raw) ? sanitize_text_field($token_raw) : '';
        $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';

        if (empty($token) || empty($password)) {
            wp_send_json_error(['message' => __('Token and password are required', 'opptiai-alt')]);
        }

        $result = $this->api_client->reset_password($token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now log in.', 'opptiai-alt')
        ]);
    }
}
