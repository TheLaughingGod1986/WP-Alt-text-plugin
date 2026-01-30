<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Core\Event_Bus;

/**
 * Authentication Service
 *
 * Handles user authentication, registration, and session management.
 * Extracted from monolithic BbAI_Core class for better separation of concerns.
 *
 * @package BeepBeep\AltText\Services
 * @since   5.0.0
 */
class Authentication_Service {
	/**
	 * API client instance.
	 *
	 * @var \BbAI_API_Client_V2
	 */
	private \BbAI_API_Client_V2 $api_client;

	/**
	 * Event bus instance.
	 *
	 * @var Event_Bus
	 */
	private Event_Bus $event_bus;

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param \BbAI_API_Client_V2 $api_client API client.
	 * @param Event_Bus           $event_bus Event bus.
	 */
	public function __construct( \BbAI_API_Client_V2 $api_client, Event_Bus $event_bus ) {
		$this->api_client = $api_client;
		$this->event_bus  = $event_bus;
	}

	/**
	 * Register a new user account.
	 *
	 * @since 5.0.0
	 *
	 * @param string $email    User email.
	 * @param string $password User password.
	 * @return array{success: bool, message: string, user?: array, code?: string} Registration result.
	 */
	public function register( string $email, string $password ): array {
		// Validate inputs.
		if ( empty( $email ) || empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email and password are required', 'opptiai-alt' ),
			);
		}

		// Check if site already has an account.
		$existing_token = $this->api_client->get_token();
		if ( ! empty( $existing_token ) ) {
			// Check if it's a free plan.
			$usage = $this->api_client->get_usage();
			if ( ! is_wp_error( $usage ) && isset( $usage['plan'] ) && 'free' === $usage['plan'] ) {
				return array(
					'success' => false,
					'message' => __( 'This site is already linked to a free account. Ask an administrator to upgrade to Growth or Agency for higher limits.', 'opptiai-alt' ),
					'code'    => 'free_plan_exists',
				);
			}
		}

		// Attempt registration via API.
		$result = $this->api_client->register( $email, $password );

		if ( is_wp_error( $result ) ) {
			$error_code    = $result->get_error_code();
			$error_message = $result->get_error_message();

			// Handle free plan already used error.
			if ( 'free_plan_exists' === $error_code || ( is_string( $error_message ) && false !== strpos( strtolower( $error_message ), 'free plan' ) ) ) {
				return array(
					'success' => false,
					'message' => __( 'A free plan has already been used for this site. Upgrade to Growth or Agency to increase your quota.', 'opptiai-alt' ),
					'code'    => 'free_plan_exists',
				);
			}

			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		// Clear quota cache after successful registration.
		if ( class_exists( '\BeepBeepAI\AltTextGenerator\Token_Quota_Service' ) ) {
			\BeepBeepAI\AltTextGenerator\Token_Quota_Service::clear_cache();
		}

		// Emit event.
		$this->event_bus->emit( 'user_registered', $result );

		return array(
			'success' => true,
			'message' => __( 'Account created successfully', 'opptiai-alt' ),
			'user'    => $result['user'] ?? null,
		);
	}

	/**
	 * Log in existing user.
	 *
	 * @since 5.0.0
	 *
	 * @param string $email    User email.
	 * @param string $password User password.
	 * @return array{success: bool, message: string, user?: array} Login result.
	 */
	public function login( string $email, string $password ): array {
		// Validate inputs.
		if ( empty( $email ) || empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Email and password are required', 'opptiai-alt' ),
			);
		}

		// Attempt login via API.
		$result = $this->api_client->login( $email, $password );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Emit event.
		$this->event_bus->emit( 'user_logged_in', $result );

		return array(
			'success' => true,
			'message' => __( 'Logged in successfully', 'opptiai-alt' ),
			'user'    => $result['user'] ?? null,
		);
	}

	/**
	 * Log out current user.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string} Logout result.
	 */
	public function logout(): array {
		$this->api_client->clear_token();

		// Emit event.
		$this->event_bus->emit( 'user_logged_out', null );

		return array(
			'success' => true,
			'message' => __( 'Logged out successfully', 'opptiai-alt' ),
		);
	}

	/**
	 * Disconnect account from site.
	 *
	 * Clears all authentication tokens, license keys, and cached data.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string} Disconnect result.
	 */
	public function disconnect_account(): array {
		// Clear JWT token (for authenticated users).
		$this->api_client->clear_token();

		// Clear license key (for agency/license-based users).
		$this->api_client->clear_license_key();

		// Clear user data.
		delete_option( 'opptibbai_user_data' );
		delete_option( 'opptibbai_site_id' );

		// Clear usage cache.
		if ( class_exists( '\BbAI_Usage_Tracker' ) ) {
			\BbAI_Usage_Tracker::clear_cache();
		}
		delete_transient( 'bbai_usage_cache' );
		delete_transient( 'opptibbai_usage_cache' );
		delete_transient( 'opptibbai_token_last_check' );

		// Emit event.
		$this->event_bus->emit( 'account_disconnected', null );

		return array(
			'success' => true,
			'message' => __( 'Account disconnected. Please sign in again to reconnect.', 'opptiai-alt' ),
		);
	}

	/**
	 * Admin login for agency users.
	 *
	 * @since 5.0.0
	 *
	 * @param string $email    Admin email.
	 * @param string $password Admin password.
	 * @return array{success: bool, message: string, redirect?: string} Login result.
	 */
	public function admin_login( string $email, string $password ): array {
		// Verify agency license.
		$has_license  = $this->api_client->has_active_license();
		$license_data = $this->api_client->get_license_data();
		$is_agency    = false;

		if ( $has_license && $license_data && isset( $license_data['organization'] ) ) {
			$license_plan = strtolower( $license_data['organization']['plan'] ?? 'free' );
			$is_agency    = ( 'agency' === $license_plan );
		}

		if ( ! $is_agency ) {
			return array(
				'success' => false,
				'message' => __( 'Admin access is only available for agency licenses', 'opptiai-alt' ),
			);
		}

		// Validate email.
		if ( empty( $email ) || ! is_email( $email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid email address', 'opptiai-alt' ),
			);
		}

		// Validate password.
		if ( empty( $password ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter your password', 'opptiai-alt' ),
			);
		}

		// Attempt login.
		$result = $this->api_client->login( $email, $password );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message() ?: __( 'Login failed. Please check your credentials.', 'opptiai-alt' ),
			);
		}

		// Set admin session.
		$this->set_admin_session();

		// Emit event.
		$this->event_bus->emit( 'admin_logged_in', $result );

		return array(
			'success'  => true,
			'message'  => __( 'Successfully logged in', 'opptiai-alt' ),
			'redirect' => add_query_arg( array( 'tab' => 'admin' ), admin_url( 'upload.php?page=bbai' ) ),
		);
	}

	/**
	 * Admin logout.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message: string, redirect?: string} Logout result.
	 */
	public function admin_logout(): array {
		$this->clear_admin_session();

		// Emit event.
		$this->event_bus->emit( 'admin_logged_out', null );

		return array(
			'success'  => true,
			'message'  => __( 'Logged out successfully', 'opptiai-alt' ),
			'redirect' => add_query_arg( array( 'tab' => 'admin' ), admin_url( 'upload.php?page=bbai' ) ),
		);
	}

	/**
	 * Get current user info.
	 *
	 * @since 5.0.0
	 *
	 * @return array{success: bool, message?: string, user?: array, usage?: array, code?: string} User info result.
	 */
	public function get_user_info(): array {
		if ( ! $this->api_client->is_authenticated() ) {
			return array(
				'success' => false,
				'message' => __( 'Not authenticated', 'opptiai-alt' ),
				'code'    => 'not_authenticated',
			);
		}

		$user_info = $this->api_client->get_user_info();
		$usage     = $this->api_client->get_usage();

		if ( is_wp_error( $user_info ) ) {
			return array(
				'success' => false,
				'message' => $user_info->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'user'    => $user_info,
			'usage'   => is_wp_error( $usage ) ? null : $usage,
		);
	}

	/**
	 * Check if user is authenticated.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if authenticated.
	 */
	public function is_authenticated(): bool {
		return $this->api_client->is_authenticated();
	}

	/**
	 * Check if admin is authenticated.
	 *
	 * @since 5.0.0
	 *
	 * @return bool True if admin session is valid.
	 */
	public function is_admin_authenticated(): bool {
		// Check if we have a valid admin session.
		$admin_session = get_transient( 'bbai_admin_session_' . get_current_user_id() );
		if ( false === $admin_session || empty( $admin_session ) ) {
			return false;
		}

		// Verify session hasn't expired (24 hours).
		$session_time = get_transient( 'bbai_admin_session_time_' . get_current_user_id() );
		if ( false === $session_time || ( time() - intval( $session_time ) ) > ( 24 * HOUR_IN_SECONDS ) ) {
			$this->clear_admin_session();
			return false;
		}

		return true;
	}

	/**
	 * Set admin session.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	private function set_admin_session(): void {
		$user_id = get_current_user_id();
		set_transient( 'bbai_admin_session_' . $user_id, 'authenticated', DAY_IN_SECONDS );
		set_transient( 'bbai_admin_session_time_' . $user_id, time(), DAY_IN_SECONDS );
	}

	/**
	 * Clear admin session.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	private function clear_admin_session(): void {
		$user_id = get_current_user_id();
		delete_transient( 'bbai_admin_session_' . $user_id );
		delete_transient( 'bbai_admin_session_time_' . $user_id );
	}
}
