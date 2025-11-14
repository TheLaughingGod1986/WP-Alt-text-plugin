<?php
/**
 * API Client for AltText AI Phase 2
 * Handles JWT authentication and communication with the Phase 2 API
 */

if (!defined('ABSPATH')) { exit; }

class AltText_AI_API_Client_V2 {
    
    private $api_url;
    private $token_option_key = 'opptiai_alt_jwt_token';
    private $user_option_key = 'opptiai_alt_user_data';
    private $site_id_option_key = 'opptiai_alt_site_id';
    private $license_key_option_key = 'opptiai_alt_license_key';
    private $license_data_option_key = 'opptiai_alt_license_data';
    private $encryption_prefix = 'enc:';
    
    public function __construct() {
        // ALWAYS use production API by default
        $production_url = 'https://alttext-ai-backend.onrender.com';

        // Allow developers to override for local development via wp-config.php
        if (defined('ALTTEXT_AI_API_URL')) {
            // Custom URL defined in wp-config.php
            $this->api_url = ALTTEXT_AI_API_URL;
        } elseif (defined('WP_DEBUG') && WP_DEBUG && defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            // Local development mode (requires both WP_DEBUG and WP_LOCAL_DEV constants)
            $this->api_url = 'http://host.docker.internal:3001';
        } else {
            // Production for all normal users
            $this->api_url = $production_url;
        }

        // Force update WordPress settings to production (clean up legacy configs)
        $options = get_option('opptiai_alt_settings', []);
        if ($options === false || $options === null) {
            $options = get_option('opptiai_settings', []);
        }
        $options = is_array($options) ? $options : [];
        if (!isset($options['api_url']) || $options['api_url'] !== $production_url) {
            $options['api_url'] = $production_url;
            update_option('opptiai_alt_settings', $options);
        }
    }
    
    /**
     * Get stored JWT token
     */
    protected function get_token() {
        $token = get_option($this->token_option_key, '');
        if ($token === '' || $token === false) {
            $legacy = get_option('alttextai_jwt_token', '');
            if (!empty($legacy)) {
                $this->set_token($legacy);
                $token = $legacy;
            }
        }
        $token = is_string($token) ? $token : '';
        return $this->maybe_decrypt_secret($token);
    }
    
    /**
     * Store JWT token
     */
    public function set_token($token) {
        if (empty($token)) {
            $this->clear_token();
            return;
        }

        $stored = $this->encrypt_secret($token);
        if (empty($stored)) {
            $stored = $token;
        }
        update_option($this->token_option_key, $stored, false);
    }
    
    /**
     * Clear stored token
     */
    public function clear_token() {
        delete_option($this->token_option_key);
        delete_option('alttextai_jwt_token');
        delete_option($this->user_option_key);
        delete_option('alttextai_user_data');
    }
    
    /**
     * Get stored user data
     */
    public function get_user_data() {
        $data = get_option($this->user_option_key, null);
        if (($data === false || $data === null)) {
            $legacy = get_option('alttextai_user_data', null);
            if ($legacy !== null && $legacy !== false) {
                update_option($this->user_option_key, $legacy);
                $data = $legacy;
            }
        }
        return ($data !== false && $data !== null) ? $data : null;
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
        if ($key === '' || $key === false) {
            return '';
        }
        return $this->maybe_decrypt_secret($key);
    }

    /**
     * Store license key
     */
    public function set_license_key($license_key) {
        if (empty($license_key)) {
            $this->clear_license_key();
            return;
        }

        $stored = $this->encrypt_secret($license_key);
        if (empty($stored)) {
            $stored = $license_key;
        }
        update_option($this->license_key_option_key, $stored, false);
    }

    /**
     * Clear stored license key
     */
    public function clear_license_key() {
        delete_option($this->license_key_option_key);
        delete_option($this->license_data_option_key);
    }

    /**
     * Get stored license data (organization info, etc.)
     */
    public function get_license_data() {
        $data = get_option($this->license_data_option_key, null);
        return ($data !== false && $data !== null) ? $data : null;
    }

    /**
     * Store license data
     */
    public function set_license_data($license_data) {
        update_option($this->license_data_option_key, $license_data, false);
    }

    /**
     * Check if license key is active
     */
    public function has_active_license() {
        $license_key = $this->get_license_key();
        $license_data = $this->get_license_data();

        return !empty($license_key) && !empty($license_data) &&
               isset($license_data['organization']) &&
               isset($license_data['site']);
    }

    /**
     * Check if user is authenticated (JWT or License Key)
     * Also validates the token by checking with backend
     */
    public function is_authenticated() {
        // Check license key first (agency license)
        if ($this->has_active_license()) {
            return true;
        }

        // Check JWT token (personal account)
        $token = $this->get_token();

        if (empty($token)) {
            return false;
        }

        // In local development, just check if token exists
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            return true;
        }

        // Validate token is still valid (check periodically, not every request)
        $last_check = get_transient('opptiai_alt_token_last_check');
        $should_validate = $last_check === false;

        if ($should_validate) {
            // Try to fetch user info to validate token
            $user_info = $this->get_user_info();

            if (is_wp_error($user_info)) {
                // Token is invalid, clear it
                $this->clear_token();
                return false;
            }

            // Token is valid, cache result for 5 minutes
            set_transient('opptiai_alt_token_last_check', time(), 5 * MINUTE_IN_SECONDS);
        }

        return true;
    }
    
    /**
     * Get or generate unique site ID
     * This ensures quotas are tracked per-site, not per-user
     */
    private function get_site_id() {
        $site_id = get_option($this->site_id_option_key, '');
        
        if (empty($site_id)) {
            // Generate unique site ID based on site URL + timestamp
            $site_url = get_site_url();
            $site_id = md5($site_url . time() . wp_generate_password(20, false));
            update_option($this->site_id_option_key, $site_id, false);
        }
        
        return $site_id;
    }
    
    /**
     * Get authentication headers
     * Includes license key or JWT token, plus site info
     */
    private function get_auth_headers() {
        $token = $this->get_token();
        $license_key = $this->get_license_key();
        $site_id = $this->get_site_id();

        $headers = [
            'Content-Type' => 'application/json',
            'X-Site-Hash' => $site_id,  // Site-based licensing identifier
            'X-Site-URL' => get_site_url(),  // For backend reference
        ];

        // Priority: License key > JWT token
        if (!empty($license_key)) {
            $headers['X-License-Key'] = $license_key;
        } elseif ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Encrypt secret values before persisting to the options table.
     */
    private function encrypt_secret($value) {
        if (!is_string($value) || $value === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt') || (!function_exists('random_bytes') && !function_exists('openssl_random_pseudo_bytes'))) {
            return $value;
        }

        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $iv  = function_exists('random_bytes') ? @random_bytes(16) : openssl_random_pseudo_bytes(16);
        if ($iv === false) {
            return $value;
        }

        $cipher = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $value;
        }

        return $this->encryption_prefix . base64_encode($iv . $cipher);
    }

    /**
     * Decrypt stored secrets when retrieved from options.
     */
    private function maybe_decrypt_secret($value) {
        if (!is_string($value) || $value === '') {
            return '';
        }

        if (strpos($value, $this->encryption_prefix) !== 0) {
            return $value;
        }

        if (!function_exists('openssl_decrypt')) {
            return substr($value, strlen($this->encryption_prefix));
        }

        $payload = base64_decode(substr($value, strlen($this->encryption_prefix)), true);
        if ($payload === false || strlen($payload) < 17) {
            return '';
        }

        $iv = substr($payload, 0, 16);
        $cipher = substr($payload, 16);
        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : '';
    }
    
    /**
     * Make authenticated API request
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        $headers = $this->get_auth_headers();
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }
        
        $this->log_api_event('debug', 'API request started', [
            'endpoint' => $endpoint,
            'method'   => $method,
        ]);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_api_event('error', 'API request failed', [
                'endpoint' => $endpoint,
                'method'   => $method,
                'error'    => $error_message,
            ]);
            if (strpos($error_message, 'timeout') !== false) {
                return new WP_Error('api_timeout', __('Authentication server is taking too long to respond. Please try again in a few minutes.', 'wp-alt-text-plugin'));
            } elseif (strpos($error_message, 'could not resolve') !== false) {
                return new WP_Error('api_unreachable', __('Unable to reach authentication server. Please check your internet connection and try again.', 'wp-alt-text-plugin'));
            }
            return new WP_Error('api_error', $error_message);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log_api_event($status_code >= 400 ? 'warning' : 'debug', 'API response received', [
            'endpoint' => $endpoint,
            'method'   => $method,
            'status'   => $status_code,
        ]);
        
        // Handle 404 - endpoint not found
        if ($status_code === 404) {
            // Check if it's an HTML error page (means endpoint doesn't exist)
            if (strpos($body, '<html') !== false || strpos($body, 'Cannot POST') !== false || strpos($body, 'Cannot GET') !== false) {
                // Provide context-specific error messages
                $error_message = __('This feature is not yet available. Please contact support for assistance or try again later.', 'wp-alt-text-plugin');
                
                // Check endpoint to provide more specific message
                if (strpos($endpoint, '/auth/forgot-password') !== false || strpos($endpoint, '/auth/reset-password') !== false) {
                    $error_message = __('Password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'wp-alt-text-plugin');
                } elseif (strpos($endpoint, '/licenses/sites') !== false || strpos($endpoint, '/api/licenses/sites') !== false) {
                    $error_message = __('License site usage tracking is currently being set up on our backend. Please contact support for assistance or try again later.', 'wp-alt-text-plugin');
                }
                
                return new WP_Error(
                    'endpoint_not_found',
                    $error_message
                );
            }
            return new WP_Error(
                'not_found',
                $data['error'] ?? $data['message'] ?? __('The requested resource was not found.', 'wp-alt-text-plugin')
            );
        }
        
        // Handle server errors
        if ($status_code >= 500) {
            // Log the actual response body for debugging
            $error_details = '';
            if (is_array($data) && isset($data['error'])) {
                $error_details = $data['error'];
            } elseif (is_array($data) && isset($data['message'])) {
                $error_details = $data['message'];
            } elseif (!empty($body) && strlen($body) < 500) {
                // Only include body if it's not too long
                $error_details = $body;
            }
            
            $this->log_api_event('error', 'API server error', [
                'endpoint' => $endpoint,
                'method'   => $method,
                'status'   => $status_code,
                'error_details' => $error_details,
                'body_preview' => substr($body, 0, 200),
            ]);
            
            // Try to extract more specific error from response body
            $backend_error_code = '';
            $backend_error_message = '';
            
            if (is_array($data)) {
                $backend_error_code = $data['code'] ?? '';
                $backend_error_message = $data['message'] ?? $data['error'] ?? '';
            }
            
            // Provide more specific error message based on endpoint and error details
            $error_message = __('The server encountered an error processing your request. Please try again in a few minutes.', 'wp-alt-text-plugin');
            
            if (strpos($endpoint, '/api/generate') !== false) {
                // Check if it's an OpenAI API key issue (backend configuration problem)
                if (strpos(strtolower($error_details), 'incorrect api key') !== false || 
                    strpos(strtolower($error_details), 'invalid api key') !== false ||
                    strpos(strtolower($backend_error_code), 'generation_error') !== false) {
                    $error_message = __('The image generation service is temporarily unavailable due to a backend configuration issue. Please contact support.', 'wp-alt-text-plugin');
                } else {
                    $error_message = __('The image generation service is temporarily unavailable. Please try again in a few minutes.', 'wp-alt-text-plugin');
                }
            } elseif (strpos($endpoint, '/auth/') !== false) {
                $error_message = __('The authentication server is temporarily unavailable. Please try again in a few minutes.', 'wp-alt-text-plugin');
            }
            
            return new WP_Error(
                'server_error',
                $error_message,
                [
                    'status_code' => $status_code,
                    'endpoint' => $endpoint,
                    'error_details' => $error_details,
                    'backend_code' => $backend_error_code,
                    'backend_message' => $backend_error_message,
                ]
            );
        }
        
        // Handle authentication errors
        if ($status_code === 401 || $status_code === 403) {
            $this->clear_token();
            return new WP_Error(
                'auth_required',
                __('Authentication required. Please log in to continue.', 'wp-alt-text-plugin'),
                ['requires_auth' => true]
            );
        }
        
        return [
            'status_code' => $status_code,
            'data' => $data,
            'success' => $status_code >= 200 && $status_code < 300
        ];
    }
    
    /**
     * Register new user
     */
    public function register($email, $password) {
        $response = $this->make_request('/auth/register', 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success'] && isset($response['data']['token'])) {
            $this->set_token($response['data']['token']);
            $this->set_user_data($response['data']['user']);
            return $response['data'];
        }
        
        return new WP_Error(
            'registration_failed',
            $response['data']['error'] ?? __('Registration failed', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        $response = $this->make_request('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success'] && isset($response['data']['token'])) {
            $this->set_token($response['data']['token']);
            $this->set_user_data($response['data']['user']);
            return $response['data'];
        }
        
        return new WP_Error(
            'login_failed',
            $response['data']['error'] ?? __('Login failed', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Get current user info
     */
    public function get_user_info() {
        $response = $this->make_request('/auth/me');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            $this->set_user_data($response['data']['user']);
            return $response['data']['user'];
        }

        return new WP_Error(
            'user_info_failed',
            $response['data']['error'] ?? __('Failed to get user info', 'wp-alt-text-plugin')
        );
    }

    /**
     * Activate license key for this site
     */
    public function activate_license($license_key) {
        $site_id = $this->get_site_id();

        $data = [
            'licenseKey' => $license_key,
            'siteHash' => $site_id,
            'siteUrl' => get_site_url(),
            'installId' => $site_id,
            'pluginVersion' => defined('OPPTIAI_ALT_VERSION') ? OPPTIAI_ALT_VERSION : '1.0.0',
            'wordpressVersion' => get_bloginfo('version'),
            'phpVersion' => PHP_VERSION,
            'isMultisite' => is_multisite()
        ];

        $response = $this->make_request('/api/license/activate', 'POST', $data);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            // Store license key and data
            $this->set_license_key($license_key);
            $this->set_license_data([
                'organization' => $response['organization'],
                'site' => $response['site'],
                'activated_at' => current_time('mysql')
            ]);

            return [
                'success' => true,
                'organization' => $response['organization'],
                'site' => $response['site']
            ];
        }

        return new WP_Error(
            'license_activation_failed',
            $response['error'] ?? __('Failed to activate license', 'wp-alt-text-plugin')
        );
    }

    /**
     * Deactivate current license key
     */
    public function deactivate_license() {
        $this->clear_license_key();

        return [
            'success' => true,
            'message' => __('License deactivated successfully', 'wp-alt-text-plugin')
        ];
    }

    /**
     * Get license site usage statistics
     * Returns sites using the license and their generation counts
     */
    public function get_license_sites() {
        // Allow if authenticated (JWT) OR has active license (license key auth)
        $is_authenticated = $this->is_authenticated();
        $has_license = $this->has_active_license();
        
        if (!$is_authenticated && !$has_license) {
            return new WP_Error('not_authenticated', __('Must be authenticated or have an active license to view license site usage', 'wp-alt-text-plugin'));
        }

        $response = $this->make_request('/api/licenses/sites', 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data'] ?? ['sites' => []];
        }

        return new WP_Error('api_error', $response['message'] ?? __('Failed to fetch license site usage', 'wp-alt-text-plugin'));
    }

    /**
     * Disconnect a site from the license
     * Removes the installation from the backend
     */
    public function disconnect_license_site($site_id) {
        if (!$this->is_authenticated()) {
            return new WP_Error('not_authenticated', __('Must be authenticated to disconnect license sites', 'wp-alt-text-plugin'));
        }

        $response = $this->make_request('/api/licenses/sites/' . urlencode($site_id), 'DELETE');
        
        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data'] ?? ['message' => __('Site disconnected successfully', 'wp-alt-text-plugin')];
        }

        return new WP_Error('api_error', $response['message'] ?? __('Failed to disconnect site', 'wp-alt-text-plugin'));
    }

    /**
     * Get usage information
     */
    public function get_usage() {
        $has_license   = $this->has_active_license();
        $license_cache = $has_license ? $this->get_license_data() : null;

        $response = $this->make_request('/usage');

        if (is_wp_error($response)) {
            if ($has_license && $license_cache) {
                $cached_usage = $this->format_license_usage_from_cache($license_cache);
                if ($cached_usage) {
                    return $cached_usage;
                }
            }
            return $response;
        }

        if ($response['success'] && isset($response['data']['usage'])) {
            $usage = $response['data']['usage'];

            if ($has_license && is_array($usage)) {
                $this->sync_license_usage_snapshot(
                    $usage,
                    $response['data']['organization'] ?? [],
                    $response['data']['site'] ?? []
                );
            }

            return $usage;
        }

        if ($has_license && $license_cache) {
            $cached_usage = $this->format_license_usage_from_cache($license_cache);
            if ($cached_usage) {
                return $cached_usage;
            }
        }

        return new WP_Error(
            'usage_failed',
            $response['data']['error'] ?? __('Failed to get usage info', 'wp-alt-text-plugin')
        );
    }

    /**
     * Convert stored license data into a usage payload
     */
    private function format_license_usage_from_cache($license_data) {
        if (empty($license_data) || !isset($license_data['organization'])) {
            return null;
        }

        $org = $license_data['organization'];
        $limit = isset($org['tokenLimit']) ? intval($org['tokenLimit']) : 10000;
        $remaining = isset($org['tokensRemaining']) ? intval($org['tokensRemaining']) : $limit;
        $used = max(0, $limit - $remaining);

        $reset_ts = 0;
        if (!empty($org['resetDate'])) {
            $reset_ts = strtotime($org['resetDate']);
        }
        if ($reset_ts <= 0) {
            $reset_ts = strtotime('first day of next month');
        }

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'plan' => strtolower($org['plan'] ?? 'agency'),
            'resetDate' => $org['resetDate'] ?? '',
            'reset_timestamp' => $reset_ts,
            'seconds_until_reset' => max(0, $reset_ts - current_time('timestamp')),
        ];
    }

    /**
     * Persist license usage snapshots so cached data stays in sync
     */
    private function sync_license_usage_snapshot($usage, $organization = [], $site_data = []) {
        $existing_license = $this->get_license_data();
        $updated_license  = is_array($existing_license) ? $existing_license : [];
        $org              = isset($updated_license['organization']) && is_array($updated_license['organization'])
            ? $updated_license['organization']
            : [];

        if (is_array($organization) && !empty($organization)) {
            $org = array_merge($org, $organization);
        }

        if (isset($usage['limit'])) {
            $org['tokenLimit'] = intval($usage['limit']);
        }

        if (isset($usage['remaining'])) {
            $org['tokensRemaining'] = max(0, intval($usage['remaining']));
        } elseif (isset($usage['used']) && isset($org['tokenLimit'])) {
            $org['tokensRemaining'] = max(0, intval($org['tokenLimit']) - intval($usage['used']));
        }

        if (!empty($usage['resetDate'])) {
            $org['resetDate'] = sanitize_text_field($usage['resetDate']);
        } elseif (!empty($usage['nextReset'])) {
            $org['resetDate'] = sanitize_text_field($usage['nextReset']);
        }

        if (!empty($usage['plan'])) {
            $org['plan'] = sanitize_text_field($usage['plan']);
        }

        $updated_license['organization'] = $org;

        if (!empty($site_data)) {
            $updated_license['site'] = array_merge($updated_license['site'] ?? [], (array) $site_data);
        }

        $updated_license['updated_at'] = current_time('mysql');
        $this->set_license_data($updated_license);
    }

    /**
     * Check if user has reached their limit
     */
    public function has_reached_limit() {
        if ($this->has_active_license()) {
            return false;
        }

        $usage = $this->get_usage();

        // If there was an error getting usage, fail securely (assume limit reached)
        // This prevents bypassing limits when API is temporarily unavailable
        if (is_wp_error($usage)) {
            return true;
        }

        // Check if remaining is 0 or less
        return isset($usage['remaining']) && $usage['remaining'] <= 0;
    }

    /**
     * Get percentage of quota used
     */
    public function get_usage_percentage() {
        $usage = $this->get_usage();

        // If there was an error getting usage, return 0
        if (is_wp_error($usage)) {
            return 0;
        }

        $used = $usage['used'] ?? 0;
        $limit = $usage['limit'] ?? 50;

        if ($limit == 0) return 0;
        return min(100, round(($used / $limit) * 100));
    }

    /**
     * Generate alt text via API (Phase 2)
     */
    public function generate_alt_text($image_id, $context = [], $regenerate = false) {
        $endpoint = 'api/generate';
        
        // Enrich context with useful metadata for higher fidelity
        $image_url = wp_get_attachment_url($image_id);
        $title     = get_the_title($image_id);
        $caption   = wp_get_attachment_caption($image_id);
        $filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

        $image_payload = $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename);

        // Check if image preparation failed due to size
        if (isset($image_payload['_error']) && $image_payload['_error'] === 'image_too_large') {
            return new WP_Error(
                'image_too_large',
                $image_payload['_error_message'] ?? __('Image file is too large.', 'wp-alt-text-plugin'),
                ['image_id' => $image_id]
            );
        }

        $body = [
            'image_data' => $image_payload,
            'context' => $context,
            'regenerate' => $regenerate
        ];

        $response = $this->request_with_retry($endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Debug logging
        if (class_exists('AltText_AI_Debug_Log')) {
            AltText_AI_Debug_Log::log('debug', 'Generation API response', [
                'status_code' => $response['status_code'],
                'success' => $response['success'],
                'has_data' => isset($response['data']),
                'data_keys' => isset($response['data']) && is_array($response['data']) ? array_keys($response['data']) : 'not array'
            ], 'api');
        }

        // Handle rate limit
        if ($response['status_code'] === 429) {
            return new WP_Error(
                'limit_reached',
                $response['data']['error'] ?? __('Monthly limit reached', 'wp-alt-text-plugin'),
                ['usage' => $response['data']['usage'] ?? null]
            );
        }

        if (!$response['success']) {
            // Extract detailed error information
            $error_data = $response['data'] ?? [];
            $error_message = $error_data['message'] ?? $error_data['error'] ?? __('Failed to generate alt text', 'wp-alt-text-plugin');
            $error_code = $error_data['code'] ?? 'api_error';
            
            // Handle 413 Payload Too Large specifically
            if ($response['status_code'] === 413) {
                $error_message = __('Image file is too large. Please compress or resize the image before generating alt text.', 'wp-alt-text-plugin');
                $error_code = 'payload_too_large';
            }
            
            // Handle backend OpenAI API key errors (backend configuration issue)
            // Check both the error message and the backend's error field
            $backend_error_lower = strtolower($error_message . ' ' . ($error_data['error'] ?? ''));
            if (strpos($backend_error_lower, 'incorrect api key') !== false || 
                strpos($backend_error_lower, 'invalid api key') !== false ||
                strpos($backend_error_lower, 'api key provided') !== false ||
                $error_code === 'GENERATION_ERROR') {
                // This is a backend configuration issue - the backend's OpenAI API key is invalid/expired
                // This is NOT a plugin issue, but a backend server configuration problem
                $error_message = __('The backend service is experiencing a configuration issue. This is a temporary backend problem that needs to be fixed on the server side. Please try again in a few minutes or contact support if the issue persists.', 'wp-alt-text-plugin');
                $error_code = 'backend_config_error';
            }
            
            // Log detailed error information for debugging
            if (class_exists('AltText_AI_Debug_Log')) {
                AltText_AI_Debug_Log::log('error', 'API generation failed', [
                    'image_id' => $image_id,
                    'status_code' => $response['status_code'],
                    'error_code' => $error_code,
                    'error_message' => $error_message,
                    'backend_error' => $error_data['error'] ?? $error_data['message'] ?? 'Unknown error',
                    'backend_code' => $error_data['code'] ?? 'unknown',
                ], 'api');
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                [
                    'code' => $error_code,
                    'status_code' => $response['status_code'],
                    'image_id' => $image_id,
                    'api_response' => $error_data,
                    'backend_error' => $error_data['error'] ?? $error_data['message'] ?? null,
                ]
            );
        }

        return $response['data'];
    }
    
    /**
     * Review existing alt text via API
     */
    public function review_alt_text($image_id, $alt_text, $context = []) {
        $endpoint = 'api/review';

        $image_url = wp_get_attachment_url($image_id);
        $title     = get_the_title($image_id);
        $caption   = wp_get_attachment_caption($image_id);
        $filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

        $body = [
            'alt_text' => $alt_text,
            'image_data' => $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename),
            'context' => $context
        ];
        
        $response = $this->request_with_retry($endpoint, 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (!$response['success']) {
            $error_message = $response['data']['message'] ?? $response['data']['error'] ?? __('Failed to review alt text', 'wp-alt-text-plugin');
            return new WP_Error(
                'api_error',
                $error_message,
                ['code' => $response['data']['code'] ?? 'api_error']
            );
        }
        
        return $response['data'];
    }
    
    /**
     * Get billing information
     */
    public function get_billing_info() {
        $response = $this->make_request('/billing/info');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            return $response['data']['billing'];
        }
        
        return new WP_Error(
            'billing_failed',
            $response['data']['error'] ?? __('Failed to get billing info', 'wp-alt-text-plugin')
        );
    }

    /**
     * Retrieve available plans (includes Stripe price IDs)
     */
    public function get_plans() {
        $response = $this->make_request('/billing/plans');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data']['plans'] ?? [];
        }

        return new WP_Error(
            'plans_failed',
            $response['data']['error'] ?? __('Failed to fetch pricing plans', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Create checkout session
     */
    public function create_checkout_session($price_id, $success_url, $cancel_url) {
        $response = $this->make_request('/billing/checkout', 'POST', [
            'priceId' => $price_id,
            'successUrl' => $success_url,
            'cancelUrl' => $cancel_url
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            return $response['data'];
        }

        $error_message = '';
        if (isset($response['data']['error']) && is_string($response['data']['error'])) {
            $error_message = $response['data']['error'];
        } elseif (isset($response['data']['message']) && is_string($response['data']['message'])) {
            $error_message = $response['data']['message'];
        } elseif (!empty($response['data']) && is_array($response['data'])) {
            $error_message = wp_json_encode($response['data']);
        }

        if (!$error_message) {
            $error_message = __('Failed to create checkout session', 'wp-alt-text-plugin');
        }

        return new WP_Error(
            'checkout_failed',
            $error_message,
            ['response' => $response]
        );
    }
    
    /**
     * Create customer portal session
     */
    public function create_customer_portal_session($return_url) {
        $response = $this->make_request('/billing/portal', 'POST', [
            'returnUrl' => $return_url
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            return $response['data'];
        }
        
        return new WP_Error(
            'portal_failed',
            $response['data']['error'] ?? __('Failed to create customer portal session', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Request password reset
     */
    public function forgot_password($email) {
        // Temporarily clear token for unauthenticated request
        $temp_token = $this->get_token();
        $this->clear_token();
        
        // Get the WordPress site URL for the reset link
        $site_url = admin_url('upload.php?page=opptiai-alt');
        
        $response = $this->make_request('/auth/forgot-password', 'POST', [
            'email' => $email,
            'siteUrl' => $site_url
        ]);
        
        // Restore token if it existed
        if ($temp_token) {
            $this->set_token($temp_token);
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            // Return the full data object, which may include resetLink for development
            return $response['data'] ?? [];
        }
        
        // Extract error message with better context
        $error_message = $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to send password reset email', 'wp-alt-text-plugin');
        
        // Check for specific error cases
        if ($response['status_code'] === 404) {
            $error_message = __('Password reset is currently being set up. This feature is not yet available on our backend. Please contact support for assistance.', 'wp-alt-text-plugin');
        } elseif ($response['status_code'] === 429) {
            $error_message = __('Too many password reset requests. Please wait 15 minutes before trying again.', 'wp-alt-text-plugin');
        } elseif ($response['status_code'] >= 500) {
            $error_message = __('The authentication server is temporarily unavailable. Please try again in a few minutes.', 'wp-alt-text-plugin');
        }
        
        return new WP_Error(
            'forgot_password_failed',
            $error_message
        );
    }
    
    /**
     * Reset password with token
     */
    public function reset_password($email, $token, $new_password) {
        // Temporarily clear auth token for unauthenticated request
        $temp_token = $this->get_token();
        $this->clear_token();
        
        $response = $this->make_request('/auth/reset-password', 'POST', [
            'email' => $email,
            'token' => $token,
            'newPassword' => $new_password,
            'password' => $new_password // Also send as 'password' for compatibility
        ]);
        
        // Restore token if it existed
        if ($temp_token) {
            $this->set_token($temp_token);
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            return $response['data'];
        }
        
        return new WP_Error(
            'reset_password_failed',
            $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to reset password', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Get subscription information
     */
    public function get_subscription_info() {
        $response = $this->make_request('/billing/subscription');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success']) {
            return $response['data'];
        }
        
        return new WP_Error(
            'subscription_info_failed',
            $response['data']['error'] ?? __('Failed to fetch subscription information', 'wp-alt-text-plugin')
        );
    }
    
    /**
     * Prepare image payload for API
     */
    private function prepare_image_payload($image_id, $image_url, $title, $caption, $filename) {
        $payload = [
            'image_id' => (string) $image_id,  // Cast to string for Prisma compatibility
            'title' => $title,
            'caption' => $caption,
            'filename' => $filename
        ];
        
        // Try to get image dimensions
        $metadata = wp_get_attachment_metadata($image_id);
        if ($metadata && isset($metadata['width'], $metadata['height'])) {
            $payload['width'] = $metadata['width'];
            $payload['height'] = $metadata['height'];
        }
        
        if ($image_url) {
            // Check if URL is localhost - if so, encode as base64
            $parsed_url = parse_url($image_url);
            $is_localhost = isset($parsed_url['host']) && 
                           (in_array($parsed_url['host'], ['localhost', '127.0.0.1', 'host.docker.internal']) ||
                            strpos($parsed_url['host'], '.local') !== false ||
                            strpos($parsed_url['host'], 'localhost') !== false);
            
            if ($is_localhost) {
                // For localhost URLs, encode image as base64 so backend can access it
                // Resize to max 1024px on longest side to reduce payload size (doesn't affect token count)
                $file_path = get_attached_file($image_id);
                if ($file_path && file_exists($file_path)) {
                    $mime_type = get_post_mime_type($image_id) ?: 'image/jpeg';
                    
                    // Get original dimensions
                    $metadata = wp_get_attachment_metadata($image_id);
                    $orig_width = $metadata['width'] ?? 0;
                    $orig_height = $metadata['height'] ?? 0;
                    
                    // Resize to 512px for good AI analysis quality (backend now supports 2MB payloads)
                    $max_size = 512;
                    $needs_resize = ($orig_width > $max_size || $orig_height > $max_size);

                    if ($needs_resize && function_exists('wp_get_image_editor')) {
                        $editor = wp_get_image_editor($file_path);
                        if (!is_wp_error($editor)) {
                            // Calculate new dimensions maintaining aspect ratio
                            if ($orig_width > $orig_height) {
                                $new_width = $max_size;
                                $new_height = intval(($orig_height / $orig_width) * $max_size);
                            } else {
                                $new_height = $max_size;
                                $new_width = intval(($orig_width / $orig_height) * $max_size);
                            }

                            $editor->resize($new_width, $new_height, false);

                            // Set quality for good detail while keeping file size reasonable
                            if (method_exists($editor, 'set_quality')) {
                                $editor->set_quality(75);
                            }
                            
                            // Save to temporary file
                            $upload_dir = wp_upload_dir();
                            $temp_filename = 'alttext-ai-temp-' . $image_id . '-' . time() . '.jpg';
                            $temp_path = $upload_dir['path'] . '/' . $temp_filename;
                            $saved = $editor->save($temp_path, 'image/jpeg');
                            
                            if (!is_wp_error($saved) && isset($saved['path'])) {
                                $file_contents = file_get_contents($saved['path']);
                                @unlink($saved['path']); // Clean up temp file
                                if ($file_contents !== false) {
                                    $base64 = base64_encode($file_contents);
                                    $payload['image_base64'] = $base64;
                                    $payload['mime_type'] = 'image/jpeg'; // Resized images saved as JPEG
                                    // Don't send image_url for localhost - backend should use base64
                                } else {
                                    // Fallback to original file
                                    $file_contents = file_get_contents($file_path);
                                    if ($file_contents !== false) {
                                        $base64 = base64_encode($file_contents);
                                        $payload['image_base64'] = $base64;
                                        $payload['mime_type'] = $mime_type;
                                        // Don't send image_url for localhost - backend should use base64
                                    } else {
                                        // Don't send image_url for localhost - backend should use base64
                                    }
                                }
                            } else {
                                // Fallback to original file if resize fails
                                $file_contents = file_get_contents($file_path);
                                if ($file_contents !== false) {
                                    $base64 = base64_encode($file_contents);
                                    $payload['image_base64'] = $base64;
                                    $payload['mime_type'] = $mime_type;
                                    $payload['image_url'] = $image_url;
                                } else {
                                    $payload['image_url'] = $image_url;
                                }
                            }
                        } else {
                            // Fallback to original file if editor fails
                            $file_contents = file_get_contents($file_path);
                            if ($file_contents !== false) {
                                $base64 = base64_encode($file_contents);
                                $payload['image_base64'] = $base64;
                                $payload['mime_type'] = $mime_type;
                                $payload['image_url'] = $image_url;
                            } else {
                                $payload['image_url'] = $image_url;
                            }
                        }
                    } else {
                        // No resize needed or editor not available - check file size before encoding
                        $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                        $max_file_size = 4 * 1024 * 1024; // 4MB limit for base64 encoding
                        
                        if ($file_size > 0 && $file_size <= $max_file_size) {
                            $file_contents = file_get_contents($file_path);
                            if ($file_contents !== false) {
                                $base64 = base64_encode($file_contents);
                                // Base64 increases size by ~33%, so check encoded size (max ~5.3MB)
                                if (strlen($base64) <= 5.5 * 1024 * 1024) {
                                    $payload['image_base64'] = $base64;
                                    $payload['mime_type'] = $mime_type;
                                    // Don't send image_url for localhost - backend should use base64
                                } else {
                                    // File too large even after encoding, try to resize anyway
                                    if (function_exists('wp_get_image_editor')) {
                                        $editor = wp_get_image_editor($file_path);
                                        if (!is_wp_error($editor)) {
                                            $metadata = wp_get_attachment_metadata($image_id);
                                            $orig_width = $metadata['width'] ?? 0;
                                            $orig_height = $metadata['height'] ?? 0;
                                            $max_size = 800; // Smaller size for large files
                                            
                                            if ($orig_width > $max_size || $orig_height > $max_size) {
                                                if ($orig_width > $orig_height) {
                                                    $new_width = $max_size;
                                                    $new_height = intval(($orig_height / $orig_width) * $max_size);
                                                } else {
                                                    $new_height = $max_size;
                                                    $new_width = intval(($orig_width / $orig_height) * $max_size);
                                                }
                                                
                                                $editor->resize($new_width, $new_height, false);
                                                
                                                // Set quality to reduce file size (85% is a good balance)
                                                if (method_exists($editor, 'set_quality')) {
                                                    $editor->set_quality(85);
                                                }
                                                
                                                $upload_dir = wp_upload_dir();
                                                $temp_filename = 'alttext-ai-temp-' . $image_id . '-' . time() . '.jpg';
                                                $temp_path = $upload_dir['path'] . '/' . $temp_filename;
                                                $saved = $editor->save($temp_path, 'image/jpeg');
                                                
                                                if (!is_wp_error($saved) && isset($saved['path'])) {
                                                    $resized_contents = file_get_contents($saved['path']);
                                                    @unlink($saved['path']);
                                                    if ($resized_contents !== false) {
                                                        $base64 = base64_encode($resized_contents);
                                                        if (strlen($base64) <= 5.5 * 1024 * 1024) {
                                                            $payload['image_base64'] = $base64;
                                                            $payload['mime_type'] = 'image/jpeg';
                                                            // Don't send image_url for localhost
                                                        } else {
                                                            // Still too large, send URL as fallback
                                                            $payload['image_url'] = $image_url;
                                                        }
                                                    } else {
                                                        $payload['image_url'] = $image_url;
                                                    }
                                                } else {
                                                    $payload['image_url'] = $image_url;
                                                }
                                            } else {
                                                // Image already small, but file size is large - send URL
                                                $payload['image_url'] = $image_url;
                                            }
                                        } else {
                                            $payload['image_url'] = $image_url;
                                        }
                                    } else {
                                        $payload['image_url'] = $image_url;
                                    }
                                }
                            } else {
                                $payload['image_url'] = $image_url;
                            }
                        } else {
                            // File too large, try to resize it
                            if ($file_size > $max_file_size && function_exists('wp_get_image_editor')) {
                                $editor = wp_get_image_editor($file_path);
                                if (!is_wp_error($editor)) {
                                    $metadata = wp_get_attachment_metadata($image_id);
                                    $orig_width = $metadata['width'] ?? 0;
                                    $orig_height = $metadata['height'] ?? 0;
                                    $max_size = 800; // Smaller size for large files
                                    
                                    if ($orig_width > $max_size || $orig_height > $max_size) {
                                        if ($orig_width > $orig_height) {
                                            $new_width = $max_size;
                                            $new_height = intval(($orig_height / $orig_width) * $max_size);
                                        } else {
                                            $new_height = $max_size;
                                            $new_width = intval(($orig_width / $orig_height) * $max_size);
                                        }
                                        
                                        $editor->resize($new_width, $new_height, false);
                                        
                                        $upload_dir = wp_upload_dir();
                                        $temp_filename = 'alttext-ai-temp-' . $image_id . '-' . time() . '.jpg';
                                        $temp_path = $upload_dir['path'] . '/' . $temp_filename;
                                        $saved = $editor->save($temp_path, 'image/jpeg');
                                        
                                        if (!is_wp_error($saved) && isset($saved['path'])) {
                                            $resized_contents = file_get_contents($saved['path']);
                                            @unlink($saved['path']);
                                            if ($resized_contents !== false) {
                                                $base64 = base64_encode($resized_contents);
                                                if (strlen($base64) <= 5.5 * 1024 * 1024) {
                                                    $payload['image_base64'] = $base64;
                                                    $payload['mime_type'] = 'image/jpeg';
                                                    // Don't send image_url for localhost
                                                } else {
                                                    $payload['image_url'] = $image_url;
                                                }
                                            } else {
                                                $payload['image_url'] = $image_url;
                                            }
                                        } else {
                                            $payload['image_url'] = $image_url;
                                        }
                                    } else {
                                        $payload['image_url'] = $image_url;
                                    }
                                } else {
                                    $payload['image_url'] = $image_url;
                                }
                            } else {
                                $payload['image_url'] = $image_url;
                            }
                        }
                    }
                } else {
                    // Fallback to URL if file doesn't exist
                    $payload['image_url'] = $image_url;
                }
            } else {
                // For public URLs, check if image is too large and resize if needed
                // Large images should be resized and sent as base64 to avoid 413 errors
                $file_path = get_attached_file($image_id);
                if ($file_path && file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    // Very aggressive threshold - resize if over 512KB to prevent backend errors
                    $max_file_size = 512 * 1024; // 512KB - resize if larger
                    
                    // Get metadata first
                    $mime_type = get_post_mime_type($image_id) ?: 'image/jpeg';
                    $metadata = wp_get_attachment_metadata($image_id);
                    
                    // Always resize large images and send as base64 to prevent 413 errors
                    // Also resize if file doesn't exist in metadata (dimensions unknown)
                    $should_resize = ($file_size > $max_file_size) || 
                                    (empty($metadata) || empty($metadata['width']) || empty($metadata['height']));
                    
                    if ($should_resize && function_exists('wp_get_image_editor')) {
                        $orig_width = $metadata['width'] ?? 0;
                        $orig_height = $metadata['height'] ?? 0;
                        
                        // Log resize attempt for debugging
                        if (class_exists('AltText_AI_Debug_Log')) {
                            AltText_AI_Debug_Log::log('debug', 'Resizing large image for API', [
                                'image_id' => $image_id,
                                'file_size' => round($file_size / 1024 / 1024, 2) . 'MB',
                                'dimensions' => $orig_width . 'x' . $orig_height,
                            ], 'api');
                        }
                        
                        // Resize to 512px for good AI analysis (backend now supports 2MB payloads)
                        $max_size = 512;
                        
                        // Always resize if dimensions are large OR if file size is large (even if dimensions are small)
                        $needs_dimension_resize = ($orig_width > $max_size || $orig_height > $max_size);
                        $needs_resize = $needs_dimension_resize || ($file_size > $max_file_size);
                        
                        if ($needs_resize) {
                            $editor = wp_get_image_editor($file_path);
                            if (!is_wp_error($editor)) {
                                // Calculate new dimensions
                                if ($needs_dimension_resize) {
                                    // Resize based on dimensions
                                    if ($orig_width > $orig_height) {
                                        $new_width = $max_size;
                                        $new_height = intval(($orig_height / $orig_width) * $max_size);
                                    } else {
                                        $new_height = $max_size;
                                        $new_width = intval(($orig_width / $orig_height) * $max_size);
                                    }
                                    $editor->resize($new_width, $new_height, false);
                                } else {
                                    // Dimensions are small but file is large - just recompress
                                    // Don't resize, just save with lower quality
                                }
                                
                                // Set quality for good detail while keeping file size reasonable
                                if (method_exists($editor, 'set_quality')) {
                                    $editor->set_quality(75); // Good quality for AI analysis
                                }
                                
                                $upload_dir = wp_upload_dir();
                                $temp_filename = 'alttext-ai-temp-' . $image_id . '-' . time() . '.jpg';
                                $temp_path = $upload_dir['path'] . '/' . $temp_filename;
                                $saved = $editor->save($temp_path, 'image/jpeg');
                                
                                if (!is_wp_error($saved) && isset($saved['path'])) {
                                    $resized_contents = file_get_contents($saved['path']);
                                    $resized_size = strlen($resized_contents);
                                    @unlink($saved['path']);
                                    
                                    if ($resized_contents !== false) {
                                        $base64 = base64_encode($resized_contents);
                                        $base64_size = strlen($base64);
                                        
                                        // Log resize result for debugging
                                        if (class_exists('AltText_AI_Debug_Log')) {
                                            AltText_AI_Debug_Log::log('debug', 'Image resize completed', [
                                                'image_id' => $image_id,
                                                'original_size' => round($file_size / 1024 / 1024, 2) . 'MB',
                                                'resized_size' => round($resized_size / 1024 / 1024, 2) . 'MB',
                                                'base64_size' => round($base64_size / 1024 / 1024, 2) . 'MB',
                                                'max_size' => $max_size,
                                            ], 'api');
                                        }
                                        
                                        // Check if encoded size is acceptable (max ~1.8MB, backend supports 2MB)
                                        if ($base64_size <= 1.8 * 1024 * 1024) {
                                            $payload['image_base64'] = $base64;
                                            $payload['mime_type'] = 'image/jpeg';
                                            // Don't send image_url when sending base64
                                        } else {
                                            // Still too large after first resize - try smaller at 384px
                                            $editor2 = wp_get_image_editor($file_path);
                                            if (!is_wp_error($editor2)) {
                                                // Resize to 384px max dimension
                                                if ($orig_width > $orig_height) {
                                                    $editor2->resize(384, intval(($orig_height / $orig_width) * 384), false);
                                                } else {
                                                    $editor2->resize(intval(($orig_width / $orig_height) * 384), 384, false);
                                                }
                                                if (method_exists($editor2, 'set_quality')) {
                                                    $editor2->set_quality(70); // Still good quality
                                                }
                                                $saved2 = $editor2->save($temp_path, 'image/jpeg');
                                                if (!is_wp_error($saved2) && isset($saved2['path'])) {
                                                    $final_contents = file_get_contents($saved2['path']);
                                                    @unlink($saved2['path']);
                                                    if ($final_contents !== false) {
                                                        $final_base64 = base64_encode($final_contents);
                                                        if (strlen($final_base64) <= 1.8 * 1024 * 1024) {
                                                            $payload['image_base64'] = $final_base64;
                                                            $payload['mime_type'] = 'image/jpeg';
                                                        } else {
                                                            // Image is STILL too large - mark error
                                                            $payload['_error'] = 'image_too_large';
                                                            $payload['_error_message'] = __('Image file is too large even after compression. Please manually optimize the image.', 'wp-alt-text-plugin');
                                                        }
                                                    } else {
                                                        $payload['_error'] = 'image_too_large';
                                                        $payload['_error_message'] = __('Failed to read compressed image.', 'wp-alt-text-plugin');
                                                    }
                                                } else {
                                                    $payload['_error'] = 'image_too_large';
                                                    $payload['_error_message'] = __('Failed to save compressed image.', 'wp-alt-text-plugin');
                                                }
                                            } else {
                                                $payload['_error'] = 'image_too_large';
                                                $payload['_error_message'] = __('Failed to create image editor.', 'wp-alt-text-plugin');
                                            }
                                        }
                                    } else {
                                        $payload['image_url'] = $image_url;
                                    }
                                } else {
                                    // Resize failed - log and fallback to URL
                                    if (class_exists('AltText_AI_Debug_Log')) {
                                        AltText_AI_Debug_Log::log('warning', 'Image resize failed', [
                                            'image_id' => $image_id,
                                            'error' => is_wp_error($saved) ? $saved->get_error_message() : 'Unknown error',
                                        ], 'api');
                                    }
                                    $payload['image_url'] = $image_url;
                                }
                            } else {
                                // Editor creation failed
                                if (class_exists('AltText_AI_Debug_Log')) {
                                    AltText_AI_Debug_Log::log('warning', 'Image editor creation failed', [
                                        'image_id' => $image_id,
                                        'error' => $editor->get_error_message(),
                                    ], 'api');
                                }
                                $payload['image_url'] = $image_url;
                            }
                        } else {
                            // Image dimensions are small but file size is large (high quality)
                            // Try to recompress it
                            $editor = wp_get_image_editor($file_path);
                            if (!is_wp_error($editor)) {
                                if (method_exists($editor, 'set_quality')) {
                                    $editor->set_quality(75); // Good quality
                                }
                                
                                $upload_dir = wp_upload_dir();
                                $temp_filename = 'alttext-ai-temp-' . $image_id . '-' . time() . '.jpg';
                                $temp_path = $upload_dir['path'] . '/' . $temp_filename;
                                $saved = $editor->save($temp_path, 'image/jpeg');
                                
                                if (!is_wp_error($saved) && isset($saved['path'])) {
                                    $compressed_contents = file_get_contents($saved['path']);
                                    @unlink($saved['path']);
                                    if ($compressed_contents !== false) {
                                        $base64 = base64_encode($compressed_contents);
                                        if (strlen($base64) <= 1.8 * 1024 * 1024) {
                                            $payload['image_base64'] = $base64;
                                            $payload['mime_type'] = 'image/jpeg';
                                        } else {
                                            // Still too large after recompression - mark error
                                            $payload['_error'] = 'image_too_large';
                                            $payload['_error_message'] = __('Image file is too large even after compression. Please manually optimize the image.', 'wp-alt-text-plugin');
                                        }
                                    } else {
                                        $payload['image_url'] = $image_url;
                                    }
                                } else {
                                    $payload['image_url'] = $image_url;
                                }
                            } else {
                                $payload['image_url'] = $image_url;
                            }
                        }
                    } else {
                        // Small image, send URL
                        $payload['image_url'] = $image_url;
                    }
                } else {
                    // No file path, send URL
                    $payload['image_url'] = $image_url;
                }
            }
        }
        
        return $payload;
    }

    /**
     * Sanitize context data before logging to prevent exposing sensitive information
     */
    private function sanitize_log_context($context) {
        if (!is_array($context)) {
            return $context;
        }
        
        $sanitized = [];
        $sensitive_keys = ['url', 'token', 'api_key', 'password', 'secret', 'authorization', 'auth', 'bearer'];
        
        foreach ($context as $key => $value) {
            $key_lower = strtolower($key);
            
            // Skip sensitive keys entirely
            foreach ($sensitive_keys as $sensitive) {
                if (strpos($key_lower, $sensitive) !== false) {
                    $sanitized[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            
            // Sanitize URLs - only show endpoint path, not full URL
            if ($key === 'url' && is_string($value)) {
                // Extract just the endpoint path, remove base URL
                $parsed = parse_url($value);
                if (isset($parsed['path'])) {
                    $sanitized[$key] = $parsed['path'] . (isset($parsed['query']) ? '?' . '[QUERY_PARAMS]' : '');
                } else {
                    $sanitized[$key] = '[REDACTED_URL]';
                }
            }
            // Sanitize endpoint - remove query parameters
            elseif ($key === 'endpoint' && is_string($value)) {
                $sanitized[$key] = strtok($value, '?');
            }
            // Sanitize error messages - remove potential sensitive data
            elseif ($key === 'error' && is_string($value)) {
                // Remove any URLs, tokens, or API keys from error messages
                $sanitized[$key] = preg_replace(
                    [
                        '/https?:\/\/[^\s]+/i',  // Remove URLs
                        '/Bearer\s+[A-Za-z0-9\-_]+/i',  // Remove Bearer tokens
                        '/token[=:]\s*[A-Za-z0-9\-_]+/i',  // Remove token values
                        '/api[_-]?key[=:]\s*[A-Za-z0-9\-_]+/i',  // Remove API keys
                    ],
                    '[REDACTED]',
                    $value
                );
            }
            // Recursively sanitize nested arrays
            elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize_log_context($value);
            }
            // Keep other values as-is
            else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    private function log_api_event($level, $message, $context = []) {
        if (class_exists('AltText_AI_Debug_Log')) {
            // Sanitize context before logging
            $sanitized_context = $this->sanitize_log_context($context);
            AltText_AI_Debug_Log::log($level, $message, $sanitized_context, 'api');
        }
    }
}
