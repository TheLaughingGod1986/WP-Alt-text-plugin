<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Services\License_Service;

/**
 * License Controller
 *
 * Handles HTTP requests for license operations.
 * Thin controller layer that delegates to LicenseService.
 *
 * @package BeepBeep\AltText\Controllers
 * @since   5.0.0
 */
class License_Controller {
	/**
	 * License service.
	 *
	 * @var License_Service
	 */
	private License_Service $license_service;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param License_Service $license_service License service.
	 */
	public function __construct( License_Service $license_service ) {
		$this->license_service = $license_service;
	}

	/**
	 * Handle activate license request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function activate_license(): array {
		$action = 'bbai_activate_license';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$license_key     = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

		return $this->license_service->activate( $license_key );
	}

	/**
	 * Handle deactivate license request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function deactivate_license(): array {
		$action = 'bbai_deactivate_license';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->license_service->deactivate();
	}

	/**
	 * Handle get license sites request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function get_license_sites(): array {
		$action = 'bbai_get_license_sites';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->license_service->get_license_sites();
	}

	/**
	 * Handle disconnect license site request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function disconnect_license_site(): array {
		$action = 'bbai_disconnect_license_site';
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), $action ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid nonce.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$site_id     = isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : '';

		return $this->license_service->disconnect_site( $site_id );
	}

	/**
	 * Check if current user can manage plugin.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if user can manage.
	 */
	private function user_can_manage(): bool {
		return current_user_can( 'manage_options' );
	}
}
