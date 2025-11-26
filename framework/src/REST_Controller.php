<?php
/**
 * Base REST Controller
 *
 * Provides a foundation for plugin REST API routes with automatic nonce verification,
 * standard permission callbacks, and optional framework API endpoints.
 *
 * @package Optti\Framework
 */

namespace Optti\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST_Controller
 *
 * Base REST controller for Optti plugins.
 */
abstract class REST_Controller {

	/**
	 * Plugin instance.
	 *
	 * @var PluginBase
	 */
	protected $plugin;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Constructor.
	 *
	 * @param PluginBase $plugin Plugin instance.
	 */
	public function __construct( PluginBase $plugin ) {
		$this->plugin    = $plugin;
		$this->namespace = $plugin->get_plugin_slug() . '/v1';
	}

	/**
	 * Register REST routes.
	 * Override this method in child classes to register plugin-specific routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Optionally expose framework APIs as REST endpoints.
		if ( $this->should_expose_framework_apis() ) {
			$this->register_framework_routes();
		}

		// Register plugin-specific routes.
		$this->register_plugin_routes();
	}

	/**
	 * Check if framework APIs should be exposed.
	 * Override to return true if you want framework auth/billing endpoints.
	 *
	 * @return bool True to expose framework APIs.
	 */
	protected function should_expose_framework_apis() {
		return false;
	}

	/**
	 * Register framework API routes (auth, billing, license, usage).
	 *
	 * @return void
	 */
	protected function register_framework_routes() {
		$api = ApiClient::instance();

		// Authentication routes.
		register_rest_route(
			$this->namespace,
			'/auth/login',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_auth_login' ],
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => [
					'email'    => [
						'required' => true,
						'type'     => 'string',
						'validate_callback' => 'is_email',
					],
					'password' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/auth/register',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_auth_register' ],
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => [
					'email'    => [
						'required' => true,
						'type'     => 'string',
						'validate_callback' => 'is_email',
					],
					'password' => [
						'required' => true,
						'type'     => 'string',
						'minLength' => 8,
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/auth/user',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_auth_user' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/auth/forgot-password',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_auth_forgot_password' ],
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => [
					'email' => [
						'required' => true,
						'type'     => 'string',
						'validate_callback' => 'is_email',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/auth/reset-password',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_auth_reset_password' ],
				'permission_callback' => '__return_true', // Public endpoint.
				'args'                => [
					'email'        => [
						'required' => true,
						'type'     => 'string',
						'validate_callback' => 'is_email',
					],
					'token'        => [
						'required' => true,
						'type'     => 'string',
					],
					'new_password' => [
						'required' => true,
						'type'     => 'string',
						'minLength' => 8,
					],
				],
			]
		);

		// Billing routes.
		register_rest_route(
			$this->namespace,
			'/billing/plans',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_billing_plans' ],
				'permission_callback' => '__return_true', // Public endpoint.
			]
		);

		register_rest_route(
			$this->namespace,
			'/billing/checkout',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_billing_checkout' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => [
					'price_id'    => [
						'required' => true,
						'type'     => 'string',
					],
					'success_url' => [
						'required' => false,
						'type'     => 'string',
					],
					'cancel_url'  => [
						'required' => false,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/billing/portal',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_billing_portal' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => [
					'return_url' => [
						'required' => false,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/billing/subscription',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_billing_subscription' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			]
		);

		// License routes.
		register_rest_route(
			$this->namespace,
			'/license/validate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_license_validate' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => [
					'license_key' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/license/activate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_license_activate' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => [
					'license_key' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/license/deactivate',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_license_deactivate' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
				'args'                => [
					'license_key' => [
						'required' => true,
						'type'     => 'string',
					],
				],
			]
		);

		// Usage routes.
		register_rest_route(
			$this->namespace,
			'/usage',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_usage' ],
				'permission_callback' => [ $this, 'can_manage_options' ],
			]
		);
	}

	/**
	 * Register plugin-specific routes.
	 * Override this method in child classes.
	 *
	 * @return void
	 */
	protected function register_plugin_routes() {
		// Plugin-specific routes should be registered here.
	}

	/**
	 * Standard permission callback: can manage options.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if user can manage options.
	 */
	public function can_manage_options( \WP_REST_Request $request = null ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verify REST API nonce.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if nonce is valid.
	 */
	protected function verify_nonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Handle authentication login.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_auth_login( \WP_REST_Request $request ) {
		$api = ApiClient::instance();
		$email = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		$result = $api->login( $email, $password );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle authentication register.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_auth_register( \WP_REST_Request $request ) {
		$api = ApiClient::instance();
		$email = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );

		$result = $api->register( $email, $password );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get user info.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_auth_user( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$force_refresh = $request->get_param( 'force_refresh' ) === 'true';
		$result = $api->get_user_info( $force_refresh );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle forgot password.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_auth_forgot_password( \WP_REST_Request $request ) {
		$api = ApiClient::instance();
		$email = $request->get_param( 'email' );

		$result = $api->forgot_password( $email );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle reset password.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_auth_reset_password( \WP_REST_Request $request ) {
		$api = ApiClient::instance();
		$email = $request->get_param( 'email' );
		$token = $request->get_param( 'token' );
		$new_password = $request->get_param( 'new_password' );

		$result = $api->reset_password( $email, $token, $new_password );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get billing plans.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_billing_plans( \WP_REST_Request $request ) {
		$api = ApiClient::instance();
		$force_refresh = $request->get_param( 'force_refresh' ) === 'true';
		$result = $api->get_plans( $force_refresh );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle create checkout session.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_billing_checkout( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$price_id = $request->get_param( 'price_id' );
		$success_url = $request->get_param( 'success_url' ) ?: admin_url();
		$cancel_url = $request->get_param( 'cancel_url' ) ?: admin_url();

		$result = $api->create_checkout_session( $price_id, $success_url, $cancel_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle create customer portal session.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_billing_portal( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$return_url = $request->get_param( 'return_url' ) ?: admin_url();

		$result = $api->create_customer_portal_session( $return_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get subscription info.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_billing_subscription( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$force_refresh = $request->get_param( 'force_refresh' ) === 'true';
		$result = $api->get_subscription_info( $force_refresh );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle validate license.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_license_validate( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$license_key = $request->get_param( 'license_key' );

		$result = $api->validate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle activate license.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_license_activate( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$license_manager = LicenseManager::instance();
		$license_key = $request->get_param( 'license_key' );
		$fingerprint = $license_manager->get_fingerprint();

		$result = $api->activate_license( $license_key, $fingerprint );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update license data if activation successful.
		if ( isset( $result['license'] ) ) {
			$api->set_license_key( $license_key );
			$api->set_license_data( $result['license'] );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle deactivate license.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_license_deactivate( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$license_key = $request->get_param( 'license_key' );

		$result = $api->deactivate_license( $license_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Clear license data if deactivation successful.
		if ( ! is_wp_error( $result ) ) {
			$api->set_license_key( '' );
			$api->set_license_data( null );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Handle get usage.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	public function handle_usage( \WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Security check failed. Please refresh the page and try again.', 'beepbeep-ai-alt-text-generator' ),
				[ 'status' => 403 ]
			);
		}

		$api = ApiClient::instance();
		$result = $api->get_usage();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}

