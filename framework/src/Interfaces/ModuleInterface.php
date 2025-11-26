<?php
/**
 * Module Interface
 *
 * Defines the contract for all plugin modules.
 * Each module is an independent feature that can be registered with the Plugin class.
 *
 * @package Optti\Framework\Interfaces
 */

namespace Optti\Framework\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ModuleInterface
 *
 * All plugin modules must implement this interface.
 */
interface ModuleInterface {

	/**
	 * Get the module identifier.
	 *
	 * @return string Unique module identifier.
	 */
	public function get_id();

	/**
	 * Get the module name.
	 *
	 * @return string Human-readable module name.
	 */
	public function get_name();

	/**
	 * Initialize the module.
	 *
	 * This method is called when the module is registered.
	 * Use this to set up hooks, filters, and other initialization.
	 *
	 * @return void
	 */
	public function init();

	/**
	 * Check if the module is active.
	 *
	 * @return bool True if module is active, false otherwise.
	 */
	public function is_active();
}

