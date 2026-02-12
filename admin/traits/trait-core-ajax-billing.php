<?php
/**
 * Core Ajax Billing Trait
 * Handles Stripe checkout and billing AJAX endpoints
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if (!defined('ABSPATH')) { exit; }

use BeepBeepAI\AltTextGenerator\Usage_Tracker;

trait Core_Ajax_Billing {

    /**
     * AJAX handler: Create Stripe checkout session
     */
    public function ajax_create_checkout() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $price_id_raw = isset( $_POST['price_id'] ) ? wp_unslash( $_POST['price_id'] ) : '';
        $price_id     = is_string( $price_id_raw ) ? sanitize_text_field( $price_id_raw ) : '';

        // Resolve plan_id to a Stripe price ID when price_id is not provided directly.
        if ( empty( $price_id ) ) {
            $plan_id_raw = isset( $_POST['plan_id'] ) ? wp_unslash( $_POST['plan_id'] ) : '';
            $plan_id     = is_string( $plan_id_raw ) ? sanitize_key( $plan_id_raw ) : '';
            if ( ! empty( $plan_id ) ) {
                $price_id = $this->get_checkout_price_id( $plan_id );
            }
        }

        if ( empty( $price_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Price ID is required', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $success_url = admin_url('upload.php?page=bbai&checkout=success');
        $cancel_url = admin_url('upload.php?page=bbai&checkout=cancel');

        $result = $this->api_client->create_checkout_session($price_id, $success_url, $cancel_url);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_code = $result->get_error_code();
            $error_message_lower = is_string($error_message) ? strtolower($error_message) : '';

            if ($error_code === 'auth_required' ||
                strpos($error_message_lower, 'session') !== false ||
                strpos($error_message_lower, 'log in') !== false) {
                $error_message = __('Unable to create checkout session. Please try again or contact support.', 'beepbeep-ai-alt-text-generator');
            }

            wp_send_json_error(['message' => $error_message]);
            return;
        }

        wp_send_json_success([
            'url' => $result['url'] ?? '',
            'session_id' => $result['sessionId'] ?? ''
        ]);
    }

    /**
     * AJAX handler: Create customer portal session
     */
    public function ajax_create_portal() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        $is_authenticated = $this->api_client->is_authenticated();
        $is_admin_authenticated = $this->is_admin_authenticated();
        $has_agency_license = false;

        if ($is_admin_authenticated || !$is_authenticated) {
            $has_license = $this->api_client->has_active_license();
            if ($has_license) {
                $license_data = $this->api_client->get_license_data();
                if ($license_data && isset($license_data['organization'])) {
                    $license_plan = strtolower($license_data['organization']['plan'] ?? 'free');
                    $has_agency_license = ($license_plan === 'agency' || $license_plan === 'pro');
                }
            }
        }

        if (!$is_authenticated && !($is_admin_authenticated && $has_agency_license)) {
            wp_send_json_error([
                'message' => __('Please log in to manage billing', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        if ($is_admin_authenticated && $has_agency_license && !$is_authenticated) {
            $stored_portal_url = Usage_Tracker::get_billing_portal_url();
            if (!empty($stored_portal_url)) {
                wp_send_json_success(['url' => $stored_portal_url]);
                return;
            }
        }

        $return_url = admin_url('upload.php?page=bbai');
        $result = $this->api_client->create_customer_portal_session($return_url);

        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
            $error_message = is_string($error_message) ? $error_message : '';
            if (is_string($error_message) && $error_message && (
                strpos((string)$error_message, 'Authentication required') !== false ||
                strpos((string)$error_message, 'Unauthorized') !== false ||
                strpos((string)$error_message, 'not_authenticated') !== false
            )) {
                wp_send_json_error([
                    'message' => __('To manage your subscription, please log in with your account credentials (not just admin access). If you have an agency license, contact support to access billing management.', 'beepbeep-ai-alt-text-generator'),
                    'code' => 'not_authenticated'
                ]);
                return;
            }
            wp_send_json_error(['message' => $error_message]);
            return;
        }

        wp_send_json_success(['url' => $result['url'] ?? '']);
    }

    /**
     * AJAX handler: Get subscription information
     */
    public function ajax_get_subscription_info() {
        $action = 'beepbeepai_nonce';
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ) ], 403 );
            return;
        }
        if ( ! $this->user_can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ) ] );
            return;
        }

        if (!$this->api_client->is_authenticated()) {
            wp_send_json_error([
                'message' => __('Please log in to view subscription information', 'beepbeep-ai-alt-text-generator'),
                'code' => 'not_authenticated'
            ]);
            return;
        }

        $subscription_info = $this->api_client->get_subscription_info();

        if (is_wp_error($subscription_info)) {
            wp_send_json_error(['message' => $subscription_info->get_error_message()]);
            return;
        }

        wp_send_json_success($subscription_info);
    }
}
