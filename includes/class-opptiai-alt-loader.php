<?php
/**
 * Register and orchestrate actions and filters for the plugin.
 *
 * Based on the loader that ships with the WordPress Plugin Boilerplate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Opptiai_Alt_Loader {

	/**
	 * The array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions = [];

	/**
	 * The array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters = [];

	/**
	 * Add a new action to the collection.
	 *
	 * @param string   $hook          The WordPress action to hook into.
	 * @param object   $component     The instance on which the hook is defined.
	 * @param string   $callback      The method to call on the component.
	 * @param int      $priority      Optional. The priority at which to fire. Default 10.
	 * @param int      $accepted_args Optional. Number of accepted args. Default 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}

	/**
	 * Add a new filter to the collection.
	 *
	 * @param string   $hook          The WordPress filter to hook into.
	 * @param object   $component     The instance on which the hook is defined.
	 * @param string   $callback      The method to call on the component.
	 * @param int      $priority      Optional. The priority at which to fire. Default 10.
	 * @param int      $accepted_args Optional. Number of accepted args. Default 1.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}

	/**
	 * Register the filters and actions with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
