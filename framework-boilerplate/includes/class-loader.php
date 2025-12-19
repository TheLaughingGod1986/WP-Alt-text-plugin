<?php
/**
 * Hook Loader
 *
 * Registers and orchestrates all WordPress actions and filters for the plugin.
 * Based on the WordPress Plugin Boilerplate loader pattern.
 *
 * @package MyPlugin
 * @since   1.0.0
 */

namespace MyPlugin;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loader Class
 *
 * Maintains a collection of actions and filters to be registered with WordPress.
 * Provides a clean interface for registering hooks without polluting global scope.
 *
 * @since 1.0.0
 */
class Loader {

	/**
	 * Actions to be registered with WordPress.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	protected $actions = array();

	/**
	 * Filters to be registered with WordPress.
	 *
	 * @since  1.0.0
	 * @var    array
	 */
	protected $filters = array();

	/**
	 * Add a new action to be registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The WordPress action hook name.
	 * @param object $component     The instance on which the hook is defined.
	 * @param string $callback      The method name to call on the component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Add a new filter to be registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The WordPress filter hook name.
	 * @param object $component     The instance on which the hook is defined.
	 * @param string $callback      The method name to call on the component.
	 * @param int    $priority      Optional. Hook priority. Default 10.
	 * @param int    $accepted_args Optional. Number of arguments. Default 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all hooks with WordPress.
	 *
	 * Loops through all registered filters and actions and registers
	 * them with WordPress using add_filter() and add_action().
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Register filters
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Register actions
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
