<?php
/**
 * Billing Client
 * Handles billing and subscription-related API endpoints
 */

namespace BeepBeepAI\AltTextGenerator\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Billing_Client {

	private $request_handler;
	private $get_token_callback;
	private $clear_token_callback;

	public function __construct( $request_handler, $callbacks = array() ) {
		$this->request_handler      = $request_handler;
		$this->get_token_callback   = $callbacks['get_token'] ?? null;
		$this->clear_token_callback = $callbacks['clear_token'] ?? null;
	}

	/**
	 * Get billing information
	 */
	public function get_billing_info() {
		$response = $this->request_handler->make_request( '/billing/info' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data']['billing'] ?? array();
		}

		return new \WP_Error(
			'billing_failed',
			$response['data']['error'] ?? __( 'Failed to get billing info', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get available plans
	 */
	public function get_plans() {
		$response = $this->request_handler->make_request( '/billing/plans' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data']['plans'] ?? array();
		}

		return new \WP_Error(
			'plans_failed',
			$response['data']['error'] ?? __( 'Failed to fetch pricing plans', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Create checkout session
	 */
	public function create_checkout_session( $price_id, $success_url, $cancel_url ) {
		$token     = $this->get_token_callback ? call_user_func( $this->get_token_callback ) : '';
		$had_token = ! empty( $token );

		// First attempt: with token if available
		$response = $this->request_handler->make_request(
			'/billing/checkout',
			'POST',
			array(
				'priceId'    => $price_id,
				'successUrl' => $success_url,
				'cancelUrl'  => $cancel_url,
			)
		);

		// Check for "user not found" errors
		$is_user_not_found_error = false;
		if ( is_wp_error( $response ) ) {
			$error_code    = $response->get_error_code();
			$error_message = strtolower( $response->get_error_message() );
			$error_data    = $response->get_error_data();

			$is_user_not_found_error = $error_code === 'user_not_found' ||
										strpos( $error_message, 'user not found' ) !== false ||
										strpos( $error_message, 'user does not exist' ) !== false ||
										( is_array( $error_data ) && isset( $error_data['code'] ) &&
										( $error_data['code'] === 'user_not_found' ||
										strpos( strtolower( $error_data['code'] ), 'user_not_found' ) !== false ) ) ||
										( is_array( $error_data ) && isset( $error_data['backend_message'] ) &&
										is_string( $error_data['backend_message'] ) &&
										strpos( strtolower( $error_data['backend_message'] ), 'user not found' ) !== false ) ||
										( is_array( $error_data ) && isset( $error_data['error_details'] ) &&
										is_string( $error_data['error_details'] ) &&
										strpos( strtolower( $error_data['error_details'] ), 'user not found' ) !== false );
		} elseif ( isset( $response['status_code'] ) && $response['status_code'] >= 500 ) {
			$response_data = $response['data'] ?? array();
			$error_body    = '';
			if ( isset( $response_data['error'] ) && is_string( $response_data['error'] ) ) {
				$error_body = strtolower( $response_data['error'] );
			} elseif ( isset( $response_data['message'] ) && is_string( $response_data['message'] ) ) {
				$error_body = strtolower( $response_data['message'] );
			}
			$is_user_not_found_error = strpos( $error_body, 'user not found' ) !== false ||
										strpos( $error_body, 'user does not exist' ) !== false;
		}

		// If "user not found" and we had a token, clear token and retry without auth
		if ( $is_user_not_found_error && $had_token ) {
			if ( $this->clear_token_callback ) {
				call_user_func( $this->clear_token_callback );
			}
			delete_transient( 'bbai_token_last_check' );

			// Retry without token (guest checkout)
			$response = $this->request_handler->make_request(
				'/billing/checkout',
				'POST',
				array(
					'priceId'    => $price_id,
					'successUrl' => $success_url,
					'cancelUrl'  => $cancel_url,
				)
			);
		}

		// Handle errors
		if ( is_wp_error( $response ) ) {
			$error_code    = $response->get_error_code();
			$error_message = $response->get_error_message();
			$error_lower   = strtolower( $error_message );
			$error_data    = $response->get_error_data();

			if ( strpos( $error_lower, 'session' ) !== false ||
				strpos( $error_lower, 'log in' ) !== false ||
				strpos( $error_lower, 'authenticated' ) !== false ||
				strpos( $error_lower, 'user not found' ) !== false ||
				strpos( $error_lower, 'user does not exist' ) !== false ||
				$error_code === 'user_not_found' ||
				$error_code === 'auth_required' ||
				( is_array( $error_data ) && isset( $error_data['status_code'] ) && $error_data['status_code'] >= 500 ) ) {
				return new \WP_Error(
					'checkout_failed',
					__( 'Unable to create checkout session. This may be a temporary backend issue. Please try again in a moment or contact support if the problem persists.', 'beepbeep-ai-alt-text-generator' ),
					array( 'response' => $error_data )
				);
			}
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'];
		}

		$error_message = '';
		if ( isset( $response['data']['error'] ) && is_string( $response['data']['error'] ) ) {
			$error_message = $response['data']['error'];
		} elseif ( isset( $response['data']['message'] ) && is_string( $response['data']['message'] ) ) {
			$error_message = $response['data']['message'];
		} elseif ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
			$error_message = wp_json_encode( $response['data'] );
		}

		if ( ! $error_message ) {
			$error_message = __( 'Failed to create checkout session', 'beepbeep-ai-alt-text-generator' );
		}

		return new \WP_Error(
			'checkout_failed',
			$error_message,
			array( 'response' => $response )
		);
	}

	/**
	 * Create customer portal session
	 */
	public function create_customer_portal_session( $return_url ) {
		$response = $this->request_handler->make_request(
			'/billing/create-portal',
			'POST',
			array(
				'returnUrl' => $return_url,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'];
		}

		return new \WP_Error(
			'portal_failed',
			$response['data']['error'] ?? __( 'Failed to create customer portal session', 'beepbeep-ai-alt-text-generator' )
		);
	}

	/**
	 * Get subscription information
	 */
	public function get_subscription_info() {
		$response = $this->request_handler->make_request( '/billing/subscription' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response['success'] ) {
			return $response['data'];
		}

		return new \WP_Error(
			'subscription_info_failed',
			$response['data']['error'] ?? __( 'Failed to fetch subscription information', 'beepbeep-ai-alt-text-generator' )
		);
	}
}
