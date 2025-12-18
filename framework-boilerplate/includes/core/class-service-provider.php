<?php
/**
 * Service Provider
 *
 * Central registry for all plugin services.
 * This is where you configure dependency injection and register all services.
 *
 * @package MyPlugin\Core
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MyPlugin\Core;

/**
 * Service_Provider Class
 *
 * Registers all services with the dependency injection container.
 * Organize services into logical groups (core, API clients, repositories, services, controllers).
 *
 * @since 1.0.0
 */
class Service_Provider {

	/**
	 * Register all services.
	 *
	 * This is the main entry point for service registration.
	 * Called during plugin initialization.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	public static function register( Container $container ): void {
		// Register framework core services
		self::register_core( $container );

		// Register API clients and external services
		self::register_api_clients( $container );

		// Register data repositories
		self::register_repositories( $container );

		// Register business logic services
		self::register_services( $container );

		// Register HTTP controllers
		self::register_controllers( $container );
	}

	/**
	 * Register core framework services.
	 *
	 * These are the essential framework components that other services depend on.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_core( Container $container ): void {
		// Event Bus - Singleton
		// Used for event-driven communication between components
		$container->singleton(
			'event_bus',
			function ( $c ) {
				return new Event_Bus();
			}
		);

		// Router - Singleton
		// Handles AJAX and REST API routing
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
	 * Services that communicate with external APIs.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_api_clients( Container $container ): void {
		// Example: External API client
		/*
		$container->singleton(
			'api.example',
			function ( $c ) {
				return new \MyPlugin\API\Example_API_Client(
					get_option( 'myplugin_api_key' )
				);
			}
		);
		*/
	}

	/**
	 * Register repository services.
	 *
	 * Repositories handle data access layer (database queries, caching, etc).
	 * They abstract the data storage implementation from business logic.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_repositories( Container $container ): void {
		// Example: User repository
		/*
		$container->singleton(
			'repository.users',
			function ( $c ) {
				return new \MyPlugin\Repositories\User_Repository();
			}
		);
		*/
	}

	/**
	 * Register business logic services.
	 *
	 * Services contain the core business logic of your plugin.
	 * They should be framework-agnostic and easily testable.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_services( Container $container ): void {
		// Example Service - Singleton
		$container->singleton(
			'service.example',
			function ( $c ) {
				return new \MyPlugin\Services\Example_Service(
					$c->get( 'event_bus' )
				);
			}
		);

		// Add more services here as you build your plugin
		/*
		$container->singleton(
			'service.authentication',
			function ( $c ) {
				return new \MyPlugin\Services\Authentication_Service(
					$c->get( 'api.example' ),
					$c->get( 'event_bus' )
				);
			}
		);
		*/
	}

	/**
	 * Register controller services.
	 *
	 * Controllers handle HTTP requests and delegate to services.
	 * They should be thin - just input validation and service delegation.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container DI container instance.
	 * @return void
	 */
	private static function register_controllers( Container $container ): void {
		// Example Controller - Singleton
		$container->singleton(
			'controller.example',
			function ( $c ) {
				return new \MyPlugin\Controllers\Example_Controller(
					$c->get( 'service.example' )
				);
			}
		);

		// Add more controllers here
		/*
		$container->singleton(
			'controller.authentication',
			function ( $c ) {
				return new \MyPlugin\Controllers\Auth_Controller(
					$c->get( 'service.authentication' )
				);
			}
		);
		*/
	}
}
