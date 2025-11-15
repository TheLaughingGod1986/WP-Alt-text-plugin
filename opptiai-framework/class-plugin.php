<?php
/**
 * OpptiAI Framework - Plugin Module Registrar
 *
 * Manages OpptiAI plugin modules and their lifecycle
 *
 * @package OpptiAI\Framework
 */

namespace OpptiAI\Framework;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Registered modules
	 *
	 * @var array
	 */
	private static $modules = array();

	/**
	 * Register a plugin module
	 *
	 * @param array $config Module configuration
	 * @return bool True if registered successfully
	 */
	public static function register_module( $config ) {
		$defaults = array(
			'id'          => '',
			'name'        => '',
			'slug'        => '',
			'path'        => '',
			'url'         => '',
			'version'     => '1.0.0',
			'assets'      => array(
				'css' => array(),
				'js'  => array(),
			),
			'menu'        => array(
				'title'      => '',
				'parent'     => '',
				'capability' => 'manage_options',
				'icon'       => '',
				'position'   => null,
			),
			'rest_routes' => array(),
			'hooks'       => array(),
		);

		$config = wp_parse_args( $config, $defaults );

		// Validate required fields
		if ( empty( $config['id'] ) || empty( $config['name'] ) ) {
			return false;
		}

		// Set module URL if not provided
		if ( empty( $config['url'] ) && ! empty( $config['path'] ) ) {
			$config['url'] = plugins_url( '', $config['path'] . 'index.php' );
		}

		// Store module
		self::$modules[ $config['id'] ] = $config;

		// Register module assets
		add_action( 'admin_enqueue_scripts', function() use ( $config ) {
			self::enqueue_module_assets( $config );
		} );

		// Register module menu
		if ( ! empty( $config['menu']['title'] ) ) {
			add_action( 'admin_menu', function() use ( $config ) {
				self::register_module_menu( $config );
			} );
		}

		// Register REST routes
		if ( ! empty( $config['rest_routes'] ) ) {
			add_action( 'rest_api_init', function() use ( $config ) {
				self::register_module_rest_routes( $config );
			} );
		}

		// Register custom hooks
		if ( ! empty( $config['hooks'] ) ) {
			self::register_module_hooks( $config );
		}

		do_action( 'opptiai_module_registered', $config );

		return true;
	}

	/**
	 * Get registered module
	 *
	 * @param string $module_id Module ID
	 * @return array|null Module config or null
	 */
	public static function get_module( $module_id ) {
		return isset( self::$modules[ $module_id ] ) ? self::$modules[ $module_id ] : null;
	}

	/**
	 * Get all registered modules
	 *
	 * @return array All modules
	 */
	public static function get_modules() {
		return self::$modules;
	}

	/**
	 * Check if module is registered
	 *
	 * @param string $module_id Module ID
	 * @return bool True if registered
	 */
	public static function has_module( $module_id ) {
		return isset( self::$modules[ $module_id ] );
	}

	/**
	 * Enqueue module assets
	 *
	 * @param array $config Module configuration
	 * @return void
	 */
	private static function enqueue_module_assets( $config ) {
		// Only load on relevant admin pages
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Check if we're on a module page
		$is_module_page = false !== strpos( $screen->id, $config['slug'] ) || false !== strpos( $screen->id, $config['id'] );

		if ( ! $is_module_page ) {
			return;
		}

		// Enqueue CSS
		if ( ! empty( $config['assets']['css'] ) ) {
			foreach ( $config['assets']['css'] as $handle => $file ) {
				if ( is_numeric( $handle ) ) {
					$handle = $config['id'] . '-' . sanitize_key( basename( $file, '.css' ) );
				}

				wp_enqueue_style(
					$handle,
					$config['url'] . '/assets/css/' . $file,
					array( 'opptiai-framework-admin' ),
					$config['version']
				);
			}
		}

		// Enqueue JS
		if ( ! empty( $config['assets']['js'] ) ) {
			foreach ( $config['assets']['js'] as $handle => $file ) {
				if ( is_numeric( $handle ) ) {
					$handle = $config['id'] . '-' . sanitize_key( basename( $file, '.js' ) );
				}

				wp_enqueue_script(
					$handle,
					$config['url'] . '/assets/js/' . $file,
					array( 'jquery', 'opptiai-framework-admin' ),
					$config['version'],
					true
				);
			}
		}
	}

	/**
	 * Register module admin menu
	 *
	 * @param array $config Module configuration
	 * @return void
	 */
	private static function register_module_menu( $config ) {
		$menu = $config['menu'];

		// If parent menu specified, add as submenu
		if ( ! empty( $menu['parent'] ) ) {
			add_submenu_page(
				$menu['parent'],
				$config['name'],
				$menu['title'],
				$menu['capability'],
				$config['slug'],
				function() use ( $config ) {
					do_action( 'opptiai_module_page_' . $config['id'] );
				}
			);
		} else {
			// Add as top-level menu
			add_menu_page(
				$config['name'],
				$menu['title'],
				$menu['capability'],
				$config['slug'],
				function() use ( $config ) {
					do_action( 'opptiai_module_page_' . $config['id'] );
				},
				$menu['icon'],
				$menu['position']
			);
		}
	}

	/**
	 * Register module REST routes
	 *
	 * @param array $config Module configuration
	 * @return void
	 */
	private static function register_module_rest_routes( $config ) {
		$namespace = 'opptiai/' . $config['id'];

		foreach ( $config['rest_routes'] as $route => $args ) {
			register_rest_route( $namespace, $route, $args );
		}
	}

	/**
	 * Register module hooks
	 *
	 * @param array $config Module configuration
	 * @return void
	 */
	private static function register_module_hooks( $config ) {
		foreach ( $config['hooks'] as $hook ) {
			if ( ! isset( $hook['type'], $hook['name'], $hook['callback'] ) ) {
				continue;
			}

			$priority = isset( $hook['priority'] ) ? $hook['priority'] : 10;
			$args     = isset( $hook['args'] ) ? $hook['args'] : 1;

			if ( 'action' === $hook['type'] ) {
				add_action( $hook['name'], $hook['callback'], $priority, $args );
			} elseif ( 'filter' === $hook['type'] ) {
				add_filter( $hook['name'], $hook['callback'], $priority, $args );
			}
		}
	}

	/**
	 * Unregister a module
	 *
	 * @param string $module_id Module ID
	 * @return bool True if unregistered
	 */
	public static function unregister_module( $module_id ) {
		if ( ! isset( self::$modules[ $module_id ] ) ) {
			return false;
		}

		do_action( 'opptiai_module_unregistered', self::$modules[ $module_id ] );

		unset( self::$modules[ $module_id ] );

		return true;
	}
}
