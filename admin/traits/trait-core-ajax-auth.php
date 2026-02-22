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
            throw new \ErrorException(
                esc_html(sanitize_text_field((string) $errstr)),
                0,
                absint($errno),
                esc_html(sanitize_text_field((string) $errfile)),
                absint($errline)
            );
	        });
	
			        try {
			            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
			                error_log('[BBAI] ajax_register started'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
			            }
			            $action = 'beepbeepai_nonce';
			            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
			                restore_error_handler();
			                wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
			                return;
			            }
			            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
			                error_log('[BBAI] nonce verified'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
			            }

		            if (!current_user_can('manage_options')) {
		                wp_send_json_error(['message' => __('Only administrators can connect accounts.', 'beepbeep-ai-alt-text-generator')]);
		                return;
		            }

		            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		            $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
		            $password = is_string($password_input) ? $password_input : '';

		            if (empty($email) || empty($password)) {
		                wp_send_json_error(['message' => __('Email and password are required', 'beepbeep-ai-alt-text-generator')]);
		                return;
		            }

		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Getting existing token'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
		            $existing_token = $this->api_client->get_token();
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Existing token: ' . ($existing_token ? 'yes' : 'no')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
	            if (!empty($existing_token)) {
		                if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                    error_log('[BBAI] Checking usage'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		                }
	                $usage = $this->api_client->get_usage();
		                if (!is_wp_error($usage) && isset($usage['plan']) && $usage['plan'] === 'free') {
		                    wp_send_json_error([
		                        'message' => __('This site is already linked to a free account. Ask an administrator to upgrade to Growth or Agency for higher limits.', 'beepbeep-ai-alt-text-generator'),
	                        'code' => 'free_plan_exists'
	                    ]);
		                    return;
		                }
		            }

		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Calling api_client->register'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
		            $result = $this->api_client->register($email, $password);
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Register result: ' . (is_wp_error($result) ? 'error' : 'success')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }

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
                            __('This site is already connected to an account%s. All WordPress users share the same credits. Please log in with the existing account credentials, or disconnect first to use a different account.', 'beepbeep-ai-alt-text-generator'),
                            $existing_email ? ' (' . $existing_email . ')' : ''
                        ),
	                        'code' => 'site_has_license',
	                        'existing_email' => $existing_email
	                    ]);
	                    return;
	                }

	                if ($error_code === 'free_plan_exists' || (is_string($error_message) && strpos(strtolower($error_message), 'free plan') !== false)) {
	                    wp_send_json_error([
	                        'message' => __('A free plan has already been used for this site. Upgrade to Growth or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator'),
	                        'code' => 'free_plan_exists'
	                    ]);
	                    return;
	                }

	                wp_send_json_error(['message' => $error_message]);
	                return;
	            }

            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-token-quota-service.php';
            \BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();

            wp_send_json_success([
                'message' => __('Account created successfully', 'beepbeep-ai-alt-text-generator'),
	                'user' => $result['user'] ?? null
	            ]);
		        } catch (\Throwable $e) {
		            if ( defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
		                error_log('[BBAI] Registration exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log
		            }
	            $error_message = sanitize_text_field($e->getMessage());
	            $error_file = sanitize_text_field(basename($e->getFile()));
	            $error_line = (int) $e->getLine();
	            restore_error_handler();
	            wp_send_json_error([
	                'message' => 'Registration error: ' . $error_message,
	                'file' => $error_file,
	                'line' => $error_line
	            ]);
	            return;
	        }
	        restore_error_handler();
	    }

    /**
     * AJAX handler: User login
     */
		    public function ajax_login() {
		        $action = 'beepbeepai_nonce';
		        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
		            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
		            return;
		        }
		        if (!$this->user_can_manage()) {
		            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
		            return;
		        }

		        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
		        $password = is_string($password_input) ? $password_input : '';

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
		        $action = 'beepbeepai_nonce';
		        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
		            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
		            return;
		        }
		        if (!$this->user_can_manage()) {
		            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
		            return;
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
     * Handle logout via form submission (admin-post handler)
     */
    public function handle_logout() {
        $action = 'bbai_logout_action';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbai_logout_nonce'] ?? '' ) ), $action ) ) {
            wp_die(esc_html__('Security check failed', 'beepbeep-ai-alt-text-generator'));
        }

        if (!$this->user_can_manage()) {
            wp_die(esc_html__('Unauthorized', 'beepbeep-ai-alt-text-generator'));
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
		        $action = 'beepbeepai_nonce';
		        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
		            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
		            return;
		        }

	        if (!$this->user_can_manage()) {
	            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
	            return;
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
		        $action = 'beepbeepai_nonce';
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
     * AJAX handler: Forgot password
     */
	    public function ajax_forgot_password() {
	        $action = 'beepbeepai_nonce';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
	            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
	            return;
	        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

        if (empty($email)) {
            wp_send_json_error(['message' => __('Email is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->forgot_password($email);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => __('If an account exists with this email, you will receive password reset instructions.', 'beepbeep-ai-alt-text-generator')
        ]);
    }

    /**
     * AJAX handler: Reset password
     */
	    public function ajax_reset_password() {
	        $action = 'beepbeepai_nonce';
	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
	            wp_send_json_error(["message" => __("Invalid nonce.", "beepbeep-ai-alt-text-generator")], 403);
	            return;
	        }
        if (!$this->user_can_manage()) {
            wp_send_json_error(['message' => __('Unauthorized', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

		        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
		        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
		        $password = is_string($password_input) ? $password_input : '';

        if (empty($token) || empty($password)) {
            wp_send_json_error(['message' => __('Token and password are required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->reset_password($token, $password);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => __('Password reset successfully. You can now log in.', 'beepbeep-ai-alt-text-generator')
        ]);
    }
}
