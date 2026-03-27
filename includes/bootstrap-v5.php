<?php
/**
 * v5.0 Bootstrap
 *
 * Initializes the v5.0 service-oriented architecture.
 * Sets up DI container, registers services, and wires AJAX routes.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeepBeep\AltText\Core\Container;
use BeepBeep\AltText\Core\Service_Provider;
use BeepBeep\AltText\Core\Router;

/**
 * Initialize v5.0 architecture.
 *
 * Creates DI container, registers services, and wires up routes.
 *
 * @since 5.0.0
 *
 * @return Container The initialized container.
 */
function bbai_init_v5(): Container {
	static $container = null;

	// Return existing container if already initialized.
	if ( null !== $container ) {
		return $container;
	}

	// Create container.
	$container = new Container();

	// Register all services.
	Service_Provider::register( $container );

	// Wire up AJAX routes.
	bbai_register_ajax_routes( $container );

	return $container;
}

/**
 * Register AJAX routes with controllers.
 *
 * Maps WordPress AJAX actions to controller methods.
 *
 * @since 5.0.0
 *
 * @param Container $container DI container.
 * @return void
 */
function bbai_register_ajax_routes( Container $container ): void {
	/** @var Router $router */
	$router = $container->get( 'router' );

	// Authentication routes.
	$router->ajax( 'bbai_register', 'controller.auth', 'register' );
	$router->ajax( 'bbai_login', 'controller.auth', 'login' );
	$router->ajax( 'bbai_logout', 'controller.auth', 'logout' );
	$router->ajax( 'bbai_disconnect_account', 'controller.auth', 'disconnect_account' );
	$router->ajax( 'bbai_admin_login', 'controller.auth', 'admin_login' );
	$router->ajax( 'bbai_admin_logout', 'controller.auth', 'admin_logout' );
	$router->ajax( 'bbai_get_user_info', 'controller.auth', 'get_user_info' );

	// License routes.
	$router->ajax( 'bbai_activate_license', 'controller.license', 'activate_license' );
	$router->ajax( 'bbai_deactivate_license', 'controller.license', 'deactivate_license' );
	$router->ajax( 'bbai_get_license_sites', 'controller.license', 'get_license_sites' );
	$router->ajax( 'bbai_disconnect_license_site', 'controller.license', 'disconnect_license_site' );

	// Generation routes.
	$router->ajax( 'bbai_regenerate_single', 'controller.generation', 'regenerate_single' );
	$router->ajax( 'bbai_bulk_queue', 'controller.generation', 'bulk_queue' );
	$router->ajax( 'bbai_inline_generate', 'controller.generation', 'inline_generate' );

	// Queue routes.
	$router->ajax( 'bbai_queue_retry_job', 'controller.queue', 'retry_job' );
	$router->ajax( 'bbai_queue_retry_failed', 'controller.queue', 'retry_failed' );
	$router->ajax( 'bbai_queue_clear_completed', 'controller.queue', 'clear_completed' );
	$router->ajax( 'bbai_queue_stats', 'controller.queue', 'get_stats' );

	// Initialize router (registers WordPress hooks).
	$router->init();
}

/**
 * Get global container instance.
 *
 * Helper function to access the DI container from anywhere.
 *
 * @since 5.0.0
 *
 * @return Container The DI container.
 */
function bbai_container(): Container {
	return bbai_init_v5();
}

/**
 * Get service from container.
 *
 * Helper function for quick service access.
 *
 * @since 5.0.0
 *
 * @param string $service Service name.
 * @return mixed Service instance.
 */
function bbai_service( string $service ) {
	return bbai_container()->get( $service );
}

// Initialize v5.0 architecture on WordPress init.
add_action(
	'init',
	function () {
		// Only initialize if core classes are loaded.
		if ( class_exists( '\BbAI_Core' ) ) {
			bbai_init_v5();
		}
	},
	5
); // Priority 5 to run early.
