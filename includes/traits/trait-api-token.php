<?php
/**
 * API Token Trait
 * Handles JWT token and license key storage/retrieval
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Api_Token {

    /**
     * Get stored JWT token
     */
    public function get_token() {
        $token = get_option($this->token_option_key, '');
        if ($token === '' || $token === false) {
            $legacy = get_option('beepbeepai_jwt_token', '');
            if (!empty($legacy)) {
                $this->set_token($legacy);
                $token = $legacy;
            }
        }
        if (empty($token)) {
            $token = get_option('opptibbai_jwt_token', '');
        }
        return is_string($token) ? $token : '';
    }

    /**
     * Store JWT token securely
     */
    public function set_token($token) {
        if (empty($token)) {
            return false;
        }

        $encrypted = $this->encrypt_secret($token);
        update_option($this->token_option_key, $encrypted, false);

        foreach (['beepbeepai_jwt_token', 'opptibbai_jwt_token'] as $legacy) {
            if (get_option($legacy, '') !== '') {
                delete_option($legacy);
            }
        }

        return true;
    }

    /**
     * Clear stored token
     */
    public function clear_token() {
        delete_option($this->token_option_key);
        delete_option('beepbeepai_jwt_token');
        delete_option('opptibbai_jwt_token');
    }

    /**
     * Get stored user data
     */
    public function get_user_data() {
        $user_data = get_option($this->user_option_key, []);
        if (empty($user_data)) {
            $user_data = get_option('opptibbai_user_data', []);
            if (!empty($user_data)) {
                $this->set_user_data($user_data);
            }
        }
        return is_array($user_data) ? $user_data : [];
    }

    /**
     * Store user data
     */
    public function set_user_data($user_data) {
        update_option($this->user_option_key, $user_data, false);
    }

    /**
     * Get stored license key
     */
    public function get_license_key() {
        $key = get_option($this->license_key_option_key, '');
        if (!empty($key)) {
            $key = $this->maybe_decrypt_secret($key);
        }
        return is_string($key) ? $key : '';
    }

    /**
     * Store license key securely
     */
    public function set_license_key($license_key) {
        if (empty($license_key)) {
            return false;
        }

        $encrypted = $this->encrypt_secret($license_key);
        update_option($this->license_key_option_key, $encrypted, false);
        return true;
    }

    /**
     * Clear stored license key
     */
    public function clear_license_key() {
        delete_option($this->license_key_option_key);
        delete_option($this->license_data_option_key);
    }

    /**
     * Get stored license data
     */
    public function get_license_data() {
        $data = get_option($this->license_data_option_key, []);
        return is_array($data) ? $data : [];
    }

    /**
     * Store license data
     */
    public function set_license_data($license_data) {
        update_option($this->license_data_option_key, $license_data, false);
    }

    /**
     * Check if site has an active license
     */
    public function has_active_license() {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            return false;
        }

        $license_data = $this->get_license_data();
        if (empty($license_data) || empty($license_data['organization'])) {
            return false;
        }

        return true;
    }

    /**
     * Get site ID
     */
    public function get_site_id() {
        $site_id = get_option($this->site_id_option_key, '');
        if (empty($site_id)) {
            $site_id = get_option('opptibbai_site_id', '');
        }
        return is_string($site_id) ? $site_id : '';
    }

    /**
     * Encrypt a secret value
     */
    private function encrypt_secret($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        if (strpos($value, $this->encryption_prefix) === 0) {
            return $value;
        }

        if (function_exists('openssl_encrypt') && defined('LOGGED_IN_KEY') && !empty(LOGGED_IN_KEY)) {
            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($iv_length);
            $encrypted = openssl_encrypt($value, 'AES-256-CBC', LOGGED_IN_KEY, OPENSSL_RAW_DATA, $iv);
            if ($encrypted !== false) {
                return $this->encryption_prefix . base64_encode($iv . $encrypted);
            }
        }

        return $value;
    }

    /**
     * Decrypt a secret value if encrypted
     */
    private function maybe_decrypt_secret($value) {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        if (strpos($value, $this->encryption_prefix) !== 0) {
            return $value;
        }

        if (function_exists('openssl_decrypt') && defined('LOGGED_IN_KEY') && !empty(LOGGED_IN_KEY)) {
            $data = base64_decode(substr($value, strlen($this->encryption_prefix)));
            if ($data === false) {
                return '';
            }

            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', LOGGED_IN_KEY, OPENSSL_RAW_DATA, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return '';
    }
}
