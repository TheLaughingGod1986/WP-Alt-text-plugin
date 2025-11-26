<?php
/**
 * Service Interface
 *
 * Defines the contract for framework services.
 * Services are core components that provide shared functionality.
 *
 * @package Optti\Framework\Interfaces
 */

namespace Optti\Framework\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Service
 *
 * All framework services must implement this interface.
 */
interface Service {

	/**
	 * Initialize the service.
	 *
	 * This method is called when the service is first accessed.
	 * Use this to set up the service and perform any initialization.
	 *
	 * @return void
	 */
	public function init();

	/**
	 * Check if the service is available.
	 *
	 * @return bool True if service is available, false otherwise.
	 */
	public function is_available();
}

