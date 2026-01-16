<?php
/**
 * Core Generation Trait
 * Handles alt text generation helpers, prompt building, and result persistence
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Core_Generation {

    /**
     * Sanitize error messages by redacting sensitive data
     *
     * @param mixed $message Error message
     * @return string Sanitized message
     */
    private function sanitize_error_message($message) {
        if (!is_string($message) || $message === '') {
            return '';
        }

        $message = preg_replace('/\b(sk-[A-Za-z0-9]{4})[A-Za-z0-9]+([A-Za-z0-9]{4})\b/', '$1****$2', $message);
        $message = preg_replace('/\b([a-f0-9]{8})-([a-f0-9]{4})-([a-f0-9]{4})-([a-f0-9]{4})-[a-f0-9]{12}\b/i', '$1-****-****-****-************', $message);
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+/i', 'Bearer ****', $message);
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '****@****', $message);

        return $message;
    }

    /**
     * Build the prompt for alt text generation
     *
     * @param int $attachment_id Attachment ID
     * @return string|WP_Error Prompt string or error
     */
    private function build_prompt($attachment_id) {
        $opts = get_option(self::OPTION_KEY, []);

        $seo_keywords = sanitize_text_field($opts['seo_keywords'] ?? '');
        $custom_prompt = sanitize_textarea_field($opts['custom_prompt'] ?? '');
        $length = sanitize_text_field($opts['length'] ?? 'medium');

        $length_guide = '';
        switch ($length) {
            case 'short':
                $length_guide = 'Keep the ALT text short (1-2 sentences, roughly 50-80 characters).';
                break;
            case 'long':
                $length_guide = 'Provide detailed ALT text (2-4 sentences, 120-160 characters).';
                break;
            default:
                $length_guide = 'Provide ALT text of moderate length (80-120 characters).';
        }

        $parts = [
            "Generate descriptive ALT text for this image. {$length_guide}",
        ];

        if ($seo_keywords) {
            $parts[] = "Try to naturally incorporate one or more of these SEO keywords if appropriate: {$seo_keywords}";
        }

        if ($custom_prompt) {
            $parts[] = $custom_prompt;
        }

        $parts[] = 'Return only the ALT text, with no extra commentary.';

        return apply_filters('bbai_generation_prompt', implode(' ', $parts), $attachment_id, $opts);
    }

    /**
     * Build inline image payload for API request (base64)
     *
     * @param int $attachment_id Attachment ID
     * @return array|WP_Error Image payload or error
     */
    private function build_inline_image_payload($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return new \WP_Error('inline_image_missing', __('Unable to locate the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $size = filesize($file);
        if ($size === false || $size <= 0) {
            return new \WP_Error('inline_image_size', __('Unable to read the image size for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $limit = apply_filters('bbai_inline_image_limit', 1024 * 1024 * 2, $attachment_id, $file);
        if ($size > $limit) {
            return new \WP_Error('inline_image_large', sprintf(
                __('Image exceeds the 2 MB limit for inline embedding (%s).', 'beepbeep-ai-alt-text-generator'),
                size_format($size)
            ));
        }

        $mime = mime_content_type($file);
        if (!$mime || strpos((string)$mime, 'image/') !== 0) {
            return new \WP_Error('inline_image_mime', __('Invalid image MIME type for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $data = file_get_contents($file);
        if ($data === false) {
            return new \WP_Error('inline_image_read', __('Failed to read the image file for inline embedding.', 'beepbeep-ai-alt-text-generator'));
        }

        $base64 = base64_encode($data);

        return [
            'type' => 'image_url',
            'image_url' => [
                'url' => "data:{$mime};base64,{$base64}",
            ],
        ];
    }

    /**
     * Check if should retry without image (URL-based fallback)
     *
     * @param mixed $error WP_Error or other error
     * @return bool True if should retry without image
     */
    private function should_retry_without_image($error) {
        if (!is_wp_error($error)) {
            return false;
        }

        if ($error->get_error_code() !== 'api_error') {
            return false;
        }

        $error_message = $error->get_error_message();
        if (!is_string($error_message) || empty($error_message)) {
            return false;
        }
        $message = strtolower($error_message);
        $needles = ['error while downloading', 'failed to download', 'unsupported image url'];
        foreach ($needles as $needle) {
            if (is_string($message) && strpos((string)$message, (string)$needle) !== false) {
                return true;
            }
        }

        $data = $error->get_error_data();
        if (is_array($data)) {
            if (!empty($data['message']) && is_string($data['message'])) {
                $msg = strtolower($data['message']);
                foreach ($needles as $needle) {
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false) {
                        return true;
                    }
                }
            }
            if (!empty($data['body']['error']['message']) && is_string($data['body']['error']['message'])) {
                $msg = strtolower($data['body']['error']['message']);
                foreach ($needles as $needle) {
                    if (is_string($msg) && strpos((string)$msg, (string)$needle) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract JSON object from API response content
     *
     * @param string $content Raw response content
     * @return array|null Parsed JSON or null
     */
    private function extract_json_object(string $content) {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        if (stripos($content, '```') !== false) {
            $content = preg_replace('/```json/i', '', $content);
            $content = str_replace('```', '', $content);
            $content = trim($content);
        }

        if ($content !== '' && is_string($content) && isset($content[0]) && $content[0] !== '{') {
            $start = strpos((string)$content, '{');
            $end   = strrpos((string)$content, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $content = substr($content, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    /**
     * Redact API token from message
     *
     * @param string $message Message containing potential token
     * @return string Redacted message
     */
    private function redact_api_token($message) {
        if (!is_string($message) || $message === '') {
            return $message;
        }

        $mask = function($token) {
            $len = strlen($token);
            if ($len <= 8) {
                return str_repeat('*', $len);
            }
            return substr($token, 0, 4) . str_repeat('*', $len - 8) . substr($token, -4);
        };

        $message = preg_replace_callback('/(Incorrect API key provided:\s*)(\S+)/i', function($matches) use ($mask) {
            return $matches[1] . $mask($matches[2]);
        }, $message);

        $message = preg_replace_callback('/(sk-[A-Za-z0-9]{4})([A-Za-z0-9]{10,})([A-Za-z0-9]{4})/i', function($matches) {
            return $matches[1] . str_repeat('*', strlen($matches[2])) . $matches[3];
        }, $message);

        return $message;
    }

    /**
     * Persist generation result to post meta
     *
     * @param int    $attachment_id Attachment ID
     * @param string $alt_text      Generated alt text
     * @param array  $meta          Metadata (tokens, model, source)
     * @return bool True on success
     */
    public function persist_generation_result($attachment_id, $alt_text, array $meta = []) {
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }

        $alt_text = sanitize_text_field($alt_text);
        if ($alt_text === '') {
            return false;
        }

        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
        update_post_meta($attachment_id, '_bbai_generated_at', current_time('mysql'));

        if (isset($meta['tokens_total'])) {
            update_post_meta($attachment_id, '_bbai_tokens_total', intval($meta['tokens_total']));
        }
        if (isset($meta['tokens_prompt'])) {
            update_post_meta($attachment_id, '_bbai_tokens_prompt', intval($meta['tokens_prompt']));
        }
        if (isset($meta['tokens_completion'])) {
            update_post_meta($attachment_id, '_bbai_tokens_completion', intval($meta['tokens_completion']));
        }
        if (isset($meta['model'])) {
            update_post_meta($attachment_id, '_bbai_model', sanitize_text_field($meta['model']));
        }
        if (isset($meta['source'])) {
            update_post_meta($attachment_id, '_bbai_source', sanitize_key($meta['source']));
        }

        $this->invalidate_stats_cache();

        do_action('bbai_alt_text_generated', $attachment_id, $alt_text, $meta);

        return true;
    }
}
