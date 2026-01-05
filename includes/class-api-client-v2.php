<?php
/**
 * API Client for AltText AI Phase 2
 * Handles JWT authentication and communication with the Phase 2 API
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) { exit; }

class API_Client_V2 {
    /**
     * Helper to make requests with retry/backoff for transient failures.
     */
    protected function request_with_retry($endpoint, $method = 'GET', $data = null, $max_attempts = 3, $include_user_id = false, $extra_headers = []) {
        $attempt = 0;
        $last_error = null;

        while ($attempt < $max_attempts) {
            $response = $this->make_request($endpoint, $method, $data, null, $include_user_id, $extra_headers);
            if (!is_wp_error($response)) {
                if ($attempt > 0 && class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    \BeepBeepAI\AltTextGenerator\Debug_Log::log('info', 'API request recovered after retry', [
                        'endpoint' => $endpoint,
                        'attempt' => $attempt + 1,
                    ], 'api');
                }
                return $response;
            }

            if (!$this->should_retry_api_error($response)) {
                return $response;
            }

            $attempt++;
            $last_error = $response;

            if ($attempt < $max_attempts) {
                $delay = min(3, $attempt); // 1s, 2s
                if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                    \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Retrying API request after transient failure', [
                        'endpoint'    => $endpoint,
                        'attempt'     => $attempt + 1,
                        'error_code'  => $response->get_error_code(),
                        'status_code' => $response->get_error_data()['status_code'] ?? null,
                    ], 'api');
                }
                usleep($delay * 1000000);
            }
        }

        return $last_error ?: new \WP_Error('api_error', __('Unknown API error', 'beepbeep-ai-alt-text-generator'));
    }

    
    private $api_url;
    private $token_option_key = 'beepbeepai_jwt_token';
    private $user_option_key = 'beepbeepai_user_data';
    private $site_id_option_key = 'beepbeepai_site_id';
    private $license_key_option_key = 'beepbeepai_license_key';
    private $license_data_option_key = 'beepbeepai_license_data';
    private $encryption_prefix = 'enc:';
    
    public function __construct() {
        // Check for local development override first
        if (defined('BEEPBEEP_AI_API_URL')) {
            $this->api_url = BEEPBEEP_AI_API_URL;
        } else {
            // ALWAYS use production API URL
            $production_url = 'https://alttext-ai-backend.onrender.com';
            $this->api_url = $production_url;

            // Force update WordPress settings to production (clean up legacy configs)
            $options = get_option('beepbeepai_settings', []);
            if ($options === false || $options === null) {
                $options = get_option('beepbeepai_settings', []);
                if ($options === false || $options === null) {
                    $options = get_option('beepbeepai_settings', []);
                }
            }
            $options = is_array($options) ? $options : [];
            if (!isset($options['api_url']) || $options['api_url'] !== $production_url) {
                $options['api_url'] = $production_url;
                update_option('beepbeepai_settings', $options);
            }
        }
    }
    
    /**
     * Get stored JWT token
     */
    protected function get_token() {
        $token = get_option($this->token_option_key, '');
        if ($token === '' || $token === false) {
            $legacy = get_option('beepbeepai_jwt_token', '');
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
        delete_option('beepbeepai_jwt_token');
        delete_option($this->user_option_key);
        delete_option('beepbeepai_user_data');
        delete_transient('bbai_token_last_check');
    }
    
    /**
     * Get stored user data
     */
    public function get_user_data() {
        $data = get_option($this->user_option_key, null);
        if (($data === false || $data === null)) {
            $legacy = get_option('beepbeepai_user_data', null);
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
        $last_check = get_transient('bbai_token_last_check');
        $should_validate = $last_check === false;

        if ($should_validate) {
            // Try to fetch user info to validate token
            $user_info = $this->get_user_info();

            if (is_wp_error($user_info)) {
                $error_code = $user_info->get_error_code();
                $error_message = strtolower($user_info->get_error_message());

                // Only clear token if it's definitely invalid (not a temporary server error)
                // Don't clear on server errors (500), network errors, or timeouts
                $error_message_str = is_string($error_message) ? $error_message : '';
                if ($error_code === 'auth_required' ||
                    $error_code === 'user_not_found' ||
                    ($error_message_str && strpos($error_message_str, 'user not found') !== false) ||
                    ($error_message_str && strpos($error_message_str, 'session expired') !== false) ||
                    ($error_message_str && strpos($error_message_str, 'unauthorized') !== false)) {
                    // Token is definitely invalid, clear it
                    $this->clear_token();
                    return false;
                }
                // For server errors, network issues, etc., don't clear token
                // Cache a temporary success to avoid hammering the API on every request
                // This gives the benefit of the doubt during temporary outages
                set_transient('bbai_token_last_check', time(), 2 * MINUTE_IN_SECONDS);
            } else {
                // Token is valid, cache result for 5 minutes
                set_transient('bbai_token_last_check', time(), 5 * MINUTE_IN_SECONDS);
            }
        }

        return true;
    }
    
    /**
     * Get or generate unique site ID
     * This ensures quotas are tracked per-site, not per-user
     * 
     * @return string Site identifier
     */
    public function get_site_id() {
        // Use the centralized helper function
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        return \BeepBeepAI\AltTextGenerator\get_site_identifier();
    }
    
    /**
     * Get authentication headers
     * Includes license key or JWT token, plus site info
     */
    private function get_auth_headers($include_user_id = false, $extra_headers = []) {
        $token = $this->get_token();
        $license_key = $this->get_license_key();
        $site_id = $this->get_site_id();

        // Get site fingerprint for abuse prevention
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
        $fingerprint = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::get_fingerprint();

        $headers = [
            'Content-Type' => 'application/json',
            'X-Site-Hash' => $site_id,  // Site-based licensing identifier
            'X-Site-URL' => get_site_url(),  // For backend reference
            'X-Site-Fingerprint' => $fingerprint,  // Site fingerprint for abuse prevention
        ];

        // Include current user ID if requested (for analytics)
        if ($include_user_id) {
            $user_id = get_current_user_id();
            if ($user_id > 0) {
                $headers['X-WP-User-ID'] = (string) $user_id;
            }
        }

        // Priority: License key > JWT token
        if (!empty($license_key)) {
            $headers['X-License-Key'] = $license_key;
        } elseif ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // Merge any caller-provided headers (used for debugging/traceability)
        if (!empty($extra_headers) && is_array($extra_headers)) {
            $headers = array_merge($headers, $extra_headers);
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
    private function make_request($endpoint, $method = 'GET', $data = null, $timeout = null, $include_user_id = false, $extra_headers = []) {
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        $headers = $this->get_auth_headers($include_user_id, $extra_headers);
        
        // Use longer timeout for generation requests (OpenAI can take time)
        if ($timeout === null) {
            // Check for generate endpoint (with or without leading slash)
            $endpoint_str = is_string($endpoint) ? $endpoint : '';
            $is_generate_endpoint = $endpoint_str && ((strpos($endpoint_str, '/api/generate') !== false) || (strpos($endpoint_str, 'api/generate') !== false));
            $timeout = $is_generate_endpoint ? 90 : 30;
        }
        
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => true, // Explicitly verify SSL certificates (WordPress.org requirement)
        ];
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            // Ensure data is an array before encoding
            if (!is_array($data)) {
                $this->log_api_event('error', 'Request data is not an array', [
                    'endpoint' => $endpoint,
                    'data_type' => gettype($data),
                ]);
                return new \WP_Error('invalid_data', __('Request data must be an array', 'beepbeep-ai-alt-text-generator'));
            }
            
            // Clean data to ensure it's JSON-encodable (remove any non-serializable values)
            $clean_data = $this->clean_json_data($data);
            
            // Encode to JSON with error handling
            $json_body = wp_json_encode($clean_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            if ($json_body === false || $json_body === null) {
                $json_error = json_last_error_msg();
                $this->log_api_event('error', 'JSON encoding failed', [
                    'endpoint' => $endpoint,
                    'json_error' => $json_error,
                    'data_keys' => array_keys($clean_data),
                ]);
                return new \WP_Error('json_encode_error', sprintf(__('Failed to encode request data: %s', 'beepbeep-ai-alt-text-generator'), $json_error));
            }
            
            // Validate JSON is not empty
            if (empty($json_body) || $json_body === '{}' || $json_body === '[]') {
                $this->log_api_event('error', 'JSON body is empty or invalid', [
                    'endpoint' => $endpoint,
                    'json_body' => $json_body,
                ]);
                return new \WP_Error('empty_json', __('Request body cannot be empty', 'beepbeep-ai-alt-text-generator'));
            }
            
            // Clean JSON: remove BOM, trim whitespace, ensure it starts with valid JSON character
            $json_body = trim($json_body);
            // Remove UTF-8 BOM if present
            if (substr($json_body, 0, 3) === "\xEF\xBB\xBF") {
                $json_body = substr($json_body, 3);
            }
            // Ensure JSON starts with { or [
            if (!preg_match('/^[{\[]/', $json_body)) {
                $this->log_api_event('error', 'JSON body does not start with valid JSON character', [
                    'endpoint' => $endpoint,
                    'json_start' => substr($json_body, 0, 10),
                ]);
                return new \WP_Error('invalid_json_format', __('JSON body format is invalid', 'beepbeep-ai-alt-text-generator'));
            }
            
            $args['body'] = $json_body;
            
            // Log body preview for debugging (first 200 chars)
            $this->log_api_event('debug', 'Request body prepared', [
                'endpoint' => $endpoint,
                'body_length' => strlen($json_body),
                'body_preview' => substr($json_body, 0, 200),
            ]);
        }
        
        $this->log_api_event('debug', 'API request started', [
            'endpoint' => $endpoint,
            'method'   => $method,
            'url'      => $url,
            'api_host' => parse_url($url, PHP_URL_HOST) ?: '',
            'api_path' => parse_url($url, PHP_URL_PATH) ?: '',
            'headers'  => array_keys($headers), // Log header names only (not values for security)
            'has_body' => isset($args['body']),
            'body_size' => isset($args['body']) ? strlen($args['body']) : 0,
        ]);

        $status_code = null;
        $body = null;
        $data = null;

        // Use WordPress HTTP API exclusively (WordPress.org requirement)
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $error_message = is_string($error_message) ? $error_message : '';
            $this->log_api_event('error', 'API request failed', [
                'endpoint' => $endpoint,
                'method'   => $method,
                'error'    => $error_message,
            ]);
            $error_message_str = is_string($error_message) ? $error_message : '';
            if ($error_message_str && strpos($error_message_str, 'timeout') !== false) {
                // Provide more specific message for generation timeouts
                $endpoint_str = is_string($endpoint) ? $endpoint : '';
                $is_generate_endpoint = $endpoint_str && ((strpos($endpoint_str, '/api/generate') !== false) || (strpos($endpoint_str, 'api/generate') !== false));
                if ($is_generate_endpoint) {
                    return new \WP_Error('api_timeout', __('The image generation is taking longer than expected. This may happen with large images or during high server load. Please try again.', 'beepbeep-ai-alt-text-generator'));
                }
                return new \WP_Error('api_timeout', __('The server is taking too long to respond. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator'));
            } elseif ($error_message_str && strpos($error_message_str, 'could not resolve') !== false) {
                return new \WP_Error('api_unreachable', __('Unable to reach authentication server. Please check your internet connection and try again.', 'beepbeep-ai-alt-text-generator'));
            }
            return new \WP_Error('api_error', $error_message ?: __('API request failed', 'beepbeep-ai-alt-text-generator'));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $log_context = [
            'endpoint' => $endpoint,
            'method'   => $method,
            'status'   => $status_code,
        ];
        if ($status_code >= 400) {
            $log_context['body_preview'] = is_string($body) ? substr($body, 0, 300) : '';
        }
        $this->log_api_event($status_code >= 400 ? 'warning' : 'debug', 'API response received', $log_context);
        
        // Handle authentication errors FIRST (401/403) - these are more specific than 404
        if ($status_code === 401 || $status_code === 403) {
            $body_str = is_string($body) ? $body : '';
            $data_array = is_array($data) ? $data : [];
            
            // Check if response indicates license key is required
            $error_message_lower = '';
            if (isset($data_array['message'])) {
                $error_message_lower = strtolower(is_string($data_array['message']) ? $data_array['message'] : '');
            } elseif (isset($data_array['error'])) {
                $error_message_lower = strtolower(is_string($data_array['error']) ? $data_array['error'] : '');
            } elseif ($body_str) {
                $error_message_lower = strtolower($body_str);
            }
            
            $is_license_error = (
                strpos($error_message_lower, 'license') !== false ||
                strpos($error_message_lower, 'invalid_license') !== false ||
                strpos($error_message_lower, 'license key required') !== false ||
                (isset($data_array['error']) && strtolower($data_array['error']) === 'invalid_license')
            );
            
            if ($is_license_error) {
                // License key is required or invalid
                $license_key = $this->get_license_key();
                if (empty($license_key)) {
                    return new \WP_Error(
                        'license_required',
                        __('License key is required to generate alt text. Please activate your license key in the Settings page.', 'beepbeep-ai-alt-text-generator'),
                        [
                            'status_code' => $status_code,
                            'requires_license' => true,
                            'action' => 'activate_license'
                        ]
                    );
                } else {
                    return new \WP_Error(
                        'invalid_license',
                        __('Your license key is invalid or expired. Please check your license key in the Settings page.', 'beepbeep-ai-alt-text-generator'),
                        [
                            'status_code' => $status_code,
                            'requires_license' => true,
                            'action' => 'check_license'
                        ]
                    );
                }
            }
            
            // Generic authentication error
            return new \WP_Error(
                'auth_required',
                __('Authentication required. Please log in to continue.', 'beepbeep-ai-alt-text-generator'),
                ['status_code' => $status_code, 'requires_auth' => true]
            );
        }
        
        // Handle 404 - endpoint not found (check AFTER auth errors)
        if ($status_code === 404) {
            // Check if it's an HTML error page (means endpoint doesn't exist)
            $body_str = is_string($body) ? $body : '';
            $endpoint_str = is_string($endpoint) ? $endpoint : '';
            if ($body_str && (strpos($body_str, '<html') !== false || strpos($body_str, 'Cannot POST') !== false || strpos($body_str, 'Cannot GET') !== false)) {
                // Provide context-specific error messages
                $error_message = __('This feature is not yet available. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator');
                
                // Check endpoint to provide more specific message
                if ($endpoint_str && (strpos($endpoint_str, '/auth/forgot-password') !== false || strpos($endpoint_str, '/auth/reset-password') !== false)) {
                    $error_message = __('Password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator');
                } elseif ($endpoint_str && (strpos($endpoint_str, '/licenses/sites') !== false || strpos($endpoint_str, '/api/licenses/sites') !== false)) {
                    $error_message = __('License site usage tracking is currently being set up on our backend. Please contact support for assistance or try again later.', 'beepbeep-ai-alt-text-generator');
                }
                
                return new \WP_Error(
                    'endpoint_not_found',
                    $error_message
                );
            }
            return new \WP_Error(
                'not_found',
                $data['error'] ?? $data['message'] ?? __('The requested resource was not found.', 'beepbeep-ai-alt-text-generator')
            );
        }
        
        // Handle server errors
        if ($status_code >= 500) {
            // Log the actual response body for debugging
            $error_details = '';
            if (is_array($data) && isset($data['error'])) {
                $error_details = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
            } elseif (is_array($data) && isset($data['message'])) {
                $error_details = is_string($data['message']) ? $data['message'] : '';
            } elseif (!empty($body) && strlen($body) < 500) {
                // Only include body if it's not too long
                $error_details = is_string($body) ? $body : '';
            }
            
            $this->log_api_event('error', 'API server error', [
                'endpoint' => $endpoint,
                'method'   => $method,
                'status'   => $status_code,
                'error_details' => $error_details,
                'body_preview' => is_string($body) ? substr($body, 0, 200) : '',
            ]);
            
            // Try to extract more specific error from response body
            $backend_error_code = '';
            $backend_error_message = '';
            
            if (is_array($data)) {
                $backend_error_code = is_string($data['code'] ?? '') ? $data['code'] : '';
                $backend_error_message = is_string($data['message'] ?? $data['error'] ?? '') ? ($data['message'] ?? $data['error'] ?? '') : '';
            }
            
            // Check for "User not found" errors first (these come back as 500 sometimes)
            // Exception: For checkout endpoints, don't clear token here - let create_checkout_session() handle retry without token
            $endpoint_str = is_string($endpoint) ? $endpoint : '';
            $is_checkout_endpoint = strpos($endpoint_str, '/billing/checkout') !== false;
            
            $error_details_str = is_string($error_details) ? $error_details : '';
            $backend_error_message_str = is_string($backend_error_message) ? $backend_error_message : '';
            $error_details_lower = strtolower($error_details_str . ' ' . $backend_error_message_str);
            if (strpos($error_details_lower, 'user not found') !== false || 
                strpos($error_details_lower, 'user does not exist') !== false ||
                (is_array($data) && isset($data['code']) && is_string($data['code']) && strpos(strtolower($data['code']), 'user_not_found') !== false)) {
                
                // For checkout, return error without clearing token so it can retry without auth
                if ($is_checkout_endpoint) {
                    return new \WP_Error(
                        'user_not_found',
                        __('User not found', 'beepbeep-ai-alt-text-generator'),
                        [
                            'requires_auth' => false,
                            'status_code' => $status_code,
                            'code' => 'user_not_found',
                            'backend_message' => $backend_error_message_str,
                            'error_details' => $error_details_str,
                        ]
                    );
                }
                
                // For other endpoints, clear token and return auth_required error
                $this->clear_token();
                delete_transient('bbai_token_last_check');
                return new \WP_Error(
                    'auth_required',
                    __('Your session has expired or your account is no longer available. Please log in again.', 'beepbeep-ai-alt-text-generator'),
                    [
                        'requires_auth' => true,
                        'status_code' => $status_code,
                        'code' => 'user_not_found',
                    ]
                );
            }
            
            // Provide more specific error message based on endpoint and error details
            $error_message = __('The server encountered an error processing your request. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
            
            // Check for generate endpoint (with or without leading slash)
            $endpoint_str = is_string($endpoint) ? $endpoint : '';
            $is_generate_endpoint = $endpoint_str && ((strpos($endpoint_str, '/api/generate') !== false) || (strpos($endpoint_str, 'api/generate') !== false));
            $is_checkout_endpoint = strpos($endpoint_str, '/billing/checkout') !== false;
            
            if ($is_checkout_endpoint) {
                // For checkout, provide a more user-friendly error message
                // The backend is having issues with checkout - likely needs authentication fix
                $error_message = __('Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator');
            } elseif ($is_generate_endpoint) {
                // Check if it's an OpenAI API key issue (backend configuration problem)
                $error_details_str = is_string($error_details) ? $error_details : '';
                $backend_error_code_str = is_string($backend_error_code) ? $backend_error_code : '';
                if (($error_details_str && (strpos(strtolower($error_details_str), 'incorrect api key') !== false || 
                    strpos(strtolower($error_details_str), 'invalid api key') !== false)) ||
                    ($backend_error_code_str && strpos(strtolower($backend_error_code_str), 'generation_error') !== false)) {
                    $error_message = __('The image generation service is temporarily unavailable due to a backend configuration issue. Please contact support.', 'beepbeep-ai-alt-text-generator');
                } else {
                    $error_message = __('The image generation service is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
                }
            } elseif (is_string($endpoint) && strpos($endpoint, '/auth/') !== false) {
                $error_message = __('The authentication server is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
            }
            
            return new \WP_Error(
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
        
        // Note: Authentication errors (401/403) are now handled earlier, before 404 checks
        // This ensures license key errors are detected and reported correctly
        
        return [
            'status_code' => $status_code,
            'data' => $data,
            'success' => $status_code >= 200 && $status_code < 300
        ];
    }

    /**
     * Determine whether a request should be retried.
     */
    private function should_retry_api_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }

        $retryable_codes = ['server_error', 'api_timeout', 'api_unreachable'];
        $code = $error->get_error_code();
        if (!in_array($code, $retryable_codes, true)) {
            return false;
        }

        $data = $error->get_error_data();
        $status = isset($data['status_code']) ? intval($data['status_code']) : 0;

        // Retry network errors without status codes, and HTTP 5xx responses.
        if ($status === 0) {
            return true;
        }

        return in_array($status, [500, 502, 503, 504], true);
    }
    
    /**
     * Register new user
     * Includes site_id to prevent multiple free accounts per site
     */
    public function register($email, $password) {
        // Check if site already has an account
        $existing_token = $this->get_token();
        if (!empty($existing_token)) {
            // Site already has an account - check if it's a free plan
            $usage = $this->get_usage();
            if (!is_wp_error($usage) && isset($usage['plan']) && $usage['plan'] === 'free') {
                return new \WP_Error(
                    'free_plan_exists',
                    __('A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator'),
                    ['code' => 'free_plan_already_used']
                );
            }
        }
        
        // Get site identifier
        $site_id = $this->get_site_id();
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        $site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
        
        $response = $this->make_request('/auth/register', 'POST', [
            'email' => $email,
            'password' => $password,
            'site_id' => $site_id,
            'site_url' => get_site_url(),
            'plugin_version' => defined('BBAI_VERSION') ? BBAI_VERSION : '1.0.0',
            'wordpress_version' => get_bloginfo('version'),
        ]);
        
        if (is_wp_error($response)) {
            // Check for "free plan already used" error from backend
            $error_message = strtolower($response->get_error_message());
            if (strpos($error_message, 'free plan') !== false || 
                strpos($error_message, 'already used') !== false ||
                $response->get_error_code() === 'free_plan_exists') {
                return new \WP_Error(
                    'free_plan_exists',
                    __('A free plan has already been used for this site. Upgrade to Pro or Agency to increase your quota.', 'beepbeep-ai-alt-text-generator'),
                    ['code' => 'free_plan_already_used']
                );
            }
            return $response;
        }
        
        if ($response['success'] && isset($response['data']['token'])) {
            $this->set_token($response['data']['token']);
            $this->set_user_data($response['data']['user']);
            return $response['data'];
        }
        
        return new \WP_Error(
            'registration_failed',
            $response['data']['error'] ?? __('Registration failed', 'beepbeep-ai-alt-text-generator')
        );
    }
    
    /**
     * Login user
     * Includes site_id for account linking
     */
    public function login($email, $password) {
        // Get site identifier
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/helpers-site-id.php';
        $site_id = \BeepBeepAI\AltTextGenerator\get_site_identifier();
        
        $response = $this->make_request('/auth/login', 'POST', [
            'email' => $email,
            'password' => $password,
            'site_id' => $site_id,
            'site_url' => get_site_url(),
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['success'] && isset($response['data']['token'])) {
            $this->set_token($response['data']['token']);
            $this->set_user_data($response['data']['user']);
            return $response['data'];
        }
        
        return new \WP_Error(
            'login_failed',
            $response['data']['error'] ?? __('Login failed', 'beepbeep-ai-alt-text-generator')
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

        return new \WP_Error(
            'user_info_failed',
            $response['data']['error'] ?? __('Failed to get user info', 'beepbeep-ai-alt-text-generator')
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
            'pluginVersion' => defined('BBAI_VERSION') ? BBAI_VERSION : '1.0.0',
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

        return new \WP_Error(
            'license_activation_failed',
            $response['error'] ?? __('Failed to activate license', 'beepbeep-ai-alt-text-generator')
        );
    }

    /**
     * Deactivate current license key
     */
    public function deactivate_license() {
        $this->clear_license_key();

        return [
            'success' => true,
            'message' => __('License deactivated successfully', 'beepbeep-ai-alt-text-generator')
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
            return new \WP_Error('not_authenticated', __('Must be authenticated or have an active license to view license site usage', 'beepbeep-ai-alt-text-generator'));
        }

        $response = $this->make_request('/api/licenses/sites', 'GET');
        
        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data'] ?? ['sites' => []];
        }

        return new \WP_Error('api_error', $response['message'] ?? __('Failed to fetch license site usage', 'beepbeep-ai-alt-text-generator'));
    }

    /**
     * Disconnect a site from the license
     * Removes the installation from the backend
     */
    public function disconnect_license_site($site_id) {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Must be authenticated to disconnect license sites', 'beepbeep-ai-alt-text-generator'));
        }

        $response = $this->make_request('/api/licenses/sites/' . urlencode($site_id), 'DELETE');
        
        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data'] ?? ['message' => __('Site disconnected successfully', 'beepbeep-ai-alt-text-generator')];
        }

        return new \WP_Error('api_error', $response['message'] ?? __('Failed to disconnect site', 'beepbeep-ai-alt-text-generator'));
    }

    /**
     * Get usage information
     */
    public function get_usage() {
        $has_license   = $this->has_active_license();
        $license_cache = $has_license ? $this->get_license_data() : null;

        $response = $this->make_request('/usage');

        if (is_wp_error($response)) {
            // If API call fails, try to use cached usage as fallback
            if ($has_license && $license_cache) {
                $cached_usage = $this->format_license_usage_from_cache($license_cache);
                if ($cached_usage) {
                    return $cached_usage;
                }
            }
            
            // For non-license accounts, try cached usage as fallback
            if (!$has_license) {
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
                // Only use cached usage if it's a valid array with remaining credits >= 0
                // This ensures we have valid data before falling back to cache
                if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] >= 0) {
                    // Return cached usage if available
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
            } else {
                // Update usage cache for non-license accounts
                require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
                \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($usage);
            }

            return $usage;
        }

        if ($has_license && $license_cache) {
            $cached_usage = $this->format_license_usage_from_cache($license_cache);
            if ($cached_usage) {
                return $cached_usage;
            }
        }
        
        // For non-license accounts, try cached usage as fallback
        if (!$has_license) {
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
            $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
            // Only use cached usage if it's a valid array with remaining credits >= 0
            if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] >= 0) {
                // Return cached usage if available
                return $cached_usage;
            }
        }

        return new \WP_Error(
            'usage_failed',
            $response['data']['error'] ?? __('Failed to get usage info', 'beepbeep-ai-alt-text-generator')
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

        try {
            // ALWAYS check cached usage first - it's more reliable than API calls
            // This prevents false "limit reached" errors when API is slow/unreliable
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
            $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
            
            // If cached usage is valid and shows credits available, don't block
            if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining'])) {
                $remaining = intval($cached_usage['remaining']);
                // Only block if cached usage explicitly shows 0 remaining
                if ($remaining > 0) {
                    return false; // Cached shows credits available - don't block
                }
                if ($remaining === 0) {
                    return true; // Cached shows 0 - block generation
                }
            }
            
            // If no valid cached usage, try API as fallback
            $usage = $this->get_usage();

            // If there was an error getting usage, check if it's an auth error
            // For auth errors, don't assume limit reached - allow generation to proceed
            // Backend will handle usage limits during processing
            if (is_wp_error($usage)) {
                $error_code = $usage->get_error_code();
                
                // If it's an auth/user error, don't block generation - backend will handle it
                if ($error_code === 'auth_required' || $error_code === 'user_not_found') {
                    return false; // Don't block on auth errors
                }
                
                // For other errors and no cached usage, allow generation to proceed
                // Backend will enforce limits, and we don't want to block unnecessarily
                return false;
            }

            // Check if remaining is 0 or less from API response
            // Make sure remaining is numeric before comparing
            if (isset($usage['remaining']) && is_numeric($usage['remaining'])) {
                $remaining = intval($usage['remaining']);
                // Only block if API explicitly shows 0 remaining AND no cached usage contradicted it
                if ($remaining === 0 && (!is_array($cached_usage) || !isset($cached_usage['remaining']) || intval($cached_usage['remaining']) === 0)) {
                    return true; // API and cache both show 0 - block
                }
                if ($remaining > 0) {
                    return false; // API shows credits available - don't block
                }
            }
            
            // If we can't determine usage from API or cache, don't block - let backend handle it
            return false;
        } catch ( \Exception $e ) {
            // If anything throws an exception, check cached usage as fallback
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
            $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
            
            // Check if cached usage is valid and shows credits available
            if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] > 0) {
                // Cached usage shows credits available - don't block
                return false;
            }
            
            // If cached usage shows 0 remaining, respect that and block
            if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] === 0) {
                // Cached usage shows 0 credits - block generation
                return true;
            }
            
            // No valid cached usage available - don't block on exceptions, let backend handle it
            return false;
        }
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
        // Validate authentication before making request
        // If we haven't checked recently and token exists, validate it first
        if (!$this->has_active_license()) {
            $token = $this->get_token();
            if (!empty($token) && !defined('WP_LOCAL_DEV')) {
                $last_check = get_transient('bbai_token_last_check');
                // If token check expired or never done, validate before generating
                if ($last_check === false) {
                    $user_info = $this->get_user_info();
                    if (is_wp_error($user_info)) {
                        $error_code = $user_info->get_error_code();
                        $error_message = strtolower($user_info->get_error_message());
                        
                        // Only clear token if it's definitely invalid (not a temporary server error)
                        if ($error_code === 'auth_required' || 
                            $error_code === 'user_not_found' ||
                            (strpos($error_message, 'user not found') !== false) ||
                            (strpos($error_message, 'session expired') !== false) ||
                            (strpos($error_message, 'unauthorized') !== false)) {
                            // Token is definitely invalid, clear it
                            $this->clear_token();
                            delete_transient('bbai_token_last_check');
                            return new \WP_Error(
                                'auth_required',
                                __('Your session has expired. Please log in again.', 'beepbeep-ai-alt-text-generator'),
                                ['requires_auth' => true]
                            );
                        }
                        // For server errors, network issues, etc., don't clear token
                        // Just proceed with generation attempt - backend will handle it
                    } else {
                        // Token is valid, cache for 5 minutes
                        set_transient('bbai_token_last_check', time(), 5 * MINUTE_IN_SECONDS);
                    }
                }
            }
        }
        
        // Validate site fingerprint before credit operation
        require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-site-fingerprint.php';
        $fingerprint_check = \BeepBeepAI\AltTextGenerator\Site_Fingerprint::check_on_credit_operation(false);
        if (is_wp_error($fingerprint_check)) {
            return $fingerprint_check;
        }
        
        $endpoint = 'api/generate';
        
        // CRITICAL: Log image_id being used for generation (to debug backend receiving wrong ID)
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'generate_alt_text called', [
                'image_id' => $image_id,
                'regenerate' => $regenerate,
                'endpoint' => $endpoint,
            ], 'api');
        }
        
        // Enrich context with useful metadata for higher fidelity
        $image_url = wp_get_attachment_url($image_id);
        $title     = get_the_title($image_id);
        $caption   = wp_get_attachment_caption($image_id);
        $filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

        // Debug: Log image details when regenerating to ensure correct image is being processed
        if ($regenerate && class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            // Get actual file path for verification
            $file_path = get_attached_file($image_id);
            $file_exists = $file_path && file_exists($file_path);
            $file_hash = $file_exists && function_exists('md5_file') ? md5_file($file_path) : 'unknown';
            
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('info', 'Regenerating alt text for image', [
                'image_id' => $image_id,
                'image_url' => $image_url,
                'file_path' => $file_path,
                'file_exists' => $file_exists,
                'file_hash' => substr($file_hash, 0, 8) . '...',
                'filename' => $filename,
                'title' => $title,
            ], 'api');
        }

        $image_payload = $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename);

        // Check if image preparation failed due to size
        if (isset($image_payload['_error']) && $image_payload['_error'] === 'image_too_large') {
            return new \WP_Error(
                'image_too_large',
                $image_payload['_error_message'] ?? __('Image file is too large.', 'beepbeep-ai-alt-text-generator'),
                ['image_id' => $image_id]
            );
        }
        
        // Check if image data is missing (critical error)
        if (isset($image_payload['_error']) && $image_payload['_error'] === 'missing_image_data') {
            return new \WP_Error(
                'missing_image_data',
                $image_payload['_error_message'] ?? __('Image data is missing. Cannot generate alt text.', 'beepbeep-ai-alt-text-generator'),
                ['image_id' => $image_id]
            );
        }
        
        // Debug: Log what's being sent to backend (always, not just on regenerate)
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            $log_level = $regenerate ? 'warning' : 'info';
            \BeepBeepAI\AltTextGenerator\Debug_Log::log($log_level, 'Sending image to backend API', [
                'image_id' => $image_id,
                'regenerate' => $regenerate,
                'has_image_url' => !empty($image_payload['image_url']),
                'has_image_base64' => !empty($image_payload['image_base64']),
                'image_url_full' => !empty($image_payload['image_url']) ? $image_payload['image_url'] : 'none',
                'image_url_preview' => !empty($image_payload['image_url']) ? substr($image_payload['image_url'], 0, 100) . '...' : 'none',
                'base64_length' => !empty($image_payload['image_base64']) ? strlen($image_payload['image_base64']) : 0,
                'payload_keys' => array_keys($image_payload),
                'image_id_in_payload' => !empty($image_payload['image_id']) ? $image_payload['image_id'] : 'missing',
            ], 'api');
        }

        // CRITICAL: Ensure image data is actually included in payload
        // If image_url or image_base64 is missing, log a warning
        if (empty($image_payload['image_url']) && empty($image_payload['image_base64'])) {
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'Image payload missing both image_url and image_base64', [
                    'image_id' => $image_id,
                    'payload_keys' => array_keys($image_payload),
                    'image_url_input' => $image_url,
                ], 'api');
            }
        }
        
        // CRITICAL: Ensure image_id in image_data payload matches the actual image_id
        // Backend might be reading image_id from image_data.image_id
        if (isset($image_payload['image_id']) && (string) $image_payload['image_id'] !== (string) $image_id) {
            $payload_image_id_before = $image_payload['image_id'] ?? 'missing';
            $image_payload['image_id'] = (string) $image_id;
            
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'Image ID mismatch detected and fixed', [
                    'expected_image_id' => $image_id,
                    'payload_image_id_before' => $payload_image_id_before,
                    'payload_image_id_after' => (string) $image_id,
                ], 'api');
            }
        }
        
        // Ensure attachment_id mirrors image_id for backward compatibility with backend parsers
        if (!isset($image_payload['attachment_id']) || (string) $image_payload['attachment_id'] !== (string) $image_id) {
            $payload_attachment_before = $image_payload['attachment_id'] ?? 'missing';
            $image_payload['attachment_id'] = (string) $image_id;

            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Attachment ID normalized on payload', [
                    'expected_attachment_id' => $image_id,
                    'payload_attachment_before' => $payload_attachment_before,
                    'payload_attachment_after' => (string) $image_id,
                ], 'api');
            }
        }
        
        // Log the exact payload being sent to verify image_id is correct
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'API Request Payload (BEFORE sending to backend)', [
                'image_id_param' => $image_id,
                'root_image_id_in_body' => (string) $image_id,
                'image_data.image_id' => $image_payload['image_id'] ?? 'missing',
                'image_data.attachment_id' => $image_payload['attachment_id'] ?? 'missing',
                'regenerate_flag' => $regenerate,
                'has_image_url' => !empty($image_payload['image_url']),
                'image_url_preview' => !empty($image_payload['image_url']) ? substr($image_payload['image_url'], 0, 100) : 'none',
                'timestamp' => time(),
            ], 'api');
        }
        
        $body = [
            'image_data' => $image_payload,
            'context' => $context,
            'regenerate' => $regenerate ? true : false, // Explicitly cast to boolean
            // Add timestamp and image_id to prevent caching issues
            'timestamp' => time(),
            'image_id' => (string) $image_id, // Include image_id at root level AND in image_data
            'attachment_id' => (string) $image_id, // Redundant identifier for backend parsers
        ];

        // Include user ID and explicit image identifiers in headers for traceability
        $extra_headers = [
            'X-Image-ID' => (string) $image_id,
            'X-Attachment-ID' => (string) $image_id,
        ];

        $response = $this->request_with_retry($endpoint, 'POST', $body, 3, true, $extra_headers);

        if (is_wp_error($response)) {
            return $response;
        }

        // Debug logging
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            \BeepBeepAI\AltTextGenerator\Debug_Log::log('debug', 'Generation API response', [
                'status_code' => $response['status_code'],
                'success' => $response['success'],
                'has_data' => isset($response['data']),
                'data_keys' => isset($response['data']) && is_array($response['data']) ? array_keys($response['data']) : 'not array'
            ], 'api');
        }

        // Handle rate limit
        if ($response['status_code'] === 429) {
            // DO NOT update usage from error responses - credits should only be deducted on success
            // The backend may have consumed credits during the failed attempt, but we shouldn't record it
            // Usage will be refreshed on successful generation
            
            // Before blocking, check cached usage - backend might be incorrectly reporting quota exhausted
            require_once BEEPBEEP_AI_PLUGIN_DIR . 'includes/class-usage-tracker.php';
            $cached_usage = \BeepBeepAI\AltTextGenerator\Usage_Tracker::get_cached_usage(false);
            
            // If cached usage shows credits available, verify with fresh API check
            if (is_array($cached_usage) && isset($cached_usage['remaining']) && is_numeric($cached_usage['remaining']) && $cached_usage['remaining'] > 0) {
                // Cached shows credits available - backend error might be incorrect
                // Do a fresh check to see actual status
                $fresh_usage = $this->get_usage();
                
                if (!is_wp_error($fresh_usage) && is_array($fresh_usage) && isset($fresh_usage['remaining']) && is_numeric($fresh_usage['remaining']) && $fresh_usage['remaining'] > 0) {
                    // Fresh API check shows credits available - backend 429 was incorrect
                    // Update cache with fresh data and return a retry error instead of blocking
                    \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($fresh_usage);
                    
                    if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                        \BeepBeepAI\AltTextGenerator\Debug_Log::log('warning', 'Backend returned 429 but cache and fresh API check show credits available', [
                            'cached_remaining' => $cached_usage['remaining'],
                            'api_remaining' => $fresh_usage['remaining'],
                            'backend_error' => $response['data']['error'] ?? 'Monthly limit reached',
                        ], 'api');
                    }
                    
                    // Return a retry error instead of blocking completely
                    return new \WP_Error(
                        'quota_check_mismatch',
                        __('Backend reported quota limit, but credits appear available. Please try again in a moment.', 'beepbeep-ai-alt-text-generator'),
                        ['usage' => $fresh_usage, 'retry_after' => 3]
                    );
                } elseif (!is_wp_error($fresh_usage) && is_array($fresh_usage) && isset($fresh_usage['remaining']) && is_numeric($fresh_usage['remaining']) && $fresh_usage['remaining'] === 0) {
                    // Fresh API check confirms 0 credits - backend was correct, update cache
                    \BeepBeepAI\AltTextGenerator\Usage_Tracker::update_usage($fresh_usage);
                }
            }
            
            // Cached usage confirms no credits OR fresh check also shows exhausted
            return new \WP_Error(
                'limit_reached',
                $response['data']['error'] ?? __('Monthly limit reached', 'beepbeep-ai-alt-text-generator'),
                ['usage' => $response['data']['usage'] ?? null]
            );
        }

        if (!$response['success']) {
            // Extract detailed error information
            $error_data = $response['data'] ?? [];
            $error_message = $error_data['message'] ?? $error_data['error'] ?? __('Failed to generate alt text', 'beepbeep-ai-alt-text-generator');
            $error_code = $error_data['code'] ?? 'api_error';
            
            // Handle authentication/user errors - only clear token if definitely invalid
            // Don't clear on temporary server errors
            $error_message_lower = strtolower($error_message . ' ' . ($error_data['error'] ?? ''));
            $status_code_check = isset($response['status_code']) ? intval($response['status_code']) : 0;
            
            // Only clear token if it's clearly an auth issue, not a server error
            if ((strpos($error_message_lower, 'user not found') !== false || 
                 strpos($error_message_lower, 'user does not exist') !== false ||
                 ($status_code_check === 401 && strpos($error_message_lower, 'unauthorized') !== false)) &&
                $status_code_check < 500) { // Don't clear token on 500 errors - might be backend issue
                
                // User was deleted or token is invalid - clear stored credentials
                $this->clear_token();
                delete_transient('bbai_token_last_check');
                return new \WP_Error(
                    'auth_required',
                    __('Your session has expired or your account is no longer available. Please log in again.', 'beepbeep-ai-alt-text-generator'),
                    [
                        'requires_auth' => true,
                        'status_code' => $status_code_check,
                        'code' => 'user_not_found',
                    ]
                );
            }
            
            // Handle 413 Payload Too Large specifically
            if ($response['status_code'] === 413) {
                $error_message = __('Image file is too large. Please compress or resize the image before generating alt text.', 'beepbeep-ai-alt-text-generator');
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
                $error_message = __('The backend service is experiencing a configuration issue. This is a temporary backend problem that needs to be fixed on the server side. Please try again in a few minutes or contact support if the issue persists.', 'beepbeep-ai-alt-text-generator');
                $error_code = 'backend_config_error';
            }
            
            // Log detailed error information for debugging
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'API generation failed', [
                    'image_id' => $image_id,
                    'status_code' => $response['status_code'],
                    'error_code' => $error_code,
                    'error_message' => $error_message,
                    'backend_error' => $error_data['error'] ?? $error_data['message'] ?? 'Unknown error',
                    'backend_code' => $error_data['code'] ?? 'unknown',
                ], 'api');
            }
            
            return new \WP_Error(
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
            $error_message = $response['data']['message'] ?? $response['data']['error'] ?? __('Failed to review alt text', 'beepbeep-ai-alt-text-generator');
            return new \WP_Error(
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
        
        return new \WP_Error(
            'billing_failed',
            $response['data']['error'] ?? __('Failed to get billing info', 'beepbeep-ai-alt-text-generator')
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

        return new \WP_Error(
            'plans_failed',
            $response['data']['error'] ?? __('Failed to fetch pricing plans', 'beepbeep-ai-alt-text-generator')
        );
    }
    
    /**
     * Create checkout session
     */
    public function create_checkout_session($price_id, $success_url, $cancel_url) {
        // For checkout, try without token if token is invalid/expired
        // This allows guest checkout - users can create account during checkout
        $token = $this->get_token();
        $had_token = !empty($token);
        
        // First attempt: with token if available
        $response = $this->make_request('/billing/checkout', 'POST', [
            'priceId' => $price_id,
            'successUrl' => $success_url,
            'cancelUrl' => $cancel_url
        ]);
        
        // Check response body for "user not found" errors (backend returns 500 with this message)
        $is_user_not_found_error = false;
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = strtolower($response->get_error_message());
            $error_data = $response->get_error_data();
            
            // Check for "user not found" errors in various forms
            $is_user_not_found_error = $error_code === 'user_not_found' ||
                                       strpos($error_message, 'user not found') !== false ||
                                       strpos($error_message, 'user does not exist') !== false ||
                                       (is_array($error_data) && isset($error_data['code']) && 
                                        ($error_data['code'] === 'user_not_found' ||
                                         strpos(strtolower($error_data['code']), 'user_not_found') !== false)) ||
                                       (is_array($error_data) && isset($error_data['backend_message']) && 
                                        is_string($error_data['backend_message']) &&
                                        strpos(strtolower($error_data['backend_message']), 'user not found') !== false) ||
                                       (is_array($error_data) && isset($error_data['error_details']) && 
                                        is_string($error_data['error_details']) &&
                                        strpos(strtolower($error_data['error_details']), 'user not found') !== false);
        } elseif (isset($response['status_code']) && $response['status_code'] >= 500) {
            // Check response body for "user not found" in 500 errors
            $response_data = $response['data'] ?? [];
            $error_body = '';
            if (isset($response_data['error']) && is_string($response_data['error'])) {
                $error_body = strtolower($response_data['error']);
            } elseif (isset($response_data['message']) && is_string($response_data['message'])) {
                $error_body = strtolower($response_data['message']);
            }
            $is_user_not_found_error = strpos($error_body, 'user not found') !== false ||
                                       strpos($error_body, 'user does not exist') !== false;
        }
        
        // If it's a "user not found" error and we had a token, clear token and retry without auth (for guest checkout)
        if ($is_user_not_found_error && $had_token) {
            // Clear invalid token - it's causing the backend to fail
            $this->clear_token();
            delete_transient('bbai_token_last_check');
            
            // Retry without token (guest checkout)
            $response = $this->make_request('/billing/checkout', 'POST', [
                'priceId' => $price_id,
                'successUrl' => $success_url,
                'cancelUrl' => $cancel_url
            ]);
        }
        
        // Handle errors - don't show "session expired" or "user not found" for checkout
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            $error_lower = strtolower($error_message);
            $error_data = $response->get_error_data();
            
            // For checkout, provide user-friendly error messages
            // Don't show technical errors like "user not found" or "session expired"
            if (strpos($error_lower, 'session') !== false || 
                strpos($error_lower, 'log in') !== false ||
                strpos($error_lower, 'authenticated') !== false ||
                strpos($error_lower, 'user not found') !== false ||
                strpos($error_lower, 'user does not exist') !== false ||
                $error_code === 'user_not_found' ||
                $error_code === 'auth_required' ||
                (is_array($error_data) && isset($error_data['status_code']) && $error_data['status_code'] >= 500)) {
                // For checkout errors, provide a helpful message
                return new \WP_Error(
                    'checkout_failed',
                    __('Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator'),
                    ['response' => $error_data]
                );
            }
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
            $error_message = __('Failed to create checkout session', 'beepbeep-ai-alt-text-generator');
        }

        return new \WP_Error(
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
        
        return new \WP_Error(
            'portal_failed',
            $response['data']['error'] ?? __('Failed to create customer portal session', 'beepbeep-ai-alt-text-generator')
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
        $site_url = admin_url('upload.php?page=bbai');
        
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
        $error_message = $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to send password reset email', 'beepbeep-ai-alt-text-generator');
        
        // Check for specific error cases
        if ($response['status_code'] === 404) {
            $error_message = __('Password reset is currently being set up. This feature is not yet available on our backend. Please contact support for assistance.', 'beepbeep-ai-alt-text-generator');
        } elseif ($response['status_code'] === 429) {
            $error_message = __('Too many password reset requests. Please wait 15 minutes before trying again.', 'beepbeep-ai-alt-text-generator');
        } elseif ($response['status_code'] >= 500) {
            $error_message = __('The authentication server is temporarily unavailable. Please try again in a few minutes.', 'beepbeep-ai-alt-text-generator');
        }
        
        return new \WP_Error(
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
        
        return new \WP_Error(
            'reset_password_failed',
            $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to reset password', 'beepbeep-ai-alt-text-generator')
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
        
        return new \WP_Error(
            'subscription_info_failed',
            $response['data']['error'] ?? __('Failed to fetch subscription information', 'beepbeep-ai-alt-text-generator')
        );
    }
    
    /**
     * Prepare image payload for API
     */
    private function prepare_image_payload($image_id, $image_url, $title, $caption, $filename) {
        $payload = [
            'image_id' => (string) $image_id,  // Cast to string for Prisma compatibility
            'attachment_id' => (string) $image_id, // Redundant field for backend compatibility
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
            // Always send image URL to backend - backend handles all image processing
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
                    
                    // Resize to max 800px on longest side for large files
                    $max_size = 800;
                    
                    if ($orig_width > $max_size || $orig_height > $max_size) {
                        if ($orig_width > $orig_height) {
                            $new_width = $max_size;
                            $new_height = intval(($orig_height / $orig_width) * $max_size);
                        } else {
                            $new_height = $max_size;
                            $new_width = intval(($orig_width / $orig_height) * $max_size);
                        }
                        
                        $editor = wp_get_image_editor($file_path);
                        if (!is_wp_error($editor)) {
                            $editor->resize($new_width, $new_height, false);
                            
                            // Set quality to reduce file size
                            if (method_exists($editor, 'set_quality')) {
                                $editor->set_quality(85);
                            }
                            
                            $upload_dir = wp_upload_dir();
                            $temp_filename = 'beepbeepai-temp-' . $image_id . '-' . time() . '.jpg';
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
                            $payload['image_url'] = $image_url;
                        }
                    } else {
                        // Image already small, send URL
                        $payload['image_url'] = $image_url;
                    }
                } else {
                    // No resize needed or editor not available - send URL
                    $payload['image_url'] = $image_url;
                }
            } else {
                // Fallback to URL if file doesn't exist
                $payload['image_url'] = $image_url;
            }
        } else {
            // No image URL provided - this is an error condition
            // Log it but still return the payload (backend should handle the error)
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'prepare_image_payload: No image_url provided', [
                    'image_id' => $image_id,
                    'image_url_param' => $image_url,
                ], 'api');
            }
        }

        // Always try to include inline image data for accuracy when file is accessible
        // This helps when the backend cannot reach the public URL (private sites/CDNs)
        $file_path = $file_path ?? get_attached_file($image_id);
        if (empty($payload['image_base64']) && $file_path && file_exists($file_path)) {
            $file_size = filesize($file_path);
            $max_inline_size = 5.5 * 1024 * 1024; // ~5.5MB upper bound
            if ($file_size > 0 && $file_size <= $max_inline_size) {
                $contents = file_get_contents($file_path);
                if ($contents !== false) {
                    $base64 = base64_encode($contents);
                    // Avoid sending absurdly large base64 (should align with size check)
                    if (!empty($base64) && strlen($base64) <= $max_inline_size * 1.4) {
                        $mime_type = $mime_type ?? get_post_mime_type($image_id) ?: 'image/jpeg';
                        $payload['image_base64'] = $base64;
                        $payload['mime_type'] = $mime_type;
                    }
                }
            }
        }
        
        // CRITICAL: Verify that image data is included before returning
        // At least one of image_url or image_base64 must be present
        if (empty($payload['image_url']) && empty($payload['image_base64'])) {
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                \BeepBeepAI\AltTextGenerator\Debug_Log::log('error', 'prepare_image_payload: Payload missing image data', [
                    'image_id' => $image_id,
                    'payload_keys' => array_keys($payload),
                    'image_url_input' => $image_url,
                ], 'api');
            }
            // Set error flag so caller knows payload is invalid
            $payload['_error'] = 'missing_image_data';
            $payload['_error_message'] = 'Image URL or base64 data is required';
        }
        
        return $payload;
    }

    /**
     * Sanitize context data before logging to prevent exposing sensitive information
     */
    /**
     * Clean data array to ensure it's JSON-encodable
     * Removes non-serializable values like resources, objects without __toString, etc.
     */
    private function clean_json_data($data) {
        if (!is_array($data)) {
            // Convert objects to arrays if possible
            if (is_object($data)) {
                // Try to convert to array
                if (method_exists($data, '__toString')) {
                    return (string) $data;
                }
                // If it's a stdClass or array-like object, cast it
                if ($data instanceof \stdClass || $data instanceof \ArrayObject) {
                    return $this->clean_json_data((array) $data);
                }
                // Otherwise, skip it
                return null;
            }
            // For scalar values, return as-is
            return $data;
        }
        
        $cleaned = [];
        foreach ($data as $key => $value) {
            // Skip non-scalar keys
            if (!is_string($key) && !is_int($key)) {
                continue;
            }
            
            // Recursively clean nested arrays/objects
            if (is_array($value) || is_object($value)) {
                $cleaned_value = $this->clean_json_data($value);
                // Only add if cleaning succeeded
                if ($cleaned_value !== null) {
                    $cleaned[$key] = $cleaned_value;
                }
            } elseif (is_resource($value)) {
                // Skip resources
                continue;
            } elseif (is_scalar($value) || $value === null) {
                // Include scalar values and null
                $cleaned[$key] = $value;
            }
        }
        
        return $cleaned;
    }
    
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
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            // Sanitize context before logging
            $sanitized_context = $this->sanitize_log_context($context);
            \BeepBeepAI\AltTextGenerator\Debug_Log::log($level, $message, $sanitized_context, 'api');
        }
    }
}
