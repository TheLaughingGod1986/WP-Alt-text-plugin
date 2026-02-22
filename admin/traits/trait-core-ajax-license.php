<?php
/**
 * Core Ajax License Trait
 * Handles license management AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Ajax_License {

    /**
     * AJAX handler: Activate license key
     */
    public function ajax_activate_license() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $license_key)) {
            wp_send_json_error(['message' => __('Invalid license key format', 'beepbeep-ai-alt-text-generator')]);
            return;
        }

        $result = $this->api_client->activate_license($license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }

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
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }

        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $result = $this->api_client->deactivate_license();

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
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view license site usage', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

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
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to disconnect license sites', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

        $site_id = isset($_POST['site_id']) ? sanitize_text_field(wp_unslash($_POST['site_id'])) : '';
        if (empty($site_id)) {
            wp_send_json_error([
                'message' => __('Site ID is required', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

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
        $admin_session = get_transient('bbai_admin_session_' . get_current_user_id());
        if ($admin_session === false || empty($admin_session)) {
            return false;
        }

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
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

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

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password_input = isset($_POST['password']) ? wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be text-sanitized.
        $password = is_string($password_input) ? $password_input : '';

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

        $result = $this->api_client->login($email, $password);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message() ?: __('Login failed. Please check your credentials.', 'beepbeep-ai-alt-text-generator')
            ]);
            return;
        }

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
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $this->clear_admin_session();

        wp_send_json_success([
            'message' => __('Logged out successfully', 'beepbeep-ai-alt-text-generator'),
            'redirect' => add_query_arg(['tab' => 'admin'], admin_url('upload.php?page=bbai'))
        ]);
    }
}
