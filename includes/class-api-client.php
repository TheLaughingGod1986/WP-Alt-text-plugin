<?php
/**
 * API Client for AltText AI
 * Handles communication with the proxy API
 */

if (!defined('ABSPATH')) { exit; }

class AltText_AI_API_Client {
    
    private $api_url;
    private $domain;
    
    public function __construct() {
        $options = get_option('ai_alt_gpt_settings', []);
        $this->api_url = $options['api_url'] ?? 'https://alttext-ai-backend.onrender.com';
        $this->domain = $this->get_site_domain();
    }
    
    /**
     * Get the current site's domain
     */
    private function get_site_domain() {
        $url = get_site_url();
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'localhost';
    }
    
    /**
     * Generate alt text via API
     */
    public function generate_alt_text($image_id, $context = [], $regenerate = false) {
        $endpoint = trailingslashit($this->api_url) . 'api/generate';
        
        // Enrich context with useful metadata for higher fidelity
        $image_url = wp_get_attachment_url($image_id);
        $title     = get_the_title($image_id);
        $caption   = wp_get_attachment_caption($image_id);
        $filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

        $body = [
            'domain' => $this->domain,
            'image_data' => $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename),
            'context' => $context
        ];
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Secret' => 'alttext-secret-key-2024',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Handle rate limit
        if ($status_code === 429) {
            return new WP_Error(
                'limit_reached',
                $data['error'] ?? __('Monthly limit reached', 'ai-alt-gpt'),
                ['usage' => $data['usage'] ?? null]
            );
        }
        
        if ($status_code !== 200) {
            $error_message = $data['message'] ?? $data['error'] ?? __('Failed to generate alt text', 'ai-alt-gpt');
            $error_data = [
                'code' => $data['code'] ?? 'api_error',
                'debug' => $data,
            ];
            return new WP_Error(
                'api_error',
                $error_message,
                $error_data
            );
        }
        
        // Cache usage data
        if (isset($data['usage'])) {
            AltText_AI_Usage_Tracker::update_usage($data['usage']);
        }
        
        return $data;
    }

    /**
     * Review existing alt text via API
     */
    public function review_alt_text($image_id, $alt_text, $context = []) {
        $endpoint = trailingslashit($this->api_url) . 'api/review';

        $image_url = wp_get_attachment_url($image_id);
        $title     = get_the_title($image_id);
        $caption   = wp_get_attachment_caption($image_id);
        $filename  = $image_url ? wp_basename(parse_url($image_url, PHP_URL_PATH)) : '';

        $body = [
            'domain' => $this->domain,
            'alt_text' => $alt_text,
            'image_data' => $this->prepare_image_payload($image_id, $image_url, $title, $caption, $filename),
            'context' => $context,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Secret' => 'alttext-secret-key-2024',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            return new WP_Error(
                'api_error',
                $data['error'] ?? __('Failed to review alt text', 'ai-alt-gpt')
            );
        }

        return $data;
    }
    
    /**
     * Get current usage for this domain
     */
    public function get_usage() {
        $endpoint = trailingslashit($this->api_url) . 'api/usage/' . urlencode($this->domain);
        
        $response = wp_remote_get($endpoint, [
            'headers' => [
                'X-API-Secret' => 'alttext-secret-key-2024',
            ],
            'timeout' => 10,
        ]);
        
        if (is_wp_error($response)) {
            // Return cached usage if API is unavailable
            return AltText_AI_Usage_Tracker::get_cached_usage();
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['usage'])) {
            AltText_AI_Usage_Tracker::update_usage($data['usage']);
            return $data['usage'];
        }
        
        return AltText_AI_Usage_Tracker::get_cached_usage();
    }
    
    /**
     * Check if user has reached their limit
     */
    public function has_reached_limit() {
        $usage = $this->get_usage();
        return ($usage['remaining'] ?? 1) <= 0;
    }
    
    /**
     * Get percentage of quota used
     */
    public function get_usage_percentage() {
        $usage = $this->get_usage();
        $used = $usage['used'] ?? 0;
        $limit = $usage['limit'] ?? 10;

        if ($limit == 0) return 0;
        return min(100, round(($used / $limit) * 100));
    }

    /**
     * Prepare inline (base64) image data for multimodal requests.
     */
    private function get_inline_image_payload($attachment_id) {
        $max_bytes = apply_filters('ai_alt_gpt_inline_image_max_bytes', 550000);
        $preferred_size = apply_filters('ai_alt_gpt_inline_image_size', 'medium_large');
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return null;
        }

        $candidates = [];

        if ($preferred_size) {
            $intermediate = image_get_intermediate_size($attachment_id, $preferred_size);
            if (!empty($intermediate['file'])) {
                $intermediate_path = path_join(dirname($file_path), $intermediate['file']);
                if (file_exists($intermediate_path)) {
                    $candidates[] = $intermediate_path;
                }
            }
        }

        $candidates[] = $file_path;

        foreach ($candidates as $candidate) {
            $size = @filesize($candidate);
            if ($size && $size > $max_bytes) {
                continue;
            }

            $mime = wp_get_image_mime($candidate);
            if (!$mime) {
                continue;
            }

            $contents = @file_get_contents($candidate);
            if ($contents === false) {
                continue;
            }

            if (strlen($contents) > $max_bytes) {
                continue;
            }

            return [
                'data_url' => 'data:' . $mime . ';base64,' . base64_encode($contents),
                'bytes'    => strlen($contents),
                'mime'     => $mime,
                'source'   => basename($candidate),
            ];
        }

        return null;
    }

    private function prepare_image_payload($image_id, $image_url, $title, $caption, $filename) {
        $image_dimensions = wp_get_attachment_metadata($image_id) ?: [];

        return [
            'id' => $image_id,
            'url' => $image_url,
            'title' => $title,
            'caption' => $caption,
            'filename' => $filename,
            'width' => isset($image_dimensions['width']) ? intval($image_dimensions['width']) : null,
            'height' => isset($image_dimensions['height']) ? intval($image_dimensions['height']) : null,
            'inline' => $this->get_inline_image_payload($image_id),
        ];
    }
}
