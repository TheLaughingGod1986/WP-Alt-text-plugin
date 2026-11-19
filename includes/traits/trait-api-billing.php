<?php
/**
 * API Billing Trait
 * Handles Stripe checkout and billing operations
 *
 * @package BeepBeep_AI
 * @since 5.0.0
 */

namespace BeepBeepAI\AltTextGenerator\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

trait Api_Billing {

	/**
	 * Get billing info
	 */
	public function get_billing_info() {
		if ( ! $this->is_authenticated() ) {
			return new \WP_Error( 'not_authenticated', __( 'Authentication required', 'beepbeep-ai-alt-text-generator' ) );
		}

		$response = $this->make_request( '/billing/info', 'GET' );

		return $response;
	}

	/**
	 * Get available plans
	 */
	public function get_plans() {
		$response = $this->make_request( '/plans', 'GET' );

		return $response;
	}

	/**
	 * Create Stripe checkout session
	 */
	public function create_checkout_session( $price_id, $success_url, $cancel_url, array $attribution = array() ) {
		$site_fingerprint = $this->get_site_fingerprint();

		$data = array(
			'price_id'         => $price_id,
			'success_url'      => $success_url,
			'cancel_url'       => $cancel_url,
			'site_url'         => get_site_url(),
			'site_fingerprint' => $site_fingerprint,
		);

		if ( class_exists( '\BeepBeepAI\AltTextGenerator\BBAI_Attribution' ) ) {
			$stored_attribution = \BeepBeepAI\AltTextGenerator\BBAI_Attribution::get_payload();
			$data               = array_merge( $data, \BeepBeepAI\AltTextGenerator\BBAI_Attribution::for_checkout( array_merge( $stored_attribution, $attribution ) ) );
		} elseif ( ! empty( $attribution ) ) {
			$data = array_merge( $data, $attribution );
		}

		if ( defined( 'BEEPBEEP_AI_VERSION' ) ) {
			$data['plugin_version'] = (string) BEEPBEEP_AI_VERSION;
		}
		if ( function_exists( '\BeepBeepAI\AltTextGenerator\bbai_get_site_install_id' ) ) {
			$data['site_install_id'] = sanitize_text_field( (string) \BeepBeepAI\AltTextGenerator\bbai_get_site_install_id() );
		}

		// Which plugin the checkout came from (set last so attribution cannot change it).
		$data['plugin'] = 'alt_text';

		return $this->make_request( '/billing/checkout', 'POST', $data );
	}

	/**
	 * Create customer portal session
	 */
	public function create_customer_portal_session( $return_url ) {
		if ( ! $this->is_authenticated() ) {
			return new \WP_Error( 'not_authenticated', __( 'Authentication required', 'beepbeep-ai-alt-text-generator' ) );
		}

		$response = $this->make_request(
			'/billing/portal',
			'POST',
			array(
				'return_url' => $return_url,
			)
		);

		return $response;
	}

	/**
	 * Get subscription info
	 */
	public function get_subscription_info() {
		if ( ! $this->is_authenticated() ) {
			return new \WP_Error( 'not_authenticated', __( 'Authentication required', 'beepbeep-ai-alt-text-generator' ) );
		}

		$response = $this->make_request( '/billing/subscription', 'GET' );

		return $response;
	}
}
