<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Router
 *
 * Handles routing for AJAX requests and REST API endpoints.
 * Routes requests to appropriate controllers.
 *
 * @package BeepBeep\AltText\Core
 * @since   5.0.0
 */
class Router {
	/**
	 * DI container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * AJAX routes.
	 *
	 * @var array<string, array{controller: string, method: string, auth: bool}>
	 */
	private array $ajax_routes = array();

	/**
	 * REST routes.
	 *
	 * @var array<string, array{controller: string, method: string, methods: string, permission_callback: callable|null}>
	 */
	private array $rest_routes = array();

	/**
	 * Constructor.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register AJAX route.
	 *
	 * @since 5.0.0
	 *
	 * @param string $action     AJAX action name.
	 * @param string $controller Controller service name.
	 * @param string $method     Controller method name.
	 * @param bool   $auth       Require authentication (default true).
	 * @return void
	 */
	public function ajax(string $action, string $controller, string $method, bool $auth = true): void {
		$this->ajax_routes[ $action ] = array(
			'controller' => $controller,
			'method'     => $method,
			'auth'       => $auth,
		);
	}

	/**
	 * Register REST route.
	 *
	 * @since 5.0.0
	 *
	 * @param string        $route               REST route pattern.
	 * @param string        $controller          Controller service name.
	 * @param string        $method              Controller method name.
	 * @param string        $methods             HTTP methods (default 'POST').
	 * @param callable|null $permission_callback Permission callback (default requires manage_options).
	 * @return void
	 */
	public function rest(string $route, string $controller, string $method, string $methods = 'POST', ?callable $permission_callback = null): void {
		$this->rest_routes[ $route ] = array(
			'controller'          => $controller,
			'method'              => $method,
			'methods'             => $methods,
			'permission_callback' => $permission_callback,
		);
	}

	/**
	 * Initialize router.
	 *
	 * Registers WordPress hooks for AJAX and REST.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Register AJAX handlers.
		foreach ( $this->ajax_routes as $action => $route ) {
			// Public AJAX.
			add_action(
				"wp_ajax_nopriv_{$action}",
				function () use ( $action, $route ) {
					$this->handle_ajax( $action, $route );
				}
			);

			// Authenticated AJAX.
			add_action(
				"wp_ajax_{$action}",
				function () use ( $action, $route ) {
					$this->handle_ajax( $action, $route );
				}
			);
		}

		// Register REST routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Handle AJAX request.
	 *
	 * @since 5.0.0
	 *
	 * @param string $action AJAX action.
	 * @param array  $route  Route configuration.
	 * @return void
	 */
	private function handle_ajax(string $action, array $route): void {
		try {
			// Verify nonce.
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $action ) ) {
				wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
				return;
			}

			// Check authentication if required.
			if ( $route['auth'] && ! is_user_logged_in() ) {
				wp_send_json_error( array( 'message' => 'Authentication required.' ), 401 );
				return;
			}

			// Get controller and call method.
			$controller = $this->container->get( $route['controller'] );
			$method     = $route['method'];

			if ( ! method_exists( $controller, $method ) ) {
				throw new \Exception( "Method {$method} not found on controller." );
			}

			$result = $controller->$method();

			wp_send_json_success( $result );
		} catch ( \Throwable $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
					'code'    => $e->getCode(),
				),
				500
			);
		}
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		foreach ( $this->rest_routes as $route => $config ) {
			// Use route-specific permission callback or default to manage_options for security.
			$permission_callback = $config['permission_callback'] ?? array( $this, 'default_permission_callback' );

			register_rest_route(
				'bbai/v1',
				$route,
				array(
					'methods'             => $config['methods'],
					'callback'            => function ( \WP_REST_Request $request ) use ( $config ) {
						return $this->handle_rest( $request, $config );
					},
					'permission_callback' => $permission_callback,
				)
			);
		}
	}

	/**
	 * Default permission callback - requires manage_options for security.
	 *
	 * Routes should specify their own permission_callback for more granular control.
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request instance.
	 * @return bool
	 */
	public function default_permission_callback( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle REST request.
	 *
	 * @since 5.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param array            $config  Route configuration.
	 * @return \WP_REST_Response|\WP_Error Response.
	 */
	private function handle_rest( \WP_REST_Request $request, array $config ) {
		try {
			// Get controller and call method.
			$controller = $this->container->get( $config['controller'] );
			$method     = $config['method'];

			if ( ! method_exists( $controller, $method ) ) {
				return new \WP_Error(
					'method_not_found',
					"Method {$method} not found on controller.",
					array( 'status' => 500 )
				);
			}

			$result = $controller->$method( $request );

			// If result is WP_Error, return it.
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// If result is already a WP_REST_Response, return it.
			if ( $result instanceof \WP_REST_Response ) {
				return $result;
			}

			// Otherwise wrap in REST response.
			return new \WP_REST_Response( $result, 200 );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'server_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get all registered AJAX routes.
	 *
	 * @since 5.0.0
	 *
	 * @return array<string, array> AJAX routes.
	 */
	public function get_ajax_routes(): array {
		return $this->ajax_routes;
	}

	/**
	 * Get all registered REST routes.
	 *
	 * @since 5.0.0
	 *
	 * @return array<string, array> REST routes.
	 */
	public function get_rest_routes(): array {
		return $this->rest_routes;
	}
}
