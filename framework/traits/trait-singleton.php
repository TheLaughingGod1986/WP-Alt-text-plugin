<?php
/**
 * Singleton Trait
 *
 * Provides singleton pattern implementation for framework classes.
 *
 * @package Optti\Framework\Traits
 */

namespace Optti\Framework\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait Singleton
 *
 * Use this trait to make a class a singleton.
 */
trait Singleton {

	/**
	 * Instance of the class.
	 *
	 * @var static|null
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return static Instance of the class.
	 */
	public static function instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Prevent cloning.
	 */
	protected function __clone() {
		// Prevent cloning.
	}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}

