<?php
/**
 * Resend Service
 *
 * Handles sending emails via Resend API for contact forms and support requests.
 *
 * @package BeepBeepAI\AltTextGenerator
 * @since 6.0.0
 */

namespace BeepBeepAI\AltTextGenerator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resend Service Class
 */
class Resend_Service {

    /**
     * Resend API endpoint
     *
     * @var string
     */
    private const API_ENDPOINT = 'https://api.resend.com/emails';

    /**
     * Get Resend API key from multiple sources (constant, env var, or option)
     *
     * Priority order:
     * 1. BBAI_RESEND_API_KEY constant (wp-config.php)
     * 2. RESEND_API_KEY environment variable (server/host config)
     * 3. WordPress option 'bbai_resend_api_key' (stored in database)
     *
     * @return string|false API key or false if not configured
     */
    private function get_api_key() {
        // 1. Check WordPress constant (wp-config.php)
        if (defined('BBAI_RESEND_API_KEY') && !empty(BBAI_RESEND_API_KEY)) {
            return BBAI_RESEND_API_KEY;
        }

        // 2. Check environment variable (server/host configuration)
        $env_key = getenv('RESEND_API_KEY');
        if (!empty($env_key)) {
            return $env_key;
        }

        // 3. Check WordPress option (stored in database)
        $option_key = get_option('bbai_resend_api_key', '');
        if (!empty($option_key)) {
            return $option_key;
        }

        return false;
    }

    /**
     * Get recipient email address from multiple sources
     *
     * Priority order:
     * 1. BBAI_CONTACT_EMAIL constant
     * 2. RESEND_CONTACT_EMAIL environment variable
     * 3. WordPress option 'bbai_contact_email'
     * 4. WordPress admin email as fallback
     *
     * @return string|false Email address or false if not configured
     */
    private function get_recipient_email() {
        // 1. Check WordPress constant
        if (defined('BBAI_CONTACT_EMAIL') && !empty(BBAI_CONTACT_EMAIL)) {
            return BBAI_CONTACT_EMAIL;
        }

        // 2. Check environment variable
        $env_email = getenv('RESEND_CONTACT_EMAIL');
        if (!empty($env_email) && is_email($env_email)) {
            return $env_email;
        }

        // 3. Check WordPress option
        $option_email = get_option('bbai_contact_email', '');
        if (!empty($option_email) && is_email($option_email)) {
            return $option_email;
        }

        // 4. Fallback to WordPress admin email
        $admin_email = get_option('admin_email');
        if (!empty($admin_email) && is_email($admin_email)) {
            return $admin_email;
        }

        return false;
    }

    /**
     * Send contact form email via Resend API
     *
     * @param array $data {
     *     Contact form data.
     *
     *     @type string $name       User's name.
     *     @type string $email      User's email.
     *     @type string $subject    Email subject.
     *     @type string $message    Message content.
     *     @type string $wp_version WordPress version (optional).
     *     @type string $plugin_version Plugin version (optional).
     * }
     * @return array|\WP_Error {
     *     Success response or WP_Error on failure.
     *
     *     @type bool   $success Whether email was sent successfully.
     *     @type string $message Response message.
     *     @type string $id      Resend email ID if successful (optional).
     * }
     */
    public function send_contact_email($data) {
        $api_key = $this->get_api_key();
        if (!$api_key) {
            return new \WP_Error(
                'resend_api_key_missing',
                __('Resend API key is not configured. Please set BBAI_RESEND_API_KEY constant, RESEND_API_KEY environment variable, or store it in WordPress options.', 'beepbeep-ai-alt-text-generator')
            );
        }

        // Validate required fields
        $required_fields = ['name', 'email', 'subject', 'message'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Required field "%s" is missing.', 'beepbeep-ai-alt-text-generator'), $field)
                );
            }
        }

        // Validate email format
        if (!is_email($data['email'])) {
            return new \WP_Error(
                'invalid_email',
                __('Invalid email address format.', 'beepbeep-ai-alt-text-generator')
            );
        }

        // Get recipient email
        $recipient_email = $this->get_recipient_email();
        if (!$recipient_email) {
            // Extract domain from API key if possible, or use a fallback
            // For now, require BBAI_CONTACT_EMAIL to be set
            return new \WP_Error(
                'recipient_email_missing',
                __('Contact email is not configured. Please set BBAI_CONTACT_EMAIL constant or configure in Resend.', 'beepbeep-ai-alt-text-generator')
            );
        }

        // Build email HTML content
        $html_content = $this->build_email_html($data);

        // Prepare request payload
        $payload = [
            'from' => 'BeepBeep AI Support <noreply@resend.dev>', // Default, should be configured in Resend
            'to' => [$recipient_email],
            'subject' => sprintf('[BeepBeep AI Support] %s', sanitize_text_field($data['subject'])),
            'html' => $html_content,
            'reply_to' => [
                sanitize_email($data['email']) => sanitize_text_field($data['name'])
            ]
        ];

        // Log the request (optional, for debugging)
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Sending contact form email via Resend', [
                'recipient' => $recipient_email,
                'subject' => $payload['subject'],
                'from_email' => $data['email']
            ], 'contact');
        }

        // Make API request
        $response = wp_remote_post(
            self::API_ENDPOINT,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 30,
                'sslverify' => true,
            ]
        );

        // Handle response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('error', 'Resend API request failed', [
                    'error' => $error_message
                ], 'contact');
            }
            return new \WP_Error(
                'resend_api_error',
                sprintf(__('Failed to send email: %s', 'beepbeep-ai-alt-text-generator'), $error_message)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($status_code !== 200 && $status_code !== 201) {
            $error_message = isset($response_data['message']) ? $response_data['message'] : __('Unknown error', 'beepbeep-ai-alt-text-generator');
            if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
                Debug_Log::log('error', 'Resend API returned error', [
                    'status_code' => $status_code,
                    'error' => $error_message,
                    'response' => $response_data
                ], 'contact');
            }
            return new \WP_Error(
                'resend_api_error',
                sprintf(__('Email service error: %s', 'beepbeep-ai-alt-text-generator'), $error_message)
            );
        }

        // Success
        $email_id = isset($response_data['id']) ? $response_data['id'] : '';
        if (class_exists('\BeepBeepAI\AltTextGenerator\Debug_Log')) {
            Debug_Log::log('info', 'Contact form email sent successfully via Resend', [
                'email_id' => $email_id,
                'recipient' => $recipient_email
            ], 'contact');
        }

        return [
            'success' => true,
            'message' => __('Your message has been sent successfully. We\'ll get back to you soon!', 'beepbeep-ai-alt-text-generator'),
            'id' => $email_id
        ];
    }

    /**
     * Build HTML email content from form data
     *
     * @param array $data Contact form data.
     * @return string HTML email content.
     */
    private function build_email_html($data) {
        $name = esc_html($data['name']);
        $email = esc_html($data['email']);
        $subject = esc_html($data['subject']);
        $message = nl2br(esc_html($data['message']));
        $wp_version = isset($data['wp_version']) ? esc_html($data['wp_version']) : __('Not provided', 'beepbeep-ai-alt-text-generator');
        $plugin_version = isset($data['plugin_version']) ? esc_html($data['plugin_version']) : __('Not provided', 'beepbeep-ai-alt-text-generator');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, sans-serif; line-height: 1.6; color: #333;">
    <div class="container" style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div class="header" style="background: #10b981; color: #ffffff; padding: 20px; border-radius: 8px 8px 0 0;">
            <h1 style="margin: 0; font-size: 20px;">New Contact Form Submission</h1>
        </div>
        <div class="content" style="background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; border-top: none;">
            <div class="field" style="margin-bottom: 15px;">
                <div class="field-label" style="font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Name</div>
                <div class="field-value" style="color: #111827; font-size: 14px;">' . $name . '</div>
            </div>
            <div class="field" style="margin-bottom: 15px;">
                <div class="field-label" style="font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Email</div>
                <div class="field-value" style="color: #111827; font-size: 14px;"><a href="mailto:' . $email . '" style="color: #111827; text-decoration: none;">' . $email . '</a></div>
            </div>
            <div class="field" style="margin-bottom: 15px;">
                <div class="field-label" style="font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Subject</div>
                <div class="field-value" style="color: #111827; font-size: 14px;">' . $subject . '</div>
            </div>
            <div class="field" style="margin-bottom: 15px;">
                <div class="field-label" style="font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">Message</div>
                <div class="message-box" style="background: #ffffff; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981; margin-top: 10px;">' . $message . '</div>
            </div>
            <div class="footer" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
                <div class="field" style="margin-bottom: 0;">
                    <div class="field-label" style="font-weight: 600; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px;">System Information</div>
                    <div class="field-value" style="color: #111827; font-size: 14px;">WordPress Version: ' . $wp_version . '<br>Plugin Version: ' . $plugin_version . '</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
