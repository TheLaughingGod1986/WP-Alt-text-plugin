<?php
declare(strict_types=1);

namespace BeepBeep\AltText\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dependency Injection Container
 *
 * Simple DI container for managing service instantiation and dependencies.
 * Supports both factory and singleton patterns.
 *
 * @package BeepBeep\AltText\Core
 * @since   5.0.0
 */
class Container {
	/**
	 * Service factory definitions.
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Singleton service instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Service aliases.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = array();

	/**
	 * Register a service factory.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory function that returns the service instance.
	 * @return void
	 */
	public function register(string $name, callable $factory): void {
		$this->services[ $name ] = $factory;
	}

	/**
	 * Register a singleton service.
	 *
	 * Singleton services are instantiated once and reused.
	 *
	 * @since 5.0.0
	 *
	 * @param string   $name    Service name.
	 * @param callable $factory Factory function that returns the service instance.
	 * @return void
	 */
	public function singleton(string $name, callable $factory): void {
		$this->register(
			$name,
			function () use ( $name, $factory ) {
				if ( ! isset( $this->instances[ $name ] ) ) {
					$this->instances[ $name ] = $factory( $this );
				}
				return $this->instances[ $name ];
			}
		);
	}

	/**
	 * Register a service alias.
	 *
	 * Allows accessing a service by multiple names.
	 *
	 * @since 5.0.0
	 *
	 * @param string $alias   Alias name.
	 * @param string $service Target service name.
	 * @return void
	 */
	public function alias(string $alias, string $service): void {
		$this->aliases[ $alias ] = $service;
	}

	/**
	 * Get a service instance.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name Service name or alias.
	 * @return mixed Service instance.
	 * @throws \Exception If service is not found.
	 */
	public function get(string $name) {
		// Resolve alias.
		$service_name = $this->aliases[ $name ] ?? $name;

		if ( ! isset( $this->services[ $service_name ] ) ) {
			throw new \Exception( "Service '{$service_name}' not found in container." );
		}

		return $this->services[ $service_name ]( $this );
	}

	/**
	 * Check if service exists.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name Service name or alias.
	 * @return bool True if service exists.
	 */
	public function has(string $name): bool {
		$service_name = $this->aliases[ $name ] ?? $name;
		return isset( $this->services[ $service_name ] );
	}

	/**
	 * Bind a service instance directly.
	 *
	 * Useful for binding existing instances or values.
	 *
	 * @since 5.0.0
	 *
	 * @param string $name     Service name.
	 * @param mixed  $instance Service instance.
	 * @return void
	 */
	public function instance(string $name, $instance): void {
		$this->singleton(
			$name,
			function () use ( $instance ) {
				return $instance;
			}
		);
	}

	/**
	 * Make a class instance with dependency injection.
	 *
	 * Automatically resolves constructor dependencies from the container.
	 *
	 * @since 5.0.0
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return mixed Class instance.
	 * @throws \ReflectionException If class doesn't exist.
	 * @throws \Exception If dependencies cannot be resolved.
	 */
	public function make(string $class_name) {
		$reflection = new \ReflectionClass( $class_name );

		$constructor = $reflection->getConstructor();
		if ( null === $constructor ) {
			return new $class_name();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = array();

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();

			if ( null === $type || $type->isBuiltin() ) {
				if ( $parameter->isDefaultValueAvailable() ) {
					$dependencies[] = $parameter->getDefaultValue();
					continue;
				}

				throw new \Exception(
					"Cannot resolve parameter '{$parameter->getName()}' in {$class_name}"
				);
			}

			$type_name = $type->getName();

			if ( $this->has( $type_name ) ) {
				$dependencies[] = $this->get( $type_name );
			} elseif ( class_exists( $type_name ) ) {
				$dependencies[] = $this->make( $type_name );
			} else {
				throw new \Exception( "Cannot resolve dependency '{$type_name}' for {$class_name}" );
			}
		}

		return $reflection->newInstanceArgs( $dependencies );
	}

	/**
	 * Clear all singleton instances.
	 *
	 * Useful for testing.
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->instances = array();
	}

	/**
	 * Get all registered service names.
	 *
	 * @since 5.0.0
	 *
	 * @return array<string> Service names.
	 */
	public function getRegistered(): array {
		return array_keys( $this->services );
	}
}
