<?php
/**
 * API Client for AltText AI Phase 2
 * Handles JWT authentication and communication with the Phase 2 API
 */

if (!defined('ABSPATH')) { exit; }

class AltText_AI_API_Client_V2 {
    
    private $api_url;
    private $token_option_key = 'alttextai_jwt_token';
    private $user_option_key = 'alttextai_user_data';
    
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
        $options = get_option('ai_alt_gpt_settings', []);
<<<<<<< HEAD
        $options = is_array($options) ? $options : [];
=======
>>>>>>> origin/main
        if (!isset($options['api_url']) || $options['api_url'] !== $production_url) {
            $options['api_url'] = $production_url;
            update_option('ai_alt_gpt_settings', $options);
        }
    }
    
    /**
     * Get stored JWT token
     */
    protected function get_token() {
<<<<<<< HEAD
        $token = get_option($this->token_option_key, '');
        return is_string($token) ? $token : '';
=======
        return get_option($this->token_option_key, '');
>>>>>>> origin/main
    }
    
    /**
     * Store JWT token
     */
    public function set_token($token) {
        update_option($this->token_option_key, $token);
    }
    
    /**
     * Clear stored token
     */
    public function clear_token() {
        delete_option($this->token_option_key);
        delete_option($this->user_option_key);
    }
    
    /**
     * Get stored user data
     */
    public function get_user_data() {
<<<<<<< HEAD
        $data = get_option($this->user_option_key, null);
        return ($data !== false && $data !== null) ? $data : null;
=======
        return get_option($this->user_option_key, null);
>>>>>>> origin/main
    }
    
    /**
     * Store user data
     */
    public function set_user_data($user_data) {
        update_option($this->user_option_key, $user_data);
    }
    
    /**
     * Check if user is authenticated
     * Also validates the token by checking with backend
     */
    public function is_authenticated() {
        $token = $this->get_token();

        if (empty($token)) {
            return false;
        }

        // In local development, just check if token exists
        if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
            return true;
        }

        // Validate token is still valid (check periodically, not every request)
        $last_check = get_transient('alttextai_token_last_check');
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
            set_transient('alttextai_token_last_check', time(), 5 * MINUTE_IN_SECONDS);
        }

        return true;
    }
    
    /**
     * Get authentication headers
     */
    private function get_auth_headers() {
        $token = $this->get_token();
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        return $headers;
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
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (strpos($error_message, 'timeout') !== false) {
                return new WP_Error('api_timeout', __('Authentication server is taking too long to respond. Please try again in a few minutes.', 'ai-alt-gpt'));
            } elseif (strpos($error_message, 'could not resolve') !== false) {
                return new WP_Error('api_unreachable', __('Unable to reach authentication server. Please check your internet connection and try again.', 'ai-alt-gpt'));
            }
            return new WP_Error('api_error', $error_message);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Handle 404 - endpoint not found
        if ($status_code === 404) {
            // Check if it's an HTML error page (means endpoint doesn't exist)
            if (strpos($body, '<html') !== false || strpos($body, 'Cannot POST') !== false || strpos($body, 'Cannot GET') !== false) {
                return new WP_Error(
                    'endpoint_not_found',
                    __('This feature is not yet available. The password reset functionality is currently being set up on our backend. Please contact support for assistance or try again later.', 'ai-alt-gpt')
                );
            }
            return new WP_Error(
                'not_found',
                $data['error'] ?? $data['message'] ?? __('The requested resource was not found.', 'ai-alt-gpt')
            );
        }
        
        // Handle server errors
        if ($status_code >= 500) {
            return new WP_Error(
                'server_error',
                __('Authentication server is temporarily unavailable. Please try again in a few minutes.', 'ai-alt-gpt')
            );
        }
        
        // Handle authentication errors
        if ($status_code === 401 || $status_code === 403) {
            $this->clear_token();
            return new WP_Error(
                'auth_required',
                __('Authentication required. Please log in to continue.', 'ai-alt-gpt'),
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
            $response['data']['error'] ?? __('Registration failed', 'ai-alt-gpt')
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
            $response['data']['error'] ?? __('Login failed', 'ai-alt-gpt')
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
            $response['data']['error'] ?? __('Failed to get user info', 'ai-alt-gpt')
        );
    }
    
    /**
     * Get usage information
     */
    public function get_usage() {
        $response = $this->make_request('/usage');

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['success']) {
            return $response['data']['usage'];
        }

        return new WP_Error(
            'usage_failed',
            $response['data']['error'] ?? __('Failed to get usage info', 'ai-alt-gpt')
        );
    }

    /**
     * Check if user has reached their limit
     */
    public function has_reached_limit() {
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
<<<<<<< HEAD
        $limit = $usage['limit'] ?? 50;
=======
        $limit = $usage['limit'] ?? 10;
>>>>>>> origin/main

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

        $body = [
            'image_data' => $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename),
            'context' => $context,
            'regenerate' => $regenerate
        ];
        
        $response = $this->make_request($endpoint, 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Handle rate limit
        if ($response['status_code'] === 429) {
            return new WP_Error(
                'limit_reached',
                $response['data']['error'] ?? __('Monthly limit reached', 'ai-alt-gpt'),
                ['usage' => $response['data']['usage'] ?? null]
            );
        }
        
        if (!$response['success']) {
            $error_message = $response['data']['message'] ?? $response['data']['error'] ?? __('Failed to generate alt text', 'ai-alt-gpt');
            return new WP_Error(
                'api_error',
                $error_message,
                ['code' => $response['data']['code'] ?? 'api_error']
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
        
        $response = $this->make_request($endpoint, 'POST', $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (!$response['success']) {
            $error_message = $response['data']['message'] ?? $response['data']['error'] ?? __('Failed to review alt text', 'ai-alt-gpt');
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
            $response['data']['error'] ?? __('Failed to get billing info', 'ai-alt-gpt')
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
            $response['data']['error'] ?? __('Failed to fetch pricing plans', 'ai-alt-gpt')
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
            $error_message = __('Failed to create checkout session', 'ai-alt-gpt');
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
            $response['data']['error'] ?? __('Failed to create customer portal session', 'ai-alt-gpt')
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
        $site_url = admin_url('upload.php?page=ai-alt-gpt');
        
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
        $error_message = $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to send password reset email', 'ai-alt-gpt');
        
        // Check for specific error cases
        if ($response['status_code'] === 404) {
            $error_message = __('Password reset is currently being set up. This feature is not yet available on our backend. Please contact support for assistance.', 'ai-alt-gpt');
        } elseif ($response['status_code'] === 429) {
            $error_message = __('Too many password reset requests. Please wait 15 minutes before trying again.', 'ai-alt-gpt');
        } elseif ($response['status_code'] >= 500) {
            $error_message = __('The authentication server is temporarily unavailable. Please try again in a few minutes.', 'ai-alt-gpt');
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
            $response['data']['error'] ?? $response['data']['message'] ?? __('Failed to reset password', 'ai-alt-gpt')
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
            $response['data']['error'] ?? __('Failed to fetch subscription information', 'ai-alt-gpt')
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
        
        if ($image_url) {
            $payload['url'] = $image_url;
            
            // Try to get image dimensions
            $metadata = wp_get_attachment_metadata($image_id);
            if ($metadata && isset($metadata['width'], $metadata['height'])) {
                $payload['width'] = $metadata['width'];
                $payload['height'] = $metadata['height'];
            }
        }
        
        return $payload;
    }
}
