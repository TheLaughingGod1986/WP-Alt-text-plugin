<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service Provider
 *
 * Registers all services with the dependency injection container.
 * This is the central location for service configuration.
 *
 * @package BeepBeep\AltText\Core
 * @since   5.0.0
 */
class Service_Provider {
	/**
	 * Register all services.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	public static function register( Container $container ): void {
		// Register core services.
		self::register_core( $container );

		// Register API clients.
		self::register_api_clients( $container );

		// Register repositories.
		self::register_repositories( $container );

		// Register business services.
		self::register_services( $container );

		// Register controllers.
		self::register_controllers( $container );
	}

	/**
	 * Register core framework services.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_core( Container $container ): void {
		// Event bus - singleton.
		$container->singleton(
			'event_bus',
			function ( $c ) {
				return new Event_Bus();
			}
		);

		// Router - singleton.
		$container->singleton(
			'router',
			function ( $c ) {
				return new Router( $c );
			}
		);
	}

	/**
	 * Register API client services.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_api_clients( Container $container ): void {
		// BeepBeep API client - singleton.
		$container->singleton(
			'api.beepbeep',
			function ( $c ) {
				// Use existing API client instance.
				return \BbAI_API_Client_V2::get_instance();
			}
		);

		// Legacy core instance - temporary during migration.
		$container->singleton(
			'core',
			function ( $c ) {
				// Get the global BbAI_Core instance.
				global $bbai_core;
				if ( $bbai_core instanceof \BbAI_Core ) {
					return $bbai_core;
				}
				// Fallback: try to instantiate if global not set.
				return \BbAI_Core::get_instance();
			}
		);
	}

	/**
	 * Register repository services.
	 *
	 * Repositories handle data access layer.
	 * Note: Repositories will be implemented in future phases.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_repositories( Container $container ): void {
		// Repositories will be added in future phases as needed.
		// For now, services interact with legacy classes directly.
	}

	/**
	 * Register business logic services.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_services( Container $container ): void {
		// Authentication service - singleton.
		$container->singleton(
			'service.auth',
			function ( $c ) {
				return new \BeepBeep\AltText\Services\Authentication_Service(
					$c->get( 'api.beepbeep' ),
					$c->get( 'event_bus' )
				);
			}
		);

		// License service - singleton.
		$container->singleton(
			'service.license',
			function ( $c ) {
				return new \BeepBeep\AltText\Services\License_Service(
					$c->get( 'api.beepbeep' ),
					$c->get( 'event_bus' )
				);
			}
		);

		// Usage service - singleton.
		$container->singleton(
			'service.usage',
			function ( $c ) {
				return new \BeepBeep\AltText\Services\Usage_Service(
					$c->get( 'api.beepbeep' )
				);
			}
		);

		// Generation service - singleton.
		$container->singleton(
			'service.generation',
			function ( $c ) {
				return new \BeepBeep\AltText\Services\Generation_Service(
					$c->get( 'api.beepbeep' ),
					$c->get( 'service.usage' ),
					$c->get( 'event_bus' ),
					$c->get( 'core' )
				);
			}
		);

		// Queue service - singleton.
		$container->singleton(
			'service.queue',
			function ( $c ) {
				return new \BeepBeep\AltText\Services\Queue_Service(
					$c->get( 'event_bus' )
				);
			}
		);
	}

	/**
	 * Register controller services.
	 *
	 * @since 5.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_controllers( Container $container ): void {
		// Auth controller.
		$container->singleton(
			'controller.auth',
			function ( $c ) {
				return new \BeepBeep\AltText\Controllers\Auth_Controller(
					$c->get( 'service.auth' )
				);
			}
		);

		// License controller.
		$container->singleton(
			'controller.license',
			function ( $c ) {
				return new \BeepBeep\AltText\Controllers\License_Controller(
					$c->get( 'service.license' )
				);
			}
		);

		// Generation controller.
		$container->singleton(
			'controller.generation',
			function ( $c ) {
				return new \BeepBeep\AltText\Controllers\Generation_Controller(
					$c->get( 'service.generation' )
				);
			}
		);

		// Queue controller.
		$container->singleton(
			'controller.queue',
			function ( $c ) {
				return new \BeepBeep\AltText\Controllers\Queue_Controller(
					$c->get( 'service.queue' )
				);
			}
		);
	}
}
