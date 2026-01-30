<?php
/**
 * API Billing Trait
 * Handles Stripe checkout and billing operations
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

trait Api_Billing {

    /**
     * Get billing info
     */
    public function get_billing_info() {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Authentication required', 'opptiai-alt'));
        }

        $response = $this->make_request('/billing/info', 'GET');

        return $response;
    }

    /**
     * Get available plans
     */
    public function get_plans() {
        $response = $this->make_request('/plans', 'GET');

        return $response;
    }

    /**
     * Create Stripe checkout session
     */
    public function create_checkout_session($price_id, $success_url, $cancel_url) {
        $site_fingerprint = $this->get_site_fingerprint();

        $data = [
            'price_id' => $price_id,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'site_url' => get_site_url(),
            'site_fingerprint' => $site_fingerprint,
        ];

        $response = $this->make_request('/billing/checkout', 'POST', $data);

        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            if (in_array($error_code, ['auth_expired', 'invalid_token'], true)) {
                $guest_response = $this->make_request('/billing/checkout/guest', 'POST', $data);
                if (!is_wp_error($guest_response)) {
                    return $guest_response;
                }
            }
            return $response;
        }

        return $response;
    }

    /**
     * Create customer portal session
     */
    public function create_customer_portal_session($return_url) {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Authentication required', 'opptiai-alt'));
        }

        $response = $this->make_request('/billing/portal', 'POST', [
            'return_url' => $return_url,
        ]);

        return $response;
    }

    /**
     * Get subscription info
     */
    public function get_subscription_info() {
        if (!$this->is_authenticated()) {
            return new \WP_Error('not_authenticated', __('Authentication required', 'opptiai-alt'));
        }

        $response = $this->make_request('/billing/subscription', 'GET');

        return $response;
    }
}
