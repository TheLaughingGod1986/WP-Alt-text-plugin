<?php
/**
 * Bootstrap - Initialize Framework
 *
 * This file initializes the DI container, registers services, and sets up routes.
 * Think of this as the "wiring diagram" for your plugin.
 *
 * @package MyPlugin
 * @since   1.0.0
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MyPlugin\Core\Container;
use MyPlugin\Core\Service_Provider;
use MyPlugin\Core\Router;

/**
 * Initialize the plugin's DI container and framework.
 *
 * This function creates the dependency injection container, registers all services,
 * and sets up routing. It uses a static variable to ensure initialization happens
 * only once (singleton pattern).
 *
 * @since 1.0.0
 *
 * @return Container The initialized DI container.
 */
function myplugin_init(): Container {
	static $container = null;

	// Return existing container if already initialized
	if ( null !== $container ) {
		return $container;
	}

	// Create new container
	$container = new Container();

	// Register all services via Service Provider
	Service_Provider::register( $container );

	// Setup AJAX and REST API routes
	myplugin_register_routes( $container );

	/**
	 * Fires after the plugin container is initialized.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container The DI container instance.
	 */
	do_action( 'myplugin_initialized', $container );

	return $container;
}

/**
 * Register AJAX and REST API routes.
 *
 * Map WordPress AJAX actions and REST endpoints to controller methods.
 * All routes go through the router which handles nonce verification,
 * authentication, and error handling automatically.
 *
 * @since 1.0.0
 *
 * @param Container $container The DI container.
 * @return void
 */
function myplugin_register_routes( Container $container ): void {
	/** @var Router $router */
	$router = $container->get( 'router' );

	// ==========================================
	// AJAX Routes
	// ==========================================

	// Example: Register AJAX action
	// WordPress action: wp_ajax_my_action
	// Controller: controller.example service
	// Method: handle_action method
	// Auth: true (requires logged-in user)
	$router->ajax(
		'my_action',              // AJAX action name
		'controller.example',     // Controller service name
		'handle_action',          // Controller method
		true                      // Require authentication
	);

	// Add more AJAX routes here
	// $router->ajax('another_action', 'controller.other', 'method_name');

	// ==========================================
	// REST API Routes
	// ==========================================

	// Example: Register REST endpoint
	// URL: /wp-json/myplugin/v1/items
	// Controller: controller.example
	// Method: get_items
	// HTTP Method: GET
	$router->rest(
		'/items',                 // Route pattern
		'controller.example',     // Controller service
		'get_items',             // Controller method
		'GET'                    // HTTP method(s)
	);

	// Add more REST routes here
	// $router->rest('/items/(?P<id>\d+)', 'controller.example', 'get_item', 'GET');
	// $router->rest('/items', 'controller.example', 'create_item', 'POST');

	// Initialize router (registers all WordPress hooks)
	$router->init();
}

/**
 * Get the global DI container instance.
 *
 * Helper function for easy access to the container from anywhere in your code.
 *
 * Example usage:
 * ```php
 * $container = myplugin_container();
 * $service = $container->get('service.example');
 * ```
 *
 * @since 1.0.0
 *
 * @return Container The DI container.
 */
function myplugin_container(): Container {
	return myplugin_init();
}

/**
 * Get a service from the container.
 *
 * Convenience function for quick service access without getting the container first.
 *
 * Example usage:
 * ```php
 * $service = myplugin_service('service.example');
 * $result = $service->do_something();
 * ```
 *
 * @since 1.0.0
 *
 * @param string $service Service name as registered in Service_Provider.
 * @return mixed The service instance.
 * @throws \Exception If service is not found.
 */
function myplugin_service( string $service ) {
	return myplugin_container()->get( $service );
}

/**
 * Initialize the framework on WordPress init hook.
 *
 * Priority 5 ensures we initialize early, before most other plugins.
 */
add_action(
	'init',
	function () {
		myplugin_init();
	},
	5
);
