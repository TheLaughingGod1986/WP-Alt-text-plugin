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
        $options = get_option('ai_alt_gpt_settings', []);
        // Use host.docker.internal for Docker environments, localhost for local development
        $default_url = (defined('WP_CLI') || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) 
            ? 'http://host.docker.internal:3001' 
            : 'http://localhost:3001';
        $this->api_url = $options['api_url'] ?? $default_url;
    }
    
    /**
     * Get stored JWT token
     */
    private function get_token() {
        return get_option($this->token_option_key, '');
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
        return get_option($this->user_option_key, null);
    }
    
    /**
     * Store user data
     */
    public function set_user_data($user_data) {
        update_option($this->user_option_key, $user_data);
    }
    
    /**
     * Check if user is authenticated
     */
    public function is_authenticated() {
        $token = $this->get_token();
        return !empty($token);
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
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
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
        
        return new WP_Error(
            'checkout_failed',
            $response['data']['error'] ?? __('Failed to create checkout session', 'ai-alt-gpt')
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
     * Prepare image payload for API
     */
    private function prepare_image_payload($image_id, $image_url, $title, $caption, $filename) {
        $payload = [
            'image_id' => $image_id,
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
