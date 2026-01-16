<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Services\Authentication_Service;

/**
 * Authentication Controller
 *
 * Handles HTTP requests for authentication operations.
 * Thin controller layer that delegates to AuthenticationService.
 *
 * @package BeepBeep\AltText\Controllers
 * @since   5.0.0
 */
class Auth_Controller {
	/**
	 * Authentication service.
	 *
	 * @var Authentication_Service
	 */
	private Authentication_Service $auth_service;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param Authentication_Service $auth_service Authentication service.
	 */
	public function __construct( Authentication_Service $auth_service ) {
		$this->auth_service = $auth_service;
	}

	/**
	 * Handle register request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function register(): array {
		// Only admins can register/connect accounts.
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Only administrators can connect accounts.', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email     = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password  = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		return $this->auth_service->register( $email, $password );
	}

	/**
	 * Handle login request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function login(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$email_raw = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email     = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password  = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		return $this->auth_service->login( $email, $password );
	}

	/**
	 * Handle logout request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function logout(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->auth_service->logout();
	}

	/**
	 * Handle disconnect account request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function disconnect_account(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->auth_service->disconnect_account();
	}

	/**
	 * Handle admin login request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function admin_login(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		$email_raw    = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
		$email        = is_string( $email_raw ) ? sanitize_email( $email_raw ) : '';
		$password_raw = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
		$password     = is_string( $password_raw ) ? $password_raw : '';

		return $this->auth_service->admin_login( $email, $password );
	}

	/**
	 * Handle admin logout request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function admin_logout(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->auth_service->admin_logout();
	}

	/**
	 * Handle get user info request.
	 *
	 * @since 5.0.0
	 *
	 * @return array Response data.
	 */
	public function get_user_info(): array {
		// Check permission.
		if ( ! $this->user_can_manage() ) {
			return array(
				'success' => false,
				'message' => __( 'Unauthorized', 'beepbeep-ai-alt-text-generator' ),
			);
		}

		return $this->auth_service->get_user_info();
	}

	/**
	 * Check if current user can manage plugin.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if user can manage.
	 */
	private function user_can_manage(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'upload_files' );
	}
}
