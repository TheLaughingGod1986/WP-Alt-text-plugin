<?php
/**
 * API Authentication Trait
 * Handles authentication, login, and registration
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Api_Auth {

    /**
     * Check if user is authenticated
     */
    public function is_authenticated() {
        $has_license = $this->has_active_license();
        if ($has_license) {
            return true;
        }

        $token = $this->get_token();
        if (empty($token)) {
            return false;
        }

        $cache_key = 'bbai_auth_check_' . md5($token);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'valid';
        }

        $response = $this->make_request('/auth/me', 'GET');

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            if (in_array($error_code, ['auth_expired', 'invalid_token', 'unauthorized'], true)) {
                set_transient($cache_key, 'invalid', 5 * MINUTE_IN_SECONDS);
                return false;
            }
            return false;
        }

        set_transient($cache_key, 'valid', 5 * MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Register a new user
     */
    public function register($email, $password) {
        $site_fingerprint = $this->get_site_fingerprint();

        $response = $this->make_request('/auth/register', 'POST', [
            'email' => $email,
            'password' => $password,
            'site_url' => get_site_url(),
            'site_fingerprint' => $site_fingerprint,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['token'])) {
            $this->set_token($response['token']);
        }

        if (isset($response['user'])) {
            $this->set_user_data($response['user']);
        }

        if (isset($response['site_id'])) {
            update_option($this->site_id_option_key, $response['site_id'], false);
        }

        return $response;
    }

    /**
     * Login user
     */
    public function login($email, $password) {
        $site_fingerprint = $this->get_site_fingerprint();

        $response = $this->make_request('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password,
            'site_url' => get_site_url(),
            'site_fingerprint' => $site_fingerprint,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['token'])) {
            $this->set_token($response['token']);
        }

        if (isset($response['user'])) {
            $this->set_user_data($response['user']);
        }

        if (isset($response['site_id'])) {
            update_option($this->site_id_option_key, $response['site_id'], false);
        }

        return $response;
    }

    /**
     * Get user info
     */
    public function get_user_info() {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Not authenticated', 'beepbeep-ai-alt-text-generator'));
        }

        $response = $this->make_request('/auth/me', 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['user'])) {
            $this->set_user_data($response['user']);
        }

        return $response;
    }

    /**
     * Forgot password
     */
    public function forgot_password($email) {
        $site_fingerprint = $this->get_site_fingerprint();

        $response = $this->make_request('/auth/forgot-password', 'POST', [
            'email' => $email,
            'site_url' => get_site_url(),
            'site_fingerprint' => $site_fingerprint,
        ]);

        return $response;
    }

    /**
     * Reset password
     */
    public function reset_password($token, $new_password) {
        $response = $this->make_request('/auth/reset-password', 'POST', [
            'token' => $token,
            'password' => $new_password,
        ]);

        return $response;
    }

    /**
     * Get site fingerprint
     */
    private function get_site_fingerprint() {
        if (class_exists('\BeepBeepAI\AltTextGenerator\Site_Fingerprint')) {
            return \BeepBeepAI\AltTextGenerator\Site_Fingerprint::generate();
        }

        return md5(get_site_url() . AUTH_KEY);
    }

    /**
     * Get authentication headers
     */
    private function get_auth_headers($include_user_id = false, $extra_headers = []) {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $license_key = $this->get_license_key();
        if (!empty($license_key)) {
            $headers['X-License-Key'] = $license_key;
            $headers['X-Site-Fingerprint'] = $this->get_site_fingerprint();
            $headers['X-Site-URL'] = get_site_url();

            if ($include_user_id) {
                $user_id = get_current_user_id();
                if ($user_id > 0) {
                    $headers['X-WP-User-Id'] = (string) $user_id;
                }
            }

            return array_merge($headers, $extra_headers);
        }

        $token = $this->get_token();
        if (!empty($token)) {
            $token = $this->maybe_decrypt_secret($token);
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ($include_user_id) {
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                $headers['X-WP-User-Id'] = (string) $user_id;
            }
        }

        return array_merge($headers, $extra_headers);
    }
}
